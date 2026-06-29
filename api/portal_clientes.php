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
    if (!in_array($user['rol'], ['dir_admin', 'dueno', 'comercial', 'desarrollo'])) {
        http_response_code(403); echo json_encode(['error' => 'Sin permiso']); exit;
    }

    $id = intval($_POST['id'] ?? 0);
    if (!$id) { echo json_encode(['error' => 'ID inválido']); exit; }

    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id, codigo, portal_password_hash FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cliente) { echo json_encode(['error' => 'Cliente no encontrado']); exit; }

    // comercial solo puede generar si el cliente aún no tiene acceso
    if (!empty($cliente['portal_password_hash']) && !in_array($user['rol'], ['dir_admin', 'dueno', 'desarrollo'])) {
        http_response_code(403); echo json_encode(['error' => 'Solo dir_admin puede regenerar un acceso existente']); exit;
    }

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

// ── Enviar acceso al portal por WhatsApp ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'enviar_acceso_wa') {
    $user = requirePermiso('ver_dashboard');
    if (!in_array($user['rol'], ['dir_admin', 'dueno', 'comercial', 'desarrollo'])) {
        http_response_code(403); echo json_encode(['error' => 'Sin permiso']); exit;
    }

    $id  = intval($_POST['id'] ?? 0);
    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT id, nombre, razon_social, codigo, telefono, telefono_alterno, portal_password FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) { http_response_code(404); echo json_encode(['error' => 'Cliente no encontrado']); exit; }

    // Determinar teléfono WA (preferir alterno)
    $telRaw = trim($c['telefono_alterno'] ?: $c['telefono'] ?: '');
    if (!$telRaw) { http_response_code(400); echo json_encode(['error' => 'El cliente no tiene teléfono registrado']); exit; }
    $telDig = preg_replace('/[^0-9]/', '', $telRaw);
    $telefono = (strlen($telDig) === 10) ? '52' . $telDig : (strlen($telDig) === 12 ? $telDig : '52' . substr($telDig, -10));

    // Generar contraseña si no tiene
    $pass = $c['portal_password'];
    if (!$pass) {
        $mayus = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $minus = 'abcdefghjkmnpqrstuvwxyz';
        $nums  = '23456789';
        $simbs = '!@#$%&*+-?';
        $todos = $mayus . $minus . $nums . $simbs;
        do {
            $pass  = $mayus[random_int(0, strlen($mayus)-1)];
            $pass .= $minus[random_int(0, strlen($minus)-1)];
            $pass .= $nums[random_int(0,  strlen($nums)-1)];
            $pass .= $simbs[random_int(0, strlen($simbs)-1)];
            for ($i = 0; $i < 4; $i++) $pass .= $todos[random_int(0, strlen($todos)-1)];
            $pass = str_shuffle($pass);
        } while (
            !preg_match('/[A-Z]/', $pass) || !preg_match('/[a-z]/', $pass) ||
            !preg_match('/[0-9]/', $pass) || !preg_match('/[!@#$%&*+\-?]/', $pass)
        );
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE clientes SET portal_password = ?, portal_password_hash = ?, portal_activo = 1 WHERE id = ?")
            ->execute([$pass, $hash, $id]);
    }

    $nombre = substr(strip_tags($c['razon_social'] ?: $c['nombre']), 0, 60);
    $codigo = substr(strip_tags($c['codigo']), 0, 20);

    require_once __DIR__ . '/wa_helper.php';
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $telefono,
        'type'              => 'template',
        'template'          => [
            'name'       => 'acceso_portal',
            'language'   => ['code' => 'es_MX'],
            'components' => [[
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $nombre],
                    ['type' => 'text', 'text' => $codigo],
                    ['type' => 'text', 'text' => $pass],
                ]
            ]]
        ]
    ];
    $res  = enviarMensajeWA($payload);
    $waId = $res['data']['messages'][0]['id'] ?? null;
    if (!$waId) {
        http_response_code(502);
        echo json_encode(['error' => 'Error al enviar WA', 'detalle' => $res['data']]);
        exit;
    }
    echo json_encode(['ok' => true, 'password' => $pass, 'wa_message_id' => $waId]);
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