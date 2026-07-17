<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('ver_reportes');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=productividad'); exit;
}
header('Content-Type: text/html; charset=utf-8');
$maps_key = defined('GOOGLE_MAPS_KEY') ? GOOGLE_MAPS_KEY : '';
?>
<style>

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {

  --bg:      #0d0d14;

  --surface: #13131e;

  --card:    #1a1a28;

  --border:  #252535;

  --text:    #eeeeff;

  --muted:   #5a5a7a;

  --accent:  #f5a623;

  --green:   #22c55e;

  --red:     #ef4444;

  --amber:   #f59e0b;

  --blue:    #3b82f6;

  --c-corte:    #f59e0b;

  --c-canteado: #06b6d4;

  --c-trazo:    #a855f7;

  --c-taladro:  #ec4899;

  --c-horno:    #ef4444;

}

body { background: var(--bg); color: var(--text); font-family: -apple-system, 'Helvetica Neue', sans-serif; min-height: 100dvh; }



/* Header */

.header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 13px 20px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }

.logo { font-size: 14px; font-weight: 800; letter-spacing: 2px; color: var(--accent); }

.page-title { font-size: 11px; color: var(--muted); letter-spacing: 1.5px; text-transform: uppercase; margin-left: 14px; }

.header-right { display: flex; align-items: center; gap: 10px; }

.btn-back { background: var(--card); color: var(--muted); border: 1px solid var(--border); padding: 6px 14px; border-radius: 8px; font-size: 12px; text-decoration: none; }

.btn-refresh { background: var(--accent); color: #000; border: none; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 800; cursor: pointer; }

.live { display: flex; align-items: center; gap: 5px; font-size: 11px; color: var(--muted); }

.live-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green); box-shadow: 0 0 5px var(--green); animation: pulse 2s infinite; }

@keyframes pulse { 0%,100%{opacity:1}50%{opacity:.3} }



/* Tabs */

.tabs { background: var(--surface); border-bottom: 1px solid var(--border); padding: 0 20px; display: flex; gap: 2px; overflow-x: auto; }

.tab { padding: 11px 18px; font-size: 11px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; border: none; background: none; color: var(--muted); border-bottom: 2px solid transparent; transition: all .2s; white-space: nowrap; }

.tab.active { color: var(--accent); border-bottom-color: var(--accent); }

.tab:hover:not(.active) { color: var(--text); }



.main { padding: 18px 20px; max-width: 1400px; margin: 0 auto; }



/* &#9472;&#9472; Vista Hora &#9472;&#9472; */

.hora-wrap { display: flex; flex-direction: column; gap: 14px; }



/* Resumen por estaci&#243;n */

.est-resumen-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px; margin-bottom: 6px; }

.est-res-card { background: var(--card); border-radius: 14px; border: 1px solid var(--border); padding: 14px 16px; }

.est-res-header { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }

.est-res-icon { font-size: 18px; }

.est-res-nombre { font-size: 11px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }

.est-res-stat { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; }

.est-res-lbl { font-size: 10px; color: var(--muted); font-weight: 600; }

.est-res-val { font-size: 16px; font-weight: 800; }

.est-res-unit { font-size: 10px; color: var(--muted); margin-left: 3px; }



/* Chips pico/baja */

.pico-baja { display: flex; gap: 6px; margin-top: 10px; }

.pb-chip { flex: 1; border-radius: 8px; padding: 6px 8px; text-align: center; }

.pb-chip-lbl { font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }

.pb-chip-val { font-size: 11px; font-weight: 700; }

.chip-pico { background: rgba(34,197,94,.1); }

.chip-pico .pb-chip-lbl { color: var(--green); }

.chip-baja { background: rgba(239,68,68,.1); }

.chip-baja .pb-chip-lbl { color: var(--red); }



/* Tabla de franjas */

.franjas-card { background: var(--card); border-radius: 14px; border: 1px solid var(--border); overflow: hidden; }

.franjas-card-title { padding: 12px 18px; font-size: 11px; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); }

.franjas-table { width: 100%; border-collapse: collapse; }

.franjas-table th { padding: 9px 14px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); text-align: left; border-bottom: 1px solid var(--border); background: var(--surface); }

.franjas-table td { padding: 10px 14px; font-size: 13px; border-bottom: 1px solid var(--border); }

.franjas-table tr:last-child td { border-bottom: none; }

.franjas-table tr.extra-row { background: rgba(245,166,35,.04); }

.franjas-table tr.pico-row  { background: rgba(34,197,94,.05); }

.franjas-table tr.baja-row  { background: rgba(239,68,68,.05); }

/* Tabla de trazabilidad de rutas — clase propia para no heredar el @media que oculta
   columnas de .franjas-table en pantallas angostas (aquí todas las columnas importan). */
.traza-table-wrap { overflow-x: auto; }
.traza-table { width: 100%; border-collapse: collapse; min-width: 760px; }
.traza-table th { padding: 9px 12px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: var(--muted); text-align: left; border-bottom: 1px solid var(--border); background: var(--surface); white-space: nowrap; }
.traza-table td { padding: 10px 12px; font-size: 13px; border-bottom: 1px solid var(--border); white-space: nowrap; }
.traza-table tr:last-child td { border-bottom: none; }

.franja-label { font-size: 12px; font-weight: 700; }

.tipo-badge { font-size: 9px; font-weight: 800; padding: 2px 7px; border-radius: 4px; margin-left: 6px; }

.tipo-normal { background: rgba(34,197,94,.15); color: var(--green); }

.tipo-extra  { background: rgba(245,166,35,.15); color: var(--accent); }

.tipo-pico   { background: rgba(34,197,94,.2); color: var(--green); }

.tipo-baja   { background: rgba(239,68,68,.2); color: var(--red); }

.val-cell { font-weight: 800; }

.muerto-warn { font-size: 11px; color: var(--red); font-weight: 700; }

.muerto-ok   { font-size: 11px; color: var(--muted); }

.delta-cell  { font-size: 12px; }

.delta-warn  { color: var(--red); font-weight: 700; }

.delta-ok    { color: var(--green); }



/* &#9472;&#9472; Vista Comparativa &#9472;&#9472; */

.comp-grid { display: flex; flex-direction: column; gap: 14px; }

.comp-card { background: var(--card); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; }

.comp-header { padding: 14px 20px; background: var(--surface); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }

.comp-title { font-size: 16px; font-weight: 800; }

.comp-sub   { font-size: 11px; color: var(--muted); margin-top: 2px; }

.comp-hrs   { font-size: 11px; color: var(--muted); }

.comp-hrs b { color: var(--accent); }

.comp-body  { padding: 16px 20px; display: grid; grid-template-columns: repeat(5,1fr); gap: 16px; }

.comp-est-title { font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }

.comp-stat { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; }

.comp-stat-lbl { font-size: 10px; color: var(--muted); }

.comp-stat-val { font-size: 15px; font-weight: 800; }

.comp-stat-unit { font-size: 10px; color: var(--muted); margin-left: 3px; }

.divider { height: 1px; background: var(--border); margin: 8px 0; }

.comp-tasa { display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px; }

.comp-tasa span { color: var(--muted); }

.comp-tasa b { color: var(--text); }

.comp-muertos { font-size: 11px; color: var(--muted); display: flex; justify-content: space-between; }

.comp-muertos b { color: var(--red); }

.cristal-mini { margin-top: 8px; }

.cristal-mini-item { display: flex; justify-content: space-between; font-size: 10px; padding: 3px 0; border-bottom: 1px solid var(--border); }

.cristal-mini-item:last-child { border-bottom: none; }

.cristal-mini-name { color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 120px; }

.cristal-mini-val  { font-weight: 700; flex-shrink: 0; margin-left: 6px; }



/* Loading */

.loading-wrap { display: flex; align-items: center; justify-content: center; padding: 80px; flex-direction: column; gap: 14px; }

.spin { width: 22px; height: 22px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: sp .7s linear infinite; }

@keyframes sp { to { transform: rotate(360deg); } }

.loading-txt { font-size: 11px; color: var(--muted); letter-spacing: 1px; text-transform: uppercase; }



@media (max-width: 1100px) {

  .est-resumen-grid { grid-template-columns: repeat(3,1fr); }

  .comp-body { grid-template-columns: repeat(3,1fr); }

}

@media (max-width: 700px) {

  .est-resumen-grid { grid-template-columns: 1fr 1fr; }

  .comp-body { grid-template-columns: 1fr 1fr; }

  .franjas-table th:nth-child(n+4),

  .franjas-table td:nth-child(n+4) { display: none; }

}

</style>

<div style="background:#13131e;border-bottom:1px solid #252535;padding:10px 20px;display:flex;align-items:center;gap:12px;">
  <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:#5a5a7a;">
    <div style="width:6px;height:6px;border-radius:50%;background:#22c55e;box-shadow:0 0 5px #22c55e;animation:pulse 2s infinite"></div>
    <span id="tsLabel">&#8212;</span>
  </div>
  <button onclick="cargar()" style="background:#f5a623;color:#000;border:none;padding:5px 14px;border-radius:8px;font-size:12px;font-weight:800;cursor:pointer">&#8635; Actualizar</button>
</div>








<div class="tabs">

  <button class="tab active" onclick="cambiarVista('hora')"   id="tab-hora">&#9201; Por Hora</button>

  <button class="tab"        onclick="cambiarVista('dia')"    id="tab-dia">&#128197; Por D&#237;a</button>

  <button class="tab"        onclick="cambiarVista('semana')" id="tab-semana">&#128198; Por Semana</button>

  <button class="tab"        onclick="cambiarVista('mes')"    id="tab-mes">&#128467; Por Mes</button>

  <button class="tab"        onclick="cambiarVista('rutas')"  id="tab-rutas">&#128666; Rutas de Entrega</button>

</div>

<div id="rutas-fecha-wrap" style="display:none;padding:10px 20px;background:var(--surface);border-bottom:1px solid var(--border)">
  <input type="date" id="rutas-fecha" onchange="cargar()" style="font-size:13px;padding:5px 8px;border:1px solid var(--border);border-radius:6px">
</div>



<div class="main" id="main">

  <div class="loading-wrap"><div class="spin"></div><div class="loading-txt">Cargando&#8230;</div></div>

</div>

<div id="modalReplay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:9000;align-items:center;justify-content:center;padding:16px">
  <div style="background:var(--surface,#13131e);border:1px solid var(--border,#252535);border-radius:12px;width:100%;max-width:900px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--border,#252535)">
      <div>
        <div style="font-weight:800;font-size:15px" id="replayTitulo">Recorrido de ruta</div>
        <div style="font-size:11px;color:var(--muted,#5a5a7a)" id="replaySub">&#8212;</div>
      </div>
      <button onclick="cerrarReplay()" style="background:none;border:none;color:var(--muted,#5a5a7a);font-size:22px;cursor:pointer;line-height:1">&times;</button>
    </div>
    <div id="mapaReplay" style="flex:1;min-height:380px;background:#1a1a28"></div>
    <div style="padding:10px 16px;border-top:1px solid var(--border,#252535);display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <button id="btnPlayReplay" onclick="togglePlayReplay()" style="background:#f5a623;color:#000;border:none;width:34px;height:34px;border-radius:50%;font-size:14px;font-weight:800;cursor:pointer">&#9654;</button>
      <input type="range" id="sliderReplay" min="0" max="0" value="0" style="flex:1;min-width:140px" oninput="scrubReplay(this.value)">
      <span id="replayTiempo" style="font-size:11px;color:var(--muted,#5a5a7a);min-width:52px;text-align:right">&#8212;</span>
      <select id="velReplay" onchange="cambiarVelocidadReplay(this.value)" style="font-size:12px;padding:4px 6px;border-radius:6px;border:1px solid var(--border,#252535);background:var(--card,#1a1a28);color:var(--text,#eeeeff)">
        <option value="1">1x</option>
        <option value="4" selected>4x</option>
        <option value="10">10x</option>
        <option value="30">30x</option>
      </select>
    </div>
  </div>
</div>



<script>
window.ModProductividad=(function(){


const API = '../api/productividad.php';

let vista = 'hora';



const EST = {

  corte:    { nombre:'Corte',    icon:'&#9986;&#65039;',  color:'var(--c-corte)',    unidad:'m&#178;' },

  canteado: { nombre:'Canteado', icon:'&#128297;',  color:'var(--c-canteado)', unidad:'ml' },

  trazo:    { nombre:'Trazo',    icon:'&#9999;&#65039;',  color:'var(--c-trazo)',    unidad:'pzs' },

  taladro:  { nombre:'Taladro',  icon:'&#128295;',  color:'var(--c-taladro)',  unidad:'pzs' },

  horno:    { nombre:'Horno',    icon:'&#128293;',  color:'var(--c-horno)',    unidad:'m&#178;' },

};

const ESTS = Object.keys(EST);



// fmt — definida en utils.js



function cambiarVista(v) {

  vista = v;

  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));

  document.getElementById('tab-'+v).classList.add('active');

  document.getElementById('rutas-fecha-wrap').style.display = (v === 'rutas') ? 'block' : 'none';

  if (v === 'rutas' && !document.getElementById('rutas-fecha').value) {
    document.getElementById('rutas-fecha').value = new Date().toISOString().slice(0,10);
  }

  cargar();

}



async function cargar() {

  document.getElementById('main').innerHTML = `<div class="loading-wrap"><div class="spin"></div><div class="loading-txt">Calculando&#8230;</div></div>`;

  try {

    const vistaApi = (vista === 'rutas') ? 'trazabilidad_rutas' : vista;
    const fechaQs  = (vista === 'rutas') ? '&fecha=' + document.getElementById('rutas-fecha').value : '';
    const res  = await fetch(API + '?vista=' + vistaApi + fechaQs + '&t=' + Date.now());

    const data = await res.json();

    if (data.error) {

      document.getElementById('main').innerHTML = `<div class="loading-wrap"><div class="loading-txt">&#9888;&#65039; ${data.error}</div></div>`;

      return;

    }

    if (vista==='hora')   renderHora(data);

    else if (vista==='dia')    renderComp(data.dias,    'label','fecha','hrs_prod','D&#237;as recientes');

    else if (vista==='semana') renderComp(data.semanas, 'label','desde','hrs_prod','Semanas');

    else if (vista==='mes')    renderComp(data.meses,   'label','mes',  'hrs_prod','Meses');

    else if (vista==='rutas')  renderTrazabilidadRutas(data);

    document.getElementById('tsLabel').textContent =

      'Act. ' + new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});

  } catch(e) {

    document.getElementById('main').innerHTML = `<div class="loading-wrap"><div class="loading-txt">&#10060; Error de conexi&#243;n</div></div>`;

  }

}



// &#9472;&#9472; Render Vista Hora &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;

function renderHora(data) {

  const franjas = data.franjas || [];

  const pico    = data.pico || {};

  const baja    = data.baja || {};



  if (!franjas.length) {

    document.getElementById('main').innerHTML = '<div class="loading-wrap"><div class="loading-txt">Sin actividad registrada hoy</div></div>';

    return;

  }



  // Calcular totales del d&#237;a por estaci&#243;n

  const totales = {};

  ESTS.forEach(e => {

    totales[e] = { total:0, muertos:0, deltas:[] };

    franjas.forEach(f => {

      const d = f.datos[e];

      totales[e].total   += parseFloat(d.total||0);

      totales[e].muertos += parseInt(d.tiempos_muertos||0);

      if (d.prom_delta_min !== null) totales[e].deltas.push(parseFloat(d.prom_delta_min));

    });

    totales[e].total = Math.round(totales[e].total * 100) / 100;

    totales[e].promDelta = totales[e].deltas.length

      ? Math.round(totales[e].deltas.reduce((a,b)=>a+b,0)/totales[e].deltas.length * 10)/10

      : null;

  });



  // Tarjetas resumen por estaci&#243;n

  let html = `<div class="hora-wrap">`;

  html += `<div class="est-resumen-grid">`;



  ESTS.forEach(e => {

    const est  = EST[e];

    const tot  = totales[e];

    const isInt = e==='trazo'||e==='taladro';

    const dec   = isInt ? 0 : 2;



    // Franja pico y baja

    const iP = pico[e]; const iB = baja[e];

    const picoLabel = iP !== null && iP !== undefined ? franjas[iP]?.label : null;

    const bajaLabel = iB !== null && iB !== undefined ? franjas[iB]?.label : null;



    html += `

      <div class="est-res-card">

        <div class="est-res-header">

          <span class="est-res-icon">${est.icon}</span>

          <span class="est-res-nombre" style="color:${est.color}">${est.nombre}</span>

        </div>

        <div class="est-res-stat">

          <span class="est-res-lbl">Total hoy</span>

          <span><span class="est-res-val" style="color:${est.color}">${fmt(tot.total,dec)}</span><span class="est-res-unit">${est.unidad}</span></span>

        </div>

        <div class="est-res-stat">

          <span class="est-res-lbl">&#9201; Prom entre escaneos</span>

          <span class="est-res-val" style="font-size:14px;color:${tot.promDelta!==null&&tot.promDelta>=10?'var(--red)':'var(--green)'}">

            ${tot.promDelta !== null ? tot.promDelta + ' min' : '&#8212;'}

          </span>

        </div>

        <div class="est-res-stat">

          <span class="est-res-lbl">&#128308; Tiempos muertos</span>

          <span class="est-res-val" style="font-size:14px;color:${tot.muertos>0?'var(--red)':'var(--muted)'}">

            ${tot.muertos > 0 ? tot.muertos : '0'}

          </span>

        </div>

        <div class="pico-baja">

          <div class="pb-chip chip-pico">

            <div class="pb-chip-lbl">&#128293; Pico</div>

            <div class="pb-chip-val">${picoLabel || '&#8212;'}</div>

          </div>

          <div class="pb-chip chip-baja">

            <div class="pb-chip-lbl">&#128034; Baja</div>

            <div class="pb-chip-val">${bajaLabel || '&#8212;'}</div>

          </div>

        </div>

      </div>`;

  });

  html += `</div>`;



  // Tabla de franjas

  html += `

    <div class="franjas-card">

      <div class="franjas-card-title">Detalle por franja horaria</div>

      <div style="overflow-x:auto">

      <table class="franjas-table">

        <thead>

          <tr>

            <th>Franja</th>

            <th style="color:var(--c-corte)">&#9986;&#65039; Corte m&#178;</th>

            <th style="color:var(--c-canteado)">&#128297; Canteado ml</th>

            <th style="color:var(--c-trazo)">&#9999;&#65039; Trazo pzs</th>

            <th style="color:var(--c-taladro)">&#128295; Taladro pzs</th>

            <th style="color:var(--c-horno)">&#128293; Horno m&#178;</th>

            <th>&#9201; Prom espera</th>

            <th>&#128308; T.Muertos</th>

          </tr>

        </thead>

        <tbody>`;



  franjas.forEach((f, i) => {

    // Determinar clase de fila

    const esPico = ESTS.some(e => pico[e] === i);

    const esBaja = ESTS.some(e => baja[e] === i);

    const cls = f.tipo==='extra' ? 'extra-row' : (esPico ? 'pico-row' : (esBaja ? 'baja-row' : ''));



    // Badge de franja

    let badge = `<span class="tipo-badge tipo-${f.tipo}">${f.tipo==='normal'?'TURNO':'EXTRA'}</span>`;

    if (esPico && f.tipo==='normal') badge += `<span class="tipo-badge tipo-pico">&#128293; PICO</span>`;

    if (esBaja && f.tipo==='normal') badge += `<span class="tipo-badge tipo-baja">&#128034; BAJA</span>`;



    // Valores por estaci&#243;n

    const vals = ESTS.map(e => {

      const d = f.datos[e];

      const isInt = e==='trazo'||e==='taladro';

      const dec = isInt ? 0 : 2;

      const v = parseFloat(d.total||0);

      return `<td class="val-cell" style="color:${v>0?EST[e].color:'var(--muted)'}">

        ${v > 0 ? fmt(v, dec) : '&#8212;'}

      </td>`;

    }).join('');



    // Promedio delta (promedio de las estaciones con datos)

    const deltasF = ESTS.map(e => f.datos[e].prom_delta_min).filter(v => v !== null);

    const promDeltaF = deltasF.length ? (deltasF.reduce((a,b)=>a+b,0)/deltasF.length).toFixed(1) : null;

    const muertosF   = ESTS.reduce((sum,e) => sum + parseInt(f.datos[e].tiempos_muertos||0), 0);



    html += `<tr class="${cls}">

      <td class="franja-label" ${f.tipo==='extra'?'onclick="toggleExtraDetalle(this,\''+f.desde.split(' ')[0]+'\')" style="cursor:pointer"':''}>
        ${f.label}${badge}${f.tipo==='extra'?' <span style="font-size:10px;color:#5a5a7a">&#9660; ver detalle</span>':''}</td>

      ${vals}

      <td class="delta-cell ${promDeltaF!==null&&promDeltaF>=10?'delta-warn':'delta-ok'}">

        ${promDeltaF !== null ? promDeltaF + ' min' : '&#8212;'}

      </td>

      <td class="${muertosF>0?'muerto-warn':'muerto-ok'}">${muertosF > 0 ? muertosF : '&#8212;'}</td>

    </tr>`;

  });



  html += `</tbody></table></div></div></div>`;

  document.getElementById('main').innerHTML = html;

}



// &#9472;&#9472; Render Vista Comparativa &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;

function renderComp(periodos, labelKey, subKey, hrsKey, titulo) {

  if (!periodos || !periodos.length) {

    document.getElementById('main').innerHTML = '<div class="loading-wrap"><div class="loading-txt">Sin datos</div></div>';

    return;

  }



  let html = `<div class="comp-grid">`;



  periodos.forEach(p => {

    const hrsP = parseFloat(p[hrsKey]||0).toFixed(1);

    html += `

      <div class="comp-card">

        <div class="comp-header">

          <div>

            <div class="comp-title">${p[labelKey]}</div>

            <div class="comp-sub">${p[subKey]||''}</div>

          </div>

          <div class="comp-hrs">Horas productivas: <b>${hrsP} hrs</b></div>

        </div>

        <div class="comp-body">`;



    ESTS.forEach(e => {

      const est   = EST[e];

      const d     = p.datos[e];

      const isInt = e==='trazo'||e==='taladro';

      const dec   = isInt ? 0 : 2;

      const hrsNum = parseFloat(p[hrsKey]||0);



      html += `

        <div>

          <div class="comp-est-title" style="color:${est.color}">${est.icon} ${est.nombre}</div>

          <div class="comp-stat">

            <span class="comp-stat-lbl">Total</span>

            <span><span class="comp-stat-val" style="color:${est.color}">${fmt(d.total, dec)}</span><span class="comp-stat-unit">${est.unidad}</span></span>

          </div>

          <div class="divider"></div>

          <div class="comp-tasa">

            <span>Por hora</span>

            <b>${fmt(d.tasa_hr, dec)} ${est.unidad}/hr</b>

          </div>

          <div class="comp-tasa">

            <span>Prom entre escaneos</span>

            <b style="color:${d.prom_delta_min!==null&&d.prom_delta_min>=10?'var(--red)':'var(--green)'}">

              ${d.prom_delta_min !== null ? d.prom_delta_min + ' min' : '&#8212;'}

            </b>

          </div>

          <div class="comp-muertos">

            <span>Tiempos muertos</span>

            <b>${d.tiempos_muertos > 0 ? d.tiempos_muertos : '&#8212;'}</b>

          </div>

          ${e==='horno' && d.por_cristal && d.por_cristal.length > 0 ? `

          <div class="cristal-mini">

            ${d.por_cristal.slice(0,5).map(c => `

              <div class="cristal-mini-item">

                <span class="cristal-mini-name">${c.cristal}</span>

                <span class="cristal-mini-val">${c.m2} m&#178;</span>

              </div>`).join('')}

          </div>` : ''}

        </div>`;

    });



    html += `</div></div>`;

  });



  html += `</div>`;

  document.getElementById('main').innerHTML = html;

}



async function toggleExtraDetalle(td, fecha) {
  const tr    = td.closest('tr');
  const exist = tr.nextElementSibling;
  if (exist && exist.classList.contains('extra-detalle-row')) {
    exist.remove();
    return;
  }
  const loadTr = document.createElement('tr');
  loadTr.className = 'extra-detalle-row';
  loadTr.innerHTML = '<td colspan="8" style="padding:12px 20px;background:rgba(245,166,35,.06);color:#5a5a7a;font-size:12px">Cargando...</td>';
  tr.after(loadTr);

  try {
    const res  = await fetch('../api/productividad.php?vista=detalle_extra&fecha=' + fecha);
    const data = await res.json();
    const rows = data.detalle || [];
    if (!rows.length) {
      loadTr.innerHTML = '<td colspan="8" style="padding:12px 20px;background:rgba(245,166,35,.06);color:#5a5a7a">Sin piezas registradas fuera de turno</td>';
      return;
    }
    loadTr.innerHTML = `<td colspan="8" style="padding:0;background:rgba(245,166,35,.04)">
      <table style="width:100%;border-collapse:collapse;font-size:12px">
        <thead><tr style="background:rgba(245,166,35,.12)">
          <th style="padding:8px 14px;text-align:left;color:#f5a623">Hora</th>
          <th style="padding:8px 14px;text-align:left;color:#f5a623">Estaci&#243;n</th>
          <th style="padding:8px 14px;text-align:left;color:#f5a623">Folio</th>
          <th style="padding:8px 14px;text-align:left;color:#f5a623">Cliente</th>
          <th style="padding:8px 14px;text-align:left;color:#f5a623">Pieza</th>
          <th style="padding:8px 14px;text-align:left;color:#f5a623">Medidas</th>
          <th style="padding:8px 14px;text-align:left;color:#f5a623">Operador</th>
        </tr></thead>
        <tbody>
          ${rows.map(r => `<tr style="border-bottom:1px solid rgba(37,37,53,.8)">
            <td style="padding:8px 14px;font-weight:700;color:#f5a623">${r.hora}</td>
            <td style="padding:8px 14px;color:#eeeeff">${r.estacion}</td>
            <td style="padding:8px 14px;font-weight:700;color:#3b82f6;cursor:pointer" onclick="irA('orden',{folio:'${r.folio}'})">${r.folio}</td>
            <td style="padding:8px 14px;color:#eeeeff">${r.cliente||'&#8212;'}</td>
            <td style="padding:8px 14px;color:#5a5a7a">P${r.partida} #${r.pieza}</td>
            <td style="padding:8px 14px;color:#5a5a7a">${r.medidas}</td>
            <td style="padding:8px 14px;color:#5a5a7a">${r.operador||'&#8212;'}</td>
          </tr>`).join('')}
        </tbody>
        <tfoot><tr style="background:rgba(245,166,35,.08)">
          <td colspan="7" style="padding:8px 14px;font-size:11px;color:#f5a623;font-weight:700">
            ${rows.length} movimientos fuera de turno
          </td>
        </tr></tfoot>
      </table></td>`;
  } catch(e) {
    loadTr.innerHTML = '<td colspan="8" style="padding:12px 20px;color:#ef4444">Error al cargar</td>';
  }
}

function esc(s) {
  return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtHora(dt) {
  if (!dt) return '&#8212;';
  return new Date(dt.replace(' ','T')).toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit'});
}

const UNIDAD_LBL = { gris: '&#128667; Gris', blanca: '&#128667; Blanca' };

function renderTrazabilidadRutas(data) {
  const filas = data.trazabilidad || [];
  if (!filas.length) {
    document.getElementById('main').innerHTML = '<div class="loading-wrap"><div class="loading-txt">Sin rutas para esta fecha</div></div>';
    return;
  }

  // Agrupar por ruta_id (las filas ya vienen ordenadas por unidad/secuencia, así que
  // los ruta_id salen naturalmente en bloques consecutivos).
  const grupos = [];
  let actual = null;
  filas.forEach(f => {
    if (!actual || actual.ruta_id !== f.ruta_id) {
      actual = { ruta_id: f.ruta_id, unidad: f.unidad, chofer: f.chofer, estado: f.ruta_estado, filas: [] };
      grupos.push(actual);
    }
    actual.filas.push(f);
  });

  let html = `<div style="padding:16px 20px">`;

  grupos.forEach(g => {
    html += `<div style="display:flex;align-items:center;justify-content:space-between;margin:14px 0 6px">
      <div style="font-size:13px;font-weight:700">${UNIDAD_LBL[g.unidad] || esc(g.unidad)} &#8212; ${esc(g.chofer) || 'Sin chofer'}</div>
      <button onclick="abrirReplay(${g.ruta_id})" style="background:#3b82f6;color:#fff;border:none;padding:5px 12px;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer">&#128205; Ver recorrido</button>
    </div>
    <div class="traza-table-wrap">
    <table class="traza-table">
      <thead><tr>
        <th>Orden</th><th>Cliente</th><th>Unidad</th><th>Chofer</th>
        <th>Salida (QR)</th><th>Llegada (GPS)</th><th>Tiempo muerto</th><th>Entregado</th>
      </tr></thead>
      <tbody>`;

    g.filas.forEach(f => {
      const muerto = (f.tiempo_muerto_min == null) ? '&#8212;'
        : (f.tiempo_muerto_min <= 0 ? '0 min' : f.tiempo_muerto_min + ' min');
      const muertoColor = (f.tiempo_muerto_min != null && f.tiempo_muerto_min > 10) ? 'color:#dc2626;font-weight:700' : '';
      html += `<tr>
        <td style="font-weight:700;color:#2563eb">${esc(f.folio)}</td>
        <td>${esc(f.cliente)}</td>
        <td>${UNIDAD_LBL[f.unidad] || esc(f.unidad)}</td>
        <td>${esc(f.chofer) || '&#8212;'}</td>
        <td>${fmtHora(f.salida_qr_at)}</td>
        <td>${fmtHora(f.llegada_gps_at)}</td>
        <td style="${muertoColor}">${muerto}</td>
        <td>${fmtHora(f.entregado_at)}</td>
      </tr>`;
    });

    html += `</tbody></table></div>`;
  });

  html += `</div>`;
  document.getElementById('main').innerHTML = html;
}

// ── Replay de ruta: mapa con paradas numeradas + track GPS real animado ──────
let _replay = { track: [], idx: 0, playing: false, timer: null, speed: 4, map: null, marker: null, poly: null };

function cerrarReplay() {
  document.getElementById('modalReplay').style.display = 'none';
  pausarReplay();
}

async function abrirReplay(ruta_id) {
  document.getElementById('modalReplay').style.display = 'flex';
  document.getElementById('replayTitulo').textContent = 'Cargando recorrido…';
  document.getElementById('replaySub').textContent = '—';
  pausarReplay();

  try {
    const res = await fetch(API + '?vista=ruta_replay&ruta_id=' + ruta_id + '&t=' + Date.now());
    const data = await res.json();
    if (data.error) { document.getElementById('replayTitulo').textContent = '⚠️ ' + data.error; return; }

    document.getElementById('replayTitulo').textContent = (UNIDAD_LBL[data.ruta.unidad] || data.ruta.unidad).replace(/&#\d+;/g,'') + ' — ' + (data.ruta.chofer || 'Sin chofer');
    document.getElementById('replaySub').textContent = data.ruta.fecha + ' · ' + data.paradas.length + ' paradas · ' + data.track.length + ' puntos GPS';

    esperarGoogleMaps(function() { dibujarReplay(data); });
  } catch (e) {
    document.getElementById('replayTitulo').textContent = '❌ Error de conexión';
  }
}

function dibujarReplay(data) {
  const el = document.getElementById('mapaReplay');
  const paradas = (data.paradas || []).filter(p => p.lat && p.lng);
  const track   = data.track || [];

  const center = track.length ? { lat: +track[0].lat, lng: +track[0].lng }
    : (paradas.length ? { lat: +paradas[0].lat, lng: +paradas[0].lng } : { lat: 25.6931, lng: -100.4807 });

  const map = new google.maps.Map(el, { zoom: 12, center, disableDefaultUI: true, zoomControl: true, mapTypeControl: false, streetViewControl: false });
  const bounds = new google.maps.LatLngBounds();

  // Pin planta
  const planta = { lat: 25.6931510, lng: -100.4803430 };
  new google.maps.Marker({ position: planta, map, label: { text: '🏭', fontSize: '18px' }, title: 'Planta' });
  bounds.extend(planta);

  // Paradas numeradas (orden ya optimizado)
  paradas.forEach((p, i) => {
    const pos = { lat: +p.lat, lng: +p.lng };
    bounds.extend(pos);
    const color = p.estado === 'entregado' ? '#16a34a' : (p.estado === 'no_entregado' ? '#dc2626' : '#f59e0b');
    const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="26" height="34" viewBox="0 0 32 42">'
      + '<path d="M16 0C7.16 0 0 7.16 0 16c0 11.65 16 26 16 26S32 27.65 32 16C32 7.16 24.84 0 16 0z" fill="' + color + '"/>'
      + '<circle cx="16" cy="16" r="10" fill="white"/>'
      + '<text x="16" y="21" text-anchor="middle" font-size="12" font-weight="bold" fill="' + color + '">' + (i + 1) + '</text></svg>';
    const marker = new google.maps.Marker({
      position: pos, map,
      icon: { url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg), scaledSize: new google.maps.Size(26, 34), anchor: new google.maps.Point(13, 34) },
      title: p.folio + ' — ' + p.cliente_nombre,
    });
    const iw = new google.maps.InfoWindow({ content: '<div style="font-size:12px"><b>' + esc(p.folio) + '</b><br>' + esc(p.cliente_nombre) + '</div>' });
    marker.addListener('click', () => iw.open(map, marker));
  });

  // Trazo real del recorrido (track GPS ya guardado en gps_posiciones — no llama a Google)
  const path = track.map(t => ({ lat: +t.lat, lng: +t.lng }));
  path.forEach(p => bounds.extend(p));
  const poly = new google.maps.Polyline({ path, map, strokeColor: '#3b82f6', strokeOpacity: 0.8, strokeWeight: 3 });

  const truckMarker = new google.maps.Marker({
    map, position: path[0] || center,
    icon: { url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28"><text x="14" y="22" font-size="22" text-anchor="middle">🚚</text></svg>'), scaledSize: new google.maps.Size(28, 28), anchor: new google.maps.Point(14, 14) },
    zIndex: 999,
  });

  if (bounds.isEmpty() === false) map.fitBounds(bounds, 40);

  _replay = { track, idx: 0, playing: false, timer: null, speed: parseInt(document.getElementById('velReplay').value, 10) || 4, map, marker: truckMarker, poly };

  document.getElementById('sliderReplay').max = Math.max(0, track.length - 1);
  document.getElementById('sliderReplay').value = 0;
  actualizarReplayUI();
}

function actualizarReplayUI() {
  const t = _replay.track[_replay.idx];
  document.getElementById('sliderReplay').value = _replay.idx;
  document.getElementById('replayTiempo').textContent = t ? fmtHora(t.capturado_at) : '—';
  document.getElementById('btnPlayReplay').innerHTML = _replay.playing ? '&#10074;&#10074;' : '&#9654;';
}

function moverCamionA(idx) {
  if (!_replay.track.length) return;
  idx = Math.max(0, Math.min(_replay.track.length - 1, idx));
  _replay.idx = idx;
  const t = _replay.track[idx];
  if (t && _replay.marker) _replay.marker.setPosition({ lat: +t.lat, lng: +t.lng });
  actualizarReplayUI();
}

function scrubReplay(v) {
  pausarReplay();
  moverCamionA(parseInt(v, 10));
}

function togglePlayReplay() {
  if (_replay.playing) { pausarReplay(); return; }
  if (!_replay.track.length) return;
  if (_replay.idx >= _replay.track.length - 1) _replay.idx = 0;
  _replay.playing = true;
  actualizarReplayUI();
  tickReplay();
}

function tickReplay() {
  if (!_replay.playing) return;
  moverCamionA(_replay.idx + 1);
  if (_replay.idx >= _replay.track.length - 1) { pausarReplay(); return; }
  const intervaloBaseMs = 500;
  _replay.timer = setTimeout(tickReplay, Math.max(30, intervaloBaseMs / _replay.speed));
}

function pausarReplay() {
  _replay.playing = false;
  if (_replay.timer) { clearTimeout(_replay.timer); _replay.timer = null; }
  actualizarReplayUI();
}

function cambiarVelocidadReplay(v) {
  _replay.speed = parseInt(v, 10) || 1;
}

// El SPA no limpia scripts entre navegaciones (mismo patrón de Logística Rutas, UPD-335) —
// si Maps JS ya está cargado o cargándose, no se vuelve a inyectar el script.
function esperarGoogleMaps(cb) {
  if (window.google && window.google.maps) { cb(); return; }
  if (window._prodMapsLoading) { window._prodMapsLoading.push(cb); return; }
  window._prodMapsLoading = [cb];
  const script = document.createElement('script');
  script.src = 'https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($maps_key) ?>&v=beta&loading=async';
  script.async = true;
  script.defer = true;
  script.onload = function() {
    const cbs = window._prodMapsLoading || [];
    window._prodMapsLoading = null;
    cbs.forEach(f => f());
  };
  document.head.appendChild(script);
}

window.cambiarVista      = cambiarVista;
window.toggleExtraDetalle = toggleExtraDetalle;
window.cargar             = cargar;
window.abrirReplay        = abrirReplay;
window.cerrarReplay       = cerrarReplay;
window.togglePlayReplay   = togglePlayReplay;
window.scrubReplay        = scrubReplay;
window.cambiarVelocidadReplay = cambiarVelocidadReplay;

cargar();

setInterval(cargar, 120000);


})();
</script>