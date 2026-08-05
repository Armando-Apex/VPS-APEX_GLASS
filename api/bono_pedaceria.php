<?php
// ============================================================
//  APEX GLASS - API: Bono de Corte por uso de Pedacería
//  Método: GET/POST ?accion=mi_bono|resumen_semana|marcar_pagado
//
//  Aislado del resto del sistema (ver CLAUDE.md, módulos WIP): solo LEE
//  sesiones_corte (nunca la modifica) y escribe únicamente en su propia
//  tabla bono_pedaceria_pagos. No toca sesion_corte.php, laminas,
//  inventario_movimientos ni ninguna tabla de producción.
//
//  Reglas de negocio (ver plan / mockup validado con Armando y Mando):
//  - Elegible para el bono: sesiones_corte con es_pedaceria=1,
//    m2_disponible <= 2.5 (tope silencioso, nunca expuesto a Angel) y
//    created_at dentro de la semana Y >= BONO_PEDACERIA_INICIO (sin
//    retroactividad — el programa arranca desde esa fecha, no antes).
//  - Monto elegible por sesión = m2_aprovechado (lo que sí se convirtió
//    en piezas, no el sobrante completo declarado).
//  - Fórmula por tramos de 18 m² = $150: 0–18 m² proporcional
//    (rampa de arranque); de ahí en adelante $150 por cada tramo de
//    18 m² YA COMPLETO — lo que sobra de un tramo a medias no paga nada
//    hasta completarlo entero.
// ============================================================
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

// Sin retroactividad: cualquier sesión de pedacería anterior a esta fecha no
// cuenta para nada, sin importar si hubiera cumplido el tope de 2.5 m².
const BONO_PEDACERIA_INICIO = '2026-08-03';
const BONO_TOPE_M2          = 2.5;
const BONO_TRAMO_M2         = 18.0;
const BONO_TRAMO_MONTO      = 150.0;

function bpCalcularMonto($m2) {
    if ($m2 < BONO_TRAMO_M2) {
        return round(($m2 / BONO_TRAMO_M2) * BONO_TRAMO_MONTO, 2);
    }
    return floor($m2 / BONO_TRAMO_M2) * BONO_TRAMO_MONTO;
}

// Lunes–Domingo de la semana que contiene $fecha (formato Y-m-d)
function bpSemana($fecha) {
    $d = new DateTime($fecha);
    $dow = (int)$d->format('N'); // 1=lunes .. 7=domingo
    $inicio = (clone $d)->modify('-' . ($dow - 1) . ' days');
    $fin    = (clone $inicio)->modify('+6 days');
    return [$inicio->format('Y-m-d'), $fin->format('Y-m-d')];
}

$db = getDB();

// Mismo bug real encontrado y corregido en api/bitacora_desechos.php (05-ago-2026):
// $_POST solo se llena con bodies application/x-www-form-urlencoded o multipart — un
// fetch con Content-Type: application/json (como el que manda marcar_pagado) deja
// $_POST vacío, así que $accion nunca coincidía con nada y siempre caía en "Acción no
// reconocida". Se lee el body UNA vez aquí y se reutiliza (php://input no siempre se
// puede releer más de una vez).
$bodyInput = ($_SERVER['REQUEST_METHOD'] === 'POST' && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false)
    ? (json_decode(file_get_contents('php://input'), true) ?: [])
    : [];
$accion = $_GET['accion'] ?? ($_POST['accion'] ?? ($bodyInput['accion'] ?? ''));

// ============================================================
//  mi_bono — progreso de la semana en curso del operador logueado.
//  NUNCA expone sesiones excluidas ni el valor del tope (BONO_TOPE_M2).
// ============================================================
if ($accion === 'mi_bono') {
    $user = requireSessionApi();
    if ($user['rol'] !== 'operador') {
        jsonResponse(['ok' => false, 'error' => 'Solo disponible para operadores']);
        exit;
    }

    [$semanaInicio, $semanaFin] = bpSemana(date('Y-m-d'));
    $desde = max($semanaInicio, BONO_PEDACERIA_INICIO);

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(m2_aprovechado), 0) AS m2_elegible
        FROM sesiones_corte
        WHERE operador_id = ?
          AND es_pedaceria = 1
          AND m2_disponible <= ?
          AND created_at >= ?
          AND created_at <= ?
    ");
    $stmt->execute([$user['id'], BONO_TOPE_M2, $desde . ' 00:00:00', $semanaFin . ' 23:59:59']);
    $m2Elegible = round((float)$stmt->fetchColumn(), 4);

    jsonResponse([
        'ok'            => true,
        'semana_inicio' => $semanaInicio,
        'semana_fin'    => $semanaFin,
        'm2_elegible'   => $m2Elegible,
        'monto'         => bpCalcularMonto($m2Elegible),
    ]);
    exit;
}

// ============================================================
//  resumen_semana — revisión para Armando/Mando: por operador, m2
//  elegible/monto + detalle de sesiones excluidas por el tope (aquí SÍ
//  se muestra, es para quien revisa).
// ============================================================
if ($accion === 'resumen_semana') {
    requirePermisoApi('ver_contabilidad');

    [$semanaInicio, $semanaFin] = bpSemana($_GET['semana'] ?? date('Y-m-d'));
    $desde = max($semanaInicio, BONO_PEDACERIA_INICIO);

    $stmt = $db->prepare("
        SELECT sc.operador_id, u.nombre AS operador_nombre,
               COUNT(*) AS sesiones,
               COALESCE(SUM(sc.m2_aprovechado), 0) AS m2_elegible
        FROM sesiones_corte sc
        JOIN usuarios u ON u.id = sc.operador_id
        WHERE sc.es_pedaceria = 1
          AND sc.m2_disponible <= ?
          AND sc.created_at >= ?
          AND sc.created_at <= ?
        GROUP BY sc.operador_id, u.nombre
        ORDER BY u.nombre
    ");
    $stmt->execute([BONO_TOPE_M2, $desde . ' 00:00:00', $semanaFin . ' 23:59:59']);
    $operadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtExcl = $db->prepare("
        SELECT id, tipo, espesor_mm, m2_disponible, created_at
        FROM sesiones_corte
        WHERE operador_id = ?
          AND es_pedaceria = 1
          AND m2_disponible > ?
          AND created_at >= ?
          AND created_at <= ?
        ORDER BY created_at
    ");

    // Detalle de seguimiento: cada sesión ELEGIBLE (la que sí cuenta para el bono)
    // con las piezas reales que salieron de ahí — orden/partida/pieza/medidas —
    // para que Armando/Mando puedan ver qué se hizo con cada pedazo de sobrante,
    // no solo el total en m². Solo lectura, no cambia el cálculo del bono.
    $stmtSesiones = $db->prepare("
        SELECT id, tipo, espesor_mm, m2_disponible, m2_aprovechado, created_at
        FROM sesiones_corte
        WHERE operador_id = ?
          AND es_pedaceria = 1
          AND m2_disponible <= ?
          AND created_at >= ?
          AND created_at <= ?
        ORDER BY created_at
    ");
    $stmtPiezas = $db->prepare("
        SELECT p.partida, p.pieza_num, p.pieza_total, p.ancho_mm, p.alto_mm, o.folio
        FROM sesiones_corte_piezas scp
        JOIN piezas p ON p.id = scp.pieza_id
        JOIN ordenes o ON o.id = p.orden_id
        WHERE scp.sesion_id = ? AND scp.incluida = 1
        ORDER BY o.folio, p.partida, p.pieza_num
    ");

    $stmtPago = $db->prepare("SELECT estado, aprobado_por, aprobado_at FROM bono_pedaceria_pagos WHERE operador_id = ? AND semana_inicio = ?");

    foreach ($operadores as &$op) {
        $op['m2_elegible'] = round((float)$op['m2_elegible'], 4);
        $op['monto']       = bpCalcularMonto($op['m2_elegible']);

        $stmtExcl->execute([$op['operador_id'], BONO_TOPE_M2, $desde . ' 00:00:00', $semanaFin . ' 23:59:59']);
        $op['excluidas'] = $stmtExcl->fetchAll(PDO::FETCH_ASSOC);

        $stmtSesiones->execute([$op['operador_id'], BONO_TOPE_M2, $desde . ' 00:00:00', $semanaFin . ' 23:59:59']);
        $sesiones = $stmtSesiones->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sesiones as &$s) {
            $stmtPiezas->execute([$s['id']]);
            $s['piezas'] = $stmtPiezas->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($s);
        // Ojo: NO usar la clave 'sesiones' aquí — ya existe como el COUNT(*) de la
        // query principal (número de sesiones elegibles), usado en la tarjeta resumen.
        $op['sesiones_detalle'] = $sesiones;

        $stmtPago->execute([$op['operador_id'], $semanaInicio]);
        $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);
        $op['estado']      = $pago['estado'] ?? 'calculado';
        $op['aprobado_por']= $pago['aprobado_por'] ?? null;
        $op['aprobado_at'] = $pago['aprobado_at'] ?? null;
    }
    unset($op);

    jsonResponse([
        'ok'            => true,
        'semana_inicio' => $semanaInicio,
        'semana_fin'    => $semanaFin,
        'operadores'    => $operadores,
    ]);
    exit;
}

// ============================================================
//  marcar_pagado — único checkpoint humano, un clic por semana (no una
//  aprobación por sesión). Recalcula todo server-side, nunca confía en
//  lo que mande el cliente.
// ============================================================
if ($accion === 'marcar_pagado') {
    $user = requirePermisoApi('gestionar_contabilidad');

    $body        = $bodyInput;
    $operador_id = (int)($body['operador_id'] ?? 0);
    $semanaRef   = trim($body['semana_inicio'] ?? '');
    if (!$operador_id || !$semanaRef) {
        jsonResponse(['ok' => false, 'error' => 'Datos incompletos']);
        exit;
    }

    [$semanaInicio, $semanaFin] = bpSemana($semanaRef);
    $desde = max($semanaInicio, BONO_PEDACERIA_INICIO);

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(m2_aprovechado), 0) AS m2_elegible
        FROM sesiones_corte
        WHERE operador_id = ?
          AND es_pedaceria = 1
          AND m2_disponible <= ?
          AND created_at >= ?
          AND created_at <= ?
    ");
    $stmt->execute([$operador_id, BONO_TOPE_M2, $desde . ' 00:00:00', $semanaFin . ' 23:59:59']);
    $m2Elegible = round((float)$stmt->fetchColumn(), 4);
    $monto      = bpCalcularMonto($m2Elegible);

    $db->prepare("
        INSERT INTO bono_pedaceria_pagos
            (operador_id, semana_inicio, semana_fin, m2_elegible, monto, estado, aprobado_por, aprobado_at)
        VALUES (?, ?, ?, ?, ?, 'pagado', ?, NOW())
        ON DUPLICATE KEY UPDATE
            m2_elegible = VALUES(m2_elegible), monto = VALUES(monto),
            estado = 'pagado', aprobado_por = VALUES(aprobado_por), aprobado_at = NOW()
    ")->execute([$operador_id, $semanaInicio, $semanaFin, $m2Elegible, $monto, $user['id']]);

    jsonResponse(['ok' => true, 'monto' => $monto, 'm2_elegible' => $m2Elegible]);
    exit;
}

jsonResponse(['ok' => false, 'error' => 'Acción no reconocida']);
