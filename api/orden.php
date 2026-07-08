<?php
// ============================================================
//  APEX GLASS - API: Detalle de orden
//  Archivo: api/orden.php
//  M�1�7�1�7todo: GET  ?folio=R-801
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

// Acepta sesión interna del sistema O sesión del portal de clientes
if (session_status() === PHP_SESSION_NONE) session_start();
$esPortal     = !empty($_SESSION['portal_cliente_id']);
$esInterno    = !empty($_SESSION['user_id']);
if (!$esPortal && !$esInterno) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']); exit;
}
$portalClienteId = $esPortal ? (int)$_SESSION['portal_cliente_id'] : null;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

$folio = strtoupper(trim($_GET['folio'] ?? ''));
if (!$folio) jsonResponse(['error' => 'Folio requerido'], 400);

$db = getDB();

// Datos de la orden
$stmt = $db->prepare('
    SELECT o.*, c.vobo_at, c.vobo_por
    FROM ordenes o
    LEFT JOIN cotizaciones c ON c.orden_id = o.id
    WHERE o.folio = ?
');
$stmt->execute([$folio]);
$orden = $stmt->fetch();
if (!$orden) jsonResponse(['error' => 'Orden no encontrada'], 404);

// Si es portal (y NO es usuario interno), verificar que la orden pertenece al cliente de la sesión
if ($esPortal && !$esInterno) {
    $stmtCli = $db->prepare('SELECT razon_social, nombre FROM clientes WHERE id = ?');
    $stmtCli->execute([$portalClienteId]);
    $rowCli = $stmtCli->fetch();
    $razonCli = $rowCli ? ($rowCli['razon_social'] ?: $rowCli['nombre']) : '';
    if ((int)$orden['cliente_id'] !== $portalClienteId && $orden['cliente_nombre'] !== $razonCli) {
        jsonResponse(['error' => 'No autorizado'], 403);
    }
}

// Todas las piezas con sus campos de trabajos incluidos
$stmt = $db->prepare('
    SELECT 
        p.*,
        TIMESTAMPDIFF(MINUTE,
            (SELECT h.created_at FROM historial_estatus h
             WHERE h.pieza_id = p.id AND h.estatus_nuevo = "cortado"
             ORDER BY h.created_at ASC LIMIT 1),
            NOW()
        ) as minutos_desde_corte
    FROM piezas p
    WHERE p.orden_id = ?
    ORDER BY p.partida ASC, p.pieza_num ASC
');
$stmt->execute([$orden['id']]);
$piezas = $stmt->fetchAll();

// Agrupar por partida
$partidas = [];
foreach ($piezas as $p) {
    $key = $p['partida'];
    if (!isset($partidas[$key])) {
        $partidas[$key] = [
            'partida'       => $p['partida'],
            'cristal'       => $p['cristal'],
            'ancho_mm'      => $p['ancho_mm'],
            'alto_mm'       => $p['alto_mm'],
            'm2_unitario'   => $p['m2'],
            'cpb'           => $p['cpb'],
            'detalles'      => $p['detalles'],
            'resaques'      => $p['resaques'],
            'tp'            => $p['tp'],
            'ta'            => $p['ta'],
            'comentarios'   => $p['comentarios'],
            'total_piezas'  => $p['pieza_total'],
            'piezas'        => [],
            'cnt_pendiente' => 0,
            'cnt_cortado'   => 0,
            'cnt_canteado'  => 0,
            'cnt_trazo'     => 0,
            'cnt_taladro'   => 0,
            'cnt_templado'  => 0,
            'cnt_en_horno'  => 0,
            'cnt_terminado' => 0,
            'cnt_entregado' => 0,
        ];
    }

    // CR�1�70�1�71TICO: incluir tp, ta, resaques, requiere_templado en cada pieza
    // La funci�1�7�1�7n calcularAvance() del JS los necesita para determinar
    // el flujo correcto y calcular el % de avance de cada pieza
    $partidas[$key]['piezas'][] = [
        'id'                  => $p['id'],
        'pieza_num'           => $p['pieza_num'],
        'qr_code'             => $p['qr_code'],
        'estatus'             => $p['estatus'],
        'tp'                  => intval($p['tp']              ?? 0),
        'ta'                  => intval($p['ta']              ?? 0),
        'resaques'            => intval($p['resaques']        ?? 0),
        'requiere_templado'   => intval($p['requiere_templado'] ?? 1),
        'minutos_desde_corte' => $p['minutos_desde_corte'],
        'updated_at'          => $p['updated_at'],
        'es_retrabajo'        => intval($p['es_retrabajo']    ?? 0),
        'razon_retrabajo'     => $p['razon_retrabajo'] ?? null,
    ];

    $estatus = $p['estatus'];
    if (array_key_exists('cnt_' . $estatus, $partidas[$key])) {
        $partidas[$key]['cnt_' . $estatus]++;
    }
}

// Resumen general de la orden
$resumen = [
    'total'        => count($piezas),
    'pendientes'   => 0,
    'cortadas'     => 0,
    'canteadas'    => 0,
    'en_trazo'     => 0,
    'en_taladro'   => 0,
    'en_horno'     => 0,
    'templadas'    => 0,
    'terminadas'   => 0,
    'entregadas'   => 0,
    'con_trabajos' => 0,
];

foreach ($piezas as $p) {
    $map = [
        'pendiente' => 'pendientes',
        'cortado'   => 'cortadas',
        'canteado'  => 'canteadas',
        'trazo'     => 'en_trazo',
        'taladro'   => 'en_taladro',
        'templado'  => 'templadas',
        'en_horno'  => 'en_horno',
        'terminado' => 'terminadas',
        'entregado' => 'entregadas',
    ];
    if (isset($map[$p['estatus']])) $resumen[$map[$p['estatus']]]++;
    if ($p['tp'] > 0 || $p['ta'] > 0 || $p['resaques'] > 0) $resumen['con_trabajos']++;
}

jsonResponse([
    'orden'    => $orden,
    'resumen'  => $resumen,
    'partidas' => array_values($partidas),
]);