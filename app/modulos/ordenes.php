<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
requirePermiso('ver_dashboard');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=ordenes'); exit;
}
?>
<style>
.ord-wrap { padding: 24px; }
.page-title { font-size: 18px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
.page-sub   { font-size: 12px; color: #9ca3af; margin-bottom: 20px; }
.tabs { display:flex; gap:2px; margin-bottom:20px; background:#f3f4f6; padding:3px; border-radius:10px; width:fit-content; }
.tab-btn { padding:7px 18px; border-radius:8px; border:none; font-size:13px; cursor:pointer; font-weight:500; background:none; color:#6b7280; transition:all .15s; display:flex; align-items:center; gap:6px; }
.tab-btn.active { background:#fff; color:#1a1a1a; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.tab-cnt { font-size:11px; font-weight:600; padding:1px 7px; border-radius:99px; background:#e5e7eb; color:#6b7280; }
.tab-btn.active .tab-cnt { background:#dcfce7; color:#166534; }
.seccion { display:none; } .seccion.visible { display:block; }
.orden-card { background:#fff; border:0.5px solid #e5e7eb; border-radius:12px; margin-bottom:10px; overflow:hidden; }
.orden-card.prioritaria { border-left:3px solid #f59e0b; }
.orden-head { display:flex; align-items:center; gap:12px; padding:13px 18px; cursor:pointer; transition:background .1s; }
.orden-head:hover { background:#fafafa; }
.orden-folio  { font-size:14px; font-weight:600; color:#2563eb; min-width:90px; cursor:pointer; }
.orden-folio:hover { text-decoration:underline; }
.orden-cliente { font-size:13px; color:#374151; flex:1; }
.orden-asesor  { font-size:12px; color:#9ca3af; min-width:100px; }
.orden-fecha   { font-size:12px; font-weight:500; min-width:80px; text-align:right; }
.badge-prio { font-size:10px; font-weight:500; background:#fef9c3; color:#854d0e; padding:2px 7px; border-radius:99px; }
.pzs-badge  { font-size:12px; font-weight:500; padding:3px 10px; border-radius:99px; min-width:70px; text-align:center; }
.pzs-pend   { background:#fee2e2; color:#991b1b; }
.pzs-term   { background:#dcfce7; color:#166534; }
.toggle-icon { font-size:12px; color:#9ca3af; margin-left:4px; transition:transform .2s; }
.toggle-icon.open { transform:rotate(180deg); }
.partidas-wrap { display:none; border-top:0.5px solid #f3f4f6; }
.partidas-wrap.open { display:block; }
.partida-row { display:flex; align-items:center; gap:12px; padding:10px 18px 10px 36px; border-bottom:0.5px solid #f9fafb; font-size:12px; }
.partida-row:last-child { border-bottom:none; }
.partida-num  { font-weight:500; color:#374151; min-width:60px; }
.partida-desc { color:#6b7280; flex:1; }
.partida-pzs  { font-weight:500; color:#991b1b; background:#fee2e2; padding:2px 8px; border-radius:99px; font-size:11px; }
.entr-row { display:flex; align-items:center; gap:12px; padding:13px 18px; border-bottom:0.5px solid #f9fafb; font-size:13px; }
.entr-row:last-child { border-bottom:none; }
.entr-folio   { font-size:14px; font-weight:600; color:#2563eb; min-width:90px; cursor:pointer; }
.entr-folio:hover { text-decoration:underline; }
.entr-cliente { color:#374151; flex:1; }
.entr-asesor  { font-size:12px; color:#9ca3af; min-width:100px; }
.entr-pzs     { font-size:12px; color:#6b7280; min-width:80px; }
.entr-fecha   { font-size:12px; color:#6b7280; min-width:100px; text-align:right; }
.cierre-pill  { font-size:11px; font-weight:500; padding:2px 9px; border-radius:99px; }
.cierre-ok      { background:#dcfce7; color:#166534; }
.cierre-parcial { background:#fef9c3; color:#854d0e; }
.card-empty { text-align:center; padding:48px; color:#9ca3af; font-size:14px; }
.search-sort-bar { display:flex; align-items:center; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
#ord-busqueda { flex:1; min-width:200px; background:#fff; border:0.5px solid #e5e7eb; border-radius:8px; padding:8px 14px; font-size:13px; color:#1a1a1a; outline:none; }
#ord-busqueda:focus { border-color:#16a34a; }
.sort-btns { display:flex; gap:6px; flex-wrap:wrap; }
.sort-btn { background:#fff; border:0.5px solid #e5e7eb; border-radius:8px; padding:7px 12px; font-size:12px; color:#6b7280; cursor:pointer; transition:all .15s; display:flex; align-items:center; gap:4px; }
.sort-btn:hover { background:#f3f4f6; }
.sort-btn.active { background:#f0fdf4; color:#166534; border-color:#bbf7d0; font-weight:500; }
.sort-arrow { font-size:11px; }
.loading-msg { text-align:center; padding:48px; color:#9ca3af; font-size:14px; }

@media(max-width:768px){
  .ord-wrap { padding:14px 12px; }

  /* Tabs: scroll horizontal para que quepan */
  .tabs { width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; flex-wrap:nowrap; }
  .tab-btn { white-space:nowrap; padding:7px 12px; font-size:12px; }

  /* Sort bar: buscador arriba, botones abajo */
  .search-sort-bar { flex-direction:column; align-items:stretch; gap:8px; }
  #ord-busqueda { width:100%; min-width:0; }
  .sort-btns { justify-content:flex-start; }
  .sort-btn { font-size:11px; padding:6px 10px; }

  /* Card cabecera: 2 líneas en lugar de fila única */
  .orden-head {
    flex-wrap:wrap;
    gap:6px;
    padding:12px 14px;
  }
  .orden-folio  { min-width:auto; font-size:14px; }
  .orden-cliente { width:100%; flex:none; font-size:13px; order:3; }
  .orden-asesor  { font-size:11px; color:#9ca3af; order:4; flex:1; min-width:0; }
  .orden-fecha   { min-width:auto; font-size:11px; order:4; text-align:left; }
  .pzs-badge     { min-width:auto; order:2; }
  .toggle-icon   { order:1; margin-left:auto; }
  .badge-prio    { order:2; }

  /* Partidas: reducir sangría */
  .partida-row { padding:9px 12px 9px 16px; flex-wrap:wrap; gap:6px; }
  .partida-num  { min-width:auto; }
  .partida-desc { width:100%; flex:none; }
  .partida-pzs  { margin-left:auto; }

  /* Filas de entregadas/listas/en proceso */
  .entr-row {
    flex-wrap:wrap;
    gap:4px 8px;
    padding:11px 14px;
    align-items:flex-start;
  }
  .entr-folio   { min-width:auto; font-size:14px; }
  .entr-cliente { width:100%; flex:none; order:3; margin-top:2px; }
  .entr-asesor  { min-width:auto; font-size:11px; order:4; }
  .entr-fecha   { min-width:auto; font-size:11px; order:4; text-align:left; }
  .entr-pzs     { font-size:11px; order:4; }
  .cierre-pill  { order:2; margin-left:auto; }
  .pzs-badge    { min-width:auto; order:2; margin-left:auto; }
  .badge-prio   { order:2; }
}
</style>

<div class="ord-wrap">
  <div class="page-title">&#211;rdenes</div>
  <div class="page-sub" id="ord-sub">Cargando&#8230;</div>

  <div class="search-sort-bar">
    <input type="text" id="ord-busqueda" placeholder="&#128269; Buscar por cliente u orden&#8230;" oninput="ordFiltrar()" autocomplete="off">
    <div class="sort-btns">
      <button class="sort-btn active" id="ord-sort-folio"   onclick="ordSort('folio')"  ># Folio <span class="sort-arrow" id="ord-arrow-folio">&#8593;</span></button>
      <button class="sort-btn"        id="ord-sort-cliente" onclick="ordSort('cliente')">A-Z Cliente <span class="sort-arrow" id="ord-arrow-cliente">&#8593;</span></button>
      <button class="sort-btn"        id="ord-sort-fecha"   onclick="ordSort('fecha')"  >&#128197; Fecha <span class="sort-arrow" id="ord-arrow-fecha">&#8593;</span></button>
    </div>
    <div class="sort-btns" style="margin-left:auto">
      <button class="sort-btn active" id="ord-lim-50"  onclick="ordCambiarLimite(50,this)">50</button>
      <button class="sort-btn"        id="ord-lim-100" onclick="ordCambiarLimite(100,this)">100</button>
      <button class="sort-btn"        id="ord-lim-200" onclick="ordCambiarLimite(200,this)">200</button>
    </div>
  </div>

  <div class="tabs">
    <button class="tab-btn active" onclick="ordTab('por_iniciar')">Por iniciar <span class="tab-cnt" id="ord-cnt-por_iniciar">&#8212;</span></button>
    <button class="tab-btn"        onclick="ordTab('en_proceso')" >En proceso  <span class="tab-cnt" id="ord-cnt-en_proceso">&#8212;</span></button>
    <button class="tab-btn"        onclick="ordTab('listas')"     >Listas para entregar <span class="tab-cnt" id="ord-cnt-listas">&#8212;</span></button>
    <button class="tab-btn"        onclick="ordTab('entregadas')" >Entregadas  <span class="tab-cnt" id="ord-cnt-entregadas">&#8212;</span></button>
  </div>

  <div id="ord-sec-por_iniciar" class="seccion visible"><div class="loading-msg">Cargando&#8230;</div></div>
  <div id="ord-sec-en_proceso"  class="seccion"><div class="loading-msg">Cargando&#8230;</div></div>
  <div id="ord-sec-listas"      class="seccion"><div class="loading-msg">Cargando&#8230;</div></div>
  <div id="ord-sec-entregadas"  class="seccion"><div class="loading-msg">Cargando&#8230;</div></div>
  <div id="ord-sec-busqueda"    class="seccion"><div class="loading-msg">Cargando&#8230;</div></div>
</div>

<script>
window.ModOrdenes=(function(){

let _ordData = {}, _ordSort = 'folio', _ordDir = 'asc', _porPagina = 50;

window.ordCambiarLimite = function(n, btn) {
  _porPagina = n;
  document.querySelectorAll('[id^="ord-lim-"]').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  ordCargar();
};

function ordFmtFecha(f) {
  if (!f) return '&#8212;';
  const d = new Date(f.includes('T') ? f : f + 'T12:00:00');
  return isNaN(d) ? '&#8212;' : d.toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'});
}
function ordColorFecha(f) {
  if (!f) return '#9ca3af';
  const dias = Math.ceil((new Date(f)-new Date())/86400000);
  return dias<=0?'#dc2626':dias<=2?'#d97706':'#16a34a';
}
function ordTogglePartidas(folio) {
  const w = document.getElementById('ord-partidas-'+folio);
  const i = document.getElementById('ord-icon-'+folio);
  if (w) { w.classList.toggle('open'); i.classList.toggle('open'); }
}
function ordTab(tab) {
  document.querySelectorAll('.tab-btn').forEach((b,i) => {
    const tabs=['por_iniciar','en_proceso','listas','entregadas'];
    b.classList.toggle('active', tabs[i]===tab);
  });
  document.querySelectorAll('.seccion').forEach(s => s.classList.remove('visible'));
  document.getElementById('ord-sec-'+tab).classList.add('visible');
}
function ordSort(field) {
  _ordDir = _ordSort===field ? (_ordDir==='asc'?'desc':'asc') : 'asc';
  _ordSort = field;
  document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('ord-sort-'+field).classList.add('active');
  ['folio','cliente','fecha'].forEach(f => {
    const a = document.getElementById('ord-arrow-'+f);
    if (a) a.textContent = _ordSort===f ? (_ordDir==='asc'?'&#8593;':'&#8595;') : '&#8593;';
  });
  ordFiltrar();
}
let _ordBusqTimer = null;
function ordFiltrar() {
  const q = (document.getElementById('ord-busqueda')?.value||'').trim();
  const sortFn = (a,b) => {
    let va,vb;
    if (_ordSort==='folio')        { va=(a.folio||'').toLowerCase(); vb=(b.folio||'').toLowerCase(); }
    else if (_ordSort==='cliente') { va=(a.cliente_nombre||'').toLowerCase(); vb=(b.cliente_nombre||'').toLowerCase(); }
    else { va=a.fecha_entrega||'9999'; vb=b.fecha_entrega||'9999'; }
    return va<vb?(_ordDir==='asc'?-1:1):va>vb?(_ordDir==='asc'?1:-1):0;
  };

  const tabs    = document.querySelector('.tabs');
  const secBuq  = document.getElementById('ord-sec-busqueda');
  const secciones = ['por_iniciar','en_proceso','listas','entregadas'].map(k => document.getElementById('ord-sec-'+k));

  if (q) {
    // Con búsqueda: llamar API con debounce para traer resultados completos
    clearTimeout(_ordBusqTimer);
    _ordBusqTimer = setTimeout(async () => {
      const tabs   = document.querySelector('.tabs');
      const secBuq = document.getElementById('ord-sec-busqueda');
      const secciones = ['por_iniciar','en_proceso','listas','entregadas'].map(k => document.getElementById('ord-sec-'+k));
      if (tabs) tabs.style.display = 'none';
      secciones.forEach(s => { if(s) s.classList.remove('visible'); });
      secBuq.classList.add('visible');
      secBuq.innerHTML = '<div class="loading-msg">Buscando&#8230;</div>';
      try {
        const res  = await fetch('../api/ordenes.php?t='+Date.now()+'&busqueda='+encodeURIComponent(q));
        const data = await res.json();
        const ql   = q.toLowerCase();
        const matchFn = o => (o.folio||'').toLowerCase().includes(ql) || (o.cliente_nombre||'').toLowerCase().includes(ql);
        const foliosVistos = new Set();
        const todos = [
          ...(data.por_iniciar||[]),
          ...(data.en_proceso||[]),
          ...(data.listas||[]),
          ...(data.entregadas||[])
        ].filter(o => {
          if (foliosVistos.has(o.folio)) return false;
          foliosVistos.add(o.folio);
          return matchFn(o);
        }).sort(sortFn);

    // Ocultar tabs y secciones normales, mostrar sección búsqueda
    if (tabs) tabs.style.display = 'none';
    secciones.forEach(s => { if(s) s.classList.remove('visible'); });
    secBuq.classList.add('visible');

    if (!todos.length) {
      secBuq.innerHTML = '<div class="loading-msg">No se encontraron &#243;rdenes para <strong>"'+q+'"</strong></div>';
      return;
    }

    // Etiquetar cada orden con su estado para contexto visual
    const etiquetaEstado = o => {
      if ((_ordData.entregadas||[]).find(x=>x.folio===o.folio)) return '<span style="background:#e0f2fe;color:#0891b2;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:6px">Entregada</span>';
      if ((_ordData.listas||[]).find(x=>x.folio===o.folio))     return '<span style="background:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:6px">Lista para entregar</span>';
      if ((_ordData.en_proceso||[]).find(x=>x.folio===o.folio))  return '<span style="background:#fef9c3;color:#ca8a04;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:6px">En proceso</span>';
      return '<span style="background:#f3f4f6;color:#6b7280;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:6px">Por iniciar</span>';
    };

    secBuq.innerHTML = '<div class="loading-msg" style="text-align:left;padding:8px 0 14px;color:#6b7280">'+todos.length+' resultado'+(todos.length!==1?'s':'')+'</div>' +
      todos.map(o => {
        const esPrio = +o.prioridad===1;
        const fecha  = ordFmtFecha(o.fecha_entrega);
        const color  = ordColorFecha(o.fecha_entrega);
        return `<div class="orden-card ${esPrio?'prioritaria':''}">
          <div class="orden-head" onclick="ordTogglePartidas('bq-${o.folio}')">
            <span class="orden-folio" onclick="event.stopPropagation();irA('orden',{folio:'${o.folio}'})">${o.folio}</span>
            ${esPrio?'<span class="badge-prio">&#9889; Prior.</span>':''}
            ${etiquetaEstado(o)}
            <span class="orden-cliente">${o.cliente_nombre||'&#8212;'}</span>
            <span class="orden-asesor">${o.asesor||'&#8212;'}</span>
            <span class="orden-fecha" style="color:${color}">${fecha}</span>
            <span class="toggle-icon" id="ord-icon-bq-${o.folio}">&#9660;</span>
          </div>
          <div class="partidas-wrap" id="ord-partidas-bq-${o.folio}"></div>
        </div>`;
      }).join('');
      } catch(e) { secBuq.innerHTML = '<div class="loading-msg" style="color:#dc2626">Error al buscar</div>'; }
    }, 350);
    return;
  } else {
    // Sin búsqueda: renderizar datos ya cargados con sort
    const tabs   = document.querySelector('.tabs');
    const secBuq = document.getElementById('ord-sec-busqueda');
    const secciones = ['por_iniciar','en_proceso','listas','entregadas'].map(k => document.getElementById('ord-sec-'+k));
    if (tabs) tabs.style.display = '';
    if (secBuq) secBuq.classList.remove('visible');
    const tabActivo = document.querySelector('.tab-btn.active');
    if (!tabActivo) secciones[0]?.classList.add('visible');
    else {
      const idx = Array.from(document.querySelectorAll('.tab-btn')).indexOf(tabActivo);
      secciones.forEach((s,i) => { if(s) s.classList.toggle('visible', i===idx); });
    }
    ordRenderPorIniciar((_ordData.por_iniciar||[]).sort(sortFn));
    ordRenderEnProceso((_ordData.en_proceso||[]).sort(sortFn));
    ordRenderListas((_ordData.listas||[]).sort(sortFn));
    ordRenderEntregadas((_ordData.entregadas||[]).sort(sortFn));
  }
}

async function ordCargar() {
  try {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 15000);
    const res  = await fetch('../api/ordenes.php?t='+Date.now()+'&por_pagina='+_porPagina, { signal: ctrl.signal });
    clearTimeout(timer);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json();
    _ordData = {
      por_iniciar: data.por_iniciar||[],
      en_proceso:  data.en_proceso||[],
      listas:      data.listas||[],
      entregadas:  data.entregadas||[]
    };
    // Solo renderizar si NO hay búsqueda activa
    const q = (document.getElementById('ord-busqueda')?.value||'').trim();
    if (!q) {
      ordRenderPorIniciar(_ordData.por_iniciar);
      ordRenderEnProceso(_ordData.en_proceso);
      ordRenderListas(_ordData.listas);
      ordRenderEntregadas(_ordData.entregadas);
      // Restaurar tabs visibles
      const tabs = document.querySelector('.tabs');
      const secBuq = document.getElementById('ord-sec-busqueda');
      if (tabs) tabs.style.display = '';
      if (secBuq) secBuq.classList.remove('visible');
      const secciones = ['por_iniciar','en_proceso','listas','entregadas'].map(k => document.getElementById('ord-sec-'+k));
      const tabActivo = document.querySelector('.tab-btn.active');
      if (!tabActivo) { secciones[0]?.classList.add('visible'); }
      else {
        const idx = Array.from(document.querySelectorAll('.tab-btn')).indexOf(tabActivo);
        secciones.forEach((s,i) => { if(s) s.classList.toggle('visible', i===idx); });
      }
    }
    document.getElementById('ord-sub').textContent = 'Actualizado a las '+new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
  } catch(e) {
    document.getElementById('ord-sub').textContent = 'Error: ' + e.message;
    document.getElementById('ord-sec-por_iniciar').innerHTML = '<div class="loading-msg" style="color:#dc2626">Error al cargar &#243;rdenes. Intenta de nuevo.</div>';
  }
}

function ordRenderPorIniciar(list) {
  document.getElementById('ord-cnt-por_iniciar').textContent = list.length;
  if (!list.length) { document.getElementById('ord-sec-por_iniciar').innerHTML='<div class="card-empty">&#9989; No hay &#243;rdenes pendientes de corte</div>'; return; }
  document.getElementById('ord-sec-por_iniciar').innerHTML = list.map(o => {
    const esPrio = +o.prioridad===1;
    const pts = o.partidas.map(p=>`<div class="partida-row"><span class="partida-num">Partida ${p.partida}</span><span class="partida-desc">${p.cristal} &#183; ${p.ancho_mm}&#215;${p.alto_mm} mm</span><span class="partida-pzs">${p.piezas_pendientes} pz pendientes</span></div>`).join('');
    return `<div class="orden-card ${esPrio?'prioritaria':''}">
      <div class="orden-head" onclick="ordTogglePartidas('${o.folio}')">
        <span class="orden-folio" onclick="event.stopPropagation();irA('orden',{folio:'${o.folio}'})">${o.folio}</span>
        ${esPrio?'<span class="badge-prio">&#9889; Prior.</span>':''}
        <span class="orden-cliente">${o.cliente_nombre||'&#8212;'}</span>
        <span class="orden-asesor">${o.asesor||'&#8212;'}</span>
        <span class="orden-fecha" style="color:${ordColorFecha(o.fecha_entrega)}">${ordFmtFecha(o.fecha_entrega)}</span>
        <span class="pzs-badge pzs-pend">${o.total_pendientes} pz</span>
        <span class="toggle-icon" id="ord-icon-${o.folio}">&#9660;</span>
      </div>
      <div class="partidas-wrap" id="ord-partidas-${o.folio}">${pts}</div>
    </div>`;
  }).join('');
}

function ordRenderEnProceso(list) {
  document.getElementById('ord-cnt-en_proceso').textContent = list.length;
  const el = document.getElementById('ord-sec-en_proceso');
  if (!list.length) { el.innerHTML='<div class="card-empty">No hay &#243;rdenes en proceso</div>'; return; }
  el.innerHTML = '<div class="orden-card">'+list.map(o => {
    const esPrio=+o.prioridad===1, pct=+o.avance_pct||0, total=+o.total_piezas||0;
    const partes=[]; 
    if (+o.pendientes>0) partes.push(o.pendientes+' pend.');
    if (+o.en_corte>0)   partes.push(o.en_corte+' corte');
    if (+o.canteadas>0)  partes.push(o.canteadas+' cant.');
    const traz=(+o.trazo||0)+(+o.taladro||0);
    if (traz>0) partes.push(traz+' traz.');
    if (+o.en_horno>0)   partes.push(o.en_horno+' horno');
    if (+o.terminadas>0) partes.push(o.terminadas+' term.');
    return `<div class="entr-row">
      <span class="entr-folio" onclick="irA('orden',{folio:'${o.folio}'})">${o.folio}</span>
      ${esPrio?'<span class="badge-prio">&#9889;</span>':''}
      <span class="entr-cliente">${o.cliente_nombre||'&#8212;'}</span>
      <span class="entr-asesor">${o.asesor||'&#8212;'}</span>
      <span class="entr-fecha" style="color:${ordColorFecha(o.fecha_entrega)}">${ordFmtFecha(o.fecha_entrega)}</span>
      <span style="font-size:12px;color:#9ca3af;min-width:160px">${partes.join(' &#183; ')}</span>
      <span class="pzs-badge pzs-term">${pct}% &#183; ${o.terminadas||0}/${total}</span>
    </div>`;
  }).join('')+'</div>';
}

function ordRenderListas(list) {
  document.getElementById('ord-cnt-listas').textContent = list.length;
  const el = document.getElementById('ord-sec-listas');
  if (!list.length) { el.innerHTML='<div class="card-empty">No hay &#243;rdenes listas para entregar</div>'; return; }
  el.innerHTML='<div class="orden-card">'+list.map(o=>`<div class="entr-row">
    <span class="entr-folio" onclick="irA('orden',{folio:'${o.folio}'})">${o.folio}</span>
    ${+o.prioridad===1?'<span class="badge-prio">&#9889;</span>':''}
    <span class="entr-cliente">${o.cliente_nombre||'&#8212;'}</span>
    <span class="entr-asesor">${o.asesor||'&#8212;'}</span>
    <span class="entr-fecha" style="color:${ordColorFecha(o.fecha_entrega)}">${ordFmtFecha(o.fecha_entrega)}</span>
    <span class="pzs-badge pzs-term">${o.terminadas} pz listas</span>
  </div>`).join('')+'</div>';
}

function ordRenderEntregadas(list) {
  document.getElementById('ord-cnt-entregadas').textContent = list.length;
  const el = document.getElementById('ord-sec-entregadas');
  if (!list.length) { el.innerHTML='<div class="card-empty">No hay &#243;rdenes entregadas a&#250;n</div>'; return; }
  el.innerHTML='<div class="orden-card">'+list.map(o=>{
    const totalEntr=+o.piezas_entregadas, total=+o.total_piezas, completa=totalEntr>=total;
    const fechaCierre = o.fecha_cierre ? ordFmtFecha(o.fecha_cierre) : null;
    return `<div class="entr-row">
      <span class="entr-folio" onclick="irA('orden',{folio:'${o.folio}'})">${o.folio}</span>
      <span class="entr-cliente">${o.cliente_nombre||'&#8212;'}</span>
      <span class="entr-asesor">${o.asesor||'&#8212;'}</span>
      <span class="entr-pzs">${totalEntr} de ${total} pzs</span>
      <span class="entr-fecha">&#218;lt. entrega: ${ordFmtFecha(o.ultima_entrega)}</span>
      <span class="cierre-pill ${completa?'cierre-ok':'cierre-parcial'}">
        ${completa ? '&#9989; Completa' : '&#9203; Parcial: '+totalEntr+'/'+total}
      </span>
    </div>`;
  }).join('')+'</div>';
}

ordCargar();
setInterval(ordCargar, 60000);

// Exponer al scope global ANTES de cerrar el IIFE
window.ordTab             = ordTab;
window.ordSort            = ordSort;
window.ordFiltrar         = ordFiltrar;
window.ordTogglePartidas  = ordTogglePartidas;
window.irA = typeof irA !== 'undefined' ? irA : function(){};
return{init:ordCargar};
})();
ModOrdenes.init();
</script>