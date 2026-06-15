<?php
// APEX GLASS - Login v2
// Archivo: api/login.php
// Bloqueo por IP + usuario (no solo IP)
require_once "config.php";
require_once "permisos.php";

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] !== "POST") jsonResponse(["error" => "Metodo no permitido"], 405);

$input = json_decode(file_get_contents("php://input"), true);
if (!$input) jsonResponse(["error" => "JSON invalido"], 400);

$usuario  = trim($input["usuario"]  ?? "");
$password = trim($input["password"] ?? "");

if (!$usuario || !$password) jsonResponse(["error" => "Campos requeridos"], 400);

$db = getDB();
$ip = $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["REMOTE_ADDR"] ?? "unknown";

// Verificar bloqueo por IP + usuario
$stmt = $db->prepare("SELECT intentos, bloqueado, updated_at FROM login_intentos WHERE ip = ? AND usuario = ?");
$stmt->execute([$ip, $usuario]);
$intento = $stmt->fetch();

if ($intento && $intento["bloqueado"]) {
    $minutos = (time() - strtotime($intento["updated_at"])) / 60;
    if ($minutos < 15) {
        $restantes = ceil(15 - $minutos);
        jsonResponse(["error" => "Demasiados intentos. Espera {$restantes} minutos."], 429);
    } else {
        $db->prepare("UPDATE login_intentos SET intentos=0, bloqueado=0 WHERE ip=? AND usuario=?")->execute([$ip, $usuario]);
        $intento = null;
    }
}

// Buscar usuario
$stmt = $db->prepare("SELECT id, nombre, usuario, password, rol, estacion, activo FROM usuarios WHERE usuario = ?");
$stmt->execute([$usuario]);
$user = $stmt->fetch();

if (!$user || !$user["activo"] || !password_verify($password, $user["password"])) {
    if ($intento) {
        $nuevos = $intento["intentos"] + 1;
        $bloq   = $nuevos >= 10 ? 1 : 0;
        $db->prepare("UPDATE login_intentos SET intentos=?, bloqueado=? WHERE ip=? AND usuario=?")->execute([$nuevos, $bloq, $ip, $usuario]);
    } else {
        $db->prepare("INSERT INTO login_intentos (ip, usuario, intentos) VALUES (?, ?, 1)")->execute([$ip, $usuario]);
    }
    jsonResponse(["error" => "Usuario o contrasena incorrectos"], 401);
}

// Login exitoso - limpiar intentos
$db->prepare("DELETE FROM login_intentos WHERE ip=? AND usuario=?")->execute([$ip, $usuario]);

$_SESSION["user_id"]       = $user["id"];
$_SESSION["user_name"]     = $user["nombre"];
$_SESSION["user_rol"]      = $user["rol"];
$_SESSION["user_estacion"] = $user["estacion"];

// Redirecci¨®n especial por estaci¨®n
if ($user["rol"] === 'operador' && $user["estacion"] === 'corte') {
    $redireccion = 'corte_dashboard.php';
} else {
    $redireccion = REDIRECCION_LOGIN[$user["rol"]] ?? "operador.php";
}

$permisos = PERMISOS[$user["rol"]] ?? [];

jsonResponse([
    "ok"          => true,
    "user"        => [
        "id"       => $user["id"],
        "nombre"   => $user["nombre"],
        "rol"      => $user["rol"],
        "estacion" => $user["estacion"],
        "permisos" => $permisos,
    ],
    "redireccion" => $redireccion,
]);
