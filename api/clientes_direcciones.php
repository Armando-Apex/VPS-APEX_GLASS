<?php
// Direcciones guardadas por cliente (Logística Rutas) — permite reutilizar una
// dirección (ej. "Taller") en vez de teclearla cada vez que se asigna/edita una parada.
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user = requireSessionApi();
$rol  = $user['rol'];
$pdo  = getDB();

// Mismo gate que $esLogistica en api/rutas.php — este archivo solo apoya al
// módulo Logística Rutas, sin ampliar acceso a roles que ahí no lo tienen.
if (!in_array($rol, ['administracion', 'dir_admin', 'dueno', 'desarrollo', 'comercial'])) {
    jsonResponse(['ok'=>false,'error'=>'Sin permiso'], 403);
    exit;
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && $accion === 'listar') {
    $clienteId = (int)($_GET['cliente_id'] ?? 0);
    if (!$clienteId) { jsonResponse(['ok'=>false,'error'=>'Falta cliente_id']); exit; }

    $stmt = $pdo->prepare("
        SELECT id, etiqueta, direccion, colonia, ciudad, referencias
        FROM clientes_direcciones WHERE cliente_id = ?
        ORDER BY etiqueta ASC
    ");
    $stmt->execute([$clienteId]);
    jsonResponse(['ok'=>true, 'direcciones'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($method === 'POST' && $accion === 'guardar') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    $clienteId  = (int)($d['cliente_id'] ?? 0);
    $etiqueta   = trim($d['etiqueta'] ?? '');
    $direccion  = trim($d['direccion'] ?? '');
    $colonia    = trim($d['colonia'] ?? '') ?: null;
    $ciudad     = trim($d['ciudad'] ?? '') ?: null;
    $referencias= trim($d['referencias'] ?? '') ?: null;

    if (!$clienteId)  { jsonResponse(['ok'=>false,'error'=>'Falta cliente_id']); exit; }
    if (!$etiqueta)   { jsonResponse(['ok'=>false,'error'=>'Falta el nombre de la dirección']); exit; }
    if (!$direccion)  { jsonResponse(['ok'=>false,'error'=>'Falta la dirección']); exit; }

    $stmt = $pdo->prepare("
        INSERT INTO clientes_direcciones (cliente_id, etiqueta, direccion, colonia, ciudad, referencias)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$clienteId, $etiqueta, $direccion, $colonia, $ciudad, $referencias]);
    jsonResponse(['ok'=>true, 'id'=>$pdo->lastInsertId()]);
    exit;
}

jsonResponse(['ok'=>false,'error'=>'Acción no válida'], 400);
