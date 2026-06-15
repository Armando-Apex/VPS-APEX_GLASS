<?php
// ============================================================
//  APEX GLASS - API: Notificaciones
//  Archivo: api/notificaciones.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');

// requireSessionApi en lugar de requirePermiso('ver_dashboard')
// para que el rol 'corte' también pueda acceder
$user    = requireSessionApi();
$rol     = $user['rol'];
$user_id = $user['id'];
$db      = getDB();
$accion  = $_GET['accion'] ?? 'listar';

// ── GET listar notificaciones ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'listar') {

    if ($rol === 'dir_admin' || $rol === 'administracion') {
        $dest = $rol === 'dir_admin' ? 'dir_admin' : 'administracion';
        $stmt = $db->prepare("
            SELECT id, tipo, titulo, mensaje, folio, orden_id, leida, created_at
            FROM notificaciones
            WHERE rol_destino = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$dest]);
        $notifs    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $no_leidas = count(array_filter($notifs, fn($n) => !$n['leida']));

    } elseif ($rol === 'comercial') {
        // comercial: solo las suyas, usa campo leida original
        $stmt = $db->prepare("
            SELECT id, tipo, titulo, mensaje, folio, orden_id, leida, created_at
            FROM notificaciones
            WHERE rol_destino = 'comercial'
              AND usuario_id_dest = ?
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id]);
        $notifs    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $no_leidas = count(array_filter($notifs, fn($n) => !$n['leida']));

    } elseif ($rol === 'corte' || $rol === 'jefe_piso') {
        // corte / jefe_piso: leída se calcula POR USUARIO
        // via tabla notificaciones_leidas_usuario
        $stmt = $db->prepare("
            SELECT n.id, n.tipo, n.titulo, n.mensaje, n.folio, n.orden_id, n.created_at,
                   CASE WHEN nlu.id IS NOT NULL THEN 1 ELSE 0 END AS leida
            FROM notificaciones n
            LEFT JOIN notificaciones_leidas_usuario nlu
                   ON nlu.notificacion_id = n.id
                  AND nlu.usuario_id = ?
            WHERE n.rol_destino = ?
            ORDER BY n.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id, $rol]);
        $notifs    = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $no_leidas = count(array_filter($notifs, fn($n) => !$n['leida']));

    } else {
        echo json_encode(['ok' => true, 'notificaciones' => [], 'no_leidas' => 0]);
        exit;
    }

    echo json_encode(['ok' => true, 'notificaciones' => $notifs, 'no_leidas' => $no_leidas]);
    exit;
}

// ── POST marcar leída ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'leer') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = intval($body['id'] ?? 0);

    if ($id) {
        if ($rol === 'corte' || $rol === 'jefe_piso') {
            // Insertar en tabla por usuario — IGNORE para evitar duplicados
            $db->prepare("
                INSERT IGNORE INTO notificaciones_leidas_usuario (notificacion_id, usuario_id)
                VALUES (?, ?)
            ")->execute([$id, $user_id]);
        } else {
            // dir_admin y comercial: UPDATE directo al campo leida
            $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ?")->execute([$id]);
        }
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── POST marcar todas leídas ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'leer_todas') {
    if ($rol === 'dir_admin' || $rol === 'administracion') {
        $dest = $rol === 'dir_admin' ? 'dir_admin' : 'administracion';
        $db->prepare("
            UPDATE notificaciones SET leida = 1
            WHERE rol_destino = ? AND leida = 0
        ")->execute([$dest]);

    } elseif ($rol === 'comercial') {
        $db->prepare("
            UPDATE notificaciones SET leida = 1
            WHERE rol_destino = 'comercial' AND usuario_id_dest = ? AND leida = 0
        ")->execute([$user_id]);

    } elseif ($rol === 'corte' || $rol === 'jefe_piso') {
        // Insertar un registro por cada notificación no leída aún por este usuario
        $db->prepare("
            INSERT IGNORE INTO notificaciones_leidas_usuario (notificacion_id, usuario_id)
            SELECT n.id, ?
            FROM notificaciones n
            LEFT JOIN notificaciones_leidas_usuario nlu
                   ON nlu.notificacion_id = n.id AND nlu.usuario_id = ?
            WHERE n.rol_destino = ?
              AND nlu.id IS NULL
        ")->execute([$user_id, $user_id, $rol]);
    }

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);