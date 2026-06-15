<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
requirePermiso('ver_dashboard');
$rol    = $_SESSION['user_rol']    ?? '';
$nombre = $_SESSION['user_nombre'] ?? 'U';
if (!in_array($rol, ['director','dir_admin','dueno','administracion'])) {
    include __DIR__ . '/403.php'; exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APEX GLASS — Reporte Dirección</title>
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
.btn-refresh { background: none; border: 0.5px solid #e5e7eb; border-radius: 8px; padding: 6px 12px; font-size: 12px; color: #6b7280; cursor: pointer; }
.btn-refresh:hover { background: #f3f4f6; }
.btn-salir { font-size: 12px; color: #9ca3af; text-decoration: none; }

/* ── Layout ── */
.layout { display: flex; min-height: calc(100vh - 52px); }

/* ── Sidebar ── */
.sidebar { width: 210px; flex-shrink: 0; background: #fff; border-right: 0.5px solid #e5e7eb; padding: 20px 0; }
.sb-section { margin-bottom: 20px; padding: 0 12px; }
.sb-label { font-size: 10px; font-weight: 500; letter-spacing: 1.5px; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; padding: 0 6px; }
.sb-item { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-radius: 8px; font-size: 13px; color: #6b7280; text-decoration: none; transition: background .15s, color .15s; }
.sb-item:hover { background: #f3f4f6; color: #1a1a1a; }
.sb-item.active { background: #f0fdf4; color: #166534; font-weight: 500; }
.sb-item svg { width: 15px; height: 15px; flex-shrink: 0; }

/* ── Main ── */
.main { flex: 1; padding: 24px; max-width: 1200px; }

/* ── Filtro ── */
.filter-row {
  display: flex; align-items: center; gap: 12px;
  margin-bottom: 20px; flex-wrap: wrap;
}
.filter-row label { font-size: 12px; color: #6b7280; font-weight: 500; }
.filter-row select {
  background: #fff; border: 0.5px solid #e5e7eb;
  border-radius: 8px; padding: 7px 12px;
  font-size: 13px; color: #1a1a1a; outline: none; cursor: pointer;
}
.filter-row select:focus { border-color: #16a34a; }
.ts-label { font-size: 12px; color: #9ca3af; margin-left: auto; }
.live-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #22c55e; margin-right: 4px; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

/* ── KPI grid ── */
.kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 10px; margin-bottom: 16px; }
.kpi-card {
  background: #fff; border: 0.5px solid #e5e7eb;
  border-radius: 12px; padding: 18px 16px; text-align: center;
  border-top: 3px solid #e5e7eb;
}
.kpi-num   { font-size: 34px; font-weight: 500; line-height: 1; margin-bottom: 4px; }
.kpi-label { font-size: 11px; font-weight: 500; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; }
.kpi-sub   { font-size: 12px; color: #9ca3af; margin-top: 4px; }
.kpi-total   { border-top-color: #2563eb; } .kpi-total .kpi-num   { color: #2563eb; }
.kpi-tiempo  { border-top-color: #16a34a; } .kpi-tiempo .kpi-num  { color: #16a34a; }
.kpi-retraso { border-top-color: #dc2626; } .kpi-retraso .kpi-num { color: #dc2626; }
.kpi-proceso { border-top-color: #d97706; } .kpi-proceso .kpi-num { color: #d97706; }

/* ── Métricas secundarias ── */
.metrics-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; margin-bottom: 16px; }
.metric-card {
  background: #fff; border: 0.5px solid #e5e7eb;
  border-radius: 12px; padding: 14px 16px;
  display: flex; align-items: center; gap: 12px;
}
.metric-icon { font-size: 24px; flex-shrink: 0; }
.metric-num  { font-size: 22px; font-weight: 500; line-height: 1; }
.metric-lbl  { font-size: 11px; color: #9ca3af; font-weight: 500; text-transform: uppercase; letter-spacing: .5px; margin-top: 3px; }

/* ── Efectividad ── */
.efect-card {
  background: #fff; border: 0.5px solid #e5e7eb;
  border-radius: 12px; padding: 18px 20px; margin-bottom: 16px;
}
.efect-header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 12px; }
.efect-title { font-size: 12px; font-weight: 500; color: #9ca3af; text-transform: uppercase; letter-spacing: .5px; }
.efect-pct   { font-size: 30px; font-weight: 500; }
.efect-bar   { height: 8px; background: #f3f4f6; border-radius: 4px; overflow: hidden; margin-bottom: 10px; }
.efect-fill  { height: 100%; border-radius: 4px; background: #16a34a; transition: width .8s ease; }
.efect-legend { display: flex; gap: 16px; font-size: 12px; color: #6b7280; }
.leg-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }

/* ── Section title ── */
.section-title { font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 10px; margin-top: 4px; }

/* ── Tabla mensual ── */
.table-card { background: #fff; border: 0.5px solid #e5e7eb; border-radius: 12px; overflow: hidden; margin-bottom: 16px; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 700px; }
thead { background: #fafafa; }
thead th {
  padding: 10px 14px; font-size: 11px; font-weight: 500;
  text-transform: uppercase; letter-spacing: .5px;
  color: #9ca3af; text-align: center;
  border-bottom: 0.5px solid #f3f4f6; white-space: nowrap;
}
thead th:first-child { text-align: left; }
tbody tr:hover td { background: #fafafa; }
tbody td {
  padding: 11px 14px; font-size: 13px; color: #374151;
  border-bottom: 0.5px solid #f9fafb; text-align: center;
}
tbody td:first-child { text-align: left; font-weight: 500; }
tbody tr:last-child td { border-bottom: none; }
tfoot tr { background: #f0fdf4; }
tfoot td { padding: 11px 14px; font-size: 13px; font-weight: 500; color: #166534; text-align: center; border-top: 0.5px solid #bbf7d0; }
tfoot td:first-child { text-align: left; }

.badge-tiempo  { background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 500; }
.badge-retraso { background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 500; }
.badge-proceso { background: #fef9c3; color: #854d0e; padding: 2px 8px; border-radius: 99px; font-size: 11px; font-weight: 500; }
.pct-good { color: #16a34a; font-weight: 500; }
.pct-warn { color: #d97706; font-weight: 500; }
.pct-bad  { color: #dc2626; font-weight: 500; }

/* ── Órdenes abiertas ── */
.ordenes-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px; }
.orden-item {
  background: #fff; border: 0.5px solid #e5e7eb;
  border-left: 3px solid #e5e7eb;
  border-radius: 10px; padding: 12px 14px;
  text-decoration: none; color: inherit;
  display: block; transition: background .15s;
}
.orden-item:hover { background: #fafafa; }
.orden-item.urgente { border-left-color: #dc2626; }
.orden-item.alerta  { border-left-color: #d97706; }
.orden-item.proceso { border-left-color: #2563eb; }
.oi-folio   { font-size: 14px; font-weight: 500; color: #2563eb; margin-bottom: 2px; }
.oi-cliente { font-size: 12px; color: #9ca3af; }
.oi-meta    { display: flex; justify-content: space-between; margin-top: 8px; font-size: 12px; color: #6b7280; }

/* ── Progress mini ── */
.prog-bar { height: 4px; background: #f3f4f6; border-radius: 2px; margin-top: 6px; overflow: hidden; }
.prog-fill { height: 100%; border-radius: 2px; background: #16a34a; }

.loading-msg { text-align: center; padding: 60px; color: #9ca3af; font-size: 14px; }
.spin-wrap   { display: flex; align-items: center; justify-content: center; padding: 60px; gap: 12px; }
.spin { width: 18px; height: 18px; border: 2px solid #e5e7eb; border-top-color: #16a34a; border-radius: 50%; animation: sp .7s linear infinite; }
@keyframes sp { to { transform: rotate(360deg); } }

@media (max-width: 1024px) {
  .kpi-grid { grid-template-columns: repeat(2,1fr); }
  .ordenes-grid { grid-template-columns: 1fr; }
}
@media (max-width: 768px) {
  .sidebar { display: none; }
  .main { padding: 16px; }
  .metrics-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <a href="dashboard.php" class="topbar-logo">APEX GLASS</a>
  <nav class="topbar-nav">
    <a href="dashboard.php"         class="nav-item">◻ Dashboard</a>
    <a href="ordenes.php"           class="nav-item">◻ Órdenes</a>
    <a href="reporte_direccion.php" class="nav-item active">◻ Reporte</a>
    <a href="productividad.php"     class="nav-item">◻ Productividad</a>
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
    <div class="sb-section">
      <div class="sb-label">Reportes</div>
      <a href="reporte_direccion.php" class="sb-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Dirección
      </a>
      <a href="productividad.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Productividad
      </a>
      <a href="optimizador_corte.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4L8.12 15.88M14.47 14.48L20 20M8.12 8.12L12 12"/></svg>
        Optimizador
      </a>
    </div>
  </aside>

  <!-- Main -->
  <main class="main">

    <div class="filter-row">
      <label>Período:</label>
      <select id="filtroMes" onchange="cargar()">
        <option value="mes_actual">Este mes</option>
        <option value="mes_anterior">Mes anterior</option>
        <option value="3meses">Últimos 3 meses</option>
        <option value="6meses">Últimos 6 meses</option>
        <option value="año">Este año</option>
        <option value="todo">Todo el historial</option>
      </select>
      <span class="ts-label"><span class="live-dot"></span><span id="tsInner">Cargando…</span></span>
    </div>

    <div id="main-content">
      <div class="spin-wrap"><div class="spin"></div></div>
    </div>

  </main>
</div>

<script>
const API = '../api/';

async function cargar() {
  const periodo = document.getElementById('filtroMes').value;
  document.getElementById('main-content').innerHTML = '<div class="spin-wrap"><div class="spin"></div></div>';
  try {
    const [r1, r2] = await Promise.all([
      fetch(API + 'reporte_direccion.php?periodo=' + periodo),
      fetch(API + 'dashboard.php')
    ]);
    const rep  = await r1.json();
    const dash = await r2.json();
    if (rep.error) {
      document.getElementById('main-content').innerHTML = `<div class="loading-msg">⚠️ ${rep.error}</div>`;
      return;
    }
    render(rep, dash);
    document.getElementById('tsLabel').textContent =
      new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'}) +
      ' — ' + new Date().toLocaleDateString('es-MX',{weekday:'short',day:'2-digit',month:'short'});
    document.getElementById('tsInner').textContent =
      'Act. ' + new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  } catch(e) {
    document.getElementById('main-content').innerHTML = '<div class="loading-msg">❌ Error de conexión</div>';
  }
}

function fmtFecha(ts) {
  if (!ts) return '—';
  const d = new Date(ts.replace(' ','T'));
  if (isNaN(d)) return '—';
  return d.toLocaleDateString('es-MX', {day:'2-digit', month:'short', year:'numeric'});
}

function diasPara(fecha) {
  if (!fecha) return 99;
  const f = new Date((fecha.includes('T') ? fecha : fecha + 'T12:00:00'));
  return Math.ceil((f - new Date()) / 86400000);
}

function render(rep, dash) {
  const r       = rep.resumen || {};
  const mensual = rep.mensual || [];
  const abiertas = (dash.ordenes || []).filter(o => parseInt(o.avance_pct||0) < 100);

  const pctTiempo = r.total > 0 ? Math.round((r.a_tiempo / r.total) * 100) : 0;
  const pctColor  = pctTiempo >= 80 ? '#16a34a' : pctTiempo >= 60 ? '#d97706' : '#dc2626';
  let html = '';

  // ── KPIs ──
  html += `<div class="kpi-grid">
    <div class="kpi-card kpi-total">
      <div class="kpi-num">${r.total||0}</div>
      <div class="kpi-label">Total órdenes</div>
      <div class="kpi-sub">${r.cerradas||0} cerradas · ${r.abiertas||0} abiertas</div>
    </div>
    <div class="kpi-card kpi-tiempo">
      <div class="kpi-num">${r.a_tiempo||0}</div>
      <div class="kpi-label">✅ A tiempo</div>
      <div class="kpi-sub">${pctTiempo}% del total</div>
    </div>
    <div class="kpi-card kpi-retraso">
      <div class="kpi-num">${r.con_retraso||0}</div>
      <div class="kpi-label">⚠️ Con retraso</div>
      <div class="kpi-sub">${r.total>0?Math.round((r.con_retraso/r.total)*100):0}% del total</div>
    </div>
    <div class="kpi-card kpi-proceso">
      <div class="kpi-num">${r.en_proceso||0}</div>
      <div class="kpi-label">🔄 En proceso</div>
      <div class="kpi-sub">${r.retraso_abierto||0} con retraso</div>
    </div>
  </div>`;

  // ── Métricas ──
  html += `<div class="metrics-grid">
    <div class="metric-card">
      <div class="metric-icon">⏱️</div>
      <div>
        <div class="metric-num">${r.prom_dias ? parseFloat(r.prom_dias).toFixed(1) : '—'}</div>
        <div class="metric-lbl">Promedio días proceso</div>
      </div>
    </div>
    <div class="metric-card">
      <div class="metric-icon">🏠</div>
      <div>
        <div class="metric-num">${r.local||0}</div>
        <div class="metric-lbl">Órdenes locales</div>
      </div>
    </div>
    <div class="metric-card">
      <div class="metric-icon">🚚</div>
      <div>
        <div class="metric-num">${r.foraneo||0}</div>
        <div class="metric-lbl">Órdenes foráneas</div>
      </div>
    </div>
  </div>`;

  // ── Efectividad ──
  html += `<div class="efect-card">
    <div class="efect-header">
      <div class="efect-title">% Entrega a tiempo</div>
      <div class="efect-pct" style="color:${pctColor}">${pctTiempo}%</div>
    </div>
    <div class="efect-bar"><div class="efect-fill" style="width:${pctTiempo}%;background:${pctColor}"></div></div>
    <div class="efect-legend">
      <span><span class="leg-dot" style="background:#16a34a"></span>${r.a_tiempo||0} A tiempo</span>
      <span><span class="leg-dot" style="background:#dc2626"></span>${r.con_retraso||0} Con retraso</span>
      <span><span class="leg-dot" style="background:#d97706"></span>${r.en_proceso||0} En proceso</span>
    </div>
  </div>`;

  // ── Tabla concentrado mensual ──
  if (mensual.length > 0) {
    const totRow = mensual.find(m => m.es_total) || {};
    const filas  = mensual.filter(m => !m.es_total);
    html += `<div class="section-title">Concentrado mensual</div>
    <div class="table-card">
      <table>
        <thead><tr>
          <th>Mes</th><th>Total</th><th>Cerradas</th><th>Abiertas</th>
          <th>✅ A Tiempo</th><th>⚠️ Retraso</th><th>🔄 En Proceso</th>
          <th>% A Tiempo</th><th>Prom. Días</th><th>🏠 Local</th><th>🚚 Foráneo</th>
        </tr></thead>
        <tbody>
          ${filas.map(m => {
            const pct = m.total > 0 ? Math.round((m.a_tiempo / m.total) * 100) : 0;
            const cls = pct >= 80 ? 'pct-good' : pct >= 60 ? 'pct-warn' : 'pct-bad';
            return `<tr>
              <td>${m.mes_label}</td>
              <td>${m.total}</td>
              <td>${m.cerradas}</td>
              <td>${m.abiertas > 0 ? '<span class="badge-proceso">'+m.abiertas+'</span>' : '0'}</td>
              <td><span class="badge-tiempo">${m.a_tiempo}</span></td>
              <td>${m.con_retraso > 0 ? '<span class="badge-retraso">'+m.con_retraso+'</span>' : '0'}</td>
              <td>${m.en_proceso > 0 ? '<span class="badge-proceso">'+m.en_proceso+'</span>' : '0'}</td>
              <td class="${cls}">${pct}%</td>
              <td>${m.prom_dias ? parseFloat(m.prom_dias).toFixed(1) : '—'}</td>
              <td>${m.local||0}</td>
              <td>${m.foraneo||0}</td>
            </tr>`;
          }).join('')}
        </tbody>
        ${totRow.total ? `<tfoot><tr>
          <td>TOTAL</td>
          <td>${totRow.total}</td>
          <td>${totRow.cerradas}</td>
          <td>${totRow.abiertas}</td>
          <td>${totRow.a_tiempo}</td>
          <td>${totRow.con_retraso}</td>
          <td>${totRow.en_proceso}</td>
          <td>${totRow.total > 0 ? Math.round((totRow.a_tiempo/totRow.total)*100)+'%' : '—'}</td>
          <td>${totRow.prom_dias ? parseFloat(totRow.prom_dias).toFixed(1) : '—'}</td>
          <td>${totRow.local||0}</td>
          <td>${totRow.foraneo||0}</td>
        </tr></tfoot>` : ''}
      </table>
    </div>`;
  }

  // ── Órdenes activas ──
  if (abiertas.length > 0) {
    const sorted = [...abiertas].sort((a,b) => diasPara(a.fecha_entrega) - diasPara(b.fecha_entrega));
    html += `<div class="section-title">Órdenes activas (${abiertas.length})</div>
    <div class="ordenes-grid">
      ${sorted.map(o => {
        const dias = diasPara(o.fecha_entrega);
        const cls  = dias <= 0 ? 'urgente' : dias <= 2 ? 'alerta' : 'proceso';
        const pct  = parseInt(o.avance_pct || 0);
        const lbl  = dias <= 0 ? '🔴 Vencida' : dias <= 2 ? `🟡 ${dias} días` : `🟢 ${dias} días`;
        return `<a href="orden.php?folio=${encodeURIComponent(o.folio)}" class="orden-item ${cls}">
          <div class="oi-folio">${o.folio}${parseInt(o.prioridad)?'  ⚡':''}</div>
          <div class="oi-cliente">${o.cliente_nombre||'—'} · ${o.asesor||'—'}</div>
          <div class="oi-meta">
            <span style="font-weight:500">${lbl}</span>
            <span>${pct}% avance</span>
          </div>
          <div class="prog-bar"><div class="prog-fill" style="width:${pct}%"></div></div>
        </a>`;
      }).join('')}
    </div>`;
  }

  document.getElementById('main-content').innerHTML = html;
}

cargar();
setInterval(cargar, 60000);
</script>
</body>
</html>