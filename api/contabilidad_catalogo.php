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

// ─── GET — listar árbol de cuentas ────────────────────────────────────────────
if ($method === 'GET') {
    $solo_activas = ($_GET['activas'] ?? '1') === '1';
    $where = $solo_activas ? 'WHERE activo = 1' : '';
    $stmt  = $pdo->query("SELECT * FROM cuentas_contables $where ORDER BY orden ASC, codigo ASC");
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ─── Solo quien tenga gestionar_contabilidad puede escribir ───────────────────
if (!tienePermiso($rol, 'gestionar_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── POST — crear cuenta ──────────────────────────────────────────────────────
if ($method === 'POST') {
    $codigo          = trim($body['codigo'] ?? '');
    $nombre          = trim($body['nombre'] ?? '');
    $cuenta_padre_id = isset($body['cuenta_padre_id']) && $body['cuenta_padre_id'] !== '' ? (int)$body['cuenta_padre_id'] : null;
    $tipo_financiero = $body['tipo_financiero'] ?? '';
    $naturaleza      = $body['naturaleza'] ?? '';
    $es_acumulativa  = !empty($body['es_acumulativa']) ? 1 : 0;
    $nivel           = (int)($body['nivel'] ?? 2);
    $orden           = (int)($body['orden'] ?? 0);

    $tipos_validos = ['ingreso','costo_venta','gasto_operativo','financiero','impuesto'];
    if (!$codigo || !$nombre || !in_array($tipo_financiero, $tipos_validos) || !in_array($naturaleza, ['suma','resta'])) {
        jsonResponse(['error' => 'Datos incompletos o inválidos']); exit;
    }

    $stmt = $pdo->prepare("
        INSERT INTO cuentas_contables (codigo, nombre, cuenta_padre_id, tipo_financiero, naturaleza, es_acumulativa, nivel, orden)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    try {
        $stmt->execute([$codigo, $nombre, $cuenta_padre_id, $tipo_financiero, $naturaleza, $es_acumulativa, $nivel, $orden]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Código ya existe o dato inválido']); exit;
    }
    jsonResponse(['ok' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

// ─── PUT — editar cuenta (o desactivar) ───────────────────────────────────────
if ($method === 'PUT') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $campos = [];
    $valores = [];
    foreach (['codigo','nombre','tipo_financiero','naturaleza'] as $campo) {
        if (isset($body[$campo]) && $body[$campo] !== '') {
            $campos[] = "$campo = ?";
            $valores[] = trim($body[$campo]);
        }
    }
    foreach (['es_acumulativa','activo','nivel','orden'] as $campo) {
        if (isset($body[$campo])) {
            $campos[] = "$campo = ?";
            $valores[] = (int)$body[$campo];
        }
    }
    if (array_key_exists('cuenta_padre_id', $body)) {
        $campos[] = "cuenta_padre_id = ?";
        $valores[] = $body['cuenta_padre_id'] !== '' && $body['cuenta_padre_id'] !== null ? (int)$body['cuenta_padre_id'] : null;
    }
    if (!$campos) { jsonResponse(['error' => 'Nada que actualizar']); exit; }

    $valores[] = $id;
    $stmt = $pdo->prepare("UPDATE cuentas_contables SET " . implode(', ', $campos) . " WHERE id = ?");
    $stmt->execute($valores);
    jsonResponse(['ok' => true]);
    exit;
}

jsonResponse(['error' => 'Método no soportado'], 405);
