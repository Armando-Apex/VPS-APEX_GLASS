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
