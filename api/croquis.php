<?php
// ============================================================
//  APEX GLASS - API: Croquis Técnicos
//  Archivo: api/croquis.php
//  Métodos: GET / POST / PUT / DELETE
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';

header('Content-Type: application/json; charset=utf-8');

$metodo = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$user   = requirePermiso('ver_ordenes');
$rol    = $user['rol'];
$nombre = $user['nombre'] ?? $user['usuario'] ?? 'sistema';
$pdo    = getDB();

// ── Roles permitidos ─────────────────────────────────────────────────────────
// Leer:   todos los que tienen ver_ordenes
// Crear/editar: comercial, dir_admin, dueno, administracion
// Eliminar: solo dir_admin

$puedeEditar  = in_array($rol, ['comercial','dir_admin','dueno','administracion','desarrollo']);
$puedeEliminar = in_array($rol, ['dir_admin','desarrollo']);

// ════════════════════════════════════════════════════════════════════════════
//  GET
// ════════════════════════════════════════════════════════════════════════════
if ($metodo === 'GET') {

    // GET ?id=X — croquis individual
    if (!empty($_GET['id'])) {
        $id   = (int)$_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM croquis_partidas WHERE id = ?");
        $stmt->execute([$id]);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); echo json_encode(['error' => 'No encontrado']); exit; }
        $row = decodificarJson($row);
        echo json_encode(['ok' => true, 'data' => $row]);
        exit;
    }

    // GET ?cotizacion_id=X — todos los croquis de una cotización
    if (!empty($_GET['cotizacion_id'])) {
        $cot_id = (int)$_GET['cotizacion_id'];
        $stmt   = $pdo->prepare("
            SELECT * FROM croquis_partidas
            WHERE cotizacion_id = ?
            ORDER BY num_partida ASC, id ASC
        ");
        $stmt->execute([$cot_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rows = array_map('decodificarJson', $rows);
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Parámetro requerido: id o cotizacion_id']);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
//  POST — crear
// ════════════════════════════════════════════════════════════════════════════
if ($metodo === 'POST') {
    if (!$puedeEditar) { http_response_code(403); echo json_encode(['error' => 'Sin permiso']); exit; }

    $cot_id      = (int)($body['cotizacion_id'] ?? 0);
    $num_partida = (int)($body['num_partida']   ?? 0);
    $forma       = $body['forma']   ?? 'rect';
    $ancho_mm    = (float)($body['ancho_mm']    ?? 0);
    $alto_mm     = (float)($body['alto_mm']     ?? 0);

    if (!$cot_id || !$num_partida || !$ancho_mm || !$alto_mm) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios: cotizacion_id, num_partida, ancho_mm, alto_mm']);
        exit;
    }

    if (!in_array($forma, ['rect','corte','L','trap','poligono','esq'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Forma inválida']);
        exit;
    }

    // Verificar que la cotización existe
    $check = $pdo->prepare("SELECT id FROM cotizaciones WHERE id = ?");
    $check->execute([$cot_id]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Cotización no encontrada']);
        exit;
    }

    $params_forma = isset($body['params_forma']) ? json_encode($body['params_forma']) : null;
    $elementos    = isset($body['elementos'])    ? json_encode($body['elementos'])    : null;
    $canteo       = isset($body['canteo'])       ? json_encode($body['canteo'])       : null;
    $notas        = trim($body['notas'] ?? '');

    $stmt = $pdo->prepare("
        INSERT INTO croquis_partidas
            (cotizacion_id, num_partida, forma, ancho_mm, alto_mm, params_forma, elementos, canteo, notas, creado_por)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $cot_id, $num_partida, $forma, $ancho_mm, $alto_mm,
        $params_forma, $elementos, $canteo,
        $notas ?: null,
        $nombre
    ]);

    $nuevo_id = (int)$pdo->lastInsertId();
    echo json_encode(['ok' => true, 'id' => $nuevo_id]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
//  PUT — actualizar
// ════════════════════════════════════════════════════════════════════════════
if ($metodo === 'PUT') {
    if (!$puedeEditar) { http_response_code(403); echo json_encode(['error' => 'Sin permiso']); exit; }

    $id = (int)($body['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT * FROM croquis_partidas WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$actual) { http_response_code(404); echo json_encode(['error' => 'No encontrado']); exit; }

    $forma    = $body['forma']    ?? $actual['forma'];
    $ancho_mm = (float)($body['ancho_mm'] ?? $actual['ancho_mm']);
    $alto_mm  = (float)($body['alto_mm']  ?? $actual['alto_mm']);

    if (!in_array($forma, ['rect','corte','L','trap','poligono','esq'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Forma inválida']);
        exit;
    }

    $params_forma = isset($body['params_forma']) ? json_encode($body['params_forma']) : $actual['params_forma'];
    $elementos    = isset($body['elementos'])    ? json_encode($body['elementos'])    : $actual['elementos'];
    $canteo       = isset($body['canteo'])       ? json_encode($body['canteo'])       : $actual['canteo'];
    $notas        = isset($body['notas'])        ? trim($body['notas'])               : $actual['notas'];

    $pdo->prepare("
        UPDATE croquis_partidas
        SET forma = ?, ancho_mm = ?, alto_mm = ?,
            params_forma = ?, elementos = ?, canteo = ?, notas = ?
        WHERE id = ?
    ")->execute([$forma, $ancho_mm, $alto_mm, $params_forma, $elementos, $canteo, $notas ?: null, $id]);

    echo json_encode(['ok' => true]);
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
//  DELETE
// ════════════════════════════════════════════════════════════════════════════
if ($metodo === 'DELETE') {
    if (!$puedeEliminar) { http_response_code(403); echo json_encode(['error' => 'Solo dir_admin puede eliminar croquis']); exit; }

    $id = (int)($body['id'] ?? $_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error' => 'ID requerido']); exit; }

    $pdo->prepare("DELETE FROM croquis_partidas WHERE id = ?")->execute([$id]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);

// ── Helper: decodifica campos JSON del row ────────────────────────────────
function decodificarJson($row) {
    foreach (['params_forma','elementos','canteo'] as $campo) {
        if (!empty($row[$campo])) {
            $decoded = json_decode($row[$campo], true);
            if ($decoded !== null) $row[$campo] = $decoded;
        }
    }
    return $row;
}