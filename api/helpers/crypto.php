<?php
// ============================================================
//  APEX GLASS - Helper: cifrado reversible (AES-256-GCM)
//  Archivo: api/helpers/crypto.php
//  S1-07: contraseñas de portal cifradas en vez de texto plano —
//  el ERP puede seguir mostrándolas/copiándolas (Ver/Copiar/Reenviar),
//  pero un dump de BD sin la llave PORTAL_PASS_KEY (.env) no sirve de nada.
//  El login de clientes sigue usando SOLO portal_password_hash (bcrypt),
//  este helper no participa en el login.
// ============================================================

function apexEncrypt(string $texto): string {
    $key = base64_decode(env('PORTAL_PASS_KEY', ''));
    if (strlen($key) !== 32) {
        throw new Exception('PORTAL_PASS_KEY no configurada o inválida en .env');
    }
    $iv  = random_bytes(12); // GCM: 12 bytes es el estándar
    $tag = '';
    $cipher = openssl_encrypt($texto, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new Exception('Fallo al cifrar');
    }
    // iv(12) + tag(16) + ciphertext, todo junto y en base64 para guardar en varchar
    return base64_encode($iv . $tag . $cipher);
}

function apexDecrypt(?string $cifrado): ?string {
    if ($cifrado === null || $cifrado === '') return null;
    $key = base64_decode(env('PORTAL_PASS_KEY', ''));
    if (strlen($key) !== 32) {
        throw new Exception('PORTAL_PASS_KEY no configurada o inválida en .env');
    }
    $raw = base64_decode($cifrado, true);
    if ($raw === false || strlen($raw) < 29) return null; // dato corrupto o formato viejo
    $iv     = substr($raw, 0, 12);
    $tag    = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plano  = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plano === false ? null : $plano;
}
