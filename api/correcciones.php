<?php
// ============================================================
//  APEX GLASS - API: Correcciones por dir_admin
//  Archivo: api/correcciones.php
//  GET  ?cotizacion_id=X  → historial de correcciones
//  POST { cotizacion_id, motivo, cambios_header, cambios_partidas }
// ============================================================
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

// Solo dir_admin
if (empty($_SESSION['user_id']) || ($_SESSION['user_rol'] ?? '') !== 'dir_admin') {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$usuario = $_SESSION['user_name'] ?? 'dir_admin';
$method  = $_SERVER['REQUEST_METHOD'];
$db      = getDB();

// ── GET → historial ──────────────────────────────────────────────────────────
if ($method === 'GET') {
    $cot_id = (int)($_GET['cotizacion_id'] ?? 0);
    if (!$cot_id) jsonResponse(['error' => 'cotizacion_id requerido'], 422);

    $stmt = $db->prepare("
        SELECT id, campo, valor_anterior, valor_nuevo, motivo, usuario, fecha
        FROM correcciones_log
        WHERE tipo = 'cotizacion' AND referencia_id = ?
        ORDER BY fecha DESC
        LIMIT 300
    ");
    $stmt->execute([$cot_id]);
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

// ── POST → aplicar corrección ────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?: [];
    $cot_id = (int)($body['cotizacion_id'] ?? 0);
    $motivo = trim($body['motivo'] ?? '');

    if (!$cot_id || !$motivo) {
        jsonResponse(['error' => 'cotizacion_id y motivo son requeridos'], 422);
    }

    // Cargar cotización actual
    $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE id = ?");
    $stmt->execute([$cot_id]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cot) jsonResponse(['error' => 'Cotización no encontrada'], 404);

    $folio      = $cot['folio'];
    $es_orden   = ($cot['estatus'] === 'orden');
    $cambios    = 0;

    $db->beginTransaction();
    try {

        // ── Cambios encabezado ────────────────────────────────────────────────
        $campos_header  = ['descuento', 'condicion_pago', 'fecha_entrega', 'alerta', 'cliente_nombre', 'factura_tipo'];
        $updates_header = [];
        $params_header  = [];
        $cambio_descuento = null;

        foreach ($campos_header as $campo) {
            $hdr = $body['cambios_header'] ?? [];
            if (!array_key_exists($campo, $hdr)) continue;

            $nuevo    = $hdr[$campo];
            $anterior = $cot[$campo] ?? '';

            if ($campo === 'factura_tipo') {
                $nuevo = ($nuevo === 'generica') ? 'generica' : '';
            }
            if ($campo === 'cliente_nombre') {
                $nuevo = trim((string)$nuevo);
                if ($nuevo === '') continue; // no permitir dejar la factura sin nombre
            }

            if ((string)$anterior === (string)$nuevo) continue;

            $updates_header[] = "$campo = ?";
            $params_header[]  = $nuevo;

            if ($campo === 'descuento') {
                $cambio_descuento = (float)$nuevo;
            }

            $db->prepare("
                INSERT INTO correcciones_log
                    (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, motivo, usuario)
                VALUES ('cotizacion', ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$cot_id, $folio, $campo, $anterior, $nuevo, $motivo, $usuario]);
            $cambios++;
        }

        if ($updates_header) {
            $params_header[] = $cot_id;
            $db->prepare("UPDATE cotizaciones SET " . implode(', ', $updates_header) . ", updated_at = NOW() WHERE id = ?")
               ->execute($params_header);
        }

        // Si cambió descuento → recalcular precio_unitario de todas las partidas
        if ($cambio_descuento !== null) {
            $sp = $db->prepare("SELECT * FROM cotizaciones_partidas WHERE cotizacion_id = ?");
            $sp->execute([$cot_id]);
            $todas = $sp->fetchAll(PDO::FETCH_ASSOC);

            foreach ($todas as $p) {
                $m2         = (float)$p['m2'];
                $precio_m2  = (float)$p['precio_m2_usado'];
                $cantidad   = (int)$p['cantidad'];
                $nuevo_unit = round($m2 * $precio_m2 * (1 - $cambio_descuento / 100), 4);
                $subtotal_p = round($nuevo_unit * $cantidad, 2);
                $iva_p      = round($subtotal_p * 0.16, 2);
                $total_p    = round($subtotal_p + $iva_p, 2);

                $anterior_unit = (float)$p['precio_unitario'];
                if (round($anterior_unit, 4) !== round($nuevo_unit, 4)) {
                    $db->prepare("
                        INSERT INTO correcciones_log
                            (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, motivo, usuario)
                        VALUES ('cotizacion', ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $cot_id, $folio,
                        'P' . $p['num_partida'] . '.precio_unitario',
                        number_format($anterior_unit, 4),
                        number_format($nuevo_unit, 4),
                        'Recalculado por cambio de descuento a ' . $cambio_descuento . '%',
                        $usuario,
                    ]);
                }

                $db->prepare("
                    UPDATE cotizaciones_partidas
                    SET precio_unitario = ?, subtotal = ?, iva = ?, total = ?
                    WHERE id = ?
                ")->execute([$nuevo_unit, $subtotal_p, $iva_p, $total_p, $p['id']]);
            }
        }

        // ── Cambios por partida ───────────────────────────────────────────────
        $campos_partida = [
            'ancho', 'alto',
            'precio_unitario', 'precio_m2_usado', 'cantidad',
            'detalles', 'cpb', 'comentarios_etiqueta',
            'resaques', 'taladros_pasados', 'taladros_avellanados', 'requiere_templado',
        ];

        foreach ($body['cambios_partidas'] ?? [] as $pc) {
            $pid = (int)($pc['partida_id'] ?? 0);
            if (!$pid) continue;

            $sp = $db->prepare("SELECT * FROM cotizaciones_partidas WHERE id = ? AND cotizacion_id = ?");
            $sp->execute([$pid, $cot_id]);
            $partida = $sp->fetch(PDO::FETCH_ASSOC);
            if (!$partida) continue;

            $updates_p = [];
            $params_p  = [];

            foreach ($campos_partida as $campo) {
                if (!array_key_exists($campo, $pc)) continue;
                // Cantidad solo si aún es cotización
                if ($campo === 'cantidad' && $es_orden) continue;

                $nuevo    = $pc[$campo];
                $anterior = $partida[$campo] ?? '';
                if ((string)$anterior === (string)$nuevo) continue;

                $updates_p[] = "$campo = ?";
                $params_p[]  = $nuevo;

                $db->prepare("
                    INSERT INTO correcciones_log
                        (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, motivo, usuario)
                    VALUES ('cotizacion', ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $cot_id, $folio,
                    'P' . $partida['num_partida'] . '.' . $campo,
                    $anterior, $nuevo, $motivo, $usuario,
                ]);
                $cambios++;
            }

            if ($updates_p) {
                $descuento_cot = (float)($cot['descuento'] ?? 0);
                $m2_partida    = (float)$partida['m2'];
                $cambio_dim    = array_key_exists('ancho', $pc) || array_key_exists('alto', $pc);

                // Si cambió ancho o alto → recalcular m2
                if ($cambio_dim) {
                    $nuevo_ancho = array_key_exists('ancho', $pc) ? (int)$pc['ancho'] : (int)$partida['ancho'];
                    $nuevo_alto  = array_key_exists('alto',  $pc) ? (int)$pc['alto']  : (int)$partida['alto'];
                    $m2_partida  = round(($nuevo_ancho / 1000) * ($nuevo_alto / 1000), 6);
                    $updates_p[] = 'm2 = ?';
                    $params_p[]  = $m2_partida;
                }

                // Si cambió precio_m2_usado → derivar precio_unitario = m2 × precio_m2 × (1 - desc%)
                if (array_key_exists('precio_m2_usado', $pc)) {
                    $nuevo_m2_usado = (float)$pc['precio_m2_usado'];
                    $nuevo_unit     = round($m2_partida * $nuevo_m2_usado * (1 - $descuento_cot / 100), 4);
                    if (!array_key_exists('precio_unitario', $pc)) {
                        $updates_p[] = 'precio_unitario = ?';
                        $params_p[]  = $nuevo_unit;
                    }
                } elseif ($cambio_dim) {
                    // Recalcular precio_unitario con el nuevo m2 y el precio_m2_usado existente
                    $precio_m2_actual = (float)$partida['precio_m2_usado'];
                    $nuevo_unit       = round($m2_partida * $precio_m2_actual * (1 - $descuento_cot / 100), 4);
                    if (!array_key_exists('precio_unitario', $pc)) {
                        $updates_p[] = 'precio_unitario = ?';
                        $params_p[]  = $nuevo_unit;
                    }
                } else {
                    $nuevo_unit = array_key_exists('precio_unitario', $pc)
                        ? (float)$pc['precio_unitario']
                        : (float)$partida['precio_unitario'];
                }

                $nueva_cant = (!$es_orden && array_key_exists('cantidad', $pc))
                    ? (int)$pc['cantidad']
                    : (int)$partida['cantidad'];

                $subtotal_p = round($nuevo_unit * $nueva_cant, 2);
                $iva_p      = round($subtotal_p * 0.16, 2);
                $total_p    = round($subtotal_p + $iva_p, 2);

                $updates_p[] = 'subtotal = ?'; $params_p[] = $subtotal_p;
                $updates_p[] = 'iva = ?';      $params_p[] = $iva_p;
                $updates_p[] = 'total = ?';    $params_p[] = $total_p;
                $params_p[]  = $pid;

                $db->prepare("UPDATE cotizaciones_partidas SET " . implode(', ', $updates_p) . " WHERE id = ?")
                   ->execute($params_p);

                // Propagar cambio de dimensiones a piezas en producción
                if ($cambio_dim && $cot['orden_id']) {
                    $db->prepare("
                        UPDATE piezas SET ancho_mm = ?, alto_mm = ?, m2 = ?, updated_at = NOW()
                        WHERE orden_id = ? AND partida = ?
                    ")->execute([$nuevo_ancho, $nuevo_alto, $m2_partida, $cot['orden_id'], $partida['num_partida']]);
                }
            }
        }

        // ── Eliminar partidas ─────────────────────────────────────────────────
        $eliminar_ids = array_map('intval', $body['eliminar_partidas'] ?? []);
        if (!empty($eliminar_ids)) {

            // Verificar que quedaría al menos 1 partida
            $sp = $db->prepare("SELECT COUNT(*) FROM cotizaciones_partidas WHERE cotizacion_id = ?");
            $sp->execute([$cot_id]);
            $total_partidas = (int)$sp->fetchColumn();
            if ($total_partidas - count($eliminar_ids) < 1) {
                throw new Exception('La cotización debe tener al menos una partida.');
            }

            foreach ($eliminar_ids as $pid) {
                $sp = $db->prepare("SELECT * FROM cotizaciones_partidas WHERE id = ? AND cotizacion_id = ?");
                $sp->execute([$pid, $cot_id]);
                $partida = $sp->fetch(PDO::FETCH_ASSOC);
                if (!$partida) continue;

                $num_p = (int)$partida['num_partida'];

                // Verificar que las piezas no hayan entrado a producción
                if ($cot['orden_id']) {
                    $sp2 = $db->prepare("
                        SELECT COUNT(*) FROM piezas
                        WHERE orden_id = ? AND partida = ? AND estatus != 'pendiente'
                    ");
                    $sp2->execute([$cot['orden_id'], $num_p]);
                    if ((int)$sp2->fetchColumn() > 0) {
                        throw new Exception('La partida ' . $num_p . ' ya tiene piezas en producción y no puede eliminarse.');
                    }
                    // Eliminar piezas pendientes
                    $db->prepare("DELETE FROM piezas WHERE orden_id = ? AND partida = ?")
                       ->execute([$cot['orden_id'], $num_p]);
                }

                // Eliminar partida
                $db->prepare("DELETE FROM cotizaciones_partidas WHERE id = ?")
                   ->execute([$pid]);

                // Log
                $db->prepare("
                    INSERT INTO correcciones_log
                        (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, motivo, usuario)
                    VALUES ('cotizacion', ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $cot_id, $folio,
                    'P' . $num_p . '.eliminada',
                    $partida['cristal_nombre'] . ' ' . $partida['ancho'] . 'x' . $partida['alto'],
                    'ELIMINADA',
                    $motivo, $usuario,
                ]);
                $cambios++;
            }
        }

        // ── Recalcular totales de la cotización ───────────────────────────────
        if ($cambios > 0 || $cambio_descuento !== null) {
            $st = $db->prepare("
                SELECT SUM(subtotal) AS sub, SUM(iva) AS iva_sum, SUM(total) AS tot
                FROM cotizaciones_partidas WHERE cotizacion_id = ?
            ");
            $st->execute([$cot_id]);
            $tots = $st->fetch(PDO::FETCH_ASSOC);

            $nuevo_total     = round((float)$tots['tot'], 2);
            $saldo_pagado    = (float)$cot['saldo_pagado'];
            $nuevo_pendiente = max(0, $nuevo_total - $saldo_pagado);

            $db->prepare("
                UPDATE cotizaciones
                SET subtotal = ?, iva = ?, total = ?, saldo_pendiente = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                round((float)$tots['sub'], 2),
                round((float)$tots['iva_sum'], 2),
                $nuevo_total, $nuevo_pendiente, $cot_id,
            ]);
        }

        $db->commit();
        jsonResponse(['ok' => true, 'cambios' => $cambios]);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

jsonResponse(['error' => 'Método no permitido'], 405);
