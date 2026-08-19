<?php
// Mensajería interna 1-a-1: cada persona (comercial/piso/etc.) tiene UN solo
// hilo con "desarrollo" (agrupado por otro_usuario_id, sin importar cuál
// usuario dev/dir_admin haya escrito). Nunca entre dos usuarios que no sean dev.
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user = requireSessionApi();
$rol  = $user['rol'];
$pdo  = getDB();

$esDev = in_array($rol, ['desarrollo', 'dir_admin']);

$accion = $_GET['accion'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── GET conversaciones (solo desarrollo/dir_admin): 1 fila por persona ───────
if ($method === 'GET' && $accion === 'conversaciones') {
    if (!$esDev) { jsonResponse(['ok'=>false,'error'=>'Sin permiso'], 403); exit; }

    $rows = $pdo->query("
        SELECT u.id AS usuario_id, u.nombre, u.rol,
               (SELECT mensaje FROM mensajes_internos m2
                 WHERE m2.otro_usuario_id = u.id ORDER BY m2.id DESC LIMIT 1) AS ultimo_mensaje,
               (SELECT created_at FROM mensajes_internos m3
                 WHERE m3.otro_usuario_id = u.id ORDER BY m3.id DESC LIMIT 1) AS ultimo_at,
               (SELECT COUNT(*) FROM mensajes_internos m4
                 WHERE m4.otro_usuario_id = u.id AND m4.de_otro = 1 AND m4.leido_at IS NULL) AS no_leidos
        FROM usuarios u
        WHERE EXISTS (SELECT 1 FROM mensajes_internos m WHERE m.otro_usuario_id = u.id)
        ORDER BY ultimo_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(['ok'=>true, 'conversaciones'=>$rows]);
    exit;
}

// ── GET hilo (dev: ?con=usuario_id · no-dev: su propio hilo) ─────────────────
if ($method === 'GET' && $accion === 'hilo') {
    $otroId = $esDev ? (int)($_GET['con'] ?? 0) : (int)$user['id'];
    if (!$otroId) { jsonResponse(['ok'=>false,'error'=>'Falta usuario']); exit; }

    $rows = $pdo->prepare("
        SELECT id, de_otro, autor_nombre, mensaje, created_at, leido_at
        FROM mensajes_internos WHERE otro_usuario_id = ?
        ORDER BY id ASC LIMIT 300
    ");
    $rows->execute([$otroId]);

    // Marcar como leído lo que le tocaba leer al que está consultando
    $deOtroAMarcar = $esDev ? 1 : 0;
    $upd = $pdo->prepare("
        UPDATE mensajes_internos SET leido_at = NOW()
        WHERE otro_usuario_id = ? AND de_otro = ? AND leido_at IS NULL
    ");
    $upd->execute([$otroId, $deOtroAMarcar]);

    jsonResponse(['ok'=>true, 'mensajes'=>$rows->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── GET sin_leer (contador para badge del ícono) ──────────────────────────────
if ($method === 'GET' && $accion === 'sin_leer') {
    if ($esDev) {
        $total = (int)$pdo->query("
            SELECT COUNT(*) FROM mensajes_internos WHERE de_otro = 1 AND leido_at IS NULL
        ")->fetchColumn();
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM mensajes_internos
            WHERE otro_usuario_id = ? AND de_otro = 0 AND leido_at IS NULL
        ");
        $stmt->execute([$user['id']]);
        $total = (int)$stmt->fetchColumn();
    }
    jsonResponse(['ok'=>true, 'total'=>$total]);
    exit;
}

// ── POST enviar ────────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'enviar') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    $mensaje = trim($d['mensaje'] ?? '');
    if (!$mensaje) { jsonResponse(['ok'=>false,'error'=>'Mensaje requerido']); exit; }
    if (mb_strlen($mensaje) > 2000) { jsonResponse(['ok'=>false,'error'=>'Mensaje muy largo']); exit; }

    if ($esDev) {
        $otroId = (int)($d['para'] ?? 0);
        if (!$otroId) { jsonResponse(['ok'=>false,'error'=>'Falta destinatario']); exit; }
        // El destinatario nunca puede ser otro usuario dev/dir_admin (regla: solo persona<->yo)
        $chk = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ? AND activo = 1");
        $chk->execute([$otroId]);
        $rolOtro = $chk->fetchColumn();
        if (!$rolOtro || in_array($rolOtro, ['desarrollo', 'dir_admin'])) {
            jsonResponse(['ok'=>false,'error'=>'Destinatario inválido']); exit;
        }
        $deOtro = 0;
    } else {
        $otroId = (int)$user['id'];
        $deOtro = 1;
    }

    $stmt = $pdo->prepare("
        INSERT INTO mensajes_internos (otro_usuario_id, de_otro, autor_usuario_id, autor_nombre, mensaje)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$otroId, $deOtro, $user['id'], $user['nombre'], $mensaje]);

    // Notificación cruzada vía el sistema de notificaciones ya existente
    if ($deOtro === 0) {
        $stmtN = $pdo->prepare("
            INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_id_dest, usuario_id_orig, usuario_nombre)
            VALUES ('mensaje_interno', 'Nuevo mensaje de Desarrollo', ?, ?, ?, ?)
        ");
        $stmtN->execute([mb_substr($mensaje, 0, 100), $otroId, $user['id'], $user['nombre']]);
    } else {
        $devs = $pdo->query("SELECT id FROM usuarios WHERE rol IN ('desarrollo','dir_admin') AND activo=1")->fetchAll(PDO::FETCH_ASSOC);
        $stmtN = $pdo->prepare("
            INSERT INTO notificaciones (tipo, titulo, mensaje, usuario_id_dest, usuario_id_orig, usuario_nombre)
            VALUES ('mensaje_interno', ?, ?, ?, ?, ?)
        ");
        foreach ($devs as $dev) {
            $stmtN->execute(['Nuevo mensaje de ' . $user['nombre'], mb_substr($mensaje, 0, 100), $dev['id'], $user['id'], $user['nombre']]);
        }
    }

    jsonResponse(['ok'=>true]);
    exit;
}

// ── GET resolver_usuario (dev: busca el usuario_id de un reporte por nombre) ─
if ($method === 'GET' && $accion === 'resolver_usuario') {
    if (!$esDev) { jsonResponse(['ok'=>false,'error'=>'Sin permiso'], 403); exit; }
    $nombre = trim($_GET['nombre'] ?? '');
    if (!$nombre) { jsonResponse(['ok'=>false,'error'=>'Falta nombre']); exit; }
    $stmt = $pdo->prepare("SELECT id, nombre, rol FROM usuarios WHERE nombre = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$nombre]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u || in_array($u['rol'], ['desarrollo', 'dir_admin'])) {
        jsonResponse(['ok'=>false,'error'=>'Usuario no encontrado']); exit;
    }
    jsonResponse(['ok'=>true, 'usuario'=>$u]);
    exit;
}

jsonResponse(['ok'=>false,'error'=>'Acción no válida'], 400);
