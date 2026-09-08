<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_rh');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=rh_asistencia'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.main { padding: 24px; max-width: 1400px; margin: 0 auto; }

.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }

.semana-nav { display: flex; align-items: center; gap: 10px; }
.semana-nav button {
  width: 30px; height: 30px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: white;
  font-size: 15px; font-weight: 700; color: #374151; cursor: pointer;
}
.semana-nav button:hover { background: #f1f5f9; }
.semana-nav .label { font-size: 13px; font-weight: 700; color: #1e293b; white-space: nowrap; }
.semana-nav .sub { font-size: 11px; color: #94a3b8; }

.wip-banner {
  background: #eff6ff; color: #1e40af; font-size: 13px; padding: 10px 16px;
  border-radius: 8px; margin: 12px 0 20px;
}

.table-wrap {
  background: white; border-radius: 14px;
  overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); overflow-x: auto;
}
table { width: 100%; border-collapse: collapse; }
thead { background: #f8fafc; }
th {
  padding: 10px 12px; text-align: left;
  font-size: 11px; font-weight: 700; color: #64748b;
  text-transform: uppercase; letter-spacing: .5px; white-space: nowrap;
}
th.col-dia { text-align: center; }
td { padding: 10px 12px; border-top: 1px solid #f1f5f9; font-size: 12px; color: #374151; vertical-align: top; }
td.col-dia { text-align: center; white-space: nowrap; font-variant-numeric: tabular-nums; }
td.col-dispersion { font-weight: 700; color: #64748b; white-space: nowrap; }
td.col-nombre { white-space: nowrap; }
tr.dom td.col-dia { color: #cbd5e1; }
th.col-ref, td.col-ref { background: #f8fafc; }
th.col-ref { color: #94a3b8; }
td.col-ref.vacio, td.col-ref .vacio { color: #cbd5e1; }
.vacio { color: #cbd5e1; }
.empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }
</style>

<div class="main">
  <div class="top-bar">
    <div class="section-title"><?= icono('activity') ?> Asistencia — Checador</div>
    <div class="semana-nav">
      <button onclick="ModRHAsistencia._prevSemana()">&lsaquo;</button>
      <div>
        <div class="label" id="lblSemana">&mdash;</div>
        <div class="sub" id="lblSemanaSub">&nbsp;</div>
      </div>
      <button onclick="ModRHAsistencia._nextSemana()">&rsaquo;</button>
    </div>
  </div>

  <div class="wip-banner">
    Checadas reales capturadas por el reloj físico de planta desde el 08-sep-2026 hacia adelante — no hay historial anterior a esa fecha.
    Semana de nómina real: jueves a miércoles (domingo es descanso); la tabla se muestra de miércoles a miércoles — la primera columna (miércoles anterior, en gris) es de referencia y no cuenta como día de la semana de nómina.
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr id="filaEncabezado"></tr></thead>
      <tbody id="tablaAsistencia"><tr><td class="empty">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
var ModRHAsistencia = (function(){
var API = '../api/checador.php';
var _semana = hoyStr();

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s === null || s === undefined) ? '' : String(s);
  return d.innerHTML;
}

function hoyStr() {
  var d = new Date();
  return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
}

function fmtFecha(fechaYmd) {
  var meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  var p = fechaYmd.split('-');
  return parseInt(p[2],10) + ' ' + meses[parseInt(p[1],10)-1];
}

var DIA_LABEL = { 4:'Jue', 5:'Vie', 6:'Sáb', 0:'Dom', 1:'Lun', 2:'Mar', 3:'Mié' };

function diaSemanaLabel(fechaYmd) {
  var d = new Date(fechaYmd + 'T00:00:00');
  return DIA_LABEL[d.getDay()];
}

function prevSemana() {
  var d = new Date(_semana + 'T00:00:00');
  d.setDate(d.getDate() - 7);
  _semana = d.toISOString().slice(0,10);
  cargar();
}

function nextSemana() {
  var d = new Date(_semana + 'T00:00:00');
  d.setDate(d.getDate() + 7);
  _semana = d.toISOString().slice(0,10);
  cargar();
}

async function cargar() {
  document.getElementById('tablaAsistencia').innerHTML = '<tr><td class="empty">Cargando...</td></tr>';
  try {
    var res = await fetch(API + '?accion=asistencia_semana&semana=' + encodeURIComponent(_semana));
    var data = await res.json();
    if (data.error) { document.getElementById('tablaAsistencia').innerHTML = '<tr><td class="empty" style="color:#dc2626">' + esc(data.error) + '</td></tr>'; return; }
    render(data);
  } catch(e) {
    document.getElementById('tablaAsistencia').innerHTML = '<tr><td class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function render(data) {
  document.getElementById('lblSemana').textContent = fmtFecha(data.miercoles_anterior) + ' – ' + fmtFecha(data.fin);
  document.getElementById('lblSemanaSub').textContent = 'Semana real de nómina: ' + fmtFecha(data.inicio) + ' a ' + fmtFecha(data.fin);

  var headHtml = '<th>Disp.</th><th>Nombre</th>';
  for (var i = 0; i < data.dias.length; i++) {
    var esRef = data.dias[i] === data.miercoles_anterior;
    headHtml += '<th class="col-dia' + (esRef ? ' col-ref' : '') + '">' + diaSemanaLabel(data.dias[i]) + (esRef ? ' (ref.)' : '') + '<br>' + fmtFecha(data.dias[i]) + '</th>';
  }
  document.getElementById('filaEncabezado').innerHTML = headHtml;

  if (!data.filas.length) {
    document.getElementById('tablaAsistencia').innerHTML = '<tr><td class="empty" colspan="' + (2 + data.dias.length) + '">Sin empleados activos</td></tr>';
    return;
  }

  var html = '';
  for (var i = 0; i < data.filas.length; i++) {
    var f = data.filas[i];
    html += '<tr>';
    html += '<td class="col-dispersion">' + esc(f.numero_dispersion || '-') + '</td>';
    html += '<td class="col-nombre">' + esc(f.nombre) + (!f.checador_pin ? ' <span class="vacio">(sin PIN)</span>' : '') + '</td>';
    for (var j = 0; j < data.dias.length; j++) {
      var dFecha = data.dias[j];
      var horas = f.dias[dFecha] || [];
      var esDom = new Date(dFecha + 'T00:00:00').getDay() === 0;
      var esRef = dFecha === data.miercoles_anterior;
      html += '<td class="col-dia' + (esDom ? ' dom' : '') + (esRef ? ' col-ref' : '') + '">' + (horas.length ? esc(horas.join(' ')) : '<span class="vacio">&mdash;</span>') + '</td>';
    }
    html += '</tr>';
  }
  document.getElementById('tablaAsistencia').innerHTML = html;
}

cargar();

return {
  init: cargar,
  _prevSemana: prevSemana,
  _nextSemana: nextSemana
};
})();
</script>
