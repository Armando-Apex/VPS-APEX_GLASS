<?php
// ============================================================
//  APEX GLASS - API: Maquila
//  Archivo: api/maquila.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
require_once 'cotizacion_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$user           = requireSessionApi();
$rol            = $user['rol'];
$usuario_id     = $user['id'];
$usuario_nombre = $user['nombre'];
$db             = getDB();
$method         = $_SERVER['REQUEST_METHOD'];

if (!tienePermiso($rol, 'ver_maquila')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$recurso = $_GET['recurso'] ?? null;
$body    = $method !== 'GET' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];
if ($method !== 'GET') {
    $recurso = $recurso ?? ($body['recurso'] ?? null);
}

// ─── Recurso: tipos_vidrio ────────────────────────────────────────────────────
if ($recurso === 'tipos_vidrio') {
    if ($method === 'GET') {
        $solo_activos = ($_GET['activos'] ?? '1') === '1';
        $where = $solo_activos ? 'WHERE activo = 1' : '';
        $stmt  = $db->query("SELECT * FROM maquila_tipos_vidrio $where ORDER BY nombre ASC");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if (!tienePermiso($rol, 'gestionar_maquila_precios')) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    if ($method === 'POST') {
        $nombre = trim($body['nombre'] ?? '');
        if (!$nombre) { jsonResponse(['error' => 'Nombre requerido']); exit; }
        $db->prepare("INSERT INTO maquila_tipos_vidrio (nombre) VALUES (?)")->execute([$nombre]);
        jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
        exit;
    }

    if ($method === 'PUT') {
        $id     = (int)($body['id'] ?? 0);
        $nombre = trim($body['nombre'] ?? '');
        $activo = isset($body['activo']) ? (int)$body['activo'] : null;
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
        if ($nombre !== '') {
            $db->prepare("UPDATE maquila_tipos_vidrio SET nombre = ? WHERE id = ?")->execute([$nombre, $id]);
        }
        if ($activo !== null) {
            $db->prepare("UPDATE maquila_tipos_vidrio SET activo = ? WHERE id = ?")->execute([$activo, $id]);
        }
        jsonResponse(['ok' => true]);
        exit;
    }

    jsonResponse(['error' => 'Método no permitido'], 405);
    exit;
}

// ─── Recurso: precios (corte/canteado/taladro/horno por espesor) ─────────────
if ($recurso === 'precios') {
    if ($method === 'GET') {
        $stmt = $db->query("SELECT * FROM maquila_precios WHERE activo = 1 ORDER BY servicio, espesor_mm");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if (!tienePermiso($rol, 'gestionar_maquila_precios')) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    if ($method === 'POST' || $method === 'PUT') {
        $servicio   = $body['servicio']   ?? '';
        $espesor_mm = (float)($body['espesor_mm'] ?? 0);
        $precio     = (float)($body['precio']     ?? 0);

        if (!in_array($servicio, ['corte','canteado','taladro','horno'])) {
            jsonResponse(['error' => 'Servicio inválido']); exit;
        }
        if ($espesor_mm <= 0 || $precio <= 0) {
            jsonResponse(['error' => 'Espesor y precio deben ser mayores a 0']); exit;
        }

        // Upsert por (servicio, espesor_mm) — la UNIQUE KEY del Task 1 lo garantiza
        $db->prepare("
            INSERT INTO maquila_precios (servicio, espesor_mm, precio, activo)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE precio = VALUES(precio), activo = 1
        ")->execute([$servicio, $espesor_mm, $precio]);

        jsonResponse(['ok' => true]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($body['id'] ?? ($_GET['id'] ?? 0));
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
        $db->prepare("UPDATE maquila_precios SET activo = 0 WHERE id = ?")->execute([$id]);
        jsonResponse(['ok' => true]);
        exit;
    }

    jsonResponse(['error' => 'Método no permitido'], 405);
    exit;
}

jsonResponse(['error' => 'Recurso no encontrado'], 404);
