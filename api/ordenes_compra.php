<?php
// ============================================================
//  APEX GLASS - API: Órdenes de Compra
//  GET    ?accion=lista|detalle|calendario_pagos|consecutivo
//  POST   accion=crear|agregar_partida|registrar_entrega|registrar_pago|cerrar
//  PUT    accion=actualizar|actualizar_partida
//  DELETE accion=eliminar_partida
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
$user = requirePermiso('ver_inventario');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? 'lista';
$id     = (int)($_GET['id'] ?? 0);

// ── Helper: ¿puede editarse el encabezado / agregar partidas? ─
// Borrador: cualquiera con gestionar_inventario.
// Abierta: solo dir_admin (con o sin entregas).
function ocHeaderEsEditable($db, $oc_id, $rol) {
    $s = $db->prepare("SELECT estado FROM ordenes_compra WHERE id = ?");
    $s->execute([$oc_id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    if ($row['estado'] === 'borrador') return true;
    if ($row['estado'] === 'abierta' && $rol === 'dir_admin') return true;
    return false;
}

// ── Helper: ¿puede editarse/eliminarse esta partida? ──────────
// Borrador: cualquiera con gestionar_inventario.
// Abierta + dir_admin: solo si la partida no tiene material recibido.
function partidaEsEditable($db, $partida_id, $rol) {
    $s = $db->prepare("
        SELECT o.estado, COALESCE(op.cantidad_recibida, 0) AS recibido
        FROM oc_partidas op
        JOIN ordenes_compra o ON o.id = op.orden_compra_id
        WHERE op.id = ?
    ");
    $s->execute([$partida_id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    if ($row['estado'] === 'borrador') return true;
    if ($row['estado'] === 'abierta' && $rol === 'dir_admin' && (float)$row['recibido'] === 0.0) return true;
    return false;
}

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {

    // Siguiente número de OC
    if ($accion === 'consecutivo') {
        $s = $db->query("SELECT ultimo + 1 AS siguiente FROM oc_consecutivo WHERE id = 1");
        $n = $s->fetchColumn();
        jsonResponse(['numero_oc' => 'APEX-' . str_pad($n, 4, '0', STR_PAD_LEFT)]);
    }

    // Calendario de pagos
    if ($accion === 'calendario_pagos') {
        $s = $db->query("
            SELECT
                oc.id,
                oc.numero_oc,
                oc.fecha_oc,
                oc.dias_credito,
                oc.estado,
                oc.fecha_pago_programada,
                oc.notas,
                p.nombre                            AS proveedor,
                SUM(op.importe)                     AS subtotal_sin_iva,
                SUM(op.importe) * 1.16              AS total_con_iva,
                COALESCE(SUM(pg.monto), 0)          AS pagado,
                DATEDIFF(oc.fecha_pago_programada, CURDATE()) AS dias_para_pago
            FROM ordenes_compra oc
            JOIN proveedores p   ON p.id = oc.proveedor_id
            LEFT JOIN oc_partidas op ON op.orden_compra_id = oc.id
            LEFT JOIN oc_pagos pg    ON pg.orden_compra_id = oc.id
            WHERE oc.estado IN ('cerrada','abierta')
              AND oc.fecha_pago_programada IS NOT NULL
            GROUP BY oc.id
            ORDER BY oc.fecha_pago_programada ASC
        ");
        jsonResponse(['pagos' => $s->fetchAll()]);
    }

    // Detalle de una OC con partidas y entregas
    if ($accion === 'detalle' && $id) {
        $s = $db->prepare("
            SELECT oc.*, p.nombre AS proveedor_nombre,
                   p.contacto AS proveedor_contacto,
                   p.telefono AS proveedor_telefono,
                   u.nombre AS creado_por
            FROM ordenes_compra oc
            JOIN proveedores p ON p.id = oc.proveedor_id
            LEFT JOIN usuarios u ON u.id = oc.created_by
            WHERE oc.id = ?
        ");
        $s->execute([$id]);
        $oc = $s->fetch();
        if (!$oc) jsonResponse(['error' => 'No encontrada'], 404);

        // Partidas
        $sp = $db->prepare("
            SELECT op.*,
                   l.tipo AS lamina_tipo, l.espesor_mm, l.ancho_mm, l.alto_mm,
                   oc2.numero_oc AS oc_ref_numero
            FROM oc_partidas op
            LEFT JOIN laminas l ON l.id = op.lamina_id
            LEFT JOIN ordenes_compra oc2 ON oc2.id = op.oc_referencia_id
            WHERE op.orden_compra_id = ?
            ORDER BY op.numero_partida ASC
        ");
        $sp->execute([$id]);
        $oc['partidas'] = $sp->fetchAll();

        // Entregas
        $se = $db->prepare("
            SELECT e.*, u.nombre AS registrado_por,
                   GROUP_CONCAT(
                     CONCAT(l.tipo,' ',l.espesor_mm,'mm x ',ed.cantidad_recibida)
                     ORDER BY op.numero_partida SEPARATOR ' | '
                   ) AS resumen
            FROM oc_entregas e
            LEFT JOIN usuarios u ON u.id = e.created_by
            LEFT JOIN oc_entrega_detalle ed ON ed.entrega_id = e.id
            LEFT JOIN oc_partidas op ON op.id = ed.oc_partida_id
            LEFT JOIN laminas l ON l.id = op.lamina_id
            WHERE e.orden_compra_id = ?
            GROUP BY e.id
            ORDER BY e.fecha_entrega ASC
        ");
        $se->execute([$id]);
        $oc['entregas'] = $se->fetchAll();

        // Pagos
        $spg = $db->prepare("
            SELECT pg.*, u.nombre AS registrado_por
            FROM oc_pagos pg
            LEFT JOIN usuarios u ON u.id = pg.created_by
            WHERE pg.orden_compra_id = ?
            ORDER BY pg.fecha_pago ASC
        ");
        $spg->execute([$id]);
        $oc['pagos'] = $spg->fetchAll();

        jsonResponse($oc);
    }

    // Compras de inventario disponibles para distribuir flete
    if ($accion === 'compras_para_flete') {
        $oc_id = (int)($_GET['oc_id'] ?? 0);
        if (!$oc_id) jsonResponse(['error' => 'oc_id requerido'], 422);

        $sf = $db->prepare("
            SELECT COALESCE(SUM(precio_unitario * cantidad), 0) AS total_flete
            FROM oc_partidas WHERE orden_compra_id = ? AND tipo = 'flete'
        ");
        $sf->execute([$oc_id]);
        $total_flete = (float)$sf->fetchColumn();

        $sc = $db->query("
            SELECT
                ic.id,
                ic.cantidad_laminas,
                ic.precio_unitario,
                ic.costo_real_unitario,
                ic.costo_flete_total,
                ic.orden_compra_id,
                oc2.numero_oc                         AS oc_numero,
                CONCAT(l.tipo, ' ', l.espesor_mm, 'mm ', l.ancho_mm, 'x', l.alto_mm) AS lamina_desc,
                l.m2,
                ic.cantidad_laminas - COALESCE((
                    SELECT SUM(m.cantidad_laminas)
                    FROM inventario_movimientos m WHERE m.lamina_id = ic.lamina_id
                ), 0) AS en_stock
            FROM inventario_compras ic
            JOIN laminas l ON l.id = ic.lamina_id
            LEFT JOIN ordenes_compra oc2 ON oc2.id = ic.orden_compra_id
            HAVING en_stock > 0
            ORDER BY ic.orden_compra_id ASC, ic.id ASC
        ");
        jsonResponse(['total_flete' => $total_flete, 'compras' => $sc->fetchAll()]);
    }

    // Lista de OCs
    $estado_raw  = $_GET['estado'] ?? '';
    $tipo_raw    = $_GET['tipo']   ?? '';
    $estados_ok  = ['borrador', 'abierta', 'cerrada', 'pagada', 'cancelada'];
    $where_parts = [];
    $params      = [];
    if ($estado_raw !== '' && in_array($estado_raw, $estados_ok, true)) {
        $where_parts[] = 'oc.estado = ?';
        $params[]      = $estado_raw;
    }
    if ($tipo_raw !== '' && in_array($tipo_raw, ['material', 'suministro'], true)) {
        $where_parts[] = 'oc.tipo = ?';
        $params[]      = $tipo_raw;
    }
    $where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';
    $s = $db->prepare("
        SELECT
            oc.id, oc.tipo, oc.categoria, oc.numero_oc, oc.fecha_oc, oc.dias_credito,
            oc.estado, oc.fecha_pago_programada, oc.notas,
            p.nombre                            AS proveedor,
            COUNT(DISTINCT op.id)               AS num_partidas,
            COALESCE(SUM(op.importe), 0)        AS subtotal_sin_iva,
            COALESCE(SUM(op.importe), 0) * 1.16 AS total_con_iva,
            COALESCE(SUM(op.cantidad_recibida), 0) AS total_recibido,
            COALESCE(SUM(op.cantidad), 0)           AS total_ordenado,
            DATEDIFF(oc.fecha_pago_programada, CURDATE()) AS dias_para_pago,
            -- OC que solo tiene partidas de flete sin referencia a OC de vidrio
            (SUM(CASE WHEN op.tipo != 'flete' THEN 1 ELSE 0 END) = 0
             AND SUM(CASE WHEN op.tipo = 'flete' AND op.oc_referencia_id IS NULL THEN 1 ELSE 0 END) > 0
            ) AS es_solo_flete
        FROM ordenes_compra oc
        JOIN proveedores p ON p.id = oc.proveedor_id
        LEFT JOIN oc_partidas op ON op.orden_compra_id = oc.id
        $where
        GROUP BY oc.id
        ORDER BY oc.fecha_oc DESC
        LIMIT 200
    ");
    $s->execute($params);
    jsonResponse(['ordenes' => $s->fetchAll()]);
}

// ── POST ─────────────────────────────────────────────────────
if ($method === 'POST') {
    requirePermiso('gestionar_inventario');
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = $body['accion'] ?? $accion;

    // ── Crear OC ──────────────────────────────────────────────
    if ($accion === 'crear') {
        $proveedor_id = (int)($body['proveedor_id'] ?? 0);
        $fecha_oc     = $body['fecha_oc'] ?? date('Y-m-d');
        $dias_credito = (int)($body['dias_credito'] ?? 0);
        $notas        = trim($body['notas'] ?? '');
        $numero_oc    = trim($body['numero_oc'] ?? '');
        $tipo         = in_array($body['tipo'] ?? '', ['material','suministro']) ? $body['tipo'] : 'material';
        $categoria    = trim($body['categoria'] ?? '') ?: null;

        if (!$proveedor_id) jsonResponse(['error' => 'Proveedor requerido'], 422);

        // Si no viene número, generar consecutivo
        if (!$numero_oc) {
            $db->beginTransaction();
            $db->exec("UPDATE oc_consecutivo SET ultimo = ultimo + 1 WHERE id = 1");
            $n = $db->query("SELECT ultimo FROM oc_consecutivo WHERE id = 1")->fetchColumn();
            $numero_oc = 'APEX-' . str_pad($n, 4, '0', STR_PAD_LEFT);
            $db->commit();
        }

        $s = $db->prepare("INSERT INTO ordenes_compra
            (tipo, categoria, numero_oc, proveedor_id, fecha_oc, dias_credito, notas, created_by)
            VALUES (?,?,?,?,?,?,?,?)");
        try {
            $s->execute([$tipo, $categoria, $numero_oc, $proveedor_id, $fecha_oc, $dias_credito, $notas, $user['id']]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000)
                jsonResponse(['error' => 'Ya existe una OC con ese número'], 422);
            throw $e;
        }
        jsonResponse(['ok' => true, 'id' => $db->lastInsertId(), 'numero_oc' => $numero_oc]);
    }

    // ── Agregar partida ───────────────────────────────────────
    if ($accion === 'agregar_partida') {
        $oc_id      = (int)($body['orden_compra_id'] ?? 0);
        if (!ocHeaderEsEditable($db, $oc_id, $user['rol']))
            jsonResponse(['error' => 'No tienes permiso para modificar esta OC'], 403);
        $tipo       = $body['tipo'] ?? 'lamina';
        $lamina_id  = $tipo === 'lamina' ? (int)($body['lamina_id'] ?? 0) : null;
        $oc_ref_id  = $tipo === 'flete'  ? (int)($body['oc_referencia_id'] ?? 0) : null;
        $desc       = trim($body['descripcion'] ?? '');
        $unidad     = strtoupper(trim($body['unidad'] ?? 'LAMINA'));
        $cantidad   = (float)($body['cantidad'] ?? 0);
        $precio     = (float)($body['precio_unitario'] ?? 0);

        if (!$oc_id || !$desc || !$cantidad || !$precio)
            jsonResponse(['error' => 'Faltan campos requeridos'], 422);

        // Número de partida
        $n = $db->prepare("SELECT COALESCE(MAX(numero_partida),0)+1 FROM oc_partidas WHERE orden_compra_id=?");
        $n->execute([$oc_id]);
        $num = $n->fetchColumn();

        $s = $db->prepare("INSERT INTO oc_partidas
            (orden_compra_id, numero_partida, tipo, lamina_id, oc_referencia_id,
             descripcion, unidad, cantidad, precio_unitario)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $s->execute([$oc_id, $num, $tipo, $lamina_id, $oc_ref_id ?: null,
                     $desc, $unidad, $cantidad, $precio]);
        jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
    }

    // ── Registrar entrega ─────────────────────────────────────
    if ($accion === 'registrar_entrega') {
        $oc_id        = (int)($body['orden_compra_id'] ?? 0);
        $fecha        = $body['fecha_entrega'] ?? date('Y-m-d');
        $notas        = trim($body['notas'] ?? '');
        $detalle      = $body['detalle'] ?? []; // [{oc_partida_id, cantidad_recibida}]

        if (!$oc_id || empty($detalle))
            jsonResponse(['error' => 'Faltan datos de entrega'], 422);

        // Detectar si es OC de flete
        $stp = $db->prepare("SELECT COUNT(*) FROM oc_partidas WHERE orden_compra_id = ? AND tipo = 'flete'");
        $stp->execute([$oc_id]);
        $es_oc_flete = (int)$stp->fetchColumn() > 0;

        // Si es OC de vidrio, verificar si existe una OC de flete vinculada
        $flete_tipo_vidrio = 'incluido';
        if (!$es_oc_flete) {
            $shf = $db->prepare("
                SELECT COUNT(*) FROM oc_partidas op
                JOIN ordenes_compra oc2 ON oc2.id = op.orden_compra_id
                WHERE op.tipo = 'flete' AND op.oc_referencia_id = ? AND oc2.estado != 'cancelada'
            ");
            $shf->execute([$oc_id]);
            if ((int)$shf->fetchColumn() > 0) $flete_tipo_vidrio = 'oc_separada';
        }

        $db->beginTransaction();
        try {
            // Insertar entrega
            $se = $db->prepare("INSERT INTO oc_entregas
                (orden_compra_id, fecha_entrega, notas, created_by)
                VALUES (?,?,?,?)");
            $se->execute([$oc_id, $fecha, $notas, $user['id']]);
            $entrega_id = $db->lastInsertId();

            // Insertar detalle y actualizar cantidad_recibida en partidas
            $sd = $db->prepare("INSERT INTO oc_entrega_detalle
                (entrega_id, oc_partida_id, cantidad_recibida) VALUES (?,?,?)");
            $su = $db->prepare("UPDATE oc_partidas
                SET cantidad_recibida = cantidad_recibida + ?
                WHERE id = ? AND orden_compra_id = ?");

            foreach ($detalle as $d) {
                $partida_id = (int)($d['oc_partida_id'] ?? 0);
                $cant       = (float)($d['cantidad_recibida'] ?? 0);
                if (!$partida_id || !$cant) continue;

                $sd->execute([$entrega_id, $partida_id, $cant]);
                $su->execute([$cant, $partida_id, $oc_id]);

                if (!$es_oc_flete) {
                    // Registrar entrada en inventario para partidas de lamina
                    $sp2 = $db->prepare("SELECT lamina_id, precio_unitario FROM oc_partidas WHERE id = ? AND tipo = 'lamina'");
                    $sp2->execute([$partida_id]);
                    $partida = $sp2->fetch();
                    if ($partida && $partida['lamina_id']) {
                        $sic = $db->prepare("INSERT INTO inventario_compras
                            (lamina_id, proveedor_id, fecha_compra, cantidad_laminas,
                             precio_unitario, flete_tipo, costo_flete_total,
                             notas, created_by, orden_compra_id, oc_entrega_id)
                            SELECT ?, oc.proveedor_id, ?, ?, ?, ?, 0, ?, ?, ?, ?
                            FROM ordenes_compra oc WHERE oc.id = ?");
                        $sic->execute([
                            $partida['lamina_id'],
                            $fecha,
                            $cant,
                            $partida['precio_unitario'],
                            $flete_tipo_vidrio,
                            'Entrega de OC ' . $oc_id,
                            $user['id'],
                            $oc_id,
                            $entrega_id,
                            $oc_id
                        ]);
                    }
                }
            }

            // Si es OC de flete, distribuir costo al inventario de la OC de vidrio vinculada
            if ($es_oc_flete) {
                $sfp = $db->prepare("
                    SELECT precio_unitario * cantidad AS importe_flete, oc_referencia_id
                    FROM oc_partidas
                    WHERE orden_compra_id = ? AND tipo = 'flete' AND oc_referencia_id IS NOT NULL
                ");
                $sfp->execute([$oc_id]);
                foreach ($sfp->fetchAll() as $fp) {
                    $glass_oc_id   = (int)$fp['oc_referencia_id'];
                    $importe_flete = (float)$fp['importe_flete'];

                    $stl = $db->prepare("SELECT COALESCE(SUM(cantidad_laminas), 0) FROM inventario_compras WHERE orden_compra_id = ?");
                    $stl->execute([$glass_oc_id]);
                    $total_laminas = (float)$stl->fetchColumn();
                    if ($total_laminas <= 0) continue;

                    $flete_x_lamina = $importe_flete / $total_laminas;

                    // Distribuye flete por lamina; usa OLD costo_flete_total para acumular si hay mas de una OC flete
                    $sup = $db->prepare("
                        UPDATE inventario_compras
                        SET costo_flete_total = costo_flete_total + ? * cantidad_laminas,
                            flete_tipo        = 'oc_separada',
                            flete_oc_id       = ?
                        WHERE orden_compra_id = ?
                    ");
                    $sup->execute([$flete_x_lamina, $oc_id, $glass_oc_id]);
                }
            }

            // Verificar si la OC quedo completa
            $stype = $db->prepare("SELECT tipo FROM ordenes_compra WHERE id = ?");
            $stype->execute([$oc_id]);
            $oc_tipo    = $stype->fetchColumn() ?: 'material';
            $tipo_check = ($oc_tipo === 'suministro') ? 'otro' : ($es_oc_flete ? 'flete' : 'lamina');
            $sc = $db->prepare("
                SELECT SUM(cantidad) - SUM(cantidad_recibida) AS pendiente
                FROM oc_partidas WHERE orden_compra_id = ? AND tipo = ?
            ");
            $sc->execute([$oc_id, $tipo_check]);
            $pendiente = (float)$sc->fetchColumn();

            $cierra = $pendiente <= 0;
            if ($cierra) {
                $db->prepare("UPDATE oc_entregas SET cierra_oc=1 WHERE id=?")->execute([$entrega_id]);
                $db->prepare("
                    UPDATE ordenes_compra
                    SET estado = 'cerrada',
                        fecha_pago_programada = DATE_ADD(?, INTERVAL dias_credito DAY)
                    WHERE id = ?
                ")->execute([$fecha, $oc_id]);
            }

            $db->commit();
            jsonResponse(['ok' => true, 'entrega_id' => $entrega_id, 'cerro_oc' => $cierra]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // ── Registrar pago ────────────────────────────────────────
    if ($accion === 'registrar_pago') {
        $oc_id      = (int)($body['orden_compra_id'] ?? 0);
        $fecha      = $body['fecha_pago'] ?? date('Y-m-d');
        $monto      = (float)($body['monto'] ?? 0);
        $inc_iva    = isset($body['incluye_iva']) ? (int)$body['incluye_iva'] : 1;
        $referencia = trim($body['referencia'] ?? '');
        $notas      = trim($body['notas'] ?? '');

        if (!$oc_id || !$monto) jsonResponse(['error' => 'Faltan datos'], 422);

        $db->prepare("INSERT INTO oc_pagos
            (orden_compra_id, fecha_pago, monto, incluye_iva, referencia, notas, created_by)
            VALUES (?,?,?,?,?,?,?)")
           ->execute([$oc_id, $fecha, $monto, $inc_iva, $referencia, $notas, $user['id']]);

        // Verificar si quedó pagada completamente
        $st = $db->prepare("
            SELECT
                SUM(op.importe) * 1.16 AS total_con_iva,
                COALESCE((SELECT SUM(pg.monto) FROM oc_pagos pg WHERE pg.orden_compra_id=?),0) AS pagado
            FROM oc_partidas op WHERE op.orden_compra_id=?
        ");
        $st->execute([$oc_id, $oc_id]);
        $res = $st->fetch();
        if ($res && $res['pagado'] >= $res['total_con_iva']) {
            $db->prepare("UPDATE ordenes_compra SET estado='pagada' WHERE id=?")->execute([$oc_id]);
        }

        jsonResponse(['ok' => true]);
    }

    // ── Distribuir flete de OC sin referencia a inventario ───
    if ($accion === 'distribuir_flete') {
        requirePermiso('gestionar_inventario');
        $oc_id   = (int)($body['oc_id'] ?? 0);
        $compras = array_map('intval', $body['compra_ids'] ?? []);
        $metodo  = in_array($body['metodo'] ?? '', ['lamina', 'm2']) ? $body['metodo'] : 'lamina';

        if (!$oc_id || empty($compras))
            jsonResponse(['error' => 'Datos insuficientes'], 422);

        $soc = $db->prepare("SELECT estado FROM ordenes_compra WHERE id = ?");
        $soc->execute([$oc_id]);
        $oc = $soc->fetch();
        if (!$oc) jsonResponse(['error' => 'OC no encontrada'], 404);
        if ($oc['estado'] !== 'abierta') jsonResponse(['error' => 'La OC debe estar abierta para distribuir'], 422);

        $sf = $db->prepare("
            SELECT COALESCE(SUM(precio_unitario * cantidad), 0) AS total_flete
            FROM oc_partidas WHERE orden_compra_id = ? AND tipo = 'flete'
        ");
        $sf->execute([$oc_id]);
        $total_flete = (float)$sf->fetchColumn();
        if ($total_flete <= 0) jsonResponse(['error' => 'OC sin importe de flete válido'], 422);

        $ids_str = implode(',', $compras);
        $sc = $db->query("
            SELECT ic.id, ic.cantidad_laminas, ic.precio_unitario, l.m2
            FROM inventario_compras ic
            JOIN laminas l ON l.id = ic.lamina_id
            WHERE ic.id IN ($ids_str)
        ");
        $compras_data = $sc->fetchAll();
        if (empty($compras_data)) jsonResponse(['error' => 'No se encontraron registros seleccionados'], 422);

        $total_base = 0;
        foreach ($compras_data as $c) {
            $total_base += $metodo === 'm2'
                ? (float)$c['cantidad_laminas'] * (float)$c['m2']
                : (float)$c['cantidad_laminas'];
        }
        if ($total_base <= 0) jsonResponse(['error' => 'Error en cálculo de distribución'], 422);

        $db->beginTransaction();
        try {
            $sup = $db->prepare("
                UPDATE inventario_compras
                SET costo_flete_total = ?,
                    flete_tipo        = 'oc_separada',
                    flete_oc_id       = ?
                WHERE id = ?
            ");
            foreach ($compras_data as $c) {
                $base_c     = $metodo === 'm2'
                    ? (float)$c['cantidad_laminas'] * (float)$c['m2']
                    : (float)$c['cantidad_laminas'];
                $flete_este = $total_flete * ($base_c / $total_base);
                $sup->execute([$flete_este, $oc_id, $c['id']]);
            }

            $db->prepare("
                UPDATE oc_partidas SET cantidad_recibida = cantidad
                WHERE orden_compra_id = ? AND tipo = 'flete'
            ")->execute([$oc_id]);

            $db->prepare("
                UPDATE ordenes_compra
                SET estado = 'cerrada',
                    fecha_pago_programada = DATE_ADD(CURDATE(), INTERVAL dias_credito DAY)
                WHERE id = ?
            ")->execute([$oc_id]);

            $db->commit();
            jsonResponse(['ok' => true, 'total_flete' => $total_flete, 'registros' => count($compras_data)]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // ── Cambiar estado manualmente ────────────────────────────
    if ($accion === 'cambiar_estado') {
        $oc_id  = (int)($body['orden_compra_id'] ?? 0);
        $estado = $body['estado'] ?? '';
        $validos = ['borrador','abierta','cerrada','pagada'];
        if (!$oc_id || !in_array($estado, $validos))
            jsonResponse(['error' => 'Datos inválidos'], 422);
        $db->prepare("UPDATE ordenes_compra SET estado=? WHERE id=?")->execute([$estado, $oc_id]);
        jsonResponse(['ok' => true]);
    }

    jsonResponse(['error' => 'Acción no válida'], 400);
}

// ── PUT ──────────────────────────────────────────────────────
if ($method === 'PUT') {
    requirePermiso('gestionar_inventario');
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = $body['accion'] ?? '';
    $id     = (int)($body['id'] ?? 0);

    if ($accion === 'actualizar' && $id) {
        if (!ocHeaderEsEditable($db, $id, $user['rol']))
            jsonResponse(['error' => 'No tienes permiso para modificar esta OC'], 403);
        $proveedor_id = (int)($body['proveedor_id'] ?? 0);
        $fecha_oc     = $body['fecha_oc'] ?? '';
        $dias_credito = (int)($body['dias_credito'] ?? 0);
        $notas        = trim($body['notas'] ?? '');
        if (!$proveedor_id || !$fecha_oc)
            jsonResponse(['error' => 'Faltan datos'], 422);
        $categoria = trim($body['categoria'] ?? '') ?: null;
        $db->prepare("UPDATE ordenes_compra SET
            proveedor_id=?, fecha_oc=?, dias_credito=?, notas=?, categoria=?
            WHERE id=?")
           ->execute([$proveedor_id, $fecha_oc, $dias_credito, $notas, $categoria, $id]);
        jsonResponse(['ok' => true]);
    }

    if ($accion === 'actualizar_partida' && $id) {
        if (!partidaEsEditable($db, $id, $user['rol']))
            jsonResponse(['error' => 'No puedes editar esta partida: ya tiene material recibido o no tienes permiso'], 403);
        $desc    = trim($body['descripcion'] ?? '');
        $unidad  = strtoupper(trim($body['unidad'] ?? 'LAMINA'));
        $cant    = (float)($body['cantidad'] ?? 0);
        $precio  = (float)($body['precio_unitario'] ?? 0);
        if (!$desc || !$cant || !$precio) jsonResponse(['error' => 'Faltan datos'], 422);
        $db->prepare("UPDATE oc_partidas SET descripcion=?, unidad=?, cantidad=?, precio_unitario=?
            WHERE id=?")
           ->execute([$desc, $unidad, $cant, $precio, $id]);
        jsonResponse(['ok' => true]);
    }

    jsonResponse(['error' => 'Acción no válida'], 400);
}

// ── DELETE ───────────────────────────────────────────────────
if ($method === 'DELETE') {
    requirePermiso('gestionar_inventario');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = $body['accion'] ?? 'eliminar_partida';
    $id     = (int)($body['id'] ?? 0);

    if ($accion === 'eliminar_partida' && $id) {
        if (!partidaEsEditable($db, $id, $user['rol']))
            jsonResponse(['error' => 'No puedes eliminar esta partida: ya tiene material recibido o no tienes permiso'], 403);
        $db->prepare("DELETE FROM oc_partidas WHERE id = ?")
           ->execute([$id]);
        jsonResponse(['ok' => true]);
    }

    jsonResponse(['error' => 'Acción no válida'], 400);
}

jsonResponse(['error' => 'Método no soportado'], 405);