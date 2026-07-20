<?php
// ============================================================
//  APEX GLASS - API: Cotizaciones
//  Archivo: api/cotizaciones.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');

$user           = requireSessionApi();
$rol            = $user['rol'];
$usuario_id     = $user['id'];
$usuario_nombre = $user['nombre'];
$method         = $_SERVER['REQUEST_METHOD'];
$db             = getDB();

$puede_editar = in_array($rol, ['dir_admin', 'dueno', 'comercial', 'desarrollo']);
$es_admin     = in_array($rol, ['dir_admin', 'dueno', 'desarrollo']);

require_once 'cotizacion_helpers.php';
require_once __DIR__ . '/helpers/totales.php'; // A-2: fórmula canónica de totales

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $id      = isset($_GET['id'])  ? (int)$_GET['id']  : null;
    $estatus = $_GET['estatus']    ?? '';
    $q       = trim($_GET['q']     ?? '');
    $limit   = min((int)($_GET['limit'] ?? 50), 1000);

    // Detalle completo con partidas
    if ($id) {
        $stmt = $db->prepare("
            SELECT c.*, cl.codigo as cliente_codigo,
                   o.folio AS orden_folio
            FROM cotizaciones c
            LEFT JOIN clientes cl ON c.cliente_id = cl.id
            LEFT JOIN ordenes o ON o.id = c.orden_id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $cot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cot) { jsonResponse(['error' => 'No encontrada']); exit; }

        // Recalcular total con la fórmula canónica (A-2) — ramifica suministro/maquila
        $tots = apexTotalesCotizacion($db, $id);
        if ($tots) {
            $cot['total'] = $tots['total'];
            $cot['saldo_pendiente'] = max(0, round($tots['total'] - (float)$cot['saldo_pagado'], 2));
        }

        $stmt2 = $db->prepare("
            SELECT cp.*, cr.nombre as cristal_nombre_actual
            FROM cotizaciones_partidas cp
            LEFT JOIN cristales cr ON cp.cristal_id = cr.id
            WHERE cp.cotizacion_id = ?
            ORDER BY cp.num_partida ASC
        ");
        $stmt2->execute([$id]);
        $partidas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Adjuntar servicios a cada partida — 1 sola query para todas
        $serviciosPorPartida = [];
        if ($partidas) {
            $pidIds = array_column($partidas, 'id');
            $inPh   = implode(',', array_fill(0, count($pidIds), '?'));
            $stmtSrv = $db->prepare("
                SELECT cps.*, sc.nombre as servicio_nombre
                FROM cotizacion_partida_servicios cps
                LEFT JOIN servicios_catalogo sc ON sc.id = cps.servicio_id
                WHERE cps.partida_id IN ($inPh)
                ORDER BY cps.partida_id ASC, cps.id ASC
            ");
            $stmtSrv->execute($pidIds);
            foreach ($stmtSrv->fetchAll(PDO::FETCH_ASSOC) as $srv) {
                $serviciosPorPartida[$srv['partida_id']][] = $srv;
            }
        }
        foreach ($partidas as &$par) {
            $par['servicios'] = $serviciosPorPartida[$par['id']] ?? [];
        }
        unset($par);

        $cot['partidas'] = $partidas;

        // Datos de rechazo si aplica
        if ($cot['estatus'] === 'rechazada') {
            $stmtR = $db->prepare("SELECT motivo, monto_devuelto, registrado_por, created_at FROM rechazo_calidad WHERE cotizacion_id = ? ORDER BY id DESC LIMIT 1");
            $stmtR->execute([$id]);
            $cot['rechazo'] = $stmtR->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        jsonResponse($cot); exit;
    }

    // Lista
    $where  = ['1=1'];
    $params = [];
    // Filtrar por asesor si el rol es comercial
    if ($rol === 'comercial' && $usuario_nombre) {
        $where[]  = 'c.asesor_nombre LIKE ?';
        $params[] = '%' . $usuario_nombre . '%';
    }
    if ($estatus) { $where[] = 'c.estatus = ?'; $params[] = $estatus; }
    if ($q) {
        $where[]  = '(c.folio LIKE ? OR c.cliente_nombre LIKE ? OR c.proyecto LIKE ? OR o.folio LIKE ?)';
        $like     = "%$q%";
        $params   = array_merge($params, [$like, $like, $like, $like]);
    }
    $where_str = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT c.id, c.folio, c.fecha, c.cliente_nombre, c.asesor_nombre,
               c.proyecto, c.estatus,
               ROUND((COALESCE(cp_sums.bruto, 0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16, 2) AS total,
               c.fecha_entrega, c.localidad, c.ciudad_destino, c.condicion_pago,
               GREATEST(0, ROUND((COALESCE(cp_sums.bruto, 0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16, 2) - COALESCE(c.saldo_pagado,0)) AS saldo_pendiente,
               c.entrega_bloqueada,
               o.folio AS orden_folio,
               IF(c.estatus = 'cotizacion' AND c.created_at < DATE_SUB(NOW(), INTERVAL 15 DAY), 1, 0) AS es_inactiva
        FROM cotizaciones c
        LEFT JOIN ordenes o ON o.id = c.orden_id
        LEFT JOIN (
            SELECT cotizacion_id, SUM(precio_m2_usado * m2 * cantidad) AS bruto
            FROM cotizaciones_partidas
            GROUP BY cotizacion_id
        ) cp_sums ON cp_sums.cotizacion_id = c.id
        WHERE $where_str
        ORDER BY c.created_at DESC
        LIMIT $limit
    ");
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

if (!$puede_editar) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── POST — crear cotización ──────────────────────────────────────────────────
if ($method === 'POST') {
    $accion = $body['accion'] ?? 'crear';

    // Autorizar entrega con adeudo
    if ($accion === 'autorizar_entrega') {
        if (!$es_admin) { jsonResponse(['error' => 'Solo dir_admin puede autorizar']); exit; }
        $id   = (int)($body['id'] ?? 0);
        $nota = trim($body['nota'] ?? '');
        $db->prepare("UPDATE cotizaciones SET entrega_bloqueada=0, entrega_autorizada_por=?, entrega_autorizada_at=NOW(), entrega_autorizada_nota=? WHERE id=?")
           ->execute([$usuario_id, $nota, $id]);
        jsonResponse(['ok' => true]); exit;
    }

    // Convertir cotización a orden de producción
    if ($accion === 'convertir_orden') {
        $id = (int)($body['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        $cot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cot) { jsonResponse(['error' => 'No encontrada']); exit; }
        if ($cot['estatus'] !== 'cotizacion') { jsonResponse(['error' => 'Solo se pueden convertir cotizaciones']); exit; }
        // A-7: un asesor comercial solo convierte SUS cotizaciones
        if ($rol === 'comercial' && (int)$cot['asesor_id'] !== (int)$usuario_id) {
            jsonResponse(['error' => 'Solo el asesor dueño puede convertir esta cotización'], 403); exit;
        }

        // Bloquear conversión si descuento > 10% sin autorización aprobada (excepto dir_admin)
        if ((float)$cot['descuento'] > 10 && !in_array($rol, ['dir_admin', 'desarrollo'])) {
            // C-2: la aprobación debe CUBRIR el % actual — una aprobación vieja de
            // menor % ya no ampara el descuento editado después.
            $authStmt = $db->prepare("SELECT id FROM autorizaciones_descuento
                WHERE cotizacion_id = ? AND estatus = 'aprobado' AND descuento >= ?
                LIMIT 1");
            $authStmt->execute([$id, (float)$cot['descuento']]);
            if (!$authStmt->fetch()) {
                jsonResponse(['error' => 'El descuento del ' . $cot['descuento'] . '% requiere una autorización de Dirección de ' . $cot['descuento'] . '% o más. Solicita la autorización antes de convertir a orden.']); exit;
            }
        }

        $stmt2 = $db->prepare("SELECT * FROM cotizaciones_partidas WHERE cotizacion_id = ? ORDER BY num_partida ASC");
        $stmt2->execute([$id]);
        $partidas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Crear la orden usando el mismo flujo existente
        // generarFolioOrden usa LOCK TABLES que hace commit implícito,
        // debe llamarse ANTES de beginTransaction para no romper el rollback.
        $folio_orden = generarFolioOrden($db);
        $db->beginTransaction();
        try {
            if ($cot['tipo'] === 'maquila') {
                $folio_final = 'MA-' . $folio_orden;

                $stmt3 = $db->prepare("INSERT INTO ordenes
                    (folio, orden_trabajo, tipo, cliente_id, cliente_nombre, asesor, proyecto,
                     fecha_pedido, fecha_entrega, tipo_entrega, observaciones)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?)
                ");
                $stmt3->execute([
                    $folio_final, $folio_final, 'maquila',
                    $cot['cliente_id'] ?: null, $cot['cliente_nombre'], $cot['asesor_nombre'],
                    $cot['proyecto'] ?? '', $cot['fecha'], $cot['fecha_entrega'],
                    $cot['tipo_entrega'], $cot['alerta'] ?? ''
                ]);
                $orden_id = $db->lastInsertId();

                $stmt2 = $db->prepare("
                    SELECT mp.*, tv.nombre AS tipo_vidrio_nombre
                    FROM cotizaciones_maquila_partidas mp
                    LEFT JOIN maquila_tipos_vidrio tv ON tv.id = mp.cristal_tipo_id
                    WHERE mp.cotizacion_id = ? ORDER BY mp.num_partida ASC
                ");
                $stmt2->execute([$id]);
                $partidas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                $stmtPieza = $db->prepare("INSERT INTO piezas
                    (orden_id, partida, pieza_num, pieza_total,
                     cristal, cristal_corto, requiere_templado, requiere_corte,
                     ancho_mm, alto_mm, m2,
                     cpb, detalles, tp, ta, qr_code, estatus)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ");
                foreach ($partidas as $p) {
                    $etiqueta = trim(($p['tipo_vidrio_nombre'] ?? 'Cliente') . ' ' . $p['espesor_mm'] . 'mm');
                    for ($i = 1; $i <= $p['cantidad']; $i++) {
                        $qr = $folio_final . '-' . str_pad($p['num_partida'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($i, 3, '0', STR_PAD_LEFT) . '-' . str_pad($p['cantidad'], 3, '0', STR_PAD_LEFT);
                        $stmtPieza->execute([
                            $orden_id, $p['num_partida'], $i, $p['cantidad'],
                            $etiqueta, $etiqueta, (int)$p['templado'], (int)$p['corte'],
                            $p['ancho'], $p['alto'], $p['m2'],
                            $p['cpb'], $p['detalles'] ?? '', $p['taladros_pasados'], $p['taladros_avellanados'],
                            $qr, 'pendiente'
                        ]);
                    }
                }
            } else {
            $stmt3 = $db->prepare("INSERT INTO ordenes
                (folio, orden_trabajo, tipo, cliente_id, cliente_nombre, asesor, proyecto,
                 fecha_pedido, fecha_entrega, tipo_entrega, observaciones)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt3->execute([
                $folio_orden,
                $folio_orden,
                'suministro',
                $cot['cliente_id'] ?: null,
                $cot['cliente_nombre'],
                $cot['asesor_nombre'],
                $cot['proyecto'] ?? '',
                $cot['fecha'],
                $cot['fecha_entrega'],
                $cot['tipo_entrega'],
                $cot['alerta'] ?? ''
            ]);
            $orden_id = $db->lastInsertId();

            // Crear piezas individuales
            $stmtPieza = $db->prepare("INSERT INTO piezas 
                (orden_id, partida, pieza_num, pieza_total,
                 cristal, cristal_corto, requiere_templado,
                 ancho_mm, alto_mm, m2,
                 cpb, detalles, resaques, tp, ta, comentarios, qr_code, estatus)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");

            foreach ($partidas as $p) {
                for ($i = 1; $i <= $p['cantidad']; $i++) {
                    $qr = $cot['folio'] . '-' . str_pad($p['num_partida'], 2, '0', STR_PAD_LEFT) . '-' . str_pad($i, 3, '0', STR_PAD_LEFT) . '-' . str_pad($p['cantidad'], 3, '0', STR_PAD_LEFT);
                    $stmtPieza->execute([
                        $orden_id,
                        $p['num_partida'],
                        $i,
                        $p['cantidad'],
                        $p['cristal_nombre'] ?? $p['cristal_etiqueta'],
                        $p['cristal_etiqueta'],
                        (int)($p['requiere_templado'] ?? 1), // viene de la cotización
                        $p['ancho'],
                        $p['alto'],
                        $p['m2'],
                        $p['cpb'] ?? '',
                        $p['detalles'] ?? '',
                        $p['resaques'] ?? 0,
                        $p['taladros_pasados'] ?? 0,
                        $p['taladros_avellanados'] ?? 0,
                        $p['comentarios_etiqueta'] ?? '',
                        $qr,
                        'pendiente'
                    ]);
                }
            }
            }

            $folio_mostrado = ($cot['tipo'] === 'maquila') ? $folio_final : $folio_orden;

            // Actualizar cotización
            // Orden arranca en pendiente_vobo hasta VoBo de Finanzas
            $db->prepare("UPDATE ordenes SET estado='pendiente_vobo' WHERE id=?")->execute([$orden_id]);
            $db->prepare("UPDATE cotizaciones SET estatus='orden', orden_id=? WHERE id=?")
               ->execute([$orden_id, $id]);

            $db->commit();

            // Notificar a Lina (administracion) que hay nueva OC esperando VoBo
            try {
                $db->prepare("
                    INSERT INTO notificaciones (tipo, titulo, mensaje, folio, orden_id, rol_destino, leida)
                    VALUES ('nueva_oc', 'Nueva OC pendiente de VoBo', ?, ?, ?, 'administracion', 0)
                ")->execute([
                    'Orden ' . $folio_mostrado . ' — ' . $cot['cliente_nombre'] . ' requiere autorización.',
                    $folio_mostrado,
                    $orden_id
                ]);
            } catch (Exception $ignored) {}

            $folio_respuesta = ($cot['tipo'] === 'maquila') ? $folio_final : $cot['folio'];
            jsonResponse(['ok' => true, 'orden_id' => $orden_id, 'folio' => $folio_respuesta]); exit;

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()]); exit;
        }
    }

    // ── Agregar servicio a partida ───────────────────────────────────────────────
    if ($accion === 'agregar_servicio') {
        $cot_id         = (int)($body['cotizacion_id']    ?? 0);
        $partida_id     = (int)($body['partida_id']       ?? 0);
        $servicio_id    = (int)($body['servicio_id']      ?? 0);
        $und_x_pieza    = max(1, (int)($body['unidades_por_pieza'] ?? 1));
        $cant_piezas    = max(1, (int)($body['cantidad_piezas']    ?? 1));

        if (!$cot_id || !$partida_id || !$servicio_id) {
            jsonResponse(['error' => 'Datos incompletos']); exit;
        }

        $stmtS = $db->prepare("SELECT id, nombre, precio_default FROM servicios_catalogo WHERE id = ? AND activo = 1");
        $stmtS->execute([$servicio_id]);
        $srv = $stmtS->fetch(PDO::FETCH_ASSOC);
        if (!$srv) { jsonResponse(['error' => 'Servicio no encontrado']); exit; }

        // Verificar que la partida pertenece a la cotización
        $stmtP = $db->prepare("SELECT id FROM cotizaciones_partidas WHERE id = ? AND cotizacion_id = ?");
        $stmtP->execute([$partida_id, $cot_id]);
        if (!$stmtP->fetch()) { jsonResponse(['error' => 'Partida no pertenece a la cotización']); exit; }

        $precio    = (float)$srv['precio_default'];
        $subtotal  = round($precio * $und_x_pieza * $cant_piezas, 2);

        $db->beginTransaction();
        try {
            $db->prepare("INSERT INTO cotizacion_partida_servicios
                (cotizacion_id, partida_id, servicio_id, descripcion, precio_unitario, unidades_por_pieza, cantidad_piezas, subtotal)
                VALUES (?,?,?,?,?,?,?,?)
            ")->execute([$cot_id, $partida_id, $servicio_id, $srv['nombre'], $precio, $und_x_pieza, $cant_piezas, $subtotal]);

            // Recalcular servicios_subtotal de la cotización
            $st = $db->prepare("SELECT COALESCE(SUM(subtotal),0) as srv_total FROM cotizacion_partida_servicios WHERE cotizacion_id = ?");
            $st->execute([$cot_id]);
            $srv_total = (float)$st->fetchColumn();
            $db->prepare("UPDATE cotizaciones SET servicios_subtotal = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$srv_total, $cot_id]);

            // A-2: c.total incluye servicios — recalcular con la fórmula canónica
            $tots = apexTotalesCotizacion($db, $cot_id);
            $db->prepare("UPDATE cotizaciones SET subtotal = ?, iva = ?, total = ? WHERE id = ?")
               ->execute([$tots['subtotal'], $tots['iva'], $tots['total'], $cot_id]);

            $db->commit();
            jsonResponse(['ok' => true, 'servicios_subtotal' => $srv_total, 'nuevo_servicio' => [
                'id' => (int)$db->lastInsertId(), 'descripcion' => $srv['nombre'],
                'precio_unitario' => $precio, 'unidades_por_pieza' => $und_x_pieza,
                'cantidad_piezas' => $cant_piezas, 'subtotal' => $subtotal,
            ]]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Eliminar servicio de partida ──────────────────────────────────────────
    if ($accion === 'eliminar_servicio') {
        $srv_partida_id = (int)($body['servicio_partida_id'] ?? 0);
        $cot_id         = (int)($body['cotizacion_id']       ?? 0);
        if (!$srv_partida_id || !$cot_id) { jsonResponse(['error' => 'Datos incompletos']); exit; }

        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM cotizacion_partida_servicios WHERE id = ? AND cotizacion_id = ?")
               ->execute([$srv_partida_id, $cot_id]);

            $st = $db->prepare("SELECT COALESCE(SUM(subtotal),0) as srv_total FROM cotizacion_partida_servicios WHERE cotizacion_id = ?");
            $st->execute([$cot_id]);
            $srv_total = (float)$st->fetchColumn();
            $db->prepare("UPDATE cotizaciones SET servicios_subtotal = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$srv_total, $cot_id]);

            // A-2: c.total incluye servicios — recalcular con la fórmula canónica
            $tots = apexTotalesCotizacion($db, $cot_id);
            $db->prepare("UPDATE cotizaciones SET subtotal = ?, iva = ?, total = ? WHERE id = ?")
               ->execute([$tots['subtotal'], $tots['iva'], $tots['total'], $cot_id]);

            $db->commit();
            jsonResponse(['ok' => true, 'servicios_subtotal' => $srv_total]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()]);
        }
        exit;
    }

    // Crear cotización nueva
    $cliente_id   = (int)($body['cliente_id']    ?? 0);
    $proyecto     = trim($body['proyecto']        ?? '');
    $descuento    = (float)($body['descuento']    ?? 0);
    $credito      = ($body['credito'] ?? 'no') === 'si' ? 'si' : 'no';
    $condicion    = in_array($body['condicion_pago'] ?? '', ['anticipo','pago_total']) ? $body['condicion_pago'] : 'anticipo';
    $tipo_entrega = in_array($body['tipo_entrega'] ?? '', ['domicilio','planta']) ? $body['tipo_entrega'] : 'domicilio';
    $localidad    = ($body['localidad'] ?? '') === 'foraneo' ? 'foraneo' : 'local';
    $ciudad       = trim($body['ciudad_destino']  ?? '');
    $factura_tipo = trim($body['factura_tipo']     ?? ''); // 'generica' solo si el checkbox lo marca explícitamente; vacío = no es público en general
    $alerta       = trim($body['alerta']           ?? '');
    $partidas     = $body['partidas']              ?? [];
    $fecha_entrega_manual = trim($body['fecha_entrega'] ?? '');

    if (!$cliente_id) { jsonResponse(['error' => 'Cliente requerido']); exit; }
    if (empty($partidas)) { jsonResponse(['error' => 'Se requiere al menos una partida']); exit; }

    // Datos del cliente
    $stmt = $db->prepare("SELECT razon_social, nombre FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cliente) { jsonResponse(['error' => 'Cliente no encontrado']); exit; }
    $cliente_nombre = $cliente['razon_social'] ?: $cliente['nombre'];

    // Calcular fecha entrega
    $fecha_hoy    = date('Y-m-d');
    $fecha_entrega = $fecha_entrega_manual ?: calcularFechaEntrega($db, $fecha_hoy, $localidad, $ciudad);
    $es_manual    = $fecha_entrega_manual ? 1 : 0;

    // Calcular totales
    $subtotal_total = 0;
    $bruto_total    = 0; // A-2: bruto SIN descuento para la fórmula canónica
    $partidas_data  = [];

    foreach ($partidas as $p) {
        $cristal_id = (int)($p['cristal_id'] ?? 0);
        $cantidad   = max(1, (int)($p['cantidad'] ?? 1));
        $ancho      = (int)($p['ancho'] ?? 0);
        $alto       = (int)($p['alto']  ?? 0);

        if (!$cristal_id || !$ancho || !$alto) continue;

        $stmt = $db->prepare("SELECT nombre, nombre_etiqueta, precio_m2 FROM cristales WHERE id = ? AND activo = 1");
        $stmt->execute([$cristal_id]);
        $cristal = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cristal) continue;

        $m2             = round(($ancho / 1000) * ($alto / 1000), 6);
        $precio_m2      = (float)$cristal['precio_m2'];
        $precio_unit    = round($m2 * $precio_m2 * (1 - $descuento / 100), 4);
        $subtotal       = round($precio_unit * $cantidad, 2);
        $iva            = round($subtotal * 0.16, 2);
        $total_p        = round($subtotal + $iva, 2);
        $subtotal_total += $subtotal;
        $bruto_total    += $m2 * $precio_m2 * $cantidad; // A-2: mismo bruto que recalcula la BD

        $partidas_data[] = [
            'cristal_id'           => $cristal_id,
            'cristal_nombre'       => $cristal['nombre'],
            'cristal_etiqueta'     => $cristal['nombre_etiqueta'],
            'precio_m2_usado'      => $precio_m2,
            'cantidad'             => $cantidad,
            'ancho'                => $ancho,
            'alto'                 => $alto,
            'm2'                   => $m2,
            'detalles'             => trim($p['detalles']              ?? ''),
            'cpb'                  => trim($p['cpb']                   ?? ''),
            'resaques'             => (int)($p['resaques']             ?? 0),
            'taladros_pasados'     => (int)($p['taladros_pasados']     ?? 0),
            'taladros_avellanados' => (int)($p['taladros_avellanados'] ?? 0),
            'precio_unitario'      => $precio_unit,
            'subtotal'             => $subtotal,
            'iva'                  => $iva,
            'total'                => $total_p,
            'comentarios_etiqueta' => trim($p['comentarios_etiqueta']  ?? ''),
            'requiere_templado'    => isset($p['requiere_templado']) ? (int)$p['requiere_templado'] : 1,
        ];
    }

    if (empty($partidas_data)) { jsonResponse(['error' => 'Ninguna partida válida']); exit; }

    // A-2: totales de encabezado con la fórmula canónica
    // (los subtotales por renglón se conservan solo para despliegue)
    $tots          = apexTotales($bruto_total, $descuento, 0); // al crear aún no hay servicios
    $subtotal_neto = $tots['subtotal'];
    $iva_total     = $tots['iva'];
    $total_final   = $tots['total'];
    $saldo         = ($condicion === 'anticipo') ? round($total_final * 0.5, 2) : $total_final;

    // Generar folio ANTES de la transacción (LOCK TABLES no es compatible con transacciones)
    $folio = generarFolio($db);

    $db->beginTransaction();
    try {

        $db->prepare("INSERT INTO cotizaciones
            (folio, fecha, cliente_id, cliente_nombre, asesor_id, asesor_nombre,
             proyecto, descuento, credito, condicion_pago, tipo_entrega,
             localidad, ciudad_destino, factura_tipo, fecha_entrega, fecha_entrega_manual,
             alerta, subtotal, iva, total, saldo_pendiente, entrega_bloqueada, estatus)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $folio, $fecha_hoy, $cliente_id, $cliente_nombre,
            $usuario_id, $usuario_nombre,
            $proyecto, $descuento, $credito, $condicion, $tipo_entrega,
            $localidad, $ciudad, $factura_tipo, $fecha_entrega, $es_manual,
            $alerta, $subtotal_neto, $iva_total, $total_final,
            $saldo,
            0,
            'cotizacion'
        ]);
        $cot_id = $db->lastInsertId();

        $stmtP = $db->prepare("INSERT INTO cotizaciones_partidas
            (cotizacion_id, num_partida, cristal_id, cristal_nombre, cristal_etiqueta,
             precio_m2_usado, cantidad, ancho, alto, m2, detalles, cpb,
             resaques, taladros_pasados, taladros_avellanados, requiere_templado,
             precio_unitario, subtotal, iva, total, comentarios_etiqueta)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        foreach ($partidas_data as $i => $p) {
            $stmtP->execute([
                $cot_id, $i + 1,
                $p['cristal_id'], $p['cristal_nombre'], $p['cristal_etiqueta'],
                $p['precio_m2_usado'], $p['cantidad'], $p['ancho'], $p['alto'], $p['m2'],
                $p['detalles'], $p['cpb'], $p['resaques'],
                $p['taladros_pasados'], $p['taladros_avellanados'], $p['requiere_templado'],
                $p['precio_unitario'], $p['subtotal'], $p['iva'], $p['total'],
                $p['comentarios_etiqueta']
            ]);
        }

        $db->commit();

        // Solicitud de autorización si descuento > 10% y no es dir_admin
        if ($descuento > 10 && !in_array($rol, ['dir_admin', 'desarrollo'])) {
            $motivo_auth = trim($body['motivo_descuento'] ?? '');
            try {
                $db->prepare("INSERT INTO autorizaciones_descuento (cotizacion_id, folio, descuento, motivo, solicitado_por) VALUES (?,?,?,?,?)")
                   ->execute([$cot_id, $folio, $descuento, $motivo_auth, $usuario_nombre]);
            } catch (Exception $ignored) {}
        }

        jsonResponse(['ok' => true, 'id' => $cot_id, 'folio' => $folio,
                          'fecha_entrega' => $fecha_entrega, 'total' => $total_final]);

    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => $e->getMessage()]);
    }
    exit;
}

// ─── PUT — actualizar estatus / saldo / contenido ───────────────────────────────
if ($method === 'PUT') {
    $id      = (int)($body['id']      ?? 0);
    $accion  = $body['accion']        ?? '';
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    // ── Actualizar contenido completo de una cotización ──
    if ($accion === 'actualizar') {
        if (!$puede_editar) { jsonResponse(['error' => 'Sin permiso']); exit; }

        // Verificar que existe y está en estatus cotizacion
        $stmt = $db->prepare("SELECT estatus, asesor_id FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        $cot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cot) { jsonResponse(['error' => 'Cotización no encontrada']); exit; }
        if ($cot['estatus'] !== 'cotizacion') { jsonResponse(['error' => 'Solo se pueden editar cotizaciones (no órdenes)']); exit; }
        // A-7: un asesor comercial solo edita SUS cotizaciones (admin roles exentos)
        if ($rol === 'comercial' && (int)$cot['asesor_id'] !== (int)$usuario_id) {
            jsonResponse(['error' => 'Solo el asesor dueño puede editar esta cotización'], 403); exit;
        }

        $cliente_id   = (int)($body['cliente_id']    ?? 0);
        $proyecto     = trim($body['proyecto']        ?? '');
        $descuento    = max(0, min(100, (float)($body['descuento'] ?? 0)));
        $credito      = ($body['credito'] ?? 'no') === 'si' ? 'si' : 'no';
        $condicion    = in_array($body['condicion_pago'] ?? '', ['anticipo','pago_total']) ? $body['condicion_pago'] : 'anticipo';
        $tipo_entrega = in_array($body['tipo_entrega'] ?? '', ['domicilio','planta']) ? $body['tipo_entrega'] : 'domicilio';
        $localidad    = ($body['localidad'] ?? '') === 'foraneo' ? 'foraneo' : 'local';
        $ciudad       = trim($body['ciudad_destino']  ?? '');
        $factura_tipo = trim($body['factura_tipo']     ?? ''); // 'generica' solo si el checkbox lo marca explícitamente; vacío = no es público en general
        $alerta       = trim($body['alerta']           ?? '');
        $partidas     = $body['partidas']              ?? [];
        $fecha_entrega_manual = trim($body['fecha_entrega'] ?? '');

        if (!$cliente_id) { jsonResponse(['error' => 'Cliente requerido']); exit; }
        if (empty($partidas)) { jsonResponse(['error' => 'Se requiere al menos una partida']); exit; }

        // Datos del cliente
        $stmt = $db->prepare("SELECT razon_social, nombre FROM clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cliente) { jsonResponse(['error' => 'Cliente no encontrado']); exit; }
        $cliente_nombre = $cliente['razon_social'] ?: $cliente['nombre'];

        // Calcular fecha entrega
        $fecha_entrega = $fecha_entrega_manual ?: calcularFechaEntrega($db, date('Y-m-d'), $localidad, $ciudad);
        $es_manual     = $fecha_entrega_manual ? 1 : 0;

        // C-3: precios vigentes leídos de BD — NUNCA del body.
        // Mapa "num_partida|cristal_id" → precio_m2_usado actual de la cotización.
        $stmtPrev = $db->prepare("SELECT num_partida, cristal_id, precio_m2_usado
                                  FROM cotizaciones_partidas WHERE cotizacion_id = ?");
        $stmtPrev->execute([$id]);
        $preciosVigentes = [];
        foreach ($stmtPrev->fetchAll(PDO::FETCH_ASSOC) as $pv) {
            $preciosVigentes[$pv['num_partida'] . '|' . $pv['cristal_id']] = (float)$pv['precio_m2_usado'];
        }

        // Calcular totales y validar partidas
        $subtotal_total = 0;
        $bruto_total    = 0; // A-2
        $partidas_data  = [];

        foreach ($partidas as $p) {
            $cristal_id = (int)($p['cristal_id'] ?? 0);
            $cantidad   = max(1, (int)($p['cantidad'] ?? 1));
            $ancho      = (int)($p['ancho'] ?? 0);
            $alto       = (int)($p['alto']  ?? 0);
            if (!$cristal_id || !$ancho || !$alto) continue;

            $stmt = $db->prepare("SELECT nombre, nombre_etiqueta, precio_m2 FROM cristales WHERE id = ? AND activo = 1");
            $stmt->execute([$cristal_id]);
            $cristal = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cristal) continue;

            $m2 = round(($ancho / 1000) * ($alto / 1000), 6);

            // C-3: el precio lo decide el servidor. Se conserva el precio vigente en BD
            // si el renglón ya existía (misma posición y mismo cristal — protege precios
            // históricos cuando el catálogo sube); partida nueva o cristal distinto → catálogo.
            // Los cambios de precio solo se hacen vía api/correcciones.php (auditado, dir_admin).
            $precio_m2 = $preciosVigentes[(count($partidas_data) + 1) . '|' . $cristal_id]
                         ?? (float)$cristal['precio_m2'];

            $precio_unit  = round($m2 * $precio_m2 * (1 - $descuento / 100), 4);
            $subtotal     = round($precio_unit * $cantidad, 2);
            $iva          = round($subtotal * 0.16, 2);
            $total_p      = round($subtotal + $iva, 2);
            $subtotal_total += $subtotal;
            $bruto_total    += $m2 * $precio_m2 * $cantidad; // A-2

            $partidas_data[] = [
                'cristal_id'           => $cristal_id,
                'cristal_nombre'       => $cristal['nombre'],
                'cristal_etiqueta'     => $cristal['nombre_etiqueta'],
                'precio_m2_usado'      => $precio_m2,
                'cantidad'             => $cantidad,
                'ancho'                => $ancho,
                'alto'                 => $alto,
                'm2'                   => $m2,
                'detalles'             => trim($p['detalles']              ?? ''),
                'cpb'                  => trim($p['cpb']                   ?? ''),
                'resaques'             => (int)($p['resaques']             ?? 0),
                'taladros_pasados'     => (int)($p['taladros_pasados']     ?? 0),
                'taladros_avellanados' => (int)($p['taladros_avellanados'] ?? 0),
                'precio_unitario'      => $precio_unit,
                'subtotal'             => $subtotal,
                'iva'                  => $iva,
                'total'                => $total_p,
                'comentarios_etiqueta' => trim($p['comentarios_etiqueta']  ?? ''),
            'requiere_templado'    => isset($p['requiere_templado']) ? (int)$p['requiere_templado'] : 1,
            ];
        }

        if (empty($partidas_data)) { jsonResponse(['error' => 'Ninguna partida válida']); exit; }

        // A-2: estimar los servicios que sobreviven la edición (se preservan por
        // num_partida más abajo) para que el total ya los incluya desde el UPDATE.
        $stSrvEst = $db->prepare("
            SELECT COALESCE(SUM(cps.subtotal),0)
            FROM cotizacion_partida_servicios cps
            JOIN cotizaciones_partidas cp ON cp.id = cps.partida_id
            WHERE cps.cotizacion_id = ? AND cp.num_partida <= ?
        ");
        $stSrvEst->execute([$id, count($partidas_data)]);
        $servicios_estimados = (float)$stSrvEst->fetchColumn();

        // A-2: fórmula canónica — c.total incluye servicios
        $tots          = apexTotales($bruto_total, $descuento, $servicios_estimados);
        $subtotal_neto = $tots['subtotal'];
        $iva_total     = $tots['iva'];
        $total_final   = $tots['total'];
        $saldo         = ($condicion === 'anticipo') ? round($total_final * 0.5, 2) : $total_final;

        $db->beginTransaction();
        try {
            // Actualizar cabecera
            $db->prepare("UPDATE cotizaciones SET
                cliente_id=?, cliente_nombre=?, proyecto=?, descuento=?,
                credito=?, condicion_pago=?, tipo_entrega=?, localidad=?,
                ciudad_destino=?, factura_tipo=?, fecha_entrega=?, fecha_entrega_manual=?,
                alerta=?, subtotal=?, iva=?, total=?, saldo_pendiente=?,
                updated_at=NOW()
                WHERE id=?
            ")->execute([
                $cliente_id, $cliente_nombre, $proyecto, $descuento,
                $credito, $condicion, $tipo_entrega, $localidad,
                $ciudad, $factura_tipo, $fecha_entrega, $es_manual,
                $alerta, $subtotal_neto, $iva_total, $total_final, $saldo,
                $id
            ]);

            // Preservar servicios antes de borrar partidas (mapeados por num_partida)
            $stmtSrvBak = $db->prepare("
                SELECT cps.*, cp.num_partida
                FROM cotizacion_partida_servicios cps
                JOIN cotizaciones_partidas cp ON cp.id = cps.partida_id
                WHERE cps.cotizacion_id = ?
            ");
            $stmtSrvBak->execute([$id]);
            $srvBackup = $stmtSrvBak->fetchAll(PDO::FETCH_ASSOC);
            $db->prepare("DELETE FROM cotizacion_partida_servicios WHERE cotizacion_id = ?")->execute([$id]);

            // Reemplazar partidas
            $db->prepare("DELETE FROM cotizaciones_partidas WHERE cotizacion_id = ?")->execute([$id]);

            $stmtP = $db->prepare("INSERT INTO cotizaciones_partidas
                (cotizacion_id, num_partida, cristal_id, cristal_nombre, cristal_etiqueta,
                 precio_m2_usado, cantidad, ancho, alto, m2, detalles, cpb,
                 resaques, taladros_pasados, taladros_avellanados, requiere_templado,
                 precio_unitario, subtotal, iva, total, comentarios_etiqueta)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $nuevosIdsPorNumPartida = [];
            foreach ($partidas_data as $i => $p) {
                $numPart = $i + 1;
                $stmtP->execute([
                    $id, $numPart,
                    $p['cristal_id'], $p['cristal_nombre'], $p['cristal_etiqueta'],
                    $p['precio_m2_usado'], $p['cantidad'], $p['ancho'], $p['alto'], $p['m2'],
                    $p['detalles'], $p['cpb'], $p['resaques'],
                    $p['taladros_pasados'], $p['taladros_avellanados'], $p['requiere_templado'],
                    $p['precio_unitario'], $p['subtotal'], $p['iva'], $p['total'],
                    $p['comentarios_etiqueta']
                ]);
                $nuevosIdsPorNumPartida[$numPart] = (int)$db->lastInsertId();
            }

            // Restaurar servicios con nuevos partida_ids
            $srv_restaurado = 0;
            if (!empty($srvBackup)) {
                $stmtSrvIns = $db->prepare("INSERT INTO cotizacion_partida_servicios
                    (cotizacion_id, partida_id, servicio_id, descripcion, precio_unitario, unidades_por_pieza, cantidad_piezas, subtotal)
                    VALUES (?,?,?,?,?,?,?,?)");
                foreach ($srvBackup as $s) {
                    $nuevoPartidaId = $nuevosIdsPorNumPartida[$s['num_partida']] ?? null;
                    if (!$nuevoPartidaId) continue;
                    $stmtSrvIns->execute([
                        $id, $nuevoPartidaId, $s['servicio_id'] ?: null, $s['descripcion'],
                        $s['precio_unitario'], $s['unidades_por_pieza'], $s['cantidad_piezas'], $s['subtotal'],
                    ]);
                    $srv_restaurado += (float)$s['subtotal'];
                }
                // Actualizar servicios_subtotal si cambió (partidas eliminadas pierden sus servicios)
                $stSrv = $db->prepare("SELECT COALESCE(SUM(subtotal),0) FROM cotizacion_partida_servicios WHERE cotizacion_id = ?");
                $stSrv->execute([$id]);
                $srv_total_real = (float)$stSrv->fetchColumn();
                $db->prepare("UPDATE cotizaciones SET servicios_subtotal = ? WHERE id = ?")
                   ->execute([$srv_total_real, $id]);
            }

            $db->commit();

            // C-2: invalidar aprobaciones previas cuyo % ya no coincide con el actual.
            // Una aprobación ampara únicamente el descuento exacto que se autorizó.
            // (Se marca 'rechazado' con nota para conservar la traza en el historial.)
            $db->prepare("UPDATE autorizaciones_descuento
                SET estatus = 'rechazado',
                    nota_resolucion = ?,
                    fecha_resolucion = NOW()
                WHERE cotizacion_id = ? AND estatus = 'aprobado' AND descuento <> ?")
               ->execute([
                   'Invalidada automáticamente: el descuento cambió a ' . $descuento . '% — requiere nueva autorización',
                   $id, $descuento
               ]);

            // Gestionar autorización de descuento al actualizar
            if ($descuento > 10 && !in_array($rol, ['dir_admin', 'desarrollo'])) {
                $motivo_auth = trim($body['motivo_descuento'] ?? '');
                $chk = $db->prepare("SELECT id, descuento, estatus FROM autorizaciones_descuento WHERE cotizacion_id = ? ORDER BY fecha_solicitud DESC LIMIT 1");
                $chk->execute([$id]);
                $ultima = $chk->fetch(PDO::FETCH_ASSOC);

                if (!$ultima) {
                    // No hay registro previo — crear nuevo
                    $db->prepare("INSERT INTO autorizaciones_descuento (cotizacion_id, folio, descuento, motivo, solicitado_por) VALUES (?,?,?,?,?)")
                       ->execute([$id, '', $descuento, $motivo_auth, $usuario_nombre]);
                } elseif ($ultima['estatus'] === 'pendiente') {
                    // Actualizar el pendiente con el nuevo descuento/motivo
                    $db->prepare("UPDATE autorizaciones_descuento SET descuento=?, motivo=?, fecha_solicitud=NOW() WHERE id=?")
                       ->execute([$descuento, $motivo_auth, $ultima['id']]);
                } elseif ($ultima['estatus'] === 'rechazado' || ((float)$ultima['descuento'] !== $descuento && $ultima['estatus'] === 'aprobado')) {
                    // Rechazado o aprobado pero con descuento diferente — crear nueva solicitud
                    $db->prepare("INSERT INTO autorizaciones_descuento (cotizacion_id, folio, descuento, motivo, solicitado_por) VALUES (?,?,?,?,?)")
                       ->execute([$id, '', $descuento, $motivo_auth, $usuario_nombre]);
                }
                // Si $ultima['estatus'] === 'aprobado' y mismo descuento — no hacer nada
            } elseif ($descuento <= 10) {
                // Descuento reducido — cancelar autorizaciones pendientes
                $db->prepare("UPDATE autorizaciones_descuento SET estatus='rechazado', nota_resolucion='Descuento reducido por el asesor', fecha_resolucion=NOW() WHERE cotizacion_id=? AND estatus='pendiente'")
                   ->execute([$id]);
            }

            jsonResponse(['ok' => true, 'total' => $total_final, 'fecha_entrega' => $fecha_entrega]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($accion === 'marcar_entregada') {
        // Verificar bloqueo
        $stmt = $db->prepare("SELECT entrega_bloqueada, saldo_pendiente FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        $cot = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cot['entrega_bloqueada'] && !$es_admin) {
            jsonResponse(['error' => 'Entrega bloqueada por saldo pendiente', 'bloqueada' => true]); exit;
        }
        $db->prepare("UPDATE cotizaciones SET estatus='entregada', updated_at=NOW() WHERE id=?")->execute([$id]);
        jsonResponse(['ok' => true]); exit;
    }

    if ($accion === 'actualizar_saldo') {
        // A-7: solo Finanzas/admin — un comercial no puede liquidar adeudos
        // ni desbloquear entregas (mismos roles que api/finanzas.php).
        if (!in_array($rol, ['administracion', 'dir_admin', 'dueno', 'desarrollo'])) {
            jsonResponse(['error' => 'Solo Finanzas puede ajustar saldos'], 403); exit;
        }

        $stmtC = $db->prepare("SELECT folio, COALESCE(saldo_pagado,0) AS saldo_pagado, saldo_pendiente
                               FROM cotizaciones WHERE id = ?");
        $stmtC->execute([$id]);
        $cot = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$cot) { jsonResponse(['error' => 'Cotización no encontrada']); exit; }

        // A-7: el saldo se RECALCULA en servidor (total canónico − saldo_pagado);
        // el valor del body ya no se acepta.
        $totales   = apexTotalesCotizacion($db, $id);
        $saldo     = max(0, round($totales['total'] - (float)$cot['saldo_pagado'], 2));
        $bloqueada = $saldo > 0 ? 1 : 0;
        $db->prepare("UPDATE cotizaciones SET saldo_pendiente=?, entrega_bloqueada=?, updated_at=NOW() WHERE id=?")
           ->execute([$saldo, $bloqueada, $id]);

        // Bitácora del ajuste (quién/cuándo/antes/después)
        $db->prepare("INSERT INTO correcciones_log
            (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, motivo, usuario)
            VALUES ('cotizacion', ?, ?, 'saldo_pendiente', ?, ?, ?, ?)")
           ->execute([
               $id, $cot['folio'],
               (string)$cot['saldo_pendiente'], (string)$saldo,
               'Recalculado: total $' . number_format($totales['total'], 2)
                   . ' − pagado $' . number_format((float)$cot['saldo_pagado'], 2),
               $usuario_nombre
           ]);

        jsonResponse(['ok' => true, 'saldo_pendiente' => $saldo, 'entrega_bloqueada' => $bloqueada]); exit;
    }

    if ($accion === 'cancelar') {
        if (!$es_admin) { jsonResponse(['error' => 'Solo dir_admin puede cancelar']); exit; }

        // A-8: cascada cotización ↔ orden + el dinero cobrado NO queda atrapado
        $stmtC = $db->prepare("SELECT id, folio, orden_id, cliente_id, estatus, COALESCE(saldo_pagado,0) AS saldo_pagado
                               FROM cotizaciones WHERE id = ?");
        $stmtC->execute([$id]);
        $cot = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$cot) { jsonResponse(['error' => 'Cotización no encontrada']); exit; }
        if (in_array($cot['estatus'], ['cancelada', 'rechazada'])) {
            jsonResponse(['error' => 'La cotización ya está ' . $cot['estatus']]); exit;
        }
        if ($cot['orden_id']) {
            $stO = $db->prepare("SELECT estado FROM ordenes WHERE id = ?");
            $stO->execute([$cot['orden_id']]);
            if ($stO->fetchColumn() === 'entregada') {
                jsonResponse(['error' => 'La orden ya fue entregada; no se puede cancelar (usa el flujo de rechazo/devolución)']); exit;
            }
        }

        $db->beginTransaction();
        try {
            // Cancelar ambas puntas
            $db->prepare("UPDATE cotizaciones SET estatus='cancelada', updated_at=NOW() WHERE id=?")->execute([$id]);
            if ($cot['orden_id']) {
                $db->prepare("UPDATE ordenes SET estado='cancelada', updated_at=NOW() WHERE id=? AND estado != 'entregada'")
                   ->execute([$cot['orden_id']]);
            }

            // Dinero cobrado → saldo a favor del cliente (mismo flujo que 'rechazar')
            $monto = (float)$cot['saldo_pagado'];
            if ($monto > 0 && $cot['cliente_id']) {
                $db->prepare("INSERT INTO clientes_saldo_favor (cliente_id, tipo, monto, fecha, referencia, notas, cotizacion_id, creado_por)
                              VALUES (?, 'deposito', ?, CURDATE(), ?, ?, ?, ?)")
                   ->execute([
                       $cot['cliente_id'], $monto,
                       'Cancelación ' . $cot['folio'],
                       'Saldo cobrado movido a favor por cancelación',
                       $id, $usuario_nombre
                   ]);
                $db->prepare("UPDATE cotizaciones SET saldo_pagado=0, saldo_pendiente=0, estatus_pago='pendiente', updated_at=NOW() WHERE id=?")
                   ->execute([$id]);
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()]); exit;
        }
        jsonResponse(['ok' => true]); exit;
    }

    // ── Rechazar por calidad ──────────────────────────────────────────────────
    if ($accion === 'rechazar') {
        if (!$es_admin) { jsonResponse(['error' => 'Solo dir_admin puede registrar rechazos']); exit; }
        $motivo = trim($body['motivo'] ?? '');
        if (!$motivo) { jsonResponse(['error' => 'El motivo del rechazo es obligatorio']); exit; }

        // C-13: se lee el estatus para no permitir doble rechazo
        $stmtCot = $db->prepare("SELECT id, orden_id, cliente_id, saldo_pagado, folio, estatus FROM cotizaciones WHERE id = ?");
        $stmtCot->execute([$id]);
        $cot = $stmtCot->fetch(PDO::FETCH_ASSOC);
        if (!$cot) { jsonResponse(['error' => 'Cotización no encontrada']); exit; }
        if ($cot['estatus'] === 'rechazada') { jsonResponse(['error' => 'Esta cotización ya fue rechazada — el saldo a favor ya se generó']); exit; }
        if ($cot['estatus'] === 'cancelada')  { jsonResponse(['error' => 'La cotización está cancelada']); exit; }
        if (!$cot['orden_id']) { jsonResponse(['error' => 'Esta cotización no tiene una orden generada']); exit; }

        $montoDevuelto = (float)($cot['saldo_pagado'] ?? 0);
        $db->beginTransaction();
        try {
            // 1. Marcar orden y cotización como rechazadas.
            //    C-13: UPDATE condicional + rowCount — un doble clic/reintento
            //    concurrente no puede volver a ejecutar el flujo.
            $db->prepare("UPDATE ordenes SET estado='rechazada', updated_at=NOW() WHERE id=?")
               ->execute([$cot['orden_id']]);
            $stUp = $db->prepare("UPDATE cotizaciones
                                  SET estatus='rechazada', saldo_pagado=0, saldo_pendiente=0, updated_at=NOW()
                                  WHERE id=? AND estatus NOT IN ('rechazada','cancelada')");
            $stUp->execute([$id]);
            if ($stUp->rowCount() === 0) {
                throw new Exception('La cotización ya fue rechazada o cancelada por otro proceso');
            }

            // 2. Insertar en bitácora de rechazos
            $db->prepare("INSERT INTO rechazo_calidad (cotizacion_id, orden_id, cliente_id, motivo, monto_devuelto, registrado_por)
                          VALUES (?, ?, ?, ?, ?, ?)")
               ->execute([$id, $cot['orden_id'], $cot['cliente_id'], $motivo, $montoDevuelto, $user['nombre']]);

            // 3. Mover saldo_pagado a saldo a favor del cliente (una sola vez:
            //    el UPDATE de arriba ya puso saldo_pagado=0, por lo que el dinero
            //    deja de contarse como "cobrado" en el mismo paso)
            if ($montoDevuelto > 0) {
                $db->prepare("INSERT INTO clientes_saldo_favor (cliente_id, tipo, monto, fecha, referencia, notas, cotizacion_id, creado_por)
                              VALUES (?, 'deposito', ?, CURDATE(), ?, ?, ?, ?)")
                   ->execute([
                       $cot['cliente_id'],
                       $montoDevuelto,
                       'Rechazo ' . $cot['folio'],
                       'Rechazo por calidad: ' . mb_substr($motivo, 0, 150),
                       $id,
                       $user['nombre']
                   ]);
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()]); exit;
        }
        jsonResponse(['ok' => true, 'monto_devuelto' => $montoDevuelto]); exit;
    }

    jsonResponse(['error' => 'Acción no reconocida']); exit;
}

// ─── GET especial: calcular fecha entrega ─────────────────────────────────────
if ($method === 'GET' && isset($_GET['calcular_fecha'])) {
    $fecha    = $_GET['fecha']     ?? date('Y-m-d');
    $localidad= $_GET['localidad'] ?? 'local';
    $ciudad   = $_GET['ciudad']    ?? '';
    jsonResponse(['fecha_entrega' => calcularFechaEntrega($db, $fecha, $localidad, $ciudad)]); exit;
}

jsonResponse(['error' => 'Método no soportado']);