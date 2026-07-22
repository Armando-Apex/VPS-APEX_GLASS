<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';

// Roles que tienen acceso a operador.php
$rolesOperador = ['operador','chofer','jefe_piso','director','dir_admin','desarrollo'];

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /produccion/app/login.php');
    exit;
}

$rol = $_SESSION['user_rol'] ?? '';

if (!in_array($rol, $rolesOperador)) {
    $redir = REDIRECCION_LOGIN[$rol] ?? 'dashboard.php';
    header('Location: /produccion/app/' . $redir);
    exit;
}

// Leer datos del usuario antes de continuar
$user = [
    'id'       => $_SESSION['user_id']       ?? 0,
    'nombre'   => $_SESSION['user_name']     ?? '',
    'rol'      => $_SESSION['user_rol']      ?? '',
    'estacion' => $_SESSION['user_estacion'] ?? $_SESSION['user_rol'] ?? '',
];
// Nota: NO llamamos session_write_close() aqui
// para que la sesion siga disponible en las llamadas al API
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>APEX GLASS — Operador</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:      #0d0d0f;
  --surface: #18181c;
  --border:  #2a2a32;
  --text:    #f0f0f0;
  --muted:   #6b6b7a;
  --accent:  #f5a623;
  --green:   #22d47a;
  --red:     #ff4757;
  --blue:    #4a9eff;
}
body {
  background: var(--bg); color: var(--text);
  font-family: -apple-system, 'Helvetica Neue', sans-serif;
  min-height: 100dvh; overflow-x: hidden;
}

/* ── LOGIN ─────────────────────────────────────────────── */
#screen-login {
  min-height: 100dvh; display: flex;
  flex-direction: column; align-items: center;
  justify-content: center; padding: 32px 24px;
}
.login-hex { width: 52px; height: 52px; margin-bottom: 6px; }
.login-brand {
  font-size: 11px; font-weight: 700; letter-spacing: 5px;
  color: var(--muted); text-transform: uppercase; margin-bottom: 44px;
}
.login-card { width: 100%; max-width: 340px; }
.login-card h2 { font-size: 28px; font-weight: 800; margin-bottom: 28px; line-height: 1.1; }
.field { margin-bottom: 14px; }
.field label {
  display: block; font-size: 10px; font-weight: 700;
  letter-spacing: 2px; text-transform: uppercase;
  color: var(--muted); margin-bottom: 7px;
}
.field input {
  width: 100%; background: var(--surface);
  border: 1.5px solid var(--border); border-radius: 10px;
  padding: 15px 16px; font-size: 17px; color: var(--text);
  outline: none; -webkit-appearance: none; transition: border-color .2s;
}
.field input:focus { border-color: var(--accent); }
.btn-primary {
  width: 100%; padding: 16px; background: var(--accent); color: #000;
  border: none; border-radius: 10px; font-size: 15px; font-weight: 800;
  cursor: pointer; margin-top: 6px; -webkit-tap-highlight-color: transparent;
}
.btn-primary:active { opacity: .8; }
.login-error {
  background: #2d1515; border: 1px solid #5a1e1e; border-radius: 8px;
  padding: 11px 14px; font-size: 13px; color: #ff8080;
  margin-bottom: 14px; text-align: center; display: none;
}
.login-error.show { display: block; }

/* ── SCANNER SCREEN ─────────────────────────────────────── */
#screen-scanner { display: none; flex-direction: column; min-height: 100dvh; }

.op-header {
  background: var(--surface); border-bottom: 1px solid var(--border);
  padding: 12px 16px; display: flex;
  align-items: center; justify-content: space-between; flex-shrink: 0;
}
.station-info { display: flex; align-items: center; gap: 10px; }
.station-dot  { width: 9px; height: 9px; border-radius: 50%; }
.station-name { font-size: 15px; font-weight: 700; }
.op-user-label{ font-size: 11px; color: var(--muted); margin-top: 1px; }
.btn-logout {
  background: none; border: 1px solid var(--border);
  color: var(--muted); font-size: 12px; padding: 6px 12px; border-radius: 6px; cursor: pointer;
}

/* Botón activar cámara */
.btn-activar-cam {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  width: 100%; height: 260px; background: #111; border: none; cursor: pointer;
  gap: 12px; flex-shrink: 0;
}
.btn-activar-cam .cam-icon { font-size: 52px; }
.btn-activar-cam .cam-label {
  font-size: 18px; font-weight: 800; color: var(--accent); letter-spacing: 1px;
}
.btn-activar-cam .cam-sub { font-size: 12px; color: var(--muted); }

/* Cámara */
.camera-wrap {
  position: relative; width: 100%; height: 260px;
  background: #000; overflow: hidden; flex-shrink: 0;
}
.camera-wrap video { width: 100%; height: 100%; object-fit: cover; display: block; }

.scan-overlay {
  position: absolute; inset: 0;
  display: flex; align-items: center; justify-content: center;
  pointer-events: none;
}
.scan-frame { width: 200px; height: 200px; position: relative; }
.corner {
  position: absolute; width: 36px; height: 36px;
  border-color: var(--accent); border-style: solid;
}
.corner.tl { top:0; left:0;     border-width: 3px 0 0 3px; border-radius: 3px 0 0 0; }
.corner.tr { top:0; right:0;    border-width: 3px 3px 0 0; border-radius: 0 3px 0 0; }
.corner.bl { bottom:0; left:0;  border-width: 0 0 3px 3px; border-radius: 0 0 0 3px; }
.corner.br { bottom:0; right:0; border-width: 0 3px 3px 0; border-radius: 0 0 3px 0; }
.scan-line {
  position: absolute; left: 3px; right: 3px; height: 2px;
  background: linear-gradient(90deg, transparent, var(--accent), transparent);
  border-radius: 1px; animation: scanAnim 2s ease-in-out infinite;
}
@keyframes scanAnim { 0%,100% { top: 8px; } 50% { top: 188px; } }

.scan-status {
  position: absolute; top: 10px; left: 50%; transform: translateX(-50%);
  font-size: 11px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase;
  padding: 3px 10px; border-radius: 20px; background: rgba(0,0,0,.75); white-space: nowrap;
}
.scan-status.active   { color: var(--green); border: 1px solid rgba(34,212,122,.3); }
.scan-status.inactive { color: var(--muted); border: 1px solid var(--border); }
.scan-status.warn     { color: var(--accent); border: 1px solid rgba(245,166,35,.3); }

.scan-hint {
  position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%);
  font-size: 11px; color: rgba(255,255,255,.45); white-space: nowrap;
}
.cam-flash {
  position: absolute; inset: 0; background: white;
  opacity: 0; pointer-events: none; transition: opacity .08s;
}
.cam-flash.show { opacity: .45; }

/* Panel resultado */
.result-panel { flex: 1; overflow-y: auto; padding: 14px 14px 30px; }
.empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
.empty-icon  { font-size: 44px; margin-bottom: 10px; opacity: .35; }
.empty-text  { font-size: 13px; line-height: 1.7; }

/* Tarjeta pieza */
.pieza-card {
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 14px; overflow: hidden; display: none; margin-bottom: 12px;
}
.pieza-card.show { display: block; }
.card-head {
  padding: 14px 16px; border-bottom: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: flex-start;
}
.card-folio   { font-size: 19px; font-weight: 800; }
.card-cliente { font-size: 12px; color: var(--muted); margin-top: 2px; }
.estatus-pill {
  font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 20px;
  text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; flex-shrink: 0;
}
.card-grid {
  padding: 12px 16px; display: grid; grid-template-columns: 1fr 1fr;
  gap: 12px; border-bottom: 1px solid var(--border);
}
.card-grid .full { grid-column: 1 / -1; }
.info-label { font-size: 10px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; color: var(--muted); margin-bottom: 3px; }
.info-val   { font-size: 16px; font-weight: 700; }
.tags-row   { padding: 10px 16px; border-bottom: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 6px; min-height: 38px; }
.tag { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 6px; background: rgba(255,255,255,.06); border: 1px solid var(--border); }
.tag.hi { background: rgba(245,166,35,.1); color: var(--accent); border-color: rgba(245,166,35,.25); }

.action-wrap { padding: 12px 14px; }
.btn-action {
  width: 100%; padding: 17px; border: none; border-radius: 11px;
  font-size: 17px; font-weight: 800; cursor: pointer;
  transition: opacity .15s, transform .1s; -webkit-tap-highlight-color: transparent;
}
.btn-action:active { opacity: .85; transform: scale(.98); }
.btn-action.go   { background: var(--green); color: #000; }
.btn-action.done { background: var(--border); color: var(--muted); pointer-events: none; }

.sec-row { padding: 0 14px 14px; display: flex; gap: 8px; }
.btn-sec {
  flex: 1; padding: 12px; background: none;
  border: 1.5px solid var(--border); border-radius: 10px;
  font-size: 13px; font-weight: 700; color: var(--muted); cursor: pointer;
}
.btn-sec.danger { border-color: rgba(255,71,87,.35); color: var(--red); }

.manual-wrap { display: flex; gap: 8px; }
.manual-input {
  flex: 1; background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 8px; padding: 11px 12px; font-size: 14px; color: var(--text);
  outline: none; -webkit-appearance: none;
}
.manual-input:focus { border-color: var(--accent); }
.btn-manual {
  padding: 11px 16px; background: var(--accent); color: #000;
  border: none; border-radius: 8px; font-size: 13px; font-weight: 800; cursor: pointer;
}

/* Nota de compatibilidad */
.compat-note {
  background: rgba(245,166,35,.08); border: 1px solid rgba(245,166,35,.25);
  border-radius: 10px; padding: 12px 14px; margin-bottom: 12px;
  font-size: 12px; color: var(--accent); line-height: 1.5; display: none;
}
.compat-note.show { display: block; }

/* Big feedback */
.big-fb {
  position: fixed; inset: 0; display: flex; align-items: center; justify-content: center;
  z-index: 150; pointer-events: none; opacity: 0; transition: opacity .25s;
}
.big-fb.show { opacity: 1; pointer-events: auto; }
.big-fb-inner { text-align: center; padding: 36px 44px; border-radius: 20px; backdrop-filter: blur(24px); }
.big-fb.ok  .big-fb-inner { background: rgba(34,212,122,.14); border: 2px solid rgba(34,212,122,.35); }
.big-fb.err .big-fb-inner { background: rgba(255,71,87,.14);  border: 2px solid rgba(255,71,87,.35); }
.fb-icon  { font-size: 68px; line-height: 1; margin-bottom: 10px; }
.fb-label { font-size: 18px; font-weight: 800; }
.fb-sub   { font-size: 12px; color: var(--muted); margin-top: 5px; }

.toast {
  position: fixed; bottom: 28px; left: 50%;
  transform: translateX(-50%) translateY(70px);
  padding: 13px 22px; border-radius: 30px; font-size: 14px; font-weight: 700;
  z-index: 160; pointer-events: none; white-space: nowrap;
  transition: transform .3s cubic-bezier(.34,1.56,.64,1);
}
.toast.show { transform: translateX(-50%) translateY(0); }
.toast.success { background: var(--green); color: #000; }
.toast.error   { background: var(--red);   color: #fff; }
.toast.info    { background: var(--blue);  color: #fff; }

/* ── Modal Retrabajo ─────────────────────────────────────── */
.modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.75); z-index: 100;
  align-items: flex-end; justify-content: center;
}
.modal-bg.open { display: flex; }
.modal-sheet {
  background: var(--surface); border-radius: 20px 20px 0 0;
  padding: 20px 16px 40px; width: 100%; max-width: 480px;
  border-top: 1px solid var(--border);
  animation: slideUp .25s ease;
}
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.modal-handle {
  width: 36px; height: 4px; background: var(--border);
  border-radius: 2px; margin: 0 auto 16px;
}
.modal-title {
  font-size: 17px; font-weight: 800; margin-bottom: 4px; text-align: center;
}
.modal-sub {
  font-size: 12px; color: var(--muted); text-align: center; margin-bottom: 20px;
}
.razones-grid {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 10px; margin-bottom: 16px;
}
.btn-razon {
  padding: 16px 12px; background: var(--bg);
  border: 1.5px solid var(--border); border-radius: 12px;
  font-size: 14px; font-weight: 700; color: var(--text);
  cursor: pointer; text-align: center; line-height: 1.3;
  transition: all .15s; -webkit-tap-highlight-color: transparent;
}
.btn-razon:active  { opacity: .8; transform: scale(.97); }
.btn-razon.selected {
  border-color: var(--red); background: rgba(255,71,87,.1); color: var(--red);
}
.modal-notas {
  width: 100%; background: var(--bg); border: 1.5px solid var(--border);
  border-radius: 10px; padding: 12px; font-size: 14px; color: var(--text);
  outline: none; resize: none; font-family: inherit; margin-bottom: 14px;
}
.modal-notas:focus { border-color: var(--accent); }
.btn-confirmar {
  width: 100%; padding: 17px; background: var(--red); color: #fff;
  border: none; border-radius: 12px; font-size: 16px; font-weight: 800;
  cursor: pointer; -webkit-tap-highlight-color: transparent;
}
.btn-confirmar:disabled { opacity: .4; pointer-events: none; }
.btn-cancelar {
  width: 100%; padding: 12px; background: none; color: var(--muted);
  border: none; font-size: 14px; cursor: pointer; margin-top: 8px;
}
</style>
</head>
<body>

<!-- ══ LOGIN ═══════════════════════════════════════════════ -->
<!-- screen-login no se usa, la sesion es validada por PHP -->
<div id="screen-login" style="display:none"></div>

<!-- ══ SCANNER ═══════════════════════════════════════════════ -->
<div id="screen-scanner">
  <div class="op-header">
    <div class="station-info">
      <div class="station-dot" id="stationDot"></div>
      <div>
        <div class="station-name" id="stationName">—</div>
        <div class="op-user-label" id="opUser">—</div>
      </div>
    </div>
    <button class="btn-logout" onclick="doLogout()">Salir</button>
  </div>

  <button class="btn-activar-cam" id="btnActivarCam" onclick="activarCamara()">
    <span class="cam-icon">📷</span>
    <span class="cam-label">TOCA PARA ACTIVAR CÁMARA</span>
    <span class="cam-sub">Se pedirá permiso si es necesario</span>
  </button>

  <div class="camera-wrap" id="cameraWrap" style="display:none">
    <video id="videoEl" playsinline muted></video>
    <canvas id="canvasEl" style="display:none"></canvas>
    <div class="scan-overlay">
      <div class="scan-frame">
        <div class="scan-line"></div>
        <div class="corner tl"></div><div class="corner tr"></div>
        <div class="corner bl"></div><div class="corner br"></div>
      </div>
    </div>
    <div class="scan-status inactive" id="scanStatus">Iniciando…</div>
    <div class="scan-hint">Apunta el QR al recuadro</div>
    <div class="cam-flash" id="camFlash"></div>
  </div>

  <div class="result-panel">
    <div class="compat-note" id="compatNote"></div>

    <div class="empty-state" id="emptyState">
      <div class="empty-icon">📷</div>
      <div class="empty-text">Escanea el QR de una pieza<br>para registrar el avance</div>
    </div>

    <div class="pieza-card" id="piezaCard">
      <div class="card-head">
        <div>
          <div class="card-folio"   id="pcFolio">—</div>
          <div class="card-cliente" id="pcCliente">—</div>
        </div>
        <span class="estatus-pill" id="pcPill">—</span>
      </div>
      <div class="card-grid">
        <div>
          <div class="info-label">Partida / Pieza</div>
          <div class="info-val" id="pcPartida">—</div>
        </div>
        <div>
          <div class="info-label">Medidas mm</div>
          <div class="info-val" id="pcMedidas">—</div>
        </div>
        <div class="full">
          <div class="info-label">Cristal</div>
          <div class="info-val" id="pcCristal">—</div>
        </div>
      </div>
      <div class="tags-row" id="pcTags"></div>
      <div class="action-wrap">
        <button class="btn-action done" id="btnAction">—</button>
      </div>
      <div class="sec-row">
        <button class="btn-sec" onclick="verHistorial()">📋 Historial</button>
        <button class="btn-sec danger" onclick="reportarError()">⚠️ Reportar</button>
      </div>
    </div>

    <div class="pieza-card" id="ordenMasivaCard">
      <div class="card-head">
        <div>
          <div class="card-folio" id="omFolio">—</div>
          <div class="card-cliente" id="omCliente">—</div>
        </div>
      </div>
      <div id="omBody" style="padding:14px 0;font-size:14px;color:#cbd5e1;text-align:center"></div>
      <div class="action-wrap" id="omActions"></div>
    </div>

    <!-- Botón "Cortar": inicia la sesión (elegir lámina) — solo estación 'corte' -->
    <button id="btnIniciarCorte" onclick="abrirWizardCorte()" style="display:none;width:100%;margin-bottom:10px;
      padding:16px;background:#f5a623;color:#000;border:none;border-radius:12px;font-size:15px;font-weight:800;
      cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.3)">✂️ Cortar</button>

    <!-- Franja de sesión de corte activa: escanea con la cámara mientras esto está visible.
         Al terminar de escanear, "Empezar a cortar" abre el menú de revisión (ver/quitar
         piezas + Avanzar a canto) — no hay botón de avance directo aquí. -->
    <div id="franjaSesionCorte" style="display:none;margin-bottom:10px;
      background:#18181c;border:1.5px solid #f5a623;border-radius:12px;padding:12px 14px;box-shadow:0 4px 14px rgba(0,0,0,.3)">
      <div style="font-size:13px;color:#f5a623;font-weight:800;margin-bottom:8px" id="franjaSesionInfo">Sesión activa</div>
      <button onclick="wizAbrirRevision()" style="width:100%;padding:12px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:800;cursor:pointer">✂️ Empezar a cortar</button>
    </div>

    <div class="manual-wrap">
      <input type="text" class="manual-input" id="manualQR"
             placeholder="N&#250;mero de orden (ej: 820)"
             inputmode="numeric" autocomplete="off">
      <button class="btn-manual" onclick="buscarPorNumero()">&#128269;</button>
    </div>
    <div id="busquedaPanel" style="display:none;margin-top:8px;background:#1e1e24;border-radius:12px;overflow:hidden;max-height:55vh;overflow-y:auto"></div>

    <input type="file" id="inputFotoQR" accept="image/*" capture="environment" style="display:none" onchange="procesarFotoQR(this)">
    <button id="btnFotoQR" onclick="document.getElementById('inputFotoQR').click()" style="display:none;width:100%;margin-top:10px;padding:14px;background:#2a2a32;color:#f0f0f0;border:1.5px solid #3a3a44;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;">
      📸 Tomar foto del QR
    </button>
  </div>
</div>

<!-- ── Modal Omisión ────────────────────────────────────── -->
<div class="modal-bg" id="modalOmision">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-title" style="color:#f5a623">&#9888;&#65039; Omisi&oacute;n detectada</div>
    <div class="modal-sub" id="omisionSub">—</div>
    <div style="background:rgba(245,166,35,.08);border:1px solid rgba(245,166,35,.25);border-radius:12px;padding:14px;margin-bottom:18px;font-size:13px;line-height:1.6;color:#f0f0f0">
      Si confirmas, la omisi&oacute;n quedar&aacute; registrada y podr&aacute;s continuar con tu proceso.<br>
      <strong style="color:#f5a623">La supervisi&oacute;n podr&aacute; ver este registro.</strong>
    </div>
    <button class="btn-confirmar" style="background:#f5a623;color:#000" onclick="confirmarOmision()">&#10003; Confirmar y continuar</button>
    <button class="btn-cancelar" onclick="cerrarModalOmision()">Cancelar</button>
  </div>
</div>

<!-- ── Modal Retrabajo ──────────────────────────────────── -->
<div class="modal-bg" id="modalRetrabajo">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div class="modal-title">⚠️ Reportar Retrabajo</div>
    <div class="modal-sub" id="modalRetSub">Selecciona la razón</div>
    <div class="razones-grid" id="razonesGrid"></div>
    <textarea class="modal-notas" id="modalNotas" rows="2" placeholder="Notas adicionales (opcional)"></textarea>
    <button class="btn-confirmar" id="btnConfirmarRet" disabled onclick="confirmarRetrabajo()">Confirmar Retrabajo</button>
    <button class="btn-cancelar" onclick="cerrarModalRetrabajo()">Cancelar</button>
  </div>
</div>

<!-- ── Modal Wizard Sesión de Corte ─────────────────────── -->
<div class="modal-bg" id="modalCorte">
  <div class="modal-sheet" style="max-height:85vh;overflow-y:auto">
    <div class="modal-handle"></div>
    <div class="modal-title">✂️ Sesión de Corte</div>
    <div class="modal-sub" id="corteSub">—</div>
    <div id="corteWizardBody"></div>
    <button class="btn-cancelar" onclick="cerrarWizardCorte()">Cancelar</button>
  </div>
</div>

<div class="big-fb" id="bigFb" onclick="this.classList.remove('show')">
  <div class="big-fb-inner">
    <div class="fb-icon"  id="fbIcon">✅</div>
    <div class="fb-label" id="fbLabel">—</div>
    <div class="fb-sub"   id="fbSub">—</div>
  </div>
</div>
<div class="toast" id="toast"></div>

<script>
// ── Config ────────────────────────────────────────────────
const API = '../api/';

const ESTATUS = {
  pendiente: { label:'⏳ Pendiente',  color:'#4b5563' },
  en_corte:  { label:'⚙️ En CNC',    color:'#c2410c' },
  cortado:   { label:'✂️ Cortado',    color:'#d97706' },
  canteado:  { label:'🔩 Canteado',   color:'#2563eb' },
  trazo:     { label:'✏️ Trazo',      color:'#7c3aed' },
  taladro:   { label:'🔧 Taladro',    color:'#9333ea' },
  en_horno:  { label:'🔥 En Horno',   color:'#dc2626' },
  templado:  { label:'🔥 Templado',   color:'#dc2626' },
  terminado: { label:'📦 Terminado',  color:'#16a34a' },
  entregado: { label:'✅ Entregado',  color:'#15803d' },
};
const ESTACION_REG = {
  corte:     'en_corte',   // Primera acción: registrar en CNC
  canteado:  'canteado',
  trazo:     'trazo',
  taladro:   'taladro',
  templado:  'en_horno',   // Primera acción: entrar al horno
  terminado: 'terminado',
  entrega:   'entregado',
  admin:          null,
  jefe_piso:      null,
  comercial:      null,
  administracion: null,
  dueno:          null,
};
const ESTACION_LABEL = {
  corte:'✂️ Corte', canteado:'🔩 Canteado',
  trazo:'✏️ Trazo', taladro:'🔧 Taladro', templado:'🔥 Templado',
  terminado:'📦 Terminado', entrega:'🚚 Entrega',
  admin:'⬡ Admin', jefe_piso:'⬡ Jefe de Piso',
  director:'⬡ Director', dir_admin:'⬡ Dir. Admin',
  comercial:'💼 Comercial', administracion:'📋 Administración',
  dueno:'👁 Dirección', chofer:'🚚 Chofer — Entregas',
};
const ESTACION_COLOR = {
  corte:'#d97706', canteado:'#0891b2',
  trazo:'#7c3aed', taladro:'#9333ea', templado:'#dc2626',
  terminado:'#16a34a', entrega:'#15803d',
  admin:'#f5a623', jefe_piso:'#f5a623',
  director:'#f5a623', dir_admin:'#f5a623',
  comercial:'#2563eb', administracion:'#2563eb',
  dueno:'#6b7280', chofer:'#15803d',
};

// ── State ─────────────────────────────────────────────────
let pieza      = null;
let scanning   = false;
let detector   = null;
let lastQR     = '';
let lastQRTime = 0;
const DEBOUNCE = 3000;

// ── Sesión de corte (solo estación 'corte') ───────────────────────────────
// El operador elige PRIMERO la lámina/pedacería que va a usar, y LUEGO
// escanea (con la cámara ya activa) las piezas que va sacando de ella.
// Al terminar ("Avanzar a canto") se descuenta 1 lámina real (o se registra
// pedacería) y se marcan todas las piezas escaneadas como 'cortado'.
// Ver api/sesion_corte.php.
let sesionCorteActiva = false;
let sesionCorte = {
  origen: null,      // 'catalogo' | 'pedaceria'
  tipo: null,        // label real (catálogo de Cristales), se guarda en BD
  tipoEnum: null,    // slug ENUM de `laminas`, solo para consultar stock (puede ser null)
  espesor: null,
  laminaId: null,
  anchoMm: null,
  altoMm: null,
  piezas: [],        // piezas escaneadas durante la sesión (aún no en BD)
  removidas: [],     // ids de piezas quitadas a propósito (rotas/error) — no deben volver a entrar por re-escaneo
};

// ── Sesion desde PHP ───────────────────────────────────────
const session = {
  id:       <?= (int)$user['id'] ?? 0 ?>,
  nombre:   "<?= htmlspecialchars($user['nombre'],   ENT_QUOTES) ?>",
  rol:      "<?= htmlspecialchars($user['rol'],      ENT_QUOTES) ?>",
  estacion: "<?= htmlspecialchars($user['estacion'], ENT_QUOTES) ?>",
};
async function doLogout() {
  scanning = false;
  // Detener cámara
  const v = document.getElementById('videoEl');
  if (v && v.srcObject) { v.srcObject.getTracks().forEach(t => t.stop()); v.srcObject = null; }

  // Destruir sesión PHP en el servidor
  try { await fetch(API + 'logout.php'); } catch(_) {}

  // Redirigir al login
  window.location.href = 'login.php';
}

// ── Iniciar escáner ───────────────────────────────────────
function iniciarScanner() {
  document.getElementById('screen-login').style.display   = 'none';
  document.getElementById('screen-scanner').style.display = 'flex';
  var est   = session.estacion || session.rol || 'admin';
  var color = ESTACION_COLOR[est] || '#22d47a';
  document.getElementById('stationName').textContent = ESTACION_LABEL[est] || est;
  document.getElementById('opUser').textContent      = session.nombre;
  document.getElementById('stationDot').style.cssText =
    'background:' + color + ';box-shadow:0 0 7px ' + color;
  document.getElementById('btnActivarCam').style.display = 'none';
  document.getElementById('cameraWrap').style.display    = 'block';
  startCamera();
  actualizarUiSesionCorte();
  restaurarSesionCorteAbierta();
}

function activarCamara() {
  document.getElementById('btnActivarCam').style.display = 'none';
  document.getElementById('cameraWrap').style.display    = 'block';
  startCamera();
}

// ── Cámara ────────────────────────────────────────────────
async function startCamera() {
  const statusEl = document.getElementById('scanStatus');
  statusEl.textContent = 'Solicitando cámara…';
  statusEl.className   = 'scan-status inactive';

  if (!window.isSecureContext) {
    statusEl.textContent = '⚠ Requiere HTTPS';
    statusEl.className   = 'scan-status inactive';
    mostrarCompatNote('La cámara requiere conexión segura. Abre la app con https://apex.glass/produccion/app/operador.php (asegúrate que diga https://, no http://).');
    return;
  }

  if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    statusEl.textContent = '⚠ Sin soporte';
    statusEl.className   = 'scan-status inactive';
    mostrarCompatNote('Tu navegador no soporta acceso a cámara. Usa Chrome actualizado e ingresa a la app con https://');
    return;
  }

  try {
    var stream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } }
      });
    } catch(e1) {
      // Fallback: constraints mínimas
      stream = await navigator.mediaDevices.getUserMedia({ video: true });
    }
    const video = document.getElementById('videoEl');
    video.srcObject = stream;
    video.play().catch(function() {}); // Sin await — autoplay muted no requiere gesto de usuario

    // Intentar BarcodeDetector nativo (Chrome Android, Edge)
    if ('BarcodeDetector' in window) {
      try {
        const supported = await BarcodeDetector.getSupportedFormats();
        if (supported.includes('qr_code')) {
          detector = new BarcodeDetector({ formats: ['qr_code'] });
          statusEl.textContent = '● Escaneando';
          statusEl.className   = 'scan-status active';
          scanning = true;
          scanLoopNative(video);
          return;
        }
      } catch(_) {}
    }

    // Fallback: canvas + jsQR inline (sin CDN)
    // Cargamos jsQR desde el mismo servidor
    const script = document.createElement('script');
    script.src = '../lib/jsqr.min.js';
    script.onload = () => {
      if (typeof jsQR !== 'undefined') {
        statusEl.textContent = '● Escaneando';
        statusEl.className   = 'scan-status active';
        scanning = true;
        scanLoopJsQR(video);
      } else {
        modoManualForzado(statusEl);
      }
    };
    script.onerror = () => modoManualForzado(statusEl);
    document.head.appendChild(script);

  } catch(e) {
    console.error(e.name, e.message);
    statusEl.textContent = '⚠ Sin acceso a cámara';
    statusEl.className   = 'scan-status inactive';
    var msg;
    if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
      msg = '📵 Cámara bloqueada en Chrome.\n\n' +
            'En Chrome toca ⋮ → Configuración\n' +
            '→ Configuración del sitio → Cámara\n' +
            '→ Busca apex.glass → Permitir\n\n' +
            'Luego toca REINTENTAR abajo.';
    } else if (e.name === 'NotFoundError' || e.name === 'DevicesNotFoundError') {
      msg = 'No se encontró cámara en este dispositivo.';
    } else if (e.name === 'NotReadableError') {
      msg = 'La cámara está siendo usada por otra app. Ciérrala e intenta de nuevo.';
    } else {
      msg = 'Error de cámara: ' + (e.name || 'desconocido') + '\n' + (e.message || '') + '\n\nIngresa el número de orden manualmente abajo.';
    }
    mostrarCompatNote(msg);
    mostrarBotonReintentar();
    document.getElementById('btnFotoQR').style.display = 'block';
  }
}

function modoManualForzado(statusEl) {
  statusEl.textContent = '⚠ Modo manual';
  statusEl.className   = 'scan-status warn';
  mostrarCompatNote('El escáner automático no está disponible en este dispositivo. Ingresa el código manualmente.');
}

function mostrarCompatNote(msg) {
  const el = document.getElementById('compatNote');
  el.style.whiteSpace = 'pre-line';
  el.textContent = msg;
  el.classList.add('show');
}

function mostrarBotonReintentar() {
  var existing = document.getElementById('btnReintentar');
  if (existing) return;
  var btn = document.createElement('button');
  btn.id = 'btnReintentar';
  btn.textContent = '🔄 REINTENTAR CÁMARA';
  btn.style.cssText = 'display:block;width:100%;margin-top:10px;padding:16px;' +
    'background:#f5a623;color:#000;border:none;border-radius:10px;' +
    'font-size:16px;font-weight:800;cursor:pointer;';
  btn.onclick = function() {
    btn.remove();
    document.getElementById('compatNote').classList.remove('show');
    startCamera();
  };
  document.getElementById('compatNote').insertAdjacentElement('afterend', btn);
}

// ── Scan con BarcodeDetector nativo ──────────────────────
async function scanLoopNative(video) {
  if (!scanning || !detector) return;
  if (video.readyState >= 2) {
    try {
      const barcodes = await detector.detect(video);
      if (barcodes.length > 0) {
        const qr  = barcodes[0].rawValue.toUpperCase().trim();
        const now = Date.now();
        if (qr !== lastQR || now - lastQRTime > DEBOUNCE) {
          lastQR = qr; lastQRTime = now;
          camFlash();
          loadPieza(qr);
        }
      }
    } catch(_) {}
  }
  requestAnimationFrame(() => scanLoopNative(video));
}

// ── Scan con jsQR desde canvas ────────────────────────────
function scanLoopJsQR(video) {
  if (!scanning) return;
  if (video.readyState >= 2) {
    const canvas = document.getElementById('canvasEl');
    const w = video.videoWidth;
    const h = video.videoHeight;
    if (w > 0 && h > 0) {
      canvas.width = w; canvas.height = h;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, w, h);
      const imageData = ctx.getImageData(0, 0, w, h);
      try {
        const code = jsQR(imageData.data, imageData.width, imageData.height, {
          inversionAttempts: 'dontInvert',
        });
        if (code) {
          const qr  = code.data.toUpperCase().trim();
          const now = Date.now();
          if (qr !== lastQR || now - lastQRTime > DEBOUNCE) {
            lastQR = qr; lastQRTime = now;
            camFlash();
            loadPieza(qr);
          }
        }
      } catch(_) {}
    }
  }
  setTimeout(() => scanLoopJsQR(video), 100); // ~10fps — suficiente y no agota batería
}

function camFlash() {
  const el = document.getElementById('camFlash');
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 120);
}

// ── Cargar pieza ──────────────────────────────────────────
// El QR puede contener la URL completa (ej: https://apex.glass/...?qr=R-806-P2-1de2)
// o solo el código (R-806-P2-1de2). Esta función extrae siempre el código limpio.
function extraerCodigo(raw) {
  try {
    const url = new URL(raw);
    const param = url.searchParams.get('qr') || url.searchParams.get('QR');
    if (param) return param.toUpperCase().trim();
  } catch(_) {}
  return raw.toUpperCase().trim();
}

function extraerOrdenMasivo(raw) {
  try {
    const url = new URL(raw);
    const param = url.searchParams.get('orden_masivo') || url.searchParams.get('ORDEN_MASIVO');
    if (param) return parseInt(param, 10) || null;
  } catch(_) {}
  return null;
}

// QR de la remisión (app/imprimir_salida.php) — escaneado por el chofer al CARGAR el
// camión en planta. Ver api/salidas.php accion=scan_qr.
function extraerSalida(raw) {
  try {
    const url = new URL(raw);
    const param = url.searchParams.get('qr_salida') || url.searchParams.get('QR_SALIDA');
    if (param) return parseInt(param, 10) || null;
  } catch(_) {}
  return null;
}

// QR de la hoja de ruta (app/imprimir_ruta.php) — escaneado por el chofer al ENTREGAR en casa
// de cada cliente (ya no al salir hacia allá). Ver api/salidas.php accion=scan_qr_ruta.
function extraerRuta(raw) {
  try {
    const url = new URL(raw);
    const param = url.searchParams.get('qr_ruta') || url.searchParams.get('QR_RUTA');
    if (param) return parseInt(param, 10) || null;
  } catch(_) {}
  return null;
}

async function loadPieza(raw) {
  const rutaOrdenId = extraerRuta(raw);
  if (rutaOrdenId) { await loadSalidaRuta(rutaOrdenId); return; }

  const salidaOrdenId = extraerSalida(raw);
  if (salidaOrdenId) { await loadSalida(salidaOrdenId); return; }

  const ordenId = extraerOrdenMasivo(raw);
  if (ordenId) {
    if (sesionCorteActiva) { await agregarOrdenASesionCorte(ordenId); return; }
    await loadOrdenMasiva(ordenId);
    return;
  }

  const qr = extraerCodigo(raw);

  // Mientras hay una sesión de corte activa, cualquier QR de pieza escaneado
  // se agrega directo a la sesión (sin mostrar la tarjeta normal) — ver
  // abrirWizardCorte()/wizAvanzarACanto().
  if (sesionCorteActiva) { await agregarQrASesionCorte(qr); return; }

  try {
    const r = await fetch(API + 'pieza.php?qr=' + encodeURIComponent(qr));
    const d = await r.json();
    if (d.error) { showFeedback('err', '❌', 'QR no encontrado', qr); return; }
    pieza = d.pieza;
    renderPieza(d.pieza);
  } catch(e) { toast('Error de conexión', 'error'); }
}

function renderPieza(p) {
  document.getElementById('emptyState').style.display = 'none';
  document.getElementById('ordenMasivaCard').classList.remove('show');
  document.getElementById('piezaCard').classList.add('show');
  document.getElementById('pcFolio').textContent   = p.folio + ' — P' + p.partida;
  document.getElementById('pcCliente').textContent = p.cliente_nombre || '—';
  document.getElementById('pcPartida').textContent = 'P' + p.partida + ' · ' + p.pieza_num + '/' + p.pieza_total;
  document.getElementById('pcMedidas').textContent = p.ancho_mm + ' × ' + p.alto_mm;
  document.getElementById('pcCristal').textContent = p.cristal || '—';

  const inf  = ESTATUS[p.estatus] || { label: p.estatus, color:'#666' };
  const pill = document.getElementById('pcPill');
  pill.textContent      = inf.label;
  pill.style.background = inf.color + '22';
  pill.style.color      = inf.color;
  pill.style.border     = '1px solid ' + inf.color + '55';

  const tags = [];
  if (p.cpb && p.cpb !== '')  tags.push([p.cpb, true]);
  if (p.resaques > 0)         tags.push([p.resaques + ' Resaq.', false]);
  if (p.tp > 0)               tags.push([p.tp + ' TP', false]);
  if (p.ta > 0)               tags.push([p.ta + ' TA', false]);
  if (p.esmerilado)           tags.push(['Esmerilado', false]);
  if (p.pintura)              tags.push(['Pintura', false]);
  if (p.acabado_forma)        tags.push(['Acabado forma', false]);
  if (p.detalles && p.detalles !== 'NO') tags.push([p.detalles, true]);
  if (!p.requiere_templado)   tags.push(['🌡 RECOCIDO', true]);
  if (p.comentarios)          tags.push(['💬 ' + p.comentarios, false]);

  document.getElementById('pcTags').innerHTML = tags.length
    ? tags.map(([t,h]) => `<span class="tag${h?' hi':''}">${t}</span>`).join('')
    : '<span style="font-size:11px;color:var(--muted)">Sin trabajos especiales</span>';

  setupButton(p);
  document.getElementById('piezaCard').scrollIntoView({ behavior:'smooth', block:'start' });
}

function setupButton(p) {
  const btn = document.getElementById('btnAction');
  // Reset estado del botón
  btn.style.background = '';
  btn.style.cursor     = '';
  btn.disabled         = false;
  // Limpiar botones extra anteriores globalmente
  document.querySelectorAll('.btn-extra, .btn-info-extra').forEach(e => e.remove());
  const est = session.estacion || session.rol || 'admin';

  // Roles con acceso total (jefe_piso, director, dir_admin)
  const esAdmin = ['admin','jefe_piso','director','dir_admin'].includes(est);

  if (esAdmin) {
    const next = nextEstatus(p);
    if (next) {
      btn.textContent = '▶ Avanzar → ' + (ESTATUS[next]?.label || next);
      btn.className   = 'btn-action go';
      btn.onclick     = () => doUpdate(next);
    } else {
      btn.textContent = '✅ Completado';
      btn.className   = 'btn-action done';
      btn.onclick     = null;
    }
    return;
  }

  // Roles que no tienen acción de producción
  if (['comercial','administracion','dueno'].includes(est)) {
    btn.textContent = 'Solo lectura';
    btn.className   = 'btn-action done';
    btn.onclick     = null;
    return;
  }

  // Trazo y Taladro pueden registrar canteado si la pieza está en canteado pendiente
  // además de su propio estatus
  const reg = ESTACION_REG[est];
  if (!reg) {
    btn.textContent = 'Sin acción en esta estación';
    btn.className   = 'btn-action done';
    btn.onclick     = null;
    return;
  }

  // ── CORTE ─────────────────────────────────────────────────
  // El escaneo individual de una pieza ya NO la avanza directo — el flujo
  // ahora es: primero elegir la lámina/pedacería (botón ✂️ Cortar), y ya
  // con la sesión activa, escanear las piezas que se van sacando de ella.
  // Si esta tarjeta llegó a mostrarse es porque no hay sesión activa
  // (mientras hay sesión activa, loadPieza() intercepta el escaneo antes
  // de llegar aquí — ver manejarQrEnSesionCorte()).
  if (est === 'corte') {
    const wrap = document.getElementById('btnAction').parentElement;
    wrap.querySelectorAll('.btn-extra').forEach(e => e.remove());
    if (p.estatus === 'pendiente' || p.estatus === 'en_corte') {
      btn.textContent = '✂️ Iniciar sesión de corte';
      btn.className   = 'btn-action go';
      btn.onclick     = () => abrirWizardCorte();
    } else {
      btn.textContent = '✅ Ya cortada';
      btn.className   = 'btn-action done';
      btn.onclick     = null;
    }
    return;
  }

  // ── HORNO: solo registra entrada al horno ────────────────
  if (est === 'templado') {
    if (p.estatus === 'taladro' || p.estatus === 'canteado') {
      btn.textContent      = '🔥 Entrar al Horno';
      btn.className        = 'btn-action go';
      btn.style.background = '#dc2626';
      btn.onclick          = () => doUpdate('en_horno');
    } else if (p.estatus === 'en_horno') {
      btn.textContent = '⏳ En horno — espera estación Terminado';
      btn.className   = 'btn-action done';
      btn.onclick     = null;
    } else if (['trazo','cortado','en_corte'].indexOf(p.estatus) !== -1) {
      var omisionHornoMsg = {
        trazo:    'TALADRO no marcó la pieza',
        cortado:  'CANTEADO y TALADRO no marcaron',
        en_corte: 'CORTE, CANTEADO y TALADRO no marcaron',
      };
      btn.textContent   = '⚠️ ' + (omisionHornoMsg[p.estatus] || 'Estaciones anteriores no marcaron') + ' — Confirmar omisión';
      btn.className     = 'btn-action';
      btn.style.cssText = 'background:#d97706;color:#000;font-size:14px;';
      var _pe3 = p.estatus;
      btn.onclick = function() { abrirModalOmision('templado', _pe3, 'en_horno'); };
    } else {
      btn.textContent = '✅ Ya procesado';
      btn.className   = 'btn-action done';
      btn.onclick     = null;
    }
    return;
  }

  // ── TERMINADO: registra salida del horno ─────────────────
  if (est === 'terminado') {
    const contenedor = document.getElementById('btnAction').parentElement;
    const extras = contenedor.querySelectorAll('.btn-extra');
    extras.forEach(e => e.remove());

    if (p.estatus === 'en_horno') {
      btn.textContent      = '✅ Salió OK → Terminado';
      btn.className        = 'btn-action go';
      btn.style.background = '#16a34a';
      btn.onclick          = () => doUpdate('terminado');
      const btnReproc = document.createElement('button');
      btnReproc.textContent   = '🔄 Reproceso → Pendiente';
      btnReproc.className     = 'btn-action btn-extra';
      btnReproc.style.cssText = 'background:#d97706;margin-top:10px;width:100%';
      btnReproc.onclick       = () => doReproceso();
      contenedor.appendChild(btnReproc);
    } else if (p.estatus === 'terminado') {
      btn.textContent = '✅ Ya terminado';
      btn.className   = 'btn-action done';
      btn.onclick     = null;
    } else if (parseInt(p.requiere_templado) === 1 && ['taladro','canteado'].indexOf(p.estatus) !== -1) {
      btn.textContent   = '⚠️ TEMPLADO no marcó entrada al horno — Confirmar omisión';
      btn.className     = 'btn-action';
      btn.style.cssText = 'background:#d97706;color:#000;font-size:14px;';
      var _pe2 = p.estatus;
      btn.onclick = function() { abrirModalOmision('terminado', _pe2, 'terminado'); };
    } else if (parseInt(p.requiere_templado) === 0 && p.estatus === 'taladro') {
      // Flujo normal sin templado: taladro → terminado
      btn.textContent      = '✅ Salió OK → Terminado';
      btn.className        = 'btn-action go';
      btn.style.background = '#16a34a';
      btn.onclick          = () => doUpdate('terminado');
      var btnReproc2 = document.createElement('button');
      btnReproc2.textContent   = '🔄 Reproceso → Pendiente';
      btnReproc2.className     = 'btn-action btn-extra';
      btnReproc2.style.cssText = 'background:#d97706;margin-top:10px;width:100%';
      btnReproc2.onclick       = () => doReproceso();
      contenedor.appendChild(btnReproc2);
    } else if (['pendiente','en_corte','cortado','canteado','trazo'].indexOf(p.estatus) !== -1) {
      // Estaciones intermedias saltadas — ofrecer omisión
      var omisionTermMsg = {
        pendiente: 'Ninguna estación marcó la pieza',
        en_corte:  'CORTE, CANTEADO, TRAZO y TALADRO no marcaron',
        cortado:   'CANTEADO, TRAZO y TALADRO no marcaron',
        canteado:  'TRAZO y TALADRO no marcaron',
        trazo:     'TALADRO no marcó la pieza',
      };
      btn.textContent   = '⚠️ ' + (omisionTermMsg[p.estatus] || 'Estaciones anteriores no marcaron') + ' — Confirmar omisión';
      btn.className     = 'btn-action';
      btn.style.cssText = 'background:#d97706;color:#000;font-size:14px;';
      var _pe3 = p.estatus;
      btn.onclick = function() { abrirModalOmision('terminado', _pe3, 'terminado'); };
    } else {
      btn.textContent   = '⛔ Pieza no está lista para terminar';
      btn.className     = 'btn-action done';
      btn.style.cssText = 'background:#334155;color:#94a3b8;cursor:not-allowed;font-size:14px;opacity:1';
      btn.onclick       = null;
    }
    return;
  }

  // ── CANTEADO, TRAZO, TALADRO: acción normal + reproceso ──
  if (['canteado','trazo','taladro'].includes(est)) {
    const contenedor = document.getElementById('btnAction').parentElement;
    const extras = contenedor.querySelectorAll('.btn-extra');
    extras.forEach(e => e.remove());

    // Validar que la pieza haya pasado por la estación anterior
    const PREVIO_REQUERIDO = {
      canteado: ['cortado'],
      trazo:    ['canteado'],
      taladro:  ['trazo'],
    };
    const previosOk = PREVIO_REQUERIDO[est] || [];
    const piezaLista = previosOk.includes(p.estatus) || p.estatus === reg;

    if (!piezaLista) {
      var omisionMsg = {
        canteado: 'CORTE no marcó la pieza como cortada',
        trazo:    'CANTEADO no marcó la pieza',
        taladro:  'TRAZO no marcó la pieza',
      };
      btn.textContent   = '⚠️ ' + (omisionMsg[est] || 'Estación anterior no marcó') + ' — Confirmar omisión';
      btn.className     = 'btn-action';
      btn.style.cssText = 'background:#d97706;color:#000;font-size:14px;';
      var _est = est, _pe = p.estatus, _re = reg;
      btn.onclick = function() { abrirModalOmision(_est, _pe, _re); };
      return;
    }

    if (p.estatus === reg) {
      btn.textContent = '✅ Ya registrado aquí';
      btn.className   = 'btn-action done';
      btn.onclick     = null;
    } else {
      btn.textContent = '▶ Registrar: ' + (ESTATUS[reg]?.label || reg);
      btn.className   = 'btn-action go';
      btn.onclick     = () => doUpdate(reg);
      // Botón reproceso
      const btnReproc = document.createElement('button');
      btnReproc.textContent = '🔄 Reproceso → Pendiente';
      btnReproc.className   = 'btn-action btn-extra';
      btnReproc.style.cssText = 'background:#d97706;margin-top:10px;width:100%';
      btnReproc.onclick = () => doReproceso();
      contenedor.appendChild(btnReproc);
    }
    return;
  }

  if (p.estatus === reg) {
    btn.textContent = '✅ Ya registrado aquí';
    btn.className   = 'btn-action done';
    btn.onclick     = null;
  } else {
    btn.textContent = '▶ Registrar: ' + (ESTATUS[reg]?.label || reg);
    btn.className   = 'btn-action go';
    btn.onclick     = () => doUpdate(reg);
  }
}

function nextEstatus(p) {
  const ag = (p.tp > 0 || p.ta > 0 || p.resaques > 0);
  const re = !p.requiere_templado;
  switch(p.estatus) {
    case 'pendiente':  return 'en_corte';
    case 'en_corte':   return 'cortado';
    case 'cortado':    return 'canteado';
    case 'canteado':   return ag ? 'trazo' : (re ? 'terminado' : 'en_horno');
    case 'trazo':      return 'taladro';
    case 'taladro':    return re ? 'terminado' : 'en_horno';
    case 'en_horno':   return 'terminado';
    case 'terminado':  return 'entregado';
    default:           return null;
  }
}

// ── Reproceso ────────────────────────────────────────────
async function doReproceso() {
  if (!pieza) return;
  if (!confirm('¿Confirmar REPROCESO? La pieza regresará a PENDIENTE para volver a cortar.')) return;
  const btn = document.getElementById('btnAction');
  btn.innerHTML = '<span class="spin"></span>'; btn.disabled = true;
  try {
    const r = await fetch(API + 'actualizar_estatus.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ qr_code: pieza.qr_code, estatus: 'pendiente', usuario_id: session.id, notas: 'Reproceso desde ' + pieza.estatus })
    });
    const d = await r.json();
    if (d.ok) {
      pieza.estatus = 'pendiente';
      showFeedback('ok', '🔄', 'Reproceso', pieza.folio + ' P' + pieza.partida + ' → Pendiente');
      setupButton(pieza);
    } else {
      toast('❌ ' + (d.error || 'Error'), 'error');
    }
  } catch(e) { toast('❌ Error de conexión', 'error'); }
  btn.disabled = false;
}

// ── Modal Omisión ─────────────────────────────────────────
var _omisionNuevoEstatus = null;

function abrirModalOmision(est, estatusActual, nuevoEstatus) {
  var msgs = {
    canteado:  'CORTE (pieza no marcada como cortada)',
    trazo:     'CANTEADO (pieza no marcada)',
    taladro:   'TRAZO (pieza no marcada)',
    terminado: 'TEMPLADO (pieza no entró al horno)',
  };
  _omisionNuevoEstatus = nuevoEstatus;
  document.getElementById('omisionSub').textContent =
    'La pieza está en "' + estatusActual.toUpperCase() + '". Estación ' + (msgs[est] || est) + '.';
  document.getElementById('modalOmision').classList.add('open');
}

function cerrarModalOmision() {
  document.getElementById('modalOmision').classList.remove('open');
  _omisionNuevoEstatus = null;
}

async function confirmarOmision() {
  if (!pieza || !_omisionNuevoEstatus) return;
  var est = _omisionNuevoEstatus;
  cerrarModalOmision();
  await doUpdate(est, true);
}

// ── Actualizar estatus ────────────────────────────────────
async function doUpdate(nuevoEstatus, esOmision) {
  if (!pieza) return;
  const btn  = document.getElementById('btnAction');
  const orig = btn.textContent;
  btn.innerHTML = '<span class="spin"></span>'; btn.disabled = true;
  try {
    const r = await fetch(API + 'actualizar_estatus.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ qr_code: pieza.qr_code, estatus: nuevoEstatus, usuario_id: session.id, omision: esOmision ? 1 : 0 })
    });
    const d = await r.json();
    if (d.ok) {
      pieza.estatus = nuevoEstatus;
      const inf = ESTATUS[nuevoEstatus];
      const sub = d.orden_completa
        ? '¡Orden ' + d.folio + ' completada!'
        : d.folio + ' P' + pieza.partida + ' · ' + pieza.pieza_num + '/' + pieza.pieza_total;
      showFeedback('ok', '✅', inf?.label || nuevoEstatus, sub);
      const pill = document.getElementById('pcPill');
      pill.textContent      = inf.label;
      pill.style.background = inf.color + '22';
      pill.style.color      = inf.color;
      pill.style.border     = '1px solid ' + inf.color + '55';
      setupButton(pieza);
    } else {
      toast('❌ ' + (d.error || 'Error'), 'error');
      btn.textContent = orig;
    }
  } catch(e) {
    toast('❌ Error de conexión', 'error');
    btn.textContent = orig;
  } finally { btn.disabled = false; }
}

// ── Orden masiva (QR maestro de Corte) ────────────────────
let ordenMasivaActual = null;

async function loadOrdenMasiva(ordenId) {
  const est = session.estacion || session.rol || 'admin';
  if (est !== 'corte') {
    toast('Este QR es solo para la estación de Corte', 'error');
    return;
  }
  try {
    const r = await fetch(API + 'orden_masivo.php?orden_id=' + encodeURIComponent(ordenId));
    const d = await r.json();
    if (d.error) { showFeedback('err', '❌', 'Orden no encontrada', ''); return; }
    ordenMasivaActual = d;
    renderOrdenMasiva(d);
  } catch(e) { toast('Error de conexión', 'error'); }
}

function renderOrdenMasiva(d) {
  document.getElementById('emptyState').classList.remove('show');
  document.getElementById('emptyState').style.display = 'none';
  document.getElementById('piezaCard').classList.remove('show');
  document.getElementById('ordenMasivaCard').classList.add('show');

  document.getElementById('omFolio').textContent   = d.folio;
  document.getElementById('omCliente').textContent = d.cliente || '—';

  const body   = document.getElementById('omBody');
  const acts   = document.getElementById('omActions');

  if (d.pendientes === 0) {
    body.textContent = 'Esta orden ya fue registrada en CNC';
    acts.innerHTML = '';
    return;
  }

  body.textContent = d.pendientes + (d.pendientes === 1 ? ' pieza pendiente' : ' piezas pendientes') + ' → pasarán a EN CNC';
  acts.innerHTML = '';

  const btnOk = document.createElement('button');
  btnOk.className = 'btn-action go';
  btnOk.textContent = '▶ Confirmar y registrar en CNC';
  btnOk.onclick = confirmarOrdenMasiva;

  const btnCancel = document.createElement('button');
  btnCancel.className = 'btn-sec';
  btnCancel.style.marginTop = '8px';
  btnCancel.textContent = 'Cancelar';
  btnCancel.onclick = cancelarOrdenMasiva;

  acts.appendChild(btnOk);
  acts.appendChild(btnCancel);
}

async function confirmarOrdenMasiva() {
  if (!ordenMasivaActual) return;
  const acts = document.getElementById('omActions');
  acts.innerHTML = '<span class="spin"></span>';
  try {
    const r = await fetch(API + 'actualizar_estatus_masivo.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orden_id: ordenMasivaActual.orden_id, usuario_id: session.id })
    });
    const d = await r.json();
    if (d.ok) {
      showFeedback('ok', '✅', 'Orden ' + d.folio, d.actualizadas + ' piezas registradas en CNC');
      cancelarOrdenMasiva();
    } else {
      toast('❌ ' + (d.error || 'Error'), 'error');
    }
  } catch(e) {
    toast('❌ Error de conexión', 'error');
  }
}

// ── Sesión de corte: 1) elegir lámina/pedacería, 2) escanear piezas ───────
// Tipos y espesores reales tomados del catálogo de Cristales (no de un
// arreglo fijo) — así incluye también tipos sin lámina dada de alta en
// inventario (ej. Bronce, Ultra Claro). Ver api/sesion_corte.php accion=catalogo_tipos_mm.
let catalogoTiposMm = [];

function abrirWizardCorte() {
  // Defensa extra: el botón "Cortar" ya se oculta mientras hay sesión activa
  // (actualizarUiSesionCorte), pero si de todos modos se llega aquí con una
  // sesión abierta, se manda directo a la revisión en vez de empezar otra.
  if (sesionCorteActiva) { wizAbrirRevision(); return; }
  document.getElementById('modalCorte').classList.add('open');
  renderCortePasoTipo();
}

function cerrarWizardCorte() {
  document.getElementById('modalCorte').classList.remove('open');
}

// ── Paso 1: tipo de lámina ─────────────────────────────────
async function renderCortePasoTipo() {
  document.getElementById('corteSub').textContent = 'Paso 1 de 3 — Tipo de lámina';
  document.getElementById('corteWizardBody').innerHTML = '<div style="text-align:center;padding:20px"><span class="spin"></span></div>';
  try {
    var r = await fetch(API + 'sesion_corte.php?accion=catalogo_tipos_mm');
    var d = await r.json();
    catalogoTiposMm = d.tipos || [];
    var opts = catalogoTiposMm.map(function(t) { return '<option value="' + t.label + '">' + t.label + '</option>'; }).join('');
    document.getElementById('corteWizardBody').innerHTML =
      '<div style="margin-bottom:14px"><select id="corteTipoSel" class="manual-input" style="width:100%">' + opts + '</select></div>' +
      '<button class="btn-action go" onclick="wizElegirTipo()">Siguiente</button>';
  } catch(e) { toast('Error al consultar catálogo', 'error'); }
}

// ── Paso 2: espesor (mm) — según lo que exista de ese tipo en Cristales ───
function wizElegirTipo() {
  var label = document.getElementById('corteTipoSel').value;
  var entry = catalogoTiposMm.find(function(t) { return t.label === label; });
  sesionCorte.tipo     = label;
  sesionCorte.tipoEnum = entry ? entry.enum : null;
  document.getElementById('corteSub').textContent = 'Paso 2 de 3 — Espesor (mm)';

  var espesores = entry ? entry.espesores : [];
  if (!espesores.length) {
    document.getElementById('corteWizardBody').innerHTML =
      '<div class="compat-note show" style="margin-bottom:14px">Sin espesores registrados para este tipo.</div>' +
      '<input id="corteEspesorManual" class="manual-input" type="number" step="0.5" placeholder="mm" style="width:100%;margin-bottom:12px">' +
      '<button class="btn-action go" onclick="wizElegirEspesorManual()">Siguiente</button>';
    return;
  }
  document.getElementById('corteWizardBody').innerHTML =
    '<div class="razones-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:12px">' +
    espesores.map(function(e) { return '<button class="btn-razon" onclick="wizElegirEspesor(' + e + ')">' + parseFloat(e) + ' mm</button>'; }).join('') +
    '</div>';
}

function wizElegirEspesorManual() {
  var esp = parseFloat(document.getElementById('corteEspesorManual').value);
  if (!esp || esp <= 0) { toast('Ingresa un espesor válido', 'error'); return; }
  wizElegirEspesor(esp);
}

// ── Paso 3: lámina de catálogo (dropdown) o pedacería (medidas) ───────────
// Si el tipo elegido no tiene equivalente en el ENUM de `laminas` (tipoEnum
// vacío), no hay nada que consultar — se ofrece pedacería directamente.
async function wizElegirEspesor(espesor) {
  sesionCorte.espesor = parseFloat(espesor);
  sesionCorte.origen = 'catalogo';
  document.getElementById('corteSub').textContent = 'Paso 3 de 3 — Elige la lámina';

  if (!sesionCorte.tipoEnum) { renderCortePasoLamina([]); return; }

  document.getElementById('corteWizardBody').innerHTML = '<div style="text-align:center;padding:20px"><span class="spin"></span></div>';
  try {
    var r = await fetch(API + 'sesion_corte.php?accion=laminas_disponibles&tipo=' + encodeURIComponent(sesionCorte.tipoEnum) + '&espesor_mm=' + sesionCorte.espesor);
    var d = await r.json();
    renderCortePasoLamina(d.laminas || []);
  } catch(e) { toast('Error al consultar láminas', 'error'); }
}

var _laminasActuales = [];

function renderCortePasoLamina(laminas) {
  _laminasActuales = laminas;
  var opts = laminas.map(function(l) {
    return '<option value="' + l.id + '" data-ancho="' + l.ancho_mm + '" data-alto="' + l.alto_mm + '">' +
      l.ancho_mm + ' × ' + l.alto_mm + ' mm (stock: ' + l.stock_laminas + ')</option>';
  }).join('');

  sesionCorte.origen = 'catalogo';
  document.getElementById('corteWizardBody').innerHTML =
    (laminas.length
      ? '<div style="display:flex;gap:8px;align-items:center;margin-bottom:12px">' +
          '<select id="corteLaminaSel" class="manual-input" style="flex:1">' + opts + '</select>' +
          '<button onclick="wizTogglePedaceria()" style="background:#7c3aed;color:#fff;border:none;border-radius:8px;padding:11px 14px;font-weight:800;cursor:pointer;white-space:nowrap">🧩 Pedacería</button>' +
        '</div>'
      : '<div class="compat-note show" style="margin-bottom:14px">Sin stock de este tipo/espesor en catálogo.</div>' +
        '<button onclick="wizTogglePedaceria()" style="width:100%;background:#7c3aed;color:#fff;border:none;border-radius:10px;padding:14px;font-weight:800;cursor:pointer;margin-bottom:12px">🧩 Usar pedacería</button>'
    ) +
    '<div id="cortePedaceriaWrap" style="display:none;margin-bottom:12px">' +
      '<div style="display:flex;gap:8px;margin-bottom:10px">' +
        '<input id="wizPedAncho" class="manual-input" type="number" placeholder="Ancho mm" style="flex:1">' +
        '<input id="wizPedAlto" class="manual-input" type="number" placeholder="Alto mm" style="flex:1">' +
      '</div>' +
      '<button class="btn-sec" style="width:100%" onclick="wizVolverACatalogo()">← Volver</button>' +
    '</div>' +
    '<button class="btn-action go" style="margin-top:12px" onclick="wizSiguienteLamina()">Siguiente</button>';
}

function wizTogglePedaceria() {
  sesionCorte.origen = 'pedaceria';
  var sel = document.getElementById('corteLaminaSel');
  if (sel) sel.parentElement.style.display = 'none';
  document.getElementById('cortePedaceriaWrap').style.display = 'block';
}

function wizVolverACatalogo() {
  renderCortePasoLamina(_laminasActuales);
}

function wizSiguienteLamina() {
  if (sesionCorte.origen === 'pedaceria') {
    var ancho = parseInt(document.getElementById('wizPedAncho').value, 10);
    var alto  = parseInt(document.getElementById('wizPedAlto').value, 10);
    if (!ancho || !alto) { toast('Ingresa ancho y alto', 'error'); return; }
    sesionCorte.laminaId = null; sesionCorte.anchoMm = ancho; sesionCorte.altoMm = alto;
  } else {
    var sel = document.getElementById('corteLaminaSel');
    if (!sel || !sel.value) { toast('Elige una lámina o usa pedacería', 'error'); return; }
    var opt = sel.options[sel.selectedIndex];
    sesionCorte.laminaId = parseInt(sel.value, 10);
    sesionCorte.anchoMm  = parseInt(opt.dataset.ancho, 10);
    sesionCorte.altoMm   = parseInt(opt.dataset.alto, 10);
  }
  sesionCorte.piezas = [];
  sesionCorte.removidas = [];
  sesionCorteActiva = true;
  cerrarWizardCorte();
  actualizarUiSesionCorte();
  guardarSesionAbiertaServidor();
  showFeedback('ok', '📷', 'Sesión de corte activa', 'Escanea las piezas que vayas cortando');
}

// ── Escaneo durante la sesión ──────────────────────────────
function actualizarUiSesionCorte() {
  var est = session.estacion || session.rol || 'admin';
  var btnIniciar = document.getElementById('btnIniciarCorte');
  var franja     = document.getElementById('franjaSesionCorte');
  if (!btnIniciar || !franja) return;
  if (est !== 'corte') { btnIniciar.style.display = 'none'; franja.style.display = 'none'; return; }
  if (sesionCorteActiva) {
    btnIniciar.style.display = 'none';
    franja.style.display = 'block';
    document.getElementById('franjaSesionInfo').textContent =
      '📷 Escaneando · ' + sesionCorte.piezas.length + ' pieza(s)';
  } else {
    btnIniciar.style.display = 'block';
    franja.style.display = 'none';
  }
}

function sesionEnLista(piezaId) {
  return sesionCorte.piezas.some(function(x) { return x.id === piezaId; });
}

// ── Persistencia en servidor de la sesión abierta (sobrevive recargas) ────
async function guardarSesionAbiertaServidor() {
  try {
    await fetch(API + 'sesion_corte.php?accion=guardar_sesion_abierta', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        tipo: sesionCorte.tipo, tipo_enum: sesionCorte.tipoEnum, espesor_mm: sesionCorte.espesor,
        es_pedaceria: sesionCorte.origen === 'pedaceria', lamina_id: sesionCorte.laminaId,
        ancho_mm: sesionCorte.anchoMm, alto_mm: sesionCorte.altoMm, piezas: sesionCorte.piezas,
        removidas: sesionCorte.removidas,
      })
    });
  } catch(e) { /* best-effort — no bloquea el flujo si falla la red */ }
}

// Al entrar a la pantalla de escaneo, revisa si este operador ya dejó una
// sesión de corte abierta (recarga, pestaña cerrada, otro dispositivo) y la
// restaura en vez de dejarlo iniciar una nueva encimada.
async function restaurarSesionCorteAbierta() {
  var est = session.estacion || session.rol || 'admin';
  if (est !== 'corte') return;
  try {
    var r = await fetch(API + 'sesion_corte.php?accion=sesion_abierta');
    var d = await r.json();
    if (!d.sesion) return;
    var s = d.sesion;
    sesionCorte = {
      origen: s.es_pedaceria == 1 ? 'pedaceria' : 'catalogo',
      tipo: s.tipo, tipoEnum: s.tipo_enum, espesor: parseFloat(s.espesor_mm),
      laminaId: s.lamina_id ? parseInt(s.lamina_id, 10) : null,
      anchoMm: s.ancho_mm ? parseInt(s.ancho_mm, 10) : null,
      altoMm: s.alto_mm ? parseInt(s.alto_mm, 10) : null,
      piezas: s.piezas || [],
      removidas: s.removidas || [],
    };
    sesionCorteActiva = true;
    actualizarUiSesionCorte();
    toast('✂️ Se restauró tu sesión de corte activa (' + sesionCorte.piezas.length + ' pieza(s))', 'info');
  } catch(e) { /* si falla, el operador puede seguir usando la app normal */ }
}

// Mismo criterio que api/helpers/cristal_parser.php (parsearCristalLabelEspesor)
// para validar en el navegador, sin esperar respuesta del servidor, que la
// pieza escaneada sea del mismo tipo/espesor que se eligió para la sesión.
function parsearCristalLabelEspesorJs(texto) {
  if (!texto) return null;
  var n = texto.replace(/\s*-\s*(Servicio Express|Con Esmerilado)\s*$/i, '');
  n = n.replace(/^Plantilla\s+/i, '');
  var m = n.trim().match(/^(.*?)\s*[-]?\s*(\d+(\.\d+)?)\s*mm$/i);
  if (!m) return null;
  var label = m[1].replace(/\s+de$/i, '').trim().replace(/[\s-]+$/, '');
  if (!label) return null;
  return { label: label, espesor: parseFloat(m[2]) };
}

function cristalCoincideConSesionJs(cristalTexto) {
  var parsed = parsearCristalLabelEspesorJs(cristalTexto);
  if (!parsed) return true; // no se pudo parsear — no bloquea (mismo criterio que backend)
  var normLabel = parsed.label.replace(/\s+/g, '').toLowerCase();
  var normSesion = (sesionCorte.tipo || '').replace(/\s+/g, '').toLowerCase();
  return Math.abs(parsed.espesor - sesionCorte.espesor) < 0.01 && normLabel === normSesion;
}

async function agregarQrASesionCorte(qr) {
  try {
    const r = await fetch(API + 'pieza.php?qr=' + encodeURIComponent(qr));
    const d = await r.json();
    if (d.error) { showFeedback('err', '❌', 'QR no encontrado', qr); return; }
    var p = d.pieza;
    if (!['pendiente', 'en_corte'].includes(p.estatus)) {
      showFeedback('err', '⛔', 'Pieza no disponible', 'Estatus actual: ' + p.estatus);
      return;
    }
    if (!cristalCoincideConSesionJs(p.cristal)) {
      showFeedback('err', '⛔', 'Tipo/espesor distinto', p.cristal + ' — la sesión es de ' + sesionCorte.tipo + ' ' + sesionCorte.espesor + 'mm');
      return;
    }
    if (sesionEnLista(p.id)) {
      toast('Esa pieza ya está en la sesión', 'info');
      return;
    }
    if (sesionCorte.removidas.some(function(r) { return r.id === p.id; })) {
      showFeedback('err', '⛔', 'Pieza quitada de esta sesión', p.folio + ' P' + p.partida + ' — ya la habías marcado como no cortada. Deshaz la remoción en "Ver lista" si fue un error.');
      return;
    }
    sesionCorte.piezas.push({
      id: p.id, qr_code: p.qr_code, folio: p.folio, cliente_nombre: p.cliente_nombre,
      partida: p.partida, pieza_num: p.pieza_num, pieza_total: p.pieza_total,
      cristal: p.cristal, cristal_corto: p.cristal_corto,
      ancho_mm: p.ancho_mm, alto_mm: p.alto_mm, m2: p.m2,
    });
    camFlash();
    actualizarUiSesionCorte();
    guardarSesionAbiertaServidor();
    toast('✂️ ' + p.folio + ' P' + p.partida + ' agregada (' + sesionCorte.piezas.length + ')', 'success');
  } catch(e) { toast('Error de conexión', 'error'); }
}

async function agregarOrdenASesionCorte(ordenId) {
  try {
    const r = await fetch(API + 'orden_masivo.php?orden_id=' + encodeURIComponent(ordenId));
    const d = await r.json();
    if (d.error) { showFeedback('err', '❌', 'Orden no encontrada', ''); return; }
    const r2 = await fetch(API + 'buscar_orden.php?num=' + encodeURIComponent(d.folio) + '&estacion=corte');
    const d2 = await r2.json();
    if (d2.error || !d2.ordenes?.length) { toast('Sin piezas pendientes en esa orden', 'info'); return; }
    var agregadas = 0, omitidas = 0;
    d2.ordenes.forEach(function(orden) {
      (orden.piezas || []).forEach(function(p) {
        if (!['pendiente', 'en_corte'].includes(p.estatus) || sesionEnLista(p.id)) return;
        if (sesionCorte.removidas.some(function(r) { return r.id === p.id; })) return;
        if (!cristalCoincideConSesionJs(p.cristal)) { omitidas++; return; }
        sesionCorte.piezas.push({
          id: p.id, qr_code: p.qr_code, folio: orden.folio, cliente_nombre: orden.cliente_nombre,
          partida: p.partida, pieza_num: p.pieza_num, pieza_total: p.pieza_total,
          cristal: p.cristal, cristal_corto: p.cristal_corto,
          ancho_mm: p.ancho_mm, alto_mm: p.alto_mm,
          m2: p.ancho_mm && p.alto_mm ? (p.ancho_mm * p.alto_mm / 1000000) : null,
        });
        agregadas++;
      });
    });
    actualizarUiSesionCorte();
    if (agregadas > 0) guardarSesionAbiertaServidor();
    showFeedback('ok', '✂️', 'Orden ' + d.folio,
      agregadas + ' pieza(s) agregada(s)' + (omitidas ? ' · ' + omitidas + ' omitida(s) por tipo/espesor distinto' : ''));
  } catch(e) { toast('Error de conexión', 'error'); }
}

// ── Revisión de la sesión (botón "Ver lista") ─────────────
function wizAbrirRevision() {
  document.getElementById('modalCorte').classList.add('open');
  document.getElementById('corteSub').textContent = 'Piezas en esta sesión';
  renderCorteRevision();
}

function renderCorteRevision() {
  var rows = sesionCorte.piezas.map(function(p) {
    return '<div style="display:flex;justify-content:space-between;align-items:center;background:#252530;border-radius:10px;padding:10px 12px;margin-bottom:6px;border:1.5px solid #2a2a32">' +
      '<div><div style="font-size:13px;font-weight:700">' + p.folio + ' · P' + p.partida + '</div>' +
      '<div style="font-size:11px;color:#6b6b7a">' + (p.cristal_corto || p.cristal || '') + ' · ' + p.ancho_mm + '×' + p.alto_mm + 'mm</div></div>' +
      '<button onclick="wizQuitarDeSesion(' + p.id + ')" style="background:none;border:1.5px solid rgba(255,71,87,.4);color:#ff4757;border-radius:8px;padding:6px 12px;font-weight:800;cursor:pointer">✕</button>' +
      '</div>';
  }).join('') || '<div style="text-align:center;color:#6b6b7a;padding:14px">Aún no has escaneado ninguna pieza</div>';

  var rowsRemovidas = sesionCorte.removidas.map(function(p) {
    return '<div style="display:flex;justify-content:space-between;align-items:center;background:#1c1c22;border-radius:10px;padding:10px 12px;margin-bottom:6px;border:1.5px dashed rgba(255,71,87,.3);opacity:.75">' +
      '<div><div style="font-size:13px;font-weight:700;text-decoration:line-through">' + p.folio + ' · P' + p.partida + '</div>' +
      '<div style="font-size:11px;color:#6b6b7a">' + (p.cristal_corto || p.cristal || '') + ' · ' + p.ancho_mm + '×' + p.alto_mm + 'mm</div></div>' +
      '<button onclick="wizDeshacerRemocion(' + p.id + ')" style="background:none;border:1.5px solid rgba(34,197,94,.4);color:#22c55e;border-radius:8px;padding:6px 12px;font-weight:800;cursor:pointer">↩ Deshacer</button>' +
      '</div>';
  }).join('');

  document.getElementById('corteWizardBody').innerHTML =
    '<div style="font-size:12px;color:#6b6b7a;margin-bottom:8px">' + sesionCorte.piezas.length + ' pieza(s) — pasarán a CORTADO al avanzar a canto</div>' +
    rows +
    (rowsRemovidas ? '<div style="font-size:11px;color:#6b6b7a;margin:12px 0 6px">Quitadas de esta sesión (rotas/error)</div>' + rowsRemovidas : '') +
    '<button class="btn-action go" style="margin-top:12px;background:#16a34a" onclick="wizAvanzarACanto()" ' + (!sesionCorte.piezas.length ? 'disabled' : '') + '>✅ Avanzar a canto</button>' +
    '<button class="btn-sec" style="margin-top:8px;width:100%" onclick="cerrarWizardCorte()">Seguir escaneando</button>' +
    '<button class="btn-sec" style="margin-top:8px;width:100%;color:#ff4757;border-color:rgba(255,71,87,.4)" onclick="wizCancelarSesion()">Cancelar sesión completa</button>';
}

function wizQuitarDeSesion(piezaId) {
  var quitada = sesionCorte.piezas.filter(function(p) { return p.id === piezaId; })[0];
  sesionCorte.piezas = sesionCorte.piezas.filter(function(p) { return p.id !== piezaId; });
  // Recordar que se quitó a propósito (rota/error) para que no vuelva a entrar
  // sola si se re-escanea el QR de la pieza o el QR maestro de la orden.
  if (quitada && !sesionCorte.removidas.some(function(p) { return p.id === piezaId; })) {
    sesionCorte.removidas.push(quitada);
  }
  actualizarUiSesionCorte();
  guardarSesionAbiertaServidor();
  renderCorteRevision();
}

function wizDeshacerRemocion(piezaId) {
  var restaurada = sesionCorte.removidas.filter(function(p) { return p.id === piezaId; })[0];
  sesionCorte.removidas = sesionCorte.removidas.filter(function(p) { return p.id !== piezaId; });
  if (restaurada && !sesionEnLista(piezaId)) sesionCorte.piezas.push(restaurada);
  actualizarUiSesionCorte();
  guardarSesionAbiertaServidor();
  renderCorteRevision();
}

// Confirmación propia dentro del modal (no window.confirm — en algunos
// navegadores/PWA de celular el diálogo nativo no se dispara o queda oculto).
function wizCancelarSesion() {
  document.getElementById('corteWizardBody').innerHTML =
    '<div style="text-align:center;padding:10px 0 18px">' +
      '<div style="font-size:15px;font-weight:800;margin-bottom:6px">¿Cancelar la sesión completa?</div>' +
      '<div style="font-size:12px;color:#6b6b7a">Las ' + sesionCorte.piezas.length + ' pieza(s) escaneadas se descartan. No se toca la BD — ninguna pieza fue marcada cortado todavía.</div>' +
    '</div>' +
    '<button class="btn-action" style="background:#ff4757;color:#fff" onclick="wizConfirmarCancelarSesion()">Sí, cancelar sesión</button>' +
    '<button class="btn-sec" style="margin-top:8px;width:100%" onclick="renderCorteRevision()">No, seguir aquí</button>';
}

async function wizConfirmarCancelarSesion() {
  sesionCorteActiva = false;
  sesionCorte = { origen: null, tipo: null, tipoEnum: null, espesor: null, laminaId: null, anchoMm: null, altoMm: null, piezas: [], removidas: [] };
  cerrarWizardCorte();
  actualizarUiSesionCorte();
  try {
    await fetch(API + 'sesion_corte.php?accion=cancelar_sesion_abierta', { method: 'POST' });
  } catch(e) {}
  toast('Sesión cancelada', 'info');
}

// ── Finalizar: avanzar a canto ─────────────────────────────
async function wizAvanzarACanto() {
  if (!sesionCorte.piezas.length) { toast('Escanea al menos una pieza primero', 'error'); return; }
  try {
    const r = await fetch(API + 'sesion_corte.php?accion=confirmar_sesion', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        es_pedaceria: sesionCorte.origen === 'pedaceria',
        lamina_id: sesionCorte.laminaId, tipo: sesionCorte.tipo, tipo_enum: sesionCorte.tipoEnum,
        espesor_mm: sesionCorte.espesor,
        ancho_mm: sesionCorte.anchoMm, alto_mm: sesionCorte.altoMm,
        piezas: sesionCorte.piezas.map(function(p) { return { pieza_id: p.id }; }),
        piezas_removidas: sesionCorte.removidas.map(function(p) { return { pieza_id: p.id }; }),
      })
    });
    const d = await r.json();
    if (d.ok) {
      sesionCorteActiva = false;
      sesionCorte = { origen: null, tipo: null, tipoEnum: null, espesor: null, laminaId: null, anchoMm: null, altoMm: null, piezas: [], removidas: [] };
      cerrarWizardCorte();
      actualizarUiSesionCorte();
      showFeedback('ok', '✅', 'Avanzado a canto', 'Efectividad: ' + d.efectividad_pct + '%');
    } else {
      toast('❌ ' + (d.error || 'Error al confirmar'), 'error');
    }
  } catch(e) {
    toast('❌ Error de conexión', 'error');
  }
}

function cancelarOrdenMasiva() {
  ordenMasivaActual = null;
  document.getElementById('ordenMasivaCard').classList.remove('show');
  document.getElementById('emptyState').style.display = 'block';
}

// ── QR de la remisión / orden de salida (chofer, CARGA en planta) ──────────
// El chofer escanea el QR que ya trae impresa la remisión (imprimir_salida.php) mientras
// sube las órdenes al camión, antes de Iniciar Ruta. Escaneo dispara la acción de inmediato.
async function loadSalida(ordenId) {
  try {
    const r = await fetch(API + 'salidas.php?accion=scan_qr', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orden_id: ordenId })
    });
    const d = await r.json();
    if (d.error) { showFeedback('err', '❌', 'Error', d.error); return; }
    if (d.ruta_iniciada) {
      showFeedback('ok', '🚛', '¡Ruta iniciada!', 'Última pieza cargada — ya puedes salir');
    } else {
      showFeedback('ok', '📦', 'Cargada al camión', d.cargadas + ' / ' + d.total + ' piezas cargadas');
    }
  } catch(e) {
    toast('Error de conexión', 'error');
  }
}

// ── QR de la hoja de ruta (chofer, confirmación de entrega) ────────────────
// El chofer escanea el QR de la hoja de ruta (imprimir_ruta.php, una sección por parada)
// al ENTREGAR el pedido en casa del cliente — ya no al salir hacia allá. Mismo efecto que el
// botón manual "Entregado" del chofer en Logística Rutas.
async function loadSalidaRuta(ordenId) {
  try {
    const r = await fetch(API + 'salidas.php?accion=scan_qr_ruta', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orden_id: ordenId })
    });
    const d = await r.json();
    if (d.error) { showFeedback('err', '❌', 'Error', d.error); return; }

    if (d.ya_escaneado) {
      showFeedback('ok', '⏱', 'Ya registrada', 'Esta orden ya estaba marcada como entregada');
      return;
    }

    if (d.ruta_completada) {
      showFeedback('ok', '🏁', '¡Ruta completada! — ' + d.folio, 'Última entrega de la ruta');
    } else {
      showFeedback('ok', '✅', 'Entrega confirmada — ' + d.folio, 'Siguiente parada notificada');
    }
  } catch(e) {
    toast('Error de conexión', 'error');
  }
}

// ── Manual ────────────────────────────────────────────────
document.getElementById('manualQR').addEventListener('keydown', e => {
  if (e.key === 'Enter') buscarPorNumero();
});

async function buscarPorNumero() {
  const num = document.getElementById('manualQR').value.trim();
  if (!num) return;

  const panel = document.getElementById('busquedaPanel');
  panel.style.display = 'block';
  panel.innerHTML = '<div style="padding:16px;text-align:center;color:#6b6b7a;font-size:13px">Buscando...</div>';

  try {
    const est = session.estacion || session.rol || '';
    const res  = await fetch(API + 'buscar_orden.php?num=' + encodeURIComponent(num) + '&estacion=' + encodeURIComponent(est));
    const data = await res.json();

    if (data.error || !data.ordenes?.length) {
      panel.innerHTML = '<div style="padding:16px;text-align:center;color:#ff4757;font-size:13px">No se encontraron ordenes con ese numero</div>';
      return;
    }

    let html = '';
    for (const orden of data.ordenes) {
      const piezas = orden.piezas || [];
      if (!piezas.length) continue;

      html += `<div style="border-bottom:1px solid #2a2a32;padding:10px 14px">
        <div style="font-size:14px;font-weight:800;color:#f5a623">${orden.folio}</div>
        <div style="font-size:11px;color:#6b6b7a;margin-bottom:8px">${orden.cliente_nombre||''}</div>`;

      for (const p of piezas) {
        const tags = [];
        if (p.cpb && p.cpb !== 'No') tags.push(p.cpb);
        if (p.resaques > 0) tags.push(p.resaques + ' res.');
        if (p.tp > 0) tags.push(p.tp + ' TP');

        html += `<div onclick="seleccionarPieza('${p.qr_code}')" style="
          background:#252530;border-radius:10px;padding:10px 12px;
          margin-bottom:6px;cursor:pointer;border:1.5px solid #2a2a32;
          transition:border-color .15s" 
          onmousedown="this.style.borderColor='#f5a623'" 
          onmouseup="this.style.borderColor='#2a2a32'">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
              <span style="font-size:13px;font-weight:700;color:#f0f0f0">P${p.partida} &bull; Pieza ${p.pieza_num}/${p.pieza_total}</span>
              <div style="font-size:11px;color:#6b6b7a;margin-top:2px">${p.cristal_corto||p.cristal||''} &bull; ${p.ancho_mm}&times;${p.alto_mm}mm</div>
              ${tags.length ? '<div style="font-size:10px;color:#f5a623;margin-top:2px">' + tags.join(' &middot; ') + '</div>' : ''}
            </div>
            <div style="font-size:11px;font-weight:700;padding:3px 8px;border-radius:6px;background:#1a1a2e;color:#94a3b8">
              ${p.estatus.toUpperCase()}
            </div>
          </div>
        </div>`;
      }
      html += '</div>';
    }

    if (!html) {
      panel.innerHTML = '<div style="padding:16px;text-align:center;color:#6b6b7a;font-size:13px">No hay piezas disponibles para esta estacion</div>';
    } else {
      panel.innerHTML = html;
    }
  } catch(e) {
    panel.innerHTML = '<div style="padding:16px;text-align:center;color:#ff4757;font-size:13px">Error de conexion</div>';
  }
}

async function seleccionarPieza(qrCode) {
  // Cerrar panel y cargar la pieza
  document.getElementById('busquedaPanel').style.display = 'none';
  document.getElementById('manualQR').value = '';
  await loadPieza(qrCode);
}

// ── Historial ─────────────────────────────────────────────
async function verHistorial() {
  if (!pieza) return;
  try {
    const r = await fetch(API + 'pieza.php?qr=' + encodeURIComponent(pieza.qr_code));
    const d = await r.json();
    if (d.historial?.length) {
      const txt = d.historial.map(h => {
        const t = new Date(h.created_at).toLocaleString('es-MX',{hour:'2-digit',minute:'2-digit',day:'2-digit',month:'short'});
        return `${t}  ${h.estatus_nuevo}  ${h.usuario_nombre||'—'}`;
      }).join('\n');
      alert('Historial:\n\n' + txt);
    } else { toast('Sin historial aún', 'info'); }
  } catch(e) { toast('Error', 'error'); }
}
// ── Modal Retrabajo ───────────────────────────────────────
const RAZONES_RETRABAJO = {
  corte:     ['Roto en corte'],
  canteado:  ['Pelo', 'Desconche', 'Quebrado'],
  trazo:     ['Medidas incorrectas'],
  taladro:   ['Pieza rota', 'Pelo'],
  templado:  ['Estrellada/Tronada', 'Deshecha'],
  terminado: ['Estrellada/Tronada', 'Deshecha'],
};

// Mapeo de estatus de pieza → estación de retrabajo
const ESTATUS_A_ESTACION = {
  en_corte:  'corte',
  cortado:   'corte',
  canteado:  'canteado',
  trazo:     'trazo',
  taladro:   'taladro',
  en_horno:  'terminado',
  terminado: 'terminado',
};

const ESTACIONES_JEFE = ['corte', 'canteado', 'trazo', 'taladro', 'templado'];
const ESTACION_NOMBRE = {
  corte: '✂️ Corte', canteado: '🔩 Canteado',
  trazo: '✏️ Trazo', taladro: '🔧 Taladro', templado: '🔥 Templado',
};

let _razonSeleccionada = null;
let _estacionRetrabajo = null;

function reportarError() {
  if (!pieza) return;

  const est = session.estacion || session.rol || '';
  let estacionFinal;

  if (est === 'jefe_piso') {
    // Detectar estación automáticamente por el estatus de la pieza
    estacionFinal = ESTATUS_A_ESTACION[pieza.estatus];
    if (!estacionFinal) {
      toast('⚠️ Esta pieza no tiene razones de retrabajo para su estatus actual', 'info');
      return;
    }
  } else {
    estacionFinal = est;
  }

  const razones = RAZONES_RETRABAJO[estacionFinal];
  if (!razones) {
    toast('⚠️ Tu estación no tiene razones de retrabajo configuradas', 'info');
    return;
  }

  _estacionRetrabajo = estacionFinal;
  _razonSeleccionada = null;
  document.getElementById('btnConfirmarRet').disabled = true;
  document.getElementById('modalNotas').value = '';
  document.getElementById('modalRetSub').textContent =
    pieza.folio + ' · P' + pieza.partida + ' · Pieza ' + pieza.pieza_num + '/' + pieza.pieza_total +
    (est === 'jefe_piso' ? ' · ' + ESTACION_NOMBRE[estacionFinal] : '');

  document.getElementById('razonesGrid').innerHTML = razones.map(r => `
    <button class="btn-razon" onclick="seleccionarRazon(this, '${r}')">${r}</button>
  `).join('');

  document.getElementById('modalRetrabajo').classList.add('open');
}

window.seleccionarRazon = function(el, razon) {
  document.querySelectorAll('.btn-razon').forEach(b => b.classList.remove('selected'));
  el.classList.add('selected');
  _razonSeleccionada = razon;
  document.getElementById('btnConfirmarRet').disabled = false;
};

window.cerrarModalRetrabajo = function() {
  document.getElementById('modalRetrabajo').classList.remove('open');
  _razonSeleccionada = null;
};

async function confirmarRetrabajo() {
  if (!pieza || !_razonSeleccionada) return;

  const notas   = document.getElementById('modalNotas').value.trim();
  const razonFinal = notas ? _razonSeleccionada + (notas ? ' — ' + notas : '') : _razonSeleccionada;
  const btn     = document.getElementById('btnConfirmarRet');

  btn.innerHTML = '<span class="spin" style="border-top-color:#fff"></span>';
  btn.disabled  = true;

  try {
    const r = await fetch(API + 'reproceso.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        pieza_id:   pieza.id,
        razon:      razonFinal,
        razon_otro: notas,
      })
    });
    const d = await r.json();

    cerrarModalRetrabajo();

    if (d.ok) {
      pieza.estatus = 'pendiente';
      showFeedback('ok', '🔄', 'Retrabajo registrado', pieza.folio + ' → regresa a corte');
      setupButton(pieza);
    } else {
      toast('❌ ' + (d.error || 'Error al registrar'), 'error');
    }
  } catch(e) {
    cerrarModalRetrabajo();
    toast('❌ Error de conexión', 'error');
  }
}

// ── Feedback / Toast ──────────────────────────────────────
function showFeedback(tipo, icon, label, sub) {
  const el = document.getElementById('bigFb');
  el.className = 'big-fb show ' + tipo;
  document.getElementById('fbIcon').textContent  = icon;
  document.getElementById('fbLabel').textContent = label;
  document.getElementById('fbSub').textContent   = sub || '';
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 2500);
}
function toast(msg, tipo = '') {
  const el = document.getElementById('toast');
  el.textContent = msg; el.className = 'toast show ' + tipo;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3000);
}

// ── Foto QR (fallback cuando cámara está bloqueada) ───────
function procesarFotoQR(input) {
  if (!input.files || !input.files[0]) return;
  var file = input.files[0];
  var reader = new FileReader();
  reader.onload = function(e) {
    var img = new Image();
    img.onload = function() {
      var canvas = document.createElement('canvas');
      canvas.width  = img.width;
      canvas.height = img.height;
      var ctx = canvas.getContext('2d');
      ctx.drawImage(img, 0, 0);
      var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
      // Cargar jsQR si no está cargado
      function intentarDecodificar() {
        if (typeof jsQR !== 'undefined') {
          var code = jsQR(imageData.data, imageData.width, imageData.height, { inversionAttempts: 'dontInvert' });
          if (code) {
            loadPieza(code.data);
          } else {
            toast('No se detectó QR en la foto. Intenta de nuevo.', 'error');
          }
        } else {
          var s = document.createElement('script');
          s.src = '../lib/jsqr.min.js';
          s.onload = function() { intentarDecodificar(); };
          document.head.appendChild(s);
        }
      }
      intentarDecodificar();
    };
    img.src = e.target.result;
  };
  reader.readAsDataURL(file);
  input.value = '';
}

// ── Arranque automático ────────────────────────────────────
window.addEventListener('load', function() { iniciarScanner(); });
</script>
</body>
</html>