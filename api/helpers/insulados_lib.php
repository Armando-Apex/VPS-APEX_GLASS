<?php
// ============================================================
//  APEX GLASS - Helpers: Cotizador de Insulados (UPD-613)
//  Archivo: api/helpers/insulados_lib.php
//
//  Un insulado se captura como UNA unidad (N unidades, ancho × alto, vidrio
//  exterior, vidrio interior, separador) pero se guarda como 2 partidas
//  normales de cotizaciones_partidas — insulado_rol 'ext' e 'int', mismo
//  insulado_grupo — para que producción, QR, inventario, costos, P&L y CFDI
//  sigan viendo partidas normales sin cambios.
//
//  El separador vive como UNA línea de cotizacion_partida_servicios con
//  es_insulado=1, colgada de la partida 'ext'. La genera SIEMPRE el servidor
//  (nunca se confía en lo que manda el navegador): cantidad_piezas = unidades,
//  unidades_por_pieza = perímetro (ml) / área (m2) / 1 (pieza).
//
//  Esto elimina el error de la "mitad de piezas" (UPD-583, COT-1790, COT-1821):
//  las unidades SIEMPRE son la cantidad capturada, sin importar si los dos
//  vidrios son del mismo tipo o distintos.
// ============================================================

// Cantidad de "unidades de cobro" del separador por cada unidad insulada.
function insuladoUnidadesPorPieza($unidad, $ancho, $alto) {
    if ($unidad === 'ml') return round(2 * ((int)$ancho + (int)$alto) / 1000, 3);
    if ($unidad === 'm2') return round(((int)$ancho / 1000) * ((int)$alto / 1000), 3);
    return 1.0;
}

// Valida y normaliza los grupos de insulado dentro de $partidas_data (ya construido
// por crear/actualizar, en orden de num_partida). Cada partida puede traer
// insulado_grupo / insulado_rol / insulado_servicio_id desde el payload.
//
// Reglas: cada grupo tiene exactamente 1 'ext' y 1 'int', con las mismas medidas y
// cantidad; la 'ext' trae un separador válido del catálogo (activo, unidad ml/m2/pieza).
// Los grupos se renumeran 1..n en orden de aparición.
//
// Devuelve ['error' => string|null, 'partidas' => array, 'grupos' => [nuevo_grupo => [...]]]
//   grupos[g] = ['grupo_original' => int, 'ext_idx' => int, 'int_idx' => int,
//                'servicio' => fila de servicios_catalogo]
function insuladoNormalizar(PDO $db, array $partidas_data) {
    $porGrupo = [];
    foreach ($partidas_data as $idx => $p) {
        $g   = (int)($p['insulado_grupo'] ?? 0);
        $rol = $p['insulado_rol'] ?? null;
        if ($g <= 0 || !in_array($rol, ['ext', 'int'], true)) {
            $partidas_data[$idx]['insulado_grupo'] = null;
            $partidas_data[$idx]['insulado_rol']   = null;
            continue;
        }
        if (isset($porGrupo[$g][$rol])) {
            return ['error' => 'El insulado #' . $g . ' tiene dos vidrios ' . ($rol === 'ext' ? 'exteriores' : 'interiores') . '.', 'partidas' => $partidas_data, 'grupos' => []];
        }
        $porGrupo[$g][$rol] = $idx;
    }

    $stSrv = $db->prepare("SELECT id, nombre, precio_default, unidad FROM servicios_catalogo WHERE id = ? AND activo = 1");
    $grupos = [];
    $n = 0;
    // Orden de aparición = orden de la primera partida del grupo
    uasort($porGrupo, function ($a, $b) { return min($a) <=> min($b); });
    foreach ($porGrupo as $gOrig => $roles) {
        if (!isset($roles['ext']) || !isset($roles['int'])) {
            return ['error' => 'Al insulado #' . $gOrig . ' le falta el vidrio ' . (isset($roles['ext']) ? 'interior' : 'exterior') . ' (o sus medidas quedaron vacías).', 'partidas' => $partidas_data, 'grupos' => []];
        }
        $e = $partidas_data[$roles['ext']];
        $i = $partidas_data[$roles['int']];
        if ((int)$e['ancho'] !== (int)$i['ancho'] || (int)$e['alto'] !== (int)$i['alto'] || (int)$e['cantidad'] !== (int)$i['cantidad']) {
            return ['error' => 'En el insulado #' . $gOrig . ' los dos vidrios deben tener las mismas medidas y cantidad.', 'partidas' => $partidas_data, 'grupos' => []];
        }
        if (!empty($e['lamina_id']) || !empty($i['lamina_id'])) {
            return ['error' => 'Un insulado no puede ser venta de lámina completa.', 'partidas' => $partidas_data, 'grupos' => []];
        }
        $srvId = (int)($e['insulado_servicio_id'] ?? 0);
        $stSrv->execute([$srvId]);
        $srv = $stSrv->fetch(PDO::FETCH_ASSOC);
        if (!$srv) {
            return ['error' => 'Selecciona el separador del insulado #' . $gOrig . '.', 'partidas' => $partidas_data, 'grupos' => []];
        }
        $n++;
        $partidas_data[$roles['ext']]['insulado_grupo'] = $n;
        $partidas_data[$roles['int']]['insulado_grupo'] = $n;
        $grupos[$n] = ['grupo_original' => (int)$gOrig, 'ext_idx' => $roles['ext'], 'int_idx' => $roles['int'], 'servicio' => $srv];
    }
    return ['error' => null, 'partidas' => $partidas_data, 'grupos' => $grupos];
}

// Inserta la línea de separador (es_insulado=1) de cada grupo. $idsPorIdx mapea el
// índice de $partidas_data → id real de cotizaciones_partidas. $preciosPrevios mapea
// grupo_original → ['servicio_id' => , 'precio_unitario' => ] para conservar el precio
// ya cotizado si el separador no cambió (mismo criterio C-3 que el precio del vidrio:
// un cambio de catálogo no reprecia en silencio una cotización ya hecha).
function insuladoInsertarSeparadores(PDO $db, $cot_id, array $partidas_data, array $grupos, array $idsPorIdx, array $preciosPrevios = []) {
    $st = $db->prepare("INSERT INTO cotizacion_partida_servicios
        (cotizacion_id, partida_id, servicio_id, descripcion, precio_unitario, unidades_por_pieza, cantidad_piezas, subtotal, es_insulado)
        VALUES (?,?,?,?,?,?,?,?,1)");
    foreach ($grupos as $g) {
        $e      = $partidas_data[$g['ext_idx']];
        $srv    = $g['servicio'];
        $prev   = $preciosPrevios[$g['grupo_original']] ?? null;
        $precio = ($prev && (int)$prev['servicio_id'] === (int)$srv['id'])
            ? (float)$prev['precio_unitario']
            : (float)$srv['precio_default'];
        $upp      = insuladoUnidadesPorPieza($srv['unidad'], $e['ancho'], $e['alto']);
        $unidades = (int)$e['cantidad'];
        $st->execute([
            $cot_id, $idsPorIdx[$g['ext_idx']], $srv['id'], $srv['nombre'],
            $precio, $upp, $unidades, round($precio * $upp * $unidades, 2),
        ]);
    }
}

// Recalcula servicios_subtotal + totales canónicos + saldo pendiente de la cotización
// después de tocar servicios (mismo criterio que agregar_servicio / A-2).
function insuladoRecalcularTotales(PDO $db, $cot_id, $condicion_pago) {
    $st = $db->prepare("SELECT COALESCE(SUM(subtotal),0) FROM cotizacion_partida_servicios WHERE cotizacion_id = ?");
    $st->execute([$cot_id]);
    $db->prepare("UPDATE cotizaciones SET servicios_subtotal = ? WHERE id = ?")->execute([(float)$st->fetchColumn(), $cot_id]);

    $tots = apexTotalesCotizacion($db, $cot_id);
    $stP  = $db->prepare("SELECT COALESCE(saldo_pagado,0) FROM cotizaciones WHERE id = ?");
    $stP->execute([$cot_id]);
    $pagado = (float)$stP->fetchColumn();
    $base   = ($condicion_pago === 'anticipo') ? round($tots['total'] * 0.5, 2) : $tots['total'];
    $saldo  = max(0, round($base - $pagado, 2));
    $db->prepare("UPDATE cotizaciones SET subtotal = ?, iva = ?, total = ?, saldo_pendiente = ? WHERE id = ?")
       ->execute([$tots['subtotal'], $tots['iva'], $tots['total'], $saldo, $cot_id]);
    return $tots;
}
