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

$puede_editar = in_array($rol, ['dir_admin', 'dueno', 'comercial']);
$es_admin     = in_array($rol, ['dir_admin', 'dueno']);

// ─── Función: generar siguiente folio de COTIZACIÓN ─────────────────────────
function generarFolio($db) {
    $db->exec("LOCK TABLES folios_control WRITE");
    $row    = $db->query("SELECT * FROM folios_control LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $modo   = $row['modo_cot'] ?? 'prueba';
    $num    = ($row['num_cot'] ?? 0) + 1;
    $db->prepare("UPDATE folios_control SET num_cot = ? WHERE id = ?")
       ->execute([$num, $row['id']]);
    $db->exec("UNLOCK TABLES");

    if ($modo === 'produccion') {
        return 'COT-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    } else {
        return 'xCOT-' . str_pad($num, 3, '0', STR_PAD_LEFT);
    }
}

// ─── Función: generar siguiente folio de ORDEN DE PRODUCCIÓN ────────────────
function generarFolioOrden($db) {
    $db->exec("LOCK TABLES folios_control WRITE");
    $row    = $db->query("SELECT * FROM folios_control LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $letra  = $row['letra_actual'];
    $numero = $row['numero_actual'] + 1;
    if ($numero > 999) {
        $numero = 1;
        $letra  = chr(ord($letra) + 1);
    }
    $db->prepare("UPDATE folios_control SET letra_actual = ?, numero_actual = ? WHERE id = ?")
       ->execute([$letra, $numero, $row['id']]);
    $db->exec("UNLOCK TABLES");
    return $letra . '-' . str_pad($numero, 3, '0', STR_PAD_LEFT);
}

// ─── Función: calcular fecha entrega (días hábiles) ──────────────────────────
function calcularFechaEntrega($db, $fecha_inicio, $localidad, $ciudad) {
    // Obtener festivos
    $stmt = $db->query("SELECT fecha FROM festivos");
    $festivos = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'fecha');

    $fecha  = new DateTime($fecha_inicio);
    $dias   = 0;
    $target = 5;

    while ($dias < $target) {
        $fecha->modify('+1 day');
        $dow = (int)$fecha->format('N'); // 1=lunes, 7=domingo
        $f   = $fecha->format('Y-m-d');
        if ($dow >= 6) continue;          // fin de semana
        if (in_array($f, $festivos)) continue; // festivo
        $dias++;
    }

    // Saltillo: ajustar al viernes de esa semana
    if ($localidad === 'foraneo' && stripos($ciudad ?? '', 'saltillo') !== false) {
        $dow = (int)$fecha->format('N');
        if ($dow < 5) {
            $dias_a_viernes = 5 - $dow;
            $fecha->modify("+$dias_a_viernes days");
        }
        // Si viernes es festivo, mover al siguiente día hábil
        while (in_array($fecha->format('Y-m-d'), $festivos) || (int)$fecha->format('N') >= 6) {
            $fecha->modify('+1 day');
        }
    }

    return $fecha->format('Y-m-d');
}

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $id      = isset($_GET['id'])  ? (int)$_GET['id']  : null;
    $estatus = $_GET['estatus']    ?? '';
    $q       = trim($_GET['q']     ?? '');
    $limit   = min((int)($_GET['limit'] ?? 50), 200);

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
        if (!$cot) { echo json_encode(['error' => 'No encontrada']); exit; }

        // Recalcular total desde partidas (c.subtotal puede ser bruto o neto según antigüedad del registro)
        $stBruto = $db->prepare("SELECT COALESCE(SUM(precio_m2_usado*m2*cantidad),0) FROM cotizaciones_partidas WHERE cotizacion_id=?");
        $stBruto->execute([$id]);
        $bruto_partidas = (float)$stBruto->fetchColumn();
        $subtotal_neto = round($bruto_partidas * (1 - (float)$cot['descuento']/100), 2);
        $cot['total'] = round(($subtotal_neto + (float)$cot['servicios_subtotal']) * 1.16, 2);
        $cot['saldo_pendiente'] = max(0, round($cot['total'] - (float)$cot['saldo_pagado'], 2));

        $stmt2 = $db->prepare("
            SELECT cp.*, cr.nombre as cristal_nombre_actual
            FROM cotizaciones_partidas cp
            LEFT JOIN cristales cr ON cp.cristal_id = cr.id
            WHERE cp.cotizacion_id = ?
            ORDER BY cp.num_partida ASC
        ");
        $stmt2->execute([$id]);
        $partidas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Adjuntar servicios a cada partida
        $stmtSrv = $db->prepare("
            SELECT cps.*, sc.nombre as servicio_nombre
            FROM cotizacion_partida_servicios cps
            LEFT JOIN servicios_catalogo sc ON sc.id = cps.servicio_id
            WHERE cps.partida_id = ?
            ORDER BY cps.id ASC
        ");
        foreach ($partidas as &$par) {
            $stmtSrv->execute([$par['id']]);
            $par['servicios'] = $stmtSrv->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($par);

        $cot['partidas'] = $partidas;

        // Datos de rechazo si aplica
        if ($cot['estatus'] === 'rechazada') {
            $stmtR = $db->prepare("SELECT motivo, monto_devuelto, registrado_por, created_at FROM rechazo_calidad WHERE cotizacion_id = ? ORDER BY id DESC LIMIT 1");
            $stmtR->execute([$id]);
            $cot['rechazo'] = $stmtR->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        echo json_encode($cot); exit;
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
               ROUND((COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16, 2) AS total,
               c.fecha_entrega, c.localidad, c.ciudad_destino, c.condicion_pago,
               GREATEST(0, ROUND((COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16, 2) - COALESCE(c.saldo_pagado,0)) AS saldo_pendiente,
               c.entrega_bloqueada,
               o.folio AS orden_folio
        FROM cotizaciones c
        LEFT JOIN ordenes o ON o.id = c.orden_id
        WHERE $where_str
        ORDER BY c.created_at DESC
        LIMIT $limit
    ");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

if (!$puede_editar) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── POST — crear cotización ──────────────────────────────────────────────────
if ($method === 'POST') {
    $accion = $body['accion'] ?? 'crear';

    // Autorizar entrega con adeudo
    if ($accion === 'autorizar_entrega') {
        if (!$es_admin) { echo json_encode(['error' => 'Solo dir_admin puede autorizar']); exit; }
        $id   = (int)($body['id'] ?? 0);
        $nota = trim($body['nota'] ?? '');
        $db->prepare("UPDATE cotizaciones SET entrega_bloqueada=0, entrega_autorizada_por=?, entrega_autorizada_at=NOW(), entrega_autorizada_nota=? WHERE id=?")
           ->execute([$usuario_id, $nota, $id]);
        echo json_encode(['ok' => true]); exit;
    }

    // Convertir cotización a orden de producción
    if ($accion === 'convertir_orden') {
        $id = (int)($body['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        $cot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cot) { echo json_encode(['error' => 'No encontrada']); exit; }
        if ($cot['estatus'] !== 'cotizacion') { echo json_encode(['error' => 'Solo se pueden convertir cotizaciones']); exit; }

        // Bloquear conversión si descuento > 10% sin autorización aprobada (excepto dir_admin)
        if ((float)$cot['descuento'] > 10 && $rol !== 'dir_admin') {
            $authStmt = $db->prepare("SELECT id FROM autorizaciones_descuento WHERE cotizacion_id = ? AND estatus = 'aprobado' LIMIT 1");
            $authStmt->execute([$id]);
            if (!$authStmt->fetch()) {
                echo json_encode(['error' => 'El descuento del ' . $cot['descuento'] . '% requiere autorización de Dirección. Solicita la autorización antes de convertir a orden.']); exit;
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
            $stmt3 = $db->prepare("INSERT INTO ordenes
                (folio, orden_trabajo, tipo, cliente_nombre, asesor, proyecto,
                 fecha_pedido, fecha_entrega, tipo_entrega, observaciones)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt3->execute([
                $folio_orden,
                $folio_orden,
                'suministro',
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
                    'Orden ' . $folio_orden . ' — ' . $cot['cliente_nombre'] . ' requiere autorización.',
                    $folio_orden,
                    $orden_id
                ]);
            } catch (Exception $ignored) {}

            echo json_encode(['ok' => true, 'orden_id' => $orden_id, 'folio' => $cot['folio']]); exit;

        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['error' => $e->getMessage()]); exit;
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
            echo json_encode(['error' => 'Datos incompletos']); exit;
        }

        $stmtS = $db->prepare("SELECT id, nombre, precio_default FROM servicios_catalogo WHERE id = ? AND activo = 1");
        $stmtS->execute([$servicio_id]);
        $srv = $stmtS->fetch(PDO::FETCH_ASSOC);
        if (!$srv) { echo json_encode(['error' => 'Servicio no encontrado']); exit; }

        // Verificar que la partida pertenece a la cotización
        $stmtP = $db->prepare("SELECT id FROM cotizaciones_partidas WHERE id = ? AND cotizacion_id = ?");
        $stmtP->execute([$partida_id, $cot_id]);
        if (!$stmtP->fetch()) { echo json_encode(['error' => 'Partida no pertenece a la cotización']); exit; }

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

            $db->commit();
            echo json_encode(['ok' => true, 'servicios_subtotal' => $srv_total, 'nuevo_servicio' => [
                'id' => (int)$db->lastInsertId(), 'descripcion' => $srv['nombre'],
                'precio_unitario' => $precio, 'unidades_por_pieza' => $und_x_pieza,
                'cantidad_piezas' => $cant_piezas, 'subtotal' => $subtotal,
            ]]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    // ── Eliminar servicio de partida ──────────────────────────────────────────
    if ($accion === 'eliminar_servicio') {
        $srv_partida_id = (int)($body['servicio_partida_id'] ?? 0);
        $cot_id         = (int)($body['cotizacion_id']       ?? 0);
        if (!$srv_partida_id || !$cot_id) { echo json_encode(['error' => 'Datos incompletos']); exit; }

        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM cotizacion_partida_servicios WHERE id = ? AND cotizacion_id = ?")
               ->execute([$srv_partida_id, $cot_id]);

            $st = $db->prepare("SELECT COALESCE(SUM(subtotal),0) as srv_total FROM cotizacion_partida_servicios WHERE cotizacion_id = ?");
            $st->execute([$cot_id]);
            $srv_total = (float)$st->fetchColumn();
            $db->prepare("UPDATE cotizaciones SET servicios_subtotal = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$srv_total, $cot_id]);

            $db->commit();
            echo json_encode(['ok' => true, 'servicios_subtotal' => $srv_total]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
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

    if (!$cliente_id) { echo json_encode(['error' => 'Cliente requerido']); exit; }
    if (empty($partidas)) { echo json_encode(['error' => 'Se requiere al menos una partida']); exit; }

    // Datos del cliente
    $stmt = $db->prepare("SELECT razon_social, nombre FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cliente) { echo json_encode(['error' => 'Cliente no encontrado']); exit; }
    $cliente_nombre = $cliente['razon_social'] ?: $cliente['nombre'];

    // Calcular fecha entrega
    $fecha_hoy    = date('Y-m-d');
    $fecha_entrega = $fecha_entrega_manual ?: calcularFechaEntrega($db, $fecha_hoy, $localidad, $ciudad);
    $es_manual    = $fecha_entrega_manual ? 1 : 0;

    // Calcular totales
    $subtotal_total = 0;
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

    if (empty($partidas_data)) { echo json_encode(['error' => 'Ninguna partida válida']); exit; }

    $iva_total   = round($subtotal_total * 0.16, 2);
    $total_final = round($subtotal_total + $iva_total, 2);
    $saldo       = ($condicion === 'anticipo') ? round($total_final * 0.5, 2) : $total_final;

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
            $alerta, $subtotal_total, $iva_total, $total_final,
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
        if ($descuento > 10 && $rol !== 'dir_admin') {
            $motivo_auth = trim($body['motivo_descuento'] ?? '');
            try {
                $db->prepare("INSERT INTO autorizaciones_descuento (cotizacion_id, folio, descuento, motivo, solicitado_por) VALUES (?,?,?,?,?)")
                   ->execute([$cot_id, $folio, $descuento, $motivo_auth, $usuario_nombre]);
            } catch (Exception $ignored) {}
        }

        echo json_encode(['ok' => true, 'id' => $cot_id, 'folio' => $folio,
                          'fecha_entrega' => $fecha_entrega, 'total' => $total_final]);

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ─── PUT — actualizar estatus / saldo / contenido ───────────────────────────────
if ($method === 'PUT') {
    $id      = (int)($body['id']      ?? 0);
    $accion  = $body['accion']        ?? '';
    if (!$id) { echo json_encode(['error' => 'ID requerido']); exit; }

    // ── Actualizar contenido completo de una cotización ──
    if ($accion === 'actualizar') {
        if (!$puede_editar) { echo json_encode(['error' => 'Sin permiso']); exit; }

        // Verificar que existe y está en estatus cotizacion
        $stmt = $db->prepare("SELECT estatus FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        $cot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cot) { echo json_encode(['error' => 'Cotización no encontrada']); exit; }
        if ($cot['estatus'] !== 'cotizacion') { echo json_encode(['error' => 'Solo se pueden editar cotizaciones (no órdenes)']); exit; }

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

        if (!$cliente_id) { echo json_encode(['error' => 'Cliente requerido']); exit; }
        if (empty($partidas)) { echo json_encode(['error' => 'Se requiere al menos una partida']); exit; }

        // Datos del cliente
        $stmt = $db->prepare("SELECT razon_social, nombre FROM clientes WHERE id = ?");
        $stmt->execute([$cliente_id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cliente) { echo json_encode(['error' => 'Cliente no encontrado']); exit; }
        $cliente_nombre = $cliente['razon_social'] ?: $cliente['nombre'];

        // Calcular fecha entrega
        $fecha_entrega = $fecha_entrega_manual ?: calcularFechaEntrega($db, date('Y-m-d'), $localidad, $ciudad);
        $es_manual     = $fecha_entrega_manual ? 1 : 0;

        // Calcular totales y validar partidas
        $subtotal_total = 0;
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

            $m2           = round(($ancho / 1000) * ($alto / 1000), 6);
            $precio_m2    = (float)$cristal['precio_m2'];
            $precio_unit  = round($m2 * $precio_m2 * (1 - $descuento / 100), 4);
            $subtotal     = round($precio_unit * $cantidad, 2);
            $iva          = round($subtotal * 0.16, 2);
            $total_p      = round($subtotal + $iva, 2);
            $subtotal_total += $subtotal;

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

        if (empty($partidas_data)) { echo json_encode(['error' => 'Ninguna partida válida']); exit; }

        $iva_total   = round($subtotal_total * 0.16, 2);
        $total_final = round($subtotal_total + $iva_total, 2);
        $saldo       = ($condicion === 'anticipo') ? round($total_final * 0.5, 2) : $total_final;

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
                $alerta, $subtotal_total, $iva_total, $total_final, $saldo,
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

            // Gestionar autorización de descuento al actualizar
            if ($descuento > 10 && $rol !== 'dir_admin') {
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

            echo json_encode(['ok' => true, 'total' => $total_final, 'fecha_entrega' => $fecha_entrega]);

        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($accion === 'marcar_entregada') {
        // Verificar bloqueo
        $stmt = $db->prepare("SELECT entrega_bloqueada, saldo_pendiente FROM cotizaciones WHERE id = ?");
        $stmt->execute([$id]);
        $cot = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($cot['entrega_bloqueada'] && !$es_admin) {
            echo json_encode(['error' => 'Entrega bloqueada por saldo pendiente', 'bloqueada' => true]); exit;
        }
        $db->prepare("UPDATE cotizaciones SET estatus='entregada', updated_at=NOW() WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]); exit;
    }

    if ($accion === 'actualizar_saldo') {
        $saldo = (float)($body['saldo'] ?? 0);
        $bloqueada = $saldo > 0 ? 1 : 0;
        $db->prepare("UPDATE cotizaciones SET saldo_pendiente=?, entrega_bloqueada=?, updated_at=NOW() WHERE id=?")
           ->execute([$saldo, $bloqueada, $id]);
        echo json_encode(['ok' => true]); exit;
    }

    if ($accion === 'cancelar') {
        if (!$es_admin) { echo json_encode(['error' => 'Solo dir_admin puede cancelar']); exit; }
        $db->prepare("UPDATE cotizaciones SET estatus='cancelada', updated_at=NOW() WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]); exit;
    }

    // ── Rechazar por calidad ──────────────────────────────────────────────────
    if ($accion === 'rechazar') {
        if (!$es_admin) { echo json_encode(['error' => 'Solo dir_admin puede registrar rechazos']); exit; }
        $motivo = trim($body['motivo'] ?? '');
        if (!$motivo) { echo json_encode(['error' => 'El motivo del rechazo es obligatorio']); exit; }

        $stmtCot = $db->prepare("SELECT id, orden_id, cliente_id, saldo_pagado, folio FROM cotizaciones WHERE id = ?");
        $stmtCot->execute([$id]);
        $cot = $stmtCot->fetch(PDO::FETCH_ASSOC);
        if (!$cot) { echo json_encode(['error' => 'Cotización no encontrada']); exit; }
        if (!$cot['orden_id']) { echo json_encode(['error' => 'Esta cotización no tiene una orden generada']); exit; }

        $montoDevuelto = (float)($cot['saldo_pagado'] ?? 0);
        $db->beginTransaction();

        // 1. Marcar orden y cotización como rechazadas
        $db->prepare("UPDATE ordenes SET estado='rechazada', updated_at=NOW() WHERE id=?")
           ->execute([$cot['orden_id']]);
        $db->prepare("UPDATE cotizaciones SET estatus='rechazada', updated_at=NOW() WHERE id=?")
           ->execute([$id]);

        // 2. Insertar en bitácora de rechazos
        $db->prepare("INSERT INTO rechazo_calidad (cotizacion_id, orden_id, cliente_id, motivo, monto_devuelto, registrado_por)
                      VALUES (?, ?, ?, ?, ?, ?)")
           ->execute([$id, $cot['orden_id'], $cot['cliente_id'], $motivo, $montoDevuelto, $user['nombre']]);

        // 3. Mover saldo_pagado a saldo a favor del cliente
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
        echo json_encode(['ok' => true, 'monto_devuelto' => $montoDevuelto]); exit;
    }

    echo json_encode(['error' => 'Acción no reconocida']); exit;
}

// ─── GET especial: calcular fecha entrega ─────────────────────────────────────
if ($method === 'GET' && isset($_GET['calcular_fecha'])) {
    $fecha    = $_GET['fecha']     ?? date('Y-m-d');
    $localidad= $_GET['localidad'] ?? 'local';
    $ciudad   = $_GET['ciudad']    ?? '';
    echo json_encode(['fecha_entrega' => calcularFechaEntrega($db, $fecha, $localidad, $ciudad)]); exit;
}

echo json_encode(['error' => 'Método no soportado']);