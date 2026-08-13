<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
$puedeEditar = tienePermiso($user['rol'], 'gestionar_contabilidad');
$puedeBorrar = in_array($user['rol'], ['dir_admin', 'desarrollo']);
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=bitacora_desechos'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
/* S3-03: antes era `body{}` — pisaba el fondo de TODO el dashboard mientras
   este módulo estaba cargado en el SPA. Retargeteado a .main (raíz del módulo). */
.main { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--c-bg, #f8fafc); padding: 24px; max-width: 1200px; margin: 0 auto; }

.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.section-sub { font-size: 12.5px; color: #64748b; margin-bottom: 20px; max-width: 70ch; line-height: 1.5; }

.rango-selector { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rango-selector input, .rango-selector select { padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; }

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
td { padding: 10px 14px; border-top: 1px solid #f1f5f9; font-size: 13px; color: #374151; vertical-align: middle; }
tr:hover td { background: #f8fafc; }
.desc-cell { max-width: 280px; white-space: normal; }
.monto-cell { white-space: nowrap; font-weight: 600; }
.monto-vacio { color: #cbd5e1; font-weight: 400; }

.chip { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
.chip .sw { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.chip-vidrio { background: #e0f2fe; color: #0369a1; }
.chip-madera { background: #fef3c7; color: #92400e; }
.chip-articulos_oficina { background: #ede9fe; color: #6d28d9; }
.chip-otro { background: #f1f5f9; color: #475569; }

.files-btn {
  display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600;
  color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 7px;
  padding: 5px 10px; cursor: pointer; white-space: nowrap;
}
.files-btn:hover { color: #1e293b; border-color: #94a3b8; }

.empty { text-align: center; padding: 48px; color:var(--c-muted); font-size: 15px; }

.modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); z-index: 1000;
  align-items: center; justify-content: center; padding: 20px;
}
.modal-bg.open { display: flex; }
.modal {
  background: white; border-radius: 16px;
  padding: 28px; width: 100%; max-width: 460px;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
  max-height: 90vh; overflow-y: auto;
}
.modal h2 { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 20px; }
.field { margin-bottom: 16px; }
.field label { display: block; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
.field input, .field select, .field textarea {
  width: 100%; padding: 10px 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 14px; color: #1e293b; font-family: inherit;
}
.field textarea { resize: vertical; min-height: 64px; }
.field-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.field-link { background: none; border: none; color: #2563eb; font-size: 12.5px; font-weight: 600; cursor: pointer; padding: 0 0 12px; text-align: left; }
.field-link:hover { text-decoration: underline; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

.file-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
.file-row:last-child { border-bottom: none; }
.file-ic { width: 30px; height: 30px; border-radius: 7px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #64748b; flex-shrink: 0; font-size: 14px; }
.file-name { font-size: 12.5px; color: #1e293b; font-weight: 600; }
.file-meta { font-size: 11px; color:var(--c-muted); }
.file-row .btn { margin-left: auto; }

@media (max-width: 640px) { .field-row2 { grid-template-columns: 1fr; } .desc-cell { max-width: 160px; } }
</style>

<div class="main">

  <div class="top-bar">
    <div class="section-title"><?= icono('truck') ?> Bitácora de Desechos</div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <div class="rango-selector">
        <input type="date" id="fDesde">
        <span style="color:var(--c-muted)">a</span>
        <input type="date" id="fHasta">
        <select id="fCategoria">
          <option value="">Todas las categorías</option>
          <option value="vidrio">Vidrio</option>
          <option value="madera">Madera</option>
          <option value="articulos_oficina">Artículos de oficina</option>
          <option value="otro">Otro</option>
        </select>
      </div>
      <?php if ($puedeEditar): ?>
      <button class="btn btn-ghost" onclick="ModBitacoraDesechos._abrirModalProveedor()">+ Nuevo proveedor</button>
      <button class="btn btn-primary" onclick="ModBitacoraDesechos._abrirModalRecoleccion()">+ Registrar recolección</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="section-sub">Trazabilidad de cuándo el proveedor de reciclaje se lleva la merma física de la planta (vidrio, madera, artículos de oficina), con evidencia adjunta. Registro operativo — no genera nada en Contabilidad ni en el Estado de Resultados.</div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Fecha</th><th>Categoría</th><th>Descripción</th><th>Proveedor</th>
          <th>Monto</th><th>Archivos</th><th>Registró</th>
          <?php if ($puedeBorrar): ?><th>Acción</th><?php endif; ?>
        </tr>
      </thead>
      <tbody id="tablaRegistros">
        <tr><td colspan="8" class="empty">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

</div>

<!-- Modal: registrar recolección -->
<div class="modal-bg" id="modalRecoleccionBg">
  <div class="modal">
    <h2>Registrar recolección</h2>
    <div class="field-row2">
      <div class="field">
        <label>Fecha</label>
        <input type="date" id="rFecha">
      </div>
      <div class="field">
        <label>Categoría</label>
        <select id="rCategoria">
          <option value="vidrio">Vidrio</option>
          <option value="madera">Madera</option>
          <option value="articulos_oficina">Artículos de oficina</option>
          <option value="otro">Otro</option>
        </select>
      </div>
    </div>
    <div class="field">
      <label>Descripción</label>
      <textarea id="rDescripcion" placeholder="Qué se llevaron, cantidad aproximada, notas..."></textarea>
    </div>
    <div class="field">
      <label>Proveedor</label>
      <select id="rProveedor"></select>
    </div>
    <button class="field-link" onclick="ModBitacoraDesechos._abrirModalProveedor()">+ Registrar un proveedor nuevo</button>
    <div class="field">
      <label>Monto (opcional)</label>
      <input type="number" id="rMonto" step="0.01" placeholder="0.00">
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="ModBitacoraDesechos._cerrarModalRecoleccion()">Cancelar</button>
      <button class="btn btn-success" onclick="ModBitacoraDesechos._guardarRecoleccion()">Guardar registro</button>
    </div>
  </div>
</div>

<!-- Modal: nuevo proveedor -->
<div class="modal-bg" id="modalProveedorBg">
  <div class="modal">
    <h2>Nuevo proveedor</h2>
    <div class="field">
      <label>Empresa</label>
      <input type="text" id="pEmpresa" placeholder="Recicladora del Norte">
    </div>
    <div class="field">
      <label>Nombre de contacto</label>
      <input type="text" id="pContacto" placeholder="Roberto Salas">
    </div>
    <div class="field-row2">
      <div class="field">
        <label>Número de contacto</label>
        <input type="text" id="pTelContacto" placeholder="81 1234 5678">
      </div>
      <div class="field">
        <label>Número de empresa</label>
        <input type="text" id="pTelEmpresa" placeholder="81 8765 4321">
      </div>
    </div>
    <div class="field">
      <label>Correo de empresa (opcional)</label>
      <input type="text" id="pCorreo" placeholder="contacto@recicladora.mx">
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="ModBitacoraDesechos._cerrarModalProveedor()">Cancelar</button>
      <button class="btn btn-success" onclick="ModBitacoraDesechos._guardarProveedor()">Guardar proveedor</button>
    </div>
  </div>
</div>

<!-- Modal: archivos de un registro -->
<div class="modal-bg" id="modalArchivosBg">
  <div class="modal">
    <h2 id="archivosTitulo">Archivos adjuntos</h2>
    <div id="archivosLista"></div>
    <?php if ($puedeEditar): ?>
    <div class="field" style="margin-top:16px">
      <label>Adjuntar archivo(s) (jpg, png o pdf, máx. 10 MB c/u)</label>
      <input type="file" id="archivoInput" accept=".jpg,.jpeg,.png,.pdf" multiple onchange="ModBitacoraDesechos._mostrarSeleccion()">
      <div id="archivoSeleccionLabel" style="font-size:11.5px;color:#64748b;margin-top:6px"></div>
    </div>
    <div class="modal-footer" style="justify-content:flex-start;margin-top:0">
      <button class="btn btn-primary btn-sm" onclick="ModBitacoraDesechos._subirArchivo()">Subir archivo(s)</button>
    </div>
    <?php endif; ?>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="ModBitacoraDesechos._cerrarModalArchivos()">Cerrar</button>
    </div>
  </div>
</div>

<script>
window._puedeEditar = <?= $puedeEditar ? 'true' : 'false' ?>;
window._puedeBorrar = <?= $puedeBorrar ? 'true' : 'false' ?>;

var ModBitacoraDesechos = (function(){
var API = '../api/bitacora_desechos.php';
var CAT_LABEL = { vidrio: 'Vidrio', madera: 'Madera', articulos_oficina: 'Art. oficina', otro: 'Otro' };
var _proveedores = [];
var _archivosDesechoId = null;

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
  var cat = document.getElementById('fCategoria').value;
  document.getElementById('fDesde').value = desde;
  document.getElementById('fHasta').value = hasta;

  try {
    var url = API + '?accion=listar&desde=' + desde + '&hasta=' + hasta;
    if (cat) url += '&categoria=' + cat;
    var res = await fetch(url);
    var data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    render(data.registros || []);
  } catch(e) {
    document.getElementById('tablaRegistros').innerHTML =
      '<tr><td colspan="8" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function render(filas) {
  var colspan = window._puedeBorrar ? 8 : 7;
  if (!filas.length) {
    document.getElementById('tablaRegistros').innerHTML =
      '<tr><td colspan="' + colspan + '" class="empty">Sin recolecciones en este rango</td></tr>';
    return;
  }
  var html = '';
  for (var i = 0; i < filas.length; i++) {
    var f = filas[i];
    var montoHtml = f.monto ? fmt(f.monto) : '<span class="monto-vacio">—</span>';
    var nArch = (f.archivos || []).length;
    html += '<tr>';
    html += '<td>' + esc(f.fecha_recoleccion) + '</td>';
    html += '<td><span class="chip chip-' + f.categoria + '"><span class="sw"></span>' + (CAT_LABEL[f.categoria] || f.categoria) + '</span></td>';
    html += '<td class="desc-cell">' + esc(f.descripcion) + '</td>';
    html += '<td>' + esc(f.proveedor_empresa) + '</td>';
    html += '<td class="monto-cell">' + montoHtml + '</td>';
    html += '<td><button class="files-btn" onclick="ModBitacoraDesechos._verArchivos(' + f.id + ')">📎 ' + nArch + '</button></td>';
    html += '<td>' + esc(f.registrado_por) + '</td>';
    if (window._puedeBorrar) {
      html += '<td><button class="btn btn-danger btn-sm" onclick="ModBitacoraDesechos._eliminar(' + f.id + ')">Eliminar</button></td>';
    }
    html += '</tr>';
  }
  document.getElementById('tablaRegistros').innerHTML = html;
}

async function eliminar(id) {
  if (!confirm('¿Eliminar este registro y sus archivos adjuntos?')) return;
  try {
    var res = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ accion: 'eliminar', id: id }) });
    var data = await res.json();
    if (data.ok) { cargar(); } else { alert(data.error || 'Error al eliminar'); }
  } catch(e) { alert('Error de conexión'); }
}

async function cargarProveedores() {
  try {
    var res = await fetch(API + '?accion=listar_proveedores');
    var data = await res.json();
    _proveedores = data.proveedores || [];
    var html = '';
    for (var i = 0; i < _proveedores.length; i++) {
      html += '<option value="' + _proveedores[i].id + '">' + esc(_proveedores[i].empresa) + '</option>';
    }
    document.getElementById('rProveedor').innerHTML = html || '<option value="">Sin proveedores — registra uno primero</option>';
  } catch(e) {}
}

function abrirModalRecoleccion() {
  document.getElementById('rFecha').value = hoy();
  document.getElementById('rCategoria').value = 'vidrio';
  document.getElementById('rDescripcion').value = '';
  document.getElementById('rMonto').value = '';
  cargarProveedores();
  document.getElementById('modalRecoleccionBg').classList.add('open');
}
function cerrarModalRecoleccion() {
  document.getElementById('modalRecoleccionBg').classList.remove('open');
}

async function guardarRecoleccion() {
  var payload = {
    accion: 'crear',
    fecha_recoleccion: document.getElementById('rFecha').value,
    categoria: document.getElementById('rCategoria').value,
    descripcion: document.getElementById('rDescripcion').value.trim(),
    proveedor_id: parseInt(document.getElementById('rProveedor').value || 0),
    monto: document.getElementById('rMonto').value ? parseFloat(document.getElementById('rMonto').value) : null
  };
  if (!payload.fecha_recoleccion || !payload.descripcion || !payload.proveedor_id) {
    alert('Completa fecha, descripción y proveedor'); return;
  }
  try {
    var res = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cerrarModalRecoleccion();
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

function abrirModalProveedor() {
  document.getElementById('pEmpresa').value = '';
  document.getElementById('pContacto').value = '';
  document.getElementById('pTelContacto').value = '';
  document.getElementById('pTelEmpresa').value = '';
  document.getElementById('pCorreo').value = '';
  document.getElementById('modalProveedorBg').classList.add('open');
}
function cerrarModalProveedor() {
  document.getElementById('modalProveedorBg').classList.remove('open');
}

async function guardarProveedor() {
  var payload = {
    accion: 'crear_proveedor',
    empresa: document.getElementById('pEmpresa').value.trim(),
    nombre_contacto: document.getElementById('pContacto').value.trim(),
    telefono_contacto: document.getElementById('pTelContacto').value.trim(),
    telefono_empresa: document.getElementById('pTelEmpresa').value.trim(),
    correo: document.getElementById('pCorreo').value.trim()
  };
  if (!payload.empresa || !payload.nombre_contacto || !payload.telefono_contacto) {
    alert('Completa empresa, nombre de contacto y número de contacto'); return;
  }
  try {
    var res = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cerrarModalProveedor();
    await cargarProveedores();
    if (data.id) document.getElementById('rProveedor').value = data.id;
    // Si el modal de recolección no estaba abierto, ábrelo para continuar el flujo
    if (!document.getElementById('modalRecoleccionBg').classList.contains('open')) {
      document.getElementById('modalRecoleccionBg').classList.add('open');
    }
  } catch(e) { alert('Error de conexión'); }
}

async function verArchivos(desechoId) {
  _archivosDesechoId = desechoId;
  document.getElementById('archivosTitulo').textContent = 'Archivos adjuntos';
  document.getElementById('archivosLista').innerHTML = '<div class="empty" style="padding:20px">Cargando...</div>';
  document.getElementById('modalArchivosBg').classList.add('open');
  try {
    var res = await fetch(API + '?accion=obtener&id=' + desechoId);
    var data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    renderArchivos(data.registro.archivos || []);
  } catch(e) {
    document.getElementById('archivosLista').innerHTML = '<div class="empty" style="padding:20px;color:#dc2626">Error al cargar</div>';
  }
}

function renderArchivos(archivos) {
  if (!archivos.length) {
    document.getElementById('archivosLista').innerHTML = '<div class="empty" style="padding:20px">Sin archivos adjuntos todavía</div>';
    return;
  }
  var html = '';
  for (var i = 0; i < archivos.length; i++) {
    var a = archivos[i];
    html += '<div class="file-row">';
    html += '<div class="file-ic">📄</div>';
    html += '<div><div class="file-name">' + esc(a.nombre_original) + '</div><div class="file-meta">' + esc(a.created_at) + '</div></div>';
    html += '<button class="btn btn-ghost btn-sm" onclick="window.open(\'' + API + '?accion=descargar&id=' + a.id + '\',\'_blank\')">Ver</button>';
    if (window._puedeBorrar) {
      html += '<button class="btn btn-danger btn-sm" onclick="ModBitacoraDesechos._eliminarArchivo(' + a.id + ')">Borrar</button>';
    }
    html += '</div>';
  }
  document.getElementById('archivosLista').innerHTML = html;
}

function cerrarModalArchivos() {
  document.getElementById('modalArchivosBg').classList.remove('open');
  _archivosDesechoId = null;
}

function mostrarSeleccion() {
  var input = document.getElementById('archivoInput');
  var label = document.getElementById('archivoSeleccionLabel');
  if (!input.files || !input.files.length) { label.textContent = ''; return; }
  var nombres = [];
  for (var i = 0; i < input.files.length; i++) nombres.push(input.files[i].name);
  label.textContent = input.files.length + ' archivo(s) seleccionado(s): ' + nombres.join(', ');
}

async function subirArchivo() {
  var input = document.getElementById('archivoInput');
  if (!input.files || !input.files.length) { alert('Selecciona uno o más archivos'); return; }
  if (!_archivosDesechoId) return;

  var errores = [];
  for (var i = 0; i < input.files.length; i++) {
    var fd = new FormData();
    fd.append('accion', 'subir_archivo');
    fd.append('desecho_id', _archivosDesechoId);
    fd.append('archivo', input.files[i]);
    try {
      var res = await fetch(API, { method: 'POST', body: fd });
      var data = await res.json();
      if (!data.ok) errores.push(input.files[i].name + ': ' + (data.error || 'error'));
    } catch(e) {
      errores.push(input.files[i].name + ': error de conexión');
    }
  }

  input.value = '';
  document.getElementById('archivoSeleccionLabel').textContent = '';
  if (errores.length) alert('Algunos archivos no se subieron:\n' + errores.join('\n'));
  verArchivos(_archivosDesechoId);
  cargar();
}

async function eliminarArchivo(id) {
  if (!confirm('¿Borrar este archivo?')) return;
  try {
    var res = await fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ accion: 'eliminar_archivo', id: id }) });
    var data = await res.json();
    if (data.ok) { verArchivos(_archivosDesechoId); cargar(); } else { alert(data.error || 'Error'); }
  } catch(e) { alert('Error de conexión'); }
}

document.getElementById('fDesde').value = inicioMes();
document.getElementById('fHasta').value = hoy();
document.getElementById('fDesde').addEventListener('change', cargar);
document.getElementById('fHasta').addEventListener('change', cargar);
document.getElementById('fCategoria').addEventListener('change', cargar);
['modalRecoleccionBg','modalProveedorBg','modalArchivosBg'].forEach(function(id){
  document.getElementById(id).addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
});

cargar();

return {
  init: cargar,
  _eliminar: eliminar,
  _abrirModalRecoleccion: abrirModalRecoleccion,
  _cerrarModalRecoleccion: cerrarModalRecoleccion,
  _guardarRecoleccion: guardarRecoleccion,
  _abrirModalProveedor: abrirModalProveedor,
  _cerrarModalProveedor: cerrarModalProveedor,
  _guardarProveedor: guardarProveedor,
  _verArchivos: verArchivos,
  _cerrarModalArchivos: cerrarModalArchivos,
  _mostrarSeleccion: mostrarSeleccion,
  _subirArchivo: subirArchivo,
  _eliminarArchivo: eliminarArchivo
};
})();
</script>
