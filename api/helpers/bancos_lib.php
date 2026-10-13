<?php
// ============================================================
//  APEX GLASS - Bancos (UPD-697): cadena de saldos y plan de importación
//  Archivo: api/helpers/bancos_lib.php   (lo usa api/bancos.php)
// ============================================================
// Sin sesión ni salida: se puede probar desde consola.
require_once __DIR__ . '/bbva_pdf_lib.php';

// Revisa toda la cadena de saldos registrada: saldo_inicial ± cada movimiento = su saldo.
function bancosVerificarCadena($db, $cuenta) {
    $s = $db->prepare("SELECT id, secuencia, fecha, cargo, abono, saldo FROM banco_movimientos WHERE cuenta_id = ? ORDER BY secuencia");
    $s->execute([$cuenta['id']]);
    $saldo = (float)$cuenta['saldo_inicial'];
    $n = 0;
    while ($r = $s->fetch(PDO::FETCH_ASSOC)) {
        $n++;
        $saldo = round($saldo + (float)$r['abono'] - (float)$r['cargo'], 2);
        if (abs($saldo - (float)$r['saldo']) >= 0.005) {
            return ['ok' => false, 'movimientos' => $n, 'error_en' => $r['id'], 'fecha' => $r['fecha'],
                    'esperado' => $saldo, 'registrado' => (float)$r['saldo']];
        }
    }
    return ['ok' => true, 'movimientos' => $n, 'saldo' => $saldo];
}

// Compara el PDF contra lo registrado y decide qué es nuevo. No escribe nada.
// Devuelve el plan o lanza Exception con la razón (en español, para el usuario).
function bancosPlanImportacion($db, $cuenta, $parsed) {
    if ($parsed['cuenta'] && $parsed['cuenta'] !== $cuenta['numero']) {
        throw new Exception('El PDF es de la cuenta ' . $parsed['cuenta'] . ', no de la ' . $cuenta['numero']);
    }
    $F = $parsed['filas'];

    // 1. Ignorar lo anterior al arranque de la cuenta.
    $s = 0;
    while ($s < count($F) && $F[$s]['fecha'] < $cuenta['fecha_inicial']) $s++;
    $previas = $s;
    if ($s >= count($F)) {
        return ['nuevas' => [], 'existentes' => 0, 'previas' => $previas, 'saldo_final' => null];
    }

    // 2. Lo registrado desde la fecha de la primera fila útil del PDF.
    $st = $db->prepare("SELECT secuencia, fecha, cargo, abono, saldo FROM banco_movimientos
                        WHERE cuenta_id = ? AND fecha >= ? ORDER BY secuencia");
    $st->execute([$cuenta['id'], $F[$s]['fecha']]);
    $E = $st->fetchAll(PDO::FETCH_ASSOC);

    $igual = function ($f, $e) {
        return $f['fecha'] === $e['fecha']
            && abs($f['monto'] - ((float)$e['cargo'] + (float)$e['abono'])) < 0.005
            && abs($f['saldo'] - (float)$e['saldo']) < 0.005;
    };

    if (!$E) {
        // Nada registrado en ese rango: lo del PDF debe continuar justo después del último saldo.
        $st = $db->prepare("SELECT saldo, fecha FROM banco_movimientos WHERE cuenta_id = ? ORDER BY secuencia DESC LIMIT 1");
        $st->execute([$cuenta['id']]);
        $ult = $st->fetch(PDO::FETCH_ASSOC);
        if ($ult && $ult['fecha'] > $F[$s]['fecha']) {
            throw new Exception('El PDF empieza el ' . $F[$s]['fecha'] . ' pero ya hay movimientos registrados hasta el ' . $ult['fecha'] . ' que no aparecen en él.');
        }
        $saldoPrevio = $ult ? (float)$ult['saldo'] : (float)$cuenta['saldo_inicial'];
        $inicioNuevas = $s;
    } else {
        // Ubicar la primera fila útil del PDF dentro de lo registrado y exigir que todo lo
        // que se traslapa coincida renglón por renglón.
        $q = null;
        for ($j = 0; $j < count($E); $j++) {
            if ($igual($F[$s], $E[$j])) { $q = $j; break; }
        }
        if ($q === null) {
            throw new Exception('El primer movimiento del PDF (' . $F[$s]['fecha'] . ', $' . number_format($F[$s]['monto'], 2) . ') no coincide con lo ya registrado. Revisa que sea el PDF correcto.');
        }
        $k = $s;
        for ($j = $q; $j < count($E) && $k < count($F); $j++, $k++) {
            if (!$igual($F[$k], $E[$j])) {
                throw new Exception('El movimiento del ' . $F[$k]['fecha'] . ' por $' . number_format($F[$k]['monto'], 2) . ' del PDF no coincide con lo registrado (renglón ' . ($E[$j]['secuencia']) . '). No se registró nada.');
            }
        }
        $inicioNuevas = $k;
        $saldoPrevio = (float)$E[$k - $s + $q - 1]['saldo'];
        // El PDF es más viejo que lo registrado: todo ya existe.
        if ($inicioNuevas >= count($F)) {
            return ['nuevas' => [], 'existentes' => count($F) - $s, 'previas' => $previas, 'saldo_final' => null];
        }
    }

    // 3. Lo nuevo debe encadenar al centavo con el saldo previo.
    $nuevas = [];
    $saldo = $saldoPrevio;
    for ($k = $inicioNuevas; $k < count($F); $k++) {
        $f = $F[$k];
        if (!bbvaClasificar($f, $saldo)) {
            // Saldo con el que el PDF espera arrancar este movimiento (se conoce si no es la
            // primera fila del PDF; la primera no trae con qué compararse).
            $msg = 'Faltan movimientos entre lo registrado y este PDF: el último saldo registrado es $' . number_format($saldo, 2);
            if ($k > 0) {
                $esperado = (float)$F[$k - 1]['saldo'];
                $msg .= ' y el PDF continúa desde $' . number_format($esperado, 2)
                      . ' (diferencia $' . number_format($esperado - $saldo, 2) . ')';
            }
            $msg .= '. Probablemente el PDF anterior se imprimió antes de que terminara el día; descarga uno que incluya esos días completos. No se registró nada.';
            throw new Exception($msg);
        }
        $saldo = $f['saldo'];
        $nuevas[] = $f;
    }
    return ['nuevas' => $nuevas, 'existentes' => $inicioNuevas - $s, 'previas' => $previas, 'saldo_final' => $saldo];
}

// Registra la importación y sus movimientos nuevos. Debe llamarse dentro de una
// transacción con la cuenta bloqueada (FOR UPDATE). Devuelve la verificación de la
// cadena; lanza Exception si no cuadra (quien llama hace rollBack).
function bancosRegistrar($db, $cuenta, $parsed, $plan, $archivoNombre, $archivoGuardado, $usuario) {
    $F = $parsed['filas'];
    $st = $db->prepare("INSERT INTO banco_importaciones
        (cuenta_id, archivo_nombre, archivo_guardado, fecha_impresion, saldo_disponible, filas_archivo, filas_nuevas,
         filas_existentes, filas_previas, primera_fecha, ultima_fecha, saldo_final, usuario)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $st->execute([
        $cuenta['id'], $archivoNombre, $archivoGuardado, $parsed['impreso'], $parsed['saldo_disponible'],
        count($F), count($plan['nuevas']), $plan['existentes'], $plan['previas'],
        $F[0]['fecha'], $F[count($F) - 1]['fecha'], $plan['saldo_final'], $usuario,
    ]);
    $impId = (int)$db->lastInsertId();

    if ($plan['nuevas']) {
        $st  = $db->prepare("SELECT COALESCE(MAX(secuencia), 0) FROM banco_movimientos WHERE cuenta_id = ?");
        $st->execute([$cuenta['id']]);
        $sec = (int)$st->fetchColumn();
        $ins = $db->prepare("INSERT INTO banco_movimientos
            (cuenta_id, secuencia, fecha, concepto, detalle, tipo, cargo, abono, saldo, importacion_id)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        foreach ($plan['nuevas'] as $f) {
            $ins->execute([$cuenta['id'], ++$sec, $f['fecha'], $f['concepto'], $f['detalle'] ?: null,
                           $f['tipo'], $f['cargo'], $f['abono'], $f['saldo'], $impId]);
        }
    }

    $cadena = bancosVerificarCadena($db, $cuenta);
    if (!$cadena['ok']) throw new Exception('La cadena de saldos no cuadra después de registrar; no se guardó nada.');
    return $cadena;
}
