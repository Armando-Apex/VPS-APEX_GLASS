<?php
// ============================================================
//  APEX GLASS - Hoja de Ruta (impresión)
//  Archivo: app/imprimir_ruta.php?id=<ruta_id>
//  Una sección por parada, en el orden de visita ya optimizado, con datos del
//  cliente (nombre/teléfono/dirección) y su propio QR — ese QR es el que el
//  chofer escanea al SALIR hacia cada cliente (ver api/salidas.php accion=scan_qr_ruta).
// ============================================================
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
requirePermiso('ver_ordenes');

$ruta_id = (int)($_GET['id'] ?? 0);
if (!$ruta_id) die('ID de ruta requerido');

$db = getDB();

$stmt = $db->prepare('SELECT * FROM rutas WHERE id = ?');
$stmt->execute([$ruta_id]);
$ruta = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ruta) die('Ruta no encontrada');

$stmt = $db->prepare("
    SELECT re.*, o.folio, o.cliente_id,
           COALESCE(cl.nombre, o.cliente_nombre) AS cliente_nombre,
           cl.telefono, cl.telefono_alterno
    FROM ruta_entregas re
    JOIN ordenes o ON o.id = re.orden_id
    LEFT JOIN clientes cl ON cl.id = o.cliente_id
    WHERE re.ruta_id = ? AND re.estado = 'pendiente'
    ORDER BY re.secuencia ASC
");
$stmt->execute([$ruta_id]);
$paradas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$UNIDAD_LABEL = ['gris' => 'Nissan Gris', 'blanca' => 'NP TN - Blanca'];
$unidadLabel  = $UNIDAD_LABEL[$ruta['unidad']] ?? $ruta['unidad'];
$fechaFmt     = date('d/m/Y', strtotime($ruta['fecha']));

// Con pocas paradas, se agrandan las tarjetas y el QR para aprovechar la hoja —
// más fácil de leer/escanear para el chofer que un bloque chico perdido en la página.
$n = count($paradas);
if ($n <= 2)      { $qrPx = 130; $fFolio = 26; $fCliente = 22; $fDato = 17; $fNum = 40; $pad = 28; }
elseif ($n <= 4)  { $qrPx = 100; $fFolio = 20; $fCliente = 17; $fDato = 14; $fNum = 30; $pad = 20; }
else              { $qrPx = 64;  $fFolio = 15; $fCliente = 14; $fDato = 12; $fNum = 22; $pad = 14; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Hoja de Ruta — <?= htmlspecialchars($unidadLabel) ?> — <?= htmlspecialchars($fechaFmt) ?></title>
<script src="../lib/qrcode.min.js"></script>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 20px; color: #1e293b; }
  .header-wrap { display: flex; border: 2px solid #000; margin-bottom: 16px; }
  .header-logo { width: 90px; min-width: 90px; border-right: 2px solid #000; display: flex; align-items: center; justify-content: center; padding: 8px; }
  .header-logo img { max-width: 72px; max-height: 72px; object-fit: contain; }
  .header-center { flex: 1; text-align: center; border-right: 2px solid #000; padding: 8px; display: flex; flex-direction: column; justify-content: center; }
  .header-center .empresa { font-size: 15px; font-weight: 900; color: #1a1a2e; letter-spacing: .5px; }
  .header-center .doc-tipo { font-size: 13px; font-weight: 700; margin-top: 2px; }
  .header-center .doc-sub { font-size: 10px; color: #475569; margin-top: 2px; }
  .header-right { width: 200px; min-width: 200px; padding: 8px 10px; display: flex; flex-direction: column; justify-content: center; gap: 4px; font-size: 12px; }
  .header-right b { color: #64748b; }
  .parada { border: 1.5px solid #cbd5e1; border-radius: 8px; padding: <?= $pad ?>px; margin-bottom: 16px; display: flex; align-items: center; gap: 20px; page-break-inside: avoid; }
  .parada-num { font-size: <?= $fNum ?>px; font-weight: 800; color: #2563eb; min-width: <?= round($fNum*1.4) ?>px; }
  .parada-info { flex: 1; }
  .parada-folio { font-size: <?= $fFolio ?>px; font-weight: 700; color: #1e293b; }
  .parada-cliente { font-size: <?= $fCliente ?>px; font-weight: 600; margin-top: 4px; }
  .parada-dato { font-size: <?= $fDato ?>px; color: #334155; margin-top: 6px; }
  .parada-dato b { color: #64748b; font-weight: 600; }
  .parada-qr { text-align: center; }
  .parada-qr .lbl { font-size: <?= max(9, round($fDato*0.7)) ?>px; color: #64748b; margin-top: 4px; }
  .btn-imprimir { position: fixed; top: 14px; right: 14px; padding: 10px 18px; background: #2563eb; color: white; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; }
  @media print {
    .btn-imprimir { display: none; }
    body { padding: 0; }
  }
</style>
</head>
<body>

<button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir</button>

<div class="header-wrap">
  <div class="header-logo">
    <img src="../logoAG.png" alt="APEX GLASS">
  </div>
  <div class="header-center">
    <div class="empresa">TEMPLADORA NORESTE, S. A. DE C. V.</div>
    <div class="doc-tipo">Hoja de Ruta</div>
    <div class="doc-sub">Parque Industrial MARFER, Carr. Monterrey-Saltillo km 65, Av. De la Industria #214, Santa Catarina, N.L.</div>
  </div>
  <div class="header-right">
    <div><b>Unidad:</b> <?= htmlspecialchars($unidadLabel) ?></div>
    <div><b>Chofer:</b> <?= htmlspecialchars($ruta['chofer'] ?: 'Sin asignar') ?></div>
    <div><b>Fecha:</b> <?= htmlspecialchars($fechaFmt) ?></div>
    <div><b>Paradas:</b> <?= count($paradas) ?></div>
  </div>
</div>

<?php if (!$paradas): ?>
  <p>Esta ruta no tiene paradas pendientes.</p>
<?php else: ?>
  <?php foreach ($paradas as $i => $p): ?>
    <div class="parada">
      <div class="parada-num"><?= $i + 1 ?></div>
      <div class="parada-info">
        <div class="parada-folio"><?= htmlspecialchars($p['folio']) ?></div>
        <div class="parada-cliente"><?= htmlspecialchars($p['cliente_nombre'] ?: '—') ?></div>
        <div class="parada-dato"><b>Tel:</b> <?= htmlspecialchars($p['telefono_alterno'] ?: $p['telefono'] ?: '—') ?></div>
        <div class="parada-dato"><b>Dirección:</b> <?= htmlspecialchars(trim(implode(', ', array_filter([$p['direccion'], $p['colonia'], $p['ciudad']])))) ?></div>
        <?php if (!empty($p['referencias'])): ?>
          <div class="parada-dato"><b>Referencias:</b> <?= htmlspecialchars($p['referencias']) ?></div>
        <?php endif; ?>
      </div>
      <div class="parada-qr">
        <div id="qr-<?= (int)$p['id'] ?>"></div>
        <div class="lbl">Escanear al salir</div>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<script>
(function() {
  if (typeof QRCode === 'undefined') return;
  var qrPx = <?= (int)$qrPx ?>;
  var paradas = <?= json_encode(array_map(function($p){ return ['id'=>(int)$p['id'], 'orden_id'=>(int)$p['orden_id']]; }, $paradas)) ?>;
  paradas.forEach(function(p) {
    var el = document.getElementById('qr-' + p.id);
    if (!el) return;
    var url = 'https://apex.glass/produccion/app/operador.php?qr_ruta=' + p.orden_id;
    new QRCode(el, { text: url, width: qrPx, height: qrPx, colorDark: '#000000', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M });
  });
})();
</script>

</body>
</html>
