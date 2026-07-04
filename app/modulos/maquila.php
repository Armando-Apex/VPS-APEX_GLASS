<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_maquila');
$puedeEditar = in_array($user['rol'], ['dir_admin','dueno','comercial','desarrollo']);
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=maquila'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
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
