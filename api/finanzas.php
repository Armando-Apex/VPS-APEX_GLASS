<?php
// ============================================================
//  APEX GLASS - API: Finanzas (VoBo + Pagos)
//  Archivo: api/finanzas.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
require_once __DIR__ . '/helpers/totales.php'; // A-2

header('Content-Type: application/json; charset=utf-8');

$user           = requireSessionApi();
$rol            = $user['rol'];
$usuario_nombre = $user['nombre'];
$method         = $_SERVER['REQUEST_METHOD'];
$db             = getDB();

$es_finanzas = in_array($rol, ['administracion', 'dir_admin', 'dueno', 'desarrollo']);
if (!$es_finanzas) {
    jsonResponse(['error' => 'Sin permiso'], 403);
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
                   ROUND(CASE WHEN c.tipo = 'maquila' THEN c.total ELSE (COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16 END, 2) AS total,
                   GREATEST(0, ROUND(CASE WHEN c.tipo = 'maquila' THEN c.total ELSE (COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16 END, 2) - COALESCE(c.saldo_pagado,0)) AS saldo_pendiente,
                   c.saldo_pagado,
                   c.condicion_pago, c.folio as cot_folio
            FROM ordenes o
            LEFT JOIN cotizaciones c ON c.orden_id = o.id
            WHERE o.estado = 'pendiente_vobo'
            ORDER BY o.fecha_pedido ASC, o.id ASC
        ");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    // Detalle de una orden con sus pagos
    if ($accion === 'detalle' && isset($_GET['orden_id'])) {
        $orden_id = (int)$_GET['orden_id'];

        $stmt = $db->prepare("
            SELECT o.*, c.localidad, c.id as cot_id,
                   ROUND(CASE WHEN c.tipo = 'maquila' THEN c.total ELSE (COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16 END, 2) AS total,
                   GREATEST(0, ROUND(CASE WHEN c.tipo = 'maquila' THEN c.total ELSE (COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16 END, 2) - COALESCE(c.saldo_pagado,0)) AS saldo_pendiente,
                   c.saldo_pagado,
                   c.condicion_pago, c.folio as cot_folio, c.vobo_por, c.vobo_at, c.cliente_id
            FROM ordenes o
            LEFT JOIN cotizaciones c ON c.orden_id = o.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orden_id]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orden) { jsonResponse(['error' => 'No encontrada']); exit; }

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

        jsonResponse($orden); exit;
    }

    // ── Cobranza general — todas las órdenes con cotización ──
    if ($accion === 'cobranza') {
        $stmt = $db->query("
            SELECT o.id, o.folio, o.cliente_nombre, o.asesor, o.fecha_pedido,
                   o.estado, c.localidad, c.ciudad_destino, c.tipo_entrega,
                   c.id as cot_id, c.cliente_id,
                   ROUND(CASE WHEN c.tipo = 'maquila' THEN c.total ELSE (COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16 END, 2) AS total,
                   GREATEST(0, ROUND(CASE WHEN c.tipo = 'maquila' THEN c.total ELSE (COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id=c.id),0) * (1 - COALESCE(c.descuento,0)/100) + COALESCE(c.servicios_subtotal,0)) * 1.16 END, 2) - COALESCE(c.saldo_pagado,0)) AS saldo_pendiente,
                   c.saldo_pagado,
                   c.condicion_pago, c.folio as cot_folio, c.vobo_por, c.vobo_at,
                   COALESCE(c.estatus_pago, 'pendiente') as estatus_pago
            FROM cotizaciones c
            JOIN ordenes o ON o.id = c.orden_id
            WHERE c.orden_id IS NOT NULL
              AND o.estado NOT IN ('cancelada', 'rechazada')
              AND c.estatus NOT IN ('cancelada', 'rechazada')
            ORDER BY
                CASE COALESCE(c.estatus_pago,'pendiente')
                    WHEN 'pendiente' THEN 0
                    WHEN 'parcial'   THEN 1
                    WHEN 'pagado'    THEN 2
                    ELSE 3
                END ASC,
                o.id DESC
        ");
        $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cargar pagos de cada cotización
        foreach ($ordenes as &$ord) {
            $stmt2 = $db->prepare("SELECT * FROM cotizacion_pagos WHERE cotizacion_id = ? ORDER BY fecha_pago ASC, hora_pago ASC");
            $stmt2->execute([$ord['cot_id']]);
            $ord['pagos'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        jsonResponse($ordenes); exit;
    }

    jsonResponse(['error' => 'Acción no reconocida']); exit;
}

// ─── POST: registrar pago ─────────────────────────────────────────────────────
if ($method === 'POST') {
    $accion = $body['accion'] ?? '';

    if ($accion === 'actualizar_estatus_pago') {
        $cot_id       = (int)($body['cotizacion_id'] ?? 0);
        $estatus_pago = $body['estatus_pago'] ?? '';
        $nota         = trim($body['nota'] ?? '');
        $validos      = ['pendiente', 'en_proceso', 'pago_entrega', 'pagado'];
        if (!$cot_id || !in_array($estatus_pago, $validos)) {
            jsonResponse(['error' => 'Datos inválidos']); exit;
        }

        // C-11: evidencia de pagos (recalculada en servidor) o override de dir_admin;
        // TODO cambio queda en bitácora (quién/cuándo/valor anterior).
        $stmtC = $db->prepare("SELECT folio, estatus_pago, COALESCE(saldo_pagado,0) AS saldo_pagado
                               FROM cotizaciones WHERE id = ?");
        $stmtC->execute([$cot_id]);
        $cotPago = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$cotPago) { jsonResponse(['error' => 'Cotización no encontrada']); exit; }

        $totales   = apexTotalesCotizacion($db, $cot_id);
        $pagado    = (float)$cotPago['saldo_pagado'];
        $cubierto  = $totales['total'] > 0 && $pagado >= $totales['total'] - 0.99;
        $es_dir    = in_array($rol, ['dir_admin', 'desarrollo']);

        // 'pagado' manual exige que los pagos registrados cubran el total canónico…
        if ($estatus_pago === 'pagado' && !$cubierto) {
            if (!$es_dir) {
                jsonResponse(['error' => 'No se puede marcar Pagado: hay $' . number_format($pagado, 2)
                    . ' cobrados de $' . number_format($totales['total'], 2)
                    . '. Registra el pago faltante primero.']); exit;
            }
            // …salvo override de dir_admin, que exige motivo y queda en bitácora.
            if (!$nota) {
                jsonResponse(['error' => 'Para marcar Pagado sin cubrir el total debes indicar el motivo (override dir_admin).']); exit;
            }
        }

        $db->beginTransaction();
        try {
            $db->prepare("UPDATE cotizaciones SET estatus_pago = ?, updated_at = NOW() WHERE id = ?")
               ->execute([$estatus_pago, $cot_id]);

            // Bitácora — misma tabla que correcciones: quién autorizó la salida sin cobrar
            $db->prepare("INSERT INTO correcciones_log
                (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, motivo, usuario)
                VALUES ('cotizacion', ?, ?, 'estatus_pago', ?, ?, ?, ?)")
               ->execute([
                   $cot_id, $cotPago['folio'], $cotPago['estatus_pago'], $estatus_pago,
                   $nota !== '' ? $nota
                                : 'Cobrado $' . number_format($pagado, 2) . ' de $' . number_format($totales['total'], 2),
                   $usuario_nombre
               ]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()]);
        }
        jsonResponse(['ok' => true, 'estatus_pago' => $estatus_pago]); exit;
    }

    if ($accion === 'registrar_pago') {
        $cot_id    = (int)($body['cotizacion_id'] ?? 0);
        $fecha     = trim($body['fecha_pago']  ?? date('Y-m-d'));
        $hora      = trim($body['hora_pago']   ?? date('H:i:s'));
        $monto     = (float)($body['monto']    ?? 0);
        $forma     = $body['forma_pago']       ?? 'efectivo';
        $notas     = trim($body['notas']       ?? '');

        if (!$cot_id || $monto <= 0) {
            jsonResponse(['error' => 'Datos incompletos']); exit;
        }
        if (!in_array($forma, ['efectivo','tarjeta','transferencia','saldo_favor'])) {
            jsonResponse(['error' => 'Forma de pago inválida']); exit;
        }

        // A-10: fecha_pago validada en servidor — sin backdating que altere
        // cortes ya cerrados ni fechas futuras que descuadren "cobrado".
        $dtFecha = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$dtFecha || $dtFecha->format('Y-m-d') !== $fecha) {
            jsonResponse(['error' => 'fecha_pago inválida (formato AAAA-MM-DD)']); exit;
        }
        if ($fecha > date('Y-m-d')) {
            jsonResponse(['error' => 'fecha_pago no puede ser futura']); exit;
        }
        $minFecha = date('Y-m-d', strtotime('first day of last month'));
        if ($fecha < $minFecha) {
            jsonResponse(['error' => 'fecha_pago demasiado antigua (mínimo ' . $minFecha . '): alteraría cortes ya cerrados. Registra con fecha actual y anota la fecha real en notas.']); exit;
        }

        // Anti-doble-clic: mismo pago (cotización+monto+forma) registrado hace <8s = envío duplicado
        $stmt_dup = $db->prepare("SELECT id FROM cotizacion_pagos
            WHERE cotizacion_id = ? AND monto = ? AND forma_pago = ?
              AND created_at >= (NOW() - INTERVAL 8 SECOND) LIMIT 1");
        $stmt_dup->execute([$cot_id, $monto, $forma]);
        if ($stmt_dup->fetch()) {
            jsonResponse(['error' => 'Este pago ya se registró hace unos segundos (posible doble clic). Revisa el historial antes de reintentar.']); exit;
        }

        $db->beginTransaction();
        try {
            // Calcular total y saldo pendiente ANTES de aplicar el pago.
            // A-10: FOR UPDATE — dos pagos concurrentes no pueden leer el mismo saldo;
            // y no se aceptan pagos sobre cotizaciones canceladas/rechazadas.
            $stmt_pre = $db->prepare("SELECT COALESCE(saldo_pagado,0) as saldo_pagado, descuento, servicios_subtotal, tipo, total, estatus,
                COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id = c.id),0) AS bruto_partidas,
                cliente_id
                FROM cotizaciones c WHERE c.id = ? FOR UPDATE");
            $stmt_pre->execute([$cot_id]);
            $pre = $stmt_pre->fetch(PDO::FETCH_ASSOC);
            if (!$pre) throw new Exception('Cotización no encontrada');
            if (in_array($pre['estatus'], ['cancelada', 'rechazada'])) {
                throw new Exception('No se pueden registrar pagos en una cotización ' . $pre['estatus']);
            }

            // A-2: fórmula canónica única (maquila: c.total ya es canónico tras C-5)
            $total_real      = ($pre['tipo'] === 'maquila')
                ? round((float)$pre['total'], 2)
                : apexTotales((float)$pre['bruto_partidas'], (float)$pre['descuento'], (float)$pre['servicios_subtotal'])['total'];
            $saldo_pendiente = round(max(0, $total_real - (float)$pre['saldo_pagado']), 2);
            if ($saldo_pendiente <= 0.01) {
                throw new Exception('La cotización ya está liquidada — registra el excedente como depósito en Saldo a Favor');
            }
            $excedente       = round($monto - $saldo_pendiente, 2);

            // A-10: TODO excedente real (> $0.01 de tolerancia de redondeo) va a una
            // cuenta explícita (saldo a favor). Nada se "absorbe": saldo_pagado
            // nunca queda por encima del total y por_cobrar nunca se subestima.
            $monto_aplicar = ($excedente > 0.01) ? $saldo_pendiente : $monto;
            $depositar_favor = ($excedente > 0.01) ? $excedente : 0.0;

            // Insertar pago (por el monto que realmente se aplica a la orden)
            $db->prepare("INSERT INTO cotizacion_pagos
                (cotizacion_id, fecha_pago, hora_pago, monto, forma_pago, notas, registrado_por)
                VALUES (?,?,?,?,?,?,?)
            ")->execute([$cot_id, $fecha, $hora, $monto_aplicar, $forma, $notas, $usuario_nombre]);

            // Actualizar saldo_pagado en cotizaciones
            $db->prepare("UPDATE cotizaciones SET
                saldo_pagado = COALESCE(saldo_pagado,0) + ?,
                updated_at = NOW()
                WHERE id = ?
            ")->execute([$monto_aplicar, $cot_id]);

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

            // Si el pago excedió el total en más de $5 → depositar diferencia como saldo a favor
            if ($depositar_favor > 0) {
                $cliente_id_dep = (int)($pre['cliente_id'] ?? 0);
                if (!$cliente_id_dep) throw new Exception('Cotización sin cliente asociado para depósito de excedente');
                $stmt_fol = $db->prepare("SELECT o.folio FROM ordenes o JOIN cotizaciones c ON c.orden_id = o.id WHERE c.id = ?");
                $stmt_fol->execute([$cot_id]);
                $fila_fol = $stmt_fol->fetch(PDO::FETCH_ASSOC);
                $ref_dep  = 'Excedente de pago en ' . ($fila_fol ? $fila_fol['folio'] : 'cot. #'.$cot_id);
                $db->prepare("INSERT INTO clientes_saldo_favor
                    (cliente_id, tipo, monto, fecha, referencia, notas, cotizacion_id, creado_por)
                    VALUES (?, 'deposito', ?, ?, ?, ?, ?, ?)
                ")->execute([$cliente_id_dep, $depositar_favor, $fecha, $ref_dep, $notas, $cot_id, $usuario_nombre]);
            }

            $db->commit();

            // Devolver saldo actualizado (bruto desde partidas para evitar inconsistencia en c.subtotal)
            $stmt = $db->prepare("SELECT saldo_pagado, descuento, servicios_subtotal, tipo, total,
                COALESCE((SELECT SUM(cp.precio_m2_usado*cp.m2*cp.cantidad) FROM cotizaciones_partidas cp WHERE cp.cotizacion_id = c.id),0) AS bruto_partidas
                FROM cotizaciones c WHERE c.id = ?");
            $stmt->execute([$cot_id]);
            $cot = $stmt->fetch(PDO::FETCH_ASSOC);
            // A-2: fórmula canónica única (maquila: c.total ya es canónico tras C-5)
            $total_real = ($cot['tipo'] === 'maquila')
                ? round((float)$cot['total'], 2)
                : apexTotales((float)$cot['bruto_partidas'], (float)$cot['descuento'], (float)$cot['servicios_subtotal'])['total'];

            // Marcar como pagado automáticamente si el saldo cubre el total (tolerancia $0.99)
            if ($total_real > 0 && ($total_real - (float)$cot['saldo_pagado']) <= 0.99) {
                $db->prepare("UPDATE cotizaciones SET estatus_pago = 'pagado', updated_at = NOW() WHERE id = ?")
                   ->execute([$cot_id]);
            }

            jsonResponse([
                'ok'              => true,
                'saldo_pagado'    => $cot['saldo_pagado'],
                'total'           => $total_real,
                'excedente'       => $depositar_favor > 0 ? $depositar_favor : null,
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()]);
        }
        exit;
    }

    jsonResponse(['error' => 'Acción no reconocida']); exit;
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
            jsonResponse(['error' => 'Orden no encontrada o no está pendiente de VoBo']); exit;
        }

        // Si no viene fecha, calcular ahora
        if (!$fecha_entrega) {
            $fecha_entrega = calcularFechaVobo($db, $orden['localidad'] ?? 'LOCAL', $orden['ciudad_destino'] ?? '');
        }

        // C-12: fecha de entrega válida y no pasada
        $dtFecha = DateTime::createFromFormat('Y-m-d', $fecha_entrega);
        if (!$dtFecha || $dtFecha->format('Y-m-d') !== $fecha_entrega) {
            jsonResponse(['error' => 'Fecha de entrega inválida']); exit;
        }
        if ($fecha_entrega < date('Y-m-d')) {
            jsonResponse(['error' => 'La fecha de entrega no puede ser anterior a hoy']); exit;
        }

        // C-12: condiciones de pago — sin anticipo no hay producción.
        // condicion_pago: 'anticipo' = 50% del total; 'pago_total' = 100%.
        $vobo_override = false;
        if ($orden['cot_id']) {
            $stmtCP = $db->prepare("SELECT condicion_pago, COALESCE(saldo_pagado,0) AS saldo_pagado, folio
                                    FROM cotizaciones WHERE id = ?");
            $stmtCP->execute([$orden['cot_id']]);
            $cp = $stmtCP->fetch(PDO::FETCH_ASSOC);

            $totalesCot    = apexTotalesCotizacion($db, $orden['cot_id']);
            $totalCot      = $totalesCot['total'];
            $anticipo_req  = ($cp['condicion_pago'] === 'pago_total')
                ? $totalCot
                : round($totalCot * 0.5, 2);
            $pagado        = (float)$cp['saldo_pagado'];

            if ($pagado + 0.99 < $anticipo_req) {
                $pctTxt = ($cp['condicion_pago'] === 'pago_total') ? '100%' : '50%';
                // C-12: dir_admin/desarrollo pasan directo sin anticipo — no necesitan mandar
                // nada extra — pero queda registrado en bitácora automáticamente (nota_vobo
                // opcional, si la mandan se usa; si no, se genera una nota por default).
                if (in_array($rol, ['dir_admin', 'desarrollo'])) {
                    $notaOv = trim($body['nota_vobo'] ?? '') ?: 'VoBo forzado por ' . $rol . ' sin anticipo completo';
                    $vobo_override = true;
                } else {
                    jsonResponse(['error' => 'Anticipo insuficiente para VoBo: la condición es ' . $pctTxt
                        . ' ($' . number_format($anticipo_req, 2) . ' de $' . number_format($totalCot, 2)
                        . ') y solo hay $' . number_format($pagado, 2)
                        . ' cobrados. Registra el pago o pide a dir_admin el override.']); exit;
                }
            }
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

                // C-12: override de anticipo — bitácora con quién y por qué
                if ($vobo_override) {
                    $db->prepare("INSERT INTO correcciones_log
                        (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, motivo, usuario)
                        VALUES ('cotizacion', ?, ?, 'vobo_sin_anticipo', ?, 'OVERRIDE', ?, ?)")
                       ->execute([
                           $orden['cot_id'], $cp['folio'],
                           'Cobrado $' . number_format($pagado, 2) . ' de anticipo requerido $' . number_format($anticipo_req, 2),
                           $notaOv, $usuario_nombre
                       ]);
                }
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

            jsonResponse([
                'ok'            => true,
                'fecha_entrega' => $fecha_entrega,
                'vobo_por'      => $usuario_nombre,
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()]);
        }
        exit;
    }

    jsonResponse(['error' => 'Acción no reconocida']); exit;
}

jsonResponse(['error' => 'Método no soportado']);