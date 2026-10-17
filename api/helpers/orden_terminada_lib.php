<?php
// UPD-720: aviso en la campana cuando TODA la orden llega a "terminado".
// Lo usan el escaneo pieza por pieza (api/actualizar_estatus.php) y la Corrección de
// Estatus de Admin Órdenes (api/admin_ordenes.php), para que ambos avisen igual:
//   · administracion → "Orden terminada — verificar pago"
//   · asesora de la orden (rol comercial) → "Orden lista — programar entrega"
// $evitarDuplicado: no repetir el aviso si la orden ya tiene uno (Corrección de Estatus
// se puede correr varias veces sobre la misma orden). Nunca lanza excepción.

if (!function_exists('notificarOrdenTerminada')) {
    function notificarOrdenTerminada(PDO $db, $ordenId, $evitarDuplicado = false) {
        try {
            $ord = $db->prepare('SELECT folio, asesor, cliente_nombre FROM ordenes WHERE id = ?');
            $ord->execute([(int)$ordenId]);
            $ord = $ord->fetch(PDO::FETCH_ASSOC);
            if (!$ord) return;

            if ($evitarDuplicado) {
                $ya = $db->prepare("SELECT COUNT(*) FROM notificaciones WHERE tipo = 'orden_terminada' AND orden_id = ?");
                $ya->execute([(int)$ordenId]);
                if ((int)$ya->fetchColumn() > 0) return;
            }

            // Alerta a Lina (administracion): verificar pago
            $db->prepare("
                INSERT INTO notificaciones (tipo, titulo, mensaje, folio, orden_id, rol_destino, leida)
                VALUES ('orden_terminada', 'Orden terminada — verificar pago', ?, ?, ?, 'administracion', 0)
            ")->execute([
                'La orden ' . $ord['folio'] . ' (' . $ord['cliente_nombre'] . ') está terminada. Verificar estado de pago.',
                $ord['folio'],
                (int)$ordenId
            ]);

            // Alerta al asesor comercial: programar entrega
            $asesor = $db->prepare("SELECT id FROM usuarios WHERE nombre = ? AND rol = 'comercial' LIMIT 1");
            $asesor->execute([$ord['asesor']]);
            $asesor = $asesor->fetch(PDO::FETCH_ASSOC);
            $db->prepare("
                INSERT INTO notificaciones (tipo, titulo, mensaje, folio, orden_id, rol_destino, usuario_id_dest, leida)
                VALUES ('orden_terminada', 'Orden lista — programar entrega', ?, ?, ?, 'comercial', ?, 0)
            ")->execute([
                'La orden ' . $ord['folio'] . ' (' . $ord['cliente_nombre'] . ') está lista. Programa la entrega.',
                $ord['folio'],
                (int)$ordenId,
                $asesor['id'] ?? null
            ]);
        } catch (Exception $ignored) {}
    }
}
