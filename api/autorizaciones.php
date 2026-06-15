<?php
// ============================================================
//  APEX GLASS - Autorizaciones de descuento > 10%
//  GET  ?cotizacion_id=X  → última autorización activa
//  POST { accion:'resolver', autorizacion_id, estatus, nota } → dir_admin resuelve
// ============================================================
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

if (empty($_SESSION['user_id'])) {
    jsonResponse(['error' => 'No autenticado'], 401);
}

$rol     = $_SESSION['user_rol']  ?? '';
$usuario = $_SESSION['user_name'] ?? '';
$method  = $_SERVER['REQUEST_METHOD'];
$db      = getDB();

// ── GET → pendientes (dir_admin) o estado de una cotización ──────────────────
if ($method === 'GET') {

    // Lista de pendientes — solo dir_admin
    if (isset($_GET['pendientes'])) {
        if ($rol !== 'dir_admin') jsonResponse(['error' => 'Sin permiso'], 403);

        $stmt = $db->prepare("
            SELECT a.id, a.cotizacion_id, a.descuento, a.motivo,
                   a.solicitado_por, a.fecha_solicitud,
                   c.folio AS cot_folio, c.cliente_nombre, c.asesor_nombre,
                   COALESCE(o.folio, c.folio) AS folio_display
            FROM autorizaciones_descuento a
            JOIN cotizaciones c ON a.cotizacion_id = c.id
            LEFT JOIN ordenes o ON o.id = c.orden_id
            WHERE a.estatus = 'pendiente'
            ORDER BY a.fecha_solicitud ASC
        ");
        $stmt->execute();
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Estado de una cotización específica
    $cot_id = (int)($_GET['cotizacion_id'] ?? 0);
    if (!$cot_id) jsonResponse(['error' => 'cotizacion_id requerido'], 422);

    $stmt = $db->prepare("
        SELECT id, cotizacion_id, folio, descuento, motivo, estatus,
               solicitado_por, fecha_solicitud,
               autorizado_por, fecha_resolucion, nota_resolucion
        FROM autorizaciones_descuento
        WHERE cotizacion_id = ?
        ORDER BY fecha_solicitud DESC
        LIMIT 1
    ");
    $stmt->execute([$cot_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) jsonResponse(['error' => 'sin_registro'], 404);
    jsonResponse($row);
}

// ── POST → resolver autorización (solo dir_admin) ─────────────────────────────
if ($method === 'POST') {
    if ($rol !== 'dir_admin') jsonResponse(['error' => 'Sin permiso'], 403);

    $body     = json_decode(file_get_contents('php://input'), true) ?: [];
    $accion   = $body['accion'] ?? '';

    if ($accion === 'resolver') {
        $auth_id = (int)($body['autorizacion_id'] ?? 0);
        $estatus = $body['estatus'] ?? '';
        $nota    = trim($body['nota'] ?? '');

        if (!$auth_id || !in_array($estatus, ['aprobado', 'rechazado'])) {
            jsonResponse(['error' => 'Parámetros inválidos'], 422);
        }
        if ($estatus === 'rechazado' && !$nota) {
            jsonResponse(['error' => 'Se requiere nota para rechazar'], 422);
        }

        $stmt = $db->prepare("SELECT id, estatus FROM autorizaciones_descuento WHERE id = ?");
        $stmt->execute([$auth_id]);
        $auth = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$auth) jsonResponse(['error' => 'Solicitud no encontrada'], 404);
        if ($auth['estatus'] !== 'pendiente') jsonResponse(['error' => 'Esta solicitud ya fue resuelta'], 409);

        $db->prepare("
            UPDATE autorizaciones_descuento
            SET estatus = ?, autorizado_por = ?, fecha_resolucion = NOW(), nota_resolucion = ?
            WHERE id = ?
        ")->execute([$estatus, $usuario, $nota, $auth_id]);

        jsonResponse(['ok' => true, 'estatus' => $estatus]);
    }

    jsonResponse(['error' => 'Acción desconocida'], 400);
}

jsonResponse(['error' => 'Método no permitido'], 405);
