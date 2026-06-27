<?php
// ============================================================
//  APEX GLASS - API: Campañas WhatsApp
//  Archivo: api/campanas.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');

$user   = requireSessionApi();
$rol    = $user['rol'];
$db     = getDB();
$accion = $_GET['accion'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

$esCampanas  = in_array($rol, ['dir_admin','dueno','comercial','administracion','desarrollo']);
$puedeEnviar = in_array($rol, ['dir_admin','dueno','desarrollo']);

if (!$esCampanas) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

// ── Función: normalizar teléfono a 52XXXXXXXXXX ──────────────
function normalizarTelefono($tel) {
    $tel = preg_replace('/\D/', '', $tel);
    if (strlen($tel) === 10) {
        return '52' . $tel;
    }
    if (strlen($tel) === 12 && substr($tel, 0, 2) === '52') {
        return $tel;
    }
    return '52' . substr($tel, -10);
}

// ── Función: enviar mensaje a Meta Cloud API ─────────────────
require_once __DIR__ . '/wa_helper.php';

// ── GET mensajes sin leer (badge sidebar) ────────────────────
if ($metodo === 'GET' && $accion === 'sin_leer') {
    $stmt = $db->query("SELECT COALESCE(SUM(mensajes_sin_leer), 0) as total FROM whatsapp_conversaciones");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['total' => (int)$row['total']]);
    exit;
}

// ── GET listar plantillas aprobadas desde Meta ───────────────
if ($metodo === 'GET' && $accion === 'listar_plantillas') {
    $url = 'https://graph.facebook.com/v20.0/' . WA_WABA_ID .
           '/message_templates?fields=name,status,category,language,components&limit=100&access_token=' . WA_TOKEN;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $resp = curl_exec($ch);
    $code = curl_errno($ch) ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        http_response_code(502);
        echo json_encode(['error' => 'Error al consultar Meta API', 'code' => $code]);
        exit;
    }
    $data = json_decode($resp, true);
    $plantillas = [];
    foreach (($data['data'] ?? []) as $t) {
        if (($t['status'] ?? '') !== 'APPROVED') continue;
        $body = ''; $headerFormat = ''; $headerExample = '';
        foreach (($t['components'] ?? []) as $comp) {
            if (($comp['type'] ?? '') === 'BODY') { $body = $comp['text'] ?? ''; }
            if (($comp['type'] ?? '') === 'HEADER') {
                $headerFormat  = $comp['format'] ?? '';
                $headerExample = $comp['example']['header_handle'][0] ?? '';
            }
        }
        $plantillas[] = [
            'name'           => $t['name'],
            'category'       => $t['category'] ?? '',
            'language'       => $t['language'] ?? '',
            'body'           => $body,
            'header_format'  => $headerFormat,
            'header_example' => $headerExample,
        ];
    }
    usort($plantillas, function($a, $b) { return strcmp($a['name'], $b['name']); });
    echo json_encode(['plantillas' => $plantillas]);
    exit;
}

// ── GET listar campañas ──────────────────────────────────────
if ($metodo === 'GET' && $accion === 'listar') {
    $stmt = $db->query("SELECT id, nombre, template_nombre, estado,
        total_destinatarios, enviados, entregados, leidos, respuestas, created_at
        FROM campanas ORDER BY created_at DESC");
    echo json_encode(['campanas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── GET detalle campaña ──────────────────────────────────────
if ($metodo === 'GET' && $accion === 'detalle') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT * FROM campanas WHERE id = ?");
    $stmt->execute([$id]);
    $campana = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$campana) {
        http_response_code(404);
        echo json_encode(['error' => 'No encontrada']);
        exit;
    }
    $stmt2 = $db->prepare("SELECT ce.*,
        COALESCE(c.nombre, ce.nombre_override) as nombre_cliente
        FROM campana_envios ce
        LEFT JOIN clientes c ON c.id = ce.cliente_id
        WHERE ce.campana_id = ?
        ORDER BY nombre_cliente ASC");
    $stmt2->execute([$id]);
    echo json_encode(['campana' => $campana, 'envios' => $stmt2->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── GET prospectos por estado ────────────────────────────────
if ($metodo === 'GET' && $accion === 'prospectos_segmento') {
    $estado          = trim($_GET['estado'] ?? '');
    $excluirClientes = (int)($_GET['excluir_clientes'] ?? 1);

    $where  = ['activo = 1', "telefono != ''"];
    $params = [];
    if ($estado !== '') {
        $where[] = 'estado = ?';
        $params[] = $estado;
    }
    if ($excluirClientes) {
        $where[] = 'es_cliente = 0';
    }
    $sql  = "SELECT id, nombre, telefono, estado FROM prospectos WHERE " . implode(' AND ', $where) . " ORDER BY nombre ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['prospectos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── GET clientes por segmento ────────────────────────────────
if ($metodo === 'GET' && $accion === 'clientes_segmento') {
    $localidad   = $_GET['localidad'] ?? '';
    $ciudad      = trim($_GET['ciudad'] ?? '');
    $soloActivos = (int)($_GET['activos'] ?? 1);

    $where  = ["telefono IS NOT NULL", "telefono != ''"];
    $params = [];

    if ($soloActivos) {
        $where[] = 'activo = 1';
    }
    if ($localidad === 'LOCAL' || $localidad === 'FORANEO') {
        $where[] = 'localidad = ?';
        $params[] = strtolower($localidad);
    }
    if ($ciudad !== '') {
        $where[] = 'ciudad LIKE ?';
        $params[] = '%' . $ciudad . '%';
    }

    $sql  = "SELECT id, nombre, contacto, telefono, localidad, ciudad
             FROM clientes WHERE " . implode(' AND ', $where) . " ORDER BY nombre ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── GET progreso de envío ────────────────────────────────────
if ($metodo === 'GET' && $accion === 'progreso') {
    $id   = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("SELECT estado, total_destinatarios, enviados FROM campanas WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'No encontrada']);
        exit;
    }
    echo json_encode([
        'estado'   => $row['estado'],
        'total'    => (int)$row['total_destinatarios'],
        'enviados' => (int)$row['enviados']
    ]);
    exit;
}

// ── GET conversaciones ───────────────────────────────────────
if ($metodo === 'GET' && $accion === 'conversaciones') {
    $stmt = $db->query("
        SELECT wc.id, wc.cliente_id,
               COALESCE(c.nombre, cp.nombre, pr.nombre) as nombre_cliente,
               CASE
                   WHEN wc.cliente_id IS NOT NULL OR cp.id IS NOT NULL THEN 'cliente'
                   WHEN pr.id IS NOT NULL THEN 'prospecto'
                   ELSE 'desconocido'
               END as tipo_contacto,
               wc.telefono, wc.ultima_actividad, wc.mensajes_sin_leer, wc.estado,
               (SELECT contenido FROM whatsapp_mensajes wm
                WHERE wm.conversacion_id = wc.id
                ORDER BY wm.created_at DESC LIMIT 1) as ultimo_mensaje
        FROM whatsapp_conversaciones wc
        LEFT JOIN clientes c ON c.id = wc.cliente_id
        LEFT JOIN clientes cp ON wc.cliente_id IS NULL
            AND (RIGHT(REGEXP_REPLACE(cp.telefono,'[^0-9]',''), 10) = RIGHT(REGEXP_REPLACE(wc.telefono,'[^0-9]',''), 10)
              OR RIGHT(REGEXP_REPLACE(cp.telefono_alterno,'[^0-9]',''), 10) = RIGHT(REGEXP_REPLACE(wc.telefono,'[^0-9]',''), 10))
        LEFT JOIN prospectos pr ON wc.cliente_id IS NULL
            AND RIGHT(REGEXP_REPLACE(wc.telefono,'[^0-9]',''), 10) = pr.telefono
            AND pr.activo = 1
        ORDER BY wc.ultima_actividad DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Auto-vincular conversaciones sin cliente cuando el fallback por teléfono encontró uno
    $stmtVinc = $db->prepare("
        UPDATE whatsapp_conversaciones wc
        JOIN clientes cp ON (RIGHT(REGEXP_REPLACE(cp.telefono,'[^0-9]',''),10) = RIGHT(REGEXP_REPLACE(wc.telefono,'[^0-9]',''),10)
                          OR RIGHT(REGEXP_REPLACE(cp.telefono_alterno,'[^0-9]',''),10) = RIGHT(REGEXP_REPLACE(wc.telefono,'[^0-9]',''),10))
        SET wc.cliente_id = cp.id
        WHERE wc.cliente_id IS NULL");
    $stmtVinc->execute();
    echo json_encode(['conversaciones' => $rows]);
    exit;
}

// ── GET mensajes de conversación ─────────────────────────────
if ($metodo === 'GET' && $accion === 'mensajes') {
    $cid  = (int)($_GET['conversacion_id'] ?? 0);
    $stmt = $db->prepare("SELECT id, direccion, contenido, tipo, enviado_por, created_at
        FROM whatsapp_mensajes WHERE conversacion_id = ? ORDER BY created_at ASC");
    $stmt->execute([$cid]);
    echo json_encode(['mensajes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── POST crear campaña ───────────────────────────────────────
if ($metodo === 'POST' && $accion === 'crear') {
    if (!$puedeEnviar) {
        http_response_code(403);
        echo json_encode(['error' => 'Sin permiso']);
        exit;
    }
    $body = json_decode(file_get_contents('php://input'), true);

    $nombre         = trim($body['nombre'] ?? '');
    $template       = trim($body['template_nombre'] ?? '');
    $varsJson       = json_encode($body['template_vars'] ?? []);
    $segmentoJson   = json_encode($body['segmento'] ?? []);
    $clienteIds     = $body['cliente_ids']   ?? [];
    $prospectoIds   = $body['prospecto_ids'] ?? [];
    $headerImageUrl = trim($body['header_image_url'] ?? '');

    if (!$nombre || !$template || (empty($clienteIds) && empty($prospectoIds))) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos']);
        exit;
    }

    $total = count($clienteIds) + count($prospectoIds);

    $db->beginTransaction();
    $stmt = $db->prepare("INSERT INTO campanas
        (nombre, template_nombre, template_vars_json, header_image_url, segmento_json, creado_por, total_destinatarios)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $template, $varsJson, $headerImageUrl ?: null, $segmentoJson, $user['nombre'], $total]);
    $campanaId = $db->lastInsertId();

    $stmtCli = $db->prepare("SELECT id, nombre, telefono FROM clientes WHERE id = ?");
    $stmtIns = $db->prepare("INSERT INTO campana_envios (campana_id, cliente_id, telefono) VALUES (?, ?, ?)");
    foreach ($clienteIds as $cid) {
        $stmtCli->execute([(int)$cid]);
        $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);
        if ($cli && $cli['telefono']) {
            $tel = normalizarTelefono($cli['telefono']);
            $stmtIns->execute([$campanaId, $cli['id'], $tel]);
        }
    }

    $stmtPr  = $db->prepare("SELECT id, nombre, telefono FROM prospectos WHERE id = ?");
    $stmtInsPr = $db->prepare("INSERT INTO campana_envios (campana_id, prospecto_id, nombre_override, telefono) VALUES (?, ?, ?, ?)");
    foreach ($prospectoIds as $pid) {
        $stmtPr->execute([(int)$pid]);
        $pr = $stmtPr->fetch(PDO::FETCH_ASSOC);
        if ($pr && $pr['telefono']) {
            $tel = '52' . $pr['telefono'];
            $stmtInsPr->execute([$campanaId, $pr['id'], $pr['nombre'] ?: 'Prospecto', $tel]);
        }
    }

    $db->commit();
    echo json_encode(['ok' => true, 'id' => $campanaId]);
    exit;
}

// ── POST enviar campaña ──────────────────────────────────────
if ($metodo === 'POST' && $accion === 'enviar') {
    if (!$puedeEnviar) {
        http_response_code(403);
        echo json_encode(['error' => 'Sin permiso']);
        exit;
    }
    set_time_limit(600);
    $body = json_decode(file_get_contents('php://input'), true);
    $campanaId = (int)($body['campana_id'] ?? 0);

    $stmtC = $db->prepare("SELECT * FROM campanas WHERE id = ? AND estado IN ('borrador','cancelada')");
    $stmtC->execute([$campanaId]);
    $campana = $stmtC->fetch(PDO::FETCH_ASSOC);
    if (!$campana) {
        http_response_code(400);
        echo json_encode(['error' => 'Campaña no válida']);
        exit;
    }

    $db->prepare("UPDATE campanas SET estado='enviando' WHERE id=?")->execute([$campanaId]);

    $stmtE = $db->prepare("SELECT ce.id, ce.telefono,
        COALESCE(c.nombre, ce.nombre_override) as nombre_cliente,
        c.codigo as codigo_cliente
        FROM campana_envios ce
        LEFT JOIN clientes c ON c.id = ce.cliente_id
        WHERE ce.campana_id = ? AND ce.estado = 'pendiente'");
    $stmtE->execute([$campanaId]);
    $envios = $stmtE->fetchAll(PDO::FETCH_ASSOC);

    // Liberar sesión PHP antes de soltar la conexión HTTP.
    // Sin esto el archivo de sesión queda bloqueado durante todo el envío
    // y cualquier otra pestaña del sistema se congela esperando el lock.
    session_write_close();

    // Cerrar la conexión HTTP de inmediato — PHP-FPM sigue corriendo en background.
    // Evita que Apache (ProxyTimeout 300s) corte el envío de campañas largas.
    ignore_user_abort(true);
    echo json_encode(['ok' => true, 'en_proceso' => true, 'total' => count($envios)]);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        header('Connection: close');
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
        flush();
    }

    $vars    = json_decode($campana['template_vars_json'], true) ?? [];
    $stmtUpd = $db->prepare("UPDATE campana_envios SET estado=?, wa_message_id=?, enviado_at=NOW(), error_msg=? WHERE id=?");
    $stmtCnt = $db->prepare("UPDATE campanas SET enviados = enviados + 1 WHERE id=?");

    // Subir imagen a Media API para obtener media_id reutilizable en todos los envíos.
    // Las URLs scontent.whatsapp.net tienen tokens de sesión — Meta no las puede fetchear
    // desde sus servidores de entrega, causando fallido silencioso con wamid válido.
    $headerMediaId = null;
    if (!empty($campana['header_image_url'])) {
        $imgBytes = @file_get_contents($campana['header_image_url']);
        if ($imgBytes !== false) {
            $tmpImg = tempnam(sys_get_temp_dir(), 'wa_hdr_');
            file_put_contents($tmpImg, $imgBytes);
            $mime = mime_content_type($tmpImg) ?: 'image/jpeg';
            $chUp = curl_init('https://graph.facebook.com/v20.0/' . WA_PHONE_ID . '/media');
            curl_setopt($chUp, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chUp, CURLOPT_POST, true);
            curl_setopt($chUp, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
            curl_setopt($chUp, CURLOPT_POSTFIELDS, [
                'messaging_product' => 'whatsapp',
                'type'              => $mime,
                'file'              => new CURLFile($tmpImg, $mime, 'header.' . (explode('/', $mime)[1] ?? 'jpg'))
            ]);
            curl_setopt($chUp, CURLOPT_TIMEOUT, 30);
            $upRes = json_decode(curl_exec($chUp), true);
            curl_close($chUp);
            @unlink($tmpImg);
            $headerMediaId = $upRes['id'] ?? null;
            error_log('APEX WA upload header: ' . ($headerMediaId ? 'OK id=' . $headerMediaId : 'FAIL ' . json_encode($upRes)));
        }
    }

    $enviados = 0;

    foreach ($envios as $envio) {
        $parametros = [];
        foreach ($vars as $var) {
            $valor = $var;
            if ($var === '{{nombre_cliente}}') {
                $valor = $envio['nombre_cliente'] ?? 'Cliente';
            } elseif ($var === '{{codigo_portal}}') {
                $valor = $envio['codigo_cliente'] ?? '';
            }
            // Sanitizar: solo texto plano, máx 1024 chars (límite Meta)
            $valor = strip_tags((string)$valor);
            $valor = substr($valor, 0, 1024);
            $parametros[] = ['type' => 'text', 'text' => $valor];
        }

        $components = [];
        if (!empty($campana['header_image_url'])) {
            $imgParam = $headerMediaId
                ? ['id'   => $headerMediaId]
                : ['link' => $campana['header_image_url']];
            $components[] = [
                'type'       => 'header',
                'parameters' => [['type' => 'image', 'image' => $imgParam]]
            ];
        }
        if (!empty($parametros)) {
            $components[] = ['type' => 'body', 'parameters' => $parametros];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $envio['telefono'],
            'type'              => 'template',
            'template'          => [
                'name'       => $campana['template_nombre'],
                'language'   => ['code' => 'es_MX'],
                'components' => $components
            ]
        ];

        $res         = enviarMensajeWA($payload);
        $waId        = $res['data']['messages'][0]['id'] ?? null;
        $error       = ($res['code'] !== 200) ? substr(json_encode($res['data']), 0, 255) : null;
        $nuevoEstado = $waId ? 'enviado' : 'fallido';

        $stmtUpd->execute([$nuevoEstado, $waId, $error, $envio['id']]);
        if ($waId) {
            $stmtCnt->execute([$campanaId]);
        }
        $enviados++;
    }

    $db->prepare("UPDATE campanas SET estado='enviada' WHERE id=?")->execute([$campanaId]);
    exit;
}

// ── POST responder en conversación ───────────────────────────
if ($metodo === 'POST' && $accion === 'responder') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $convId  = (int)($body['conversacion_id'] ?? 0);
    $mensaje = trim($body['mensaje'] ?? '');
    if (!$convId || !$mensaje) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos']);
        exit;
    }

    $stmtConv = $db->prepare("SELECT * FROM whatsapp_conversaciones WHERE id = ?");
    $stmtConv->execute([$convId]);
    $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);
    if (!$conv) {
        http_response_code(404);
        echo json_encode(['error' => 'Conversación no encontrada']);
        exit;
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $conv['telefono'],
        'type'              => 'text',
        'text'              => ['body' => $mensaje]
    ];
    $res = enviarMensajeWA($payload);
    if ($res['code'] !== 200) {
        http_response_code(502);
        echo json_encode(['error' => 'Error Meta API', 'detalle' => $res['data']]);
        exit;
    }

    $waId = $res['data']['messages'][0]['id'] ?? null;
    $db->prepare("INSERT INTO whatsapp_mensajes
        (conversacion_id, direccion, contenido, tipo, wa_message_id, enviado_por)
        VALUES (?, 'outbound', ?, 'texto', ?, ?)")
       ->execute([$convId, $mensaje, $waId, $user['nombre']]);

    $db->prepare("UPDATE whatsapp_conversaciones SET ultima_actividad=NOW() WHERE id=?")
       ->execute([$convId]);
    echo json_encode(['ok' => true, 'wa_message_id' => $waId]);
    exit;
}

// ── POST enviar media (imagen/documento) en conversación ─────
if ($metodo === 'POST' && $accion === 'enviar_media') {
    $convId = (int)($_POST['conversacion_id'] ?? 0);
    if (!$convId || empty($_FILES['archivo'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos']);
        exit;
    }

    $stmtConv = $db->prepare("SELECT * FROM whatsapp_conversaciones WHERE id = ?");
    $stmtConv->execute([$convId]);
    $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);
    if (!$conv) {
        http_response_code(404);
        echo json_encode(['error' => 'Conversación no encontrada']);
        exit;
    }

    $file     = $_FILES['archivo'];
    $mime     = $file['type'];
    $tmpPath  = $file['tmp_name'];
    $origName = basename($file['name']);

    // Determinar tipo WA
    $tiposImagen = ['image/jpeg','image/png','image/gif','image/webp'];
    if (in_array($mime, $tiposImagen)) {
        $waType = 'image';
    } elseif ($mime === 'application/pdf') {
        $waType = 'document';
    } else {
        http_response_code(415);
        echo json_encode(['error' => 'Tipo de archivo no soportado']);
        exit;
    }

    // Guardar copia local con nombre único — extensión validada por whitelist + MIME real
    $extAllowed = [
        'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',  'gif'  => 'image/gif',
        'webp' => 'image/webp', 'pdf'  => 'application/pdf',
    ];
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!isset($extAllowed[$ext])) {
        http_response_code(415);
        echo json_encode(['error' => 'Extensión de archivo no permitida']);
        exit;
    }
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($tmpPath);
    if ($mimeReal !== $extAllowed[$ext]) {
        http_response_code(415);
        echo json_encode(['error' => 'Tipo de archivo no coincide con la extensión']);
        exit;
    }
    $localName = uniqid('wa_', true) . '.' . $ext;
    $localDir  = dirname(__DIR__) . '/archivos_campanas/wa_media/';
    $localPath = $localDir . $localName;
    $localUrl  = '/produccion/archivos_campanas/wa_media/' . $localName;
    move_uploaded_file($tmpPath, $localPath);
    $tmpPath = $localPath;

    // Subir a Meta Media API
    $urlMedia = 'https://graph.facebook.com/v20.0/' . WA_PHONE_ID . '/media';
    $ch = curl_init($urlMedia);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'messaging_product' => 'whatsapp',
        'type'              => $mime,
        'file'              => new CURLFile($tmpPath, $mime, $origName)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $mediaRes  = json_decode(curl_exec($ch), true);
    $mediaCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($mediaCode !== 200 || empty($mediaRes['id'])) {
        http_response_code(502);
        echo json_encode(['error' => 'Error subiendo media a Meta', 'detalle' => $mediaRes]);
        exit;
    }

    $mediaId = $mediaRes['id'];

    // Construir payload según tipo
    if ($waType === 'image') {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $conv['telefono'],
            'type'              => 'image',
            'image'             => ['id' => $mediaId]
        ];
    } else {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $conv['telefono'],
            'type'              => 'document',
            'document'          => ['id' => $mediaId, 'filename' => $origName]
        ];
    }

    $res = enviarMensajeWA($payload);
    if ($res['code'] !== 200) {
        http_response_code(502);
        echo json_encode(['error' => 'Error enviando mensaje', 'detalle' => $res['data']]);
        exit;
    }

    $waId    = $res['data']['messages'][0]['id'] ?? null;
    $tipoMsg   = ($waType === 'image') ? 'imagen' : 'documento';
    $contenido = ($waType === 'image') ? $localUrl : $origName;
    $db->prepare("INSERT INTO whatsapp_mensajes
        (conversacion_id, direccion, contenido, tipo, wa_message_id, enviado_por)
        VALUES (?, 'outbound', ?, ?, ?, ?)")
       ->execute([$convId, $contenido, $tipoMsg, $waId, $user['nombre']]);

    $db->prepare("UPDATE whatsapp_conversaciones SET ultima_actividad=NOW() WHERE id=?")
       ->execute([$convId]);

    echo json_encode(['ok' => true, 'wa_message_id' => $waId, 'tipo' => $tipoMsg]);
    exit;
}

// ── POST marcar conversación como leída ──────────────────────
if ($metodo === 'POST' && $accion === 'marcar_leido') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $convId = (int)($body['conversacion_id'] ?? 0);
    $db->prepare("UPDATE whatsapp_conversaciones SET mensajes_sin_leer=0 WHERE id=?")
       ->execute([$convId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── POST enviar cotización por WhatsApp (plantilla cotizacion_apex) ──
if ($metodo === 'POST' && $accion === 'enviar_cotizacion_wa') {
    $body           = json_decode(file_get_contents('php://input'), true);
    $cotizacionId   = (int)($body['cotizacion_id'] ?? 0);
    $telefonoRaw    = trim($body['telefono'] ?? '');
    $guardarAlterno = !empty($body['guardar_alterno']);

    if (!$cotizacionId || !$telefonoRaw) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos incompletos']);
        exit;
    }

    // Validar formato teléfono: solo dígitos, entre 10 y 15 caracteres
    $telefonoDigitos = preg_replace('/[^0-9]/', '', $telefonoRaw);
    if (strlen($telefonoDigitos) < 10 || strlen($telefonoDigitos) > 15) {
        http_response_code(400);
        echo json_encode(['error' => 'Número de teléfono inválido']);
        exit;
    }

    $esAdminWA = in_array($user['rol'], ['dir_admin', 'dueno', 'administracion', 'desarrollo']);

    // Cargar cotización — si no es admin, restringir a cotizaciones propias
    $sqlCot = "
        SELECT c.folio, c.cliente_nombre, c.proyecto, c.cliente_id,
               c.asesor_id, c.descuento, c.servicios_subtotal,
               COALESCE(SUM(cp.precio_m2_usado * cp.m2 * cp.cantidad), 0) as subtotal_bruto
        FROM cotizaciones c
        LEFT JOIN cotizaciones_partidas cp ON cp.cotizacion_id = c.id
        WHERE c.id = ?";
    $params = [$cotizacionId];
    if (!$esAdminWA) {
        $sqlCot .= " AND c.asesor_id = ?";
        $params[] = $user['id'];
    }
    $sqlCot .= " GROUP BY c.id";
    $stmtCot = $db->prepare($sqlCot);
    $stmtCot->execute($params);
    $cot = $stmtCot->fetch(PDO::FETCH_ASSOC);
    if (!$cot) {
        http_response_code(404);
        echo json_encode(['error' => 'Cotización no encontrada']);
        exit;
    }

    // Calcular total igual que imprimir_cotizacion.php
    $subtotal_neto = ($cot['descuento'] > 0)
        ? round($cot['subtotal_bruto'] * (1 - $cot['descuento'] / 100), 2)
        : (float)$cot['subtotal_bruto'];
    $base       = round($subtotal_neto + (float)($cot['servicios_subtotal'] ?? 0), 2);
    $totalFmt   = '$' . number_format(round($base * 1.16, 2), 2, '.', ',');

    $telefono = normalizarTelefono($telefonoRaw);

    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $telefono,
        'type'              => 'template',
        'template'          => [
            'name'       => 'cotizacion',
            'language'   => ['code' => 'es_MX'],
            'components' => [[
                'type'       => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => substr(strip_tags($cot['cliente_nombre']), 0, 60)],
                    ['type' => 'text', 'text' => substr(strip_tags($cot['folio']), 0, 20)],
                    ['type' => 'text', 'text' => substr(strip_tags($cot['proyecto'] ?: 'N/A'), 0, 100)],
                    ['type' => 'text', 'text' => $totalFmt],
                ]
            ]]
        ]
    ];

    $res  = enviarMensajeWA($payload);
    $waId = $res['data']['messages'][0]['id'] ?? null;

    if (!$waId) {
        http_response_code(502);
        echo json_encode(['error' => 'Error al enviar', 'detalle' => $res['data']]);
        exit;
    }

    // Guardar en conversación para que aparezca en el inbox
    // Buscar por últimos 10 dígitos para evitar doble chat por formato 52 vs 521
    $stmtConv = $db->prepare("SELECT id FROM whatsapp_conversaciones WHERE RIGHT(REGEXP_REPLACE(telefono,'[^0-9]',''),10) = ?");
    $stmtConv->execute([substr($telefonoDigitos, -10)]);
    $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);
    if (!$conv) {
        $db->prepare("INSERT INTO whatsapp_conversaciones (cliente_id, telefono, ultima_actividad) VALUES (?,?,NOW())")
           ->execute([$cot['cliente_id'] ?: null, $telefono]);
        $convId = $db->lastInsertId();
    } else {
        $convId = $conv['id'];
    }
    $resumen = 'Cotización ' . $cot['folio'] . ' — ' . $totalFmt;
    $db->prepare("INSERT INTO whatsapp_mensajes (conversacion_id, direccion, contenido, tipo, wa_message_id, enviado_por) VALUES (?,'outbound',?,?,?,?)")
       ->execute([$convId, $resumen, 'texto', $waId, $user['nombre']]);
    $db->prepare("UPDATE whatsapp_conversaciones SET ultima_actividad=NOW() WHERE id=?")
       ->execute([$convId]);

    // Guardar teléfono alterno si se solicitó
    if ($guardarAlterno && $cot['cliente_id']) {
        $db->prepare("UPDATE clientes SET telefono_alterno=? WHERE id=?")
           ->execute([$telefonoRaw, $cot['cliente_id']]);
    }

    echo json_encode(['ok' => true, 'wa_message_id' => $waId]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Accion no reconocida']);
