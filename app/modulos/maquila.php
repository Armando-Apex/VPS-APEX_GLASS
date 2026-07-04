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
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }
.main { padding: 24px; max-width: 1100px; margin: 0 auto; }
.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; }
.btn { padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; }
.btn-primary { background: #2563eb; color: white; }
.table-wrap { background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
table { width: 100%; border-collapse: collapse; }
thead { background: #f8fafc; }
th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
td { padding: 14px 16px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #374151; }
.badge { display: inline-block; padding: 3px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
.badge-cotizacion { background: #fef3c7; color: #92400e; }
.badge-orden { background: #dbeafe; color: #1e40af; }
.badge-cancelada { background: #fee2e2; color: #991b1b; }
.empty { padding: 40px; text-align: center; color: #94a3b8; }
</style>

<div class="main">
  <div class="top-bar">
    <div class="section-title">Maquila</div>
    <button class="btn btn-primary" onclick="ModMaquila._abrirNueva()">+ Nueva Maquila</button>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Folio</th><th>Cliente</th><th>Estatus</th><th>Total</th><th></th></tr></thead>
      <tbody id="tablaMaquila"><tr><td colspan="5" class="empty">Cargando...</td></tr></tbody>
    </table>
  </div>
</div>

<script>
window._puedeEditarMaquila = <?= $puedeEditar ? 'true' : 'false' ?>;
var ModMaquila = (function(){
var API = '../api/maquila.php';
var lista = [];

async function cargar() {
  try {
    var res = await fetch(API + '?recurso=cotizacion&limit=100');
    lista = await res.json();
    renderTabla();
  } catch(e) {
    document.getElementById('tablaMaquila').innerHTML =
      '<tr><td colspan="5" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s == null) ? '' : String(s);
  return d.innerHTML;
}

function renderTabla() {
  if (!lista.length) {
    document.getElementById('tablaMaquila').innerHTML = '<tr><td colspan="5" class="empty">No hay maquilas registradas</td></tr>';
    return;
  }
  var html = '';
  for (var i = 0; i < lista.length; i++) {
    var c = lista[i];
    var idNum = parseInt(c.id, 10) || 0;
    var folioMostrado = c.orden_folio || c.folio;
    var badgeClass = c.estatus === 'cotizacion' ? 'badge-cotizacion' : (c.estatus === 'cancelada' ? 'badge-cancelada' : 'badge-orden');
    var total = parseFloat(c.total).toLocaleString('es-MX', {minimumFractionDigits:2});
    html += '<tr style="cursor:pointer" onclick="ModMaquila._abrirDetalle(' + idNum + ')">';
    html += '<td style="font-weight:600">' + esc(folioMostrado) + '</td>';
    html += '<td>' + esc(c.cliente_nombre || '') + '</td>';
    html += '<td><span class="badge ' + badgeClass + '">' + esc(c.estatus) + '</span></td>';
    html += '<td>$' + esc(total) + '</td>';
    html += '<td></td>';
    html += '</tr>';
  }
  document.getElementById('tablaMaquila').innerHTML = html;
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
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }
.main { padding: 24px; max-width: 1100px; margin: 0 auto; }
.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; }
.btn { padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; }
.btn-primary { background: #2563eb; color: white; }
.table-wrap { background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
</style>
<div class="main">
  <div class="top-bar">
    <div class="section-title">Nueva Maquila</div>
    <button class="btn" onclick="ModMaquilaNueva._volver()" style="background:#f1f5f9;color:#374151">&larr; Volver</button>
  </div>
  <div class="table-wrap" style="padding:24px">
    <label style="display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px">Cliente (ID)</label>
    <input id="mn_cliente_id" type="number" placeholder="ID del cliente" style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px">

    <label style="display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px">Localidad</label>
    <select id="mn_localidad" style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px">
      <option value="local">Local</option>
      <option value="foraneo">For&aacute;neo</option>
    </select>

    <div id="mn_partidas"></div>
    <button class="btn" style="background:#f1f5f9;color:#374151;margin-top:8px" onclick="ModMaquilaNueva._agregarPartida()">+ Agregar partida</button>

    <div style="margin-top:20px;font-size:16px;font-weight:700">Total: $<span id="mn_total">0.00</span></div>
    <button class="btn btn-primary" style="margin-top:12px" onclick="ModMaquilaNueva._guardar()">Guardar cotizaci&oacute;n</button>
    <div id="mn_error" style="color:#dc2626;margin-top:8px"></div>
  </div>
</div>

<script>
var ModMaquilaNueva = (function(){
var API = '../api/maquila.php';
var tiposVidrio = [];
var precios = [];
var partidas = [];
var ESPESORES = [3,4,5,6,8,10,12,15,19];
var CPB_OPTS = ['No','Perimetral','Larguero','Largueros','Cabezal','Cabezales','1 Larguero - 1 cabezal','2 Largueros - 1 cabezal','1 Larguero - 2 cabezales'];

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s == null) ? '' : String(s);
  return d.innerHTML;
}

async function cargarCatalogos() {
  var r1 = await fetch(API + '?recurso=tipos_vidrio');
  tiposVidrio = await r1.json();
  var r2 = await fetch(API + '?recurso=precios');
  precios = await r2.json();
  agregarPartida();
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
    html += '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:10px">';
    html += '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">';
    html += '<input type="number" placeholder="Ancho mm" value="' + p.ancho + '" style="width:100px;padding:6px" onchange="ModMaquilaNueva._campo(' + i + ",'ancho',parseInt(this.value)||0)\">";
    html += '<input type="number" placeholder="Alto mm" value="' + p.alto + '" style="width:100px;padding:6px" onchange="ModMaquilaNueva._campo(' + i + ",'alto',parseInt(this.value)||0)\">";
    html += '<input type="number" placeholder="Cant" value="' + p.cantidad + '" style="width:70px;padding:6px" onchange="ModMaquilaNueva._campo(' + i + ",'cantidad',parseInt(this.value)||1)\">";
    html += '<select onchange="ModMaquilaNueva._campo(' + i + ",'espesor_mm',parseFloat(this.value))\">";
    for (var e = 0; e < ESPESORES.length; e++) {
      html += '<option value="' + ESPESORES[e] + '"' + (p.espesor_mm == ESPESORES[e] ? ' selected' : '') + '>' + ESPESORES[e] + 'mm</option>';
    }
    html += '</select>';
    html += '<select onchange="ModMaquilaNueva._campo(' + i + ",'cristal_tipo_id',parseInt(this.value))\"><option value=\"0\">Tipo de vidrio...</option>";
    for (var t = 0; t < tiposVidrio.length; t++) {
      var tvId = parseInt(tiposVidrio[t].id, 10) || 0;
      html += '<option value="' + tvId + '"' + (p.cristal_tipo_id == tvId ? ' selected' : '') + '>' + esc(tiposVidrio[t].nombre) + '</option>';
    }
    html += '</select>';
    html += '</div>';
    html += '<label><input type="checkbox" ' + (p.corte ? 'checked' : '') + ' onchange="ModMaquilaNueva._campo(' + i + ",'corte',this.checked?1:0)\"> Corte</label> &nbsp;";
    html += '<label><input type="checkbox" ' + (p.canteado ? 'checked' : '') + ' onchange="ModMaquilaNueva._campo(' + i + ",'canteado',this.checked?1:0)\"> Canteado</label> ";
    html += '<select onchange="ModMaquilaNueva._campo(' + i + ",'cpb',this.value)\">";
    for (var c = 0; c < CPB_OPTS.length; c++) {
      html += '<option value="' + CPB_OPTS[c] + '"' + (p.cpb === CPB_OPTS[c] ? ' selected' : '') + '>' + CPB_OPTS[c] + '</option>';
    }
    html += '</select><br>';
    html += 'Taladros pasados: <input type="number" value="' + p.taladros_pasados + '" style="width:60px" onchange="ModMaquilaNueva._campo(' + i + ",'taladros_pasados',parseInt(this.value)||0)\"> ";
    html += 'avellanados: <input type="number" value="' + p.taladros_avellanados + '" style="width:60px" onchange="ModMaquilaNueva._campo(' + i + ",'taladros_avellanados',parseInt(this.value)||0)\"><br>";
    html += '<label><input type="checkbox" ' + (p.templado ? 'checked' : '') + ' onchange="ModMaquilaNueva._campo(' + i + ",'templado',this.checked?1:0)\"> Templado</label>";
    html += '<div style="margin-top:6px;font-weight:700">Subtotal: $' + sub.toFixed(2) + ' <button onclick="ModMaquilaNueva._eliminar(' + i + ')" style="float:right;color:#dc2626">Quitar</button></div>';
    html += '</div>';
  }
  document.getElementById('mn_partidas').innerHTML = html;

  var totalGeneral = 0;
  for (var j = 0; j < partidas.length; j++) totalGeneral += subtotalPartida(partidas[j]);
  document.getElementById('mn_total').textContent = (totalGeneral * 1.16).toFixed(2);
}

async function guardar() {
  var clienteId = parseInt(document.getElementById('mn_cliente_id').value) || 0;
  var localidad = document.getElementById('mn_localidad').value;
  document.getElementById('mn_error').textContent = '';

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
  _guardar: guardar,
  _volver: volver
};
})();
</script>
<?php endif; ?>
