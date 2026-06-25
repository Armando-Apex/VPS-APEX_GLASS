<?php
// ============================================================
//  APEX GLASS - Portal Clientes - Detalle de Cotización
//  Ruta en servidor: /produccion/portal/cotizacion.php
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

// Verificar que la cotización pertenece a este cliente
$stmtCot = $pdo->prepare("
    SELECT * FROM cotizaciones
    WHERE folio = ?
      AND (cliente_id = ? OR cliente_nombre = ?)
    LIMIT 1
");
$stmtCot->execute([$folio, $cliente_id, $cliente_razon]);
$cot = $stmtCot->fetch(PDO::FETCH_ASSOC);
if (!$cot) { header('Location: dashboard.php'); exit; }

// Partidas
$stmtPart = $pdo->prepare("
    SELECT * FROM cotizaciones_partidas
    WHERE cotizacion_id = ?
    ORDER BY num_partida ASC
");
$stmtPart->execute([$cot['id']]);
$partidas = $stmtPart->fetchAll(PDO::FETCH_ASSOC);

// Totales usando fórmula canónica
$subtotal_partidas = 0;
foreach ($partidas as $p) {
    $subtotal_partidas += (float)$p['precio_m2_usado'] * (float)$p['m2'] * (int)$p['cantidad'];
}
$servicios    = (float)($cot['servicios_subtotal'] ?? 0);
$subtotal_bruto = $subtotal_partidas + $servicios;
$descuento    = (float)$cot['descuento'];
$subtotal_neto = ($descuento > 0) ? round($subtotal_bruto * (1 - $descuento / 100), 2) : $subtotal_bruto;
$iva          = round($subtotal_neto * 0.16, 2);
$total        = round($subtotal_neto * 1.16, 2);

// Helpers
function fmt($n) { return '$' . number_format((float)$n, 2, '.', ','); }

function cotEstatus($e) {
    switch ($e) {
        case 'cotizacion': return ['Vigente',         'tag-proceso'];
        case 'orden':      return ['En producción',   'tag-activa'];
        case 'entregada':  return ['Entregada',        'tag-entregada'];
        case 'cancelada':  return ['Cancelada',        'tag-gris'];
        case 'rechazada':  return ['No aprobada',      'tag-gris'];
        default:           return [ucfirst($e),        'tag-gris'];
    }
}

[$statTxt, $statCls] = cotEstatus($cot['estatus']);
$fecha = $cot['fecha'] ? date('d M Y', strtotime($cot['fecha'])) : '—';
$fechaEntrega = $cot['fecha_entrega'] ? date('d M Y', strtotime($cot['fecha_entrega'])) : '—';
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
}

body { font-family:'Outfit',-apple-system,sans-serif; background:var(--bg); min-height:100dvh; color:var(--text-1); }

/* ── Header ── */
.header { background:var(--surface); border-bottom:1px solid var(--border); padding:0 32px; height:58px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
.header-logo { font-family:'Syncopate',sans-serif; font-size:13px; font-weight:700; letter-spacing:4px; color:var(--text-1); }
.header-right { display:flex; align-items:center; gap:18px; }
.header-cliente { font-size:12px; color:var(--text-2); }
.header-codigo  { font-size:10px; font-weight:600; color:var(--amber); letter-spacing:2px; text-transform:uppercase; }
.btn-back { font-size:9.5px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:var(--text-2); background:none; border:1px solid var(--border); border-radius:2px; padding:5px 10px; cursor:pointer; text-decoration:none; display:flex; align-items:center; gap:6px; transition:color .15s,border-color .15s; }
.btn-back:hover { color:var(--text-1); border-color:#B0B5C0; }
.btn-logout { font-size:9.5px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:var(--text-2); background:none; border:1px solid var(--border); border-radius:2px; padding:6px 14px; cursor:pointer; transition:color .15s,border-color .15s; }
.btn-logout:hover { color:var(--text-1); border-color:#B0B5C0; }

@media(max-width:639px) {
  .header { padding:0 18px; gap:10px; }
  .header-cliente { display:none; }
  .btn-logout { display:none; }
  .btn-back { padding:4px 8px; font-size:9px; }
}

/* ── Main ── */
.main { max-width:1000px; margin:0 auto; padding:32px 48px 60px; }
@media(max-width:639px) { .main { padding:20px 14px 48px; } }

/* ── Header card ── */
.cot-header { background:var(--surface); border:1px solid var(--border); border-radius:4px; padding:24px 28px; margin-bottom:24px; }
.cot-folio-row { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:10px; }
.cot-folio { font-family:'Syncopate',sans-serif; font-size:15px; font-weight:700; letter-spacing:3px; color:var(--text-1); }

.tag { display:inline-flex; align-items:center; gap:5px; font-size:10px; font-weight:600; letter-spacing:1.2px; text-transform:uppercase; padding:4px 10px; border-radius:2px; white-space:nowrap; }
.tag-activa    { background:var(--green-bg); color:var(--green); }
.tag-entregada { background:var(--blue-bg); color:var(--blue); }
.tag-proceso   { background:var(--amber-bg); color:#92400e; }
.tag-gris      { background:#f1f5f9; color:#64748b; }
.tag-dot { width:5px; height:5px; border-radius:50%; background:currentColor; display:inline-block; }

.meta-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }
@media(max-width:639px) { .meta-grid { grid-template-columns:1fr 1fr; gap:14px; } }
.meta-label { font-size:9px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:var(--text-3); display:block; margin-bottom:4px; }
.meta-val   { font-size:13px; font-weight:500; color:var(--text-1); }

/* ── Partidas ── */
.section-label { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
.section-label-txt { font-size:9.5px; font-weight:600; letter-spacing:2.5px; text-transform:uppercase; color:var(--text-2); }
.section-label-line { flex:1; height:1px; background:var(--border); }

.table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:4px; overflow:hidden; margin-bottom:24px; }
table { width:100%; border-collapse:collapse; }
thead tr { border-bottom:1px solid var(--border); }
thead th { padding:10px 18px; font-size:9.5px; font-weight:600; text-transform:uppercase; letter-spacing:1.8px; color:var(--text-2); text-align:left; white-space:nowrap; background:#fafafa; }
thead th.right { text-align:right; }
tbody tr { border-bottom:1px solid #F5F6F8; }
tbody tr:last-child { border-bottom:none; }
tbody td { padding:13px 18px; font-size:13px; vertical-align:middle; }
td.right { text-align:right; font-weight:600; }
td.muted { font-size:11px; color:var(--text-2); }

.ptag { font-size:9px; font-weight:600; letter-spacing:.8px; padding:2px 7px; border-radius:2px; display:inline-block; margin:2px 2px 0 0; }
.ptag-cpb  { background:#E0F2FE; color:#0369a1; }
.ptag-trab { background:#F3E8FF; color:#6d28d9; }
.ptag-temp { background:#DBEAFE; color:var(--blue); }

/* ── Resumen ── */
.resumen-card { background:var(--surface); border:1px solid var(--border); border-radius:4px; padding:20px 24px; }
.resumen-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #F5F6F8; font-size:13px; }
.resumen-row:last-child { border-bottom:none; }
.resumen-row.total-row { padding-top:14px; margin-top:4px; border-top:2px solid var(--border); border-bottom:none; }
.resumen-row.total-row .r-lbl { font-size:14px; font-weight:600; color:var(--text-1); }
.resumen-row.total-row .r-val { font-size:16px; font-weight:700; color:var(--text-1); }
.r-lbl { color:var(--text-2); }
.r-val { font-weight:600; color:var(--text-1); }
.r-desc { color:var(--green); font-weight:600; }

.footer { text-align:center; padding:28px 20px; font-size:9px; color:var(--text-3); letter-spacing:2px; text-transform:uppercase; }

/* Mobile: tabla scroll horizontal */
@media(max-width:639px) {
  .table-wrap { overflow-x:auto; }
  .cot-header { padding:18px; }
  .resumen-card { padding:16px 18px; }
}
</style>
</head>
<body>

<div class="header">
  <span class="header-logo">APEX GLASS</span>
  <div class="header-right">
    <a class="btn-back" href="dashboard.php">&#8592; Mis cotizaciones</a>
    <span class="header-cliente"><?= htmlspecialchars($cliente_nombre) ?></span>
    <span class="header-codigo"><?= htmlspecialchars($cliente_codigo) ?></span>
    <button class="btn-logout" onclick="cerrarSesion()">Salir</button>
  </div>
</div>

<div class="main">

  <!-- ── Header cotización ── -->
  <div class="cot-header">
    <div class="cot-folio-row">
      <span class="cot-folio"><?= htmlspecialchars($folio) ?></span>
      <span class="tag <?= $statCls ?>"><span class="tag-dot"></span><?= $statTxt ?></span>
    </div>
    <div class="meta-grid">
      <div><span class="meta-label">Fecha</span><span class="meta-val"><?= $fecha ?></span></div>
      <?php if ($fechaEntrega !== '—'): ?>
      <div><span class="meta-label">Entrega estimada</span><span class="meta-val"><?= $fechaEntrega ?></span></div>
      <?php endif; ?>
      <?php if ($cot['asesor_nombre']): ?>
      <div><span class="meta-label">Asesor</span><span class="meta-val"><?= htmlspecialchars($cot['asesor_nombre']) ?></span></div>
      <?php endif; ?>
      <?php if ($cot['proyecto']): ?>
      <div><span class="meta-label">Proyecto</span><span class="meta-val"><?= htmlspecialchars($cot['proyecto']) ?></span></div>
      <?php endif; ?>
      <div><span class="meta-label">Partidas</span><span class="meta-val"><?= count($partidas) ?></span></div>
      <div>
        <span class="meta-label">Condición de pago</span>
        <span class="meta-val"><?= $cot['condicion_pago'] === 'anticipo' ? 'Anticipo' : 'Pago total' ?></span>
      </div>
    </div>
  </div>

  <!-- ── Partidas ── -->
  <div class="section-label">
    <span class="section-label-txt">Desglose de partidas</span>
    <span class="section-label-line"></span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Material</th>
          <th>Medidas</th>
          <th class="right">m² c/u</th>
          <th class="right">Cant.</th>
          <th class="right">Precio/m²</th>
          <th class="right">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($partidas as $p):
          $sub_p = (float)$p['precio_m2_usado'] * (float)$p['m2'] * (int)$p['cantidad'];
          $trabajos = [];
          if ($p['cpb'])                   $trabajos[] = '<span class="ptag ptag-cpb">CPB ' . htmlspecialchars($p['cpb']) . '</span>';
          if ((int)$p['resaques'] > 0)     $trabajos[] = '<span class="ptag ptag-trab">' . $p['resaques'] . ' resaque(s)</span>';
          if ((int)$p['taladros_pasados'] > 0)     $trabajos[] = '<span class="ptag ptag-trab">TP×' . $p['taladros_pasados'] . '</span>';
          if ((int)$p['taladros_avellanados'] > 0) $trabajos[] = '<span class="ptag ptag-trab">TA×' . $p['taladros_avellanados'] . '</span>';
          if ($p['requiere_templado'])      $trabajos[] = '<span class="ptag ptag-temp">Templado</span>';
          if ($p['detalles'] && $p['detalles'] !== 'NO' && $p['detalles'] !== '')
            $trabajos[] = '<span class="ptag ptag-trab">' . htmlspecialchars($p['detalles']) . '</span>';
        ?>
        <tr>
          <td style="font-weight:600;color:var(--text-2)"><?= $p['num_partida'] ?></td>
          <td>
            <div style="font-weight:600"><?= htmlspecialchars($p['cristal_nombre'] ?: '—') ?></div>
            <?php if ($trabajos): ?>
            <div style="margin-top:5px"><?= implode('', $trabajos) ?></div>
            <?php endif; ?>
            <?php if ($p['comentarios_etiqueta']): ?>
            <div class="muted" style="margin-top:4px;font-size:11px"><?= htmlspecialchars($p['comentarios_etiqueta']) ?></div>
            <?php endif; ?>
          </td>
          <td class="muted"><?= $p['ancho'] ?> &times; <?= $p['alto'] ?> mm</td>
          <td class="right muted"><?= number_format((float)$p['m2'], 4) ?></td>
          <td class="right"><?= $p['cantidad'] ?></td>
          <td class="right muted"><?= fmt($p['precio_m2_usado']) ?></td>
          <td class="right"><?= fmt($sub_p) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Resumen de precios ── -->
  <div class="resumen-card">
    <div class="resumen-row">
      <span class="r-lbl">Subtotal partidas</span>
      <span class="r-val"><?= fmt($subtotal_partidas) ?></span>
    </div>
    <?php if ($servicios > 0): ?>
    <div class="resumen-row">
      <span class="r-lbl">Servicios adicionales</span>
      <span class="r-val"><?= fmt($servicios) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($descuento > 0): ?>
    <div class="resumen-row">
      <span class="r-lbl">Descuento <?= number_format($descuento, 0) ?>%</span>
      <span class="r-desc">-<?= fmt($subtotal_bruto * $descuento / 100) ?></span>
    </div>
    <?php endif; ?>
    <?php if ($descuento > 0 || $servicios > 0): ?>
    <div class="resumen-row">
      <span class="r-lbl">Subtotal</span>
      <span class="r-val"><?= fmt($subtotal_neto) ?></span>
    </div>
    <?php endif; ?>
    <div class="resumen-row">
      <span class="r-lbl">IVA 16%</span>
      <span class="r-val"><?= fmt($iva) ?></span>
    </div>
    <div class="resumen-row total-row">
      <span class="r-lbl">Total</span>
      <span class="r-val"><?= fmt($total) ?></span>
    </div>
  </div>

</div>

<div class="footer">Acceso exclusivo para clientes &mdash; APEX GLASS</div>

<script>
async function cerrarSesion() {
  try {
    var fd = new FormData();
    await fetch('../api/portal_clientes.php?accion=logout', { method: 'POST', body: fd });
  } catch(e) {}
  window.location.href = 'index.php';
}
</script>
</body>
</html>
