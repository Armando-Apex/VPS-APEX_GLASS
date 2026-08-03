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

.contab-tabs-wrap { display: flex; flex-wrap: nowrap; gap: clamp(6px, 1.4vw, 22px); align-items: flex-start; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #e2e8f0; width: 100%; }
.contab-grupo { display: flex; flex-direction: column; gap: 8px; flex: 0 0 auto; }
.contab-grupo-titulo { font-size: clamp(9px, .8vw, 10.5px); font-weight: 800; letter-spacing: .07em; text-transform: uppercase; display: flex; align-items: center; gap: 6px; white-space: nowrap; }
.contab-grupo-titulo .dot { width: 7px; height: 7px; border-radius: 50%; flex: 0 0 auto; }
.contab-grupo-reporte .contab-grupo-titulo { color: #0f766e; }
.contab-grupo-reporte .contab-grupo-titulo .dot { background: #0f766e; }
.contab-grupo-captura .contab-grupo-titulo { color: #b45309; }
.contab-grupo-captura .contab-grupo-titulo .dot { background: #b45309; }
.contab-grupo-config .contab-grupo-titulo { color: #6d28d9; }
.contab-grupo-config .contab-grupo-titulo .dot { background: #6d28d9; }
.contab-divider { width: 1px; align-self: stretch; background: #e2e8f0; margin-top: 2px; flex: 0 0 auto; }

.contab-pill-row { display: flex; gap: clamp(2px, .4vw, 6px); flex-wrap: nowrap; }
.contab-tab-btn {
  padding: clamp(5px, .8vw, 9px) clamp(6px, 1.2vw, 14px); border-radius: 7px; border: 1.5px solid #e2e8f0; background: transparent; cursor: pointer;
  font-size: clamp(11px, 1.05vw, 13px); font-weight: 700; color: #1e293b; transition: all .15s; white-space: nowrap; flex: 0 0 auto;
}
.contab-tab-btn:hover { border-color: #2563eb; color: #2563eb; }
.contab-grupo-reporte .contab-tab-btn.active { background: #ccfbf1; border-color: #0f766e; color: #0f766e; }
.contab-grupo-captura .contab-tab-btn.active { background: #fef3c7; border-color: #b45309; color: #b45309; }
.contab-grupo-config .contab-tab-btn.active { background: #ede9fe; border-color: #6d28d9; color: #6d28d9; }

#contab-content .empty-loading { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }

</style>

<div class="main">
  <div class="top-bar">
    <div class="section-title"><?= icono('layers') ?> Contabilidad <span class="wip-badge">WIP</span></div>
  </div>
  <div class="wip-banner">Módulo en construcción — proyecto de Estado de Resultados (P&amp;L). Aún no afecta ningún otro módulo del sistema.</div>

  <div class="contab-tabs-wrap">
    <div class="contab-grupo contab-grupo-reporte">
      <div class="contab-grupo-titulo"><span class="dot"></span>Reporte</div>
      <div class="contab-pill-row">
        <button class="contab-tab-btn active" data-tab="contabilidad_pnl" onclick="ModContabilidad.cargarTab('contabilidad_pnl', this)">Resultados</button>
        <button class="contab-tab-btn" data-tab="contabilidad_balance" onclick="ModContabilidad.cargarTab('contabilidad_balance', this)">Balance General</button>
      </div>
    </div>

    <div class="contab-divider"></div>

    <div class="contab-grupo contab-grupo-captura">
      <div class="contab-grupo-titulo"><span class="dot"></span>Captura mensual</div>
      <div class="contab-pill-row">
        <button class="contab-tab-btn" data-tab="nomina" onclick="ModContabilidad.cargarTab('nomina', this)">Nómina</button>
        <button class="contab-tab-btn" data-tab="gastos_fijos" onclick="ModContabilidad.cargarTab('gastos_fijos', this)">Gastos Fijos</button>
        <button class="contab-tab-btn" data-tab="caja_chica" onclick="ModContabilidad.cargarTab('caja_chica', this)">Caja Chica</button>
        <button class="contab-tab-btn" data-tab="contabilidad_polizas" onclick="ModContabilidad.cargarTab('contabilidad_polizas', this)">Pólizas</button>
      </div>
    </div>

    <div class="contab-divider"></div>

    <div class="contab-grupo contab-grupo-config">
      <div class="contab-grupo-titulo"><span class="dot"></span>Configuración</div>
      <div class="contab-pill-row">
        <button class="contab-tab-btn" data-tab="contabilidad_catalogo" onclick="ModContabilidad.cargarTab('contabilidad_catalogo', this)">Catálogo</button>
        <button class="contab-tab-btn" data-tab="contabilidad_mapeo" onclick="ModContabilidad.cargarTab('contabilidad_mapeo', this)">Mapeo Compras</button>
      </div>
    </div>
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
  contabilidad_polizas: 'modulos/contabilidad_polizas.php',
  contabilidad_pnl: 'modulos/contabilidad_pnl.php',
  contabilidad_balance: 'modulos/contabilidad_balance.php'
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

cargarTab('contabilidad_pnl', document.querySelector('.contab-tab-btn.active'));

return { cargarTab: cargarTab };
})();
</script>
