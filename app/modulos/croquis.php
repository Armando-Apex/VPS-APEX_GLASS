<?php
// ============================================================
//  APEX GLASS - Módulo: Croquis Técnicos
//  Archivo: app/modulos/croquis.php
//  Se incluye al final de modulos/cotizacion.php
//  Solo visible cuando cotizacion_id > 0
// ============================================================
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user         = requirePermiso('ver_ordenes');
$_rol         = $user['rol'];
$id_cot_php   = (int)($_GET['id'] ?? 0);
$puede_editar = in_array($_rol, ['dir_admin','dueno','comercial','administracion']);

if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=cotizacion&id='.$id_cot_php); exit;
}
if (!$id_cot_php) {
    echo '<div style="padding:24px;color:#94a3b8;font-size:13px">Guarda la cotización primero para agregar croquis.</div>';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<style>
/* ── Contenedor principal ─────────────────────────────────── */
.cq-wrap { padding: 0 24px 32px; max-width: 1400px; margin: 0 auto; }
.cq-card { background: white; border-radius: 14px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; }
.cq-card-title { font-size: 15px; font-weight: 800; color: #1e293b; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
/* ── Lista de croquis guardados ───────────────────────────── */
.cq-list { display: flex; flex-direction: column; gap: 10px; }
.cq-item { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; cursor: pointer; transition: border-color .15s, background .15s; }
.cq-item:hover { border-color: #2563eb; background: #f8faff; }
.cq-item-info { flex: 1; }
.cq-item-title { font-size: 13px; font-weight: 700; color: #1e293b; }
.cq-item-sub { font-size: 11px; color: #64748b; margin-top: 3px; }
.cq-item-tags { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 6px; }
.cq-tag { font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 99px; }
.cq-tag-tp  { background: #dbeafe; color: #1e40af; }
.cq-tag-ta  { background: #f3e8ff; color: #6b21a8; }
.cq-tag-rs  { background: #dcfce7; color: #15803d; }
.cq-tag-ct  { background: #fef9c3; color: #713f12; }
.cq-item-actions { display: flex; gap: 6px; }
.cq-btn-pdf { background: #16a34a; color: white; border: none; border-radius: 7px; padding: 6px 12px; font-size: 11px; font-weight: 700; cursor: pointer; }
.cq-btn-edit { background: #2563eb; color: white; border: none; border-radius: 7px; padding: 6px 12px; font-size: 11px; font-weight: 700; cursor: pointer; }
.cq-btn-del { background: #fee2e2; color: #dc2626; border: none; border-radius: 7px; padding: 6px 10px; font-size: 13px; cursor: pointer; }
.cq-empty { color: #94a3b8; font-size: 13px; padding: 20px 0; text-align: center; }
.cq-btn-nuevo { background: #2563eb; color: white; border: none; border-radius: 8px; padding: 9px 18px; font-size: 13px; font-weight: 700; cursor: pointer; }
.cq-btn-nuevo:hover { background: #1d4ed8; }
/* ── Constructor ──────────────────────────────────────────── */
.cq-constructor { display: none; }
.cq-constructor.open { display: block; }
.cq-editor { display: grid; grid-template-columns: 325px 1fr; gap: 0; border: 1.5px solid #e2e8f0; border-radius: 12px; overflow: hidden; height: 620px; }
.cq-panel { border-right: 1.5px solid #e2e8f0; padding: 20px; display: flex; flex-direction: column; gap: 18px; overflow-y: auto; background: #fafafa; }
.cq-panel-sec { border-top: 1px solid #e2e8f0; padding-top: 15px; }
.cq-panel-title { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; margin-bottom: 9px; }
/* Forma btns */
.cq-shape-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; }
.cq-shape-btn { border: 1.5px solid #e2e8f0; border-radius: 9px; padding: 10px 6px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 6px; background: white; font-size: 12px; color: #64748b; transition: all .12s; }
.cq-shape-btn:hover { background: #f1f5f9; }
.cq-shape-btn.active { border-color: #2563eb; color: #2563eb; background: #eff6ff; }
.cq-shape-btn svg { width: 48px; height: 34px; }
/* Fields */
.cq-field-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.cq-field-label { font-size: 13px; color: #64748b; min-width: 76px; }
.cq-fi { flex: 1; height: 34px; border: 1.5px solid #e2e8f0; border-radius: 7px; padding: 0 9px; font-size: 14px; background: white; color: #1e293b; }
.cq-fi:focus { outline: none; border-color: #2563eb; }
.cq-unit { font-size: 12px; color: #94a3b8; min-width: 22px; }
/* Canteado */
.cq-canteo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.cq-canteo-btn { border: 1px solid #e2e8f0; border-radius: 6px; padding: 7px 4px; font-size: 12px; cursor: pointer; background: white; color: #64748b; text-align: center; font-weight: 500; }
.cq-canteo-btn.on { background: #fef9c3; border-color: #ca8a04; color: #713f12; }
/* Elementos palette */
.cq-chip { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 13px; font-size: 13px; cursor: grab; background: #f1f5f9; color: #374151; display: flex; align-items: center; gap: 9px; user-select: none; margin-bottom: 7px; font-weight: 500; }
.cq-chip:hover { background: #e2e8f0; }
.cq-chip:active { cursor: grabbing; }
.cq-hint { font-size: 11px; color: #94a3b8; line-height: 1.5; margin-top: 4px; }
/* Lista elementos colocados */
.cq-placed-item { font-size: 11px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 5px 9px; display: flex; justify-content: space-between; align-items: center; color: #64748b; margin-top: 5px; }
.cq-del-btn { background: none; border: none; cursor: pointer; color: #94a3b8; font-size: 15px; padding: 0 2px; }
.cq-del-btn:hover { color: #dc2626; }
/* Canvas area */
.cq-canvas-area { display: flex; flex-direction: column; background: #f8fafc; overflow: hidden; }
.cq-canvas-toolbar { padding: 11px 18px; background: white; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.cq-canvas-hint { font-size: 12px; color: #94a3b8; }
.cq-canvas-btns { display: flex; gap: 7px; }
.cq-btn-guardar { background: #16a34a; color: white; border: none; border-radius: 8px; padding: 7px 16px; font-size: 13px; font-weight: 700; cursor: pointer; }
.cq-btn-guardar:hover { background: #15803d; }
.cq-btn-cancel-editor { background: #f1f5f9; color: #374151; border: none; border-radius: 8px; padding: 7px 16px; font-size: 13px; cursor: pointer; }
.cq-svg-wrap { flex: 1; overflow: hidden; position: relative; background: #f8fafc; cursor: grab; }
.cq-svg-wrap.panning { cursor: grabbing; }
#cq-svg { position: absolute; top: 0; left: 0; background: white; border: 1px solid #e2e8f0; border-radius: 3px; transform-origin: top left; }
.cq-nota-bar { padding: 9px 16px; border-top: 1px solid #e2e8f0; background: white; display: flex; gap: 8px; align-items: center; }
.cq-nota-input { flex: 1; height: 32px; border: 1.5px solid #e2e8f0; border-radius: 7px; padding: 0 10px; font-size: 13px; color: #1e293b; }
.cq-nota-input:focus { outline: none; border-color: #2563eb; }
/* Partida selector */
.cq-partida-sel { width: 100%; height: 38px; border: 1.5px solid #e2e8f0; border-radius: 9px; padding: 0 12px; font-size: 14px; margin-bottom: 4px; color: #1e293b; }
.cq-partida-sel:focus { outline: none; border-color: #2563eb; }
/* Modal editar elemento */
.cq-modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 300; align-items: center; justify-content: center; }
.cq-modal-bg.open { display: flex; }
.cq-modal { background: white; border-radius: 14px; padding: 22px; width: 300px; box-shadow: 0 8px 32px rgba(0,0,0,.18); }
.cq-modal h3 { font-size: 14px; font-weight: 700; margin-bottom: 14px; display: flex; align-items: center; gap: 7px; }
.cq-modal-btns { display: flex; gap: 7px; margin-top: 14px; justify-content: flex-end; }
.cq-mbtn-del    { border: none; border-radius: 7px; padding: 6px 12px; font-size: 12px; cursor: pointer; background: #fee2e2; color: #991b1b; }
.cq-mbtn-cancel { border: 1px solid #e2e8f0; border-radius: 7px; padding: 6px 12px; font-size: 12px; cursor: pointer; background: none; color: #374151; }
.cq-mbtn-ok     { border: none; border-radius: 7px; padding: 6px 12px; font-size: 12px; cursor: pointer; background: #2563eb; color: white; }
/* Controles zoom */
.cq-zoom-btn { width: 32px; height: 32px; border: 1.5px solid #e2e8f0; border-radius: 7px; cursor: pointer; background: white; font-size: 18px; line-height: 1; display: flex; align-items: center; justify-content: center; color: #374151; font-weight: 500; }
.cq-zoom-btn:hover { background: #f1f5f9; border-color: #cbd5e1; }
.cq-zoom-label { font-size: 13px; font-family: monospace; color: #475569; font-weight: 600; min-width: 44px; text-align: center; }
.cq-zoom-fit { height: 32px; padding: 0 10px; border: 1.5px solid #e2e8f0; border-radius: 7px; cursor: pointer; background: white; font-size: 12px; color: #64748b; font-weight: 600; }
.cq-zoom-fit:hover { background: #f1f5f9; border-color: #cbd5e1; }
</style>

<div class="cq-wrap">
  <div class="cq-card">
    <div class="cq-card-title">
      &#9999;&#65039; Croquis Técnicos
      <?php if ($puede_editar): ?>
      <button class="cq-btn-nuevo" onclick="CroquisMod._nuevo()">+ Nuevo croquis</button>
      <?php endif; ?>
    </div>

    <!-- Lista de croquis guardados -->
    <div id="cq-lista">
      <div class="cq-empty">Cargando...</div>
    </div>

    <!-- Constructor (oculto por defecto) -->
    <div class="cq-constructor" id="cq-constructor">
      <hr style="border:none;border-top:1.5px solid #e2e8f0;margin-bottom:20px">

      <!-- Selector de partida -->
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
        <label style="font-size:12px;font-weight:700;color:#374151;min-width:90px">Partida:</label>
        <select class="cq-partida-sel" id="cq-partida-sel" style="max-width:260px"></select>
        <span style="font-size:11px;color:#94a3b8" id="cq-medidas-hint"></span>
      </div>

      <div class="cq-editor">
        <!-- Panel izquierdo -->
        <div class="cq-panel">
          <div>
            <div class="cq-panel-title">Forma base</div>
            <div class="cq-shape-grid">
              <button class="cq-shape-btn active" onclick="CroquisMod._setForma('rect')" id="cq-btn-rect">
                <svg viewBox="0 0 38 28"><rect x="3" y="3" width="32" height="22" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                Rectángulo
              </button>
              <button class="cq-shape-btn" onclick="CroquisMod._setForma('corte')" id="cq-btn-corte">
                <svg viewBox="0 0 38 28"><polygon points="13,3 35,3 35,25 3,25 3,13" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                Esq. cortada
              </button>
              <button class="cq-shape-btn" onclick="CroquisMod._setForma('L')" id="cq-btn-L">
                <svg viewBox="0 0 38 28"><polygon points="3,3 20,3 20,12 35,12 35,25 3,25" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                Forma L
              </button>
              <button class="cq-shape-btn" onclick="CroquisMod._setForma('trap')" id="cq-btn-trap">
                <svg viewBox="0 0 38 28"><polygon points="9,3 29,3 35,25 3,25" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                Trapecio
              </button>
              <button class="cq-shape-btn" onclick="CroquisMod._setForma('poligono')" id="cq-btn-poligono">
                <svg viewBox="0 0 38 28"><polygon points="6,4 28,3 35,14 24,25 3,20" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
                Forma libre
              </button>
            </div>
          </div>

          <div class="cq-panel-sec">
            <div class="cq-panel-title">Medidas</div>
            <div id="cq-medidas-rect-fields">
            <div class="cq-field-row"><span class="cq-field-label">Ancho</span><input class="cq-fi" type="number" id="cq-ancho" value="800" min="50" max="3000" oninput="CroquisMod._redraw()"><span class="cq-unit">mm</span></div>
            <div class="cq-field-row"><span class="cq-field-label">Alto</span><input class="cq-fi" type="number" id="cq-alto" value="600" min="50" max="3000" oninput="CroquisMod._redraw()"><span class="cq-unit">mm</span></div>
            </div>
            <div id="cq-extra-corte" style="display:none">
              <div class="cq-field-row"><span class="cq-field-label">Corte X</span><input class="cq-fi" type="number" id="cq-corte-x" value="150" min="10" oninput="CroquisMod._redraw()"><span class="cq-unit">mm</span></div>
              <div class="cq-field-row"><span class="cq-field-label">Corte Y</span><input class="cq-fi" type="number" id="cq-corte-y" value="150" min="10" oninput="CroquisMod._redraw()"><span class="cq-unit">mm</span></div>
            </div>
            <div id="cq-extra-L" style="display:none">
              <div class="cq-field-row"><span class="cq-field-label">Corte W</span><input class="cq-fi" type="number" id="cq-l-cw" value="200" min="10" oninput="CroquisMod._redraw()"><span class="cq-unit">mm</span></div>
              <div class="cq-field-row"><span class="cq-field-label">Corte H</span><input class="cq-fi" type="number" id="cq-l-ch" value="200" min="10" oninput="CroquisMod._redraw()"><span class="cq-unit">mm</span></div>
            </div>
            <div id="cq-extra-trap" style="display:none">
              <div class="cq-field-row"><span class="cq-field-label">Base menor</span><input class="cq-fi" type="number" id="cq-trap-b" value="500" min="10" oninput="CroquisMod._redraw()"><span class="cq-unit">mm</span></div>
            </div>
            <div id="cq-extra-poligono" style="display:none">
              <div class="cq-hint" style="margin-top:0">Haz clic para ir colocando los puntos <b>en orden, siguiendo el contorno</b> (no saltes de un lado a otro). El punto naranja es el último — la línea punteada te muestra hacia dónde se conectará el siguiente clic. Arrastra un punto para moverlo, doble clic para borrarlo, o usa "Deshacer último" si te equivocaste.</div>
              <div style="font-size:12px;color:#374151;font-weight:700;margin:8px 0 6px" id="cq-pol-contador">0 puntos</div>
              <div style="display:flex;gap:6px">
                <button class="cq-zoom-fit" style="flex:1" onclick="CroquisMod._deshacerPunto()" id="cq-pol-deshacer">Deshacer último</button>
                <button class="cq-zoom-fit" style="flex:1" onclick="CroquisMod._resetPoligono()">Reiniciar</button>
              </div>
              <div style="margin-top:6px">
                <button class="cq-zoom-fit" style="width:100%" onclick="CroquisMod._cerrarPoligono()" id="cq-pol-cerrar">Cerrar forma</button>
              </div>
              <div id="cq-pol-segmentos" style="margin-top:8px"></div>
            </div>
          </div>

          <div class="cq-panel-sec">
            <div class="cq-panel-title">Canteado</div>
            <div class="cq-canteo-grid">
              <button class="cq-canteo-btn" id="cq-c-sup" onclick="CroquisMod._toggleCanteo('sup')">Superior</button>
              <button class="cq-canteo-btn" id="cq-c-der" onclick="CroquisMod._toggleCanteo('der')">Derecho</button>
              <button class="cq-canteo-btn" id="cq-c-inf" onclick="CroquisMod._toggleCanteo('inf')">Inferior</button>
              <button class="cq-canteo-btn" id="cq-c-izq" onclick="CroquisMod._toggleCanteo('izq')">Izquierdo</button>
            </div>
          </div>

          <div class="cq-panel-sec">
            <div class="cq-panel-title">Elementos especiales</div>
            <div class="cq-chip" draggable="true" ondragstart="CroquisMod._startDrag('tp',event)">
              <span class="cq-tag cq-tag-tp">TP</span> Taladro pasado
            </div>
            <div class="cq-chip" draggable="true" ondragstart="CroquisMod._startDrag('ta',event)">
              <span class="cq-tag cq-tag-ta">TA</span> Taladro avellanado
            </div>
            <div class="cq-chip" draggable="true" ondragstart="CroquisMod._startDrag('rs',event)">
              <span class="cq-tag cq-tag-rs">RS</span> Resaque
            </div>
            <div class="cq-hint">Arrastra al vidrio &middot; Doble clic edita</div>
            <div id="cq-placed-list"></div>
          </div>
        </div>

        <!-- Canvas derecho -->
        <div class="cq-canvas-area">
          <div class="cq-canvas-toolbar" style="position:sticky;top:0;z-index:10;">
            <span class="cq-canvas-hint">Vista previa en escala proporcional</span>
            <div style="display:flex;align-items:center;gap:5px;margin-right:8px">
              <button class="cq-zoom-btn" onclick="CroquisMod._zoomOut()">&#8722;</button>
              <span id="cq-zoom-label" class="cq-zoom-label">100%</span>
              <button class="cq-zoom-btn" onclick="CroquisMod._zoomIn()">&#43;</button>
              <button class="cq-zoom-fit" onclick="CroquisMod._zoomReset()">Fit</button>
            </div>
            <div class="cq-canvas-btns">
              <button class="cq-btn-cancel-editor" onclick="CroquisMod._cancelar()">Cancelar</button>
              <button class="cq-btn-guardar" onclick="CroquisMod._guardar()">&#128190; Guardar croquis</button>
            </div>
          </div>
          <div class="cq-svg-wrap" id="cq-svg-wrap">
            <svg id="cq-svg"
              ondragover="event.preventDefault()"
              ondrop="CroquisMod._onDrop(event)"
              onmousemove="CroquisMod._onMouseMove(event)"
              onmouseup="CroquisMod._onMouseUp(event)"
              onmouseleave="CroquisMod._onSvgMouseLeave()"
              onclick="CroquisMod._onSvgClick(event)">
            </svg>
          </div>
          <div class="cq-nota-bar">
            <span style="font-size:12px;color:#94a3b8">Nota:</span>
            <input class="cq-nota-input" type="text" id="cq-nota" placeholder="Notas de fabricación..." oninput="CroquisMod._redraw()">
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal editar elemento -->
<div class="cq-modal-bg" id="cq-edit-modal">
  <div class="cq-modal">
    <h3 id="cq-modal-title">Editar elemento</h3>
    <div id="cq-modal-fields"></div>
    <div class="cq-modal-btns">
      <button class="cq-mbtn-del" onclick="CroquisMod._deleteEditing()">&#128465; Eliminar</button>
      <button class="cq-mbtn-cancel" onclick="CroquisMod._closeModal()">Cancelar</button>
      <button class="cq-mbtn-ok" onclick="CroquisMod._saveEditing()">Guardar</button>
    </div>
  </div>
</div>

<script>
var CroquisMod = (function() {

var API_CROQUIS  = '../api/croquis.php';
var ID_COT       = <?= $id_cot_php ?>;
var PUEDE_EDITAR = <?= $puede_editar ? 'true' : 'false' ?>;

// Estado del constructor
var _forma       = 'rect';
var _canteo      = {sup:false,der:false,inf:false,izq:false};
var _elementos   = [];
var _elemCounter = 0;
var _draggingChip = null;
var _draggingElem = null;
var _dragOffX = 0, _dragOffY = 0;
var _editingId = null;
var _editingCroquis = null; // null = nuevo
var _partidas  = []; // {num_partida, ancho, alto, cristal}

// Polígono libre
var _poligonoPuntos  = []; // [{x,y}] en mm, sistema CAD (0,0 abajo-izq)
var _poligonoCerrado = false;
var _draggingVert    = null; // índice del punto que se arrastra
var _justDraggedVert = false; // evita que el click tras soltar agregue un punto extra
var _polPreviewPt    = null; // {x,y} mm — posición actual del mouse, para línea de previsualización
var POL_SNAP = 10; // mm

var SVG_W = 450, SVG_H = 340, PAD = 44;
var EL_BASE = 50; // px reservados debajo del vidrio para cota ancho + "Eje X" antes de que empiecen las filas de elementos
var _zoom = 1.0; // 1.0 = fit, escala multiplicadora sobre el fit base
var _panX = 0, _panY = 0;      // offset de pan en px
var _panning = false, _panStartX = 0, _panStartY = 0, _panStartOX = 0, _panStartOY = 0;

// ── Inicializar ────────────────────────────────────────────────────────────
function init() {
  cargarCroquis();
  cargarPartidas();
  _initPan();
}

async function cargarCroquis() {
  try {
    var res  = await fetch(API_CROQUIS + '?cotizacion_id=' + ID_COT);
    var data = await res.json();
    renderLista(data.data || []);
  } catch(e) {
    document.getElementById('cq-lista').innerHTML = '<div class="cq-empty">Error al cargar croquis</div>';
  }
}

async function cargarPartidas() {
  try {
    var res  = await fetch('../api/cotizaciones.php?id=' + ID_COT);
    var data = await res.json();
    _partidas = (data && data.partidas) ? data.partidas.map(function(p) {
      return { num: p.num_partida, ancho: p.ancho, alto: p.alto, cristal: p.cristal_nombre || p.cristal_etiqueta || 'Partida ' + p.num_partida };
    }) : [];
    renderPartidaSel();
  } catch(e) {}
}

function renderPartidaSel() {
  var sel = document.getElementById('cq-partida-sel');
  if (!sel) return;
  if (!_partidas.length) {
    sel.innerHTML = '<option value="">Sin partidas</option>';
    return;
  }
  sel.innerHTML = _partidas.map(function(p) {
    return '<option value="' + p.num + '">' + 'Partida ' + p.num + ' — ' + escHtml(p.cristal) + ' ' + p.ancho + '×' + p.alto + ' mm</option>';
  }).join('');
  sel.onchange = function() { actualizarMedidasDesdePartida(); };
  actualizarMedidasDesdePartida();
}

function actualizarMedidasDesdePartida() {
  var sel = document.getElementById('cq-partida-sel');
  if (!sel) return;
  var num = parseInt(sel.value);
  var p   = _partidas.find(function(x){ return x.num === num; });
  if (!p) return;
  document.getElementById('cq-ancho').value = p.ancho;
  document.getElementById('cq-alto').value  = p.alto;
  document.getElementById('cq-medidas-hint').textContent = p.ancho + ' × ' + p.alto + ' mm';
  _redraw();
}

// ── Render lista guardada ──────────────────────────────────────────────────
function renderLista(croquis) {
  var cont = document.getElementById('cq-lista');
  if (!croquis.length) {
    cont.innerHTML = '<div class="cq-empty">Sin croquis — agrega uno con el botón de arriba</div>';
    return;
  }
  cont.innerHTML = '<div class="cq-list">' + croquis.map(function(c) {
    var elems = c.elementos || [];
    var canteoObj = c.canteo || {};
    var tags = '';
    var tpCnt = elems.filter(function(e){ return e.tipo==='tp'; }).length;
    var taCnt = elems.filter(function(e){ return e.tipo==='ta'; }).length;
    var rsCnt = elems.filter(function(e){ return e.tipo==='rs'; }).length;
    var ladosCanteo = [];
    if (canteoObj.sup) ladosCanteo.push('Sup');
    if (canteoObj.inf) ladosCanteo.push('Inf');
    if (canteoObj.izq) ladosCanteo.push('Izq');
    if (canteoObj.der) ladosCanteo.push('Der');
    if (tpCnt) tags += '<span class="cq-tag cq-tag-tp">' + tpCnt + ' TP</span>';
    if (taCnt) tags += '<span class="cq-tag cq-tag-ta">' + taCnt + ' TA</span>';
    if (rsCnt) tags += '<span class="cq-tag cq-tag-rs">' + rsCnt + ' RS</span>';
    if (ladosCanteo.length) tags += '<span class="cq-tag cq-tag-ct">Cant: ' + ladosCanteo.join('+') + '</span>';

    var formaLabel = {rect:'Rectángulo',corte:'Esq. cortada',L:'Forma L',trap:'Trapecio',poligono:'Forma libre'}[c.forma] || c.forma;
    var editBtn    = PUEDE_EDITAR ? '<button class="cq-btn-edit" onclick="CroquisMod._editarCroquis(' + c.id + ')">&#9999;&#65039; Editar</button>' : '';
    var delBtn     = PUEDE_EDITAR ? '<button class="cq-btn-del" onclick="CroquisMod._eliminarCroquis(' + c.id + ')">&#128465;</button>' : '';

    return '<div class="cq-item">'
      + '<div class="cq-item-info">'
      + '<div class="cq-item-title">Partida ' + c.num_partida + ' &mdash; ' + formaLabel + ' &mdash; ' + c.ancho_mm + ' &times; ' + c.alto_mm + ' mm</div>'
      + (c.notas ? '<div class="cq-item-sub">' + escHtml(c.notas) + '</div>' : '')
      + '<div class="cq-item-tags">' + tags + '</div>'
      + '</div>'
      + '<div class="cq-item-actions">'
      + '<button class="cq-btn-pdf" onclick="CroquisMod._imprimirCroquis(' + c.id + ')">&#128424;&#65039; PDF</button>'
      + editBtn + delBtn
      + '</div>'
      + '</div>';
  }).join('') + '</div>';
}

// ── Abrir constructor nuevo ────────────────────────────────────────────────
function abrirConstructor(croquis) {
  _zoom = 1.0; _panX = 0; _panY = 0;
  _updateZoomLabel();
  _editingCroquis = croquis || null;
  _forma    = (croquis && croquis.forma)  || 'rect';
  _canteo   = (croquis && croquis.canteo) || {sup:false,der:false,inf:false,izq:false};
  _elementos = (croquis && croquis.elementos) ? croquis.elementos.map(function(e, i) {
    return Object.assign({}, e, {id: ++_elemCounter});
  }) : [];

  var pfIn = (croquis && croquis.params_forma) || {};
  _poligonoPuntos  = (_forma === 'poligono' && Array.isArray(pfIn.puntos)) ? pfIn.puntos.slice() : [];
  _poligonoCerrado = _forma === 'poligono' && _poligonoPuntos.length >= 3;
  _polPreviewPt    = null;

  // Resetear UI forma
  ['rect','corte','L','trap','poligono'].forEach(function(f) {
    document.getElementById('cq-btn-' + f).classList.remove('active');
  });
  document.getElementById('cq-btn-' + _forma).classList.add('active');
  document.getElementById('cq-extra-corte').style.display    = _forma==='corte'?'block':'none';
  document.getElementById('cq-extra-L').style.display        = _forma==='L'?'block':'none';
  document.getElementById('cq-extra-trap').style.display     = _forma==='trap'?'block':'none';
  document.getElementById('cq-extra-poligono').style.display = _forma==='poligono'?'block':'none';
  document.getElementById('cq-medidas-rect-fields').style.display = _forma==='poligono'?'none':'block';
  _renderPolInfo();

  // Medidas
  if (croquis) {
    document.getElementById('cq-ancho').value = croquis.ancho_mm;
    document.getElementById('cq-alto').value  = croquis.alto_mm;
    var pf = croquis.params_forma || {};
    if (pf['corte-x']) document.getElementById('cq-corte-x').value = pf['corte-x'];
    if (pf['corte-y']) document.getElementById('cq-corte-y').value = pf['corte-y'];
    if (pf['l-cw'])    document.getElementById('cq-l-cw').value    = pf['l-cw'];
    if (pf['l-ch'])    document.getElementById('cq-l-ch').value    = pf['l-ch'];
    if (pf['trap-b'])  document.getElementById('cq-trap-b').value  = pf['trap-b'];
    document.getElementById('cq-nota').value  = croquis.notas || '';

    // Seleccionar la partida correspondiente
    var sel = document.getElementById('cq-partida-sel');
    if (sel) sel.value = croquis.num_partida;
    document.getElementById('cq-medidas-hint').textContent = croquis.ancho_mm + ' × ' + croquis.alto_mm + ' mm';
  } else {
    actualizarMedidasDesdePartida();
    document.getElementById('cq-nota').value = '';
  }

  // Canteado UI
  ['sup','inf','izq','der'].forEach(function(l) {
    document.getElementById('cq-c-' + l).classList.toggle('on', !!_canteo[l]);
  });

  _renderPlacedList();
  _redraw();
  document.getElementById('cq-constructor').classList.add('open');
  document.getElementById('cq-constructor').scrollIntoView({behavior:'smooth', block:'start'});
  // centrar después de que el DOM esté visible
  setTimeout(function() {
    var wrap = document.getElementById('cq-svg-wrap');
    if (wrap) {
      _panX = Math.max(0, (wrap.offsetWidth  - SVG_W) / 2);
      _panY = Math.max(0, (wrap.offsetHeight - SVG_H) / 2);
      _redraw();
    }
  }, 50);
}

function _nuevo() { abrirConstructor(null); }

async function _editarCroquis(id) {
  try {
    var res  = await fetch(API_CROQUIS + '?id=' + id);
    var data = await res.json();
    if (data.ok) abrirConstructor(data.data);
    else alert('Error al cargar croquis');
  } catch(e) { alert('Error de conexión'); }
}

async function _eliminarCroquis(id) {
  if (!confirm('¿Eliminar este croquis?')) return;
  try {
    var res  = await fetch(API_CROQUIS, { method:'DELETE', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id:id}) });
    var data = await res.json();
    if (data.ok) cargarCroquis();
    else alert(data.error || 'Error al eliminar');
  } catch(e) { alert('Error de conexión'); }
}

function _imprimirCroquis(id) {
  window.open('../app/imprimir_croquis.php?id=' + id, '_blank');
}

function _cancelar() {
  document.getElementById('cq-constructor').classList.remove('open');
  _editingCroquis = null;
  _elementos = [];
}

// ── Guardar croquis ────────────────────────────────────────────────────────
async function _guardar() {
  var sel = document.getElementById('cq-partida-sel');
  var numPartida = sel ? parseInt(sel.value) : 0;
  if (!numPartida) { alert('Selecciona una partida'); return; }

  if (_forma === 'poligono' && (!_poligonoCerrado || _poligonoPuntos.length < 3)) {
    alert('Cierra la forma libre (mínimo 3 puntos) antes de guardar');
    return;
  }

  var ancho = parseFloat(document.getElementById('cq-ancho').value) || 0;
  var alto  = parseFloat(document.getElementById('cq-alto').value)  || 0;
  if (!ancho || !alto) { alert('Ingresa las medidas'); return; }

  var pf = {};
  if (_forma === 'corte')    { pf['corte-x'] = +document.getElementById('cq-corte-x').value; pf['corte-y'] = +document.getElementById('cq-corte-y').value; }
  if (_forma === 'L')        { pf['l-cw']    = +document.getElementById('cq-l-cw').value;    pf['l-ch']    = +document.getElementById('cq-l-ch').value; }
  if (_forma === 'trap')     { pf['trap-b']  = +document.getElementById('cq-trap-b').value; }
  if (_forma === 'poligono') { pf['puntos']  = _poligonoPuntos; }

  var payload = {
    cotizacion_id: ID_COT,
    num_partida:   numPartida,
    forma:         _forma,
    ancho_mm:      ancho,
    alto_mm:       alto,
    params_forma:  Object.keys(pf).length ? pf : null,
    elementos:     _elementos.map(function(e) { var c = Object.assign({}, e); delete c.id; return c; }),
    canteo:        _canteo,
    notas:         document.getElementById('cq-nota').value.trim() || null,
  };

  try {
    var esEdicion = !!(_editingCroquis && _editingCroquis.id);
    if (esEdicion) {
      payload.id = _editingCroquis.id;
      var res  = await fetch(API_CROQUIS, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
      var data = await res.json();
    } else {
      var res  = await fetch(API_CROQUIS, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
      var data = await res.json();
    }
    if (data.ok) {
      _cancelar();
      cargarCroquis();
    } else {
      alert(data.error || 'Error al guardar');
    }
  } catch(e) { alert('Error de conexión'); }
}

// ── Forma ──────────────────────────────────────────────────────────────────
function _setForma(f) {
  _forma = f;
  ['rect','corte','L','trap','poligono'].forEach(function(x) { document.getElementById('cq-btn-'+x).classList.remove('active'); });
  document.getElementById('cq-btn-'+f).classList.add('active');
  document.getElementById('cq-extra-corte').style.display    = f==='corte'?'block':'none';
  document.getElementById('cq-extra-L').style.display        = f==='L'?'block':'none';
  document.getElementById('cq-extra-trap').style.display     = f==='trap'?'block':'none';
  document.getElementById('cq-extra-poligono').style.display = f==='poligono'?'block':'none';
  document.getElementById('cq-medidas-rect-fields').style.display = f==='poligono'?'none':'block';
  _renderPolInfo();
  _redraw();
}

function _renderPolInfo() {
  var cont = document.getElementById('cq-pol-contador');
  var cerrarBtn = document.getElementById('cq-pol-cerrar');
  var deshacerBtn = document.getElementById('cq-pol-deshacer');
  if (!cont) return;
  cont.textContent = _poligonoPuntos.length + ' punto' + (_poligonoPuntos.length===1?'':'s') + (_poligonoCerrado ? ' — forma cerrada' : '');
  if (cerrarBtn) cerrarBtn.disabled = _poligonoCerrado || _poligonoPuntos.length < 3;
  if (deshacerBtn) deshacerBtn.disabled = _poligonoCerrado || !_poligonoPuntos.length;
  var segCont = document.getElementById('cq-pol-segmentos');
  if (!segCont) return;
  if (_poligonoPuntos.length < 2) { segCont.innerHTML = ''; return; }
  var n = _poligonoPuntos.length;
  var html = '';
  for (var i = 0; i < n; i++) {
    if (i === n-1 && !_poligonoCerrado) break;
    var a = _poligonoPuntos[i], b = _poligonoPuntos[(i+1) % n];
    var dist = Math.round(Math.hypot(b.x-a.x, b.y-a.y));
    html += '<div class="cq-placed-item"><span style="flex:1">Lado ' + (i+1) + '</span><span>' + dist + ' mm</span></div>';
  }
  segCont.innerHTML = html;
}

function _resetPoligono() {
  _poligonoPuntos = [];
  _poligonoCerrado = false;
  _polPreviewPt = null;
  _renderPolInfo();
  _redraw();
}

function _deshacerPunto() {
  if (_poligonoCerrado || !_poligonoPuntos.length) return;
  _poligonoPuntos.pop();
  _renderPolInfo();
  _redraw();
}

function _cerrarPoligono() {
  if (_poligonoPuntos.length < 3) { alert('Se necesitan al menos 3 puntos'); return; }
  _poligonoCerrado = true;
  _polPreviewPt = null;
  _normalizarPoligono();
  _renderPolInfo();
  _redraw();
}

// Recorre el bounding box y desplaza los puntos para que min(x)=0, min(y)=0;
// actualiza los campos ancho/alto que ya usa el resto del editor (grid, cotas, eje X/Y)
function _normalizarPoligono() {
  if (!_poligonoPuntos.length) return;
  var minX = Math.min.apply(null, _poligonoPuntos.map(function(p){ return p.x; }));
  var minY = Math.min.apply(null, _poligonoPuntos.map(function(p){ return p.y; }));
  if (minX !== 0 || minY !== 0) {
    _poligonoPuntos = _poligonoPuntos.map(function(p) { return {x: p.x - minX, y: p.y - minY}; });
  }
  var maxX = Math.max.apply(null, _poligonoPuntos.map(function(p){ return p.x; }));
  var maxY = Math.max.apply(null, _poligonoPuntos.map(function(p){ return p.y; }));
  document.getElementById('cq-ancho').value = Math.max(50, maxX);
  document.getElementById('cq-alto').value  = Math.max(50, maxY);
  document.getElementById('cq-medidas-hint').textContent = Math.max(50,maxX) + ' × ' + Math.max(50,maxY) + ' mm (calculado)';
}

function _toggleCanteo(lado) {
  _canteo[lado] = !_canteo[lado];
  document.getElementById('cq-c-'+lado).classList.toggle('on', _canteo[lado]);
  _redraw();
}

// ── Drag & drop ────────────────────────────────────────────────────────────
function _startDrag(tipo, event) {
  _draggingChip = tipo;
  event.dataTransfer.effectAllowed = 'copy';
}

function _onDrop(event) {
  event.preventDefault();
  if (!_draggingChip) return;
  var pt = _svgPoint(event);
  var o  = _getOrigin();
  var mm = _toMM(pt.x, pt.y, o);
  mm.x = Math.max(0, Math.min(mm.x, o.ancho));
  mm.y = Math.max(0, Math.min(mm.y, o.alto));
  var el = {id: ++_elemCounter, tipo: _draggingChip, x: mm.x, y: mm.y};
  if (_draggingChip === 'tp') { el.d  = 13; }
  if (_draggingChip === 'ta') { el.de = 20; el.di = 20; }
  if (_draggingChip === 'rs') { el.w  = 120; el.h = 40; }
  _elementos.push(el);
  _draggingChip = null;
  _renderPlacedList();
  _redraw();
}

function _onMouseMove(event) {
  if (_draggingVert !== null) {
    var pt = _svgPoint(event);
    var o  = _getOrigin();
    var mm = _toMM(pt.x, pt.y, o);
    mm.x = _snap(Math.max(0, Math.min(mm.x, o.ancho)));
    mm.y = _snap(Math.max(0, Math.min(mm.y, o.alto)));
    _poligonoPuntos[_draggingVert] = mm;
    _redraw(); _renderPolInfo();
    return;
  }
  if (_draggingElem) {
    var pt2 = _svgPoint(event);
    var o2  = _getOrigin();
    var mm2 = _toMM(pt2.x - _dragOffX, pt2.y - _dragOffY, o2);
    mm2.x = Math.max(0, Math.min(mm2.x, o2.ancho));
    mm2.y = Math.max(0, Math.min(mm2.y, o2.alto));
    var el = _elementos.find(function(e){ return e.id === _draggingElem; });
    if (el) { el.x = mm2.x; el.y = mm2.y; _redraw(); _renderPlacedList(); }
    return;
  }
  if (_forma === 'poligono' && !_poligonoCerrado && _poligonoPuntos.length) {
    var pt3 = _svgPoint(event);
    var o3  = _getOrigin();
    var mm3 = _toMM(pt3.x, pt3.y, o3);
    mm3.x = Math.max(0, Math.min(mm3.x, o3.ancho));
    mm3.y = Math.max(0, Math.min(mm3.y, o3.alto));
    _polPreviewPt = mm3;
    _redraw();
  }
}

function _onMouseUp() {
  _draggingElem = null;
  if (_draggingVert !== null) {
    _draggingVert = null;
    _justDraggedVert = true;
    if (_poligonoCerrado) _normalizarPoligono();
    setTimeout(function(){ _justDraggedVert = false; }, 0);
  }
}

function _onSvgMouseLeave() {
  _polPreviewPt = null;
  _onMouseUp();
  _redraw();
}

function _snap(mm) { return Math.round(mm / POL_SNAP) * POL_SNAP; }

function _onSvgClick(event) {
  if (_forma !== 'poligono' || _poligonoCerrado || _justDraggedVert) return;
  if (event.target && event.target.dataset && event.target.dataset.vert) return;
  var pt = _svgPoint(event);
  var o  = _getOrigin();
  var mm = _toMM(pt.x, pt.y, o);
  mm.x = _snap(Math.max(0, Math.min(mm.x, o.ancho)));
  mm.y = _snap(Math.max(0, Math.min(mm.y, o.alto)));
  _poligonoPuntos.push(mm);
  _renderPolInfo();
  _redraw();
}

function _vertMouseDown(idx, event) {
  event.stopPropagation(); event.preventDefault();
  _draggingVert = idx;
}

function _vertDblClick(idx, event) {
  event.stopPropagation();
  _poligonoPuntos.splice(idx, 1);
  if (_poligonoPuntos.length < 3) _poligonoCerrado = false;
  _renderPolInfo();
  _redraw();
}

function _elemMouseDown(elemId, event) {
  event.stopPropagation(); event.preventDefault();
  var el = _elementos.find(function(e){ return e.id === elemId; });
  if (!el) return;
  var pt = _svgPoint(event);
  var o  = _getOrigin();
  var ep = _toPX(el.x, el.y, o);
  _draggingElem = elemId;
  _dragOffX = pt.x - ep.x;
  _dragOffY = pt.y - ep.y;
}

function _elemDblClick(elemId, event) {
  event.stopPropagation();
  _openEditModal(elemId);
}

// ── Modal editar elemento ──────────────────────────────────────────────────
function _openEditModal(id) {
  _editingId = id;
  var el = _elementos.find(function(e){ return e.id === id; });
  if (!el) return;
  var titles = {tp:'Taladro pasado', ta:'Taladro avellanado', rs:'Resaque'};
  var tagCls  = {tp:'cq-tag-tp', ta:'cq-tag-ta', rs:'cq-tag-rs'};
  document.getElementById('cq-modal-title').innerHTML = '<span class="cq-tag '+tagCls[el.tipo]+'" style="margin-right:5px">'+el.tipo.toUpperCase()+'</span>'+titles[el.tipo];
  var f = _mField('Pos X (mm)','ced-x',el.x) + _mField('Pos Y (mm)','ced-y',el.y);
  if (el.tipo==='tp') f += _mSelectTP('ced-d', el.d);
  if (el.tipo==='ta') f += _mSelectTA('ced-de', el.de, 'Ø ext (mm)') + _mSelectTA('ced-di', el.di, 'Ø int (mm)');
  if (el.tipo==='rs') f += _mField('Ancho (mm)','ced-w',el.w) + _mField('Alto (mm)','ced-h',el.h);
  document.getElementById('cq-modal-fields').innerHTML = f;
  document.getElementById('cq-edit-modal').classList.add('open');
}

function _mField(label, id, val) {
  return '<div class="cq-field-row" style="margin-bottom:8px"><span class="cq-field-label" style="min-width:120px;font-size:11px">'+label+'</span><input class="cq-fi" type="number" id="'+id+'" value="'+val+'"></div>';
}

function _mSelectTP(id, val) {
  var opts = [10,13,16,19,20].map(function(v){
    return '<option value="'+v+'"'+(v===val?' selected':'')+'>Ø '+v+' mm</option>';
  }).join('');
  return '<div class="cq-field-row" style="margin-bottom:8px"><span class="cq-field-label" style="min-width:120px;font-size:11px">Diámetro</span><select class="cq-fi" id="'+id+'">'+opts+'</select></div>';
}

function _mSelectTA(id, val, label) {
  var opts = [];
  for (var v=20; v<=29; v++) {
    opts.push('<option value="'+v+'"'+(v===val?' selected':'')+'>Ø '+v+' mm</option>');
  }
  return '<div class="cq-field-row" style="margin-bottom:8px"><span class="cq-field-label" style="min-width:120px;font-size:11px">'+label+'</span><select class="cq-fi" id="'+id+'">'+opts.join('')+'</select></div>';
}

function _closeModal() {
  document.getElementById('cq-edit-modal').classList.remove('open');
  _editingId = null;
}

function _saveEditing() {
  var el = _elementos.find(function(e){ return e.id === _editingId; });
  if (!el) { _closeModal(); return; }
  el.x = +document.getElementById('ced-x').value || 0;
  el.y = +document.getElementById('ced-y').value || 0;
  if (el.tipo==='tp') el.d  = +document.getElementById('ced-d').value  || 12;
  if (el.tipo==='ta') { el.de = +document.getElementById('ced-de').value || 20; el.di = +document.getElementById('ced-di').value || 10; }
  if (el.tipo==='rs') { el.w  = +document.getElementById('ced-w').value  || 120; el.h = +document.getElementById('ced-h').value || 40; }
  _closeModal();
  _renderPlacedList();
  _redraw();
}

function _deleteEditing() {
  _elementos = _elementos.filter(function(e){ return e.id !== _editingId; });
  _closeModal();
  _renderPlacedList();
  _redraw();
}

function _renderPlacedList() {
  var list = document.getElementById('cq-placed-list');
  if (!_elementos.length) { list.innerHTML = ''; return; }
  list.innerHTML = _elementos.map(function(e) {
    var tc = e.tipo==='tp'?'cq-tag-tp':e.tipo==='ta'?'cq-tag-ta':'cq-tag-rs';
    var label = e.tipo==='tp' ? 'X:'+e.x+' Y:'+e.y+' Ø'+e.d
              : e.tipo==='ta' ? 'X:'+e.x+' Y:'+e.y+' Ø'+e.de+'/'+e.di
              : 'X:'+e.x+' Y:'+e.y+' '+e.w+'×'+e.h;
    return '<div class="cq-placed-item"><span class="cq-tag '+tc+'" style="margin-right:4px">'+e.tipo.toUpperCase()+'</span><span style="flex:1;margin:0 4px">'+label+'</span><button class="cq-del-btn" onclick="CroquisMod._removeElem('+e.id+')">×</button></div>';
  }).join('');
}

function _removeElem(id) {
  _elementos = _elementos.filter(function(e){ return e.id !== id; });
  _renderPlacedList();
  _redraw();
}

// ── Canvas / SVG ───────────────────────────────────────────────────────────
function _svgPoint(event) {
  var svg  = document.getElementById('cq-svg');
  var rect = svg.getBoundingClientRect();
  // compensar zoom: getBoundingClientRect ya incluye el scale,
  // pero necesitamos coordenadas dentro del SVG sin escalar
  return {
    x: (event.clientX - rect.left) / _zoom,
    y: (event.clientY - rect.top)  / _zoom
  };
}

function _getOrigin() {
  var ancho = Math.max(50, +document.getElementById('cq-ancho').value || 800);
  var alto  = Math.max(50, +document.getElementById('cq-alto').value  || 600);
  // ML generoso: cota alto (22) + eje Y (14) + etiqueta rotada (~20) + margen = 80
  // MB/MR crecen con el número de elementos especiales: cada uno usa su propia fila/columna de cota,
  // reservando primero el espacio fijo de la cota ancho + etiqueta "Eje X" (EL_BASE) para no chocar con ellas
  var extraFilas = Math.max(0, _elementos.length - 1);
  var ML=80, MR=80+extraFilas*14, MT=20;
  var MB = _elementos.length ? Math.max(90, EL_BASE + _elementos.length*14 + 16) : 90;
  var sc = Math.min((SVG_W-ML-MR)/ancho, (SVG_H-MT-MB)/alto);
  var gw = ancho*sc; var gh = alto*sc;
  // centrar el vidrio dentro del área disponible
  var ox = ML + (SVG_W-ML-MR-gw)/2;
  var oy = MT + (SVG_H-MT-MB-gh)/2;
  return {ox:ox, oy:oy, gw:gw, gh:gh, sc:sc, ancho:ancho, alto:alto, canvW:SVG_W, canvH:SVG_H};
}

function _zoomIn() {
  _zoom = Math.min(4.0, Math.round((_zoom + 0.25)*100)/100);
  _updateZoomLabel(); _redraw();
}
function _zoomOut() {
  _zoom = Math.max(0.25, Math.round((_zoom - 0.25)*100)/100);
  _updateZoomLabel(); _redraw();
}
function _zoomReset() {
  _zoom = 1.0;
  var wrap = document.getElementById('cq-svg-wrap');
  _panX = wrap ? Math.max(0, (wrap.offsetWidth  - SVG_W) / 2) : 0;
  _panY = wrap ? Math.max(0, (wrap.offsetHeight - SVG_H) / 2) : 0;
  _updateZoomLabel(); _redraw();
}
function _updateZoomLabel() {
  var lbl = document.getElementById('cq-zoom-label');
  if (lbl) lbl.textContent = Math.round(_zoom*100) + '%';
}

// ── Pan: click & drag en el wrap ──────────────────────────────────────────
function _initPan() {
  var wrap = document.getElementById('cq-svg-wrap');
  if (!wrap || wrap._panInit) return;
  wrap._panInit = true;
  wrap.addEventListener('mousedown', function(ev) {
    if (_draggingElem || _draggingChip) return;
    if (ev.button !== 0) return;
    _panning = true;
    _panStartX = ev.clientX; _panStartY = ev.clientY;
    _panStartOX = _panX;    _panStartOY = _panY;
    wrap.classList.add('panning');
    ev.preventDefault();
  });
  window.addEventListener('mousemove', function(ev) {
    if (!_panning) return;
    _panX = _panStartOX + (ev.clientX - _panStartX);
    _panY = _panStartOY + (ev.clientY - _panStartY);
    var svg = document.getElementById('cq-svg');
    if (svg) svg.style.transform = 'scale('+_zoom+') translate('+(_panX/_zoom)+'px,'+(_panY/_zoom)+'px)';
  });
  window.addEventListener('mouseup', function() {
    if (_panning) { _panning = false; wrap && wrap.classList.remove('panning'); }
  });
}

// px SVG ↔ mm CAD (0,0 = esquina inferior izquierda, Y arriba)
function _toPX(mmX, mmY, o) {
  return { x: o.ox + mmX*o.sc, y: o.oy + o.gh - mmY*o.sc };
}
function _toMM(pxX, pxY, o) {
  return {
    x: Math.round((pxX - o.ox) / o.sc),
    y: Math.round((o.oy + o.gh - pxY) / o.sc)
  };
}

function _buildPath(ox, oy, gw, gh) {
  var ancho = +document.getElementById('cq-ancho').value || 800;
  var alto  = +document.getElementById('cq-alto').value  || 600;
  var sc    = gw / ancho;
  if (_forma === 'rect') {
    return 'M'+ox+' '+oy+' L'+(ox+gw)+' '+oy+' L'+(ox+gw)+' '+(oy+gh)+' L'+ox+' '+(oy+gh)+' Z';
  }
  if (_forma === 'corte') {
    var cx = Math.min(+document.getElementById('cq-corte-x').value||150, ancho*.4)*sc;
    var cy = Math.min(+document.getElementById('cq-corte-y').value||150, alto*.4)*sc;
    return 'M'+(ox+cx)+' '+oy+' L'+(ox+gw)+' '+oy+' L'+(ox+gw)+' '+(oy+gh)+' L'+ox+' '+(oy+gh)+' L'+ox+' '+(oy+cy)+' Z';
  }
  if (_forma === 'L') {
    var lw = Math.min(+document.getElementById('cq-l-cw').value||200, ancho*.7)*sc;
    var lh = Math.min(+document.getElementById('cq-l-ch').value||200, alto*.7)*sc;
    return 'M'+ox+' '+oy+' L'+(ox+lw)+' '+oy+' L'+(ox+lw)+' '+(oy+lh)+' L'+(ox+gw)+' '+(oy+lh)+' L'+(ox+gw)+' '+(oy+gh)+' L'+ox+' '+(oy+gh)+' Z';
  }
  if (_forma === 'trap') {
    var tb  = Math.min(+document.getElementById('cq-trap-b').value||500, ancho-10)*sc;
    var off = (gw-tb)/2;
    return 'M'+(ox+off)+' '+oy+' L'+(ox+gw-off)+' '+oy+' L'+(ox+gw)+' '+(oy+gh)+' L'+ox+' '+(oy+gh)+' Z';
  }
  if (_forma === 'poligono') {
    if (_poligonoPuntos.length < 3) return '';
    var d = '';
    _poligonoPuntos.forEach(function(p, i) {
      var px = ox + p.x*sc, py = (oy+gh) - p.y*sc;
      d += (i===0 ? 'M'+px+' '+py+' ' : 'L'+px+' '+py+' ');
    });
    return d + 'Z';
  }
  return '';
}

function _redraw() {
  var svg = document.getElementById('cq-svg');
  if (!svg) return;
  var o   = _getOrigin();
  var ox=o.ox, oy=o.oy, gw=o.gw, gh=o.gh, sc=o.sc;
  var ancho = o.ancho, alto = o.alto;
  var nota  = document.getElementById('cq-nota').value;
  var uid   = 'cq'+Math.floor(Math.random()*99999);
  var out   = '';

  // tamaño fijo del SVG — zoom y pan via CSS transform
  svg.setAttribute('width',  SVG_W);
  svg.setAttribute('height', SVG_H);
  svg.style.transform = 'scale('+_zoom+') translate('+(_panX/_zoom)+'px,'+(_panY/_zoom)+'px)';
  svg.style.width  = SVG_W + 'px';
  svg.style.height = SVG_H + 'px';

  // ── Escala tipográfica fija — zoom se aplica solo vía CSS transform ──
  var fz     = 9;    // font-size base
  var fzSm   = 8;    // font-size pequeño
  var sw     = '0.9'; // stroke-width líneas cota
  var tk     = 4;    // tick size
  var cOff   = 24;   // offset cota ancho
  var cxOff  = 22;   // offset cota alto
  var arwSz  = 3.5;  // tamaño flecha eje
  var arwLen = 7;    // largo flecha eje
  var lblW   = 36;   // ancho rect etiqueta mm
  var lblH   = 11;   // alto rect etiqueta mm
  var lblWEj = 36;   // ancho etiqueta eje
  var rotW   = 10;   // ancho rect rotado
  var rotH   = 36;   // alto rect rotado

  // origen inferior izquierdo en px (sistema CAD: Y arriba)
  var oyBottom = oy + gh;

  // convierte mm CAD a px SVG
  function toPX(mmX, mmY) {
    return { x: ox + mmX*sc, y: oyBottom - mmY*sc };
  }

  // ── Grid y fondo ────────────────────────────────────────────
  out += '<defs><pattern id="g'+uid+'" width="'+(10*sc)+'" height="'+(10*sc)+'" patternUnits="userSpaceOnUse" x="'+ox+'" y="'+oy+'"><path d="M '+(10*sc)+' 0 L 0 0 0 '+(10*sc)+'" fill="none" stroke="#e2e8f0" stroke-width="0.4"/></pattern></defs>';
  out += '<rect width="'+o.canvW+'" height="'+o.canvH+'" fill="white"/>';

  // ── Forma ────────────────────────────────────────────────────
  var sp = _buildPath(ox,oy,gw,gh);
  out += '<clipPath id="cl'+uid+'"><path d="'+sp+'"/></clipPath>';
  out += '<rect x="'+ox+'" y="'+oy+'" width="'+gw+'" height="'+gh+'" fill="url(#g'+uid+')" clip-path="url(#cl'+uid+')"/>';
  out += '<path d="'+sp+'" fill="#e8f4fd" fill-opacity="0.65" stroke="#2563eb" stroke-width="1.5"/>';

  // ── Vértices del polígono libre ────────────────────────────────
  if (_forma === 'poligono') {
    var n = _poligonoPuntos.length;
    for (var pi = 0; pi < n; pi++) {
      var p1 = _poligonoPuntos[pi];
      var v1 = {x: ox + p1.x*sc, y: (oy+gh) - p1.y*sc};
      if (pi < n-1) {
        var p2 = _poligonoPuntos[pi+1];
        var v2 = {x: ox + p2.x*sc, y: (oy+gh) - p2.y*sc};
        out += '<line x1="'+v1.x+'" y1="'+v1.y+'" x2="'+v2.x+'" y2="'+v2.y+'" stroke="#2563eb" stroke-width="1.3" stroke-dasharray="'+(_poligonoCerrado?'0':'4,3')+'"/>';
      } else if (_poligonoCerrado && n > 2) {
        var p0 = _poligonoPuntos[0];
        var v0 = {x: ox + p0.x*sc, y: (oy+gh) - p0.y*sc};
        out += '<line x1="'+v1.x+'" y1="'+v1.y+'" x2="'+v0.x+'" y2="'+v0.y+'" stroke="#2563eb" stroke-width="1.3"/>';
      }
      // último punto colocado = naranja (de aquí sale el siguiente segmento); primero = verde; resto = azul
      var esUltimo  = !_poligonoCerrado && pi === n-1 && n > 0;
      var vCol = esUltimo ? '#f59e0b' : (pi===0 ? '#16a34a' : '#2563eb');
      out += '<circle data-vert="'+pi+'" cx="'+v1.x+'" cy="'+v1.y+'" r="7" fill="'+vCol+'" stroke="white" stroke-width="1.5" style="cursor:'+(_draggingVert===pi?'grabbing':'grab')+'" onmousedown="CroquisMod._vertMouseDown('+pi+',event)" ondblclick="CroquisMod._vertDblClick('+pi+',event)"/>';
      out += '<text x="'+v1.x+'" y="'+v1.y+'" text-anchor="middle" dominant-baseline="central" font-size="8" font-weight="700" fill="white" font-family="monospace" style="pointer-events:none">'+(pi+1)+'</text>';
    }
    // ── Línea de previsualización: del último punto al cursor ──────
    if (!_poligonoCerrado && n > 0 && _polPreviewPt) {
      var last = _poligonoPuntos[n-1];
      var lv = {x: ox + last.x*sc, y: (oy+gh) - last.y*sc};
      var pv = {x: ox + _polPreviewPt.x*sc, y: (oy+gh) - _polPreviewPt.y*sc};
      out += '<line x1="'+lv.x+'" y1="'+lv.y+'" x2="'+pv.x+'" y2="'+pv.y+'" stroke="#f59e0b" stroke-width="1.1" stroke-dasharray="3,3" opacity="0.85"/>';
      out += '<circle cx="'+pv.x+'" cy="'+pv.y+'" r="3" fill="#f59e0b" opacity="0.6"/>';
      var pdist = Math.round(Math.hypot(_polPreviewPt.x-last.x, _polPreviewPt.y-last.y));
      out += '<text x="'+((lv.x+pv.x)/2)+'" y="'+((lv.y+pv.y)/2-6)+'" text-anchor="middle" font-size="8" font-weight="700" fill="#f59e0b" font-family="monospace" style="pointer-events:none">'+pdist+' mm</text>';
    }
  }

  // ── Canteado ─────────────────────────────────────────────────
  var cs = 'stroke:#f59e0b;stroke-width:3;stroke-linecap:round';
  if (_canteo.sup) out += '<line x1="'+ox+'" y1="'+oy+'" x2="'+(ox+gw)+'" y2="'+oy+'" style="'+cs+'"/>';
  if (_canteo.inf) out += '<line x1="'+ox+'" y1="'+oyBottom+'" x2="'+(ox+gw)+'" y2="'+oyBottom+'" style="'+cs+'"/>';
  if (_canteo.izq) out += '<line x1="'+ox+'" y1="'+oy+'" x2="'+ox+'" y2="'+oyBottom+'" style="'+cs+'"/>';
  if (_canteo.der) out += '<line x1="'+(ox+gw)+'" y1="'+oy+'" x2="'+(ox+gw)+'" y2="'+oyBottom+'" style="'+cs+'"/>';

  // ── Cota ancho (debajo del vidrio) ────────────────────────────
  out += '<line x1="'+ox+'" y1="'+oyBottom+'" x2="'+ox+'" y2="'+(oyBottom+cOff+2)+'" stroke="#94a3b8" stroke-width="0.5" stroke-dasharray="2,2"/>';
  out += '<line x1="'+(ox+gw)+'" y1="'+oyBottom+'" x2="'+(ox+gw)+'" y2="'+(oyBottom+cOff+2)+'" stroke="#94a3b8" stroke-width="0.5" stroke-dasharray="2,2"/>';
  out += '<line x1="'+ox+'" y1="'+(oyBottom+cOff)+'" x2="'+(ox+gw)+'" y2="'+(oyBottom+cOff)+'" stroke="#475569" stroke-width="'+sw+'"/>';
  out += '<line x1="'+ox+'" y1="'+(oyBottom+cOff-tk)+'" x2="'+ox+'" y2="'+(oyBottom+cOff+tk)+'" stroke="#475569" stroke-width="'+sw+'"/>';
  out += '<line x1="'+(ox+gw)+'" y1="'+(oyBottom+cOff-tk)+'" x2="'+(ox+gw)+'" y2="'+(oyBottom+cOff+tk)+'" stroke="#475569" stroke-width="'+sw+'"/>';
  out += '<rect x="'+(ox+gw/2-lblW/2)+'" y="'+(oyBottom+cOff-lblH/2)+'" width="'+lblW+'" height="'+lblH+'" fill="white"/>';
  out += '<text x="'+(ox+gw/2)+'" y="'+(oyBottom+cOff+fz/2-1)+'" text-anchor="middle" font-size="'+fz+'" font-weight="700" fill="#1e293b" font-family="monospace">'+ancho+' mm</text>';

  // ── Cota alto (izquierda del vidrio) ──────────────────────────
  out += '<line x1="'+ox+'" y1="'+oy+'" x2="'+(ox-cxOff-2)+'" y2="'+oy+'" stroke="#94a3b8" stroke-width="0.5" stroke-dasharray="2,2"/>';
  out += '<line x1="'+ox+'" y1="'+oyBottom+'" x2="'+(ox-cxOff-2)+'" y2="'+oyBottom+'" stroke="#94a3b8" stroke-width="0.5" stroke-dasharray="2,2"/>';
  out += '<line x1="'+(ox-cxOff)+'" y1="'+oy+'" x2="'+(ox-cxOff)+'" y2="'+oyBottom+'" stroke="#475569" stroke-width="'+sw+'"/>';
  out += '<line x1="'+(ox-cxOff-tk)+'" y1="'+oy+'" x2="'+(ox-cxOff+tk)+'" y2="'+oy+'" stroke="#475569" stroke-width="'+sw+'"/>';
  out += '<line x1="'+(ox-cxOff-tk)+'" y1="'+oyBottom+'" x2="'+(ox-cxOff+tk)+'" y2="'+oyBottom+'" stroke="#475569" stroke-width="'+sw+'"/>';
  out += '<rect x="'+(ox-cxOff-rotW/2)+'" y="'+(oy+gh/2-rotH/2)+'" width="'+rotW+'" height="'+rotH+'" fill="white"/>';
  out += '<text x="'+(ox-cxOff)+'" y="'+(oy+gh/2)+'" text-anchor="middle" font-size="'+fz+'" font-weight="700" fill="#1e293b" font-family="monospace" transform="rotate(-90,'+(ox-cxOff)+','+(oy+gh/2)+')">'+alto+' mm</text>';

  // ── Eje X — cota dedicada debajo de la de ancho, con flecha ──
  var ejXY = oyBottom + cOff + 14;
  out += '<line x1="'+ox+'" y1="'+ejXY+'" x2="'+(ox+gw)+'" y2="'+ejXY+'" stroke="#dc2626" stroke-width="1.1"/>';
  out += '<line x1="'+ox+'" y1="'+(ejXY-tk)+'" x2="'+ox+'" y2="'+(ejXY+tk)+'" stroke="#dc2626" stroke-width="1.1"/>';
  out += '<polygon points="'+(ox+gw)+','+(ejXY-arwSz)+' '+(ox+gw+arwLen)+','+ejXY+' '+(ox+gw)+','+(ejXY+arwSz)+'" fill="#dc2626"/>';
  out += '<rect x="'+(ox+gw/2-lblWEj/2)+'" y="'+(ejXY-lblH/2)+'" width="'+lblWEj+'" height="'+lblH+'" fill="white"/>';
  out += '<text x="'+(ox+gw/2)+'" y="'+(ejXY+fz/2-1)+'" text-anchor="middle" font-size="'+fz+'" font-weight="700" fill="#dc2626" font-family="monospace">Eje X</text>';

  // ── Eje Y — cota dedicada a la izq de la de alto, con flecha ──
  var ejYX = ox - cxOff - 14;
  out += '<line x1="'+ejYX+'" y1="'+oyBottom+'" x2="'+ejYX+'" y2="'+oy+'" stroke="#16a34a" stroke-width="1.1"/>';
  out += '<line x1="'+(ejYX-tk)+'" y1="'+oyBottom+'" x2="'+(ejYX+tk)+'" y2="'+oyBottom+'" stroke="#16a34a" stroke-width="1.1"/>';
  out += '<polygon points="'+(ejYX-arwSz)+','+oy+' '+ejYX+','+(oy-arwLen)+' '+(ejYX+arwSz)+','+oy+'" fill="#16a34a"/>';
  out += '<rect x="'+(ejYX-rotW/2)+'" y="'+(oy+gh/2-rotH/2)+'" width="'+rotW+'" height="'+rotH+'" fill="white"/>';
  out += '<text x="'+ejYX+'" y="'+(oy+gh/2)+'" text-anchor="middle" font-size="'+fz+'" font-weight="700" fill="#16a34a" font-family="monospace" transform="rotate(-90,'+ejYX+','+(oy+gh/2)+')">Eje Y</text>';

  // ── Elementos ─────────────────────────────────────────────────
  _elementos.forEach(function(e, idxEl) {
    var ep  = toPX(e.x, e.y);
    var ex  = ep.x, ey = ep.y;
    var cur = _draggingElem===e.id ? 'grabbing' : 'grab';
    var evts = ' onmousedown="CroquisMod._elemMouseDown('+e.id+',event)" ondblclick="CroquisMod._elemDblClick('+e.id+',event)"';

    // ── Líneas de referencia punteadas (sketch 3 style) ───────────
    out += '<line x1="'+ox+'" y1="'+ey+'" x2="'+ex+'" y2="'+ey+'" stroke="#dc2626" stroke-width="0.6" stroke-dasharray="4,3" opacity="0.6"/>';
    out += '<line x1="'+ex+'" y1="'+oyBottom+'" x2="'+ex+'" y2="'+ey+'" stroke="#16a34a" stroke-width="0.6" stroke-dasharray="4,3" opacity="0.6"/>';
    out += '<circle cx="'+ox+'" cy="'+ey+'" r="2.5" fill="#dc2626" opacity="0.7"/>';
    out += '<circle cx="'+ex+'" cy="'+oyBottom+'" r="2.5" fill="#16a34a" opacity="0.7"/>';

    // ── Cota X debajo del vidrio (cada elemento usa su propia fila, después de la cota ancho + Eje X) ──
    var cxPad = EL_BASE + idxEl*14, cxLblW = 44, cxLblH = 12;
    out += '<line x1="'+ox+'" y1="'+(oyBottom+cxPad)+'" x2="'+ex+'" y2="'+(oyBottom+cxPad)+'" stroke="#dc2626" stroke-width="'+sw+'"/>';
    out += '<line x1="'+ox+'" y1="'+(oyBottom+cxPad-tk/2)+'" x2="'+ox+'" y2="'+(oyBottom+cxPad+tk/2)+'" stroke="#dc2626" stroke-width="'+sw+'"/>';
    out += '<line x1="'+ex+'" y1="'+(oyBottom+cxPad-tk/2)+'" x2="'+ex+'" y2="'+(oyBottom+cxPad+tk/2)+'" stroke="#dc2626" stroke-width="'+sw+'"/>';
    var lxMid = ox + (ex-ox)/2;
    out += '<rect x="'+(lxMid-cxLblW/2)+'" y="'+(oyBottom+cxPad+2)+'" width="'+cxLblW+'" height="'+cxLblH+'" fill="white" rx="2"/>';
    out += '<text x="'+lxMid+'" y="'+(oyBottom+cxPad+cxLblH/2+fzSm/2)+'" text-anchor="middle" font-size="'+fzSm+'" font-weight="700" fill="#dc2626" font-family="monospace">X: '+e.x+' mm</text>';

    // ── Cota Y a la derecha del vidrio (cada elemento usa su propia columna) ──
    var cyPad = 6 + idxEl*14, cyLblW = 12, cyLblH = 44;
    out += '<line x1="'+(ox+gw+cyPad)+'" y1="'+oyBottom+'" x2="'+(ox+gw+cyPad)+'" y2="'+ey+'" stroke="#16a34a" stroke-width="'+sw+'"/>';
    out += '<line x1="'+(ox+gw+cyPad-tk/2)+'" y1="'+oyBottom+'" x2="'+(ox+gw+cyPad+tk/2)+'" y2="'+oyBottom+'" stroke="#16a34a" stroke-width="'+sw+'"/>';
    out += '<line x1="'+(ox+gw+cyPad-tk/2)+'" y1="'+ey+'" x2="'+(ox+gw+cyPad+tk/2)+'" y2="'+ey+'" stroke="#16a34a" stroke-width="'+sw+'"/>';
    var lyMid = ey + (oyBottom-ey)/2;
    out += '<rect x="'+(ox+gw+cyPad+2)+'" y="'+(lyMid-cyLblH/2)+'" width="'+cyLblW+'" height="'+cyLblH+'" fill="white" rx="2"/>';
    out += '<text x="'+(ox+gw+cyPad+cyLblW/2+2)+'" y="'+lyMid+'" text-anchor="middle" font-size="'+fzSm+'" font-weight="700" fill="#16a34a" font-family="monospace" transform="rotate(-90,'+(ox+gw+cyPad+cyLblW/2+2)+','+lyMid+')">Y: '+e.y+' mm</text>';

    // ── Dibujo del elemento ───────────────────────────────────────
    // Etiqueta siempre apunta hacia el centro del vidrio para no tapar los ejes ni otros elementos
    var sx = (ex >= ox + gw/2) ? -1 : 1;   // derecha del vidrio → etiqueta a la izquierda; izquierda → a la derecha
    var sy = (ey <= oy + gh/2) ? 1  : -1;  // mitad superior → etiqueta abajo; mitad inferior → arriba

    if (e.tipo==='tp') {
      var r = Math.max(4, (e.d/2)*sc);
      out += '<g style="cursor:'+cur+'"'+evts+'>';
      out += '<circle cx="'+ex+'" cy="'+ey+'" r="'+(r+4)+'" fill="transparent"/>';
      out += '<circle cx="'+ex+'" cy="'+ey+'" r="'+r+'" fill="white" stroke="#1e40af" stroke-width="1.5"/>';
      out += '<line x1="'+(ex-r*1.3)+'" y1="'+ey+'" x2="'+(ex+r*1.3)+'" y2="'+ey+'" stroke="#1e40af" stroke-width="1"/>';
      out += '<line x1="'+ex+'" y1="'+(ey-r*1.3)+'" x2="'+ex+'" y2="'+(ey+r*1.3)+'" stroke="#1e40af" stroke-width="1"/>';
      var tpTxt = 'TP  \u00D8'+e.d+' mm';
      var lblW1 = tpTxt.length * 5 + 4;
      var lx = sx>0 ? ex+r+4 : ex-r-4;
      var ly = sy>0 ? ey+r+4+fz + idxEl*(fzSm+3) : ey-r-4 - idxEl*(fzSm+3);
      var anchor1 = sx>0 ? 'start' : 'end';
      var rectX1  = sx>0 ? lx-1 : lx-lblW1+1;
      out += '<rect x="'+rectX1+'" y="'+(ly-fz)+'" width="'+lblW1+'" height="'+(fzSm+3)+'" fill="#dbeafe" rx="2"/>';
      out += '<text x="'+lx+'" y="'+ly+'" text-anchor="'+anchor1+'" font-size="'+fzSm+'" font-weight="700" fill="#1e40af" font-family="monospace">'+tpTxt+'</text>';
      out += '</g>';
    }
    if (e.tipo==='ta') {
      var re=Math.max(5, (e.de/2)*sc), ri=Math.max(2, (e.di/2)*sc);
      out += '<g style="cursor:'+cur+'"'+evts+'>';
      out += '<circle cx="'+ex+'" cy="'+ey+'" r="'+(re+4)+'" fill="transparent"/>';
      out += '<circle cx="'+ex+'" cy="'+ey+'" r="'+re+'" fill="white" stroke="#7c3aed" stroke-width="1.5"/>';
      out += '<circle cx="'+ex+'" cy="'+ey+'" r="'+ri+'" fill="none" stroke="#7c3aed" stroke-width="0.8" stroke-dasharray="2,1.5"/>';
      var taTxt = 'TA  \u00D8'+e.de+'/'+e.di+' mm';
      var lblW2 = taTxt.length * 5 + 4;
      var lx = sx>0 ? ex+re+4 : ex-re-4;
      var ly = sy>0 ? ey+re+4+fz + idxEl*(fzSm+3) : ey-re-4 - idxEl*(fzSm+3);
      var anchor2 = sx>0 ? 'start' : 'end';
      var rectX2  = sx>0 ? lx-1 : lx-lblW2+1;
      out += '<rect x="'+rectX2+'" y="'+(ly-fz)+'" width="'+lblW2+'" height="'+(fzSm+3)+'" fill="#f3e8ff" rx="2"/>';
      out += '<text x="'+lx+'" y="'+ly+'" text-anchor="'+anchor2+'" font-size="'+fzSm+'" font-weight="700" fill="#7c3aed" font-family="monospace">'+taTxt+'</text>';
      out += '</g>';
    }
    if (e.tipo==='rs') {
      var rw=Math.max(8,e.w*sc), rh=Math.max(4,e.h*sc);
      var rySVG = ey - rh; // Y crece hacia arriba en CAD
      out += '<g style="cursor:'+cur+'"'+evts+'>';
      out += '<rect x="'+(ex-3)+'" y="'+(rySVG-3)+'" width="'+(rw+6)+'" height="'+(rh+6)+'" fill="transparent"/>';
      out += '<rect x="'+ex+'" y="'+rySVG+'" width="'+rw+'" height="'+rh+'" fill="#fef9c3" fill-opacity="0.85" stroke="#854d0e" stroke-width="1.2" stroke-dasharray="3,2"/>';
      // cota ancho resaque
      out += '<line x1="'+ex+'" y1="'+(rySVG-9)+'" x2="'+(ex+rw)+'" y2="'+(rySVG-9)+'" stroke="#854d0e" stroke-width="0.8"/>';
      out += '<line x1="'+ex+'" y1="'+(rySVG-12)+'" x2="'+ex+'" y2="'+(rySVG-6)+'" stroke="#854d0e" stroke-width="0.8"/>';
      out += '<line x1="'+(ex+rw)+'" y1="'+(rySVG-12)+'" x2="'+(ex+rw)+'" y2="'+(rySVG-6)+'" stroke="#854d0e" stroke-width="0.8"/>';
      out += '<rect x="'+(ex+rw/2-18)+'" y="'+(rySVG-20)+'" width="36" height="10" fill="#dcfce7" rx="2"/>';
      out += '<text x="'+(ex+rw/2)+'" y="'+(rySVG-12)+'" text-anchor="middle" font-size="'+fzSm+'" font-weight="700" fill="#854d0e" font-family="monospace">'+e.w+' mm</text>';
      // cota alto resaque
      out += '<line x1="'+(ex+rw+9)+'" y1="'+rySVG+'" x2="'+(ex+rw+9)+'" y2="'+ey+'" stroke="#854d0e" stroke-width="0.8"/>';
      out += '<line x1="'+(ex+rw+6)+'" y1="'+rySVG+'" x2="'+(ex+rw+12)+'" y2="'+rySVG+'" stroke="#854d0e" stroke-width="0.8"/>';
      out += '<line x1="'+(ex+rw+6)+'" y1="'+ey+'" x2="'+(ex+rw+12)+'" y2="'+ey+'" stroke="#854d0e" stroke-width="0.8"/>';
      var lyRS = rySVG + rh/2;
      out += '<rect x="'+(ex+rw+12)+'" y="'+(lyRS-16)+'" width="10" height="32" fill="#dcfce7" rx="2"/>';
      out += '<text x="'+(ex+rw+17)+'" y="'+lyRS+'" text-anchor="middle" font-size="'+fzSm+'" font-weight="700" fill="#854d0e" font-family="monospace" transform="rotate(-90,'+(ex+rw+17)+','+lyRS+')">'+e.h+' mm</text>';
      var lxRS=ex, lyRS2=rySVG-24;
      out += '<rect x="'+(lxRS-1)+'" y="'+(lyRS2-fz)+'" width="52" height="'+(fz+3)+'" fill="#dcfce7" rx="2"/>';
      out += '<text x="'+lxRS+'" y="'+lyRS2+'" font-size="'+fzSm+'" font-weight="700" fill="#854d0e" font-family="monospace">RS  posición</text>';
      out += '</g>';
    }
  });

  // ── Canteado label ────────────────────────────────────────────
  var lados = [];
  if (_canteo.sup) lados.push('Sup'); if (_canteo.inf) lados.push('Inf');
  if (_canteo.izq) lados.push('Izq'); if (_canteo.der) lados.push('Der');
  if (lados.length) out += '<text x="'+(o.canvW/2)+'" y="'+(o.canvH-6)+'" text-anchor="middle" font-size="'+fz+'" fill="#92400e" font-family="monospace">Canteado: '+lados.join(' + ')+'</text>';
  if (nota) out += '<text x="'+(o.canvW/2)+'" y="'+(o.canvH-(lados.length?fz*2:6))+'" text-anchor="middle" font-size="'+fzSm+'" fill="#64748b" font-family="sans-serif">'+nota.substring(0,65)+'</text>';

  svg.innerHTML = out;
}

// ── Helper ─────────────────────────────────────────────────────────────────
function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

init();

return {
  init:            init,
  _nuevo:          _nuevo,
  _setForma:       _setForma,
  _toggleCanteo:   _toggleCanteo,
  _startDrag:      _startDrag,
  _onDrop:         _onDrop,
  _onMouseMove:    _onMouseMove,
  _onMouseUp:      _onMouseUp,
  _elemMouseDown:  _elemMouseDown,
  _elemDblClick:   _elemDblClick,
  _openEditModal:  _openEditModal,
  _closeModal:     _closeModal,
  _saveEditing:    _saveEditing,
  _deleteEditing:  _deleteEditing,
  _renderPlacedList: _renderPlacedList,
  _removeElem:     _removeElem,
  _redraw:         _redraw,
  _guardar:        _guardar,
  _cancelar:       _cancelar,
  _editarCroquis:  _editarCroquis,
  _eliminarCroquis: _eliminarCroquis,
  _imprimirCroquis: _imprimirCroquis,
  _zoomIn:         _zoomIn,
  _zoomOut:        _zoomOut,
  _zoomReset:      _zoomReset,
  _initPan:        _initPan,
  _onSvgClick:     _onSvgClick,
  _vertMouseDown:  _vertMouseDown,
  _vertDblClick:   _vertDblClick,
  _cerrarPoligono: _cerrarPoligono,
  _resetPoligono:  _resetPoligono,
  _deshacerPunto:  _deshacerPunto,
  _onSvgMouseLeave: _onSvgMouseLeave,
};
})();
</script>