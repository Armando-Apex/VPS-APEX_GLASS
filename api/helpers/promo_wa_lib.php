<?php
// ============================================================
//  APEX GLASS - Helper: Promoción Estados de WhatsApp (volumen)
//  Archivo: api/helpers/promo_wa_lib.php
//  Publicidad en Estados de WhatsApp (ago-2026, indefinida hasta
//  que Armando la apague). Descuento escalonado por # de piezas
//  de la cotización. Código personalizado por cliente:
//  "<CTN del cliente>PROMO" (ej. CTN-146PROMO) — el cliente solo
//  puede usar SU propio código, valida contra el cliente_id de
//  la cotización. El % resultante se escribe directo en
//  cotizaciones.descuento (no es un campo aditivo aparte, como
//  descuento_referido) para que el candado de autorización >10%
//  (autorizaciones_descuento) se dispare igual que un descuento
//  manual — decisión explícita de Armando, 15-ago-2026.
// ============================================================

// Valida el código escrito por el asesor. Solo lectura.
// Devuelve ['error'=>?string, 'promocion_id'=>?int].
function promoWaValidarCodigo(PDO $db, $codigo, $cliente_id) {
    $codigo = strtoupper(trim((string)$codigo));
    if ($codigo === '') return ['error' => null, 'promocion_id' => null];

    if (!preg_match('/^(CTN-\d+)PROMO$/', $codigo, $m)) {
        return ['error' => 'Formato de código inválido — debe ser CTN-###PROMO.', 'promocion_id' => null];
    }
    $ctn = $m[1];

    $stCli = $db->prepare("SELECT id FROM clientes WHERE codigo = ?");
    $stCli->execute([$ctn]);
    $cli = $stCli->fetch(PDO::FETCH_ASSOC);
    if (!$cli) {
        return ['error' => 'El código "' . $codigo . '" no corresponde a ningún cliente.', 'promocion_id' => null];
    }
    if ((int)$cli['id'] !== (int)$cliente_id) {
        return ['error' => 'Este código de promoción pertenece a otro cliente.', 'promocion_id' => null];
    }

    $stPromo = $db->prepare("SELECT id FROM promociones WHERE tipo = 'volumen_piezas' AND activo = 1 ORDER BY id DESC LIMIT 1");
    $stPromo->execute();
    $promo = $stPromo->fetch(PDO::FETCH_ASSOC);
    if (!$promo) {
        return ['error' => 'No hay ninguna promoción de volumen activa en este momento.', 'promocion_id' => null];
    }

    return ['error' => null, 'promocion_id' => (int)$promo['id']];
}

// Regresa el % de descuento del tramo que le toca a $totalPiezas, o null si
// ningún tramo cubre esa cantidad (no debería pasar con tramos continuos).
function promoWaCalcularDescuento(PDO $db, $promocion_id, $totalPiezas) {
    $totalPiezas = max(0, (int)$totalPiezas);
    $st = $db->prepare("
        SELECT descuento_pct FROM promociones_tramos
        WHERE promocion_id = ? AND piezas_min <= ? AND (piezas_max IS NULL OR piezas_max >= ?)
        ORDER BY piezas_min DESC LIMIT 1
    ");
    $st->execute([$promocion_id, $totalPiezas, $totalPiezas]);
    $val = $st->fetchColumn();
    return $val !== false ? (float)$val : null;
}
