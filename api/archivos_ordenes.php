<?php
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user           = requireSessionApi();
$rol            = $user['rol'];
$usuario_id     = $user['id'];
$usuario_nombre = $user['nombre'];
$method         = $_SERVER['REQUEST_METHOD'];

$pdo = getDB();

// Roles con acceso base
$puede_acceder = in_array($rol, ['dir_admin', 'administracion', 'comercial', 'desarrollo']);
if (!$puede_acceder) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$accion = $_GET['accion'] ?? '';

// ── LISTAR ────────────────────────────────────────────────────────────────────
if ($method === 'GET' && $accion === 'listar') {
    $folio   = trim($_GET['folio']   ?? '');
    $cliente = trim($_GET['cliente'] ?? '');

    $where  = '1=1';
    $params = [];

    if ($folio) {
        $where   .= ' AND a.folio LIKE ?';
        $params[] = '%' . $folio . '%';
    }
    if ($cliente) {
        $where   .= ' AND o.cliente_nombre LIKE ?';
        $params[] = '%' . $cliente . '%';
    }

    // Asesor solo ve sus órdenes
    if ($rol === 'comercial') {
        $where   .= ' AND o.asesor = ?';
        $params[] = $usuario_nombre;
    }

    $stmt = $pdo->prepare("
        SELECT a.id, a.orden_id, a.folio, a.nombre_original, a.nombre_servidor,
               a.categoria, a.subido_por, a.created_at,
               o.cliente_nombre
        FROM orden_archivos a
        LEFT JOIN ordenes o ON o.id = a.orden_id
        WHERE $where
        ORDER BY a.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

// ── SUBIR ─────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'subir') {
    $folio     = strtoupper(trim($_POST['folio']     ?? ''));
    $categoria = trim($_POST['categoria'] ?? '');

    if (!$folio)     { jsonResponse(['error' => 'Folio requerido']); exit; }
    if (!$categoria) { jsonResponse(['error' => 'Categoría requerida']); exit; }

    $cats_validas = ['factura', 'comprobante_de_pago', 'croquis'];
    if (!in_array($categoria, $cats_validas)) {
        jsonResponse(['error' => 'Categoría inválida']); exit;
    }

    // Verificar que la orden existe
    $stmt = $pdo->prepare("SELECT id, asesor FROM ordenes WHERE folio = ?");
    $stmt->execute([$folio]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$orden) { jsonResponse(['error' => 'Orden no encontrada']); exit; }

    // Asesor solo puede subir a sus propias órdenes
    if ($rol === 'comercial' && $orden['asesor'] !== $usuario_nombre) {
        jsonResponse(['error' => 'Solo puedes subir archivos a tus propias órdenes'], 403);
    }

    if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No se recibió archivo o hubo un error en la subida']); exit;
    }

    $archivo     = $_FILES['archivo'];
    $ext         = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $exts_validas = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $exts_validas)) {
        jsonResponse(['error' => 'Formato no permitido. Solo jpg, png, pdf']); exit;
    }

    // Validar MIME real
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($archivo['tmp_name']);
    $mimes_ok = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mime, $mimes_ok)) {
        jsonResponse(['error' => 'Tipo de archivo no válido']); exit;
    }

    // Límite 10 MB
    if ($archivo['size'] > 10 * 1024 * 1024) {
        jsonResponse(['error' => 'El archivo supera el límite de 10 MB']); exit;
    }

    // Generar nombre servidor: FOLIO_categoria_YYYY-MM-DD_HH-mm.ext
    $fecha          = date('Y-m-d_H-i');
    $cat_slug       = str_replace(' ', '_', $categoria);
    $nombre_servidor = $folio . '_' . $cat_slug . '_' . $fecha . '.' . $ext;

    $dir_archivos = __DIR__ . '/../../archivos_ordenes/';
    if (!is_dir($dir_archivos)) {
        mkdir($dir_archivos, 0750, true);
        file_put_contents($dir_archivos . '.htaccess', "Order deny,allow\nDeny from all\n");
    }

    $ruta_destino = $dir_archivos . $nombre_servidor;
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        jsonResponse(['error' => 'Error al guardar el archivo en el servidor']); exit;
    }

    $pdo->prepare("
        INSERT INTO orden_archivos (orden_id, folio, nombre_original, nombre_servidor, categoria, subido_por_id, subido_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$orden['id'], $folio, $archivo['name'], $nombre_servidor, $categoria, $usuario_id, $usuario_nombre]);

    jsonResponse([
        'ok'              => true,
        'nombre_servidor' => $nombre_servidor,
        'nombre_original' => $archivo['name'],
    ]); exit;
}

// ── DESCARGAR / VER ───────────────────────────────────────────────────────────
if ($method === 'GET' && $accion === 'descargar') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido'], 400); }

    $stmt = $pdo->prepare("
        SELECT a.*, o.asesor FROM orden_archivos a
        LEFT JOIN ordenes o ON o.id = a.orden_id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$archivo) { jsonResponse(['error' => 'Archivo no encontrado'], 404); }

    // Asesor solo ve sus archivos
    if ($rol === 'comercial' && $archivo['asesor'] !== $usuario_nombre) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    $ruta = __DIR__ . '/../../archivos_ordenes/' . basename($archivo['nombre_servidor']);
    if (!file_exists($ruta)) { jsonResponse(['error' => 'Archivo no existe en servidor'], 404); }

    $ext  = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    $mime = $ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($archivo['nombre_original']) . '"');
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: private, max-age=3600');
    readfile($ruta);
    exit;
}

// ── BORRAR ────────────────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'borrar') {
    if (!in_array($rol, ['dir_admin', 'desarrollo'])) {
        jsonResponse(['error' => 'Solo dir_admin puede borrar archivos'], 403);
    }

    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT nombre_servidor FROM orden_archivos WHERE id = ?");
    $stmt->execute([$id]);
    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$archivo) { jsonResponse(['error' => 'No encontrado']); exit; }

    // Borrar archivo físico
    $ruta = __DIR__ . '/../../archivos_ordenes/' . basename($archivo['nombre_servidor']);
    if (file_exists($ruta)) unlink($ruta);

    $pdo->prepare("DELETE FROM orden_archivos WHERE id = ?")->execute([$id]);

    jsonResponse(['ok' => true]); exit;
}

jsonResponse(['error' => 'Acción no válida']);