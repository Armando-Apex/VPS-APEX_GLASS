<?php
// ============================================================
//  APEX GLASS - Portal Clientes - Login
//  Ruta en servidor: /produccion/portal/index.php
// ============================================================
require_once __DIR__ . '/../api/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['portal_cliente_id'])) {
    header('Location: dashboard.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>APEX GLASS &mdash; Portal Clientes</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --amber:        #F5A623;
  --bg:           #F0F1F3;
  --surface:      #FFFFFF;
  --border:       #E2E5EB;
  --border-focus: rgba(245,166,35,.8);
  --text-1:       #0F1117;
  --text-2:       #7A7E8E;
  --text-3:       #C4C8D2;
  --red:          #D93025;
  --red-bg:       rgba(217,48,37,.06);
}

body {
  font-family: 'Outfit', -apple-system, sans-serif;
  background: var(--bg);
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}

/* ── Card ── */
.card {
  position: relative;
  z-index: 1;
  width: 100%;
  max-width: 368px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 52px 44px 44px;
  box-shadow: 0 2px 8px rgba(0,0,0,.06), 0 12px 32px rgba(0,0,0,.05);
  animation: cardIn .6s cubic-bezier(.22,1,.36,1) both;
}

@keyframes cardIn {
  from { opacity: 0; transform: translateY(20px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Logo ── */
.logo {
  display: flex;
  flex-direction: column;
  align-items: center;
  margin-bottom: 40px;
}

.logo-name {
  font-family: 'Syncopate', sans-serif;
  font-size: 16px;
  font-weight: 700;
  color: var(--text-1);
  letter-spacing: 5px;
  line-height: 1;
  margin-bottom: 9px;
}

.logo-sub {
  font-size: 10px;
  font-weight: 400;
  color: var(--text-2);
  letter-spacing: 2.5px;
  text-transform: uppercase;
  margin-bottom: 20px;
}

.portal-tag {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 9px;
  font-weight: 600;
  color: var(--amber);
  letter-spacing: 2.5px;
  text-transform: uppercase;
}

.portal-tag::before,
.portal-tag::after {
  content: '';
  display: block;
  width: 22px;
  height: 1px;
  background: rgba(245,166,35,.28);
}

/* ── Divider ── */
.sep {
  width: 100%;
  height: 1px;
  background: var(--border);
  margin: 0 0 34px;
}

/* ── Form fields ── */
.field {
  position: relative;
  margin-bottom: 30px;
}

.field label {
  display: block;
  font-size: 9.5px;
  font-weight: 600;
  color: var(--text-2);
  letter-spacing: 2.2px;
  text-transform: uppercase;
  margin-bottom: 10px;
}

.field input {
  width: 100%;
  background: transparent;
  border: none;
  border-bottom: 1px solid var(--text-3);
  border-radius: 0;
  padding: 8px 0 10px;
  font-family: 'Outfit', sans-serif;
  font-size: 16px;
  font-weight: 300;
  color: var(--text-1);
  outline: none;
  -webkit-appearance: none;
  caret-color: var(--amber);
  transition: border-color .2s;
}

.field input::placeholder { color: var(--text-3); }
.field input:focus { border-bottom-color: transparent; }

/* Animated amber underline */
.field-line {
  position: absolute;
  bottom: 0; left: 0;
  width: 100%; height: 1px;
  background: var(--border-focus);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform .25s cubic-bezier(.4,0,.2,1);
}

.field input:focus ~ .field-line { transform: scaleX(1); }

/* ── Error ── */
.error {
  background: var(--red-bg);
  border-left: 2px solid var(--red);
  border-radius: 2px;
  padding: 10px 14px;
  font-size: 12px;
  font-weight: 400;
  color: var(--red);
  margin-bottom: 24px;
  letter-spacing: .3px;
  display: none;
  animation: errIn .18s ease;
}

.error.show { display: block; }

@keyframes errIn {
  from { opacity: 0; transform: translateX(-3px); }
  to   { opacity: 1; transform: translateX(0); }
}

/* ── Buttons ── */
.btn-main {
  width: 100%;
  padding: 15px 20px;
  background: var(--amber);
  color: #000;
  border: none;
  border-radius: 2px;
  font-family: 'Outfit', sans-serif;
  font-size: 11.5px;
  font-weight: 700;
  letter-spacing: 2.8px;
  text-transform: uppercase;
  cursor: pointer;
  margin-top: 6px;
  position: relative;
  overflow: hidden;
  transition: opacity .15s, transform .12s;
}

.btn-main::after {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(255,255,255,.14);
  opacity: 0;
  transition: opacity .15s;
}

.btn-main:hover::after  { opacity: 1; }
.btn-main:active        { transform: scale(.99); }
.btn-main:disabled      { opacity: .42; cursor: not-allowed; }

.btn-ghost {
  width: 100%;
  margin-top: 11px;
  padding: 13px 20px;
  background: transparent;
  color: var(--text-1);
  border: 1.5px solid var(--border);
  border-radius: 2px;
  font-family: 'Outfit', sans-serif;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 2.2px;
  text-transform: uppercase;
  cursor: pointer;
  transition: border-color .2s, background .2s;
}

.btn-ghost:hover {
  border-color: #B0B5C0;
  background: #F7F8FA;
}

/* ── Spinner ── */
.spin {
  display: inline-block;
  width: 14px; height: 14px;
  border: 1.5px solid rgba(0,0,0,.18);
  border-top-color: #000;
  border-radius: 50%;
  animation: sp .6s linear infinite;
  vertical-align: middle;
}

@keyframes sp { to { transform: rotate(360deg); } }

/* ── Footer note ── */
.footer-note {
  text-align: center;
  margin-top: 26px;
  font-size: 9.5px;
  font-weight: 400;
  color: var(--text-3);
  letter-spacing: 1.8px;
  text-transform: uppercase;
}

/* ── Modal Ofertas ── */
.modal-bg {
  display: none;
  position: fixed; inset: 0;
  background: rgba(0,0,0,.84);
  z-index: 999;
  align-items: center;
  justify-content: center;
  padding: 24px;
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
}

.modal-bg.open { display: flex; }

.modal-box {
  position: relative;
  max-width: min(90vw, 600px);
  max-height: 90vh;
  border-radius: 4px;
  overflow: hidden;
  border: 1px solid var(--border);
  animation: modalIn .22s cubic-bezier(.22,1,.36,1);
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(.95); }
  to   { opacity: 1; transform: scale(1); }
}

.modal-img {
  display: block;
  width: 100%;
  max-height: 85vh;
  object-fit: contain;
}

.modal-close {
  position: absolute; top: 12px; right: 12px;
  background: rgba(0,0,0,.72);
  border: 1px solid rgba(255,255,255,.15);
  color: #fff;
  border-radius: 2px;
  width: 32px; height: 32px;
  font-size: 13px;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  z-index: 10;
  transition: background .15s;
}

.modal-close:hover { background: rgba(0,0,0,.94); }

@media (max-width: 420px) {
  .card { padding: 40px 28px 36px; }
}
</style>
</head>
<body>

<div class="card">

  <div class="logo">
    <div class="logo-name">APEX GLASS</div>
    <div class="logo-sub">Templadora Noreste</div>
    <div class="portal-tag">Portal Clientes</div>
  </div>

  <div class="sep"></div>

  <div class="error" id="errorMsg"></div>

  <div class="field">
    <label>Usuario</label>
    <input type="text" id="usuario" placeholder="CTN-000"
           autocomplete="username" autocapitalize="characters"
           autocorrect="off" spellcheck="false">
    <span class="field-line"></span>
  </div>

  <div class="field">
    <label>Contrase&ntilde;a</label>
    <input type="password" id="password" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;"
           autocomplete="current-password">
    <span class="field-line"></span>
  </div>

  <button class="btn-main" id="btnLogin" onclick="doLogin()">Entrar</button>
  <button class="btn-ghost" onclick="abrirOfertas()">Conoce nuestras ofertas</button>

  <div class="footer-note">Acceso exclusivo para clientes</div>
</div>

<!-- Modal Ofertas -->
<div id="modalOfertas" class="modal-bg" onclick="cerrarOfertas()">
  <div class="modal-box" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="cerrarOfertas()">&#10005;</button>
    <img src="img/oferta.jpeg" alt="Ofertas APEX GLASS" class="modal-img">
  </div>
</div>

<script>
document.getElementById('usuario').addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('password').focus();
});
document.getElementById('password').addEventListener('keydown', e => {
  if (e.key === 'Enter') doLogin();
});
document.getElementById('usuario').addEventListener('input', function() {
  const pos = this.selectionStart;
  this.value = this.value.toUpperCase();
  this.setSelectionRange(pos, pos);
});

async function doLogin() {
  const user = document.getElementById('usuario').value.trim();
  const pass = document.getElementById('password').value;
  const err  = document.getElementById('errorMsg');
  const btn  = document.getElementById('btnLogin');

  if (!user || !pass) { showErr('Completa usuario y contraseña'); return; }

  err.classList.remove('show');
  btn.innerHTML = '<span class="spin"></span>';
  btn.disabled  = true;

  try {
    const fd = new FormData();
    fd.append('usuario',  user);
    fd.append('password', pass);
    const r = await fetch('../api/portal_clientes.php?accion=login', { method: 'POST', body: fd });
    const d = await r.json();
    if (d.ok) {
      window.location.href = 'dashboard.php';
    } else {
      showErr(d.error || 'Usuario o contraseña incorrectos');
      btn.textContent = 'Entrar';
      btn.disabled    = false;
    }
  } catch(e) {
    showErr('Error de conexión, intenta de nuevo');
    btn.textContent = 'Entrar';
    btn.disabled    = false;
  }
}

function showErr(msg) {
  const el = document.getElementById('errorMsg');
  el.textContent = msg;
  el.classList.add('show');
}

function abrirOfertas()  { document.getElementById('modalOfertas').classList.add('open'); }
function cerrarOfertas() { document.getElementById('modalOfertas').classList.remove('open'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarOfertas(); });
</script>
</body>
</html>
