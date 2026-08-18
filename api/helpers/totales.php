<?php
// ============================================================
//  APEX GLASS - Helpers: fórmula canónica de totales
//  Archivo: api/helpers/totales.php
//  A-2: UNA sola fórmula para cotización, cobranza, portal,
//  reportes e impresión.
//  Reglas de negocio:
//   - El descuento (%) aplica SOLO a las partidas, NUNCA a servicios.
//   - IVA 16% sobre (subtotal neto + servicios).
//   - Maquila: el "bruto" es SUM(cotizaciones_maquila_partidas.subtotal).
// ============================================================

// ─── Fórmula pura: bruto + descuento + servicios → totales ───────────────────
function apexTotales($bruto_partidas, $descuento_pct, $servicios_subtotal) {
    $subtotal = round((float)$bruto_partidas * (1 - (float)$descuento_pct / 100), 2);
    $base     = round($subtotal + (float)$servicios_subtotal, 2);
    $iva      = round($base * 0.16, 2);
    return [
        'subtotal'  => $subtotal,                          // partidas netas (con descuento), sin IVA
        'servicios' => round((float)$servicios_subtotal, 2),
        'base'      => $base,                              // base gravable = neto + servicios
        'iva'       => $iva,
        'total'     => round($base + $iva, 2),
    ];
}

// ─── Totales de una cotización ya guardada (lee BD, ramifica por tipo) ───────
// Devuelve null si la cotización no existe.
function apexTotalesCotizacion(PDO $db, $cotizacion_id) {
    $stmt = $db->prepare("SELECT tipo, descuento, COALESCE(descuento_referido,0) AS descuento_referido, COALESCE(servicios_subtotal,0) AS servicios_subtotal
                          FROM cotizaciones WHERE id = ?");
    $stmt->execute([(int)$cotizacion_id]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cot) return null;

    if (($cot['tipo'] ?? '') === 'maquila') {
        // Maquila: las partidas viven en cotizaciones_maquila_partidas y
        // ya traen su subtotal calculado por api/maquila.php (sin descuento,
        // ni siquiera el manual — el descuento de maquila se aplica distinto,
        // a nivel partida en api/maquila.php). Referidos: mismo criterio,
        // no aplica por esta vía para maquila.
        $st = $db->prepare("SELECT COALESCE(SUM(subtotal),0) FROM cotizaciones_maquila_partidas WHERE cotizacion_id = ?");
        $st->execute([(int)$cotizacion_id]);
        return apexTotales((float)$st->fetchColumn(), 0, (float)$cot['servicios_subtotal']);
    }

    // Descuento efectivo = manual (asesor) + automático de cliente referido (suma, no cascada).
    // El candado de autorización dir_admin >10% (autorizaciones_descuento) evalúa
    // solo `cotizaciones.descuento` — descuento_referido queda fuera de ese cálculo
    // a propósito, es automático y no requiere aprobación.
    $descuento_efectivo = min(100, (float)$cot['descuento'] + (float)$cot['descuento_referido']); // S1-01/S1-04: tope 100%

    $st = $db->prepare("SELECT COALESCE(SUM(precio_m2_usado*m2*cantidad),0) FROM cotizaciones_partidas WHERE cotizacion_id = ?");
    $st->execute([(int)$cotizacion_id]);
    return apexTotales((float)$st->fetchColumn(), $descuento_efectivo, (float)$cot['servicios_subtotal']);
}
