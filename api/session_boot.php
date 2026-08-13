<?php
// ============================================================
//  APEX GLASS - Arranque de sesión centralizado (S2-10)
//  Archivo: api/session_boot.php
//  Requerir este archivo en vez de llamar session_start() directo,
//  para que TODA sesión (interna, portal) nazca con cookie
//  HttpOnly/Secure/SameSite=Lax — mismo patrón que ya usan
//  api/login.php, api/portal_clientes.php y requireSessionApi().
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}
// S2-11: token CSRF por sesión (synchronizer token) — se emite una sola vez.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
