<?php
// ============================================================
//  APEX GLASS - API: Maquila
//  Archivo: api/maquila.php
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
require_once 'cotizacion_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$user           = requireSessionApi();
$rol            = $user['rol'];
$usuario_id     = $user['id'];
$usuario_nombre = $user['nombre'];
$db             = getDB();
$method         = $_SERVER['REQUEST_METHOD'];

if (!tienePermiso($rol, 'ver_maquila')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$recurso = $_GET['recurso'] ?? null;
$body    = $method !== 'GET' ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];
if ($method !== 'GET') {
    $recurso = $recurso ?? ($body['recurso'] ?? null);
}

// ─── Cálculo de ml de canteado según selección tipo CPB ──────────────────────
function calcularMlCanteado($cpb, $ancho, $alto) {
    $anchoM = $ancho / 1000;
    $altoM  = $alto  / 1000;
    switch ($cpb) {
        case 'Perimetral':                return 2 * $anchoM + 2 * $altoM;
        case 'Larguero':                  return $altoM;
        case 'Largueros':                 return 2 * $altoM;
        case 'Cabezal':                   return $anchoM;
        case 'Cabezales':                 return 2 * $anchoM;
        case '1 Larguero - 1 cabezal':    return $altoM + $anchoM;
        case '2 Largueros - 1 cabezal':   return 2 * $altoM + $anchoM;
        case '1 Larguero - 2 cabezales':  return $altoM + 2 * $anchoM;
        default:                          return 0.0; // 'No'
    }
}

// ─── Búsqueda de precio activo por servicio + espesor ────────────────────────
function buscarPrecio($db, $servicio, $espesor_mm) {
    $stmt = $db->prepare("SELECT precio FROM maquila_precios WHERE servicio = ? AND espesor_mm = ? AND activo = 1");
    $stmt->execute([$servicio, $espesor_mm]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? (float)$row['precio'] : null;
}

// ─── Calcula una partida completa (subtotales por servicio + total) ──────────
// Lanza una excepción de dominio (array de error) si falta un precio en catálogo.
function calcularPartidaMaquila($db, $p) {
    $ancho      = (int)($p['ancho'] ?? 0);
    $alto       = (int)($p['alto']  ?? 0);
    $cantidad   = max(1, (int)($p['cantidad'] ?? 1));
    $espesor_mm = (float)($p['espesor_mm'] ?? 0);
    if (!$ancho || !$alto || !$espesor_mm) return null;

    $m2 = round(($ancho / 1000) * ($alto / 1000), 6);

    $out = [
        'cristal_tipo_id' => (int)($p['cristal_tipo_id'] ?? 0) ?: null,
        'espesor_mm'      => $espesor_mm,
        'ancho'           => $ancho,
        'alto'            => $alto,
        'cantidad'        => $cantidad,
        'm2'              => $m2,
        'detalles'        => trim($p['detalles'] ?? ''),
        'corte' => 0, 'ml_corte' => 0, 'precio_corte_usado' => null, 'subtotal_corte' => 0,
        'canteado' => 0, 'cpb' => 'No', 'ml_canteado' => 0, 'precio_canteado_usado' => null, 'subtotal_canteado' => 0,
        'taladros_pasados' => 0, 'taladros_avellanados' => 0, 'precio_taladro_usado' => null, 'subtotal_taladro' => 0,
        'templado' => 0, 'precio_horno_usado' => null, 'subtotal_horno' => 0,
    ];

    if (!empty($p['corte'])) {
        $precio = buscarPrecio($db, 'corte', $espesor_mm);
        if ($precio === null) return ['__error' => "Sin precio de corte para espesor {$espesor_mm}mm"];
        $ml = round(2 * ($ancho / 1000) + 2 * ($alto / 1000), 4);
        $out['corte'] = 1;
        $out['ml_corte'] = $ml;
        $out['precio_corte_usado'] = $precio;
        $out['subtotal_corte'] = round($ml * $precio * $cantidad, 2);
    }

    if (!empty($p['canteado'])) {
        $cpb = $p['cpb'] ?? 'No';
        $precio = buscarPrecio($db, 'canteado', $espesor_mm);
        if ($precio === null) return ['__error' => "Sin precio de canteado para espesor {$espesor_mm}mm"];
        $ml = round(calcularMlCanteado($cpb, $ancho, $alto), 4);
        $out['canteado'] = 1;
        $out['cpb'] = $cpb;
        $out['ml_canteado'] = $ml;
        $out['precio_canteado_usado'] = $precio;
        $out['subtotal_canteado'] = round($ml * $precio * $cantidad, 2);
    }

    $pasados     = (int)($p['taladros_pasados']     ?? 0);
    $avellanados = (int)($p['taladros_avellanados'] ?? 0);
    if ($pasados + $avellanados > 0) {
        $precio = buscarPrecio($db, 'taladro', $espesor_mm);
        if ($precio === null) return ['__error' => "Sin precio de taladro para espesor {$espesor_mm}mm"];
        $out['taladros_pasados'] = $pasados;
        $out['taladros_avellanados'] = $avellanados;
        $out['precio_taladro_usado'] = $precio;
        $out['subtotal_taladro'] = round(($pasados + $avellanados) * $precio * $cantidad, 2);
    }

    if (!empty($p['templado'])) {
        $precio = buscarPrecio($db, 'horno', $espesor_mm);
        if ($precio === null) return ['__error' => "Sin precio de horno para espesor {$espesor_mm}mm"];
        $out['templado'] = 1;
        $out['precio_horno_usado'] = $precio;
        $out['subtotal_horno'] = round($m2 * $precio * $cantidad, 2);
    }

    $out['subtotal'] = round(
        $out['subtotal_corte'] + $out['subtotal_canteado'] +
        $out['subtotal_taladro'] + $out['subtotal_horno'], 2
    );

    return $out;
}

// ─── Recurso: cotizacion (maquila) ────────────────────────────────────────────
if ($recurso === 'cotizacion' && $method === 'POST' && ($body['accion'] ?? '') === 'crear') {
    if (!in_array($rol, ['dir_admin','dueno','comercial','desarrollo'])) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    $cliente_id   = (int)($body['cliente_id'] ?? 0);
    $proyecto     = trim($body['proyecto'] ?? '');
    $localidad    = ($body['localidad'] ?? '') === 'foraneo' ? 'foraneo' : 'local';
    $ciudad       = trim($body['ciudad_destino'] ?? '');
    $tipo_entrega = in_array($body['tipo_entrega'] ?? '', ['domicilio','planta']) ? $body['tipo_entrega'] : 'domicilio';
    $partidas     = $body['partidas'] ?? [];

    if (!$cliente_id)      { jsonResponse(['error' => 'Cliente requerido']); exit; }
    if (empty($partidas))  { jsonResponse(['error' => 'Se requiere al menos una partida']); exit; }

    $stmt = $db->prepare("SELECT razon_social, nombre FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cliente) { jsonResponse(['error' => 'Cliente no encontrado']); exit; }
    $cliente_nombre = $cliente['razon_social'] ?: $cliente['nombre'];

    $fecha_entrega = calcularFechaEntrega($db, date('Y-m-d'), $localidad, $ciudad);

    $partidas_calc = [];
    $subtotal_total = 0;
    foreach ($partidas as $p) {
        $calc = calcularPartidaMaquila($db, $p);
        if ($calc === null) continue;
        if (isset($calc['__error'])) { jsonResponse(['error' => $calc['__error']]); exit; }
        $partidas_calc[] = $calc;
        $subtotal_total += $calc['subtotal'];
    }
    if (empty($partidas_calc)) { jsonResponse(['error' => 'Ninguna partida válida']); exit; }

    $iva_total   = round($subtotal_total * 0.16, 2);
    $total_final = round($subtotal_total + $iva_total, 2);

    $folio = generarFolio($db);

    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO cotizaciones
            (folio, fecha, cliente_id, cliente_nombre, asesor_id, asesor_nombre,
             proyecto, tipo_entrega, localidad, ciudad_destino, fecha_entrega,
             subtotal, iva, total, saldo_pendiente, estatus, tipo)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $folio, date('Y-m-d'), $cliente_id, $cliente_nombre,
            $usuario_id, $usuario_nombre, $proyecto, $tipo_entrega,
            $localidad, $ciudad, $fecha_entrega,
            $subtotal_total, $iva_total, $total_final, $total_final,
            'cotizacion', 'maquila'
        ]);
        $cot_id = $db->lastInsertId();

        $stmtP = $db->prepare("INSERT INTO cotizaciones_maquila_partidas
            (cotizacion_id, num_partida, cristal_tipo_id, espesor_mm, ancho, alto, cantidad, m2,
             corte, ml_corte, precio_corte_usado, subtotal_corte,
             canteado, cpb, ml_canteado, precio_canteado_usado, subtotal_canteado,
             taladros_pasados, taladros_avellanados, precio_taladro_usado, subtotal_taladro,
             templado, precio_horno_usado, subtotal_horno,
             detalles, subtotal)
            VALUES (?,?,?,?,?,?,?,?, ?,?,?,?, ?,?,?,?,?, ?,?,?,?, ?,?,?, ?,?)
        ");
        foreach ($partidas_calc as $i => $p) {
            $stmtP->execute([
                $cot_id, $i + 1, $p['cristal_tipo_id'], $p['espesor_mm'], $p['ancho'], $p['alto'], $p['cantidad'], $p['m2'],
                $p['corte'], $p['ml_corte'], $p['precio_corte_usado'], $p['subtotal_corte'],
                $p['canteado'], $p['cpb'], $p['ml_canteado'], $p['precio_canteado_usado'], $p['subtotal_canteado'],
                $p['taladros_pasados'], $p['taladros_avellanados'], $p['precio_taladro_usado'], $p['subtotal_taladro'],
                $p['templado'], $p['precio_horno_usado'], $p['subtotal_horno'],
                $p['detalles'], $p['subtotal']
            ]);
        }

        $db->commit();
        jsonResponse(['ok' => true, 'id' => $cot_id, 'folio' => $folio]);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => 'Error al guardar: ' . $e->getMessage()], 500);
        exit;
    }
}

// ─── Recurso: tipos_vidrio ────────────────────────────────────────────────────
if ($recurso === 'tipos_vidrio') {
    if ($method === 'GET') {
        $solo_activos = ($_GET['activos'] ?? '1') === '1';
        $where = $solo_activos ? 'WHERE activo = 1' : '';
        $stmt  = $db->query("SELECT * FROM maquila_tipos_vidrio $where ORDER BY nombre ASC");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if (!tienePermiso($rol, 'gestionar_maquila_precios')) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    if ($method === 'POST') {
        $nombre = trim($body['nombre'] ?? '');
        if (!$nombre) { jsonResponse(['error' => 'Nombre requerido']); exit; }
        $db->prepare("INSERT INTO maquila_tipos_vidrio (nombre) VALUES (?)")->execute([$nombre]);
        jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
        exit;
    }

    if ($method === 'PUT') {
        $id     = (int)($body['id'] ?? 0);
        $nombre = trim($body['nombre'] ?? '');
        $activo = isset($body['activo']) ? (int)$body['activo'] : null;
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
        if ($nombre !== '') {
            $db->prepare("UPDATE maquila_tipos_vidrio SET nombre = ? WHERE id = ?")->execute([$nombre, $id]);
        }
        if ($activo !== null) {
            $db->prepare("UPDATE maquila_tipos_vidrio SET activo = ? WHERE id = ?")->execute([$activo, $id]);
        }
        jsonResponse(['ok' => true]);
        exit;
    }

    jsonResponse(['error' => 'Método no permitido'], 405);
    exit;
}

// ─── Recurso: precios (corte/canteado/taladro/horno por espesor) ─────────────
if ($recurso === 'precios') {
    if ($method === 'GET') {
        $stmt = $db->query("SELECT * FROM maquila_precios WHERE activo = 1 ORDER BY servicio, espesor_mm");
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if (!tienePermiso($rol, 'gestionar_maquila_precios')) {
        jsonResponse(['error' => 'Sin permiso'], 403);
    }

    if ($method === 'POST' || $method === 'PUT') {
        $servicio   = $body['servicio']   ?? '';
        $espesor_mm = (float)($body['espesor_mm'] ?? 0);
        $precio     = (float)($body['precio']     ?? 0);

        if (!in_array($servicio, ['corte','canteado','taladro','horno'])) {
            jsonResponse(['error' => 'Servicio inválido']); exit;
        }
        if ($espesor_mm <= 0 || $precio <= 0) {
            jsonResponse(['error' => 'Espesor y precio deben ser mayores a 0']); exit;
        }

        // Upsert por (servicio, espesor_mm) — la UNIQUE KEY del Task 1 lo garantiza
        $db->prepare("
            INSERT INTO maquila_precios (servicio, espesor_mm, precio, activo)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE precio = VALUES(precio), activo = 1
        ")->execute([$servicio, $espesor_mm, $precio]);

        jsonResponse(['ok' => true]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($body['id'] ?? ($_GET['id'] ?? 0));
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
        $db->prepare("UPDATE maquila_precios SET activo = 0 WHERE id = ?")->execute([$id]);
        jsonResponse(['ok' => true]);
        exit;
    }

    jsonResponse(['error' => 'Método no permitido'], 405);
    exit;
}

jsonResponse(['error' => 'Recurso no encontrado'], 404);
