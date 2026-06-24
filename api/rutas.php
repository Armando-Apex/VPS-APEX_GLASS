<?php
// ============================================================
//  APEX GLASS - API: Rutas de Entrega
//  Archivo: api/rutas.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');

$user    = requireSessionApi();
$rol     = $user['rol'];
$nombre  = $user['nombre'];
$db      = getDB();
$method  = $_SERVER['REQUEST_METHOD'];

$esLogistica = in_array($rol, ['administracion', 'dir_admin', 'dueno', 'desarrollo']);
$esChofer    = $rol === 'chofer';

if (!$esLogistica && !$esChofer) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Helper: peso estimado de una orden ───────────────────────
function calcularPesoOrden($db, $orden_id) {
    $stmt = $db->prepare("SELECT ancho_mm, alto_mm, cristal FROM piezas WHERE orden_id = ?");
    $stmt->execute([$orden_id]);
    $piezas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $peso = 0;
    foreach ($piezas as $p) {
        if (preg_match('/(\d+(?:\.\d+)?)\s*mm/i', $p['cristal'] ?? '', $m)) {
            $grosor = (float)$m[1];
        } else {
            $grosor = 6;
        }
        $peso += ($p['ancho_mm'] / 1000) * ($p['alto_mm'] / 1000) * $grosor * 2.5;
    }
    return round($peso, 2);
}

// ── GET ───────────────────────────────────────────────────────
if ($method === 'GET') {
    $accion = $_GET['accion'] ?? 'rutas_fecha';
    $fecha  = $_GET['fecha']  ?? date('Y-m-d');

    if ($accion === 'pendientes') {
        $stmt = $db->prepare("
            SELECT o.id, o.folio, o.cliente_nombre, o.asesor, o.fecha_entrega,
                   o.estado, c.localidad, c.ciudad_destino
            FROM ordenes o
            LEFT JOIN cotizaciones c ON c.orden_id = o.id
            WHERE o.estado = 'activa'
              AND NOT EXISTS (
                  SELECT 1 FROM piezas p
                  WHERE p.orden_id = o.id
                    AND p.estatus NOT IN ('terminado','entregado')
              )
              AND o.id NOT IN (
                  SELECT re.orden_id FROM ruta_entregas re
                  JOIN rutas r ON r.id = re.ruta_id
                  WHERE r.fecha = ?
              )
            ORDER BY o.fecha_entrega ASC, o.id ASC
        ");
        $stmt->execute([$fecha]);
        $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ordenes as &$o) {
            $o['peso_kg'] = calcularPesoOrden($db, $o['id']);
        }
        echo json_encode($ordenes); exit;
    }

    if ($accion === 'rutas_fecha') {
        $stmt = $db->prepare("
            SELECT r.*,
                   COUNT(re.id) as total_entregas,
                   COALESCE(SUM(re.peso_kg),0) as peso_total,
                   COALESCE(SUM(re.estado = 'entregado'),0) as entregadas
            FROM rutas r
            LEFT JOIN ruta_entregas re ON re.ruta_id = r.id
            WHERE r.fecha = ?
            GROUP BY r.id
            ORDER BY r.unidad ASC, r.id ASC
        ");
        $stmt->execute([$fecha]);
        $rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rutas as &$ruta) {
            $stmt2 = $db->prepare("
                SELECT re.*, re.estado as entrega_estado, o.folio, o.cliente_nombre, o.estado as orden_estado
                FROM ruta_entregas re
                JOIN ordenes o ON o.id = re.orden_id
                WHERE re.ruta_id = ?
                ORDER BY re.secuencia ASC, re.id ASC
            ");
            $stmt2->execute([$ruta['id']]);
            $entregas = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            // Agregar piezas asignadas por entrega
            foreach ($entregas as &$e) {
                $stmt3 = $db->prepare("
                    SELECT rep.id, rep.pieza_id, rep.estado,
                           p.qr_code, p.partida, p.pieza_num, p.pieza_total,
                           p.cristal_corto, p.ancho_mm, p.alto_mm
                    FROM ruta_entrega_piezas rep
                    JOIN piezas p ON p.id = rep.pieza_id
                    WHERE rep.ruta_entrega_id = ?
                    ORDER BY p.partida ASC, p.pieza_num ASC
                ");
                $stmt3->execute([$e['id']]);
                $e['piezas'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($e);
            $ruta['entregas'] = $entregas;
        }
        echo json_encode($rutas); exit;
    }

    if ($accion === 'mi_ruta') {
        $fecha_q  = $_GET['fecha'] ?? date('Y-m-d');
        $chofer_q = $esChofer ? $nombre : ($_GET['chofer'] ?? null);

        $sql = "
            SELECT r.id as ruta_id, r.unidad, r.chofer, r.estado as ruta_estado, r.fecha,
                   re.id as entrega_id, re.secuencia, re.direccion, re.colonia, re.ciudad,
                   re.referencias, re.peso_kg, re.estado as entrega_estado, re.notas_entrega,
                   o.folio, o.cliente_nombre
            FROM rutas r
            JOIN ruta_entregas re ON re.ruta_id = r.id
            JOIN ordenes o ON o.id = re.orden_id
            WHERE r.fecha = ?
        ";
        $params = [$fecha_q];
        if ($chofer_q) { $sql .= " AND r.chofer = ?"; $params[] = $chofer_q; }
        $sql .= " ORDER BY r.id ASC, re.secuencia ASC, re.id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar piezas por entrega
        foreach ($entregas as &$e) {
            $stmt2 = $db->prepare("
                SELECT rep.id, rep.pieza_id, rep.estado,
                       p.qr_code, p.partida, p.pieza_num, p.pieza_total,
                       p.cristal_corto, p.ancho_mm, p.alto_mm
                FROM ruta_entrega_piezas rep
                JOIN piezas p ON p.id = rep.pieza_id
                WHERE rep.ruta_entrega_id = ?
                ORDER BY p.partida ASC, p.pieza_num ASC
            ");
            $stmt2->execute([$e['entrega_id']]);
            $e['piezas'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($e);

        echo json_encode($entregas); exit;
    }

    if ($accion === 'piezas_orden') {
        if (!$esLogistica) { echo json_encode(['error' => 'Sin permiso']); exit; }
        $orden_id = (int)($_GET['orden_id'] ?? 0);
        if (!$orden_id) { echo json_encode(['error' => 'orden_id requerido']); exit; }
        $stmt = $db->prepare("
            SELECT p.id, p.partida, p.pieza_num, p.pieza_total,
                   p.qr_code, p.cristal_corto, p.ancho_mm, p.alto_mm, p.estatus
            FROM piezas p
            WHERE p.orden_id = ? AND p.estatus = 'terminado'
            ORDER BY p.partida ASC, p.pieza_num ASC
        ");
        $stmt->execute([$orden_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    echo json_encode(['error' => 'Acción no reconocida']); exit;
}

// ── POST ──────────────────────────────────────────────────────
if ($method === 'POST') {
    $accion = $body['accion'] ?? '';

    if ($accion === 'crear_ruta') {
        if (!$esLogistica) { echo json_encode(['error' => 'Sin permiso']); exit; }
        $fecha  = $body['fecha']  ?? date('Y-m-d');
        $unidad = $body['unidad'] ?? '';
        $chofer = trim($body['chofer'] ?? '');
        $notas  = trim($body['notas']  ?? '');
        if (!in_array($unidad, ['gris','blanca'])) {
            echo json_encode(['error' => 'Unidad inválida']); exit;
        }
        $db->prepare("INSERT INTO rutas (fecha, unidad, chofer, notas, creado_por) VALUES (?,?,?,?,?)")
           ->execute([$fecha, $unidad, $chofer, $notas, $nombre]);
        echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId()]); exit;
    }

    if ($accion === 'asignar') {
        if (!$esLogistica) { echo json_encode(['error' => 'Sin permiso']); exit; }
        $ruta_id     = (int)($body['ruta_id']    ?? 0);
        $orden_id    = (int)($body['orden_id']   ?? 0);
        $direccion   = trim($body['direccion']   ?? '');
        $colonia     = trim($body['colonia']     ?? '');
        $ciudad      = trim($body['ciudad']      ?? 'Monterrey');
        $referencias = trim($body['referencias'] ?? '');
        $pieza_ids   = $body['pieza_ids'] ?? [];
        if (!$ruta_id || !$orden_id) {
            echo json_encode(['error' => 'Datos incompletos']); exit;
        }
        if (empty($pieza_ids)) {
            echo json_encode(['error' => 'Debes seleccionar al menos una pieza']); exit;
        }

        // Calcular peso solo de las piezas seleccionadas
        $peso = 0;
        $stmt_p = $db->prepare("SELECT ancho_mm, alto_mm, cristal FROM piezas WHERE id = ?");
        foreach ($pieza_ids as $pid) {
            $stmt_p->execute([(int)$pid]);
            $p = $stmt_p->fetch(PDO::FETCH_ASSOC);
            if ($p) {
                if (preg_match('/(\d+(?:\.\d+)?)\s*mm/i', $p['cristal'] ?? '', $m)) {
                    $grosor = (float)$m[1];
                } else {
                    $grosor = 6;
                }
                $peso += ($p['ancho_mm'] / 1000) * ($p['alto_mm'] / 1000) * $grosor * 2.5;
            }
        }
        $peso = round($peso, 2);

        // Verificar capacidad
        $cap = ['gris' => 1250, 'blanca' => 700];
        $r = $db->prepare("SELECT r.unidad, COALESCE(SUM(re.peso_kg),0) as usado
                            FROM rutas r LEFT JOIN ruta_entregas re ON re.ruta_id = r.id
                            WHERE r.id = ? GROUP BY r.id");
        $r->execute([$ruta_id]);
        $r = $r->fetch(PDO::FETCH_ASSOC);
        $capacidad = $cap[$r['unidad']] ?? 700;
        $usado     = (float)($r['usado'] ?? 0);

        if (($usado + $peso) > $capacidad) {
            echo json_encode([
                'error' => 'Excede la capacidad de la unidad ' . $r['unidad'] . ' ('.$capacidad.' kg). Disponible: '.round($capacidad-$usado,1).' kg, piezas: '.$peso.' kg'
            ]); exit;
        }

        $seq = $db->prepare("SELECT COALESCE(MAX(secuencia),0)+1 as n FROM ruta_entregas WHERE ruta_id=?");
        $seq->execute([$ruta_id]);
        $seq = (int)$seq->fetch(PDO::FETCH_ASSOC)['n'];

        try {
            $db->beginTransaction();
            $db->prepare("INSERT INTO ruta_entregas
                (ruta_id, orden_id, secuencia, direccion, colonia, ciudad, referencias, peso_kg)
                VALUES (?,?,?,?,?,?,?,?)")
               ->execute([$ruta_id, $orden_id, $seq, $direccion, $colonia, $ciudad, $referencias, $peso]);
            $re_id = (int)$db->lastInsertId();

            // Insertar piezas seleccionadas
            $ins = $db->prepare("INSERT INTO ruta_entrega_piezas (ruta_entrega_id, pieza_id) VALUES (?,?)");
            foreach ($pieza_ids as $pid) {
                $ins->execute([$re_id, (int)$pid]);
            }
            $db->commit();
            echo json_encode(['ok' => true, 'peso_kg' => $peso, 'entrega_id' => $re_id]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['error' => 'Esa orden ya está en esta ruta o hubo un error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($accion === 'actualizar_entrega') {
        if (!$esLogistica) { echo json_encode(['error' => 'Sin permiso']); exit; }
        $id  = (int)($body['entrega_id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); exit; }
        $db->prepare("UPDATE ruta_entregas SET direccion=?, colonia=?, ciudad=?, referencias=? WHERE id=?")
           ->execute([
               trim($body['direccion']   ?? ''),
               trim($body['colonia']     ?? ''),
               trim($body['ciudad']      ?? 'Monterrey'),
               trim($body['referencias'] ?? ''),
               $id
           ]);
        echo json_encode(['ok' => true]); exit;
    }

    if ($accion === 'reordenar') {
        if (!$esLogistica) { echo json_encode(['error' => 'Sin permiso']); exit; }
        foreach (($body['orden'] ?? []) as $i => $eid) {
            $db->prepare("UPDATE ruta_entregas SET secuencia=? WHERE id=?")
               ->execute([$i + 1, (int)$eid]);
        }
        echo json_encode(['ok' => true]); exit;
    }

    if ($accion === 'iniciar_ruta') {
        if (!$esLogistica) { echo json_encode(['error' => 'Sin permiso']); exit; }
        $id = (int)($body['ruta_id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); exit; }
        $db->prepare("UPDATE rutas SET estado='en_ruta', updated_at=NOW() WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]); exit;
    }

    if ($accion === 'marcar_estado') {
        $entrega_id = (int)($body['entrega_id'] ?? 0);
        $estado     = $body['estado'] ?? '';
        $notas      = trim($body['notas_entrega'] ?? '');
        if (!$entrega_id || !in_array($estado, ['entregado','no_entregado','pendiente'])) {
            echo json_encode(['error' => 'Datos inválidos']); exit;
        }
        // Chofer solo puede marcar entregas de rutas asignadas a su nombre
        if ($esChofer) {
            $own = $db->prepare("SELECT r.chofer FROM ruta_entregas re JOIN rutas r ON r.id = re.ruta_id WHERE re.id = ?");
            $own->execute([$entrega_id]);
            $own = $own->fetch(PDO::FETCH_ASSOC);
            if (!$own || $own['chofer'] !== $nombre) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permiso sobre esta entrega']); exit;
            }
        }
        $ts = ($estado === 'entregado') ? date('Y-m-d H:i:s') : null;
        $db->prepare("UPDATE ruta_entregas SET estado=?, entregado_at=?, notas_entrega=? WHERE id=?")
           ->execute([$estado, $ts, $notas, $entrega_id]);

        if ($estado === 'entregado') {
            $re = $db->prepare("SELECT orden_id FROM ruta_entregas WHERE id=?");
            $re->execute([$entrega_id]);
            $re = $re->fetch(PDO::FETCH_ASSOC);
            if ($re) {
                $db->prepare("UPDATE ordenes SET estado='entregada', updated_at=NOW() WHERE id=?")
                   ->execute([$re['orden_id']]);
                $db->prepare("UPDATE piezas SET estatus='entregado', updated_at=NOW() WHERE orden_id=? AND estatus='terminado'")
                   ->execute([$re['orden_id']]);
            }
        }

        // Marcar ruta como completada si no quedan pendientes
        $chk = $db->prepare("SELECT ruta_id, SUM(estado='pendiente') as pend FROM ruta_entregas WHERE id=?");
        $chk->execute([$entrega_id]);
        $chk = $chk->fetch(PDO::FETCH_ASSOC);
        if ($chk && (int)$chk['pend'] === 0) {
            $pend2 = $db->prepare("SELECT SUM(estado='pendiente') as p FROM ruta_entregas WHERE ruta_id=?");
            $pend2->execute([$chk['ruta_id']]);
            $pend2 = $pend2->fetch(PDO::FETCH_ASSOC);
            if ((int)($pend2['p'] ?? 1) === 0) {
                $db->prepare("UPDATE rutas SET estado='completada', updated_at=NOW() WHERE id=?")
                   ->execute([$chk['ruta_id']]);
            }
        }
        echo json_encode(['ok' => true]); exit;
    }

    if ($accion === 'marcar_pieza') {
        $entrega_id = (int)($body['entrega_id'] ?? 0);
        $qr_code    = trim($body['qr_code']    ?? '');
        $estado     = $body['estado'] ?? 'entregada'; // entregada | rechazada
        if (!$entrega_id || !$qr_code) {
            echo json_encode(['error' => 'Datos incompletos']); exit;
        }
        if (!in_array($estado, ['entregada','rechazada'])) {
            echo json_encode(['error' => 'Estado inválido']); exit;
        }

        // Verificar que el chofer tiene acceso
        if ($esChofer) {
            $own = $db->prepare("SELECT r.chofer FROM ruta_entregas re JOIN rutas r ON r.id = re.ruta_id WHERE re.id = ?");
            $own->execute([$entrega_id]);
            $own = $own->fetch(PDO::FETCH_ASSOC);
            if (!$own || $own['chofer'] !== $nombre) {
                http_response_code(403);
                echo json_encode(['error' => 'Sin permiso']); exit;
            }
        }

        // Buscar la pieza por QR dentro de esta entrega
        $pieza = $db->prepare("
            SELECT rep.id, rep.pieza_id FROM ruta_entrega_piezas rep
            JOIN piezas p ON p.id = rep.pieza_id
            WHERE rep.ruta_entrega_id = ? AND p.qr_code = ?
        ");
        $pieza->execute([$entrega_id, $qr_code]);
        $pieza = $pieza->fetch(PDO::FETCH_ASSOC);

        if (!$pieza) {
            echo json_encode(['error' => 'QR no corresponde a esta entrega']); exit;
        }

        $rechazada_at = $estado === 'rechazada' ? date('Y-m-d H:i:s') : null;
        $db->prepare("UPDATE ruta_entrega_piezas SET estado=?, rechazada_at=? WHERE id=?")
           ->execute([$estado, $rechazada_at, $pieza['id']]);

        // Si entregada, marcar pieza como entregado
        if ($estado === 'entregada') {
            $db->prepare("UPDATE piezas SET estatus='entregado', updated_at=NOW() WHERE id=?")
               ->execute([$pieza['pieza_id']]);
        }

        // Verificar si todas las piezas de esta entrega ya fueron marcadas
        $pendientes = $db->prepare("SELECT COUNT(*) FROM ruta_entrega_piezas WHERE ruta_entrega_id = ? AND estado = 'asignada'");
        $pendientes->execute([$entrega_id]);
        $pendientes = (int)$pendientes->fetchColumn();

        $respuesta = ['ok' => true, 'pendientes_pieza' => $pendientes];

        if ($pendientes === 0) {
            // Todas las piezas marcadas — determinar estado de la entrega
            $hayRechazadas = $db->prepare("SELECT COUNT(*) FROM ruta_entrega_piezas WHERE ruta_entrega_id = ? AND estado = 'rechazada'");
            $hayRechazadas->execute([$entrega_id]);
            $hayRechazadas = (int)$hayRechazadas->fetchColumn();

            $hayEntregadas = $db->prepare("SELECT COUNT(*) FROM ruta_entrega_piezas WHERE ruta_entrega_id = ? AND estado = 'entregada'");
            $hayEntregadas->execute([$entrega_id]);
            $hayEntregadas = (int)$hayEntregadas->fetchColumn();

            if ($hayEntregadas > 0) {
                // Al menos algunas entregadas — marcar entrega como entregado
                $ts = date('Y-m-d H:i:s');
                $db->prepare("UPDATE ruta_entregas SET estado='entregado', entregado_at=? WHERE id=?")
                   ->execute([$ts, $entrega_id]);
                $respuesta['entrega_completada'] = true;
            }

            // Obtener orden_id para evaluar si la orden se cierra
            $re = $db->prepare("SELECT orden_id FROM ruta_entregas WHERE id=?");
            $re->execute([$entrega_id]);
            $re = $re->fetch(PDO::FETCH_ASSOC);

            if ($re) {
                // Verificar si todas las piezas de la orden están entregadas
                $totalOrden = $db->prepare("SELECT COUNT(*) FROM piezas WHERE orden_id=?");
                $totalOrden->execute([$re['orden_id']]);
                $totalOrden = (int)$totalOrden->fetchColumn();

                $entregadasOrden = $db->prepare("SELECT COUNT(*) FROM piezas WHERE orden_id=? AND estatus='entregado'");
                $entregadasOrden->execute([$re['orden_id']]);
                $entregadasOrden = (int)$entregadasOrden->fetchColumn();

                if ($totalOrden === $entregadasOrden) {
                    $db->prepare("UPDATE ordenes SET estado='entregada', updated_at=NOW() WHERE id=?")
                       ->execute([$re['orden_id']]);
                    $respuesta['orden_cerrada'] = true;
                }
            }

            // Verificar si la ruta se completa
            $re2 = $db->prepare("SELECT ruta_id FROM ruta_entregas WHERE id=?");
            $re2->execute([$entrega_id]);
            $re2 = $re2->fetch(PDO::FETCH_ASSOC);
            if ($re2) {
                $pendRuta = $db->prepare("SELECT COUNT(*) FROM ruta_entregas WHERE ruta_id=? AND estado='pendiente'");
                $pendRuta->execute([$re2['ruta_id']]);
                if ((int)$pendRuta->fetchColumn() === 0) {
                    $db->prepare("UPDATE rutas SET estado='completada', updated_at=NOW() WHERE id=?")
                       ->execute([$re2['ruta_id']]);
                }
            }
        }

        echo json_encode($respuesta); exit;
    }

    if ($accion === 'quitar') {
        if (!$esLogistica) { echo json_encode(['error' => 'Sin permiso']); exit; }
        $id = (int)($body['entrega_id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'ID requerido']); exit; }
        // Limpiar piezas primero
        $db->prepare("DELETE FROM ruta_entrega_piezas WHERE ruta_entrega_id=?")->execute([$id]);
        $db->prepare("DELETE FROM ruta_entregas WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]); exit;
    }

    if ($accion === 'optimizar') {
        if (!$esLogistica) { echo json_encode(['error' => 'Sin permiso']); exit; }
        $ruta_id = (int)($body['ruta_id'] ?? 0);
        if (!$ruta_id) { echo json_encode(['error' => 'ID requerido']); exit; }

        // Obtener entregas pendientes de esta ruta
        $stmt = $db->prepare("
            SELECT re.id, re.direccion, re.colonia, re.ciudad
            FROM ruta_entregas re
            WHERE re.ruta_id = ? AND re.estado = 'pendiente'
            ORDER BY re.secuencia ASC
        ");
        $stmt->execute([$ruta_id]);
        $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($entregas) < 2) {
            echo json_encode(['ok' => true, 'msg' => 'Sin suficientes paradas para optimizar']);
            exit;
        }

        // Construir waypoints para Routes API
        $MAPS_KEY = defined('GOOGLE_MAPS_SERVER_KEY') && GOOGLE_MAPS_SERVER_KEY ? GOOGLE_MAPS_SERVER_KEY : (defined('GOOGLE_MAPS_KEY') ? GOOGLE_MAPS_KEY : '');
        if (!$MAPS_KEY) {
            echo json_encode(['error' => 'Google Maps Key no configurada']); exit;
        }

        $origen = 'Avenida de la Industria 214, Parque Industrial Marfer, Santa Catarina, Nuevo León';

        $intermediates = array_map(function($e) {
            $addr = implode(', ', array_filter([$e['direccion'], $e['colonia'], $e['ciudad']]));
            return ['address' => $addr ?: 'Monterrey, Nuevo León'];
        }, $entregas);

        $payload = [
            'origin'        => ['address' => $origen],
            'destination'   => ['address' => $origen],
            'intermediates' => $intermediates,
            'travelMode'    => 'DRIVE',
            'routingPreference' => 'TRAFFIC_AWARE',
            'optimizeWaypointOrder' => true,
        ];

        $ch = curl_init('https://routes.googleapis.com/directions/v2:computeRoutes');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Goog-Api-Key: ' . $MAPS_KEY,
                'X-Goog-FieldMask: routes.optimizedIntermediateWaypointIndex,routes.legs.duration,routes.legs.distanceMeters',
            ],
        ]);
        $resp   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            echo json_encode(['error' => 'Error al contactar Google Maps', 'detalle' => $resp]); exit;
        }

        $data = json_decode($resp, true);
        $order = $data['routes'][0]['optimizedIntermediateWaypointIndex'] ?? null;

        if (!$order) {
            echo json_encode(['ok' => true, 'msg' => 'Google Maps no devolvió orden optimizado']); exit;
        }

        // Reordenar entregas según el orden optimizado
        foreach ($order as $nuevaPos => $idxOriginal) {
            $entrega_id = $entregas[$idxOriginal]['id'];
            $db->prepare("UPDATE ruta_entregas SET secuencia=? WHERE id=?")
               ->execute([$nuevaPos + 1, $entrega_id]);
        }

        // Calcular tiempo total estimado
        $legs     = $data['routes'][0]['legs'] ?? [];
        $totalSeg = array_sum(array_map(function($l) {
            return (int)($l['duration'] ?? 0);
        }, $legs));
        $totalMin = round($totalSeg / 60);

        echo json_encode([
            'ok'        => true,
            'orden'     => $order,
            'tiempo_min'=> $totalMin,
            'paradas'   => count($entregas),
        ]);
        exit;
    }

    echo json_encode(['error' => 'Acción no reconocida']); exit;
}

echo json_encode(['error' => 'Método no soportado']);