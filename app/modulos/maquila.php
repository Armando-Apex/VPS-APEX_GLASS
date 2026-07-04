<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_maquila');
$puedeEditar = in_array($user['rol'], ['dir_admin','dueno','comercial','desarrollo']);
$vista = $_GET['vista'] ?? 'lista';
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=maquila'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<?php if ($vista === 'lista'): ?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.mq-wrap { padding: 24px; }
.page-title { font-size: 18px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #9ca3af; margin-bottom: 16px; }
.mq-toolbar { display: flex; gap: 10px; margin-bottom: 16px; align-items: center; flex-wrap: wrap; }
.mq-search { flex: 1; min-width: 200px; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
.mq-search:focus { border-color: #2563eb; }
.btn-nueva { background: #2563eb; color: white; border: none; padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-nueva:hover { background: #1d4ed8; }
.mq-tabs { display: flex; gap: 2px; background: #f3f4f6; padding: 3px; border-radius: 10px; width: fit-content; margin-bottom: 16px; }
.mq-tab { padding: 6px 16px; border-radius: 8px; border: none; font-size: 13px; cursor: pointer; font-weight: 500; background: none; color: #6b7280; }
.mq-tab.active { background: #fff; color: #1a1a1a; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
.mq-cnt { font-size: 11px; font-weight: 600; padding: 1px 6px; border-radius: 99px; background: #e5e7eb; color: #6b7280; margin-left: 4px; }
.mq-table { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
thead th { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; }
tbody tr { border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background .1s; }
tbody tr:hover { background: #f8fafc; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 11px 14px; font-size: 13px; }
.mq-folio { font-weight: 700; color: #2563eb; }
.badge-cot   { background: #dbeafe; color: #1d4ed8; }
.badge-orden { background: #dcfce7; color: #15803d; }
.badge-canc  { background: #f1f5f9; color: #94a3b8; }
.est-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 99px; }
.loading-msg { text-align: center; padding: 48px; color: #9ca3af; font-size: 14px; }
.pager-inner { display: flex; align-items: center; justify-content: center; gap: 12px; padding: 12px 16px; border-top: 1px solid #f1f5f9; }
.pager-btn { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 6px 14px; font-size: 13px; font-weight: 600; cursor: pointer; color: #374151; }
.pager-btn:hover:not([disabled]) { background: #e2e8f0; }
.pager-btn[disabled] { opacity: .4; cursor: default; }
.pager-info { font-size: 12px; color: #6b7280; }

@media(max-width:768px){
  .mq-wrap { padding: 12px; }
  .mq-toolbar { flex-direction: column; align-items: stretch; gap: 8px; }
  .mq-search  { width: 100%; min-width: 0; }
  .btn-nueva   { width: 100%; text-align: center; }
  .mq-tabs { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; flex-wrap: nowrap; }
  .mq-tab  { white-space: nowrap; padding: 6px 12px; font-size: 12px; }
  tbody td { padding: 10px 8px; font-size: 12px; }
  thead th  { padding: 8px 8px; font-size: 10px; }
  .mq-folio { font-size: 12px; }
}
</style>

<div class="mq-wrap">
  <div class="page-title">Maquila</div>
  <div class="page-sub" id="mq-sub">Cargando&#8230;</div>

  <div class="mq-toolbar">
    <input type="text" class="mq-search" id="mq-q" placeholder="Buscar por folio o cliente&#8230;" oninput="ModMaquila._filtrar()">
    <button class="btn-nueva" onclick="ModMaquila._abrirNueva()">+ Nueva Maquila</button>
  </div>

  <div class="mq-tabs">
    <button class="mq-tab active" onclick="ModMaquila._tab('cotizacion')">Cotizaciones <span class="mq-cnt" id="mq-cnt-cot">&#8212;</span></button>
    <button class="mq-tab"        onclick="ModMaquila._tab('orden')"      >&#211;rdenes <span class="mq-cnt" id="mq-cnt-ord">&#8212;</span></button>
    <button class="mq-tab"        onclick="ModMaquila._tab('cancelada')"  >Canceladas <span class="mq-cnt" id="mq-cnt-can">&#8212;</span></button>
  </div>

  <div class="mq-table">
    <table>
      <thead><tr><th>Folio</th><th>Cliente</th><th>Total</th><th>Estado</th></tr></thead>
      <tbody id="tablaMaquila"><tr><td colspan="4" class="loading-msg">Cargando&#8230;</td></tr></tbody>
    </table>
    <div id="mq-pager"></div>
  </div>
</div>

<script>
window._puedeEditarMaquila = <?= $puedeEditar ? 'true' : 'false' ?>;
var ModMaquila = (function(){
var API = '../api/maquila.php';
var _data = [];
var _tab = 'cotizacion';
var _page = 1;
var _PER_PAGE = 25;
var _ALL_TABS = ['cotizacion','orden','cancelada'];

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s == null) ? '' : String(s);
  return d.innerHTML;
}

async function cargar() {
  try {
    var res = await fetch(API + '?recurso=cotizacion&limit=1000&t=' + Date.now());
    var data = await res.json();
    _data = Array.isArray(data) ? data : (data.cotizaciones || []);
    filtrar();
    document.getElementById('mq-sub').textContent =
      'Actualizado a las ' + new Date().toLocaleTimeString('es-MX', {hour:'2-digit', minute:'2-digit'});
  } catch(e) {
    document.getElementById('mq-sub').textContent = 'Error al cargar';
    document.getElementById('tablaMaquila').innerHTML =
      '<tr><td colspan="4" class="loading-msg" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function tab(t) {
  _tab = t;
  _page = 1;
  var btns = document.querySelectorAll('.mq-tab');
  for (var i = 0; i < btns.length; i++) {
    if (_ALL_TABS[i] === t) btns[i].classList.add('active'); else btns[i].classList.remove('active');
  }
  filtrar();
}

function matchSearch(c, q) {
  if (!q) return true;
  var folioMostrado = (c.orden_folio || c.folio || '').toLowerCase();
  return folioMostrado.indexOf(q) >= 0 || (c.cliente_nombre || '').toLowerCase().indexOf(q) >= 0;
}

function paginar(dir) {
  _page += dir;
  filtrar();
}
window.mqPaginar = paginar;

function marcarTabActiva() {
  var btns = document.querySelectorAll('.mq-tab');
  for (var i = 0; i < btns.length; i++) {
    if (_ALL_TABS[i] === _tab) btns[i].classList.add('active'); else btns[i].classList.remove('active');
  }
}

function filtrar() {
  var q = ((document.getElementById('mq-q') || {}).value || '').toLowerCase().trim();

  if (q) {
    var enActual = [];
    for (var i = 0; i < _data.length; i++) {
      if (_data[i].estatus === _tab && matchSearch(_data[i], q)) enActual.push(_data[i]);
    }
    if (enActual.length === 0) {
      for (var ti = 0; ti < _ALL_TABS.length; ti++) {
        if (_ALL_TABS[ti] === _tab) continue;
        var tabCheck = _ALL_TABS[ti];
        var hayEnOtro = false;
        for (var j = 0; j < _data.length; j++) {
          if (_data[j].estatus === tabCheck && matchSearch(_data[j], q)) { hayEnOtro = true; break; }
        }
        if (hayEnOtro) { _tab = tabCheck; _page = 1; marcarTabActiva(); break; }
      }
    }
  }

  var lista = [];
  var cots = 0, ords = 0, cans = 0;
  for (var k = 0; k < _data.length; k++) {
    var c = _data[k];
    if (c.estatus === 'cotizacion') cots++;
    else if (c.estatus === 'orden') ords++;
    else if (c.estatus === 'cancelada') cans++;
    if (c.estatus === _tab && matchSearch(c, q)) lista.push(c);
  }
  document.getElementById('mq-cnt-cot').textContent = cots;
  document.getElementById('mq-cnt-ord').textContent = ords;
  document.getElementById('mq-cnt-can').textContent = cans;

  var totalPags = Math.max(1, Math.ceil(lista.length / _PER_PAGE));
  if (_page > totalPags) _page = totalPags;
  if (_page < 1) _page = 1;
  var pagina = lista.slice((_page - 1) * _PER_PAGE, _page * _PER_PAGE);

  var pagerEl = document.getElementById('mq-pager');

  if (!lista.length) {
    document.getElementById('tablaMaquila').innerHTML = '<tr><td colspan="4" class="loading-msg">No hay registros</td></tr>';
    if (pagerEl) pagerEl.innerHTML = '';
    return;
  }

  var html = '';
  for (var m = 0; m < pagina.length; m++) {
    var row = pagina[m];
    var idNum = parseInt(row.id, 10) || 0;
    var folioMostrado = row.orden_folio || row.folio;
    var badgeClass = row.estatus === 'cotizacion' ? 'badge-cot' : (row.estatus === 'cancelada' ? 'badge-canc' : 'badge-orden');
    var badgeLabel = row.estatus === 'cotizacion' ? 'Cotizaci&oacute;n' : (row.estatus === 'cancelada' ? 'Cancelada' : 'Orden');
    var total = parseFloat(row.total || 0).toLocaleString('es-MX', {minimumFractionDigits:2});
    html += '<tr onclick="ModMaquila._abrirDetalle(' + idNum + ')">';
    html += '<td><span class="mq-folio">' + esc(folioMostrado) + '</span></td>';
    html += '<td>' + esc(row.cliente_nombre || '&#8212;') + '</td>';
    html += '<td style="font-weight:600">$' + total + '</td>';
    html += '<td><span class="est-badge ' + badgeClass + '">' + badgeLabel + '</span></td>';
    html += '</tr>';
  }
  document.getElementById('tablaMaquila').innerHTML = html;

  if (pagerEl) {
    if (totalPags <= 1) {
      pagerEl.innerHTML = '';
    } else {
      pagerEl.innerHTML = '<div class="pager-inner">'
        + '<button class="pager-btn" onclick="mqPaginar(-1)"' + (_page <= 1 ? ' disabled' : '') + '>&#8592; Ant</button>'
        + '<span class="pager-info">P&aacute;g. ' + _page + ' / ' + totalPags + ' &nbsp;&middot;&nbsp; ' + lista.length + ' registros</span>'
        + '<button class="pager-btn" onclick="mqPaginar(1)"' + (_page >= totalPags ? ' disabled' : '') + '>Sig &#8594;</button>'
        + '</div>';
    }
  }
}

function abrirDetalle(id) {
  window.irA('maquila_detalle', { id: id });
}

function abrirNueva() {
  window.irA('maquila_nueva');
}

cargar();

return {
  init: cargar,
  _cargar: cargar,
  _tab: tab,
  _filtrar: filtrar,
  _abrirDetalle: abrirDetalle,
  _abrirNueva: abrirNueva
};
})();
</script>
<?php endif; ?>

<?php if ($vista === 'nueva'): ?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.mq-wrap { padding: 24px; max-width: 980px; margin: 0 auto; }
.top-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-title { font-size: 18px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #9ca3af; }
.btn { padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; }
.btn-primary { background: #2563eb; color: white; }
.btn-primary:hover { background: #1d4ed8; }
.btn-ghost { background: #f1f5f9; color: #374151; }
.btn-sm { padding: 7px 14px; font-size: 12px; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
.card-title { font-size: 13px; font-weight: 700; color: #1e293b; margin-bottom: 16px; text-transform: uppercase; letter-spacing: .4px; }
.form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.span-2 { grid-column: span 2; }
.field { display: flex; flex-direction: column; gap: 6px; position: relative; }
.field label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
.field input, .field select { padding: 9px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #1e293b; background: white; width: 100%; }
.field input:focus, .field select:focus { outline: none; border-color: #2563eb; }
.autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1.5px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); z-index: 100; max-height: 220px; overflow-y: auto; margin-top: 2px; }
.autocomplete-item { padding: 9px 12px; cursor: pointer; font-size: 13px; }
.autocomplete-item:hover { background: #f0f4f8; }
.autocomplete-item .codigo { font-size: 11px; color: #94a3b8; margin-top: 2px; }
.mn-partida { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 12px; }
.mn-partida-num { font-size: 12px; font-weight: 700; color: #2563eb; margin-bottom: 12px; text-transform: uppercase; letter-spacing: .4px; }
.mn-partida-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 12px; }
.mn-servicios { display: flex; gap: 16px; align-items: center; flex-wrap: wrap; margin-bottom: 10px; font-size: 13px; color: #374151; }
.mn-servicios select { padding: 6px 10px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 12px; }
.chk { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.mn-taladros { display: flex; gap: 16px; align-items: center; font-size: 12px; color: #64748b; margin-bottom: 10px; }
.mn-taladros input { width: 60px; padding: 5px 8px; border: 1.5px solid #e2e8f0; border-radius: 6px; margin-left: 6px; }
.mn-partida-foot { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 10px; }
.mn-subtotal { font-weight: 700; color: #1e293b; font-size: 13px; }
.btn-del { background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; width: 28px; height: 28px; cursor: pointer; font-size: 15px; line-height: 1; }
.btn-del:hover { background: #fecaca; }
.btn-add-partida { background: none; border: 1.5px dashed #94a3b8; color: #64748b; padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; }
.btn-add-partida:hover { background: #f8fafc; }
.totales-box { background: #f8fafc; border-radius: 12px; padding: 18px 20px; max-width: 320px; margin-left: auto; }
.totales-row { display: flex; justify-content: space-between; font-size: 18px; font-weight: 800; color: #1e293b; }
.acciones { display: flex; gap: 10px; margin-top: 16px; }
</style>
<div class="mq-wrap">
  <div class="top-row">
    <div>
      <div class="page-title">Nueva Maquila</div>
      <div class="page-sub">Servicio de maquila sobre vidrio del cliente</div>
    </div>
    <button class="btn btn-ghost" onclick="ModMaquilaNueva._volver()">&larr; Volver</button>
  </div>

  <div class="card">
    <div class="card-title">Datos generales</div>
    <div class="form-grid">
      <div class="field span-2">
        <label>Cliente</label>
        <input type="text" id="mn_cliente_busqueda" placeholder="Buscar cliente&#8230;" autocomplete="off" oninput="ModMaquilaNueva._buscarCliente()">
        <div class="autocomplete-list" id="mn_cliente_lista" style="display:none"></div>
      </div>
      <div class="field">
        <label>Localidad</label>
        <select id="mn_localidad">
          <option value="local">Local</option>
          <option value="foraneo">For&aacute;neo</option>
        </select>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Partidas</div>
    <div id="mn_partidas"></div>
    <button class="btn-add-partida" onclick="ModMaquilaNueva._agregarPartida()">+ Agregar partida</button>
  </div>

  <div class="totales-box">
    <div class="totales-row"><span>Total</span><span>$<span id="mn_total">0.00</span></span></div>
  </div>

  <div class="acciones">
    <button class="btn btn-primary" onclick="ModMaquilaNueva._guardar()">Guardar cotizaci&oacute;n</button>
  </div>
  <div id="mn_error" style="color:#dc2626;margin-top:8px;font-size:13px;font-weight:600"></div>
</div>

<script>
var ModMaquilaNueva = (function(){
var API     = '../api/maquila.php';
var API_CLI = '../api/clientes.php';
var tiposVidrio = [];
var precios = [];
var partidas = [];
var clienteId = 0;
var _buscarTimer = null;
var ESPESORES = [3,4,5,6,8,10,12,15,19];
var CPB_OPTS = ['No','Perimetral','Larguero','Largueros','Cabezal','Cabezales','1 Larguero - 1 cabezal','2 Largueros - 1 cabezal','1 Larguero - 2 cabezales'];

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s == null) ? '' : String(s);
  return d.innerHTML;
}
function escJs(s) { return String(s || '').replace(/\\/g,'\\\\').replace(/'/g,"\\'"); }

async function cargarCatalogos() {
  var r1 = await fetch(API + '?recurso=tipos_vidrio');
  tiposVidrio = await r1.json();
  var r2 = await fetch(API + '?recurso=precios');
  precios = await r2.json();
  agregarPartida();
}

function buscarCliente() {
  if (_buscarTimer) clearTimeout(_buscarTimer);
  var q = (document.getElementById('mn_cliente_busqueda').value || '').trim();
  var lista = document.getElementById('mn_cliente_lista');
  if (q.length < 2) { lista.style.display = 'none'; return; }
  _buscarTimer = setTimeout(function() {
    fetch(API_CLI + '?q=' + encodeURIComponent(q)).then(function(r) { return r.json(); }).then(function(items) {
      if (!items.length) { lista.style.display = 'none'; return; }
      var html = '';
      for (var i = 0; i < items.length; i++) {
        var c = items[i];
        var nombre = c.razon_social || c.nombre;
        html += '<div class="autocomplete-item" onclick="ModMaquilaNueva._seleccionarCliente(' + (parseInt(c.id,10)||0) + ",'" + escJs(nombre) + "')\">"
              + esc(nombre) + '<div class="codigo">' + esc(c.codigo || '') + '</div></div>';
      }
      lista.innerHTML = html;
      lista.style.display = 'block';
    }).catch(function(){ lista.style.display = 'none'; });
  }, 250);
}

function seleccionarCliente(id, nombre) {
  clienteId = id;
  document.getElementById('mn_cliente_busqueda').value = nombre;
  document.getElementById('mn_cliente_lista').style.display = 'none';
}

function agregarPartida() {
  partidas.push({ ancho:0, alto:0, cantidad:1, espesor_mm:6, cristal_tipo_id:0, corte:0, canteado:0, cpb:'No', taladros_pasados:0, taladros_avellanados:0, templado:0 });
  render();
}

function eliminarPartida(idx) {
  partidas.splice(idx, 1);
  render();
}

function actualizarCampo(idx, campo, valor) {
  partidas[idx][campo] = valor;
  render();
}

function precioDe(servicio, espesor) {
  for (var i = 0; i < precios.length; i++) {
    if (precios[i].servicio === servicio && parseFloat(precios[i].espesor_mm) === parseFloat(espesor)) return parseFloat(precios[i].precio);
  }
  return null;
}

function mlCanteado(cpb, ancho, alto) {
  var a = ancho / 1000, h = alto / 1000;
  switch (cpb) {
    case 'Perimetral': return 2*a + 2*h;
    case 'Larguero': return h;
    case 'Largueros': return 2*h;
    case 'Cabezal': return a;
    case 'Cabezales': return 2*a;
    case '1 Larguero - 1 cabezal': return h + a;
    case '2 Largueros - 1 cabezal': return 2*h + a;
    case '1 Larguero - 2 cabezales': return h + 2*a;
    default: return 0;
  }
}

function subtotalPartida(p) {
  var m2 = (p.ancho/1000) * (p.alto/1000);
  var total = 0;
  if (p.corte) {
    var pc = precioDe('corte', p.espesor_mm);
    if (pc) total += (2*p.ancho/1000 + 2*p.alto/1000) * pc * p.cantidad;
  }
  if (p.canteado) {
    var pk = precioDe('canteado', p.espesor_mm);
    if (pk) total += mlCanteado(p.cpb, p.ancho, p.alto) * pk * p.cantidad;
  }
  var perforaciones = (parseInt(p.taladros_pasados)||0) + (parseInt(p.taladros_avellanados)||0);
  if (perforaciones > 0) {
    var pt = precioDe('taladro', p.espesor_mm);
    if (pt) total += perforaciones * pt * p.cantidad;
  }
  if (p.templado) {
    var ph = precioDe('horno', p.espesor_mm);
    if (ph) total += m2 * ph * p.cantidad;
  }
  return total;
}

function render() {
  var html = '';
  for (var i = 0; i < partidas.length; i++) {
    var p = partidas[i];
    var sub = subtotalPartida(p);
    html += '<div class="mn-partida">';
    html += '<div class="mn-partida-num">Partida ' + (i+1) + '</div>';
    html += '<div class="mn-partida-grid">';
    html += '<div class="field"><label>Ancho mm</label><input type="number" value="' + p.ancho + '" onchange="ModMaquilaNueva._campo(' + i + ",'ancho',parseInt(this.value)||0)\"></div>";
    html += '<div class="field"><label>Alto mm</label><input type="number" value="' + p.alto + '" onchange="ModMaquilaNueva._campo(' + i + ",'alto',parseInt(this.value)||0)\"></div>";
    html += '<div class="field"><label>Cantidad</label><input type="number" value="' + p.cantidad + '" onchange="ModMaquilaNueva._campo(' + i + ",'cantidad',parseInt(this.value)||1)\"></div>";
    html += '<div class="field"><label>Espesor</label><select onchange="ModMaquilaNueva._campo(' + i + ",'espesor_mm',parseFloat(this.value))\">";
    for (var e = 0; e < ESPESORES.length; e++) {
      html += '<option value="' + ESPESORES[e] + '"' + (p.espesor_mm == ESPESORES[e] ? ' selected' : '') + '>' + ESPESORES[e] + 'mm</option>';
    }
    html += '</select></div>';
    html += '<div class="field span-2"><label>Tipo de vidrio</label><select onchange="ModMaquilaNueva._campo(' + i + ",'cristal_tipo_id',parseInt(this.value))\"><option value=\"0\">Selecciona&#8230;</option>";
    for (var t = 0; t < tiposVidrio.length; t++) {
      var tvId = parseInt(tiposVidrio[t].id, 10) || 0;
      html += '<option value="' + tvId + '"' + (p.cristal_tipo_id == tvId ? ' selected' : '') + '>' + esc(tiposVidrio[t].nombre) + '</option>';
    }
    html += '</select></div>';
    html += '</div>';

    html += '<div class="mn-servicios">';
    html += '<label class="chk"><input type="checkbox" ' + (p.corte ? 'checked' : '') + ' onchange="ModMaquilaNueva._campo(' + i + ",'corte',this.checked?1:0)\"> Corte</label>";
    html += '<label class="chk"><input type="checkbox" ' + (p.canteado ? 'checked' : '') + ' onchange="ModMaquilaNueva._campo(' + i + ",'canteado',this.checked?1:0)\"> Canteado</label>";
    html += '<select onchange="ModMaquilaNueva._campo(' + i + ",'cpb',this.value)\">";
    for (var c = 0; c < CPB_OPTS.length; c++) {
      html += '<option value="' + CPB_OPTS[c] + '"' + (p.cpb === CPB_OPTS[c] ? ' selected' : '') + '>' + CPB_OPTS[c] + '</option>';
    }
    html += '</select>';
    html += '<label class="chk"><input type="checkbox" ' + (p.templado ? 'checked' : '') + ' onchange="ModMaquilaNueva._campo(' + i + ",'templado',this.checked?1:0)\"> Templado</label>";
    html += '</div>';

    html += '<div class="mn-taladros">';
    html += '<label>Taladros pasados<input type="number" value="' + p.taladros_pasados + '" onchange="ModMaquilaNueva._campo(' + i + ",'taladros_pasados',parseInt(this.value)||0)\"></label>";
    html += '<label>Avellanados<input type="number" value="' + p.taladros_avellanados + '" onchange="ModMaquilaNueva._campo(' + i + ",'taladros_avellanados',parseInt(this.value)||0)\"></label>";
    html += '</div>';

    html += '<div class="mn-partida-foot">';
    html += '<span class="mn-subtotal">Subtotal: $' + sub.toFixed(2) + '</span>';
    html += '<button class="btn-del" onclick="ModMaquilaNueva._eliminar(' + i + ')">&times;</button>';
    html += '</div>';
    html += '</div>';
  }
  document.getElementById('mn_partidas').innerHTML = html;

  var totalGeneral = 0;
  for (var j = 0; j < partidas.length; j++) totalGeneral += subtotalPartida(partidas[j]);
  document.getElementById('mn_total').textContent = (totalGeneral * 1.16).toFixed(2);
}

async function guardar() {
  document.getElementById('mn_error').textContent = '';
  if (!clienteId) { document.getElementById('mn_error').textContent = 'Busca y selecciona un cliente'; return; }
  var localidad = document.getElementById('mn_localidad').value;

  try {
    var res = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ recurso: 'cotizacion', accion: 'crear', cliente_id: clienteId, localidad: localidad, partidas: partidas })
    });
    var data = await res.json();
    if (data.error) { document.getElementById('mn_error').textContent = data.error; return; }
    window.irA('maquila_detalle', { id: data.id });
  } catch(e) {
    document.getElementById('mn_error').textContent = 'Error de red al guardar';
  }
}

function volver() {
  window.irA('maquila');
}

cargarCatalogos();

return {
  init: cargarCatalogos,
  _agregarPartida: agregarPartida,
  _eliminar: eliminarPartida,
  _campo: actualizarCampo,
  _buscarCliente: buscarCliente,
  _seleccionarCliente: seleccionarCliente,
  _guardar: guardar,
  _volver: volver
};
})();
</script>
<?php endif; ?>

<?php if ($vista === 'detalle'): ?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.mq-wrap { padding: 24px; max-width: 980px; margin: 0 auto; }
.top-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-title { font-size: 18px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #9ca3af; }
.btn { padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; }
.btn-primary { background: #2563eb; color: white; }
.btn-primary:hover { background: #1d4ed8; }
.btn-ghost { background: #f1f5f9; color: #374151; }
.btn-danger { background: #fee2e2; color: #991b1b; }
.btn-danger:hover { background: #fecaca; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
.md-info-row { display: flex; gap: 40px; flex-wrap: wrap; }
.md-info-item .lbl { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
.md-info-item .val { font-size: 15px; font-weight: 700; color: #1e293b; margin-top: 4px; }
.est-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 99px; }
.badge-cot   { background: #dbeafe; color: #1d4ed8; }
.badge-orden { background: #dcfce7; color: #15803d; }
.badge-canc  { background: #f1f5f9; color: #94a3b8; }
.mq-table { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
thead th { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; }
tbody tr { border-bottom: 1px solid #f1f5f9; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 11px 14px; font-size: 13px; }
.acciones { display: flex; gap: 10px; margin-top: 16px; }
</style>
<div class="mq-wrap">
  <div class="top-row">
    <div>
      <div class="page-title" id="md_titulo">Cargando&#8230;</div>
      <div class="page-sub">Detalle de cotizaci&oacute;n de maquila</div>
    </div>
    <button class="btn btn-ghost" onclick="ModMaquilaDetalle._volver()">&larr; Volver</button>
  </div>

  <div class="card">
    <div class="md-info-row">
      <div class="md-info-item"><div class="lbl">Cliente</div><div class="val" id="md_cliente">&#8212;</div></div>
      <div class="md-info-item"><div class="lbl">Estatus</div><div class="val" id="md_estatus">&#8212;</div></div>
      <div class="md-info-item"><div class="lbl">Total</div><div class="val" id="md_total">&#8212;</div></div>
    </div>
  </div>

  <div class="mq-table">
    <table>
      <thead><tr><th>#</th><th>Medidas</th><th>Espesor</th><th>Servicios</th><th>Subtotal</th></tr></thead>
      <tbody id="md_partidas"></tbody>
    </table>
  </div>

  <div class="acciones">
    <button class="btn btn-primary" id="md_btnConvertir" onclick="ModMaquilaDetalle._convertir()" style="display:none">Convertir a Orden</button>
    <button class="btn btn-danger" id="md_btnCancelar" onclick="ModMaquilaDetalle._cancelar()" style="display:none">Cancelar</button>
  </div>
  <div id="md_error" style="color:#dc2626;margin-top:8px;font-size:13px;font-weight:600"></div>
</div>

<script>
window._puedeEditarMaquila = <?= $puedeEditar ? 'true' : 'false' ?>;
var ModMaquilaDetalle = (function(){
var API = '../api/maquila.php';
var cotId = new URLSearchParams(location.search).get('id');
var cot = null;

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s == null) ? '' : String(s);
  return d.innerHTML;
}

async function cargar() {
  var res = await fetch(API + '?recurso=cotizacion&id=' + cotId);
  cot = await res.json();
  if (cot.error) {
    document.getElementById('md_titulo').textContent = 'Error';
    document.getElementById('md_error').textContent = cot.error;
    return;
  }
  render();
}

function render() {
  var folioMostrado = cot.orden_folio || cot.folio;
  document.getElementById('md_titulo').textContent = 'Maquila ' + folioMostrado;
  document.getElementById('md_cliente').textContent = cot.cliente_nombre || '&#8212;';

  var badgeClass = cot.estatus === 'cotizacion' ? 'badge-cot' : (cot.estatus === 'cancelada' ? 'badge-canc' : 'badge-orden');
  var badgeLabel = cot.estatus === 'cotizacion' ? 'Cotización' : (cot.estatus === 'cancelada' ? 'Cancelada' : 'Orden');
  document.getElementById('md_estatus').innerHTML = '<span class="est-badge ' + badgeClass + '">' + badgeLabel + '</span>';
  document.getElementById('md_total').textContent = '$' + parseFloat(cot.total).toLocaleString('es-MX', {minimumFractionDigits:2});

  var html = '';
  for (var i = 0; i < cot.partidas.length; i++) {
    var p = cot.partidas[i];
    var servicios = [];
    if (p.corte == 1) servicios.push('Corte (' + parseFloat(p.ml_corte).toFixed(2) + 'ml)');
    if (p.canteado == 1) servicios.push('Canteado ' + esc(p.cpb) + ' (' + parseFloat(p.ml_canteado).toFixed(2) + 'ml)');
    if ((parseInt(p.taladros_pasados)||0) + (parseInt(p.taladros_avellanados)||0) > 0) servicios.push('Taladro (' + p.taladros_pasados + 'p/' + p.taladros_avellanados + 'a)');
    if (p.templado == 1) servicios.push('Templado');
    html += '<tr>';
    html += '<td>' + (i+1) + '</td>';
    html += '<td>' + p.ancho + 'x' + p.alto + ' mm &times;' + p.cantidad + '</td>';
    html += '<td>' + p.espesor_mm + 'mm</td>';
    html += '<td>' + servicios.join(', ') + '</td>';
    html += '<td style="font-weight:600">$' + parseFloat(p.subtotal).toLocaleString('es-MX', {minimumFractionDigits:2}) + '</td>';
    html += '</tr>';
  }
  document.getElementById('md_partidas').innerHTML = html;

  if (cot.estatus === 'cotizacion' && window._puedeEditarMaquila) {
    document.getElementById('md_btnConvertir').style.display = 'inline-block';
    document.getElementById('md_btnCancelar').style.display = 'inline-block';
  }
}

async function convertir() {
  if (!confirm('&#191;Convertir esta cotización de maquila en orden de producción?')) return;
  var res = await fetch('../api/cotizaciones.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ accion: 'convertir_orden', id: cotId })
  });
  var data = await res.json();
  if (data.error) { document.getElementById('md_error').textContent = data.error; return; }
  cargar();
}

async function cancelar() {
  if (!confirm('&#191;Cancelar esta cotización de maquila?')) return;
  var res = await fetch(API, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recurso: 'cotizacion', accion: 'cancelar', id: cotId })
  });
  var data = await res.json();
  if (data.error) { document.getElementById('md_error').textContent = data.error; return; }
  cargar();
}

function volver() {
  window.irA('maquila');
}

cargar();

return { init: cargar, _convertir: convertir, _cancelar: cancelar, _volver: volver };
})();
</script>
<?php endif; ?>
