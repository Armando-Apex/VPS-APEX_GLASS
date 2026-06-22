<?php
// ============================================================
//  APEX GLASS - API: Listado de órdenes por estado
//  GET ?seccion=por_iniciar|en_proceso|listas|entregadas&pagina=1&por_pagina=25
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
requireSessionApi();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

$db        = getDB();
$seccion   = $_GET['seccion']    ?? 'todas';

// Filtro por asesor si el rol es comercial
if (session_status() === PHP_SESSION_NONE) session_start();
$rolSesion    = $_SESSION['user_rol']  ?? '';
$nombreSesion = $_SESSION['user_name'] ?? '';
$filtroAsesor = '';
$paramAsesor  = [];
if ($rolSesion === 'comercial' && $nombreSesion) {
    $filtroAsesor = "AND o.asesor LIKE ?";
    $paramAsesor  = ['%' . $nombreSesion . '%'];
}
$busqueda  = trim($_GET['busqueda'] ?? '');
$pagina    = max(1, (int)($_GET['pagina']    ?? 1));
$porPagina = $busqueda ? 9999 : min(200, max(10, (int)($_GET['por_pagina'] ?? 50)));
$offset    = $busqueda ? 0    : (int)(($pagina - 1) * $porPagina);
$porPagina = (int)$porPagina;

// Filtro busqueda en BD
$filtroBusqueda = '';
$paramBusqueda  = [];
if ($busqueda) {
    $filtroBusqueda = "AND (o.folio LIKE ? OR o.cliente_nombre LIKE ?)";
    $paramBusqueda  = ['%'.$busqueda.'%', '%'.$busqueda.'%'];
}

// ── Helper: contar total para paginación ─────────────────────
function contarOrdenes(PDO $db, string $where): int {
    return (int)$db->query("SELECT COUNT(DISTINCT o.id) FROM ordenes o JOIN piezas p ON p.orden_id=o.id $where")->fetchColumn();
}

// ── 1. POR INICIAR ───────────────────────────────────────────
$total_pi = contarOrdenes($db, "WHERE p.estatus='pendiente' AND o.estado='activa'");
// Obtener órdenes paginadas
$stmt = $db->prepare("
    SELECT DISTINCT o.folio, o.cliente_nombre, o.asesor, o.fecha_entrega, o.prioridad
    FROM piezas p
    JOIN ordenes o ON o.id = p.orden_id
    WHERE p.estatus = 'pendiente' AND o.estado = 'activa'
      $filtroAsesor $filtroBusqueda
    ORDER BY o.prioridad DESC, o.fecha_entrega ASC, o.folio ASC
    LIMIT ? OFFSET ?
");
$stmt->execute(array_merge($paramAsesor, $paramBusqueda, [$porPagina, $offset]));
$folios_pi = $stmt->fetchAll();

// Para cada orden, obtener sus partidas pendientes
$por_iniciar = [];
foreach ($folios_pi as $ord) {
    $stmt_p = $db->prepare("
        SELECT p.partida, p.cristal, p.ancho_mm, p.alto_mm, COUNT(*) AS piezas_pendientes
        FROM piezas p
        JOIN ordenes o ON o.id = p.orden_id
        WHERE p.estatus = 'pendiente' AND o.folio = ?
        GROUP BY p.partida, p.cristal, p.ancho_mm, p.alto_mm
        ORDER BY p.partida ASC
    ");
    $stmt_p->execute([$ord['folio']]);
    $partidas = $stmt_p->fetchAll();
    $por_iniciar[] = array_merge($ord, [
        'total_pendientes' => array_sum(array_column($partidas, 'piezas_pendientes')),
        'partidas' => $partidas,
    ]);
}

// ── 2. EN PROCESO ────────────────────────────────────────────
$total_ep = (int)$db->query("
    SELECT COUNT(DISTINCT o.id) FROM ordenes o
    JOIN piezas p ON p.orden_id = o.id
    WHERE o.estado = 'activa'
    HAVING SUM(p.estatus != 'pendiente') > 0
       AND SUM(p.estatus IN ('terminado','entregado')) < COUNT(*)
")->fetchColumn();

$stmt2 = $db->prepare("
    SELECT o.folio, o.cliente_nombre, o.asesor, o.fecha_entrega, o.prioridad,
        COUNT(*)                              AS total_piezas,
        SUM(p.estatus = 'pendiente')              AS pendientes,
        SUM(p.estatus IN ('en_corte','cortado'))  AS en_corte,
        SUM(p.estatus = 'canteado')               AS canteadas,
        SUM(p.estatus IN ('trazo','taladro'))      AS trazo_tal,
        SUM(p.estatus IN ('en_horno','templado'))  AS en_horno,
        SUM(p.estatus = 'terminado')              AS terminadas,
        SUM(p.estatus = 'entregado')              AS entregadas,
        ROUND((
            SUM(p.estatus = 'pendiente')             * 0  +
            SUM(p.estatus = 'en_corte')              * 10 +
            SUM(p.estatus = 'cortado')               * 20 +
            SUM(p.estatus = 'canteado')              * 40 +
            SUM(p.estatus = 'trazo')                 * 55 +
            SUM(p.estatus = 'taladro')               * 70 +
            SUM(p.estatus IN ('en_horno','templado')) * 85 +
            SUM(p.estatus = 'terminado')             * 100 +
            SUM(p.estatus = 'entregado')             * 100
        ) / COUNT(*)) AS avance_pct
    FROM ordenes o
    JOIN piezas p ON p.orden_id = o.id
    WHERE o.estado = 'activa'
      $filtroAsesor $filtroBusqueda
    GROUP BY o.folio, o.cliente_nombre, o.asesor, o.fecha_entrega, o.prioridad
    HAVING SUM(p.estatus != 'pendiente') > 0
       AND SUM(p.estatus IN ('terminado','entregado')) < COUNT(*)
    ORDER BY o.prioridad DESC, o.fecha_entrega ASC, o.folio ASC
    LIMIT ? OFFSET ?
");
$stmt2->execute(array_merge($paramAsesor, $paramBusqueda, [$porPagina, $offset]));
$en_proceso = $stmt2->fetchAll();

// ── 3. LISTAS PARA ENTREGAR ──────────────────────────────────
$stmt3 = $db->prepare("
    SELECT o.folio, o.cliente_nombre, o.asesor, o.fecha_entrega, o.prioridad,
        COUNT(*) AS total_piezas,
        SUM(p.estatus = 'terminado') AS terminadas,
        SUM(p.estatus = 'entregado') AS entregadas
    FROM ordenes o
    JOIN piezas p ON p.orden_id = o.id
    WHERE o.estado = 'activa'
      $filtroAsesor $filtroBusqueda
    GROUP BY o.folio, o.cliente_nombre, o.asesor, o.fecha_entrega, o.prioridad
    HAVING terminadas > 0
       AND (terminadas + entregadas) = total_piezas
       AND entregadas < total_piezas
    ORDER BY o.prioridad DESC, o.fecha_entrega ASC
    LIMIT ? OFFSET ?
");
$stmt3->execute(array_merge($paramAsesor, $paramBusqueda, [$porPagina, $offset]));
$listas = $stmt3->fetchAll();

// ── 4. ENTREGADAS ────────────────────────────────────────────
$stmt4 = $db->prepare("
    SELECT o.folio, o.cliente_nombre, o.asesor,
        o.fecha_entrega, o.estado,
        COUNT(*) AS total_piezas,
        SUM(p.estatus = 'entregado') AS piezas_entregadas,
        MAX(h.created_at) AS ultima_entrega,
        MIN(h.created_at) AS fecha_cierre
    FROM ordenes o
    JOIN piezas p ON p.orden_id = o.id
    LEFT JOIN historial_estatus h ON h.pieza_id = p.id AND h.estatus_nuevo = 'entregado'
    WHERE o.estado IN ('activa','entregada')
      AND p.estatus = 'entregado'
      $filtroAsesor $filtroBusqueda
    GROUP BY o.folio, o.cliente_nombre, o.asesor,
             o.fecha_entrega, o.estado
    ORDER BY ultima_entrega DESC
    LIMIT ? OFFSET ?
");
$stmt4->execute(array_merge($paramAsesor, $paramBusqueda, [$porPagina, $offset]));
$entregadas = $stmt4->fetchAll();

jsonResponse([
    'por_iniciar' => $por_iniciar,
    'en_proceso'  => $en_proceso,
    'listas'      => $listas,
    'entregadas'  => $entregadas,
    'paginacion'  => [
        'pagina'     => $pagina,
        'por_pagina' => $porPagina,
        'totales'    => [
            'por_iniciar' => $total_pi,
            'en_proceso'  => $total_ep,
        ],
    ],
]);