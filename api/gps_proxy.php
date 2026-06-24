<?php
// ============================================================
//  APEX GLASS - Proxy GPS ProTrack365
//  Archivo: api/gps_proxy.php
//  Obtiene posicion en tiempo real de las unidades via Open API
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireSessionApi();
if (!in_array($user['rol'], ['administracion','dir_admin','dueno','desarrollo'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']); exit;
}

$ACCOUNT  = PROTRACK_ACCOUNT;
$PASSWORD = PROTRACK_PASSWORD;
$BASE_URL = 'https://www.protrack365.com';

$IMEI_MAP = [
    'gris'   => PROTRACK_IMEI_GRIS,
    'blanca' => PROTRACK_IMEI_BLANCA,
];

if (!$ACCOUNT || !$PASSWORD) {
    echo json_encode(['error' => 'Credenciales ProTrack365 no configuradas']); exit;
}

// ── Cache de token en sesion (valido 110 min para no acercarse al limite de 2h) ──
function getToken($base, $account, $password) {
    if (!empty($_SESSION['protrack_token']) && !empty($_SESSION['protrack_token_exp'])
        && time() < $_SESSION['protrack_token_exp']) {
        return $_SESSION['protrack_token'];
    }

    $time = time();
    $sig  = md5(md5($password) . $time);
    $url  = $base . '/api/authorization?time=' . $time . '&account=' . urlencode($account) . '&signature=' . $sig;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) return null;
    $data = json_decode($resp, true);
    if (($data['code'] ?? -1) !== 0) return null;

    $token = $data['record']['access_token'] ?? null;
    if (!$token) return null;

    $_SESSION['protrack_token']     = $token;
    $_SESSION['protrack_token_exp'] = time() + 6600; // 110 min
    return $token;
}

// ── Obtener posicion de una o varias unidades ──
$accion = $_GET['accion'] ?? 'ubicacion';

if ($accion === 'ubicacion') {
    $unidad = $_GET['unidad'] ?? 'ambas'; // gris | blanca | ambas

    if ($unidad === 'ambas') {
        $imeis = array_filter(array_values($IMEI_MAP));
    } elseif (isset($IMEI_MAP[$unidad])) {
        $imeis = array_filter([$IMEI_MAP[$unidad]]);
    } else {
        echo json_encode(['error' => 'Unidad no reconocida']); exit;
    }

    if (empty($imeis)) {
        echo json_encode(['error' => 'IMEI no configurado']); exit;
    }

    $token = getToken($BASE_URL, $ACCOUNT, $PASSWORD);
    if (!$token) {
        echo json_encode(['error' => 'No se pudo autenticar con ProTrack365']); exit;
    }

    $url = $BASE_URL . '/api/track?access_token=' . urlencode($token) . '&imeis=' . implode(',', $imeis);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        echo json_encode(['error' => 'Error al contactar ProTrack365', 'http' => $code]); exit;
    }

    $data = json_decode($resp, true);
    if (($data['code'] ?? -1) !== 0) {
        // Token expirado — limpiar cache y reintentar una vez
        unset($_SESSION['protrack_token'], $_SESSION['protrack_token_exp']);
        $token = getToken($BASE_URL, $ACCOUNT, $PASSWORD);
        if ($token) {
            $url  = $BASE_URL . '/api/track?access_token=' . urlencode($token) . '&imeis=' . implode(',', $imeis);
            $ch   = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $data = json_decode($resp, true);
        }
    }

    if (($data['code'] ?? -1) !== 0) {
        echo json_encode(['error' => 'ProTrack365: ' . ($data['message'] ?? 'Error desconocido')]); exit;
    }

    // Mapear IMEI -> nombre de unidad para respuesta
    $imei_to_unidad = array_flip($IMEI_MAP);
    $resultado = [];
    foreach (($data['record'] ?? []) as $item) {
        $imei    = $item['imei'] ?? '';
        $nombre  = $imei_to_unidad[$imei] ?? $imei;
        $lat     = (float)($item['lat'] ?? 0);
        $lng     = (float)($item['lng'] ?? 0);
        $valido  = $lat != 0 && $lng != 0;

        $resultado[$nombre] = [
            'imei'      => $imei,
            'unidad'    => $nombre,
            'lat'       => $lat,
            'lng'       => $lng,
            'valido'    => $valido,
            'velocidad' => (int)($item['speed']   ?? 0),
            'curso'     => (int)($item['course']  ?? 0),
            'acc'       => (int)($item['acc']     ?? 0), // 1=encendido
            'bateria'   => (int)($item['battery'] ?? 0),
            'tiempo'    => $item['positionTime']  ?? $item['time'] ?? null,
            'estado'    => ($item['acc'] ?? 0) ? 'en_movimiento' : 'detenido',
        ];
    }

    echo json_encode(['ok' => true, 'unidades' => $resultado]);
    exit;
}

echo json_encode(['error' => 'Accion no reconocida']);
