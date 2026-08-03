<?php
// ============================================================
//  APEX GLASS - Helper: Esquema de Referidos
//  Archivo: api/helpers/referidos_lib.php
//  Promoción Agosto 2026: cliente nuevo captura el CTN de quien
//  lo refirió (solo en su primera cotización) → 5% de descuento
//  automático para el referido + 5% de saldo a favor para el
//  referente al VoBo de cada cotización del referido en el mes.
// ============================================================
require_once __DIR__ . '/totales.php';

const REFERIDOS_PROMO_INICIO = '2026-08-01';
const REFERIDOS_PROMO_FIN    = '2026-08-31';
const REFERIDOS_PCT          = 5.00;

function referidosPromoActiva($fecha = null) {
    $f = $fecha ?: date('Y-m-d');
    return $f >= REFERIDOS_PROMO_INICIO && $f <= REFERIDOS_PROMO_FIN;
}

function referidosMesPromo() {
    return substr(REFERIDOS_PROMO_INICIO, 0, 7);
}

// Valida un código de referido (CTN) ANTES de crear la cotización nueva (aún
// no existe cot_id, por eso el chequeo de "cliente nuevo" es un COUNT simple).
// Solo lectura — no inserta nada. Devuelve ['error'=>?string, 'referente_id'=>?int].
function referidosValidar(PDO $db, $cliente_id, $referido_ctn) {
    $referido_ctn = trim((string)$referido_ctn);
    if ($referido_ctn === '') return ['error' => null, 'referente_id' => null];

    if (!referidosPromoActiva()) {
        return ['error' => 'La promoción de referidos no está activa (vigente ' . REFERIDOS_PROMO_INICIO . ' a ' . REFERIDOS_PROMO_FIN . ').', 'referente_id' => null];
    }

    // Cliente debe ser nuevo: sin cotizaciones previas.
    $stCnt = $db->prepare("SELECT COUNT(*) FROM cotizaciones WHERE cliente_id = ?");
    $stCnt->execute([$cliente_id]);
    if ((int)$stCnt->fetchColumn() > 0) {
        return ['error' => 'El código de referido solo aplica a clientes nuevos — este cliente ya tiene cotizaciones previas.', 'referente_id' => null];
    }

    $stRef = $db->prepare("SELECT id FROM clientes WHERE codigo = ? AND activo = 1");
    $stRef->execute([$referido_ctn]);
    $referente = $stRef->fetch(PDO::FETCH_ASSOC);
    if (!$referente) {
        return ['error' => 'El código de referido "' . $referido_ctn . '" no corresponde a ningún cliente activo.', 'referente_id' => null];
    }
    if ((int)$referente['id'] === (int)$cliente_id) {
        return ['error' => 'Un cliente no puede referirse a sí mismo.', 'referente_id' => null];
    }

    $stDup = $db->prepare("SELECT id FROM clientes_referidos WHERE cliente_id = ?");
    $stDup->execute([$cliente_id]);
    if ($stDup->fetch()) {
        return ['error' => 'Este cliente ya tiene un referente registrado.', 'referente_id' => null];
    }

    return ['error' => null, 'referente_id' => (int)$referente['id']];
}

// Registra la relación de referido una vez que la cotización YA existe
// (cot_id conocido). Llamar dentro de la misma transacción, tras el INSERT
// de la cotización y solo si referidosValidar() no devolvió error.
function referidosRegistrar(PDO $db, $cliente_id, $referente_id, $referido_ctn, $usuario_nombre, $cot_id) {
    $db->prepare("INSERT INTO clientes_referidos
        (cliente_id, referente_cliente_id, referente_ctn, mes_promo, registrado_por, cotizacion_origen_id)
        VALUES (?,?,?,?,?,?)")
       ->execute([$cliente_id, $referente_id, trim((string)$referido_ctn), referidosMesPromo(), $usuario_nombre, $cot_id]);
}

// Para cotizaciones NUEVAS y subsecuentes del mismo cliente durante el mes de
// promo: si ya existe relación registrada, aplica el 5% automático sin que
// el asesor tenga que volver a teclear el código.
function referidosDescuentoAutomatico(PDO $db, $cliente_id) {
    if (!referidosPromoActiva()) return 0.0;
    $st = $db->prepare("SELECT id FROM clientes_referidos WHERE cliente_id = ? AND mes_promo = ?");
    $st->execute([$cliente_id, referidosMesPromo()]);
    return $st->fetch() ? REFERIDOS_PCT : 0.0;
}

// Al VoBo de una cotización: si el cliente tiene referente registrado y el
// VoBo cae dentro del mismo mes de la promoción, abona 5% del subtotal neto
// al referente (idempotente — nunca duplica) y regresa datos para WA.
// Debe llamarse DESPUÉS de que cotizaciones.vobo_at ya quedó grabado.
function referidosAcreditarVoBo(PDO $db, $cotizacion_id) {
    $st = $db->prepare("
        SELECT c.id, c.folio, c.cliente_id, c.vobo_at, cl.nombre AS cliente_nombre,
               cr.referente_cliente_id, cr.mes_promo,
               ref.nombre AS referente_nombre, ref.telefono AS referente_telefono,
               ref.telefono_alterno AS referente_telefono_alterno
        FROM cotizaciones c
        JOIN clientes cl ON cl.id = c.cliente_id
        JOIN clientes_referidos cr ON cr.cliente_id = c.cliente_id
        JOIN clientes ref ON ref.id = cr.referente_cliente_id
        WHERE c.id = ?
    ");
    $st->execute([$cotizacion_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row || !$row['vobo_at']) return null;

    // Solo cuenta si el VoBo cae dentro del mes de la promoción vigente cuando se registró el referido.
    if (substr($row['vobo_at'], 0, 7) !== $row['mes_promo']) return null;

    // Idempotencia: nunca abonar dos veces por la misma cotización.
    $stChk = $db->prepare("SELECT id FROM clientes_saldo_favor WHERE tipo = 'referido' AND cotizacion_id = ?");
    $stChk->execute([$cotizacion_id]);
    if ($stChk->fetch()) return null;

    $tots = apexTotalesCotizacion($db, $cotizacion_id);
    if (!$tots) return null;
    $monto = round((float)$tots['subtotal'] * (REFERIDOS_PCT / 100), 2);
    if ($monto <= 0) return null;

    $db->prepare("INSERT INTO clientes_saldo_favor
        (cliente_id, tipo, monto, fecha, referencia, notas, cotizacion_id, creado_por)
        VALUES (?, 'referido', ?, CURDATE(), ?, ?, ?, ?)")
       ->execute([
           $row['referente_cliente_id'], $monto,
           'Referido: ' . $row['folio'],
           'Bono ' . REFERIDOS_PCT . '% por compra de ' . $row['cliente_nombre'] . ' (' . $row['folio'] . ')',
           $cotizacion_id, 'Sistema (Referidos)'
       ]);

    return [
        'referente_cliente_id'    => (int)$row['referente_cliente_id'],
        'referente_nombre'        => $row['referente_nombre'],
        'referente_telefono'      => $row['referente_telefono_alterno'] ?: $row['referente_telefono'],
        'monto'                   => $monto,
        'cliente_referido_nombre' => $row['cliente_nombre'],
        'folio'                   => $row['folio'],
    ];
}
