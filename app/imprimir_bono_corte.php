<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user = requirePermiso('ver_contabilidad');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo "ID requerido"; exit; }

$db   = getDB();
$stmt = $db->prepare("
    SELECT bpp.*, u.nombre AS operador_nombre, u2.nombre AS aprobado_por_nombre
    FROM bono_pedaceria_pagos bpp
    JOIN usuarios u ON u.id = bpp.operador_id
    LEFT JOIN usuarios u2 ON u2.id = bpp.aprobado_por
    WHERE bpp.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p) { echo "Recibo no encontrado"; exit; }
if ($p['estado'] !== 'pagado') { echo "Este bono aún no está marcado como pagado — no se puede imprimir el recibo todavía."; exit; }

$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
function fechaLarga($f, $meses) {
    if (!$f) return '';
    $d = new DateTime($f);
    return $d->format('d') . ' de ' . $meses[(int)$d->format('m')] . ' de ' . $d->format('Y');
}
$semana_inicio_fmt = fechaLarga($p['semana_inicio'], $meses);
$semana_fin_fmt    = fechaLarga($p['semana_fin'], $meses);
$pago_fecha_fmt    = fechaLarga($p['aprobado_at'], $meses);
$pago_hora_fmt     = $p['aprobado_at'] ? (new DateTime($p['aprobado_at']))->format('H:i') : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<link rel="icon" type="image/png" href="/favicon/favicon-96x96.png" sizes="96x96">
<link rel="icon" type="image/x-icon" href="/favicon/favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
<link rel="manifest" href="/favicon/site.webmanifest">
<meta charset="UTF-8">
<title>Recibo de Bono — <?= htmlspecialchars($p['operador_nombre']) ?> — APEX GLASS</title>
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #222; background: #fff; }
.page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 14mm 14mm 20mm; position: relative; }

.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1a1a2e; padding-bottom: 12px; margin-bottom: 14px; }
.logo-area { display: flex; flex-direction: column; }
.logo-title { font-family: 'Syncopate', sans-serif; font-size: 18px; font-weight: 700; color: #1a1a2e; letter-spacing: 2px; }
.logo-sub { font-size: 9px; color: #64748b; margin-top: 2px; letter-spacing: 1px; text-transform: uppercase; }
.company-info { font-size: 9.5px; color: #374151; text-align: right; line-height: 1.6; }

.doc-title-bar { background: #1a1a2e; color: white; padding: 8px 14px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.doc-title-bar .label { font-family: 'Syncopate', sans-serif; font-size: 13px; letter-spacing: 1px; }
.doc-title-bar .fecha { font-size: 10px; color: #cbd5e1; }

.estatus-box { border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; font-size: 12px; font-weight: 700; text-align: center; letter-spacing: .3px; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
.info-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; }
.info-box .box-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-bottom: 6px; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; }
.info-box .row { display: flex; justify-content: space-between; padding: 2px 0; }
.info-box .row .key { color: #64748b; }
.info-box .row .val { font-weight: 600; color: #1e293b; text-align: right; }
.info-box .operador-nombre { font-size: 13px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }

.totales-wrap { display: flex; justify-content: flex-end; margin-bottom: 14px; }
.totales-box { width: 260px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.totales-row { display: flex; justify-content: space-between; padding: 6px 14px; font-size: 11px; border-bottom: 1px solid #f1f5f9; }
.totales-row:last-child { border-bottom: none; }
.totales-row .label { color: #64748b; }
.totales-row .val { font-weight: 600; }
.totales-row.final { background: #1a1a2e; }
.totales-row.final .label, .totales-row.final .val { color: white; font-weight: 700; font-size: 13px; }

.condiciones { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; }
.condiciones .title { font-size: 10px; font-weight: 700; color: #374151; margin-bottom: 6px; }
.condiciones ul { padding-left: 16px; }
.condiciones li { font-size: 9.5px; color: #4b5563; margin-bottom: 3px; line-height: 1.5; }

.firma-area { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 40px; border-top: 1px solid #e2e8f0; padding-top: 16px; }
.firma-box { text-align: center; }
.firma-box .linea { border-top: 1.5px solid #374151; width: 200px; margin: 0 auto 4px; }
.firma-box .nombre { font-size: 11px; font-weight: 700; }
.firma-box .cargo { font-size: 9.5px; color: #64748b; }

.btn-print { position: fixed; bottom: 20px; right: 20px; background: #2563eb; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(37,99,235,.4); z-index: 100; }

@media print {
  .btn-print { display: none; }
  .page { padding: 10mm 12mm 15mm; }
  @page { size: A4; margin: 0; }
}
</style>
</head>
<body>

<button class="btn-print" onclick="window.print()">🖨️ Guardar / Imprimir PDF</button>

<div class="page">

  <div class="header">
    <div class="logo-area">
      <div class="logo-title">APEX GLASS</div>
      <div class="logo-sub">Templadora Noreste S.A. de C.V.</div>
    </div>
    <div class="company-info">
      Ave. de la Industria 214, Parque Industrial Marfer<br>
      Santa Catarina, Nuevo León<br>
      Tel: +52 81 1180 5078<br>
      ventas@apex.glass · apex.glass
    </div>
  </div>

  <div class="doc-title-bar">
    <div>
      <div class="label">RECIBO DE BONO — CORTE DE PEDACERÍA</div>
      <div class="fecha">Semana del <?= htmlspecialchars($semana_inicio_fmt) ?> al <?= htmlspecialchars($semana_fin_fmt) ?></div>
    </div>
    <div class="fecha" style="text-align:right">Folio interno<br><strong style="color:#fff;font-size:14px">#<?= (int)$p['id'] ?></strong></div>
  </div>

  <div class="estatus-box">PAGADO</div>

  <div class="info-grid">
    <div class="info-box">
      <div class="box-title">Operador</div>
      <div class="operador-nombre"><?= htmlspecialchars($p['operador_nombre']) ?></div>
      <div class="row"><span class="key">Semana laborada</span><span class="val"><?= htmlspecialchars($semana_inicio_fmt) ?> — <?= htmlspecialchars($semana_fin_fmt) ?></span></div>
      <div class="row"><span class="key">Puesto</span><span class="val">Operador de Corte</span></div>
    </div>
    <div class="info-box">
      <div class="box-title">Pago</div>
      <div class="row"><span class="key">Fecha de pago</span><span class="val"><?= htmlspecialchars($pago_fecha_fmt) ?></span></div>
      <div class="row"><span class="key">Hora de registro</span><span class="val"><?= htmlspecialchars($pago_hora_fmt) ?></span></div>
      <div class="row"><span class="key">Autorizó</span><span class="val"><?= htmlspecialchars($p['aprobado_por_nombre'] ?: '—') ?></span></div>
    </div>
  </div>

  <div class="totales-wrap">
    <div class="totales-box">
      <div class="totales-row"><span class="label">m² de pedacería aprovechados</span><span class="val"><?= number_format((float)$p['m2_elegible'], 2) ?> m²</span></div>
      <div class="totales-row final"><span class="label">Monto pagado</span><span class="val">$<?= number_format((float)$p['monto'], 2) ?></span></div>
    </div>
  </div>

  <div class="condiciones">
    <div class="title">Detalle del cálculo</div>
    <ul>
      <li>El bono se calcula sobre el sobrante de corte (pedacería) que sí se convirtió en piezas de pedido durante la semana, con un tope de tamaño por sesión que no afecta este resultado.</li>
      <li>Fórmula: $150.00 por cada tramo completo de 18 m² aprovechados; el primer tramo (0–18 m²) se paga de forma proporcional.</li>
      <li>Este recibo corresponde a un único pago semanal por operador — no se acumula ni se repite entre semanas.</li>
    </ul>
  </div>

  <div class="firma-area">
    <div class="firma-box">
      <div class="linea"></div>
      <div class="nombre"><?= htmlspecialchars($p['operador_nombre']) ?></div>
      <div class="cargo">Recibí conforme</div>
    </div>
    <div class="firma-box">
      <div class="linea"></div>
      <div class="nombre"><?= htmlspecialchars($p['aprobado_por_nombre'] ?: '—') ?></div>
      <div class="cargo">Autorizó el pago</div>
    </div>
  </div>

</div>
</body>
</html>
