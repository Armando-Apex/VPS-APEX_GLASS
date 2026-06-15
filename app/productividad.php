<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
requirePermiso('ver_dashboard');
$rol     = $_SESSION['user_rol']    ?? '';
$nombre  = $_SESSION['user_nombre'] ?? 'U';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APEX GLASS — Productividad</title>
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f9fa; color: #1a1a1a; min-height: 100vh; }

/* ── Topbar ── */
.topbar {
  background: #fff; border-bottom: 0.5px solid #e5e7eb;
  height: 52px; display: flex; align-items: center;
  justify-content: space-between; padding: 0 24px;
  position: sticky; top: 0; z-index: 100;
}
.topbar-logo {
  font-family: 'Syncopate', sans-serif; font-weight: 700;
  font-size: 15px; color: #1a1a1a; letter-spacing: 1px; text-decoration: none;
}
.topbar-nav { display: flex; gap: 2px; }
.nav-item {
  display: flex; align-items: center; gap: 5px;
  padding: 6px 14px; border-radius: 8px;
  font-size: 13px; color: #6b7280;
  text-decoration: none; transition: background .15s, color .15s;
}
.nav-item:hover { background: #f3f4f6; color: #1a1a1a; }
.nav-item.active { background: #f0fdf4; color: #166534; font-weight: 500; }
.topbar-right { display: flex; align-items: center; gap: 12px; }
.topbar-time { font-size: 12px; color: #9ca3af; }
.avatar {
  width: 28px; height: 28px; border-radius: 50%;
  background: #dcfce7; color: #166534;
  font-size: 11px; font-weight: 500;
  display: flex; align-items: center; justify-content: center;
}
.btn-refresh {
  background: none; border: 0.5px solid #e5e7eb;
  border-radius: 8px; padding: 6px 12px;
  font-size: 12px; color: #6b7280; cursor: pointer;
}
.btn-refresh:hover { background: #f3f4f6; }
.btn-salir { font-size: 12px; color: #9ca3af; text-decoration: none; }

/* ── Layout ── */
.layout { display: flex; min-height: calc(100vh - 52px); }

/* ── Sidebar ── */
.sidebar {
  width: 210px; flex-shrink: 0;
  background: #fff; border-right: 0.5px solid #e5e7eb;
  padding: 20px 0;
}
.sb-section { margin-bottom: 20px; padding: 0 12px; }
.sb-label {
  font-size: 10px; font-weight: 500; letter-spacing: 1.5px;
  text-transform: uppercase; color: #9ca3af;
  margin-bottom: 6px; padding: 0 6px;
}
.sb-item {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 10px; border-radius: 8px;
  font-size: 13px; color: #6b7280;
  text-decoration: none; transition: background .15s, color .15s;
}
.sb-item:hover { background: #f3f4f6; color: #1a1a1a; }
.sb-item.active { background: #f0fdf4; color: #166534; font-weight: 500; }
.sb-item svg { width: 15px; height: 15px; flex-shrink: 0; }

/* ── Main ── */
.main-wrap { flex: 1; display: flex; flex-direction: column; }

/* ── Sub-tabs ── */
.subtabs {
  background: #fff; border-bottom: 0.5px solid #e5e7eb;
  padding: 0 24px; display: flex; gap: 2px;
  overflow-x: auto;
}
.subtab {
  padding: 12px 16px; font-size: 13px; font-weight: 500;
  color: #6b7280; cursor: pointer; border: none;
  background: none; border-bottom: 2px solid transparent;
  transition: all .15s; white-space: nowrap;
}
.subtab:hover { color: #1a1a1a; }
.subtab.active { color: #166534; border-bottom-color: #16a34a; }

/* ── Content ── */
.content { padding: 24px; max-width: 1400px; }

/* ── Tarjetas resumen estación ── */
.est-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px; }
.est-card {
  background: #fff; border: 0.5px solid #e5e7eb;
  border-radius: 12px; padding: 16px;
}
.est-card-header {
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 12px;
}
.est-icon { font-size: 16px; }
.est-nombre {
  font-size: 11px; font-weight: 600;
  text-transform: uppercase; letter-spacing: .5px;
}
.est-stat { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
.est-lbl { font-size: 11px; color: #9ca3af; }
.est-val { font-size: 20px; font-weight: 500; }
.est-unit { font-size: 11px; color: #9ca3af; margin-left: 2px; }
.est-sub { display: flex; justify-content: space-between; font-size: 11px; color: #9ca3af; margin-bottom: 4px; }
.est-sub b { color: #374151; }
.pico-baja { display: flex; gap: 6px; margin-top: 10px; }
.pb-chip { flex: 1; border-radius: 8px; padding: 6px 8px; text-align: center; }
.pb-lbl { font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
.pb-val { font-size: 11px; font-weight: 500; }
.chip-pico { background: #f0fdf4; }
.chip-pico .pb-lbl { color: #16a34a; }
.chip-baja { background: #fef2f2; }
.chip-baja .pb-lbl { color: #dc2626; }

/* ── Tabla franjas ── */
.card {
  background: #fff; border: 0.5px solid #e5e7eb;
  border-radius: 12px; overflow: hidden; margin-bottom: 16px;
}
.card-head {
  padding: 14px 18px; border-bottom: 0.5px solid #f3f4f6;
  font-size: 13px; font-weight: 500; color: #374151;
}
table { width: 100%; border-collapse: collapse; }
thead { background: #fafafa; }
th {
  padding: 10px 14px; text-align: center;
  font-size: 11px; font-weight: 500; color: #9ca3af;
  text-transform: uppercase; letter-spacing: .5px;
  border-bottom: 0.5px solid #f3f4f6;
}
td {
  padding: 11px 14px; font-size: 13px; color: #374151;
  border-bottom: 0.5px solid #f9fafb;
  text-align: center;
}
tr:last-child td { border-bottom: none; }
tr.pico-row td { background: #f0fdf4; }
tr.baja-row td { background: #fef2f2; }
tr.extra-row td { background: #fffbeb; }
.franja-lbl { font-weight: 500; color: #1a1a1a; text-align: left; }
td:first-child { text-align: left; }
.tipo-badge {
  font-size: 9px; font-weight: 600; padding: 2px 7px;
  border-radius: 4px; margin-left: 6px;
}
.tipo-normal  { background: #dcfce7; color: #166534; }
.tipo-extra   { background: #fef9c3; color: #854d0e; }
.tipo-pico    { background: #dcfce7; color: #166534; }
.tipo-baja    { background: #fee2e2; color: #991b1b; }
.val-cell { font-weight: 500; }
.delta-ok   { color: #16a34a; }
.delta-warn { color: #dc2626; font-weight: 500; }
.muerto-ok  { color: #9ca3af; }
.muerto-warn { color: #dc2626; font-weight: 500; }

/* ── Vista comparativa ── */
.comp-grid { display: flex; flex-direction: column; gap: 12px; }
.comp-card { background: #fff; border: 0.5px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.comp-head {
  padding: 14px 20px; background: #fafafa;
  border-bottom: 0.5px solid #f3f4f6;
  display: flex; justify-content: space-between; align-items: center;
}
.comp-titulo { font-size: 15px; font-weight: 500; color: #1a1a1a; }
.comp-sub    { font-size: 12px; color: #9ca3af; margin-top: 2px; }
.comp-hrs    { font-size: 12px; color: #6b7280; }
.comp-hrs b  { color: #166534; font-weight: 500; }
.comp-body   { padding: 16px 20px; display: grid; grid-template-columns: repeat(5,1fr); gap: 16px; }
.comp-est-title {
  font-size: 10px; font-weight: 600; text-transform: uppercase;
  letter-spacing: .5px; margin-bottom: 10px;
}
.comp-stat { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; font-size: 12px; }
.comp-stat span { color: #9ca3af; }
.comp-stat b    { font-size: 16px; font-weight: 500; }
.comp-divider   { height: 0.5px; background: #f3f4f6; margin: 6px 0; }
.comp-row { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px; }
.comp-row span  { color: #9ca3af; }
.comp-row b     { color: #374151; font-weight: 500; }
.cristal-mini { margin-top: 8px; }
.cristal-mini-item {
  display: flex; justify-content: space-between;
  font-size: 10px; padding: 3px 0;
  border-bottom: 0.5px solid #f3f4f6;
}
.cristal-mini-item:last-child { border-bottom: none; }
.cristal-mini-name { color: #9ca3af; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 120px; }
.cristal-mini-val  { font-weight: 500; color: #374151; flex-shrink: 0; margin-left: 6px; }

.loading-msg { text-align: center; padding: 60px; color: #9ca3af; font-size: 14px; }
.spin-wrap   { display: flex; align-items: center; justify-content: center; padding: 60px; gap: 12px; }
.spin { width: 18px; height: 18px; border: 2px solid #e5e7eb; border-top-color: #16a34a; border-radius: 50%; animation: sp .7s linear infinite; }
@keyframes sp { to { transform: rotate(360deg); } }

@media (max-width: 1100px) {
  .est-grid  { grid-template-columns: repeat(3,1fr); }
  .comp-body { grid-template-columns: repeat(3,1fr); }
}
@media (max-width: 768px) {
  .sidebar   { display: none; }
  .est-grid  { grid-template-columns: 1fr 1fr; }
  .comp-body { grid-template-columns: 1fr 1fr; }
  .content   { padding: 16px; }
  th:nth-child(n+4), td:nth-child(n+4) { display: none; }
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <a href="dashboard.php" class="topbar-logo">APEX GLASS</a>
  <nav class="topbar-nav">
    <a href="dashboard.php"  class="nav-item">◻ Dashboard</a>
    <a href="ordenes.php"    class="nav-item">◻ Órdenes</a>
    <?php if (in_array($rol, ['director','dir_admin','dueno','administracion'])): ?>
    <a href="reporte_direccion.php" class="nav-item">◻ Reporte</a>
    <?php endif; ?>
    <a href="productividad.php" class="nav-item active">◻ Productividad</a>
  </nav>
  <div class="topbar-right">
    <span class="topbar-time" id="tsLabel">—</span>
    <button class="btn-refresh" onclick="cargar()">↻ Actualizar</button>
    <div class="avatar"><?php echo strtoupper(substr($nombre, 0, 2)); ?></div>
    <a href="../api/logout.php?redirect=login.php" class="btn-salir">Salir</a>
  </div>
</div>

<div class="layout">

  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sb-section">
      <div class="sb-label">Producción</div>
      <a href="dashboard.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Resumen
      </a>
      <a href="produccion_estaciones.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2v-4M9 21H5a2 2 0 01-2-2v-4m0 0h18"/></svg>
        Tablero planta
      </a>
    </div>
    <div class="sb-section">
      <div class="sb-label">Comercial</div>
      <a href="recibir_orden.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 4v16m8-8H4"/></svg>
        Nueva orden
      </a>
      <a href="ordenes.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Órdenes
      </a>
    </div>
    <?php if (in_array($rol, ['director','dir_admin','dueno','administracion'])): ?>
    <div class="sb-section">
      <div class="sb-label">Reportes</div>
      <a href="reporte_direccion.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Dirección
      </a>
      <a href="productividad.php" class="sb-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Productividad
      </a>
      <a href="optimizador_corte.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.12 15.88M14.47 14.48L20 20M8.12 8.12L12 12"/></svg>
        Optimizador
      </a>
    </div>
    <?php endif; ?>
  </aside>

  <!-- Main -->
  <div class="main-wrap">
    <div class="subtabs">
      <button class="subtab active" onclick="cambiarVista('hora')"   id="tab-hora">⏱ Por hora</button>
      <button class="subtab"        onclick="cambiarVista('dia')"    id="tab-dia">📅 Por día</button>
      <button class="subtab"        onclick="cambiarVista('semana')" id="tab-semana">📆 Por semana</button>
      <button class="subtab"        onclick="cambiarVista('mes')"    id="tab-mes">🗓 Por mes</button>
    </div>
    <div class="content" id="main">
      <div class="spin-wrap"><div class="spin"></div></div>
    </div>
  </div>
</div>

<script>
const API = '../api/productividad.php';
let vista = 'hora';

const EST = {
  corte:    { nombre:'Corte',    icon:'✂️',  color:'#d97706', unidad:'m²'  },
  canteado: { nombre:'Canteado', icon:'🔩',  color:'#0891b2', unidad:'ml'  },
  trazo:    { nombre:'Trazo',    icon:'✏️',  color:'#7c3aed', unidad:'pzs' },
  taladro:  { nombre:'Taladro',  icon:'🔧',  color:'#db2777', unidad:'pzs' },
  horno:    { nombre:'Horno',    icon:'🔥',  color:'#dc2626', unidad:'m²'  },
};
const ESTS = Object.keys(EST);

function fmt(v, dec=2) {
  if (v===null||v===undefined) return '—';
  const n = parseFloat(v);
  if (isNaN(n)) return '—';
  return dec===0 ? n.toLocaleString('es-MX') : n.toFixed(dec);
}

function cambiarVista(v) {
  vista = v;
  document.querySelectorAll('.subtab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-'+v).classList.add('active');
  cargar();
}

async function cargar() {
  document.getElementById('main').innerHTML = '<div class="spin-wrap"><div class="spin"></div></div>';
  try {
    const res  = await fetch(API + '?vista=' + vista + '&t=' + Date.now());
    const data = await res.json();
    if (data.error) {
      document.getElementById('main').innerHTML = `<div class="loading-msg">⚠️ ${data.error}</div>`;
      return;
    }
    if      (vista==='hora')   renderHora(data);
    else if (vista==='dia')    renderComp(data.dias,    'label','fecha',  'hrs_prod','Días recientes');
    else if (vista==='semana') renderComp(data.semanas, 'label','desde',  'hrs_prod','Semanas');
    else if (vista==='mes')    renderComp(data.meses,   'label','mes',    'hrs_prod','Meses');
    document.getElementById('tsLabel').textContent =
      'Act. ' + new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  } catch(e) {
    document.getElementById('main').innerHTML = '<div class="loading-msg">❌ Error de conexión</div>';
  }
}

// ── Vista Hora ─────────────────────────────────────────────
function renderHora(data) {
  const franjas = data.franjas || [];
  const pico    = data.pico    || {};
  const baja    = data.baja    || {};

  if (!franjas.length) {
    document.getElementById('main').innerHTML = '<div class="loading-msg">Sin actividad registrada hoy</div>';
    return;
  }

  // Totales del día
  const totales = {};
  ESTS.forEach(e => {
    totales[e] = { total:0, muertos:0, deltas:[] };
    franjas.forEach(f => {
      const d = f.datos[e];
      totales[e].total   += parseFloat(d.total||0);
      totales[e].muertos += parseInt(d.tiempos_muertos||0);
      if (d.prom_delta_min !== null) totales[e].deltas.push(parseFloat(d.prom_delta_min));
    });
    totales[e].total = Math.round(totales[e].total * 100) / 100;
    totales[e].promDelta = totales[e].deltas.length
      ? Math.round(totales[e].deltas.reduce((a,b)=>a+b,0)/totales[e].deltas.length * 10)/10
      : null;
  });

  // Tarjetas
  let html = `<div class="est-grid">`;
  ESTS.forEach(e => {
    const est = EST[e];
    const tot = totales[e];
    const isInt = e==='trazo'||e==='taladro';
    const dec   = isInt ? 0 : 2;
    const iP = pico[e]; const iB = baja[e];
    const picoLabel = iP!=null && iP!=undefined ? franjas[iP]?.label : null;
    const bajaLabel = iB!=null && iB!=undefined ? franjas[iB]?.label : null;

    html += `
      <div class="est-card">
        <div class="est-card-header">
          <span class="est-icon">${est.icon}</span>
          <span class="est-nombre" style="color:${est.color}">${est.nombre}</span>
        </div>
        <div class="est-stat">
          <span class="est-lbl">Total hoy</span>
          <span><span class="est-val" style="color:${est.color}">${fmt(tot.total,dec)}</span><span class="est-unit">${est.unidad}</span></span>
        </div>
        <div class="est-sub">
          <span>Prom. entre escaneos</span>
          <b style="color:${tot.promDelta!==null&&tot.promDelta>=10?'#dc2626':'#16a34a'}">${tot.promDelta!==null?tot.promDelta+' min':'—'}</b>
        </div>
        <div class="est-sub">
          <span>Tiempos muertos</span>
          <b style="color:${tot.muertos>0?'#dc2626':'#9ca3af'}">${tot.muertos>0?tot.muertos:'—'}</b>
        </div>
        <div class="pico-baja">
          <div class="pb-chip chip-pico">
            <div class="pb-lbl">🔥 Pico</div>
            <div class="pb-val">${picoLabel||'—'}</div>
          </div>
          <div class="pb-chip chip-baja">
            <div class="pb-lbl">🐢 Baja</div>
            <div class="pb-val">${bajaLabel||'—'}</div>
          </div>
        </div>
      </div>`;
  });
  html += `</div>`;

  // Tabla franjas
  html += `
    <div class="card">
      <div class="card-head">Detalle por franja horaria</div>
      <div style="overflow-x:auto">
      <table>
        <thead><tr>
          <th>Franja</th>
          <th style="color:#d97706">✂️ Corte m²</th>
          <th style="color:#0891b2">🔩 Canteado ml</th>
          <th style="color:#7c3aed">✏️ Trazo pzs</th>
          <th style="color:#db2777">🔧 Taladro pzs</th>
          <th style="color:#dc2626">🔥 Horno m²</th>
          <th>⏱ Prom espera</th>
          <th>🔴 T.Muertos</th>
        </tr></thead>
        <tbody>`;

  franjas.forEach((f, i) => {
    const esPico = ESTS.some(e => pico[e]===i);
    const esBaja = ESTS.some(e => baja[e]===i);
    const cls = f.tipo==='extra' ? 'extra-row' : (esPico ? 'pico-row' : (esBaja ? 'baja-row' : ''));

    let badge = `<span class="tipo-badge tipo-${f.tipo}">${f.tipo==='normal'?'TURNO':'EXTRA'}</span>`;
    if (esPico && f.tipo==='normal') badge += `<span class="tipo-badge tipo-pico">🔥 PICO</span>`;
    if (esBaja && f.tipo==='normal') badge += `<span class="tipo-badge tipo-baja">🐢 BAJA</span>`;

    const vals = ESTS.map(e => {
      const d = f.datos[e];
      const isInt = e==='trazo'||e==='taladro';
      const v   = parseFloat(d.total||0);
      const pzs = parseInt(d.conteo||0);
      // Corte, canteado y horno muestran "pzs / valor"
      const mostrarPzs = ['corte','canteado','horno'].includes(e);
      const texto = v > 0
        ? (mostrarPzs
            ? `<span style="font-size:11px;margin-right:3px">${pzs} /</span>${fmt(v,isInt?0:2)}`
            : fmt(v, isInt?0:2))
        : '—';
      return `<td class="val-cell" style="color:${v>0?EST[e].color:'#d1d5db'}">${texto}</td>`;
    }).join('');

    const deltasF = ESTS.map(e => f.datos[e].prom_delta_min).filter(v => v!==null);
    const promDeltaF = deltasF.length ? (deltasF.reduce((a,b)=>a+b,0)/deltasF.length).toFixed(1) : null;
    const muertosF   = ESTS.reduce((sum,e) => sum+parseInt(f.datos[e].tiempos_muertos||0), 0);

    html += `<tr class="${cls}">
      <td class="franja-lbl">${f.label}${badge}</td>
      ${vals}
      <td class="${promDeltaF!==null&&promDeltaF>=10?'delta-warn':'delta-ok'}">${promDeltaF!==null?promDeltaF+' min':'—'}</td>
      <td class="${muertosF>0?'muerto-warn':'muerto-ok'}">${muertosF>0?muertosF:'—'}</td>
    </tr>`;
  });

  html += `</tbody></table></div></div>`;
  document.getElementById('main').innerHTML = html;
}

// ── Vista Comparativa ──────────────────────────────────────
function renderComp(periodos, labelKey, subKey, hrsKey, titulo) {
  if (!periodos || !periodos.length) {
    document.getElementById('main').innerHTML = '<div class="loading-msg">Sin datos suficientes</div>';
    return;
  }

  let html = `<div class="comp-grid">`;
  periodos.forEach(p => {
    const hrsP = parseFloat(p[hrsKey]||0).toFixed(1);
    html += `
      <div class="comp-card">
        <div class="comp-head">
          <div>
            <div class="comp-titulo">${p[labelKey]}</div>
            <div class="comp-sub">${p[subKey]||''}</div>
          </div>
          <div class="comp-hrs">Horas productivas: <b>${hrsP} hrs</b></div>
        </div>
        <div class="comp-body">`;

    ESTS.forEach(e => {
      const est   = EST[e];
      const d     = p.datos[e];
      const isInt = e==='trazo'||e==='taladro';
      const dec   = isInt ? 0 : 2;

      html += `
        <div>
          <div class="comp-est-title" style="color:${est.color}">${est.icon} ${est.nombre}</div>
          <div class="comp-stat">
            <span>Total</span>
            <span><b style="color:${est.color}">${fmt(d.total,dec)}</b> <span style="font-size:11px;color:#9ca3af">${est.unidad}</span></span>
          </div>
          <div class="comp-divider"></div>
          <div class="comp-row"><span>Por hora</span><b>${fmt(d.tasa_hr,dec)} ${est.unidad}/hr</b></div>
          <div class="comp-row">
            <span>Prom. escaneos</span>
            <b style="color:${d.prom_delta_min!==null&&d.prom_delta_min>=10?'#dc2626':'#16a34a'}">
              ${d.prom_delta_min!==null?d.prom_delta_min+' min':'—'}
            </b>
          </div>
          <div class="comp-row">
            <span>Tiempos muertos</span>
            <b style="color:${d.tiempos_muertos>0?'#dc2626':'#9ca3af'}">${d.tiempos_muertos>0?d.tiempos_muertos:'—'}</b>
          </div>
          ${e==='horno'&&d.por_cristal&&d.por_cristal.length>0?`
          <div class="cristal-mini">
            ${d.por_cristal.slice(0,5).map(c=>`
              <div class="cristal-mini-item">
                <span class="cristal-mini-name">${c.cristal}</span>
                <span class="cristal-mini-val">${c.m2} m²</span>
              </div>`).join('')}
          </div>`:''}
        </div>`;
    });

    html += `</div></div>`;
  });

  html += `</div>`;
  document.getElementById('main').innerHTML = html;
}

cargar();
setInterval(cargar, 120000);
</script>
</body>
</html>