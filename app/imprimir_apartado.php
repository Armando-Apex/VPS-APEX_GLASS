<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user = requirePermiso('ver_ordenes');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo "ID requerido"; exit; }

$db   = getDB();
$stmt = $db->prepare("
    SELECT a.*, sf.monto, sf.fecha AS fecha_deposito, sf.referencia, sf.cliente_id,
           cl.codigo AS cliente_codigo, COALESCE(cl.razon_social, cl.nombre) AS cliente_nombre,
           cl.telefono AS cliente_telefono,
           CASE WHEN a.estatus = 'activo' AND a.vigencia_hasta < CURDATE() THEN 'vencido' ELSE a.estatus END AS estatus_efectivo
    FROM saldo_favor_apartados a
    JOIN clientes_saldo_favor sf ON sf.id = a.saldo_favor_id
    JOIN clientes cl ON cl.id = sf.cliente_id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$a = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$a) { echo "Apartado no encontrado"; exit; }

$stmtI = $db->prepare("
    SELECT i.precio_m2_pactado, i.m2_referencia, c.nombre AS cristal_nombre
    FROM saldo_favor_apartado_items i
    JOIN cristales c ON c.id = i.cristal_id
    WHERE i.apartado_id = ?
    ORDER BY i.id ASC
");
$stmtI->execute([$id]);
$items = $stmtI->fetchAll(PDO::FETCH_ASSOC);

$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
function fechaLarga($f, $meses) {
    if (!$f) return '';
    $d = new DateTime($f);
    return $d->format('d') . ' de ' . $meses[(int)$d->format('m')] . ' de ' . $d->format('Y');
}
$fecha_deposito_fmt = fechaLarga($a['fecha_deposito'], $meses);
$vigencia_hasta_fmt = fechaLarga($a['vigencia_hasta'], $meses);

$estatusInfo = [
    'activo'         => ['label' => 'PRECIO GARANTIZADO — VIGENTE', 'color' => '#15803d', 'bg' => '#dcfce7'],
    'pendiente_vobo' => ['label' => 'PENDIENTE DE APROBACIÓN DEL DIRECTOR', 'color' => '#92400e', 'bg' => '#fef3c7'],
    'rechazado'      => ['label' => 'PRECIO NO AUTORIZADO — SOLO SALDO A FAVOR', 'color' => '#b91c1c', 'bg' => '#fee2e2'],
    'vencido'        => ['label' => 'VIGENCIA VENCIDA — YA NO APLICA', 'color' => '#64748b', 'bg' => '#f1f5f9'],
];
$estCfg = $estatusInfo[$a['estatus_efectivo']] ?? $estatusInfo['activo'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<link rel="icon" type="image/png" href="/favicon/favicon-96x96.png" sizes="96x96">
<link rel="icon" type="image/x-icon" href="/favicon/favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
<link rel="manifest" href="/favicon/site.webmanifest">
<meta charset="UTF-8">
<title>Apartado de Precio — <?= htmlspecialchars($a['cliente_nombre']) ?> — APEX GLASS</title>
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
.doc-title-bar .fecha { font-size: 10px; color:var(--c-muted); }

.estatus-box { border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; font-size: 12px; font-weight: 700; text-align: center; letter-spacing: .3px; }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
.info-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; }
.info-box .box-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-bottom: 6px; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; }
.info-box .row { display: flex; justify-content: space-between; padding: 2px 0; }
.info-box .row .key { color: #64748b; }
.info-box .row .val { font-weight: 600; color: #1e293b; text-align: right; }
.info-box .cliente-nombre { font-size: 13px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }

.section-title { font-size: 11px; font-weight: 700; color: #1a1a2e; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
thead tr { background: #1a1a2e; color: white; }
thead th { padding: 7px 8px; font-size: 9.5px; font-weight: 700; text-align: left; letter-spacing: .3px; }
thead th.right { text-align: right; }
tbody tr { border-bottom: 1px solid #f1f5f9; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody td { padding: 6px 8px; font-size: 10px; vertical-align: top; }
tbody td.right { text-align: right; }

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

.alerta-box { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; font-size: 10px; color: #92400e; font-weight: 600; }

.firma-area { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 16px; }
.firma-box { text-align: center; }
.firma-box .linea { border-top: 1.5px solid #374151; width: 180px; margin: 0 auto 4px; }
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
      <div class="label">APARTADO DE PRECIO</div>
      <div class="fecha"><?= htmlspecialchars($fecha_deposito_fmt) ?></div>
    </div>
    <div class="fecha" style="text-align:right">Folio interno<br><strong style="color:#fff;font-size:14px">#<?= (int)$a['id'] ?></strong></div>
  </div>

  <div class="estatus-box" style="background:<?= $estCfg['bg'] ?>;color:<?= $estCfg['color'] ?>">
    <?= htmlspecialchars($estCfg['label']) ?>
    <?php if ($a['estatus_efectivo'] === 'pendiente_vobo'): ?>
      — vigencia solicitada de <?= (int)$a['vigencia_dias'] ?> días, requiere VoBo del Director
    <?php elseif (in_array($a['estatus_efectivo'], ['activo', 'vencido'])): ?>
      — vigencia hasta el <?= htmlspecialchars($vigencia_hasta_fmt) ?>
    <?php endif; ?>
  </div>

  <div class="info-grid">
    <div class="info-box">
      <div class="box-title">Cliente</div>
      <div class="cliente-nombre"><?= htmlspecialchars($a['cliente_nombre']) ?></div>
      <div class="row"><span class="key">Código</span><span class="val"><?= htmlspecialchars($a['cliente_codigo'] ?: '—') ?></span></div>
      <div class="row"><span class="key">Teléfono</span><span class="val"><?= htmlspecialchars($a['cliente_telefono'] ?: '—') ?></span></div>
    </div>
    <div class="info-box">
      <div class="box-title">Depósito</div>
      <div class="row"><span class="key">Monto depositado</span><span class="val">$<?= number_format((float)$a['monto'], 2) ?></span></div>
      <div class="row"><span class="key">Fecha de depósito</span><span class="val"><?= htmlspecialchars($a['fecha_deposito']) ?></span></div>
      <div class="row"><span class="key">Referencia</span><span class="val"><?= htmlspecialchars($a['referencia'] ?: '—') ?></span></div>
      <div class="row"><span class="key">Vence garantía de precio</span><span class="val"><?= htmlspecialchars($a['vigencia_hasta']) ?></span></div>
    </div>
  </div>

  <div class="section-title">Productos con precio pactado</div>
  <table>
    <thead><tr>
      <th>Producto</th>
      <th class="right">Precio/m² pactado</th>
      <th class="right">m² de referencia</th>
    </tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><?= htmlspecialchars($it['cristal_nombre']) ?></td>
        <td class="right">$<?= number_format((float)$it['precio_m2_pactado'], 2) ?></td>
        <td class="right"><?= $it['m2_referencia'] !== null ? number_format((float)$it['m2_referencia'], 2) . ' m²' : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totales-wrap">
    <div class="totales-box">
      <div class="totales-row final"><span class="label">Monto disponible</span><span class="val">$<?= number_format((float)$a['monto'], 2) ?></span></div>
    </div>
  </div>

  <div class="condiciones">
    <div class="title">Condiciones del apartado</div>
    <ul>
      <li>El monto depositado <strong>no es reembolsable en efectivo</strong>; solo es aplicable a la compra de productos que ofrece APEX GLASS.</li>
      <li>El monto puede repartirse libremente entre los productos listados arriba, en cualquier combinación, mientras no exceda el total depositado.</li>
      <li>El precio pactado por m² solo tiene validez hasta el <strong><?= htmlspecialchars($vigencia_hasta_fmt) ?></strong>. Después de esa fecha, cualquier pedido se cobrará al precio vigente en ese momento, aunque el saldo depositado siga disponible.</li>
      <li>Si el pedido a realizar excede el monto disponible de este apartado, el excedente se define en conjunto con la Dirección antes de aplicarse.</li>
    </ul>
  </div>

  <div class="firma-area">
    <div class="firma-box">
      <div class="linea"></div>
      <div class="nombre"><?= htmlspecialchars($a['cliente_nombre']) ?></div>
      <div class="cargo">Cliente</div>
    </div>
    <div class="firma-box">
      <div class="linea"></div>
      <div class="nombre"><?= htmlspecialchars($a['creado_por']) ?></div>
      <div class="cargo">Registró el depósito</div>
    </div>
  </div>

</div>
</body>
</html>
