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

// LN-1: conceptos (desc/cant/precio) reconstruidos en SERVIDOR a partir del folio de
// orden — nunca confiar en lo que mande el cliente cuando hay una orden real detrás.
// Usada tanto para prellenar el formulario (buscar_orden) como para validar lo que
// se guarda (guardar). Regresa null si el folio no resuelve a una orden con cotización.
function _facturapiConceptosDesdeOrden($pdo, $ordenFolio) {
    $stmt = $pdo->prepare("SELECT id FROM ordenes WHERE folio = ? LIMIT 1");
    $stmt->execute([$ordenFolio]);
    $ordenId = $stmt->fetchColumn();
    if (!$ordenId) return null;

    $stmt = $pdo->prepare("SELECT id, tipo, descuento, COALESCE(descuento_referido,0) AS descuento_referido, COALESCE(descuento_encuesta,0) AS descuento_encuesta FROM cotizaciones WHERE orden_id = ? LIMIT 1");
    $stmt->execute([$ordenId]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cot) return null;

    $cotId     = $cot['id'];
    $esMaquila = ($cot['tipo'] ?? 'suministro') === 'maquila';
    // BLV-3: descuento efectivo = manual + automáticos de referido/encuesta (mismo
    // criterio que apexTotalesCotizacion, helpers/totales.php:51) — antes solo se
    // usaba el manual, dejando el CFDI por un monto distinto al realmente cobrado.
    $descuento = min(100, (float)($cot['descuento'] ?? 0) + (float)$cot['descuento_referido'] + (float)$cot['descuento_encuesta']);

    $conceptos = [];
    if ($esMaquila) {
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
    } else {
        $stmt = $pdo->prepare("
            SELECT cristal_nombre, m2, cantidad, precio_m2_usado, promo_precio
            FROM cotizaciones_partidas
            WHERE cotizacion_id = ?
            ORDER BY num_partida ASC
        ");
        $stmt->execute([$cotId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            // precio_m2_usado es bruto (sin descuento) — aplicar el % de la cotización, igual que el resto del sistema.
            // Partidas con precio fijo de promo (SALT_SEP2026) no reciben el % de descuento.
            $precioNeto = ($descuento > 0 && empty($p['promo_precio']))
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
        // BLV-3: servicios adicionales (instalado, taladro, ml, etc.) también forman
        // parte de la base gravable canónica (helpers/totales.php: base = subtotal+servicios)
        // pero antes no se incluían aquí — el CFDI quedaba sub-facturado en cotizaciones con servicios.
        $stmtSrv = $pdo->prepare("
            SELECT descripcion, precio_unitario, unidades_por_pieza, cantidad_piezas, subtotal
            FROM cotizacion_partida_servicios WHERE cotizacion_id = ?
        ");
        $stmtSrv->execute([$cotId]);
        foreach ($stmtSrv->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $conceptos[] = [
                'desc'   => $s['descripcion'] ?: 'Servicio',
                'clave'  => '',
                'unidad' => 'ACT',
                'cant'   => 1,
                'precio' => (float)$s['subtotal'],
                'iva'    => true,
            ];
        }
    }
    return $conceptos;
}

// Candado de contexto de negocio: ¿esta orden se puede facturar?
// Antes NO se validaba en ningún punto (hallado auditando el 26-sep-2026), así que se
// podía emitir un CFDI real de una orden cancelada, rechazada, sin VoBo o de RETRABAJO.
// El retrabajo es el caso grave: todo el sistema lo aísla a propósito (fuera de ventas,
// de cobranza y del P&L, con su propia cuenta contable) porque es corrección de un error
// nuestro y NO se cobra — facturarlo es cobrarle al cliente algo que le debíamos.
// Regresa null si se puede facturar, o el texto del motivo por el que no.
function _facturapiOrdenNoFacturable($pdo, $ordenFolio) {
    $stmt = $pdo->prepare("
        SELECT o.estado, COALESCE(c.es_retrabajo, 0) AS es_retrabajo, c.estatus AS cot_estatus
        FROM ordenes o
        LEFT JOIN cotizaciones c ON c.orden_id = o.id
        WHERE o.folio = ? LIMIT 1
    ");
    $stmt->execute([$ordenFolio]);
    $o = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$o) return 'No existe una orden con el folio ' . $ordenFolio . '.';

    if ((int)$o['es_retrabajo'] === 1) {
        return 'La orden ' . $ordenFolio . ' es un RETRABAJO: es la corrección de un trabajo previo '
             . 'y no se le cobra al cliente, así que no debe facturarse. Si de verdad hay que cobrarla, '
             . 'primero hay que quitarle la marca de retrabajo.';
    }
    $etiquetas = [
        'cancelada'      => 'está cancelada',
        'rechazada'      => 'fue rechazada',
        'pendiente_vobo' => 'todavía no tiene VoBo (la venta no está confirmada)',
    ];
    if (isset($etiquetas[$o['estado']])) {
        return 'La orden ' . $ordenFolio . ' ' . $etiquetas[$o['estado']] . ', no se puede facturar.';
    }
    if (!in_array($o['estado'], ['activa', 'entregada'], true)) {
        return 'La orden ' . $ordenFolio . ' está en estado "' . $o['estado'] . '" y no se puede facturar.';
    }
    if (in_array((string)$o['cot_estatus'], ['cancelada', 'rechazada'], true)) {
        return 'La cotización de origen de la orden ' . $ordenFolio . ' está ' . $o['cot_estatus'] . ', no se puede facturar.';
    }
    return null;
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

// ENVIO DE CFDI POR CORREO — APAGADO A PROPOSITO (Armando, 26-sep-2026).
// Se baja la feature completa hasta el final del proyecto de facturacion. Motivo: al
// probar el modulo en vivo se descubrio que el correo del receptor viene pre-llenado del
// CRM, asi que timbrar en sandbox le mando a un cliente REAL un CFDI de pruebas adjunto.
// Mismo patron que RUTA_WA_AVISOS_ACTIVO en rutas_lib.php: para reactivarlo basta poner
// esta constante en true (y entonces sigue vigente el segundo candado de abajo, que
// impide enviar mientras FACTURAPI_MODE no sea 'live').
define('FACTURACION_ENVIO_CORREO_ACTIVO', false);

// S2-a: resguardo propio del comprobante. Los CFDI viven en FacturAPI, pero el SAT
// obliga a conservar el XML 5 años y la caída de suscripción del 24-sep-2026 dejó claro
// que depender de ellos significa perder acceso a nuestros propios comprobantes. Se
// guarda una copia en archivos_facturas/ (protegida con .htaccess deny) y el proxy de
// descarga la sirve de ahí, pegando a FacturAPI solo si el archivo local falta.
// Regresa la ruta relativa guardada, o null si no se pudo escribir (nunca lanza: el
// timbrado ya ocurrió ante el SAT y no debe fallar por un problema de disco).
if (!defined('APEX_DIR_FACTURAS')) define('APEX_DIR_FACTURAS', __DIR__ . '/../archivos_facturas');

function _guardarArchivoFacturaLocal($bin, $folioInterno, $ext) {
    if ($bin === null || $bin === '') return null;
    // Nombre determinista y sin datos del cliente: folio interno + año-mes de guardado.
    $nombre = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$folioInterno) . '.' . $ext;
    $subdir = APEX_DIR_FACTURAS . '/' . date('Y-m');
    if (!is_dir($subdir)) {
        if (!@mkdir($subdir, 0775, true)) {
            error_log('APEX Facturacion: no se pudo crear '.$subdir);
            return null;
        }
        // El modo de mkdir() lo recorta el umask del proceso (PHP-FPM corre con 022, asi
        // que 0775 acababa en 0755 y la carpeta quedaba escribible SOLO por su dueno).
        // Se fuerza explicitamente para que el grupo tambien pueda mantenerla — sin esto,
        // cualquier limpieza o respaldo hecho por otro usuario del grupo falla en silencio.
        @chmod($subdir, 02775);
    }
    $ruta = $subdir . '/' . $nombre;
    if (@file_put_contents($ruta, $bin) === false) {
        error_log('APEX Facturacion: no se pudo escribir '.$ruta);
        return null;
    }
    @chmod($ruta, 0664);
    return date('Y-m') . '/' . $nombre;   // ruta relativa a archivos_facturas/
}

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
               f.estatus, f.uuid, f.modo, f.pdf_url, f.motivo_cancel, f.sustituye_uuid, f.pac_cancel_status, f.created_at,
               f.verification_url, f.fecha_timbrado, f.timbrado_por, f.cancelado_por, f.cancelado_at,
               (f.xml_path IS NOT NULL) AS tiene_resguardo, f.creado_por,
               f.global_periodicidad, f.global_meses, f.global_anio,
               f.relacion_tipo, f.relacion_uuid
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
        $stmt = $pdo->prepare("SELECT folio_interno FROM facturas WHERE id=? AND estatus='borrador'");
        $stmt->execute([$id]);
        $folioInterno = $stmt->fetchColumn();
        if (!$folioInterno) {
            jsonResponse(['ok'=>false,'error'=>'Factura no encontrada o ya timbrada']); exit;
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
    if (!empty($fac['orden_folio'])) {
        $stmt = $pdo->prepare("
            SELECT folio_interno FROM facturas
            WHERE orden_folio = ? AND id <> ? AND estatus IN ('timbrada','timbrando')
            LIMIT 1
        ");
        $stmt->execute([$fac['orden_folio'], $id]);
        if ($otra = $stmt->fetchColumn()) {
            $abortar('La orden '.$fac['orden_folio'].' ya tiene la factura '.$otra.' timbrada. Si necesitas refacturar, cancela primero la anterior.');
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
        $abortar('Error de conexión con FacturAPI: '.$curlErr);
    }

    $res = json_decode($response, true);

    if ($httpCode !== 200) {
        $msg = $res['message'] ?? $res['error'] ?? 'Error desconocido de FacturAPI';
        error_log('APEX FacturAPI error '.$httpCode.': '.$response);
        $abortar('FacturAPI: '.$msg);
    }

    // Guardar resultado en BD
    $uuid        = $res['uuid']         ?? '';
    $facurapiId  = $res['id']           ?? '';
    $pdfUrl      = 'https://www.facturapi.io/v2/invoices/' . $facurapiId . '/pdf';
    $xmlUrl      = 'https://www.facturapi.io/v2/invoices/' . $facurapiId . '/xml';

    // S2-b: el PAC es la verdad, no nuestro cálculo. Antes se guardaba el total que
    // calculamos nosotros y nunca se comparaba contra el del comprobante: si el PAC
    // redondeaba distinto, BD y CFDI quedaban desalineados sin que nadie se enterara.
    // Ahora se guarda el total del PAC y la diferencia queda en el log si existe.
    // (FacturAPI no devuelve 'subtotal' a nivel raíz, solo 'total' — verificado contra
    // la API real el 26-sep-2026; el subtotal/IVA nuestros se conservan tal cual.)
    $totalPac = isset($res['total']) ? round((float)$res['total'], 2) : null;
    if ($totalPac !== null && abs($totalPac - (float)$fac['total']) > 0.005) {
        error_log('APEX Facturacion: total del PAC ('.$totalPac.') distinto al calculado ('
            .$fac['total'].') en factura id='.$id.' — se guarda el del PAC');
    }
    // Datos del timbre que antes se tiraban: la liga de verificación del SAT (no se
    // puede reconstruir sola, incluye parte del sello) y la fecha REAL del timbrado
    // según el PAC, que no es la misma que la fecha capturada en el formulario.
    $verifUrl = $res['verification_url'] ?? null;
    $fechaTim = null;
    if (!empty($res['date'])) {
        try { $fechaTim = (new DateTime($res['date']))->setTimezone(new DateTimeZone('America/Monterrey'))->format('Y-m-d H:i:s'); }
        catch (Exception $e) { $fechaTim = null; }
    }

    // S2-a: una sola descarga de PDF/XML sirve para el resguardo local Y para el correo
    // (antes solo se descargaban si había correos que notificar, y no se guardaban).
    $pdfBin  = _descargarArchivoFacturapi($pdfUrl);
    $xmlBin  = _descargarArchivoFacturapi($xmlUrl);
    $pdfPath = _guardarArchivoFacturaLocal($pdfBin, $fac['folio_interno'], 'pdf');
    $xmlPath = _guardarArchivoFacturaLocal($xmlBin, $fac['folio_interno'], 'xml');
    if ($xmlPath === null) {
        // No se aborta (el CFDI ya existe ante el SAT) pero sí se deja constancia fuerte:
        // sin el XML en disco volvemos a depender de FacturAPI para conservarlo.
        error_log('APEX Facturacion: OJO, no se pudo resguardar el XML de la factura id='.$id
            .' — el comprobante solo existe en FacturAPI');
    }

    // El UPDATE final confirma desde la reserva 'timbrando' (A-9b) — si por alguna
    // razón la factura ya no estuviera en ese estatus, no se sobreescribe nada.
    $stmt = $pdo->prepare("
        UPDATE facturas SET
            estatus='timbrada', facturapi_id=?, uuid=?,
            pdf_url=?, xml_url=?, pdf_path=?, xml_path=?,
            verification_url=?, fecha_timbrado=?,
            total = COALESCE(?, total),
            timbrado_por=?, timbrado_at=NOW(), updated_at=NOW()
        WHERE id=? AND estatus='timbrando'
    ");
    $stmt->execute([$facurapiId, $uuid, $pdfUrl, $xmlUrl, $pdfPath, $xmlPath,
                    $verifUrl, $fechaTim, $totalPac, $user['nombre'], $id]);

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

    jsonResponse([
        'ok'             => true,
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

    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? AND estatus='timbrada'");
    $stmt->execute([$id]);
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

    // S2-c: queda registrado quien cancelo y cuando (antes solo se sabia quien habia
    // creado el borrador). cancelado_at se sella al PEDIR la cancelacion, aunque el SAT
    // la deje 'pending' en espera de que el receptor la acepte.
    $stmt = $pdo->prepare("
        UPDATE facturas SET
            estatus=?, motivo_cancel=?, sustituye_uuid=?, pac_cancel_status=?,
            cancelado_por=?, cancelado_at=NOW(), updated_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([
        $esFirme ? 'cancelada' : 'timbrada',
        $motivo,
        $motivo === '01' ? $substitucion : null,
        $pacStatus,
        $user['nombre'],
        $id
    ]);

    jsonResponse(['ok'=>true, 'estatus'=>$pacStatus, 'firme'=>$esFirme]);
    exit;
}

// ── GET verificar_cancelacion (re-consulta a FacturAPI el estatus real de una cancelación 'pending') ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $accion === 'verificar_cancelacion') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { jsonResponse(['ok'=>false,'error'=>'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? AND pac_cancel_status IS NOT NULL");
    $stmt->execute([$id]);
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
