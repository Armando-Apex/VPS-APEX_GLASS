<?php
// ============================================================
//  APEX GLASS - API: Correcciones por dir_admin
//  Archivo: api/correcciones.php
//  GET  ?cotizacion_id=X  → historial de correcciones
//  POST { cotizacion_id, motivo, cambios_header, cambios_partidas }
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
require_once __DIR__ . '/helpers/totales.php'; // A-2/C-5: fórmula canónica, ramifica maquila
require_once 'cotizacion_helpers.php'; // BLV-5: cotizacionesFacturaVigente()

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$user = requireSessionApi();
if (!in_array($user['rol'], ['dir_admin', 'desarrollo'])) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$usuario = $user['nombre'];
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

    // BLV-5: $es_orden se calculaba y nunca se usaba — bloquear correcciones que
    // afectan el monto (descuento, precio/medidas/cantidad de partida, eliminar
    // partida) sobre una orden con CFDI timbrado/timbrando vigente. Sin esto,
    // dir_admin podía corregir precios de una orden ya facturada y el CFDI se
    // quedaba con un monto distinto al nuevo total canónico, sin avisar.
    if ($es_orden && $cot['orden_id']) {
        $tocaMonetario = array_key_exists('descuento', $body['cambios_header'] ?? [])
            || !empty($body['eliminar_partidas']);
        if (!$tocaMonetario) {
            foreach ($body['cambios_partidas'] ?? [] as $pc) {
                if (array_key_exists('precio_unitario', $pc) || array_key_exists('precio_m2_usado', $pc)
                    || array_key_exists('cantidad', $pc) || array_key_exists('ancho', $pc) || array_key_exists('alto', $pc)) {
                    $tocaMonetario = true;
                    break;
                }
            }
        }
        if ($tocaMonetario && ($facturaVigente = cotizacionesFacturaVigente($db, $cot['orden_id']))) {
            jsonResponse(['error' => 'Esta orden tiene la factura ' . $facturaVigente . ' timbrada vigente ante el SAT — no se pueden corregir montos, precios, medidas o cantidades. Cancela la factura primero en Facturación.'], 422);
        }
    }

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

                $nueva_cant = array_key_exists('cantidad', $pc)
                    ? max(1, (int)$pc['cantidad'])
                    : (int)$partida['cantidad'];
                $cantidad_cambio = $nueva_cant !== (int)$partida['cantidad'];

                $subtotal_p = round($nuevo_unit * $nueva_cant, 2);
                $iva_p      = round($subtotal_p * 0.16, 2);
                $total_p    = round($subtotal_p + $iva_p, 2);

                $updates_p[] = 'subtotal = ?'; $params_p[] = $subtotal_p;
                $updates_p[] = 'iva = ?';      $params_p[] = $iva_p;
                $updates_p[] = 'total = ?';    $params_p[] = $total_p;
                $params_p[]  = $pid;

                $db->prepare("UPDATE cotizaciones_partidas SET " . implode(', ', $updates_p) . " WHERE id = ?")
                   ->execute($params_p);

                // Propagar cambios a piezas en producción
                if ($cot['orden_id']) {
                    $piezas_sets    = [];
                    $piezas_params  = [];
                    if ($cambio_dim) {
                        $piezas_sets[]   = 'ancho_mm = ?'; $piezas_params[] = $nuevo_ancho;
                        $piezas_sets[]   = 'alto_mm = ?';  $piezas_params[] = $nuevo_alto;
                        $piezas_sets[]   = 'm2 = ?';       $piezas_params[] = $m2_partida;
                    }
                    if (array_key_exists('cpb', $pc)) {
                        $piezas_sets[]   = 'cpb = ?'; $piezas_params[] = $pc['cpb'];
                    }
                    if (array_key_exists('resaques', $pc)) {
                        $piezas_sets[]   = 'resaques = ?'; $piezas_params[] = (int)$pc['resaques'];
                    }
                    if (array_key_exists('taladros_pasados', $pc)) {
                        $piezas_sets[]   = 'tp = ?'; $piezas_params[] = (int)$pc['taladros_pasados'];
                    }
                    if (array_key_exists('taladros_avellanados', $pc)) {
                        $piezas_sets[]   = 'ta = ?'; $piezas_params[] = (int)$pc['taladros_avellanados'];
                    }
                    if (array_key_exists('requiere_templado', $pc)) {
                        $piezas_sets[]   = 'requiere_templado = ?'; $piezas_params[] = (int)$pc['requiere_templado'];
                    }
                    if (array_key_exists('detalles', $pc)) {
                        $piezas_sets[]   = 'detalles = ?'; $piezas_params[] = $pc['detalles'];
                    }
                    if (!empty($piezas_sets)) {
                        $piezas_sets[]   = 'updated_at = NOW()';
                        $piezas_params[] = $cot['orden_id'];
                        $piezas_params[] = $partida['num_partida'];
                        $db->prepare("UPDATE piezas SET " . implode(', ', $piezas_sets) . " WHERE orden_id = ? AND partida = ?")
                           ->execute($piezas_params);
                    }

                    // Corrección de cantidad de piezas en una orden ya convertida (dir_admin/desarrollo
                    // solamente, ver guard de rol arriba). Cada unidad tiene su propia fila en `piezas`
                    // (generadas 1:1 al convertir la cotización) y su `qr_code` trae el total horneado
                    // en el string (`{folio}-{partida}-{num}-{total}`) — cambiar la cantidad sin
                    // resincronizar `piezas` dejaría el conteo de producción y los QR desalineados.
                    if ($cantidad_cambio) {
                        $spz = $db->prepare("SELECT id, pieza_num, estatus FROM piezas WHERE orden_id = ? AND partida = ? ORDER BY pieza_num ASC");
                        $spz->execute([$cot['orden_id'], $partida['num_partida']]);
                        $piezasActuales = $spz->fetchAll(PDO::FETCH_ASSOC);
                        $cantActual     = count($piezasActuales);

                        if ($nueva_cant < $cantActual) {
                            // Reducir: solo permitido si ninguna de las piezas a quitar ya tiene
                            // historial de producción — nunca se borra trabajo real ya hecho.
                            $aQuitar = array_slice($piezasActuales, $nueva_cant);
                            $idsQuitar = array_column($aQuitar, 'id');
                            foreach ($aQuitar as $pz) {
                                if ($pz['estatus'] !== 'pendiente') {
                                    throw new Exception("No se puede reducir la cantidad de la partida P{$partida['num_partida']}: la pieza #{$pz['pieza_num']} ya tiene estatus '{$pz['estatus']}' (ya entró a producción).");
                                }
                            }
                            $shp = $db->prepare("SELECT COUNT(*) FROM historial_estatus WHERE pieza_id IN (" . implode(',', array_fill(0, count($idsQuitar), '?')) . ")");
                            $shp->execute($idsQuitar);
                            if ((int)$shp->fetchColumn() > 0) {
                                throw new Exception("No se puede reducir la cantidad de la partida P{$partida['num_partida']}: alguna de las piezas a eliminar ya tiene historial de producción.");
                            }
                            $db->prepare("DELETE FROM piezas WHERE id IN (" . implode(',', array_fill(0, count($idsQuitar), '?')) . ")")
                               ->execute($idsQuitar);
                        } elseif ($nueva_cant > $cantActual) {
                            // Aumentar: clonar los atributos compartidos de la partida desde una
                            // pieza existente y crear las unidades nuevas en estatus 'pendiente'.
                            $base = $piezasActuales[0] ?? null;
                            $sb = $db->prepare("SELECT * FROM piezas WHERE id = ?");
                            $sb->execute([$base ? $base['id'] : 0]);
                            $modelo = $sb->fetch(PDO::FETCH_ASSOC);
                            if ($modelo) {
                                $insPz = $db->prepare("INSERT INTO piezas
                                    (orden_id, partida, pieza_num, pieza_total,
                                     cristal, cristal_corto, requiere_templado, requiere_corte,
                                     ancho_mm, alto_mm, m2,
                                     cpb, detalles, resaques, tp, ta, pintura, esmerilado,
                                     acabado_forma, requiere_trazo, tipo_biselado, espesor_biselado,
                                     comentarios, qr_code, estatus)
                                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                                ");
                                for ($i = $cantActual + 1; $i <= $nueva_cant; $i++) {
                                    $qr = $folio . '-' . str_pad($partida['num_partida'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($i, 3, '0', STR_PAD_LEFT) . '-' . str_pad($nueva_cant, 3, '0', STR_PAD_LEFT);
                                    $insPz->execute([
                                        $cot['orden_id'], $partida['num_partida'], $i, $nueva_cant,
                                        $modelo['cristal'], $modelo['cristal_corto'], $modelo['requiere_templado'], $modelo['requiere_corte'],
                                        $modelo['ancho_mm'], $modelo['alto_mm'], $modelo['m2'],
                                        $modelo['cpb'], $modelo['detalles'], $modelo['resaques'], $modelo['tp'], $modelo['ta'],
                                        $modelo['pintura'], $modelo['esmerilado'], $modelo['acabado_forma'], $modelo['requiere_trazo'],
                                        $modelo['tipo_biselado'], $modelo['espesor_biselado'], $modelo['comentarios'],
                                        $qr, 'pendiente'
                                    ]);
                                }
                            }
                        }

                        // Re-numerar y regenerar QR de TODAS las piezas restantes de esta partida —
                        // el total cambió, así que el string del QR (que lo incluye) ya no es válido.
                        $spz2 = $db->prepare("SELECT id FROM piezas WHERE orden_id = ? AND partida = ? ORDER BY pieza_num ASC");
                        $spz2->execute([$cot['orden_id'], $partida['num_partida']]);
                        $ids = $spz2->fetchAll(PDO::FETCH_COLUMN);
                        $upPz = $db->prepare("UPDATE piezas SET pieza_num = ?, pieza_total = ?, qr_code = ?, updated_at = NOW() WHERE id = ?");
                        $n = 1;
                        foreach ($ids as $pid2) {
                            $qr = $folio . '-' . str_pad($partida['num_partida'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($n, 3, '0', STR_PAD_LEFT) . '-' . str_pad($nueva_cant, 3, '0', STR_PAD_LEFT);
                            $upPz->execute([$n, $nueva_cant, $qr, $pid2]);
                            $n++;
                        }
                    }
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

                // Alto#4: eliminar la partida no borraba sus servicios adicionales
                // (cotizacion_partida_servicios) — quedaban huérfanos y su costo
                // seguía sumado en servicios_subtotal, cobrando servicios de una
                // partida que ya no existe. Mismo patrón que ya usa
                // api/cotizaciones.php al eliminar un servicio.
                $db->prepare("DELETE FROM cotizacion_partida_servicios WHERE partida_id = ? AND cotizacion_id = ?")
                   ->execute([$pid, $cot_id]);

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

            // Alto#4: cotizaciones.servicios_subtotal es una columna guardada (no
            // se re-suma en vivo) — hay que recalcularla desde lo que realmente
            // quedó en cotizacion_partida_servicios tras borrar las de la(s)
            // partida(s) eliminada(s), o el total seguiría cobrando servicios
            // fantasma aunque ya no existan sus filas.
            $stSrv = $db->prepare("SELECT COALESCE(SUM(subtotal),0) FROM cotizacion_partida_servicios WHERE cotizacion_id = ?");
            $stSrv->execute([$cot_id]);
            $db->prepare("UPDATE cotizaciones SET servicios_subtotal = ? WHERE id = ?")
               ->execute([(float)$stSrv->fetchColumn(), $cot_id]);
        }

        // ── Recalcular totales de la cotización (fórmula canónica A-2) ────────
        if ($cambios > 0 || $cambio_descuento !== null) {
            // C-5: ramificar por tipo — la maquila guarda sus partidas en
            // cotizaciones_maquila_partidas (cotizaciones_partidas está VACÍA para
            // ella y el SUM anterior dejaba el total en $0.00, anulando la deuda).
            // El helper también incluye servicios_subtotal en el total.
            $tots = apexTotalesCotizacion($db, $cot_id);

            $nuevo_total        = $tots['total'];
            $saldo_pagado       = (float)$cot['saldo_pagado'];
            $nuevo_pendiente    = max(0, round($nuevo_total - $saldo_pagado, 2));
            $nuevo_saldo_pagado = $saldo_pagado;

            // BLV-5: max(0,...) escondía el sobrepago cuando una corrección baja el
            // total por debajo de lo ya cobrado (ej. reducir descuento/cantidad tras
            // haber cobrado con el monto anterior) — mover el excedente a saldo a
            // favor del cliente en vez de dejarlo invisible, mismo patrón que ya usan
            // cancelar/rechazar en cotizaciones.php.
            $excedente = round($saldo_pagado - $nuevo_total, 2);
            if ($excedente > 0.01 && $cot['cliente_id']) {
                $db->prepare("INSERT INTO clientes_saldo_favor (cliente_id, tipo, monto, fecha, referencia, notas, cotizacion_id, creado_por)
                              VALUES (?, 'deposito', ?, CURDATE(), ?, ?, ?, ?)")
                   ->execute([
                       $cot['cliente_id'], $excedente,
                       'Corrección ' . $folio,
                       'Excedente por corrección que redujo el total por debajo de lo ya cobrado',
                       $cot_id, $usuario
                   ]);
                $nuevo_saldo_pagado = $nuevo_total;

                $db->prepare("
                    INSERT INTO correcciones_log
                        (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, motivo, usuario)
                    VALUES ('cotizacion', ?, ?, 'saldo_pagado', ?, ?, ?, ?)
                ")->execute([
                    $cot_id, $folio, $saldo_pagado, $nuevo_saldo_pagado,
                    'Excedente $' . $excedente . ' movido a saldo a favor por corrección', $usuario,
                ]);
            }

            $db->prepare("
                UPDATE cotizaciones
                SET subtotal = ?, iva = ?, total = ?, saldo_pendiente = ?, saldo_pagado = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $tots['subtotal'],
                $tots['iva'],
                $nuevo_total, $nuevo_pendiente, $nuevo_saldo_pagado, $cot_id,
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
