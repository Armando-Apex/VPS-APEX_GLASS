<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
$puedeEditar = tienePermiso($user['rol'], 'gestionar_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=gastos_fijos'); exit;
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

.periodo-selector { display: flex; align-items: center; gap: 8px; }
.periodo-selector input { padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; }

.btn {
  padding: 9px 18px; border-radius: 8px; font-size: 13px;
  font-weight: 700; cursor: pointer; border: none; transition: opacity .15s;
}
.btn:hover { opacity: .85; }
.btn-primary { background: #2563eb; color: white; }
.btn-ghost   { background: #f1f5f9; color: #374151; }
.btn-success { background: #16a34a; color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

.checklist { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
.check-row {
  background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06);
  padding: 14px 18px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
}
.check-row .nombre { flex: 1 1 200px; font-size: 14px; font-weight: 700; color: #1e293b; }
.check-row .estimado { font-size: 12px; color: #94a3b8; }
.check-row input[type=number] { width: 110px; padding: 8px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; }
.check-row input[type=date] { padding: 8px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; }

.badge-pagado { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #dcfce7; color: #16a34a; }
.badge-pendiente { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #fee2e2; color: #dc2626; }

.total-row {
  background: #f8fafc; border-radius: 12px; padding: 14px 18px;
  font-size: 15px; font-weight: 800; color: #1e293b; text-align: right;
}

.empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }

.modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); z-index: 1000;
  align-items: center; justify-content: center;
}
.modal-bg.open { display: flex; }
.modal {
  background: white; border-radius: 16px;
  padding: 28px; width: 100%; max-width: 420px;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.modal h2 { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 20px; }
.field { margin-bottom: 16px; }
.field label { display: block; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
.field input, .field select {
  width: 100%; padding: 10px 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 14px; color: #1e293b;
}
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
</style>

<div class="main">

  <div class="top-bar">
    <div class="section-title"><?= icono('activity') ?> Gastos Fijos <span class="wip-badge">WIP</span></div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <div class="periodo-selector">
        <label style="font-size:12px;font-weight:700;color:#64748b">Periodo</label>
        <input type="month" id="fPeriodo">
      </div>
      <?php if ($puedeEditar): ?><button class="btn btn-primary" onclick="ModGastosFijos._abrirModalConcepto()">+ Concepto</button><?php endif; ?>
    </div>
  </div>

  <div class="wip-banner">Módulo en construcción — parte del proyecto de Estado de Resultados (P&amp;L). Checklist mensual de gastos fijos (renta, luz, agua, internet, seguros...); al marcar como pagado se registra en la cuenta contable asignada. Aún no afecta ningún otro módulo del sistema.</div>

  <div class="checklist" id="checklist">
    <div class="empty">Cargando...</div>
  </div>

  <div class="total-row" id="totalRow"></div>

</div>

<div class="modal-bg" id="modalConceptoBg">
  <div class="modal">
    <h2>Nuevo concepto</h2>
    <div class="field">
      <label>Nombre</label>
      <input type="text" id="cNombre" placeholder="Ej: Renta local">
    </div>
    <div class="field">
      <label>Cuenta contable</label>
      <select id="cCuenta"></select>
    </div>
    <div class="field">
      <label>Monto estimado</label>
      <input type="number" id="cMontoEstimado" step="0.01" placeholder="0.00">
    </div>
    <div class="field">
      <label>Frecuencia</label>
      <select id="cFrecuencia">
        <option value="mensual">Mensual</option>
        <option value="bimestral">Bimestral</option>
        <option value="anual">Anual</option>
      </select>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="ModGastosFijos._cerrarModalConcepto()">Cancelar</button>
      <button class="btn btn-success" onclick="ModGastosFijos._guardarConcepto()">Guardar</button>
    </div>
  </div>
</div>

<script>
window._puedeEditar = <?= $puedeEditar ? 'true' : 'false' ?>;

var ModGastosFijos = (function(){
var API = '../api/gastos_fijos.php';
var catalogoApi = '../api/contabilidad_catalogo.php';
var filas = [];
var cuentas = [];

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s === null || s === undefined) ? '' : String(s);
  return d.innerHTML;
}

function fmt(n) {
  n = parseFloat(n || 0);
  return '$' + n.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function periodoActual() {
  var d = new Date();
  var mes = ('0' + (d.getMonth() + 1)).slice(-2);
  return d.getFullYear() + '-' + mes;
}

async function cargar() {
  var periodo = document.getElementById('fPeriodo').value || periodoActual();
  document.getElementById('fPeriodo').value = periodo;
  try {
    var res = await fetch(API + '?accion=pagos&periodo=' + periodo);
    var data = await res.json();
    filas = data.filas || [];
    render();
  } catch(e) {
    document.getElementById('checklist').innerHTML = '<div class="empty" style="color:#dc2626">Error al cargar</div>';
  }
}

function render() {
  if (!filas.length) {
    document.getElementById('checklist').innerHTML = '<div class="empty">No hay conceptos registrados todavía</div>';
    document.getElementById('totalRow').textContent = '';
    return;
  }
  var html = '';
  var total = 0;
  for (var i = 0; i < filas.length; i++) {
    var f = filas[i];
    var pagado = f.pago_id !== null;
    var monto = pagado ? f.monto : f.monto_estimado;
    if (pagado) total += parseFloat(monto);

    html += '<div class="check-row">';
    html += '<div class="nombre">' + esc(f.nombre) + '<div class="estimado">' + esc(f.cuenta_codigo) + ' — ' + esc(f.cuenta_nombre) + ' · estimado ' + fmt(f.monto_estimado) + '</div></div>';
    if (window._puedeEditar) {
      html += '<input type="number" step="0.01" id="monto_' + i + '" value="' + esc(monto) + '" placeholder="Monto">';
      html += '<input type="date" id="fecha_' + i + '" value="' + esc(f.fecha_pago || '') + '">';
      html += '<button class="btn btn-success btn-sm" onclick="ModGastosFijos._guardarPago(' + i + ')">' + (pagado ? 'Actualizar' : 'Marcar pagado') + '</button>';
    }
    html += pagado ? '<span class="badge-pagado">Pagado</span>' : '<span class="badge-pendiente">Pendiente</span>';
    html += '</div>';
  }
  document.getElementById('checklist').innerHTML = html;
  document.getElementById('totalRow').textContent = 'Total pagado del periodo: ' + fmt(total);
}

async function guardarPago(idx) {
  var f = filas[idx];
  var monto = parseFloat(document.getElementById('monto_' + idx).value || 0);
  var fecha = document.getElementById('fecha_' + idx).value;
  var periodo = document.getElementById('fPeriodo').value;

  if (!fecha) { alert('Selecciona la fecha de pago'); return; }

  try {
    var res = await fetch(API + '?accion=guardar_pago', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ concepto_id: f.concepto_id, periodo: periodo, fecha_pago: fecha, monto: monto })
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

async function cargarCuentas() {
  try {
    var res = await fetch(catalogoApi + '?activas=1');
    var data = await res.json();
    cuentas = data.filter(function(c) { return c.es_acumulativa == 0; });
    var html = '';
    for (var i = 0; i < cuentas.length; i++) {
      html += '<option value="' + cuentas[i].id + '">' + esc(cuentas[i].codigo) + ' — ' + esc(cuentas[i].nombre) + '</option>';
    }
    document.getElementById('cCuenta').innerHTML = html;
  } catch(e) {}
}

function abrirModalConcepto() {
  document.getElementById('cNombre').value = '';
  document.getElementById('cMontoEstimado').value = '';
  document.getElementById('cFrecuencia').value = 'mensual';
  cargarCuentas();
  document.getElementById('modalConceptoBg').classList.add('open');
}

function cerrarModalConcepto() {
  document.getElementById('modalConceptoBg').classList.remove('open');
}

async function guardarConcepto() {
  var nombre = document.getElementById('cNombre').value.trim();
  var cuentaId = document.getElementById('cCuenta').value;
  if (!nombre || !cuentaId) { alert('Nombre y cuenta son obligatorios'); return; }
  var payload = {
    nombre: nombre,
    cuenta_id: parseInt(cuentaId),
    monto_estimado: parseFloat(document.getElementById('cMontoEstimado').value || 0),
    frecuencia: document.getElementById('cFrecuencia').value
  };
  try {
    var res = await fetch(API + '?accion=crear_concepto', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cerrarModalConcepto();
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

document.getElementById('fPeriodo').value = periodoActual();
document.getElementById('fPeriodo').addEventListener('change', cargar);
document.getElementById('modalConceptoBg').addEventListener('click', function(e) {
  if (e.target === this) cerrarModalConcepto();
});

cargar();

return {
  init: cargar,
  _guardarPago: guardarPago,
  _abrirModalConcepto: abrirModalConcepto,
  _cerrarModalConcepto: cerrarModalConcepto,
  _guardarConcepto: guardarConcepto
};
})();
</script>
