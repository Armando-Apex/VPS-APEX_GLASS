<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_reportes');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=reporte_direccion'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:       #f1f5f9;
  --surface:  #ffffff;
  --border:   #e2e8f0;
  --border-lt:#f1f5f9;
  --text:     #1e293b;
  --muted:    #64748b;
  --muted-lt: #94a3b8;
  --accent:   #f59e0b;
  --blue:     #2563eb;
  --blue-lt:  #eff6ff;
  --green:    #16a34a;
  --green-lt: #dcfce7;
  --red:      #dc2626;
  --red-lt:   #fee2e2;
  --amber:    #d97706;
  --amber-lt: #fef3c7;
  --purple:   #7c3aed;
  --purple-lt:#ede9fe;
}
.rd-wrap {
  padding: 20px 24px;
  background: var(--bg);
  min-height: 100%;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
}
.rd-toolbar { display:flex; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.rd-toolbar label { font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.6px; }
.rd-toolbar select {
  padding:6px 10px; border:1px solid var(--border); border-radius:6px;
  font-size:13px; background:var(--surface); color:var(--text);
  outline:none; cursor:pointer; transition:border-color .15s;
}
.rd-toolbar select:focus { border-color:var(--blue); }
.live-dot { display:inline-block; width:7px; height:7px; border-radius:50%; background:#22c55e; margin-right:5px; animation:pulse 2.5s infinite; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.3} }
.ts-label { font-size:11px; color:var(--muted-lt); display:flex; align-items:center; }
.loading { text-align:center; padding:60px; color:var(--muted); font-size:14px; }
.spin { width:30px; height:30px; border:2px solid var(--border); border-top-color:var(--blue); border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 12px; }
@keyframes spin { to{transform:rotate(360deg)} }
.section-title {
  font-size:10px; font-weight:700; color:var(--muted);
  text-transform:uppercase; letter-spacing:.8px;
  margin:20px 0 10px; padding-bottom:8px;
  border-bottom:1px solid var(--border);
}
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:14px; }
.kpi-card {
  background:var(--surface); border-radius:10px; padding:16px 18px;
  box-shadow:0 1px 3px rgba(0,0,0,.05), 0 0 0 1px rgba(0,0,0,.04);
}
.kpi-num   { font-size:34px; font-weight:800; line-height:1; margin-bottom:3px; color:var(--text); }
.kpi-num-sm{ font-size:22px; font-weight:800; line-height:1; margin-bottom:3px; color:var(--text); }
.kpi-label { font-size:10px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; }
.kpi-sub   { font-size:11px; color:var(--muted-lt); margin-top:4px; }
.metrics-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:14px; }
.metric-card {
  background:var(--surface); border-radius:10px; padding:14px 16px;
  box-shadow:0 1px 3px rgba(0,0,0,.05), 0 0 0 1px rgba(0,0,0,.04);
}
.metric-num { font-size:22px; font-weight:800; line-height:1; color:var(--text); }
.metric-lbl { font-size:10px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-top:3px; }
.metric-sub { font-size:10px; color:var(--muted-lt); margin-top:2px; }
.efectividad-card {
  background:var(--surface); border-radius:10px; padding:16px 20px;
  box-shadow:0 1px 3px rgba(0,0,0,.05), 0 0 0 1px rgba(0,0,0,.04);
  margin-bottom:14px;
}
.efect-header { display:flex; justify-content:space-between; align-items:baseline; margin-bottom:10px; }
.efect-title  { font-size:10px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.6px; }
.efect-pct    { font-size:26px; font-weight:800; }
.efect-bar    { background:var(--border); border-radius:3px; height:7px; overflow:hidden; margin-bottom:10px; display:flex; }
.efect-legend { display:flex; gap:16px; font-size:11px; color:var(--muted); flex-wrap:wrap; }
.leg-dot { display:inline-block; width:7px; height:7px; border-radius:50%; margin-right:4px; }
.table-card {
  background:var(--surface); border-radius:10px;
  box-shadow:0 1px 3px rgba(0,0,0,.05), 0 0 0 1px rgba(0,0,0,.04);
  margin-bottom:14px; overflow:hidden; overflow-x:auto;
}
table { width:100%; border-collapse:collapse; }
thead tr { background:var(--bg); }
thead th {
  padding:9px 14px; font-size:10px; font-weight:700;
  color:var(--muted); text-align:left; letter-spacing:.5px;
  text-transform:uppercase; white-space:nowrap;
  border-bottom:1px solid var(--border);
}
tbody tr { border-bottom:1px solid var(--border-lt); transition:background .1s; }
tbody tr:hover { background:#fafbfc; }
tbody td { padding:9px 14px; font-size:13px; color:var(--text); }
tfoot tr { background:var(--bg); font-weight:700; border-top:1px solid var(--border); }
tfoot td { padding:9px 14px; font-size:13px; }
.badge-tiempo  { background:var(--green-lt); color:#14532d; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600; }
.badge-retraso { background:var(--red-lt);   color:#991b1b; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600; }
.badge-proceso { background:var(--amber-lt); color:#78350f; padding:2px 7px; border-radius:4px; font-size:11px; font-weight:600; }
.pct-good { color:var(--green); font-weight:700; }
.pct-warn { color:var(--amber); font-weight:700; }
.pct-bad  { color:var(--red);   font-weight:700; }
.ordenes-row { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:8px; margin-bottom:14px; }
.orden-item {
  background:var(--surface); border-radius:10px; padding:12px 14px;
  box-shadow:0 1px 3px rgba(0,0,0,.05), 0 0 0 1px rgba(0,0,0,.04);
  cursor:pointer; text-decoration:none; color:inherit;
  border-left:3px solid var(--border); transition:box-shadow .15s; display:block;
}
.orden-item:hover { box-shadow:0 4px 12px rgba(0,0,0,.08); }
.orden-item.urgente { border-left-color:var(--red); }
.orden-item.alerta  { border-left-color:var(--amber); }
.orden-item.proceso { border-left-color:var(--green); }
.oi-folio   { font-size:14px; font-weight:700; color:var(--blue); margin-bottom:3px; }
.oi-cliente { font-size:12px; color:var(--muted); margin-bottom:5px; }
.oi-meta    { display:flex; justify-content:space-between; font-size:11px; color:var(--muted-lt); }
@media(max-width:1200px){ .kpi-grid{grid-template-columns:repeat(3,1fr) !important;} }
@media(max-width:900px) { .kpi-grid{grid-template-columns:repeat(2,1fr) !important;} }
@media(max-width:700px) { .kpi-grid{grid-template-columns:repeat(2,1fr) !important;} .metrics-grid{grid-template-columns:repeat(2,1fr) !important;} }
.inv-row-grupo { background:var(--bg); cursor:pointer; font-weight:600; }
.inv-row-grupo:hover { background:#e2e8f0; }
.rd-panel-header {
  padding:9px 14px;
  background:var(--bg);
  color:var(--muted);
  font-weight:700;
  font-size:10px;
  letter-spacing:.6px;
  text-transform:uppercase;
  border-bottom:1px solid var(--border);
}
.rd-tabs { display:flex; gap:4px; margin-bottom:16px; border-bottom:1px solid var(--border); }
.rd-tab {
  background:none; border:none; padding:10px 16px; font-size:13px; font-weight:600;
  color:var(--muted); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px;
  transition:color .15s, border-color .15s;
}
.rd-tab:hover  { color:var(--text); }
.rd-tab.active { color:var(--blue); border-bottom-color:var(--blue); }
.rv-toolbar { justify-content:space-between; }
.rv-toggle  { display:flex; background:var(--bg); border-radius:8px; padding:3px; gap:2px; }
.rv-toggle button {
  border:none; background:none; padding:6px 14px; font-size:12px; font-weight:600;
  color:var(--muted); border-radius:6px; cursor:pointer; transition:all .15s;
}
.rv-toggle button.active { background:var(--surface); color:var(--blue); box-shadow:0 1px 2px rgba(0,0,0,.08); }
.rv-nav-btn {
  border:1px solid var(--border); background:var(--surface); width:30px; height:30px;
  border-radius:6px; cursor:pointer; font-size:16px; color:var(--muted); display:flex;
  align-items:center; justify-content:center; transition:background .15s;
}
.rv-nav-btn:hover { background:var(--bg); }
.rv-label   { font-size:13px; font-weight:700; color:var(--text); min-width:200px; text-align:center; }
.rv-hoy-btn {
  border:1px solid var(--border); background:var(--surface); padding:6px 12px; border-radius:6px;
  font-size:12px; font-weight:600; color:var(--blue); cursor:pointer;
}
.rv-hoy-btn:hover { background:var(--blue-lt); }
</style>

<div class="rd-wrap">
  <div class="rd-tabs">
    <button type="button" class="rd-tab active" data-tab="resumen" onclick="rdTabSwitch('resumen')">Resumen</button>
    <button type="button" class="rd-tab" data-tab="ventas" onclick="rdTabSwitch('ventas')">Ventas y Cobranza</button>
  </div>

  <div id="rdPanelResumen">
    <div class="rd-toolbar">
      <label>Per&#237;odo</label>
      <select id="rdFiltro" onchange="rdCargar()">
        <option value="mes_actual">Este mes</option>
        <option value="mes_anterior">Mes anterior</option>
        <option value="3meses">&#218;ltimos 3 meses</option>
        <option value="6meses">&#218;ltimos 6 meses</option>
        <option value="a&#241;o">Este a&#241;o</option>
        <option value="todo">Todo el historial</option>
      </select>
      <div class="ts-label"><span class="live-dot"></span><span id="rdTs">Cargando&#8230;</span></div>
    </div>
    <div id="rdMain"><div class="loading"><div class="spin"></div>Cargando reporte&#8230;</div></div>
  </div>

  <div id="rdPanelVentas" style="display:none">
    <div class="rd-toolbar rv-toolbar">
      <div class="rv-toggle">
        <button type="button" data-g="dia" class="active" onclick="rvSetGran('dia')">D&#237;a</button>
        <button type="button" data-g="semana" onclick="rvSetGran('semana')">Semana</button>
        <button type="button" data-g="mes" onclick="rvSetGran('mes')">Mes</button>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <button type="button" class="rv-nav-btn" onclick="rvNav(-1)">&#8249;</button>
        <span class="rv-label" id="rvLabel">&#8212;</span>
        <button type="button" class="rv-nav-btn" onclick="rvNav(1)">&#8250;</button>
      </div>
      <button type="button" class="rv-hoy-btn" onclick="rvHoy()">Hoy</button>
    </div>
    <div id="rvMain"><div class="loading"><div class="spin"></div>Cargando&#8230;</div></div>
  </div>
</div>

<script>
window.ModReporte=(function(){

async function rdCargar() {
  var periodo = document.getElementById('rdFiltro').value;
  document.getElementById('rdMain').innerHTML = '<div class="loading"><div class="spin"></div>Cargando reporte&#8230;</div>';
  try {
    const [r1, r2, r3, r4] = await Promise.all([
      fetch('../api/reporte_direccion.php?periodo=' + periodo),
      fetch('../api/dashboard.php'),
      fetch('../api/inventario.php?accion=costo_promedio'),
      fetch('../api/reporte_direccion.php?accion=efectividad_corte&periodo=' + periodo)
    ]);
    const rep  = await r1.json();
    const dash = await r2.json();
    const inv  = await r3.json().catch(function(){ return {}; });
    const ef   = await r4.json().catch(function(){ return {}; });
    if (rep.error) {
      document.getElementById('rdMain').innerHTML = '<div class="loading" style="color:#dc2626">Error: ' + rep.error + '</div>';
      return;
    }
    rdRender(rep, dash, inv, ef);
    document.getElementById('rdTs').textContent = 'Actualizado ' + new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  } catch(e) {
    document.getElementById('rdMain').innerHTML = '<div class="loading" style="color:#dc2626">Error de conexi&#243;n</div>';
  }
}

function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

function rdFmtFecha(ts) {
  if (!ts) return '&#8212;';
  var d = new Date(ts.includes('T') ? ts : ts + 'T12:00:00');
  return d.toLocaleDateString('es-MX', {day:'2-digit', month:'short', year:'numeric'});
}
function rdDias(fecha) {
  if (!fecha) return 99;
  var f = fecha.includes('T') ? new Date(fecha) : new Date(fecha + 'T12:00:00');
  return Math.ceil((f - new Date()) / 86400000);
}
// fmtMXN — definida en utils.js

function mkTopPanel(titulo, lista, valFn) {
  var rows = '';
  if (lista.length === 0) {
    rows = '<tr><td colspan="3" style="text-align:center;color:var(--muted-lt);padding:16px;font-size:12px">Sin datos en el per&#237;odo</td></tr>';
  } else {
    lista.forEach(function(cl, i) {
      var rank = (i + 1) + '.';
      rows += '<tr>' +
        '<td style="text-align:center;font-size:12px;width:32px;color:var(--muted-lt);font-weight:700">' + rank + '</td>' +
        '<td style="font-size:12px;padding:8px 10px"><strong>' + esc(cl.nombre) + '</strong></td>' +
        '<td style="text-align:right;white-space:nowrap;padding:8px 10px">' + valFn(cl) + '</td></tr>';
    });
  }
  return '<div class="table-card" style="margin-bottom:0">' +
    '<div class="rd-panel-header">' + titulo + '</div>' +
    '<table><tbody>' + rows + '</tbody></table></div>';
}

function rdRender(rep, dash, inv, ef) {
  var r           = rep.resumen      || {};
  var fin         = rep.finanzas     || {};
  var cot         = rep.cots_resumen || {};
  var mensual     = rep.mensual      || [];
  var conv        = rep.conversion   || {};
  var topClientes = rep.top_clientes          || [];
  var topPedidos  = rep.top_clientes_pedidos  || [];
  var topM2       = rep.top_clientes_m2       || [];
  var porAsesor   = rep.por_asesor            || [];
  var repro       = rep.reproceso    || {};
  var hornoSems   = rep.horno_semanas|| [];
  var _resueltas = parseInt(r.a_tiempo||0) + parseInt(r.con_retraso||0);
  var pctTiempo  = _resueltas > 0 ? Math.round((r.a_tiempo / _resueltas) * 100) : 0;
  var pctColor   = pctTiempo >= 80 ? 'var(--green)' : pctTiempo >= 60 ? 'var(--amber)' : 'var(--red)';
  var pctCobrado = parseFloat(fin.ventas||0) > 0 ? Math.round((parseFloat(fin.cobrado||0) / parseFloat(fin.ventas)) * 100) : 0;
  var html = '';

  /* ─── KPIs Producción ─── */
  html += '<div class="section-title">&#211;rdenes del per&#237;odo</div>';
  html += '<div class="kpi-grid" style="grid-template-columns:repeat(6,1fr)">' +
    '<div class="kpi-card"><div class="kpi-num">' + (r.total||0) + '</div><div class="kpi-label">Total</div></div>' +
    '<div class="kpi-card"><div class="kpi-num" style="color:var(--green)">' + (r.a_tiempo||0) + '</div><div class="kpi-label">A tiempo</div><div class="kpi-sub">' + pctTiempo + '% de terminadas</div></div>' +
    '<div class="kpi-card"><div class="kpi-num" style="color:var(--red)">' + (r.con_retraso||0) + '</div><div class="kpi-label">Con retraso</div><div class="kpi-sub">Cerradas fuera de fecha</div></div>' +
    '<div class="kpi-card"><div class="kpi-num" style="color:var(--amber)">' + (r.en_proceso||0) + '</div><div class="kpi-label">En proceso</div><div class="kpi-sub">Activas dentro de fecha</div></div>' +
    '<div class="kpi-card"><div class="kpi-num" style="color:var(--red)">' + (r.retraso_abierto||0) + '</div><div class="kpi-label">Retraso abierto</div><div class="kpi-sub">Activas vencidas</div></div>' +
    '<div class="kpi-card"><div class="kpi-num" style="color:var(--purple)">' + (r.vobo_pendientes||0) + '</div><div class="kpi-label">VoBo pendiente</div><div class="kpi-sub">Esperando aprobaci&#243;n</div></div>' +
  '</div>';

  /* ─── KPIs Finanzas ─── */
  html += '<div class="section-title">Finanzas</div>';
  html += '<div class="kpi-grid" style="margin-bottom:14px">' +
    '<div class="kpi-card"><div class="kpi-num-sm">' + fmtMXN(fin.ventas) + '</div><div class="kpi-label">Ventas</div><div class="kpi-sub">Valor total de &#243;rdenes</div></div>' +
    '<div class="kpi-card"><div class="kpi-num-sm" style="color:var(--green)">' + fmtMXN(fin.cobrado) + '</div><div class="kpi-label">Cobrado</div><div class="kpi-sub">' + pctCobrado + '% de las ventas</div></div>' +
    '<div class="kpi-card"><div class="kpi-num-sm" style="color:var(--red)">' + fmtMXN(fin.por_cobrar) + '</div><div class="kpi-label">Por cobrar</div><div class="kpi-sub">Saldo pendiente</div></div>' +
    '<div class="kpi-card"><div class="kpi-num-sm">' + fmtMXN(fin.ticket_promedio) + '</div><div class="kpi-label">Ticket promedio</div><div class="kpi-sub">Por orden</div></div>' +
  '</div>';

  /* ─── KPIs Cotizaciones ─── */
  var convTotal = parseInt(conv.total_cots||0);
  var convConv  = parseInt(conv.convertidas||0);
  var convPct   = convTotal > 0 ? Math.round((convConv / convTotal) * 100) : 0;
  var convColor = convPct >= 60 ? 'var(--green)' : convPct >= 40 ? 'var(--amber)' : 'var(--red)';

  html += '<div class="section-title">Cotizaciones</div>';
  html += '<div class="kpi-grid" style="margin-bottom:14px">' +
    '<div class="kpi-card"><div class="kpi-num-sm">' + convTotal + '</div><div class="kpi-label">Cotizaciones</div><div class="kpi-sub">' + convConv + ' convertidas a orden</div></div>' +
    '<div class="kpi-card"><div class="kpi-num-sm" style="color:' + convColor + '">' + convPct + '%</div><div class="kpi-label">Tasa conversi&#243;n</div><div class="kpi-sub">Cotizaciones convertidas</div></div>' +
    '<div class="kpi-card"><div class="kpi-num-sm" style="color:var(--blue)">' + fmtMXN(cot.pipeline_mes_anterior) + ' / ' + fmtMXN(cot.pipeline_mes_actual) + '</div><div class="kpi-label">Pipeline vigente</div><div class="kpi-sub">Mes anterior / mes actual</div></div>' +
    '<div class="kpi-card"><div class="kpi-num-sm">' + parseInt(cot.total_cots||0) + '</div><div class="kpi-label">Pendientes</div><div class="kpi-sub">Vivas hoy, cualquier fecha (' + fmtMXN(cot.total_cotizado) + ')</div></div>' +
  '</div>';

  /* ─── Rendimiento por asesor ─── */
  if (porAsesor.length > 0) {
    html += '<div class="section-title">Rendimiento por asesor</div>';
    html += '<div class="table-card"><table>' +
      '<thead><tr>' +
        '<th>Asesor</th>' +
        '<th style="text-align:right">&#211;rdenes</th>' +
        '<th style="text-align:right">Ventas</th>' +
        '<th style="text-align:right">Cotizado (pipeline)</th>' +
      '</tr></thead><tbody>';
    porAsesor.forEach(function(a) {
      var bethyCot = a.asesor_nombre && a.asesor_nombre.indexOf('Bethy') >= 0 ? fmtMXN(cot.bethy_total) : (a.asesor_nombre && a.asesor_nombre.indexOf('Cynthia') >= 0 ? fmtMXN(cot.cynthia_total) : '&#8212;');
      html += '<tr>' +
        '<td><strong>' + esc(a.asesor_nombre||'Sin asignar') + '</strong></td>' +
        '<td style="text-align:right">' + a.ordenes + '</td>' +
        '<td style="text-align:right;font-weight:700;color:var(--blue)">' + fmtMXN(a.total_ventas) + '</td>' +
        '<td style="text-align:right;color:var(--muted)">' + bethyCot + '</td>' +
      '</tr>';
    });
    html += '</tbody></table></div>';
  }

  /* ─── Métricas operativas ─── */
  var reproTotal = parseInt(repro.total_piezas||0);
  var reproPct   = reproTotal > 0 ? ((parseInt(repro.piezas_reproceso||0) / reproTotal) * 100).toFixed(1) : '0.0';
  var reproColor = parseFloat(reproPct) === 0 ? 'var(--green)' : parseFloat(reproPct) <= 5 ? 'var(--amber)' : 'var(--red)';

  var valorAlmacen = 0;
  if (inv && inv.por_lamina) {
    inv.por_lamina.forEach(function(l) {
      valorAlmacen += (parseFloat(l.en_stock||0)) * (parseFloat(l.costo_prom_lamina||0));
    });
  }

  html += '<div class="section-title">Operaciones</div>';
  html += '<div class="metrics-grid" style="grid-template-columns:repeat(5,1fr)">' +
    '<div class="metric-card"><div class="metric-num">' + (r.prom_dias ? parseFloat(r.prom_dias).toFixed(1) : '&#8212;') + '</div><div class="metric-lbl">Prom. d&#237;as</div><div class="metric-sub">Ciclo de producci&#243;n</div></div>' +
    '<div class="metric-card"><div class="metric-num">' + (r.local||0) + '</div><div class="metric-lbl">Locales</div><div class="metric-sub">&#211;rdenes MTY</div></div>' +
    '<div class="metric-card"><div class="metric-num">' + (r.foraneo||0) + '</div><div class="metric-lbl">For&#225;neas</div><div class="metric-sub">&#211;rdenes SLC</div></div>' +
    '<div class="metric-card"><div class="metric-num" style="color:' + reproColor + '">' + reproPct + '%</div><div class="metric-lbl">Reproceso</div><div class="metric-sub">' + (repro.piezas_reproceso||0) + ' de ' + reproTotal + ' piezas</div></div>' +
    '<div class="metric-card"><div class="metric-num" style="font-size:17px">' + fmtMXN(valorAlmacen) + '</div><div class="metric-lbl">Valor almac&#233;n</div><div class="metric-sub">Stock actual</div></div>' +
  '</div>';

  /* ─── Barra entrega a tiempo ─── */
  var bATiempo = parseInt(r.a_tiempo||0);
  var bRetraso = parseInt(r.con_retraso||0);
  var totalBarra  = bATiempo + bRetraso;
  var pctATiempo  = totalBarra > 0 ? (bATiempo / totalBarra * 100).toFixed(1) : 0;
  var pctRetraso  = totalBarra > 0 ? (bRetraso / totalBarra * 100).toFixed(1) : 0;

  html += '<div class="efectividad-card">' +
    '<div class="efect-header"><div class="efect-title">Entrega a tiempo</div><div class="efect-pct" style="color:' + pctColor + '">' + pctTiempo + '%</div></div>' +
    '<div class="efect-bar">' +
      (pctATiempo > 0 ? '<div style="width:' + pctATiempo + '%;background:var(--green);transition:width .4s"></div>' : '') +
      (pctRetraso > 0 ? '<div style="width:' + pctRetraso + '%;background:var(--red);transition:width .4s"></div>' : '') +
    '</div>' +
    '<div class="efect-legend">' +
      '<span><span class="leg-dot" style="background:var(--green)"></span>' + bATiempo + ' a tiempo (' + pctATiempo + '%)</span>' +
      '<span><span class="leg-dot" style="background:var(--red)"></span>' + bRetraso + ' con retraso (' + pctRetraso + '%)</span>' +
      '<span style="color:var(--muted)">' + (parseInt(r.en_proceso||0) + parseInt(r.retraso_abierto||0)) + ' a&#250;n en producci&#243;n (excluidas)</span>' +
    '</div>' +
  '</div>';

  /* ─── Concentrado mensual ─── */
  if (mensual.length > 0) {
    var totRow = mensual.find(function(m){ return m.es_total; }) || {};
    var filas  = mensual.filter(function(m){ return !m.es_total; });
    html += '<div class="section-title">Concentrado mensual</div>';
    html += '<div class="table-card"><table>' +
      '<thead><tr>' +
        '<th>Mes</th><th>Total</th><th>Cerradas</th><th>Abiertas</th>' +
        '<th>A tiempo</th><th>Retraso</th><th>En proceso</th>' +
        '<th>% A tiempo</th><th>Prom. d&#237;as</th><th>Local</th><th>For&#225;neo</th>' +
      '</tr></thead><tbody>';
    filas.forEach(function(m) {
      var _res = parseInt(m.a_tiempo||0) + parseInt(m.con_retraso||0);
      var pct = _res > 0 ? Math.round((m.a_tiempo/_res)*100) : 0;
      var cls = pct>=80?'pct-good':pct>=60?'pct-warn':'pct-bad';
      html += '<tr>' +
        '<td>' + m.mes_label + '</td>' +
        '<td>' + m.total + '</td>' +
        '<td>' + m.cerradas + '</td>' +
        '<td>' + (m.abiertas>0?'<span class="badge-proceso">'+m.abiertas+'</span>':'0') + '</td>' +
        '<td><span class="badge-tiempo">' + m.a_tiempo + '</span></td>' +
        '<td>' + (m.con_retraso>0?'<span class="badge-retraso">'+m.con_retraso+'</span>':'0') + '</td>' +
        '<td>' + (m.en_proceso>0?'<span class="badge-proceso">'+m.en_proceso+'</span>':'0') + '</td>' +
        '<td class="' + cls + '">' + pct + '%</td>' +
        '<td>' + (m.prom_dias?parseFloat(m.prom_dias).toFixed(1):'&#8212;') + '</td>' +
        '<td>' + (m.local||0) + '</td>' +
        '<td>' + (m.foraneo||0) + '</td>' +
      '</tr>';
    });
    html += '</tbody>';
    if (totRow.total) {
      html += '<tfoot><tr>' +
        '<td>Total</td><td>' + totRow.total + '</td><td>' + totRow.cerradas + '</td>' +
        '<td>' + totRow.abiertas + '</td><td>' + totRow.a_tiempo + '</td>' +
        '<td>' + totRow.con_retraso + '</td><td>' + totRow.en_proceso + '</td>' +
        '<td>' + (function(){ var _r=parseInt(totRow.a_tiempo||0)+parseInt(totRow.con_retraso||0); return _r>0?Math.round(totRow.a_tiempo/_r*100)+'%':'&#8212;'; })() + '</td>' +
        '<td>' + (totRow.prom_dias?parseFloat(totRow.prom_dias).toFixed(1):'&#8212;') + '</td>' +
        '<td>' + (totRow.local||0) + '</td><td>' + (totRow.foraneo||0) + '</td>' +
      '</tr></tfoot>';
    }
    html += '</table></div>';
  }

  /* ─── Top clientes ─── */
  if (topClientes.length > 0 || topPedidos.length > 0 || topM2.length > 0) {
    html += '<div class="section-title">Top 5 Clientes del per&#237;odo</div>';
    html += '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px">' +
      mkTopPanel('Por monto ($)', topClientes, function(cl) {
        return '<span style="font-weight:800;color:var(--blue);font-size:14px">' + fmtMXN(cl.total_ventas) + '</span>';
      }) +
      mkTopPanel('Por pedidos', topPedidos, function(cl) {
        return '<span style="font-weight:700;color:var(--green)">' + cl.ordenes + ' &#243;rdenes</span>';
      }) +
      mkTopPanel('Por m&#178;', topM2, function(cl) {
        return '<span style="font-weight:700;color:var(--accent)">' + parseFloat(cl.total_m2).toFixed(1) + ' m&#178;</span>';
      }) +
    '</div>';
  }

  /* ─── Ocupación horno ─── */
  if (hornoSems.length > 0) {
    var maxPiezas = Math.max.apply(null, hornoSems.map(function(s){ return parseInt(s.piezas); }));
    var CHART_H = 130;
    var MIN_BAR = 10;
    html += '<div class="section-title">Ocupaci&#243;n horno — &#250;ltimas semanas</div>';
    html += '<div class="table-card" style="padding:20px 24px">' +
      '<div style="display:flex;align-items:flex-end;gap:8px;height:' + (CHART_H + 48) + 'px;border-bottom:1px solid var(--border);padding-bottom:0">';
    hornoSems.forEach(function(s) {
      var piezas  = parseInt(s.piezas);
      var barH    = maxPiezas > 0 ? Math.max(MIN_BAR, Math.round((piezas / maxPiezas) * CHART_H)) : MIN_BAR;
      var isLast  = s === hornoSems[hornoSems.length - 1];
      var bgColor = isLast ? 'var(--accent)' : 'var(--blue)';
      var opacity = isLast ? '1' : '0.55';
      html += '<div style="flex:1;min-width:36px;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;gap:5px">' +
        '<div style="font-size:12px;font-weight:800;color:' + (isLast ? 'var(--accent)' : 'var(--muted)') + '">' + piezas + '</div>' +
        '<div style="width:100%;height:' + barH + 'px;background:' + bgColor + ';border-radius:4px 4px 0 0;opacity:' + opacity + '"></div>' +
        '<div style="font-size:10px;font-weight:600;color:var(--muted-lt);text-align:center;padding-bottom:4px;white-space:nowrap">' + esc(s.semana_inicio) + '</div>' +
      '</div>';
    });
    html += '</div></div>';
  }

  html += rdRenderAlmacen(inv);
  html += rdRenderRentabilidad(inv);
  html += rdRenderEfectividadCorte(ef);
  document.getElementById('rdMain').innerHTML = html;
}

function rdRenderAlmacen(inv) {
  var porTipo   = (inv && inv.por_tipo)   ? inv.por_tipo   : [];
  var porLamina = (inv && inv.por_lamina) ? inv.por_lamina : [];
  if (porTipo.length === 0) return '';

  var fmt  = function(n) { return (n != null && n !== '') ? '$' + parseFloat(n).toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}) : '&#8212;'; };
  var fmtN = function(n) { return (n != null) ? parseInt(n).toLocaleString('es-MX') : '0'; };
  var lbl  = function(txt) { return '<div style="font-size:10px;color:var(--muted-lt);font-weight:400;margin-top:1px">'+txt+'</div>'; };

  var detalleMap = {};
  porLamina.forEach(function(r) {
    var k = r.tipo + '|' + r.espesor_mm;
    if (!detalleMap[k]) detalleMap[k] = [];
    detalleMap[k].push(r);
  });

  var html = '<div class="section-title">Costo de almac&#233;n (stock actual)</div>';
  html += '<div class="table-card"><table>' +
    '<thead><tr>' +
      '<th style="width:28px"></th>' +
      '<th>Tipo de vidrio</th>' +
      '<th>Espesor</th>' +
      '<th>Dimensiones</th>' +
      '<th style="text-align:right">Stock</th>' +
      '<th style="text-align:right">M&#237;n /m&#178;</th>' +
      '<th style="text-align:right">M&#225;x /m&#178;</th>' +
      '<th style="text-align:right">Prom pond. /m&#178;</th>' +
      '<th style="text-align:right">Prom /l&#225;m.</th>' +
    '</tr></thead><tbody>';

  porTipo.forEach(function(g, gi) {
    var k       = g.tipo + '|' + g.espesor_mm;
    var dets    = detalleMap[k] || [];
    var multi   = dets.length > 1;
    var dimText = multi
      ? dets.length + ' dimensiones'
      : (dets[0] ? dets[0].ancho_mm + '&#215;' + dets[0].alto_mm + ' mm' : '&#8212;');

    html += '<tr class="inv-row-grupo" onclick="rdToggleAlmacen(' + gi + ')">' +
      '<td style="text-align:center">' + (multi ? '<span id="inv-tog-'+gi+'" style="font-size:10px;color:var(--muted-lt)">&#9654;</span>' : '') + '</td>' +
      '<td><strong>' + g.tipo + '</strong></td>' +
      '<td>' + g.espesor_mm + ' mm</td>' +
      '<td style="color:var(--muted-lt);font-size:12px">' + dimText + '</td>' +
      '<td style="text-align:right"><strong>' + fmtN(g.en_stock) + '</strong>' + lbl('l&#225;m.') + '</td>' +
      '<td style="text-align:right;color:var(--green)">' + fmt(g.precio_min_m2) + '</td>' +
      '<td style="text-align:right;color:var(--red)">' + fmt(g.precio_max_m2) + '</td>' +
      '<td style="text-align:right"><strong style="color:var(--blue)">' + fmt(g.costo_prom_m2) + '</strong></td>' +
      '<td style="text-align:right">' + fmt(g.prom_lamina) + '</td>' +
    '</tr>';

    if (multi) {
      dets.forEach(function(d) {
        html += '<tr class="inv-det-' + gi + '" style="display:none;background:#fafbfc">' +
          '<td></td>' +
          '<td style="padding-left:24px;color:var(--muted);font-size:12px">' + d.tipo + '</td>' +
          '<td style="color:var(--muted);font-size:12px">' + d.espesor_mm + ' mm</td>' +
          '<td style="font-size:12px">' + d.ancho_mm + '&#215;' + d.alto_mm + ' mm' + lbl(parseFloat(d.m2).toFixed(4)+' m&#178;/l&#225;m.') + '</td>' +
          '<td style="text-align:right;font-size:12px"><strong>' + fmtN(d.en_stock) + '</strong>' + lbl('l&#225;m.') + '</td>' +
          '<td style="text-align:right;font-size:12px;color:var(--green)">' + fmt(d.precio_min_m2) + '</td>' +
          '<td style="text-align:right;font-size:12px;color:var(--red)">' + fmt(d.precio_max_m2) + '</td>' +
          '<td style="text-align:right;font-size:12px;color:var(--blue)">' + fmt(d.costo_prom_m2) + '</td>' +
          '<td style="text-align:right;font-size:12px">' + fmt(d.costo_prom_lamina) + '</td>' +
        '</tr>';
      });
    }
  });

  html += '</tbody></table></div>';
  return html;
}

function rdRenderRentabilidad(inv) {
  var porTipo = (inv && inv.por_tipo) ? inv.por_tipo : [];
  if (!porTipo.length) return '';
  var fechaDesde = (inv && inv.precio_real_desde) ? inv.precio_real_desde.substring(0, 10) : '';

  var rows = [];
  porTipo.forEach(function(g) {
    var precioSinIva = (g.precio_venta_real !== null && g.precio_venta_real !== undefined) ? parseFloat(g.precio_venta_real) : null;
    var precioConIva = precioSinIva !== null ? parseFloat((precioSinIva * 1.16).toFixed(4)) : null;
    var m2Vendidos  = parseFloat(g.m2_vendidos_real || 0);
    var costoSinIva = (g.costo_prom_m2 !== null && g.costo_prom_m2 !== undefined) ? parseFloat(g.costo_prom_m2) : null;
    var costoConIva = costoSinIva !== null ? parseFloat((costoSinIva * 1.16).toFixed(4)) : null;
    // Sin costo actual (se agotó el stock o nunca se compró) y sin ventas registradas: no hay nada que mostrar.
    if (costoSinIva === null && precioSinIva === null) return;
    // Utilidad/Markup/%Utilidad/Margen% se calculan con costo c/IVA vs precio real c/IVA (misma base
    // en ambos lados) a peticion de Armando (10-jul-2026) — antes mezclaba precio s/IVA con costo c/IVA.
    var utilidad    = (precioConIva !== null && costoConIva !== null) ? parseFloat((precioConIva - costoConIva).toFixed(4)) : null;
    var markup      = (utilidad !== null && costoConIva > 0)  ? (utilidad / costoConIva) * 100 : null;
    var utilidadPct = (utilidad !== null && precioConIva > 0) ? (utilidad / precioConIva) * 100 : null;
    var margen      = utilidadPct;
    rows.push({ tipo: g.tipo, espesor: g.espesor_mm, costoSinIva: costoSinIva, costoConIva: costoConIva, precioSinIva: precioSinIva, precioConIva: precioConIva, m2Vendidos: m2Vendidos, utilidad: utilidad, markup: markup, utilidadPct: utilidadPct, margen: margen });
  });

  if (!rows.length) return '';
  rows.sort(function(a, b) {
    if (a.tipo < b.tipo) return -1;
    if (a.tipo > b.tipo) return 1;
    return parseFloat(a.espesor) - parseFloat(b.espesor);
  });

  var fmt    = function(n) { return n != null ? '$' + parseFloat(n).toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}) : '&#8212;'; };
  var fmtPct = function(n) { return n != null ? parseFloat(n).toFixed(1) + '%' : '&#8212;'; };
  var clrMargen = function(m) { return m === null ? 'var(--muted-lt)' : m >= 55 ? 'var(--green)' : m >= 40 ? 'var(--amber)' : 'var(--red)'; };

  var html = '<div class="section-title">Rentabilidad por m&#178; de vidrio</div>';
  html += '<div style="font-size:11px;color:var(--muted-lt);margin:-6px 0 10px">Precio real ponderado por m&#178; efectivamente vendido' + (fechaDesde ? ' desde ' + fechaDesde : '') + ' (neto de descuento, no precio de cat&#225;logo)</div>';
  html += '<div class="table-card"><table>' +
    '<thead><tr>' +
      '<th>Tipo</th>' +
      '<th>Espesor</th>' +
      '<th style="text-align:right">Costo s/IVA /m&#178;</th>' +
      '<th style="text-align:right">Costo c/IVA /m&#178;</th>' +
      '<th style="text-align:right">m&#178; vendidos</th>' +
      '<th style="text-align:right">Precio real s/IVA /m&#178;</th>' +
      '<th style="text-align:right">Precio real c/IVA /m&#178;</th>' +
      '<th style="text-align:right">Utilidad /m&#178;</th>' +
      '<th style="text-align:right">Markup</th>' +
      '<th style="text-align:right">% Utilidad</th>' +
      '<th style="text-align:right">Margen %</th>' +
    '</tr></thead><tbody>';

  rows.forEach(function(r) {
    var sinCosto = '<span style="color:var(--muted-lt);font-size:11px">Sin stock</span>';
    var sinVenta = '<span style="color:var(--muted-lt);font-size:11px">Sin ventas</span>';
    var clr = clrMargen(r.margen);
    var barW = r.margen !== null ? Math.min(100, Math.max(0, parseFloat(r.margen.toFixed(0)))) : 0;
    var margenCell = r.margen !== null
      ? '<div style="display:flex;align-items:center;gap:6px;justify-content:flex-end">' +
          '<div style="width:44px;height:5px;background:var(--border);border-radius:3px;overflow:hidden">' +
            '<div style="width:' + barW + '%;height:100%;background:' + clr + ';border-radius:3px"></div>' +
          '</div>' +
          '<span style="font-weight:800;font-size:13px;color:' + clr + ';min-width:44px;text-align:right">' + fmtPct(r.margen) + '</span>' +
        '</div>'
      : (r.costoSinIva === null ? sinCosto : sinVenta);
    html += '<tr>' +
      '<td><strong>' + esc(r.tipo) + '</strong></td>' +
      '<td>' + r.espesor + ' mm</td>' +
      '<td style="text-align:right;color:var(--muted)">' + (r.costoSinIva !== null ? fmt(r.costoSinIva) : sinCosto) + '</td>' +
      '<td style="text-align:right;color:var(--purple)">' + (r.costoConIva !== null ? fmt(r.costoConIva) : sinCosto) + '</td>' +
      '<td style="text-align:right;color:var(--muted)">' + r.m2Vendidos.toFixed(1) + '</td>' +
      '<td style="text-align:right;color:var(--muted)">' + (r.precioSinIva !== null ? fmt(r.precioSinIva) : sinVenta) + '</td>' +
      '<td style="text-align:right">' + (r.precioConIva !== null ? fmt(r.precioConIva) : sinVenta) + '</td>' +
      '<td style="text-align:right;color:var(--green);font-weight:700">' + fmt(r.utilidad) + '</td>' +
      '<td style="text-align:right;color:var(--muted)">' + fmtPct(r.markup) + '</td>' +
      '<td style="text-align:right;font-weight:700;color:var(--blue)">' + fmtPct(r.utilidadPct) + '</td>' +
      '<td style="text-align:right">' + margenCell + '</td>' +
    '</tr>';
  });

  html += '</tbody></table></div>';
  return html;
}

// Efectividad de corte por m2 (sesiones_corte, ver api/sesion_corte.php).
// % = m2 aprovechado / m2 disponible de la lamina o pedaceria usada.
function rdRenderEfectividadCorte(ef) {
  var g = (ef && ef.global) ? ef.global : null;
  if (!g || !g.total_sesiones) return '';

  var pct = g.efectividad_pct_global !== null ? parseFloat(g.efectividad_pct_global) : null;
  var clr = pct === null ? 'var(--muted-lt)' : pct >= 85 ? 'var(--green)' : pct >= 70 ? 'var(--amber)' : 'var(--red)';

  var html = '<div class="section-title">Efectividad de Corte</div>';
  html += '<div class="kpi-grid" style="margin-bottom:14px">' +
    '<div class="kpi-card"><div class="kpi-num-sm" style="color:' + clr + '">' + (pct !== null ? pct.toFixed(1) + '%' : '&#8212;') + '</div><div class="kpi-label">% Efectividad global</div><div class="kpi-sub">m&#178; aprovechados / m&#178; usados</div></div>' +
    '<div class="kpi-card"><div class="kpi-num-sm">' + parseFloat(g.m2_aprovechado_total || 0).toFixed(1) + '</div><div class="kpi-label">m&#178; aprovechados</div><div class="kpi-sub">de ' + parseFloat(g.m2_disponible_total || 0).toFixed(1) + ' m&#178; usados</div></div>' +
    '<div class="kpi-card"><div class="kpi-num-sm">' + (g.sesiones_catalogo || 0) + '</div><div class="kpi-label">L&#225;minas de cat&#225;logo</div><div class="kpi-sub">Sesiones de corte</div></div>' +
    '<div class="kpi-card"><div class="kpi-num-sm" style="color:var(--amber)">' + (g.sesiones_pedaceria || 0) + '</div><div class="kpi-label">L&#225;minas de pedacer&#237;a usadas</div><div class="kpi-sub">Base para bono de operador</div></div>' +
  '</div>';

  var porTipo = (ef && ef.por_tipo) ? ef.por_tipo : [];
  if (porTipo.length) {
    html += '<div class="table-card"><table>' +
      '<thead><tr><th>Tipo</th><th>Espesor</th><th style="text-align:right">L&#225;minas</th><th style="text-align:right">% Efectividad</th>' +
      '<th style="text-align:right">Efectividad &gt;85%</th><th style="text-align:right">Efectividad &lt;85%</th><th style="text-align:right">Pedacer&#237;a (m&#178;)</th></tr></thead><tbody>';
    porTipo.forEach(function(t) {
      var tPct = t.efectividad_pct !== null ? parseFloat(t.efectividad_pct) : null;
      html += '<tr>' +
        '<td>' + esc(t.tipo) + '</td>' +
        '<td>' + t.espesor_mm + ' mm</td>' +
        '<td style="text-align:right">' + t.sesiones + '</td>' +
        '<td style="text-align:right;font-weight:700">' + (tPct !== null ? tPct.toFixed(1) + '%' : '&#8212;') + '</td>' +
        '<td style="text-align:right;color:var(--green)">' + (t.sesiones_efectivas || 0) + '</td>' +
        '<td style="text-align:right;color:var(--red)">' + (t.sesiones_no_efectivas || 0) + '</td>' +
        '<td style="text-align:right;color:var(--amber)">' + parseFloat(t.m2_pedaceria || 0).toFixed(1) + '</td>' +
      '</tr>';
    });
    html += '</tbody></table></div>';
  }
  return html;
}

function rdToggleAlmacen(gi) {
  var rows   = document.querySelectorAll('.inv-det-' + gi);
  var toggle = document.getElementById('inv-tog-' + gi);
  var open   = rows.length > 0 && rows[0].style.display !== 'none';
  rows.forEach(function(r) { r.style.display = open ? 'none' : ''; });
  if (toggle) toggle.innerHTML = open ? '&#9654;' : '&#9660;';
}

/* ─── Pestaña Ventas y Cobranza ─── */
var rvGran   = 'dia';
var rvFecha  = rvFmtDate(new Date());
var rvLoaded = false;

function rvFmtDate(d) {
  var y = d.getFullYear();
  var m = String(d.getMonth() + 1);
  var day = String(d.getDate());
  if (m.length < 2) m = '0' + m;
  if (day.length < 2) day = '0' + day;
  return y + '-' + m + '-' + day;
}

function rvParseDate(s) {
  return new Date(s + 'T12:00:00');
}

function rdTabSwitch(tab) {
  document.getElementById('rdPanelResumen').style.display = (tab === 'resumen') ? '' : 'none';
  document.getElementById('rdPanelVentas').style.display  = (tab === 'ventas')  ? '' : 'none';
  document.querySelectorAll('.rd-tab').forEach(function(b) {
    if (b.getAttribute('data-tab') === tab) b.classList.add('active');
    else b.classList.remove('active');
  });
  if (tab === 'ventas' && !rvLoaded) {
    rvLoaded = true;
    rvCargar();
  }
}

function rvSetGran(g) {
  rvGran  = g;
  rvFecha = rvFmtDate(new Date());
  document.querySelectorAll('.rv-toggle button').forEach(function(b) {
    if (b.getAttribute('data-g') === g) b.classList.add('active');
    else b.classList.remove('active');
  });
  rvCargar();
}

function rvNav(delta) {
  var d = rvParseDate(rvFecha);
  if (rvGran === 'dia') {
    d.setDate(d.getDate() + delta);
  } else if (rvGran === 'semana') {
    d.setDate(d.getDate() + (delta * 7));
  } else {
    d.setDate(15);
    d.setMonth(d.getMonth() + delta);
  }
  rvFecha = rvFmtDate(d);
  rvCargar();
}

function rvHoy() {
  rvFecha = rvFmtDate(new Date());
  rvCargar();
}

function rvCargar() {
  document.getElementById('rvMain').innerHTML = '<div class="loading"><div class="spin"></div>Cargando&#8230;</div>';
  fetch('../api/reporte_direccion.php?accion=ventas_cobranza&gran=' + rvGran + '&fecha=' + rvFecha)
    .then(function(r) { return r.json(); })
    .then(function(data) { rvRender(data); })
    .catch(function() {
      document.getElementById('rvMain').innerHTML = '<div class="loading" style="color:#dc2626">Error de conexi&#243;n</div>';
    });
}

function rvRender(data) {
  var p = data.periodo || {};
  var lbl = document.getElementById('rvLabel');
  if (lbl) lbl.textContent = p.label || '&#8212;';
  var ordenes = data.ordenes || [];
  var html = '';
  if (ordenes.length === 0) {
    html = '<div class="loading" style="padding:40px">Sin &#243;rdenes en este per&#237;odo</div>';
  } else {
    var granLbl = rvGran === 'dia' ? 'D&#237;a' : (rvGran === 'semana' ? 'Semana' : 'Mes');
    html += '<div class="table-card"><table><thead><tr>' +
      '<th>#Orden</th><th>Asesor</th><th>Cliente</th>' +
      '<th style="text-align:right">Anticipo</th>' +
      '<th style="text-align:right">Restante</th>' +
      '<th style="text-align:right">Total del Pedido</th>' +
      '<th style="text-align:right">Acumulado en Pedidos del ' + granLbl + '</th>' +
    '</tr></thead><tbody>';
    ordenes.forEach(function(o) {
      var restanteColor = parseFloat(o.restante) > 0.005 ? 'var(--red)' : 'var(--muted-lt)';
      html += '<tr>' +
        '<td><strong style="color:var(--blue)">' + esc(o.folio) + '</strong></td>' +
        '<td>' + esc(o.asesor_nombre || '&#8212;') + '</td>' +
        '<td>' + esc(o.cliente_nombre) + '</td>' +
        '<td style="text-align:right;color:var(--green)">' + fmtMXN(o.anticipo) + '</td>' +
        '<td style="text-align:right;color:' + restanteColor + '">' + fmtMXN(o.restante) + '</td>' +
        '<td style="text-align:right;font-weight:700">' + fmtMXN(o.total) + '</td>' +
        '<td style="text-align:right;color:var(--muted)">' + fmtMXN(o.acumulado) + '</td>' +
      '</tr>';
    });
    html += '</tbody></table></div>';
  }
  document.getElementById('rvMain').innerHTML = html;
}

rdCargar();
setInterval(rdCargar, 300000);

window.rdCargar        = rdCargar;
window.rdToggleAlmacen = rdToggleAlmacen;
window.rdTabSwitch     = rdTabSwitch;
window.rvSetGran       = rvSetGran;
window.rvNav           = rvNav;
window.rvHoy           = rvHoy;
return { init: rdCargar };
})();
ModReporte.init();
</script>
