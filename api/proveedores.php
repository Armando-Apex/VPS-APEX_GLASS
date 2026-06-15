<?php
// ============================================================
//  APEX GLASS - API: Proveedores
//  GET    ?accion=lista|detalle&id=X
//  POST   accion=crear
//  PUT    accion=actualizar&id=X
//  DELETE accion=eliminar&id=X
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
$user = requirePermiso('ver_inventario');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? $_POST['accion'] ?? 'lista';
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($accion === 'detalle' && $id) {
        $s = $db->prepare("SELECT * FROM proveedores WHERE id = ?");
        $s->execute([$id]);
        $p = $s->fetch();
        if (!$p) jsonResponse(['error' => 'No encontrado'], 404);
        jsonResponse($p);
    }
    // lista
    $activo = isset($_GET['todos']) ? '' : "WHERE activo = 1";
    $s = $db->query("SELECT id, nombre, contacto, telefono, email, notas, activo
                     FROM proveedores $activo ORDER BY nombre ASC");
    jsonResponse(['proveedores' => $s->fetchAll()]);
}

// ── POST / PUT ───────────────────────────────────────────────
if ($method === 'POST' || $method === 'PUT') {
    requirePermiso('gestionar_inventario');
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion  = $body['accion'] ?? $accion;
    $id      = (int)($body['id'] ?? $id);

    $nombre   = trim($body['nombre']   ?? '');
    $contacto = trim($body['contacto'] ?? '');
    $telefono = trim($body['telefono'] ?? '');
    $email    = trim($body['email']    ?? '');
    $notas    = trim($body['notas']    ?? '');
    $activo   = isset($body['activo']) ? (int)$body['activo'] : 1;

    if (!$nombre) jsonResponse(['error' => 'Nombre requerido'], 422);

    if ($accion === 'crear') {
        $s = $db->prepare("INSERT INTO proveedores
            (nombre, contacto, telefono, email, notas, activo)
            VALUES (?,?,?,?,?,?)");
        $s->execute([$nombre, $contacto, $telefono, $email, $notas, $activo]);
        jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
    }

    if ($accion === 'actualizar' && $id) {
        $s = $db->prepare("UPDATE proveedores SET
            nombre=?, contacto=?, telefono=?, email=?, notas=?, activo=?
            WHERE id=?");
        $s->execute([$nombre, $contacto, $telefono, $email, $notas, $activo, $id]);
        jsonResponse(['ok' => true]);
    }
}

// ── DELETE ───────────────────────────────────────────────────
if ($method === 'DELETE') {
    requirePermiso('gestionar_inventario');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($body['id'] ?? $id);
    if (!$id) jsonResponse(['error' => 'ID requerido'], 422);
    // Soft delete
    $s = $db->prepare("UPDATE proveedores SET activo=0 WHERE id=?");
    $s->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Método no soportado'], 405);