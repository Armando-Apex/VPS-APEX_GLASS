<?php
// ============================================================
//  APEX GLASS - API: Inventario (compras y movimientos)
//  GET    ?accion=compras|movimientos|compras_lamina&lamina_id=X
//  POST   accion=registrar_compra|registrar_uso
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
$user = requirePermiso('ver_inventario');

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$accion = $_GET['accion'] ?? 'compras';

// ── GET ──────────────────────────────────────────────────────
if ($method === 'GET') {

    if ($accion === 'compras') {
        $lamina_id = (int)($_GET['lamina_id'] ?? 0);
        $where  = $lamina_id ? "WHERE ic.lamina_id = ?" : '';
        $params = $lamina_id ? [$lamina_id] : [];
        $s = $db->prepare("
            SELECT
                ic.id,
                ic.fecha_compra,
                ic.cantidad_laminas,
                ic.precio_unitario,
                ic.flete_tipo,
                ic.costo_flete_total,
                ic.costo_real_unitario,
                ic.referencia,
                ic.notas,
                ic.created_at,
                l.tipo           AS lamina_tipo,
                l.espesor_mm     AS lamina_espesor,
                l.ancho_mm       AS lamina_ancho,
                l.alto_mm        AS lamina_alto,
                l.m2             AS lamina_m2,
                p.nombre         AS proveedor_nombre,
                u.nombre         AS registrado_por
            FROM inventario_compras ic
            JOIN laminas l    ON l.id = ic.lamina_id
            JOIN proveedores p ON p.id = ic.proveedor_id
            LEFT JOIN usuarios u ON u.id = ic.created_by
            $where
            ORDER BY ic.fecha_compra DESC, ic.created_at DESC
            LIMIT 200
        ");
        $s->execute($params);
        jsonResponse(['compras' => $s->fetchAll()]);
    }

    if ($accion === 'movimientos') {
        $lamina_id = (int)($_GET['lamina_id'] ?? 0);
        $where  = $lamina_id ? "WHERE im.lamina_id = ?" : '';
        $params = $lamina_id ? [$lamina_id] : [];
        $s = $db->prepare("
            SELECT
                im.id,
                im.fecha,
                im.cantidad_laminas,
                im.ordenes,
                im.notas,
                im.created_at,
                l.tipo       AS lamina_tipo,
                l.espesor_mm AS lamina_espesor,
                l.ancho_mm   AS lamina_ancho,
                l.alto_mm    AS lamina_alto,
                u.nombre     AS operador_nombre,
                (SELECT GROUP_CONCAT(
                            CONCAT(o.folio, ' P', p.partida, ' · pieza ', p.pieza_num, '/', p.pieza_total)
                            ORDER BY o.folio, p.partida, p.pieza_num
                            SEPARATOR ', ')
                   FROM sesiones_corte sc
                   JOIN sesiones_corte_piezas scp ON scp.sesion_id = sc.id AND scp.incluida = 1
                   JOIN piezas p  ON p.id = scp.pieza_id
                   JOIN ordenes o ON o.id = p.orden_id
                  WHERE sc.movimiento_id = im.id
                ) AS piezas_detalle
            FROM inventario_movimientos im
            JOIN laminas l ON l.id = im.lamina_id
            LEFT JOIN usuarios u ON u.id = im.operador_id
            $where
            ORDER BY im.fecha DESC, im.created_at DESC
            LIMIT 200
        ");
        $s->execute($params);
        jsonResponse(['movimientos' => $s->fetchAll()]);
    }

    if ($accion === 'costo_promedio') {
        // Costo promedio ponderado por lamina (tipo + espesor + dimensiones)
        $s = $db->query("
            SELECT
                ic.lamina_id,
                l.tipo,
                l.espesor_mm,
                l.ancho_mm,
                l.alto_mm,
                l.m2,
                SUM(ic.cantidad_laminas) AS total_compradas,
                COALESCE((SELECT SUM(m.cantidad_laminas) FROM inventario_movimientos m WHERE m.lamina_id = ic.lamina_id), 0) AS total_usadas,
                SUM(ic.cantidad_laminas) - COALESCE((SELECT SUM(m.cantidad_laminas) FROM inventario_movimientos m WHERE m.lamina_id = ic.lamina_id), 0) AS en_stock,
                SUM(ic.cantidad_laminas * COALESCE(ic.costo_real_unitario, ic.precio_unitario)) / SUM(ic.cantidad_laminas) AS costo_prom_lamina,
                SUM(ic.cantidad_laminas * COALESCE(ic.costo_real_unitario, ic.precio_unitario)) / (SUM(ic.cantidad_laminas) * l.m2) AS costo_prom_m2,
                MIN(COALESCE(ic.costo_real_unitario, ic.precio_unitario)) AS precio_min,
                MAX(COALESCE(ic.costo_real_unitario, ic.precio_unitario)) AS precio_max,
                MIN(COALESCE(ic.costo_real_unitario, ic.precio_unitario)) / l.m2 AS precio_min_m2,
                MAX(COALESCE(ic.costo_real_unitario, ic.precio_unitario)) / l.m2 AS precio_max_m2
            FROM inventario_compras ic
            JOIN laminas l ON l.id = ic.lamina_id
            GROUP BY ic.lamina_id, l.tipo, l.espesor_mm, l.ancho_mm, l.alto_mm, l.m2
            HAVING en_stock > 0
            ORDER BY l.tipo, l.espesor_mm, l.ancho_mm, l.alto_mm
        ");
        $por_lamina = $s->fetchAll();

        // Agregar por tipo + espesor en PHP
        $grupos = [];
        foreach ($por_lamina as $row) {
            $key = $row['tipo'] . '|' . $row['espesor_mm'];
            if (!isset($grupos[$key])) {
                $grupos[$key] = [
                    'tipo' => $row['tipo'], 'espesor_mm' => $row['espesor_mm'],
                    'en_stock' => 0, 'sum_valor' => 0.0, 'sum_m2' => 0.0,
                    'precio_min_m2' => null, 'precio_max_m2' => null,
                ];
            }
            $grupos[$key]['en_stock']  += $row['en_stock'];
            $grupos[$key]['sum_valor'] += $row['en_stock'] * $row['costo_prom_lamina'];
            $grupos[$key]['sum_m2']    += $row['en_stock'] * $row['m2'];
            // Normalizar min/max a $/m² para comparar entre dimensiones distintas
            $m2 = (float)$row['m2'];
            if ($m2 > 0) {
                $min_m2 = (float)$row['precio_min'] / $m2;
                $max_m2 = (float)$row['precio_max'] / $m2;
                $grupos[$key]['precio_min_m2'] = $grupos[$key]['precio_min_m2'] === null
                    ? $min_m2 : min($grupos[$key]['precio_min_m2'], $min_m2);
                $grupos[$key]['precio_max_m2'] = $grupos[$key]['precio_max_m2'] === null
                    ? $max_m2 : max($grupos[$key]['precio_max_m2'], $max_m2);
            }
        }
        $por_tipo = [];
        foreach ($grupos as $g) {
            $por_tipo[] = [
                'tipo'          => $g['tipo'],
                'espesor_mm'    => $g['espesor_mm'],
                'en_stock'      => $g['en_stock'],
                'costo_prom_m2' => $g['sum_m2'] > 0 ? round($g['sum_valor'] / $g['sum_m2'], 4) : null,
                'valor_total'   => round($g['sum_valor'], 2),
                'precio_min_m2' => $g['precio_min_m2'] !== null ? round($g['precio_min_m2'], 4) : null,
                'precio_max_m2' => $g['precio_max_m2'] !== null ? round($g['precio_max_m2'], 4) : null,
                'prom_lamina'   => $g['en_stock'] > 0 ? round($g['sum_valor'] / $g['en_stock'], 2) : null,
            ];
        }
        usort($por_tipo, function($a, $b) { return strcmp($a['tipo'] . $a['espesor_mm'], $b['tipo'] . $b['espesor_mm']); });

        $sc = $db->query("SELECT id, nombre, nombre_etiqueta, precio_m2 FROM cristales WHERE activo = 1 ORDER BY nombre ASC");
        $cristales = $sc->fetchAll();

        // Precio real ponderado por m2 vendido (reemplaza el precio de catalogo x0.90 usado antes en
        // Rentabilidad por m2 de vidrio). A peticion de Armando (10-jul-2026): los costos de cristal
        // subieron fuerte en los ultimos dias, quiere ver el precio REAL cobrado, no una suposicion.
        // Ventana fija desde que empezaron los cambios de precio, no un rango movil.
        $FECHA_INICIO_PRECIO_REAL = '2026-07-01 00:00:00';
        $tipoLbl = [
            'claro' => 'Claro', 'claro_zafiro' => 'Claro Zafiro', 'filtrasol' => 'Filtrasol',
            'espejo' => 'Espejo', 'espejo_aluminio' => 'Espejo Aluminio', 'laminado_claro' => 'Laminado Claro',
            'reflecta' => 'Reflecta', 'satinado' => 'Satinado', 'tintex' => 'Tintex',
        ];
        $normalizar = function($s) { return preg_replace('/\s+/', '', mb_strtolower($s)); };

        $sv = $db->prepare("
            SELECT cp.cristal_id,
                   SUM(cp.precio_m2_usado * cp.m2 * cp.cantidad * (1 - COALESCE(c.descuento,0)/100)) AS ingreso_neto,
                   SUM(cp.m2 * cp.cantidad) AS m2_total
            FROM cotizaciones_partidas cp
            JOIN cotizaciones c ON c.id = cp.cotizacion_id
            JOIN ordenes o ON o.id = c.orden_id
            WHERE cp.cristal_id IS NOT NULL
              AND o.estado IN ('activa','entregada')
              AND c.vobo_at >= ? AND c.vobo_at <= NOW()
            GROUP BY cp.cristal_id
        ");
        $sv->execute([$FECHA_INICIO_PRECIO_REAL]);
        $ventasPorCristal = [];
        foreach ($sv->fetchAll() as $v) {
            $ventasPorCristal[(int)$v['cristal_id']] = ['ingreso' => (float)$v['ingreso_neto'], 'm2' => (float)$v['m2_total']];
        }

        // Agrupa variantes del mismo tipo+espesor (Express, Con Esmerilado, etc.) con el mismo
        // criterio que el ranking del sorteo (UPD-299): mismo cristal base, distinto acabado/servicio.
        // Excluye por diseno Plantilla/Zafiro/Ultra Claro (nombre no arranca con el mismo prefijo).
        foreach ($por_tipo as &$g) {
            $base = $normalizar(($tipoLbl[$g['tipo']] ?? $g['tipo']) . (int)$g['espesor_mm'] . 'mm');
            $ingreso = 0.0; $m2v = 0.0;
            foreach ($cristales as $c) {
                $nombreNorm = $normalizar($c['nombre']);
                $sufijo = substr($nombreNorm, strlen($base), 1);
                if (strpos($nombreNorm, $base) !== 0 || ($sufijo !== '' && $sufijo !== '-')) continue;
                $cid = (int)$c['id'];
                if (isset($ventasPorCristal[$cid])) {
                    $ingreso += $ventasPorCristal[$cid]['ingreso'];
                    $m2v     += $ventasPorCristal[$cid]['m2'];
                }
            }
            $g['precio_venta_real'] = $m2v > 0 ? round($ingreso / $m2v, 4) : null;
            $g['m2_vendidos_real']  = round($m2v, 2);
        }
        unset($g);

        jsonResponse(['por_lamina' => $por_lamina, 'por_tipo' => $por_tipo, 'cristales' => $cristales, 'precio_real_desde' => $FECHA_INICIO_PRECIO_REAL]);
    }

    jsonResponse(['error' => 'Accion no valida'], 400);
}

// ── POST ─────────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = $body['accion'] ?? '';

    // ── Registrar compra ──────────────────────────────────────
    if ($accion === 'registrar_compra') {
        requirePermiso('gestionar_inventario');

        $lamina_id   = (int)($body['lamina_id']        ?? 0);
        $proveedor_id= (int)($body['proveedor_id']     ?? 0);
        $fecha       = $body['fecha_compra']            ?? date('Y-m-d');
        $cantidad    = (int)($body['cantidad_laminas']  ?? 0);
        $precio      = (float)($body['precio_unitario'] ?? 0);
        $flete_tipo  = $body['flete_tipo']              ?? 'incluido';
        $costo_flete = (float)($body['costo_flete_total'] ?? 0);
        $referencia  = trim($body['referencia']         ?? '');
        $notas       = trim($body['notas']              ?? '');

        if (!$lamina_id || !$proveedor_id || !$cantidad || !$precio)
            jsonResponse(['error' => 'Faltan campos requeridos'], 422);

        $s = $db->prepare("INSERT INTO inventario_compras
            (lamina_id, proveedor_id, fecha_compra, cantidad_laminas,
             precio_unitario, flete_tipo, costo_flete_total,
             referencia, notas, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $s->execute([
            $lamina_id, $proveedor_id, $fecha, $cantidad,
            $precio, $flete_tipo, $costo_flete,
            $referencia, $notas, $user['id']
        ]);
        jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
    }

    // ── Registrar uso (operador corte) ────────────────────────
    if ($accion === 'registrar_uso') {
        // operador de corte puede registrar uso
        $lamina_id = (int)($body['lamina_id']       ?? 0);
        $cantidad  = (int)($body['cantidad_laminas'] ?? 0);
        $ordenes   = $body['ordenes']                ?? [];  // array de folios
        $fecha     = $body['fecha']                  ?? date('Y-m-d');
        $notas     = trim($body['notas']             ?? '');

        if (!$lamina_id || !$cantidad)
            jsonResponse(['error' => 'Faltan campos requeridos'], 422);

        if (!is_array($ordenes))
            jsonResponse(['error' => 'ordenes debe ser un array de folios'], 422);

        // Verificar que hay stock suficiente
        $s = $db->prepare("
            SELECT
                COALESCE(SUM(c.cantidad_laminas),0) -
                COALESCE((SELECT SUM(m.cantidad_laminas)
                          FROM inventario_movimientos m
                          WHERE m.lamina_id = ?), 0) AS stock
            FROM inventario_compras c
            WHERE c.lamina_id = ?
        ");
        $s->execute([$lamina_id, $lamina_id]);
        $stock = (int)$s->fetchColumn();

        if ($stock < $cantidad)
            jsonResponse([
                'error' => 'Stock insuficiente. Disponible: '.$stock.' láminas'
            ], 422);

        $s = $db->prepare("INSERT INTO inventario_movimientos
            (lamina_id, cantidad_laminas, ordenes, operador_id, fecha, notas)
            VALUES (?,?,?,?,?,?)");
        $s->execute([
            $lamina_id, $cantidad,
            json_encode($ordenes, JSON_UNESCAPED_UNICODE),
            $user['id'], $fecha, $notas
        ]);
        jsonResponse(['ok' => true, 'id' => $db->lastInsertId()]);
    }

    jsonResponse(['error' => 'Accion no valida'], 400);
}

jsonResponse(['error' => 'Método no soportado'], 405);