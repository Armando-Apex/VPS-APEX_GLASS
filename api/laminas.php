<?php
// ============================================================
//  APEX GLASS - API: Láminas (catálogo)
//  GET    ?accion=lista|detalle&id=X|stock
//  POST   accion=crear
//  PUT    accion=actualizar&id=X
//  DELETE accion=eliminar&id=X
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
$user = requirePermiso('ver_inventario');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? 'lista';
$id     = (int)($_GET['id'] ?? 0);

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {

    if ($accion === 'detalle' && $id) {
        $s = $db->prepare("SELECT * FROM laminas WHERE id = ?");
        $s->execute([$id]);
        jsonResponse($s->fetch() ?: ['error' => 'No encontrado']);
    }

    if ($accion === 'stock') {
        // Stock actual + costo ponderado + m² requeridos en produccion
        $s = $db->query("
            SELECT
                l.id,
                l.tipo,
                l.espesor_mm,
                l.ancho_mm,
                l.alto_mm,
                l.m2,
                l.notas,
                -- Láminas compradas total
                COALESCE(c.total_compradas, 0)         AS total_compradas,
                -- Láminas usadas total
                COALESCE(m.total_usadas, 0)            AS total_usadas,
                -- Stock actual en láminas
                COALESCE(c.total_compradas, 0)
                  - COALESCE(m.total_usadas, 0)        AS stock_laminas,
                -- Stock en m²
                (COALESCE(c.total_compradas, 0)
                  - COALESCE(m.total_usadas, 0))
                  * l.m2                               AS stock_m2,
                -- Costo promedio ponderado por lámina
                COALESCE(c.costo_ponderado, 0)         AS costo_prom_lamina,
                -- Costo por m²
                CASE WHEN l.m2 > 0
                     THEN COALESCE(c.costo_ponderado, 0) / l.m2
                     ELSE 0 END                        AS costo_prom_m2,
                -- m² requeridos en produccion (piezas pendiente + en_corte)
                COALESCE(req.m2_requeridos, 0)         AS m2_requeridos
            FROM laminas l
            LEFT JOIN (
                SELECT
                    lamina_id,
                    SUM(cantidad_laminas)  AS total_compradas,
                    -- Costo ponderado = suma(costo_real * cantidad) / total
                    SUM(costo_real_unitario * cantidad_laminas)
                      / NULLIF(SUM(cantidad_laminas), 0) AS costo_ponderado
                FROM inventario_compras
                GROUP BY lamina_id
            ) c ON c.lamina_id = l.id
            LEFT JOIN (
                SELECT lamina_id, SUM(cantidad_laminas) AS total_usadas
                FROM inventario_movimientos
                GROUP BY lamina_id
            ) m ON m.lamina_id = l.id
            LEFT JOIN (
                -- m² requeridos: pendiente de implementar cuando cristales tenga tipo_vidrio y espesor_mm
                SELECT NULL AS lamina_id, 0 AS m2_requeridos FROM dual WHERE 1=0
            ) req ON req.lamina_id = l.id
            WHERE l.activo = 1
            ORDER BY l.tipo ASC, l.espesor_mm ASC, l.ancho_mm DESC
        ");
        $laminas = $s->fetchAll();

        // Marcar alerta: stock_m2 <= m2_requeridos * 1.20
        foreach ($laminas as &$l) {
            $l['alerta_stock'] = (
                $l['m2_requeridos'] > 0 &&
                $l['stock_m2'] <= $l['m2_requeridos'] * 1.20
            ) ? 1 : 0;
            $l['pct_stock'] = $l['m2_requeridos'] > 0
                ? round($l['stock_m2'] / $l['m2_requeridos'] * 100)
                : null;
        }
        jsonResponse(['laminas' => $laminas]);
    }

    // lista simple
    $s = $db->query("SELECT id, tipo, espesor_mm, ancho_mm, alto_mm, m2, notas, activo
                     FROM laminas WHERE activo=1
                     ORDER BY tipo ASC, espesor_mm ASC, ancho_mm DESC");
    jsonResponse(['laminas' => $s->fetchAll()]);
}

// ── POST / PUT ───────────────────────────────────────────────
if ($method === 'POST' || $method === 'PUT') {
    requirePermiso('gestionar_inventario');
    $body     = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion   = $body['accion'] ?? $accion;
    $id       = (int)($body['id'] ?? $id);
    $tipo     = $body['tipo']       ?? '';
    $espesor  = (float)($body['espesor_mm'] ?? 0);
    $ancho    = (int)($body['ancho_mm']   ?? 0);
    $alto     = (int)($body['alto_mm']    ?? 0);
    $notas    = trim($body['notas'] ?? '');
    $activo   = isset($body['activo']) ? (int)$body['activo'] : 1;

    if (!$tipo || !$espesor || !$ancho || !$alto)
        jsonResponse(['error' => 'Tipo, espesor y dimensiones requeridos'], 422);

    if ($accion === 'crear') {
        try {
            $s = $db->prepare("INSERT INTO laminas
                (tipo, espesor_mm, ancho_mm, alto_mm, notas, activo)
                VALUES (?,?,?,?,?,?)");
            $s->execute([$tipo, $espesor, $ancho, $alto, $notas, $activo]);
            jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000)
                jsonResponse(['error' => 'Ya existe una lámina con ese tipo, espesor y dimensión'], 422);
            throw $e;
        }
    }

    if ($accion === 'actualizar' && $id) {
        $s = $db->prepare("UPDATE laminas SET
            tipo=?, espesor_mm=?, ancho_mm=?, alto_mm=?, notas=?, activo=?
            WHERE id=?");
        $s->execute([$tipo, $espesor, $ancho, $alto, $notas, $activo, $id]);
        jsonResponse(['ok' => true]);
    }
}

// ── DELETE ───────────────────────────────────────────────────
if ($method === 'DELETE') {
    requirePermiso('gestionar_inventario');
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $id   = (int)($body['id'] ?? $id);
    if (!$id) jsonResponse(['error' => 'ID requerido'], 422);
    $s = $db->prepare("UPDATE laminas SET activo=0 WHERE id=?");
    $s->execute([$id]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['error' => 'Método no soportado'], 405);