<?php
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user           = requireSessionApi();
$rol            = $user['rol'];
$usuario_id     = $user['id'];
$usuario_nombre = $user['nombre'];
$method         = $_SERVER['REQUEST_METHOD'];
$puede_editar   = in_array($rol, ['dir_admin', 'dueno', 'comercial']);
$es_admin       = in_array($rol, ['dir_admin', 'dueno']);
	
$pdo = getDB();

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $q       = isset($_GET['q'])  ? trim($_GET['q'])  : '';
    $activos = ($_GET['activos'] ?? '1') === '1';

    $ver_pass = in_array($rol, ['dir_admin', 'dueno', 'comercial']);

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cliente) { echo json_encode(['error' => 'No encontrado']); exit; }
        if (!$ver_pass) {
            unset($cliente['portal_password'], $cliente['portal_password_hash']);
        } else {
            unset($cliente['portal_password_hash']);
        }
        $stmt2 = $pdo->prepare("SELECT * FROM clientes_bitacora WHERE cliente_id = ? ORDER BY fecha DESC LIMIT 100");
        $stmt2->execute([$id]);
        $cliente['bitacora'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($cliente); exit;
    }

    $where  = $activos ? 'WHERE c.activo = 1' : 'WHERE 1=1';
    $params = [];
    if ($q) {
        $where .= " AND (c.razon_social LIKE ? OR c.nombre LIKE ? OR c.codigo LIKE ? OR c.contacto LIKE ? OR c.telefono LIKE ?)";
        $like   = "%$q%";
        $params = [$like, $like, $like, $like, $like];
    }
    $pass_col = $ver_pass ? ', portal_password' : '';
    $stmt = $pdo->prepare("
        SELECT id, codigo, razon_social, nombre, contacto, telefono, email, localidad, ciudad, activo, created_at
        $pass_col, portal_activo
        FROM clientes c $where ORDER BY codigo ASC LIMIT 200
    ");
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

if (!$puede_editar) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── POST accion=editar_contacto ─────────────────────────────────────────────
if ($method === 'POST' && ($_GET['accion'] ?? '') === 'editar_contacto') {
    if (!in_array($rol, ['dir_admin', 'administracion'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Sin permiso']); exit;
    }

    $id             = (int)($_POST['id'] ?? 0);
    $nuevoContacto  = strtoupper(trim($_POST['contacto'] ?? ''));

    if (!$id) { echo json_encode(['error' => 'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT contacto FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) { echo json_encode(['error' => 'Cliente no encontrado']); exit; }

    $pdo->prepare("UPDATE clientes SET contacto = ? WHERE id = ?")
        ->execute([$nuevoContacto, $id]);

    $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?, 'Contacto', ?, ?, ?, ?)")
        ->execute([$id, $actual['contacto'] ?? '', $nuevoContacto, $usuario_id, $usuario_nombre]);

    echo json_encode(['ok' => true, 'contacto' => $nuevoContacto]); exit;
}

// ─── POST accion=editar_nombre ────────────────────────────────────────────────
if ($method === 'POST' && ($_GET['accion'] ?? '') === 'editar_nombre') {
    if (!in_array($rol, ['dir_admin', 'administracion'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Sin permiso']); exit;
    }

    $id          = (int)($_POST['id'] ?? 0);
    $nuevoNombre = strtoupper(trim($_POST['nombre'] ?? ''));

    if (!$id)          { echo json_encode(['error' => 'ID requerido']); exit; }
    if (!$nuevoNombre) { echo json_encode(['error' => 'Nombre requerido']); exit; }

    $stmt = $pdo->prepare("SELECT nombre, razon_social FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) { echo json_encode(['error' => 'Cliente no encontrado']); exit; }

    $nombreAnterior = $actual['razon_social'] ?: $actual['nombre'];

    // Actualizar clientes
    $pdo->prepare("UPDATE clientes SET nombre = ?, razon_social = ? WHERE id = ?")
        ->execute([$nuevoNombre, $nuevoNombre, $id]);

    // Actualizar ordenes por cliente_id
    $stmtOrd1 = $pdo->prepare("UPDATE ordenes SET cliente_nombre = ? WHERE cliente_id = ?");
    $stmtOrd1->execute([$nuevoNombre, $id]);
    $filas1 = $stmtOrd1->rowCount();

    // Actualizar ordenes por nombre anterior (fallback FK nulo)
    $stmtOrd2 = $pdo->prepare("UPDATE ordenes SET cliente_nombre = ? WHERE cliente_nombre = ? AND (cliente_id IS NULL OR cliente_id = 0)");
    $stmtOrd2->execute([$nuevoNombre, $nombreAnterior]);
    $filas2 = $stmtOrd2->rowCount();

    // Bitácora
    $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?, 'Nombre', ?, ?, ?, ?)")
        ->execute([$id, $nombreAnterior, $nuevoNombre, $usuario_id, $usuario_nombre]);

    echo json_encode([
        'ok'                  => true,
        'nombre'              => $nuevoNombre,
        'ordenes_actualizadas'=> $filas1 + $filas2,
    ]); exit;
}

// ─── POST ──────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $razon_social = strtoupper(trim($body['razon_social'] ?? ''));
    $contacto     = strtoupper(trim($body['contacto']      ?? ''));
    $telefono     = trim($body['telefono']      ?? '');
    $email        = trim($body['email']         ?? '');
    $localidad    = ($body['localidad'] ?? '') === 'foraneo' ? 'foraneo' : 'local';
    $ciudad       = trim($body['ciudad']        ?? '');

    if (!$razon_social) { echo json_encode(['error' => 'La razón social es obligatoria']); exit; }
    if ($localidad === 'foraneo' && !$ciudad) { echo json_encode(['error' => 'La ciudad es obligatoria para foráneos']); exit; }

    $stmt  = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)) as max_num FROM clientes WHERE codigo LIKE 'CTN-%'");
    $row   = $stmt->fetch(PDO::FETCH_ASSOC);
    $next  = ($row['max_num'] ?? 146) + 1;
    $codigo = 'CTN-' . $next;

    $pdo->prepare("INSERT INTO clientes (codigo, razon_social, nombre, contacto, telefono, email, localidad, ciudad) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$codigo, $razon_social, $razon_social, $contacto, $telefono, $email, $localidad, $ciudad]);
    $new_id = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?, 'CREACION', '', ?, ?, ?)")
        ->execute([$new_id, "Cliente creado: $razon_social", $usuario_id, $usuario_nombre]);

    echo json_encode(['ok' => true, 'id' => $new_id, 'codigo' => $codigo]); exit;
}

// ─── PUT ──────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) { echo json_encode(['error' => 'No encontrado']); exit; }

    $campos = [
        'razon_social' => strtoupper(trim($body['razon_social'] ?? $actual['razon_social'])),
        'contacto'     => strtoupper(trim($body['contacto']     ?? $actual['contacto'])),
        'telefono'     => trim($body['telefono']      ?? $actual['telefono']),
        'email'        => trim($body['email']         ?? $actual['email']),
        'localidad'    => in_array($body['localidad'] ?? '', ['local','foraneo']) ? $body['localidad'] : $actual['localidad'],
        'ciudad'       => trim($body['ciudad']        ?? $actual['ciudad']),
    ];
    if (isset($body['activo']) && $es_admin) $campos['activo'] = (int)$body['activo'];

    $etiquetas = ['razon_social'=>'Razón Social','contacto'=>'Contacto','telefono' =>'Teléfono','email'=>'Email','localidad'=>'Localidad','ciudad'=>'Ciudad','activo' =>'Estatus'];
    $stmt_log  = $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?,?,?,?,?,?)");

    foreach ($campos as $campo => $nuevo) {
        if ((string)$nuevo !== (string)($actual[$campo] ?? '')) {
            $stmt_log->execute([$id, $etiquetas[$campo] ?? $campo, $actual[$campo] ?? '', $nuevo, $usuario_id, $usuario_nombre]);
        }
    }

    $campos['nombre'] = $campos['razon_social'];
    $sets   = implode(', ', array_map(function($k) { return "$k = ?"; }, array_keys($campos)));
    $params = array_values($campos);
    $params[] = $id;
    $pdo->prepare("UPDATE clientes SET $sets WHERE id = ?")->execute($params);

    echo json_encode(['ok' => true]); exit;
}

echo json_encode(['error' => 'Método no soportado']);