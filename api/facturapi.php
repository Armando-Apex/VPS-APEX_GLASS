<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/helpers/facturapi_lib.php';

header('Content-Type: application/json');

// S2-c/S2-d: permiso propio en vez de 'ver_wip' (cajon de sastre de modulos WIP) —
// asi administracion (Lina, que lleva cobranza) puede facturar sin heredar todo lo WIP.
$user = requirePermisoApi('facturar');
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

    // Se avisa aquí, antes de que el usuario capture nada, no hasta el timbrado.
    if ($motivoNo = _facturapiOrdenNoFacturable($pdo, $orden['folio'])) {
        jsonResponse(['ok'=>false,'error'=>$motivoNo]); exit;
    }

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
    $conceptos = _facturapiConceptosDesdeOrden($pdo, $orden['folio']) ?? [];

    // Estado de cobro (29-sep-2026): define PUE/PPD y si Público en General ya se puede
    // timbrar. Y aviso de venta de un mes anterior: facturarla ahora afecta un mes que el
    // contador probablemente ya declaró.
    $cobro = _facturapiSaldoOrden($pdo, $orden['folio']);
    if ($cobro) $cobro['liquidada'] = _facturapiOrdenLiquidada($pdo, $orden['folio']);
    $stmt = $pdo->prepare("SELECT vobo_at FROM cotizaciones WHERE orden_id = ? LIMIT 1");
    $stmt->execute([$orden['id']]);
    $voboAt = $stmt->fetchColumn() ?: null;
    $avisoMes = null;
    if ($voboAt && substr($voboAt, 0, 7) < date('Y-m')) {
        $avisoMes = 'Esta venta es de ' . substr($voboAt, 0, 7) . ' (VoBo del ' . date('d/m/Y', strtotime($voboAt))
            . '). Facturarla en ' . date('m/Y') . ' puede afectar un mes que ya se declaró: confírmalo con el contador.';
    }

    // Anticipos (esquema A): se avisa desde aquí cómo va a quedar la factura.
    $avisoAnt = null;
    $antO = _facturapiAnticiposDeOrden($pdo, $orden['folio']);
    $avisosExtra = [];
    if ($antO['referido'] > 0.004) {
        $avisosExtra[] = 'Incluye $' . number_format($antO['referido'], 2) . ' de bono de referido: va como descuento dentro de la factura (el total del CFDI baja en ese monto).';
    }
    if ($antO['anterior'] > 0.004) {
        $avisosExtra[] = 'Incluye $' . number_format($antO['anterior'], 2) . ' de saldo a favor anterior al 01/10/2026 (nunca se facturó como anticipo): la factura va normal, con la forma de pago con la que el cliente pagó originalmente ese dinero. Confírmalo con el contador.';
    }
    if ($antO['aplicado'] > 0.004) {
        $avisosExtra[] = 'Con saldo a favor aplicado se factura en PUE cuando la orden esté liquidada.';
    }
    if ($antO['sin_facturar']) {
        $avisoAnt = 'Se pagó con saldo a favor de un depósito cuyo anticipo todavía no se factura: primero factúralo en "Anticipos por facturar".';
    } elseif ($antO['anticipos']) {
        $avisoAnt = 'Se pagó $' . number_format($antO['total_anticipos'], 2) . ' con anticipo ya facturado ('
            . implode(', ', array_column($antO['anticipos'], 'folio')) . '): la factura va por el total, en PUE, con forma de pago '
            . implode(' o ', _facturapiFormasValidasConAnticipo($pdo, $orden['folio']))
            . ' (la del pago en dinero de mayor importe; 30 solo si el anticipo cubrió todo) y al timbrarla se emite sola la nota de crédito con forma 30.';
    }

    if ($avisosExtra) $avisoAnt = trim(($avisoAnt ?? '') . ' ' . implode(' ', $avisosExtra));

    jsonResponse(['ok'=>true, 'orden'=>$orden, 'cliente'=>$cliente, 'conceptos'=>$conceptos,
                  'cobro'=>$cobro, 'aviso_mes'=>$avisoMes, 'aviso_anticipo'=>$avisoAnt]);
    exit;
}

// ── GET lista ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'lista') {
    $rows = $pdo->query("
        SELECT f.id, f.folio_interno, f.serie, f.folio_numero, f.orden_folio, f.saldo_favor_id, f.tipo_cfdi, f.fecha,
               f.receptor_nombre, f.receptor_rfc, f.receptor_cp, f.receptor_regimen,
               f.receptor_uso_cfdi, f.receptor_email, f.cliente_solicito_id,
               COALESCE(NULLIF(cs.razon_social,''), cs.nombre) AS cliente_solicito_nombre,
               f.forma_pago, f.metodo_pago,
               f.conceptos, f.subtotal, f.iva, f.total,
               f.estatus, f.uuid, f.modo, f.pdf_url, f.motivo_cancel, f.sustituye_uuid, f.pac_cancel_status, f.created_at,
               f.verification_url, f.fecha_timbrado, f.timbrado_por, f.cancelado_por, f.cancelado_at,
               (f.xml_path IS NOT NULL) AS tiene_resguardo, f.creado_por,
               f.global_periodicidad, f.global_meses, f.global_anio,
               f.relacion_tipo, f.relacion_uuid,
               (SELECT COUNT(*) FROM facturas_anticipos fa WHERE fa.factura_id = f.id) AS anticipos_aplicados,
               (SELECT x.folio_interno FROM facturas_anticipos fa2 JOIN facturas x ON x.id = fa2.nota_credito_id
                  WHERE fa2.factura_id = f.id AND x.estatus IN ('timbrada','timbrando') LIMIT 1) AS nota_credito_folio,
               fp.parcialidad AS cp_parcialidad, fp.monto AS cp_monto, fp.saldo_anterior AS cp_saldo_anterior,
               fp.saldo_insoluto AS cp_saldo_insoluto, fp.fecha_pago AS cp_fecha_pago, fp.forma_pago AS cp_forma_pago,
               fr.id AS cp_factura_id, fr.folio_interno AS cp_factura_folio, fr.orden_folio AS cp_orden_folio, fr.uuid AS cp_factura_uuid
        FROM facturas f
        LEFT JOIN clientes cs ON cs.id = f.cliente_solicito_id
        LEFT JOIN facturas_pagos fp ON fp.complemento_id = f.id
        LEFT JOIN facturas fr ON fr.id = fp.factura_id
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

    // Uso de CFDI, régimen y serie se validaban solo en la UI: la columna es texto libre y
    // el POST se puede mandar a mano, así que un valor fuera de catálogo se guardaba y solo
    // reventaba hasta el timbrado (o peor, se colaba). Se valida contra el mismo catálogo
    // que ofrece el formulario. Si el SAT amplía el catálogo, hay que agregarlo en ambos.
    $USOS_CFDI = ['G01','G02','G03','I01','I02','I03','I04','I08','CP01','S01'];
    if (!in_array(strtoupper(trim($d['receptor_uso_cfdi'])), $USOS_CFDI, true)) {
        jsonResponse(['ok'=>false,'error'=>'Uso de CFDI fuera de catálogo: '.$d['receptor_uso_cfdi']]); exit;
    }
    $REGIMENES = ['601','603','605','606','607','608','610','611','612','614','615','616','620','621','622','623','624','625','626'];
    if (!in_array(trim($d['receptor_regimen']), $REGIMENES, true)) {
        jsonResponse(['ok'=>false,'error'=>'Régimen fiscal fuera de catálogo: '.$d['receptor_regimen']]); exit;
    }
    if (!in_array($d['metodo_pago'], ['PUE','PPD'], true)) {
        jsonResponse(['ok'=>false,'error'=>'Método de pago inválido']); exit;
    }
    if (!preg_match('/^(0[1-9]|1[0-7]|2[0-9]|3[01]|99)$/', (string)$d['forma_pago'])) {
        jsonResponse(['ok'=>false,'error'=>'Forma de pago fuera de catálogo: '.$d['forma_pago']]); exit;
    }
    // SAT (Anexo 20, CFDI 4.0): con PPD la forma de pago debe ser 99 "Por definir"; la
    // forma real de cada abono se declara en su Complemento de Pago.
    if ($d['metodo_pago'] === 'PPD' && (string)$d['forma_pago'] !== '99') {
        jsonResponse(['ok'=>false,'error'=>'Con método de pago PPD el SAT exige forma de pago 99 (Por definir). La forma real de cada abono va en su Complemento de Pago.']); exit;
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

    // Información Global: el SAT la exige cuando el receptor es el RFC genérico con nombre
    // "PUBLICO EN GENERAL" — sin ella el PAC rechaza el timbrado con CFDI40130 (comprobado
    // en vivo el 26-sep-2026). Se valida al guardar para no dejar pasar un borrador que
    // después sea imposible de timbrar.
    $globalPer = null; $globalMes = null; $globalAnio = null;
    if (strtoupper(trim($d['receptor_rfc'])) === 'XAXX010101000') {
        $globalPer = trim($d['global_periodicidad'] ?? '');
        $globalMes = trim($d['global_meses'] ?? '');
        $globalAnio = (int)($d['global_anio'] ?? 0);
        // FacturAPI solo acepta estos 4 valores (probados uno por uno contra la API real);
        // el bimestral que sí contempla el SAT no lo soporta.
        if (!in_array($globalPer, ['day','week','fortnight','month'], true)) {
            jsonResponse(['ok'=>false,'error'=>'Las facturas a Público en General requieren la periodicidad del periodo que agrupan (diaria, semanal, quincenal o mensual).']); exit;
        }
        if (!preg_match('/^(0[1-9]|1[0-2])$/', $globalMes)) {
            jsonResponse(['ok'=>false,'error'=>'Mes inválido para la información global (01 a 12).']); exit;
        }
        if ($globalAnio < 2020 || $globalAnio > 2099) {
            jsonResponse(['ok'=>false,'error'=>'Año inválido para la información global.']); exit;
        }
        // SAT: la factura global (Público en General) solo admite PUE y una forma de pago
        // real — se factura cuando la venta ya está cobrada completa (candado al timbrar).
        if ($d['metodo_pago'] !== 'PUE') {
            jsonResponse(['ok'=>false,'error'=>'Una factura a Público en General debe ser PUE (Pago en una sola exhibición): se timbra hasta que la orden esté pagada al 100%.']); exit;
        }
        if ((string)$d['forma_pago'] === '99') {
            jsonResponse(['ok'=>false,'error'=>'Una factura a Público en General necesita la forma de pago real con la que se liquidó (no 99).']); exit;
        }
    }

    // CFDI relacionados (nodo CfdiRelacionados). Hasta hoy el payload NUNCA los mandaba, así
    // que el flujo de refacturación quedaba a medias: se podía cancelar con motivo 01
    // (sustitución) apuntando a la nueva, pero la nueva no declaraba de qué era sustitución.
    // Nombre y forma confirmados sondeando la API real (26-sep-2026): el campo es
    // 'related_documents', con 'relationship' (código del SAT) y 'documents' (arreglo de UUID).
    // Catálogo c_TipoRelacion del SAT.
    $RELACIONES = ['01','02','03','04','05','06','07'];
    $relTipo = trim($d['relacion_tipo'] ?? '') ?: null;
    $relUuid = strtoupper(trim($d['relacion_uuid'] ?? '')) ?: null;
    if ($relTipo !== null || $relUuid !== null) {
        if (!in_array($relTipo, $RELACIONES, true)) {
            jsonResponse(['ok'=>false,'error'=>'Tipo de relación de CFDI fuera de catálogo: '.$relTipo]); exit;
        }
        if (!preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/', (string)$relUuid)) {
            jsonResponse(['ok'=>false,'error'=>'El UUID del CFDI relacionado no tiene formato válido.']); exit;
        }
        // Debe ser un CFDI que realmente emitimos: relacionar a un UUID ajeno o inventado
        // produce un comprobante que el SAT rechaza o que no se puede rastrear.
        $stmtRel = $pdo->prepare("SELECT folio_interno, estatus FROM facturas WHERE uuid = ? LIMIT 1");
        $stmtRel->execute([$relUuid]);
        if (!$stmtRel->fetch(PDO::FETCH_ASSOC)) {
            jsonResponse(['ok'=>false,'error'=>'No tenemos ninguna factura con ese UUID. Verifica el UUID del CFDI que se va a relacionar.']); exit;
        }
    }

    // Folio interno: obtener siguiente número
    // La serie se ocultó de la UI (siempre 'A') pero el endpoint aceptaba cualquier valor,
    // lo que permitiría abrir numeraciones paralelas por accidente o a mano. Se acota a las
    // series dadas de alta; para agregar una nueva hay que ponerla aquí a propósito.
    $SERIES_VALIDAS = ['A'];
    $serie = preg_replace('/[^A-Z0-9]/', '', strtoupper($d['serie'] ?? 'A'));
    if (!$serie) $serie = 'A';
    if (!in_array($serie, $SERIES_VALIDAS, true)) {
        jsonResponse(['ok'=>false,'error'=>'Serie no válida: '.$serie.'. Series dadas de alta: '.implode(', ', $SERIES_VALIDAS)]); exit;
    }

    // S2-03: Calcular totales (no confiar en el cliente) — IVA a 2 decimales por
    // línea (no acumulado a 6 y redondeado al final): el importe de cada línea se
    // redondea primero a 2 decimales (igual que FacturAPI/el SAT hacen por
    // concepto), y el IVA se calcula sobre la base gravable ya redondeada — evita
    // que el total quede desfasado en centavos contra lo que el PAC realmente timbra.
    // Seguridad (29-sep-2026): los conceptos sin orden se guardaban tal como llegaban del
    // navegador — texto con comillas/HTML en la descripción terminaba ejecutándose en la
    // pantalla de quien abría el borrador. Se normalizan a tipos y formatos estrictos
    // (la pantalla además escapa al mostrar). Con orden, más abajo se sustituyen por los
    // del servidor de todos modos.
    if (!is_array($d['conceptos']) || !$d['conceptos'] || count($d['conceptos']) > 200) {
        jsonResponse(['ok'=>false,'error'=>'Conceptos inválidos']); exit;
    }
    $conceptosLimpios = [];
    foreach (array_values($d['conceptos']) as $c) {
        if (!is_array($c)) { jsonResponse(['ok'=>false,'error'=>'Conceptos inválidos']); exit; }
        $desc = preg_replace('/[\x00-\x1F\x7F<>]/u', ' ', (string)($c['desc'] ?? ''));
        $desc = trim(preg_replace('/\s+/u', ' ', $desc));
        $clave  = strtoupper(trim((string)($c['clave']  ?? '')));
        $unidad = strtoupper(trim((string)($c['unidad'] ?? '')));
        $conceptosLimpios[] = [
            'desc'   => mb_substr($desc, 0, 1000),
            'clave'  => preg_match('/^[0-9]{8}$/', $clave) ? $clave : '',
            'unidad' => preg_match('/^[A-Z0-9]{1,3}$/', $unidad) ? $unidad : '',
            'cant'   => (float)($c['cant'] ?? 0),
            'precio' => (float)($c['precio'] ?? 0),
            'iva'    => !empty($c['iva']),
        ];
    }
    $d['conceptos'] = $conceptosLimpios;

    $sub = 0; $subGravable = 0;
    foreach ($d['conceptos'] as $c) {
        $imp = round((float)($c['cant'] ?? 0) * (float)($c['precio'] ?? 0), 2);
        $sub += $imp;
        if (!empty($c['iva'])) $subGravable += $imp;
    }
    $sub   = round($sub, 2);
    $iva   = round($subGravable * 0.16, 2);
    $total = round($sub + $iva, 2);

    $ordenFolio = trim($d['orden_folio'] ?? '') ?: null;

    // Defensa en profundidad: el aviso de buscar_orden es UI, y este POST se puede mandar
    // a mano. Se revalida aquí para no guardar un borrador imposible/indebido de timbrar.
    if ($ordenFolio && ($motivoNo = _facturapiOrdenNoFacturable($pdo, $ordenFolio))) {
        jsonResponse(['ok'=>false,'error'=>$motivoNo]); exit;
    }

    // El receptor NO tiene por qué ser siempre el cliente de la orden (pasa que un cliente
    // pide que se facture a su empresa, o a Público en General), así que esto NO bloquea:
    // solo exige confirmación explícita la primera vez. El riesgo real es el silencio —
    // con el receptor ya cargado de otra búsqueda, un folio mal tecleado emite el CFDI de
    // la orden de un cliente a nombre de otro sin que nada lo advierta.
    $avisoReceptor = null;
    if ($ordenFolio && strtoupper(trim($d['receptor_rfc'])) !== 'XAXX010101000' && empty($d['confirmar_receptor_distinto'])) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(NULLIF(cl.razon_social,''), cl.nombre) AS nombre_fiscal, cl.rfc
            FROM ordenes o LEFT JOIN clientes cl ON cl.id = o.cliente_id
            WHERE o.folio = ? LIMIT 1
        ");
        $stmt->execute([$ordenFolio]);
        $cliOrden = $stmt->fetch(PDO::FETCH_ASSOC);
        $rfcOrden = strtoupper(trim((string)($cliOrden['rfc'] ?? '')));
        $rfcRecep = strtoupper(trim($d['receptor_rfc']));
        if ($rfcOrden !== '' && $rfcOrden !== $rfcRecep) {
            jsonResponse([
                'ok'                => false,
                'requiere_confirmar'=> 'receptor_distinto',
                'error'             => 'El receptor de esta factura no es el cliente de la orden ' . $ordenFolio . '.'
                    . "\n\nCliente de la orden: " . ($cliOrden['nombre_fiscal'] ?? '(sin nombre)') . ' (' . $rfcOrden . ')'
                    . "\nReceptor capturado: " . $d['receptor_nombre'] . ' (' . $rfcRecep . ')'
                    . "\n\n¿Es correcto? Suele serlo cuando el cliente pide facturar a otra razón social.",
            ]); exit;
        }
    }

    // BLV-2: los conceptos que se TIMBRAN nunca deben ser los que mandó el navegador
    // cuando hay una orden real detrás — antes solo se validaba que el TOTAL cuadrara
    // (±$0.01) pero se guardaba json_encode($d['conceptos']) tal cual, así que una
    // descripción/cantidad/precio de línea editada a mano pasaba sin detectarse
    // mientras el total no cambiara. Ahora, con orden_folio, los conceptos y los
    // totales que se guardan SIEMPRE son los reconstruidos en servidor
    // ($conceptosServidor/$totalSrv) — el total del body solo se usa para el mensaje
    // de error si no coincide (para que el usuario sepa que su edición se descartó).
    $conceptosParaGuardar = $d['conceptos'];
    if ($ordenFolio) {
        $conceptosServidor = _facturapiConceptosDesdeOrden($pdo, $ordenFolio);
        if ($conceptosServidor === null) {
            jsonResponse(['ok'=>false,'error'=>'No se encontró la orden/cotización de origen para el folio '.$ordenFolio.'. Vuelve a buscar la orden antes de guardar.']); exit;
        }
        $subSrv = 0; $subGravSrv = 0;
        foreach ($conceptosServidor as $c) {
            $imp = round((float)$c['cant'] * (float)$c['precio'], 2);
            $subSrv += $imp;
            if (!empty($c['iva'])) $subGravSrv += $imp;
        }
        $subSrv    = round($subSrv, 2);
        $ivaSrv    = round($subGravSrv * 0.16, 2);
        $totalSrv  = round($subSrv + $ivaSrv, 2);
        if (abs($totalSrv - $total) > 0.01) {
            jsonResponse(['ok'=>false,'error'=>'Los conceptos no coinciden con la cotización de la orden '.$ordenFolio
                .' (esperado $'.number_format($totalSrv,2).', recibido $'.number_format($total,2)
                .'). Vuelve a cargar la orden con "Buscar" antes de guardar.']); exit;
        }
        // El total puede cuadrar (±$0.01) aunque las líneas se hayan editado — se
        // guardan los conceptos y totales del servidor siempre, no los del body.
        //
        // F-1: EXCEPCIÓN deliberada a lo de arriba — la clave SAT y la unidad SÍ se
        // conservan de lo que mandó el navegador. No son dinero: son catalogación
        // fiscal que el usuario escoge en el modal y que la reconstrucción de servidor
        // todavía no sabe resolver sola (devuelve clave vacía — ver
        // _facturapiConceptosDesdeOrden). Sin esto la clave escogida se perdía al
        // guardar y el candado de timbrado ("no tiene clave SAT válida") bloqueaba
        // SIEMPRE cualquier factura ligada a una orden: el flujo estaba roto de punta
        // a punta. Los montos siguen siendo 100% del servidor, no se relaja nada de BLV-2.
        // Solo se conservan si el número de líneas coincide: si el usuario agregó o
        // quitó renglones los índices ya no son comparables, y se dejan vacías para que
        // el candado le pida asignarlas de nuevo (falla cerrado, no abierto).
        // Cuando exista el catálogo de claves por cristal/servicio, este bloque sobra.
        $conceptosBody = is_array($d['conceptos']) ? array_values($d['conceptos']) : [];
        if (count($conceptosServidor) === count($conceptosBody)) {
            foreach ($conceptosServidor as $i => $_cs) {
                $claveUsuario  = strtoupper(trim((string)($conceptosBody[$i]['clave']  ?? '')));
                $unidadUsuario = strtoupper(trim((string)($conceptosBody[$i]['unidad'] ?? '')));
                // Formato de catálogo SAT: clave de producto = 8 dígitos exactos,
                // clave de unidad = 1 a 3 alfanuméricos. Cualquier otra cosa se ignora.
                if (preg_match('/^[0-9]{8}$/', $claveUsuario)) {
                    $conceptosServidor[$i]['clave'] = $claveUsuario;
                }
                if (preg_match('/^[A-Z0-9]{1,3}$/', $unidadUsuario)) {
                    $conceptosServidor[$i]['unidad'] = $unidadUsuario;
                }
            }
        }
        $conceptosParaGuardar = $conceptosServidor;
        $sub   = $subSrv;
        $iva   = $ivaSrv;
        $total = $totalSrv;
    }

    $id = isset($d['id']) ? (int)$d['id'] : 0;

    if ($id) {
        // Actualizar borrador existente — verificar propiedad Y que siga en borrador.
        // Sin este chequeo el UPDATE afectaba 0 filas (factura ajena o ya timbrada)
        // y se respondía ok=true igual: el usuario creía haber guardado sus cambios (A-9a).
        // S2-c: la autorización es por PERMISO (requirePermisoApi al inicio del archivo),
        // ya no por 'creado_por = tu nombre'. Antes, un segundo dir_admin no podía tocar la
        // factura de un compañero ni en una urgencia, y si cambiaba el nombre de un usuario
        // sus facturas quedaban huérfanas para siempre (incluida la posibilidad de cancelarlas).
        // La trazabilidad no se pierde: creado_por se conserva y ahora además se registra
        // timbrado_por/cancelado_por con su fecha.
        $stmt = $pdo->prepare("SELECT folio_interno, saldo_favor_id FROM facturas WHERE id=? AND estatus='borrador'");
        $stmt->execute([$id]);
        $filaBorr = $stmt->fetch(PDO::FETCH_ASSOC);
        $folioInterno = $filaBorr['folio_interno'] ?? null;
        if (!$folioInterno) {
            jsonResponse(['ok'=>false,'error'=>'Factura no encontrada o ya timbrada']); exit;
        }
        // Una factura de anticipo se arma sola a partir del depósito (monto, forma, concepto
        // del SAT); editarla a mano rompería el cuadre con el dinero recibido.
        if (!empty($filaBorr['saldo_favor_id'])) {
            jsonResponse(['ok'=>false,'error'=>'Las facturas de anticipo no se editan. Elimina este borrador y vuelve a crearlo desde "Anticipos por facturar".']); exit;
        }

        $stmt = $pdo->prepare("
            UPDATE facturas SET
                tipo_cfdi=?, fecha=?, orden_folio=?, receptor_nombre=?, receptor_rfc=?,
                receptor_cp=?, receptor_regimen=?, receptor_uso_cfdi=?,
                receptor_email=?, cliente_solicito_id=?, forma_pago=?, metodo_pago=?,
                global_periodicidad=?, global_meses=?, global_anio=?,
                relacion_tipo=?, relacion_uuid=?,
                conceptos=?, subtotal=?, iva=?, total=?, updated_at=NOW()
            WHERE id=? AND estatus='borrador'
        ");
        $stmt->execute([
            $d['tipo_cfdi'] ?? 'I', $d['fecha'] ?? date('Y-m-d'), $ordenFolio,
            $d['receptor_nombre'], $d['receptor_rfc'], $d['receptor_cp'],
            $d['receptor_regimen'], $d['receptor_uso_cfdi'],
            $d['receptor_email'] ?? null, $clienteSolicitoId, $d['forma_pago'], $d['metodo_pago'],
            $globalPer, $globalMes, ($globalAnio ?: null),
            $relTipo, $relUuid,
            json_encode($conceptosParaGuardar), $sub, $iva, $total, $id
        ]);
        // rowCount()===0 aquí solo significa "guardó sin cambios" (PDO MySQL no cuenta
        // filas que quedaron idénticas) — la existencia/propiedad ya se verificó arriba.
        if ($stmt->rowCount() === 0) {
            jsonResponse(['ok'=>true,'id'=>$id,'folio'=>$folioInterno,'total'=>$total,'sin_cambios'=>true]);
        }
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
                         global_periodicidad, global_meses, global_anio,
                         relacion_tipo, relacion_uuid,
                         conceptos, subtotal, iva, total, estatus, modo, creado_por)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'borrador',?,?)
                ");
                $stmt->execute([
                    $folioInterno, $serie, $folioNum, $ordenFolio,
                    $d['tipo_cfdi'] ?? 'I', $d['fecha'] ?? date('Y-m-d'),
                    $d['receptor_nombre'], $d['receptor_rfc'], $d['receptor_cp'],
                    $d['receptor_regimen'], $d['receptor_uso_cfdi'],
                    $d['receptor_email'] ?? null, $clienteSolicitoId, $d['forma_pago'], $d['metodo_pago'],
                    $globalPer, $globalMes, ($globalAnio ?: null),
                    $relTipo, $relUuid,
                    json_encode($conceptosParaGuardar), $sub, $iva, $total,
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

    // Reserva atómica: solo UNA petición concurrente logra pasar la factura de
    // 'borrador' a 'timbrando' (rowCount=1); la otra recibe error y NUNCA llama al PAC.
    // Antes: dos pestañas pasaban el SELECT de 'borrador' y timbraban las dos →
    // dos CFDI válidos ante el SAT para la misma venta (A-9b).
    $stmt = $pdo->prepare("
        UPDATE facturas SET estatus='timbrando', updated_at=NOW()
        WHERE id=? AND estatus='borrador'
    ");
    $stmt->execute([$id]);
    if ($stmt->rowCount() !== 1) {
        jsonResponse(['ok'=>false,'error'=>'Factura no encontrada o ya timbrada']); exit;
    }

    // Libera la reserva y responde error — usar en TODO fallo anterior al timbrado
    // para que la factura no quede atascada en 'timbrando'.
    $abortar = function($msg) use ($pdo, $id) {
        $pdo->prepare("UPDATE facturas SET estatus='borrador' WHERE id=? AND estatus='timbrando'")->execute([$id]);
        jsonResponse(['ok'=>false,'error'=>$msg]); exit;
    };

    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=?");
    $stmt->execute([$id]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);

    // Una orden no debe tener dos CFDI activos: si ya existe otra factura timbrada
    // (o en proceso de timbrado) ligada al mismo folio de orden, se aborta.
    // Las canceladas no estorban (refacturación tras cancelación es flujo válido).
    // EXCEPCIÓN (29-sep-2026): una SUSTITUCIÓN (relación 04 al UUID de la vigente, p. ej.
    // Público en General → razón social). El SAT exige timbrar primero la nueva y después
    // cancelar la original con motivo 01 apuntando a ella; esa cancelación se hace sola
    // al terminar este timbrado ($facOriginalSustituir).
    $facOriginalSustituir = null;
    if (!empty($fac['orden_folio'])) {
        $stmt = $pdo->prepare("
            SELECT * FROM facturas
            WHERE orden_folio = ? AND id <> ? AND estatus IN ('timbrada','timbrando')
        ");
        $stmt->execute([$fac['orden_folio'], $id]);
        $otras = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($otras) {
            $o = $otras[0];
            $esSustitucion = count($otras) === 1 && $fac['relacion_tipo'] === '04'
                && $o['estatus'] === 'timbrada' && $o['pac_cancel_status'] !== 'pending'
                && strcasecmp((string)$o['uuid'], (string)$fac['relacion_uuid']) === 0;
            if (!$esSustitucion) {
                $abortar('La orden '.$fac['orden_folio'].' ya tiene la factura '.$o['folio_interno'].' timbrada. '
                    .'Para cambiarla usa "Refacturar" en esa factura (sustitución), o cancélala primero.');
            }
            $facOriginalSustituir = $o;
        }
    }

    // F-2: candado de servidor sobre el tipo de CFDI. El <select> del modal ya los
    // deshabilitó, pero eso es solo UI: cualquiera puede mandar el POST a mano. Los 3
    // tipos distintos de Ingreso se arman hoy como una factura normal y emitirían un
    // comprobante mal formado ante el SAT, así que se bloquean aquí también:
    //   E  → falta 'relations' con la relación 01 al UUID de la factura original
    //   P  → requiere type=payment + 'complements' (UUID, parcialidad, saldos), no items
    //   IG → requiere el nodo de información global (periodicidad/mes/año) y agrupar
    // Emitir mal un CFDI es mucho más caro que no emitirlo: se falla cerrado.
    $tiposBloqueados = [
        'E'  => 'Nota de Crédito: falta la relación al UUID de la factura original que exige el SAT.',
        'P'  => 'Complemento de Pago: falta el complemento ligado a los pagos de Cobranza.',
        'IG' => 'Factura Global: falta el nodo de información global del periodo.',
    ];
    if (isset($tiposBloqueados[$fac['tipo_cfdi']])) {
        $abortar('Este tipo de CFDI todavía no se puede timbrar — '.$tiposBloqueados[$fac['tipo_cfdi']]
            .' Por ahora solo se emiten facturas de tipo Ingreso (I).');
    }

    // Última revisión antes de emitir: entre guardar el borrador y timbrarlo la orden pudo
    // cancelarse o marcarse como retrabajo. Un CFDI emitido ya no se deshace, solo se cancela.
    if (!empty($fac['orden_folio']) && ($motivoNo = _facturapiOrdenNoFacturable($pdo, $fac['orden_folio']))) {
        $abortar($motivoNo);
    }

    // PPD solo aplica si al emitir todavía hay saldo por cobrar. Si la orden ya está pagada
    // completa, el SAT exige PUE — timbrar en PPD obligaría a emitir complementos de pagos
    // que ocurrieron antes de la factura (caso real A-002/S-931, 26-sep-2026).
    if ($fac['metodo_pago'] === 'PPD' && !empty($fac['orden_folio']) && _facturapiOrdenLiquidada($pdo, $fac['orden_folio'])) {
        $abortar('La orden '.$fac['orden_folio'].' ya está pagada completa, así que el método de pago debe ser PUE '
            .'(Pago en una sola exhibición), no PPD. Edita la factura, cambia el método de pago y vuelve a timbrar.');
    }

    // Borradores guardados antes de la validación de PPD→99 en guardar.
    if ($fac['metodo_pago'] === 'PPD' && (string)$fac['forma_pago'] !== '99') {
        $abortar('Con método de pago PPD el SAT exige forma de pago 99 (Por definir). Edita la factura y vuelve a timbrar.');
    }

    // Candados de cobranza (29-sep-2026).
    $esPublicoGeneral = (strtoupper(trim((string)$fac['receptor_rfc'])) === 'XAXX010101000');
    if ($esPublicoGeneral) {
        // Público en General solo con la venta cobrada completa, PUE y forma real.
        if ($fac['metodo_pago'] !== 'PUE' || (string)$fac['forma_pago'] === '99') {
            $abortar('Una factura a Público en General debe ser PUE con la forma de pago real (no 99). Edita la factura y vuelve a timbrar.');
        }
        if (!empty($fac['orden_folio']) && !_facturapiOrdenLiquidada($pdo, $fac['orden_folio'])) {
            $so = _facturapiSaldoOrden($pdo, $fac['orden_folio']);
            $abortar('La orden '.$fac['orden_folio'].' todavía tiene saldo pendiente'
                .($so ? ' de $'.number_format($so['saldo'], 2) : '').'. A Público en General solo se factura cuando está pagada al 100%.');
        }
    } elseif ($fac['metodo_pago'] === 'PUE' && !empty($fac['orden_folio']) && !_facturapiOrdenLiquidada($pdo, $fac['orden_folio'])) {
        // PUE declara que la venta ya está pagada: con saldo pendiente va en PPD, y cada
        // abono posterior genera su Complemento de Pago automático desde Cobranza.
        $so = _facturapiSaldoOrden($pdo, $fac['orden_folio']);
        $abortar('La orden '.$fac['orden_folio'].' tiene saldo pendiente'
            .($so ? ' de $'.number_format($so['saldo'], 2) : '').', así que la factura debe ser PPD (forma de pago 99). '
            .'Los complementos de cada abono se emiten solos al registrarlos en Cobranza.');
    }

    // Factura de anticipo (esquema A): el total debe ser exactamente el dinero del depósito
    // y un depósito solo puede tener un CFDI de anticipo vigente.
    if (!empty($fac['saldo_favor_id'])) {
        $stmt = $pdo->prepare("SELECT monto, tipo FROM clientes_saldo_favor WHERE id=?");
        $stmt->execute([(int)$fac['saldo_favor_id']]);
        $dep = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dep || $dep['tipo'] !== 'deposito') {
            $abortar('El depósito de saldo a favor de esta factura de anticipo ya no existe.');
        }
        if (abs((float)$dep['monto'] - (float)$fac['total']) > 0.005) {
            $abortar('El total de esta factura de anticipo no coincide con el depósito ($'.number_format((float)$dep['monto'], 2).'). Elimínala y vuelve a crearla.');
        }
        if ($otraAnt = _facturapiAnticipoVigente($pdo, $fac['saldo_favor_id'], $id)) {
            if ($otraAnt['estatus'] !== 'borrador') {
                $abortar('Este depósito ya tiene la factura de anticipo '.$otraAnt['folio_interno'].'.');
            }
        }
    }

    // Esquema A de anticipos (Fase 3): si la orden se pagó con saldo a favor que viene de un
    // anticipo ya facturado, esta factura va por el TOTAL, relacionada (07) a esos CFDI de
    // anticipo, con la forma de pago del monto mayor (30 si es el anticipo) y en PUE; al
    // timbrarla se emite sola la nota de crédito por lo aplicado.
    $antOrden = null;
    $descReferido = 0.0;   // Fase 4: bono de referido aplicado = descuento dentro de esta factura
    if (!empty($fac['orden_folio']) && empty($fac['saldo_favor_id'])) {
        $antOrden = _facturapiAnticiposDeOrden($pdo, $fac['orden_folio']);
        // Cualquier saldo a favor aplicado (anticipo, referido o saldo anterior) obliga a
        // facturar en PUE con la orden liquidada: con PPD, esos "pagos" generarían
        // complementos de un dinero que no se cobró en ese momento (Fase 4, 30-sep-2026).
        if ($antOrden['aplicado'] > 0.004 && $fac['metodo_pago'] !== 'PUE') {
            $abortar('La orden '.$fac['orden_folio'].' se pagó en parte con saldo a favor ($'.number_format($antOrden['aplicado'], 2)
                .'): se factura en PUE cuando esté liquidada, no en parcialidades.');
        }
        $descReferido = $antOrden['referido'];
        if ($antOrden['sin_facturar']) {
            $pz = $antOrden['sin_facturar'][0];
            $abortar('La orden '.$fac['orden_folio'].' se pagó con saldo a favor de un depósito del '.date('d/m/Y', strtotime($pz['fecha']))
                .' cuyo anticipo todavía no se factura. Primero factúralo en "Anticipos por facturar" y después timbra esta factura.');
        }
        if ($antOrden['anticipos']) {
            if ($fac['metodo_pago'] !== 'PUE') {
                $abortar('La orden '.$fac['orden_folio'].' se pagó en parte con un anticipo ya facturado: se factura en PUE cuando esté liquidada '
                    .'(el esquema de anticipos con pagos en parcialidades no está soportado todavía).');
            }
            $formasOk = _facturapiFormasValidasConAnticipo($pdo, $fac['orden_folio']);
            if (!in_array((string)$fac['forma_pago'], $formasOk, true)) {
                $abortar('La orden '.$fac['orden_folio'].' se pagó en parte con anticipo ($'.number_format($antOrden['total_anticipos'], 2).'). '
                    .($formasOk === ['30']
                        ? 'Como el anticipo cubrió todo, la forma de pago es 30 (Aplicación de anticipos).'
                        : 'La factura del total lleva la forma con la que se pagó la diferencia (la de mayor importe): '.implode(' o ', $formasOk)
                          .'. La forma 30 va en la nota de crédito, no aquí (Anexo 20, Apéndice 6).')
                    .' Edita la factura y vuelve a timbrar.');
            }
        } else {
            $antOrden = null;
        }
    }

    $conceptos = json_decode($fac['conceptos'], true);

    // Bloquear timbrado si algún concepto no trae una clave SAT real asignada —
    // '01010101' es la clave de "no existe en catálogo", solo válida para pruebas sandbox
    foreach ($conceptos as $i => $c) {
        $clave = trim($c['clave'] ?? '');
        if ($clave === '' || $clave === '01010101') {
            $abortar('El concepto "'.($c['desc'] ?: ($i+1)).'" no tiene una clave SAT válida asignada. Asígnala antes de timbrar.');
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
                // Solo el anticipo manda el precio con IVA incluido (ver _facturapiConceptoAnticipo).
                'tax_included' => !empty($c['iva_incluido']),
                'taxes'        => $applyIva
                    ? [['type'=>'IVA','rate'=>0.16,'factor'=>'Tasa']]
                    : [],
            ],
        ];
        $items[] = $item;
    }

    // Bono de referido aplicado (Fase 4 del esquema de anticipos, 30-sep-2026): no es un
    // pago ni un anticipo — no entró dinero —, es una bonificación sobre esta venta. Va
    // como DESCUENTO en los conceptos (campo 'discount' de FacturAPI, antes de IVA),
    // repartido en proporción al importe de cada concepto gravado. El total del CFDI queda
    // por lo que el cliente realmente pagó en dinero.
    if ($descReferido > 0.004) {
        $idx = [];
        foreach ($conceptos as $i => $c) { if (!empty($c['iva']) && empty($c['iva_incluido'])) $idx[] = $i; }
        $conIva = (bool)$idx;
        if (!$idx) $idx = array_keys($conceptos);
        $desc = $conIva ? round($descReferido / 1.16, 2) : round($descReferido, 2);
        $imps = []; $totImp = 0.0;
        foreach ($idx as $i) { $imps[$i] = round((float)$conceptos[$i]['cant'] * (float)$conceptos[$i]['precio'], 2); $totImp += $imps[$i]; }
        // Dividir entre 1.16 y redondear puede dejar el total un centavo arriba o abajo de
        // (total - bono). Se prueba el descuento vecino que deje el total exacto.
        if ($conIva) {
            $objetivo = round((float)$fac['total'] - $descReferido, 2);
            $resto = round((float)$fac['total'] - round($totImp * 1.16, 2), 2);   // conceptos fuera del reparto
            $mejor = $desc; $dif = null;
            foreach ([0, -0.01, 0.01, -0.02, 0.02] as $k) {
                $dd = round($desc + $k, 2); $base = round($totImp - $dd, 2);
                $t = round($base + round($base * 0.16, 2) + $resto, 2);
                if ($dif === null || abs($t - $objetivo) < $dif - 0.0001) { $dif = abs($t - $objetivo); $mejor = $dd; }
            }
            $desc = $mejor;
        }
        if ($totImp <= 0 || $desc > $totImp) {
            $abortar('El bono de referido aplicado ($'.number_format($descReferido, 2).') es mayor que los conceptos de la factura.');
        }
        $repartido = 0.0; $ultimo = end($idx);
        foreach ($idx as $i) {
            $d = ($i === $ultimo) ? round($desc - $repartido, 2) : round($desc * $imps[$i] / $totImp, 2);
            $repartido = round($repartido + $d, 2);
            if ($d > 0) $items[$i]['discount'] = $d;
        }
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
            // El SAT exige el nombre idéntico al padrón (CFDI40145): un doble espacio capturado
            // en el CRM basta para que rechace el timbrado (caso real CTN-532, 26-sep-2026).
            'legal_name'  => trim(preg_replace('/\s+/u', ' ', (string)$fac['receptor_nombre'])),
            'tax_id'      => $fac['receptor_rfc'],
            'tax_system'  => $fac['receptor_regimen'],
            'email'       => $correosFactura[0] ?? null,
            'address'     => ['zip' => $fac['receptor_cp']],
        ],
        'items' => $items,
    ];

    // Fecha de emisión: hasta hoy el campo "fecha" del formulario era decorativo — nunca se
    // mandaba, así que el PAC timbraba siempre con la fecha del momento. Eso impide el caso
    // más común de todos: facturar el día 2 una venta del día 30 del mes anterior.
    // Verificado contra la API real (26-sep-2026): FacturAPI acepta 'date' hacia atrás dentro
    // de la ventana del SAT — 2 días atrás sí, 10 días atrás "fuera de rango". Solo se manda
    // si la fecha capturada NO es hoy, para no arriesgar nada en el caso normal; si el SAT la
    // rechaza por vieja, el error de FacturAPI ya se muestra tal cual al usuario.
    if (!empty($fac['fecha']) && $fac['fecha'] !== date('Y-m-d')) {
        try {
            $tz = new DateTimeZone('America/Monterrey');
            $fEmision = new DateTime($fac['fecha'] . ' 12:00:00', $tz);
            // Nunca una fecha futura: el SAT no la acepta y no tiene sentido.
            if ($fEmision <= new DateTime('now', $tz)) {
                $payload['date'] = $fEmision->format('Y-m-d\\TH:i:s');
            }
        } catch (Exception $e) {
            // Fecha ilegible: se deja que el PAC timbre con la de hoy.
            error_log('APEX Facturacion: fecha de emision ilegible en factura id='.$id.': '.$fac['fecha']);
        }
    }

    // CFDI relacionados: forma confirmada contra la API real (ver accion=guardar).
    if (!empty($fac['relacion_tipo']) && !empty($fac['relacion_uuid'])) {
        $payload['related_documents'] = [[
            'relationship' => $fac['relacion_tipo'],
            'documents'    => [$fac['relacion_uuid']],
        ]];
    }
    // Anticipos aplicados: relación 07 "CFDI por aplicación de anticipo" a cada CFDI de anticipo.
    if ($antOrden) {
        $payload['related_documents'] = $payload['related_documents'] ?? [];
        $payload['related_documents'][] = [
            'relationship' => '07',
            'documents'    => array_column($antOrden['anticipos'], 'uuid'),
        ];
    }

    // Información Global (nodo InformacionGlobal del CFDI 4.0). Obligatorio con el RFC
    // genérico + nombre "PUBLICO EN GENERAL", si no el PAC rechaza con CFDI40130.
    // Nombres y valores confirmados contra la API real: global.periodicity acepta
    // 'day'|'week'|'fortnight'|'month' (no los códigos del SAT, y no soporta bimestral).
    if (strtoupper(trim((string)$fac['receptor_rfc'])) === 'XAXX010101000') {
        if (empty($fac['global_periodicidad']) || empty($fac['global_meses']) || empty($fac['global_anio'])) {
            $abortar('Esta factura es a Público en General y le falta la Información Global (periodicidad, mes y año) que exige el SAT. '
                .'Ábrela con Editar, llénala y guárdala antes de timbrar.');
        }
        $payload['global'] = [
            'periodicity' => $fac['global_periodicidad'],
            'months'      => $fac['global_meses'],
            'year'        => (int)$fac['global_anio'],
        ];
    }

    // Llamada a FacturAPI. external_id identifica la factura en FacturAPI para
    // verificar_timbrado; idempotency_key evita un segundo CFDI si esta misma reserva
    // llegara dos veces (ver _facturapiIdempotencyKey en helpers/facturapi_lib.php).
    $payload['external_id']     = _facturapiExternalId($fac);
    $payload['idempotency_key'] = _facturapiIdempotencyKey($fac);
    $r = _facturapiLlamar('POST', 'https://www.facturapi.io/v2/invoices', $payload);
    $httpCode = $r['code']; $res = $r['res'];

    if (_facturapiRespuestaAmbigua($r['err'], $httpCode, $res)) {
        // No se libera la reserva: el CFDI pudo quedar emitido (ver _facturapiRespuestaAmbigua).
        _facturapiLogError('timbrar AMBIGUO factura id='.$id, $r);
        jsonResponse(['ok'=>false, 'en_verificacion'=>true, 'error'=>_facturapiMsgAmbiguo($httpCode, $res)]); exit;
    }

    if ($httpCode !== 200) {
        _facturapiLogError('timbrar error factura id='.$id, $r);
        $abortar('FacturAPI: '._facturapiMensajeError($r));
    }

    // Guardar resultado en BD
    $t = _facturapiRegistrarTimbre($pdo, $fac, $res, $user['nombre']);
    $uuid = $t['uuid']; $facurapiId = $t['facturapi_id']; $pdfUrl = $t['pdf_url']; $xmlUrl = $t['xml_url'];
    $totalPac = $t['total_pac']; $pdfBin = $t['pdf_bin']; $xmlBin = $t['xml_bin'];

    // Envío de PDF+XML a todos los correos capturados — best-effort, nunca bloquea la respuesta de
    // timbrado (la factura ya quedó timbrada ante el SAT independientemente de si el correo se logra enviar).
    //
    // CANDADO DE MODO PRUEBA (26-sep-2026): en 'test' NO se manda nada al correo del
    // receptor. Se detectó en vivo al probar el módulo: el correo del cliente viene
    // pre-llenado del CRM, así que timbrar en sandbox le mandaba a un cliente REAL un
    // CFDI de pruebas adjunto. Un comprobante de sandbox no tiene validez fiscal y
    // confunde al cliente (o peor, lo mete a su contabilidad). Solo se envía en 'live'.
    $correoEnviado = false;
    $correoOmitido = false;   // true = habia destinatarios pero no se envio (a proposito)
    if ($correosFactura && !FACTURACION_ENVIO_CORREO_ACTIVO) {
        $correoOmitido = true;
        error_log('APEX Facturacion: envio de correo DESACTIVADO — no se envió el CFDI de la factura id='
            .$id.' a '.implode(', ', $correosFactura));
        $correosFactura = [];
    } elseif ($correosFactura && FACTURAPI_MODE !== 'live') {
        // Segundo candado, independiente del interruptor: aunque se reactive el envio,
        // nunca sale un CFDI de sandbox al correo de un cliente real.
        $correoOmitido = true;
        error_log('APEX Facturacion: modo '.FACTURAPI_MODE.' — no se envió el CFDI de la factura id='
            .$id.' a '.implode(', ', $correosFactura).' (candado de modo prueba)');
        $correosFactura = [];
    }
    if ($correosFactura) {
        $facParaCorreo = ['folio_interno'=>$fac['folio_interno'], 'receptor_nombre'=>$fac['receptor_nombre'], 'total'=>($totalPac ?? $fac['total'])];
        $resCorreo = enviarCorreoFactura($facParaCorreo, $correosFactura, $pdfBin, $xmlBin);
        $correoEnviado = $resCorreo['ok'];
        if (!$correoEnviado) error_log('APEX Facturacion: no se pudo enviar correo de factura id='.$id.': '.($resCorreo['error'] ?? ''));
    }

    // Sustitución: la original se cancela con motivo 01 apuntando a la nueva. Si falla
    // (PAC caído, complementos vivos), se avisa — la nueva ya quedó timbrada y hay que
    // cancelar la original a mano para no dejar dos CFDI vigentes de la misma venta.
    $sustitucion = null;
    if ($facOriginalSustituir && $uuid) {
        $rc = _facturapiCancelarEnPac($pdo, $facOriginalSustituir, '01', $uuid, $user['nombre']);
        $sustitucion = ['folio_original'=>$facOriginalSustituir['folio_interno']] + $rc;
        if (empty($rc['ok'])) {
            error_log('APEX Facturacion: sustitución — la factura id='.$id.' se timbró pero NO se pudo cancelar la original '
                .$facOriginalSustituir['folio_interno'].': '.($rc['error'] ?? ''));
        }
    }

    // PPD: los abonos que ya existían (anticipo) reciben su complemento de inmediato,
    // para que no se olviden y queden dentro del plazo del día 5.
    $complementos = [];
    if ($fac['metodo_pago'] === 'PPD' && !empty($fac['orden_folio']) && $uuid) {
        $complementos = _facturapiEmitirPendientesAuto($pdo, $id, $user['nombre']);
    }

    // Nota de crédito de la aplicación de anticipos (esquema A). Nunca rompe la respuesta:
    // si falla, la factura muestra "Falta nota de crédito" con el botón para reintentar.
    $notaCredito = null;
    if ($antOrden && $uuid) {
        $ins = $pdo->prepare("INSERT INTO facturas_anticipos (factura_id, anticipo_id, saldo_favor_id, monto) VALUES (?,?,?,?)");
        foreach ($antOrden['anticipos'] as $a) $ins->execute([$id, $a['anticipo_id'], $a['saldo_favor_id'], $a['monto']]);
        $notaCredito = _facturapiEmitirNotaAnticipo($pdo, $id, $user['nombre']);
    }

    jsonResponse([
        'ok'             => true,
        'sustitucion'    => $sustitucion,
        'complementos'   => $complementos,
        'nota_credito'   => $notaCredito,
        'uuid'           => $uuid,
        'facturapi_id'   => $facurapiId,
        'pdf_url'        => $pdfUrl,
        'xml_url'        => $xmlUrl,
        'modo'           => FACTURAPI_MODE,
        'correo_enviado' => $correoEnviado,
        'correo_omitido' => $correoOmitido,
    ]);
    exit;
}

// ── POST reenviar_correo (timbrada → reenvía PDF+XML a los correos capturados o a una lista puntual) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'reenviar_correo') {
    $d  = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? AND estatus='timbrada'");
    $stmt->execute([$id]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac) { jsonResponse(['ok'=>false,'error'=>'Factura no encontrada o no está timbrada']); exit; }

    // Por default reenvía a los correos ya guardados en la factura; si mandan "correos" en el body
    // (ej. el usuario quiere agregar uno puntual sin editar el registro), se usan esos en su lugar.
    $listaRaw  = trim($d['correos'] ?? '') !== '' ? $d['correos'] : ($fac['receptor_email'] ?? '');
    $correos   = _correosValidos($listaRaw);
    if (!$correos) { jsonResponse(['ok'=>false,'error'=>'No hay correos válidos para reenviar']); exit; }

    // Mismos dos candados que en timbrar.
    if (!FACTURACION_ENVIO_CORREO_ACTIVO) {
        jsonResponse(['ok'=>false,'error'=>'El envío de comprobantes por correo está desactivado por ahora. '
            .'Se habilita al final del proyecto de facturación.']); exit;
    }
    if (FACTURAPI_MODE !== 'live') {
        jsonResponse(['ok'=>false,'error'=>'Estás en modo PRUEBA: el envío de comprobantes por correo está bloqueado a propósito, '
            .'para no mandarle a un cliente real un CFDI de sandbox. Se habilita solo en modo producción.']); exit;
    }

    // S2-a: primero el resguardo local; a FacturAPI solo si falta el archivo. Antes esto
    // siempre pegaba a FacturAPI, así que con la suscripción caída no se podía ni reenviar
    // por correo una factura ya timbrada.
    $leerLocal = function($rel) {
        if (!$rel) return null;
        $real = realpath(APEX_DIR_FACTURAS . '/' . $rel);
        $base = realpath(APEX_DIR_FACTURAS);
        if ($real && $base && strpos($real, $base . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
            return file_get_contents($real);
        }
        return null;
    };
    $pdfBin = $leerLocal($fac['pdf_path']) ?: ($fac['facturapi_id'] ? _descargarArchivoFacturapi($fac['pdf_url']) : null);
    $xmlBin = $leerLocal($fac['xml_path']) ?: ($fac['facturapi_id'] ? _descargarArchivoFacturapi($fac['xml_url']) : null);
    if (!$pdfBin && !$xmlBin) { jsonResponse(['ok'=>false,'error'=>'No se encontró el PDF ni el XML, ni en el resguardo local ni en FacturAPI']); exit; }

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

    $stmt = $pdo->prepare("SELECT facturapi_id, estatus, folio_interno, pdf_path, xml_path FROM facturas WHERE id=?");
    $stmt->execute([$id]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    // Una cancelada sigue siendo un comprobante que hay que poder descargar (el SAT
    // obliga a conservarla): se permite también, no solo 'timbrada'.
    if (!$fac || !in_array($fac['estatus'], ['timbrada','cancelada'], true)) { http_response_code(404); exit; }

    // S2-a: servir del resguardo local. Es lo que hace que la descarga siga funcionando
    // aunque FacturAPI esté caído o sin suscripción — el caso real del 24-sep-2026.
    $rutaLocal = $fac[$accion === 'pdf' ? 'pdf_path' : 'xml_path'];
    if ($rutaLocal) {
        $abs = APEX_DIR_FACTURAS . '/' . $rutaLocal;
        // realpath + comprobación de prefijo: la ruta viene de BD, no se confía en ella
        // para construir un path (defensa contra traversal si alguna vez se ensucia).
        $real = realpath($abs);
        $base = realpath(APEX_DIR_FACTURAS);
        if ($real && $base && strpos($real, $base . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
            header('Content-Type: ' . ($accion === 'pdf' ? 'application/pdf' : 'application/xml'));
            header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9_-]/','',$fac['folio_interno']) . '.' . $accion . '"');
            header('Content-Length: ' . filesize($real));
            readfile($real);
            exit;
        }
        error_log('APEX Facturacion: resguardo local ausente para factura id='.$id.' ('.$rutaLocal.'), se recurre a FacturAPI');
    }

    $url = 'https://www.facturapi.io/v2/invoices/' . $fac['facturapi_id'] . '/' . $accion;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . FACTURAPI_KEY],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $data     = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    unset($ch);

    // S-5: antes cualquier fallo devolvía un 502 en blanco y el usuario veía una
    // pestaña vacía sin explicación. Ahora se muestra el motivo real — el caso que
    // lo hizo evidente fue el 402 'subscription_required' de FacturAPI, imposible de
    // diagnosticar desde la UI. Se responde en HTML porque este endpoint se abre en
    // una pestaña nueva (target="_blank"), no por fetch.
    if ($httpCode !== 200) {
        $detalle = '';
        if ($curlErr) {
            $detalle = 'Error de conexión con FacturAPI: '.$curlErr;
        } else {
            $j = json_decode((string)$data, true);
            $detalle = is_array($j) ? (string)($j['message'] ?? $j['error'] ?? '') : '';
            if ($detalle === '') $detalle = 'FacturAPI respondió HTTP '.$httpCode.' sin un mensaje legible.';
        }
        error_log('APEX FacturAPI '.$accion.' id='.$id.' HTTP '.$httpCode.': '.($curlErr ?: (string)$data));
        http_response_code(502);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>No se pudo obtener el '.strtoupper($accion).'</title>'
           . '<div style="font:14px/1.6 system-ui,sans-serif;max-width:560px;margin:60px auto;padding:0 20px;color:#1e293b">'
           . '<h2 style="margin:0 0 10px;font-size:17px">No se pudo obtener el '.strtoupper($accion).' de esta factura</h2>'
           . '<p style="margin:0 0 14px;color:#475569">'.htmlspecialchars($detalle, ENT_QUOTES, 'UTF-8').'</p>'
           . '<p style="margin:0;color:#64748b;font-size:13px">El comprobante sigue timbrado ante el SAT; esto es solo la descarga. '
           . 'Si el mensaje habla de la suscripción, hay que revisarla en el panel de FacturAPI.</p></div>';
        exit;
    }

    header('Content-Type: ' . ($accion === 'pdf' ? 'application/pdf' : 'application/xml'));
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9_-]/','',$fac['folio_interno']) . '.' . $accion . '"');
    echo $data;
    exit;
}

// ── POST eliminar (solo borradores) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'eliminar') {
    $d  = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }

    // Se leen las rutas del resguardo ANTES de borrar la fila: si no, los archivos
    // quedaban huérfanos en archivos_facturas/ sin nada que los referenciara.
    $stmt = $pdo->prepare("SELECT pdf_path, xml_path FROM facturas WHERE id=?");
    $stmt->execute([$id]);
    $paths = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Una factura PPD con complementos ligados no se puede borrar sin borrar antes sus
    // complementos (la FK lo impide de todos modos; aquí se da un mensaje claro).
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM facturas_pagos WHERE factura_id=?");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        jsonResponse(['ok'=>false,'error'=>'Esta factura tiene complementos de pago ligados. Elimina primero sus complementos.']);
        exit;
    }

    // Una factura en modo test también se puede borrar si está CANCELADA, no solo
    // timbrada — al probar el flujo completo lo normal es acabar con canceladas de
    // prueba, y antes esas quedaban imborrables desde la UI (había que ir a SQL).
    // En modo live sigue siendo imposible borrar una timbrada o cancelada: el SAT
    // obliga a conservarlas.
    $stmt = $pdo->prepare("DELETE FROM facturas WHERE id=? AND (estatus='borrador' OR (estatus IN ('timbrada','cancelada') AND modo='test'))");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['ok'=>false,'error'=>'No se puede eliminar: no existe, o es una factura timbrada/cancelada en producción (el SAT obliga a conservarla)']);
        exit;
    }

    // Limpieza del resguardo local, con la misma comprobación de prefijo del proxy
    // para no borrar nada fuera de la carpeta por una ruta sucia en BD.
    $base = realpath(APEX_DIR_FACTURAS);
    foreach ([$paths['pdf_path'] ?? null, $paths['xml_path'] ?? null] as $rel) {
        if (!$rel) continue;
        $real = realpath(APEX_DIR_FACTURAS . '/' . $rel);
        if ($real && $base && strpos($real, $base . DIRECTORY_SEPARATOR) === 0 && is_file($real)) {
            @unlink($real);
        }
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
    if ($motivo === '01') {
        // Auditoría 29-sep-2026: formato de UUID y que la sustituta sea un CFDI vigente
        // emitido por nosotros (y distinto de la factura que se cancela); antes se mandaba
        // a FacturAPI cualquier texto, solo URL-encoded.
        $substitucion = strtoupper($substitucion);
        if (!preg_match('/^[0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12}$/', $substitucion)) {
            jsonResponse(['ok'=>false,'error'=>'El UUID de la factura sustituta no tiene un formato válido.']); exit;
        }
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM facturas WHERE UPPER(uuid)=? AND estatus='timbrada' AND id<>?");
        $stmt->execute([$substitucion, $id]);
        if (!(int)$stmt->fetchColumn()) {
            jsonResponse(['ok'=>false,'error'=>'La factura sustituta debe ser una factura timbrada y vigente emitida desde este sistema.']); exit;
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? AND estatus='timbrada'");
    $stmt->execute([$id]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac) { jsonResponse(['ok'=>false,'error'=>'Factura no encontrada o no está timbrada']); exit; }

    $r = _facturapiCancelarEnPac($pdo, $fac, $motivo, $motivo === '01' ? $substitucion : '', $user['nombre']);
    jsonResponse($r);
    exit;
}

// ── POST verificar_cancelacion (re-consulta a FacturAPI el estatus real de una cancelación 'pending') ──
// POST (antes GET): modifica la fila, y un GET se puede disparar desde un enlace externo
// con la cookie de sesión (SameSite=Lax la manda en navegación). Auditoría 29-sep-2026.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'verificar_cancelacion') {
    $dVc = json_decode(file_get_contents('php://input'), true);
    $id = (int)($dVc['id'] ?? 0);
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? AND pac_cancel_status IS NOT NULL");
    $stmt->execute([$id]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac) { jsonResponse(['ok'=>false,'error'=>'Factura no encontrada o sin cancelación en trámite']); exit; }

    $r = _facturapiLlamar('GET', 'https://www.facturapi.io/v2/invoices/' . $fac['facturapi_id'], null, null, 20);
    if ($r['code'] !== 200) {
        _facturapiLogError('verificar_cancelacion factura id='.$id, $r);
        jsonResponse(['ok'=>false,'error'=>'No se pudo consultar FacturAPI: '._facturapiMensajeError($r)]); exit;
    }

    // 'rejected' = el receptor la rechazó o expiró; 'none' = el SAT no tiene solicitud.
    // En ambos casos la factura sigue vigente y se puede volver a pedir la cancelación.
    $pacStatus = _facturapiEstadoCancelacion($r['res']);
    if ($pacStatus === 'none') $pacStatus = 'rejected';
    $esFirme   = ($pacStatus === 'canceled');

    $stmt = $pdo->prepare("UPDATE facturas SET estatus=?, pac_cancel_status=?, updated_at=NOW() WHERE id=?");
    $stmt->execute([$esFirme ? 'cancelada' : 'timbrada', $pacStatus, $id]);

    jsonResponse(['ok'=>true, 'estatus'=>$pacStatus, 'firme'=>$esFirme]);
    exit;
}

// ── GET pagos_factura (abonos de Cobranza de una factura PPD y sus complementos) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'pagos_factura') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=?");
    $stmt->execute([$id]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac || $fac['tipo_cfdi'] !== 'I' || $fac['metodo_pago'] !== 'PPD' || empty($fac['orden_folio'])) {
        jsonResponse(['ok'=>false,'error'=>'Solo las facturas PPD ligadas a una orden llevan complementos de pago.']); exit;
    }
    $estado = _facturapiEstadoPagos($pdo, $fac);
    unset($estado['complementos']);
    $estado['factura'] = [
        'id'=>(int)$fac['id'], 'folio'=>$fac['folio_interno'], 'orden_folio'=>$fac['orden_folio'],
        'estatus'=>$fac['estatus'], 'pac_cancel_status'=>$fac['pac_cancel_status'],
        'receptor_nombre'=>$fac['receptor_nombre'], 'fecha'=>substr((string)($fac['fecha_timbrado'] ?: $fac['fecha']), 0, 10),
        'modo_ok'=>($fac['modo'] === FACTURAPI_MODE),
    ];
    jsonResponse(['ok'=>true] + $estado);
    exit;
}

// ── GET complementos_pendientes (abonos de facturas PPD vigentes sin complemento) ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'complementos_pendientes') {
    $rows = $pdo->query("
        SELECT f.id AS factura_id, f.folio_interno AS factura_folio, f.orden_folio, f.receptor_nombre,
               f.total AS factura_total, f.modo,
               p.id AS pago_id, p.fecha_pago, p.monto, p.forma_pago
        FROM facturas f
        JOIN ordenes o          ON o.folio = f.orden_folio
        JOIN cotizaciones c     ON c.orden_id = o.id
        JOIN cotizacion_pagos p ON p.cotizacion_id = c.id
        WHERE f.tipo_cfdi = 'I' AND f.metodo_pago = 'PPD' AND f.estatus = 'timbrada' AND p.monto > 0
          AND f.modo = ".$pdo->quote(FACTURAPI_MODE)."
          AND NOT EXISTS (
              SELECT 1 FROM facturas_pagos fp JOIN facturas x ON x.id = fp.complemento_id
              WHERE fp.factura_id = f.id AND fp.cotizacion_pago_id = p.id
                AND x.estatus IN ('timbrada','timbrando')
          )
        ORDER BY p.fecha_pago, p.hora_pago, p.id
    ")->fetchAll(PDO::FETCH_ASSOC);
    $hoy = date('Y-m-d');
    foreach ($rows as &$r) {
        $r['fecha_limite'] = _facturapiLimiteComplemento($r['fecha_pago']);
        $r['dias_restantes'] = $r['fecha_limite']
            ? (int)((strtotime($r['fecha_limite']) - strtotime($hoy)) / 86400) : null;
    }
    unset($r);
    jsonResponse(['ok'=>true, 'pendientes'=>$rows]);
    exit;
}

// ── POST emitir_complemento (crea y timbra el CFDI tipo P de UN abono) ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'emitir_complemento') {
    $d = json_decode(file_get_contents('php://input'), true);
    jsonResponse(_facturapiEmitirComplemento($pdo, (int)($d['factura_id'] ?? 0), (int)($d['cotizacion_pago_id'] ?? 0),
        trim((string)($d['forma_pago'] ?? '')), $user['nombre']));
    exit;
}

// ── POST crear_cliente_fiscal (alta rápida en el CRM desde el modal de la factura) ──
// Para cuando el cliente pide facturar a una razón social que no está en el CRM. Si el
// RFC ya existe, NO duplica: regresa ese cliente. Antes de dar de alta valida nombre,
// RFC, CP y régimen contra el padrón del SAT (sandbox), para no guardar datos que luego
// truenan al timbrar. Mismo alta que api/clientes.php (código CTN-N con reintento).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'crear_cliente_fiscal') {
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    $razon   = strtoupper(trim(preg_replace('/\s+/u', ' ', (string)($d['razon_social'] ?? ''))));
    $rfc     = strtoupper(trim((string)($d['rfc'] ?? '')));
    $cp      = trim((string)($d['cp'] ?? ''));
    $regimen = trim((string)($d['regimen'] ?? ''));
    $email   = trim((string)($d['email'] ?? ''));

    if ($razon === '' || mb_strlen($razon) > 200 || preg_match('/[<>]/', $razon)) {
        jsonResponse(['ok'=>false,'error'=>'Captura la razón social tal como aparece en la Constancia de Situación Fiscal.']); exit;
    }
    if (!preg_match('/^[A-ZÑ&]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc)) {
        jsonResponse(['ok'=>false,'error'=>'RFC con formato inválido.']); exit;
    }
    if (in_array($rfc, ['XAXX010101000','XEXX010101000'], true)) {
        jsonResponse(['ok'=>false,'error'=>'El RFC genérico no se da de alta como cliente.']); exit;
    }
    if (!preg_match('/^[0-9]{5}$/', $cp)) { jsonResponse(['ok'=>false,'error'=>'CP fiscal inválido (5 dígitos).']); exit; }
    $REGIMENES = ['601','603','605','606','607','608','610','611','612','614','615','616','620','621','622','623','624','625','626'];
    if (!in_array($regimen, $REGIMENES, true)) { jsonResponse(['ok'=>false,'error'=>'Selecciona el régimen fiscal.']); exit; }
    foreach (array_filter(array_map('trim', explode(',', $email))) as $correo) {
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) { jsonResponse(['ok'=>false,'error'=>'Correo inválido: '.$correo]); exit; }
    }

    $stmt = $pdo->prepare("SELECT id, codigo, COALESCE(NULLIF(razon_social,''), nombre) AS nombre FROM clientes WHERE UPPER(TRIM(rfc)) = ? ORDER BY activo DESC, id LIMIT 1");
    $stmt->execute([$rfc]);
    if ($ex = $stmt->fetch(PDO::FETCH_ASSOC)) {
        jsonResponse(['ok'=>true, 'existente'=>true, 'id'=>(int)$ex['id'], 'codigo'=>$ex['codigo'], 'nombre'=>$ex['nombre']]); exit;
    }

    $v = _facturapiValidarReceptorSat($rfc, $razon, $cp, $regimen);
    if (empty($v['ok'])) {
        jsonResponse(['ok'=>false, 'error'=>$v['error'].' — revisa que la razón social sea idéntica a la de la Constancia (mayúsculas, sin régimen de capital como "SA DE CV").']); exit;
    }

    $intentos = 0;
    while (true) {
        $intentos++;
        $row  = $pdo->query("SELECT MAX(CAST(SUBSTRING(codigo, 5) AS UNSIGNED)) AS max_num FROM clientes WHERE codigo LIKE 'CTN-%'")->fetch(PDO::FETCH_ASSOC);
        $codigo = 'CTN-' . (($row['max_num'] ?? 146) + 1);
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO clientes (codigo, razon_social, nombre, contacto, telefono, telefono_alterno, email, localidad, ciudad, rfc, cp_fiscal, regimen_fiscal)
                           VALUES (?,?,?,'','',NULL,?,'local','',?,?,?)")
                ->execute([$codigo, $razon, $razon, $email, $rfc, $cp, $regimen]);
            $newId = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO clientes_bitacora (cliente_id, campo, valor_anterior, valor_nuevo, usuario_id, usuario_nombre) VALUES (?, 'CREACION', '', ?, ?, ?)")
                ->execute([$newId, 'Cliente creado desde Facturación (datos fiscales validados contra el SAT): '.$razon, $user['id'], $user['nombre']]);
            $pdo->commit();
            break;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000' && $intentos < 5) continue;
            error_log('APEX Facturacion: crear_cliente_fiscal: '.$e->getMessage());
            jsonResponse(['ok'=>false,'error'=>'No se pudo dar de alta al cliente.']); exit;
        }
    }
    jsonResponse(['ok'=>true, 'existente'=>false, 'id'=>$newId, 'codigo'=>$codigo, 'nombre'=>$razon]);
    exit;
}

// ── GET anticipos_pendientes (depósitos de saldo a favor sin su CFDI de anticipo) ──
// Esquema A del SAT (Fase 2, 30-sep-2026). Solo depósitos en dinero desde ANTICIPOS_DESDE;
// el bono de referido (tipo 'referido') nunca es anticipo. Si el depósito ya tiene un
// borrador de anticipo se regresa para continuarlo en vez de crear otro.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'anticipos_pendientes') {
    $stmt = $pdo->prepare("
        SELECT sf.id, sf.cliente_id, sf.monto, sf.forma_pago, sf.tarjeta_tipo, sf.fecha, sf.referencia,
               c.codigo AS cliente_codigo, COALESCE(NULLIF(c.razon_social,''), c.nombre) AS cliente_nombre,
               c.rfc, c.razon_social, c.cp_fiscal, c.regimen_fiscal,
               (SELECT f.id FROM facturas f WHERE f.saldo_favor_id = sf.id AND f.modo = ? AND f.estatus = 'borrador' ORDER BY f.id DESC LIMIT 1) AS borrador_id
        FROM clientes_saldo_favor sf
        JOIN clientes c ON c.id = sf.cliente_id
        WHERE sf.tipo = 'deposito' AND sf.monto > 0 AND sf.fecha >= ?
          AND NOT EXISTS (SELECT 1 FROM facturas f2 WHERE f2.saldo_favor_id = sf.id AND f2.modo = ? AND f2.estatus IN ('timbrando','timbrada'))
        ORDER BY sf.fecha, sf.id
    ");
    $stmt->execute([FACTURAPI_MODE, ANTICIPOS_DESDE, FACTURAPI_MODE]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['forma_sat']      = _facturapiFormaSatDeposito($r['forma_pago'], $r['tarjeta_tipo']);
        $r['fiscal_completo'] = (trim((string)$r['rfc']) !== '' && trim((string)$r['razon_social']) !== ''
            && trim((string)$r['cp_fiscal']) !== '' && trim((string)$r['regimen_fiscal']) !== '');
        unset($r['razon_social']);
    }
    unset($r);
    jsonResponse(['ok'=>true, 'desde'=>ANTICIPOS_DESDE, 'anticipos'=>$rows]);
    exit;
}

// ── POST anticipo_crear (arma el borrador del CFDI de anticipo de un depósito) ──
// Todo sale del servidor: receptor (datos fiscales del cliente o Público en General),
// concepto del SAT, monto y forma de pago del depósito. La pantalla solo escoge entre
// cliente / Público en General, el uso de CFDI y, si el depósito no la guardó, la forma.
// Después la pantalla lo timbra con accion=timbrar (mismos candados de siempre).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'anticipo_crear') {
    $d    = json_decode(file_get_contents('php://input'), true) ?: [];
    $sfId = (int)($d['saldo_favor_id'] ?? 0);
    $pg   = !empty($d['publico_general']);

    $stmt = $pdo->prepare("
        SELECT sf.*, c.codigo, c.nombre, c.razon_social, c.rfc, c.cp_fiscal, c.regimen_fiscal, c.email
        FROM clientes_saldo_favor sf JOIN clientes c ON c.id = sf.cliente_id
        WHERE sf.id = ?
    ");
    $stmt->execute([$sfId]);
    $dep = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$dep || $dep['tipo'] !== 'deposito' || (float)$dep['monto'] <= 0) {
        jsonResponse(['ok'=>false,'error'=>'Depósito no encontrado (solo los depósitos en dinero se facturan como anticipo).']); exit;
    }
    if ($dep['fecha'] < ANTICIPOS_DESDE) {
        jsonResponse(['ok'=>false,'error'=>'Los depósitos anteriores al '.date('d/m/Y', strtotime(ANTICIPOS_DESDE)).' no se facturan como anticipo.']); exit;
    }
    if ($ya = _facturapiAnticipoVigente($pdo, $sfId)) {
        if ($ya['estatus'] === 'borrador') { jsonResponse(['ok'=>true, 'id'=>(int)$ya['id'], 'folio'=>$ya['folio_interno'], 'existente'=>true]); exit; }
        jsonResponse(['ok'=>false,'error'=>'Este depósito ya tiene la factura de anticipo '.$ya['folio_interno'].'.']); exit;
    }

    // Forma de pago: la del depósito; si no se guardó (mezcla de formas al regresar dinero
    // de una orden) se escoge en pantalla, solo entre las formas reales de un cobro.
    $forma = _facturapiFormaSatDeposito($dep['forma_pago'], $dep['tarjeta_tipo']);
    if (!$forma) {
        $forma = (string)($d['forma_pago'] ?? '');
        if (!in_array($forma, ['01','02','03','04','28'], true)) {
            jsonResponse(['ok'=>false,'error'=>'El depósito no tiene forma de pago registrada: indica con qué se pagó.']); exit;
        }
    }

    if ($pg) {
        $rec = ['nombre'=>'PUBLICO EN GENERAL', 'rfc'=>'XAXX010101000', 'cp'=>FACTURAPI_EMISOR_CP, 'regimen'=>'616', 'uso'=>'S01'];
        $glob = ['day', date('m', strtotime($dep['fecha'])), (int)date('Y', strtotime($dep['fecha']))];
        $solicito = (int)$dep['cliente_id'];
    } else {
        $rfc = strtoupper(trim((string)$dep['rfc']));
        $nom = trim(preg_replace('/\s+/u', ' ', (string)$dep['razon_social']));
        $cp  = trim((string)$dep['cp_fiscal']);
        $reg = trim((string)$dep['regimen_fiscal']);
        if ($rfc === '' || $nom === '' || $cp === '' || $reg === '') {
            jsonResponse(['ok'=>false,'error'=>'El cliente '.$dep['codigo'].' no tiene completos sus datos fiscales (RFC, razón social, CP y régimen). Captura su Constancia en Clientes, o factura el anticipo a Público en General.']); exit;
        }
        $uso = strtoupper(trim((string)($d['uso_cfdi'] ?? 'G03')));
        if (!in_array($uso, ['G01','G03','I01','I02','I03','I04','I08','S01'], true)) {
            jsonResponse(['ok'=>false,'error'=>'Uso de CFDI no válido para un anticipo: '.$uso]); exit;
        }
        $rec = ['nombre'=>$nom, 'rfc'=>$rfc, 'cp'=>$cp, 'regimen'=>$reg, 'uso'=>$uso];
        $glob = [null, null, null];
        $solicito = null;
    }

    $conc = _facturapiConceptoAnticipo($dep['monto']);
    $serie = 'A';
    $intentos = 0;
    while (true) {
        $intentos++;
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(folio_numero),0)+1 FROM facturas WHERE serie=?");
        $stmt->execute([$serie]);
        $folioNum     = (int)$stmt->fetchColumn();
        $folioInterno = $serie . '-' . str_pad($folioNum, 3, '0', STR_PAD_LEFT);
        try {
            $pdo->prepare("
                INSERT INTO facturas
                    (folio_interno, serie, folio_numero, orden_folio, saldo_favor_id, tipo_cfdi, fecha,
                     receptor_nombre, receptor_rfc, receptor_cp, receptor_regimen, receptor_uso_cfdi,
                     receptor_email, cliente_solicito_id, forma_pago, metodo_pago,
                     global_periodicidad, global_meses, global_anio,
                     conceptos, subtotal, iva, total, estatus, modo, creado_por)
                VALUES (?,?,?,NULL,?,'I',?,?,?,?,?,?,?,?,?,'PUE',?,?,?,?,?,?,?,'borrador',?,?)
            ")->execute([
                $folioInterno, $serie, $folioNum, $sfId, date('Y-m-d'),
                $rec['nombre'], $rec['rfc'], $rec['cp'], $rec['regimen'], $rec['uso'],
                ($pg ? null : ($dep['email'] ?: null)), $solicito, $forma,
                $glob[0], $glob[1], $glob[2],
                json_encode($conc['conceptos']), $conc['subtotal'], $conc['iva'], $conc['total'],
                FACTURAPI_MODE, $user['nombre'],
            ]);
            break;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000' && $intentos < 5) continue;
            throw $e;
        }
    }
    jsonResponse(['ok'=>true, 'id'=>(int)$pdo->lastInsertId(), 'folio'=>$folioInterno, 'total'=>$conc['total']]);
    exit;
}

// ── POST emitir_nota_anticipo (reintento manual de la nota de crédito de una factura) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'emitir_nota_anticipo') {
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    jsonResponse(_facturapiEmitirNotaAnticipo($pdo, (int)($d['id'] ?? 0), $user['nombre']));
    exit;
}

// ── POST verificar_timbrado (resuelve una factura o complemento atorado en 'timbrando') ──
// Busca el CFDI en FacturAPI (por external_id y, si no, por serie+folio): si existe se registra como timbrado; si
// con certeza no existe se libera (la factura vuelve a borrador, el complemento se borra
// y su abono vuelve a pendientes). Solo después de 2 minutos, para no pisar un timbrado
// que todavía está en curso (el PAC tiene 30 s de timeout).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'verificar_timbrado') {
    $d  = json_decode(file_get_contents('php://input'), true);
    $id = (int)($d['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT *, (updated_at <= NOW() - INTERVAL 2 MINUTE) AS maduro FROM facturas WHERE id=? AND estatus='timbrando'");
    $stmt->execute([$id]);
    $fac = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$fac) { jsonResponse(['ok'=>false,'error'=>'Este comprobante ya no está en verificación. Recarga la lista.']); exit; }
    if (!(int)$fac['maduro']) {
        jsonResponse(['ok'=>false,'error'=>'El timbrado empezó hace menos de 2 minutos y podría seguir en curso. Espera un momento y vuelve a verificar.']); exit;
    }
    if ($fac['modo'] !== FACTURAPI_MODE) {
        jsonResponse(['ok'=>false,'error'=>'Este comprobante se reservó en otro modo (prueba/real); no se puede verificar con la llave actual.']); exit;
    }

    $inv = _facturapiBuscarCfdi($fac, $fac['updated_at']);
    if ($inv === false) {
        jsonResponse(['ok'=>false,'error'=>'No se pudo consultar FacturAPI. Intenta de nuevo en unos minutos.']); exit;
    }
    if ($inv === 'pendiente') {
        // Intermitencia del SAT: FacturAPI sigue reintentando (hasta ~50 min). No se libera.
        jsonResponse(['ok'=>true, 'resultado'=>'pendiente', 'folio'=>$fac['folio_interno']]); exit;
    }
    if ($inv) {
        $t = _facturapiRegistrarTimbre($pdo, $fac, $inv, $user['nombre']);
        error_log('APEX Facturacion: verificar_timbrado id='.$id.' — el CFDI SÍ existía en FacturAPI (UUID '.$t['uuid'].'), registrado');
        // Factura de orden pagada con anticipo: su nota de crédito (si no aplica, no hace nada).
        $nota = null;
        if ($fac['tipo_cfdi'] === 'I' && !empty($fac['orden_folio'])) {
            $nota = _facturapiEmitirNotaAnticipo($pdo, $id, $user['nombre']);
            if (!empty($nota['sin_anticipo'])) $nota = null;
        }
        jsonResponse(['ok'=>true, 'resultado'=>'timbrada', 'folio'=>$fac['folio_interno'], 'uuid'=>$t['uuid'], 'nota_credito'=>$nota]); exit;
    }
    if ($fac['tipo_cfdi'] === 'P' || $fac['tipo_cfdi'] === 'E') {
        // Complementos y notas de crédito de anticipo se arman solos: si no llegaron a
        // timbrarse se borra la reserva y se vuelven a emitir desde su origen.
        $pdo->prepare("DELETE FROM facturas WHERE id=? AND estatus='timbrando'")->execute([$id]);
    } else {
        $pdo->prepare("UPDATE facturas SET estatus='borrador' WHERE id=? AND estatus='timbrando'")->execute([$id]);
    }
    error_log('APEX Facturacion: verificar_timbrado id='.$id.' — no existe en FacturAPI, reserva liberada');
    jsonResponse(['ok'=>true, 'resultado'=>'liberada', 'folio'=>$fac['folio_interno'], 'tipo_cfdi'=>$fac['tipo_cfdi']]); exit;
}

jsonResponse(['ok'=>false,'error'=>'Acción no válida'], 400);
