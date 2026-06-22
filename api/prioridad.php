<?php
// ============================================================
//  APEX GLASS - API: Toggle Prioridad de Orden
//  Archivo: /produccion/api/prioridad.php
//  Método: POST { folio }
//  Acceso: dir_admin, dueno
// ============================================================

require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

requireSessionApi();

$rol = $_SESSION['user_rol'] ?? '';
if (!in_array($rol, ['dir_admin','dueno'])) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
    jsonResponse(['error' => 'Método no permitido'], 405);

$body  = json_decode(file_get_contents('php://input'), true);
$folio = trim($body['folio'] ?? '');

if (!$folio) jsonResponse(['error' => 'Folio requerido'], 400);

$db = getDB();

// Obtener prioridad actual
$stmt = $db->prepare("SELECT id, prioridad FROM ordenes WHERE folio = ?");
$stmt->execute([$folio]);
$orden = $stmt->fetch();

if (!$orden) jsonResponse(['error' => 'Orden no encontrada'], 404);

// Toggle
$nueva = $orden['prioridad'] ? 0 : 1;
$db->prepare("UPDATE ordenes SET prioridad = ? WHERE folio = ?")
   ->execute([$nueva, $folio]);

jsonResponse([
    'ok'       => true,
    'folio'    => $folio,
    'prioridad'=> $nueva,
    'mensaje'  => $nueva ? '⚡ Marcada como prioritaria' : 'Prioridad removida',
]);