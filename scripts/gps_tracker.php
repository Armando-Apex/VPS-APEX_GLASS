<?php
// ============================================================
//  Cron GPS — trazabilidad de rutas de entrega (ver módulo Productividad)
//  Corre cada 1-2 min via crontab, independiente de si alguien tiene
//  abierto el navegador en Logística Rutas (a diferencia de la detección
//  de llegada que corre en el navegador, ver app/modulos/logistica_rutas.php).
//
//  Por cada ruta "en_ruta" hoy:
//   - guarda la posición de su unidad en gps_posiciones (log histórico)
//   - si el camión ya está a <= RADIO_LLEGADA_M de la siguiente parada
//     pendiente, marca ruta_entregas.llegada_gps_at
//   - si esa parada ya tiene un escaneo QR de salida (orden_salida_escaneos)
//     y el camión ya se está moviendo, marca ruta_entregas.movimiento_iniciado_at
//     ("tiempo muerto" = movimiento_iniciado_at - hora del escaneo QR)
//
//  Uso: php84 gps_tracker.php   (crontab: */1 * * * *)
// ============================================================

if (php_sapi_name() !== 'cli') {
    die('Solo CLI.');
}

chdir(__DIR__ . '/../api');
require_once 'config.php';
require_once 'gps_lib.php';

const RADIO_LLEGADA_M   = 300;
const VELOCIDAD_MOVIMIENTO_KMH = 5;
// Coordenadas de la planta (misma ubicación del pin de fábrica en Logística Rutas, UPD-332)
const PLANTA_LAT = 25.693151;
const PLANTA_LNG = -100.480343;

function distMetros($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLng/2)*sin($dLng/2);
    return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
}

function geocodeDireccion($addr) {
    if (!defined('GOOGLE_MAPS_SERVER_KEY') || !GOOGLE_MAPS_SERVER_KEY) return null;
    $url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode($addr . ', México')
        . '&key=' . GOOGLE_MAPS_SERVER_KEY;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
    $resp = curl_exec($ch);
    unset($ch);
    $data = json_decode($resp, true);
    if (($data['status'] ?? '') !== 'OK' || empty($data['results'][0])) return null;
    $loc = $data['results'][0]['geometry']['location'];
    return ['lat' => $loc['lat'], 'lng' => $loc['lng']];
}

$db = getDB();

$ACCOUNT  = PROTRACK_ACCOUNT;
$PASSWORD = PROTRACK_PASSWORD;
$BASE_URL = 'https://www.protrack365.com';
$IMEI_MAP = ['gris' => PROTRACK_IMEI_GRIS, 'blanca' => PROTRACK_IMEI_BLANCA];

if (!$ACCOUNT || !$PASSWORD) {
    fwrite(STDERR, "Credenciales ProTrack365 no configuradas\n");
    exit(1);
}

$hoy = date('Y-m-d');

$stmt = $db->prepare("SELECT id, unidad FROM rutas WHERE fecha = ? AND estado = 'en_ruta'");
$stmt->execute([$hoy]);
$rutasActivas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rutasActivas) {
    echo "Sin rutas en_ruta hoy — nada que hacer.\n";
    exit(0);
}

$gps = gpsObtenerUbicaciones($IMEI_MAP, $ACCOUNT, $PASSWORD, $BASE_URL);
if (!$gps) {
    fwrite(STDERR, "No se pudo obtener posición GPS (ver api/gps_lib.php)\n");
    exit(1);
}

$unidadesLogueadas = [];

foreach ($rutasActivas as $ruta) {
    $pos = $gps['unidades'][$ruta['unidad']] ?? null;
    if (!$pos || !$pos['valido']) continue;

    // Log histórico de posición — una fila por unidad por corrida, no por ruta
    if (!isset($unidadesLogueadas[$ruta['unidad']])) {
        $db->prepare("INSERT INTO gps_posiciones (unidad, lat, lng, velocidad, capturado_at) VALUES (?,?,?,?,NOW())")
           ->execute([$ruta['unidad'], $pos['lat'], $pos['lng'], $pos['velocidad']]);
        $unidadesLogueadas[$ruta['unidad']] = true;
    }

    // Siguiente parada pendiente de esta ruta (misma lógica que el frontend: la primera
    // sin entregar, en orden de secuencia)
    $stmt = $db->prepare("
        SELECT id, orden_id, direccion, colonia, ciudad, lat, lng, llegada_gps_at, movimiento_iniciado_at
        FROM ruta_entregas
        WHERE ruta_id = ? AND estado = 'pendiente'
        ORDER BY secuencia ASC LIMIT 1
    ");
    $stmt->execute([$ruta['id']]);
    $destino = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$destino) {
        // Ya no quedan paradas pendientes — detectar regreso a planta para cerrar la ruta.
        $ru = $db->prepare("SELECT regreso_planta_at, estado FROM rutas WHERE id=?");
        $ru->execute([$ruta['id']]);
        $ru = $ru->fetch(PDO::FETCH_ASSOC);
        if ($ru && $ru['regreso_planta_at'] === null) {
            $dist = distMetros($pos['lat'], $pos['lng'], PLANTA_LAT, PLANTA_LNG);
            if ($dist <= RADIO_LLEGADA_M) {
                $db->prepare("UPDATE rutas SET regreso_planta_at=NOW(), estado='completada', updated_at=NOW() WHERE id=?")
                   ->execute([$ruta['id']]);
                echo "Ruta {$ruta['id']} ({$ruta['unidad']}): regreso a planta detectado, ruta cerrada\n";
            }
        }
        continue;
    }

    // Geocodificar y cachear si es la primera vez que se procesa esta parada
    if ($destino['lat'] === null || $destino['lng'] === null) {
        $addr = implode(', ', array_filter([$destino['direccion'], $destino['colonia'], $destino['ciudad']]));
        $geo  = $addr ? geocodeDireccion($addr) : null;
        if ($geo) {
            $db->prepare("UPDATE ruta_entregas SET lat=?, lng=? WHERE id=?")
               ->execute([$geo['lat'], $geo['lng'], $destino['id']]);
            $destino['lat'] = $geo['lat'];
            $destino['lng'] = $geo['lng'];
        }
    }

    // Llegada por GPS
    if ($destino['llegada_gps_at'] === null && $destino['lat'] !== null) {
        $dist = distMetros($pos['lat'], $pos['lng'], (float)$destino['lat'], (float)$destino['lng']);
        if ($dist <= RADIO_LLEGADA_M) {
            $db->prepare("UPDATE ruta_entregas SET llegada_gps_at = NOW() WHERE id = ?")
               ->execute([$destino['id']]);
            echo "Ruta {$ruta['id']} ({$ruta['unidad']}): llegada detectada a parada {$destino['id']}\n";
        }
    }

    // Movimiento tras escaneo QR de salida — "tiempo muerto" = movimiento_iniciado_at - escaneo
    if ($destino['movimiento_iniciado_at'] === null) {
        $stmt2 = $db->prepare("
            SELECT MAX(created_at) FROM orden_salida_escaneos
            WHERE orden_id = ? AND DATE(created_at) = ?
        ");
        $stmt2->execute([$destino['orden_id'], $hoy]);
        $scanAt = $stmt2->fetchColumn();
        if ($scanAt && $pos['velocidad'] > VELOCIDAD_MOVIMIENTO_KMH) {
            $db->prepare("UPDATE ruta_entregas SET movimiento_iniciado_at = NOW() WHERE id = ?")
               ->execute([$destino['id']]);
            echo "Ruta {$ruta['id']} ({$ruta['unidad']}): movimiento detectado tras escaneo QR de parada {$destino['id']}\n";
        }
    }
}

echo "OK — " . count($rutasActivas) . " ruta(s) revisada(s)\n";
