<?php
// ============================================================
//  APEX GLASS - WhatsApp Webhook (Meta Cloud API)
//  Archivo: api/whatsapp_webhook.php
// ============================================================
require_once __DIR__ . '/config.php';

// ── GET: verificación inicial de Meta ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode']         ?? $_GET['hub.mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? $_GET['hub.challenge']    ?? '';

    if ($mode === 'subscribe' && $token === WA_VERIFY_TOKEN) {
        http_response_code(200);
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

// ── POST: eventos de Meta ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawBody   = file_get_contents('php://input');
    $sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    $expected  = 'sha256=' . hash_hmac('sha256', $rawBody, WA_APP_SECRET);
    if (!hash_equals($expected, $sigHeader)) {
        http_response_code(401);
        exit;
    }

    $data = json_decode($rawBody, true);
    $db   = getDB();

    foreach (($data['entry'] ?? []) as $entry) {
        foreach (($entry['changes'] ?? []) as $change) {
            $value = $change['value'] ?? [];

            // ── Mensajes inbound (cliente escribió) ──────────
            foreach (($value['messages'] ?? []) as $msg) {
                $telefono  = $msg['from'] ?? '';
                $waId      = $msg['id']   ?? '';
                $tipo      = $msg['type'] ?? 'texto';
                $contenido = '';

                if ($tipo === 'text') {
                    $contenido = $msg['text']['body'] ?? '';
                    $tipo = 'texto';
                } elseif ($tipo === 'image') {
                    $contenido = '[Imagen recibida]';
                    $tipo = 'imagen';
                } else {
                    $contenido = '[Mensaje tipo: ' . $tipo . ']';
                    $tipo = 'texto';
                }

                // Buscar o crear conversación
                $stmtConv = $db->prepare("SELECT id FROM whatsapp_conversaciones WHERE telefono = ?");
                $stmtConv->execute([$telefono]);
                $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);

                if (!$conv) {
                    $stmtCli = $db->prepare("SELECT id FROM clientes WHERE REGEXP_REPLACE(telefono,'[^0-9]','') LIKE ?");
                    $stmtCli->execute(['%' . substr($telefono, -10)]);
                    $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);
                    $clienteId = $cli ? $cli['id'] : null;

                    $db->prepare("INSERT INTO whatsapp_conversaciones (cliente_id, telefono, ultima_actividad) VALUES (?,?,NOW())")
                       ->execute([$clienteId, $telefono]);
                    $convId = $db->lastInsertId();
                } else {
                    $convId = $conv['id'];
                }

                // Asociar a envío de campaña si existe
                $stmtEnv = $db->prepare("SELECT id, campana_id FROM campana_envios WHERE telefono = ? AND estado IN ('enviado','entregado','leido') ORDER BY enviado_at DESC LIMIT 1");
                $stmtEnv->execute([$telefono]);
                $envio = $stmtEnv->fetch(PDO::FETCH_ASSOC);
                $campanaEnvioId = $envio ? $envio['id'] : null;

                $db->prepare("INSERT INTO whatsapp_mensajes (conversacion_id, campana_envio_id, direccion, contenido, tipo, wa_message_id) VALUES (?,?,'inbound',?,?,?)")
                   ->execute([$convId, $campanaEnvioId, $contenido, $tipo, $waId]);

                $db->prepare("UPDATE whatsapp_conversaciones SET mensajes_sin_leer = mensajes_sin_leer + 1, ultima_actividad = NOW() WHERE id=?")
                   ->execute([$convId]);

                if ($envio) {
                    $db->prepare("UPDATE campanas SET respuestas = respuestas + 1 WHERE id=?")
                       ->execute([$envio['campana_id']]);
                }
            }

            // ── Actualizaciones de estado ─────────────────────
            foreach (($value['statuses'] ?? []) as $status) {
                $waId       = $status['id']     ?? '';
                $estado     = $status['status'] ?? '';
                $mapa       = ['sent' => 'enviado', 'delivered' => 'entregado', 'read' => 'leido', 'failed' => 'fallido'];
                $nuevoEstado = $mapa[$estado] ?? null;
                if (!$nuevoEstado || !$waId) continue;

                $stmtEnv = $db->prepare("SELECT id, campana_id, estado FROM campana_envios WHERE wa_message_id = ?");
                $stmtEnv->execute([$waId]);
                $envio = $stmtEnv->fetch(PDO::FETCH_ASSOC);
                if (!$envio) continue;

                $orden = ['pendiente'=>0,'enviado'=>1,'entregado'=>2,'leido'=>3,'fallido'=>4];
                $actualIdx = $orden[$envio['estado']] ?? 0;
                $nuevoIdx  = $orden[$nuevoEstado] ?? 0;
                if ($nuevoIdx <= $actualIdx && $nuevoEstado !== 'fallido') continue;

                $campoFecha = ['entregado'=>'entregado_at','leido'=>'leido_at'];
                $sqlFecha   = isset($campoFecha[$nuevoEstado]) ? ', ' . $campoFecha[$nuevoEstado] . '=NOW()' : '';

                $db->prepare("UPDATE campana_envios SET estado=?" . $sqlFecha . " WHERE id=?")
                   ->execute([$nuevoEstado, $envio['id']]);

                $campoContador = ['entregado'=>'entregados','leido'=>'leidos'];
                if (isset($campoContador[$nuevoEstado])) {
                    $col = $campoContador[$nuevoEstado];
                    $db->prepare("UPDATE campanas SET $col = $col + 1 WHERE id=?")
                       ->execute([$envio['campana_id']]);
                }
            }
        }
    }

    http_response_code(200);
    echo 'OK';
    exit;
}

http_response_code(405);
