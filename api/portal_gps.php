<?php
// ============================================================
//  APEX GLASS - GPS para Portal Clientes
//  Archivo: api/portal_gps.php
//
//  A diferencia de api/gps_proxy.php (interno, ve la flota completa), este
//  endpoint SOLO expone la ubicación de la unidad asignada a una entrega EN
//  CURSO del cliente autenticado — nunca la flota completa, nunca IMEI/nombre
//  de dispositivo, y nada si el cliente no tiene un reparto en camino ahora mismo.
// ============================================================
require_once 'config.php';
require_once 'gps_lib.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['portal_cliente_id'])) {
    jsonResponse(['ok' => false, 'error' => 'No autenticado'], 401); exit;
}
$cliente_id = (int) $_SESSION['portal_cliente_id'];

$pdo = getDB();

// Entrega de este cliente en una ruta que el chofer ya inició (estado='en_ruta') y que
// todavía no se marca como entregada/no_entregada.
$stmt = $pdo->prepare("
    SELECT r.unidad
    FROM ruta_entregas re
    JOIN rutas r   ON r.id = re.ruta_id
    JOIN ordenes o ON o.id = re.orden_id
    WHERE o.cliente_id = ? AND r.estado = 'en_ruta' AND re.estado = 'pendiente'
    ORDER BY re.id DESC
    LIMIT 1
");
$stmt->execute([$cliente_id]);
$entrega = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entrega) {
    jsonResponse(['ok' => true, 'disponible' => false]);
    exit;
}

$ACCOUNT  = PROTRACK_ACCOUNT;
$PASSWORD = PROTRACK_PASSWORD;
$BASE_URL = 'https://www.protrack365.com';

if (!$ACCOUNT || !$PASSWORD) {
    jsonResponse(['ok' => true, 'disponible' => false]); exit; // no exponer detalle de config al cliente
}

$IMEI_MAP = [
    'gris'   => PROTRACK_IMEI_GRIS,
    'blanca' => PROTRACK_IMEI_BLANCA,
];

$res = gpsObtenerUbicaciones($IMEI_MAP, $ACCOUNT, $PASSWORD, $BASE_URL);
$u   = $res['unidades'][$entrega['unidad']] ?? null;

if (!$res || !$u || !$u['valido']) {
    jsonResponse(['ok' => true, 'disponible' => false]);
    exit;
}

// Solo lo mínimo para pintar el mapa del cliente — nada de imei/devicename/batería
jsonResponse([
    'ok'         => true,
    'disponible' => true,
    'lat'        => $u['lat'],
    'lng'        => $u['lng'],
    'velocidad'  => $u['velocidad'],
    'tiempo'     => $u['tiempo'],
]);
