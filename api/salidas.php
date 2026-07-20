<?php
// ============================================================
//  APEX GLASS - API Salidas / Entregas
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/wa_helper.php';
require_once __DIR__ . '/rutas_lib.php';
require_once __DIR__ . '/helpers/totales.php'; // A-2/A-8

requireSessionApi();
requirePermisoApi('registrar_entrega');

$metodo = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';
$db     = getDB();

// ── GET: piezas terminadas agrupadas por partida ──────────────────────────────
if ($metodo === 'GET' && $accion === 'piezas_terminadas') {
    $orden_id = (int)($_GET['orden_id'] ?? 0);
    if (!$orden_id) jsonResponse(['error' => 'orden_id requerido'], 400);

    $stmt = $db->prepare('
        SELECT p.id, p.partida, p.pieza_num, p.pieza_total,
               p.cristal_corto, p.ancho_mm, p.alto_mm, p.m2, p.estatus
        FROM piezas p
        WHERE p.orden_id = ?
        ORDER BY p.partida ASC, p.pieza_num ASC
    ');
    $stmt->execute([$orden_id]);
    $piezas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total      = count($piezas);
    $terminadas = count(array_filter($piezas, fn($p) => $p['estatus'] === 'terminado'));
    $entregadas = count(array_filter($piezas, fn($p) => $p['estatus'] === 'entregado'));

    jsonResponse([
        'ok'         => true,
        'piezas'     => $piezas,
        'total'      => $total,
        'terminadas' => $terminadas,
        'entregadas' => $entregadas,
    ]);
}

// ── POST: registrar salida ────────────────────────────────────────────────────
if ($metodo === 'POST' && $accion === 'registrar_salida') {
    $body          = json_decode(file_get_contents('php://input'), true) ?? [];
    $orden_id      = (int)($body['orden_id']      ?? 0);
    $cotizacion_id = (int)($body['cotizacion_id'] ?? 0);
    $pieza_ids     = array_map('intval', $body['pieza_ids'] ?? []);
    $tipo          = in_array($body['tipo'] ?? '', ['recoleccion', 'chofer']) ? $body['tipo'] : 'recoleccion';
    $fecha_chofer  = !empty($body['fecha_entrega_chofer']) ? $body['fecha_entrega_chofer'] : null;

    if (!$orden_id || !$cotizacion_id || empty($pieza_ids)) {
        jsonResponse(['error' => 'Datos incompletos'], 400);
    }

    // Confirmar que la cotizacion pertenece a la orden (evita IDOR con cotizacion_id stale)
    $stmtCv = $db->prepare('SELECT id FROM cotizaciones WHERE id = ? AND orden_id = ?');
    $stmtCv->execute([$cotizacion_id, $orden_id]);
    if (!$stmtCv->fetchColumn()) {
        jsonResponse(['error' => 'Cotización no corresponde a esta orden'], 400);
    }

    // A-8: barrera de cobro EN SERVIDOR (antes solo existía en el frontend).
    // Sale la mercancía si: pago completo (total canónico), o Finanzas autorizó
    // el estatus de pago (bitácora C-11), o dir_admin autorizó la entrega con adeudo.
    $stmtPago = $db->prepare("SELECT estatus_pago, COALESCE(saldo_pagado,0) AS saldo_pagado,
                                     entrega_bloqueada, entrega_autorizada_por
                              FROM cotizaciones WHERE id = ?");
    $stmtPago->execute([$cotizacion_id]);
    $cotPago     = $stmtPago->fetch(PDO::FETCH_ASSOC);
    $totalesCot  = apexTotalesCotizacion($db, $cotizacion_id);
    $totalCot    = $totalesCot ? $totalesCot['total'] : 0.0;
    $pagadoCot   = (float)$cotPago['saldo_pagado'];

    $pagoCompleto = $totalCot > 0 && $pagadoCot >= $totalCot - 0.99;
    $autorizada   = in_array($cotPago['estatus_pago'], ['en_proceso', 'pago_entrega', 'pagado'], true)
                    || ((int)$cotPago['entrega_bloqueada'] === 0 && !empty($cotPago['entrega_autorizada_por']));
    if (!$pagoCompleto && !$autorizada) {
        jsonResponse(['error' => 'Salida bloqueada: faltan $' . number_format(max(0, $totalCot - $pagadoCot), 2)
            . ' por cobrar. Finanzas debe registrar el pago o autorizar el estatus de pago.'], 422);
        exit;
    }

    // Validar que las piezas sean terminadas y de esta orden
    $ph   = implode(',', array_fill(0, count($pieza_ids), '?'));
    $stmt = $db->prepare("SELECT id FROM piezas WHERE id IN ($ph) AND orden_id = ? AND estatus = 'terminado'");
    $params = array_merge($pieza_ids, [$orden_id]);
    $stmt->execute($params);
    $piezas_validas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($piezas_validas)) {
        jsonResponse(['error' => 'No hay piezas válidas'], 400);
    }

    // Total de piezas de la orden y ya entregadas (una sola query)
    $stmt = $db->prepare("SELECT COUNT(*) AS total, SUM(estatus='entregado') AS ya_entregadas FROM piezas WHERE orden_id = ?");
    $stmt->execute([$orden_id]);
    $row           = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_piezas  = (int)$row['total'];
    $ya_entregadas = (int)$row['ya_entregadas'];

    $piezas_count            = count($piezas_validas);
    $total_tras_salida       = $ya_entregadas + $piezas_count;
    $es_parcial              = ($total_tras_salida < $total_piezas) ? 1 : 0;
    $orden_completa          = !$es_parcial;

    $ph2 = implode(',', array_fill(0, count($piezas_validas), '?'));

    $db->beginTransaction();
    try {
        // Marcar piezas seleccionadas como entregado (guard estatus previene TOCTOU)
        $db->prepare("UPDATE piezas SET estatus='entregado', updated_at=NOW() WHERE id IN ($ph2) AND orden_id = ? AND estatus='terminado'")
           ->execute([...$piezas_validas, $orden_id]);

        // Cerrar orden si todas están entregadas
        if ($orden_completa) {
            // tipo='chofer' (domicilio): aunque la orden ya cierra aquí para efectos de
            // reportes/dinero, todavía le falta el trayecto físico — se marca requiere_ruta
            // para que aparezca en "Pendientes de asignar" del módulo Rutas de Entrega.
            // tipo='recoleccion' (local, cliente pasa a la planta): no requiere ruta.
            $reqRuta = ($tipo === 'chofer') ? 1 : 0;
            $db->prepare("UPDATE ordenes SET estado='entregada', fecha_cierre=NOW(), fecha_entrega_chofer=?, requiere_ruta=? WHERE id=?")
               ->execute([$fecha_chofer, $reqRuta, $orden_id]);
        } elseif ($fecha_chofer && $tipo === 'chofer') {
            $db->prepare("UPDATE ordenes SET fecha_entrega_chofer=? WHERE id=?")
               ->execute([$fecha_chofer, $orden_id]);
        }

        // Si esta orden ya estaba asignada a una ruta (módulo Rutas de Entrega) y esa parada
        // seguía "pendiente", se cierra aquí — si no, se queda pegada ahí para siempre y la
        // orden nunca vuelve a aparecer en "Pendientes de asignar" para las piezas que faltan
        // (en salidas parciales) ni se puede re-planificar. Se marca 'entregado' porque lo que
        // SÍ estaba asignado a esa parada ya salió, aunque haya sido parcial.
        $stmtRE = $db->prepare("SELECT id, ruta_id FROM ruta_entregas WHERE orden_id = ? AND estado = 'pendiente'");
        $stmtRE->execute([$orden_id]);
        foreach ($stmtRE->fetchAll(PDO::FETCH_ASSOC) as $re) {
            $notaRE = $es_parcial ? "Salida parcial ($piezas_count/$total_piezas) impresa desde Cobranza" : 'Salida completa impresa desde Cobranza';
            $db->prepare("UPDATE ruta_entregas SET estado='entregado', entregado_at=NOW(), notas_entrega=? WHERE id=?")
               ->execute([$notaRE, $re['id']]);

            // Si ya no quedan paradas pendientes en esa ruta, se marca completada (mismo
            // criterio que api/rutas.php, accion=marcar_estado)
            $pendRuta = $db->prepare("SELECT COUNT(*) FROM ruta_entregas WHERE ruta_id=? AND estado='pendiente'");
            $pendRuta->execute([$re['ruta_id']]);
            if ((int)$pendRuta->fetchColumn() === 0) {
                $db->prepare("UPDATE rutas SET estado='completada', updated_at=NOW() WHERE id=?")
                   ->execute([$re['ruta_id']]);
            }
        }

        // Registrar evento de salida
        $db->prepare('
            INSERT INTO orden_salidas
              (orden_id, cotizacion_id, tipo, fecha_entrega_chofer, piezas_count, piezas_total, es_parcial, registrado_por, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ')->execute([$orden_id, $cotizacion_id, $tipo, $fecha_chofer, $piezas_count, $total_piezas, $es_parcial, $_SESSION['user_name'] ?? 'sistema']);

        $salida_id = (int)$db->lastInsertId();

        // Detalle de piezas por salida
        $stmtP = $db->prepare('INSERT INTO orden_salida_piezas (salida_id, pieza_id) VALUES (?,?)');
        foreach ($piezas_validas as $pid) {
            $stmtP->execute([$salida_id, $pid]);
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Error BD: ' . $e->getMessage()], 500);
    }

    // ── Enviar WA (fuera de transacción — error aquí no revierte la salida) ──────
    $wa_enviado = false;
    try {
    $stmt = $db->prepare('
        SELECT o.folio, o.cliente_id,
               COALESCE(cl.nombre, o.cliente_nombre) AS nombre,
               cl.telefono, cl.telefono_alterno
        FROM ordenes o
        LEFT JOIN clientes cl ON cl.id = o.cliente_id
        WHERE o.id = ?
    ');
    $stmt->execute([$orden_id]);
    $dw = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($dw) {
        $telRaw = preg_replace('/\D/', '', $dw['telefono_alterno'] ?: $dw['telefono'] ?? '');
        if ($telRaw && strlen($telRaw) >= 10) {
            if (strlen($telRaw) === 10) $telRaw = '52' . $telRaw;
            $nombre = substr(strip_tags($dw['nombre'] ?? 'Cliente'), 0, 60);
            $folio  = substr(strip_tags($dw['folio'] ?? ''), 0, 20);
            if ($fecha_chofer) {
                $meses = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
                $ts    = strtotime($fecha_chofer);
                $fecha_fmt = (int)date('j', $ts) . ' de ' . $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
            } else {
                $fecha_fmt = 'por confirmar';
            }

            if (!$es_parcial) {
                $tpl    = ($tipo === 'chofer') ? 'salida_domicilio' : 'salida_recoleccion';
                $params = ($tipo === 'chofer')
                    ? [['type'=>'text','text'=>$nombre], ['type'=>'text','text'=>$folio], ['type'=>'text','text'=>$fecha_fmt]]
                    : [['type'=>'text','text'=>$nombre], ['type'=>'text','text'=>$folio]];
            } else {
                $tpl    = ($tipo === 'chofer') ? 'salida_parcial_domicilio' : 'salida_parcial_recoleccion';
                $params = ($tipo === 'chofer')
                    ? [['type'=>'text','text'=>$nombre], ['type'=>'text','text'=>(string)$piezas_count], ['type'=>'text','text'=>$folio], ['type'=>'text','text'=>$fecha_fmt]]
                    : [['type'=>'text','text'=>$nombre], ['type'=>'text','text'=>(string)$piezas_count], ['type'=>'text','text'=>$folio]];
            }

            $resWa = enviarMensajeWA([
                'messaging_product' => 'whatsapp',
                'to'                => $telRaw,
                'type'              => 'template',
                'template'          => [
                    'name'       => $tpl,
                    'language'   => ['code' => 'es_MX'],
                    'components' => [['type' => 'body', 'parameters' => $params]],
                ],
            ]);

            $waId = $resWa['data']['messages'][0]['id'] ?? null;
            if ($resWa['code'] === 200 && $waId) {
                $wa_enviado = true;

                // Guardar en inbox
                $tel10   = substr($telRaw, -10);
                $stmtCv  = $db->prepare("SELECT id, cliente_id FROM whatsapp_conversaciones WHERE RIGHT(REGEXP_REPLACE(telefono,'[^0-9]',''),10)=?");
                $stmtCv->execute([$tel10]);
                $convRow = $stmtCv->fetch(PDO::FETCH_ASSOC);
                if (!$convRow) {
                    $db->prepare("INSERT INTO whatsapp_conversaciones (cliente_id,telefono,ultima_actividad) VALUES (?,?,NOW())")
                       ->execute([$dw['cliente_id'] ?? null, $telRaw]);
                    $convId = (int)$db->lastInsertId();
                } else {
                    $convId = (int)$convRow['id'];
                    if (!$convRow['cliente_id'] && !empty($dw['cliente_id'])) {
                        $db->prepare("UPDATE whatsapp_conversaciones SET cliente_id=? WHERE id=?")
                           ->execute([$dw['cliente_id'], $convId]);
                    }
                }
                $logTxt = '[Plantilla ' . $tpl . '] ' . $folio . ' — ' . $piezas_count . ' piezas entregadas';
                $db->prepare("INSERT INTO whatsapp_mensajes (conversacion_id,direccion,contenido,tipo,wa_message_id,enviado_por) VALUES (?,'outbound',?,'texto',?,'sistema')")
                   ->execute([$convId, $logTxt, $waId]);
                $db->prepare("UPDATE whatsapp_conversaciones SET ultima_actividad=NOW() WHERE id=?")
                   ->execute([$convId]);

                $db->prepare("UPDATE orden_salidas SET wa_enviado=1 WHERE id=?")
                   ->execute([$salida_id]);
            } else {
                error_log('APEX WA salida fallo: orden=' . ($dw['folio'] ?? '') . ' tpl=' . $tpl . ' resp=' . json_encode($resWa['data'] ?? []));
            }
        }
    }
    } catch (Exception $eWa) {
        error_log('APEX WA salida inbox error: orden_id=' . $orden_id . ' ' . $eWa->getMessage());
    }

    jsonResponse([
        'ok'            => true,
        'salida_id'     => $salida_id,
        'pieza_ids'     => $piezas_validas,
        'es_parcial'    => (bool)$es_parcial,
        'orden_cerrada' => $orden_completa,
        'wa_enviado'    => $wa_enviado,
    ]);
}

// ── POST: escaneo QR de la remisión (imprimir_salida.php) por el chofer al CARGAR el
// camión en planta. Marca las piezas de esa orden (ya asignadas a una ruta) como cargadas.
// El aviso "ya voy en camino" / "eres el siguiente" ahora lo dispara un QR distinto — el de
// la hoja de ruta impresa desde Logística Rutas — ver accion=scan_qr_ruta más abajo.
if ($metodo === 'POST' && $accion === 'scan_qr') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $orden_id = (int)($body['orden_id'] ?? 0);
    if (!$orden_id) jsonResponse(['error' => 'orden_id requerido'], 400);

    $stmt = $db->prepare("
        SELECT re.id as entrega_id, re.ruta_id, r.estado as ruta_estado
        FROM ruta_entregas re JOIN rutas r ON r.id = re.ruta_id
        WHERE re.orden_id = ? AND re.estado = 'pendiente'
        ORDER BY re.id DESC LIMIT 1
    ");
    $stmt->execute([$orden_id]);
    $re = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$re) {
        jsonResponse(['error' => 'Esta orden no está asignada a ninguna ruta todavía']); exit;
    }

    $db->prepare("
        UPDATE ruta_entrega_piezas
        SET cargado_at = NOW()
        WHERE ruta_entrega_id = ? AND cargado_at IS NULL
    ")->execute([$re['entrega_id']]);

    $prog = $db->prepare("SELECT COUNT(*) as total, SUM(cargado_at IS NOT NULL) as cargadas FROM ruta_entrega_piezas WHERE ruta_entrega_id = ?");
    $prog->execute([$re['entrega_id']]);
    $prog = $prog->fetch(PDO::FETCH_ASSOC);

    // Arranque automático: si con este escaneo ya quedaron cargadas TODAS las piezas de
    // TODAS las paradas pendientes de la ruta, ya no hace falta el botón "Iniciar Ruta" —
    // se marca en_ruta y se calcula el tiempo estimado aquí mismo.
    $rutaIniciada = false;
    if ($re['ruta_estado'] === 'planificada') {
        $chkFalta = $db->prepare("
            SELECT COUNT(*) FROM ruta_entrega_piezas rep
            JOIN ruta_entregas re2 ON re2.id = rep.ruta_entrega_id
            WHERE re2.ruta_id = ? AND re2.estado = 'pendiente' AND rep.cargado_at IS NULL
        ");
        $chkFalta->execute([$re['ruta_id']]);
        if ((int)$chkFalta->fetchColumn() === 0) {
            $db->prepare("UPDATE rutas SET estado='en_ruta', updated_at=NOW() WHERE id=?")->execute([$re['ruta_id']]);
            calcularYGuardarEtas($db, $re['ruta_id']);
            enviarAvisosInicioRuta($db, $re['ruta_id']);
            $rutaIniciada = true;
        }
    }

    jsonResponse([
        'ok'            => true,
        'tipo'          => 'cargado',
        'cargadas'      => (int)$prog['cargadas'],
        'total'         => (int)$prog['total'],
        'ruta_iniciada' => $rutaIniciada,
    ]);
}

// ── POST: escaneo QR de la hoja de ruta (app/imprimir_ruta.php) al CONFIRMAR LA ENTREGA en
// casa del cliente (ya no al salir hacia allá — cambio de flujo). Mismo efecto que el botón
// manual "Entregado" del chofer en Logística Rutas (api/rutas.php, accion=marcar_estado):
// cierra la parada/orden/piezas y avisa a la parada que queda de primera en la fila (ver
// marcarEntregaComoEntregada en rutas_lib.php, compartida entre ambos caminos).
if ($metodo === 'POST' && $accion === 'scan_qr_ruta') {
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $orden_id = (int)($body['orden_id'] ?? 0);
    if (!$orden_id) jsonResponse(['error' => 'orden_id requerido'], 400);

    $chofer_id     = (int)($_SESSION['user_id'] ?? 0);
    $chofer_nombre = trim($_SESSION['user_name'] ?? 'Chofer');

    $stmt = $db->prepare("
        SELECT re.id as entrega_id, o.folio
        FROM ruta_entregas re
        JOIN ordenes o ON o.id = re.orden_id
        WHERE re.orden_id = ? AND re.estado = 'pendiente'
        ORDER BY re.id DESC LIMIT 1
    ");
    $stmt->execute([$orden_id]);
    $re = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$re) {
        // Ya no hay parada pendiente para esta orden: o ya se marcó entregada antes (doble
        // escaneo, o ya se marcó desde el botón manual en Logística Rutas) o nunca se asignó
        // a una ruta — cualquiera de los dos casos se reporta igual, sin bloquear al chofer.
        jsonResponse(['ok' => true, 'ya_escaneado' => true]); exit;
    }

    $r = marcarEntregaComoEntregada($db, $re['entrega_id'], 'Entrega confirmada por QR — ' . $chofer_nombre);

    // ON DUPLICATE KEY UPDATE en vez de INSERT simple: orden_id es UNIQUE (un registro por
    // orden), pero una parada se puede regresar a "pendiente" para corregir y volver a
    // escanearse — sin esto, el segundo escaneo truena con error de llave duplicada aunque
    // marcarEntregaComoEntregada() de arriba ya haya corrido bien.
    $db->prepare('
        INSERT INTO orden_salida_escaneos (orden_id, chofer_usuario_id, tipo, created_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE chofer_usuario_id = VALUES(chofer_usuario_id), tipo = VALUES(tipo), created_at = NOW()
    ')->execute([$orden_id, $chofer_id, 'entregado']);

    jsonResponse([
        'ok'              => (bool)($r['ok'] ?? false),
        'folio'           => $re['folio'],
        'ruta_completada' => (bool)($r['ruta_completada'] ?? false),
    ]);
}

jsonResponse(['error' => 'Acción no válida'], 400);
