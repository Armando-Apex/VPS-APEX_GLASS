<?php
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user           = requireSessionApi();
$rol            = $user['rol'];
$usuario_id     = $user['id'];
$usuario_nombre = $user['nombre'];
$method         = $_SERVER['REQUEST_METHOD'];
$puede_editar   = in_array($rol, ['dir_admin', 'dueno', 'comercial', 'desarrollo']);
$es_admin       = in_array($rol, ['dir_admin', 'dueno', 'desarrollo']);
	
$pdo = getDB();

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $q       = isset($_GET['q'])  ? trim($_GET['q'])  : '';
    $activos = ($_GET['activos'] ?? '1') === '1';

    $ver_pass = in_array($rol, ['dir_admin', 'dueno', 'comercial', 'desarrollo']);

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
        $stmt->execute([$id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cliente) { jsonResponse(['error' => 'No encontrado']); exit; }
        if (!$ver_pass) {
            unset($cliente['portal_password'], $cliente['portal_password_hash']);
        } else {
            unset($cliente['portal_password_hash']);
        }
        $stmt2 = $pdo->prepare("SELECT * FROM clientes_bitacora WHERE cliente_id = ? ORDER BY fecha DESC LIMIT 100");
        $stmt2->execute([$id]);
        $cliente['bitacora'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        jsonResponse($cliente); exit;
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
        SELECT id, codigo, razon_social, nombre, contacto, telefono, telefono_alterno, email,
               rfc, cp_fiscal, regimen_fiscal,
               localidad, ciudad, activo, created_at
        $pass_col, portal_activo
        FROM clientes c $where ORDER BY codigo ASC LIMIT 350
    ");
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

if (!$puede_editar) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

// ─── POST accion=editar_contacto ─────────────────────────────────────────────
if ($method === 'POST' && ($_GET['accion'] ?? '') === 'editar_contacto') {
    if (!in_array($rol, ['dir_admin', 'administracion', 'desarrollo'])) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    $id             = (int)($_POST['id'] ?? 0);
    $nuevoContacto  = strtoupper(trim($_POST['contacto'] ?? ''));

    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT contacto FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) { jsonResponse(['error' => 'Cliente no encontrado']); exit; }

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE clientes SET contacto = ? WHERE id = ?")
        ->execute([$nuevoContacto, $id]);
    $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?, 'Contacto', ?, ?, ?, ?)")
        ->execute([$id, $actual['contacto'] ?? '', $nuevoContacto, $usuario_id, $usuario_nombre]);
    $pdo->commit();

    jsonResponse(['ok' => true, 'contacto' => $nuevoContacto]); exit;
}

// ─── POST accion=editar_nombre ────────────────────────────────────────────────
if ($method === 'POST' && ($_GET['accion'] ?? '') === 'editar_nombre') {
    if (!in_array($rol, ['dir_admin', 'administracion', 'desarrollo'])) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    $id          = (int)($_POST['id'] ?? 0);
    $nuevoNombre = strtoupper(trim($_POST['nombre'] ?? ''));

    if (!$id)          { jsonResponse(['error' => 'ID requerido']); exit; }
    if (!$nuevoNombre) { jsonResponse(['error' => 'Nombre requerido']); exit; }

    $stmt = $pdo->prepare("SELECT nombre, razon_social FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) { jsonResponse(['error' => 'Cliente no encontrado']); exit; }

    $nombreAnterior = $actual['razon_social'] ?: $actual['nombre'];

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE clientes SET nombre = ?, razon_social = ? WHERE id = ?")
        ->execute([$nuevoNombre, $nuevoNombre, $id]);

    $stmtOrd1 = $pdo->prepare("UPDATE ordenes SET cliente_nombre = ? WHERE cliente_id = ?");
    $stmtOrd1->execute([$nuevoNombre, $id]);
    $filas1 = $stmtOrd1->rowCount();

    $stmtOrd2 = $pdo->prepare("UPDATE ordenes SET cliente_nombre = ? WHERE cliente_nombre = ? AND (cliente_id IS NULL OR cliente_id = 0)");
    $stmtOrd2->execute([$nuevoNombre, $nombreAnterior]);
    $filas2 = $stmtOrd2->rowCount();

    $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?, 'Nombre', ?, ?, ?, ?)")
        ->execute([$id, $nombreAnterior, $nuevoNombre, $usuario_id, $usuario_nombre]);
    $pdo->commit();

    jsonResponse([
        'ok'                  => true,
        'nombre'              => $nuevoNombre,
        'ordenes_actualizadas'=> $filas1 + $filas2,
    ]); exit;
}

// ─── POST accion=editar_telefono ─────────────────────────────────────────────
if ($method === 'POST' && ($_GET['accion'] ?? '') === 'editar_telefono') {
    if (!in_array($rol, ['dir_admin', 'administracion', 'comercial', 'desarrollo'])) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    $id      = (int)($_POST['id']    ?? 0);
    $campo   = $_POST['campo']        ?? '';
    $valor   = trim($_POST['valor']   ?? '');

    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
    if (!in_array($campo, ['telefono', 'telefono_alterno'])) {
        jsonResponse(['error' => 'Campo inválido']); exit;
    }

    $stmt = $pdo->prepare("SELECT telefono, telefono_alterno FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) { jsonResponse(['error' => 'Cliente no encontrado']); exit; }

    $valorAnterior = $actual[$campo] ?? '';
    $nuevoValor    = $valor ?: null;

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE clientes SET $campo = ? WHERE id = ?")
        ->execute([$nuevoValor, $id]);
    $etiqueta = $campo === 'telefono' ? 'Teléfono' : 'Teléfono Alterno WA';
    $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$id, $etiqueta, $valorAnterior, $nuevoValor ?? '', $usuario_id, $usuario_nombre]);
    $pdo->commit();

    jsonResponse(['ok' => true, 'campo' => $campo, 'valor' => $nuevoValor ?? '']); exit;
}

// ─── POST accion=guardar_fiscal ───────────────────────────────────────────────
// Guarda RFC, CP fiscal y régimen desde la Constancia de Situación Fiscal (CSF)
if ($method === 'POST' && ($_GET['accion'] ?? '') === 'guardar_fiscal') {
    $id             = (int)($body['id'] ?? 0);
    $rfc            = strtoupper(trim($body['rfc']            ?? '')) ?: null;
    $cp_fiscal      = trim($body['cp_fiscal']      ?? '') ?: null;
    $regimen_fiscal = trim($body['regimen_fiscal']  ?? '') ?: null;

    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT rfc, cp_fiscal, regimen_fiscal FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) { jsonResponse(['ok'=>false,'error'=>'Cliente no encontrado']); exit; }

    $pdo->beginTransaction();
    $pdo->prepare("UPDATE clientes SET rfc=?, cp_fiscal=?, regimen_fiscal=?, updated_at=NOW() WHERE id=?")
        ->execute([$rfc, $cp_fiscal, $regimen_fiscal, $id]);
    $stmt_log = $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?,?,?,?,?,?)");
    $cambios = [
        'RFC'            => ['rfc',            $actual['rfc'],            $rfc],
        'CP Fiscal'      => ['cp_fiscal',      $actual['cp_fiscal'],      $cp_fiscal],
        'Régimen Fiscal' => ['regimen_fiscal',  $actual['regimen_fiscal'], $regimen_fiscal],
    ];
    foreach ($cambios as $etiqueta => $vals) {
        if ((string)($vals[1] ?? '') !== (string)($vals[2] ?? '')) {
            $stmt_log->execute([$id, $etiqueta, $vals[1] ?? '', $vals[2] ?? '', $usuario_id, $usuario_nombre]);
        }
    }
    $pdo->commit();

    jsonResponse(['ok'=>true, 'rfc'=>$rfc, 'cp_fiscal'=>$cp_fiscal, 'regimen_fiscal'=>$regimen_fiscal]);
    exit;
}

// ─── POST ──────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $razon_social     = strtoupper(trim($body['razon_social']     ?? ''));
    $contacto         = strtoupper(trim($body['contacto']          ?? ''));
    $telefono         = trim($body['telefono']          ?? '');
    $telefono_alterno = trim($body['telefono_alterno']  ?? '');
    $email            = trim($body['email']             ?? '');
    $localidad        = ($body['localidad'] ?? '') === 'foraneo' ? 'foraneo' : 'local';
    $ciudad           = trim($body['ciudad']            ?? '');

    if (!$razon_social) { jsonResponse(['error' => 'La razón social es obligatoria']); exit; }
    if ($localidad === 'foraneo' && !$ciudad) { jsonResponse(['error' => 'La ciudad es obligatoria para foráneos']); exit; }

    $stmt  = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)) as max_num FROM clientes WHERE codigo LIKE 'CTN-%'");
    $row   = $stmt->fetch(PDO::FETCH_ASSOC);
    $next  = ($row['max_num'] ?? 146) + 1;
    $codigo = 'CTN-' . $next;

    $rfc            = strtoupper(trim($body['rfc']            ?? '')) ?: null;
    $cp_fiscal      = trim($body['cp_fiscal']      ?? '') ?: null;
    $regimen_fiscal = trim($body['regimen_fiscal']  ?? '') ?: null;

    $pdo->beginTransaction();
    $pdo->prepare("INSERT INTO clientes (codigo, razon_social, nombre, contacto, telefono, telefono_alterno, email, localidad, ciudad, rfc, cp_fiscal, regimen_fiscal) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$codigo, $razon_social, $razon_social, $contacto, $telefono, $telefono_alterno ?: null, $email, $localidad, $ciudad, $rfc, $cp_fiscal, $regimen_fiscal]);
    $new_id = $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?, 'CREACION', '', ?, ?, ?)")
        ->execute([$new_id, "Cliente creado: $razon_social", $usuario_id, $usuario_nombre]);
    $pdo->commit();

    jsonResponse(['ok' => true, 'id' => $new_id, 'codigo' => $codigo]); exit;
}

// ─── PUT ──────────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) { jsonResponse(['error' => 'No encontrado']); exit; }

    $campos = [
        'razon_social'     => strtoupper(trim($body['razon_social']    ?? $actual['razon_social'])),
        'contacto'         => strtoupper(trim($body['contacto']        ?? $actual['contacto'])),
        'telefono'         => trim($body['telefono']        ?? $actual['telefono']),
        'telefono_alterno' => trim($body['telefono_alterno'] ?? $actual['telefono_alterno'] ?? '') ?: null,
        'email'            => trim($body['email']           ?? $actual['email']),
        'localidad'        => in_array($body['localidad'] ?? '', ['local','foraneo']) ? $body['localidad'] : $actual['localidad'],
        'ciudad'           => trim($body['ciudad']          ?? $actual['ciudad']),
        'rfc'              => strtoupper(trim($body['rfc']            ?? $actual['rfc'] ?? '')) ?: null,
        'cp_fiscal'        => trim($body['cp_fiscal']      ?? $actual['cp_fiscal'] ?? '') ?: null,
        'regimen_fiscal'   => trim($body['regimen_fiscal']  ?? $actual['regimen_fiscal'] ?? '') ?: null,
    ];
    if (isset($body['activo']) && $es_admin) $campos['activo'] = (int)$body['activo'];

    $etiquetas = ['razon_social'=>'Razón Social','contacto'=>'Contacto','telefono'=>'Teléfono','telefono_alterno'=>'Teléfono Alterno WA','email'=>'Email','localidad'=>'Localidad','ciudad'=>'Ciudad','activo'=>'Estatus','rfc'=>'RFC','cp_fiscal'=>'CP Fiscal','regimen_fiscal'=>'Régimen Fiscal'];
    $stmt_log  = $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?,?,?,?,?,?)");

    $pdo->beginTransaction();
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
    $pdo->commit();

    jsonResponse(['ok' => true]); exit;
}

jsonResponse(['error' => 'Método no soportado']);