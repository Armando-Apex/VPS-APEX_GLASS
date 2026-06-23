<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user = requirePermiso('ver_ordenes');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { echo "ID requerido"; exit; }

$db = getDB();
$stmt = $db->prepare("SELECT * FROM croquis_partidas WHERE id = ?");
$stmt->execute([$id]);
$cq = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cq) { echo "Croquis no encontrado"; exit; }

$stmt2 = $db->prepare("
    SELECT c.folio, c.cliente_nombre, c.proyecto, o.folio AS orden_folio
    FROM cotizaciones c
    LEFT JOIN ordenes o ON o.id = c.orden_id
    WHERE c.id = ?
");
$stmt2->execute([$cq['cotizacion_id']]);
$cot = $stmt2->fetch(PDO::FETCH_ASSOC) ?: [];

$stmt3 = $db->prepare("SELECT * FROM cotizaciones_partidas WHERE cotizacion_id = ? AND num_partida = ?");
$stmt3->execute([$cq['cotizacion_id'], $cq['num_partida']]);
$partida = $stmt3->fetch(PDO::FETCH_ASSOC) ?: [];

$forma     = $cq['forma'];
$ancho     = (float)$cq['ancho_mm'];
$alto      = (float)$cq['alto_mm'];
$params    = $cq['params_forma'] ? json_decode($cq['params_forma'], true) : [];
$elementos = $cq['elementos']    ? json_decode($cq['elementos'], true)    : [];
$canteo    = $cq['canteo']       ? json_decode($cq['canteo'], true)       : [];
$notas     = $cq['notas'] ?? '';

if (!is_array($params))    $params    = [];
if (!is_array($elementos)) $elementos = [];
if (!is_array($canteo))    $canteo    = [];

// ── Geometría (mismo cálculo que croquis.php _getOrigin) ──────────────────
$SVG_W = 760; $SVG_H = 960;
$cOff_geom = 32; // mismo que $cOff más abajo — usado para calcular MB dinámico
$rowStep    = 14 * ($SVG_W / 450);
$extraFilas = max(0, count($elementos) - 1);
$hasEl      = count($elementos) > 0;
// canvas más ancho cuando hay elementos para acomodar tabla lateral (proporcional a 450→560)
$canvW = $hasEl ? $SVG_W + round(120 * $SVG_W / 450) : $SVG_W;
// MB crece con el número de elementos para que todas las cotas X quepan debajo del vidrio
$cxBase_geom = $cOff_geom + 14 + 12; // offset de la primera cota X de elemento (igual a $cxBase en el foreach)
$ML = 110; $MR = 80 + $extraFilas*$rowStep + ($hasEl ? round(130 * $SVG_W / 450) : 0); $MT = 20;
$MB = max(140, (int)ceil($cxBase_geom + count($elementos) * $rowStep + 24));
$sc = min(($canvW - $ML - $MR) / max($ancho, 1), ($SVG_H - $MT - $MB) / max($alto, 1));
$gw = $ancho * $sc;
$gh = $alto  * $sc;
$ox = $ML + ($canvW - $ML - $MR - $gw) / 2;
$oy = $MT  + ($SVG_H - $MT - $MB - $gh) / 2;
$oyBottom = $oy + $gh;

function toPX($mmX, $mmY, $ox, $oyBottom, $sc) {
    return ['x' => $ox + $mmX * $sc, 'y' => $oyBottom - $mmY * $sc];
}

function buildPath($forma, $params, $ox, $oy, $gw, $gh, $ancho, $alto, $sc) {
    if ($forma === 'rect') {
        return "M$ox $oy L".($ox+$gw)." $oy L".($ox+$gw)." ".($oy+$gh)." L$ox ".($oy+$gh)." Z";
    }
    if ($forma === 'corte') {
        $cx  = min((float)($params['corte-x'] ?? 150), $ancho*0.4) * $sc;
        $cy  = min((float)($params['corte-y'] ?? 150), $alto*0.4)  * $sc;
        $esq = $params['corte-esq'] ?? ['si'=>true,'sd'=>false,'ii'=>false,'id'=>false];
        $eSI = !empty($esq['si']); $eSD = !empty($esq['sd']);
        $eII = !empty($esq['ii']); $eID = !empty($esq['id']);
        $d  = $eSI ? "M".($ox+$cx)." $oy " : "M$ox $oy ";
        $d .= $eSD ? "L".($ox+$gw-$cx)." $oy L".($ox+$gw)." ".($oy+$cy)." " : "L".($ox+$gw)." $oy ";
        $d .= $eID ? "L".($ox+$gw)." ".($oy+$gh-$cy)." L".($ox+$gw-$cx)." ".($oy+$gh)." " : "L".($ox+$gw)." ".($oy+$gh)." ";
        $d .= $eII ? "L".($ox+$cx)." ".($oy+$gh)." L$ox ".($oy+$gh-$cy)." " : "L$ox ".($oy+$gh)." ";
        if ($eSI) $d .= "L$ox ".($oy+$cy)." ";
        return $d . 'Z';
    }
    if ($forma === 'L') {
        $lw = min((float)($params['l-cw'] ?? 200), $ancho*0.7) * $sc;
        $lh = min((float)($params['l-ch'] ?? 200), $alto*0.7)  * $sc;
        return "M$ox $oy L".($ox+$lw)." $oy L".($ox+$lw)." ".($oy+$lh)." L".($ox+$gw)." ".($oy+$lh)." L".($ox+$gw)." ".($oy+$gh)." L$ox ".($oy+$gh)." Z";
    }
    if ($forma === 'trap') {
        $tb  = min((float)($params['trap-b'] ?? 500), $ancho-10) * $sc;
        $off = ($gw - $tb) / 2;
        return "M".($ox+$off)." $oy L".($ox+$gw-$off)." $oy L".($ox+$gw)." ".($oy+$gh)." L$ox ".($oy+$gh)." Z";
    }
    if ($forma === 'poligono') {
        $puntos = $params['puntos'] ?? [];
        if (count($puntos) < 3) return '';
        $d = '';
        foreach ($puntos as $i => $p) {
            $px = $ox + (float)$p['x'] * $sc;
            $py = ($oy + $gh) - (float)$p['y'] * $sc;
            $d .= ($i === 0 ? "M$px $py " : "L$px $py ");
        }
        return $d . 'Z';
    }
    return '';
}

$fz=14; $fzSm=12; $sw='1.2'; $tk=5; $cOff=32; $cxOff=28;
$arwSz=5; $arwLen=10; $lblW=52; $lblH=16; $lblWEj=52; $rotW=14; $rotH=52;

$svg  = '';
$uid  = 'cq' . $id;
$svg .= '<defs><pattern id="g'.$uid.'" width="'.($sc*10).'" height="'.($sc*10).'" patternUnits="userSpaceOnUse" x="'.$ox.'" y="'.$oy.'"><path d="M '.($sc*10).' 0 L 0 0 0 '.($sc*10).'" fill="none" stroke="#cccccc" stroke-width="0.4"/></pattern></defs>';
$svg .= '<rect width="'.$canvW.'" height="'.$SVG_H.'" fill="white"/>';

$sp = buildPath($forma, $params, $ox, $oy, $gw, $gh, $ancho, $alto, $sc);
$svg .= '<clipPath id="cl'.$uid.'"><path d="'.$sp.'"/></clipPath>';
$svg .= '<rect x="'.$ox.'" y="'.$oy.'" width="'.$gw.'" height="'.$gh.'" fill="url(#g'.$uid.')" clip-path="url(#cl'.$uid.')"/>';
$svg .= '<path d="'.$sp.'" fill="#f0f0f0" fill-opacity="1" stroke="#1a1a1a" stroke-width="1.5"/>';

$cs = 'stroke:#222222;stroke-width:3;stroke-linecap:round;stroke-dasharray:6,3';
if (!empty($canteo['sup'])) $svg .= '<line x1="'.$ox.'" y1="'.$oy.'" x2="'.($ox+$gw).'" y2="'.$oy.'" style="'.$cs.'"/>';
if (!empty($canteo['inf'])) $svg .= '<line x1="'.$ox.'" y1="'.$oyBottom.'" x2="'.($ox+$gw).'" y2="'.$oyBottom.'" style="'.$cs.'"/>';
if (!empty($canteo['izq'])) $svg .= '<line x1="'.$ox.'" y1="'.$oy.'" x2="'.$ox.'" y2="'.$oyBottom.'" style="'.$cs.'"/>';
if (!empty($canteo['der'])) $svg .= '<line x1="'.($ox+$gw).'" y1="'.$oy.'" x2="'.($ox+$gw).'" y2="'.$oyBottom.'" style="'.$cs.'"/>';

// Cota ancho
$svg .= '<line x1="'.$ox.'" y1="'.$oyBottom.'" x2="'.$ox.'" y2="'.($oyBottom+$cOff+2).'" stroke="#bbbbbb" stroke-width="0.5" stroke-dasharray="2,2"/>';
$svg .= '<line x1="'.($ox+$gw).'" y1="'.$oyBottom.'" x2="'.($ox+$gw).'" y2="'.($oyBottom+$cOff+2).'" stroke="#bbbbbb" stroke-width="0.5" stroke-dasharray="2,2"/>';
$svg .= '<line x1="'.$ox.'" y1="'.($oyBottom+$cOff).'" x2="'.($ox+$gw).'" y2="'.($oyBottom+$cOff).'" stroke="#222222" stroke-width="'.$sw.'"/>';
$svg .= '<line x1="'.$ox.'" y1="'.($oyBottom+$cOff-$tk).'" x2="'.$ox.'" y2="'.($oyBottom+$cOff+$tk).'" stroke="#222222" stroke-width="'.$sw.'"/>';
$svg .= '<line x1="'.($ox+$gw).'" y1="'.($oyBottom+$cOff-$tk).'" x2="'.($ox+$gw).'" y2="'.($oyBottom+$cOff+$tk).'" stroke="#222222" stroke-width="'.$sw.'"/>';
$svg .= '<rect x="'.($ox+$gw/2-$lblW/2).'" y="'.($oyBottom+$cOff-$lblH/2).'" width="'.$lblW.'" height="'.$lblH.'" fill="white"/>';
$svg .= '<text x="'.($ox+$gw/2).'" y="'.($oyBottom+$cOff+$fz/2-1).'" text-anchor="middle" font-size="'.$fz.'" font-weight="700" fill="#1e293b" font-family="monospace">'.$ancho.' mm</text>';

// Cota alto
$svg .= '<line x1="'.$ox.'" y1="'.$oy.'" x2="'.($ox-$cxOff-2).'" y2="'.$oy.'" stroke="#bbbbbb" stroke-width="0.5" stroke-dasharray="2,2"/>';
$svg .= '<line x1="'.$ox.'" y1="'.$oyBottom.'" x2="'.($ox-$cxOff-2).'" y2="'.$oyBottom.'" stroke="#bbbbbb" stroke-width="0.5" stroke-dasharray="2,2"/>';
$svg .= '<line x1="'.($ox-$cxOff).'" y1="'.$oy.'" x2="'.($ox-$cxOff).'" y2="'.$oyBottom.'" stroke="#222222" stroke-width="'.$sw.'"/>';
$svg .= '<line x1="'.($ox-$cxOff-$tk).'" y1="'.$oy.'" x2="'.($ox-$cxOff+$tk).'" y2="'.$oy.'" stroke="#222222" stroke-width="'.$sw.'"/>';
$svg .= '<line x1="'.($ox-$cxOff-$tk).'" y1="'.$oyBottom.'" x2="'.($ox-$cxOff+$tk).'" y2="'.$oyBottom.'" stroke="#222222" stroke-width="'.$sw.'"/>';
$svg .= '<rect x="'.($ox-$cxOff-$rotW/2).'" y="'.($oy+$gh/2-$rotH/2).'" width="'.$rotW.'" height="'.$rotH.'" fill="white"/>';
$svg .= '<text x="'.($ox-$cxOff).'" y="'.($oy+$gh/2).'" text-anchor="middle" font-size="'.$fz.'" font-weight="700" fill="#1e293b" font-family="monospace" transform="rotate(-90,'.($ox-$cxOff).','.($oy+$gh/2).')">'.$alto.' mm</text>';

// Eje X
$ejXY = $oyBottom + $cOff + 14;
$svg .= '<line x1="'.$ox.'" y1="'.$ejXY.'" x2="'.($ox+$gw).'" y2="'.$ejXY.'" stroke="#dc2626" stroke-width="1.1"/>';
$svg .= '<line x1="'.$ox.'" y1="'.($ejXY-$tk).'" x2="'.$ox.'" y2="'.($ejXY+$tk).'" stroke="#dc2626" stroke-width="1.1"/>';
$svg .= '<polygon points="'.($ox+$gw).','.($ejXY-$arwSz).' '.($ox+$gw+$arwLen).','.$ejXY.' '.($ox+$gw).','.($ejXY+$arwSz).'" fill="#dc2626"/>';
$svg .= '<rect x="'.($ox+$gw/2-$lblWEj/2).'" y="'.($ejXY-$lblH/2).'" width="'.$lblWEj.'" height="'.$lblH.'" fill="white"/>';
$svg .= '<text x="'.($ox+$gw/2).'" y="'.($ejXY+$fz/2-1).'" text-anchor="middle" font-size="'.$fz.'" font-weight="700" fill="#dc2626" font-family="monospace">Eje X</text>';

// Eje Y
$ejYX = $ox - $cxOff - 24;
$svg .= '<line x1="'.$ejYX.'" y1="'.$oyBottom.'" x2="'.$ejYX.'" y2="'.$oy.'" stroke="#16a34a" stroke-width="1.1"/>';
$svg .= '<line x1="'.($ejYX-$tk).'" y1="'.$oyBottom.'" x2="'.($ejYX+$tk).'" y2="'.$oyBottom.'" stroke="#16a34a" stroke-width="1.1"/>';
$svg .= '<polygon points="'.($ejYX-$arwSz).','.$oy.' '.$ejYX.','.($oy-$arwLen).' '.($ejYX+$arwSz).','.$oy.'" fill="#16a34a"/>';
$svg .= '<rect x="'.($ejYX-$rotW/2).'" y="'.($oy+$gh/2-$rotH/2).'" width="'.$rotW.'" height="'.$rotH.'" fill="white"/>';
$svg .= '<text x="'.$ejYX.'" y="'.($oy+$gh/2).'" text-anchor="middle" font-size="'.$fz.'" font-weight="700" fill="#16a34a" font-family="monospace" transform="rotate(-90,'.$ejYX.','.($oy+$gh/2).')">Eje Y</text>';

// Elementos
foreach ($elementos as $idxEl => $e) {
    $ep = toPX((float)$e['x'], (float)$e['y'], $ox, $oyBottom, $sc);
    $ex = $ep['x']; $ey = $ep['y'];

    $svg .= '<line x1="'.$ox.'" y1="'.$ey.'" x2="'.$ex.'" y2="'.$ey.'" stroke="#dc2626" stroke-width="0.6" stroke-dasharray="4,3" opacity="0.6"/>';
    $svg .= '<line x1="'.$ex.'" y1="'.$oyBottom.'" x2="'.$ex.'" y2="'.$ey.'" stroke="#16a34a" stroke-width="0.6" stroke-dasharray="4,3" opacity="0.6"/>';
    $svg .= '<circle cx="'.$ox.'" cy="'.$ey.'" r="2.5" fill="#dc2626" opacity="0.7"/>';
    $svg .= '<circle cx="'.$ex.'" cy="'.$oyBottom.'" r="2.5" fill="#16a34a" opacity="0.7"/>';

    // Cota X debajo del vidrio (fila por elemento, después del Eje X)
    $cxBase = $cOff + 14 + 12;
    $cxPad  = $cxBase + $idxEl*$rowStep; $cxLblW=44; $cxLblH=12;
    $svg .= '<line x1="'.$ox.'" y1="'.($oyBottom+$cxPad).'" x2="'.$ex.'" y2="'.($oyBottom+$cxPad).'" stroke="#dc2626" stroke-width="'.$sw.'"/>';
    $svg .= '<line x1="'.$ox.'" y1="'.($oyBottom+$cxPad-$tk/2).'" x2="'.$ox.'" y2="'.($oyBottom+$cxPad+$tk/2).'" stroke="#dc2626" stroke-width="'.$sw.'"/>';
    $svg .= '<line x1="'.$ex.'" y1="'.($oyBottom+$cxPad-$tk/2).'" x2="'.$ex.'" y2="'.($oyBottom+$cxPad+$tk/2).'" stroke="#dc2626" stroke-width="'.$sw.'"/>';
    $lxMid = $ox + ($ex-$ox)/2;
    $svg .= '<rect x="'.($lxMid-$cxLblW/2).'" y="'.($oyBottom+$cxPad+2).'" width="'.$cxLblW.'" height="'.$cxLblH.'" fill="white" rx="2"/>';
    $svg .= '<text x="'.$lxMid.'" y="'.($oyBottom+$cxPad+$cxLblH/2+$fzSm/2).'" text-anchor="middle" font-size="'.$fzSm.'" font-weight="700" fill="#dc2626" font-family="monospace">X: '.$e['x'].' mm</text>';

    // Cota Y a la derecha del vidrio (columna por elemento)
    $cyPad=6 + $idxEl*$rowStep; $cyLblW=12; $cyLblH=44;
    $svg .= '<line x1="'.($ox+$gw+$cyPad).'" y1="'.$oyBottom.'" x2="'.($ox+$gw+$cyPad).'" y2="'.$ey.'" stroke="#16a34a" stroke-width="'.$sw.'"/>';
    $svg .= '<line x1="'.($ox+$gw+$cyPad-$tk/2).'" y1="'.$oyBottom.'" x2="'.($ox+$gw+$cyPad+$tk/2).'" y2="'.$oyBottom.'" stroke="#16a34a" stroke-width="'.$sw.'"/>';
    $svg .= '<line x1="'.($ox+$gw+$cyPad-$tk/2).'" y1="'.$ey.'" x2="'.($ox+$gw+$cyPad+$tk/2).'" y2="'.$ey.'" stroke="#16a34a" stroke-width="'.$sw.'"/>';
    $lyMid = $ey + ($oyBottom-$ey)/2;
    $svg .= '<rect x="'.($ox+$gw+$cyPad+2).'" y="'.($lyMid-$cyLblH/2).'" width="'.$cyLblW.'" height="'.$cyLblH.'" fill="white" rx="2"/>';
    $svg .= '<text x="'.($ox+$gw+$cyPad+$cyLblW/2+2).'" y="'.$lyMid.'" text-anchor="middle" font-size="'.$fzSm.'" font-weight="700" fill="#16a34a" font-family="monospace" transform="rotate(-90,'.($ox+$gw+$cyPad+$cyLblW/2+2).','.$lyMid.')">Y: '.$e['y'].' mm</text>';

    if ($e['tipo'] === 'tp') {
        $r = max(4, ((float)$e['d']/2) * $sc);
        $svg .= '<circle cx="'.$ex.'" cy="'.$ey.'" r="'.$r.'" fill="white" stroke="#1e40af" stroke-width="1.5"/>';
        $svg .= '<line x1="'.($ex-$r*1.3).'" y1="'.$ey.'" x2="'.($ex+$r*1.3).'" y2="'.$ey.'" stroke="#1e40af" stroke-width="1"/>';
        $svg .= '<line x1="'.$ex.'" y1="'.($ey-$r*1.3).'" x2="'.$ex.'" y2="'.($ey+$r*1.3).'" stroke="#1e40af" stroke-width="1"/>';
        $lx=$ex+$r+4; $ly=$ey-$r-4;
        $svg .= '<rect x="'.($lx-1).'" y="'.($ly-$fz).'" width="62" height="'.($fz+3).'" fill="white" rx="2"/>';
        $svg .= '<text x="'.$lx.'" y="'.$ly.'" font-size="'.$fzSm.'" font-weight="700" fill="#1e40af" font-family="monospace">TP  &#216;'.$e['d'].' mm</text>';
    }
    if ($e['tipo'] === 'ta') {
        $re = max(5, ((float)$e['de']/2) * $sc);
        $ri = max(2, ((float)$e['di']/2) * $sc);
        $svg .= '<circle cx="'.$ex.'" cy="'.$ey.'" r="'.$re.'" fill="white" stroke="#7c3aed" stroke-width="1.5"/>';
        $svg .= '<circle cx="'.$ex.'" cy="'.$ey.'" r="'.$ri.'" fill="none" stroke="#7c3aed" stroke-width="0.8" stroke-dasharray="2,1.5"/>';
        $lx=$ex+$re+4; $ly=$ey-$re-4;
        $svg .= '<rect x="'.($lx-1).'" y="'.($ly-$fz).'" width="76" height="'.($fz+3).'" fill="white" rx="2"/>';
        $svg .= '<text x="'.$lx.'" y="'.$ly.'" font-size="'.$fzSm.'" font-weight="700" fill="#7c3aed" font-family="monospace">TA  &#216;'.$e['de'].'/'.$e['di'].' mm</text>';
    }
    if ($e['tipo'] === 'rs') {
        $rw = max(8, (float)$e['h']*$sc);
        $rh = max(4, (float)$e['w']*$sc);
        $exD = min($ex, $ox+$gw-$rw);
        $rySVG = max($ey - $rh, $oy);
        $colRS = '#854d0e';
        $svg .= '<rect x="'.$exD.'" y="'.$rySVG.'" width="'.$rw.'" height="'.$rh.'" fill="#fef9c3" fill-opacity="0.85" stroke="#854d0e" stroke-width="1.2" stroke-dasharray="3,2"/>';
        $svg .= '<line x1="'.$exD.'" y1="'.($rySVG-9).'" x2="'.($exD+$rw).'" y2="'.($rySVG-9).'" stroke="'.$colRS.'" stroke-width="0.8"/>';
        $svg .= '<line x1="'.$exD.'" y1="'.($rySVG-12).'" x2="'.$exD.'" y2="'.($rySVG-6).'" stroke="'.$colRS.'" stroke-width="0.8"/>';
        $svg .= '<line x1="'.($exD+$rw).'" y1="'.($rySVG-12).'" x2="'.($exD+$rw).'" y2="'.($rySVG-6).'" stroke="'.$colRS.'" stroke-width="0.8"/>';
        $svg .= '<rect x="'.($exD+$rw/2-18).'" y="'.($rySVG-20).'" width="36" height="10" fill="white" rx="2"/>';
        $svg .= '<text x="'.($exD+$rw/2).'" y="'.($rySVG-12).'" text-anchor="middle" font-size="'.$fzSm.'" font-weight="700" fill="'.$colRS.'" font-family="monospace">'.$e['h'].' mm</text>';
        $svg .= '<line x1="'.($exD+$rw+9).'" y1="'.$rySVG.'" x2="'.($exD+$rw+9).'" y2="'.$ey.'" stroke="'.$colRS.'" stroke-width="0.8"/>';
        $svg .= '<line x1="'.($exD+$rw+6).'" y1="'.$rySVG.'" x2="'.($exD+$rw+12).'" y2="'.$rySVG.'" stroke="'.$colRS.'" stroke-width="0.8"/>';
        $svg .= '<line x1="'.($exD+$rw+6).'" y1="'.$ey.'" x2="'.($exD+$rw+12).'" y2="'.$ey.'" stroke="'.$colRS.'" stroke-width="0.8"/>';
        $lyRS = $rySVG + $rh/2;
        $svg .= '<rect x="'.($exD+$rw+12).'" y="'.($lyRS-16).'" width="10" height="32" fill="white" rx="2"/>';
        $svg .= '<text x="'.($exD+$rw+17).'" y="'.$lyRS.'" text-anchor="middle" font-size="'.$fzSm.'" font-weight="700" fill="'.$colRS.'" font-family="monospace" transform="rotate(-90,'.($exD+$rw+17).','.$lyRS.')">'.$e['w'].' mm</text>';
        $lxRS=$exD; $lyRS2=$rySVG-24;
        $svg .= '<rect x="'.($lxRS-1).'" y="'.($lyRS2-$fz).'" width="52" height="'.($fz+3).'" fill="white" rx="2"/>';
        $svg .= '<text x="'.$lxRS.'" y="'.$lyRS2.'" font-size="'.$fzSm.'" font-weight="700" fill="'.$colRS.'" font-family="monospace">RS  posici&#243;n</text>';
    }
    if ($e['tipo'] === 'bi') {
        $rw = max(8, (float)$e['h']*$sc);
        $rh = max(4, (float)$e['w']*$sc);
        $exD = min($ex, $ox+$gw-$rw);
        $rySVG = max($ey - $rh, $oy);
        $colBI = '#0f766e';
        $distR = $ancho - (float)$e['x']; $distL = (float)$e['x'];
        $distT = $alto  - (float)$e['y']; $distB = (float)$e['y'];
        $minD  = min($distR, $distL, $distT, $distB);
        $biRot = 0;
        if      ($minD === $distR) $biRot = 270;
        else if ($minD === $distT) $biRot = 180;
        else if ($minD === $distL) $biRot = 90;
        $cxR = $exD + $rw*0.5; $cyR = $rySVG + $rh*0.5;
        $cr1  = $rw * 0.28; $d45 = $cr1 * 0.7071;
        $lx1  = $exD - $d45; $rx1c = $exD + $rw + $d45; $eyC = $rySVG - $d45;
        $svg .= '<g transform="rotate('.$biRot.' '.$cxR.' '.$cyR.')">';
        $svg .= '<rect x="'.$exD.'" y="'.$rySVG.'" width="'.$rw.'" height="'.$rh.'" fill="#ccfbf1" fill-opacity="0.9" stroke="none"/>';
        $svg .= '<circle cx="'.$lx1.'" cy="'.$eyC.'" r="'.$cr1.'" fill="#ccfbf1" fill-opacity="0.9" stroke="none"/>';
        $svg .= '<circle cx="'.$rx1c.'" cy="'.$eyC.'" r="'.$cr1.'" fill="#ccfbf1" fill-opacity="0.9" stroke="none"/>';
        $svg .= '<path d="M '.$exD.' '.($rySVG+$rh).' L '.$exD.' '.$rySVG.' L '.($exD+$rw).' '.$rySVG.' L '.($exD+$rw).' '.($rySVG+$rh).'" fill="none" stroke="'.$colBI.'" stroke-width="1.5"/>';
        $svg .= '<circle cx="'.$lx1.'" cy="'.$eyC.'" r="'.$cr1.'" fill="none" stroke="'.$colBI.'" stroke-width="1.5"/>';
        $svg .= '<circle cx="'.$rx1c.'" cy="'.$eyC.'" r="'.$cr1.'" fill="none" stroke="'.$colBI.'" stroke-width="1.5"/>';
        $svg .= '</g>';
        $lxRS=$exD; $lyRS2=$rySVG-24;
        $svg .= '<rect x="'.($lxRS-1).'" y="'.($lyRS2-$fz).'" width="18" height="'.($fz+3).'" fill="white" rx="2"/>';
        $svg .= '<text x="'.$lxRS.'" y="'.$lyRS2.'" font-size="'.$fzSm.'" font-weight="700" fill="'.$colBI.'" font-family="monospace">BI</text>';
    }
}

// Tabla de elementos (a la derecha de las cotas Y, sin tapar la pieza)
if ($hasEl) {
    $tExtraFilas = max(0, count($elementos) - 1);
    $tblX = $ox + $gw + 6 + $tExtraFilas*$rowStep + 22;
    $tblW = min($canvW - $tblX - 4, round(140 * $SVG_W / 450));
    $tblY = $oy + 2;
    $cardH = round(28 * $SVG_W / 450);
    $eCol = ['tp'=>'#1e40af','ta'=>'#7c3aed','rs'=>'#854d0e','bi'=>'#0f766e'];
    $eBg  = ['tp'=>'#dbeafe','ta'=>'#f3e8ff','rs'=>'#fef9c3','bi'=>'#ccfbf1'];
    $svg .= '<text x="'.$tblX.'" y="'.$tblY.'" font-size="13" font-weight="700" fill="#64748b" font-family="sans-serif">ELEMENTOS</text>';
    foreach ($elementos as $i => $el) {
        $ec  = $eCol[$el['tipo']] ?? '#374151';
        $ebg = $eBg[$el['tipo']]  ?? '#f1f5f9';
        $ry  = $tblY + 18 + $i * $cardH;
        $svg .= '<rect x="'.$tblX.'" y="'.$ry.'" width="'.$tblW.'" height="'.($cardH-2).'" fill="#f8fafc" rx="3"/>';
        $svg .= '<rect x="'.$tblX.'" y="'.$ry.'" width="8" height="'.($cardH-2).'" fill="'.$ec.'" rx="3"/>';
        $svg .= '<text x="'.($tblX+13).'" y="'.($ry+18).'" font-size="15" font-weight="700" fill="'.$ec.'" font-family="monospace">'.($i+1).'. '.strtoupper($el['tipo']).'</text>';
        $det = '';
        if ($el['tipo']==='tp') $det = '&#216;'.$el['d'].'mm';
        if ($el['tipo']==='ta') $det = '&#216;'.$el['de'].'/'.$el['di'];
        if ($el['tipo']==='rs') $det = $el['w'].'&#215;'.$el['h'].'mm';
        if ($el['tipo']==='bi') $det = $el['w'].'&#215;'.$el['h'].'mm';
        $svg .= '<text x="'.($tblX+$tblW-2).'" y="'.($ry+18).'" text-anchor="end" font-size="14" fill="'.$ec.'" font-family="monospace">'.$det.'</text>';
        $svg .= '<text x="'.($tblX+12).'" y="'.($ry+$cardH-6).'" font-size="12" fill="#374151" font-family="monospace">X: '.$el['x'].'  Y: '.$el['y'].'</text>';
    }
}

// Canteado label
$lados = [];
if (!empty($canteo['sup'])) $lados[] = 'Sup';
if (!empty($canteo['inf'])) $lados[] = 'Inf';
if (!empty($canteo['izq'])) $lados[] = 'Izq';
if (!empty($canteo['der'])) $lados[] = 'Der';
if ($lados) {
    $svg .= '<text x="'.($canvW/2).'" y="'.($SVG_H-6).'" text-anchor="middle" font-size="'.$fz.'" fill="#92400e" font-family="monospace">Canteado: '.implode(' + ', $lados).'</text>';
}
if ($notas) {
    $svg .= '<text x="'.($canvW/2).'" y="'.($SVG_H-($lados ? $fz*2 : 6)).'" text-anchor="middle" font-size="'.$fzSm.'" fill="#64748b" font-family="sans-serif">'.htmlspecialchars(mb_substr($notas, 0, 65)).'</text>';
}

$folioMostrar = $cot['orden_folio'] ?: ($cot['folio'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Croquis P<?= (int)$cq['num_partida'] ?> — <?= htmlspecialchars($folioMostrar) ?> — APEX GLASS</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
@page { size: A4 portrait; margin: 10mm; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #1e293b; background: #fff; }
.page { width: 210mm; min-height: 277mm; margin: 0 auto; padding: 10mm; }
.header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1a1a2e; padding-bottom: 8px; margin-bottom: 12px; }
.header h1 { font-size: 18px; font-weight: 900; color: #1a1a2e; letter-spacing: .5px; }
.header .sub { font-size: 12px; color: #64748b; margin-top: 2px; }
.meta { text-align: right; font-size: 12px; color: #334155; line-height: 1.6; }
.meta b { color: #1a1a2e; }
.svg-wrap { border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px; display: flex; justify-content: center; }
.svg-wrap svg { width: 100%; height: auto; }
.footer-note { margin-top: 8px; font-size: 10px; color: #94a3b8; text-align: center; }
.btn-print { position: fixed; top: 10px; right: 10px; background: #16a34a; color: #fff; border: none; border-radius: 6px; padding: 8px 14px; font-size: 13px; font-weight: 700; cursor: pointer; }
@media print { .btn-print { display: none; } body { background: #fff; } }
</style>
</head>
<body>
<button class="btn-print" onclick="window.print()">&#128424; Imprimir</button>
<div class="page">
  <div class="header">
    <div>
      <h1>APEX GLASS</h1>
      <div class="sub">Croquis t&#233;cnico de corte</div>
    </div>
    <div class="meta">
      <div><b>Orden/Cot.:</b> <?= htmlspecialchars($folioMostrar) ?></div>
      <div><b>Cliente:</b> <?= htmlspecialchars($cot['cliente_nombre'] ?? '') ?></div>
      <div><b>Partida:</b> P<?= (int)$cq['num_partida'] ?><?= !empty($partida['cristal_nombre']) ? ' — '.htmlspecialchars($partida['cristal_nombre']) : '' ?></div>
      <div><b>Medidas:</b> <?= $ancho ?> &#215; <?= $alto ?> mm</div>
    </div>
  </div>
  <div class="svg-wrap">
    <svg width="<?= $canvW ?>" height="<?= $SVG_H ?>" viewBox="0 0 <?= $canvW ?> <?= $SVG_H ?>" style="max-width:100%"><?= $svg ?></svg>
  </div>
  <div class="footer-note">Generado <?= date('d/m/Y H:i') ?> &mdash; Este croquis puede ser editado en el sistema, este PDF refleja la &#250;ltima versi&#243;n guardada.</div>
</div>
</body>
</html>
