<?php
/**
 * Helpers de datos para el Estado de Resultados (P&L) — Contabilidad WIP.
 * Ingreso se reconoce al entregar la orden (fecha_cierre).
 * Costo de ventas es consumo real de material, trazado desde sesiones_corte
 * (wizard de corte). Antes de 2026-07-21 no existe ese trazo — ver
 * costoVentasCobertura() para saber qué tan confiable es un rango dado.
 */

function ingresosPeriodo(PDO $pdo, string $desde, string $hasta): float {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(c.total), 0) AS ingresos
        FROM ordenes o
        JOIN cotizaciones c ON c.orden_id = o.id
        WHERE o.estado IN ('activa', 'entregada')
          AND c.estatus NOT IN ('cancelada', 'rechazada')
          AND DATE(COALESCE(o.fecha_cierre, o.updated_at)) BETWEEN ? AND ?
    ");
    $stmt->execute([$desde, $hasta]);
    return (float) $stmt->fetchColumn();
}

/**
 * Ingresos separados por tipo de cotización (suministro/maquila), para
 * repartirlos en las cuentas hoja 4.1/4.2 del catálogo en vez de un solo total.
 */
function ingresosPorTipoPeriodo(PDO $pdo, string $desde, string $hasta): array {
    $stmt = $pdo->prepare("
        SELECT c.tipo, COALESCE(SUM(c.total), 0) AS total
        FROM ordenes o
        JOIN cotizaciones c ON c.orden_id = o.id
        WHERE o.estado IN ('activa', 'entregada')
          AND c.estatus NOT IN ('cancelada', 'rechazada')
          AND DATE(COALESCE(o.fecha_cierre, o.updated_at)) BETWEEN ? AND ?
        GROUP BY c.tipo
    ");
    $stmt->execute([$desde, $hasta]);
    $out = ['suministro' => 0.0, 'maquila' => 0.0];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (isset($out[$r['tipo']])) $out[$r['tipo']] = (float) $r['total'];
    }
    return $out;
}

function costoVentasPeriodo(PDO $pdo, string $desde, string $hasta): float {
    $stmt = $pdo->prepare("
        SELECT COALESCE(ROUND(SUM(scp.m2_pieza * cp.costo_prom_m2), 2), 0) AS costo_ventas
        FROM inventario_movimientos im
        JOIN sesiones_corte sc ON sc.movimiento_id = im.id
        JOIN sesiones_corte_piezas scp ON scp.sesion_id = sc.id AND scp.incluida = 1
        JOIN piezas p ON p.id = scp.pieza_id
        JOIN ordenes o ON o.id = p.orden_id
        JOIN (
            SELECT ic.lamina_id,
                   SUM(ic.cantidad_laminas * COALESCE(ic.costo_real_unitario, ic.precio_unitario))
                     / (SUM(ic.cantidad_laminas) * l2.m2) AS costo_prom_m2
            FROM inventario_compras ic
            JOIN laminas l2 ON l2.id = ic.lamina_id
            GROUP BY ic.lamina_id
        ) cp ON cp.lamina_id = im.lamina_id
        WHERE o.estado IN ('activa', 'entregada')
          AND DATE(COALESCE(o.fecha_cierre, o.updated_at)) BETWEEN ? AND ?
    ");
    $stmt->execute([$desde, $hasta]);
    return (float) $stmt->fetchColumn();
}

/**
 * % de piezas entregadas en el rango que sí tienen costo real trazado
 * (via wizard de corte). Bajo este % el costo de ventas del rango
 * está subestimado — el margen que salga no es confiable.
 */
function costoVentasCobertura(PDO $pdo, string $desde, string $hasta): array {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT p.id) AS piezas_total,
            COUNT(DISTINCT CASE WHEN scp.id IS NOT NULL THEN p.id END) AS piezas_con_costo
        FROM piezas p
        JOIN ordenes o ON o.id = p.orden_id
        LEFT JOIN sesiones_corte_piezas scp ON scp.pieza_id = p.id AND scp.incluida = 1
        WHERE o.estado IN ('activa', 'entregada')
          AND DATE(COALESCE(o.fecha_cierre, o.updated_at)) BETWEEN ? AND ?
    ");
    $stmt->execute([$desde, $hasta]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = (int) $r['piezas_total'];
    $conCosto = (int) $r['piezas_con_costo'];
    return [
        'piezas_total'    => $total,
        'piezas_con_costo'=> $conCosto,
        'pct_cobertura'   => $total > 0 ? round($conCosto / $total * 100, 1) : 0.0,
        'confiable'       => $total > 0 && ($conCosto / $total) >= 0.8,
    ];
}
