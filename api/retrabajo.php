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

// Costo de retrabajo por orden — mismo costeo que la cuenta 5.5 del Estado de
// Resultados, que ahora tiene 2 componentes (UPD-513):
// (1) Retrabajo de PISO: UN cargo por CADA evento de retrabajo (marcador
//     historial_estatus con estatus_nuevo='pendiente' y notas LIKE 'Retrabajo:%'),
//     ver costoRetrabajoPisoPeriodo() en api/helpers/pnl_datos.php.
// (2) Retrabajo COMERCIAL: la orden nueva creada desde una cotización marcada
//     es_retrabajo=1 (ver costoRetrabajoComercialPeriodo()) — antes de este fix
//     esas órdenes (ej. S-593) siempre salían en $0.00 aquí porque solo se
//     buscaba el marcador de piso, que nunca existe para ellas (no pasaron por
//     api/reproceso.php, nacieron ya con la pieza marcada desde la conversión
//     de la cotización). Costo = m² de la pieza × costo promedio de compra por
//     tipo/espesor en ambos casos. Excluye maquila (vidrio del cliente). A
//     diferencia del P&L, aquí NO se aplica el corte 1-ago-2026: es una vista
//     operativa que muestra el costo histórico completo de cada orden viva con
//     retrabajo, no un reporte contable por período.
$costoPromSub = "(
    SELECT l.tipo, l.espesor_mm,
           SUM(ic.cantidad_laminas * COALESCE(ic.costo_real_unitario, ic.precio_unitario))
             / SUM(ic.cantidad_laminas * l.m2) AS costo_prom_m2
    FROM inventario_compras ic
    JOIN laminas l ON l.id = ic.lamina_id
    GROUP BY l.tipo, l.espesor_mm
)";
$tipoNormSql = "(CASE
            WHEN pp.cristal REGEXP 'zafiro' THEN 'claro_zafiro'
            WHEN pp.cristal REGEXP 'claro' THEN 'claro'
            WHEN pp.cristal REGEXP 'filtra' THEN 'filtrasol'
            WHEN pp.cristal REGEXP 'bronce' THEN 'bronce'
            WHEN pp.cristal REGEXP 'tintex' THEN 'tintex'
            WHEN pp.cristal REGEXP 'satinado' THEN 'satinado'
            WHEN pp.cristal REGEXP 'espejo' THEN 'espejo'
            WHEN pp.cristal REGEXP 'evo' THEN 'evo_50'
            WHEN pp.cristal REGEXP 'cliente' THEN 'cliente_maquila'
            WHEN pp.cristal REGEXP 'laminado' THEN 'laminado'
            ELSE 'otro' END)";
$espesorNormSql = "(CASE
            WHEN pp.cristal REGEXP '12' THEN 12.00
            WHEN pp.cristal REGEXP '9' THEN 9.00
            WHEN pp.cristal REGEXP '6' THEN 6.00
            WHEN pp.cristal REGEXP '5' THEN 5.00
            ELSE NULL END)";

$costoRetrabajoPisoSub = "(
    SELECT COALESCE(ROUND(SUM(pp.m2 * cp.costo_prom_m2), 2), 0)
    FROM historial_estatus h
    JOIN piezas pp ON pp.id = h.pieza_id
    LEFT JOIN $costoPromSub cp
      ON cp.tipo = $tipoNormSql AND cp.espesor_mm = $espesorNormSql
    WHERE pp.orden_id = o.id
      AND h.estatus_nuevo = 'pendiente'
      AND h.notas LIKE 'Retrabajo:%'
      AND $tipoNormSql NOT IN ('cliente_maquila','laminado')
)";

$costoRetrabajoComercialSub = "(
    SELECT COALESCE(ROUND(SUM(pp.m2 * cp.costo_prom_m2), 2), 0)
    FROM piezas pp
    JOIN cotizaciones c2 ON c2.orden_id = pp.orden_id
    LEFT JOIN $costoPromSub cp
      ON cp.tipo = $tipoNormSql AND cp.espesor_mm = $espesorNormSql
    WHERE pp.orden_id = o.id
      AND c2.es_retrabajo = 1
      AND $tipoNormSql NOT IN ('cliente_maquila','laminado')
)";

$costoRetrabajoSub = "($costoRetrabajoPisoSub + $costoRetrabajoComercialSub)";

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
        $costoRetrabajoSub                   AS costo_retrabajo,
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