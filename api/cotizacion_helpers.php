<?php
// ============================================================
//  APEX GLASS - Helpers compartidos: folios y fecha de entrega
//  Archivo: api/cotizacion_helpers.php
//  Usado por api/cotizaciones.php y api/maquila.php
// ============================================================

// ─── Función: generar siguiente folio de COTIZACIÓN ─────────────────────────
function generarFolio($db) {
    $db->exec("LOCK TABLES folios_control WRITE");
    $row    = $db->query("SELECT * FROM folios_control LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $modo   = $row['modo_cot'] ?? 'prueba';
    $num    = ($row['num_cot'] ?? 0) + 1;
    $db->prepare("UPDATE folios_control SET num_cot = ? WHERE id = ?")
       ->execute([$num, $row['id']]);
    $db->exec("UNLOCK TABLES");

    if ($modo === 'produccion') {
        return 'COT-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    } else {
        return 'xCOT-' . str_pad($num, 3, '0', STR_PAD_LEFT);
    }
}

// ─── Función: generar siguiente folio de ORDEN DE PRODUCCIÓN ────────────────
function generarFolioOrden($db) {
    $db->exec("LOCK TABLES folios_control WRITE");
    $row    = $db->query("SELECT * FROM folios_control LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $letra  = $row['letra_actual'];
    $numero = $row['numero_actual'] + 1;
    if ($numero > 999) {
        $numero = 1;
        $letra  = chr(ord($letra) + 1);
    }
    $db->prepare("UPDATE folios_control SET letra_actual = ?, numero_actual = ? WHERE id = ?")
       ->execute([$letra, $numero, $row['id']]);
    $db->exec("UNLOCK TABLES");
    return $letra . '-' . str_pad($numero, 3, '0', STR_PAD_LEFT);
}

// ─── Función: calcular fecha entrega (días hábiles) ──────────────────────────
function calcularFechaEntrega($db, $fecha_inicio, $localidad, $ciudad) {
    // Obtener festivos
    $stmt = $db->query("SELECT fecha FROM festivos");
    $festivos = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'fecha');

    $fecha  = new DateTime($fecha_inicio);
    $dias   = 0;
    $target = 5;

    while ($dias < $target) {
        $fecha->modify('+1 day');
        $dow = (int)$fecha->format('N'); // 1=lunes, 7=domingo
        $f   = $fecha->format('Y-m-d');
        if ($dow >= 6) continue;          // fin de semana
        if (in_array($f, $festivos)) continue; // festivo
        $dias++;
    }

    // Saltillo: ajustar al viernes de esa semana
    if ($localidad === 'foraneo' && stripos($ciudad ?? '', 'saltillo') !== false) {
        $dow = (int)$fecha->format('N');
        if ($dow < 5) {
            $dias_a_viernes = 5 - $dow;
            $fecha->modify("+$dias_a_viernes days");
        }
        // Si viernes es festivo, mover al siguiente día hábil
        while (in_array($fecha->format('Y-m-d'), $festivos) || (int)$fecha->format('N') >= 6) {
            $fecha->modify('+1 day');
        }
    }

    return $fecha->format('Y-m-d');
}
