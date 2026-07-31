<?php
// ============================================================
//  APEX GLASS - API: Saldo a Favor por Cliente
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
header('Content-Type: application/json; charset=utf-8');

$user   = requireSessionApi();
$rol    = $user['rol'];
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];

$puede_registrar = in_array($rol, ['administracion', 'dir_admin', 'dueno', 'desarrollo']);

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $accion = $_GET['accion'] ?? 'lista';

    // Saldo de un cliente (para banner en cotizacion)
    if ($accion === 'saldo' && isset($_GET['cliente_id'])) {
        $cid  = (int)$_GET['cliente_id'];
        $stmt = $db->prepare("SELECT COALESCE(SUM(monto),0) as saldo FROM clientes_saldo_favor WHERE cliente_id = ?");
        $stmt->execute([$cid]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        jsonResponse(['saldo' => (float)$row['saldo']]);
        exit;
    }

    // Historial de movimientos de un cliente
    if ($accion === 'historial' && isset($_GET['cliente_id'])) {
        $cid  = (int)$_GET['cliente_id'];
        $stmt = $db->prepare("
            SELECT sf.id, sf.tipo, sf.monto, sf.fecha, sf.referencia, sf.notas,
                   sf.cotizacion_id, sf.creado_por, sf.created_at
            FROM clientes_saldo_favor sf
            WHERE sf.cliente_id = ?
            ORDER BY sf.fecha DESC, sf.created_at DESC
        ");
        $stmt->execute([$cid]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Lista de todos los clientes activos con su saldo acumulado
    if ($accion === 'lista') {
        $stmt = $db->query("
            SELECT cl.id, cl.codigo,
                   COALESCE(cl.razon_social, cl.nombre) AS razon_social,
                   cl.contacto, cl.telefono,
                   COALESCE(SUM(sf.monto), 0) as saldo,
                   MAX(sf.fecha) as ultimo_movimiento
            FROM clientes cl
            LEFT JOIN clientes_saldo_favor sf ON sf.cliente_id = cl.id
            WHERE cl.activo = 1
            GROUP BY cl.id, cl.codigo, cl.razon_social, cl.nombre, cl.contacto, cl.telefono
            ORDER BY saldo DESC, COALESCE(cl.razon_social, cl.nombre) ASC
        ");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Apartados de precio de un cliente (para el historial del tab Saldo a Favor)
    if ($accion === 'apartados_cliente' && isset($_GET['cliente_id'])) {
        $cid  = (int)$_GET['cliente_id'];
        $stmt = $db->prepare("
            SELECT a.id, a.saldo_favor_id, a.vigencia_dias, a.vigencia_hasta, a.estatus,
                   a.creado_por, a.created_at,
                   sf.monto, sf.fecha AS fecha_deposito,
                   CASE WHEN a.estatus = 'activo' AND a.vigencia_hasta < CURDATE() THEN 'vencido' ELSE a.estatus END AS estatus_efectivo
            FROM saldo_favor_apartados a
            JOIN clientes_saldo_favor sf ON sf.id = a.saldo_favor_id
            WHERE sf.cliente_id = ?
            ORDER BY a.created_at DESC
        ");
        $stmt->execute([$cid]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Detalle completo de un apartado (para imprimir_apartado.php y el panel de revisión)
    if ($accion === 'apartado' && isset($_GET['id'])) {
        $id   = (int)$_GET['id'];
        $stmt = $db->prepare("
            SELECT a.*, sf.monto, sf.fecha AS fecha_deposito, sf.referencia, sf.cliente_id,
                   cl.codigo AS cliente_codigo, COALESCE(cl.razon_social, cl.nombre) AS cliente_nombre,
                   CASE WHEN a.estatus = 'activo' AND a.vigencia_hasta < CURDATE() THEN 'vencido' ELSE a.estatus END AS estatus_efectivo
            FROM saldo_favor_apartados a
            JOIN clientes_saldo_favor sf ON sf.id = a.saldo_favor_id
            JOIN clientes cl ON cl.id = sf.cliente_id
            WHERE a.id = ?
        ");
        $stmt->execute([$id]);
        $apartado = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$apartado) { jsonResponse(['error' => 'Apartado no encontrado'], 404); }

        $stmtI = $db->prepare("
            SELECT i.cristal_id, i.precio_m2_pactado, i.m2_referencia, c.nombre AS cristal_nombre
            FROM saldo_favor_apartado_items i
            JOIN cristales c ON c.id = i.cristal_id
            WHERE i.apartado_id = ?
            ORDER BY i.id ASC
        ");
        $stmtI->execute([$id]);
        $apartado['items'] = $stmtI->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse($apartado);
        exit;
    }

    // Lista de apartados pendientes de VoBo — solo dir_admin
    if ($accion === 'apartados_pendientes') {
        if ($rol !== 'dir_admin') jsonResponse(['error' => 'Sin permiso'], 403);
        $stmt = $db->query("
            SELECT a.id, a.vigencia_dias, a.vigencia_hasta, a.creado_por, a.created_at,
                   sf.monto, sf.fecha AS fecha_deposito,
                   cl.codigo AS cliente_codigo, COALESCE(cl.razon_social, cl.nombre) AS cliente_nombre
            FROM saldo_favor_apartados a
            JOIN clientes_saldo_favor sf ON sf.id = a.saldo_favor_id
            JOIN clientes cl ON cl.id = sf.cliente_id
            WHERE a.estatus = 'pendiente_vobo'
            ORDER BY a.created_at ASC
        ");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    jsonResponse(['error' => 'Acción no reconocida']); exit;
}

// ─── POST ─────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    if (!$puede_registrar) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = $body['accion'] ?? '';

    if ($accion === 'deposito') {
        $cliente_id = (int)($body['cliente_id'] ?? 0);
        $monto      = (float)($body['monto']      ?? 0);
        $fecha      = trim($body['fecha']          ?? date('Y-m-d'));
        $referencia = trim($body['referencia']     ?? '');
        $notas      = trim($body['notas']          ?? '');

        if (!$cliente_id) { jsonResponse(['error' => 'Cliente requerido']); exit; }
        if ($monto <= 0)  { jsonResponse(['error' => 'El monto debe ser mayor a cero']); exit; }

        $stmt = $db->prepare("SELECT id FROM clientes WHERE id = ? AND activo = 1");
        $stmt->execute([$cliente_id]);
        if (!$stmt->fetch()) { jsonResponse(['error' => 'Cliente no encontrado']); exit; }

        $db->prepare("
            INSERT INTO clientes_saldo_favor (cliente_id, tipo, monto, fecha, referencia, notas, creado_por)
            VALUES (?, 'deposito', ?, ?, ?, ?, ?)
        ")->execute([$cliente_id, $monto, $fecha, $referencia, $notas, $user['nombre']]);

        $stmt2 = $db->prepare("SELECT COALESCE(SUM(monto),0) as saldo FROM clientes_saldo_favor WHERE cliente_id = ?");
        $stmt2->execute([$cliente_id]);
        $row = $stmt2->fetch(PDO::FETCH_ASSOC);

        jsonResponse(['ok' => true, 'saldo' => (float)$row['saldo']]);
        exit;
    }

    // Depósito con Apartado de Precio: mismo depósito de siempre + garantía de precio con vigencia
    if ($accion === 'crear_apartado') {
        $cliente_id    = (int)($body['cliente_id']    ?? 0);
        $monto         = (float)($body['monto']       ?? 0);
        $fecha         = trim($body['fecha']          ?? date('Y-m-d'));
        $referencia    = trim($body['referencia']     ?? '');
        $notas         = trim($body['notas']          ?? '');
        $vigencia_dias = (int)($body['vigencia_dias'] ?? 0);
        $items         = is_array($body['items'] ?? null) ? $body['items'] : [];

        if (!$cliente_id)                              { jsonResponse(['error' => 'Cliente requerido']); exit; }
        if ($monto <= 0)                                { jsonResponse(['error' => 'El monto debe ser mayor a cero']); exit; }
        if ($vigencia_dias < 1 || $vigencia_dias > 45)  { jsonResponse(['error' => 'La vigencia debe ser de 1 a 45 días']); exit; }
        if (!count($items))                             { jsonResponse(['error' => 'Agrega al menos un producto apartado']); exit; }

        $stmt = $db->prepare("SELECT id FROM clientes WHERE id = ? AND activo = 1");
        $stmt->execute([$cliente_id]);
        if (!$stmt->fetch()) { jsonResponse(['error' => 'Cliente no encontrado']); exit; }

        $itemsLimpios = [];
        foreach ($items as $it) {
            $cristal_id = (int)($it['cristal_id'] ?? 0);
            $precio     = (float)($it['precio_m2_pactado'] ?? 0);
            $m2ref      = isset($it['m2_referencia']) && $it['m2_referencia'] !== '' ? (float)$it['m2_referencia'] : null;
            if (!$cristal_id || $precio <= 0) { jsonResponse(['error' => 'Producto o precio pactado inválido']); exit; }
            $itemsLimpios[] = [$cristal_id, $precio, $m2ref];
        }
        $cristalIds = array_column($itemsLimpios, 0);
        $stmtC = $db->prepare("SELECT COUNT(*) FROM cristales WHERE id IN (" . implode(',', array_fill(0, count($cristalIds), '?')) . ")");
        $stmtC->execute($cristalIds);
        if ((int)$stmtC->fetchColumn() !== count(array_unique($cristalIds))) {
            jsonResponse(['error' => 'Uno o más productos no son válidos']); exit;
        }

        $vigencia_hasta = date('Y-m-d', strtotime($fecha . " +{$vigencia_dias} days"));
        $estatus        = $vigencia_dias <= 7 ? 'activo' : 'pendiente_vobo';

        $db->beginTransaction();
        try {
            $db->prepare("
                INSERT INTO clientes_saldo_favor (cliente_id, tipo, monto, fecha, referencia, notas, creado_por)
                VALUES (?, 'deposito', ?, ?, ?, ?, ?)
            ")->execute([$cliente_id, $monto, $fecha, $referencia, $notas, $user['nombre']]);
            $saldo_favor_id = (int)$db->lastInsertId();

            $db->prepare("
                INSERT INTO saldo_favor_apartados (saldo_favor_id, vigencia_dias, vigencia_hasta, estatus, notas, creado_por)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$saldo_favor_id, $vigencia_dias, $vigencia_hasta, $estatus, $notas, $user['nombre']]);
            $apartado_id = (int)$db->lastInsertId();

            $stmtItem = $db->prepare("
                INSERT INTO saldo_favor_apartado_items (apartado_id, cristal_id, precio_m2_pactado, m2_referencia)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($itemsLimpios as $it) {
                $stmtItem->execute([$apartado_id, $it[0], $it[1], $it[2]]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            jsonResponse(['error' => 'No se pudo registrar el apartado']); exit;
        }

        jsonResponse(['ok' => true, 'apartado_id' => $apartado_id, 'saldo_favor_id' => $saldo_favor_id, 'estatus' => $estatus, 'vigencia_hasta' => $vigencia_hasta]);
        exit;
    }

    // VoBo del Director sobre un apartado con vigencia > 7 días
    if ($accion === 'vobo_apartado') {
        if ($rol !== 'dir_admin') jsonResponse(['error' => 'Sin permiso — solo el Director puede dar VoBo'], 403);

        $apartado_id = (int)($body['apartado_id'] ?? 0);
        $nuevo       = $body['estatus'] ?? '';
        $nota        = trim($body['nota'] ?? '');

        if (!$apartado_id || !in_array($nuevo, ['activo', 'rechazado'])) {
            jsonResponse(['error' => 'Parámetros inválidos']); exit;
        }
        if ($nuevo === 'rechazado' && !$nota) {
            jsonResponse(['error' => 'Se requiere nota para rechazar']); exit;
        }

        $stmt = $db->prepare("SELECT estatus FROM saldo_favor_apartados WHERE id = ?");
        $stmt->execute([$apartado_id]);
        $ap = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ap) { jsonResponse(['error' => 'Apartado no encontrado'], 404); }
        if ($ap['estatus'] !== 'pendiente_vobo') { jsonResponse(['error' => 'Este apartado ya fue resuelto'], 409); }

        $db->prepare("
            UPDATE saldo_favor_apartados
            SET estatus = ?, vobo_por = ?, vobo_at = NOW(), nota_resolucion = ?
            WHERE id = ?
        ")->execute([$nuevo, $user['nombre'], $nota, $apartado_id]);

        jsonResponse(['ok' => true, 'estatus' => $nuevo]);
        exit;
    }

    jsonResponse(['error' => 'Acción no reconocida']); exit;
}

jsonResponse(['error' => 'Método no soportado']);
