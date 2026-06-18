<?php /* modulos/omisiones.php — Tablero de Omisiones de Estación */ ?>
<style>
.om-wrap { padding: 24px; max-width: 1100px; margin: 0 auto; }
.om-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px; }
.om-title { font-size: 20px; font-weight: 700; color: #1e293b; }
.om-filtros { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.om-filtros input[type=date] { border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 10px; font-size: 13px; color: #1e293b; }
.om-filtros button { padding: 7px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
.om-kpis { display: grid; grid-template-columns: repeat(3,1fr); gap: 14px; margin-bottom: 24px; }
.om-kpi { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 20px; }
.om-kpi-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 6px; }
.om-kpi-val { font-size: 32px; font-weight: 800; color: #1e293b; line-height: 1; }
.om-kpi-val.alerta { color: #dc2626; }
.om-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 10px; }
.om-barras { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
.om-barra-item { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; min-width: 160px; flex: 1; }
.om-barra-nombre { font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 6px; }
.om-barra-track { background: #f1f5f9; border-radius: 4px; height: 8px; margin-bottom: 4px; overflow: hidden; }
.om-barra-fill { height: 100%; background: #f59e0b; border-radius: 4px; transition: width .4s; }
.om-barra-count { font-size: 13px; font-weight: 800; color: #d97706; }
.table-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
.table-card table { width: 100%; border-collapse: collapse; font-size: 13px; }
.table-card th { background: #f8fafc; padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; border-bottom: 1px solid #e2e8f0; }
.table-card td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; color: #374151; }
.table-card tr:last-child td { border-bottom: none; }
.table-card tr:hover td { background: #f8fafc; }
.om-pill { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #fef3c7; color: #d97706; border: 1px solid #fcd34d; }
.om-empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 14px; }
@media(max-width:640px){ .om-kpis{grid-template-columns:1fr 1fr;} .om-kpi-val{font-size:24px;} }
</style>

<div class="om-wrap">
  <div class="om-header">
    <div class="om-title">&#9888;&#65039; Tablero de Omisiones</div>
    <div class="om-filtros">
      <input type="date" id="omDesde">
      <input type="date" id="omHasta">
      <button onclick="omCargar()">Filtrar</button>
    </div>
  </div>

  <div class="om-kpis">
    <div class="om-kpi">
      <div class="om-kpi-label">Hoy</div>
      <div class="om-kpi-val" id="omKpiHoy">—</div>
    </div>
    <div class="om-kpi">
      <div class="om-kpi-label">Esta semana</div>
      <div class="om-kpi-val" id="omKpiSemana">—</div>
    </div>
    <div class="om-kpi">
      <div class="om-kpi-label">En el período</div>
      <div class="om-kpi-val" id="omKpiPeriodo">—</div>
    </div>
  </div>

  <div class="om-section-title">Por estación omitida</div>
  <div class="om-barras" id="omBarras"></div>

  <div class="om-section-title">Detalle de omisiones</div>
  <div class="table-card">
    <table>
      <thead>
        <tr>
          <th>Fecha y hora</th>
          <th>Orden</th>
          <th>Pieza</th>
          <th>Estación omitida</th>
          <th>Avanzó a</th>
          <th>Reportó</th>
        </tr>
      </thead>
      <tbody id="omTabla"></tbody>
    </table>
  </div>
</div>

<script>
var ModOmisiones = (function() {

  var EST_LABEL = {
    pendiente: 'Pendiente', en_corte: 'En CNC', cortado: 'Corte',
    canteado: 'Canteado', trazo: 'Trazo', taladro: 'Taladro',
    en_horno: 'Horno/Templado', terminado: 'Terminado', entregado: 'Entregado',
  };

  var OMITIDA_LABEL = {
    en_corte: 'CORTE (cortado)',
    cortado:  'CANTEADO',
    canteado: 'TRAZO',
    trazo:    'TALADRO',
    taladro:  'TEMPLADO/HORNO',
  };

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
    var kpi = d.kpi || {};
    var hoy = parseInt(kpi.hoy || 0);
    document.getElementById('omKpiHoy').textContent    = hoy;
    document.getElementById('omKpiSemana').textContent = parseInt(kpi.semana || 0);
    document.getElementById('omKpiPeriodo').textContent = parseInt(kpi.periodo || 0);
    if (hoy > 0) document.getElementById('omKpiHoy').classList.add('alerta');
    else          document.getElementById('omKpiHoy').classList.remove('alerta');

    renderBarras(d.por_estacion || []);
    renderTabla(d.omisiones || []);
  }

  function renderBarras(porEst) {
    var el = document.getElementById('omBarras');
    if (!porEst.length) { el.innerHTML = '<div style="color:#94a3b8;font-size:13px">Sin omisiones en el período</div>'; return; }
    var max = parseInt(porEst[0].total);
    var html = '';
    porEst.forEach(function(e) {
      var label = OMITIDA_LABEL[e.estacion_omitida] || (EST_LABEL[e.estacion_omitida] || e.estacion_omitida);
      var pct   = max > 0 ? Math.round(parseInt(e.total) / max * 100) : 0;
      html += '<div class="om-barra-item">' +
              '<div class="om-barra-nombre">' + esc(label) + '</div>' +
              '<div class="om-barra-track"><div class="om-barra-fill" style="width:' + pct + '%"></div></div>' +
              '<div class="om-barra-count">' + e.total + ' omisión' + (parseInt(e.total) !== 1 ? 'es' : '') + '</div>' +
              '</div>';
    });
    el.innerHTML = html;
  }

  function renderTabla(rows) {
    var tb = document.getElementById('omTabla');
    if (!rows.length) {
      tb.innerHTML = '<tr><td colspan="6"><div class="om-empty">Sin omisiones en el período seleccionado</div></td></tr>';
      return;
    }
    var html = '';
    rows.forEach(function(r) {
      var omitida = OMITIDA_LABEL[r.estatus_anterior] || (EST_LABEL[r.estatus_anterior] || r.estatus_anterior);
      var avanzo  = EST_LABEL[r.estatus_nuevo] || r.estatus_nuevo;
      html += '<tr>' +
        '<td style="white-space:nowrap;color:#64748b">' + fmtFecha(r.created_at) + '</td>' +
        '<td><strong>' + esc(r.folio) + '</strong><br><span style="font-size:11px;color:#64748b">' + esc(r.cliente_nombre) + '</span></td>' +
        '<td style="font-size:12px">P' + esc(r.partida) + ' · ' + esc(r.pieza_num) + '/' + esc(r.pieza_total) + '<br>' +
             '<span style="color:#64748b">' + esc(r.ancho_mm) + '×' + esc(r.alto_mm) + ' mm</span></td>' +
        '<td><span class="om-pill">' + esc(omitida) + '</span></td>' +
        '<td style="color:#16a34a;font-weight:600">' + esc(avanzo) + '</td>' +
        '<td>' + esc(r.usuario_nombre || '—') + '</td>' +
        '</tr>';
    });
    tb.innerHTML = html;
  }

  return { init: init };
})();

window.omCargar = function() { ModOmisiones.init(); };

ModOmisiones.init();
</script>
