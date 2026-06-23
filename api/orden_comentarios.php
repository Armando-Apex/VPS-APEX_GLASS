<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';
$user = requireSessionApi();
$db   = getDB();

$accion = $_GET['accion'] ?? $_POST['accion'] ?? (json_decode(file_get_contents('php://input'), true)['accion'] ?? '');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

if ($accion === 'listar') {
    $cid = (int)($_GET['cotizacion_id'] ?? 0);
    if (!$cid) jsonResponse(['error' => 'ID requerido'], 400);
    $rows = $db->prepare('SELECT * FROM orden_comentarios WHERE cotizacion_id = ? ORDER BY created_at ASC');
    $rows->execute([$cid]);
    jsonResponse(['comentarios' => $rows->fetchAll()]);
}

if ($accion === 'agregar') {
    $cid   = (int)($body['cotizacion_id'] ?? 0);
    $texto = trim($body['texto'] ?? '');
    if (!$cid || !$texto) jsonResponse(['error' => 'Datos incompletos'], 400);
    $st = $db->prepare('INSERT INTO orden_comentarios (cotizacion_id, texto, usuario_nombre) VALUES (?, ?, ?)');
    $st->execute([$cid, $texto, $user['nombre']]);
    jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
}

if ($accion === 'cancelar') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID requerido'], 400);
    $st = $db->prepare('UPDATE orden_comentarios SET cancelado=1, cancelado_por=?, cancelado_at=NOW() WHERE id=? AND cancelado=0');
    $st->execute([$user['nombre'], $id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Acción desconocida'], 400);
