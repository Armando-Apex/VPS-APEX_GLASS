<?php
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user   = requireSessionApi();
$rol    = $user['rol'];
$nombre = $user['nombre'];
$pdo    = getDB();

$accion = $_GET['accion'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── GET lista (solo desarrollo / dir_admin) ───────────────────────────────────
if ($method === 'GET' && $accion === 'lista') {
    if (!in_array($rol, ['desarrollo', 'dir_admin'])) {
        jsonResponse(['ok'=>false,'error'=>'Sin permiso'], 403);
    }
    $estado = $_GET['estado'] ?? '';
    $where  = $estado === 'completado' ? "WHERE estado='completado'" : ($estado === 'pendiente' ? "WHERE estado='pendiente'" : '');
    $rows = $pdo->query("
        SELECT id, tipo, descripcion, elemento, estado,
               creado_por, creado_por_rol, created_at,
               completado_por, completado_at
        FROM reportes $where
        ORDER BY estado ASC, created_at DESC
        LIMIT 300
    ")->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['ok'=>true, 'reportes'=>$rows]);
    exit;
}

// ── POST crear ────────────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'crear') {
    $d    = json_decode(file_get_contents('php://input'), true) ?? [];
    $tipo  = in_array($d['tipo'] ?? '', ['bug','mejora']) ? $d['tipo'] : null;
    $desc  = trim($d['descripcion'] ?? '');
    $elem  = isset($d['elemento']) && is_array($d['elemento']) ? json_encode($d['elemento'], JSON_UNESCAPED_UNICODE) : null;

    if (!$tipo) { jsonResponse(['ok'=>false,'error'=>'Tipo requerido']); exit; }
    if (!$desc) { jsonResponse(['ok'=>false,'error'=>'Descripción requerida']); exit; }

    $stmt = $pdo->prepare("
        INSERT INTO reportes (tipo, descripcion, elemento, creado_por, creado_por_rol)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$tipo, $desc, $elem, $nombre, $rol]);
    $newId = $pdo->lastInsertId();

    // Notificar a todos los usuarios con rol=desarrollo
    $devs = $pdo->query("SELECT id, nombre FROM usuarios WHERE rol='desarrollo' AND activo=1")->fetchAll(PDO::FETCH_ASSOC);
    $tipoLabel = $tipo === 'bug' ? 'Bug' : 'Mejora';
    $stmtNotif = $pdo->prepare("
        INSERT INTO notificaciones (tipo, titulo, mensaje, rol_destino, usuario_id_dest, usuario_id_orig, usuario_nombre)
        VALUES ('reporte', ?, ?, 'desarrollo', ?, ?, ?)
    ");
    foreach ($devs as $dev) {
        $stmtNotif->execute([
            $tipoLabel . ' reportado por ' . $nombre,
            mb_substr($desc, 0, 100),
            $dev['id'],
            $user['id'],
            $nombre,
        ]);
    }

    jsonResponse(['ok'=>true, 'id'=>$newId]);
    exit;
}

// ── POST completar ────────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'completar') {
    if (!in_array($rol, ['desarrollo', 'dir_admin'])) {
        jsonResponse(['ok'=>false,'error'=>'Sin permiso'], 403);
    }
    $d  = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($d['id'] ?? 0);
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }

    $stmt = $pdo->prepare("
        UPDATE reportes SET estado='completado', completado_por=?, completado_at=NOW()
        WHERE id=? AND estado='pendiente'
    ");
    $stmt->execute([$nombre, $id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['ok'=>false,'error'=>'No encontrado o ya completado']); exit;
    }
    jsonResponse(['ok'=>true]);
    exit;
}

jsonResponse(['ok'=>false,'error'=>'Acción no válida'], 400);
