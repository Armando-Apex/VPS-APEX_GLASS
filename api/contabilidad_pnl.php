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

// ── 2) Costo de ventas (consumo real trazado) ──────────────────────────────────
$costoVentas = costoVentasPeriodo($pdo, $desde, $hasta);
$cobertura   = costoVentasCobertura($pdo, $desde, $hasta);
$costoLineas = [];
if (isset($porCodigo['5.1'])) {
    $costoLineas[] = ['codigo' => '5.1', 'nombre' => $porCodigo['5.1']['nombre'], 'monto' => $costoVentas];
}

$utilidadBruta = $totalIngresos - $costoVentas;

// ── 3) Gastos operativos, financieros, impuestos (movimientos_contables + Compras mapeadas) ─
$stmtMov = $pdo->prepare("
    SELECT c.id, c.codigo, c.nombre, c.tipo_financiero, c.cuenta_padre_id, COALESCE(SUM(m.monto), 0) AS monto
    FROM cuentas_contables c
    LEFT JOIN movimientos_contables m ON m.cuenta_id = c.id AND m.fecha_movimiento BETWEEN ? AND ?
    WHERE c.es_acumulativa = 0 AND c.activo = 1
      AND c.tipo_financiero IN ('gasto_operativo', 'financiero', 'impuesto')
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

foreach ($movFilas as $f) {
    $monto = (float) $f['monto'] + ($comprasPorCuenta[(int) $f['id']] ?? 0.0);
    $linea = ['codigo' => $f['codigo'], 'nombre' => $f['nombre'], 'monto' => $monto];
    if ($f['tipo_financiero'] === 'gasto_operativo') {
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
