<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_rh');
$puedeEditar = tienePermiso($user['rol'], 'gestionar_rh');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=rh_empleados'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.main { padding: 24px; max-width: 1200px; margin: 0 auto; }

.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.search-box input {
  padding: 9px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; width: 240px;
}

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
  overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); overflow-x: auto;
}
table { width: 100%; border-collapse: collapse; }
thead { background: #f8fafc; }
th {
  padding: 12px 14px; text-align: left;
  font-size: 11px; font-weight: 700; color: #64748b;
  text-transform: uppercase; letter-spacing: .5px; white-space: nowrap;
}
td { padding: 10px 14px; border-top: 1px solid #f1f5f9; font-size: 13px; color: #374151; white-space: nowrap; }
tr.fila-empleado:hover td { background: #f8fafc; cursor: pointer; }
.empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }

.foto-mini {
  width: 32px; height: 32px; border-radius: 50%; object-fit: cover;
  background: #e2e8f0; display: inline-block; vertical-align: middle;
}
.foto-mini-placeholder {
  width: 32px; height: 32px; border-radius: 50%; background: #cbd5e1; color: #64748b;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700; vertical-align: middle;
}
.badge-inactivo { background: #fee2e2; color: #b91c1c; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; margin-left: 6px; }

/* Modal detalle */
.modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); z-index: 1000;
  align-items: flex-start; justify-content: center; overflow-y: auto; padding: 30px 16px;
}
.modal-bg.open { display: flex; }
.modal {
  background: white; border-radius: 16px;
  padding: 0; width: 100%; max-width: 720px;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
}
.modal-sm { max-width: 420px; padding: 28px; }
.modal-header { display: flex; align-items: center; gap: 14px; padding: 24px 28px 0; }
.modal-header h2 { font-size: 18px; font-weight: 800; color: #1e293b; }
.modal-header .foto-grande {
  width: 56px; height: 56px; border-radius: 50%; object-fit: cover; background: #e2e8f0;
}
.modal-close { margin-left: auto; background: none; border: none; font-size: 22px; cursor: pointer; color: #94a3b8; }

.tabs { display: flex; gap: 4px; padding: 16px 28px 0; border-bottom: 1px solid #e2e8f0; }
.tab-btn {
  padding: 10px 14px; font-size: 13px; font-weight: 700; color: #64748b;
  background: none; border: none; cursor: pointer; border-bottom: 2px solid transparent;
}
.tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
.tab-content { padding: 22px 28px 28px; display: none; }
.tab-content.active { display: block; }

.field { margin-bottom: 14px; }
.field label { display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
.field input, .field select, .field textarea {
  width: 100%; padding: 9px 12px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 13px; color: #1e293b; background: white;
}
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px; }
.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 18px; }

.mini-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.mini-item {
  display: flex; align-items: center; gap: 10px; padding: 10px 12px;
  background: #f8fafc; border-radius: 8px; font-size: 13px;
}
.mini-item .flex1 { flex: 1; min-width: 0; }
.mini-item .tipo { font-weight: 700; color: #1e293b; }
.mini-item .sub { color: #94a3b8; font-size: 11px; }
.mini-item a { color: #2563eb; text-decoration: none; font-size: 12px; font-weight: 700; }

.periodo-card {
  background: #f8fafc; border-radius: 10px; padding: 12px 14px; margin-bottom: 8px; cursor: pointer;
}
.periodo-card:hover { background: #eef2f7; }
.periodo-card .top { display: flex; justify-content: space-between; font-size: 13px; font-weight: 700; color: #1e293b; }
.periodo-card .bar { height: 6px; background: #e2e8f0; border-radius: 99px; margin-top: 8px; overflow: hidden; }
.periodo-card .bar-fill { height: 100%; background: #2563eb; }
.periodo-card .info { font-size: 11px; color: #64748b; margin-top: 6px; }

.upload-row { display: flex; gap: 8px; align-items: flex-end; margin-bottom: 16px; flex-wrap: wrap; }
.upload-row .field { margin-bottom: 0; flex: 1; min-width: 140px; }
</style>

<div class="main">
  <div class="top-bar">
    <div class="section-title"><?= icono('users') ?> Recursos Humanos</div>
    <div style="display:flex;gap:10px;align-items:center">
      <div class="search-box"><input type="text" id="fBuscar" placeholder="Buscar empleado..."></div>
      <?php if ($puedeEditar): ?><button class="btn btn-primary" onclick="ModRH._abrirNuevoEmpleado()">+ Empleado</button><?php endif; ?>
    </div>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr><th></th><th>Nombre</th><th>Puesto</th><th>Departamento</th><th>Antigüedad</th><th>Ingreso</th></tr>
      </thead>
      <tbody id="tablaEmpleados"><tr><td colspan="6" class="empty">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>

<!-- Modal: nuevo empleado -->
<div class="modal-bg" id="modalNuevoBg">
  <div class="modal modal-sm">
    <h2 style="margin-bottom:18px">Nuevo empleado</h2>
    <div class="field"><label>Nombre</label><input type="text" id="nNombre"></div>
    <div class="field"><label>Puesto</label><input type="text" id="nPuesto"></div>
    <div class="field"><label>Departamento</label><input type="text" id="nDepartamento"></div>
    <div class="field">
      <label>Área</label>
      <select id="nArea"><option value="planta">Planta</option><option value="oficina">Oficina</option></select>
    </div>
    <div class="field"><label>Sueldo base</label><input type="number" id="nSueldoBase" step="0.01"></div>
    <div class="field"><label>Fecha de ingreso</label><input type="date" id="nFechaIngreso"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="ModRH._cerrarNuevoEmpleado()">Cancelar</button>
      <button class="btn btn-success" onclick="ModRH._guardarNuevoEmpleado()">Guardar</button>
    </div>
  </div>
</div>

<!-- Modal: detalle empleado (expediente / documentos / vacaciones / incidencias) -->
<div class="modal-bg" id="modalDetalleBg">
  <div class="modal">
    <div class="modal-header">
      <img class="foto-grande" id="dFoto" src="" style="display:none">
      <div class="foto-mini-placeholder" id="dFotoPlaceholder" style="width:56px;height:56px;font-size:18px"></div>
      <div>
        <h2 id="dNombre">-</h2>
        <div style="font-size:12px;color:#94a3b8" id="dSub">-</div>
      </div>
      <button class="modal-close" onclick="ModRH._cerrarDetalle()">&times;</button>
    </div>
    <div class="tabs">
      <button class="tab-btn active" data-tab="expediente" onclick="ModRH._cambiarTab('expediente')">Expediente</button>
      <button class="tab-btn" data-tab="documentos" onclick="ModRH._cambiarTab('documentos')">Documentos</button>
      <button class="tab-btn" data-tab="vacaciones" onclick="ModRH._cambiarTab('vacaciones')">Vacaciones</button>
      <button class="tab-btn" data-tab="incidencias" onclick="ModRH._cambiarTab('incidencias')">Incidencias</button>
    </div>

    <!-- Tab Expediente -->
    <div class="tab-content active" id="tabExpediente">
      <div class="field">
        <label>Foto</label>
        <input type="file" id="eFotoArchivo" accept="image/jpeg,image/png">
      </div>
      <div class="grid-2">
        <div class="field"><label>Nombre</label><input type="text" id="eNombre"></div>
        <div class="field"><label>Puesto</label><input type="text" id="ePuesto"></div>
        <div class="field"><label>Departamento</label><input type="text" id="eDepartamento"></div>
        <div class="field"><label>Área</label><select id="eArea"><option value="planta">Planta</option><option value="oficina">Oficina</option></select></div>
        <div class="field"><label>Sueldo base</label><input type="number" id="eSueldoBase" step="0.01"></div>
        <div class="field"><label>Fecha de ingreso</label><input type="date" id="eFechaIngreso"></div>
        <div class="field"><label>Fecha de nacimiento</label><input type="date" id="eFechaNacimiento"></div>
        <div class="field"><label>CURP</label><input type="text" id="eCurp" maxlength="18"></div>
        <div class="field"><label>RFC</label><input type="text" id="eRfc" maxlength="13"></div>
        <div class="field"><label>NSS (IMSS)</label><input type="text" id="eNss" maxlength="11"></div>
        <div class="field"><label>Teléfono</label><input type="text" id="eTelefono"></div>
        <div class="field"><label>Fecha de baja (si aplica)</label><input type="date" id="eFechaBaja"></div>
      </div>
      <div class="field"><label>Dirección</label><input type="text" id="eDireccion"></div>
      <div class="grid-2">
        <div class="field"><label>Contacto de emergencia — nombre</label><input type="text" id="eContactoNombre"></div>
        <div class="field"><label>Contacto de emergencia — teléfono</label><input type="text" id="eContactoTelefono"></div>
      </div>
      <?php if ($puedeEditar): ?>
      <div class="modal-footer"><button class="btn btn-success" onclick="ModRH._guardarExpediente()">Guardar expediente</button></div>
      <?php endif; ?>
    </div>

    <!-- Tab Documentos -->
    <div class="tab-content" id="tabDocumentos">
      <?php if ($puedeEditar): ?>
      <div class="upload-row">
        <div class="field">
          <label>Tipo de documento</label>
          <select id="docTipo">
            <option value="Identificación (INE)">Identificación (INE)</option>
            <option value="CURP">CURP</option>
            <option value="Acta de nacimiento">Acta de nacimiento</option>
            <option value="Comprobante de domicilio">Comprobante de domicilio</option>
            <option value="Contrato">Contrato</option>
            <option value="Certificado médico">Certificado médico</option>
            <option value="Otro">Otro</option>
          </select>
        </div>
        <div class="field"><label>Archivo (jpg/png/pdf)</label><input type="file" id="docArchivo" accept="image/jpeg,image/png,application/pdf"></div>
        <button class="btn btn-primary btn-sm" onclick="ModRH._subirDocumento()">Subir</button>
      </div>
      <?php endif; ?>
      <div class="mini-list" id="listaDocumentos"></div>
    </div>

    <!-- Tab Vacaciones -->
    <div class="tab-content" id="tabVacaciones">
      <div id="listaVacaciones"></div>
    </div>

    <!-- Tab Incidencias -->
    <div class="tab-content" id="tabIncidencias">
      <?php if ($puedeEditar): ?>
      <div class="grid-2" style="margin-bottom:10px">
        <div class="field">
          <label>Tipo</label>
          <select id="incTipo">
            <option value="incapacidad_enfermedad">Incapacidad — enfermedad general</option>
            <option value="incapacidad_maternidad">Incapacidad — maternidad</option>
            <option value="incapacidad_paternidad">Permiso de paternidad</option>
            <option value="riesgo_trabajo">Incapacidad — riesgo de trabajo</option>
            <option value="fallecimiento_familiar">Fallecimiento familiar</option>
            <option value="permiso_personal_con_goce">Permiso personal (con goce de sueldo)</option>
            <option value="permiso_personal_sin_goce">Permiso personal (sin goce de sueldo)</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="field"><label>&nbsp;</label><label style="display:flex;align-items:center;gap:6px;font-weight:400;text-transform:none;font-size:13px;color:#374151"><input type="checkbox" id="incGoce" style="width:auto"> Con goce de sueldo</label></div>
        <div class="field"><label>Fecha inicio</label><input type="date" id="incInicio"></div>
        <div class="field"><label>Fecha fin</label><input type="date" id="incFin"></div>
      </div>
      <div class="field"><label>Notas</label><input type="text" id="incNotas" placeholder="Opcional"></div>
      <div class="modal-footer" style="margin-top:0;margin-bottom:16px">
        <button class="btn btn-primary btn-sm" onclick="ModRH._crearIncidencia()">Registrar incidencia</button>
      </div>
      <?php endif; ?>
      <div class="mini-list" id="listaIncidencias"></div>
    </div>
  </div>
</div>

<!-- Modal: registrar vacación dentro de un periodo -->
<div class="modal-bg" id="modalVacBg">
  <div class="modal modal-sm">
    <h2 style="margin-bottom:6px" id="vacTitulo">Registrar vacaciones</h2>
    <div style="font-size:12px;color:#94a3b8;margin-bottom:16px" id="vacSaldo"></div>
    <div class="field">
      <label>Tipo</label>
      <select id="vacTipo">
        <option value="tomado">Días tomados (descanso real)</option>
        <option value="pagado_sin_ausencia">Días pagados sin ausencia</option>
      </select>
    </div>
    <div class="grid-2">
      <div class="field"><label>Fecha inicio</label><input type="date" id="vacInicio"></div>
      <div class="field"><label>Fecha fin</label><input type="date" id="vacFin"></div>
    </div>
    <div class="field"><label>Días</label><input type="number" id="vacDias" min="1"></div>
    <div class="field"><label>Notas</label><input type="text" id="vacNotas" placeholder="Opcional"></div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="ModRH._cerrarModalVac()">Cancelar</button>
      <button class="btn btn-success" onclick="ModRH._guardarVacacion()">Guardar</button>
    </div>
  </div>
</div>

<script>
var ModRH = (function(){
var API_RH = '../api/rh.php';
var API_NOMINA = '../api/nomina.php';
var empleados = [];
var empleadoActual = null;
var periodoActualId = null;

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s === null || s === undefined) ? '' : String(s);
  return d.innerHTML;
}

function iniciales(nombre) {
  var partes = (nombre || '').trim().split(/\s+/);
  var ini = '';
  for (var i = 0; i < Math.min(2, partes.length); i++) { ini += partes[i].charAt(0).toUpperCase(); }
  return ini || '?';
}

async function cargar() {
  try {
    var res = await fetch(API_RH + '?accion=listar');
    var data = await res.json();
    empleados = data.error ? [] : data;
    render();
  } catch(e) {
    document.getElementById('tablaEmpleados').innerHTML = '<tr><td colspan="6" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function render() {
  var q = (document.getElementById('fBuscar').value || '').toLowerCase();
  var filtrados = [];
  for (var i = 0; i < empleados.length; i++) {
    if (!q || empleados[i].nombre.toLowerCase().indexOf(q) !== -1) filtrados.push(empleados[i]);
  }
  if (!filtrados.length) {
    document.getElementById('tablaEmpleados').innerHTML = '<tr><td colspan="6" class="empty">Sin empleados</td></tr>';
    return;
  }
  var html = '';
  for (var i = 0; i < filtrados.length; i++) {
    var e = filtrados[i];
    var fotoCell = e.foto
      ? '<img class="foto-mini" src="' + API_RH + '?accion=descargar&tipo=foto&empleado_id=' + e.id + '">'
      : '<span class="foto-mini-placeholder">' + esc(iniciales(e.nombre)) + '</span>';
    html += '<tr class="fila-empleado" onclick="ModRH._abrirDetalle(' + e.id + ')">';
    html += '<td>' + fotoCell + '</td>';
    html += '<td>' + esc(e.nombre) + (e.activo == 0 ? '<span class="badge-inactivo">BAJA</span>' : '') + '</td>';
    html += '<td>' + esc(e.puesto || '-') + '</td>';
    html += '<td>' + esc(e.departamento || '-') + '</td>';
    html += '<td>' + (e.antiguedad_anios !== null ? e.antiguedad_anios + ' año(s)' : '-') + '</td>';
    html += '<td>' + esc(e.fecha_ingreso || '-') + '</td>';
    html += '</tr>';
  }
  document.getElementById('tablaEmpleados').innerHTML = html;
}

// ── Nuevo empleado ──────────────────────────────────────────────────────────
function abrirNuevoEmpleado() {
  document.getElementById('nNombre').value = '';
  document.getElementById('nPuesto').value = '';
  document.getElementById('nDepartamento').value = '';
  document.getElementById('nArea').value = 'planta';
  document.getElementById('nSueldoBase').value = '';
  document.getElementById('nFechaIngreso').value = '';
  document.getElementById('modalNuevoBg').classList.add('open');
}
function cerrarNuevoEmpleado() { document.getElementById('modalNuevoBg').classList.remove('open'); }

async function guardarNuevoEmpleado() {
  var nombre = document.getElementById('nNombre').value.trim();
  if (!nombre) { alert('El nombre es obligatorio'); return; }
  var payload = {
    nombre: nombre,
    puesto: document.getElementById('nPuesto').value.trim(),
    departamento: document.getElementById('nDepartamento').value.trim(),
    area: document.getElementById('nArea').value,
    sueldo_base: parseFloat(document.getElementById('nSueldoBase').value || 0),
    fecha_ingreso: document.getElementById('nFechaIngreso').value
  };
  try {
    var res = await fetch(API_NOMINA + '?accion=crear_empleado', {
      method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cerrarNuevoEmpleado();
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

// ── Detalle empleado ──────────────────────────────────────────────────────────
async function abrirDetalle(id) {
  try {
    var res = await fetch(API_RH + '?accion=detalle&id=' + id);
    var data = await res.json();
    if (data.error) { alert(data.error); return; }
    empleadoActual = data.empleado;

    document.getElementById('dNombre').textContent = empleadoActual.nombre;
    document.getElementById('dSub').textContent = (empleadoActual.puesto || '-') + ' · ' + (empleadoActual.departamento || 'Sin depto.');
    var fotoImg = document.getElementById('dFoto');
    var fotoPh = document.getElementById('dFotoPlaceholder');
    if (empleadoActual.foto) {
      fotoImg.src = API_RH + '?accion=descargar&tipo=foto&empleado_id=' + empleadoActual.id;
      fotoImg.style.display = 'block'; fotoPh.style.display = 'none';
    } else {
      fotoImg.style.display = 'none'; fotoPh.style.display = 'flex';
      fotoPh.textContent = iniciales(empleadoActual.nombre);
    }

    document.getElementById('eNombre').value = empleadoActual.nombre || '';
    document.getElementById('ePuesto').value = empleadoActual.puesto || '';
    document.getElementById('eDepartamento').value = empleadoActual.departamento || '';
    document.getElementById('eArea').value = empleadoActual.area || 'oficina';
    document.getElementById('eSueldoBase').value = empleadoActual.sueldo_base || '';
    document.getElementById('eFechaIngreso').value = empleadoActual.fecha_ingreso || '';
    document.getElementById('eFechaNacimiento').value = empleadoActual.fecha_nacimiento || '';
    document.getElementById('eCurp').value = empleadoActual.curp || '';
    document.getElementById('eRfc').value = empleadoActual.rfc || '';
    document.getElementById('eNss').value = empleadoActual.nss || '';
    document.getElementById('eTelefono').value = empleadoActual.telefono || '';
    document.getElementById('eFechaBaja').value = empleadoActual.fecha_baja || '';
    document.getElementById('eDireccion').value = empleadoActual.direccion || '';
    document.getElementById('eContactoNombre').value = empleadoActual.contacto_emergencia_nombre || '';
    document.getElementById('eContactoTelefono').value = empleadoActual.contacto_emergencia_telefono || '';
    document.getElementById('eFotoArchivo').value = '';

    renderDocumentos(data.documentos || []);
    renderVacaciones(data.vacaciones || []);
    renderIncidencias(data.incidencias || []);

    cambiarTab('expediente');
    document.getElementById('modalDetalleBg').classList.add('open');
  } catch(e) { alert('Error al cargar el expediente'); }
}

function cerrarDetalle() {
  document.getElementById('modalDetalleBg').classList.remove('open');
  empleadoActual = null;
}

function cambiarTab(tab) {
  var btns = document.querySelectorAll('.tab-btn');
  for (var i = 0; i < btns.length; i++) {
    btns[i].classList.toggle('active', btns[i].getAttribute('data-tab') === tab);
  }
  var conts = document.querySelectorAll('.tab-content');
  for (var i = 0; i < conts.length; i++) {
    conts[i].classList.toggle('active', conts[i].id === 'tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
  }
}

async function guardarExpediente() {
  if (!empleadoActual) return;
  var fotoInput = document.getElementById('eFotoArchivo');
  if (fotoInput.files && fotoInput.files[0]) {
    var fd = new FormData();
    fd.append('empleado_id', empleadoActual.id);
    fd.append('archivo', fotoInput.files[0]);
    try {
      var resFoto = await fetch(API_RH + '?accion=subir_foto', { method: 'POST', body: fd });
      var dataFoto = await resFoto.json();
      if (!dataFoto.ok) { alert(dataFoto.error || 'Error al subir la foto'); return; }
    } catch(e) { alert('Error al subir la foto'); return; }
  }

  var payload = {
    id: empleadoActual.id,
    nombre: document.getElementById('eNombre').value.trim(),
    puesto: document.getElementById('ePuesto').value.trim(),
    departamento: document.getElementById('eDepartamento').value.trim(),
    area: document.getElementById('eArea').value,
    sueldo_base: parseFloat(document.getElementById('eSueldoBase').value || 0),
    fecha_ingreso: document.getElementById('eFechaIngreso').value,
    fecha_nacimiento: document.getElementById('eFechaNacimiento').value,
    curp: document.getElementById('eCurp').value.trim(),
    rfc: document.getElementById('eRfc').value.trim(),
    nss: document.getElementById('eNss').value.trim(),
    telefono: document.getElementById('eTelefono').value.trim(),
    fecha_baja: document.getElementById('eFechaBaja').value,
    direccion: document.getElementById('eDireccion').value.trim(),
    contacto_emergencia_nombre: document.getElementById('eContactoNombre').value.trim(),
    contacto_emergencia_telefono: document.getElementById('eContactoTelefono').value.trim()
  };
  try {
    var res = await fetch(API_NOMINA + '?accion=editar_empleado', {
      method: 'PUT', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cerrarDetalle();
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

// ── Documentos ──────────────────────────────────────────────────────────────
function renderDocumentos(docs) {
  if (!docs.length) { document.getElementById('listaDocumentos').innerHTML = '<div class="empty" style="padding:20px">Sin documentos</div>'; return; }
  var html = '';
  for (var i = 0; i < docs.length; i++) {
    var d = docs[i];
    html += '<div class="mini-item">';
    html += '<div class="flex1"><div class="tipo">' + esc(d.tipo_documento) + '</div>';
    html += '<div class="sub">' + esc(d.nombre_original) + ' · ' + esc((d.created_at || '').substring(0,10)) + '</div></div>';
    html += '<a href="' + API_RH + '?accion=descargar&id=' + d.id + '" target="_blank">Ver</a>';
    <?php if ($puedeEditar): ?>
    html += '&nbsp;&nbsp;<a href="#" onclick="ModRH._borrarDocumento(' + d.id + ');return false" style="color:#b91c1c">Borrar</a>';
    <?php endif; ?>
    html += '</div>';
  }
  document.getElementById('listaDocumentos').innerHTML = html;
}

async function subirDocumento() {
  if (!empleadoActual) return;
  var archivo = document.getElementById('docArchivo').files[0];
  if (!archivo) { alert('Selecciona un archivo'); return; }
  var fd = new FormData();
  fd.append('empleado_id', empleadoActual.id);
  fd.append('tipo_documento', document.getElementById('docTipo').value);
  fd.append('archivo', archivo);
  try {
    var res = await fetch(API_RH + '?accion=subir_documento', { method: 'POST', body: fd });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al subir'); return; }
    document.getElementById('docArchivo').value = '';
    abrirDetalle(empleadoActual.id);
  } catch(e) { alert('Error de conexión'); }
}

async function borrarDocumento(id) {
  if (!confirm('¿Borrar este documento?')) return;
  try {
    var res = await fetch(API_RH + '?accion=borrar_documento', {
      method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id: id})
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al borrar'); return; }
    abrirDetalle(empleadoActual.id);
  } catch(e) { alert('Error de conexión'); }
}

// ── Vacaciones ──────────────────────────────────────────────────────────────
function renderVacaciones(periodos) {
  if (!periodos.length) {
    document.getElementById('listaVacaciones').innerHTML = '<div class="empty" style="padding:20px">Aún no cumple su primer año de antigüedad</div>';
    return;
  }
  var html = '';
  for (var i = 0; i < periodos.length; i++) {
    var p = periodos[i];
    var saldo = p.dias_derecho - p.dias_tomados - p.dias_pagados_sin_ausencia;
    var usado = p.dias_derecho > 0 ? Math.round(((p.dias_tomados + p.dias_pagados_sin_ausencia) / p.dias_derecho) * 100) : 0;
    html += '<div class="periodo-card" onclick="ModRH._abrirModalVac(' + p.id + ',' + saldo + ',' + i + ')" data-periodos-idx="' + i + '">';
    html += '<div class="top"><span>Año ' + p.anio_antiguedad + ' de antigüedad</span><span>' + saldo + ' de ' + p.dias_derecho + ' días disponibles</span></div>';
    html += '<div class="bar"><div class="bar-fill" style="width:' + usado + '%"></div></div>';
    html += '<div class="info">' + esc(p.fecha_inicio_periodo) + ' a ' + esc(p.fecha_fin_periodo) + ' · Tomados: ' + p.dias_tomados + ' · Pagados sin ausencia: ' + p.dias_pagados_sin_ausencia + '</div>';
    html += '</div>';
  }
  document.getElementById('listaVacaciones').innerHTML = html;
  window._periodosActuales = periodos;
}

function abrirModalVac(periodoId, saldo) {
  periodoActualId = periodoId;
  document.getElementById('vacTitulo').textContent = 'Registrar vacaciones';
  document.getElementById('vacSaldo').textContent = 'Saldo disponible en este periodo: ' + saldo + ' día(s)';
  document.getElementById('vacTipo').value = 'tomado';
  document.getElementById('vacInicio').value = '';
  document.getElementById('vacFin').value = '';
  document.getElementById('vacDias').value = '';
  document.getElementById('vacNotas').value = '';
  document.getElementById('modalVacBg').classList.add('open');
}
function cerrarModalVac() { document.getElementById('modalVacBg').classList.remove('open'); }

async function guardarVacacion() {
  var payload = {
    periodo_id: periodoActualId,
    fecha_inicio: document.getElementById('vacInicio').value,
    fecha_fin: document.getElementById('vacFin').value,
    dias: parseInt(document.getElementById('vacDias').value || 0, 10),
    tipo: document.getElementById('vacTipo').value,
    notas: document.getElementById('vacNotas').value.trim()
  };
  if (!payload.fecha_inicio || !payload.fecha_fin || !payload.dias) { alert('Completa fecha inicio, fin y días'); return; }
  try {
    var res = await fetch(API_RH + '?accion=vacaciones_registrar', {
      method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al registrar'); return; }
    cerrarModalVac();
    abrirDetalle(empleadoActual.id);
    cambiarTab('vacaciones');
  } catch(e) { alert('Error de conexión'); }
}

// ── Incidencias ─────────────────────────────────────────────────────────────
var TIPO_INCIDENCIA_LABEL = {
  incapacidad_enfermedad: 'Incapacidad — enfermedad',
  incapacidad_maternidad: 'Incapacidad — maternidad',
  incapacidad_paternidad: 'Permiso de paternidad',
  riesgo_trabajo: 'Incapacidad — riesgo de trabajo',
  fallecimiento_familiar: 'Fallecimiento familiar',
  permiso_personal_con_goce: 'Permiso personal (con goce)',
  permiso_personal_sin_goce: 'Permiso personal (sin goce)',
  otro: 'Otro'
};

function renderIncidencias(incidencias) {
  if (!incidencias.length) { document.getElementById('listaIncidencias').innerHTML = '<div class="empty" style="padding:20px">Sin incidencias registradas</div>'; return; }
  var html = '';
  for (var i = 0; i < incidencias.length; i++) {
    var inc = incidencias[i];
    html += '<div class="mini-item">';
    html += '<div class="flex1"><div class="tipo">' + esc(TIPO_INCIDENCIA_LABEL[inc.tipo] || inc.tipo) + '</div>';
    html += '<div class="sub">' + esc(inc.fecha_inicio) + ' a ' + esc(inc.fecha_fin) + ' · ' + inc.dias + ' día(s)' + (inc.goce_sueldo == 1 ? ' · Con goce de sueldo' : '') + '</div></div>';
    <?php if ($puedeEditar): ?>
    html += '<a href="#" onclick="ModRH._borrarIncidencia(' + inc.id + ');return false" style="color:#b91c1c;text-decoration:none;font-size:12px;font-weight:700">Borrar</a>';
    <?php endif; ?>
    html += '</div>';
  }
  document.getElementById('listaIncidencias').innerHTML = html;
}

async function crearIncidencia() {
  if (!empleadoActual) return;
  var payload = {
    empleado_id: empleadoActual.id,
    tipo: document.getElementById('incTipo').value,
    goce_sueldo: document.getElementById('incGoce').checked ? 1 : 0,
    fecha_inicio: document.getElementById('incInicio').value,
    fecha_fin: document.getElementById('incFin').value,
    notas: document.getElementById('incNotas').value.trim()
  };
  if (!payload.fecha_inicio || !payload.fecha_fin) { alert('Completa fecha inicio y fin'); return; }
  try {
    var res = await fetch(API_RH + '?accion=incidencia_crear', {
      method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload)
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al registrar'); return; }
    document.getElementById('incNotas').value = '';
    abrirDetalle(empleadoActual.id);
    cambiarTab('incidencias');
  } catch(e) { alert('Error de conexión'); }
}

async function borrarIncidencia(id) {
  if (!confirm('¿Borrar esta incidencia?')) return;
  try {
    var res = await fetch(API_RH + '?accion=incidencia_borrar', {
      method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({id: id})
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al borrar'); return; }
    abrirDetalle(empleadoActual.id);
    cambiarTab('incidencias');
  } catch(e) { alert('Error de conexión'); }
}

document.getElementById('fBuscar').addEventListener('input', render);
document.getElementById('modalNuevoBg').addEventListener('click', function(e){ if (e.target === this) cerrarNuevoEmpleado(); });
document.getElementById('modalDetalleBg').addEventListener('click', function(e){ if (e.target === this) cerrarDetalle(); });
document.getElementById('modalVacBg').addEventListener('click', function(e){ if (e.target === this) cerrarModalVac(); });

cargar();

return {
  init: cargar,
  _abrirNuevoEmpleado: abrirNuevoEmpleado,
  _cerrarNuevoEmpleado: cerrarNuevoEmpleado,
  _guardarNuevoEmpleado: guardarNuevoEmpleado,
  _abrirDetalle: abrirDetalle,
  _cerrarDetalle: cerrarDetalle,
  _cambiarTab: cambiarTab,
  _guardarExpediente: guardarExpediente,
  _subirDocumento: subirDocumento,
  _borrarDocumento: borrarDocumento,
  _abrirModalVac: abrirModalVac,
  _cerrarModalVac: cerrarModalVac,
  _guardarVacacion: guardarVacacion,
  _crearIncidencia: crearIncidencia,
  _borrarIncidencia: borrarIncidencia
};
})();
</script>
