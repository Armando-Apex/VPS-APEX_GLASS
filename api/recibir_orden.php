<?php
// ============================================================
//  APEX GLASS - API: Recibir orden desde Google Sheets
//  Archivo: api/recibir_orden.php  — v2
//  Método: POST
//  Header requerido: X-API-Key: [tu clave]
// ============================================================

require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Método no permitido'], 405);

requireApiKey();

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error' => 'JSON inválido'], 400);

if (empty($input['folio']))    jsonResponse(['error' => 'Folio requerido'], 400);
if (empty($input['partidas']) || !is_array($input['partidas'])) {
    jsonResponse(['error' => 'Se requiere al menos una partida'], 400);
}

$db = getDB();

try {
    $db->beginTransaction();

    // ── 1. Buscar o crear orden ───────────────────────────────
    $folio = trim(strtoupper($input['folio']));
    $stmt  = $db->prepare('SELECT id FROM ordenes WHERE folio = ?');
    $stmt->execute([$folio]);
    $orden = $stmt->fetch();

    if ($orden) {
        $ordenId = $orden['id'];
        // Borrar piezas anteriores para recrearlas
        $db->prepare('DELETE FROM piezas WHERE orden_id = ?')->execute([$ordenId]);

        $db->prepare('UPDATE ordenes SET 
            orden_trabajo  = ?,
            tipo           = ?,
            cliente_nombre = ?,
            asesor         = ?,
            proyecto       = ?,
            fecha_pedido   = ?,
            fecha_entrega  = ?,
            tipo_entrega   = ?,
            ubicacion      = ?,
            ciudad_destino = ?,
            observaciones  = ?,
            sheets_id      = ?,
            updated_at     = NOW()
            WHERE id = ?
        ')->execute([
            $input['orden_trabajo']  ?? $folio,
            $input['tipo']           ?? 'suministro',
            $input['cliente']        ?? '',
            $input['asesor']         ?? '',
            $input['proyecto']       ?? '',
            $input['fecha_pedido']   ?? null,
            $input['fecha_entrega']  ?? null,
            $input['tipo_entrega']   ?? 'planta',
            $input['ubicacion']      ?? '',
            $input['ciudad_destino'] ?? '',
            $input['observaciones']  ?? '',
            $input['sheets_id']      ?? '',
            $ordenId
        ]);
    } else {
        $stmt = $db->prepare('INSERT INTO ordenes 
            (folio, orden_trabajo, tipo, cliente_nombre, asesor, proyecto,
             fecha_pedido, fecha_entrega, tipo_entrega, ubicacion, ciudad_destino,
             observaciones, sheets_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ');
        $stmt->execute([
            $folio,
            $input['orden_trabajo']  ?? $folio,
            $input['tipo']           ?? 'suministro',
            $input['cliente']        ?? '',
            $input['asesor']         ?? '',
            $input['proyecto']       ?? '',
            $input['fecha_pedido']   ?? null,
            $input['fecha_entrega']  ?? null,
            $input['tipo_entrega']   ?? 'planta',
            $input['ubicacion']      ?? '',
            $input['ciudad_destino'] ?? '',
            $input['observaciones']  ?? '',
            $input['sheets_id']      ?? ''
        ]);
        $ordenId = $db->lastInsertId();
    }

    // ── 2. Insertar piezas individuales ──────────────────────
    $stmtPieza = $db->prepare('INSERT INTO piezas 
        (orden_id, partida, pieza_num, pieza_total,
         cristal, cristal_corto, requiere_templado,
         ancho_mm, alto_mm, m2,
         cpb, detalles, resaques, tp, ta,
         pintura, esmerilado, acabado_forma,
         tipo_biselado, espesor_biselado,
         comentarios, qr_code, estatus)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ');

    $piezasCreadas = 0;

    foreach ($input['partidas'] as $partida) {
        $numPartida = intval($partida['partida']    ?? 0);
        $numPiezas  = intval($partida['num_piezas'] ?? 1);
        $cristal    = trim($partida['cristal']      ?? '');

        if (empty($cristal) || $numPiezas < 1) continue;

        $cristalCorto     = trim($partida['cristal_corto'] ?? '') ?: $cristal;
        $requiereTemplado = isset($partida['recocido']) && $partida['recocido'] ? 0 : 1;

        $ancho = intval($partida['ancho_mm'] ?? 0);
        $alto  = intval($partida['alto_mm']  ?? 0);
        $m2    = ($ancho > 0 && $alto > 0) ? round(($ancho / 1000) * ($alto / 1000), 4) : 0;

        for ($i = 1; $i <= $numPiezas; $i++) {
            $qr = generarQR($folio, $numPartida, $i, $numPiezas);

            $stmtPieza->execute([
                $ordenId,
                $numPartida,
                $i,
                $numPiezas,
                $cristal,
                $cristalCorto,
                $requiereTemplado,
                $ancho,
                $alto,
                $m2,
                $partida['cpb']           ?? '',
                $partida['detalles']      ?? '',
                intval($partida['resaques']      ?? 0),
                intval($partida['tp']            ?? 0),
                intval($partida['ta']            ?? 0),
                empty($partida['pintura'])       ? 0 : 1,
                empty($partida['esmerilado'])    ? 0 : 1,
                empty($partida['acabado_forma']) ? 0 : 1,
                $partida['tipo_biselado']        ?? '',
                $partida['espesor_biselado']     ?? '',
                $partida['comentarios']          ?? '',
                $qr,
                'pendiente'
            ]);
            $piezasCreadas++;
        }
    }

    $db->commit();

    jsonResponse([
        'ok'      => true,
        'orden_id'=> $ordenId,
        'folio'   => $folio,
        'piezas'  => $piezasCreadas,
        'message' => "Orden $folio importada con $piezasCreadas piezas"
    ]);

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['error' => $e->getMessage()], 500);
}