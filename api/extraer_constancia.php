<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';
requirePermisoApi('ver_wip');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

if (empty($_FILES['constancia']) || $_FILES['constancia']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo']);
    exit;
}

$file = $_FILES['constancia'];

// Validar tipo MIME y extensión
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);

if ($ext !== 'pdf' || $mime !== 'application/pdf') {
    echo json_encode(['ok' => false, 'error' => 'Solo se aceptan archivos PDF']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'Archivo demasiado grande (máx 5 MB)']);
    exit;
}

// Guardar en directorio temporal seguro fuera del webroot
$tmpDir  = '/home/apexglass2025/tmp/constancias';
if (!is_dir($tmpDir)) mkdir($tmpDir, 0700, true);
$tmpFile = $tmpDir . '/' . bin2hex(random_bytes(8)) . '.pdf';

if (!move_uploaded_file($file['tmp_name'], $tmpFile)) {
    echo json_encode(['ok' => false, 'error' => 'Error al procesar el archivo']);
    exit;
}

// Extraer texto con pdftotext (solo primera página)
$outFile = $tmpFile . '.txt';
$cmd = 'pdftotext -f 1 -l 2 ' . escapeshellarg($tmpFile) . ' ' . escapeshellarg($outFile) . ' 2>&1';
exec($cmd, $output, $ret);

$texto = '';
if ($ret === 0 && file_exists($outFile)) {
    $texto = file_get_contents($outFile);
    unlink($outFile);
}
unlink($tmpFile);

if (!$texto) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo extraer texto del PDF. Verifica que no sea una imagen escaneada.']);
    exit;
}

// ── Parseo de campos ──────────────────────────────────────────────────────────

$datos = [
    'rfc'      => '',
    'nombre'   => '',
    'cp'       => '',
    'regimen'  => [],
    'raw_hint' => '',
];

// RFC: 12 o 13 caracteres alfanuméricos con formato SAT
// Personas morales: 3 letras + 6 dígitos + 3 alfanum
// Personas físicas: 4 letras + 6 dígitos + 3 alfanum
if (preg_match('/\b([A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3})\b/', $texto, $m)) {
    $datos['rfc'] = $m[1];
}

// Nombre / Razón Social
// Personas morales: línea después de "Denominación/Razón Social" o "Razón Social"
if (preg_match('/(?:Denominaci[oó]n\s*\/?\s*Raz[oó]n\s*Social\s*[:\n\r]+)\s*([^\n\r]+)/ui', $texto, $m)) {
    $datos['nombre'] = trim($m[1]);
}

// Personas físicas: nombre completo desde "Nombre(s)" + apellidos
// La constancia pone: Nombre (s) [NOMBRE]\nApellido Paterno [AP]\nApellido Materno [AM]
if (!$datos['nombre']) {
    $nom = $ap = $am = '';
    if (preg_match('/Nombre\s*\(s\)\s*[:\n\r]*\s*([^\n\r]+)/ui', $texto, $m)) $nom = trim($m[1]);
    if (preg_match('/Apellido\s+Paterno\s*[:\n\r]*\s*([^\n\r]+)/ui', $texto, $m)) $ap  = trim($m[1]);
    if (preg_match('/Apellido\s+Materno\s*[:\n\r]*\s*([^\n\r]+)/ui', $texto, $m)) $am  = trim($m[1]);
    $partes = array_filter([$ap, $am, $nom]);
    if ($partes) $datos['nombre'] = implode(' ', $partes);
}

// CP Fiscal: 5 dígitos después de "Código Postal" en la sección de domicilio
// La constancia tiene dos secciones; nos interesa la del domicilio fiscal
if (preg_match('/C[oó]digo\s+Postal\s*[:\n\r]*\s*(\d{5})/ui', $texto, $m)) {
    $datos['cp'] = $m[1];
}

// Régimen(es) fiscal(es): aparecen en tabla con encabezado "Régimen"
// Cada régimen en línea propia, con su fecha de inicio
// Extraemos el código numérico + descripción
$regimenes_conocidos = [
    '601' => '601 – General de Ley Personas Morales',
    '603' => '603 – Personas Morales sin Fines de Lucro',
    '606' => '606 – Arrendamiento',
    '612' => '612 – Pers. Físicas Actividades Empresariales',
    '616' => '616 – Sin obligaciones fiscales',
    '621' => '621 – Incorporación Fiscal',
    '625' => '625 – Plataformas Tecnológicas',
    '626' => '626 – Resico',
];

foreach ($regimenes_conocidos as $cod => $label) {
    if (strpos($texto, $cod) !== false) {
        $datos['regimen'][] = ['cod' => $cod, 'label' => $label];
    }
}

// Fallback: buscar por nombre de régimen en texto
if (empty($datos['regimen'])) {
    $patrones_regimen = [
        '601' => 'General de Ley Personas Morales',
        '603' => 'Personas Morales sin Fines de Lucro',
        '606' => 'Arrendamiento',
        '612' => 'Actividades Empresariales',
        '616' => 'Sin obligaciones fiscales',
        '621' => 'Incorporaci[oó]n Fiscal',
        '625' => 'Plataformas',
        '626' => 'Resico',
    ];
    foreach ($patrones_regimen as $cod => $pat) {
        if (preg_match('/' . $pat . '/ui', $texto)) {
            $datos['regimen'][] = ['cod' => $cod, 'label' => $regimenes_conocidos[$cod]];
        }
    }
}

// Verificación mínima
if (!$datos['rfc'] && !$datos['nombre']) {
    echo json_encode([
        'ok'    => false,
        'error' => 'No se encontraron datos fiscales en el PDF. Verifica que sea una Constancia de Situación Fiscal del SAT.'
    ]);
    exit;
}

echo json_encode(['ok' => true, 'datos' => $datos]);
