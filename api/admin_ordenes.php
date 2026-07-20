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

        $db->beginTransaction();
        try {
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
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => $e->getMessage()], 500);
        }
        jsonResponse(['ok' => true]);
    }

    // Restaurar
    if ($accion === 'restaurar') {
        $stmt = $db->prepare("UPDATE ordenes SET estado='activa' WHERE folio=?");
        $stmt->execute([$folio]);
        jsonResponse(['ok' => true]);
    }

    // Corrección masiva de estatus
    if ($accion === 'corregir_estatus') {
        $estatus   = $body['estatus'] ?? '';
        $fecha     = $body['fecha']   ?? null;

        $estatusValidos = ['pendiente','en_corte','cortado','canteado','trazo','taladro','en_horno','terminado','entregado','reproceso'];
        if (!in_array($estatus, $estatusValidos)) {
            jsonResponse(['error' => 'Estatus no válido'], 400);
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