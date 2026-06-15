<?php
// ============================================================
//  APEX GLASS - API: Consultar pieza por QR
//  Archivo: api/pieza.php
//  Método: GET  ?qr=R-001-P1-1de12
// ============================================================

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$qr = trim($_GET['qr'] ?? '');
if (!$qr) jsonResponse(['error' => 'qr requerido'], 400);

$db = getDB();

$stmt = $db->prepare('
    SELECT p.*, 
           o.folio, o.orden_trabajo, o.cliente_nombre, 
           o.asesor, o.fecha_entrega, o.tipo, o.tipo_entrega
    FROM piezas p
    JOIN ordenes o ON o.id = p.orden_id
    WHERE p.qr_code = ?
');
$stmt->execute([$qr]);
$pieza = $stmt->fetch();

if (!$pieza) jsonResponse(['error' => 'Código no encontrado'], 404);

// Historial de esta pieza
$hist = $db->prepare('SELECT * FROM historial_estatus WHERE pieza_id = ? ORDER BY created_at DESC LIMIT 10');
$hist->execute([$pieza['id']]);
$historial = $hist->fetchAll();

jsonResponse([
    'pieza'    => $pieza,
    'historial'=> $historial,
]);
