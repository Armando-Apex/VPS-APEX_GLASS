<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';

header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

// ── GET activos: sin sesión (SmartTV) ─────────────────────────────────────────
if ($metodo === 'GET' && $accion === 'activos') {
    $pdo  = getDB();
    $stmt = $pdo->query("SELECT id, archivo, intervalo_min, duracion_seg, mostrar_ahora,
                         UNIX_TIMESTAMP(last_shown_at) as last_shown_ts
                         FROM comunicados WHERE activo = 1 ORDER BY id ASC");
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── POST reset_mostrar: sin sesión (SmartTV llama después de mostrar) ─────────
if ($metodo === 'POST' && $accion === 'reset_mostrar') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = intval($body['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'ID inválido']); exit; }
    $pdo = getDB();
    // Resetea mostrar_ahora y guarda la hora real en que se mostró
    $pdo->prepare("UPDATE comunicados SET mostrar_ahora = 0, last_shown_at = NOW() WHERE id = ?")
        ->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── GET imágenes disponibles ──────────────────────────────────────────────────
if ($metodo === 'GET' && $accion === 'imagenes') {
    requirePermiso('ver_dashboard');
    $dir   = __DIR__ . '/../imagenes_comunicados/';
    $files = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) as $f) {
            if (in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ['jpg','jpeg','png'])) {
                $files[] = $f;
            }
        }
        sort($files);
    }
    echo json_encode(['ok' => true, 'imagenes' => $files]);
    exit;
}

// ── Resto requiere dir_admin ──────────────────────────────────────────────────
$user = requirePermiso('ver_dashboard');
if (!in_array($user['rol'], ['dir_admin', 'desarrollo'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']); exit;
}

$pdo = getDB();

// ── GET listar todos ──────────────────────────────────────────────────────────
if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $pdo->query("SELECT *,
                         UNIX_TIMESTAMP(last_shown_at) as last_shown_ts
                         FROM comunicados ORDER BY created_at DESC");
    echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── POST mostrar_ahora ────────────────────────────────────────────────────────
if ($metodo === 'POST' && $accion === 'mostrar_ahora') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = intval($body['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'ID inválido']); exit; }
    $pdo->prepare("UPDATE comunicados SET mostrar_ahora = 1 WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── POST crear ────────────────────────────────────────────────────────────────
if ($metodo === 'POST' && $accion === 'crear') {
    $body      = json_decode(file_get_contents('php://input'), true) ?? [];
    $nombre    = trim($body['nombre']        ?? '');
    $archivo   = trim($body['archivo']       ?? '');
    $intervalo = intval($body['intervalo_min'] ?? 0);
    $duracion  = intval($body['duracion_seg']  ?? 0);

    if (!$nombre)  { echo json_encode(['error' => 'Nombre requerido']); exit; }
    if (!$archivo) { echo json_encode(['error' => 'Selecciona una imagen']); exit; }
    if ($intervalo < 1 || $intervalo > 1440) { echo json_encode(['error' => 'Intervalo inválido (1–1440 min)']); exit; }
    if ($duracion  < 5 || $duracion  > 3600) { echo json_encode(['error' => 'Duración inválida (5–3600 seg)']); exit; }

    $dir = __DIR__ . '/../imagenes_comunicados/';
    if (!file_exists($dir . basename($archivo))) {
        echo json_encode(['error' => 'El archivo no existe en la carpeta de comunicados']); exit;
    }
    $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png'])) {
        echo json_encode(['error' => 'Solo se permiten JPG o PNG']); exit;
    }

    $pdo->prepare("INSERT INTO comunicados (nombre, archivo, intervalo_min, duracion_seg, creado_por) VALUES (?,?,?,?,?)")
        ->execute([$nombre, basename($archivo), $intervalo, $duracion, $user['nombre']]);
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

// ── POST toggle ───────────────────────────────────────────────────────────────
if ($metodo === 'POST' && $accion === 'toggle') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = intval($body['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'ID inválido']); exit; }
    $pdo->prepare("UPDATE comunicados SET activo = IF(activo=1,0,1) WHERE id = ?")->execute([$id]);
    $row = $pdo->prepare("SELECT activo FROM comunicados WHERE id = ?");
    $row->execute([$id]);
    echo json_encode(['ok' => true, 'activo' => (int)$row->fetchColumn()]);
    exit;
}

// ── POST eliminar ─────────────────────────────────────────────────────────────
if ($metodo === 'POST' && $accion === 'eliminar') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = intval($body['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'ID inválido']); exit; }
    $pdo->prepare("DELETE FROM comunicados WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);