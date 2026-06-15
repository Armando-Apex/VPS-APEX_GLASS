<?php
// ============================================================
//  APEX GLASS - Dashboard Corte (móvil)
//  Archivo: /produccion/app/corte_dashboard.php
// ============================================================
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /produccion/app/login.php'); exit;
}

$estacion = $_SESSION['user_estacion'] ?? '';
$nombre   = $_SESSION['user_name'] ?? 'Operador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>APEX GLASS — Corte</title>
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
  --purple:  #a78bfa;
}
body {
  background: var(--bg); color: var(--text);
  font-family: -apple-system, 'Helvetica Neue', sans-serif;
  min-height: 100dvh; padding-bottom: 100px;
}

/* HEADER */
.header {
  background: var(--surface); border-bottom: 1px solid var(--border);
  padding: 14px 16px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 10;
}
.header-left { display: flex; align-items: center; gap: 10px; }
.station-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--accent); flex-shrink: 0; }
.header-title { font-size: 16px; font-weight: 800; }
.header-sub   { font-size: 11px; color: var(--muted); margin-top: 1px; }
.btn-logout {
  background: none; border: 1px solid var(--border);
  color: var(--muted); font-size: 12px; padding: 6px 12px;
  border-radius: 6px; cursor: pointer; text-decoration: none;
}

/* CONTENIDO */
.content { padding: 16px; }

/* TOTAL BADGE */
.total-badge {
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 14px; padding: 16px;
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 20px;
}
.total-label { font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
.total-num   { font-size: 36px; font-weight: 900; color: var(--accent); line-height: 1; }
.total-sub   { font-size: 11px; color: var(--muted); margin-top: 2px; }

/* SECCIÓN */
.section { margin-bottom: 24px; }
.section-title {
  font-size: 11px; font-weight: 700; letter-spacing: 2px;
  text-transform: uppercase; color: var(--muted);
  margin-bottom: 10px; padding-left: 2px;
}

/* TABLA CRISTAL */
.cristal-list { display: flex; flex-direction: column; gap: 8px; }
.cristal-row {
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 12px; padding: 14px 16px;
  display: flex; align-items: center; justify-content: space-between;
}
.cristal-name { font-size: 15px; font-weight: 700; }
.cristal-count {
  font-size: 22px; font-weight: 900; color: var(--accent);
  background: rgba(245,166,35,.1); border-radius: 8px;
  padding: 4px 12px; min-width: 56px; text-align: center;
}

/* TARJETAS URGENTES */
.urgente-list { display: flex; flex-direction: column; gap: 8px; }
.urgente-card {
  background: var(--surface); border: 1.5px solid rgba(255,71,87,.35);
  border-radius: 12px; padding: 14px 16px;
}
.urgente-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 8px;
}
.urgente-folio  { font-size: 17px; font-weight: 800; }
.urgente-dias   {
  font-size: 11px; font-weight: 700; color: var(--red);
  background: rgba(255,71,87,.12); border-radius: 6px;
  padding: 3px 8px;
}
.urgente-cliente { font-size: 12px; color: var(--muted); margin-bottom: 8px; }
.urgente-footer {
  display: flex; align-items: center; justify-content: space-between;
}
.urgente-piezas {
  font-size: 13px; font-weight: 700; color: var(--text);
}
.urgente-entrega { font-size: 11px; color: var(--muted); }

/* TARJETAS PEQUEÑOS */
.pequeno-list { display: flex; flex-direction: column; gap: 8px; }
.pequeno-card {
  background: var(--surface); border: 1.5px solid rgba(74,158,255,.25);
  border-radius: 12px; padding: 14px 16px;
  display: flex; align-items: center; justify-content: space-between;
}
.pequeno-info {}
.pequeno-folio   { font-size: 16px; font-weight: 800; }
.pequeno-cliente { font-size: 12px; color: var(--muted); margin-top: 2px; }
.pequeno-entrega { font-size: 11px; color: var(--muted); margin-top: 2px; }
.pequeno-count {
  font-size: 26px; font-weight: 900; color: var(--blue);
  background: rgba(74,158,255,.1); border-radius: 10px;
  padding: 6px 14px; min-width: 56px; text-align: center; flex-shrink: 0;
}

/* EMPTY STATE */
.empty {
  text-align: center; padding: 24px;
  color: var(--muted); font-size: 13px;
}
.empty-icon { font-size: 32px; margin-bottom: 8px; opacity: .4; }

/* BOTÓN ESCANEAR */
.btn-scan-wrap {
  position: fixed; bottom: 0; left: 0; right: 0;
  padding: 16px; background: var(--bg);
  border-top: 1px solid var(--border); z-index: 20;
}
.btn-scan {
  width: 100%; padding: 18px;
  background: var(--accent); color: #000;
  border: none; border-radius: 14px;
  font-size: 18px; font-weight: 900;
  cursor: pointer; -webkit-tap-highlight-color: transparent;
  display: flex; align-items: center; justify-content: center; gap: 10px;
}
.btn-scan:active { opacity: .85; transform: scale(.98); }

/* LOADING */
.loading {
  text-align: center; padding: 60px 20px;
  color: var(--muted); font-size: 14px;
}
.spin {
  display: inline-block; width: 24px; height: 24px;
  border: 3px solid var(--border); border-top-color: var(--accent);
  border-radius: 50%; animation: spin .7s linear infinite;
  margin-bottom: 12px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* REFRESH */
.refresh-row {
  display: flex; align-items: center; justify-content: flex-end;
  margin-bottom: 16px; gap: 8px;
}
.ts-label { font-size: 11px; color: var(--muted); }
.btn-refresh {
  background: none; border: 1px solid var(--border);
  color: var(--muted); font-size: 13px; padding: 5px 10px;
  border-radius: 6px; cursor: pointer;
}
.btn-refresh:active { color: var(--accent); }

/* CAMPANA */
.notif-wrap { position: relative; }
.notif-btn  { background: none; border: none; cursor: pointer; font-size: 20px; padding: 4px; position: relative; line-height: 1; }
.notif-badge { position: absolute; top: -2px; right: -2px; background: var(--red); color: white; font-size: 10px; font-weight: 700; min-width: 16px; height: 16px; border-radius: 99px; display: none; align-items: center; justify-content: center; padding: 0 4px; }
.notif-badge.show { display: flex; }
.notif-panel { display: none; position: absolute; top: calc(100% + 8px); right: 0; width: 300px; background: #18181c; border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.5); z-index: 200; overflow: hidden; }
.notif-panel.open { display: block; }
.notif-panel-head { padding: 12px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.notif-panel-titulo { font-size: 13px; font-weight: 700; }
.notif-btn-leer-todas { font-size: 11px; color: var(--muted); background: none; border: none; cursor: pointer; }
.notif-lista { max-height: 320px; overflow-y: auto; }
.notif-item { padding: 11px 14px; border-bottom: 1px solid rgba(255,255,255,.04); cursor: pointer; display: flex; gap: 8px; }
.notif-item:hover { background: rgba(255,255,255,.04); }
.notif-item.no-leida { background: rgba(245,166,35,.06); }
.notif-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); flex-shrink: 0; margin-top: 4px; }
.notif-dot.leida { background: transparent; }
.notif-item-titulo { font-size: 13px; font-weight: 600; }
.notif-item-msg { font-size: 11px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
.notif-item-tiempo { font-size: 10px; color: #475569; margin-top: 2px; }
.notif-empty { padding: 24px; text-align: center; color: var(--muted); font-size: 13px; }

/* RETRABAJO */
.ret-list { display: flex; flex-direction: column; gap: 8px; }
.ret-card {
  background: var(--surface); border: 1.5px solid rgba(245,166,35,.35);
  border-radius: 12px; padding: 14px 16px;
}
.ret-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
.ret-folio  { font-size: 17px; font-weight: 800; color: var(--accent); }
.ret-badge  { font-size: 11px; font-weight: 700; color: var(--accent); background: rgba(245,166,35,.12); border-radius: 6px; padding: 3px 8px; }
.ret-cliente { font-size: 12px; color: var(--muted); margin-bottom: 6px; }
.ret-razon   { font-size: 11px; color: var(--muted); font-style: italic; }
.ret-entrega { font-size: 11px; color: var(--muted); margin-top: 4px; }

/* DRAWER PIEZAS RETRABAJO */
.ret-card { cursor: pointer; }
.ret-card:active { opacity: .85; }
.ret-chevron { font-size: 11px; color: var(--muted); margin-top: 4px; text-align: right; }

.drawer-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.6); z-index: 100;
}
.drawer-bg.open { display: block; }
.drawer {
  position: fixed; bottom: 0; left: 0; right: 0;
  background: var(--surface); border-radius: 20px 20px 0 0;
  border-top: 1.5px solid var(--border);
  max-height: 75vh; overflow-y: auto;
  transform: translateY(100%);
  transition: transform .3s ease;
  z-index: 101;
  padding-bottom: 30px;
}
.drawer-bg.open .drawer { transform: translateY(0); }
.drawer-handle {
  width: 36px; height: 4px; background: var(--border);
  border-radius: 99px; margin: 12px auto 0;
}
.drawer-head {
  padding: 14px 18px 10px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.drawer-titulo { font-size: 15px; font-weight: 800; color: var(--accent); }
.drawer-sub    { font-size: 11px; color: var(--muted); margin-top: 2px; }
.drawer-close  {
  background: none; border: none; color: var(--muted);
  font-size: 20px; cursor: pointer; padding: 4px 8px;
  line-height: 1;
}
.pieza-row {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 18px; border-bottom: 1px solid rgba(255,255,255,.04);
}
.pieza-qr {
  font-size: 11px; font-weight: 700; color: var(--accent);
  background: rgba(245,166,35,.1); border-radius: 6px;
  padding: 3px 8px; white-space: nowrap; flex-shrink: 0;
}
.pieza-info { flex: 1; min-width: 0; }
.pieza-medidas { font-size: 14px; font-weight: 700; color: var(--text); }
.pieza-cristal { font-size: 11px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pieza-razon   { font-size: 11px; color: var(--red); margin-top: 3px; font-style: italic; }
.drawer-empty  { text-align: center; padding: 30px; color: var(--muted); font-size: 13px; }

/* SECCIONES COLAPSABLES */
.sec-card {
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 14px; margin-bottom: 12px; overflow: hidden;
}
.sec-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px; cursor: pointer; -webkit-tap-highlight-color: transparent;
  user-select: none;
}
.sec-header:active { opacity: .8; }
.sec-left  { display: flex; align-items: center; gap: 12px; }
.sec-icon  { font-size: 22px; line-height: 1; }
.sec-label { font-size: 14px; font-weight: 700; }
.sec-sub   { font-size: 11px; color: var(--muted); margin-top: 2px; }
.sec-right { display: flex; align-items: center; gap: 10px; }
.sec-count {
  font-size: 28px; font-weight: 900; line-height: 1;
  padding: 4px 14px; border-radius: 10px;
  background: rgba(255,255,255,.06);
}
.sec-count.c-accent { color: var(--accent); }
.sec-count.c-red    { color: var(--red); }
.sec-count.c-blue   { color: var(--blue); }
.sec-count.c-green  { color: var(--green); }
.sec-chevron {
  font-size: 12px; color: var(--muted);
  transition: transform .25s; display: inline-block;
}
.sec-card.open .sec-chevron { transform: rotate(180deg); }
.sec-body {
  display: none; padding: 0 12px 12px; border-top: 1px solid var(--border);
}
.sec-card.open .sec-body { display: block; padding-top: 12px; }
.sec-ok {
  text-align: center; padding: 18px;
  color: var(--muted); font-size: 13px;
}
</style>
</head>
<body>

<div class="header">
  <div class="header-left">
    <div class="station-dot"></div>
    <div>
      <div class="header-title">✂️ Corte</div>
      <div class="header-sub"><?= htmlspecialchars($nombre) ?></div>
    </div>
  </div>
  <a href="/produccion/api/logout.php" class="btn-logout">Salir</a>
  <div class="notif-wrap" id="notifWrap">
    <button class="notif-btn" onclick="toggleNotifPanel()">🔔<span class="notif-badge" id="notifBadge"></span></button>
    <div class="notif-panel" id="notifPanel">
      <div class="notif-panel-head">
        <span class="notif-panel-titulo">Notificaciones</span>
        <button class="notif-btn-leer-todas" onclick="leerTodas()">Leer todas</button>
      </div>
      <div class="notif-lista" id="notifLista"><div class="notif-empty">Cargando…</div></div>
    </div>
  </div>
</div>

<div class="content">

  <div class="refresh-row">
    <span class="ts-label" id="tsLabel">Cargando...</span>
    <button class="btn-refresh" onclick="cargar()">↻</button>
  </div>

  <div id="mainContent">
    <div class="loading">
      <div class="spin"></div><br>Cargando datos...
    </div>
  </div>

</div>

<!-- Drawer piezas retrabajo -->
<div class="drawer-bg" id="retDrawerBg" onclick="cerrarDrawerRet(event)">
  <div class="drawer" id="retDrawer">
    <div class="drawer-handle"></div>
    <div class="drawer-head">
      <div>
        <div class="drawer-titulo" id="retDrawerFolio"></div>
        <div class="drawer-sub" id="retDrawerSub"></div>
      </div>
      <button class="drawer-close" onclick="cerrarDrawerRet()">✕</button>
    </div>
    <div id="retDrawerPiezas"></div>
  </div>
</div>

<div class="btn-scan-wrap">
  <a href="/produccion/app/optimizador_corte.php" style="display:block;width:100%;padding:14px;background:var(--surface);border:1.5px solid var(--border);border-radius:14px;text-align:center;font-size:15px;font-weight:700;color:var(--text);text-decoration:none;margin-bottom:10px;">
    📐 Optimizador de corte
  </a>
  <button class="btn-scan" onclick="irEscanear()">
    📷 Escanear QR
  </button>
</div>

<script>
const API = '/produccion/api/';

function fmtFecha(f) {
  if (!f) return '—';
  const d = new Date(f + 'T00:00:00');
  return d.toLocaleDateString('es-MX', { day:'2-digit', month:'short' });
}

async function cargar() {
  try {
    const r = await fetch(API + 'corte_dashboard.php?t=' + Date.now());
    const d = await r.json();
    renderDashboard(d);
    document.getElementById('tsLabel').textContent = 'Actualizado ' + new Date().toLocaleTimeString('es-MX', {hour:'2-digit',minute:'2-digit'});
  } catch(e) {
    document.getElementById('mainContent').innerHTML =
      '<div class="empty"><div class="empty-icon">⚠️</div>Error de conexión</div>';
  }
}

function toggleSec(id) {
  document.getElementById(id).classList.toggle('open');
}

function secCard(id, icon, label, sub, count, colorCls, bodyHtml) {
  return `
    <div class="sec-card" id="${id}">
      <div class="sec-header" onclick="toggleSec('${id}')">
        <div class="sec-left">
          <div class="sec-icon">${icon}</div>
          <div>
            <div class="sec-label">${label}</div>
            <div class="sec-sub">${sub}</div>
          </div>
        </div>
        <div class="sec-right">
          <div class="sec-count ${colorCls}">${count}</div>
          <span class="sec-chevron">▼</span>
        </div>
      </div>
      <div class="sec-body">${bodyHtml}</div>
    </div>`;
}

function renderDashboard(d) {
  let html = '';

  // TOTAL GENERAL
  html += `
    <div class="total-badge">
      <div>
        <div class="total-label">Pendientes de cortar</div>
        <div class="total-sub">en todas las órdenes activas</div>
      </div>
      <div>
        <div class="total-num">${d.total_pendiente}</div>
        <div class="total-sub" style="text-align:right">piezas</div>
      </div>
    </div>`;

  // ── RETRABAJOS ──────────────────────────────────────────
  let retBody = '';
  const nRet = d.retrabajos ? d.retrabajos.length : 0;
  if (nRet === 0) {
    retBody = '<div class="sec-ok">✅ Sin retrabajos pendientes</div>';
  } else {
    d.retrabajos.forEach(r => {
      const piezasJson = escAttr(JSON.stringify(r.piezas || []));
      retBody += `
        <div class="ret-card" onclick="abrirDrawerRet('${r.folio}','${escAttr(r.cliente_nombre||'')}',${r.piezas_retrabajo},'${escAttr(r.fecha_entrega||'')}',${piezasJson})">
          <div class="ret-head">
            <div class="ret-folio">${r.folio}</div>
            <div class="ret-badge">⚠️ ${r.piezas_retrabajo} pieza(s)</div>
          </div>
          <div class="ret-cliente">${r.cliente_nombre || '—'}</div>
          ${r.razones ? `<div class="ret-razon">${r.razones}</div>` : ''}
          <div class="ret-entrega">Entrega: ${fmtFecha(r.fecha_entrega)}</div>
          <div class="ret-chevron">Ver piezas ›</div>
        </div>`;
    });
  }
  html += secCard('sec-ret', '🔄', 'Retrabajos', nRet === 0 ? 'Sin pendientes' : `${nRet} orden(es)`, nRet, nRet > 0 ? 'c-accent' : 'c-green', retBody);

  // ── POR TIPO DE VIDRIO ──────────────────────────────────
  let cristalBody = '';
  const nCristal = d.por_cristal ? d.por_cristal.length : 0;
  if (nCristal === 0) {
    cristalBody = '<div class="sec-ok">✅ Sin piezas pendientes</div>';
  } else {
    cristalBody += '<div class="cristal-list">';
    d.por_cristal.forEach(c => {
      cristalBody += `
        <div class="cristal-row">
          <div class="cristal-name">${c.cristal || '(sin tipo)'}</div>
          <div class="cristal-count">${c.total}</div>
        </div>`;
    });
    cristalBody += '</div>';
  }
  const totalPiezasCristal = d.por_cristal ? d.por_cristal.reduce((s, c) => s + parseInt(c.total), 0) : 0;
  html += secCard('sec-cristal', '🪟', 'Por tipo de vidrio', `${nCristal} tipo(s) pendientes`, totalPiezasCristal, 'c-accent', cristalBody);

  // ── URGENTES ────────────────────────────────────────────
  let urgBody = '';
  const nUrg = d.urgentes ? d.urgentes.length : 0;
  if (nUrg === 0) {
    urgBody = '<div class="sec-ok">👍 Sin pedidos atrasados</div>';
  } else {
    urgBody += '<div class="urgente-list">';
    d.urgentes.forEach(u => {
      urgBody += `
        <div class="urgente-card">
          <div class="urgente-head">
            <div class="urgente-folio">${u.folio}</div>
            <div class="urgente-dias">${u.dias_en_produccion} días</div>
          </div>
          <div class="urgente-cliente">${u.cliente_nombre || '—'}</div>
          <div class="urgente-footer">
            <div class="urgente-piezas">✂️ ${u.piezas_pendientes} piezas sin cortar</div>
            <div class="urgente-entrega">Entrega: ${fmtFecha(u.fecha_entrega)}</div>
          </div>
        </div>`;
    });
    urgBody += '</div>';
  }
  html += secCard('sec-urg', '🔴', 'Pedidos atrasados', '3 o más días en producción', nUrg, nUrg > 0 ? 'c-red' : 'c-green', urgBody);

  // ── PEQUEÑOS ────────────────────────────────────────────
  let pequBody = '';
  const nPeq = d.pequenos ? d.pequenos.length : 0;
  if (nPeq === 0) {
    pequBody = '<div class="sec-ok">— Sin pedidos pequeños pendientes</div>';
  } else {
    pequBody += '<div class="pequeno-list">';
    d.pequenos.forEach(p => {
      pequBody += `
        <div class="pequeno-card">
          <div class="pequeno-info">
            <div class="pequeno-folio">${p.folio}</div>
            <div class="pequeno-cliente">${p.cliente_nombre || '—'}</div>
            <div class="pequeno-entrega">Entrega: ${fmtFecha(p.fecha_entrega)}</div>
          </div>
          <div class="pequeno-count">${p.piezas_pendientes}</div>
        </div>`;
    });
    pequBody += '</div>';
  }
  html += secCard('sec-peq', '🔵', 'Pedidos pequeños', '1 a 3 piezas', nPeq, 'c-blue', pequBody);

  document.getElementById('mainContent').innerHTML = html;

  // Abrir automáticamente retrabajos si hay pendientes
  if (nRet > 0) document.getElementById('sec-ret').classList.add('open');
}

// ── Notificaciones ────────────────────────────────────────
let _notifData = [];

async function cargarNotificaciones() {
  try {
    const r = await fetch('/produccion/api/notificaciones.php?accion=listar&t=' + Date.now());
    const d = await r.json();
    if (!d.ok) return;
    _notifData = d.notificaciones || [];
    const badge = document.getElementById('notifBadge');
    if (d.no_leidas > 0) {
      badge.textContent = d.no_leidas > 99 ? '99+' : d.no_leidas;
      badge.classList.add('show');
    } else {
      badge.classList.remove('show');
    }
    renderNotifLista();
  } catch(e) {}
}

function renderNotifLista() {
  const lista = document.getElementById('notifLista');
  if (!_notifData.length) { lista.innerHTML = '<div class="notif-empty">Sin notificaciones</div>'; return; }
  lista.innerHTML = _notifData.map(n => {
    const leida  = n.leida == 1;
    const diff   = Math.floor((Date.now() - new Date(n.created_at)) / 1000);
    const tiempo = diff < 60 ? 'Hace un momento' : diff < 3600 ? 'Hace ' + Math.floor(diff/60) + ' min' : diff < 86400 ? 'Hace ' + Math.floor(diff/3600) + 'h' : 'Hace ' + Math.floor(diff/86400) + ' días';
    return `<div class="notif-item ${leida ? '' : 'no-leida'}" onclick="notifClick(${n.id})">
      <div class="notif-dot ${leida ? 'leida' : ''}"></div>
      <div>
        <div class="notif-item-titulo">${n.titulo}</div>
        <div class="notif-item-msg">${n.mensaje || ''}</div>
        <div class="notif-item-tiempo">${tiempo}</div>
      </div>
    </div>`;
  }).join('');
}

async function notifClick(id) {
  await fetch('/produccion/api/notificaciones.php?accion=leer', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });
  const n = _notifData.find(x => x.id == id);
  if (n) n.leida = 1;
  const noLeidas = _notifData.filter(x => !x.leida).length;
  const badge = document.getElementById('notifBadge');
  badge.textContent = noLeidas > 99 ? '99+' : noLeidas;
  noLeidas > 0 ? badge.classList.add('show') : badge.classList.remove('show');
  renderNotifLista();
}

async function leerTodas() {
  await fetch('/produccion/api/notificaciones.php?accion=leer_todas', { method: 'POST' });
  _notifData.forEach(n => n.leida = 1);
  document.getElementById('notifBadge').classList.remove('show');
  renderNotifLista();
}

function toggleNotifPanel() {
  const panel = document.getElementById('notifPanel');
  panel.classList.toggle('open');
  if (panel.classList.contains('open')) cargarNotificaciones();
}

document.addEventListener('click', e => {
  const wrap = document.getElementById('notifWrap');
  if (wrap && !wrap.contains(e.target)) document.getElementById('notifPanel').classList.remove('open');
});





function escAttr(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/'/g,'&#39;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function abrirDrawerRet(folio, cliente, total, fechaEntrega, piezas) {
  document.getElementById('retDrawerFolio').textContent = folio + ' — ' + (cliente || '—');
  document.getElementById('retDrawerSub').textContent = total + ' pieza(s) de retrabajo · Entrega: ' + fmtFecha(fechaEntrega);

  var cont = document.getElementById('retDrawerPiezas');
  if (!piezas || !piezas.length) {
    cont.innerHTML = '<div class="drawer-empty">Sin detalle de piezas</div>';
  } else {
    cont.innerHTML = piezas.map(function(p) {
      return '<div class="pieza-row">'
        + '<div class="pieza-qr">' + escAttr(p.qr_code || ('P' + p.partida + '-' + p.pieza_num + 'de' + p.pieza_total)) + '</div>'
        + '<div class="pieza-info">'
        +   '<div class="pieza-medidas">' + (p.ancho_mm || '?') + ' × ' + (p.alto_mm || '?') + ' mm</div>'
        +   '<div class="pieza-cristal">' + escAttr(p.cristal_corto || '') + '</div>'
        +   (p.razon_retrabajo ? '<div class="pieza-razon">↺ ' + escAttr(p.razon_retrabajo) + '</div>' : '')
        + '</div>'
        + '</div>';
    }).join('');
  }

  document.getElementById('retDrawerBg').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function cerrarDrawerRet(e) {
  if (e && e.target !== document.getElementById('retDrawerBg')) return;
  document.getElementById('retDrawerBg').classList.remove('open');
  document.body.style.overflow = '';
}

function irEscanear() {
  window.location.href = '/produccion/app/operador.php';
}

// Auto-refresh cada 2 minutos
cargar();
setInterval(cargar, 120000);
cargarNotificaciones();
setInterval(cargarNotificaciones, 30000);
</script>
</body>
</html>