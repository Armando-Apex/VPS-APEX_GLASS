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
$ya_entregada      = ($orden && $orden['estado'] === 'entregada');
$fecha_chofer_php  = (!empty($orden['fecha_entrega_chofer'])) ? date('Y-m-d', strtotime($orden['fecha_entrega_chofer'])) : '';

// ── Salidas registradas + mapa de fechas de entrega por pieza ────────────────
$salidas_previas = [];
$fechas_entrega  = []; // pieza_id (int) => 'Y-m-d'
$tiene_salidas   = false;

if ($orden_id_php) {
    $stmtSp = $db->prepare('
        SELECT os.id, os.tipo, os.piezas_count, os.total_piezas, os.es_parcial,
               os.fecha_entrega_chofer, os.created_at,
               GROUP_CONCAT(osp.pieza_id ORDER BY osp.pieza_id) AS pieza_ids_str
        FROM orden_salidas os
        LEFT JOIN orden_salida_piezas osp ON osp.salida_id = os.id
        WHERE os.orden_id = ? AND os.cotizacion_id = ?
        GROUP BY os.id ORDER BY os.created_at ASC
    ');
    $stmtSp->execute([$orden_id_php, $cotizacion_id_php]);
    foreach ($stmtSp->fetchAll(PDO::FETCH_ASSOC) as $sp) {
        $sp['pieza_ids'] = $sp['pieza_ids_str'] ? array_map('intval', explode(',', $sp['pieza_ids_str'])) : [];
        unset($sp['pieza_ids_str']);
        $fecha_real = !empty($sp['fecha_entrega_chofer'])
            ? date('Y-m-d', strtotime($sp['fecha_entrega_chofer']))
            : date('Y-m-d', strtotime($sp['created_at']));
        foreach ($sp['pieza_ids'] as $pid) {
            $fechas_entrega[$pid] = $fecha_real;
        }
        $salidas_previas[] = $sp;
    }
    $tiene_salidas = !empty($salidas_previas);
}
$salidas_previas_json = json_encode($salidas_previas, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// ── Generar tbody: SIEMPRE todas las piezas de la orden + columna Entrega ────
$tbody_html  = '';
$totales_txt = 'TOTAL PIEZAS: — &nbsp;|&nbsp; TOTAL M²: —';
$meses_doc   = ['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

if ($orden_id_php) {
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

    $parts_idx = [];
    foreach ($parts as $pt) { $parts_idx[(int)$pt['num_partida']] = $pt; }

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
        if (!empty($pt['resaques'])            && $pt['resaques'] > 0)            $specs[] = 'Res: ' . $pt['resaques'];
        if (!empty($pt['taladros_pasados'])    && $pt['taladros_pasados'] > 0)    $specs[] = 'TP: ' . $pt['taladros_pasados'];
        if (!empty($pt['taladros_avellanados'])&& $pt['taladros_avellanados'] > 0) $specs[] = 'TA: ' . $pt['taladros_avellanados'];
        $specs[] = !empty($pt['requiere_templado']) ? 'Templado' : 'No Templado';

        $mat   = $pt['cristal_nombre'] ?? ($grp[0]['cristal_corto'] ?? '—');
        $ancho = $grp[0]['ancho_mm'] ?? ($pt['ancho'] ?? '—');
        $alto  = $grp[0]['alto_mm']  ?? ($pt['alto']  ?? '—');

        // Columna Entrega: cuántas piezas del grupo tienen fecha de entrega
        $pids_grupo  = array_map(fn($p) => (int)$p['id'], $grp);
        $ent_dates   = array_filter(array_map(fn($pid) => $fechas_entrega[$pid] ?? null, $pids_grupo));
        $cnt_ent     = count($ent_dates);

        if ($cnt_ent === 0) {
            $entrega_html = '<span class="ent-pendiente">PENDIENTE</span>';
        } elseif ($cnt_ent === $cant) {
            $ultimo = max($ent_dates);
            $ts     = strtotime($ultimo);
            $fmtd   = date('d', $ts) . '/' . $meses_doc[(int)date('n', $ts)] . '/' . date('Y', $ts);
            $entrega_html = '<span class="ent-fecha">' . $fmtd . '</span>';
        } else {
            $ultimo = max($ent_dates);
            $ts     = strtotime($ultimo);
            $fmtd   = date('d', $ts) . '/' . $meses_doc[(int)date('n', $ts)] . '/' . date('Y', $ts);
            $entrega_html = '<span class="ent-parcial">' . $cnt_ent . '/' . $cant . ' al ' . $fmtd . '</span>';
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
        $tbody_html .= '<td class="entrega-col" id="cel-ent-' . (int)$np . '">' . $entrega_html . '</td>';
        $tbody_html .= '</tr>';
    }
    $totales_txt = 'TOTAL PIEZAS: ' . $tot_pz . '  |  TOTAL M²: ' . number_format($tot_m2, 4);
}

// Fecha de entrega a mostrar en el doc (fecha_chofer_php > estimada de orden)
if ($fecha_chofer_php) {
    $d = (int)date('j', strtotime($fecha_chofer_php));
    $m = (int)date('n', strtotime($fecha_chofer_php));
    $a = date('Y', strtotime($fecha_chofer_php));
    $fecha_ent = $d . '/' . str_pad($m, 2, '0', STR_PAD_LEFT) . '/' . $a;
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

/* ── Barra superior ── */
.print-bar {
  background: #1a1a2e; padding: 10px 24px;
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
}
.print-bar span { color: #94a3b8; font-size: 12px; }
.btn-print  { background: #f5a623; color: #000; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; }
.btn-cancel { background: #334155; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font-size: 13px; cursor: pointer; }

/* ── Menú: Reimprimir o Nueva Entrega ── */
#menu-salidas {
  max-width: 520px; margin: 60px auto; padding: 0 24px;
}
.menu-titulo { font-size: 20px; font-weight: 700; color: #1a1a2e; margin-bottom: 6px; }
.menu-sub    { font-size: 13px; color: #64748b; margin-bottom: 32px; }
.menu-opciones { display: flex; flex-direction: column; gap: 14px; }
.btn-menu {
  display: flex; align-items: center; gap: 16px;
  border: 2px solid #e2e8f0; border-radius: 10px;
  padding: 18px 22px; cursor: pointer; background: #fff;
  text-align: left; width: 100%; transition: border-color .15s, background .15s;
}
.btn-menu:hover { border-color: #1a1a2e; background: #f8fafc; }
.btn-menu-icon {
  width: 44px; height: 44px; border-radius: 8px;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.btn-menu-icon.verde { background: #dcfce7; color: #16a34a; }
.btn-menu-icon.azul  { background: #dbeafe; color: #1d4ed8; }
.btn-menu-texto strong { display: block; font-size: 14px; font-weight: 700; color: #1e293b; }
.btn-menu-texto span  { font-size: 12px; color: #64748b; }
.menu-salidas-hist { margin-top: 28px; font-size: 11px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 14px; }
.menu-salidas-hist ul { margin-top: 6px; padding-left: 18px; line-height: 1.9; }

/* ── Selector de piezas ── */
#selector-view { padding: 20px 28px; max-width: 780px; margin: 0 auto; }
.sel-titulo { font-size: 16px; font-weight: 700; color: #1a1a2e; margin-bottom: 4px; }
.sel-sub    { font-size: 12px; color: #64748b; margin-bottom: 20px; }

.partida-bloque { border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 14px; overflow: hidden; }
.partida-header {
  background: #f8fafc; padding: 10px 16px;
  display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0;
}
.partida-header .ph-left  { font-weight: 700; font-size: 13px; color: #1e293b; }
.partida-header .ph-right { font-size: 11px; color: #64748b; }
.partida-header .ph-actions { display: flex; gap: 8px; align-items: center; }
.btn-tod { background: none; border: 1px solid #cbd5e1; color: #334155; font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 4px; cursor: pointer; transition: background .12s; }
.btn-tod:hover { background: #f1f5f9; }

.piezas-grid { display: flex; flex-wrap: wrap; gap: 8px; padding: 12px 16px; }
.pieza-chip {
  display: flex; align-items: center; gap: 6px;
  border: 1.5px solid #cbd5e1; border-radius: 6px;
  padding: 8px 12px; cursor: pointer; user-select: none;
  font-size: 12px; font-weight: 600; color: #334155;
  transition: background .15s, border-color .15s, color .15s; min-height: 36px;
}
.pieza-chip:not(.entregado):not(.en-proceso):hover { background: #f0fdf4; border-color: #86efac; }
.pieza-chip:focus-within { outline: 2px solid #3b82f6; outline-offset: 1px; }
.pieza-chip input[type=checkbox] { margin: 0; width: 14px; height: 14px; cursor: pointer; }
.pieza-chip.checked   { background: #ecfdf5; border-color: #16a34a; color: #15803d; }
.pieza-chip.checked:hover { background: #dcfce7; }
.pieza-chip.entregado { background: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; cursor: default; opacity: .7; }
.pieza-chip.en-proceso { background: #fff7ed; border-color: #fed7aa; color: #9a3412; cursor: default; }

.leyenda { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 16px; }
.leyenda-item { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #475569; }
.leyenda-dot { width: 10px; height: 10px; border-radius: 3px; border: 1.5px solid; }
.leyenda-dot.l-term { background: #ecfdf5; border-color: #16a34a; }
.leyenda-dot.l-ent  { background: #f1f5f9; border-color: #e2e8f0; }
.leyenda-dot.l-proc { background: #fff7ed; border-color: #fed7aa; }

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
  display: flex; align-items: center; justify-content: center; gap: 8px; transition: background .15s;
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

.tipo-toggle-wrap { margin-bottom: 16px; }
.tipo-toggle-lbl  { font-size: 11px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
.tipo-toggle { display: inline-flex; background: #f1f5f9; border: 1.5px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
.tipo-btn {
  padding: 8px 18px; font-size: 13px; font-weight: 600; cursor: pointer;
  border: none; background: transparent; color: #64748b;
  transition: background .15s, color .15s; min-height: 40px;
  display: flex; align-items: center; gap: 6px;
}
.tipo-btn.active { background: #1a1a2e; color: #fff; }
.tipo-btn:not(.active):hover { background: #e2e8f0; color: #334155; }

.no-piezas { padding: 32px 16px; text-align: center; }
.no-piezas p { color: #64748b; font-size: 13px; line-height: 1.5; }

@media (max-width: 600px) {
  .sel-footer { flex-direction: column; align-items: stretch; }
  .btn-confirmar { width: 100%; }
}

/* ── Documento imprimible ── */
.doc { padding: 20px 28px; max-width: 960px; margin: 0 auto; }

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
    font-size: 10px; font-weight: 700; text-align: center; text-transform: uppercase; letter-spacing: .3px;
}
.partidas-table td {
    border: 1px solid #999; padding: 6px 5px;
    font-size: 11px; vertical-align: middle; text-align: center;
}
.partidas-table td.left { text-align: left; }
.partidas-table tr:nth-child(even) { background: #f9fafb; }
.cristal-cell { font-weight: 600; text-align: left; }

/* Columna Entrega */
.entrega-col { text-align: center; min-width: 82px; font-size: 10px; }
.ent-pendiente { color: #94a3b8; font-style: italic; font-weight: 600; }
.ent-fecha     { color: #15803d; font-weight: 700; }
.ent-parcial   { color: #c2410c; font-weight: 600; }

.totales-row { margin-top: 12px; display: flex; justify-content: space-between; align-items: flex-start; }
.total-box   { border: 2px solid #1a1a2e; border-radius: 4px; padding: 8px 20px; font-size: 13px; font-weight: 800; }

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
.log-grid  { display: grid; grid-template-columns: repeat(4, 1fr); }
.log-field { padding: 8px 12px; border-right: 1px solid #ddd; }
.log-field:last-child { border-right: none; }
.log-lbl  { font-size: 9px; font-weight: 700; color: #555; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 14px; }
.log-line { border-bottom: 1px solid #000; margin-top: 4px; }

.firmas { margin-top: 28px; display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; }
.firma-box { text-align: center; }
.firma-linea { border-top: 1.5px solid #000; margin-bottom: 5px; margin-top: 36px; }
.firma-lbl { font-size: 10px; color: #374151; }

.condiciones { margin-top: 14px; font-size: 9px; color: #555; border-top: 1px solid #e2e8f0; padding-top: 8px; line-height: 1.5; }
.pie         { margin-top: 8px; font-size: 9px; color: #6b7280; border-top: 1px solid #e2e8f0; padding-top: 6px; }

@media print {
    .no-print { display: none !important; }
    #menu-salidas { display: none !important; }
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
    <button class="btn-cancel" id="btnVolver"   style="display:none" onclick="volverAlMenu()">&#8592; Volver</button>
    <button class="btn-print"  id="btnImprimir" style="display:none" onclick="window.print()">&#128438; Imprimir / Guardar PDF</button>
  </div>
</div>

<!-- ── Menú: Reimprimir o Registrar nueva entrega (Caso A) ── -->
<div id="menu-salidas" class="no-print" style="display:none">
  <div class="menu-titulo">Orden <?= htmlspecialchars($folio_orden) ?></div>
  <div class="menu-sub">Esta orden tiene entregas registradas. ¿Qué deseas hacer?</div>
  <div class="menu-opciones">
    <button class="btn-menu" onclick="reimprimir()">
      <div class="btn-menu-icon verde">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
      </div>
      <div class="btn-menu-texto">
        <strong>Reimprimir</strong>
        <span>Imprime el estado actual de la orden sin registrar nada nuevo</span>
      </div>
    </button>
    <button class="btn-menu" id="btnMenuNuevaEntrega" onclick="irASelector()">
      <div class="btn-menu-icon azul">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
      </div>
      <div class="btn-menu-texto">
        <strong>Registrar nueva entrega</strong>
        <span>Selecciona piezas adicionales para entregar</span>
      </div>
    </button>
  </div>
  <div class="menu-salidas-hist" id="menu-hist-lista"></div>
</div>

<!-- ── Selector de piezas ── -->
<div id="selector-view" class="no-print" style="display:none">
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

<!-- ── Documento imprimible (PHP-generado, siempre con todas las piezas) ── -->
<div class="doc" id="doc-print" style="display:none">

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
        <th style="width:90px">Entrega</th>
      </tr>
    </thead>
    <tbody id="doc-tbody"><?= $tbody_html ?></tbody>
  </table>

  <div class="totales-row">
    <div class="total-box" id="doc-totales"><?= $totales_txt ?></div>
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
var ORDEN_ID        = <?= $orden_id_php ?>;
var COTIZACION_ID   = <?= $cotizacion_id_php ?>;
var TIPO_ENTREGA    = '<?= $tipo_ent ?>';
var PARTS           = <?= $parts_json ?>;
var FECHA_CHOFER    = '<?= $fecha_chofer_php ?>';
var YA_ENTREGADA    = <?= $ya_entregada ? 'true' : 'false' ?>;
var TIENE_SALIDAS   = <?= $tiene_salidas ? 'true' : 'false' ?>;
var SALIDAS_PREVIAS = <?= $salidas_previas_json ?>;

var todasPiezas   = [];
var seleccionadas = {};

function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// ── INIT: detectar caso ───────────────────────────────────────────────────────
(function init() {
  if (YA_ENTREGADA) {
    // Caso E: orden ya cerrada — directo a impresión
    mostrarDoc(false);
    setTimeout(function() { window.print(); }, 400);
    return;
  }
  if (TIENE_SALIDAS) {
    // Caso A: hay salidas registradas — mostrar menú
    mostrarMenuSalidas();
    return;
  }
  if (!ORDEN_ID) {
    document.getElementById('menu-salidas').querySelector('.menu-sub').textContent =
      'Esta cotización no tiene una orden de producción vinculada.';
    document.getElementById('menu-salidas').style.display = 'block';
    return;
  }
  // Caso C/D: primera entrega — ir directo al selector
  irASelector();
})();

// ── Mostrar menú (Caso A) ─────────────────────────────────────────────────────
function mostrarMenuSalidas() {
  // Si no quedan piezas terminadas, deshabilitar "nueva entrega"
  // (lo sabemos al abrir el selector, no aquí, así que dejamos habilitado)
  renderHistorialMenu();
  document.getElementById('menu-salidas').style.display = 'block';
}

function renderHistorialMenu() {
  if (!SALIDAS_PREVIAS.length) return;
  var meses = ['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  var html = '<strong>Entregas registradas:</strong><ul>';
  SALIDAS_PREVIAS.forEach(function(s, i) {
    var dt   = new Date(s.created_at.replace(' ', 'T') + '-06:00');
    var fstr = dt.getDate() + '/' + meses[dt.getMonth()+1] + '/' + dt.getFullYear();
    var tipo = s.tipo === 'chofer' ? 'Domicilio' : 'Recolección';
    var parc = s.es_parcial == 1 ? ' · Parcial' : ' · Completa';
    html += '<li>Entrega ' + (i+1) + ' — ' + s.piezas_count + ' pieza(s) · ' + tipo + parc + ' · ' + fstr + '</li>';
  });
  html += '</ul>';
  document.getElementById('menu-hist-lista').innerHTML = html;
}

// ── Reimprimir (Caso B): mostrar documento actual sin preguntas ───────────────
function reimprimir() {
  mostrarDoc(true);
  setTimeout(function() { window.print(); }, 400);
}

// ── Ir al selector de piezas (Casos C / D) ───────────────────────────────────
function irASelector() {
  document.getElementById('menu-salidas').style.display  = 'none';
  document.getElementById('selector-view').style.display = 'block';

  if (todasPiezas.length > 0) {
    // Ya tenemos piezas cargadas (ej: volvimos del documento tras una entrega)
    renderSelector();
    return;
  }
  fetch('../api/salidas.php?accion=piezas_terminadas&orden_id=' + ORDEN_ID)
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.ok) throw new Error(data.error || 'Error');
      todasPiezas = data.piezas;
      renderSelector();
    })
    .catch(function(e) {
      document.getElementById('partidas-container').innerHTML =
        '<div class="no-piezas"><p>Error al cargar piezas: ' + esc(e.message) + '</p></div>';
    });
}

// ── Renderizar chips selector ─────────────────────────────────────────────────
function renderSelector() {
  seleccionadas = {};
  var grupos = {};
  todasPiezas.forEach(function(p) {
    if (!grupos[p.partida]) grupos[p.partida] = [];
    grupos[p.partida].push(p);
  });

  var html = '';
  var hayTerminadas = false;

  Object.keys(grupos).sort(function(a, b) { return a - b; }).forEach(function(numPart) {
    var piezas  = grupos[numPart];
    var part    = PARTS.find(function(pt) { return pt.num_partida == numPart; }) || {};
    var termCnt = piezas.filter(function(p) { return p.estatus === 'terminado'; }).length;
    var entCnt  = piezas.filter(function(p) { return p.estatus === 'entregado'; }).length;

    html += '<div class="partida-bloque">';
    html += '<div class="partida-header">';
    html += '<div class="ph-left">Partida ' + numPart + ' &nbsp;—&nbsp; ' + esc(part.cristal_nombre || piezas[0].cristal_corto || '—');
    if (part.ancho) html += ' &nbsp;' + esc(part.ancho) + ' × ' + esc(part.alto) + ' mm';
    html += '</div>';
    html += '<div class="ph-actions">';
    html += '<span style="font-size:11px;color:#64748b">' + termCnt + ' terminada(s) · ' + entCnt + ' entregada(s)</span>';
    if (termCnt > 0) {
      html += '<button class="btn-tod" onclick="togglePartida(\'' + numPart + '\', true)">Todo</button>';
      html += '<button class="btn-tod" onclick="togglePartida(\'' + numPart + '\', false)">Ninguno</button>';
    }
    html += '</div></div>';
    html += '<div class="piezas-grid">';

    piezas.forEach(function(p) {
      var clase    = '';
      var disabled = true;
      var checked  = '';
      if (p.estatus === 'terminado') {
        clase = 'checked'; disabled = false; checked = 'checked';
        seleccionadas[p.id] = true; hayTerminadas = true;
      } else if (p.estatus === 'entregado') {
        clase = 'entregado';
      } else {
        clase = 'en-proceso';
      }
      var titulo = p.estatus === 'entregado' ? 'Ya entregada' :
                   (p.estatus !== 'terminado' ? 'En producción (' + p.estatus + ')' : '');
      html += '<label class="pieza-chip ' + clase + '" title="' + titulo + '" id="chip-' + p.id + '">';
      html += '<input type="checkbox" ' + (disabled ? 'disabled' : '') + ' ' + checked + ' ';
      html += 'onchange="togglePieza(' + p.id + ', this.checked)" value="' + p.id + '">';
      html += 'P' + p.pieza_num + '/' + p.pieza_total;
      html += '</label>';
    });

    if (termCnt === 0) {
      html += '<span style="font-size:12px;color:#94a3b8;padding:8px">Sin piezas terminadas</span>';
    }
    html += '</div></div>';
  });

  if (!hayTerminadas) {
    html = '<div class="no-piezas"><p>No hay piezas en estatus <strong>terminado</strong>.<br>Todas las piezas ya fueron entregadas o están en producción.</p></div>';
    // Ocultar el botón nueva entrega en el menú si vuelven al menú
    var btnNueva = document.getElementById('btnMenuNuevaEntrega');
    if (btnNueva) btnNueva.disabled = true;
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

function togglePartida(numPart, activar) {
  todasPiezas.forEach(function(p) {
    if (p.partida == numPart && p.estatus === 'terminado') {
      seleccionadas[p.id] = activar;
      var chip = document.getElementById('chip-' + p.id);
      if (chip) { chip.className = 'pieza-chip ' + (activar ? 'checked' : ''); chip.querySelector('input').checked = activar; }
    }
  });
  actualizarContador();
}

function actualizarContador() {
  var cnt = Object.values(seleccionadas).filter(Boolean).length;
  document.getElementById('cnt-sel').textContent = cnt;
  document.getElementById('btnConfirmar').disabled = cnt === 0;
}

function setTipo(tipo) {
  TIPO_ENTREGA = tipo;
  document.getElementById('btnTipoRecoleccion').className = 'tipo-btn' + (tipo === 'recoleccion' ? ' active' : '');
  document.getElementById('btnTipoChofer').className      = 'tipo-btn' + (tipo === 'chofer'      ? ' active' : '');
  var campoFecha = document.getElementById('campo-fecha-chofer');
  campoFecha.style.display = (tipo === 'chofer') ? '' : 'none';
  if (tipo !== 'chofer') document.getElementById('fecha-chofer').value = '';
  var docTipo = document.getElementById('doc-tipo-entrega-val');
  if (docTipo) docTipo.textContent = (tipo === 'chofer') ? 'Domicilio / Ruta' : 'Recolección en planta';
}

// ── Confirmar salida ──────────────────────────────────────────────────────────
function confirmarSalida() {
  var ids = Object.keys(seleccionadas).filter(function(k) { return seleccionadas[k]; }).map(Number);
  if (!ids.length) return;

  var fechaChofer = null;
  if (TIPO_ENTREGA === 'chofer') {
    var fcInput = document.getElementById('fecha-chofer');
    if (!fcInput || !fcInput.value) { alert('Indica la fecha de entrega por chofer.'); if (fcInput) fcInput.focus(); return; }
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
    var pids = data.pieza_ids || ids;

    // Registrar en historial de sesión
    var ahora = new Date();
    var pad   = function(n) { return String(n).padStart(2,'0'); };
    SALIDAS_PREVIAS.push({
      id: data.salida_id, tipo: TIPO_ENTREGA,
      piezas_count: pids.length, total_piezas: todasPiezas.length,
      es_parcial: data.es_parcial ? 1 : 0,
      fecha_entrega_chofer: fechaChofer,
      created_at: ahora.getFullYear() + '-' + pad(ahora.getMonth()+1) + '-' + pad(ahora.getDate())
                + ' ' + pad(ahora.getHours()) + ':' + pad(ahora.getMinutes()) + ':00',
      pieza_ids: pids
    });
    TIENE_SALIDAS = true;

    // Actualizar fecha en header del doc
    if (fechaChofer) {
      document.getElementById('doc-fecha-entrega').textContent = formatFechaDoc(fechaChofer);
    }

    // Actualizar celdas Entrega en el documento PHP
    actualizarCeldasEntrega(pids, fechaChofer || new Date().toISOString().substring(0,10));

    // Actualizar piezas en memoria para que el selector esté correcto al volver
    var pidsSet = {};
    pids.forEach(function(pid) { pidsSet[pid] = true; });
    todasPiezas.forEach(function(p) { if (pidsSet[p.id]) p.estatus = 'entregado'; });

    // Mostrar documento e imprimir
    document.getElementById('selector-view').style.display = 'none';
    mostrarDoc(true);
    setTimeout(function() { window.print(); }, 400);
  })
  .catch(function(e) {
    alert('Error al registrar: ' + e.message);
    btn.disabled = false;
    btn.classList.remove('loading');
    document.getElementById('spinnerConfirmar').style.display = 'none';
    btn.querySelector('.btn-label').textContent = 'Confirmar e imprimir';
  });
}

// ── Actualizar celdas de entrega en el documento PHP ─────────────────────────
function actualizarCeldasEntrega(pids, fechaStr) {
  var fechaDisplay = formatFechaDoc(fechaStr);
  var pidsSet = {};
  pids.forEach(function(id) { pidsSet[id] = true; });

  // IDs ya entregados previamente en esta sesión (de SALIDAS_PREVIAS anteriores)
  var prevSet = {};
  SALIDAS_PREVIAS.slice(0, SALIDAS_PREVIAS.length - 1).forEach(function(s) {
    (s.pieza_ids || []).forEach(function(pid) { prevSet[pid] = true; });
  });

  // Partidas afectadas por esta salida
  var partidasAfectadas = {};
  todasPiezas.forEach(function(p) {
    if (pidsSet[p.id]) partidasAfectadas[p.partida] = true;
  });

  Object.keys(partidasAfectadas).forEach(function(np) {
    var cel = document.getElementById('cel-ent-' + np);
    if (!cel) return;

    var totalPartida = 0;
    var entregadasTotal = 0;
    todasPiezas.forEach(function(p) {
      if (p.partida == np) {
        totalPartida++;
        if (pidsSet[p.id] || prevSet[p.id]) entregadasTotal++;
      }
    });

    if (entregadasTotal >= totalPartida) {
      cel.innerHTML = '<span class="ent-fecha">' + fechaDisplay + '</span>';
    } else {
      cel.innerHTML = '<span class="ent-parcial">' + entregadasTotal + '/' + totalPartida + ' al ' + fechaDisplay + '</span>';
    }
  });
}

// ── Mostrar documento imprimible ──────────────────────────────────────────────
function mostrarDoc(conVolver) {
  // Badge parcial: hay PENDIENTE en el documento?
  var pendientes = document.querySelectorAll('.ent-pendiente').length;
  var badge = document.getElementById('doc-badge-parcial');
  badge.innerHTML = pendientes > 0 ? '<span class="badge-parcial">ENTREGA PARCIAL</span>' : '';

  // Tipo de entrega en el doc
  var docTipo = document.getElementById('doc-tipo-entrega-val');
  if (docTipo) docTipo.textContent = TIPO_ENTREGA === 'chofer' ? 'Domicilio / Ruta' : 'Recolección en planta';

  document.getElementById('doc-print').style.display    = 'block';
  document.getElementById('btnImprimir').style.display  = 'inline-block';
  document.getElementById('btnVolver').style.display    = conVolver ? 'inline-block' : 'none';
}

// ── Volver al menú desde el documento ────────────────────────────────────────
function volverAlMenu() {
  document.getElementById('doc-print').style.display   = 'none';
  document.getElementById('btnImprimir').style.display = 'none';
  document.getElementById('btnVolver').style.display   = 'none';
  if (TIENE_SALIDAS) {
    renderHistorialMenu();
    document.getElementById('menu-salidas').style.display = 'block';
  } else {
    irASelector();
  }
}

// ── Utilidades ────────────────────────────────────────────────────────────────
function formatFechaDoc(fechaStr) {
  if (!fechaStr) return '—';
  var meses  = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  var partes = fechaStr.split('-');
  if (partes.length !== 3) return fechaStr;
  return partes[2] + '/' + meses[parseInt(partes[1]) - 1] + '/' + partes[0];
}
</script>
</body>
</html>
