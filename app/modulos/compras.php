<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
requirePermiso('ver_inventario');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=compras'); exit;
}
$puede_gestionar = in_array($_SESSION['user_rol'] ?? '', ['dir_admin','dueno','administracion','desarrollo']);
$es_dir_admin    = in_array($_SESSION['user_rol'] ?? '', ['dir_admin','dueno','desarrollo']);
?>
<style>
.cmp-wrap { padding: 24px; }
.page-title { font-size: 18px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #9ca3af; margin-bottom: 16px; }

/* Tabs */
.cmp-tabs { display: flex; gap: 2px; background: #f3f4f6; padding: 3px; border-radius: 10px; width: fit-content; margin-bottom: 16px; }
.cmp-tab  { padding: 6px 16px; border-radius: 8px; border: none; font-size: 13px; cursor: pointer; font-weight: 500; background: none; color: #6b7280; }
.cmp-tab.active { background: #fff; color: #1a1a1a; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.cmp-cnt  { font-size: 11px; font-weight: 600; padding: 1px 6px; border-radius: 99px; background: #e5e7eb; color: #6b7280; margin-left: 4px; }

/* KPIs */
.cmp-kpis { display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap; }
.kpi-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; min-width: 140px; flex: 1; }
.kpi-lbl  { font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 4px; }
.kpi-val  { font-size: 18px; font-weight: 700; color: #1a1a1a; }
.kpi-val.verde { color: #16a34a; }
.kpi-val.rojo  { color: #dc2626; }

/* Toolbar */
.cmp-toolbar { display: flex; gap: 10px; margin-bottom: 14px; align-items: center; flex-wrap: wrap; }
.cmp-search  { flex: 1; min-width: 200px; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
.cmp-search:focus { border-color: #2563eb; }
.cmp-sel     { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; background: #fff; }
.btn-nuevo   { background: #2563eb; color: #fff; border: none; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-nuevo:hover { background: #1d4ed8; }

/* Tabla */
.cmp-table { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
thead th { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; }
tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .1s; }
tbody tr:hover { background: #f8fafc; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 11px 14px; font-size: 13px; }
.loading-msg { text-align: center; padding: 48px; color: #9ca3af; font-size: 14px; }
.num-oc { font-weight: 700; color: #2563eb; font-size: 13px; }

/* Badges estado */
.est-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 99px; }
.est-borrador { background: #f3f4f6; color: #6b7280; }
.est-abierta  { background: #dbeafe; color: #1d4ed8; }
.est-cerrada  { background: #dcfce7; color: #15803d; }
.est-pagada   { background: #d1fae5; color: #065f46; }

/* Badge categoría */
.cat-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: #fef3c7; color: #92400e; }

/* Pager */
.pager-inner { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 12px 16px; border-top: 1px solid #f1f5f9; }
.pager-btn   { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 14px; font-size: 13px; font-weight: 600; cursor: pointer; color: #374151; }
.pager-btn:hover:not([disabled]) { background: #e2e8f0; }
.pager-btn[disabled] { opacity: .4; cursor: default; }
.pager-info  { font-size: 12px; color: #6b7280; }

/* Botones acción tabla */
.btn-ver  { background: none; border: 1px solid #2563eb; color: #2563eb; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; }
.btn-ver:hover { background: #eff6ff; }

/* ── Modales ── */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 9000; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal-box     { background: #fff; border-radius: 14px; width: 100%; max-width: 620px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
.modal-box.modal-lg { max-width: 820px; }
.modal-header  { padding: 18px 22px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; }
.modal-title   { font-size: 16px; font-weight: 700; color: #1a1a1a; }
.modal-close   { background: none; border: none; font-size: 20px; cursor: pointer; color: #9ca3af; line-height: 1; }
.modal-body    { padding: 22px; overflow-y: auto; flex: 1; }
.modal-footer  { padding: 16px 22px; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }

/* Form */
.form-row   { display: flex; gap: 14px; margin-bottom: 14px; }
.form-group { flex: 1; display: flex; flex-direction: column; gap: 5px; }
.form-label { font-size: 12px; font-weight: 600; color: #374151; }
.form-input, .form-select { padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; outline: none; width: 100%; background: #fff; }
.form-input:focus, .form-select:focus { border-color: #2563eb; }
.form-input[readonly] { background: #f9fafb; color: #6b7280; }

/* Detalle tabs */
.det-tabs  { display: flex; gap: 4px; border-bottom: 2px solid #e2e8f0; margin-bottom: 16px; }
.det-tab   { padding: 8px 16px; border: none; background: none; font-size: 13px; font-weight: 500; color: #6b7280; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.det-tab.active { color: #2563eb; border-bottom-color: #2563eb; }
.det-panel { display: none; }
.det-panel.active { display: block; }

/* Partidas table */
.part-table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 12px; }
.part-table th { text-align: left; padding: 7px 10px; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .4px; border-bottom: 1.5px solid #e2e8f0; }
.part-table td { padding: 9px 10px; border-bottom: 1px solid #f1f5f9; }
.part-table tr:last-child td { border-bottom: none; }

/* Botones */
.btn-prim  { background: #2563eb; color: #fff; border: none; padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-prim:hover { background: #1d4ed8; }
.btn-sec   { background: #f8fafc; color: #374151; border: 1px solid #e2e8f0; padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-sec:hover { background: #f1f5f9; }
.btn-sm    { padding: 5px 12px; font-size: 12px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; }
.btn-add   { background: #dcfce7; color: #15803d; }
.btn-add:hover { background: #bbf7d0; }
.btn-del   { background: #fee2e2; color: #b91c1c; }
.btn-del:hover { background: #fecaca; }

/* Pago row */
.pago-row  { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
.pago-row:last-child { border-bottom: none; }
.pago-monto { font-weight: 700; color: #16a34a; margin-left: auto; }

/* Totales */
.totales-row { display: flex; justify-content: flex-end; gap: 24px; padding: 10px 0; font-size: 13px; border-top: 1.5px solid #e2e8f0; margin-top: 8px; }
.tot-lbl { color: #6b7280; }
.tot-val { font-weight: 700; color: #1a1a1a; min-width: 90px; text-align: right; }

/* Info header detalle */
.det-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 16px; background: #f8fafc; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; }
.det-info-lbl  { font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: .3px; }
.det-info-val  { font-weight: 600; color: #1a1a1a; }

@media(max-width:768px) {
  .cmp-wrap { padding: 12px; }
  .cmp-toolbar { flex-direction: column; align-items: stretch; }
  .cmp-kpis { gap: 8px; }
  .kpi-card { min-width: 120px; }
  .form-row { flex-direction: column; gap: 10px; }
  thead th:nth-child(3), thead th:nth-child(5), tbody td:nth-child(3), tbody td:nth-child(5) { display: none; }
}
</style>

<div class="cmp-wrap">
  <div class="page-title">Compras</div>
  <div class="page-sub" id="cmp-sub">Cargando&hellip;</div>

  <div class="cmp-tabs">
    <button class="cmp-tab active" onclick="ModCompras._cambiarTab('suministro')">Suministros <span class="cmp-cnt" id="cmp-cnt-sum">&mdash;</span></button>
    <button class="cmp-tab"        onclick="ModCompras._cambiarTab('material')">&#211;rdenes de Compra <span class="cmp-cnt" id="cmp-cnt-mat">&mdash;</span></button>
  </div>

  <div class="cmp-kpis">
    <div class="kpi-card"><div class="kpi-lbl">Total periodo</div><div class="kpi-val" id="k-total-cmp">&mdash;</div></div>
    <div class="kpi-card"><div class="kpi-lbl">Pagado</div><div class="kpi-val verde" id="k-pagado-cmp">&mdash;</div></div>
    <div class="kpi-card"><div class="kpi-lbl">Por pagar</div><div class="kpi-val rojo" id="k-porpagar-cmp">&mdash;</div></div>
    <div class="kpi-card"><div class="kpi-lbl">N&ordm; de OCs</div><div class="kpi-val" id="k-nocs-cmp">&mdash;</div></div>
  </div>

  <div class="cmp-toolbar">
    <input type="text" class="cmp-search" id="cmp-q" placeholder="&#128269; Buscar por n&uacute;m. OC o proveedor&hellip;" oninput="ModCompras._filtrar()">
    <select class="cmp-sel" id="cmp-estado" onchange="ModCompras._filtrar()">
      <option value="">Todos los estados</option>
      <option value="borrador">Borrador</option>
      <option value="abierta">Abierta</option>
      <option value="cerrada">Cerrada</option>
      <option value="pagada">Pagada</option>
    </select>
    <?php if ($puede_gestionar): ?>
    <button class="btn-nuevo" onclick="ModCompras._nueva()">+ Nueva OC</button>
    <?php endif; ?>
  </div>

  <div class="cmp-table">
    <table>
      <thead>
        <tr id="cmp-thead-row">
          <th>N&ordm; OC</th><th>Fecha</th><th>Proveedor</th><th id="th-cat">Categor&iacute;a</th><th>Partidas</th><th>Total c/IVA</th><th>Estado</th><th></th>
        </tr>
      </thead>
      <tbody id="cmp-tbody"><tr><td colspan="8" class="loading-msg">Cargando&hellip;</td></tr></tbody>
    </table>
    <div id="cmp-pager"></div>
  </div>
</div>

<!-- Modal: Nueva OC -->
<div id="modalNuevaOC" class="modal-overlay" style="display:none">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title" id="modalOCTitle">Nueva OC &mdash; Suministros</div>
      <button class="modal-close" onclick="ModCompras._cerrarModal('modalNuevaOC')">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="ocTipoHidden">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">N&uacute;m. OC</label>
          <input type="text" id="ocNumero" class="form-input" readonly placeholder="Se genera autom&aacute;tico">
        </div>
        <div class="form-group">
          <label class="form-label">Fecha *</label>
          <input type="date" id="ocFecha" class="form-input">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Proveedor *</label>
          <select id="ocProveedor" class="form-select"><option value="">Cargando&hellip;</option></select>
        </div>
        <div class="form-group" id="grpCategoria">
          <label class="form-label">Categor&iacute;a *</label>
          <select id="ocCategoria" class="form-select">
            <option value="">Selecciona una categor&iacute;a&hellip;</option>
            <option value="Herramienta / Consumible">Herramienta / Consumible</option>
            <option value="Seguridad y EPP">Seguridad y EPP</option>
            <option value="Limpieza">Limpieza</option>
            <option value="Papeler&iacute;a / Oficina">Papeler&iacute;a / Oficina</option>
            <option value="Flete y Log&iacute;stica">Flete y Log&iacute;stica</option>
            <option value="Mantenimiento">Mantenimiento</option>
            <option value="Operaci&oacute;n mensual">Operaci&oacute;n mensual</option>
            <option value="Renta">Renta</option>
            <option value="Servicios contables">Servicios contables</option>
            <option value="Otro">Otro</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">D&iacute;as cr&eacute;dito</label>
          <input type="number" id="ocDiasCredito" class="form-input" min="0" value="0" placeholder="0 = contado">
        </div>
        <div class="form-group">
          <label class="form-label">Notas</label>
          <input type="text" id="ocNotas" class="form-input" placeholder="Opcional">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Cotizaci&oacute;n / Factura a pagar <span style="font-weight:400;color:#9ca3af">(opcional)</span></label>
          <input type="file" id="ocArchivo" accept=".pdf,.jpg,.jpeg,.png,.webp" style="font-size:13px;padding:4px 0">
          <span style="font-size:11px;color:#9ca3af">PDF o imagen &mdash; se adjunta al correo de la OC</span>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-sec" onclick="ModCompras._cerrarModal('modalNuevaOC')">Cancelar</button>
      <button class="btn-prim" onclick="cmpGuardarOC()">Guardar OC</button>
    </div>
  </div>
</div>

<!-- Modal: Detalle OC -->
<div id="modalDetalle" class="modal-overlay" style="display:none">
  <div class="modal-box modal-lg">
    <div class="modal-header">
      <div class="modal-title" id="detTitulo">Detalle OC</div>
      <button class="modal-close" onclick="ModCompras._cerrarModal('modalDetalle')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="det-info-grid" id="detInfoGrid"></div>
      <div class="det-tabs">
        <button class="det-tab active" onclick="cmpDetTab('partidas')">Partidas</button>
        <button class="det-tab"        onclick="cmpDetTab('pagos')">Pagos</button>
        <button class="det-tab"        onclick="cmpDetTab('recepcion')">Recepci&oacute;n</button>
        <button class="det-tab"        onclick="cmpDetTab('comprobantes')">Comprobantes</button>
      </div>
      <div class="det-panel active" id="panelPartidas"></div>
      <div class="det-panel"        id="panelPagos"></div>
      <div class="det-panel"        id="panelRecepcion"></div>
      <div class="det-panel"        id="panelComprobantes"></div>
    </div>
    <div class="modal-footer" id="detFooter"></div>
  </div>
</div>

<!-- Modal: Agregar partida -->
<div id="modalPartida" class="modal-overlay" style="display:none">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">Agregar partida</div>
      <button class="modal-close" onclick="ModCompras._cerrarModal('modalPartida')">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="pOcId">
      <div class="form-row">
        <div class="form-group" style="flex:3">
          <label class="form-label">Descripci&oacute;n *</label>
          <input type="text" id="pDesc" class="form-input" placeholder="ej: Guantes nitrilo talla M">
        </div>
        <div class="form-group" style="flex:1">
          <label class="form-label">Unidad</label>
          <input type="text" id="pUnidad" class="form-input" placeholder="PZA">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Cantidad *</label>
          <input type="number" id="pCantidad" class="form-input" min="0.01" step="0.01" placeholder="0">
        </div>
        <div class="form-group">
          <label class="form-label">Precio unitario *</label>
          <input type="number" id="pPrecio" class="form-input" min="0.01" step="0.01" placeholder="0.00">
        </div>
        <div class="form-group">
          <label class="form-label">Importe</label>
          <input type="text" id="pImporte" class="form-input" readonly placeholder="0.00">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-sec" onclick="ModCompras._cerrarModal('modalPartida')">Cancelar</button>
      <button class="btn-prim" onclick="cmpGuardarPartida()">Agregar</button>
    </div>
  </div>
</div>

<!-- Modal: Registrar pago -->
<div id="modalPago" class="modal-overlay" style="display:none">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">Registrar pago</div>
      <button class="modal-close" onclick="ModCompras._cerrarModal('modalPago')">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="pagoOcId">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Fecha *</label>
          <input type="date" id="pagoFecha" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Monto *</label>
          <input type="number" id="pagoMonto" class="form-input" min="0.01" step="0.01" placeholder="0.00">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Referencia / N&ordm; cheque</label>
          <input type="text" id="pagoRef" class="form-input" placeholder="Opcional">
        </div>
        <div class="form-group">
          <label class="form-label">Notas</label>
          <input type="text" id="pagoNotas" class="form-input" placeholder="Opcional">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-sec" onclick="ModCompras._cerrarModal('modalPago')">Cancelar</button>
      <button class="btn-prim" id="btnGuardarPago" onclick="cmpGuardarPago()">Registrar pago</button>
    </div>
  </div>
</div>

<script>
var PUEDE_GESTIONAR_CMP = <?= $puede_gestionar ? 'true' : 'false' ?>;
var ES_DIR_ADMIN_CMP    = <?= $es_dir_admin ? 'true' : 'false' ?>;

var ModCompras = (function() {

var _cmpTab  = 'suministro';
var _cmpData = [];
var _cmpPage = 1;
var _PER     = 25;
var _provs   = [];
var _detalle = null;
var _detTab  = 'partidas';

// ── Carga principal ───────────────────────────────────────────
async function cmpCargar() {
  try {
    var resOC   = await fetch('../api/ordenes_compra.php?accion=lista&t=' + Date.now());
    var dataOC  = await resOC.json();
    _cmpData    = Array.isArray(dataOC.ordenes) ? dataOC.ordenes : [];

    var resProv = await fetch('../api/proveedores.php?t=' + Date.now());
    var dataProv = await resProv.json();
    _provs = Array.isArray(dataProv) ? dataProv : (Array.isArray(dataProv.proveedores) ? dataProv.proveedores : []);
    cmpPoblarSelectProv();

    cmpFiltrar();
    document.getElementById('cmp-sub').textContent = 'Actualizado a las ' + new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  } catch(e) {
    document.getElementById('cmp-sub').textContent = 'Error al cargar';
  }
}

function cmpPoblarSelectProv() {
  var sel = document.getElementById('ocProveedor');
  if (!sel) return;
  sel.innerHTML = '<option value="">Seleccionar proveedor&hellip;</option>';
  _provs.forEach(function(p) {
    sel.innerHTML += '<option value="' + p.id + '">' + cmpEsc(p.nombre) + '</option>';
  });
}

// ── Tab ───────────────────────────────────────────────────────
function cmpCambiarTab(tab) {
  _cmpTab  = tab;
  _cmpPage = 1;
  document.querySelectorAll('.cmp-tab').forEach(function(b, i) {
    b.classList.toggle('active', ['suministro','material'][i] === tab);
  });
  // Mostrar/ocultar columna Categoría (solo suministro)
  var thCat = document.getElementById('th-cat');
  if (thCat) thCat.style.display = tab === 'suministro' ? '' : 'none';
  cmpFiltrar();
}

// ── Filtrar y renderizar ──────────────────────────────────────
function cmpFiltrar() {
  var q      = ((document.getElementById('cmp-q') || {}).value || '').toLowerCase().trim();
  var estado = (document.getElementById('cmp-estado') || {}).value || '';

  var lista = _cmpData.filter(function(o) {
    if (o.tipo !== _cmpTab) return false;
    if (estado && o.estado !== estado) return false;
    if (q && (o.numero_oc||'').toLowerCase().indexOf(q) < 0 && (o.proveedor||'').toLowerCase().indexOf(q) < 0 && (o.categoria||'').toLowerCase().indexOf(q) < 0) return false;
    return true;
  });

  var cntSum = _cmpData.filter(function(o){ return o.tipo === 'suministro'; }).length;
  var cntMat = _cmpData.filter(function(o){ return o.tipo === 'material'; }).length;
  document.getElementById('cmp-cnt-sum').textContent = cntSum;
  document.getElementById('cmp-cnt-mat').textContent = cntMat;

  cmpRenderKpis(lista);

  var totalPags = Math.max(1, Math.ceil(lista.length / _PER));
  if (_cmpPage > totalPags) _cmpPage = totalPags;
  if (_cmpPage < 1)         _cmpPage = 1;
  var pagina = lista.slice((_cmpPage - 1) * _PER, _cmpPage * _PER);

  cmpRenderTabla(pagina);

  var pagerEl = document.getElementById('cmp-pager');
  if (pagerEl) {
    if (totalPags <= 1) {
      pagerEl.innerHTML = '';
    } else {
      pagerEl.innerHTML = '<div class="pager-inner">'
        + '<button class="pager-btn" onclick="cmpPaginar(-1)"' + (_cmpPage <= 1 ? ' disabled' : '') + '>&#8592; Ant</button>'
        + '<span class="pager-info">P&aacute;g. ' + _cmpPage + ' / ' + totalPags + ' &nbsp;&middot;&nbsp; ' + lista.length + ' registros</span>'
        + '<button class="pager-btn" onclick="cmpPaginar(1)"' + (_cmpPage >= totalPags ? ' disabled' : '') + '>Sig &#8594;</button>'
        + '</div>';
    }
  }
}

function cmpRenderKpis(lista) {
  var total = 0, pagado = 0;
  lista.forEach(function(o) {
    total  += parseFloat(o.total_con_iva || 0);
    pagado += parseFloat(o.pagado_total  || 0);
  });
  var fmt = function(n) { return '$' + n.toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  document.getElementById('k-total-cmp').textContent   = fmt(total);
  document.getElementById('k-pagado-cmp').textContent  = fmt(pagado);
  document.getElementById('k-porpagar-cmp').textContent = fmt(Math.max(0, total - pagado));
  document.getElementById('k-nocs-cmp').textContent    = lista.length;
}

function cmpRenderTabla(lista) {
  if (!lista.length) {
    document.getElementById('cmp-tbody').innerHTML = '<tr><td colspan="8" class="loading-msg">No hay registros</td></tr>';
    return;
  }
  var fmt  = function(n) { return '$' + parseFloat(n||0).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  var fmtF = function(f) { return f ? new Date(f+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}) : '&mdash;'; };
  var estMap = {borrador:'est-borrador',abierta:'est-abierta',cerrada:'est-cerrada',pagada:'est-pagada'};
  var estLbl = {borrador:'Borrador',abierta:'Abierta',cerrada:'Cerrada',pagada:'Pagada'};

  document.getElementById('cmp-tbody').innerHTML = lista.map(function(o) {
    var est   = o.estado || 'borrador';
    var cat   = o.categoria ? '<span class="cat-badge">' + cmpEsc(o.categoria) + '</span>' : '&mdash;';
    var total = parseFloat(o.total_con_iva || 0);
    return '<tr>'
      + '<td class="num-oc">' + cmpEsc(o.numero_oc) + '</td>'
      + '<td style="font-size:12px;color:#64748b">' + fmtF(o.fecha_oc) + '</td>'
      + '<td>' + cmpEsc(o.proveedor||'&mdash;') + '</td>'
      + '<td>' + (o.tipo === 'suministro' ? cat : '<span style="font-size:11px;color:#6b7280">Material</span>') + '</td>'
      + '<td style="text-align:center;color:#64748b">' + (o.num_partidas||0) + '</td>'
      + '<td style="font-weight:600">' + fmt(total) + '</td>'
      + '<td><span class="est-badge ' + (estMap[est]||'est-borrador') + '">' + (estLbl[est]||est) + '</span></td>'
      + '<td><button class="btn-ver" onclick="ModCompras._ver(' + o.id + ')">Ver</button></td>'
      + '</tr>';
  }).join('');
}

// ── Nueva OC ──────────────────────────────────────────────────
async function cmpNuevaOC() {
  document.getElementById('ocTipoHidden').value = _cmpTab;
  document.getElementById('modalOCTitle').textContent = _cmpTab === 'suministro' ? 'Nueva OC — Suministros' : 'Nueva OC — Material';
  document.getElementById('grpCategoria').style.display = _cmpTab === 'suministro' ? '' : 'none';
  document.getElementById('ocFecha').value = new Date().toISOString().substr(0,10);
  document.getElementById('ocDiasCredito').value = '0';
  document.getElementById('ocNotas').value = '';
  document.getElementById('ocCategoria').value = '';

  // Obtener siguiente consecutivo
  try {
    var r = await fetch('../api/ordenes_compra.php?accion=consecutivo');
    var d = await r.json();
    document.getElementById('ocNumero').value = d.numero_oc || '';
  } catch(e) {
    document.getElementById('ocNumero').value = '';
  }

  document.getElementById('modalNuevaOC').style.display = 'flex';
}

function cmpCerrarModal(id) {
  var el = document.getElementById(id);
  if (el) el.style.display = 'none';
}
window.cmpCerrarModal = cmpCerrarModal;

// ── Guardar nueva OC ──────────────────────────────────────────
async function cmpGuardarOC() {
  var tipo         = document.getElementById('ocTipoHidden').value;
  var proveedor_id = document.getElementById('ocProveedor').value;
  var fecha_oc     = document.getElementById('ocFecha').value;
  var dias_credito = document.getElementById('ocDiasCredito').value;
  var notas        = document.getElementById('ocNotas').value;
  var categoria    = tipo === 'suministro' ? document.getElementById('ocCategoria').value : '';

  if (!proveedor_id) { alert('Selecciona un proveedor'); return; }
  if (!fecha_oc)     { alert('Indica la fecha'); return; }
  if (tipo === 'suministro' && !categoria) { alert('Selecciona una categoría'); return; }

  try {
    var r = await fetch('../api/ordenes_compra.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({accion:'crear', tipo: tipo, categoria: categoria, proveedor_id: parseInt(proveedor_id), fecha_oc: fecha_oc, dias_credito: parseInt(dias_credito)||0, notas: notas})
    });
    var d = await r.json();
    if (d.ok) {
      // Subir archivo si se seleccionó
      var archivoInput = document.getElementById('ocArchivo');
      if (archivoInput && archivoInput.files && archivoInput.files.length) {
        var form = new FormData();
        form.append('archivo', archivoInput.files[0]);
        form.append('oc_id', d.id);
        await fetch('../api/ordenes_compra.php?accion=subir_archivo', { method: 'POST', body: form });
        archivoInput.value = '';
      }
      cmpCerrarModal('modalNuevaOC');
      await cmpCargar();
      cmpVerDetalle(d.id);
    } else {
      alert(d.error || 'Error al guardar');
    }
  } catch(e) { alert('Error de conexion'); }
}
window.cmpGuardarOC = cmpGuardarOC;

// ── Ver detalle ───────────────────────────────────────────────
async function cmpVerDetalle(id) {
  _detalle = null;
  _detTab  = 'partidas';
  document.getElementById('detTitulo').textContent = 'Cargando...';
  document.getElementById('detInfoGrid').innerHTML = '';
  document.getElementById('panelPartidas').innerHTML    = '<p style="color:#9ca3af;padding:20px">Cargando...</p>';
  document.getElementById('panelPagos').innerHTML       = '';
  document.getElementById('panelRecepcion').innerHTML   = '';
  document.getElementById('panelComprobantes').innerHTML = '';
  document.getElementById('modalDetalle').style.display = 'flex';

  try {
    var r = await fetch('../api/ordenes_compra.php?accion=detalle&id=' + id);
    _detalle = await r.json();
    if (_detalle.error) { alert(_detalle.error); cmpCerrarModal('modalDetalle'); return; }
    cmpRenderDetalle();
  } catch(e) { alert('Error de conexion'); cmpCerrarModal('modalDetalle'); }
}

function cmpRenderDetalle() {
  if (!_detalle) return;
  var o    = _detalle;
  var fmt  = function(n) { return '$' + parseFloat(n||0).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  var fmtF = function(f) { return f ? new Date(f+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}) : '—'; };
  var estLbl = {borrador:'Borrador',abierta:'Abierta',cerrada:'Cerrada',pagada:'Pagada'};

  document.getElementById('detTitulo').textContent = o.numero_oc + (o.tipo === 'suministro' ? ' — Suministro' : ' — Material');

  var catHtml = o.categoria ? ' &nbsp;<span class="cat-badge">' + cmpEsc(o.categoria) + '</span>' : '';
  document.getElementById('detInfoGrid').innerHTML =
    '<div><div class="det-info-lbl">Proveedor</div><div class="det-info-val">' + cmpEsc(o.proveedor_nombre||'') + '</div></div>'
  + '<div><div class="det-info-lbl">Estado</div><div class="det-info-val">' + (estLbl[o.estado]||o.estado) + '</div></div>'
  + '<div><div class="det-info-lbl">Fecha OC</div><div class="det-info-val">' + fmtF(o.fecha_oc) + '</div></div>'
  + '<div><div class="det-info-lbl">D&iacute;as cr&eacute;dito</div><div class="det-info-val">' + (o.dias_credito||0) + ' d&iacute;as</div></div>'
  + (o.tipo === 'suministro' ? '<div><div class="det-info-lbl">Categor&iacute;a</div><div class="det-info-val">' + catHtml + '</div></div>' : '')
  + (o.notas ? '<div style="grid-column:1/-1"><div class="det-info-lbl">Notas</div><div class="det-info-val">' + cmpEsc(o.notas) + '</div></div>' : '');

  // Activar tab correcto
  var _tabs = ['partidas','pagos','recepcion','comprobantes'];
  document.querySelectorAll('.det-tab').forEach(function(b, i) {
    b.classList.toggle('active', _tabs[i] === _detTab);
  });
  document.querySelectorAll('.det-panel').forEach(function(p, i) {
    p.classList.toggle('active', _tabs[i] === _detTab);
  });

  if (_detTab === 'partidas')     cmpRenderPanelPartidas(o);
  if (_detTab === 'pagos')        cmpRenderPanelPagos(o);
  if (_detTab === 'recepcion')    cmpRenderPanelRecepcion(o);
  if (_detTab === 'comprobantes') cmpRenderPanelComprobantes(o.id);

  // Footer con acciones
  var footer = '';
  var puedeEditar = (o.estado === 'borrador' || o.estado === 'abierta');
  // Pagos: se permiten aunque la OC ya esté cerrada (recepción y pago suelen ir en momentos distintos, p.ej. días de crédito)
  var puedePagar  = (o.estado === 'borrador' || o.estado === 'abierta' || o.estado === 'cerrada');
  if (PUEDE_GESTIONAR_CMP) {
    if (puedeEditar) {
      footer += '<button class="btn-sm btn-add" onclick="cmpAbrirModalPartida(' + o.id + ')">+ Partida</button> ';
    }
    if (puedePagar) {
      footer += '<button class="btn-sm btn-add" onclick="cmpAbrirModalPago(' + o.id + ')">+ Pago</button> ';
    }
    if (o.estado === 'borrador') {
      var lbl_abrir = ES_DIR_ADMIN_CMP ? 'Abrir OC (envia correo)' : 'Abrir OC';
      footer += '<button class="btn-prim" style="font-size:12px;padding:7px 14px" onclick="cmpCambiarEstado(' + o.id + ',\'abierta\')">' + lbl_abrir + '</button> ';
    }
    if (o.estado === 'abierta') {
      footer += '<button class="btn-prim" style="font-size:12px;padding:7px 14px;background:#16a34a" onclick="cmpAbrirModalRecepcion(' + o.id + ')">Registrar recepci&oacute;n</button> ';
      // Botón enviar correo — solo dir_admin, solo si no se ha enviado
      if (ES_DIR_ADMIN_CMP && !o.correo_enviado) {
        footer += '<button class="btn-prim" style="font-size:12px;padding:7px 14px;background:#7c3aed" onclick="cmpEnviarCorreo(' + o.id + ')">Enviar OC por correo</button> ';
      }
      if (o.correo_enviado) {
        footer += '<span style="font-size:12px;color:#16a34a;font-weight:600;padding:7px 0">Correo enviado</span> ';
      }
    }
  }
  footer += '<button class="btn-sec" onclick="ModCompras._cerrarModal(\'modalDetalle\')">Cerrar</button>';
  document.getElementById('detFooter').innerHTML = footer;
}

function cmpRenderPanelPartidas(o) {
  var fmt  = function(n) { return '$' + parseFloat(n||0).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  var parts = o.partidas || [];
  var subtotal = 0;
  parts.forEach(function(p) { subtotal += parseFloat(p.importe||0); });

  var rows = parts.map(function(p) {
    var recPct = p.cantidad > 0 ? Math.round((p.cantidad_recibida/p.cantidad)*100) : 0;
    var delBtn = (PUEDE_GESTIONAR_CMP && o.estado === 'borrador')
      ? '<button class="btn-sm btn-del" onclick="cmpEliminarPartida(' + p.id + ')">&#10005;</button>' : '';
    return '<tr>'
      + '<td>' + cmpEsc(p.descripcion) + '</td>'
      + '<td style="text-align:right">' + parseFloat(p.cantidad) + ' ' + cmpEsc(p.unidad) + '</td>'
      + '<td style="text-align:right">' + fmt(p.precio_unitario) + '</td>'
      + '<td style="text-align:right;font-weight:600">' + fmt(p.importe) + '</td>'
      + '<td style="text-align:center;font-size:11px;color:#16a34a">' + p.cantidad_recibida + ' (' + recPct + '%)</td>'
      + '<td>' + delBtn + '</td>'
      + '</tr>';
  }).join('');

  var html = parts.length
    ? '<table class="part-table"><thead><tr><th>Descripci&oacute;n</th><th style="text-align:right">Cant./Und</th><th style="text-align:right">P. Unit.</th><th style="text-align:right">Importe</th><th style="text-align:center">Recibido</th><th></th></tr></thead><tbody>' + rows + '</tbody></table>'
    : '<p style="color:#9ca3af;padding:10px 0">Sin partidas a&uacute;n.</p>';

  html += '<div class="totales-row">'
    + '<span class="tot-lbl">Subtotal s/IVA</span><span class="tot-val">' + fmt(subtotal) + '</span>'
    + '<span class="tot-lbl">IVA 16%</span><span class="tot-val">' + fmt(subtotal*0.16) + '</span>'
    + '<span class="tot-lbl" style="font-weight:700">Total c/IVA</span><span class="tot-val" style="color:#2563eb">' + fmt(subtotal*1.16) + '</span>'
    + '</div>';

  document.getElementById('panelPartidas').innerHTML = html;
}

function cmpRenderPanelPagos(o) {
  var fmt   = function(n) { return '$' + parseFloat(n||0).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  var pagos = o.pagos || [];
  var totalPagado = 0;
  pagos.forEach(function(p) { totalPagado += parseFloat(p.monto||0); });

  var html = pagos.length ? pagos.map(function(p) {
    return '<div class="pago-row">'
      + '<span style="font-size:12px;color:#64748b">' + cmpEsc(p.fecha_pago) + '</span>'
      + (p.referencia ? '<span style="font-size:12px">' + cmpEsc(p.referencia) + '</span>' : '')
      + (p.notas ? '<span style="font-size:12px;color:#9ca3af">' + cmpEsc(p.notas) + '</span>' : '')
      + '<span class="pago-monto">' + fmt(p.monto) + '</span>'
      + '</div>';
  }).join('') : '<p style="color:#9ca3af;padding:10px 0">Sin pagos registrados.</p>';

  html += '<div class="totales-row"><span class="tot-lbl">Total pagado</span><span class="tot-val verde" style="color:#16a34a">' + fmt(totalPagado) + '</span></div>';
  document.getElementById('panelPagos').innerHTML = html;
}

function cmpRenderPanelRecepcion(o) {
  var entregas = o.entregas || [];
  var html = entregas.length ? entregas.map(function(e) {
    return '<div class="pago-row">'
      + '<span style="font-size:12px;color:#64748b">' + cmpEsc(e.fecha_entrega||'') + '</span>'
      + '<span style="font-size:12px">' + cmpEsc(e.resumen||e.notas||'Sin detalle') + '</span>'
      + (e.cierra_oc ? '<span style="font-size:11px;font-weight:700;color:#15803d">Cierra OC</span>' : '')
      + '</div>';
  }).join('') : '<p style="color:#9ca3af;padding:10px 0">Sin recepciones registradas.</p>';
  document.getElementById('panelRecepcion').innerHTML = html;
}

// ── Sub-tab detalle ───────────────────────────────────────────
function cmpDetTab(tab) {
  _detTab = tab;
  var _tabs = ['partidas','pagos','recepcion','comprobantes'];
  document.querySelectorAll('.det-tab').forEach(function(b, i) {
    b.classList.toggle('active', _tabs[i] === tab);
  });
  document.querySelectorAll('.det-panel').forEach(function(p, i) {
    p.classList.toggle('active', _tabs[i] === tab);
  });
  if (!_detalle) return;
  if (tab === 'partidas')     cmpRenderPanelPartidas(_detalle);
  if (tab === 'pagos')        cmpRenderPanelPagos(_detalle);
  if (tab === 'recepcion')    cmpRenderPanelRecepcion(_detalle);
  if (tab === 'comprobantes') cmpRenderPanelComprobantes(_detalle.id);
}
window.cmpDetTab = cmpDetTab;

// ── Panel Comprobantes ────────────────────────────────────────
async function cmpRenderPanelComprobantes(oc_id) {
  var panel = document.getElementById('panelComprobantes');
  panel.innerHTML = '<p style="color:#9ca3af;padding:16px 0">Cargando...</p>';
  try {
    var r    = await fetch('../api/ordenes_compra.php?accion=archivos&id=' + oc_id);
    var list = await r.json();
    var html = '';

    if (PUEDE_GESTIONAR_CMP) {
      html += '<div style="margin-bottom:14px">'
            + '<label style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;display:block;margin-bottom:6px">Subir comprobante (PDF, imagen)</label>'
            + '<div style="display:flex;gap:8px;align-items:center">'
            + '<input type="file" id="cmpFileInput" accept=".pdf,.jpg,.jpeg,.png,.webp" style="font-size:13px;flex:1">'
            + '<button class="btn-prim" style="font-size:12px;padding:7px 14px;white-space:nowrap" onclick="cmpSubirArchivo(' + oc_id + ')">&#128206; Subir</button>'
            + '</div>'
            + '<div id="cmpUploadMsg" style="font-size:12px;margin-top:6px;color:#6b7280"></div>'
            + '</div>';
    }

    if (!list.length) {
      html += '<p style="color:#9ca3af;font-size:13px">Sin comprobantes adjuntos.</p>';
    } else {
      html += '<table style="width:100%;border-collapse:collapse;font-size:13px">'
            + '<thead><tr style="border-bottom:2px solid #e2e8f0">'
            + '<th style="text-align:left;padding:6px 8px;color:#6b7280;font-weight:600">Archivo</th>'
            + '<th style="text-align:left;padding:6px 8px;color:#6b7280;font-weight:600">Subido por</th>'
            + '<th style="text-align:left;padding:6px 8px;color:#6b7280;font-weight:600">Fecha</th>'
            + '<th style="padding:6px 8px"></th>'
            + '</tr></thead><tbody>';
      list.forEach(function(a) {
        var fecha = a.created_at ? a.created_at.substring(0,10) : '';
        html += '<tr style="border-bottom:1px solid #f1f5f9">'
              + '<td style="padding:8px"><a href="../archivos_oc/' + cmpEsc(a.ruta) + '" target="_blank" style="color:#2563eb;text-decoration:none;font-weight:500">&#128206; ' + cmpEsc(a.nombre) + '</a></td>'
              + '<td style="padding:8px;color:#374151">' + cmpEsc(a.subido_por||'—') + '</td>'
              + '<td style="padding:8px;color:#6b7280">' + fecha + '</td>'
              + '<td style="padding:8px;text-align:right">';
        if (PUEDE_GESTIONAR_CMP) {
          html += '<button onclick="cmpEliminarArchivo(' + a.id + ',' + oc_id + ')" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:16px" title="Eliminar">&#128465;</button>';
        }
        html += '</td></tr>';
      });
      html += '</tbody></table>';
    }

    panel.innerHTML = html;
  } catch(e) {
    panel.innerHTML = '<p style="color:#dc2626;font-size:13px">Error al cargar comprobantes.</p>';
  }
}

async function cmpSubirArchivo(oc_id) {
  var input = document.getElementById('cmpFileInput');
  var msg   = document.getElementById('cmpUploadMsg');
  if (!input || !input.files.length) { msg.textContent = 'Selecciona un archivo primero.'; return; }
  var form = new FormData();
  form.append('archivo', input.files[0]);
  form.append('oc_id', oc_id);
  msg.textContent = 'Subiendo...';
  try {
    var r    = await fetch('../api/ordenes_compra.php?accion=subir_archivo', { method: 'POST', body: form });
    var data = await r.json();
    if (data.ok) {
      input.value = '';
      msg.textContent = '';
      cmpRenderPanelComprobantes(oc_id);
    } else {
      msg.textContent = data.error || 'Error al subir.';
    }
  } catch(e) {
    msg.textContent = 'Error de conexion.';
  }
}
window.cmpSubirArchivo = cmpSubirArchivo;

async function cmpEliminarArchivo(archivo_id, oc_id) {
  if (!confirm('¿Eliminar este comprobante?')) return;
  try {
    var r    = await fetch('../api/ordenes_compra.php?accion=eliminar_archivo&id=' + archivo_id, { method: 'DELETE' });
    var data = await r.json();
    if (data.ok) cmpRenderPanelComprobantes(oc_id);
    else alert(data.error || 'Error al eliminar.');
  } catch(e) { alert('Error de conexion.'); }
}
window.cmpEliminarArchivo = cmpEliminarArchivo;

// ── Agregar partida ───────────────────────────────────────────
function cmpAbrirModalPartida(oc_id) {
  document.getElementById('pOcId').value    = oc_id;
  document.getElementById('pDesc').value    = '';
  document.getElementById('pUnidad').value  = 'PZA';
  document.getElementById('pCantidad').value = '';
  document.getElementById('pPrecio').value  = '';
  document.getElementById('pImporte').value = '';
  document.getElementById('modalPartida').style.display = 'flex';
}
window.cmpAbrirModalPartida = cmpAbrirModalPartida;

document.addEventListener('input', function(e) {
  if (e.target.id === 'pCantidad' || e.target.id === 'pPrecio') {
    var c = parseFloat(document.getElementById('pCantidad').value) || 0;
    var p = parseFloat(document.getElementById('pPrecio').value) || 0;
    document.getElementById('pImporte').value = '$' + (c * p).toLocaleString('es-MX', {minimumFractionDigits: 2});
  }
});

async function cmpGuardarPartida() {
  var oc_id    = parseInt(document.getElementById('pOcId').value);
  var desc     = document.getElementById('pDesc').value.trim();
  var unidad   = document.getElementById('pUnidad').value.trim() || 'PZA';
  var cantidad = parseFloat(document.getElementById('pCantidad').value);
  var precio   = parseFloat(document.getElementById('pPrecio').value);

  if (!desc || !cantidad || !precio) { alert('Descripción, cantidad y precio son requeridos'); return; }

  try {
    var r = await fetch('../api/ordenes_compra.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({accion:'agregar_partida', orden_compra_id: oc_id, tipo:'otro', descripcion: desc, unidad: unidad, cantidad: cantidad, precio_unitario: precio})
    });
    var d = await r.json();
    if (d.ok) {
      cmpCerrarModal('modalPartida');
      var r2 = await fetch('../api/ordenes_compra.php?accion=detalle&id=' + oc_id);
      _detalle = await r2.json();
      _detTab  = 'partidas';
      cmpRenderDetalle();
      await cmpCargar();
    } else { alert(d.error || 'Error'); }
  } catch(e) { alert('Error de conexion'); }
}
window.cmpGuardarPartida = cmpGuardarPartida;

// ── Eliminar partida ──────────────────────────────────────────
async function cmpEliminarPartida(id) {
  if (!confirm('Eliminar esta partida?')) return;
  try {
    var r = await fetch('../api/ordenes_compra.php', {
      method: 'DELETE',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({accion:'eliminar_partida', id: id})
    });
    var d = await r.json();
    if (d.ok && _detalle) {
      var r2 = await fetch('../api/ordenes_compra.php?accion=detalle&id=' + _detalle.id);
      _detalle = await r2.json();
      cmpRenderDetalle();
      await cmpCargar();
    } else { alert(d.error || 'Error'); }
  } catch(e) { alert('Error de conexion'); }
}
window.cmpEliminarPartida = cmpEliminarPartida;

// ── Registrar pago ────────────────────────────────────────────
function cmpAbrirModalPago(oc_id) {
  document.getElementById('pagoOcId').value  = oc_id;
  document.getElementById('pagoFecha').value = new Date().toISOString().substr(0,10);
  document.getElementById('pagoMonto').value  = '';
  document.getElementById('pagoRef').value    = '';
  document.getElementById('pagoNotas').value  = '';
  var btnPago = document.getElementById('btnGuardarPago');
  if (btnPago) { btnPago.disabled = false; btnPago.textContent = 'Registrar pago'; }
  document.getElementById('modalPago').style.display = 'flex';
}
window.cmpAbrirModalPago = cmpAbrirModalPago;

async function cmpGuardarPago() {
  var btn = document.getElementById('btnGuardarPago');
  if (btn && btn.disabled) return; // ya se está procesando este mismo clic

  var oc_id = parseInt(document.getElementById('pagoOcId').value);
  var fecha  = document.getElementById('pagoFecha').value;
  var monto  = parseFloat(document.getElementById('pagoMonto').value);
  var ref    = document.getElementById('pagoRef').value.trim();
  var notas  = document.getElementById('pagoNotas').value.trim();

  if (!fecha || !monto) { alert('Fecha y monto son requeridos'); return; }

  if (btn) { btn.disabled = true; btn.textContent = 'Registrando...'; }

  try {
    var r = await fetch('../api/ordenes_compra.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({accion:'registrar_pago', orden_compra_id: oc_id, fecha_pago: fecha, monto: monto, referencia: ref, notas: notas, incluye_iva: 1})
    });
    var d = await r.json();
    if (d.ok) {
      cmpCerrarModal('modalPago');
      var r2 = await fetch('../api/ordenes_compra.php?accion=detalle&id=' + oc_id);
      _detalle = await r2.json();
      _detTab  = 'pagos';
      cmpRenderDetalle();
      await cmpCargar();
    } else {
      if (btn) { btn.disabled = false; btn.textContent = 'Registrar pago'; }
      alert(d.error || 'Error');
    }
  } catch(e) {
    if (btn) { btn.disabled = false; btn.textContent = 'Registrar pago'; }
    alert('Error de conexion');
  }
}
window.cmpGuardarPago = cmpGuardarPago;

// ── Registrar recepción ───────────────────────────────────────
function cmpAbrirModalRecepcion(oc_id) {
  if (!_detalle || !_detalle.partidas || !_detalle.partidas.length) {
    alert('Agrega partidas antes de registrar recepción'); return;
  }
  var pendientes = _detalle.partidas.filter(function(p) {
    return parseFloat(p.cantidad_recibida||0) < parseFloat(p.cantidad||0);
  });
  if (!pendientes.length) { alert('Todas las partidas ya están recibidas'); return; }

  var html = '<p style="font-size:13px;margin-bottom:14px">Indica la cantidad recibida por partida:</p>';
  pendientes.forEach(function(p) {
    var pend = parseFloat(p.cantidad) - parseFloat(p.cantidad_recibida||0);
    html += '<div class="form-row" style="align-items:center;gap:12px;margin-bottom:10px">'
      + '<div style="flex:3;font-size:13px">' + cmpEsc(p.descripcion) + ' <span style="color:#9ca3af">(' + pend + ' ' + cmpEsc(p.unidad) + ' pendientes)</span></div>'
      + '<div style="flex:1"><input type="number" class="form-input rec-cant" data-partida-id="' + p.id + '" min="0" max="' + pend + '" step="0.01" value="' + pend + '" style="text-align:right"></div>'
      + '</div>';
  });

  html += '<div class="form-row" style="margin-top:8px">'
    + '<div class="form-group"><label class="form-label">Fecha recepci&oacute;n</label><input type="date" id="recFecha" class="form-input" value="' + new Date().toISOString().substr(0,10) + '"></div>'
    + '<div class="form-group"><label class="form-label">Notas</label><input type="text" id="recNotas" class="form-input" placeholder="Opcional"></div>'
    + '</div>';

  html += '<div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px">'
    + '<button class="btn-sec" onclick="ModCompras._cerrarModal(\'modalRecepcion\')">Cancelar</button>'
    + '<button class="btn-prim" onclick="cmpGuardarRecepcion(' + oc_id + ')">Registrar</button>'
    + '</div>';

  var modal = document.getElementById('modalRecepcion');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'modalRecepcion';
    modal.className = 'modal-overlay';
    modal.innerHTML = '<div class="modal-box"><div class="modal-header"><div class="modal-title">Registrar recepci&oacute;n</div><button class="modal-close" onclick="ModCompras._cerrarModal(\'modalRecepcion\')">&times;</button></div><div class="modal-body" id="recBody"></div></div>';
    document.body.appendChild(modal);
  }
  document.getElementById('recBody').innerHTML = html;
  modal.style.display = 'flex';
}
window.cmpAbrirModalRecepcion = cmpAbrirModalRecepcion;

async function cmpGuardarRecepcion(oc_id) {
  var fecha  = (document.getElementById('recFecha') || {}).value || new Date().toISOString().substr(0,10);
  var notas  = (document.getElementById('recNotas') || {}).value || '';
  var detalle = [];
  document.querySelectorAll('.rec-cant').forEach(function(inp) {
    var cant = parseFloat(inp.value);
    if (cant > 0) detalle.push({oc_partida_id: parseInt(inp.dataset.partidaId), cantidad_recibida: cant});
  });
  if (!detalle.length) { alert('Indica al menos una cantidad mayor a 0'); return; }

  try {
    var r = await fetch('../api/ordenes_compra.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({accion:'registrar_entrega', orden_compra_id: oc_id, fecha_entrega: fecha, notas: notas, detalle: detalle})
    });
    var d = await r.json();
    if (d.ok) {
      cmpCerrarModal('modalRecepcion');
      var r2 = await fetch('../api/ordenes_compra.php?accion=detalle&id=' + oc_id);
      _detalle = await r2.json();
      _detTab  = 'recepcion';
      cmpRenderDetalle();
      await cmpCargar();
      if (d.cerro_oc) alert('OC cerrada automáticamente — todos los productos fueron recibidos.');
    } else { alert(d.error || 'Error'); }
  } catch(e) { alert('Error de conexion'); }
}
window.cmpGuardarRecepcion = cmpGuardarRecepcion;

// ── Cambiar estado ────────────────────────────────────────────
async function cmpCambiarEstado(oc_id, estado) {
  try {
    var r = await fetch('../api/ordenes_compra.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({accion:'cambiar_estado', orden_compra_id: oc_id, estado: estado})
    });
    var d = await r.json();
    if (d.ok) {
      var r2 = await fetch('../api/ordenes_compra.php?accion=detalle&id=' + oc_id);
      _detalle = await r2.json();
      cmpRenderDetalle();
      await cmpCargar();
    } else { alert(d.error || 'Error'); }
  } catch(e) { alert('Error de conexion'); }
}
window.cmpCambiarEstado = cmpCambiarEstado;

// ── Enviar correo OC (dir_admin) ──────────────────────────────
async function cmpEnviarCorreo(oc_id) {
  if (!confirm('Enviar correo de esta OC a los destinatarios configurados?')) return;
  try {
    var r = await fetch('../api/ordenes_compra.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({accion:'enviar_correo', orden_compra_id: oc_id})
    });
    var d = await r.json();
    if (d.ok) {
      var r2 = await fetch('../api/ordenes_compra.php?accion=detalle&id=' + oc_id);
      _detalle = await r2.json();
      cmpRenderDetalle();
      await cmpCargar();
      actualizarBadgeCompras();
    } else {
      alert(d.error || 'Error al enviar correo');
    }
  } catch(e) { alert('Error de conexion'); }
}
window.cmpEnviarCorreo = cmpEnviarCorreo;

// ── Paginar ───────────────────────────────────────────────────
function cmpPaginar(dir) { _cmpPage += dir; cmpFiltrar(); }
window.cmpPaginar = cmpPaginar;

// ── Helper escape ─────────────────────────────────────────────
function cmpEsc(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Init ──────────────────────────────────────────────────────
cmpCargar();

return {
  init:        cmpCargar,
  _cambiarTab: cmpCambiarTab,
  _filtrar:    cmpFiltrar,
  _nueva:      cmpNuevaOC,
  _ver:        cmpVerDetalle,
  _cerrarModal: cmpCerrarModal,
};
})();
</script>
