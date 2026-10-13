<?php
// ============================================================
//  APEX GLASS - Lector del PDF "Detalle de movimientos" de BBVA (Bancos, UPD-697)
//  Archivo: api/helpers/bbva_pdf_lib.php
// ============================================================
// El PDF es la pantalla "Detalle de movimientos" de BBVA impresa desde el navegador
// ("Mes actual" / "Mes anterior"). Viene del más NUEVO al más VIEJO y cada renglón trae
// su saldo. pdftotext -layout lo deja así:
//
//              SPEI RECIBIDOBANREGIO/0127415557 058        <- concepto (línea antes)
//   12-10                              $ 4,680.59   $ 652,503.95   <- fecha, monto, saldo
//                   0261012obrScyf                         <- detalle (línea después)
//   12-10   DEPOSITO EN EFECTIVO/0000000000T-053   $ 5,725.88   $ 631,183.27  <- una sola línea
//
// No se usa la columna para saber si es cargo o abono (se mueve entre páginas): se
// deduce del saldo. Si saldo = saldo_anterior + monto es abono; si es - monto, cargo.

// Convierte el PDF a texto. Devuelve el texto o lanza Exception.
function bbvaPdfATexto($rutaPdf) {
    $out = $rutaPdf . '.txt';
    exec('timeout 30 pdftotext -layout ' . escapeshellarg($rutaPdf) . ' ' . escapeshellarg($out) . ' 2>/dev/null', $_, $ret);
    if ($ret !== 0 || !is_file($out)) {
        throw new Exception('No se pudo leer el PDF');
    }
    $txt = file_get_contents($out);
    @unlink($out);
    return $txt;
}

function bbvaNumero($s) {
    return round((float)str_replace(',', '', $s), 2);
}

// Tipo de movimiento a partir del concepto (sirve para filtrar y, en la Fase 2, conciliar).
function bbvaTipoMovimiento($concepto) {
    $c = strtoupper($concepto);
    if (strpos($c, 'SPEI RECIBIDO') === 0 || strpos($c, 'TEF RECIBIDO') === 0) return 'spei_recibido';
    if (strpos($c, 'DEPOSITO EFECTIVO') === 0)      return 'deposito_efectivo'; // "DEPOSITO EFECTIVO PRACTIC" (cajero)
    if (strpos($c, 'DEP.CHEQUES') === 0)            return 'deposito_cheque';
    if (strpos($c, 'SPEI DEVUELTO') === 0)          return 'spei_devuelto';
    if (strpos($c, 'SPEI ENVIADO') === 0)           return 'spei_enviado';
    if (strpos($c, 'PAGO CUENTA DE TERCERO') === 0) return 'pago_tercero';
    if (strpos($c, 'DEPOSITO EN EFECTIVO') === 0)   return 'deposito_efectivo';
    if (strpos($c, 'VENTAS DEBITO') === 0 || strpos($c, 'VENTAS CREDITO') === 0) return 'terminal_venta';
    if (strpos($c, 'APLI TASA') === 0 || strpos($c, 'IVA TASA') === 0)           return 'terminal_comision';
    if (strpos($c, 'SERV BANCA') === 0 || strpos($c, 'IVA COM') === 0 || strpos($c, 'COMISION') !== false) return 'comision_banco';
    if (strpos($c, '/GUIA:') !== false)              return 'pago_servicio';
    return 'otro';
}

// Lee el texto del PDF. Devuelve:
//   cuenta, impreso (Y-m-d H:i:s), saldo_disponible,
//   filas: del más VIEJO al más NUEVO, cada una con fecha (Y-m-d), concepto, detalle,
//          tipo, monto, saldo, cargo/abono (null en la primera fila: depende del saldo
//          anterior, que lo pone quien importa).
// Lanza Exception si el formato no es el esperado o si el saldo no cuadra dentro del PDF.
function bbvaParsearTexto($texto) {
    $texto  = str_replace(["\f", "\r"], ['', ''], $texto);
    $lineas = explode("\n", $texto);

    $cuenta = null; $impreso = null; $disponible = null;
    $reFecha = '/^\s*(\d{2})-(\d{2})\s+(.*)$/';
    $reMonto = '/\$\s*([\d,]+\.\d{2})/';

    $filasNuevoAViejo = [];
    $pend = [];
    $empezo = false;
    $n = count($lineas);

    for ($i = 0; $i < $n; $i++) {
        $l = $lineas[$i];
        $t = trim($l);

        if (!$empezo) {
            if ($impreso === null && preg_match('#^(\d{2})/(\d{2})/(\d{4})\s*-\s*(\d{1,2}):(\d{2}):(\d{2})\s*(AM|PM)#i', $t, $m)) {
                $h = (int)$m[4] % 12;
                if (strtoupper($m[7]) === 'PM') $h += 12;
                $impreso = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $m[3], $m[2], $m[1], $h, $m[5], $m[6]);
            }
            if (preg_match('/N[uú]mero de cuenta:\s*(\d+)/u', $t, $m))    $cuenta = $m[1];
            if (preg_match('/Saldo disponible:\s*([\d,]+\.\d{2})/', $t, $m)) $disponible = bbvaNumero($m[1]);
            if (strpos($t, 'Fecha') === 0 && strpos($t, 'Saldo') !== false && strpos($t, 'Cargo') !== false) {
                $empezo = true;
            }
            continue;
        }
        if (strpos($t, 'En cumplimiento') === 0) break;

        if (preg_match($reFecha, $l, $m) && preg_match_all($reMonto, $l, $mm)) {
            $montos = $mm[1];
            if (count($montos) !== 2) {
                throw new Exception('Renglón con formato inesperado (' . count($montos) . ' importes): ' . $t);
            }
            $inline  = trim(preg_replace($reMonto, '', $m[3]));
            $partes  = $pend;
            $pend    = [];
            if ($inline !== '') array_unshift($partes, $inline);
            // La segunda línea (detalle) va DESPUÉS del renglón con importes, solo cuando el
            // concepto venía arriba (los de una sola línea no tienen detalle).
            if ($inline === '' && $i + 1 < $n) {
                $sig = trim(str_replace("\f", '', $lineas[$i + 1]));
                if ($sig !== '' && !preg_match($reFecha, $lineas[$i + 1]) && strpos($sig, 'En cumplimiento') !== 0) {
                    $partes[] = $sig;
                    $i++;
                }
            }
            $concepto = $partes[0] ?? '';
            $detalle  = count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : '';
            $filasNuevoAViejo[] = [
                'dia' => (int)$m[1], 'mes' => (int)$m[2],
                'concepto' => mb_substr($concepto, 0, 200),
                'detalle'  => mb_substr($detalle, 0, 200),
                'monto'    => bbvaNumero($montos[0]),
                'saldo'    => bbvaNumero($montos[1]),
            ];
        } elseif ($t !== '') {
            $pend[] = $t;
        }
    }

    if (!$empezo || !$filasNuevoAViejo) {
        throw new Exception('El PDF no parece ser un "Detalle de movimientos" de BBVA');
    }
    if (!$impreso) {
        throw new Exception('No se encontró la fecha de impresión en el PDF');
    }

    // Año: el PDF solo trae día-mes. Se toma el de la fecha de impresión; un mes mayor al
    // de impresión es del año anterior (ej. diciembre impreso en enero).
    $anioImp = (int)substr($impreso, 0, 4);
    $mesImp  = (int)substr($impreso, 5, 2);

    $filas = array_reverse($filasNuevoAViejo);
    foreach ($filas as $k => &$f) {
        $anio = ($f['mes'] > $mesImp) ? $anioImp - 1 : $anioImp;
        if (!checkdate($f['mes'], $f['dia'], $anio)) {
            throw new Exception('Fecha inválida en el renglón ' . ($k + 1));
        }
        $f['fecha'] = sprintf('%04d-%02d-%02d', $anio, $f['mes'], $f['dia']);
        $f['tipo']  = bbvaTipoMovimiento($f['concepto']);
        $f['cargo'] = null;
        $f['abono'] = null;
        unset($f['dia'], $f['mes']);
    }
    unset($f);

    // Cadena de saldos dentro del PDF (de la 2a fila en adelante).
    for ($k = 1; $k < count($filas); $k++) {
        if (!bbvaClasificar($filas[$k], $filas[$k - 1]['saldo'])) {
            throw new Exception('El saldo no cuadra dentro del PDF en el movimiento del ' . $filas[$k]['fecha'] . ' por $' . number_format($filas[$k]['monto'], 2) . ' (' . $filas[$k]['concepto'] . ')');
        }
    }

    return [
        'cuenta'           => $cuenta,
        'impreso'          => $impreso,
        'saldo_disponible' => $disponible,
        'filas'            => $filas,
    ];
}

// Pone cargo/abono a la fila según el saldo anterior. Devuelve false si no cuadra.
function bbvaClasificar(&$fila, $saldoAnterior) {
    $m = $fila['monto'];
    if (abs(round($saldoAnterior + $m, 2) - $fila['saldo']) < 0.005) {
        $fila['abono'] = $m; $fila['cargo'] = 0.0; return true;
    }
    if (abs(round($saldoAnterior - $m, 2) - $fila['saldo']) < 0.005) {
        $fila['cargo'] = $m; $fila['abono'] = 0.0; return true;
    }
    return false;
}
