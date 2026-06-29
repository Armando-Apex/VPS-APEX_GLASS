<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';

header('Content-Type: application/json');

$user = requirePermisoApi('ver_wip');
$pdo  = getDB();

$accion = $_GET['accion'] ?? '';

// ── GET buscar_clientes ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'buscar_clientes') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode(['ok'=>true,'clientes'=>[]]); exit; }
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT id, codigo, razon_social, nombre, email,
               rfc, cp_fiscal, regimen_fiscal
        FROM clientes
        WHERE activo = 1
          AND (razon_social LIKE ? OR nombre LIKE ? OR codigo LIKE ?)
        ORDER BY razon_social ASC
        LIMIT 10
    ");
    $stmt->execute([$like, $like, $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'clientes'=>$rows]);
    exit;
}

// ── GET lista ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'lista') {
    $rows = $pdo->query("
        SELECT id, folio_interno, serie, folio_numero, tipo_cfdi, fecha,
               receptor_nombre, receptor_rfc, receptor_cp, receptor_regimen,
               receptor_uso_cfdi, receptor_email, forma_pago, metodo_pago,
               conceptos, subtotal, iva, total,
               estatus, uuid, modo, pdf_url, created_at
        FROM facturas
        ORDER BY id DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'facturas' => $rows]);
    exit;
}

// ── POST guardar (borrador) ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'guardar') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) { echo json_encode(['ok'=>false,'error'=>'Datos inválidos']); exit; }

    // Validaciones mínimas
    $requeridos = ['receptor_rfc','receptor_nombre','receptor_cp','receptor_regimen',
                   'receptor_uso_cfdi','forma_pago','metodo_pago','conceptos'];
    foreach ($requeridos as $k) {
        if (empty($d[$k])) {
            echo json_encode(['ok'=>false,'error'=>'Campo requerido: '.$k]); exit;
        }
    }

    // Folio interno: obtener siguiente número
    $serie = preg_replace('/[^A-Z0-9]/', '', strtoupper($d['serie'] ?? 'A'));
    if (!$serie) $serie = 'A';
    $stmt = $pdo->prepare("SELECT COALESCE(MAX(folio_numero),0)+1 FROM facturas WHERE serie=?");
    $stmt->execute([$serie]);
    $folioNum = (int)$stmt->fetchColumn();
    $folioInterno = $serie . '-' . str_pad($folioNum, 3, '0', STR_PAD_LEFT);

    // Calcular totales (no confiar en el cliente)
    $sub = 0; $iva = 0;
    foreach ($d['conceptos'] as $c) {
        $imp  = (float)($c['cant'] ?? 0) * (float)($c['precio'] ?? 0);
        $sub += $imp;
        if (!empty($c['iva'])) $iva += round($imp * 0.16, 6);
    }
    $sub   = round($sub, 2);
    $iva   = round($iva, 2);
    $total = round($sub + $iva, 2);

    $id = isset($d['id']) ? (int)$d['id'] : 0;

    if ($id) {
        // Actualizar borrador existente — verificar propiedad
        $stmt = $pdo->prepare("
            UPDATE facturas SET
                tipo_cfdi=?, fecha=?, receptor_nombre=?, receptor_rfc=?,
                receptor_cp=?, receptor_regimen=?, receptor_uso_cfdi=?,
                receptor_email=?, forma_pago=?, metodo_pago=?,
                conceptos=?, subtotal=?, iva=?, total=?, updated_at=NOW()
            WHERE id=? AND estatus='borrador' AND creado_por=?
        ");
        $stmt->execute([
            $d['tipo_cfdi'] ?? 'I', $d['fecha'] ?? date('Y-m-d'),
            $d['receptor_nombre'], $d['receptor_rfc'], $d['receptor_cp'],
            $d['receptor_regimen'], $d['receptor_uso_cfdi'],
            $d['receptor_email'] ?? null, $d['forma_pago'], $d['metodo_pago'],
            json_encode($d['conceptos']), $sub, $iva, $total, $id, $user['nombre']
        ]);
        echo json_encode(['ok'=>true,'id'=>$id,'folio'=>$folioInterno,'total'=>$total]);
    } else {
        // Nueva factura
        $stmt = $pdo->prepare("
            INSERT INTO facturas
                (folio_interno, serie, folio_numero, tipo_cfdi, fecha,
                 receptor_nombre, receptor_rfc, receptor_cp, receptor_regimen,
                 receptor_uso_cfdi, receptor_email, forma_pago, metodo_pago,
                 conceptos, subtotal, iva, total, estatus, modo, creado_por)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'borrador',?,?)
        ");
        $stmt->execute([
            $folioInterno, $serie, $folioNum,
            $d['tipo_cfdi'] ?? 'I', $d['fecha'] ?? date('Y-m-d'),
            $d['receptor_nombre'], $d['receptor_rfc'], $d['receptor_cp'],
            $d['receptor_regimen'], $d['receptor_uso_cfdi'],
            $d['receptor_email'] ?? null, $d['forma_pago'], $d['metodo_pago'],
            json_encode($d['conceptos']), $sub, $iva, $total,
            FACTURAPI_MODE, $user['nombre']
        ]);
        $newId = $pdo->lastInsertId();
        echo json_encode(['ok'=>true,'id'=>$newId,'folio'=>$folioInterno,'total'=>$total]);
    }
    exit;
}

// ── POST timbrar ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'timbrar') {
    $d  = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'ID requerido']); exit; }

    // Cargar factura — verificar propiedad
    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? AND estatus='borrador' AND creado_por=?");
    $stmt->execute([$id, $user['nombre']]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac) { echo json_encode(['ok'=>false,'error'=>'Factura no encontrada o ya timbrada']); exit; }

    $conceptos = json_decode($fac['conceptos'], true);

    // Construir items para FacturAPI
    $items = [];
    foreach ($conceptos as $c) {
        $applyIva = !empty($c['iva']);
        $item = [
            'quantity' => (float)$c['cant'],
            'product'  => [
                'description'  => $c['desc'],
                'product_key'  => $c['clave']  ?: '01010101',
                'unit_key'     => $c['unidad'] ?: 'ACT',
                'price'        => (float)$c['precio'],
                'tax_included' => false,
                'taxes'        => $applyIva
                    ? [['type'=>'IVA','rate'=>0.16,'factor'=>'Tasa']]
                    : [],
            ],
        ];
        $items[] = $item;
    }

    // Tipo CFDI: IG se manda como I a FacturAPI
    $tipoCfdi = ($fac['tipo_cfdi'] === 'IG') ? 'I' : $fac['tipo_cfdi'];

    // Payload FacturAPI v2
    $payload = [
        'type'           => $tipoCfdi,
        'use'            => $fac['receptor_uso_cfdi'],
        'payment_form'   => $fac['forma_pago'],
        'payment_method' => $fac['metodo_pago'],
        'series'         => $fac['serie'],
        'folio_number'   => (int)$fac['folio_numero'],
        'currency'       => 'MXN',
        'customer'       => [
            'legal_name'  => $fac['receptor_nombre'],
            'tax_id'      => $fac['receptor_rfc'],
            'tax_system'  => $fac['receptor_regimen'],
            'email'       => $fac['receptor_email'] ?: null,
            'address'     => ['zip' => $fac['receptor_cp']],
        ],
        'items' => $items,
    ];

    // Llamada a FacturAPI
    $apiKey = FACTURAPI_KEY;
    $ch = curl_init('https://www.facturapi.io/v2/invoices');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['ok'=>false,'error'=>'Error de conexión con FacturAPI: '.$curlErr]);
        exit;
    }

    $res = json_decode($response, true);

    if ($httpCode !== 200) {
        $msg = $res['message'] ?? $res['error'] ?? 'Error desconocido de FacturAPI';
        error_log('APEX FacturAPI error '.$httpCode.': '.$response);
        echo json_encode(['ok'=>false,'error'=>'FacturAPI: '.$msg]);
        exit;
    }

    // Guardar resultado en BD
    $uuid        = $res['uuid']         ?? '';
    $facurapiId  = $res['id']           ?? '';
    $pdfUrl      = 'https://www.facturapi.io/v2/invoices/' . $facurapiId . '/pdf';
    $xmlUrl      = 'https://www.facturapi.io/v2/invoices/' . $facurapiId . '/xml';

    $stmt = $pdo->prepare("
        UPDATE facturas SET
            estatus='timbrada', facturapi_id=?, uuid=?,
            pdf_url=?, xml_url=?, updated_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([$facurapiId, $uuid, $pdfUrl, $xmlUrl, $id]);

    echo json_encode([
        'ok'           => true,
        'uuid'         => $uuid,
        'facturapi_id' => $facurapiId,
        'pdf_url'      => $pdfUrl,
        'xml_url'      => $xmlUrl,
        'modo'         => FACTURAPI_MODE,
    ]);
    exit;
}

// ── GET descargar PDF/XML (proxy autenticado) ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && in_array($accion, ['pdf','xml'])) {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); exit; }

    $stmt = $pdo->prepare("SELECT facturapi_id, estatus FROM facturas WHERE id=?");
    $stmt->execute([$id]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac || $fac['estatus'] !== 'timbrada') { http_response_code(404); exit; }

    $url = 'https://www.facturapi.io/v2/invoices/' . $fac['facturapi_id'] . '/' . $accion;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . FACTURAPI_KEY],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $data     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) { http_response_code(502); exit; }

    header('Content-Type: ' . ($accion === 'pdf' ? 'application/pdf' : 'application/xml'));
    header('Content-Disposition: inline; filename="' . $fac['facturapi_id'] . '.' . $accion . '"');
    echo $data;
    exit;
}

// ── POST eliminar (solo borradores) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'eliminar') {
    $d  = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'ID requerido']); exit; }

    // Verificar propiedad antes de eliminar
    $stmt = $pdo->prepare("DELETE FROM facturas WHERE id=? AND creado_por=? AND (estatus='borrador' OR (estatus='timbrada' AND modo='test'))");
    $stmt->execute([$id, $user['nombre']]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok'=>false,'error'=>'No se puede eliminar: no existe o es una factura timbrada en producción']);
        exit;
    }
    echo json_encode(['ok'=>true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok'=>false,'error'=>'Acción no válida']);
