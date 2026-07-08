<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
requirePermiso('ver_dashboard');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    $f = isset($_GET['folio']) ? '&folio='.urlencode($_GET['folio']) : '';
    header('Location: ../dashboard.php?m=orden'.$f); exit;
}
$folio_php = $_GET['folio'] ?? '';
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }
.header {
  background: #1a1a2e; color: white;
  padding: 14px 24px;
  display: flex; align-items: center; justify-content: space-between;
}
.header-left { display: flex; align-items: center; gap: 16px; }
.header h1 { font-size: 18px; font-weight: 800; letter-spacing: 1px; }
.back-btn {
  background: rgba(255,255,255,.1); color: white; border: none;
  padding: 6px 14px; border-radius: 8px; font-size: 13px; cursor: pointer;
  text-decoration: none; display: inline-block;
}
.refresh-btn {
  background: #2563eb; color: white; border: none;
  padding: 7px 14px; border-radius: 8px; font-size: 13px; cursor: pointer;
}
.imprimir-btn {
  background: #16a34a; color: white; border: none;
  padding: 7px 14px; border-radius: 8px; font-size: 13px; cursor: pointer;
  text-decoration: none; display: inline-block;
}
.main { padding: 20px 24px; max-width: 1200px; margin: 0 auto; }

.orden-header {
  background: white; border-radius: 14px;
  padding: 20px 24px; margin-bottom: 20px;
  box-shadow: 0 2px 8px rgba(0,0,0,.06);
}
.orden-meta {
  display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px;
  margin-bottom: 16px;
}
.meta-item label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; }
.meta-item p { font-size: 15px; font-weight: 700; color: #1e293b; margin-top: 3px; }

.orden-stats { display: flex; gap: 10px; flex-wrap: wrap; }
.stat-pill {
  display: flex; align-items: center; gap: 6px;
  padding: 6px 14px; border-radius: 20px;
  font-size: 13px; font-weight: 700;
}
.stat-pill .num { font-size: 18px; }
.pill-pendiente  { background: #f1f5f9; color: #64748b; }
.pill-en_corte   { background: #fff7ed; color: #c2410c; }
.pill-cortado    { background: #fef3c7; color: #d97706; }
.pill-canteado   { background: #e0f2fe; color: #0369a1; }
.pill-trazo      { background: #fce7f3; color: #be185d; }
.pill-taladro    { background: #fdf4ff; color: #7e22ce; }
.pill-en_horno   { background: #fef2f2; color: #dc2626; }
.pill-templado   { background: #dbeafe; color: #1d4ed8; }
.pill-terminado  { background: #dcfce7; color: #15803d; }
.pill-entregado  { background: #bbf7d0; color: #14532d; }
.pill-trabajos   { background: #f3e8ff; color: #6d28d9; }

.progreso-wrap { margin-top: 14px; }
.progreso-label {
  font-size: 12px; color: #64748b; margin-bottom: 6px;
  display: flex; justify-content: space-between;
}
.progreso-bar { background: #e2e8f0; border-radius: 6px; height: 10px; overflow: hidden; }
.progreso-fill { background: linear-gradient(90deg, #2563eb, #16a34a); height: 100%; border-radius: 6px; transition: width .5s; }

.section-title { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 14px; margin-top: 4px; }

.partida-card {
  background: white; border-radius: 14px;
  margin-bottom: 16px;
  box-shadow: 0 2px 8px rgba(0,0,0,.06);
  overflow: hidden;
}
.partida-header {
  padding: 16px 20px; background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
}
.partida-titulo  { font-size: 16px; font-weight: 800; color: #1a1a2e; }
.partida-cristal { font-size: 13px; color: #475569; margin-top: 2px; }
.partida-meta    { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 8px; }
.tag { font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 20px; }
.tag-cpb     { background: #e0f2fe; color: #0369a1; }
.tag-trabajo { background: #f3e8ff; color: #6d28d9; }
.tag-nota    { background: #fef3c7; color: #92400e; }

.partida-resumen {
  display: flex; gap: 6px; flex-wrap: wrap;
  align-items: center; min-width: 180px; justify-content: flex-end;
}
.mini-stat { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 12px; }

.piezas-tabla { width: 100%; border-collapse: collapse; }
.piezas-tabla th {
  padding: 10px 16px; font-size: 11px; font-weight: 700; color: #64748b;
  text-transform: uppercase; letter-spacing: .5px;
  text-align: left; background: #fafafa; border-bottom: 1px solid #f1f5f9;
}
.piezas-tabla td {
  padding: 11px 16px; font-size: 14px; color: #374151;
  border-bottom: 1px solid #f8fafc; vertical-align: middle;
}
.piezas-tabla tr:last-child td { border-bottom: none; }
.piezas-tabla tr:hover td { background: #f8fafc; }

.badge {
  font-size: 11px; font-weight: 700;
  padding: 3px 10px; border-radius: 20px;
  text-transform: uppercase; white-space: nowrap; display: inline-block;
}
.badge-pendiente { background: #f1f5f9; color: #64748b; }
.badge-en_corte  { background: #fff7ed; color: #c2410c; }
.badge-cortado   { background: #fef3c7; color: #d97706; }
.badge-canteado  { background: #e0f2fe; color: #0369a1; }
.badge-trazo     { background: #fce7f3; color: #be185d; }
.badge-taladro   { background: #fdf4ff; color: #7e22ce; }
.badge-en_horno  { background: #fef2f2; color: #dc2626; }
.badge-templado  { background: #dbeafe; color: #1d4ed8; }
.badge-terminado { background: #dcfce7; color: #15803d; }
.badge-entregado { background: #bbf7d0; color: #14532d; }
.tag-retrabajo   { background: #fef3c7; color: #92400e; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; white-space: nowrap; margin-left: 4px; }

.tiempo-chip { font-size: 11px; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 10px; }
.tiempo-chip.alerta  { background: #fef3c7; color: #d97706; }
.tiempo-chip.urgente { background: #fef2f2; color: #dc2626; }
.qr-code { font-family: monospace; font-size: 11px; color: #94a3b8; }
.loading { text-align: center; padding: 60px; color: #94a3b8; }

@media (max-width: 768px) {
  .orden-meta { grid-template-columns: 1fr 1fr; }
  .main { padding: 12px 16px; }
  .partida-header { flex-direction: column; }
  .partida-resumen { justify-content: flex-start; }
  .piezas-tabla th:nth-child(4),
  .piezas-tabla td:nth-child(4) { display: none; }
}
</style>


<div class="header">
  <div class="header-left">
    <a class="back-btn" href="dashboard.php">&#8592; Volver</a>
    <h1>&#11041; APEX GLASS &#8212; Detalle Orden</h1>
  </div>
  <div class="right">
    <button class="refresh-btn" onclick="cargar()">&#8635; Actualizar</button>
    <a id="btnImprimir" class="imprimir-btn" href="#" target="_blank">&#128424;&#65039; Imprimir etiquetas</a>
  </div>
</div>

<div class="main" id="main">
  <div class="loading">Cargando orden...</div>
</div>

<script>
var ESTATUS_LABELS = {
  pendiente:  '&#9203; Pendiente',
  en_corte:   '&#9881;&#65039; En CNC',
  cortado:    '&#9986;&#65039; Cortado',
  canteado:   '&#128297; Canteado',
  trazo:      '&#9999;&#65039; Trazo',
  taladro:    '&#128295; Taladro',
  en_horno:   '&#128293; En horno',
  templado:   '&#128293; Templado',
  terminado:  '&#128230; Terminado',
  entregado:  '&#9989; Entregado',
};

var ESTATUS_ORDEN = ['pendiente','en_corte','cortado','canteado','trazo','taladro','en_horno','terminado','entregado'];

var params = new URLSearchParams(window.location.search);
var FOLIO  = '<?= htmlspecialchars($folio_php, ENT_QUOTES) ?>' || params.get('folio') || '';

// Apuntar bot&#243;n de imprimir al folio correcto
if (FOLIO) {
  document.getElementById('btnImprimir').href = 
    'imprimir_etiquetas.php?folio=' + encodeURIComponent(FOLIO);
}

if (!FOLIO) {
  document.getElementById('main').innerHTML = '<div class="loading">&#9888;&#65039; No se especific&#243; un folio</div>';
}

// &#9472;&#9472; Calcular % de avance ponderado por pieza &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;
// Igual que en actualizar_estatus.php &#8212; se ajusta al flujo de cada pieza
function calcularAvance(piezas) {
  if (!piezas || !piezas.length) return 0;
  let suma = 0;
  piezas.forEach(p => {
    const tieneAgujeros = (parseInt(p.tp||0) > 0 || parseInt(p.ta||0) > 0 || parseInt(p.resaques||0) > 0);
    const esRecocida    = !p.requiere_templado;

    const pasos = ['pendiente','cortado','canteado'];
    if (tieneAgujeros) { pasos.push('trazo'); pasos.push('taladro'); }
    if (!esRecocida)   { pasos.push('templado'); }
    pasos.push('terminado');

    const totalPasos = pasos.length - 1;
    const estatus    = p.estatus === 'entregado' ? 'terminado' : p.estatus;
    const pos        = pasos.indexOf(estatus);
    suma += totalPasos > 0 ? ((pos < 0 ? 0 : pos) / totalPasos) * 100 : 0;
  });
  return Math.round(suma / piezas.length);
}

async function cargar() {
  if (!FOLIO) return;
  try {
    const res  = await fetch('../api/orden.php?folio=' + encodeURIComponent(FOLIO));
    const data = await res.json();
    if (!res.ok || data.error) {
      document.getElementById('main').innerHTML = '<div class="loading">&#9888;&#65039; ' + (data.error || 'Error') + '</div>';
      return;
    }
    render(data);
  } catch(e) {
    document.getElementById('main').innerHTML = '<div class="loading">&#10060; Error de conexi&#243;n</div>';
  }
}

function render(data) {
  const { orden, resumen, partidas } = data;

  // Recolectar todas las piezas para calcular avance ponderado
  const todasPiezas = partidas.flatMap(pt => pt.piezas);
  const pct         = calcularAvance(todasPiezas);
  const terminadas  = todasPiezas.filter(p => ['terminado','entregado'].includes(p.estatus)).length;
  const total       = todasPiezas.length;

  const fechaRegistro = orden.vobo_at
    ? new Date(orden.vobo_at.replace(' ', 'T')).toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' })
    : (orden.fecha_pedido
      ? new Date(orden.fecha_pedido + 'T12:00:00').toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' })
      : '&#8212;');
  const fechaEntrega = orden.fecha_entrega
    ? new Date(orden.fecha_entrega + 'T12:00:00').toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' })
    : '&#8212;';
  const fechaCierre = orden.fecha_cierre
    ? new Date(orden.fecha_cierre).toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' })
    : null;

  // Color de urgencia
  let colorFecha = '#1e293b';
  if (orden.fecha_entrega) {
    const dias = Math.ceil((new Date(orden.fecha_entrega) - new Date()) / 86400000);
    if (dias <= 0)      colorFecha = '#dc2626';
    else if (dias <= 2) colorFecha = '#d97706';
    else                colorFecha = '#16a34a';
  }

  let html = `
    <div class="orden-header">
      <div class="orden-meta">
        <div class="meta-item"><label>Folio</label><p>${orden.folio}</p></div>
        <div class="meta-item"><label>Cliente</label><p>${orden.cliente_nombre || '&#8212;'}</p></div>
        <div class="meta-item"><label>Asesor</label><p>${orden.asesor || '&#8212;'}</p></div>
        <div class="meta-item"><label>Registro</label><p>${fechaRegistro}</p></div>
        <div class="meta-item"><label>Entrega compromiso</label><p style="color:${colorFecha};font-weight:700">${fechaEntrega}</p></div>
        <div class="meta-item"><label>Cierre real</label><p style="color:${fechaCierre ? '#16a34a' : '#94a3b8'}">${fechaCierre || 'En proceso&#8230;'}</p></div>
      </div>

      <div class="orden-stats">
        ${resumen.pendientes  > 0 ? `<div class="stat-pill pill-pendiente"><span class="num">${resumen.pendientes}</span> Pendiente</div>` : ''}
        ${resumen.cortadas    > 0 ? `<div class="stat-pill pill-cortado"><span class="num">${resumen.cortadas}</span> Cortado</div>` : ''}
        ${resumen.canteadas   > 0 ? `<div class="stat-pill pill-canteado"><span class="num">${resumen.canteadas}</span> Canteado</div>` : ''}
        ${resumen.en_trazo    > 0 ? `<div class="stat-pill pill-trazo"><span class="num">${resumen.en_trazo}</span> Trazo</div>` : ''}
        ${resumen.en_taladro  > 0 ? `<div class="stat-pill pill-taladro"><span class="num">${resumen.en_taladro}</span> Taladro</div>` : ''}
        ${resumen.en_horno    > 0 ? `<div class="stat-pill pill-en_horno"><span class="num">${resumen.en_horno}</span> En horno</div>` : ''}
        ${resumen.terminadas  > 0 ? `<div class="stat-pill pill-terminado"><span class="num">${resumen.terminadas}</span> Terminado</div>` : ''}
        ${resumen.entregadas  > 0 ? `<div class="stat-pill pill-entregado"><span class="num">${resumen.entregadas}</span> Entregado</div>` : ''}
        ${resumen.con_trabajos > 0 ? `<div class="stat-pill pill-trabajos">&#128297; ${resumen.con_trabajos} con trabajos</div>` : ''}
      </div>

      <div class="progreso-wrap">
        <div class="progreso-label">
          <span>Progreso general</span>
          <span>${pct}% &#8212; ${terminadas} de ${total} piezas terminadas</span>
        </div>
        <div class="progreso-bar">
          <div class="progreso-fill" style="width:${pct}%"></div>
        </div>
      </div>
    </div>

    <div class="section-title">&#128203; Partidas (${partidas.length})</div>
  `;

  partidas.forEach(pt => {
    const canteadoTag = pt.cpb && pt.cpb.trim()
      ? `<span class="tag tag-cpb">CPB &#8212; ${pt.cpb}</span>`
      : `<span class="tag tag-cpb">Canteado</span>`;

    const trabajosTags = [];
    if (parseInt(pt.resaques) > 0) trabajosTags.push(`<span class="tag tag-trabajo">&#9986;&#65039; ${pt.resaques} resaque(s)</span>`);
    if (parseInt(pt.tp) > 0)       trabajosTags.push(`<span class="tag tag-trabajo">&#128297; ${pt.tp} TP</span>`);
    if (parseInt(pt.ta) > 0)       trabajosTags.push(`<span class="tag tag-trabajo">&#128297; ${pt.ta} TA</span>`);
    if (pt.detalles && pt.detalles !== 'NO' && pt.detalles !== '')
                                    trabajosTags.push(`<span class="tag tag-nota">&#128203; ${pt.detalles}</span>`);
    if (pt.comentarios && pt.comentarios !== '')
                                    trabajosTags.push(`<span class="tag tag-nota">&#128172; ${pt.comentarios}</span>`);

    const miniStats = ESTATUS_ORDEN.map(e => {
      const cnt = pt['cnt_' + e] || 0;
      if (!cnt) return '';
      const colors = {
        pendiente:'#f1f5f9;color:#64748b', cortado:'#fef3c7;color:#d97706',
        canteado:'#e0f2fe;color:#0369a1',  trazo:'#fce7f3;color:#be185d',
        taladro:'#fdf4ff;color:#7e22ce',   templado:'#dbeafe;color:#1d4ed8',
        terminado:'#dcfce7;color:#15803d', entregado:'#bbf7d0;color:#14532d',
      };
      return `<span class="mini-stat" style="background:${colors[e]}">${cnt} ${ESTATUS_LABELS[e]}</span>`;
    }).join('');

    // Avance de esta partida
    const avancePt = calcularAvance(pt.piezas);

    const filasHtml = pt.piezas.map(p => {
      const mins = p.minutos_desde_corte;
      let tiempoHtml = '';
      if (mins !== null && mins !== undefined && p.estatus !== 'pendiente') {
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        const texto = h > 0 ? `${h}h ${m}min` : `${m} min`;
        const clase = mins > 480 ? 'urgente' : (mins > 240 ? 'alerta' : '');
        tiempoHtml = `<span class="tiempo-chip ${clase}">&#9201; ${texto}</span>`;
      }
      const retrabajoTag = p.es_retrabajo == 1
        ? `<span class="tag-retrabajo" title="${p.razon_retrabajo || ''}">&#9888; Retrabajo${p.razon_retrabajo ? ': ' + p.razon_retrabajo : ''}</span>`
        : '';

      return `<tr>
        <td style="font-weight:700;color:#1a1a2e">${p.pieza_num}${retrabajoTag}</td>
        <td><span class="badge badge-${p.estatus}">${ESTATUS_LABELS[p.estatus] || p.estatus}</span></td>
        <td>${tiempoHtml}</td>
        <td class="qr-code">${p.qr_code}</td>
      </tr>`;
    }).join('');

    html += `
      <div class="partida-card">
        <div class="partida-header">
          <div style="flex:1">
            <div class="partida-titulo">Partida ${pt.partida} &#8212; ${pt.total_piezas} pieza(s) &#183; ${avancePt}% avance</div>
            <div class="partida-cristal">${pt.cristal} &#183; ${pt.ancho_mm} &#215; ${pt.alto_mm} mm &#183; ${pt.m2_unitario} m&#178;/pieza</div>
            <div class="partida-meta">${canteadoTag}${trabajosTags.join('')}</div>
          </div>
          <div class="partida-resumen">${miniStats}</div>
        </div>
        <table class="piezas-tabla">
          <thead>
            <tr>
              <th>Pieza #</th><th>Estatus</th><th>Tiempo desde corte</th><th>QR</th>
            </tr>
          </thead>
          <tbody>${filasHtml}</tbody>
        </table>
      </div>
    `;
  });

  document.getElementById('main').innerHTML = html;
}

cargar();
setInterval(cargar, 30000);
</script>