<?php
require_once __DIR__ . '/../api/config.php';
// Pantalla SmartTV — acceso libre, sin login requerido
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
<title>APEX GLASS — Producción</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Barlow+Condensed:wght@400;600;700;800&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:      #07080b;
  --surface: #0d0e12;
  --surface2:#111318;
  --border:  #1c1d24;
  --text:    #f0f0f5;
  --muted:   #42435a;
  --accent:  #f5a623;

  --c-pendiente: #6b7280;
  --c-cnc:       #ea580c;
  --c-cortado:   #f59e0b;
  --c-canteado:  #06b6d4;
  --c-trazo:     #8b5cf6;
  --c-taladro:   #7c3aed;
  --c-templado:  #ef4444;
  --c-terminado: #22c55e;
}

html, body {
  width: 100vw;
  height: 100vh;
  overflow: hidden;
  background: var(--bg);
  color: var(--text);
  font-family: 'Barlow Condensed', sans-serif;
}

body {
  display: flex;
  flex-direction: column;
}

/* ── Header — 5.5vh ──────────────────────────────────────── */
.header {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0 2vw;
  height: 5.5vh;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  position: relative;
}
.header::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, var(--accent), transparent 50%);
}

.logo {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 2.8vh;
  letter-spacing: 0.4vw;
  color: var(--accent);
  display: flex;
  align-items: center;
  gap: 0.5vw;
  white-space: nowrap;
}
.logo-dot {
  width: 0.7vh; height: 0.7vh;
  background: var(--accent);
  border-radius: 50%;
  box-shadow: 0 0 6px var(--accent);
  flex-shrink: 0;
}

.header-center {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.6vh;
  letter-spacing: 0.5vw;
  color: var(--muted);
  position: absolute;
  left: 50%; transform: translateX(-50%);
  white-space: nowrap;
}

.header-right {
  display: flex;
  align-items: center;
  gap: 1.5vw;
}
.clock {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 3.2vh;
  letter-spacing: 0.2vw;
  color: var(--text);
  white-space: nowrap;
  min-width: 10vw;
  text-align: right;
}
.view-toggle {
  display: flex;
  background: var(--border);
  border-radius: 0.5vh;
  padding: 0.3vh;
  gap: 0.2vw;
}
.toggle-btn {
  padding: 0.4vh 1.2vw;
  border: none;
  border-radius: 0.4vh;
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.4vh;
  font-weight: 700;
  letter-spacing: 0.1vw;
  cursor: pointer;
  background: none;
  color: var(--muted);
  transition: all .2s;
  text-transform: uppercase;
  white-space: nowrap;
}
.toggle-btn.active { background: var(--accent); color: #000; }

/* ── Grid 6 columnas — altura restante ───────────────────── */
#vista-planta {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 1px;
  background: var(--border);
  overflow: hidden;
  min-height: 0; /* importante para que flex:1 funcione */
}

.estacion-col {
  background: var(--bg);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
  min-height: 0;
}
.estacion-col::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0; height: 3px;
  z-index: 1;
}
.est-pendiente::before { background: var(--c-pendiente); }
.est-cnc::before       { background: var(--c-cnc); }
.est-cortado::before   { background: var(--c-cortado); }
.est-canteado::before  { background: var(--c-canteado); }
.est-trazo::before     { background: var(--c-trazo); }
.est-taladro::before   { background: var(--c-taladro); }
.est-templado::before  { background: var(--c-templado); }
.est-terminado::before { background: var(--c-terminado); }

/* ── Header de cada estación — altura fija 14vh ─────────── */
.est-head {
  padding: 1vh 0.7vw 0.6vh;
  flex-shrink: 0;
  border-bottom: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 0.2vh;
}
.est-icono { font-size: 1.4vh; line-height: 1; }
.est-nombre {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 2.2vh;
  letter-spacing: 0.15vw;
  line-height: 1;
}
.est-stats {
  display: flex;
  align-items: baseline;
  gap: 0.3vw;
}
.est-total {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 6vh;
  line-height: 1;
}
.est-unit {
  font-size: 1vh;
  font-weight: 700;
  letter-spacing: 0.12vw;
  text-transform: uppercase;
  color: var(--muted);
  align-self: flex-end;
  padding-bottom: 0.5vh;
}
.est-sub {
  font-size: 1vh;
  font-weight: 600;
  letter-spacing: 0.08vw;
  text-transform: uppercase;
  color: var(--muted);
}

/* ── Lista scrolleable ──────────────────────────────────── */
.est-ordenes {
  flex: 1;
  overflow: hidden;
  padding: 0.4vh 0.3vw;
  min-height: 0;
  position: relative;
}
/* Contenedor interno que se desplaza */
.est-ordenes-inner {
  will-change: transform;
}

.orden-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.6vh 0.4vw;
  border-radius: 0.5vh;
  margin-bottom: 0.3vh;
  background: var(--surface);
  border-left: 3px solid transparent;
  text-decoration: none;
  transition: background .12s;
  gap: 0.3vw;
}
.orden-row:hover  { background: var(--surface2); }
.orden-row.urgente     { border-left-color: #ef4444; background: rgba(239,68,68,.07); }
.orden-row.alerta      { border-left-color: #f59e0b; }
.orden-row.ok          { border-left-color: var(--border); }
.orden-row.casi-completo { background: rgba(255,255,255,.04); }

.row-left { min-width: 0; flex: 1; }
.row-folio {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.8vh;
  letter-spacing: 0.1vw;
  line-height: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.row-info {
  font-size: 0.95vh;
  color: var(--muted);
  font-weight: 600;
  letter-spacing: 0.04vw;
  margin-top: 0.2vh;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.cristal-row {
  font-size: 0.85vh;
  color: var(--muted);
  margin-top: 0.15vh;
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.row-right { text-align: right; flex-shrink: 0; }
.row-cnt {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 2.6vh;
  line-height: 1;
}
.row-dias {
  font-size: 0.85vh;
  font-weight: 700;
  letter-spacing: 0.08vw;
  margin-top: 0.1vh;
  white-space: nowrap;
}

.empty-col {
  padding: 2vh 1vw;
  font-size: 1.1vh;
  color: var(--muted);
  font-weight: 700;
  letter-spacing: 0.15vw;
  text-transform: uppercase;
  text-align: center;
}

/* Colores por estación */
.est-pendiente .est-nombre,
.est-pendiente .est-total { color: var(--c-pendiente); }
.est-pendiente .row-cnt   { color: var(--c-pendiente); }

.est-cnc .est-nombre,
.est-cnc .est-total       { color: var(--c-cnc); }
.est-cnc .row-cnt         { color: var(--c-cnc); }

.est-cortado .est-nombre,
.est-cortado .est-total   { color: var(--c-cortado); }
.est-cortado .row-cnt     { color: var(--c-cortado); }

.est-canteado .est-nombre,
.est-canteado .est-total  { color: var(--c-canteado); }
.est-canteado .row-cnt    { color: var(--c-canteado); }

.est-trazo .est-nombre,
.est-trazo .est-total     { color: var(--c-trazo); }
.est-trazo .row-cnt       { color: var(--c-trazo); }

.est-taladro .est-nombre,
.est-taladro .est-total   { color: var(--c-taladro); }
.est-taladro .row-cnt     { color: var(--c-taladro); }

.est-templado .est-nombre,
.est-templado .est-total  { color: var(--c-templado); }
.est-templado .row-cnt    { color: var(--c-templado); }

.est-terminado .est-nombre,
.est-terminado .est-total { color: var(--c-terminado); }
.est-terminado .row-cnt   { color: var(--c-terminado); }

/* ── Vista Admin ─────────────────────────────────────────── */
#vista-admin {
  flex: 1;
  overflow-y: auto;
  padding: 2vh 2vw;
  display: none;
  min-height: 0;
}
.admin-section { margin-bottom: 2.5vh; }
.admin-section-header {
  display: flex;
  align-items: center;
  gap: 1vw;
  margin-bottom: 1vh;
  padding-bottom: 1vh;
  border-bottom: 1px solid var(--border);
}
.section-dot { width: 1vh; height: 1vh; border-radius: 50%; flex-shrink: 0; }
.section-titulo {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 2.2vh;
  letter-spacing: 0.2vw;
}
.section-total { font-size: 1.3vh; color: var(--muted); margin-left: auto; font-weight: 700; }
.admin-table-wrap {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 1vh;
  overflow: hidden;
}
.admin-table { width: 100%; border-collapse: collapse; }
.admin-table th {
  padding: 1vh 1.5vw;
  font-size: 1.1vh; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.1vw;
  color: var(--muted); text-align: left;
  background: rgba(255,255,255,.02);
  border-bottom: 1px solid var(--border);
}
.admin-table td {
  padding: 1vh 1.5vw;
  font-size: 1.4vh; color: var(--text);
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.admin-table tr:last-child td { border-bottom: none; }
.admin-table tr:hover td { background: rgba(255,255,255,.02); }
.folio-link {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 1.8vh; letter-spacing: 0.1vw;
  color: var(--accent); text-decoration: none;
}
.pct-bar-wrap { display: flex; align-items: center; gap: 0.5vw; }
.pct-bar { flex: 1; height: 0.5vh; background: var(--border); border-radius: 0.3vh; overflow: hidden; }
.pct-fill { height: 100%; background: linear-gradient(90deg, #2563eb, #16a34a); border-radius: 0.3vh; }
.pct-label { font-size: 1.1vh; color: var(--muted); width: 3vw; text-align: right; font-weight: 700; }
.fecha-urgente { color: #ef4444; font-weight: 700; }
.fecha-alerta  { color: #f59e0b; font-weight: 700; }
.fecha-ok      { color: #22c55e; }
.mini-pill {
  display: inline-block; font-size: 1vh; font-weight: 700;
  padding: 0.2vh 0.5vw; border-radius: 0.5vh; margin: 1px;
}

/* ── Footer — 3.5vh ─────────────────────────────────────── */
.footer-bar {
  background: var(--surface);
  border-top: 1px solid var(--border);
  padding: 0 2vw;
  height: 3.5vh;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
}
.footer-ts {
  font-size: 1.1vh;
  color: var(--muted);
  letter-spacing: 0.15vw;
  text-transform: uppercase;
  font-weight: 700;
}
.footer-live { display: flex; align-items: center; gap: 0.5vw; }
.footer-dot {
  width: 0.7vh; height: 0.7vh; border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 6px #22c55e;
  animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.3} }

/* ── Popup nueva orden ──────────────────────────────────── */
#popup-nueva-orden {
  display: none;
  position: fixed;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%) scale(0.85);
  z-index: 9999;
  background: #0d0e12;
  border: 2px solid var(--accent);
  border-radius: 2vh;
  padding: 4vh 6vw;
  text-align: center;
  box-shadow: 0 0 60px rgba(245,166,35,0.4), 0 0 120px rgba(245,166,35,0.15);
  min-width: 28vw;
  opacity: 0;
  transition: opacity 0.3s ease, transform 0.3s ease;
}
#popup-nueva-orden.visible {
  opacity: 1;
  transform: translate(-50%, -50%) scale(1);
}
.popup-etiqueta {
  font-size: 2vh; font-weight: 700; letter-spacing: 0.4vw;
  text-transform: uppercase; color: var(--accent); margin-bottom: 1.5vh;
  font-family: 'Barlow Condensed', sans-serif;
}
.popup-folio {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 12vh; letter-spacing: 0.3vw; color: var(--accent); line-height: 1;
}
.popup-piezas {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 3vh; font-weight: 600; color: var(--muted); margin-top: 1vh; letter-spacing: 0.15vw;
}
.popup-cliente {
  font-size: 1.8vh; color: #6b7280; margin-top: 0.5vh; letter-spacing: 0.1vw;
  font-family: 'Barlow Condensed', sans-serif;
}
.popup-barra-wrap { margin-top: 2.5vh; height: 0.5vh; background: var(--border); border-radius: 1vh; overflow: hidden; }
.popup-barra { height: 100%; background: var(--accent); border-radius: 1vh; width: 100%; transform-origin: left; }

/* ── Popup orden terminada ──────────────────────────────── */
#popup-orden-terminada {
  display: none;
  position: fixed;
  top: 50%; left: 50%;
  transform: translate(-50%, -50%) scale(0.85);
  z-index: 9998;
  background: #0d0e12;
  border: 2px solid #22c55e;
  border-radius: 2vh;
  padding: 4vh 6vw;
  text-align: center;
  box-shadow: 0 0 60px rgba(34,197,94,0.4), 0 0 120px rgba(34,197,94,0.15);
  min-width: 28vw;
  opacity: 0;
  transition: opacity 0.3s ease, transform 0.3s ease;
}
#popup-orden-terminada.visible {
  opacity: 1;
  transform: translate(-50%, -50%) scale(1);
}
.popup-terminada-etiqueta {
  font-size: 2vh; font-weight: 700; letter-spacing: 0.4vw;
  text-transform: uppercase; color: #22c55e; margin-bottom: 1.5vh;
  font-family: 'Barlow Condensed', sans-serif;
}
.popup-terminada-folio {
  font-family: 'Bebas Neue', sans-serif;
  font-size: 12vh; letter-spacing: 0.3vw; color: #22c55e; line-height: 1;
}
.popup-terminada-piezas {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 3vh; font-weight: 600; color: var(--muted); margin-top: 1vh; letter-spacing: 0.15vw;
}
.popup-terminada-cliente {
  font-size: 1.8vh; color: #6b7280; margin-top: 0.5vh; letter-spacing: 0.1vw;
  font-family: 'Barlow Condensed', sans-serif;
}
.popup-terminada-barra-wrap { margin-top: 2.5vh; height: 0.5vh; background: var(--border); border-radius: 1vh; overflow: hidden; }
.popup-terminada-barra { height: 100%; background: #22c55e; border-radius: 1vh; width: 100%; transform-origin: left; }

/* ── Overlay Comunicados ─────────────────────────────────── */
#overlay-comunicado {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 9000;
  background: #000;
  align-items: center;
  justify-content: center;
}
#overlay-comunicado.visible {
  display: flex;
}
#com-imagen {
  max-width: 100vw;
  max-height: calc(100vh - 3.5vh);
  object-fit: contain;
  display: block;
}
.com-barra-wrap {
  position: fixed;
  bottom: 0; left: 0; right: 0;
  height: 3.5vh;
  background: rgba(0,0,0,.7);
  display: flex;
  align-items: center;
  padding: 0 2vw;
  gap: 1vw;
  z-index: 9001;
}
.com-barra-label {
  font-family: 'Barlow Condensed', sans-serif;
  font-size: 1.4vh; font-weight: 700;
  color: var(--accent);
  letter-spacing: .15vw;
  text-transform: uppercase;
  white-space: nowrap; flex-shrink: 0;
}
.com-barra-track {
  flex: 1; height: .5vh;
  background: rgba(255,255,255,.15);
  border-radius: 1vh; overflow: hidden;
}
.com-barra-fill {
  height: 100%;
  background: var(--accent);
  border-radius: 1vh;
  transform-origin: left;
  width: 100%;
}

/* Loading */
.loading-wrap {
  flex: 1; display: flex; align-items: center; justify-content: center;
  color: var(--muted); font-size: 1.6vh; letter-spacing: 0.3vw; text-transform: uppercase;
  gap: 1vw; grid-column: 1/-1;
}
.spin {
  width: 2.2vh; height: 2.2vh;
  border: 2px solid var(--border); border-top-color: var(--accent);
  border-radius: 50%; animation: spinA .7s linear infinite;
}
@keyframes spinA { to{transform:rotate(360deg)} }
</style>
</head>
<body>

<div class="header">
  <div class="logo">
    <div class="logo-dot"></div>
    APEX GLASS
  </div>
  <div class="header-center">TABLERO DE PRODUCCIÓN</div>
  <div class="header-right">
    <div class="view-toggle">
      <button class="toggle-btn active" id="btnPlanta" onclick="setVista('planta')">🏭 PLANTA</button>
      <button class="toggle-btn"        id="btnAdmin"  onclick="setVista('admin')">📊 ADMIN</button>
    </div>
    <div class="clock" id="clock">00:00:00</div>
  </div>
</div>

<div id="vista-planta">
  <div class="loading-wrap"><div class="spin"></div> CARGANDO ESTACIONES…</div>
</div>

<div id="vista-admin"></div>

<div class="footer-bar">
  <div class="footer-ts" id="footerTs">Conectando…</div>
  <div class="footer-live">
    <div class="footer-dot"></div>
    <div class="footer-ts">EN VIVO · ACTUALIZA CADA 120s</div>
  </div>
</div>

<!-- Popup nueva orden -->
<div id="popup-nueva-orden">
  <div class="popup-etiqueta">🔔 Nuevo Pedido</div>
  <div class="popup-folio" id="popup-folio">—</div>
  <div class="popup-piezas" id="popup-piezas">— piezas</div>
  <div class="popup-cliente" id="popup-cliente"></div>
  <div class="popup-barra-wrap"><div class="popup-barra" id="popup-barra"></div></div>
</div>

<!-- Popup orden terminada -->
<div id="popup-orden-terminada">
  <div class="popup-terminada-etiqueta">✅ Orden Terminada</div>
  <div class="popup-terminada-folio" id="popup-term-folio">—</div>
  <div class="popup-terminada-piezas" id="popup-term-piezas">— piezas</div>
  <div class="popup-terminada-cliente" id="popup-term-cliente"></div>
  <div class="popup-terminada-barra-wrap"><div class="popup-terminada-barra" id="popup-term-barra"></div></div>
</div>

<script>
const API = '../api/estaciones.php';
let vista = 'planta';
let cache        = null;
let foliosVistos = null; // null = primera carga, Set() = ya inicializado
const colaPopup  = [];   // cola de órdenes nuevas a mostrar
let popupActivo  = false;

// ── Popup orden terminada ─────────────────────────────────
let foliosTerminados = null; // null = primera carga
const colaTerminados = [];
let popupTermActivo  = false;

function mostrarPopupTerminada(orden) {
  return new Promise(resolve => {
    const el    = document.getElementById('popup-orden-terminada');
    const barra = document.getElementById('popup-term-barra');
    const DURACION = 3000;

    document.getElementById('popup-term-folio').textContent   = orden.folio;
    document.getElementById('popup-term-piezas').textContent  = orden.piezas + (orden.piezas === 1 ? ' pieza' : ' piezas');
    document.getElementById('popup-term-cliente').textContent = orden.cliente || '';

    el.style.display = 'block';
    requestAnimationFrame(() => {
      el.classList.add('visible');
      barra.style.transition = 'none';
      barra.style.transform  = 'scaleX(1)';
      requestAnimationFrame(() => {
        barra.style.transition = 'transform ' + DURACION + 'ms linear';
        barra.style.transform  = 'scaleX(0)';
      });
    });

    setTimeout(() => {
      el.classList.remove('visible');
      setTimeout(() => { el.style.display = 'none'; resolve(); }, 300);
    }, DURACION);
  });
}

async function procesarColaTerminados() {
  if (popupTermActivo || colaTerminados.length === 0) return;
  popupTermActivo = true;
  while (colaTerminados.length > 0) {
    const orden = colaTerminados.shift();
    await mostrarPopupTerminada(orden);
    await new Promise(r => setTimeout(r, 400));
  }
  popupTermActivo = false;
}

function detectarTerminadas(piezas) {
  // Agrupar piezas terminadas por folio
  const terminadas = {};
  piezas.filter(p => p.estatus === 'terminado').forEach(p => {
    if (!terminadas[p.folio]) terminadas[p.folio] = { folio: p.folio, cliente: p.cliente_nombre, piezas: 0 };
    terminadas[p.folio].piezas++;
  });

  if (foliosTerminados === null) {
    // Primera carga — solo registrar, no mostrar
    foliosTerminados = new Set(Object.keys(terminadas));
    return;
  }

  Object.values(terminadas).forEach(orden => {
    if (!foliosTerminados.has(orden.folio)) {
      foliosTerminados.add(orden.folio);
      if (vista === 'planta') colaTerminados.push(orden);
    }
  });

  procesarColaTerminados();
}

function mostrarPopup(orden) {
  return new Promise(resolve => {
    const el    = document.getElementById('popup-nueva-orden');
    const barra = document.getElementById('popup-barra');
    const DURACION = 3000; // ms

    document.getElementById('popup-folio').textContent   = orden.folio;
    document.getElementById('popup-piezas').textContent  = orden.piezas + (orden.piezas === 1 ? ' pieza' : ' piezas');
    document.getElementById('popup-cliente').textContent = orden.cliente || '';

    // Mostrar con animación
    el.style.display = 'block';
    requestAnimationFrame(() => {
      el.classList.add('visible');
      // Barra de progreso
      barra.style.transition = 'none';
      barra.style.transform  = 'scaleX(1)';
      requestAnimationFrame(() => {
        barra.style.transition = 'transform ' + DURACION + 'ms linear';
        barra.style.transform  = 'scaleX(0)';
      });
    });

    setTimeout(() => {
      el.classList.remove('visible');
      setTimeout(() => {
        el.style.display = 'none';
        resolve();
      }, 300); // esperar animación de salida
    }, DURACION);
  });
}

async function procesarCola() {
  if (popupActivo || colaPopup.length === 0) return;
  popupActivo = true;
  while (colaPopup.length > 0) {
    const orden = colaPopup.shift();
    await mostrarPopup(orden);
    // Pequeña pausa entre popups
    await new Promise(r => setTimeout(r, 400));
  }
  popupActivo = false;
}

function detectarNuevas(piezas) {
  // Agrupar pendientes por folio
  const pendientes = {};
  piezas.filter(p => p.estatus === 'pendiente').forEach(p => {
    if (!pendientes[p.folio]) pendientes[p.folio] = { folio: p.folio, cliente: p.cliente_nombre, piezas: 0 };
    pendientes[p.folio].piezas++;
  });

  if (foliosVistos === null) {
    // Primera carga — solo registrar folios, no mostrar popup
    foliosVistos = new Set(Object.keys(pendientes));
    return;
  }

  // Detectar folios nuevos
  Object.values(pendientes).forEach(orden => {
    if (!foliosVistos.has(orden.folio)) {
      foliosVistos.add(orden.folio);
      // Solo mostrar en vista planta
      if (vista === 'planta') colaPopup.push(orden);
    }
  });

  procesarCola();
}

// Reloj
function tickClock() {
  const now = new Date();
  const p = n => String(n).padStart(2,'0');
  document.getElementById('clock').textContent =
    p(now.getHours())+':'+p(now.getMinutes())+':'+p(now.getSeconds());
}
setInterval(tickClock, 1000);
tickClock();

// Toggle vista
function setVista(v) {
  vista = v;
  document.getElementById('btnPlanta').classList.toggle('active', v==='planta');
  document.getElementById('btnAdmin').classList.toggle('active',  v==='admin');
  document.getElementById('vista-planta').style.display = v==='planta' ? 'grid'  : 'none';
  document.getElementById('vista-admin').style.display  = v==='admin'  ? 'block' : 'none';
  if (cache) render(cache);
}

// Cargar datos
async function cargar() {
  try {
    const r = await fetch(API+'?t='+Date.now());
    const d = await r.json();
    if (d.error) return;
    cache = d;
    detectarNuevas(d.piezas || []);
    detectarTerminadas(d.piezas || []);
    render(d);
    const ts = new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit',second:'2-digit'});
    document.getElementById('footerTs').textContent = 'Última actualización: '+ts;
  } catch(e){ console.error(e); }
}
function render(d) {
  if (vista==='planta') { renderPlanta(d); iniciarScroll(); }
  else { renderAdmin(d); if (scrollRAF) { cancelAnimationFrame(scrollRAF); scrollRAF = null; } }
}

// Helpers
const FESTIVOS = ['2026-01-01','2026-02-02','2026-03-16','2026-04-02','2026-04-03',
  '2026-05-01','2026-09-16','2026-11-16','2026-12-25'];

function diasHabilesHasta(fechaStr) {
  if (!fechaStr) return 99;
  const hoy = new Date(); hoy.setHours(0,0,0,0);
  const dst = new Date(fechaStr+'T00:00:00'); dst.setHours(0,0,0,0);
  if (dst <= hoy) return 0;
  let dias=0, cur=new Date(hoy);
  while (cur < dst) {
    cur.setDate(cur.getDate()+1);
    const dow=cur.getDay(), ymd=cur.toISOString().slice(0,10);
    if (dow!==0 && dow!==6 && !FESTIVOS.includes(ymd)) dias++;
  }
  return dias;
}
function clsUrgencia(d) { return d<=0?'urgente':(d<=2?'alerta':'ok'); }
function colorDias(d)   { return d<=0?'#ef4444':(d<=2?'#f59e0b':'#4a4a5a'); }
function labelDias(d) {
  if (d<=0) return '🔴 VENCIDA';
  if (d===1) return '🟡 1 DÍA H.';
  if (d===2) return '🟡 2 DÍAS H.';
  return '🟢 '+d+' DÍAS H.';
}

function agruparPorOrden(piezas) {
  const map = {};
  piezas.forEach(p => {
    if (!map[p.folio]) map[p.folio] = {
      folio:p.folio, cliente:p.cliente_nombre, asesor:p.asesor,
      fecha:p.fecha_entrega, fecha_cierre:p.fecha_cierre||null,
      avance:p.avance_pct, cnt:0, piezas:[]
    };
    map[p.folio].cnt++;
    map[p.folio].piezas.push(p);
  });
  return Object.values(map).sort((a,b)=>diasHabilesHasta(a.fecha)-diasHabilesHasta(b.fecha));
}

function labelTerminado(o, totalPorOrden) {
  const total = totalPorOrden[o.folio]||0;
  const esC   = o.cnt>=total && total>0;
  if (!esC) return o.cnt+'/'+total+' pzs';
  const fc = o.fecha_cierre?o.fecha_cierre.substring(0,10):null;
  const fe = o.fecha?o.fecha.substring(0,10):null;
  if (!fc||!fe) return '✓ LISTO';
  return fc<=fe?'✓ A TIEMPO':'✗ RETRASO';
}

// ════════════════════════════════════════════════════════
//  VISTA PLANTA
// ════════════════════════════════════════════════════════
function renderPlanta(d) {
  const wrap = document.getElementById('vista-planta');

  const COLS = [
    { key:'pendiente', label:'PENDIENTE', icon:'⏳', cls:'est-pendiente', keys:['pendiente'],           sub:'en espera' },
    { key:'en_corte',  label:'CNC',       icon:'⚙️', cls:'est-cnc',       keys:['en_corte'],            sub:'en optimización' },
    { key:'cortado',   label:'CORTE',     icon:'✂️',  cls:'est-cortado',   keys:['cortado'],             sub:'piezas cortadas' },
    { key:'canteado',  label:'CANTEADO',  icon:'🔩', cls:'est-canteado',  keys:['canteado'],            sub:'piezas canteadas' },
    { key:'trazo',     label:'TRAZO',     icon:'✏️', cls:'est-trazo',     keys:['trazo'],               sub:'en trazo' },
    { key:'taladro',   label:'TALADRO',   icon:'🔧', cls:'est-taladro',   keys:['taladro'],             sub:'en taladro' },
    { key:'templado',  label:'HORNO',     icon:'🔥', cls:'est-templado',  keys:['templado','en_horno'], sub:'en horno' },
    { key:'terminado', label:'TERMINADO', icon:'📦', cls:'est-terminado', keys:['terminado'],           sub:'para entrega' },
  ];

  const totalPorOrden = {};
  d.piezas.forEach(p => { totalPorOrden[p.folio]=(totalPorOrden[p.folio]||0)+1; });

  wrap.innerHTML = COLS.map(col => {
    const piezasCol = d.piezas.filter(p => col.keys.includes(p.estatus));
    const total     = piezasCol.length;
    const ordenes   = agruparPorOrden(piezasCol);
    const numOrd    = ordenes.length;

    const chips = ordenes.length
      ? ordenes.map(o => {
          const dias     = diasHabilesHasta(o.fecha);
          const totalOrd = totalPorOrden[o.folio]||0;
          const esC      = col.key==='terminado' && o.cnt>=totalOrd && totalOrd>0;
          const faltantes= col.key==='terminado' ? totalOrd-o.cnt : 99;
          const casiC    = col.key==='terminado' && !esC && faltantes<=2;
          const cls      = casiC?'casi-completo':(esC?'ok':clsUrgencia(dias));
          const folioClr = esC?'#a78bfa':(colorDias(dias)==='#4a4a5a'?'#fff':colorDias(dias));
          const cntClr   = esC?'#a78bfa':
            col.key==='pendiente'?'var(--c-pendiente)':
            col.key==='en_corte' ?'var(--c-cnc)':
            col.key==='cortado'  ?'var(--c-cortado)':
            col.key==='canteado' ?'var(--c-canteado)':
            col.key==='trazo'    ?'var(--c-trazo)':
            col.key==='taladro'  ?'var(--c-taladro)':
            col.key==='templado' ?'var(--c-templado)':'var(--c-terminado)';
          const cli      = (o.cliente||'—').split(' ').slice(0,3).join(' ');
          const labelBot = col.key==='terminado'?labelTerminado(o,totalPorOrden):labelDias(dias);
          const labelClr = esC?'#a78bfa':colorDias(dias);

          let cristalRows='';
          if (col.key==='pendiente'||col.key==='en_corte'||col.key==='cortado') {
            const byC={};
            o.piezas.forEach(p=>{ const k=p.cristal_corto||p.cristal||'?'; byC[k]=(byC[k]||0)+1; });
            cristalRows=Object.entries(byC).map(([c,n])=>n+' '+c).join(' · ');
          }

          return '<a class="orden-row '+cls+'" href="orden.php?folio='+encodeURIComponent(o.folio)+'">' +
            '<div class="row-left">' +
              '<div class="row-folio" style="color:'+folioClr+'">'+o.folio+'</div>' +
              '<div class="row-info">'+cli+'</div>' +
              (cristalRows?'<div class="cristal-row">'+cristalRows+'</div>':'') +
            '</div>' +
            '<div class="row-right">' +
              '<div class="row-cnt" style="color:'+cntClr+'">'+o.cnt+'</div>' +
              '<div class="row-dias" style="color:'+labelClr+'">'+labelBot+'</div>' +
            '</div>' +
          '</a>';
        }).join('')
      : '<div class="empty-col">— Sin piezas —</div>';

    return '<div class="estacion-col '+col.cls+'">' +
      '<div class="est-head">' +
        '<div class="est-icono">'+col.icon+'</div>' +
        '<div class="est-nombre">'+col.label+'</div>' +
        '<div class="est-stats">' +
          '<div class="est-total">'+total+'</div>' +
          '<div class="est-unit">PZS</div>' +
        '</div>' +
        '<div class="est-sub">'+(numOrd>0?numOrd+' orden'+(numOrd!==1?'es':'')+' · ':'')+col.sub+'</div>' +
      '</div>' +
      '<div class="est-ordenes"><div class="est-ordenes-inner">'+chips+'</div></div>' +
    '</div>';
  }).join('');
}

// ════════════════════════════════════════════════════════
//  VISTA ADMIN
// ════════════════════════════════════════════════════════
function renderAdmin(d) {
  const wrap = document.getElementById('vista-admin');
  const SECS = [
    { label:'Sin cortar',  icon:'⏳', color:'#6b7280', keys:['pendiente'] },
    { label:'CNC',         icon:'⚙️', color:'#ea580c', keys:['en_corte'] },
    { label:'En Corte',    icon:'✂️',  color:'#f59e0b', keys:['cortado'] },
    { label:'En Canteado', icon:'🔩', color:'#06b6d4', keys:['canteado'] },
    { label:'En Trazo',    icon:'✏️', color:'#8b5cf6', keys:['trazo'] },
    { label:'En Taladro',  icon:'🔧', color:'#7c3aed', keys:['taladro'] },
    { label:'En Horno',    icon:'🔥', color:'#ef4444', keys:['templado','en_horno'] },
    { label:'Terminado',   icon:'📦', color:'#22c55e', keys:['terminado'] },
  ];
  wrap.innerHTML = SECS.map(sec => {
    const pz  = d.piezas.filter(p=>sec.keys.includes(p.estatus));
    const tot = pz.length;
    const ords= agruparPorOrden(pz);
    if (!ords.length) return `<div class="admin-section">
      <div class="admin-section-header">
        <div class="section-dot" style="background:${sec.color}"></div>
        <div class="section-titulo" style="color:${sec.color}">${sec.icon} ${sec.label}</div>
        <div class="section-total">Sin piezas activas</div>
      </div></div>`;
    const filas = ords.map(o=>{
      const dias=diasHabilesHasta(o.fecha);
      const fcls=dias<=0?'fecha-urgente':(dias<=2?'fecha-alerta':'fecha-ok');
      const fstr=o.fecha?new Date(o.fecha+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short'}):'—';
      const desg={};
      o.piezas.forEach(p=>{desg[p.estatus]=(desg[p.estatus]||0)+1;});
      const pClr={trazo:'#8b5cf6',taladro:'#a78bfa'};
      const pills=Object.entries(desg).map(([e,c])=>
        `<span class="mini-pill" style="background:${(pClr[e]||sec.color)}22;color:${pClr[e]||sec.color}">${c} ${e}</span>`
      ).join('');
      const av=parseInt(o.avance||0);
      return `<tr>
        <td><a class="folio-link" href="orden.php?folio=${encodeURIComponent(o.folio)}">${o.folio}</a></td>
        <td>${o.cliente||'—'}</td><td style="color:#4a4a5a">${o.asesor||'—'}</td>
        <td class="${fcls}">${fstr}</td>
        <td><strong style="color:${sec.color};font-size:1.8vh">${o.cnt}</strong></td>
        <td>${pills}</td>
        <td style="min-width:10vw"><div class="pct-bar-wrap">
          <div class="pct-bar"><div class="pct-fill" style="width:${av}%"></div></div>
          <div class="pct-label">${av}%</div>
        </div></td>
      </tr>`;
    }).join('');
    return `<div class="admin-section">
      <div class="admin-section-header">
        <div class="section-dot" style="background:${sec.color}"></div>
        <div class="section-titulo" style="color:${sec.color}">${sec.icon} ${sec.label}</div>
        <div class="section-total">${tot} piezas · ${ords.length} órdenes</div>
      </div>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Folio</th><th>Cliente</th><th>Asesor</th>
          <th>Entrega</th><th>Piezas aquí</th><th>Detalle</th><th>Avance orden</th>
        </tr></thead><tbody>${filas}</tbody>
      </table></div></div>`;
  }).join('');
}

cargar();
setInterval(cargar, 120000);

// ── Auto-scroll continuo ──────────────────────────────────
const SCROLL_VPS = 28;    // px por segundo
const PAUSA_MS   = 3000;  // ms de pausa al llegar al fondo

let scrollRAF = null;
let lastTime  = null;

// Por cada .est-ordenes-inner guardamos su estado
// { pos, pausandoHasta }  usando el propio objeto como clave
const colState = new WeakMap();

function getState(inner) {
  if (!colState.has(inner)) colState.set(inner, { pos: 0, pausandoHasta: 0 });
  return colState.get(inner);
}

function autoScroll(ts) {
  if (!lastTime) lastTime = ts;
  const delta = (ts - lastTime) / 1000; // segundos
  lastTime = ts;

  document.querySelectorAll('.est-ordenes').forEach(wrap => {
    const inner = wrap.querySelector('.est-ordenes-inner');
    if (!inner) return;

    const wrapH  = wrap.clientHeight;
    const innerH = inner.scrollHeight;

    // Contenido cabe en pantalla — no scrollear
    if (innerH <= wrapH) {
      inner.style.transform = 'translateY(0)';
      return;
    }

    const st = getState(inner);

    // ¿En pausa? esperar a que termine
    if (st.pausandoHasta > 0) {
      if (performance.now() < st.pausandoHasta) return;
      // Pausa terminó — saltar al inicio
      st.pausandoHasta = 0;
      st.pos = 0;
      inner.style.transform = 'translateY(0)';
      return;
    }

    // Avanzar
    st.pos += SCROLL_VPS * delta;

    const maxScroll = innerH - wrapH;
    if (st.pos >= maxScroll) {
      // Llegó al fondo — iniciar pausa (NO resetear pos todavía)
      st.pos = maxScroll;
      st.pausandoHasta = performance.now() + PAUSA_MS;
    }

    inner.style.transform = 'translateY(-' + st.pos + 'px)';
  });

  scrollRAF = requestAnimationFrame(autoScroll);
}

function iniciarScroll() {
  if (scrollRAF) cancelAnimationFrame(scrollRAF);
  lastTime = null;
  scrollRAF = requestAnimationFrame(autoScroll);
}

// ── Comunicados SmartTV ───────────────────────────────────
const COM_API      = '../api/comunicados.php?accion=activos';
const COM_IMG_BASE = '../imagenes_comunicados/';

let comLista            = [];
let comMostrandose      = false;
let comCola             = [];
const comMostrarProcesado = new Set(); // IDs donde ya se procesó mostrar_ahora, persiste entre recargas

async function cargarComunicados() {
  try {
    const r = await fetch(COM_API + '&t=' + Date.now());
    const d = await r.json();
    if (!d.ok) return;
    comLista = d.data || [];
    // Si ya procesamos el mostrar_ahora de algún ID, asegurarnos que no se vuelva a disparar
    comLista.forEach(c => {
      if (comMostrarProcesado.has(c.id)) c.mostrar_ahora = 0;
    });
  } catch(e) {}
}

function tickComunicados() {
  if (comMostrandose || vista !== 'planta') return;
  const ahora = Date.now();
  comLista.forEach(c => {
    if (comCola.includes(c.id)) return;

    // Botón "Mostrar ahora" del admin
    if (parseInt(c.mostrar_ahora) === 1 && !comMostrarProcesado.has(c.id)) {
      comMostrarProcesado.add(c.id); // marcar antes de cualquier async
      comCola.push(c.id);
      // Resetear en BD inmediatamente
      fetch('../api/comunicados.php?accion=reset_mostrar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: c.id })
      }).then(r => r.json()).then(d => {
        if (d.ok) {
          // Actualizar en comLista local para que el contador use el timestamp correcto
          const item = comLista.find(x => x.id === c.id);
          if (item) item.last_shown_ts = Math.floor(Date.now() / 1000);
        }
      }).catch(() => {});
      return;
    }

    // Solo mostrar automáticamente si ya fue mostrado antes Y ya pasó el intervalo
    const lastMs      = (c.last_shown_ts || 0) * 1000;
    const intervaloMs = c.intervalo_min * 60 * 1000;
    if (c.last_shown_ts && c.last_shown_ts > 0 && (ahora - lastMs) >= intervaloMs) {
      comCola.push(c.id);
    }
  });
  if (comCola.length > 0 && !comMostrandose) mostrarSiguienteComunicado();
}

function mostrarSiguienteComunicado() {
  if (comCola.length === 0 || comMostrandose) return;
  const id  = comCola.shift();
  const com = comLista.find(c => c.id === id);
  if (!com) { mostrarSiguienteComunicado(); return; }

  const duracionMs = (parseInt(com.duracion_seg) || 90) * 1000;

  comMostrandose = true;

  // Pausar scroll
  if (scrollRAF) { cancelAnimationFrame(scrollRAF); scrollRAF = null; }

  const overlay = document.getElementById('overlay-comunicado');
  const img     = document.getElementById('com-imagen');
  const fill    = document.getElementById('com-barra-fill');

  img.src = COM_IMG_BASE + com.archivo;
  overlay.classList.add('visible');

  fill.style.transition = 'none';
  fill.style.transform  = 'scaleX(1)';
  requestAnimationFrame(() => {
    fill.style.transition = `transform ${duracionMs}ms linear`;
    fill.style.transform  = 'scaleX(0)';
  });

  setTimeout(() => {
    overlay.classList.remove('visible');
    img.src = '';
    comMostrandose = false;
    comMostrarProcesado.delete(com.id); // limpiar para que pueda usarse de nuevo
    lastTime  = null;
    scrollRAF = requestAnimationFrame(autoScroll);

    // Notificar a la BD: resetear mostrar_ahora y guardar last_shown_at
    fetch('../api/comunicados.php?accion=reset_mostrar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: com.id })
    }).catch(() => {});

    if (comCola.length > 0) setTimeout(mostrarSiguienteComunicado, 500);
  }, duracionMs);
}

// Iniciar comunicados
cargarComunicados().then(() => tickComunicados());
setInterval(cargarComunicados, 30 * 1000);  // cada 30s para detectar mostrar_ahora
setInterval(tickComunicados,   30 * 1000);
</script>
<!-- Overlay Comunicados SmartTV -->
<div id="overlay-comunicado">
  <img id="com-imagen" src="" alt="Comunicado">
  <div class="com-barra-wrap">
    <div class="com-barra-label">📢 COMUNICADO</div>
    <div class="com-barra-track">
      <div class="com-barra-fill" id="com-barra-fill"></div>
    </div>
  </div>
</div>

</body>
</html>