<?php

// ============================================================

//  APEX GLASS - API: Productividad por estación v4

//  Archivo: api/productividad.php

//  Método: GET ?vista=hora|dia|semana|mes

//

//  FIX v4: created_at está en hora Monterrey (no UTC).

//          Sin conversiones — se compara directo.

// ============================================================



require_once 'config.php';

require_once 'permisos.php';



header('Content-Type: application/json; charset=utf-8');

header('Access-Control-Allow-Origin: https://apex.glass');



requireSessionApi();



$db  = getDB();

$TZ  = new DateTimeZone('America/Monterrey');

$now = new DateTime('now', $TZ);



define('MUERTO_MIN', 10);



function esTurnoNormal(DateTime $dt): bool {

    $dow = (int)$dt->format('N');

    $hm  = $dt->format('H:i');

    if ($dow === 7) return false;

    if ($dow === 6) return ($hm >= '08:30' && $hm < '13:00');

    if ($hm >= '13:00' && $hm < '13:30') return false;

    return ($hm >= '08:30' && $hm < '17:00');

}



function horasProductivas(DateTime $desde, DateTime $hasta): float {

    $mins = 0;

    $cur  = clone $desde;

    while ($cur < $hasta) {

        if (esTurnoNormal($cur)) $mins++;

        $cur->modify('+1 minute');

    }

    return round($mins / 60, 2);

}



// created_at en BD ya está en hora local — sin conversiones

function statusToEst(string $s): ?string {
    static $m = ['cortado'=>'corte','en_corte'=>'corte','canteado'=>'canteado',
                  'trazo'=>'trazo','taladro'=>'taladro','templado'=>'horno','en_horno'=>'horno'];
    return $m[$s] ?? null;
}

// 1 query para las 5 estaciones en lugar de 5 queries separadas
function metricasFranjaAll(PDO $db, string $desde, string $hasta, bool $soloExtra = false): array {

    $filtroExtra = $soloExtra
        ? " AND (TIME(h.created_at) < '08:30' OR (DAYOFWEEK(h.created_at) BETWEEN 2 AND 6 AND TIME(h.created_at) >= '17:00') OR (DAYOFWEEK(h.created_at) = 7 AND TIME(h.created_at) >= '13:00') OR DAYOFWEEK(h.created_at) = 1)"
        : "";

    $stmt = $db->prepare("
        SELECT h.pieza_id, h.created_at, h.estatus_nuevo,
            COALESCE(p.m2, (p.ancho_mm * p.alto_mm / 1000000)) AS m2,
            ((p.ancho_mm + p.alto_mm) * 2 / 1000)              AS metros_lin,
            p.cristal
        FROM historial_estatus h
        LEFT JOIN piezas p ON p.id = h.pieza_id
        WHERE h.estatus_nuevo IN ('cortado','en_corte','canteado','trazo','taladro','templado','en_horno')
          AND h.created_at BETWEEN ? AND ?
          $filtroExtra
        ORDER BY h.estatus_nuevo, h.created_at ASC
    ");
    $stmt->execute([$desde, $hasta]);
    $rows = $stmt->fetchAll();

    $ests = ['corte','canteado','trazo','taladro','horno'];
    $buckets = [];
    foreach ($ests as $e) $buckets[$e] = [];

    // A-5: un retrabajo (api/reproceso.php regresa la pieza a 'pendiente' y se
    // vuelve a escanear) generaba OTRO renglón por estación y duplicaba los
    // m²/ml "producidos". Solo cuenta el primer pase de cada pieza por estación
    // dentro de la ventana (el ORDER BY created_at garantiza que el primero es
    // el pase original, no el retrabajo).
    $vistos = [];
    foreach ($rows as $r) {
        $est = statusToEst($r['estatus_nuevo']);
        if (!$est) continue;
        $llave = (int)$r['pieza_id'] . '|' . $est;
        if (isset($vistos[$llave])) continue;
        $vistos[$llave] = true;
        if ($est === 'corte' || $est === 'horno') $v = floatval($r['m2']);
        elseif ($est === 'canteado')               $v = floatval($r['metros_lin']);
        else                                        $v = 1.0;
        $buckets[$est][] = ['created_at' => $r['created_at'], 'v' => $v, 'cristal' => $r['cristal'] ?? ''];
    }

    $result = [];
    foreach ($ests as $est) {
        $total = 0.0; $conteo = 0; $deltas = []; $muertos = 0; $prevDt = null; $porCristal = [];
        foreach ($buckets[$est] as $r) {
            $val = $r['v'];
            $total += $val; $conteo++;
            $curDt = new DateTime($r['created_at'], $GLOBALS['TZ']);
            if ($est === 'horno' && $r['cristal'] !== '') {
                $porCristal[$r['cristal']] = ($porCristal[$r['cristal']] ?? 0) + $val;
            }
            if ($prevDt !== null && $prevDt->format('Y-m-d') === $curDt->format('Y-m-d')) {
                // A-5: solo deltas dentro del mismo día/turno — antes el hueco
                // entre el último escaneo de hoy y el primero de mañana (~900
                // min de horas no laborales) se sumaba como "tiempo muerto".
                // El cruce de comida 13:00–13:30 ya se excluye con $esCom.
                $delta = ($curDt->getTimestamp() - $prevDt->getTimestamp()) / 60;
                $esCom = ($prevDt->format('H:i') <= '13:00' && $curDt->format('H:i') >= '13:30'
                          && (int)$curDt->format('N') <= 5);
                if (!$esCom) { $deltas[] = $delta; if ($delta >= MUERTO_MIN) $muertos++; }
            }
            $prevDt = $curDt;
        }
        $promDelta = count($deltas) > 0 ? round(array_sum($deltas) / count($deltas), 1) : null;
        arsort($porCristal);
        $cristalFmt = [];
        foreach ($porCristal as $c => $v) $cristalFmt[] = ['cristal' => $c, 'm2' => round($v, 2)];
        $result[$est] = ['total' => round($total, 2), 'conteo' => $conteo,
                         'prom_delta_min' => $promDelta, 'tiempos_muertos' => $muertos,
                         'por_cristal' => $cristalFmt];
    }
    return $result;
}

// Compatibilidad: llamadas individuales a metricasFranja() siguen funcionando
function metricasFranja(PDO $db, string $est, string $desde, string $hasta, bool $soloExtra = false): array {
    return metricasFranjaAll($db, $desde, $hasta, $soloExtra)[$est];
}

function metricasPeriodo(PDO $db, string $desde, string $hasta): array {
    return metricasFranjaAll($db, $desde, $hasta);
}



$vista = $_GET['vista'] ?? 'hora';



// ============================================================

//  VISTA: HORA

// ============================================================

if ($vista === 'hora') {

    $fecha = $now->format('Y-m-d');

    $dow   = (int)$now->format('N');

    $ests  = ['corte','canteado','trazo','taladro','horno'];



    $bloques = [];

    if ($dow >= 1 && $dow <= 5) {

        $bloques = [['08:30','13:00'],['13:30','17:00']];

    } elseif ($dow === 6) {

        $bloques = [['08:30','13:00']];

    }



    $franjas = [];

    foreach ($bloques as [$ini, $fin]) {

        $cur = new DateTime("$fecha $ini", $TZ);

        $end = new DateTime("$fecha $fin", $TZ);

        while ($cur < $end && $cur <= $now) {

            $next = clone $cur; $next->modify('+1 hour');

            if ($next > $end) $next = clone $end;

            $hasta = $next > $now ? clone $now : clone $next;

            $franjas[] = [

                'label'  => $cur->format('H:i') . '–' . $next->format('H:i'),

                'tipo'   => 'normal',

                'desde'  => $cur->format('Y-m-d H:i:s'),

                'hasta'  => $hasta->format('Y-m-d H:i:s'),

            ];

            $cur = $next;

        }

    }



    // Detectar horas extra — created_at ya en hora local, comparar directo

    $stmt = $db->prepare("

        SELECT COUNT(*) FROM historial_estatus

        WHERE DATE(created_at) = ?

          AND (

            TIME(created_at) < '08:30'

            OR (DAYOFWEEK(created_at) BETWEEN 2 AND 6 AND TIME(created_at) >= '17:00')

            OR (DAYOFWEEK(created_at) = 7 AND TIME(created_at) >= '13:00')

            OR DAYOFWEEK(created_at) = 1

          )

    ");

    $stmt->execute([$fecha]);

    $hayExtra = (int)$stmt->fetchColumn() > 0;



    if ($hayExtra) {

        $franjas[] = [

            'label'  => 'Horas extra',

            'tipo'   => 'extra',

            'desde'  => $fecha . ' 00:00:00',

            'hasta'  => $now->format('Y-m-d H:i:s'),

        ];

    }



    $resultado = [];

    foreach ($franjas as $f) {

        $datos = metricasFranjaAll($db, $f['desde'], $f['hasta'], $f['tipo'] === 'extra');

        $resultado[] = array_merge($f, ['datos' => $datos]);

    }



    $pico = []; $baja = [];

    foreach ($ests as $est) {

        $maxV = -1; $minV = PHP_INT_MAX;

        $iPico = null; $iBaja = null;

        foreach ($resultado as $i => $f) {

            if ($f['tipo'] !== 'normal') continue;

            $v = $f['datos'][$est]['total'];

            if ($v > $maxV) { $maxV = $v; $iPico = $i; }

            if ($v < $minV) { $minV = $v; $iBaja = $i; }

        }

        $pico[$est] = $iPico;

        $baja[$est] = ($iBaja !== $iPico) ? $iBaja : null;

    }



    jsonResponse([

        'vista'     => 'hora',

        'fecha'     => $fecha,

        'hay_extra' => $hayExtra,

        'franjas'   => $resultado,

        'pico'      => $pico,

        'baja'      => $baja,

    ]);

}



// ============================================================

//  VISTA: DÍA

// ============================================================

if ($vista === 'dia') {

    $dias = [];

    $cur  = clone $now;

    $n    = 0;



    while ($n < 4) {

        if ((int)$cur->format('N') === 7) { $cur->modify('-1 day'); continue; }

        $fecha = $cur->format('Y-m-d');

        $desde = $fecha . ' 00:00:00';

        $hasta = $n === 0 ? $now->format('Y-m-d H:i:s') : $fecha . ' 23:59:59';



        $hrsP  = horasProductivas(new DateTime($desde, $TZ), new DateTime($hasta, $TZ));

        $datos = metricasPeriodo($db, $desde, $hasta);

        foreach ($datos as &$d) {

            $d['tasa_hr'] = $hrsP > 0 ? round($d['total'] / $hrsP, 2) : 0;

        } unset($d);



        $dias[] = [

            'label'    => $n === 0 ? 'Hoy' : $cur->format('D d/m'),

            'fecha'    => $fecha,

            'es_hoy'   => $n === 0,

            'hrs_prod' => $hrsP,

            'datos'    => $datos,

        ];

        $n++; $cur->modify('-1 day');

    }

    jsonResponse(['vista' => 'dia', 'dias' => $dias]);

}



// ============================================================

//  VISTA: SEMANA

// ============================================================

if ($vista === 'semana') {

    $semanas = [];

    for ($s = 0; $s < 2; $s++) {

        $lunes = clone $now;

        $lunes->modify('-' . $s . ' week');

        $dow = (int)$lunes->format('N');

        $lunes->modify('-' . ($dow-1) . ' days')->setTime(0,0,0);

        $sab = clone $lunes; $sab->modify('+5 days')->setTime(23,59,59);

        $hasta = ($s === 0 && $sab > $now) ? clone $now : clone $sab;



        $hrsP  = horasProductivas($lunes, $hasta);

        $datos = metricasPeriodo($db, $lunes->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s'));

        foreach ($datos as &$d) { $d['tasa_hr'] = $hrsP > 0 ? round($d['total']/$hrsP,2) : 0; } unset($d);



        $semanas[] = [

            'label'    => $s === 0 ? 'Esta semana' : 'Semana anterior',

            'desde'    => $lunes->format('d/m'),

            'hasta'    => $hasta->format('d/m'),

            'hrs_prod' => $hrsP,

            'datos'    => $datos,

        ];

    }

    jsonResponse(['vista' => 'semana', 'semanas' => $semanas]);

}



// ============================================================

//  VISTA: MES

// ============================================================

if ($vista === 'mes') {

    $meses = [];

    for ($m = 0; $m < 2; $m++) {

        $ref = clone $now;

        $ref->modify($m === 0 ? 'first day of this month' : 'first day of last month');

        $ini = clone $ref; $ini->setTime(0,0,0);

        $fin = clone $ref; $fin->modify('last day of this month')->setTime(23,59,59);

        $hasta = ($m === 0 && $fin > $now) ? clone $now : clone $fin;



        $hrsP  = horasProductivas($ini, $hasta);

        $datos = metricasPeriodo($db, $ini->format('Y-m-d H:i:s'), $hasta->format('Y-m-d H:i:s'));

        foreach ($datos as &$d) { $d['tasa_hr'] = $hrsP > 0 ? round($d['total']/$hrsP,2) : 0; } unset($d);



        $meses[] = [

            'label'    => $m === 0 ? 'Este mes' : 'Mes anterior',

            'mes'      => $ini->format('F Y'),

            'hrs_prod' => $hrsP,

            'datos'    => $datos,

        ];

    }

    jsonResponse(['vista' => 'mes', 'meses' => $meses]);

}



// ============================================================
//  VISTA: DETALLE HORAS EXTRA
// ============================================================
if ($vista === 'detalle_extra') {
    $fecha = $_GET['fecha'] ?? $now->format('Y-m-d');

    $stmt = $db->prepare("
        SELECT
            h.created_at,
            h.estatus_nuevo,
            h.usuario_nombre,
            o.folio,
            o.cliente_nombre,
            p.partida,
            p.pieza_num,
            p.ancho_mm,
            p.alto_mm,
            p.cristal
        FROM historial_estatus h
        JOIN piezas p ON p.id = h.pieza_id
        JOIN ordenes o ON o.id = p.orden_id
        WHERE DATE(h.created_at) = ?
          AND h.estatus_nuevo IN ('cortado','en_corte','canteado','trazo','taladro','templado','en_horno','terminado','entregado')
          AND (
            TIME(h.created_at) < '08:30'
            OR (DAYOFWEEK(h.created_at) BETWEEN 2 AND 6 AND TIME(h.created_at) >= '17:00')
            OR (DAYOFWEEK(h.created_at) = 7 AND TIME(h.created_at) >= '13:00')
            OR DAYOFWEEK(h.created_at) = 1
          )
        ORDER BY h.created_at ASC
    ");
    $stmt->execute([$fecha]);
    $piezas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [
        'cortado'  => 'Corte',   'canteado' => 'Canteado',
        'trazo'    => 'Trazo',   'taladro'  => 'Taladro',
        'templado' => 'Horno',   'terminado'=> 'Terminado',
        'entregado'=> 'Entregado',
    ];

    $resultado = array_map(function($p) use ($labels) {
        return [
            'hora'     => (new DateTime($p['created_at']))->format('H:i'),
            'estacion' => $labels[$p['estatus_nuevo']] ?? $p['estatus_nuevo'],
            'folio'    => $p['folio'],
            'cliente'  => $p['cliente_nombre'],
            'partida'  => $p['partida'],
            'pieza'    => $p['pieza_num'],
            'medidas'  => $p['ancho_mm'] . 'x' . $p['alto_mm'] . ' mm',
            'cristal'  => $p['cristal'],
            'operador' => $p['usuario_nombre'],
        ];
    }, $piezas);

    jsonResponse(['detalle' => $resultado, 'fecha' => $fecha, 'total' => count($resultado)]);
}

// ── Trazabilidad de rutas de entrega: Orden, chofer, salida (QR), tiempo muerto, llegada (GPS) ──
if ($vista === 'trazabilidad_rutas') {
    $fecha = $_GET['fecha'] ?? $now->format('Y-m-d');

    $stmt = $db->prepare("
        SELECT
            o.folio, o.cliente_nombre,
            r.id AS ruta_id, r.unidad, r.chofer, r.estado AS ruta_estado,
            re.id AS entrega_id, re.secuencia,
            re.movimiento_iniciado_at, re.llegada_gps_at, re.entregado_at,
            (SELECT MAX(created_at) FROM orden_salida_escaneos
              WHERE orden_id = re.orden_id AND DATE(created_at) = r.fecha) AS salida_qr_at
        FROM ruta_entregas re
        JOIN rutas r   ON r.id = re.ruta_id
        JOIN ordenes o ON o.id = re.orden_id
        WHERE r.fecha = ?
        ORDER BY r.unidad ASC, re.secuencia ASC
    ");
    $stmt->execute([$fecha]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $resultado = array_map(function($f) {
        $tiempoMuertoMin = null;
        if ($f['salida_qr_at'] && $f['movimiento_iniciado_at']) {
            $tiempoMuertoMin = round((strtotime($f['movimiento_iniciado_at']) - strtotime($f['salida_qr_at'])) / 60);
        }
        return [
            'ruta_id'           => (int)$f['ruta_id'],
            'ruta_estado'       => $f['ruta_estado'],
            'folio'             => $f['folio'],
            'cliente'           => $f['cliente_nombre'],
            'unidad'            => $f['unidad'],
            'chofer'            => $f['chofer'],
            'salida_qr_at'      => $f['salida_qr_at'],
            'movimiento_iniciado_at' => $f['movimiento_iniciado_at'],
            'tiempo_muerto_min' => $tiempoMuertoMin,
            'llegada_gps_at'    => $f['llegada_gps_at'],
            'entregado_at'      => $f['entregado_at'],
        ];
    }, $filas);

    jsonResponse(['trazabilidad' => $resultado, 'fecha' => $fecha, 'total' => count($resultado)]);
}

// ── Replay de una ruta: paradas en orden optimizado (con su lat/lng ya cacheado por el
// cron, sin re-geocodificar) + track GPS real del día, para animar el recorrido del chofer
// en un mapa. Ver app/modulos/productividad.php, modal "Ver recorrido". ──
if ($vista === 'ruta_replay') {
    $ruta_id = (int)($_GET['ruta_id'] ?? 0);
    if (!$ruta_id) jsonResponse(['error' => 'ruta_id requerido'], 400);

    $stmt = $db->prepare("SELECT id, fecha, unidad, chofer, estado, regreso_planta_at FROM rutas WHERE id = ?");
    $stmt->execute([$ruta_id]);
    $ruta = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ruta) jsonResponse(['error' => 'Ruta no encontrada'], 404);

    $stmt = $db->prepare("
        SELECT re.secuencia, re.direccion, re.colonia, re.ciudad, re.lat, re.lng,
               re.estado, re.llegada_gps_at, re.entregado_at, o.folio, o.cliente_nombre
        FROM ruta_entregas re
        JOIN ordenes o ON o.id = re.orden_id
        WHERE re.ruta_id = ?
        ORDER BY re.secuencia ASC
    ");
    $stmt->execute([$ruta_id]);
    $paradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Track real: solo la propia BD (gps_posiciones, llenada por scripts/gps_tracker.php) —
    // no se llama a ninguna API de Google para armar el recorrido.
    $fin = $ruta['regreso_planta_at'] ?: date('Y-m-d H:i:s');
    $stmt = $db->prepare("
        SELECT lat, lng, velocidad, capturado_at
        FROM gps_posiciones
        WHERE unidad = ? AND DATE(capturado_at) = ? AND capturado_at <= ?
        ORDER BY capturado_at ASC
    ");
    $stmt->execute([$ruta['unidad'], $ruta['fecha'], $fin]);
    $track = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'ruta'    => $ruta,
        'paradas' => $paradas,
        'track'   => $track,
    ]);
}

jsonResponse(['error' => 'Vista no válida: hora|dia|semana|mes'], 400);