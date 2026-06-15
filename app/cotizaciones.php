<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user         = requirePermiso('ver_ordenes');
$rol          = $user['rol'];
$es_admin     = in_array($rol, ['dir_admin', 'dueno']);
$puede_editar = in_array($rol, ['dir_admin', 'dueno', 'comercial']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APEX GLASS — Cotizaciones</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@400;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.header { background: #1a1a2e; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
.header h1 { font-size: 20px; font-weight: 800; letter-spacing: 1px; font-family: 'Syncopate', sans-serif; }
.header .right { display: flex; gap: 16px; align-items: center; }
.header a { color: #94a3b8; font-size: 13px; text-decoration: none; }

.main { padding: 24px; max-width: 1300px; margin: 0 auto; }
.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; }

.filtros { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
.filtros input, .filtros select { padding: 9px 14px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 14px; color: #1e293b; background: white; }
.filtros input:focus, .filtros select:focus { outline: none; border-color: #2563eb; }
.filtros input[type=text] { width: 260px; }

.btn { padding: 9px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn-primary { background: #2563eb; color: white; }
.btn-ghost   { background: #f1f5f9; color: #374151; }
.btn-sm { padding: 6px 12px; font-size: 12px; }

.table-wrap { background: white; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
table { width: 100%; border-collapse: collapse; }
thead { background: #f8fafc; }
th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
td { padding: 13px 16px; border-top: 1px solid #f1f5f9; font-size: 14px; color: #374151; }
tr:hover td { background: #f8fafc; cursor: pointer; }

.folio { font-weight: 800; color: #2563eb; font-size: 14px; }
.monto { font-weight: 700; color: #1e293b; }
.empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }
.count { font-size: 13px; color: #94a3b8; }

.badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
.badge-cotizacion { background: #dbeafe; color: #1d4ed8; }
.badge-orden      { background: #fef3c7; color: #d97706; }
.badge-entregada  { background: #dcfce7; color: #16a34a; }
.badge-cancelada  { background: #f1f5f9; color: #94a3b8; }
.badge-bloqueada  { background: #fee2e2; color: #dc2626; }

.icon-bloqueado { color: #dc2626; font-size: 14px; }
</style>
</head>
<body>

<div class="header">
  <h1>APEX GLASS — Cotizaciones</h1>
  <div class="right">
    <a href="dashboard.php">← Dashboard</a>
    <a href="../api/logout.php?redirect=../app/login.php">Salir</a>
  </div>
</div>

<div class="main">
  <div class="top-bar">
    <div class="section-title">📋 Cotizaciones</div>
    <?php if ($puede_editar): ?>
    <a href="cotizacion.php" class="btn btn-primary">+ Nueva cotización</a>
    <?php endif; ?>
  </div>

  <div class="filtros">
    <input type="text" id="busqueda" placeholder="🔍 Buscar folio, cliente, proyecto..." oninput="buscar()">
    <select id="filtroEstatus" onchange="buscar()">
      <option value="">Todos los estatus</option>
      <option value="cotizacion">Cotización</option>
      <option value="orden">Orden generada</option>
      <option value="entregada">Entregada</option>
      <option value="cancelada">Cancelada</option>
    </select>
    <span class="count" id="conteo"></span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Folio</th>
          <th>Fecha</th>
          <th>Cliente</th>
          <th>Proyecto</th>
          <th>Asesor</th>
          <th>F. Entrega</th>
          <th>Total</th>
          <th>Estatus</th>
        </tr>
      </thead>
      <tbody id="tablaCotizaciones">
        <tr><td colspan="8" class="empty">Cargando...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script>
const API = '../api/cotizaciones.php';
let timer = null;

function buscar() { clearTimeout(timer); timer = setTimeout(cargar, 300); }

async function cargar() {
  var q       = document.getElementById('busqueda').value.trim();
  var estatus = document.getElementById('filtroEstatus').value;
  var url     = API + '?limit=100';
  if (q)       url += '&q='       + encodeURIComponent(q);
  if (estatus) url += '&estatus=' + estatus;

  try {
    var res  = await fetch(url);
    var lista = await res.json();
    if (!Array.isArray(lista)) lista = [];
    document.getElementById('conteo').textContent = lista.length + ' cotización(es)';
    renderTabla(lista);
  } catch(e) {
    document.getElementById('tablaCotizaciones').innerHTML = '<tr><td colspan="8" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
  }
}

function renderTabla(lista) {
  if (!lista.length) {
    document.getElementById('tablaCotizaciones').innerHTML = '<tr><td colspan="8" class="empty">No se encontraron cotizaciones</td></tr>';
    return;
  }
  var html = '';
  for (var i = 0; i < lista.length; i++) {
    var c = lista[i];
    var bloqueada = c.entrega_bloqueada == 1 && c.estatus !== 'entregada';
    var fecha     = c.fecha      ? c.fecha.substring(0,10)        : '—';
    var fentrega  = c.fecha_entrega ? c.fecha_entrega.substring(0,10) : '—';
    var monto     = c.total ? '$' + parseFloat(c.total).toLocaleString('es-MX', {minimumFractionDigits:2}) : '—';
    var badge     = '<span class="badge badge-' + c.estatus + '">' + etiquetaEstatus(c.estatus) + '</span>';
    if (bloqueada) badge += ' <span class="icon-bloqueado" title="Entrega bloqueada por adeudo">🔒</span>';

    html += '<tr onclick="verDetalle(' + c.id + ')">';
    html += '<td><span class="folio">' + c.folio + '</span></td>';
    html += '<td>' + fecha + '</td>';
    html += '<td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (c.cliente_nombre || '—') + '</td>';
    html += '<td style="color:#64748b;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (c.proyecto || '—') + '</td>';
    html += '<td style="color:#64748b">' + (c.asesor_nombre || '—') + '</td>';
    html += '<td>' + fentrega + '</td>';
    html += '<td><span class="monto">' + monto + '</span></td>';
    html += '<td>' + badge + '</td>';
    html += '</tr>';
  }
  document.getElementById('tablaCotizaciones').innerHTML = html;
}

function etiquetaEstatus(s) {
  var map = { cotizacion: 'Cotización', orden: 'Orden', entregada: 'Entregada', cancelada: 'Cancelada' };
  return map[s] || s;
}

function verDetalle(id) {
  window.location.href = 'cotizacion.php?id=' + id;
}

cargar();
</script>
</body>
</html>