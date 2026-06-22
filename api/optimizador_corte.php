<?php
// ============================================================
//  APEX GLASS - API: Optimizador de Corte v4
//  Archivo: /produccion/api/optimizador_corte.php
//
//  Algoritmo híbrido:
//  - ≤8 piezas candidatas por lámina: búsqueda por permutaciones
//  - >8 piezas: múltiples heurísticas + mejor resultado
//
//  Solo pedidos con 1-3 piezas PENDIENTES de corte
// ============================================================

require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

requireSessionApi();

$db = getDB();

$subPequenos = "
    SELECT orden_id FROM piezas
    WHERE estatus = 'pendiente'
    GROUP BY orden_id
    HAVING COUNT(*) BETWEEN 1 AND 4
";

// ============================================================
//  GET — Cristales disponibles
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->query("
        SELECT p.cristal,
               COUNT(*) as total_piezas,
               SUM(p.m2) as total_m2,
               COUNT(DISTINCT p.orden_id) as total_pedidos
        FROM piezas p
        JOIN ordenes o ON o.id = p.orden_id
        WHERE o.estado = 'activa'
          AND p.estatus = 'pendiente'
          AND p.cristal IS NOT NULL
          AND o.id IN ($subPequenos)
        GROUP BY p.cristal
        ORDER BY total_piezas DESC
    ");
    jsonResponse(['cristales' => $stmt->fetchAll()]);
}

// ============================================================
//  POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true);
    $accion = $body['accion'] ?? 'optimizar';

    // ── REGISTRAR CONSUMO ──────────────────────────────────
    if ($accion === 'registrar') {
        $cristal  = trim($body['cristal']      ?? '');
        $ancho    = (int)($body['ancho_lamina'] ?? 0);
        $alto     = (int)($body['alto_lamina']  ?? 0);
        $cantidad = (int)($body['cantidad']     ?? 1);
        $pedidos  = trim($body['pedidos']       ?? '');

        if (!$cristal || !$ancho || !$alto || !$cantidad)
            jsonResponse(['error' => 'Campos requeridos: cristal, ancho_lamina, alto_lamina, cantidad'], 400);

        $stmt = $db->prepare("
            INSERT INTO corte_laminas (fecha, cristal, ancho_mm, alto_mm, cantidad, pedidos, usuario_id)
            VALUES (CURDATE(), ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$cristal, $ancho, $alto, $cantidad, $pedidos, $_SESSION['usuario_id'] ?? null]);
        jsonResponse(['ok' => true, 'mensaje' => 'Consumo registrado correctamente']);
    }

    // ── OPTIMIZAR ──────────────────────────────────────────
    $cristal = trim($body['cristal']       ?? '');
    $anchoL  = (int)($body['ancho_lamina'] ?? 0);
    $altoL   = (int)($body['alto_lamina']  ?? 0);

    if (!$cristal || !$anchoL || !$altoL)
        jsonResponse(['error' => 'Campos requeridos: cristal, ancho_lamina, alto_lamina'], 400);

    // Obtener piezas
    $stmt = $db->prepare("
        SELECT p.id, p.ancho_mm, p.alto_mm, p.m2, p.cpb,
               p.partida, p.pieza_num, p.pieza_total,
               o.folio, o.cliente_nombre, o.fecha_entrega
        FROM piezas p
        JOIN ordenes o ON o.id = p.orden_id
        WHERE o.estado = 'activa'
          AND p.estatus = 'pendiente'
          AND p.cristal = ?
          AND o.id IN ($subPequenos)
        ORDER BY (p.ancho_mm * p.alto_mm) DESC
    ");
    $stmt->execute([$cristal]);
    $piezas = $stmt->fetchAll();

    if (empty($piezas))
        jsonResponse(['error' => 'No hay pedidos de 1 a 3 piezas pendientes de este cristal'], 404);

    $areaLamina = $anchoL * $altoL;

    // Ajustar por CPB (+8mm)
    $piezasAdj = [];
    foreach ($piezas as $p) {
        $cpb      = strtoupper(trim($p['cpb'] ?? ''));
        $tieneCPB = ($cpb !== '' && $cpb !== 'NO' && $cpb !== 'FM' && $cpb !== 'FILO MUERTO');
        $extra    = $tieneCPB ? 8 : 0;
        $w        = $p['ancho_mm'] + $extra;
        $h        = $p['alto_mm']  + $extra;

        if (($w > $anchoL || $h > $altoL) && ($h > $anchoL || $w > $altoL))
            continue;

        $piezasAdj[] = array_merge($p, ['w' => $w, 'h' => $h, 'tiene_cpb' => $tieneCPB]);
    }

    if (empty($piezasAdj))
        jsonResponse(['error' => 'Ninguna pieza cabe en las dimensiones indicadas'], 400);

    // ── Funciones del algoritmo MAXRECTS ──────────────────

    function intersecta(array $r1, array $r2): bool {
        return !($r1['x']+$r1['w'] <= $r2['x'] || $r2['x']+$r2['w'] <= $r1['x'] ||
                 $r1['y']+$r1['h'] <= $r2['y'] || $r2['y']+$r2['h'] <= $r1['y']);
    }

    function splitRect(array $libre, array $ocup): array {
        if (!intersecta($libre, $ocup)) return [$libre];
        $res = [];
        if ($ocup['x'] > $libre['x'])
            $res[] = ['x'=>$libre['x'],'y'=>$libre['y'],
                      'w'=>$ocup['x']-$libre['x'],'h'=>$libre['h']];
        if ($ocup['x']+$ocup['w'] < $libre['x']+$libre['w'])
            $res[] = ['x'=>$ocup['x']+$ocup['w'],'y'=>$libre['y'],
                      'w'=>$libre['x']+$libre['w']-$ocup['x']-$ocup['w'],'h'=>$libre['h']];
        if ($ocup['y'] > $libre['y'])
            $res[] = ['x'=>$libre['x'],'y'=>$libre['y'],
                      'w'=>$libre['w'],'h'=>$ocup['y']-$libre['y']];
        if ($ocup['y']+$ocup['h'] < $libre['y']+$libre['h'])
            $res[] = ['x'=>$libre['x'],'y'=>$ocup['y']+$ocup['h'],
                      'w'=>$libre['w'],'h'=>$libre['y']+$libre['h']-$ocup['y']-$ocup['h']];
        return $res;
    }

    function esContenido(array $r1, array $r2): bool {
        return ($r2['x']<=$r1['x'] && $r2['y']<=$r1['y'] &&
                $r2['x']+$r2['w']>=$r1['x']+$r1['w'] &&
                $r2['y']+$r2['h']>=$r1['y']+$r1['h']);
    }

    function purgar(array $libres): array {
        $res = [];
        foreach ($libres as $i => $r1) {
            $cont = false;
            foreach ($libres as $j => $r2) {
                if ($i !== $j && esContenido($r1, $r2)) { $cont = true; break; }
            }
            if (!$cont) $res[] = $r1;
        }
        return $res;
    }

    // Colocar piezas en un orden dado — devuelve [colocadas, area_usada]
    function colocarEnOrden(array $orden, int $lw, int $lh): array {
        $libres = [['x'=>0,'y'=>0,'w'=>$lw,'h'=>$lh]];
        $col    = [];

        foreach ($orden as $p) {
            $best = PHP_INT_MAX; $bi = -1; $brot = false;

            foreach ($libres as $ei => $esp) {
                if ($p['w'] <= $esp['w'] && $p['h'] <= $esp['h']) {
                    $s = min($esp['w']-$p['w'], $esp['h']-$p['h']);
                    if ($s < $best) { $best=$s; $bi=$ei; $brot=false; }
                }
                if ($p['h'] <= $esp['w'] && $p['w'] <= $esp['h']) {
                    $s = min($esp['w']-$p['h'], $esp['h']-$p['w']);
                    if ($s < $best) { $best=$s; $bi=$ei; $brot=true; }
                }
            }

            if ($bi < 0) continue;

            $esp  = $libres[$bi];
            $pw   = $brot ? $p['h'] : $p['w'];
            $ph   = $brot ? $p['w'] : $p['h'];
            $ocup = ['x'=>$esp['x'],'y'=>$esp['y'],'w'=>$pw,'h'=>$ph];

            $col[] = array_merge($p, ['pw'=>$pw,'ph'=>$ph,'rot'=>$brot]);

            $nuevos = [];
            foreach ($libres as $lib) $nuevos = array_merge($nuevos, splitRect($lib, $ocup));
            $libres = purgar($nuevos);
        }

        $area = array_sum(array_map(fn($c) => $c['pw']*$c['ph'], $col));
        return [$col, $area];
    }

    // Generar permutaciones en PHP
    function permutaciones(array $arr): array {
        if (count($arr) <= 1) return [$arr];
        $result = [];
        foreach ($arr as $i => $item) {
            $resto = array_values(array_filter($arr, fn($x,$k) => $k !== $i, ARRAY_FILTER_USE_BOTH));
            foreach (permutaciones($resto) as $perm) {
                $result[] = array_merge([$item], $perm);
            }
        }
        return $result;
    }

    // Heurísticas de ordenamiento para grupos grandes
    function heuristicas(array $piezas): array {
        $ordenes = [];

        // 1. Por área desc
        $tmp = $piezas;
        usort($tmp, fn($a,$b) => ($b['w']*$b['h']) - ($a['w']*$a['h']));
        $ordenes[] = $tmp;

        // 2. Por área asc
        $tmp = $piezas;
        usort($tmp, fn($a,$b) => ($a['w']*$a['h']) - ($b['w']*$b['h']));
        $ordenes[] = $tmp;

        // 3. Por altura desc
        $tmp = $piezas;
        usort($tmp, fn($a,$b) => $b['h'] - $a['h']);
        $ordenes[] = $tmp;

        // 4. Por ancho desc
        $tmp = $piezas;
        usort($tmp, fn($a,$b) => $b['w'] - $a['w']);
        $ordenes[] = $tmp;

        // 5. Por perímetro desc
        $tmp = $piezas;
        usort($tmp, fn($a,$b) => ($b['w']+$b['h']) - ($a['w']+$a['h']));
        $ordenes[] = $tmp;

        // 6. Alternado grande-pequeño
        $tmp = $piezas;
        usort($tmp, fn($a,$b) => ($b['w']*$b['h']) - ($a['w']*$a['h']));
        $alternado = [];
        $i = 0; $j = count($tmp)-1;
        $turno = true;
        while ($i <= $j) {
            if ($turno) $alternado[] = $tmp[$i++];
            else        $alternado[] = $tmp[$j--];
            $turno = !$turno;
        }
        $ordenes[] = $alternado;

        return $ordenes;
    }

    // ── Optimizar lámina: elige el mejor método según cantidad ──
    function optimizarLamina(array &$piezasDisp, int $lw, int $lh): array {
        $n = count($piezasDisp);

        if ($n <= 8) {
            // Permutaciones exhaustivas — garantiza óptimo local
            $mejorArea = -1;
            $mejorCol  = [];

            foreach (permutaciones($piezasDisp) as $perm) {
                [$col, $area] = colocarEnOrden($perm, $lw, $lh);
                if ($area > $mejorArea) {
                    $mejorArea = $area;
                    $mejorCol  = $col;
                }
            }
        } else {
            // Múltiples heurísticas — elige la mejor
            $mejorArea = -1;
            $mejorCol  = [];

            foreach (heuristicas($piezasDisp) as $orden) {
                [$col, $area] = colocarEnOrden($orden, $lw, $lh);
                if ($area > $mejorArea) {
                    $mejorArea = $area;
                    $mejorCol  = $col;
                }
            }

            // Shuffles aleatorios — mejora aprovechamiento en grupos grandes
            $tmp = $piezasDisp;
            for ($i = 0; $i < 30; $i++) {
                shuffle($tmp);
                [$col, $area] = colocarEnOrden($tmp, $lw, $lh);
                if ($area > $mejorArea) {
                    $mejorArea = $area;
                    $mejorCol  = $col;
                }
            }
        }

        // Remover colocadas del array disponible
        $idsCol     = array_column($mejorCol, 'id');
        $piezasDisp = array_values(array_filter($piezasDisp,
            fn($p) => !in_array($p['id'], $idsCol)));

        return $mejorCol;
    }

    // ── Calcular láminas ──────────────────────────────────
    $laminas   = [];
    $resto     = $piezasAdj;
    $laminaNum = 1;
    $maxLam    = 30;

    while (!empty($resto) && $laminaNum <= $maxLam) {
        $colocadas = optimizarLamina($resto, $anchoL, $altoL);
        if (empty($colocadas)) break;

        $areaUsada = array_sum(array_map(fn($p) => $p['pw']*$p['ph'], $colocadas));
        $pct       = round($areaUsada / $areaLamina * 100, 1);

        $folios = [];
        foreach ($colocadas as $p) $folios[$p['folio']] = $p['cliente_nombre'];

        $laminas[] = [
            'lamina_num'      => $laminaNum,
            'piezas'          => count($colocadas),
            'aprovechamiento' => $pct,
            'folios'          => $folios,
            'detalle'         => array_map(fn($p) => [
                'folio'     => $p['folio'],
                'partida'   => $p['partida'],
                'pieza'     => $p['pieza_num'] . '/' . $p['pieza_total'],
                'medida'    => $p['ancho_mm'] . 'x' . $p['alto_mm'],
                'corte'     => $p['pw'] . 'x' . $p['ph'],
                'rotada'    => $p['rot'],
                'tiene_cpb' => $p['tiene_cpb'],
                'cliente'   => $p['cliente_nombre'],
            ], $colocadas),
        ];
        $laminaNum++;
    }

    $noCaben = array_map(fn($p) => [
        'folio'   => $p['folio'],
        'medida'  => $p['ancho_mm'] . 'x' . $p['alto_mm'],
        'cliente' => $p['cliente_nombre'],
    ], $resto);

    $totalLaminas = count($laminas);
    $promAprov    = $totalLaminas > 0
        ? round(array_sum(array_column($laminas, 'aprovechamiento')) / $totalLaminas, 1)
        : 0;

    // Stock disponible para las dimensiones seleccionadas
    $stmtStock = $db->prepare("
        SELECT COALESCE(SUM(
            COALESCE(c.total_compradas, 0) - COALESCE(m.total_usadas, 0)
        ), 0) AS stock_laminas
        FROM laminas l
        LEFT JOIN (
            SELECT lamina_id, SUM(cantidad_laminas) AS total_compradas
            FROM inventario_compras GROUP BY lamina_id
        ) c ON c.lamina_id = l.id
        LEFT JOIN (
            SELECT lamina_id, SUM(cantidad_laminas) AS total_usadas
            FROM inventario_movimientos GROUP BY lamina_id
        ) m ON m.lamina_id = l.id
        WHERE l.activo = 1 AND l.ancho_mm = ? AND l.alto_mm = ?
    ");
    $stmtStock->execute([$anchoL, $altoL]);
    $stockRow        = $stmtStock->fetch();
    $stockDisponible = (int)($stockRow['stock_laminas'] ?? 0);
    $stockFaltante   = max(0, $totalLaminas - $stockDisponible);

    jsonResponse([
        'cristal'              => $cristal,
        'lamina'               => $anchoL . 'x' . $altoL . 'mm',
        'total_laminas'        => $totalLaminas,
        'total_piezas'         => count($piezasAdj) - count($resto),
        'prom_aprovechamiento' => $promAprov,
        'laminas'              => $laminas,
        'no_caben'             => $noCaben,
        'stock_disponible'     => $stockDisponible,
        'stock_faltante'       => $stockFaltante,
    ]);
}

jsonResponse(['error' => 'Método no permitido'], 405);