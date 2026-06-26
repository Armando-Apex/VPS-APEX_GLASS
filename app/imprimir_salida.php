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
$parts = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$orden = null;
if ($c['orden_id']) {
    $stmt3 = $db->prepare('SELECT id, folio, fecha_entrega, tipo_entrega, estado, fecha_entrega_chofer FROM ordenes WHERE id = ?');
    $stmt3->execute([$c['orden_id']]);
    $orden = $stmt3->fetch(PDO::FETCH_ASSOC);
}

$parts_json = json_encode($parts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$cliente     = $c['cliente_nombre'] ?: '—';
$folio_cot   = $c['folio'] ?: '—';
$folio_orden = $orden['folio'] ?? '—';
$fecha_hoy   = date('d/m/Y');
$fecha_ent   = $orden && $orden['fecha_entrega'] ? date('d/m/Y', strtotime($orden['fecha_entrega'])) : '—';
$asesor      = $c['asesor_nombre'] ?? '—';
$proyecto    = $c['proyecto'] ?: '—';
$tipo_ent    = ($c['tipo_entrega'] ?? $orden['tipo_entrega'] ?? '') === 'domicilio' ? 'chofer' : 'recoleccion';
$tipo_label  = $tipo_ent === 'chofer' ? 'Domicilio / Ruta' : 'Recolección en planta';
$localidad   = strtolower($c['localidad'] ?? '') === 'foraneo' ? 'Foráneo — ' . ($c['ciudad_destino'] ?? '') : 'Local';
$cond_pago   = $c['condicion_pago'] ?? '—';
$epago_display = $pago_completo ? 'pagado' : $epago;
$epago_label   = ['pendiente'=>'Pendiente','en_proceso'=>'En proceso','pago_entrega'=>'Pago a la entrega','pagado'=>'Pagado'][$epago_display] ?? $epago_display;

$orden_id_php      = (int)($orden['id'] ?? 0);
$cotizacion_id_php = $id;
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

/* ── Barra no-print ── */
.print-bar {
  background: #1a1a2e; padding: 10px 24px;
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px;
}
.print-bar span { color: #94a3b8; font-size: 12px; }
.btn-print  { background: #f5a623; color: #000; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; }
.btn-cancel { background: #334155; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; }

/* ── Selector de piezas ── */
#selector-view { padding: 20px 28px; max-width: 780px; margin: 0 auto; }
.sel-titulo { font-size: 16px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
.sel-sub    { font-size: 12px; color: #64748b; margin-bottom: 20px; }

.partida-bloque { border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 14px; overflow: hidden; }
.partida-header {
  background: #f8fafc; padding: 10px 16px;
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 1px solid #e2e8f0;
}
.partida-header .ph-left { font-weight: 700; font-size: 13px; color: #1e293b; }
.partida-header .ph-right { font-size: 11px; color: #64748b; }

.piezas-grid {
  display: flex; flex-wrap: wrap; gap: 8px;
  padding: 12px 16px;
}
.pieza-chip {
  display: flex; align-items: center; gap: 6px;
  border: 1.5px solid #cbd5e1; border-radius: 6px;
  padding: 8px 12px; cursor: pointer; user-select: none;
  font-size: 12px; font-weight: 600; color: #334155;
  transition: background .15s, border-color .15s, color .15s;
  min-height: 36px;
}
.pieza-chip:not(.entregado):not(.en-proceso):hover { background: #f0fdf4; border-color: #86efac; }
.pieza-chip:focus-within { outline: 2px solid #3b82f6; outline-offset: 1px; }
.pieza-chip input[type=checkbox] { margin: 0; width: 14px; height: 14px; cursor: pointer; }
.pieza-chip.checked { background: #ecfdf5; border-color: #16a34a; color: #15803d; }
.pieza-chip.checked:hover { background: #dcfce7; }
.pieza-chip.entregado { background: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; cursor: default; opacity: .7; }
.pieza-chip.en-proceso { background: #fff7ed; border-color: #fed7aa; color: #9a3412; cursor: default; }

.leyenda { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 16px; }
.leyenda-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #475569; }
.leyenda-dot { width: 10px; height: 10px; border-radius: 3px; border: 1.5px solid; }
.leyenda-dot.l-term { background: #ecfdf5; border-color: #16a34a; }
.leyenda-dot.l-ent  { background: #f1f5f9; border-color: #e2e8f0; }
.leyenda-dot.l-proc { background: #fff7ed; border-color: #fed7aa; }

.partida-header .ph-actions { display: flex; gap: 8px; align-items: center; }
.btn-tod { background: none; border: 1px solid #cbd5e1; color: #334155; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 4px; cursor: pointer; transition: background .12s; }
.btn-tod:hover { background: #f1f5f9; }

.sel-footer { margin-top: 20px; display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap; }
.sel-footer-left { display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap; flex: 1; }
.sel-counter { font-size: 13px; color: #334155; }
.sel-counter strong { color: #16a34a; font-size: 16px; }

.campo-fecha { display: flex; flex-direction: column; gap: 4px; }
.campo-fecha label { font-size: 11px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .5px; }
.campo-fecha input { border: 1.5px solid #cbd5e1; border-radius: 6px; padding: 7px 10px; font-size: 13px; min-height: 44px; }
.campo-fecha input:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }

.btn-confirmar {
  background: #16a34a; color: white; border: none;
  padding: 10px 28px; border-radius: 8px; font-size: 14px; font-weight: 700;
  cursor: pointer; min-height: 44px; min-width: 200px;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: background .15s;
}
.btn-confirmar:not(:disabled):hover { background: #15803d; }
.btn-confirmar:disabled { background: #94a3b8; cursor: not-allowed; }

.spinner {
  width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.4);
  border-top-color: #fff; border-radius: 50%;
  animation: spin .6s linear infinite; display: none;
}
@keyframes spin { to { transform: rotate(360deg); } }
.btn-confirmar.loading .spinner { display: block; }
.btn-confirmar.loading .btn-label { opacity: .8; }

@media (max-width: 600px) {
  .sel-footer { flex-direction: column; align-items: stretch; }
  .btn-confirmar { width: 100%; }
}

.no-piezas {
  padding: 32px 16px; text-align: center;
}
.no-piezas svg { margin: 0 auto 10px; display: block; opacity: .4; }
.no-piezas p { color: #64748b; font-size: 13px; line-height: 1.5; }

/* ── Toggle tipo entrega ── */
.tipo-toggle-wrap { margin-bottom: 16px; }
.tipo-toggle-lbl { font-size: 11px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
.tipo-toggle { display: inline-flex; background: #f1f5f9; border: 1.5px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.tipo-btn {
  padding: 8px 18px; font-size: 13px; font-weight: 600; cursor: pointer;
  border: none; background: transparent; color: #64748b;
  transition: background .15s, color .15s; min-height: 40px;
  display: flex; align-items: center; gap: 6px;
}
.tipo-btn.active { background: #1a1a2e; color: #fff; }
.tipo-btn:not(.active):hover { background: #e2e8f0; color: #334155; }

/* ── Documento imprimible ── */
.doc { padding: 20px 28px; max-width: 960px; margin: 0 auto; display: none; }

.header-wrap { display: flex; border: 2px solid #000; margin-bottom: 0; }
.header-logo  { width: 90px; min-width: 90px; border-right: 2px solid #000; display: flex; align-items: center; justify-content: center; padding: 8px; }
.header-logo img { max-width: 72px; max-height: 72px; object-fit: contain; }
.header-center { flex: 1; text-align: center; border-right: 2px solid #000; padding: 8px; display: flex; flex-direction: column; justify-content: center; }
.empresa  { font-family: 'Syncopate', sans-serif; font-size: 13px; font-weight: 700; letter-spacing: 1px; }
.doc-tipo { font-size: 14px; font-weight: 700; margin-top: 2px; text-transform: uppercase; }
.doc-sub  { font-size: 10px; color: #555; margin-top: 1px; }
.header-right { width: 180px; min-width: 180px; padding: 8px 10px; display: flex; flex-direction: column; gap: 4px; justify-content: center; }
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

.totales-row { margin-top: 12px; display: flex; justify-content: space-between; align-items: flex-start; }
.total-box { border: 2px solid #1a1a2e; border-radius: 4px; padding: 8px 20px; font-size: 13px; font-weight: 800; }

.resumen-mat { margin-top: 12px; border: 1.5px solid #1a1a2e; border-radius: 4px; }
.resumen-mat-title { background: #1a1a2e; color: white; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 5px 12px; }
.resumen-mat table { width: 100%; border-collapse: collapse; }
.resumen-mat th { background: #f3f4f6; font-size: 10px; font-weight: 700; padding: 5px 10px; text-align: left; border-bottom: 1px solid #d1d5db; }
.resumen-mat th:not(:first-child) { text-align: center; }
.resumen-mat td { font-size: 11px; padding: 5px 10px; border-bottom: 1px solid #e5e7eb; }
.resumen-mat td:not(:first-child) { text-align: center; font-weight: 600; }
.resumen-mat tr:last-child td { border-bottom: none; }

.logistica { margin-top: 18px; border: 1.5px solid #000; border-radius: 4px; }
.log-title { background: #1a1a2e; color: white; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; padding: 5px 12px; }
.log-grid { display: grid; grid-template-columns: repeat(4, 1fr); }
.log-field { padding: 8px 12px; border-right: 1px solid #ddd; }
.log-field:last-child { border-right: none; }
.log-lbl { font-size: 9px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 14px; }
.log-line { border-bottom: 1px solid #000; margin-top: 4px; }

.firmas { margin-top: 28px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; }
.firma-box { text-align: center; }
.firma-linea { border-top: 1.5px solid #000; margin-bottom: 5px; margin-top: 36px; }
.firma-lbl { font-size: 10px; color: #374151; }

.condiciones { margin-top: 14px; font-size: 9px; color: #555; border-top: 1px solid #e2e8f0; padding-top: 8px; line-height: 1.5; }
.pie { margin-top: 8px; font-size: 9px; color: #6b7280; border-top: 1px solid #e2e8f0; padding-top: 6px; }

@media print {
    .no-print { display: none !important; }
    #selector-view { display: none !important; }
    .doc { display: block !important; padding: 0; }
    body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    @page { margin: 12mm 10mm; size: letter portrait; }
}
</style>
</head>
<body>

<!-- ── Barra superior ── -->
<div class="print-bar no-print" id="topbar">
  <span>Remisión / Orden de Salida — <?= htmlspecialchars($folio_cot) ?></span>
  <div style="display:flex;gap:10px">
    <button class="btn-cancel" id="btnVolver" style="display:none" onclick="volverAlSelector()">&#8592; Volver</button>
    <button class="btn-print"  id="btnImprimir" style="display:none" onclick="window.print()">&#128438; Imprimir / Guardar PDF</button>
  </div>
</div>

<!-- ── Selector de piezas (pantalla pre-impresión) ── -->
<div id="selector-view" class="no-print">
  <div class="sel-titulo">Registrar salida — <?= htmlspecialchars($folio_orden) ?></div>
  <div class="sel-sub">Selecciona las piezas que salen en esta entrega. Solo las <strong>terminadas</strong> son seleccionables.</div>

  <div class="tipo-toggle-wrap">
    <div class="tipo-toggle-lbl">Tipo de entrega</div>
    <div class="tipo-toggle" role="group" aria-label="Tipo de entrega">
      <button class="tipo-btn <?= $tipo_ent === 'recoleccion' ? 'active' : '' ?>" id="btnTipoRecoleccion" onclick="setTipo('recoleccion')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Recolección en planta
      </button>
      <button class="tipo-btn <?= $tipo_ent === 'chofer' ? 'active' : '' ?>" id="btnTipoChofer" onclick="setTipo('chofer')">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        Chofer / Domicilio
      </button>
    </div>
  </div>

  <div class="leyenda">
    <div class="leyenda-item"><div class="leyenda-dot l-term"></div> Terminada (seleccionable)</div>
    <div class="leyenda-item"><div class="leyenda-dot l-ent"></div> Ya entregada</div>
    <div class="leyenda-item"><div class="leyenda-dot l-proc"></div> En producción</div>
  </div>

  <div id="partidas-container">
    <div style="padding:40px;text-align:center;color:#94a3b8">
      <div style="width:32px;height:32px;border:3px solid #e2e8f0;border-top-color:#64748b;border-radius:50%;animation:spin .6s linear infinite;margin:0 auto 12px"></div>
      Cargando piezas...
    </div>
  </div>

  <div class="sel-footer">
    <div class="sel-footer-left">
      <div class="sel-counter">Seleccionadas: <strong id="cnt-sel" aria-live="polite" aria-atomic="true">0</strong> piezas</div>
      <div class="campo-fecha" id="campo-fecha-chofer" style="<?= $tipo_ent === 'chofer' ? '' : 'display:none' ?>">
        <label for="fecha-chofer">Fecha de entrega por chofer</label>
        <input type="date" id="fecha-chofer" value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <button class="btn-confirmar" id="btnConfirmar" disabled onclick="confirmarSalida()">
      <div class="spinner" id="spinnerConfirmar"></div>
      <span class="btn-label">Confirmar e imprimir</span>
    </button>
  </div>
</div>

<!-- ── Documento imprimible ── -->
<div class="doc" id="doc-print">

  <div class="header-wrap">
    <div class="header-logo">
      <img src="../logoAG.png" alt="APEX GLASS">
    </div>
    <div class="header-center">
      <div class="empresa">TEMPLADORA NORESTE, S. A. DE C. V.</div>
      <div class="doc-tipo">Remisión / Orden de Salida <span id="doc-badge-parcial"></span></div>
      <div class="doc-sub">Parque Industrial MARFER, Carr. Monterrey-Saltillo km 65, Av. De la Industria #214, Santa Catarina, N.L.</div>
    </div>
    <div class="header-right">
      <div style="font-size:15px;font-weight:900;color:#1a1a2e;letter-spacing:.5px;border-bottom:2px solid #1a1a2e;padding-bottom:4px;margin-bottom:6px">
        ORDEN: <?= htmlspecialchars($folio_orden) ?>
      </div>
      <div class="hdr-field"><span>Fecha:</span> <?= $fecha_hoy ?></div>
      <div class="hdr-field"><span>Entrega:</span> <span id="doc-fecha-entrega"><?= $fecha_ent ?></span></div>
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
      <td class="val" id="doc-tipo-entrega-val"><?= $tipo_label ?></td>
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

  <table class="partidas-table" id="doc-tabla-partidas">
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
    <tbody id="doc-tbody"></tbody>
  </table>

  <div class="totales-row">
    <div class="total-box" id="doc-totales">TOTAL PIEZAS: — &nbsp;|&nbsp; TOTAL M²: —</div>
  </div>

  <div id="doc-resumen-mat"></div>

  <div class="logistica">
    <div class="log-title">Datos de entrega / logística</div>
    <div class="log-grid">
      <div class="log-field"><div class="log-lbl">Chofer</div><div class="log-line"></div></div>
      <div class="log-field"><div class="log-lbl">Vehículo / Placas</div><div class="log-line"></div></div>
      <div class="log-field"><div class="log-lbl">Fecha y hora de salida</div><div class="log-line"></div></div>
      <div class="log-field"><div class="log-lbl">Dirección de entrega</div><div class="log-line"></div></div>
    </div>
  </div>

  <div class="firmas">
    <div class="firma-box"><div class="firma-linea"></div><div class="firma-lbl">Entregó — Nombre y firma</div></div>
    <div class="firma-box"><div class="firma-linea"></div><div class="firma-lbl">Recibió — Nombre y firma</div></div>
    <div class="firma-box"><div class="firma-linea"></div><div class="firma-lbl">Revisó calidad — Nombre y firma</div></div>
  </div>

  <div class="condiciones">
    + Una vez que la mercancía es recibida mediante firma de conformidad por parte del cliente y/o ha salido de nuestras instalaciones, Templadora Noreste S.A. de C.V. no se hace responsable por daños ocasionados durante el transporte o instalación.<br>
    + Esta remisión ampara únicamente las partidas descritas en el presente documento. Cualquier reclamación deberá hacerse dentro de las 24 horas siguientes a la recepción.
  </div>
  <div class="pie">
    Templadora Noreste, S.A. de C.V. — Tel: 81 1180 5078 — Parque Industrial MARFER, Carr. Monterrey-Saltillo km 65, Santa Catarina, N.L., C.P. 66367
  </div>
</div>

<script>
var ORDEN_ID       = <?= $orden_id_php ?>;
var COTIZACION_ID  = <?= $cotizacion_id_php ?>;
var TIPO_ENTREGA   = '<?= $tipo_ent ?>';  // mutable — puede cambiarse con setTipo()
var PARTS          = <?= $parts_json ?>;
var ORDEN_ESTADO   = '<?= htmlspecialchars($orden['estado'] ?? '') ?>';
var FECHA_CHOFER   = '<?= htmlspecialchars($orden['fecha_entrega_chofer'] ?? '') ?>';

function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

var todasPiezas   = [];
var seleccionadas = {};

// ── Cargar piezas al iniciar ──────────────────────────────────────────────────
(function init() {
  if (!ORDEN_ID) {
    document.getElementById('partidas-container').innerHTML =
      '<div class="no-piezas">Esta cotización no tiene una orden de producción vinculada.</div>';
    return;
  }
  fetch('../api/salidas.php?accion=piezas_terminadas&orden_id=' + ORDEN_ID)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) throw new Error(data.error || 'Error');
      todasPiezas = data.piezas;
      // Si la orden ya está entregada y no quedan piezas terminadas → mostrar doc directamente
      if (data.terminadas === 0 && data.entregadas > 0 && ORDEN_ESTADO === 'entregada') {
        var allIds = todasPiezas.map(function(p) { return p.id; });
        var fc = FECHA_CHOFER || null;
        construirDocumento(allIds, fc, false, allIds.length);
        return;
      }
      renderSelector();
    })
    .catch(function(e) {
      document.getElementById('partidas-container').innerHTML =
        '<div class="no-piezas">Error al cargar piezas: ' + e.message + '</div>';
    });
})();

// ── Renderizar selector de piezas ─────────────────────────────────────────────
function renderSelector() {
  seleccionadas = {};   // reset en cada render para no arrastrar estado previo
  var grupos = {};
  var totalPorPartida = {};
  todasPiezas.forEach(function(p) {
    var k = p.partida;
    if (!grupos[k]) grupos[k] = [];
    grupos[k].push(p);
    totalPorPartida[k] = (totalPorPartida[k] || 0) + 1;
  });

  var html = '';
  var hayTerminadas = false;

  Object.keys(grupos).sort(function(a, b) { return a - b; }).forEach(function(numPart) {
    var piezas = grupos[numPart];
    var part   = PARTS.find(function(pt) { return pt.num_partida == numPart; }) || {};
    var termCnt = piezas.filter(function(p) { return p.estatus === 'terminado'; }).length;
    var entCnt  = piezas.filter(function(p) { return p.estatus === 'entregado'; }).length;

    html += '<div class="partida-bloque">';
    html += '<div class="partida-header">';
    html += '<div class="ph-left">Partida ' + numPart + ' &nbsp;—&nbsp; ' + esc(part.cristal_nombre || piezas[0].cristal_corto || '—');
    if (part.ancho) html += ' &nbsp;' + esc(part.ancho) + ' × ' + esc(part.alto) + ' mm';
    html += '</div>';
    html += '<div class="ph-actions">';
    html += '<span style="font-size:11px;color:#64748b">' + termCnt + ' terminada(s) &nbsp;·&nbsp; ' + entCnt + ' entregada(s)</span>';
    if (termCnt > 0) {
      html += '<button class="btn-tod" onclick="togglePartida(\'' + numPart + '\', true)" title="Seleccionar todas las terminadas de esta partida">Todo</button>';
      html += '<button class="btn-tod" onclick="togglePartida(\'' + numPart + '\', false)" title="Deseleccionar todas de esta partida">Ninguno</button>';
    }
    html += '</div>';
    html += '</div>';
    html += '<div class="piezas-grid">';

    piezas.forEach(function(p) {
      var clase = '';
      var disabled = true;
      var checked  = '';

      if (p.estatus === 'terminado') {
        clase    = 'checked';
        disabled = false;
        checked  = 'checked';
        seleccionadas[p.id] = true;
        hayTerminadas = true;
      } else if (p.estatus === 'entregado') {
        clase = 'entregado';
      } else {
        clase = 'en-proceso';
      }

      var titulo = p.estatus === 'entregado' ? 'Ya entregada' : (p.estatus !== 'terminado' ? 'En producción (' + p.estatus + ')' : '');

      html += '<label class="pieza-chip ' + clase + '" title="' + titulo + '" id="chip-' + p.id + '">';
      html += '<input type="checkbox" ' + (disabled ? 'disabled' : '') + ' ' + checked + ' ';
      html += 'onchange="togglePieza(' + p.id + ', this.checked)" value="' + p.id + '">';
      html += 'P' + p.pieza_num + '/' + p.pieza_total;
      html += '</label>';
    });

    if (termCnt === 0) {
      html += '<span style="font-size:12px;color:#94a3b8;padding:8px">Sin piezas terminadas en esta partida</span>';
    }

    html += '</div></div>';
  });

  if (!hayTerminadas) {
    html = '<div class="no-piezas">No hay piezas en estatus <strong>terminado</strong> disponibles para entregar.</div>';
  }

  document.getElementById('partidas-container').innerHTML = html;
  actualizarContador();
}

function togglePieza(id, activo) {
  seleccionadas[id] = activo;
  var chip = document.getElementById('chip-' + id);
  if (chip) chip.className = 'pieza-chip ' + (activo ? 'checked' : '');
  actualizarContador();
}

function setTipo(tipo) {
  TIPO_ENTREGA = tipo;
  document.getElementById('btnTipoRecoleccion').className = 'tipo-btn' + (tipo === 'recoleccion' ? ' active' : '');
  document.getElementById('btnTipoChofer').className      = 'tipo-btn' + (tipo === 'chofer'      ? ' active' : '');
  var campoFecha = document.getElementById('campo-fecha-chofer');
  campoFecha.style.display = (tipo === 'chofer') ? '' : 'none';
  // Al cambiar a recoleccion, limpiar fecha para que la validación no bloquee
  if (tipo !== 'chofer') document.getElementById('fecha-chofer').value = '';
  // Actualizar label de tipo en el documento imprimible
  var docTipo = document.getElementById('doc-tipo-entrega-val');
  if (docTipo) docTipo.textContent = (tipo === 'chofer') ? 'Domicilio / Ruta' : 'Recolección en planta';
}

function togglePartida(numPart, activar) {
  todasPiezas.forEach(function(p) {
    if (p.partida == numPart && p.estatus === 'terminado') {
      seleccionadas[p.id] = activar;
      var chip = document.getElementById('chip-' + p.id);
      if (chip) {
        chip.className = 'pieza-chip ' + (activar ? 'checked' : '');
        chip.querySelector('input').checked = activar;
      }
    }
  });
  actualizarContador();
}

function actualizarContador() {
  var cnt = Object.values(seleccionadas).filter(Boolean).length;
  document.getElementById('cnt-sel').textContent = cnt;
  document.getElementById('btnConfirmar').disabled = cnt === 0;
}

// ── Confirmar salida ──────────────────────────────────────────────────────────
function confirmarSalida() {
  var ids = Object.keys(seleccionadas).filter(function(k) { return seleccionadas[k]; }).map(Number);
  if (!ids.length) return;

  var fechaChofer = null;
  var fcInput = document.getElementById('fecha-chofer');
  if (fcInput) {
    if (!fcInput.value) { alert('Indica la fecha de entrega por chofer.'); fcInput.focus(); return; }
    fechaChofer = fcInput.value;
  }

  var btn = document.getElementById('btnConfirmar');
  btn.disabled = true;
  btn.classList.add('loading');
  document.getElementById('spinnerConfirmar').style.display = 'block';
  btn.querySelector('.btn-label').textContent = 'Registrando...';

  fetch('../api/salidas.php?accion=registrar_salida', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      orden_id:             ORDEN_ID,
      cotizacion_id:        COTIZACION_ID,
      pieza_ids:            ids,
      tipo:                 TIPO_ENTREGA,
      fecha_entrega_chofer: fechaChofer
    })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (!data.ok) throw new Error(data.error || 'Error');
    // Usar pieza_ids confirmados por la API (pueden ser subconjunto si hubo TOCTOU)
    construirDocumento(data.pieza_ids || ids, fechaChofer, data.es_parcial, (data.pieza_ids || ids).length);
  })
  .catch(function(e) {
    alert('Error al registrar: ' + e.message);
    btn.disabled = false;
    btn.classList.remove('loading');
    document.getElementById('spinnerConfirmar').style.display = 'none';
    btn.querySelector('.btn-label').textContent = 'Confirmar e imprimir';
  });
}

// ── Construir documento imprimible ────────────────────────────────────────────
function construirDocumento(idsSeleccionados, fechaChofer, esParcial, piezasCount) {
  // Badge parcial
  var badge = document.getElementById('doc-badge-parcial');
  if (esParcial) {
    badge.innerHTML = '<span class="badge-parcial">ENTREGA PARCIAL</span>';
  }

  // Fecha en header
  if (fechaChofer) {
    var partes = fechaChofer.split('-');
    var meses  = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    document.getElementById('doc-fecha-entrega').textContent =
      partes[2] + '/' + (parseInt(partes[1]) < 10 ? '0' : '') + parseInt(partes[1]) + '/' + partes[0];
  }

  // Agrupar piezas seleccionadas por partida
  var selSet = {};
  idsSeleccionados.forEach(function(id) { selSet[id] = true; });

  var grupos = {};
  todasPiezas.filter(function(p) { return selSet[p.id]; }).forEach(function(p) {
    if (!grupos[p.partida]) grupos[p.partida] = [];
    grupos[p.partida].push(p);
  });

  var tbody   = document.getElementById('doc-tbody');
  var filas   = '';
  var totalPz = 0;
  var totalM2 = 0;
  var resMat  = {};

  Object.keys(grupos).sort(function(a, b) { return a - b; }).forEach(function(numPart) {
    var piezasGrupo = grupos[numPart];
    var part = PARTS.find(function(pt) { return pt.num_partida == numPart; }) || {};
    var cant = piezasGrupo.length;
    var m2u  = piezasGrupo[0] ? (piezasGrupo[0].m2 || 0) : 0;
    var m2t  = parseFloat((m2u * cant).toFixed(4));
    totalPz += cant;
    totalM2 += m2t;

    var specs = [];
    if (part.cpb && part.cpb.toUpperCase() !== 'NO') specs.push('CPB: ' + part.cpb);
    if (part.detalles) specs.push(part.detalles);
    if (part.resaques > 0) specs.push('Res: ' + part.resaques);
    if (part.taladros_pasados > 0) specs.push('TP: ' + part.taladros_pasados);
    if (part.taladros_avellanados > 0) specs.push('TA: ' + part.taladros_avellanados);
    specs.push(part.requiere_templado ? 'Templado' : 'No Templado');

    var mat = part.cristal_nombre || piezasGrupo[0].cristal_corto || '—';
    if (!resMat[mat]) resMat[mat] = { pzas: 0, m2: 0 };
    resMat[mat].pzas += cant;
    resMat[mat].m2   += m2t;

    var totalPartida = totalPorPartida[numPart] || cant;
    var labelCant = cant < totalPartida ? cant + ' de ' + totalPartida : String(cant);

    filas += '<tr>';
    filas += '<td style="font-weight:700;color:#1d4ed8">' + numPart + '</td>';
    filas += '<td class="cristal-cell">' + esc(mat) + '</td>';
    filas += '<td>' + esc(piezasGrupo[0] ? piezasGrupo[0].ancho_mm : (part.ancho || '—')) + '</td>';
    filas += '<td>' + esc(piezasGrupo[0] ? piezasGrupo[0].alto_mm  : (part.alto  || '—')) + '</td>';
    filas += '<td>' + labelCant + '</td>';
    filas += '<td>' + m2t.toFixed(4) + '</td>';
    filas += '<td class="left">' + esc(specs.join(' · ') || '—') + '</td>';
    filas += '<td class="left">' + esc(part.comentarios_etiqueta || '') + '</td>';
    filas += '</tr>';
  });

  tbody.innerHTML = filas;
  document.getElementById('doc-totales').textContent =
    'TOTAL PIEZAS: ' + totalPz + '  |  TOTAL M²: ' + totalM2.toFixed(4);

  // Resumen de material
  var matKeys = Object.keys(resMat);
  var rhtml   = '';
  if (matKeys.length > 1) {
    rhtml  = '<div class="resumen-mat"><div class="resumen-mat-title">Resumen de material</div>';
    rhtml += '<table><thead><tr><th>Material</th><th style="width:90px">Piezas</th><th style="width:110px">M² total</th></tr></thead><tbody>';
    matKeys.forEach(function(m) {
      rhtml += '<tr><td>' + esc(m) + '</td><td>' + resMat[m].pzas + '</td><td>' + resMat[m].m2.toFixed(4) + '</td></tr>';
    });
    rhtml += '</tbody></table></div>';
  }
  document.getElementById('doc-resumen-mat').innerHTML = rhtml;

  // Mostrar documento e imprimir
  document.getElementById('selector-view').style.display = 'none';
  document.getElementById('doc-print').style.display     = 'block';
  document.getElementById('btnImprimir').style.display   = 'inline-block';
  document.getElementById('btnVolver').style.display     = 'inline-block';
  document.getElementById('btnConfirmar') && (document.getElementById('btnConfirmar').style.display = 'none');

  setTimeout(function() { window.print(); }, 400);
}

function volverAlSelector() {
  document.getElementById('doc-print').style.display   = 'none';
  document.getElementById('btnImprimir').style.display = 'none';
  document.getElementById('btnVolver').style.display   = 'none';
  // Re-cargar piezas desde la API para reflejar los nuevos estatus (entregado)
  document.getElementById('selector-view').style.display = 'block';
  document.getElementById('partidas-container').innerHTML = '<div style="padding:40px;text-align:center;color:#94a3b8">Actualizando...</div>';
  fetch('../api/salidas.php?accion=piezas_terminadas&orden_id=' + ORDEN_ID)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) throw new Error(data.error || 'Error');
      todasPiezas = data.piezas;
      renderSelector();
    })
    .catch(function(e) {
      document.getElementById('partidas-container').innerHTML =
        '<div class="no-piezas">Error al actualizar: ' + e.message + '</div>';
    });
}
</script>
</body>
</html>
