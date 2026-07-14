<?php
// ============================================================
//  APEX GLASS - Librería compartida GPS ProTrack365
//  Usada por api/gps_proxy.php (interno) y api/portal_gps.php (clientes).
//
//  Cache de posiciones + tokens en ARCHIVO (no en $_SESSION): la ubicación de
//  las unidades es el mismo dato para todo mundo (staff interno y clientes del
//  portal viendo su entrega en curso) — cachear por sesión de usuario forzaría
//  un login distinto contra ProTrack365 por cada pestaña/cliente abierto.
// ============================================================

define('GPS_CACHE_POS_FILE',   sys_get_temp_dir() . '/apex_gps_pos.json');
define('GPS_CACHE_TOKEN_FILE', sys_get_temp_dir() . '/apex_gps_token.json');
define('GPS_CACHE_LOCK_FILE',  sys_get_temp_dir() . '/apex_gps.lock');
define('GPS_CACHE_TTL', 12); // segundos de frescura para la posición

function _gpsLeerTokens() {
    if (!is_file(GPS_CACHE_TOKEN_FILE)) return [];
    $j = json_decode((string) @file_get_contents(GPS_CACHE_TOKEN_FILE), true);
    return is_array($j) ? $j : [];
}
function _gpsGuardarTokens($data) {
    @file_put_contents(GPS_CACHE_TOKEN_FILE, json_encode($data));
}

// ── Open API oficial ──────────────────────────────────────────────────────────
function gpsGetTokenOficial($base, $account, $password) {
    $t = _gpsLeerTokens();
    if (!empty($t['official_token']) && !empty($t['official_exp']) && time() < $t['official_exp']) {
        return $t['official_token'];
    }

    $time = time();
    $sig  = md5(md5($password) . $time);
    $url  = $base . '/api/authorization?time=' . $time . '&account=' . urlencode($account) . '&signature=' . $sig;

    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return null;

    $data = json_decode($resp, true);
    if (($data['code'] ?? -1) !== 0) return null;
    $token = $data['record']['access_token'] ?? null;
    if (!$token) return null;

    $t['official_token'] = $token;
    $t['official_exp']   = time() + 6600; // 110 min
    _gpsGuardarTokens($t);
    return $token;
}

function gpsInvalidarTokenOficial() {
    $t = _gpsLeerTokens();
    unset($t['official_token'], $t['official_exp']);
    _gpsGuardarTokens($t);
}

// ── Fallback: sesión web (mientras ProTrack365 no habilite la Open API, ver UPD-327/328) ──
function gpsGetTokenWeb($account, $password) {
    $t = _gpsLeerTokens();
    if (!empty($t['web_token']) && !empty($t['web_customerid']) && !empty($t['web_exp']) && time() < $t['web_exp']) {
        return [$t['web_token'], $t['web_customerid']];
    }

    $jar = tempnam(sys_get_temp_dir(), 'ptweb');

    // 1) GET de la home para obtener JSESSIONID
    $ch = curl_init('https://www.protrack365.com/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);

    // 2) POST login (mismo endpoint y forma que usa su web, ver LoginService)
    $time = (int) round(microtime(true) * 1000);
    $url  = 'https://www.protrack365.com/LoginService?method=login'
        . '&username=' . urlencode($account)
        . '&passwd='   . md5($password)
        . '&logintype=webcustomer&_t=' . $time . '&tzOffset=-21600';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '',
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'X-Requested-With: XMLHttpRequest',
            'Referer: https://www.protrack365.com/',
            'Origin: https://www.protrack365.com',
        ],
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($resp, true);
    $customerid = $data['customerid'] ?? null;
    if (($data['errorcode'] ?? -1) !== 0 || !$customerid) { @unlink($jar); return null; }

    // 3) GET V2/index.jsp — el servidor ya renderiza el token de sesión embebido en el HTML
    $ch = curl_init('https://www.protrack365.com/V2/index.jsp');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Referer: https://www.protrack365.com/'],
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    @unlink($jar);

    if (!preg_match('/[?&]token=([A-Za-z0-9]+)/', $html, $m)) return null;

    $t['web_token']      = $m[1];
    $t['web_customerid'] = $customerid;
    $t['web_exp']        = time() + 1200; // 20 min — se desconoce la vida real, cauteloso
    _gpsGuardarTokens($t);
    return [$m[1], $customerid];
}

function gpsInvalidarTokenWeb() {
    $t = _gpsLeerTokens();
    unset($t['web_token'], $t['web_customerid'], $t['web_exp']);
    _gpsGuardarTokens($t);
}

function gpsGetUbicacionesWeb($token, $customerid) {
    $cbName = 'apexcb' . mt_rand(1000, 9999);
    $time   = (int) round(microtime(true) * 1000);
    $url = 'https://real.gpscenter.xyz/LocationService?method=customerDeviceAndGpsone'
        . '&maptype=google&customerid=' . $customerid . '&token=' . $token
        . '&version=2&_t=' . $time . '&lang=en-us&fromweb=1&uuid=&timezone=-21600&callback=' . $cbName;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Referer: https://www.protrack365.com/'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) return null;

    // Respuesta viene envuelta en JSONP: nombreCallback({...})
    if (!preg_match('/^\s*' . preg_quote($cbName, '/') . '\((.*)\)\s*;?\s*$/s', $resp, $m)) return null;
    $data = json_decode($m[1], true);
    if (($data['errorcode'] ?? -1) !== 0) return null;
    return $data['records'] ?? [];
}

// Trae la posición de TODAS las unidades configuradas (sin filtrar) probando primero la Open API
// oficial y cayendo al método web si sigue bloqueada. El filtro por unidad lo hace quien llama.
function _gpsFetchReal($IMEI_MAP, $account, $password, $base) {
    $imeis          = array_filter(array_values($IMEI_MAP));
    $imei_to_unidad = array_flip($IMEI_MAP);
    if (empty($imeis)) return null;

    // ── Intento 1: Open API oficial ──
    $token = gpsGetTokenOficial($base, $account, $password);
    if ($token) {
        $url = $base . '/api/track?access_token=' . urlencode($token) . '&imeis=' . implode(',', $imeis);
        $ch  = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = ($code === 200) ? json_decode($resp, true) : null;

        if (($data['code'] ?? -1) !== 0) {
            // Token expirado — limpiar cache y reintentar una vez
            gpsInvalidarTokenOficial();
            $token = gpsGetTokenOficial($base, $account, $password);
            if ($token) {
                $url  = $base . '/api/track?access_token=' . urlencode($token) . '&imeis=' . implode(',', $imeis);
                $ch   = curl_init($url);
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
                $resp = curl_exec($ch);
                curl_close($ch);
                $data = json_decode($resp, true);
            }
        }

        if (($data['code'] ?? -1) === 0) {
            $resultado = [];
            foreach (($data['record'] ?? []) as $item) {
                $imei   = $item['imei'] ?? '';
                $nombre = $imei_to_unidad[$imei] ?? $imei;
                $lat    = (float)($item['lat'] ?? 0);
                $lng    = (float)($item['lng'] ?? 0);
                $resultado[$nombre] = [
                    'imei'      => $imei,
                    'unidad'    => $nombre,
                    'lat'       => $lat,
                    'lng'       => $lng,
                    'valido'    => $lat != 0 && $lng != 0,
                    'velocidad' => (int)($item['speed']   ?? 0),
                    'curso'     => (int)($item['course']  ?? 0),
                    'acc'       => (int)($item['acc']     ?? 0), // 1=encendido
                    'bateria'   => (int)($item['battery'] ?? 0),
                    'tiempo'    => $item['positionTime']  ?? $item['time'] ?? null,
                    'estado'    => ($item['acc'] ?? 0) ? 'en_movimiento' : 'detenido',
                ];
            }
            return ['unidades' => $resultado, 'fuente' => 'open_api'];
        }
    }

    // ── Intento 2 (fallback): sesión web ──
    $web = gpsGetTokenWeb($account, $password);
    if ($web) {
        list($webToken, $customerid) = $web;
        $records = gpsGetUbicacionesWeb($webToken, $customerid);
        if ($records === null) {
            gpsInvalidarTokenWeb();
        } else {
            $resultado = [];
            foreach ($records as $item) {
                $imei = $item['imei'] ?? '';
                if (!in_array($imei, $imeis, true)) continue;
                $nombre = $imei_to_unidad[$imei] ?? $imei;
                $lat = (float)($item['lat'] ?? 0);
                $lng = (float)($item['lng'] ?? 0);
                $resultado[$nombre] = [
                    'imei'      => $imei,
                    'unidad'    => $nombre,
                    'lat'       => $lat,
                    'lng'       => $lng,
                    'valido'    => $lat != 0 && $lng != 0,
                    'velocidad' => (int)($item['speed']  ?? 0),
                    'curso'     => (int)($item['course'] ?? 0),
                    'acc'       => (int)($item['accstatus'] ?? 0),
                    'bateria'   => 0, // no disponible en este método
                    'tiempo'    => $item['gpstime'] ?? null, // epoch ms
                    'estado'    => ((int)($item['speed'] ?? 0) > 0) ? 'en_movimiento' : 'detenido',
                ];
            }
            return ['unidades' => $resultado, 'fuente' => 'web_fallback'];
        }
    }

    return null;
}

// Punto de entrada principal: posición de todas las unidades, cacheada ~12s y protegida contra
// que varias pestañas/clientes abiertos al mismo tiempo disparen logins duplicados contra ProTrack.
function gpsObtenerUbicaciones($IMEI_MAP, $account, $password, $base) {
    $cached = null;
    if (is_file(GPS_CACHE_POS_FILE)) {
        $cached = json_decode((string) @file_get_contents(GPS_CACHE_POS_FILE), true);
        if ($cached && (time() - ($cached['ts'] ?? 0)) < GPS_CACHE_TTL) {
            return $cached['data'];
        }
    }

    $fp = fopen(GPS_CACHE_LOCK_FILE, 'c');
    if (!$fp) return $cached['data'] ?? null;

    if (flock($fp, LOCK_EX | LOCK_NB)) {
        // Tenemos el lock — este proceso es el que refresca de verdad
        $data = _gpsFetchReal($IMEI_MAP, $account, $password, $base);
        if ($data !== null) {
            @file_put_contents(GPS_CACHE_POS_FILE, json_encode(['ts' => time(), 'data' => $data]));
        }
        flock($fp, LOCK_UN);
        fclose($fp);
        return $data ?? ($cached['data'] ?? null);
    }

    // Otro proceso ya está refrescando — usar lo que haya en cache aunque tenga unos segundos más
    if ($cached) { fclose($fp); return $cached['data']; }

    // No hay nada todavía (primer request de todos) — esperar a que el otro termine
    flock($fp, LOCK_EX);
    flock($fp, LOCK_UN);
    fclose($fp);
    $raw = @file_get_contents(GPS_CACHE_POS_FILE);
    $data2 = json_decode((string) $raw, true);
    return $data2['data'] ?? null;
}
