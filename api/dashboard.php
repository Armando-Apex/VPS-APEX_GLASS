<?php
// ============================================================
//  APEX GLASS - API: Dashboard
//  GET ?pagina=1&por_pagina=25
// ============================================================
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$db        = getDB();
$pagina    = max(1, (int)($_GET['pagina']    ?? 1));
$porPagina = min(50, max(10, (int)($_GET['por_pagina'] ?? 15)));
$offset    = ($pagina - 1) * $porPagina;

// Filtro por asesor si el rol es comercial
if (session_status() === PHP_SESSION_NONE) session_start();
$rolSesion    = $_SESSION['user_rol']  ?? '';
$nombreSesion = $_SESSION['user_name'] ?? '';
$filtroAsesor = '';
$paramAsesor  = [];
if ($rolSesion === 'comercial' && $nombreSesion) {
    $filtroAsesor = 'AND o.asesor LIKE ?';
    $paramAsesor  = ['%' . $nombreSesion . '%'];
}

// ── Total de órdenes activas (para paginación) ───────────────
$stmtTotal = $db->prepare("
    SELECT COUNT(DISTINCT o.id)
    FROM ordenes o
    WHERE o.estado IN ('activa','concluida')
      AND (o.fecha_cierre IS NULL OR o.fecha_cierre >= DATE_SUB(NOW(), INTERVAL 7 DAY))
      $filtroAsesor
");
$stmtTotal->execute($paramAsesor);
$total = $stmtTotal->fetchColumn();

// ── Resumen por orden — paginado ─────────────────────────────
$stmt = $db->prepare("
    SELECT
        o.id, o.folio, o.cliente_nombre, o.asesor,
        o.fecha_entrega, o.fecha_cierre, o.tipo, o.estado, o.prioridad,
        COUNT(p.id)                              as total_piezas,
        SUM(p.estatus = 'pendiente')             as cnt_pendiente,
        SUM(p.estatus = 'en_corte')              as cnt_en_corte,
        SUM(p.estatus = 'cortado')               as cnt_cortado,
        SUM(p.estatus = 'canteado')              as cnt_canteado,
        SUM(p.estatus = 'trazo')                 as cnt_trazo,
        SUM(p.estatus = 'taladro')               as cnt_taladro,
        SUM(p.estatus IN ('en_horno','templado')) as cnt_horno,
        SUM(p.estatus = 'terminado')             as cnt_terminado,
        SUM(p.estatus = 'entregado')             as cnt_entregado,
        SUM(p.estatus IN ('terminado','entregado')) as piezas_listas,
        ROUND(SUM(COALESCE(p.m2, p.ancho_mm*p.alto_mm/1000000)), 2) as total_m2
    FROM ordenes o
    LEFT JOIN piezas p ON p.orden_id = o.id
    WHERE o.estado IN ('activa','concluida')
      AND (o.fecha_cierre IS NULL OR o.fecha_cierre >= DATE_SUB(NOW(), INTERVAL 7 DAY))
      $filtroAsesor
    GROUP BY o.id
    ORDER BY o.prioridad DESC, o.fecha_entrega ASC, o.folio ASC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($paramAsesor, [$porPagina, $offset]));
$resumen = $stmt->fetchAll();

// Calcular avance_pct por peso de estatus
$PESOS = [
    'pendiente' => 0,
    'en_corte'  => 10,
    'cortado'   => 20,
    'canteado'  => 40,
    'trazo'     => 55,
    'taladro'   => 70,
    'en_horno'  => 85,
    'templado'  => 85,
    'terminado' => 100,
    'entregado' => 100,
];

foreach ($resumen as &$o) {
    $total_p = (int)$o['total_piezas'];
    if ($total_p === 0) { $o['avance_pct'] = 0; continue; }

    $suma = 0;
    $suma += (int)$o['cnt_pendiente'] * $PESOS['pendiente'];
    $suma += (int)$o['cnt_en_corte']  * $PESOS['en_corte'];
    $suma += (int)$o['cnt_cortado']   * $PESOS['cortado'];
    $suma += (int)$o['cnt_canteado']  * $PESOS['canteado'];
    $suma += (int)$o['cnt_trazo']     * $PESOS['trazo'];
    $suma += (int)$o['cnt_taladro']   * $PESOS['taladro'];
    $suma += (int)$o['cnt_horno']     * $PESOS['en_horno'];
    $suma += (int)$o['cnt_terminado'] * $PESOS['terminado'];
    $suma += (int)$o['cnt_entregado'] * $PESOS['entregado'];

    $o['avance_pct'] = round(($suma / ($total_p * 100)) * 100);
    // piezas_listas sigue siendo terminado+entregado para el badge "Listo para entregar"
    $o['piezas_listas'] = (int)$o['cnt_terminado'] + (int)$o['cnt_entregado'];
}
unset($o);

// ── Totales globales — solo órdenes activas ──────────────────
$totales = $db->query('
    SELECT
        SUM(p.estatus = "pendiente")              as pendiente,
        SUM(p.estatus IN ("cortado","en_corte"))  as en_corte,
        SUM(p.estatus = "canteado")               as canteado,
        SUM(p.estatus IN ("trazo","taladro"))      as trazo,
        SUM(p.estatus IN ("en_horno","templado"))  as en_horno,
        SUM(p.estatus = "terminado")              as terminado,
        SUM(p.estatus = "entregado")              as entregado
    FROM piezas p
    JOIN ordenes o ON o.id = p.orden_id
    WHERE o.estado = "activa"
')->fetch();

// ── Últimos movimientos — solo 25 ───────────────────────────
$movimientos = $db->query('
    SELECT
        h.estatus_nuevo, h.created_at, h.usuario_nombre,
        p.cristal, p.ancho_mm, p.alto_mm,
        p.partida, p.pieza_num, p.pieza_total,
        o.folio, o.cliente_nombre
    FROM historial_estatus h
    JOIN piezas p  ON p.id  = h.pieza_id
    JOIN ordenes o ON o.id  = p.orden_id
    ORDER BY h.created_at DESC
    LIMIT 25
')->fetchAll();

jsonResponse([
    'ordenes'      => $resumen,
    'totales'      => $totales,
    'movimientos'  => $movimientos,
    'paginacion'   => [
        'pagina'     => $pagina,
        'por_pagina' => $porPagina,
        'total'      => (int)$total,
        'paginas'    => (int)ceil($total / $porPagina),
    ],
    'ts' => date('Y-m-d H:i:s'),
]);