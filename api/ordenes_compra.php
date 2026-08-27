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
require_once 'mailer.php';
require_once 'helpers/polizas_lib.php';
$user = requirePermisoApi('ver_inventario');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? 'lista';
$id     = (int)($_GET['id'] ?? 0);

// ── Helper: ¿puede editarse el encabezado / agregar partidas? ─
// Borrador: cualquiera con gestionar_inventario.
$ROLES_GESTIONAR_OC = ['dir_admin', 'administracion', 'dueno', 'desarrollo'];

// Abierta: roles con gestión de inventario (con o sin entregas).
function ocHeaderEsEditable($db, $oc_id, $rol) {
    global $ROLES_GESTIONAR_OC;
    $s = $db->prepare("SELECT estado FROM ordenes_compra WHERE id = ?");
    $s->execute([$oc_id]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    if ($row['estado'] === 'borrador') return true;
    if ($row['estado'] === 'abierta' && in_array($rol, $ROLES_GESTIONAR_OC)) return true;
    return false;
}

// ── Helper: ¿puede editarse/eliminarse esta partida? ──────────
// Borrador: cualquiera con gestionar_inventario.
// Abierta + roles gestión: solo si la partida no tiene material recibido.
function partidaEsEditable($db, $partida_id, $rol) {
    global $ROLES_GESTIONAR_OC;
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
    if ($row['estado'] === 'abierta' && in_array($rol, $ROLES_GESTIONAR_OC) && (float)$row['recibido'] === 0.0) return true;
    return false;
}

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {

    // OCs abiertas sin correo enviado (badge sidebar dir_admin)
    if ($accion === 'pendientes_envio') {
        if (!in_array($user['rol'], ['dir_admin','dueno','desarrollo']))
            jsonResponse(['total' => 0]);
        $s = $db->query("SELECT COUNT(*) FROM ordenes_compra WHERE estado='abierta' AND correo_enviado=0");
        jsonResponse(['total' => (int)$s->fetchColumn()]);
    }

    // Siguiente número de OC
    if ($accion === 'consecutivo') {
        $s = $db->query("SELECT ultimo + 1 AS siguiente FROM oc_consecutivo WHERE id = 1");
        $n = $s->fetchColumn();
        jsonResponse(['numero_oc' => 'APEX-' . str_pad($n, 4, '0', STR_PAD_LEFT)]);
    }

    // Calendario de pagos
    if ($accion === 'calendario_pagos') {
        // [C-8] Subconsultas agregadas independientes: el JOIN oc_partidas × oc_pagos
        // hacía fan-out (N partidas × M pagos) y multiplicaba subtotal y pagado.
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
                COALESCE(tp.subtotal_sin_iva, 0)    AS subtotal_sin_iva,
                COALESCE(tp.total_con_iva, 0)        AS total_con_iva,
                COALESCE(pg.pagado, 0)              AS pagado,
                DATEDIFF(oc.fecha_pago_programada, CURDATE()) AS dias_para_pago
            FROM ordenes_compra oc
            JOIN proveedores p ON p.id = oc.proveedor_id
            LEFT JOIN (
                SELECT orden_compra_id,
                       SUM(importe) AS subtotal_sin_iva,
                       SUM(CASE WHEN iva_incluido = 1 THEN importe ELSE importe * 1.16 END) AS total_con_iva
                FROM oc_partidas
                GROUP BY orden_compra_id
            ) tp ON tp.orden_compra_id = oc.id
            LEFT JOIN (
                SELECT orden_compra_id, SUM(monto) AS pagado
                FROM oc_pagos
                GROUP BY orden_compra_id
            ) pg ON pg.orden_compra_id = oc.id
            WHERE oc.estado IN ('cerrada','abierta')
              AND oc.fecha_pago_programada IS NOT NULL
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

    // Archivos / comprobantes de una OC
    if ($accion === 'archivos' && $id) {
        $stmt = $db->prepare("SELECT id, nombre, ruta, subido_por, created_at FROM oc_archivos WHERE oc_id = ? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
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
            oc.estado, oc.fecha_pago_programada, oc.notas, oc.correo_enviado,
            p.nombre                            AS proveedor,
            COUNT(DISTINCT op.id)               AS num_partidas,
            COALESCE(SUM(op.importe), 0)        AS subtotal_sin_iva,
            COALESCE(SUM(CASE WHEN op.iva_incluido = 1 THEN op.importe ELSE op.importe * 1.16 END), 0) AS total_con_iva,
            COALESCE(SUM(op.cantidad_recibida), 0) AS total_recibido,
            COALESCE(SUM(op.cantidad), 0)           AS total_ordenado,
            DATEDIFF(oc.fecha_pago_programada, CURDATE()) AS dias_para_pago,
            -- subconsulta independiente (no JOIN) para no repetir el fan-out de partidas×pagos que ya se corrigió en calendario_pagos (C-8)
            (SELECT COALESCE(SUM(pg.monto), 0) FROM oc_pagos pg WHERE pg.orden_compra_id = oc.id) AS pagado_total,
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
    requirePermisoApi('gestionar_inventario');
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
        if ($tipo === 'suministro' && !$categoria) jsonResponse(['error' => 'Categoría requerida'], 422);

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
        // [A-12] Tipo limitado al catálogo real; default 'otro' (una partida sin
        // tipo explícito no debe nacer como lámina)
        $tipo       = $body['tipo'] ?? 'otro';
        if (!in_array($tipo, ['lamina', 'flete', 'otro'], true)) $tipo = 'otro';
        $lamina_id  = $tipo === 'lamina' ? (int)($body['lamina_id'] ?? 0) : null;
        $oc_ref_id  = $tipo === 'flete'  ? (int)($body['oc_referencia_id'] ?? 0) : null;
        $desc       = trim($body['descripcion'] ?? '');
        $unidad     = strtoupper(trim($body['unidad'] ?? 'LAMINA'));
        $cantidad   = (float)($body['cantidad'] ?? 0);
        $precio     = (float)($body['precio_unitario'] ?? 0);

        // [A-3] Cantidad y precio deben ser positivos
        if (!$oc_id || !$desc || $cantidad <= 0 || $precio <= 0)
            jsonResponse(['error' => 'Faltan campos requeridos (cantidad y precio deben ser mayores a 0)'], 422);

        // [A-12] Partida tipo lámina: lamina_id obligatorio, existente y activo.
        // Sin esto la OC cerraba sin crear stock en silencio al recibirla.
        if ($tipo === 'lamina') {
            if (!$lamina_id)
                jsonResponse(['error' => 'Selecciona la lámina de la partida'], 422);
            $sl = $db->prepare("SELECT id FROM laminas WHERE id = ? AND activo = 1");
            $sl->execute([$lamina_id]);
            if (!$sl->fetch())
                jsonResponse(['error' => 'La lámina seleccionada no existe o está inactiva'], 422);
        }

        // [M-3] Flag opcional: si no viene, 0 = precio sin IVA (comportamiento actual)
        $iva_incluido = !empty($body['iva_incluido']) ? 1 : 0;

        // EC-3: MAX(numero_partida)+1 sin lock ni UNIQUE — dos requests agregando
        // partida a la misma OC al mismo tiempo podían calcular el mismo número.
        // En vez de un ALTER TABLE (índice UNIQUE), se serializa con el mismo patrón
        // FOR UPDATE que ya usa el resto del módulo (recepciones, pagos): el lock de
        // fila sobre la OC padre obliga a que el segundo request espere a que el
        // primero termine su transacción antes de leer el MAX.
        $db->beginTransaction();
        try {
            $db->prepare("SELECT id FROM ordenes_compra WHERE id=? FOR UPDATE")->execute([$oc_id]);

            $n = $db->prepare("SELECT COALESCE(MAX(numero_partida),0)+1 FROM oc_partidas WHERE orden_compra_id=?");
            $n->execute([$oc_id]);
            $num = $n->fetchColumn();

            $s = $db->prepare("INSERT INTO oc_partidas
                (orden_compra_id, numero_partida, tipo, lamina_id, oc_referencia_id,
                 descripcion, unidad, cantidad, precio_unitario, iva_incluido)
                VALUES (?,?,?,?,?,?,?,?,?,?)");
            $s->execute([$oc_id, $num, $tipo, $lamina_id, $oc_ref_id ?: null,
                         $desc, $unidad, $cantidad, $precio, $iva_incluido]);
            $nuevoId = $db->lastInsertId();
            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'No se pudo agregar la partida: ' . $e->getMessage()], 500);
        }
        jsonResponse(['ok' => true, 'id' => $nuevoId]);
    }

    // ── Registrar entrega ─────────────────────────────────────
    if ($accion === 'registrar_entrega') {
        $oc_id        = (int)($body['orden_compra_id'] ?? 0);
        $fecha        = $body['fecha_entrega'] ?? date('Y-m-d');
        $notas        = trim($body['notas'] ?? '');
        $detalle      = $body['detalle'] ?? []; // [{oc_partida_id, cantidad_recibida}]

        if (!$oc_id || empty($detalle))
            jsonResponse(['error' => 'Faltan datos de entrega'], 422);

        // [C-7] Solo se recibe mercancía de una OC viva. Se permite 'pagada'
        // porque el flujo pagar-antes-de-recibir es válido; el cierre (abajo)
        // tiene guard AND estado='abierta' y nunca la degrada a 'cerrada'.
        $soce = $db->prepare("SELECT estado FROM ordenes_compra WHERE id = ?");
        $soce->execute([$oc_id]);
        $estado_oc = $soce->fetchColumn();
        if (!$estado_oc)
            jsonResponse(['error' => 'OC no encontrada'], 404);
        if (!in_array($estado_oc, ['abierta', 'pagada'], true))
            jsonResponse(['error' => 'No se pueden registrar entregas en una OC ' . $estado_oc], 422);

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
            if ((int)$shf->fetchColumn() > 0) $flete_tipo_vidrio = 'cobrado';
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
            // [C-7] Guard atómico por partida: solo suma si la partida pertenece
            // a esta OC y no excede lo ordenado. Si rowCount=0, la recepción es
            // inválida (sobre-recepción, doble recepción concurrente o partida
            // de otra OC) y se revierte TODA la entrega.
            $su = $db->prepare("UPDATE oc_partidas
                SET cantidad_recibida = cantidad_recibida + ?
                WHERE id = ? AND orden_compra_id = ?
                  AND cantidad_recibida + ? <= cantidad");

            // [IVA-1] totalRecepcion sigue siendo el valor SIN IVA (va a 1.3
            // Inventario, nunca debe llevar impuesto capitalizado). totalIva
            // es el crédito fiscal (1.4 IVA Acreditable) y totalConIva es lo
            // que realmente se le debe al proveedor (2.1 CxP) — antes CxP se
            // abonaba sin IVA en la recepción pero se cargaba con IVA en el
            // pago, dejando un residuo permanente del 16% sin cuadrar.
            $totalRecepcion = 0;
            $totalIva       = 0;
            $totalConIva    = 0;
            $precioPartidaStmt = $db->prepare("SELECT precio_unitario, iva_incluido FROM oc_partidas WHERE id = ? AND orden_compra_id = ?");

            foreach ($detalle as $d) {
                $partida_id = (int)($d['oc_partida_id'] ?? 0);
                $cant       = (float)($d['cantidad_recibida'] ?? 0);
                if (!$partida_id)
                    throw new Exception('Detalle de entrega con partida inválida', 422);
                // [A-3] Cantidades deben ser positivas (un negativo restaba stock)
                if ($cant <= 0)
                    throw new Exception('La cantidad recibida debe ser mayor a 0 (partida #' . $partida_id . ')', 422);

                $precioPartidaStmt->execute([$partida_id, $oc_id]);
                $pInfo   = $precioPartidaStmt->fetch();
                $importe = (float)($pInfo['precio_unitario'] ?? 0) * $cant;
                if (!empty($pInfo['iva_incluido'])) {
                    $importeSinIva = $importe / 1.16;
                    $importeConIva = $importe;
                } else {
                    $importeSinIva = $importe;
                    $importeConIva = $importe * 1.16;
                }
                $totalRecepcion += $importeSinIva;
                $totalConIva    += $importeConIva;
                $totalIva       += $importeConIva - $importeSinIva;

                $sd->execute([$entrega_id, $partida_id, $cant]);
                $su->execute([$cant, $partida_id, $oc_id, $cant]);
                if ($su->rowCount() === 0)
                    throw new Exception('La partida #' . $partida_id . ' no pertenece a la OC o la cantidad excede lo pendiente por recibir', 422);

                // [A-11] La entrada a inventario se decide POR PARTIDA (tipo='lamina'),
                // no por OC: una OC mixta (lámina + flete) ya no deja de crear stock.
                // [A-11] La lectura valida que la partida pertenece a ESTA OC
                // (AND orden_compra_id = ?) — no se puede recibir contra la
                // partida de otra OC e inflar inventario.
                // [C-9] costo_real_unitario es columna GENERATED (precio_unitario +
                // costo_flete_total/cantidad_laminas) — MySQL la calcula sola, no se
                // inserta explícito (INSERT directo sobre ella falla: error 1906).
                $sp2 = $db->prepare("SELECT lamina_id, precio_unitario FROM oc_partidas
                    WHERE id = ? AND orden_compra_id = ? AND tipo = 'lamina'");
                $sp2->execute([$partida_id, $oc_id]);
                $partida = $sp2->fetch();
                if ($partida) {
                    // [A-12] Partida lámina sin lámina asociada: fallar la entrega
                    // en vez de cerrar la OC en silencio sin crear stock.
                    if (!$partida['lamina_id'])
                        throw new Exception('La partida #' . $partida_id . ' es tipo lámina y no tiene lamina_id; corrige la partida antes de recibir', 422);
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

                // [Alto#8 fix] Si esta línea del detalle es la partida de flete (no
                // una de lámina), distribuir SOLO el importe correspondiente a lo
                // recién recibido en ESTA entrega ($cant), no el total ordenado de
                // la partida completa. Antes se recalculaba con `precio_unitario *
                // cantidad` (el total ordenado) en CADA entrega parcial de la OC de
                // flete — 2 entregas parciales duplicaban el costo_flete_total
                // completo sobre las láminas de la OC de vidrio vinculada.
                if ($es_oc_flete) {
                    $sf = $db->prepare("SELECT precio_unitario, oc_referencia_id FROM oc_partidas
                        WHERE id = ? AND orden_compra_id = ? AND tipo = 'flete' AND oc_referencia_id IS NOT NULL");
                    $sf->execute([$partida_id, $oc_id]);
                    $fletePartida = $sf->fetch();
                    if ($fletePartida) {
                        $glass_oc_id     = (int)$fletePartida['oc_referencia_id'];
                        // precio_unitario es POR UNIDAD (mismo criterio que el resto de
                        // oc_partidas: importe total de la línea = precio_unitario × cantidad
                        // ordenada) — el importe de ESTA entrega es precio_unitario × $cant
                        // recibido ahora, no × la cantidad total ordenada de la partida.
                        $importeEsteRecibo = (float)$fletePartida['precio_unitario'] * $cant;

                        $stl = $db->prepare("SELECT COALESCE(SUM(cantidad_laminas), 0) FROM inventario_compras WHERE orden_compra_id = ?");
                        $stl->execute([$glass_oc_id]);
                        $total_laminas = (float)$stl->fetchColumn();
                        if ($total_laminas > 0) {
                            $flete_x_lamina = $importeEsteRecibo / $total_laminas;
                            // Se sigue acumulando (+=) a propósito — así 2 OCs de flete
                            // DISTINTAS ligadas a la misma OC de vidrio sí se suman entre
                            // sí; lo que se corrigió es que la MISMA OC de flete ya no
                            // vuelve a sumar su propio importe completo en cada entrega.
                            $sup = $db->prepare("
                                UPDATE inventario_compras
                                SET costo_flete_total = costo_flete_total + ? * cantidad_laminas,
                                    flete_tipo        = 'cobrado',
                                    flete_oc_id       = ?
                                WHERE orden_compra_id = ?
                            ");
                            $sup->execute([$flete_x_lamina, $oc_id, $glass_oc_id]);
                        }
                    }
                }
            }

            // [flete/otro auto-completo] El flete (y ajustes tipo 'otro') son
            // servicios, no mercancía física — la UI de "Registrar entrega"
            // solo ofrece partidas tipo 'lamina' para recibir (no hay forma de
            // marcarlos a mano). Regla de negocio: se dan por completados al
            // 100% en el momento en que TODO el material (partidas 'lamina')
            // de la OC ya se recibió; sin esto la OC nunca cerraba y el pago
            // nunca se desbloqueaba.
            $sLamPend = $db->prepare("
                SELECT COALESCE(SUM(cantidad),0) - COALESCE(SUM(cantidad_recibida),0)
                FROM oc_partidas WHERE orden_compra_id = ? AND tipo = 'lamina'
            ");
            $sLamPend->execute([$oc_id]);
            if ((float)$sLamPend->fetchColumn() <= 0) {
                $db->prepare("
                    UPDATE oc_partidas SET cantidad_recibida = cantidad
                    WHERE orden_compra_id = ? AND tipo IN ('flete','otro') AND cantidad_recibida < cantidad
                ")->execute([$oc_id]);
            }

            // [A-11] Verificar si la OC quedo completa evaluando TODAS las
            // partidas (lámina, flete y otro). Antes, si existía CUALQUIER
            // partida de flete, la OC entera se trataba como flete: cerraba
            // sin recibir las láminas y sin crear su stock.
            $sc = $db->prepare("
                SELECT COALESCE(SUM(cantidad),0) - COALESCE(SUM(cantidad_recibida),0) AS pendiente
                FROM oc_partidas WHERE orden_compra_id = ?
            ");
            $sc->execute([$oc_id]);
            $pendiente = (float)$sc->fetchColumn();

            $cierra = $pendiente <= 0;
            if ($cierra) {
                $db->prepare("UPDATE oc_entregas SET cierra_oc=1 WHERE id=?")->execute([$entrega_id]);
                // [C-7] Solo transiciona desde 'abierta': una OC ya 'pagada'
                // conserva su estado y su fecha de pago programada.
                $db->prepare("
                    UPDATE ordenes_compra
                    SET estado = 'cerrada',
                        fecha_pago_programada = DATE_ADD(?, INTERVAL dias_credito DAY)
                    WHERE id = ? AND estado = 'abierta'
                ")->execute([$fecha, $oc_id]);
            }

            if ($totalRecepcion > 0) {
                $inventario     = pl_cuentaId($db, '1.3');
                $cxp            = pl_cuentaId($db, '2.1');
                $ivaAcreditable = pl_cuentaId($db, '1.4');
                if ($inventario && $cxp && $ivaAcreditable) {
                    // [IVA-1] 3 líneas: Inventario sin IVA + IVA Acreditable (crédito
                    // fiscal) = CxP con IVA (lo que realmente se le debe al proveedor).
                    // Antes CxP se abonaba sin IVA aquí pero se cargaba con IVA al
                    // pagar (registrar_pago usa monto_base homologado con IVA) — la
                    // cuenta nunca cuadraba a $0 en una OC ya pagada por completo.
                    // Redondeo: IVA se calcula como diferencia entre los otros dos
                    // YA redondeados, para que Debe=Haber exacto y pl_crearPoliza
                    // nunca rechace la póliza por un centavo de residuo.
                    $rSinIva  = round($totalRecepcion, 2);
                    $rConIva  = round($totalConIva, 2);
                    $rIva     = round($rConIva - $rSinIva, 2);
                    $lineas = [
                        [$inventario, $rSinIva, 0, 'Recepción OC #' . $oc_id . ' (sin IVA)'],
                    ];
                    if ($rIva > 0) {
                        $lineas[] = [$ivaAcreditable, $rIva, 0, 'IVA acreditable OC #' . $oc_id];
                    }
                    $lineas[] = [$cxp, 0, $rConIva, 'Recepción OC #' . $oc_id . ' (con IVA)'];
                    pl_generarAutomatica($db, 'oc_entregas', (int)$entrega_id, 'diario', $fecha,
                        'Recepción de mercancía OC #' . $oc_id, $lineas, (int)$user['id']);
                }
            }

            $db->commit();
            jsonResponse(['ok' => true, 'entrega_id' => $entrega_id, 'cerro_oc' => $cierra]);
        } catch (Exception $e) {
            $db->rollBack();
            // Los throws de validación traen código HTTP int (404/422);
            // PDOException trae SQLSTATE string → 500
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            jsonResponse(['error' => $e->getMessage()], $code);
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

        if (!$oc_id || $monto <= 0) jsonResponse(['error' => 'Faltan datos'], 422);

        $db->beginTransaction();
        try {
            // [M-2] Validar estado de la OC con lock de fila: serializa pagos
            // concurrentes sobre la misma OC (el saldo se calcula protegido).
            $soc = $db->prepare("SELECT id, estado FROM ordenes_compra WHERE id = ? FOR UPDATE");
            $soc->execute([$oc_id]);
            $oc = $soc->fetch();
            if (!$oc)
                throw new Exception('OC no encontrada', 404);
            if (!in_array($oc['estado'], ['abierta', 'cerrada'], true))
                throw new Exception('No se pueden registrar pagos en una OC ' . $oc['estado'], 422);

            // Anti-doble-clic: mismo pago (OC+monto) registrado hace <8s = envío duplicado
            $stmt_dup = $db->prepare("SELECT id FROM oc_pagos
                WHERE orden_compra_id = ? AND monto = ?
                  AND created_at >= (NOW() - INTERVAL 8 SECOND) LIMIT 1");
            $stmt_dup->execute([$oc_id, $monto]);
            if ($stmt_dup->fetch())
                throw new Exception('Este pago ya se registró hace unos segundos (posible doble clic). Revisa el historial antes de reintentar.', 422);

            // [M-2] Saldo homologado a base CON IVA:
            //   pago con incluye_iva=1 → el monto ya es con IVA
            //   pago con incluye_iva=0 → el monto es sin IVA, se homologa ×1.16
            // Antes se sumaban montos de ambas bases mezclados y se comparaban
            // contra el total con IVA: los pagos "sin IVA" cerraban la OC antes
            // de tiempo (o nunca) y no había tope de sobrepago.
            $st = $db->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN op.iva_incluido = 1 THEN op.importe ELSE op.importe * 1.16 END), 0) AS total_con_iva,
                    COALESCE((SELECT SUM(CASE WHEN pg.incluye_iva = 1 THEN pg.monto ELSE pg.monto * 1.16 END)
                              FROM oc_pagos pg WHERE pg.orden_compra_id = ?), 0) AS pagado_con_iva
                FROM oc_partidas op WHERE op.orden_compra_id = ?
            ");
            $st->execute([$oc_id, $oc_id]);
            $res            = $st->fetch();
            $total_con_iva  = (float)$res['total_con_iva'];
            $pagado_con_iva = (float)$res['pagado_con_iva'];
            $monto_base     = $inc_iva ? $monto : $monto * 1.16;
            $saldo          = $total_con_iva - $pagado_con_iva;

            // [M-2] Rechazar sobrepago (tolerancia de 1 centavo por redondeo)
            if ($monto_base > $saldo + 0.01)
                throw new Exception('El pago excede el saldo pendiente de la OC ($' . number_format(max($saldo, 0), 2) . ' con IVA)', 422);

            $db->prepare("INSERT INTO oc_pagos
                (orden_compra_id, fecha_pago, monto, incluye_iva, referencia, notas, created_by)
                VALUES (?,?,?,?,?,?,?)")
               ->execute([$oc_id, $fecha, $monto, $inc_iva, $referencia, $notas, $user['id']]);
            $pagoOcId = $db->lastInsertId();

            $cxp    = pl_cuentaId($db, '2.1');
            $bancos = pl_cuentaId($db, '1.1');
            if ($cxp && $bancos) {
                pl_generarAutomatica($db, 'oc_pagos', (int)$pagoOcId, 'egresos', $fecha,
                    'Pago a proveedor OC #' . $oc_id,
                    [[$cxp, $monto_base, 0, 'Pago OC #' . $oc_id], [$bancos, 0, $monto_base, 'Pago OC #' . $oc_id]],
                    (int)$user['id']);
            }

            // Cierre a 'pagada' solo cuando el saldo queda cubierto y solo
            // desde estados vivos (nunca reabrir/reescribir una cancelada)
            if ($pagado_con_iva + $monto_base >= $total_con_iva - 0.01) {
                $db->prepare("UPDATE ordenes_compra SET estado='pagada' WHERE id=? AND estado IN ('abierta','cerrada')")
                   ->execute([$oc_id]);
            }

            $db->commit();
            jsonResponse(['ok' => true]);
        } catch (Exception $e) {
            $db->rollBack();
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            jsonResponse(['error' => $e->getMessage()], $code);
        }
    }

    // ── Distribuir flete de OC sin referencia a inventario ───
    if ($accion === 'distribuir_flete') {
        requirePermisoApi('gestionar_inventario');
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

        // BLO-02: total con/sin IVA del flete, mismo criterio ya usado en
        // registrar_pago (L.664) — necesario para la póliza de abajo.
        $sf = $db->prepare("
            SELECT COALESCE(SUM(precio_unitario * cantidad), 0) AS total_flete,
                   COALESCE(SUM(CASE WHEN iva_incluido = 1 THEN precio_unitario * cantidad / 1.16
                                      ELSE precio_unitario * cantidad END), 0) AS total_flete_sin_iva,
                   COALESCE(SUM(CASE WHEN iva_incluido = 1 THEN precio_unitario * cantidad
                                      ELSE precio_unitario * cantidad * 1.16 END), 0) AS total_flete_con_iva
            FROM oc_partidas WHERE orden_compra_id = ? AND tipo = 'flete'
        ");
        $sf->execute([$oc_id]);
        $fleteRow          = $sf->fetch();
        $total_flete       = (float)$fleteRow['total_flete'];
        $total_flete_sinI  = round((float)$fleteRow['total_flete_sin_iva'], 2);
        $total_flete_conI  = round((float)$fleteRow['total_flete_con_iva'], 2);
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
            // BLO-02: acumular (+=), no sobrescribir — antes SET costo_flete_total=?
            // destruía cualquier flete ya capturado en registrar_compra o ya
            // distribuido por otra OC (columna GENERATED costo_real_unitario
            // dependía de ese dato). El estado='abierta' exigido arriba ya evita
            // que ESTA misma OC se distribuya dos veces (transiciona a 'cerrada').
            $sup = $db->prepare("
                UPDATE inventario_compras
                SET costo_flete_total = costo_flete_total + ?,
                    flete_tipo        = 'cobrado',
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

            // BLO-02: generar el registro de entrega — antes la OC de flete cerraba
            // sin ningún oc_entregas, dejando imposible auditar cuándo se recibió.
            $seF = $db->prepare("INSERT INTO oc_entregas
                (orden_compra_id, fecha_entrega, notas, created_by, cierra_oc)
                VALUES (?, CURDATE(), ?, ?, 1)");
            $seF->execute([$oc_id, 'Distribución de flete a ' . count($compras_data) . ' compra(s)', $user['id']]);
            $entregaFleteId = $db->lastInsertId();

            $db->prepare("
                UPDATE ordenes_compra
                SET estado = 'cerrada',
                    fecha_pago_programada = DATE_ADD(CURDATE(), INTERVAL dias_credito DAY)
                WHERE id = ?
            ")->execute([$oc_id]);

            // BLO-02: póliza (Inventario/IVA Acreditable/CxP) — antes el abono a
            // CxP del flete nunca se registraba, pero registrar_pago sí lo carga
            // al pagar la OC: la cuenta 2.1 quedaba descuadrada permanentemente.
            $inventario     = pl_cuentaId($db, '1.3');
            $cxp            = pl_cuentaId($db, '2.1');
            $ivaAcreditable = pl_cuentaId($db, '1.4');
            if ($inventario && $cxp && $ivaAcreditable) {
                $rIva = round($total_flete_conI - $total_flete_sinI, 2);
                $lineas = [
                    [$inventario, $total_flete_sinI, 0, 'Distribución de flete OC #' . $oc_id . ' (sin IVA)'],
                ];
                if ($rIva > 0) {
                    $lineas[] = [$ivaAcreditable, $rIva, 0, 'IVA acreditable flete OC #' . $oc_id];
                }
                $lineas[] = [$cxp, 0, $total_flete_conI, 'Distribución de flete OC #' . $oc_id . ' (con IVA)'];
                pl_generarAutomatica($db, 'oc_entregas', (int)$entregaFleteId, 'diario', date('Y-m-d'),
                    'Distribución de flete OC #' . $oc_id, $lineas, (int)$user['id']);
            }

            $db->commit();
            jsonResponse(['ok' => true, 'total_flete' => $total_flete, 'registros' => count($compras_data)]);
        } catch (Exception $e) {
            $db->rollBack();
            // Los throws de validación traen código HTTP int (404/422);
            // PDOException trae SQLSTATE string → 500
            $code = (is_int($e->getCode()) && $e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
            jsonResponse(['error' => $e->getMessage()], $code);
        }
    }

    // ── Cambiar estado manualmente ────────────────────────────
    if ($accion === 'cambiar_estado') {
        // LN-6: antes solo heredaba el gate del módulo (ver_inventario, línea 13),
        // que 'comercial' también tiene sin gestionar_inventario — cualquier
        // comercial podía saltar una OC a 'pagada' vía API directa.
        requirePermisoApi('gestionar_inventario');

        $oc_id  = (int)($body['orden_compra_id'] ?? 0);
        $estado = $body['estado'] ?? '';
        $motivo = trim($body['motivo'] ?? '');
        $validos = ['borrador','abierta','cerrada','pagada'];
        if (!$oc_id || !in_array($estado, $validos))
            jsonResponse(['error' => 'Datos inválidos'], 422);

        $stAct = $db->prepare("SELECT numero_oc, estado FROM ordenes_compra WHERE id = ?");
        $stAct->execute([$oc_id]);
        $ocAct = $stAct->fetch(PDO::FETCH_ASSOC);
        if (!$ocAct) jsonResponse(['error' => 'Orden de compra no encontrada'], 404);
        $estadoActual = $ocAct['estado'];

        // LN-6: máquina de estados — adelante es libre entre pasos consecutivos
        // (saltar borrador->pagada directo sigue sin tener sentido de negocio);
        // hacia atrás (reabrir) es un override que requiere motivo y solo lo
        // pueden hacer los roles de mayor confianza del módulo.
        $ORDEN_ESTADOS = ['borrador' => 0, 'abierta' => 1, 'cerrada' => 2, 'pagada' => 3];
        $esReversa = $ORDEN_ESTADOS[$estado] < $ORDEN_ESTADOS[$estadoActual];
        if ($estado === $estadoActual) {
            jsonResponse(['error' => 'La OC ya está en estado "' . $estado . '"'], 422);
        }
        if (!$esReversa && ($ORDEN_ESTADOS[$estado] - $ORDEN_ESTADOS[$estadoActual]) > 1) {
            jsonResponse(['error' => 'No se puede saltar de "' . $estadoActual . '" a "' . $estado . '" directo — solo un paso a la vez.'], 422);
        }
        if ($esReversa) {
            if (!in_array($user['rol'], ['dir_admin', 'dueno', 'desarrollo'])) {
                jsonResponse(['error' => 'Solo Dirección puede reabrir una OC (' . $estadoActual . ' → ' . $estado . ')'], 403);
            }
            if ($motivo === '') {
                jsonResponse(['error' => 'Para reabrir una OC (' . $estadoActual . ' → ' . $estado . ') debes escribir el motivo.'], 422);
            }
        }

        $db->prepare("UPDATE ordenes_compra SET estado=? WHERE id=?")->execute([$estado, $oc_id]);

        // LN-6: bitácora siempre — quién, cuándo, de dónde a dónde y por qué (si es reversa)
        $db->prepare("INSERT INTO correcciones_log
            (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, motivo, usuario)
            VALUES ('orden_compra', ?, ?, 'estado', ?, ?, ?, ?)")
           ->execute([
               $oc_id, $ocAct['numero_oc'], $estadoActual, $estado,
               $esReversa ? $motivo : ('Avance normal de flujo por ' . $user['rol']),
               $user['nombre']
           ]);

        // Auto-envío cuando dir_admin/dueno abre una OC
        $correo_enviado = false;
        if ($estado === 'abierta' && in_array($user['rol'], ['dir_admin','dueno','desarrollo'])) {
            $soc = $db->prepare("
                SELECT oc.numero_oc, oc.notas,
                       p.nombre AS proveedor_nombre,
                       COALESCE(SUM(CASE WHEN op.iva_incluido = 1 THEN op.importe ELSE op.importe * 1.16 END),0) AS total_con_iva
                FROM ordenes_compra oc
                JOIN proveedores p ON p.id = oc.proveedor_id
                LEFT JOIN oc_partidas op ON op.orden_compra_id = oc.id
                WHERE oc.id = ?
                GROUP BY oc.id
            ");
            $soc->execute([$oc_id]);
            $oc_data = $soc->fetch();
            // Buscar primer archivo adjunto
            $saf = $db->prepare("SELECT ruta FROM oc_archivos WHERE oc_id=? ORDER BY created_at ASC LIMIT 1");
            $saf->execute([$oc_id]);
            $archivo = $saf->fetchColumn();
            $archivo_path = $archivo ? __DIR__ . '/../archivos_oc/' . basename($archivo) : null;

            $result = enviarCorreoOC($oc_data, $archivo_path);
            if ($result['ok']) {
                $db->prepare("UPDATE ordenes_compra SET correo_enviado=1, correo_enviado_at=NOW() WHERE id=?")
                   ->execute([$oc_id]);
                $correo_enviado = true;
            } else {
                error_log('APEX OC correo fallido OC#' . $oc_id . ': ' . ($result['error'] ?? ''));
            }
        }
        jsonResponse(['ok' => true, 'correo_enviado' => $correo_enviado]);
    }

    // ── Enviar correo OC manualmente (dir_admin) ──────────────
    if ($accion === 'enviar_correo') {
        if (!in_array($user['rol'], ['dir_admin','dueno','desarrollo']))
            jsonResponse(['error' => 'Sin permiso'], 403);
        $oc_id = (int)($body['orden_compra_id'] ?? 0);
        if (!$oc_id) jsonResponse(['error' => 'orden_compra_id requerido'], 422);

        $soc = $db->prepare("
            SELECT oc.numero_oc, oc.notas,
                   p.nombre AS proveedor_nombre,
                   COALESCE(SUM(CASE WHEN op.iva_incluido = 1 THEN op.importe ELSE op.importe * 1.16 END),0) AS total_con_iva
            FROM ordenes_compra oc
            JOIN proveedores p ON p.id = oc.proveedor_id
            LEFT JOIN oc_partidas op ON op.orden_compra_id = oc.id
            WHERE oc.id = ?
            GROUP BY oc.id
        ");
        $soc->execute([$oc_id]);
        $oc_data = $soc->fetch();
        if (!$oc_data) jsonResponse(['error' => 'OC no encontrada'], 404);

        $saf = $db->prepare("SELECT ruta FROM oc_archivos WHERE oc_id=? ORDER BY created_at ASC LIMIT 1");
        $saf->execute([$oc_id]);
        $archivo = $saf->fetchColumn();
        $archivo_path = $archivo ? __DIR__ . '/../archivos_oc/' . basename($archivo) : null;

        $result = enviarCorreoOC($oc_data, $archivo_path);
        if ($result['ok']) {
            $db->prepare("UPDATE ordenes_compra SET correo_enviado=1, correo_enviado_at=NOW() WHERE id=?")
               ->execute([$oc_id]);
            jsonResponse(['ok' => true]);
        } else {
            jsonResponse(['error' => 'Error al enviar: ' . ($result['error'] ?? 'desconocido')], 500);
        }
    }

    // ── Subir archivo / comprobante ────────────────────────────
    if ($accion === 'subir_archivo') {
        global $ROLES_GESTIONAR_OC;
        if (!in_array($user['rol'], $ROLES_GESTIONAR_OC))
            jsonResponse(['error' => 'Sin permiso'], 403);

        $oc_id = (int)($_POST['oc_id'] ?? 0);
        if (!$oc_id) jsonResponse(['error' => 'oc_id requerido'], 400);

        if (empty($_FILES['archivo']['tmp_name']))
            jsonResponse(['error' => 'No se recibió archivo'], 400);

        $ext_permitidas = ['pdf','jpg','jpeg','png','webp'];
        $nombre_original = $_FILES['archivo']['name'];
        $ext = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        if (!in_array($ext, $ext_permitidas))
            jsonResponse(['error' => 'Tipo de archivo no permitido'], 400);

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($_FILES['archivo']['tmp_name']);
        $mimes_ok = ['application/pdf','image/jpeg','image/png','image/webp'];
        if (!in_array($mime, $mimes_ok))
            jsonResponse(['error' => 'Tipo de archivo no válido'], 400);

        $nombre_unico = 'oc_' . $oc_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = __DIR__ . '/../archivos_oc/' . $nombre_unico;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino))
            jsonResponse(['error' => 'Error al guardar el archivo'], 500);

        $stmt = $db->prepare("INSERT INTO oc_archivos (oc_id, nombre, ruta, subido_por) VALUES (?,?,?,?)");
        $stmt->execute([$oc_id, $nombre_original, $nombre_unico, $user['nombre']]);

        jsonResponse(['ok' => true, 'id' => $db->lastInsertId(), 'nombre' => $nombre_original, 'ruta' => $nombre_unico]);
    }

    jsonResponse(['error' => 'Acción no válida'], 400);
}

// ── PUT ──────────────────────────────────────────────────────
if ($method === 'PUT') {
    requirePermisoApi('gestionar_inventario');
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
        $tipoActual = $db->prepare("SELECT tipo FROM ordenes_compra WHERE id = ?");
        $tipoActual->execute([$id]);
        if ($tipoActual->fetchColumn() === 'suministro' && !$categoria)
            jsonResponse(['error' => 'Categoría requerida'], 422);
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
        // BLO-01: chequeo falsy dejaba pasar cantidad/precio negativos (truthy en PHP) —
        // mismo criterio que ya usa agregar_partida (A-3, L.320).
        if (!$desc || $cant <= 0 || $precio <= 0) jsonResponse(['error' => 'Faltan campos requeridos (cantidad y precio deben ser mayores a 0)'], 422);
        $db->prepare("UPDATE oc_partidas SET descripcion=?, unidad=?, cantidad=?, precio_unitario=?
            WHERE id=?")
           ->execute([$desc, $unidad, $cant, $precio, $id]);
        jsonResponse(['ok' => true]);
    }

    jsonResponse(['error' => 'Acción no válida'], 400);
}

// ── DELETE ───────────────────────────────────────────────────
if ($method === 'DELETE') {
    requirePermisoApi('gestionar_inventario');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = $body['accion'] ?? ($_GET['accion'] ?? 'eliminar_partida');
    $id     = (int)($body['id'] ?? ($_GET['id'] ?? 0));

    if ($accion === 'eliminar_partida' && $id) {
        if (!partidaEsEditable($db, $id, $user['rol']))
            jsonResponse(['error' => 'No puedes eliminar esta partida: ya tiene material recibido o no tienes permiso'], 403);
        $db->prepare("DELETE FROM oc_partidas WHERE id = ?")
           ->execute([$id]);
        jsonResponse(['ok' => true]);
    }

    // ── Eliminar archivo / comprobante ─────────────────────────
    if ($accion === 'eliminar_archivo' && $id) {
        global $ROLES_GESTIONAR_OC;
        if (!in_array($user['rol'], $ROLES_GESTIONAR_OC))
            jsonResponse(['error' => 'Sin permiso'], 403);

        $stmt = $db->prepare("SELECT ruta FROM oc_archivos WHERE id = ?");
        $stmt->execute([$id]);
        $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$archivo) jsonResponse(['error' => 'Archivo no encontrado'], 404);

        $ruta = __DIR__ . '/../archivos_oc/' . basename($archivo['ruta']);
        if (file_exists($ruta)) unlink($ruta);

        $db->prepare("DELETE FROM oc_archivos WHERE id = ?")->execute([$id]);
        jsonResponse(['ok' => true]);
    }

    jsonResponse(['error' => 'Acción no válida'], 400);
}

jsonResponse(['error' => 'Método no soportado'], 405);