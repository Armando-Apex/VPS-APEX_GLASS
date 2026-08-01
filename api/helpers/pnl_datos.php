<?php
/**
 * Helpers de datos para el Estado de Resultados (P&L) — Contabilidad WIP.
 * Ingreso se reconoce al dar VoBo la orden (cotizaciones.vobo_at) — acuerdo
 * con Armando 01-ago-2026: "las ventas son las órdenes que tuvieron acción
 * de VoBo de parte de Lina", no la entrega física.
 *
 * Costo de ventas (acuerdo con Armando 01-ago-2026, reemplaza el método por
 * wizard de corte que solo cubría ~44% de las piezas): m² de cada pieza
 * vendida × precio promedio de compra por tipo/espesor de vidrio, sin
 * importar de qué lámina/tamaño específico salió. piezas.cristal es texto
 * libre (no hay FK a laminas) — se normaliza con REGEXP a tipo/espesor para
 * poder cruzarlo contra el costo promedio de inventario_compras. Piezas de
 * maquila (el cliente trae su propio vidrio) correctamente no tienen costo.
 * Ver costoVentasCobertura() para saber qué % de m² sí tiene precio de
 * referencia — algunos tipos/espesores raros nunca se han comprado y no se
 * pueden costear (se avisa en vez de omitirlos en silencio).
 */

// Normaliza piezas.cristal (texto libre) a un tipo de laminas.tipo.
const PNL_TIPO_NORM_SQL = "(CASE
    WHEN p.cristal REGEXP 'zafiro' THEN 'claro_zafiro'
    WHEN p.cristal REGEXP 'claro' THEN 'claro'
    WHEN p.cristal REGEXP 'filtra' THEN 'filtrasol'
    WHEN p.cristal REGEXP 'bronce' THEN 'bronce'
    WHEN p.cristal REGEXP 'tintex' THEN 'tintex'
    WHEN p.cristal REGEXP 'satinado' THEN 'satinado'
    WHEN p.cristal REGEXP 'espejo' THEN 'espejo'
    WHEN p.cristal REGEXP 'evo' THEN 'evo_50'
    WHEN p.cristal REGEXP 'cliente' THEN 'cliente_maquila'
    WHEN p.cristal REGEXP 'laminado' THEN 'laminado'
    ELSE 'otro'
END)";

// Normaliza piezas.cristal al espesor en mm (5/6/9/12), leyendo el primer número que matchea.
const PNL_ESPESOR_NORM_SQL = "(CASE
    WHEN p.cristal REGEXP '12' THEN 12.00
    WHEN p.cristal REGEXP '9' THEN 9.00
    WHEN p.cristal REGEXP '6' THEN 6.00
    WHEN p.cristal REGEXP '5' THEN 5.00
    ELSE NULL
END)";

// Tipos sin costo de compra por diseño (maquila con vidrio del cliente) — no cuentan como hueco de cobertura.
const PNL_TIPOS_SIN_COSTO = "('cliente_maquila','laminado')";

const PNL_COSTO_PROM_SUBQUERY = "(
    SELECT l.tipo, l.espesor_mm,
           SUM(ic.cantidad_laminas * COALESCE(ic.costo_real_unitario, ic.precio_unitario))
             / SUM(ic.cantidad_laminas * l.m2) AS costo_prom_m2
    FROM inventario_compras ic
    JOIN laminas l ON l.id = ic.lamina_id
    GROUP BY l.tipo, l.espesor_mm
)";

function ingresosPeriodo(PDO $pdo, string $desde, string $hasta): float {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(c.total), 0) AS ingresos
        FROM ordenes o
        JOIN cotizaciones c ON c.orden_id = o.id
        WHERE o.estado IN ('activa', 'entregada')
          AND c.estatus NOT IN ('cancelada', 'rechazada')
          AND c.vobo_at IS NOT NULL
          AND DATE(c.vobo_at) BETWEEN ? AND ?
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
          AND c.vobo_at IS NOT NULL
          AND DATE(c.vobo_at) BETWEEN ? AND ?
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
    $tipoNorm = PNL_TIPO_NORM_SQL;
    $espesorNorm = PNL_ESPESOR_NORM_SQL;
    $sinCosto = PNL_TIPOS_SIN_COSTO;
    $costoProm = PNL_COSTO_PROM_SUBQUERY;
    $stmt = $pdo->prepare("
        SELECT COALESCE(ROUND(SUM(p.m2 * cp.costo_prom_m2), 2), 0) AS costo_ventas
        FROM piezas p
        JOIN ordenes o ON o.id = p.orden_id
        JOIN cotizaciones c ON c.orden_id = o.id
        LEFT JOIN $costoProm cp ON cp.tipo = $tipoNorm AND cp.espesor_mm = $espesorNorm
        WHERE o.estado IN ('activa', 'entregada')
          AND c.estatus NOT IN ('cancelada', 'rechazada')
          AND c.vobo_at IS NOT NULL
          AND DATE(c.vobo_at) BETWEEN ? AND ?
          AND $tipoNorm NOT IN $sinCosto
    ");
    $stmt->execute([$desde, $hasta]);
    return (float) $stmt->fetchColumn();
}

/**
 * Gastos de Compras (OCs tipo 'suministro' — Mantenimiento, Herramienta,
 * Limpieza, etc.) que ya tienen regla de mapeo a una cuenta contable
 * (cuenta_mapeo_reglas, origen_tipo='oc_categoria'). Reconocido en base a
 * efectivo (oc_pagos.fecha_pago), igual que Nómina/Gastos Fijos/Caja Chica.
 * OCs tipo 'material' (vidrio/flete) se excluyen a propósito — ese costo ya
 * llega al P&L vía costoVentasPeriodo() (m² vendidos × precio promedio);
 * incluirlo aquí también duplicaría el gasto.
 * Regresa [cuenta_id => monto].
 */
function gastosComprasPorCuenta(PDO $pdo, string $desde, string $hasta): array {
    $stmt = $pdo->prepare("
        SELECT r.cuenta_id, SUM(p.monto) AS monto
        FROM oc_pagos p
        JOIN ordenes_compra oc ON oc.id = p.orden_compra_id
        JOIN cuenta_mapeo_reglas r ON r.origen_tipo = 'oc_categoria' AND r.origen_valor = oc.categoria
        WHERE oc.tipo = 'suministro'
          AND p.fecha_pago BETWEEN ? AND ?
        GROUP BY r.cuenta_id
    ");
    $stmt->execute([$desde, $hasta]);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[(int) $r['cuenta_id']] = (float) $r['monto'];
    }
    return $out;
}

/**
 * Categorías de Compras (tipo 'suministro') con pagos en el rango pero SIN
 * regla de mapeo todavía — para avisar en vez de perder el gasto en silencio.
 */
function comprasSinMapearPeriodo(PDO $pdo, string $desde, string $hasta): array {
    $stmt = $pdo->prepare("
        SELECT oc.categoria, SUM(p.monto) AS monto
        FROM oc_pagos p
        JOIN ordenes_compra oc ON oc.id = p.orden_compra_id
        LEFT JOIN cuenta_mapeo_reglas r ON r.origen_tipo = 'oc_categoria' AND r.origen_valor = oc.categoria
        WHERE oc.tipo = 'suministro'
          AND p.fecha_pago BETWEEN ? AND ?
          AND r.id IS NULL
        GROUP BY oc.categoria
    ");
    $stmt->execute([$desde, $hasta]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * % de piezas vendidas en el rango cuyo tipo/espesor de vidrio sí tiene un
 * precio de compra de referencia (inventario_compras) para poder costearlas.
 * Piezas de maquila (vidrio del cliente) no cuentan — ahí es correcto no
 * tener costo, no es un hueco. Bajo este % el costo de ventas del rango
 * está subestimado porque hay tipos de vidrio que nunca se han comprado
 * (o su texto no se pudo identificar) — el margen mostrado no es confiable.
 */
function costoVentasCobertura(PDO $pdo, string $desde, string $hasta): array {
    $tipoNorm = PNL_TIPO_NORM_SQL;
    $espesorNorm = PNL_ESPESOR_NORM_SQL;
    $sinCosto = PNL_TIPOS_SIN_COSTO;
    $costoProm = PNL_COSTO_PROM_SUBQUERY;
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS piezas_total,
            COUNT(cp.costo_prom_m2) AS piezas_con_costo
        FROM piezas p
        JOIN ordenes o ON o.id = p.orden_id
        JOIN cotizaciones c ON c.orden_id = o.id
        LEFT JOIN $costoProm cp ON cp.tipo = $tipoNorm AND cp.espesor_mm = $espesorNorm
        WHERE o.estado IN ('activa', 'entregada')
          AND c.estatus NOT IN ('cancelada', 'rechazada')
          AND c.vobo_at IS NOT NULL
          AND DATE(c.vobo_at) BETWEEN ? AND ?
          AND $tipoNorm NOT IN $sinCosto
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
