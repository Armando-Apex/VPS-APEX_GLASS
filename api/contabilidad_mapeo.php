<?php
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user  = requireSessionApi();
$rol   = $user['rol'];
if (!tienePermiso($rol, 'ver_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}
$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ─── GET — listar reglas + valores de origen disponibles ──────────────────────
if ($method === 'GET') {
    $reglas = $pdo->query("
        SELECT r.id, r.origen_tipo, r.origen_valor, r.cuenta_id, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre
        FROM cuenta_mapeo_reglas r
        JOIN cuentas_contables c ON c.id = r.cuenta_id
        ORDER BY r.origen_tipo, r.origen_valor
    ")->fetchAll(PDO::FETCH_ASSOC);

    $valoresTipo = $pdo->query("
        SELECT DISTINCT tipo AS valor FROM oc_partidas WHERE tipo IS NOT NULL AND tipo <> ''
    ")->fetchAll(PDO::FETCH_COLUMN);

    $valoresCategoria = $pdo->query("
        SELECT DISTINCT categoria AS valor FROM ordenes_compra WHERE categoria IS NOT NULL AND categoria <> ''
    ")->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse([
        'reglas' => $reglas,
        'valores_oc_partida_tipo' => $valoresTipo,
        'valores_oc_categoria' => $valoresCategoria,
    ]);
    exit;
}

// ─── Solo quien tenga gestionar_contabilidad puede escribir ───────────────────
if (!tienePermiso($rol, 'gestionar_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── POST — crear o actualizar regla (upsert por origen_tipo+origen_valor) ────
if ($method === 'POST') {
    $origen_tipo  = $body['origen_tipo'] ?? '';
    $origen_valor = trim($body['origen_valor'] ?? '');
    $cuenta_id    = (int)($body['cuenta_id'] ?? 0);

    if (!in_array($origen_tipo, ['oc_partida_tipo', 'oc_categoria']) || !$origen_valor || !$cuenta_id) {
        jsonResponse(['error' => 'Datos incompletos o inválidos']); exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO cuenta_mapeo_reglas (origen_tipo, origen_valor, cuenta_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE cuenta_id = VALUES(cuenta_id)
    ");
    $stmt->execute([$origen_tipo, $origen_valor, $cuenta_id]);
    jsonResponse(['ok' => true]);
    exit;
}

// ─── DELETE — quitar regla ─────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
    $stmt = $pdo->prepare("DELETE FROM cuenta_mapeo_reglas WHERE id = ?");
    $stmt->execute([$id]);
    jsonResponse(['ok' => true]);
    exit;
}

jsonResponse(['error' => 'Método no soportado'], 405);
