<?php
// ============================================================
//  APEX GLASS - Proxy GPS ProTrack365 (interno)
//  Archivo: api/gps_proxy.php
//  Obtiene posicion en tiempo real de las unidades — ver api/gps_lib.php
//  para el detalle del flujo (Open API oficial + fallback web).
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
require_once 'gps_lib.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireSessionApi();
if (!in_array($user['rol'], ['administracion','dir_admin','dueno','desarrollo'])) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$ACCOUNT  = PROTRACK_ACCOUNT;
$PASSWORD = PROTRACK_PASSWORD;
$BASE_URL = 'https://www.protrack365.com';

$IMEI_MAP = [
    'gris'   => PROTRACK_IMEI_GRIS,
    'blanca' => PROTRACK_IMEI_BLANCA,
];

if (!$ACCOUNT || !$PASSWORD) {
    jsonResponse(['error' => 'Credenciales ProTrack365 no configuradas']); exit;
}

// ── Obtener posicion de una o varias unidades ──
$accion = $_GET['accion'] ?? 'ubicacion';

if ($accion === 'ubicacion') {
    $unidad = $_GET['unidad'] ?? 'ambas'; // gris | blanca | ambas
    if ($unidad !== 'ambas' && !isset($IMEI_MAP[$unidad])) {
        jsonResponse(['error' => 'Unidad no reconocida']); exit;
    }

    $res = gpsObtenerUbicaciones($IMEI_MAP, $ACCOUNT, $PASSWORD, $BASE_URL);
    if ($res === null) {
        jsonResponse(['error' => 'No se pudo obtener ubicación de ProTrack365 (Open API y respaldo web fallaron)']); exit;
    }

    $unidades = $res['unidades'];
    if ($unidad !== 'ambas') {
        $unidades = isset($unidades[$unidad]) ? [$unidad => $unidades[$unidad]] : [];
    }

    jsonResponse(['ok' => true, 'unidades' => $unidades, 'fuente' => $res['fuente']]);
    exit;
}

jsonResponse(['error' => 'Accion no reconocida']);
