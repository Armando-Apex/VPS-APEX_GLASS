<?php
// ============================================================
//  APEX GLASS - Impresión de etiquetas con QR
//  Archivo: app/imprimir_etiquetas.php
//  Uso: imprimir_etiquetas.php?folio=R-801
//       imprimir_etiquetas.php?folio=R-801&partida=3  (solo una partida)
// ============================================================
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
requireSession();

$folio   = strtoupper(trim($_GET['folio']   ?? ''));
$partida = intval($_GET['partida'] ?? 0);

if (!$folio) {
    die('<p>Falta el folio. Ejemplo: ?folio=R-801</p>');
}

$db = getDB();

// Obtener datos de la orden
$stmt = $db->prepare('SELECT * FROM ordenes WHERE folio = ?');
$stmt->execute([$folio]);
$orden = $stmt->fetch();

if (!$orden) {
    die('<p>Orden no encontrada: ' . htmlspecialchars($folio) . '</p>');
}


// Obtener piezas
if ($partida > 0) {
    $stmt = $db->prepare('SELECT * FROM piezas WHERE orden_id = ? AND partida = ? ORDER BY partida, pieza_num');
    $stmt->execute([$orden['id'], $partida]);
} else {
    $stmt = $db->prepare('SELECT * FROM piezas WHERE orden_id = ? ORDER BY partida, pieza_num');
    $stmt->execute([$orden['id']]);
}
$piezas = $stmt->fetchAll();

if (empty($piezas)) {
    die('<p>No hay piezas para este folio.</p>');
}

// Servicios adicionales por partida
$serviciosPorPartida = [];
$stmtSrv = $db->prepare("
    SELECT cps.descripcion, cps.unidades_por_pieza, sc.nombre AS servicio_nombre, cp.num_partida
    FROM cotizacion_partida_servicios cps
    JOIN cotizaciones_partidas cp ON cp.id = cps.partida_id
    JOIN cotizaciones c ON c.id = cp.cotizacion_id
    LEFT JOIN servicios_catalogo sc ON sc.id = cps.servicio_id
    WHERE c.orden_id = ?
    ORDER BY cp.num_partida, cps.id
");
$stmtSrv->execute([$orden['id']]);
foreach ($stmtSrv->fetchAll() as $srv) {
    $label = trim($srv['descripcion'] ?: ($srv['servicio_nombre'] ?? ''));
    if ($label !== '') {
        $uni = (int)($srv['unidades_por_pieza'] ?? 0);
        if ($uni > 0) $label .= ' - ' . $uni;
        $serviciosPorPartida[(int)$srv['num_partida']][] = $label;
    }
}

// Fecha de entrega formateada
$fechaEntrega = '';
if ($orden['fecha_entrega']) {
    $dt = new DateTime($orden['fecha_entrega']);
    $fechaEntrega = $dt->format('d/m');
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Etiquetas <?= htmlspecialchars($folio) ?></title>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<style>
* { margin:0; padding:0; box-sizing:border-box; }

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #e8e8e8;
    padding: 20px;
}

.toolbar {
    background: #1a1a2e;
    color: white;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
    border-radius: 8px;
}
.toolbar h1 { font-size: 15px; font-weight: 700; letter-spacing: 1px; }
.toolbar .info { font-size: 12px; color: #94a3b8; }
.btn-print {
    background: white; color: #1a1a2e;
    border: none; padding: 8px 20px;
    border-radius: 6px; font-size: 13px;
    font-weight: 700; cursor: pointer;
    letter-spacing: .3px;
}
.btn-print:hover { background: #f0f0f0; }

/* Grid de etiquetas en pantalla */
.grid {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

/* ── ETIQUETA ── */
/* Tamaño real: 76.2mm x 50.8mm (3" x 2") */
/* En pantalla: ~288px x 192px a 96dpi */
.etiqueta {
    width: 288px;
    height: 192px;
    background: white;
    border: 1.5px solid #222;
    border-radius: 3px;
    display: flex;
    overflow: hidden;
    page-break-inside: avoid;
}

/* Columna izquierda */
.col-izq {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    padding: 6px 7px 5px 7px;
    border-right: 1px solid #555;
}

/* Columna derecha */
.col-der {
    width: 94px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    padding: 5px 4px 4px 4px;
}

/* Folio */
.folio    { font-size: 17px; font-weight: 900; color: #000; letter-spacing: .3px; line-height: 1; }
.partida  { font-size: 11px; font-weight: 700; color: #222; margin-top: 1px; }

.divider  { border: none; border-top: .5px solid #000; margin: 3px 0; }

/* Cristal + medidas */
.fila-cristal { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2px; }
.lbl    { font-size: 7.5px; color: #666; text-transform: uppercase; letter-spacing: .3px; line-height: 1; }
.cristal-val  { font-size: 11px; font-weight: 700; color: #000; line-height: 1.2; }
.medidas-val  { font-size: 13px; font-weight: 900; color: #000; line-height: 1.2; text-align: right; }

/* Badge CPB — mismo estilo que Filo Muerto */
.badge-cpb {
    background: #fff; border: 1.5px solid #000; border-radius: 2px;
    padding: 2px 6px; display: inline-block; margin-bottom: 2px;
}
.badge-cpb span { font-size: 9.5px; font-weight: 700; color: #000; letter-spacing: .2px; }

/* Badge Filo Muerto — borde */
.badge-fm {
    background: #fff; border: 1.5px solid #000; border-radius: 2px;
    padding: 2px 6px; display: inline-block; margin-bottom: 2px;
}
.badge-fm span { font-size: 9.5px; font-weight: 700; color: #000; }

/* Chips trabajos */
.chips { display: flex; gap: 3px; flex-wrap: wrap; margin-bottom: 2px; }
.chip {
    font-size: 9px; font-weight: 700;
    background: #fff; color: #000;
    padding: 1px 5px; border-radius: 2px;
    border: 1px solid #000; line-height: 1.3;
}

/* Badge servicio adicional */
.badge-srv {
    background: #fff; border: 1.5px solid #000; border-radius: 2px;
    padding: 2px 6px; display: inline-block; margin-bottom: 2px;
}
.badge-srv span { font-size: 9.5px; font-weight: 700; color: #000; letter-spacing: .2px; }

/* Detalle */
.detalle {
    background: #fff; border: 1.5px solid #000;
    border-radius: 2px; padding: 1px 6px;
    display: inline-block; margin-bottom: 2px;
}
.detalle span { font-size: 9px; font-weight: 700; color: #000; letter-spacing: .2px; }

/* Footer */
.footer { display: flex; justify-content: space-between; align-items: flex-end; margin-top: auto; }
.footer-val { font-size: 9.5px; font-weight: 700; color: #000; line-height: 1.2; }

/* QR */
.qr-wrap { width: 80px; height: 80px; flex-shrink: 0; }
.qr-wrap img, .qr-wrap canvas { width: 80px !important; height: 80px !important; }
.qr-code-txt {
    font-size: 7px; color: #222;
    text-align: center; line-height: 1.4;
    font-family: 'Courier New', monospace;
    margin: 2px 0;
}
.logo-img { width: 52px; height: auto; display: block; }

/* ── IMPRESIÓN ── */
@media print {
    body { background: white; padding: 0; }
    .toolbar { display: none; }
    .grid {
        gap: 3mm;
        padding: 3mm;
    }
    .etiqueta {
        /* 76.2mm x 50.8mm exacto */
        width: 76.2mm;
        height: 50.8mm;
        border: 0.4mm solid #000;
    }
    .folio   { font-size: 5.5mm; }
    .partida { font-size: 3.5mm; }
    .cristal-val { font-size: 3.5mm; }
    .medidas-val { font-size: 4mm; }
    .badge-cpb span, .badge-fm span, .badge-srv span { font-size: 3mm; }
    .chip    { font-size: 2.8mm; }
    .detalle span { font-size: 2.8mm; }
    .footer-val { font-size: 3mm; }
    .qr-wrap { width: 22mm; height: 22mm; }
    .qr-wrap img, .qr-wrap canvas { width: 22mm !important; height: 22mm !important; }
    .col-der { width: 26mm; }
    .qr-code-txt { font-size: 2mm; }
    .logo-img { width: 14mm; }
    @page { margin: 5mm; }
}
</style>
</head>
<body>

<div class="toolbar">
    <div>
        <h1>&#11041; APEX GLASS &mdash; Etiquetas de producción</h1>
        <div class="info">
            <?= htmlspecialchars($folio) ?> &bull;
            <?= htmlspecialchars($orden['cliente_nombre'] ?? '') ?> &bull;
            <?= count($piezas) ?> piezas
            <?php if ($fechaEntrega): ?> &bull; Entrega: <?= $fechaEntrega ?><?php endif; ?>
        </div>
    </div>
    <button class="btn-print" onclick="window.print()">&#128438; Imprimir</button>
</div>

<div class="grid" id="grid">
<?php foreach ($piezas as $p):
    // Determinar badge de canteado
    $cpbVal  = trim($p['cpb'] ?? '');
    $cpbUp   = strtoupper($cpbVal);
    // CPB → tiene valor real (no vacío, no NO, no FM)
    $esCPB   = ($cpbVal !== '' && $cpbUp !== 'NO' && $cpbUp !== 'FM' && $cpbUp !== 'FILO MUERTO');
    // FILO MUERTO → cuando NO hay CPB (vacío, NO, FM, o explícito)
    $esFM    = !$esCPB;

    // Chips de trabajos
    $chips = [];
    if ($p['resaques'] > 0)  $chips[] = $p['resaques'] . ' RES';
    if ($p['tp']       > 0)  $chips[] = $p['tp']       . ' TP';
    if ($p['ta']       > 0)  $chips[] = $p['ta']       . ' TA';
    if ($p['esmerilado'])     $chips[] = 'ESMER';
    if ($p['pintura'])        $chips[] = 'PINTURA';
    if ($p['acabado_forma'])  $chips[] = 'A.FORMA';

    // Detalle
    $detalle = trim($p['detalles'] ?? '');
    $hayDetalle = ($detalle !== '' && strtoupper($detalle) !== 'NO');

    // QR id único para el div
    $qrId = 'qr_' . $p['id'];

    // Medidas — restar 100mm en Plantilla/Diámetro (margen de protocolo)
    $anchoEtiq = (int)$p['ancho_mm'];
    $altoEtiq  = (int)$p['alto_mm'];
    if (in_array($detalle, ['Plantilla', 'Diámetro', 'Diametro'])) {
        $anchoEtiq = max(0, $anchoEtiq - 100);
        $altoEtiq  = max(0, $altoEtiq  - 100);
    }
    $medidas = $anchoEtiq . 'x' . $altoEtiq;
?>
<div class="etiqueta">

    <!-- Columna izquierda -->
    <div class="col-izq">

        <!-- Folio + partida/pieza -->
        <div class="folio"><?= htmlspecialchars($folio) ?></div>
        <div class="partida">P<?= $p['partida'] ?> &middot; <?= $p['pieza_num'] ?> de <?= $p['pieza_total'] ?></div>

        <hr class="divider">

        <!-- Cristal + medidas -->
        <div class="fila-cristal">
            <div>
                <div class="lbl">Cristal</div>
                <div class="cristal-val"><?= htmlspecialchars($p['cristal'] ?? '') ?></div>
            </div>
            <div>
                <div class="lbl" style="text-align:right">mm</div>
                <div class="medidas-val"><?= $medidas ?></div>
            </div>
        </div>

        <!-- Badge canteado -->
        <?php if ($esCPB): ?>
            <div class="badge-cpb"><span>CPB <?= strtoupper(htmlspecialchars($cpbVal)) ?></span></div>
        <?php endif; ?>
        <?php if ($esFM): ?>
            <div class="badge-fm"><span>FILO MUERTO</span></div>
        <?php endif; ?>

        <!-- Servicios adicionales -->
        <?php foreach ($serviciosPorPartida[(int)$p['partida']] ?? [] as $srvLabel): ?>
            <div class="badge-srv"><span><?= strtoupper(htmlspecialchars($srvLabel)) ?></span></div>
        <?php endforeach; ?>

        <!-- Chips trabajos -->
        <?php if (!empty($chips)): ?>
        <div class="chips">
            <?php foreach ($chips as $c): ?>
            <span class="chip"><?= $c ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Detalle -->
        <?php if ($hayDetalle): ?>
        <div class="detalle"><span><?= strtoupper(htmlspecialchars($detalle)) ?></span></div>
        <?php endif; ?>
        
        <!-- Comentarios -->
        <?php 
            $comentario = trim($p['comentarios'] ?? '');
             if ($comentario !== '' && strtoupper($comentario) !== 'NO'): ?>
             <div class="detalle" style="width:100%">
                <span><?= strtoupper(htmlspecialchars($comentario)) ?></span>
            </div>
        <?php endif; ?>
        

        <!-- Footer cliente + entrega -->
        <div class="footer" style="margin-top:auto;">
            <div>
                <div class="lbl">Cliente</div>
                <div class="footer-val"><?= htmlspecialchars(substr($orden['cliente_nombre'] ?? '', 0, 14)) ?></div>
            </div>
            <?php if ($fechaEntrega): ?>
            <div style="text-align:right">
                <div class="lbl">Entrega</div>
                <div class="footer-val"><?= $fechaEntrega ?></div>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Columna derecha: QR + código + logo -->
    <div class="col-der">
        <div class="qr-wrap" id="<?= $qrId ?>"></div>
        <div class="qr-code-txt"><?= htmlspecialchars($p['qr_code']) ?></div>
        <img class="logo-img" src="../logoAG.png" alt="APEX GLASS">
    </div>

</div>
<?php endforeach; ?>
</div>

<script>
document.querySelectorAll('.qr-wrap').forEach(function(el) {
    var qrText = 'https://apex.glass/produccion/app/operador.php?qr=' + encodeURIComponent(el.id.replace('qr_',''));
    // Buscar el texto del código que está en el div hermano
    var codeTxt = el.nextElementSibling ? el.nextElementSibling.textContent.trim() : '';
    if (codeTxt) {
        qrText = 'https://apex.glass/produccion/app/operador.php?qr=' + encodeURIComponent(codeTxt);
    }
    new QRCode(el, {
        text: qrText,
        width: 80,
        height: 80,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>

</body>
</html>