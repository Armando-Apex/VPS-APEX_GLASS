<?php
// ============================================================
//  Genera las 4 campañas WA segmentadas del mes (estado borrador).
//  Uso (CLI, mes a evaluar = el que está cerrando, normalmente
//  se corre entre el 25 y 28 de cada mes para el mes en curso):
//
//    php84 generar_campanas_segmentadas.php 2026-06 011_segmento_1 012__segmento_2 013_segmento_3 014_segmento_4
//
//  Argumentos: AAAA-MM  template_frecuentes  template_compradores_mes  template_cotizo_sin_comprar  template_sin_cotizar_mes
//
//  Segmentos (prioridad 1>2>3>4, sin traslape, solo clientes CRM activos con teléfono):
//    1. Frecuentes          = 3+ órdenes generadas en el mes (cualquier estado != cancelada)
//    2. Compradores del mes = 1-2 órdenes generadas en el mes
//    3. Cotizó sin comprar  = tiene cotización histórica, sin orden activa/entregada/pendiente_vobo, 0 órdenes el mes
//    4. Sin cotizar en el mes = sin ninguna cotización creada en el mes
//
//  No envía nada a WhatsApp — solo crea las campañas en borrador para
//  revisión y envío manual desde el módulo Campañas (botón "Enviar campaña").
// ============================================================

if (php_sapi_name() !== 'cli') {
    die('Solo CLI.');
}

$mesArg = $argv[1] ?? null;
if (!$mesArg || !preg_match('/^\d{4}-\d{2}$/', $mesArg)) {
    die("Uso: php84 generar_campanas_segmentadas.php AAAA-MM template1 template2 template3 template4\n");
}
$tplFrecuentes      = $argv[2] ?? null;
$tplCompradoresMes  = $argv[3] ?? null;
$tplCotizoSinComprar = $argv[4] ?? null;
$tplSinCotizarMes   = $argv[5] ?? null;
if (!$tplFrecuentes || !$tplCompradoresMes || !$tplCotizoSinComprar || !$tplSinCotizarMes) {
    die("Faltan templates. Uso: php84 generar_campanas_segmentadas.php AAAA-MM template1 template2 template3 template4\n");
}

chdir(__DIR__ . '/../api');
require_once 'config.php';
$db = getDB();

function normalizarTelefono($tel) {
    $tel = preg_replace('/\D/', '', $tel);
    if (strlen($tel) === 10) return '52' . $tel;
    if (strlen($tel) === 12 && substr($tel, 0, 2) === '52') return $tel;
    return '52' . substr($tel, -10);
}

// Overrides manuales de nombre por cliente_id — agregar aquí los que Armando
// vaya confirmando mes a mes (ver HISTORIAL_UPD para el origen de cada uno).
function nombreCampanaCorto($clienteId, $contacto) {
    $overrides = [
        44 => 'Veronica Guajardo',
        45 => 'Ignacio Romo',
        84 => 'Paola Lopez',
        66 => 'Srta Luz',
    ];
    if (isset($overrides[$clienteId])) return $overrides[$clienteId];
    $contacto = trim($contacto ?? '');
    if ($contacto === '') return 'Cliente';
    $palabras = preg_split('/\s+/', $contacto);
    $palabras = array_slice($palabras, 0, 2);
    return mb_convert_case(implode(' ', $palabras), MB_CASE_TITLE, 'UTF-8');
}

$mesIni = $mesArg . '-01';
$mesFin = date('Y-m-01', strtotime($mesIni . ' +1 month'));
$etiquetaMes = date('F Y', strtotime($mesIni));

// ── Segmento 1: Frecuentes (>=3 ordenes generadas en el mes, cualquier estado != cancelada)
$seg1 = $db->query("
    SELECT c.id FROM clientes c
    WHERE c.activo=1 AND c.telefono IS NOT NULL AND c.telefono != ''
    AND (SELECT COUNT(*) FROM ordenes o WHERE o.cliente_id=c.id AND o.created_at >= '$mesIni' AND o.created_at < '$mesFin' AND o.estado != 'cancelada') >= 3
")->fetchAll(PDO::FETCH_COLUMN);

// ── Segmento 2: Compradores del mes (1-2 ordenes generadas en el mes), excluye seg1
$seg1Set = "'" . implode("','", array_map('intval', $seg1)) . "'";
$seg2 = $db->query("
    SELECT c.id FROM clientes c
    WHERE c.activo=1 AND c.telefono IS NOT NULL AND c.telefono != ''
    AND c.id NOT IN (" . ($seg1 ? $seg1Set : "0") . ")
    AND (SELECT COUNT(*) FROM ordenes o WHERE o.cliente_id=c.id AND o.created_at >= '$mesIni' AND o.created_at < '$mesFin' AND o.estado != 'cancelada') BETWEEN 1 AND 2
")->fetchAll(PDO::FETCH_COLUMN);

// ── Segmento 3: Cotizo sin comprar, excluye seg1+seg2
$seg12 = array_merge($seg1, $seg2);
$seg12Set = "'" . implode("','", array_map('intval', $seg12)) . "'";
$seg3 = $db->query("
    SELECT c.id FROM clientes c
    WHERE c.activo=1 AND c.telefono IS NOT NULL AND c.telefono != ''
    AND c.id NOT IN (" . ($seg12 ? $seg12Set : "0") . ")
    AND EXISTS (SELECT 1 FROM cotizaciones ct WHERE ct.cliente_id=c.id)
    AND NOT EXISTS (SELECT 1 FROM ordenes o WHERE o.cliente_id=c.id AND o.estado IN ('activa','entregada','pendiente_vobo'))
")->fetchAll(PDO::FETCH_COLUMN);

// ── Segmento 4: Sin cotizar en el mes, excluye seg1+seg2+seg3
$seg123 = array_merge($seg1, $seg2, $seg3);
$seg123Set = "'" . implode("','", array_map('intval', $seg123)) . "'";
$seg4 = $db->query("
    SELECT c.id FROM clientes c
    WHERE c.activo=1 AND c.telefono IS NOT NULL AND c.telefono != ''
    AND c.id NOT IN (" . ($seg123 ? $seg123Set : "0") . ")
    AND NOT EXISTS (SELECT 1 FROM cotizaciones ct WHERE ct.cliente_id=c.id AND ct.created_at >= '$mesIni' AND ct.created_at < '$mesFin')
")->fetchAll(PDO::FETCH_COLUMN);

$segmentos = [
    ['nombre' => "Frecuentes (3+ ordenes $etiquetaMes)",        'template' => $tplFrecuentes,       'ids' => $seg1, 'vars' => ['{{nombre_cliente}}']],
    ['nombre' => "Compradores del mes (1-2 ordenes $etiquetaMes)", 'template' => $tplCompradoresMes,   'ids' => $seg2, 'vars' => ['{{nombre_cliente}}']],
    ['nombre' => "Cotizo sin comprar - $etiquetaMes",           'template' => $tplCotizoSinComprar, 'ids' => $seg3, 'vars' => ['{{nombre_cliente}}', '{{nombre_asesor}}']],
    ['nombre' => "Sin cotizar en $etiquetaMes",                 'template' => $tplSinCotizarMes,    'ids' => $seg4, 'vars' => ['{{nombre_cliente}}']],
];

$stmtCli = $db->prepare("SELECT id, nombre, contacto, telefono FROM clientes WHERE id = ?");

foreach ($segmentos as $seg) {
    $ids = $seg['ids'];
    $total = count($ids);

    $db->beginTransaction();
    $stmtCamp = $db->prepare("INSERT INTO campanas
        (nombre, template_nombre, template_vars_json, segmento_json, creado_por, total_destinatarios)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmtCamp->execute([
        $seg['nombre'],
        $seg['template'],
        json_encode($seg['vars']),
        json_encode(['tipo' => 'segmento_mensual', 'mes' => $mesArg]),
        'Director Administrativo',
        $total
    ]);
    $campanaId = $db->lastInsertId();

    $stmtIns = $db->prepare("INSERT INTO campana_envios (campana_id, cliente_id, telefono, nombre_override) VALUES (?, ?, ?, ?)");
    $muestra = [];
    foreach ($ids as $cid) {
        $stmtCli->execute([(int)$cid]);
        $cli = $stmtCli->fetch();
        if (!$cli || !$cli['telefono']) continue;
        $tel = normalizarTelefono($cli['telefono']);
        $nombreCorto = nombreCampanaCorto((int)$cli['id'], $cli['contacto'] ?: $cli['nombre']);
        $stmtIns->execute([$campanaId, $cli['id'], $tel, $nombreCorto]);
        if (count($muestra) < 8) {
            $muestra[] = $nombreCorto . ' (' . $cli['nombre'] . ') - ' . $tel;
        }
    }
    $db->commit();

    echo "=== Campaña #$campanaId: {$seg['nombre']} ===\n";
    echo "Template: {$seg['template']} | Total: $total\n";
    echo "Muestra:\n";
    foreach ($muestra as $m) echo "  - $m\n";
    echo "\n";
}

$totalGeneral = count($seg1) + count($seg2) + count($seg3) + count($seg4);
$totalActivos = (int)$db->query("SELECT COUNT(*) FROM clientes WHERE activo=1 AND telefono IS NOT NULL AND telefono != ''")->fetchColumn();
echo "Total cubierto: $totalGeneral de $totalActivos clientes activos con telefono.\n";
