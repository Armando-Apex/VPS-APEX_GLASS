<?php
// ============================================================
//  APEX GLASS - API: Finanzas (VoBo + Pagos)
//  Archivo: api/finanzas.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');

$user           = requireSessionApi();
$rol            = $user['rol'];
$usuario_nombre = $user['nombre'];
$method         = $_SERVER['REQUEST_METHOD'];
$db             = getDB();

$es_finanzas = in_array($rol, ['administracion', 'dir_admin', 'dueno']);
if (!$es_finanzas) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']); exit;
}

// ─── Función: calcular fecha entrega desde fecha VoBo ────────────────────────
function calcularFechaVobo($db, $localidad, $ciudad) {
    $stmt = $db->query("SELECT fecha FROM festivos");
    $festivos = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'fecha');

    $fecha  = new DateTime(); // hoy = día del VoBo
    $dias   = 0;
    $target = 5;

    // Para Saltillo necesitamos saber cuántos días hábiles hay hasta el próximo viernes
    if (strtolower($localidad) === 'foraneo' && stripos($ciudad ?? '', 'saltillo') !== false) {
        // Avanzar 4 días hábiles desde hoy para ver si llegamos al viernes
        $test = clone $fecha;
        $hab  = 0;
        while ($hab < 4) {
            $test->modify('+1 day');
            $dow = (int)$test->format('N');
            $f   = $test->format('Y-m-d');
            if ($dow >= 6 || in_array($f, $festivos)) continue;
            $hab++;
        }
        // Encontrar el próximo viernes desde hoy
        $viernes = clone $fecha;
        while ((int)$viernes->format('N') !== 5) {
            $viernes->modify('+1 day');
        }
        // Si ese viernes es festivo, avanzar al siguiente viernes
        while (in_array($viernes->format('Y-m-d'), $festivos)) {
            $viernes->modify('+7 days');
        }
        // Si los 4 días hábiles nos llevan más allá del viernes → siguiente viernes
        if ($test > $viernes) {
            $viernes->modify('+7 days');
            while (in_array($viernes->format('Y-m-d'), $festivos)) {
                $viernes->modify('+7 days');
            }
        }
        return $viernes->format('Y-m-d');
    }

    // Local u otro foráneo: 5 días hábiles
    while ($dias < $target) {
        $fecha->modify('+1 day');
        $dow = (int)$fecha->format('N');
        $f   = $fecha->format('Y-m-d');
        if ($dow >= 6 || in_array($f, $festivos)) continue;
        $dias++;
    }
    return $fecha->format('Y-m-d');
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $accion = $_GET['accion'] ?? 'lista_vobo';

    // Lista de órdenes pendiente_vobo (más vieja primero)
    if ($accion === 'lista_vobo') {
        $stmt = $db->query("
            SELECT o.id, o.folio, o.cliente_nombre, o.asesor, o.fecha_pedido,
                   c.localidad, c.ciudad_destino, c.tipo_entrega,
                   c.id as cot_id,
                   ROUND((COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16, 2) AS total,
                   GREATEST(0, ROUND((COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16, 2) - COALESCE(c.saldo_pagado,0)) AS saldo_pendiente,
                   c.saldo_pagado,
                   c.condicion_pago, c.folio as cot_folio
            FROM ordenes o
            LEFT JOIN cotizaciones c ON c.orden_id = o.id
            WHERE o.estado = 'pendiente_vobo'
            ORDER BY o.fecha_pedido ASC, o.id ASC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    // Detalle de una orden con sus pagos
    if ($accion === 'detalle' && isset($_GET['orden_id'])) {
        $orden_id = (int)$_GET['orden_id'];

        $stmt = $db->prepare("
            SELECT o.*, c.localidad, c.id as cot_id,
                   ROUND((COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16, 2) AS total,
                   GREATEST(0, ROUND((COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16, 2) - COALESCE(c.saldo_pagado,0)) AS saldo_pendiente,
                   c.saldo_pagado,
                   c.condicion_pago, c.folio as cot_folio, c.vobo_por, c.vobo_at, c.cliente_id
            FROM ordenes o
            LEFT JOIN cotizaciones c ON c.orden_id = o.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orden_id]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orden) { echo json_encode(['error' => 'No encontrada']); exit; }

        // Pagos registrados
        $stmt2 = $db->prepare("
            SELECT * FROM cotizacion_pagos
            WHERE cotizacion_id = ?
            ORDER BY fecha_pago ASC, hora_pago ASC
        ");
        $stmt2->execute([$orden['cot_id']]);
        $orden['pagos'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        // Fecha entrega sugerida al dar VoBo ahora
        $orden['fecha_vobo_sugerida'] = calcularFechaVobo($db, $orden['localidad'] ?? 'LOCAL', $orden['ciudad_destino'] ?? '');

        echo json_encode($orden); exit;
    }

    // ── Cobranza general — todas las órdenes con cotización ──
    if ($accion === 'cobranza') {
        $stmt = $db->query("
            SELECT o.id, o.folio, o.cliente_nombre, o.asesor, o.fecha_pedido,
                   o.estado, c.localidad, c.ciudad_destino, c.tipo_entrega,
                   c.id as cot_id,
                   ROUND((COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16, 2) AS total,
                   GREATEST(0, ROUND((COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16, 2) - COALESCE(c.saldo_pagado,0)) AS saldo_pendiente,
                   c.saldo_pagado,
                   c.condicion_pago, c.folio as cot_folio, c.vobo_por, c.vobo_at,
                   COALESCE(c.estatus_pago, 'pendiente') as estatus_pago
            FROM cotizaciones c
            JOIN ordenes o ON o.id = c.orden_id
            WHERE c.orden_id IS NOT NULL
              AND o.estado != 'cancelada'
              AND c.estatus != 'cancelada'
            ORDER BY o.fecha_pedido DESC
        ");
        $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cargar pagos de cada cotización
        foreach ($ordenes as &$ord) {
            $stmt2 = $db->prepare("SELECT * FROM cotizacion_pagos WHERE cotizacion_id = ? ORDER BY fecha_pago ASC, hora_pago ASC");
            $stmt2->execute([$ord['cot_id']]);
            $ord['pagos'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($ordenes); exit;
    }

    echo json_encode(['error' => 'Acción no reconocida']); exit;
}

// ─── POST: registrar pago ─────────────────────────────────────────────────────
if ($method === 'POST') {
    $accion = $body['accion'] ?? '';

    if ($accion === 'actualizar_estatus_pago') {
        $cot_id       = (int)($body['cotizacion_id'] ?? 0);
        $estatus_pago = $body['estatus_pago'] ?? '';
        $validos      = ['pendiente', 'en_proceso', 'pago_entrega', 'pagado'];
        if (!$cot_id || !in_array($estatus_pago, $validos)) {
            echo json_encode(['error' => 'Datos inválidos']); exit;
        }
        $db->prepare("UPDATE cotizaciones SET estatus_pago = ?, updated_at = NOW() WHERE id = ?")
           ->execute([$estatus_pago, $cot_id]);
        echo json_encode(['ok' => true, 'estatus_pago' => $estatus_pago]); exit;
    }

    if ($accion === 'registrar_pago') {
        $cot_id    = (int)($body['cotizacion_id'] ?? 0);
        $fecha     = trim($body['fecha_pago']  ?? date('Y-m-d'));
        $hora      = trim($body['hora_pago']   ?? date('H:i:s'));
        $monto     = (float)($body['monto']    ?? 0);
        $forma     = $body['forma_pago']       ?? 'efectivo';
        $notas     = trim($body['notas']       ?? '');

        if (!$cot_id || $monto <= 0) {
            echo json_encode(['error' => 'Datos incompletos']); exit;
        }
        if (!in_array($forma, ['efectivo','tarjeta','transferencia','saldo_favor'])) {
            echo json_encode(['error' => 'Forma de pago inválida']); exit;
        }

        $db->beginTransaction();
        try {
            // Insertar pago
            $db->prepare("INSERT INTO cotizacion_pagos
                (cotizacion_id, fecha_pago, hora_pago, monto, forma_pago, notas, registrado_por)
                VALUES (?,?,?,?,?,?,?)
            ")->execute([$cot_id, $fecha, $hora, $monto, $forma, $notas, $usuario_nombre]);

            // Actualizar saldo_pagado en cotizaciones
            $db->prepare("UPDATE cotizaciones SET
                saldo_pagado = COALESCE(saldo_pagado,0) + ?,
                updated_at = NOW()
                WHERE id = ?
            ")->execute([$monto, $cot_id]);

            // Si aplica saldo a favor → descontar del monedero del cliente
            if ($forma === 'saldo_favor') {
                // Derivar cliente_id del servidor — nunca del body para evitar IDOR
                $stmt_c = $db->prepare("SELECT cliente_id FROM cotizaciones WHERE id = ?");
                $stmt_c->execute([$cot_id]);
                $cliente_id = (int)($stmt_c->fetchColumn() ?: 0);
                if (!$cliente_id) {
                    throw new Exception('Cotización sin cliente asociado');
                }
                $stmt_s = $db->prepare("SELECT COALESCE(SUM(monto),0) as saldo FROM clientes_saldo_favor WHERE cliente_id = ?");
                $stmt_s->execute([$cliente_id]);
                $saldo_disp = (float)$stmt_s->fetch(PDO::FETCH_ASSOC)['saldo'];
                if ($saldo_disp < $monto) {
                    throw new Exception('Saldo a favor insuficiente. Disponible: $' . number_format($saldo_disp, 2));
                }
                $stmt_f = $db->prepare("SELECT o.folio FROM ordenes o JOIN cotizaciones c ON c.orden_id = o.id WHERE c.id = ?");
                $stmt_f->execute([$cot_id]);
                $fila = $stmt_f->fetch(PDO::FETCH_ASSOC);
                $ref  = $fila ? 'Aplicado a ' . $fila['folio'] : 'Aplicado a cot. #' . $cot_id;
                $db->prepare("INSERT INTO clientes_saldo_favor
                    (cliente_id, tipo, monto, fecha, referencia, notas, cotizacion_id, creado_por)
                    VALUES (?, 'aplicacion', ?, ?, ?, ?, ?, ?)
                ")->execute([$cliente_id, -$monto, $fecha, $ref, $notas, $cot_id, $usuario_nombre]);
            }

            $db->commit();

            // Devolver saldo actualizado (bruto desde partidas para evitar inconsistencia en c.subtotal)
            $stmt = $db->prepare("SELECT saldo_pagado, descuento, servicios_subtotal,
                COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id = c.id),0) AS bruto_partidas
                FROM cotizaciones c WHERE c.id = ?");
            $stmt->execute([$cot_id]);
            $cot = $stmt->fetch(PDO::FETCH_ASSOC);
            $total_real = round(((float)$cot['bruto_partidas'] * (1 - (float)$cot['descuento']/100) + (float)$cot['servicios_subtotal']) * 1.16, 2);

            // Marcar como pagado automáticamente si el saldo cubre el total
            if ((float)$cot['saldo_pagado'] >= $total_real && $total_real > 0) {
                $db->prepare("UPDATE cotizaciones SET estatus_pago = 'pagado', updated_at = NOW() WHERE id = ?")
                   ->execute([$cot_id]);
            }

            echo json_encode([
                'ok'           => true,
                'saldo_pagado' => $cot['saldo_pagado'],
                'total'        => $total_real,
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['error' => 'Acción no reconocida']); exit;
}

// ─── PUT: dar VoBo ───────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $accion   = $body['accion'] ?? '';
    $orden_id = (int)($body['orden_id'] ?? 0);

    if ($accion === 'vobo' && $orden_id) {
        // Fecha entrega: usa la que manda Lina (puede haberla ajustado)
        $fecha_entrega = trim($body['fecha_entrega'] ?? '');

        // Verificar que la orden está en pendiente_vobo
        $stmt = $db->prepare("SELECT o.*, c.localidad, c.id as cot_id FROM ordenes o LEFT JOIN cotizaciones c ON c.orden_id = o.id WHERE o.id = ? AND o.estado = 'pendiente_vobo'");
        $stmt->execute([$orden_id]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orden) {
            echo json_encode(['error' => 'Orden no encontrada o no está pendiente de VoBo']); exit;
        }

        // Si no viene fecha, calcular ahora
        if (!$fecha_entrega) {
            $fecha_entrega = calcularFechaVobo($db, $orden['localidad'] ?? 'LOCAL', $orden['ciudad_destino'] ?? '');
        }

        $db->beginTransaction();
        try {
            // Activar orden y fijar fecha de entrega real
            $db->prepare("UPDATE ordenes SET
                estado = 'activa',
                fecha_entrega = ?,
                updated_at = NOW()
                WHERE id = ?
            ")->execute([$fecha_entrega, $orden_id]);

            // Marcar VoBo en cotización
            if ($orden['cot_id']) {
                $db->prepare("UPDATE cotizaciones SET
                    vobo_por = ?,
                    vobo_at  = NOW(),
                    updated_at = NOW()
                    WHERE id = ?
                ")->execute([$usuario_nombre, $orden['cot_id']]);
            }

            $db->commit();

            // Notificar al asesor comercial que su orden entró a producción
            try {
                $asesor_row = $db->prepare("SELECT id FROM usuarios WHERE nombre = ? AND rol = 'comercial' LIMIT 1");
                $asesor_row->execute([$orden['asesor']]);
                $asesor_row = $asesor_row->fetch(PDO::FETCH_ASSOC);
                $db->prepare("
                    INSERT INTO notificaciones (tipo, titulo, mensaje, folio, orden_id, rol_destino, usuario_id_dest, leida)
                    VALUES ('vobo_aprobado', 'Orden aprobada — en producción', ?, ?, ?, 'comercial', ?, 0)
                ")->execute([
                    'La orden ' . $orden['folio'] . ' fue autorizada y ya está en producción.',
                    $orden['folio'],
                    $orden_id,
                    $asesor_row['id'] ?? null
                ]);
            } catch (Exception $ignored) {}

            echo json_encode([
                'ok'            => true,
                'fecha_entrega' => $fecha_entrega,
                'vobo_por'      => $usuario_nombre,
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['error' => 'Acción no reconocida']); exit;
}

echo json_encode(['error' => 'Método no soportado']);