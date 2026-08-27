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

// ── Helper: peso estimado de lo que FALTA por rutear de una orden ────────────
// Solo piezas con salida tipo 'chofer' registrada que no están ya en una parada
// viva ('pendiente'/'entregado') — mismo criterio que accion=pendientes (UPD-395).
// rep.estado != 'rechazada' (UPD-399): una pieza que el cliente NO aceptó en una
// entrega parcial en puerta sigue libre para re-rutear aunque su parada ya haya
// cerrado como 'entregado' (parcial) por las demás piezas que sí se quedó.
function calcularPesoPendienteRutear($db, $orden_id) {
    $stmt = $db->prepare("
        SELECT p.ancho_mm, p.alto_mm, p.cristal
        FROM piezas p
        JOIN orden_salida_piezas osp ON osp.pieza_id = p.id
        JOIN orden_salidas os ON os.id = osp.salida_id AND os.tipo = 'chofer'
        WHERE p.orden_id = ?
          AND p.id NOT IN (
              SELECT rep.pieza_id FROM ruta_entrega_piezas rep
              JOIN ruta_entregas re ON re.id = rep.ruta_entrega_id
              WHERE re.estado IN ('pendiente','entregado') AND rep.estado != 'rechazada'
          )
    ");
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
        // Criterio POR PIEZA (UPD-395, antes era por orden completa): una orden aparece si
        // tiene piezas con salida tipo 'chofer' registrada en Cobranza que aún no están en
        // ninguna parada viva ('pendiente' o 'entregado' — las de paradas 'no_entregado' se
        // liberan para re-rutear), y no tiene ya otra parada pendiente (una parada viva a la
        // vez por orden, C-14). Así una orden con salidas PARCIALES reaparece sola cuando
        // Cobranza registra la siguiente tanda, y desaparece cuando todo quedó ruteado.
        // Orden: primero 'activa' (aún con piezas por salir) y luego 'entregada'; dentro de
        // cada grupo, fecha de entrega prometida más antigua primero.
        $stmt = $db->prepare("
            SELECT o.id, o.folio, o.cliente_id, o.cliente_nombre, o.asesor, o.fecha_entrega,
                   o.estado, c.localidad, c.ciudad_destino
            FROM ordenes o
            LEFT JOIN cotizaciones c ON c.orden_id = o.id
            WHERE o.requiere_ruta = 1
              AND o.estado != 'cancelada'
              AND NOT EXISTS (
                  SELECT 1 FROM ruta_entregas re
                  WHERE re.orden_id = o.id AND re.estado = 'pendiente'
              )
              AND EXISTS (
                  SELECT 1
                  FROM orden_salida_piezas osp
                  JOIN orden_salidas os ON os.id = osp.salida_id
                  WHERE os.orden_id = o.id AND os.tipo = 'chofer'
                    AND osp.pieza_id NOT IN (
                        SELECT rep.pieza_id FROM ruta_entrega_piezas rep
                        JOIN ruta_entregas re2 ON re2.id = rep.ruta_entrega_id
                        WHERE re2.estado IN ('pendiente','entregado') AND rep.estado != 'rechazada'
                    )
              )
            ORDER BY (o.estado = 'entregada') ASC, o.fecha_entrega ASC, o.id ASC
        ");
        $stmt->execute();
        $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($ordenes as &$o) {
            $o['peso_kg'] = calcularPesoPendienteRutear($db, $o['id']);
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
                SELECT re.*, re.estado as entrega_estado, o.folio, o.cliente_id, o.cliente_nombre, o.estado as orden_estado,
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
        // UPD-395: solo se ofrecen piezas con salida tipo 'chofer' ya registrada en Cobranza
        // (la salida es la barrera de cobro, A-8) que no estén ya en una parada viva
        // ('pendiente'/'entregado' — las de paradas 'no_entregado' se liberan para re-rutear).
        // Antes listaba TODA pieza terminado/entregado de la orden, lo que permitía volver a
        // asignar piezas de una tanda anterior ya ruteada en salidas parciales.
        $stmt = $db->prepare("
            SELECT p.id, p.partida, p.pieza_num, p.pieza_total,
                   p.qr_code, p.cristal_corto, p.ancho_mm, p.alto_mm, p.estatus
            FROM piezas p
            JOIN orden_salida_piezas osp ON osp.pieza_id = p.id
            JOIN orden_salidas os ON os.id = osp.salida_id AND os.tipo = 'chofer'
            WHERE p.orden_id = ?
              AND p.id NOT IN (
                  SELECT rep.pieza_id FROM ruta_entrega_piezas rep
                  JOIN ruta_entregas re ON re.id = rep.ruta_entrega_id
                  WHERE re.estado IN ('pendiente','entregado') AND rep.estado != 'rechazada'
              )
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

    if ($accion === 'liberar_pendiente') {
        // Quita una orden de la cola de "pendientes de rutear" sin crear una ruta
        // falsa — para órdenes que ya se entregaron físicamente fuera del flujo
        // formal de rutas (entrega informal/directa) y se quedaron atoradas para
        // siempre porque requiere_ruta nunca se apaga solo. Solo dir_admin/dueno
        // (o administracion — Lina) pueden usarla; deja rastro de quién y cuándo.
        if (!in_array($rol, ['dir_admin', 'dueno', 'administracion', 'desarrollo'])) {
            jsonResponse(['error' => 'Sin permiso']); exit;
        }
        $orden_id = (int)($body['orden_id'] ?? 0);
        $nota     = trim($body['nota'] ?? '');
        if (!$orden_id) { jsonResponse(['error' => 'Falta orden_id']); exit; }

        $stmtOrd = $db->prepare("SELECT id, folio, estado, requiere_ruta FROM ordenes WHERE id = ?");
        $stmtOrd->execute([$orden_id]);
        $ordenRow = $stmtOrd->fetch(PDO::FETCH_ASSOC);
        if (!$ordenRow) { jsonResponse(['error' => 'Orden no encontrada']); exit; }
        if (!$ordenRow['requiere_ruta']) { jsonResponse(['error' => 'Esta orden ya no está en la cola de pendientes']); exit; }

        $stmtChk = $db->prepare("SELECT COUNT(*) FROM ruta_entregas WHERE orden_id = ? AND estado = 'pendiente'");
        $stmtChk->execute([$orden_id]);
        if ((int)$stmtChk->fetchColumn() > 0) {
            jsonResponse(['error' => 'Esta orden ya tiene una parada pendiente en una ruta activa; complétala o cancélala desde ahí']); exit;
        }

        $db->prepare("UPDATE ordenes SET requiere_ruta = 0 WHERE id = ?")->execute([$orden_id]);
        $obs = 'Liberada de cola de rutas por ' . $nombre . ' (' . date('Y-m-d H:i') . ')' . ($nota !== '' ? ': ' . $nota : '');
        $db->prepare("UPDATE ordenes SET observaciones = TRIM(CONCAT(COALESCE(observaciones,''), '\n', ?)) WHERE id = ?")
           ->execute([$obs, $orden_id]);

        jsonResponse(['ok' => true, 'folio' => $ordenRow['folio']]); exit;
    }

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
        $pieza_ids   = array_values(array_unique(array_map('intval', (array)($body['pieza_ids'] ?? []))));
        if (!$ruta_id || !$orden_id) {
            jsonResponse(['error' => 'Datos incompletos']); exit;
        }
        if (empty($pieza_ids)) {
            jsonResponse(['error' => 'Debes seleccionar al menos una pieza']); exit;
        }

        // ── Validaciones C-14 ────────────────────────────────────────
        // 1. La ruta existe y sigue en planeación (no meter paradas a una
        //    ruta que ya salió o ya completó).
        $stmtRuta = $db->prepare("SELECT id, unidad, estado FROM rutas WHERE id = ? AND archivada = 0");
        $stmtRuta->execute([$ruta_id]);
        $rutaRow = $stmtRuta->fetch(PDO::FETCH_ASSOC);
        if (!$rutaRow) {
            jsonResponse(['error' => 'Ruta no encontrada']); exit;
        }
        if ($rutaRow['estado'] !== 'planificada') {
            jsonResponse(['error' => 'Solo se puede asignar a rutas en planeación (estado actual: ' . $rutaRow['estado'] . ')']); exit;
        }

        // 2. La orden existe, no está cancelada y no tiene otra parada
        //    pendiente (una cancelada en ruta "resucitaba" como entregada).
        $stmtOrd = $db->prepare("SELECT id, folio, estado FROM ordenes WHERE id = ?");
        $stmtOrd->execute([$orden_id]);
        $ordenRow = $stmtOrd->fetch(PDO::FETCH_ASSOC);
        if (!$ordenRow) {
            jsonResponse(['error' => 'Orden no encontrada']); exit;
        }
        if ($ordenRow['estado'] === 'cancelada') {
            jsonResponse(['error' => 'La orden ' . $ordenRow['folio'] . ' está cancelada; no se puede asignar a ruta']); exit;
        }
        $stmtOtra = $db->prepare("SELECT COUNT(*) FROM ruta_entregas WHERE orden_id = ? AND estado = 'pendiente'");
        $stmtOtra->execute([$orden_id]);
        if ((int)$stmtOtra->fetchColumn() > 0) {
            jsonResponse(['error' => 'La orden ' . $ordenRow['folio'] . ' ya tiene una parada pendiente en una ruta']); exit;
        }

        // 3. Las piezas deben ser de ESTA orden y tener salida tipo 'chofer' ya
        //    registrada en Cobranza (la salida es la barrera de cobro, A-8) —
        //    mismo criterio que el GET accion=piezas_orden (UPD-395).
        $ph    = implode(',', array_fill(0, count($pieza_ids), '?'));
        $stmtVal = $db->prepare("
            SELECT COUNT(*) FROM piezas p
            JOIN orden_salida_piezas osp ON osp.pieza_id = p.id
            JOIN orden_salidas os ON os.id = osp.salida_id AND os.tipo = 'chofer'
            WHERE p.id IN ($ph) AND p.orden_id = ?
        ");
        $stmtVal->execute([...$pieza_ids, $orden_id]);
        if ((int)$stmtVal->fetchColumn() !== count($pieza_ids)) {
            jsonResponse(['error' => 'Hay piezas que no pertenecen a la orden o que aún no tienen salida registrada en Cobranza']); exit;
        }

        // 4. Ninguna pieza puede estar ya en una parada viva — pendiente O ya
        //    entregada (UPD-395: antes solo checaba 'pendiente', lo que permitía
        //    re-rutear piezas ya entregadas de una tanda anterior).
        $stmtDup = $db->prepare("
            SELECT COUNT(*) FROM ruta_entrega_piezas rep
            JOIN ruta_entregas re ON re.id = rep.ruta_entrega_id
            WHERE rep.pieza_id IN ($ph) AND re.estado IN ('pendiente','entregado') AND rep.estado != 'rechazada'
        ");
        $stmtDup->execute($pieza_ids);
        if ((int)$stmtDup->fetchColumn() > 0) {
            jsonResponse(['error' => 'Alguna pieza ya está asignada a otra parada o ya fue entregada en una ruta']); exit;
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
        $cap = ['gris' => 1500, 'blanca' => 700];
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

        // LN-12: claim atómico — sin condicionar el estado actual se podía "iniciar"
        // una ruta que ya estaba 'en_ruta' o 'completada' (doble clic / reintento),
        // reenviando a los clientes el WhatsApp de "tu pedido va en camino" de forma
        // espuria. Mismo patrón que VoBo/campañas del sprint P0.
        $stUpd = $db->prepare("UPDATE rutas SET estado='en_ruta', updated_at=NOW() WHERE id=? AND estado='planificada'");
        $stUpd->execute([$id]);
        if ($stUpd->rowCount() !== 1) {
            jsonResponse(['error' => 'Esta ruta ya se inició o cambió de estado — recarga antes de reintentar.'], 409); exit;
        }

        $etas = calcularYGuardarEtas($db, $id);
        enviarAvisosInicioRuta($db, $id);
        jsonResponse(['ok' => true, 'etas' => $etas]); exit;
    }

    if ($accion === 'recalcular_eta') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $id = (int)($body['ruta_id'] ?? 0);
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
        $etas = calcularYGuardarEtas($db, $id);
        jsonResponse(['ok' => true, 'etas' => $etas]); exit;
    }

    if ($accion === 'entrega_parcial') {
        // Entrega parcial en puerta (UPD-399): el chofer sí llegó y el cliente se quedó con
        // algunas piezas, pero regresó otras (defecto, no era lo que pidió, etc.) — distinto de
        // 'no_entregado' (nada se aceptó). Solo desde el panel de Logística (Armando/Lina, con
        // lo que les reporta el chofer), no desde el celular del chofer.
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $entrega_id = (int)($body['entrega_id'] ?? 0);
        $rechazadas = array_values(array_unique(array_map('intval', (array)($body['pieza_ids_rechazadas'] ?? []))));
        $notas      = trim($body['notas_entrega'] ?? '');
        if (!$entrega_id) { jsonResponse(['error' => 'Datos incompletos']); exit; }

        $reCheck = $db->prepare("SELECT estado FROM ruta_entregas WHERE id=?");
        $reCheck->execute([$entrega_id]);
        $reCheck = $reCheck->fetch(PDO::FETCH_ASSOC);
        if (!$reCheck) { jsonResponse(['error' => 'Entrega no encontrada'], 404); exit; }
        if ($reCheck['estado'] !== 'pendiente') {
            jsonResponse(['error' => 'Esta parada ya no está pendiente (estado actual: ' . $reCheck['estado'] . ')']); exit;
        }

        $totStmt = $db->prepare("SELECT COUNT(*) FROM ruta_entrega_piezas WHERE ruta_entrega_id=? AND estado='asignada'");
        $totStmt->execute([$entrega_id]);
        $total = (int)$totStmt->fetchColumn();
        if ($total === 0) { jsonResponse(['error' => 'Esta parada no tiene piezas asignadas']); exit; }
        if (!empty($rechazadas) && count($rechazadas) >= $total) {
            jsonResponse(['error' => 'Estás rechazando todas las piezas de esta parada — usa el botón "No entregado" en vez de Entrega parcial']); exit;
        }

        $r = marcarEntregaComoEntregada($db, $entrega_id, $notas, $rechazadas);
        jsonResponse([
            'ok' => (bool)($r['ok'] ?? false),
            'etas' => $r['etas'] ?? [],
            'ruta_completada' => $r['ruta_completada'] ?? false,
            'piezas_rechazadas' => $r['piezas_rechazadas'] ?? 0,
        ]); exit;
    }

    if ($accion === 'marcar_estado') {
        $entrega_id = (int)($body['entrega_id'] ?? 0);
        $estado     = $body['estado'] ?? '';
        $notas      = trim($body['notas_entrega'] ?? '');
        $motivo     = trim($body['motivo'] ?? '');
        if (!$entrega_id || !in_array($estado, ['entregado','no_entregado','pendiente'])) {
            jsonResponse(['error' => 'Datos inválidos']); exit;
        }
        // BLO-04: la reversa a 'pendiente' de una parada ya 'entregado' ("deshacer
        // entrega") des-entrega mercancía potencialmente ya cobrada — antes cualquier
        // chofer podía hacerlo vía API directa sobre su propia ruta sin motivo ni
        // bitácora. Se restringe a roles de logística y se exige motivo (el log a
        // correcciones_log se hace más abajo, junto al resto de la reversa).
        if ($esChofer) {
            $own = $db->prepare("SELECT r.chofer FROM ruta_entregas re JOIN rutas r ON r.id = re.ruta_id WHERE re.id = ?");
            $own->execute([$entrega_id]);
            $own = $own->fetch(PDO::FETCH_ASSOC);
            if (!$own || $own['chofer'] !== $nombre) {
                jsonResponse(['error' => 'Sin permiso sobre esta entrega'], 403);
            }
            if ($estado === 'pendiente') {
                jsonResponse(['error' => 'Solo Logística puede revertir una entrega ya confirmada. Repórtalo para que lo corrijan.'], 403); exit;
            }
        }
        if ($estado === 'pendiente' && $motivo === '') {
            jsonResponse(['error' => 'Indica el motivo de la reversa']); exit;
        }
        // 'entregado' cierra orden/piezas, avanza el mapa y avisa a la siguiente parada — misma
        // lógica que dispara el escaneo del QR de hoja de ruta (ver marcarEntregaComoEntregada
        // en rutas_lib.php, compartida entre ambos caminos).
        if ($estado === 'entregado') {
            $r = marcarEntregaComoEntregada($db, $entrega_id, $notas);
            jsonResponse(['ok' => (bool)($r['ok'] ?? false), 'etas' => $r['etas'] ?? []]); exit;
        }

        // C-14b: estado actual de la parada (para reversa e idempotencia)
        $reAntes = $db->prepare("SELECT re.estado, re.orden_id, re.ruta_id, o.folio AS orden_folio FROM ruta_entregas re JOIN ordenes o ON o.id = re.orden_id WHERE re.id=?");
        $reAntes->execute([$entrega_id]);
        $reAntes = $reAntes->fetch(PDO::FETCH_ASSOC);
        if (!$reAntes) { jsonResponse(['error' => 'Entrega no encontrada'], 404); exit; }
        if ($reAntes['estado'] === $estado) { jsonResponse(['ok' => true, 'etas' => []]); exit; }

        // [V3-C5 fix] Candado de integridad: 'no_entregado' asume que las piezas de
        // esta parada NUNCA se marcaron 'entregado' (es el flujo normal — el botón
        // solo se ofrece desde 'pendiente', antes de que el chofer llegue). Pero si
        // ya están 'entregado' por otra vía (una salida de Cobranza, un escaneo
        // directo, o una parada vieja que quedó desincronizada), marcar "no
        // entregado" aquí encima sería falso — la mercancía sí llegó/se cobró — y
        // punto de quiebre de auditoria_e2e_v3.md V3-C5: el resto del sistema
        // (portal, Cobranza) seguiría diciendo "entregado" sin que nadie se
        // enterara de la contradicción. Se bloquea con un mensaje claro en vez de
        // fallar en silencio.
        if ($estado === 'no_entregado') {
            $chk = $db->prepare("
                SELECT COUNT(*) FROM ruta_entrega_piezas rep
                JOIN piezas p ON p.id = rep.pieza_id
                WHERE rep.ruta_entrega_id = ? AND rep.estado = 'asignada' AND p.estatus = 'entregado'
            ");
            $chk->execute([$entrega_id]);
            if ((int)$chk->fetchColumn() > 0) {
                jsonResponse(['error' => 'Las piezas de esta parada ya están marcadas como entregadas por otra vía (Cobranza/escaneo) — no se puede marcar "no entregado". Repórtalo para revisar manualmente.']); exit;
            }
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE ruta_entregas SET estado=?, entregado_at=NULL, notas_entrega=? WHERE id=?")
               ->execute([$estado, $notas, $entrega_id]);

            if ($estado === 'pendiente' && $reAntes['estado'] === 'entregado') {
                // "Deshacer": se revierten SOLO las piezas de esta parada y se
                // reabre la orden únicamente si la cerró la ruta. Si la cerró
                // Cobranza (fecha_cierre puesta en salidas.php) o la pieza ya
                // tiene salida registrada (orden_salida_piezas), no se toca.
                $db->prepare("UPDATE ruta_entrega_piezas SET estado='asignada' WHERE ruta_entrega_id=? AND estado='entregada'")
                   ->execute([$entrega_id]);

                $ord = $db->prepare("SELECT estado, fecha_cierre FROM ordenes WHERE id=?");
                $ord->execute([$reAntes['orden_id']]);
                $ord = $ord->fetch(PDO::FETCH_ASSOC);

                $cerradaPorCobranza = $ord && $ord['estado'] === 'entregada' && !empty($ord['fecha_cierre']);
                if (!$cerradaPorCobranza) {
                    $db->prepare("
                        UPDATE piezas p
                        JOIN ruta_entrega_piezas rep ON rep.pieza_id = p.id
                        LEFT JOIN orden_salida_piezas osp ON osp.pieza_id = p.id
                        SET p.estatus='terminado', p.updated_at=NOW()
                        WHERE rep.ruta_entrega_id=? AND rep.estado='asignada'
                          AND p.estatus='entregado' AND osp.pieza_id IS NULL
                    ")->execute([$entrega_id]);
                    if ($ord && $ord['estado'] === 'entregada') {
                        $db->prepare("UPDATE ordenes SET estado='activa', updated_at=NOW() WHERE id=?")
                           ->execute([$reAntes['orden_id']]);
                    }
                }

                // Si la ruta ya había completado, reabre al deshacer una parada
                $db->prepare("UPDATE rutas SET estado='en_ruta', updated_at=NOW() WHERE id=? AND estado='completada'")
                   ->execute([$reAntes['ruta_id']]);

                // BLO-04: bitácora de la reversa — antes no quedaba ningún rastro de
                // quién revirtió una entrega ya confirmada, ni por qué.
                $db->prepare("INSERT INTO correcciones_log
                    (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, motivo, usuario, fecha)
                    VALUES ('orden', ?, ?, 'reversa_entrega_ruta', 'entregado', 'pendiente', ?, ?, NOW())")
                   ->execute([$reAntes['orden_id'], $reAntes['orden_folio'], $motivo, $nombre]);
            }

            $ruta_completada = false;
            if ($estado === 'no_entregado') {
                // Ya no cierra la ruta aquí — se queda 'en_ruta' hasta que el GPS confirme
                // el regreso a planta (scripts/gps_tracker.php). $ruta_completada solo indica
                // "sin paradas pendientes" para el mensaje al usuario (ver S-375, ruta 44).
                $pend = $db->prepare("SELECT SUM(estado='pendiente') as p FROM ruta_entregas WHERE ruta_id=?");
                $pend->execute([$reAntes['ruta_id']]);
                $pend = $pend->fetch(PDO::FETCH_ASSOC);
                if ((int)($pend['p'] ?? 0) === 0) {
                    $ruta_completada = true;
                }
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Error al actualizar la entrega'], 500); exit;
        }
        jsonResponse(['ok' => true, 'etas' => [], 'ruta_completada' => $ruta_completada]); exit;
    }

    if ($accion === 'confirmar_regreso') {
        // Respaldo manual: la ruta se cierra sola cuando el GPS detecta al chofer a
        // <= RADIO_LLEGADA_M de la planta (scripts/gps_tracker.php, corre cada minuto).
        // Si el GPS falla/no responde (ProTrack365 sin Open API oficial, ver CLAUDE.md),
        // esto evita que la ruta quede 'en_ruta' para siempre sin poder Finalizarse.
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $ruta_id = (int)($body['ruta_id'] ?? 0);
        if (!$ruta_id) { jsonResponse(['error' => 'ID requerido']); exit; }

        $stmt = $db->prepare("SELECT estado FROM rutas WHERE id=?");
        $stmt->execute([$ruta_id]);
        $estadoActual = $stmt->fetchColumn();
        if ($estadoActual === false) { jsonResponse(['error' => 'Ruta no encontrada']); exit; }
        if ($estadoActual !== 'en_ruta') { jsonResponse(['error' => 'Solo aplica a una ruta en curso']); exit; }

        $pend = $db->prepare("SELECT COUNT(*) FROM ruta_entregas WHERE ruta_id=? AND estado='pendiente'");
        $pend->execute([$ruta_id]);
        if ((int)$pend->fetchColumn() > 0) {
            jsonResponse(['error' => 'Todavía hay paradas pendientes de entregar']); exit;
        }

        // archivada=1 de una vez — un solo clic de respaldo, sin pedir un segundo clic en
        // "Finalizar" (mismo criterio que el cierre automático de scripts/gps_tracker.php).
        $db->prepare("UPDATE rutas SET estado='completada', regreso_planta_at=NOW(), archivada=1, updated_at=NOW() WHERE id=?")
           ->execute([$ruta_id]);
        jsonResponse(['ok' => true]); exit;
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

        // BLO-05: la parada debe seguir 'pendiente' — evita escanear piezas de una
        // parada ya cerrada (entregado/no_entregado) que quedó desincronizada.
        $paradaChk = $db->prepare("SELECT estado FROM ruta_entregas WHERE id=?");
        $paradaChk->execute([$entrega_id]);
        $paradaEstado = $paradaChk->fetchColumn();
        if ($paradaEstado !== 'pendiente') {
            jsonResponse(['error' => 'Esta parada ya no está pendiente (estado actual: ' . $paradaEstado . ')']); exit;
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

        // BLO-05: las 6+ escrituras de este flujo corrían sin transacción — un fallo
        // de red/batería a media secuencia dejaba pieza/parada/orden desincronizados.
        $db->beginTransaction();
        try {
            $rechazada_at = $estado === 'rechazada' ? date('Y-m-d H:i:s') : null;
            $db->prepare("UPDATE ruta_entrega_piezas SET estado=?, rechazada_at=? WHERE id=?")
               ->execute([$estado, $rechazada_at, $pieza['id']]);

            // Si entregada, marcar pieza como entregado — BLO-05: antes se marcaba
            // desde CUALQUIER estatus previo (a diferencia de rutas_lib.php:286, que
            // exige 'terminado'); un QR mal asignado a una pieza aún en producción
            // podía "entregarse" sola por un escaneo de ruta.
            if ($estado === 'entregada') {
                $stPz = $db->prepare("UPDATE piezas SET estatus='entregado', updated_at=NOW() WHERE id=? AND estatus='terminado'");
                $stPz->execute([$pieza['pieza_id']]);
                if ($stPz->rowCount() === 0) {
                    throw new Exception('La pieza no está en estatus "terminado" (sigue en producción) — no se puede marcar como entregada. Verifica el QR.', 422);
                }
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
                        // LN-4: homologado con rutas_lib.php:300 — sin este guard una orden
                        // cancelada/rechazada mientras la ruta seguía viva podía "resucitar"
                        // a entregada solo por terminar de escanear sus piezas (entrega física
                        // + crédito ya dado al cliente al mismo tiempo).
                        $stCierre = $db->prepare("UPDATE ordenes SET estado='entregada', updated_at=NOW() WHERE id=? AND estado NOT IN ('cancelada','rechazada')");
                        $stCierre->execute([$re['orden_id']]);
                        $respuesta['orden_cerrada'] = $stCierre->rowCount() === 1;
                    }
                }

                // Ya no cierra la ruta aquí aunque no queden paradas pendientes — se queda 'en_ruta'
                // hasta que el GPS confirme el regreso a planta (scripts/gps_tracker.php).
            }

            $db->commit();
            jsonResponse($respuesta); exit;
        } catch (Exception $e) {
            $db->rollBack();
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            jsonResponse(['error' => $e->getMessage()], $code); exit;
        }
    }

    if ($accion === 'quitar') {
        if (!$esLogistica) { jsonResponse(['error' => 'Sin permiso']); exit; }
        $id = (int)($body['entrega_id'] ?? 0);
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

        // ── A-14: solo se quitan paradas pendientes ────────────────────
        // Antes borraba cualquier parada sin checar estado y sin transacción:
        // si el chofer ya la había marcado, se perdía el historial de la
        // entrega (mismo criterio de protección que 'eliminar_ruta').
        $stmtE = $db->prepare("SELECT estado FROM ruta_entregas WHERE id=?");
        $stmtE->execute([$id]);
        $entrega = $stmtE->fetch(PDO::FETCH_ASSOC);
        if (!$entrega) { jsonResponse(['error' => 'Entrega no encontrada'], 404); exit; }
        if ($entrega['estado'] !== 'pendiente') {
            jsonResponse(['error' => "Solo se pueden quitar paradas pendientes (estado actual: {$entrega['estado']}); el historial de entregas no se borra"], 409); exit;
        }

        $db->beginTransaction();
        try {
            // Limpiar piezas primero
            $db->prepare("DELETE FROM ruta_entrega_piezas WHERE ruta_entrega_id=?")->execute([$id]);
            // El AND estado='pendiente' es segunda línea de defensa: si el
            // chofer la marcó entre el SELECT y aquí, el DELETE no la toca.
            $db->prepare("DELETE FROM ruta_entregas WHERE id=? AND estado='pendiente'")->execute([$id]);
            $db->commit();
            jsonResponse(['ok' => true]); exit;
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'Error al quitar la parada'], 500);
        }
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