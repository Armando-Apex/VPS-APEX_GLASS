<?php
// ============================================================
//  APEX GLASS - API: Estaciones de producción
//  Archivo: api/estaciones.php
//  Método: GET
//  Devuelve todas las piezas de órdenes activas con su estatus,
//  agrupables por estación tanto en planta como en admin.
// ============================================================

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

$db = getDB();

// Todas las piezas de órdenes activas con datos de la orden
$piezas = $db->query('
    SELECT
        p.id,
        p.estatus,
        p.partida,
        p.pieza_num,
        p.pieza_total,
        p.cristal,
        p.ancho_mm,
        p.alto_mm,
        p.tp,
        p.ta,
        p.resaques,
        p.requiere_templado,
        o.folio,
        o.cliente_nombre,
        o.asesor,
        o.fecha_entrega
    FROM piezas p
    JOIN ordenes o ON o.id = p.orden_id
    WHERE o.estado = "activa"
      AND p.estatus != "entregado"
    ORDER BY o.fecha_entrega ASC, o.folio ASC, p.partida ASC, p.pieza_num ASC
')->fetchAll();

// Calcular avance de cada orden (ponderado) para mostrarlo en vista admin
$avanceOrdenes = $db->query('
    SELECT
        o.folio,
        p.estatus,
        p.tp,
        p.ta,
        p.resaques,
        p.requiere_templado
    FROM piezas p
    JOIN ordenes o ON o.id = p.orden_id
    WHERE o.estado = "activa"
')->fetchAll();

// Calcular % por orden
$avancePorOrden = [];
foreach ($avanceOrdenes as $p) {
    $folio = $p['folio'];
    if (!isset($avancePorOrden[$folio])) $avancePorOrden[$folio] = [];
    $avancePorOrden[$folio][] = $p;
}
$avancePct = [];
foreach ($avancePorOrden as $folio => $lista) {
    $suma = 0;
    foreach ($lista as $p) {
        $ag = ($p['tp'] > 0 || $p['ta'] > 0 || $p['resaques'] > 0);
        $re = !$p['requiere_templado'];
        $pasos = ['pendiente','cortado','canteado'];
        if ($ag) { $pasos[] = 'trazo'; $pasos[] = 'taladro'; }
        if (!$re) { $pasos[] = 'templado'; }
        $pasos[] = 'terminado';
        $est = $p['estatus'] === 'entregado' ? 'terminado' : $p['estatus'];
        $pos = array_search($est, $pasos);
        $suma += (count($pasos) - 1) > 0 ? (($pos === false ? 0 : $pos) / (count($pasos) - 1)) * 100 : 0;
    }
    $avancePct[$folio] = round($suma / count($lista));
}

// Agregar avance_pct a cada pieza
foreach ($piezas as &$p) {
    $p['avance_pct'] = $avancePct[$p['folio']] ?? 0;
}
unset($p);

// Totales por estatus
$totales = [
    'pendiente' => 0, 'cortado'  => 0, 'en_corte' => 0, 'canteado' => 0,
    'trazo'     => 0, 'taladro'  => 0, 'templado' => 0, 'en_horno' => 0,
    'terminado' => 0,
];
foreach ($piezas as $p) {
    if (isset($totales[$p['estatus']])) $totales[$p['estatus']]++;
}
// Combinar en_corte con cortado, en_horno con templado
$totales['cortado']  += $totales['en_corte']  ?? 0;
$totales['templado'] += $totales['en_horno']  ?? 0;
unset($totales['en_corte'], $totales['en_horno']);

jsonResponse([
    'piezas'  => $piezas,
    'totales' => $totales,
    'ts'      => date('Y-m-d H:i:s'),
]);