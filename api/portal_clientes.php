<?php
// ============================================================
//  APEX GLASS - Portal Clientes - API
//  Ruta en servidor: /produccion/api/portal_clientes.php
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';

header('Content-Type: application/json; charset=utf-8');

$accion = $_GET['accion'] ?? '';

// ── Generar / regenerar password (solo dir_admin) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'generar_pass') {
    $user = requirePermiso('ver_dashboard');
    if ($user['rol'] !== 'dir_admin') {
        http_response_code(403); echo json_encode(['error' => 'Sin permiso']); exit;
    }

    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'ID inválido']); exit; }

    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, codigo FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cliente) { echo json_encode(['error' => 'Cliente no encontrado']); exit; }

    // ── Generar contraseña segura de 8 caracteres ─────────────────────────────
    // Garantiza al menos: 1 mayúscula, 1 minúscula, 1 número, 1 símbolo
    // Excluye caracteres visualmente confusos (0, O, 1, I, l)
    $mayus = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $minus = 'abcdefghjkmnpqrstuvwxyz';
    $nums  = '23456789';
    $simbs = '!@#$%&*+-?';
    $todos = $mayus . $minus . $nums . $simbs;

    do {
        $pass  = '';
        $pass .= $mayus[random_int(0, strlen($mayus)-1)];
        $pass .= $minus[random_int(0, strlen($minus)-1)];
        $pass .= $nums[random_int(0,  strlen($nums)-1)];
        $pass .= $simbs[random_int(0, strlen($simbs)-1)];
        for ($i = 0; $i < 4; $i++) {
            $pass .= $todos[random_int(0, strlen($todos)-1)];
        }
        $pass = str_shuffle($pass);
    } while (
        !preg_match('/[A-Z]/', $pass) ||
        !preg_match('/[a-z]/', $pass) ||
        !preg_match('/[0-9]/', $pass) ||
        !preg_match('/[!@#$%&*+\-?]/', $pass)
    );

    // ── Guardar texto plano (para que admin lo vea) Y hash bcrypt (para login) ─
    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

    $pdo->prepare(
        "UPDATE clientes SET portal_password = ?, portal_password_hash = ?, portal_activo = 1 WHERE id = ?"
    )->execute([$pass, $hash, $id]);

    echo json_encode(['ok' => true, 'password' => $pass]);
    exit;
}

// ── Login del portal ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'login') {
    $usuario  = strtoupper(trim($_POST['usuario']  ?? ''));
    $password = trim($_POST['password'] ?? '');

    if (!$usuario || !$password) {
        echo json_encode(['error' => 'Usuario y contraseña requeridos']); exit;
    }

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT id, codigo, razon_social, nombre, portal_password_hash, portal_activo
         FROM clientes WHERE codigo = ? LIMIT 1"
    );
    $stmt->execute([$usuario]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);

    // Mismo mensaje para usuario no encontrado y contraseña incorrecta
    // (no revelar si el usuario existe o no)
    if (!$c || !$c['portal_activo'] || !$c['portal_password_hash']) {
        // Hacer verify de todas formas para evitar timing attack
        password_verify($password, '$2y$12$invalidhashpadding000000000000000000000000000000000000u');
        echo json_encode(['error' => 'Usuario o contraseña incorrectos']); exit;
    }

    if (!password_verify($password, $c['portal_password_hash'])) {
        echo json_encode(['error' => 'Usuario o contraseña incorrectos']); exit;
    }

    // Iniciar sesión del portal
    if (session_status() === PHP_SESSION_NONE) session_start();
    session_regenerate_id(true); // Prevenir session fixation
    $_SESSION['portal_cliente_id']     = $c['id'];
    $_SESSION['portal_cliente_codigo'] = $c['codigo'];
    $_SESSION['portal_cliente_nombre'] = $c['razon_social'] ?: $c['nombre'];

    echo json_encode(['ok' => true, 'nombre' => $_SESSION['portal_cliente_nombre']]);
    exit;
}

// ── Logout portal ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'logout') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);