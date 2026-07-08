<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_ordenes');
$_rol = $user['rol'];
$es_finanzas = in_array($_rol, ['administracion','dir_admin','dueno','desarrollo']);
if (!$es_finanzas) {
    echo '<div style="padding:40px;text-align:center;color:#dc2626">Sin permiso.</div>'; exit;
}
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=finanzas_cobranza'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<style>
.cob-wrap { padding: 24px; max-width: 1400px; margin: 0 auto; }
.page-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #94a3b8; margin-bottom: 20px; }

/* Filtros */
.filtros { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end; padding: 14px 16px; background: var(--c-bg); border: 1px solid var(--c-border); border-radius: var(--r-sm); }
.filtro-field { display: flex; flex-direction: column; gap: 3px; }
.filtro-field label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .4px; }
.filtro-field input, .filtro-field select {
  padding: 7px 11px; border: 1px solid #e2e8f0; border-radius: 6px;
  font-size: 13px; color: #1e293b; background: white; min-width: 150px;
  transition: border-color .15s;
}
.filtro-field input:focus, .filtro-field select:focus { outline: none; border-color: #1a1a2e; box-shadow: 0 0 0 3px rgba(26,26,46,.07); }
.btn-limpiar { padding: 7px 14px; background: white; color: #64748b; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; align-self: flex-end; transition: background .15s; }
.btn-limpiar:hover { background: #f1f5f9; color: #334155; }

/* KPIs */
.kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 20px; }
.kpi-card {
  background: white; border-radius: 10px; padding: 16px 18px;
  border: 1px solid #e2e8f0;
  display: flex; align-items: flex-start; gap: 12px;
}
.kpi-icon { width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.kpi-icon.azul  { background: #eff6ff; color: #2563eb; }
.kpi-icon.verde { background: #f0fdf4; color: #16a34a; }
.kpi-icon.rojo  { background: #fef2f2; color: #dc2626; }
.kpi-icon.gris  { background: #f8fafc; color: #64748b; }
.kpi-info { min-width: 0; }
.kpi-lbl { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
.kpi-val { font-size: 20px; font-weight: 800; color: #1e293b; font-variant-numeric: tabular-nums; }
.kpi-val.verde  { color: #16a34a; }
.kpi-val.rojo   { color: #dc2626; }
.kpi-val.azul   { color: #2563eb; }

/* Tabla */
.cob-table { background: white; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
thead th { padding: 10px 14px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #94a3b8; text-align: left; }
tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .12s; }
tbody tr:hover { background: #fafafa; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 11px 14px; font-size: 13px; vertical-align: middle; }

/* Barra de pago */
.pay-bar-wrap { display: flex; align-items: center; gap: 8px; min-width: 140px; }
.pay-bar { flex: 1; background: #e2e8f0; border-radius: 99px; height: 6px; overflow: hidden; }
.pay-fill { height: 100%; border-radius: 99px; transition: width .3s; }
.pay-fill.completo { background: #16a34a; }
.pay-fill.parcial  { background: #f59e0b; }
.pay-fill.sin-pago { background: #e2e8f0; }
.pay-pct { font-size: 11px; font-weight: 700; color: #64748b; min-width: 32px; text-align: right; }

/* Badge estado pago */
.pay-badge { font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 99px; white-space: nowrap; }
.pay-badge.completo { background: #dcfce7; color: #15803d; }
.pay-badge.parcial  { background: #fef3c7; color: #b45309; }
.pay-badge.sin-pago { background: #fee2e2; color: #dc2626; }

/* Selector estatus pago Lina */
.sel-epago { font-size: 11px; font-weight: 600; padding: 3px 7px; border-radius: 6px; border: 1.5px solid #e2e8f0; cursor: pointer; background: #f8fafc; outline: none; }
.sel-epago:focus { border-color: #1a1a2e; outline: none; }
.sel-epago.ep-pendiente   { border-color: #fca5a5; color: #dc2626; background: #fef2f2; }
.sel-epago.ep-en_proceso  { border-color: #fcd34d; color: #b45309; background: #fffbeb; }
.sel-epago.ep-pago_entrega{ border-color: #93c5fd; color: #1d4ed8; background: #eff6ff; }
.sel-epago.ep-pagado      { border-color: #6ee7b7; color: #15803d; background: #f0fdf4; }

/* Botón imprimir salida */
.btn-salida { font-size: 11px; font-weight: 600; padding: 5px 10px; border-radius: 6px; border: 1px solid #1a1a2e; cursor: pointer; background: white; color: #1a1a2e; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px; transition: background .15s; }
.btn-salida:hover { background: #1a1a2e; color: white; }
.btn-salida:disabled { background: #f1f5f9; color: #94a3b8; border-color: #e2e8f0; cursor: not-allowed; }

/* Badge estado orden */
.ord-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 99px; }
.ord-vobo    { background: #f3e8ff; color: #7c3aed; }
.ord-activa  { background: #dbeafe; color: #1d4ed8; }
.ord-entregada { background: #dcfce7; color: #15803d; }
.ord-cancelada { background: #f1f5f9; color: #94a3b8; }

/* Botón expandir */
.btn-expand { background: none; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 10px; font-size: 12px; cursor: pointer; color: #475569; font-weight: 600; transition: background .12s, border-color .12s; }
.btn-expand:hover { background: #f8fafc; border-color: #cbd5e1; color: #1e293b; }

/* Panel de pagos expandido */
.pagos-panel { display: none; background: #f8fafc; }
.pagos-panel.open { display: table-row; }
.pagos-inner { padding: 16px 20px; }
.pagos-lista { margin-bottom: 12px; }
.pago-row { display: flex; align-items: center; gap: 12px; padding: 7px 10px; background: white; border-radius: 8px; margin-bottom: 5px; font-size: 13px; border: 1px solid #e2e8f0; }
.pago-fecha { color: #64748b; font-size: 12px; min-width: 130px; }
.pago-forma { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.forma-efectivo      { background: #dcfce7; color: #15803d; }
.forma-tarjeta       { background: #dbeafe; color: #1d4ed8; }
.forma-transferencia { background: #f3e8ff; color: #7c3aed; }
.forma-saldo-favor   { background: #fef9c3; color: #78350f; }
.pago-monto { font-weight: 700; color: #1e293b; margin-left: auto; }
.pago-por   { font-size: 11px; color: #94a3b8; }
.sin-pagos  { font-size: 13px; color: #94a3b8; padding: 8px 0; }

/* Form pago inline */
.pago-form-inline { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; }
.pago-form-titulo { font-size: 11px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; display: flex; align-items: center; gap: 6px; }
.pago-form-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; }
.pf { display: flex; flex-direction: column; gap: 3px; }
.pf label { font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.pf input, .pf select { padding: 7px 10px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 13px; min-width: 110px; }
.pf input:focus, .pf select:focus { outline: none; border-color: #0369a1; }
.btn-reg { padding: 7px 14px; background: var(--c-blue); color: white; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; align-self: flex-end; transition: background .15s; }
.btn-reg:hover { background: var(--c-blue-dark); }

.loading-msg { text-align: center; padding: 48px; color: #94a3b8; }
.empty-msg   { text-align: center; padding: 48px; color: #94a3b8; }

/* Tabs */
.fin-tabs { display: flex; gap: 0; margin-bottom: 20px; border-bottom: 1.5px solid #e2e8f0; }
.fin-tab  { padding: 9px 20px; font-size: 13px; font-weight: 600; color: #94a3b8; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1.5px; background: none; border-top: none; border-left: none; border-right: none; transition: color .15s; display: flex; align-items: center; gap: 6px; }
.fin-tab:hover  { color: #475569; }
.fin-tab.active { color: #1a1a2e; border-bottom-color: #1a1a2e; font-weight: 700; }

/* Saldo a Favor */
.sf-toolbar { display: flex; gap: 10px; align-items: center; margin-bottom: 16px; flex-wrap: wrap; }
.sf-search  { flex: 1; min-width: 200px; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
.sf-search:focus { border-color: #2563eb; }
.btn-sf-nuevo { background: var(--c-blue); color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; transition: background .15s; }
.btn-sf-nuevo:hover { background: var(--c-blue-dark); }
.sf-table  { background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
.sf-badge  { font-size: 12px; font-weight: 700; padding: 3px 10px; border-radius: 99px; background: #dcfce7; color: #15803d; }
.sf-badge.cero { background: #f1f5f9; color: #94a3b8; }
.btn-sf-hist { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid #e2e8f0; background: white; cursor: pointer; color: #2563eb; }
.btn-sf-hist:hover { background: #eff6ff; }

/* Historial expandido */
.sf-hist-panel { display: none; background: #f8fafc; }
.sf-hist-panel.open { display: table-row; }
.sf-hist-inner { padding: 16px 20px; }
.sf-mov-row { display: flex; gap: 12px; align-items: center; padding: 7px 10px; background: white; border-radius: 8px; margin-bottom: 5px; font-size: 13px; border: 1px solid #e2e8f0; }
.sf-mov-tipo { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.sf-mov-deposito  { background: #dcfce7; color: #15803d; }
.sf-mov-aplicacion{ background: #dbeafe; color: #1d4ed8; }
.sf-mov-ajuste    { background: #fef3c7; color: #b45309; }
.sf-mov-fecha  { color: #64748b; font-size: 12px; min-width: 100px; }
.sf-mov-ref    { font-size: 12px; color: #64748b; flex: 1; }
.sf-mov-monto  { font-weight: 700; margin-left: auto; }
.sf-mov-monto.positivo { color: #16a34a; }
.sf-mov-monto.negativo { color: #dc2626; }

/* Modal depósito */
.sf-modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1200; align-items: center; justify-content: center; }
.sf-modal-bg.open { display: flex; }
.sf-modal { background: white; border-radius: 16px; width: 100%; max-width: 500px; margin: 16px; box-shadow: 0 20px 60px rgba(0,0,0,.2); overflow: hidden; }
.sf-modal-head { background: #0f172a; color: white; padding: 20px 24px; display: flex; justify-content: space-between; align-items: center; }
.sf-modal-head h3 { font-size: 16px; font-weight: 700; }
.sf-modal-body { padding: 24px; }
.sf-form-row { margin-bottom: 16px; }
.sf-form-label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-bottom: 6px; }
.sf-form-input, .sf-form-select { width: 100%; padding: 9px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; box-sizing: border-box; background: white; }
.sf-form-input:focus { border-color: #16a34a; }
.sf-modal-foot { display: flex; justify-content: flex-end; gap: 10px; padding: 16px 24px; border-top: 1px solid #f1f5f9; }
.sf-btn-cancel { background: none; border: 1px solid #e2e8f0; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; color: #64748b; transition: background .15s; }
.sf-btn-cancel:hover { background: #f8fafc; }
.sf-btn-save   { background: #1a1a2e; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background .15s; }
.sf-btn-save:hover    { background: #2d2d4a; }
.sf-btn-save:disabled { opacity: .6; cursor: not-allowed; }
.sf-cli-autocomplete { position: relative; }
.sf-cli-lista { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid var(--c-border); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); z-index: 1300; max-height: 200px; overflow-y: auto; display: none; }
.sf-cli-item  { padding: 9px 12px; cursor: pointer; font-size: 13px; border-bottom: 1px solid #f1f5f9; }
.sf-cli-item:hover { background: #f0fdf4; }
.sf-cli-item .sf-cli-cod { font-size: 11px; color: #94a3b8; }

@media(max-width:768px){
  .cob-wrap { padding: 12px; }

  /* Tabs */
  .fin-tabs { overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; }
  .fin-tab  { white-space: nowrap; padding: 10px 14px; font-size: 12px; flex-shrink: 0; }

  /* Filtros: en columna */
  .filtros { flex-direction: column; gap: 8px; }
  .filtro-field { width: 100%; }
  .filtro-field input,
  .filtro-field select { min-width: 0 !important; width: 100%; box-sizing: border-box; }
  .td-sel-epago .sel-epago { min-width: 0 !important; width: auto !important; max-width: 85px !important; font-size: 10px !important; padding: 3px 4px !important; }
  .btn-filtrar,
  .btn-limpiar { width: 100%; text-align: center; }

  /* KPIs: 2 columnas */
  .kpis { grid-template-columns: repeat(2, 1fr); gap: 10px; }
  .kpi-card { padding: 12px 14px; }
  .kpi-val  { font-size: 18px; }
  .kpi-lbl  { font-size: 10px; }

  /* Tabla cobranza: ocultar Asesor, Fecha, Estado, Cobrado, Avance, Pago */
  .cob-table thead th:nth-child(3),
  .cob-table thead th:nth-child(4),
  .cob-table thead th:nth-child(5),
  .cob-table thead th:nth-child(7),
  .cob-table thead th:nth-child(9),
  .cob-table thead th:nth-child(10),
  .cob-table tbody td:nth-child(3),
  .cob-table tbody td:nth-child(4),
  .cob-table tbody td:nth-child(5),
  .cob-table tbody td:nth-child(7),
  .cob-table tbody td:nth-child(9),
  .cob-table tbody td:nth-child(10) { display: none; }

  /* Tabla saldo a favor: ocultar Código, Teléfono, Último Movimiento */
  .sf-table thead th:nth-child(2),
  .sf-table thead th:nth-child(3),
  .sf-table thead th:nth-child(5),
  .sf-table tbody td:nth-child(2),
  .sf-table tbody td:nth-child(3),
  .sf-table tbody td:nth-child(5) { display: none; }

  thead th { padding: 8px 10px; font-size: 10px; white-space: normal; }
  tbody td  { padding: 9px 10px; font-size: 12px; }

  /* Estatus pago: select compacto */
  .td-sel-epago .sel-epago { font-size: 10px !important; padding: 3px 4px !important; width: 100%; max-width: 85px; }

  /* Botones col acciones: en columna */
  .td-acciones-cob { white-space: normal !important; display: table-cell !important; }
  .td-acciones-cob .btn-expand,
  .td-acciones-cob .btn-salida { display: block !important; width: 100%; margin-bottom: 4px; text-align: center; box-sizing: border-box; }

  /* Barra de pago más compacta */
  .pay-bar-wrap { min-width: 60px; }

  /* Panel de pagos expandido */
  .pago-form-row { flex-direction: column; }
  .pf input, .pf select { min-width: 0; width: 100%; }
  .btn-reg { width: 100%; text-align: center; }

  /* Toolbar saldo */
  .sf-toolbar { flex-direction: column; align-items: stretch; gap: 8px; }
  .sf-search  { width: 100%; min-width: 0; }
  .btn-sf-nuevo { width: 100%; text-align: center; }
}
</style>

<div class="cob-wrap">
  <div class="page-title">Finanzas</div>
  <div class="page-sub" id="cob-sub">Cargando...</div>

  <!-- Tabs -->
  <div class="fin-tabs">
    <button class="fin-tab active" id="tab-cobranza" onclick="sfSwitchTab('cobranza')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      Cobranza
    </button>
    <button class="fin-tab" id="tab-saldo" onclick="sfSwitchTab('saldo')">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
      Saldo a Favor
    </button>
  </div>

  <!-- Sección Saldo a Favor -->
  <div id="sec-saldo" style="display:none">
    <div class="sf-toolbar">
      <input type="text" class="sf-search" id="sf-q" placeholder="Buscar cliente..." oninput="sfFiltrar()">
      <button class="btn-sf-nuevo" onclick="sfAbrirModal()">&#43; Registrar Dep&#243;sito</button>
    </div>
    <div class="sf-table">
      <table>
        <thead><tr>
          <th>Cliente</th>
          <th>C&#243;digo</th>
          <th>Tel&#233;fono</th>
          <th>Saldo Disponible</th>
          <th>&#218;ltimo Movimiento</th>
          <th></th>
        </tr></thead>
        <tbody id="sf-tbody"><tr><td colspan="6" class="loading-msg">Cargando...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- Modal Registrar Depósito -->
  <div class="sf-modal-bg" id="sfModalBg">
    <div class="sf-modal">
      <div class="sf-modal-head">
        <h3>Registrar Dep&#243;sito — Saldo a Favor</h3>
        <button style="background:none;border:none;color:#94a3b8;font-size:20px;cursor:pointer;padding:0" onclick="sfCerrarModal()">&#10005;</button>
      </div>
      <div class="sf-modal-body">
        <div class="sf-form-row">
          <label class="sf-form-label">Cliente <span style="color:#ef4444">*</span></label>
          <div class="sf-cli-autocomplete">
            <input class="sf-form-input" id="sf-cli-busq" type="text" placeholder="Buscar cliente..." oninput="sfBuscarCliente()" autocomplete="off">
            <div class="sf-cli-lista" id="sf-cli-lista"></div>
            <input type="hidden" id="sf-cli-id">
          </div>
        </div>
        <div class="sf-form-row">
          <label class="sf-form-label">Monto <span style="color:#ef4444">*</span></label>
          <input class="sf-form-input" id="sf-monto" type="number" min="0.01" step="0.01" placeholder="0.00">
        </div>
        <div class="sf-form-row">
          <label class="sf-form-label">Fecha <span style="color:#ef4444">*</span></label>
          <input class="sf-form-input" id="sf-fecha" type="date">
        </div>
        <div class="sf-form-row">
          <label class="sf-form-label">Referencia / No. de transferencia</label>
          <input class="sf-form-input" id="sf-ref" type="text" placeholder="Ej. SPEI-123456">
        </div>
        <div class="sf-form-row">
          <label class="sf-form-label">Anotaciones</label>
          <input class="sf-form-input" id="sf-notas" type="text" placeholder="Notas adicionales...">
        </div>
      </div>
      <div class="sf-modal-foot">
        <button class="sf-btn-cancel" onclick="sfCerrarModal()">Cancelar</button>
        <button class="sf-btn-save" id="sf-btn-guardar" onclick="sfGuardar()">Guardar Dep&#243;sito</button>
      </div>
    </div>
  </div>

  <!-- Sección Cobranza -->
  <div id="sec-cobranza">
  <!-- Filtros -->
  <div class="filtros">
    <div class="filtro-field">
      <label>Cliente / Folio</label>
      <input type="text" id="f-q" placeholder="Buscar..." oninput="ModFinanzasCobranza._filtrar()">
    </div>
    <div class="filtro-field">
      <label>Estado orden</label>
      <select id="f-estado" onchange="ModFinanzasCobranza._filtrar()">
        <option value="">Todos</option>
        <option value="pendiente_vobo">Pendiente VoBo</option>
        <option value="activa">Activa</option>
        <option value="entregada">Entregada</option>
      </select>
    </div>
    <div class="filtro-field">
      <label>Estado pago</label>
      <select id="f-pago" onchange="ModFinanzasCobranza._filtrar()">
        <option value="">Todos</option>
        <option value="sin_pago">Sin pago</option>
        <option value="parcial">Parcial</option>
        <option value="completo">Completo</option>
      </select>
    </div>
    <div class="filtro-field">
      <label>Asesor</label>
      <select id="f-asesor" onchange="ModFinanzasCobranza._filtrar()">
        <option value="">Todos</option>
      </select>
    </div>
    <div class="filtro-field">
      <label>Desde</label>
      <input type="date" id="f-desde" onchange="ModFinanzasCobranza._filtrar()">
    </div>
    <div class="filtro-field">
      <label>Hasta</label>
      <input type="date" id="f-hasta" onchange="ModFinanzasCobranza._filtrar()">
    </div>
    <button class="btn-limpiar" onclick="ModFinanzasCobranza._limpiar()">&#10005; Limpiar</button>
  </div>

  <!-- KPIs -->
  <div class="kpis" id="cob-kpis">
    <div class="kpi-card">
      <div class="kpi-icon azul"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
      <div class="kpi-info"><div class="kpi-lbl">Total facturado</div><div class="kpi-val azul" id="k-total">—</div></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon verde"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
      <div class="kpi-info"><div class="kpi-lbl">Cobrado</div><div class="kpi-val verde" id="k-cobrado">—</div></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon rojo"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
      <div class="kpi-info"><div class="kpi-lbl">Por cobrar</div><div class="kpi-val rojo" id="k-porcobrar">—</div></div>
    </div>
    <div class="kpi-card">
      <div class="kpi-icon gris"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
      <div class="kpi-info"><div class="kpi-lbl">&#211;rdenes</div><div class="kpi-val" id="k-ordenes">—</div></div>
    </div>
  </div>

  <!-- Tabla -->
  <div class="cob-table">
    <table>
      <thead><tr>
        <th>Folio</th>
        <th>Cliente</th>
        <th>Asesor</th>
        <th>Fecha</th>
        <th>Estado</th>
        <th>Total</th>
        <th>Cobrado</th>
        <th>Pendiente</th>
        <th>Avance</th>
        <th>Pago</th>
        <th>Estatus pago</th>
        <th></th>
      </tr></thead>
      <tbody id="cob-tbody">
        <tr><td colspan="12" class="loading-msg">Cargando...</td></tr>
      </tbody>
    </table>
  </div>
  </div><!-- /sec-cobranza -->
</div>

<script>
var ModFinanzasCobranza = (function() {

var API        = '../api/finanzas.php';
var API_SF_COB = '../api/saldo_favor.php';
var _data      = [];
var _lista     = [];
var _abiertos  = {};
var _sfCache      = {}; // cliente_id -> saldo a favor disponible
var _clientePorCot = {}; // cot_id -> cliente_id

async function cargar() {
  try {
    var res  = await fetch(API + '?accion=cobranza&t=' + Date.now());
    var data = await res.json();
    _data = Array.isArray(data) ? data : [];
    poblarAsesores();
    filtrar();
    document.getElementById('cob-sub').textContent =
      'Actualizado a las ' + new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  } catch(e) {
    document.getElementById('cob-sub').textContent = 'Error al cargar';
  }
}

function poblarAsesores() {
  var sel = document.getElementById('f-asesor');
  var asesores = [];
  _data.forEach(function(o) {
    if (o.asesor && asesores.indexOf(o.asesor) === -1) asesores.push(o.asesor);
  });
  asesores.sort();
  asesores.forEach(function(a) {
    var opt = document.createElement('option');
    opt.value = a; opt.textContent = a;
    sel.appendChild(opt);
  });
}

function filtrar() {
  var q      = (document.getElementById('f-q')?.value || '').toLowerCase();
  var estado = document.getElementById('f-estado')?.value || '';
  var pago   = document.getElementById('f-pago')?.value   || '';
  var asesor = document.getElementById('f-asesor')?.value || '';
  var desde  = document.getElementById('f-desde')?.value  || '';
  var hasta  = document.getElementById('f-hasta')?.value  || '';

  _lista = _data.filter(function(o) {
    if (q && !(o.folio||'').toLowerCase().includes(q) && !(o.cliente_nombre||'').toLowerCase().includes(q)) return false;
    if (estado && o.estado !== estado) return false;
    if (asesor && o.asesor !== asesor) return false;
    if (desde  && o.fecha_pedido < desde) return false;
    if (hasta  && o.fecha_pedido > hasta) return false;
    if (pago) {
      var total  = parseFloat(o.total||0);
      var pagado = parseFloat(o.saldo_pagado||0);
      var ep = estadoPago(total, pagado);
      if (ep !== pago) return false;
    }
    return true;
  });

  renderKpis();
  renderTabla();
}

function estadoPago(total, pagado) {
  if (!total || total <= 0) return 'sin_pago';
  if (pagado <= 0) return 'sin_pago';
  if ((total - pagado) <= 0.99) return 'completo';
  return 'parcial';
}

function renderKpis() {
  var total = 0, cobrado = 0;
  _lista.forEach(function(o) {
    total   += parseFloat(o.total||0);
    cobrado += parseFloat(o.saldo_pagado||0);
  });
  var fmt = function(n) { return '$' + n.toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  document.getElementById('k-total').textContent    = fmt(total);
  document.getElementById('k-cobrado').textContent  = fmt(cobrado);
  document.getElementById('k-porcobrar').textContent= fmt(Math.max(0, total - cobrado));
  document.getElementById('k-ordenes').textContent  = _lista.length;
}

function renderTabla() {
  var tbody = document.getElementById('cob-tbody');
  if (!_lista.length) {
    tbody.innerHTML = '<tr><td colspan="12" class="empty-msg">Sin registros</td></tr>';
    return;
  }

  var fmt = function(n) { return '$' + parseFloat(n||0).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  var fmtF= function(f) { return f ? new Date(f+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}) : '—'; };

  var epagoOpts = [
    {v:'pendiente',    l:'Pendiente'},
    {v:'en_proceso',   l:'En proceso'},
    {v:'pago_entrega', l:'Pago a entrega'},
    {v:'pagado',       l:'Pagado'},
  ];

  var rows = '';
  for (var i = 0; i < _lista.length; i++) {
    var o      = _lista[i];
    var total  = parseFloat(o.total||0);
    var pagado = parseFloat(o.saldo_pagado||0);
    var pend   = Math.max(0, total - pagado);
    var pct    = total > 0 ? Math.min(100, Math.round(pagado/total*100)) : 0;
    var ep     = estadoPago(total, pagado);
    var epLabel= ep==='completo'?'Completo':ep==='parcial'?'Parcial':'Sin pago';
    var ordLabel= o.estado==='pendiente_vobo'?'Pend. VoBo':o.estado==='activa'?'Activa':o.estado==='entregada'?'Entregada':'Cancelada';
    var ordClass= 'ord-'+(o.estado==='pendiente_vobo'?'vobo':o.estado);
    var abierto = _abiertos[o.cot_id] ? 'open' : '';
    _clientePorCot[o.cot_id] = o.cliente_id;

    var epActual = o.estatus_pago || 'pendiente';
    var selOpts  = epagoOpts.map(function(opt) {
      return '<option value="' + opt.v + '"' + (opt.v === epActual ? ' selected' : '') + '>' + opt.l + '</option>';
    }).join('');
    var selHtml  = '<select class="sel-epago ep-' + epActual + '" '
      + 'onchange="ModFinanzasCobranza._cambiarEpago(' + o.cot_id + ',this)">'
      + selOpts + '</select>';

    var puedeImprimir = pagado >= total || ['en_proceso','pago_entrega','pagado'].indexOf(epActual) !== -1;
    var btnSalida = '<button class="btn-salida" '
      + (puedeImprimir ? 'onclick="window.open(\'imprimir_salida.php?id=' + o.cot_id + '\',\'_blank\')"' : 'disabled')
      + '><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Salida</button>';

    rows += '<tr>'
      + '<td style="font-weight:700;color:#2563eb">' + escHtml(o.folio) + '</td>'
      + '<td>' + escHtml(o.cliente_nombre) + '</td>'
      + '<td style="font-size:12px;color:#64748b">' + escHtml(o.asesor||'—') + '</td>'
      + '<td style="font-size:12px;color:#64748b">' + fmtF(o.fecha_pedido) + '</td>'
      + '<td><span class="ord-badge ' + ordClass + '">' + ordLabel + '</span></td>'
      + '<td style="font-weight:600">' + fmt(total) + '</td>'
      + '<td style="color:#16a34a;font-weight:600">' + fmt(pagado) + '</td>'
      + '<td style="color:' + (pend>0?'#dc2626':'#16a34a') + ';font-weight:600">' + fmt(pend) + '</td>'
      + '<td><div class="pay-bar-wrap"><div class="pay-bar"><div class="pay-fill ' + ep + '" style="width:' + pct + '%"></div></div><span class="pay-pct">' + pct + '%</span></div></td>'
      + '<td><span class="pay-badge ' + ep + '">' + epLabel + '</span></td>'
      + '<td class="td-sel-epago">' + selHtml + '</td>'
      + '<td class="td-acciones-cob">'
      +   '<button class="btn-expand" onclick="ModFinanzasCobranza._toggle(' + o.cot_id + ',' + i + ')">Ver pagos</button>'
      +   btnSalida
      + '</td>'
      + '</tr>'
      + '<tr class="pagos-panel ' + abierto + '" id="panel-' + o.cot_id + '">'
      + '<td colspan="12">' + renderPanelPagos(o) + '</td>'
      + '</tr>';
  }
  tbody.innerHTML = rows;

  // Refrescar saldo a favor de paneles ya abiertos (ej. tras registrar un pago)
  _lista.forEach(function(o) {
    if (_abiertos[o.cot_id] && o.cliente_id && _sfCache[o.cliente_id] === undefined) {
      cargarSaldoFavor(o.cliente_id, o.cot_id);
    }
  });
}

var FORMA_LABELS = {efectivo:'Efectivo', tarjeta:'Tarjeta', transferencia:'Transferencia', saldo_favor:'Saldo a Favor'};

function renderPanelPagos(o) {
  var pagos  = o.pagos || [];
  var fmt    = function(n) { return '$' + parseFloat(n||0).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  var totalP  = parseFloat(o.total||0);
  var pendP   = Math.max(0, totalP - parseFloat(o.saldo_pagado||0));
  var html   = '<div class="pagos-inner">';

  html += '<div class="pagos-lista">';
  if (pagos.length) {
    pagos.forEach(function(p) {
      var fc = 'forma-' + p.forma_pago.replace('_','-');
      var fl = FORMA_LABELS[p.forma_pago] || (p.forma_pago.charAt(0).toUpperCase() + p.forma_pago.slice(1));
      html += '<div class="pago-row">'
        + '<div class="pago-fecha">' + p.fecha_pago + ' ' + (p.hora_pago||'').substring(0,5) + '</div>'
        + '<span class="pago-forma ' + fc + '">' + fl + '</span>'
        + (p.notas ? '<span style="font-size:12px;color:#64748b">' + escHtml(p.notas) + '</span>' : '')
        + '<div class="pago-monto">' + fmt(p.monto) + '</div>'
        + '<div class="pago-por">por ' + escHtml(p.registrado_por||'—') + '</div>'
        + '</div>';
    });
  } else {
    html += '<div class="sin-pagos">Sin pagos registrados</div>';
  }
  html += '</div>';

  // Form registrar pago
  html += '<div class="pago-form-inline">';
  html += '<div class="pago-form-titulo"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Registrar pago</div>';
  html += '<div class="pago-form-row">';
  html += '<div class="pf"><label>Fecha</label><input type="date" id="pf-fecha-' + o.cot_id + '" value="' + new Date().toISOString().substring(0,10) + '"></div>';
  html += '<div class="pf"><label>Hora</label><input type="time" id="pf-hora-' + o.cot_id + '" value="' + new Date().toTimeString().substring(0,5) + '"></div>';
  html += '<div class="pf"><label>Monto $</label><input type="number" id="pf-monto-' + o.cot_id + '" min="0" step="0.01" placeholder="0.00" style="min-width:130px"></div>';
  var sfSaldo = _sfCache[o.cliente_id];
  var sfLabel = (typeof sfSaldo === 'number' && sfSaldo > 0)
    ? 'Saldo a Favor (Disp: $' + sfSaldo.toLocaleString('es-MX',{minimumFractionDigits:2}) + ')'
    : 'Saldo a Favor';
  html += '<div class="pf"><label>Forma</label><select id="pf-forma-' + o.cot_id + '" onchange="ModFinanzasCobranza._onFormaChange(' + o.cot_id + ',' + pendP.toFixed(2) + ')"><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="transferencia">Transferencia</option><option value="saldo_favor">' + escHtml(sfLabel) + '</option></select></div>';
  html += '<div class="pf" style="flex:1"><label>Notas</label><input type="text" id="pf-notas-' + o.cot_id + '" placeholder="Opcional..." style="min-width:180px"></div>';
  html += '<button class="btn-reg" onclick="ModFinanzasCobranza._registrarPago(' + o.cot_id + ')">Registrar</button>';
  html += '</div></div></div>';

  return html;
}

function toggle(cot_id, idx) {
  var panel = document.getElementById('panel-' + cot_id);
  if (!panel) return;
  if (_abiertos[cot_id]) {
    delete _abiertos[cot_id];
    panel.classList.remove('open');
    var btn = panel.previousElementSibling?.querySelector('.btn-expand');
    if (btn) btn.textContent = 'Ver pagos';
  } else {
    _abiertos[cot_id] = true;
    panel.classList.add('open');
    var btn2 = panel.previousElementSibling?.querySelector('.btn-expand');
    if (btn2) btn2.textContent = 'Cerrar';
    var o = _lista[idx];
    if (o && o.cliente_id && _sfCache[o.cliente_id] === undefined) {
      cargarSaldoFavor(o.cliente_id, cot_id);
    }
  }
}

// ── Saldo a Favor: carga perezosa + actualización del select ya renderizado ──
async function cargarSaldoFavor(cliente_id, cot_id) {
  try {
    var res  = await fetch(API_SF_COB + '?accion=saldo&cliente_id=' + cliente_id);
    var data = await res.json();
    _sfCache[cliente_id] = parseFloat(data.saldo || 0);
  } catch (e) {
    _sfCache[cliente_id] = 0;
  }
  var sel = document.getElementById('pf-forma-' + cot_id);
  if (!sel) return;
  var saldo = _sfCache[cliente_id];
  var opt = sel.querySelector('option[value="saldo_favor"]');
  if (opt) {
    opt.textContent = saldo > 0
      ? 'Saldo a Favor (Disp: $' + saldo.toLocaleString('es-MX',{minimumFractionDigits:2}) + ')'
      : 'Saldo a Favor';
  }
}

// ── Auto-rellenar monto al elegir Saldo a Favor ───────────────
function onFormaChange(cot_id, pendiente) {
  var sel = document.getElementById('pf-forma-' + cot_id);
  var forma = sel ? sel.value : '';
  if (forma !== 'saldo_favor') return;
  var saldo = _sfCache[_clientePorCot[cot_id]];
  if (typeof saldo === 'number' && saldo > 0) {
    var montoInput = document.getElementById('pf-monto-' + cot_id);
    if (montoInput) montoInput.value = Math.min(saldo, pendiente).toFixed(2);
  }
}

async function registrarPago(cot_id) {
  var fecha = document.getElementById('pf-fecha-' + cot_id)?.value;
  var hora  = (document.getElementById('pf-hora-'  + cot_id)?.value || '00:00') + ':00';
  var monto = parseFloat(document.getElementById('pf-monto-' + cot_id)?.value || 0);
  var forma = document.getElementById('pf-forma-' + cot_id)?.value;
  var notas = document.getElementById('pf-notas-' + cot_id)?.value || '';

  if (!monto || monto <= 0) { alert('Ingresa un monto válido'); return; }

  var clienteId = _clientePorCot[cot_id];
  if (forma === 'saldo_favor') {
    var saldoDisp = _sfCache[clienteId];
    if (typeof saldoDisp === 'number' && monto > saldoDisp) {
      alert('El monto excede el Saldo a Favor disponible ($' + saldoDisp.toLocaleString('es-MX',{minimumFractionDigits:2}) + ')');
      return;
    }
  }

  var res  = await fetch(API, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({accion:'registrar_pago', cotizacion_id: cot_id, fecha_pago: fecha, hora_pago: hora, monto: monto, forma_pago: forma, notas: notas})
  });
  var data = await res.json();
  if (data.ok) {
    _abiertos[cot_id] = true;
    delete _sfCache[clienteId]; // forzar re-fetch: el saldo a favor pudo cambiar (uso o excedente)
    await cargar();
    if (data.excedente) {
      alert('Pago registrado.\n\nEl excedente de $' + parseFloat(data.excedente).toLocaleString('es-MX',{minimumFractionDigits:2}) + ' fue abonado al saldo a favor del cliente.');
    }
  } else {
    alert(data.error || 'Error al registrar pago');
  }
}

async function cambiarEpago(cot_id, sel) {
  var nuevo = sel.value;
  // Quitar clases anteriores y aplicar la nueva
  sel.className = 'sel-epago ep-' + nuevo;

  var res  = await fetch(API, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({accion:'actualizar_estatus_pago', cotizacion_id: cot_id, estatus_pago: nuevo})
  });
  var data = await res.json();
  if (data.ok) {
    _abiertos[cot_id] = true;
    await cargar();
  } else {
    alert(data.error || 'Error al actualizar');
    sel.value = sel.dataset.prev || 'pendiente';
  }
  sel.dataset.prev = nuevo;
}

function limpiar() {
  document.getElementById('f-q').value      = '';
  document.getElementById('f-estado').value = '';
  document.getElementById('f-pago').value   = '';
  document.getElementById('f-asesor').value = '';
  document.getElementById('f-desde').value  = '';
  document.getElementById('f-hasta').value  = '';
  filtrar();
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

cargar();

return {
  init:           cargar,
  _filtrar:       filtrar,
  _limpiar:       limpiar,
  _toggle:        toggle,
  _registrarPago: registrarPago,
  _cambiarEpago:  cambiarEpago,
  _onFormaChange: onFormaChange,
};
})();

// ── Saldo a Favor ─────────────────────────────────────────────────────────────
var _sfData     = [];
var _sfLista    = [];
var _sfAbiertos = {};
var _sfCliTimer = null;
var API_SF = '../api/saldo_favor.php';

function sfSwitchTab(tab) {
  var isCobranza = tab === 'cobranza';
  document.getElementById('tab-cobranza').classList.toggle('active', isCobranza);
  document.getElementById('tab-saldo').classList.toggle('active', !isCobranza);
  document.getElementById('sec-saldo').style.display   = isCobranza ? 'none' : '';
  // Ocultar/mostrar sección de cobranza
  var secCob = document.getElementById('sec-cobranza');
  if (secCob) secCob.style.display = isCobranza ? '' : 'none';
  if (!isCobranza && !_sfData.length) sfCargar();
}
window.sfSwitchTab = sfSwitchTab;

async function sfCargar() {
  try {
    var res  = await fetch(API_SF + '?accion=lista&t=' + Date.now());
    _sfData  = await res.json();
    sfFiltrar();
  } catch(e) {}
}

function sfFiltrar() {
  var q = (document.getElementById('sf-q')?.value || '').toLowerCase();
  _sfLista = _sfData.filter(function(c) {
    if (!q) return true;
    return (c.razon_social||'').toLowerCase().includes(q) || (c.codigo||'').toLowerCase().includes(q);
  });
  sfRender();
}
window.sfFiltrar = sfFiltrar;

function sfRender() {
  var tbody = document.getElementById('sf-tbody');
  if (!tbody) return;
  if (!_sfLista.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty-msg">Sin clientes</td></tr>'; return;
  }
  var fmt = function(n) { return '$' + parseFloat(n||0).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); };
  var rows = '';
  _sfLista.forEach(function(c, i) {
    var saldo = parseFloat(c.saldo||0);
    var badgeCls = saldo > 0 ? 'sf-badge' : 'sf-badge cero';
    var abierto  = _sfAbiertos[c.id] ? 'open' : '';
    rows += '<tr>'
      + '<td style="font-weight:600;color:#1e293b">' + escHtmlSf(c.razon_social) + '</td>'
      + '<td style="font-size:12px;color:#64748b">' + escHtmlSf(c.codigo||'—') + '</td>'
      + '<td style="font-size:12px;color:#64748b">' + escHtmlSf(c.telefono||'—') + '</td>'
      + '<td><span class="' + badgeCls + '">' + fmt(saldo) + '</span></td>'
      + '<td style="font-size:12px;color:#64748b">' + (c.ultimo_movimiento || '—') + '</td>'
      + '<td><button class="btn-sf-hist" onclick="sfToggleHist(' + c.id + ',' + i + ')">Historial</button></td>'
      + '</tr>'
      + '<tr class="sf-hist-panel ' + abierto + '" id="sfhist-' + c.id + '">'
      + '<td colspan="6"><div class="sf-hist-inner" id="sfhist-inner-' + c.id + '"><div style="color:#94a3b8;font-size:13px">Cargando...</div></div></td>'
      + '</tr>';
  });
  tbody.innerHTML = rows;
}

async function sfToggleHist(cid, idx) {
  var panel = document.getElementById('sfhist-' + cid);
  if (!panel) return;
  if (_sfAbiertos[cid]) {
    delete _sfAbiertos[cid];
    panel.classList.remove('open');
  } else {
    _sfAbiertos[cid] = true;
    panel.classList.add('open');
    // Cargar historial
    try {
      var res  = await fetch(API_SF + '?accion=historial&cliente_id=' + cid);
      var movs = await res.json();
      var inner = document.getElementById('sfhist-inner-' + cid);
      if (!inner) return;
      var fmt = function(n) { return '$' + parseFloat(n||0).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); };
      if (!movs.length) {
        inner.innerHTML = '<div style="color:#94a3b8;font-size:13px;padding:8px 0">Sin movimientos registrados</div>'; return;
      }
      var html = movs.map(function(m) {
        var pos = parseFloat(m.monto) >= 0;
        return '<div class="sf-mov-row">'
          + '<span class="sf-mov-tipo sf-mov-' + m.tipo + '">' + m.tipo.charAt(0).toUpperCase() + m.tipo.slice(1) + '</span>'
          + '<span class="sf-mov-fecha">' + m.fecha + '</span>'
          + (m.referencia ? '<span class="sf-mov-ref">' + escHtmlSf(m.referencia) + '</span>' : '<span class="sf-mov-ref" style="font-style:italic;color:#cbd5e1">Sin referencia</span>')
          + (m.notas ? '<span style="font-size:12px;color:#64748b">' + escHtmlSf(m.notas) + '</span>' : '')
          + '<span class="sf-mov-monto ' + (pos?'positivo':'negativo') + '">' + (pos?'+':'') + fmt(m.monto) + '</span>'
          + '<span style="font-size:11px;color:#94a3b8">por ' + escHtmlSf(m.creado_por||'—') + '</span>'
          + '</div>';
      }).join('');
      inner.innerHTML = html;
    } catch(e) {}
  }
}
window.sfToggleHist = sfToggleHist;

function sfAbrirModal(clienteId, clienteNombre) {
  document.getElementById('sf-cli-busq').value = clienteNombre || '';
  document.getElementById('sf-cli-id').value   = clienteId    || '';
  document.getElementById('sf-monto').value    = '';
  document.getElementById('sf-fecha').value    = new Date().toISOString().substring(0,10);
  document.getElementById('sf-ref').value      = '';
  document.getElementById('sf-notas').value    = '';
  document.getElementById('sf-cli-lista').style.display = 'none';
  document.getElementById('sfModalBg').classList.add('open');
  setTimeout(function() {
    var el = clienteId ? document.getElementById('sf-monto') : document.getElementById('sf-cli-busq');
    if (el) el.focus();
  }, 80);
}
window.sfAbrirModal = sfAbrirModal;

function sfCerrarModal() {
  document.getElementById('sfModalBg').classList.remove('open');
}
window.sfCerrarModal = sfCerrarModal;

function sfBuscarCliente() {
  clearTimeout(_sfCliTimer);
  _sfCliTimer = setTimeout(async function() {
    var q = document.getElementById('sf-cli-busq').value.trim();
    if (q.length < 2) { document.getElementById('sf-cli-lista').style.display = 'none'; return; }
    try {
      var res   = await fetch('../api/clientes.php?q=' + encodeURIComponent(q));
      var items = await res.json();
      var lista = document.getElementById('sf-cli-lista');
      if (!items.length) { lista.style.display = 'none'; return; }
      lista.innerHTML = items.map(function(c) {
        var nombre = c.razon_social || c.nombre;
        return '<div class="sf-cli-item" onclick="sfSelCliente(' + c.id + ',\'' + escJsSf(nombre) + '\')">'
          + escHtmlSf(nombre)
          + '<div class="sf-cli-cod">' + escHtmlSf(c.codigo||'') + '</div>'
          + '</div>';
      }).join('');
      lista.style.display = 'block';
    } catch(e) {}
  }, 250);
}
window.sfBuscarCliente = sfBuscarCliente;

function sfSelCliente(id, nombre) {
  document.getElementById('sf-cli-id').value   = id;
  document.getElementById('sf-cli-busq').value = nombre;
  document.getElementById('sf-cli-lista').style.display = 'none';
  document.getElementById('sf-monto').focus();
}
window.sfSelCliente = sfSelCliente;

async function sfGuardar() {
  var cid    = document.getElementById('sf-cli-id').value;
  var monto  = parseFloat(document.getElementById('sf-monto').value || 0);
  var fecha  = document.getElementById('sf-fecha').value;
  var ref    = document.getElementById('sf-ref').value.trim();
  var notas  = document.getElementById('sf-notas').value.trim();

  if (!cid)      { alert('Selecciona un cliente'); document.getElementById('sf-cli-busq').focus(); return; }
  if (monto <= 0){ alert('El monto debe ser mayor a cero'); document.getElementById('sf-monto').focus(); return; }
  if (!fecha)    { alert('La fecha es obligatoria'); return; }

  var btn = document.getElementById('sf-btn-guardar');
  btn.disabled = true; btn.textContent = 'Guardando...';
  try {
    var r = await fetch(API_SF, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({accion:'deposito', cliente_id: parseInt(cid), monto: monto, fecha: fecha, referencia: ref, notas: notas})
    });
    var d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Error desconocido');
    sfCerrarModal();
    await sfCargar();
    // Abrir historial del cliente recién registrado
    _sfAbiertos[parseInt(cid)] = true;
    sfRender();
    sfToggleHist(parseInt(cid), 0);
  } catch(e) {
    alert('Error: ' + e.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Guardar Depósito';
  }
}
window.sfGuardar = sfGuardar;

function escHtmlSf(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJsSf(s) {
  return String(s||'').replace(/'/g,"\\'").replace(/\n/g,' ');
}
</script>