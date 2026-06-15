<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user  = requirePermiso('ver_ordenes');
$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$remision = !empty($_GET['remision']);
if (!$id) { echo "ID requerido"; exit; }

$db   = getDB();
$stmt = $db->prepare("SELECT c.*, cl.telefono as cli_tel, cl.email as cli_email, cl.ciudad as cli_ciudad,
           o.folio AS orden_folio
    FROM cotizaciones c
    LEFT JOIN clientes cl ON c.cliente_id = cl.id
    LEFT JOIN ordenes o   ON o.id = c.orden_id
    WHERE c.id = ?");
$stmt->execute([$id]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$c) { echo "Cotización no encontrada"; exit; }

$stmt2 = $db->prepare("SELECT * FROM cotizaciones_partidas WHERE cotizacion_id = ? ORDER BY num_partida ASC");
$stmt2->execute([$id]);
$partidas = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Datos del asesor — usa los de la BD o los del Excel como fallback
$asesores_excel = [
    'Bethy Rocha'          => ['movil' => '81 3400 0145', 'email' => 'bethy.rocha@apex.glass'],
    'Cynthia Negrete'      => ['movil' => '81 4005 1992', 'email' => 'cynthia.negrete@apex.glass'],
    'Nadia Zaragoza Garcia'=> ['movil' => '81 2004 8082', 'email' => 'nadia.zaragoza@apex.glass'],
    'Armando Reyna'        => ['movil' => '81 2390 8070', 'email' => 'armando.reyna@apex.glass'],
];

// Mapa de nombre corto → nombre completo
$nombres_completos = [
    'Bethy'   => 'Bethy Rocha',
    'Cynthia' => 'Cynthia Negrete',
    'Nadia'   => 'Nadia Zaragoza Garcia',
    'Armando' => 'Armando Reyna',
];

$asesor_nombre = $c['asesor_nombre'] ?? '';
// Si es nombre corto, resolver al completo
if (isset($nombres_completos[$asesor_nombre])) {
    $asesor_nombre = $nombres_completos[$asesor_nombre];
}
$asesor_movil  = isset($asesores_excel[$asesor_nombre]) ? $asesores_excel[$asesor_nombre]['movil'] : '';
$asesor_email  = isset($asesores_excel[$asesor_nombre]) ? $asesores_excel[$asesor_nombre]['email'] : '';
$tel_empresa = '+52 81 1180 5078';
$tel_fijo    = '81 2315 3005';

$descuento = (float)($c['descuento'] ?? 0);

// Calcular bruto desde precio_m2_usado × m2 × cantidad — misma fórmula que la pantalla.
// precio_unitario en registros viejos almacenaba precio bruto (sin descuento aplicado),
// por lo que SUM(precio_unitario×cantidad) no es confiable como neto.
$subtotal = 0;
foreach ($partidas as $p) {
    $subtotal += (float)$p['precio_m2_usado'] * (float)$p['m2'] * (int)$p['cantidad'];
}
$subtotal = round($subtotal, 2);

$subtotal_neto = ($descuento > 0 && $descuento < 100)
    ? round($subtotal * (1 - $descuento / 100), 2)
    : $subtotal;
$iva   = round($subtotal_neto * 0.16, 2);
$total = round($subtotal_neto * 1.16, 2);

$fecha_formateada = '';
if ($c['fecha']) {
    $d = new DateTime($c['fecha']);
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $fecha_formateada = $d->format('d') . ' de ' . $meses[(int)$d->format('m')] . ' de ' . $d->format('Y');
}
$fecha_entrega_fmt = '';
if ($c['fecha_entrega']) {
    $d2 = new DateTime($c['fecha_entrega']);
    $meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
    $fecha_entrega_fmt = $d2->format('d') . ' de ' . $meses[(int)$d2->format('m')] . ' de ' . $d2->format('Y');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= $remision ? 'Remisión ' . htmlspecialchars($c['orden_folio'] ?: $c['folio']) : ($c['orden_folio'] ? htmlspecialchars($c['orden_folio']) : 'Cotización ' . htmlspecialchars($c['folio'])) ?> — APEX GLASS</title>
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
.info-box .cliente-nombre { font-size: 13px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }

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
.cristal-nombre { font-weight: 600; }
.cristal-det { font-size: 9px; color: #64748b; margin-top: 1px; }

/* Totales */
.totales-wrap { display: flex; justify-content: flex-end; margin-bottom: 14px; }
.totales-box { width: 240px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.totales-row { display: flex; justify-content: space-between; padding: 6px 14px; font-size: 11px; border-bottom: 1px solid #f1f5f9; }
.totales-row:last-child { border-bottom: none; }
.totales-row .label { color: #64748b; }
.totales-row .val { font-weight: 600; }
.totales-row.final { background: #1a1a2e; }
.totales-row.final .label, .totales-row.final .val { color: white; font-weight: 700; font-size: 13px; }
.totales-row.descuento .label, .totales-row.descuento .val { color: #dc2626; }

/* Condiciones */
.condiciones { border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; margin-bottom: 14px; }
.condiciones .title { font-size: 10px; font-weight: 700; color: #374151; margin-bottom: 6px; }
.condiciones ul { padding-left: 16px; }
.condiciones li { font-size: 9.5px; color: #4b5563; margin-bottom: 3px; line-height: 1.5; }

/* Alerta */
.alerta-box { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; font-size: 10px; color: #92400e; font-weight: 600; }

/* Firma */
.firma-area { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 20px; border-top: 1px solid #e2e8f0; padding-top: 16px; }
.firma-box { text-align: center; }
.firma-box .linea { border-top: 1.5px solid #374151; width: 180px; margin: 0 auto 4px; }
.firma-box .nombre { font-size: 11px; font-weight: 700; }
.firma-box .cargo { font-size: 9.5px; color: #64748b; }
.contacto-footer { font-size: 9px; color: #94a3b8; text-align: right; line-height: 1.7; }

/* Print */
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
      ventas@apex.glass · apex.glass
    </div>
  </div>

  <!-- Título y folio -->
  <div class="doc-title-bar">
    <div>
      <div class="label"><?= $remision ? 'REMISIÓN' : ($c['orden_folio'] ? 'ORDEN DE PRODUCCIÓN' : 'COTIZACIÓN') ?></div>
      <div class="fecha"><?= htmlspecialchars($fecha_formateada) ?></div>
      <?php if ($c['orden_folio']): ?>
      <div style="font-size:9px;color:#94a3b8;margin-top:2px">COT: <?= htmlspecialchars($c['folio']) ?></div>
      <?php endif; ?>
    </div>
    <div class="folio"><?= htmlspecialchars($c['orden_folio'] ?: $c['folio']) ?></div>
  </div>

  <?php if (!empty($c['alerta'])): ?>
  <div class="alerta-box">⚠️ <?= htmlspecialchars($c['alerta']) ?></div>
  <?php endif; ?>

  <!-- Info cliente y detalles -->
  <div class="info-grid">
    <div class="info-box">
      <div class="box-title">Cliente</div>
      <div class="cliente-nombre"><?= htmlspecialchars($c['cliente_nombre']) ?></div>
      <?php if (!empty($c['cli_tel'])): ?>
      <div class="row"><span class="key">Teléfono</span><span class="val"><?= htmlspecialchars($c['cli_tel']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($c['cli_email'])): ?>
      <div class="row"><span class="key">Email</span><span class="val"><?= htmlspecialchars($c['cli_email']) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($c['proyecto'])): ?>
      <div class="row"><span class="key">Proyecto</span><span class="val"><?= htmlspecialchars($c['proyecto']) ?></span></div>
      <?php endif; ?>
    </div>
    <div class="info-box">
      <div class="box-title">Detalles de la Cotización</div>
      <div class="row"><span class="key">Asesor</span><span class="val"><?= htmlspecialchars($asesor_nombre) ?></span></div>
      <div class="row"><span class="key">Condición pago</span><span class="val"><?= $c['condicion_pago'] === 'anticipo' ? '50% Anticipo' : 'Pago Total' ?></span></div>
      <div class="row"><span class="key">Entrega</span><span class="val"><?= $c['tipo_entrega'] === 'domicilio' ? 'A domicilio' : 'Recoge en planta' ?></span></div>
      <?php if (!empty($c['ciudad_destino'])): ?>
      <div class="row"><span class="key">Ciudad</span><span class="val"><?= htmlspecialchars($c['ciudad_destino']) ?></span></div>
      <?php endif; ?>
      <?php if ($fecha_entrega_fmt): ?>
      <div class="row"><span class="key">Fecha compromiso</span><span class="val"><?= $fecha_entrega_fmt ?></span></div>
      <?php endif; ?>
      <?php if ($c['credito'] === 'si'): ?>
      <div class="row"><span class="key">Crédito</span><span class="val">Sí</span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabla de partidas -->
  <div class="section-title">Partidas</div>
  <table>
    <thead>
      <tr>
        <th style="width:28px">#</th>
        <th>Cristal</th>
        <th class="right">Medidas (mm)</th>
        <th class="right">Cant.</th>
        <th class="right">m²</th>
        <th class="right">Precio/m²</th>
        <th class="right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($partidas as $p):
        $m2_total = round($p['m2'] * $p['cantidad'], 4);
        $detalles_extra = array_filter([
            $p['detalles'] !== 'NO' ? $p['detalles'] : '',
            $p['cpb'] !== 'No' ? 'CPB: '.$p['cpb'] : '',
            $p['resaques'] > 0 ? $p['resaques'].' resaque(s)' : '',
            $p['taladros_pasados'] > 0 ? $p['taladros_pasados'].' TP' : '',
            $p['taladros_avellanados'] > 0 ? $p['taladros_avellanados'].' TA' : '',
            $p['comentarios_etiqueta'] ?? '',
        ]);
    ?>
      <tr>
        <td class="center"><?= $p['num_partida'] ?></td>
        <td>
          <div class="cristal-nombre"><?= htmlspecialchars($p['cristal_nombre']) ?></div>
          <?php if (!empty($detalles_extra)): ?>
          <div class="cristal-det"><?= htmlspecialchars(implode(' · ', $detalles_extra)) ?></div>
          <?php endif; ?>
        </td>
        <td class="right"><?= number_format($p['ancho'],0) ?> × <?= number_format($p['alto'],0) ?></td>
        <td class="center"><?= $p['cantidad'] ?></td>
        <td class="right"><?= number_format($m2_total, 3) ?></td>
        <td class="right">$<?= number_format($p['precio_m2_usado'], 2) ?></td>
        <td class="right">$<?= number_format($p['subtotal'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totales -->
  <?php
  $resumen_cristal = [];
  $total_piezas = 0;
  $total_m2_general = 0;
  foreach ($partidas as $p) {
      $cristal = $p['cristal_etiqueta'] ?: $p['cristal_nombre'];
      $m2 = round($p['m2'] * $p['cantidad'], 4);
      if (!isset($resumen_cristal[$cristal])) $resumen_cristal[$cristal] = 0;
      $resumen_cristal[$cristal] += $m2;
      $total_piezas += $p['cantidad'];
      $total_m2_general += $m2;
  }
  ?>
  <div style="display:flex; gap:16px; justify-content:space-between; margin-bottom:14px; align-items:flex-start;">

    <!-- Resumen material -->
    <div style="border:1px solid #e2e8f0; border-radius:6px; overflow:hidden; min-width:200px;">
      <div style="background:#f8fafc; padding:5px 10px; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b; border-bottom:1px solid #e2e8f0;">Resumen de Material</div>
      <div style="padding:6px 10px;">
        <?php foreach ($resumen_cristal as $cristal => $m2): ?>
        <div style="display:flex; justify-content:space-between; font-size:9.5px; padding:2px 0; border-bottom:1px solid #f8fafc;">
          <span style="color:#374151;"><?= htmlspecialchars($cristal) ?></span>
          <span style="font-weight:700;"><?= number_format($m2, 3) ?> m²</span>
        </div>
        <?php endforeach; ?>
        <div style="display:flex; justify-content:space-between; font-size:10px; padding:4px 0 2px; border-top:1px solid #e2e8f0; margin-top:3px;">
          <span style="font-weight:700; color:#1a1a2e;">Total m²</span>
          <span style="font-weight:700; color:#1a1a2e;"><?= number_format($total_m2_general, 3) ?></span>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:10px; padding:2px 0;">
          <span style="font-weight:700; color:#1a1a2e;">Total piezas</span>
          <span style="font-weight:700; color:#1a1a2e;"><?= $total_piezas ?></span>
        </div>
      </div>
    </div>

    <!-- Totales monetarios -->
    <div class="totales-box" style="flex-shrink:0;">
      <div class="totales-row">
        <span class="label">Subtotal</span>
        <span class="val">$<?= number_format($subtotal, 2) ?></span>
      </div>
      <?php if ($descuento > 0): ?>
      <div class="totales-row descuento">
        <span class="label">Descuento</span>
        <span class="val">-$<?= number_format($subtotal * $descuento / 100, 2) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($iva > 0): ?>
      <div class="totales-row">
        <span class="label">IVA (16%)</span>
        <span class="val">$<?= number_format($iva, 2) ?></span>
      </div>
      <?php endif; ?>
      <div class="totales-row final">
        <span class="label">TOTAL</span>
        <span class="val">$<?= number_format($total, 2) ?> MXN</span>
      </div>
    </div>

  </div>

  <!-- Condiciones y Datos Bancarios -->
  <div style="display:grid; grid-template-columns:1fr auto; gap:12px; margin-bottom:14px; align-items:start;">
    <div class="condiciones" style="margin-bottom:0">
      <div class="title">Condiciones Generales</div>
      <ul>
        <li>Esta cotización tiene vigencia de <strong>15 días naturales</strong> a partir de la fecha de emisión.</li>
        <li>Los precios están expresados en <strong>Pesos Mexicanos (MXN)</strong> e incluyen IVA.</li>
        <?php if ($c['condicion_pago'] === 'anticipo'): ?>
        <li>Se requiere <strong>50% de anticipo</strong> para iniciar la producción. Saldo al momento de la entrega.</li>
        <?php else: ?>
        <li>Se requiere <strong>pago total</strong> previo a la entrega del material.</li>
        <?php endif; ?>
        <li>El tiempo de entrega es a partir de recibido el anticipo y aprobación del cliente.</li>
        <li>El vidrio templado <strong>no admite cortes ni perforaciones</strong> una vez procesado.</li>
        <li>Precios sujetos a cambio sin previo aviso por variaciones en el mercado.</li>
      </ul>
    </div>
    <div class="condiciones" style="margin-bottom:0; min-width:200px;">
      <div class="title">Datos Bancarios</div>
      <div style="margin-top:6px;">
        <div style="padding:4px 0; border-bottom:1px solid #f1f5f9;">
          <span style="color:#64748b; font-size:9.5px;">Banco</span><br>
          <span style="font-weight:700; color:#1e293b; font-size:11px;">BBVA</span>
        </div>
        <div style="padding:4px 0; border-bottom:1px solid #f1f5f9;">
          <span style="color:#64748b; font-size:9.5px;">Cuenta</span><br>
          <span style="font-weight:700; color:#1e293b; font-size:11px;">0196573215</span>
        </div>
        <div style="padding:4px 0;">
          <span style="color:#64748b; font-size:9.5px;">CLABE Interbancaria</span><br>
          <span style="font-weight:700; color:#1e293b; font-size:11px; letter-spacing:.5px;">012580001965732153</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Firma -->
  <div class="firma-area">
    <div class="firma-box">
      <div class="linea"></div>
      <div class="nombre"><?= htmlspecialchars($asesor_nombre) ?></div>
      <div class="cargo">Asesor Comercial — APEX GLASS</div>
    </div>
    <div style="border:1px solid #e2e8f0; border-radius:6px; overflow:hidden; min-width:210px;">
      <div style="background:#f8fafc; padding:5px 12px; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#64748b; border-bottom:1px solid #e2e8f0;">Pagos</div>
      <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px; border-bottom:1px solid #f1f5f9;">
        <span style="font-size:11px; font-weight:700; color:#374151;">Anticipo</span>
        <span style="font-size:11px; color:#1e293b; min-width:95px; border-bottom:1px solid #374151; display:inline-block; height:18px;">&nbsp;</span>
      </div>
      <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 12px;">
        <span style="font-size:11px; font-weight:700; color:#374151;">Restante</span>
        <span style="font-size:11px; color:#1e293b; min-width:95px; border-bottom:1px solid #374151; display:inline-block; height:18px;">&nbsp;</span>
      </div>
    </div>
  </div>

  <?php if (!empty($c['factura_tipo']) && $c['factura_tipo'] === 'generica'): ?>
  <div style="margin-top:14px; padding-top:8px; border-top:1px solid #e2e8f0; text-align:center; font-size:9px; color:#94a3b8; letter-spacing:.3px;">
    Factura: PÚBLICO EN GENERAL
  </div>
  <?php endif; ?>

</div><!-- /page -->
</body>
</html>