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

    if ($rol === 'dir_admin' || $rol === 'administracion' || $rol === 'desarrollo') {
        $dest = ($rol === 'dir_admin' || $rol === 'desarrollo') ? 'dir_admin' : 'administracion';
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
        jsonResponse(['ok' => true, 'notificaciones' => [], 'no_leidas' => 0]);
    }

    jsonResponse(['ok' => true, 'notificaciones' => $notifs, 'no_leidas' => $no_leidas]);
}

// ── POST marcar leída ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'leer') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = intval($body['id'] ?? 0);

    if ($id) {
        if ($rol === 'corte' || $rol === 'jefe_piso') {
            // Por usuario — solo si la notificación va dirigida a su rol
            // (antes se podía "leer" el id de una alerta de dirección, M-18)
            $db->prepare("
                INSERT IGNORE INTO notificaciones_leidas_usuario (notificacion_id, usuario_id)
                SELECT n.id, ? FROM notificaciones n WHERE n.id = ? AND n.rol_destino = ?
            ")->execute([$user_id, $id, $rol]);
        } elseif ($rol === 'comercial') {
            // Solo las propias (mismo criterio que leer_todas)
            $db->prepare("
                UPDATE notificaciones SET leida = 1
                WHERE id = ? AND rol_destino = 'comercial' AND usuario_id_dest = ?
            ")->execute([$id, $user_id]);
        } elseif (in_array($rol, ['dir_admin', 'administracion', 'desarrollo'], true)) {
            // Solo las de su propia bandeja (mismo criterio que leer_todas)
            $dest = ($rol === 'dir_admin' || $rol === 'desarrollo') ? 'dir_admin' : 'administracion';
            $db->prepare("UPDATE notificaciones SET leida = 1 WHERE id = ? AND rol_destino = ?")
               ->execute([$id, $dest]);
        }
        // Cualquier otro rol (chofer, operador, director...): no marca nada —
        // en listar tampoco reciben notificaciones.
    }
    jsonResponse(['ok' => true]);
}

// ── POST marcar todas leídas ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'leer_todas') {
    if ($rol === 'dir_admin' || $rol === 'administracion' || $rol === 'desarrollo') {
        $dest = ($rol === 'dir_admin' || $rol === 'desarrollo') ? 'dir_admin' : 'administracion';
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

    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Acción no reconocida'], 400);