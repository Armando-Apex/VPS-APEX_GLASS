<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_inventario');
$puedeGestionar = in_array($user['rol'], ['dir_admin','administracion']);
$esDirAdmin     = $user['rol'] === 'dir_admin';
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=inventario'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.main { padding: 24px; max-width: 1300px; margin: 0 auto; }
.page-title { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 4px; }
.page-sub { font-size: 13px; color: #64748b; margin-bottom: 20px; }

/* Tabs */
.tabs { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 2px solid var(--c-border); }
.tab-btn {
  padding: 10px 20px; border: none; background: none; cursor: pointer;
  font-size: 13px; font-weight: 700; color: #64748b; border-bottom: 2px solid transparent;
  margin-bottom: -2px; transition: all .15s; letter-spacing: .3px;
}
.tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }
.tab-btn:hover:not(.active) { color: #374151; background: #f8fafc; border-radius: 6px 6px 0 0; }
.tab-panel { display: none; }
.tab-panel.active { display: block; }

/* KPI cards */
.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap: 14px; margin-bottom: 24px; }
.kpi-card {
  background: white; border-radius: 12px; padding: 18px 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,.06); border-top: 3px solid var(--c-border);
}
.kpi-card.alerta { border-top-color: var(--c-red); }
.kpi-card.ok     { border-top-color: var(--c-green); }
.kpi-card.info   { border-top-color: #2563eb; }
.kpi-card.warn   { border-top-color: #f59e0b; }
.kpi-num { font-size: 28px; font-weight: 800; color: #1e293b; line-height: 1; }
.kpi-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-top: 6px; }
.kpi-sub { font-size: 12px; color: #94a3b8; margin-top: 3px; }

/* Alertas banner */
.alerta-banner {
  background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px;
  padding: 12px 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
}
.alerta-banner .icon { font-size: 18px; flex-shrink: 0; }
.alerta-banner .txt { font-size: 13px; color: #991b1b; font-weight: 600; }

/* Tabla */
.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
.section-title { font-size: 15px; font-weight: 700; color: #1e293b; }
.table-wrap { background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; background: #f8fafc; border-bottom: 1px solid var(--c-border); white-space: nowrap; }
td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; color: #374151; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #f8fafc; }

/* Badges */
.badge { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; white-space: nowrap; }
.badge-ok    { background: #dcfce7; color: #16a34a; }
.badge-warn  { background: #fef9c3; color: #ca8a04; }
.badge-alerta{ background: #fee2e2; color: #dc2626; }
.badge-info  { background: #dbeafe; color: #2563eb; }
.badge-gray  { background: #f1f5f9; color: #64748b; }

/* Barra de stock */
.stock-bar-wrap { display: flex; align-items: center; gap: 8px; min-width: 120px; }
.stock-bar { flex: 1; height: 6px; background: var(--c-border); border-radius: 3px; overflow: hidden; }
.stock-fill { height: 100%; border-radius: 3px; }
.stock-fill.ok    { background: var(--c-green); }
.stock-fill.warn  { background: #f59e0b; }
.stock-fill.alerta{ background: var(--c-red); }
.stock-pct { font-size: 11px; font-weight: 700; color: #64748b; width: 36px; text-align: right; }

/* Botones */
.btn { padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn-primary { background: #2563eb; color: white; }
.btn-success { background: #16a34a; color: white; }
.btn-danger  { background: #dc2626; color: white; }
.btn-ghost   { background: #f1f5f9; color: #374151; }
.btn-sm { padding: 5px 10px; font-size: 12px; }
.btn-icon { background: none; border: none; cursor: pointer; padding: 4px 6px; border-radius: 6px; font-size: 15px; }
.btn-icon:hover { background: #f1f5f9; }

/* Modal */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:999; align-items:center; justify-content:center; }
.modal-overlay.open { display:flex; }
.modal { background:white; border-radius:16px; width:90%; max-width:540px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.modal-head { display:flex; align-items:center; justify-content:space-between; padding:18px 22px; border-bottom:1px solid var(--c-border); }
.modal-title { font-size:16px; font-weight:800; color:#1e293b; }
.modal-close { background:none; border:none; font-size:20px; color:#94a3b8; cursor:pointer; padding:2px 8px; border-radius:6px; }
.modal-close:hover { background:#f1f5f9; }
.modal-body { padding:20px 22px; }
.modal-footer { padding:14px 22px; border-top:1px solid var(--c-border); display:flex; gap:8px; justify-content:flex-end; }

/* Form */
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
.form-full { grid-column:1/-1; }
.form-group { display:flex; flex-direction:column; gap:5px; }
.form-label { font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:.3px; }
.form-input, .form-select, .form-textarea {
  padding:9px 12px; border:1px solid var(--c-border); border-radius:8px;
  font-size:13px; color:#1e293b; outline:none; background:white;
  transition:border-color .15s;
}
.form-input:focus, .form-select:focus, .form-textarea:focus { border-color:#2563eb; }
.form-hint { font-size:11px; color:#94a3b8; }

/* Compras detalle */
.detalle-compras { background:#f8fafc; border-radius:10px; padding:14px; margin-top:14px; }
.detalle-title { font-size:12px; font-weight:700; color:#374151; text-transform:uppercase; letter-spacing:.5px; margin-bottom:10px; }

/* Loading */
.loading-row td { text-align:center; padding:40px; color:#94a3b8; font-size:13px; }

/* Flete badge */
.flete-incluido { color:#16a34a; font-weight:700; }
.flete-cobrado  { color:#f59e0b; font-weight:700; }
.flete-propio   { color:var(--c-red); font-weight:700; }

@media(max-width:768px){
  .main { padding: 12px; }
  .page-title { font-size: 18px; }

  /* KPIs: 2 columnas */
  .kpi-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .kpi-card { padding: 12px 14px; }
  .kpi-num  { font-size: 22px; }
  .kpi-label{ font-size: 10px; }

  /* Tabs: scroll horizontal */
  .tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; gap: 0; }
  .tab-btn { white-space: nowrap; padding: 10px 14px; font-size: 12px; flex-shrink: 0; }

  /* Top bar dentro de cada tab */
  .top-bar { flex-direction: column; align-items: stretch; gap: 8px; }
  .top-bar .btn { width: 100%; text-align: center; }

  /* ── TAB STOCK: ocultar m²/lámina, Req.producción, Costo prom. */
  #inv-tab-stock thead th:nth-child(3),
  #inv-tab-stock thead th:nth-child(5),
  #inv-tab-stock thead th:nth-child(6),
  #inv-tab-stock tbody td:nth-child(3),
  #inv-tab-stock tbody td:nth-child(5),
  #inv-tab-stock tbody td:nth-child(6) { display: none; }

  /* ── TAB COMPRAS: ocultar Proveedor, Precio unit, Flete, Referencia, Registró */
  #inv-tab-compras thead th:nth-child(3),
  #inv-tab-compras thead th:nth-child(5),
  #inv-tab-compras thead th:nth-child(6),
  #inv-tab-compras thead th:nth-child(8),
  #inv-tab-compras thead th:nth-child(9),
  #inv-tab-compras tbody td:nth-child(3),
  #inv-tab-compras tbody td:nth-child(5),
  #inv-tab-compras tbody td:nth-child(6),
  #inv-tab-compras tbody td:nth-child(8),
  #inv-tab-compras tbody td:nth-child(9) { display: none; }

  /* ── TAB PROVEEDORES: ocultar Email, Notas */
  #inv-tab-proveedores thead th:nth-child(4),
  #inv-tab-proveedores thead th:nth-child(5),
  #inv-tab-proveedores tbody td:nth-child(4),
  #inv-tab-proveedores tbody td:nth-child(5) { display: none; }

  /* ── TAB OC: ocultar Partidas, Subtotal, Recepción */
  #inv-tab-oc thead th:nth-child(4),
  #inv-tab-oc thead th:nth-child(5),
  #inv-tab-oc thead th:nth-child(7),
  #inv-tab-oc tbody td:nth-child(4),
  #inv-tab-oc tbody td:nth-child(5),
  #inv-tab-oc tbody td:nth-child(7) { display: none; }

  /* ── TAB CALENDARIO: ocultar Fecha OC, Pagado, Días */
  #inv-tab-calendario thead th:nth-child(3),
  #inv-tab-calendario thead th:nth-child(5),
  #inv-tab-calendario thead th:nth-child(8),
  #inv-tab-calendario tbody td:nth-child(3),
  #inv-tab-calendario tbody td:nth-child(5),
  #inv-tab-calendario tbody td:nth-child(8) { display: none; }

  /* Celdas generales */
  th { padding: 8px 10px; font-size: 10px; }
  td { padding: 9px 10px; font-size: 12px; }

  /* Barra stock más compacta */
  .stock-bar-wrap { min-width: 70px; }

  /* Modales full width */
  .modal { width: 96%; margin: 8px; max-width: 100%; }
  .form-grid { grid-template-columns: 1fr; }
  .form-full { grid-column: 1; }
  .modal-footer { flex-direction: column; }
  .modal-footer .btn { width: 100%; text-align: center; }

  /* Alerta banner */
  .alerta-banner { flex-direction: column; gap: 6px; padding: 10px 12px; }
}
</style>

<div class="main">
  <div class="page-title">&#128230; Inventario</div>
  <div class="page-sub" id="invSub">Actualizando&#8230;</div>

  <!-- Banner alertas (solo si hay stock bajo) -->
  <div id="invAlertaBanner" style="display:none" class="alerta-banner">
    <div class="icon">&#9888;&#65039;</div>
    <div class="txt" id="invAlertaTxt"></div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn active" onclick="invTab('stock')">&#128200; Stock</button>
    <button class="tab-btn"        onclick="invTab('oc')">&#128203; &#211;rdenes de Compra</button>
    <button class="tab-btn"        onclick="invTab('calendario')">&#128197; Calendario de Pagos</button>
    <button class="tab-btn"        onclick="invTab('compras')">&#128230; Entradas</button>
    <?php if($puedeGestionar): ?>
    <button class="tab-btn"        onclick="invTab('consumo')">&#128228; Consumo Diario</button>
    <?php endif; ?>
    <button class="tab-btn"        onclick="invTab('proveedores')">&#127970; Proveedores</button>
  </div>

  <!-- ── TAB STOCK ─────────────────────────────────────────── -->
  <div id="inv-tab-stock" class="tab-panel active">
    <div class="kpi-grid" id="invKpis"></div>
    <div class="top-bar">
      <div class="section-title">L&#225;minas en inventario</div>
      <?php if($puedeGestionar): ?>
      <button class="btn btn-primary" onclick="invAbrirModalLamina()">+ Nueva l&#225;mina</button>
      <?php endif; ?>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Tipo</th><th>Espesor</th><th>Dimensi&#243;n</th>
          <th>m&#178; / l&#225;mina</th><th>Stock</th>
          <th>Stock m&#178;</th><th>Req. producci&#243;n</th>
          <th>Costo prom. m&#178;</th><th>Estado</th>
          <?php if($puedeGestionar): ?><th></th><?php endif; ?>
        </tr></thead>
        <tbody id="invTbodyStock"><tr class="loading-row"><td colspan="10">Cargando&#8230;</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── TAB COMPRAS ───────────────────────────────────────── -->
  <div id="inv-tab-compras" class="tab-panel">
    <div class="top-bar">
      <div class="section-title">Historial de compras</div>
      <?php if($puedeGestionar): ?>
      <button class="btn btn-success" onclick="invAbrirModalCompra()">+ Registrar compra</button>
      <?php endif; ?>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Fecha</th><th>L&#225;mina</th><th>Proveedor</th>
          <th>Cantidad</th><th>Precio unit. s/IVA</th>
          <th>Flete</th><th>Costo real unit.</th><th>Referencia</th><th>Registr&#243;</th>
        </tr></thead>
        <tbody id="invTbodyCompras"><tr class="loading-row"><td colspan="9">Cargando&#8230;</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── TAB CONSUMO ──────────────────────────────────────── -->
  <div id="inv-tab-consumo" class="tab-panel">
    <div class="top-bar">
      <div class="section-title">Consumo Diario de L&#225;minas</div>
      <button class="btn btn-primary" onclick="invAbrirModalConsumo()">+ Registrar uso</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Fecha</th><th>L&#225;mina</th><th>Dimensi&#243;n</th>
          <th>Cantidad</th><th>Notas</th><th>Registr&#243;</th>
        </tr></thead>
        <tbody id="invTbodyConsumo"><tr class="loading-row"><td colspan="6">Cargando&#8230;</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── TAB PROVEEDORES ───────────────────────────────────── -->
  <div id="inv-tab-proveedores" class="tab-panel">
    <div class="top-bar">
      <div class="section-title">Proveedores</div>
      <?php if($puedeGestionar): ?>
      <button class="btn btn-primary" onclick="invAbrirModalProveedor()">+ Nuevo proveedor</button>
      <?php endif; ?>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Nombre</th><th>Contacto</th><th>Tel&#233;fono</th>
          <th>Email</th><th>Notas</th><th>Estado</th>
          <?php if($puedeGestionar): ?><th></th><?php endif; ?>
        </tr></thead>
        <tbody id="invTbodyProv"><tr class="loading-row"><td colspan="7">Cargando&#8230;</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── TAB OC ─────────────────────────────────────────────── -->
  <div id="inv-tab-oc" class="tab-panel">
    <div class="top-bar">
      <div class="section-title">&#211;rdenes de Compra</div>
      <?php if($puedeGestionar): ?>
      <button class="btn btn-primary" onclick="ocAbrirModalNueva()">+ Nueva OC</button>
      <?php endif; ?>
    </div>
    <!-- Filtros de estado -->
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">
      <button class="btn btn-ghost btn-sm oc-filtro active" data-estado="" onclick="ocFiltrar(this,'')">Todas</button>
      <button class="btn btn-ghost btn-sm oc-filtro" data-estado="borrador" onclick="ocFiltrar(this,'borrador')">Borrador</button>
      <button class="btn btn-ghost btn-sm oc-filtro" data-estado="abierta" onclick="ocFiltrar(this,'abierta')">Abiertas</button>
      <button class="btn btn-ghost btn-sm oc-filtro" data-estado="cerrada" onclick="ocFiltrar(this,'cerrada')">Cerradas</button>
      <button class="btn btn-ghost btn-sm oc-filtro" data-estado="pagada" onclick="ocFiltrar(this,'pagada')">Pagadas</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>No. OC</th><th>Fecha</th><th>Proveedor</th>
          <th>Partidas</th><th>Subtotal s/IVA</th><th>Total c/IVA</th>
          <th>Recepci&#243;n</th><th>Pago programado</th><th>Estado</th>
          <?php if($puedeGestionar): ?><th></th><?php endif; ?>
        </tr></thead>
        <tbody id="invTbodyOC"><tr class="loading-row"><td colspan="10">Cargando&#8230;</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- ── TAB CALENDARIO ────────────────────────────────────── -->
  <div id="inv-tab-calendario" class="tab-panel">
    <div class="top-bar">
      <div class="section-title">&#128197; Calendario de Pagos &#8212; Materia Prima</div>
    </div>
    <!-- KPIs calendario -->
    <div class="kpi-grid" id="calKpis" style="margin-bottom:20px"></div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>No. OC</th><th>Proveedor</th><th>Fecha OC</th>
          <th>Total c/IVA</th><th>Pagado</th><th>Saldo</th>
          <th>Fecha l&#237;mite pago</th><th>D&#237;as</th><th>Estado</th>
          <?php if($puedeGestionar): ?><th></th><?php endif; ?>
        </tr></thead>
        <tbody id="invTbodyCal"><tr class="loading-row"><td colspan="10">Cargando&#8230;</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<!-- ── MODAL LÁMINA ─────────────────────────────────────────── -->
<div class="modal-overlay" id="modalLamina">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title" id="modalLaminaTitulo">Nueva l&#225;mina</div>
      <button class="modal-close" onclick="invCerrarModal('modalLamina')">&#215;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="laminaId">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Tipo *</label>
          <select id="laminaTipo" class="form-select">
            <option value="claro">Claro</option>
            <option value="claro_zafiro">Claro Zafiro</option>
            <option value="filtrasol">Filtrasol</option>
            <option value="espejo">Espejo</option>
            <option value="espejo_aluminio">Espejo Aluminio</option>
            <option value="laminado_claro">Laminado Claro</option>
            <option value="reflecta">Reflecta</option>
            <option value="satinado">Satinado</option>
            <option value="tintex">Tintex</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Espesor (mm) *</label>
          <input type="number" id="laminaEspesor" class="form-input" step="0.5" min="1" placeholder="ej: 6">
        </div>
        <div class="form-group">
          <label class="form-label">Ancho (mm) *</label>
          <input type="number" id="laminaAncho" class="form-input" placeholder="ej: 3660">
        </div>
        <div class="form-group">
          <label class="form-label">Alto (mm) *</label>
          <input type="number" id="laminaAlto" class="form-input" placeholder="ej: 2440">
        </div>
        <div class="form-group form-full">
          <label class="form-label">Notas</label>
          <input type="text" id="laminaNotas" class="form-input" placeholder="Observaciones opcionales">
        </div>
      </div>
      <div id="laminaM2Preview" style="margin-top:12px;font-size:13px;color:#2563eb;font-weight:700;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="invCerrarModal('modalLamina')">Cancelar</button>
      <button class="btn btn-primary" onclick="invGuardarLamina()">Guardar</button>
    </div>
  </div>
</div>

<!-- ── MODAL COMPRA ─────────────────────────────────────────── -->
<div class="modal-overlay" id="modalCompra">
  <div class="modal" style="max-width:600px">
    <div class="modal-head">
      <div class="modal-title">Registrar compra</div>
      <button class="modal-close" onclick="invCerrarModal('modalCompra')">&#215;</button>
    </div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">L&#225;mina *</label>
          <select id="compraLamina" class="form-select"></select>
        </div>
        <div class="form-group">
          <label class="form-label">Proveedor *</label>
          <select id="compraProveedor" class="form-select"></select>
        </div>
        <div class="form-group">
          <label class="form-label">Fecha compra *</label>
          <input type="date" id="compraFecha" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Cantidad (l&#225;minas) *</label>
          <input type="number" id="compraCantidad" class="form-input" min="1" placeholder="ej: 20">
        </div>
        <div class="form-group">
          <label class="form-label">Precio unit. sin IVA *</label>
          <input type="number" id="compraPrecio" class="form-input" step="0.01" min="0" placeholder="$ por l&#225;mina">
        </div>
        <div class="form-group">
          <label class="form-label">Tipo de flete</label>
          <select id="compraFleteTipo" class="form-select" onchange="invToggleFlete()">
            <option value="incluido">Incluido en precio</option>
            <option value="cobrado">Cobrado por separado</option>
            <option value="propio">Yo consigo el flete</option>
          </select>
        </div>
        <div class="form-group" id="grupoCostoFlete" style="display:none">
          <label class="form-label">Costo flete total ($)</label>
          <input type="number" id="compraCostoFlete" class="form-input" step="0.01" min="0" value="0">
        </div>
        <div class="form-group">
          <label class="form-label">Referencia / Factura</label>
          <input type="text" id="compraReferencia" class="form-input" placeholder="# factura o remisi&#243;n">
        </div>
        <div class="form-group form-full">
          <label class="form-label">Notas</label>
          <input type="text" id="compraNotas" class="form-input" placeholder="Observaciones">
        </div>
      </div>
      <div id="compraPreview" style="margin-top:14px;padding:12px;background:#f0f9ff;border-radius:8px;display:none">
        <div style="font-size:12px;font-weight:700;color:#0369a1;margin-bottom:4px">COSTO REAL ESTIMADO</div>
        <div id="compraPreviewTxt" style="font-size:14px;color:#1e293b;font-weight:700"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="invCerrarModal('modalCompra')">Cancelar</button>
      <button class="btn btn-success" onclick="invGuardarCompra()">Registrar compra</button>
    </div>
  </div>
</div>

<!-- ── MODAL REGISTRAR ENTREGA ──────────────────────────────── -->
<div class="modal-overlay" id="modalEntrega">
  <div class="modal" style="max-width:600px">
    <div class="modal-head">
      <div class="modal-title" id="modalEntregaTitulo">Registrar Entrega</div>
      <button class="modal-close" onclick="invCerrarModal('modalEntrega')">&#215;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="entregaOcId">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Fecha de entrega *</label>
          <input type="date" id="entregaFecha" class="form-input">
        </div>
        <div class="form-group form-full">
          <label class="form-label">Notas</label>
          <input type="text" id="entregaNotas" class="form-input" placeholder="Observaciones opcionales">
        </div>
      </div>
      <div style="margin-top:16px">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:10px">
          Cantidad recibida por partida
        </div>
        <div id="entregaPartidas"></div>
      </div>
      <div id="entregaPreview" style="margin-top:14px;padding:12px;background:#f0fdf4;border-radius:8px;display:none">
        <div style="font-size:12px;font-weight:700;color:#16a34a;margin-bottom:4px">&#10003; Esta entrega completa la OC</div>
        <div style="font-size:12px;color:#374151" id="entregaPreviewTxt"></div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="invCerrarModal('modalEntrega')">Cancelar</button>
      <button class="btn btn-success" onclick="entregaGuardar()">Registrar entrega</button>
    </div>
  </div>
</div>

<!-- ── MODAL NUEVA OC ──────────────────────────────────────── -->
<div class="modal-overlay" id="modalNuevaOC">
  <div class="modal" style="max-width:560px">
    <div class="modal-head">
      <div class="modal-title">Nueva Orden de Compra</div>
      <button class="modal-close" onclick="invCerrarModal('modalNuevaOC')">&#215;</button>
    </div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">No. OC</label>
          <input type="text" id="ocNumero" class="form-input" placeholder="Auto: APEX-XXXX">
          <span class="form-hint">Dejar vac&#237;o para generar autom&#225;ticamente</span>
        </div>
        <div class="form-group">
          <label class="form-label">Fecha *</label>
          <input type="date" id="ocFecha" class="form-input">
        </div>
        <div class="form-group form-full">
          <label class="form-label">Proveedor *</label>
          <select id="ocProveedor" class="form-select"></select>
        </div>
        <div class="form-group">
          <label class="form-label">D&#237;as de cr&#233;dito</label>
          <input type="number" id="ocDiasCredito" class="form-input" value="0" min="0">
        </div>
        <div class="form-group form-full">
          <label class="form-label">Notas</label>
          <textarea id="ocNotas" class="form-textarea" rows="2" placeholder="Observaciones"></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="invCerrarModal('modalNuevaOC')">Cancelar</button>
      <button class="btn btn-primary" onclick="ocGuardarNueva()">Crear OC</button>
    </div>
  </div>
</div>

<!-- ── MODAL DETALLE OC ─────────────────────────────────────── -->
<div class="modal-overlay" id="modalDetalleOC">
  <div class="modal" style="max-width:900px;width:96%">
    <div class="modal-head">
      <div class="modal-title" id="modalOCTitulo">Detalle OC</div>
      <button class="modal-close" onclick="invCerrarModal('modalDetalleOC')">&#215;</button>
    </div>
    <div class="modal-body" id="modalOCBody" style="padding:20px">Cargando&#8230;</div>
  </div>
</div>

<!-- ── MODAL REGISTRAR PAGO ─────────────────────────────────── -->
<div class="modal-overlay" id="modalPago">
  <div class="modal" style="max-width:480px">
    <div class="modal-head">
      <div class="modal-title">Registrar Pago</div>
      <button class="modal-close" onclick="invCerrarModal('modalPago')">&#215;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="pagoOcId">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Fecha de pago *</label>
          <input type="date" id="pagoFecha" class="form-input">
        </div>
        <div class="form-group">
          <label class="form-label">Monto *</label>
          <input type="number" id="pagoMonto" class="form-input" step="0.01" placeholder="$">
        </div>
        <div class="form-group">
          <label class="form-label">&#191;Incluye IVA?</label>
          <select id="pagoIva" class="form-select">
            <option value="1">S&#237;, incluye IVA</option>
            <option value="0">No, es sin IVA</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Referencia</label>
          <input type="text" id="pagoRef" class="form-input" placeholder="# transferencia / cheque">
        </div>
        <div class="form-group form-full">
          <label class="form-label">Notas</label>
          <input type="text" id="pagoNotas" class="form-input">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="invCerrarModal('modalPago')">Cancelar</button>
      <button class="btn btn-success" onclick="ocGuardarPago()">Registrar pago</button>
    </div>
  </div>
</div>

<!-- ── MODAL DISTRIBUIR FLETE ────────────────────────────────── -->
<div class="modal-overlay" id="modalDistribuirFlete">
  <div class="modal" style="max-width:600px">
    <div class="modal-head">
      <div class="modal-title" id="dfTitulo">Distribuir flete</div>
      <button class="modal-close" onclick="invCerrarModal('modalDistribuirFlete')">&#215;</button>
    </div>
    <div class="modal-body">
      <div style="display:flex;align-items:center;gap:16px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;margin-bottom:16px">
        <span style="font-size:22px">&#128666;</span>
        <div>
          <div style="font-size:11px;color:#92400e;text-transform:uppercase;font-weight:700">Total flete a distribuir</div>
          <div style="font-size:20px;font-weight:800;color:#78350f" id="dfMontoFlete">$0.00</div>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
        <label style="font-size:13px;font-weight:600;color:#374151">M&#233;todo de distribuci&#243;n:</label>
        <select id="dfMetodo" class="form-select" style="width:auto" onchange="dfPreview()">
          <option value="lamina">Por l&#225;mina (igual entre todas)</option>
          <option value="m2">Por m&#178; (proporcional al &#225;rea)</option>
        </select>
      </div>
      <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:8px">Compras en inventario</div>
      <div id="dfComprasLista" style="max-height:320px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px"></div>
      <div id="dfPreview" style="display:none;margin-top:12px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 14px;font-size:13px;color:#0369a1;font-weight:600">
        <span id="dfPreviewTxt"></span>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="invCerrarModal('modalDistribuirFlete')">Cancelar</button>
      <button class="btn btn-success" onclick="dfConfirmar()">&#10003; Distribuir y cerrar OC</button>
    </div>
  </div>
</div>

<!-- ── MODAL PROVEEDOR ──────────────────────────────────────── -->
<div class="modal-overlay" id="modalProveedor">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-title" id="modalProvTitulo">Nuevo proveedor</div>
      <button class="modal-close" onclick="invCerrarModal('modalProveedor')">&#215;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="provId">
      <div class="form-grid">
        <div class="form-group form-full">
          <label class="form-label">Nombre *</label>
          <input type="text" id="provNombre" class="form-input" placeholder="Nombre del proveedor">
        </div>
        <div class="form-group">
          <label class="form-label">Contacto</label>
          <input type="text" id="provContacto" class="form-input" placeholder="Nombre del contacto">
        </div>
        <div class="form-group">
          <label class="form-label">Tel&#233;fono</label>
          <input type="text" id="provTelefono" class="form-input" placeholder="81 1234 5678">
        </div>
        <div class="form-group form-full">
          <label class="form-label">Email</label>
          <input type="email" id="provEmail" class="form-input" placeholder="correo@proveedor.com">
        </div>
        <div class="form-group form-full">
          <label class="form-label">Notas</label>
          <textarea id="provNotas" class="form-textarea" rows="2" placeholder="Observaciones"></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="invCerrarModal('modalProveedor')">Cancelar</button>
      <button class="btn btn-primary" onclick="invGuardarProveedor()">Guardar</button>
    </div>
  </div>
</div>

<!-- ── MODAL CONSUMO DIARIO ──────────────────────────────── -->
<div class="modal-overlay" id="modalConsumo">
  <div class="modal" style="max-width:480px">
    <div class="modal-head">
      <div class="modal-title">Registrar Uso de L&#225;minas</div>
      <button class="modal-close" onclick="invCerrarModal('modalConsumo')">&#215;</button>
    </div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group form-full">
          <label class="form-label">L&#225;mina *</label>
          <select id="consumoLaminaId" class="form-select">
            <option value="">Selecciona una l&#225;mina&#8230;</option>
          </select>
          <div id="consumoStockInfo" style="font-size:12px;color:#64748b;margin-top:4px"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Cantidad usada *</label>
          <input type="number" id="consumoCantidad" class="form-input" min="1" value="1" inputmode="numeric">
        </div>
        <div class="form-group">
          <label class="form-label">Fecha</label>
          <input type="date" id="consumoFecha" class="form-input" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group form-full">
          <label class="form-label">Notas (opcional)</label>
          <textarea id="consumoNotas" class="form-textarea" rows="2" placeholder="Ej: corte pedidos ma&#241;ana, merma, etc."></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="invCerrarModal('modalConsumo')">Cancelar</button>
      <button class="btn btn-primary" onclick="invRegistrarConsumo()">Registrar</button>
    </div>
  </div>
</div>

<script>
var ModInventario = (function(){

var puedeGestionar = <?= $puedeGestionar ? 'true' : 'false' ?>;
var esDirAdmin     = <?= $esDirAdmin ? 'true' : 'false' ?>;
var _tabActual = 'stock';
var _laminas   = [];
var _proveedores = [];
var _ocEstado = '';
var _entregaPartidas = [];

// ── Tabs ─────────────────────────────────────────────────────
function invTab(t) {
  _tabActual = t;
  var tabs = puedeGestionar
    ? ['stock','oc','calendario','compras','consumo','proveedores']
    : ['stock','oc','calendario','compras','proveedores'];
  document.querySelectorAll('.tab-btn').forEach(function(b,i){
    b.classList.toggle('active', tabs[i] === t);
  });
  document.querySelectorAll('.tab-panel').forEach(function(p){ p.classList.remove('active'); });
  document.getElementById('inv-tab-'+t).classList.add('active');
  if (t === 'consumo') cargarConsumo();
  if (t === 'stock')       cargarStock();
  if (t === 'oc')          cargarOC();
  if (t === 'calendario')  cargarCalendario();
  if (t === 'compras')     cargarCompras();
  if (t === 'proveedores') cargarProveedores();
}

// ── Formatters ───────────────────────────────────────────────
var $ = function(id){ return document.getElementById(id); };
// fmt, fmtPeso, escAttr — definidos en utils.js
var tipoLabel = {
  claro:'Claro', claro_zafiro:'Claro Zafiro', filtrasol:'Filtrasol',
  espejo:'Espejo', espejo_aluminio:'Espejo Aluminio', laminado_claro:'Laminado Claro',
  reflecta:'Reflecta', satinado:'Satinado', tintex:'Tintex'
};
var fleteLabel = {
  incluido:'<span class="flete-incluido">&#10003; Incluido</span>',
  cobrado: '<span class="flete-cobrado">&#36; Cobrado</span>',
  propio:  '<span class="flete-propio">&#128666; Propio</span>'
};
// fmtFecha — definida en utils.js

// ── Cargar Stock ─────────────────────────────────────────────
async function cargarStock() {
  try {
    var r = await fetch('../api/laminas.php?accion=stock');
    var d = await r.json();
    _laminas = d.laminas || [];

    var total    = _laminas.length;
    var alertas  = _laminas.filter(function(l){ return l.alerta_stock == 1; }).length;
    var totalM2  = _laminas.reduce(function(s,l){ return s + parseFloat(l.stock_m2||0); }, 0);
    var reqM2    = _laminas.reduce(function(s,l){ return s + parseFloat(l.m2_requeridos||0); }, 0);

    $('invKpis').innerHTML =
      '<div class="kpi-card info"><div class="kpi-num">'+total+'</div><div class="kpi-label">Tipos de l&#225;mina</div></div>'+
      '<div class="kpi-card '+(alertas>0?'alerta':'ok')+'"><div class="kpi-num">'+alertas+'</div><div class="kpi-label">Stock bajo</div><div class="kpi-sub">'+(alertas>0?'Requieren atenci&#243;n':'Todo en orden')+'</div></div>'+
      '<div class="kpi-card info"><div class="kpi-num">'+fmt(totalM2,1)+'</div><div class="kpi-label">m&#178; disponibles</div></div>'+
      '<div class="kpi-card warn"><div class="kpi-num">'+fmt(reqM2,1)+'</div><div class="kpi-label">m&#178; en producci&#243;n</div><div class="kpi-sub">Piezas pendiente + en corte</div></div>';

    if (alertas > 0) {
      var nombres = _laminas.filter(function(l){ return l.alerta_stock==1; })
        .map(function(l){ return (tipoLabel[l.tipo]||l.tipo)+' '+l.espesor_mm+'mm'; }).join(', ');
      $('invAlertaBanner').style.display = 'flex';
      $('invAlertaTxt').textContent = 'Stock bajo en: '+nombres;
    } else {
      $('invAlertaBanner').style.display = 'none';
    }

    if (!_laminas.length) {
      $('invTbodyStock').innerHTML = '<tr class="loading-row"><td colspan="10">Sin l&#225;minas registradas</td></tr>';
      return;
    }

    $('invTbodyStock').innerHTML = _laminas.map(function(l){
      var stock    = parseInt(l.stock_laminas)||0;
      var stockM2  = parseFloat(l.stock_m2)||0;
      var rM2      = parseFloat(l.m2_requeridos)||0;
      var pct      = l.pct_stock;
      var cls      = l.alerta_stock==1 ? 'alerta' : (pct!==null && pct<150 ? 'warn' : 'ok');
      var barW     = pct !== null ? Math.min(pct, 100) : 100;
      var estadoBadge = l.alerta_stock==1
        ? '<span class="badge badge-alerta">&#9888;&#65039; Stock bajo</span>'
        : '<span class="badge badge-ok">&#10003; OK</span>';
      var acciones = puedeGestionar ? '<button class="btn-icon" title="Editar" onclick="invEditarLamina('+l.id+')">&#9998;</button>' : '';
      return '<tr>'+
        '<td><strong>'+(tipoLabel[l.tipo]||l.tipo)+'</strong></td>'+
        '<td>'+l.espesor_mm+' mm</td>'+
        '<td style="white-space:nowrap">'+((l.ancho_mm/10).toFixed(0))+' &#215; '+((l.alto_mm/10).toFixed(0))+' cm</td>'+
        '<td>'+fmt(l.m2,2)+' m&#178;</td>'+
        '<td><strong>'+stock+'</strong> l&#225;m.</td>'+
        '<td><div class="stock-bar-wrap"><div class="stock-bar"><div class="stock-fill '+cls+'" style="width:'+barW+'%"></div></div><div class="stock-pct">'+(pct !== null ? pct+'%' : '&#8212;')+'</div></div><div style="font-size:11px;color:#64748b;margin-top:2px">'+fmt(stockM2,1)+' m&#178;</div></td>'+
        '<td>'+(rM2>0 ? fmt(rM2,1)+' m&#178;' : '<span style="color:#94a3b8">&#8212;</span>')+'</td>'+
        '<td>'+fmtPeso(l.costo_prom_m2)+'/m&#178;</td>'+
        '<td>'+estadoBadge+'</td>'+
        (puedeGestionar ? '<td>'+acciones+'</td>' : '')+
        '</tr>';
    }).join('');

    $('invSub').textContent = 'Actualizado a las '+new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  } catch(e) { console.error(e); }
}

// ── Cargar Compras ───────────────────────────────────────────
async function cargarCompras() {
  try {
    var r = await fetch('../api/inventario.php?accion=compras');
    var d = await r.json();
    var compras = d.compras || [];
    if (!compras.length) {
      $('invTbodyCompras').innerHTML = '<tr class="loading-row"><td colspan="9">Sin compras registradas</td></tr>';
      return;
    }
    $('invTbodyCompras').innerHTML = compras.map(function(c){
      return '<tr>'+
        '<td>'+fmtFecha(c.fecha_compra)+'</td>'+
        '<td><strong>'+(tipoLabel[c.lamina_tipo]||c.lamina_tipo)+'</strong><div style="font-size:11px;color:#64748b">'+c.lamina_espesor+'mm &middot; '+((c.lamina_ancho/10).toFixed(0))+'&#215;'+((c.lamina_alto/10).toFixed(0))+'cm</div></td>'+
        '<td>'+(c.proveedor_nombre||'&#8212;')+'</td>'+
        '<td><strong>'+c.cantidad_laminas+'</strong> l&#225;m.</td>'+
        '<td>'+fmtPeso(c.precio_unitario)+'</td>'+
        '<td>'+(fleteLabel[c.flete_tipo]||c.flete_tipo)+(parseFloat(c.costo_flete_total)>0 ? '<div style="font-size:11px;color:#64748b">'+fmtPeso(c.costo_flete_total)+' total</div>' : '')+'</td>'+
        '<td><strong>'+fmtPeso(c.costo_real_unitario)+'</strong></td>'+
        '<td style="font-size:12px;color:#64748b">'+(c.referencia||'&#8212;')+'</td>'+
        '<td style="font-size:12px;color:#94a3b8">'+(c.registrado_por||'&#8212;')+'</td>'+
        '</tr>';
    }).join('');
  } catch(e) { console.error(e); }
}

// ── Cargar Proveedores ───────────────────────────────────────
async function cargarProveedores() {
  try {
    var r = await fetch('../api/proveedores.php');
    var d = await r.json();
    _proveedores = d.proveedores || [];
    if (!_proveedores.length) {
      $('invTbodyProv').innerHTML = '<tr class="loading-row"><td colspan="7">Sin proveedores registrados</td></tr>';
      return;
    }
    $('invTbodyProv').innerHTML = _proveedores.map(function(p){
      return '<tr>'+
        '<td><strong>'+p.nombre+'</strong></td>'+
        '<td>'+(p.contacto||'&#8212;')+'</td>'+
        '<td>'+(p.telefono||'&#8212;')+'</td>'+
        '<td>'+(p.email||'&#8212;')+'</td>'+
        '<td style="font-size:12px;color:#64748b">'+(p.notas||'&#8212;')+'</td>'+
        '<td>'+(p.activo==1 ? '<span class="badge badge-ok">Activo</span>' : '<span class="badge badge-gray">Inactivo</span>')+'</td>'+
        (puedeGestionar ? '<td><button class="btn-icon" title="Editar" onclick="invEditarProveedor('+p.id+')">&#9998;</button></td>' : '')+
        '</tr>';
    }).join('');
  } catch(e) { console.error(e); }
}

// ── Modales helpers ──────────────────────────────────────────
function invCerrarModal(id) { document.getElementById(id).classList.remove('open'); }
function invAbrirModal(id)  { document.getElementById(id).classList.add('open'); }

document.querySelectorAll('.modal-overlay').forEach(function(m){
  m.addEventListener('click', function(e){ if(e.target===m) m.classList.remove('open'); });
});

// ── Modal Lámina ─────────────────────────────────────────────
function invAbrirModalLamina() {
  $('laminaId').value = '';
  $('modalLaminaTitulo').textContent = 'Nueva l\u00e1mina';
  $('laminaTipo').value = 'claro';
  $('laminaEspesor').value = '';
  $('laminaAncho').value = '';
  $('laminaAlto').value = '';
  $('laminaNotas').value = '';
  $('laminaM2Preview').innerHTML = '';
  invAbrirModal('modalLamina');
}

function invEditarLamina(id) {
  var l = _laminas.find(function(x){ return x.id == id; });
  if (!l) return;
  $('laminaId').value      = l.id;
  $('modalLaminaTitulo').textContent = 'Editar l\u00e1mina';
  $('laminaTipo').value    = l.tipo;
  $('laminaEspesor').value = l.espesor_mm;
  $('laminaAncho').value   = l.ancho_mm;
  $('laminaAlto').value    = l.alto_mm;
  $('laminaNotas').value   = l.notas||'';
  invActualizarM2Preview();
  invAbrirModal('modalLamina');
}

function invActualizarM2Preview() {
  var a = parseFloat($('laminaAncho').value)||0;
  var b = parseFloat($('laminaAlto').value)||0;
  if (a && b) {
    var m2 = (a * b / 1000000).toFixed(4);
    $('laminaM2Preview').innerHTML = '&#128204; Esta l&#225;mina tiene <strong>'+m2+' m&#178;</strong>';
  } else {
    $('laminaM2Preview').innerHTML = '';
  }
}
$('laminaAncho').addEventListener('input', invActualizarM2Preview);
$('laminaAlto').addEventListener('input', invActualizarM2Preview);

async function invGuardarLamina() {
  var id = $('laminaId').value;
  var payload = {
    accion:      id ? 'actualizar' : 'crear',
    id:          id ? parseInt(id) : undefined,
    tipo:        $('laminaTipo').value,
    espesor_mm:  parseFloat($('laminaEspesor').value),
    ancho_mm:    parseInt($('laminaAncho').value),
    alto_mm:     parseInt($('laminaAlto').value),
    notas:       $('laminaNotas').value,
  };
  if (!payload.tipo || !payload.espesor_mm || !payload.ancho_mm || !payload.alto_mm)
    return alert('Completa todos los campos obligatorios');
  var r = await fetch('../api/laminas.php', {
    method: id ? 'PUT' : 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  var d = await r.json();
  if (d.error) return alert(d.error);
  invCerrarModal('modalLamina');
  cargarStock();
}

// ── Modal Compra ─────────────────────────────────────────────
async function invAbrirModalCompra() {
  var rl = await fetch('../api/laminas.php');
  var dl = await rl.json();
  var rp = await fetch('../api/proveedores.php');
  var dp = await rp.json();
  $('compraLamina').innerHTML = (dl.laminas||[]).map(function(l){
    return '<option value="'+l.id+'">'+(tipoLabel[l.tipo]||l.tipo)+' '+l.espesor_mm+'mm '+((l.ancho_mm/10).toFixed(0))+'x'+((l.alto_mm/10).toFixed(0))+'cm</option>';
  }).join('');
  $('compraProveedor').innerHTML = (dp.proveedores||[]).map(function(p){
    return '<option value="'+p.id+'">'+p.nombre+'</option>';
  }).join('');
  $('compraFecha').value = new Date().toISOString().slice(0,10);
  $('compraCantidad').value = '';
  $('compraPrecio').value = '';
  $('compraFleteTipo').value = 'incluido';
  $('compraCostoFlete').value = '0';
  $('compraReferencia').value = '';
  $('compraNotas').value = '';
  $('grupoCostoFlete').style.display = 'none';
  $('compraPreview').style.display = 'none';
  invAbrirModal('modalCompra');
}

function invToggleFlete() {
  var tipo = $('compraFleteTipo').value;
  $('grupoCostoFlete').style.display = tipo !== 'incluido' ? 'block' : 'none';
  invActualizarPreviewCompra();
}

function invActualizarPreviewCompra() {
  var cant   = parseInt($('compraCantidad').value)||0;
  var precio = parseFloat($('compraPrecio').value)||0;
  var flete  = parseFloat($('compraCostoFlete').value)||0;
  if (!cant || !precio) { $('compraPreview').style.display='none'; return; }
  var costoReal = precio + (flete / cant);
  var total     = costoReal * cant;
  $('compraPreview').style.display = 'block';
  $('compraPreviewTxt').innerHTML = 'Costo real por l&#225;mina: <strong>$'+fmt(costoReal)+'</strong> &nbsp;&nbsp; Total compra: <strong>$'+fmt(total)+'</strong>';
}
$('compraCantidad').addEventListener('input', invActualizarPreviewCompra);
$('compraPrecio').addEventListener('input', invActualizarPreviewCompra);
$('compraCostoFlete').addEventListener('input', invActualizarPreviewCompra);

async function invGuardarCompra() {
  var payload = {
    accion:            'registrar_compra',
    lamina_id:         parseInt($('compraLamina').value),
    proveedor_id:      parseInt($('compraProveedor').value),
    fecha_compra:      $('compraFecha').value,
    cantidad_laminas:  parseInt($('compraCantidad').value),
    precio_unitario:   parseFloat($('compraPrecio').value),
    flete_tipo:        $('compraFleteTipo').value,
    costo_flete_total: parseFloat($('compraCostoFlete').value)||0,
    referencia:        $('compraReferencia').value,
    notas:             $('compraNotas').value,
  };
  if (!payload.lamina_id || !payload.proveedor_id || !payload.cantidad_laminas || !payload.precio_unitario)
    return alert('Completa los campos obligatorios');
  var r = await fetch('../api/inventario.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  var d = await r.json();
  if (d.error) return alert(d.error);
  invCerrarModal('modalCompra');
  cargarCompras();
  cargarStock();
}

// ── Modal Proveedor ──────────────────────────────────────────
function invAbrirModalProveedor() {
  $('provId').value = '';
  $('modalProvTitulo').textContent = 'Nuevo proveedor';
  $('provNombre').value = ''; $('provContacto').value = '';
  $('provTelefono').value = ''; $('provEmail').value = '';
  $('provNotas').value = '';
  invAbrirModal('modalProveedor');
}

function invEditarProveedor(id) {
  var p = _proveedores.find(function(x){ return x.id == id; });
  if (!p) return;
  $('provId').value       = p.id;
  $('modalProvTitulo').textContent = 'Editar proveedor';
  $('provNombre').value   = p.nombre||'';
  $('provContacto').value = p.contacto||'';
  $('provTelefono').value = p.telefono||'';
  $('provEmail').value    = p.email||'';
  $('provNotas').value    = p.notas||'';
  invAbrirModal('modalProveedor');
}

async function invGuardarProveedor() {
  var id = $('provId').value;
  var payload = {
    accion:   id ? 'actualizar' : 'crear',
    id:       id ? parseInt(id) : undefined,
    nombre:   $('provNombre').value.trim(),
    contacto: $('provContacto').value.trim(),
    telefono: $('provTelefono').value.trim(),
    email:    $('provEmail').value.trim(),
    notas:    $('provNotas').value.trim(),
  };
  if (!payload.nombre) return alert('El nombre es obligatorio');
  var r = await fetch('../api/proveedores.php', {
    method: id ? 'PUT' : 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(payload)
  });
  var d = await r.json();
  if (d.error) return alert(d.error);
  invCerrarModal('modalProveedor');
  cargarProveedores();
}

// ── OC: Cargar lista ────────────────────────────────────────
async function cargarOC() {
  try {
    var url = '../api/ordenes_compra.php' + (_ocEstado ? '?estado='+_ocEstado : '');
    var r = await fetch(url);
    var d = await r.json();
    var ocs = d.ordenes || [];
    if (!ocs.length) {
      $('invTbodyOC').innerHTML = '<tr class="loading-row"><td colspan="10">Sin &#243;rdenes de compra</td></tr>';
      return;
    }
    var estadoClr = { borrador:'badge-gray', abierta:'badge-info', cerrada:'badge-warn', pagada:'badge-ok' };
    var estadoLbl = { borrador:'Borrador', abierta:'Abierta', cerrada:'Cerrada', pagada:'Pagada' };
    $('invTbodyOC').innerHTML = ocs.map(function(o){
      var pct     = o.total_ordenado > 0 ? Math.round(o.total_recibido / o.total_ordenado * 100) : 0;
      var barCls  = pct >= 100 ? 'ok' : (pct > 0 ? 'warn' : 'alerta');
      var diasPago = parseInt(o.dias_para_pago);
      var pagoClr  = o.fecha_pago_programada ? (diasPago < 0 ? '#dc2626' : diasPago <= 7 ? '#f59e0b' : '#16a34a') : '#94a3b8';
      var pagoTxt  = o.fecha_pago_programada ? (diasPago < 0 ? Math.abs(diasPago)+' d&#237;as vencida' : diasPago === 0 ? 'Hoy' : diasPago+' d&#237;as') : '&#8212;';
      return '<tr>'+
        '<td><strong style="color:#2563eb;cursor:pointer" onclick="ocVerDetalle('+o.id+')">'+o.numero_oc+'</strong></td>'+
        '<td>'+fmtFecha(o.fecha_oc)+'</td>'+
        '<td>'+o.proveedor+'</td>'+
        '<td style="text-align:center">'+o.num_partidas+'</td>'+
        '<td>'+fmtPeso(o.subtotal_sin_iva)+'</td>'+
        '<td><strong>'+fmtPeso(o.total_con_iva)+'</strong></td>'+
        '<td style="min-width:120px"><div class="stock-bar-wrap"><div class="stock-bar"><div class="stock-fill '+barCls+'" style="width:'+pct+'%"></div></div><div class="stock-pct">'+pct+'%</div></div></td>'+
        '<td style="color:'+pagoClr+';font-weight:600;font-size:12px">'+(o.fecha_pago_programada ? fmtFecha(o.fecha_pago_programada)+'<br><small>'+pagoTxt+'</small>' : '&#8212;')+'</td>'+
        '<td><span class="badge '+(estadoClr[o.estado]||'badge-gray')+'">'+(estadoLbl[o.estado]||o.estado)+'</span></td>'+
        (puedeGestionar ? '<td style="white-space:nowrap"><button class="btn-icon" title="Ver detalle" onclick="ocVerDetalle('+o.id+')">&#128203;</button>'+(o.estado==='cerrada' ? '<button class="btn-icon" title="Registrar pago" onclick="ocAbrirPago('+o.id+')">&#128176;</button>' : '')+(o.es_solo_flete && o.estado==='abierta' ? '<button class="btn-icon" title="Distribuir flete a inventario" style="color:#f59e0b" data-df-id="'+escAttr(o.id)+'" data-df-oc="'+escAttr(o.numero_oc)+'" onclick="ocAbrirDistribuir(this.dataset.dfId,this.dataset.dfOc)">&#128666;</button>' : '')+'</td>' : '<td></td>')+
        '</tr>';
    }).join('');
  } catch(e) { console.error(e); }
}

function ocFiltrar(btn, estado) {
  _ocEstado = estado;
  document.querySelectorAll('.oc-filtro').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  cargarOC();
}

// ── OC: Calendario de pagos ──────────────────────────────────
async function cargarCalendario() {
  try {
    var r = await fetch('../api/ordenes_compra.php?accion=calendario_pagos');
    var d = await r.json();
    var pagos = d.pagos || [];

    var venc  = pagos.filter(function(p){ return parseInt(p.dias_para_pago) < 0; });
    var prox7 = pagos.filter(function(p){ var dd = parseInt(p.dias_para_pago); return dd >= 0 && dd <= 7; });
    var total30 = pagos.filter(function(p){ return parseInt(p.dias_para_pago) <= 30 && parseInt(p.dias_para_pago) >= 0; })
                       .reduce(function(s,p){ return s + (parseFloat(p.total_con_iva) - parseFloat(p.pagado)); }, 0);

    $('calKpis').innerHTML =
      '<div class="kpi-card '+(venc.length > 0 ? 'alerta' : 'ok')+'"><div class="kpi-num">'+venc.length+'</div><div class="kpi-label">Pagos vencidos</div></div>'+
      '<div class="kpi-card '+(prox7.length > 0 ? 'warn' : 'ok')+'"><div class="kpi-num">'+prox7.length+'</div><div class="kpi-label">Vencen en 7 d&#237;as</div></div>'+
      '<div class="kpi-card info"><div class="kpi-num">'+fmtPeso(total30)+'</div><div class="kpi-label">Por pagar en 30 d&#237;as</div></div>';

    if (!pagos.length) {
      $('invTbodyCal').innerHTML = '<tr class="loading-row"><td colspan="9">Sin pagos programados</td></tr>';
      return;
    }

    $('invTbodyCal').innerHTML = pagos.map(function(p){
      var dias  = parseInt(p.dias_para_pago);
      var saldo = parseFloat(p.total_con_iva) - parseFloat(p.pagado);
      var clr   = dias < 0 ? '#dc2626' : dias <= 7 ? '#f59e0b' : '#16a34a';
      var lbl   = dias < 0 ? Math.abs(dias)+' d&#237;as vencida' : dias === 0 ? 'Hoy' : dias+' d&#237;as';
      return '<tr>'+
        '<td><strong style="color:#2563eb;cursor:pointer" onclick="ocVerDetalle('+p.id+')">'+p.numero_oc+'</strong></td>'+
        '<td>'+p.proveedor+'</td>'+
        '<td>'+fmtFecha(p.fecha_oc)+'</td>'+
        '<td>'+fmtPeso(p.total_con_iva)+'</td>'+
        '<td style="color:#16a34a">'+fmtPeso(p.pagado)+'</td>'+
        '<td><strong style="color:'+(saldo > 0 ? '#dc2626' : '#16a34a')+'">'+fmtPeso(saldo)+'</strong></td>'+
        '<td>'+fmtFecha(p.fecha_pago_programada)+'</td>'+
        '<td style="color:'+clr+';font-weight:700">'+lbl+'</td>'+
        '<td><span class="badge '+(p.estado==='pagada'?'badge-ok':'badge-warn')+'">'+p.estado+'</span></td>'+
        (puedeGestionar ? '<td>'+(p.estado==='cerrada' ? '<button class="btn-icon" title="Registrar pago" onclick="ocAbrirPago('+p.id+')">&#128176;</button>' : '')+'</td>' : '<td></td>')+
        '</tr>';
    }).join('');
  } catch(e) { console.error(e); }
}

// ── OC: Ver detalle ──────────────────────────────────────────
async function ocVerDetalle(id) {
  $('modalOCTitulo').textContent = 'Cargando&#8230;';
  $('modalOCBody').innerHTML = '<div style="text-align:center;padding:32px;color:#94a3b8">Cargando&#8230;</div>';
  invAbrirModal('modalDetalleOC');
  try {
    var r = await fetch('../api/ordenes_compra.php?accion=detalle&id='+id);
    var oc = await r.json();
    $('modalOCTitulo').textContent = oc.numero_oc + ' \u2014 ' + oc.proveedor_nombre;

    var subtotal = (oc.partidas||[]).reduce(function(s,p){ return s + parseFloat(p.importe||0); }, 0);
    var iva      = subtotal * 0.16;
    var total    = subtotal + iva;
    var estadoBadge = { borrador:'badge-gray', abierta:'badge-info', cerrada:'badge-warn', pagada:'badge-ok' };
    var puedeEditarHeader = (oc.estado === 'borrador' && puedeGestionar) ||
                           (oc.estado === 'abierta'  && esDirAdmin);

    var html =
      '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:18px">'+
      '<div><span style="font-size:11px;color:#94a3b8;text-transform:uppercase">Proveedor</span><br><strong>'+oc.proveedor_nombre+'</strong><br><span style="font-size:12px;color:#64748b">'+(oc.proveedor_telefono||'')+'</span></div>'+
      '<div><span style="font-size:11px;color:#94a3b8;text-transform:uppercase">Fecha OC</span><br><strong>'+fmtFecha(oc.fecha_oc)+'</strong></div>'+
      '<div><span style="font-size:11px;color:#94a3b8;text-transform:uppercase">Estado</span><br>'+
        '<span class="badge '+(estadoBadge[oc.estado]||'badge-gray')+'">'+oc.estado+'</span>'+
        (oc.estado==='borrador' && puedeGestionar ? '<button class="btn btn-ghost btn-sm" style="margin-top:4px" onclick="ocCambiarEstado('+oc.id+',\'abierta\')">Abrir OC</button>' : '')+
      '</div>'+
      '<div><span style="font-size:11px;color:#94a3b8;text-transform:uppercase">Cr&#233;dito</span><br><strong>'+oc.dias_credito+' d&#237;as</strong></div>'+
      '<div><span style="font-size:11px;color:#94a3b8;text-transform:uppercase">Fecha pago</span><br><strong style="color:#f59e0b">'+(fmtFecha(oc.fecha_pago_programada)||'&#8212;')+'</strong></div>'+
      '<div><span style="font-size:11px;color:#94a3b8;text-transform:uppercase">Total c/IVA</span><br><strong style="font-size:18px">'+fmtPeso(total)+'</strong></div>'+
      '</div>'+

      '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">'+
        '<div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#64748b">Partidas</div>'+
        '<div style="display:flex;gap:8px">'+
          (puedeEditarHeader ? '<button class="btn btn-primary btn-sm" onclick="ocAbrirAgregarPartida('+oc.id+')">+ Agregar partida</button>' : '')+
          (oc.estado==='abierta' && puedeGestionar ? '<button class="btn btn-success btn-sm" onclick="ocAbrirEntrega('+oc.id+')">&#128230; Registrar entrega</button>' : '')+
        '</div>'+
      '</div>'+
      '<table class="rd-modal-table" style="margin-bottom:16px">'+
        '<thead><tr><th>#</th><th>Descripci&#243;n</th><th>Tipo</th><th>Cantidad</th><th>Precio unit.</th><th>Importe</th><th>Recibido</th><th></th></tr></thead>'+
        '<tbody>'+
        (oc.partidas||[]).map(function(p){
          var noRecibida = parseFloat(p.cantidad_recibida||0) === 0;
          var puedeEditarPartida = puedeEditarHeader && noRecibida;
          var descEsc = p.descripcion.replace(/'/g,"\\'");
          return '<tr>'+
            '<td>'+p.numero_partida+'</td>'+
            '<td>'+p.descripcion+'</td>'+
            '<td><span class="badge '+(p.tipo==='lamina'?'badge-info':p.tipo==='flete'?'badge-warn':'badge-gray')+'">'+p.tipo+'</span></td>'+
            '<td>'+p.cantidad+' '+p.unidad+'</td>'+
            '<td>'+fmtPeso(p.precio_unitario)+'</td>'+
            '<td>'+fmtPeso(p.importe)+'</td>'+
            '<td>'+(parseFloat(p.cantidad_recibida||0)>0 ? '<strong style="color:#16a34a">'+p.cantidad_recibida+'</strong>' : '0')+'/'+p.cantidad+'</td>'+
            '<td style="white-space:nowrap">'+
              (puedeEditarPartida ? '<button class="btn-icon" title="Editar" onclick="npEditar('+oc.id+','+p.id+','+p.cantidad+','+p.precio_unitario+',\''+descEsc+'\',\''+p.unidad+'\')">&#9998;</button>' : '')+
              (puedeEditarPartida ? '<button class="btn-icon" title="Eliminar partida" style="color:#dc2626" onclick="npEliminar('+oc.id+','+p.id+',\''+descEsc+'\')">&#128465;</button>' : '')+
            '</td>'+
            '</tr>';
        }).join('')+
        '<tr style="background:#f8fafc;font-weight:700"><td colspan="5" style="text-align:right">Subtotal s/IVA:</td><td colspan="2">'+fmtPeso(subtotal)+'</td></tr>'+
        '<tr style="background:#f8fafc"><td colspan="5" style="text-align:right">IVA (16%):</td><td colspan="2">'+fmtPeso(iva)+'</td></tr>'+
        '<tr style="background:#f8fafc;font-weight:800;font-size:15px"><td colspan="5" style="text-align:right">TOTAL:</td><td colspan="2">'+fmtPeso(total)+'</td></tr>'+
        '</tbody>'+
      '</table>';

    if ((oc.entregas||[]).length) {
      html += '<div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:8px">Entregas recibidas</div>'+
        '<table class="rd-modal-table" style="margin-bottom:16px"><thead><tr><th>Fecha</th><th>Detalle</th><th>Registr&#243;</th><th>Cierra OC</th></tr></thead><tbody>'+
        (oc.entregas||[]).map(function(e){
          return '<tr><td>'+fmtFecha(e.fecha_entrega)+'</td><td style="font-size:12px">'+(e.resumen||'&#8212;')+'</td><td style="color:#64748b">'+(e.registrado_por||'&#8212;')+'</td><td>'+(e.cierra_oc==1?'<span class="badge badge-ok">&#10003; S&#237;</span>':'&#8212;')+'</td></tr>';
        }).join('')+
        '</tbody></table>';
    }

    if ((oc.pagos||[]).length) {
      html += '<div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#64748b;margin-bottom:8px">Pagos</div>'+
        '<table class="rd-modal-table"><thead><tr><th>Fecha</th><th>Monto</th><th>IVA</th><th>Referencia</th><th>Registr&#243;</th></tr></thead><tbody>'+
        (oc.pagos||[]).map(function(pg){
          return '<tr><td>'+fmtFecha(pg.fecha_pago)+'</td><td><strong>'+fmtPeso(pg.monto)+'</strong></td><td>'+(pg.incluye_iva==1?'Con IVA':'Sin IVA')+'</td><td style="color:#64748b">'+(pg.referencia||'&#8212;')+'</td><td style="color:#64748b">'+(pg.registrado_por||'&#8212;')+'</td></tr>';
        }).join('')+
        '</tbody></table>';
    }

    $('modalOCBody').innerHTML = html;
  } catch(e) { $('modalOCBody').innerHTML = '<div style="color:#dc2626;padding:20px">Error al cargar</div>'; }
}

// ── OC: Crear nueva ──────────────────────────────────────────
async function ocAbrirModalNueva() {
  var rc = await fetch('../api/ordenes_compra.php?accion=consecutivo');
  var dc = await rc.json();
  $('ocNumero').placeholder = 'Auto: ' + (dc.numero_oc||'');
  $('ocNumero').value = '';
  $('ocFecha').value = new Date().toISOString().slice(0,10);
  $('ocDiasCredito').value = 0;
  $('ocNotas').value = '';
  var rp = await fetch('../api/proveedores.php');
  var dp = await rp.json();
  $('ocProveedor').innerHTML = '<option value="">Seleccionar proveedor...</option>' +
    (dp.proveedores||[]).map(function(p){ return '<option value="'+p.id+'">'+p.nombre+'</option>'; }).join('');
  invAbrirModal('modalNuevaOC');
}

async function ocGuardarNueva() {
  var payload = {
    accion:       'crear',
    numero_oc:    $('ocNumero').value.trim(),
    proveedor_id: parseInt($('ocProveedor').value),
    fecha_oc:     $('ocFecha').value,
    dias_credito: parseInt($('ocDiasCredito').value)||0,
    notas:        $('ocNotas').value.trim(),
  };
  if (!payload.proveedor_id || !payload.fecha_oc) return alert('Proveedor y fecha son obligatorios');
  var r = await fetch('../api/ordenes_compra.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
  var d = await r.json();
  if (d.error) return alert(d.error);
  invCerrarModal('modalNuevaOC');
  cargarOC();
  alert('OC ' + d.numero_oc + ' creada. Ahora puedes agregar las partidas desde el detalle.');
  ocVerDetalle(d.id);
  invAbrirModal('modalDetalleOC');
}

// ── OC: Agregar partida ─────────────────────────────────────
async function ocAbrirAgregarPartida(ocId) {
  var rl = await fetch('../api/laminas.php');
  var dl = await rl.json();
  var laminas = dl.laminas || [];
  var optsLam = laminas.map(function(l){
    return '<option value="'+l.id+'" data-desc="'+(tipoLabel[l.tipo]||l.tipo)+' '+l.espesor_mm+'MM - '+Math.round(l.ancho_mm/10)+'x'+Math.round(l.alto_mm/10)+'">'+(tipoLabel[l.tipo]||l.tipo)+' '+l.espesor_mm+'mm '+Math.round(l.ancho_mm/10)+'&#215;'+Math.round(l.alto_mm/10)+'cm</option>';
  }).join('');
  var html = '<div style="padding:4px 0 16px">'+
    '<div style="font-size:13px;font-weight:700;color:#1e293b;margin-bottom:12px">Nueva partida</div>'+
    '<div class="form-grid">'+
      '<div class="form-group form-full"><label class="form-label">Tipo *</label>'+
        '<select id="npTipo" class="form-select" onchange="npToggleTipo()"><option value="lamina">L&aacute;mina</option><option value="flete">Flete / Entrega</option><option value="otro">Otro servicio</option></select></div>'+
      '<div class="form-group form-full" id="npGrupoLamina"><label class="form-label">L&aacute;mina</label>'+
        '<select id="npLamina" class="form-select" onchange="npDescripcionAuto()"><option value="">Seleccionar...</option>'+optsLam+'</select></div>'+
      '<div class="form-group form-full"><label class="form-label">Descripci&oacute;n *</label>'+
        '<input type="text" id="npDesc" class="form-input" placeholder="Descripci&oacute;n del producto o servicio"></div>'+
      '<div class="form-group"><label class="form-label">Unidad</label><input type="text" id="npUnidad" class="form-input" value="LAMINA"></div>'+
      '<div class="form-group"><label class="form-label">Cantidad *</label><input type="number" id="npCantidad" class="form-input" min="1" step="1" oninput="npPreview()"></div>'+
      '<div class="form-group"><label class="form-label">Precio unit. s/IVA *</label><input type="number" id="npPrecio" class="form-input" step="0.01" min="0" oninput="npPreview()"></div>'+
      '<div class="form-group form-full" id="npPreviewBox" style="display:none"><div style="background:#f0f9ff;border-radius:8px;padding:10px;font-size:13px;color:#0369a1;font-weight:700" id="npPreviewTxt"></div></div>'+
    '</div>'+
    '<div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px">'+
      '<button class="btn btn-ghost" onclick="ocVerDetalle('+ocId+')">Cancelar</button>'+
      '<button class="btn btn-primary" onclick="npGuardar('+ocId+')">Agregar partida</button>'+
    '</div></div>';
  $('modalOCTitulo').textContent = 'Agregar partida';
  $('modalOCBody').innerHTML = html;
}

function npToggleTipo() {
  var tipo = $('npTipo').value;
  $('npGrupoLamina').style.display = tipo === 'lamina' ? 'block' : 'none';
  if (tipo === 'flete') { $('npDesc').value = 'SERVICIO DE ENTREGA'; $('npUnidad').value = 'FLETE'; }
  if (tipo === 'lamina') { $('npUnidad').value = 'LAMINA'; }
}

function npDescripcionAuto() {
  var sel = $('npLamina');
  var opt = sel.options[sel.selectedIndex];
  if (opt && opt.dataset.desc) $('npDesc').value = opt.dataset.desc;
  npPreview();
}

function npPreview() {
  var cant = parseFloat($('npCantidad').value)||0;
  var prec = parseFloat($('npPrecio').value)||0;
  if (!cant || !prec) { $('npPreviewBox').style.display='none'; return; }
  var imp = cant * prec;
  $('npPreviewBox').style.display = 'block';
  $('npPreviewTxt').textContent = 'Importe: $' + imp.toLocaleString('es-MX',{minimumFractionDigits:2}) + ' s/IVA  |  Con IVA: $' + (imp*1.16).toLocaleString('es-MX',{minimumFractionDigits:2});
}

async function npGuardar(ocId) {
  var tipo = $('npTipo').value;
  var payload = {
    accion:          'agregar_partida',
    orden_compra_id: ocId,
    tipo:            tipo,
    lamina_id:       tipo==='lamina' ? (parseInt($('npLamina').value)||null) : null,
    descripcion:     $('npDesc').value.trim(),
    unidad:          $('npUnidad').value.trim()||'LAMINA',
    cantidad:        parseFloat($('npCantidad').value),
    precio_unitario: parseFloat($('npPrecio').value),
  };
  if (!payload.descripcion || !payload.cantidad || !payload.precio_unitario)
    return alert('Completa todos los campos obligatorios');
  var r = await fetch('../api/ordenes_compra.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
  var d = await r.json();
  if (d.error) return alert(d.error);
  ocVerDetalle(ocId);
  cargarOC();
}

function npEditar(ocId, partId, cantidad, precio, desc, unidad) {
  var nuevaCant   = prompt('Cantidad:', cantidad);
  if (nuevaCant === null) return;
  var nuevoPrecio = prompt('Precio unitario s/IVA:', precio);
  if (nuevoPrecio === null) return;
  var nuevaDesc   = prompt('Descripci\u00f3n:', desc);
  if (nuevaDesc === null) return;
  fetch('../api/ordenes_compra.php', {
    method: 'PUT', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({accion:'actualizar_partida', id:partId, descripcion:nuevaDesc, unidad:unidad, cantidad:parseFloat(nuevaCant), precio_unitario:parseFloat(nuevoPrecio)})
  }).then(function(r){ return r.json(); }).then(function(d){ if(d.error) return alert(d.error); ocVerDetalle(ocId); cargarOC(); });
}

async function npEliminar(ocId, partId, desc) {
  if (!confirm('¿Eliminar partida "' + desc + '"?\n\nEsta acción no se puede deshacer.')) return;
  var r = await fetch('../api/ordenes_compra.php', {
    method: 'DELETE', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({accion:'eliminar_partida', id:partId})
  });
  var d = await r.json();
  if (d.error) return alert(d.error);
  ocVerDetalle(ocId);
  cargarOC();
}

async function ocCambiarEstado(ocId, estado) {
  var r = await fetch('../api/ordenes_compra.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({accion:'cambiar_estado', orden_compra_id:ocId, estado:estado})});
  var d = await r.json();
  if (d.error) return alert(d.error);
  ocVerDetalle(ocId);
  cargarOC();
}

// ── OC: Registrar entrega ────────────────────────────────────
async function ocAbrirEntrega(ocId) {
  $('entregaOcId').value = ocId;
  $('entregaFecha').value = new Date().toISOString().slice(0,10);
  $('entregaNotas').value = '';
  $('entregaPreview').style.display = 'none';
  var r = await fetch('../api/ordenes_compra.php?accion=detalle&id='+ocId);
  var oc = await r.json();
  $('modalEntregaTitulo').textContent = 'Registrar entrega \u2014 '+oc.numero_oc;
  _entregaPartidas = (oc.partidas||[]).filter(function(p){ return p.tipo==='lamina' && parseFloat(p.cantidad_recibida) < parseFloat(p.cantidad); });
  if (!_entregaPartidas.length) {
    $('entregaPartidas').innerHTML = '<div style="color:#16a34a;padding:12px">&#10003; Todas las partidas ya fueron recibidas</div>';
  } else {
    $('entregaPartidas').innerHTML = _entregaPartidas.map(function(p){
      var pendiente = parseFloat(p.cantidad) - parseFloat(p.cantidad_recibida);
      return '<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f1f5f9">'+
        '<div style="flex:1"><div style="font-size:13px;font-weight:600">'+p.descripcion+'</div>'+
        '<div style="font-size:11px;color:#64748b">Pendiente: '+pendiente+' '+p.unidad+'</div></div>'+
        '<div style="display:flex;align-items:center;gap:6px">'+
        '<input type="number" id="ent_'+p.id+'" class="form-input" style="width:90px" min="0" max="'+pendiente+'" step="1" value="'+pendiente+'" oninput="entregaActualizarPreview('+ocId+')">'+
        '<span style="font-size:12px;color:#64748b">'+p.unidad+'</span></div></div>';
    }).join('');
    setTimeout(function(){ entregaActualizarPreview(ocId); }, 100);
  }
  invCerrarModal('modalDetalleOC');
  invAbrirModal('modalEntrega');
}

function entregaActualizarPreview(ocId) {
  var todasCompletas = true;
  _entregaPartidas.forEach(function(p){
    var inp = document.getElementById('ent_'+p.id);
    var cant = parseFloat(inp ? inp.value : 0)||0;
    var pendiente = parseFloat(p.cantidad) - parseFloat(p.cantidad_recibida);
    if (cant < pendiente) todasCompletas = false;
  });
  if (todasCompletas && _entregaPartidas.length > 0) {
    $('entregaPreview').style.display = 'block';
    $('entregaPreviewTxt').textContent = 'La OC se cerrar\u00e1 autom\u00e1ticamente y se calcular\u00e1 la fecha de pago.';
  } else {
    $('entregaPreview').style.display = 'none';
  }
}

async function entregaGuardar() {
  var ocId  = parseInt($('entregaOcId').value);
  var fecha = $('entregaFecha').value;
  var notas = $('entregaNotas').value.trim();
  if (!fecha) return alert('La fecha es obligatoria');
  var detalle = _entregaPartidas
    .map(function(p){ return {oc_partida_id:p.id, cantidad_recibida:parseFloat(document.getElementById('ent_'+p.id) ? document.getElementById('ent_'+p.id).value : 0)||0}; })
    .filter(function(d){ return d.cantidad_recibida > 0; });
  if (!detalle.length) return alert('Ingresa al menos una cantidad mayor a 0');
  var r = await fetch('../api/ordenes_compra.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({accion:'registrar_entrega', orden_compra_id:ocId, fecha_entrega:fecha, notas:notas, detalle:detalle})
  });
  var d = await r.json();
  if (d.error) return alert(d.error);
  invCerrarModal('modalEntrega');
  cargarOC();
  cargarStock();
  if (d.cerro_oc) alert('&#10003; Entrega registrada. La OC qued\u00f3 completamente recibida. Se calcul\u00f3 la fecha de pago.');
  ocVerDetalle(ocId);
  invAbrirModal('modalDetalleOC');
}

// ── Pago ─────────────────────────────────────────────────────
function ocAbrirPago(ocId) {
  $('pagoOcId').value = ocId;
  $('pagoFecha').value = new Date().toISOString().slice(0,10);
  $('pagoMonto').value = '';
  $('pagoRef').value = '';
  $('pagoNotas').value = '';
  invAbrirModal('modalPago');
}

async function ocGuardarPago() {
  var payload = {
    accion:          'registrar_pago',
    orden_compra_id: parseInt($('pagoOcId').value),
    fecha_pago:      $('pagoFecha').value,
    monto:           parseFloat($('pagoMonto').value),
    incluye_iva:     parseInt($('pagoIva').value),
    referencia:      $('pagoRef').value.trim(),
    notas:           $('pagoNotas').value.trim(),
  };
  if (!payload.monto) return alert('El monto es obligatorio');
  var r = await fetch('../api/ordenes_compra.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)});
  var d = await r.json();
  if (d.error) return alert(d.error);
  invCerrarModal('modalPago');
  cargarOC();
  cargarCalendario();
}

// ── OC: Distribuir flete sin referencia ─────────────────────
var _dfOcId = 0, _dfCompras = [], _dfMetodo = 'lamina', _dfTotalFlete = 0;

async function ocAbrirDistribuir(ocId, numeroOc) {
  _dfOcId = ocId;
  _dfCompras = [];
  _dfMetodo  = 'lamina';
  $('dfTitulo').textContent = 'Distribuir flete — ' + numeroOc;
  $('dfMetodo').value = 'lamina';
  $('dfComprasLista').innerHTML = '<div style="color:#94a3b8;padding:12px">Cargando&#8230;</div>';
  $('dfPreview').style.display = 'none';
  invAbrirModal('modalDistribuirFlete');
  try {
    var r = await fetch('../api/ordenes_compra.php?accion=compras_para_flete&oc_id=' + ocId);
    var d = await r.json();
    _dfTotalFlete = parseFloat(d.total_flete || 0);
    var compras   = d.compras || [];
    $('dfMontoFlete').textContent = fmtPeso(_dfTotalFlete);
    if (!compras.length) {
      $('dfComprasLista').innerHTML = '<div style="color:#94a3b8;padding:12px">No hay compras en inventario con stock disponible.</div>';
      return;
    }
    var ocAnterior = null;
    $('dfComprasLista').innerHTML = compras.map(function(c) {
      var header = '';
      var ocLabel = c.oc_numero ? c.oc_numero : 'Sin OC';
      if (ocLabel !== ocAnterior) {
        header = '<div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#64748b;padding:8px 0 4px">' + ocLabel + '</div>';
        ocAnterior = ocLabel;
      }
      return header +
        '<label style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9;cursor:pointer">'+
        '<input type="checkbox" class="df-check" data-id="'+c.id+'" data-cant="'+c.cantidad_laminas+'" data-m2="'+c.m2+'" checked onchange="dfPreview()">'+
        '<div style="flex:1">'+
          '<div style="font-size:13px;font-weight:600">'+c.lamina_desc+'</div>'+
          '<div style="font-size:11px;color:#64748b">'+c.cantidad_laminas+' lám · '+c.m2+' m²/lám · Costo actual: '+fmtPeso(c.costo_real_unitario)+'/lám</div>'+
        '</div>'+
        '<div id="df_prev_'+c.id+'" style="font-size:12px;color:#2563eb;font-weight:600;min-width:90px;text-align:right"></div>'+
        '</label>';
    }).join('');
    dfPreview();
  } catch(e) { $('dfComprasLista').innerHTML = '<div style="color:#dc2626;padding:12px">Error al cargar compras.</div>'; }
}

function dfPreview() {
  _dfMetodo = $('dfMetodo').value;
  var checks = document.querySelectorAll('.df-check:checked');
  if (!checks.length || _dfTotalFlete <= 0) {
    $('dfPreview').style.display = 'none';
    document.querySelectorAll('[id^="df_prev_"]').forEach(function(el){ el.textContent = ''; });
    return;
  }
  var totalBase = 0;
  checks.forEach(function(ch) {
    totalBase += _dfMetodo === 'm2'
      ? parseFloat(ch.dataset.cant) * parseFloat(ch.dataset.m2)
      : parseFloat(ch.dataset.cant);
  });
  document.querySelectorAll('[id^="df_prev_"]').forEach(function(el){ el.textContent = ''; });
  var lineas = [];
  checks.forEach(function(ch) {
    var base_c = _dfMetodo === 'm2'
      ? parseFloat(ch.dataset.cant) * parseFloat(ch.dataset.m2)
      : parseFloat(ch.dataset.cant);
    var flete_este   = _dfTotalFlete * (base_c / totalBase);
    var flete_x_lam  = flete_este / parseFloat(ch.dataset.cant);
    var el = document.getElementById('df_prev_' + ch.dataset.id);
    if (el) el.textContent = '+' + fmtPeso(flete_x_lam) + '/lám';
    lineas.push('+' + fmtPeso(flete_x_lam) + '/lám');
  });
  $('dfPreview').style.display = 'block';
  $('dfPreviewTxt').textContent = 'Distribución lista. Al confirmar se actualizará el costo real de las ' + checks.length + ' compra(s) seleccionada(s) y se cerrará la OC de flete.';
}

async function dfConfirmar() {
  var checks = document.querySelectorAll('.df-check:checked');
  if (!checks.length) return alert('Selecciona al menos una compra');
  var compra_ids = Array.from(checks).map(function(ch){ return parseInt(ch.dataset.id); });
  if (!confirm('¿Distribuir $' + _dfTotalFlete.toLocaleString('es-MX', {minimumFractionDigits:2}) + ' de flete entre ' + compra_ids.length + ' compra(s) y cerrar la OC?')) return;
  var r = await fetch('../api/ordenes_compra.php', {
    method: 'POST', headers: {'Content-Type':'application/json'},
    body: JSON.stringify({accion:'distribuir_flete', oc_id:_dfOcId, compra_ids:compra_ids, metodo:_dfMetodo})
  });
  var d = await r.json();
  if (d.error) return alert(d.error);
  invCerrarModal('modalDistribuirFlete');
  cargarOC();
  cargarStock();
  alert('✅ Flete de ' + fmtPeso(d.total_flete) + ' distribuido en ' + d.registros + ' compra(s). La OC quedó cerrada.');
}

// ── Consumo Diario ────────────────────────────────────────────
async function cargarConsumo() {
  var tbody = document.getElementById('invTbodyConsumo');
  if (!tbody) return;
  tbody.innerHTML = '<tr class="loading-row"><td colspan="6">Cargando&#8230;</td></tr>';
  try {
    var r = await fetch('../api/inventario.php?accion=movimientos');
    var d = await r.json();
    var movs = d.movimientos || [];
    if (!movs.length) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:32px;color:#94a3b8">Sin registros de consumo</td></tr>';
      return;
    }
    tbody.innerHTML = movs.map(function(m) {
      var dim    = escAttr(m.lamina_ancho) + 'x' + escAttr(m.lamina_alto) + 'mm';
      var nombre = escAttr(m.lamina_tipo) + ' ' + escAttr(m.lamina_espesor) + 'mm';
      return '<tr>' +
        '<td>' + escAttr(m.fecha) + '</td>' +
        '<td><strong>' + nombre + '</strong></td>' +
        '<td style="color:#64748b">' + dim + '</td>' +
        '<td><span class="badge badge-info">' + escAttr(m.cantidad_laminas) + ' l&#225;m.</span></td>' +
        '<td style="color:#64748b;font-size:12px">' + (m.notas ? escAttr(m.notas) : '&#8212;') + '</td>' +
        '<td style="color:#94a3b8;font-size:12px">' + (m.operador_nombre ? escAttr(m.operador_nombre) : '&#8212;') + '</td>' +
      '</tr>';
    }).join('');
  } catch(e) {
    tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#dc2626;padding:20px">Error al cargar</td></tr>';
  }
}

function invAbrirModalConsumo() {
  var sel = document.getElementById('consumoLaminaId');
  sel.innerHTML = '<option value="">Cargando&#8230;</option>';
  document.getElementById('consumoStockInfo').textContent = '';
  document.getElementById('consumoCantidad').value = '1';
  document.getElementById('consumoNotas').value = '';
  document.getElementById('consumoFecha').value = new Date().toISOString().split('T')[0];

  fetch('../api/laminas.php?accion=stock')
    .then(function(r){ return r.json(); })
    .then(function(d){
      var lams = (d.laminas || []).filter(function(l){ return parseFloat(l.stock_laminas) > 0; });
      if (!lams.length) {
        sel.innerHTML = '<option value="">Sin stock disponible</option>';
        return;
      }
      sel.innerHTML = '<option value="">Selecciona una l&#225;mina&#8230;</option>' +
        lams.map(function(l){
          var stock = Math.floor(parseFloat(l.stock_laminas));
          return '<option value="' + l.id + '" data-stock="' + stock + '">' +
            l.tipo + ' ' + l.espesor_mm + 'mm — ' + l.ancho_mm + 'x' + l.alto_mm + 'mm' +
            ' (Stock: ' + stock + ')' +
          '</option>';
        }).join('');
      sel.onchange = consumoActualizarStock;
    })
    .catch(function(){ sel.innerHTML = '<option value="">Error al cargar</option>'; });

  document.getElementById('modalConsumo').classList.add('open');
}

function consumoActualizarStock() {
  var sel  = document.getElementById('consumoLaminaId');
  var opt  = sel.options[sel.selectedIndex];
  var info = document.getElementById('consumoStockInfo');
  if (!sel.value) { info.textContent = ''; return; }
  var stock = parseInt(opt.dataset.stock || 0);
  info.textContent = 'Disponibles en stock: ' + stock + ' lámina' + (stock !== 1 ? 's' : '');
  info.style.color = stock > 3 ? '#16a34a' : stock > 0 ? '#ca8a04' : '#dc2626';
}

async function invRegistrarConsumo() {
  var laminaId = parseInt(document.getElementById('consumoLaminaId').value);
  var cantidad = parseInt(document.getElementById('consumoCantidad').value);
  var fecha    = document.getElementById('consumoFecha').value;
  var notas    = document.getElementById('consumoNotas').value.trim();

  if (!laminaId)             return alert('Selecciona una lámina');
  if (!cantidad || cantidad < 1) return alert('Ingresa una cantidad válida');
  if (!fecha)                return alert('Ingresa la fecha');

  var btn = document.querySelector('#modalConsumo .btn-primary');
  btn.disabled = true;
  btn.textContent = 'Registrando…';

  try {
    var r = await fetch('../api/inventario.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({accion:'registrar_uso', lamina_id:laminaId, cantidad_laminas:cantidad, fecha:fecha, notas:notas, ordenes:[]})
    });
    var d = await r.json();
    if (d.error) { alert(d.error); return; }
    invCerrarModal('modalConsumo');
    cargarConsumo();
    cargarStock();
    alert('✅ Consumo de ' + cantidad + ' lámina(s) registrado correctamente');
  } catch(e) {
    alert('Error de conexión');
  } finally {
    btn.disabled = false;
    btn.textContent = 'Registrar';
  }
}

// ── Init ─────────────────────────────────────────────────────
cargarStock();

// Exponer globales
window.invTab               = invTab;
window.invCerrarModal       = invCerrarModal;
window.invAbrirModalLamina  = invAbrirModalLamina;
window.invEditarLamina      = invEditarLamina;
window.invActualizarM2Preview = invActualizarM2Preview;
window.invGuardarLamina     = invGuardarLamina;
window.invAbrirModalCompra  = invAbrirModalCompra;
window.invToggleFlete       = invToggleFlete;
window.invGuardarCompra     = invGuardarCompra;
window.invAbrirModalProveedor = invAbrirModalProveedor;
window.invEditarProveedor   = invEditarProveedor;
window.invGuardarProveedor  = invGuardarProveedor;
window.ocFiltrar            = ocFiltrar;
window.ocVerDetalle         = ocVerDetalle;
window.ocAbrirModalNueva    = ocAbrirModalNueva;
window.ocGuardarNueva       = ocGuardarNueva;
window.ocAbrirAgregarPartida = ocAbrirAgregarPartida;
window.npToggleTipo         = npToggleTipo;
window.npDescripcionAuto    = npDescripcionAuto;
window.npPreview            = npPreview;
window.npGuardar            = npGuardar;
window.npEditar             = npEditar;
window.npEliminar           = npEliminar;
window.ocCambiarEstado      = ocCambiarEstado;
window.ocAbrirEntrega       = ocAbrirEntrega;
window.entregaActualizarPreview = entregaActualizarPreview;
window.entregaGuardar       = entregaGuardar;
window.ocAbrirPago          = ocAbrirPago;
window.ocGuardarPago        = ocGuardarPago;
window.ocAbrirDistribuir    = ocAbrirDistribuir;
window.dfPreview            = dfPreview;
window.dfConfirmar          = dfConfirmar;
window.invAbrirModalConsumo = invAbrirModalConsumo;
window.consumoActualizarStock = consumoActualizarStock;
window.invRegistrarConsumo  = invRegistrarConsumo;

return { init: cargarStock };
})();
ModInventario.init();
</script>