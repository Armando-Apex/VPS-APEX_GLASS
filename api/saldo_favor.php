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

    jsonResponse(['error' => 'Acción no reconocida']); exit;
}

jsonResponse(['error' => 'Método no soportado']);
