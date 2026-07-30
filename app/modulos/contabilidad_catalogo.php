<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_contabilidad');
$puedeEditar = tienePermiso($user['rol'], 'gestionar_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=contabilidad_catalogo'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.main { padding: 24px; max-width: 1000px; margin: 0 auto; }

.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.wip-badge { font-size: 10px; background: #f59e0b; color: #000; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.wip-banner {
  background: #fef3c7; color: #92400e; font-size: 13px; padding: 10px 16px;
  border-radius: 8px; margin-bottom: 20px;
}

.btn {
  padding: 9px 18px; border-radius: 8px; font-size: 13px;
  font-weight: 700; cursor: pointer; border: none; transition: opacity .15s;
}
.btn:hover { opacity: .85; }
.btn-primary { background: #2563eb; color: white; }
.btn-ghost   { background: #f1f5f9; color: #374151; }
.btn-success { background: #16a34a; color: white; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

.table-wrap {
  background: white; border-radius: 14px;
  overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
table { width: 100%; border-collapse: collapse; }
thead { background: #f8fafc; }
th {
  padding: 12px 16px; text-align: left;
  font-size: 11px; font-weight: 700; color: #64748b;
  text-transform: uppercase; letter-spacing: .5px;
}
td { padding: 12px 16px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #374151; }
tr:hover td { background: #f8fafc; }

.codigo { font-family: monospace; font-weight: 700; color: #64748b; }
.nivel-1 { font-weight: 800; color: #1e293b; }
.nivel-2 { padding-left: 20px !important; }

.badge-tipo { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.tipo-ingreso { background: #dcfce7; color: #16a34a; }
.tipo-costo_venta { background: #fee2e2; color: #dc2626; }
.tipo-gasto_operativo { background: #fef3c7; color: #b45309; }
.tipo-financiero { background: #dbeafe; color: #1d4ed8; }
.tipo-impuesto { background: #f1f5f9; color: #64748b; }

.badge-activo   { background: #dcfce7; color: #16a34a; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.badge-inactivo { background: #f1f5f9; color: #94a3b8; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

.empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }

.modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); z-index: 1000;
  align-items: center; justify-content: center;
}
.modal-bg.open { display: flex; }
.modal {
  background: white; border-radius: 16px;
  padding: 28px; width: 100%; max-width: 480px;
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
.field input:focus, .field select:focus { outline: none; border-color: #2563eb; }
.field-check { display: flex; align-items: center; gap: 8px; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
</style>

<div class="main">

  <div class="top-bar">
    <div class="section-title"><?= icono('layers') ?> Catálogo de Cuentas <span class="wip-badge">WIP</span></div>
    <?php if ($puedeEditar): ?><button class="btn btn-primary" onclick="abrirModalNueva()">+ Nueva cuenta</button><?php endif; ?>
  </div>

  <div class="wip-banner">Módulo en construcción — parte del proyecto de Estado de Resultados (P&amp;L). Aún no afecta ningún otro módulo del sistema.</div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Código</th>
          <th>Nombre</th>
          <th>Tipo</th>
          <th>Naturaleza</th>
          <th>Estatus</th>
          <?php if ($puedeEditar): ?><th>Acciones</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="tablaCuentas">
        <tr><td colspan="6" class="empty">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

</div>

<div class="modal-bg" id="modalBg">
  <div class="modal">
    <h2 id="modalTitulo">Nueva cuenta</h2>
    <input type="hidden" id="editId">

    <div class="field">
      <label>Código</label>
      <input type="text" id="fCodigo" placeholder="Ej: 6.6">
    </div>
    <div class="field">
      <label>Nombre</label>
      <input type="text" id="fNombre" placeholder="Ej: Mantenimiento">
    </div>
    <div class="field">
      <label>Cuenta padre</label>
      <select id="fPadre"><option value="">(ninguna — cuenta raíz)</option></select>
    </div>
    <div class="field">
      <label>Tipo financiero</label>
      <select id="fTipo">
        <option value="ingreso">Ingreso</option>
        <option value="costo_venta">Costo de venta</option>
        <option value="gasto_operativo">Gasto operativo</option>
        <option value="financiero">Financiero</option>
        <option value="impuesto">Impuesto</option>
      </select>
    </div>
    <div class="field">
      <label>Naturaleza</label>
      <select id="fNaturaleza">
        <option value="suma">Suma (ej. ingresos)</option>
        <option value="resta">Resta (ej. costos/gastos)</option>
      </select>
    </div>
    <div class="field field-check">
      <input type="checkbox" id="fAcumulativa">
      <label style="margin:0">Es acumulativa (solo agrupa, no recibe movimientos)</label>
    </div>

    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-success" onclick="guardar()">Guardar</button>
    </div>
  </div>
</div>

<script>
window._puedeEditar = <?= $puedeEditar ? 'true' : 'false' ?>;

var ModCatalogoContable = (function(){
var API = '../api/contabilidad_catalogo.php';
var cuentasData = [];

async function cargar() {
  try {
    var res = await fetch(API + '?activas=0');
    cuentasData = await res.json();
    renderTabla();
    renderSelectPadre();
  } catch(e) {
    document.getElementById('tablaCuentas').innerHTML =
      '<tr><td colspan="6" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s === null || s === undefined) ? '' : String(s);
  return d.innerHTML;
}

var TIPO_LABEL = {
  ingreso: 'Ingreso', costo_venta: 'Costo de venta', gasto_operativo: 'Gasto operativo',
  financiero: 'Financiero', impuesto: 'Impuesto'
};

function renderTabla() {
  if (!cuentasData.length) {
    document.getElementById('tablaCuentas').innerHTML =
      '<tr><td colspan="6" class="empty">No hay cuentas registradas</td></tr>';
    return;
  }
  var html = '';
  for (var i = 0; i < cuentasData.length; i++) {
    var c = cuentasData[i];
    var nivelClass = c.nivel == 1 ? 'nivel-1' : 'nivel-2';
    var badgeEstatus = c.activo == 1
      ? '<span class="badge-activo">Activa</span>'
      : '<span class="badge-inactivo">Inactiva</span>';
    var acciones = '';
    if (window._puedeEditar) {
      acciones += '<button class="btn btn-ghost btn-sm" onclick="ModCatalogoContable._abrirModalEditar(' + c.id + ')">Editar</button> ';
      var txt = c.activo == 1 ? 'Desactivar' : 'Activar';
      acciones += '<button class="btn btn-sm" onclick="ModCatalogoContable._toggleActivo(' + c.id + ',' + c.activo + ')">' + txt + '</button>';
    }
    html += '<tr>';
    html += '<td class="codigo">' + esc(c.codigo) + '</td>';
    html += '<td class="' + nivelClass + '">' + esc(c.nombre) + '</td>';
    html += '<td><span class="badge-tipo tipo-' + c.tipo_financiero + '">' + (TIPO_LABEL[c.tipo_financiero] || c.tipo_financiero) + '</span></td>';
    html += '<td>' + (c.naturaleza == 'suma' ? '+ Suma' : '&minus; Resta') + '</td>';
    html += '<td>' + badgeEstatus + '</td>';
    if (window._puedeEditar) html += '<td>' + acciones + '</td>';
    html += '</tr>';
  }
  document.getElementById('tablaCuentas').innerHTML = html;
}

function renderSelectPadre() {
  var sel = document.getElementById('fPadre');
  var html = '<option value="">(ninguna — cuenta raíz)</option>';
  for (var i = 0; i < cuentasData.length; i++) {
    var c = cuentasData[i];
    html += '<option value="' + c.id + '">' + esc(c.codigo) + ' — ' + esc(c.nombre) + '</option>';
  }
  sel.innerHTML = html;
}

function abrirModalNueva() {
  document.getElementById('modalTitulo').textContent = 'Nueva cuenta';
  document.getElementById('editId').value = '';
  document.getElementById('fCodigo').value = '';
  document.getElementById('fNombre').value = '';
  document.getElementById('fPadre').value = '';
  document.getElementById('fTipo').value = 'gasto_operativo';
  document.getElementById('fNaturaleza').value = 'resta';
  document.getElementById('fAcumulativa').checked = false;
  document.getElementById('modalBg').classList.add('open');
}

function abrirModalEditar(id) {
  var c = null;
  for (var i = 0; i < cuentasData.length; i++) { if (cuentasData[i].id == id) { c = cuentasData[i]; break; } }
  if (!c) return;
  document.getElementById('modalTitulo').textContent = 'Editar cuenta';
  document.getElementById('editId').value = c.id;
  document.getElementById('fCodigo').value = c.codigo;
  document.getElementById('fNombre').value = c.nombre;
  document.getElementById('fPadre').value = c.cuenta_padre_id || '';
  document.getElementById('fTipo').value = c.tipo_financiero;
  document.getElementById('fNaturaleza').value = c.naturaleza;
  document.getElementById('fAcumulativa').checked = c.es_acumulativa == 1;
  document.getElementById('modalBg').classList.add('open');
}

function cerrarModal() {
  document.getElementById('modalBg').classList.remove('open');
}

async function guardar() {
  var id = document.getElementById('editId').value;
  var codigo = document.getElementById('fCodigo').value.trim();
  var nombre = document.getElementById('fNombre').value.trim();
  var padre = document.getElementById('fPadre').value;
  var tipo = document.getElementById('fTipo').value;
  var naturaleza = document.getElementById('fNaturaleza').value;
  var acumulativa = document.getElementById('fAcumulativa').checked ? 1 : 0;

  if (!codigo || !nombre) { alert('Código y nombre son obligatorios'); return; }

  var nivel = padre ? 2 : 1;
  var payload = {
    codigo: codigo, nombre: nombre,
    cuenta_padre_id: padre ? parseInt(padre) : '',
    tipo_financiero: tipo, naturaleza: naturaleza,
    es_acumulativa: acumulativa, nivel: nivel
  };
  var method = id ? 'PUT' : 'POST';
  if (id) payload.id = parseInt(id);

  try {
    var res = await fetch(API, { method: method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    var data = await res.json();
    if (data.ok) { cerrarModal(); cargar(); }
    else { alert(data.error || 'Error al guardar'); }
  } catch(e) { alert('Error de conexión'); }
}

async function toggleActivo(id, actual) {
  var accion = actual == 1 ? 'desactivar' : 'activar';
  if (!confirm('¿Deseas ' + accion + ' esta cuenta?')) return;
  try {
    var res = await fetch(API, {
      method: 'PUT', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ id: id, activo: actual == 1 ? 0 : 1 })
    });
    var data = await res.json();
    if (data.ok) cargar();
  } catch(e) { alert('Error'); }
}

document.getElementById('modalBg').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});

cargar();

return {
  init: cargar,
  _abrirModalNueva: abrirModalNueva,
  _abrirModalEditar: abrirModalEditar,
  _cerrarModal: cerrarModal,
  _guardar: guardar,
  _toggleActivo: toggleActivo
};
})();

window.abrirModalNueva = ModCatalogoContable._abrirModalNueva;
window.cerrarModal     = ModCatalogoContable._cerrarModal;
window.guardar         = ModCatalogoContable._guardar;
</script>
