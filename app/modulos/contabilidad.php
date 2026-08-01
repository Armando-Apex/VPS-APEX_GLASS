<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=contabilidad'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.main { padding: 24px; max-width: 1200px; margin: 0 auto; }

.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.wip-badge { font-size: 10px; background: #f59e0b; color: #000; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.wip-banner {
  background: #fef3c7; color: #92400e; font-size: 13px; padding: 10px 16px;
  border-radius: 8px; margin-bottom: 20px;
}

.contab-tabs { display: flex; gap: 6px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; flex-wrap: wrap; }
.contab-tab-btn {
  padding: 10px 16px; border: none; background: none; cursor: pointer;
  font-size: 13px; font-weight: 700; color: #64748b; border-bottom: 3px solid transparent;
  margin-bottom: -2px; transition: color .15s, border-color .15s;
}
.contab-tab-btn:hover { color: #2563eb; }
.contab-tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; }

#contab-content .empty-loading { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }
</style>

<div class="main">
  <div class="top-bar">
    <div class="section-title"><?= icono('layers') ?> Contabilidad <span class="wip-badge">WIP</span></div>
  </div>
  <div class="wip-banner">Módulo en construcción — proyecto de Estado de Resultados (P&amp;L). Aún no afecta ningún otro módulo del sistema.</div>

  <div class="contab-tabs">
    <button class="contab-tab-btn active" data-tab="contabilidad_catalogo" onclick="ModContabilidad.cargarTab('contabilidad_catalogo', this)">Catálogo de Cuentas</button>
    <button class="contab-tab-btn" data-tab="contabilidad_mapeo" onclick="ModContabilidad.cargarTab('contabilidad_mapeo', this)">Mapeo Compras</button>
    <button class="contab-tab-btn" data-tab="nomina" onclick="ModContabilidad.cargarTab('nomina', this)">Nómina</button>
    <button class="contab-tab-btn" data-tab="gastos_fijos" onclick="ModContabilidad.cargarTab('gastos_fijos', this)">Gastos Fijos</button>
    <button class="contab-tab-btn" data-tab="caja_chica" onclick="ModContabilidad.cargarTab('caja_chica', this)">Caja Chica</button>
    <button class="contab-tab-btn" data-tab="contabilidad_pnl" onclick="ModContabilidad.cargarTab('contabilidad_pnl', this)">Estado de Resultados</button>
  </div>

  <div id="contab-content"><div class="empty-loading">Cargando...</div></div>
</div>

<script>
var ModContabilidad = (function(){
var ARCHIVOS = {
  contabilidad_catalogo: 'modulos/contabilidad_catalogo.php',
  contabilidad_mapeo: 'modulos/contabilidad_mapeo.php',
  nomina: 'modulos/nomina.php',
  gastos_fijos: 'modulos/gastos_fijos.php',
  caja_chica: 'modulos/caja_chica.php',
  contabilidad_pnl: 'modulos/contabilidad_pnl.php'
};
var scriptsInyectados = [];

async function cargarTab(tab, btnEl) {
  document.querySelectorAll('.contab-tab-btn').forEach(function(b) { b.classList.remove('active'); });
  if (btnEl) btnEl.classList.add('active');

  scriptsInyectados.forEach(function(s) { if (s.parentNode) s.parentNode.removeChild(s); });
  scriptsInyectados = [];

  var cont = document.getElementById('contab-content');
  cont.innerHTML = '<div class="empty-loading">Cargando...</div>';

  try {
    var res = await fetch(ARCHIVOS[tab], { headers: { 'X-SPA-Request': '1' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    var html = await res.text();

    var tmp = document.createElement('div');
    tmp.innerHTML = html;
    var scripts = Array.prototype.slice.call(tmp.querySelectorAll('script'));
    scripts.forEach(function(s) { s.remove(); });
    cont.innerHTML = tmp.innerHTML;

    scripts.forEach(function(oldScript) {
      var newScript = document.createElement('script');
      newScript.text = oldScript.textContent;
      document.body.appendChild(newScript);
      scriptsInyectados.push(newScript);
    });
  } catch(e) {
    cont.innerHTML = '<div class="empty-loading" style="color:#dc2626">Error al cargar la pestaña</div>';
  }
}

cargarTab('contabilidad_catalogo', document.querySelector('.contab-tab-btn.active'));

return { cargarTab: cargarTab };
})();
</script>
