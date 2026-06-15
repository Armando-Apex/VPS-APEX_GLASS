<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user    = requirePermiso('ver_ordenes');
$rol     = $user['rol'];
$esAdmin = $user['rol'] === 'dir_admin';
$puedeSubir = in_array($rol, ['dir_admin', 'administracion', 'comercial']);
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=archivos_ordenes'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<style>
.arch-wrap { padding: 24px; max-width: 1100px; }
.page-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #94a3b8; margin-bottom: 20px; }

/* Buscador */
.arch-search-bar { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: flex-end; }
.arch-field { display: flex; flex-direction: column; gap: 4px; flex: 1; min-width: 160px; }
.arch-field label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.arch-field input { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
.arch-field input:focus { border-color: #2563eb; }
.btn-buscar { padding: 9px 18px; background: #2563eb; color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; align-self: flex-end; }
.btn-buscar:hover { background: #1d4ed8; }
.btn-limpiar { padding: 9px 14px; background: #f1f5f9; color: #374151; border: none; border-radius: 8px; font-size: 13px; cursor: pointer; align-self: flex-end; }

/* Subir archivo */
.arch-subir-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px 24px; margin-bottom: 24px; }
.arch-subir-titulo { font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 14px; }
.arch-subir-grid { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items: flex-end; }
.arch-form-field { display: flex; flex-direction: column; gap: 5px; }
.arch-form-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
.arch-form-input, .arch-form-select { padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; background: white; }
.arch-form-input:focus, .arch-form-select:focus { border-color: #2563eb; }
.btn-subir { padding: 9px 20px; background: #16a34a; color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; white-space: nowrap; align-self: flex-end; }
.btn-subir:hover { background: #15803d; }
.btn-subir:disabled { opacity: .5; cursor: not-allowed; }
.arch-msg { padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-top: 12px; display: none; }
.arch-msg.ok  { background: #dcfce7; color: #16a34a; }
.arch-msg.err { background: #fee2e2; color: #dc2626; }

/* Lista */
.arch-tabla-wrap { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
thead th { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; }
tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .1s; }
tbody tr:hover { background: #f8fafc; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 11px 14px; font-size: 13px; }
.cat-badge { font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 99px; white-space: nowrap; }
.cat-factura           { background: #dbeafe; color: #1d4ed8; }
.cat-comprobante_de_pago { background: #dcfce7; color: #15803d; }
.cat-croquis           { background: #fef3c7; color: #b45309; }
.btn-ver { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; cursor: pointer; color: #2563eb; white-space: nowrap; }
.btn-ver:hover { background: #eff6ff; }
.btn-del { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: none; background: #fee2e2; color: #dc2626; cursor: pointer; white-space: nowrap; }
.btn-del:hover { background: #fecaca; }
.empty-msg { text-align: center; padding: 48px; color: #94a3b8; font-size: 14px; }
.loading-msg { text-align: center; padding: 48px; color: #94a3b8; font-size: 14px; }

@media(max-width:768px){
  .arch-wrap { padding: 12px; }
  .arch-search-bar { flex-direction: column; align-items: stretch; }
  .btn-buscar, .btn-limpiar { width: 100%; text-align: center; }
  .arch-subir-grid { grid-template-columns: 1fr; }
  .btn-subir { width: 100%; text-align: center; }
  thead th:nth-child(4),
  thead th:nth-child(5),
  tbody td:nth-child(4),
  tbody td:nth-child(5) { display: none; }
  tbody td { padding: 9px 10px; font-size: 12px; }
  thead th { padding: 8px 10px; font-size: 10px; }
}
</style>

<div class="arch-wrap">
  <div class="page-title">&#128193; Archivos de &Oacute;rdenes</div>
  <div class="page-sub" id="arch-sub">Busca por folio o cliente para ver los archivos</div>

  <!-- Buscador -->
  <div class="arch-search-bar">
    <div class="arch-field">
      <label>Folio de orden</label>
      <input type="text" id="arch-folio" placeholder="Ej. R-001" oninput="this.value=this.value.toUpperCase()">
    </div>
    <div class="arch-field">
      <label>Cliente</label>
      <input type="text" id="arch-cliente" placeholder="Nombre del cliente">
    </div>
    <button class="btn-buscar" onclick="archBuscar()">&#128269; Buscar</button>
    <button class="btn-limpiar" onclick="archLimpiar()">&#10005; Limpiar</button>
  </div>

  <!-- Subir archivo -->
  <?php if ($puedeSubir): ?>
  <div class="arch-subir-card">
    <div class="arch-subir-titulo">&#128229; Subir Archivo</div>
    <div class="arch-subir-grid">
      <div class="arch-form-field">
        <label class="arch-form-label">Folio de orden <span style="color:#ef4444">*</span></label>
        <input type="text" id="sub-folio" class="arch-form-input" placeholder="R-001" oninput="this.value=this.value.toUpperCase()">
      </div>
      <div class="arch-form-field">
        <label class="arch-form-label">Categoría <span style="color:#ef4444">*</span></label>
        <select id="sub-categoria" class="arch-form-select">
          <option value="">— Selecciona —</option>
          <option value="factura">Factura</option>
          <option value="comprobante_de_pago">Comprobante de pago</option>
          <option value="croquis">Croquis</option>
        </select>
      </div>
      <div class="arch-form-field">
        <label class="arch-form-label">Archivo <span style="color:#ef4444">*</span></label>
        <input type="file" id="sub-archivo" class="arch-form-input" accept=".jpg,.jpeg,.png,.pdf">
      </div>
      <button class="btn-subir" id="btn-subir" onclick="archSubir()">&#8679; Subir</button>
    </div>
    <div class="arch-msg" id="arch-msg"></div>
  </div>
  <?php endif; ?>

  <!-- Tabla -->
  <div class="arch-tabla-wrap">
    <table>
      <thead><tr>
        <th>Folio</th>
        <th>Cliente</th>
        <th>Categoría</th>
        <th>Archivo</th>
        <th>Subido por</th>
        <th>Fecha</th>
        <th></th>
      </tr></thead>
      <tbody id="arch-tbody">
        <tr><td colspan="7" class="empty-msg">Usa el buscador para ver archivos</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
var ModArchivos = (function() {

var API      = '../api/archivos_ordenes.php';
var ES_ADMIN = <?= $esAdmin ? 'true' : 'false' ?>;
var _data    = [];

var CATS = {
  'factura':             'Factura',
  'comprobante_de_pago': 'Comprobante de pago',
  'croquis':             'Croquis',
};

function fmtFecha(f) {
  if (!f) return '—';
  var d = new Date(f.includes('T') ? f : f.replace(' ', 'T'));
  return d.toLocaleDateString('es-MX', {day:'2-digit', month:'short', year:'numeric'}) + ' ' +
         d.toLocaleTimeString('es-MX', {hour:'2-digit', minute:'2-digit'});
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function mostrarMsg(txt, tipo) {
  var el = document.getElementById('arch-msg');
  el.textContent = txt;
  el.className   = 'arch-msg ' + tipo;
  el.style.display = 'block';
  if (tipo === 'ok') setTimeout(function() { el.style.display = 'none'; }, 4000);
}

window.archBuscar = function() {
  var folio   = (document.getElementById('arch-folio').value   || '').trim();
  var cliente = (document.getElementById('arch-cliente').value || '').trim();
  if (!folio && !cliente) { alert('Escribe un folio o cliente para buscar'); return; }

  document.getElementById('arch-tbody').innerHTML = '<tr><td colspan="7" class="loading-msg">Buscando&#8230;</td></tr>';
  document.getElementById('arch-sub').textContent = 'Buscando...';

  var url = API + '?accion=listar';
  if (folio)   url += '&folio='   + encodeURIComponent(folio);
  if (cliente) url += '&cliente=' + encodeURIComponent(cliente);

  fetch(url)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      _data = Array.isArray(data) ? data : [];
      archRender();
      document.getElementById('arch-sub').textContent = _data.length + ' archivo(s) encontrado(s)';
    })
    .catch(function() {
      document.getElementById('arch-tbody').innerHTML = '<tr><td colspan="7" class="empty-msg" style="color:#dc2626">Error al buscar</td></tr>';
    });
};

window.archLimpiar = function() {
  document.getElementById('arch-folio').value   = '';
  document.getElementById('arch-cliente').value = '';
  _data = [];
  document.getElementById('arch-tbody').innerHTML = '<tr><td colspan="7" class="empty-msg">Usa el buscador para ver archivos</td></tr>';
  document.getElementById('arch-sub').textContent = 'Busca por folio o cliente para ver los archivos';
};

function archRender() {
  if (!_data.length) {
    document.getElementById('arch-tbody').innerHTML = '<tr><td colspan="7" class="empty-msg">Sin archivos para esta búsqueda</td></tr>';
    return;
  }
  document.getElementById('arch-tbody').innerHTML = _data.map(function(a) {
    var catLabel = CATS[a.categoria] || a.categoria;
    var catClass = 'cat-' + a.categoria;
    var ext      = (a.nombre_servidor.split('.').pop() || '').toLowerCase();
    var icono    = ext === 'pdf' ? '&#128196;' : '&#128444;&#65039;';
    var btnBorrar = ES_ADMIN
      ? '<button class="btn-del" onclick="archBorrar(' + a.id + ',\'' + escHtml(a.nombre_original).replace(/'/g,"\\'") + '\')">&#128465; Borrar</button>'
      : '';
    return '<tr>'
      + '<td style="font-weight:700;color:#2563eb">' + escHtml(a.folio) + '</td>'
      + '<td>' + escHtml(a.cliente_nombre || '—') + '</td>'
      + '<td><span class="cat-badge ' + catClass + '">' + catLabel + '</span></td>'
      + '<td style="font-size:12px;color:#64748b">' + icono + ' ' + escHtml(a.nombre_original) + '</td>'
      + '<td style="font-size:12px;color:#64748b">' + escHtml(a.subido_por) + '</td>'
      + '<td style="font-size:12px;color:#64748b">' + fmtFecha(a.created_at) + '</td>'
      + '<td style="display:flex;gap:6px;align-items:center">'
      +   '<button class="btn-ver" onclick="archVer(' + a.id + ')">&#128065; Ver</button>'
      +   btnBorrar
      + '</td>'
      + '</tr>';
  }).join('');
}

window.archVer = function(id) {
  window.open(API + '?accion=descargar&id=' + id, '_blank');
};

window.archBorrar = function(id, nombre) {
  if (!confirm('¿Borrar el archivo "' + nombre + '"?\nEsta acción no se puede deshacer.')) return;
  fetch(API + '?accion=borrar', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: id })
  })
  .then(function(r) { return r.json(); })
  .then(function(d) {
    if (d.ok) {
      _data = _data.filter(function(a) { return a.id !== id; });
      archRender();
      document.getElementById('arch-sub').textContent = _data.length + ' archivo(s) encontrado(s)';
    } else {
      alert('Error: ' + (d.error || 'desconocido'));
    }
  })
  .catch(function() { alert('Error de conexión'); });
};

window.archSubir = function() {
  var folio     = (document.getElementById('sub-folio').value     || '').trim().toUpperCase();
  var categoria = (document.getElementById('sub-categoria').value || '').trim();
  var fileInput = document.getElementById('sub-archivo');
  var archivo   = fileInput.files[0];

  if (!folio)     { mostrarMsg('El folio es obligatorio', 'err'); return; }
  if (!categoria) { mostrarMsg('Selecciona una categoría', 'err'); return; }
  if (!archivo)   { mostrarMsg('Selecciona un archivo', 'err'); return; }

  var btn = document.getElementById('btn-subir');
  btn.disabled = true; btn.textContent = 'Subiendo...';

  var fd = new FormData();
  fd.append('folio',     folio);
  fd.append('categoria', categoria);
  fd.append('archivo',   archivo);

  fetch(API + '?accion=subir', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.ok) {
        mostrarMsg('✅ Archivo subido: ' + d.nombre_servidor, 'ok');
        document.getElementById('sub-folio').value    = '';
        document.getElementById('sub-categoria').value = '';
        fileInput.value = '';
        // Si hay búsqueda activa, recargar
        var folioQ   = document.getElementById('arch-folio').value.trim();
        var clienteQ = document.getElementById('arch-cliente').value.trim();
        if (folioQ || clienteQ) archBuscar();
      } else {
        mostrarMsg('Error: ' + (d.error || 'desconocido'), 'err');
      }
    })
    .catch(function() { mostrarMsg('Error de conexión', 'err'); })
    .finally(function() { btn.disabled = false; btn.textContent = '⬆ Subir'; });
};

// Si viene con folio desde otra pantalla
var _params = new URLSearchParams(location.search);
var _folioInit = _params.get('folio');
if (_folioInit) {
  document.getElementById('arch-folio').value = _folioInit.toUpperCase();
  archBuscar();
}

return { init: function() {} };
})();
</script>