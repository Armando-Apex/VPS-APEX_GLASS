<?php
// ============================================================
//  APEX GLASS - Portal Clientes - Beneficios por Referidos
//  Ruta en servidor: /produccion/portal/referidos.php
//  Reemplaza el link "Sorteo" (portal/tablero.php) desde ago-2026.
// ============================================================
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/helpers/referidos_lib.php';

require_once __DIR__ . '/../api/session_boot.php'; // S2-10

if (empty($_SESSION['portal_cliente_id'])) {
    header('Location: index.php');
    exit;
}

$cliente_id = $_SESSION['portal_cliente_id'];
$pdo = getDB();

$stmtCli = $pdo->prepare("SELECT codigo, nombre, razon_social FROM clientes WHERE id = ?");
$stmtCli->execute([$cliente_id]);
$cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);
$mi_codigo = $cliente['codigo'] ?? '';

// Personas que este cliente ha referido
$stmtRef = $pdo->prepare("
    SELECT cl.nombre, cl.razon_social, cr.fecha_registro
    FROM clientes_referidos cr
    JOIN clientes cl ON cl.id = cr.cliente_id
    WHERE cr.referente_cliente_id = ?
    ORDER BY cr.fecha_registro DESC
");
$stmtRef->execute([$cliente_id]);
$referidos = $stmtRef->fetchAll(PDO::FETCH_ASSOC);

// Saldo acumulado por este concepto
$stmtSaldo = $pdo->prepare("SELECT COALESCE(SUM(monto),0) FROM clientes_saldo_favor WHERE cliente_id = ? AND tipo = 'referido'");
$stmtSaldo->execute([$cliente_id]);
$saldo_referidos = (float)$stmtSaldo->fetchColumn();

$promo_activa = referidosPromoActiva();

function fmtDinero($v) { return '$' . number_format((float)$v, 2, '.', ','); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<link rel="icon" type="image/png" href="/favicon/favicon-96x96.png" sizes="96x96">
<link rel="icon" type="image/x-icon" href="/favicon/favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
<link rel="manifest" href="/favicon/site.webmanifest">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>APEX GLASS &mdash; Beneficios por Referidos</title>
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
  --green:    #1E9E5A;
  --green-bg: rgba(30,158,90,.08);
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

.main { max-width: 620px; margin: 0 auto; padding: 40px 24px 60px; }

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

.card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 4px;
  padding: 22px 24px;
  margin-bottom: 20px;
}

.codigo-box {
  border: 1.5px dashed var(--amber);
  border-radius: 4px;
  padding: 18px;
  text-align: center;
  margin-bottom: 10px;
}
.codigo-box-label {
  font-size: 9.5px;
  font-weight: 600;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--text-2);
  margin-bottom: 8px;
}
.codigo-box-val {
  font-size: 28px;
  font-weight: 700;
  letter-spacing: 1px;
  color: var(--amber);
}
.codigo-hint { text-align: center; font-size: 11.5px; color: var(--text-2); }

.saldo-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.saldo-label { font-size: 11px; color: var(--text-2); letter-spacing: .5px; text-transform: uppercase; }
.saldo-val { font-size: 22px; font-weight: 700; color: var(--green); }

.section-label {
  font-size: 9.5px;
  font-weight: 600;
  letter-spacing: 2px;
  text-transform: uppercase;
  color: var(--text-2);
  margin-bottom: 10px;
}

.referido-item {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #F5F6F8;
  font-size: 13px;
}
.referido-item:last-child { border-bottom: none; }
.referido-fecha { color: var(--text-2); font-size: 11.5px; }

.empty {
  text-align: center;
  padding: 20px;
  font-size: 12.5px;
  color: var(--text-3);
}

.promo-badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 600;
  letter-spacing: .5px;
  padding: 4px 10px;
  border-radius: 3px;
  margin-bottom: 16px;
}
.promo-badge.activa { background: var(--green-bg); color: var(--green); }
.promo-badge.inactiva { background: #F0F1F3; color: var(--text-2); }

.footer {
  text-align: center;
  padding: 28px 20px;
  font-size: 9px;
  font-weight: 400;
  color: var(--text-3);
  letter-spacing: 2px;
  text-transform: uppercase;
}

@media (max-width: 560px) {
  .codigo-box-val { font-size: 22px; }
}
</style>
</head>
<body>

<div class="header">
  <span class="header-logo">APEX GLASS</span>
  <a class="header-link" href="dashboard.php">Mi portal</a>
</div>

<div class="main">

  <div class="hero">
    <div class="hero-tag">Beneficios por Referidos</div>
    <div class="hero-title">Comparte tu c&oacute;digo, gana saldo</div>
    <div class="hero-sub">Cuando alguien nuevo compra en APEX GLASS usando tu c&oacute;digo, recibe 5% de descuento y a ti se te abona 5% de saldo a favor por cada compra suya del mes.</div>
  </div>

  <div style="text-align:center;">
    <span class="promo-badge <?= $promo_activa ? 'activa' : 'inactiva' ?>">
      <?= $promo_activa ? 'Promoci&oacute;n activa &mdash; Agosto 2026' : 'Promoci&oacute;n no vigente' ?>
    </span>
  </div>

  <div class="card">
    <div class="codigo-box">
      <div class="codigo-box-label">Tu c&oacute;digo</div>
      <div class="codigo-box-val"><?= htmlspecialchars($mi_codigo) ?></div>
    </div>
    <div class="codigo-hint">Comp&aacute;rtelo con quien quieras referir. Deben decirle este c&oacute;digo a su asesor al cotizar.</div>
  </div>

  <div class="card">
    <div class="saldo-row">
      <span class="saldo-label">Saldo acumulado por referidos</span>
      <span class="saldo-val"><?= fmtDinero($saldo_referidos) ?></span>
    </div>
  </div>

  <div class="card">
    <div class="section-label">Personas que has referido (<?= count($referidos) ?>)</div>
    <?php if (empty($referidos)): ?>
      <div class="empty">A&uacute;n no has referido a nadie</div>
    <?php else: ?>
      <?php foreach ($referidos as $r):
          $nombreRef = $r['razon_social'] ?: $r['nombre'];
          $fecha = $r['fecha_registro'] ? date('d/m/Y', strtotime($r['fecha_registro'])) : '';
      ?>
      <div class="referido-item">
        <span><?= htmlspecialchars($nombreRef) ?></span>
        <span class="referido-fecha"><?= $fecha ?></span>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<div class="footer">APEX GLASS &mdash; Templadora Noreste, S.A. de C.V.</div>

</body>
</html>
