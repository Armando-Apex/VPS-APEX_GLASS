<?php
require_once __DIR__ . '/saldo_favor_lib.php';
// Funciones compartidas de Facturación (CFDI, complementos de pago, resguardo).
// Extraídas de api/facturapi.php el 29-sep-2026 para que Cobranza (api/finanzas.php)
// pueda emitir complementos de pago automáticos sin duplicar código.
// Requiere config.php ya cargado (FACTURAPI_KEY, FACTURAPI_MODE, getDB).
if (defined('APEX_FACTURAPI_LIB')) return;
define('APEX_FACTURAPI_LIB', true);


// Recibe "correo1@x.com, correo2@x.com" y regresa solo los que son válidos (silenciosamente descarta lo demás)
function _correosValidos($raw) {
    $out = [];
    foreach (explode(',', (string)$raw) as $e) {
        $e = trim($e);
        if ($e && filter_var($e, FILTER_VALIDATE_EMAIL)) $out[] = $e;
    }
    return $out;
}

// LN-1: conceptos (desc/cant/precio) reconstruidos en SERVIDOR a partir del folio de
// orden — nunca confiar en lo que mande el cliente cuando hay una orden real detrás.
// Usada tanto para prellenar el formulario (buscar_orden) como para validar lo que
// se guarda (guardar). Regresa null si el folio no resuelve a una orden con cotización.
function _facturapiConceptosDesdeOrden($pdo, $ordenFolio) {
    $stmt = $pdo->prepare("SELECT id FROM ordenes WHERE folio = ? LIMIT 1");
    $stmt->execute([$ordenFolio]);
    $ordenId = $stmt->fetchColumn();
    if (!$ordenId) return null;

    $stmt = $pdo->prepare("SELECT id, tipo, descuento, COALESCE(descuento_referido,0) AS descuento_referido, COALESCE(descuento_encuesta,0) AS descuento_encuesta FROM cotizaciones WHERE orden_id = ? LIMIT 1");
    $stmt->execute([$ordenId]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cot) return null;

    $cotId     = $cot['id'];
    $esMaquila = ($cot['tipo'] ?? 'suministro') === 'maquila';
    // BLV-3: descuento efectivo = manual + automáticos de referido/encuesta (mismo
    // criterio que apexTotalesCotizacion, helpers/totales.php:51) — antes solo se
    // usaba el manual, dejando el CFDI por un monto distinto al realmente cobrado.
    $descuento = min(100, (float)($cot['descuento'] ?? 0) + (float)$cot['descuento_referido'] + (float)$cot['descuento_encuesta']);

    $conceptos = [];
    if ($esMaquila) {
        $stmt = $pdo->prepare("
            SELECT mp.*, tv.nombre AS tipo_vidrio_nombre
            FROM cotizaciones_maquila_partidas mp
            LEFT JOIN maquila_tipos_vidrio tv ON tv.id = mp.cristal_tipo_id
            WHERE mp.cotizacion_id = ?
            ORDER BY mp.num_partida ASC
        ");
        $stmt->execute([$cotId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            $servicios = [];
            if ($p['corte'])    $servicios[] = 'Corte';
            if ($p['canteado']) $servicios[] = 'Canteado';
            if ($p['taladros_pasados'] + $p['taladros_avellanados'] > 0) $servicios[] = 'Taladro';
            if ($p['templado'])  $servicios[] = 'Templado';
            $desc = trim(($p['tipo_vidrio_nombre'] ?: 'Vidrio') . ' ' . $p['espesor_mm'] . 'mm');
            if ($servicios) $desc .= ' - Maquila: ' . implode('/', $servicios);
            $conceptos[] = [
                'desc'   => $desc,
                'clave'  => '',
                'unidad' => 'MTK',
                'cant'   => round((float)$p['m2'] * (int)$p['cantidad'], 6),
                'precio' => (float)$p['m2'] > 0 ? round((float)$p['subtotal'] / ((float)$p['m2'] * (int)$p['cantidad']), 6) : 0,
                'iva'    => true,
            ];
        }
    } else {
        $stmt = $pdo->prepare("
            SELECT cristal_nombre, m2, cantidad, precio_m2_usado, promo_precio
            FROM cotizaciones_partidas
            WHERE cotizacion_id = ?
            ORDER BY num_partida ASC
        ");
        $stmt->execute([$cotId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
            // precio_m2_usado es bruto (sin descuento) — aplicar el % de la cotización, igual que el resto del sistema.
            // Partidas con precio fijo de promo (SALT_SEP2026) no reciben el % de descuento.
            $precioNeto = ($descuento > 0 && empty($p['promo_precio']))
                ? round((float)$p['precio_m2_usado'] * (1 - $descuento / 100), 6)
                : (float)$p['precio_m2_usado'];
            $conceptos[] = [
                'desc'   => $p['cristal_nombre'] ?: 'Vidrio',
                'clave'  => '',
                'unidad' => 'MTK',
                'cant'   => round((float)$p['m2'] * (int)$p['cantidad'], 6),
                'precio' => $precioNeto,
                'iva'    => true,
            ];
        }
        // BLV-3: servicios adicionales (instalado, taladro, ml, etc.) también forman
        // parte de la base gravable canónica (helpers/totales.php: base = subtotal+servicios)
        // pero antes no se incluían aquí — el CFDI quedaba sub-facturado en cotizaciones con servicios.
        $stmtSrv = $pdo->prepare("
            SELECT descripcion, precio_unitario, unidades_por_pieza, cantidad_piezas, subtotal
            FROM cotizacion_partida_servicios WHERE cotizacion_id = ?
        ");
        $stmtSrv->execute([$cotId]);
        foreach ($stmtSrv->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $conceptos[] = [
                'desc'   => $s['descripcion'] ?: 'Servicio',
                'clave'  => '',
                'unidad' => 'ACT',
                'cant'   => 1,
                'precio' => (float)$s['subtotal'],
                'iva'    => true,
            ];
        }
    }
    return $conceptos;
}

// Candado de contexto de negocio: ¿esta orden se puede facturar?
// Antes NO se validaba en ningún punto (hallado auditando el 26-sep-2026), así que se
// podía emitir un CFDI real de una orden cancelada, rechazada, sin VoBo o de RETRABAJO.
// El retrabajo es el caso grave: todo el sistema lo aísla a propósito (fuera de ventas,
// de cobranza y del P&L, con su propia cuenta contable) porque es corrección de un error
// nuestro y NO se cobra — facturarlo es cobrarle al cliente algo que le debíamos.
// Regresa null si se puede facturar, o el texto del motivo por el que no.
function _facturapiOrdenNoFacturable($pdo, $ordenFolio) {
    $stmt = $pdo->prepare("
        SELECT o.estado, COALESCE(c.es_retrabajo, 0) AS es_retrabajo, c.estatus AS cot_estatus
        FROM ordenes o
        LEFT JOIN cotizaciones c ON c.orden_id = o.id
        WHERE o.folio = ? LIMIT 1
    ");
    $stmt->execute([$ordenFolio]);
    $o = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$o) return 'No existe una orden con el folio ' . $ordenFolio . '.';

    if ((int)$o['es_retrabajo'] === 1) {
        return 'La orden ' . $ordenFolio . ' es un RETRABAJO: es la corrección de un trabajo previo '
             . 'y no se le cobra al cliente, así que no debe facturarse. Si de verdad hay que cobrarla, '
             . 'primero hay que quitarle la marca de retrabajo.';
    }
    $etiquetas = [
        'cancelada'      => 'está cancelada',
        'rechazada'      => 'fue rechazada',
        'pendiente_vobo' => 'todavía no tiene VoBo (la venta no está confirmada)',
    ];
    if (isset($etiquetas[$o['estado']])) {
        return 'La orden ' . $ordenFolio . ' ' . $etiquetas[$o['estado']] . ', no se puede facturar.';
    }
    if (!in_array($o['estado'], ['activa', 'entregada'], true)) {
        return 'La orden ' . $ordenFolio . ' está en estado "' . $o['estado'] . '" y no se puede facturar.';
    }
    if (in_array((string)$o['cot_estatus'], ['cancelada', 'rechazada'], true)) {
        return 'La cotización de origen de la orden ' . $ordenFolio . ' está ' . $o['cot_estatus'] . ', no se puede facturar.';
    }
    return null;
}

// ── Complementos de Pago (CFDI tipo P, 29-sep-2026) ─────────────────────────
// Una factura PPD obliga a emitir un Complemento de Pago por cada abono, a más tardar
// el día 5 del mes siguiente al pago. Cada complemento es una fila de `facturas`
// (tipo_cfdi='P', serie propia 'P', total=0 como el CFDI real, orden_folio NULL para no
// contar como "factura vigente de la orden" en los candados existentes) más una fila
// de `facturas_pagos` que lo liga con la factura PPD y con el abono de Cobranza.
// Un complemento está ACTIVO si su fila de facturas está 'timbrada' o 'timbrando'
// (una cancelación en trámite sigue contando: ante el SAT todavía existe).
define('FACTURAPI_SERIE_COMPLEMENTO', 'P');

// ── Anticipos de clientes — esquema A del SAT (Fase 2, 30-sep-2026) ──────────
// Apéndice 6 de la Guía de llenado del CFDI 4.0: el dinero que el cliente deja a cuenta
// (saldo a favor) es un ANTICIPO y se factura cuando se recibe, con un CFDI de ingreso de
// un solo concepto (84111506 / ACT / "Anticipo del bien o servicio"), PUE y la forma de
// pago real. Solo depósitos desde el arranque de la facturación en el sistema: los
// anteriores nunca se facturaron como anticipo (ver Fase 4 en CLAUDE.md).
if (!defined('ANTICIPOS_DESDE')) define('ANTICIPOS_DESDE', '2026-10-01');   // también en saldo_favor_lib.php
// CP del lugar de expedición (domicilio fiscal del emisor en FacturAPI). En una factura a
// Público en General el SAT exige que el CP del receptor sea este.
define('FACTURAPI_EMISOR_CP', '66367');
define('ANTICIPO_CLAVE', '84111506');
define('ANTICIPO_DESCRIPCION', 'Anticipo del bien o servicio');

// Forma de pago del SAT a partir de la del depósito; null si no se conoce.
function _facturapiFormaSatDeposito($forma, $tarjetaTipo) {
    if ($forma === 'efectivo')      return '01';
    if ($forma === 'cheque')        return '02';
    if ($forma === 'transferencia') return '03';
    if ($forma === 'tarjeta')       return $tarjetaTipo === 'debito' ? '28' : ($tarjetaTipo === 'credito' ? '04' : null);
    return null;
}

// Concepto único del anticipo. El total del CFDI debe ser EXACTAMENTE el dinero recibido,
// y dividir entre 1.16 con base a 2 decimales no cuadra en ~14% de los montos por el
// redondeo del IVA (probado con 200,000 montos). Por eso el precio va con el IVA incluido
// ('iva_incluido' → tax_included de FacturAPI) y el PAC desglosa la base: probado en
// sandbox, 1,000.03 y 1,000.10 timbran con total exacto. subtotal/iva son informativos
// (el total del PAC es el que se guarda al timbrar).
function _facturapiConceptoAnticipo($monto) {
    $monto = round((float)$monto, 2);
    $base  = round($monto / 1.16, 2);
    return [
        'conceptos' => [[
            'desc' => ANTICIPO_DESCRIPCION, 'clave' => ANTICIPO_CLAVE, 'unidad' => 'ACT',
            'cant' => 1, 'precio' => $monto, 'iva' => true, 'iva_incluido' => true,
        ]],
        'subtotal' => $base, 'iva' => round($monto - $base, 2), 'total' => $monto,
    ];
}

// ── Aplicación de anticipos a una orden (Fase 3, 30-sep-2026) ───────────────
// El saldo a favor es un monedero: no guarda qué depósito se gastó en qué orden. Se
// asigna PEPS (primero en entrar, primero en salir): cada consumo (fila negativa) toma
// de los depósitos más antiguos que tengan saldo, en orden de fecha e id. Regresa, por
// cada fila de consumo, los pedazos de depósito que la pagaron.
function _facturapiAsignacionPeps($pdo, $clienteId) {
    return sfAsignacionPeps($pdo, $clienteId);   // helpers/saldo_favor_lib.php (reintegros heredan su origen)
}

// Cómo se pagó con saldo a favor una orden, clasificado para el esquema A:
//  anticipos   → pedazos de depósitos con CFDI de anticipo vigente (van con relación 07
//                y nota de crédito), agrupados por CFDI de anticipo
//  sin_facturar→ depósitos desde ANTICIPOS_DESDE cuyo anticipo todavía no se factura
//                (bloquea: primero se factura el anticipo)
//  referido    → monto que vino de bonos de referido (Fase 4: descuento)
//  anterior    → saldo anterior al arranque o sin origen claro (Fase 4: factura normal)
function _facturapiAnticiposDeOrden($pdo, $ordenFolio) {
    $vacio = ['aplicado'=>0.0, 'anticipos'=>[], 'total_anticipos'=>0.0, 'sin_facturar'=>[], 'referido'=>0.0, 'anterior'=>0.0, 'previo_sin_revisar'=>[]];
    $stmt = $pdo->prepare("SELECT c.id, c.cliente_id FROM ordenes o JOIN cotizaciones c ON c.orden_id = o.id WHERE o.folio = ? LIMIT 1");
    $stmt->execute([$ordenFolio]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cot || !$cot['cliente_id']) return $vacio;
    // Lo aplicado HOY: pedazos de sus aplicaciones menos lo ya reintegrado (p. ej. una
    // corrección que bajó el total después de pagar con saldo a favor).
    $piezas = sfPiezasNetasCotizacion($pdo, $cot['id'], $cot['cliente_id']);
    if (!$piezas) return $vacio;
    $stAnt = $pdo->prepare("SELECT id, folio_interno, uuid FROM facturas
        WHERE saldo_favor_id = ? AND modo = ? AND estatus = 'timbrada' AND (pac_cancel_status IS NULL OR pac_cancel_status <> 'pending') ORDER BY id LIMIT 1");
    // Depósitos anteriores al arranque: Administración marca si se facturaron como anticipo
    // en CONTPAQi (UPD-629). Si sí, se relacionan (07) por su UUID igual que los de Apex.
    $stPrev = $pdo->prepare("SELECT previo_facturado, previo_folio, previo_uuid FROM clientes_saldo_favor WHERE id = ?");
    $r = $vacio; $porAnt = [];
    foreach ([$piezas] as $lista) {
        foreach ($lista as $pz) {
            $r['aplicado'] = round($r['aplicado'] + $pz['monto'], 2);
            if ($pz['tipo'] === 'referido') { $r['referido'] = round($r['referido'] + $pz['monto'], 2); continue; }
            if ($pz['tipo'] !== 'deposito') { $r['anterior'] = round($r['anterior'] + $pz['monto'], 2); continue; }
            if ($pz['fecha'] < ANTICIPOS_DESDE) {
                $stPrev->execute([$pz['deposito_id']]);
                $pv = $stPrev->fetch(PDO::FETCH_ASSOC);
                if (!$pv || $pv['previo_facturado'] === null) { $r['previo_sin_revisar'][] = $pz; continue; }
                if ((int)$pv['previo_facturado'] !== 1 || empty($pv['previo_uuid'])) { $r['anterior'] = round($r['anterior'] + $pz['monto'], 2); continue; }
                $k = 'ext-' . (int)$pz['deposito_id'];
                if (!isset($porAnt[$k])) $porAnt[$k] = ['anticipo_id'=>null, 'folio'=>'CONTPAQi ' . $pv['previo_folio'], 'uuid'=>$pv['previo_uuid'], 'saldo_favor_id'=>$pz['deposito_id'], 'monto'=>0.0];
                $porAnt[$k]['monto'] = round($porAnt[$k]['monto'] + $pz['monto'], 2);
                $r['total_anticipos'] = round($r['total_anticipos'] + $pz['monto'], 2);
                continue;
            }
            $stAnt->execute([$pz['deposito_id'], FACTURAPI_MODE]);
            $ant = $stAnt->fetch(PDO::FETCH_ASSOC);
            if (!$ant) { $r['sin_facturar'][] = $pz; continue; }
            $k = (int)$ant['id'];
            if (!isset($porAnt[$k])) $porAnt[$k] = ['anticipo_id'=>$k, 'folio'=>$ant['folio_interno'], 'uuid'=>$ant['uuid'], 'saldo_favor_id'=>$pz['deposito_id'], 'monto'=>0.0];
            $porAnt[$k]['monto'] = round($porAnt[$k]['monto'] + $pz['monto'], 2);
            $r['total_anticipos'] = round($r['total_anticipos'] + $pz['monto'], 2);
        }
    }
    $r['anticipos'] = array_values($porAnt);
    return $r;
}

// Formas de pago válidas para la factura del TOTAL de una orden con anticipo aplicado.
// Anexo 20 (Guía de llenado CFDI 4.0), Apéndice 6-A-II y caso de uso del Apéndice 8: la
// factura del total lleva la forma con la que se pagó la diferencia al concretar la venta
// (en el ejemplo oficial: anticipo con cheque, diferencia con cheque → 02), y la forma 30
// "Aplicación de anticipos" va en el CFDI de egreso. Con varias formas, la del mayor
// importe; si hay empate, "a su consideración una de las formas" (Anexo 20, campo
// FormaPago) → se aceptan todas las empatadas. Solo si el anticipo cubrió todo (no hubo
// pago en dinero) la forma es 30. Tarjeta sin tipo registrado (pagos anteriores al
// 29-sep-2026) acepta 04 o 28. Los pagos con saldo a favor no cuentan.
// Regresa la lista de claves válidas.
function _facturapiFormasValidasConAnticipo($pdo, $ordenFolio) {
    $stmt = $pdo->prepare("SELECT p.forma_pago, p.tarjeta_tipo, SUM(p.monto) m
        FROM ordenes o JOIN cotizaciones c ON c.orden_id = o.id JOIN cotizacion_pagos p ON p.cotizacion_id = c.id
        WHERE o.folio = ? AND p.forma_pago <> 'saldo_favor' AND p.monto > 0 GROUP BY p.forma_pago, p.tarjeta_tipo");
    $stmt->execute([$ordenFolio]);
    $sumas = []; $claves = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        if ($p['forma_pago'] === 'tarjeta' && !$p['tarjeta_tipo']) { $k = 'tarjeta?'; $cl = ['04','28']; }
        else { $sat = _facturapiFormaSatDeposito($p['forma_pago'], $p['tarjeta_tipo']); if (!$sat) continue; $k = $sat; $cl = [$sat]; }
        $sumas[$k] = round(($sumas[$k] ?? 0) + (float)$p['m'], 2);
        $claves[$k] = $cl;
    }
    if (!$sumas) return ['30'];
    $max = max($sumas);
    $validas = [];
    foreach ($sumas as $k => $m) { if (abs($m - $max) < 0.005) $validas = array_merge($validas, $claves[$k]); }
    return array_values(array_unique($validas));
}

define('FACTURAPI_SERIE_NOTA', 'N');

// Emite (o reintenta) la nota de crédito de la aplicación de anticipos de una factura
// timbrada: CFDI E, serie N, un concepto 84111506/ACT "Aplicación de anticipo" por el total
// aplicado (IVA incluido), forma 30, PUE, relación 07 a la factura. Si la factura todavía no
// tiene sus renglones en facturas_anticipos (se timbró por "Verificar timbrado"), los arma
// con la asignación actual. Regresa ['ok', 'folio'?, 'uuid'?, 'error'?, 'en_verificacion'?]
// u ['ok'=>true,'sin_anticipo'=>true] si no aplica. No llamar dentro de una transacción.
function _facturapiEmitirNotaAnticipo($pdo, $facturaId, $usuario) {
    $facturaId = (int)$facturaId;
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ? FOR UPDATE");
        $stmt->execute([$facturaId]);
        $fac = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fac || $fac['tipo_cfdi'] !== 'I' || empty($fac['orden_folio']) || $fac['estatus'] !== 'timbrada' || empty($fac['uuid'])) {
            $pdo->rollBack(); return ['ok'=>false, 'error'=>'Solo se emite nota de crédito de anticipo sobre una factura de orden timbrada.'];
        }
        if ($fac['modo'] !== FACTURAPI_MODE) { $pdo->rollBack(); return ['ok'=>false, 'error'=>'La factura es de otro modo (prueba/real).']; }

        $stmt = $pdo->prepare("SELECT * FROM facturas_anticipos WHERE factura_id = ?");
        $stmt->execute([$facturaId]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$filas) {
            $ant = _facturapiAnticiposDeOrden($pdo, $fac['orden_folio']);
            if (!$ant['anticipos']) { $pdo->rollBack(); return ['ok'=>true, 'sin_anticipo'=>true]; }
            $ins = $pdo->prepare("INSERT INTO facturas_anticipos (factura_id, anticipo_id, anticipo_uuid, saldo_favor_id, monto) VALUES (?,?,?,?,?)");
            foreach ($ant['anticipos'] as $a) $ins->execute([$facturaId, $a['anticipo_id'], $a['uuid'], $a['saldo_favor_id'], $a['monto']]);
            $stmt = $pdo->prepare("SELECT * FROM facturas_anticipos WHERE factura_id = ?");
            $stmt->execute([$facturaId]);
            $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        // ¿Ya hay nota vigente o en proceso?
        $stmt = $pdo->prepare("SELECT x.id, x.folio_interno, x.estatus, x.uuid FROM facturas_anticipos fa JOIN facturas x ON x.id = fa.nota_credito_id
            WHERE fa.factura_id = ? AND x.estatus IN ('timbrada','timbrando') LIMIT 1");
        $stmt->execute([$facturaId]);
        if ($ya = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            if ($ya['estatus'] === 'timbrando') return ['ok'=>false, 'en_verificacion'=>true, 'error'=>'La nota de crédito '.$ya['folio_interno'].' está en verificación: usa "Verificar timbrado" en ella.'];
            return ['ok'=>true, 'folio'=>$ya['folio_interno'], 'uuid'=>$ya['uuid'], 'existente'=>true];
        }

        $monto = 0.0; foreach ($filas as $f) $monto = round($monto + (float)$f['monto'], 2);
        $conc  = _facturapiConceptoAnticipo($monto);
        $conc['conceptos'][0]['desc'] = 'Aplicación de anticipo';
        $esPg  = (strtoupper((string)$fac['receptor_rfc']) === 'XAXX010101000');
        $serie = FACTURAPI_SERIE_NOTA;
        $stmt  = $pdo->prepare("SELECT COALESCE(MAX(folio_numero),0)+1 FROM facturas WHERE serie=? FOR UPDATE");
        $stmt->execute([$serie]);
        $folioNum = (int)$stmt->fetchColumn();
        $folioInterno = $serie . '-' . str_pad($folioNum, 3, '0', STR_PAD_LEFT);
        $pdo->prepare("
            INSERT INTO facturas
                (folio_interno, serie, folio_numero, orden_folio, tipo_cfdi, fecha,
                 receptor_nombre, receptor_rfc, receptor_cp, receptor_regimen, receptor_uso_cfdi, cliente_solicito_id,
                 forma_pago, metodo_pago, relacion_tipo, relacion_uuid,
                 conceptos, subtotal, iva, total, estatus, modo, creado_por)
            VALUES (?,?,?,NULL,'E',?,?,?,?,?,?,?,'30','PUE','07',?,?,?,?,?,'timbrando',?,?)
        ")->execute([$folioInterno, $serie, $folioNum, date('Y-m-d'),
            $fac['receptor_nombre'], $fac['receptor_rfc'], $fac['receptor_cp'], $fac['receptor_regimen'], ($esPg ? 'S01' : 'G02'),
            $fac['cliente_solicito_id'], $fac['uuid'],
            json_encode($conc['conceptos']), $conc['subtotal'], $conc['iva'], $conc['total'], FACTURAPI_MODE, $usuario]);
        $notaId = (int)$pdo->lastInsertId();
        $pdo->prepare("UPDATE facturas_anticipos SET nota_credito_id = ? WHERE factura_id = ?")->execute([$notaId, $facturaId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('APEX Nota anticipo: error al reservar factura id='.$facturaId.': '.$e->getMessage());
        return ['ok'=>false, 'error'=>'No se pudo preparar la nota de crédito. Intenta de nuevo.'];
    }

    $payload = [
        'type'           => 'E',
        'series'         => $serie,
        'folio_number'   => $folioNum,
        'use'            => $esPg ? 'S01' : 'G02',
        'payment_form'   => '30',
        'payment_method' => 'PUE',
        'customer'       => [
            'legal_name' => trim(preg_replace('/\s+/u', ' ', (string)$fac['receptor_nombre'])),
            'tax_id'     => $fac['receptor_rfc'],
            'tax_system' => $fac['receptor_regimen'],
            'address'    => ['zip' => $fac['receptor_cp']],
        ],
        'items' => [[
            'quantity' => 1,
            'product'  => [
                'description' => 'Aplicación de anticipo', 'product_key' => ANTICIPO_CLAVE, 'unit_key' => 'ACT',
                'price' => $conc['total'], 'tax_included' => true,
                'taxes' => [['type'=>'IVA', 'rate'=>0.16, 'factor'=>'Tasa']],
            ],
        ]],
        'related_documents' => [['relationship' => '07', 'documents' => [$fac['uuid']]]],
    ];
    $payload['external_id']     = _facturapiExternalId(['modo'=>FACTURAPI_MODE, 'tipo_cfdi'=>'E', 'id'=>$notaId]);
    $payload['idempotency_key'] = $payload['external_id'];

    $r = _facturapiLlamar('POST', 'https://www.facturapi.io/v2/invoices', $payload);
    if (_facturapiRespuestaAmbigua($r['err'], $r['code'], $r['res'])) {
        _facturapiLogError('nota anticipo AMBIGUO id='.$notaId, $r);
        return ['ok'=>false, 'en_verificacion'=>true, 'folio'=>$folioInterno, 'error'=>_facturapiMsgAmbiguo($r['code'], $r['res'])];
    }
    if ($r['code'] !== 200) {
        _facturapiLogError('nota anticipo error id='.$notaId, $r);
        $pdo->prepare("DELETE FROM facturas WHERE id = ? AND estatus = 'timbrando'")->execute([$notaId]);
        return ['ok'=>false, 'error'=>'FacturAPI: '._facturapiMensajeError($r)];
    }
    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id = ?");
    $stmt->execute([$notaId]);
    $t = _facturapiRegistrarTimbre($pdo, $stmt->fetch(PDO::FETCH_ASSOC), $r['res'], $usuario);
    return ['ok'=>true, 'folio'=>$folioInterno, 'uuid'=>$t['uuid'], 'monto'=>$conc['total']];
}

// Factura de anticipo vigente (o en proceso) de un depósito, en el modo actual.
function _facturapiAnticipoVigente($pdo, $saldoFavorId, $excluirId = 0) {
    $stmt = $pdo->prepare("SELECT id, folio_interno, estatus FROM facturas
        WHERE saldo_favor_id = ? AND id <> ? AND modo = ? AND estatus IN ('borrador','timbrando','timbrada')
        ORDER BY id LIMIT 1");
    $stmt->execute([(int)$saldoFavorId, (int)$excluirId, FACTURAPI_MODE]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Formas de pago del catálogo SAT válidas en un complemento (99 "Por definir" NO se
// permite en un pago: el pago ya ocurrió, se sabe cómo fue).
function _facturapiFormasPagoComplemento() {
    return ['01','02','03','04','05','06','08','12','13','14','15','17','23','24','25','26','27','28','29','30','31'];
}

// Forma de pago sugerida a partir de lo capturado en Cobranza. 'tarjeta' no distingue
// crédito de débito, se sugiere 04 y el usuario la corrige si fue débito (28).
// Saldo a favor no tiene equivalente automático (depende del esquema de anticipos que
// defina el contador), así que no se sugiere nada y se obliga a escoger.
function _facturapiFormaSugerida($formaCobranza, $tarjetaTipo = null) {
    if ($formaCobranza === 'tarjeta' && $tarjetaTipo === 'debito') return '28';
    $map = ['efectivo'=>'01', 'transferencia'=>'03', 'tarjeta'=>'04'];
    return $map[$formaCobranza] ?? '';
}

// Forma SAT que se puede usar SIN intervención humana para el complemento automático.
// Solo cuando no hay duda: tarjeta sin tipo (pagos anteriores al 29-sep-2026) y saldo a
// favor (depende del esquema de anticipos del contador) regresan null → emisión manual.
function _facturapiFormaAutomatica($pago) {
    $f = $pago['forma_pago'] ?? '';
    if ($f === 'efectivo')      return '01';
    if ($f === 'transferencia') return '03';
    if ($f === 'tarjeta') {
        $t = $pago['tarjeta_tipo'] ?? null;
        if ($t === 'credito') return '04';
        if ($t === 'debito')  return '28';
    }
    return null;
}

// Día límite para emitir el complemento de un pago: día 5 del mes siguiente.
function _facturapiLimiteComplemento($fechaPago) {
    $d = DateTime::createFromFormat('Y-m-d', substr((string)$fechaPago, 0, 10));
    if (!$d) return null;
    $d->modify('first day of next month');
    return $d->format('Y-m') . '-05';
}

// Abonos de Cobranza de la orden de una factura PPD, cada uno con su complemento activo
// (si existe), más el saldo de la factura según los complementos activos.
function _facturapiEstadoPagos($pdo, $fac) {
    $stmt = $pdo->prepare("
        SELECT p.id, p.fecha_pago, p.hora_pago, p.monto, p.forma_pago, p.tarjeta_tipo, p.notas
        FROM cotizacion_pagos p
        JOIN cotizaciones c ON c.id = p.cotizacion_id
        JOIN ordenes o      ON o.id = c.orden_id
        WHERE o.folio = ? AND p.monto > 0
        ORDER BY p.fecha_pago, p.hora_pago, p.id
    ");
    $stmt->execute([$fac['orden_folio']]);
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT fp.*, x.folio_interno, x.estatus, x.uuid, x.pac_cancel_status
        FROM facturas_pagos fp
        JOIN facturas x ON x.id = fp.complemento_id
        WHERE fp.factura_id = ?
        ORDER BY fp.id
    ");
    $stmt->execute([$fac['id']]);
    $comps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $activos = [];      // cotizacion_pago_id => complemento activo
    $sumActivo = 0.0;
    $numActivos = 0;
    foreach ($comps as $c) {
        if (!in_array($c['estatus'], ['timbrada','timbrando'], true)) continue;
        $numActivos++;
        $sumActivo += (float)$c['monto'];
        if ($c['cotizacion_pago_id'] !== null) $activos[(int)$c['cotizacion_pago_id']] = $c;
    }

    $fechaFactura = substr((string)($fac['fecha_timbrado'] ?: $fac['fecha']), 0, 10);
    foreach ($pagos as &$p) {
        $c = $activos[(int)$p['id']] ?? null;
        $p['complemento'] = $c ? [
            'id' => (int)$c['complemento_id'], 'folio' => $c['folio_interno'], 'estatus' => $c['estatus'],
            'parcialidad' => (int)$c['parcialidad'], 'pac_cancel_status' => $c['pac_cancel_status'],
        ] : null;
        $p['forma_sugerida']   = _facturapiFormaSugerida($p['forma_pago'], $p['tarjeta_tipo'] ?? null);
        $p['antes_de_factura'] = ($p['fecha_pago'] < $fechaFactura);
        $p['fecha_limite']     = _facturapiLimiteComplemento($p['fecha_pago']);
    }
    unset($p);

    return [
        'pagos'                => $pagos,
        'complementos'         => $comps,
        'total_factura'        => round((float)$fac['total'], 2),
        'pagado_complementado' => round($sumActivo, 2),
        'saldo'                => round((float)$fac['total'] - $sumActivo, 2),
        'siguiente_parcialidad'=> $numActivos + 1,
    ];
}

// ¿La orden ya está pagada completa? Si sí, el SAT exige método PUE (no PPD).
function _facturapiOrdenLiquidada($pdo, $ordenFolio) {
    $stmt = $pdo->prepare("
        SELECT c.total, COALESCE((SELECT SUM(p.monto) FROM cotizacion_pagos p WHERE p.cotizacion_id = c.id), 0) AS pagado
        FROM ordenes o JOIN cotizaciones c ON c.orden_id = o.id
        WHERE o.folio = ? LIMIT 1
    ");
    $stmt->execute([$ordenFolio]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r || (float)$r['total'] <= 0) return false;
    return (float)$r['pagado'] >= (float)$r['total'] - 0.005;
}

// Descarga un archivo (PDF/XML) de FacturAPI autenticado; regresa el binario o null si falla
function _descargarArchivoFacturapi($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>['Authorization: Bearer '.FACTURAPI_KEY], CURLOPT_TIMEOUT=>20]);
    $bin = curl_exec($ch);
    if (curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) $bin = null;
    unset($ch);
    return $bin;
}

// ENVIO DE CFDI POR CORREO — APAGADO A PROPOSITO (Armando, 26-sep-2026).
// Se baja la feature completa hasta el final del proyecto de facturacion. Motivo: al
// probar el modulo en vivo se descubrio que el correo del receptor viene pre-llenado del
// CRM, asi que timbrar en sandbox le mando a un cliente REAL un CFDI de pruebas adjunto.
// Mismo patron que RUTA_WA_AVISOS_ACTIVO en rutas_lib.php: para reactivarlo basta poner
// esta constante en true (y entonces sigue vigente el segundo candado de abajo, que
// impide enviar mientras FACTURAPI_MODE no sea 'live').
define('FACTURACION_ENVIO_CORREO_ACTIVO', false);

// S2-a: resguardo propio del comprobante. Los CFDI viven en FacturAPI, pero el SAT
// obliga a conservar el XML 5 años y la caída de suscripción del 24-sep-2026 dejó claro
// que depender de ellos significa perder acceso a nuestros propios comprobantes. Se
// guarda una copia en archivos_facturas/ (protegida con .htaccess deny) y el proxy de
// descarga la sirve de ahí, pegando a FacturAPI solo si el archivo local falta.
// Regresa la ruta relativa guardada, o null si no se pudo escribir (nunca lanza: el
// timbrado ya ocurrió ante el SAT y no debe fallar por un problema de disco).
if (!defined('APEX_DIR_FACTURAS')) define('APEX_DIR_FACTURAS', __DIR__ . '/../../archivos_facturas');

function _guardarArchivoFacturaLocal($bin, $folioInterno, $ext) {
    if ($bin === null || $bin === '') return null;
    // Nombre determinista y sin datos del cliente: folio interno + año-mes de guardado.
    $nombre = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$folioInterno) . '.' . $ext;
    $subdir = APEX_DIR_FACTURAS . '/' . date('Y-m');
    if (!is_dir($subdir)) {
        if (!@mkdir($subdir, 0775, true)) {
            error_log('APEX Facturacion: no se pudo crear '.$subdir);
            return null;
        }
        // El modo de mkdir() lo recorta el umask del proceso (PHP-FPM corre con 022, asi
        // que 0775 acababa en 0755 y la carpeta quedaba escribible SOLO por su dueno).
        // Se fuerza explicitamente para que el grupo tambien pueda mantenerla — sin esto,
        // cualquier limpieza o respaldo hecho por otro usuario del grupo falla en silencio.
        @chmod($subdir, 02775);
    }
    $ruta = $subdir . '/' . $nombre;
    if (@file_put_contents($ruta, $bin) === false) {
        error_log('APEX Facturacion: no se pudo escribir '.$ruta);
        return null;
    }
    @chmod($ruta, 0664);
    return date('Y-m') . '/' . $nombre;   // ruta relativa a archivos_facturas/
}

// ── Timbrado con respuesta ambigua (auditoría complementos, 29-sep-2026) ──────
// Si FacturAPI timbra pero su respuesta no llega (timeout, conexión cortada, 5xx), el
// CFDI YA existe ante el SAT aunque nosotros no lo sepamos. Antes se liberaba la reserva
// en esos casos y el usuario podía volver a timbrar → dos CFDI de la misma venta o dos
// complementos del mismo abono. Ahora la fila se queda en 'timbrando' ("en verificación")
// y accion=verificar_timbrado consulta a FacturAPI por serie+folio antes de decidir.
// Un 4xx sí es rechazo definitivo (el PAC no creó nada) y se libera como siempre.
function _facturapiRespuestaAmbigua($curlErr, $httpCode, $res) {
    if ($curlErr) return true;
    if ($httpCode === 0 || $httpCode >= 500) return true;
    // 202 = intermitencia del SAT (guía oficial "Intermitencias", 29-sep-2026): FacturAPI
    // guarda el CFDI en 'pending' con el folio reservado y lo reintenta solo hasta 50 min.
    // Puede terminar timbrado, así que NO es un rechazo: antes se tomaba como error y se
    // liberaba el folio, lo que permitía timbrar de nuevo y dejar dos CFDI de la misma venta.
    if ($httpCode === 202) return true;
    if ($httpCode === 200 && (empty($res['uuid']) || ($res['status'] ?? '') === 'pending')) return true;
    // 409 idempotency_key_in_use = esa misma petición ya creó un CFDI antes (verificado en
    // sandbox: FacturAPI no duplica, responde 409). Se resuelve con "Verificar timbrado".
    if ($httpCode === 409 && is_array($res) && ($res['code'] ?? '') === 'idempotency_key_in_use') return true;
    return false;
}

// Mensaje para el usuario según el tipo de respuesta ambigua.
function _facturapiMsgAmbiguo($httpCode, $res) {
    if ($httpCode === 202 || ($httpCode === 200 && ($res['status'] ?? '') === 'pending')) return FACTURAPI_MSG_PENDIENTE_SAT;
    return FACTURAPI_MSG_EN_VERIFICACION;
}

// ── Llamadas a FacturAPI (buenas prácticas de su documentación oficial, 29-sep-2026) ──
// Un solo punto para las llamadas que timbran, cancelan o consultan: manda Accept-Language
// y conserva el header X-Facturapi-Log-Id (el id que pide soporte de FacturAPI para
// rastrear un incidente) y Retry-After (viene con el 429 de exceso de peticiones).
// Regresa ['code','res','raw','err','log_id','retry_after']. Nunca lanza.
function _facturapiLlamar($metodo, $url, $payload = null, $llave = null, $timeout = 30) {
    $logId = ''; $retry = null;
    $headers = ['Authorization: Bearer ' . ($llave ?? FACTURAPI_KEY), 'Accept-Language: es'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HEADERFUNCTION => function($ch, $h) use (&$logId, &$retry) {
            $par = explode(':', $h, 2);
            if (count($par) === 2) {
                $n = strtolower(trim($par[0]));
                if ($n === 'x-facturapi-log-id') $logId = trim($par[1]);
                elseif ($n === 'retry-after')    $retry = (int)trim($par[1]);
            }
            return strlen($h);
        },
    ];
    if ($payload !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
        $headers[] = 'Content-Type: application/json';
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    unset($ch);
    return ['code'=>$code, 'res'=>json_decode((string)$raw, true), 'raw'=>(string)$raw,
            'err'=>$err, 'log_id'=>$logId, 'retry_after'=>$retry];
}

// Mensaje legible de un error de FacturAPI: el mensaje general más el detalle y el código
// del SAT/PAC que vienen en errors[] (antes solo se mostraba el mensaje general).
function _facturapiMensajeError($r) {
    $res = is_array($r['res']) ? $r['res'] : [];
    if ($r['code'] === 429 || ($res['code'] ?? '') === 'rate_limit_exceeded') {
        return 'FacturAPI está recibiendo demasiadas peticiones. Espera '.max(1, (int)$r['retry_after']).' segundos e intenta de nuevo.';
    }
    $msg = $res['message'] ?? $res['error'] ?? 'Error desconocido de FacturAPI';
    $extra = []; $codigoExt = '';
    foreach (($res['errors'] ?? []) as $e) {
        if (!is_array($e)) continue;
        $fuente = $e['source'] ?? '';
        if ($codigoExt === '' && in_array($fuente, ['sat','pac'], true) && !empty($e['code'])) {
            $codigoExt = strtoupper($fuente).' '.$e['code'];
        }
        if (!empty($e['message']) && $e['message'] !== $msg && count($extra) < 3) $extra[] = $e['message'];
    }
    return $msg.($extra ? ' — '.implode('; ', $extra) : '').($codigoExt !== '' ? ' ['.$codigoExt.']' : '');
}

// Deja en el log una llamada fallida con el id de soporte de FacturAPI.
function _facturapiLogError($contexto, $r) {
    error_log('APEX FacturAPI '.$contexto.' HTTP '.$r['code'].($r['log_id'] !== '' ? ' log_id='.$r['log_id'] : '')
        .($r['err'] ? ' curl: '.$r['err'] : '').' — '.substr($r['raw'], 0, 500));
}

// Identificadores que se mandan con cada CFDI (documentación oficial):
//  - external_id: nuestro id, para encontrar el CFDI exacto en FacturAPI al verificar
//    (antes solo se podía por serie+folio, que se repiten en pruebas al borrar).
//  - idempotency_key: único por RESERVA de timbrado. Si la misma petición llega dos veces,
//    FacturAPI no crea un segundo CFDI (responde 409). Probado en sandbox el 29-sep-2026:
//    un rechazo (4xx) NO gasta la clave. Se amarra a la reserva y no solo a la fila porque
//    un CFDI que FacturAPI marca 'failed' tras una intermitencia podría conservarla y
//    bloquear para siempre el re-timbrado de esa factura.
function _facturapiExternalId($fac) {
    return 'apex-'.$fac['modo'].'-'.$fac['tipo_cfdi'].'-'.(int)$fac['id'];
}
function _facturapiIdempotencyKey($fac) {
    return _facturapiExternalId($fac).'-'.preg_replace('/\D/', '', (string)$fac['updated_at']);
}

// Busca en FacturAPI el CFDI de una reserva en 'timbrando', creado a partir de $desde (hora
// local de la reserva, con 5 min de margen por desfase de relojes). Primero por
// external_id y, si no aparece (reservas anteriores al 29-sep-2026 no lo mandaban), por
// serie+folio. Regresa el CFDI vigente, 'pendiente' si FacturAPI lo tiene en espera del
// SAT (intermitencia, no se debe liberar el folio), null si con certeza no existe, o false
// si no se pudo consultar.
function _facturapiBuscarCfdi($fac, $desde) {
    try { $tsDesde = (new DateTime($desde, new DateTimeZone('America/Monterrey')))->getTimestamp() - 300; }
    catch (Exception $e) { return false; }
    $base = 'https://www.facturapi.io/v2/invoices?';
    $consultas = [
        $base.'external_id='.urlencode(_facturapiExternalId($fac)),
        $base.'series='.urlencode($fac['serie']).'&folio_number='.(int)$fac['folio_numero'],
    ];
    $pendiente = false;
    foreach ($consultas as $url) {
        $r = _facturapiLlamar('GET', $url, null, null, 20);
        if ($r['code'] !== 200 || !isset($r['res']['data']) || !is_array($r['res']['data'])) {
            _facturapiLogError('buscar CFDI factura id='.(int)$fac['id'], $r);
            return false;
        }
        foreach ($r['res']['data'] as $inv) {
            if (strtotime($inv['created_at'] ?? '') < $tsDesde) continue;
            $st = $inv['status'] ?? '';
            if ($st === 'valid' && !empty($inv['uuid'])) return $inv;
            if ($st === 'pending') $pendiente = true;
        }
        if ($pendiente) return 'pendiente';
    }
    return null;
}

// Traduce la respuesta de FacturAPI a nuestro pac_cancel_status (guía oficial
// "Cancelaciones"). La factura trae DOS campos: status (valid/canceled) y
// cancellation_status (none/verifying/pending/accepted/rejected). Antes se guardaba
// 'status', así que una cancelación en espera del receptor quedaba como 'valid': la
// pantalla dejaba de mostrarla en trámite y los candados que miran 'pending' no la veían.
// 'verifying' (el SAT la está validando) se trata igual que 'pending': en trámite.
function _facturapiEstadoCancelacion($res) {
    if (($res['status'] ?? '') === 'canceled') return 'canceled';
    $cs = $res['cancellation_status'] ?? '';
    if ($cs === 'accepted') return 'canceled';
    if ($cs === 'rejected') return 'rejected';
    if ($cs === 'none')     return 'none';
    return 'pending';
}

// Registra en la fila (en 'timbrando') el CFDI que devolvió FacturAPI: UUID, total del
// PAC, liga de verificación, fecha real del timbre y resguardo local de PDF/XML. Lo usan
// el timbrado de facturas, el de complementos y verificar_timbrado.
function _facturapiRegistrarTimbre($pdo, $fac, $res, $usuario) {
    $id          = (int)$fac['id'];
    $uuid        = $res['uuid'] ?? '';
    $facturapiId = $res['id']   ?? '';
    $pdfUrl      = 'https://www.facturapi.io/v2/invoices/' . $facturapiId . '/pdf';
    $xmlUrl      = 'https://www.facturapi.io/v2/invoices/' . $facturapiId . '/xml';

    // S2-b: el PAC es la verdad, no nuestro cálculo. Antes se guardaba el total que
    // calculamos nosotros y nunca se comparaba contra el del comprobante: si el PAC
    // redondeaba distinto, BD y CFDI quedaban desalineados sin que nadie se enterara.
    // Ahora se guarda el total del PAC y la diferencia queda en el log si existe.
    // (FacturAPI no devuelve 'subtotal' a nivel raíz, solo 'total' — verificado contra
    // la API real el 26-sep-2026; el subtotal/IVA nuestros se conservan tal cual.)
    $totalPac = isset($res['total']) ? round((float)$res['total'], 2) : null;
    if ($totalPac !== null && abs($totalPac - (float)$fac['total']) > 0.005) {
        error_log('APEX Facturacion: total del PAC ('.$totalPac.') distinto al calculado ('
            .$fac['total'].') en factura id='.$id.' — se guarda el del PAC');
    }
    // Datos del timbre que antes se tiraban: la liga de verificación del SAT (no se
    // puede reconstruir sola, incluye parte del sello) y la fecha REAL del timbrado
    // según el PAC, que no es la misma que la fecha capturada en el formulario.
    $verifUrl = $res['verification_url'] ?? null;
    $fechaTim = null;
    if (!empty($res['date'])) {
        try { $fechaTim = (new DateTime($res['date']))->setTimezone(new DateTimeZone('America/Monterrey'))->format('Y-m-d H:i:s'); }
        catch (Exception $e) { $fechaTim = null; }
    }

    // S2-a: una sola descarga de PDF/XML sirve para el resguardo local Y para el correo
    // (antes solo se descargaban si había correos que notificar, y no se guardaban).
    $pdfBin  = _descargarArchivoFacturapi($pdfUrl);
    $xmlBin  = _descargarArchivoFacturapi($xmlUrl);
    $pdfPath = _guardarArchivoFacturaLocal($pdfBin, $fac['folio_interno'], 'pdf');
    $xmlPath = _guardarArchivoFacturaLocal($xmlBin, $fac['folio_interno'], 'xml');
    if ($xmlPath === null) {
        // No se aborta (el CFDI ya existe ante el SAT) pero sí se deja constancia fuerte:
        // sin el XML en disco volvemos a depender de FacturAPI para conservarlo.
        error_log('APEX Facturacion: OJO, no se pudo resguardar el XML de la factura id='.$id
            .' — el comprobante solo existe en FacturAPI');
    }

    // El UPDATE final confirma desde la reserva 'timbrando' (A-9b) — si por alguna
    // razón la factura ya no estuviera en ese estatus, no se sobreescribe nada.
    $stmt = $pdo->prepare("
        UPDATE facturas SET
            estatus='timbrada', facturapi_id=?, uuid=?,
            pdf_url=?, xml_url=?, pdf_path=?, xml_path=?,
            verification_url=?, fecha_timbrado=?,
            total = COALESCE(?, total),
            timbrado_por=?, timbrado_at=NOW(), updated_at=NOW()
        WHERE id=? AND estatus='timbrando'
    ");
    $stmt->execute([$facturapiId, $uuid, $pdfUrl, $xmlUrl, $pdfPath, $xmlPath,
                    $verifUrl, $fechaTim, $totalPac, $usuario, $id]);

    return ['uuid'=>$uuid, 'facturapi_id'=>$facturapiId, 'pdf_url'=>$pdfUrl, 'xml_url'=>$xmlUrl,
            'total_pac'=>$totalPac, 'pdf_bin'=>$pdfBin, 'xml_bin'=>$xmlBin];
}

// Mensaje único para el caso ambiguo (la fila se queda 'timbrando').
define('FACTURAPI_MSG_PENDIENTE_SAT', 'El SAT tuvo una falla temporal y FacturAPI dejó el comprobante en espera: lo reintenta solo durante '
    .'la próxima hora y puede quedar timbrado. Se dejó "en verificación" para no emitirlo dos veces: en una hora abre el detalle '
    .'y pulsa "Verificar timbrado". NO lo vuelvas a capturar.');
define('FACTURAPI_MSG_EN_VERIFICACION', 'FacturAPI no respondió con claridad, así que no se sabe si el comprobante quedó timbrado. '
    .'Se dejó "en verificación" para no emitirlo dos veces: en un par de minutos abre el detalle y pulsa "Verificar timbrado". '
    .'NO lo vuelvas a capturar.');

// Crea y timbra el CFDI tipo P de UN abono (ver accion=emitir_complemento). Regresa
// ['ok'=>bool, 'error'?, 'en_verificacion'?, 'folio'?, 'uuid'?, 'parcialidad'?, ...].
// No se debe llamar dentro de una transacción abierta (abre la suya).
function _facturapiEmitirComplemento($pdo, $facturaId, $pagoId, $forma, $usuario) {
    $facturaId = (int)$facturaId; $pagoId = (int)$pagoId; $forma = (string)$forma;
    if (!$facturaId || !$pagoId) return ['ok'=>false,'error'=>'Faltan la factura o el pago'];
    if (!in_array($forma, _facturapiFormasPagoComplemento(), true)) {
        return ['ok'=>false,'error'=>'Escoge una forma de pago válida del catálogo del SAT.'];
    }

    // Reserva: bajo candado de la fila de la factura PPD se valida, se calcula saldo y
    // parcialidad, y se inserta el complemento en 'timbrando'. Dos clics simultáneos
    // sobre la misma factura quedan en fila; el segundo ve el complemento del primero.
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=? FOR UPDATE");
        $stmt->execute([$facturaId]);
        $fac = $stmt->fetch(PDO::FETCH_ASSOC);
        $falla = null;
        if (!$fac || $fac['tipo_cfdi'] !== 'I' || $fac['metodo_pago'] !== 'PPD' || empty($fac['orden_folio'])) {
            $falla = 'Solo se emiten complementos de facturas PPD ligadas a una orden.';
        } elseif ($fac['estatus'] !== 'timbrada' || empty($fac['uuid'])) {
            $falla = 'La factura '.$fac['folio_interno'].' no está timbrada.';
        } elseif ($fac['pac_cancel_status'] === 'pending') {
            $falla = 'La factura '.$fac['folio_interno'].' tiene una cancelación en trámite ante el SAT.';
        } elseif ($fac['modo'] !== FACTURAPI_MODE) {
            // Una factura de pruebas no tiene validez ante el SAT: su complemento no puede
            // salir en modo real (ni al revés). Pasa al cambiar FACTURAPI_MODE a live con
            // facturas de sandbox todavía en la tabla.
            $falla = 'La factura '.$fac['folio_interno'].' se emitió en modo '.($fac['modo'] === 'test' ? 'prueba' : 'real')
                .' y el sistema está en modo '.(FACTURAPI_MODE === 'test' ? 'prueba' : 'real').'; no se le puede emitir complemento.';
        }

        if (!$falla) {
            // El IVA del pago se desglosa en proporción al de la factura. Todas las facturas
            // de hoy son 100% gravadas al 16% o 100% sin IVA; una mezcla requeriría dos
            // renglones de impuesto por pago y no se emite a ciegas.
            $conceptos = json_decode($fac['conceptos'], true) ?: [];
            $conIva = 0; foreach ($conceptos as $c) { if (!empty($c['iva'])) $conIva++; }
            if ($conIva > 0 && $conIva < count($conceptos)) {
                $falla = 'La factura mezcla conceptos con y sin IVA; ese caso todavía no se soporta en complementos.';
            }
        }

        if (!$falla) {
            $estado = _facturapiEstadoPagos($pdo, $fac);
            $pago = null;
            foreach ($estado['pagos'] as $p) { if ((int)$p['id'] === $pagoId) { $pago = $p; break; } }
            if (!$pago) {
                $falla = 'Ese abono no pertenece a la orden '.$fac['orden_folio'].'.';
            } elseif ($pago['complemento']) {
                $falla = 'Ese abono ya tiene el complemento '.$pago['complemento']['folio'].'.';
            } else {
                // Los complementos se emiten en orden cronológico: la parcialidad y el
                // saldo anterior tienen que encadenar con la fecha de los pagos (el SAT
                // valida que los saldos cuadren). $estado['pagos'] ya viene ordenado por
                // fecha, hora e id, igual que la lista de la pantalla.
                foreach ($estado['pagos'] as $p) {
                    if ((int)$p['id'] === $pagoId) break;
                    if (!$p['complemento']) {
                        $falla = 'Primero emite el complemento del abono del '.date('d/m/Y', strtotime($p['fecha_pago']))
                            .' ($'.number_format((float)$p['monto'], 2).'); los complementos van en orden de fecha.';
                        break;
                    }
                }
            }
        }

        if (!$falla) {
            $saldoAnt = $estado['saldo'];
            $monto    = round((float)$pago['monto'], 2);
            // Tolerancia de centavos: el total de la factura es el del PAC, que puede
            // redondear 1-2 centavos distinto al total de la orden (UPD-606); el último
            // abono se ajusta al saldo exacto de la factura en vez de rechazarlo.
            if ($monto > $saldoAnt + 0.005) {
                if ($monto - $saldoAnt <= 0.05 && $saldoAnt > 0) {
                    error_log('APEX Complemento: abono '.$pagoId.' ($'.$monto.') ajustado al saldo de la factura id='.$facturaId.' ($'.$saldoAnt.')');
                    $monto = $saldoAnt;
                } else {
                    $falla = 'El abono ($'.number_format($monto,2).') es mayor al saldo pendiente de la factura ($'
                        .number_format($saldoAnt,2).'). Revisa los complementos ya emitidos.';
                }
            }
        }

        if ($falla) { $pdo->rollBack(); return ['ok'=>false,'error'=>$falla]; }

        $parcialidad  = $estado['siguiente_parcialidad'];
        $saldoInsol   = round($saldoAnt - $monto, 2);
        $serie        = FACTURAPI_SERIE_COMPLEMENTO;
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(folio_numero),0)+1 FROM facturas WHERE serie=? FOR UPDATE");
        $stmt->execute([$serie]);
        $folioNum     = (int)$stmt->fetchColumn();
        $folioInterno = $serie . '-' . str_pad($folioNum, 3, '0', STR_PAD_LEFT);

        // metodo_pago es NOT NULL en la tabla; en un CFDI P no existe, se guarda 'PPD'
        // porque es el método de la factura que se está pagando.
        $stmt = $pdo->prepare("
            INSERT INTO facturas
                (folio_interno, serie, folio_numero, orden_folio, tipo_cfdi, fecha,
                 receptor_nombre, receptor_rfc, receptor_cp, receptor_regimen, receptor_uso_cfdi,
                 forma_pago, metodo_pago, conceptos, subtotal, iva, total, estatus, modo, creado_por)
            VALUES (?,?,?,NULL,'P',?,?,?,?,?,'CP01',?,'PPD','[]',0,0,0,'timbrando',?,?)
        ");
        $stmt->execute([$folioInterno, $serie, $folioNum, date('Y-m-d'),
            $fac['receptor_nombre'], $fac['receptor_rfc'], $fac['receptor_cp'], $fac['receptor_regimen'],
            $forma, FACTURAPI_MODE, $usuario]);
        $compId = (int)$pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO facturas_pagos
                (complemento_id, factura_id, cotizacion_pago_id, parcialidad, saldo_anterior, monto, saldo_insoluto, fecha_pago, forma_pago)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([$compId, $facturaId, $pagoId, $parcialidad, $saldoAnt, $monto, $saldoInsol, $pago['fecha_pago'], $forma]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('APEX Complemento: error al reservar: '.$e->getMessage());
        return ['ok'=>false,'error'=>'No se pudo preparar el complemento. Intenta de nuevo.'];
    }

    // Cualquier fallo antes de quedar timbrado borra la reserva (la FK en cascada se
    // lleva la fila de facturas_pagos) y libera el folio.
    $abortarComp = function($msg) use ($pdo, $compId) {
        $pdo->prepare("DELETE FROM facturas WHERE id=? AND estatus='timbrando'")->execute([$compId]);
        return ['ok'=>false,'error'=>$msg];
    };

    $taxes = [];
    if ((float)$fac['iva'] > 0) {
        $taxes[] = ['base' => round($monto / 1.16, 2), 'type' => 'IVA', 'rate' => 0.16];
    }
    $hora = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', (string)$pago['hora_pago']) ? substr($pago['hora_pago'].':00', 0, 8) : '12:00:00';
    $payload = [
        'type'         => 'P',
        'series'       => $serie,
        'folio_number' => $folioNum,
        'customer'     => [
            'legal_name' => trim(preg_replace('/\s+/u', ' ', (string)$fac['receptor_nombre'])),
            'tax_id'     => $fac['receptor_rfc'],
            'tax_system' => $fac['receptor_regimen'],
            'address'    => ['zip' => $fac['receptor_cp']],
        ],
        'complements' => [[
            'type' => 'pago',
            'data' => [[
                'payment_form' => $forma,
                'date'         => $pago['fecha_pago'] . 'T' . $hora,
                'related_documents' => [[
                    'uuid'         => $fac['uuid'],
                    'series'       => $fac['serie'],
                    'folio_number' => (int)$fac['folio_numero'],
                    'amount'       => $monto,
                    'installment'  => $parcialidad,
                    'last_balance' => $saldoAnt,
                    'taxes'        => $taxes,
                ]],
            ]],
        ]],
    ];

    // Un complemento reservado es siempre una fila nueva, así que su external_id ya es
    // único por reserva y sirve también de idempotency_key.
    $payload['external_id']     = _facturapiExternalId(['modo'=>FACTURAPI_MODE, 'tipo_cfdi'=>'P', 'id'=>$compId]);
    $payload['idempotency_key'] = $payload['external_id'];

    $r = _facturapiLlamar('POST', 'https://www.facturapi.io/v2/invoices', $payload);
    $httpCode = $r['code']; $res = $r['res'];
    if (_facturapiRespuestaAmbigua($r['err'], $httpCode, $res)) {
        // No se borra la reserva: el complemento pudo quedar emitido. Mientras siga en
        // 'timbrando' cuenta como activo y el abono no se vuelve a ofrecer.
        _facturapiLogError('complemento AMBIGUO id='.$compId, $r);
        return ['ok'=>false, 'en_verificacion'=>true, 'id'=>$compId, 'folio'=>$folioInterno, 'error'=>_facturapiMsgAmbiguo($httpCode, $res)];
    }
    if ($httpCode !== 200) {
        _facturapiLogError('complemento error id='.$compId, $r);
        return $abortarComp('FacturAPI: '._facturapiMensajeError($r));
    }

    $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=?");
    $stmt->execute([$compId]);
    $t = _facturapiRegistrarTimbre($pdo, $stmt->fetch(PDO::FETCH_ASSOC), $res, $usuario);
    $uuid = $t['uuid'];

    return [
        'ok'=>true, 'id'=>$compId, 'folio'=>$folioInterno, 'uuid'=>$uuid, 'modo'=>FACTURAPI_MODE,
        'parcialidad'=>$parcialidad, 'saldo_anterior'=>$saldoAnt, 'monto'=>$monto, 'saldo_insoluto'=>$saldoInsol,
    ];
}

// Cancela ante el SAT (vía FacturAPI) un CFDI timbrado y refleja el resultado en BD.
// Motivo 01 exige el UUID de la sustituta. Regresa ['ok','estatus','firme'] o ['ok'=>false,'error'].
// Lo usan accion=cancelar y la cancelación automática de la original tras timbrar una sustitución.
function _facturapiCancelarEnPac($pdo, $fac, $motivo, $sustitucion, $usuario) {
    // El SAT no deja cancelar una factura PPD mientras tenga complementos de pago vigentes
    // relacionados: primero se cancelan los complementos, luego la factura.
    $stmt = $pdo->prepare("
        SELECT GROUP_CONCAT(x.folio_interno ORDER BY x.id SEPARATOR ', ')
        FROM facturas_pagos fp JOIN facturas x ON x.id = fp.complemento_id
        WHERE fp.factura_id = ? AND x.estatus IN ('timbrada','timbrando')
    ");
    $stmt->execute([(int)$fac['id']]);
    if ($compsVivos = $stmt->fetchColumn()) {
        return ['ok'=>false,'error'=>'Esta factura tiene complementos de pago vigentes ('.$compsVivos.'). Cancélalos primero y después cancela la factura.'];
    }

    // Esquema A de anticipos: la nota de crédito de la aplicación va relacionada (07) a esta
    // factura; primero se cancela la nota. Y un CFDI de anticipo ya aplicado en una factura
    // vigente no se cancela: dejaría a esa factura relacionada a un anticipo inexistente.
    $stmt = $pdo->prepare("SELECT GROUP_CONCAT(DISTINCT x.folio_interno SEPARATOR ', ') FROM facturas_anticipos fa JOIN facturas x ON x.id = fa.nota_credito_id
        WHERE fa.factura_id = ? AND x.estatus IN ('timbrada','timbrando')");
    $stmt->execute([(int)$fac['id']]);
    if ($notas = $stmt->fetchColumn()) {
        return ['ok'=>false,'error'=>'Esta factura tiene la nota de crédito de anticipo '.$notas.' vigente. Cancélala primero y después cancela la factura.'];
    }
    if (!empty($fac['saldo_favor_id'])) {
        $stmt = $pdo->prepare("SELECT GROUP_CONCAT(DISTINCT f.folio_interno SEPARATOR ', ') FROM facturas_anticipos fa JOIN facturas f ON f.id = fa.factura_id
            WHERE fa.anticipo_id = ? AND f.estatus IN ('timbrada','timbrando')");
        $stmt->execute([(int)$fac['id']]);
        if ($usadas = $stmt->fetchColumn()) {
            return ['ok'=>false,'error'=>'Este anticipo ya se aplicó en la factura '.$usadas.'. Cancela primero esa factura (y su nota de crédito).'];
        }
    }

    // FacturAPI espera motive/substitution como query string, no en el body (confirmado contra la API real: con
    // POSTFIELDS respondía "motive is required" con location:"query" en el error).
    $url = 'https://www.facturapi.io/v2/invoices/' . $fac['facturapi_id'] . '?motive=' . urlencode($motivo);
    if ($motivo === '01') {
        $url .= '&substitution=' . urlencode($sustitucion);
    }
    $r = _facturapiLlamar('DELETE', $url);
    if ($r['err']) {
        _facturapiLogError('cancelar factura id='.(int)$fac['id'], $r);
        return ['ok'=>false,'error'=>'Error de conexión con FacturAPI: '.$r['err']];
    }
    $res = $r['res'];
    if ($r['code'] !== 200) {
        _facturapiLogError('cancelar factura id='.(int)$fac['id'], $r);
        return ['ok'=>false,'error'=>'FacturAPI: '._facturapiMensajeError($r)];
    }

    // FacturAPI puede dejar la cancelación en trámite: 'pending' cuando el SAT exige que el
    // receptor la acepte en su buzón (factura >$1,000 MXN o después de 72 h) o 'verifying'
    // mientras el SAT la valida. Solo es firme con status 'canceled'; si no, se queda
    // 'timbrada' con pac_cancel_status='pending' y se revisa con accion=verificar_cancelacion.
    $pacStatus = _facturapiEstadoCancelacion($res);
    if ($pacStatus === 'none' || $pacStatus === 'rejected') $pacStatus = 'pending'; // recién pedida: en trámite
    $esFirme   = ($pacStatus === 'canceled');

    // S2-c: queda registrado quien cancelo y cuando (antes solo se sabia quien habia
    // creado el borrador). cancelado_at se sella al PEDIR la cancelacion, aunque el SAT
    // la deje 'pending' en espera de que el receptor la acepte.
    $stmt = $pdo->prepare("
        UPDATE facturas SET
            estatus=?, motivo_cancel=?, sustituye_uuid=?, pac_cancel_status=?,
            cancelado_por=?, cancelado_at=NOW(), updated_at=NOW()
        WHERE id=?
    ");
    $stmt->execute([
        $esFirme ? 'cancelada' : 'timbrada',
        $motivo,
        $motivo === '01' ? $sustitucion : null,
        $pacStatus,
        $usuario,
        (int)$fac['id']
    ]);

    return ['ok'=>true, 'estatus'=>$pacStatus, 'firme'=>$esFirme];
}

// Emite en orden cronológico los complementos de los abonos que todavía no lo tienen,
// hasta el primero que no se pueda emitir solo (forma no automática, error del PAC,
// abono mayor al saldo). Lo usan el timbrado de una factura PPD (anticipos previos) y
// Cobranza al registrar un pago. Regresa una lista de resultados, uno por intento.
function _facturapiEmitirPendientesAuto($pdo, $facturaId, $usuario) {
    $out = [];
    for ($i = 0; $i < 60; $i++) {
        $stmt = $pdo->prepare("SELECT * FROM facturas WHERE id=?");
        $stmt->execute([(int)$facturaId]);
        $fac = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$fac || $fac['tipo_cfdi'] !== 'I' || $fac['metodo_pago'] !== 'PPD' || $fac['estatus'] !== 'timbrada'
            || $fac['modo'] !== FACTURAPI_MODE || $fac['pac_cancel_status'] === 'pending' || empty($fac['orden_folio'])) {
            break;
        }
        $estado = _facturapiEstadoPagos($pdo, $fac);
        $sig = null;
        foreach ($estado['pagos'] as $p) { if (!$p['complemento']) { $sig = $p; break; } }
        if (!$sig) break;
        $base = ['fecha_pago'=>$sig['fecha_pago'], 'monto_pago'=>(float)$sig['monto'], 'fecha_limite'=>$sig['fecha_limite'],
                 'factura_folio'=>$fac['folio_interno']];
        if ($estado['saldo'] <= 0.005) {
            $out[] = $base + ['ok'=>false, 'error'=>'La factura ya no tiene saldo por cubrir; este abono excede lo facturado. Revísalo en Facturación → Complementos pendientes.'];
            break;
        }
        $forma = _facturapiFormaAutomatica($sig);
        if (!$forma) {
            $motivo = ($sig['forma_pago'] === 'saldo_favor')
                ? 'se pagó con saldo a favor (la forma SAT depende del contador)'
                : 'es un pago con tarjeta sin indicar si fue crédito o débito';
            $out[] = $base + ['ok'=>false, 'manual'=>true, 'error'=>'El complemento de este abono se emite a mano: '.$motivo.'. Hazlo desde Facturación → Complementos pendientes.'];
            break;
        }
        $r = _facturapiEmitirComplemento($pdo, (int)$fac['id'], (int)$sig['id'], $forma, $usuario);
        $out[] = $base + $r;
        if (empty($r['ok'])) break;
    }
    return $out;
}

// Factura de ingreso vigente (timbrada o en verificación) de la orden de una cotización.
function _facturapiFacturaVigenteDeCotizacion($pdo, $cotId) {
    $stmt = $pdo->prepare("
        SELECT f.* FROM facturas f
        JOIN ordenes o ON o.folio = f.orden_folio
        JOIN cotizaciones c ON c.orden_id = o.id
        WHERE c.id = ? AND f.tipo_cfdi = 'I' AND f.estatus IN ('timbrada','timbrando')
        ORDER BY f.id DESC LIMIT 1
    ");
    $stmt->execute([(int)$cotId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Total, pagado y saldo de la orden (mismo criterio que _facturapiOrdenLiquidada).
function _facturapiSaldoOrden($pdo, $ordenFolio) {
    $stmt = $pdo->prepare("
        SELECT c.total, COALESCE((SELECT SUM(p.monto) FROM cotizacion_pagos p WHERE p.cotizacion_id = c.id), 0) AS pagado
        FROM ordenes o JOIN cotizaciones c ON c.orden_id = o.id
        WHERE o.folio = ? LIMIT 1
    ");
    $stmt->execute([$ordenFolio]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$r) return null;
    $total = round((float)$r['total'], 2); $pagado = round((float)$r['pagado'], 2);
    return ['total'=>$total, 'pagado'=>$pagado, 'saldo'=>round(max(0, $total - $pagado), 2)];
}

// Valida RFC + nombre + CP + régimen contra el padrón del SAT usando SIEMPRE el
// sandbox de FacturAPI (llave de pruebas), aunque el sistema esté en live: emite una
// factura de prueba de $1 y la cancela enseguida (mismo método que
// scripts/validar_datos_fiscales.php). Regresa ['ok'=>true] o ['ok'=>false,'error'].
function _facturapiValidarReceptorSat($rfc, $nombre, $cp, $regimen) {
    if (!defined('FACTURAPI_KEY_TEST') || FACTURAPI_KEY_TEST === '') {
        return ['ok'=>false, 'error'=>'No hay llave de pruebas de FacturAPI para validar contra el SAT.'];
    }
    $llamar = function($url, $method, $body = null) {
        $ch = curl_init($url);
        $o = [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>30,
              CURLOPT_HTTPHEADER=>['Authorization: Bearer '.FACTURAPI_KEY_TEST, 'Content-Type: application/json']];
        if ($method === 'POST')   { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = json_encode($body); }
        if ($method === 'DELETE') { $o[CURLOPT_CUSTOMREQUEST] = 'DELETE'; }
        curl_setopt_array($ch, $o);
        $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); $e = curl_error($ch);
        unset($ch);
        return [$c, json_decode((string)$r, true), $e];
    };
    list($code, $res, $err) = $llamar('https://www.facturapi.io/v2/invoices', 'POST', [
        'type'=>'I', 'use'=>'G03', 'payment_form'=>'01', 'payment_method'=>'PUE',
        'series'=>'VAL', 'folio_number'=>(int)(90000 + (int)date('His')), 'currency'=>'MXN',
        'customer'=>['legal_name'=>$nombre, 'tax_id'=>$rfc, 'tax_system'=>$regimen, 'address'=>['zip'=>$cp]],
        'items'=>[['quantity'=>1, 'product'=>['description'=>'Validacion de datos fiscales', 'product_key'=>'30171706',
            'unit_key'=>'MTK', 'price'=>1, 'tax_included'=>false, 'taxes'=>[['type'=>'IVA','rate'=>0.16,'factor'=>'Tasa']]]]],
    ]);
    if ($err) return ['ok'=>false, 'error'=>'No se pudo consultar el padrón del SAT: '.$err];
    if ($code === 200) {
        if (!empty($res['id'])) $llamar('https://www.facturapi.io/v2/invoices/'.$res['id'].'?motive=02', 'DELETE');
        return ['ok'=>true];
    }
    $msg = $res['message'] ?? 'Error desconocido';
    if (!empty($res['errors'][0]['path'])) $msg = $res['errors'][0]['path'].': '.($res['errors'][0]['message'] ?? $msg);
    return ['ok'=>false, 'error'=>'El SAT rechazó los datos: '.trim(preg_replace('/\s+/', ' ', $msg))];
}
