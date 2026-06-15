<?php
// ============================================================
//  APEX GLASS - Login HTML
//  Archivo: app/login.php
// ============================================================
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';

// Si ya tiene sesion, redirigir segun rol
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    $redireccion = REDIRECCION_LOGIN[$_SESSION['user_rol']] ?? 'operador.php';
    header('Location: ' . $redireccion);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<title>APEX GLASS - Acceso</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: -apple-system, 'Helvetica Neue', sans-serif;
  background: #0d0d0f; min-height: 100dvh;
  display: flex; align-items: center; justify-content: center; padding: 20px;
}
.card {
  background: #18181c; border: 1px solid #2a2a32;
  border-radius: 16px; padding: 40px 32px;
  width: 100%; max-width: 360px;
}
.logo { text-align: center; margin-bottom: 32px; }
.logo svg { width: 52px; height: 52px; margin-bottom: 8px; }
.logo h1 { font-size: 22px; color: #f5a623; font-weight: 800; letter-spacing: 3px; }
.logo p  { color: #6b6b7a; font-size: 12px; margin-top: 4px; letter-spacing: 1px; text-transform: uppercase; }
.field { margin-bottom: 14px; }
.field label { display: block; font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #6b6b7a; margin-bottom: 7px; }
.field input {
  width: 100%; background: #0d0d0f; border: 1.5px solid #2a2a32;
  border-radius: 10px; padding: 15px 16px; font-size: 16px; color: #f0f0f0;
  outline: none; -webkit-appearance: none; transition: border-color .2s;
}
.field input:focus { border-color: #f5a623; }
.btn {
  width: 100%; padding: 16px; background: #f5a623; color: #000;
  border: none; border-radius: 10px; font-size: 15px; font-weight: 800;
  cursor: pointer; margin-top: 6px; letter-spacing: .5px;
  transition: opacity .15s;
}
.btn:active { opacity: .8; }
.error {
  background: #2d1515; border: 1px solid #5a1e1e; border-radius: 8px;
  padding: 11px 14px; font-size: 13px; color: #ff8080;
  margin-bottom: 14px; text-align: center; display: none;
}
.error.show { display: block; }
.spin {
  display: inline-block; width: 18px; height: 18px;
  border: 2px solid rgba(0,0,0,.2); border-top-color: #000;
  border-radius: 50%; animation: sp .6s linear infinite; vertical-align: middle;
}
@keyframes sp { to { transform: rotate(360deg); } }
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <svg viewBox="0 0 52 52" fill="none">
      <polygon points="26,3 49,16 49,36 26,49 3,36 3,16" fill="none" stroke="#f5a623" stroke-width="2"/>
      <polygon points="26,11 42,20 42,32 26,41 10,32 10,20" fill="none" stroke="#f5a623" stroke-width="1" opacity=".35"/>
      <circle cx="26" cy="26" r="5" fill="#f5a623"/>
    </svg>
    <h1>APEX GLASS</h1>
    <p>Sistema de Produccion</p>
  </div>
  <div class="error" id="errorMsg"></div>
  <div class="field">
    <label>Usuario</label>
    <input type="text" id="usuario" autocomplete="username" autocapitalize="none" autocorrect="off">
  </div>
  <div class="field">
    <label>Contrasena</label>
    <input type="password" id="password" autocomplete="current-password">
  </div>
  <button class="btn" id="btnLogin" onclick="doLogin()">Entrar</button>
</div>
<script>
document.getElementById('usuario').addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('password').focus();
});
document.getElementById('password').addEventListener('keydown', e => {
  if (e.key === 'Enter') doLogin();
});

async function doLogin() {
  const user = document.getElementById('usuario').value.trim();
  const pass = document.getElementById('password').value;
  const err  = document.getElementById('errorMsg');
  const btn  = document.getElementById('btnLogin');

  if (!user || !pass) { showErr('Completa usuario y contrasena'); return; }

  err.classList.remove('show');
  btn.innerHTML = '<span class="spin"></span>';
  btn.disabled  = true;

  try {
    const r = await fetch('../api/login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ usuario: user, password: pass })
    });
    const d = await r.json();
    if (d.ok) {
      window.location.href = d.redireccion;
    } else {
      showErr(d.error || 'Error al iniciar sesion');
      btn.textContent = 'Entrar';
      btn.disabled = false;
    }
  } catch(e) {
    showErr('Error de conexion');
    btn.textContent = 'Entrar';
    btn.disabled = false;
  }
}

function showErr(msg) {
  const el = document.getElementById('errorMsg');
  el.textContent = msg;
  el.classList.add('show');
}
</script>
</body>
</html>