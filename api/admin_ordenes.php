<?php
// ============================================================
//  APEX GLASS - API: Admin Ordenes
//  GET                    → listar todas las ordenes
//  POST accion=cancelar   → cancelar orden
//  POST accion=restaurar  → restaurar orden
//  POST accion=corregir_estatus → cambiar estatus masivo
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

$user = requirePermiso('cambiar_cualquier_estatus');
if (!in_array($user['rol'], ['dir_admin', 'desarrollo'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: listar órdenes ──────────────────────────────────────
if ($method === 'GET') {
    $q = $_GET['q'] ?? '';
    $where = $q ? "WHERE (o.folio LIKE ? OR o.cliente_nombre LIKE ? OR o.asesor LIKE ?)" : "";
    $params = $q ? ["%$q%", "%$q%", "%$q%"] : [];

    $stmt = $db->prepare("
        SELECT o.folio, o.cliente_nombre, o.asesor,
               o.fecha_entrega, o.estado,
               COUNT(p.id) AS total_piezas
        FROM ordenes o
        LEFT JOIN piezas p ON p.orden_id = o.id
        $where
        GROUP BY o.id
        ORDER BY o.estado ASC, o.fecha_entrega DESC, o.folio DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
    exit;
}

// ── POST: acciones ───────────────────────────────────────────
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $accion = $body['accion'] ?? '';
    $folio  = trim($body['folio'] ?? '');

    if (!$folio) jsonResponse(['error' => 'Folio requerido'], 400);

    // Cancelar
    if ($accion === 'cancelar') {
        // A-8: cascada — la cotización ligada y su dinero no quedan sueltos
        $stmtO = $db->prepare("SELECT id, estado FROM ordenes WHERE folio=?");
        $stmtO->execute([$folio]);
        $orden = $stmtO->fetch(PDO::FETCH_ASSOC);
        if (!$orden) jsonResponse(['error' => 'Orden no encontrada'], 404);
        if ($orden['estado'] === 'entregada') {
            jsonResponse(['error' => 'No se puede cancelar una orden entregada'], 422);
        }
        $ordenId = (int)$orden['id'];

        // A-14 candado 1: ya tiene una entrega registrada → cancelarla
        // contradiría el historial (la mercancía sí llegó al cliente).
        $stmtEnt = $db->prepare("SELECT COUNT(*) FROM ruta_entregas WHERE orden_id=? AND estado='entregado'");
        $stmtEnt->execute([$ordenId]);
        if ((int)$stmtEnt->fetchColumn() > 0) {
            jsonResponse(['error' => "La orden $folio ya tiene una entrega registrada y no puede cancelarse"], 409);
        }

        // A-14 candado 2: tiene parada pendiente en una ruta que YA SALIÓ → el
        // chofer la llevaría sin saber que está cancelada. Primero hay que
        // quitarla de la ruta (accion 'quitar' en api/rutas.php).
        $stmtRuta = $db->prepare("
            SELECT COUNT(*) FROM ruta_entregas re
            JOIN rutas r ON r.id = re.ruta_id
            WHERE re.orden_id=? AND re.estado='pendiente' AND r.estado='en_ruta'
        ");
        $stmtRuta->execute([$ordenId]);
        if ((int)$stmtRuta->fetchColumn() > 0) {
            jsonResponse(['error' => "La orden $folio va en una ruta en curso; quítala de la ruta antes de cancelarla"], 409);
        }

        $db->beginTransaction();
        try {
            // A-14: paradas pendientes en rutas aún en planeación se borran
            // junto con sus piezas asignadas — mismo criterio que 'quitar' y
            // 'eliminar_ruta' en api/rutas.php.
            $stmtPar = $db->prepare("SELECT id FROM ruta_entregas WHERE orden_id=? AND estado='pendiente'");
            $stmtPar->execute([$ordenId]);
            $paradaIds = $stmtPar->fetchAll(PDO::FETCH_COLUMN);
            if ($paradaIds) {
                $in = implode(',', array_fill(0, count($paradaIds), '?'));
                $db->prepare("DELETE FROM ruta_entrega_piezas WHERE ruta_entrega_id IN ($in)")->execute($paradaIds);
                $db->prepare("DELETE FROM ruta_entregas WHERE id IN ($in)")->execute($paradaIds);
            }

            $db->prepare("UPDATE ordenes SET estado='cancelada', updated_at=NOW() WHERE id=? AND estado != 'entregada'")
               ->execute([$orden['id']]);

            // Cotización ligada: cancelar y mover lo cobrado a saldo a favor
            $stmtC = $db->prepare("SELECT id, folio, cliente_id, COALESCE(saldo_pagado,0) AS saldo_pagado
                                   FROM cotizaciones
                                   WHERE orden_id = ? AND estatus NOT IN ('cancelada','rechazada')");
            $stmtC->execute([$orden['id']]);
            foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $cot) {
                $db->prepare("UPDATE cotizaciones SET estatus='cancelada', updated_at=NOW() WHERE id=?")
                   ->execute([$cot['id']]);
                $monto = (float)$cot['saldo_pagado'];
                if ($monto > 0 && $cot['cliente_id']) {
                    $db->prepare("INSERT INTO clientes_saldo_favor (cliente_id, tipo, monto, fecha, referencia, notas, cotizacion_id, creado_por)
                                  VALUES (?, 'deposito', ?, CURDATE(), ?, ?, ?, ?)")
                       ->execute([
                           $cot['cliente_id'], $monto,
                           'Cancelación ' . $folio,
                           'Saldo cobrado movido a favor por cancelación de orden',
                           $cot['id'], $user['nombre']
                       ]);
                    $db->prepare("UPDATE cotizaciones SET saldo_pagado=0, saldo_pendiente=0, estatus_pago='pendiente', updated_at=NOW() WHERE id=?")
                       ->execute([$cot['id']]);
                }
            }

            $db->commit();
            jsonResponse(['ok' => true, 'paradas_liberadas' => count($paradaIds)]);
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // Restaurar
    if ($accion === 'restaurar') {
        // A-14: restaurar solo desde 'cancelada' y al estado correcto — antes
        // ponía 'activa' incondicionalmente, saltándose el VoBo de Finanzas.
        // El VoBo se registra en cotizaciones.vobo_at (api/finanzas.php),
        // no en la orden.
        $stmtO = $db->prepare("
            SELECT o.id, o.estado, c.vobo_at
            FROM ordenes o
            LEFT JOIN cotizaciones c ON c.orden_id = o.id
            WHERE o.folio=?
        ");
        $stmtO->execute([$folio]);
        $orden = $stmtO->fetch(PDO::FETCH_ASSOC);
        if (!$orden) jsonResponse(['error' => 'Orden no encontrada'], 404);
        if ($orden['estado'] !== 'cancelada') {
            jsonResponse(['error' => "Solo se pueden restaurar órdenes canceladas (estado actual: {$orden['estado']})"], 409);
        }
        // Con VoBo dado vuelve a 'activa'; sin VoBo vuelve a 'pendiente_vobo'
        // para que Finanzas la autorice como cualquier orden recién creada.
        $destino = $orden['vobo_at'] ? 'activa' : 'pendiente_vobo';
        $stmt = $db->prepare("UPDATE ordenes SET estado=? WHERE id=?");
        $stmt->execute([$destino, $orden['id']]);
        jsonResponse(['ok' => true, 'estado' => $destino]);
    }

    // Corrección masiva de estatus
    if ($accion === 'corregir_estatus') {
        $estatus   = $body['estatus'] ?? '';
        $fecha     = $body['fecha']   ?? null;

        $estatusValidos = ['pendiente','en_corte','cortado','canteado','trazo','taladro','en_horno','terminado','entregado','reproceso'];
        if (!in_array($estatus, $estatusValidos)) {
            jsonResponse(['error' => 'Estatus no válido'], 400);
        }

        // [UPD-386] La fecha capturada aquí se escribe directo en historial_estatus.created_at
        // (Actividad Reciente del Resumen la ordena por esa columna) — una fecha futura por error
        // de captura (ej. mes equivocado) queda pegada arriba de todo indefinidamente.
        if ($fecha !== null && strtotime($fecha) > time()) {
            jsonResponse(['error' => 'La fecha de corrección no puede ser futura'], 422);
        }

        // Obtener la orden
        $stmtO = $db->prepare("SELECT id FROM ordenes WHERE folio=?");
        $stmtO->execute([$folio]);
        $orden = $stmtO->fetch();
        if (!$orden) jsonResponse(['error' => 'Orden no encontrada'], 404);

        $ordenId = $orden['id'];

        // Obtener piezas
        $stmtP = $db->prepare("SELECT id, estatus FROM piezas WHERE orden_id=?");
        $stmtP->execute([$ordenId]);
        $piezas = $stmtP->fetchAll();

        $fechaCambio = $fecha ?: date('Y-m-d H:i:s');
        $count = 0;

        $db->beginTransaction();
        try {
            foreach ($piezas as $pieza) {
                // Actualizar estatus
                $stmtU = $db->prepare("UPDATE piezas SET estatus=?, updated_at=? WHERE id=?");
                $stmtU->execute([$estatus, $fechaCambio, $pieza['id']]);

                // Registrar en historial
                $stmtH = $db->prepare("
                    INSERT INTO historial_estatus (pieza_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, notas, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtH->execute([
                    $pieza['id'],
                    $pieza['estatus'],
                    $estatus,
                    $user['id'],
                    $user['nombre'],
                    'Corrección masiva de estatus',
                    $fechaCambio
                ]);
                $count++;
            }

            // Si estatus es entregado, marcar orden como entregada
            if ($estatus === 'entregado') {
                $stmtOrden = $db->prepare("UPDATE ordenes SET estado='entregada' WHERE folio=?");
                $stmtOrden->execute([$folio]);
            } elseif ($estatus !== 'pendiente') {
                $stmtOrden = $db->prepare("UPDATE ordenes SET estado='activa' WHERE folio=?");
                $stmtOrden->execute([$folio]);
            }

            $db->commit();
            jsonResponse(['ok' => true, 'piezas_actualizadas' => $count]);

        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    jsonResponse(['error' => 'Acción no válida'], 400);
}

jsonResponse(['error' => 'Método no permitido'], 405);