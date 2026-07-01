<?php
// ============================================================
//  APEX GLASS - API: Reporte de Direcci��n
//  M��todo: GET  ?periodo=mes_actual|mes_anterior|3meses|6meses|a�0�9o|todo
//  fecha_cierre: campo fecha_cierre de la tabla ordenes
//  local/foraneo: campo ubicacion ('LOCAL' / 'FORANEO')
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
requirePermisoApi('ver_reportes');
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

$pdo     = getDB();
$periodo = $_GET['periodo'] ?? 'mes_actual';

// Festivos para excluir del cálculo de días hábiles
$festivosSet = array_flip(
    $pdo->query("SELECT fecha FROM festivos")->fetchAll(PDO::FETCH_COLUMN)
);

function diasHabiles($d1, $d2, $festivosSet) {
    if (!$d1 || !$d2) return null;
    $cur = new DateTime($d1);
    $end = new DateTime($d2);
    if ($end <= $cur) return 0;
    $cur->modify('+1 day');
    $n = 0;
    while ($cur <= $end) {
        $dow = (int)$cur->format('N'); // 1=Lun … 7=Dom
        if ($dow < 6 && !isset($festivosSet[$cur->format('Y-m-d')])) $n++;
        $cur->modify('+1 day');
    }
    return $n;
}
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

// Retraso se mide por fecha_terminado (cuando la última pieza llegó a 'terminado'),
// NO por fecha_cierre — el cliente puede recoger tarde y eso no es retraso de producción.
// Órdenes 'activa' con TODAS las piezas en terminado/entregado también se clasifican
// como a_tiempo o con_retraso (no en_proceso/retraso_abierto) — pt.todas_terminadas.
$stmt = $pdo->prepare("
    SELECT
        COUNT(*)                                                                      AS total,
        SUM(o.estado = 'entregada')                                                   AS cerradas,
        SUM(o.estado = 'activa')                                                      AS abiertas,
        SUM(
            (o.estado = 'entregada'
                AND COALESCE(ft.fecha_terminado, DATE(COALESCE(o.fecha_cierre, o.updated_at))) <= o.fecha_entrega)
            OR (o.estado = 'activa' AND pt.todas_terminadas = 1
                AND ft.fecha_terminado <= DATE(o.fecha_entrega))
        )                                                                             AS a_tiempo,
        SUM(
            (o.estado = 'entregada'
                AND COALESCE(ft.fecha_terminado, DATE(COALESCE(o.fecha_cierre, o.updated_at))) > o.fecha_entrega)
            OR (o.estado = 'activa' AND pt.todas_terminadas = 1
                AND ft.fecha_terminado > DATE(o.fecha_entrega))
        )                                                                             AS con_retraso,
        SUM(o.estado = 'activa'
            AND (pt.todas_terminadas IS NULL OR pt.todas_terminadas = 0)
            AND (o.fecha_entrega IS NULL OR o.fecha_entrega >= CURDATE()))            AS en_proceso,
        SUM(o.estado = 'activa'
            AND (pt.todas_terminadas IS NULL OR pt.todas_terminadas = 0)
            AND o.fecha_entrega < CURDATE())                                          AS retraso_abierto,
        AVG(CASE WHEN o.estado = 'entregada' OR (o.estado = 'activa' AND pt.todas_terminadas = 1)
                 THEN DATEDIFF(
                     COALESCE(ft.fecha_terminado, DATE(COALESCE(o.fecha_cierre, o.updated_at))),
                     o.fecha_pedido)
            END)                                                                      AS prom_dias,
        SUM(o.estado = 'pendiente_vobo')                                              AS vobo_pendientes,
        SUM(UPPER(COALESCE(o.ubicacion,'')) != 'FORANEO')                             AS local,
        SUM(UPPER(o.ubicacion) = 'FORANEO')                                           AS foraneo
    FROM ordenes o
    LEFT JOIN (
        SELECT p.orden_id, DATE(MAX(h.created_at)) AS fecha_terminado
        FROM historial_estatus h
        JOIN piezas p ON p.id = h.pieza_id
        WHERE h.estatus_nuevo = 'terminado'
        GROUP BY p.orden_id
    ) ft ON ft.orden_id = o.id
    LEFT JOIN (
        SELECT orden_id,
            CASE WHEN SUM(estatus NOT IN ('terminado','entregado')) = 0 THEN 1 ELSE 0 END AS todas_terminadas
        FROM piezas
        GROUP BY orden_id
    ) pt ON pt.orden_id = o.id
    WHERE o.estado != 'cancelada'
      AND (o.fecha_pedido BETWEEN ? AND ?
           OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
");
$stmt->execute($params4);
$resumen = $stmt->fetch(PDO::FETCH_ASSOC);

// ���� Concentrado mensual ����
$stmtM = $pdo->prepare("
    SELECT
        DATE_FORMAT(COALESCE(o.fecha_pedido, DATE(o.created_at)), '%Y-%m') AS mes_key,
        DATE_FORMAT(COALESCE(o.fecha_pedido, DATE(o.created_at)), '%b %Y') AS mes_label,
        COUNT(*)                                                                      AS total,
        SUM(o.estado = 'entregada')                                                   AS cerradas,
        SUM(o.estado = 'activa')                                                      AS abiertas,
        SUM(
            (o.estado = 'entregada'
                AND COALESCE(ft.fecha_terminado, DATE(COALESCE(o.fecha_cierre, o.updated_at))) <= o.fecha_entrega)
            OR (o.estado = 'activa' AND pt.todas_terminadas = 1
                AND ft.fecha_terminado <= DATE(o.fecha_entrega))
        )                                                                             AS a_tiempo,
        SUM(
            (o.estado = 'entregada'
                AND COALESCE(ft.fecha_terminado, DATE(COALESCE(o.fecha_cierre, o.updated_at))) > o.fecha_entrega)
            OR (o.estado = 'activa' AND pt.todas_terminadas = 1
                AND ft.fecha_terminado > DATE(o.fecha_entrega))
        )                                                                             AS con_retraso,
        SUM(o.estado = 'activa'
            AND (pt.todas_terminadas IS NULL OR pt.todas_terminadas = 0)
            AND (o.fecha_entrega IS NULL OR o.fecha_entrega >= CURDATE()))            AS en_proceso,
        SUM(o.estado = 'activa'
            AND (pt.todas_terminadas IS NULL OR pt.todas_terminadas = 0)
            AND o.fecha_entrega < CURDATE())                                          AS retraso_abierto,
        AVG(CASE WHEN o.estado = 'entregada' OR (o.estado = 'activa' AND pt.todas_terminadas = 1)
                 THEN DATEDIFF(
                     COALESCE(ft.fecha_terminado, DATE(COALESCE(o.fecha_cierre, o.updated_at))),
                     o.fecha_pedido)
            END)                                                                      AS prom_dias,
        SUM(o.estado = 'pendiente_vobo')                                              AS vobo_pendientes,
        SUM(UPPER(COALESCE(o.ubicacion,'')) != 'FORANEO')                             AS local,
        SUM(UPPER(o.ubicacion) = 'FORANEO')                                           AS foraneo
    FROM ordenes o
    LEFT JOIN (
        SELECT p.orden_id, DATE(MAX(h.created_at)) AS fecha_terminado
        FROM historial_estatus h
        JOIN piezas p ON p.id = h.pieza_id
        WHERE h.estatus_nuevo = 'terminado'
        GROUP BY p.orden_id
    ) ft ON ft.orden_id = o.id
    LEFT JOIN (
        SELECT orden_id,
            CASE WHEN SUM(estatus NOT IN ('terminado','entregado')) = 0 THEN 1 ELSE 0 END AS todas_terminadas
        FROM piezas
        GROUP BY orden_id
    ) pt ON pt.orden_id = o.id
    WHERE o.estado != 'cancelada'
      AND (o.fecha_pedido BETWEEN ? AND ?
           OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
    GROUP BY mes_key
    ORDER BY mes_key ASC
");
$stmtM->execute($params4);
$mensual = $stmtM->fetchAll(PDO::FETCH_ASSOC);

// Fechas crudas por orden para calcular días hábiles (excluye sáb, dom y festivos)
$stmtDiasQ = $pdo->prepare("
    SELECT
        DATE_FORMAT(COALESCE(o.fecha_pedido, DATE(o.created_at)), '%Y-%m') AS mes_key,
        COALESCE(o.fecha_pedido, DATE(o.created_at))                        AS fecha_inicio,
        COALESCE(ft.fecha_terminado,
                 DATE(COALESCE(o.fecha_cierre, o.updated_at)))              AS fecha_fin
    FROM ordenes o
    LEFT JOIN (
        SELECT p.orden_id, DATE(MAX(h.created_at)) AS fecha_terminado
        FROM historial_estatus h
        JOIN piezas p ON p.id = h.pieza_id
        WHERE h.estatus_nuevo = 'terminado'
        GROUP BY p.orden_id
    ) ft ON ft.orden_id = o.id
    LEFT JOIN (
        SELECT orden_id,
            CASE WHEN SUM(estatus NOT IN ('terminado','entregado')) = 0 THEN 1 ELSE 0 END AS todas_terminadas
        FROM piezas
        GROUP BY orden_id
    ) pt ON pt.orden_id = o.id
    WHERE o.estado != 'cancelada'
      AND (o.estado = 'entregada' OR (o.estado = 'activa' AND pt.todas_terminadas = 1))
      AND (o.fecha_pedido BETWEEN ? AND ?
           OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
");
$stmtDiasQ->execute($params4);

$diasTodos  = [];
$diasPorMes = [];
foreach ($stmtDiasQ->fetchAll(PDO::FETCH_ASSOC) as $dr) {
    $d = diasHabiles($dr['fecha_inicio'], $dr['fecha_fin'], $festivosSet);
    if ($d !== null) {
        $diasTodos[]              = $d;
        $diasPorMes[$dr['mes_key']][] = $d;
    }
}

$promDiasTotal = count($diasTodos) > 0
    ? round(array_sum($diasTodos) / count($diasTodos), 1)
    : null;

// Reemplazar prom_dias en resumen global (antes era DATEDIFF calendario)
$resumen['prom_dias'] = $promDiasTotal;

// Reemplazar prom_dias en filas mensuales
foreach ($mensual as &$fila) {
    $mk = $fila['mes_key'] ?? '';
    if (isset($diasPorMes[$mk]) && count($diasPorMes[$mk]) > 0) {
        $fila['prom_dias'] = round(
            array_sum($diasPorMes[$mk]) / count($diasPorMes[$mk]), 1
        );
    } else {
        $fila['prom_dias'] = null;
    }
}
unset($fila);

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
    'prom_dias'       => $promDiasTotal,
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

// ── Pipeline vigente: TODAS las cotizaciones abiertas (estatus=cotizacion),
// sin filtrar por período — son cotizaciones vivas hoy sin importar cuándo se crearon.
// Filtrar por created_at las hacía "desaparecer" del reporte al cambiar de mes
// aunque siguieran pendientes de decisión del cliente.
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
");
$stmtC->execute();
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

// ── Top 5 clientes por número de pedidos (período) ──
$stmtTopPed = $pdo->prepare("
    SELECT cl.nombre, COUNT(o.id) AS ordenes
    FROM ordenes o
    JOIN cotizaciones c ON c.orden_id = o.id
    JOIN clientes cl ON cl.id = c.cliente_id
    WHERE o.estado != 'cancelada' AND c.estatus != 'cancelada'
      AND (o.fecha_pedido BETWEEN ? AND ?
           OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
    GROUP BY cl.id, cl.nombre
    ORDER BY ordenes DESC
    LIMIT 5
");
$stmtTopPed->execute($params4);
$top_clientes_pedidos = $stmtTopPed->fetchAll(PDO::FETCH_ASSOC);

// ── Top 5 clientes por M² ordenado (período) ──
$stmtTopM2 = $pdo->prepare("
    SELECT cl.nombre, COALESCE(SUM(cp.m2 * cp.cantidad), 0) AS total_m2, COUNT(DISTINCT o.id) AS ordenes
    FROM ordenes o
    JOIN cotizaciones c ON c.orden_id = o.id
    JOIN clientes cl ON cl.id = c.cliente_id
    JOIN cotizaciones_partidas cp ON cp.cotizacion_id = c.id
    WHERE o.estado != 'cancelada' AND c.estatus != 'cancelada'
      AND (o.fecha_pedido BETWEEN ? AND ?
           OR (o.fecha_pedido IS NULL AND o.created_at BETWEEN ? AND ?))
    GROUP BY cl.id, cl.nombre
    ORDER BY total_m2 DESC
    LIMIT 5
");
$stmtTopM2->execute($params4);
$top_clientes_m2 = $stmtTopM2->fetchAll(PDO::FETCH_ASSOC);

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

jsonResponse([
    'resumen'               => $resumen,
    'mensual'               => $mensual,
    'finanzas'              => $finanzas,
    'cots_resumen'          => $cots_resumen,
    'conversion'            => $conversion,
    'top_clientes'          => $top_clientes,
    'top_clientes_pedidos'  => $top_clientes_pedidos,
    'top_clientes_m2'       => $top_clientes_m2,
    'por_asesor'            => $por_asesor,
    'reproceso'             => $reproceso,
    'horno_semanas'         => $horno_semanas,
]);