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

// C-4: la omisión salta TODA la validación de flujo entre estaciones —
// solo roles con 'cambiar_cualquier_estatus' (jefe_piso, director, dir_admin,
// administracion, dueno, desarrollo; ver api/permisos.php).
if ($omision && !tienePermiso($usuario['rol'], 'cambiar_cualquier_estatus')) {
    jsonResponse(['error' => 'La omisión de estaciones requiere a un jefe de piso o dirección'], 403);
}



$estatusValidos = [

    'pendiente','en_corte','cortado','canteado',

    'trazo','taladro','en_horno','terminado','entregado','reproceso'

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



$estatusAnterior = $pieza['estatus'];

$FLUJO_ORDEN = ['pendiente','en_corte','cortado','canteado','trazo','taladro','en_horno','terminado','entregado'];

// El nombre también sale de la sesión (ya no se consulta con el id del body)
$nombreUsuario = $usuario['nombre'];

// Actualizar estatus + bitácora en una sola transacción:
// si falla cualquier INSERT de historial, la pieza no queda movida a medias.
$db->beginTransaction();
try {
    $db->prepare('UPDATE piezas SET estatus = ?, updated_at = NOW() WHERE qr_code = ?')
       ->execute([$estatus, $qr]);

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
    jsonResponse(['error' => 'Error al actualizar la pieza'], 500);
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
    try {
        $ord = $db->prepare('SELECT folio, asesor, cliente_nombre FROM ordenes WHERE id = ?');
        $ord->execute([$pieza['orden_id']]);
        $ord = $ord->fetch();

        if ($ord) {
            // Alerta a Lina (administracion): verificar pago
            $db->prepare("
                INSERT INTO notificaciones (tipo, titulo, mensaje, folio, orden_id, rol_destino, leida)
                VALUES ('orden_terminada', 'Orden terminada — verificar pago', ?, ?, ?, 'administracion', 0)
            ")->execute([
                'La orden ' . $ord['folio'] . ' (' . $ord['cliente_nombre'] . ') está terminada. Verificar estado de pago.',
                $ord['folio'],
                $pieza['orden_id']
            ]);

            // Alerta al asesor comercial: programar entrega
            $asesor_row = $db->prepare("SELECT id FROM usuarios WHERE nombre = ? AND rol = 'comercial' LIMIT 1");
            $asesor_row->execute([$ord['asesor']]);
            $asesor_row = $asesor_row->fetch(PDO::FETCH_ASSOC);
            $db->prepare("
                INSERT INTO notificaciones (tipo, titulo, mensaje, folio, orden_id, rol_destino, usuario_id_dest, leida)
                VALUES ('orden_terminada', 'Orden lista — programar entrega', ?, ?, ?, 'comercial', ?, 0)
            ")->execute([
                'La orden ' . $ord['folio'] . ' (' . $ord['cliente_nombre'] . ') está lista. Programa la entrega.',
                $ord['folio'],
                $pieza['orden_id'],
                $asesor_row['id'] ?? null
            ]);
        }
    } catch (Exception $ignored) {}

    // Envío WA automático (una sola vez) cuando toda la orden está terminada
    try {
        $stmtWa = $db->prepare('
            SELECT o.wa_lista_enviado, o.cliente_nombre, o.folio,
                   COALESCE(o.cliente_id, c.cliente_id) as cliente_id,
                   COALESCE(cl.telefono, cl2.telefono) as telefono,
                   COALESCE(cl.telefono_alterno, cl2.telefono_alterno) as telefono_alterno,
                   c.proyecto,
                   (SELECT COUNT(*) FROM piezas WHERE orden_id = o.id) as total_piezas
            FROM ordenes o
            LEFT JOIN clientes cl ON cl.id = o.cliente_id
            LEFT JOIN cotizaciones c ON c.orden_id = o.id
            LEFT JOIN clientes cl2 ON cl2.id = c.cliente_id
            WHERE o.id = ?
        ');
        $stmtWa->execute([$pieza['orden_id']]);
        $ordWa = $stmtWa->fetch(PDO::FETCH_ASSOC);

        if ($ordWa && !$ordWa['wa_lista_enviado']) {
            // Verificar que ninguna pieza quede fuera de terminado/entregado
            $stmtPend = $db->prepare('
                SELECT COUNT(*) FROM piezas
                WHERE orden_id = ? AND estatus NOT IN ("terminado","entregado")
            ');
            $stmtPend->execute([$pieza['orden_id']]);
            if ((int)$stmtPend->fetchColumn() === 0) {
                $telRaw = preg_replace('/\D/', '', $ordWa['telefono_alterno'] ?: $ordWa['telefono']);
                if ($telRaw && strlen($telRaw) >= 10) {
                    if (strlen($telRaw) === 10) $telRaw = '52' . $telRaw;
                    $proyectoWa = trim($ordWa['proyecto'] ?: $ordWa['cliente_nombre']);
                    $resWa = enviarMensajeWA([
                        'messaging_product' => 'whatsapp',
                        'to'   => $telRaw,
                        'type' => 'template',
                        'template' => [
                            'name'     => 'orden_lista',
                            'language' => ['code' => 'es_MX'],
                            'components' => [[
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => substr(strip_tags($ordWa['cliente_nombre']), 0, 60)],
                                    ['type' => 'text', 'text' => substr(strip_tags($ordWa['folio']), 0, 20)],
                                    ['type' => 'text', 'text' => substr(strip_tags($proyectoWa), 0, 100)],
                                    ['type' => 'text', 'text' => (string)$ordWa['total_piezas']],
                                ]
                            ]]
                        ]
                    ]);

                    $waId = $resWa['data']['messages'][0]['id'] ?? null;
                    if ($resWa['code'] === 200 && $waId) {
                        // Marcar enviado solo si Meta confirmó
                        $db->prepare('UPDATE ordenes SET wa_lista_enviado = 1 WHERE id = ?')
                           ->execute([$pieza['orden_id']]);

                        // Guardar en inbox para visibilidad
                        $tel10 = substr($telRaw, -10);
                        $stmtCv = $db->prepare("SELECT id, cliente_id FROM whatsapp_conversaciones WHERE RIGHT(REGEXP_REPLACE(telefono,'[^0-9]',''),10) = ?");
                        $stmtCv->execute([$tel10]);
                        $conv = $stmtCv->fetch(PDO::FETCH_ASSOC);
                        if (!$conv) {
                            $db->prepare("INSERT INTO whatsapp_conversaciones (cliente_id, telefono, ultima_actividad) VALUES (?,?,NOW())")
                               ->execute([$ordWa['cliente_id'] ?? null, $telRaw]);
                            $convId = $db->lastInsertId();
                        } else {
                            $convId = $conv['id'];
                        }
                        $contenidoLog = '[Plantilla orden_lista] Orden ' . $ordWa['folio'] . ' lista — ' . $ordWa['total_piezas'] . ' piezas — ' . $proyectoWa;
                        $db->prepare("INSERT INTO whatsapp_mensajes (conversacion_id, direccion, contenido, tipo, wa_message_id, enviado_por) VALUES (?, 'outbound', ?, 'texto', ?, 'sistema')")
                           ->execute([$convId, $contenidoLog, $waId]);
                        $db->prepare("UPDATE whatsapp_conversaciones SET ultima_actividad=NOW() WHERE id=?")
                           ->execute([$convId]);
                    } else {
                        error_log('APEX WA orden_lista fallo: orden=' . $ordWa['folio'] . ' tel=' . $telRaw . ' resp=' . json_encode($resWa['data']));
                    }
                }
            }
        }
    } catch (Exception $ignored) {}
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