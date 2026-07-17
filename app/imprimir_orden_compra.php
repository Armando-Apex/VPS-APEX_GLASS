<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user = requirePermiso('ver_inventario');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo "ID requerido"; exit; }

$db = getDB();
$stmt = $db->prepare("
    SELECT oc.*, p.nombre AS proveedor_nombre, p.contacto AS proveedor_contacto,
           p.telefono AS proveedor_telefono, p.email AS proveedor_email,
           u.nombre AS creado_por
    FROM ordenes_compra oc
    JOIN proveedores p ON p.id = oc.proveedor_id
    LEFT JOIN usuarios u ON u.id = oc.created_by
    WHERE oc.id = ?
");
$stmt->execute([$id]);
$oc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$oc) { echo "Orden de compra no encontrada"; exit; }

$stmtP = $db->prepare("SELECT * FROM oc_partidas WHERE orden_compra_id = ? ORDER BY numero_partida ASC");
$stmtP->execute([$id]);
$partidas = $stmtP->fetchAll(PDO::FETCH_ASSOC);

$subtotal = 0;
foreach ($partidas as $p) { $subtotal += (float)$p['importe']; }
$subtotal = round($subtotal, 2);
$iva   = round($subtotal * 0.16, 2);
$total = round($subtotal * 1.16, 2);

$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$fecha_formateada = '';
if ($oc['fecha_oc']) {
    $d = new DateTime($oc['fecha_oc']);
    $fecha_formateada = $d->format('d') . ' de ' . $meses[(int)$d->format('m')] . ' de ' . $d->format('Y');
}
$fecha_pago_fmt = '';
if ($oc['fecha_pago_programada']) {
    $d2 = new DateTime($oc['fecha_pago_programada']);
    $fecha_pago_fmt = $d2->format('d') . ' de ' . $meses[(int)$d2->format('m')] . ' de ' . $d2->format('Y');
}

$estLbl = ['borrador' => 'Borrador', 'abierta' => 'Abierta', 'cerrada' => 'Cerrada', 'pagada' => 'Pagada', 'cancelada' => 'Cancelada'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Orden de Compra <?= htmlspecialchars($oc['numero_oc']) ?> — APEX GLASS</title>
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #222; background: #fff; }

.page { width: 210mm; min-height: 297mm; margin: 0 auto; padding: 14mm 14mm 20mm; position: relative; }

/* Header */
.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #1a1a2e; padding-bottom: 12px; margin-bottom: 14px; }
.logo-area { display: flex; flex-direction: column; }
.logo-title { font-family: 'Syncopate', sans-serif; font-size: 18px; font-weight: 700; color: #1a1a2e; letter-spacing: 2px; }
.logo-sub { font-size: 9px; color: #64748b; margin-top: 2px; letter-spacing: 1px; text-transform: uppercase; }
.company-info { font-size: 9.5px; color: #374151; text-align: right; line-height: 1.6; }

/* Doc title */
.doc-title-bar { background: #1a1a2e; color: white; padding: 8px 14px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; }
.doc-title-bar .label { font-family: 'Syncopate', sans-serif; font-size: 13px; letter-spacing: 1px; }
.doc-title-bar .folio { font-size: 18px; font-weight: 700; font-family: 'Syncopate', sans-serif; letter-spacing: 3px; }
.doc-title-bar .fecha { font-size: 10px; color: #94a3b8; }

/* Dos columnas info */
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
.info-box { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; }
.info-box .box-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; margin-bottom: 6px; border-bottom: 1px solid #f1f5f9; padding-bottom: 4px; }
.info-box .row { display: flex; justify-content: space-between; padding: 2px 0; }
.info-box .row .key { color: #64748b; }
.info-box .row .val { font-weight: 600; color: #1e293b; text-align: right; }
.info-box .prov-nombre { font-size: 13px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }

/* Tabla partidas */
.section-title { font-size: 11px; font-weight: 700; color: #1a1a2e; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
thead tr { background: #1a1a2e; color: white; }
thead th { padding: 7px 8px; font-size: 9.5px; font-weight: 700; text-align: left; letter-spacing: .3px; }
thead th.right { text-align: right; }
tbody tr { border-bottom: 1px solid #f1f5f9; }
tbody tr:nth-child(even) { background: #f8fafc; }
tbody td { padding: 6px 8px; font-size: 10px; vertical-align: top; }
tbody td.right { text-align: right; }
tbody td.center { text-align: center; }

/* Totales */
.totales-wrap { display: flex; justify-content: flex-end; margin-bottom: 14px; }
.totales-box { width: 240px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.totales-row { display: flex; justify-content: space-between; padding: 6px 14px; font-size: 11px; border-bottom: 1px solid #f1f5f9; }
.totales-row:last-child { border-bottom: none; }
.totales-row .label { color: #64748b; }
.totales-row .val { font-weight: 600; }
.totales-row.final { background: #1a1a2e; }
.totales-row.final .label, .totales-row.final .val { color: white; font-weight: 700; font-size: 13px; }

/* Condiciones */
.condiciones { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; }
.condiciones .title { font-size: 10px; font-weight: 700; color: #374151; margin-bottom: 6px; }
.condiciones ul { padding-left: 16px; }
.condiciones li { font-size: 9.5px; color: #4b5563; margin-bottom: 3px; line-height: 1.5; }

/* Firma */
.firma-area { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 16px; }
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

  <!-- Header -->
  <div class="header">
    <div class="logo-area">
      <div class="logo-title">APEX GLASS</div>
      <div class="logo-sub">Templadora Noreste S.A. de C.V.</div>
    </div>
    <div class="company-info">
      Ave. de la Industria 214, Parque Industrial Marfer<br>
      Santa Catarina, Nuevo León<br>
      Tel: +52 81 1180 5078<br>
      compras@apex.glass · apex.glass
    </div>
  </div>

  <!-- Título y folio -->
  <div class="doc-title-bar">
    <div>
      <div class="label">ORDEN DE COMPRA</div>
      <div class="fecha"><?= htmlspecialchars($fecha_formateada) ?></div>
    </div>
    <div class="folio"><?= htmlspecialchars($oc['numero_oc']) ?></div>
  </div>

  <!-- Info proveedor y detalles -->
  <div class="info-grid">
    <div class="info-box">
      <div class="box-title">Proveedor</div>
      <div class="prov-nombre"><?= htmlspecialchars($oc['proveedor_nombre']) ?></div>
      <?php if (!empty($oc['proveedor_contacto'])): ?>
      <div class="row"><span class="key">Contacto</span><span class="val"><?= htmlspecialchars($oc['proveedor_contacto']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($oc['proveedor_telefono'])): ?>
      <div class="row"><span class="key">Teléfono</span><span class="val"><?= htmlspecialchars($oc['proveedor_telefono']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($oc['proveedor_email'])): ?>
      <div class="row"><span class="key">Email</span><span class="val"><?= htmlspecialchars($oc['proveedor_email']) ?></span></div>
      <?php endif; ?>
    </div>
    <div class="info-box">
      <div class="box-title">Detalles de la Orden</div>
      <div class="row"><span class="key">Tipo</span><span class="val"><?= $oc['tipo'] === 'suministro' ? 'Suministro' : 'Material' ?></span></div>
      <?php if (!empty($oc['categoria'])): ?>
      <div class="row"><span class="key">Categoría</span><span class="val"><?= htmlspecialchars($oc['categoria']) ?></span></div>
      <?php endif; ?>
      <div class="row"><span class="key">Estado</span><span class="val"><?= $estLbl[$oc['estado']] ?? htmlspecialchars($oc['estado']) ?></span></div>
      <div class="row"><span class="key">Días crédito</span><span class="val"><?= (int)$oc['dias_credito'] ?> días</span></div>
      <?php if ($fecha_pago_fmt): ?>
      <div class="row"><span class="key">Pago programado</span><span class="val"><?= $fecha_pago_fmt ?></span></div>
      <?php endif; ?>
      <?php if (!empty($oc['creado_por'])): ?>
      <div class="row"><span class="key">Elaboró</span><span class="val"><?= htmlspecialchars($oc['creado_por']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabla de partidas -->
  <div class="section-title">Partidas</div>
  <table>
    <thead>
      <tr>
        <th style="width:28px">#</th>
        <th>Descripción</th>
        <th class="right">Unidad</th>
        <th class="right">Cantidad</th>
        <th class="right">Precio Unit.</th>
        <th class="right">Importe</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($partidas as $p): ?>
      <tr>
        <td class="center"><?= (int)$p['numero_partida'] ?></td>
        <td><?= htmlspecialchars($p['descripcion']) ?></td>
        <td class="right"><?= htmlspecialchars($p['unidad']) ?></td>
        <td class="right"><?= number_format((float)$p['cantidad'], 2) ?></td>
        <td class="right">$<?= number_format((float)$p['precio_unitario'], 2) ?></td>
        <td class="right">$<?= number_format((float)$p['importe'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totales -->
  <div class="totales-wrap">
    <div class="totales-box">
      <div class="totales-row">
        <span class="label">Subtotal</span>
        <span class="val">$<?= number_format($subtotal, 2) ?></span>
      </div>
      <div class="totales-row">
        <span class="label">IVA (16%)</span>
        <span class="val">$<?= number_format($iva, 2) ?></span>
      </div>
      <div class="totales-row final">
        <span class="label">TOTAL</span>
        <span class="val">$<?= number_format($total, 2) ?> MXN</span>
      </div>
    </div>
  </div>

  <!-- Condiciones y Entrega -->
  <div style="display:grid; grid-template-columns:1fr auto; gap:12px; margin-bottom:14px; align-items:start;">
    <div class="condiciones" style="margin-bottom:0">
      <div class="title">Condiciones Generales</div>
      <ul>
        <li>Los precios están expresados en <strong>Pesos Mexicanos (MXN)</strong> más IVA.</li>
        <li>Favor de confirmar disponibilidad y fecha estimada de entrega al recibir esta orden.</li>
        <li>Cualquier variación en precio o cantidad debe notificarse antes de surtir el pedido.</li>
        <li>Condición de pago: <strong><?= (int)$oc['dias_credito'] > 0 ? (int)$oc['dias_credito'] . ' días de crédito' : 'Contado' ?></strong>.</li>
      </ul>
    </div>
    <div class="condiciones" style="margin-bottom:0; min-width:200px;">
      <div class="title">Entregar en</div>
      <div style="font-weight:700; color:#1e293b; font-size:11px; margin-top:6px; margin-bottom:2px;">Templadora Noreste S.A. de C.V.</div>
      <div style="font-size:10px; color:#374151; line-height:1.6; margin-top:4px;">
        Ave. de la Industria 214<br>
        Parque Industrial Marfer<br>
        Santa Catarina, Nuevo León
      </div>
    </div>
  </div>

  <!-- Firma -->
  <div class="firma-area">
    <div class="firma-box">
      <div class="linea"></div>
      <div class="nombre"><?= htmlspecialchars($oc['creado_por'] ?: 'Compras') ?></div>
      <div class="cargo">Autorizó — APEX GLASS</div>
    </div>
    <div class="firma-box">
      <div class="linea"></div>
      <div class="nombre">&nbsp;</div>
      <div class="cargo">Recibido de conformidad — <?= htmlspecialchars($oc['proveedor_nombre']) ?></div>
    </div>
  </div>

  <?php if (!empty($oc['notas'])): ?>
  <div style="margin-top:14px; padding-top:8px; border-top:1px solid #e2e8f0; font-size:9.5px; color:#4b5563;">
    <strong>Notas:</strong> <?= htmlspecialchars($oc['notas']) ?>
  </div>
  <?php endif; ?>

</div><!-- /page -->
</body>
</html>
