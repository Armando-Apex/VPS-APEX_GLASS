<?php
// Checador ZKTeco — cola de comandos alta/baja (UPD-570).
// Dos mundos en un archivo:
//   - Lado UI (sesión, ver_rh/gestionar_rh): encolar_alta, encolar_baja, historial, reintentar
//   - Lado dispositivo (llave compartida X-Checador-Key, sin sesión): pendientes, confirmar
//     Contrato para el listener de la Raspberry Pi (Fase 2, no implementado aún):
//       GET  ?accion=pendientes  → devuelve comandos pendientes y los marca "entregado"
//       POST ?accion=confirmar   → {id, ok, mensaje} avisa si el reloj aceptó/rechazó el comando
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

// Lunes–Domingo no aplica aquí — la semana de asistencia real de Armando es
// Jueves-Miércoles (con domingo de descanso dentro de ella), distinta de la
// semana Lunes-Domingo que usa Bono de Corte. Ver CLAUDE.md sección 1.
function checadorSemanaJueMier($fecha) {
    $d = new DateTime($fecha);
    $dow = (int)$d->format('N'); // 1=lunes .. 7=domingo
    $offset = ($dow - 4 + 7) % 7; // días desde el jueves más reciente (4=jueves)
    $inicio = (clone $d)->modify('-' . $offset . ' days');
    $fin = (clone $inicio)->modify('+6 days');
    return [$inicio->format('Y-m-d'), $fin->format('Y-m-d')];
}

$ACCIONES_DISPOSITIVO = ['pendientes', 'confirmar', 'registrar_checadas'];

if (in_array($accion, $ACCIONES_DISPOSITIVO, true)) {
    $llave = $_SERVER['HTTP_X_CHECADOR_KEY'] ?? '';
    if (!CHECADOR_LISTENER_KEY || !hash_equals(CHECADOR_LISTENER_KEY, $llave)) {
        jsonResponse(['error' => 'No autorizado'], 401);
    }
    $pdo = getDB();

    if ($method === 'GET' && $accion === 'pendientes') {
        $pdo->beginTransaction();
        $comandos = $pdo->query("
            SELECT id, empleado_id, tipo, pin, nombre_reloj
            FROM checador_comandos
            WHERE estado = 'pendiente'
            ORDER BY id ASC
            LIMIT 50
            FOR UPDATE
        ")->fetchAll(PDO::FETCH_ASSOC);

        if ($comandos) {
            $ids = implode(',', array_map('intval', array_column($comandos, 'id')));
            $pdo->exec("UPDATE checador_comandos SET estado = 'entregado', entregado_at = NOW() WHERE id IN ($ids)");
        }
        $pdo->commit();
        jsonResponse(['comandos' => $comandos]); exit;
    }

    if ($method === 'POST' && $accion === 'confirmar') {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $id      = (int)($body['id'] ?? 0);
        $ok      = !empty($body['ok']);
        $mensaje = trim($body['mensaje'] ?? '');
        if (!$id) { jsonResponse(['error' => 'id requerido']); exit; }

        $stmt = $pdo->prepare("SELECT * FROM checador_comandos WHERE id = ?");
        $stmt->execute([$id]);
        $cmd = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cmd) { jsonResponse(['error' => 'Comando no encontrado'], 404); exit; }

        $nuevoEstado = $ok ? 'confirmado' : 'error';
        $pdo->prepare("UPDATE checador_comandos SET estado = ?, error_mensaje = ?, confirmado_at = NOW() WHERE id = ?")
            ->execute([$nuevoEstado, $ok ? null : ($mensaje ?: 'Error no especificado'), $id]);

        // Baja confirmada → libera el PIN. Alta rechazada por el reloj (ej. PIN ya ocupado ahí) → libera el PIN reservado.
        if (($ok && $cmd['tipo'] === 'baja') || (!$ok && $cmd['tipo'] === 'alta')) {
            $pdo->prepare("UPDATE nomina_empleados SET checador_pin = NULL WHERE id = ? AND checador_pin = ?")
                ->execute([$cmd['empleado_id'], $cmd['pin']]);
        }

        jsonResponse(['ok' => true]); exit;
    }

    // El listener manda las líneas crudas de ATTLOG (PIN\tFechaHora\tEstatus\tVerifyMode\t...)
    // tal cual las empuja el reloj — se parsean e insertan aquí, deduplicado por (pin, fecha_hora).
    if ($method === 'POST' && $accion === 'registrar_checadas') {
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $lineas  = $body['lineas'] ?? [];
        if (!is_array($lineas)) { jsonResponse(['error' => 'lineas debe ser un arreglo']); exit; }

        $insertadas = 0;
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO checador_checadas (pin, fecha_hora, estatus, verify_mode)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($lineas as $linea) {
            $campos = explode("\t", trim((string)$linea));
            if (count($campos) < 2) continue;
            $pin = (int)$campos[0];
            $fechaHora = trim($campos[1]);
            if (!$pin || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fechaHora)) continue;
            $estatus = isset($campos[2]) && $campos[2] !== '' ? (int)$campos[2] : null;
            $verifyMode = isset($campos[3]) && $campos[3] !== '' ? (int)$campos[3] : null;
            $stmt->execute([$pin, $fechaHora, $estatus, $verifyMode]);
            if ($stmt->rowCount() > 0) $insertadas++;
        }

        jsonResponse(['ok' => true, 'insertadas' => $insertadas, 'recibidas' => count($lineas)]); exit;
    }

    jsonResponse(['error' => 'Acción no soportada'], 400);
    exit;
}

// ── A partir de aquí, endpoints de UI — requieren sesión ───────────────────────
$user   = requireSessionApi();
$rol    = $user['rol'];
$nombre = $user['nombre'];
if (!tienePermiso($rol, 'ver_rh')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}
$pdo = getDB();

if ($method === 'GET' && $accion === 'historial') {
    $empleado_id = (int)($_GET['empleado_id'] ?? 0);
    if (!$empleado_id) { jsonResponse(['error' => 'empleado_id requerido']); exit; }
    $stmt = $pdo->prepare("SELECT * FROM checador_comandos WHERE empleado_id = ? ORDER BY id DESC");
    $stmt->execute([$empleado_id]);
    jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

// Reporte semanal de asistencia — semana real Jueves-Miércoles (7 días), pero el
// encabezado muestra el miércoles anterior como referencia visual (a petición de
// Armando, 08-sep-2026) sin que cuente como día de esa semana.
if ($method === 'GET' && $accion === 'asistencia_semana') {
    $ref = $_GET['semana'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ref)) { jsonResponse(['error' => 'Fecha inválida']); exit; }
    list($inicio, $fin) = checadorSemanaJueMier($ref);
    $miercolesAnterior = (new DateTime($inicio))->modify('-1 day')->format('Y-m-d');

    $empleados = $pdo->query("
        SELECT id, nombre, checador_pin, numero_dispersion
        FROM nomina_empleados
        WHERE activo = 1
        ORDER BY (numero_dispersion IS NULL), numero_dispersion ASC, nombre ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $pins = array_values(array_filter(array_column($empleados, 'checador_pin')));
    $checadasPorPinDia = [];
    if ($pins) {
        $in = implode(',', array_map('intval', $pins));
        $stmt = $pdo->prepare("
            SELECT pin, fecha_hora FROM checador_checadas
            WHERE pin IN ($in) AND fecha_hora >= ? AND fecha_hora < ?
            ORDER BY fecha_hora ASC
        ");
        $stmt->execute([$inicio . ' 00:00:00', (new DateTime($fin))->modify('+1 day')->format('Y-m-d')]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fecha = substr($row['fecha_hora'], 0, 10);
            $hora  = substr($row['fecha_hora'], 11, 5);
            $checadasPorPinDia[$row['pin']][$fecha][] = $hora;
        }
    }

    $dias = [];
    $cursor = new DateTime($inicio);
    for ($i = 0; $i < 7; $i++) { $dias[] = $cursor->format('Y-m-d'); $cursor->modify('+1 day'); }

    $filas = [];
    foreach ($empleados as $e) {
        $porDia = [];
        foreach ($dias as $d) {
            $porDia[$d] = $e['checador_pin'] ? ($checadasPorPinDia[$e['checador_pin']][$d] ?? []) : [];
        }
        $filas[] = [
            'id' => $e['id'], 'nombre' => $e['nombre'],
            'numero_dispersion' => $e['numero_dispersion'], 'checador_pin' => $e['checador_pin'],
            'dias' => $porDia
        ];
    }

    jsonResponse([
        'inicio' => $inicio, 'fin' => $fin, 'miercoles_anterior' => $miercolesAnterior,
        'dias' => $dias, 'filas' => $filas
    ]); exit;
}

if (!tienePermiso($rol, 'gestionar_rh')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST' && $accion === 'encolar_alta') {
    $empleado_id  = (int)($body['empleado_id'] ?? 0);
    $pin          = (int)($body['pin'] ?? 0);
    $nombre_reloj = trim($body['nombre_reloj'] ?? '');

    if (!$empleado_id || !$pin) { jsonResponse(['error' => 'Datos incompletos']); exit; }
    if ($pin < 1 || $pin > 99999999) { jsonResponse(['error' => 'PIN inválido (1 a 99999999)']); exit; }
    if (!$nombre_reloj) { jsonResponse(['error' => 'El nombre para el reloj es obligatorio']); exit; }
    if (mb_strlen($nombre_reloj) > 24) { jsonResponse(['error' => 'El nombre no puede superar 24 caracteres (límite del reloj)']); exit; }

    $emp = $pdo->prepare("SELECT id, checador_pin FROM nomina_empleados WHERE id = ?");
    $emp->execute([$empleado_id]);
    $emp = $emp->fetch(PDO::FETCH_ASSOC);
    if (!$emp) { jsonResponse(['error' => 'Empleado no encontrado']); exit; }
    if ($emp['checador_pin']) { jsonResponse(['error' => 'Este empleado ya tiene un PIN asignado (' . $emp['checador_pin'] . ')']); exit; }

    $dup = $pdo->prepare("SELECT COUNT(*) FROM nomina_empleados WHERE checador_pin = ?");
    $dup->execute([$pin]);
    if ($dup->fetchColumn() > 0) { jsonResponse(['error' => 'Ese PIN ya está asignado a otro empleado en Apex']); exit; }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE nomina_empleados SET checador_pin = ? WHERE id = ?")->execute([$pin, $empleado_id]);
        $pdo->prepare("
            INSERT INTO checador_comandos (empleado_id, tipo, pin, nombre_reloj, creado_por)
            VALUES (?, 'alta', ?, ?, ?)
        ")->execute([$empleado_id, $pin, $nombre_reloj, $nombre]);
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Error al encolar']); exit;
    }

    jsonResponse(['ok' => true, 'id' => $pdo->lastInsertId()]); exit;
}

if ($method === 'POST' && $accion === 'encolar_baja') {
    $empleado_id = (int)($body['empleado_id'] ?? 0);
    if (!$empleado_id) { jsonResponse(['error' => 'empleado_id requerido']); exit; }

    $emp = $pdo->prepare("SELECT id, checador_pin FROM nomina_empleados WHERE id = ?");
    $emp->execute([$empleado_id]);
    $emp = $emp->fetch(PDO::FETCH_ASSOC);
    if (!$emp || !$emp['checador_pin']) { jsonResponse(['error' => 'Este empleado no tiene PIN activo en el reloj']); exit; }

    $pend = $pdo->prepare("
        SELECT COUNT(*) FROM checador_comandos
        WHERE empleado_id = ? AND tipo = 'baja' AND estado IN ('pendiente', 'entregado')
    ");
    $pend->execute([$empleado_id]);
    if ($pend->fetchColumn() > 0) { jsonResponse(['error' => 'Ya hay una baja en proceso para este empleado']); exit; }

    $pdo->prepare("
        INSERT INTO checador_comandos (empleado_id, tipo, pin, creado_por)
        VALUES (?, 'baja', ?, ?)
    ")->execute([$empleado_id, $emp['checador_pin'], $nombre]);

    jsonResponse(['ok' => true]); exit;
}

if ($method === 'POST' && $accion === 'reintentar') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'id requerido']); exit; }
    $pdo->prepare("
        UPDATE checador_comandos SET estado = 'pendiente', entregado_at = NULL, error_mensaje = NULL
        WHERE id = ? AND estado IN ('entregado', 'error')
    ")->execute([$id]);
    jsonResponse(['ok' => true]); exit;
}

jsonResponse(['error' => 'Acción no soportada'], 400);
