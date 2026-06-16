<?php
// ============================================================
//  APEX GLASS - API: Reporte de Direcci��n
//  M��todo: GET  ?periodo=mes_actual|mes_anterior|3meses|6meses|a�0�9o|todo
//  fecha_cierre: campo fecha_cierre de la tabla ordenes
//  local/foraneo: campo ubicacion ('LOCAL' / 'FORANEO')
// ============================================================
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$pdo     = getDB();
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
        $desde = $hoy->format('Y-01-01');
        $hasta = $hoy->format('Y-m-d'); break;
    default:
        $desde = '2020-01-01';
        $hasta = $hoy->format('Y-m-d');
}

$params4 = [$desde, $hasta, $desde.' 00:00:00', $hasta.' 23:59:59'];

// ���� Resumen global del per��odo ����
// fecha_cierre: usa campo fecha_cierre (datetime), fallback a updated_at
// ubicacion: 'LOCAL' o 'FORANEO' (may��sculas)
$stmt = $pdo->prepare("
    SELECT
        COUNT(*)                                                                     AS total,
        SUM(estado = 'entregada')                                                    AS cerradas,
        SUM(estado = 'activa')                                                       AS abiertas,
        SUM(estado = 'entregada'
            AND DATE(COALESCE(fecha_cierre, updated_at)) <= fecha_entrega)           AS a_tiempo,
        SUM(estado = 'entregada'
            AND DATE(COALESCE(fecha_cierre, updated_at)) >  fecha_entrega)           AS con_retraso,
        SUM(estado = 'activa'
            AND (fecha_entrega IS NULL OR fecha_entrega >= CURDATE()))               AS en_proceso,
        SUM(estado = 'activa'
            AND fecha_entrega < CURDATE())                                           AS retraso_abierto,
        AVG(CASE WHEN estado = 'entregada'
                 THEN DATEDIFF(DATE(COALESCE(fecha_cierre, updated_at)), fecha_pedido)
            END)                                                                     AS prom_dias,
        SUM(estado = 'pendiente_vobo')                                               AS vobo_pendientes,
        SUM(UPPER(COALESCE(ubicacion,'')) != 'FORANEO')                              AS local,
        SUM(UPPER(ubicacion) = 'FORANEO')                                           AS foraneo
    FROM ordenes
    WHERE estado != 'cancelada'
      AND (fecha_pedido BETWEEN ? AND ?
           OR (fecha_pedido IS NULL AND created_at BETWEEN ? AND ?))
");
$stmt->execute($params4);
$resumen = $stmt->fetch(PDO::FETCH_ASSOC);

// ���� Concentrado mensual ����
$stmtM = $pdo->prepare("
    SELECT
        DATE_FORMAT(COALESCE(fecha_pedido, DATE(created_at)), '%Y-%m') AS mes_key,
        DATE_FORMAT(COALESCE(fecha_pedido, DATE(created_at)), '%b %Y') AS mes_label,
        COUNT(*)                                                                     AS total,
        SUM(estado = 'entregada')                                                    AS cerradas,
        SUM(estado = 'activa')                                                       AS abiertas,
        SUM(estado = 'entregada'
            AND DATE(COALESCE(fecha_cierre, updated_at)) <= fecha_entrega)           AS a_tiempo,
        SUM(estado = 'entregada'
            AND DATE(COALESCE(fecha_cierre, updated_at)) >  fecha_entrega)           AS con_retraso,
        SUM(estado = 'activa'
            AND (fecha_entrega IS NULL OR fecha_entrega >= CURDATE()))               AS en_proceso,
        SUM(estado = 'activa'
            AND fecha_entrega < CURDATE())                                           AS retraso_abierto,
        AVG(CASE WHEN estado = 'entregada'
                 THEN DATEDIFF(DATE(COALESCE(fecha_cierre, updated_at)), fecha_pedido)
            END)                                                                     AS prom_dias,
        SUM(estado = 'pendiente_vobo')                                               AS vobo_pendientes,
        SUM(UPPER(COALESCE(ubicacion,'')) != 'FORANEO')                              AS local,
        SUM(UPPER(ubicacion) = 'FORANEO')                                           AS foraneo
    FROM ordenes
    WHERE estado != 'cancelada'
      AND (fecha_pedido BETWEEN ? AND ?
           OR (fecha_pedido IS NULL AND created_at BETWEEN ? AND ?))
    GROUP BY mes_key
    ORDER BY mes_key ASC
");
$stmtM->execute($params4);
$mensual = $stmtM->fetchAll(PDO::FETCH_ASSOC);

// Fila de totales
$totales = [
    'es_total'        => true,
    'mes_label'       => 'TOTAL',
    'total'           => array_sum(array_column($mensual, 'total')),
    'cerradas'        => array_sum(array_column($mensual, 'cerradas')),
    'abiertas'        => array_sum(array_column($mensual, 'abiertas')),
    'a_tiempo'        => array_sum(array_column($mensual, 'a_tiempo')),
    'con_retraso'     => array_sum(array_column($mensual, 'con_retraso')),
    'en_proceso'      => array_sum(array_column($mensual, 'en_proceso')),
    'retraso_abierto' => array_sum(array_column($mensual, 'retraso_abierto')),
    'vobo_pendientes' => array_sum(array_column($mensual, 'vobo_pendientes')),
    'prom_dias'       => count($mensual) > 0
                         ? array_sum(array_column($mensual, 'prom_dias')) / count($mensual)
                         : null,
    'local'           => array_sum(array_column($mensual, 'local')),
    'foraneo'         => array_sum(array_column($mensual, 'foraneo')),
];
$mensual[] = $totales;

// ── Resumen financiero del período (órdenes S-001+) ──
$stmtF = $pdo->prepare("
    SELECT
        COALESCE(SUM(c.total), 0)                                              AS ventas,
        COALESCE(SUM(c.saldo_pagado), 0)                                       AS cobrado,
        COALESCE(SUM(COALESCE(c.total,0) - COALESCE(c.saldo_pagado,0)), 0)     AS por_cobrar,
        AVG(CASE WHEN COALESCE(c.total,0) > 0 THEN c.total ELSE NULL END)      AS ticket_promedio
    FROM ordenes o
    JOIN cotizaciones c ON c.orden_id = o.id
    WHERE o.estado != 'cancelada'
      AND c.estatus != 'cancelada'
      AND LEFT(o.folio, 1) >= 'S'
      AND (o.fecha_pedido BETWEEN ? AND ?
           OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
");
$stmtF->execute($params4);
$finanzas = $stmtF->fetch(PDO::FETCH_ASSOC);

// ── Cotizaciones desde COT-0100 (solo estatus cotizacion) ──
$stmtC = $pdo->prepare("
    SELECT
        COUNT(c.id)                                                                         AS total_cots,
        COALESCE(SUM(c.total), 0)                                                          AS total_cotizado,
        AVG(CASE WHEN COALESCE(c.total,0) > 0 THEN c.total ELSE NULL END)                  AS ticket_promedio,
        COALESCE(SUM(CASE WHEN c.asesor_nombre LIKE '%Bethy%' THEN c.total ELSE 0 END), 0) AS bethy_total,
        COALESCE(SUM(CASE WHEN c.asesor_nombre LIKE '%Cynthia%' THEN c.total ELSE 0 END),0) AS cynthia_total
    FROM cotizaciones c
    WHERE c.folio >= 'COT-0100'
      AND c.estatus = 'cotizacion'
      AND c.created_at BETWEEN ? AND ?
");
$stmtC->execute([$desde . ' 00:00:00', $hasta . ' 23:59:59']);
$cots_resumen = $stmtC->fetch(PDO::FETCH_ASSOC);

// ── Tasa de conversión cotizaciones (período) ──
$stmtConv = $pdo->prepare("
    SELECT
        COUNT(*)                          AS total_cots,
        SUM(orden_id IS NOT NULL)         AS convertidas
    FROM cotizaciones
    WHERE folio >= 'COT-0100'
      AND estatus != 'cancelada'
      AND created_at BETWEEN ? AND ?
");
$stmtConv->execute([$desde . ' 00:00:00', $hasta . ' 23:59:59']);
$conversion = $stmtConv->fetch(PDO::FETCH_ASSOC);

// ── Top 5 clientes por ventas (período) ──
$stmtTop = $pdo->prepare("
    SELECT cl.nombre, COUNT(o.id) AS ordenes, COALESCE(SUM(c.total), 0) AS total_ventas
    FROM ordenes o
    JOIN cotizaciones c ON c.orden_id = o.id
    JOIN clientes cl ON cl.id = c.cliente_id
    WHERE o.estado != 'cancelada' AND c.estatus != 'cancelada'
      AND (o.fecha_pedido BETWEEN ? AND ?
           OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
    GROUP BY cl.id, cl.nombre
    ORDER BY total_ventas DESC
    LIMIT 5
");
$stmtTop->execute($params4);
$top_clientes = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

// ── Órdenes y ventas por asesor (período) ──
$stmtAsesor = $pdo->prepare("
    SELECT c.asesor_nombre, COUNT(o.id) AS ordenes, COALESCE(SUM(c.total), 0) AS total_ventas
    FROM cotizaciones c
    JOIN ordenes o ON o.id = c.orden_id
    WHERE o.estado != 'cancelada' AND c.estatus != 'cancelada'
      AND LEFT(o.folio, 1) >= 'S'
      AND (o.fecha_pedido BETWEEN ? AND ?
           OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
    GROUP BY c.asesor_nombre
    ORDER BY total_ventas DESC
");
$stmtAsesor->execute($params4);
$por_asesor = $stmtAsesor->fetchAll(PDO::FETCH_ASSOC);

// ── Tasa de reproceso (período) ──
$stmtRep = $pdo->prepare("
    SELECT
        (SELECT COUNT(DISTINCT r.pieza_id)
         FROM reprocesos r JOIN ordenes o ON o.id = r.orden_id
         WHERE o.fecha_pedido BETWEEN ? AND ?
            OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?)
        ) AS piezas_reproceso,
        (SELECT COUNT(*) FROM piezas p
         JOIN ordenes o ON o.id = p.orden_id
         WHERE o.estado != 'cancelada'
           AND (o.fecha_pedido BETWEEN ? AND ?
                OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
        ) AS total_piezas
");
$stmtRep->execute([$desde, $hasta, $desde.' 00:00:00', $hasta.' 23:59:59',
                   $desde, $hasta, $desde.' 00:00:00', $hasta.' 23:59:59']);
$reproceso = $stmtRep->fetch(PDO::FETCH_ASSOC);

// ── Ocupación horno últimas 8 semanas ──
$stmtHorno = $pdo->prepare("
    SELECT
        YEARWEEK(created_at, 1)               AS semana,
        DATE_FORMAT(MIN(created_at), '%d %b') AS semana_inicio,
        COUNT(*)                              AS piezas
    FROM historial_estatus
    WHERE estatus_nuevo = 'en_horno'
      AND created_at >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
    GROUP BY semana
    ORDER BY semana ASC
");
$stmtHorno->execute();
$horno_semanas = $stmtHorno->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'resumen'       => $resumen,
    'mensual'       => $mensual,
    'finanzas'      => $finanzas,
    'cots_resumen'  => $cots_resumen,
    'conversion'    => $conversion,
    'top_clientes'  => $top_clientes,
    'por_asesor'    => $por_asesor,
    'reproceso'     => $reproceso,
    'horno_semanas' => $horno_semanas,
]);