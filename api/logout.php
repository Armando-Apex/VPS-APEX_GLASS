<?php
// ============================================================
//  APEX GLASS - Logout
//  Archivo: api/logout.php
//  Soporta: llamada AJAX (devuelve JSON) o redirect directo
// ============================================================
require_once "config.php";

require_once __DIR__ . '/session_boot.php'; // S2-10
$_SESSION = [];
session_destroy();

// Si viene con redirect o directamente desde browser, redirigir al login
$redirect = trim($_GET["redirect"] ?? "");
$esAjax   = isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
             (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($redirect || !$esAjax) {
    header("Location: ../app/login.php");
    exit;
}

header("Content-Type: application/json; charset=utf-8");
echo json_encode(["ok" => true]);