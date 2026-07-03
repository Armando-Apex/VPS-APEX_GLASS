<?php
// ============================================================
//  APEX GLASS - API: Registro masivo en CNC (QR maestro de orden)
//  Archivo: api/actualizar_estatus_masivo.php
//  Método: POST { orden_id, usuario_id }
//  Efecto: todas las piezas 'pendiente' de la orden pasan a 'en_corte'
// ============================================================

require_once 'config.php';
require_once 'permisos.php';
requireSessionApi();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error' => 'JSON inválido'], 400);

$ordenId = (int)($input['orden_id']   ?? 0);
$userId  = (int)($input['usuario_id'] ?? 0);

if (!$ordenId) jsonResponse(['error' => 'orden_id requerido'], 400);

$db = getDB();

$ord = $db->prepare('SELECT id, folio FROM ordenes WHERE id = ?');
$ord->execute([$ordenId]);
$ord = $ord->fetch();
if (!$ord) jsonResponse(['error' => 'Orden no encontrada'], 404);

$nombreUsuario = 'Desconocido';
if ($userId) {
    $u = $db->prepare('SELECT nombre FROM usuarios WHERE id = ?');
    $u->execute([$userId]);
    $u = $u->fetch();
    if ($u) $nombreUsuario = $u['nombre'];
} elseif (!empty($_SESSION['user_name'])) {
    $nombreUsuario = $_SESSION['user_name'];
}

$piezas = $db->prepare("SELECT id FROM piezas WHERE orden_id = ? AND estatus = 'pendiente'");
$piezas->execute([$ordenId]);
$piezaIds = $piezas->fetchAll(PDO::FETCH_COLUMN);

if (!$piezaIds) {
    jsonResponse(['ok' => true, 'folio' => $ord['folio'], 'actualizadas' => 0]);
}

$db->beginTransaction();
try {
    $upd  = $db->prepare("UPDATE piezas SET estatus = 'en_corte', updated_at = NOW() WHERE id = ?");
    $hist = $db->prepare('
        INSERT INTO historial_estatus
            (pieza_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, notas, omision)
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ');
    foreach ($piezaIds as $piezaId) {
        $upd->execute([$piezaId]);
        $hist->execute([$piezaId, 'pendiente', 'en_corte', $userId ?: null, $nombreUsuario, 'Registro masivo — QR de orden']);
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['error' => 'Error al actualizar piezas'], 500);
}

jsonResponse([
    'ok'           => true,
    'folio'        => $ord['folio'],
    'actualizadas' => count($piezaIds),
]);
