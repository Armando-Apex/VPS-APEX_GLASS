<?php
/**
 * APEX GLASS — Validación masiva de datos fiscales contra el padrón del SAT
 * Archivo: scripts/validar_datos_fiscales.php
 *
 * POR QUÉ EXISTE
 * El error más común y más molesto del CFDI 4.0 es que la razón social del receptor
 * debe coincidir EXACTO con el padrón del SAT (mayúsculas, sin el régimen de capital
 * tipo "SA DE CV"). Si no coincide, el PAC rechaza el timbrado — y normalmente eso se
 * descubre con el cliente enfrente esperando su factura.
 *
 * Este script lo detecta por adelantado y en lote: por cada cliente con RFC capturado
 * intenta un timbrado en el SANDBOX de FacturAPI y reporta quién pasa y quién no, con
 * el motivo. Verificado el 26-sep-2026: el sandbox SÍ valida RFC + razón social contra
 * el padrón real del SAT (un nombre inventado con un RFC real da 400), así que sirve
 * como comprobación fiable ANTES de pasar a modo live.
 *
 * SEGURIDAD / EFECTOS
 *   - Solo lectura sobre nuestra base de datos: no escribe ni una fila.
 *   - Exige FACTURAPI_MODE=test. Si el sistema está en live, ABORTA: no vamos a emitir
 *     comprobantes fiscales reales para probar datos.
 *   - Cada intento que pasa crea una factura en el sandbox de FacturAPI; el script la
 *     cancela enseguida. Las que fallan no crean nada.
 *
 * USO
 *   php84 scripts/validar_datos_fiscales.php              # todos los que tengan RFC
 *   php84 scripts/validar_datos_fiscales.php --limite=5   # solo los primeros 5
 *   php84 scripts/validar_datos_fiscales.php --cliente=CTN-147
 *   php84 scripts/validar_datos_fiscales.php --csv=/ruta/reporte.csv
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo por linea de comandos.\n"); }

require_once __DIR__ . '/../api/config.php';

// ── Guardas ──────────────────────────────────────────────────────────────────
if (FACTURAPI_MODE === 'live') {
    exit("ABORTA: FACTURAPI_MODE=live. Este script solo corre en modo test — no se emiten\n"
       . "comprobantes fiscales reales para validar datos. Cambia a test o hazlo en otro momento.\n");
}
if (FACTURAPI_KEY === '') {
    exit("ABORTA: no hay llave de FacturAPI configurada en .env.\n");
}

// ── Argumentos ───────────────────────────────────────────────────────────────
$limite  = 0;
$soloUno = '';
$csvPath = '';
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--limite=(\d+)$/', $a, $m))       $limite  = (int)$m[1];
    elseif (preg_match('/^--cliente=(.+)$/', $a, $m))   $soloUno = trim($m[1]);
    elseif (preg_match('/^--csv=(.+)$/', $a, $m))       $csvPath = trim($m[1]);
    else exit("Argumento no reconocido: $a\n");
}

$pdo = getDB();

$sql = "SELECT id, codigo, COALESCE(NULLIF(razon_social,''), nombre) AS nombre_fiscal,
               rfc, cp_fiscal, regimen_fiscal, email
        FROM clientes
        WHERE activo = 1 AND rfc IS NOT NULL AND rfc <> ''";
$params = [];
if ($soloUno !== '') { $sql .= " AND codigo = ?"; $params[] = $soloUno; }
$sql .= " ORDER BY codigo ASC";
if ($limite > 0) $sql .= " LIMIT " . $limite;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$clientes) exit("No hay clientes con RFC capturado que coincidan con el filtro.\n");

echo "Validando " . count($clientes) . " cliente(s) contra el padron del SAT (sandbox de FacturAPI).\n";
echo "Cada intento que pase crea una factura de prueba y se cancela enseguida.\n\n";

function fapi($url, $method = 'GET', $body = null) {
    $ch = curl_init($url);
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 40,
          CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . FACTURAPI_KEY, 'Content-Type: application/json']];
    if ($method === 'POST')   { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
    if ($method === 'DELETE') { $o[CURLOPT_CUSTOMREQUEST] = 'DELETE'; }
    curl_setopt_array($ch, $o);
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $e = curl_error($ch);
    unset($ch);
    return [$c, json_decode($r, true), $e];
}

$resultados = [];
$folio = 90000 + (int)date('His');   // serie propia para no tocar la numeracion real
$ok = 0; $mal = 0; $incompletos = 0;

foreach ($clientes as $c) {
    $etiqueta = sprintf('%-10s %-38s', $c['codigo'], mb_substr($c['nombre_fiscal'], 0, 38));

    // Faltantes obligatorios: ni vale la pena molestar al PAC
    $falta = [];
    if (!$c['cp_fiscal'])      $falta[] = 'CP fiscal';
    if (!$c['regimen_fiscal']) $falta[] = 'regimen fiscal';
    if ($falta) {
        $incompletos++;
        $resultados[] = [$c['codigo'], $c['nombre_fiscal'], $c['rfc'], 'INCOMPLETO', 'Falta ' . implode(' y ', $falta)];
        echo $etiqueta . " INCOMPLETO  Falta " . implode(' y ', $falta) . "\n";
        continue;
    }

    $folio++;
    $payload = [
        'type' => 'I', 'use' => 'G03', 'payment_form' => '01', 'payment_method' => 'PUE',
        'series' => 'VAL', 'folio_number' => $folio, 'currency' => 'MXN',
        'customer' => [
            'legal_name' => $c['nombre_fiscal'],
            'tax_id'     => $c['rfc'],
            'tax_system' => $c['regimen_fiscal'],
            'address'    => ['zip' => $c['cp_fiscal']],
        ],
        'items' => [[
            'quantity' => 1,
            'product'  => ['description' => 'Validacion de datos fiscales', 'product_key' => '30171706',
                           'unit_key' => 'MTK', 'price' => 1, 'tax_included' => false,
                           'taxes' => [['type' => 'IVA', 'rate' => 0.16, 'factor' => 'Tasa']]],
        ]],
    ];

    list($code, $res, $err) = fapi('https://www.facturapi.io/v2/invoices', 'POST', $payload);

    if ($err) {
        $resultados[] = [$c['codigo'], $c['nombre_fiscal'], $c['rfc'], 'ERROR RED', $err];
        echo $etiqueta . " ERROR RED   $err\n";
        continue;
    }

    if ($code === 200) {
        $ok++;
        $resultados[] = [$c['codigo'], $c['nombre_fiscal'], $c['rfc'], 'OK', ''];
        echo $etiqueta . " OK\n";
        // limpiar: cancelar la factura de validacion en el sandbox
        if (!empty($res['id'])) fapi('https://www.facturapi.io/v2/invoices/' . $res['id'] . '?motive=02', 'DELETE');
    } else {
        $mal++;
        $msg = $res['message'] ?? 'Error desconocido';
        // FacturAPI detalla el campo culpable en 'errors'
        if (!empty($res['errors'][0]['path'])) $msg = $res['errors'][0]['path'] . ': ' . ($res['errors'][0]['message'] ?? $msg);
        $msg = trim(preg_replace('/\s+/', ' ', $msg));
        $resultados[] = [$c['codigo'], $c['nombre_fiscal'], $c['rfc'], 'RECHAZADO', $msg];
        echo $etiqueta . " RECHAZADO   " . mb_substr($msg, 0, 110) . "\n";
    }

    usleep(400000);   // no atropellar la API
}

echo "\n" . str_repeat('-', 78) . "\n";
printf("Validos: %d    Rechazados: %d    Incompletos: %d    Total: %d\n", $ok, $mal, $incompletos, count($clientes));
if ($mal || $incompletos) {
    echo "\nLos RECHAZADOS casi siempre son la razon social: debe ser IGUAL a la del padron,\n";
    echo "en mayusculas y SIN el regimen de capital (sin 'SA DE CV', 'S DE RL', etc.).\n";
    echo "Lo mas confiable es pedirle al cliente su Constancia de Situacion Fiscal y subirla\n";
    echo "en el modulo Clientes -> pestana Fiscal, que rellena los campos tal cual vienen.\n";
}

if ($csvPath) {
    $fh = fopen($csvPath, 'w');
    fputcsv($fh, ['codigo', 'nombre_fiscal', 'rfc', 'resultado', 'detalle']);
    foreach ($resultados as $r) fputcsv($fh, $r);
    fclose($fh);
    echo "\nReporte guardado en $csvPath\n";
}
