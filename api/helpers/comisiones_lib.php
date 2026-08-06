<?php
/**
 * Helper de datos para Comisiones de Asesores (ver CLAUDE.md sección 12/UPD).
 * Esquema confirmado con Armando 06-ago-2026:
 * - Tramos por venta del mes, TASA ÚNICA sobre el total (no marginal/progresivo).
 * - "Venta del mes" = cotizaciones.vobo_at (mismo criterio que Ingresos del P&L,
 *   UPD-437), base cotizaciones.subtotal (neto de IVA).
 * - Yahaira: 1.5% fijo ene-oct 2026, esquema normal desde nov-2026 + mínimo
 *   mensual permanente de $450,000 (si no lo alcanza, comisión = $0 ese mes).
 * - Retrabajo (error comercial, cotizaciones.es_retrabajo=1): no cuenta como
 *   venta; penaliza 50% del subtotal de esa cotización, PERDONADO por completo
 *   si el cliente pagó (saldo_pagado) al menos 50% del total (con IVA) de esa
 *   misma cotización.
 */

const COMISION_TRAMOS = [
    ['hasta' => 749999.99,     'tasa' => 0.010],
    ['hasta' => 999999.99,     'tasa' => 0.015],
    ['hasta' => PHP_FLOAT_MAX, 'tasa' => 0.020],
];

const COMISION_ASESORES = [
    'bethy'   => ['label' => 'Bethy',   'like' => '%Bethy%'],
    'cynthia' => ['label' => 'Cynthia', 'like' => '%Cynthia%'],
    'yahaira' => ['label' => 'Yahaira', 'like' => '%Yahaira%'],
];

const YAHAIRA_TASA_INICIAL         = 0.015;
const YAHAIRA_FECHA_CAMBIO_ESQUEMA = '2026-11-01';
const YAHAIRA_MINIMO_MENSUAL       = 450000.00;

function comisionTasaTramoUnico(float $ventas): float {
    foreach (COMISION_TRAMOS as $t) {
        if ($ventas <= $t['hasta']) return $t['tasa'];
    }
    $tramos = COMISION_TRAMOS; // el último tramo cierra en PHP_FLOAT_MAX, así que
    return end($tramos)['tasa']; // esta línea es solo red de seguridad, nunca debería correr
}

// Ventas del mes de un asesor — mismo criterio que ingresosPeriodo() del P&L,
// más el filtro de asesor y la exclusión de retrabajo (no cuenta como venta).
function ventasAsesorMes(PDO $pdo, string $asesorKey, string $anioMes): float {
    if (!isset(COMISION_ASESORES[$asesorKey])) return 0.0;
    $like = COMISION_ASESORES[$asesorKey]['like'];
    $desde = $anioMes . '-01';
    $hasta = date('Y-m-t', strtotime($desde));
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(c.subtotal), 0)
        FROM ordenes o
        JOIN cotizaciones c ON c.orden_id = o.id
        WHERE o.estado IN ('activa', 'entregada')
          AND c.estatus NOT IN ('cancelada', 'rechazada')
          AND c.vobo_at IS NOT NULL
          AND c.es_retrabajo = 0
          AND c.asesor_nombre LIKE ?
          AND DATE(c.vobo_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$like, $desde, $hasta]);
    return (float) $stmt->fetchColumn();
}

// Penalizaciones de retrabajo del mes — calculadas en vivo, sin tabla propia:
// la cotización de retrabajo YA tiene todo lo necesario (subtotal, total, saldo_pagado).
function penalizacionesRetrabajoAsesorMes(PDO $pdo, string $asesorKey, string $anioMes): array {
    $detalle = [];
    $total   = 0.0;
    if (!isset(COMISION_ASESORES[$asesorKey])) return ['total' => 0.0, 'detalle' => $detalle];
    $like  = COMISION_ASESORES[$asesorKey]['like'];
    $desde = $anioMes . '-01';
    $hasta = date('Y-m-t', strtotime($desde));

    $stmt = $pdo->prepare("
        SELECT c.id, c.folio, c.cliente_nombre, c.subtotal, c.total,
               COALESCE(c.saldo_pagado, 0) AS saldo_pagado,
               o.folio AS orden_folio
        FROM cotizaciones c
        LEFT JOIN ordenes o ON o.id = c.orden_id
        WHERE c.es_retrabajo = 1
          AND c.estatus NOT IN ('cancelada', 'rechazada')
          AND c.vobo_at IS NOT NULL
          AND c.asesor_nombre LIKE ?
          AND DATE(c.vobo_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$like, $desde, $hasta]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $subtotal = (float) $r['subtotal'];
        $totalCon = (float) $r['total'];
        $pagado   = (float) $r['saldo_pagado'];
        $umbralPago = 0.5 * $totalCon;
        $perdonada  = $pagado >= $umbralPago;
        $monto      = $perdonada ? 0.0 : round(0.5 * $subtotal, 2);

        $detalle[] = [
            'cotizacion_id' => (int) $r['id'],
            'folio'         => $r['folio'],
            'orden_folio'   => $r['orden_folio'],
            'cliente'       => $r['cliente_nombre'],
            'subtotal'      => $subtotal,
            'total'         => $totalCon,
            'pagado'        => $pagado,
            'perdonada'     => $perdonada,
            'monto'         => $monto,
        ];
        $total += $monto;
    }

    return ['total' => round($total, 2), 'detalle' => $detalle];
}

// Comisión bruta del mes (sin penalizaciones) según el esquema del asesor.
function calcularComisionBrutaMes(PDO $pdo, string $asesorKey, string $anioMes): array {
    $ventas = ventasAsesorMes($pdo, $asesorKey, $anioMes);

    if ($asesorKey === 'yahaira') {
        if ($anioMes < substr(YAHAIRA_FECHA_CAMBIO_ESQUEMA, 0, 7)) {
            $tasa = YAHAIRA_TASA_INICIAL;
            return ['ventas' => $ventas, 'tasa' => $tasa, 'comision_bruta' => round($ventas * $tasa, 2), 'motivo_cero' => null];
        }
        if ($ventas < YAHAIRA_MINIMO_MENSUAL) {
            return ['ventas' => $ventas, 'tasa' => 0.0, 'comision_bruta' => 0.0, 'motivo_cero' => 'minimo_no_alcanzado'];
        }
    }

    $tasa = comisionTasaTramoUnico($ventas);
    return ['ventas' => $ventas, 'tasa' => $tasa, 'comision_bruta' => round($ventas * $tasa, 2), 'motivo_cero' => null];
}

// Cálculo completo: bruta - penalizaciones de retrabajo, topada en 0 (no se
// arrastra deuda al mes siguiente — supuesto propio, ver plan/UPD).
function calcularComisionAsesorMes(PDO $pdo, string $asesorKey, string $anioMes): array {
    $bruta = calcularComisionBrutaMes($pdo, $asesorKey, $anioMes);
    $pen   = penalizacionesRetrabajoAsesorMes($pdo, $asesorKey, $anioMes);
    $neta  = max(0.0, round($bruta['comision_bruta'] - $pen['total'], 2));

    return [
        'asesor_key'         => $asesorKey,
        'asesor_label'       => COMISION_ASESORES[$asesorKey]['label'] ?? $asesorKey,
        'ventas'             => $bruta['ventas'],
        'tasa'               => $bruta['tasa'],
        'comision_bruta'     => $bruta['comision_bruta'],
        'motivo_cero'        => $bruta['motivo_cero'],
        'penalizaciones'     => $pen['total'],
        'penalizaciones_detalle' => $pen['detalle'],
        'comision_neta'      => $neta,
    ];
}
