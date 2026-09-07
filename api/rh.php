<?php
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');

$user   = requireSessionApi();
$rol    = $user['rol'];
$nombre = $user['nombre'];
$method = $_SERVER['REQUEST_METHOD'];

if (!tienePermiso($rol, 'ver_rh')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

$pdo    = getDB();
$accion = $_GET['accion'] ?? '';

// ── Tabla de vacaciones dignas (LFT 2023) ──────────────────────────────────────
function rhDiasPorAntiguedad($anio) {
    if ($anio < 1) return 0;
    if ($anio <= 5) return 12 + ($anio - 1) * 2; // 1=12,2=14,3=16,4=18,5=20
    return 22 + intdiv($anio - 6, 5) * 2;        // 6-10=22, 11-15=24, 16-20=26...
}

// Genera (si faltan) los periodos de vacaciones desde el año 1 hasta la antigüedad actual
function rhAsegurarPeriodos($pdo, $empleado_id, $fecha_ingreso) {
    if (!$fecha_ingreso) return;
    $ingreso = new DateTime($fecha_ingreso);
    $hoy     = new DateTime();
    $anios   = (int)$ingreso->diff($hoy)->y;
    if ($anios < 1) return;

    for ($anio = 1; $anio <= $anios; $anio++) {
        $inicio = (clone $ingreso)->modify('+' . ($anio - 1) . ' years');
        $fin    = (clone $ingreso)->modify('+' . $anio . ' years')->modify('-1 day');
        $dias   = rhDiasPorAntiguedad($anio);

        $pdo->prepare("
            INSERT IGNORE INTO rh_vacaciones_periodos
                (empleado_id, anio_antiguedad, fecha_inicio_periodo, fecha_fin_periodo, dias_derecho)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$empleado_id, $anio, $inicio->format('Y-m-d'), $fin->format('Y-m-d'), $dias]);
    }
}

// ── Subida de archivo (foto o documento) — validación estricta ────────────────
function rhGuardarArchivo($archivo) {
    if (empty($archivo) || $archivo['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'No se recibió archivo o hubo un error en la subida'];
    }
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
        return ['error' => 'Formato no permitido. Solo jpg, png, pdf'];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($archivo['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'])) {
        return ['error' => 'Tipo de archivo no válido'];
    }
    if ($archivo['size'] > 10 * 1024 * 1024) {
        return ['error' => 'El archivo supera el límite de 10 MB'];
    }

    $dir = __DIR__ . '/../../archivos_rh/';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
        file_put_contents($dir . '.htaccess', "Order deny,allow\nDeny from all\nRequire all denied\n");
    }

    $nombreServidor = date('Y-m-d_H-i') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($archivo['tmp_name'], $dir . $nombreServidor)) {
        return ['error' => 'Error al guardar el archivo en el servidor'];
    }
    return ['ok' => true, 'nombre_servidor' => $nombreServidor, 'nombre_original' => $archivo['name']];
}

// ── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    if ($accion === 'listar') {
        $solo_activos = ($_GET['activos'] ?? '1') === '1';
        $where = $solo_activos ? 'WHERE activo = 1' : '';
        $stmt = $pdo->query("
            SELECT id, nombre, puesto, departamento, area, foto, fecha_ingreso, activo
            FROM nomina_empleados $where
            ORDER BY nombre ASC
        ");
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as &$f) {
            $f['antiguedad_anios'] = $f['fecha_ingreso']
                ? (new DateTime($f['fecha_ingreso']))->diff(new DateTime())->y
                : null;
        }
        jsonResponse($filas); exit;
    }

    if ($accion === 'detalle') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

        $stmt = $pdo->prepare("SELECT * FROM nomina_empleados WHERE id = ?");
        $stmt->execute([$id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$emp) { jsonResponse(['error' => 'Empleado no encontrado'], 404); }

        $emp['antiguedad_anios'] = $emp['fecha_ingreso']
            ? (new DateTime($emp['fecha_ingreso']))->diff(new DateTime())->y
            : null;

        rhAsegurarPeriodos($pdo, $id, $emp['fecha_ingreso']);

        $docs = $pdo->prepare("SELECT * FROM rh_documentos WHERE empleado_id = ? ORDER BY created_at DESC");
        $docs->execute([$id]);

        $periodos = $pdo->prepare("SELECT * FROM rh_vacaciones_periodos WHERE empleado_id = ? ORDER BY anio_antiguedad DESC");
        $periodos->execute([$id]);

        $incidencias = $pdo->prepare("SELECT * FROM rh_incidencias WHERE empleado_id = ? ORDER BY fecha_inicio DESC");
        $incidencias->execute([$id]);

        jsonResponse([
            'empleado'     => $emp,
            'documentos'   => $docs->fetchAll(PDO::FETCH_ASSOC),
            'vacaciones'   => $periodos->fetchAll(PDO::FETCH_ASSOC),
            'incidencias'  => $incidencias->fetchAll(PDO::FETCH_ASSOC),
        ]); exit;
    }

    if ($accion === 'vacaciones_detalle') {
        $periodo_id = (int)($_GET['periodo_id'] ?? 0);
        if (!$periodo_id) { jsonResponse(['error' => 'periodo_id requerido']); exit; }
        $stmt = $pdo->prepare("SELECT * FROM rh_vacaciones_detalle WHERE periodo_id = ? ORDER BY fecha_inicio DESC");
        $stmt->execute([$periodo_id]);
        jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }

    if ($accion === 'descargar') {
        $tipo = $_GET['tipo'] ?? 'documento'; // 'documento' o 'foto'
        if ($tipo === 'foto') {
            $empleado_id = (int)($_GET['empleado_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT foto AS nombre_servidor, nombre AS nombre_original FROM nomina_empleados WHERE id = ?");
            $stmt->execute([$empleado_id]);
        } else {
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT nombre_servidor, nombre_original FROM rh_documentos WHERE id = ?");
            $stmt->execute([$id]);
        }
        $archivo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$archivo || !$archivo['nombre_servidor']) { jsonResponse(['error' => 'Archivo no encontrado'], 404); }

        $ruta = __DIR__ . '/../../archivos_rh/' . basename($archivo['nombre_servidor']);
        if (!file_exists($ruta)) { jsonResponse(['error' => 'Archivo no existe en servidor'], 404); }

        $ext  = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);

        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($archivo['nombre_original'] ?: $archivo['nombre_servidor']) . '"');
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=3600');
        readfile($ruta);
        exit;
    }

    jsonResponse(['error' => 'Acción no soportada'], 400);
    exit;
}

// ── A partir de aquí, requiere permiso de escritura ────────────────────────────
if (!tienePermiso($rol, 'gestionar_rh')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

// ── POST (multipart) — subir foto o documento ──────────────────────────────────
if ($method === 'POST' && $accion === 'subir_foto') {
    $empleado_id = (int)($_POST['empleado_id'] ?? 0);
    if (!$empleado_id) { jsonResponse(['error' => 'empleado_id requerido']); exit; }

    $r = rhGuardarArchivo($_FILES['archivo'] ?? null);
    if (isset($r['error'])) { jsonResponse($r); exit; }

    $anterior = $pdo->prepare("SELECT foto FROM nomina_empleados WHERE id = ?");
    $anterior->execute([$empleado_id]);
    $fotoAnterior = $anterior->fetchColumn();

    $pdo->prepare("UPDATE nomina_empleados SET foto = ? WHERE id = ?")
        ->execute([$r['nombre_servidor'], $empleado_id]);

    if ($fotoAnterior) {
        $rutaAnterior = __DIR__ . '/../../archivos_rh/' . basename($fotoAnterior);
        if (file_exists($rutaAnterior)) unlink($rutaAnterior);
    }

    jsonResponse(['ok' => true, 'nombre_servidor' => $r['nombre_servidor']]); exit;
}

if ($method === 'POST' && $accion === 'subir_documento') {
    $empleado_id    = (int)($_POST['empleado_id'] ?? 0);
    $tipo_documento = trim($_POST['tipo_documento'] ?? '');
    $notas          = trim($_POST['notas'] ?? '');
    if (!$empleado_id)    { jsonResponse(['error' => 'empleado_id requerido']); exit; }
    if (!$tipo_documento) { jsonResponse(['error' => 'Tipo de documento requerido']); exit; }

    $r = rhGuardarArchivo($_FILES['archivo'] ?? null);
    if (isset($r['error'])) { jsonResponse($r); exit; }

    $pdo->prepare("
        INSERT INTO rh_documentos (empleado_id, tipo_documento, nombre_original, nombre_servidor, notas, subido_por)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$empleado_id, $tipo_documento, $r['nombre_original'], $r['nombre_servidor'], $notas ?: null, $nombre]);

    jsonResponse(['ok' => true, 'id' => $pdo->lastInsertId()]); exit;
}

// ── El resto llega como JSON ────────────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST' && $accion === 'borrar_documento') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }

    $stmt = $pdo->prepare("SELECT nombre_servidor FROM rh_documentos WHERE id = ?");
    $stmt->execute([$id]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc) { jsonResponse(['error' => 'No encontrado']); exit; }

    // No permitir borrar un documento que sea evidencia de una incidencia registrada
    $enUso = $pdo->prepare("SELECT COUNT(*) FROM rh_incidencias WHERE documento_id = ?");
    $enUso->execute([$id]);
    if ($enUso->fetchColumn() > 0) {
        jsonResponse(['error' => 'Este documento está ligado a una incidencia, no se puede borrar']); exit;
    }

    $ruta = __DIR__ . '/../../archivos_rh/' . basename($doc['nombre_servidor']);
    if (file_exists($ruta)) unlink($ruta);
    $pdo->prepare("DELETE FROM rh_documentos WHERE id = ?")->execute([$id]);

    jsonResponse(['ok' => true]); exit;
}

if ($method === 'POST' && $accion === 'vacaciones_registrar') {
    $periodo_id = (int)($body['periodo_id'] ?? 0);
    $fecha_inicio = trim($body['fecha_inicio'] ?? '');
    $fecha_fin    = trim($body['fecha_fin'] ?? '');
    $dias         = (int)($body['dias'] ?? 0);
    $tipo         = $body['tipo'] ?? 'tomado';
    $notas        = trim($body['notas'] ?? '');

    if (!$periodo_id || !$fecha_inicio || !$fecha_fin || $dias < 1) {
        jsonResponse(['error' => 'Datos incompletos']); exit;
    }
    if (!in_array($tipo, ['tomado', 'pagado_sin_ausencia'])) {
        jsonResponse(['error' => 'Tipo inválido']); exit;
    }

    $periodo = $pdo->prepare("SELECT * FROM rh_vacaciones_periodos WHERE id = ?");
    $periodo->execute([$periodo_id]);
    $periodo = $periodo->fetch(PDO::FETCH_ASSOC);
    if (!$periodo) { jsonResponse(['error' => 'Periodo no encontrado']); exit; }

    $saldo = $periodo['dias_derecho'] - $periodo['dias_tomados'] - $periodo['dias_pagados_sin_ausencia'];
    if ($dias > $saldo) {
        jsonResponse(['error' => "Solo tiene $saldo día(s) disponibles en este periodo"]); exit;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO rh_vacaciones_detalle (periodo_id, fecha_inicio, fecha_fin, dias, tipo, notas, registrado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([$periodo_id, $fecha_inicio, $fecha_fin, $dias, $tipo, $notas ?: null, $nombre]);

        $campo = $tipo === 'tomado' ? 'dias_tomados' : 'dias_pagados_sin_ausencia';
        $pdo->prepare("UPDATE rh_vacaciones_periodos SET $campo = $campo + ? WHERE id = ?")
            ->execute([$dias, $periodo_id]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['error' => 'Error al registrar']); exit;
    }

    jsonResponse(['ok' => true]); exit;
}

if ($method === 'POST' && $accion === 'incidencia_crear') {
    $empleado_id  = (int)($body['empleado_id'] ?? 0);
    $tipo         = $body['tipo'] ?? '';
    $fecha_inicio = trim($body['fecha_inicio'] ?? '');
    $fecha_fin    = trim($body['fecha_fin'] ?? '');
    $goce_sueldo  = !empty($body['goce_sueldo']) ? 1 : 0;
    $documento_id = !empty($body['documento_id']) ? (int)$body['documento_id'] : null;
    $notas        = trim($body['notas'] ?? '');

    $tipos_validos = ['incapacidad_enfermedad', 'incapacidad_maternidad', 'incapacidad_paternidad',
        'riesgo_trabajo', 'fallecimiento_familiar', 'permiso_personal_con_goce',
        'permiso_personal_sin_goce', 'otro'];

    if (!$empleado_id || !in_array($tipo, $tipos_validos) || !$fecha_inicio || !$fecha_fin) {
        jsonResponse(['error' => 'Datos incompletos o inválidos']); exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
        jsonResponse(['error' => 'Formato de fecha inválido']); exit;
    }

    $inicio = new DateTime($fecha_inicio);
    $fin    = new DateTime($fecha_fin);
    if ($fin < $inicio) { jsonResponse(['error' => 'La fecha fin no puede ser antes del inicio']); exit; }
    $dias = $inicio->diff($fin)->days + 1;

    $pdo->prepare("
        INSERT INTO rh_incidencias (empleado_id, tipo, fecha_inicio, fecha_fin, dias, goce_sueldo, documento_id, notas, registrado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([$empleado_id, $tipo, $fecha_inicio, $fecha_fin, $dias, $goce_sueldo, $documento_id, $notas ?: null, $nombre]);

    jsonResponse(['ok' => true, 'id' => $pdo->lastInsertId(), 'dias' => $dias]); exit;
}

if ($method === 'POST' && $accion === 'incidencia_borrar') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) { jsonResponse(['error' => 'ID requerido']); exit; }
    $pdo->prepare("DELETE FROM rh_incidencias WHERE id = ?")->execute([$id]);
    jsonResponse(['ok' => true]); exit;
}

jsonResponse(['error' => 'Acción no soportada'], 400);
