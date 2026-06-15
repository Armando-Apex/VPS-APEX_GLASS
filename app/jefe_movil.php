<?php

require_once __DIR__ . '/../api/config.php';

require_once __DIR__ . '/../api/permisos.php';

requirePermiso('ver_estaciones');

$user = [

    'nombre'   => $_SESSION['user_name'] ?? '',

    'rol'      => $_SESSION['user_rol']  ?? '',

    'estacion' => $_SESSION['user_estacion'] ?? '',

];

session_write_close();

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<title>APEX GLASS &#8212; Producci&#243;n</title>

<style>

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {

  --bg:      #f0f4f8;

  --white:   #ffffff;

  --border:  #e2e8f0;

  --text:    #1e293b;

  --muted:   #64748b;

  --accent:  #f5a623;

  --green:   #16a34a;

  --red:     #dc2626;

  --yellow:  #d97706;

  --blue:    #2563eb;

  --purple:  #7c3aed;



  --c-pendiente: #64748b;

  --c-cortado:   #d97706;

  --c-canteado:  #0891b2;

  --c-trazo:     #7c3aed;

  --c-taladro:   #9333ea;

  --c-templado:  #dc2626;

  --c-terminado: #16a34a;

  --c-entregado: #15803d;

}

body {

  background: var(--bg);

  color: var(--text);

  font-family: -apple-system, 'Helvetica Neue', sans-serif;

  min-height: 100dvh;

  padding-bottom: 80px;

}



/* &#9472;&#9472; Header &#9472;&#9472; */

.header {

  background: #1a1a2e;

  color: white;

  padding: 12px 16px;

  display: flex;

  align-items: center;

  justify-content: space-between;

  position: sticky;

  top: 0;

  z-index: 50;

}

.header-logo {

  font-size: 16px;

  font-weight: 800;

  letter-spacing: 2px;

  color: var(--accent);

}

.header-user {

  font-size: 11px;

  color: rgba(255,255,255,.5);

  margin-top: 1px;

}

.btn-scan {

  background: var(--accent);

  color: #000;

  border: none;

  padding: 8px 14px;

  border-radius: 8px;

  font-size: 13px;

  font-weight: 800;

  text-decoration: none;

  display: inline-block;

}

.btn-scan-wrap { display: flex; gap: 8px; align-items: center; }
/* Campana */
.notif-wrap { position: relative; }
.notif-btn  { background: none; border: none; cursor: pointer; font-size: 20px; padding: 4px; position: relative; line-height: 1; }
.notif-badge { position: absolute; top: -2px; right: -2px; background: var(--red); color: white; font-size: 10px; font-weight: 700; min-width: 16px; height: 16px; border-radius: 99px; display: none; align-items: center; justify-content: center; padding: 0 4px; }
.notif-badge.show { display: flex; }
.notif-panel { display: none; position: absolute; top: calc(100% + 8px); right: 0; width: 300px; background: var(--white); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,.15); z-index: 200; overflow: hidden; }
.notif-panel.open { display: block; }
.notif-panel-head { padding: 12px 14px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.notif-panel-titulo { font-size: 13px; font-weight: 700; color: var(--text); }
.notif-btn-leer-todas { font-size: 11px; color: var(--muted); background: none; border: none; cursor: pointer; }
.notif-lista { max-height: 320px; overflow-y: auto; }
.notif-item { padding: 11px 14px; border-bottom: 1px solid var(--border); cursor: pointer; display: flex; gap: 8px; }
.notif-item:hover { background: #f8fafc; }
.notif-item.no-leida { background: #eff6ff; }
.notif-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--blue); flex-shrink: 0; margin-top: 4px; }
.notif-dot.leida { background: transparent; }
.notif-item-titulo { font-size: 13px; font-weight: 600; color: var(--text); }
.notif-item-msg { font-size: 11px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }
.notif-item-tiempo { font-size: 10px; color: #94a3b8; margin-top: 2px; }
.notif-empty { padding: 24px; text-align: center; color: var(--muted); font-size: 13px; }

.btn-back {

  background: rgba(255,255,255,.1);

  color: rgba(255,255,255,.7);

  border: none;

  padding: 8px 12px;

  border-radius: 8px;

  font-size: 12px;

  text-decoration: none;

}



/* &#9472;&#9472; Timestamp &#9472;&#9472; */

.ts-bar {

  background: #1a1a2e;

  padding: 4px 16px 8px;

  display: flex;

  align-items: center;

  justify-content: space-between;

}

.ts-text { font-size: 11px; color: rgba(255,255,255,.35); }

.live-dot {

  width: 6px; height: 6px;

  border-radius: 50%;

  background: #22c55e;

  box-shadow: 0 0 5px #22c55e;

  animation: pulse 2s infinite;

  display: inline-block;

  margin-right: 5px;

}

@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }



/* &#9472;&#9472; Secci&#243;n &#9472;&#9472; */

.section { padding: 14px 14px 0; }

.section-title {

  font-size: 11px;

  font-weight: 700;

  letter-spacing: 1.5px;

  text-transform: uppercase;

  color: var(--muted);

  margin-bottom: 10px;

}



/* &#9472;&#9472; Alertas &#9472;&#9472; */

.alerta-card {

  background: var(--white);

  border-radius: 12px;

  border-left: 4px solid var(--red);

  padding: 12px 14px;

  margin-bottom: 8px;

  display: flex;

  align-items: center;

  justify-content: space-between;

}

.alerta-card.amarilla { border-left-color: var(--yellow); }

.alerta-folio { font-size: 16px; font-weight: 800; color: var(--text); }

.alerta-cliente { font-size: 12px; color: var(--muted); margin-top: 1px; }

.alerta-badge {

  font-size: 11px;

  font-weight: 700;

  padding: 4px 10px;

  border-radius: 20px;

  white-space: nowrap;

}

.badge-rojo    { background: #fee2e2; color: var(--red); }

.badge-amarillo{ background: #fef3c7; color: var(--yellow); }

.alerta-pct {

  font-size: 22px;

  font-weight: 800;

  color: var(--text);

  text-align: right;

}

.alerta-pct span { font-size: 11px; color: var(--muted); display: block; font-weight: 400; }



/* &#9472;&#9472; Cards de estaci&#243;n &#9472;&#9472; */

.estacion-card {

  background: var(--white);

  border-radius: 14px;

  overflow: hidden;

  margin-bottom: 10px;

  box-shadow: 0 1px 4px rgba(0,0,0,.06);

}

.est-head {

  padding: 12px 14px;

  display: flex;

  align-items: center;

  justify-content: space-between;

  border-bottom: 1px solid var(--border);

}

.est-nombre {

  font-size: 15px;

  font-weight: 800;

  display: flex;

  align-items: center;

  gap: 8px;

}

.est-count {

  font-size: 28px;

  font-weight: 800;

  line-height: 1;

}

.est-sub {

  font-size: 10px;

  color: var(--muted);

  font-weight: 600;

  letter-spacing: .5px;

  text-transform: uppercase;

  margin-top: 1px;

}



/* Filas de orden dentro de la estaci&#243;n */

.orden-fila {

  padding: 10px 14px;

  border-bottom: 1px solid #f8fafc;

  display: flex;

  align-items: center;

  justify-content: space-between;

  text-decoration: none;

  color: inherit;

}

.orden-fila:last-child { border-bottom: none; }

.orden-fila:active { background: #f8fafc; }

.of-folio { font-size: 15px; font-weight: 800; color: var(--blue); }

.of-cliente { font-size: 11px; color: var(--muted); margin-top: 1px; }

.of-right { text-align: right; flex-shrink: 0; }

.of-piezas { font-size: 22px; font-weight: 800; }

.of-fecha { font-size: 10px; font-weight: 700; margin-top: 1px; }

.fecha-ok      { color: var(--green); }

.fecha-alerta  { color: var(--yellow); }

.fecha-urgente { color: var(--red); }



.est-vacia {

  padding: 14px;

  font-size: 12px;

  color: var(--muted);

  text-align: center;

}



/* &#9472;&#9472; Resumen global &#9472;&#9472; */

.resumen-grid {

  display: grid;

  grid-template-columns: repeat(3, 1fr);

  gap: 8px;

  margin-bottom: 4px;

}

.res-card {

  background: var(--white);

  border-radius: 12px;

  padding: 12px 10px;

  text-align: center;

  box-shadow: 0 1px 4px rgba(0,0,0,.06);

}

.res-num {

  font-size: 28px;

  font-weight: 800;

  line-height: 1;

}

.res-label {

  font-size: 10px;

  font-weight: 700;

  letter-spacing: .5px;

  text-transform: uppercase;

  color: var(--muted);

  margin-top: 3px;

}



/* &#9472;&#9472; Barra de navegaci&#243;n inferior &#9472;&#9472; */

.bottom-nav {

  position: fixed;

  bottom: 0; left: 0; right: 0;

  background: var(--white);

  border-top: 1px solid var(--border);

  display: flex;

  padding: 8px 0 12px;

  z-index: 50;

}

.nav-btn {

  flex: 1;

  display: flex;

  flex-direction: column;

  align-items: center;

  gap: 3px;

  border: none;

  background: none;

  cursor: pointer;

  text-decoration: none;

  color: var(--muted);

  font-size: 10px;

  font-weight: 700;

  letter-spacing: .5px;

  text-transform: uppercase;

  -webkit-tap-highlight-color: transparent;

}

.nav-btn.active { color: var(--accent); }

.nav-icon { font-size: 22px; line-height: 1; }



/* &#9472;&#9472; Loading &#9472;&#9472; */

.loading {

  text-align: center;

  padding: 60px 20px;

  color: var(--muted);

  font-size: 14px;

}

.spin {

  width: 20px; height: 20px;

  border: 2px solid var(--border);

  border-top-color: var(--accent);

  border-radius: 50%;

  animation: sp .7s linear infinite;

  display: inline-block;

  margin-bottom: 10px;

}

@keyframes sp { to { transform: rotate(360deg); } }

</style>

</head>

<body>



<div class="header">

  <div>

    <div class="header-logo">&#11041; APEX GLASS</div>

    <div class="header-user"><?= htmlspecialchars($user['nombre']) ?></div>

  </div>

  <div class="btn-scan-wrap">

    <?php if (in_array($user['rol'], ['jefe_piso','director','dir_admin'])): ?>

    <a href="operador.php" class="btn-scan">&#128247; Escanear</a>
    <a href="../api/logout.php?redirect=login.php" class="btn-logout">Salir</a>
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

    <?php endif; ?>

    <a href="jefe_movil.php" class="btn-back">&#8592; Inicio</a>

  </div>

</div>



<div class="ts-bar">

  <div class="ts-text"><span class="live-dot"></span>EN VIVO &#8212; <span id="tsText">cargando&#8230;</span></div>

  <div class="ts-text" id="clock">--:--</div>

</div>



<!-- Contenido din&#225;mico -->

<div id="contenido">

  <div class="loading"><div class="spin"></div><br>Cargando producci&#243;n&#8230;</div>

</div>



<!-- Barra inferior -->

<div class="bottom-nav">

  <button class="nav-btn active" id="tab-resumen" onclick="setTab('resumen')">

    <span class="nav-icon">&#128202;</span>Resumen

  </button>

  <button class="nav-btn" id="tab-alertas" onclick="setTab('alertas')">

    <span class="nav-icon">&#128680;</span>Alertas

  </button>

  <button class="nav-btn" id="tab-estaciones" onclick="setTab('estaciones')">

    <span class="nav-icon">&#11041;</span>Estaciones

  </button>

  <button class="nav-btn" id="tab-ordenes" onclick="setTab('ordenes')">

    <span class="nav-icon">&#128203;</span>&#211;rdenes

  </button>

</div>



<script>

const API = '../api/';

let datos = null;

let tabActual = 'resumen';



// Reloj

function tickClock() {

  const n = new Date();

  document.getElementById('clock').textContent =

    String(n.getHours()).padStart(2,'0') + ':' + String(n.getMinutes()).padStart(2,'0');

}

setInterval(tickClock, 1000); tickClock();



// Tabs

function setTab(tab) {

  tabActual = tab;

  document.querySelectorAll('.nav-btn').forEach(b => b.classList.remove('active'));

  document.getElementById('tab-' + tab).classList.add('active');

  if (datos) renderTab();

}



// Helpers

function diasPara(fecha) {

  if (!fecha) return 99;

  const f = fecha.includes('T') || (fecha.includes(' ') && fecha.length > 10)

    ? new Date(fecha)

    : new Date(fecha + 'T12:00:00');

  return Math.ceil((f - new Date()) / 86400000);

}

function clsFecha(dias) {

  return dias <= 0 ? 'fecha-urgente' : dias <= 2 ? 'fecha-alerta' : 'fecha-ok';

}

function labelFecha(fecha, dias) {

  if (!fecha) return '&#8212;';

  const f = new Date(fecha + 'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short'});

  if (dias <= 0)  return '&#128308; ' + f;

  if (dias <= 2)  return '&#128993; ' + f;

  return '&#128994; ' + f;

}



function agruparPorOrden(piezas) {

  const map = {};

  piezas.forEach(p => {

    if (!map[p.folio]) map[p.folio] = {

      folio: p.folio, cliente: p.cliente_nombre,

      fecha: p.fecha_entrega, avance: p.avance_pct, cnt: 0

    };

    map[p.folio].cnt++;

  });

  return Object.values(map).sort((a,b) => diasPara(a.fecha) - diasPara(b.fecha));

}



// Cargar datos

async function cargar() {

  try {

    const r = await fetch(API + 'estaciones.php?t=' + Date.now());

    const d = await r.json();

    if (d.error) return;

    datos = d;



    // Tambi&#233;n cargar dashboard para resumen de &#243;rdenes

    const r2 = await fetch(API + 'dashboard.php?t=' + Date.now());

    datos.ordenes = (await r2.json()).ordenes || [];



    renderTab();

    document.getElementById('tsText').textContent =

      new Date().toLocaleTimeString('es-MX', {hour:'2-digit',minute:'2-digit',second:'2-digit'});

  } catch(e) { console.error(e); }

}



function renderTab() {

  if (!datos) return;

  const c = document.getElementById('contenido');

  if (tabActual === 'resumen')    c.innerHTML = renderResumen();

  if (tabActual === 'alertas')    c.innerHTML = renderAlertas();

  if (tabActual === 'estaciones') c.innerHTML = renderEstaciones();

  if (tabActual === 'ordenes')    c.innerHTML = renderOrdenes();

}



// &#9472;&#9472; RESUMEN &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;

function renderResumen() {

  const t = datos.totales || {};

  const ordenes = datos.ordenes || [];

  const urgentes  = ordenes.filter(o => diasPara(o.fecha_entrega) <= 0 && parseInt(o.avance_pct||0) < 100).length;

  const alertas   = ordenes.filter(o => { const d = diasPara(o.fecha_entrega); return d > 0 && d <= 2 && parseInt(o.avance_pct||0) < 100; }).length;

  const enProceso = ordenes.filter(o => parseInt(o.avance_pct) > 0 && parseInt(o.avance_pct) < 100).length;



  return `

    ${urgentes > 0 ? `

    <div class="section">

      <div style="background:#fee2e2;border-radius:12px;padding:12px 14px;display:flex;align-items:center;gap:10px;margin-bottom:0">

        <span style="font-size:24px">&#128308;</span>

        <div>

          <div style="font-size:14px;font-weight:800;color:#dc2626">${urgentes} orden${urgentes>1?'es':''} VENCIDA${urgentes>1?'S':''}</div>

          <div style="font-size:12px;color:#b91c1c">Fecha de entrega superada</div>

        </div>

      </div>

    </div>` : ''}



    <div class="section">

      <div class="section-title">Piezas en producci&#243;n</div>

      <div class="resumen-grid">

        <div class="res-card">

          <div class="res-num" style="color:var(--c-pendiente)">${t.pendiente||0}</div>

          <div class="res-label">&#9203; Pendiente</div>

        </div>

        <div class="res-card">

          <div class="res-num" style="color:var(--c-cortado)">${t.cortado||0}</div>

          <div class="res-label">&#9986;&#65039; Corte</div>

        </div>

        <div class="res-card">

          <div class="res-num" style="color:var(--c-canteado)">${t.canteado||0}</div>

          <div class="res-label">&#128297; Canteado</div>

        </div>

        <div class="res-card">

          <div class="res-num" style="color:var(--c-trazo)">${(parseInt(t.trazo||0)+parseInt(t.taladro||0))}</div>

          <div class="res-label">&#9999;&#65039; Trazo/Tal.</div>

        </div>

        <div class="res-card">

          <div class="res-num" style="color:var(--c-templado)">${t.templado||0}</div>

          <div class="res-label">&#128293; Horno</div>

        </div>

        <div class="res-card">

          <div class="res-num" style="color:var(--c-terminado)">${t.terminado||0}</div>

          <div class="res-label">&#128230; Terminado</div>

        </div>

      </div>

    </div>



    <div class="section">

      <div class="section-title">&#211;rdenes activas</div>

      <div class="resumen-grid">

        <div class="res-card">

          <div class="res-num" style="color:var(--red)">${urgentes}</div>

          <div class="res-label">&#128308; Vencidas</div>

        </div>

        <div class="res-card">

          <div class="res-num" style="color:var(--yellow)">${alertas}</div>

          <div class="res-label">&#128993; Urgentes</div>

        </div>

        <div class="res-card">

          <div class="res-num" style="color:var(--blue)">${enProceso}</div>

          <div class="res-label">&#9881;&#65039; En proceso</div>

        </div>

      </div>

    </div>`;

}



// &#9472;&#9472; ALERTAS &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;

function renderAlertas() {

  const ordenes = datos.ordenes || [];

  const urgentes = ordenes.filter(o => diasPara(o.fecha_entrega) <= 0 && parseInt(o.avance_pct||0) < 100);

  const alertas  = ordenes.filter(o => { const d = diasPara(o.fecha_entrega); return d > 0 && d <= 2 && parseInt(o.avance_pct||0) < 100; });



  if (!urgentes.length && !alertas.length) {

    return `<div class="section">

      <div style="text-align:center;padding:60px 20px;color:var(--muted)">

        <div style="font-size:48px;margin-bottom:10px">&#9989;</div>

        <div style="font-size:14px">Sin alertas &#8212; todas las &#243;rdenes est&#225;n a tiempo</div>

      </div>

    </div>`;

  }



  let html = '<div class="section">';

  if (urgentes.length) {

    html += `<div class="section-title">&#128308; Vencidas (${urgentes.length})</div>`;

    urgentes.forEach(o => {

      const dias = diasPara(o.fecha_entrega);

      const pct  = parseInt(o.avance_pct||0);

      const fstr = o.fecha_entrega ? new Date(o.fecha_entrega+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short'}) : '&#8212;';

      html += `<a href="orden.php?folio=${encodeURIComponent(o.folio)}" class="alerta-card" style="text-decoration:none">

        <div>

          <div class="alerta-folio">${o.folio}</div>

          <div class="alerta-cliente">${o.cliente_nombre||'&#8212;'}</div>

          <div style="margin-top:6px"><span class="alerta-badge badge-rojo">Venci&#243; ${fstr}</span></div>

        </div>

        <div class="alerta-pct">${pct}%<span>avance</span></div>

      </a>`;

    });

  }

  if (alertas.length) {

    html += `<div class="section-title" style="margin-top:14px">&#128993; Urgentes &#8212; menos de 2 d&#237;as (${alertas.length})</div>`;

    alertas.forEach(o => {

      const dias = diasPara(o.fecha_entrega);

      const pct  = parseInt(o.avance_pct||0);

      const fstr = o.fecha_entrega ? new Date(o.fecha_entrega+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short'}) : '&#8212;';

      html += `<a href="orden.php?folio=${encodeURIComponent(o.folio)}" class="alerta-card amarilla" style="text-decoration:none">

        <div>

          <div class="alerta-folio">${o.folio}</div>

          <div class="alerta-cliente">${o.cliente_nombre||'&#8212;'}</div>

          <div style="margin-top:6px"><span class="alerta-badge badge-amarillo">${dias === 1 ? 'Ma&#241;ana' : 'En 2 d&#237;as'} &#8212; ${fstr}</span></div>

        </div>

        <div class="alerta-pct">${pct}%<span>avance</span></div>

      </a>`;

    });

  }

  html += '</div>';

  return html;

}



// &#9472;&#9472; ESTACIONES &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;

function renderEstaciones() {

  const COLS = [

    { key:'pendiente', label:'Pendiente',    icon:'&#9203;', color:'var(--c-pendiente)', keys:['pendiente'] },

    { key:'cortado',   label:'Corte',        icon:'&#9986;&#65039;',  color:'var(--c-cortado)',   keys:['cortado'] },

    { key:'canteado',  label:'Canteado',     icon:'&#128297;', color:'var(--c-canteado)',  keys:['canteado'] },

    { key:'trazo',     label:'Trazo / Tal.', icon:'&#9999;&#65039;', color:'var(--c-trazo)',     keys:['trazo','taladro'] },

    { key:'templado',  label:'Horno',        icon:'&#128293;', color:'var(--c-templado)',  keys:['templado'] },

    { key:'terminado', label:'Terminado',    icon:'&#128230;', color:'var(--c-terminado)', keys:['terminado'] },

  ];



  let html = '<div class="section">';

  COLS.forEach(col => {

    const piezasCol = datos.piezas.filter(p => col.keys.includes(p.estatus));

    const total     = piezasCol.length;

    const ordenes   = agruparPorOrden(piezasCol);



    const filas = ordenes.length

      ? ordenes.map(o => {

          const dias = diasPara(o.fecha);

          return `<a href="orden.php?folio=${encodeURIComponent(o.folio)}" class="orden-fila">

            <div>

              <div class="of-folio">${o.folio}</div>

              <div class="of-cliente">${(o.cliente||'&#8212;').split(' ').slice(0,3).join(' ')}</div>

            </div>

            <div class="of-right">

              <div class="of-piezas" style="color:${col.color}">${o.cnt}</div>

              <div class="of-fecha ${clsFecha(dias)}">${labelFecha(o.fecha, dias)}</div>

            </div>

          </a>`;

        }).join('')

      : `<div class="est-vacia">Sin piezas</div>`;



    html += `<div class="estacion-card">

      <div class="est-head">

        <div class="est-nombre" style="color:${col.color}">${col.icon} ${col.label}</div>

        <div>

          <div class="est-count" style="color:${col.color}">${total}</div>

          <div class="est-sub">piezas</div>

        </div>

      </div>

      ${filas}

    </div>`;

  });

  html += '</div>';

  return html;

}



// &#9472;&#9472; &#211;RDENES &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;

function renderOrdenes() {

  const ordenes = (datos.ordenes || []).sort((a,b) => diasPara(a.fecha_entrega) - diasPara(b.fecha_entrega));

  if (!ordenes.length) return '<div class="section"><div class="loading">Sin &#243;rdenes activas</div></div>';



  let html = '<div class="section">';

  ordenes.forEach(o => {

    const dias = diasPara(o.fecha_entrega);

    const pct  = parseInt(o.avance_pct || 0);

    const total = parseInt(o.total_piezas || 0);

    const listas = parseInt(o.piezas_listas || 0);

    // Parsear fecha correctamente independiente del formato que venga

    let fechaStr = '&#8212;';

    if (o.fecha_entrega) {

      const f = o.fecha_entrega.includes('T') || o.fecha_entrega.includes(' ')

        ? new Date(o.fecha_entrega)

        : new Date(o.fecha_entrega + 'T12:00:00');

      fechaStr = f.toLocaleDateString('es-MX', {day:'2-digit', month:'short', year:'numeric'});

    }



    // Barra de color seg&#250;n urgencia

    const borderColor = dias <= 0 ? '#dc2626' : dias <= 2 ? '#d97706' : '#e2e8f0';



    html += `<a href="orden.php?folio=${encodeURIComponent(o.folio)}"

      style="display:block;background:white;border-radius:12px;padding:14px;margin-bottom:8px;

             border-left:4px solid ${borderColor};text-decoration:none;color:inherit;

             box-shadow:0 1px 4px rgba(0,0,0,.06)">

      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">

        <div>

          <div style="font-size:17px;font-weight:800;color:var(--blue)">${o.folio}</div>

          <div style="font-size:12px;color:var(--muted);margin-top:2px">${o.cliente_nombre||'&#8212;'}</div>

          <div style="font-size:11px;color:var(--muted)">${o.asesor||'&#8212;'}</div>

        </div>

        <div style="text-align:right">

          <div style="font-size:24px;font-weight:800">${pct}%</div>

          <div style="font-size:10px;color:var(--muted)">${listas}/${total} term.</div>

        </div>

      </div>

      <div style="background:#f1f5f9;border-radius:4px;height:6px;overflow:hidden;margin-bottom:8px">

        <div style="height:100%;width:${pct}%;background:linear-gradient(90deg,#2563eb,#16a34a);border-radius:4px"></div>

      </div>

      <div style="display:flex;justify-content:space-between;align-items:center">

        <div style="font-size:11px;font-weight:700;color:${dias<=0?'#dc2626':dias<=2?'#d97706':'#16a34a'}">

          ${dias<=0?'&#128308;':dias<=2?'&#128993;':'&#128994;'} ${fechaStr}

        </div>

        <div style="font-size:11px;color:var(--muted)">

          ${o.pendientes>0?`${o.pendientes} pend. `:''}

          ${o.cortadas>0?`${o.cortadas} cort. `:''}

          ${o.canteadas>0?`${o.canteadas} cant. `:''}

          ${(parseInt(o.trazo||0)+parseInt(o.taladro||0))>0?`${parseInt(o.trazo||0)+parseInt(o.taladro||0)} traz. `:''}

          ${o.templadas>0?`${o.templadas} horn. `:''}

          ${o.terminadas>0?`${o.terminadas} term.`:''}

        </div>

      </div>

    </a>`;

  });

  html += '</div>';

  return html;

}



cargar();

setInterval(cargar, 30000);

// ── Notificaciones ─────────────────────────────────────────
let _notifData = [];

async function cargarNotificaciones() {
  try {
    const r = await fetch('../api/notificaciones.php?accion=listar&t=' + Date.now());
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
  await fetch('../api/notificaciones.php?accion=leer', {
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
  await fetch('../api/notificaciones.php?accion=leer_todas', { method: 'POST' });
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

cargarNotificaciones();
setInterval(cargarNotificaciones, 30000);

</script>

</body>

</html>