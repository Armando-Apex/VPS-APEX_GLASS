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

/* Menú 3 puntos */
.fac-menu-wrap { position: relative; display: inline-block; }
.fac-menu-btn { background: none; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 10px; font-size: 16px; cursor: pointer; line-height: 1; color: #64748b; }
.fac-menu-btn:hover { background: #f1f5f9; }
.fac-menu-drop { display: none; position: fixed; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.1); min-width: 150px; z-index: 9999; padding: 4px 0; }
.fac-menu-drop.open { display: block; }
.fac-menu-item { display: block; width: 100%; text-align: left; background: none; border: none; padding: 8px 14px; font-size: 13px; cursor: pointer; color: #1e293b; white-space: nowrap; text-decoration: none; box-sizing: border-box; }
.fac-menu-item:hover { background: #f1f5f9; }
.fac-menu-item.danger { color: #dc2626; }
.fac-menu-item.danger:hover { background: #fee2e2; }
.fac-menu-sep { border: none; border-top: 1px solid #f1f5f9; margin: 4px 0; }

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

/* Constancia upload */
.fac-cst-drop { border: 2px dashed #cbd5e1; border-radius: 10px; padding: 18px 20px; display: flex; align-items: center; gap: 14px; cursor: pointer; transition: all .15s; background: #f8fafc; margin-bottom: 16px; }
.fac-cst-drop:hover, .fac-cst-drop.drag { border-color: #2563eb; background: #eff6ff; }
.fac-cst-drop input[type=file] { display: none; }
.fac-cst-icon { width: 38px; height: 38px; background: #e0e7ff; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.fac-cst-txt { flex: 1; }
.fac-cst-txt strong { display: block; font-size: 13px; color: #1e293b; }
.fac-cst-txt span { font-size: 11px; color: #64748b; }
.fac-cst-preview { background: #f0fdf4; border: 1px solid #86efac; border-radius: 10px; padding: 14px 16px; margin-bottom: 16px; display: none; }
.fac-cst-preview.visible { display: block; }
.fac-cst-preview h4 { margin: 0 0 10px; font-size: 12px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: .04em; }
.fac-cst-fields { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
.fac-cst-field { background: #fff; border: 1px solid #bbf7d0; border-radius: 7px; padding: 8px 10px; }
.fac-cst-field label { font-size: 10px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: .04em; display: block; margin-bottom: 2px; }
.fac-cst-field span { font-size: 13px; color: #1a1a1a; font-weight: 600; }
.fac-cst-warn { font-size: 11px; color: #92400e; background: #fef3c7; border-radius: 6px; padding: 6px 10px; margin-bottom: 10px; display: none; }
.fac-cst-warn.visible { display: block; }
.fac-cst-actions { display: flex; gap: 8px; align-items: center; }
.fac-cst-usar { background: #166534; color: #fff; border: none; padding: 7px 16px; border-radius: 7px; font-size: 12px; font-weight: 600; cursor: pointer; }
.fac-cst-usar:hover { background: #15803d; }
.fac-cst-desc { font-size: 11px; color: #64748b; }
.fac-cst-loading { display: none; align-items: center; gap: 8px; font-size: 12px; color: #2563eb; padding: 10px 0; }
.fac-cst-loading.visible { display: flex; }
.fac-cst-spin { width: 16px; height: 16px; border: 2px solid #bfdbfe; border-top-color: #2563eb; border-radius: 50%; animation: facSpin .7s linear infinite; }
@keyframes facSpin { to { transform: rotate(360deg); } }
.fac-cst-error { font-size: 12px; color: #dc2626; background: #fee2e2; border-radius: 7px; padding: 8px 12px; display: none; margin-top: 8px; }
.fac-cst-error.visible { display: block; }

/* Buscador cliente CRM */
.fac-cli-wrap { position:relative; }
.fac-cli-drop {
  display:none; position:absolute; top:100%; left:0; right:0;
  background:#fff; border:1px solid #e2e8f0; border-radius:8px;
  box-shadow:0 8px 24px rgba(0,0,0,.1); z-index:2100;
  max-height:240px; overflow-y:auto; margin-top:2px;
}
.fac-cli-drop.open { display:block; }
.fac-cli-opt { padding:10px 14px; cursor:pointer; border-bottom:1px solid #f1f5f9; }
.fac-cli-opt:last-child { border-bottom:none; }
.fac-cli-opt:hover { background:#f8fafc; }
.fac-cli-opt-nombre { font-size:13px; font-weight:600; color:#1e293b; }
.fac-cli-opt-sub { font-size:11px; color:#94a3b8; margin-top:1px; display:flex; gap:8px; }
.fac-cli-opt-rfc { font-family:monospace; font-size:11px; color:#2563eb; font-weight:700; }
.fac-cli-ok  { font-size:11px; background:#f0fdf4; border:1px solid #86efac; border-radius:6px; padding:6px 10px; margin-top:6px; color:#166534; display:none; }
.fac-cli-ok.vis  { display:block; }
.fac-cli-warn { font-size:11px; background:#fef3c7; border:1px solid #fbbf24; border-radius:6px; padding:6px 10px; margin-top:6px; color:#92400e; display:none; }
.fac-cli-warn.vis { display:block; }

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

      <!-- Datos generales — oculto (folios internos aún no definidos); los campos se mantienen en el DOM
           con sus valores por default (serie 'A', fecha de hoy) para no romper guardar()/recalc() -->
      <div style="display:none">
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
      </div>

      <!-- Constancia SAT — oculto (se sube desde el módulo Clientes); campos se mantienen en el DOM -->
      <div style="display:none">
        <div class="fac-section-title">Constancia de Situación Fiscal</div>
        <label class="fac-cst-drop" id="fac-cst-drop" ondragover="ModFacturacion.cstDrag(event,true)" ondragleave="ModFacturacion.cstDrag(event,false)" ondrop="ModFacturacion.cstDrop(event)">
          <input type="file" id="fac-cst-file" accept=".pdf,application/pdf" onchange="ModFacturacion.cstSubir(this.files[0])">
          <div class="fac-cst-icon">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
          </div>
          <div class="fac-cst-txt">
            <strong>Subir Constancia de Situación Fiscal (PDF)</strong>
            <span>Haz clic o arrastra el PDF del SAT — extrae RFC, nombre, CP y régimen automáticamente</span>
          </div>
        </label>

        <div class="fac-cst-loading" id="fac-cst-loading">
          <div class="fac-cst-spin"></div>
          Extrayendo datos del PDF…
        </div>
        <div class="fac-cst-error" id="fac-cst-error"></div>

        <div class="fac-cst-preview" id="fac-cst-preview">
          <h4>Datos encontrados en la constancia</h4>
          <div class="fac-cst-fields" id="fac-cst-fields"></div>
          <div class="fac-cst-warn" id="fac-cst-warn"></div>
          <div class="fac-cst-actions">
            <button class="fac-cst-usar" onclick="ModFacturacion.cstAplicar()">Usar estos datos</button>
            <button class="fac-cst-usar" onclick="ModFacturacion.cstDescartar()" style="background:#64748b">Descartar</button>
          </div>
        </div>
      </div>

      <!-- Buscar por folio de orden -->
      <div class="fac-section-title">Orden de producción (opcional)</div>
      <div class="fac-field" style="margin-bottom:14px">
        <label>Folio de orden</label>
        <div class="fac-cli-wrap" style="display:flex;gap:8px">
          <input type="text" id="fac-orden-folio" placeholder="Ej. R-224 o solo 224" autocomplete="off" style="flex:1"
            oninput="ModFacturacion.sugerirOrdenes()"
            onkeydown="if(event.key==='Enter'){event.preventDefault();ModFacturacion.buscarOrden();}">
          <button type="button" class="fac-cst-usar" style="width:auto;padding:0 16px" onclick="ModFacturacion.buscarOrden()">Buscar</button>
          <div id="fac-orden-drop" class="fac-cli-drop"></div>
        </div>
        <div id="fac-orden-msg" class="fac-cli-warn"></div>
      </div>

      <!-- Buscar cliente CRM -->
      <div class="fac-section-title">Receptor (Cliente)</div>

      <div class="fac-field" style="margin-bottom:14px">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;text-transform:none;font-weight:600">
          <input type="checkbox" id="fac-publico-general" onchange="ModFacturacion.togglePublicoGeneral()" style="width:auto">
          Facturar a Público en General
        </label>
        <div class="fac-hint">Úsalo cuando el cliente no quiere factura a su nombre. El CFDI sale genérico, pero queda registrado internamente quién lo pidió.</div>
      </div>

      <div class="fac-field" id="fac-solicito-wrap" style="display:none;margin-bottom:14px">
        <label>Cliente que lo solicitó <span style="font-weight:400;text-transform:none;font-size:10px;color:#94a3b8">(registro interno, no aparece en el CFDI)</span></label>
        <select id="fac-solicito-cli">
          <option value="">— Selecciona el cliente real —</option>
        </select>
      </div>

      <div class="fac-field" id="fac-cli-normal-wrap" style="margin-bottom:14px">
        <label>Cliente en CRM</label>
        <select id="fac-cli-q" onchange="ModFacturacion.seleccionarClienteSelect()">
          <option value="">— Selecciona un cliente —</option>
        </select>
        <div id="fac-cli-ok"   class="fac-cli-ok">&#10003; <strong id="fac-cli-ok-nombre"></strong> seleccionado — datos pre-llenados desde el CRM (bloqueados para edición). <button onclick="ModFacturacion.limpiarCliente()" style="background:none;border:none;color:#166534;text-decoration:underline;cursor:pointer;font-size:11px;padding:0">Limpiar</button> <button id="fac-receptor-unlock" onclick="ModFacturacion.desbloquearReceptor()" style="display:none;background:none;border:none;color:#92400e;text-decoration:underline;cursor:pointer;font-size:11px;padding:0;margin-left:8px">Editar de todos modos</button></div>
        <div id="fac-cli-warn" class="fac-cli-warn">&#9888; Este cliente no tiene datos fiscales en el CRM. Sube la Constancia SAT abajo o agrégalos en el m&#243;dulo <strong>Clientes</strong>.</div>
      </div>
      <div class="fac-row cols2">
        <div class="fac-field">
          <label>Nombre / Razón Social</label>
          <input type="text" id="fac-receptor-nombre" placeholder="Tal como aparece en el SAT">
        </div>
        <div class="fac-field">
          <label>Correo electrónico</label>
          <input type="email" id="fac-email" placeholder="cliente@empresa.com">
          <div class="fac-hint">Facturama / FacturAPI envía el XML+PDF a este correo al timbrar</div>
        </div>
      </div>
      <div class="fac-row cols3">
        <div class="fac-field">
          <label>RFC</label>
          <input type="text" id="fac-rfc" placeholder="XAXX010101000 (público en general)" maxlength="13" style="text-transform:uppercase">
          <div class="fac-hint">CFDI 4.0: debe coincidir exactamente con el SAT</div>
        </div>
        <div class="fac-field">
          <label>CP Fiscal del receptor</label>
          <input type="text" id="fac-cp" placeholder="64000" maxlength="5">
          <div class="fac-hint">Código postal del domicilio fiscal</div>
        </div>
        <div class="fac-field">
          <label>Tipo de factura</label>
          <select id="fac-tipo-cfdi" onchange="ModFacturacion.tipoChange()">
            <option value="I">Factura (Ingreso)</option>
            <option value="E">Nota de Crédito (Egreso)</option>
            <option value="P">Complemento de Pago</option>
            <option value="IG">Factura Global (Ingreso)</option>
          </select>
          <div class="fac-hint" id="fac-tipo-hint">Venta normal de productos o servicios</div>
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
            <option value="626">626 – Régimen Simplificado de Confianza (Resico)</option>
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

<!-- ── Modal cancelar factura (timbrada → cancelada, vía FacturAPI) ── -->
<div class="fac-overlay" id="fac-est-overlay">
  <div class="fac-modal" style="width:420px">
    <div class="fac-modal-head">
      <h3>Cancelar Factura</h3>
      <button class="fac-modal-close" onclick="ModFacturacion.cerrarEstatus()">&times;</button>
    </div>
    <div class="fac-modal-body">
      <div style="font-size:13px;color:#475569;margin-bottom:12px">Factura: <strong id="fac-est-folio"></strong></div>

      <div class="fac-motivo-wrap visible">
        <label>Motivo de cancelación (SAT)</label>
        <select id="fac-motivo-cancel">
          <option value="01">01 – Error en la factura, se sustituye por otra</option>
          <option value="02">02 – Error en la factura, sin sustitución</option>
          <option value="03">03 – No se realizó la operación</option>
          <option value="04">04 – Operación nominativa en factura global</option>
        </select>
        <div class="fac-hint" style="margin-top:4px;color:#dc2626">La cancelación >$1,000 MXN o después de 72 hrs requiere aceptación del cliente en su buzón SAT. Esta acción se envía directamente al PAC (FacturAPI) y no se puede deshacer.</div>
      </div>
    </div>
    <div class="fac-modal-foot">
      <button class="fac-btn-cancel" onclick="ModFacturacion.cerrarEstatus()">Cerrar</button>
      <button class="fac-btn-save" onclick="ModFacturacion.confirmarEstatus()">Confirmar cancelación</button>
    </div>
  </div>
</div>

<script>
var ModFacturacion = (function() {
  var _editingEstId = null;
  var _facturas     = [];

  var CLAVES_SAT = [
    {v:'01010101', l:'01010101 – No existe en catálogo (pruebas)'},
    {v:'30171706', l:'30171706 – Vidrio templado'},
    {v:'31241700', l:'31241700 – Espejos'},
  ];

  var UNIDADES_SAT = [
    {v:'MTK', l:'MTK – Metro cuadrado'},
    {v:'H87', l:'H87 – Pieza'},
    {v:'MTR', l:'MTR – Metro lineal'},
    {v:'E48', l:'E48 – Unidad de servicio'},
    {v:'ACT', l:'ACT – Actividad'},
    {v:'KGM', l:'KGM – Kilogramo'},
  ];

  function _esc(s) {
    var d = document.createElement('div');
    d.textContent = s || '';
    return d.innerHTML;
  }

  function _apiFetch(url, opts, cb) {
    var xhr = new XMLHttpRequest();
    xhr.open(opts.method || 'GET', url);
    xhr.setRequestHeader('X-SPA-Request', '1');
    if (opts.body) xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.onload = function() {
      var res;
      try { res = JSON.parse(xhr.responseText); } catch(e) { res = {ok:false,error:'Error del servidor'}; }
      cb(null, res);
    };
    xhr.onerror = function() { cb('Error de conexión'); };
    xhr.send(opts.body || null);
  }

  function _cargarLista() {
    _apiFetch('../api/facturapi.php?accion=lista', {}, function(err, res) {
      if (err || !res.ok) return;
      _facturas = res.facturas || [];
      _renderTabla();
    });
  }

  function _fmt(n) {
    return '$' + parseFloat(n || 0).toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
  }

  function _badgeHtml(est) {
    var labels = {pendiente:'Pendiente', timbrada:'Timbrada', cancelada:'Cancelada'};
    return '<span class="fac-badge ' + est + '">' + (labels[est] || est) + '</span>';
  }

  function _csatWidget(sel) {
    var label = sel || '— Clave —';
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
    var wrap    = opt.closest('.fac-csat');
    var hidden  = wrap.querySelector('.fac-c-clave') || wrap.querySelector('.fac-c-unidad');
    var display = wrap.querySelector('.fac-csat-display');
    var list    = wrap.querySelector('.fac-csat-list');
    if (hidden) hidden.value = val;
    display.textContent = val;
    list.classList.remove('open');
  }

  function _unidadWidget(sel) {
    var label = sel || '— Unidad —';
    var html = '<div class="fac-csat">';
    html += '<input type="hidden" class="fac-c-unidad" value="' + (sel||'') + '">';
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
      '<td>' + _csatWidget(clave||'') + '</td>' +
      '<td>' + _unidadWidget(unidad||'') + '</td>' +
      '<td><input type="number" class="fac-c-cant" value="' + (cant||1) + '" min="1" oninput="ModFacturacion.recalc()"></td>' +
      '<td><input type="number" class="fac-c-precio" value="' + (precio||'') + '" min="0" step="0.01" placeholder="0.00" oninput="ModFacturacion.recalc()"></td>' +
      '<td style="text-align:center"><input type="checkbox" class="fac-c-iva" ' + (applyIva ? 'checked' : '') + ' title="Aplica IVA 16%" onchange="ModFacturacion.recalc()"></td>' +
      '<td class="fac-c-imp" style="color:#475569;font-weight:600">' + _fmt(imp) + '</td>' +
      '<td><button class="fac-del-row" onclick="this.closest(\'tr\').remove();ModFacturacion.recalc()">&times;</button></td>' +
      '</tr>';
  }

  function _renderTabla() {
    var tbody = document.getElementById('fac-tbody');
    if (!tbody) return;
    if (!_facturas.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="fac-empty">No hay facturas. Crea la primera.</td></tr>';
      return;
    }
    var TIPOS = {I:'Factura',E:'Nota Cred.',P:'Comp. Pago',IG:'Global'};
    var html = '';
    for (var i = 0; i < _facturas.length; i++) {
      var f = _facturas[i];
      var esBorrador  = f.estatus === 'borrador';
      var esTimbrada  = f.estatus === 'timbrada';
      var modoBadge   = (f.modo === 'test') ? '<span style="font-size:9px;background:#fef3c7;color:#92400e;border-radius:4px;padding:1px 5px;margin-left:4px;font-weight:700">PRUEBA</span>' : '';
      var pubGeneral  = (f.receptor_rfc === 'XAXX010101000');
      var pubBadge    = pubGeneral ? '<span style="font-size:9px;background:#e0e7ff;color:#3730a3;border-radius:4px;padding:1px 5px;margin-left:4px;font-weight:700">PÚB. GRAL.</span>' : '';
      html += '<tr>';
      html += '<td style="font-weight:600;color:#2563eb">' + f.folio_interno + modoBadge + pubBadge + '</td>';
      html += '<td><div style="font-weight:600">' + (f.receptor_nombre||'—') + '</div>';
      html += '<div style="font-size:11px;color:#94a3b8">' + (f.receptor_rfc||'') + '</div>';
      if (pubGeneral && f.cliente_solicito_nombre) {
        html += '<div style="font-size:10px;color:#3730a3;margin-top:2px">Solicitó: ' + _esc(f.cliente_solicito_nombre) + '</div>';
      }
      html += '</td>';
      html += '<td style="font-size:12px">' + (TIPOS[f.tipo_cfdi]||f.tipo_cfdi) + '</td>';
      html += '<td style="font-size:12px">' + (f.receptor_uso_cfdi||'') + ' <span style="color:#94a3b8">/ ' + (f.metodo_pago||'') + '</span></td>';
      html += '<td style="font-weight:600">' + _fmt(f.total) + '</td>';
      html += '<td>' + _badgeHtml(f.estatus);
      if (esTimbrada && f.uuid) html += '<div style="font-size:10px;color:#22c55e;font-family:monospace;margin-top:2px">' + f.uuid.slice(0,8) + '…</div>';
      html += '</td>';
      html += '<td>';
      html += '<div class="fac-menu-wrap">';
      html += '<button class="fac-menu-btn" onclick="ModFacturacion.menuToggle(this)">···</button>';
      html += '<div class="fac-menu-drop">';
      if (esBorrador) {
        html += '<button class="fac-menu-item" onclick="ModFacturacion.menuCerrar();ModFacturacion.abrirEditar(' + f.id + ')">Editar</button>';
        html += '<button class="fac-menu-item" onclick="ModFacturacion.menuCerrar();ModFacturacion.timbrar(' + f.id + ')">Timbrar</button>';
        html += '<hr class="fac-menu-sep">';
        html += '<button class="fac-menu-item danger" onclick="ModFacturacion.menuCerrar();ModFacturacion.eliminar(' + f.id + ')">Eliminar</button>';
      } else if (esTimbrada) {
        html += '<a class="fac-menu-item" href="../api/facturapi.php?accion=pdf&id=' + f.id + '" target="_blank">Descargar PDF</a>';
        html += '<a class="fac-menu-item" href="../api/facturapi.php?accion=xml&id=' + f.id + '" target="_blank">Descargar XML</a>';
        html += '<hr class="fac-menu-sep">';
        html += '<button class="fac-menu-item danger" onclick="ModFacturacion.menuCerrar();ModFacturacion.abrirEstatus(' + f.id + ')">Cancelar factura (SAT)</button>';
        if (f.modo === 'test') {
          html += '<button class="fac-menu-item danger" onclick="ModFacturacion.menuCerrar();ModFacturacion.eliminar(' + f.id + ')">Eliminar (prueba)</button>';
        }
      }
      html += '</div></div>';
      html += '</td></tr>';
    }
    tbody.innerHTML = html;
  }

  function _resetBuscador() {
    var qEl = document.getElementById('fac-cli-q');
    if (qEl) qEl.value = '';
    var okEl   = document.getElementById('fac-cli-ok');
    var warnEl = document.getElementById('fac-cli-warn');
    if (okEl)   okEl.className = 'fac-cli-ok';
    if (warnEl) warnEl.className = 'fac-cli-warn';
    var cstDrop = document.getElementById('fac-cst-drop');
    if (cstDrop) cstDrop.style.opacity = '1';
    var folioEl = document.getElementById('fac-orden-folio');
    if (folioEl) folioEl.value = '';
    var msgEl = document.getElementById('fac-orden-msg');
    if (msgEl) { msgEl.className = 'fac-cli-warn'; msgEl.textContent = ''; }
    if (!_clienteslistaCargada) _cargarListaClientes();
  }

  function _clearForm() {
    _resetBuscador();
    _ultimoClienteOrdenId = null;
    document.getElementById('fac-edit-id').value    = '';
    document.getElementById('fac-folio').value      = '(se asigna al guardar)';
    document.getElementById('fac-fecha').value      = new Date().toISOString().slice(0,10);
    document.getElementById('fac-receptor-nombre').value = '';
    document.getElementById('fac-email').value      = '';
    document.getElementById('fac-rfc').value        = '';
    document.getElementById('fac-cp').value         = '';
    document.getElementById('fac-serie').value      = 'A';
    document.getElementById('fac-tipo-cfdi').value  = 'I';
    document.getElementById('fac-uso-cfdi').value   = '';
    document.getElementById('fac-regimen').value    = '';
    document.getElementById('fac-forma-pago').value  = '';
    document.getElementById('fac-metodo-pago').value = '';
    document.getElementById('fac-publico-general').checked = false;
    document.getElementById('fac-solicito-wrap').style.display   = 'none';
    document.getElementById('fac-cli-normal-wrap').style.display = 'block';
    document.getElementById('fac-uso-cfdi').disabled = false;
    tipoChange();
    document.getElementById('fac-conceptos-body').innerHTML = _conceptoRow('', '', '', 1, '', true);
    recalc();
  }

  function _getConceptos() {
    var rows = document.querySelectorAll('#fac-conceptos-body tr');
    var out  = [];
    for (var i = 0; i < rows.length; i++) {
      var desc   = rows[i].querySelector('.fac-c-desc').value;
      var clave  = rows[i].querySelector('.fac-c-clave').value;
      var unidadEl = rows[i].querySelector('.fac-c-unidad');
      var unidad   = unidadEl ? unidadEl.value : '';
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
    var f = null;
    for (var i = 0; i < _facturas.length; i++) { if (String(_facturas[i].id) === String(id)) { f = _facturas[i]; break; } }
    if (!f) return;
    document.getElementById('fac-modal-titulo').textContent = 'Editar Factura';
    document.getElementById('fac-edit-id').value            = f.id;
    document.getElementById('fac-folio').value              = f.folio_interno;
    document.getElementById('fac-fecha').value              = f.fecha || '';
    document.getElementById('fac-receptor-nombre').value    = f.receptor_nombre || '';
    document.getElementById('fac-email').value              = f.receptor_email  || '';
    document.getElementById('fac-rfc').value                = f.receptor_rfc    || '';
    document.getElementById('fac-cp').value                 = f.receptor_cp     || '';
    document.getElementById('fac-serie').value              = f.serie           || 'A';
    document.getElementById('fac-tipo-cfdi').value          = f.tipo_cfdi       || 'I';
    document.getElementById('fac-uso-cfdi').value           = f.receptor_uso_cfdi || '';
    document.getElementById('fac-regimen').value            = f.receptor_regimen  || '';
    document.getElementById('fac-forma-pago').value         = f.forma_pago        || '';
    document.getElementById('fac-metodo-pago').value        = f.metodo_pago       || '';

    var esPublicoGeneral = (f.receptor_rfc === 'XAXX010101000');
    document.getElementById('fac-publico-general').checked = esPublicoGeneral;
    document.getElementById('fac-solicito-wrap').style.display   = esPublicoGeneral ? 'block' : 'none';
    document.getElementById('fac-cli-normal-wrap').style.display = esPublicoGeneral ? 'none'  : 'block';
    document.getElementById('fac-uso-cfdi').disabled = esPublicoGeneral;
    _lockReceptor(esPublicoGeneral);
    var setSolicito = function() {
      var sel = document.getElementById('fac-solicito-cli');
      if (sel) sel.value = f.cliente_solicito_id || '';
    };
    if (!_clienteslistaCargada) { _cargarListaClientes(setSolicito); } else { setSolicito(); }

    tipoChange();
    var tbody = document.getElementById('fac-conceptos-body');
    tbody.innerHTML = '';
    var conceptos = typeof f.conceptos === 'string' ? JSON.parse(f.conceptos) : (f.conceptos || []);
    for (var j = 0; j < conceptos.length; j++) {
      var c = conceptos[j];
      tbody.innerHTML += _conceptoRow(c.desc, c.clave, c.unidad, c.cant, c.precio, c.iva);
    }
    if (!conceptos.length) tbody.innerHTML = _conceptoRow('', '', '', 1, '', true);
    recalc();
    document.getElementById('fac-overlay').classList.add('open');
  }

  function cerrarModal() {
    document.getElementById('fac-overlay').classList.remove('open');
  }

  function guardar() {
    var esPublicoGeneral = document.getElementById('fac-publico-general').checked;

    var errores = [];
    if (!document.getElementById('fac-receptor-nombre').value.trim()) errores.push('Nombre / Razón Social');
    if (!document.getElementById('fac-rfc').value.trim())             errores.push('RFC');
    if (!document.getElementById('fac-cp').value.trim())              errores.push('CP Fiscal');
    if (!document.getElementById('fac-uso-cfdi').value)               errores.push('Uso CFDI');
    if (!document.getElementById('fac-regimen').value)                errores.push('Régimen Fiscal');
    if (!document.getElementById('fac-forma-pago').value)             errores.push('Forma de Pago');
    if (!document.getElementById('fac-metodo-pago').value)            errores.push('Método de Pago');
    if (esPublicoGeneral && !document.getElementById('fac-solicito-cli').value) errores.push('Cliente que solicitó Público en General');
    if (errores.length) {
      alert('Faltan campos obligatorios:\n\n• ' + errores.join('\n• '));
      return;
    }

    var payload = {
      id:                   document.getElementById('fac-edit-id').value || null,
      serie:                document.getElementById('fac-serie').value.trim() || 'A',
      tipo_cfdi:            document.getElementById('fac-tipo-cfdi').value,
      fecha:                document.getElementById('fac-fecha').value,
      receptor_nombre:      document.getElementById('fac-receptor-nombre').value.trim(),
      receptor_rfc:         document.getElementById('fac-rfc').value.trim().toUpperCase(),
      receptor_cp:          document.getElementById('fac-cp').value.trim(),
      receptor_regimen:     document.getElementById('fac-regimen').value,
      receptor_uso_cfdi:    document.getElementById('fac-uso-cfdi').value,
      receptor_email:       document.getElementById('fac-email').value.trim(),
      cliente_solicito_id:  esPublicoGeneral ? (document.getElementById('fac-solicito-cli').value || null) : null,
      forma_pago:           document.getElementById('fac-forma-pago').value,
      metodo_pago:          document.getElementById('fac-metodo-pago').value,
      conceptos:            _getConceptos(),
    };

    var btn = document.querySelector('#fac-overlay .fac-btn-save');
    if (btn) { btn.disabled = true; btn.textContent = 'Guardando…'; }

    _apiFetch('../api/facturapi.php?accion=guardar', {method:'POST', body:JSON.stringify(payload)}, function(err, res) {
      if (btn) { btn.disabled = false; btn.textContent = 'Guardar Factura'; }
      if (err || !res.ok) { alert(err || res.error || 'Error al guardar'); return; }
      cerrarModal();
      _cargarLista();
    });
  }

  function eliminar(id) {
    var f = null;
    for (var i = 0; i < _facturas.length; i++) { if (String(_facturas[i].id) === String(id)) { f = _facturas[i]; break; } }
    var msg = (f && f.estatus === 'timbrada')
      ? 'Esta factura fue timbrada en modo PRUEBA.\n¿Eliminarla? Esta acción no se puede deshacer.'
      : '¿Eliminar esta factura borrador? Esta acción no se puede deshacer.';
    if (!confirm(msg)) return;
    _apiFetch('../api/facturapi.php?accion=eliminar', {method:'POST', body:JSON.stringify({id:id})}, function(err, res) {
      if (err || !res.ok) { alert(err || res.error || 'Error al eliminar'); return; }
      _cargarLista();
    });
  }

  function timbrar(id) {
    if (!confirm('¿Timbrar esta factura en modo PRUEBA con FacturAPI sandbox?')) return;
    var btn = document.querySelector('button[onclick*="timbrar(' + id + ')"]');
    if (btn) { btn.disabled = true; btn.textContent = 'Timbrando…'; }

    _apiFetch('../api/facturapi.php?accion=timbrar', {method:'POST', body:JSON.stringify({id:id})}, function(err, res) {
      if (btn) { btn.disabled = false; btn.textContent = 'Timbrar'; }
      if (err || !res.ok) { alert('Error al timbrar: ' + (err || res.error)); return; }
      alert('✅ Timbrada en SANDBOX\nUUID: ' + res.uuid + '\n\nPuedes descargar el PDF desde la lista.');
      _cargarLista();
    });
  }

  function abrirEstatus(id) {
    var f = null;
    for (var i = 0; i < _facturas.length; i++) { if (String(_facturas[i].id) === String(id)) { f = _facturas[i]; break; } }
    if (!f) return;
    _editingEstId = f.id;
    document.getElementById('fac-est-folio').textContent = f.folio_interno;
    document.getElementById('fac-motivo-cancel').value = '01';
    document.getElementById('fac-est-overlay').classList.add('open');
  }

  function cerrarEstatus() {
    _editingEstId = null;
    document.getElementById('fac-est-overlay').classList.remove('open');
  }

  function confirmarEstatus() {
    if (!_editingEstId) return;
    var motivo = document.getElementById('fac-motivo-cancel').value;
    if (!confirm('¿Cancelar esta factura ante el SAT? Esta acción no se puede deshacer.')) return;
    var id = _editingEstId;
    _apiFetch('../api/facturapi.php?accion=cancelar', {method:'POST', body:JSON.stringify({id:id, motivo:motivo})}, function(err, res) {
      if (err || !res.ok) { alert('Error al cancelar: ' + (err || res.error)); return; }
      cerrarEstatus();
      _cargarLista();
    });
  }

  function agregarConcepto() {
    document.getElementById('fac-conceptos-body').innerHTML += _conceptoRow('', '', '', 1, '');
    recalc();
  }

  // ── Selector cliente CRM (dropdown, sin escribir) ─────────────────────────────
  var _cliOpciones  = [];
  var _clienteslistaCargada = false;
  var _ultimoClienteOrdenId = null;

  function _setSolicitoCliente(id) {
    var sel = document.getElementById('fac-solicito-cli');
    if (sel && id) sel.value = String(id);
  }

  function _cargarListaClientes(cb) {
    _apiFetch('../api/facturapi.php?accion=lista_clientes', {}, function(err, res) {
      if (err || !res.ok) { if (cb) cb(); return; }
      _cliOpciones = res.clientes || [];
      var sel = document.getElementById('fac-cli-q');
      if (sel) {
        var html = '<option value="">— Selecciona un cliente —</option>';
        for (var i = 0; i < _cliOpciones.length; i++) {
          var c = _cliOpciones[i];
          var nombre = c.razon_social || c.nombre || '—';
          html += '<option value="' + i + '">' + nombre + (c.codigo ? ' (' + c.codigo + ')' : '') + '</option>';
        }
        sel.innerHTML = html;
      }
      var selSolicito = document.getElementById('fac-solicito-cli');
      if (selSolicito) {
        var html2 = '<option value="">— Selecciona el cliente real —</option>';
        for (var j = 0; j < _cliOpciones.length; j++) {
          var c2 = _cliOpciones[j];
          var nombre2 = c2.razon_social || c2.nombre || '—';
          html2 += '<option value="' + c2.id + '">' + nombre2 + (c2.codigo ? ' (' + c2.codigo + ')' : '') + '</option>';
        }
        selSolicito.innerHTML = html2;
      }
      _clienteslistaCargada = true;
      if (cb) cb();
    });
  }

  function seleccionarClienteSelect() {
    var sel = document.getElementById('fac-cli-q');
    var idx = sel ? sel.value : '';
    if (idx === '') { limpiarCliente(); return; }
    if (_cliOpciones[idx]) _seleccionarCliente(_cliOpciones[idx]);
  }

  function _seleccionarClientePorId(clienteId) {
    for (var i = 0; i < _cliOpciones.length; i++) {
      if (String(_cliOpciones[i].id) === String(clienteId)) {
        var sel = document.getElementById('fac-cli-q');
        if (sel) sel.value = String(i);
        _seleccionarCliente(_cliOpciones[i]);
        return true;
      }
    }
    return false;
  }

  function _seleccionarCliente(c) {
    var nombre = c.razon_social || c.nombre || '';

    // Pre-llenar campos del receptor
    var setVal = function(id, val) { var el = document.getElementById(id); if (el) el.value = val || ''; };
    setVal('fac-receptor-nombre', nombre);
    setVal('fac-email',   c.email          || '');
    setVal('fac-rfc',     c.rfc            || '');
    setVal('fac-cp',      c.cp_fiscal      || '');
    setVal('fac-regimen', c.regimen_fiscal || '');

    // Aviso según si tiene o no datos fiscales completos
    var tieneFiscal = !!(c.rfc && c.cp_fiscal && c.regimen_fiscal);
    var okEl   = document.getElementById('fac-cli-ok');
    var warnEl = document.getElementById('fac-cli-warn');
    var okNom  = document.getElementById('fac-cli-ok-nombre');
    if (okEl)   okEl.className   = 'fac-cli-ok'   + (tieneFiscal  ? ' vis' : '');
    if (warnEl) warnEl.className = 'fac-cli-warn'  + (!tieneFiscal ? ' vis' : '');
    if (okNom)  okNom.textContent = nombre;

    // Si ya tiene RFC completo, bajar opacidad de la zona CSF (ya no es necesaria)
    var cstDrop = document.getElementById('fac-cst-drop');
    if (cstDrop) cstDrop.style.opacity = tieneFiscal ? '0.4' : '1';

    // Bloquear edición manual del receptor mientras haya un cliente CRM con datos fiscales completos seleccionado
    _lockReceptor(tieneFiscal);
  }

  function _lockReceptor(lock) {
    var campos = ['fac-receptor-nombre', 'fac-email', 'fac-rfc', 'fac-cp', 'fac-regimen'];
    for (var i = 0; i < campos.length; i++) {
      var el = document.getElementById(campos[i]);
      if (!el) continue;
      if (lock) { el.setAttribute('readonly', 'readonly'); el.disabled = (el.tagName === 'SELECT'); el.style.background = '#f8fafc'; el.style.color = '#64748b'; }
      else { el.removeAttribute('readonly'); el.disabled = false; el.style.background = ''; el.style.color = ''; }
    }
    var link = document.getElementById('fac-receptor-unlock');
    if (link) link.style.display = lock ? 'inline' : 'none';
  }

  function desbloquearReceptor() {
    if (!confirm('¿Editar el receptor manualmente? Asegúrate de que los datos coincidan exactamente con el SAT del cliente.')) return;
    _lockReceptor(false);
  }

  function togglePublicoGeneral() {
    var chk = document.getElementById('fac-publico-general');
    var on  = chk && chk.checked;
    document.getElementById('fac-solicito-wrap').style.display   = on ? 'block' : 'none';
    document.getElementById('fac-cli-normal-wrap').style.display = on ? 'none'  : 'block';

    if (on) {
      limpiarCliente();
      if (!_clienteslistaCargada) {
        _cargarListaClientes(function() { _setSolicitoCliente(_ultimoClienteOrdenId); });
      } else {
        _setSolicitoCliente(_ultimoClienteOrdenId);
      }
      var setVal = function(id, val) { var el = document.getElementById(id); if (el) el.value = val; };
      setVal('fac-receptor-nombre', 'PUBLICO EN GENERAL');
      setVal('fac-rfc', 'XAXX010101000');
      setVal('fac-regimen', '616');
      setVal('fac-uso-cfdi', 'S01');
      setVal('fac-email', '');
      _lockReceptor(true);
      document.getElementById('fac-uso-cfdi').disabled = true;
    } else {
      document.getElementById('fac-solicito-cli').value = '';
      _lockReceptor(false);
      document.getElementById('fac-uso-cfdi').disabled = false;

      // Restaurar los datos reales del cliente si veníamos de una orden/selección previa,
      // en vez de dejar el receptor en blanco
      if (_ultimoClienteOrdenId && !_clienteslistaCargada) {
        _cargarListaClientes(function() { _seleccionarClientePorId(_ultimoClienteOrdenId); });
      } else if (!_ultimoClienteOrdenId || !_seleccionarClientePorId(_ultimoClienteOrdenId)) {
        var setVal2 = function(id, val) { var el = document.getElementById(id); if (el) el.value = val; };
        setVal2('fac-receptor-nombre', '');
        setVal2('fac-rfc', '');
        setVal2('fac-regimen', '');
        setVal2('fac-uso-cfdi', '');
      }
    }
  }

  function limpiarCliente() {
    var sel = document.getElementById('fac-cli-q');
    if (sel) sel.value = '';
    var okEl   = document.getElementById('fac-cli-ok');
    var warnEl = document.getElementById('fac-cli-warn');
    if (okEl)   okEl.className   = 'fac-cli-ok';
    if (warnEl) warnEl.className = 'fac-cli-warn';
    var cstDrop = document.getElementById('fac-cst-drop');
    if (cstDrop) cstDrop.style.opacity = '1';
    _lockReceptor(false);
  }

  // ── Sugerencias de folio de orden ─────────────────────────────────────────────
  var _ordenTimer = null;

  function sugerirOrdenes() {
    clearTimeout(_ordenTimer);
    var q = (document.getElementById('fac-orden-folio') || {}).value || '';
    q = q.trim();
    if (q.length < 1) { _cerrarSugerenciasOrden(); return; }
    _ordenTimer = setTimeout(function() {
      _apiFetch('../api/facturapi.php?accion=sugerir_ordenes&q=' + encodeURIComponent(q), {}, function(err, res) {
        if (err || !res.ok) return;
        _renderSugerenciasOrden(res.ordenes || []);
      });
    }, 250);
  }

  function _renderSugerenciasOrden(lista) {
    var drop = document.getElementById('fac-orden-drop');
    if (!drop) return;
    if (!lista.length) {
      drop.innerHTML = '<div style="padding:12px 14px;font-size:12px;color:#94a3b8">Sin resultados</div>';
      drop.classList.add('open');
      return;
    }
    var html = '';
    for (var i = 0; i < lista.length; i++) {
      var o = lista[i];
      html += '<div class="fac-cli-opt" onclick="ModFacturacion._elegirOrdenFolio(\'' + o.folio.replace(/'/g, "\\'") + '\')">';
      html += '<div class="fac-cli-opt-nombre">' + o.folio + '</div>';
      html += '<div class="fac-cli-opt-sub"><span>' + (o.cliente_nombre || 'Sin cliente') + '</span></div>';
      html += '</div>';
    }
    drop.innerHTML = html;
    drop.classList.add('open');
  }

  function _cerrarSugerenciasOrden() {
    var drop = document.getElementById('fac-orden-drop');
    if (drop) { drop.innerHTML = ''; drop.classList.remove('open'); }
  }

  function _elegirOrdenFolio(folio) {
    _cerrarSugerenciasOrden();
    var folioEl = document.getElementById('fac-orden-folio');
    if (folioEl) folioEl.value = folio;
    buscarOrden();
  }

  document.addEventListener('click', function(e) {
    if (!e.target.closest('#fac-orden-drop') && !e.target.closest('#fac-orden-folio')) _cerrarSugerenciasOrden();
  });

  // ── Buscar por folio de orden ─────────────────────────────────────────────────
  function buscarOrden() {
    _cerrarSugerenciasOrden();
    var folioEl = document.getElementById('fac-orden-folio');
    var msgEl   = document.getElementById('fac-orden-msg');
    var folio   = folioEl ? folioEl.value.trim() : '';
    if (!folio) return;
    if (msgEl) { msgEl.className = 'fac-cli-warn vis'; msgEl.textContent = 'Buscando orden ' + folio + '…'; }

    _apiFetch('../api/facturapi.php?accion=buscar_orden&folio=' + encodeURIComponent(folio), {}, function(err, res) {
      if (err || !res.ok) {
        if (msgEl) { msgEl.className = 'fac-cli-warn vis'; msgEl.textContent = '⚠ ' + ((res && res.error) || 'No se encontró esa orden'); }
        return;
      }

      var clienteOk = false;
      if (res.cliente) {
        _ultimoClienteOrdenId = res.cliente.id;
        var esPublicoGeneral = document.getElementById('fac-publico-general').checked;
        if (esPublicoGeneral) {
          // No tocar el receptor genérico — solo ligar quién lo solicitó
          if (!_clienteslistaCargada) {
            _cargarListaClientes(function() { _setSolicitoCliente(res.cliente.id); });
          } else {
            _setSolicitoCliente(res.cliente.id);
          }
        } else if (!_clienteslistaCargada) {
          _cargarListaClientes(function() { _seleccionarClientePorId(res.cliente.id); });
        } else {
          clienteOk = _seleccionarClientePorId(res.cliente.id);
        }
      }

      if (res.conceptos && res.conceptos.length) {
        var tbody = document.getElementById('fac-conceptos-body');
        tbody.innerHTML = '';
        for (var i = 0; i < res.conceptos.length; i++) {
          var c = res.conceptos[i];
          tbody.innerHTML += _conceptoRow(c.desc, c.clave, c.unidad, c.cant, c.precio, c.iva);
        }
        recalc();
      }

      if (msgEl) {
        if (!res.cliente) {
          msgEl.className = 'fac-cli-warn vis';
          msgEl.textContent = '⚠ Orden encontrada pero sin cliente ligado en el CRM — selecciónalo manualmente.';
        } else if (!res.conceptos || !res.conceptos.length) {
          msgEl.className = 'fac-cli-warn vis';
          msgEl.textContent = '⚠ Orden y cliente cargados, pero no se encontraron partidas de precio — captura los conceptos manualmente.';
        } else {
          msgEl.className = 'fac-cli-ok vis';
          msgEl.textContent = '✓ Orden ' + folio + ' cargada: cliente y ' + res.conceptos.length + ' concepto(s).';
        }
      }
    });
  }

  // ── Menú 3 puntos ─────────────────────────────────────────────────────────
  function menuToggle(btn) {
    var drop = btn.nextSibling;
    var isOpen = drop.classList.contains('open');
    menuCerrar();
    if (!isOpen) {
      var rect = btn.getBoundingClientRect();
      drop.style.top  = (rect.bottom + 4) + 'px';
      drop.style.left = (rect.right - 150) + 'px';
      if (parseFloat(drop.style.left) < 8) drop.style.left = '8px';
      drop.classList.add('open');
    }
  }

  function menuCerrar() {
    var all = document.querySelectorAll('.fac-menu-drop.open');
    for (var i = 0; i < all.length; i++) all[i].classList.remove('open');
  }

  // Cerrar menú al hacer click fuera
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.fac-menu-wrap')) menuCerrar();
  });

  // ── Constancia de Situación Fiscal ────────────────────────────────────────
  var _cstDatos = null;

  function cstDrag(e, over) {
    e.preventDefault();
    var drop = document.getElementById('fac-cst-drop');
    if (drop) drop.classList.toggle('drag', over);
  }

  function cstDrop(e) {
    e.preventDefault();
    var drop = document.getElementById('fac-cst-drop');
    if (drop) drop.classList.remove('drag');
    var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
    if (file) cstSubir(file);
  }

  // Carga PDF.js una sola vez
  var _pdfJsListo = false;
  function _cargarPdfJs(cb) {
    if (_pdfJsListo) { cb(); return; }
    if (document.getElementById('pdfjs-script')) {
      // ya está en el DOM, esperar a que cargue
      var t = setInterval(function() {
        if (window.pdfjsLib) { clearInterval(t); _pdfJsListo = true; cb(); }
      }, 50);
      return;
    }
    var base = '/produccion/lib/';
    var s = document.createElement('script');
    s.id  = 'pdfjs-script';
    s.src = base + 'pdf.min.js';
    s.onload = function() {
      window.pdfjsLib.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.js';
      _pdfJsListo = true;
      cb();
    };
    s.onerror = function() { _cstError('No se pudo cargar el lector de PDF'); };
    document.head.appendChild(s);
  }

  function cstSubir(file) {
    if (!file) return;
    if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) {
      _cstError('Solo se aceptan archivos PDF'); return;
    }
    if (file.size > 5 * 1024 * 1024) {
      _cstError('Archivo demasiado grande (máx 5 MB)'); return;
    }
    _cstReset();
    document.getElementById('fac-cst-loading').classList.add('visible');

    _cargarPdfJs(function() {
      var reader = new FileReader();
      reader.onload = function(e) {
        var loadTask = window.pdfjsLib.getDocument({data: e.target.result});
        loadTask.promise.then(function(pdf) {
          // Extraer texto de todas las páginas (máx 3)
          var paginas = Math.min(pdf.numPages, 3);
          var textos  = [];
          var pending = paginas;
          for (var p = 1; p <= paginas; p++) {
            (function(num) {
              pdf.getPage(num).then(function(page) {
                page.getTextContent().then(function(content) {
                  var linea = content.items.map(function(it) { return it.str; }).join(' ');
                  textos[num] = linea;
                  pending--;
                  if (pending === 0) {
                    var texto = textos.join(' ').trim();
                    // PDF nativo con texto suficiente → parsear directo
                    if (texto.length > 100) {
                      document.getElementById('fac-cst-loading').classList.remove('visible');
                      var datos = _cstExtraer(texto);
                      if (!datos.rfc && !datos.nombre && !datos.cp) {
                        _cstError('No se encontraron datos fiscales. Verifica que sea una Constancia de Situación Fiscal del SAT.');
                        return;
                      }
                      _cstMostrar(datos);
                    } else {
                      // PDF de imagen (Print to PDF / escaneado) → OCR en servidor
                      _cstOcrServidor(file);
                    }
                  }
                });
              });
            })(p);
          }
        }).catch(function(err) {
          document.getElementById('fac-cst-loading').classList.remove('visible');
          _cstError('Error al leer el PDF: ' + err.message);
        });
      };
      reader.onerror = function() {
        document.getElementById('fac-cst-loading').classList.remove('visible');
        _cstError('Error al leer el archivo');
      };
      reader.readAsArrayBuffer(file);
    });
  }

  function _cstOcrServidor(file) {
    var fd = new FormData();
    fd.append('constancia', file);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '../api/extraer_constancia.php');
    xhr.setRequestHeader('X-SPA-Request', '1');
    xhr.onload = function() {
      document.getElementById('fac-cst-loading').classList.remove('visible');
      var res;
      try { res = JSON.parse(xhr.responseText); } catch(e) { res = {ok:false,error:'Error del servidor'}; }
      if (!res.ok) { _cstError(res.error || 'No se pudo leer la constancia'); return; }
      var d = res.datos;
      var datos = {
        rfc:     d.rfc     || '',
        nombre:  d.nombre  || '',
        cp:      d.cp      || '',
        regimen: d.regimen || []
      };
      if (!datos.rfc && !datos.nombre && !datos.cp) {
        _cstError('No se encontraron datos fiscales. Verifica que sea una Constancia de Situación Fiscal del SAT.');
        return;
      }
      _cstMostrar(datos);
    };
    xhr.onerror = function() {
      document.getElementById('fac-cst-loading').classList.remove('visible');
      _cstError('Error de conexión al procesar la constancia');
    };
    xhr.send(fd);
  }

  function _cstExtraer(texto) {
    var datos = {rfc: '', nombre: '', cp: '', regimen: []};

    // RFC: 3-4 letras + 6 dígitos + 3 alfanum
    var mRfc = texto.match(/\b([A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3})\b/);
    if (mRfc) datos.rfc = mRfc[1];

    // CP Fiscal
    var mCp = texto.match(/C[oó]digo\s*Postal\s*[:\s]*(\d{5})\b/i);
    if (!mCp) mCp = texto.match(/\bCP\s*[:\s]*(\d{5})\b/i);
    if (mCp) datos.cp = mCp[1];

    // Nombre persona moral
    var mMoral = texto.match(/(?:Denominaci[oó]n\s*(?:\/\s*)?Raz[oó]n\s*Social|Raz[oó]n\s*Social)\s*[:\s]+([A-ZÁÉÍÓÚÑÜ&][A-ZÁÉÍÓÚÑÜ&\s,\.\-]{2,80}?)(?:\s{3,}|\d{2}\/\d{2}\/\d{4}|RFC|Curp|CURP|Régimen)/i);
    if (mMoral) datos.nombre = mMoral[1].trim().replace(/\s+/g, ' ');

    // Nombre persona física: Apellido Paterno + Materno + Nombre(s)
    if (!datos.nombre) {
      var ap = '', am = '', nom = '';
      var mAp  = texto.match(/Apellido\s+Paterno\s*[:\s]+([A-ZÁÉÍÓÚÑÜ][A-ZÁÉÍÓÚÑÜ\s]{1,30}?)(?:\s{2,}|Apellido|Nombre|CURP)/i);
      var mAm  = texto.match(/Apellido\s+Materno\s*[:\s]+([A-ZÁÉÍÓÚÑÜ][A-ZÁÉÍÓÚÑÜ\s]{1,30}?)(?:\s{2,}|Nombre|RFC|CURP)/i);
      var mNom = texto.match(/Nombre\s*\(?s?\)?\s*[:\s]+([A-ZÁÉÍÓÚÑÜ][A-ZÁÉÍÓÚÑÜ\s]{1,50}?)(?:\s{2,}|Apellido|RFC|CURP|\d)/i);
      if (mAp)  ap  = mAp[1].trim();
      if (mAm)  am  = mAm[1].trim();
      if (mNom) nom = mNom[1].trim();
      var pn = [ap, am, nom].filter(function(x){ return x.length > 1; });
      if (pn.length) datos.nombre = pn.join(' ');
    }

    // Régimen(es)
    var regs = [
      {cod:'601', pat:/601|General de Ley Personas Morales/i,             label:'601 – General de Ley Personas Morales'},
      {cod:'603', pat:/603|Personas Morales sin Fines de Lucro/i,         label:'603 – Personas Morales sin Fines de Lucro'},
      {cod:'606', pat:/606|Arrendamiento/i,                               label:'606 – Arrendamiento'},
      {cod:'612', pat:/612|Actividades Empresariales y Profesionales/i,   label:'612 – Pers. Físicas Actividades Empresariales'},
      {cod:'616', pat:/616|Sin obligaciones fiscales/i,                   label:'616 – Sin obligaciones fiscales'},
      {cod:'621', pat:/621|Incorporaci[oó]n Fiscal/i,                    label:'621 – Incorporación Fiscal'},
      {cod:'625', pat:/625|Plataformas Tecnol[oó]gicas/i,                label:'625 – Plataformas Tecnológicas'},
      {cod:'626', pat:/626|RESICO|R[eé]gimen Simplificado de Confianza/i, label:'626 – Resico'},
    ];
    for (var i = 0; i < regs.length; i++) {
      if (regs[i].pat.test(texto)) {
        datos.regimen.push({cod: regs[i].cod, label: regs[i].label});
      }
    }

    return datos;
  }

  function _cstReset() {
    _cstDatos = null;
    document.getElementById('fac-cst-error').className   = 'fac-cst-error';
    document.getElementById('fac-cst-preview').className = 'fac-cst-preview';
    document.getElementById('fac-cst-loading').className = 'fac-cst-loading';
  }

  function _cstError(msg) {
    var el = document.getElementById('fac-cst-error');
    el.textContent = msg;
    el.className = 'fac-cst-error visible';
  }

  function _cstMostrar(datos) {
    _cstDatos = datos;
    var html = '';
    if (datos.rfc)    html += '<div class="fac-cst-field"><label>RFC</label><span>' + datos.rfc + '</span></div>';
    if (datos.nombre) html += '<div class="fac-cst-field"><label>Nombre / Razón Social</label><span>' + datos.nombre + '</span></div>';
    if (datos.cp)     html += '<div class="fac-cst-field"><label>CP Fiscal</label><span>' + datos.cp + '</span></div>';
    if (datos.regimen && datos.regimen.length) {
      var regs = [];
      for (var i = 0; i < datos.regimen.length; i++) regs.push(datos.regimen[i].label);
      html += '<div class="fac-cst-field" style="grid-column:1/-1"><label>Régimen(es) Fiscal(es)</label><span style="font-size:12px">' + regs.join('<br>') + '</span></div>';
    }
    document.getElementById('fac-cst-fields').innerHTML = html;

    var warn = '';
    if (!datos.rfc)    warn += '• RFC no encontrado — ingrésalo manualmente<br>';
    if (!datos.nombre) warn += '• Nombre no encontrado — ingrésalo manualmente<br>';
    if (!datos.cp)     warn += '• CP Fiscal no encontrado — ingrésalo manualmente<br>';
    if (!datos.regimen || !datos.regimen.length) warn += '• Régimen fiscal no detectado — selecciónalo manualmente<br>';
    var warnEl = document.getElementById('fac-cst-warn');
    warnEl.innerHTML = warn;
    warnEl.className = 'fac-cst-warn' + (warn ? ' visible' : '');

    document.getElementById('fac-cst-preview').className = 'fac-cst-preview visible';
  }

  function cstAplicar() {
    if (!_cstDatos) return;
    var d = _cstDatos;
    if (d.rfc)    document.getElementById('fac-rfc').value             = d.rfc;
    if (d.nombre) document.getElementById('fac-receptor-nombre').value = d.nombre;
    if (d.cp)     document.getElementById('fac-cp').value     = d.cp;
    // Régimen: si hay uno solo, seleccionarlo; si hay varios, seleccionar el primero y avisar
    if (d.regimen && d.regimen.length) {
      var sel = document.getElementById('fac-regimen');
      sel.value = d.regimen[0].cod;
      if (d.regimen.length > 1) {
        var nombres = [];
        for (var i = 0; i < d.regimen.length; i++) nombres.push(d.regimen[i].label);
        alert('Se encontraron ' + d.regimen.length + ' regímenes fiscales:\n\n' + nombres.join('\n') + '\n\nSe seleccionó el primero. Verifica con el cliente cuál aplica para esta factura.');
      }
    }
    // Ocultar preview tras aplicar
    document.getElementById('fac-cst-preview').className = 'fac-cst-preview';
    document.getElementById('fac-cst-file').value = '';
    _cstDatos = null;
  }

  var TIPO_HINTS = {
    'I':  'Venta normal de productos o servicios',
    'E':  'Devolución, descuento o bonificación a una factura emitida',
    'P':  'Registra el pago de una factura emitida en parcialidades (PPD)',
    'IG': 'Agrupa ventas a público general con RFC XAXX010101000'
  };

  function tipoChange() {
    var val  = document.getElementById('fac-tipo-cfdi').value;
    var hint = document.getElementById('fac-tipo-hint');
    if (hint) hint.textContent = TIPO_HINTS[val] || '';
  }

  function cstDescartar() {
    _cstDatos = null;
    document.getElementById('fac-cst-preview').className = 'fac-cst-preview';
    document.getElementById('fac-cst-error').className   = 'fac-cst-error';
    document.getElementById('fac-cst-file').value = '';
  }

  // ── Cerrar dropdown clave SAT al hacer click fuera
  document.addEventListener('click', function(e) {
    if (!e.target.closest('.fac-csat')) {
      var all = document.querySelectorAll('.fac-csat-list.open');
      for (var i = 0; i < all.length; i++) all[i].classList.remove('open');
    }
  });

  _cargarLista();

  return {
    abrirNueva:      abrirNueva,
    abrirEditar:     abrirEditar,
    cerrarModal:     cerrarModal,
    guardar:         guardar,
    eliminar:        eliminar,
    abrirEstatus:    abrirEstatus,
    cerrarEstatus:   cerrarEstatus,
    confirmarEstatus: confirmarEstatus,
    agregarConcepto: agregarConcepto,
    recalc:          recalc,
    _csatToggle:     _csatToggle,
    _csatPick:       _csatPick,
    cstDrag:         cstDrag,
    cstDrop:         cstDrop,
    cstSubir:        cstSubir,
    cstAplicar:      cstAplicar,
    cstDescartar:    cstDescartar,
    tipoChange:      tipoChange,
    timbrar:              timbrar,
    menuToggle:           menuToggle,
    menuCerrar:           menuCerrar,
    seleccionarClienteSelect: seleccionarClienteSelect,
    buscarOrden:          buscarOrden,
    sugerirOrdenes:       sugerirOrdenes,
    _elegirOrdenFolio:    _elegirOrdenFolio,
    limpiarCliente:       limpiarCliente,
    desbloquearReceptor:  desbloquearReceptor,
    togglePublicoGeneral: togglePublicoGeneral
  };
})();

window.ModFacturacion = ModFacturacion;
</script>
