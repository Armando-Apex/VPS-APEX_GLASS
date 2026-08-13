// APEX GLASS — Utilidades JS compartidas
// Cargado una vez en dashboard.php y standalone apps (operador, jefe_movil, estaciones)

// Número con decimales configurables; null/undefined → em dash
function fmt(n, dec) {
  dec = dec === undefined ? 2 : dec;
  if (n === null || n === undefined) return '&#8212;';
  var v = parseFloat(n);
  if (isNaN(v)) return '&#8212;';
  return v.toLocaleString('es-MX', {minimumFractionDigits: dec, maximumFractionDigits: dec});
}

// Peso en MXN con 2 decimales
function fmtPeso(n) {
  return n == null ? '&#8212;' : '$' + fmt(n, 2);
}

// Peso en MXN sin decimales (montos grandes en reportes)
function fmtMXN(n) {
  if (!n || isNaN(parseFloat(n))) return '$0';
  return '$' + parseFloat(n).toLocaleString('es-MX', {minimumFractionDigits: 0, maximumFractionDigits: 0});
}

// Fecha corta: "15 jun 2026"
function fmtFecha(f) {
  if (!f) return '&#8212;';
  var d = new Date(String(f).replace(' ', 'T') + (String(f).length === 10 ? 'T12:00:00' : ''));
  return d.toLocaleDateString('es-MX', {day: '2-digit', month: 'short', year: 'numeric'});
}

// Fecha + hora: "15 jun 2026 14:30"
function fmtFechaHora(f) {
  if (!f) return '&#8212;';
  var d = new Date(String(f).includes('T') ? f : String(f).replace(' ', 'T'));
  return d.toLocaleDateString('es-MX', {day: '2-digit', month: 'short', year: 'numeric'}) +
         ' ' + d.toLocaleTimeString('es-MX', {hour: '2-digit', minute: '2-digit'});
}

// Peso en kg con 1 decimal
function fmtKg(n) {
  return parseFloat(n || 0).toFixed(1) + ' kg';
}

// Escapar HTML para atributos
function escAttr(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

// ── Toast global (S3-01) ─────────────────────────────────────────────────────
// Uso: toast('Guardado'); toast('Error de red', 'error'); toast('Aviso','warn');
// Compatible con firmas viejas: toast(msg, true) === toast(msg, 'error')
function toast(msg, tipo) {
  if (tipo === true) tipo = 'error';
  tipo = tipo || 'ok';
  var host = document.getElementById('apex-toast-host');
  if (!host) {
    host = document.createElement('div');
    host.id = 'apex-toast-host';
    host.setAttribute('role', 'status');
    host.setAttribute('aria-live', 'polite');
    document.body.appendChild(host);
  }
  var el = document.createElement('div');
  el.className = 'apex-toast apex-toast-' + tipo;
  el.textContent = msg;
  host.appendChild(el);
  requestAnimationFrame(function(){ el.classList.add('show'); });
  setTimeout(function(){
    el.classList.remove('show');
    setTimeout(function(){ el.remove(); }, 250);
  }, 3000);
}

// ── Modal de confirmación (S3-01) ────────────────────────────────────────────
// const ok = await confirmar('¿Cancelar la orden F-123?', 'Cancelar orden');
function confirmar(msg, titulo, textoOk) {
  return new Promise(function(resolve){
    var ov = document.createElement('div');
    ov.className = 'apex-confirm-overlay';
    ov.innerHTML =
      '<div class="apex-confirm" role="alertdialog" aria-modal="true" aria-label="' + escAttr(titulo||'Confirmar') + '">' +
        '<div class="apex-confirm-title"></div>' +
        '<div class="apex-confirm-msg"></div>' +
        '<div class="apex-confirm-btns">' +
          '<button type="button" class="apex-btn-ghost" data-x="0">Cancelar</button>' +
          '<button type="button" class="apex-btn-danger" data-x="1"></button>' +
        '</div>' +
      '</div>';
    ov.querySelector('.apex-confirm-title').textContent = titulo || 'Confirmar acción';
    ov.querySelector('.apex-confirm-msg').textContent = msg;
    ov.querySelector('[data-x="1"]').textContent = textoOk || 'Sí, continuar';
    document.body.appendChild(ov);
    ov.querySelector('[data-x="1"]').focus();
    ov.addEventListener('click', function(e){
      var x = e.target.getAttribute('data-x');
      if (x === null && e.target !== ov) return;
      ov.remove();
      resolve(x === '1');
    });
    ov.addEventListener('keydown', function(e){
      if (e.key === 'Escape') { ov.remove(); resolve(false); }
    });
  });
}

// ── Íconos SVG en JS (S3-02/S3-07) — espejo mínimo de api/helpers/icons.php ──
var APEX_ICONS = {
  'alert-triangle': '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
  'refresh-cw': '<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
  'box': '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
  'activity': '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
  'bar-chart-2': '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
  'truck': '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
  'scissors': '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/>',
  // S3-02: sweep de emojis → íconos
  'ban': '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
  'check': '<polyline points="20 6 9 17 4 12"/>',
  'check-circle': '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
  'x': '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
  'edit-2': '<path d="M17 3a2.83 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5z"/>',
  'plus-circle': '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/>',
  'search': '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
  'phone': '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
  'eye': '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
  'copy': '<rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
  'key': '<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>',
  'rotate-ccw': '<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/>',
  'clipboard': '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>',
  'megaphone': '<path d="M3 11l19-9v18L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
  'upload': '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>',
  'clipboard-list': '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="12" y1="11" x2="16" y2="11"/><line x1="12" y1="16" x2="16" y2="16"/><line x1="8" y1="11" x2="8.01" y2="11"/><line x1="8" y1="16" x2="8.01" y2="16"/>'
};
function iconoJS(nombre, size) {
  size = size || 16;
  return '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size +
    '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:-2px">' +
    (APEX_ICONS[nombre] || '') + '</svg>';
}

// ── Estado de error con reintento (S3-07) ────────────────────────────────────
// retryFn = nombre de una función global (string) que recarga el contenido.
function stateErrorHTML(detalle, retryFn) {
  return '<div class="state-error">' +
    '<div class="state-error-icon">' + iconoJS('alert-triangle', 28) + '</div>' +
    '<div class="state-error-msg">No se pudo cargar la información</div>' +
    (detalle ? '<div class="state-error-detail">' + escAttr(String(detalle)) + '</div>' : '') +
    (retryFn ? '<button type="button" class="state-error-retry" onclick="' + escAttr(retryFn) + '()">' +
      iconoJS('refresh-cw', 14) + ' Reintentar</button>' : '') +
    '</div>';
}

// ── Estado vacío consistente (S3-08) ─────────────────────────────────────────
function stateEmptyHTML(msg, icon) {
  return '<div class="state-empty"><div class="state-empty-icon">' + iconoJS(icon || 'box', 28) +
    '</div><div class="state-empty-txt">' + escAttr(String(msg||'')) + '</div></div>';
}

// ── Validación visual de formularios (S3-06) ─────────────────────────────────
// marcarError(document.getElementById('clienteId'), 'Selecciona un cliente')
function marcarError(input, msg) {
  if (!input) return;
  var field = input.closest('.field') || input.parentElement || input;
  field.classList.add('campo-error');
  var em = field.querySelector('.error-msg');
  if (!em) { em = document.createElement('div'); em.className = 'error-msg'; field.appendChild(em); }
  em.textContent = msg || 'Campo obligatorio';
  input.focus();
  input.addEventListener('input', function h(){ field.classList.remove('campo-error'); input.removeEventListener('input', h); });
}
