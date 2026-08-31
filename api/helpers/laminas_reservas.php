<?php
// ============================================================
//  APEX GLASS - Helpers: Venta anticipada de lámina completa
//  Archivo: api/helpers/laminas_reservas.php
//  UPD-554: permite vender una lámina completa (sin cortar) aunque
//  no haya stock físico todavía, comprometiéndola contra una compra
//  futura (OC o "Registrar compra" manual) — sin ligarla a una OC
//  específica: en cuanto llega lámina de ese mismo catálogo (id),
//  se liquida sola por orden de antigüedad (FIFO).
//
//  Todas las funciones asumen que el caller YA abrió una transacción
//  ($db->beginTransaction()) y hace commit/rollback por su cuenta.
// ============================================================

// Stock físico disponible de una lámina (compras recibidas - usado), bloqueando
// el renglón de catálogo para que nadie más lo consuma mientras se liquida.
function laminasStockFisico(PDO $db, $lamina_id) {
    $db->prepare("SELECT id FROM laminas WHERE id = ? FOR UPDATE")->execute([$lamina_id]);
    $s = $db->prepare("
        SELECT
            COALESCE((SELECT SUM(cantidad_laminas) FROM inventario_compras WHERE lamina_id = ?), 0) -
            COALESCE((SELECT SUM(cantidad_laminas) FROM inventario_movimientos WHERE lamina_id = ?), 0)
    ");
    $s->execute([$lamina_id, $lamina_id]);
    return (int)$s->fetchColumn();
}

// Liquida (FIFO) las reservas activas de una lámina contra el stock físico
// disponible en este momento. Se llama tanto al crear una reserva (por si ya
// hay stock) como cada vez que entra una compra nueva de esa lámina.
function laminasLiquidarReservas(PDO $db, $lamina_id, $operador_id) {
    $disponible = laminasStockFisico($db, $lamina_id);
    if ($disponible <= 0) return;

    $s = $db->prepare("SELECT * FROM laminas_reservas
        WHERE lamina_id = ? AND estado = 'activa'
        ORDER BY created_at ASC, id ASC FOR UPDATE");
    $s->execute([$lamina_id]);
    $reservas = $s->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reservas as $r) {
        if ($disponible <= 0) break;
        $pendiente = (int)$r['cantidad'] - (int)$r['cantidad_cumplida'];
        if ($pendiente <= 0) continue;
        $tomar = min($disponible, $pendiente);

        $db->prepare("INSERT INTO inventario_movimientos
            (lamina_id, cantidad_laminas, ordenes, operador_id, fecha, notas)
            VALUES (?,?,?,?,CURDATE(),?)")
           ->execute([
               $lamina_id, $tomar,
               json_encode([$r['folio']], JSON_UNESCAPED_UNICODE),
               $operador_id,
               'Venta lámina completa (reserva) — Orden ' . $r['folio'],
           ]);

        $nuevaCumplida = (int)$r['cantidad_cumplida'] + $tomar;
        $nuevoEstado   = ($nuevaCumplida >= (int)$r['cantidad']) ? 'cumplida' : 'activa';
        $db->prepare("UPDATE laminas_reservas SET cantidad_cumplida = ?, estado = ? WHERE id = ?")
           ->execute([$nuevaCumplida, $nuevoEstado, $r['id']]);

        $disponible -= $tomar;
    }
}

// Crea la reserva de venta de una partida "lámina completa" al dar VoBo, e
// intenta liquidarla de inmediato contra el stock que ya exista.
function laminasCrearReservaVenta(PDO $db, $lamina_id, $cantidad, $cotizacion_partida_id, $folio, $operador_id) {
    $db->prepare("INSERT INTO laminas_reservas
        (lamina_id, cantidad, cotizacion_partida_id, folio, notas)
        VALUES (?,?,?,?,?)")
       ->execute([
           $lamina_id, $cantidad, $cotizacion_partida_id, $folio,
           'Venta lámina completa — Orden ' . $folio,
       ]);
    laminasLiquidarReservas($db, $lamina_id, $operador_id);
}

// Revierte la reserva de una partida al cancelar la orden: regresa a stock lo
// que ya se hubiera liquidado (movimiento compensatorio, sin borrar historial)
// y cancela la reserva para que no se siga intentando cumplir.
function laminasRevertirReservaPorPartida(PDO $db, $cotizacion_partida_id, $folio, $operador_id) {
    $s = $db->prepare("SELECT * FROM laminas_reservas
        WHERE cotizacion_partida_id = ? AND estado IN ('activa','cumplida') FOR UPDATE");
    $s->execute([$cotizacion_partida_id]);
    $reservas = $s->fetchAll(PDO::FETCH_ASSOC);

    foreach ($reservas as $r) {
        $cumplida = (int)$r['cantidad_cumplida'];
        if ($cumplida > 0) {
            $db->prepare("INSERT INTO inventario_movimientos
                (lamina_id, cantidad_laminas, ordenes, operador_id, fecha, notas)
                VALUES (?,?,?,?,CURDATE(),?)")
               ->execute([
                   $r['lamina_id'], -$cumplida,
                   json_encode([$folio], JSON_UNESCAPED_UNICODE),
                   $operador_id,
                   'Reversión venta lámina completa — Orden ' . $folio . ' cancelada',
               ]);
        }
        $db->prepare("UPDATE laminas_reservas SET estado = 'cancelada' WHERE id = ?")->execute([$r['id']]);
    }
}
