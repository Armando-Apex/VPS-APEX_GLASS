<?php
// ============================================================
//  APEX GLASS - API: Catálogo de Servicios
//  GET             → lista todos (activos por defecto)
//  POST crear      → { nombre, precio_default }
//  PUT  editar     → { id, nombre, precio_default }
//  DELETE          → ?id=X  (desactiva)
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');

$user   = requireSessionApi();
$rol    = $user['rol'];
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

$es_admin = in_array($rol, ['dir_admin', 'desarrollo']);

// ── GET ───────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $todos = isset($_GET['todos']) && $_GET['todos'] === '1';
    $where = $todos ? '' : 'WHERE activo = 1';
    $stmt  = $db->query("SELECT id, nombre, precio_default, activo FROM servicios_catalogo $where ORDER BY nombre ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

// Escritura solo dir_admin
if (!$es_admin) {
    http_response_code(403);
    echo json_encode(['error' => 'Solo dir_admin puede gestionar el catálogo']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST crear ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $nombre  = trim($body['nombre']        ?? '');
    $precio  = (float)($body['precio_default'] ?? 0);

    if (!$nombre || $precio <= 0) {
        echo json_encode(['error' => 'Nombre y precio son requeridos']); exit;
    }

    $stmt = $db->prepare("INSERT INTO servicios_catalogo (nombre, precio_default) VALUES (?, ?)");
    $stmt->execute([$nombre, $precio]);
    echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId(), 'nombre' => $nombre, 'precio_default' => $precio]); exit;
}

// ── PUT editar ────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $id     = (int)($body['id']            ?? 0);
    $nombre = trim($body['nombre']         ?? '');
    $precio = (float)($body['precio_default'] ?? 0);

    if (!$id || !$nombre || $precio <= 0) {
        echo json_encode(['error' => 'Datos incompletos']); exit;
    }

    $db->prepare("UPDATE servicios_catalogo SET nombre = ?, precio_default = ? WHERE id = ?")
       ->execute([$nombre, $precio, $id]);
    echo json_encode(['ok' => true]); exit;
}

// ── DELETE desactivar ─────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'id requerido']); exit; }

    $db->prepare("UPDATE servicios_catalogo SET activo = 0 WHERE id = ?")
       ->execute([$id]);
    echo json_encode(['ok' => true]); exit;
}

echo json_encode(['error' => 'Método no permitido']); exit;
