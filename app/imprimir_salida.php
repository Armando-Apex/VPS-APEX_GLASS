<?php
// ============================================================
//  APEX GLASS - Remisión / Orden de Salida
//  Archivo: app/imprimir_salida.php?id=<cotizacion_id>
// ============================================================
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
requirePermiso('ver_ordenes');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die('ID requerido');

$db = getDB();

$stmt = $db->prepare('
    SELECT c.*,
           cl.telefono as cliente_tel, cl.email as cliente_email,
           cl.ciudad as cliente_ciudad
    FROM cotizaciones c
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
    WHERE c.id = ?
');
$stmt->execute([$id]);
$c = $stmt->fetch();
if (!$c) die('No encontrada');

// Verificar que la orden tiene permiso de impresión
$epago        = $c['estatus_pago'] ?? 'pendiente';
$saldo_pagado = (float)($c['saldo_pagado'] ?? 0);
$total_cot    = (float)($c['total'] ?? 0);
$pago_completo = $total_cot > 0 && $saldo_pagado >= $total_cot;
if (!$pago_completo && !in_array($epago, ['en_proceso','pago_entrega','pagado'])) {
    die('Esta orden aún no tiene autorización de salida. Lina debe actualizar el estatus de pago.');
}

$stmt2 = $db->prepare('
    SELECT cp.*
    FROM cotizaciones_partidas cp
    WHERE cp.cotizacion_id = ?
    ORDER BY cp.num_partida ASC
');
$stmt2->execute([$id]);
$parts = $stmt2->fetchAll();

// Buscar orden de producción vinculada para obtener folio
$orden = null;
if ($c['orden_id']) {
    $stmt3 = $db->prepare('SELECT folio, fecha_entrega, tipo_entrega FROM ordenes WHERE id = ?');
    $stmt3->execute([$c['orden_id']]);
    $orden = $stmt3->fetch();
}

$cliente     = $c['cliente_nombre'] ?: '—';
$folio_cot   = $c['folio'] ?: '—';
$folio_orden = $orden['folio'] ?? '—';
$fecha_hoy   = date('d/m/Y');
$fecha_ent   = $orden && $orden['fecha_entrega'] ? date('d/m/Y', strtotime($orden['fecha_entrega'])) : '—';
$asesor      = $c['asesor_nombre'] ?? '—';
$proyecto    = $c['proyecto'] ?: '—';
$tipo_ent    = ($c['tipo_entrega'] ?? $orden['tipo_entrega'] ?? '') === 'domicilio' ? 'Domicilio / Ruta' : 'Recolección en planta';
$localidad   = strtolower($c['localidad'] ?? '') === 'foraneo' ? 'Foráneo — ' . ($c['ciudad_destino'] ?? '') : 'Local';
$cond_pago   = $c['condicion_pago'] ?? '—';
$epago_display = $pago_completo ? 'pagado' : $epago;
$epago_label   = ['pendiente'=>'Pendiente','en_proceso'=>'En proceso','pago_entrega'=>'Pago a la entrega','pagado'=>'Pagado'][$epago_display] ?? $epago_display;

$total_pzas = 0;
$m2_total   = 0;
foreach ($parts as $p) {
    $total_pzas += $p['cantidad'];
    $m2_total   += round(($p['ancho'] / 1000) * ($p['alto'] / 1000), 4) * $p['cantidad'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Remisión <?= htmlspecialchars($folio_cot) ?> — APEX GLASS</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Syncopate:wght@700&family=Inter:wght@400;500;600;700&display=swap');

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', Arial, sans-serif; font-size: 11px; color: #000; background: white; }

.print-bar { background: #1a1a2e; padding: 10px 24px; display: flex; align-items: center; justify-content: space-between; }
.print-bar span { color: #94a3b8; font-size: 12px; }
.btn-print { background: #f5a623; color: #000; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; }

.doc { padding: 20px 28px; max-width: 960px; margin: 0 auto; }

/* Header */
.header-wrap { display: flex; border: 2px solid #000; margin-bottom: 0; }
.header-logo  { width: 90px; min-width: 90px; border-right: 2px solid #000; display: flex; align-items: center; justify-content: center; padding: 8px; }
.header-logo img { max-width: 72px; max-height: 72px; object-fit: contain; }
.header-center { flex: 1; text-align: center; border-right: 2px solid #000; padding: 8px; display: flex; flex-direction: column; justify-content: center; }
.empresa  { font-family: 'Syncopate', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: 1px; }
.doc-tipo { font-size: 14px; font-weight: 700; margin-top: 2px; text-transform: uppercase; }
.doc-sub  { font-size: 10px; color: #555; margin-top: 1px; }
.header-right { width: 160px; min-width: 160px; padding: 8px 10px; display: flex; flex-direction: column; gap: 4px; justify-content: center; }
.hdr-field { font-size: 10px; }
.hdr-field span { font-weight: 700; }

/* Info grid */
.info-table { width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; }
.info-table td { border: 1px solid #000; padding: 5px 8px; font-size: 11px; }
.info-table .lbl { font-weight: 700; background: #f3f4f6; white-space: nowrap; width: 120px; }
.info-table .val { font-weight: 600; }
.epago-badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
.epago-en_proceso   { background: #fef3c7; color: #92400e; }
.epago-pago_entrega { background: #dbeafe; color: #1e40af; }
.epago-pagado       { background: #dcfce7; color: #15803d; }

/* Tabla partidas */
.partidas-table { width: 100%; border-collapse: collapse; margin-top: 14px; }
.partidas-table th {
    border: 1px solid #000; padding: 6px 5px;
    background: #1a1a2e; color: white;
    font-size: 10px; font-weight: 700;
    text-align: center; text-transform: uppercase; letter-spacing: .3px;
}
.partidas-table td {
    border: 1px solid #999; padding: 6px 5px;
    font-size: 11px; vertical-align: middle; text-align: center;
}
.partidas-table td.left { text-align: left; }
.partidas-table tr:nth-child(even) { background: #f9fafb; }
.cristal-cell { font-weight: 600; text-align: left; }

/* Totales */
.totales-row { margin-top: 12px; display: flex; justify-content: space-between; align-items: flex-start; }
.total-box { border: 2px solid #1a1a2e; border-radius: 4px; padding: 8px 20px; font-size: 13px; font-weight: 800; }

/* Logística */
.logistica { margin-top: 18px; border: 1.5px solid #000; border-radius: 4px; }
.log-title { background: #1a1a2e; color: white; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 5px 12px; }
.log-grid { display: grid; grid-template-columns: repeat(4, 1fr); }
.log-field { padding: 8px 12px; border-right: 1px solid #ddd; }
.log-field:last-child { border-right: none; }
.log-lbl { font-size: 9px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 14px; }
.log-line { border-bottom: 1px solid #000; margin-top: 4px; }

/* Firmas */
.firmas { margin-top: 28px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; }
.firma-box { text-align: center; }
.firma-linea { border-top: 1.5px solid #000; margin-bottom: 5px; margin-top: 36px; }
.firma-lbl { font-size: 10px; color: #374151; }

/* Condiciones */
.condiciones { margin-top: 14px; font-size: 9px; color: #555; border-top: 1px solid #e2e8f0; padding-top: 8px; line-height: 1.5; }

/* Pie */
.pie { margin-top: 8px; font-size: 9px; color: #6b7280; border-top: 1px solid #e2e8f0; padding-top: 6px; }

@media print {
    .no-print { display: none !important; }
    body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    @page { margin: 12mm 10mm; size: letter portrait; }
    .doc { padding: 0; }
}
</style>
</head>
<body>

<div class="print-bar no-print">
  <span>Remisión / Orden de Salida — <?= htmlspecialchars($folio_cot) ?></span>
  <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
</div>

<div class="doc">

  <!-- Header -->
  <div class="header-wrap">
    <div class="header-logo">
      <img src="../logoAG.png" alt="APEX GLASS">
    </div>
    <div class="header-center">
      <div class="empresa">TEMPLADORA NORESTE, S. A. DE C. V.</div>
      <div class="doc-tipo">Remisión / Orden de Salida</div>
      <div class="doc-sub">Parque Industrial MARFER, Carr. Monterrey-Saltillo km 65, Av. De la Industria #214, Santa Catarina, N.L.</div>
    </div>
    <div class="header-right">
      <div style="font-size:15px;font-weight:900;color:#1a1a2e;letter-spacing:.5px;border-bottom:2px solid #1a1a2e;padding-bottom:4px;margin-bottom:6px">ORDEN: <?= htmlspecialchars($folio_orden) ?></div>
      <div class="hdr-field"><span>Fecha:</span> <?= $fecha_hoy ?></div>
      <div class="hdr-field"><span>Entrega:</span> <?= $fecha_ent ?></div>
    </div>
  </div>

  <!-- Info cliente / comercial -->
  <table class="info-table">
    <tr>
      <td class="lbl">Cliente:</td>
      <td class="val" colspan="3"><?= htmlspecialchars($cliente) ?></td>
    </tr>
    <tr>
      <td class="lbl">Proyecto:</td>
      <td class="val"><?= htmlspecialchars($proyecto) ?></td>
      <td class="lbl">Asesor:</td>
      <td class="val"><?= htmlspecialchars($asesor) ?></td>
    </tr>
    <tr>
      <td class="lbl">Tipo entrega:</td>
      <td class="val"><?= $tipo_ent ?></td>
      <td class="lbl">Localidad:</td>
      <td class="val"><?= htmlspecialchars($localidad) ?></td>
    </tr>
    <tr>
      <td class="lbl">Condición pago:</td>
      <td class="val"><?= htmlspecialchars($cond_pago) ?></td>
      <td class="lbl">Estatus pago:</td>
      <td class="val">
        <span class="epago-badge epago-<?= $epago_display ?>"><?= $epago_label ?></span>
      </td>
    </tr>
  </table>

  <!-- Tabla partidas -->
  <table class="partidas-table">
    <thead>
      <tr>
        <th style="width:40px">Part.</th>
        <th>Cristal</th>
        <th style="width:75px">Ancho mm</th>
        <th style="width:75px">Alto mm</th>
        <th style="width:55px">Pzas.</th>
        <th style="width:70px">M² total</th>
        <th>Detalles / Especificaciones</th>
        <th>Obs.</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($parts as $p):
      $m2u = round(($p['ancho'] / 1000) * ($p['alto'] / 1000), 4);
      $m2t = round($m2u * $p['cantidad'], 4);
      $specs = [];
      if (!empty($p['cpb']) && strtoupper($p['cpb']) !== 'NO') $specs[] = 'CPB: ' . $p['cpb'];
      if (!empty($p['detalles'])) $specs[] = $p['detalles'];
      if (!empty($p['resaques']) && $p['resaques'] > 0) $specs[] = 'Res: ' . $p['resaques'];
      if (!empty($p['taladros_pasados']) && $p['taladros_pasados'] > 0) $specs[] = 'TP: ' . $p['taladros_pasados'];
      if (!empty($p['taladros_avellanados']) && $p['taladros_avellanados'] > 0) $specs[] = 'TA: ' . $p['taladros_avellanados'];
      $specs[] = $p['requiere_templado'] ? 'Templado' : 'No Templado';
    ?>
      <tr>
        <td style="font-weight:700;color:#1d4ed8"><?= $p['num_partida'] ?></td>
        <td class="cristal-cell">
          <?= htmlspecialchars($p['cristal_nombre'] ?? '—') ?>
        </td>
        <td><?= number_format($p['ancho']) ?></td>
        <td><?= number_format($p['alto']) ?></td>
        <td><?= $p['cantidad'] ?></td>
        <td><?= number_format($m2t, 4) ?></td>
        <td class="left"><?= htmlspecialchars(implode(' · ', $specs) ?: '—') ?></td>
        <td class="left"><?= htmlspecialchars($p['comentarios_etiqueta'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totales -->
  <div class="totales-row">
    <div class="total-box">TOTAL PIEZAS: <?= $total_pzas ?> &nbsp;|&nbsp; TOTAL M²: <?= number_format($m2_total, 4) ?></div>
  </div>

  <!-- Logística -->
  <div class="logistica">
    <div class="log-title">Datos de entrega / logística</div>
    <div class="log-grid">
      <div class="log-field">
        <div class="log-lbl">Chofer</div>
        <div class="log-line"></div>
      </div>
      <div class="log-field">
        <div class="log-lbl">Vehículo / Placas</div>
        <div class="log-line"></div>
      </div>
      <div class="log-field">
        <div class="log-lbl">Fecha y hora de salida</div>
        <div class="log-line"></div>
      </div>
      <div class="log-field">
        <div class="log-lbl">Dirección de entrega</div>
        <div class="log-line"></div>
      </div>
    </div>
  </div>

  <!-- Firmas -->
  <div class="firmas">
    <div class="firma-box">
      <div class="firma-linea"></div>
      <div class="firma-lbl">Entregó — Nombre y firma</div>
    </div>
    <div class="firma-box">
      <div class="firma-linea"></div>
      <div class="firma-lbl">Recibió — Nombre y firma</div>
    </div>
    <div class="firma-box">
      <div class="firma-linea"></div>
      <div class="firma-lbl">Revisó calidad — Nombre y firma</div>
    </div>
  </div>

  <!-- Condiciones -->
  <div class="condiciones">
    + Una vez que la mercancía es recibida mediante firma de conformidad por parte del cliente y/o ha salido de nuestras instalaciones, Templadora Noreste S.A. de C.V. no se hace responsable por daños ocasionados durante el transporte o instalación.<br>
    + Esta remisión ampara únicamente las partidas descritas en el presente documento. Cualquier reclamación deberá hacerse dentro de las 24 horas siguientes a la recepción.
  </div>

  <div class="pie">
    Templadora Noreste, S.A. de C.V. — Tel: 81 1180 5078 — Parque Industrial MARFER, Carr. Monterrey-Saltillo km 65, Santa Catarina, N.L., C.P. 66367
  </div>

</div>
</body>
</html>
