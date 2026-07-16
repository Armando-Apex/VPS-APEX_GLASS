<?php
// ============================================================
//  APEX GLASS - Helpers compartidos de Rutas de Entrega
//  Usado por api/rutas.php y api/salidas.php (arranque automático de ruta
//  al completarse el escaneo de carga, ver accion=scan_qr en api/salidas.php)
// ============================================================

// ── Helper: llamar a Google Routes API (computeRoutes) ────────
// Regresa el array decodificado de la respuesta, o null si falló la petición HTTP.
function computeRouteGoogle($mapsKey, $origen, $intermediates, $optimizar) {
    $payload = [
        'origin'        => ['address' => $origen],
        'destination'   => ['address' => $origen],
        'intermediates' => $intermediates,
        'travelMode'    => 'DRIVE',
        'routingPreference' => 'TRAFFIC_AWARE',
        'optimizeWaypointOrder' => $optimizar,
    ];
    $ch = curl_init('https://routes.googleapis.com/directions/v2:computeRoutes');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $mapsKey,
            'X-Goog-FieldMask: routes.optimizedIntermediateWaypointIndex,routes.legs.duration,routes.legs.distanceMeters',
        ],
    ]);
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);
    if ($status !== 200) return null;
    return json_decode($resp, true);
}

// ── Helper: ETA por parada (min acumulados desde ahora) sobre el orden ya definido de la
// ruta — se guarda en ruta_entregas.eta_min. No recalcula con tráfico en vivo después.
function calcularYGuardarEtas($db, $ruta_id) {
    $etas = [];
    $MAPS_KEY = defined('GOOGLE_MAPS_SERVER_KEY') && GOOGLE_MAPS_SERVER_KEY ? GOOGLE_MAPS_SERVER_KEY : (defined('GOOGLE_MAPS_KEY') ? GOOGLE_MAPS_KEY : '');
    if (!$MAPS_KEY) return $etas;

    $stmt = $db->prepare("
        SELECT re.id, re.direccion, re.colonia, re.ciudad
        FROM ruta_entregas re
        WHERE re.ruta_id = ? AND re.estado = 'pendiente'
        ORDER BY re.secuencia ASC
    ");
    $stmt->execute([$ruta_id]);
    $entregasEta = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$entregasEta) return $etas;

    $origen = 'Avenida de la Industria 214, Parque Industrial Marfer, Santa Catarina, Nuevo León';
    $intermediates = array_map(function($e) {
        $addr = implode(', ', array_filter([$e['direccion'], $e['colonia'], $e['ciudad']]));
        return ['address' => $addr ?: 'Monterrey, Nuevo León'];
    }, $entregasEta);

    $TOLERANCIA_MIN = 15;
    $data = computeRouteGoogle($MAPS_KEY, $origen, $intermediates, false);
    $legs = $data['routes'][0]['legs'] ?? null;
    if (!$legs) return $etas;

    $acumMin = 0;
    $updEta = $db->prepare("UPDATE ruta_entregas SET eta_min=? WHERE id=?");
    foreach ($entregasEta as $i => $e) {
        $acumMin += round((int)($legs[$i]['duration'] ?? 0) / 60) + $TOLERANCIA_MIN;
        $updEta->execute([(int)$acumMin, $e['id']]);
        $etas[] = ['entrega_id' => (int)$e['id'], 'eta_min' => (int)$acumMin];
    }
    return $etas;
}
