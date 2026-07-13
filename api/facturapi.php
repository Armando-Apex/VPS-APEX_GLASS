<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/mailer.php';

// Recibe "correo1@x.com, correo2@x.com" y regresa solo los que son válidos (silenciosamente descarta lo demás)
function _correosValidos($raw) {
    $out = [];
    foreach (explode(',', (string)$raw) as $e) {
        $e = trim($e);
        if ($e && filter_var($e, FILTER_VALIDATE_EMAIL)) $out[] = $e;
    }
    return $out;
}

// Descarga un archivo (PDF/XML) de FacturAPI autenticado; regresa el binario o null si falla
function _descargarArchivoFacturapi($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer '.FACTURAPI_KEY], CURLOPT_TIMEOUT=>20]);
    $bin = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) $bin = null;
    unset($ch);
    return $bin;
}

header('Content-Type: application/json');

$user = requirePermisoApi('ver_wip');
$pdo  = getDB();

$accion = $_GET['accion'] ?? '';

// ── GET buscar_clientes ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'buscar_clientes') {
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) { jsonResponse(['ok'=>true,'clientes'=>[]]); exit; }
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
    jsonResponse(['ok'=>true,'clientes'=>$rows]);
    exit;
}

// ── GET lista_clientes (para el <select>) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'lista_clientes') {
    $rows = $pdo->query("
        SELECT id, codigo, razon_social, nombre, email,
               rfc, cp_fiscal, regimen_fiscal
        FROM clientes
        WHERE activo = 1
        ORDER BY COALESCE(NULLIF(razon_social, ''), nombre) ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['ok'=>true,'clientes'=>$rows]);
    exit;
}

// ── GET sugerir_ordenes (folio parcial) ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'sugerir_ordenes') {
    $q = trim($_GET['q'] ?? '');
    if ($q === '') { jsonResponse(['ok'=>true,'ordenes'=>[]]); exit; }
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT folio, cliente_nombre
        FROM ordenes
        WHERE folio LIKE ?
        ORDER BY id DESC
        LIMIT 15
    ");
    $stmt->execute([$like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['ok'=>true,'ordenes'=>$rows]);
    exit;
}

// ── GET buscar_orden (folio) ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'buscar_orden') {
    $folio = trim($_GET['folio'] ?? '');
    if ($folio === '') { jsonResponse(['ok'=>false,'error'=>'Folio requerido']); exit; }

    $stmt = $pdo->prepare("SELECT id, folio, cliente_id, cliente_nombre FROM ordenes WHERE folio = ? LIMIT 1");
    $stmt->execute([$folio]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$orden) { jsonResponse(['ok'=>false,'error'=>'No se encontró una orden con ese folio']); exit; }

    $cliente = null;
    if ($orden['cliente_id']) {
        $stmt = $pdo->prepare("
            SELECT id, codigo, razon_social, nombre, email, rfc, cp_fiscal, regimen_fiscal
            FROM clientes WHERE id = ?
        ");
        $stmt->execute([$orden['cliente_id']]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Relacionar con la cotización de origen vía cotizaciones.orden_id (se llena siempre al convertir, api/cotizaciones.php)
    $stmt = $pdo->prepare("SELECT id, tipo, descuento FROM cotizaciones WHERE orden_id = ? LIMIT 1");
    $stmt->execute([$orden['id']]);
    $cot       = $stmt->fetch(PDO::FETCH_ASSOC);
    $cotId     = $cot['id'] ?? null;
    $esMaquila = ($cot['tipo'] ?? 'suministro') === 'maquila';
    $descuento = (float)($cot['descuento'] ?? 0);

    $conceptos = [];
    if ($cotId && $esMaquila) {
        // Maquila guarda sus renglones en cotizaciones_maquila_partidas (tabla distinta, ver UPD-273/302);
        // subtotal ya incluye corte/canteado/taladro/horno y no lleva descuento por partida (no aplica en maquila).
        $stmt = $pdo->prepare("
            SELECT mp.*, tv.nombre AS tipo_vidrio_nombre
            FROM cotizaciones_maquila_partidas mp
            LEFT JOIN maquila_tipos_vidrio tv ON tv.id = mp.cristal_tipo_id
            WHERE mp.cotizacion_id = ?
            ORDER BY mp.num_partida ASC
        ");
        $stmt->execute([$cotId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $servicios = [];
            if ($p['corte'])    $servicios[] = 'Corte';
            if ($p['canteado']) $servicios[] = 'Canteado';
            if ($p['taladros_pasados'] + $p['taladros_avellanados'] > 0) $servicios[] = 'Taladro';
            if ($p['templado'])  $servicios[] = 'Templado';
            $desc = trim(($p['tipo_vidrio_nombre'] ?: 'Vidrio') . ' ' . $p['espesor_mm'] . 'mm');
            if ($servicios) $desc .= ' - Maquila: ' . implode('/', $servicios);
            $conceptos[] = [
                'desc'   => $desc,
                'clave'  => '',
                'unidad' => 'MTK',
                'cant'   => round((float)$p['m2'] * (int)$p['cantidad'], 6),
                'precio' => (float)$p['m2'] > 0 ? round((float)$p['subtotal'] / ((float)$p['m2'] * (int)$p['cantidad']), 6) : 0,
                'iva'    => true,
            ];
        }
    } elseif ($cotId) {
        $stmt = $pdo->prepare("
            SELECT cristal_nombre, m2, cantidad, precio_m2_usado
            FROM cotizaciones_partidas
            WHERE cotizacion_id = ?
            ORDER BY num_partida ASC
        ");
        $stmt->execute([$cotId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            // precio_m2_usado es bruto (sin descuento) — aplicar el % de la cotización, igual que el resto del sistema.
            // Se redondea a 6 decimales (no 4) para no perder precisión de m2 (decimal(10,6) en BD) al multiplicar.
            $precioNeto = $descuento > 0
                ? round((float)$p['precio_m2_usado'] * (1 - $descuento / 100), 6)
                : (float)$p['precio_m2_usado'];
            $conceptos[] = [
                'desc'   => $p['cristal_nombre'] ?: 'Vidrio',
                'clave'  => '',
                'unidad' => 'MTK',
                'cant'   => round((float)$p['m2'] * (int)$p['cantidad'], 6),
                'precio' => $precioNeto,
                'iva'    => true,
            ];
        }
    }

    jsonResponse(['ok'=>true, 'orden'=>$orden, 'cliente'=>$cliente, 'conceptos'=>$conceptos]);
    exit;
}

// ── GET lista ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'lista') {
    $rows = $pdo->query("
        SELECT f.id, f.folio_interno, f.serie, f.folio_numero, f.orden_folio, f.tipo_cfdi, f.fecha,
               f.receptor_nombre, f.receptor_rfc, f.receptor_cp, f.receptor_regimen,
               f.receptor_uso_cfdi, f.receptor_email, f.cliente_solicito_id,
               COALESCE(NULLIF(cs.razon_social,''), cs.nombre) AS cliente_solicito_nombre,
               f.forma_pago, f.metodo_pago,
               f.conceptos, f.subtotal, f.iva, f.total,
               f.estatus, f.uuid, f.modo, f.pdf_url, f.motivo_cancel, f.sustituye_uuid, f.pac_cancel_status, f.created_at
        FROM facturas f
        LEFT JOIN clientes cs ON cs.id = f.cliente_solicito_id
        ORDER BY f.id DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(['ok' => true, 'facturas' => $rows]);
    exit;
}

// ── POST guardar (borrador) ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'guardar') {
    $d = json_decode(file_get_contents('php://input'), true);
    if (!$d) { jsonResponse(['ok'=>false,'error'=>'Datos inválidos']); exit; }

    // Validaciones mínimas
    $requeridos = ['receptor_rfc','receptor_nombre','receptor_cp','receptor_regimen',
                   'receptor_uso_cfdi','forma_pago','metodo_pago','conceptos'];
    foreach ($requeridos as $k) {
        if (empty($d[$k])) {
            jsonResponse(['ok'=>false,'error'=>'Campo requerido: '.$k]); exit;
        }
    }

    // Correo(s) del receptor: acepta varios separados por coma (algunos clientes piden que la
    // factura llegue a facturación Y a su contador, por ejemplo) — se valida cada uno individualmente.
    if (!empty($d['receptor_email'])) {
        foreach (explode(',', $d['receptor_email']) as $correo) {
            $correo = trim($correo);
            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['ok'=>false,'error'=>'Correo inválido: '.$correo]); exit;
            }
        }
    }

    // Público en General: exige ligar al cliente real que lo pidió, para trazabilidad interna
    $clienteSolicitoId = null;
    if (strtoupper(trim($d['receptor_rfc'])) === 'XAXX010101000') {
        $clienteSolicitoId = (int)($d['cliente_solicito_id'] ?? 0);
        if (!$clienteSolicitoId) {
            jsonResponse(['ok'=>false,'error'=>'Facturas a Público en General (RFC XAXX010101000) requieren ligar al cliente real que lo solicitó — activa la casilla "Facturar a Público en General" en el modal y selecciónalo ahí, en vez de escribir ese RFC directo en el campo.']); exit;
        }
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id=?");
        $stmt->execute([$clienteSolicitoId]);
        if (!$stmt->fetchColumn()) {
            jsonResponse(['ok'=>false,'error'=>'El cliente que solicitó Público en General no existe']); exit;
        }
    }

    // Folio interno: obtener siguiente número
    $serie = preg_replace('/[^A-Z0-9]/', '', strtoupper($d['serie'] ?? 'A'));
    if (!$serie) $serie = 'A';

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

    $ordenFolio = trim($d['orden_folio'] ?? '') ?: null;

    $id = isset($d['id']) ? (int)$d['id'] : 0;

    if ($id) {
        // Actualizar borrador existente — verificar propiedad
        $stmt = $pdo->prepare("
            UPDATE facturas SET
                tipo_cfdi=?, fecha=?, orden_folio=?, receptor_nombre=?, receptor_rfc=?,
                receptor_cp=?, receptor_regimen=?, receptor_uso_cfdi=?,
                receptor_email=?, cliente_solicito_id=?, forma_pago=?, metodo_pago=?,
                conceptos=?, subtotal=?, iva=?, total=?, updated_at=NOW()
            WHERE id=? AND estatus='borrador' AND creado_por=?
        ");
        $stmt->execute([
            $d['tipo_cfdi'] ?? 'I', $d['fecha'] ?? date('Y-m-d'), $ordenFolio,
            $d['receptor_nombre'], $d['receptor_rfc'], $d['receptor_cp'],
            $d['receptor_regimen'], $d['receptor_uso_cfdi'],
            $d['receptor_email'] ?? null, $clienteSolicitoId, $d['forma_pago'], $d['metodo_pago'],
            json_encode($d['conceptos']), $sub, $iva, $total, $id, $user['nombre']
        ]);
        $stmt = $pdo->prepare("SELECT folio_interno FROM facturas WHERE id=?");
        $stmt->execute([$id]);
        $folioInterno = $stmt->fetchColumn();
        jsonResponse(['ok'=>true,'id'=>$id,'folio'=>$folioInterno,'total'=>$total]);
    } else {
        // Nueva factura — reintenta si otra petición concurrente ya tomó el folio calculado
        // (UNIQUE KEY uq_serie_folio en BD es la garantía real; el retry aquí solo evita
        // que el usuario vea un error por una colisión que la mayoría de las veces se resuelve sola).
        $intentos = 0;
        while (true) {
            $intentos++;
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(folio_numero),0)+1 FROM facturas WHERE serie=?");
            $stmt->execute([$serie]);
            $folioNum     = (int)$stmt->fetchColumn();
            $folioInterno = $serie . '-' . str_pad($folioNum, 3, '0', STR_PAD_LEFT);
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO facturas
                        (folio_interno, serie, folio_numero, orden_folio, tipo_cfdi, fecha,
                         receptor_nombre, receptor_rfc, receptor_cp, receptor_regimen,
                         receptor_uso_cfdi, receptor_email, cliente_solicito_id, forma_pago, metodo_pago,
                         conceptos, subtotal, iva, total, estatus, modo, creado_por)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'borrador',?,?)
                ");
                $stmt->execute([
                    $folioInterno, $serie, $folioNum, $ordenFolio,
                    $d['tipo_cfdi'] ?? 'I', $d['fecha'] ?? date('Y-m-d'),
                    $d['receptor_nombre'], $d['receptor_rfc'], $d['receptor_cp'],
                    $d['receptor_regimen'], $d['receptor_uso_cfdi'],
                    $d['receptor_email'] ?? null, $clienteSolicitoId, $d['forma_pago'], $d['metodo_pago'],
                    json_encode($d['conceptos']), $sub, $iva, $total,
                    FACTURAPI_MODE, $user['nombre']
                ]);
                break;
            } catch (PDOException $e) {
                // 23000 = violación de UNIQUE KEY (otra petición tomó este folio_numero primero)
                if ($e->getCode() === '23000' && $intentos < 5) continue;
                throw $e;
            }
        }
        $newId = $pdo->lastInsertId();
        jsonResponse(['ok'=>true,'id'=>$newId,'folio'=>$folioInterno,'total'=>$total]);
    }
    exit;
}

// ── POST timbrar ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'timbrar') {
    $d  = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }

    // Cargar factura — verificar propiedad
    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? AND estatus='borrador' AND creado_por=?");
    $stmt->execute([$id, $user['nombre']]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac) { jsonResponse(['ok'=>false,'error'=>'Factura no encontrada o ya timbrada']); exit; }

    $conceptos = json_decode($fac['conceptos'], true);

    // Bloquear timbrado si algún concepto no trae una clave SAT real asignada —
    // '01010101' es la clave de "no existe en catálogo", solo válida para pruebas sandbox
    foreach ($conceptos as $i => $c) {
        $clave = trim($c['clave'] ?? '');
        if ($clave === '' || $clave === '01010101') {
            jsonResponse(['ok'=>false,'error'=>'El concepto "'.($c['desc'] ?: ($i+1)).'" no tiene una clave SAT válida asignada. Asígnala antes de timbrar.']);
            exit;
        }
    }

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

    // receptor_email puede traer varios correos separados por coma (ver accion=guardar) — FacturAPI
    // solo soporta un correo en customer.email (lo usa para su propia notificación al PAC, no es dato
    // fiscal del CFDI), así que le mandamos solo el primero; el envío real a TODOS los correos ocurre
    // más abajo vía nuestro propio SMTP (enviarCorreoFactura), que sí soporta lista completa.
    $correosFactura = _correosValidos($fac['receptor_email'] ?? '');

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
            'email'       => $correosFactura[0] ?? null,
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
    unset($ch);

    if ($curlErr) {
        jsonResponse(['ok'=>false,'error'=>'Error de conexión con FacturAPI: '.$curlErr]);
        exit;
    }

    $res = json_decode($response, true);

    if ($httpCode !== 200) {
        $msg = $res['message'] ?? $res['error'] ?? 'Error desconocido de FacturAPI';
        error_log('APEX FacturAPI error '.$httpCode.': '.$response);
        jsonResponse(['ok'=>false,'error'=>'FacturAPI: '.$msg]);
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

    // Envío de PDF+XML a todos los correos capturados — best-effort, nunca bloquea la respuesta de
    // timbrado (la factura ya quedó timbrada ante el SAT independientemente de si el correo se logra enviar).
    $correoEnviado = false;
    if ($correosFactura) {
        $pdfBin = _descargarArchivoFacturapi($pdfUrl);
        $xmlBin = _descargarArchivoFacturapi($xmlUrl);

        $facParaCorreo = ['folio_interno'=>$fac['folio_interno'], 'receptor_nombre'=>$fac['receptor_nombre'], 'total'=>$fac['total']];
        $resCorreo = enviarCorreoFactura($facParaCorreo, $correosFactura, $pdfBin, $xmlBin);
        $correoEnviado = $resCorreo['ok'];
        if (!$correoEnviado) error_log('APEX Facturacion: no se pudo enviar correo de factura id='.$id.': '.($resCorreo['error'] ?? ''));
    }

    jsonResponse([
        'ok'             => true,
        'uuid'           => $uuid,
        'facturapi_id'   => $facurapiId,
        'pdf_url'        => $pdfUrl,
        'xml_url'        => $xmlUrl,
        'modo'           => FACTURAPI_MODE,
        'correo_enviado' => $correoEnviado,
    ]);
    exit;
}

// ── POST reenviar_correo (timbrada → reenvía PDF+XML a los correos capturados o a una lista puntual) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'reenviar_correo') {
    $d  = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? AND estatus='timbrada' AND creado_por=?");
    $stmt->execute([$id, $user['nombre']]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac) { jsonResponse(['ok'=>false,'error'=>'Factura no encontrada o no está timbrada']); exit; }

    // Por default reenvía a los correos ya guardados en la factura; si mandan "correos" en el body
    // (ej. el usuario quiere agregar uno puntual sin editar el registro), se usan esos en su lugar.
    $listaRaw  = trim($d['correos'] ?? '') !== '' ? $d['correos'] : ($fac['receptor_email'] ?? '');
    $correos   = _correosValidos($listaRaw);
    if (!$correos) { jsonResponse(['ok'=>false,'error'=>'No hay correos válidos para reenviar']); exit; }

    if (!$fac['facturapi_id']) { jsonResponse(['ok'=>false,'error'=>'Esta factura no tiene PDF/XML en FacturAPI']); exit; }

    $pdfBin = _descargarArchivoFacturapi($fac['pdf_url']);
    $xmlBin = _descargarArchivoFacturapi($fac['xml_url']);
    if (!$pdfBin && !$xmlBin) { jsonResponse(['ok'=>false,'error'=>'No se pudo descargar el PDF ni el XML de FacturAPI']); exit; }

    $facParaCorreo = ['folio_interno'=>$fac['folio_interno'], 'receptor_nombre'=>$fac['receptor_nombre'], 'total'=>$fac['total']];
    $resCorreo = enviarCorreoFactura($facParaCorreo, $correos, $pdfBin, $xmlBin);
    if (!$resCorreo['ok']) { jsonResponse(['ok'=>false,'error'=>$resCorreo['error'] ?? 'Error al enviar el correo']); exit; }

    jsonResponse(['ok'=>true, 'correos'=>$correos]);
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
    unset($ch);

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
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }

    // Verificar propiedad antes de eliminar
    $stmt = $pdo->prepare("DELETE FROM facturas WHERE id=? AND creado_por=? AND (estatus='borrador' OR (estatus='timbrada' AND modo='test'))");
    $stmt->execute([$id, $user['nombre']]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['ok'=>false,'error'=>'No se puede eliminar: no existe o es una factura timbrada en producción']);
        exit;
    }
    jsonResponse(['ok'=>true]);
    exit;
}

// ── POST cancelar (timbrada → cancelada, vía FacturAPI) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'cancelar') {
    $d           = json_decode(file_get_contents('php://input'), true);
    $id          = (int)($d['id'] ?? 0);
    $motivo      = $d['motivo'] ?? '';
    $substitucion = trim($d['substitution'] ?? '');
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }
    if (!in_array($motivo, ['01','02','03','04'], true)) {
        jsonResponse(['ok'=>false,'error'=>'Motivo de cancelación inválido']); exit;
    }
    // El SAT exige el UUID de la factura sustituta cuando el motivo es 01 (se factura la corrección
    // ANTES de cancelar la original, y se referencia aquí para dejar el rastro de sustitución).
    if ($motivo === '01' && $substitucion === '') {
        jsonResponse(['ok'=>false,'error'=>'Motivo 01 requiere el UUID de la factura sustituta']); exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? AND estatus='timbrada' AND creado_por=?");
    $stmt->execute([$id, $user['nombre']]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac) { jsonResponse(['ok'=>false,'error'=>'Factura no encontrada o no está timbrada']); exit; }

    // FacturAPI espera motive/substitution como query string, no en el body (confirmado contra la API real: con
    // POSTFIELDS respondía "motive is required" con location:"query" en el error).
    $url = 'https://www.facturapi.io/v2/invoices/' . $fac['facturapi_id'] . '?motive=' . urlencode($motivo);
    if ($motivo === '01') {
        $url .= '&substitution=' . urlencode($substitucion);
    }
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_CUSTOMREQUEST   => 'DELETE',
        CURLOPT_HTTPHEADER      => [
            'Authorization: Bearer ' . FACTURAPI_KEY,
        ],
        CURLOPT_TIMEOUT         => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    unset($ch);

    if ($curlErr) {
        jsonResponse(['ok'=>false,'error'=>'Error de conexión con FacturAPI: '.$curlErr]);
        exit;
    }

    $res = json_decode($response, true);

    if ($httpCode !== 200) {
        $msg = $res['message'] ?? $res['error'] ?? 'Error desconocido de FacturAPI';
        error_log('APEX FacturAPI cancelar error '.$httpCode.': '.$response);
        jsonResponse(['ok'=>false,'error'=>'FacturAPI: '.$msg]);
        exit;
    }

    // FacturAPI puede regresar la cancelación en 'pending' cuando el SAT exige que el receptor la acepte
    // en su buzón (factura >$1,000 MXN o después de 72hrs) — en ese caso NO está cancelada todavía de
    // verdad, aunque la llamada haya sido exitosa (HTTP 200). Solo marcamos estatus='cancelada' en firme
    // cuando FacturAPI confirma 'canceled'; si no, se queda 'timbrada' con pac_cancel_status='pending'
    // para poder verificarla después (accion=verificar_cancelacion) y reflejar el estado real.
    $pacStatus = $res['status'] ?? 'canceled';
    $esFirme   = ($pacStatus === 'canceled');

    $stmt = $pdo->prepare("
        UPDATE facturas SET
            estatus=?, motivo_cancel=?, sustituye_uuid=?, pac_cancel_status=?, updated_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([
        $esFirme ? 'cancelada' : 'timbrada',
        $motivo,
        $motivo === '01' ? $substitucion : null,
        $pacStatus,
        $id
    ]);

    jsonResponse(['ok'=>true, 'estatus'=>$pacStatus, 'firme'=>$esFirme]);
    exit;
}

// ── GET verificar_cancelacion (re-consulta a FacturAPI el estatus real de una cancelación 'pending') ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'verificar_cancelacion') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? AND creado_por=? AND pac_cancel_status IS NOT NULL");
    $stmt->execute([$id, $user['nombre']]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac) { jsonResponse(['ok'=>false,'error'=>'Factura no encontrada o sin cancelación en trámite']); exit; }

    $ch = curl_init('https://www.facturapi.io/v2/invoices/' . $fac['facturapi_id']);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . FACTURAPI_KEY],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($httpCode !== 200) { jsonResponse(['ok'=>false,'error'=>'No se pudo consultar FacturAPI']); exit; }

    $res       = json_decode($response, true);
    $pacStatus = $res['status'] ?? $fac['pac_cancel_status'];
    $esFirme   = ($pacStatus === 'canceled');

    $stmt = $pdo->prepare("UPDATE facturas SET estatus=?, pac_cancel_status=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$esFirme ? 'cancelada' : 'timbrada', $pacStatus, $id]);

    jsonResponse(['ok'=>true, 'estatus'=>$pacStatus, 'firme'=>$esFirme]);
    exit;
}

jsonResponse(['ok'=>false,'error'=>'Acción no válida'], 400);
