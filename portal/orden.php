<?php
// ============================================================
//  APEX GLASS - Portal Clientes - Detalle de Orden
//  Ruta en servidor: /produccion/portal/orden.php
// ============================================================
require_once __DIR__ . '/../api/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['portal_cliente_id'])) {
    header('Location: index.php'); exit;
}

$cliente_id     = $_SESSION['portal_cliente_id'];
$cliente_nombre = $_SESSION['portal_cliente_nombre'];
$cliente_codigo = $_SESSION['portal_cliente_codigo'];
$folio          = trim($_GET['folio'] ?? '');

if (!$folio) { header('Location: dashboard.php'); exit; }

$pdo = getDB();

$stmtCli = $pdo->prepare("SELECT razon_social, nombre FROM clientes WHERE id = ?");
$stmtCli->execute([$cliente_id]);
$rowCli = $stmtCli->fetch(PDO::FETCH_ASSOC);
$cliente_razon = $rowCli ? ($rowCli['razon_social'] ?: $rowCli['nombre']) : '';

$stmtCheck = $pdo->prepare("
    SELECT id FROM ordenes
    WHERE folio = ?
      AND (cliente_id = ? OR cliente_nombre = ?)
    LIMIT 1
");
$stmtCheck->execute([$folio, $cliente_id, $cliente_razon]);
if (!$stmtCheck->fetch()) {
    header('Location: dashboard.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>APEX GLASS &mdash; <?= htmlspecialchars($folio) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --amber:    #F5A623;
  --bg:       #F0F1F3;
  --surface:  #FFFFFF;
  --border:   #E2E5EB;
  --text-1:   #0F1117;
  --text-2:   #7A7E8E;
  --text-3:   #C4C8D2;
  --green:    #16a34a;
  --green-bg: rgba(22,163,74,.08);
  --blue:     #1d4ed8;
  --blue-bg:  rgba(29,78,216,.07);
  --amber-bg: rgba(245,166,35,.08);
  --red:      #dc2626;
}

body {
  font-family: 'Outfit', -apple-system, sans-serif;
  background: var(--bg);
  min-height: 100dvh;
  color: var(--text-1);
}

/* ── Header ── */
.header {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  padding: 0 32px;
  height: 58px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky; top: 0; z-index: 100;
}
.header-logo {
  font-family: 'Syncopate', sans-serif;
  font-size: 13px; font-weight: 700;
  letter-spacing: 4px; color: var(--text-1);
}
.header-right { display: flex; align-items: center; gap: 18px; }
.header-cliente { font-size: 12px; font-weight: 400; color: var(--text-2); letter-spacing: .3px; }
.header-codigo  { font-size: 10px; font-weight: 600; color: var(--amber); letter-spacing: 2px; text-transform: uppercase; }

.btn-back {
  font-size: 9.5px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
  color: var(--text-2); background: none; border: 1px solid var(--border);
  border-radius: 2px; padding: 5px 10px; cursor: pointer;
  text-decoration: none; display: flex; align-items: center; gap: 6px;
  transition: color .15s, border-color .15s;
}
.btn-back:hover { color: var(--text-1); border-color: #B0B5C0; }

.btn-logout {
  font-size: 9.5px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
  color: var(--text-2); background: none; border: 1px solid var(--border);
  border-radius: 2px; padding: 6px 14px; cursor: pointer;
  transition: color .15s, border-color .15s;
}
.btn-logout:hover { color: var(--text-1); border-color: #B0B5C0; }

@media (max-width: 639px) {
  .header { padding: 0 18px; gap: 10px; }
  .header-cliente { display: none; }
  .btn-logout { display: none; }
  .btn-back { padding: 4px 8px; letter-spacing: 1px; font-size: 9px; }
}

/* ── Main ── */
.main { max-width: 1400px; margin: 0 auto; padding: 32px 48px 60px; }

/* ── Orden header card ── */
.orden-header {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 24px 28px;
  margin-bottom: 24px;
}

.orden-folio-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 10px;
}
.orden-folio {
  font-family: 'Syncopate', sans-serif;
  font-size: 15px; font-weight: 700;
  letter-spacing: 3px; color: var(--text-1);
}

/* status tags */
.tag {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 600; letter-spacing: 1.2px; text-transform: uppercase;
  padding: 4px 10px; border-radius: 2px; white-space: nowrap;
}
.tag-activa    { background: var(--green-bg); color: var(--green); }
.tag-entregada { background: var(--blue-bg);  color: var(--blue); }
.tag-proceso   { background: var(--amber-bg); color: #92400e; }
.tag-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

/* meta grid */
.orden-meta {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid #F5F6F8;
  margin-bottom: 20px;
}
@media (max-width: 639px) { .orden-meta { grid-template-columns: 1fr 1fr; gap: 16px; } }
@media (max-width: 359px) { .orden-meta { grid-template-columns: 1fr; } }

.meta-label {
  font-size: 9px; font-weight: 600; letter-spacing: 2px;
  text-transform: uppercase; color: var(--text-3); display: block; margin-bottom: 5px;
}
.meta-val { font-size: 13px; font-weight: 500; color: var(--text-1); }

/* pills conteo */
.orden-pills { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; }
.pill {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 600; letter-spacing: .8px;
  padding: 4px 10px; border-radius: 2px;
}
.pill-num { font-size: 13px; font-weight: 600; }
.pill-pendiente { background: #F5F6F8; color: var(--text-2); }
.pill-cortado   { background: #FEF3C7; color: #d97706; }
.pill-canteado  { background: #E0F2FE; color: #0369a1; }
.pill-trazo     { background: #FCE7F3; color: #be185d; }
.pill-taladro   { background: #FDF4FF; color: #7e22ce; }
.pill-templado  { background: #DBEAFE; color: var(--blue); }
.pill-terminado { background: var(--green-bg); color: var(--green); }
.pill-entregado { background: #BBF7D0; color: #14532d; }

/* progreso */
.progreso-label { display: flex; justify-content: space-between; font-size: 10px; color: var(--text-2); margin-bottom: 7px; letter-spacing: .5px; flex-wrap: wrap; gap: 4px; }
.progreso-bar   { background: #ECEEF2; border-radius: 99px; height: 4px; overflow: hidden; }
.progreso-fill  { height: 100%; border-radius: 99px; background: var(--amber); transition: width .5s; }
.progreso-fill.completo { background: var(--green); }

/* ── Partidas ── */
.section-label {
  display: flex; align-items: center; gap: 12px; margin-bottom: 16px;
}
.section-label-txt {
  font-size: 9.5px; font-weight: 600; letter-spacing: 2.5px;
  text-transform: uppercase; color: var(--text-2);
}
.section-label-line { flex: 1; height: 1px; background: var(--border); }

.partida-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  margin-bottom: 12px;
  overflow: hidden;
}

.partida-header {
  padding: 16px 20px;
  background: #FAFBFC;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px;
}
@media (max-width: 639px) { .partida-header { flex-direction: column; gap: 12px; } }

.partida-titulo  { font-size: 13px; font-weight: 600; color: var(--text-1); letter-spacing: .2px; }
.partida-cristal { font-size: 11px; color: var(--text-2); margin-top: 4px; line-height: 1.5; }
.partida-tags    { display: flex; gap: 5px; flex-wrap: wrap; margin-top: 8px; }
.ptag {
  font-size: 9.5px; font-weight: 600; letter-spacing: 1px;
  padding: 3px 8px; border-radius: 2px;
}
.ptag-cpb    { background: #E0F2FE; color: #0369a1; }
.ptag-nota   { background: #FEF3C7; color: #92400e; }
.ptag-trab   { background: #F3E8FF; color: #6d28d9; }

.mini-stats  { display: flex; gap: 5px; flex-wrap: wrap; flex-shrink: 0; }
.mini-stat   { font-size: 9.5px; font-weight: 600; letter-spacing: .8px; padding: 3px 8px; border-radius: 2px; white-space: nowrap; }

/* tabla piezas */
.piezas-tabla { width: 100%; border-collapse: collapse; }
.piezas-tabla th {
  padding: 9px 20px; font-size: 9px; font-weight: 600; color: var(--text-2);
  text-transform: uppercase; letter-spacing: 1.8px;
  text-align: left; background: #FAFAFA; border-bottom: 1px solid #F5F6F8;
}
.piezas-tabla td {
  padding: 10px 20px; font-size: 12px; color: var(--text-1);
  border-bottom: 1px solid #F8F9FA; vertical-align: middle;
}
.piezas-tabla tr:last-child td { border-bottom: none; }
.piezas-tabla tr:hover td { background: #FAFBFC; }

/* estatus badges piezas */
.badge {
  font-size: 9.5px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;
  padding: 3px 8px; border-radius: 2px; white-space: nowrap; display: inline-block;
}
.badge-pendiente { background: #F5F6F8; color: var(--text-2); }
.badge-en_corte  { background: #FEFCE8; color: #ca8a04; }
.badge-cortado   { background: #FEF3C7; color: #d97706; }
.badge-canteado  { background: #E0F2FE; color: #0369a1; }
.badge-trazo     { background: #FCE7F3; color: #be185d; }
.badge-taladro   { background: #FDF4FF; color: #7e22ce; }
.badge-en_horno  { background: #FFF1F2; color: #e11d48; }
.badge-terminado { background: var(--green-bg); color: var(--green); }
.badge-entregado { background: #BBF7D0; color: #14532d; }
.badge-reproceso { background: #FFF7ED; color: #c2410c; }

.loading { text-align: center; padding: 60px; color: var(--text-3); font-size: 13px; letter-spacing: .5px; }
.footer  { text-align: center; padding: 28px 20px; font-size: 9px; font-weight: 400; color: var(--text-3); letter-spacing: 2px; text-transform: uppercase; }

@media (max-width: 479px) {
  .piezas-tabla th, .piezas-tabla td { padding: 8px 14px; }
  .orden-header { padding: 18px 18px; }
  .main { padding: 20px 14px 48px; }
}
</style>
</head>
<body>

<div class="header">
  <span class="header-logo">APEX GLASS</span>
  <div class="header-right">
    <a class="btn-back" href="dashboard.php">&#8592; Mis órdenes</a>
    <span class="header-cliente"><?= htmlspecialchars($cliente_nombre) ?></span>
    <span class="header-codigo"><?= htmlspecialchars($cliente_codigo) ?></span>
    <button class="btn-logout" onclick="cerrarSesion()">Salir</button>
  </div>
</div>

<div class="main" id="main">
  <div class="loading">Cargando orden&#8230;</div>
</div>

<div class="footer">Acceso exclusivo para clientes &mdash; APEX GLASS</div>

<script>
const FOLIO = <?= json_encode($folio) ?>;

const ESTATUS_LABELS = {
  pendiente:'Pendiente', en_corte:'En corte', cortado:'Cortado',
  canteado:'Canteado', trazo:'Trazo', taladro:'Taladro',
  en_horno:'En horno', terminado:'Terminado', entregado:'Entregado', reproceso:'Reproceso',
};

const ESTATUS_ORDEN = ['pendiente','cortado','canteado','trazo','taladro','terminado','entregado'];

const PESOS = {
  pendiente:0, en_corte:1, cortado:2, canteado:3,
  trazo:4, taladro:5, en_horno:6, terminado:8, entregado:8
};

function calcularAvance(piezas) {
  if (!piezas || !piezas.length) return 0;
  const suma = piezas.reduce((acc, p) => acc + (PESOS[p.estatus] ?? 0), 0);
  return Math.round(suma / (piezas.length * 8) * 100);
}

async function cargar() {
  try {
    const res  = await fetch('../api/orden.php?folio=' + encodeURIComponent(FOLIO));
    const data = await res.json();
    if (!res.ok || data.error) {
      document.getElementById('main').innerHTML = '<div class="loading">No se encontró la orden</div>';
      return;
    }
    render(data);
  } catch(e) {
    document.getElementById('main').innerHTML = '<div class="loading">Error de conexión</div>';
  }
}

function render(data) {
  const { orden, resumen, partidas } = data;
  const todasPiezas = partidas.flatMap(pt => pt.piezas);
  const pct         = calcularAvance(todasPiezas);
  const terminadas  = todasPiezas.filter(p => ['terminado','entregado'].includes(p.estatus)).length;
  const total       = todasPiezas.length;

  const fmt = (d, hora) => d ? new Date(d + (hora ? '' : 'T12:00:00')).toLocaleDateString('es-MX', {day:'2-digit',month:'short',year:'numeric'}) : '—';
  const fechaPedido  = fmt(orden.fecha_pedido);
  const fechaEntrega = fmt(orden.fecha_entrega);
  const fechaCierre  = orden.fecha_cierre ? fmt(orden.fecha_cierre, true) : null;

  let colorFecha = 'var(--text-1)';
  if (orden.fecha_entrega) {
    const dias = Math.ceil((new Date(orden.fecha_entrega) - new Date()) / 86400000);
    if (dias <= 0)      colorFecha = 'var(--red)';
    else if (dias <= 2) colorFecha = '#d97706';
    else                colorFecha = 'var(--green)';
  }

  const pillMap = [
    ['pendiente','pendiente'],['en_corte','cortado'],['cortado','cortado'],
    ['canteado','canteado'],['trazo','trazo'],['taladro','taladro'],
    ['en_horno','templado'],['terminado','terminado'],['entregado','entregado'],
  ];
  const statKeys = {
    pendiente:'pendientes', cortado:'cortadas', canteado:'canteadas',
    trazo:'en_trazo', taladro:'en_taladro', templado:'templadas',
    terminado:'terminadas', entregado:'entregadas',
  };
  let pillsHtml = '';
  for (const [est, cls] of pillMap) {
    const key = statKeys[est === 'en_corte' ? 'cortado' : est] || est;
    const cnt = resumen[key] || 0;
    if (cnt > 0) pillsHtml += `<div class="pill pill-${cls}"><span class="pill-num">${cnt}</span> ${ESTATUS_LABELS[est]}</div>`;
  }

  let html = `
    <div class="orden-header">
      <div class="orden-folio-row">
        <span class="orden-folio">${orden.folio}</span>
        <span class="tag ${pct === 100 ? 'tag-entregada' : 'tag-activa'}">
          <span class="tag-dot"></span>${pct === 100 ? 'Completa' : 'En producción'}
        </span>
      </div>
      <div class="orden-meta">
        <div><span class="meta-label">Fecha pedido</span><span class="meta-val">${fechaPedido}</span></div>
        <div><span class="meta-label">Entrega compromiso</span><span class="meta-val" style="color:${colorFecha}">${fechaEntrega}</span></div>
        <div><span class="meta-label">Cierre real</span><span class="meta-val" style="color:${fechaCierre ? 'var(--green)' : 'var(--text-3)'}">${fechaCierre || 'En proceso'}</span></div>
        ${orden.proyecto ? `<div><span class="meta-label">Proyecto</span><span class="meta-val">${orden.proyecto}</span></div>` : ''}
        <div><span class="meta-label">Total piezas</span><span class="meta-val">${total}</span></div>
      </div>
      <div class="orden-pills">${pillsHtml}</div>
      <div class="progreso-label">
        <span>Progreso general</span>
        <span>${pct}% &mdash; ${terminadas} de ${total} piezas terminadas</span>
      </div>
      <div class="progreso-bar">
        <div class="progreso-fill ${pct === 100 ? 'completo' : ''}" style="width:${pct}%"></div>
      </div>
    </div>
    <div class="section-label">
      <span class="section-label-txt">Partidas (${partidas.length})</span>
      <span class="section-label-line"></span>
    </div>
  `;

  const colors = {
    pendiente:'#F5F6F8;color:#7A7E8E', cortado:'#FEF3C7;color:#d97706',
    canteado:'#E0F2FE;color:#0369a1',  trazo:'#FCE7F3;color:#be185d',
    taladro:'#FDF4FF;color:#7e22ce',   templado:'#DBEAFE;color:#1d4ed8',
    terminado:'rgba(22,163,74,.08);color:#16a34a', entregado:'#BBF7D0;color:#14532d',
  };

  partidas.forEach(pt => {
    const canteadoTag = pt.cpb && pt.cpb.trim()
      ? `<span class="ptag ptag-cpb">CPB &mdash; ${pt.cpb}</span>`
      : `<span class="ptag ptag-cpb">Canteado</span>`;

    const trabajosTags = [];
    if (parseInt(pt.resaques) > 0) trabajosTags.push(`<span class="ptag ptag-trab">${pt.resaques} resaque(s)</span>`);
    if (parseInt(pt.tp) > 0)       trabajosTags.push(`<span class="ptag ptag-trab">${pt.tp} TP</span>`);
    if (parseInt(pt.ta) > 0)       trabajosTags.push(`<span class="ptag ptag-trab">${pt.ta} TA</span>`);
    if (pt.detalles && pt.detalles !== 'NO' && pt.detalles !== '')
      trabajosTags.push(`<span class="ptag ptag-nota">${pt.detalles}</span>`);
    if (pt.comentarios && pt.comentarios !== '')
      trabajosTags.push(`<span class="ptag ptag-nota">${pt.comentarios}</span>`);

    const avancePt = calcularAvance(pt.piezas);

    const miniStats = ESTATUS_ORDEN.map(e => {
      const cnt = pt['cnt_' + e] || 0;
      if (!cnt) return '';
      return `<span class="mini-stat" style="background:${colors[e]}">${cnt} ${ESTATUS_LABELS[e]}</span>`;
    }).join('');

    const filasHtml = pt.piezas.map(p => `
      <tr>
        <td style="font-weight:600">${p.pieza_num}</td>
        <td><span class="badge badge-${p.estatus}">${ESTATUS_LABELS[p.estatus] || p.estatus}</span></td>
      </tr>
    `).join('');

    html += `
      <div class="partida-card">
        <div class="partida-header">
          <div style="flex:1;min-width:0">
            <div class="partida-titulo">Partida ${pt.partida} &mdash; ${pt.total_piezas} pieza(s) &middot; ${avancePt}%</div>
            <div class="partida-cristal">${pt.cristal} &middot; ${pt.ancho_mm}&times;${pt.alto_mm} mm &middot; ${pt.m2_unitario} m²/pza</div>
            <div class="partida-tags">${canteadoTag}${trabajosTags.join('')}</div>
          </div>
          <div class="mini-stats">${miniStats}</div>
        </div>
        <table class="piezas-tabla">
          <thead><tr><th>Pieza #</th><th>Estatus</th></tr></thead>
          <tbody>${filasHtml}</tbody>
        </table>
      </div>
    `;
  });

  document.getElementById('main').innerHTML = html;
}

async function cerrarSesion() {
  try {
    const fd = new FormData();
    await fetch('../api/portal_clientes.php?accion=logout', { method: 'POST', body: fd });
  } catch(e) {}
  window.location.href = 'index.php';
}

cargar();
setInterval(cargar, 30000);
</script>
</body>
</html>