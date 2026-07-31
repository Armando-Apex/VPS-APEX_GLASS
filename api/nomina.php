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

// ─── GET — listar empleados o pagos de un periodo ──────────────────────────────
if ($method === 'GET') {
    if ($accion === 'empleados') {
        $solo_activos = ($_GET['activos'] ?? '1') === '1';
        $where = $solo_activos ? 'WHERE activo = 1' : '';
        $stmt = $pdo->query("SELECT * FROM nomina_empleados $where ORDER BY nombre ASC");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($accion === 'pagos') {
        $periodo = $_GET['periodo'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            jsonResponse(['error' => 'Periodo inválido']); exit;
        }
        $stmt = $pdo->prepare("
            SELECT e.id AS empleado_id, e.nombre, e.puesto, e.departamento, e.sueldo_base,
                   p.id AS pago_id, p.fecha_pago, p.sueldo_neto, p.imss_patronal,
                   p.otras_prestaciones, p.total_pagado
            FROM nomina_empleados e
            LEFT JOIN nomina_pagos p ON p.empleado_id = e.id AND p.periodo = ?
            WHERE e.activo = 1
            ORDER BY e.nombre ASC
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

// ─── POST — crear empleado o guardar pago del periodo ─────────────────────────
if ($method === 'POST') {
    if ($accion === 'crear_empleado') {
        $nombre       = trim($body['nombre'] ?? '');
        $puesto       = trim($body['puesto'] ?? '');
        $departamento = trim($body['departamento'] ?? '');
        $sueldo_base  = (float)($body['sueldo_base'] ?? 0);

        if (!$nombre) { jsonResponse(['error' => 'El nombre es obligatorio']); exit; }

        $stmt = $pdo->prepare("
            INSERT INTO nomina_empleados (nombre, puesto, departamento, sueldo_base)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $puesto ?: null, $departamento ?: null, $sueldo_base]);
        jsonResponse(['ok' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($accion === 'guardar_pago') {
        $empleado_id        = (int)($body['empleado_id'] ?? 0);
        $periodo            = $body['periodo'] ?? '';
        $fecha_pago         = $body['fecha_pago'] ?? '';
        $sueldo_neto        = (float)($body['sueldo_neto'] ?? 0);
        $imss_patronal      = (float)($body['imss_patronal'] ?? 0);
        $otras_prestaciones = (float)($body['otras_prestaciones'] ?? 0);

        if (!$empleado_id || !preg_match('/^\d{4}-\d{2}$/', $periodo) || !$fecha_pago) {
            jsonResponse(['error' => 'Datos incompletos o inválidos']); exit;
        }

        $cuentaNomina = $pdo->query("SELECT id FROM cuentas_contables WHERE codigo = '6.1'")->fetchColumn();
        if (!$cuentaNomina) { jsonResponse(['error' => 'No existe la cuenta 6.1 Nómina en el catálogo']); exit; }

        $total = round($sueldo_neto + $imss_patronal + $otras_prestaciones, 2);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO nomina_pagos
                    (empleado_id, periodo, fecha_pago, sueldo_neto, imss_patronal, otras_prestaciones, total_pagado, cuenta_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    fecha_pago = VALUES(fecha_pago), sueldo_neto = VALUES(sueldo_neto),
                    imss_patronal = VALUES(imss_patronal), otras_prestaciones = VALUES(otras_prestaciones),
                    total_pagado = VALUES(total_pagado)
            ");
            $stmt->execute([$empleado_id, $periodo, $fecha_pago, $sueldo_neto, $imss_patronal, $otras_prestaciones, $total, $cuentaNomina]);

            $pagoId = $pdo->query("SELECT id FROM nomina_pagos WHERE empleado_id = " . (int)$empleado_id . " AND periodo = " . $pdo->quote($periodo))->fetchColumn();

            $stmtMov = $pdo->prepare("
                INSERT INTO movimientos_contables (cuenta_id, origen_tabla, origen_id, monto, fecha_movimiento, tipo_financiero, descripcion)
                VALUES (?, 'nomina_pagos', ?, ?, ?, 'gasto_operativo', ?)
                ON DUPLICATE KEY UPDATE monto = VALUES(monto), fecha_movimiento = VALUES(fecha_movimiento), descripcion = VALUES(descripcion)
            ");
            $stmtMov->execute([$cuentaNomina, $pagoId, $total, $fecha_pago, "Nómina $periodo"]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['error' => 'Error al guardar']); exit;
        }

        jsonResponse(['ok' => true, 'pago_id' => $pagoId, 'total' => $total]);
        exit;
    }

    jsonResponse(['error' => 'Acción no soportada'], 400);
    exit;
}

// ─── PUT — editar/desactivar empleado ──────────────────────────────────────────
if ($method === 'PUT' && $accion === 'editar_empleado') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $campos = [];
    $valores = [];
    foreach (['nombre', 'puesto', 'departamento'] as $campo) {
        if (isset($body[$campo])) { $campos[] = "$campo = ?"; $valores[] = trim($body[$campo]); }
    }
    if (isset($body['sueldo_base'])) { $campos[] = 'sueldo_base = ?'; $valores[] = (float)$body['sueldo_base']; }
    if (isset($body['activo']))      { $campos[] = 'activo = ?';      $valores[] = (int)$body['activo']; }
    if (!$campos) { jsonResponse(['error' => 'Nada que actualizar']); exit; }

    $valores[] = $id;
    $stmt = $pdo->prepare("UPDATE nomina_empleados SET " . implode(', ', $campos) . " WHERE id = ?");
    $stmt->execute($valores);
    jsonResponse(['ok' => true]);
    exit;
}

jsonResponse(['error' => 'Método no soportado'], 405);
