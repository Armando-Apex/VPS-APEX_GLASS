<?php
// ============================================================
//  APEX GLASS - API: Sesión de Corte (consumo de láminas)
//  Archivo: api/sesion_corte.php
//  GET  ?accion=espesores_disponibles&tipo=X
//  GET  ?accion=piezas_lote&qr_codes[]=...
//  GET  ?accion=laminas_disponibles&tipo=X&espesor_mm=Y
//  GET  ?accion=sesion_abierta
//  POST ?accion=guardar_sesion_abierta
//  POST ?accion=cancelar_sesion_abierta
//  POST ?accion=confirmar_sesion
//
//  Flujo: el operador de Corte elige primero de qué lámina (catálogo real
//  o pedacería) va a cortar, y LUEGO escanea las piezas que va sacando de
//  ella. Al terminar, se descuenta 1 lámina real del inventario (o se
//  registra el uso de pedacería) y se calcula el % de efectividad por m²
//  (piezas que sí salieron / m² de la lámina o pedacería usada).
//
//  Sesión abierta (tabla sesion_corte_abierta, 1 fila por operador): se
//  guarda en servidor desde que elige lámina hasta que confirma/cancela,
//  para que recargar la página no pierda el bloqueo de "una sesión a la
//  vez" ni el avance de piezas ya escaneadas.
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
require_once 'helpers/cristal_parser.php';

$usuario = requireSessionApi();

// Acceso: operador de piso en su propia estación, o roles con acceso total
// (jefe_piso/dirección) — mismo criterio que la omisión de estación.
$tieneAcceso = tienePermiso($usuario['rol'], 'escanear_estacion_propia')
            || tienePermiso($usuario['rol'], 'cambiar_cualquier_estatus');
if (!$tieneAcceso) {
    jsonResponse(['error' => 'Sin permiso para registrar consumo de corte'], 403);
}

header('Content-Type: application/json; charset=utf-8');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? '';

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {

    if ($accion === 'piezas_lote') {
        $qrCodes = $_GET['qr_codes'] ?? [];
        if (!is_array($qrCodes) || !count($qrCodes)) {
            jsonResponse(['piezas' => []]);
        }
        $placeholders = implode(',', array_fill(0, count($qrCodes), '?'));
        $s = $db->prepare("
            SELECT p.id, p.qr_code, p.orden_id, p.partida, p.pieza_num, p.pieza_total,
                   p.cristal, p.cristal_corto, p.ancho_mm, p.alto_mm, p.m2, p.estatus,
                   o.folio, o.cliente_nombre
            FROM piezas p
            JOIN ordenes o ON o.id = p.orden_id
            WHERE p.qr_code IN ($placeholders)
        ");
        $s->execute($qrCodes);
        $piezas = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($piezas as &$p) {
            $parsed = parsearCristalATipoEspesor($p['cristal']);
            $p['tipo_detectado']    = $parsed['tipo']       ?? null;
            $p['espesor_detectado'] = $parsed['espesor_mm'] ?? null;
        }
        unset($p);
        jsonResponse(['piezas' => $piezas]);
    }

    // Tipos y espesores REALES tomados del catálogo de Cristales (cristales.nombre_etiqueta),
    // no solo del ENUM fijo de `laminas` — así aparecen también tipos que aún no tienen
    // lámina dada de alta en inventario (ej. Bronce, Ultra Claro): el paso siguiente
    // (laminas_disponibles) simplemente saldrá vacío para esos y se ofrece pedacería.
    if ($accion === 'catalogo_tipos_mm') {
        $s = $db->query("SELECT DISTINCT nombre_etiqueta, nombre FROM cristales WHERE activo = 1");
        $filas = $s->fetchAll(PDO::FETCH_ASSOC);

        $tipos = []; // label => ['enum' => ..., 'espesores' => [...]]
        foreach ($filas as $fila) {
            $et = $fila['nombre_etiqueta'];

            if (preg_match('/^(.*?)\s*(\d+(\.\d+)?)\s*mm$/i', $et, $m)) {
                // Caso normal: la etiqueta ya trae "Tipo NNmm".
                $label   = $m[1];
                $espesor = (float)$m[2];
            } else {
                // La etiqueta no trae espesor (ej. "EVO 50") — se usa la etiqueta
                // tal cual como label, y el espesor se saca del nombre completo.
                if (!preg_match('/(\d+(\.\d+)?)\s*mm/i', $fila['nombre'], $m2)) continue;
                $label   = $et;
                $espesor = (float)$m2[1];
            }

            $label = preg_replace('/\s+de$/i', '', trim($label)); // normaliza "Claro de" -> "Claro"
            if ($label === '') continue;
            $parsed = parsearCristalATipoEspesor($label . ' ' . $espesor . 'mm');

            if (!isset($tipos[$label])) {
                $tipos[$label] = ['label' => $label, 'enum' => $parsed['tipo'] ?? null, 'espesores' => []];
            }
            if (!in_array($espesor, $tipos[$label]['espesores'], true)) {
                $tipos[$label]['espesores'][] = $espesor;
            }
        }

        $tipos = array_values($tipos);
        foreach ($tipos as &$t) { sort($t['espesores']); }
        unset($t);
        usort($tipos, function ($a, $b) { return strcmp($a['label'], $b['label']); });

        jsonResponse(['tipos' => $tipos]);
    }

    if ($accion === 'laminas_disponibles') {
        $tipo    = trim($_GET['tipo'] ?? '');
        $espesor = (float)($_GET['espesor_mm'] ?? 0);
        if (!$espesor) jsonResponse(['error' => 'espesor_mm requerido'], 422);
        // Tipo vacío = este producto de Cristales no tiene equivalente en el ENUM
        // de `laminas` (ej. Bronce, Ultra Claro) — no hay catálogo, se ofrece pedacería.
        if (!$tipo) jsonResponse(['laminas' => []]);
        $s = $db->prepare("
            SELECT l.id, l.ancho_mm, l.alto_mm, l.m2,
                   COALESCE(c.total_compradas,0) - COALESCE(m.total_usadas,0) AS stock_laminas
            FROM laminas l
            LEFT JOIN (SELECT lamina_id, SUM(cantidad_laminas) total_compradas
                       FROM inventario_compras GROUP BY lamina_id) c ON c.lamina_id = l.id
            LEFT JOIN (SELECT lamina_id, SUM(cantidad_laminas) total_usadas
                       FROM inventario_movimientos GROUP BY lamina_id) m ON m.lamina_id = l.id
            WHERE l.activo = 1 AND l.tipo = ? AND l.espesor_mm = ?
            HAVING stock_laminas > 0
            ORDER BY l.m2 ASC
        ");
        $s->execute([$tipo, $espesor]);
        jsonResponse(['laminas' => $s->fetchAll(PDO::FETCH_ASSOC)]);
    }

    if ($accion === 'sesion_abierta') {
        $s = $db->prepare("SELECT * FROM sesion_corte_abierta WHERE operador_id = ?");
        $s->execute([$usuario['id']]);
        $fila = $s->fetch(PDO::FETCH_ASSOC);
        if (!$fila) jsonResponse(['sesion' => null]);
        $fila['piezas'] = json_decode($fila['piezas'], true) ?: [];
        jsonResponse(['sesion' => $fila]);
    }

    jsonResponse(['error' => 'Acción no válida'], 400);
}

// ── POST ─────────────────────────────────────────────────────
if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($accion === 'guardar_sesion_abierta') {
        $tipoLabel = trim($body['tipo'] ?? '');
        $espesor   = (float)($body['espesor_mm'] ?? 0);
        if (!$tipoLabel || !$espesor) jsonResponse(['error' => 'tipo y espesor_mm requeridos'], 422);

        $db->prepare("
            INSERT INTO sesion_corte_abierta
                (operador_id, tipo, tipo_enum, espesor_mm, es_pedaceria, lamina_id, ancho_mm, alto_mm, piezas)
            VALUES (?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                tipo=VALUES(tipo), tipo_enum=VALUES(tipo_enum), espesor_mm=VALUES(espesor_mm),
                es_pedaceria=VALUES(es_pedaceria), lamina_id=VALUES(lamina_id),
                ancho_mm=VALUES(ancho_mm), alto_mm=VALUES(alto_mm), piezas=VALUES(piezas)
        ")->execute([
            $usuario['id'],
            $tipoLabel,
            trim($body['tipo_enum'] ?? '') ?: null,
            $espesor,
            !empty($body['es_pedaceria']) ? 1 : 0,
            (int)($body['lamina_id'] ?? 0) ?: null,
            (int)($body['ancho_mm'] ?? 0) ?: null,
            (int)($body['alto_mm'] ?? 0) ?: null,
            json_encode($body['piezas'] ?? [], JSON_UNESCAPED_UNICODE),
        ]);
        jsonResponse(['ok' => true]);
    }

    if ($accion === 'cancelar_sesion_abierta') {
        $db->prepare("DELETE FROM sesion_corte_abierta WHERE operador_id = ?")->execute([$usuario['id']]);
        jsonResponse(['ok' => true]);
    }

    if ($accion === 'confirmar_sesion') {
        $esPedaceria = !empty($body['es_pedaceria']);
        // tipoLabel: nombre real tal como aparece en Cristales (ej. "Bronce", "Claro
        // Zafiro") — es lo que se guarda en sesiones_corte.tipo (varchar, catálogo
        // completo). tipoEnum: slug del ENUM de `laminas` (puede venir vacío si ese
        // tipo real no tiene lámina dada de alta) — SOLO se usa para validar la
        // lámina de catálogo elegida, nunca se guarda tal cual si viene vacío.
        $tipoLabel   = trim($body['tipo'] ?? '');
        $tipoEnum    = trim($body['tipo_enum'] ?? '');
        $espesor     = (float)($body['espesor_mm'] ?? 0);
        $laminaId    = (int)($body['lamina_id'] ?? 0);
        $anchoBody   = (int)($body['ancho_mm'] ?? 0);
        $altoBody    = (int)($body['alto_mm'] ?? 0);
        $piezas      = $body['piezas'] ?? [];
        $removidas   = $body['piezas_removidas'] ?? [];
        $notas       = trim($body['notas'] ?? '');

        if (!$tipoLabel || !$espesor) {
            jsonResponse(['error' => 'tipo y espesor_mm requeridos'], 422);
        }
        if (!is_array($piezas) || !count($piezas)) {
            jsonResponse(['error' => 'Debe incluir al menos una pieza'], 422);
        }
        if (!is_array($removidas)) $removidas = [];

        $anchoMm = $anchoBody;
        $altoMm  = $altoBody;
        $m2Disponible = null;

        if (!$esPedaceria) {
            if (!$laminaId) jsonResponse(['error' => 'lamina_id requerido para catálogo'], 422);

            // Recalcular stock server-side — nunca confiar en el cliente.
            $s = $db->prepare("
                SELECT l.ancho_mm, l.alto_mm, l.m2, l.tipo, l.espesor_mm,
                       COALESCE(c.total_compradas,0) - COALESCE(m.total_usadas,0) AS stock_laminas
                FROM laminas l
                LEFT JOIN (SELECT lamina_id, SUM(cantidad_laminas) total_compradas
                           FROM inventario_compras GROUP BY lamina_id) c ON c.lamina_id = l.id
                LEFT JOIN (SELECT lamina_id, SUM(cantidad_laminas) total_usadas
                           FROM inventario_movimientos GROUP BY lamina_id) m ON m.lamina_id = l.id
                WHERE l.id = ? AND l.activo = 1
            ");
            $s->execute([$laminaId]);
            $lam = $s->fetch(PDO::FETCH_ASSOC);
            if (!$lam) jsonResponse(['error' => 'Lámina no encontrada'], 404);
            if ((int)$lam['stock_laminas'] < 1) {
                jsonResponse(['error' => 'Sin stock disponible de esta lámina'], 422);
            }
            if (!$tipoEnum || $lam['tipo'] !== $tipoEnum || (float)$lam['espesor_mm'] !== $espesor) {
                jsonResponse(['error' => 'La lámina no coincide con el tipo/espesor solicitado'], 422);
            }
            $anchoMm = (int)$lam['ancho_mm'];
            $altoMm  = (int)$lam['alto_mm'];
            $m2Disponible = (float)$lam['m2'];
        } else {
            if (!$anchoMm || !$altoMm) {
                jsonResponse(['error' => 'ancho_mm y alto_mm requeridos para pedacería'], 422);
            }
            $m2Disponible = round(($anchoMm * $altoMm) / 1000000, 4);
        }

        $piezaIds = array_map(function ($p) { return (int)($p['pieza_id'] ?? 0); }, $piezas);
        $piezaIds = array_filter($piezaIds);
        if (!count($piezaIds)) jsonResponse(['error' => 'piezas inválidas'], 422);

        $removidaIds = array_map(function ($p) { return (int)($p['pieza_id'] ?? 0); }, $removidas);
        $removidaIds = array_filter($removidaIds);

        $db->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($piezaIds), '?'));
            $s = $db->prepare("
                SELECT p.id, p.qr_code, p.estatus, p.m2, p.cristal, o.folio, o.estado AS orden_estado
                FROM piezas p
                JOIN ordenes o ON o.id = p.orden_id
                WHERE p.id IN ($placeholders)
                FOR UPDATE
            ");
            $s->execute($piezaIds);
            $piezasDb = $s->fetchAll(PDO::FETCH_ASSOC);

            // El escaneo dentro de la sesión de corte es el primer toque de la pieza —
            // puede venir directo de 'pendiente' (flujo nuevo) o de 'en_corte' (piezas
            // que ya estaban registradas en CNC antes de este cambio de flujo).
            // Defensa en profundidad: aunque el frontend ya filtra, aquí se vuelve a
            // exigir que el tipo/espesor de la pieza coincida con lo declarado para la
            // sesión — nunca se descuenta una lámina/pedacería de un tipo por piezas de otro.
            $piezasValidas = [];
            $piezasTipoIncorrecto = 0;
            $folios = [];
            foreach ($piezasDb as $p) {
                if (!in_array($p['estatus'], ['pendiente', 'en_corte'], true)) continue;
                if ($p['orden_estado'] !== 'activa') continue;
                $parsedPieza = parsearCristalLabelEspesor($p['cristal']);
                if ($parsedPieza && !cristalCoincideConSesion($p['cristal'], $tipoLabel, $espesor)) {
                    $piezasTipoIncorrecto++;
                    continue;
                }
                $piezasValidas[] = $p;
                $folios[$p['folio']] = true;
            }
            if ($piezasTipoIncorrecto > 0 && !count($piezasValidas)) {
                throw new Exception($piezasTipoIncorrecto . ' pieza(s) son de otro tipo/espesor de cristal (sesión: ' . $tipoLabel . ' ' . $espesor . 'mm)');
            }
            if (!count($piezasValidas)) {
                throw new Exception('Ninguna pieza sigue válida para marcar como cortada');
            }

            $nombreUsuario = $usuario['nombre'];
            $upd  = $db->prepare("UPDATE piezas SET estatus='cortado', updated_at=NOW() WHERE id=?");
            $histEnCorte = $db->prepare("
                INSERT INTO historial_estatus
                    (pieza_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, notas, omision)
                VALUES (?,'pendiente','en_corte',?,?,?,0)
            ");
            $histCortado = $db->prepare("
                INSERT INTO historial_estatus
                    (pieza_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, notas, omision)
                VALUES (?,'en_corte','cortado',?,?,?,0)
            ");
            $m2Aprovechado = 0.0;
            $insDetalle = $db->prepare("
                INSERT INTO sesiones_corte_piezas (sesion_id, pieza_id, m2_pieza, incluida)
                VALUES (?,?,?,?)
            ");

            $movimientoId = null;
            if (!$esPedaceria) {
                $ins = $db->prepare("INSERT INTO inventario_movimientos
                    (lamina_id, cantidad_laminas, ordenes, operador_id, fecha, notas)
                    VALUES (?,1,?,?,CURDATE(),?)");
                $ins->execute([
                    $laminaId,
                    json_encode(array_keys($folios), JSON_UNESCAPED_UNICODE),
                    $usuario['id'],
                    $notas !== '' ? $notas : 'Consumo real registrado desde wizard de corte',
                ]);
                $movimientoId = $db->lastInsertId();
            }

            $insSesion = $db->prepare("
                INSERT INTO sesiones_corte
                    (es_pedaceria, lamina_id, tipo, espesor_mm, ancho_mm, alto_mm,
                     m2_disponible, m2_aprovechado, efectividad_pct, operador_id,
                     movimiento_id, notas)
                VALUES (?,?,?,?,?,?,?,0,0,?,?,?)
            ");
            $insSesion->execute([
                $esPedaceria ? 1 : 0,
                $esPedaceria ? null : $laminaId,
                $tipoLabel, $espesor, $anchoMm, $altoMm,
                $m2Disponible,
                $usuario['id'],
                $movimientoId,
                $notas,
            ]);
            $sesionId = $db->lastInsertId();

            foreach ($piezasValidas as $p) {
                if ($p['estatus'] === 'pendiente') {
                    $histEnCorte->execute([$p['id'], $usuario['id'], $nombreUsuario, $notas]);
                }
                $upd->execute([$p['id']]);
                $histCortado->execute([$p['id'], $usuario['id'], $nombreUsuario, $notas]);
                $insDetalle->execute([$sesionId, $p['id'], $p['m2'], 1]);
                $m2Aprovechado += (float)$p['m2'];
            }

            foreach ($removidaIds as $rid) {
                $s2 = $db->prepare("SELECT m2 FROM piezas WHERE id=?");
                $s2->execute([$rid]);
                $m2r = (float)($s2->fetchColumn() ?: 0);
                $insDetalle->execute([$sesionId, $rid, $m2r, 0]);
            }

            $efectividad = $m2Disponible > 0 ? round($m2Aprovechado / $m2Disponible * 100, 2) : 0;
            $db->prepare("UPDATE sesiones_corte SET m2_aprovechado=?, efectividad_pct=? WHERE id=?")
               ->execute([$m2Aprovechado, $efectividad, $sesionId]);

            $db->prepare("DELETE FROM sesion_corte_abierta WHERE operador_id = ?")->execute([$usuario['id']]);

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            jsonResponse(['error' => 'No se pudo confirmar la sesión: ' . $e->getMessage()], 409);
        }

        jsonResponse([
            'ok' => true,
            'sesion_id' => $sesionId,
            'efectividad_pct' => $efectividad,
            'piezas_cortadas' => count($piezasValidas),
            'piezas_tipo_incorrecto' => $piezasTipoIncorrecto,
        ]);
    }

    jsonResponse(['error' => 'Acción no válida'], 400);
}

jsonResponse(['error' => 'Método no soportado'], 405);
