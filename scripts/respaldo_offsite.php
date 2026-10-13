<?php
/**
 * APEX GLASS — Preparación del respaldo externo (offsite)
 * Archivo: scripts/respaldo_offsite.php
 *
 * POR QUÉ EXISTE
 * Hasta el 01-oct-2026 el respaldo diario (backup_runner.php) escribía el .sql.gz
 * DENTRO del mismo servidor que respalda, y nada más. Las funciones para subir a
 * Google Drive existían pero NUNCA se llamaban desde el flujo y su archivo de
 * credenciales (`_secure/apex-glass-sa.json`) no existe — o sea que la copia externa
 * nunca funcionó, y el log decía "BD=OK" todos los días sin mencionarlo.
 * Si se perdía el VPS se perdían la base Y los respaldos juntos.
 *
 * QUÉ HACE
 * Deja listo, CIFRADO, lo que la Raspberry de casa se va a llevar a un disco externo:
 *   - La base de datos del día (el mismo .sql.gz que ya generó backup_runner).
 *   - Los comprobantes fiscales (PDF/XML de archivos_facturas), que el SAT obliga a
 *     conservar 5 años y hoy viven SOLO en el disco del VPS.
 *
 * DECISIONES DE DISEÑO
 *  - **Cifrado asimétrico, con la llave PÚBLICA de la Raspberry.** El servidor cifra
 *    y NO puede descifrar lo que cifró. Si alguien compromete el VPS no puede leer
 *    ningún respaldo, ni los viejos ni los nuevos. Con una contraseña guardada aquí
 *    esa propiedad no se cumple.
 *  - **La Raspberry JALA, el servidor nunca empuja.** Así un atacante con el VPS no
 *    puede borrar las copias (no sabe ni dónde están). Ver ~/bin/offsite-serve.
 *  - **Los comprobantes se cifran UNA vez.** Un PDF/XML de CFDI no cambia nunca
 *    después de timbrar, así que se cifra al aparecer y la Pi solo baja lo nuevo —
 *    no se reenvía la carpeta completa cada día.
 *  - **Falla cerrado.** Sin llave configurada no inventa nada: aborta con mensaje y
 *    el log del respaldo lo reporta.
 *
 * USO
 *   php84 scripts/respaldo_offsite.php            # lo que corre el cron
 *   php84 scripts/respaldo_offsite.php --estado   # solo informa, no cifra nada
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo por linea de comandos.\n"); }

// ── Rutas ────────────────────────────────────────────────────────────────────
define('RAIZ',       '/home/apexglass2025/apex.glass/public_html/produccion');
define('OFFSITE',    '/home/mando/offsite');
define('DIR_BD',     OFFSITE . '/cifrado/bd');
define('DIR_FAC',    OFFSITE . '/cifrado/facturas');
define('DIR_ORIGEN_FAC', RAIZ . '/archivos_facturas');
define('DIR_BACKUPS',    RAIZ . '/_backups');
define('ARCH_DESTINATARIO', OFFSITE . '/config/destinatario.txt');
define('GNUPGHOME_APEX',    OFFSITE . '/gnupg');
define('ARCH_MANIFIESTO',   OFFSITE . '/cifrado/MANIFIESTO.txt');
define('ARCH_LOG',          OFFSITE . '/log/offsite.log');

// Retención en el DISCO EXTERNO la decide la Pi. Aquí solo se decide cuánto se
// mantiene listo para jalar: la BD de los últimos 20 días (más que los 15 que
// conserva el servidor, para que la Pi pueda recuperar varios días apagada) y
// SIEMPRE el día 1 de cada mes. Los comprobantes NUNCA se borran de aquí.
define('DIAS_BD_DISPONIBLES', 20);

$soloEstado = in_array('--estado', array_slice($argv, 1), true);

function logear($msg) {
    file_put_contents(ARCH_LOG, sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $msg), FILE_APPEND);
}

function gpgCifrar($origen, $destino, $destinatario) {
    // --trust-model always: la llave se importó a mano y a propósito; no hay red de
    // confianza que validar. -r por huella completa, no por nombre.
    $cmd = sprintf(
        'GNUPGHOME=%s /usr/bin/gpg --batch --yes --quiet --trust-model always ' .
        '--recipient %s --output %s --encrypt %s 2>&1',
        escapeshellarg(GNUPGHOME_APEX), escapeshellarg($destinatario),
        escapeshellarg($destino . '.parcial'), escapeshellarg($origen)
    );
    exec($cmd, $salida, $ret);
    if ($ret !== 0 || !file_exists($destino . '.parcial')) {
        @unlink($destino . '.parcial');
        return 'gpg falló: ' . implode(' ', $salida);
    }
    // Se escribe a .parcial y se renombra al final: así la Pi nunca alcanza a bajar
    // un archivo a medio cifrar (rename es atómico en el mismo sistema de archivos).
    if (!rename($destino . '.parcial', $destino)) {
        @unlink($destino . '.parcial');
        return 'no se pudo renombrar ' . $destino;
    }
    @chmod($destino, 0600);
    return null;
}

// ── Destinatario (llave pública de la Raspberry) ─────────────────────────────
$destinatario = is_file(ARCH_DESTINATARIO) ? trim(file_get_contents(ARCH_DESTINATARIO)) : '';
$hayLlave = ($destinatario !== '' && is_dir(GNUPGHOME_APEX));

if ($soloEstado) {
    $bds  = glob(DIR_BD . '/*.gpg') ?: [];
    $facs = glob(DIR_FAC . '/*/*.gpg') ?: [];
    $ultimo = is_file(OFFSITE . '/estado/ultimo_jalon.txt')
        ? trim(file_get_contents(OFFSITE . '/estado/ultimo_jalon.txt')) : 'nunca';
    echo "Llave de la Raspberry configurada: " . ($hayLlave ? "SI ($destinatario)" : "NO") . "\n";
    echo "Respaldos de BD listos para jalar: " . count($bds) . "\n";
    echo "Comprobantes cifrados: " . count($facs) . "\n";
    echo "Último jalón de la Raspberry: $ultimo\n";
    exit(0);
}

if (!$hayLlave) {
    $msg = 'SIN COPIA EXTERNA: falta la llave pública de la Raspberry en ' . ARCH_DESTINATARIO;
    logear($msg);
    fwrite(STDERR, $msg . "\n");
    exit(2);   // código 2 = no configurado (backup_runner lo reporta en su log)
}

$nuevos = 0; $errores = [];

// ── 1. Base de datos del día ─────────────────────────────────────────────────
// Se cifra el MISMO archivo que generó backup_runner, no un dump nuevo: así la copia
// externa es idéntica a la local y no se hace un segundo mysqldump.
$dumps = glob(DIR_BACKUPS . '/backup_*.sql.gz') ?: [];
usort($dumps, fn($a, $b) => filemtime($b) <=> filemtime($a));
foreach ($dumps as $dump) {
    $destino = DIR_BD . '/' . basename($dump) . '.gpg';
    if (is_file($destino)) continue;
    if ($err = gpgCifrar($dump, $destino, $destinatario)) { $errores[] = basename($dump) . ': ' . $err; continue; }
    $nuevos++;
}

// ── 2. Comprobantes fiscales (una sola vez cada uno) ─────────────────────────
foreach (glob(DIR_ORIGEN_FAC . '/*', GLOB_ONLYDIR) ?: [] as $mes) {
    $nombreMes = basename($mes);
    if (!preg_match('/^\d{4}-\d{2}$/', $nombreMes)) continue;   // solo carpetas de mes
    $destinoMes = DIR_FAC . '/' . $nombreMes;
    if (!is_dir($destinoMes) && !@mkdir($destinoMes, 0700, true)) {
        $errores[] = 'no se pudo crear ' . $destinoMes; continue;
    }
    foreach (glob($mes . '/*.{pdf,xml}', GLOB_BRACE) ?: [] as $arch) {
        $destino = $destinoMes . '/' . basename($arch) . '.gpg';
        if (is_file($destino)) continue;
        if ($err = gpgCifrar($arch, $destino, $destinatario)) { $errores[] = basename($arch) . ': ' . $err; continue; }
        $nuevos++;
    }
}

// ── 3. Limpiar BD vieja de la zona de jalón (los comprobantes NUNCA se borran) ─
$borrados = 0;
$limite = time() - (DIAS_BD_DISPONIBLES * 86400);
foreach (glob(DIR_BD . '/*.gpg') ?: [] as $f) {
    if (filemtime($f) >= $limite) continue;
    // El día 1 de cada mes se conserva siempre: es la copia de archivo histórico.
    if (preg_match('/backup_(\d{4})-(\d{2})-01_/', basename($f))) continue;
    unlink($f); $borrados++;
}

// ── 4. Manifiesto con sumas de verificación ──────────────────────────────────
// La Pi compara este manifiesto con lo que ya tiene y solo baja lo que falta; el
// sha256 le permite confirmar que el archivo llegó completo y sin corromperse.
$lineas = [];
foreach (array_merge(glob(DIR_BD . '/*.gpg') ?: [], glob(DIR_FAC . '/*/*.gpg') ?: []) as $f) {
    $rel = ltrim(str_replace(OFFSITE . '/cifrado', '', $f), '/');
    $lineas[] = hash_file('sha256', $f) . ' ' . filesize($f) . ' ' . $rel;
}
sort($lineas);
file_put_contents(ARCH_MANIFIESTO . '.parcial', implode("\n", $lineas) . "\n");
rename(ARCH_MANIFIESTO . '.parcial', ARCH_MANIFIESTO);
@chmod(ARCH_MANIFIESTO, 0600);

$resumen = sprintf('cifrados_nuevos=%d | en_zona_de_jalon=%d | bd_borradas=%d%s',
    $nuevos, count($lineas), $borrados,
    $errores ? ' | ERRORES: ' . implode(' | ', $errores) : '');
logear($resumen);
echo $resumen . "\n";
exit($errores ? 1 : 0);
