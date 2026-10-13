<?php
// Función compartida para enviar mensajes vía Meta Cloud API WhatsApp
if (!function_exists('enviarMensajeWA')) {
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $resp   = curl_exec($ch);
        $errno  = curl_errno($ch);
        $code   = $errno ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr= $errno ? curl_error($ch) : null;
        curl_close($ch);
        $data = json_decode($resp, true);
        return ['code' => $code, 'data' => $data, 'curl_error' => $curlErr];
    }
}

// Ventana de servicio al cliente de WhatsApp (24h): solo se puede reabrir con un mensaje
// entrante DEL CLIENTE, nunca enviando algo nosotros. $ultimoMensajeClienteAt debe venir de
// whatsapp_conversaciones.ultimo_mensaje_cliente_at (NO de ultima_actividad, que también se
// mueve con nuestros propios envíos y por eso no sirve para esta validación).
if (!function_exists('waVentanaAbierta')) {
    function waVentanaAbierta($ultimoMensajeClienteAt) {
        if (!$ultimoMensajeClienteAt) return false;
        $diffSeg = time() - strtotime($ultimoMensajeClienteAt);
        return $diffSeg < 24 * 3600;
    }
}

// Envía la plantilla WA "orden_lista" al cliente cuando TODAS las piezas de la orden
// están en terminado/entregado. Una sola vez por orden (claim atómico sobre
// ordenes.wa_lista_enviado). Lo usan actualizar_estatus.php (escaneo pieza por pieza)
// y admin_ordenes.php (Corrección de Estatus masiva a "terminado").
if (!function_exists('waEnviarOrdenLista')) {
    function waEnviarOrdenLista(PDO $db, $ordenId) {
        $ordenId = (int)$ordenId;
        try {
            $stmtWa = $db->prepare('
                SELECT o.wa_lista_enviado, o.cliente_nombre, o.folio,
                       COALESCE(o.cliente_id, c.cliente_id) as cliente_id,
                       COALESCE(cl.telefono, cl2.telefono) as telefono,
                       COALESCE(cl.telefono_alterno, cl2.telefono_alterno) as telefono_alterno,
                       c.proyecto,
                       (SELECT COUNT(*) FROM piezas WHERE orden_id = o.id) as total_piezas
                FROM ordenes o
                LEFT JOIN clientes cl ON cl.id = o.cliente_id
                LEFT JOIN cotizaciones c ON c.orden_id = o.id
                LEFT JOIN clientes cl2 ON cl2.id = c.cliente_id
                WHERE o.id = ?
            ');
            $stmtWa->execute([$ordenId]);
            $ordWa = $stmtWa->fetch(PDO::FETCH_ASSOC);

            if ($ordWa && !$ordWa['wa_lista_enviado']) {
                // Verificar que ninguna pieza quede fuera de terminado/entregado
                $stmtPend = $db->prepare('
                    SELECT COUNT(*) FROM piezas
                    WHERE orden_id = ? AND estatus NOT IN ("terminado","entregado")
                ');
                $stmtPend->execute([$ordenId]);
                // BLO-14: claim atómico antes de mandar — dos escaneos casi simultáneos
                // de las 2 últimas piezas de la orden podían pasar ambos el chequeo de
                // arriba (wa_lista_enviado seguía en 0 para los dos) y mandar el WhatsApp
                // "orden lista" duplicado. Si Meta falla, se libera el claim para que el
                // próximo cambio de estatus lo vuelva a intentar (mismo comportamiento
                // de reintento que ya tenía antes de este fix).
                $claimado = false;
                if ((int)$stmtPend->fetchColumn() === 0) {
                    $stClaim = $db->prepare('UPDATE ordenes SET wa_lista_enviado = 1 WHERE id = ? AND wa_lista_enviado = 0');
                    $stClaim->execute([$ordenId]);
                    $claimado = $stClaim->rowCount() === 1;
                }
                if ($claimado) {
                    $telRaw = preg_replace('/\D/', '', $ordWa['telefono_alterno'] ?: $ordWa['telefono']);
                    if (!$telRaw || strlen($telRaw) < 10) {
                        // Sin teléfono válido — liberar el claim para reintentar si se
                        // corrige el teléfono del cliente antes del siguiente cambio.
                        $db->prepare('UPDATE ordenes SET wa_lista_enviado = 0 WHERE id = ?')->execute([$ordenId]);
                    }
                    if ($telRaw && strlen($telRaw) >= 10) {
                        if (strlen($telRaw) === 10) $telRaw = '52' . $telRaw;
                        $proyectoWa = trim($ordWa['proyecto'] ?: $ordWa['cliente_nombre']);
                        $resWa = enviarMensajeWA([
                            'messaging_product' => 'whatsapp',
                            'to'   => $telRaw,
                            'type' => 'template',
                            'template' => [
                                'name'     => 'orden_lista',
                                'language' => ['code' => 'es_MX'],
                                'components' => [[
                                    'type' => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => substr(strip_tags($ordWa['cliente_nombre']), 0, 60)],
                                        ['type' => 'text', 'text' => substr(strip_tags($ordWa['folio']), 0, 20)],
                                        ['type' => 'text', 'text' => substr(strip_tags($proyectoWa), 0, 100)],
                                        ['type' => 'text', 'text' => (string)$ordWa['total_piezas']],
                                    ]
                                ]]
                            ]
                        ]);

                        $waId = $resWa['data']['messages'][0]['id'] ?? null;
                        if ($resWa['code'] === 200 && $waId) {
                            // Ya quedó marcado por el claim de arriba — solo falta el inbox.
                            // Guardar en inbox para visibilidad
                            $tel10 = substr($telRaw, -10);
                            $stmtCv = $db->prepare("SELECT id, cliente_id FROM whatsapp_conversaciones WHERE RIGHT(REGEXP_REPLACE(telefono,'[^0-9]',''),10) = ?");
                            $stmtCv->execute([$tel10]);
                            $conv = $stmtCv->fetch(PDO::FETCH_ASSOC);
                            if (!$conv) {
                                $db->prepare("INSERT INTO whatsapp_conversaciones (cliente_id, telefono, ultima_actividad) VALUES (?,?,NOW())")
                                   ->execute([$ordWa['cliente_id'] ?? null, $telRaw]);
                                $convId = $db->lastInsertId();
                            } else {
                                $convId = $conv['id'];
                            }
                            $contenidoLog = '[Plantilla orden_lista] Orden ' . $ordWa['folio'] . ' lista — ' . $ordWa['total_piezas'] . ' piezas — ' . $proyectoWa;
                            $db->prepare("INSERT INTO whatsapp_mensajes (conversacion_id, direccion, contenido, tipo, wa_message_id, enviado_por) VALUES (?, 'outbound', ?, 'texto', ?, 'sistema')")
                               ->execute([$convId, $contenidoLog, $waId]);
                            $db->prepare("UPDATE whatsapp_conversaciones SET ultima_actividad=NOW() WHERE id=?")
                               ->execute([$convId]);
                        } else {
                            // Meta rechazó el envío — liberar el claim para reintentar
                            // en el próximo cambio de estatus de esta orden.
                            $db->prepare('UPDATE ordenes SET wa_lista_enviado = 0 WHERE id = ?')->execute([$ordenId]);
                            error_log('APEX WA orden_lista fallo: orden=' . $ordWa['folio'] . ' tel=' . $telRaw . ' resp=' . json_encode($resWa['data']));
                        }
                    }
                }
            }
        } catch (Exception $ignored) {}
    }
}
