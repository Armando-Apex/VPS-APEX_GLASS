<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
requirePermiso('ver_ordenes');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) die('ID requerido');

$db  = getDB();
$cot = $db->prepare('
    SELECT c.*,
           cl.razon_social, cl.telefono as cliente_tel,
           u.nombre as asesor_nombre_usr,
           o.id AS orden_id_real, o.folio AS orden_folio
    FROM cotizaciones c
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
    LEFT JOIN usuarios u  ON u.id  = c.asesor_id
    LEFT JOIN ordenes o   ON o.id  = c.orden_id
    WHERE c.id = ?
');
$cot->execute([$id]);
$c = $cot->fetch();
if (!$c) die('Orden no encontrada');

$partidas = $db->prepare('
    SELECT cp.*
    FROM cotizaciones_partidas cp
    WHERE cp.cotizacion_id = ?
    ORDER BY cp.num_partida ASC
');
$partidas->execute([$id]);
$parts = $partidas->fetchAll();

$cliente       = $c['razon_social'] ?: $c['cliente_nombre'];
$folio         = $c['orden_folio'] ?: ($c['folio'] ?: '—');
$fecha         = date('d/m/Y', strtotime($c['created_at']));
$fechaEnt      = $c['fecha_entrega'] ? date('d/m/Y', strtotime($c['fecha_entrega'])) : '—';
$asesor        = $c['asesor_nombre_usr'] ?? $c['asesor_nombre'] ?? '—';
$proyecto      = $c['proyecto'] ?: '—';
$tipoEntrega   = $c['tipo_entrega'] === 'domicilio' ? 'Domicilio' : 'Planta';
$ordenId = (int)($c['orden_id_real'] ?? 0);

// Calcular m² total
$m2_total = 0;
$total_pzas = 0;
foreach ($parts as $p) {
    $m2 = round(($p['ancho'] / 1000) * ($p['alto'] / 1000), 4);
    $m2_total += $m2 * $p['cantidad'];
    $total_pzas += $p['cantidad'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Orden de Producción <?= htmlspecialchars($folio) ?> — APEX GLASS</title>
<script src="../lib/qrcode.min.js"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Syncopate:wght@700&family=Inter:wght@400;500;600;700&display=swap');

* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', Arial, sans-serif; font-size: 11px; color: #000; background: white; }

/* ── Barra impresión ── */
.print-bar { background: #1a1a2e; padding: 10px 24px; display: flex; align-items: center; justify-content: space-between; }
.print-bar span { color: #94a3b8; font-size: 12px; }
.btn-print { background: #2563eb; color: white; border: none; padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 700; cursor: pointer; }

/* ── Documento ── */
.doc { padding: 20px 28px; max-width: 960px; margin: 0 auto; }

/* ── Header ── */
.header { text-align: center; border: 2px solid #000; padding: 10px; margin-bottom: 0; position: relative; }
.qr-masivo { position: absolute; top: 6px; right: 8px; width: 56px; text-align: center; }
.qr-masivo canvas, .qr-masivo img { width: 56px !important; height: 56px !important; }
.qr-masivo .qr-masivo-lbl { font-size: 6px; font-weight: 700; color: #6b7280; margin-top: 1px; letter-spacing: .3px; }
.empresa { font-family: 'Syncopate', sans-serif; font-size: 14px; font-weight: 700; letter-spacing: 1px; }
.titulo { font-size: 13px; font-weight: 700; margin-top: 2px; }
.subtipo { font-size: 12px; font-weight: 600; color: #374151; }

/* ── Info grid ── */
.info-table { width: 100%; border-collapse: collapse; border: 1px solid #000; border-top: none; }
.info-table td { border: 1px solid #000; padding: 5px 8px; font-size: 11px; }
.info-table .lbl { font-weight: 700; background: #f3f4f6; white-space: nowrap; width: 120px; }
.info-table .val { font-weight: 600; }

/* ── Tabla partidas ── */
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
.partidas-table tr:hover { background: #eff6ff; }
.cristal-cell { font-weight: 600; text-align: left; }
.det-pill { display: inline-block; background: #e0e7ff; color: #3730a3; border-radius: 4px; padding: 1px 5px; font-size: 9px; font-weight: 700; margin: 1px; }
.det-pill.cpb { background: #fef3c7; color: #92400e; }
.det-pill.tp  { background: #dcfce7; color: #166534; }
.det-pill.ta  { background: #fce7f3; background: #f0fdf4; color: #15803d; }
.det-pill.res { background: #fee2e2; color: #991b1b; }
.si  { color: #15803d; font-weight: 700; }
.no  { color: #9ca3af; }

/* ── Footer ── */
.footer-info { margin-top: 16px; display: flex; justify-content: space-between; align-items: flex-start; }
.total-pzas { font-size: 13px; font-weight: 800; border: 2px solid #1a1a2e; padding: 8px 20px; border-radius: 6px; }
.firma-box { border-top: 1px solid #000; width: 220px; text-align: center; padding-top: 6px; font-size: 11px; color: #374151; margin-top: 40px; }
.m2-box { font-size: 12px; font-weight: 700; }
.recibio { margin-top: 32px; display: flex; justify-content: space-between; }
.recibio-item { text-align: center; }
.recibio-linea { border-top: 1px solid #000; width: 200px; margin: 0 auto 5px; }

/* ── Comentarios ── */
.comentarios-section { margin-top: 16px; }
.comentarios-titulo { font-size: 11px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; border-bottom: 1.5px solid #1a1a2e; padding-bottom: 4px; }
.comentario-card { border: 1.5px solid #1a1a2e; border-radius: 6px; padding: 10px 14px; margin-bottom: 8px; }
.comentario-card.cancelado { border-color: #dc2626; background: #fff5f5; }
.comentario-meta { font-size: 10px; font-weight: 700; color: #6b7280; margin-bottom: 6px; display: flex; align-items: center; gap: 10px; }
.comentario-meta .autor { color: #1a1a2e; font-size: 11px; }
.comentario-texto { font-size: 14px; font-weight: 700; color: #1a1a2e; line-height: 1.5; }
.comentario-cancelacion { margin-top: 8px; padding-top: 6px; border-top: 1px dashed #fca5a5; font-size: 10px; color: #dc2626; font-weight: 600; }
.btn-cancelar-com { background: none; border: 1px solid #dc2626; color: #dc2626; border-radius: 4px; padding: 2px 8px; font-size: 10px; cursor: pointer; font-weight: 700; }
.btn-cancelar-com:hover { background: #fee2e2; }
/* Modal comentario */
.com-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 999; align-items: center; justify-content: center; }
.com-overlay.show { display: flex; }
.com-modal { background: white; border-radius: 12px; padding: 28px; width: 480px; max-width: calc(100vw - 32px); box-shadow: 0 8px 32px rgba(0,0,0,.2); }
.com-modal h3 { font-size: 16px; font-weight: 700; margin-bottom: 16px; color: #1a1a2e; }
.com-modal textarea { width: 100%; height: 100px; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px; font-size: 14px; font-family: inherit; resize: vertical; }
.com-modal textarea:focus { outline: none; border-color: #1a1a2e; }
.com-modal-btns { display: flex; gap: 10px; justify-content: flex-end; margin-top: 14px; }
.btn-com-guardar { background: #16a34a; color: white; border: none; padding: 9px 22px; border-radius: 7px; font-size: 14px; font-weight: 700; cursor: pointer; }
.btn-com-guardar:hover { background: #15803d; }
.btn-com-cancel { background: #f1f5f9; color: #374151; border: none; padding: 9px 16px; border-radius: 7px; font-size: 14px; font-weight: 600; cursor: pointer; }

/* ── Print ── */
@media print {
    .no-print { display: none !important; }
    body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    @page { margin: 15mm 10mm; size: letter landscape; }
    .doc { padding: 0; }
    .btn-cancelar-com { display: none !important; }
}
</style>
</head>
<body>

<div class="print-bar no-print">
  <span>Orden de Producción — <?= htmlspecialchars($folio) ?></span>
  <div style="display:flex;gap:10px">
    <button class="btn-com-guardar" onclick="abrirModalComentario()" style="padding:8px 20px;font-size:13px">➕ Agregar comentario</button>
    <button class="btn-print" onclick="window.print()">🖨️ Imprimir / Guardar PDF</button>
  </div>
</div>

<div class="doc">

  <!-- Header -->
  <div class="header">
    <div class="empresa">TEMPLADORA NORESTE, S. A. DE C. V.</div>
    <div class="titulo">ORDEN DE PRODUCCIÓN — TEMPLADOS</div>
    <?php if ($ordenId): ?>
    <div class="qr-masivo" id="qrMasivo">
      <div class="qr-masivo-lbl">ESCANEA AL<br>TERMINAR CNC</div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Info -->
  <table class="info-table">
    <tr>
      <td class="lbl">Fecha:</td>
      <td class="val"><?= $fecha ?></td>
      <td class="lbl">Asesor comercial:</td>
      <td class="val"><?= htmlspecialchars($asesor) ?></td>
    </tr>
    <tr>
      <td class="lbl">Orden de trabajo:</td>
      <td class="val" style="font-family:'Syncopate',sans-serif;font-size:13px;font-weight:700;color:#1a1a2e"><?= htmlspecialchars($folio) ?></td>
      <td class="lbl">Tipo entrega:</td>
      <td class="val"><?= $tipoEntrega ?></td>
    </tr>
    <tr>
      <td class="lbl">Fecha entrega:</td>
      <td class="val" style="font-weight:700;color:#dc2626"><?= $fechaEnt ?></td>
      <td class="lbl">M²:</td>
      <td class="val"><?= number_format($m2_total, 4) ?></td>
    </tr>
    <tr>
      <td class="lbl">Cliente:</td>
      <td class="val"><?= htmlspecialchars($cliente) ?></td>
      <td class="lbl">Proyecto:</td>
      <td class="val"><?= htmlspecialchars($proyecto) ?></td>
    </tr>
  </table>

  <!-- Tabla partidas -->
  <table class="partidas-table">
    <thead>
      <tr>
        <th style="width:40px">Part.</th>
        <th>Cristal</th>
        <th style="width:70px">Ancho mm</th>
        <th style="width:70px">Alto mm</th>
        <th style="width:50px">Pzas.</th>
        <th style="width:65px">M²</th>
        <th style="width:80px">Detalles</th>
        <th style="width:60px">Templado</th>
        <th style="width:60px">CPB</th>
        <th style="width:55px">Resaques</th>
        <th style="width:35px">TP</th>
        <th style="width:35px">TA</th>
        <th style="width:55px">Pintura</th>
        <th>Observaciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($parts as $p):
      $m2 = round(($p['ancho'] / 1000) * ($p['alto'] / 1000), 4);
      $m2t = round($m2 * $p['cantidad'], 4);
      $templado = $p['requiere_templado'] ?? 1;
    ?>
      <tr>
        <td style="font-weight:700;color:#1d4ed8"><?= $p['num_partida'] ?></td>
        <td class="cristal-cell"><?= htmlspecialchars($p['cristal_nombre'] ?? '—') ?></td>
        <td><?= number_format($p['ancho']) ?></td>
        <td><?= number_format($p['alto']) ?></td>
        <td><?= $p['cantidad'] ?></td>
        <td><?= number_format($m2t, 4) ?></td>
        <td class="left"><?= htmlspecialchars($p['detalles'] ?: '—') ?></td>
        <td class="<?= $templado ? 'si' : 'no' ?>"><?= $templado ? 'Sí' : 'No' ?></td>
        <td><?= htmlspecialchars($p['cpb'] ?: '—') ?></td>
        <td><?= $p['resaques'] ?: '—' ?></td>
        <td><?= $p['taladros_pasados'] ?: '—' ?></td>
        <td><?= $p['taladros_avellanados'] ?: '—' ?></td>
        <td><?= htmlspecialchars($p['pintura'] ?? '—') ?></td>
        <td class="left"><?= htmlspecialchars($p['comentarios_etiqueta'] ?: '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Footer -->
  <div class="footer-info">
    <div>
      <div class="total-pzas">TOTAL DE PIEZAS: <?= $total_pzas ?></div>
      <div class="m2-box" style="margin-top:8px">TOTAL M²: <?= number_format($m2_total, 4) ?></div>
    </div>
    <div style="display:flex;gap:60px">
      <div style="text-align:center">
        <div style="margin-bottom:40px;font-size:11px;color:#374151">Recibió producción</div>
        <div class="recibio-linea"></div>
        <div style="font-size:10px">Nombre y firma</div>
      </div>
      <div style="text-align:center">
        <div style="margin-bottom:40px;font-size:11px;color:#374151">Fecha y hora</div>
        <div class="recibio-linea"></div>
        <div style="font-size:10px">—</div>
      </div>
    </div>
  </div>

  <!-- Comentarios de producción -->
  <div class="comentarios-section" id="comentarios-section"></div>

  <div style="margin-top:14px;font-size:9px;color:#6b7280;border-top:1px solid #e2e8f0;padding-top:8px">
    Templadora Noreste, S.A. de C.V. — Parque Industrial MARFER, Carr. Monterrey-Saltillo km 65, Av. De la Industria #214, Santa Catarina, N.L., C.P. 66367 — Tel: 81 1180 5078
  </div>

</div>

<!-- Modal agregar comentario -->
<div class="com-overlay no-print" id="com-overlay">
  <div class="com-modal">
    <h3>Agregar comentario a la orden</h3>
    <textarea id="com-texto" placeholder="Escribe el comentario..."></textarea>
    <div class="com-modal-btns">
      <button class="btn-com-cancel" onclick="cerrarModalComentario()">Cancelar</button>
      <button class="btn-com-guardar" onclick="guardarComentario()">Guardar</button>
    </div>
  </div>
</div>

<script>
var COT_ID = <?= (int)$id ?>;
var ORDEN_ID_MASIVO = <?= (int)$ordenId ?>;
var API_COM = '../api/orden_comentarios.php';

function cargarComentarios() {
    fetch(API_COM + '?accion=listar&cotizacion_id=' + COT_ID)
        .then(function(r){ return r.json(); })
        .then(function(d){ renderComentarios(d.comentarios || []); });
}

function renderComentarios(lista) {
    var sec = document.getElementById('comentarios-section');
    if (!lista.length) { sec.innerHTML = ''; return; }
    var html = '<div class="comentarios-titulo">Comentarios de producción</div>';
    lista.forEach(function(c) {
        var fecha = new Date(c.created_at);
        var fechaStr = fecha.toLocaleDateString('es-MX', {day:'2-digit',month:'2-digit',year:'numeric'});
        var horaStr  = fecha.toLocaleTimeString('es-MX', {hour:'2-digit',minute:'2-digit'});
        html += '<div class="comentario-card' + (c.cancelado == 1 ? ' cancelado' : '') + '">';
        html += '<div class="comentario-meta">';
        html += '<span class="autor">' + escHtml(c.usuario_nombre) + '</span>';
        html += '<span>' + fechaStr + ' &nbsp;·&nbsp; ' + horaStr + '</span>';
        if (!c.cancelado) {
            html += '<button class="btn-cancelar-com no-print" onclick="cancelarComentario(' + c.id + ')">✕ Cancelar</button>';
        }
        html += '</div>';
        html += '<div class="comentario-texto">' + escHtml(c.texto) + '</div>';
        if (c.cancelado == 1 && c.cancelado_por) {
            var fechaCan = new Date(c.cancelado_at);
            var fechaCanStr = fechaCan.toLocaleDateString('es-MX', {day:'2-digit',month:'2-digit',year:'numeric'});
            var horaCanStr  = fechaCan.toLocaleTimeString('es-MX', {hour:'2-digit',minute:'2-digit'});
            html += '<div class="comentario-cancelacion">⛔ Cancelado por ' + escHtml(c.cancelado_por) + ' — ' + fechaCanStr + ' ' + horaCanStr + '</div>';
        }
        html += '</div>';
    });
    sec.innerHTML = html;
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function abrirModalComentario() {
    document.getElementById('com-texto').value = '';
    document.getElementById('com-overlay').classList.add('show');
    document.getElementById('com-texto').focus();
}

function cerrarModalComentario() {
    document.getElementById('com-overlay').classList.remove('show');
}

function guardarComentario() {
    var texto = document.getElementById('com-texto').value.trim();
    if (!texto) return;
    fetch(API_COM, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({accion:'agregar', cotizacion_id: COT_ID, texto: texto})
    }).then(function(r){ return r.json(); }).then(function(d) {
        if (d.ok) { cerrarModalComentario(); cargarComentarios(); }
        else alert(d.error || 'Error al guardar');
    });
}

function cancelarComentario(id) {
    if (!confirm('¿Cancelar este comentario?')) return;
    fetch(API_COM, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({accion:'cancelar', id: id})
    }).then(function(r){ return r.json(); }).then(function(d) {
        if (d.ok) cargarComentarios();
        else alert(d.error || 'Error');
    });
}

document.getElementById('com-overlay').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalComentario();
});

if (ORDEN_ID_MASIVO) {
    var qrEl = document.getElementById('qrMasivo');
    var qrBox = document.createElement('div');
    qrEl.insertBefore(qrBox, qrEl.firstChild);
    new QRCode(qrBox, {
        text: 'https://apex.glass/produccion/app/operador.php?orden_masivo=' + ORDEN_ID_MASIVO,
        width: 48,
        height: 48,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
}

cargarComentarios();
</script>
</body>
</html>