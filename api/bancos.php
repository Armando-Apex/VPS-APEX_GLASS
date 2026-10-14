<?php
// ============================================================
//  APEX GLASS - Bancos (UPD-697) — Fase 1: registro idéntico al estado de cuenta
//  Archivo: api/bancos.php
// ============================================================
// Cada movimiento del banco se guarda tal cual (fecha, concepto, detalle, cargo, abono,
// saldo) con su orden real (secuencia). Se alimenta subiendo el PDF "Detalle de
// movimientos" de BBVA las veces que se quiera: lo ya registrado no se duplica y lo
// nuevo SOLO entra si encadena al centavo con el último saldo registrado.
//
// GET  ?accion=cuentas
// GET  ?accion=movimientos&cuenta_id=&desde=&hasta=&tipo=&q=
// GET  ?accion=importaciones&cuenta_id=
// POST ?accion=previsualizar   (multipart: cuenta_id, pdf)  -> resumen + token
// POST ?accion=confirmar       (JSON: token)                -> registra lo nuevo
// UPD-702 conciliación de abonos:
// GET  ?accion=conciliacion&movimiento_id=&q=   -> ligas + sugerencias
// GET  ?accion=apex_sin_banco&cuenta_id=&desde=&hasta= -> pagos/depósitos de Apex sin ligar
// GET  ?accion=auto_propuesta&cuenta_id=&desde=&hasta= -> pares 1 a 1 seguros (no escribe)
// POST ?accion=ligar           (JSON: movimiento_id, items[])
// POST ?accion=desligar        (JSON: id)
// POST ?accion=auto_confirmar  (JSON: pares[{movimiento_id, origen, origen_id}])
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/helpers/bancos_lib.php';
require_once __DIR__ . '/helpers/bancos_conciliacion_lib.php';

header('Content-Type: application/json');

$user = requireSessionApi();
$rol  = $user['rol'];
if (!tienePermiso($rol, 'ver_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}
$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

define('BANCOS_DIR', '/home/apexglass2025/archivos_bancos');

// ─── Helpers ──────────────────────────────────────────────────────────────────
function bancosCuenta($db, $id, $bloquear = false) {
    $s = $db->prepare("SELECT * FROM banco_cuentas WHERE id = ? AND activo = 1" . ($bloquear ? ' FOR UPDATE' : ''));
    $s->execute([(int)$id]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

// ─── GET ──────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($accion === 'cuentas') {
        $rows = $db->query("SELECT * FROM banco_cuentas WHERE activo = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$c) {
            $st = $db->prepare("SELECT fecha, saldo FROM banco_movimientos WHERE cuenta_id = ? ORDER BY secuencia DESC LIMIT 1");
            $st->execute([$c['id']]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            $c['saldo_actual']     = $u ? (float)$u['saldo'] : (float)$c['saldo_inicial'];
            $c['ultima_fecha']     = $u ? $u['fecha'] : null;
            $c['cadena']           = bancosVerificarCadena($db, $c);
            $st = $db->prepare("SELECT fecha_impresion, saldo_disponible, created_at, usuario FROM banco_importaciones WHERE cuenta_id = ? ORDER BY id DESC LIMIT 1");
            $st->execute([$c['id']]);
            $c['ultima_importacion'] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        unset($c);
        jsonResponse(['cuentas' => $rows]);
    }

    if ($accion === 'movimientos') {
        $cuenta = bancosCuenta($db, $_GET['cuenta_id'] ?? 0);
        if (!$cuenta) jsonResponse(['error' => 'Cuenta no encontrada'], 404);
        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-01');
        $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');
        $tipo  = $_GET['tipo'] ?? '';
        $q     = trim($_GET['q'] ?? '');
        $conc  = $_GET['conc'] ?? '';

        // Saldo al iniciar el rango (independiente de los filtros de tipo/búsqueda).
        $st = $db->prepare("SELECT saldo FROM banco_movimientos WHERE cuenta_id = ? AND fecha < ? ORDER BY secuencia DESC LIMIT 1");
        $st->execute([$cuenta['id'], $desde]);
        $saldoIni = $st->fetchColumn();
        $saldoIni = ($saldoIni === false) ? (float)$cuenta['saldo_inicial'] : (float)$saldoIni;
        $st = $db->prepare("SELECT saldo FROM banco_movimientos WHERE cuenta_id = ? AND fecha <= ? ORDER BY secuencia DESC LIMIT 1");
        $st->execute([$cuenta['id'], $hasta]);
        $saldoFin = $st->fetchColumn();
        $saldoFin = ($saldoFin === false) ? $saldoIni : (float)$saldoFin;

        $where = "cuenta_id = ? AND fecha BETWEEN ? AND ?";
        $params = [$cuenta['id'], $desde, $hasta];
        if ($tipo === 'cargos')      { $where .= " AND cargo > 0"; }
        elseif ($tipo === 'abonos')  { $where .= " AND abono > 0"; }
        elseif ($tipo !== '' && preg_match('/^[a-z_]{1,30}$/', $tipo)) { $where .= " AND tipo = ?"; $params[] = $tipo; }
        if ($q !== '') {
            $where .= " AND (concepto LIKE ? OR detalle LIKE ? OR CAST(cargo AS CHAR) LIKE ? OR CAST(abono AS CHAR) LIKE ?)";
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $ligadoSql = "(SELECT COALESCE(SUM(bc.monto), 0) FROM banco_conciliacion bc WHERE bc.movimiento_id = banco_movimientos.id)";
        if ($conc === 'pendientes')      { $where .= " AND abono > 0 AND $ligadoSql < abono - 0.005"; }
        elseif ($conc === 'conciliados') { $where .= " AND abono > 0 AND $ligadoSql >= abono - 0.005"; }
        $st = $db->prepare("SELECT id, secuencia, fecha, concepto, detalle, tipo, cargo, abono, saldo, $ligadoSql AS ligado,
                                   (SELECT GROUP_CONCAT(DISTINCT COALESCE(o.folio, c.folio, cl.codigo, bc.categoria) ORDER BY bc.id SEPARATOR ', ')
                                    FROM banco_conciliacion bc
                                    LEFT JOIN cotizacion_pagos p ON bc.origen = 'pago' AND p.id = bc.origen_id
                                    LEFT JOIN clientes_saldo_favor sf ON bc.origen = 'saldo_favor' AND sf.id = bc.origen_id
                                    LEFT JOIN cotizaciones c ON c.id = COALESCE(p.cotizacion_id, sf.cotizacion_id)
                                    LEFT JOIN ordenes o ON o.id = c.orden_id
                                    LEFT JOIN clientes cl ON cl.id = sf.cliente_id
                                    WHERE bc.movimiento_id = banco_movimientos.id) AS ligado_a
                            FROM banco_movimientos WHERE $where ORDER BY secuencia DESC");
        $st->execute($params);
        $filas = $st->fetchAll(PDO::FETCH_ASSOC);
        $tc = 0; $ta = 0;
        foreach ($filas as $f) { $tc += (float)$f['cargo']; $ta += (float)$f['abono']; }

        // Avance de conciliación del rango (sin filtros de tipo/búsqueda).
        $st = $db->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(abono), 0) AS total,
                                   SUM($ligadoSql >= abono - 0.005) AS n_ok,
                                   COALESCE(SUM(LEAST(abono, $ligadoSql)), 0) AS total_ligado
                            FROM banco_movimientos WHERE cuenta_id = ? AND fecha BETWEEN ? AND ? AND abono > 0");
        $st->execute([$cuenta['id'], $desde, $hasta]);
        $avance = $st->fetch(PDO::FETCH_ASSOC);

        jsonResponse([
            'desde' => $desde, 'hasta' => $hasta,
            'saldo_inicial' => $saldoIni, 'saldo_final' => $saldoFin,
            'total_cargos' => round($tc, 2), 'total_abonos' => round($ta, 2),
            'conciliacion' => [
                'abonos' => (int)$avance['n'], 'conciliados' => (int)$avance['n_ok'],
                'total' => round((float)$avance['total'], 2), 'ligado' => round((float)$avance['total_ligado'], 2),
            ],
            'filas' => $filas,
        ]);
    }

    if ($accion === 'conciliacion') {
        $mov = concMovimiento($db, $_GET['movimiento_id'] ?? 0);
        if (!$mov || !bancosCuenta($db, $mov['cuenta_id'])) jsonResponse(['error' => 'Movimiento no encontrado'], 404);
        $ligado = concTotalLigado($db, $mov['id']);
        $restante = round((float)$mov['abono'] - $ligado, 2);
        jsonResponse([
            'movimiento'  => $mov,
            'ligado'      => round($ligado, 2),
            'restante'    => $restante,
            'ligas'       => concLigas($db, $mov['id']),
            'sugerencias' => (float)$mov['abono'] > 0 ? concSugerencias($db, $mov, $restante, mb_substr(trim($_GET['q'] ?? ''), 0, 60)) : [],
            'categorias'  => CONC_CATEGORIAS,
        ]);
    }

    if ($accion === 'apex_sin_banco') {
        $cuenta = bancosCuenta($db, $_GET['cuenta_id'] ?? 0);
        if (!$cuenta) jsonResponse(['error' => 'Cuenta no encontrada'], 404);
        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-01');
        $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');
        if ($desde < $cuenta['fecha_inicial']) $desde = $cuenta['fecha_inicial'];
        $st = $db->prepare("SELECT r.* FROM " . concRegistrosSql(true) . " WHERE r.fecha BETWEEN ? AND ? ORDER BY r.forma, r.fecha, r.origen_id");
        $st->execute([$desde, $hasta]);
        jsonResponse(['desde' => $desde, 'hasta' => $hasta, 'registros' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($accion === 'auto_propuesta') {
        $cuenta = bancosCuenta($db, $_GET['cuenta_id'] ?? 0);
        if (!$cuenta) jsonResponse(['error' => 'Cuenta no encontrada'], 404);
        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-01');
        $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');
        jsonResponse(['pares' => concPropuestaAuto($db, $cuenta['id'], $desde, $hasta)]);
    }

    if ($accion === 'importaciones') {
        $st = $db->prepare("SELECT id, archivo_nombre, fecha_impresion, saldo_disponible, filas_archivo, filas_nuevas,
                                   filas_existentes, filas_previas, primera_fecha, ultima_fecha, saldo_final, usuario, created_at
                            FROM banco_importaciones WHERE cuenta_id = ? ORDER BY id DESC LIMIT 50");
        $st->execute([(int)($_GET['cuenta_id'] ?? 0)]);
        jsonResponse(['importaciones' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    jsonResponse(['error' => 'Acción no válida'], 400);
}

// ─── Escritura: solo quien gestiona contabilidad ───────────────────────────────
if ($method !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);
if (!tienePermiso($rol, 'gestionar_contabilidad')) {
    jsonResponse(['error' => 'Sin permiso'], 403);
}

if ($accion === 'previsualizar') {
    $cuenta = bancosCuenta($db, $_POST['cuenta_id'] ?? 0);
    if (!$cuenta) jsonResponse(['error' => 'Cuenta no encontrada'], 404);
    if (empty($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'No se recibió el archivo'], 422);
    }
    $file = $_FILES['pdf'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf' || $mime !== 'application/pdf') {
        jsonResponse(['error' => 'Solo se aceptan archivos PDF'], 422);
    }
    if ($file['size'] > 15 * 1024 * 1024) {
        jsonResponse(['error' => 'Archivo demasiado grande (máx. 15 MB)'], 422);
    }

    // Limpiar pendientes viejos (más de 1 día).
    foreach (glob(BANCOS_DIR . '/pendientes/*.pdf') ?: [] as $viejo) {
        if (filemtime($viejo) < time() - 86400) @unlink($viejo);
    }
    $token = bin2hex(random_bytes(16));
    $ruta  = BANCOS_DIR . '/pendientes/' . $token . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], $ruta)) {
        jsonResponse(['error' => 'No se pudo guardar el archivo'], 500);
    }

    try {
        $parsed = bbvaParsearTexto(bbvaPdfATexto($ruta));
        $plan   = bancosPlanImportacion($db, $cuenta, $parsed);
    } catch (Exception $e) {
        @unlink($ruta);
        jsonResponse(['error' => $e->getMessage()], 422);
    }

    $_SESSION['bancos_import'][$token] = [
        'cuenta_id' => (int)$cuenta['id'],
        'nombre'    => mb_substr(basename($file['name']), 0, 200),
    ];

    $F = $parsed['filas'];
    jsonResponse([
        'ok'               => true,
        'token'            => $token,
        'cuenta'           => $parsed['cuenta'],
        'impreso'          => $parsed['impreso'],
        'saldo_disponible' => $parsed['saldo_disponible'],
        'filas_archivo'    => count($F),
        'primera_fecha'    => $F[0]['fecha'],
        'ultima_fecha'     => $F[count($F) - 1]['fecha'],
        'previas'          => $plan['previas'],
        'existentes'       => $plan['existentes'],
        'nuevas'           => $plan['nuevas'],
        'saldo_final'      => $plan['saldo_final'],
        'impreso_hoy'      => substr($parsed['impreso'], 0, 10) === date('Y-m-d'),
    ]);
}

if ($accion === 'confirmar') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = (string)($body['token'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $token) || empty($_SESSION['bancos_import'][$token])) {
        jsonResponse(['error' => 'La vista previa expiró; vuelve a subir el PDF'], 422);
    }
    $info = $_SESSION['bancos_import'][$token];
    $ruta = BANCOS_DIR . '/pendientes/' . $token . '.pdf';
    if (!is_file($ruta)) jsonResponse(['error' => 'La vista previa expiró; vuelve a subir el PDF'], 422);

    $db->beginTransaction();
    try {
        // Bloquea la cuenta: dos importaciones al mismo tiempo no pueden pisarse.
        $cuenta = bancosCuenta($db, $info['cuenta_id'], true);
        if (!$cuenta) throw new Exception('Cuenta no encontrada');
        // Se recalcula todo contra la BD actual (no se confía en la vista previa).
        $parsed = bbvaParsearTexto(bbvaPdfATexto($ruta));
        $plan   = bancosPlanImportacion($db, $cuenta, $parsed);
        $destDir = BANCOS_DIR . '/' . $cuenta['numero'];
        if (!is_dir($destDir)) mkdir($destDir, 0750, true);
        $guardado = $destDir . '/' . date('Ymd_His') . '_' . substr($token, 0, 8) . '.pdf';

        $cadena = bancosRegistrar($db, $cuenta, $parsed, $plan, $info['nombre'], $guardado, $user['nombre']);

        if (!rename($ruta, $guardado)) throw new Exception('No se pudo archivar el PDF');
        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(['error' => $e->getMessage()], 422);
    }
    unset($_SESSION['bancos_import'][$token]);
    jsonResponse(['ok' => true, 'nuevas' => count($plan['nuevas']), 'existentes' => $plan['existentes'], 'saldo_final' => $cadena['saldo']]);
}

if ($accion === 'ligar' || $accion === 'desligar' || $accion === 'auto_confirmar') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $db->beginTransaction();
    try {
        if ($accion === 'ligar') {
            $items = is_array($body['items'] ?? null) ? array_slice($body['items'], 0, 50) : [];
            $total = concLigar($db, (int)($body['movimiento_id'] ?? 0), $items, $user['nombre']);
            $db->commit();
            jsonResponse(['ok' => true, 'ligado' => $total]);
        }
        if ($accion === 'desligar') {
            $st = $db->prepare("SELECT bc.id FROM banco_conciliacion bc
                                JOIN banco_movimientos m ON m.id = bc.movimiento_id
                                JOIN banco_cuentas c ON c.id = m.cuenta_id AND c.activo = 1
                                WHERE bc.id = ? FOR UPDATE");
            $st->execute([(int)($body['id'] ?? 0)]);
            $ligaId = $st->fetchColumn();
            if ($ligaId === false) throw new Exception('La liga ya no existe');
            $db->prepare("DELETE FROM banco_conciliacion WHERE id = ?")->execute([(int)$ligaId]);
            $db->commit();
            jsonResponse(['ok' => true]);
        }
        // auto_confirmar: cada par se vuelve a validar (monto exacto, sin ligar) al registrar.
        $pares = is_array($body['pares'] ?? null) ? array_slice($body['pares'], 0, 500) : [];
        $n = 0;
        foreach ($pares as $p) {
            $movId = (int)($p['movimiento_id'] ?? 0);
            $mov = concMovimiento($db, $movId, true);
            if (!$mov || concTotalLigado($db, $movId) > 0) continue;
            $origen = ($p['origen'] ?? '') === 'saldo_favor' ? 'saldo_favor' : 'pago';
            $st = $db->prepare("SELECT r.monto FROM " . concRegistrosSql(true) . " WHERE r.origen = ? AND r.origen_id = ?");
            $st->execute([$origen, (int)($p['origen_id'] ?? 0)]);
            $monto = $st->fetchColumn();
            if ($monto === false || abs((float)$monto - (float)$mov['abono']) >= 0.005) continue;
            concLigar($db, $movId, [['origen' => $origen, 'origen_id' => (int)$p['origen_id']]], $user['nombre'], 1);
            $n++;
        }
        $db->commit();
        jsonResponse(['ok' => true, 'ligados' => $n, 'omitidos' => count($pares) - $n]);
    } catch (Exception $e) {
        $db->rollBack();
        $msg = $e->getMessage();
        if ($e instanceof PDOException) { error_log('[bancos conciliacion] ' . $msg); $msg = 'Uno de los registros ya está ligado a otro movimiento del banco'; }
        jsonResponse(['error' => $msg], 422);
    }
}

jsonResponse(['error' => 'Acción no válida'], 400);
