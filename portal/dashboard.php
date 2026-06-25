<?php
// ============================================================
//  APEX GLASS - Portal Clientes - Dashboard
//  Ruta en servidor: /produccion/portal/dashboard.php
// ============================================================
require_once __DIR__ . '/../api/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['portal_cliente_id'])) {
    header('Location: index.php'); exit;
}

$cliente_id     = $_SESSION['portal_cliente_id'];
$cliente_nombre = $_SESSION['portal_cliente_nombre'];
$cliente_codigo = $_SESSION['portal_cliente_codigo'];

$pdo = getDB();

$stmtCli = $pdo->prepare("SELECT razon_social, nombre FROM clientes WHERE id = ?");
$stmtCli->execute([$cliente_id]);
$rowCli = $stmtCli->fetch(PDO::FETCH_ASSOC);
$cliente_razon = $rowCli ? ($rowCli['razon_social'] ?: $rowCli['nombre']) : '';

$stmt = $pdo->prepare("
    SELECT
        o.folio,
        o.orden_trabajo,
        o.proyecto,
        o.fecha_pedido,
        o.fecha_entrega,
        o.fecha_cierre,
        o.estado,
        o.tipo,
        COUNT(p.id) AS total_piezas,
        SUM(p.estatus = 'entregado') AS piezas_entregadas,
        SUM(p.estatus = 'terminado') AS piezas_terminadas,
        ROUND(
            SUM(CASE p.estatus
                WHEN 'pendiente'  THEN 0
                WHEN 'en_corte'   THEN 1
                WHEN 'cortado'    THEN 2
                WHEN 'canteado'   THEN 3
                WHEN 'trazo'      THEN 4
                WHEN 'taladro'    THEN 5
                WHEN 'en_horno'   THEN 6
                WHEN 'terminado'  THEN 8
                WHEN 'entregado'  THEN 8
                ELSE 0
            END)
            / NULLIF(COUNT(p.id) * 8, 0) * 100
        ) AS avance_pct
    FROM ordenes o
    LEFT JOIN piezas p ON p.orden_id = o.id
    WHERE (o.cliente_id = ? OR o.cliente_nombre = ?)
      AND o.estado IN ('activa','entregada')
    GROUP BY o.id
    ORDER BY FIELD(o.estado,'activa','entregada'), o.fecha_pedido DESC
");
$stmt->execute([$cliente_id, $cliente_razon]);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$activas    = array_filter($ordenes, fn($o) => $o['estado'] === 'activa');
$entregadas = array_filter($ordenes, fn($o) => $o['estado'] === 'entregada');

$stmtCot = $pdo->prepare("
    SELECT
        c.folio,
        c.fecha,
        c.proyecto,
        c.total,
        c.estatus,
        c.asesor_nombre
    FROM cotizaciones c
    WHERE (c.cliente_id = ? OR c.cliente_nombre = ?)
    ORDER BY c.fecha DESC
");
$stmtCot->execute([$cliente_id, $cliente_razon]);
$cotizaciones = $stmtCot->fetchAll(PDO::FETCH_ASSOC);

function cotTag($estatus) {
    switch ($estatus) {
        case 'cotizacion': return ['Pendiente',      'tag-proceso'];
        case 'orden':      return ['En producción',  'tag-activa'];
        case 'entregada':  return ['Entregada',      'tag-entregada'];
        case 'cancelada':  return ['Cancelada',      'tag-cancelada'];
        case 'rechazada':  return ['No aprobada',    'tag-cancelada'];
        default:           return [ucfirst($estatus),'tag-cancelada'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>APEX GLASS &mdash; Mis &Oacute;rdenes</title>
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
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 4px;
  color: var(--text-1);
}

.header-right {
  display: flex;
  align-items: center;
  gap: 18px;
}

.header-cliente {
  font-size: 12px;
  font-weight: 400;
  color: var(--text-2);
  letter-spacing: .3px;
}

.header-codigo {
  font-size: 10px;
  font-weight: 600;
  color: var(--amber);
  letter-spacing: 2px;
  text-transform: uppercase;
}

.btn-logout {
  font-size: 9.5px;
  font-weight: 600;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--text-2);
  background: none;
  border: 1px solid var(--border);
  border-radius: 2px;
  padding: 6px 14px;
  cursor: pointer;
  transition: color .15s, border-color .15s;
}
.btn-logout:hover { color: var(--text-1); border-color: #B0B5C0; }

/* ── Main ── */
.main { max-width: 1400px; margin: 0 auto; padding: 36px 48px 60px; }

/* ── Section header ── */
.section-label {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}
.section-label-txt {
  font-size: 9.5px;
  font-weight: 600;
  letter-spacing: 2.5px;
  text-transform: uppercase;
  color: var(--text-2);
}
.section-label-line {
  flex: 1;
  height: 1px;
  background: var(--border);
}
.section-count {
  font-size: 10px;
  font-weight: 600;
  color: var(--text-2);
  letter-spacing: 1px;
}

.section { margin-bottom: 40px; }

/* ── Tabla ── */
.table-wrap {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  overflow: hidden;
}

table { width: 100%; border-collapse: collapse; }

thead tr { border-bottom: 1px solid var(--border); }
thead th {
  padding: 11px 20px;
  font-size: 9.5px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1.8px;
  color: var(--text-2);
  text-align: left;
  white-space: nowrap;
}

tbody tr {
  border-bottom: 1px solid #F5F6F8;
  cursor: pointer;
  transition: background .1s;
}
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: #FAFBFC; }
tbody td { padding: 14px 20px; font-size: 13px; vertical-align: middle; }

.folio-txt    { font-weight: 600; color: var(--text-1); font-size: 14px; letter-spacing: .2px; }
.proyecto-txt { font-size: 11px; color: var(--text-3); margin-top: 2px; letter-spacing: .2px; }
.fecha-txt    { font-size: 12px; color: var(--text-2); white-space: nowrap; font-weight: 400; }

/* status tags */
.tag {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 1.2px;
  text-transform: uppercase;
  padding: 4px 10px;
  border-radius: 2px;
  white-space: nowrap;
}
.tag-activa    { background: var(--green-bg); color: var(--green); }
.tag-entregada { background: var(--blue-bg);  color: var(--blue); }
.tag-proceso   { background: var(--amber-bg); color: #92400e; }
.tag-listo     { background: rgba(6,182,212,.10); color: #0891b2; }
.tag-dot { width: 5px; height: 5px; border-radius: 50%; background: currentColor; display: inline-block; }
.tag-cancelada { background: #f1f5f9; color: #64748b; }
.cot-cancelada { display: none; }
.btn-toggle-cancel {
  font-size: 10px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase;
  color: var(--text-2); background: none; border: none; cursor: pointer;
  padding: 0; text-decoration: underline; text-underline-offset: 2px;
  white-space: nowrap;
}
.btn-toggle-cancel:hover { color: var(--text-1); }

/* barra avance */
.avance-wrap { display: flex; align-items: center; gap: 10px; }
.avance-bar  { flex: 1; height: 3px; background: #ECEEF2; border-radius: 99px; overflow: hidden; min-width: 60px; }
.avance-fill { height: 100%; border-radius: 99px; background: var(--amber); }
.avance-fill.completo { background: var(--green); }
.avance-pct  { font-size: 11px; font-weight: 600; color: var(--text-2); min-width: 30px; text-align: right; }

/* ── CARDS (mobile < 640px) ── */
.cards-list { display: flex; flex-direction: column; gap: 1px; background: var(--border); border: 1px solid var(--border); border-radius: 4px; overflow: hidden; }
.orden-card {
  background: var(--surface);
  padding: 18px 20px;
  cursor: pointer;
  text-decoration: none;
  color: inherit;
  display: block;
  transition: background .1s;
}
.orden-card:active { background: #FAFBFC; }
.card-top    { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; gap: 10px; }
.card-folio  { font-weight: 600; font-size: 15px; color: var(--text-1); letter-spacing: .2px; }
.card-proyecto { font-size: 11px; color: var(--text-3); margin-top: 3px; }
.card-row {
  display: flex; justify-content: space-between;
  font-size: 11px; padding: 7px 0;
  border-bottom: 1px solid #F5F6F8;
}
.card-row:last-of-type { border-bottom: none; }
.card-row-label { color: var(--text-2); letter-spacing: .5px; }
.card-row-val   { font-weight: 500; color: var(--text-1); }
.card-avance    { margin-top: 14px; padding-top: 14px; border-top: 1px solid #F5F6F8; }
.card-avance-top { display: flex; justify-content: space-between; font-size: 10px; color: var(--text-2); margin-bottom: 6px; letter-spacing: .5px; }
.card-avance-bar  { height: 3px; background: #ECEEF2; border-radius: 99px; overflow: hidden; }
.card-avance-fill { height: 100%; border-radius: 99px; background: var(--amber); }

/* mostrar/ocultar */
.desktop-only { display: block; }
.mobile-only  { display: none; }

@media (max-width: 639px) {
  .desktop-only  { display: none !important; }
  .mobile-only   { display: block; }
  .header        { padding: 0 20px; }
  .header-cliente{ display: none; }
  .main          { padding: 24px 16px 48px; }
}

.empty {
  text-align: center;
  padding: 40px 20px;
  font-size: 13px;
  color: var(--text-3);
  letter-spacing: .3px;
}

/* ── Footer ── */
.footer {
  text-align: center;
  padding: 28px 20px;
  font-size: 9px;
  font-weight: 400;
  color: var(--text-3);
  letter-spacing: 2px;
  text-transform: uppercase;
}
</style>
</head>
<body>

<div class="header">
  <span class="header-logo">APEX GLASS</span>
  <div class="header-right">
    <span class="header-cliente"><?= htmlspecialchars($cliente_nombre) ?></span>
    <span class="header-codigo"><?= htmlspecialchars($cliente_codigo) ?></span>
    <button class="btn-logout" onclick="cerrarSesion()">Salir</button>
  </div>
</div>

<div class="main">

  <!-- ── Órdenes activas ── -->
  <div class="section">
    <div class="section-label">
      <span class="section-label-txt">Órdenes activas</span>
      <span class="section-label-line"></span>
      <span class="section-count"><?= count($activas) ?></span>
    </div>

    <?php if (empty($activas)): ?>
      <div class="table-wrap"><div class="empty">No tienes órdenes activas en este momento</div></div>
    <?php else: ?>

      <!-- DESKTOP -->
      <div class="table-wrap desktop-only">
        <table>
          <thead>
            <tr>
              <th>Folio</th>
              <th>Fecha pedido</th>
              <th>Entrega estimada</th>
              <th>Estatus</th>
              <th>Avance</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($activas as $o):
              $avance        = intval($o['avance_pct'] ?? 0);
              $fecha_pedido  = $o['fecha_pedido']  ? date('d M Y', strtotime($o['fecha_pedido']))  : '—';
              $fecha_entrega = $o['fecha_entrega'] ? date('d M Y', strtotime($o['fecha_entrega'])) : '—';
              $url           = 'orden.php?folio=' . urlencode($o['folio']);
            ?>
            <tr onclick="window.location='<?= htmlspecialchars($url) ?>'">
              <td>
                <div class="folio-txt"><?= htmlspecialchars($o['folio']) ?></div>
                <?php if ($o['proyecto']): ?><div class="proyecto-txt"><?= htmlspecialchars($o['proyecto']) ?></div><?php endif; ?>
              </td>
              <td><span class="fecha-txt"><?= $fecha_pedido ?></span></td>
              <td><span class="fecha-txt"><?= $fecha_entrega ?></span></td>
              <td>
                <?php if ($avance === 100): ?>
                  <span class="tag tag-listo"><span class="tag-dot"></span>Listo para entregar</span>
                <?php elseif ($avance === 0): ?>
                  <span class="tag tag-proceso"><span class="tag-dot"></span>Por iniciar</span>
                <?php else: ?>
                  <span class="tag tag-activa"><span class="tag-dot"></span>En producción</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="avance-wrap">
                  <div class="avance-bar"><div class="avance-fill" style="width:<?= $avance ?>%"></div></div>
                  <span class="avance-pct"><?= $avance ?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- MOBILE -->
      <div class="cards-list mobile-only">
        <?php foreach ($activas as $o):
          $avance        = intval($o['avance_pct'] ?? 0);
          $fecha_pedido  = $o['fecha_pedido']  ? date('d M Y', strtotime($o['fecha_pedido']))  : '—';
          $fecha_entrega = $o['fecha_entrega'] ? date('d M Y', strtotime($o['fecha_entrega'])) : '—';
          $url           = 'orden.php?folio=' . urlencode($o['folio']);
          $tagCls = $avance === 100 ? 'tag-listo' : ($avance === 0 ? 'tag-proceso' : 'tag-activa');
          $tagTxt = $avance === 100 ? 'Listo para entregar' : ($avance === 0 ? 'Por iniciar' : 'En producción');
        ?>
        <a class="orden-card" href="<?= htmlspecialchars($url) ?>">
          <div class="card-top">
            <div>
              <div class="card-folio"><?= htmlspecialchars($o['folio']) ?></div>
              <?php if ($o['proyecto']): ?><div class="card-proyecto"><?= htmlspecialchars($o['proyecto']) ?></div><?php endif; ?>
            </div>
            <span class="tag <?= $tagCls ?>"><span class="tag-dot"></span><?= $tagTxt ?></span>
          </div>
          <div class="card-row"><span class="card-row-label">Fecha pedido</span><span class="card-row-val"><?= $fecha_pedido ?></span></div>
          <div class="card-row"><span class="card-row-label">Entrega estimada</span><span class="card-row-val"><?= $fecha_entrega ?></span></div>
          <div class="card-avance">
            <div class="card-avance-top"><span>Avance</span><span><?= $avance ?>%</span></div>
            <div class="card-avance-bar"><div class="card-avance-fill" style="width:<?= $avance ?>%"></div></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>

  <!-- ── Historial ── -->
  <div class="section">
    <div class="section-label">
      <span class="section-label-txt">Historial de entregas</span>
      <span class="section-label-line"></span>
      <span class="section-count"><?= count($entregadas) ?></span>
    </div>

    <?php if (empty($entregadas)): ?>
      <div class="table-wrap"><div class="empty">Sin órdenes entregadas aún</div></div>
    <?php else: ?>

      <!-- DESKTOP -->
      <div class="table-wrap desktop-only">
        <table>
          <thead>
            <tr>
              <th>Folio</th>
              <th>Fecha pedido</th>
              <th>Fecha entrega</th>
              <th>Estatus</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($entregadas as $o):
              $fecha_pedido = $o['fecha_pedido'] ? date('d M Y', strtotime($o['fecha_pedido'])) : '—';
              $fecha_cierre = $o['fecha_cierre']  ? date('d M Y', strtotime($o['fecha_cierre']))  :
                             ($o['fecha_entrega'] ? date('d M Y', strtotime($o['fecha_entrega'])) : '—');
              $url          = 'orden.php?folio=' . urlencode($o['folio']);
              $esActiva100  = $o['estado'] === 'activa' && intval($o['avance_pct']) >= 100;
            ?>
            <tr onclick="window.location='<?= htmlspecialchars($url) ?>'">
              <td>
                <div class="folio-txt"><?= htmlspecialchars($o['folio']) ?></div>
                <?php if ($o['proyecto']): ?><div class="proyecto-txt"><?= htmlspecialchars($o['proyecto']) ?></div><?php endif; ?>
              </td>
              <td><span class="fecha-txt"><?= $fecha_pedido ?></span></td>
              <td><span class="fecha-txt"><?= $fecha_cierre ?></span></td>
              <td>
                <?php if ($esActiva100): ?>
                  <span class="tag tag-proceso"><span class="tag-dot"></span>Lista para entregar</span>
                <?php else: ?>
                  <span class="tag tag-entregada"><span class="tag-dot"></span>Entregada</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- MOBILE -->
      <div class="cards-list mobile-only">
        <?php foreach ($entregadas as $o):
          $fecha_pedido = $o['fecha_pedido'] ? date('d M Y', strtotime($o['fecha_pedido'])) : '—';
          $fecha_cierre = $o['fecha_cierre']  ? date('d M Y', strtotime($o['fecha_cierre']))  :
                         ($o['fecha_entrega'] ? date('d M Y', strtotime($o['fecha_entrega'])) : '—');
          $url         = 'orden.php?folio=' . urlencode($o['folio']);
          $esActiva100 = $o['estado'] === 'activa' && intval($o['avance_pct']) >= 100;
          $tagCls      = $esActiva100 ? 'tag-proceso'   : 'tag-entregada';
          $tagTxt      = $esActiva100 ? 'Lista p/entregar' : 'Entregada';
        ?>
        <a class="orden-card" href="<?= htmlspecialchars($url) ?>">
          <div class="card-top">
            <div>
              <div class="card-folio"><?= htmlspecialchars($o['folio']) ?></div>
              <?php if ($o['proyecto']): ?><div class="card-proyecto"><?= htmlspecialchars($o['proyecto']) ?></div><?php endif; ?>
            </div>
            <span class="tag <?= $tagCls ?>"><span class="tag-dot"></span><?= $tagTxt ?></span>
          </div>
          <div class="card-row"><span class="card-row-label">Fecha pedido</span><span class="card-row-val"><?= $fecha_pedido ?></span></div>
          <div class="card-row"><span class="card-row-label">Fecha entrega</span><span class="card-row-val"><?= $fecha_cierre ?></span></div>
        </a>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>


  <!-- ── Cotizaciones ── -->
  <?php
    $cotActivas    = array_filter($cotizaciones, fn($c) => !in_array($c['estatus'], ['cancelada','rechazada']));
    $cotCanceladas = array_filter($cotizaciones, fn($c) =>  in_array($c['estatus'], ['cancelada','rechazada']));
  ?>
  <div class="section">
    <div class="section-label">
      <span class="section-label-txt">Cotizaciones</span>
      <span class="section-label-line"></span>
      <?php if (count($cotCanceladas) > 0): ?>
        <button class="btn-toggle-cancel" id="btnToggleCancel" onclick="toggleCanceladas()">
          + <?= count($cotCanceladas) ?> cancelada<?= count($cotCanceladas) > 1 ? 's' : '' ?>
        </button>
      <?php endif; ?>
      <span class="section-count"><?= count($cotizaciones) ?></span>
    </div>

    <?php if (empty($cotizaciones)): ?>
      <div class="table-wrap"><div class="empty">No tienes cotizaciones registradas</div></div>
    <?php else: ?>

      <!-- DESKTOP -->
      <div class="table-wrap desktop-only">
        <table>
          <thead>
            <tr>
              <th>Folio</th>
              <th>Fecha</th>
              <th>Proyecto</th>
              <th>Asesor</th>
              <th style="text-align:right">Total</th>
              <th>Estatus</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($cotizaciones as $c):
              [$tagTxt, $tagCls] = cotTag($c['estatus']);
              $fecha     = $c['fecha'] ? date('d M Y', strtotime($c['fecha'])) : '—';
              $total     = '$' . number_format((float)$c['total'], 2, '.', ',');
              $esCancela = in_array($c['estatus'], ['cancelada','rechazada']);
              $urlCot    = 'cotizacion.php?folio=' . urlencode($c['folio']);
            ?>
            <tr class="<?= $esCancela ? 'cot-cancelada' : '' ?>" onclick="window.location='<?= htmlspecialchars($urlCot) ?>'">
              <td><div class="folio-txt"><?= htmlspecialchars($c['folio']) ?></div></td>
              <td><span class="fecha-txt"><?= $fecha ?></span></td>
              <td><span class="fecha-txt"><?= htmlspecialchars($c['proyecto'] ?: '—') ?></span></td>
              <td><span class="fecha-txt"><?= htmlspecialchars($c['asesor_nombre'] ?: '—') ?></span></td>
              <td style="text-align:right"><span class="fecha-txt" style="font-weight:600;color:var(--text-1)"><?= $total ?></span></td>
              <td><span class="tag <?= $tagCls ?>"><span class="tag-dot"></span><?= $tagTxt ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- MOBILE -->
      <div class="cards-list mobile-only">
        <?php foreach ($cotizaciones as $c):
          [$tagTxt, $tagCls] = cotTag($c['estatus']);
          $fecha     = $c['fecha'] ? date('d M Y', strtotime($c['fecha'])) : '—';
          $total     = '$' . number_format((float)$c['total'], 2, '.', ',');
          $esCancela = in_array($c['estatus'], ['cancelada','rechazada']);
          $urlCot    = 'cotizacion.php?folio=' . urlencode($c['folio']);
        ?>
        <a class="orden-card <?= $esCancela ? 'cot-cancelada' : '' ?>" href="<?= htmlspecialchars($urlCot) ?>">
          <div class="card-top">
            <div>
              <div class="card-folio"><?= htmlspecialchars($c['folio']) ?></div>
              <?php if ($c['proyecto']): ?><div class="card-proyecto"><?= htmlspecialchars($c['proyecto']) ?></div><?php endif; ?>
            </div>
            <span class="tag <?= $tagCls ?>"><span class="tag-dot"></span><?= $tagTxt ?></span>
          </div>
          <div class="card-row"><span class="card-row-label">Fecha</span><span class="card-row-val"><?= $fecha ?></span></div>
          <?php if ($c['asesor_nombre']): ?>
          <div class="card-row"><span class="card-row-label">Asesor</span><span class="card-row-val"><?= htmlspecialchars($c['asesor_nombre']) ?></span></div>
          <?php endif; ?>
          <div class="card-row"><span class="card-row-label">Total</span><span class="card-row-val" style="font-weight:600"><?= $total ?></span></div>
        </a>
        <?php endforeach; ?>
      </div>

    <?php endif; ?>
  </div>

</div>

<div class="footer">Acceso exclusivo para clientes &mdash; APEX GLASS</div>

<script>
async function cerrarSesion() {
  try {
    const fd = new FormData();
    await fetch('../api/portal_clientes.php?accion=logout', { method: 'POST', body: fd });
  } catch(e) {}
  window.location.href = 'index.php';
}

var _canceladasVisible = false;
function toggleCanceladas() {
  _canceladasVisible = !_canceladasVisible;
  var items = document.querySelectorAll('.cot-cancelada');
  var btn   = document.getElementById('btnToggleCancel');
  for (var i = 0; i < items.length; i++) {
    items[i].style.display = _canceladasVisible ? '' : 'none';
  }
  if (btn) btn.textContent = _canceladasVisible ? 'Ocultar canceladas' : btn.dataset.original;
}
document.addEventListener('DOMContentLoaded', function() {
  var btn = document.getElementById('btnToggleCancel');
  if (btn) btn.dataset.original = btn.textContent.trim();
});
</script>
</body>
</html>