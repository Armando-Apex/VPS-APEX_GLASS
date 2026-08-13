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
    $stmt  = $db->query("SELECT id, nombre, precio_default, unidad, activo FROM servicios_catalogo $where ORDER BY nombre ASC");
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// Escritura solo dir_admin
if (!$es_admin) {
    jsonResponse(['error' => 'Solo dir_admin puede gestionar el catálogo'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── POST crear ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $nombre  = trim($body['nombre']        ?? '');
    $precio  = (float)($body['precio_default'] ?? 0);
    $unidad  = ($body['unidad'] ?? 'pieza') === 'ml' ? 'ml' : 'pieza';

    if (!$nombre || $precio <= 0) {
        jsonResponse(['error' => 'Nombre y precio son requeridos']);
    }

    $stmt = $db->prepare("INSERT INTO servicios_catalogo (nombre, precio_default, unidad) VALUES (?, ?, ?)");
    $stmt->execute([$nombre, $precio, $unidad]);
    jsonResponse(['ok' => true, 'id' => (int)$db->lastInsertId(), 'nombre' => $nombre, 'precio_default' => $precio, 'unidad' => $unidad]);
}

// ── PUT editar ────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $id     = (int)($body['id']            ?? 0);
    $nombre = trim($body['nombre']         ?? '');
    $precio = (float)($body['precio_default'] ?? 0);
    $unidad = ($body['unidad'] ?? 'pieza') === 'ml' ? 'ml' : 'pieza';

    if (!$id || !$nombre || $precio <= 0) {
        jsonResponse(['error' => 'Datos incompletos']);
    }

    $db->prepare("UPDATE servicios_catalogo SET nombre = ?, precio_default = ?, unidad = ? WHERE id = ?")
       ->execute([$nombre, $precio, $unidad, $id]);
    jsonResponse(['ok' => true]);
}

// ── DELETE desactivar ─────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'id requerido']); }

    $db->prepare("UPDATE servicios_catalogo SET activo = 0 WHERE id = ?")
       ->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Método no permitido']);
