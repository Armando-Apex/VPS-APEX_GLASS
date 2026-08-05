<?php
// ============================================================
//  APEX GLASS - API: Bitácora de Desechos
//  Registro de trazabilidad de recolección de merma física
//  (vidrio, madera, artículos de oficina) por proveedores de
//  reciclaje. Módulo puramente operativo/administrativo — NO
//  genera pólizas ni toca Contabilidad/Estado de Resultados.
//  Tablas propias y aisladas: bitacora_desechos,
//  bitacora_desechos_proveedores, bitacora_desechos_archivos.
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');

$user           = requireSessionApi();
$rol            = $user['rol'];
$usuario_id     = $user['id'];
$usuario_nombre = $user['nombre'];
$method         = $_SERVER['REQUEST_METHOD'];

if (!tienePermiso($rol, 'ver_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$pdo = getDB();

// El body se lee UNA sola vez aquí y se reutiliza en todas las acciones — php://input
// es un stream que no siempre se puede releer, y además esto es lo que arregla el bug
// real: las acciones que mandan `accion` dentro del JSON del POST (crear,
// crear_proveedor, eliminar, eliminar_archivo) nunca coincidían con ningún bloque
// porque $_POST solo se llena con bodies application/x-www-form-urlencoded o
// multipart — un fetch con Content-Type: application/json deja $_POST vacío, así que
// $accion quedaba '' y siempre caía en "Acción no válida" (reportado 05-ago-2026).
$bodyInput = ($method === 'POST' && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false)
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : [];
$accion = $_GET['accion'] ?? ($_POST['accion'] ?? ($bodyInput['accion'] ?? ''));

$CATEGORIAS_VALIDAS = ['vidrio', 'madera', 'articulos_oficina', 'otro'];

// ── LISTAR RECOLECCIONES ────────────────────────────────────────────────────
if ($method === 'GET' && $accion === 'listar') {
    $desde = trim($_GET['desde'] ?? date('Y-m-01'));
    $hasta = trim($_GET['hasta'] ?? date('Y-m-d'));
    $cat   = trim($_GET['categoria'] ?? '');

    $where  = 'd.fecha_recoleccion BETWEEN ? AND ?';
    $params = [$desde, $hasta];
    if ($cat && in_array($cat, $CATEGORIAS_VALIDAS)) {
        $where   .= ' AND d.categoria = ?';
        $params[] = $cat;
    }

    $stmt = $pdo->prepare("
        SELECT d.*, p.empresa AS proveedor_empresa
        FROM bitacora_desechos d
        JOIN bitacora_desechos_proveedores p ON p.id = d.proveedor_id
        WHERE $where
        ORDER BY d.fecha_recoleccion DESC, d.id DESC
        LIMIT 500
    ");
    $stmt->execute($params);
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($registros) {
        $ids = array_column($registros, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmtA = $pdo->prepare("
            SELECT id, desecho_id, nombre_original, nombre_servidor, created_at
            FROM bitacora_desechos_archivos
            WHERE desecho_id IN ($ph)
            ORDER BY created_at ASC
        ");
        $stmtA->execute($ids);
        $archivosPorDesecho = [];
        foreach ($stmtA->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $archivosPorDesecho[$a['desecho_id']][] = $a;
        }
        foreach ($registros as &$r) {
            $r['archivos'] = $archivosPorDesecho[$r['id']] ?? [];
        }
        unset($r);
    }

    jsonResponse(['ok' => true, 'registros' => $registros]); exit;
}

// ── OBTENER UN REGISTRO (con sus archivos) ──────────────────────────────────
if ($method === 'GET' && $accion === 'obtener') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $stmt = $pdo->prepare("
        SELECT d.*, p.empresa AS proveedor_empresa
        FROM bitacora_desechos d
        JOIN bitacora_desechos_proveedores p ON p.id = d.proveedor_id
        WHERE d.id = ?
    ");
    $stmt->execute([$id]);
    $registro = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$registro) { jsonResponse(['error' => 'No encontrado'], 404); exit; }

    $stmtA = $pdo->prepare("
        SELECT id, nombre_original, nombre_servidor, created_at
        FROM bitacora_desechos_archivos
        WHERE desecho_id = ?
        ORDER BY created_at ASC
    ");
    $stmtA->execute([$id]);
    $registro['archivos'] = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse(['ok' => true, 'registro' => $registro]); exit;
}

// ── CREAR RECOLECCIÓN ───────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'crear') {
    if (!tienePermiso($rol, 'gestionar_contabilidad')) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }
    $body = $bodyInput;

    $fecha        = trim($body['fecha_recoleccion'] ?? '');
    $categoria    = trim($body['categoria'] ?? '');
    $descripcion  = trim($body['descripcion'] ?? '');
    $proveedor_id = (int)($body['proveedor_id'] ?? 0);
    $monto        = $body['monto'] ?? null;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { jsonResponse(['error' => 'Fecha inválida']); exit; }
    if (!in_array($categoria, $CATEGORIAS_VALIDAS))   { jsonResponse(['error' => 'Categoría inválida']); exit; }
    if (!$descripcion)   { jsonResponse(['error' => 'Descripción requerida']); exit; }
    if (!$proveedor_id)  { jsonResponse(['error' => 'Proveedor requerido']); exit; }

    $stmtP = $pdo->prepare("SELECT id FROM bitacora_desechos_proveedores WHERE id = ? AND activo = 1");
    $stmtP->execute([$proveedor_id]);
    if (!$stmtP->fetch()) { jsonResponse(['error' => 'Proveedor no encontrado']); exit; }

    $monto = ($monto === null || $monto === '') ? null : round((float)$monto, 2);

    $pdo->prepare("
        INSERT INTO bitacora_desechos
            (fecha_recoleccion, categoria, descripcion, proveedor_id, monto, registrado_por_id, registrado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([$fecha, $categoria, $descripcion, $proveedor_id, $monto, $usuario_id, $usuario_nombre]);

    jsonResponse(['ok' => true, 'id' => (int)$pdo->lastInsertId()]); exit;
}

// ── LISTAR PROVEEDORES ──────────────────────────────────────────────────────
if ($method === 'GET' && $accion === 'listar_proveedores') {
    $stmt = $pdo->query("
        SELECT id, empresa, nombre_contacto, telefono_contacto, telefono_empresa, correo
        FROM bitacora_desechos_proveedores
        WHERE activo = 1
        ORDER BY empresa ASC
    ");
    jsonResponse(['ok' => true, 'proveedores' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit;
}

// ── CREAR PROVEEDOR ─────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'crear_proveedor') {
    if (!tienePermiso($rol, 'gestionar_contabilidad')) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }
    $body = $bodyInput;

    $empresa           = trim($body['empresa'] ?? '');
    $nombre_contacto   = trim($body['nombre_contacto'] ?? '');
    $telefono_contacto = trim($body['telefono_contacto'] ?? '');
    $telefono_empresa  = trim($body['telefono_empresa'] ?? '');
    $correo            = trim($body['correo'] ?? '');

    if (!$empresa)           { jsonResponse(['error' => 'Empresa requerida']); exit; }
    if (!$nombre_contacto)   { jsonResponse(['error' => 'Nombre de contacto requerido']); exit; }
    if (!$telefono_contacto) { jsonResponse(['error' => 'Número de contacto requerido']); exit; }
    if ($correo && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Correo inválido']); exit;
    }

    $pdo->prepare("
        INSERT INTO bitacora_desechos_proveedores
            (empresa, nombre_contacto, telefono_contacto, telefono_empresa, correo)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$empresa, $nombre_contacto, $telefono_contacto, $telefono_empresa ?: null, $correo ?: null]);

    jsonResponse(['ok' => true, 'id' => (int)$pdo->lastInsertId()]); exit;
}

// ── SUBIR ARCHIVO ────────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'subir_archivo') {
    if (!tienePermiso($rol, 'gestionar_contabilidad')) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    $desecho_id = (int)($_POST['desecho_id'] ?? 0);
    if (!$desecho_id) { jsonResponse(['error' => 'Registro requerido']); exit; }

    $stmtD = $pdo->prepare("SELECT id FROM bitacora_desechos WHERE id = ?");
    $stmtD->execute([$desecho_id]);
    if (!$stmtD->fetch()) { jsonResponse(['error' => 'Registro no encontrado']); exit; }

    if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No se recibió archivo o hubo un error en la subida']); exit;
    }

    $archivo      = $_FILES['archivo'];
    $ext          = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $exts_validas = ['jpg', 'jpeg', 'png', 'pdf'];
    if (!in_array($ext, $exts_validas)) {
        jsonResponse(['error' => 'Formato no permitido. Solo jpg, png, pdf']); exit;
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($archivo['tmp_name']);
    $mimes_ok = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!in_array($mime, $mimes_ok)) {
        jsonResponse(['error' => 'Tipo de archivo no válido']); exit;
    }

    if ($archivo['size'] > 10 * 1024 * 1024) {
        jsonResponse(['error' => 'El archivo supera el límite de 10 MB']); exit;
    }

    $fecha           = date('Y-m-d_H-i');
    $nombre_servidor = 'DESECHO' . $desecho_id . '_' . $fecha . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    $dir_archivos = __DIR__ . '/../bitacora_desechos/';
    if (!is_dir($dir_archivos)) {
        mkdir($dir_archivos, 0750, true);
        file_put_contents($dir_archivos . '.htaccess', "Order deny,allow\nDeny from all\n");
    }

    $ruta_destino = $dir_archivos . $nombre_servidor;
    if (!move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        jsonResponse(['error' => 'Error al guardar el archivo en el servidor']); exit;
    }

    $pdo->prepare("
        INSERT INTO bitacora_desechos_archivos (desecho_id, nombre_original, nombre_servidor, subido_por_id, subido_por)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([$desecho_id, $archivo['name'], $nombre_servidor, $usuario_id, $usuario_nombre]);

    jsonResponse([
        'ok'              => true,
        'id'              => (int)$pdo->lastInsertId(),
        'nombre_servidor' => $nombre_servidor,
        'nombre_original' => $archivo['name'],
    ]); exit;
}

// ── DESCARGAR / VER ───────────────────────────────────────────────────────────
if ($method === 'GET' && $accion === 'descargar') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido'], 400); }

    $stmt = $pdo->prepare("SELECT * FROM bitacora_desechos_archivos WHERE id = ?");
    $stmt->execute([$id]);
    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$archivo) { jsonResponse(['error' => 'Archivo no encontrado'], 404); }

    $ruta = __DIR__ . '/../bitacora_desechos/' . basename($archivo['nombre_servidor']);
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

// ── BORRAR ARCHIVO ───────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'eliminar_archivo') {
    if (!in_array($rol, ['dir_admin', 'desarrollo'])) {
        jsonResponse(['error' => 'Solo dir_admin puede borrar archivos'], 403);
    }
    $body = $bodyInput;
    $id   = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT nombre_servidor FROM bitacora_desechos_archivos WHERE id = ?");
    $stmt->execute([$id]);
    $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$archivo) { jsonResponse(['error' => 'No encontrado']); exit; }

    $ruta = __DIR__ . '/../bitacora_desechos/' . basename($archivo['nombre_servidor']);
    if (file_exists($ruta)) unlink($ruta);

    $pdo->prepare("DELETE FROM bitacora_desechos_archivos WHERE id = ?")->execute([$id]);

    jsonResponse(['ok' => true]); exit;
}

// ── BORRAR REGISTRO ──────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'eliminar') {
    if (!in_array($rol, ['dir_admin', 'desarrollo'])) {
        jsonResponse(['error' => 'Solo dir_admin puede borrar registros'], 403);
    }
    $body = $bodyInput;
    $id   = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $pdo->beginTransaction();
    try {
        $stmtA = $pdo->prepare("SELECT nombre_servidor FROM bitacora_desechos_archivos WHERE desecho_id = ?");
        $stmtA->execute([$id]);
        $archivosDelRegistro = $stmtA->fetchAll(PDO::FETCH_COLUMN);

        $pdo->prepare("DELETE FROM bitacora_desechos_archivos WHERE desecho_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM bitacora_desechos WHERE id = ?")->execute([$id]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Error al eliminar: ' . $e->getMessage()]); exit;
    }

    // Borrar archivos físicos fuera de la transacción (no participan de SQL)
    foreach ($archivosDelRegistro as $nombreServidor) {
        $ruta = __DIR__ . '/../bitacora_desechos/' . basename($nombreServidor);
        if (file_exists($ruta)) unlink($ruta);
    }

    jsonResponse(['ok' => true]); exit;
}

jsonResponse(['error' => 'Acción no válida']);
