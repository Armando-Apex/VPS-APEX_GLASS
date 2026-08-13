<?php
// ============================================================
//  APEX GLASS - Permisos por rol
//  Archivo: api/permisos.php
//  Incluir en cualquier PHP que necesite validar acceso
// ============================================================

// Mapa completo de permisos por rol
// Cada permiso es una capacidad especifica del sistema
define('PERMISOS', [
    'operador' => [
        'ver_estacion_propia',
        'escanear_estacion_propia',
    ],
    'chofer' => [
        'registrar_entrega',
    ],
    'comercial' => [
        'ver_dashboard',
        'ver_ordenes',
        'ver_maquila',
        'ver_estaciones',
        'imprimir_etiquetas',
        'registrar_entrega',
        'ver_inventario',
    ],
    'jefe_piso' => [
        'ver_dashboard',
        'ver_ordenes',
        'ver_estaciones',
        'imprimir_etiquetas',
        'cambiar_cualquier_estatus',
    ],
    'director' => [
        'ver_dashboard',
        'ver_ordenes',
        'ver_maquila',
        'ver_estaciones',
        'imprimir_etiquetas',
        'cambiar_cualquier_estatus',
        'ver_reportes',
    ],
    'dir_admin' => [
        'ver_dashboard',
        'ver_ordenes',
        'ver_maquila',
        'ver_estaciones',
        'imprimir_etiquetas',
        'cambiar_cualquier_estatus',
        'registrar_entrega',
        'ver_reportes',
        'ver_inventario',
        'gestionar_inventario',
        'gestionar_maquila_precios',
        'ver_wip',
        'ver_contabilidad',
        'gestionar_contabilidad',
    ],
    'administracion' => [
        'ver_dashboard',
        'ver_ordenes',
        'ver_maquila',
        'ver_estaciones',
        'imprimir_etiquetas',
        'registrar_entrega',
        'cambiar_cualquier_estatus',
        'ver_reportes',
        'ver_inventario',
        'gestionar_inventario',
        'ver_contabilidad',
        'gestionar_contabilidad',
    ],
    'dueno' => [
        'ver_dashboard',
        'ver_ordenes',
        'ver_maquila',
        'ver_estaciones',
        'imprimir_etiquetas',
        'cambiar_cualquier_estatus',
        'registrar_entrega',
        'ver_reportes',
        'ver_inventario',
        'gestionar_inventario',
        'gestionar_maquila_precios',
        'ver_contabilidad',
        'gestionar_contabilidad',
    ],
    'desarrollo' => [
        'ver_dashboard',
        'ver_ordenes',
        'ver_maquila',
        'ver_estaciones',
        'imprimir_etiquetas',
        'cambiar_cualquier_estatus',
        'registrar_entrega',
        'ver_reportes',
        'ver_inventario',
        'gestionar_inventario',
        'ver_wip',
        'gestionar_maquila_precios',
        'ver_contabilidad',
        'gestionar_contabilidad',
    ],
]);

// Redireccion por defecto al hacer login segun rol
define('REDIRECCION_LOGIN', [
    'operador'       => 'operador.php',
    'chofer'         => 'operador.php',
    'comercial'      => 'dashboard.php',
    'jefe_piso'      => 'jefe_movil.php',
    'director'       => 'dashboard.php',
    'dir_admin'      => 'dashboard.php',
    'administracion' => 'dashboard.php',
    'dueno'          => 'dashboard.php',
    'desarrollo'     => 'dashboard.php',
]);

// Verificar si el rol tiene un permiso especifico
function tienePermiso($rol, $permiso) {
    $mapa = PERMISOS;
    if (!isset($mapa[$rol])) return false;
    return in_array($permiso, $mapa[$rol]);
}

// Verificar sesion activa y retornar datos del usuario
// Si no hay sesion redirige al login
function requireSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
    // S2-11: token CSRF por sesión (synchronizer token) — se emite una sola vez.
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    return [
        'id'      => $_SESSION['user_id'],
        'nombre'  => $_SESSION['user_name'],
        'rol'     => $_SESSION['user_rol'],
        'estacion'=> $_SESSION['user_estacion'] ?? null,
    ];
}

// Verificar sesion Y permiso especifico
// Si no tiene permiso muestra error 403
function requirePermiso($permiso) {
    $user = requireSession();
    if (!tienePermiso($user['rol'], $permiso)) {
        http_response_code(403);
        include __DIR__ . '/../app/403.php';
        exit;
    }
    return $user;
}

// Para APIs: verificar sesion via JSON
function requireSessionApi() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_start();
    }
    // S2-11: token CSRF por sesión (synchronizer token) — se emite una sola vez.
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    // Protección CSRF: validar Origin en peticiones de mutación
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST','PUT','DELETE','PATCH'])) {
        $origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
        $referer = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST);
        $allowed = 'apex.glass';
        if ($origin && parse_url($origin, PHP_URL_HOST) !== $allowed) {
            http_response_code(403);
            echo json_encode(['error' => 'Origen no permitido']);
            exit;
        }
        if (!$origin && $referer && $referer !== $allowed) {
            http_response_code(403);
            echo json_encode(['error' => 'Origen no permitido']);
            exit;
        }
    }
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Sesion requerida']);
        exit;
    }
    // S2-11: synchronizer token — Origin/Referer se conserva como primera
    // barrera, pero ya no es la única (un cliente sin ambos headers pasaba
    // antes). Sin token válido de sesión no hay mutación. Va DESPUÉS de
    // confirmar sesión, para que el error sea claro si lo que falta es login.
    if (in_array($method, ['POST','PUT','DELETE','PATCH'])) {
        $tokenHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $tokenHeader)) {
            http_response_code(403);
            echo json_encode(['error' => 'Token CSRF inválido o ausente']);
            exit;
        }
    }
    $user = [
        'id'      => $_SESSION['user_id'],
        'nombre'  => $_SESSION['user_name'],
        'rol'     => $_SESSION['user_rol'],
        'estacion'=> $_SESSION['user_estacion'] ?? null,
    ];
    // Liberar lock de sesión — permite requests concurrentes del mismo usuario
    session_write_close();
    return $user;
}

// Para APIs: verificar permiso especifico via JSON
function requirePermisoApi($permiso) {
    $user = requireSessionApi();
    if (!tienePermiso($user['rol'], $permiso)) {
        http_response_code(403);
        echo json_encode(['error' => 'Sin permiso para esta accion']);
        exit;
    }
    return $user;
}