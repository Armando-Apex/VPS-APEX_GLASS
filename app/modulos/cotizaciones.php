<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
requirePermiso('ver_ordenes');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=cotizaciones'); exit;
}
$es_dir_admin_cots = (($_SESSION['user_rol'] ?? '') === 'dir_admin');
?>
<style>
.cot-wrap { padding: 24px; }
.page-title { font-size: 18px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #9ca3af; margin-bottom: 16px; }
.cot-toolbar { display: flex; gap: 10px; margin-bottom: 16px; align-items: center; flex-wrap: wrap; }
.cot-search { flex: 1; min-width: 200px; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
.cot-search:focus { border-color: #2563eb; }
.btn-nueva { background: #2563eb; color: white; border: none; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-nueva:hover { background: #1d4ed8; }
.cot-tabs { display: flex; gap: 2px; background: #f3f4f6; padding: 3px; border-radius: 10px; width: fit-content; margin-bottom: 16px; }
.cot-tab { padding: 6px 16px; border-radius: 8px; border: none; font-size: 13px; cursor: pointer; font-weight: 500; background: none; color: #6b7280; }
.cot-tab.active { background: #fff; color: #1a1a1a; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.cot-cnt { font-size: 11px; font-weight: 600; padding: 1px 6px; border-radius: 99px; background: #e5e7eb; color: #6b7280; margin-left: 4px; }
.cot-table { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
thead th { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; }
tbody tr { border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background .1s; }
tbody tr:hover { background: #f8fafc; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 11px 14px; font-size: 13px; }
.cot-folio { font-weight: 700; color: #2563eb; }
.badge-cot   { background: #dbeafe; color: #1d4ed8; }
.badge-orden { background: #dcfce7; color: #15803d; }
.badge-canc  { background: #fee2e2; color: #b91c1c; }
.est-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 99px; }
.loading-msg { text-align: center; padding: 48px; color: #9ca3af; font-size: 14px; }

/* ── Sección autorizaciones pendientes ── */
.auth-pend-box  { border-radius: 12px; overflow: hidden; margin-bottom: 16px; border: 1.5px solid #fcd34d; }
.auth-pend-hdr  { background: #fffbeb; padding: 12px 18px; display: flex; align-items: center; gap: 10px; cursor: pointer; user-select: none; }
.auth-pend-hdr:hover { background: #fef3c7; }
.auth-pend-title { font-size: 14px; font-weight: 700; color: #92400e; flex: 1; }
.auth-pend-cnt  { background: #f59e0b; color: white; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.auth-pend-body { background: #fff; padding: 0 18px 16px; display: none; }
.auth-pend-body.open { display: block; }
.auth-pend-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
.auth-pend-table thead th { text-align: left; padding: 6px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #64748b; border-bottom: 1.5px solid #e2e8f0; }
.auth-pend-table tbody td { padding: 10px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
.auth-pend-table tbody tr:last-child td { border-bottom: none; }
.auth-nota-in { width: 100%; font-size: 12px; border: 1px solid #d1d5db; border-radius: 6px; padding: 5px 8px; resize: none; margin-bottom: 6px; }
.btn-apr { background: #16a34a; color: white; border: none; padding: 5px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
.btn-apr:hover { background: #15803d; }
.btn-rec { background: #dc2626; color: white; border: none; padding: 5px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
.btn-rec:hover { background: #b91c1c; }

.pager-inner { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 12px 16px; border-top: 1px solid #f1f5f9; }
.pager-btn { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 14px; font-size: 13px; font-weight: 600; cursor: pointer; color: #374151; }
.pager-btn:hover:not([disabled]) { background: #e2e8f0; }
.pager-btn[disabled] { opacity: .4; cursor: default; }
.pager-info { font-size: 12px; color: #6b7280; }

@media(max-width:768px){
  .cot-wrap { padding: 12px; }

  /* Toolbar: buscador arriba, botón abajo */
  .cot-toolbar { flex-direction: column; align-items: stretch; gap: 8px; }
  .cot-search  { width: 100%; min-width: 0; }
  .btn-nueva   { width: 100%; text-align: center; }

  /* Tabs: scroll horizontal */
  .cot-tabs { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; }
  .cot-tab  { white-space: nowrap; padding: 6px 12px; font-size: 12px; }

  /* Tabla: ocultar Asesor, Fecha creación y Estado (la tab ya lo indica) */
  thead th:nth-child(3),
  thead th:nth-child(4),
  thead th:nth-child(7),
  tbody td:nth-child(3),
  tbody td:nth-child(4),
  tbody td:nth-child(7) { display: none; }

  tbody td { padding: 10px 8px; font-size: 12px; }
  thead th  { padding: 8px 8px; font-size: 10px; }

  /* Folio más compacto */
  .cot-folio { font-size: 12px; }
}
</style>

<div class="cot-wrap">
  <div class="page-title">Cotizaciones</div>
  <div class="page-sub" id="cot-sub">Cargando&#8230;</div>

  <?php if ($es_dir_admin_cots): ?>
  <div class="auth-pend-box" id="authPendBox" style="display:none">
    <div class="auth-pend-hdr" onclick="ModCotizaciones._toggleAuthPend()">
      <span class="auth-pend-title">&#9203; Autorizaciones de descuento pendientes</span>
      <span class="auth-pend-cnt" id="authPendCnt">0</span>
      <span id="authPendArrow" style="color:#92400e;font-size:12px">&#9660;</span>
    </div>
    <div class="auth-pend-body" id="authPendBody"></div>
  </div>
  <?php endif; ?>

  <div class="cot-toolbar">
    <input type="text" class="cot-search" id="cot-q" placeholder="&#128269; Buscar por folio, orden, cliente o proyecto&#8230;" oninput="ModCotizaciones._filtrar()">
    <button class="btn-nueva" onclick="irA('cotizacion',{nuevo:'1'})">+ Nueva Cotizaci&#243;n</button>
  </div>

  <div class="cot-tabs">
    <button class="cot-tab active" onclick="ModCotizaciones._tab('cotizacion')">Cotizaciones <span class="cot-cnt" id="cot-cnt-cot">&#8212;</span></button>
    <button class="cot-tab"        onclick="ModCotizaciones._tab('orden')"      >&#211;rdenes <span class="cot-cnt" id="cot-cnt-ord">&#8212;</span></button>
    <button class="cot-tab"        onclick="ModCotizaciones._tab('cancelada')"  >Canceladas <span class="cot-cnt" id="cot-cnt-can">&#8212;</span></button>
  </div>

  <div class="cot-table">
    <table>
      <thead><tr>
        <th>Folio</th><th>Cliente</th><th>Asesor</th>
        <th>Fecha</th><th>Entrega</th><th>Total</th><th>Estado</th>
      </tr></thead>
      <tbody id="cot-tbody"><tr><td colspan="7" class="loading-msg">Cargando&#8230;</td></tr></tbody>
    </table>
    <div id="cot-pager"></div>
  </div>
</div>

<script>
var ES_DIR_ADMIN_COTS = <?= $es_dir_admin_cots ? 'true' : 'false' ?>;

var ModCotizaciones = (function() {

var _cotData      = [];
var _cotTab       = 'cotizacion';
var _cotPage      = 1;
var _authPendOpen = false;
var _COT_PER_PAGE = 25;

async function cotCargar() {
  try {
    var res  = await fetch('../api/cotizaciones.php?limit=200&t=' + Date.now());
    var data = await res.json();
    _cotData = Array.isArray(data) ? data : (data.cotizaciones || []);
    cotFiltrar();
    document.getElementById('cot-sub').textContent =
      'Actualizado a las ' + new Date().toLocaleTimeString('es-MX', {hour:'2-digit', minute:'2-digit'});
  } catch(e) {
    document.getElementById('cot-sub').textContent = 'Error al cargar';
  }
}

function cotTab(tab) {
  _cotTab  = tab;
  _cotPage = 1;
  document.querySelectorAll('.cot-tab').forEach(function(b, i) {
    b.classList.toggle('active', ['cotizacion','orden','cancelada'][i] === tab);
  });
  cotFiltrar();
}

function cotMatchSearch(c, q) {
  if (!q) return true;
  return (c.folio||'').toLowerCase().indexOf(q) >= 0
      || (c.cliente_nombre||'').toLowerCase().indexOf(q) >= 0
      || (c.orden_folio||'').toLowerCase().indexOf(q) >= 0
      || (c.proyecto||'').toLowerCase().indexOf(q) >= 0;
}

function cotPaginar(dir) {
  _cotPage += dir;
  cotFiltrar();
}
window.cotPaginar = cotPaginar;

function cotFiltrar() {
  var q = ((document.getElementById('cot-q') || {}).value || '').toLowerCase().trim();

  // Auto-switch tab si la búsqueda no tiene resultados en el tab actual pero sí en otro
  if (q) {
    var enActual = _cotData.filter(function(c) { return c.estatus === _cotTab && cotMatchSearch(c, q); });
    if (enActual.length === 0) {
      var tabs = ['cotizacion', 'orden', 'cancelada'];
      for (var ti = 0; ti < tabs.length; ti++) {
        if (tabs[ti] === _cotTab) continue;
        var tabCheck = tabs[ti];
        var enOtro = _cotData.filter(function(c) { return c.estatus === tabCheck && cotMatchSearch(c, q); });
        if (enOtro.length > 0) {
          _cotTab  = tabCheck;
          _cotPage = 1;
          document.querySelectorAll('.cot-tab').forEach(function(b, i) {
            b.classList.toggle('active', ['cotizacion','orden','cancelada'][i] === _cotTab);
          });
          break;
        }
      }
    }
  }

  var lista = _cotData.filter(function(c) { return c.estatus === _cotTab && cotMatchSearch(c, q); });

  var cots = _cotData.filter(function(c){ return c.estatus==='cotizacion'; }).length;
  var ords = _cotData.filter(function(c){ return c.estatus==='orden'; }).length;
  var cans = _cotData.filter(function(c){ return c.estatus==='cancelada'; }).length;
  document.getElementById('cot-cnt-cot').textContent = cots;
  document.getElementById('cot-cnt-ord').textContent = ords;
  document.getElementById('cot-cnt-can').textContent = cans;

  var totalPags = Math.max(1, Math.ceil(lista.length / _COT_PER_PAGE));
  if (_cotPage > totalPags) _cotPage = totalPags;
  if (_cotPage < 1)         _cotPage = 1;
  var pagina = lista.slice((_cotPage - 1) * _COT_PER_PAGE, _cotPage * _COT_PER_PAGE);

  var pagerEl = document.getElementById('cot-pager');

  if (!lista.length) {
    document.getElementById('cot-tbody').innerHTML = '<tr><td colspan="7" class="loading-msg">No hay registros</td></tr>';
    if (pagerEl) pagerEl.innerHTML = '';
    return;
  }

  document.getElementById('cot-tbody').innerHTML = pagina.map(function(c) {
    var fecha   = c.fecha        ? new Date(c.fecha+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}) : '&#8212;';
    var entrega = c.fecha_entrega? new Date(c.fecha_entrega+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}) : '&#8212;';
    var total   = c.total ? '$'+parseFloat(c.total).toLocaleString('es-MX',{minimumFractionDigits:2}) : '&#8212;';
    var badgeClass = c.estatus==='cotizacion'?'badge-cot':c.estatus==='orden'?'badge-orden':'badge-canc';
    var badgeLabel = c.estatus==='cotizacion'?'Cotizaci&#243;n':c.estatus==='orden'?'Orden':'Cancelada';
    var folioCell = c.orden_folio
      ? '<span class="cot-folio">'+c.orden_folio+'</span><br><span style="font-size:11px;color:#94a3b8">'+c.folio+'</span>'
      : '<span class="cot-folio">'+(c.folio||'&#8212;')+'</span>';
    return '<tr onclick="irA(\'cotizacion\',{id:\''+c.id+'\'})">'
      +'<td>'+folioCell+'</td>'
      +'<td>'+(c.cliente_nombre||'&#8212;')+'</td>'
      +'<td style="color:#64748b;font-size:12px">'+(c.asesor_nombre||'&#8212;')+'</td>'
      +'<td style="color:#64748b;font-size:12px">'+fecha+'</td>'
      +'<td style="font-size:12px">'+entrega+'</td>'
      +'<td style="font-weight:600">'+total+'</td>'
      +'<td><span class="est-badge '+badgeClass+'">'+badgeLabel+'</span></td>'
      +'</tr>';
  }).join('');

  if (pagerEl) {
    if (totalPags <= 1) {
      pagerEl.innerHTML = '';
    } else {
      pagerEl.innerHTML = '<div class="pager-inner">'
        + '<button class="pager-btn" onclick="cotPaginar(-1)"' + (_cotPage <= 1 ? ' disabled' : '') + '>&#8592; Ant</button>'
        + '<span class="pager-info">P&#225;g. ' + _cotPage + ' / ' + totalPags + ' &nbsp;&middot;&nbsp; ' + lista.length + ' registros</span>'
        + '<button class="pager-btn" onclick="cotPaginar(1)"' + (_cotPage >= totalPags ? ' disabled' : '') + '>Sig &#8594;</button>'
        + '</div>';
    }
  }
}

// ── Autorizaciones pendientes (dir_admin) ─────────────────────────────────────

async function cargarAuthPend() {
  if (!ES_DIR_ADMIN_COTS) return;
  try {
    var res  = await fetch('../api/autorizaciones.php?pendientes=1&t=' + Date.now());
    var data = await res.json();
    var lista = Array.isArray(data) ? data : [];

    var box = document.getElementById('authPendBox');
    var cnt = document.getElementById('authPendCnt');
    if (!box || !cnt) return;

    cnt.textContent = lista.length;
    box.style.display = lista.length > 0 ? '' : 'none';

    var body = document.getElementById('authPendBody');
    if (!body) return;

    if (!lista.length) { body.innerHTML = ''; return; }

    var rows = lista.map(function(a, idx) {
      var fecha = a.fecha_solicitud ? new Date(a.fecha_solicitud).toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}) : '';
      return '<tr>'
        + '<td><a href="#" onclick="irA(\'cotizacion\',{id:' + a.cotizacion_id + '});return false;" style="font-weight:700;color:#2563eb">' + escCots(a.folio_display || a.cot_folio) + '</a>'
        + '<br><span style="font-size:11px;color:#64748b">' + escCots(a.cliente_nombre) + '</span></td>'
        + '<td style="font-weight:700;color:#b45309">' + parseFloat(a.descuento) + '%</td>'
        + '<td style="font-size:12px;color:#374151">' + escCots(a.motivo || '—') + '</td>'
        + '<td style="font-size:12px;color:#64748b">' + escCots(a.solicitado_por) + '<br>' + fecha + '</td>'
        + '<td style="min-width:180px">'
        +   '<textarea class="auth-nota-in" id="apn_' + idx + '" rows="2" placeholder="Nota (requerida al rechazar)..."></textarea>'
        +   '<div style="display:flex;gap:6px">'
        +     '<button class="btn-apr" onclick="ModCotizaciones._resolverAuthLista(' + a.id + ',' + idx + ',\'aprobado\')">&#10003; Aprobar</button>'
        +     '<button class="btn-rec" onclick="ModCotizaciones._resolverAuthLista(' + a.id + ',' + idx + ',\'rechazado\')">&#10005; Rechazar</button>'
        +   '</div>'
        + '</td>'
        + '</tr>';
    }).join('');

    body.innerHTML = '<table class="auth-pend-table">'
      + '<thead><tr><th>Folio / Cliente</th><th>Descuento</th><th>Motivo</th><th>Solicitado por</th><th>Resolución</th></tr></thead>'
      + '<tbody>' + rows + '</tbody>'
      + '</table>';

    if (_authPendOpen) body.classList.add('open');

  } catch(e) {}
}

function toggleAuthPend() {
  _authPendOpen = !_authPendOpen;
  var body  = document.getElementById('authPendBody');
  var arrow = document.getElementById('authPendArrow');
  if (body)  body.classList.toggle('open', _authPendOpen);
  if (arrow) arrow.innerHTML = _authPendOpen ? '&#9650;' : '&#9660;';
}

async function resolverAuthLista(authId, idx, estatus) {
  var nota = (document.getElementById('apn_' + idx) || {}).value || '';
  if (estatus === 'rechazado' && !nota.trim()) {
    alert('Escribe una nota con el motivo del rechazo'); return;
  }
  try {
    var res = await fetch('../api/autorizaciones.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({accion: 'resolver', autorizacion_id: authId, estatus: estatus, nota: nota.trim()})
    });
    var data = await res.json();
    if (data.ok) {
      await cargarAuthPend();
    } else {
      alert(data.error || 'Error');
    }
  } catch(e) { alert('Error de conexión'); }
}

function escCots(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

cotCargar();
cargarAuthPend();

return {
  init:              cotCargar,
  _tab:              cotTab,
  _filtrar:          cotFiltrar,
  _toggleAuthPend:   toggleAuthPend,
  _resolverAuthLista: resolverAuthLista,
};
})();
</script>