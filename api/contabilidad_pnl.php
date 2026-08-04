<?php
require_once 'config.php';
require_once 'permisos.php';
require_once 'helpers/pnl_datos.php';

header('Content-Type: application/json');

$user  = requireSessionApi();
$rol   = $user['rol'];
if (!tienePermiso($rol, 'ver_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}
$pdo = getDB();

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    jsonResponse(['error' => 'Rango de fechas inválido']); exit;
}

// ── Cuentas del catálogo, para poder rotular y agrupar ─────────────────────────
$cuentas = $pdo->query("SELECT * FROM cuentas_contables WHERE activo = 1 ORDER BY orden, codigo")->fetchAll(PDO::FETCH_ASSOC);
$porCodigo = [];
foreach ($cuentas as $c) { $porCodigo[$c['codigo']] = $c; }

// ── 1) Ingresos (on-the-fly desde cotizaciones, por tipo) ──────────────────────
$ingresosPorTipo = ingresosPorTipoPeriodo($pdo, $desde, $hasta);
$ingresosLineas = [];
if (isset($porCodigo['4.1'])) {
    $ingresosLineas[] = ['codigo' => '4.1', 'nombre' => $porCodigo['4.1']['nombre'], 'monto' => $ingresosPorTipo['suministro']];
}
if (isset($porCodigo['4.2'])) {
    $ingresosLineas[] = ['codigo' => '4.2', 'nombre' => $porCodigo['4.2']['nombre'], 'monto' => $ingresosPorTipo['maquila']];
}
$totalIngresos = array_sum($ingresosPorTipo);

// ── 2) Costo de ventas: 5.1 consumo real trazado (vidrio) + 5.2/5.3 desde movimientos_contables
//      (mano de obra directa y costos indirectos de planta, capturados en Nómina/Gastos Fijos/Caja Chica) ─
$costoVentasVidrio = costoVentasPeriodo($pdo, $desde, $hasta);
$cobertura         = costoVentasCobertura($pdo, $desde, $hasta);
$mermaNeta         = mermaNetaPeriodo($pdo, $desde, $hasta);
$costoLineas = [];
if (isset($porCodigo['5.1'])) {
    $costoLineas[] = ['codigo' => '5.1', 'nombre' => $porCodigo['5.1']['nombre'], 'monto' => $costoVentasVidrio];
}
if (isset($porCodigo['5.4'])) {
    $costoLineas[] = ['codigo' => '5.4', 'nombre' => $porCodigo['5.4']['nombre'], 'monto' => $mermaNeta];
}

// ── 3) Gastos/costos por cuenta (movimientos_contables + Compras mapeadas) ─────
$stmtMov = $pdo->prepare("
    SELECT c.id, c.codigo, c.nombre, c.tipo_financiero, c.cuenta_padre_id, COALESCE(SUM(m.monto), 0) AS monto
    FROM cuentas_contables c
    LEFT JOIN movimientos_contables m ON m.cuenta_id = c.id AND m.fecha_movimiento BETWEEN ? AND ?
    WHERE c.es_acumulativa = 0 AND c.activo = 1
      AND c.tipo_financiero IN ('costo_venta', 'gasto_operativo', 'financiero', 'impuesto')
      AND c.codigo NOT IN ('5.1', '5.4')
    GROUP BY c.id
    ORDER BY c.orden, c.codigo
");
$stmtMov->execute([$desde, $hasta]);
$movFilas = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

$comprasPorCuenta = gastosComprasPorCuenta($pdo, $desde, $hasta);

$gastosOperativos = [];
$financieros = [];
$impuestos = [];
$totalGastosOperativos = 0.0;
$totalFinancieros = 0.0;
$totalImpuestos = 0.0;
$totalCostoVentasPlanta = 0.0;

foreach ($movFilas as $f) {
    $monto = (float) $f['monto'] + ($comprasPorCuenta[(int) $f['id']] ?? 0.0);
    $linea = ['codigo' => $f['codigo'], 'nombre' => $f['nombre'], 'monto' => $monto];
    if ($f['tipo_financiero'] === 'costo_venta') {
        $costoLineas[] = $linea;
        $totalCostoVentasPlanta += $linea['monto'];
    } elseif ($f['tipo_financiero'] === 'gasto_operativo') {
        $gastosOperativos[] = $linea;
        $totalGastosOperativos += $linea['monto'];
    } elseif ($f['tipo_financiero'] === 'financiero') {
        $financieros[] = $linea;
        $totalFinancieros += $linea['monto'];
    } elseif ($f['tipo_financiero'] === 'impuesto') {
        $impuestos[] = $linea;
        $totalImpuestos += $linea['monto'];
    }
}

$costoVentas   = $costoVentasVidrio + $mermaNeta + $totalCostoVentasPlanta;
$utilidadBruta = $totalIngresos - $costoVentas;

$comprasSinMapear = comprasSinMapearPeriodo($pdo, $desde, $hasta);

$utilidadOperativa = $utilidadBruta - $totalGastosOperativos;
$utilidadAntesImpuestos = $utilidadOperativa - $totalFinancieros;
$utilidadNeta = $utilidadAntesImpuestos - $totalImpuestos;

jsonResponse([
    'desde' => $desde, 'hasta' => $hasta,
    'ingresos' => ['lineas' => $ingresosLineas, 'total' => $totalIngresos],
    'costo_ventas' => ['lineas' => $costoLineas, 'total' => $costoVentas, 'cobertura' => $cobertura],
    'utilidad_bruta' => $utilidadBruta,
    'gastos_operativos' => ['lineas' => $gastosOperativos, 'total' => $totalGastosOperativos],
    'utilidad_operativa' => $utilidadOperativa,
    'financieros' => ['lineas' => $financieros, 'total' => $totalFinancieros],
    'impuestos' => ['lineas' => $impuestos, 'total' => $totalImpuestos],
    'utilidad_neta' => $utilidadNeta,
    'compras_sin_mapear' => $comprasSinMapear,
]);
