<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=contabilidad_balance'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.main { padding: 24px; max-width: 1100px; margin: 0 auto; }

.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.wip-badge { font-size: 10px; background: #f59e0b; color: #000; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.wip-banner {
  background: #fef3c7; color: #92400e; font-size: 13px; padding: 10px 16px;
  border-radius: 8px; margin-bottom: 20px;
}

.corte-selector { display: flex; align-items: center; gap: 8px; }
.corte-selector label { font-size: 12.5px; color: #64748b; font-weight: 700; }
.corte-selector input { padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; }

.cuadra-badge { font-size: 12px; font-weight: 800; padding: 5px 12px; border-radius: 20px; }
.cuadra-si { background: #dcfce7; color: #15803d; }
.cuadra-no { background: #fee2e2; color: #b91c1c; }

.section-h { font-size: 15px; font-weight: 800; color: #1e293b; margin: 32px 0 10px; display: flex; align-items: center; gap: 10px; }
.section-h:first-of-type { margin-top: 0; }

.tabla-wrap { background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.06); overflow-x: auto; margin-bottom: 12px; }
table.contab { border-collapse: collapse; width: 100%; font-size: 13.5px; }
table.contab th, table.contab td { padding: 9px 16px; white-space: nowrap; }
table.contab th { text-align: right; font-weight: 700; color: #64748b; border-bottom: 2px solid #e2e8f0; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; }
table.contab th:first-child, table.contab td:first-child { text-align: left; white-space: normal; min-width: 220px; }
table.contab td { text-align: right; font-variant-numeric: tabular-nums; color: #1e293b; border-top: 1px solid #f1f5f9; }
table.contab td.concepto { color: #334155; text-align: left; }
table.contab tr.total td { font-weight: 800; border-top: 2px solid #1e293b; color: #1e293b; }

.balance-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 760px) { .balance-grid { grid-template-columns: 1fr; } }
.balance-col-h { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-bottom: 8px; }

.empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }
</style>

<div class="main">

  <div class="top-bar">
    <div class="section-title"><?= icono('activity') ?> Balance General <span class="wip-badge">WIP</span></div>
    <div class="corte-selector">
      <label>Periodo</label>
      <select id="fPeriodo">
        <option value="hoy">Hoy</option>
        <option value="mes_anterior">Cierre mes anterior</option>
        <option value="trimestre">Cierre trimestre anterior</option>
        <option value="semestre">Cierre semestre anterior</option>
        <option value="anual">Cierre año anterior</option>
        <option value="custom">Fecha personalizada</option>
      </select>
      <label>Corte al</label>
      <input type="date" id="fCorte">
    </div>
  </div>

  <div class="wip-banner">Se calcula 100% desde las Pólizas (nunca se captura a mano) — es la prueba de que el sistema cuadra solo. Hoy las pólizas automáticas solo cubren Compras, Nómina, Gastos Fijos y Caja Chica; Ventas/Cobros todavía no generan póliza, así que Ingresos y Cuentas por Cobrar pueden verse bajos o en $0 hasta que se conecte esa parte.</div>

  <div id="contenido"><div class="empty">Cargando...</div></div>

</div>

<script>
var ModContabilidadBalance = (function(){
var API = '../api/contabilidad_balance.php';

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s === null || s === undefined) ? '' : String(s);
  return d.innerHTML;
}

function fmt(n) {
  n = parseFloat(n || 0);
  var neg = n < 0;
  var s = '$' + Math.abs(n).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  return neg ? '(' + s + ')' : s;
}

function hoy() {
  return fechaISO(new Date());
}

function fechaISO(d) {
  return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
}

// El Balance General es una foto a una fecha (no un rango como el P&L) — cada opción
// de período fija el "corte" al último día de ESE período ya cerrado, para poder
// comparar snapshots (hoy vs. cierre de mes/trimestre/semestre/año anterior).
function fechaCorteParaPeriodo(periodo) {
  var hoyD = new Date();
  var anio = hoyD.getFullYear();
  var mes0 = hoyD.getMonth(); // 0-indexado

  if (periodo === 'mes_anterior') {
    return fechaISO(new Date(anio, mes0, 0)); // día 0 del mes actual = último día del mes anterior
  }
  if (periodo === 'trimestre') {
    var qStartMonth = Math.floor(mes0 / 3) * 3;
    return fechaISO(new Date(anio, qStartMonth, 0)); // último día del trimestre calendario anterior
  }
  if (periodo === 'semestre') {
    var sStartMonth = mes0 < 6 ? 0 : 6;
    return fechaISO(new Date(anio, sStartMonth, 0)); // último día del semestre calendario anterior
  }
  if (periodo === 'anual') {
    return fechaISO(new Date(anio - 1, 11, 31)); // 31-dic del año anterior
  }
  return hoy(); // 'hoy' (default)
}

function onPeriodoChange() {
  var periodo = document.getElementById('fPeriodo').value;
  if (periodo === 'custom') return; // deja la fecha tal cual, el usuario la va a picar a mano
  document.getElementById('fCorte').value = fechaCorteParaPeriodo(periodo);
  cargar();
}

function onCorteChange() {
  document.getElementById('fPeriodo').value = 'custom';
  cargar();
}

async function cargar() {
  var corte = document.getElementById('fCorte').value || hoy();
  document.getElementById('fCorte').value = corte;
  document.getElementById('contenido').innerHTML = '<div class="empty">Cargando...</div>';
  try {
    var res = await fetch(API + '?corte=' + corte);
    var data = await res.json();
    if (data.error) { document.getElementById('contenido').innerHTML = '<div class="empty" style="color:#dc2626">' + esc(data.error) + '</div>'; return; }
    render(data);
  } catch(e) {
    document.getElementById('contenido').innerHTML = '<div class="empty" style="color:#dc2626">Error al cargar</div>';
  }
}

function columnaCuentas(titulo, cuentas, total) {
  var html = '<div class="balance-col-h">' + esc(titulo) + '</div>';
  html += '<div class="tabla-wrap"><table class="contab"><tbody>';
  if (!cuentas.length) {
    html += '<tr><td class="concepto" style="color:#94a3b8;font-style:italic">Sin movimientos</td><td></td></tr>';
  } else {
    for (var i = 0; i < cuentas.length; i++) {
      html += '<tr><td class="concepto">' + esc(cuentas[i].codigo) + ' — ' + esc(cuentas[i].nombre) + '</td><td>' + fmt(cuentas[i].saldo) + '</td></tr>';
    }
  }
  html += '<tr class="total"><td class="concepto">TOTAL</td><td>' + fmt(total) + '</td></tr>';
  html += '</tbody></table></div>';
  return html;
}

function render(data) {
  var b = data.balance;
  var comp = data.comprobacion;

  var html = '<div class="section-h">Balance de Comprobación</div>';
  html += '<div class="tabla-wrap"><table class="contab"><thead><tr><th style="text-align:left">Cuenta</th><th>Debe</th><th>Haber</th><th>Saldo</th></tr></thead><tbody>';
  if (!comp.length) {
    html += '<tr><td class="concepto" colspan="4" style="text-align:center;color:#94a3b8;font-style:italic">Sin pólizas registradas hasta esta fecha</td></tr>';
  } else {
    for (var i = 0; i < comp.length; i++) {
      var c = comp[i];
      html += '<tr><td class="concepto">' + esc(c.codigo) + ' — ' + esc(c.nombre) + '</td><td>' + fmt(c.debe) + '</td><td>' + fmt(c.haber) + '</td><td>' + fmt(c.saldo) + '</td></tr>';
    }
  }
  html += '</tbody></table></div>';

  html += '<div class="section-h">Balance General <span class="cuadra-badge ' + (b.cuadra ? 'cuadra-si' : 'cuadra-no') + '">' + (b.cuadra ? 'Cuadra ✓ (Activo = Pasivo + Capital)' : 'No cuadra — revisar') + '</span></div>';

  html += '<div class="balance-grid">';
  html += '<div>' + columnaCuentas('Activo', b.activo, b.activo_total) + '</div>';

  var pasivoCapital = b.pasivo.concat(b.capital);
  html += '<div>' + columnaCuentas('Pasivo + Capital', pasivoCapital, 0) + '</div>';
  html = html.replace(/{{PENDIENTE}}/, '');
  html += '</div>';

  document.getElementById('contenido').innerHTML = html;

  // Segunda columna necesita su propio total (Pasivo+Capital+Resultado del periodo), se arma aparte
  // porque columnaCuentas() no sabe sumar una línea extra de "Resultado del periodo".
  var col2 = document.querySelectorAll('.balance-grid .tabla-wrap')[1];
  var filaResultado = document.createElement('tr');
  filaResultado.innerHTML = '<td class="concepto">Resultado del periodo (Ingresos − Costos − Gastos)</td><td>' + fmt(b.resultado_periodo) + '</td>';
  var totalRow = col2.querySelector('tr.total');
  col2.querySelector('tbody').insertBefore(filaResultado, totalRow);
  totalRow.innerHTML = '<td class="concepto">TOTAL</td><td>' + fmt(b.pasivo_mas_capital) + '</td>';
}

document.getElementById('fCorte').value = hoy();
document.getElementById('fPeriodo').value = 'hoy';
document.getElementById('fPeriodo').addEventListener('change', onPeriodoChange);
document.getElementById('fCorte').addEventListener('change', onCorteChange);
cargar();

return { init: cargar };
})();
</script>
