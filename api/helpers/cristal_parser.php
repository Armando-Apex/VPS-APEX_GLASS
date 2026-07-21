<?php
// ============================================================
//  APEX GLASS - Helper: parsear piezas.cristal (texto libre)
//  Archivo: api/helpers/cristal_parser.php
//  Infiere tipo (ENUM de `laminas`) + espesor_mm a partir del
//  texto libre de piezas.cristal (ej. "Claro de 9mm", "Tintex 6mm",
//  "Claro 9mm - Servicio Express"). Mismo criterio de normalizacion
//  y prefijo ya usado en api/inventario.php (accion=costo_promedio)
//  y portal/tablero.php (UPD-299/314): el tipo siempre aparece
//  primero en el texto, seguido del espesor.
//  Si el texto no matchea ningun tipo del ENUM (ej. "Pavia",
//  "Bronce", "EVO 50"), retorna null — la UI debe caer a
//  seleccion manual, nunca bloquear el flujo.
// ============================================================

const CRISTAL_TIPO_LABEL = [
    'claro'           => 'Claro',
    'claro_zafiro'    => 'Claro Zafiro',
    'filtrasol'       => 'Filtrasol',
    'espejo'          => 'Espejo',
    'espejo_aluminio' => 'Espejo Aluminio',
    'laminado_claro'  => 'Laminado Claro',
    'reflecta'        => 'Reflecta',
    'satinado'        => 'Satinado',
    'tintex'          => 'Tintex',
];

function cristalNormalizar($s) {
    return preg_replace('/\s+/', '', mb_strtolower((string)$s));
}

// Retorna ['tipo' => 'claro', 'espesor_mm' => 6.0] o null si no matchea.
function parsearCristalATipoEspesor($cristalTexto) {
    if (!$cristalTexto) return null;
    $norm = cristalNormalizar($cristalTexto);

    if (!preg_match('/(\d+(\.\d+)?)\s*mm/i', $cristalTexto, $m)) return null;
    $espesor = (float)$m[1];

    $tipoEncontrado = null;
    foreach (CRISTAL_TIPO_LABEL as $enumVal => $label) {
        $base = cristalNormalizar($label);
        if (strpos($norm, $base) === 0) {
            // Prefiere el match más largo (ej. "claro_zafiro" sobre "claro")
            if ($tipoEncontrado === null || strlen($base) > strlen(cristalNormalizar(CRISTAL_TIPO_LABEL[$tipoEncontrado]))) {
                $tipoEncontrado = $enumVal;
            }
        }
    }
    if ($tipoEncontrado === null) return null;

    return ['tipo' => $tipoEncontrado, 'espesor_mm' => $espesor];
}

// Extrae label real (texto, no ENUM) + espesor de un texto libre tipo
// piezas.cristal — mismo criterio que api/sesion_corte.php accion=catalogo_tipos_mm,
// para poder comparar "¿esta pieza es del mismo tipo/espesor que la sesión de
// corte activa?" sin depender del ENUM restringido de `laminas`.
// Retorna ['label' => 'Claro Zafiro', 'espesor_mm' => 9.0] o null si no hay mm.
function parsearCristalLabelEspesor($texto) {
    if (!$texto) return null;
    $n = preg_replace('/\s*-\s*(Servicio Express|Con Esmerilado)\s*$/i', '', $texto);
    $n = preg_replace('/^Plantilla\s+/i', '', $n);
    if (!preg_match('/^(.*?)\s*[-]?\s*(\d+(\.\d+)?)\s*mm$/i', trim($n), $m)) return null;
    $label = preg_replace('/\s+de$/i', '', trim($m[1]));
    $label = rtrim($label, ' -');
    if ($label === '') return null;
    return ['label' => $label, 'espesor_mm' => (float)$m[2]];
}

// ¿El texto libre de una pieza coincide con el tipo/espesor que el operador
// eligió para la sesión de corte? Compara por label normalizado (ignora
// mayúsculas/espacios) — no exige coincidencia exacta de mayúsculas/acentos.
function cristalCoincideConSesion($cristalTexto, $tipoLabelSesion, $espesorSesion) {
    $parsed = parsearCristalLabelEspesor($cristalTexto);
    if (!$parsed) return false;
    if (abs($parsed['espesor_mm'] - (float)$espesorSesion) > 0.01) return false;
    return cristalNormalizar($parsed['label']) === cristalNormalizar($tipoLabelSesion);
}
