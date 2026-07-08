<?php
// ============================================================
//  APEX GLASS - Portal Clientes - Tablero Sorteo Julio 2026
//  Ruta en servidor: /produccion/portal/tablero.php
//  Acceso publico (Top 10 por CTN). Si el cliente ya tiene
//  sesion activa del portal, se agrega su fila personal abajo.
// ============================================================
require_once __DIR__ . '/../api/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = getDB();

// ---- Configuracion del sorteo (ajustar manualmente cada mes) ----
$mesInicio   = '2026-07-01';
$mesFin      = '2026-07-31';
$mesLabel    = 'Julio 2026';
$idsClaro6mm = [1, 16];       // Claro 6mm, Claro 6mm - Servicio Express
$idsClaro9mm = [2, 15, 24];   // Claro 9mm, Claro 9mm - Servicio Express, Claro 9mm - Con Esmerilado
$idsClaroAll = array_merge($idsClaro6mm, $idsClaro9mm);

$sql = "
  SELECT
    cl.id AS cliente_id,
    cl.codigo,
    SUM(CASE WHEN cp.cristal_id IN (" . implode(',', $idsClaro6mm) . ") THEN cp.m2 * cp.cantidad ELSE 0 END) AS m2_6mm,
    SUM(CASE WHEN cp.cristal_id IN (" . implode(',', $idsClaro9mm) . ") THEN cp.m2 * cp.cantidad ELSE 0 END) AS m2_9mm,
    SUM(cp.m2 * cp.cantidad) AS m2_total
  FROM ordenes o
  JOIN cotizaciones c ON c.orden_id = o.id
  JOIN cotizaciones_partidas cp ON cp.cotizacion_id = c.id
  JOIN clientes cl ON cl.id = o.cliente_id
  WHERE o.estado IN ('activa','entregada')
    AND o.fecha_pedido BETWEEN ? AND ?
    AND cp.cristal_id IN (" . implode(',', $idsClaroAll) . ")
  GROUP BY cl.id, cl.codigo
  ORDER BY m2_total DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$mesInicio, $mesFin]);
$ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($ranking as $i => &$r) { $r['lugar'] = $i + 1; }
unset($r);

$top10 = array_slice($ranking, 0, 10);

$clienteSesionId = $_SESSION['portal_cliente_id'] ?? null;
$logueado        = !empty($clienteSesionId);
$miFila          = null;
$estoyEnTop10    = false;

if ($logueado) {
    foreach ($ranking as $r) {
        if ((int)$r['cliente_id'] === (int)$clienteSesionId) { $miFila = $r; break; }
    }
    foreach ($top10 as $r) {
        if ((int)$r['cliente_id'] === (int)$clienteSesionId) { $estoyEnTop10 = true; break; }
    }
}

function fmtM2($v) { return number_format((float)$v, 2, '.', ',') . ' m&sup2;'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>APEX GLASS &mdash; Sorteo <?= htmlspecialchars($mesLabel) ?></title>
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
  --gold:     #C9971F;
  --gold-bg:  rgba(245,166,35,.10);
  --silver:   #8A8F9C;
  --silver-bg:#F0F1F3;
  --bronze:   #A3672F;
  --bronze-bg:rgba(163,103,47,.10);
}

body {
  font-family: 'Outfit', -apple-system, sans-serif;
  background: var(--bg);
  min-height: 100dvh;
  color: var(--text-1);
}

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
.header-link {
  font-size: 9.5px;
  font-weight: 600;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--text-2);
  text-decoration: none;
  border: 1px solid var(--border);
  border-radius: 2px;
  padding: 6px 14px;
  transition: color .15s, border-color .15s;
}
.header-link:hover { color: var(--text-1); border-color: #B0B5C0; }

.main { max-width: 720px; margin: 0 auto; padding: 40px 24px 60px; }

.hero { text-align: center; margin-bottom: 32px; }
.hero-tag {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 9px;
  font-weight: 600;
  color: var(--amber);
  letter-spacing: 2.5px;
  text-transform: uppercase;
  margin-bottom: 14px;
}
.hero-tag::before, .hero-tag::after {
  content: ''; display: block; width: 22px; height: 1px; background: rgba(245,166,35,.28);
}
.hero-title {
  font-size: 22px;
  font-weight: 600;
  letter-spacing: .2px;
  margin-bottom: 8px;
}
.hero-sub {
  font-size: 12.5px;
  color: var(--text-2);
  line-height: 1.6;
  max-width: 460px;
  margin: 0 auto;
}

.table-wrap {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  overflow: hidden;
  margin-bottom: 24px;
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
thead th.num { text-align: right; }
tbody tr { border-bottom: 1px solid #F5F6F8; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 13px 20px; font-size: 13.5px; vertical-align: middle; }
tbody td.num { text-align: right; font-weight: 600; }

.lugar-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 26px; height: 26px;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 700;
  background: #F0F1F3;
  color: var(--text-2);
}
tr.top1 .lugar-badge { background: var(--gold-bg); color: var(--gold); }
tr.top2 .lugar-badge { background: var(--silver-bg); color: var(--silver); }
tr.top3 .lugar-badge { background: var(--bronze-bg); color: var(--bronze); }
tr.top1, tr.top2, tr.top3 { font-weight: 500; }

tr.mi-fila-en-top10 { background: var(--gold-bg); }

.codigo-txt { font-weight: 600; letter-spacing: .3px; color: var(--text-1); }

.empty {
  text-align: center;
  padding: 40px 20px;
  font-size: 13px;
  color: var(--text-3);
  letter-spacing: .3px;
}

/* ── Mi posicion ── */
.mi-pos {
  background: var(--surface);
  border: 1.5px solid var(--amber);
  border-radius: 4px;
  padding: 22px 24px;
  margin-bottom: 24px;
}
.mi-pos-label {
  font-size: 9.5px;
  font-weight: 600;
  letter-spacing: 2.2px;
  text-transform: uppercase;
  color: var(--amber);
  margin-bottom: 14px;
}
.mi-pos-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
}
.mi-pos-item-label {
  font-size: 9.5px;
  color: var(--text-2);
  letter-spacing: .8px;
  text-transform: uppercase;
  margin-bottom: 6px;
}
.mi-pos-item-val { font-size: 17px; font-weight: 600; color: var(--text-1); }
.mi-pos-item-val.destacado { color: var(--amber); }

@media (max-width: 560px) {
  .mi-pos-grid { grid-template-columns: repeat(2, 1fr); }
  thead th, tbody td { padding: 10px 12px; font-size: 12px; }
}

.login-cta {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 18px 22px;
  text-align: center;
  font-size: 12.5px;
  color: var(--text-2);
  margin-bottom: 24px;
}
.login-cta a { color: var(--amber); font-weight: 600; text-decoration: none; }
.login-cta a:hover { text-decoration: underline; }

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
  <?php if ($logueado): ?>
    <a class="header-link" href="dashboard.php">Mi portal</a>
  <?php else: ?>
    <a class="header-link" href="index.php">Entrar</a>
  <?php endif; ?>
</div>

<div class="main">

  <div class="hero">
    <div class="hero-tag">Sorteo <?= htmlspecialchars($mesLabel) ?></div>
    <div class="hero-title">Top consumo Claro 6mm y 9mm</div>
    <div class="hero-sub">Los primeros 3 lugares del mes se llevan un premio. El ranking se identifica solo por c&oacute;digo de cliente.</div>
  </div>

  <?php if (empty($top10)): ?>
    <div class="table-wrap"><div class="empty">A&uacute;n no hay compras registradas este mes</div></div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Lugar</th>
            <th>Cliente</th>
            <th class="num">m&sup2; Claro 6/9mm</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top10 as $r):
            $esMio = $logueado && (int)$r['cliente_id'] === (int)$clienteSesionId;
            $cls = [];
            if ($r['lugar'] === 1) $cls[] = 'top1';
            if ($r['lugar'] === 2) $cls[] = 'top2';
            if ($r['lugar'] === 3) $cls[] = 'top3';
            if ($esMio) $cls[] = 'mi-fila-en-top10';
          ?>
          <tr class="<?= implode(' ', $cls) ?>">
            <td><span class="lugar-badge"><?= $r['lugar'] ?></span></td>
            <td><span class="codigo-txt"><?= htmlspecialchars($r['codigo']) ?></span><?= $esMio ? ' <span style="color:var(--amber);font-size:11px;font-weight:600">(t&uacute;)</span>' : '' ?></td>
            <td class="num"><?= fmtM2($r['m2_total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($logueado): ?>
    <?php if ($miFila && !$estoyEnTop10): ?>
      <div class="mi-pos">
        <div class="mi-pos-label">Tu posici&oacute;n &mdash; Lugar #<?= $miFila['lugar'] ?></div>
        <div class="mi-pos-grid">
          <div>
            <div class="mi-pos-item-label">C&oacute;digo</div>
            <div class="mi-pos-item-val"><?= htmlspecialchars($miFila['codigo']) ?></div>
          </div>
          <div>
            <div class="mi-pos-item-label">Claro 6mm</div>
            <div class="mi-pos-item-val"><?= fmtM2($miFila['m2_6mm']) ?></div>
          </div>
          <div>
            <div class="mi-pos-item-label">Claro 9mm</div>
            <div class="mi-pos-item-val"><?= fmtM2($miFila['m2_9mm']) ?></div>
          </div>
          <div>
            <div class="mi-pos-item-label">Suma total</div>
            <div class="mi-pos-item-val destacado"><?= fmtM2($miFila['m2_total']) ?></div>
          </div>
        </div>
      </div>
    <?php elseif (!$miFila): ?>
      <div class="login-cta">A&uacute;n no tienes compras registradas de Claro 6mm/9mm este mes.</div>
    <?php endif; ?>
  <?php else: ?>
    <div class="login-cta">&iquest;Ya eres cliente? <a href="index.php">Inicia sesi&oacute;n</a> para ver tu posici&oacute;n y tu consumo de m&sup2;.</div>
  <?php endif; ?>

</div>

<div class="footer">Templadora Noreste &mdash; APEX GLASS</div>

</body>
</html>
