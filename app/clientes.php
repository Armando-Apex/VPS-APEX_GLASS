<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user         = requirePermiso('ver_ordenes');
$rol          = $user['rol'];
$puede_editar = in_array($rol, ['dir_admin', 'dueno', 'comercial']);
$es_admin     = in_array($rol, ['dir_admin', 'dueno']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APEX GLASS — Clientes</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@400;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.header {
  background: #1a1a2e; color: white;
  padding: 16px 24px;
  display: flex; align-items: center; justify-content: space-between;
}
.header h1 { font-size: 20px; font-weight: 800; letter-spacing: 1px; font-family: 'Syncopate', sans-serif; }
.header .right { display: flex; gap: 16px; align-items: center; }
.header a { color: #94a3b8; font-size: 13px; text-decoration: none; }

.main { padding: 24px; max-width: 1200px; margin: 0 auto; }
.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; }

.filtros { display: flex; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
.filtros input, .filtros select {
  padding: 9px 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 14px; color: #1e293b; background: white;
}
.filtros input:focus, .filtros select:focus { outline: none; border-color: #2563eb; }
.filtros input[type=text] { width: 300px; }
.toggle-inactivos { font-size: 13px; color: #64748b; cursor: pointer; display: flex; align-items: center; gap: 6px; }

.btn { padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn-primary { background: #2563eb; color: white; }
.btn-success { background: #16a34a; color: white; }
.btn-ghost   { background: #f1f5f9; color: #374151; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

.table-wrap { background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 24px; }
table { width: 100%; border-collapse: collapse; }
thead { background: #f8fafc; }
th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
td { padding: 13px 16px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #374151; }
tr:hover td { background: #f8fafc; }

.badge-local    { background: #dbeafe; color: #1d4ed8; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.badge-foraneo  { background: #fef3c7; color: #d97706; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.badge-activo   { background: #dcfce7; color: #16a34a; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.badge-inactivo { background: #f1f5f9; color: #94a3b8; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.codigo { font-weight: 800; color: #2563eb; font-size: 13px; }
.empty  { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }
.count  { font-size: 13px; color: #94a3b8; }

.modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1000; align-items: flex-start; justify-content: center; padding-top: 40px; }
.modal-bg.open { display: flex; }
.modal { background: white; border-radius: 16px; padding: 28px; width: 100%; max-width: 560px; box-shadow: 0 20px 60px rgba(0,0,0,.2); max-height: 85vh; overflow-y: auto; }
.modal h2 { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 6px; }
.modal .codigo-display { font-size: 13px; color: #94a3b8; margin-bottom: 20px; }

.field { margin-bottom: 16px; }
.field label { display: block; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
.field input, .field select { width: 100%; padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #1e293b; background: white; }
.field input:focus, .field select:focus { outline: none; border-color: #2563eb; }
.field .hint { font-size: 11px; color: #94a3b8; margin-top: 4px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; border-top: 1px solid #f1f5f9; padding-top: 20px; }

.panel-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 1000; }
.panel-bg.open { display: block; }
.panel { position: fixed; top: 0; right: -480px; width: 460px; height: 100vh; background: white; box-shadow: -4px 0 30px rgba(0,0,0,.12); transition: right .3s ease; overflow-y: auto; z-index: 1001; }
.panel.open { right: 0; }
.panel-header { background: #1a1a2e; color: white; padding: 20px 24px; display: flex; justify-content: space-between; align-items: flex-start; }
.panel-header h3 { font-size: 16px; font-weight: 800; }
.panel-header p  { font-size: 12px; color: #94a3b8; margin-top: 4px; }
.panel-close { background: none; border: none; color: #94a3b8; font-size: 20px; cursor: pointer; }
.panel-body { padding: 24px; }

.bit-item { display: flex; gap: 14px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
.bit-item:last-child { border: none; }
.bit-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; background: #f0f4f8; }
.bit-icon.creacion { background: #dcfce7; }
.bit-icon.cambio   { background: #dbeafe; }
.bit-info { flex: 1; }
.bit-campo { font-size: 13px; font-weight: 700; color: #1e293b; }
.bit-valores { font-size: 12px; color: #64748b; margin-top: 3px; }
.bit-valores .ant { color: #dc2626; text-decoration: line-through; }
.bit-valores .nvo { color: #16a34a; }
.bit-meta { font-size: 11px; color: #94a3b8; margin-top: 4px; }
.bit-empty { text-align: center; padding: 40px; color: #94a3b8; }
</style>
</head>
<body>

<div class="header">
  <h1>APEX GLASS — Clientes</h1>
  <div class="right">
    <a href="dashboard.php">← Dashboard</a>
    <a href="../api/logout.php?redirect=../app/login.php">Salir</a>
  </div>
</div>

<div class="main">

  <div class="top-bar">
    <div class="section-title">👥 Clientes</div>
    <?php if ($puede_editar): ?>
    <button class="btn btn-primary" onclick="abrirModalNuevo()">+ Nuevo cliente</button>
    <?php endif; ?>
  </div>

  <div class="filtros">
    <input type="text" id="busqueda" placeholder="🔍 Buscar por nombre, código o teléfono..." oninput="buscar()">
    <select id="filtroLocalidad" onchange="buscar()">
      <option value="">Todos</option>
      <option value="local">Solo locales</option>
      <option value="foraneo">Solo foráneos</option>
    </select>
    <?php if ($es_admin): ?>
    <label class="toggle-inactivos">
      <input type="checkbox" id="verInactivos" onchange="buscar()">
      Ver inactivos
    </label>
    <?php endif; ?>
    <span class="count" id="conteo"></span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Código</th>
          <th>Razón Social</th>
          <th>Contacto</th>
          <th>Teléfono</th>
          <th>Tipo</th>
          <th>Ciudad</th>
          <th>Estatus</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tablaClientes">
        <tr><td colspan="8" class="empty">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

</div>

<!-- Modal nuevo / editar -->
<div class="modal-bg" id="modalBg">
  <div class="modal">
    <h2 id="modalTitulo">Nuevo cliente</h2>
    <div class="codigo-display" id="modalCodigo"></div>
    <input type="hidden" id="editId">

    <div class="field">
      <label>Razón Social *</label>
      <input type="text" id="fRazonSocial" placeholder="Nombre completo o empresa">
    </div>
    <div class="form-row">
      <div class="field">
        <label>Nombre de contacto</label>
        <input type="text" id="fContacto" placeholder="Ej: Juan Pérez">
      </div>
      <div class="field">
        <label>Teléfono</label>
        <input type="text" id="fTelefono" placeholder="Ej: 8112345678">
      </div>
    </div>
    <div class="field">
      <label>Email</label>
      <input type="email" id="fEmail" placeholder="correo@ejemplo.com">
    </div>
    <div class="form-row">
      <div class="field">
        <label>Tipo de cliente *</label>
        <select id="fLocalidad" onchange="toggleCiudad()">
          <option value="local">Local (MTY / SCT)</option>
          <option value="foraneo">Foráneo</option>
        </select>
      </div>
      <div class="field" id="campoCiudad" style="display:none">
        <label>Ciudad destino *</label>
        <input type="text" id="fCiudad" placeholder="Ej: Saltillo, Tampico...">
      </div>
    </div>
    <?php if ($es_admin): ?>
    <div class="field" id="campoActivo" style="display:none">
      <label>Estatus</label>
      <select id="fActivo">
        <option value="1">Activo</option>
        <option value="0">Inactivo</option>
      </select>
    </div>
    <?php endif; ?>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-success" onclick="guardar()">Guardar</button>
    </div>
  </div>
</div>

<!-- Panel bitácora -->
<div class="panel-bg" id="panelBg" onclick="cerrarPanel()"></div>
<div class="panel" id="panel">
  <div class="panel-header">
    <div>
      <h3 id="panelTitulo">Bitácora</h3>
      <p id="panelSubtitulo"></p>
    </div>
    <button class="panel-close" onclick="cerrarPanel()">✕</button>
  </div>
  <div class="panel-body" id="panelBody">
    <div class="bit-empty">Cargando...</div>
  </div>
</div>

<script>
const API          = '../api/clientes.php';
const PUEDE_EDITAR = <?= $puede_editar ? 'true' : 'false' ?>;
const ES_ADMIN     = <?= $es_admin ? 'true' : 'false' ?>;

let timer = null;
function buscar() { clearTimeout(timer); timer = setTimeout(cargar, 300); }

async function cargar() {
  const q         = document.getElementById('busqueda').value.trim();
  const localidad = document.getElementById('filtroLocalidad').value;
  const inactivos = ES_ADMIN && document.getElementById('verInactivos') && document.getElementById('verInactivos').checked;
  let url = API + '?activos=' + (inactivos ? '0' : '1');
  if (q) url += '&q=' + encodeURIComponent(q);
  try {
    const res  = await fetch(url);
    let lista  = await res.json();
    if (localidad) lista = lista.filter(function(c) { return c.localidad === localidad; });
    renderTabla(lista);
  } catch(e) {
    document.getElementById('tablaClientes').innerHTML = '<tr><td colspan="8" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function renderTabla(lista) {
  document.getElementById('conteo').textContent = lista.length + ' cliente(s)';
  if (!lista.length) {
    document.getElementById('tablaClientes').innerHTML = '<tr><td colspan="8" class="empty">No se encontraron clientes</td></tr>';
    return;
  }
  var html = '';
  for (var i = 0; i < lista.length; i++) {
    var c = lista[i];
    var nombre = (c.razon_social || c.nombre || '—').replace(/'/g, "\\'");
    var codigo = (c.codigo || '').replace(/'/g, "\\'");
    html += '<tr>';
    html += '<td><span class="codigo">' + (c.codigo || '—') + '</span></td>';
    html += '<td style="font-weight:600;max-width:220px">' + (c.razon_social || c.nombre || '—') + '</td>';
    html += '<td style="color:#64748b">' + (c.contacto || '—') + '</td>';
    html += '<td>' + (c.telefono || '—') + '</td>';
    html += '<td><span class="badge-' + (c.localidad || 'local') + '">' + (c.localidad === 'foraneo' ? 'Foráneo' : 'Local') + '</span></td>';
    html += '<td style="color:#64748b">' + (c.ciudad || '—') + '</td>';
    html += '<td><span class="' + (c.activo == 1 ? 'badge-activo' : 'badge-inactivo') + '">' + (c.activo == 1 ? 'Activo' : 'Inactivo') + '</span></td>';
    html += '<td><div style="display:flex;gap:6px">';
    html += '<button class="btn btn-ghost btn-sm" onclick="verBitacora(' + c.id + ',\'' + nombre + '\',\'' + codigo + '\')">📋</button>';
    if (PUEDE_EDITAR) html += '<button class="btn btn-primary btn-sm" onclick="abrirModalEditar(' + c.id + ')">✏️ Editar</button>';
    html += '</div></td></tr>';
  }
  document.getElementById('tablaClientes').innerHTML = html;
}

function abrirModalNuevo() {
  document.getElementById('modalTitulo').textContent   = 'Nuevo cliente';
  document.getElementById('modalCodigo').textContent   = 'El código CTN se generará automáticamente';
  document.getElementById('editId').value              = '';
  document.getElementById('fRazonSocial').value        = '';
  document.getElementById('fContacto').value           = '';
  document.getElementById('fTelefono').value           = '';
  document.getElementById('fEmail').value              = '';
  document.getElementById('fLocalidad').value          = 'local';
  document.getElementById('fCiudad').value             = '';
  document.getElementById('campoCiudad').style.display = 'none';
  if (ES_ADMIN && document.getElementById('campoActivo')) document.getElementById('campoActivo').style.display = 'none';
  document.getElementById('modalBg').classList.add('open');
}

async function abrirModalEditar(id) {
  try {
    var res  = await fetch(API + '?id=' + id);
    var data = await res.json();
    document.getElementById('modalTitulo').textContent   = 'Editar cliente';
    document.getElementById('modalCodigo').textContent   = data.codigo || '';
    document.getElementById('editId').value              = data.id;
    document.getElementById('fRazonSocial').value        = data.razon_social || data.nombre || '';
    document.getElementById('fContacto').value           = data.contacto || '';
    document.getElementById('fTelefono').value           = data.telefono || '';
    document.getElementById('fEmail').value              = data.email || '';
    document.getElementById('fLocalidad').value          = data.localidad || 'local';
    document.getElementById('fCiudad').value             = data.ciudad || '';
    document.getElementById('campoCiudad').style.display = data.localidad === 'foraneo' ? 'block' : 'none';
    if (ES_ADMIN && document.getElementById('campoActivo')) {
      document.getElementById('campoActivo').style.display = 'block';
      document.getElementById('fActivo').value = data.activo;
    }
    document.getElementById('modalBg').classList.add('open');
  } catch(e) { alert('Error al cargar cliente'); }
}

function cerrarModal() { document.getElementById('modalBg').classList.remove('open'); }
function toggleCiudad() {
  document.getElementById('campoCiudad').style.display =
    document.getElementById('fLocalidad').value === 'foraneo' ? 'block' : 'none';
}

async function guardar() {
  var id           = document.getElementById('editId').value;
  var razon_social = document.getElementById('fRazonSocial').value.trim();
  var contacto     = document.getElementById('fContacto').value.trim();
  var telefono     = document.getElementById('fTelefono').value.trim();
  var email        = document.getElementById('fEmail').value.trim();
  var localidad    = document.getElementById('fLocalidad').value;
  var ciudad       = document.getElementById('fCiudad').value.trim();

  if (!razon_social) { alert('La razón social es obligatoria'); return; }
  if (localidad === 'foraneo' && !ciudad) { alert('Indica la ciudad para cliente foráneo'); return; }

  var payload = { razon_social: razon_social, contacto: contacto, telefono: telefono, email: email, localidad: localidad, ciudad: ciudad };
  if (id) { payload.id = parseInt(id); }
  if (ES_ADMIN && id && document.getElementById('fActivo')) { payload.activo = parseInt(document.getElementById('fActivo').value); }

  try {
    var res  = await fetch(API, { method: id ? 'PUT' : 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    var data = await res.json();
    if (data.ok || data.id) { cerrarModal(); cargar(); }
    else alert(data.error || 'Error al guardar');
  } catch(e) { alert('Error de conexión'); }
}

async function verBitacora(id, nombre, codigo) {
  document.getElementById('panelTitulo').textContent    = nombre;
  document.getElementById('panelSubtitulo').textContent = codigo;
  document.getElementById('panelBody').innerHTML        = '<div class="bit-empty">Cargando...</div>';
  document.getElementById('panelBg').classList.add('open');
  document.getElementById('panel').classList.add('open');
  try {
    var res  = await fetch(API + '?id=' + id);
    var data = await res.json();
    var bits = data.bitacora || [];
    if (!bits.length) {
      document.getElementById('panelBody').innerHTML = '<div class="bit-empty">Sin cambios registrados</div>';
      return;
    }
    var html = '';
    for (var i = 0; i < bits.length; i++) {
      var b = bits[i];
      var esCreacion = b.campo === 'CREACION';
      var fecha = new Date(b.fecha).toLocaleString('es-MX', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
      html += '<div class="bit-item">';
      html += '<div class="bit-icon ' + (esCreacion ? 'creacion' : 'cambio') + '">' + (esCreacion ? '🆕' : '✏️') + '</div>';
      html += '<div class="bit-info">';
      html += '<div class="bit-campo">' + (esCreacion ? 'Cliente creado' : b.campo) + '</div>';
      if (!esCreacion) {
        html += '<div class="bit-valores"><span class="ant">' + (b.valor_anterior||'—') + '</span> → <span class="nvo">' + (b.valor_nuevo||'—') + '</span></div>';
      } else {
        html += '<div class="bit-valores" style="color:#16a34a">' + b.valor_nuevo + '</div>';
      }
      html += '<div class="bit-meta">' + fecha + ' · ' + (b.usuario_nombre||'—') + '</div>';
      html += '</div></div>';
    }
    document.getElementById('panelBody').innerHTML = html;
  } catch(e) {
    document.getElementById('panelBody').innerHTML = '<div class="bit-empty" style="color:#dc2626">Error al cargar</div>';
  }
}

function cerrarPanel() {
  document.getElementById('panelBg').classList.remove('open');
  document.getElementById('panel').classList.remove('open');
}

document.getElementById('modalBg').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});

cargar();
</script>
</body>
</html>