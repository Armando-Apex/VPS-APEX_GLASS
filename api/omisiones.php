<?php
require_once 'config.php';
require_once 'permisos.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

$user = requirePermiso('ver_dashboard');
$rol  = $user['rol'];
if (!in_array($rol, ['dir_admin','dueno','director','jefe_piso','desarrollo'])) {
    jsonResponse(['error' => 'Sin permisos'], 403);
}

$db     = getDB();
$accion = $_GET['accion'] ?? 'lista';
$desde  = $_GET['desde']  ?? date('Y-m-01');
$hasta  = $_GET['hasta']  ?? date('Y-m-d');

// Estaciones reportadas y a qué estatus "checkpoint" corresponden en piezas/historial_estatus.
// Nota: no todas las piezas requieren todas las estaciones (ej. un espejo no lleva canteado
// ni templado) — la aplicabilidad se calcula por pieza con las mismas reglas de negocio que
// ya usan api/actualizar_estatus.php y app/operador.php (requiere_corte / cpb / tp+ta+resaques /
// requiere_templado), no por el estatus_anterior crudo de historial_estatus.
//
// "Escaneada" NO se determina con omision=0: cuando actualizar_estatus.php registra un salto de
// varias estaciones a la vez (ej. de "cortado" directo a "en_horno" en una pieza sin canteado/
// trazo/taladro aplicables), inserta una fila por cada paso saltado con omision=1 — incluida la
// fila de LLEGADA real a la estación destino, aunque esa sí fue un registro genuino. Las filas
// sintéticas intermedias (las que realmente representan un paso saltado) se distinguen porque
// su nota es literalmente 'OMISIÓN AUTOMÁTICA'; la fila final del salto trae la nota real del
// usuario (o vacía). Por eso "escaneada" = existe una fila con ese estatus_nuevo cuya nota NO es
// ese marcador — verificado contra datos reales antes de publicar (ver dry-run de la sesión).
$ESTACIONES = [
    'corte'    => ['label' => 'Corte',    'checkpoint' => 'cortado'],
    'canteado' => ['label' => 'Canteado', 'checkpoint' => 'canteado'],
    'trazo'    => ['label' => 'Trazo',    'checkpoint' => 'trazo'],
    'taladro'  => ['label' => 'Taladro',  'checkpoint' => 'taladro'],
    'horno'    => ['label' => 'Horno',    'checkpoint' => 'en_horno'],
];

if ($accion === 'lista') {
    // Piezas que llegaron a "terminado" dentro del período (última vez que llegó, por si tuvo reproceso)
    $stmt = $db->prepare("
        SELECT
            p.id, p.orden_id, p.partida, p.pieza_num, p.pieza_total,
            p.ancho_mm, p.alto_mm,
            p.requiere_corte, p.cpb, p.tp, p.ta, p.resaques, p.requiere_templado,
            o.folio, o.cliente_nombre,
            t.fecha_terminado,
            MAX(CASE WHEN h.estatus_nuevo = 'cortado'  AND COALESCE(h.notas,'') <> 'OMISIÓN AUTOMÁTICA' THEN 1 ELSE 0 END) AS esc_corte,
            MAX(CASE WHEN h.estatus_nuevo = 'canteado' AND COALESCE(h.notas,'') <> 'OMISIÓN AUTOMÁTICA' THEN 1 ELSE 0 END) AS esc_canteado,
            MAX(CASE WHEN h.estatus_nuevo = 'trazo'    AND COALESCE(h.notas,'') <> 'OMISIÓN AUTOMÁTICA' THEN 1 ELSE 0 END) AS esc_trazo,
            MAX(CASE WHEN h.estatus_nuevo = 'taladro'  AND COALESCE(h.notas,'') <> 'OMISIÓN AUTOMÁTICA' THEN 1 ELSE 0 END) AS esc_taladro,
            MAX(CASE WHEN h.estatus_nuevo = 'en_horno' AND COALESCE(h.notas,'') <> 'OMISIÓN AUTOMÁTICA' THEN 1 ELSE 0 END) AS esc_horno
        FROM piezas p
        JOIN ordenes o ON o.id = p.orden_id
        JOIN (
            SELECT pieza_id, MAX(created_at) AS fecha_terminado
            FROM historial_estatus
            WHERE estatus_nuevo = 'terminado'
            GROUP BY pieza_id
        ) t ON t.pieza_id = p.id
        LEFT JOIN historial_estatus h ON h.pieza_id = p.id
        WHERE DATE(t.fecha_terminado) BETWEEN ? AND ?
        GROUP BY p.id
    ");
    $stmt->execute([$desde, $hasta]);
    $rows = $stmt->fetchAll();

    $tot = [];
    foreach ($ESTACIONES as $key => $meta) {
        $tot[$key] = ['estacion' => $key, 'label' => $meta['label'], 'escaneadas' => 0, 'omitidas' => 0];
    }

    $detalle = [];
    foreach ($rows as $r) {
        $aplicaCorte    = (int)$r['requiere_corte'] === 1;
        $cpb            = trim($r['cpb'] ?? '');
        $aplicaCanteado = $cpb !== '' && strcasecmp($cpb, 'No') !== 0;
        $aplicaTaladro  = ((int)$r['tp'] + (int)$r['ta'] + (int)$r['resaques']) > 0;
        $aplicaHorno    = (int)$r['requiere_templado'] === 1;

        $checks = [
            'corte'    => [$aplicaCorte,    (int)$r['esc_corte']],
            'canteado' => [$aplicaCanteado, (int)$r['esc_canteado']],
            'trazo'    => [$aplicaTaladro,  (int)$r['esc_trazo']],
            'taladro'  => [$aplicaTaladro,  (int)$r['esc_taladro']],
            'horno'    => [$aplicaHorno,    (int)$r['esc_horno']],
        ];

        $omitidasPieza = [];
        foreach ($checks as $key => list($aplica, $escaneada)) {
            if (!$aplica) continue;
            if ($escaneada) {
                $tot[$key]['escaneadas']++;
            } else {
                $tot[$key]['omitidas']++;
                $omitidasPieza[] = $ESTACIONES[$key]['label'];
            }
        }

        if ($omitidasPieza) {
            $detalle[] = [
                'folio'           => $r['folio'],
                'cliente_nombre'  => $r['cliente_nombre'],
                'orden_id'        => $r['orden_id'],
                'partida'         => $r['partida'],
                'pieza_num'       => $r['pieza_num'],
                'pieza_total'     => $r['pieza_total'],
                'ancho_mm'        => $r['ancho_mm'],
                'alto_mm'         => $r['alto_mm'],
                'fecha_terminado' => $r['fecha_terminado'],
                'estaciones_omitidas' => $omitidasPieza,
            ];
        }
    }

    usort($detalle, function($a, $b) { return strcmp($b['fecha_terminado'], $a['fecha_terminado']); });

    $estaciones = [];
    foreach ($tot as $key => $t) {
        $total = $t['escaneadas'] + $t['omitidas'];
        $t['total']       = $total;
        $t['pct_omision'] = $total > 0 ? round($t['omitidas'] / $total * 100, 1) : 0.0;
        $estaciones[] = $t;
    }

    jsonResponse([
        'ok'               => true,
        'desde'            => $desde,
        'hasta'            => $hasta,
        'total_terminadas' => count($rows),
        'estaciones'       => $estaciones,
        'detalle'          => array_slice($detalle, 0, 300),
    ]);
}

jsonResponse(['error' => 'Acción no válida'], 400);
