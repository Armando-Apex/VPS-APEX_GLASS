<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_ordenes');
$_rol = $user['rol'];
$es_finanzas = in_array($_rol, ['administracion','dir_admin','dueno','desarrollo']);
if (!$es_finanzas) {
    echo '<div style="padding:40px;text-align:center;color:#dc2626">Sin permiso para esta sección.</div>'; exit;
}
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=finanzas_vobo'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<style>
.fin-wrap { padding: 24px; max-width: 1300px; margin: 0 auto; }
.page-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #94a3b8; margin-bottom: 20px; }

/* Lista */
.vobo-table { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 24px; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
thead th { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; }
tbody tr { border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background .1s; }
tbody tr:hover { background: #f0f9ff; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 12px 14px; font-size: 13px; }
.dias-badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 99px; }
.dias-ok  { background: #dcfce7; color: #15803d; }
.dias-warn{ background: #fef3c7; color: #b45309; }
.dias-old { background: #fee2e2; color: #dc2626; }

/* Panel detalle */
.det-panel { background: white; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; display: none; }
.det-panel.show { display: block; }
.det-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.det-folio { font-family: 'Syncopate', sans-serif; font-size: 20px; font-weight: 700; background: var(--c-dark-2); color: white; padding: 6px 16px; border-radius: var(--r-sm); }
.det-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; margin-bottom: 20px; }
.det-field { background: #f8fafc; border-radius: 8px; padding: 10px 14px; }
.det-label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
.det-value { font-size: 14px; font-weight: 600; color: #1e293b; }

/* Saldo visual */
.saldo-box { background: #f8fafc; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; gap: 24px; align-items: center; }
.saldo-item { text-align: center; }
.saldo-lbl { font-size: 11px; color: #94a3b8; text-transform: uppercase; font-weight: 700; margin-bottom: 4px; }
.saldo-val { font-size: 18px; font-weight: 800; }
.saldo-total  { color: #1e293b; }
.saldo-pagado { color: #16a34a; }
.saldo-pend   { color: #dc2626; }
.saldo-bar    { flex: 1; background: #e2e8f0; border-radius: 99px; height: 8px; overflow: hidden; }
.saldo-fill   { height: 100%; background: #16a34a; border-radius: 99px; transition: width .4s; }

/* Pagos */
.pagos-titulo { font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
.pago-row { display: flex; align-items: center; gap: 12px; padding: 8px 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 6px; font-size: 13px; }
.pago-fecha { color: #64748b; font-size: 12px; min-width: 140px; }
.pago-forma { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.forma-efectivo     { background: #dcfce7; color: #15803d; }
.forma-tarjeta      { background: #dbeafe; color: #1d4ed8; }
.forma-transferencia{ background: #f3e8ff; color: #7c3aed; }
.forma-saldo-favor  { background: #fef9c3; color: #78350f; }
.pago-monto { font-weight: 700; color: #1e293b; margin-left: auto; }

/* Formulario pago */
.pago-form { background: var(--c-blue-light); border: 1px solid #bae6fd; border-radius: var(--r-sm); padding: 16px; margin-bottom: 20px; }
.pago-form-title { font-size: 13px; font-weight: 700; color: #0369a1; margin-bottom: 12px; }
.pago-form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px; }
.pf-field { display: flex; flex-direction: column; gap: 4px; }
.pf-field label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.pf-field input, .pf-field select { padding: 8px 10px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 13px; }
.pf-field input:focus, .pf-field select:focus { outline: none; border-color: #0369a1; }

/* VoBo */
.vobo-box { background: var(--c-green-light); border: 2px solid #bbf7d0; border-radius: var(--r-md); padding: 20px; }
.vobo-box-title { font-size: 14px; font-weight: 700; color: #15803d; margin-bottom: 14px; }
.vobo-fecha-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
.vf-field { display: flex; flex-direction: column; gap: 4px; }
.vf-field label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.vf-field input { padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
.vf-field input:focus { outline: none; border-color: #16a34a; }

/* Botones */
.btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn-success { background: var(--c-green); color: white; font-size: 14px; padding: 10px 20px; }
.btn-blue    { background: #2563eb; color: white; }
.btn-ghost   { background: #f1f5f9; color: #374151; }
.btn-sm { padding: 7px 14px; font-size: 12px; }
.loading-msg { text-align: center; padding: 48px; color: #94a3b8; font-size: 14px; }
.empty-msg   { text-align: center; padding: 48px; color: #94a3b8; }
</style>

<div class="fin-wrap">
  <div class="page-title">&#9989; VoBo de Órdenes</div>
  <div class="page-sub" id="vobo-sub">Cargando...</div>

  <div class="vobo-table" id="vobo-lista-wrap">
    <table>
      <thead><tr>
        <th>Folio Orden</th>
        <th>Cliente</th>
        <th>Asesor</th>
        <th>Fecha pedido</th>
        <th>Días en espera</th>
        <th>Total</th>
        <th>Saldo pagado</th>
        <th>Condición</th>
      </tr></thead>
      <tbody id="vobo-tbody">
        <tr><td colspan="8" class="loading-msg">Cargando...</td></tr>
      </tbody>
    </table>
  </div>

  <div class="det-panel" id="det-panel">
    <!-- Se rellena dinámicamente -->
  </div>
</div>

<script>
var ModFinanzasVobo = (function() {

var API = '../api/finanzas.php';
var _lista = [];
var _detalle = null;
var _saldoFavor = 0;

// ── Cargar lista ──────────────────────────────────────────────
async function cargarLista() {
  try {
    var res  = await fetch(API + '?accion=lista_vobo&t=' + Date.now());
    var data = await res.json();
    _lista = Array.isArray(data) ? data : [];
    renderLista();
    document.getElementById('vobo-sub').textContent =
      _lista.length + ' orden(es) pendiente(s) de VoBo — ' +
      new Date().toLocaleTimeString('es-MX', {hour:'2-digit',minute:'2-digit'});
  } catch(e) {
    document.getElementById('vobo-sub').textContent = 'Error al cargar';
  }
}

function renderLista() {
  var tbody = document.getElementById('vobo-tbody');
  if (!_lista.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="empty-msg">&#127881; No hay órdenes pendientes de VoBo</td></tr>';
    return;
  }
  tbody.innerHTML = _lista.map(function(o) {
    var hoy   = new Date();
    var fped  = new Date(o.fecha_pedido + 'T12:00:00');
    var dias  = Math.floor((hoy - fped) / 86400000);
    var badgeClass = dias <= 1 ? 'dias-ok' : dias <= 3 ? 'dias-warn' : 'dias-old';
    var total = o.total ? '$' + parseFloat(o.total).toLocaleString('es-MX',{minimumFractionDigits:2}) : '—';
    var pagado = o.saldo_pagado ? '$' + parseFloat(o.saldo_pagado).toLocaleString('es-MX',{minimumFractionDigits:2}) : '$0.00';
    var condLabel = o.condicion_pago === 'pago_total' ? 'Pago Total' : '50% Anticipo';
    var fechaLabel = fped.toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'});
    return '<tr onclick="ModFinanzasVobo._abrirDetalle(' + o.id + ')">'
      + '<td style="font-weight:700;color:#2563eb">' + escHtml(o.folio) + '</td>'
      + '<td>' + escHtml(o.cliente_nombre) + '</td>'
      + '<td style="color:#64748b;font-size:12px">' + escHtml(o.asesor||'—') + '</td>'
      + '<td style="font-size:12px;color:#64748b">' + fechaLabel + '</td>'
      + '<td><span class="dias-badge ' + badgeClass + '">' + dias + ' día(s)</span></td>'
      + '<td style="font-weight:700">' + total + '</td>'
      + '<td style="color:#16a34a;font-weight:600">' + pagado + '</td>'
      + '<td style="font-size:12px">' + condLabel + '</td>'
      + '</tr>';
  }).join('');
}

// ── Abrir detalle de una orden ────────────────────────────────
async function abrirDetalle(orden_id) {
  var panel = document.getElementById('det-panel');
  panel.innerHTML = '<div class="loading-msg">Cargando detalle...</div>';
  panel.classList.add('show');
  panel.scrollIntoView({behavior:'smooth', block:'start'});

  var res  = await fetch(API + '?accion=detalle&orden_id=' + orden_id);
  _detalle = await res.json();
  _saldoFavor = 0;
  if (_detalle && _detalle.cliente_id) {
    try {
      var rs = await fetch('../api/saldo_favor.php?accion=saldo&cliente_id=' + _detalle.cliente_id);
      var sd = await rs.json();
      _saldoFavor = parseFloat(sd.saldo || 0);
    } catch(e) { _saldoFavor = 0; }
  }
  renderDetalle();
}

function renderDetalle() {
  var o = _detalle;
  if (!o || o.error) {
    document.getElementById('det-panel').innerHTML = '<div style="color:#dc2626;padding:20px">Error: ' + escHtml((o&&o.error)||'desconocido') + '</div>';
    return;
  }

  var total     = parseFloat(o.total || 0);
  var pagado    = parseFloat(o.saldo_pagado || 0);
  var pendiente = Math.max(0, total - pagado);
  var pct       = total > 0 ? Math.min(100, Math.round(pagado / total * 100)) : 0;

  var fmtMoney = function(n) { return '$' + parseFloat(n).toLocaleString('es-MX',{minimumFractionDigits:2}); };
  var fmtFecha = function(f) { return f ? new Date(f+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}) : '—'; };

  var html = '';

  // Header
  html += '<div class="det-header">';
  html += '<div>';
  html += '<div class="det-folio">' + escHtml(o.folio) + '</div>';
  html += '<div style="font-size:12px;color:#94a3b8;margin-top:6px">Cotización: ' + escHtml(o.cot_folio||'—') + '</div>';
  html += '</div>';
  html += '<button class="btn btn-ghost btn-sm" onclick="ModFinanzasVobo._cerrarDetalle()">&#8592; Volver a lista</button>';
  html += '</div>';

  // Datos generales
  html += '<div class="det-grid">';
  html += '<div class="det-field"><div class="det-label">Cliente</div><div class="det-value">' + escHtml(o.cliente_nombre) + '</div></div>';
  html += '<div class="det-field"><div class="det-label">Asesor</div><div class="det-value">' + escHtml(o.asesor||'—') + '</div></div>';
  html += '<div class="det-field"><div class="det-label">Fecha pedido</div><div class="det-value">' + fmtFecha(o.fecha_pedido) + '</div></div>';
  html += '<div class="det-field"><div class="det-label">Localidad</div><div class="det-value">' + escHtml(o.localidad||'LOCAL') + (o.ciudad_destino?' — '+escHtml(o.ciudad_destino):'') + '</div></div>';
  html += '<div class="det-field"><div class="det-label">Tipo entrega</div><div class="det-value">' + (o.tipo_entrega==='planta'?'Recoge en planta':'A domicilio') + '</div></div>';
  html += '<div class="det-field"><div class="det-label">Condición pago</div><div class="det-value">' + (o.condicion_pago==='pago_total'?'Pago Total':'50% Anticipo') + '</div></div>';
  html += '</div>';

  // Saldo visual
  html += '<div class="saldo-box">';
  html += '<div class="saldo-item"><div class="saldo-lbl">Total</div><div class="saldo-val saldo-total">' + fmtMoney(total) + '</div></div>';
  html += '<div class="saldo-item"><div class="saldo-lbl">Pagado</div><div class="saldo-val saldo-pagado">' + fmtMoney(pagado) + '</div></div>';
  html += '<div class="saldo-item"><div class="saldo-lbl">Pendiente</div><div class="saldo-val saldo-pend">' + fmtMoney(pendiente) + '</div></div>';
  html += '<div class="saldo-bar"><div class="saldo-fill" id="saldo-fill" style="width:' + pct + '%"></div></div>';
  html += '<div style="font-size:13px;font-weight:700;color:#64748b">' + pct + '%</div>';
  html += '</div>';

  // Pagos registrados
  html += '<div class="pagos-titulo">&#128184; Pagos registrados';
  html += ' <span style="font-size:11px;font-weight:400;color:#94a3b8">(' + (o.pagos||[]).length + ')</span></div>';

  if (o.pagos && o.pagos.length) {
    for (var i = 0; i < o.pagos.length; i++) {
      var p = o.pagos[i];
      var formaLabels = {efectivo:'Efectivo', tarjeta:'Tarjeta', transferencia:'Transferencia', saldo_favor:'Saldo a Favor'};
      var formaClass = 'forma-' + p.forma_pago.replace('_','-');
      var formaLabel = formaLabels[p.forma_pago] || (p.forma_pago.charAt(0).toUpperCase() + p.forma_pago.slice(1));
      html += '<div class="pago-row">';
      html += '<div class="pago-fecha">' + p.fecha_pago + ' ' + (p.hora_pago||'').substring(0,5) + '</div>';
      html += '<span class="pago-forma ' + formaClass + '">' + formaLabel + '</span>';
      if (p.notas) html += '<span style="font-size:12px;color:#64748b">' + escHtml(p.notas) + '</span>';
      html += '<div class="pago-monto">' + fmtMoney(p.monto) + '</div>';
      html += '<div style="font-size:11px;color:#94a3b8">por ' + escHtml(p.registrado_por||'—') + '</div>';
      html += '</div>';
    }
  } else {
    html += '<div style="font-size:13px;color:#94a3b8;padding:8px 0">Sin pagos registrados aún</div>';
  }

  // Banner saldo a favor
  if (_saldoFavor > 0) {
    html += '<div style="background:#fef9c3;border:1.5px solid #fbbf24;border-radius:8px;padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:10px;font-size:13px">';
    html += '<span style="font-size:18px">&#128176;</span>';
    html += '<span>Este cliente tiene <strong style="color:#92400e">$' + _saldoFavor.toLocaleString('es-MX',{minimumFractionDigits:2}) + '</strong> de Saldo a Favor disponible.</span>';
    html += '</div>';
  }

  // Formulario registrar pago
  var sfLabel = _saldoFavor > 0
    ? 'Saldo a Favor (Disp: $' + _saldoFavor.toLocaleString('es-MX',{minimumFractionDigits:2}) + ')'
    : 'Saldo a Favor';
  html += '<div class="pago-form" style="margin-top:8px">';
  html += '<div class="pago-form-title">&#43; Registrar pago</div>';
  html += '<div class="pago-form-grid">';
  html += '<div class="pf-field"><label>Fecha</label><input type="date" id="pf-fecha" value="' + new Date().toISOString().substring(0,10) + '"></div>';
  html += '<div class="pf-field"><label>Hora</label><input type="time" id="pf-hora" value="' + new Date().toTimeString().substring(0,5) + '"></div>';
  html += '<div class="pf-field"><label>Monto $</label><input type="number" id="pf-monto" min="0" step="0.01" placeholder="0.00"></div>';
  html += '<div class="pf-field"><label>Forma de pago</label>';
  html += '<select id="pf-forma" onchange="ModFinanzasVobo._onFormaChange(' + parseFloat(pendiente).toFixed(2) + ')">';
  html += '<option value="efectivo">Efectivo</option>';
  html += '<option value="tarjeta">Tarjeta</option>';
  html += '<option value="transferencia">Transferencia</option>';
  html += '<option value="saldo_favor">' + escHtml(sfLabel) + '</option>';
  html += '</select></div>';
  html += '</div>';
  html += '<div style="display:flex;gap:10px;align-items:center">';
  html += '<input type="text" id="pf-notas" placeholder="Notas (opcional)" style="flex:1;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px">';
  html += '<button class="btn btn-blue btn-sm" onclick="ModFinanzasVobo._registrarPago(' + o.cot_id + ',' + o.id + ')">Registrar pago</button>';
  html += '</div>';
  html += '</div>';

  // VoBo
  html += '<div class="vobo-box">';
  html += '<div class="vobo-box-title">&#9989; Autorizar VoBo — Dar entrada a producción</div>';
  html += '<div class="vobo-fecha-grid">';
  html += '<div class="vf-field"><label>Fecha de entrega calculada</label>';
  html += '<input type="date" id="vobo-fecha" value="' + (o.fecha_vobo_sugerida||'') + '">';
  html += '<div style="font-size:11px;color:#64748b;margin-top:4px">Calculada al día de hoy. Puedes ajustarla.</div>';
  html += '</div>';
  html += '<div class="vf-field"><label>VoBo autorizado por</label>';
  html += '<input type="text" readonly value="<?= htmlspecialchars($user['nombre']) ?>" style="background:#f8fafc;color:#64748b">';
  html += '</div>';
  html += '</div>';
  html += '<button class="btn btn-success" onclick="ModFinanzasVobo._darVobo(' + o.id + ')">&#9989; Confirmar VoBo — Pasar a Producción</button>';
  html += '</div>';

  document.getElementById('det-panel').innerHTML = html;
}

// ── Auto-rellenar monto al elegir Saldo a Favor ───────────────
function onFormaChange(pendiente) {
  var forma = document.getElementById('pf-forma').value;
  if (forma === 'saldo_favor' && _saldoFavor > 0) {
    var auto = Math.min(_saldoFavor, pendiente);
    document.getElementById('pf-monto').value = auto.toFixed(2);
  }
}

// ── Registrar pago ────────────────────────────────────────────
async function registrarPago(cot_id, orden_id) {
  var fecha = document.getElementById('pf-fecha')?.value;
  var hora  = document.getElementById('pf-hora')?.value + ':00';
  var monto = parseFloat(document.getElementById('pf-monto')?.value || 0);
  var forma = document.getElementById('pf-forma')?.value;
  var notas = document.getElementById('pf-notas')?.value || '';

  if (!monto || monto <= 0) { alert('Ingresa un monto válido'); return; }
  if (forma === 'saldo_favor' && monto > _saldoFavor) {
    alert('El monto excede el Saldo a Favor disponible ($' + _saldoFavor.toLocaleString('es-MX',{minimumFractionDigits:2}) + ')');
    return;
  }

  var payload = { accion:'registrar_pago', cotizacion_id: cot_id, fecha_pago: fecha, hora_pago: hora, monto: monto, forma_pago: forma, notas: notas };

  var res  = await fetch(API, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  var data = await res.json();
  if (data.ok) {
    // Recargar detalle para mostrar el nuevo pago
    await abrirDetalle(orden_id);
    if (data.excedente) {
      alert('Pago registrado.\n\nEl excedente de $' + parseFloat(data.excedente).toLocaleString('es-MX',{minimumFractionDigits:2}) + ' fue abonado al saldo a favor del cliente.');
    }
  } else {
    alert(data.error || 'Error al registrar pago');
  }
}

// ── Dar VoBo ──────────────────────────────────────────────────
async function darVobo(orden_id) {
  var fecha = document.getElementById('vobo-fecha')?.value;
  if (!fecha) { alert('Selecciona una fecha de entrega'); return; }
  if (!confirm('¿Confirmar VoBo y pasar esta orden a producción?\n\nFecha de entrega: ' + fecha)) return;

  var res  = await fetch(API, {
    method: 'PUT',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ accion:'vobo', orden_id: orden_id, fecha_entrega: fecha })
  });
  var data = await res.json();
  if (data.ok) {
    alert('✅ VoBo registrado por ' + data.vobo_por + '\nFecha de entrega: ' + data.fecha_entrega);
    cerrarDetalle();
    cargarLista();
  } else {
    alert(data.error || 'Error al dar VoBo');
  }
}

function cerrarDetalle() {
  var panel = document.getElementById('det-panel');
  panel.classList.remove('show');
  panel.innerHTML = '';
  _detalle = null;
  window.scrollTo({top:0, behavior:'smooth'});
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

cargarLista();

return {
  init:            cargarLista,
  _abrirDetalle:   abrirDetalle,
  _cerrarDetalle:  cerrarDetalle,
  _registrarPago:  registrarPago,
  _darVobo:        darVobo,
  _onFormaChange:  onFormaChange,
};
})();
</script>