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
$accion = $_GET['accion'] ?? '';

$categorias_validas = ['viaticos', 'papeleria', 'mantenimiento', 'combustible', 'otro'];

// ─── GET — listar movimientos (rango de fechas opcional) ───────────────────────
if ($method === 'GET') {
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT m.*, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre, e.nombre AS empleado_nombre
        FROM caja_chica_movimientos m
        JOIN cuentas_contables c ON c.id = m.cuenta_id
        LEFT JOIN nomina_empleados e ON e.id = m.empleado_id
        WHERE m.fecha BETWEEN ? AND ?
        ORDER BY m.fecha DESC, m.id DESC
    ");
    $stmt->execute([$desde, $hasta]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach ($filas as $f) { $total += (float)$f['monto']; }

    jsonResponse(['desde' => $desde, 'hasta' => $hasta, 'filas' => $filas, 'total' => round($total, 2)]);
    exit;
}

// ─── Solo quien tenga gestionar_contabilidad puede escribir ───────────────────
if (!tienePermiso($rol, 'gestionar_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── POST — registrar movimiento ───────────────────────────────────────────────
if ($method === 'POST') {
    $fecha           = $body['fecha'] ?? '';
    $concepto        = trim($body['concepto'] ?? '');
    $monto           = (float)($body['monto'] ?? 0);
    $categoria       = $body['categoria'] ?? 'otro';
    $cuenta_id       = (int)($body['cuenta_id'] ?? 0);
    $empleado_id     = !empty($body['empleado_id']) ? (int)$body['empleado_id'] : null;
    $comprobante_url = trim($body['comprobante_url'] ?? '');

    if (!$fecha || !$concepto || $monto <= 0 || !$cuenta_id || !in_array($categoria, $categorias_validas)) {
        jsonResponse(['error' => 'Datos incompletos o inválidos']); exit;
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO caja_chica_movimientos
                (fecha, concepto, monto, categoria, cuenta_id, empleado_id, comprobante_url, autorizado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$fecha, $concepto, $monto, $categoria, $cuenta_id, $empleado_id, $comprobante_url ?: null, $user['nombre'] ?? $rol]);
        $movId = $pdo->lastInsertId();

        $stmtMov = $pdo->prepare("
            INSERT INTO movimientos_contables (cuenta_id, origen_tabla, origen_id, monto, fecha_movimiento, tipo_financiero, descripcion)
            VALUES (?, 'caja_chica_movimientos', ?, ?, ?, 'gasto_operativo', ?)
        ");
        $stmtMov->execute([$cuenta_id, $movId, $monto, $fecha, $concepto]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Error al guardar']); exit;
    }

    jsonResponse(['ok' => true, 'id' => $movId]);
    exit;
}

// ─── DELETE — eliminar movimiento (también quita su reflejo contable) ─────────
if ($method === 'DELETE') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM movimientos_contables WHERE origen_tabla = 'caja_chica_movimientos' AND origen_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM caja_chica_movimientos WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Error al eliminar']); exit;
    }
    jsonResponse(['ok' => true]);
    exit;
}

jsonResponse(['error' => 'Método no soportado'], 405);
