<?php
// ============================================================
//  APEX GLASS - API: Reproceso / Retrabajo
//  Archivo: api/reproceso.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');

$user = requireSessionApi();
$db   = getDB();

// Roles que pueden reportar retrabajo
$rolesPermitidos = ['operador', 'jefe_piso', 'dir_admin', 'director', 'administracion'];
if (!in_array($user['rol'], $rolesPermitidos)) {
    jsonResponse(['ok' => false, 'error' => 'Sin permiso para reportar retrabajo']);
}

$body = json_decode(file_get_contents('php://input'), true);

$pieza_id       = intval($body['pieza_id'] ?? 0);
$razon          = trim($body['razon']      ?? '');
$razon_otro     = trim($body['razon_otro'] ?? '');
$usuario_nombre = $_SESSION['user_name']   ?? 'Desconocido';
$usuario_id     = intval($_SESSION['user_id'] ?? 0);

if (!$pieza_id || !$razon) {
    jsonResponse(['ok' => false, 'error' => 'Faltan datos']);
}

$razon_final = $razon === 'Otro' ? $razon_otro : $razon;

if (!$razon_final) {
    jsonResponse(['ok' => false, 'error' => 'Especifica la raz�n']);
}

// Obtener pieza actual
$stmt = $db->prepare('
    SELECT p.*, o.folio, o.cliente_nombre, o.id as oid, o.asesor
    FROM piezas p
    JOIN ordenes o ON o.id = p.orden_id
    WHERE p.id = ?
');
$stmt->execute([$pieza_id]);
$p = $stmt->fetch();

if (!$p) {
    jsonResponse(['ok' => false, 'error' => 'Pieza no encontrada']);
}

$estatus_anterior = $p['estatus'];

try {
    $db->beginTransaction();

    // 1. Marcar pieza como descartada + guardar raz�n
    $db->prepare('
        UPDATE piezas 
        SET estatus = "descartada", 
            razon_retrabajo = ?,
            es_retrabajo = 1,
            updated_at = NOW()
        WHERE id = ?
    ')->execute([$razon_final, $pieza_id]);

    // 2. Historial � descartada
    $db->prepare('
        INSERT INTO historial_estatus 
        (pieza_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, notas)
        VALUES (?, ?, "descartada", ?, ?, ?)
    ')->execute([$pieza_id, $estatus_anterior, $usuario_id, $usuario_nombre, 'Retrabajo: ' . $razon_final]);

    // 3. Regresar pieza a pendiente
    $db->prepare('
        UPDATE piezas 
        SET estatus = "pendiente", updated_at = NOW()
        WHERE id = ?
    ')->execute([$pieza_id]);

    // 4. Historial � regreso a pendiente
    $db->prepare('
        INSERT INTO historial_estatus 
        (pieza_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, notas)
        VALUES (?, "descartada", "pendiente", ?, ?, ?)
    ')->execute([$pieza_id, $usuario_id, $usuario_nombre, 'Retrabajo: regresa a corte']);

    $titulo   = 'Retrabajo � ' . $p['folio'];
    $detalle  = $p['cristal'] . ' � ' . $p['ancho_mm'] . '�' . $p['alto_mm'] . 'mm';
    $partida  = 'P' . $p['partida'] . ' � ' . $p['pieza_num'] . '/' . $p['pieza_total'];
    $msgBase  = $p['cliente_nombre'] . ' � ' . $partida . ' � ' . $detalle . ' � Raz�n: ' . $razon_final . ' � Reportado por: ' . $usuario_nombre;

    // 5. Notificaci�n para corte
    $db->prepare('
        INSERT INTO notificaciones 
        (tipo, titulo, mensaje, rol_destino, usuario_id_orig, usuario_nombre, pieza_id, orden_id, folio, leida)
        VALUES ("retrabajo", ?, ?, "corte", ?, ?, ?, ?, ?, 0)
    ')->execute([
        $titulo,
        $partida . ' � ' . $detalle . ' � ' . $razon_final,
        $usuario_id, $usuario_nombre,
        $pieza_id, $p['oid'], $p['folio']
    ]);

    // 6. Notificaci�n para dir_admin (recibe todas)
    $db->prepare('
        INSERT INTO notificaciones 
        (tipo, titulo, mensaje, rol_destino, usuario_id_orig, usuario_nombre, pieza_id, orden_id, folio, leida)
        VALUES ("retrabajo", ?, ?, "dir_admin", ?, ?, ?, ?, ?, 0)
    ')->execute([
        $titulo, $msgBase,
        $usuario_id, $usuario_nombre,
        $pieza_id, $p['oid'], $p['folio']
    ]);

    // 7. Notificaci�n al asesor espec�fico de la orden (asesor1 o asesor2 solo ven las suyas)
    if (!empty($p['asesor'])) {
        // Buscar con LIKE porque usuarios.nombre puede ser 'Bethy' y ordenes.asesor 'Bethy Rocha'
        $stmtAsesor = $db->prepare("SELECT id FROM usuarios WHERE ? LIKE CONCAT('%', nombre, '%') AND rol = 'comercial' LIMIT 1");
        $stmtAsesor->execute([$p['asesor']]);
        $asesorRow = $stmtAsesor->fetch();
        if ($asesorRow) {
            $db->prepare('
                INSERT INTO notificaciones 
                (tipo, titulo, mensaje, rol_destino, usuario_id_dest, usuario_id_orig, usuario_nombre, pieza_id, orden_id, folio, leida)
                VALUES ("retrabajo", ?, ?, "comercial", ?, ?, ?, ?, ?, ?, 0)
            ')->execute([
                $titulo, $msgBase,
                $asesorRow['id'],
                $usuario_id, $usuario_nombre,
                $pieza_id, $p['oid'], $p['folio']
            ]);
        }
    }

    // 8. Notificación para jefe_piso
    $db->prepare('
        INSERT INTO notificaciones 
        (tipo, titulo, mensaje, rol_destino, usuario_id_orig, usuario_nombre, pieza_id, orden_id, folio, leida)
        VALUES ("retrabajo", ?, ?, "jefe_piso", ?, ?, ?, ?, ?, 0)
    ')->execute([
        $titulo, $msgBase,
        $usuario_id, $usuario_nombre,
        $pieza_id, $p['oid'], $p['folio']
    ]);

    $db->commit();

    jsonResponse([
        'ok'    => true,
        'folio' => $p['folio'],
        'razon' => $razon_final,
        'msg'   => 'Retrabajo registrado. Pieza regresada a corte.'
    ]);

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
}