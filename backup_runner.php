<?php
/**
 * APEX GLASS — Backup automático de Base de Datos
 * ─────────────────────────────────────────────────────────────────────────────
 * - Hace dump de la BD cada noche vía cron
 * - Sube el backup comprimido a Google Drive (API REST, sin Composer)
 * - La service account crea su propia carpeta y la comparte con Mando y Armando
 * - Borra backups locales y en Drive con más de 15 días
 * - Log de resultados en _backups/backup.log
 *
 * Ruta servidor: /home3/a3026051/apexglass_tngl/apex.glass/produccion/backup_runner.php
 * Cron:          0 0 * * * /usr/local/bin/php .../backup_runner.php >> /dev/null 2>&1
 * ─────────────────────────────────────────────────────────────────────────────
 */

// ── CONFIGURACIÓN ─────────────────────────────────────────────────────────────

// Leer .env (mismo archivo que usa el resto del sistema)
$_envFile = dirname(__DIR__, 2) . '/apex.glass/.env';
if (is_readable($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        if (str_starts_with(trim($_line), '#') || !str_contains($_line, '=')) continue;
        [$_k, $_v] = explode('=', $_line, 2);
        $_ENV[trim($_k)] = trim($_v);
    }
}
function _env(string $key, string $default = ''): string {
    return $_ENV[$key] ?? $default;
}

// Ruta al JSON de credenciales de la Service Account
define('SA_KEY_FILE', __DIR__ . '/../_secure/apex-glass-sa.json');

// Emails que tendrán acceso de LECTURA a la carpeta de backups en Drive
define('DRIVE_COMPARTIR_CON', [
    'areyna.sanchez@gmail.com',
]);

// Archivo donde se persiste el ID de la carpeta Drive entre ejecuciones
define('DRIVE_FOLDER_ID_FILE', __DIR__ . '/../_backups/drive_folder_id.txt');

// Días a retener backups (local y Drive)
define('DIAS_RETENER', 15);

// Carpeta local donde se guardan los backups
define('BACKUP_DIR', __DIR__ . '/_backups');

// Base de datos — leídos desde .env
define('DB_HOST', _env('DB_HOST', '::1'));
define('DB_NAME', _env('DB_NAME', ''));
define('DB_USER', _env('DB_USER', ''));
define('DB_PASS', _env('DB_PASS', ''));

// Token de seguridad para llamadas HTTP manuales — leído desde .env
define('TOKEN_SECRETO', _env('BACKUP_TOKEN', ''));

// ── SEGURIDAD ─────────────────────────────────────────────────────────────────
if (php_sapi_name() !== 'cli') {
    $token = $_GET['token'] ?? '';
    if ($token !== TOKEN_SECRETO) {
        http_response_code(403);
        exit('Acceso denegado');
    }
}

// ── INICIALIZAR CARPETA LOCAL ─────────────────────────────────────────────────
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0750, true);
}
$htaccess = BACKUP_DIR . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
}

// ── GOOGLE DRIVE: JWT + OAuth 2.0 (sin Composer) ─────────────────────────────

function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function getDriveAccessToken() {
    if (!file_exists(SA_KEY_FILE)) {
        throw new Exception('Credenciales no encontradas: ' . SA_KEY_FILE);
    }
    $sa = json_decode(file_get_contents(SA_KEY_FILE), true);
    if (!$sa || empty($sa['private_key']) || empty($sa['client_email'])) {
        throw new Exception('Credenciales inválidas o incompletas');
    }

    $now     = time();
    $header  = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $payload = base64UrlEncode(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/drive',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));

    $sigInput   = $header . '.' . $payload;
    $privateKey = openssl_pkey_get_private($sa['private_key']);
    if (!$privateKey) throw new Exception('No se pudo cargar private_key');

    openssl_sign($sigInput, $signature, $privateKey, 'SHA256');
    $jwt = $sigInput . '.' . base64UrlEncode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) throw new Exception('cURL token error: ' . $err);
    $data = json_decode($resp, true);
    if (empty($data['access_token'])) throw new Exception('Sin access_token: ' . $resp);

    return $data['access_token'];
}

/**
 * Crea la carpeta APEX_BACKUPS_BD en el Drive de la service account
 * y la comparte con los emails configurados en DRIVE_COMPARTIR_CON
 * Guarda el ID en un archivo local para reutilizarlo en ejecuciones futuras
 */
function obtenerOCrearCarpetaDrive($accessToken) {
    // Si ya existe el ID guardado, usarlo directamente
    if (file_exists(DRIVE_FOLDER_ID_FILE)) {
        $id = trim(file_get_contents(DRIVE_FOLDER_ID_FILE));
        if (!empty($id)) return $id;
    }

    // Crear la carpeta en el Drive de la service account
    $body = json_encode([
        'name'     => 'APEX_BACKUPS_BD',
        'mimeType' => 'application/vnd.google-apps.folder',
    ]);

    $ch = curl_init('https://www.googleapis.com/drive/v3/files');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) throw new Exception('Error creando carpeta Drive: HTTP ' . $code . ' — ' . $resp);

    $data = json_decode($resp, true);
    $folderId = $data['id'] ?? null;
    if (!$folderId) throw new Exception('No se obtuvo ID de carpeta: ' . $resp);

    // Guardar el ID para ejecuciones futuras
    file_put_contents(DRIVE_FOLDER_ID_FILE, $folderId);

    // Compartir la carpeta con Mando y Armando como lectores (viewer)
    foreach (DRIVE_COMPARTIR_CON as $email) {
        $permBody = json_encode([
            'role'         => 'reader',
            'type'         => 'user',
            'emailAddress' => $email,
        ]);
        $ch = curl_init("https://www.googleapis.com/drive/v3/files/$folderId/permissions?sendNotificationEmail=true&emailMessage=Backups+automaticos+APEX+GLASS");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $permBody,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    return $folderId;
}

/**
 * Sube un archivo a Google Drive
 */
function subirADrive($accessToken, $rutaArchivo, $nombreArchivo, $folderId) {
    $contenido = file_get_contents($rutaArchivo);
    if ($contenido === false) throw new Exception('No se pudo leer: ' . $rutaArchivo);

    $metadata = json_encode([
        'name'    => $nombreArchivo,
        'parents' => [$folderId],
    ]);

    $boundary = 'apex_boundary_' . uniqid();
    $body  = "--$boundary\r\n";
    $body .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
    $body .= $metadata . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: application/gzip\r\n\r\n";
    $body .= $contenido . "\r\n";
    $body .= "--$boundary--";

    $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . strlen($body),
        ],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err)      throw new Exception('cURL upload error: ' . $err);
    if ($code !== 200) throw new Exception('Error Drive upload HTTP ' . $code . ': ' . $resp);

    $data = json_decode($resp, true);
    return $data['id'] ?? null;
}

/**
 * Lista archivos de backup en la carpeta de Drive
 */
function listarBackupsDrive($accessToken, $folderId) {
    $query = urlencode("'$folderId' in parents and name contains 'backup_' and trashed=false");
    $url   = "https://www.googleapis.com/drive/v3/files?q=$query&fields=files(id,name,createdTime)&orderBy=createdTime";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) throw new Exception('cURL list error: ' . $err);
    $data = json_decode($resp, true);
    return $data['files'] ?? [];
}

/**
 * Borra un archivo de Drive por ID
 */
function borrarDeDrive($accessToken, $fileId) {
    $ch = curl_init("https://www.googleapis.com/drive/v3/files/$fileId");
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ── HACER EL BACKUP DE BD ─────────────────────────────────────────────────────

$fecha    = date('Y-m-d_H-i');
$nombreGz = 'backup_' . $fecha . '.sql.gz';
$rutaGz   = BACKUP_DIR . '/' . $nombreGz;
$rutaSql  = BACKUP_DIR . '/backup_' . $fecha . '.sql';

$ok          = false;
$metodo      = '';
$tamano      = 0;
$driveFileId = null;
$errores     = [];

// Intentar mysqldump primero
$mysqldumpPath = '';
foreach (['/usr/bin/mysqldump', '/usr/local/bin/mysqldump', 'mysqldump'] as $path) {
    $test = @shell_exec($path . ' --version 2>&1');
    if ($test && strpos($test, 'Distrib') !== false) {
        $mysqldumpPath = $path;
        break;
    }
}

if ($mysqldumpPath) {
    $cmd = sprintf(
        '%s --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s 2>&1',
        escapeshellcmd($mysqldumpPath),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASS),
        escapeshellarg(DB_NAME),
        escapeshellarg($rutaSql)
    );
    exec($cmd, $output, $returnCode);

    if ($returnCode === 0 && file_exists($rutaSql) && filesize($rutaSql) > 1000) {
        $gz = gzopen($rutaGz, 'w9');
        gzwrite($gz, file_get_contents($rutaSql));
        gzclose($gz);
        unlink($rutaSql);
        $ok     = true;
        $metodo = 'mysqldump';
        $tamano = filesize($rutaGz);
    }
}

if (!$ok) {
    $metodo = 'php-pdo';
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $sql  = "-- APEX GLASS Backup\n-- Fecha: " . date('Y-m-d H:i:s') . "\n-- Base: " . DB_NAME . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
        $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tablas as $tabla) {
            $create = $pdo->query("SHOW CREATE TABLE `$tabla`")->fetch(PDO::FETCH_ASSOC);
            $sql .= "-- Tabla: $tabla\nDROP TABLE IF EXISTS `$tabla`;\n" . $create['Create Table'] . ";\n\n";

            $filas = $pdo->query("SELECT * FROM `$tabla`")->fetchAll(PDO::FETCH_ASSOC);
            if (empty($filas)) { $sql .= "-- (tabla vacía)\n\n"; continue; }

            $lote = [];
            foreach ($filas as $i => $fila) {
                $valores = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote($v), array_values($fila));
                $lote[]  = '(' . implode(',', $valores) . ')';
                if (count($lote) >= 500 || $i === count($filas) - 1) {
                    $cols  = '`' . implode('`,`', array_keys($fila)) . '`';
                    $sql  .= "INSERT INTO `$tabla` ($cols) VALUES\n" . implode(",\n", $lote) . ";\n";
                    $lote  = [];
                }
            }
            $sql .= "\n";
        }
        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        $gz = gzopen($rutaGz, 'w9');
        gzwrite($gz, $sql);
        gzclose($gz);
        $ok     = true;
        $tamano = filesize($rutaGz);

    } catch (Exception $e) {
        $errores[] = 'BD Error: ' . $e->getMessage();
    }
}

// ── LIMPIAR BACKUPS LOCALES VIEJOS ────────────────────────────────────────────

$borradosLocal = 0;
$limite        = time() - (DIAS_RETENER * 86400);
foreach (glob(BACKUP_DIR . '/backup_*.sql.gz') as $f) {
    if (filemtime($f) < $limite) { unlink($f); $borradosLocal++; }
}

// ── LOG ───────────────────────────────────────────────────────────────────────

$logLinea = sprintf(
    "[%s] BD=%s | método=%s | tamaño=%s KB | borrados_local=%d%s\n",
    date('Y-m-d H:i:s'),
    $ok ? 'OK' : 'ERROR',
    $metodo,
    $ok ? round($tamano / 1024, 1) : '0',
    $borradosLocal,
    !empty($errores) ? ' | ERRORES: ' . implode(' | ', $errores) : ''
);
file_put_contents(BACKUP_DIR . '/backup.log', $logLinea, FILE_APPEND);

// ── RESPUESTA ─────────────────────────────────────────────────────────────────

if (php_sapi_name() === 'cli') {
    echo $logLinea;
} else {
    header('Content-Type: application/json');
    echo json_encode([
        'ok'             => $ok,
        'metodo'         => $metodo,
        'archivo'        => $ok ? $nombreGz : null,
        'tamano_kb'      => $ok ? round($tamano / 1024, 1) : 0,
        'borrados_local' => $borradosLocal,
        'errores'        => $errores,
        'fecha'          => date('Y-m-d H:i:s'),
    ]);
}
