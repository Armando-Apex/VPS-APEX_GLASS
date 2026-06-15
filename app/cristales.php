<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user = requirePermiso('ver_dashboard');
$rol  = $user['rol'];
if (!in_array($rol, ['dir_admin', 'dueno'])) {
    http_response_code(403);
    include __DIR__ . '/403.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APEX GLASS — Catálogo de Cristales</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@400;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

/* ── Header ── */
.header {
  background: #1a1a2e; color: white;
  padding: 16px 24px;
  display: flex; align-items: center; justify-content: space-between;
}
.header h1 { font-size: 20px; font-weight: 800; letter-spacing: 1px; font-family: 'Syncopate', sans-serif; }
.header .right { display: flex; gap: 16px; align-items: center; }
.header a { color: #94a3b8; font-size: 13px; text-decoration: none; }

/* ── Layout ── */
.main { padding: 24px; max-width: 1100px; margin: 0 auto; }

.top-bar {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 20px;
}
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; }

/* ── Botones ── */
.btn {
  padding: 9px 18px; border-radius: 8px; font-size: 13px;
  font-weight: 700; cursor: pointer; border: none; transition: opacity .15s;
}
.btn:hover { opacity: .85; }
.btn-primary { background: #2563eb; color: white; }
.btn-success { background: #16a34a; color: white; }
.btn-danger  { background: #dc2626; color: white; }
.btn-ghost   { background: #f1f5f9; color: #374151; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

/* ── Tabla ── */
.table-wrap {
  background: white; border-radius: 14px;
  overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06);
  margin-bottom: 24px;
}
table { width: 100%; border-collapse: collapse; }
thead { background: #f8fafc; }
th {
  padding: 12px 16px; text-align: left;
  font-size: 11px; font-weight: 700; color: #64748b;
  text-transform: uppercase; letter-spacing: .5px;
}
td { padding: 14px 16px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #374151; }
tr:hover td { background: #f8fafc; }

.badge-activo   { background: #dcfce7; color: #16a34a; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.badge-inactivo { background: #f1f5f9; color: #94a3b8; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }

.precio { font-size: 16px; font-weight: 800; color: #1e293b; }

/* ── Modal ── */
.modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.45); z-index: 1000;
  align-items: center; justify-content: center;
}
.modal-bg.open { display: flex; }
.modal {
  background: white; border-radius: 16px;
  padding: 28px; width: 100%; max-width: 500px;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
  max-height: 90vh; overflow-y: auto;
}
.modal h2 { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 20px; }

.field { margin-bottom: 16px; }
.field label { display: block; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; }
.field input, .field textarea, .field select {
  width: 100%; padding: 10px 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 14px; color: #1e293b;
  transition: border-color .15s;
}
.field input:focus, .field textarea:focus {
  outline: none; border-color: #2563eb;
}
.field .hint { font-size: 11px; color: #94a3b8; margin-top: 4px; }

.modal-footer { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

/* ── Historial ── */
.historial-wrap {
  background: white; border-radius: 14px;
  padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,.06);
  display: none;
}
.historial-wrap.open { display: block; }
.historial-title {
  font-size: 15px; font-weight: 700; color: #1e293b;
  margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;
}
.hist-item {
  display: flex; align-items: center; gap: 16px;
  padding: 12px 0; border-bottom: 1px solid #f1f5f9;
}
.hist-item:last-child { border: none; }
.hist-dot {
  width: 10px; height: 10px; border-radius: 50%;
  background: #2563eb; flex-shrink: 0;
}
.hist-dot.inicial { background: #16a34a; }
.hist-info { flex: 1; }
.hist-precios { font-size: 14px; font-weight: 700; color: #1e293b; }
.hist-precios .anterior { color: #dc2626; text-decoration: line-through; margin-right: 8px; }
.hist-precios .nuevo    { color: #16a34a; }
.hist-meta  { font-size: 12px; color: #94a3b8; margin-top: 2px; }
.hist-motivo { font-size: 12px; color: #2563eb; margin-top: 2px; font-style: italic; }

.empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }

/* ── Filtro ── */
.filtros { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; }
.filtros input {
  padding: 9px 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 14px; width: 280px;
}
.filtros input:focus { outline: none; border-color: #2563eb; }
.toggle-inactivos { font-size: 13px; color: #64748b; cursor: pointer; display: flex; align-items: center; gap: 6px; }
</style>
</head>
<body>

<div class="header">
  <h1>APEX GLASS — Catálogo de Cristales</h1>
  <div class="right">
    <a href="dashboard.php">← Dashboard</a>
    <a href="../api/logout.php?redirect=login.php">Salir</a>
  </div>
</div>

<div class="main">

  <div class="top-bar">
    <div class="section-title">💎 Catálogo de Cristales</div>
    <button class="btn btn-primary" onclick="abrirModalNuevo()">+ Nuevo cristal</button>
  </div>

  <!-- Filtros -->
  <div class="filtros">
    <input type="text" id="busqueda" placeholder="🔍 Buscar cristal..." oninput="filtrar()">
    <label class="toggle-inactivos">
      <input type="checkbox" id="verInactivos" onchange="cargar()">
      Ver inactivos
    </label>
  </div>

  <!-- Tabla -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nombre del cristal</th>
          <th>Nombre etiqueta</th>
          <th>Precio / m²</th>
          <th>Estatus</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tablaCristales">
        <tr><td colspan="6" class="empty">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

  <!-- Historial de precios -->
  <div class="historial-wrap" id="historialWrap">
    <div class="historial-title">
      <span id="historialTitulo">Historial de precios</span>
      <button class="btn btn-ghost btn-sm" onclick="cerrarHistorial()">✕ Cerrar</button>
    </div>
    <div id="historialContenido"></div>
  </div>

</div>

<!-- Modal nuevo / editar -->
<div class="modal-bg" id="modalBg">
  <div class="modal">
    <h2 id="modalTitulo">Nuevo cristal</h2>
    <input type="hidden" id="editId">

    <div class="field">
      <label>Nombre del cristal</label>
      <input type="text" id="fNombre" placeholder="Ej: Claro 9mm - Precio especial">
      <div class="hint">Nombre completo que aparece en cotizaciones</div>
    </div>

    <div class="field">
      <label>Nombre para etiqueta</label>
      <input type="text" id="fEtiqueta" placeholder="Ej: Claro de 9mm">
      <div class="hint">Nombre corto que aparece en la etiqueta de producción</div>
    </div>

    <div class="field">
      <label>Precio por m²</label>
      <input type="number" id="fPrecio" placeholder="0.00" step="0.01" min="0">
    </div>

    <div class="field" id="campoMotivo" style="display:none">
      <label>Motivo del cambio de precio</label>
      <input type="text" id="fMotivo" placeholder="Ej: Ajuste por inflación oct 2026">
      <div class="hint">Opcional — queda registrado en el historial</div>
    </div>

    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="cerrarModal()">Cancelar</button>
      <button class="btn btn-success" onclick="guardar()">Guardar</button>
    </div>
  </div>
</div>

<script>
const API = '../api/cristales.php';
let cristalesData = [];
let precioOriginal = 0;

// ── Cargar lista ──────────────────────────────────────────────────────────────
async function cargar() {
  const verInactivos = document.getElementById('verInactivos').checked;
  const url = API + (verInactivos ? '?activos=0' : '?activos=1');
  try {
    const res  = await fetch(url);
    cristalesData = await res.json();
    filtrar();
  } catch(e) {
    document.getElementById('tablaCristales').innerHTML =
      '<tr><td colspan="6" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function filtrar() {
  const q = document.getElementById('busqueda').value.toLowerCase();
  const lista = q
    ? cristalesData.filter(c => c.nombre.toLowerCase().includes(q) || c.nombre_etiqueta.toLowerCase().includes(q))
    : cristalesData;
  renderTabla(lista);
}

function renderTabla(lista) {
  if (!lista.length) {
    document.getElementById('tablaCristales').innerHTML =
      '<tr><td colspan="6" class="empty">No hay cristales registrados</td></tr>';
    return;
  }
  document.getElementById('tablaCristales').innerHTML = lista.map((c, i) => `
    <tr>
      <td style="color:#94a3b8;font-size:13px">${i + 1}</td>
      <td style="font-weight:600">${c.nombre}</td>
      <td style="color:#64748b">${c.nombre_etiqueta}</td>
      <td><span class="precio">$${parseFloat(c.precio_m2).toLocaleString('es-MX', {minimumFractionDigits:2})}</span></td>
      <td>
        <span class="${c.activo == 1 ? 'badge-activo' : 'badge-inactivo'}">
          ${c.activo == 1 ? 'Activo' : 'Inactivo'}
        </span>
      </td>
      <td>
        <div style="display:flex;gap:8px">
          <button class="btn btn-ghost btn-sm" onclick="verHistorial(${c.id}, '${c.nombre.replace(/'/g,"\\'")}')">📋 Historial</button>
          <button class="btn btn-primary btn-sm" onclick="abrirModalEditar(${c.id})">✏️ Editar</button>
          <button class="btn btn-sm" style="background:${c.activo==1?'#fef2f2':'#f0fdf4'};color:${c.activo==1?'#dc2626':'#16a34a'}"
            onclick="toggleActivo(${c.id}, ${c.activo})">
            ${c.activo == 1 ? '🚫 Desactivar' : '✅ Activar'}
          </button>
        </div>
      </td>
    </tr>
  `).join('');
}

// ── Modal nuevo ───────────────────────────────────────────────────────────────
function abrirModalNuevo() {
  document.getElementById('modalTitulo').textContent = 'Nuevo cristal';
  document.getElementById('editId').value    = '';
  document.getElementById('fNombre').value   = '';
  document.getElementById('fEtiqueta').value = '';
  document.getElementById('fPrecio').value   = '';
  document.getElementById('fMotivo').value   = '';
  document.getElementById('campoMotivo').style.display = 'none';
  precioOriginal = 0;
  document.getElementById('modalBg').classList.add('open');
}

// ── Modal editar ──────────────────────────────────────────────────────────────
async function abrirModalEditar(id) {
  try {
    const res  = await fetch(API + '?id=' + id);
    const data = await res.json();
    document.getElementById('modalTitulo').textContent  = 'Editar cristal';
    document.getElementById('editId').value             = data.id;
    document.getElementById('fNombre').value            = data.nombre;
    document.getElementById('fEtiqueta').value          = data.nombre_etiqueta;
    document.getElementById('fPrecio').value            = parseFloat(data.precio_m2).toFixed(2);
    document.getElementById('fMotivo').value            = '';
    precioOriginal = parseFloat(data.precio_m2);
    // Mostrar campo motivo al cambiar precio
    document.getElementById('fPrecio').oninput = () => {
      const nuevo = parseFloat(document.getElementById('fPrecio').value) || 0;
      document.getElementById('campoMotivo').style.display = (nuevo !== precioOriginal) ? 'block' : 'none';
    };
    document.getElementById('modalBg').classList.add('open');
  } catch(e) {
    alert('Error al cargar cristal');
  }
}

function cerrarModal() {
  document.getElementById('modalBg').classList.remove('open');
}

// ── Guardar ───────────────────────────────────────────────────────────────────
async function guardar() {
  const id       = document.getElementById('editId').value;
  const nombre   = document.getElementById('fNombre').value.trim();
  const etiqueta = document.getElementById('fEtiqueta').value.trim();
  const precio   = parseFloat(document.getElementById('fPrecio').value) || 0;
  const motivo   = document.getElementById('fMotivo').value.trim();

  if (!nombre || !etiqueta || precio <= 0) {
    alert('Nombre, etiqueta y precio son obligatorios'); return;
  }

  const method  = id ? 'PUT' : 'POST';
  const payload = id
    ? { id: parseInt(id), nombre, nombre_etiqueta: etiqueta, precio_m2: precio, motivo }
    : { nombre, nombre_etiqueta: etiqueta, precio_m2: precio };

  try {
    const res  = await fetch(API, { method, headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    const data = await res.json();
    if (data.ok) {
      cerrarModal();
      cargar();
    } else {
      alert(data.error || 'Error al guardar');
    }
  } catch(e) {
    alert('Error de conexión');
  }
}

// ── Activar / Desactivar ──────────────────────────────────────────────────────
async function toggleActivo(id, actual) {
  const accion = actual == 1 ? 'desactivar' : 'activar';
  if (!confirm(`¿Deseas ${accion} este cristal?`)) return;
  try {
    const res  = await fetch(API, {
      method: 'PUT',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ id, activo: actual == 1 ? 0 : 1 })
    });
    const data = await res.json();
    if (data.ok) cargar();
  } catch(e) { alert('Error'); }
}

// ── Historial de precios ──────────────────────────────────────────────────────
async function verHistorial(id, nombre) {
  try {
    const res  = await fetch(API + '?id=' + id);
    const data = await res.json();
    const hist = data.historial || [];

    document.getElementById('historialTitulo').textContent = `📋 Historial de precios — ${nombre}`;

    if (!hist.length) {
      document.getElementById('historialContenido').innerHTML =
        '<div class="empty">Sin historial de cambios</div>';
    } else {
      document.getElementById('historialContenido').innerHTML = hist.map(h => {
        const esInicial = h.motivo === 'Precio inicial';
        const fecha = new Date(h.fecha).toLocaleString('es-MX', {
          day:'2-digit', month:'short', year:'numeric',
          hour:'2-digit', minute:'2-digit'
        });
        return `
          <div class="hist-item">
            <div class="hist-dot ${esInicial ? 'inicial' : ''}"></div>
            <div class="hist-info">
              <div class="hist-precios">
                ${esInicial
                  ? `<span class="nuevo">$${parseFloat(h.precio_nuevo).toLocaleString('es-MX',{minimumFractionDigits:2})}</span>`
                  : `<span class="anterior">$${parseFloat(h.precio_anterior).toLocaleString('es-MX',{minimumFractionDigits:2})}</span>
                     → <span class="nuevo">$${parseFloat(h.precio_nuevo).toLocaleString('es-MX',{minimumFractionDigits:2})}</span>`
                }
              </div>
              <div class="hist-meta">${fecha} · ${h.usuario_nombre || '—'}</div>
              ${h.motivo && !esInicial ? `<div class="hist-motivo">"${h.motivo}"</div>` : ''}
            </div>
          </div>`;
      }).join('');
    }

    const wrap = document.getElementById('historialWrap');
    wrap.classList.add('open');
    wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
  } catch(e) {
    alert('Error al cargar historial');
  }
}

function cerrarHistorial() {
  document.getElementById('historialWrap').classList.remove('open');
}

// Cerrar modal al hacer click fuera
document.getElementById('modalBg').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});

// Iniciar
cargar();
</script>
</body>
</html>