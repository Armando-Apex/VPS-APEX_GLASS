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
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }
.main { padding: 24px; max-width: 900px; margin: 0 auto; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 16px; }
.table-wrap { background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 24px; padding: 16px; }
table { width: 100%; border-collapse: collapse; }
th { padding: 10px; text-align: left; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; }
td { padding: 10px; border-top: 1px solid #f1f5f9; font-size: 14px; }
.btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; background: #2563eb; color: white; }
input, select { padding: 8px; border: 1px solid #e2e8f0; border-radius: 6px; }
</style>

<div class="main">
  <div class="section-title">Precios de Maquila</div>
  <div class="table-wrap">
    <div style="display:flex;gap:8px;margin-bottom:12px">
      <select id="mp_servicio"><option value="corte">Corte</option><option value="canteado">Canteado</option><option value="taladro">Taladro</option><option value="horno">Horno</option></select>
      <input id="mp_espesor" type="number" placeholder="Espesor mm">
      <input id="mp_precio" type="number" step="0.01" placeholder="Precio $">
      <button class="btn" onclick="ModMaquilaPrecios._guardar()">Guardar</button>
    </div>
    <table>
      <thead><tr><th>Servicio</th><th>Espesor</th><th>Precio</th><th></th></tr></thead>
      <tbody id="mp_tabla"><tr><td colspan="4">Cargando...</td></tr></tbody>
    </table>
  </div>

  <div class="section-title">Tipos de Vidrio</div>
  <div class="table-wrap">
    <div style="display:flex;gap:8px;margin-bottom:12px">
      <input id="tv_nombre" placeholder="Nombre (ej. Claro)">
      <button class="btn" onclick="ModMaquilaPrecios._crearTipo()">Agregar</button>
    </div>
    <table>
      <thead><tr><th>Nombre</th><th>Activo</th><th></th></tr></thead>
      <tbody id="tv_tabla"><tr><td colspan="3">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
var ModMaquilaPrecios = (function(){
var API = '../api/maquila.php';
var precios = [];
var tipos = [];

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
  if (!precios.length) { document.getElementById('mp_tabla').innerHTML = '<tr><td colspan="4">Sin precios registrados</td></tr>'; return; }
  var html = '';
  for (var i = 0; i < precios.length; i++) {
    var p = precios[i];
    var idNum = parseInt(p.id, 10) || 0;
    html += '<tr><td>' + esc(p.servicio) + '</td><td>' + esc(p.espesor_mm) + 'mm</td><td>$' + esc(parseFloat(p.precio).toFixed(2)) + '</td>';
    html += '<td style="display:flex;gap:8px">';
    html += '<button onclick="ModMaquilaPrecios._editar(' + idNum + ')" style="color:#2563eb">Editar</button>';
    html += '<button onclick="ModMaquilaPrecios._eliminar(' + idNum + ')" style="color:#dc2626">Desactivar</button>';
    html += '</td></tr>';
  }
  document.getElementById('mp_tabla').innerHTML = html;
}

function renderTipos() {
  var html = '';
  for (var i = 0; i < tipos.length; i++) {
    var t = tipos[i];
    var idNum = parseInt(t.id, 10) || 0;
    var activoNum = (parseInt(t.activo, 10) === 1) ? 1 : 0;
    html += '<tr><td>' + esc(t.nombre) + '</td><td>' + (activoNum === 1 ? 'S&iacute;' : 'No') + '</td>';
    html += '<td><button onclick="ModMaquilaPrecios._toggleTipo(' + idNum + ',' + activoNum + ')">' + (activoNum === 1 ? 'Desactivar' : 'Activar') + '</button></td></tr>';
  }
  document.getElementById('tv_tabla').innerHTML = html;
}

async function guardarPrecio() {
  var servicio = document.getElementById('mp_servicio').value;
  var espesor = parseFloat(document.getElementById('mp_espesor').value) || 0;
  var precio = parseFloat(document.getElementById('mp_precio').value) || 0;
  await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recurso: 'precios', servicio: servicio, espesor_mm: espesor, precio: precio })
  });
  cargar();
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
  document.getElementById('mp_precio').focus();
}

async function eliminarPrecio(id) {
  await fetch(API, {
    method: 'DELETE',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recurso: 'precios', id: id })
  });
  cargar();
}

async function crearTipo() {
  var nombre = document.getElementById('tv_nombre').value.trim();
  if (!nombre) return;
  await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recurso: 'tipos_vidrio', nombre: nombre })
  });
  document.getElementById('tv_nombre').value = '';
  cargar();
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

return { init: cargar, _guardar: guardarPrecio, _editar: editarPrecio, _eliminar: eliminarPrecio, _crearTipo: crearTipo, _toggleTipo: toggleTipo };
})();
</script>
