<?php
// ============================================================
//  APEX GLASS - API: Retrabajo
//  Archivo: api/retrabajo.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');

$user = requirePermiso('ver_ordenes');
$rol  = $user['rol'];
$pdo  = getDB();

// Órdenes con al menos una pieza en retrabajo
// Activas primero, luego entregadas
// dir_admin ve todo; comercial solo ve sus órdenes
$baseQuery = "
    SELECT
        o.id,
        o.folio,
        o.cliente_nombre,
        o.asesor,
        o.estado,
        o.fecha_pedido,
        o.fecha_entrega,
        o.fecha_cierre,
        COUNT(p.id)                          AS total_piezas,
        SUM(p.es_retrabajo = 1)              AS piezas_retrabajo,
        GROUP_CONCAT(DISTINCT p.razon_retrabajo
            ORDER BY p.updated_at DESC
            SEPARATOR ' | ')                 AS razones
    FROM ordenes o
    JOIN piezas p ON p.orden_id = o.id
    WHERE p.es_retrabajo = 1
      AND o.estado IN ('activa','entregada')
";

if ($rol === 'comercial') {
    // Buscar el nombre del asesor en usuarios para filtrar sus órdenes
    $stmtNombre = $pdo->prepare("SELECT nombre FROM usuarios WHERE id = ? AND rol = 'comercial' LIMIT 1");
    $stmtNombre->execute([$user['id']]);
    $rowNombre = $stmtNombre->fetch();
    $nombreAsesor = $rowNombre ? $rowNombre['nombre'] : '';

    // Filtrar órdenes donde asesor contiene el nombre del usuario
    $stmt = $pdo->prepare($baseQuery . "
      AND o.asesor LIKE ?
    GROUP BY o.id
    ORDER BY
        FIELD(o.estado, 'activa', 'entregada'),
        o.fecha_entrega ASC
    LIMIT 200
    ");
    $stmt->execute(['%' . $nombreAsesor . '%']);
} else {
    // dir_admin, director, jefe_piso — ven todo
    $stmt = $pdo->query($baseQuery . "
    GROUP BY o.id
    ORDER BY
        FIELD(o.estado, 'activa', 'entregada'),
        o.fecha_entrega ASC
    LIMIT 200
    ");
}

jsonResponse(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);