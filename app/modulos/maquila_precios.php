<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('gestionar_maquila_precios');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=maquila_precios'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.mq-wrap { padding: 24px; max-width: 900px; margin: 0 auto; }
.page-title { font-size: 18px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #9ca3af; margin-bottom: 20px; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
.card-title { font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 16px; text-transform: uppercase; letter-spacing: .4px; }
.form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
.field input, .field select { padding: 9px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #1e293b; background: white; width: 100%; }
.field input:focus, .field select:focus { outline: none; border-color: #2563eb; }
.acciones { display: flex; gap: 10px; align-items: center; margin-top: 16px; }
.btn { padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; }
.btn-primary { background: #2563eb; color: white; }
.btn-primary:hover { background: #1d4ed8; }
.btn-ghost { background: #f1f5f9; color: #374151; }
.form-error { color: #dc2626; margin-top: 8px; font-size: 13px; font-weight: 600; min-height: 16px; }
.mq-table { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
thead th { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; }
tbody tr { border-bottom: 1px solid #f1f5f9; }
tbody tr:hover { background: #f8fafc; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 11px 14px; font-size: 13px; }
.loading-msg { text-align: center; padding: 32px; color: #9ca3af; font-size: 14px; }
.row-actions { display: flex; gap: 14px; }
.link-btn { background: none; border: none; padding: 0; font-size: 13px; font-weight: 600; cursor: pointer; }
.link-edit { color: #2563eb; }
.link-edit:hover { text-decoration: underline; }
.link-danger { color: #dc2626; }
.link-danger:hover { text-decoration: underline; }
.est-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 99px; }
.badge-activo   { background: #dcfce7; color: #15803d; }
.badge-inactivo { background: #f1f5f9; color: #94a3b8; }

@media(max-width:768px){
  .mq-wrap { padding: 12px; }
  .form-grid { grid-template-columns: 1fr; }
  tbody td { padding: 10px 8px; font-size: 12px; }
  thead th  { padding: 8px 8px; font-size: 10px; }
}
</style>

<div class="mq-wrap">
  <div class="page-title">Precios de Maquila</div>
  <div class="page-sub">Tarifas por servicio y espesor, y cat&aacute;logo de tipos de vidrio</div>

  <div class="card">
    <div class="card-title" id="mp_form_title">Agregar precio</div>
    <div class="form-grid">
      <div class="field">
        <label>Servicio</label>
        <select id="mp_servicio">
          <option value="corte">Corte</option>
          <option value="canteado">Canteado</option>
          <option value="filo_muerto">Filo Muerto</option>
          <option value="taladro">Taladro</option>
          <option value="horno">Horno (Templado)</option>
        </select>
      </div>
      <div class="field">
        <label>Espesor (mm)</label>
        <input id="mp_espesor" type="number" placeholder="6">
      </div>
      <div class="field">
        <label>Precio ($)</label>
        <input id="mp_precio" type="number" step="0.01" placeholder="0.00">
      </div>
    </div>
    <div class="acciones">
      <button class="btn btn-primary" onclick="ModMaquilaPrecios._guardar()">Guardar</button>
      <button class="btn btn-ghost" id="mp_btnCancelar" onclick="ModMaquilaPrecios._cancelarEdicion()" style="display:none">Cancelar edici&oacute;n</button>
    </div>
    <div class="form-error" id="mp_error"></div>
  </div>

  <div class="mq-table">
    <table>
      <thead><tr><th>Servicio</th><th>Espesor</th><th>Precio</th><th></th></tr></thead>
      <tbody id="mp_tabla"><tr><td colspan="4" class="loading-msg">Cargando&#8230;</td></tr></tbody>
    </table>
  </div>

  <div class="card">
    <div class="card-title">Agregar tipo de vidrio</div>
    <div class="form-grid" style="grid-template-columns: 2fr 1fr; align-items:end">
      <div class="field">
        <label>Nombre</label>
        <input id="tv_nombre" placeholder="Ej. Claro, Bronce, Reflectivo&#8230;">
      </div>
      <button class="btn btn-primary" onclick="ModMaquilaPrecios._crearTipo()">+ Agregar</button>
    </div>
    <div class="form-error" id="tv_error"></div>
  </div>

  <div class="mq-table">
    <table>
      <thead><tr><th>Nombre</th><th>Estado</th><th></th></tr></thead>
      <tbody id="tv_tabla"><tr><td colspan="3" class="loading-msg">Cargando&#8230;</td></tr></tbody>
    </table>
  </div>
</div>

<script>
var ModMaquilaPrecios = (function(){
var API = '../api/maquila.php';
var precios = [];
var tipos = [];
var _editando = false;
var SERVICIO_LABEL = { corte:'Corte', canteado:'Canteado', filo_muerto:'Filo Muerto', taladro:'Taladro', horno:'Horno (Templado)' };

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s == null) ? '' : String(s);
  return d.innerHTML;
}

async function cargar() {
  var r1 = await fetch(API + '?recurso=precios');
  precios = await r1.json();
  renderPrecios();
  var r2 = await fetch(API + '?recurso=tipos_vidrio&activos=0');
  tipos = await r2.json();
  renderTipos();
}

function renderPrecios() {
  if (!precios.length) { document.getElementById('mp_tabla').innerHTML = '<tr><td colspan="4" class="loading-msg">Sin precios registrados</td></tr>'; return; }
  var html = '';
  for (var i = 0; i < precios.length; i++) {
    var p = precios[i];
    var idNum = parseInt(p.id, 10) || 0;
    html += '<tr>';
    html += '<td style="font-weight:600">' + esc(SERVICIO_LABEL[p.servicio] || p.servicio) + '</td>';
    html += '<td>' + esc(p.espesor_mm) + 'mm</td>';
    html += '<td style="font-weight:600">$' + esc(parseFloat(p.precio).toFixed(2)) + '</td>';
    html += '<td><div class="row-actions">';
    html += '<button class="link-btn link-edit" onclick="ModMaquilaPrecios._editar(' + idNum + ')">Editar</button>';
    html += '<button class="link-btn link-danger" onclick="ModMaquilaPrecios._eliminar(' + idNum + ')">Desactivar</button>';
    html += '</div></td>';
    html += '</tr>';
  }
  document.getElementById('mp_tabla').innerHTML = html;
}

function renderTipos() {
  if (!tipos.length) { document.getElementById('tv_tabla').innerHTML = '<tr><td colspan="3" class="loading-msg">Sin tipos registrados</td></tr>'; return; }
  var html = '';
  for (var i = 0; i < tipos.length; i++) {
    var t = tipos[i];
    var idNum = parseInt(t.id, 10) || 0;
    var activoNum = (parseInt(t.activo, 10) === 1) ? 1 : 0;
    html += '<tr>';
    html += '<td style="font-weight:600">' + esc(t.nombre) + '</td>';
    html += '<td><span class="est-badge ' + (activoNum === 1 ? 'badge-activo' : 'badge-inactivo') + '">' + (activoNum === 1 ? 'Activo' : 'Inactivo') + '</span></td>';
    html += '<td><button class="link-btn ' + (activoNum === 1 ? 'link-danger' : 'link-edit') + '" onclick="ModMaquilaPrecios._toggleTipo(' + idNum + ',' + activoNum + ')">' + (activoNum === 1 ? 'Desactivar' : 'Activar') + '</button></td>';
    html += '</tr>';
  }
  document.getElementById('tv_tabla').innerHTML = html;
}

function limpiarFormPrecio() {
  document.getElementById('mp_servicio').value = 'corte';
  document.getElementById('mp_espesor').value = '';
  document.getElementById('mp_precio').value = '';
  document.getElementById('mp_form_title').textContent = 'Agregar precio';
  document.getElementById('mp_btnCancelar').style.display = 'none';
  document.getElementById('mp_error').textContent = '';
  _editando = false;
}

async function guardarPrecio() {
  var errorEl = document.getElementById('mp_error');
  errorEl.textContent = '';
  var servicio = document.getElementById('mp_servicio').value;
  var espesor  = parseFloat(document.getElementById('mp_espesor').value) || 0;
  var precio   = parseFloat(document.getElementById('mp_precio').value) || 0;

  if (espesor <= 0) { errorEl.textContent = 'Indica un espesor mayor a 0'; return; }
  if (precio  <= 0) { errorEl.textContent = 'Indica un precio mayor a 0'; return; }

  try {
    var res = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ recurso: 'precios', servicio: servicio, espesor_mm: espesor, precio: precio })
    });
    var data = await res.json();
    if (data.error) { errorEl.textContent = data.error; return; }
    limpiarFormPrecio();
    cargar();
  } catch(e) {
    errorEl.textContent = 'Error de red al guardar';
  }
}

function editarPrecio(id) {
  var p = null;
  for (var i = 0; i < precios.length; i++) {
    if (parseInt(precios[i].id, 10) === id) { p = precios[i]; break; }
  }
  if (!p) return;
  document.getElementById('mp_servicio').value = p.servicio;
  document.getElementById('mp_espesor').value = p.espesor_mm;
  document.getElementById('mp_precio').value = parseFloat(p.precio).toFixed(2);
  document.getElementById('mp_form_title').textContent = 'Editando: ' + (SERVICIO_LABEL[p.servicio] || p.servicio) + ' ' + p.espesor_mm + 'mm';
  document.getElementById('mp_btnCancelar').style.display = 'inline-block';
  document.getElementById('mp_error').textContent = '';
  document.getElementById('mp_precio').focus();
  _editando = true;
}

function cancelarEdicionPrecio() {
  limpiarFormPrecio();
}

async function eliminarPrecio(id) {
  if (!confirm('&#191;Desactivar este precio? Ya no estar&aacute; disponible para nuevas cotizaciones de maquila.')) return;
  await fetch(API, {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recurso: 'precios', id: id })
  });
  cargar();
}

async function crearTipo() {
  var errorEl = document.getElementById('tv_error');
  errorEl.textContent = '';
  var nombre = document.getElementById('tv_nombre').value.trim();
  if (!nombre) { errorEl.textContent = 'Escribe un nombre'; return; }
  try {
    var res = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ recurso: 'tipos_vidrio', nombre: nombre })
    });
    var data = await res.json();
    if (data.error) { errorEl.textContent = data.error; return; }
    document.getElementById('tv_nombre').value = '';
    cargar();
  } catch(e) {
    errorEl.textContent = 'Error de red al guardar';
  }
}

async function toggleTipo(id, activo) {
  await fetch(API, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recurso: 'tipos_vidrio', id: id, activo: activo == 1 ? 0 : 1 })
  });
  cargar();
}

cargar();

return {
  init: cargar,
  _guardar: guardarPrecio,
  _editar: editarPrecio,
  _cancelarEdicion: cancelarEdicionPrecio,
  _eliminar: eliminarPrecio,
  _crearTipo: crearTipo,
  _toggleTipo: toggleTipo
};
})();
</script>
