<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
$puedeEditar = tienePermiso($user['rol'], 'gestionar_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=caja_chica'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
/* S3-03: antes era `body{}` — pisaba el fondo de TODO el dashboard mientras
   este módulo estaba cargado en el SPA. Retargeteado a .main (raíz del módulo). */
.main { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--c-bg, #f8fafc); padding: 24px; max-width: 1100px; margin: 0 auto; }

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
  font-weight: 700; cursor: pointer; border: none; transition: opacity .15s;
}
.btn:hover { opacity: .85; }
.btn-primary { background: #2563eb; color: white; }
.btn-ghost   { background: #f1f5f9; color: #374151; }
.btn-success { background: #16a34a; color: white; }
.btn-danger  { background: #fee2e2; color: #dc2626; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

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
tr:hover td { background: #f8fafc; }
.fila-total td { font-weight: 800; color: #1e293b; border-top: 2px solid #e2e8f0; background: #f8fafc; }

.badge-cat { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #f1f5f9; color: #64748b; }

.empty { text-align: center; padding: 48px; color:var(--c-muted); font-size: 15px; }

.modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); z-index: 1000;
  align-items: center; justify-content: center;
}
.modal-bg.open { display: flex; }
.modal {
  background: white; border-radius: 16px;
  padding: 28px; width: 100%; max-width: 440px;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
  max-height: 90vh; overflow-y: auto;
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
    <div class="section-title"><?= icono('credit-card') ?> Caja Chica / Viáticos <span class="wip-badge">WIP</span></div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <div class="rango-selector">
        <input type="date" id="fDesde">
        <span style="color:var(--c-muted)">a</span>
        <input type="date" id="fHasta">
      </div>
      <?php if ($puedeEditar): ?><button class="btn btn-primary" onclick="ModCajaChica._abrirModalMovimiento()">+ Registrar gasto</button><?php endif; ?>
    </div>
  </div>

  <div class="wip-banner">Módulo en construcción — parte del proyecto de Estado de Resultados (P&amp;L). Bitácora rápida de gastos menores (viáticos, papelería, mantenimiento, combustible); cada registro se refleja en la cuenta contable elegida. Aún no afecta ningún otro módulo del sistema.</div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Fecha</th><th>Concepto</th><th>Categoría</th><th>Cuenta</th>
          <th>Empleado</th><th>Monto</th><th>Autorizó</th>
          <?php if ($puedeEditar): ?><th>Acción</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="tablaMovimientos">
        <tr><td colspan="8" class="empty">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

</div>

<div class="modal-bg" id="modalMovimientoBg">
  <div class="modal">
    <h2>Registrar gasto</h2>
    <div class="field">
      <label>Fecha</label>
      <input type="date" id="mFecha">
    </div>
    <div class="field">
      <label>Concepto</label>
      <input type="text" id="mConcepto" placeholder="Ej: Gasolina camioneta reparto">
    </div>
    <div class="field">
      <label>Monto</label>
      <input type="number" id="mMonto" step="0.01" placeholder="0.00">
    </div>
    <div class="field">
      <label>Categoría</label>
      <select id="mCategoria">
        <option value="viaticos">Viáticos</option>
        <option value="papeleria">Papelería</option>
        <option value="mantenimiento">Mantenimiento</option>
        <option value="combustible">Combustible</option>
        <option value="otro">Otro</option>
      </select>
    </div>
    <div class="field">
      <label>Cuenta contable</label>
      <select id="mCuenta"></select>
    </div>
    <div class="field">
      <label>Empleado (opcional)</label>
      <select id="mEmpleado"><option value="">(sin asignar)</option></select>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="ModCajaChica._cerrarModalMovimiento()">Cancelar</button>
      <button class="btn btn-success" onclick="ModCajaChica._guardarMovimiento()">Guardar</button>
    </div>
  </div>
</div>

<script>
window._puedeEditar = <?= $puedeEditar ? 'true' : 'false' ?>;

var ModCajaChica = (function(){
var API = '../api/caja_chica.php';
var catalogoApi = '../api/contabilidad_catalogo.php';
var nominaApi = '../api/nomina.php';
var CAT_LABEL = { viaticos: 'Viáticos', papeleria: 'Papelería', mantenimiento: 'Mantenimiento', combustible: 'Combustible', otro: 'Otro' };

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
    render(data.filas || [], data.total || 0);
  } catch(e) {
    document.getElementById('tablaMovimientos').innerHTML =
      '<tr><td colspan="8" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function render(filas, total) {
  if (!filas.length) {
    document.getElementById('tablaMovimientos').innerHTML =
      '<tr><td colspan="8" class="empty">Sin movimientos en este rango</td></tr>';
    return;
  }
  var html = '';
  for (var i = 0; i < filas.length; i++) {
    var f = filas[i];
    html += '<tr>';
    html += '<td>' + esc(f.fecha) + '</td>';
    html += '<td>' + esc(f.concepto) + '</td>';
    html += '<td><span class="badge-cat">' + (CAT_LABEL[f.categoria] || f.categoria) + '</span></td>';
    html += '<td>' + esc(f.cuenta_codigo) + ' — ' + esc(f.cuenta_nombre) + '</td>';
    html += '<td>' + esc(f.empleado_nombre || '-') + '</td>';
    html += '<td>' + fmt(f.monto) + '</td>';
    html += '<td>' + esc(f.autorizado_por || '-') + '</td>';
    if (window._puedeEditar) {
      html += '<td><button class="btn btn-danger btn-sm" onclick="ModCajaChica._eliminar(' + f.id + ')">Eliminar</button></td>';
    }
    html += '</tr>';
  }
  html += '<tr class="fila-total"><td colspan="5">TOTAL DEL RANGO</td><td>' + fmt(total) + '</td><td colspan="' + (window._puedeEditar ? 2 : 1) + '"></td></tr>';
  document.getElementById('tablaMovimientos').innerHTML = html;
}

async function eliminar(id) {
  if (!confirm('¿Eliminar este movimiento?')) return;
  try {
    var res = await fetch(API, { method: 'DELETE', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ id: id }) });
    var data = await res.json();
    if (data.ok) cargar();
  } catch(e) { alert('Error al eliminar'); }
}

async function cargarCuentas() {
  try {
    var res = await fetch(catalogoApi + '?activas=1');
    var data = await res.json();
    var cuentas = data.filter(function(c) { return c.es_acumulativa == 0; });
    var html = '';
    for (var i = 0; i < cuentas.length; i++) {
      html += '<option value="' + cuentas[i].id + '">' + esc(cuentas[i].codigo) + ' — ' + esc(cuentas[i].nombre) + '</option>';
    }
    document.getElementById('mCuenta').innerHTML = html;
  } catch(e) {}
}

async function cargarEmpleados() {
  try {
    var res = await fetch(nominaApi + '?accion=empleados');
    var data = await res.json();
    var html = '<option value="">(sin asignar)</option>';
    for (var i = 0; i < data.length; i++) {
      html += '<option value="' + data[i].id + '">' + esc(data[i].nombre) + '</option>';
    }
    document.getElementById('mEmpleado').innerHTML = html;
  } catch(e) {}
}

function abrirModalMovimiento() {
  document.getElementById('mFecha').value = hoy();
  document.getElementById('mConcepto').value = '';
  document.getElementById('mMonto').value = '';
  document.getElementById('mCategoria').value = 'viaticos';
  cargarCuentas();
  cargarEmpleados();
  document.getElementById('modalMovimientoBg').classList.add('open');
}

function cerrarModalMovimiento() {
  document.getElementById('modalMovimientoBg').classList.remove('open');
}

async function guardarMovimiento() {
  var payload = {
    fecha: document.getElementById('mFecha').value,
    concepto: document.getElementById('mConcepto').value.trim(),
    monto: parseFloat(document.getElementById('mMonto').value || 0),
    categoria: document.getElementById('mCategoria').value,
    cuenta_id: parseInt(document.getElementById('mCuenta').value || 0),
    empleado_id: document.getElementById('mEmpleado').value ? parseInt(document.getElementById('mEmpleado').value) : null
  };
  if (!payload.fecha || !payload.concepto || !payload.monto || !payload.cuenta_id) {
    alert('Completa fecha, concepto, monto y cuenta'); return;
  }
  try {
    var res = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cerrarModalMovimiento();
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

document.getElementById('fDesde').value = inicioMes();
document.getElementById('fHasta').value = hoy();
document.getElementById('fDesde').addEventListener('change', cargar);
document.getElementById('fHasta').addEventListener('change', cargar);
document.getElementById('modalMovimientoBg').addEventListener('click', function(e) {
  if (e.target === this) cerrarModalMovimiento();
});

cargar();

return {
  init: cargar,
  _eliminar: eliminar,
  _abrirModalMovimiento: abrirModalMovimiento,
  _cerrarModalMovimiento: cerrarModalMovimiento,
  _guardarMovimiento: guardarMovimiento
};
})();
</script>
