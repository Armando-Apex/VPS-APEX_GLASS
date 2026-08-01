<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=contabilidad_pnl'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.main { padding: 24px; max-width: 900px; margin: 0 auto; }

.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.wip-badge { font-size: 10px; background: #f59e0b; color: #000; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.wip-banner {
  background: #fef3c7; color: #92400e; font-size: 13px; padding: 10px 16px;
  border-radius: 8px; margin-bottom: 20px;
}
.aviso-cobertura {
  background: #fee2e2; color: #991b1b; font-size: 13px; padding: 10px 16px;
  border-radius: 8px; margin-bottom: 20px; display: none;
}

.rango-selector { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rango-selector input { padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; }

.pnl-card { background: white; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,.06); overflow: hidden; }
.pnl-row { display: flex; justify-content: space-between; padding: 12px 20px; border-top: 1px solid #f1f5f9; font-size: 14px; }
.pnl-row:first-child { border-top: none; }
.pnl-row .linea-nombre { color: #64748b; padding-left: 18px; }
.pnl-row .monto { font-variant-numeric: tabular-nums; }
.pnl-row.resta .monto { color: #dc2626; }
.pnl-row.subtotal { background: #f8fafc; font-weight: 800; color: #1e293b; font-size: 15px; }
.pnl-row.subtotal.positivo .monto { color: #16a34a; }
.pnl-row.subtotal.negativo .monto { color: #dc2626; }
.pnl-row.final { background: #1e293b; color: white; font-weight: 800; font-size: 17px; }
.pnl-row.final .monto { color: white; }
.pnl-row.vacio { color: #94a3b8; font-style: italic; padding-left: 18px; }

.empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }
</style>

<div class="main">

  <div class="top-bar">
    <div class="section-title"><?= icono('bar-chart-2') ?> Estado de Resultados (P&amp;L) <span class="wip-badge">WIP</span></div>
    <div class="rango-selector">
      <select id="rangoPreset">
        <option value="mes_actual">Mes actual</option>
        <option value="mes_anterior" selected>Mes anterior</option>
        <option value="2_meses_atras">2 meses atrás</option>
        <option value="trimestre">Trimestre (últimos 3 meses)</option>
        <option value="semestre">Semestre (últimos 6 meses)</option>
        <option value="anio">Año actual</option>
        <option value="personalizado">Personalizado</option>
      </select>
      <input type="date" id="fDesde">
      <span style="color:#94a3b8">a</span>
      <input type="date" id="fHasta">
    </div>
  </div>

  <div class="wip-banner">Módulo en construcción — el costo de ventas solo es confiable desde el 21-jul-2026 (fecha en que arrancó el trazo de consumo real por pieza). Rangos anteriores mostrarán costo subestimado y margen falsamente alto.</div>

  <div class="aviso-cobertura" id="avisoCobertura"></div>

  <div class="pnl-card" id="pnlContent">
    <div class="empty">Cargando...</div>
  </div>

</div>

<script>
var ModContabilidadPnl = (function(){
var API = '../api/contabilidad_pnl.php';

function fmt(n) {
  n = parseFloat(n || 0);
  var neg = n < 0;
  var s = '$' + Math.abs(n).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  return neg ? '-' + s : s;
}

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s === null || s === undefined) ? '' : String(s);
  return d.innerHTML;
}

function hoy() {
  var d = new Date();
  return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
}

function pad2(n) { return ('0' + n).slice(-2); }
function fechaStr(y, m, d) { return y + '-' + pad2(m) + '-' + pad2(d); }
function ultimoDiaMes(y, m) { return new Date(y, m, 0).getDate(); }

function calcularRango(preset) {
  var hoyD = new Date();
  var y = hoyD.getFullYear();
  var m = hoyD.getMonth() + 1;
  var py, pm;

  if (preset === 'mes_actual') {
    return { desde: fechaStr(y, m, 1), hasta: hoy() };
  }
  if (preset === 'mes_anterior') {
    py = y; pm = m - 1;
    if (pm === 0) { pm = 12; py = y - 1; }
    return { desde: fechaStr(py, pm, 1), hasta: fechaStr(py, pm, ultimoDiaMes(py, pm)) };
  }
  if (preset === '2_meses_atras') {
    py = y; pm = m - 2;
    if (pm <= 0) { pm += 12; py = y - 1; }
    return { desde: fechaStr(py, pm, 1), hasta: fechaStr(py, pm, ultimoDiaMes(py, pm)) };
  }
  if (preset === 'trimestre') {
    py = y; pm = m - 2;
    if (pm <= 0) { pm += 12; py = y - 1; }
    return { desde: fechaStr(py, pm, 1), hasta: hoy() };
  }
  if (preset === 'semestre') {
    py = y; pm = m - 5;
    if (pm <= 0) { pm += 12; py = y - 1; }
    return { desde: fechaStr(py, pm, 1), hasta: hoy() };
  }
  if (preset === 'anio') {
    return { desde: fechaStr(y, 1, 1), hasta: hoy() };
  }
  return null;
}

function aplicarPreset() {
  var preset = document.getElementById('rangoPreset').value;
  var rango = calcularRango(preset);
  if (rango) {
    document.getElementById('fDesde').value = rango.desde;
    document.getElementById('fHasta').value = rango.hasta;
  }
  cargar();
}

function fechaEditadaManual() {
  document.getElementById('rangoPreset').value = 'personalizado';
  cargar();
}

function bloque(titulo, lineas, total, esResta) {
  var html = '<div class="pnl-row" style="font-weight:700;color:#1e293b">' + titulo + '<span class="monto">' + fmt(total) + '</span></div>';
  if (!lineas.length) {
    html += '<div class="pnl-row vacio">Sin movimientos en este rango<span></span></div>';
  } else {
    for (var i = 0; i < lineas.length; i++) {
      var l = lineas[i];
      html += '<div class="pnl-row' + (esResta ? ' resta' : '') + '"><span class="linea-nombre">' + esc(l.codigo) + ' — ' + esc(l.nombre) + '</span><span class="monto">' + (esResta ? '-' : '') + fmt(l.monto) + '</span></div>';
    }
  }
  return html;
}

async function cargar() {
  var desde = document.getElementById('fDesde').value || inicioMes();
  var hasta = document.getElementById('fHasta').value || hoy();
  document.getElementById('fDesde').value = desde;
  document.getElementById('fHasta').value = hasta;

  try {
    var res = await fetch(API + '?desde=' + desde + '&hasta=' + hasta);
    var data = await res.json();
    if (data.error) { document.getElementById('pnlContent').innerHTML = '<div class="empty" style="color:#dc2626">' + esc(data.error) + '</div>'; return; }
    render(data);
  } catch(e) {
    document.getElementById('pnlContent').innerHTML = '<div class="empty" style="color:#dc2626">Error al cargar</div>';
  }
}

function render(d) {
  var cob = d.costo_ventas.cobertura;
  var avisos = [];
  if (cob && !cob.confiable) {
    avisos.push('Aviso: solo ' + cob.pct_cobertura + '% de las piezas entregadas en este rango tienen costo real trazado (' + cob.piezas_con_costo + ' de ' + cob.piezas_total + '). El costo de ventas está subestimado y el margen mostrado no es confiable.');
  }
  if (d.compras_sin_mapear && d.compras_sin_mapear.length) {
    var totalSinMapear = 0;
    var nombres = [];
    for (var si = 0; si < d.compras_sin_mapear.length; si++) {
      totalSinMapear += parseFloat(d.compras_sin_mapear[si].monto);
      nombres.push(d.compras_sin_mapear[si].categoria);
    }
    avisos.push('Aviso: hay ' + fmt(totalSinMapear) + ' en pagos de Compras (' + nombres.join(', ') + ') sin cuenta contable asignada todavía — no están incluidos en Gastos Operativos. Asígnalas en Contabilidad → Mapeo Compras.');
  }
  var avisoEl = document.getElementById('avisoCobertura');
  if (avisos.length) {
    avisoEl.style.display = 'block';
    avisoEl.innerHTML = avisos.map(esc).join('<br>');
  } else {
    avisoEl.style.display = 'none';
  }

  var html = '';
  html += bloque('Ingresos', d.ingresos.lineas, d.ingresos.total, false);
  html += bloque('(-) Costo de Ventas', d.costo_ventas.lineas, d.costo_ventas.total, true);
  html += '<div class="pnl-row subtotal ' + (d.utilidad_bruta >= 0 ? 'positivo' : 'negativo') + '">Utilidad Bruta<span class="monto">' + fmt(d.utilidad_bruta) + '</span></div>';

  html += bloque('(-) Gastos Operativos', d.gastos_operativos.lineas, d.gastos_operativos.total, true);
  html += '<div class="pnl-row subtotal ' + (d.utilidad_operativa >= 0 ? 'positivo' : 'negativo') + '">Utilidad Operativa<span class="monto">' + fmt(d.utilidad_operativa) + '</span></div>';

  if (d.financieros.lineas.length) {
    html += bloque('(-) Gastos Financieros', d.financieros.lineas, d.financieros.total, true);
  }
  if (d.impuestos.lineas.length) {
    html += bloque('(-) Impuestos', d.impuestos.lineas, d.impuestos.total, true);
  }

  html += '<div class="pnl-row final">Utilidad Neta<span class="monto">' + fmt(d.utilidad_neta) + '</span></div>';

  document.getElementById('pnlContent').innerHTML = html;
}

document.getElementById('rangoPreset').addEventListener('change', aplicarPreset);
document.getElementById('fDesde').addEventListener('change', fechaEditadaManual);
document.getElementById('fHasta').addEventListener('change', fechaEditadaManual);

aplicarPreset();

return { init: cargar };
})();
</script>
