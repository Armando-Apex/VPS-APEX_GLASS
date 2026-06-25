<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_wip');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=facturacion'); exit;
}
?>
<style>
.fac-wrap { padding: 24px; max-width: 1100px; }
.fac-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.fac-title { font-size: 18px; font-weight: 600; color: #1a1a1a; flex: 1; }
.fac-wip { font-size: 10px; background: #f59e0b; color: #000; font-weight: 700; padding: 2px 7px; border-radius: 99px; }
.fac-btn-new { background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.fac-btn-new:hover { background: #1d4ed8; }

/* Tabla */
.fac-table-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 24px; }
.fac-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.fac-table th { background: #f8fafc; color: #64748b; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; padding: 10px 14px; text-align: left; border-bottom: 1px solid #e2e8f0; }
.fac-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.fac-table tr:last-child td { border-bottom: none; }
.fac-table tr:hover td { background: #f8fafc; }
.fac-empty { text-align: center; color: #94a3b8; padding: 48px 0; font-size: 13px; }

/* Estatus badges */
.fac-badge { display: inline-block; padding: 2px 9px; border-radius: 99px; font-size: 11px; font-weight: 600; }
.fac-badge.pendiente { background: #fef3c7; color: #92400e; }
.fac-badge.timbrada  { background: #dcfce7; color: #166534; }
.fac-badge.cancelada { background: #fee2e2; color: #991b1b; }

/* Acciones tabla */
.fac-act-btn { background: none; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 10px; font-size: 11px; cursor: pointer; margin-right: 4px; }
.fac-act-btn:hover { background: #f1f5f9; }
.fac-act-btn.danger { color: #dc2626; border-color: #fca5a5; }
.fac-act-btn.danger:hover { background: #fee2e2; }

/* Modal */
.fac-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1500; align-items: center; justify-content: center; }
.fac-overlay.open { display: flex; }
.fac-modal { background: #fff; border-radius: 12px; width: 860px; max-width: calc(100vw - 32px); max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
.fac-modal-head { padding: 20px 24px 16px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
.fac-modal-head h3 { margin: 0; font-size: 16px; font-weight: 600; }
.fac-modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8; line-height: 1; }
.fac-modal-body { padding: 20px 24px; }
.fac-modal-foot { padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; }

/* Form */
.fac-row { display: grid; gap: 12px; margin-bottom: 14px; }
.fac-row.cols2 { grid-template-columns: 1fr 1fr; }
.fac-row.cols3 { grid-template-columns: 1fr 1fr 1fr; }
.fac-field label { display: block; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
.fac-field input, .fac-field select, .fac-field textarea {
    width: 100%; box-sizing: border-box; padding: 8px 10px;
    border: 1px solid #e2e8f0; border-radius: 7px; font-size: 13px;
    outline: none; font-family: inherit;
}
.fac-field input:focus, .fac-field select:focus, .fac-field textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,.12); }
.fac-section-title { font-size: 11px; font-weight: 700; color: #2563eb; text-transform: uppercase; letter-spacing: .05em; margin: 18px 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }

/* Conceptos */
.fac-conceptos { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 8px; }
.fac-conceptos th { background: #f8fafc; color: #64748b; font-size: 10px; text-transform: uppercase; padding: 6px 8px; text-align: left; border: 1px solid #e2e8f0; }
.fac-conceptos td { padding: 4px 6px; border: 1px solid #f1f5f9; }
.fac-conceptos input { border: none; outline: none; width: 100%; font-size: 12px; padding: 3px; background: transparent; }
.fac-conceptos input:focus { background: #f0f9ff; border-radius: 3px; }
.fac-add-concepto { font-size: 11px; color: #2563eb; background: none; border: none; cursor: pointer; padding: 0; margin-top: 4px; }
.fac-add-concepto:hover { text-decoration: underline; }
.fac-del-row { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 14px; padding: 0 4px; }

/* Totales */
.fac-totales { text-align: right; margin-top: 10px; font-size: 13px; }
.fac-totales div { margin-bottom: 3px; }
.fac-totales .total-line { font-weight: 700; font-size: 15px; color: #1a1a1a; margin-top: 6px; padding-top: 6px; border-top: 1px solid #e2e8f0; }

/* Btn modal */
.fac-btn-cancel { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 8px 18px; border-radius: 8px; font-size: 13px; cursor: pointer; }
.fac-btn-save   { background: #2563eb; color: #fff; border: none; padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.fac-btn-save:hover { background: #1d4ed8; }

/* Estatus change modal */
.fac-est-opts { display: flex; gap: 10px; margin-top: 12px; }
.fac-est-btn { flex: 1; border: 2px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; cursor: pointer; font-size: 13px; font-weight: 600; background: #fff; }
.fac-est-btn:hover { border-color: #2563eb; background: #eff6ff; }
.fac-est-btn.active { border-color: #2563eb; background: #eff6ff; }
</style>

<div class="fac-wrap">
  <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400e;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
    <strong>Modo prueba:</strong> Todas las facturas se generan a nombre de <strong>PRUEBA DE PORTAL</strong> (CTN-259). Los datos se guardan solo en este navegador.
  </div>
  <div class="fac-header">
    <span class="fac-title">Facturación <span class="fac-wip">WIP</span></span>
    <button class="fac-btn-new" onclick="ModFacturacion.abrirNueva()">+ Nueva Factura</button>
  </div>

  <div class="fac-table-wrap">
    <table class="fac-table">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Cliente / RFC</th>
          <th>Uso CFDI</th>
          <th>Forma de Pago</th>
          <th>Total</th>
          <th>Estatus</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="fac-tbody">
        <tr><td colspan="7" class="fac-empty">No hay facturas. Crea la primera.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal nueva / editar factura -->
<div class="fac-overlay" id="fac-overlay">
  <div class="fac-modal">
    <div class="fac-modal-head">
      <h3 id="fac-modal-titulo">Nueva Factura</h3>
      <button class="fac-modal-close" onclick="ModFacturacion.cerrarModal()">&times;</button>
    </div>
    <div class="fac-modal-body">
      <input type="hidden" id="fac-edit-id" value="">

      <div class="fac-section-title">Datos Generales</div>
      <div class="fac-row cols2">
        <div class="fac-field">
          <label>Folio</label>
          <input type="text" id="fac-folio" placeholder="F-001" readonly style="background:#f8fafc;color:#64748b">
        </div>
        <div class="fac-field">
          <label>Fecha</label>
          <input type="date" id="fac-fecha">
        </div>
      </div>
      <div class="fac-row cols2">
        <div class="fac-field">
          <label>Nombre / Razón Social</label>
          <input type="text" id="fac-nombre" value="PRUEBA DE PORTAL" readonly style="background:#f8fafc;color:#64748b;cursor:not-allowed">
        </div>
        <div class="fac-field">
          <label>RFC <span style="font-size:10px;color:#94a3b8;font-weight:400;text-transform:none">(cliente de prueba)</span></label>
          <input type="text" id="fac-rfc" value="XAXX010101000" placeholder="XAXX010101000" maxlength="13" style="text-transform:uppercase">
        </div>
      </div>

      <div class="fac-section-title">Datos CFDI</div>
      <div class="fac-row cols2">
        <div class="fac-field">
          <label>Uso CFDI</label>
          <select id="fac-uso-cfdi">
            <option value="G01">G01 – Adquisición de bienes</option>
            <option value="G02">G02 – Devoluciones</option>
            <option value="G03" selected>G03 – Gastos en general</option>
            <option value="I01">I01 – Construcciones</option>
            <option value="D01">D01 – Honorarios médicos</option>
            <option value="S01">S01 – Sin efectos fiscales</option>
            <option value="P01">P01 – Por definir</option>
          </select>
        </div>
        <div class="fac-field">
          <label>Régimen Fiscal</label>
          <select id="fac-regimen">
            <option value="601">601 – General de Ley Personas Morales</option>
            <option value="612" selected>612 – Personas Físicas Actividades Empresariales</option>
            <option value="616">616 – Sin obligaciones fiscales</option>
            <option value="626">626 – Resico</option>
          </select>
        </div>
      </div>
      <div class="fac-row cols2">
        <div class="fac-field">
          <label>Forma de Pago</label>
          <select id="fac-forma-pago">
            <option value="01">01 – Efectivo</option>
            <option value="03" selected>03 – Transferencia electrónica</option>
            <option value="04">04 – Tarjeta de crédito</option>
            <option value="28">28 – Tarjeta de débito</option>
            <option value="99">99 – Por definir</option>
          </select>
        </div>
        <div class="fac-field">
          <label>Método de Pago</label>
          <select id="fac-metodo-pago">
            <option value="PUE" selected>PUE – Pago en una sola exhibición</option>
            <option value="PPD">PPD – Pago en parcialidades o diferido</option>
          </select>
        </div>
      </div>

      <div class="fac-section-title">Conceptos</div>
      <table class="fac-conceptos">
        <thead>
          <tr>
            <th style="width:40%">Descripción</th>
            <th style="width:10%">Cant.</th>
            <th style="width:18%">Precio unit.</th>
            <th style="width:18%">Importe</th>
            <th style="width:8%"></th>
          </tr>
        </thead>
        <tbody id="fac-conceptos-body"></tbody>
      </table>
      <button class="fac-add-concepto" onclick="ModFacturacion.agregarConcepto()">+ Agregar concepto</button>

      <div class="fac-totales">
        <div>Subtotal: <strong id="fac-t-sub">$0.00</strong></div>
        <div>IVA 16%: <strong id="fac-t-iva">$0.00</strong></div>
        <div class="total-line">Total: <strong id="fac-t-total">$0.00</strong></div>
      </div>
    </div>
    <div class="fac-modal-foot">
      <button class="fac-btn-cancel" onclick="ModFacturacion.cerrarModal()">Cancelar</button>
      <button class="fac-btn-save" onclick="ModFacturacion.guardar()">Guardar Factura</button>
    </div>
  </div>
</div>

<!-- Modal cambio estatus -->
<div class="fac-overlay" id="fac-est-overlay">
  <div class="fac-modal" style="width:360px">
    <div class="fac-modal-head">
      <h3>Cambiar Estatus</h3>
      <button class="fac-modal-close" onclick="ModFacturacion.cerrarEstatus()">&times;</button>
    </div>
    <div class="fac-modal-body">
      <div style="font-size:13px;color:#475569">Factura: <strong id="fac-est-folio"></strong></div>
      <div class="fac-est-opts">
        <button class="fac-est-btn" onclick="ModFacturacion.setEstatus('pendiente')">
          <div style="font-size:18px;margin-bottom:4px">🕐</div>
          Pendiente
        </button>
        <button class="fac-est-btn" onclick="ModFacturacion.setEstatus('timbrada')">
          <div style="font-size:18px;margin-bottom:4px">✅</div>
          Timbrada
        </button>
        <button class="fac-est-btn" onclick="ModFacturacion.setEstatus('cancelada')">
          <div style="font-size:18px;margin-bottom:4px">❌</div>
          Cancelada
        </button>
      </div>
    </div>
  </div>
</div>

<script>
var ModFacturacion = (function() {
  var STORAGE_KEY = 'apex_facturas_wip';
  var _editingEstId = null;

  function _load() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; }
    catch(e) { return []; }
  }

  function _save(data) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  }

  function _nextFolio() {
    var data = _load();
    var n = data.length + 1;
    return 'F-' + (n < 10 ? '00' : n < 100 ? '0' : '') + n;
  }

  function _fmt(n) {
    return '$' + parseFloat(n || 0).toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
  }

  function _badgeHtml(est) {
    var labels = {pendiente:'Pendiente', timbrada:'Timbrada', cancelada:'Cancelada'};
    return '<span class="fac-badge ' + est + '">' + (labels[est] || est) + '</span>';
  }

  function _renderTabla() {
    var data = _load();
    var tbody = document.getElementById('fac-tbody');
    if (!tbody) return;
    if (!data.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="fac-empty">No hay facturas. Crea la primera.</td></tr>';
      return;
    }
    var html = '';
    for (var i = 0; i < data.length; i++) {
      var f = data[i];
      var usoCfdi = f.uso_cfdi || '';
      var fPago   = f.forma_pago || '';
      html += '<tr>';
      html += '<td style="font-weight:600;color:#2563eb">' + f.folio + '</td>';
      html += '<td><div style="font-weight:600">' + (f.nombre || '—') + '</div><div style="font-size:11px;color:#94a3b8">' + (f.rfc || '') + '</div></td>';
      html += '<td style="font-size:12px">' + usoCfdi + '</td>';
      html += '<td style="font-size:12px">' + fPago + '</td>';
      html += '<td style="font-weight:600">' + _fmt(f.total) + '</td>';
      html += '<td>' + _badgeHtml(f.estatus) + '</td>';
      html += '<td>';
      html += '<button class="fac-act-btn" onclick="ModFacturacion.abrirEditar(' + f.id + ')">Editar</button>';
      html += '<button class="fac-act-btn" onclick="ModFacturacion.abrirEstatus(' + f.id + ')">Estatus</button>';
      html += '<button class="fac-act-btn danger" onclick="ModFacturacion.eliminar(' + f.id + ')">Eliminar</button>';
      html += '</td>';
      html += '</tr>';
    }
    tbody.innerHTML = html;
  }

  function _conceptoRow(desc, cant, precio) {
    var importe = (parseFloat(cant)||0) * (parseFloat(precio)||0);
    return '<tr>' +
      '<td><input type="text" class="fac-c-desc" value="' + (desc||'') + '" oninput="ModFacturacion.recalc()"></td>' +
      '<td><input type="number" class="fac-c-cant" value="' + (cant||1) + '" min="1" style="width:50px" oninput="ModFacturacion.recalc()"></td>' +
      '<td><input type="number" class="fac-c-precio" value="' + (precio||'') + '" min="0" step="0.01" oninput="ModFacturacion.recalc()"></td>' +
      '<td class="fac-c-imp" style="color:#475569">' + _fmt(importe) + '</td>' +
      '<td><button class="fac-del-row" onclick="this.closest(\'tr\').remove();ModFacturacion.recalc()">&times;</button></td>' +
      '</tr>';
  }

  function _clearForm() {
    document.getElementById('fac-edit-id').value = '';
    document.getElementById('fac-folio').value = _nextFolio();
    document.getElementById('fac-fecha').value = new Date().toISOString().slice(0,10);
    document.getElementById('fac-nombre').value = 'PRUEBA DE PORTAL';
    document.getElementById('fac-rfc').value = 'XAXX010101000';
    document.getElementById('fac-uso-cfdi').value = 'G03';
    document.getElementById('fac-regimen').value = '612';
    document.getElementById('fac-forma-pago').value = '03';
    document.getElementById('fac-metodo-pago').value = 'PUE';
    document.getElementById('fac-conceptos-body').innerHTML = _conceptoRow('', 1, '');
    recalc();
  }

  function _getConceptos() {
    var rows = document.querySelectorAll('#fac-conceptos-body tr');
    var out = [];
    for (var i = 0; i < rows.length; i++) {
      var desc   = rows[i].querySelector('.fac-c-desc').value;
      var cant   = parseFloat(rows[i].querySelector('.fac-c-cant').value) || 0;
      var precio = parseFloat(rows[i].querySelector('.fac-c-precio').value) || 0;
      if (desc || precio) out.push({desc:desc, cant:cant, precio:precio});
    }
    return out;
  }

  function recalc() {
    var rows = document.querySelectorAll('#fac-conceptos-body tr');
    var sub = 0;
    for (var i = 0; i < rows.length; i++) {
      var cant   = parseFloat(rows[i].querySelector('.fac-c-cant').value)   || 0;
      var precio = parseFloat(rows[i].querySelector('.fac-c-precio').value) || 0;
      var imp    = cant * precio;
      sub += imp;
      var cell = rows[i].querySelector('.fac-c-imp');
      if (cell) cell.textContent = _fmt(imp);
    }
    var iva   = sub * 0.16;
    var total = sub + iva;
    document.getElementById('fac-t-sub').textContent   = _fmt(sub);
    document.getElementById('fac-t-iva').textContent   = _fmt(iva);
    document.getElementById('fac-t-total').textContent = _fmt(total);
  }

  function abrirNueva() {
    document.getElementById('fac-modal-titulo').textContent = 'Nueva Factura';
    _clearForm();
    document.getElementById('fac-overlay').classList.add('open');
  }

  function abrirEditar(id) {
    var data = _load();
    var f = null;
    for (var i = 0; i < data.length; i++) { if (data[i].id === id) { f = data[i]; break; } }
    if (!f) return;
    document.getElementById('fac-modal-titulo').textContent = 'Editar Factura';
    document.getElementById('fac-edit-id').value = f.id;
    document.getElementById('fac-folio').value = f.folio;
    document.getElementById('fac-fecha').value = f.fecha || '';
    document.getElementById('fac-nombre').value = f.nombre || '';
    document.getElementById('fac-rfc').value = f.rfc || '';
    document.getElementById('fac-uso-cfdi').value = f.uso_cfdi || 'G03';
    document.getElementById('fac-regimen').value = f.regimen || '612';
    document.getElementById('fac-forma-pago').value = f.forma_pago_cod || '03';
    document.getElementById('fac-metodo-pago').value = f.metodo_pago || 'PUE';
    var tbody = document.getElementById('fac-conceptos-body');
    tbody.innerHTML = '';
    var conceptos = f.conceptos || [];
    for (var j = 0; j < conceptos.length; j++) {
      tbody.innerHTML += _conceptoRow(conceptos[j].desc, conceptos[j].cant, conceptos[j].precio);
    }
    if (!conceptos.length) tbody.innerHTML = _conceptoRow('', 1, '');
    recalc();
    document.getElementById('fac-overlay').classList.add('open');
  }

  function cerrarModal() {
    document.getElementById('fac-overlay').classList.remove('open');
  }

  function guardar() {
    var nombre = 'PRUEBA DE PORTAL';
    var rfc    = document.getElementById('fac-rfc').value.trim().toUpperCase() || 'XAXX010101000';

    var conceptos = _getConceptos();
    var sub   = 0;
    for (var i = 0; i < conceptos.length; i++) sub += conceptos[i].cant * conceptos[i].precio;
    var iva   = sub * 0.16;
    var total = sub + iva;

    var formaPagoCod  = document.getElementById('fac-forma-pago').value;
    var formaPagoText = document.getElementById('fac-forma-pago').options[document.getElementById('fac-forma-pago').selectedIndex].text;

    var data   = _load();
    var editId = document.getElementById('fac-edit-id').value;

    if (editId) {
      for (var j = 0; j < data.length; j++) {
        if (String(data[j].id) === String(editId)) {
          data[j].nombre       = nombre;
          data[j].rfc          = rfc;
          data[j].fecha        = document.getElementById('fac-fecha').value;
          data[j].uso_cfdi     = document.getElementById('fac-uso-cfdi').value;
          data[j].regimen      = document.getElementById('fac-regimen').value;
          data[j].forma_pago   = formaPagoText;
          data[j].forma_pago_cod = formaPagoCod;
          data[j].metodo_pago  = document.getElementById('fac-metodo-pago').value;
          data[j].conceptos    = conceptos;
          data[j].subtotal     = sub;
          data[j].iva          = iva;
          data[j].total        = total;
          break;
        }
      }
    } else {
      data.push({
        id:            Date.now(),
        folio:         document.getElementById('fac-folio').value,
        fecha:         document.getElementById('fac-fecha').value,
        nombre:        nombre,
        rfc:           rfc,
        uso_cfdi:      document.getElementById('fac-uso-cfdi').value,
        regimen:       document.getElementById('fac-regimen').value,
        forma_pago:    formaPagoText,
        forma_pago_cod: formaPagoCod,
        metodo_pago:   document.getElementById('fac-metodo-pago').value,
        conceptos:     conceptos,
        subtotal:      sub,
        iva:           iva,
        total:         total,
        estatus:       'pendiente'
      });
    }

    _save(data);
    cerrarModal();
    _renderTabla();
  }

  function eliminar(id) {
    if (!confirm('¿Eliminar esta factura?')) return;
    var data = _load();
    data = data.filter(function(f) { return f.id !== id; });
    _save(data);
    _renderTabla();
  }

  function abrirEstatus(id) {
    var data = _load();
    var f = null;
    for (var i = 0; i < data.length; i++) { if (data[i].id === id) { f = data[i]; break; } }
    if (!f) return;
    _editingEstId = id;
    document.getElementById('fac-est-folio').textContent = f.folio;
    var btns = document.querySelectorAll('.fac-est-btn');
    for (var j = 0; j < btns.length; j++) btns[j].classList.remove('active');
    document.getElementById('fac-est-overlay').classList.add('open');
  }

  function cerrarEstatus() {
    _editingEstId = null;
    document.getElementById('fac-est-overlay').classList.remove('open');
  }

  function setEstatus(est) {
    if (!_editingEstId) return;
    var data = _load();
    for (var i = 0; i < data.length; i++) {
      if (data[i].id === _editingEstId) { data[i].estatus = est; break; }
    }
    _save(data);
    cerrarEstatus();
    _renderTabla();
  }

  function agregarConcepto() {
    document.getElementById('fac-conceptos-body').innerHTML += _conceptoRow('', 1, '');
    recalc();
  }

  // Init
  _renderTabla();

  return {
    abrirNueva:     abrirNueva,
    abrirEditar:    abrirEditar,
    cerrarModal:    cerrarModal,
    guardar:        guardar,
    eliminar:       eliminar,
    abrirEstatus:   abrirEstatus,
    cerrarEstatus:  cerrarEstatus,
    setEstatus:     setEstatus,
    agregarConcepto: agregarConcepto,
    recalc:         recalc
  };
})();

window.ModFacturacion = ModFacturacion;
</script>
