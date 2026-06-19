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

$esCampanas  = in_array($rol, ['dir_admin','dueno','comercial']);
$puedeEnviar = in_array($rol, ['dir_admin','dueno']);

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
function enviarMensajeWA($payload) {
    $url = 'https://graph.facebook.com/v20.0/' . WA_PHONE_ID . '/messages';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . WA_TOKEN,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($resp, true);
    return ['code' => $code, 'data' => $data];
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
    $stmt2 = $db->prepare("SELECT ce.*, c.nombre as nombre_cliente
        FROM campana_envios ce
        LEFT JOIN clientes c ON c.id = ce.cliente_id
        WHERE ce.campana_id = ?
        ORDER BY c.nombre ASC");
    $stmt2->execute([$id]);
    echo json_encode(['campana' => $campana, 'envios' => $stmt2->fetchAll(PDO::FETCH_ASSOC)]);
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
        SELECT wc.id, wc.cliente_id, c.nombre as nombre_cliente, wc.telefono,
               wc.ultima_actividad, wc.mensajes_sin_leer, wc.estado,
               (SELECT contenido FROM whatsapp_mensajes wm
                WHERE wm.conversacion_id = wc.id
                ORDER BY wm.created_at DESC LIMIT 1) as ultimo_mensaje
        FROM whatsapp_conversaciones wc
        LEFT JOIN clientes c ON c.id = wc.cliente_id
        ORDER BY wc.ultima_actividad DESC");
    echo json_encode(['conversaciones' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
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

    $nombre       = trim($body['nombre'] ?? '');
    $template     = trim($body['template_nombre'] ?? '');
    $varsJson     = json_encode($body['template_vars'] ?? []);
    $segmentoJson = json_encode($body['segmento'] ?? []);
    $clienteIds   = $body['cliente_ids'] ?? [];

    if (!$nombre || !$template || !$clienteIds) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos']);
        exit;
    }

    $db->beginTransaction();
    $stmt = $db->prepare("INSERT INTO campanas
        (nombre, template_nombre, template_vars_json, segmento_json, creado_por, total_destinatarios)
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nombre, $template, $varsJson, $segmentoJson, $user['username'], count($clienteIds)]);
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

    $stmtE = $db->prepare("SELECT ce.id, ce.telefono, c.nombre as nombre_cliente
        FROM campana_envios ce
        LEFT JOIN clientes c ON c.id = ce.cliente_id
        WHERE ce.campana_id = ? AND ce.estado = 'pendiente'");
    $stmtE->execute([$campanaId]);
    $envios = $stmtE->fetchAll(PDO::FETCH_ASSOC);

    $vars    = json_decode($campana['template_vars_json'], true) ?? [];
    $stmtUpd = $db->prepare("UPDATE campana_envios SET estado=?, wa_message_id=?, enviado_at=NOW(), error_msg=? WHERE id=?");
    $stmtCnt = $db->prepare("UPDATE campanas SET enviados = enviados + 1 WHERE id=?");

    $enviados  = 0;
    $inicioMin = time();

    foreach ($envios as $envio) {
        if ($enviados > 0 && $enviados % 25 === 0) {
            $transcurrido = time() - $inicioMin;
            if ($transcurrido < 60) {
                sleep(60 - $transcurrido);
            }
            $inicioMin = time();
        }

        $parametros = [];
        foreach ($vars as $var) {
            $valor = $var;
            if ($var === '{{nombre_cliente}}') {
                $valor = $envio['nombre_cliente'] ?? 'Cliente';
            }
            $parametros[] = ['type' => 'text', 'text' => $valor];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $envio['telefono'],
            'type'              => 'template',
            'template'          => [
                'name'       => $campana['template_nombre'],
                'language'   => ['code' => 'es_MX'],
                'components' => [['type' => 'body', 'parameters' => $parametros]]
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
    echo json_encode(['ok' => true, 'enviados' => $enviados]);
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
       ->execute([$convId, $mensaje, $waId, $user['username']]);

    $db->prepare("UPDATE whatsapp_conversaciones SET ultima_actividad=NOW() WHERE id=?")
       ->execute([$convId]);
    echo json_encode(['ok' => true, 'wa_message_id' => $waId]);
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

http_response_code(400);
echo json_encode(['error' => 'Accion no reconocida']);
