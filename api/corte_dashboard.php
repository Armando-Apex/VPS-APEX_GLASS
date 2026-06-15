<?php
// ============================================================
//  APEX GLASS - API: Dashboard Corte
//  Archivo: /produccion/api/corte_dashboard.php
//  Método: GET
//  Devuelve resumen de piezas pendientes de corte
// ============================================================

require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

requireSessionApi();

$db = getDB();

// 1. Resumen por tipo de cristal (piezas pendientes de cortar)
$porCristal = $db->query("
    SELECT p.cristal, COUNT(*) as total
    FROM piezas p
    JOIN ordenes o ON o.id = p.orden_id
    WHERE o.estado = 'activa'
      AND p.estatus = 'pendiente'
    GROUP BY p.cristal
    ORDER BY total DESC
")->fetchAll();

// 2. Pedidos urgentes — 3+ días en producción con piezas pendientes
$urgentes = $db->query("
    SELECT o.folio, o.cliente_nombre, o.fecha_entrega,
           DATEDIFF(NOW(), o.created_at) as dias_en_produccion,
           COUNT(p.id) as piezas_pendientes
    FROM ordenes o
    JOIN piezas p ON p.orden_id = o.id
    WHERE o.estado = 'activa'
      AND p.estatus = 'pendiente'
      AND DATEDIFF(NOW(), o.created_at) >= 3
    GROUP BY o.id
    ORDER BY dias_en_produccion DESC
")->fetchAll();

// 3. Pedidos pequeños — 1 a 3 piezas pendientes de corte
$pequenos = $db->query("
    SELECT o.folio, o.cliente_nombre, o.fecha_entrega,
           COUNT(p.id) as piezas_pendientes
    FROM ordenes o
    JOIN piezas p ON p.orden_id = o.id
    WHERE o.estado = 'activa'
      AND p.estatus = 'pendiente'
    GROUP BY o.id
    HAVING piezas_pendientes BETWEEN 1 AND 3
    ORDER BY o.fecha_entrega ASC
")->fetchAll();

// Total general pendientes
$totalPendiente = 0;
foreach ($porCristal as $r) $totalPendiente += $r['total'];

// 4. Órdenes con retrabajo pendiente de cortar
$retrabajos = $db->query("
    SELECT o.folio, o.cliente_nombre, o.fecha_entrega,
           COUNT(p.id) as piezas_retrabajo,
           GROUP_CONCAT(DISTINCT p.razon_retrabajo ORDER BY p.updated_at DESC SEPARATOR ', ') as razones
    FROM ordenes o
    JOIN piezas p ON p.orden_id = o.id
    WHERE o.estado = 'activa'
      AND p.es_retrabajo = 1
      AND p.estatus = 'pendiente'
    GROUP BY o.id
    ORDER BY o.fecha_entrega ASC
")->fetchAll();

// 4b. Detalle de piezas por orden de retrabajo
foreach ($retrabajos as &$ret) {
    $stmt = $db->prepare("
        SELECT p.id, p.partida, p.pieza_num, p.pieza_total,
               p.qr_code, p.cristal_corto, p.ancho_mm, p.alto_mm,
               p.razon_retrabajo
        FROM piezas p
        JOIN ordenes o ON o.id = p.orden_id
        WHERE o.folio = ?
          AND p.es_retrabajo = 1
          AND p.estatus = 'pendiente'
        ORDER BY p.partida ASC, p.pieza_num ASC
    ");
    $stmt->execute([$ret['folio']]);
    $ret['piezas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
unset($ret);

jsonResponse([
    'total_pendiente' => $totalPendiente,
    'por_cristal'     => $porCristal,
    'urgentes'        => $urgentes,
    'pequenos'        => $pequenos,
    'retrabajos'      => $retrabajos,
    'ts'              => date('Y-m-d H:i:s'),
]);