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

// Arranque de la facturación en Apex: los depósitos desde esta fecha se facturan como
// anticipo en Apex; los anteriores se revisan uno por uno (¿se facturaron en CONTPAQi?).
if (!defined('ANTICIPOS_DESDE')) define('ANTICIPOS_DESDE', '2026-10-01');

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
// TODOS sus pagos en dinero fueron con la misma forma (y, en tarjeta, el mismo tipo). Los
// pagos con saldo a favor no cuentan: esa parte regresa como reintegro, no como depósito
// (ver sfRegistrarDevolucion). Si hay mezcla regresa [null, null] y la forma se escoge al
// facturar el anticipo — no se adivina.
function sfFormaDePagosCotizacion($db, $cotId) {
    $stmt = $db->prepare("SELECT DISTINCT forma_pago, tarjeta_tipo FROM cotizacion_pagos WHERE cotizacion_id = ? AND monto > 0 AND forma_pago <> 'saldo_favor'");
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

// ── Asignación PEPS del monedero (Fase 3/4 de anticipos, 30-sep-2026) ─────────
// El saldo a favor no guarda qué depósito se gastó en qué orden. Se asigna PEPS: cada
// consumo (fila negativa) toma de las entradas más antiguas con saldo, por (fecha, id).
// Un 'reintegro' (dinero que regresa de una orden cancelada que se había pagado con saldo
// a favor) es una entrada nueva que HEREDA el origen del depósito de donde salió
// (origen_id): al volver a gastarse, se relaciona con el mismo CFDI de anticipo en vez de
// parecer dinero nuevo. Regresa [id de fila de consumo => [pedazos]], cada pedazo con el
// depósito ORIGINAL (deposito_id, tipo, fecha) y el monto tomado.
function sfAsignacionPeps($db, $clienteId) {
    $stmt = $db->prepare("SELECT id, tipo, monto, fecha, origen_id FROM clientes_saldo_favor WHERE cliente_id = ? ORDER BY fecha, id");
    $stmt->execute([(int)$clienteId]);
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $porId = [];
    foreach ($filas as $f) $porId[(int)$f['id']] = $f;
    $cola = []; $asig = [];
    foreach ($filas as $r) {
        $m = round((float)$r['monto'], 2);
        if ($m > 0) {
            $o = $r;
            if ($r['tipo'] === 'reintegro' && $r['origen_id'] && isset($porId[(int)$r['origen_id']])) $o = $porId[(int)$r['origen_id']];
            $cola[] = ['resto'=>$m, 'deposito_id'=>(int)$o['id'], 'tipo'=>$o['tipo'], 'fecha'=>$o['fecha']];
            continue;
        }
        if ($m == 0) continue;
        $falta = -$m; $piezas = [];
        foreach ($cola as &$d) {
            if ($falta <= 0.004) break;
            if ($d['resto'] <= 0.004) continue;
            $toma = round(min($d['resto'], $falta), 2);
            $d['resto'] = round($d['resto'] - $toma, 2);
            $falta = round($falta - $toma, 2);
            $piezas[] = ['deposito_id'=>$d['deposito_id'], 'tipo'=>$d['tipo'], 'fecha'=>$d['fecha'], 'monto'=>$toma];
        }
        unset($d);
        if ($falta > 0.004) $piezas[] = ['deposito_id'=>null, 'tipo'=>'sin_origen', 'fecha'=>null, 'monto'=>$falta];
        $asig[(int)$r['id']] = $piezas;
    }
    return $asig;
}

// Lo que una cotización tiene aplicado HOY de saldo a favor, por depósito de origen:
// pedazos de sus aplicaciones menos lo que ya se le reintegró. Regresa lista de pedazos.
function sfPiezasNetasCotizacion($db, $cotId, $clienteId, $asig = null) {
    if ($asig === null) $asig = sfAsignacionPeps($db, $clienteId);
    $stmt = $db->prepare("SELECT id FROM clientes_saldo_favor WHERE cotizacion_id = ? AND tipo = 'aplicacion' AND monto < 0 ORDER BY fecha, id");
    $stmt->execute([(int)$cotId]);
    $netas = [];   // clave origen => pedazo acumulado
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $aid) {
        foreach (($asig[(int)$aid] ?? []) as $pz) {
            $k = $pz['deposito_id'] === null ? 'sin' : (string)$pz['deposito_id'];
            if (!isset($netas[$k])) $netas[$k] = $pz + [];
            else $netas[$k]['monto'] = round($netas[$k]['monto'] + $pz['monto'], 2);
        }
    }
    $stmt = $db->prepare("SELECT origen_id, SUM(monto) m FROM clientes_saldo_favor WHERE cotizacion_id = ? AND tipo = 'reintegro' GROUP BY origen_id");
    $stmt->execute([(int)$cotId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $re) {
        $k = $re['origen_id'] === null ? 'sin' : (string)$re['origen_id'];
        if (isset($netas[$k])) $netas[$k]['monto'] = round($netas[$k]['monto'] - (float)$re['m'], 2);
    }
    return array_values(array_filter($netas, function($p) { return $p['monto'] > 0.004; }));
}

// Regresa al saldo a favor del cliente dinero de una cotización (cancelación, rechazo por
// calidad, corrección que baja el total). La parte que se había pagado con saldo a favor
// vuelve como REINTEGRO ligado a su depósito de origen (no es dinero nuevo: su anticipo ya
// está facturado); solo el resto entra como 'deposito', con la forma de pago de la orden.
// Sustituye al INSERT directo que hacían los 5 puntos de devolución (UPD-623/626).
function sfRegistrarDevolucion($db, $clienteId, $monto, $referencia, $notas, $cotId, $usuario) {
    $monto = round((float)$monto, 2);
    if ($monto <= 0 || !$clienteId) return;
    $hoy = date('Y-m-d');
    $falta = $monto;
    // Primero se reintegra lo pagado con saldo a favor, del depósito más reciente al más antiguo.
    $piezas = array_reverse(sfPiezasNetasCotizacion($db, $cotId, $clienteId));
    $ins = $db->prepare("INSERT INTO clientes_saldo_favor (cliente_id, tipo, monto, fecha, referencia, notas, cotizacion_id, origen_id, creado_por)
                         VALUES (?, 'reintegro', ?, ?, ?, ?, ?, ?, ?)");
    foreach ($piezas as $pz) {
        if ($falta <= 0.004) break;
        $toma = round(min($falta, $pz['monto']), 2);
        $ins->execute([(int)$clienteId, $toma, $hoy, mb_substr('Reintegro ' . $referencia, 0, 150), $notas, (int)$cotId, $pz['deposito_id'], $usuario]);
        $falta = round($falta - $toma, 2);
    }
    if ($falta > 0.004) {
        $db->prepare("INSERT INTO clientes_saldo_favor (cliente_id, tipo, monto, fecha, referencia, notas, cotizacion_id, creado_por)
                      VALUES (?, 'deposito', ?, ?, ?, ?, ?, ?)")
           ->execute([(int)$clienteId, $falta, $hoy, $referencia, $notas, (int)$cotId, $usuario]);
        sfMarcarFormaDesdeCotizacion($db, (int)$db->lastInsertId(), $cotId);
    }
}

// Saldo vigente de cada entrada del monedero de un cliente (lo que PEPS todavía no ha
// consumido). Regresa [id de la fila de entrada => resto]. Un reintegro suma a su origen.
function sfRemanentes($db, $clienteId) {
    $stmt = $db->prepare("SELECT id, tipo, monto, origen_id FROM clientes_saldo_favor WHERE cliente_id = ? ORDER BY fecha, id");
    $stmt->execute([(int)$clienteId]);
    $cola = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $m = round((float)$r['monto'], 2);
        if ($m > 0) {
            $clave = ($r['tipo'] === 'reintegro' && $r['origen_id']) ? (int)$r['origen_id'] : (int)$r['id'];
            $cola[] = ['k' => $clave, 'resto' => $m];
            continue;
        }
        $falta = -$m;
        foreach ($cola as &$d) {
            if ($falta <= 0.004) break;
            $toma = min($d['resto'], $falta);
            $d['resto'] = round($d['resto'] - $toma, 2);
            $falta = round($falta - $toma, 2);
        }
        unset($d);
    }
    $res = [];
    foreach ($cola as $d) { if ($d['resto'] > 0.004) $res[$d['k']] = round(($res[$d['k']] ?? 0) + $d['resto'], 2); }
    return $res;
}

// Depósitos anteriores al arranque (ANTICIPOS_DESDE) que todavía tienen saldo vigente, para
// que Administración marque si se facturaron como anticipo en CONTPAQi (UPD-629). Los bonos
// de referido no aparecen: nunca son anticipo.
function sfAnticiposPrevios($db) {
    $clientes = $db->query("SELECT DISTINCT cliente_id FROM clientes_saldo_favor WHERE tipo = 'deposito' AND fecha < '" . ANTICIPOS_DESDE . "'")->fetchAll(PDO::FETCH_COLUMN);
    $det = $db->prepare("SELECT sf.id, sf.cliente_id, sf.monto, sf.forma_pago, sf.tarjeta_tipo, sf.fecha, sf.referencia, sf.notas,
            sf.previo_facturado, sf.previo_folio, sf.previo_uuid, sf.previo_revisado_por, sf.previo_revisado_at,
            c.codigo AS cliente_codigo, COALESCE(NULLIF(c.razon_social,''), c.nombre) AS cliente_nombre
        FROM clientes_saldo_favor sf JOIN clientes c ON c.id = sf.cliente_id WHERE sf.id = ?");
    $out = [];
    foreach ($clientes as $cid) {
        foreach (sfRemanentes($db, $cid) as $id => $resto) {
            $det->execute([(int)$id]);
            $r = $det->fetch(PDO::FETCH_ASSOC);
            if (!$r || $r['fecha'] >= ANTICIPOS_DESDE) continue;
            $stTipo = $db->prepare("SELECT tipo FROM clientes_saldo_favor WHERE id = ?");
            $stTipo->execute([(int)$id]);
            if ($stTipo->fetchColumn() !== 'deposito') continue;
            $r['saldo_vigente'] = $resto;
            $out[] = $r;
        }
    }
    usort($out, function($a, $b) { return strcmp($a['cliente_nombre'], $b['cliente_nombre']) ?: ($a['id'] - $b['id']); });
    return $out;
}
