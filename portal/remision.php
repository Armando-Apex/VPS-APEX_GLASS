<?php
// ============================================================
//  APEX GLASS - Portal Clientes - Remisión (solo lectura)
//  Ruta en servidor: /produccion/portal/remision.php?folio=S-XXX
//
//  Vista de solo consulta del mismo documento que genera
//  app/imprimir_salida.php — NO reutiliza ese archivo porque es una
//  herramienta interactiva de staff (selecciona piezas, registra
//  nuevas entregas vía api/salidas.php) y no es seguro exponerla tal
//  cual a clientes. Aquí se recalcula el mismo documento en modo
//  lectura, con el auth del portal en vez de sesión interna.
// ============================================================
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/helpers/totales.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['portal_cliente_id'])) {
    header('Location: index.php'); exit;
}

$cliente_id = $_SESSION['portal_cliente_id'];
$folio      = trim($_GET['folio'] ?? '');
if (!$folio) { header('Location: dashboard.php'); exit; }

$db = getDB();

$stmtCli = $db->prepare("SELECT razon_social, nombre FROM clientes WHERE id = ?");
$stmtCli->execute([$cliente_id]);
$rowCli = $stmtCli->fetch();
$cliente_razon = $rowCli ? ($rowCli['razon_social'] ?: $rowCli['nombre']) : '';

// La orden debe pertenecer al cliente logueado (mismo criterio que portal/orden.php)
$stmtOrd = $db->prepare("
    SELECT id FROM ordenes
    WHERE folio = ? AND (cliente_id = ? OR cliente_nombre = ?)
    LIMIT 1
");
$stmtOrd->execute([$folio, $cliente_id, $cliente_razon]);
$ordenRow = $stmtOrd->fetch();
if (!$ordenRow) { header('Location: dashboard.php'); exit; }
$orden_id_php = (int)$ordenRow['id'];

// Disponible desde que existe la orden — sin esperar a que se registre una salida
// real. Cuando todavía no hay ninguna, el documento se arma igual (fecha_entrega
// estimada de la orden, tipo de entrega según la preferencia de la cotización) y cada
// partida muestra "PENDIENTE" en la columna Entrega (ver lógica más abajo).
$stmt = $db->prepare('
    SELECT c.*, cl.telefono AS cliente_tel, cl.email AS cliente_email, cl.ciudad AS cliente_ciudad
    FROM cotizaciones c
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
    WHERE c.orden_id = ?
    ORDER BY c.id DESC LIMIT 1
');
$stmt->execute([$orden_id_php]);
$c = $stmt->fetch();
if (!$c) { header('Location: orden.php?folio=' . urlencode($folio)); exit; }
$cotizacion_id_php = (int)$c['id'];

$totales_cot = apexTotalesCotizacion($db, $cotizacion_id_php);
$total_cot   = $totales_cot ? $totales_cot['total'] : 0.0;

$stmt2 = $db->prepare('SELECT * FROM cotizaciones_partidas WHERE cotizacion_id = ? ORDER BY num_partida ASC');
$stmt2->execute([$cotizacion_id_php]);
$parts = $stmt2->fetchAll(PDO::FETCH_ASSOC);
$parts_idx = [];
foreach ($parts as $pt) { $parts_idx[(int)$pt['num_partida']] = $pt; }

$stmt3 = $db->prepare('SELECT id, folio, fecha_entrega, tipo_entrega, estado, fecha_entrega_chofer FROM ordenes WHERE id = ?');
$stmt3->execute([$orden_id_php]);
$orden = $stmt3->fetch(PDO::FETCH_ASSOC);

$cliente     = $c['cliente_nombre'] ?: '—';
$folio_cot   = $c['folio'] ?: '—';
$folio_orden = $orden['folio'] ?? '—';
$fecha_hoy   = date('d/m/Y');
$fecha_ent   = $orden['fecha_entrega'] ? date('d/m/Y', strtotime($orden['fecha_entrega'])) : '—';
$asesor      = $c['asesor_nombre'] ?? '—';
$proyecto    = $c['proyecto'] ?: '—';

$stSal = $db->prepare('SELECT tipo FROM orden_salidas WHERE orden_id = ? ORDER BY id DESC LIMIT 1');
$stSal->execute([$orden_id_php]);
$tipoSalidaReal = $stSal->fetchColumn() ?: null;
$tipo_ent   = $tipoSalidaReal !== null
    ? $tipoSalidaReal
    : ((($c['tipo_entrega'] ?? $orden['tipo_entrega'] ?? '') === 'domicilio') ? 'chofer' : 'recoleccion');
$tipo_label = $tipo_ent === 'chofer' ? 'Domicilio / Ruta' : 'Recolección en planta';
$localidad  = strtolower($c['localidad'] ?? '') === 'foraneo' ? 'Foráneo — ' . ($c['ciudad_destino'] ?? '') : 'Local';
$cond_pago  = $c['condicion_pago'] ?? '—';

$epago         = $c['estatus_pago'] ?? 'pendiente';
$saldo_pagado  = (float)($c['saldo_pagado'] ?? 0);
$pago_completo = $total_cot > 0 && $saldo_pagado >= $total_cot - 0.99;
$epago_display = $pago_completo ? 'pagado' : $epago;
$epago_label   = ['pendiente'=>'Pendiente','en_proceso'=>'En proceso','pago_entrega'=>'Pago a la entrega','pagado'=>'Pagado'][$epago_display] ?? $epago_display;

$fecha_chofer_php = !empty($orden['fecha_entrega_chofer']) ? date('Y-m-d', strtotime($orden['fecha_entrega_chofer'])) : '';
if ($fecha_chofer_php) {
    $fecha_ent = date('d/m/Y', strtotime($fecha_chofer_php));
}

// ── Fechas de entrega por pieza (de todas las salidas registradas) ──────────
$fechas_entrega = [];
$stmtFe = $db->prepare('
    SELECT osp.pieza_id, COALESCE(os.fecha_entrega_chofer, os.created_at) AS f
    FROM orden_salidas os
    JOIN orden_salida_piezas osp ON osp.salida_id = os.id
    WHERE os.orden_id = ?
');
$stmtFe->execute([$orden_id_php]);
foreach ($stmtFe->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $fechas_entrega[(int)$row['pieza_id']] = date('Y-m-d', strtotime($row['f']));
}

// ── Tabla de partidas (misma lógica que app/imprimir_salida.php) ────────────
$stmtPz = $db->prepare('
    SELECT id, partida, pieza_num, pieza_total, ancho_mm, alto_mm, m2, cristal_corto
    FROM piezas WHERE orden_id = ?
    ORDER BY partida ASC, pieza_num ASC
');
$stmtPz->execute([$orden_id_php]);
$piezas_doc = $stmtPz->fetchAll(PDO::FETCH_ASSOC);

$grupos_php = [];
foreach ($piezas_doc as $p) { $grupos_php[(int)$p['partida']][] = $p; }
ksort($grupos_php);

$meses_doc  = ['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
$tbody_html = '';
$tot_pz = 0; $tot_m2 = 0.0;

foreach ($grupos_php as $np => $grp) {
    $pt   = $parts_idx[$np] ?? [];
    $cant = count($grp);
    $m2u  = (float)($grp[0]['m2'] ?? 0);
    $m2t  = round($m2u * $cant, 4);
    $tot_pz += $cant; $tot_m2 += $m2t;

    $specs = [];
    if (!empty($pt['cpb']) && strtoupper($pt['cpb']) !== 'NO') $specs[] = 'CPB: ' . $pt['cpb'];
    if (!empty($pt['detalles'])) $specs[] = $pt['detalles'];
    if (!empty($pt['resaques']) && $pt['resaques'] > 0) $specs[] = 'Res: ' . $pt['resaques'];
    if (!empty($pt['taladros_pasados']) && $pt['taladros_pasados'] > 0) $specs[] = 'TP: ' . $pt['taladros_pasados'];
    if (!empty($pt['taladros_avellanados']) && $pt['taladros_avellanados'] > 0) $specs[] = 'TA: ' . $pt['taladros_avellanados'];
    $specs[] = !empty($pt['requiere_templado']) ? 'Templado' : 'No Templado';

    $mat   = $pt['cristal_nombre'] ?? ($grp[0]['cristal_corto'] ?? '—');
    $ancho = $grp[0]['ancho_mm'] ?? ($pt['ancho'] ?? '—');
    $alto  = $grp[0]['alto_mm']  ?? ($pt['alto']  ?? '—');

    $pids_grupo = array_map(fn($p) => (int)$p['id'], $grp);
    $ent_dates  = array_filter(array_map(fn($pid) => $fechas_entrega[$pid] ?? null, $pids_grupo));
    $cnt_ent    = count($ent_dates);

    if ($cnt_ent === 0) {
        $entrega_html = '<span class="ent-pendiente">PENDIENTE</span>';
    } else {
        $ultimo = max($ent_dates);
        $ts     = strtotime($ultimo);
        $fmtd   = date('d', $ts) . '/' . $meses_doc[(int)date('n', $ts)] . '/' . date('Y', $ts);
        $entrega_html = $cnt_ent === $cant
            ? '<span class="ent-fecha">' . $fmtd . '</span>'
            : '<span class="ent-parcial">' . $cnt_ent . '/' . $cant . ' al ' . $fmtd . '</span>';
    }

    $tbody_html .= '<tr>';
    $tbody_html .= '<td style="font-weight:700;color:#1d4ed8">' . (int)$np . '</td>';
    $tbody_html .= '<td class="cristal-cell">' . htmlspecialchars($mat) . '</td>';
    $tbody_html .= '<td>' . htmlspecialchars((string)$ancho) . '</td>';
    $tbody_html .= '<td>' . htmlspecialchars((string)$alto) . '</td>';
    $tbody_html .= '<td>' . $cant . '</td>';
    $tbody_html .= '<td>' . number_format($m2t, 4) . '</td>';
    $tbody_html .= '<td class="left">' . htmlspecialchars(implode(' · ', $specs) ?: '—') . '</td>';
    $tbody_html .= '<td class="left">' . htmlspecialchars($pt['comentarios_etiqueta'] ?? '') . '</td>';
    $tbody_html .= '<td class="entrega-col">' . $entrega_html . '</td>';
    $tbody_html .= '</tr>';
}
$totales_txt = 'TOTAL PIEZAS: ' . $tot_pz . '  |  TOTAL M²: ' . number_format($tot_m2, 4);

$pendientes = ($tot_pz > 0 && strpos($tbody_html, 'ent-pendiente') !== false);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<link rel="icon" type="image/png" href="/favicon/favicon-96x96.png" sizes="96x96">
<link rel="icon" type="image/x-icon" href="/favicon/favicon.ico">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Remisión <?= htmlspecialchars($folio_orden) ?> — APEX GLASS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --amber: #F5A623; --bg: #F0F1F3; --surface: #FFFFFF; --border: #E2E5EB;
  --text-1: #0F1117; --text-2: #7A7E8E; --text-3: #C4C8D2;
}
body { font-family: 'Outfit', -apple-system, sans-serif; background: var(--bg); min-height: 100dvh; color: var(--text-1); }

.header {
  background: var(--surface); border-bottom: 1px solid var(--border);
  padding: 0 32px; height: 58px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 100;
}
.header-logo { font-family: 'Syncopate', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: 4px; color: var(--text-1); }
.btn-back {
  font-size: 9.5px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
  color: var(--text-2); background: none; border: 1px solid var(--border);
  border-radius: 2px; padding: 5px 10px; cursor: pointer;
  text-decoration: none; display: flex; align-items: center; gap: 6px;
  transition: color .15s, border-color .15s;
}
.btn-back:hover { color: var(--text-1); border-color: #B0B5C0; }
.btn-imprimir {
  font-size: 9.5px; font-weight: 600; letter-spacing: 2px; text-transform: uppercase;
  color: #0F1117; background: var(--amber); border: none;
  border-radius: 2px; padding: 6px 14px; cursor: pointer;
  display: flex; align-items: center; gap: 6px; transition: filter .15s;
}
.btn-imprimir:hover { filter: brightness(1.07); }
.header-right { display: flex; align-items: center; gap: 10px; }

@media (max-width: 639px) { .header { padding: 0 18px; gap: 10px; } }

/* ── Documento (misma maqueta que app/imprimir_salida.php, ámbito reducido a solo lectura) ── */
.doc { font-family: Arial, sans-serif; font-size: 11px; color: #000; background: #fff; max-width: 960px; margin: 24px auto 60px; padding: 20px 28px; border: 1px solid var(--border); border-radius: 4px; }

.header-wrap { display: flex; border: 2px solid #000; margin-bottom: 0; }
.header-logo-box { width: 90px; min-width: 90px; border-right: 2px solid #000; display: flex; align-items: center; justify-content: center; padding: 8px; }
.header-logo-box img { max-width: 72px; max-height: 72px; object-fit: contain; }
.header-center { flex: 1; text-align: center; border-right: 2px solid #000; padding: 8px; display: flex; flex-direction: column; justify-content: center; }
.empresa  { font-family: 'Syncopate', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: 1px; }
.doc-tipo { font-size: 14px; font-weight: 700; margin-top: 2px; text-transform: uppercase; }
.doc-sub  { font-size: 10px; color: #555; margin-top: 1px; }
.header-right-box { width: 180px; min-width: 180px; padding: 8px 10px; display: flex; flex-direction: column; gap: 4px; justify-content: center; }
.hdr-field { font-size: 10px; }
.hdr-field span { font-weight: 700; }

.info-table { width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; }
.info-table td { border: 1px solid #000; padding: 5px 8px; font-size: 11px; }
.info-table .lbl { font-weight: 700; background: #f3f4f6; white-space: nowrap; width: 120px; }
.info-table .val { font-weight: 600; }
.epago-badge { display: inline-block; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; }
.epago-en_proceso   { background: #fef3c7; color: #92400e; }
.epago-pago_entrega { background: #dbeafe; color: #1e40af; }
.epago-pagado       { background: #dcfce7; color: #15803d; }

.badge-parcial { display: inline-block; background: #fff7ed; color: #c2410c; font-size: 10px; font-weight: 700; padding: 3px 10px; border-radius: 4px; border: 1px solid #fed7aa; margin-left: 8px; }

.partidas-table { width: 100%; border-collapse: collapse; margin-top: 14px; }
.partidas-table th { border: 1px solid #000; padding: 6px 5px; background: #1a1a2e; color: #fff; font-size: 10px; font-weight: 700; text-align: center; text-transform: uppercase; letter-spacing: .3px; }
.partidas-table td { border: 1px solid #999; padding: 6px 5px; font-size: 11px; vertical-align: middle; text-align: center; }
.partidas-table td.left { text-align: left; }
.partidas-table tr:nth-child(even) { background: #f9fafb; }
.cristal-cell { font-weight: 600; text-align: left; }

.entrega-col { text-align: center; min-width: 82px; font-size: 10px; }
.ent-pendiente { color: #94a3b8; font-style: italic; font-weight: 600; }
.ent-fecha     { color: #15803d; font-weight: 700; }
.ent-parcial   { color: #c2410c; font-weight: 600; }

.totales-row { margin-top: 12px; display: flex; justify-content: space-between; align-items: flex-start; }
.total-box   { border: 2px solid #1a1a2e; border-radius: 4px; padding: 8px 20px; font-size: 13px; font-weight: 800; }

.condiciones { margin-top: 14px; font-size: 9px; color: #555; border-top: 1px solid #e2e8f0; padding-top: 8px; line-height: 1.5; }
.pie         { margin-top: 8px; font-size: 9px; color: #6b7280; border-top: 1px solid #e2e8f0; padding-top: 6px; }

@media print {
    .no-print { display: none !important; }
    body { background: #fff; }
    .doc { border: none; margin: 0; box-shadow: none; }
    @page { margin: 12mm 10mm; size: letter portrait; }
}
@media (max-width: 479px) {
    .doc { padding: 14px; margin: 14px auto 40px; }
    .header-wrap { flex-direction: column; }
    .header-logo-box, .header-center, .header-right-box { border-right: none; border-bottom: 2px solid #000; width: auto; }
}
</style>
</head>
<body>

<div class="header no-print">
  <span class="header-logo">APEX GLASS</span>
  <div class="header-right">
    <a class="btn-back" href="orden.php?folio=<?= urlencode($folio_orden) ?>">&#8592; Volver a la orden</a>
    <button class="btn-imprimir" onclick="window.print()">Imprimir / Guardar PDF</button>
  </div>
</div>

<div class="doc">
  <div class="header-wrap">
    <div class="header-logo-box"><img src="../logoAG.png" alt="APEX GLASS"></div>
    <div class="header-center">
      <div class="empresa">TEMPLADORA NORESTE, S. A. DE C. V.</div>
      <div class="doc-tipo">Remisión / Orden de Salida <?= $pendientes ? '<span class="badge-parcial">ENTREGA PARCIAL</span>' : '' ?></div>
      <div class="doc-sub">Parque Industrial MARFER, Carr. Monterrey-Saltillo km 65, Av. De la Industria #214, Santa Catarina, N.L.</div>
    </div>
    <div class="header-right-box">
      <div style="font-size:15px;font-weight:900;color:#1a1a2e;letter-spacing:.5px;border-bottom:2px solid #1a1a2e;padding-bottom:4px;margin-bottom:6px">
        ORDEN: <?= htmlspecialchars($folio_orden) ?>
      </div>
      <div class="hdr-field"><span>Fecha:</span> <?= $fecha_hoy ?></div>
      <div class="hdr-field"><span>Entrega:</span> <?= $fecha_ent ?></div>
    </div>
  </div>

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
      <td class="val"><?= $tipo_label ?></td>
      <td class="lbl">Localidad:</td>
      <td class="val"><?= htmlspecialchars($localidad) ?></td>
    </tr>
    <tr>
      <td class="lbl">Condición pago:</td>
      <td class="val"><?= htmlspecialchars($cond_pago) ?></td>
      <td class="lbl">Estatus pago:</td>
      <td class="val"><span class="epago-badge epago-<?= $epago_display ?>"><?= $epago_label ?></span></td>
    </tr>
  </table>

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
        <th style="width:90px">Entrega</th>
      </tr>
    </thead>
    <tbody><?= $tbody_html ?></tbody>
  </table>

  <div class="totales-row">
    <div class="total-box"><?= $totales_txt ?></div>
  </div>

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
