<?php
// Balance de Comprobación + Balance General — Fase 6.3. Se calculan 100% desde `polizas_lineas`
// (pólizas activas), nunca se capturan a mano — es la prueba de que el sistema cuadra solo.
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user = requireSessionApi();
$rol  = $user['rol'];
if (!tienePermiso($rol, 'ver_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}
$pdo = getDB();

// Regla universal de partida doble (independiente del campo `naturaleza`, que en cuentas_contables
// describe el signo dentro del Estado de Resultados, no si la cuenta es deudora o acreedora).
$DEUDORA = ['activo', 'costo_venta', 'gasto_operativo', 'financiero', 'impuesto'];

$corte = $_GET['corte'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT c.id, c.codigo, c.nombre, c.tipo_financiero, c.cuenta_padre_id, c.nivel,
           COALESCE(SUM(pl.debe), 0) AS debe, COALESCE(SUM(pl.haber), 0) AS haber
    FROM cuentas_contables c
    LEFT JOIN polizas_lineas pl ON pl.cuenta_id = c.id
    LEFT JOIN polizas p ON p.id = pl.poliza_id AND p.estado = 'activa' AND p.fecha <= ?
    WHERE c.es_acumulativa = 0
    GROUP BY c.id
    ORDER BY c.orden ASC, c.codigo ASC
");
$stmt->execute([$corte]);
$cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$comprobacion = [];
$porTipo = ['activo' => [], 'pasivo' => [], 'capital' => [], 'ingreso' => [], 'costo_venta' => [], 'gasto_operativo' => [], 'financiero' => [], 'impuesto' => []];

foreach ($cuentas as $c) {
    $debe  = (float)$c['debe'];
    $haber = (float)$c['haber'];
    $esDeudora = in_array($c['tipo_financiero'], $DEUDORA, true);
    $saldo = $esDeudora ? ($debe - $haber) : ($haber - $debe);

    if ($debe != 0 || $haber != 0) {
        $comprobacion[] = [
            'codigo' => $c['codigo'], 'nombre' => $c['nombre'], 'tipo_financiero' => $c['tipo_financiero'],
            'debe' => round($debe, 2), 'haber' => round($haber, 2), 'saldo' => round($saldo, 2),
        ];
    }
    if (isset($porTipo[$c['tipo_financiero']])) {
        $porTipo[$c['tipo_financiero']][] = ['codigo' => $c['codigo'], 'nombre' => $c['nombre'], 'saldo' => round($saldo, 2)];
    }
}

function totalSaldo(array $cuentas): float {
    $t = 0; foreach ($cuentas as $c) $t += $c['saldo']; return round($t, 2);
}

$activoTotal  = totalSaldo($porTipo['activo']);
$pasivoTotal  = totalSaldo($porTipo['pasivo']);
$capitalTotal = totalSaldo($porTipo['capital']);

$ingresos   = totalSaldo($porTipo['ingreso']);
$costos     = totalSaldo($porTipo['costo_venta']);
$gastos     = totalSaldo($porTipo['gasto_operativo']);
$financieros= totalSaldo($porTipo['financiero']);
$impuestos  = totalSaldo($porTipo['impuesto']);
$resultado  = round($ingresos - $costos - $gastos - $financieros - $impuestos, 2);

$pasivoMasCapital = round($pasivoTotal + $capitalTotal + $resultado, 2);
$cuadra = abs($activoTotal - $pasivoMasCapital) < 0.01;

jsonResponse([
    'corte' => $corte,
    'comprobacion' => $comprobacion,
    'balance' => [
        'activo' => array_values(array_filter($porTipo['activo'], fn($c) => $c['saldo'] != 0)),
        'activo_total' => $activoTotal,
        'pasivo' => array_values(array_filter($porTipo['pasivo'], fn($c) => $c['saldo'] != 0)),
        'pasivo_total' => $pasivoTotal,
        'capital' => array_values(array_filter($porTipo['capital'], fn($c) => $c['saldo'] != 0)),
        'capital_total' => $capitalTotal,
        'resultado_periodo' => $resultado,
        'pasivo_mas_capital' => $pasivoMasCapital,
        'cuadra' => $cuadra,
    ],
]);
