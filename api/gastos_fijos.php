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

// ─── GET — listar conceptos o pagos de un periodo ──────────────────────────────
if ($method === 'GET') {
    if ($accion === 'conceptos') {
        $solo_activos = ($_GET['activos'] ?? '1') === '1';
        $where = $solo_activos ? 'WHERE gfc.activo = 1' : '';
        $stmt = $pdo->query("
            SELECT gfc.*, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre
            FROM gastos_fijos_conceptos gfc
            JOIN cuentas_contables c ON c.id = gfc.cuenta_id
            $where
            ORDER BY gfc.nombre ASC
        ");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($accion === 'pagos') {
        $periodo = $_GET['periodo'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            jsonResponse(['error' => 'Periodo inválido']); exit;
        }
        $stmt = $pdo->prepare("
            SELECT gfc.id AS concepto_id, gfc.nombre, gfc.monto_estimado, gfc.frecuencia,
                   c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre,
                   p.id AS pago_id, p.fecha_pago, p.monto, p.comprobante_url
            FROM gastos_fijos_conceptos gfc
            JOIN cuentas_contables c ON c.id = gfc.cuenta_id
            LEFT JOIN gastos_fijos_pagos p ON p.concepto_id = gfc.id AND p.periodo = ?
            WHERE gfc.activo = 1
            ORDER BY gfc.nombre ASC
        ");
        $stmt->execute([$periodo]);
        jsonResponse(['periodo' => $periodo, 'filas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    jsonResponse(['error' => 'Acción no soportada'], 400);
    exit;
}

// ─── Solo quien tenga gestionar_contabilidad puede escribir ───────────────────
if (!tienePermiso($rol, 'gestionar_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── POST — crear concepto o guardar pago del periodo ─────────────────────────
if ($method === 'POST') {
    if ($accion === 'crear_concepto') {
        $nombre         = trim($body['nombre'] ?? '');
        $cuenta_id      = (int)($body['cuenta_id'] ?? 0);
        $monto_estimado = (float)($body['monto_estimado'] ?? 0);
        $frecuencia     = $body['frecuencia'] ?? 'mensual';

        if (!$nombre || !$cuenta_id || !in_array($frecuencia, ['mensual', 'bimestral', 'anual'])) {
            jsonResponse(['error' => 'Datos incompletos o inválidos']); exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO gastos_fijos_conceptos (nombre, cuenta_id, monto_estimado, frecuencia)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $cuenta_id, $monto_estimado, $frecuencia]);
        jsonResponse(['ok' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($accion === 'guardar_pago') {
        $concepto_id = (int)($body['concepto_id'] ?? 0);
        $periodo     = $body['periodo'] ?? '';
        $fecha_pago  = $body['fecha_pago'] ?? '';
        $monto       = (float)($body['monto'] ?? 0);

        if (!$concepto_id || !preg_match('/^\d{4}-\d{2}$/', $periodo) || !$fecha_pago) {
            jsonResponse(['error' => 'Datos incompletos o inválidos']); exit;
        }

        $cuentaId = $pdo->prepare("SELECT cuenta_id FROM gastos_fijos_conceptos WHERE id = ?");
        $cuentaId->execute([$concepto_id]);
        $cuentaId = $cuentaId->fetchColumn();
        if (!$cuentaId) { jsonResponse(['error' => 'Concepto no encontrado']); exit; }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO gastos_fijos_pagos (concepto_id, periodo, fecha_pago, monto)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE fecha_pago = VALUES(fecha_pago), monto = VALUES(monto)
            ");
            $stmt->execute([$concepto_id, $periodo, $fecha_pago, $monto]);

            $stmtId = $pdo->prepare("SELECT id FROM gastos_fijos_pagos WHERE concepto_id = ? AND periodo = ?");
            $stmtId->execute([$concepto_id, $periodo]);
            $pagoId = $stmtId->fetchColumn();

            $nombreConcepto = $pdo->prepare("SELECT nombre FROM gastos_fijos_conceptos WHERE id = ?");
            $nombreConcepto->execute([$concepto_id]);
            $nombreConcepto = $nombreConcepto->fetchColumn();

            $stmtMov = $pdo->prepare("
                INSERT INTO movimientos_contables (cuenta_id, origen_tabla, origen_id, monto, fecha_movimiento, tipo_financiero, descripcion)
                VALUES (?, 'gastos_fijos_pagos', ?, ?, ?, 'gasto_operativo', ?)
                ON DUPLICATE KEY UPDATE monto = VALUES(monto), fecha_movimiento = VALUES(fecha_movimiento), descripcion = VALUES(descripcion)
            ");
            $stmtMov->execute([$cuentaId, $pagoId, $monto, $fecha_pago, "$nombreConcepto $periodo"]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Error al guardar']); exit;
        }

        jsonResponse(['ok' => true, 'pago_id' => $pagoId]);
        exit;
    }

    jsonResponse(['error' => 'Acción no soportada'], 400);
    exit;
}

// ─── PUT — editar/desactivar concepto ──────────────────────────────────────────
if ($method === 'PUT' && $accion === 'editar_concepto') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $campos = [];
    $valores = [];
    if (isset($body['nombre']))         { $campos[] = 'nombre = ?';         $valores[] = trim($body['nombre']); }
    if (isset($body['cuenta_id']))      { $campos[] = 'cuenta_id = ?';      $valores[] = (int)$body['cuenta_id']; }
    if (isset($body['monto_estimado'])) { $campos[] = 'monto_estimado = ?'; $valores[] = (float)$body['monto_estimado']; }
    if (isset($body['frecuencia']) && in_array($body['frecuencia'], ['mensual','bimestral','anual'])) {
        $campos[] = 'frecuencia = ?'; $valores[] = $body['frecuencia'];
    }
    if (isset($body['activo'])) { $campos[] = 'activo = ?'; $valores[] = (int)$body['activo']; }
    if (!$campos) { jsonResponse(['error' => 'Nada que actualizar']); exit; }

    $valores[] = $id;
    $stmt = $pdo->prepare("UPDATE gastos_fijos_conceptos SET " . implode(', ', $campos) . " WHERE id = ?");
    $stmt->execute($valores);
    jsonResponse(['ok' => true]);
    exit;
}

jsonResponse(['error' => 'Método no soportado'], 405);
