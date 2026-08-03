<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
$puedeEditar = tienePermiso($user['rol'], 'gestionar_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=contabilidad_polizas'); exit;
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

.rango-selector { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rango-selector input { padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; }

.btn {
  padding: 9px 18px; border-radius: 8px; font-size: 13px;
  font-weight: 700; cursor: pointer; border: none; transition: opacity .15s, background-color .15s;
}
.btn:hover { opacity: .85; }
.btn:disabled { opacity: .5; cursor: not-allowed; }
.btn-primary { background: #2563eb; color: white; }
.btn-ghost   { background: #f1f5f9; color: #374151; }
.btn-success { background: #16a34a; color: white; }
.btn-danger  { background: #fee2e2; color: #dc2626; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-icon { padding: 6px 10px; font-size: 13px; line-height: 1; }

.table-wrap {
  background: white; border-radius: 14px;
  overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px;
  overflow-x: auto;
}
table { width: 100%; border-collapse: collapse; }
thead { background: #f8fafc; }
th {
  padding: 12px 14px; text-align: left;
  font-size: 11px; font-weight: 700; color: #64748b;
  text-transform: uppercase; letter-spacing: .5px; white-space: nowrap;
}
td { padding: 10px 14px; border-top: 1px solid #f1f5f9; font-size: 13px; color: #374151; white-space: nowrap; }
tr.clickable { cursor: pointer; }
tr:hover td { background: #f8fafc; }

.folio { font-family: monospace; font-weight: 700; color: #64748b; }
.badge-tipo { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #dbeafe; color: #1d4ed8; }
.badge-origen { font-size: 10.5px; font-weight: 700; padding: 3px 9px; border-radius: 20px; }
.badge-auto   { background: #dcfce7; color: #16a34a; }
.badge-manual { background: #f1f5f9; color: #64748b; }
.badge-activa { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #dcfce7; color: #16a34a; }
.badge-anulada { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #f1f5f9; color: #94a3b8; text-decoration: line-through; }

.empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }

.modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); z-index: 1000;
  align-items: center; justify-content: center; padding: 20px;
}
.modal-bg.open { display: flex; }
.modal {
  background: white; border-radius: 16px;
  padding: 28px; width: 100%; max-width: 720px;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
  max-height: 92vh; overflow-y: auto;
}
.modal h2 { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 20px; }
.field { margin-bottom: 16px; }
.field label { display: block; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
.field input, .field select {
  width: 100%; padding: 10px 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 14px; color: #1e293b;
}
.field-row { display: flex; gap: 14px; }
.field-row .field { flex: 1; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; align-items: center; }

.lineas-tabla { width: 100%; border-collapse: collapse; margin-top: 6px; }
.lineas-tabla th { font-size: 10px; padding: 6px 6px; }
.lineas-tabla td { padding: 4px; border: none; white-space: normal; }
.lineas-tabla input, .lineas-tabla select { padding: 7px 8px; font-size: 13px; width: 100%; border: 1.5px solid #e2e8f0; border-radius: 6px; }
.lineas-tabla input[type=number] { text-align: right; }
.col-quitar { width: 34px; text-align: center; }

.balance-bar {
  display: flex; justify-content: space-between; align-items: center;
  margin-top: 10px; padding: 10px 14px; border-radius: 8px;
  font-size: 13px; font-weight: 700; background: #f1f5f9; color: #64748b;
}
.balance-bar.ok { background: #dcfce7; color: #15803d; }
.balance-bar.mal { background: #fee2e2; color: #b91c1c; }

.detalle-list { list-style: none; margin-top: 4px; }
.detalle-list li { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: 13.5px; }
</style>

<div class="main">

  <div class="top-bar">
    <div class="section-title"><?= icono('clipboard-list') ?> Pólizas <span class="wip-badge">WIP</span></div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <div class="rango-selector">
        <input type="date" id="fDesde">
        <span style="color:#94a3b8">a</span>
        <input type="date" id="fHasta">
      </div>
      <?php if ($puedeEditar): ?><button class="btn btn-primary" onclick="ModPolizas._abrirModalNueva()">+ Nueva póliza</button><?php endif; ?>
    </div>
  </div>

  <div class="wip-banner">Módulo en construcción — pólizas Debe/Haber, base del proyecto de partida doble. Se generan solas al recibir/pagar una OC, pagar nómina, gastos fijos o registrar caja chica (badge "Auto") — o puedes capturar una manual (badge "Manual"). Falta conectar Ventas/Cobros (VoBo, finanzas). Aún no afecta ningún otro módulo del sistema.</div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Folio</th><th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Origen</th><th>Total</th><th>Estatus</th>
        </tr>
      </thead>
      <tbody id="tablaPolizas">
        <tr><td colspan="7" class="empty">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

</div>

<div class="modal-bg" id="modalNuevaBg">
  <div class="modal">
    <h2>Nueva póliza</h2>

    <div class="field-row">
      <div class="field">
        <label>Tipo</label>
        <select id="pTipo">
          <option value="diario">Diario</option>
          <option value="ingresos">Ingresos</option>
          <option value="egresos">Egresos</option>
        </select>
      </div>
      <div class="field">
        <label>Fecha</label>
        <input type="date" id="pFecha">
      </div>
    </div>
    <div class="field">
      <label>Concepto</label>
      <input type="text" id="pConcepto" placeholder="Ej: Cobro de venta S-450">
    </div>

    <table class="lineas-tabla">
      <thead>
        <tr><th>Cuenta</th><th style="width:110px">Debe</th><th style="width:110px">Haber</th><th>Nota (opcional)</th><th class="col-quitar"></th></tr>
      </thead>
      <tbody id="tablaLineas"></tbody>
    </table>
    <button class="btn btn-ghost btn-sm" style="margin-top:8px" onclick="ModPolizas._agregarLinea()">+ Agregar línea</button>

    <div class="balance-bar" id="balanceBar">Debe $0.00 — Haber $0.00</div>

    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="ModPolizas._cerrarModalNueva()">Cancelar</button>
      <button class="btn btn-success" id="btnGuardarPoliza" onclick="ModPolizas._guardar()" disabled>Guardar</button>
    </div>
  </div>
</div>

<div class="modal-bg" id="modalDetalleBg">
  <div class="modal" style="max-width:520px">
    <h2 id="detalleTitulo">Póliza</h2>
    <div id="detalleContenido"></div>
    <div class="modal-footer">
      <?php if ($puedeEditar): ?><button class="btn btn-danger" id="btnAnular" onclick="ModPolizas._anular()">Anular</button><?php endif; ?>
      <button class="btn btn-ghost" onclick="ModPolizas._cerrarModalDetalle()">Cerrar</button>
    </div>
  </div>
</div>

<script>
window._puedeEditar = <?= $puedeEditar ? 'true' : 'false' ?>;

var ModPolizas = (function(){
var API = '../api/polizas.php';
var catalogoApi = '../api/contabilidad_catalogo.php';
var cuentasCache = [];
var polizaAbierta = null;

var TIPO_LABEL = { diario: 'Diario', ingresos: 'Ingresos', egresos: 'Egresos' };

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s === null || s === undefined) ? '' : String(s);
  return d.innerHTML;
}

function fmt(n) {
  n = parseFloat(n || 0);
  return '$' + n.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function inicioMes() {
  var d = new Date();
  return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-01';
}

function hoy() {
  var d = new Date();
  return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
}

async function cargar() {
  var desde = document.getElementById('fDesde').value || inicioMes();
  var hasta = document.getElementById('fHasta').value || hoy();
  document.getElementById('fDesde').value = desde;
  document.getElementById('fHasta').value = hasta;

  try {
    var res = await fetch(API + '?desde=' + desde + '&hasta=' + hasta);
    var data = await res.json();
    render(data || []);
  } catch(e) {
    document.getElementById('tablaPolizas').innerHTML =
      '<tr><td colspan="7" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function render(filas) {
  if (!filas.length) {
    document.getElementById('tablaPolizas').innerHTML =
      '<tr><td colspan="7" class="empty">Sin pólizas en este rango</td></tr>';
    return;
  }
  var html = '';
  for (var i = 0; i < filas.length; i++) {
    var f = filas[i];
    var badgeEstado = f.estado === 'anulada'
      ? '<span class="badge-anulada">Anulada</span>'
      : '<span class="badge-activa">Activa</span>';
    var badgeOrigen = f.origen_modulo
      ? '<span class="badge-origen badge-auto">Auto</span>'
      : '<span class="badge-origen badge-manual">Manual</span>';
    html += '<tr class="clickable" onclick="ModPolizas._verDetalle(' + f.id + ')">';
    html += '<td class="folio">' + esc(f.folio) + '</td>';
    html += '<td>' + esc(f.fecha) + '</td>';
    html += '<td><span class="badge-tipo">' + (TIPO_LABEL[f.tipo] || f.tipo) + '</span></td>';
    html += '<td>' + esc(f.concepto) + '</td>';
    html += '<td>' + badgeOrigen + '</td>';
    html += '<td>' + fmt(f.total) + '</td>';
    html += '<td>' + badgeEstado + '</td>';
    html += '</tr>';
  }
  document.getElementById('tablaPolizas').innerHTML = html;
}

async function cargarCuentas() {
  if (cuentasCache.length) return cuentasCache;
  try {
    var res = await fetch(catalogoApi + '?activas=1');
    var data = await res.json();
    cuentasCache = data.filter(function(c) { return c.es_acumulativa == 0; });
  } catch(e) { cuentasCache = []; }
  return cuentasCache;
}

function optsCuentas() {
  var html = '<option value="">Selecciona cuenta...</option>';
  for (var i = 0; i < cuentasCache.length; i++) {
    html += '<option value="' + cuentasCache[i].id + '">' + esc(cuentasCache[i].codigo) + ' — ' + esc(cuentasCache[i].nombre) + '</option>';
  }
  return html;
}

function filaLinea() {
  var tr = document.createElement('tr');
  tr.innerHTML =
    '<td><select class="lCuenta">' + optsCuentas() + '</select></td>' +
    '<td><input type="number" class="lDebe" step="0.01" placeholder="0.00"></td>' +
    '<td><input type="number" class="lHaber" step="0.01" placeholder="0.00"></td>' +
    '<td><input type="text" class="lNota" placeholder="Opcional"></td>' +
    '<td class="col-quitar"><button type="button" class="btn btn-danger btn-icon" onclick="this.closest(\'tr\').remove(); ModPolizas._recalcBalance();">&times;</button></td>';
  var debeInput = tr.querySelector('.lDebe');
  var haberInput = tr.querySelector('.lHaber');
  debeInput.addEventListener('input', function() { if (parseFloat(debeInput.value||0) > 0) haberInput.value = ''; recalcBalance(); });
  haberInput.addEventListener('input', function() { if (parseFloat(haberInput.value||0) > 0) debeInput.value = ''; recalcBalance(); });
  return tr;
}

function agregarLinea() {
  document.getElementById('tablaLineas').appendChild(filaLinea());
  recalcBalance();
}

function recalcBalance() {
  var filas = document.querySelectorAll('#tablaLineas tr');
  var totalDebe = 0, totalHaber = 0;
  filas.forEach(function(tr) {
    totalDebe += parseFloat(tr.querySelector('.lDebe').value || 0);
    totalHaber += parseFloat(tr.querySelector('.lHaber').value || 0);
  });
  var bar = document.getElementById('balanceBar');
  var diff = Math.round((totalDebe - totalHaber) * 100) / 100;
  var cuadra = diff === 0 && totalDebe > 0 && filas.length >= 2;
  bar.className = 'balance-bar ' + (filas.length < 2 ? '' : (cuadra ? 'ok' : 'mal'));
  bar.textContent = 'Debe ' + fmt(totalDebe) + '  —  Haber ' + fmt(totalHaber) +
    (diff !== 0 ? '  —  Diferencia ' + fmt(Math.abs(diff)) : (cuadra ? '  —  Cuadra ✓' : ''));
  document.getElementById('btnGuardarPoliza').disabled = !cuadra;
}

async function abrirModalNueva() {
  document.getElementById('pTipo').value = 'diario';
  document.getElementById('pFecha').value = hoy();
  document.getElementById('pConcepto').value = '';
  document.getElementById('tablaLineas').innerHTML = '';
  await cargarCuentas();
  agregarLinea();
  agregarLinea();
  recalcBalance();
  document.getElementById('modalNuevaBg').classList.add('open');
}

function cerrarModalNueva() {
  document.getElementById('modalNuevaBg').classList.remove('open');
}

async function guardar() {
  var lineas = [];
  document.querySelectorAll('#tablaLineas tr').forEach(function(tr) {
    lineas.push({
      cuenta_id: parseInt(tr.querySelector('.lCuenta').value || 0),
      debe: parseFloat(tr.querySelector('.lDebe').value || 0),
      haber: parseFloat(tr.querySelector('.lHaber').value || 0),
      concepto_linea: tr.querySelector('.lNota').value.trim()
    });
  });
  var payload = {
    tipo: document.getElementById('pTipo').value,
    fecha: document.getElementById('pFecha').value,
    concepto: document.getElementById('pConcepto').value.trim(),
    lineas: lineas
  };
  if (!payload.fecha || !payload.concepto) { alert('Completa fecha y concepto'); return; }

  try {
    var res = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cerrarModalNueva();
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

async function verDetalle(id) {
  try {
    var res = await fetch(API + '?id=' + id);
    var p = await res.json();
    if (p.error) { alert(p.error); return; }
    polizaAbierta = p;
    document.getElementById('detalleTitulo').textContent = p.folio + ' — ' + (TIPO_LABEL[p.tipo] || p.tipo);

    var html = '<p style="font-size:13px;color:#64748b;margin-bottom:4px">' + esc(p.fecha) + '</p>';
    html += '<p style="font-size:14px;color:#1e293b;font-weight:700;margin-bottom:12px">' + esc(p.concepto) + '</p>';
    html += '<ul class="detalle-list">';
    for (var i = 0; i < p.lineas.length; i++) {
      var l = p.lineas[i];
      var monto = parseFloat(l.debe) > 0 ? fmt(l.debe) + ' (Debe)' : fmt(l.haber) + ' (Haber)';
      html += '<li><span>' + esc(l.cuenta_codigo) + ' — ' + esc(l.cuenta_nombre) + (l.concepto_linea ? ' · ' + esc(l.concepto_linea) : '') + '</span><span>' + monto + '</span></li>';
    }
    html += '</ul>';
    document.getElementById('detalleContenido').innerHTML = html;

    var btnAnular = document.getElementById('btnAnular');
    if (btnAnular) btnAnular.style.display = p.estado === 'anulada' ? 'none' : '';

    document.getElementById('modalDetalleBg').classList.add('open');
  } catch(e) { alert('Error al cargar detalle'); }
}

function cerrarModalDetalle() {
  document.getElementById('modalDetalleBg').classList.remove('open');
}

async function anular() {
  if (!polizaAbierta) return;
  if (!confirm('¿Anular la póliza ' + polizaAbierta.folio + '? No se borra, queda marcada como anulada para el historial de auditoría.')) return;
  try {
    var res = await fetch(API, { method: 'PUT', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ id: polizaAbierta.id }) });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al anular'); return; }
    cerrarModalDetalle();
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

document.getElementById('fDesde').value = inicioMes();
document.getElementById('fHasta').value = hoy();
document.getElementById('fDesde').addEventListener('change', cargar);
document.getElementById('fHasta').addEventListener('change', cargar);
document.getElementById('modalNuevaBg').addEventListener('click', function(e) { if (e.target === this) cerrarModalNueva(); });
document.getElementById('modalDetalleBg').addEventListener('click', function(e) { if (e.target === this) cerrarModalDetalle(); });

cargar();

return {
  init: cargar,
  _abrirModalNueva: abrirModalNueva,
  _cerrarModalNueva: cerrarModalNueva,
  _agregarLinea: agregarLinea,
  _recalcBalance: recalcBalance,
  _guardar: guardar,
  _verDetalle: verDetalle,
  _cerrarModalDetalle: cerrarModalDetalle,
  _anular: anular
};
})();
</script>
