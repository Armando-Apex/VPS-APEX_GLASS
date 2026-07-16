<?php
// ============================================================
//  APEX GLASS - API: Rutas de Entrega
//  Archivo: api/rutas.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
require_once 'rutas_lib.php';

header('Content-Type: application/json; charset=utf-8');

$user    = requireSessionApi();
$rol     = $user['rol'];
$nombre  = $user['nombre'];
$db      = getDB();
$method  = $_SERVER['REQUEST_METHOD'];

$esLogistica = in_array($rol, ['administracion', 'dir_admin', 'dueno', 'desarrollo', 'comercial']);
$esChofer    = $rol === 'chofer';

if (!$esLogistica && !$esChofer) {
    jsonResponse(['error' => 'Sin permiso'], 403);
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
        // requiere_ruta=1: Cobranza ya cerró la orden (salida tipo 'chofer'/domicilio) pero le
        // falta el trayecto físico. No se filtra por fecha de ruta (a diferencia de antes) porque
        // la orden ya no está en estado 'activa' y debe seguir apareciendo hasta que se asigne,
        // sin importar cuántos días lleve esperando.
        $stmt = $db->prepare("
            SELECT o.id, o.folio, o.cliente_nombre, o.asesor, o.fecha_entrega,
                   o.estado, c.localidad, c.ciudad_destino
            FROM ordenes o
            LEFT JOIN cotizaciones c ON c.orden_id = o.id
            WHERE o.requiere_ruta = 1
              AND o.id NOT IN (SELECT re.orden_id FROM ruta_entregas re)
            ORDER BY o.fecha_entrega ASC, o.id ASC
        ");
        $stmt->execute();
        $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ordenes as &$o) {
            $o['peso_kg'] = calcularPesoOrden($db, $o['id']);
        }
        jsonResponse($ordenes); exit;
    }

    if ($accion === 'rutas_fecha') {
        $stmt = $db->prepare("
            SELECT r.*,
                   COUNT(re.id) as total_entregas,
                   COALESCE(SUM(re.peso_kg),0) as peso_total,
                   COALESCE(SUM(re.estado = 'entregado'),0) as entregadas
            FROM rutas r
            LEFT JOIN ruta_entregas re ON re.ruta_id = r.id
            WHERE r.fecha = ? AND r.archivada = 0
            GROUP BY r.id
            ORDER BY r.unidad ASC, r.id ASC
        ");
        $stmt->execute([$fecha]);
        $rutas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rutas as &$ruta) {
            // Entregas parciales: ya se registran hoy desde la remisión impresa (orden_salidas,
            // ver api/salidas.php + app/imprimir_salida.php) — se jala ese dato existente en vez
            // de duplicar el seguimiento con ruta_entrega_piezas, que es un mecanismo aparte.
            $stmt2 = $db->prepare("
                SELECT re.*, re.estado as entrega_estado, o.folio, o.cliente_nombre, o.estado as orden_estado,
                       os.es_parcial, os.piezas_count as salida_piezas_count, os.piezas_total as salida_piezas_total
                FROM ruta_entregas re
                JOIN ordenes o ON o.id = re.orden_id
                LEFT JOIN (
                    SELECT os1.orden_id, os1.es_parcial, os1.piezas_count, os1.piezas_total
                    FROM orden_salidas os1
                    INNER JOIN (
                        SELECT orden_id, MAX(id) as max_id FROM orden_salidas GROUP BY orden_id
                    ) ult ON ult.orden_id = os1.orden_id AND ult.max_id = os1.id
                ) os ON os.orden_id = re.orden_id
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
        jsonResponse($rutas); exit;
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

        jsonResponse($entregas); exit;
    }

    if ($accion === 'piezas_orden') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $orden_id = (int)($_GET['orden_id'] ?? 0);
        if (!$orden_id) { jsonResponse(['error' => 'orden_id requerido']); exit; }
        // Con el flujo de requiere_ruta, la orden ya cerró en Cobranza y sus piezas ya quedaron
        // en 'entregado' (no 'terminado') — se incluyen ambos estatus para no romper la
        // asignación de piezas a la ruta.
        $stmt = $db->prepare("
            SELECT p.id, p.partida, p.pieza_num, p.pieza_total,
                   p.qr_code, p.cristal_corto, p.ancho_mm, p.alto_mm, p.estatus
            FROM piezas p
            WHERE p.orden_id = ? AND p.estatus IN ('terminado','entregado')
            ORDER BY p.partida ASC, p.pieza_num ASC
        ");
        $stmt->execute([$orden_id]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    if ($accion === 'piezas_carga') {
        // Checklist de carga en planta: piezas asignadas a la ruta (paradas pendientes) y si
        // ya se escanearon como cargadas, agrupadas por parada/orden.
        $ruta_id = (int)($_GET['ruta_id'] ?? 0);
        if (!$ruta_id) { jsonResponse(['error' => 'ruta_id requerido']); exit; }
        $stmt = $db->prepare("
            SELECT re.id as entrega_id, re.orden_id, o.folio, o.cliente_nombre,
                   rep.id as rep_id, rep.cargado_at, p.qr_code, p.partida, p.pieza_num, p.pieza_total, p.cristal_corto
            FROM ruta_entregas re
            JOIN ordenes o ON o.id = re.orden_id
            JOIN ruta_entrega_piezas rep ON rep.ruta_entrega_id = re.id
            JOIN piezas p ON p.id = rep.pieza_id
            WHERE re.ruta_id = ? AND re.estado = 'pendiente'
            ORDER BY re.secuencia ASC, p.partida ASC, p.pieza_num ASC
        ");
        $stmt->execute([$ruta_id]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    jsonResponse(['error' => 'Acción no reconocida']); exit;
}

// ── POST ──────────────────────────────────────────────────────
if ($method === 'POST') {
    $accion = $body['accion'] ?? '';

    if ($accion === 'crear_ruta') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $fecha  = $body['fecha']  ?? date('Y-m-d');
        $unidad = $body['unidad'] ?? '';
        $chofer = trim($body['chofer'] ?? '');
        $notas  = trim($body['notas']  ?? '');
        if (!in_array($unidad, ['gris','blanca'])) {
            jsonResponse(['error' => 'Unidad inválida']); exit;
        }
        $db->prepare("INSERT INTO rutas (fecha, unidad, chofer, notas, creado_por) VALUES (?,?,?,?,?)")
           ->execute([$fecha, $unidad, $chofer, $notas, $nombre]);
        jsonResponse(['ok' => true, 'id' => (int)$db->lastInsertId()]); exit;
    }

    if ($accion === 'asignar') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $ruta_id     = (int)($body['ruta_id']    ?? 0);
        $orden_id    = (int)($body['orden_id']   ?? 0);
        $direccion   = trim($body['direccion']   ?? '');
        $colonia     = trim($body['colonia']     ?? '');
        $ciudad      = trim($body['ciudad']      ?? 'Monterrey');
        $referencias = trim($body['referencias'] ?? '');
        $pieza_ids   = $body['pieza_ids'] ?? [];
        if (!$ruta_id || !$orden_id) {
            jsonResponse(['error' => 'Datos incompletos']); exit;
        }
        if (empty($pieza_ids)) {
            jsonResponse(['error' => 'Debes seleccionar al menos una pieza']); exit;
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
            jsonResponse([
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
            jsonResponse(['ok' => true, 'peso_kg' => $peso, 'entrega_id' => $re_id]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Esa orden ya está en esta ruta o hubo un error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($accion === 'actualizar_entrega') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $id  = (int)($body['entrega_id'] ?? 0);
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
        $db->prepare("UPDATE ruta_entregas SET direccion=?, colonia=?, ciudad=?, referencias=? WHERE id=?")
           ->execute([
               trim($body['direccion']   ?? ''),
               trim($body['colonia']     ?? ''),
               trim($body['ciudad']      ?? 'Monterrey'),
               trim($body['referencias'] ?? ''),
               $id
           ]);
        jsonResponse(['ok' => true]); exit;
    }

    if ($accion === 'reordenar') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        foreach (($body['orden'] ?? []) as $i => $eid) {
            $db->prepare("UPDATE ruta_entregas SET secuencia=? WHERE id=?")
               ->execute([$i + 1, (int)$eid]);
        }
        jsonResponse(['ok' => true]); exit;
    }

    if ($accion === 'iniciar_ruta') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $id = (int)($body['ruta_id'] ?? 0);
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

        // Bloqueo: no se puede iniciar si falta escanear alguna pieza como cargada en planta
        $chk = $db->prepare("
            SELECT COUNT(*) FROM ruta_entrega_piezas rep
            JOIN ruta_entregas re ON re.id = rep.ruta_entrega_id
            WHERE re.ruta_id = ? AND re.estado = 'pendiente' AND rep.cargado_at IS NULL
        ");
        $chk->execute([$id]);
        $faltan = (int)$chk->fetchColumn();
        if ($faltan > 0) {
            jsonResponse(['error' => "Faltan $faltan pieza(s) por escanear como cargadas antes de iniciar la ruta"]); exit;
        }

        $db->prepare("UPDATE rutas SET estado='en_ruta', updated_at=NOW() WHERE id=?")->execute([$id]);

        $etas = calcularYGuardarEtas($db, $id);
        jsonResponse(['ok' => true, 'etas' => $etas]); exit;
    }

    if ($accion === 'recalcular_eta') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $id = (int)($body['ruta_id'] ?? 0);
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
        $etas = calcularYGuardarEtas($db, $id);
        jsonResponse(['ok' => true, 'etas' => $etas]); exit;
    }

    if ($accion === 'marcar_estado') {
        $entrega_id = (int)($body['entrega_id'] ?? 0);
        $estado     = $body['estado'] ?? '';
        $notas      = trim($body['notas_entrega'] ?? '');
        if (!$entrega_id || !in_array($estado, ['entregado','no_entregado','pendiente'])) {
            jsonResponse(['error' => 'Datos inválidos']); exit;
        }
        // Chofer solo puede marcar entregas de rutas asignadas a su nombre
        if ($esChofer) {
            $own = $db->prepare("SELECT r.chofer FROM ruta_entregas re JOIN rutas r ON r.id = re.ruta_id WHERE re.id = ?");
            $own->execute([$entrega_id]);
            $own = $own->fetch(PDO::FETCH_ASSOC);
            if (!$own || $own['chofer'] !== $nombre) {
                jsonResponse(['error' => 'Sin permiso sobre esta entrega'], 403);
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

        // Marcar ruta como completada si no quedan pendientes; si sigue en_ruta con paradas
        // pendientes, recalcular el tiempo estimado (la parada que se acaba de entregar ya
        // no debe seguir sumando a la cuenta de la siguiente).
        $etas = [];
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
            } elseif ($estado === 'entregado') {
                $etas = calcularYGuardarEtas($db, $chk['ruta_id']);
            }
        }
        jsonResponse(['ok' => true, 'etas' => $etas]); exit;
    }

    if ($accion === 'marcar_pieza') {
        $entrega_id = (int)($body['entrega_id'] ?? 0);
        $qr_code    = trim($body['qr_code']    ?? '');
        $estado     = $body['estado'] ?? 'entregada'; // entregada | rechazada
        if (!$entrega_id || !$qr_code) {
            jsonResponse(['error' => 'Datos incompletos']); exit;
        }
        if (!in_array($estado, ['entregada','rechazada'])) {
            jsonResponse(['error' => 'Estado inválido']); exit;
        }

        // Verificar que el chofer tiene acceso
        if ($esChofer) {
            $own = $db->prepare("SELECT r.chofer FROM ruta_entregas re JOIN rutas r ON r.id = re.ruta_id WHERE re.id = ?");
            $own->execute([$entrega_id]);
            $own = $own->fetch(PDO::FETCH_ASSOC);
            if (!$own || $own['chofer'] !== $nombre) {
                jsonResponse(['error' => 'Sin permiso'], 403);
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
            jsonResponse(['error' => 'QR no corresponde a esta entrega']); exit;
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

        jsonResponse($respuesta); exit;
    }

    if ($accion === 'quitar') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $id = (int)($body['entrega_id'] ?? 0);
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
        // Limpiar piezas primero
        $db->prepare("DELETE FROM ruta_entrega_piezas WHERE ruta_entrega_id=?")->execute([$id]);
        $db->prepare("DELETE FROM ruta_entregas WHERE id=?")->execute([$id]);
        jsonResponse(['ok' => true]); exit;
    }

    if ($accion === 'eliminar_ruta') {
        // Borrar ruta por completo (irreversible) — solo dir_admin/desarrollo, a diferencia
        // del resto de acciones de logística que sí puede usar comercial (asesoras).
        if (!in_array($rol, ['dir_admin', 'desarrollo'])) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $ruta_id = (int)($body['ruta_id'] ?? 0);
        if (!$ruta_id) { jsonResponse(['error' => 'ID requerido']); exit; }

        // No borrar si ya hay entregas reales marcadas — se perdería el histórico de la entrega
        $stmt = $db->prepare("SELECT COUNT(*) FROM ruta_entregas WHERE ruta_id=? AND estado IN ('entregado','no_entregado')");
        $stmt->execute([$ruta_id]);
        if ((int)$stmt->fetchColumn() > 0) {
            jsonResponse(['error' => 'No se puede borrar: esta ruta ya tiene entregas registradas']); exit;
        }

        $stmt = $db->prepare("SELECT id FROM ruta_entregas WHERE ruta_id=?");
        $stmt->execute([$ruta_id]);
        $entregaIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($entregaIds) {
            $in = implode(',', array_fill(0, count($entregaIds), '?'));
            $db->prepare("DELETE FROM ruta_entrega_piezas WHERE ruta_entrega_id IN ($in)")->execute($entregaIds);
            $db->prepare("DELETE FROM ruta_entregas WHERE ruta_id=?")->execute([$ruta_id]);
        }
        $stmt = $db->prepare("DELETE FROM rutas WHERE id=?");
        $stmt->execute([$ruta_id]);
        if ($stmt->rowCount() === 0) { jsonResponse(['error' => 'Ruta no encontrada']); exit; }

        jsonResponse(['ok' => true]); exit;
    }

    if ($accion === 'archivar_ruta') {
        // Solo oculta la ruta del tablero del día (r.archivada=1) — los datos siguen intactos
        // en BD para consulta posterior (ej. Productividad). Solo aplica a rutas 'completada'.
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $ruta_id = (int)($body['ruta_id'] ?? 0);
        if (!$ruta_id) { jsonResponse(['error' => 'ID requerido']); exit; }

        $stmt = $db->prepare("SELECT estado FROM rutas WHERE id=?");
        $stmt->execute([$ruta_id]);
        $estadoActual = $stmt->fetchColumn();
        if ($estadoActual === false) { jsonResponse(['error' => 'Ruta no encontrada']); exit; }
        if ($estadoActual !== 'completada') { jsonResponse(['error' => 'Solo se puede finalizar una ruta ya completada']); exit; }

        $db->prepare("UPDATE rutas SET archivada=1 WHERE id=?")->execute([$ruta_id]);
        jsonResponse(['ok' => true]); exit;
    }

    if ($accion === 'optimizar') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $ruta_id = (int)($body['ruta_id'] ?? 0);
        if (!$ruta_id) { jsonResponse(['error' => 'ID requerido']); exit; }

        // Obtener entregas pendientes de esta ruta
        $stmt = $db->prepare("
            SELECT re.id, re.direccion, re.colonia, re.ciudad, o.folio, o.cliente_nombre
            FROM ruta_entregas re
            JOIN ordenes o ON o.id = re.orden_id
            WHERE re.ruta_id = ? AND re.estado = 'pendiente'
            ORDER BY re.secuencia ASC
        ");
        $stmt->execute([$ruta_id]);
        $entregas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($entregas) < 2) {
            jsonResponse(['ok' => true, 'msg' => 'Sin suficientes paradas para optimizar']);
            exit;
        }

        // Construir waypoints para Routes API
        $MAPS_KEY = defined('GOOGLE_MAPS_SERVER_KEY') && GOOGLE_MAPS_SERVER_KEY ? GOOGLE_MAPS_SERVER_KEY : (defined('GOOGLE_MAPS_KEY') ? GOOGLE_MAPS_KEY : '');
        if (!$MAPS_KEY) {
            jsonResponse(['error' => 'Google Maps Key no configurada']); exit;
        }

        $origen = 'Avenida de la Industria 214, Parque Industrial Marfer, Santa Catarina, Nuevo León';

        $intermediates = array_map(function($e) {
            $addr = implode(', ', array_filter([$e['direccion'], $e['colonia'], $e['ciudad']]));
            return ['address' => $addr ?: 'Monterrey, Nuevo León'];
        }, $entregas);

        // Tolerancia por parada: tiempo que se tarda el chofer en bajar el vidrio y entregar,
        // aparte del tiempo de manejo — Google solo calcula tiempo de traslado, no de descarga.
        $TOLERANCIA_MIN = 15;
        $toleranciaTotal = $TOLERANCIA_MIN * count($entregas);

        // Baseline: cuánto tardaría la ruta tal como está ordenada HOY (sin optimizar), para
        // poder comparar contra el resultado optimizado y comprobar que sí mejora algo real —
        // en vez de aplicar el reorden a ciegas confiando en que Google lo hizo bien.
        $baseline = computeRouteGoogle($MAPS_KEY, $origen, $intermediates, false);
        if ($baseline === null) {
            jsonResponse(['error' => 'Error al contactar Google Maps (baseline)']); exit;
        }
        $baselineMin = round(array_sum(array_map(function($l) {
            return (int)($l['duration'] ?? 0);
        }, $baseline['routes'][0]['legs'] ?? [])) / 60) + $toleranciaTotal;

        $data  = computeRouteGoogle($MAPS_KEY, $origen, $intermediates, true);
        if ($data === null) {
            jsonResponse(['error' => 'Error al contactar Google Maps']); exit;
        }

        $order = $data['routes'][0]['optimizedIntermediateWaypointIndex'] ?? null;

        if (!$order) {
            jsonResponse(['ok' => true, 'msg' => 'Google Maps no devolvió orden optimizado']); exit;
        }

        // Reordenar entregas según el orden optimizado
        foreach ($order as $nuevaPos => $idxOriginal) {
            $entrega_id = $entregas[$idxOriginal]['id'];
            $db->prepare("UPDATE ruta_entregas SET secuencia=? WHERE id=?")
               ->execute([$nuevaPos + 1, $entrega_id]);
        }

        // Calcular tiempo total estimado (traslado + tolerancia de descarga por parada)
        $legs     = $data['routes'][0]['legs'] ?? [];
        $totalSeg = array_sum(array_map(function($l) {
            return (int)($l['duration'] ?? 0);
        }, $legs));
        $totalMin = round($totalSeg / 60) + $toleranciaTotal;

        // Desglose tramo por tramo (Planta→parada1, parada1→parada2, ...) con el nombre de
        // cada punto, en el orden YA optimizado, para poder ver cuánto tarda cada segmento.
        // Cada tramo que TERMINA en una parada de entrega (todos menos el último, que regresa
        // a planta) suma los 15 min de tolerancia de descarga.
        $ordenEntregas = array_map(function($idxOriginal) use ($entregas) {
            return $entregas[$idxOriginal];
        }, $order);
        $puntos = array_merge(
            [['label' => 'Planta']],
            array_map(function($e) {
                return ['label' => $e['folio'] . ' — ' . $e['cliente_nombre']];
            }, $ordenEntregas),
            [['label' => 'Planta']]
        );
        $numLegs = count($legs);
        $tramos = [];
        foreach ($legs as $i => $leg) {
            $esParada = $i < $numLegs - 1;
            $tramos[] = [
                'desde'       => $puntos[$i]['label'],
                'hasta'       => $puntos[$i + 1]['label'],
                'min'         => round((int)($leg['duration'] ?? 0) / 60),
                'km'          => round((float)($leg['distanceMeters'] ?? 0) / 1000, 1),
                'espera_min'  => $esParada ? $TOLERANCIA_MIN : 0,
            ];
        }

        jsonResponse([
            'ok'          => true,
            'orden'       => $order,
            'tiempo_min'  => $totalMin,
            'antes_min'   => $baselineMin,
            'ahorro_min'  => max(0, $baselineMin - $totalMin),
            'paradas'     => count($entregas),
            'tramos'      => $tramos,
        ]);
        exit;
    }

    jsonResponse(['error' => 'Acción no reconocida']); exit;
}

jsonResponse(['error' => 'Método no soportado']);