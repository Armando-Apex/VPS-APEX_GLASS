<?php
// ============================================================
//  APEX GLASS — File Manager de videos de marketing (SOLO dir_admin/desarrollo)
//  Gestiona la carpeta de trabajo fuera del webroot:
//    /home/apexglass2025/herramientas/video-marketing/media/
//  Subida por PARTES (chunked ≤8MB) para esquivar el LimitRequestBody
//  de Apache (10MB) sin tocar config global. Archivos fuera del webroot
//  (no ejecutables por web); descarga siempre por streaming confinado.
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

$user = requireSessionApi();
if (!in_array($user['rol'], ['dir_admin', 'desarrollo'], true)) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$BASE = '/home/apexglass2025/herramientas/video-marketing/media';
$TMP  = $BASE . '/.tmp_uploads';
$MAX_TOTAL = 600 * 1024 * 1024;   // 600 MB por archivo
$EXTS_OK = [
    'mp4','mov','m4v','webm','mkv','avi','mpg','mpeg','wmv','flv','3gp',
    'jpg','jpeg','png','gif','webp','bmp','tif','tiff','svg','heic',
    'mp3','wav','aac','m4a','ogg','flac','wma',
    'srt','vtt','txt','pdf','json','csv','zip',
];

if (!is_dir($BASE)) { @mkdir($BASE, 0750, true); }
if (!is_dir($TMP))  { @mkdir($TMP, 0750, true); }

$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

// ── Confina una ruta relativa dentro de $BASE ────────────────────────────────
// $mustExist=true exige que exista; si es para crear, valida el directorio padre.
function resolverRuta($BASE, $rel, $mustExist = true) {
    $rel = str_replace('\\', '/', (string)$rel);
    if (strpos($rel, "\0") !== false) return null;
    // Rechazar segmentos '..' y rutas absolutas
    foreach (explode('/', $rel) as $seg) {
        if ($seg === '..' ) return null;
    }
    $rel  = ltrim($rel, '/');
    $full = $rel === '' ? $BASE : $BASE . '/' . $rel;

    $baseReal = realpath($BASE);
    if ($baseReal === false) return null;

    if ($mustExist) {
        $real = realpath($full);
        if ($real === false) return null;
        if ($real !== $baseReal && strpos($real, $baseReal . '/') !== 0) return null;
        return $real;
    }
    // Para crear: el padre debe existir y estar dentro de base
    $parent = realpath(dirname($full));
    if ($parent === false) return null;
    if ($parent !== $baseReal && strpos($parent, $baseReal . '/') !== 0) return null;
    return $parent . '/' . basename($full);
}

function limpiarNombre($nombre) {
    $nombre = basename((string)$nombre);
    $nombre = preg_replace('/[^\p{L}\p{N}\.\_\-\s\(\)]/u', '', $nombre);
    $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
    return $nombre === '' ? 'archivo' : $nombre;
}

function nombreUnico($dir, $nombre) {
    $ruta = $dir . '/' . $nombre;
    if (!file_exists($ruta)) return $nombre;
    $ext  = pathinfo($nombre, PATHINFO_EXTENSION);
    $base = pathinfo($nombre, PATHINFO_FILENAME);
    $i = 2;
    do {
        $cand = $base . ' (' . $i . ')' . ($ext ? '.' . $ext : '');
        $i++;
    } while (file_exists($dir . '/' . $cand));
    return $cand;
}

// ── LISTAR ───────────────────────────────────────────────────────────────────
if ($method === 'GET' && $accion === 'listar') {
    $rel = $_GET['path'] ?? '';
    $dir = resolverRuta($BASE, $rel, true);
    if ($dir === null || !is_dir($dir)) jsonResponse(['error' => 'Carpeta no válida'], 400);

    $items = [];
    foreach (scandir($dir) as $n) {
        if ($n === '.' || $n === '..' || $n === '.tmp_uploads') continue;
        if ($n !== '' && $n[0] === '.') continue; // ocultos
        $p   = $dir . '/' . $n;
        $esD = is_dir($p);
        $items[] = [
            'nombre' => $n,
            'es_dir' => $esD,
            'size'   => $esD ? 0 : filesize($p),
            'mtime'  => filemtime($p),
            'ext'    => $esD ? '' : strtolower(pathinfo($n, PATHINFO_EXTENSION)),
        ];
    }
    usort($items, function ($a, $b) {
        if ($a['es_dir'] !== $b['es_dir']) return $a['es_dir'] ? -1 : 1;
        return strcasecmp($a['nombre'], $b['nombre']);
    });
    $relLimpio = trim(str_replace('\\', '/', $rel), '/');
    jsonResponse(['ok' => true, 'path' => $relLimpio, 'items' => $items]);
}

// ── DESCARGAR (streaming confinado) ──────────────────────────────────────────
if ($method === 'GET' && $accion === 'descargar') {
    $rel  = $_GET['path'] ?? '';
    $ruta = resolverRuta($BASE, $rel, true);
    if ($ruta === null || !is_file($ruta)) { http_response_code(404); exit('No encontrado'); }

    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    $mimes = [
        'mp4'=>'video/mp4','mov'=>'video/quicktime','webm'=>'video/webm','m4v'=>'video/x-m4v',
        'mkv'=>'video/x-matroska','avi'=>'video/x-msvideo',
        'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp',
        'mp3'=>'audio/mpeg','wav'=>'audio/wav','m4a'=>'audio/mp4','aac'=>'audio/aac',
        'pdf'=>'application/pdf','zip'=>'application/zip','txt'=>'text/plain','srt'=>'text/plain','json'=>'application/json','csv'=>'text/csv',
    ];
    $ct = $mimes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $ct);
    header('Content-Disposition: attachment; filename="' . basename($ruta) . '"');
    header('Content-Length: ' . filesize($ruta));
    header('X-Content-Type-Options: nosniff');
    $fp = fopen($ruta, 'rb');
    while (!feof($fp)) { echo fread($fp, 1024 * 256); flush(); }
    fclose($fp);
    exit;
}

// ── SUBIR (una parte) ────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'subir') {
    $rel      = $_POST['path']         ?? '';
    $nombre   = $_POST['filename']     ?? '';
    $uploadId = $_POST['upload_id']    ?? '';
    $idx      = (int)($_POST['chunk_index'] ?? -1);
    $total    = (int)($_POST['total_chunks'] ?? 0);

    if (!preg_match('/^[A-Za-z0-9]{8,40}$/', $uploadId)) jsonResponse(['error' => 'upload_id inválido'], 400);
    if ($idx < 0 || $total < 1 || $idx >= $total)        jsonResponse(['error' => 'Índice de parte inválido'], 400);
    if (empty($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) jsonResponse(['error' => 'Parte no recibida'], 400);

    $nombre = limpiarNombre($nombre);
    $ext    = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    if (!in_array($ext, $EXTS_OK, true)) jsonResponse(['error' => 'Formato no permitido: .' . $ext], 400);

    $dirDestino = resolverRuta($BASE, $rel, true);
    if ($dirDestino === null || !is_dir($dirDestino)) jsonResponse(['error' => 'Carpeta destino no válida'], 400);

    // EC-4: limpieza oportunista de partes huérfanas >24h (subida abandonada a
    // medias) — sin depender de un cron nuevo, corre gratis en cada request de subida.
    foreach (glob($TMP . '/*.part*') ?: [] as $huerfano) {
        if (is_file($huerfano) && (time() - filemtime($huerfano)) > 86400) @unlink($huerfano);
    }

    // EC-4: cada chunk se guarda en SU PROPIO archivo (por índice), no concatenado
    // en un único archivo compartido en orden de LLEGADA — con reintentos/red
    // inestable el orden de llegada no está garantizado y el archivo final podía
    // quedar corrupto en silencio (chunk 2 antes que el 1, etc.).
    $partePropia = $TMP . '/' . $uploadId . '.part' . $idx;
    $out = fopen($partePropia, 'wb');
    if (!$out) jsonResponse(['error' => 'No se pudo abrir archivo temporal'], 500);
    $in = fopen($_FILES['chunk']['tmp_name'], 'rb');
    while (!feof($in)) { fwrite($out, fread($in, 1024 * 256)); }
    fclose($in); fclose($out);

    // ¿Ya están las $total partes, sin importar en qué orden llegaron?
    $partes = [];
    $tamanoAcumulado = 0;
    for ($i = 0; $i < $total; $i++) {
        $pf = $TMP . '/' . $uploadId . '.part' . $i;
        if (!is_file($pf)) {
            jsonResponse(['ok' => true, 'finalizado' => false, 'recibido' => $i, 'total' => $total]);
        }
        $partes[] = $pf;
        $tamanoAcumulado += filesize($pf);
    }

    if ($tamanoAcumulado > $MAX_TOTAL) {
        foreach ($partes as $pf) @unlink($pf);
        jsonResponse(['error' => 'El archivo supera el límite de 600 MB'], 400);
    }

    // Todas las partes están — ensamblar en orden numérico 0→N-1 al archivo final.
    $nombreFinal = nombreUnico($dirDestino, $nombre);
    $rutaFinal   = $dirDestino . '/' . $nombreFinal;
    $out = fopen($rutaFinal, 'wb');
    if (!$out) jsonResponse(['error' => 'No se pudo crear el archivo final'], 500);
    foreach ($partes as $pf) {
        $in = fopen($pf, 'rb');
        while (!feof($in)) { fwrite($out, fread($in, 1024 * 256)); }
        fclose($in);
    }
    fclose($out);
    foreach ($partes as $pf) @unlink($pf);

    @chmod($rutaFinal, 0640);
    jsonResponse(['ok' => true, 'finalizado' => true, 'nombre' => $nombreFinal]);
}

// ── CREAR CARPETA ────────────────────────────────────────────────────────────
if ($method === 'POST' && $accion === 'crear_carpeta') {
    $rel    = $_POST['path']   ?? '';
    $nombre = limpiarNombre($_POST['nombre'] ?? '');
    $nombre = preg_replace('/[\.\/]+/', '', $nombre); // sin puntos/barras para carpeta
    if ($nombre === '') jsonResponse(['error' => 'Nombre de carpeta requerido'], 400);

    $padre = resolverRuta($BASE, $rel, true);
    if ($padre === null || !is_dir($padre)) jsonResponse(['error' => 'Ubicación no válida'], 400);
    $nueva = $padre . '/' . $nombre;
    if (file_exists($nueva)) jsonResponse(['error' => 'Ya existe una carpeta con ese nombre'], 400);
    if (!mkdir($nueva, 0750)) jsonResponse(['error' => 'No se pudo crear la carpeta'], 500);
    jsonResponse(['ok' => true, 'nombre' => $nombre]);
}

// ── ELIMINAR (archivo o carpeta vacía) ───────────────────────────────────────
if ($method === 'POST' && $accion === 'eliminar') {
    $rel  = $_POST['path'] ?? '';
    $ruta = resolverRuta($BASE, $rel, true);
    if ($ruta === null || $ruta === realpath($BASE)) jsonResponse(['error' => 'Ruta no válida'], 400);

    if (is_dir($ruta)) {
        $resto = array_diff(scandir($ruta), ['.', '..']);
        if (!empty($resto)) jsonResponse(['error' => 'La carpeta no está vacía. Vacíala primero.'], 400);
        if (!rmdir($ruta)) jsonResponse(['error' => 'No se pudo eliminar la carpeta'], 500);
    } else {
        if (!unlink($ruta)) jsonResponse(['error' => 'No se pudo eliminar el archivo'], 500);
    }
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Acción no válida'], 400);
