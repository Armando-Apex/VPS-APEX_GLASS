<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
requirePermiso('ver_dashboard');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=estaciones'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
.est-wrap { padding: 24px; }
.page-title { font-size: 18px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #9ca3af; margin-bottom: 20px; }
.est-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
.est-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
.est-header { padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; border-top: 3px solid transparent; }
.est-nombre { font-size: 14px; font-weight: 700; color: #1e293b; }
.est-cnt { font-size: 22px; font-weight: 800; }
.est-body { padding: 12px 18px; max-height: 320px; overflow-y: auto; }
.est-pieza { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f8fafc; font-size: 13px; }
.est-pieza:last-child { border-bottom: none; }
.est-folio  { font-weight: 600; color: #2563eb; min-width: 70px; cursor: pointer; }
.est-folio:hover { text-decoration: underline; }
.est-desc   { color: #64748b; flex: 1; font-size: 12px; }
.est-empty  { padding: 24px; text-align: center; color: #94a3b8; font-size: 13px; }
.loading-msg { text-align: center; padding: 48px; color: #9ca3af; font-size: 14px; }
</style>

<div class="est-wrap">
  <div class="page-title">Estaciones de Producci&#243;n</div>
  <div class="page-sub" id="est-sub">Cargando&#8230;</div>
  <div class="est-grid" id="est-grid">
    <div class="loading-msg">Cargando&#8230;</div>
  </div>
</div>

<script>
window.ModEstaciones=(function(){

const EST_CONFIG = [
  { key:'pendiente', label:'&#9203; Pendiente',  color:'#94a3b8' },
  { key:'cortado',   label:'&#9986;&#65039; Corte',      color:'#f59e0b' },
  { key:'canteado',  label:'&#128297; Canteado',   color:'#60a5fa' },
  { key:'trazo',     label:'&#9999;&#65039; Trazo',      color:'#a78bfa' },
  { key:'taladro',   label:'&#128295; Taladro',    color:'#c084fc' },
  { key:'templado',  label:'&#128293; Horno',      color:'#f87171' },
  { key:'terminado', label:'&#128230; Terminado',  color:'#34d399' },
];

async function estCargar() {
  try {
    const res  = await fetch('../api/estaciones.php?t='+Date.now());
    const data = await res.json();
    // Agrupar piezas por estatus &#8212; combinar en_corte con cortado, en_horno con templado
    const agrupado = {};
    (data.piezas || []).forEach(p => {
      let key = p.estatus;
      if (key === 'en_corte') key = 'cortado';
      if (key === 'en_horno') key = 'templado';
      if (!agrupado[key]) agrupado[key] = [];
      agrupado[key].push(p);
    });
    estRender(agrupado);
    document.getElementById('est-sub').textContent =
      'Actualizado a las '+new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  } catch(e) {
    document.getElementById('est-grid').innerHTML = '<div class="loading-msg" style="color:#dc2626">Error al cargar</div>';
  }
}

function estRender(data) {
  document.getElementById('est-grid').innerHTML = EST_CONFIG.map(est => {
    const piezas = data[est.key] || [];
    const cuerpo = piezas.length
      ? piezas.map(p => `<div class="est-pieza">
          <span class="est-folio" onclick="irA('orden',{folio:'${p.folio}'})">${p.folio}</span>
          <span class="est-desc">P${p.partida} &middot; ${p.pieza_num}/${p.pieza_total} &middot; ${p.cristal||''} ${p.ancho_mm||''}&#215;${p.alto_mm||''} mm &middot; ${p.cliente_nombre||''}</span>
        </div>`).join('')
      : '<div class="est-empty">Sin piezas en esta estaci&#243;n</div>';
    return `<div class="est-card">
      <div class="est-header" style="border-top-color:${est.color}">
        <span class="est-nombre">${est.label}</span>
        <span class="est-cnt" style="color:${est.color}">${piezas.length}</span>
      </div>
      <div class="est-body">${cuerpo}</div>
    </div>`;
  }).join('');
}

estCargar();
setInterval(estCargar, 30000);

return{init:estCargar};
})();
ModEstaciones.init();
</script>