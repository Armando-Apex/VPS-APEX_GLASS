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

// Guardar en directorio temporal — /tmp siempre es escribible por PHP-FPM
$tmpDir  = sys_get_temp_dir() . '/apex_csf';
if (!is_dir($tmpDir)) mkdir($tmpDir, 0700, true);
$token   = bin2hex(random_bytes(8));
$tmpFile = $tmpDir . '/' . $token . '.pdf';

if (!move_uploaded_file($file['tmp_name'], $tmpFile)) {
    echo json_encode(['ok' => false, 'error' => 'Error al procesar el archivo']);
    exit;
}

// ── Intento 1: pdftotext (PDFs nativos con capa de texto) ────────────────────
$outFile = $tmpFile . '.txt';
exec('pdftotext -f 1 -l 2 ' . escapeshellarg($tmpFile) . ' ' . escapeshellarg($outFile) . ' 2>/dev/null', $_, $ret);

$texto = '';
if ($ret === 0 && file_exists($outFile)) {
    $texto = trim(file_get_contents($outFile));
    unlink($outFile);
}

// ── Intento 2: OCR con Tesseract (PDFs escaneados / "Print to PDF") ──────────
$usedOcr = false;
if (strlen($texto) < 100) {
    $jpgBase = $tmpDir . '/' . $token . '_pg';
    // Solo página 1 (RFC, nombre y CP siempre están ahí); régimen se selecciona manualmente si no se detecta
    exec('pdftoppm -r 150 -f 1 -l 1 -jpeg ' . escapeshellarg($tmpFile) . ' ' . escapeshellarg($jpgBase) . ' 2>/dev/null', $_, $ret2);

    $textoOcr = '';
    foreach (['-1.jpg', '-01.jpg'] as $sufijo) {
        $imgFile = $jpgBase . $sufijo;
        if (!file_exists($imgFile)) continue;
        $ocrOut = $tmpDir . '/' . $token . '_ocr';
        exec('tesseract ' . escapeshellarg($imgFile) . ' ' . escapeshellarg($ocrOut) . ' -l spa --psm 6 2>/dev/null');
        $ocrTxt = $ocrOut . '.txt';
        if (file_exists($ocrTxt)) {
            $textoOcr = file_get_contents($ocrTxt);
            unlink($ocrTxt);
        }
        unlink($imgFile);
        break;
    }

    if (trim($textoOcr)) {
        $texto   = trim($textoOcr);
        $usedOcr = true;
    }
}

unlink($tmpFile);

if (!$texto) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo extraer texto del PDF. Intenta con el PDF original descargado del portal del SAT.']);
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
// OCR puede confundir Ñ con N o introducir espacios — limpiar antes
$textoRfc = preg_replace('/\s+/', ' ', strtoupper($texto));
if (preg_match('/\b([A-ZN&]{3,4}\d{6}[A-Z0-9]{3})\b/', $textoRfc, $m)) {
    $datos['rfc'] = $m[1];
}

// Nombre / Razón Social
// Personas morales: línea después de "Denominación/Razón Social" o "Razón Social"
if (preg_match('/(?:Denominaci[oó]n\s*\/?\s*Raz[oó]n\s*Social\s*[:\n\r]+)\s*([^\n\r]+)/ui', $texto, $m)) {
    $datos['nombre'] = trim($m[1]);
}

// Personas físicas: nombre completo desde "Nombre(s)" + apellidos
// PDF nativo SAT: "Apellido Paterno / Apellido Materno"
// PDF escaneado/OCR: "Primer Apellido / Segundo Apellido"
if (!$datos['nombre']) {
    $nom = $ap = $am = '';
    if (preg_match('/Nombre\s*\(?s\)?\s*[:\n\r]+\s*([^\n\r]+)/ui', $texto, $m)) $nom = trim($m[1]);
    // Apellido paterno: tanto "Apellido Paterno" como "Primer Apellido"
    if (preg_match('/(?:Apellido\s+Paterno|Primer\s+Apellido)\s*[:\n\r]+\s*([^\n\r]+)/ui', $texto, $m)) $ap = trim($m[1]);
    // Apellido materno: tanto "Apellido Materno" como "Segundo Apellido"
    if (preg_match('/(?:Apellido\s+Materno|Segundo\s+Apellido)\s*[:\n\r]+\s*([^\n\r]+)/ui', $texto, $m)) $am = trim($m[1]);
    $partes = array_filter([$ap, $am, $nom]);
    if ($partes) $datos['nombre'] = implode(' ', $partes);
}

// Fallback OCR: nombre aparece antes del label "Nombre, denominación o razón social"
// Formato: RFC\nNOMBRE COMPLETO\nApellido siguiente línea\nNombre, denominación...
if (!$datos['nombre'] && $usedOcr) {
    if (preg_match('/\b[A-Z]{3,4}\d{6}[A-Z0-9]{3}\b\s*\n+\s*([A-ZÁÉÍÓÚÑ][A-ZÁÉÍÓÚÑ\s]+)\n[A-ZÁÉÍÓÚÑ\s]*\nNombre,/u', $texto, $m)) {
        $lineas = array_filter(array_map('trim', explode("\n", $m[1])));
        $datos['nombre'] = implode(' ', $lineas);
    }
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
