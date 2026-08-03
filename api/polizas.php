<?php
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user = requireSessionApi();
$rol  = $user['rol'];
if (!tienePermiso($rol, 'ver_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}
$pdo    = getDB();
$method = $_SERVER['REQUEST_METHOD'];

$PREFIJO_TIPO = ['diario' => 'D', 'ingresos' => 'I', 'egresos' => 'E'];

// ─── GET — listar pólizas (con totales) o detalle de una (con líneas) ────────
if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $id = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM polizas WHERE id = ?");
        $stmt->execute([$id]);
        $poliza = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$poliza) { jsonResponse(['error' => 'No encontrada'], 404); exit; }

        $stmt = $pdo->prepare("
            SELECT pl.*, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre
            FROM polizas_lineas pl
            JOIN cuentas_contables c ON c.id = pl.cuenta_id
            WHERE pl.poliza_id = ? ORDER BY pl.orden ASC, pl.id ASC
        ");
        $stmt->execute([$id]);
        $poliza['lineas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($poliza);
        exit;
    }

    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT p.*, COALESCE(SUM(pl.debe), 0) AS total
        FROM polizas p
        LEFT JOIN polizas_lineas pl ON pl.poliza_id = p.id
        WHERE p.fecha BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY p.fecha DESC, p.id DESC
    ");
    $stmt->execute([$desde, $hasta]);
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// ─── Solo quien tenga gestionar_contabilidad puede escribir ───────────────────
if (!tienePermiso($rol, 'gestionar_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── POST — crear póliza con sus líneas (debe cuadrar Debe = Haber) ──────────
if ($method === 'POST') {
    $tipo     = $body['tipo'] ?? '';
    $fecha    = $body['fecha'] ?? '';
    $concepto = trim($body['concepto'] ?? '');
    $lineas   = $body['lineas'] ?? [];

    if (!isset($PREFIJO_TIPO[$tipo]) || !$fecha || !$concepto) {
        jsonResponse(['error' => 'Tipo, fecha y concepto son obligatorios']); exit;
    }
    if (!is_array($lineas) || count($lineas) < 2) {
        jsonResponse(['error' => 'Se requieren al menos 2 líneas']); exit;
    }

    $totalDebe  = 0;
    $totalHaber = 0;
    $lineasLimpias = [];
    foreach ($lineas as $i => $l) {
        $cuentaId = (int)($l['cuenta_id'] ?? 0);
        $debe     = round((float)($l['debe'] ?? 0), 2);
        $haber    = round((float)($l['haber'] ?? 0), 2);
        if (!$cuentaId || ($debe <= 0 && $haber <= 0) || ($debe > 0 && $haber > 0)) {
            jsonResponse(['error' => 'Línea ' . ($i + 1) . ' inválida: elige cuenta y captura Debe o Haber (no ambos)']); exit;
        }
        $totalDebe  += $debe;
        $totalHaber += $haber;
        $lineasLimpias[] = [$cuentaId, $debe, $haber, trim($l['concepto_linea'] ?? ''), $i];
    }
    if (round($totalDebe, 2) !== round($totalHaber, 2)) {
        jsonResponse(['error' => 'La póliza no cuadra: Debe $' . number_format($totalDebe, 2) . ' vs Haber $' . number_format($totalHaber, 2)]); exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO polizas (folio, tipo, fecha, concepto, creado_por) VALUES ('', ?, ?, ?, ?)");
        $stmt->execute([$tipo, $fecha, $concepto, $user['id']]);
        $polizaId = $pdo->lastInsertId();

        $folio = $PREFIJO_TIPO[$tipo] . '-' . str_pad($polizaId, 6, '0', STR_PAD_LEFT);
        $pdo->prepare("UPDATE polizas SET folio = ? WHERE id = ?")->execute([$folio, $polizaId]);

        $stmtL = $pdo->prepare("INSERT INTO polizas_lineas (poliza_id, cuenta_id, debe, haber, concepto_linea, orden) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($lineasLimpias as $l) {
            $stmtL->execute([$polizaId, $l[0], $l[1], $l[2], $l[3], $l[4]]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Error al guardar la póliza']); exit;
    }

    jsonResponse(['ok' => true, 'id' => $polizaId, 'folio' => $folio]);
    exit;
}

// ─── PUT — anular póliza (nunca se borra físicamente, es evidencia de auditoría)
if ($method === 'PUT') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT estado FROM polizas WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetchColumn();
    if ($actual === false) { jsonResponse(['error' => 'No encontrada'], 404); exit; }
    if ($actual === 'anulada') { jsonResponse(['error' => 'Ya está anulada']); exit; }

    $pdo->prepare("UPDATE polizas SET estado = 'anulada' WHERE id = ?")->execute([$id]);
    jsonResponse(['ok' => true]);
    exit;
}

jsonResponse(['error' => 'Método no soportado'], 405);
