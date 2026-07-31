<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
$puedeEditar = tienePermiso($user['rol'], 'gestionar_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=contabilidad_mapeo'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.main { padding: 24px; max-width: 1000px; margin: 0 auto; }

.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.wip-badge { font-size: 10px; background: #f59e0b; color: #000; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.wip-banner {
  background: #fef3c7; color: #92400e; font-size: 13px; padding: 10px 16px;
  border-radius: 8px; margin-bottom: 20px;
}

.card { background: white; border-radius: 14px; box-shadow: 0 2px 8px rgba(0,0,0,.06); padding: 20px; margin-bottom: 20px; }
.card h3 { font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 14px; }

.fila-mapeo { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.fila-mapeo .origen { flex: 0 0 220px; font-size: 13px; font-weight: 700; color: #374151; }
.fila-mapeo select { flex: 1; padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #1e293b; }
.fila-mapeo select:focus { outline: none; border-color: #2563eb; }

.badge-ok { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; background: #dcfce7; color: #16a34a; }
.badge-pendiente { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; background: #fee2e2; color: #dc2626; }

.empty { text-align: center; padding: 24px; color: #94a3b8; font-size: 14px; }
</style>

<div class="main">

  <div class="top-bar">
    <div class="section-title"><?= icono('layers') ?> Mapeo de Compras a Cuentas <span class="wip-badge">WIP</span></div>
  </div>

  <div class="wip-banner">Módulo en construcción — parte del proyecto de Estado de Resultados (P&amp;L). Asigna a qué cuenta contable va cada tipo de partida de Compras (láminas, fletes, categorías de OC). Aún no afecta ningún otro módulo del sistema.</div>

  <div class="card">
    <h3>Tipo de partida (oc_partidas.tipo)</h3>
    <div id="listaTipo"><div class="empty">Cargando...</div></div>
  </div>

  <div class="card">
    <h3>Categoría de OC (ordenes_compra.categoria)</h3>
    <div id="listaCategoria"><div class="empty">Cargando...</div></div>
  </div>

</div>

<script>
window._puedeEditar = <?= $puedeEditar ? 'true' : 'false' ?>;

var ModContabilidadMapeo = (function(){
var API = '../api/contabilidad_mapeo.php';
var cuentasApi = '../api/contabilidad_catalogo.php';
var cuentas = [];
var reglas = [];
var valoresTipo = [];
var valoresCategoria = [];

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s === null || s === undefined) ? '' : String(s);
  return d.innerHTML;
}

async function cargar() {
  try {
    var resCuentas = await fetch(cuentasApi + '?activas=1');
    cuentas = await resCuentas.json();

    var res = await fetch(API);
    var data = await res.json();
    reglas = data.reglas || [];
    valoresTipo = data.valores_oc_partida_tipo || [];
    valoresCategoria = data.valores_oc_categoria || [];

    render('listaTipo', 'oc_partida_tipo', valoresTipo);
    render('listaCategoria', 'oc_categoria', valoresCategoria);
  } catch(e) {
    document.getElementById('listaTipo').innerHTML = '<div class="empty" style="color:#dc2626">Error al cargar</div>';
  }
}

function buscarRegla(origenTipo, origenValor) {
  for (var i = 0; i < reglas.length; i++) {
    if (reglas[i].origen_tipo === origenTipo && reglas[i].origen_valor === origenValor) return reglas[i];
  }
  return null;
}

function render(contenedorId, origenTipo, valores) {
  var cont = document.getElementById(contenedorId);
  if (!valores.length) {
    cont.innerHTML = '<div class="empty">Sin valores registrados todavía en Compras</div>';
    return;
  }
  var cuentasImputables = [];
  for (var i = 0; i < cuentas.length; i++) {
    if (cuentas[i].es_acumulativa == 0) cuentasImputables.push(cuentas[i]);
  }
  var html = '';
  for (var i = 0; i < valores.length; i++) {
    var valor = valores[i];
    var regla = buscarRegla(origenTipo, valor);
    var selId = 'sel_' + origenTipo + '_' + i;
    html += '<div class="fila-mapeo">';
    html += '<div class="origen">' + esc(valor) + ' ' + (regla ? '<span class="badge-ok">Mapeado</span>' : '<span class="badge-pendiente">Sin mapear</span>') + '</div>';
    html += '<select id="' + selId + '" ' + (window._puedeEditar ? '' : 'disabled') + ' onchange="ModContabilidadMapeo._guardar(\'' + origenTipo + '\', this)" data-valor="' + esc(valor) + '">';
    html += '<option value="">(seleccionar cuenta)</option>';
    for (var j = 0; j < cuentasImputables.length; j++) {
      var c = cuentasImputables[j];
      var sel = (regla && regla.cuenta_id == c.id) ? ' selected' : '';
      html += '<option value="' + c.id + '"' + sel + '>' + esc(c.codigo) + ' — ' + esc(c.nombre) + '</option>';
    }
    html += '</select>';
    html += '</div>';
  }
  cont.innerHTML = html;
}

async function guardar(origenTipo, selectEl) {
  var origenValor = selectEl.getAttribute('data-valor');
  var cuentaId = selectEl.value;
  if (!cuentaId) return;
  try {
    var res = await fetch(API, {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ origen_tipo: origenTipo, origen_valor: origenValor, cuenta_id: parseInt(cuentaId) })
    });
    var data = await res.json();
    if (!data.ok) { alert(data.error || 'Error al guardar'); return; }
    cargar();
  } catch(e) { alert('Error de conexión'); }
}

cargar();

return {
  init: cargar,
  _guardar: guardar
};
})();
</script>
