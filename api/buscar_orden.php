<?php
// ============================================================
//  APEX GLASS - API: Buscar orden por número
//  Archivo: api/buscar_orden.php
//  Método: GET ?num=001&estacion=corte
//  Devuelve: órdenes que coincidan + piezas filtradas por estación
// ============================================================

require_once 'config.php';
require_once 'permisos.php';
requireSessionApi();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$num      = trim($_GET['num']      ?? '');
$estacion = trim($_GET['estacion'] ?? '');

if (!$num) {
    jsonResponse(['error' => 'Número de orden requerido'], 400);
}

// Limpiar input — solo números y letras, sin guiones ni espacios extras
$num = preg_replace('/[^0-9A-Za-z\s]/', '', $num);
$num = strtoupper(trim($num));

$db = getDB();

// Buscar órdenes cuyo folio contenga el número ingresado
// Ejemplos: "001" encuentra R-001, R-001 A, R-001 B, S-001, etc.
$stmt = $db->prepare("
    SELECT id, folio, cliente_nombre, fecha_entrega, estado,
           (SELECT COUNT(*) FROM piezas WHERE orden_id = ordenes.id) as total_piezas
    FROM ordenes
    WHERE folio LIKE ?
    ORDER BY folio ASC
    LIMIT 20
");
$stmt->execute(['%-' . $num . '%']);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$ordenes) {
    jsonResponse(['error' => 'No se encontraron órdenes con ese número'], 404);
}

// Mapeo estación → estatus que le corresponde
$estacion_estatus = [
    'corte'     => ['pendiente', 'en_corte'],
    'canteado'  => ['cortado'],
    'trazo'     => ['canteado'],
    'taladro'   => ['trazo'],
    'templado'  => ['taladro', 'canteado'],
    'terminado' => ['en_horno'],
    'entrega'   => ['terminado'],
    // Roles con acceso total ven todas las piezas
    'admin'          => null,
    'jefe_piso'      => null,
    'director'       => null,
    'dir_admin'      => null,
    'comercial'      => null,
    'administracion' => null,
    'dueno'          => null,
];

$estatusFiltro = isset($estacion_estatus[$estacion]) ? $estacion_estatus[$estacion] : null;

// Para cada orden, traer sus piezas filtradas por estación
foreach ($ordenes as &$orden) {
    if ($estatusFiltro === null) {
        // Acceso total — todas las piezas
        $stmt2 = $db->prepare("
            SELECT p.id, p.partida, p.pieza_num, p.pieza_total,
                   p.cristal, p.cristal_corto, p.ancho_mm, p.alto_mm,
                   p.cpb, p.detalles, p.resaques, p.tp, p.ta,
                   p.requiere_templado, p.comentarios, p.qr_code, p.estatus
            FROM piezas p
            WHERE p.orden_id = ?
            ORDER BY p.partida ASC, p.pieza_num ASC
        ");
        $stmt2->execute([$orden['id']]);
    } else {
        // Filtrar por estatus correspondiente a la estación
        $placeholders = implode(',', array_fill(0, count($estatusFiltro), '?'));
        $stmt2 = $db->prepare("
            SELECT p.id, p.partida, p.pieza_num, p.pieza_total,
                   p.cristal, p.cristal_corto, p.ancho_mm, p.alto_mm,
                   p.cpb, p.detalles, p.resaques, p.tp, p.ta,
                   p.requiere_templado, p.comentarios, p.qr_code, p.estatus
            FROM piezas p
            WHERE p.orden_id = ? AND p.estatus IN ($placeholders)
            ORDER BY p.partida ASC, p.pieza_num ASC
        ");
        $stmt2->execute(array_merge([$orden['id']], $estatusFiltro));
    }
    $orden['piezas'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $orden['piezas_en_estacion'] = count($orden['piezas']);
}
unset($orden);

jsonResponse([
    'ordenes'    => $ordenes,
    'total'      => count($ordenes),
    'estacion'   => $estacion,
    'num_buscado'=> $num,
]);