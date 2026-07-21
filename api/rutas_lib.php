<?php
// ============================================================
//  APEX GLASS - Helpers compartidos de Rutas de Entrega
//  Usado por api/rutas.php y api/salidas.php (arranque automático de ruta
//  al completarse el escaneo de carga, ver accion=scan_qr en api/salidas.php)
// ============================================================
require_once __DIR__ . '/wa_helper.php';
require_once __DIR__ . '/gps_lib.php';

// Avisos de WhatsApp del flujo de Rutas de Entrega (aviso masivo al iniciar la ruta + "eres el
// siguiente" al confirmarse cada entrega) — el mecanismo completo está construido pero se deja
// INACTIVO (no se llama a Meta) hasta que se redacten/aprueben las plantillas. Cambiar a true
// para activar los envíos reales.
define('RUTA_WA_AVISOS_ACTIVO', false);

// ── Helper: llamar a Google Routes API (computeRoutes) ────────
// $origen/$destino aceptan un string de dirección o un array ['lat'=>x,'lng'=>y] (posición GPS
// real del camión, ver calcularYGuardarEtas). Si no se pasa $destino, se usa $origen también
// como destino (viaje redondo — comportamiento original, usado por el optimizador de rutas).
// Regresa el array decodificado de la respuesta, o null si falló la petición HTTP.
function computeRouteGoogle($mapsKey, $origen, $intermediates, $optimizar, $destino = null) {
    if ($destino === null) $destino = $origen;
    $aPayload = function($p) {
        return is_array($p) ? ['location' => ['latLng' => ['latitude' => $p['lat'], 'longitude' => $p['lng']]]] : ['address' => $p];
    };
    $payload = [
        'origin'        => $aPayload($origen),
        'destination'   => $aPayload($destino),
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
// ruta — se guarda en ruta_entregas.eta_min.
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

    $origenPlanta = 'Avenida de la Industria 214, Parque Industrial Marfer, Santa Catarina, Nuevo León';
    $origen = $origenPlanta;

    // Si la ruta ya va en camino, usar la posición GPS real del camión como origen del cálculo
    // en vez de asumir que sigue en la planta — hallazgo 18-jul-2026: el tiempo estimado se
    // sentía inflado porque siempre partía de la planta sin importar cuántas paradas ya llevara
    // hechas. Si ProTrack365 no responde o la posición no es válida, cae al origen de planta.
    $rutaInfo = $db->prepare("SELECT unidad, estado FROM rutas WHERE id=?");
    $rutaInfo->execute([$ruta_id]);
    $rutaInfo = $rutaInfo->fetch(PDO::FETCH_ASSOC);
    if ($rutaInfo && $rutaInfo['estado'] === 'en_ruta' && PROTRACK_ACCOUNT && PROTRACK_PASSWORD) {
        $IMEI_MAP = ['gris' => PROTRACK_IMEI_GRIS, 'blanca' => PROTRACK_IMEI_BLANCA];
        $gps = gpsObtenerUbicaciones($IMEI_MAP, PROTRACK_ACCOUNT, PROTRACK_PASSWORD, 'https://www.protrack365.com');
        $pos = $gps['unidades'][$rutaInfo['unidad']] ?? null;
        if ($pos && !empty($pos['valido'])) {
            $origen = ['lat' => (float)$pos['lat'], 'lng' => (float)$pos['lng']];
        }
    }

    $intermediates = array_map(function($e) {
        $addr = implode(', ', array_filter([$e['direccion'], $e['colonia'], $e['ciudad']]));
        return ['address' => $addr ?: 'Monterrey, Nuevo León'];
    }, $entregasEta);

    $TOLERANCIA_MIN = 15;
    $data = computeRouteGoogle($MAPS_KEY, $origen, $intermediates, false, $origenPlanta);
    $legs = $data['routes'][0]['legs'] ?? null;
    if (!$legs) return $etas;

    $acumMin = 0;
    $updEta = $db->prepare("UPDATE ruta_entregas SET eta_min=? WHERE id=?");
    foreach ($entregasEta as $i => $e) {
        // El ETA de ESTA parada es solo tiempo de manejo — si ya va en camino hacia ella, no ha
        // llegado a descargar todavía, así que su propia tolerancia de descarga no debe sumarse
        // aquí. La tolerancia se suma DESPUÉS de guardar este ETA: representa el tiempo que el
        // camión se detiene a descargar en ESTA parada antes de arrancar hacia la siguiente.
        $acumMin += round((int)($legs[$i]['duration'] ?? 0) / 60);
        $updEta->execute([(int)$acumMin, $e['id']]);
        $etas[] = ['entrega_id' => (int)$e['id'], 'eta_min' => (int)$acumMin];
        $acumMin += $TOLERANCIA_MIN;
    }
    return $etas;
}

// ── Envía y registra en el inbox un WA de plantilla al cliente de una parada de ruta. Silencioso
// ante errores — no debe romper el flujo de entrega/carga si Meta falla. Ver RUTA_WA_AVISOS_ACTIVO.
function _rutaWaEnviarCliente($db, $parada, $tpl, $paramsTexto) {
    try {
        $telRaw = preg_replace('/\D/', '', $parada['telefono_alterno'] ?: $parada['telefono'] ?? '');
        if (!$telRaw || strlen($telRaw) < 10) return false;
        if (strlen($telRaw) === 10) $telRaw = '52' . $telRaw;

        $resWa = enviarMensajeWA([
            'messaging_product' => 'whatsapp',
            'to'                => $telRaw,
            'type'              => 'template',
            'template'          => [
                'name'       => $tpl,
                'language'   => ['code' => 'es_MX'],
                'components' => [['type' => 'body', 'parameters' => array_map(function($t) {
                    return ['type' => 'text', 'text' => (string)$t];
                }, $paramsTexto)]],
            ],
        ]);
        $waId = $resWa['data']['messages'][0]['id'] ?? null;
        if ($resWa['code'] !== 200 || !$waId) {
            error_log('APEX WA ruta fallo: folio=' . ($parada['folio'] ?? '') . ' tpl=' . $tpl . ' resp=' . json_encode($resWa['data'] ?? []));
            return false;
        }

        $tel10  = substr($telRaw, -10);
        $stmtCv = $db->prepare("SELECT id, cliente_id FROM whatsapp_conversaciones WHERE RIGHT(REGEXP_REPLACE(telefono,'[^0-9]',''),10)=?");
        $stmtCv->execute([$tel10]);
        $convRow = $stmtCv->fetch(PDO::FETCH_ASSOC);
        if (!$convRow) {
            $db->prepare("INSERT INTO whatsapp_conversaciones (cliente_id,telefono,ultima_actividad) VALUES (?,?,NOW())")
               ->execute([$parada['cliente_id'] ?? null, $telRaw]);
            $convId = (int)$db->lastInsertId();
        } else {
            $convId = (int)$convRow['id'];
        }
        $logTxt = '[Plantilla ' . $tpl . '] ' . ($parada['folio'] ?? '');
        $db->prepare("INSERT INTO whatsapp_mensajes (conversacion_id,direccion,contenido,tipo,wa_message_id,enviado_por) VALUES (?,'outbound',?,'texto',?,'sistema')")
           ->execute([$convId, $logTxt, $waId]);
        $db->prepare("UPDATE whatsapp_conversaciones SET ultima_actividad=NOW() WHERE id=?")->execute([$convId]);
        return true;
    } catch (Exception $e) {
        error_log('APEX WA ruta error: ' . $e->getMessage());
        return false;
    }
}

// ── Al arrancar una ruta (todas las piezas cargadas, o botón "Iniciar Ruta"): avisa a TODOS los
// clientes de la ruta de una sola vez. Primera parada = ETA en minutos (ruta_iniciada_eta_cliente,
// aún sin redactar); resto = aviso genérico "va en camino, llega hoy" (ruta_en_curso_cliente).
// Idempotente vía rutas.avisos_inicio_enviados — se llama tanto desde el arranque automático
// (api/salidas.php, scan_qr) como desde el botón manual (api/rutas.php, iniciar_ruta).
function enviarAvisosInicioRuta($db, $ruta_id) {
    $ya = $db->prepare("SELECT avisos_inicio_enviados FROM rutas WHERE id=?");
    $ya->execute([$ruta_id]);
    if ((int)$ya->fetchColumn() === 1) return;
    $db->prepare("UPDATE rutas SET avisos_inicio_enviados=1 WHERE id=?")->execute([$ruta_id]);

    if (!RUTA_WA_AVISOS_ACTIVO) return;

    $stmt = $db->prepare("
        SELECT re.id as entrega_id, re.eta_min, o.folio, o.cliente_id,
               COALESCE(cl.nombre, o.cliente_nombre) AS cliente_nombre,
               cl.telefono, cl.telefono_alterno
        FROM ruta_entregas re
        JOIN ordenes o ON o.id = re.orden_id
        LEFT JOIN clientes cl ON cl.id = o.cliente_id
        WHERE re.ruta_id = ? AND re.estado = 'pendiente'
        ORDER BY re.secuencia ASC
    ");
    $stmt->execute([$ruta_id]);
    $paradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($paradas as $i => $p) {
        $nombre = substr(strip_tags($p['cliente_nombre'] ?: 'Cliente'), 0, 60);
        $folio  = substr(strip_tags($p['folio'] ?: ''), 0, 20);
        if ($i === 0) {
            $eta = $p['eta_min'] ? (string)$p['eta_min'] : '—';
            _rutaWaEnviarCliente($db, $p, 'ruta_iniciada_eta_cliente', [$nombre, $folio, $eta]);
        } else {
            _rutaWaEnviarCliente($db, $p, 'ruta_en_curso_cliente', [$nombre, $folio]);
        }
    }
}

// ── Al confirmarse la entrega de una parada (QR post-entrega o botón manual del chofer): avisa
// a la parada que quedó de primera en la fila que ya casi es su turno. Reusa la plantilla
// existente siguiente_entrega_cliente (ya aprobada en Meta, ver UPD-319) — solo cambia CUÁNDO
// se dispara. Ver RUTA_WA_AVISOS_ACTIVO arriba.
function avisarSiguienteParada($db, $ruta_id) {
    if (!RUTA_WA_AVISOS_ACTIVO) return;

    $stmt = $db->prepare("
        SELECT re.id as entrega_id, o.folio, o.cliente_id,
               COALESCE(cl.nombre, o.cliente_nombre) AS cliente_nombre,
               cl.telefono, cl.telefono_alterno
        FROM ruta_entregas re
        JOIN ordenes o ON o.id = re.orden_id
        LEFT JOIN clientes cl ON cl.id = o.cliente_id
        WHERE re.ruta_id = ? AND re.estado = 'pendiente'
        ORDER BY re.secuencia ASC LIMIT 1
    ");
    $stmt->execute([$ruta_id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) return;

    $nombre = substr(strip_tags($p['cliente_nombre'] ?: 'Cliente'), 0, 60);
    $folio  = substr(strip_tags($p['folio'] ?: ''), 0, 20);
    _rutaWaEnviarCliente($db, $p, 'siguiente_entrega_cliente', [$nombre, $folio]);
}

// ── Marca una entrega de ruta como 'entregado' y propaga a orden/piezas — usado tanto por el
// botón manual del chofer (api/rutas.php, accion=marcar_estado) como por el escaneo del QR de
// hoja de ruta post-entrega (api/salidas.php, accion=scan_qr_ruta). Cierra la ruta si ya no
// quedan paradas pendientes; si no, recalcula el ETA y avisa a la siguiente parada.
// C-14b: entrega SOLO las piezas asignadas a esta parada (ruta_entrega_piezas), no todas las
// 'terminado' de la orden completa — antes una orden con piezas que se quedaron en planta (sin
// asignar a esta ruta) se marcaba entregada igual. La orden solo cierra cuando ya no le queda
// ninguna pieza pendiente por entregar (mismo criterio que api/rutas.php, marcar_pieza).
// Idempotente: si la parada ya estaba 'entregado', no repite nada. Todo en una transacción.
function marcarEntregaComoEntregada($db, $entrega_id, $notas = '') {
    $re = $db->prepare("SELECT orden_id, ruta_id, estado FROM ruta_entregas WHERE id=?");
    $re->execute([$entrega_id]);
    $re = $re->fetch(PDO::FETCH_ASSOC);
    if (!$re) return ['ok' => false];
    if ($re['estado'] === 'entregado') return ['ok' => true, 'orden_id' => (int)$re['orden_id'], 'ruta_id' => (int)$re['ruta_id'], 'ruta_completada' => false, 'etas' => []];

    $etas = [];
    $ruta_completada = false;
    $db->beginTransaction();
    try {
        $ts = date('Y-m-d H:i:s');
        $db->prepare("UPDATE ruta_entregas SET estado='entregado', entregado_at=?, notas_entrega=? WHERE id=?")
           ->execute([$ts, $notas, $entrega_id]);

        // Solo las piezas asignadas a ESTA parada.
        $db->prepare("UPDATE ruta_entrega_piezas SET estado='entregada' WHERE ruta_entrega_id=? AND estado='asignada'")
           ->execute([$entrega_id]);
        $db->prepare("
            UPDATE piezas p
            JOIN ruta_entrega_piezas rep ON rep.pieza_id = p.id
            SET p.estatus='entregado', p.updated_at=NOW()
            WHERE rep.ruta_entrega_id=? AND rep.estado='entregada' AND p.estatus='terminado'
        ")->execute([$entrega_id]);

        // La orden solo cierra cuando ya no le queda nada pendiente.
        $tot = $db->prepare("SELECT COUNT(*) FROM piezas WHERE orden_id=?");
        $tot->execute([$re['orden_id']]);
        $ent = $db->prepare("SELECT COUNT(*) FROM piezas WHERE orden_id=? AND estatus='entregado'");
        $ent->execute([$re['orden_id']]);
        if ((int)$tot->fetchColumn() === (int)$ent->fetchColumn()) {
            $db->prepare("UPDATE ordenes SET estado='entregada', updated_at=NOW() WHERE id=?")
               ->execute([$re['orden_id']]);
        }

        $pend2 = $db->prepare("SELECT SUM(estado='pendiente') as p FROM ruta_entregas WHERE ruta_id=?");
        $pend2->execute([$re['ruta_id']]);
        $pend2 = $pend2->fetch(PDO::FETCH_ASSOC);
        if ((int)($pend2['p'] ?? 0) === 0) {
            $db->prepare("UPDATE rutas SET estado='completada', updated_at=NOW() WHERE id=?")
               ->execute([$re['ruta_id']]);
            $ruta_completada = true;
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        return ['ok' => false];
    }

    // Fuera de la transacción: recálculo de ETA y aviso WA son best-effort (mismo criterio
    // que el resto del archivo — no deben tumbar la confirmación de entrega si fallan).
    if (!$ruta_completada) {
        $etas = calcularYGuardarEtas($db, $re['ruta_id']);
        avisarSiguienteParada($db, $re['ruta_id']);
    }

    return ['ok' => true, 'orden_id' => (int)$re['orden_id'], 'ruta_id' => (int)$re['ruta_id'], 'ruta_completada' => $ruta_completada, 'etas' => $etas];
}
