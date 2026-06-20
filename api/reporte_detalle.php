<?php
// ============================================================
//  APEX GLASS - API: Detalle de KPI reporte direcci��n
//  GET ?tipo=a_tiempo|con_retraso|en_proceso&periodo=mes_actual|...
//  Usa misma l��gica que api/reporte_direccion.php:
//  fecha_cierre = MAX(historial_estatus) con fallback a updated_at
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
requirePermisoApi('ver_reportes');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$pdo     = getDB();
$tipo    = $_GET['tipo']    ?? '';
$periodo = $_GET['periodo'] ?? 'mes_actual';
$hoy     = new DateTime();

switch ($periodo) {
    case 'mes_actual':
        $desde = (clone $hoy)->modify('first day of this month')->format('Y-m-01');
        $hasta = $hoy->format('Y-m-d'); break;
    case 'mes_anterior':
        $desde = (clone $hoy)->modify('first day of last month')->format('Y-m-01');
        $hasta = (clone $hoy)->modify('last day of last month')->format('Y-m-t'); break;
    case '3meses':
        $desde = (clone $hoy)->modify('-3 months')->format('Y-m-d');
        $hasta = $hoy->format('Y-m-d'); break;
    case '6meses':
        $desde = (clone $hoy)->modify('-6 months')->format('Y-m-d');
        $hasta = $hoy->format('Y-m-d'); break;
    case 'a�0�9o':
    case 'anio':
        $desde = $hoy->format('Y-01-01');
        $hasta = $hoy->format('Y-m-d'); break;
    default:
        $desde = '2020-01-01';
        $hasta = $hoy->format('Y-m-d');
}

$params4 = [$desde, $hasta, $desde.' 00:00:00', $hasta.' 23:59:59'];

// Usa campo fecha_cierre de tabla ordenes (fallback a updated_at)
$subCierre = "";

if ($tipo === 'a_tiempo') {
    $stmt = $pdo->prepare("
        SELECT
            o.folio,
            o.cliente_nombre,
            o.asesor,
            o.fecha_entrega,
            DATE(COALESCE(o.fecha_cierre, o.updated_at))              AS fecha_real_entrega,
            DATEDIFF(o.fecha_entrega,
                     DATE(COALESCE(o.fecha_cierre, o.updated_at)))    AS dias_diff
        FROM ordenes o
        WHERE o.estado = 'entregada'
          AND o.fecha_entrega IS NOT NULL
          AND DATE(COALESCE(o.fecha_cierre, o.updated_at)) <= o.fecha_entrega
          AND (o.fecha_pedido BETWEEN ? AND ?
               OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
        ORDER BY o.fecha_entrega DESC
    ");
    $stmt->execute($params4);

} elseif ($tipo === 'con_retraso') {
    $stmt = $pdo->prepare("
        SELECT
            o.folio,
            o.cliente_nombre,
            o.asesor,
            o.fecha_entrega,
            DATE(COALESCE(o.fecha_cierre, o.updated_at))              AS fecha_real_entrega,
            DATEDIFF(DATE(COALESCE(o.fecha_cierre, o.updated_at)),
                     o.fecha_entrega)                                  AS dias_diff
        FROM ordenes o
        WHERE o.estado = 'entregada'
          AND o.fecha_entrega IS NOT NULL
          AND DATE(COALESCE(o.fecha_cierre, o.updated_at)) > o.fecha_entrega
          AND (o.fecha_pedido BETWEEN ? AND ?
               OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
        ORDER BY dias_diff DESC
    ");
    $stmt->execute($params4);

} elseif ($tipo === 'en_proceso') {
    $stmt = $pdo->prepare("
        SELECT
            o.folio,
            o.cliente_nombre,
            o.asesor,
            o.fecha_entrega,
            ROUND(SUM(p.estatus IN ('terminado','entregado')) / COUNT(*) * 100) AS avance_pct,
            COUNT(*)                                        AS total_piezas,
            DATEDIFF(o.fecha_entrega, CURDATE())            AS dias_para_entrega
        FROM ordenes o
        JOIN piezas p ON p.orden_id = o.id
        WHERE o.estado = 'activa'
          AND (o.fecha_entrega IS NULL OR o.fecha_entrega >= CURDATE())
          AND (o.fecha_pedido BETWEEN ? AND ?
               OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
        GROUP BY o.folio, o.cliente_nombre, o.asesor, o.fecha_entrega
        ORDER BY o.fecha_entrega ASC
    ");
    $stmt->execute($params4);

} else {
    jsonResponse(['error' => 'Tipo no v��lido'], 400);
    exit;
}

$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
jsonResponse(['tipo' => $tipo, 'periodo' => $periodo, 'ordenes' => $ordenes]);