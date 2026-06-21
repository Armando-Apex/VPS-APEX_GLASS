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

    if ($mode === 'subscribe' && $token === WA_VERIFY_TOKEN && preg_match('/^\w+$/', $challenge)) {
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
                $telefono  = preg_replace('/\D/', '', $msg['from'] ?? '');
                $waId      = $msg['id'] ?? '';
                $tipo      = $msg['type'] ?? 'texto';
                $contenido = '';

                // Validar teléfono y wa_message_id
                if (!$telefono || strlen($telefono) < 10 || strlen($telefono) > 15) continue;
                if (!$waId) continue;

                // Protección anti-replay: ignorar wa_message_id ya procesado
                $stmtDup = $db->prepare("SELECT id FROM whatsapp_mensajes WHERE wa_message_id = ? LIMIT 1");
                $stmtDup->execute([$waId]);
                if ($stmtDup->fetch()) continue;

                if ($tipo === 'text') {
                    $contenido = $msg['text']['body'] ?? '';
                    $tipo = 'texto';
                } elseif ($tipo === 'image') {
                    $mediaId = $msg['image']['id'] ?? '';
                    $contenido = '[Imagen recibida]';
                    if ($mediaId) {
                        // Obtener URL de descarga de Meta
                        $chMeta = curl_init('https://graph.facebook.com/v20.0/' . $mediaId);
                        curl_setopt($chMeta, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chMeta, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                        curl_setopt($chMeta, CURLOPT_TIMEOUT, 10);
                        $metaRes = json_decode(curl_exec($chMeta), true);
                        curl_close($chMeta);
                        $downloadUrl = $metaRes['url'] ?? '';
                        if ($downloadUrl) {
                            // Descargar imagen
                            $chImg = curl_init($downloadUrl);
                            curl_setopt($chImg, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($chImg, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                            curl_setopt($chImg, CURLOPT_TIMEOUT, 20);
                            $imgData = curl_exec($chImg);
                            curl_close($chImg);
                            if ($imgData) {
                                $mime      = $metaRes['mime_type'] ?? 'image/jpeg';
                                $ext       = (strpos($mime, 'png') !== false) ? 'png' : ((strpos($mime, 'gif') !== false) ? 'gif' : 'jpg');
                                $localName = uniqid('wa_in_', true) . '.' . $ext;
                                $localDir  = dirname(__DIR__) . '/archivos_campanas/wa_media/';
                                file_put_contents($localDir . $localName, $imgData);
                                $contenido = '/produccion/archivos_campanas/wa_media/' . $localName;
                            }
                        }
                    }
                    $tipo = 'imagen';
                } elseif ($tipo === 'document') {
                    $contenido = $msg['document']['filename'] ?? 'documento';
                    $tipo = 'documento';
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

                // Queries explícitas por estado — sin interpolación dinámica de columnas
                if ($nuevoEstado === 'entregado') {
                    $db->prepare("UPDATE campana_envios SET estado='entregado', entregado_at=NOW() WHERE id=?")
                       ->execute([$envio['id']]);
                    $db->prepare("UPDATE campanas SET entregados = entregados + 1 WHERE id=?")
                       ->execute([$envio['campana_id']]);
                } elseif ($nuevoEstado === 'leido') {
                    $db->prepare("UPDATE campana_envios SET estado='leido', leido_at=NOW() WHERE id=?")
                       ->execute([$envio['id']]);
                    $db->prepare("UPDATE campanas SET leidos = leidos + 1 WHERE id=?")
                       ->execute([$envio['campana_id']]);
                } elseif ($nuevoEstado === 'enviado') {
                    $db->prepare("UPDATE campana_envios SET estado='enviado' WHERE id=?")
                       ->execute([$envio['id']]);
                } elseif ($nuevoEstado === 'fallido') {
                    $db->prepare("UPDATE campana_envios SET estado='fallido' WHERE id=?")
                       ->execute([$envio['id']]);
                }
            }
        }
    }

    http_response_code(200);
    echo 'OK';
    exit;
}

http_response_code(405);
