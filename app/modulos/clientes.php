<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user    = requirePermiso('ver_ordenes');
$esAdmin           = $user['rol'] === 'dir_admin';
$puedeVerPass      = in_array($user['rol'], ['dir_admin', 'comercial', 'administracion']);
$puedeGenerar      = in_array($user['rol'], ['dir_admin', 'comercial', 'administracion']);
$puedeEditarNombre = in_array($user['rol'], ['dir_admin', 'administracion']);
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=clientes'); exit;
}
?>
<style>
.cli-wrap { padding: 24px; }
.page-title { font-size: 18px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #9ca3af; margin-bottom: 16px; }
.cli-toolbar { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.cli-search { flex: 1; min-width: 200px; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
.cli-search:focus { border-color: #2563eb; }
.btn-nuevo { background: #2563eb; color: white; border: none; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-nuevo:hover { background: #1d4ed8; }
.cli-table { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
thead th { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; }
tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .1s; cursor: pointer; }
tbody tr:hover { background: #eff6ff; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 11px 14px; font-size: 13px; }
.cli-nombre { font-weight: 600; color: #1e293b; }
.loading-msg { text-align: center; padding: 48px; color: #9ca3af; font-size: 14px; cursor: default; }
.badge-local  { background: #dbeafe; color: #1d4ed8; font-size:11px; padding:2px 8px; border-radius:99px; font-weight:600; }
.badge-foraneo{ background: #fef3c7; color: #92400e; font-size:11px; padding:2px 8px; border-radius:99px; font-weight:600; }

/* ── Panel lateral ── */
.cli-panel-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:1000; }
.cli-panel-bg.open { display:block; }
.cli-panel {
  position:fixed; top:0; right:-440px; width:420px; height:100vh;
  background:#fff; box-shadow:-4px 0 30px rgba(0,0,0,.12);
  transition:right .3s ease; z-index:1001;
  display:flex; flex-direction:column; overflow:hidden;
}
.cli-panel.open { right:0; }
.cli-panel-head {
  background:#0f172a; color:#fff; padding:20px 24px;
  display:flex; justify-content:space-between; align-items:flex-start; flex-shrink:0;
}
.cli-panel-head h3 { font-size:16px; font-weight:700; margin-bottom:3px; line-height:1.3; }
.cli-panel-head p  { font-size:12px; color:#94a3b8; }
.cli-panel-close { background:none; border:none; color:#94a3b8; font-size:20px; cursor:pointer; padding:0; line-height:1; }
.cli-panel-close:hover { color:#fff; }
.cli-panel-body { padding:24px; overflow-y:auto; flex:1; }
.cli-info-row {
  display:flex; flex-direction:column; gap:3px;
  padding:14px 0; border-bottom:1px solid #f1f5f9;
}
.cli-info-row:last-child { border-bottom:none; }
.cli-info-label { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#94a3b8; }
.cli-info-valor { font-size:14px; color:#1e293b; font-weight:500; }
.cli-info-valor.mono { font-family:monospace; font-size:13px; letter-spacing:.5px; }
.cli-info-valor.muted { color:#64748b; font-weight:400; }

/* modal nuevo cliente */
.cli-modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:1100; align-items:center; justify-content:center; }
.cli-modal-bg.open { display:flex; }
.cli-modal { background:#fff; border-radius:16px; width:100%; max-width:480px; margin:16px; box-shadow:0 20px 60px rgba(0,0,0,.2); overflow:hidden; }
.cli-modal-head { background:#0f172a; color:#fff; padding:20px 24px; display:flex; justify-content:space-between; align-items:center; }
.cli-modal-head h3 { font-size:16px; font-weight:700; }
.cli-modal-body { padding:24px; }
.cli-form-row { margin-bottom:16px; }
.cli-form-label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b; margin-bottom:6px; }
.cli-form-input, .cli-form-select { width:100%; padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:13px; outline:none; box-sizing:border-box; background:#fff; }
.cli-form-input:focus, .cli-form-select:focus { border-color:#2563eb; }
.cli-modal-foot { display:flex; justify-content:flex-end; gap:10px; padding:16px 24px; border-top:1px solid #f1f5f9; }
.cli-btn-cancel { background:none; border:1px solid #e2e8f0; padding:9px 18px; border-radius:8px; font-size:13px; cursor:pointer; color:#64748b; }
.cli-btn-cancel:hover { border-color:#94a3b8; }
.cli-btn-save { background:#2563eb; color:#fff; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
.cli-btn-save:hover { background:#1d4ed8; }
.cli-btn-save:disabled { opacity:.6; cursor:not-allowed; }

/* datos fiscales panel */
.cli-fiscal-badge { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700; padding:2px 7px; border-radius:99px; margin-left:6px; vertical-align:middle; }
.cli-fiscal-badge.ok  { background:#dcfce7; color:#166534; }
.cli-fiscal-badge.no  { background:#f1f5f9; color:#94a3b8; }
.cli-fiscal-drop {
  border:2px dashed #cbd5e1; border-radius:8px; padding:14px 16px;
  display:flex; align-items:center; gap:12px; cursor:pointer;
  background:#f8fafc; transition:all .15s; margin-top:10px;
}
.cli-fiscal-drop:hover, .cli-fiscal-drop.drag { border-color:#2563eb; background:#eff6ff; }
.cli-fiscal-drop input[type=file] { display:none; }
.cli-fiscal-drop-icon { width:32px; height:32px; background:#e0e7ff; border-radius:7px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.cli-fiscal-loading { display:none; align-items:center; gap:8px; font-size:12px; color:#2563eb; padding:8px 0; }
.cli-fiscal-loading.vis { display:flex; }
.cli-fiscal-spin { width:14px; height:14px; border:2px solid #bfdbfe; border-top-color:#2563eb; border-radius:50%; animation:cliFisSpin .7s linear infinite; flex-shrink:0; }
@keyframes cliFisSpin { to { transform:rotate(360deg); } }
.cli-fiscal-error { font-size:12px; color:#dc2626; background:#fee2e2; border-radius:6px; padding:7px 10px; display:none; margin-top:8px; }
.cli-fiscal-error.vis { display:block; }
.cli-fiscal-preview { background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:12px 14px; display:none; margin-top:10px; }
.cli-fiscal-preview.vis { display:block; }
.cli-fiscal-preview h5 { margin:0 0 10px; font-size:11px; font-weight:700; color:#166534; text-transform:uppercase; letter-spacing:.04em; }
.cli-fiscal-field { margin-bottom:10px; }
.cli-fiscal-field label { display:block; font-size:10px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.04em; margin-bottom:3px; }
.cli-fiscal-field input, .cli-fiscal-field select { width:100%; box-sizing:border-box; padding:7px 9px; border:1px solid #e2e8f0; border-radius:6px; font-size:12px; outline:none; font-family:inherit; }
.cli-fiscal-field input:focus, .cli-fiscal-field select:focus { border-color:#2563eb; }
.cli-fiscal-actions { display:flex; gap:8px; margin-top:12px; }
.cli-fiscal-save { background:#166534; color:#fff; border:none; padding:7px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; }
.cli-fiscal-save:hover { background:#15803d; }
.cli-fiscal-cancel { background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; padding:7px 14px; border-radius:6px; font-size:12px; cursor:pointer; }

/* contraseña */
.cli-pass-row { display:flex; align-items:center; gap:8px; }
.cli-pass-val { font-family:monospace; font-size:14px; color:#1e293b; font-weight:600; letter-spacing:.8px; }
.cli-btn-sm {
  background:none; border:1px solid #e2e8f0; border-radius:6px;
  cursor:pointer; color:#64748b; font-size:12px; padding:4px 10px;
  transition:all .15s; white-space:nowrap;
}
.cli-btn-sm:hover { border-color:#2563eb; color:#2563eb; }
.cli-btn-gen { background:#2563eb; color:#fff; border-color:#2563eb; }
.cli-btn-gen:hover { background:#1d4ed8; border-color:#1d4ed8; color:#fff; }
.cli-copy-ok { font-size:11px; color:#16a34a; display:none; }
.cli-sin-acceso { font-size:13px; color:#94a3b8; font-style:italic; }

@media(max-width:768px){
  .cli-wrap { padding: 12px; }

  /* Toolbar */
  .cli-toolbar { flex-direction: column; align-items: stretch; gap: 8px; }
  .cli-search  { width: 100%; min-width: 0; }
  .btn-nuevo   { width: 100%; text-align: center; }

  /* Tabla: ocultar Teléfono, Email y Ciudad */
  thead th:nth-child(3),
  thead th:nth-child(4),
  thead th:nth-child(6),
  tbody td:nth-child(3),
  tbody td:nth-child(4),
  tbody td:nth-child(6) { display: none; }

  tbody td { padding: 10px 10px; font-size: 12px; }
  thead th  { padding: 8px 10px; font-size: 10px; }

  /* Panel lateral: full width en móvil */
  .cli-panel {
    right: -100%;
    width: 100%;
    top: 0;
  }
  .cli-panel.open { right: 0; }
}
</style>

<div class="cli-wrap">
  <div class="page-title">Clientes</div>
  <div class="page-sub" id="cli-sub">Cargando&#8230;</div>
  <div class="cli-toolbar">
    <input type="text" class="cli-search" id="cli-q" placeholder="&#128269; Buscar cliente&#8230;" oninput="cliFiltrar()">
    <button class="btn-nuevo" onclick="cliNuevo()">+ Nuevo Cliente</button>
  </div>
  <div class="cli-table">
    <table>
      <thead><tr>
        <th>Nombre / Raz&#243;n Social</th><th>Contacto</th>
        <th>Tel&#233;fono</th><th>Email</th><th>Tipo</th><th>Ciudad</th>
      </tr></thead>
      <tbody id="cli-tbody"><tr><td colspan="6" class="loading-msg">Cargando&#8230;</td></tr></tbody>
    </table>
  </div>
</div>

<!-- Panel lateral -->
<div class="cli-panel-bg" id="cliPanelBg" onclick="cliCerrarPanel()"></div>
<div class="cli-panel" id="cliPanel">
  <div class="cli-panel-head">
    <div>
      <h3 id="cliPanelNombre">—</h3>
      <p  id="cliPanelCodigo">—</p>
    </div>
    <button class="cli-panel-close" onclick="cliCerrarPanel()">&#10005;</button>
  </div>
  <div class="cli-panel-body" id="cliPanelBody"></div>
</div>

<!-- Modal nuevo cliente -->
<div class="cli-modal-bg" id="cliModalBg">
  <div class="cli-modal">
    <div class="cli-modal-head">
      <h3>Nuevo Cliente</h3>
      <button class="cli-panel-close" onclick="cliCerrarModal()">&#10005;</button>
    </div>
    <div class="cli-modal-body">
      <div class="cli-form-row">
        <label class="cli-form-label">Raz&#243;n Social / Nombre <span style="color:#ef4444">*</span></label>
        <input class="cli-form-input" id="new-razon" type="text" placeholder="Ej. CRISTALES DEL NORTE SA DE CV" oninput="this.value=this.value.toUpperCase()">
      </div>
      <div class="cli-form-row">
        <label class="cli-form-label">Contacto</label>
        <input class="cli-form-input" id="new-contacto" type="text" placeholder="NOMBRE DEL CONTACTO" oninput="this.value=this.value.toUpperCase()">
      </div>
      <div class="cli-form-row">
        <label class="cli-form-label">Tel&#233;fono</label>
        <input class="cli-form-input" id="new-telefono" type="text" placeholder="812 000 0000">
      </div>
      <div class="cli-form-row">
        <label class="cli-form-label">Tel&#233;fono Alterno WhatsApp</label>
        <input class="cli-form-input" id="new-telefono-alterno" type="text" placeholder="812 000 0000 (opcional)">
      </div>
      <div class="cli-form-row">
        <label class="cli-form-label">Email</label>
        <input class="cli-form-input" id="new-email" type="email" multiple placeholder="correo@ejemplo.com (separa varios con coma)">
      </div>
      <div class="cli-form-row">
        <label class="cli-form-label">Tipo</label>
        <select class="cli-form-select" id="new-localidad" onchange="cliToggleCiudad()">
          <option value="local">Local (Monterrey)</option>
          <option value="foraneo">For&#225;neo</option>
        </select>
      </div>
      <div class="cli-form-row" id="new-ciudad-row" style="display:none">
        <label class="cli-form-label">Ciudad <span style="color:#ef4444">*</span></label>
        <input class="cli-form-input" id="new-ciudad" type="text" placeholder="Ciudad de destino">
      </div>
    </div>
    <div class="cli-modal-foot">
      <button class="cli-btn-cancel" onclick="cliCerrarModal()">Cancelar</button>
      <button class="cli-btn-save" id="cli-btn-guardar" onclick="cliGuardarNuevo()">Guardar Cliente</button>
    </div>
  </div>
</div>

<script>
window.ModClientes = (function(){

const ES_ADMIN            = <?= $esAdmin           ? 'true' : 'false' ?>;
const PUEDE_VER_PASS      = <?= $puedeVerPass      ? 'true' : 'false' ?>;
const PUEDE_GENERAR       = <?= $puedeGenerar      ? 'true' : 'false' ?>;
const PUEDE_EDITAR_NOMBRE = <?= $puedeEditarNombre ? 'true' : 'false' ?>;
let _cliData   = [];
let _passVis   = false;

let _cliTimer = null;

function cliFiltrar() {
  clearTimeout(_cliTimer);
  _cliTimer = setTimeout(cliCargar, 300);
}
window.cliFiltrar = cliFiltrar;

async function cliCargar() {
  const q = (document.getElementById('cli-q')?.value||'').trim();
  try {
    const url = '../api/clientes.php?t=' + Date.now() + (q ? '&q=' + encodeURIComponent(q) : '');
    const res  = await fetch(url);
    const data = await res.json();
    _cliData   = Array.isArray(data) ? data : (data.clientes || []);
    renderTabla();
    document.getElementById('cli-sub').textContent = _cliData.length + ' cliente(s)' + (q ? ' encontrado(s)' : ' registrados');
  } catch(e) { document.getElementById('cli-sub').textContent = 'Error al cargar'; }
}

function renderTabla() {
  const lista = _cliData;
  if (!lista.length) {
    document.getElementById('cli-tbody').innerHTML = '<tr><td colspan="6" class="loading-msg">Sin resultados</td></tr>'; return;
  }
  document.getElementById('cli-tbody').innerHTML = lista.map(c => {
    const nombre = c.razon_social || c.nombre || '&#8212;';
    const tipo = (c.localidad||c.tipo) === 'foraneo'
      ? '<span class="badge-foraneo">For&#225;neo</span>'
      : '<span class="badge-local">Local</span>';
    const rfcBadge = c.rfc
      ? '<span class="cli-fiscal-badge ok" title="RFC registrado">&#10003; RFC</span>'
      : '<span class="cli-fiscal-badge no" title="Sin datos fiscales">RFC</span>';
    return `<tr onclick="cliAbrirPanel(${c.id})">
      <td class="cli-nombre">${nombre}${rfcBadge}</td>
      <td style="color:#64748b">${c.contacto||'&#8212;'}</td>
      <td style="color:#64748b">${c.telefono||'&#8212;'}</td>
      <td style="color:#64748b;font-size:12px">${c.email||'&#8212;'}</td>
      <td>${tipo}</td>
      <td style="color:#64748b;font-size:12px">${c.ciudad||'&#8212;'}</td>
    </tr>`;
  }).join('');
}

// Abrir panel con info del cliente
window.cliAbrirPanel = function(id) {
  const c = _cliData.find(x => x.id == id);
  if (!c) return;
  _passVis = false;

  const nombre = c.razon_social || c.nombre || '—';
  document.getElementById('cliPanelNombre').textContent = nombre;
  document.getElementById('cliPanelCodigo').textContent = c.codigo || '—';

  // Columna contraseña
  let passHTML = '';
  var _tieneTel = !!(c.telefono_alterno || c.telefono);
  var _btnWA = PUEDE_GENERAR && _tieneTel
    ? ` <button class="cli-btn-sm cli-btn-wa" id="btn-enviar-wa-${c.id}" onclick="cliEnviarAccesoWA(${c.id})">&#128241; Enviar por WA</button>`
    : (PUEDE_GENERAR && !_tieneTel ? ` <span style="font-size:11px;color:#94a3b8">Sin tel. para WA</span>` : '');
  if (!c.portal_password) {
    passHTML = `<span class="cli-sin-acceso">Sin acceso al portal</span>`;
    if (PUEDE_GENERAR) {
      passHTML += ` <button class="cli-btn-sm cli-btn-gen" onclick="cliGenerarPass(${c.id})">&#128273; Generar acceso</button>`;
      passHTML += _btnWA;
    }
  } else if (PUEDE_VER_PASS) {
    passHTML = `
      <div class="cli-pass-row">
        <span class="cli-pass-val" id="panel-pass-val">••••••••</span>
        <button class="cli-btn-sm" id="panel-pass-eye" onclick="cliTogglePass('${c.portal_password}')">&#128065; Ver</button>
        <button class="cli-btn-sm" onclick="cliCopiarPass('${c.portal_password}')">&#128203; Copiar</button>
        ${_btnWA}
        <span class="cli-copy-ok" id="panel-copy-ok">&#10003; Copiado</span>
      </div>
      ${ES_ADMIN ? `<div style="margin-top:8px"><button class="cli-btn-sm" onclick="cliGenerarPass(${c.id})">&#128260; Regenerar contraseña</button></div>` : ''}
    `;
  }

  document.getElementById('cliPanelBody').innerHTML = `
    <div class="cli-info-row">
      <div class="cli-info-label">Nombre / Razón Social</div>
      <div class="cli-info-valor" id="panel-nombre-val">${nombre}</div>
      ${PUEDE_EDITAR_NOMBRE ? `<button class="cli-btn-sm" style="margin-top:4px" onclick="cliEditarNombre(${c.id})">&#9998; Editar nombre</button>` : ''}
    </div>
    <div class="cli-info-row">
      <div class="cli-info-label">Contacto</div>
      <div class="cli-info-valor muted" id="panel-contacto-val">${c.contacto || '—'}</div>
      ${PUEDE_EDITAR_NOMBRE ? `<button class="cli-btn-sm" style="margin-top:4px" onclick="cliEditarContacto(${c.id})">&#9998; Editar contacto</button>` : ''}
    </div>
    <div class="cli-info-row">
      <div class="cli-info-label" style="display:flex;justify-content:space-between;align-items:center">
        Correo
        <button class="cli-btn-sm" style="font-size:10px;padding:2px 8px" onclick="cliEditarTelefono(${c.id},'email')">Editar</button>
      </div>
      <div class="cli-info-valor muted" id="panel-email-val">${c.email || '—'}</div>
    </div>
    <div class="cli-info-row">
      <div class="cli-info-label" style="display:flex;justify-content:space-between;align-items:center">
        Teléfono
        <button class="cli-btn-sm" style="font-size:10px;padding:2px 8px" onclick="cliEditarTelefono(${c.id},'telefono')">Editar</button>
      </div>
      <div class="cli-info-valor muted" id="panel-telefono-val">${c.telefono || '—'}</div>
    </div>
    <div class="cli-info-row">
      <div class="cli-info-label" style="display:flex;justify-content:space-between;align-items:center">
        Tel. Alterno WhatsApp
        <button class="cli-btn-sm" style="font-size:10px;padding:2px 8px" onclick="cliEditarTelefono(${c.id},'telefono_alterno')">Editar</button>
      </div>
      <div class="cli-info-valor muted" id="panel-telefono-alterno-val">${c.telefono_alterno || '—'}</div>
    </div>
    <div class="cli-info-row">
      <div class="cli-info-label">Usuario Portal</div>
      <div class="cli-info-valor mono">${c.codigo || '—'}</div>
    </div>
    <div class="cli-info-row">
      <div class="cli-info-label">&#128176; Saldo a Favor</div>
      <div class="cli-info-valor" id="panel-saldo-favor"><span style="color:#94a3b8">Cargando...</span></div>
    </div>
    <div class="cli-info-row" id="panel-fiscal-section">
      ${cliFiscalSeccionHtml(c)}
    </div>
    <div class="cli-info-row">
      <div class="cli-info-label">Contraseña Portal</div>
      <div class="cli-info-valor">${passHTML}</div>
    </div>
  `;

  // Cargar saldo a favor
  fetch('../api/saldo_favor.php?accion=saldo&cliente_id=' + id)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var el = document.getElementById('panel-saldo-favor');
      if (!el) return;
      var saldo = parseFloat(d.saldo || 0);
      var fmt   = '$' + saldo.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
      if (saldo > 0) {
        el.innerHTML = '<span style="font-size:15px;font-weight:700;color:#15803d">' + fmt + '</span>'
          + ' <span style="font-size:11px;color:#16a34a;background:#dcfce7;padding:2px 8px;border-radius:99px;font-weight:700">Disponible</span>';
      } else {
        el.innerHTML = '<span style="color:#94a3b8">$0.00</span>';
      }
    })
    .catch(function() {});

  document.getElementById('cliPanelBg').classList.add('open');
  document.getElementById('cliPanel').classList.add('open');
};

window.cliCerrarPanel = function() {
  document.getElementById('cliPanelBg').classList.remove('open');
  document.getElementById('cliPanel').classList.remove('open');
};

window.cliTogglePass = function(pass) {
  _passVis = !_passVis;
  const valEl = document.getElementById('panel-pass-val');
  const eyeEl = document.getElementById('panel-pass-eye');
  if (valEl) valEl.textContent = _passVis ? pass : '••••••••';
  if (eyeEl) eyeEl.innerHTML   = _passVis ? '&#128064; Ocultar' : '&#128065; Ver';
};

window.cliCopiarPass = function(pass) {
  navigator.clipboard.writeText(pass).then(() => {
    const el = document.getElementById('panel-copy-ok');
    if (el) { el.style.display = 'inline'; setTimeout(() => el.style.display = 'none', 1500); }
  });
};

window.cliNuevo = function() {
  document.getElementById('new-razon').value              = '';
  document.getElementById('new-contacto').value           = '';
  document.getElementById('new-telefono').value           = '';
  document.getElementById('new-telefono-alterno').value   = '';
  document.getElementById('new-email').value              = '';
  document.getElementById('new-localidad').value          = 'local';
  document.getElementById('new-ciudad').value             = '';
  document.getElementById('new-ciudad-row').style.display = 'none';
  document.getElementById('cliModalBg').classList.add('open');
  setTimeout(() => document.getElementById('new-razon').focus(), 80);
};

window.cliCerrarModal = function() {
  document.getElementById('cliModalBg').classList.remove('open');
};

window.cliToggleCiudad = function() {
  const foraneo = document.getElementById('new-localidad').value === 'foraneo';
  document.getElementById('new-ciudad-row').style.display = foraneo ? '' : 'none';
};

window.cliGuardarNuevo = async function() {
  const razon = document.getElementById('new-razon').value.trim();
  if (!razon) { alert('La razón social es obligatoria'); document.getElementById('new-razon').focus(); return; }
  const localidad = document.getElementById('new-localidad').value;
  const ciudad    = document.getElementById('new-ciudad').value.trim();
  if (localidad === 'foraneo' && !ciudad) { alert('La ciudad es obligatoria para clientes foráneos'); document.getElementById('new-ciudad').focus(); return; }

  const btn = document.getElementById('cli-btn-guardar');
  btn.disabled = true; btn.textContent = 'Guardando...';
  try {
    const r = await fetch('../api/clientes.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        razon_social:     razon,
        contacto:         document.getElementById('new-contacto').value.trim(),
        telefono:         document.getElementById('new-telefono').value.trim(),
        telefono_alterno: document.getElementById('new-telefono-alterno').value.trim(),
        email:            document.getElementById('new-email').value.trim(),
        localidad, ciudad
      })
    });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error desconocido');
    cliCerrarModal();
    await cliCargar();
    cliAbrirPanel(d.id);
  } catch(e) {
    alert('Error al guardar: ' + e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Guardar Cliente';
  }
};

window.cliGenerarPass = async function(id) {
  const c = _cliData.find(x => x.id == id);
  const nombre = c ? (c.razon_social || c.nombre) : 'este cliente';
  if (c && c.portal_password) {
    if (!confirm(`¿Regenerar contraseña de ${nombre}?\nLa contraseña actual quedará inválida.`)) return;
  }
  try {
    const fd = new FormData();
    fd.append('id', id);
    const r = await fetch('../api/portal_clientes.php?accion=generar_pass', { method:'POST', body:fd });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error desconocido');
    // Actualizar en memoria y reabrir el panel con la nueva pass
    const idx = _cliData.findIndex(x => x.id == id);
    if (idx >= 0) _cliData[idx].portal_password = d.password;
    cliAbrirPanel(id);
    // Mostrar la nueva contraseña directo
    _passVis = true;
    const valEl = document.getElementById('panel-pass-val');
    const eyeEl = document.getElementById('panel-pass-eye');
    if (valEl) valEl.textContent = d.password;
    if (eyeEl) eyeEl.innerHTML   = '&#128064; Ocultar';
  } catch(e) { alert('Error: ' + e.message); }
};

window.cliEnviarAccesoWA = async function(id) {
  const c = _cliData.find(x => x.id == id);
  const nombre = c ? (c.razon_social || c.nombre) : 'este cliente';
  const accion = c && !c.portal_password ? 'Generar contraseña y enviar acceso' : 'Reenviar acceso al portal';
  if (!confirm(accion + ' por WhatsApp a ' + nombre + '?')) return;
  const btn = document.getElementById('btn-enviar-wa-' + id);
  if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
  try {
    const fd = new FormData();
    fd.append('id', id);
    const r = await fetch('../api/portal_clientes.php?accion=enviar_acceso_wa', { method: 'POST', body: fd });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error desconocido');
    // Actualizar password en memoria si se generó una nueva
    const idx = _cliData.findIndex(x => x.id == id);
    if (idx >= 0 && d.password) _cliData[idx].portal_password = d.password;
    if (btn) { btn.disabled = false; btn.textContent = '✓ Enviado'; }
    setTimeout(function() { if (btn) { btn.textContent = '📱 Enviar por WA'; } }, 3000);
  } catch(e) {
    alert('Error: ' + e.message);
    if (btn) { btn.disabled = false; btn.textContent = '📱 Enviar por WA'; }
  }
};

window.cliEditarNombre = function(id) {
  const c = _cliData.find(x => x.id == id);
  if (!c) return;
  const actual = c.razon_social || c.nombre || '';
  const valEl  = document.getElementById('panel-nombre-val');
  if (!valEl) return;

  valEl.innerHTML = `
    <input id="edit-nombre-input" type="text" class="cli-form-input" value="${actual.replace(/"/g,'&quot;')}" style="width:100%;margin-bottom:6px" oninput="this.value=this.value.toUpperCase()">
    <div style="display:flex;gap:6px">
      <button class="cli-btn-sm cli-btn-gen" onclick="cliGuardarNombre(${id})">&#10003; Guardar</button>
      <button class="cli-btn-sm" onclick="cliAbrirPanel(${id})">Cancelar</button>
    </div>
  `;
  document.getElementById('edit-nombre-input').focus();
  document.getElementById('edit-nombre-input').select();
};

window.cliGuardarNombre = async function(id) {
  const input = document.getElementById('edit-nombre-input');
  if (!input) return;
  const nuevoNombre = input.value.trim().toUpperCase();

  if (!nuevoNombre) { alert('El nombre no puede estar vacío'); return; }

  const c = _cliData.find(x => x.id == id);
  const actual = (c?.razon_social || c?.nombre || '').toUpperCase();
  if (nuevoNombre === actual) { cliAbrirPanel(id); return; }

  try {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('nombre', nuevoNombre);
    const r = await fetch('../api/clientes.php?accion=editar_nombre', { method:'POST', body:fd });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error desconocido');

    const idx = _cliData.findIndex(x => x.id == id);
    if (idx >= 0) {
      _cliData[idx].nombre       = nuevoNombre;
      _cliData[idx].razon_social = nuevoNombre;
    }
    renderTabla();
    cliAbrirPanel(id);
  } catch(e) { alert('Error: ' + e.message); }
};

window.cliEditarContacto = function(id) {
  const c = _cliData.find(x => x.id == id);
  if (!c) return;
  const actual = c.contacto || '';
  const valEl  = document.getElementById('panel-contacto-val');
  if (!valEl) return;

  valEl.innerHTML = `
    <input id="edit-contacto-input" type="text" class="cli-form-input" value="${actual.replace(/"/g,'&quot;')}" style="width:100%;margin-bottom:6px">
    <div style="display:flex;gap:6px">
      <button class="cli-btn-sm cli-btn-gen" onclick="cliGuardarContacto(${id})">&#10003; Guardar</button>
      <button class="cli-btn-sm" onclick="cliAbrirPanel(${id})">Cancelar</button>
    </div>
  `;
  document.getElementById('edit-contacto-input').focus();
  document.getElementById('edit-contacto-input').select();
};

window.cliGuardarContacto = async function(id) {
  const input = document.getElementById('edit-contacto-input');
  if (!input) return;
  const nuevoContacto = input.value.trim().toUpperCase();

  const c = _cliData.find(x => x.id == id);
  if (nuevoContacto === (c?.contacto || '').toUpperCase()) { cliAbrirPanel(id); return; }

  try {
    const fd = new FormData();
    fd.append('id', id);
    fd.append('contacto', nuevoContacto);
    const r = await fetch('../api/clientes.php?accion=editar_contacto', { method:'POST', body:fd });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error desconocido');

    const idx = _cliData.findIndex(x => x.id == id);
    if (idx >= 0) _cliData[idx].contacto = nuevoContacto;
    renderTabla();
    cliAbrirPanel(id);
  } catch(e) { alert('Error: ' + e.message); }
};

const CAMPOS_EDITABLES = {
  telefono:          {elId: 'panel-telefono-val',          tipo: 'tel',   placeholder: '812 000 0000'},
  telefono_alterno:  {elId: 'panel-telefono-alterno-val',  tipo: 'tel',   placeholder: '812 000 0000'},
  email:             {elId: 'panel-email-val',             tipo: 'email', placeholder: 'correo@ejemplo.com (separa varios con coma)', multiple: true},
};

window.cliEditarTelefono = function(id, campo) {
  const c      = _cliData.find(x => x.id == id);
  if (!c) return;
  var conf     = CAMPOS_EDITABLES[campo];
  if (!conf) return;
  var actual   = c[campo] || '';
  var valEl    = document.getElementById(conf.elId);
  if (!valEl) return;

  valEl.innerHTML = '<input id="edit-tel-input" type="' + conf.tipo + '" ' + (conf.multiple ? 'multiple' : '') + ' class="cli-form-input" value="' + actual.replace(/"/g,'&quot;') + '" placeholder="' + conf.placeholder + '" style="width:100%;margin-bottom:6px">'
    + '<div style="display:flex;gap:6px">'
    + '<button class="cli-btn-sm cli-btn-gen" onclick="cliGuardarTelefono(' + id + ',\'' + campo + '\')">&#10003; Guardar</button>'
    + '<button class="cli-btn-sm" onclick="cliAbrirPanel(' + id + ')">Cancelar</button>'
    + '</div>';
  var inp = document.getElementById('edit-tel-input');
  if (inp) { inp.focus(); inp.select(); }
};

window.cliGuardarTelefono = async function(id, campo) {
  var input = document.getElementById('edit-tel-input');
  if (!input) return;
  var nuevoValor = input.value.trim();

  var c      = _cliData.find(x => x.id == id);
  var actual = c[campo] || '';
  if (nuevoValor === actual) { cliAbrirPanel(id); return; }

  try {
    var fd = new FormData();
    fd.append('id',    id);
    fd.append('campo', campo);
    fd.append('valor', nuevoValor);
    var r = await fetch('../api/clientes.php?accion=editar_telefono', { method:'POST', body:fd });
    var d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error desconocido');

    var idx = _cliData.findIndex(x => x.id == id);
    if (idx >= 0) _cliData[idx][campo] = nuevoValor;
    cliAbrirPanel(id);
  } catch(e) { alert('Error: ' + e.message); }
};

// ── Datos Fiscales ────────────────────────────────────────────────────────────

const REGIMENES = {
  '601':'601 – General de Ley Personas Morales',
  '603':'603 – Personas Morales sin Fines de Lucro',
  '606':'606 – Arrendamiento',
  '612':'612 – Pers. Físicas Actividades Empresariales',
  '616':'616 – Sin obligaciones fiscales',
  '621':'621 – Incorporación Fiscal',
  '625':'625 – Plataformas Tecnológicas',
  '626':'626 – Régimen Simplificado de Confianza (Resico)',
};

function cliFiscalSeccionHtml(c) {
  const tieneRfc = !!(c.rfc);
  const badge = tieneRfc
    ? '<span class="cli-fiscal-badge ok">&#10003; RFC registrado</span>'
    : '<span class="cli-fiscal-badge no">Sin datos fiscales</span>';

  let datosHtml = '';
  if (tieneRfc) {
    datosHtml = `
      <div style="margin-top:10px;display:grid;grid-template-columns:1fr 1fr;gap:8px">
        <div>
          <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em">RFC</div>
          <div style="font-family:monospace;font-weight:700;font-size:13px;color:#1e293b">${c.rfc}</div>
        </div>
        <div>
          <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em">CP Fiscal</div>
          <div style="font-size:13px;color:#1e293b">${c.cp_fiscal || '—'}</div>
        </div>
        <div style="grid-column:1/-1">
          <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em">Régimen Fiscal</div>
          <div style="font-size:12px;color:#1e293b">${c.regimen_fiscal ? (REGIMENES[c.regimen_fiscal] || c.regimen_fiscal) : '—'}</div>
        </div>
      </div>`;
  } else {
    datosHtml = '<div style="font-size:12px;color:#94a3b8;margin-top:6px">Sin datos fiscales. Sube la Constancia de Situación Fiscal para registrarlos.</div>';
  }

  const btnLabel = tieneRfc ? '&#128196; Actualizar desde CSF' : '&#128196; Subir Constancia SAT';

  return `
    <div class="cli-info-label" style="display:flex;justify-content:space-between;align-items:center">
      <span>Datos Fiscales ${badge}</span>
      <button class="cli-btn-sm" onclick="cliFiscalAbrirWidget(${c.id})">${btnLabel}</button>
    </div>
    ${datosHtml}
    <div id="panel-fiscal-widget"></div>`;
}

window.cliFiscalAbrirWidget = function(id) {
  const widget = document.getElementById('panel-fiscal-widget');
  if (!widget) return;

  const regimenOptions = Object.entries(REGIMENES).map(([k,v]) =>
    `<option value="${k}">${v}</option>`
  ).join('');

  widget.innerHTML = `
    <label class="cli-fiscal-drop" id="cli-fis-drop-${id}"
      ondragover="cliFiscalDrag(event,true,'${id}')"
      ondragleave="cliFiscalDrag(event,false,'${id}')"
      ondrop="cliFiscalDrop(event,'${id}')">
      <input type="file" id="cli-fis-file-${id}" accept=".pdf,application/pdf"
        onchange="cliFiscalSubir(this.files[0],'${id}')">
      <div class="cli-fiscal-drop-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
      </div>
      <div>
        <strong style="display:block;font-size:12px;color:#1e293b">Subir Constancia de Situación Fiscal</strong>
        <span style="font-size:11px;color:#64748b">PDF del SAT — extrae RFC, CP y régimen automáticamente</span>
      </div>
    </label>
    <div class="cli-fiscal-loading" id="cli-fis-loading-${id}">
      <div class="cli-fiscal-spin"></div> Extrayendo datos del PDF&#8230;
    </div>
    <div class="cli-fiscal-error" id="cli-fis-error-${id}"></div>
    <div class="cli-fiscal-preview" id="cli-fis-preview-${id}">
      <h5>&#10003; Datos encontrados — revisa y guarda</h5>
      <div class="cli-fiscal-field">
        <label>RFC</label>
        <input type="text" id="cli-fis-rfc-${id}" maxlength="13" style="text-transform:uppercase"
          oninput="this.value=this.value.toUpperCase()">
      </div>
      <div class="cli-fiscal-field">
        <label>CP Fiscal</label>
        <input type="text" id="cli-fis-cp-${id}" maxlength="5">
      </div>
      <div class="cli-fiscal-field">
        <label>Régimen Fiscal</label>
        <select id="cli-fis-reg-${id}">
          <option value="">— Selecciona —</option>
          ${regimenOptions}
        </select>
      </div>
      <div class="cli-fiscal-actions">
        <button class="cli-fiscal-save" onclick="cliFiscalGuardar(${id})">Guardar en cliente</button>
        <button class="cli-fiscal-cancel" onclick="cliFiscalCerrarWidget()">Cancelar</button>
      </div>
    </div>`;
};

window.cliFiscalCerrarWidget = function() {
  const widget = document.getElementById('panel-fiscal-widget');
  if (widget) widget.innerHTML = '';
};

window.cliFiscalDrag = function(e, over, id) {
  e.preventDefault();
  const drop = document.getElementById('cli-fis-drop-' + id);
  if (drop) drop.classList.toggle('drag', over);
};

window.cliFiscalDrop = function(e, id) {
  e.preventDefault();
  const drop = document.getElementById('cli-fis-drop-' + id);
  if (drop) drop.classList.remove('drag');
  const file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
  if (file) cliFiscalSubir(file, id);
};

window.cliFiscalSubir = async function(file, id) {
  if (!file) return;
  if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) {
    cliFiscalError(id, 'Solo se aceptan archivos PDF'); return;
  }
  if (file.size > 5 * 1024 * 1024) {
    cliFiscalError(id, 'Archivo demasiado grande (máx 5 MB)'); return;
  }

  cliFiscalError(id, '');
  const loading = document.getElementById('cli-fis-loading-' + id);
  const preview = document.getElementById('cli-fis-preview-' + id);
  if (loading) loading.classList.add('vis');
  if (preview) preview.classList.remove('vis');

  try {
    const fd = new FormData();
    fd.append('constancia', file);
    const r   = await fetch('../api/extraer_constancia.php', { method:'POST', headers:{'X-SPA-Request':'1'}, body:fd });
    const res = await r.json();
    if (loading) loading.classList.remove('vis');

    if (!res.ok) { cliFiscalError(id, res.error || 'No se pudo leer la constancia'); return; }

    const d = res.datos;
    const rfcEl = document.getElementById('cli-fis-rfc-' + id);
    const cpEl  = document.getElementById('cli-fis-cp-'  + id);
    const regEl = document.getElementById('cli-fis-reg-' + id);
    if (rfcEl) rfcEl.value = (d.rfc || '').toUpperCase();
    if (cpEl)  cpEl.value  = d.cp  || '';
    // Régimen: tomar el primero si viene array
    if (regEl && d.regimen && d.regimen.length) regEl.value = d.regimen[0].cod || '';

    if (preview) preview.classList.add('vis');
  } catch(e) {
    if (loading) loading.classList.remove('vis');
    cliFiscalError(id, 'Error de conexión al procesar la constancia');
  }
};

function cliFiscalError(id, msg) {
  const el = document.getElementById('cli-fis-error-' + id);
  if (!el) return;
  el.textContent = msg;
  el.className = 'cli-fiscal-error' + (msg ? ' vis' : '');
}

window.cliFiscalGuardar = async function(id) {
  const rfc = (document.getElementById('cli-fis-rfc-' + id)?.value || '').trim().toUpperCase();
  const cp  = (document.getElementById('cli-fis-cp-'  + id)?.value || '').trim();
  const reg = (document.getElementById('cli-fis-reg-' + id)?.value || '').trim();

  if (!rfc) { cliFiscalError(id, 'El RFC es obligatorio'); return; }

  try {
    const r = await fetch('../api/clientes.php?accion=guardar_fiscal', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-SPA-Request': '1' },
      body: JSON.stringify({ id, rfc, cp_fiscal: cp, regimen_fiscal: reg })
    });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error desconocido');

    // Actualizar en memoria y refrescar panel
    const idx = _cliData.findIndex(x => x.id == id);
    if (idx >= 0) {
      _cliData[idx].rfc            = rfc;
      _cliData[idx].cp_fiscal      = cp;
      _cliData[idx].regimen_fiscal = reg;
    }
    renderTabla();
    cliAbrirPanel(id);
  } catch(e) { cliFiscalError(id, 'Error al guardar: ' + e.message); }
};

cliCargar();
return { init: cliCargar };
})();
ModClientes.init();
</script>