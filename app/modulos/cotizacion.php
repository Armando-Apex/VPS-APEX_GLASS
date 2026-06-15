<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user       = requirePermiso('ver_ordenes');
$_rol       = $user['rol'];
$id_php     = (int)($_GET['id']    ?? 0);
$nuevo_php  = isset($_GET['nuevo']) ? 1 : 0;
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    $p = $id_php ? '&id='.$id_php : ($nuevo_php ? '&nuevo=1' : '');
    header('Location: ../dashboard.php?m=cotizacion'.$p); exit;
}
header('Content-Type: text/html; charset=utf-8');

// Variables de control para el JS
$es_admin      = in_array($_rol, ['dir_admin','dueno']);
$puede_editar  = in_array($_rol, ['dir_admin','dueno','comercial']);
$es_dir_admin  = ($_rol === 'dir_admin');
$modo         = $id_php ? 'ver' : 'nuevo';
$id_cot       = $id_php;
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }
.main { padding: 24px; max-width: 1400px; margin: 0 auto; }
.card { background: white; border-radius: 14px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; }
.card-title { font-size: 15px; font-weight: 800; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.folio-badge { background: #1a1a2e; color: white; font-size: 22px; font-weight: 800; padding: 8px 20px; border-radius: 10px; font-family: 'Syncopate', sans-serif; letter-spacing: 2px; }
.form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.span-2 { grid-column: span 2; }
.span-3 { grid-column: span 3; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
.field input, .field select, .field textarea { padding: 10px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #1e293b; background: white; width: 100%; }
.field input:focus, .field select:focus { outline: none; border-color: #2563eb; }
.field input[readonly] { background: #f8fafc; color: #64748b; }
.field .hint { font-size: 11px; color: #94a3b8; }
/* Partidas */
.partidas-header { display: grid; grid-template-columns: 34px 190px 54px 72px 72px 115px 145px 50px 50px 50px 70px 125px 34px; gap: 5px; padding: 6px 8px; background: #f8fafc; border-radius: 8px; margin-bottom: 6px; }
.partidas-header span { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.partida-row { display: grid; grid-template-columns: 34px 190px 54px 72px 72px 115px 145px 50px 50px 50px 70px 125px 34px; gap: 5px; padding: 8px; background: white; border: 1.5px solid #e2e8f0; border-radius: 10px; margin-bottom: 6px; align-items: center; }
.partida-row input, .partida-row select { padding: 6px 7px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 12px; width: 100%; }
.partida-row input:focus, .partida-row select:focus { outline: none; border-color: #2563eb; }
.partida-row input[readonly] { background: #f8fafc; color: #64748b; }
.num-partida { font-weight: 800; color: #2563eb; text-align: center; font-size: 14px; }
.btn-del { background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; width: 30px; height: 30px; cursor: pointer; font-size: 15px; display: flex; align-items: center; justify-content: center; }
/* Totales */
.totales-box { background: #f8fafc; border-radius: 12px; padding: 20px; margin-top: 16px; max-width: 380px; margin-left: auto; }
.totales-row { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; font-size: 14px; color: #374151; }
.totales-row.total-final { font-size: 20px; font-weight: 800; color: #1e293b; border-top: 2px solid #e2e8f0; padding-top: 12px; margin-top: 4px; }
/* Botones */
.btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn-primary  { background: #2563eb; color: white; }
.btn-success  { background: #16a34a; color: white; }
.btn-warning  { background: #d97706; color: white; }
.btn-danger   { background: #dc2626; color: white; }
.btn-ghost    { background: #f1f5f9; color: #374151; }
.btn-sm { padding: 7px 14px; font-size: 12px; }
.acciones { display: flex; gap: 10px; flex-wrap: wrap; }
/* Badges */
.badge { font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; }
.badge-cotizacion { background: #dbeafe; color: #1d4ed8; }
.badge-orden      { background: #fef3c7; color: #d97706; }
.badge-entregada  { background: #dcfce7; color: #16a34a; }
.badge-cancelada  { background: #f1f5f9; color: #94a3b8; }
/* Alerta saldo */
.alerta-saldo { background: #fee2e2; border: 1.5px solid #fca5a5; border-radius: 10px; padding: 14px 18px; color: #dc2626; font-weight: 700; margin-bottom: 16px; display: none; }
.alerta-saldo.show { display: flex; align-items: center; gap: 10px; }
/* ── Modal correcciones ── */
.corr-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1400; align-items:flex-start; justify-content:center; padding:24px 16px; overflow-y:auto; }
.corr-bg.open { display:flex; }
.corr-modal { background:white; border-radius:16px; width:100%; max-width:980px; box-shadow:0 24px 64px rgba(0,0,0,.22); overflow:hidden; margin:auto; }
.corr-head { background:#0f172a; color:white; padding:18px 24px; display:flex; justify-content:space-between; align-items:center; }
.corr-head h2 { font-size:15px; font-weight:800; letter-spacing:.3px; }
.corr-close { background:none; border:none; color:#94a3b8; font-size:20px; cursor:pointer; line-height:1; padding:2px 6px; }
.corr-close:hover { color:white; }
.corr-tabs { display:flex; gap:0; border-bottom:2px solid #e2e8f0; padding:0 24px; }
.corr-tab { padding:12px 20px; font-size:13px; font-weight:700; color:#64748b; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; background:none; border-top:none; border-left:none; border-right:none; }
.corr-tab.active { color:#2563eb; border-bottom-color:#2563eb; }
.corr-body { padding:24px; max-height:calc(100vh - 200px); overflow-y:auto; }
.corr-motivo-wrap { margin-bottom:20px; }
.corr-motivo-wrap label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b; margin-bottom:6px; }
.corr-motivo-wrap textarea { width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:13px; resize:vertical; min-height:60px; font-family:inherit; }
.corr-motivo-wrap textarea:focus { outline:none; border-color:#2563eb; }
.corr-section { margin-bottom:24px; }
.corr-section-title { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.6px; color:#94a3b8; margin-bottom:12px; padding-bottom:6px; border-bottom:1px solid #f1f5f9; }
.corr-hdr-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:4px; }
.corr-field label { display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
.corr-field input, .corr-field select { width:100%; padding:8px 12px; border:1.5px solid #e2e8f0; border-radius:7px; font-size:13px; background:white; }
.corr-field input:focus, .corr-field select:focus { outline:none; border-color:#2563eb; }
.corr-partidas-table { width:100%; border-collapse:collapse; font-size:12px; }
.corr-partidas-table th { padding:7px 8px; background:#f8fafc; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#64748b; text-align:left; white-space:nowrap; }
.corr-partidas-table td { padding:6px 6px; border-top:1px solid #f1f5f9; vertical-align:middle; }
.corr-partidas-table td input, .corr-partidas-table td select { width:100%; padding:5px 7px; border:1.5px solid #e2e8f0; border-radius:6px; font-size:12px; background:white; min-width:0; }
.corr-partidas-table td input:focus, .corr-partidas-table td select:focus { outline:none; border-color:#2563eb; }
.corr-partidas-table td input[disabled], .corr-partidas-table td select[disabled] { background:#f1f5f9; color:#94a3b8; cursor:not-allowed; }
.corr-num { font-weight:800; color:#2563eb; text-align:center; font-size:13px; white-space:nowrap; }
.corr-ref  { color:#374151; font-size:11px; white-space:nowrap; max-width:130px; overflow:hidden; text-overflow:ellipsis; }
.corr-foot { display:flex; justify-content:flex-end; gap:10px; padding:16px 24px; border-top:1px solid #f1f5f9; }
/* Historial */
.hist-table { width:100%; border-collapse:collapse; font-size:13px; }
.hist-table th { padding:9px 12px; background:#f8fafc; font-size:10px; font-weight:700; text-transform:uppercase; color:#64748b; letter-spacing:.4px; text-align:left; }
.hist-table td { padding:9px 12px; border-top:1px solid #f1f5f9; vertical-align:top; }
.hist-campo { font-weight:700; color:#1e293b; font-family:'Courier New',monospace; font-size:12px; }
.hist-ant { color:#dc2626; text-decoration:line-through; font-size:12px; }
.hist-nvo { color:#16a34a; font-weight:700; font-size:12px; }
.hist-motivo { color:#64748b; font-size:12px; font-style:italic; }
.hist-fecha  { color:#94a3b8; font-size:11px; white-space:nowrap; }
.hist-empty  { text-align:center; padding:40px; color:#94a3b8; }
.btn-corregir { background:#7c3aed; color:white; }
/* Banner saldo a favor */
.banner-saldo-favor { display: none; background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 10px; padding: 12px 16px; color: #15803d; font-size: 13px; font-weight: 600; margin-top: 8px; align-items: center; gap: 8px; }
/* ── Autorización de descuento ── */
.auth-banner { border-radius: 10px; padding: 14px 18px; margin-bottom: 16px; font-size: 13px; line-height: 1.6; }
.auth-banner.auth-pendiente  { background: #fffbeb; border: 1.5px solid #fcd34d; color: #92400e; }
.auth-banner.auth-aprobado   { background: #f0fdf4; border: 1.5px solid #86efac; color: #166534; }
.auth-banner.auth-rechazado  { background: #fef2f2; border: 1.5px solid #fca5a5; color: #991b1b; }
.auth-acciones { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
.auth-acciones textarea { font-size: 12px; border: 1px solid #d1d5db; border-radius: 6px; padding: 6px 10px; resize: vertical; }
/* ── Modal motivo descuento ── */
.motivo-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 20px; }
.motivo-box { background: white; border-radius: 16px; padding: 28px; max-width: 480px; width: 100%; box-shadow: 0 24px 64px rgba(0,0,0,.22); }
.motivo-box h3 { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
.motivo-box p  { font-size: 13px; color: #64748b; margin-bottom: 10px; }
.motivo-box textarea { width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; font-size: 13px; resize: vertical; outline: none; }
.motivo-box textarea:focus { border-color: #6366f1; }
.banner-saldo-favor.show { display: flex; }
/* Autocomplete cliente */
.autocomplete-wrap { position: relative; }
.autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1.5px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); z-index: 100; max-height: 240px; overflow-y: auto; }
.autocomplete-item { padding: 10px 14px; cursor: pointer; font-size: 14px; }
.autocomplete-item:hover { background: #f0f4f8; }
.autocomplete-item .codigo { font-size: 11px; color: #94a3b8; margin-top: 2px; }
</style>

<div class="main" id="mainContent">
  <div style="padding:48px;text-align:center;color:#94a3b8">Cargando...</div>
</div>

<script>
var ModCotizacion = (function() {

var API_COT      = '../api/cotizaciones.php';
var API_CLI      = '../api/clientes.php';
var API_CRIS     = '../api/cristales.php';
var ES_ADMIN     = <?= $es_admin ? 'true' : 'false' ?>;
var PUEDE_EDITAR = <?= $puede_editar ? 'true' : 'false' ?>;
var ES_DIR_ADMIN = <?= $es_dir_admin ? 'true' : 'false' ?>;
var MODO         = '<?= $modo ?>';
var ID_COT       = <?= $id_cot ?>;

var cristales           = [];
var clienteSeleccionado = null;
var partidas            = [];
var _buscarTimer        = null;
var _dataCot            = null;
var _authStatus         = null;

// ── Inicializar ────────────────────────────────────────────────────────────────
async function init() {
  var res = await fetch(API_CRIS + '?activos=1');
  cristales = await res.json();

  if (MODO === 'nuevo') {
    renderFormulario(null);
  } else {
    var res2 = await fetch(API_COT + '?id=' + ID_COT);
    var data  = await res2.json();
    if (data && data.error) {
      document.getElementById('mainContent').innerHTML =
        '<div style="padding:40px;text-align:center;color:#dc2626">Error: ' + escHtml(data.error) + '</div>';
      return;
    }
    renderFormulario(data);
  }
}

// ── Render principal ───────────────────────────────────────────────────────────
function renderFormulario(data) {
  var esNuevo   = !data;
  var editable   = PUEDE_EDITAR && (esNuevo || data.estatus === 'cotizacion');
  var estatus    = data ? data.estatus : 'cotizacion';
  var bloqueada  = data && data.entrega_bloqueada == 1 && data.estatus === 'orden';
  var tieneVobo      = !!(data && data.vobo_por);
  var tieneAbono     = !!(data && parseFloat(data.saldo_pagado || 0) > 0);
  var puedeImpSalida = !!(data && (
    (parseFloat(data.saldo_pagado || 0) >= parseFloat(data.total || 0) && parseFloat(data.total || 0) > 0) ||
    data.estatus_pago === 'pago_entrega'
  ));

  // Guardar data para el modal de correcciones
  _dataCot = data;
  // Cargar partidas en el array local
  partidas = (data && data.partidas) ? data.partidas.slice() : [];

  var html = '';

  // Alerta saldo pendiente
  if (bloqueada && estatus !== 'entregada') {
    html += '<div class="alerta-saldo show">&#128274; Esta orden tiene saldo pendiente — la entrega está bloqueada hasta que el dir_admin la autorice.</div>';
  }

  // ── Encabezado ──
  html += '<div class="card">';
  html += '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">';
  html += '<div>';
  if (!esNuevo) {
    var folioDisplay = (data.orden_folio && estatus !== 'cotizacion') ? data.orden_folio : data.folio;
    html += '<div class="folio-badge">' + escHtml(folioDisplay) + '</div>';
    if (data.orden_folio && estatus !== 'cotizacion') {
      html += '<div style="margin-top:4px;font-size:11px;color:#94a3b8">COT: ' + escHtml(data.folio) + '</div>';
    }
    html += '<div style="margin-top:8px"><span class="badge badge-' + estatus + '">' + etiquetaEstatus(estatus) + '</span></div>';
  } else {
    html += '<div style="font-size:15px;font-weight:700;color:#1e293b">Nueva Cotización</div>';
    html += '<div style="font-size:13px;color:#94a3b8;margin-top:4px">El folio se generará al guardar</div>';
  }
  html += '</div>';

  // Acciones
  if (!esNuevo) {
    html += '<div class="acciones">';
    if (estatus === 'cotizacion' && editable) {
      html += '<button class="btn btn-warning" onclick="ModCotizacion._convertirOrden()">&#127981; Convertir a Orden</button>';
    }
    if (estatus === 'cotizacion' && editable) {
      html += '<button class="btn btn-primary btn-sm" onclick="ModCotizacion._guardarCambios()">&#128190; Guardar Cambios</button>';
    }
    if (estatus === 'cotizacion') {
      html += '<button class="btn btn-ghost btn-sm" onclick="ModCotizacion._imprimirCotizacion()">&#128424;&#65039; Imprimir Cotizaci&#243;n</button>';
    }
    if (estatus === 'orden' && PUEDE_EDITAR) {
      html += '<button class="btn btn-ghost btn-sm" onclick="ModCotizacion._imprimirRemision()">&#128424;&#65039; Remisi&#243;n</button>';
    }
    if (estatus === 'orden' && PUEDE_EDITAR && tieneVobo && tieneAbono) {
      var folioEtiq = escJs(data.orden_folio || '');
      html += '<button class="btn btn-ghost btn-sm" onclick="ModCotizacion._imprimirEtiquetas(\'' + folioEtiq + '\')">&#127991; Imprimir Etiquetas</button>';
    }
    if (estatus === 'orden' && PUEDE_EDITAR && puedeImpSalida) {
      html += '<button class="btn btn-ghost btn-sm" onclick="ModCotizacion._imprimirSalida()">&#128666; Imprimir Salida</button>';
    }
    if (estatus === 'orden' && PUEDE_EDITAR) {
      html += '<button class="btn btn-ghost btn-sm" onclick="ModCotizacion._imprimirOrden()">&#128424;&#65039; Orden de Producci&#243;n</button>';
    }
    if (estatus === 'orden' && ES_ADMIN) {
      html += '<button class="btn btn-success btn-sm" onclick="ModCotizacion._marcarEntregada()">&#9989; Marcar Entregada</button>';
    }
    if (estatus === 'orden' && bloqueada && ES_ADMIN) {
      html += '<button class="btn btn-danger btn-sm" onclick="ModCotizacion._autorizarEntrega()">&#128275; Autorizar Entrega</button>';
    }
    if (estatus !== 'cancelada' && ES_ADMIN) {
      html += '<button class="btn btn-ghost btn-sm" onclick="ModCotizacion._cancelar()">&#10005; Cancelar</button>';
    }
    if (ES_DIR_ADMIN && estatus !== 'cancelada') {
      html += '<button class="btn btn-corregir btn-sm" onclick="ModCotizacion._abrirCorreccion()">&#9998; Corregir</button>';
    }
    html += '</div>';
  }
  html += '</div>'; // flex header

  // ── Campos ──
  html += '<div class="form-grid">';

  // Cliente
  html += '<div class="field span-2"><label>Cliente *</label>';
  if (editable) {
    html += '<div class="autocomplete-wrap">';
    html += '<input type="text" id="clienteBusqueda" placeholder="Buscar cliente..." oninput="ModCotizacion._buscarCliente()" autocomplete="off" value="' + (data ? escHtml(data.cliente_nombre) : '') + '">';
    html += '<div class="autocomplete-list" id="clienteLista" style="display:none"></div>';
    html += '<input type="hidden" id="clienteId" value="' + (data ? data.cliente_id : '') + '">';
    html += '</div>';
  } else {
    html += '<input type="text" readonly value="' + escHtml(data ? data.cliente_nombre : '') + '">';
  }
  html += '</div>';
  html += '<div class="banner-saldo-favor" id="bannerSaldoFavor">&#128176; <span id="bannerSaldoTexto"></span></div>';

  // Proyecto
  html += '<div class="field"><label>Proyecto / Referencia</label>';
  html += '<input type="text" id="fProyecto" placeholder="Ej: Casa Lomas" ' + (!editable ? 'readonly' : '') + ' value="' + escHtml(data ? data.proyecto : '') + '"></div>';

  // Descuento
  html += '<div class="field"><label>Descuento %</label>';
  html += '<input type="number" id="fDescuento" min="0" max="100" step="0.5" value="' + (data ? (data.descuento || '0') : '0') + '" ' + (!editable ? 'readonly' : '') + ' onchange="ModCotizacion._recalcular()"></div>';

  // Condición de pago
  html += '<div class="field"><label>Condición de pago</label>';
  if (editable) {
    html += '<select id="fCondicion"><option value="anticipo"' + (data && data.condicion_pago==='anticipo'?' selected':'') + '>50% Anticipo</option><option value="pago_total"' + (data && data.condicion_pago==='pago_total'?' selected':'') + '>Pago Total</option></select>';
  } else {
    html += '<input type="text" readonly value="' + (data && data.condicion_pago === 'pago_total' ? 'Pago Total' : '50% Anticipo') + '">';
  }
  html += '</div>';

  // Tipo entrega
  html += '<div class="field"><label>Tipo de entrega</label>';
  if (editable) {
    html += '<select id="fTipoEntrega"><option value="domicilio"' + (data && data.tipo_entrega==='domicilio'?' selected':'') + '>A domicilio</option><option value="planta"' + (data && data.tipo_entrega==='planta'?' selected':'') + '>Recoge en planta</option></select>';
  } else {
    html += '<input type="text" readonly value="' + (data && data.tipo_entrega === 'planta' ? 'Recoge en planta' : 'A domicilio') + '">';
  }
  html += '</div>';

  // Localidad
  html += '<div class="field"><label>Localidad</label>';
  if (editable) {
    html += '<select id="fLocalidad" onchange="ModCotizacion._toggleCiudad()"><option value="local"' + (data && data.localidad==='local'?' selected':'') + '>Local (MTY/SCT)</option><option value="foraneo"' + (data && data.localidad==='foraneo'?' selected':'') + '>Foráneo</option></select>';
  } else {
    html += '<input type="text" readonly value="' + (data && data.localidad === 'foraneo' ? 'Foráneo' : 'Local') + '">';
  }
  html += '</div>';

  // Ciudad destino
  var mostrarCiudad = data && data.localidad === 'foraneo';
  html += '<div class="field" id="campoCiudad" style="display:' + (mostrarCiudad ? 'flex' : 'none') + '"><label>Ciudad destino</label>';
  html += '<input type="text" id="fCiudad" ' + (!editable?'readonly':'') + ' value="' + escHtml(data ? (data.ciudad_destino || '') : '') + '" placeholder="Ej: Saltillo" onchange="ModCotizacion._actualizarFechaEntrega()"></div>';

  // Fecha entrega
  html += '<div class="field"><label>Fecha de entrega</label>';
  html += '<input type="date" id="fFechaEntrega" ' + (!editable?'readonly':'') + ' value="' + (data ? ((data.fecha_entrega||'').substring(0,10)) : '') + '">';
  html += '<div class="hint">Se calcula automáticamente (5 días hábiles)</div>';
  html += '</div>';

  // Crédito
  html += '<div class="field"><label>Crédito</label>';
  if (editable) {
    html += '<select id="fCredito"><option value="no"' + (data && data.credito==='no'?' selected':'') + '>No</option><option value="si"' + (data && data.credito==='si'?' selected':'') + '>Sí</option></select>';
  } else {
    html += '<input type="text" readonly value="' + (data && data.credito === 'si' ? 'Sí' : 'No') + '">';
  }
  html += '</div>';

  // Factura tipo
  var ftVal = data ? (data.factura_tipo || 'generica') : 'generica';
  var ftEsGenerica = (ftVal === 'generica');
  html += '<div class="field span-2"><label>Factura</label>';
  if (editable) {
    html += '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">';
    html += '<label style="display:flex;align-items:center;gap:6px;font-weight:normal;cursor:pointer;">';
    html += '<input type="checkbox" id="fFacturaGenerica" ' + (ftEsGenerica ? 'checked' : '') + ' onchange="ModCotizacion._toggleFactura()">';
    html += 'Genérica</label>';
    html += '<input type="text" id="fFacturaRfc" style="flex:1;min-width:160px;display:' + (ftEsGenerica ? 'none' : 'block') + ';"';
    html += ' value="' + escHtml(ftEsGenerica ? '' : ftVal) + '" placeholder="RFC / Razón social del cliente">';
    html += '</div>';
  } else {
    html += '<input type="text" readonly value="' + escHtml(ftVal === 'generica' ? 'Genérica' : ftVal) + '">';
  }
  html += '</div>';

  // Alerta
  html += '<div class="field span-3"><label>Alerta / Nota especial</label>';
  html += '<input type="text" id="fAlerta" ' + (!editable?'readonly':'') + ' value="' + escHtml(data ? (data.alerta||'') : '') + '" placeholder="Ej: Urgente, cliente espera..."></div>';

  html += '</div>'; // form-grid
  html += '</div>'; // card encabezado

  // Banner autorización descuento (se rellena async)
  if (!esNuevo) {
    html += '<div id="authBanner"></div>';
  }

  // ── Partidas ──
  html += '<div class="card">';
  html += '<div class="card-title">&#128230; Partidas';
  if (editable) {
    html += ' <button class="btn btn-ghost btn-sm" onclick="ModCotizacion._agregarPartida()">+ Agregar partida</button>';
  }
  html += '</div>';

  html += '<div class="partidas-header">';
  html += '<span>#</span><span>Cristal</span><span>Cant</span><span>Ancho mm</span><span>Alto mm</span><span>Detalles</span><span>CPB</span><span>Res</span><span>TP</span><span>TA</span><span>Templado</span><span>Comentarios</span><span></span>';
  html += '</div>';
  html += '<div id="partidas-container"></div>';

  // Totales
  html += '<div class="totales-box" id="totalesBox">';
  html += '<div class="totales-row"><span>Subtotal</span><span id="tSubtotal">$0.00</span></div>';
  html += '<div class="totales-row"><span>Descuento</span><span id="tDescuento" style="color:#dc2626">-$0.00</span></div>';
  html += '<div class="totales-row"><span>Base gravable</span><span id="tBase">$0.00</span></div>';
  html += '<div class="totales-row"><span>IVA 16%</span><span id="tIva">$0.00</span></div>';
  html += '<div class="totales-row total-final"><span>TOTAL</span><span id="tTotal">$0.00</span></div>';
  html += '</div>';

  if (esNuevo) {
    html += '<div style="margin-top:20px;display:flex;gap:10px">';
    html += '<button class="btn btn-primary" onclick="ModCotizacion._guardarCotizacion()">&#128190; Guardar cotización</button>';
    html += '<button class="btn btn-ghost" onclick="irA(\'cotizaciones\')">Cancelar</button>';
    html += '</div>';
  }

  html += '</div>'; // card partidas

  // Croquis técnicos — solo en cotizaciones existentes
  if (!esNuevo) {
    html += '<div id="cq-container"></div>';
  }

  document.getElementById('mainContent').innerHTML = html;
  renderPartidas(editable);
  recalcular();

  // Cargar banner de autorización si hay descuento > 10%
  if (!esNuevo && data && parseFloat(data.descuento || 0) > 10) {
    _cargarAuthStatus();
  }

  // Cargar módulo croquis técnicos
  if (!esNuevo) {
    fetch('modulos/croquis.php?id=' + ID_COT, { headers: { 'X-SPA-Request': '1' } })
      .then(function(r) { return r.text(); })
      .then(function(htmlCroquis) {
        var cont = document.getElementById('cq-container');
        if (!cont) return;
        var tmp = document.createElement('div');
        tmp.innerHTML = htmlCroquis;
        var scripts = Array.from(tmp.querySelectorAll('script'));
        scripts.forEach(function(s) { s.remove(); });
        cont.innerHTML = tmp.innerHTML;
        for (var i = 0; i < scripts.length; i++) {
          var ns = document.createElement('script');
          ns.textContent = scripts[i].textContent;
          document.head.appendChild(ns);
        }
      })
      .catch(function() {});
  }

  // Cerrar autocomplete al hacer click fuera
  document.addEventListener('click', function(e) {
    var lista = document.getElementById('clienteLista');
    if (lista && !lista.contains(e.target) && e.target.id !== 'clienteBusqueda') {
      lista.style.display = 'none';
    }
  });
}

// ── Render partidas ────────────────────────────────────────────────────────────
function renderPartidas(editable) {
  var cont = document.getElementById('partidas-container');
  if (!cont) return;

  if (!partidas.length && editable) {
    partidas.push({});
  }

  var html = '';
  for (var i = 0; i < partidas.length; i++) {
    var p = partidas[i];
    html += '<div class="partida-row" id="prow-' + i + '">';
    html += '<span class="num-partida">' + (i+1) + '</span>';

    // Cristal
    html += '<select id="p_cristal_' + i + '" onchange="ModCotizacion._recalcular()" ' + (!editable?'disabled':'') + '>';
    html += '<option value="">-- Cristal --</option>';
    for (var j = 0; j < cristales.length; j++) {
      var c = cristales[j];
      var sel = (p.cristal_id == c.id) ? ' selected' : '';
      html += '<option value="' + c.id + '"' + sel + '>' + escHtml(c.nombre) + '</option>';
    }
    html += '</select>';

    // Cantidad
    html += '<input type="number" id="p_cant_' + i + '" value="' + (p.cantidad||1) + '" min="1" ' + (!editable?'readonly':'') + ' onchange="ModCotizacion._recalcular()">';
    // Ancho
    html += '<input type="number" id="p_ancho_' + i + '" value="' + (p.ancho||'') + '" placeholder="mm" ' + (!editable?'readonly':'') + ' onchange="ModCotizacion._recalcular()">';
    // Alto
    html += '<input type="number" id="p_alto_' + i + '" value="' + (p.alto||'') + '" placeholder="mm" ' + (!editable?'readonly':'') + ' onchange="ModCotizacion._recalcular()">';
    // Detalles
    var detOpts = ['NO','Plantilla','Descuadre','Forma','Di\u00e1metro'];
    if (editable) {
      html += '<select id="p_det_' + i + '">';
      html += '<option value="">--</option>';
      for (var d = 0; d < detOpts.length; d++) {
        html += '<option value="' + detOpts[d] + '"' + (p.detalles===detOpts[d]?' selected':'') + '>' + detOpts[d] + '</option>';
      }
      html += '</select>';
    } else {
      html += '<input type="text" readonly value="' + escHtml(p.detalles||'') + '">';
    }
    // CPB
    var cpbOpts = ['No','Perimetral','Larguero','Largueros','Cabezal','Cabezales','1 Larguero - 1 cabezal','2 Largueros - 1 cabezal','1 Larguero - 2 cabezales'];
    if (editable) {
      html += '<select id="p_cpb_' + i + '">';
      html += '<option value="">--</option>';
      for (var c = 0; c < cpbOpts.length; c++) {
        html += '<option value="' + cpbOpts[c] + '"' + (p.cpb===cpbOpts[c]?' selected':'') + '>' + cpbOpts[c] + '</option>';
      }
      html += '</select>';
    } else {
      html += '<input type="text" readonly value="' + escHtml(p.cpb||'') + '">';
    }
    // Resaques
    html += '<input type="number" id="p_res_' + i + '" value="' + (p.resaques||0) + '" min="0" ' + (!editable?'readonly':'') + '>';
    // Taladros pasados
    html += '<input type="number" id="p_tp_' + i + '" value="' + (p.taladros_pasados||0) + '" min="0" ' + (!editable?'readonly':'') + '>';
    // Taladros avellanados
    html += '<input type="number" id="p_ta_' + i + '" value="' + (p.taladros_avellanados||0) + '" min="0" ' + (!editable?'readonly':'') + '>';
    // Templado
    var templVal = (p.requiere_templado === 0 || p.requiere_templado === '0') ? 0 : 1;
    if (editable) {
      html += '<select id="p_templ_' + i + '" style="font-size:12px;padding:6px 4px;border:1.5px solid #e2e8f0;border-radius:6px;width:100%">'
        + '<option value="1"' + (templVal===1?' selected':'') + '>S&#237;</option>'
        + '<option value="0"' + (templVal===0?' selected':'') + '>No</option>'
        + '</select>';
    } else {
      html += '<span style="font-size:12px;font-weight:700;color:' + (templVal===1?'#16a34a':'#dc2626') + '">'
        + (templVal===1?'S&#237;':'No') + '</span>';
    }
    // Comentarios etiqueta
    html += '<input type="text" id="p_com_' + i + '" value="' + escHtml(p.comentarios_etiqueta||'') + '" placeholder="Etiqueta..." ' + (!editable?'readonly':'') + '>';

    // Botón eliminar
    if (editable) {
      html += '<button class="btn-del" onclick="ModCotizacion._eliminarPartida(' + i + ')">&#215;</button>';
    } else {
      html += '<span></span>';
    }
    html += '</div>';
  }
  cont.innerHTML = html;
}

// ── Agregar / eliminar partida ─────────────────────────────────────────────────
function leerPartidasDelDOM() {
  for (var i = 0; i < partidas.length; i++) {
    var cristalEl = document.getElementById('p_cristal_' + i);
    var cantEl    = document.getElementById('p_cant_'    + i);
    var anchoEl   = document.getElementById('p_ancho_'   + i);
    var altoEl    = document.getElementById('p_alto_'    + i);
    var detEl     = document.getElementById('p_det_'     + i);
    var cpbEl     = document.getElementById('p_cpb_'     + i);
    var resEl     = document.getElementById('p_res_'     + i);
    var tpEl      = document.getElementById('p_tp_'      + i);
    var taEl      = document.getElementById('p_ta_'      + i);
    var templEl   = document.getElementById('p_templ_'   + i);
    var comEl     = document.getElementById('p_com_'     + i);
    if (cristalEl) partidas[i].cristal_id            = parseInt(cristalEl.value) || 0;
    if (cantEl)    partidas[i].cantidad               = parseInt(cantEl.value)    || 1;
    if (anchoEl)   partidas[i].ancho                  = parseInt(anchoEl.value)   || 0;
    if (altoEl)    partidas[i].alto                   = parseInt(altoEl.value)    || 0;
    if (detEl)     partidas[i].detalles               = detEl.value;
    if (cpbEl)     partidas[i].cpb                    = cpbEl.value;
    if (resEl)     partidas[i].resaques               = parseInt(resEl.value)     || 0;
    if (tpEl)      partidas[i].taladros_pasados        = parseInt(tpEl.value)      || 0;
    if (taEl)      partidas[i].taladros_avellanados    = parseInt(taEl.value)      || 0;
    if (templEl)   partidas[i].requiere_templado       = parseInt(templEl.value);
    if (comEl)     partidas[i].comentarios_etiqueta    = comEl.value;
  }
}

function agregarPartida() {
  leerPartidasDelDOM();
  partidas.push({});
  renderPartidas(true);
  recalcular();
}

function eliminarPartida(idx) {
  leerPartidasDelDOM();
  partidas.splice(idx, 1);
  renderPartidas(true);
  recalcular();
}

// ── Recalcular totales ────────────────────────────────────────────────────────
function recalcular() {
  var subtotal = 0;
  for (var i = 0; i < partidas.length; i++) {
    var cristalId = parseInt(document.getElementById('p_cristal_' + i)?.value || 0);
    var cantidad  = parseInt(document.getElementById('p_cant_'   + i)?.value || 0);
    var ancho     = parseInt(document.getElementById('p_ancho_'  + i)?.value || 0);
    var alto      = parseInt(document.getElementById('p_alto_'   + i)?.value || 0);
    if (!cristalId || !cantidad || !ancho || !alto) continue;
    var cris = null;
    for (var j = 0; j < cristales.length; j++) {
      if (cristales[j].id == cristalId) { cris = cristales[j]; break; }
    }
    if (!cris) continue;
    var m2      = (ancho / 1000) * (alto / 1000);
    var precio  = parseFloat(cris.precio_m2 || cris.precio_m2_usado || 0);
    subtotal   += cantidad * m2 * precio;
  }
  var pctDesc  = parseFloat(document.getElementById('fDescuento')?.value || 0);
  var descuento = subtotal * pctDesc / 100;
  var base     = subtotal - descuento;
  var iva      = base * 0.16;
  var total    = base + iva;

  function fmt(n) { return '$' + n.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}); }
  if (document.getElementById('tSubtotal'))  document.getElementById('tSubtotal').textContent  = fmt(subtotal);
  if (document.getElementById('tDescuento')) document.getElementById('tDescuento').textContent = '-' + fmt(descuento);
  if (document.getElementById('tBase'))      document.getElementById('tBase').textContent      = fmt(base);
  if (document.getElementById('tIva'))       document.getElementById('tIva').textContent       = fmt(iva);
  if (document.getElementById('tTotal'))     document.getElementById('tTotal').textContent     = fmt(total);
}

// ── Autocomplete cliente ──────────────────────────────────────────────────────
function buscarCliente() {
  if (_buscarTimer) clearTimeout(_buscarTimer);
  var q = (document.getElementById('clienteBusqueda')?.value || '').trim();
  var lista = document.getElementById('clienteLista');
  if (!lista) return;
  if (q.length < 2) { lista.style.display = 'none'; return; }

  _buscarTimer = setTimeout(async function() {
    var res   = await fetch(API_CLI + '?q=' + encodeURIComponent(q));
    var items = await res.json();
    if (!items.length) { lista.style.display = 'none'; return; }
    lista.innerHTML = items.map(function(c) {
      return '<div class="autocomplete-item" onclick="ModCotizacion._seleccionarCliente(' + c.id + ',\'' + escJs(c.razon_social||c.nombre) + '\',\'' + escJs(c.localidad||'local') + '\',\'' + escJs(c.ciudad||c.ciudad_destino||'') + '\')">'
        + escHtml(c.razon_social||c.nombre)
        + '<div class="codigo">' + escHtml(c.codigo||'') + '</div>'
        + '</div>';
    }).join('');
    lista.style.display = 'block';
  }, 250);
}

function seleccionarCliente(id, nombre, localidad, ciudad) {
  document.getElementById('clienteId').value              = id;
  document.getElementById('clienteBusqueda').value        = nombre;
  var lista = document.getElementById('clienteLista');
  if (lista) lista.style.display = 'none';
  clienteSeleccionado = { id: id, nombre: nombre, localidad: localidad, ciudad: ciudad };

  // Consultar saldo a favor del cliente
  fetch('../api/saldo_favor.php?accion=saldo&cliente_id=' + id)
    .then(function(r) { return r.json(); })
    .then(function(d) {
      var banner  = document.getElementById('bannerSaldoFavor');
      var texto   = document.getElementById('bannerSaldoTexto');
      if (!banner || !texto) return;
      var saldo = parseFloat(d.saldo || 0);
      if (saldo > 0) {
        var fmt = '$' + saldo.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
        texto.textContent = 'Este cliente tiene ' + fmt + ' de Saldo a Favor disponible';
        banner.classList.add('show');
      } else {
        banner.classList.remove('show');
      }
    })
    .catch(function() {
      var banner = document.getElementById('bannerSaldoFavor');
      if (banner) banner.classList.remove('show');
    });
  if (document.getElementById('fLocalidad')) {
    document.getElementById('fLocalidad').value = localidad;
    toggleCiudad();
  }
  if (ciudad && document.getElementById('fCiudad')) {
    document.getElementById('fCiudad').value = ciudad;
  }
  actualizarFechaEntrega();
}

// ── Localidad / Ciudad ────────────────────────────────────────────────────────
function toggleFactura() {
  var cb  = document.getElementById('fFacturaGenerica');
  var rfc = document.getElementById('fFacturaRfc');
  if (!cb || !rfc) return;
  rfc.style.display = cb.checked ? 'none' : 'block';
  if (cb.checked) rfc.value = '';
}

function toggleCiudad() {
  var esForaneo = (document.getElementById('fLocalidad')?.value === 'foraneo');
  var campo = document.getElementById('campoCiudad');
  if (campo) campo.style.display = esForaneo ? 'flex' : 'none';
  actualizarFechaEntrega();
}

async function actualizarFechaEntrega() {
  var localidad = document.getElementById('fLocalidad')?.value || 'local';
  var ciudad    = document.getElementById('fCiudad')?.value    || '';
  try {
    var res  = await fetch(API_COT + '?calcular_fecha=1&localidad=' + localidad + '&ciudad=' + encodeURIComponent(ciudad));
    var data = await res.json();
    if (data.fecha_entrega && document.getElementById('fFechaEntrega')) {
      document.getElementById('fFechaEntrega').value = data.fecha_entrega;
    }
  } catch(e) {}
}

// ── Guardar nueva cotización ──────────────────────────────────────────────────
async function guardarCotizacion() {
  var clienteId = document.getElementById('clienteId')?.value;
  if (!clienteId) { alert('Selecciona un cliente'); return; }

  var payload = armarPayload(clienteId);
  if (!payload) return;

  if (payload.descuento > 10 && !ES_DIR_ADMIN) {
    var motivo = await pedirMotivoDescuento(payload.descuento);
    if (motivo === null) return;
    payload.motivo_descuento = motivo;
  }

  try {
    var res  = await fetch(API_COT, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    var data = await res.json();
    if (data.ok) {
      irA('cotizacion', {id: data.id});
    } else {
      alert(data.error || 'Error al guardar');
    }
  } catch(e) { alert('Error de conexión'); }
}

// ── Guardar cambios en cotización existente ───────────────────────────────────
async function guardarCambios() {
  var clienteId = document.getElementById('clienteId')?.value;
  if (!clienteId) { alert('Selecciona un cliente'); return; }

  var payload = armarPayload(clienteId);
  if (!payload) return;

  if (payload.descuento > 10 && !ES_DIR_ADMIN) {
    // Si ya tiene autorización aprobada con el mismo descuento, no volver a pedir motivo
    var yaAprobado = _authStatus && _authStatus.estatus === 'aprobado' &&
                     parseFloat(_authStatus.descuento) === payload.descuento;
    if (!yaAprobado) {
      var motivo = await pedirMotivoDescuento(payload.descuento);
      if (motivo === null) return;
      payload.motivo_descuento = motivo;
    }
  }

  payload.accion = 'actualizar';
  payload.id     = ID_COT;

  try {
    var res  = await fetch(API_COT, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    var data = await res.json();
    if (data.ok) {
      alert('Cotización actualizada');
      irA('cotizacion', {id: ID_COT});
    } else {
      alert(data.error || 'Error al guardar');
    }
  } catch(e) { alert('Error de conexión'); }
}

function armarPayload(clienteId) {
  var partidasPayload = [];
  for (var i = 0; i < partidas.length; i++) {
    var cristalId = document.getElementById('p_cristal_' + i)?.value;
    var cantidad  = parseInt(document.getElementById('p_cant_'   + i)?.value || 1);
    var ancho     = parseInt(document.getElementById('p_ancho_'  + i)?.value || 0);
    var alto      = parseInt(document.getElementById('p_alto_'   + i)?.value || 0);
    if (!cristalId || !ancho || !alto) continue;
    partidasPayload.push({
      cristal_id:           parseInt(cristalId),
      cantidad:             cantidad,
      ancho:                ancho,
      alto:                 alto,
      detalles:             document.getElementById('p_det_' + i)?.value || '',
      cpb:                  document.getElementById('p_cpb_' + i)?.value || '',
      resaques:             parseInt(document.getElementById('p_res_' + i)?.value || 0),
      taladros_pasados:     parseInt(document.getElementById('p_tp_'  + i)?.value || 0),
      taladros_avellanados: parseInt(document.getElementById('p_ta_'  + i)?.value || 0),
      comentarios_etiqueta: document.getElementById('p_com_' + i)?.value || '',
      requiere_templado:    parseInt(document.getElementById('p_templ_' + i)?.value ?? 1),
    });
  }
  if (!partidasPayload.length) { alert('Agrega al menos una partida válida (cristal + medidas)'); return null; }

  return {
    cliente_id:     parseInt(clienteId),
    proyecto:       document.getElementById('fProyecto')?.value     || '',
    descuento:      parseFloat(document.getElementById('fDescuento')?.value || 0),
    credito:        document.getElementById('fCredito')?.value      || 'no',
    condicion_pago: document.getElementById('fCondicion')?.value    || 'anticipo',
    tipo_entrega:   document.getElementById('fTipoEntrega')?.value  || 'domicilio',
    localidad:      document.getElementById('fLocalidad')?.value    || 'local',
    ciudad_destino: document.getElementById('fCiudad')?.value       || '',
    fecha_entrega:  document.getElementById('fFechaEntrega')?.value || '',
    factura_tipo:   (function() { var cb = document.getElementById('fFacturaGenerica'); if (!cb || cb.checked) return 'generica'; return document.getElementById('fFacturaRfc')?.value.trim() || 'generica'; })(),
    alerta:         document.getElementById('fAlerta')?.value       || '',
    partidas:       partidasPayload,
  };
}

// ── Acciones de estatus ───────────────────────────────────────────────────────
async function convertirOrden() {
  // Verificar autorización de descuento antes de intentar convertir
  var desc = _dataCot ? parseFloat(_dataCot.descuento || 0) : 0;
  if (desc > 10 && !ES_DIR_ADMIN) {
    if (!_authStatus || _authStatus.estatus !== 'aprobado') {
      alert('El descuento del ' + desc + '% requiere autorización de Dirección antes de convertir a orden.');
      return;
    }
  }
  if (!confirm('¿Convertir esta cotización a Orden de Producción? No podrá editarse después.')) return;
  var res  = await fetch(API_COT, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({accion:'convertir_orden', id: ID_COT}) });
  var data = await res.json();
  if (data.ok) { irA('cotizacion', {id: ID_COT}); } else { alert(data.error); }
}

async function marcarEntregada() {
  if (!confirm('¿Marcar esta orden como entregada?')) return;
  var res  = await fetch(API_COT, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify({accion:'marcar_entregada', id: ID_COT}) });
  var data = await res.json();
  if (data.ok) { irA('cotizacion', {id: ID_COT}); } else { alert(data.error || 'Entrega bloqueada por saldo pendiente'); }
}

async function autorizarEntrega() {
  var nota = prompt('Motivo de autorización (opcional):') || '';
  var res  = await fetch(API_COT, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({accion:'autorizar_entrega', id: ID_COT, nota: nota}) });
  var data = await res.json();
  if (data.ok) { irA('cotizacion', {id: ID_COT}); } else { alert(data.error); }
}

async function cancelar() {
  if (!confirm('¿Cancelar esta cotización/orden? Esta acción no se puede deshacer.')) return;
  var res  = await fetch(API_COT, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify({accion:'cancelar', id: ID_COT}) });
  var data = await res.json();
  if (data.ok) { irA('cotizaciones'); } else { alert(data.error); }
}

function imprimirOrden() {
  window.open('../app/imprimir_orden.php?id=' + ID_COT, '_blank');
}

function imprimirCotizacion() {
  window.open('../app/imprimir_cotizacion.php?id=' + ID_COT, '_blank');
}

function imprimirRemision() {
  window.open('../app/imprimir_cotizacion.php?id=' + ID_COT + '&remision=1', '_blank');
}

function imprimirEtiquetas(folio) {
  window.open('../app/imprimir_etiquetas.php?folio=' + encodeURIComponent(folio), '_blank');
}

function imprimirSalida() {
  window.open('../app/imprimir_salida.php?id=' + ID_COT, '_blank');
}

// ── Modal Correcciones ────────────────────────────────────────────────────────
function abrirCorreccion() {
  if (!_dataCot) return;
  _inyectarModal();
  _renderFormCorreccion();
  document.getElementById('corrModal').classList.add('open');
}

function cerrarCorreccion() {
  var m = document.getElementById('corrModal');
  if (m) m.classList.remove('open');
}

function corrTab(tab) {
  document.getElementById('corrTabForm').style.display = tab === 'form' ? '' : 'none';
  document.getElementById('corrTabHist').style.display = tab === 'hist' ? '' : 'none';
  document.querySelectorAll('.corr-tab').forEach(function(b) { b.classList.remove('active'); });
  document.getElementById('corrTabBtn' + tab).classList.add('active');
  if (tab === 'hist') _cargarHistorial();
}

function _inyectarModal() {
  if (document.getElementById('corrModal')) return;
  var d = document.createElement('div');
  d.id = 'corrModal';
  d.className = 'corr-bg';
  var folio = _dataCot.orden_folio || _dataCot.folio;
  d.innerHTML =
    '<div class="corr-modal">' +
      '<div class="corr-head">' +
        '<h2>&#9998; Correción — ' + escHtml(folio) + '</h2>' +
        '<button class="corr-close" onclick="ModCotizacion._cerrarCorreccion()">&#10005;</button>' +
      '</div>' +
      '<div class="corr-tabs">' +
        '<button class="corr-tab active" id="corrTabBtnform" onclick="ModCotizacion._corrTab(\'form\')">Aplicar Corrección</button>' +
        '<button class="corr-tab" id="corrTabBtnhist" onclick="ModCotizacion._corrTab(\'hist\')">Historial de Cambios</button>' +
      '</div>' +
      '<div class="corr-body">' +
        '<div id="corrTabForm"></div>' +
        '<div id="corrTabHist" style="display:none"></div>' +
      '</div>' +
      '<div class="corr-foot">' +
        '<button class="btn btn-ghost" onclick="ModCotizacion._cerrarCorreccion()">Cancelar</button>' +
        '<button class="btn btn-primary" id="corrBtnGuardar" onclick="ModCotizacion._guardarCorreccion()">&#10003; Aplicar Corrección</button>' +
      '</div>' +
    '</div>';
  document.body.appendChild(d);
}

function _renderFormCorreccion() {
  var d   = _dataCot;
  var esOrden = (d.estatus === 'orden');
  var html = '';

  // Motivo
  html += '<div class="corr-motivo-wrap">';
  html += '<label>Motivo de la corrección *</label>';
  html += '<textarea id="corrMotivo" placeholder="Describe el motivo del ajuste…"></textarea>';
  html += '</div>';

  // Encabezado
  html += '<div class="corr-section">';
  html += '<div class="corr-section-title">Encabezado</div>';
  html += '<div class="corr-hdr-grid">';

  html += '<div class="corr-field"><label>Descuento %</label>';
  html += '<input type="number" id="ch_descuento" min="0" max="100" step="0.5" value="' + (d.descuento || 0) + '"></div>';

  html += '<div class="corr-field"><label>Condición de pago</label>';
  html += '<select id="ch_condicion_pago">';
  html += '<option value="anticipo"'   + (d.condicion_pago === 'anticipo'   ? ' selected' : '') + '>50% Anticipo</option>';
  html += '<option value="pago_total"' + (d.condicion_pago === 'pago_total' ? ' selected' : '') + '>Pago Total</option>';
  html += '</select></div>';

  html += '<div class="corr-field"><label>Fecha de entrega</label>';
  html += '<input type="date" id="ch_fecha_entrega" value="' + ((d.fecha_entrega || '').substring(0, 10)) + '"></div>';

  html += '<div class="corr-field"><label>Alerta / Nota</label>';
  html += '<input type="text" id="ch_alerta" value="' + escHtml(d.alerta || '') + '"></div>';

  html += '</div></div>';

  // Partidas
  html += '<div class="corr-section">';
  html += '<div class="corr-section-title">Partidas</div>';
  html += '<div style="overflow-x:auto">';
  html += '<table class="corr-partidas-table">';
  html += '<thead><tr>';
  html += '<th>#</th><th>Cristal / Medidas</th>';
  html += '<th>Precio/m²</th><th>Precio Unit.</th>';
  html += '<th>Cant.</th>';
  html += '<th>Detalles</th><th>CPB</th>';
  html += '<th>Res</th><th>TP</th><th>TA</th><th>Templ.</th>';
  html += '<th>Comentarios</th>';
  html += '</tr></thead><tbody>';

  var detOpts = ['','NO','Plantilla','Descuadre','Forma','Diámetro'];
  var cpbOpts = ['No','Perimetral','Larguero','Largueros','Cabezal','Cabezales',
                 '1 Larguero - 1 cabezal','2 Largueros - 1 cabezal','1 Larguero - 2 cabezales'];

  var pts = d.partidas || [];
  for (var i = 0; i < pts.length; i++) {
    var p   = pts[i];
    var idx = p.id;
    html += '<tr>';
    html += '<td class="corr-num">' + p.num_partida + '</td>';
    html += '<td><div class="corr-ref">' + escHtml(p.cristal_nombre || '') + '</div>';
    html += '<div style="font-size:11px;color:#94a3b8">' + (p.ancho || 0) + '×' + (p.alto || 0) + ' mm</div></td>';

    html += '<td><input type="number" id="cp_pm2_'   + idx + '" value="' + (p.precio_m2_usado    || 0) + '" step="0.01" min="0" style="width:90px"></td>';
    html += '<td><input type="number" id="cp_pu_'    + idx + '" value="' + (p.precio_unitario    || 0) + '" step="0.01" min="0" style="width:90px"></td>';
    html += '<td><input type="number" id="cp_cant_'  + idx + '" value="' + (p.cantidad           || 1) + '" min="1"' + (esOrden ? ' disabled title="No se puede cambiar cantidad en orden activa"' : '') + ' style="width:55px"></td>';

    // Detalles select
    html += '<td><select id="cp_det_' + idx + '" style="width:100px">';
    for (var d2 = 0; d2 < detOpts.length; d2++) {
      html += '<option value="' + detOpts[d2] + '"' + (p.detalles === detOpts[d2] ? ' selected' : '') + '>' + detOpts[d2] + '</option>';
    }
    html += '</select></td>';

    // CPB select
    html += '<td><select id="cp_cpb_' + idx + '" style="width:90px">';
    for (var c2 = 0; c2 < cpbOpts.length; c2++) {
      html += '<option value="' + cpbOpts[c2] + '"' + (p.cpb === cpbOpts[c2] ? ' selected' : '') + '>' + cpbOpts[c2] + '</option>';
    }
    html += '</select></td>';

    html += '<td><input type="number" id="cp_res_' + idx + '" value="' + (p.resaques             || 0) + '" min="0" style="width:48px"></td>';
    html += '<td><input type="number" id="cp_tp_'  + idx + '" value="' + (p.taladros_pasados     || 0) + '" min="0" style="width:48px"></td>';
    html += '<td><input type="number" id="cp_ta_'  + idx + '" value="' + (p.taladros_avellanados || 0) + '" min="0" style="width:48px"></td>';

    html += '<td><select id="cp_templ_' + idx + '" style="width:58px">';
    var tv = (p.requiere_templado === 0 || p.requiere_templado === '0') ? 0 : 1;
    html += '<option value="1"' + (tv === 1 ? ' selected' : '') + '>Sí</option>';
    html += '<option value="0"' + (tv === 0 ? ' selected' : '') + '>No</option>';
    html += '</select></td>';

    html += '<td><input type="text" id="cp_com_' + idx + '" value="' + escHtml(p.comentarios_etiqueta || '') + '" style="min-width:120px"></td>';
    html += '</tr>';
  }

  html += '</tbody></table></div></div>';
  document.getElementById('corrTabForm').innerHTML = html;
}

async function guardarCorreccion() {
  var motivo = (document.getElementById('corrMotivo')?.value || '').trim();
  if (!motivo) {
    alert('El motivo de la corrección es obligatorio.');
    document.getElementById('corrMotivo').focus();
    return;
  }

  // Encabezado
  var cambios_header = {
    descuento:       parseFloat(document.getElementById('ch_descuento')?.value   ?? _dataCot.descuento),
    condicion_pago:  document.getElementById('ch_condicion_pago')?.value || _dataCot.condicion_pago,
    fecha_entrega:   document.getElementById('ch_fecha_entrega')?.value  || _dataCot.fecha_entrega,
    alerta:          document.getElementById('ch_alerta')?.value         ?? (_dataCot.alerta || ''),
  };

  // Partidas
  var cambios_partidas = [];
  var pts = _dataCot.partidas || [];
  for (var i = 0; i < pts.length; i++) {
    var p   = pts[i];
    var idx = p.id;
    var pc  = { partida_id: idx };
    var pmEl  = document.getElementById('cp_pm2_'   + idx);
    var puEl  = document.getElementById('cp_pu_'    + idx);
    var cEl   = document.getElementById('cp_cant_'  + idx);
    var detEl = document.getElementById('cp_det_'   + idx);
    var cpbEl = document.getElementById('cp_cpb_'   + idx);
    var resEl = document.getElementById('cp_res_'   + idx);
    var tpEl  = document.getElementById('cp_tp_'    + idx);
    var taEl  = document.getElementById('cp_ta_'    + idx);
    var tmEl  = document.getElementById('cp_templ_' + idx);
    var coEl  = document.getElementById('cp_com_'   + idx);
    if (pmEl)  pc.precio_m2_usado          = parseFloat(pmEl.value);
    if (puEl)  pc.precio_unitario          = parseFloat(puEl.value);
    if (cEl && !cEl.disabled) pc.cantidad  = parseInt(cEl.value);
    if (detEl) pc.detalles                 = detEl.value;
    if (cpbEl) pc.cpb                      = cpbEl.value;
    if (resEl) pc.resaques                 = parseInt(resEl.value);
    if (tpEl)  pc.taladros_pasados         = parseInt(tpEl.value);
    if (taEl)  pc.taladros_avellanados     = parseInt(taEl.value);
    if (tmEl)  pc.requiere_templado        = parseInt(tmEl.value);
    if (coEl)  pc.comentarios_etiqueta     = coEl.value;
    cambios_partidas.push(pc);
  }

  var btn = document.getElementById('corrBtnGuardar');
  if (btn) { btn.disabled = true; btn.textContent = 'Aplicando…'; }

  try {
    var res  = await fetch('../api/correcciones.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        cotizacion_id:    ID_COT,
        motivo:           motivo,
        cambios_header:   cambios_header,
        cambios_partidas: cambios_partidas,
      }),
    });
    var data = await res.json();
    if (data.ok) {
      cerrarCorreccion();
      if (data.cambios > 0) {
        alert('Corrección aplicada. ' + data.cambios + ' campo(s) modificado(s).');
      } else {
        alert('No se detectaron cambios respecto al valor actual.');
      }
      irA('cotizacion', { id: ID_COT });
    } else {
      alert(data.error || 'Error al aplicar corrección');
    }
  } catch(e) {
    alert('Error de conexión');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = '✓ Aplicar Corrección'; }
  }
}

async function _cargarHistorial() {
  var cont = document.getElementById('corrTabHist');
  if (!cont) return;
  cont.innerHTML = '<div class="hist-empty">Cargando…</div>';
  try {
    var res  = await fetch('../api/correcciones.php?cotizacion_id=' + ID_COT);
    var data = await res.json();
    if (!data.length) {
      cont.innerHTML = '<div class="hist-empty">Sin correcciones registradas.</div>';
      return;
    }
    var html = '<table class="hist-table"><thead><tr>';
    html += '<th>Fecha</th><th>Campo</th><th>Anterior</th><th>Nuevo</th><th>Motivo</th><th>Usuario</th>';
    html += '</tr></thead><tbody>';
    for (var i = 0; i < data.length; i++) {
      var h = data[i];
      html += '<tr>';
      html += '<td class="hist-fecha">' + escHtml(h.fecha || '') + '</td>';
      html += '<td><span class="hist-campo">' + escHtml(h.campo || '') + '</span></td>';
      html += '<td><span class="hist-ant">'   + escHtml(h.valor_anterior || '') + '</span></td>';
      html += '<td><span class="hist-nvo">'   + escHtml(h.valor_nuevo    || '') + '</span></td>';
      html += '<td><span class="hist-motivo">' + escHtml(h.motivo || '') + '</span></td>';
      html += '<td style="font-size:12px;color:#64748b">' + escHtml(h.usuario || '') + '</td>';
      html += '</tr>';
    }
    html += '</tbody></table>';
    cont.innerHTML = html;
  } catch(e) {
    cont.innerHTML = '<div class="hist-empty">Error al cargar historial.</div>';
  }
}

// ── Autorización de descuento ─────────────────────────────────────────────────

async function _cargarAuthStatus() {
  if (!ID_COT) return;
  try {
    var res = await fetch('../api/autorizaciones.php?cotizacion_id=' + ID_COT);
    var data = await res.json();
    _authStatus = (data && !data.error) ? data : null;
  } catch(e) { _authStatus = null; }
  _renderAuthBanner();
}

function _renderAuthBanner() {
  var el = document.getElementById('authBanner');
  if (!el) return;
  var desc = _dataCot ? parseFloat(_dataCot.descuento || 0) : 0;
  if (desc <= 10) { el.innerHTML = ''; return; }

  var d = _authStatus;
  if (!d) {
    // Descuento > 10% sin solicitud registrada
    el.innerHTML = '<div class="auth-banner auth-pendiente">&#9888;&#65039; <strong>Descuento del ' + desc + '% requiere autorización de Dirección.</strong><br><small>Guarda la cotización para registrar la solicitud, o ingresa el motivo al guardar.</small></div>';
    return;
  }

  if (d.estatus === 'pendiente') {
    var html = '<div class="auth-banner auth-pendiente">';
    html += '&#9203; <strong>Descuento ' + d.descuento + '% pendiente de autorización</strong>';
    if (d.motivo) html += '<br><span>Motivo: ' + escHtml(d.motivo) + '</span>';
    html += '<br><small>Solicitado por ' + escHtml(d.solicitado_por) + '</small>';
    if (ES_DIR_ADMIN) {
      html += '<div class="auth-acciones">';
      html += '<textarea id="authNota" placeholder="Nota (requerida al rechazar)..." rows="2"></textarea>';
      html += '<div style="display:flex;gap:8px"><button class="btn btn-success btn-sm" onclick="ModCotizacion._resolverAuth(' + d.id + ',\'aprobado\')">&#10003; Aprobar</button>';
      html += '<button class="btn btn-danger btn-sm" onclick="ModCotizacion._resolverAuth(' + d.id + ',\'rechazado\')">&#10005; Rechazar</button></div>';
      html += '</div>';
    }
    html += '</div>';
    el.innerHTML = html;

  } else if (d.estatus === 'aprobado') {
    el.innerHTML = '<div class="auth-banner auth-aprobado">&#10003; Descuento ' + d.descuento + '% <strong>autorizado</strong> por ' + escHtml(d.autorizado_por) + '</div>';

  } else if (d.estatus === 'rechazado') {
    var html = '<div class="auth-banner auth-rechazado">';
    html += '&#10005; <strong>Descuento ' + d.descuento + '% rechazado</strong>';
    if (d.nota_resolucion) html += '<br><span>Nota: ' + escHtml(d.nota_resolucion) + '</span>';
    html += '<br><small>Esta cotización no puede convertirse a orden mientras el descuento no sea autorizado.</small>';
    html += '</div>';
    el.innerHTML = html;
  }
}

async function _resolverAuth(authId, estatus) {
  var nota = (document.getElementById('authNota') || {}).value || '';
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
      await _cargarAuthStatus();
    } else {
      alert(data.error || 'Error al resolver');
    }
  } catch(e) { alert('Error de conexión'); }
}

function pedirMotivoDescuento(pct) {
  return new Promise(function(resolve) {
    var el = document.createElement('div');
    el.className = 'motivo-overlay';
    el.innerHTML =
      '<div class="motivo-box">' +
        '<h3>&#128274; Autorización requerida</h3>' +
        '<p>El descuento de <strong>' + pct + '%</strong> supera el 10% y requiere autorización de Dirección.</p>' +
        '<p>Indica el motivo para que dir_admin pueda revisar la solicitud:</p>' +
        '<textarea id="motivoDescTxt" rows="3" placeholder="Ej: Cliente frecuente, proyecto grande, acuerdo previo..."></textarea>' +
        '<div style="margin-top:14px;display:flex;justify-content:flex-end;gap:10px">' +
          '<button class="btn btn-ghost btn-sm" id="motivoCancelBtn">Cancelar</button>' +
          '<button class="btn btn-primary btn-sm" id="motivoOkBtn">Enviar solicitud</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(el);
    document.getElementById('motivoCancelBtn').onclick = function() {
      document.body.removeChild(el); resolve(null);
    };
    document.getElementById('motivoOkBtn').onclick = function() {
      var motivo = (document.getElementById('motivoDescTxt').value || '').trim();
      if (!motivo) { alert('Escribe el motivo del descuento especial'); return; }
      document.body.removeChild(el); resolve(motivo);
    };
  });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function etiquetaEstatus(s) {
  var map = { cotizacion:'Cotización', orden:'Orden de Producción', entregada:'Entregada', cancelada:'Cancelada' };
  return map[s] || s;
}
function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escJs(s)   { return String(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

init();

return {
  init: init,
  _guardarCotizacion: guardarCotizacion,
  _guardarCambios:    guardarCambios,
  _agregarPartida:    agregarPartida,
  _eliminarPartida:   eliminarPartida,
  _recalcular:        recalcular,
  _buscarCliente:     buscarCliente,
  _seleccionarCliente:seleccionarCliente,
  _toggleFactura:     toggleFactura,
  _toggleCiudad:      toggleCiudad,
  _actualizarFechaEntrega: actualizarFechaEntrega,
  _convertirOrden:    convertirOrden,
  _marcarEntregada:   marcarEntregada,
  _autorizarEntrega:  autorizarEntrega,
  _cancelar:          cancelar,
  _imprimirOrden:     imprimirOrden,
  _imprimirCotizacion: imprimirCotizacion,
  _imprimirRemision:  imprimirRemision,
  _imprimirEtiquetas:  imprimirEtiquetas,
  _imprimirSalida:     imprimirSalida,
  _abrirCorreccion:    abrirCorreccion,
  _cerrarCorreccion:   cerrarCorreccion,
  _corrTab:            corrTab,
  _guardarCorreccion:  guardarCorreccion,
  _resolverAuth:       _resolverAuth,
};
})();
</script>