<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
?>
<meta charset="UTF-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; }
#omRoot { font-family: 'Outfit', system-ui, -apple-system, sans-serif; }
#omRoot .main { padding: 28px 32px 48px; max-width: 980px; margin: 0 auto; }

/* ── Hero ── */
.om-hero {
  display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap;
  gap: 20px; padding-bottom: 24px; margin-bottom: 22px; border-bottom: 1px solid #e2e8f0;
}
.om-hero-eyebrow { font-size: 11px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: #64748b; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.om-hero-eyebrow svg { color: #94a3b8; }
.om-hero-periodo { font-size: 15px; font-weight: 500; color: #334155; }
.om-hero-numero-wrap { text-align: right; }
.om-hero-label { font-size: 11px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.om-hero-numero { font-size: 38px; font-weight: 700; letter-spacing: -.01em; color: #0f172a; font-variant-numeric: tabular-nums; line-height: 1; }
.om-hero-sub { font-size: 12.5px; font-weight: 600; margin-top: 6px; }
.om-hero-sub.baja  { color: #0f766e; }
.om-hero-sub.media { color: #b45309; }
.om-hero-sub.alta  { color: #9f1239; }

/* ── Controles ── */
.om-controls { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; margin-bottom: 22px; font-size: 12.5px; color: #64748b; }
.om-controls input[type=date] {
  padding: 6px 9px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12.5px;
  font-family: inherit; background: #fff; color: #334155;
}
.om-controls input[type=date]:focus-visible { outline: 2px solid #0f766e; outline-offset: 1px; }

.om-section-title { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .1em; color: #64748b; margin: 30px 0 10px; }

/* ── Tablas ── */
.om-wrap-table { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow-x: auto; }
.om-table { border-collapse: collapse; width: 100%; font-size: 13.5px; }
.om-table th { text-align: right; font-weight: 600; color: #64748b; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; padding: 14px 18px 10px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
.om-table th:first-child, .om-table td:first-child { text-align: left; }
.om-table td { padding: 10px 18px; text-align: right; font-variant-numeric: tabular-nums; color: #334155; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
.om-table tr:last-child td { border-bottom: none; }
.om-estacion { font-weight: 600; color: #0f172a; }
.om-val-cero { color: #cbd5e1; }
.om-val-omit { color: #9f1239; font-weight: 600; }
.om-pct { font-weight: 700; }
.om-pct.baja  { color: #0f766e; }
.om-pct.media { color: #b45309; }
.om-pct.alta  { color: #9f1239; }

.om-bono { display: inline-block; padding: 3px 9px; border-radius: 4px; font-size: 11px; font-weight: 600; letter-spacing: .03em; }
.om-bono.con { background: #f0fdfa; color: #0f766e; }
.om-bono.sin { background: #fef2f2; color: #9f1239; }

.om-detalle td { white-space: normal; }
.om-detalle .col-fecha { color: #78716c; font-size: 12.5px; white-space: nowrap; }
.om-detalle .col-orden strong { color: #1e293b; font-weight: 600; }
.om-detalle .col-orden span { display: block; font-size: 11.5px; color: #94a3b8; margin-top: 1px; }
.om-detalle .col-pieza { font-size: 12.5px; color: #57534e; }
.om-detalle .col-pieza span { display: block; color: #94a3b8; margin-top: 1px; }
.om-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 500; background: #fef2f2; color: #9f1239; margin: 1px 3px 1px 0; }

.om-empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 13.5px; }
.om-spinner {
  width: 20px; height: 20px; margin: 0 auto 10px; border-radius: 50%;
  border: 2.5px solid #e2e8f0; border-top-color: #64748b;
  animation: om-girar .7s linear infinite;
}
@keyframes om-girar { to { transform: rotate(360deg); } }

@media (max-width: 640px) {
  #omRoot .main { padding: 20px 16px 36px; }
  .om-hero { flex-direction: column; align-items: flex-start; }
  .om-hero-numero-wrap { text-align: left; }
  .om-hero-numero { font-size: 30px; }
  .om-controls { justify-content: flex-start; }
}
</style>

<div id="omRoot">
<div class="main">

  <div class="om-hero">
    <div>
      <div class="om-hero-eyebrow"><?= icono('alert-triangle', 13) ?> Tablero de Omisiones</div>
      <div class="om-hero-periodo" id="omHeroPeriodo">&mdash;</div>
    </div>
    <div class="om-hero-numero-wrap">
      <div class="om-hero-label">Piezas terminadas</div>
      <div class="om-hero-numero" id="omHeroNumero">&mdash;</div>
      <div class="om-hero-sub" id="omHeroSub"></div>
    </div>
  </div>

  <div class="om-controls">
    <span>Del</span>
    <input type="date" id="omDesde">
    <span>al</span>
    <input type="date" id="omHasta">
  </div>

  <div class="om-section-title">Piezas escaneadas vs. omitidas por estación</div>
  <div class="om-wrap-table">
    <table class="om-table">
      <thead>
        <tr>
          <th>Estación</th>
          <th>Escaneadas</th>
          <th>Omitidas</th>
          <th>Total que pasó por la estación</th>
          <th>% Omisión</th>
          <th>Bono</th>
        </tr>
      </thead>
      <tbody id="omTablaEstaciones"></tbody>
    </table>
  </div>

  <div class="om-section-title">Detalle de piezas con omisión</div>
  <div class="om-wrap-table">
    <table class="om-table om-detalle">
      <thead>
        <tr>
          <th style="text-align:left">Terminada</th>
          <th style="text-align:left">Orden</th>
          <th style="text-align:left">Pieza</th>
          <th style="text-align:left">Estaciones omitidas</th>
        </tr>
      </thead>
      <tbody id="omTablaDetalle"></tbody>
    </table>
  </div>

</div>
</div>

<script>
var ModOmisiones = (function() {

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = (s === null || s === undefined) ? '' : String(s);
    return d.innerHTML;
  }

  function fmtFechaHora(s) {
    if (!s) return '—';
    var d = new Date(s.replace(' ','T'));
    return d.toLocaleDateString('es-MX',{day:'2-digit',month:'short'}) + ' ' +
           d.toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  }

  function fmtFechaCorta(s) {
    var d = new Date(s + 'T12:00:00');
    return d.toLocaleDateString('es-MX', {day:'2-digit', month:'short', year:'numeric'});
  }

  function pctClass(pct) {
    if (pct >= 20) return 'alta';
    if (pct >= 5)  return 'media';
    return 'baja';
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
    var tbEst = document.getElementById('omTablaEstaciones');
    var tbDet = document.getElementById('omTablaDetalle');
    tbEst.innerHTML = '<tr><td colspan="6"><div class="om-empty"><div class="om-spinner"></div>Cargando...</div></td></tr>';
    tbDet.innerHTML = '';
    fetch('../api/omisiones.php?accion=lista&desde=' + desde + '&hasta=' + hasta)
      .then(function(r) { return r.json(); })
      .then(function(d) { if (d.ok) render(d, desde, hasta); })
      .catch(function() {
        tbEst.innerHTML = '<tr><td colspan="6"><div class="om-empty" style="color:#9f1239">Error al cargar</div></td></tr>';
      });
  }

  function render(d, desde, hasta) {
    document.getElementById('omHeroPeriodo').textContent = fmtFechaCorta(desde) + ' — ' + fmtFechaCorta(hasta);

    var totalTerminadas = parseInt(d.total_terminadas || 0);
    document.getElementById('omHeroNumero').textContent = totalTerminadas;

    var rows = d.estaciones || [];
    var sumOmit = 0, sumTotal = 0;
    rows.forEach(function(r) { sumOmit += parseInt(r.omitidas); sumTotal += parseInt(r.total); });
    var pctGlobal = sumTotal > 0 ? (sumOmit / sumTotal * 100) : 0;

    var sub = document.getElementById('omHeroSub');
    if (sumTotal > 0) {
      sub.className = 'om-hero-sub ' + pctClass(pctGlobal);
      sub.textContent = pctGlobal.toFixed(1) + '% de omisión global (' + sumOmit + ' de ' + sumTotal + ')';
    } else {
      sub.className = 'om-hero-sub';
      sub.textContent = '';
    }

    renderEstaciones(rows);
    renderDetalle(d.detalle || []);
  }

  function renderEstaciones(rows) {
    var tb = document.getElementById('omTablaEstaciones');
    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="6"><div class="om-empty">Sin piezas terminadas en el período seleccionado</div></td></tr>';
      return;
    }
    var html = '';
    rows.forEach(function(r) {
      var omitClass = parseInt(r.omitidas) > 0 ? 'om-val-omit' : 'om-val-cero';
      var conBono = r.pct_omision < 5;
      html += '<tr>' +
        '<td class="om-estacion">' + esc(r.label) + '</td>' +
        '<td>' + r.escaneadas + '</td>' +
        '<td class="' + omitClass + '">' + r.omitidas + '</td>' +
        '<td>' + r.total + '</td>' +
        '<td><span class="om-pct ' + pctClass(r.pct_omision) + '">' + r.pct_omision.toFixed(1) + '%</span></td>' +
        '<td><span class="om-bono ' + (conBono ? 'con' : 'sin') + '">' + (conBono ? 'CON BONO' : 'SIN BONO') + '</span></td>' +
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
      var tags = (r.estaciones_omitidas || []).map(function(e) {
        return '<span class="om-tag">' + esc(e) + '</span>';
      }).join('');
      html += '<tr>' +
        '<td class="col-fecha">' + fmtFechaHora(r.fecha_terminado) + '</td>' +
        '<td class="col-orden"><strong>' + esc(r.folio) + '</strong><span>' + esc(r.cliente_nombre) + '</span></td>' +
        '<td class="col-pieza">P' + esc(r.partida) + ' · ' + esc(r.pieza_num) + '/' + esc(r.pieza_total) +
             '<span>' + esc(r.ancho_mm) + '&times;' + esc(r.alto_mm) + ' mm</span></td>' +
        '<td>' + tags + '</td>' +
        '</tr>';
    });
    tb.innerHTML = html;
  }

  return { init: init, cargar: cargar };
})();

document.getElementById('omDesde').addEventListener('change', ModOmisiones.cargar);
document.getElementById('omHasta').addEventListener('change', ModOmisiones.cargar);

ModOmisiones.init();
</script>
