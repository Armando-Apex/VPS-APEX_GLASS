<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
requirePermiso('ver_dashboard');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=resumen'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
.res-wrap { padding: 20px 24px; }
.stats-row { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 4px; }
.stat-card { background: #fff; border-radius: 12px; padding: 16px 18px; min-width: 110px; flex: 1;
  box-shadow: 0 1px 3px rgba(0,0,0,.06); border-top: 3px solid transparent; text-align: center; }
.stat-card .num { font-size: 28px; font-weight: 800; line-height: 1; }
.stat-card .lbl { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-top: 4px; }
h2.sec-title { margin: 0; font-size: 13px; }
.sec-wrap { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.06); margin-bottom: 16px; overflow: hidden; }
.sec-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #f1f5f9; }
.sec-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #1e293b; }
.sec-btn   { font-size: 12px; color: #2563eb; cursor: pointer; background: none; border: none; font-weight: 600; }
table { width: 100%; border-collapse: collapse; }
thead th { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; border-bottom: 1px solid #f1f5f9; }
tbody tr { border-bottom: 1px solid #f8fafc; cursor: pointer; transition: background .1s; }
tbody tr:hover { background: #f8fafc; }
tbody td { padding: 10px 14px; font-size: 13px; }
.td-folio { font-weight: 700; color: #2563eb; }
.prio-btn  { background: none; border: none; cursor: pointer; font-size: 14px; opacity: .3; transition: opacity .15s; }
.prio-btn.activo { opacity: 1; }
.prog-bar  { background: #e2e8f0; border-radius: 99px; height: 6px; overflow: hidden; margin-top: 4px; }
.prog-fill { height: 100%; border-radius: 99px; background: #2563eb; transition: width .5s; }
.badge-est { font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 99px; }
.movs-list { padding: 8px 0; }
.mov-item  { display: flex; gap: 10px; padding: 8px 18px; font-size: 12px; border-bottom: 1px solid #f8fafc; align-items: flex-start; }
.mov-dot   { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 4px; }
.mov-main  { font-weight: 600; color: #1e293b; }
.mov-sub   { color: #64748b; margin-top: 1px; }
.mov-time  { margin-left: auto; color: #94a3b8; font-size: 11px; white-space: nowrap; }
.pag-btns  { display: flex; gap: 6px; padding: 8px 14px; }
.pag-btn   { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; cursor: pointer; background: #f1f5f9; border: none; }
.pag-btn.activo { background: #2563eb; color: white; }
.loading-mod { padding: 32px; text-align: center; color: #94a3b8; font-size: 13px; }

@media(max-width:768px){
  .res-wrap { padding: 12px; }

  /* Stats: grid 4 columnas apretadas */
  .stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    overflow-x: visible;
    padding-bottom: 0;
    margin-bottom: 14px;
  }
  .stat-card { padding: 10px 8px; min-width: 0; border-radius: 10px; }
  .stat-card .num { font-size: 20px; }
  .stat-card .lbl { font-size: 9px; letter-spacing: 0; }

  /* Tabla: ocultar columnas Asesor, Estado, Piezas */
  thead th:nth-child(4),
  thead th:nth-child(6),
  thead th:nth-child(8),
  tbody td:nth-child(4),
  tbody td:nth-child(6),
  tbody td:nth-child(8) { display: none; }

  tbody td { padding: 9px 8px; font-size: 12px; }
  thead th  { padding: 8px 8px; font-size: 10px; }
  tbody td:nth-child(7) { min-width: 80px; }

  /* Actividad reciente */
  .mov-item { padding: 8px 12px; }
  .mov-time { font-size: 10px; }
  .sec-head { padding: 12px 14px; }
  .pag-btns { gap: 4px; }
  .pag-btn  { font-size: 11px; padding: 4px 8px; }
}
</style>

<div class="res-wrap">
  <div class="stats-row" id="res-stats">
    <div class="loading-mod">Cargando&#8230;</div>
  </div>

  <div class="sec-wrap">
    <div class="sec-head">
      <h2 class="sec-title">&#211;rdenes Activas</h2>
      <button class="sec-btn" onclick="irA('ordenes')">Ver todas &#8594;</button>
    </div>
    <table>
      <thead>
        <tr>
          <th></th><th>Folio</th><th>Cliente</th><th>Asesor</th>
          <th>Entrega</th><th>Estado</th><th>Progreso</th><th>Piezas</th>
        </tr>
      </thead>
      <tbody id="res-tabla">
        <tr><td colspan="8" class="loading-mod">Cargando&#8230;</td></tr>
      </tbody>
    </table>
    <div id="res-paginacion"></div>
  </div>

  <div class="sec-wrap">
    <div class="sec-head">
      <h2 class="sec-title">Actividad Reciente</h2>
      <div class="pag-btns">
        <button class="pag-btn activo" onclick="ModResumen.setPag(25)">25</button>
        <button class="pag-btn" onclick="ModResumen.setPag(50)">50</button>
        <button class="pag-btn" onclick="ModResumen.setPag(100)">100</button>
      </div>
    </div>
    <div class="movs-list" id="res-movs">
      <div class="loading-mod">Cargando&#8230;</div>
    </div>
  </div>
</div>

<script>
window.ModResumen = (function() {
  let _data = null;
  let _movsPag = 25;
  let _paginaActual = 1;
  let _totalPaginas = 1;
  let _timer = null;

  const COLORES = {
    pendiente:'#94a3b8', en_corte:'#f59e0b', canteado:'#60a5fa',
    trazo:'#a78bfa', taladro:'#c084fc', en_horno:'#f87171',
    terminado:'#34d399', entregado:'#10b981', reproceso:'#f97316'
  };
  const RES_LABELS = {
    pendiente:'Pendiente', en_corte:'En CNC', cortado:'Cortado',
    canteado:'Canteado', trazo:'Trazo', taladro:'Taladro',
    en_horno:'En Horno', terminado:'Terminado', entregado:'Entregado', reproceso:'Reproceso'
  };

  async function cargar() {
    try {
      const res  = await fetch('../api/dashboard.php?pagina='+_paginaActual+'&por_pagina=15');
      const data = await res.json();
      _data = data;
      if (data.paginacion) {
        _totalPaginas = data.paginacion.paginas || 1;
      }
      renderStats(data.totales || {});
      renderTabla(data.ordenes || []);
      renderMovs(data.movimientos || []);
      // Actualizar badge de vencidas en el sidebar
      const hoy  = new Date();
      const venc = (data.ordenes || []).filter(o =>
        o.fecha_entrega && new Date(o.fecha_entrega) < hoy && parseInt(o.avance_pct||0) < 100
      ).length;
      if (typeof window.actualizarBadge === 'function') window.actualizarBadge(venc);
    } catch(e) {
      document.getElementById('res-tabla').innerHTML =
        '<tr><td colspan="8" class="loading-mod" style="color:#dc2626">Error al cargar</td></tr>';
    }
  }

  function renderStats(t) {
    const stats = [
      { key:'pendiente', lbl:'Pendiente',  color:'#94a3b8' },
      { key:'en_corte',  lbl:'Corte',      color:'#f59e0b' },
      { key:'canteado',  lbl:'Canteado',   color:'#60a5fa' },
      { key:'trazo',     lbl:'Trazo/Tal',  color:'#a78bfa' },
      { key:'en_horno',  lbl:'Horno',      color:'#f87171' },
      { key:'terminado', lbl:'Terminado',  color:'#34d399' },
      { key:'entregado', lbl:'Entregado',  color:'#10b981' },
    ];
    document.getElementById('res-stats').innerHTML = stats.map(s =>
      `<div class="stat-card" style="border-top-color:${s.color}">
        <div class="num" style="color:${s.color}">${t[s.key]||0}</div>
        <div class="lbl">${s.lbl}</div>
      </div>`
    ).join('');
  }

  function renderTabla(ordenes) {
    // Filtrar — ocultar solo órdenes entregadas
    const lista = ordenes.filter(o => {
      return o.estado !== 'entregada';
    });
    if (!lista.length) {
      document.getElementById('res-tabla').innerHTML =
        '<tr><td colspan="8" class="loading-mod">No hay &#243;rdenes activas</td></tr>';
      renderPaginacion();
      return;
    }
    const hoy = new Date();
    const rows = lista.map(o => {
      const pct    = parseInt(o.avance_pct||0);
      const fecha  = o.fecha_entrega ? new Date(o.fecha_entrega+'T12:00:00') : null;
      const dias   = fecha ? Math.ceil((fecha-hoy)/86400000) : null;
      const esPrio = parseInt(o.prioridad||0) > 0;
      let colorF   = '#16a34a';
      if (dias !== null) { if (dias < 0) colorF='#dc2626'; else if (dias <= 2) colorF='#d97706'; }
      const fmtF   = fecha ? fecha.toLocaleDateString('es-MX',{day:'2-digit',month:'short'}) : '&#8212;';
      let badge    = pct===0 ? '<span class="badge-est" style="background:#f1f5f9;color:#64748b">Sin iniciar</span>'
                   : pct===100 ? '<span class="badge-est" style="background:#dcfce7;color:#15803d">&#10003; Lista</span>'
                   : '<span class="badge-est" style="background:#eff6ff;color:#1d4ed8">En proceso</span>';
      return `<tr onclick="irA('orden',{folio:'${o.folio}'})">
        <td><button class="prio-btn ${esPrio?'activo':''}" onclick="event.stopPropagation();ModResumen.prio('${o.folio}',this)">&#9889;</button></td>
        <td class="td-folio">${o.folio}</td>
        <td>${o.cliente_nombre||'&#8212;'}</td>
        <td style="color:#64748b">${o.asesor||'&#8212;'}</td>
        <td style="color:${colorF};font-weight:600">${fmtF}</td>
        <td>${badge}</td>
        <td style="min-width:120px">
          <div style="font-size:11px;color:#64748b">${pct}% &middot; ${o.piezas_listas||0}/${o.total_piezas||0} terminadas</div>
          <div class="prog-bar" title="${pct}% — ${o.piezas_listas||0} de ${o.total_piezas||0} piezas terminadas"><div class="prog-fill" style="width:${pct}%;background:${pct===100?'#16a34a':'#2563eb'}"></div></div>
        </td>
        <td style="color:#64748b">${o.total_piezas||0}</td>
      </tr>`;
    });
    document.getElementById('res-tabla').innerHTML = rows.join('');
    renderPaginacion();
  }

  function renderPaginacion() {
    const div = document.getElementById('res-paginacion');
    if (_totalPaginas <= 1) { div.innerHTML=''; return; }
    let html = '<div style="display:flex;gap:6px;padding:12px 14px;justify-content:center;border-top:1px solid #f1f5f9">';
    if (_paginaActual > 1)
      html += `<button onclick="ModResumen.irPagina(${_paginaActual-1})" style="padding:5px 12px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-size:12px">&#8592; Anterior</button>`;
    html += `<span style="padding:5px 12px;font-size:12px;color:#64748b">P&#225;g. ${_paginaActual} de ${_totalPaginas}</span>`;
    if (_paginaActual < _totalPaginas)
      html += `<button onclick="ModResumen.irPagina(${_paginaActual+1})" style="padding:5px 12px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-size:12px">Siguiente &#8594;</button>`;
    html += '</div>';
    div.innerHTML = html;
  }

  function renderMovs(movs) {
    const lista = movs.slice(0, _movsPag);
    if (!lista.length) { document.getElementById('res-movs').innerHTML='<div class="loading-mod">Sin actividad reciente</div>'; return; }
    document.getElementById('res-movs').innerHTML = lista.map(m => {
      const color = COLORES[m.estatus_nuevo] || '#94a3b8';
      const fecha = new Date(m.created_at);
      const fmtT  = fecha.toLocaleDateString('es-MX',{day:'2-digit',month:'short'}) + ', ' +
                    fecha.toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
      const lbl   = RES_LABELS[m.estatus_nuevo] || m.estatus_nuevo;
      return `<div class="mov-item">
        <div class="mov-dot" style="background:${color}"></div>
        <div style="flex:1">
          <div class="mov-main">${m.folio} P${m.partida} pieza ${m.pieza_num}/${m.pieza_total} &#8212; ${lbl}</div>
          <div class="mov-sub">${m.cristal||''} ${m.ancho_mm||''}&#215;${m.alto_mm||''}mm &middot; ${m.cliente_nombre||''} &middot; ${m.usuario_nombre||''}</div>
        </div>
        <div class="mov-time">${fmtT}</div>
      </div>`;
    }).join('');
  }

  async function prio(folio, btn) {
    const activo = btn.classList.contains('activo');
    btn.classList.toggle('activo');
    try {
      await fetch('../api/prioridad.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({folio, prioridad: activo?0:1})
      });
    } catch(e) { btn.classList.toggle('activo'); }
  }

  function setPag(n) {
    _movsPag = n;
    document.querySelectorAll('.pag-btn').forEach(b => b.classList.remove('activo'));
    event.target.classList.add('activo');
    if (_data) renderMovs(_data.movimientos || []);
  }

  function irPagina(p) {
    _paginaActual = p;
    cargar();
  }

  function destroy() {
    if (_timer) clearInterval(_timer);
  }

  // Auto-refresh cada 60s
  _timer = setInterval(cargar, 60000);

  return { init: cargar, destroy, prio, setPag, irPagina };
})();

ModResumen.init();
</script>