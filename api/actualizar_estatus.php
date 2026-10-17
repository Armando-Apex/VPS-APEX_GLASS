<?php

// ============================================================

//  APEX GLASS - API: Actualizar estatus de pieza v2

//  Archivo: api/actualizar_estatus.php

//  M&#233;todo: POST { qr_code, estatus, usuario_id, notas }

//

//  Flujo: pendiente &#8594; en_corte &#8594; canteado &#8594; trazo &#8594; taladro

//         &#8594; en_horno &#8594; terminado &#8594; entregado

// ============================================================



require_once 'config.php';
require_once 'permisos.php';
require_once 'wa_helper.php';
require_once __DIR__ . '/helpers/orden_terminada_lib.php'; // UPD-720
$usuario = requireSessionApi();



header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: https://apex.glass');



if ($_SERVER['REQUEST_METHOD'] !== 'POST')

    jsonResponse(['error' => 'M&#233;todo no permitido'], 405);



$input = json_decode(file_get_contents('php://input'), true);

if (!$input) jsonResponse(['error' => 'JSON inv&#225;lido'], 400);



$qr      = trim($input['qr_code']    ?? '');

$estatus = trim($input['estatus']    ?? '');

// El usuario es SIEMPRE el de la sesión — el usuario_id del body se ignora
// (antes podía suplantar a cualquier empleado en la bitácora).
$userId  = (int)$usuario['id'];

$notas   = trim($input['notas']      ?? '');

$omision = !empty($input['omision']) ? 1 : 0;

// La omisión salta la validación de flujo entre estaciones — permitida a
// jefe_piso/dirección ('cambiar_cualquier_estatus') Y a los operadores de
// piso en su propia estación ('escanear_estacion_propia'; revertido a
// petición explícita, C-4 de UPD-361 lo había restringido solo a jefe_piso+).
if ($omision
    && !tienePermiso($usuario['rol'], 'cambiar_cualquier_estatus')
    && !tienePermiso($usuario['rol'], 'escanear_estacion_propia')) {
    jsonResponse(['error' => 'No tienes permiso para confirmar una omisión'], 403);
}



// S2-01: 'reproceso' ya no es un destino de escaneo. El reproceso se hace por
// api/reproceso.php (reset a 'pendiente' con bitácora y permisos propios);
// aquí solo llegaba a un callejón sin salida: ningún flujo acepta 'reproceso'
// como origen, así que la pieza quedaba atascada.
$estatusValidos = [

    'pendiente','en_corte','cortado','canteado',

    'trazo','taladro','en_horno','terminado','entregado'

];




if (!$qr) jsonResponse(['error' => 'qr_code requerido'], 400);

if (!in_array($estatus, $estatusValidos))

    jsonResponse(['error' => 'Estatus inv&#225;lido: ' . $estatus], 400);



$db = getDB();



// Buscar pieza

$stmt = $db->prepare('

    SELECT p.*, o.folio, o.cliente_nombre, o.id as orden_id_val, o.tipo AS orden_tipo,
           o.estado AS orden_estado

    FROM piezas p

    JOIN ordenes o ON o.id = p.orden_id

    WHERE p.qr_code = ?

');

$stmt->execute([$qr]);

$pieza = $stmt->fetch();

if (!$pieza) jsonResponse(['error' => 'Código QR no encontrado'], 404);

// A-4: solo se produce sobre órdenes activas (con VoBo de Finanzas).
// Bloquea pendiente_vobo (sin anticipo), cancelada, rechazada y entregada.
if ($pieza['orden_estado'] !== 'activa') {
    jsonResponse(['error' => 'La orden ' . $pieza['folio'] . ' no está activa (estado: ' . $pieza['orden_estado'] . '); no se puede registrar avance'], 409);
}



// Validar flujo
// Para piezas sin templado: canteado→terminado y taladro→terminado son válidos
$estatusActual = $pieza['estatus'];

if ($pieza['orden_tipo'] === 'maquila') {
    // Flujo dinámico: solo las estaciones que la pieza realmente necesita
    $FLUJO_COMPLETO = ['pendiente','en_corte','cortado','canteado','trazo','taladro','en_horno','terminado','entregado'];
    $requiereCorte    = (int)($pieza['requiere_corte'] ?? 0) === 1;
    $requiereCanteado = trim($pieza['cpb'] ?? 'No') !== 'No' && trim($pieza['cpb'] ?? '') !== '';
    $requiereTaladro  = ((int)($pieza['tp'] ?? 0) + (int)($pieza['ta'] ?? 0)) > 0;
    $requiereHorno    = (int)($pieza['requiere_templado'] ?? 0) === 1;

    $aplicables = ['pendiente'];
    if ($requiereCorte)    { $aplicables[] = 'en_corte'; $aplicables[] = 'cortado'; }
    if ($requiereCanteado) { $aplicables[] = 'canteado'; }
    if ($requiereTaladro)  { $aplicables[] = 'trazo'; $aplicables[] = 'taladro'; }
    if ($requiereHorno)    { $aplicables[] = 'en_horno'; }
    $aplicables[] = 'terminado';
    $aplicables[] = 'entregado';

    $idxDestino = array_search($estatus, $aplicables);
    if ($idxDestino === false) {
        jsonResponse(['error' => 'La pieza no requiere el estatus "' . $estatus . '"'], 400);
    }

    if ($estatus === 'pendiente') {
        // Reproceso: mismo whitelist de orígenes válidos que suministro, filtrado
        // a las estaciones que esta pieza realmente tiene (evita reset sin validar
        // desde cualquier estatus, ej. vía el botón "Reproceso" de operador.php).
        $origenesValidos = array_intersect(['canteado', 'trazo', 'taladro', 'en_horno'], $aplicables);
        if (!$omision && !in_array($estatusActual, $origenesValidos)) {
            jsonResponse(['error' => 'La pieza est&#225; en "' . $estatusActual . '" y no puede pasar a "' . $estatus . '"'], 400);
        }
    } else {
        $predecesorValido = $idxDestino > 0 ? $aplicables[$idxDestino - 1] : null;
        if (!$omision && $predecesorValido !== null && $estatusActual !== $predecesorValido) {
            jsonResponse(['error' => 'La pieza est&#225; en "' . $estatusActual . '" y no puede pasar a "' . $estatus . '"'], 400);
        }
    }
} else {
    $sinTemplado = (int)($pieza['requiere_templado'] ?? 1) === 0;

    $FLUJO_PREVIO = [
        'en_corte'  => ['pendiente'],
        'cortado'   => ['en_corte'],
        'canteado'  => ['cortado'],
        'trazo'     => ['canteado'],
        'taladro'   => ['trazo'],
        'en_horno'  => ['taladro','canteado'],
        'terminado' => $sinTemplado ? ['taladro','canteado','en_horno'] : ['en_horno'],
        'entregado' => ['terminado'],
        'pendiente' => ['canteado','trazo','taladro','en_horno'],
    ];
    if (!$omision && isset($FLUJO_PREVIO[$estatus]) && !in_array($estatusActual, $FLUJO_PREVIO[$estatus])) {
        jsonResponse(['error' => 'La pieza est&#225; en "' . $estatusActual . '" y no puede pasar a "' . $estatus . '"'], 400);
    }
}



// Marcar entregado requiere el permiso específico 'registrar_entrega' (chofer, comercial,
// dir_admin, administracion, dueno, desarrollo) — jefe_piso puede mover el flujo de producción
// pero no puede cerrar la entrega.
if ($estatus === 'entregado' && !tienePermiso($usuario['rol'], 'registrar_entrega')) {
    jsonResponse(['error' => 'No tienes permiso para marcar una pieza como entregada'], 403);
}

$estatusAnterior = $pieza['estatus'];
// EC-2: copia sin mutar — $estatusAnterior se reasigna más abajo en el caso de
// omisión con salto múltiple; el guard del UPDATE necesita el valor ORIGINAL
// leído al inicio de la request, no el recalculado.
$estatusOriginal = $pieza['estatus'];

$FLUJO_ORDEN = ['pendiente','en_corte','cortado','canteado','trazo','taladro','en_horno','terminado','entregado'];

// El nombre también sale de la sesión (ya no se consulta con el id del body)
$nombreUsuario = $usuario['nombre'];

// Actualizar estatus + bitácora en una sola transacción:
// si falla cualquier INSERT de historial, la pieza no queda movida a medias.
$db->beginTransaction();
try {
    // EC-2: guard de carrera — la validación de flujo (arriba) se hizo sobre el
    // estatus leído al inicio de la request; sin condicionar el UPDATE al mismo
    // estatus, dos escaneos casi simultáneos del mismo QR pasaban los dos la
    // validación y ambos movían la pieza (historial/WA duplicados). Mismo patrón
    // que ya usa api/salidas.php:150.
    $stUpd = $db->prepare('UPDATE piezas SET estatus = ?, updated_at = NOW() WHERE qr_code = ? AND estatus = ?');
    $stUpd->execute([$estatus, $qr, $estatusOriginal]);
    if ($stUpd->rowCount() !== 1) {
        throw new Exception('Esta pieza ya fue movida por otro escaneo — recarga e intenta de nuevo.');
    }

    // Registrar en historial
    // Si es omisión con salto de múltiples pasos, insertar un registro por cada paso saltado
    if ($omision) {
        $idxAnterior = array_search($estatusAnterior, $FLUJO_ORDEN);
        $idxNuevo    = array_search($estatus,          $FLUJO_ORDEN);
        if ($idxAnterior !== false && $idxNuevo !== false && ($idxNuevo - $idxAnterior) > 1) {
            for ($i = $idxAnterior; $i < $idxNuevo - 1; $i++) {
                $db->prepare('
                    INSERT INTO historial_estatus
                        (pieza_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, notas, omision)
                    VALUES (?,?,?,?,?,?,1)
                ')->execute([
                    $pieza['id'],
                    $FLUJO_ORDEN[$i],
                    $FLUJO_ORDEN[$i + 1],
                    $userId ?: null,
                    $nombreUsuario,
                    'OMISIÓN AUTOMÁTICA'
                ]);
            }
            // El registro final cubre solo el último paso
            $estatusAnterior = $FLUJO_ORDEN[$idxNuevo - 1];
        }
    }

    $db->prepare('
        INSERT INTO historial_estatus
            (pieza_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, notas, omision)
        VALUES (?,?,?,?,?,?,?)
    ')->execute([
        $pieza['id'],
        $estatusAnterior,
        $estatus,
        $userId ?: null,
        $nombreUsuario,
        $notas,
        $omision
    ]);

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    // EC-2: el mensaje del guard de carrera es útil para el usuario (sabe que
    // debe recargar); cualquier otro fallo real sigue devolviendo 500 genérico.
    $esGuardCarrera = strpos($e->getMessage(), 'ya fue movida por otro escaneo') !== false;
    jsonResponse(['error' => $esGuardCarrera ? $e->getMessage() : 'Error al actualizar la pieza'], $esGuardCarrera ? 409 : 500);
}



// Verificar si toda la orden lleg&#243; al mismo estatus

$stmt = $db->prepare('

    SELECT COUNT(*) as total, SUM(estatus = ?) as coinciden

    FROM piezas WHERE orden_id = ?

');

$stmt->execute([$estatus, $pieza['orden_id']]);

$check = $stmt->fetch();

$ordenCompleta = ($check['total'] == $check['coinciden']);

// Cuando toda la orden llega a TERMINADO: alertar a Lina y al asesor
if ($ordenCompleta && $estatus === 'terminado') {
    notificarOrdenTerminada($db, $pieza['orden_id']); // UPD-720: helper compartido con Admin Órdenes

    // Envío WA automático (una sola vez) cuando toda la orden está terminada
    waEnviarOrdenLista($db, $pieza['orden_id']);
}

jsonResponse([

    'ok'               => true,

    'pieza_id'         => $pieza['id'],

    'folio'            => $pieza['folio'],

    'cliente'          => $pieza['cliente_nombre'],

    'partida'          => $pieza['partida'],

    'pieza'            => $pieza['pieza_num'] . '/' . $pieza['pieza_total'],

    'cristal'          => $pieza['cristal'],

    'medidas'          => $pieza['ancho_mm'] . '&#215;' . $pieza['alto_mm'],

    'estatus_anterior' => $estatusAnterior,

    'estatus_nuevo'    => $estatus,

    'orden_completa'   => $ordenCompleta,

]);