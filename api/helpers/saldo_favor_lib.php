<?php
// ============================================================
//  Saldo a favor (anticipos de clientes) — forma de pago del depósito
//  Fase 1 del esquema A de anticipos del SAT (30-sep-2026, UPD-623).
//
//  Para el SAT, el dinero que el cliente deja "a favor" es un ANTICIPO: se factura
//  cuando se recibe, y ese CFDI exige la forma de pago real. Antes ningún depósito la
//  guardaba. Aquí se normaliza la forma que llega de la pantalla y se deduce la de un
//  depósito que regresa dinero de una orden (cancelación, rechazo, corrección).
//  El bono de referido (tipo 'referido') NO pasa por aquí: no es dinero recibido.
// ============================================================

// Formas que acepta la captura de un depósito. La pantalla manda tarjeta_credito /
// tarjeta_debito (el SAT distingue 04 crédito de 28 débito).
function sfFormasCaptura() {
    return ['efectivo', 'transferencia', 'cheque', 'tarjeta_credito', 'tarjeta_debito'];
}

// Convierte la forma capturada a [forma_pago, tarjeta_tipo]; null si no es válida.
function sfNormalizarForma($forma) {
    $forma = (string)$forma;
    if (!in_array($forma, sfFormasCaptura(), true)) return null;
    if ($forma === 'tarjeta_credito') return ['tarjeta', 'credito'];
    if ($forma === 'tarjeta_debito')  return ['tarjeta', 'debito'];
    return [$forma, null];
}

// Forma de pago de un dinero que regresa de una cotización al saldo a favor: solo si
// TODOS sus pagos fueron con la misma forma (y, en tarjeta, el mismo tipo). Si hay
// mezcla o parte vino de saldo a favor, regresa [null, null] y se escoge al facturar
// el anticipo — no se adivina.
function sfFormaDePagosCotizacion($db, $cotId) {
    $stmt = $db->prepare("SELECT DISTINCT forma_pago, tarjeta_tipo FROM cotizacion_pagos WHERE cotizacion_id = ? AND monto > 0");
    $stmt->execute([(int)$cotId]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($filas) !== 1) return [null, null];
    $f = $filas[0];
    if (!in_array($f['forma_pago'], ['efectivo', 'transferencia', 'tarjeta'], true)) return [null, null];
    if ($f['forma_pago'] === 'tarjeta' && !$f['tarjeta_tipo']) return ['tarjeta', null];
    return [$f['forma_pago'], $f['forma_pago'] === 'tarjeta' ? $f['tarjeta_tipo'] : null];
}

// Marca la forma de pago de un depósito recién insertado a partir de los pagos de su
// cotización. Se llama justo después del INSERT, dentro de la misma transacción.
function sfMarcarFormaDesdeCotizacion($db, $saldoFavorId, $cotId) {
    list($forma, $tipo) = sfFormaDePagosCotizacion($db, $cotId);
    if (!$forma) return;
    $db->prepare("UPDATE clientes_saldo_favor SET forma_pago = ?, tarjeta_tipo = ? WHERE id = ? AND forma_pago IS NULL")
       ->execute([$forma, $tipo, (int)$saldoFavorId]);
}
