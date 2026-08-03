<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
$puedeEditar = tienePermiso($user['rol'], 'gestionar_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=nomina'); exit;
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
.btn-danger  { background: #fee2e2; color: #b91c1c; }
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
td input { width: 100px; padding: 6px 8px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 13px; }
.total-cell { font-weight: 700; color: #1e293b; }
.fila-total td { font-weight: 800; color: #1e293b; border-top: 2px solid #e2e8f0; background: #f8fafc; }

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
  border-radius: 8px; font-size: 14px; color: #1e293b; background: white;
}
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
</style>

<div class="main">

  <div class="top-bar">
    <div class="section-title"><?= icono('users') ?> Nómina <span class="wip-badge">WIP</span></div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <div class="periodo-selector">
        <label style="font-size:12px;font-weight:700;color:#64748b">Periodo</label>
        <input type="month" id="fPeriodo">
      </div>
      <?php if ($puedeEditar): ?><button class="btn btn-primary" onclick="ModNomina._abrirModalEmpleado()">+ Empleado</button><?php endif; ?>
    </div>
  </div>

  <div class="wip-banner">Módulo en construcción — parte del proyecto de Estado de Resultados (P&amp;L). Captura mensual de sueldos; al guardar un pago se registra en la cuenta contable 6.1 Nómina. Aún no afecta ningún otro módulo del sistema.</div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Empleado</th>
          <th>Puesto</th>
          <th>Área</th>
          <th>Sueldo base</th>
          <th>Sueldo neto</th>
          <th>IMSS patronal</th>
          <th>Bonos / H. extra / Otras prest.</th>
          <th>Total</th>
          <th>Fecha pago</th>
          <?php if ($puedeEditar): ?><th>Acción</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="tablaPagos">
        <tr><td colspan="10" class="empty">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

</div>

<div class="modal-bg" id="modalEmpleadoBg">
  <div class="modal">
    <h2>Nuevo empleado</h2>
    <div class="field">
      <label>Nombre</label>
      <input type="text" id="eNombre" placeholder="Nombre completo">
    </div>
    <div class="field">
      <label>Puesto</label>
      <input type="text" id="ePuesto" placeholder="Ej: Operador de corte">
    </div>
    <div class="field">
      <label>Área (para el Estado de Resultados)</label>
      <select id="eArea">
        <option value="planta">Planta (Costo de Ventas)</option>
        <option value="oficina">Administración y Ventas (Gastos Operativos)</option>
      </select>
    </div>
    <div class="field">
      <label>Sueldo base</label>
      <input type="number" id="eSueldoBase" step="0.01" placeholder="0.00">
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="ModNomina._cerrarModalEmpleado()">Cancelar</button>
      <button class="btn btn-success" onclick="ModNomina._guardarEmpleado()">Guardar</button>
    </div>
  </div>
</div>

<script>
window._puedeEditar = <?= $puedeEditar ? 'true' : 'false' ?>;

var ModNomina = (function(){
var API = '../api/nomina.php';
var filas = [];

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
    document.getElementById('tablaPagos').innerHTML =
      '<tr><td colspan="10" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function render() {
  if (!filas.length) {
    document.getElementById('tablaPagos').innerHTML =
      '<tr><td colspan="10" class="empty">No hay empleados activos registrados</td></tr>';
    return;
  }
  var html = '';
  var totalGeneral = 0;
  for (var i = 0; i < filas.length; i++) {
    var f = filas[i];
    var idx = i;
    var neto = f.sueldo_neto !== null ? f.sueldo_neto : f.sueldo_base;
    var imss = f.imss_patronal || 0;
    var otras = f.otras_prestaciones || 0;
    var total = f.total_pagado !== null ? parseFloat(f.total_pagado) : (parseFloat(neto || 0) + parseFloat(imss) + parseFloat(otras));
    totalGeneral += total;
    var fechaPago = f.fecha_pago || '';

    html += '<tr>';
    html += '<td>' + esc(f.nombre) + '</td>';
    html += '<td>' + esc(f.puesto || '-') + '</td>';
    html += '<td>' + (f.area === 'planta' ? 'Planta' : 'Admin/Ventas') + '</td>';
    html += '<td>' + fmt(f.sueldo_base) + '</td>';
    if (window._puedeEditar) {
      html += '<td><input type="number" step="0.01" id="neto_' + idx + '" value="' + esc(neto) + '"></td>';
      html += '<td><input type="number" step="0.01" id="imss_' + idx + '" value="' + esc(imss) + '"></td>';
      html += '<td><input type="number" step="0.01" id="otras_' + idx + '" value="' + esc(otras) + '"></td>';
    } else {
      html += '<td>' + fmt(neto) + '</td><td>' + fmt(imss) + '</td><td>' + fmt(otras) + '</td>';
    }
    html += '<td class="total-cell" id="total_' + idx + '">' + fmt(total) + '</td>';
    if (window._puedeEditar) {
      html += '<td><input type="date" id="fecha_' + idx + '" value="' + esc(fechaPago) + '"></td>';
      html += '<td style="display:flex;gap:6px">' +
        '<button class="btn btn-success btn-sm" onclick="ModNomina._guardarPago(' + idx + ')">Guardar</button>' +
        '<button class="btn btn-danger btn-sm" onclick="ModNomina._borrarEmpleado(' + idx + ')">Borrar</button>' +
        '</td>';
    } else {
      html += '<td>' + esc(fechaPago || '-') + '</td>';
    }
    html += '</tr>';
  }
  html += '<tr class="fila-total"><td colspan="7">TOTAL DEL PERIODO</td><td>' + fmt(totalGeneral) + '</td><td colspan="' + (window._puedeEditar ? 2 : 1) + '"></td></tr>';
  document.getElementById('tablaPagos').innerHTML = html;
}

async function guardarPago(idx) {
  var f = filas[idx];
  var neto = parseFloat(document.getElementById('neto_' + idx).value || 0);
  var imss = parseFloat(document.getElementById('imss_' + idx).value || 0);
  var otras = parseFloat(document.getElementById('otras_' + idx).value || 0);
  var fecha = document.getElementById('fecha_' + idx).value;
  var periodo = document.getElementById('fPeriodo').value;

  if (!fecha) { alert('Selecciona la fecha de pago'); return; }

  try {
    var res = await fetch(API + '?accion=guardar_pago', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        empleado_id: f.empleado_id, periodo: periodo, fecha_pago: fecha,
        sueldo_neto: neto, imss_patronal: imss, otras_prestaciones: otras
      })
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

async function borrarEmpleado(idx) {
  var f = filas[idx];
  if (!confirm('¿Dar de baja a ' + f.nombre + '? Ya no aparecerá en Nómina, pero su historial de pagos se conserva.')) return;
  try {
    var res = await fetch(API + '?accion=editar_empleado', {
      method: 'PUT', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ id: f.empleado_id, activo: 0 })
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al dar de baja'); return; }
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

function abrirModalEmpleado() {
  document.getElementById('eNombre').value = '';
  document.getElementById('ePuesto').value = '';
  document.getElementById('eArea').value = 'planta';
  document.getElementById('eSueldoBase').value = '';
  document.getElementById('modalEmpleadoBg').classList.add('open');
}

function cerrarModalEmpleado() {
  document.getElementById('modalEmpleadoBg').classList.remove('open');
}

async function guardarEmpleado() {
  var nombre = document.getElementById('eNombre').value.trim();
  if (!nombre) { alert('El nombre es obligatorio'); return; }
  var payload = {
    nombre: nombre,
    puesto: document.getElementById('ePuesto').value.trim(),
    area: document.getElementById('eArea').value,
    sueldo_base: parseFloat(document.getElementById('eSueldoBase').value || 0)
  };
  try {
    var res = await fetch(API + '?accion=crear_empleado', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cerrarModalEmpleado();
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

document.getElementById('fPeriodo').value = periodoActual();
document.getElementById('fPeriodo').addEventListener('change', cargar);
document.getElementById('modalEmpleadoBg').addEventListener('click', function(e) {
  if (e.target === this) cerrarModalEmpleado();
});

cargar();

return {
  init: cargar,
  _guardarPago: guardarPago,
  _borrarEmpleado: borrarEmpleado,
  _abrirModalEmpleado: abrirModalEmpleado,
  _cerrarModalEmpleado: cerrarModalEmpleado,
  _guardarEmpleado: guardarEmpleado
};
})();
</script>
