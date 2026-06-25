<?php
require_once 'config.php';
require_once 'permisos.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$user = requirePermiso('ver_dashboard');
$rol  = $user['rol'];
if (!in_array($rol, ['dir_admin','dueno','director','jefe_piso','desarrollo'])) {
    jsonResponse(['error' => 'Sin permisos'], 403);
}

$db     = getDB();
$accion = $_GET['accion'] ?? 'lista';
$desde  = $_GET['desde']  ?? date('Y-m-01');
$hasta  = $_GET['hasta']  ?? date('Y-m-d');

if ($accion === 'lista') {
    $stmt = $db->prepare("
        SELECT
            h.id, h.pieza_id, h.estatus_anterior, h.estatus_nuevo,
            h.usuario_nombre, h.notas, h.created_at,
            p.qr_code, p.partida, p.pieza_num, p.pieza_total,
            p.ancho_mm, p.alto_mm,
            o.folio, o.cliente_nombre, o.id AS orden_id
        FROM historial_estatus h
        JOIN piezas p  ON p.id  = h.pieza_id
        JOIN ordenes o ON o.id  = p.orden_id
        WHERE h.omision = 1
          AND DATE(h.created_at) BETWEEN ? AND ?
        ORDER BY h.created_at DESC
        LIMIT 500
    ");
    $stmt->execute([$desde, $hasta]);
    $omisiones = $stmt->fetchAll();

    $kpiStmt = $db->prepare("
        SELECT
            SUM(DATE(created_at) = CURDATE())                           AS hoy,
            SUM(YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1))         AS semana,
            COUNT(*)                                                     AS periodo
        FROM historial_estatus
        WHERE omision = 1
          AND DATE(created_at) BETWEEN ? AND ?
    ");
    $kpiStmt->execute([$desde, $hasta]);
    $kpi = $kpiStmt->fetch();

    $porEstStmt = $db->prepare("
        SELECT estatus_anterior AS estacion_omitida, COUNT(*) AS total
        FROM historial_estatus
        WHERE omision = 1
          AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY estatus_anterior
        ORDER BY total DESC
    ");
    $porEstStmt->execute([$desde, $hasta]);
    $porEstacion = $porEstStmt->fetchAll();

    jsonResponse([
        'ok'          => true,
        'omisiones'   => $omisiones,
        'kpi'         => $kpi,
        'por_estacion'=> $porEstacion,
    ]);
}
