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

        $stmt2 = $db->prepare("
            SELECT cp.*, cr.nombre as cristal_nombre_actual
            FROM cotizaciones_partidas cp
            LEFT JOIN cristales cr ON cp.cristal_id = cr.id
            WHERE cp.cotizacion_id = ?
            ORDER BY cp.num_partida ASC
        ");
        $stmt2->execute([$id]);
        $cot['partidas'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
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
        $where[]  = '(c.folio LIKE ? OR c.cliente_nombre LIKE ? OR c.proyecto LIKE ?)';
        $like     = "%$q%";
        $params   = array_merge($params, [$like, $like, $like]);
    }
    $where_str = implode(' AND ', $where);
    $stmt = $db->prepare("
        SELECT c.id, c.folio, c.fecha, c.cliente_nombre, c.asesor_nombre,
               c.proyecto, c.estatus, c.total, c.fecha_entrega,
               c.localidad, c.ciudad_destino, c.condicion_pago,
               c.saldo_pendiente, c.entrega_bloqueada,
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

    // Crear cotización nueva
    $cliente_id   = (int)($body['cliente_id']    ?? 0);
    $proyecto     = trim($body['proyecto']        ?? '');
    $descuento    = (float)($body['descuento']    ?? 0);
    $credito      = ($body['credito'] ?? 'no') === 'si' ? 'si' : 'no';
    $condicion    = in_array($body['condicion_pago'] ?? '', ['anticipo','pago_total']) ? $body['condicion_pago'] : 'anticipo';
    $tipo_entrega = in_array($body['tipo_entrega'] ?? '', ['domicilio','planta']) ? $body['tipo_entrega'] : 'domicilio';
    $localidad    = ($body['localidad'] ?? '') === 'foraneo' ? 'foraneo' : 'local';
    $ciudad       = trim($body['ciudad_destino']  ?? '');
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
             localidad, ciudad_destino, fecha_entrega, fecha_entrega_manual,
             alerta, subtotal, iva, total, saldo_pendiente, entrega_bloqueada, estatus)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $folio, $fecha_hoy, $cliente_id, $cliente_nombre,
            $usuario_id, $usuario_nombre,
            $proyecto, $descuento, $credito, $condicion, $tipo_entrega,
            $localidad, $ciudad, $fecha_entrega, $es_manual,
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
                ciudad_destino=?, fecha_entrega=?, fecha_entrega_manual=?,
                alerta=?, subtotal=?, iva=?, total=?, saldo_pendiente=?,
                updated_at=NOW()
                WHERE id=?
            ")->execute([
                $cliente_id, $cliente_nombre, $proyecto, $descuento,
                $credito, $condicion, $tipo_entrega, $localidad,
                $ciudad, $fecha_entrega, $es_manual,
                $alerta, $subtotal_total, $iva_total, $total_final, $saldo,
                $id
            ]);

            // Reemplazar partidas
            $db->prepare("DELETE FROM cotizaciones_partidas WHERE cotizacion_id = ?")->execute([$id]);

            $stmtP = $db->prepare("INSERT INTO cotizaciones_partidas
                (cotizacion_id, num_partida, cristal_id, cristal_nombre, cristal_etiqueta,
                 precio_m2_usado, cantidad, ancho, alto, m2, detalles, cpb,
                 resaques, taladros_pasados, taladros_avellanados, requiere_templado,
                 precio_unitario, subtotal, iva, total, comentarios_etiqueta)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            foreach ($partidas_data as $i => $p) {
                $stmtP->execute([
                    $id, $i + 1,
                    $p['cristal_id'], $p['cristal_nombre'], $p['cristal_etiqueta'],
                    $p['precio_m2_usado'], $p['cantidad'], $p['ancho'], $p['alto'], $p['m2'],
                    $p['detalles'], $p['cpb'], $p['resaques'],
                    $p['taladros_pasados'], $p['taladros_avellanados'], $p['requiere_templado'],
                    $p['precio_unitario'], $p['subtotal'], $p['iva'], $p['total'],
                    $p['comentarios_etiqueta']
                ]);
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