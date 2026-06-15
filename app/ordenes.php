<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
requirePermiso('ver_dashboard');
$_rol = $_SESSION['user_rol'] ?? '';
$_nombre = $_SESSION['user_nombre'] ?? 'U';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APEX GLASS — Órdenes</title>
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8f9fa; color: #1a1a1a; }

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
.avatar {
  width: 28px; height: 28px; border-radius: 50%;
  background: #dcfce7; color: #166534;
  font-size: 11px; font-weight: 500;
  display: flex; align-items: center; justify-content: center;
}
.btn-salir { font-size: 12px; color: #9ca3af; text-decoration: none; }
.btn-refresh {
  background: none; border: 0.5px solid #e5e7eb;
  border-radius: 8px; padding: 6px 12px;
  font-size: 12px; color: #6b7280; cursor: pointer;
}
.btn-refresh:hover { background: #f3f4f6; }

/* ── Layout ── */
.layout { display: flex; flex: 1; min-height: calc(100vh - 52px); }

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
.main { flex: 1; padding: 24px; max-width: 1100px; }
.page-title { font-size: 18px; font-weight: 500; color: #1a1a1a; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #9ca3af; margin-bottom: 20px; }

/* ── Tabs ── */
.tabs { display: flex; gap: 2px; margin-bottom: 20px; background: #f3f4f6; padding: 3px; border-radius: 10px; width: fit-content; }
.tab-btn {
  padding: 7px 18px; border-radius: 8px; border: none;
  font-size: 13px; cursor: pointer; font-weight: 500;
  background: none; color: #6b7280; transition: all .15s;
  display: flex; align-items: center; gap: 6px;
}
.tab-btn.active { background: #fff; color: #1a1a1a; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.tab-cnt {
  font-size: 11px; font-weight: 600; padding: 1px 7px;
  border-radius: 99px; background: #e5e7eb; color: #6b7280;
}
.tab-btn.active .tab-cnt { background: #dcfce7; color: #166534; }

/* ── Sección ── */
.seccion { display: none; }
.seccion.visible { display: block; }

/* ── Card orden ── */
.orden-card {
  background: #fff; border: 0.5px solid #e5e7eb;
  border-radius: 12px; margin-bottom: 10px; overflow: hidden;
}
.orden-card.prioritaria { border-left: 3px solid #f59e0b; }
.orden-head {
  display: flex; align-items: center; gap: 12px;
  padding: 13px 18px; cursor: pointer;
  transition: background .1s;
}
.orden-head:hover { background: #fafafa; }
.orden-folio { font-size: 14px; font-weight: 500; color: #2563eb; min-width: 90px; text-decoration: none; }
.orden-folio:hover { text-decoration: underline; }
.orden-cliente { font-size: 13px; color: #374151; flex: 1; }
.orden-asesor  { font-size: 12px; color: #9ca3af; min-width: 100px; }
.orden-fecha   { font-size: 12px; font-weight: 500; min-width: 80px; text-align: right; }
.badge-prio { font-size: 10px; font-weight: 500; background: #fef9c3; color: #854d0e; padding: 2px 7px; border-radius: 99px; }
.pzs-badge  { font-size: 12px; font-weight: 500; padding: 3px 10px; border-radius: 99px; min-width: 70px; text-align: center; }
.pzs-pend   { background: #fee2e2; color: #991b1b; }
.pzs-term   { background: #dcfce7; color: #166534; }
.pzs-entr   { background: #d1fae5; color: #065f46; }
.toggle-icon { font-size: 12px; color: #9ca3af; margin-left: 4px; transition: transform .2s; }
.toggle-icon.open { transform: rotate(180deg); }

/* ── Partidas expandibles ── */
.partidas-wrap { display: none; border-top: 0.5px solid #f3f4f6; }
.partidas-wrap.open { display: block; }
.partida-row {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 18px 10px 36px;
  border-bottom: 0.5px solid #f9fafb; font-size: 12px;
}
.partida-row:last-child { border-bottom: none; }
.partida-num  { font-weight: 500; color: #374151; min-width: 60px; }
.partida-desc { color: #6b7280; flex: 1; }
.partida-pzs  { font-weight: 500; color: #991b1b; background: #fee2e2; padding: 2px 8px; border-radius: 99px; font-size: 11px; }

/* ── Entregadas ── */
.entr-row {
  display: flex; align-items: center; gap: 12px;
  padding: 13px 18px;
  border-bottom: 0.5px solid #f9fafb; font-size: 13px;
}
.entr-row:last-child { border-bottom: none; }
.entr-folio   { font-size: 14px; font-weight: 500; color: #2563eb; min-width: 90px; text-decoration: none; }
.entr-folio:hover { text-decoration: underline; }
.entr-cliente { color: #374151; flex: 1; }
.entr-pzs     { font-size: 12px; color: #6b7280; min-width: 80px; }
.entr-fecha   { font-size: 12px; color: #6b7280; min-width: 100px; text-align: right; }
.cierre-pill  { font-size: 11px; font-weight: 500; padding: 2px 9px; border-radius: 99px; }
.cierre-ok    { background: #dcfce7; color: #166534; }
.cierre-parcial { background: #fef9c3; color: #854d0e; }

.card-empty { text-align: center; padding: 48px; color: #9ca3af; font-size: 14px; }
.search-sort-bar {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 14px; flex-wrap: wrap;
}
#busqueda {
  flex: 1; min-width: 200px;
  background: #fff; border: 0.5px solid #e5e7eb;
  border-radius: 8px; padding: 8px 14px;
  font-size: 13px; color: #1a1a1a; outline: none;
}
#busqueda:focus { border-color: #16a34a; }
.sort-btns { display: flex; gap: 6px; flex-wrap: wrap; }
.sort-btn {
  background: #fff; border: 0.5px solid #e5e7eb;
  border-radius: 8px; padding: 7px 12px;
  font-size: 12px; color: #6b7280; cursor: pointer;
  transition: all .15s; display: flex; align-items: center; gap: 4px;
}
.sort-btn:hover { background: #f3f4f6; color: #1a1a1a; }
.sort-btn.active { background: #f0fdf4; color: #166534; border-color: #bbf7d0; font-weight: 500; }
.sort-arrow { font-size: 11px; }
.loading-msg { text-align: center; padding: 48px; color: #9ca3af; font-size: 14px; }

@media (max-width: 768px) {
  .sidebar { display: none; }
  .main { padding: 16px; }
  .orden-asesor { display: none; }
}
</style>
</head>
<body>

<div class="topbar">
  <a href="dashboard.php" class="topbar-logo">APEX GLASS</a>
  <nav class="topbar-nav">
    <a href="dashboard.php" class="nav-item">◻ Dashboard</a>
    <a href="ordenes.php"   class="nav-item active">◻ Órdenes</a>
    <?php if (in_array($_rol, ['director','dir_admin','dueno','administracion'])): ?>
    <a href="reporte_direccion.php" class="nav-item">◻ Reporte</a>
    <?php endif; ?>
    <a href="productividad.php" class="nav-item">◻ Productividad</a>
  </nav>
  <div class="topbar-right">
    <button class="btn-refresh" onclick="cargar()">↻ Actualizar</button>
    <div class="avatar"><?php echo strtoupper(substr($_nombre, 0, 2)); ?></div>
    <a href="../api/logout.php?redirect=login.php" class="btn-salir">Salir</a>
  </div>
</div>

<div class="layout">
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
      <a href="ordenes.php" class="sb-item active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Órdenes
      </a>
    </div>
    <?php if (in_array($_rol, ['director','dir_admin','dueno','administracion'])): ?>
    <div class="sb-section">
      <div class="sb-label">Reportes</div>
      <a href="reporte_direccion.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
        Dirección
      </a>
      <a href="productividad.php" class="sb-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Productividad
      </a>
    </div>
    <?php endif; ?>
  </aside>

  <main class="main">
    <div class="page-title">Órdenes</div>
    <div class="page-sub" id="page-sub">Cargando...</div>

    <div class="search-sort-bar">
      <input type="text" id="busqueda" placeholder="🔍 Buscar por cliente u orden..." 
        oninput="filtrarYOrdenar()" autocomplete="off">
      <div class="sort-btns">
        <button class="sort-btn active" id="sort-folio" onclick="setSort('folio')" title="Ordenar por folio">
          # Folio <span class="sort-arrow" id="arrow-folio">↑</span>
        </button>
        <button class="sort-btn" id="sort-cliente" onclick="setSort('cliente')" title="Ordenar por cliente">
          A-Z Cliente <span class="sort-arrow" id="arrow-cliente">↑</span>
        </button>
        <button class="sort-btn" id="sort-fecha" onclick="setSort('fecha')" title="Ordenar por fecha">
          📅 Fecha <span class="sort-arrow" id="arrow-fecha">↑</span>
        </button>
      </div>
    </div>

    <div class="tabs">
      <button class="tab-btn active" onclick="setTab('por_iniciar')">
        Por iniciar <span class="tab-cnt" id="cnt-por_iniciar">—</span>
      </button>
      <button class="tab-btn" onclick="setTab('en_proceso')">
        En proceso <span class="tab-cnt" id="cnt-en_proceso">—</span>
      </button>
      <button class="tab-btn" onclick="setTab('listas')">
        Listas para entregar <span class="tab-cnt" id="cnt-listas">—</span>
      </button>
      <button class="tab-btn" onclick="setTab('entregadas')">
        Entregadas <span class="tab-cnt" id="cnt-entregadas">—</span>
      </button>
    </div>

    <div id="seccion-por_iniciar" class="seccion visible">
      <div class="loading-msg">Cargando...</div>
    </div>
    <div id="seccion-en_proceso" class="seccion">
      <div class="loading-msg">Cargando...</div>
    </div>
    <div id="seccion-listas" class="seccion">
      <div class="loading-msg">Cargando...</div>
    </div>
    <div id="seccion-entregadas" class="seccion">
      <div class="loading-msg">Cargando...</div>
    </div>
  </main>
</div>

<script>
let _tabActivo  = 'por_iniciar';
let _sortField  = 'folio';
let _sortDir    = 'asc';
let _busqueda   = '';
let _dataRaw    = {};

function setSort(field) {
  if (_sortField === field) {
    _sortDir = _sortDir === 'asc' ? 'desc' : 'asc';
  } else {
    _sortField = field;
    _sortDir   = 'asc';
  }
  document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('sort-' + field).classList.add('active');
  ['folio','cliente','fecha'].forEach(f => {
    const arrow = document.getElementById('arrow-' + f);
    if (arrow) arrow.textContent = (_sortField === f) ? (_sortDir === 'asc' ? '↑' : '↓') : '↑';
  });
  filtrarYOrdenar();
}

function filtrarYOrdenar() {
  _busqueda = (document.getElementById('busqueda')?.value || '').toLowerCase().trim();

  function matchBusqueda(o) {
    if (!_busqueda) return true;
    return (o.folio         || '').toLowerCase().includes(_busqueda) ||
           (o.cliente_nombre|| '').toLowerCase().includes(_busqueda);
  }

  function sortFn(a, b) {
    let va, vb;
    if (_sortField === 'folio') {
      va = (a.folio||'').toLowerCase();
      vb = (b.folio||'').toLowerCase();
    } else if (_sortField === 'cliente') {
      va = (a.cliente_nombre||'').toLowerCase();
      vb = (b.cliente_nombre||'').toLowerCase();
    } else {
      va = a.fecha_entrega || '9999';
      vb = b.fecha_entrega || '9999';
    }
    if (va < vb) return _sortDir === 'asc' ? -1 : 1;
    if (va > vb) return _sortDir === 'asc' ? 1 : -1;
    return 0;
  }

  const pi = (_dataRaw.por_iniciar || []).filter(matchBusqueda).sort(sortFn);
  const ep = (_dataRaw.en_proceso  || []).filter(matchBusqueda).sort(sortFn);
  const li = (_dataRaw.listas      || []).filter(matchBusqueda).sort(sortFn);
  const en = (_dataRaw.entregadas  || []).filter(o => {
    if (!_busqueda) return true;
    return (o.folio||'').toLowerCase().includes(_busqueda) ||
           (o.cliente_nombre||'').toLowerCase().includes(_busqueda);
  }).sort((a,b) => {
    // Entregadas ordena por ultima_entrega por defecto
    if (_sortField === 'fecha') {
      const va = a.ultima_entrega||'';
      const vb = b.ultima_entrega||'';
      if (va < vb) return _sortDir === 'asc' ? 1 : -1;
      if (va > vb) return _sortDir === 'asc' ? -1 : 1;
    }
    return sortFn(a, b);
  });

  renderPorIniciar(pi);
  renderEnProceso(ep);
  renderListas(li);
  renderEntregadas(en);
}

function setTab(tab) {
  _tabActivo = tab;
  document.querySelectorAll('.tab-btn').forEach((b, i) => {
    const tabs = ['por_iniciar','listas','entregadas'];
    b.classList.toggle('active', tabs[i] === tab);
  });
  document.querySelectorAll('.seccion').forEach(s => s.classList.remove('visible'));
  document.getElementById('seccion-' + tab).classList.add('visible');
}

function fmtFecha(f) {
  if (!f) return '—';
  // Normalizar: reemplazar espacio por T para compatibilidad
  const iso = f.replace(' ', 'T');
  // Si es solo fecha (YYYY-MM-DD) agregar mediodía para evitar desfase por zona horaria
  const fecha = iso.includes('T') ? iso : iso + 'T12:00:00';
  const d = new Date(fecha);
  if (isNaN(d)) return '—';
  return d.toLocaleDateString('es-MX', {day:'2-digit', month:'short', year:'numeric'});
}

function colorFecha(f) {
  if (!f) return '#9ca3af';
  const dias = Math.ceil((new Date(f) - new Date()) / 86400000);
  if (dias <= 0)      return '#dc2626';
  if (dias <= 2)      return '#d97706';
  return '#16a34a';
}

function togglePartidas(folio) {
  const wrap = document.getElementById('partidas-' + folio);
  const icon = document.getElementById('icon-' + folio);
  if (!wrap) return;
  wrap.classList.toggle('open');
  icon.classList.toggle('open');
}

async function cargar() {
  try {
    const res  = await fetch('../api/ordenes.php?t=' + Date.now());
    const data = await res.json();
    _dataRaw = {
      por_iniciar: data.por_iniciar || [],
      en_proceso:  data.en_proceso  || [],
      listas:      data.listas      || [],
      entregadas:  data.entregadas  || [],
    };
    filtrarYOrdenar();
    document.getElementById('page-sub').textContent =
      'Actualizado a las ' + new Date().toLocaleTimeString('es-MX', {hour:'2-digit', minute:'2-digit'});
  } catch(e) {
    document.getElementById('page-sub').textContent = 'Error al cargar';
  }
}

function renderPorIniciar(list) {
  document.getElementById('cnt-por_iniciar').textContent = list.length;
  if (!list.length) {
    document.getElementById('seccion-por_iniciar').innerHTML =
      '<div class="card-empty">✅ No hay órdenes con piezas pendientes de corte</div>';
    return;
  }
  document.getElementById('seccion-por_iniciar').innerHTML = list.map(o => {
    const esPrio = parseInt(o.prioridad) === 1;
    const ptidasHtml = o.partidas.map(p => `
      <div class="partida-row">
        <span class="partida-num">Partida ${p.partida}</span>
        <span class="partida-desc">${p.cristal} · ${p.ancho_mm}×${p.alto_mm} mm</span>
        <span class="partida-pzs">${p.piezas_pendientes} pz pendientes</span>
      </div>`).join('');
    return `
      <div class="orden-card ${esPrio?'prioritaria':''}">
        <div class="orden-head" onclick="togglePartidas('${o.folio}')">
          <a class="orden-folio" href="orden.php?folio=${encodeURIComponent(o.folio)}"
             onclick="event.stopPropagation()">${o.folio}</a>
          ${esPrio ? '<span class="badge-prio">⚡ Prior.</span>' : ''}
          <span class="orden-cliente">${o.cliente_nombre||'—'}</span>
          <span class="orden-asesor">${o.asesor||'—'}</span>
          <span class="orden-fecha" style="color:${colorFecha(o.fecha_entrega)}">${fmtFecha(o.fecha_entrega)}</span>
          <span class="pzs-badge pzs-pend">${o.total_pendientes} pz</span>
          <span class="toggle-icon" id="icon-${o.folio}">▼</span>
        </div>
        <div class="partidas-wrap" id="partidas-${o.folio}">${ptidasHtml}</div>
      </div>`;
  }).join('');
}

function renderEnProceso(list) {
  document.getElementById('cnt-en_proceso').textContent = list.length;
  const el = document.getElementById('seccion-en_proceso');
  if (!list.length) {
    el.innerHTML = '<div class="card-empty">No hay órdenes en proceso</div>';
    return;
  }
  let html = '<div class="orden-card">';
  list.forEach(o => {
    const esPrio = parseInt(o.prioridad) === 1;
    const pct    = parseInt(o.avance_pct || 0);
    const total  = parseInt(o.total_piezas || 0);
    const partes = [];
    if (o.pendientes > 0) partes.push(o.pendientes + ' pend.');
    if (o.en_corte   > 0) partes.push(o.en_corte   + ' corte');
    if (o.canteadas  > 0) partes.push(o.canteadas  + ' cant.');
    const traz = parseInt(o.trazo||0) + parseInt(o.taladro||0);
    if (traz > 0) partes.push(traz + ' traz.');
    if (o.en_horno   > 0) partes.push(o.en_horno   + ' horno');
    if (o.terminadas > 0) partes.push(o.terminadas + ' term.');
    const resumen = partes.join(' · ');
    html += '<div class="entr-row">' +
      '<a class="entr-folio" href="orden.php?folio=' + encodeURIComponent(o.folio) + '">' + o.folio + '</a>' +
      (esPrio ? '<span class="badge-prio">⚡</span>' : '') +
      '<span class="entr-cliente">' + (o.cliente_nombre||'—') + '</span>' +
      '<span class="orden-asesor">' + (o.asesor||'—') + '</span>' +
      '<span class="entr-fecha" style="color:' + colorFecha(o.fecha_entrega) + '">' + fmtFecha(o.fecha_entrega) + '</span>' +
      '<span style="font-size:12px;color:#9ca3af;min-width:160px">' + resumen + '</span>' +
      '<span class="pzs-badge pzs-term">' + pct + '% · ' + (o.terminadas||0) + '/' + total + '</span>' +
    '</div>';
  });
  html += '</div>';
  el.innerHTML = html;
}

function renderListas(list) {
  document.getElementById('cnt-listas').textContent = list.length;
  if (!list.length) {
    document.getElementById('seccion-listas').innerHTML =
      '<div class="card-empty">No hay órdenes listas para entregar en este momento</div>';
    return;
  }
  document.getElementById('seccion-listas').innerHTML = `
    <div class="orden-card">
    ${list.map(o => {
      const esPrio = parseInt(o.prioridad) === 1;
      return `<div class="entr-row">
        <a class="entr-folio" href="orden.php?folio=${encodeURIComponent(o.folio)}">${o.folio}</a>
        ${esPrio ? '<span class="badge-prio">⚡</span>' : ''}
        <span class="entr-cliente">${o.cliente_nombre||'—'}</span>
        <span class="orden-asesor">${o.asesor||'—'}</span>
        <span class="entr-fecha" style="color:${colorFecha(o.fecha_entrega)}">${fmtFecha(o.fecha_entrega)}</span>
        <span class="pzs-badge pzs-term">${o.terminadas} pz listas</span>
      </div>`;
    }).join('')}
    </div>`;
}

function renderEntregadas(list) {
  document.getElementById('cnt-entregadas').textContent = list.length;
  if (!list.length) {
    document.getElementById('seccion-entregadas').innerHTML =
      '<div class="card-empty">No hay piezas entregadas aún</div>';
    return;
  }
  document.getElementById('seccion-entregadas').innerHTML = `
    <div class="orden-card">
    ${list.map(o => {
      const totalEntr = parseInt(o.piezas_entregadas);
      const total     = parseInt(o.total_piezas);
      const completa  = totalEntr >= total;
      const fechaEntr = fmtFecha(o.ultima_entrega);
      const fechaCierre = o.fecha_cierre ? fmtFecha(o.fecha_cierre) : null;
      return `<div class="entr-row">
        <a class="entr-folio" href="orden.php?folio=${encodeURIComponent(o.folio)}">${o.folio}</a>
        <span class="entr-cliente">${o.cliente_nombre||'—'}</span>
        <span class="entr-pzs">${totalEntr} de ${total} pzs</span>
        <span class="entr-fecha">Últ. entrega: ${fechaEntr}</span>
        <span class="cierre-pill ${completa?'cierre-ok':'cierre-parcial'}">
          ${completa ? (fechaCierre ? '✅ Cierre: '+fechaCierre : '✅ Completa') : '⏳ Parcial'}
        </span>
      </div>`;
    }).join('')}
    </div>`;
}

cargar();
setInterval(cargar, 60000);
</script>
</body>
</html>