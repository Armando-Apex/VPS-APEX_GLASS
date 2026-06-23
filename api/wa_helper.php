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
        $resp = curl_exec($ch);
        $code = curl_errno($ch) ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($resp, true);
        return ['code' => $code, 'data' => $data];
    }
}
