<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_dashboard');
if (!in_array($user['rol'], ['dir_admin', 'desarrollo'])) { http_response_code(403); exit; }
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=admin_comunicados'); exit;
}
?>
<style>
.com-wrap        { padding: 28px 32px; max-width: 900px; }
.com-titulo      { font-size: 20px; font-weight: 700; color: var(--c-text); margin-bottom: 4px; }
.com-sub         { font-size: 13px; color: var(--c-muted); margin-bottom: 28px; }
.com-form-card   { background: var(--c-white); border: 1px solid var(--c-border); border-radius: 12px; padding: 24px; margin-bottom: 32px; }
.com-form-titulo { font-size: 14px; font-weight: 700; color: var(--c-text); margin-bottom: 18px; }
.com-fields      { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
.com-field       { display: flex; flex-direction: column; gap: 6px; }
.com-field.full  { grid-column: 1 / -1; }
.com-label       { font-size: 12px; font-weight: 600; color: var(--c-muted); text-transform: uppercase; letter-spacing: .5px; }
.com-input, .com-select {
    padding: 9px 12px; border: 1px solid var(--c-border); border-radius: 8px;
    font-size: 14px; color: var(--c-text); background: var(--c-bg);
    transition: border .15s; outline: none;
}
.com-input:focus, .com-select:focus { border-color: var(--c-blue); }
.com-input-hint  { font-size: 11px; color: var(--c-muted); margin-top: 2px; }
.com-btn         { padding: 10px 24px; background: var(--c-blue); color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: opacity .15s; }
.com-btn:hover   { opacity: .85; }
.com-btn:disabled{ opacity: .5; cursor: not-allowed; }
.com-btn-danger  { background: var(--c-red); }
.com-btn-sm      { padding: 6px 14px; font-size: 12px; }
.com-msg         { padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 16px; display: none; }
.com-msg.ok      { background: #dcfce7; color: #16a34a; }
.com-msg.err     { background: #fee2e2; color: #dc2626; }
.com-lista-titulo{ font-size: 14px; font-weight: 700; color: var(--c-text); margin-bottom: 14px; }
.com-lista-wrap  { display: flex; flex-direction: column; gap: 12px; }
.com-item        { display: flex; align-items: center; gap: 16px; background: var(--c-white); border: 1px solid var(--c-border); border-radius: 10px; padding: 14px 16px; }
.com-item.inactivo { opacity: .55; }
.com-item-info   { flex: 1; min-width: 0; }
.com-item-nombre { font-size: 14px; font-weight: 600; color: var(--c-text); }
.com-item-meta   { font-size: 12px; color: var(--c-muted); margin-top: 3px; }
.com-item-archivo{ font-size: 11px; color: var(--c-muted); font-family: monospace; margin-top: 2px; }
.com-item-badge  { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; flex-shrink: 0; }
.com-badge-on    { background: #dcfce7; color: #16a34a; }
.com-badge-off   { background: #f1f5f9; color: #94a3b8; }
.com-item-actions{ display: flex; gap: 8px; flex-shrink: 0; align-items: center; }
.com-countdown   { font-size: 11px; color: var(--c-muted); font-variant-numeric: tabular-nums; margin-top: 4px; }
.com-countdown.pronto { color: #f5a623; font-weight: 700; }
.com-countdown.listo  { color: #16a34a; font-weight: 700; }
.com-btn-now     { background: #16a34a; color: #fff; border: none; }
.com-btn-now:hover { opacity: .85; }
.com-empty       { text-align: center; padding: 40px; color: var(--c-muted); font-size: 14px; }

@media(max-width:768px){
  .com-wrap { padding: 14px 12px; }

  /* Formulario: 1 columna en móvil */
  .com-fields { grid-template-columns: 1fr; gap: 12px; }
  .com-field.full { grid-column: 1; }
  .com-form-card { padding: 16px; }
  .com-btn { width: 100%; }

  /* Cards de comunicados */
  .com-item {
    flex-direction: column;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
  }
  .com-item-info { width: 100%; }
  .com-item-badge { align-self: flex-start; }

  /* Botones de acción en fila compacta */
  .com-item-actions {
    width: 100%;
    flex-wrap: wrap;
    gap: 6px;
  }
  .com-item-actions .com-btn { flex: 1; min-width: 80px; text-align: center; }
}
</style>

<div class="com-wrap">
  <div class="com-titulo">📢 Admin Comunicados</div>
  <div class="com-sub">Los comunicados se muestran en el tablero SmartTV. La duración y el intervalo los configuras por comunicado.</div>

  <div class="com-form-card">
    <div class="com-form-titulo">➕ Nuevo Comunicado</div>
    <div id="com-msg" class="com-msg"></div>
    <div class="com-fields">
      <div class="com-field">
        <label class="com-label">Nombre / descripción</label>
        <input type="text" id="com-nombre" class="com-input" placeholder="Ej. Aviso seguridad mayo" maxlength="100">
      </div>
      <div class="com-field">
        <label class="com-label">Imagen</label>
        <select id="com-archivo" class="com-select">
          <option value="">— Cargando imágenes… —</option>
        </select>
        <span class="com-input-hint">Imágenes disponibles en la carpeta del servidor</span>
      </div>
      <div class="com-field">
        <label class="com-label">Mostrar cada (minutos)</label>
        <input type="number" id="com-intervalo" class="com-input" min="1" max="1440" placeholder="60">
      </div>
      <div class="com-field">
        <label class="com-label">Duración (segundos)</label>
        <input type="number" id="com-duracion" class="com-input" min="5" max="3600" placeholder="90">
      </div>
    </div>
    <button class="com-btn" id="com-btn-enviar" onclick="comEnviar()">📤 Agregar Comunicado</button>
  </div>

  <div class="com-lista-titulo">📋 Comunicados</div>
  <div class="com-lista-wrap" id="com-lista"><div class="com-empty">Cargando…</div></div>
</div>

<script>
window.ModAdminComunicados = (function(){

const API = '../api/comunicados.php';
let _comData     = [];
let _tickTimer   = null;
let _reloadTimer = null;

// ── Cargar imágenes ───────────────────────────────────────────────────────────
async function cargarImagenes() {
  try {
    const r = await fetch(API + '?accion=imagenes');
    const d = await r.json();
    const sel = document.getElementById('com-archivo');
    if (!d.ok || !d.imagenes.length) {
      sel.innerHTML = '<option value="">— No hay imágenes en la carpeta —</option>';
      return;
    }
    sel.innerHTML = '<option value="">— Selecciona una imagen —</option>' +
      d.imagenes.map(f => `<option value="${f}">${f}</option>`).join('');
  } catch(e) {
    document.getElementById('com-archivo').innerHTML = '<option value="">— Error al cargar —</option>';
  }
}

// ── Enviar ────────────────────────────────────────────────────────────────────
window.comEnviar = async function() {
  const nombre    = document.getElementById('com-nombre').value.trim();
  const archivo   = document.getElementById('com-archivo').value;
  const intervalo = parseInt(document.getElementById('com-intervalo').value);
  const duracion  = parseInt(document.getElementById('com-duracion').value);

  if (!nombre)   { mostrarMsg('Escribe un nombre para el comunicado', 'err'); return; }
  if (!archivo)  { mostrarMsg('Selecciona una imagen', 'err'); return; }
  if (!intervalo || intervalo < 1 || intervalo > 1440) { mostrarMsg('Intervalo inválido (1–1440 min)', 'err'); return; }
  if (!duracion  || duracion  < 5 || duracion  > 3600) { mostrarMsg('Duración inválida (5–3600 seg)', 'err'); return; }

  const btn = document.getElementById('com-btn-enviar');
  btn.disabled = true; btn.textContent = 'Guardando…';
  try {
    const r = await fetch(API + '?accion=crear', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nombre, archivo, intervalo_min: intervalo, duracion_seg: duracion })
    });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error desconocido');
    mostrarMsg('✅ Comunicado agregado correctamente', 'ok');
    document.getElementById('com-nombre').value    = '';
    document.getElementById('com-archivo').value   = '';
    document.getElementById('com-intervalo').value = '';
    document.getElementById('com-duracion').value  = '';
    await cargarLista();
  } catch(e) { mostrarMsg('Error: ' + e.message, 'err'); }
  finally { btn.disabled = false; btn.textContent = '📤 Agregar Comunicado'; }
};

// ── Toggle ────────────────────────────────────────────────────────────────────
window.comToggle = async function(id) {
  try {
    const r = await fetch(API + '?accion=toggle', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error);
    await cargarLista();
  } catch(e) { alert('Error: ' + e.message); }
};

// ── Eliminar ──────────────────────────────────────────────────────────────────
window.comEliminar = async function(id, nombre) {
  if (!confirm(`¿Eliminar el comunicado "${nombre}"?`)) return;
  try {
    const r = await fetch(API + '?accion=eliminar', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error);
    await cargarLista();
  } catch(e) { alert('Error: ' + e.message); }
};

// ── Mostrar ahora ─────────────────────────────────────────────────────────────
window.comMostrarAhora = async function(id) {
  try {
    const r = await fetch(API + '?accion=mostrar_ahora', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error');
    mostrarMsg('✅ Aparecerá en el SmartTV en los próximos 30 segundos', 'ok');
  } catch(e) { alert('Error: ' + e.message); }
};

// ── Ticker del contador (cada segundo, solo actualiza texto) ──────────────────
function tickContadores() {
  if (!_comData.length) return;
  const ahora = Date.now();
  _comData.forEach(c => {
    if (c.activo != 1) return;
    const el = document.getElementById('cdwn-' + c.id);
    if (!el) return;
    const ahora       = Date.now();
    const intervaloMs = c.intervalo_min * 60 * 1000;

    // Si nunca se ha mostrado, mostrar "Usar ▶ Ahora para la primera vez"
    if (!c.last_shown_ts || c.last_shown_ts == 0) {
      el.textContent = '⚡ Usar ▶ Ahora para mostrar por primera vez';
      el.className   = 'com-countdown';
      return;
    }

    const lastMs     = c.last_shown_ts * 1000;
    const restanteMs = Math.max(0, intervaloMs - (ahora - lastMs));
    const mins = Math.floor(restanteMs / 60000);
    const segs = Math.floor((restanteMs % 60000) / 1000);
    if (restanteMs === 0)        { el.textContent = '🟢 Lista para mostrar'; el.className = 'com-countdown listo'; }
    else if (restanteMs < 60000) { el.textContent = `🟡 Próxima en ${segs}s`;        el.className = 'com-countdown pronto'; }
    else                          { el.textContent = `⏱ Próxima en ${mins}m ${segs}s`; el.className = 'com-countdown'; }
  });
}

// ── Cargar lista ──────────────────────────────────────────────────────────────
async function cargarLista() {
  const lista = document.getElementById('com-lista');
  try {
    const r = await fetch(API + '?accion=listar&t=' + Date.now());
    const d = await r.json();
    if (!d.ok || !d.data.length) {
      lista.innerHTML = '<div class="com-empty">📭 No hay comunicados aún</div>';
      _comData = [];
      return;
    }
    _comData = d.data;

    lista.innerHTML = d.data.map(c => {
      const badgeCls = c.activo == 1 ? 'com-badge-on' : 'com-badge-off';
      const badgeTxt = c.activo == 1 ? '● Activo' : '○ Inactivo';
      const itemCls  = c.activo == 1 ? '' : ' inactivo';
      const fecha    = new Date(c.created_at).toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' });

      // Calcular countdown inicial
      const lastMs      = (c.last_shown_ts || 0) * 1000;
      const intervaloMs = c.intervalo_min * 60 * 1000;
      let cdwnTxt = '', cdwnCls = 'com-countdown';
      if (c.activo == 1) {
        if (!c.last_shown_ts || c.last_shown_ts == 0) {
          cdwnTxt = '⚡ Usar ▶ Ahora para mostrar por primera vez';
        } else {
          const restanteMs = Math.max(0, intervaloMs - (Date.now() - lastMs));
          const mins = Math.floor(restanteMs / 60000);
          const segs = Math.floor((restanteMs % 60000) / 1000);
          if (restanteMs === 0)        { cdwnTxt = '🟢 Lista para mostrar'; cdwnCls += ' listo'; }
          else if (restanteMs < 60000) { cdwnTxt = `🟡 Próxima en ${segs}s`; cdwnCls += ' pronto'; }
          else                          { cdwnTxt = `⏱ Próxima en ${mins}m ${segs}s`; }
        }
      }

      const botonesActivo = c.activo == 1
        ? `<button class="com-btn com-btn-sm com-btn-now" onclick="comMostrarAhora(${c.id})">▶ Ahora</button>
           <button class="com-btn com-btn-sm" onclick="comToggle(${c.id})">Pausar</button>`
        : `<button class="com-btn com-btn-sm" onclick="comToggle(${c.id})">Activar</button>`;

      return `
        <div class="com-item${itemCls}" id="com-item-${c.id}">
          <div class="com-item-info">
            <div class="com-item-nombre">${c.nombre}</div>
            <div class="com-item-meta">Cada ${c.intervalo_min} min &middot; ${c.duracion_seg} seg duración &middot; ${fecha} por ${c.creado_por}</div>
            <div class="com-item-archivo">📁 ${c.archivo}</div>
            ${c.activo == 1 ? `<div class="${cdwnCls}" id="cdwn-${c.id}">${cdwnTxt}</div>` : ''}
          </div>
          <span class="com-item-badge ${badgeCls}">${badgeTxt}</span>
          <div class="com-item-actions">
            ${botonesActivo}
            <button class="com-btn com-btn-sm com-btn-danger" onclick="comEliminar(${c.id}, '${c.nombre.replace(/'/g,"\\'")}')">Eliminar</button>
          </div>
        </div>`;
    }).join('');
  } catch(e) {
    lista.innerHTML = '<div class="com-empty">Error al cargar</div>';
  }
}

function mostrarMsg(txt, tipo) {
  const el = document.getElementById('com-msg');
  el.textContent = txt; el.className = 'com-msg ' + tipo; el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 5000);
}

// ── Iniciar ───────────────────────────────────────────────────────────────────
cargarImagenes();
cargarLista();
_tickTimer   = setInterval(tickContadores, 1000);         // actualiza contador cada 1s
_reloadTimer = setInterval(cargarLista,   30 * 1000);     // recarga BD cada 30s para sincronizar last_shown_ts

return {};
})();
</script>