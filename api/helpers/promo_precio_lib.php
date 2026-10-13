<?php
// ============================================================
//  APEX GLASS - Helper: Promoción de PRECIO FIJO por m² (código)
//  Archivo: api/helpers/promo_precio_lib.php
//  Campaña WA "promo_saltillo_sep" (23-sep-2026, Coahuila).
//  Código SALT_SEP2026: fija el precio por m² de Claro 6mm y
//  Claro 9mm (catálogo, ids 1 y 2) a un precio neto especial.
//  Reglas confirmadas por Armando:
//   - Precio con IVA: Claro 6mm $585/m², Claro 9mm $835/m²
//     (se guardan sin IVA: 504.3103 / 719.8275).
//   - Las partidas en promo NO reciben ningún otro descuento
//     (manual/referido/encuesta/promo volumen) — esos % solo
//     aplican a las partidas que no están en la promoción.
//     Marcadas con cotizaciones_partidas.promo_precio = 1.
//   - Plantillas, Zafiro y los "Express" del catálogo NO entran.
//   - Aplicar/editar/convertir a orden: hasta 30-sep-2026 23:59:59.
//   - VoBo de la orden: hasta 05-oct-2026 23:59:59; después se
//     bloquea hasta quitar la promo (precio vuelve a catálogo).
// ============================================================

const PROMO_PRECIO_CODIGOS = [
    'SALT_SEP2026' => [
        'precios'     => [1 => 504.3103, 2 => 719.8275], // cristal_id => precio_m2 sin IVA
        'fin'         => '2026-09-30 23:59:59',           // último momento para aplicar/editar/convertir
        'vobo_limite' => '2026-10-05 23:59:59',           // último momento para dar VoBo
    ],
    'MTY_SEP2026' => [
        'precios'     => [1 => 517.2414, 2 => 719.8276], // $600 / $835 con IVA
        'fin'         => '2026-09-30 23:59:59',
        'vobo_limite' => '2026-10-05 23:59:59',
    ],
    // UPD-668 (05-oct-2026): código por NIVELES — el sistema decide el precio según
    // los m² y piezas de Claro 6mm/9mm de la cotización (ver promoPrecioNivel):
    //   volumen  (>= 50 m² sumando 6mm+9mm)                 → $615 / $870 con IVA
    //   express  (hasta 4 piezas y casilla "Entrega Express") → $745 / $1,050
    //   normal   (todo lo demás, entrega en 5 días)           → $670 / $945
    'RAZA_OCT2026' => [
        'niveles'     => [
            'express' => [1 => 642.2414, 2 => 905.1724],
            'normal'  => [1 => 577.5862, 2 => 814.6552],
            'volumen' => [1 => 530.1724, 2 => 750.0000],
        ],
        'volumen_m2'  => 50,
        'express_max_piezas' => 4,
        'fin'         => '2026-10-12 18:00:00',
        'vobo_limite' => '2026-10-17 23:59:59',
    ],
];

const PROMO_PRECIO_NIVEL_TEXTO = [
    'express' => 'Entrega Express',
    'normal'  => 'Entrega en 5 días hábiles',
    'volumen' => 'Precio por volumen (50 m² o más)',
];

// Nivel que le toca a una cotización con un código por niveles, o null si el código
// es de precio plano. $partidas = arreglo crudo del body (cristal_id/cantidad/ancho/
// alto/lamina_id). Solo cuentan las partidas de los cristales que entran en la promo.
function promoPrecioNivel($codigo, array $partidas, $express) {
    if (!$codigo || empty(PROMO_PRECIO_CODIGOS[$codigo]['niveles'])) return null;
    $cfg = PROMO_PRECIO_CODIGOS[$codigo];
    $ids = array_keys($cfg['niveles']['normal']);
    $m2 = 0; $piezas = 0;
    foreach ($partidas as $p) {
        $cid = (int)($p['cristal_id'] ?? 0);
        $an  = (int)($p['ancho'] ?? 0);
        $al  = (int)($p['alto'] ?? 0);
        if (!in_array($cid, $ids, true) || $an <= 0 || $al <= 0 || (int)($p['lamina_id'] ?? 0)) continue;
        $cant = max(1, (int)($p['cantidad'] ?? 1));
        $m2     += ($an / 1000) * ($al / 1000) * $cant;
        $piezas += $cant;
    }
    if ($m2 >= $cfg['volumen_m2'] - 0.000001) return 'volumen';
    if ($express && $piezas > 0 && $piezas <= $cfg['express_max_piezas']) return 'express';
    return 'normal';
}

// Normaliza y valida el código. Devuelve ['error'=>?string, 'codigo'=>?string].
function promoPrecioValidar($codigo) {
    $codigo = strtoupper(trim((string)$codigo));
    if ($codigo === '') return ['error' => null, 'codigo' => null];
    if (!isset(PROMO_PRECIO_CODIGOS[$codigo])) {
        return ['error' => 'El código de promoción "' . $codigo . '" no existe.', 'codigo' => null];
    }
    if (date('Y-m-d H:i:s') > PROMO_PRECIO_CODIGOS[$codigo]['fin']) {
        return ['error' => promoPrecioMsgVencida($codigo), 'codigo' => null];
    }
    return ['error' => null, 'codigo' => $codigo];
}

function promoPrecioVigente($codigo) {
    return $codigo && isset(PROMO_PRECIO_CODIGOS[$codigo])
        && date('Y-m-d H:i:s') <= PROMO_PRECIO_CODIGOS[$codigo]['fin'];
}

function promoPrecioVoboVigente($codigo) {
    return $codigo && isset(PROMO_PRECIO_CODIGOS[$codigo])
        && date('Y-m-d H:i:s') <= PROMO_PRECIO_CODIGOS[$codigo]['vobo_limite'];
}

function promoPrecioMsgVencida($codigo) {
    $fin = isset(PROMO_PRECIO_CODIGOS[$codigo]) ? date('d-m-Y', strtotime(PROMO_PRECIO_CODIGOS[$codigo]['fin'])) : '';
    return 'La promoción ' . $codigo . ' venció el ' . $fin . ', ya no es viable el descuento especial. Quita el código para continuar (el precio regresa al de catálogo).';
}

function promoPrecioMsgVoboVencida($codigo) {
    $lim = isset(PROMO_PRECIO_CODIGOS[$codigo]) ? date('d-m-Y', strtotime(PROMO_PRECIO_CODIGOS[$codigo]['vobo_limite'])) : '';
    return 'La promoción ' . $codigo . ' requería VoBo a más tardar el ' . $lim . ', ya no es viable el descuento especial. Hay que quitar la promoción (el precio regresa al de catálogo) antes de dar VoBo.';
}

// Precio promo para un cristal, o null si el cristal no entra en la promo.
function promoPrecioDe($codigo, $cristal_id, $nivel = null) {
    if (!$codigo || !isset(PROMO_PRECIO_CODIGOS[$codigo])) return null;
    $cfg = PROMO_PRECIO_CODIGOS[$codigo];
    if (!empty($cfg['niveles'])) return $cfg['niveles'][$nivel ?: 'normal'][(int)$cristal_id] ?? null;
    return $cfg['precios'][(int)$cristal_id] ?? null;
}

// Quita la promo de una cotización/orden ya guardada: partidas en promo regresan
// al precio de catálogo, se recalculan partidas + encabezado con la fórmula
// canónica y se deja registro en correcciones_log. Debe llamarse dentro de una
// transacción abierta por el caller. Requiere helpers/totales.php cargado.
function promoPrecioQuitar(PDO $db, $cot_id, $usuario_nombre) {
    $st = $db->prepare("SELECT id, folio, promo_precio_codigo, condicion_pago, COALESCE(saldo_pagado,0) AS saldo_pagado,
                               descuento, COALESCE(descuento_referido,0) AS dr, COALESCE(descuento_encuesta,0) AS de
                        FROM cotizaciones WHERE id = ? FOR UPDATE");
    $st->execute([(int)$cot_id]);
    $cot = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cot || !$cot['promo_precio_codigo']) return false;
    $desc = min(100, (float)$cot['descuento'] + (float)$cot['dr'] + (float)$cot['de']);

    $stP = $db->prepare("SELECT cp.id, cp.m2, cp.cantidad, cr.precio_m2
                         FROM cotizaciones_partidas cp JOIN cristales cr ON cr.id = cp.cristal_id
                         WHERE cp.cotizacion_id = ? AND cp.promo_precio = 1");
    $stP->execute([(int)$cot_id]);
    $upd = $db->prepare("UPDATE cotizaciones_partidas SET promo_precio=0, precio_m2_usado=?, precio_unitario=?, subtotal=?, iva=?, total=? WHERE id=?");
    foreach ($stP->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $pu  = round((float)$p['m2'] * (float)$p['precio_m2'] * (1 - $desc / 100), 4);
        $sub = round($pu * (int)$p['cantidad'], 2);
        $iva = round($sub * 0.16, 2);
        $upd->execute([(float)$p['precio_m2'], $pu, $sub, $iva, round($sub + $iva, 2), $p['id']]);
    }

    $tots = apexTotalesCotizacion($db, (int)$cot_id);
    $saldoBase = ($cot['condicion_pago'] === 'anticipo') ? round($tots['total'] * 0.5, 2) : $tots['total'];
    $db->prepare("UPDATE cotizaciones SET promo_precio_codigo=NULL, subtotal=?, iva=?, total=?, saldo_pendiente=?, updated_at=NOW() WHERE id=?")
       ->execute([$tots['subtotal'], $tots['iva'], $tots['total'], max(0, round($saldoBase - (float)$cot['saldo_pagado'], 2)), (int)$cot_id]);

    try {
        $db->prepare("INSERT INTO correcciones_log (tipo, referencia_id, folio, campo, valor_anterior, valor_nuevo, usuario, motivo, fecha) VALUES ('cotizacion', ?, ?, 'promo_precio_codigo', ?, '', ?, 'Promoción vencida — precio regresa a catálogo', NOW())")
           ->execute([(int)$cot_id, $cot['folio'], $cot['promo_precio_codigo'], $usuario_nombre]);
    } catch (Exception $e) { error_log('[promo_precio] correcciones_log: ' . $e->getMessage()); }
    return true;
}
