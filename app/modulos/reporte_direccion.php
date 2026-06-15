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
  --bg:#f0f4f8; --white:#ffffff; --border:#e2e8f0; --text:#1e293b;
  --muted:#64748b; --accent:#f5a623; --blue:#1e40af; --green:#16a34a;
  --red:#dc2626; --yellow:#d97706; --navy:#1a1a2e;
}
.rd-wrap { padding: 24px; background: var(--bg); min-height: 100%; }
.rd-toolbar { display:flex; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.rd-toolbar label { font-size:13px; font-weight:600; color:var(--text); }
.rd-toolbar select { padding:7px 12px; border:1px solid var(--border); border-radius:8px; font-size:13px; background:#fff; outline:none; cursor:pointer; }
.live-dot { display:inline-block; width:8px; height:8px; border-radius:50%; background:#22c55e; margin-right:6px; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.4} }
.ts-label { font-size:12px; color:var(--muted); display:flex; align-items:center; }
.loading { text-align:center; padding:60px; color:var(--muted); font-size:14px; }
.spin { width:36px; height:36px; border:3px solid #e2e8f0; border-top-color:var(--blue); border-radius:50%; animation:spin .7s linear infinite; margin:0 auto 12px; }
@keyframes spin { to{transform:rotate(360deg)} }
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:16px; }
.kpi-card { background:#fff; border-radius:14px; padding:18px 20px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.kpi-total  { border-top:3px solid var(--navy); }
.kpi-tiempo { border-top:3px solid var(--green); }
.kpi-retraso{ border-top:3px solid var(--red); }
.kpi-proceso{ border-top:3px solid var(--yellow); }
.kpi-num   { font-size:36px; font-weight:800; line-height:1; margin-bottom:4px; }
.kpi-label { font-size:13px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; }
.kpi-sub   { font-size:12px; color:var(--muted); margin-top:4px; }
.metrics-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:16px; }
.metric-card { background:#fff; border-radius:12px; padding:14px 16px; box-shadow:0 1px 4px rgba(0,0,0,.06); display:flex; align-items:center; gap:12px; }
.metric-icon { font-size:28px; flex-shrink:0; }
.metric-num  { font-size:24px; font-weight:800; line-height:1; }
.metric-lbl  { font-size:11px; color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
.efectividad-card { background:#fff; border-radius:14px; padding:18px 20px; box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:16px; }
.efect-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.efect-title { font-size:14px; font-weight:700; color:var(--text); }
.efect-pct { font-size:28px; font-weight:800; }
.efect-bar { background:#e2e8f0; border-radius:99px; height:12px; overflow:hidden; margin-bottom:8px; }
.efect-fill { height:100%; border-radius:99px; background:linear-gradient(90deg,#16a34a,#22c55e); transition:width 1s; }
.efect-legend { display:flex; gap:16px; font-size:12px; color:var(--muted); }
.leg-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; }
.section-title { font-size:14px; font-weight:700; color:var(--text); margin:16px 0 10px; text-transform:uppercase; letter-spacing:.5px; }
.table-card { background:#fff; border-radius:12px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.06); margin-bottom:16px; overflow-x:auto; }
table { width:100%; border-collapse:collapse; }
thead tr { background:var(--navy); }
thead th { padding:10px 14px; font-size:11px; font-weight:700; color:white; text-align:left; letter-spacing:.5px; white-space:nowrap; }
tbody tr { border-bottom:1px solid #f1f5f9; transition:background .1s; }
tbody tr:hover { background:#f8fafc; }
tbody td { padding:10px 14px; font-size:13px; }
tfoot tr { background:#f8fafc; font-weight:700; border-top:2px solid var(--border); }
tfoot td { padding:10px 14px; font-size:13px; }
.badge-tiempo  { background:#dcfce7; color:#166534; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:600; }
.badge-retraso { background:#fee2e2; color:#b91c1c; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:600; }
.badge-proceso { background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:600; }
.pct-good { color:var(--green); font-weight:700; }
.pct-warn { color:var(--yellow); font-weight:700; }
.pct-bad  { color:var(--red); font-weight:700; }
.ordenes-row { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:10px; margin-bottom:16px; }
.orden-item { background:#fff; border-radius:12px; padding:14px 16px; box-shadow:0 1px 4px rgba(0,0,0,.06); cursor:pointer; text-decoration:none; color:inherit; border-left:4px solid #e2e8f0; transition:box-shadow .15s; display:block; }
.orden-item:hover { box-shadow:0 4px 12px rgba(0,0,0,.1); }
.orden-item.urgente { border-left-color:var(--red); }
.orden-item.alerta  { border-left-color:var(--yellow); }
.orden-item.proceso { border-left-color:var(--green); }
.oi-folio   { font-size:15px; font-weight:700; color:#1d4ed8; margin-bottom:4px; }
.oi-cliente { font-size:12px; color:var(--muted); margin-bottom:6px; }
.oi-meta    { display:flex; justify-content:space-between; font-size:12px; }
@media(max-width:900px){ .kpi-grid{grid-template-columns:repeat(2,1fr);} .metrics-grid{grid-template-columns:repeat(2,1fr);} }
.inv-row-grupo { background:#f0f4f8; cursor:pointer; font-weight:600; }
.inv-row-grupo:hover { background:#dde3ea; }
.kpi-ventas   { border-top:3px solid var(--blue); }
.kpi-cobrado  { border-top:3px solid var(--green); }
.kpi-pendiente{ border-top:3px solid var(--red); }
.kpi-ticket   { border-top:3px solid #7c3aed; }
.kpi-num-sm   { font-size:26px; font-weight:800; line-height:1; margin-bottom:4px; }
</style>

<div class="rd-wrap">
  <div class="rd-toolbar">
    <label>Per&#237;odo:</label>
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

<script>
window.ModReporte=(function(){

async function rdCargar() {
  const periodo = document.getElementById('rdFiltro').value;
  document.getElementById('rdMain').innerHTML = '<div class="loading"><div class="spin"></div>Cargando reporte&#8230;</div>';
  try {
    const [r1, r2, r3] = await Promise.all([
      fetch('../api/reporte_direccion.php?periodo=' + periodo),
      fetch('../api/dashboard.php'),
      fetch('../api/inventario.php?accion=costo_promedio')
    ]);
    const rep  = await r1.json();
    const dash = await r2.json();
    const inv  = await r3.json().catch(() => ({}));
    if (rep.error) { document.getElementById('rdMain').innerHTML = '<div class="loading" style="color:#dc2626">Error: '+rep.error+'</div>'; return; }
    rdRender(rep, dash, inv);
    document.getElementById('rdTs').textContent = 'Act. ' + new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  } catch(e) { document.getElementById('rdMain').innerHTML = '<div class="loading" style="color:#dc2626">Error de conexi&#243;n</div>'; }
}

function rdFmtFecha(ts) {
  if (!ts) return '&#8212;';
  const d = new Date(ts.includes('T') ? ts : ts + 'T12:00:00');
  return d.toLocaleDateString('es-MX', {day:'2-digit', month:'short', year:'numeric'});
}
function rdDias(fecha) {
  if (!fecha) return 99;
  const f = fecha.includes('T') ? new Date(fecha) : new Date(fecha + 'T12:00:00');
  return Math.ceil((f - new Date()) / 86400000);
}
function fmtMXN(n) {
  if (!n || isNaN(parseFloat(n))) return '$0';
  return '$' + parseFloat(n).toLocaleString('es-MX', {minimumFractionDigits:0, maximumFractionDigits:0});
}

function rdRender(rep, dash, inv) {
  const r       = rep.resumen     || {};
  const fin     = rep.finanzas    || {};
  const cot     = rep.cots_resumen|| {};
  const mensual = rep.mensual     || [];
  const pctTiempo  = r.total > 0 ? Math.round((r.a_tiempo / r.total) * 100) : 0;
  const pctColor   = pctTiempo >= 80 ? 'var(--green)' : pctTiempo >= 60 ? 'var(--yellow)' : 'var(--red)';
  const pctCobrado = parseFloat(fin.ventas||0) > 0 ? Math.round((parseFloat(fin.cobrado||0) / parseFloat(fin.ventas)) * 100) : 0;
  let html = '';

  html += `<div class="kpi-grid">
    <div class="kpi-card kpi-total"><div class="kpi-num">${r.total||0}</div><div class="kpi-label">Total &#243;rdenes</div><div class="kpi-sub">${r.cerradas||0} cerradas &#183; ${r.abiertas||0} abiertas</div></div>
    <div class="kpi-card kpi-tiempo"><div class="kpi-num">${r.a_tiempo||0}</div><div class="kpi-label">&#x2705; A tiempo</div><div class="kpi-sub">${pctTiempo}% del total</div></div>
    <div class="kpi-card kpi-retraso"><div class="kpi-num">${r.con_retraso||0}</div><div class="kpi-label">&#x26A0;&#xFE0F; Con retraso</div><div class="kpi-sub">${r.total>0?Math.round((r.con_retraso/r.total)*100):0}% del total</div></div>
    <div class="kpi-card kpi-proceso"><div class="kpi-num">${r.en_proceso||0}</div><div class="kpi-label">&#x1F504; En proceso</div><div class="kpi-sub">${r.retraso_abierto||0} con retraso abierto</div></div>
  </div>`;

  html += `<div class="section-title">&#x1F4B0; &#211;rdenes</div>
  <div class="kpi-grid" style="margin-bottom:8px">
    <div class="kpi-card kpi-ventas"><div class="kpi-num-sm">${fmtMXN(fin.ventas)}</div><div class="kpi-label">&#x1F4B3; Ventas</div><div class="kpi-sub">Valor total de &#243;rdenes</div></div>
    <div class="kpi-card kpi-cobrado"><div class="kpi-num-sm">${fmtMXN(fin.cobrado)}</div><div class="kpi-label">&#x2705; Cobrado</div><div class="kpi-sub">${pctCobrado}% de las ventas</div></div>
    <div class="kpi-card kpi-pendiente"><div class="kpi-num-sm">${fmtMXN(fin.por_cobrar)}</div><div class="kpi-label">&#x23F3; Por cobrar</div><div class="kpi-sub">Saldo pendiente</div></div>
    <div class="kpi-card kpi-ticket"><div class="kpi-num-sm">${fmtMXN(fin.ticket_promedio)}</div><div class="kpi-label">&#x1F3F7;&#xFE0F; Ticket promedio</div><div class="kpi-sub">Por orden</div></div>
  </div>`;

  html += `<div class="section-title">&#x1F4CB; Cotizaciones vigentes</div>
  <div class="kpi-grid" style="margin-bottom:16px">
    <div class="kpi-card kpi-total"><div class="kpi-num-sm">${parseInt(cot.total_cots||0)}</div><div class="kpi-label">&#x1F4C4; Cotizaciones</div><div class="kpi-sub">Pendientes de convertir a orden</div></div>
    <div class="kpi-card kpi-ventas"><div class="kpi-num-sm">${fmtMXN(cot.total_cotizado)}</div><div class="kpi-label">&#x1F4B3; Total cotizado</div><div class="kpi-sub">Ticket prom: ${fmtMXN(cot.ticket_promedio)}</div></div>
    <div class="kpi-card kpi-tiempo"><div class="kpi-num-sm">${fmtMXN(cot.bethy_total)}</div><div class="kpi-label">&#x1F9D1; Bethy Rocha</div><div class="kpi-sub">Total cotizado</div></div>
    <div class="kpi-card kpi-proceso"><div class="kpi-num-sm">${fmtMXN(cot.cynthia_total)}</div><div class="kpi-label">&#x1F9D1; Cynthia Negrete</div><div class="kpi-sub">Total cotizado</div></div>
  </div>`;

  html += `<div class="metrics-grid">
    <div class="metric-card"><div class="metric-icon">&#x23F1;&#xFE0F;</div><div><div class="metric-num">${r.prom_dias ? parseFloat(r.prom_dias).toFixed(1) : '&#8212;'}</div><div class="metric-lbl">Promedio d&#237;as proceso</div></div></div>
    <div class="metric-card"><div class="metric-icon">&#x1F3E0;</div><div><div class="metric-num">${r.local||0}</div><div class="metric-lbl">&#211;rdenes locales</div></div></div>
    <div class="metric-card"><div class="metric-icon">&#x1F69A;</div><div><div class="metric-num">${r.foraneo||0}</div><div class="metric-lbl">&#211;rdenes for&#225;neas</div></div></div>
  </div>`;

  html += `<div class="efectividad-card">
    <div class="efect-header"><div class="efect-title">% Entrega a tiempo</div><div class="efect-pct" style="color:${pctColor}">${pctTiempo}%</div></div>
    <div class="efect-bar"><div class="efect-fill" style="width:${pctTiempo}%"></div></div>
    <div class="efect-legend">
      <span><span class="leg-dot" style="background:#16a34a"></span>${r.a_tiempo||0} A tiempo</span>
      <span><span class="leg-dot" style="background:#dc2626"></span>${r.con_retraso||0} Con retraso</span>
      <span><span class="leg-dot" style="background:#d97706"></span>${r.en_proceso||0} En proceso</span>
    </div>
  </div>`;

  if (mensual.length > 0) {
    const totRow = mensual.find(m => m.es_total) || {};
    const filas  = mensual.filter(m => !m.es_total);
    html += `<div class="section-title">&#x1F4C5; Concentrado mensual</div>
    <div class="table-card"><table>
      <thead><tr><th>Mes</th><th>Total</th><th>Cerradas</th><th>Abiertas</th><th>&#x2705; A Tiempo</th><th>&#x26A0;&#xFE0F; Retraso</th><th>&#x1F504; En Proceso</th><th>% A Tiempo</th><th>Prom. D&#237;as</th><th>&#x1F3E0; Local</th><th>&#x1F69A; For&#225;neo</th></tr></thead>
      <tbody>${filas.map(m => {
        const pct = m.total > 0 ? Math.round((m.a_tiempo/m.total)*100) : 0;
        const cls = pct>=80?'pct-good':pct>=60?'pct-warn':'pct-bad';
        return `<tr><td>${m.mes_label}</td><td>${m.total}</td><td>${m.cerradas}</td>
          <td>${m.abiertas>0?'<span class="badge-proceso">'+m.abiertas+'</span>':'0'}</td>
          <td><span class="badge-tiempo">${m.a_tiempo}</span></td>
          <td>${m.con_retraso>0?'<span class="badge-retraso">'+m.con_retraso+'</span>':'0'}</td>
          <td>${m.en_proceso>0?'<span class="badge-proceso">'+m.en_proceso+'</span>':'0'}</td>
          <td class="${cls}">${pct}%</td><td>${m.prom_dias?parseFloat(m.prom_dias).toFixed(1):'&#8212;'}</td>
          <td>${m.local||0}</td><td>${m.foraneo||0}</td></tr>`;
      }).join('')}</tbody>
      ${totRow.total?`<tfoot><tr><td>TOTAL</td><td>${totRow.total}</td><td>${totRow.cerradas}</td><td>${totRow.abiertas}</td><td>${totRow.a_tiempo}</td><td>${totRow.con_retraso}</td><td>${totRow.en_proceso}</td><td>${totRow.total>0?Math.round((totRow.a_tiempo/totRow.total)*100)+'%':'&#8212;'}</td><td>${totRow.prom_dias?parseFloat(totRow.prom_dias).toFixed(1):'&#8212;'}</td><td>${totRow.local||0}</td><td>${totRow.foraneo||0}</td></tr></tfoot>`:''}
    </table></div>`;
  }

  html += rdRenderAlmacen(inv);
  html += rdRenderRentabilidad(inv);
  document.getElementById('rdMain').innerHTML = html;
}

function rdRenderAlmacen(inv) {
  const porTipo   = (inv && inv.por_tipo)   ? inv.por_tipo   : [];
  const porLamina = (inv && inv.por_lamina) ? inv.por_lamina : [];
  if (porTipo.length === 0) return '';

  const fmt  = n => (n != null && n !== '') ? '$' + parseFloat(n).toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}) : '&#8212;';
  const fmtN = n => (n != null) ? parseInt(n).toLocaleString('es-MX') : '0';
  const lbl  = txt => '<div style="font-size:10px;color:var(--muted);font-weight:400;margin-top:1px">'+txt+'</div>';

  const detalleMap = {};
  porLamina.forEach(r => {
    const k = r.tipo + '|' + r.espesor_mm;
    if (!detalleMap[k]) detalleMap[k] = [];
    detalleMap[k].push(r);
  });

  let html = `<div class="section-title">&#x1F4E6; Costo de Almac&#233;n (stock actual)</div>
  <div class="table-card"><table>
    <thead><tr>
      <th style="width:28px"></th>
      <th>Tipo de vidrio</th>
      <th>Espesor</th>
      <th>Dimensiones</th>
      <th style="text-align:right">Stock</th>
      <th style="text-align:right">M&#237;n compra/m&#178;</th>
      <th style="text-align:right">M&#225;x compra/m&#178;</th>
      <th style="text-align:right">Prom pond./m&#178;</th>
      <th style="text-align:right">Prom/l&#225;m.</th>
    </tr></thead><tbody>`;

  porTipo.forEach((g, gi) => {
    const k       = g.tipo + '|' + g.espesor_mm;
    const dets    = detalleMap[k] || [];
    const multi   = dets.length > 1;
    const dimText = multi
      ? dets.length + ' dimensiones'
      : (dets[0] ? dets[0].ancho_mm + '&#215;' + dets[0].alto_mm + ' mm' : '&#8212;');

    html += `<tr class="inv-row-grupo" onclick="rdToggleAlmacen(${gi})">
      <td style="text-align:center">${multi ? '<span id="inv-tog-'+gi+'">&#9654;</span>' : ''}</td>
      <td><strong>${g.tipo}</strong></td>
      <td>${g.espesor_mm} mm</td>
      <td style="color:var(--muted);font-size:12px">${dimText}</td>
      <td style="text-align:right"><strong>${fmtN(g.en_stock)}</strong>${lbl('l&#225;m.')}</td>
      <td style="text-align:right;color:#16a34a">${fmt(g.precio_min_m2)}</td>
      <td style="text-align:right;color:#dc2626">${fmt(g.precio_max_m2)}</td>
      <td style="text-align:right"><strong style="color:var(--blue);font-size:14px">${fmt(g.costo_prom_m2)}</strong></td>
      <td style="text-align:right">${fmt(g.prom_lamina)}</td>
    </tr>`;

    if (multi) {
      dets.forEach(d => {
        html += `<tr class="inv-det-${gi}" style="display:none;background:#fafbfc">
          <td></td>
          <td style="padding-left:24px;color:var(--muted);font-size:12px">${d.tipo}</td>
          <td style="color:var(--muted);font-size:12px">${d.espesor_mm} mm</td>
          <td style="font-size:12px">${d.ancho_mm}&#215;${d.alto_mm} mm${lbl(parseFloat(d.m2).toFixed(4)+' m&#178;/l&#225;m.')}</td>
          <td style="text-align:right;font-size:12px"><strong>${fmtN(d.en_stock)}</strong>${lbl('l&#225;m.')}</td>
          <td style="text-align:right;font-size:12px;color:#16a34a">${fmt(d.precio_min_m2)}</td>
          <td style="text-align:right;font-size:12px;color:#dc2626">${fmt(d.precio_max_m2)}</td>
          <td style="text-align:right;font-size:12px;color:var(--blue)"><strong>${fmt(d.costo_prom_m2)}</strong></td>
          <td style="text-align:right;font-size:12px">${fmt(d.costo_prom_lamina)}</td>
        </tr>`;
      });
    }
  });

  html += `</tbody></table></div>`;
  return html;
}

function rdRenderRentabilidad(inv) {
  const porTipo  = (inv && inv.por_tipo)  ? inv.por_tipo  : [];
  const cristales = (inv && inv.cristales) ? inv.cristales : [];
  if (!porTipo.length || !cristales.length) return '';

  const tipoLbl = {
    claro:'Claro', claro_zafiro:'Claro Zafiro', filtrasol:'Filtrasol',
    espejo:'Espejo', espejo_aluminio:'Espejo Aluminio', laminado_claro:'Laminado Claro',
    reflecta:'Reflecta', satinado:'Satinado', tintex:'Tintex'
  };

  // Índice de cristales por nombre normalizado: "Claro 9mm" → "claro9mm"
  const cristalMap = {};
  cristales.forEach(c => {
    cristalMap[c.nombre.toLowerCase().replace(/\s+/g, '')] = c;
  });

  const rows = [];
  porTipo.forEach(g => {
    if (!g.costo_prom_m2) return;
    const nombreNorm = ((tipoLbl[g.tipo] || g.tipo) + g.espesor_mm + 'mm').toLowerCase().replace(/\s+/g, '');
    const cristal = cristalMap[nombreNorm];
    if (!cristal || !parseFloat(cristal.precio_m2)) return;
    const costo    = parseFloat(g.costo_prom_m2);
    const precio   = parseFloat(cristal.precio_m2);
    const utilidad = precio - costo;
    const markup   = costo > 0 ? (utilidad / costo) * 100 : null;
    const margen   = precio > 0 ? (utilidad / precio) * 100 : null;
    rows.push({ tipo: g.tipo, espesor: g.espesor_mm, costo, precio, utilidad, markup, margen });
  });

  if (!rows.length) return '';
  rows.sort((a, b) => b.margen - a.margen);

  const fmt    = n => n != null ? '$' + parseFloat(n).toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}) : '&#8212;';
  const fmtPct = n => n != null ? parseFloat(n).toFixed(1) + '%' : '&#8212;';
  const clrMargen = m => m >= 40 ? '#16a34a' : m >= 25 ? '#ca8a04' : '#dc2626';

  let html = `<div class="section-title" style="margin-top:24px">&#x1F4CA; An&#225;lisis de Rentabilidad</div>
  <div class="table-card"><table>
    <thead><tr>
      <th>Tipo de vidrio</th>
      <th>Espesor</th>
      <th style="text-align:right">Costo prom./m&#178;</th>
      <th style="text-align:right">Precio venta/m&#178;</th>
      <th style="text-align:right">Utilidad bruta/m&#178;</th>
      <th style="text-align:right">Mark-up</th>
      <th style="text-align:right">Margen %</th>
    </tr></thead><tbody>`;

  rows.forEach(r => {
    html += `<tr>
      <td><strong>${r.tipo}</strong></td>
      <td>${r.espesor} mm</td>
      <td style="text-align:right">${fmt(r.costo)}</td>
      <td style="text-align:right">${fmt(r.precio)}</td>
      <td style="text-align:right;color:#16a34a"><strong>${fmt(r.utilidad)}</strong></td>
      <td style="text-align:right">${fmtPct(r.markup)}</td>
      <td style="text-align:right;font-weight:800;font-size:14px;color:${clrMargen(r.margen)}">${fmtPct(r.margen)}</td>
    </tr>`;
  });

  html += `</tbody></table></div>`;
  return html;
}

function rdToggleAlmacen(gi) {
  const rows   = document.querySelectorAll('.inv-det-' + gi);
  const toggle = document.getElementById('inv-tog-' + gi);
  const open   = rows.length > 0 && rows[0].style.display !== 'none';
  rows.forEach(r => r.style.display = open ? 'none' : '');
  if (toggle) toggle.innerHTML = open ? '&#9654;' : '&#9660;';
}

rdCargar();
setInterval(rdCargar, 300000);

window.rdCargar      = rdCargar;
window.rdToggleAlmacen = rdToggleAlmacen;
return{init:rdCargar};
})();
ModReporte.init();
</script>