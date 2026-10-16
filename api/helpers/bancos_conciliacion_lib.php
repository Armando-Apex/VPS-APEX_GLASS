<?php
// ============================================================
//  APEX GLASS - Bancos (UPD-702) — Fase 2: conciliación de abonos contra Apex
//  Archivo: api/helpers/bancos_conciliacion_lib.php   (lo usa api/bancos.php)
// ============================================================
// Cada abono del banco se liga a uno o varios registros de Apex que explican ese dinero:
//   - 'pago'        → cotizacion_pagos (efectivo/tarjeta/transferencia; nunca saldo_favor,
//                     ese dinero ya entró cuando se hizo el depósito original)
//   - 'saldo_favor' → clientes_saldo_favor tipo 'deposito' (depósitos a cuenta y excedentes)
//   - 'otro'        → dinero sin registro en Apex (préstamo, traspaso, devolución…), con categoría
// Un registro de Apex solo puede ligarse a un abono (uk_origen). La suma ligada no puede
// pasar del abono. Sin sesión ni salida: se puede probar desde consola.

// UPD-713: lo ligado puede pasar del abono por centavos (efectivo depositado sin monedas, ej. S-992
// $709.99 vs. banco $709.90). Misma tolerancia que Cobranza/Facturación: diferencia menor a $1.
const CONC_TOLERANCIA = 1.00;

const CONC_CATEGORIAS = [
    'traspaso'   => 'Traspaso entre cuentas',
    'prestamo'   => 'Préstamo / aportación',
    'devolucion' => 'Devolución / reembolso',
    'otro'       => 'Otro',
];

// Formas de pago de Apex que tienen sentido para cada tipo de movimiento del banco.
function concFormasCompatibles($tipoMov) {
    switch ($tipoMov) {
        case 'spei_recibido':
        case 'pago_tercero':      return ['transferencia'];
        case 'deposito_cheque':   return ['transferencia', 'cheque'];
        case 'deposito_efectivo': return ['efectivo'];
        case 'terminal_venta':    return ['tarjeta'];
        default:                  return null; // cualquiera
    }
}

// Registros de Apex que meten dinero (pagos + depósitos de saldo a favor), sin ligar.
// $extraWhere se aplica sobre la tabla derivada "r". Solo constantes en el SQL; los
// valores van en $params.
function concRegistrosSql($soloSinLigar = true) {
    $libre1 = $soloSinLigar ? "AND NOT EXISTS (SELECT 1 FROM banco_conciliacion bc WHERE bc.origen = 'pago' AND bc.origen_id = p.id)" : '';
    $libre2 = $soloSinLigar ? "AND NOT EXISTS (SELECT 1 FROM banco_conciliacion bc WHERE bc.origen = 'saldo_favor' AND bc.origen_id = s.id)" : '';
    return "(
        SELECT 'pago' AS origen, p.id AS origen_id, p.fecha_pago AS fecha, p.monto, p.forma_pago AS forma,
               p.tarjeta_tipo, c.folio AS cot_folio, o.folio AS orden_folio,
               COALESCE(cl.nombre, c.cliente_nombre) AS cliente, cl.codigo AS cliente_codigo,
               p.notas AS notas, p.registrado_por AS registrado_por
        FROM cotizacion_pagos p
        JOIN cotizaciones c ON c.id = p.cotizacion_id
        LEFT JOIN ordenes o ON o.id = c.orden_id
        LEFT JOIN clientes cl ON cl.id = c.cliente_id
        WHERE p.forma_pago <> 'saldo_favor' $libre1
        UNION ALL
        SELECT 'saldo_favor', s.id, s.fecha, s.monto, s.forma_pago,
               s.tarjeta_tipo, c.folio, o.folio,
               cl.nombre, cl.codigo,
               CONCAT_WS(' · ', NULLIF(s.referencia, ''), NULLIF(s.notas, '')), s.creado_por
        FROM clientes_saldo_favor s
        JOIN clientes cl ON cl.id = s.cliente_id
        LEFT JOIN cotizaciones c ON c.id = s.cotizacion_id
        LEFT JOIN ordenes o ON o.id = c.orden_id
        WHERE s.tipo = 'deposito' AND s.monto > 0 $libre2
    ) r";
}

function concNormalizar($s) {
    $s = mb_strtoupper((string)$s, 'UTF-8');
    $s = strtr($s, ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N']);
    return $s;
}

// Pistas que trae el texto del banco: números de cotización, folios de orden y palabras de nombre.
function concPistas($mov) {
    $txt = concNormalizar($mov['concepto'] . ' ' . $mov['detalle']);
    $cots = []; $ords = []; $pal = [];
    if (preg_match_all('/COT(?:IZACION)?\s*[-#.]?\s*(\d{3,5})/', $txt, $m)) {
        foreach ($m[1] as $n) $cots[] = 'COT-' . str_pad(ltrim($n, '0') ?: '0', 4, '0', STR_PAD_LEFT);
    }
    if (preg_match_all('/(?<![A-Z0-9])([A-Z])\s?-\s?(\d{3})(?![0-9])/', $txt, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) $ords[] = $x[1] . '-' . $x[2];
    }
    $stop = ['PAGO','PAGOS','TRANSFERENCIA','TRANSF','INTERBANCARIA','TEMPLADOS','TEMPLADO','VIDRIOS','VIDRIO',
             'CRISTAL','CRISTALES','ESPEJO','ESPEJOS','ANTICIPO','RECIBIDO','SPEI','BNET','DEPOSITO','EFECTIVO',
             'TEMPLADORA','TENPLADORA','NORESTE','VENTAS','CREDITO','DEBITO','TERMINALES','PUNTO','VENTA',
             'COTIZACION','ORDEN','CUENTA','TERCERO','BANORTE','BANAMEX','SANTANDER','BANREGIO','HSBC','AZTECA',
             'BAJIO','BANCOPPEL','PIEZAS','LIQUIDACION','RESTO','SALDO','FACTURA','PARA','POR','DEL','LOS','LAS'];
    $det = concNormalizar($mov['detalle']);
    foreach (preg_split('/[^A-Z]+/', $det) as $w) {
        if (strlen($w) >= 4 && !in_array($w, $stop, true)) $pal[] = $w;
    }
    return ['cots' => array_unique($cots), 'ordenes' => array_unique($ords), 'palabras' => array_unique($pal)];
}

// Califica qué tan probable es que el registro $r explique el abono $mov.
function concCalificar($mov, $r, $pistas, $restante) {
    $score = 0; $razones = [];
    $monto = (float)$r['monto'];
    if (abs($monto - (float)$mov['abono']) < 0.005) { $score += 100; $razones[] = 'Mismo monto'; }
    elseif (abs($monto - $restante) < 0.005)        { $score += 90;  $razones[] = 'Completa lo que falta'; }
    elseif ($monto > $restante && $monto - $restante < CONC_TOLERANCIA) { $score += 80; $razones[] = 'Casi el mismo monto (dif. $' . number_format($monto - $restante, 2) . ')'; }
    elseif ($monto > $restante + 0.005)             { $score -= 40; }
    if ($r['cot_folio'] && in_array($r['cot_folio'], $pistas['cots'], true))       { $score += 60; $razones[] = 'El banco dice ' . $r['cot_folio']; }
    if ($r['orden_folio'] && in_array($r['orden_folio'], $pistas['ordenes'], true)) { $score += 60; $razones[] = 'El banco dice ' . $r['orden_folio']; }
    if ($pistas['palabras'] && $r['cliente']) {
        $nom = preg_split('/[^A-Z]+/', concNormalizar($r['cliente']));
        $hits = array_values(array_intersect($pistas['palabras'], $nom));
        if ($hits) { $score += min(60, 30 * count($hits)); $razones[] = 'Nombre: ' . implode(' ', $hits); }
    }
    $formas = concFormasCompatibles($mov['tipo']);
    if ($formas !== null && $r['forma']) {
        if (in_array($r['forma'], $formas, true)) $score += 15; else { $score -= 15; $razones[] = 'Forma distinta (' . $r['forma'] . ')'; }
    }
    $dias = (int)round((strtotime($mov['fecha']) - strtotime($r['fecha'])) / 86400);
    $score += max(0, 8 - abs($dias));
    return ['score' => $score, 'razones' => $razones, 'dias' => $dias];
}

function concMovimiento($db, $id, $bloquear = false) {
    // Solo movimientos de cuentas activas (mismo alcance que bancosCuenta()).
    $s = $db->prepare("SELECT m.* FROM banco_movimientos m JOIN banco_cuentas c ON c.id = m.cuenta_id AND c.activo = 1
                       WHERE m.id = ?" . ($bloquear ? ' FOR UPDATE' : ''));
    $s->execute([(int)$id]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Ligas ya registradas de un movimiento, con la descripción del registro de Apex.
function concLigas($db, $movId) {
    $s = $db->prepare("SELECT bc.*, r.fecha AS r_fecha, r.forma, r.cot_folio, r.orden_folio, r.cliente, r.cliente_codigo, r.notas AS r_notas
                       FROM banco_conciliacion bc
                       LEFT JOIN " . concRegistrosSql(false) . " ON r.origen = bc.origen AND r.origen_id = bc.origen_id
                       WHERE bc.movimiento_id = ? ORDER BY bc.id");
    $s->execute([(int)$movId]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

// Sugerencias para un abono: registros sin ligar cerca de la fecha (o búsqueda libre).
function concSugerencias($db, $mov, $restante, $q = '') {
    $params = [];
    if ($q !== '') {
        $where = "(r.cot_folio LIKE ? OR r.orden_folio LIKE ? OR r.cliente LIKE ? OR r.cliente_codigo LIKE ? OR CAST(r.monto AS CHAR) LIKE ?)";
        $like = '%' . $q . '%';
        $params = [$like, $like, $like, $like, $like];
        $limite = 40;
    } else {
        $where = "r.fecha BETWEEN DATE_SUB(?, INTERVAL 10 DAY) AND DATE_ADD(?, INTERVAL 2 DAY)";
        $params = [$mov['fecha'], $mov['fecha']];
        $limite = 25;
    }
    $s = $db->prepare("SELECT r.* FROM " . concRegistrosSql(true) . " WHERE $where ORDER BY r.fecha DESC LIMIT 400");
    $s->execute($params);
    $pistas = concPistas($mov);
    $out = [];
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $c = concCalificar($mov, $r, $pistas, $restante);
        $r['score'] = $c['score']; $r['razones'] = $c['razones']; $r['dias'] = $c['dias'];
        $out[] = $r;
    }
    usort($out, function ($a, $b) { return $b['score'] <=> $a['score'] ?: strcmp($b['fecha'], $a['fecha']); });
    return array_slice($out, 0, $limite);
}

// Total ya ligado de un movimiento.
function concTotalLigado($db, $movId) {
    $s = $db->prepare("SELECT COALESCE(SUM(monto), 0) FROM banco_conciliacion WHERE movimiento_id = ?");
    $s->execute([(int)$movId]);
    return (float)$s->fetchColumn();
}

// Registra ligas para un abono. $items = [['origen'=>'pago|saldo_favor','origen_id'=>n] | ['origen'=>'otro','monto'=>x,'categoria'=>..,'nota'=>..]]
// Debe llamarse dentro de una transacción. Lanza Exception con mensaje para el usuario.
function concLigar($db, $movId, $items, $usuario, $automatico = 0) {
    $mov = concMovimiento($db, $movId, true);
    if (!$mov) throw new Exception('Movimiento no encontrado');
    if ((float)$mov['abono'] <= 0) throw new Exception('Solo se pueden ligar abonos (entradas de dinero)');
    if (!$items) throw new Exception('No elegiste nada para ligar');
    $total = concTotalLigado($db, $movId);
    $ins = $db->prepare("INSERT INTO banco_conciliacion (movimiento_id, origen, origen_id, monto, categoria, nota, automatico, usuario)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($items as $it) {
        $origen = $it['origen'] ?? '';
        if ($origen === 'pago' || $origen === 'saldo_favor') {
            $id = (int)($it['origen_id'] ?? 0);
            $s = $db->prepare("SELECT r.monto FROM " . concRegistrosSql(false) . " WHERE r.origen = ? AND r.origen_id = ?");
            $s->execute([$origen, $id]);
            $monto = $s->fetchColumn();
            if ($monto === false) throw new Exception('El registro de Apex ya no existe o no es un ingreso de dinero');
            $ya = $db->prepare("SELECT movimiento_id FROM banco_conciliacion WHERE origen = ? AND origen_id = ? FOR UPDATE");
            $ya->execute([$origen, $id]);
            if ($ya->fetchColumn() !== false) throw new Exception('Uno de los registros ya está ligado a otro movimiento del banco');
            $monto = (float)$monto;
            $ins->execute([$movId, $origen, $id, $monto, null, null, $automatico, $usuario]);
        } elseif ($origen === 'otro') {
            $monto = round((float)($it['monto'] ?? 0), 2);
            $cat = (string)($it['categoria'] ?? '');
            if ($monto <= 0) throw new Exception('Captura el monto');
            if (!isset(CONC_CATEGORIAS[$cat])) throw new Exception('Elige una categoría');
            $nota = mb_substr(trim((string)($it['nota'] ?? '')), 0, 300);
            if ($nota === '') throw new Exception('Escribe una nota que explique de dónde viene el dinero');
            $ins->execute([$movId, 'otro', null, $monto, $cat, $nota, 0, $usuario]);
        } else {
            throw new Exception('Tipo de registro no válido');
        }
        $total += $monto;
    }
    if (round($total - (float)$mov['abono'], 2) >= CONC_TOLERANCIA) {
        throw new Exception('Lo ligado ($' . number_format($total, 2) . ') pasa del abono del banco ($' . number_format((float)$mov['abono'], 2) . ') por $1.00 o más');
    }
    return round($total, 2);
}

// Propuesta automática: abonos sin ligar del rango con UN solo registro de Apex del mismo
// monto, forma compatible y fecha cercana (3 días antes a 1 después), y ese registro
// también sin otra opción en el rango. No escribe nada.
function concPropuestaAuto($db, $cuentaId, $desde, $hasta) {
    $s = $db->prepare("SELECT * FROM banco_movimientos m
                       WHERE m.cuenta_id = ? AND m.fecha BETWEEN ? AND ? AND m.abono > 0
                         AND NOT EXISTS (SELECT 1 FROM banco_conciliacion bc WHERE bc.movimiento_id = m.id)
                       ORDER BY m.secuencia");
    $s->execute([$cuentaId, $desde, $hasta]);
    $movs = $s->fetchAll(PDO::FETCH_ASSOC);
    if (!$movs) return [];
    $s = $db->prepare("SELECT r.* FROM " . concRegistrosSql(true) . " WHERE r.fecha BETWEEN DATE_SUB(?, INTERVAL 3 DAY) AND DATE_ADD(?, INTERVAL 1 DAY)");
    $s->execute([$desde, $hasta]);
    $regs = $s->fetchAll(PDO::FETCH_ASSOC);

    $porMov = []; $porReg = [];
    foreach ($movs as $i => $m) {
        $formas = concFormasCompatibles($m['tipo']);
        $tm = strtotime($m['fecha']);
        foreach ($regs as $j => $r) {
            if (abs((float)$r['monto'] - (float)$m['abono']) >= 0.005) continue;
            $d = ($tm - strtotime($r['fecha'])) / 86400;
            if ($d < -1 || $d > 3) continue;
            if ($formas !== null && !in_array($r['forma'], $formas, true)) continue;
            $porMov[$i][] = $j;
            $porReg[$j][] = $i;
        }
    }
    $out = [];
    foreach ($porMov as $i => $js) {
        if (count($js) !== 1) continue;
        $j = $js[0];
        if (count($porReg[$j]) !== 1) continue;
        $m = $movs[$i]; $r = $regs[$j];
        $out[] = [
            'movimiento_id' => (int)$m['id'], 'fecha' => $m['fecha'], 'concepto' => $m['concepto'], 'detalle' => $m['detalle'],
            'tipo' => $m['tipo'], 'abono' => (float)$m['abono'],
            'origen' => $r['origen'], 'origen_id' => (int)$r['origen_id'], 'r_fecha' => $r['fecha'], 'forma' => $r['forma'],
            'cot_folio' => $r['cot_folio'], 'orden_folio' => $r['orden_folio'], 'cliente' => $r['cliente'], 'cliente_codigo' => $r['cliente_codigo'],
        ];
    }
    return $out;
}
