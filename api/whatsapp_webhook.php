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
                        // Validar dominio Meta (anti-SSRF)
                        $urlHost = parse_url($downloadUrl, PHP_URL_HOST) ?? '';
                        $metaDomains = ['lookaside.fbsbx.com','scontent.whatsapp.net','mmg.whatsapp.net','media.fbcdn.net'];
                        $dominioValido = false;
                        foreach ($metaDomains as $d) {
                            if ($urlHost === $d || substr($urlHost, -(strlen($d)+1)) === '.'.$d) {
                                $dominioValido = true; break;
                            }
                        }
                        if ($downloadUrl && $dominioValido) {
                            // Descargar imagen — límite 5MB
                            $chImg = curl_init($downloadUrl);
                            curl_setopt($chImg, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($chImg, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                            curl_setopt($chImg, CURLOPT_TIMEOUT, 20);
                            curl_setopt($chImg, CURLOPT_MAXFILESIZE, 5 * 1024 * 1024);
                            curl_setopt($chImg, CURLOPT_NOPROGRESS, false);
                            curl_setopt($chImg, CURLOPT_PROGRESSFUNCTION, function($res, $dlTotal, $dlNow) {
                                return ($dlNow > 5 * 1024 * 1024) ? 1 : 0;
                            });
                            $imgData = curl_exec($chImg);
                            curl_close($chImg);
                            if ($imgData && strlen($imgData) <= 5 * 1024 * 1024) {
                                $mime      = $metaRes['mime_type'] ?? 'image/jpeg';
                                $extMap    = ['image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
                                $ext       = $extMap[$mime] ?? 'jpg';
                                $localName = uniqid('wa_in_', true) . '.' . $ext;
                                $localDir  = dirname(__DIR__) . '/archivos_campanas/wa_media/';
                                file_put_contents($localDir . $localName, $imgData);
                                $contenido = '/produccion/archivos_campanas/wa_media/' . $localName;
                            }
                        }
                    }
                    $tipo = 'imagen';
                } elseif ($tipo === 'document') {
                    $origName  = $msg['document']['filename'] ?? 'documento';
                    $mediaId   = $msg['document']['id'] ?? '';
                    $contenido = $origName; // fallback si falla la descarga
                    if ($mediaId) {
                        $chMeta = curl_init('https://graph.facebook.com/v20.0/' . $mediaId);
                        curl_setopt($chMeta, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chMeta, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                        curl_setopt($chMeta, CURLOPT_TIMEOUT, 10);
                        $metaRes     = json_decode(curl_exec($chMeta), true);
                        curl_close($chMeta);
                        $downloadUrl = $metaRes['url'] ?? '';
                        $urlHost     = parse_url($downloadUrl, PHP_URL_HOST) ?? '';
                        $metaDomains = ['lookaside.fbsbx.com','scontent.whatsapp.net','mmg.whatsapp.net','media.fbcdn.net'];
                        $dominioValido = false;
                        foreach ($metaDomains as $d) {
                            if ($urlHost === $d || substr($urlHost, -(strlen($d)+1)) === '.'.$d) {
                                $dominioValido = true; break;
                            }
                        }
                        if ($downloadUrl && $dominioValido) {
                            $chDoc = curl_init($downloadUrl);
                            curl_setopt($chDoc, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($chDoc, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                            curl_setopt($chDoc, CURLOPT_TIMEOUT, 30);
                            $docData = curl_exec($chDoc);
                            curl_close($chDoc);
                            if ($docData && strlen($docData) <= 20 * 1024 * 1024) {
                                $mime      = $metaRes['mime_type'] ?? 'application/octet-stream';
                                $extMap    = ['application/pdf'=>'pdf','application/msword'=>'doc',
                                              'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
                                              'application/vnd.ms-excel'=>'xls',
                                              'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx'];
                                $ext       = $extMap[$mime] ?? pathinfo($origName, PATHINFO_EXTENSION) ?: 'bin';
                                $localName = uniqid('wa_doc_', true) . '.' . $ext;
                                $localDir  = dirname(__DIR__) . '/archivos_campanas/wa_media/';
                                file_put_contents($localDir . $localName, $docData);
                                $contenido = '/produccion/archivos_campanas/wa_media/' . $localName . '|' . $origName;
                            }
                        }
                    }
                    $tipo = 'documento';
                } elseif ($tipo === 'reaction') {
                    $emoji = $msg['reaction']['emoji'] ?? '';
                    if (!$emoji) continue; // reacción eliminada — ignorar
                    $contenido = 'Reaccionó: ' . $emoji;
                    $tipo = 'texto';
                } elseif ($tipo === 'audio') {
                    $mediaId = $msg['audio']['id'] ?? '';
                    $contenido = '[Nota de voz]';
                    if ($mediaId) {
                        $chMeta = curl_init('https://graph.facebook.com/v20.0/' . $mediaId);
                        curl_setopt($chMeta, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chMeta, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                        curl_setopt($chMeta, CURLOPT_TIMEOUT, 10);
                        $metaRes = json_decode(curl_exec($chMeta), true);
                        curl_close($chMeta);
                        $downloadUrl = $metaRes['url'] ?? '';
                        $urlHost = parse_url($downloadUrl, PHP_URL_HOST) ?? '';
                        $metaDomains = ['lookaside.fbsbx.com','scontent.whatsapp.net','mmg.whatsapp.net','media.fbcdn.net'];
                        $dominioValido = false;
                        foreach ($metaDomains as $d) {
                            if ($urlHost === $d || substr($urlHost, -(strlen($d)+1)) === '.'.$d) {
                                $dominioValido = true; break;
                            }
                        }
                        if ($downloadUrl && $dominioValido) {
                            $chAud = curl_init($downloadUrl);
                            curl_setopt($chAud, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($chAud, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                            curl_setopt($chAud, CURLOPT_TIMEOUT, 30);
                            curl_setopt($chAud, CURLOPT_NOPROGRESS, false);
                            curl_setopt($chAud, CURLOPT_PROGRESSFUNCTION, function($res, $dlTotal, $dlNow) {
                                return ($dlNow > 16 * 1024 * 1024) ? 1 : 0;
                            });
                            $audData = curl_exec($chAud);
                            curl_close($chAud);
                            if ($audData && strlen($audData) <= 16 * 1024 * 1024) {
                                $mime = $metaRes['mime_type'] ?? 'audio/ogg';
                                $extMap = ['audio/ogg'=>'ogg','audio/mpeg'=>'mp3','audio/mp4'=>'m4a','audio/aac'=>'aac','audio/amr'=>'amr'];
                                $mimeBase = explode(';', $mime)[0];
                                $ext = $extMap[$mimeBase] ?? 'ogg';
                                $localName = uniqid('wa_in_', true) . '.' . $ext;
                                $localDir  = dirname(__DIR__) . '/archivos_campanas/wa_media/';
                                file_put_contents($localDir . $localName, $audData);
                                $contenido = '/produccion/archivos_campanas/wa_media/' . $localName;
                            }
                        }
                    }
                    $tipo = 'audio';
                } elseif ($tipo === 'video') {
                    $mediaId   = $msg['video']['id'] ?? '';
                    $contenido = '[Video]';
                    if ($mediaId) {
                        $chMeta = curl_init('https://graph.facebook.com/v20.0/' . $mediaId);
                        curl_setopt($chMeta, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chMeta, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                        curl_setopt($chMeta, CURLOPT_TIMEOUT, 10);
                        $metaRes     = json_decode(curl_exec($chMeta), true);
                        curl_close($chMeta);
                        $downloadUrl = $metaRes['url'] ?? '';
                        $urlHost     = parse_url($downloadUrl, PHP_URL_HOST) ?? '';
                        $metaDomains = ['lookaside.fbsbx.com','scontent.whatsapp.net','mmg.whatsapp.net','media.fbcdn.net'];
                        $dominioValido = false;
                        foreach ($metaDomains as $d) {
                            if ($urlHost === $d || substr($urlHost, -(strlen($d)+1)) === '.'.$d) { $dominioValido = true; break; }
                        }
                        if ($downloadUrl && $dominioValido) {
                            $chVid = curl_init($downloadUrl);
                            curl_setopt($chVid, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($chVid, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                            curl_setopt($chVid, CURLOPT_TIMEOUT, 60);
                            $vidData = curl_exec($chVid);
                            curl_close($chVid);
                            if ($vidData && strlen($vidData) <= 16 * 1024 * 1024) {
                                $localName = uniqid('wa_vid_', true) . '.mp4';
                                $localDir  = dirname(__DIR__) . '/archivos_campanas/wa_media/';
                                file_put_contents($localDir . $localName, $vidData);
                                $contenido = '/produccion/archivos_campanas/wa_media/' . $localName;
                            }
                        }
                    }
                    $tipo = 'video';
                } elseif ($tipo === 'sticker') {
                    $mediaId   = $msg['sticker']['id'] ?? '';
                    $contenido = '[Sticker]';
                    if ($mediaId) {
                        $chMeta = curl_init('https://graph.facebook.com/v20.0/' . $mediaId);
                        curl_setopt($chMeta, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chMeta, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                        curl_setopt($chMeta, CURLOPT_TIMEOUT, 10);
                        $metaRes     = json_decode(curl_exec($chMeta), true);
                        curl_close($chMeta);
                        $downloadUrl = $metaRes['url'] ?? '';
                        $urlHost     = parse_url($downloadUrl, PHP_URL_HOST) ?? '';
                        $metaDomains = ['lookaside.fbsbx.com','scontent.whatsapp.net','mmg.whatsapp.net','media.fbcdn.net'];
                        $dominioValido = false;
                        foreach ($metaDomains as $d) {
                            if ($urlHost === $d || substr($urlHost, -(strlen($d)+1)) === '.'.$d) { $dominioValido = true; break; }
                        }
                        if ($downloadUrl && $dominioValido) {
                            $chImg = curl_init($downloadUrl);
                            curl_setopt($chImg, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($chImg, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . WA_TOKEN]);
                            curl_setopt($chImg, CURLOPT_TIMEOUT, 15);
                            $imgData = curl_exec($chImg);
                            curl_close($chImg);
                            if ($imgData && strlen($imgData) <= 1 * 1024 * 1024) {
                                $localName = uniqid('wa_stk_', true) . '.webp';
                                $localDir  = dirname(__DIR__) . '/archivos_campanas/wa_media/';
                                file_put_contents($localDir . $localName, $imgData);
                                $contenido = '/produccion/archivos_campanas/wa_media/' . $localName;
                            }
                        }
                    }
                    $tipo = 'imagen';
                } elseif ($tipo === 'location') {
                    $lat = $msg['location']['latitude']  ?? '';
                    $lng = $msg['location']['longitude'] ?? '';
                    $contenido = $lat . ',' . $lng;
                    $tipo = 'ubicacion';
                } elseif ($tipo === 'interactive') {
                    $contenido = $msg['interactive']['button_reply']['title']
                              ?? $msg['interactive']['list_reply']['title']
                              ?? '[Respuesta interactiva]';
                    $tipo = 'texto';
                } else {
                    $contenido = '[' . $tipo . ']';
                    $tipo = 'texto';
                }

                // Buscar o crear conversación — por últimos 10 dígitos para tolerar 52 vs 521
                $stmtConv = $db->prepare("SELECT id, cliente_id FROM whatsapp_conversaciones WHERE RIGHT(REGEXP_REPLACE(telefono,'[^0-9]',''),10) = ?");
                $stmtConv->execute([substr($telefono, -10)]);
                $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);

                if (!$conv) {
                    $stmtCli = $db->prepare("SELECT id FROM clientes WHERE REGEXP_REPLACE(telefono,'[^0-9]','') LIKE ? OR REGEXP_REPLACE(telefono_alterno,'[^0-9]','') LIKE ?");
                    $stmtCli->execute(['%' . substr($telefono, -10), '%' . substr($telefono, -10)]);
                    $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);
                    $clienteId = $cli ? $cli['id'] : null;

                    $db->prepare("INSERT INTO whatsapp_conversaciones (cliente_id, telefono, ultima_actividad) VALUES (?,?,NOW())")
                       ->execute([$clienteId, $telefono]);
                    $convId = $db->lastInsertId();
                } else {
                    $convId = $conv['id'];
                    // Si la conversación existe pero sin cliente vinculado, intentar enlazar ahora
                    if (!$conv['cliente_id']) {
                        $stmtCli2 = $db->prepare("SELECT id FROM clientes WHERE REGEXP_REPLACE(telefono,'[^0-9]','') LIKE ? OR REGEXP_REPLACE(telefono_alterno,'[^0-9]','') LIKE ?");
                        $stmtCli2->execute(['%' . substr($telefono, -10), '%' . substr($telefono, -10)]);
                        $cli2 = $stmtCli2->fetch(PDO::FETCH_ASSOC);
                        if ($cli2) {
                            $db->prepare("UPDATE whatsapp_conversaciones SET cliente_id=? WHERE id=?")
                               ->execute([$cli2['id'], $convId]);
                        }
                    }
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
