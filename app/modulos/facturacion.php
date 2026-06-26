<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_wip');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=facturacion'); exit;
}
?>
<style>
.fac-wrap { padding: 24px; max-width: 1200px; }
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

/* Badges */
.fac-badge { display: inline-block; padding: 2px 9px; border-radius: 99px; font-size: 11px; font-weight: 600; }
.fac-badge.pendiente { background: #fef3c7; color: #92400e; }
.fac-badge.timbrada  { background: #dcfce7; color: #166534; }
.fac-badge.cancelada { background: #fee2e2; color: #991b1b; }

/* Acciones */
.fac-act-btn { background: none; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 10px; font-size: 11px; cursor: pointer; margin-right: 4px; }
.fac-act-btn:hover { background: #f1f5f9; }
.fac-act-btn.danger { color: #dc2626; border-color: #fca5a5; }
.fac-act-btn.danger:hover { background: #fee2e2; }

/* Modal */
.fac-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1500; align-items: center; justify-content: center; }
.fac-overlay.open { display: flex; }
.fac-modal { background: #fff; border-radius: 12px; width: 1020px; max-width: calc(100vw - 32px); max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
.fac-modal-head { padding: 20px 24px 16px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: #fff; z-index: 1; }
.fac-modal-head h3 { margin: 0; font-size: 16px; font-weight: 600; }
.fac-modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: #94a3b8; line-height: 1; }
.fac-modal-body { padding: 20px 24px; }
.fac-modal-foot { padding: 16px 24px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 8px; position: sticky; bottom: 0; background: #fff; }

/* Form */
.fac-row { display: grid; gap: 12px; margin-bottom: 14px; }
.fac-row.cols2 { grid-template-columns: 1fr 1fr; }
.fac-row.cols3 { grid-template-columns: 1fr 1fr 1fr; }
.fac-row.cols4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
.fac-field label { display: block; font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
.fac-field input, .fac-field select {
    width: 100%; box-sizing: border-box; padding: 8px 10px;
    border: 1px solid #e2e8f0; border-radius: 7px; font-size: 13px;
    outline: none; font-family: inherit;
}
.fac-field input:focus, .fac-field select:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,.12); }
.fac-hint { font-size: 10px; color: #94a3b8; margin-top: 3px; }
.fac-section-title { font-size: 11px; font-weight: 700; color: #2563eb; text-transform: uppercase; letter-spacing: .05em; margin: 20px 0 12px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }

/* Conceptos */
.fac-conceptos { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 8px; }
.fac-conceptos th { background: #f8fafc; color: #64748b; font-size: 10px; text-transform: uppercase; padding: 7px 8px; text-align: left; border: 1px solid #e2e8f0; white-space: nowrap; }
.fac-conceptos td { padding: 4px 5px; border: 1px solid #f1f5f9; vertical-align: middle; }
.fac-conceptos input, .fac-conceptos select { border: none; outline: none; width: 100%; font-size: 12px; padding: 4px; background: transparent; font-family: inherit; }
.fac-conceptos input:focus, .fac-conceptos select:focus { background: #f0f9ff; border-radius: 3px; }

/* Custom dropdown clave SAT */
.fac-csat { position: relative; }
.fac-csat-display { padding: 4px 20px 4px 4px; font-size: 12px; cursor: pointer; font-family: monospace; font-weight: 600; color: #1e293b; white-space: nowrap; position: relative; min-width: 80px; }
.fac-csat-display::after { content: '▾'; position: absolute; right: 4px; top: 50%; transform: translateY(-50%); font-size: 10px; color: #94a3b8; }
.fac-csat-display:hover { background: #f0f9ff; border-radius: 3px; }
.fac-csat-list { display: none; position: absolute; top: 100%; left: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); z-index: 2000; min-width: 280px; padding: 4px 0; }
.fac-csat-list.open { display: block; }
.fac-csat-opt { padding: 8px 12px; font-size: 12px; cursor: pointer; white-space: nowrap; }
.fac-csat-opt:hover { background: #eff6ff; }
.fac-csat-opt .fac-csat-cod { font-family: monospace; font-weight: 700; color: #2563eb; margin-right: 6px; }
.fac-csat-opt .fac-csat-desc { color: #475569; }
.fac-add-concepto { font-size: 11px; color: #2563eb; background: none; border: none; cursor: pointer; padding: 0; margin-top: 6px; }
.fac-add-concepto:hover { text-decoration: underline; }
.fac-del-row { background: none; border: none; color: #dc2626; cursor: pointer; font-size: 14px; padding: 0 4px; }

/* Totales */
.fac-totales { text-align: right; margin-top: 12px; font-size: 13px; }
.fac-totales div { margin-bottom: 4px; color: #475569; }
.fac-totales .total-line { font-weight: 700; font-size: 15px; color: #1a1a1a; margin-top: 8px; padding-top: 8px; border-top: 2px solid #e2e8f0; }

/* UUID box */
.fac-uuid-box { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 10px 14px; margin-top: 12px; font-size: 12px; }
.fac-uuid-box label { font-size: 10px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 4px; }
.fac-uuid-box input { width: 100%; box-sizing: border-box; border: 1px solid #86efac; border-radius: 6px; padding: 6px 10px; font-size: 12px; font-family: monospace; background: #fff; outline: none; }

/* Botones modal */
.fac-btn-cancel { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; padding: 8px 18px; border-radius: 8px; font-size: 13px; cursor: pointer; }
.fac-btn-save   { background: #2563eb; color: #fff; border: none; padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.fac-btn-save:hover { background: #1d4ed8; }

/* Modal estatus */
.fac-est-opts { display: flex; gap: 10px; margin-top: 14px; }
.fac-est-btn { flex: 1; border: 2px solid #e2e8f0; border-radius: 8px; padding: 14px 8px; text-align: center; cursor: pointer; font-size: 13px; font-weight: 600; background: #fff; transition: all .15s; }
.fac-est-btn:hover { border-color: #2563eb; background: #eff6ff; }
.fac-est-btn.sel-pendiente { border-color: #f59e0b; background: #fefce8; }
.fac-est-btn.sel-timbrada  { border-color: #22c55e; background: #f0fdf4; }
.fac-est-btn.sel-cancelada { border-color: #ef4444; background: #fef2f2; }

/* Motivo cancelación */
.fac-motivo-wrap { margin-top: 16px; display: none; }
.fac-motivo-wrap.visible { display: block; }
.fac-motivo-wrap label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 6px; }
.fac-motivo-wrap select { width: 100%; padding: 8px 10px; border: 1px solid #fca5a5; border-radius: 7px; font-size: 13px; outline: none; font-family: inherit; }
.fac-motivo-wrap select:focus { border-color: #ef4444; box-shadow: 0 0 0 2px rgba(239,68,68,.12); }
</style>

<div class="fac-wrap">
  <div style="background:#fef3c7;border:1px solid #fbbf24;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400e;margin-bottom:16px;display:flex;align-items:center;gap:8px;">
    <strong>Modo prueba:</strong> Facturas a nombre de <strong>PRUEBA DE PORTAL</strong> (CTN-259). Datos guardados solo en este navegador.
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

<!-- ── Modal nueva / editar factura ── -->
<div class="fac-overlay" id="fac-overlay">
  <div class="fac-modal">
    <div class="fac-modal-head">
      <h3 id="fac-modal-titulo">Nueva Factura</h3>
      <button class="fac-modal-close" onclick="ModFacturacion.cerrarModal()">&times;</button>
    </div>
    <div class="fac-modal-body">
      <input type="hidden" id="fac-edit-id" value="">

      <!-- Datos generales -->
      <div class="fac-section-title">Datos Generales</div>
      <div class="fac-row cols4">
        <div class="fac-field">
          <label>Folio interno</label>
          <input type="text" id="fac-folio" readonly style="background:#f8fafc;color:#64748b">
        </div>
        <div class="fac-field">
          <label>Serie CFDI</label>
          <input type="text" id="fac-serie" value="A" maxlength="10">
          <div class="fac-hint">Letra(s) que identifica la serie de folios en el SAT</div>
        </div>
        <div class="fac-field">
          <label>Moneda</label>
          <input type="text" id="fac-moneda" value="MXN" readonly style="background:#f8fafc;color:#64748b">
        </div>
        <div class="fac-field">
          <label>Fecha</label>
          <input type="date" id="fac-fecha">
        </div>
      </div>

      <!-- Receptor -->
      <div class="fac-section-title">Receptor (Cliente)</div>
      <div class="fac-row cols2">
        <div class="fac-field">
          <label>Nombre / Razón Social</label>
          <input type="text" id="fac-nombre" value="PRUEBA DE PORTAL" readonly style="background:#f8fafc;color:#64748b;cursor:not-allowed">
        </div>
        <div class="fac-field">
          <label>Correo electrónico</label>
          <input type="email" id="fac-email" placeholder="cliente@empresa.com">
          <div class="fac-hint">Facturama / FacturAPI envía el XML+PDF a este correo al timbrar</div>
        </div>
      </div>
      <div class="fac-row cols3">
        <div class="fac-field">
          <label>RFC <span style="font-weight:400;text-transform:none;font-size:10px;color:#94a3b8">(prueba)</span></label>
          <input type="text" id="fac-rfc" value="XAXX010101000" maxlength="13" style="text-transform:uppercase">
          <div class="fac-hint">CFDI 4.0: debe coincidir exactamente con el SAT</div>
        </div>
        <div class="fac-field">
          <label>CP Fiscal del receptor</label>
          <input type="text" id="fac-cp" placeholder="64000" maxlength="5">
          <div class="fac-hint">Código postal del domicilio fiscal</div>
        </div>
        <div class="fac-field">
          <label>Tipo de comprobante</label>
          <input type="text" value="I – Ingreso" readonly style="background:#f8fafc;color:#64748b">
          <div class="fac-hint">Fijo para facturas de venta</div>
        </div>
      </div>

      <!-- CFDI -->
      <div class="fac-section-title">Datos CFDI</div>
      <div class="fac-row cols2">
        <div class="fac-field">
          <label>Uso CFDI</label>
          <select id="fac-uso-cfdi" required>
            <option value="" selected disabled>— Selecciona uso CFDI —</option>
            <optgroup label="Gastos e inversiones">
              <option value="G01">G01 – Adquisición de mercancias</option>
              <option value="G02">G02 – Devoluciones, descuentos o bonificaciones</option>
              <option value="G03">G03 – Gastos en general</option>
            </optgroup>
            <optgroup label="Inversiones">
              <option value="I01">I01 – Construcciones</option>
              <option value="I02">I02 – Mobiliario y equipo de oficina</option>
              <option value="I03">I03 – Equipo de transporte</option>
              <option value="I04">I04 – Equipo de cómputo y accesorios</option>
              <option value="I08">I08 – Otra maquinaria y equipo</option>
            </optgroup>
            <optgroup label="Especiales">
              <option value="CP01">CP01 – Pagos</option>
              <option value="S01">S01 – Sin efectos fiscales</option>
              <option value="P01">P01 – Por definir</option>
            </optgroup>
          </select>
        </div>
        <div class="fac-field">
          <label>Régimen Fiscal del receptor</label>
          <select id="fac-regimen" required>
            <option value="" selected disabled>— Selecciona régimen fiscal —</option>
            <option value="601">601 – General de Ley Personas Morales</option>
            <option value="603">603 – Personas Morales sin Fines de Lucro</option>
            <option value="606">606 – Arrendamiento</option>
            <option value="612" selected>612 – Pers. Físicas Actividades Empresariales</option>
            <option value="616">616 – Sin obligaciones fiscales</option>
            <option value="621">621 – Incorporación Fiscal</option>
            <option value="625">625 – Plataformas Tecnológicas</option>
            <option value="626">626 – Resico</option>
          </select>
        </div>
      </div>
      <div class="fac-row cols2">
        <div class="fac-field">
          <label>Forma de Pago</label>
          <select id="fac-forma-pago" required>
            <option value="" selected disabled>— Selecciona forma de pago —</option>
            <option value="01">01 – Efectivo</option>
            <option value="03">03 – Transferencia electrónica</option>
            <option value="04">04 – Tarjeta de crédito</option>
            <option value="28">28 – Tarjeta de débito</option>
            <option value="99">99 – Por definir</option>
          </select>
        </div>
        <div class="fac-field">
          <label>Método de Pago</label>
          <select id="fac-metodo-pago" required>
            <option value="" selected disabled>— Selecciona método de pago —</option>
            <option value="PUE">PUE – Pago en una sola exhibición</option>
            <option value="PPD">PPD – Pago en parcialidades o diferido</option>
          </select>
        </div>
      </div>

      <!-- Conceptos -->
      <div class="fac-section-title">Conceptos</div>
      <div style="background:#fef2f2;border:1px solid #fca5a5;border-radius:7px;padding:8px 12px;font-size:11px;color:#991b1b;margin-bottom:10px;">
        ⚠️ <strong>Las claves SAT y unidades son de ejemplo — NO están verificadas con el SAT.</strong> Antes de timbrar, el contador debe confirmar la clave correcta para cada producto/servicio.
      </div>
      <table class="fac-conceptos">
        <thead>
          <tr>
            <th style="width:30%">Descripción</th>
            <th style="width:11%">Clave SAT <span style="color:#ef4444;font-weight:700">*</span></th>
            <th style="width:7%">Unidad</th>
            <th style="width:6%">Cant.</th>
            <th style="width:13%">Precio unit.</th>
            <th style="width:6%">IVA</th>
            <th style="width:13%">Importe</th>
            <th style="width:4%"></th>
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

<!-- ── Modal cambio de estatus ── -->
<div class="fac-overlay" id="fac-est-overlay">
  <div class="fac-modal" style="width:420px">
    <div class="fac-modal-head">
      <h3>Cambiar Estatus</h3>
      <button class="fac-modal-close" onclick="ModFacturacion.cerrarEstatus()">&times;</button>
    </div>
    <div class="fac-modal-body">
      <div style="font-size:13px;color:#475569;margin-bottom:4px">Factura: <strong id="fac-est-folio"></strong></div>

      <div class="fac-est-opts">
        <button class="fac-est-btn" id="fac-ebtn-pendiente" onclick="ModFacturacion.selEstatus('pendiente')">
          <div style="font-size:20px;margin-bottom:6px">🕐</div>
          Pendiente
        </button>
        <button class="fac-est-btn" id="fac-ebtn-timbrada" onclick="ModFacturacion.selEstatus('timbrada')">
          <div style="font-size:20px;margin-bottom:6px">✅</div>
          Timbrada
        </button>
        <button class="fac-est-btn" id="fac-ebtn-cancelada" onclick="ModFacturacion.selEstatus('cancelada')">
          <div style="font-size:20px;margin-bottom:6px">❌</div>
          Cancelada
        </button>
      </div>

      <!-- UUID — solo aparece al seleccionar Timbrada -->
      <div id="fac-uuid-wrap" class="fac-uuid-box" style="display:none">
        <label>UUID / Folio Fiscal SAT</label>
        <input type="text" id="fac-uuid" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" maxlength="36" style="font-family:monospace">
        <div class="fac-hint" style="color:#166534;margin-top:4px">El UUID lo entrega el PAC (Facturama / FacturAPI) al timbrar</div>
      </div>

      <!-- Motivo cancelación — solo aparece al seleccionar Cancelada -->
      <div id="fac-motivo-wrap" class="fac-motivo-wrap">
        <label>Motivo de cancelación (SAT)</label>
        <select id="fac-motivo-cancel">
          <option value="01">01 – Error en la factura, se sustituye por otra</option>
          <option value="02">02 – Error en la factura, sin sustitución</option>
          <option value="03">03 – No se realizó la operación</option>
          <option value="04">04 – Operación nominativa en factura global</option>
        </select>
        <div class="fac-hint" style="margin-top:4px;color:#dc2626">La cancelación >$1,000 MXN o después de 72 hrs requiere aceptación del cliente en su buzón SAT</div>
      </div>
    </div>
    <div class="fac-modal-foot">
      <button class="fac-btn-cancel" onclick="ModFacturacion.cerrarEstatus()">Cancelar</button>
      <button class="fac-btn-save" onclick="ModFacturacion.confirmarEstatus()">Aplicar</button>
    </div>
  </div>
</div>

<script>
var ModFacturacion = (function() {
  var STORAGE_KEY = 'apex_facturas_wip';
  var _editingEstId = null;
  var _estatusSel   = null;

  var CLAVES_SAT = [
    {v:'44111702', l:'44111702 – Vidrio templado'},
    {v:'44111701', l:'44111701 – Vidrio laminado'},
    {v:'44111700', l:'44111700 – Vidrio de seguridad'},
    {v:'44111500', l:'44111500 – Vidrio plano'},
    {v:'30171500', l:'30171500 – Mat. construcción'},
    {v:'72154100', l:'72154100 – Instalación vidrio'},
    {v:'78181500', l:'78181500 – Transporte'},
    {v:'84111506', l:'84111506 – Serv. profesional'},
  ];

  var UNIDADES_SAT = [
    {v:'M2',  l:'M2 – Metro cuadrado'},
    {v:'PZA', l:'PZA – Pieza'},
    {v:'MTR', l:'MTR – Metro lineal'},
    {v:'H87', l:'H87 – Pieza (SAT)'},
    {v:'E48', l:'E48 – Unidad servicio'},
    {v:'ACT', l:'ACT – Actividad'},
  ];

  function _load() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; }
    catch(e) { return []; }
  }

  function _save(data) { localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); }

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

  function _csatWidget(sel) {
    var label = sel || '-- Clave --';
    var html = '<div class="fac-csat">';
    html += '<input type="hidden" class="fac-c-clave" value="' + (sel||'') + '">';
    html += '<div class="fac-csat-display" onclick="ModFacturacion._csatToggle(this)">' + label + '</div>';
    html += '<div class="fac-csat-list">';
    for (var i = 0; i < CLAVES_SAT.length; i++) {
      html += '<div class="fac-csat-opt" onclick="ModFacturacion._csatPick(this,\'' + CLAVES_SAT[i].v + '\')">';
      html += '<span class="fac-csat-cod">' + CLAVES_SAT[i].v + '</span>';
      html += '<span class="fac-csat-desc">' + CLAVES_SAT[i].l.split(' – ')[1] + '</span>';
      html += '</div>';
    }
    html += '</div></div>';
    return html;
  }

  function _csatToggle(display) {
    var list = display.nextSibling;
    var isOpen = list.classList.contains('open');
    // cerrar todos los demás
    var all = document.querySelectorAll('.fac-csat-list.open');
    for (var i = 0; i < all.length; i++) all[i].classList.remove('open');
    if (!isOpen) list.classList.add('open');
  }

  function _csatPick(opt, val) {
    var wrap   = opt.closest('.fac-csat');
    var hidden  = wrap.querySelector('.fac-c-clave');
    var display = wrap.querySelector('.fac-csat-display');
    var list    = wrap.querySelector('.fac-csat-list');
    hidden.value   = val;
    display.textContent = val;
    list.classList.remove('open');
  }

  function _unidadWidget(sel) {
    var label = sel || 'M2';
    var html = '<div class="fac-csat">';
    html += '<input type="hidden" class="fac-c-unidad" value="' + (sel||'M2') + '">';
    html += '<div class="fac-csat-display" onclick="ModFacturacion._csatToggle(this)">' + label + '</div>';
    html += '<div class="fac-csat-list">';
    for (var i = 0; i < UNIDADES_SAT.length; i++) {
      html += '<div class="fac-csat-opt" onclick="ModFacturacion._csatPick(this,\'' + UNIDADES_SAT[i].v + '\')">';
      html += '<span class="fac-csat-cod">' + UNIDADES_SAT[i].v + '</span>';
      html += '<span class="fac-csat-desc">' + UNIDADES_SAT[i].l.split(' – ')[1] + '</span>';
      html += '</div>';
    }
    html += '</div></div>';
    return html;
  }

  function _conceptoRow(desc, clave, unidad, cant, precio, iva) {
    var applyIva = (iva === false || iva === 0) ? false : true;
    var imp = (parseFloat(cant)||0) * (parseFloat(precio)||0);
    return '<tr>' +
      '<td><input type="text" class="fac-c-desc" value="' + (desc||'') + '" placeholder="Descripción" oninput="ModFacturacion.recalc()"></td>' +
      '<td>' + _csatWidget(clave||'44111702') + '</td>' +
      '<td>' + _unidadWidget(unidad||'M2') + '</td>' +
      '<td><input type="number" class="fac-c-cant" value="' + (cant||1) + '" min="1" oninput="ModFacturacion.recalc()"></td>' +
      '<td><input type="number" class="fac-c-precio" value="' + (precio||'') + '" min="0" step="0.01" placeholder="0.00" oninput="ModFacturacion.recalc()"></td>' +
      '<td style="text-align:center"><input type="checkbox" class="fac-c-iva" ' + (applyIva ? 'checked' : '') + ' title="Aplica IVA 16%" onchange="ModFacturacion.recalc()"></td>' +
      '<td class="fac-c-imp" style="color:#475569;font-weight:600">' + _fmt(imp) + '</td>' +
      '<td><button class="fac-del-row" onclick="this.closest(\'tr\').remove();ModFacturacion.recalc()">&times;</button></td>' +
      '</tr>';
  }

  function _renderTabla() {
    var data  = _load();
    var tbody = document.getElementById('fac-tbody');
    if (!tbody) return;
    if (!data.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="fac-empty">No hay facturas. Crea la primera.</td></tr>';
      return;
    }
    var html = '';
    for (var i = 0; i < data.length; i++) {
      var f = data[i];
      html += '<tr>';
      html += '<td style="font-weight:600;color:#2563eb">' + f.folio + '</td>';
      html += '<td><div style="font-weight:600">' + (f.nombre||'—') + '</div>';
      html += '<div style="font-size:11px;color:#94a3b8">' + (f.rfc||'') + (f.cp ? ' · CP ' + f.cp : '') + '</div></td>';
      html += '<td style="font-size:12px">' + (f.uso_cfdi||'') + '</td>';
      html += '<td style="font-size:12px">' + (f.forma_pago||'') + ' <span style="color:#94a3b8">/ ' + (f.metodo_pago||'') + '</span></td>';
      html += '<td style="font-weight:600">' + _fmt(f.total) + '</td>';
      html += '<td>' + _badgeHtml(f.estatus) + (f.uuid ? '<div style="font-size:10px;color:#22c55e;font-family:monospace;margin-top:2px">' + f.uuid.slice(0,8) + '…</div>' : '') + '</td>';
      html += '<td>';
      html += '<button class="fac-act-btn" onclick="ModFacturacion.abrirEditar(' + f.id + ')">Editar</button>';
      html += '<button class="fac-act-btn" onclick="ModFacturacion.abrirEstatus(' + f.id + ')">Estatus</button>';
      html += '<button class="fac-act-btn danger" onclick="ModFacturacion.eliminar(' + f.id + ')">Eliminar</button>';
      html += '</td></tr>';
    }
    tbody.innerHTML = html;
  }

  function _clearForm() {
    document.getElementById('fac-edit-id').value = '';
    document.getElementById('fac-folio').value   = _nextFolio();
    document.getElementById('fac-fecha').value   = new Date().toISOString().slice(0,10);
    document.getElementById('fac-nombre').value  = 'PRUEBA DE PORTAL';
    document.getElementById('fac-email').value   = '';
    document.getElementById('fac-rfc').value     = 'XAXX010101000';
    document.getElementById('fac-cp').value      = '';
    document.getElementById('fac-serie').value   = 'A';
    document.getElementById('fac-uso-cfdi').value    = '';
    document.getElementById('fac-regimen').value     = '';
    document.getElementById('fac-forma-pago').value  = '';
    document.getElementById('fac-metodo-pago').value = '';
    document.getElementById('fac-conceptos-body').innerHTML = _conceptoRow('Vidrio templado', '44111702', 'M2', 1, '', true);
    recalc();
  }

  function _getConceptos() {
    var rows = document.querySelectorAll('#fac-conceptos-body tr');
    var out  = [];
    for (var i = 0; i < rows.length; i++) {
      var desc   = rows[i].querySelector('.fac-c-desc').value;
      var clave  = rows[i].querySelector('.fac-c-clave').value;
      var unidadEl = rows[i].querySelector('.fac-c-unidad');
      var unidad   = unidadEl ? unidadEl.value : 'M2';
      var cant     = parseFloat(rows[i].querySelector('.fac-c-cant').value)   || 0;
      var precio   = parseFloat(rows[i].querySelector('.fac-c-precio').value) || 0;
      var ivaChk   = rows[i].querySelector('.fac-c-iva');
      var applyIva = ivaChk ? ivaChk.checked : true;
      if (desc || precio) out.push({desc:desc, clave:clave, unidad:unidad, cant:cant, precio:precio, iva:applyIva});
    }
    return out;
  }

  function recalc() {
    var rows = document.querySelectorAll('#fac-conceptos-body tr');
    var sub = 0;
    var iva = 0;
    for (var i = 0; i < rows.length; i++) {
      var cant      = parseFloat(rows[i].querySelector('.fac-c-cant').value)   || 0;
      var precio    = parseFloat(rows[i].querySelector('.fac-c-precio').value) || 0;
      var ivaCheck  = rows[i].querySelector('.fac-c-iva');
      var applyIva  = ivaCheck ? ivaCheck.checked : true;
      var imp       = cant * precio;
      sub += imp;
      if (applyIva) iva += imp * 0.16;
      var cell = rows[i].querySelector('.fac-c-imp');
      if (cell) cell.textContent = _fmt(imp);
    }
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
    document.getElementById('fac-folio').value   = f.folio;
    document.getElementById('fac-fecha').value   = f.fecha || '';
    document.getElementById('fac-nombre').value  = f.nombre || 'PRUEBA DE PORTAL';
    document.getElementById('fac-email').value   = f.email  || '';
    document.getElementById('fac-rfc').value     = f.rfc    || '';
    document.getElementById('fac-cp').value      = f.cp     || '';
    document.getElementById('fac-serie').value   = f.serie  || 'A';
    document.getElementById('fac-uso-cfdi').value    = f.uso_cfdi        || '';
    document.getElementById('fac-regimen').value     = f.regimen         || '';
    document.getElementById('fac-forma-pago').value  = f.forma_pago_cod  || '';
    document.getElementById('fac-metodo-pago').value = f.metodo_pago     || '';
    var tbody = document.getElementById('fac-conceptos-body');
    tbody.innerHTML = '';
    var conceptos = f.conceptos || [];
    for (var j = 0; j < conceptos.length; j++) {
      var c = conceptos[j];
      tbody.innerHTML += _conceptoRow(c.desc, c.clave, c.unidad, c.cant, c.precio, c.iva);
    }
    if (!conceptos.length) tbody.innerHTML = _conceptoRow('', '44111702', 'M2', 1, '', true);
    recalc();
    document.getElementById('fac-overlay').classList.add('open');
  }

  function cerrarModal() {
    document.getElementById('fac-overlay').classList.remove('open');
  }

  function guardar() {
    var errores = [];
    if (!document.getElementById('fac-uso-cfdi').value)    errores.push('Uso CFDI');
    if (!document.getElementById('fac-regimen').value)     errores.push('Régimen Fiscal del receptor');
    if (!document.getElementById('fac-forma-pago').value)  errores.push('Forma de Pago');
    if (!document.getElementById('fac-metodo-pago').value) errores.push('Método de Pago');
    if (errores.length) {
      alert('Faltan datos fiscales obligatorios:\n\n• ' + errores.join('\n• ') + '\n\nDebes seleccionarlos manualmente — no se guardan por defecto a propósito.');
      return;
    }

    var rfc   = document.getElementById('fac-rfc').value.trim().toUpperCase() || 'XAXX010101000';
    var cp    = document.getElementById('fac-cp').value.trim();
    var email = document.getElementById('fac-email').value.trim();
    var serie = document.getElementById('fac-serie').value.trim() || 'A';
    var conceptos = _getConceptos();
    var sub = 0;
    for (var i = 0; i < conceptos.length; i++) sub += conceptos[i].cant * conceptos[i].precio;
    var iva   = sub * 0.16;
    var total = sub + iva;

    var fpCod  = document.getElementById('fac-forma-pago').value;
    var fpText = document.getElementById('fac-forma-pago').options[document.getElementById('fac-forma-pago').selectedIndex].text;

    var data   = _load();
    var editId = document.getElementById('fac-edit-id').value;

    if (editId) {
      for (var j = 0; j < data.length; j++) {
        if (String(data[j].id) === String(editId)) {
          data[j].rfc           = rfc;
          data[j].cp            = cp;
          data[j].email         = email;
          data[j].serie         = serie;
          data[j].fecha         = document.getElementById('fac-fecha').value;
          data[j].uso_cfdi      = document.getElementById('fac-uso-cfdi').value;
          data[j].regimen       = document.getElementById('fac-regimen').value;
          data[j].forma_pago    = fpText;
          data[j].forma_pago_cod = fpCod;
          data[j].metodo_pago   = document.getElementById('fac-metodo-pago').value;
          data[j].conceptos     = conceptos;
          data[j].subtotal      = sub;
          data[j].iva           = iva;
          data[j].total         = total;
          break;
        }
      }
    } else {
      data.push({
        id:             Date.now(),
        folio:          document.getElementById('fac-folio').value,
        fecha:          document.getElementById('fac-fecha').value,
        nombre:         'PRUEBA DE PORTAL',
        rfc:            rfc,
        cp:             cp,
        email:          email,
        serie:          serie,
        moneda:         'MXN',
        tipo_cfdi:      'I',
        uso_cfdi:       document.getElementById('fac-uso-cfdi').value,
        regimen:        document.getElementById('fac-regimen').value,
        forma_pago:     fpText,
        forma_pago_cod: fpCod,
        metodo_pago:    document.getElementById('fac-metodo-pago').value,
        conceptos:      conceptos,
        subtotal:       sub,
        iva:            iva,
        total:          total,
        estatus:        'pendiente',
        uuid:           '',
        motivo_cancel:  ''
      });
    }

    _save(data);
    cerrarModal();
    _renderTabla();
  }

  function eliminar(id) {
    if (!confirm('¿Eliminar esta factura?')) return;
    var data = _load().filter(function(f) { return f.id !== id; });
    _save(data);
    _renderTabla();
  }

  function abrirEstatus(id) {
    var data = _load();
    var f = null;
    for (var i = 0; i < data.length; i++) { if (data[i].id === id) { f = data[i]; break; } }
    if (!f) return;
    _editingEstId = f.id;
    _estatusSel   = f.estatus;
    document.getElementById('fac-est-folio').textContent = f.folio;
    document.getElementById('fac-uuid').value = f.uuid || '';
    document.getElementById('fac-motivo-cancel').value = f.motivo_cancel || '01';
    _actualizarEstPanel(f.estatus);
    document.getElementById('fac-est-overlay').classList.add('open');
  }

  function _actualizarEstPanel(est) {
    var ids = ['pendiente', 'timbrada', 'cancelada'];
    for (var i = 0; i < ids.length; i++) {
      var btn = document.getElementById('fac-ebtn-' + ids[i]);
      if (btn) { btn.className = 'fac-est-btn' + (ids[i] === est ? ' sel-' + ids[i] : ''); }
    }
    document.getElementById('fac-uuid-wrap').style.display    = (est === 'timbrada')  ? 'block' : 'none';
    document.getElementById('fac-motivo-wrap').className = 'fac-motivo-wrap' + (est === 'cancelada' ? ' visible' : '');
  }

  function selEstatus(est) {
    _estatusSel = est;
    _actualizarEstPanel(est);
  }

  function cerrarEstatus() {
    _editingEstId = null;
    _estatusSel   = null;
    document.getElementById('fac-est-overlay').classList.remove('open');
  }

  function confirmarEstatus() {
    if (!_editingEstId || !_estatusSel) return;
    var data = _load();
    for (var i = 0; i < data.length; i++) {
      if (data[i].id === _editingEstId) {
        data[i].estatus = _estatusSel;
        if (_estatusSel === 'timbrada') {
          data[i].uuid = document.getElementById('fac-uuid').value.trim();
        }
        if (_estatusSel === 'cancelada') {
          data[i].motivo_cancel = document.getElementById('fac-motivo-cancel').value;
        }
        break;
      }
    }
    _save(data);
    cerrarEstatus();
    _renderTabla();
  }

  function agregarConcepto() {
    document.getElementById('fac-conceptos-body').innerHTML += _conceptoRow('', '44111702', 'M2', 1, '');
    recalc();
  }

  // Cerrar dropdown clave SAT al hacer click fuera
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.fac-csat')) {
      var all = document.querySelectorAll('.fac-csat-list.open');
      for (var i = 0; i < all.length; i++) all[i].classList.remove('open');
    }
  });

  _renderTabla();

  return {
    abrirNueva:      abrirNueva,
    abrirEditar:     abrirEditar,
    cerrarModal:     cerrarModal,
    guardar:         guardar,
    eliminar:        eliminar,
    abrirEstatus:    abrirEstatus,
    cerrarEstatus:   cerrarEstatus,
    selEstatus:      selEstatus,
    confirmarEstatus: confirmarEstatus,
    agregarConcepto: agregarConcepto,
    recalc:          recalc,
    _csatToggle:     _csatToggle,
    _csatPick:       _csatPick
  };
})();

window.ModFacturacion = ModFacturacion;
</script>
