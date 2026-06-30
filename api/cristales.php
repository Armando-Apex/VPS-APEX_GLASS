<?php
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user           = requireSessionApi();
$rol            = $user['rol'];
$usuario_id     = $user['id'];
$usuario_nombre = $user['nombre'];
$pdo            = getDB();
$method    = $_SERVER['REQUEST_METHOD'];

// ─── GET — listar cristales ───────────────────────────────────────────────────
if ($method === 'GET') {
    $solo_activos = ($_GET['activos'] ?? '1') === '1';
    $id           = isset($_GET['id']) ? (int)$_GET['id'] : null;

    // Detalle de un cristal + su historial
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM cristales WHERE id = ?");
        $stmt->execute([$id]);
        $cristal = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cristal) {
            jsonResponse(['error' => 'No encontrado']); exit;
        }
        $stmt2 = $pdo->prepare("
            SELECT * FROM cristales_historial
            WHERE cristal_id = ?
            ORDER BY fecha DESC
            LIMIT 50
        ");
        $stmt2->execute([$id]);
        $cristal['historial'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($cristal);
        exit;
    }

    // Lista completa
    $where = $solo_activos ? 'WHERE activo = 1' : '';
    $stmt  = $pdo->query("SELECT * FROM cristales $where ORDER BY nombre ASC");
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ─── Solo dir_admin puede escribir ───────────────────────────────────────────
if (!in_array($rol, ['dir_admin', 'dueno', 'desarrollo'])) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── POST — crear cristal ─────────────────────────────────────────────────────
if ($method === 'POST') {
    $nombre   = trim($body['nombre'] ?? '');
    $etiqueta = trim($body['nombre_etiqueta'] ?? '');
    $precio   = (float)($body['precio_m2'] ?? 0);

    if (!$nombre || !$etiqueta || $precio <= 0) {
        jsonResponse(['error' => 'Datos incompletos']); exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO cristales (nombre, nombre_etiqueta, precio_m2)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$nombre, $etiqueta, $precio]);
    $new_id = $pdo->lastInsertId();

    // Registrar en historial como precio inicial
    $pdo->prepare("
        INSERT INTO cristales_historial
            (cristal_id, precio_anterior, precio_nuevo, usuario_id, usuario_nombre, motivo)
        VALUES (?, 0, ?, ?, ?, 'Precio inicial')
    ")->execute([$new_id, $precio, $usuario_id, $usuario_nombre]);

    jsonResponse(['ok' => true, 'id' => $new_id]);
    exit;
}

// ─── PUT — editar cristal ─────────────────────────────────────────────────────
if ($method === 'PUT') {
    $id       = (int)($body['id'] ?? 0);
    $nombre   = trim($body['nombre'] ?? '');
    $etiqueta = trim($body['nombre_etiqueta'] ?? '');
    $precio   = (float)($body['precio_m2'] ?? 0);
    $activo   = isset($body['activo']) ? (int)$body['activo'] : null;
    $motivo   = trim($body['motivo'] ?? '');

    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    // Obtener precio actual para historial
    $stmt = $pdo->prepare("SELECT precio_m2 FROM cristales WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) { jsonResponse(['error' => 'No encontrado']); exit; }

    $precio_anterior = (float)$actual['precio_m2'];

    // Actualizar
    $fields = [];
    $params = [];

    if ($nombre)   { $fields[] = 'nombre = ?';          $params[] = $nombre; }
    if ($etiqueta) { $fields[] = 'nombre_etiqueta = ?';  $params[] = $etiqueta; }
    if ($precio > 0) { $fields[] = 'precio_m2 = ?';     $params[] = $precio; }
    if ($activo !== null) { $fields[] = 'activo = ?';    $params[] = $activo; }

    if (!$fields) { jsonResponse(['error' => 'Nada que actualizar']); exit; }

    $params[] = $id;
    $pdo->prepare("UPDATE cristales SET " . implode(', ', $fields) . " WHERE id = ?")
        ->execute($params);

    // Historial solo si cambió el precio
    if ($precio > 0 && $precio != $precio_anterior) {
        $pdo->prepare("
            INSERT INTO cristales_historial
                (cristal_id, precio_anterior, precio_nuevo, usuario_id, usuario_nombre, motivo)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$id, $precio_anterior, $precio, $usuario_id, $usuario_nombre, $motivo]);
    }

    jsonResponse(['ok' => true]);
    exit;
}

// ─── DELETE — desactivar cristal (soft delete) ───────────────────────────────
if ($method === 'DELETE') {
    $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $pdo->prepare("UPDATE cristales SET activo = 0 WHERE id = ?")->execute([$id]);
    jsonResponse(['ok' => true]);
    exit;
}

jsonResponse(['error' => 'Método no soportado']);