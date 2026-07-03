<?php
// ============================================================
//  APEX GLASS - API: Resumen de orden para registro masivo en CNC
//  Archivo: api/orden_masivo.php
//  Método: GET  ?orden_id=123
// ============================================================

require_once 'config.php';
require_once 'permisos.php';
requireSessionApi();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

$ordenId = (int)($_GET['orden_id'] ?? 0);
if (!$ordenId) jsonResponse(['error' => 'orden_id requerido'], 400);

$db = getDB();

$stmt = $db->prepare('SELECT id, folio, cliente_nombre FROM ordenes WHERE id = ?');
$stmt->execute([$ordenId]);
$orden = $stmt->fetch();
if (!$orden) jsonResponse(['error' => 'Orden no encontrada'], 404);

$stmt = $db->prepare("SELECT COUNT(*) FROM piezas WHERE orden_id = ? AND estatus = 'pendiente'");
$stmt->execute([$ordenId]);
$pendientes = (int)$stmt->fetchColumn();

jsonResponse([
    'orden_id'   => $orden['id'],
    'folio'      => $orden['folio'],
    'cliente'    => $orden['cliente_nombre'],
    'pendientes' => $pendientes,
]);
