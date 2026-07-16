<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requireSessionApi();
if (!in_array($user['rol'], ['administracion','dir_admin','dueno','desarrollo','comercial'])) {
    header('Location: ../dashboard.php'); exit;
}
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=logistica_rutas'); exit;
}
header('Content-Type: text/html; charset=utf-8');
$maps_key = defined('GOOGLE_MAPS_KEY') ? GOOGLE_MAPS_KEY : '';
?>
<style>
.lr-wrap { padding: 20px 24px; font-family: -apple-system, sans-serif; }
.lr-header { display:flex; align-items:center; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.lr-title { font-size:18px; font-weight:700; color:#0f172a; }
.lr-date-nav { display:flex; align-items:center; gap:6px; }
.lr-date-nav button { background:#f1f5f9; border:1px solid #e2e8f0; border-radius:6px; padding:5px 10px; cursor:pointer; font-size:13px; }
.lr-date-nav button:hover { background:#e2e8f0; }
#lr-fecha-lbl { font-size:14px; font-weight:600; color:#2563eb; min-width:130px; text-align:center; }
#lr-fecha-input { border:1px solid #e2e8f0; border-radius:6px; padding:5px 10px; font-size:13px; cursor:pointer; }
.btn-nueva-ruta { margin-left:auto; background:#2563eb; color:#fff; border:none; border-radius:8px; padding:8px 16px; font-size:13px; font-weight:600; cursor:pointer; }
.btn-nueva-ruta:hover { background:#1d4ed8; }

/* KPIs */
.lr-kpis { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.lr-kpi  { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:12px 18px; min-width:160px; }
.lr-kpi .k-val { font-size:22px; font-weight:700; color:#0f172a; }
.lr-kpi .k-lbl { font-size:11px; color:#64748b; margin-top:2px; }

/* Unidades */
.lr-unidades { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:24px; }
@media(max-width:700px){ .lr-unidades { grid-template-columns:1fr; } }
.unit-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.unit-card-head { padding:14px 18px; display:flex; align-items:center; gap:10px; border-bottom:1px solid #f1f5f9; }
.unit-icon { font-size:20px; }
.unit-nombre { font-size:15px; font-weight:700; color:#0f172a; }
.unit-cap { font-size:11px; color:#64748b; margin-left:auto; }
.unit-bar-wrap { padding:0 18px 10px; }
.unit-bar { height:8px; background:#f1f5f9; border-radius:99px; margin-top:10px; overflow:hidden; }
.unit-bar-fill { height:100%; border-radius:99px; transition:width .4s; background:#22c55e; }
.unit-bar-fill.warn { background:#f59e0b; }
.unit-bar-fill.over { background:#ef4444; }
.unit-peso-txt { font-size:11px; color:#64748b; margin-top:4px; }

.unit-entregas { padding:0 12px 12px; min-height:60px; }
.entrega-row { display:flex; align-items:center; gap:6px; padding:7px 8px; border:1px solid #f1f5f9; border-radius:8px; margin-bottom:6px; background:#fafafa; }
.entrega-num  { width:22px; height:22px; background:#2563eb; color:#fff; border-radius:50%; font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.entrega-info { flex:1; min-width:0; }
.entrega-folio  { font-size:11px; font-weight:700; color:#2563eb; }
.badge-parcial-ruta { font-size:10px; font-weight:700; background:#fff7ed; color:#c2410c; border:1px solid #fed7aa; padding:1px 6px; border-radius:4px; margin-left:4px; }
.tiempo-estimado-box { font-size:12px; font-weight:700; color:#1d4ed8; background:#eff6ff; border-top:1px solid #bfdbfe; padding:8px 14px; }
.entrega-cliente{ font-size:12px; color:#0f172a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.entrega-dir    { font-size:10px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.entrega-peso   { font-size:10px; color:#64748b; white-space:nowrap; }
.entrega-badge  { font-size:10px; font-weight:600; padding:2px 7px; border-radius:99px; white-space:nowrap; }
.eb-pendiente   { background:#fef3c7; color:#b45309; }
.eb-entregado   { background:#dcfce7; color:#16a34a; }
.eb-no_entregado{ background:#fee2e2; color:#dc2626; }
.btn-mv { background:none; border:1px solid #e2e8f0; border-radius:5px; width:22px; height:22px; cursor:pointer; font-size:11px; padding:0; display:flex; align-items:center; justify-content:center; }
.btn-mv:hover { background:#f1f5f9; }
.btn-edit-e { background:none; border:none; cursor:pointer; font-size:13px; padding:2px 4px; border-radius:4px; }
.btn-edit-e:hover { background:#f1f5f9; }
.btn-quitar-e { background:none; border:none; cursor:pointer; font-size:13px; padding:2px 4px; border-radius:4px; color:#dc2626; }
.btn-quitar-e:hover { background:#fee2e2; }

.unit-footer { padding:10px 12px; border-top:1px solid #f1f5f9; display:flex; gap:8px; align-items:center; }
.unit-estado { font-size:11px; font-weight:600; padding:3px 10px; border-radius:99px; }
.ue-planificada { background:#eff6ff; color:#2563eb; }
.ue-en_ruta     { background:#fef3c7; color:#b45309; }
.ue-completada  { background:#dcfce7; color:#16a34a; }
.btn-iniciar { background:#f59e0b; color:#000; border:none; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; }
.btn-borrar-ruta { background:none; border:1px solid #fca5a5; color:#dc2626; border-radius:8px; padding:5px 10px; font-size:13px; cursor:pointer; margin-left:auto; }
.btn-borrar-ruta:hover { background:#fee2e2; }
.btn-iniciar:hover { background:#d97706; }
.no-rutas { color:#94a3b8; font-size:13px; text-align:center; padding:20px; }

/* Pendientes */
.lr-pendientes-head { font-size:14px; font-weight:700; color:#0f172a; margin-bottom:10px; }
.lr-tbl-wrap { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
table.lr-tbl { width:100%; border-collapse:collapse; font-size:13px; }
.lr-tbl thead th { background:#f8fafc; padding:10px 14px; text-align:left; font-size:11px; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid #e2e8f0; }
.lr-tbl tbody td { padding:10px 14px; border-bottom:1px solid #f8fafc; vertical-align:middle; }
.lr-tbl tbody tr:last-child td { border-bottom:none; }
.lr-tbl tbody tr:hover td { background:#f8fafc; }
.peso-chip { font-size:11px; font-weight:600; padding:2px 8px; border-radius:99px; background:#f1f5f9; color:#475569; }
.estado-chip { font-size:11px; font-weight:600; padding:2px 8px; border-radius:99px; }
.ec-activa    { background:#eff6ff; color:#2563eb; }
.ec-terminado { background:#dcfce7; color:#16a34a; }
.btn-asignar { background:#2563eb; color:#fff; border:none; border-radius:6px; padding:5px 12px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; }
.btn-asignar:hover { background:#1d4ed8; }
.loading-row td { text-align:center; color:#94a3b8; padding:30px; }

/* Modales */
.lr-modal-bg { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:999; align-items:center; justify-content:center; padding:20px; }
.lr-modal-bg.open { display:flex; }
.lr-modal { background:#fff; border-radius:14px; padding:28px; width:100%; max-width:440px; box-shadow:0 20px 60px rgba(0,0,0,.3); }
.lr-modal h3 { font-size:16px; font-weight:700; margin-bottom:18px; color:#0f172a; }
.lr-field { margin-bottom:14px; }
.lr-field label { display:block; font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.8px; margin-bottom:5px; }
.lr-field input, .lr-field select { width:100%; padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; outline:none; }
.lr-field input:focus, .lr-field select:focus { border-color:#2563eb; }
.lr-row2 { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.modal-btns { display:flex; gap:8px; justify-content:flex-end; margin-top:20px; }
.btn-cancel { background:#f1f5f9; border:none; border-radius:8px; padding:9px 18px; font-size:13px; font-weight:600; cursor:pointer; }
.btn-confirm { background:#2563eb; color:#fff; border:none; border-radius:8px; padding:9px 18px; font-size:13px; font-weight:600; cursor:pointer; }
.btn-confirm:hover { background:#1d4ed8; }
.btn-cancel:hover { background:#e2e8f0; }

.btn-planificar { background:#7c3aed; color:#fff; border:none; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:5px; }
.btn-ghost-eta { background:#fff; color:#1d4ed8; border:1px solid #bfdbfe; border-radius:8px; padding:6px 14px; font-size:12px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:5px; }
.btn-planificar:hover { background:#6d28d9; }
.btn-planificar:disabled { background:#c4b5fd; cursor:not-allowed; }
.plan-tiempo { font-size:11px; color:#7c3aed; font-weight:600; margin-left:4px; }
.unit-mapa { width:100%; height:260px; border-radius:0; border-top:1px solid #f1f5f9; display:none; }
.gps-timer { font-size:11px; color:#64748b; padding:6px 14px; border-top:1px solid #f1f5f9; background:#f8fafc; }
.unit-mapa.visible { display:block; }
.unit-tramos { padding:8px 14px; border-top:1px solid #f1f5f9; }
.tramos-title { font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:6px; }
.tramo-row { display:flex; align-items:center; gap:6px; font-size:12px; color:#334155; padding:3px 0; flex-wrap:wrap; }
.tramo-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.tramo-desde, .tramo-hasta { max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.tramo-flecha { color:#94a3b8; }
.tramo-min { margin-left:auto; color:#0f172a; font-weight:600; white-space:nowrap; }
.tramo-espera { color:#d97706; font-weight:600; }
.tramo-nota { font-size:11px; color:#94a3b8; margin-top:6px; font-style:italic; }
.lr-map-label { background:#1e40af; color:#fff; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:700; white-space:nowrap; }
/* Autocomplete nuevo — forzar estilo claro */
.pac-container { z-index:99999 !important; font-family:-apple-system,sans-serif; font-size:13px; }
gmp-place-autocomplete {
  width: 100%;
  display: block;
  color-scheme: light;
  --gmp-material-color-primary: #2563eb;
  --gmp-color-surface: #ffffff;
  --gmp-color-on-surface: #0f172a;
  --gmp-color-on-surface-variant: #64748b;
  --gmp-color-outline: #e2e8f0;
  --gmp-color-outline-variant: #e2e8f0;
  --gmp-input-background-color: #fff;
  --gmp-input-border-color: #e2e8f0;
  --gmp-input-border-radius: 8px;
  --gmp-input-font-size: 14px;
  --gmp-input-padding: 9px 12px;
  --gmp-input-color: #0f172a;
}
gmp-place-autocomplete input, gmp-place-autocomplete::part(input) {
  background: #fff !important;
  color: #0f172a !important;
  border: 1px solid #e2e8f0 !important;
  border-radius: 8px !important;
  padding: 9px 12px !important;
  font-size: 14px !important;
  width: 100% !important;
  box-sizing: border-box !important;
  outline: none !important;
  font-family: -apple-system, sans-serif !important;
}
gmp-place-autocomplete::part(icon) { display: none !important; }

/* Lista piezas en modal asignar — agrupado por partida */
.as-piezas-wrap { margin-top:4px; border:1px solid #e2e8f0; border-radius:8px; overflow-y:auto; overflow-x:hidden; max-height:240px; }
.as-partida-row { border-bottom:1px solid #f1f5f9; }
.as-partida-row:last-child { border-bottom:none; }
.as-partida-head { display:grid; grid-template-columns:20px 1fr auto auto; align-items:center; gap:8px; padding:9px 12px; background:#f8fafc; cursor:pointer; user-select:none; width:100%; box-sizing:border-box; }
.as-partida-head:hover { background:#f1f5f9; }
.as-partida-cb { width:16px; height:16px; cursor:pointer; accent-color:#2563eb; }
.as-partida-label { font-size:13px; font-weight:700; color:#0f172a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.as-partida-cnt { font-size:11px; color:#64748b; white-space:nowrap; }
.as-partida-expand { background:none; border:1px solid #e2e8f0; border-radius:4px; width:22px; height:22px; cursor:pointer; font-size:10px; color:#64748b; display:flex; align-items:center; justify-content:center; transition:transform .2s; }
.as-partida-expand.open { transform:rotate(180deg); }
.as-piezas-detalle { display:none; background:#fff; }
.as-piezas-detalle.open { display:block; }
.as-pieza-row { display:grid; grid-template-columns:16px 1fr auto; align-items:center; gap:8px; padding:6px 12px 6px 36px; border-bottom:1px solid #f8fafc; box-sizing:border-box; width:100%; }
.as-pieza-row:last-child { border-bottom:none; }
.as-pieza-cb { width:15px; height:15px; cursor:pointer; accent-color:#2563eb; }
.as-pieza-qr { font-size:10px; font-weight:700; color:#2563eb; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.as-pieza-med { font-size:11px; color:#374151; white-space:nowrap; }
.as-piezas-footer { display:flex; justify-content:space-between; align-items:center; padding:6px 12px; background:#f8fafc; border-top:1px solid #e2e8f0; font-size:11px; color:#64748b; }
.as-piezas-sel { font-weight:700; color:#2563eb; }
.as-piezas-loading { text-align:center; padding:20px; color:#94a3b8; font-size:13px; }
.as-sel-todos { background:none; border:none; color:#2563eb; font-size:11px; cursor:pointer; font-weight:600; padding:0; }

/* Toast */
.lr-toast { position:fixed; bottom:24px; right:24px; background:#0f172a; color:#fff; padding:10px 18px; border-radius:10px; font-size:13px; font-weight:500; z-index:9999; display:none; animation:fadeInUp .2s; }
@keyframes fadeInUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }

@media(max-width:768px){
  .lr-wrap { padding: 12px; }

  /* Header: apilado en columna */
  .lr-header { flex-direction: column; align-items: stretch; gap: 10px; }
  .lr-date-nav { justify-content: center; flex-wrap: wrap; }
  .btn-nueva-ruta { margin-left: 0; width: 100%; text-align: center; }

  /* KPIs: grid 2 columnas */
  .lr-kpis { display: grid; grid-template-columns: repeat(2,1fr); gap: 10px; }
  .lr-kpi { min-width: 0; padding: 10px 14px; }
  .lr-kpi .k-val { font-size: 18px; }

  /* Tabla pendientes: ocultar Asesor, Localidad, Peso est. */
  .lr-tbl thead th:nth-child(3),
  .lr-tbl thead th:nth-child(5),
  .lr-tbl thead th:nth-child(7),
  .lr-tbl tbody td:nth-child(3),
  .lr-tbl tbody td:nth-child(5),
  .lr-tbl tbody td:nth-child(7) { display: none; }

  .lr-tbl thead th { padding: 8px 10px; font-size: 10px; }
  .lr-tbl tbody td { padding: 9px 10px; font-size: 12px; }
  .btn-asignar { font-size: 11px; padding: 4px 8px; }

  /* Modales: full width */
  .lr-modal { padding: 20px 16px; max-width: 100% !important; }
  .lr-row2 { grid-template-columns: 1fr; }
  .modal-btns { flex-direction: column; }
  .modal-btns .btn-cancel,
  .modal-btns .btn-confirm { width: 100%; text-align: center; }

  /* Toast: abajo centrado */
  .lr-toast { right: 12px; left: 12px; bottom: 16px; text-align: center; }
}
</style>

<div class="lr-wrap" id="lr-wrap">
  <div class="lr-header">
    <div class="lr-title">&#128666; Rutas de Entrega</div>
    <div class="lr-date-nav">
      <button onclick="LR.cambiarDia(-1)">&#8592;</button>
      <span id="lr-fecha-lbl"></span>
      <button onclick="LR.cambiarDia(1)">&#8594;</button>
      <button onclick="LR.irHoy()">Hoy</button>
      <input type="date" id="lr-fecha-input" onchange="LR.setFecha(this.value)">
    </div>
    <button class="btn-nueva-ruta" onclick="LR.abrirModalRuta()">+ Nueva Ruta</button>
  </div>

  <div class="lr-kpis" id="lr-kpis">
    <div class="lr-kpi"><div class="k-val" id="k-pendientes">—</div><div class="k-lbl">Pendientes de asignar</div></div>
    <div class="lr-kpi"><div class="k-val" id="k-peso">—</div><div class="k-lbl">kg pendientes</div></div>
    <div class="lr-kpi"><div class="k-val" id="k-rutas">—</div><div class="k-lbl">Rutas del día</div></div>
    <div class="lr-kpi"><div class="k-val" id="k-entregadas">—</div><div class="k-lbl">Entregas completadas</div></div>
  </div>

  <div class="lr-unidades" id="lr-unidades">
    <div class="no-rutas">Cargando...</div>
  </div>

  <div class="lr-pendientes-head">Órdenes pendientes de asignar</div>
  <div class="lr-tbl-wrap">
    <table class="lr-tbl">
      <thead><tr>
        <th>Folio</th><th>Cliente</th><th>Asesor</th><th>Fecha entrega</th>
        <th>Localidad</th><th>Estado</th><th>Peso est.</th><th></th>
      </tr></thead>
      <tbody id="lr-pendientes-tbody">
        <tr class="loading-row"><td colspan="8">Cargando...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal nueva ruta -->
<div id="modal-ruta" class="lr-modal-bg">
  <div class="lr-modal">
    <h3>Nueva Ruta</h3>
    <div class="lr-field">
      <label>Unidad</label>
      <select id="nr-unidad">
        <option value="gris">&#128665; Gris — 1,250 kg</option>
        <option value="blanca">&#128665; Blanca — 700 kg</option>
      </select>
    </div>
    <div class="lr-field">
      <label>Chofer</label>
      <input type="text" id="nr-chofer" placeholder="Nombre del chofer">
    </div>
    <div class="lr-field">
      <label>Notas</label>
      <input type="text" id="nr-notas" placeholder="Ruta foránea, horario especial...">
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="LR.cerrarModalRuta()">Cancelar</button>
      <button class="btn-confirm" onclick="LR.guardarRuta()">Crear Ruta</button>
    </div>
  </div>
</div>

<!-- Modal asignar orden -->
<div id="modal-asignar" class="lr-modal-bg">
  <div class="lr-modal" style="max-width:500px;">
    <h3>Asignar a Ruta</h3>
    <input type="hidden" id="as-orden-id">
    <input type="hidden" id="as-ruta-id">
    <div class="lr-field">
      <label>Ruta destino</label>
      <select id="as-ruta-sel"></select>
    </div>
    <div class="lr-field">
      <label>Piezas a enviar</label>
      <div class="as-piezas-wrap" id="as-piezas-lista">
        <div class="as-piezas-loading">Cargando piezas...</div>
      </div>
      <div class="as-piezas-footer">
        <span><span class="as-piezas-sel" id="as-piezas-count">0</span> piezas seleccionadas</span>
        <button class="as-sel-todos" onclick="LR.toggleTodasPiezas()">Seleccionar todas</button>
      </div>
    </div>
    <div class="lr-field">
      <label>Dirección</label>
      <input type="text" id="as-dir" placeholder="Calle y número">
    </div>
    <div class="lr-row2">
      <div class="lr-field">
        <label>Colonia</label>
        <input type="text" id="as-col" placeholder="Colonia">
      </div>
      <div class="lr-field">
        <label>Ciudad</label>
        <input type="text" id="as-ciu" placeholder="Monterrey" value="Monterrey">
      </div>
    </div>
    <div class="lr-field">
      <label>Referencias</label>
      <input type="text" id="as-ref" placeholder="Entre calles, color de fachada...">
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="LR.cerrarModalAsignar()">Cancelar</button>
      <button class="btn-confirm" onclick="LR.guardarAsignacion()">Asignar</button>
    </div>
  </div>
</div>

<!-- Modal editar dirección -->
<div id="modal-edit-dir" class="lr-modal-bg">
  <div class="lr-modal">
    <h3>Editar Dirección</h3>
    <input type="hidden" id="ed-entrega-id">
    <div class="lr-field">
      <label>Dirección</label>
      <input type="text" id="ed-dir">
    </div>
    <div class="lr-row2">
      <div class="lr-field"><label>Colonia</label><input type="text" id="ed-col"></div>
      <div class="lr-field"><label>Ciudad</label><input type="text" id="ed-ciu"></div>
    </div>
    <div class="lr-field">
      <label>Referencias</label>
      <input type="text" id="ed-ref">
    </div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="LR.cerrarEditDir()">Cancelar</button>
      <button class="btn-confirm" onclick="LR.guardarEditDir()">Guardar</button>
    </div>
  </div>
</div>

<div id="modal-carga" class="lr-modal-bg">
  <div class="lr-modal">
    <h3>Carga en planta</h3>
    <div style="font-size:12px;color:#64748b;margin-bottom:10px">
      El chofer escanea el QR de la remisión de cada orden (impresa desde Cotización/Cobranza) con la cámara en su celular. Esta pantalla solo muestra el avance — se actualiza sola.
    </div>
    <input type="hidden" id="carga-ruta-id">
    <div id="carga-progreso" style="margin:8px 0;font-size:13px;font-weight:700;color:#374151"></div>
    <div id="carga-lista" style="max-height:320px;overflow-y:auto"></div>
    <div class="modal-btns">
      <button class="btn-cancel" onclick="LR.cerrarModalCarga()">Cerrar</button>
    </div>
  </div>
</div>

<div id="lr-toast" class="lr-toast"></div>

<script>
var LR = (function() {
  var API_RUTAS    = '../api/rutas.php';
  var API_GPS      = '../api/gps_proxy.php';
  var PUEDE_BORRAR_RUTA = <?= in_array($user['rol'], ['dir_admin','desarrollo']) ? 'true' : 'false' ?>;
  var _fecha       = new Date().toISOString().slice(0,10);
  var _rutas       = [];
  var _pendientes  = [];
  var CAP          = { gris: 1250, blanca: 700 };
  var UNIDAD_LABEL = { gris: '🚛 Gris', blanca: '🚛 Blanca' };
  // Planta — C. de la Industria 214, Marfer, 66367 Cdad. Santa Catarina, N.L. (mismas coords que UPD-266, WA ubicación)
  var PLANTA_LATLNG = { lat: 25.6930336, lng: -100.4807059 };
  // Paleta para colorear cada tramo (planta→parada1, parada1→parada2, ...) de una ruta con un
  // color distinto, para diferenciar los puntos de un vistazo. Hasta 20 tramos con color propio;
  // si una ruta tiene más paradas, se repiten desde el principio.
  var PALETA_TRAMOS = [
    '#2563eb', '#dc2626', '#16a34a', '#f59e0b', '#7c3aed',
    '#0891b2', '#db2777', '#65a30d', '#ea580c', '#4f46e5',
    '#059669', '#c026d3', '#0284c7', '#ca8a04', '#e11d48',
    '#0d9488', '#9333ea', '#65758d', '#84cc16', '#f43f5e',
  ];
  var _iconoFabrica = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24">'
    + '<circle cx="12" cy="12" r="11" fill="#1e40af" stroke="#fff" stroke-width="2"/>'
    + '<text x="12" y="16" text-anchor="middle" font-size="12">🏭</text>'
    + '</svg>'
  );

  function fmt(d) {
    return new Date(d + 'T12:00:00').toLocaleDateString('es-MX',{weekday:'short',day:'2-digit',month:'short',year:'numeric'});
  }
  function fmtKg(n) { return parseFloat(n||0).toFixed(1) + ' kg'; }

  function toast(msg, err) {
    var el = document.getElementById('lr-toast');
    el.textContent = msg;
    el.style.background = err ? '#dc2626' : '#0f172a';
    el.style.display = 'block';
    setTimeout(function(){ el.style.display='none'; }, 3000);
  }

  function setFecha(f) {
    _fecha = f;
    document.getElementById('lr-fecha-lbl').textContent = fmt(f);
    document.getElementById('lr-fecha-input').value = f;
    cargar();
  }

  function irHoy() { setFecha(new Date().toISOString().slice(0,10)); }

  function cambiarDia(d) {
    var dt = new Date(_fecha + 'T12:00:00');
    dt.setDate(dt.getDate() + d);
    setFecha(dt.toISOString().slice(0,10));
  }

  async function cargar() {
    await Promise.all([cargarRutas(), cargarPendientes()]);
    renderKpis();
  }

  async function cargarRutas() {
    var r = await fetch(API_RUTAS + '?accion=rutas_fecha&fecha=' + _fecha);
    _rutas = await r.json();
    window._lrRutas = _rutas;
    renderUnidades();
  }

  async function cargarPendientes() {
    var r = await fetch(API_RUTAS + '?accion=pendientes&fecha=' + _fecha);
    _pendientes = await r.json();
    renderPendientes();
  }

  function renderKpis() {
    var totalPeso = _pendientes.reduce(function(s,o){ return s + parseFloat(o.peso_kg||0); }, 0);
    var entregas  = _rutas.reduce(function(s,r){ return s + parseInt(r.entregadas||0); }, 0);
    document.getElementById('k-pendientes').textContent = _pendientes.length;
    document.getElementById('k-peso').textContent       = totalPeso.toFixed(1);
    document.getElementById('k-rutas').textContent      = _rutas.length;
    document.getElementById('k-entregadas').textContent = entregas;
  }

  function renderUnidades() {
    var el = document.getElementById('lr-unidades');
    // Limpiar instancias de mapas anteriores
    _mapas = {};
    if (!_rutas.length) {
      el.innerHTML = '<div class="no-rutas" style="grid-column:1/-1">No hay rutas para este día. Crea una con "+ Nueva Ruta".</div>';
      return;
    }
    el.innerHTML = _rutas.map(renderUnitCard).join('');
    // Dibujar pins después de que el DOM esté listo
    if (window.google && window.google.maps) {
      setTimeout(function() {
        _rutas.forEach(function(r) {
          if (r.entregas && r.entregas.length) dibujarPines(r.id);
        });
        tickGpsPolling();
        tickGpsTimerDisplay();
      }, 100);
    }
  }

  function renderUnitCard(ruta) {
    var cap    = CAP[ruta.unidad] || 700;
    var usado  = parseFloat(ruta.peso_total || 0);
    var pct    = Math.min(Math.round(usado / cap * 100), 100);
    var fillCls= pct >= 90 ? 'over' : pct >= 70 ? 'warn' : '';
    var eClass = 'ue-' + ruta.estado;

    var rows = '';
    if (ruta.entregas && ruta.entregas.length) {
      ruta.entregas.forEach(function(e, i) {
        var bCls = 'eb-' + e.entrega_estado;
        var dir  = [e.direccion, e.colonia, e.ciudad].filter(Boolean).map(escH).join(', ')
                   || '<span style="color:#94a3b8">Sin direcci&oacute;n</span>';
        var badgeParcial = e.es_parcial == 1
          ? ' <span class="badge-parcial-ruta" title="Ya sali&oacute; parte de esta orden (remisi&oacute;n impresa)">Parcial '+e.salida_piezas_count+'/'+e.salida_piezas_total+'</span>'
          : '';
        rows += '<div class="entrega-row" id="erow-'+e.id+'">'
          + '<div class="entrega-num">'+(i+1)+'</div>'
          + '<div class="entrega-info">'
          +   '<div class="entrega-folio">'+escH(e.folio)+badgeParcial+'</div>'
          +   '<div class="entrega-cliente">'+escH(e.cliente_nombre)+'</div>'
          +   '<div class="entrega-dir">'+dir+'</div>'
          + '</div>'
          + '<div class="entrega-peso">'+fmtKg(e.peso_kg)+'</div>'
          + '<span class="entrega-badge '+bCls+'">'+{pendiente:'Pend.',entregado:'OK',no_entregado:'No ent.'}[e.entrega_estado]+'</span>'
          + (i>0 ? '<button class="btn-mv" title="Subir" onclick="LR.mover('+ruta.id+','+e.id+',-1)">&#9650;</button>' : '<div style="width:22px"></div>')
          + (i<ruta.entregas.length-1 ? '<button class="btn-mv" title="Bajar" onclick="LR.mover('+ruta.id+','+e.id+',1)">&#9660;</button>' : '<div style="width:22px"></div>')
          + '<button class="btn-edit-e" title="Editar direcci&oacute;n" onclick="LR.abrirEditDir('+e.id+')">&#9999;&#65039;</button>'
          + '<button class="btn-quitar-e" title="Quitar de ruta" onclick="LR.quitar('+e.id+','+ruta.id+')">&#10005;</button>'
          + '</div>';
      });
    } else {
      rows = '<div style="color:#94a3b8;font-size:12px;text-align:center;padding:14px">Sin entregas asignadas</div>';
    }

    var btnPlanificar = '';
    var btnCarga = '';
    var btnRecalcEta = '';
    var tiempoEstimadoHtml = '';
    // Ya no hay botón "Iniciar Ruta" — arranca sola en cuanto el chofer escanea como cargada
    // la última pieza pendiente de la ruta (ver api/salidas.php accion=scan_qr).
    if (ruta.estado === 'planificada' && ruta.entregas && ruta.entregas.length) {
      btnPlanificar = '<button class="btn-planificar" id="btn-plan-'+ruta.id+'" onclick="LR.planificar('+ruta.id+')">🗺️ Ruta óptima</button>';
      btnCarga      = '<button class="btn-planificar" onclick="LR.abrirModalCarga('+ruta.id+')">📦 Ver avance de carga</button>';
    }
    if (ruta.estado === 'en_ruta' && ruta.entregas && ruta.entregas.some(function(e){ return e.entrega_estado === 'pendiente'; })) {
      btnRecalcEta = '<button class="btn-ghost-eta" onclick="LR.recalcularEta('+ruta.id+')">⏱️ Recalcular tiempo estimado</button>';
      var proxima = ruta.entregas.filter(function(e){ return e.entrega_estado === 'pendiente'; })[0];
      var etaProxima = proxima ? (_ultimasEtas[proxima.id] || proxima.eta_min) : null;
      if (etaProxima) {
        tiempoEstimadoHtml = '<div class="tiempo-estimado-box">Tiempo Estimado: '+fmtEta(etaProxima)+'</div>';
      }
    }

    var tieneEntregas = ruta.entregas && ruta.entregas.length > 0;
    var btnImprimirRuta = tieneEntregas
      ? '<button class="btn-planificar" onclick="window.open(\'imprimir_ruta.php?id='+ruta.id+'\',\'_blank\')">🖨️ Hoja de ruta</button>'
      : '';
    var btnFinalizar = (ruta.estado === 'completada')
      ? '<button class="btn-iniciar" onclick="LR.finalizarRuta('+ruta.id+')">✅ Finalizar</button>'
      : '';

    return '<div class="unit-card">'
      + '<div class="unit-card-head">'
      +   '<span class="unit-icon">🚛</span>'
      +   '<span class="unit-nombre">'+UNIDAD_LABEL[ruta.unidad]+'</span>'
      +   '<span class="unit-cap">Cap. '+cap.toLocaleString()+' kg'+(ruta.chofer ? ' — '+escH(ruta.chofer) : '')+'</span>'
      + '</div>'
      + '<div class="unit-bar-wrap">'
      +   '<div class="unit-bar"><div class="unit-bar-fill '+fillCls+'" style="width:'+pct+'%"></div></div>'
      +   '<div class="unit-peso-txt">'+fmtKg(usado)+' / '+cap.toLocaleString()+' kg ('+pct+'%)</div>'
      + '</div>'
      + '<div class="unit-entregas" id="entregas-ruta-'+ruta.id+'">'+rows+'</div>'
      + '<div id="mapa-ruta-'+ruta.id+'" class="unit-mapa'+(tieneEntregas ? ' visible' : '')+'"></div>'
      + '<div id="tramos-ruta-'+ruta.id+'" class="unit-tramos"></div>'
      + (ruta.estado === 'en_ruta' ? '<div class="gps-timer" id="gps-timer-'+ruta.id+'">📡 Ubicación en vivo</div>' : '')
      + '<div class="unit-footer">'
      +   '<span class="unit-estado '+eClass+'">'+(ruta.estado==='planificada'?'Planificada':ruta.estado==='en_ruta'?'En ruta':'Completada')+'</span>'
      +   btnPlanificar
      +   btnImprimirRuta
      +   btnCarga
      +   btnRecalcEta
      +   btnFinalizar
      +   (PUEDE_BORRAR_RUTA ? '<button class="btn-borrar-ruta" title="Borrar ruta" onclick="LR.eliminarRuta('+ruta.id+')">🗑️</button>' : '')
      + '</div>'
      + tiempoEstimadoHtml
      + '</div>';
  }

  function renderPendientes() {
    var tbody = document.getElementById('lr-pendientes-tbody');
    if (!_pendientes.length) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:24px">Sin órdenes pendientes de asignar</td></tr>';
      return;
    }
    var rows = '';
    _pendientes.forEach(function(o) {
      var fmtF = o.fecha_entrega ? new Date(o.fecha_entrega+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short'}) : '—';
      var eCls = 'ec-' + o.estado;
      rows += '<tr>'
        + '<td style="font-weight:700;color:#2563eb">'+escH(o.folio)+'</td>'
        + '<td>'+escH(o.cliente_nombre)+'</td>'
        + '<td>'+escH(o.asesor||'')+'</td>'
        + '<td>'+fmtF+'</td>'
        + '<td>'+(o.localidad||'Local')+(o.ciudad_destino?' — '+escH(o.ciudad_destino):'')+'</td>'
        + '<td><span class="estado-chip '+eCls+'">'+o.estado+'</span></td>'
        + '<td><span class="peso-chip">'+fmtKg(o.peso_kg)+'</span></td>'
        + '<td><button class="btn-asignar" onclick="LR.abrirModalAsignar('+o.id+')">Asignar</button></td>'
        + '</tr>';
    });
    tbody.innerHTML = rows;
  }

  function escH(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  // ── Modales ──────────────────────────────────────────────
  function abrirModalRuta() {
    document.getElementById('modal-ruta').classList.add('open');
    document.getElementById('nr-chofer').focus();
  }
  function cerrarModalRuta() { document.getElementById('modal-ruta').classList.remove('open'); }

  async function guardarRuta() {
    var unidad = document.getElementById('nr-unidad').value;
    var chofer = document.getElementById('nr-chofer').value.trim();
    var notas  = document.getElementById('nr-notas').value.trim();
    var r = await fetch(API_RUTAS, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({accion:'crear_ruta', fecha:_fecha, unidad:unidad, chofer:chofer, notas:notas})
    });
    var d = await r.json();
    if (d.ok) {
      cerrarModalRuta();
      document.getElementById('nr-chofer').value = '';
      document.getElementById('nr-notas').value  = '';
      toast('Ruta creada');
      await cargarRutas();
    } else {
      toast(d.error || 'Error', true);
    }
  }

  function abrirModalAsignar(orden_id) {
    if (!_rutas.length) { toast('Crea al menos una ruta primero', true); return; }
    document.getElementById('as-orden-id').value = orden_id;
    var sel = document.getElementById('as-ruta-sel');
    sel.innerHTML = _rutas.map(function(r) {
      var usado = parseFloat(r.peso_total||0);
      var cap   = CAP[r.unidad]||700;
      return '<option value="'+r.id+'">'+UNIDAD_LABEL[r.unidad]+(r.chofer?' ('+escH(r.chofer)+')':'')
        +' &mdash; '+usado.toFixed(1)+'/'+cap+' kg</option>';
    }).join('');
    document.getElementById('as-dir').value = '';
    document.getElementById('as-col').value = '';
    document.getElementById('as-ciu').value = 'Monterrey';
    document.getElementById('as-ref').value = '';
    document.getElementById('as-piezas-lista').innerHTML = '<div class="as-piezas-loading">Cargando piezas...</div>';
    document.getElementById('as-piezas-count').textContent = '0';
    document.getElementById('modal-asignar').classList.add('open');
    setTimeout(function(){ initAutocomplete(); }, 100);
    // Cargar piezas de la orden
    fetch(API_RUTAS + '?accion=piezas_orden&orden_id=' + orden_id)
      .then(function(r){ return r.json(); })
      .then(function(piezas){ renderPiezasModal(piezas); });
  }

  function renderPiezasModal(piezas) {
    var lista = document.getElementById('as-piezas-lista');
    if (!piezas.length) {
      lista.innerHTML = '<div class="as-piezas-loading">No hay piezas terminadas</div>';
      return;
    }

    // Agrupar por partida
    var grupos = {};
    piezas.forEach(function(p) {
      var key = p.partida;
      if (!grupos[key]) grupos[key] = { partida: p.partida, cristal: p.cristal_corto, piezas: [] };
      grupos[key].piezas.push(p);
    });

    var html = '';
    Object.keys(grupos).forEach(function(k) {
      var g = grupos[k];
      var total = g.piezas.length;
      var gid = 'partida-' + k;
      html += '<div class="as-partida-row">'
        + '<div class="as-partida-head" onclick="LR.togglePartidaExpand(\'' + gid + '\')">'
        +   '<input type="checkbox" class="as-partida-cb" id="cb-' + gid + '" checked'
        +     ' onchange="LR.togglePartida(\'' + gid + '\', this.checked)" onclick="event.stopPropagation()">'
        +   '<div class="as-partida-label">Partida ' + g.partida + ' — ' + escH(g.cristal || '') + '</div>'
        +   '<span class="as-partida-cnt">' + total + ' pieza' + (total > 1 ? 's' : '') + '</span>'
        +   '<button class="as-partida-expand" id="exp-' + gid + '">▼</button>'
        + '</div>'
        + '<div class="as-piezas-detalle" id="det-' + gid + '">';

      g.piezas.forEach(function(p) {
        html += '<div class="as-pieza-row">'
          + '<input type="checkbox" class="as-pieza-cb" value="' + p.id + '" data-partida="' + gid + '" checked'
          +   ' onchange="LR.onPiezaCb(\'' + gid + '\')">'
          + '<span class="as-pieza-qr">' + escH(p.qr_code || 'P'+p.partida+'-'+p.pieza_num+'de'+p.pieza_total) + '</span>'
          + '<span class="as-pieza-med">' + p.ancho_mm + '×' + p.alto_mm + ' mm</span>'
          + '</div>';
      });

      html += '</div></div>';
    });

    lista.innerHTML = html;
    actualizarContPiezas();
  }

  function togglePartidaExpand(gid) {
    var det = document.getElementById('det-' + gid);
    var btn = document.getElementById('exp-' + gid);
    if (!det) return;
    var isOpen = det.classList.contains('open');
    det.classList.toggle('open', !isOpen);
    btn.classList.toggle('open', !isOpen);
  }

  function togglePartida(gid, checked) {
    var cbs = document.querySelectorAll('.as-pieza-cb[data-partida="' + gid + '"]');
    cbs.forEach(function(c){ c.checked = checked; });
    actualizarContPiezas();
  }

  function onPiezaCb(gid) {
    var cbs     = document.querySelectorAll('.as-pieza-cb[data-partida="' + gid + '"]');
    var total   = cbs.length;
    var checked = Array.from(cbs).filter(function(c){ return c.checked; }).length;
    var cbPart  = document.getElementById('cb-' + gid);
    if (cbPart) {
      cbPart.indeterminate = checked > 0 && checked < total;
      cbPart.checked = checked === total;
    }
    actualizarContPiezas();
  }

  function actualizarContPiezas() {
    var checks = document.querySelectorAll('.as-pieza-cb:checked');
    document.getElementById('as-piezas-count').textContent = checks.length;
  }

  function toggleTodasPiezas() {
    var checks = document.querySelectorAll('.as-pieza-cb');
    var todas  = Array.from(checks).every(function(c){ return c.checked; });
    checks.forEach(function(c){ c.checked = !todas; });
    // Actualizar checkboxes de partida
    document.querySelectorAll('.as-partida-cb').forEach(function(cb) {
      cb.checked = !todas;
      cb.indeterminate = false;
    });
    actualizarContPiezas();
  }

  function cerrarModalAsignar() {
    document.getElementById('modal-asignar').classList.remove('open');
    destruirAutocomplete('as-dir');
  }

  // Quita el elemento de autocomplete insertado por initAutocomplete() y deja el input
  // listo para reinicializarse limpio la próxima vez que se abra el modal. Sin esto, cada
  // apertura del modal apilaba una caja de búsqueda de Google nueva encima de la anterior.
  function destruirAutocomplete(inputId) {
    var input = document.getElementById(inputId);
    if (!input) return;
    if (input._acEl && input._acEl.parentNode) input._acEl.parentNode.removeChild(input._acEl);
    input._acEl = null;
    input._acInit = false;
    input.style.display = '';
  }

  async function guardarAsignacion() {
    var orden_id  = parseInt(document.getElementById('as-orden-id').value);
    var ruta_id   = parseInt(document.getElementById('as-ruta-sel').value);
    var checks    = document.querySelectorAll('.as-pieza-cb:checked');
    var pieza_ids = Array.from(checks).map(function(c){ return parseInt(c.value); });

    if (!pieza_ids.length) {
      toast('Selecciona al menos una pieza', true);
      return;
    }

    var r = await fetch(API_RUTAS, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        accion:'asignar', ruta_id:ruta_id, orden_id:orden_id,
        pieza_ids: pieza_ids,
        direccion:   document.getElementById('as-dir').value.trim(),
        colonia:     document.getElementById('as-col').value.trim(),
        ciudad:      document.getElementById('as-ciu').value.trim() || 'Monterrey',
        referencias: document.getElementById('as-ref').value.trim()
      })
    });
    var d = await r.json();
    if (d.ok) {
      cerrarModalAsignar();
      toast('Orden asignada — ' + d.peso_kg + ' kg · ' + pieza_ids.length + ' piezas');
      await cargar();
    } else {
      toast(d.error || 'Error', true);
    }
  }

  function abrirEditDir(entrega_id) {
    var e = null;
    for (var ri = 0; ri < _rutas.length; ri++) {
      var entregas = _rutas[ri].entregas || [];
      for (var ei = 0; ei < entregas.length; ei++) {
        if (entregas[ei].id == entrega_id) { e = entregas[ei]; break; }
      }
      if (e) break;
    }
    if (!e) return;
    document.getElementById('ed-entrega-id').value = entrega_id;
    document.getElementById('ed-dir').value = e.direccion  || '';
    document.getElementById('ed-col').value = e.colonia    || '';
    document.getElementById('ed-ciu').value = e.ciudad     || 'Monterrey';
    document.getElementById('ed-ref').value = e.referencias|| '';
    document.getElementById('modal-edit-dir').classList.add('open');
    setTimeout(function(){ initAutocomplete(); }, 100);
  }
  function cerrarEditDir() { document.getElementById('modal-edit-dir').classList.remove('open'); }

  async function guardarEditDir() {
    var id = parseInt(document.getElementById('ed-entrega-id').value);
    var r  = await fetch(API_RUTAS, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({
        accion:'actualizar_entrega', entrega_id:id,
        direccion:   document.getElementById('ed-dir').value.trim(),
        colonia:     document.getElementById('ed-col').value.trim(),
        ciudad:      document.getElementById('ed-ciu').value.trim()||'Monterrey',
        referencias: document.getElementById('ed-ref').value.trim()
      })
    });
    var d = await r.json();
    if (d.ok) { cerrarEditDir(); toast('Dirección actualizada'); await cargarRutas(); }
    else       { toast(d.error||'Error', true); }
  }

  async function mover(ruta_id, entrega_id, dir) {
    var ruta = _rutas.find(function(r){ return r.id == ruta_id; });
    if (!ruta) return;
    var arr = ruta.entregas.slice().sort(function(a,b){ return a.secuencia-b.secuencia; });
    var idx = arr.findIndex(function(e){ return e.id == entrega_id; });
    var swp = idx + dir;
    if (swp < 0 || swp >= arr.length) return;
    var tmp = arr[idx]; arr[idx] = arr[swp]; arr[swp] = tmp;
    var r = await fetch(API_RUTAS, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({accion:'reordenar', orden: arr.map(function(e){ return e.id; })})
    });
    var d = await r.json();
    if (d.ok) await cargarRutas();
    else toast(d.error||'Error', true);
  }

  async function quitar(entrega_id, ruta_id) {
    if (!confirm('¿Quitar esta entrega de la ruta?')) return;
    var r = await fetch(API_RUTAS, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({accion:'quitar', entrega_id:entrega_id})
    });
    var d = await r.json();
    if (d.ok) { toast('Entrega quitada'); await cargar(); }
    else toast(d.error||'Error', true);
  }

  // ETA por parada calculada al iniciar la ruta (una sola vez, no se recalcula con tráfico
  // en vivo) — { entrega_id: 'HH:MM' }, igual patrón que _ultimosTramos: se guarda aparte
  // porque cargarRutas() re-genera todo el HTML de la tarjeta.
  var _ultimasEtas = {};

  // ── Avance de carga en planta (antes de Iniciar Ruta) ─────────
  // El escaneo real lo hace el chofer con la cámara en app/operador.php (QR de la remisión
  // impresa desde Cotización/Cobranza) — esta pantalla es de solo lectura, se refresca sola.
  var _cargaPollTimer = null;

  async function abrirModalCarga(ruta_id) {
    document.getElementById('carga-ruta-id').value = ruta_id;
    await cargarListaCarga(ruta_id);
    document.getElementById('modal-carga').classList.add('open');
    if (_cargaPollTimer) clearInterval(_cargaPollTimer);
    _cargaPollTimer = setInterval(function(){ cargarListaCarga(ruta_id); }, 5000);
  }

  function cerrarModalCarga() {
    document.getElementById('modal-carga').classList.remove('open');
    if (_cargaPollTimer) { clearInterval(_cargaPollTimer); _cargaPollTimer = null; }
    cargarRutas();
  }

  async function cargarListaCarga(ruta_id) {
    var r = await fetch(API_RUTAS + '?accion=piezas_carga&ruta_id=' + ruta_id);
    var piezas = await r.json();
    var cargadas = piezas.filter(function(p){ return !!p.cargado_at; }).length;
    document.getElementById('carga-progreso').textContent = cargadas + ' / ' + piezas.length + ' piezas cargadas';
    var porOrden = {};
    piezas.forEach(function(p) {
      if (!porOrden[p.orden_id]) porOrden[p.orden_id] = {folio:p.folio, cliente:p.cliente_nombre, items:[]};
      porOrden[p.orden_id].items.push(p);
    });
    var html = '';
    Object.keys(porOrden).forEach(function(oid) {
      var g = porOrden[oid];
      var todasOk = g.items.every(function(p){ return !!p.cargado_at; });
      html += '<div style="margin-bottom:10px"><div style="font-weight:700;font-size:12px;color:'+(todasOk?'#16a34a':'#2563eb')+'">'+(todasOk?'✅ ':'')+escH(g.folio)+' — '+escH(g.cliente||'')+'</div>';
      g.items.forEach(function(p) {
        var ok = !!p.cargado_at;
        html += '<div style="display:flex;align-items:center;gap:6px;padding:3px 0;font-size:12px;color:'+(ok?'#16a34a':'#64748b')+'">'
              + '<span>'+(ok?'✅':'⬜')+'</span>'
              + '<span>'+escH(p.cristal_corto||'')+' — Partida '+p.partida+' / Pieza '+p.pieza_num+' de '+p.pieza_total+'</span>'
              + '</div>';
      });
      html += '</div>';
    });
    document.getElementById('carga-lista').innerHTML = html || '<div style="color:#94a3b8;font-size:12px">Sin piezas asignadas</div>';
  }

  // Formatea minutos totales como "30 min" / "1 h" / "1 h 15 min"
  function fmtEta(min) {
    if (min < 60) return min + ' min';
    var h = Math.floor(min / 60), m = min % 60;
    return h + ' h' + (m > 0 ? ' ' + m + ' min' : '');
  }

  async function recalcularEta(ruta_id) {
    var r = await fetch(API_RUTAS, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({accion:'recalcular_eta', ruta_id:ruta_id})
    });
    var d = await r.json();
    if (d.ok) {
      (d.etas || []).forEach(function(e) { _ultimasEtas[e.entrega_id] = e.eta_min; });
      toast('Tiempo estimado actualizado');
      await cargarRutas();
    } else toast(d.error||'Error', true);
  }

  async function eliminarRuta(ruta_id) {
    if (!confirm('¿Borrar esta ruta por completo? Esta acción no se puede deshacer.')) return;
    var r = await fetch(API_RUTAS, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({accion:'eliminar_ruta', ruta_id:ruta_id})
    });
    var d = await r.json();
    if (d.ok) { toast('Ruta borrada'); await cargar(); }
    else toast(d.error||'Error', true);
  }

  // Oculta la ruta ya completada del tablero del día — los datos siguen en BD (no se borran).
  async function finalizarRuta(ruta_id) {
    if (!confirm('¿Finalizar esta ruta? Ya no se mostrará en el tablero de hoy.')) return;
    var r = await fetch(API_RUTAS, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({accion:'archivar_ruta', ruta_id:ruta_id})
    });
    var d = await r.json();
    if (d.ok) { toast('Ruta finalizada'); await cargar(); }
    else toast(d.error||'Error', true);
  }

  // Último desglose de tramos (A→B, B→C, ...) devuelto por el optimizador, por ruta_id —
  // se guarda aparte porque cargarRutas() re-genera todo el HTML de la tarjeta y borraría
  // el contenedor si no se vuelve a pintar después.
  var _ultimosTramos = {};

  function renderTramos(ruta_id) {
    var cont = document.getElementById('tramos-ruta-' + ruta_id);
    if (!cont) return;
    var tramos = _ultimosTramos[ruta_id];
    if (!tramos || !tramos.length) { cont.innerHTML = ''; return; }
    var html = '<div class="tramos-title">Tiempos por tramo</div>';
    tramos.forEach(function(t, idx) {
      var color = PALETA_TRAMOS[idx % PALETA_TRAMOS.length];
      html += '<div class="tramo-row">'
        + '<span class="tramo-dot" style="background:' + color + '"></span>'
        + '<span class="tramo-desde">' + escH(t.desde) + '</span>'
        + '<span class="tramo-flecha">&rarr;</span>'
        + '<span class="tramo-hasta">' + escH(t.hasta) + '</span>'
        + '<span class="tramo-min">' + t.min + ' min &middot; ' + t.km + ' km'
        +   (t.espera_min ? ' <span class="tramo-espera">+' + t.espera_min + ' min descarga</span>' : '')
        + '</span>'
        + '</div>';
    });
    var totalEspera = tramos.reduce(function(s,t){ return s + (t.espera_min||0); }, 0);
    if (totalEspera > 0) {
      html += '<div class="tramo-nota">Incluye ' + totalEspera + ' min de tolerancia para bajar el vidrio (15 min por parada)</div>';
    }
    cont.innerHTML = html;
  }

  async function planificar(ruta_id) {
    var btn = document.getElementById('btn-plan-' + ruta_id);
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Optimizando...'; }
    var r = await fetch(API_RUTAS, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({accion:'optimizar', ruta_id:ruta_id})
    });
    var d = await r.json();
    if (btn) { btn.disabled = false; btn.innerHTML = '🗺️ Planificar'; }
    if (d.ok) {
      var msg = 'Ruta optimizada';
      if (d.tiempo_min) {
        msg += ' — ' + d.tiempo_min + ' min estimados';
        if (typeof d.antes_min === 'number') {
          msg += d.ahorro_min > 0
            ? ' (antes ' + d.antes_min + ' min, ahorras ' + d.ahorro_min + ' min)'
            : ' (el orden actual ya era el más rápido)';
        }
      }
      toast(msg);
      _ultimosTramos[ruta_id] = d.tramos || null;
      await cargarRutas();
      setTimeout(function(){ dibujarMapa(ruta_id); renderTramos(ruta_id); }, 400);
    } else {
      toast(d.error || 'Error al optimizar', true);
    }
  }

  // Mapa de instancias activas { ruta_id: { map, markers, renderer } }
  var _mapas = {};

  function dibujarPines(ruta_id) {
    if (!window.google || !window.google.maps) return;
    var ruta = null;
    for (var i = 0; i < _rutas.length; i++) {
      if (_rutas[i].id == ruta_id) { ruta = _rutas[i]; break; }
    }
    if (!ruta || !ruta.entregas || !ruta.entregas.length) return;

    var mapEl = document.getElementById('mapa-ruta-' + ruta_id);
    if (!mapEl) return;

    var origenLatLng = PLANTA_LATLNG;

    // Crear o reusar mapa
    var inst = _mapas[ruta_id];
    if (!inst) {
      var map = new google.maps.Map(mapEl, {
        zoom: 11,
        center: origenLatLng,
        disableDefaultUI: true,
        zoomControl: true,
        mapTypeControl: false,
        streetViewControl: false,
      });
      inst = { map: map, markers: [], infoWindow: new google.maps.InfoWindow(), renderer: null };
      _mapas[ruta_id] = inst;
    }

    // Limpiar markers anteriores
    inst.markers.forEach(function(m) { m.setMap(null); });
    inst.markers = [];

    // Pin origen (planta)
    var pinOrigen = new google.maps.Marker({
      position: origenLatLng,
      map: inst.map,
      title: 'Planta — C. de la Industria 214, Marfer, Santa Catarina',
      icon: { url: _iconoFabrica, scaledSize: new google.maps.Size(30, 30), anchor: new google.maps.Point(15, 15) },
      zIndex: 999,
    });
    inst.markers.push(pinOrigen);

    var geocoder = new google.maps.Geocoder();
    var bounds   = new google.maps.LatLngBounds();
    bounds.extend(origenLatLng);

    var COLORES = { pendiente: '#f59e0b', entregado: '#16a34a', no_entregado: '#dc2626' };

    ruta.entregas.forEach(function(e, idx) {
      var num    = idx + 1;
      var addr   = [e.direccion, e.colonia, e.ciudad].filter(Boolean).join(', ') || 'Monterrey, Nuevo León';
      var color  = COLORES[e.entrega_estado] || '#f59e0b';
      var estado = { pendiente: 'Pendiente', entregado: 'Entregado ✅', no_entregado: 'No entregado ❌' }[e.entrega_estado] || '';

      geocoder.geocode({ address: addr + ', México' }, function(results, status) {
        if (status !== 'OK' || !results[0]) return;
        var pos = results[0].geometry.location;
        bounds.extend(pos);
        // Guardar posición por id de entrega — la usa actualizarLineaSiguiente() para trazar
        // la línea al siguiente destino sin tener que volver a geocodificar.
        if (!inst.pinPos) inst.pinPos = {};
        inst.pinPos[e.id] = { lat: pos.lat(), lng: pos.lng() };

        // Pin numerado con SVG
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="42" viewBox="0 0 32 42">'
          + '<path d="M16 0C7.16 0 0 7.16 0 16c0 11.65 16 26 16 26S32 27.65 32 16C32 7.16 24.84 0 16 0z" fill="' + color + '"/>'
          + '<circle cx="16" cy="16" r="10" fill="white"/>'
          + '<text x="16" y="21" text-anchor="middle" font-size="12" font-weight="bold" fill="' + color + '">' + num + '</text>'
          + '</svg>';

        var marker = new google.maps.Marker({
          position: pos,
          map: inst.map,
          icon: { url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), scaledSize: new google.maps.Size(32, 42), anchor: new google.maps.Point(16, 42) },
          title: e.folio + ' — ' + e.cliente_nombre,
          zIndex: num,
        });
        inst.markers.push(marker);

        // Popup al hacer clic
        marker.addListener('click', function() {
          var content = '<div style="font-family:-apple-system,sans-serif;min-width:180px;padding:4px">'
            + '<div style="font-weight:700;color:#2563eb;font-size:13px">' + escH(e.folio) + '</div>'
            + '<div style="font-size:13px;color:#0f172a;margin:2px 0">' + escH(e.cliente_nombre) + '</div>'
            + '<div style="font-size:11px;color:#64748b">' + escH(addr) + '</div>'
            + (e.referencias ? '<div style="font-size:11px;color:#64748b;margin-top:2px">Ref: ' + escH(e.referencias) + '</div>' : '')
            + '<div style="margin-top:6px;font-size:11px;font-weight:600;color:' + color + '">' + estado + '</div>'
            + (e.es_parcial == 1 ? '<div style="font-size:11px;font-weight:700;color:#c2410c">Parcial ' + e.salida_piezas_count + '/' + e.salida_piezas_total + '</div>' : '')
            + '<div style="font-size:11px;color:#64748b">' + fmtKg(e.peso_kg) + '</div>'
            + '</div>';
          inst.infoWindow.setContent(content);
          inst.infoWindow.open(inst.map, marker);
        });

        inst.map.fitBounds(bounds);
      });
    });
  }

  // ── Ubicación en vivo de las unidades (ProTrack365, ver api/gps_proxy.php) ──
  var _iconoCamion = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(
    '<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24">'
    + '<circle cx="12" cy="12" r="11" fill="#0f172a" stroke="#fff" stroke-width="2"/>'
    + '<text x="12" y="17" text-anchor="middle" font-size="13">🚚</text>'
    + '</svg>'
  );

  var GPS_INTERVALO_MS = 15000;
  var _proximaActualizacionGPS = Date.now() + GPS_INTERVALO_MS;

  function tickGpsPolling() {
    actualizarUbicacionesUnidades();
    _proximaActualizacionGPS = Date.now() + GPS_INTERVALO_MS;
  }

  function tickGpsTimerDisplay() {
    var restante = Math.max(0, Math.round((_proximaActualizacionGPS - Date.now()) / 1000));
    _rutas.forEach(function(r) {
      if (r.estado !== 'en_ruta') return;
      var el = document.getElementById('gps-timer-' + r.id);
      if (el) el.textContent = '📡 Actualiza cada 15s · próxima en ' + restante + 's';
    });
  }

  async function actualizarUbicacionesUnidades() {
    // Solo hay algo que actualizar si hay al menos una ruta "en_ruta" con su mapa ya dibujado
    var rutasActivas = _rutas.filter(function(r) { return r.estado === 'en_ruta' && _mapas[r.id]; });
    if (!rutasActivas.length) return;

    var r;
    try {
      r = await fetch(API_GPS + '?accion=ubicacion&unidad=ambas');
    } catch (e) { return; }
    if (!r.ok) return;
    var data = await r.json();
    if (!data.ok) return;

    rutasActivas.forEach(function(ruta) {
      var u = data.unidades[ruta.unidad];
      var inst = _mapas[ruta.id];
      if (!inst) return;
      if (!u || !u.valido) return;

      var pos = { lat: u.lat, lng: u.lng };
      if (!inst.truckMarker) {
        inst.truckMarker = new google.maps.Marker({
          position: pos,
          map: inst.map,
          icon: { url: _iconoCamion, scaledSize: new google.maps.Size(34, 34), anchor: new google.maps.Point(17, 17) },
          zIndex: 1000,
        });
        inst.truckMarker.addListener('click', function() {
          var mins = u.tiempo ? Math.max(0, Math.round((Date.now() - u.tiempo) / 60000)) : null;
          var content = '<div style="font-family:-apple-system,sans-serif;font-size:12px">'
            + '<div style="font-weight:700">' + escH(UNIDAD_LABEL[ruta.unidad] || ruta.unidad) + '</div>'
            + '<div>' + (u.velocidad > 0 ? u.velocidad + ' km/h' : 'Detenido') + '</div>'
            + (mins !== null ? '<div style="color:#64748b">Hace ' + mins + ' min</div>' : '')
            + '</div>';
          inst.infoWindow.setContent(content);
          inst.infoWindow.open(inst.map, inst.truckMarker);
        });
      } else {
        inst.truckMarker.setPosition(pos);
      }

      actualizarLineaSiguiente(ruta, inst, pos);
    });
  }

  // Distancia en metros entre 2 puntos lat/lng (fórmula haversine)
  function distMetros(a, b) {
    var R = 6371000;
    var dLat = (b.lat - a.lat) * Math.PI / 180;
    var dLng = (b.lng - a.lng) * Math.PI / 180;
    var s = Math.sin(dLat/2)*Math.sin(dLat/2)
      + Math.cos(a.lat*Math.PI/180)*Math.cos(b.lat*Math.PI/180)*Math.sin(dLng/2)*Math.sin(dLng/2);
    return R * 2 * Math.atan2(Math.sqrt(s), Math.sqrt(1-s));
  }

  var RADIO_LLEGADA_M = 300; // qué tan cerca debe estar el camión de una parada para considerarla "llegada"

  // Con la ruta ya iniciada (en_ruta), en vez de pintar los ~20 tramos de un jalón, se traza
  // solo la línea del camión (posición GPS en vivo) hacia la SIGUIENTE parada pendiente. Cuando
  // el camión entra al radio de la parada actual, se avanza sola a la línea de la siguiente —
  // así nunca hay más de 1 línea encima del mapa mientras la ruta está en curso.
  function actualizarLineaSiguiente(ruta, inst, truckPos) {
    if (ruta.estado !== 'en_ruta' || !inst.pinPos) return;

    var pendientes = ruta.entregas.filter(function(e) { return e.entrega_estado === 'pendiente'; });
    if (!pendientes.length) {
      if (inst.lineaSiguiente) { inst.lineaSiguiente.setMap(null); inst.lineaSiguiente = null; }
      return;
    }

    if (inst.destinoIdx == null) inst.destinoIdx = 0;
    if (inst.destinoIdx >= pendientes.length) inst.destinoIdx = pendientes.length - 1;

    // ¿Ya llegó a la parada actual? Avanza a la siguiente (puede saltar varias si el GPS
    // solo actualiza cada 15s y el camión ya pasó de largo por más de una).
    var destino = pendientes[inst.destinoIdx];
    while (destino && inst.pinPos[destino.id] && distMetros(truckPos, inst.pinPos[destino.id]) <= RADIO_LLEGADA_M
           && inst.destinoIdx < pendientes.length - 1) {
      inst.destinoIdx++;
      destino = pendientes[inst.destinoIdx];
    }

    var destinoPos = destino && inst.pinPos[destino.id];
    if (!destinoPos) return; // aún no se geocodificó ese punto

    if (inst._trazandoLinea) return; // evitar pedir 2 rutas encimadas si el poll anterior sigue en vuelo
    inst._trazandoLinea = true;

    var svc = new google.maps.DirectionsService();
    svc.route({
      origin: truckPos, destination: destinoPos,
      travelMode: google.maps.TravelMode.DRIVING,
    }, function(result, status) {
      inst._trazandoLinea = false;
      if (status !== 'OK') return;
      if (inst.lineaSiguiente) inst.lineaSiguiente.setMap(null);
      var path = [];
      (result.routes[0].legs[0].steps || []).forEach(function(step) {
        path = path.concat(step.path || []);
      });
      inst.lineaSiguiente = new google.maps.Polyline({
        path: path, map: inst.map,
        strokeColor: '#2563eb', strokeOpacity: 0.95, strokeWeight: 5,
      });
    });
  }

  function dibujarMapa(ruta_id) {
    if (!window.google || !window.google.maps) return;
    var ruta = null;
    for (var i = 0; i < _rutas.length; i++) {
      if (_rutas[i].id == ruta_id) { ruta = _rutas[i]; break; }
    }
    if (!ruta || !ruta.entregas || !ruta.entregas.length) return;

    var mapEl = document.getElementById('mapa-ruta-' + ruta_id);
    if (!mapEl) return;

    var origenLatLng = PLANTA_LATLNG;
    var inst = _mapas[ruta_id];
    if (!inst) {
      var map = new google.maps.Map(mapEl, {
        zoom: 11, center: origenLatLng,
        disableDefaultUI: true, zoomControl: true,
      });
      inst = { map: map, markers: [], infoWindow: new google.maps.InfoWindow(), renderer: null, tramos: [] };
      _mapas[ruta_id] = inst;
    }

    var pendientes = ruta.entregas.filter(function(e) { return e.entrega_estado !== 'entregado'; });
    if (!pendientes.length) { toast('Todas las entregas ya están completadas'); return; }

    var waypoints = pendientes.map(function(e) {
      var addr = [e.direccion, e.colonia, e.ciudad].filter(Boolean).join(', ');
      return { location: addr || 'Monterrey, Nuevo León', stopover: true };
    });

    // Limpiar tramos anteriores si existen
    (inst.tramos || []).forEach(function(p) { p.setMap(null); });
    inst.tramos = [];

    var svc = new google.maps.DirectionsService();

    svc.route({
      origin: origenLatLng,
      destination: origenLatLng,
      waypoints: waypoints,
      travelMode: google.maps.TravelMode.DRIVING,
    }, function(result, status) {
      if (status !== 'OK') { toast('No se pudo trazar la ruta en el mapa', true); return; }
      // Un Polyline por tramo (planta→parada1, parada1→parada2, ...) en vez de una sola línea
      // de un color con DirectionsRenderer, para diferenciar cada punto de la ruta.
      var legs = result.routes[0].legs || [];
      legs.forEach(function(leg, idx) {
        var path = [];
        (leg.steps || []).forEach(function(step) {
          path = path.concat(step.path || []);
        });
        var color = PALETA_TRAMOS[idx % PALETA_TRAMOS.length];
        var poly = new google.maps.Polyline({
          path: path, map: inst.map,
          strokeColor: color, strokeOpacity: 0.9, strokeWeight: 4,
        });
        inst.tramos.push(poly);
      });
    });
  }

  // Inicializar Places Autocomplete — API Nueva (PlaceAutocompleteElement). Se probó la API
  // clásica (google.maps.places.Autocomplete) como alternativa para el bug de teclado en Brave,
  // pero el proyecto solo tiene activada "Places API (New)" en Google Cloud (la clásica ya no
  // está disponible para proyectos nuevos desde marzo 2025), así que se revirtió. El elemento
  // se destruye al cerrar el modal (ver cerrarModalAsignar) para no apilar cajas de búsqueda
  // duplicadas al reabrir.
  //
  // Restricción geográfica a NL/Tamaulipas/Coahuila: Google no permite filtrar por lista de
  // estados directamente, así que se usa locationRestriction con un rectángulo que cubre los
  // 3 estados (más estricto que locationBias, que solo prioriza sin excluir). El rectángulo
  // roza ligeramente estados vecinos (Zacatecas, San Luis Potosí, Veracruz), así que se valida
  // también el estado real de la selección y se rechaza si cae fuera de los 3.
  var ESTADOS_PERMITIDOS = ['Nuevo León', 'Tamaulipas', 'Coahuila', 'Coahuila de Zaragoza'];
  var BOUNDS_NL_TAMPS_COAH = { south: 22.0, west: -103.9, north: 29.9, east: -97.0 };

  function initAutocomplete() {
    if (!window.google || !window.google.maps || !window.google.maps.places) return;
    var configs = [
      { inputId: 'as-dir', prefix: 'as' },
      { inputId: 'ed-dir', prefix: 'ed' },
    ];
    configs.forEach(function(cfg) {
      var input = document.getElementById(cfg.inputId);
      if (!input || input._acInit) return;
      input._acInit = true;

      var ac = new google.maps.places.PlaceAutocompleteElement({
        // PlaceAutocompleteElement (API nueva) usa includedRegionCodes, no componentRestrictions
        // (eso es de la API clásica google.maps.places.Autocomplete) — con el nombre equivocado
        // Google lo ignoraba en silencio y por eso seguían saliendo direcciones de EE.UU.
        includedRegionCodes: ['mx'],
        locationRestriction: new google.maps.LatLngBounds(
          { lat: BOUNDS_NL_TAMPS_COAH.south, lng: BOUNDS_NL_TAMPS_COAH.west },
          { lat: BOUNDS_NL_TAMPS_COAH.north, lng: BOUNDS_NL_TAMPS_COAH.east }
        ),
      });
      ac.setAttribute('color-scheme', 'light');
      ac.style.colorScheme = 'light';

      input.style.display = 'none';
      input.parentNode.insertBefore(ac, input.nextSibling);
      input._acEl = ac;

      ac.style.width = '100%';
      ac.style.fontSize = '14px';

      ac.addEventListener('gmp-select', function(e) {
        var prediction = e.placePrediction || e.oh;
        if (!prediction || !prediction.toPlace) return;
        var place = prediction.toPlace();
        place.fetchFields({ fields: ['formattedAddress', 'addressComponents'] }).then(function() {
          var numero = '', calle = '', estado = '';
          var coloniaPorTipo = {};
          var ciudadPorTipo   = {};
          var comps = place.addressComponents || [];
          comps.forEach(function(c) {
            var types = c.types || [];
            if (types.indexOf('street_number') >= 0) numero = c.longText;
            if (types.indexOf('route') >= 0) calle = c.longText;
            if (types.indexOf('administrative_area_level_1') >= 0) estado = c.longText;
            types.forEach(function(t) {
              if (['sublocality_level_1','sublocality','neighborhood'].indexOf(t) >= 0) coloniaPorTipo[t] = c.longText;
              if (['locality','administrative_area_level_2'].indexOf(t) >= 0) ciudadPorTipo[t] = c.longText;
            });
          });

          if (estado && ESTADOS_PERMITIDOS.indexOf(estado) < 0) {
            toast('Esa dirección está en ' + estado + ' — solo se aceptan Nuevo León, Tamaulipas y Coahuila', true);
            input.value = '';
            return;
          }

          var colonia = coloniaPorTipo.sublocality_level_1 || coloniaPorTipo.sublocality || coloniaPorTipo.neighborhood || '';
          var ciudad  = ciudadPorTipo.locality || ciudadPorTipo.administrative_area_level_2 || '';

          var dirCompleta = calle + (numero ? ' ' + numero : '');
          input.value = dirCompleta || place.formattedAddress || '';
          if (colonia) document.getElementById(cfg.prefix + '-col').value = colonia;
          if (ciudad)  document.getElementById(cfg.prefix + '-ciu').value = ciudad;
        });
      });
    });
  }

  // Init
  setFecha(new Date().toISOString().slice(0,10));
  // Refresco de ubicación en vivo — cada 15s, en línea con el cache de ~12s del backend (api/gps_lib.php)
  setInterval(tickGpsPolling, GPS_INTERVALO_MS);
  setInterval(tickGpsTimerDisplay, 1000);

  return {
    setFecha:setFecha, irHoy:irHoy, cambiarDia:cambiarDia,
    abrirModalRuta:abrirModalRuta, cerrarModalRuta:cerrarModalRuta, guardarRuta:guardarRuta,
    abrirModalAsignar:abrirModalAsignar, cerrarModalAsignar:cerrarModalAsignar, guardarAsignacion:guardarAsignacion,
    abrirEditDir:abrirEditDir, cerrarEditDir:cerrarEditDir, guardarEditDir:guardarEditDir,
    mover:mover, quitar:quitar, eliminarRuta:eliminarRuta, finalizarRuta:finalizarRuta, planificar:planificar,
    recalcularEta:recalcularEta,
    dibujarPines:dibujarPines,
    initAutocomplete:initAutocomplete, renderTramos:renderTramos,
    actualizarContPiezas:actualizarContPiezas, toggleTodasPiezas:toggleTodasPiezas,
    togglePartidaExpand:togglePartidaExpand, togglePartida:togglePartida, onPiezaCb:onPiezaCb,
    abrirModalCarga:abrirModalCarga, cerrarModalCarga:cerrarModalCarga,
  };
})();
</script>
<?php if ($maps_key): ?>
<script>
(function() {
  function despuesDeCargar() {
    if (window.LR) {
      LR.initAutocomplete();
      // Dibujar pins si ya hay rutas cargadas
      setTimeout(function() {
        if (window._lrRutas) {
          window._lrRutas.forEach(function(r) {
            if (r.entregas && r.entregas.length) LR.dibujarPines(r.id);
          });
        }
      }, 300);
    }
  }
  // El SPA no limpia scripts entre navegaciones: si el módulo se vuelve a abrir sin recargar
  // la página, cargar el script de Maps otra vez duplica el registro de sus custom elements
  // (PlaceAutocompleteElement) y rompe el autocomplete de forma intermitente. Si la API ya
  // está cargada (o se está cargando), no se vuelve a inyectar el script.
  if (window.google && window.google.maps && window.google.maps.places) {
    despuesDeCargar();
    return;
  }
  if (window._lrMapsLoading) {
    window._lrMapsLoading.push(despuesDeCargar);
    return;
  }
  window._lrMapsLoading = [despuesDeCargar];
  var script = document.createElement('script');
  script.src = 'https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($maps_key) ?>&libraries=places&v=beta&loading=async';
  script.async = true;
  script.defer = true;
  script.onload = function() {
    var callbacks = window._lrMapsLoading || [];
    window._lrMapsLoading = null;
    callbacks.forEach(function(cb) { cb(); });
  };
  document.head.appendChild(script);
})();
</script>
<?php endif; ?>