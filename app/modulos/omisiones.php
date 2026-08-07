<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
?>
<style>
.om-wrap { padding: 24px; max-width: 1100px; margin: 0 auto; }
.om-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 8px; }
.om-title { font-size: 20px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.om-title svg { color: #d97706; }
.om-filtros { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.om-filtros input[type=date] { border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 10px; font-size: 13px; color: #1e293b; }
.om-filtros button { padding: 7px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
.om-sub { font-size: 13px; color: #64748b; margin-bottom: 20px; }
.om-sub strong { color: #1e293b; }
.om-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin: 24px 0 10px; }
.table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.table-card table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table-card th { background: #f8fafc; padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.table-card td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; color: #374151; }
.table-card tr:last-child td { border-bottom: none; }
.table-card tr:hover td { background: #f8fafc; }
.om-num { text-align: right; font-variant-numeric: tabular-nums; }
.om-estacion { font-weight: 700; color: #1e293b; }
.om-omit-val { font-weight: 700; color: #dc2626; }
.om-omit-val.cero { color: #16a34a; }
.om-pct { display: inline-block; min-width: 52px; text-align: right; font-weight: 700; }
.om-pct.alta { color: #dc2626; }
.om-pct.media { color: #d97706; }
.om-pct.baja { color: #16a34a; }
.om-pill { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; margin: 1px 2px 1px 0; }
.om-empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 14px; }
@media(max-width:640px){ .om-wrap{padding:16px;} }
</style>

<div class="om-wrap">
  <div class="om-header">
    <div class="om-title"><?= icono('alert-triangle', 20) ?> Tablero de Omisiones</div>
    <div class="om-filtros">
      <input type="date" id="omDesde">
      <input type="date" id="omHasta">
      <button onclick="omCargar()">Filtrar</button>
    </div>
  </div>
  <div class="om-sub">Piezas terminadas en el período: <strong id="omTotalTerminadas">—</strong></div>

  <div class="om-section-title">Piezas escaneadas vs. omitidas por estación</div>
  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Estación</th>
          <th class="om-num"># Piezas escaneadas</th>
          <th class="om-num"># Piezas omitidas</th>
          <th class="om-num">Total piezas que pasaron por la estación</th>
          <th class="om-num">% Omisión</th>
        </tr>
      </thead>
      <tbody id="omTablaEstaciones"></tbody>
    </table>
  </div>

  <div class="om-section-title">Detalle de piezas con omisión</div>
  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Terminada</th>
          <th>Orden</th>
          <th>Pieza</th>
          <th>Estaciones omitidas</th>
        </tr>
      </thead>
      <tbody id="omTablaDetalle"></tbody>
    </table>
  </div>
</div>

<script>
var ModOmisiones = (function() {

  function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function fmtFecha(s) {
    if (!s) return '—';
    var d = new Date(s.replace(' ','T'));
    return d.toLocaleDateString('es-MX',{day:'2-digit',month:'short'}) + ' ' +
           d.toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  }

  function init() {
    var hoy    = new Date();
    var inicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    document.getElementById('omDesde').value = inicio.toISOString().slice(0,10);
    document.getElementById('omHasta').value = hoy.toISOString().slice(0,10);
    cargar();
  }

  function cargar() {
    var desde = document.getElementById('omDesde').value;
    var hasta = document.getElementById('omHasta').value;
    fetch('../api/omisiones.php?accion=lista&desde=' + desde + '&hasta=' + hasta)
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.ok) render(d); })
      .catch(function() {});
  }

  function render(d) {
    document.getElementById('omTotalTerminadas').textContent = d.total_terminadas;
    renderEstaciones(d.estaciones || []);
    renderDetalle(d.detalle || []);
  }

  function pctClass(pct) {
    if (pct >= 20) return 'alta';
    if (pct >= 5)  return 'media';
    return 'baja';
  }

  function renderEstaciones(rows) {
    var tb = document.getElementById('omTablaEstaciones');
    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="5"><div class="om-empty">Sin piezas terminadas en el período seleccionado</div></td></tr>';
      return;
    }
    var html = '';
    rows.forEach(function(r) {
      var omitClass = parseInt(r.omitidas) > 0 ? 'om-omit-val' : 'om-omit-val cero';
      html += '<tr>' +
        '<td class="om-estacion">' + esc(r.label) + '</td>' +
        '<td class="om-num">' + r.escaneadas + '</td>' +
        '<td class="om-num"><span class="' + omitClass + '">' + r.omitidas + '</span></td>' +
        '<td class="om-num">' + r.total + '</td>' +
        '<td class="om-num"><span class="om-pct ' + pctClass(r.pct_omision) + '">' + r.pct_omision.toFixed(1) + '%</span></td>' +
        '</tr>';
    });
    tb.innerHTML = html;
  }

  function renderDetalle(rows) {
    var tb = document.getElementById('omTablaDetalle');
    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="4"><div class="om-empty">Sin omisiones en el período seleccionado</div></td></tr>';
      return;
    }
    var html = '';
    rows.forEach(function(r) {
      var pills = (r.estaciones_omitidas || []).map(function(e) {
        return '<span class="om-pill">' + esc(e) + '</span>';
      }).join('');
      html += '<tr>' +
        '<td style="white-space:nowrap;color:#64748b">' + fmtFecha(r.fecha_terminado) + '</td>' +
        '<td><strong>' + esc(r.folio) + '</strong><br><span style="font-size:11px;color:#64748b">' + esc(r.cliente_nombre) + '</span></td>' +
        '<td style="font-size:12px">P' + esc(r.partida) + ' · ' + esc(r.pieza_num) + '/' + esc(r.pieza_total) + '<br>' +
             '<span style="color:#64748b">' + esc(r.ancho_mm) + '&times;' + esc(r.alto_mm) + ' mm</span></td>' +
        '<td>' + pills + '</td>' +
        '</tr>';
    });
    tb.innerHTML = html;
  }

  return { init: init, cargar: cargar };
})();

window.omCargar = function() { ModOmisiones.cargar(); };

ModOmisiones.init();
</script>
