<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';
$user = requireSessionApi();
$db   = getDB();

$accion = $_GET['accion'] ?? $_POST['accion'] ?? (json_decode(file_get_contents('php://input'), true)['accion'] ?? '');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

$esAdmin = in_array($user['rol'], ['dir_admin', 'dueno', 'administracion', 'desarrollo']);

// Verifica que el usuario tiene acceso a la cotización (default-deny: no-admin debe ser asesor)
function verificarAccesoCotizacion($db, $cid, $user, $esAdmin) {
    $st = $db->prepare('SELECT id FROM cotizaciones WHERE id = ?');
    $st->execute([$cid]);
    if (!$st->fetch()) {
        jsonResponse(['error' => 'Cotización no encontrada'], 404);
    }
    if ($esAdmin) return;
    $st2 = $db->prepare('SELECT id FROM cotizaciones WHERE id = ? AND asesor_id = ?');
    $st2->execute([$cid, $user['id']]);
    if (!$st2->fetch()) {
        jsonResponse(['error' => 'Sin acceso a esta cotización'], 403);
    }
}

if ($accion === 'listar') {
    $cid = (int)($_GET['cotizacion_id'] ?? 0);
    if (!$cid) jsonResponse(['error' => 'ID requerido'], 400);
    verificarAccesoCotizacion($db, $cid, $user, $esAdmin);
    $rows = $db->prepare('SELECT * FROM orden_comentarios WHERE cotizacion_id = ? ORDER BY created_at ASC');
    $rows->execute([$cid]);
    jsonResponse(['comentarios' => $rows->fetchAll()]);
}

if ($accion === 'agregar') {
    $cid   = (int)($body['cotizacion_id'] ?? 0);
    $texto = trim($body['texto'] ?? '');
    if (!$cid || !$texto) jsonResponse(['error' => 'Datos incompletos'], 400);
    verificarAccesoCotizacion($db, $cid, $user, $esAdmin);
    $st = $db->prepare('INSERT INTO orden_comentarios (cotizacion_id, texto, usuario_nombre) VALUES (?, ?, ?)');
    $st->execute([$cid, $texto, $user['nombre']]);
    jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
}

if ($accion === 'cancelar') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    // Admin puede cancelar cualquier comentario; otros solo el propio
    if ($esAdmin) {
        $st = $db->prepare('UPDATE orden_comentarios SET cancelado=1, cancelado_por=?, cancelado_at=NOW() WHERE id=? AND cancelado=0');
        $st->execute([$user['nombre'], $id]);
    } else {
        $st = $db->prepare('UPDATE orden_comentarios SET cancelado=1, cancelado_por=?, cancelado_at=NOW() WHERE id=? AND cancelado=0 AND usuario_nombre=?');
        $st->execute([$user['nombre'], $id, $user['nombre']]);
    }
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Acción desconocida'], 400);
