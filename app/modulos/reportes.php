<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_ordenes');
if (!in_array($user['rol'], ['desarrollo','dir_admin'])) {
    http_response_code(403); echo '<p style="padding:24px;color:#dc2626">Sin acceso.</p>'; exit;
}
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=reportes'); exit;
}
?>
<style>
.rep-wrap { padding: 24px; max-width: 900px; }
.rep-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.rep-title { font-size: 18px; font-weight: 600; color: #1a1a1a; flex: 1; }
.rep-filters { display: flex; gap: 8px; }
.rep-filter-btn { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer; color: #475569; }
.rep-filter-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
.rep-list { display: flex; flex-direction: column; gap: 10px; }
.rep-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 18px; display: flex; gap: 14px; align-items: flex-start; }
.rep-card.completado { opacity: .6; }
.rep-tipo-badge { flex-shrink: 0; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; white-space: nowrap; margin-top: 2px; }
.rep-tipo-badge.bug    { background: #fee2e2; color: #dc2626; }
.rep-tipo-badge.mejora { background: #dbeafe; color: #1d4ed8; }
.rep-content { flex: 1; }
.rep-desc { font-size: 13px; color: #1e293b; margin-bottom: 6px; line-height: 1.5; }
.rep-meta { font-size: 11px; color: #94a3b8; display: flex; gap: 12px; flex-wrap: wrap; }
.rep-meta strong { color: #64748b; }
.rep-estado { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
.rep-badge-pend { font-size: 11px; font-weight: 700; background: #fef3c7; color: #92400e; padding: 2px 9px; border-radius: 99px; }
.rep-badge-comp { font-size: 11px; font-weight: 700; background: #dcfce7; color: #166534; padding: 2px 9px; border-radius: 99px; }
.rep-btn-comp { background: #166534; color: #fff; border: none; padding: 6px 14px; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap; }
.rep-btn-comp:hover { background: #15803d; }
.rep-empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 13px; }
.rep-completado-por { font-size: 10px; color: #94a3b8; margin-top: 2px; }
.rep-elem-chip { display:inline-flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
.rep-elem-tag { background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px; font-size:11px; padding:2px 8px; color:#0284c7; }
.rep-elem-tag span { color:#0c4a6e; font-family:monospace; }
.rep-card { cursor: pointer; }
.rep-card:hover { border-color: #93c5fd; }
.rep-modal-back { display:none; position:fixed; inset:0; background:rgba(15,23,42,.5); z-index:9999; align-items:center; justify-content:center; padding:20px; }
.rep-modal-back.open { display:flex; }
.rep-modal { background:#fff; border-radius:12px; max-width:560px; width:100%; max-height:85vh; overflow-y:auto; padding:22px 24px; }
.rep-modal-close { float:right; background:none; border:none; font-size:20px; cursor:pointer; color:#94a3b8; line-height:1; }
.rep-modal h3 { margin:0 0 14px; font-size:16px; color:#1a1a1a; }
.rep-modal-desc { font-size:13px; color:#1e293b; line-height:1.6; white-space:pre-wrap; background:#f8fafc; border-radius:8px; padding:12px 14px; margin-bottom:14px; }
.rep-modal-field { font-size:12px; color:#475569; margin-bottom:8px; }
.rep-modal-field strong { color:#1e293b; display:inline-block; min-width:70px; }
.rep-modal-field code { font-family:monospace; background:#f1f5f9; border-radius:4px; padding:1px 6px; color:#0c4a6e; word-break:break-all; }
.rep-btn-ir { display:block; width:100%; margin-top:14px; background:#ea580c; color:#fff; border:none; padding:10px 14px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; }
.rep-btn-ir:hover { background:#c2410c; }
</style>

<div class="rep-wrap">
  <div class="rep-header">
    <span class="rep-title">&#128681; Reportes</span>
    <div class="rep-filters">
      <button class="rep-filter-btn active" id="rep-f-todos"      onclick="ModReportes.filtrar('todos')">Todos</button>
      <button class="rep-filter-btn"        id="rep-f-pendiente"  onclick="ModReportes.filtrar('pendiente')">Pendientes</button>
      <button class="rep-filter-btn"        id="rep-f-completado" onclick="ModReportes.filtrar('completado')">Completados</button>
    </div>
  </div>
  <div id="rep-list" class="rep-list">
    <div class="rep-empty">Cargando&#8230;</div>
  </div>
</div>

<div class="rep-modal-back" id="rep-modal-back" onclick="if(event.target===this) ModReportes.cerrarDetalle()">
  <div class="rep-modal">
    <button class="rep-modal-close" onclick="ModReportes.cerrarDetalle()">&times;</button>
    <h3>Detalle del reporte</h3>
    <div id="rep-modal-body"></div>
  </div>
</div>

<script>
var ModReportes = (function() {
  var _todos   = [];
  var _filtro  = 'todos';

  var ROLES = {
    'dir_admin':'Dir. Administrativo', 'dueno':'Dueño', 'comercial':'Comercial',
    'administracion':'Administración', 'operador':'Operador', 'chofer':'Chofer',
    'jefe_piso':'Jefe de Piso', 'director':'Director', 'desarrollo':'Desarrollo'
  };

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = (s == null) ? '' : String(s);
    return d.innerHTML;
  }

  function _fmt(dt) {
    if (!dt) return '—';
    var d = new Date(dt.replace(' ','T'));
    return d.toLocaleDateString('es-MX', {day:'2-digit',month:'short',year:'numeric'})
      + ' ' + d.toLocaleTimeString('es-MX', {hour:'2-digit',minute:'2-digit'});
  }

  function _cargar() {
    fetch('../api/reportes.php?accion=lista')
      .then(function(r) { return r.json(); })
      .then(function(d) {
        _todos = d.reportes || [];
        _render();
      })
      .catch(function() {
        document.getElementById('rep-list').innerHTML = '<div class="rep-empty">Error al cargar</div>';
      });
  }

  function _render() {
    var lista = _todos;
    if (_filtro === 'pendiente')  lista = _todos.filter(function(r) { return r.estado === 'pendiente'; });
    if (_filtro === 'completado') lista = _todos.filter(function(r) { return r.estado === 'completado'; });

    var el = document.getElementById('rep-list');
    if (!lista.length) { el.innerHTML = '<div class="rep-empty">Sin reportes</div>'; return; }

    el.innerHTML = lista.map(function(r) {
      var rolLabel = ROLES[r.creado_por_rol] || r.creado_por_rol;
      var elemHtml = '';
      if (r.elemento) {
        var el;
        try { el = JSON.parse(r.elemento); } catch(e) { el = null; }
        if (el) {
          elemHtml = '<div class="rep-elem-chip">'
            + (el.modulo ? '<span class="rep-elem-tag">Módulo: <span>' + esc(el.modulo) + '</span></span>' : '')
            + (el.ruta   ? '<span class="rep-elem-tag">Ruta: <span>' + esc(el.ruta) + '</span></span>' : '')
            + (el.texto  ? '<span class="rep-elem-tag">Texto: <span>' + esc(el.texto.length > 80 ? el.texto.slice(0,80) + '…' : el.texto) + '</span></span>' : '')
            + '</div>';
        }
      }
      var compInfo = r.estado === 'completado'
        ? '<div class="rep-completado-por">Completado por ' + esc(r.completado_por) + ' · ' + _fmt(r.completado_at) + '</div>'
        : '';
      var acciones = r.estado === 'pendiente'
        ? '<button class="rep-btn-comp" onclick="event.stopPropagation();ModReportes.completar(' + r.id + ')">&#10003; Completado</button>'
        : '';
      return '<div class="rep-card ' + r.estado + '" id="rep-card-' + r.id + '" onclick="ModReportes.verDetalle(' + r.id + ')">'
        + '<span class="rep-tipo-badge ' + r.tipo + '">' + (r.tipo === 'bug'
  ? '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:3px"><path d="m8 2 1.88 1.88"/><path d="M14.12 3.88 16 2"/><path d="M9 7.13v-1a3.003 3.003 0 1 1 6 0v1"/><path d="M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v3c0 3.3-2.7 6-6 6z"/><path d="M12 20v-9"/><path d="M6.53 9C4.6 8.8 3 7.1 3 5"/><path d="M6 13H2"/><path d="M3 21c0-2.1 1.7-3.9 3.8-4"/><path d="M20.97 5c0 2.1-1.6 3.8-3.5 4"/><path d="M22 13h-4"/><path d="M17.2 17c2.1.1 3.8 1.9 3.8 4"/></svg>Bug'
  : '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-1px;margin-right:3px"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>Mejora'
) + '</span>'
        + '<div class="rep-content">'
        +   '<div class="rep-desc">' + r.descripcion.replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>') + '</div>'
        +   elemHtml
        +   '<div class="rep-meta">'
        +     '<span><strong>' + esc(r.creado_por) + '</strong> · ' + esc(rolLabel) + '</span>'
        +     '<span>' + _fmt(r.created_at) + '</span>'
        +   '</div>'
        +   compInfo
        + '</div>'
        + '<div class="rep-estado">'
        +   '<span class="rep-badge-' + (r.estado === 'completado' ? 'comp' : 'pend') + '">'
        +     (r.estado === 'completado' ? '✓ Completado' : 'Pendiente') + '</span>'
        +   acciones
        + '</div>'
        + '</div>';
    }).join('');
  }

  var TAGS = {
    'a':'Link', 'button':'Botón', 'input':'Campo', 'select':'Selector',
    'td':'Celda de tabla', 'th':'Encabezado de tabla', 'span':'Texto', 'div':'Bloque'
  };

  function verDetalle(id) {
    var r = null;
    for (var i = 0; i < _todos.length; i++) { if (_todos[i].id === id) { r = _todos[i]; break; } }
    if (!r) return;

    var rolLabel = ROLES[r.creado_por_rol] || r.creado_por_rol;
    var html = '<div class="rep-modal-field"><strong>Tipo:</strong> ' + (r.tipo === 'bug' ? 'Bug' : 'Mejora') + '</div>'
      + '<div class="rep-modal-field"><strong>Reportó:</strong> ' + esc(r.creado_por) + ' (' + esc(rolLabel) + ')</div>'
      + '<div class="rep-modal-field"><strong>Fecha:</strong> ' + _fmt(r.created_at) + '</div>';

    var el = null;
    if (r.elemento) { try { el = JSON.parse(r.elemento); } catch(e) { el = null; } }
    if (el) {
      if (el.modulo) html += '<div class="rep-modal-field"><strong>Módulo:</strong> <code>' + esc(el.modulo) + '</code></div>';
      if (el.ruta)   html += '<div class="rep-modal-field"><strong>Ruta:</strong> <code>' + esc(el.ruta) + '</code></div>';
      if (el.msgId)  html += '<div class="rep-modal-field"><strong>Elemento:</strong> Mensaje de chat</div>';
      else if (el.tag) html += '<div class="rep-modal-field"><strong>Elemento:</strong> ' + esc(TAGS[el.tag] || el.tag) + '</div>';
      if (el.texto)  html += '<div class="rep-modal-field"><strong>Se seleccionó:</strong></div><div class="rep-modal-desc">' + esc(el.texto) + '</div>';
    }

    html += '<div class="rep-modal-field"><strong>Descripción:</strong></div>'
      + '<div class="rep-modal-desc">' + esc(r.descripcion) + '</div>';

    if (r.estado === 'completado') {
      html += '<div class="rep-modal-field"><strong>Completado:</strong> ' + esc(r.completado_por) + ' · ' + _fmt(r.completado_at) + '</div>';
    }

    if (el && el.modulo && (el.ruta || el.msgId)) {
      html += '<button class="rep-btn-ir" onclick="ModReportes.irAlElemento(' + r.id + ')">&#8594; Ir al elemento</button>';
    }

    document.getElementById('rep-modal-body').innerHTML = html;
    document.getElementById('rep-modal-back').className = 'rep-modal-back open';
  }

  function irAlElemento(id) {
    var r = null;
    for (var i = 0; i < _todos.length; i++) { if (_todos[i].id === id) { r = _todos[i]; break; } }
    if (!r || !r.elemento) return;
    var el = null;
    try { el = JSON.parse(r.elemento); } catch(e) { el = null; }
    if (!el || !el.modulo || typeof window.irA !== 'function') {
      alert('No se pudo ubicar el módulo de este reporte.');
      return;
    }
    cerrarDetalle();
    window.irA(el.modulo).then(function() {
      if (el.msgId) { _abrirChatYResaltar(el.convId, el.msgId, 0); }
      else { _buscarYResaltar(el.ruta, 0); }
    });
  }

  function _abrirChatYResaltar(convId, msgId, intentos) {
    if (typeof window.cmpTab !== 'function' || typeof window.cmpAbrirConv !== 'function') {
      if (intentos < 15) { setTimeout(function() { _abrirChatYResaltar(convId, msgId, intentos + 1); }, 200); return; }
      alert('No se pudo abrir el chat — puede que el módulo Campañas tarde en cargar, intenta de nuevo.');
      return;
    }
    var btnConv = document.getElementById('cmpTabBtnConv');
    if (btnConv) window.cmpTab('conversaciones', btnConv);
    if (convId) window.cmpAbrirConv(parseInt(convId, 10));
    _esperarMensaje(msgId, 0);
  }

  function _esperarMensaje(msgId, intentos) {
    var target = document.querySelector('#cmpMsgs [data-msg-id="' + msgId + '"]');
    if (!target && intentos < 20) {
      setTimeout(function() { _esperarMensaje(msgId, intentos + 1); }, 250);
      return;
    }
    if (!target) {
      alert('Se abrió el chat pero no se encontró ese mensaje específico — puede que ya no exista.');
      return;
    }
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target.classList.add('rep-pick-highlight');
    setTimeout(function() { target.classList.remove('rep-pick-highlight'); }, 4000);
  }

  function _buscarYResaltar(ruta, intentos) {
    var target = null;
    if (ruta) {
      try { target = document.querySelector(ruta); } catch(e) { target = null; }
    }
    if (!target && intentos < 15) {
      setTimeout(function() { _buscarYResaltar(ruta, intentos + 1); }, 200);
      return;
    }
    if (!target) {
      alert('No se encontró el elemento en la página — puede que haya cambiado desde que se reportó.');
      return;
    }
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    target.classList.add('rep-pick-highlight');
    setTimeout(function() { target.classList.remove('rep-pick-highlight'); }, 4000);
  }

  function cerrarDetalle() {
    document.getElementById('rep-modal-back').className = 'rep-modal-back';
  }

  function filtrar(f) {
    _filtro = f;
    var ids = ['todos','pendiente','completado'];
    for (var i = 0; i < ids.length; i++) {
      var btn = document.getElementById('rep-f-' + ids[i]);
      if (btn) btn.className = 'rep-filter-btn' + (ids[i] === f ? ' active' : '');
    }
    _render();
  }

  function completar(id) {
    if (!confirm('¿Marcar este reporte como completado?')) return;
    fetch('../api/reportes.php?accion=completar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.ok) {
          _cargar();
          if (typeof window.actualizarBadgeReportes === 'function') window.actualizarBadgeReportes();
        }
        else { alert(d.error || 'Error'); }
      })
      .catch(function() { alert('Error de conexión'); });
  }

  _cargar();

  return { filtrar: filtrar, completar: completar, verDetalle: verDetalle, cerrarDetalle: cerrarDetalle, irAlElemento: irAlElemento };
})();
</script>
