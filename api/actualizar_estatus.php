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

if (session_status() === PHP_SESSION_NONE) session_start();



header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: *');



if ($_SERVER['REQUEST_METHOD'] !== 'POST')

    jsonResponse(['error' => 'M&#233;todo no permitido'], 405);



$input = json_decode(file_get_contents('php://input'), true);

if (!$input) jsonResponse(['error' => 'JSON inv&#225;lido'], 400);



$qr      = trim($input['qr_code']    ?? '');

$estatus = trim($input['estatus']    ?? '');

$userId  = intval($input['usuario_id'] ?? 0);

$notas   = trim($input['notas']      ?? '');

$omision = !empty($input['omision']) ? 1 : 0;



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

    SELECT p.*, o.folio, o.cliente_nombre, o.id as orden_id_val

    FROM piezas p

    JOIN ordenes o ON o.id = p.orden_id

    WHERE p.qr_code = ?

');

$stmt->execute([$qr]);

$pieza = $stmt->fetch();



if (!$pieza) jsonResponse(['error' => 'C&#243;digo QR no encontrado'], 404);

// Validar flujo
// Para piezas sin templado: canteado→terminado y taladro→terminado son válidos
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
$estatusActual = $pieza['estatus'];
if (!$omision && isset($FLUJO_PREVIO[$estatus]) && !in_array($estatusActual, $FLUJO_PREVIO[$estatus])) {
    jsonResponse(['error' => 'La pieza est&#225; en "' . $estatusActual . '" y no puede pasar a "' . $estatus . '"'], 400);
}



$estatusAnterior = $pieza['estatus'];

$FLUJO_ORDEN = ['pendiente','en_corte','cortado','canteado','trazo','taladro','en_horno','terminado','entregado'];

// Obtener nombre de usuario

$nombreUsuario = 'Desconocido';


if ($userId) {

    $u = $db->prepare('SELECT nombre FROM usuarios WHERE id = ?');

    $u->execute([$userId]);

    $u = $u->fetch();

    if ($u) $nombreUsuario = $u['nombre'];

} elseif (!empty($_SESSION['user_name'])) {

    $nombreUsuario = $_SESSION['user_name'];

}



// Actualizar estatus

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