<?php
// ============================================================
//  APEX GLASS - Helper: Descuento por Encuesta de Satisfacción
//  Archivo: api/helpers/encuesta_descuento_lib.php
//  Al completar el Flow de WhatsApp de la encuesta de satisfacción
//  (5 preguntas), el webhook (api/whatsapp_webhook.php) genera un
//  código único por cliente y lo manda por WhatsApp como mensaje de
//  seguimiento: 5% de descuento ADICIONAL (se suma a cualquier otro
//  descuento activo — referido, promo por volumen —, no lo
//  reemplaza; decisión explícita de Armando, 16-sep-2026), válido
//  24 horas desde que se generó, un solo uso. El % se guarda en
//  cotizaciones.descuento_encuesta — columna aditiva separada de
//  `descuento`, mismo patrón que descuento_referido: automático, NO
//  dispara el candado de autorización dir_admin >10% (ese candado
//  solo evalúa el descuento manual).
// ============================================================

const ENCUESTA_DESCUENTO_PCT   = 5.00;
const ENCUESTA_VIGENCIA_HORAS  = 24;

// Genera un código único, lo liga al cliente y lo guarda con vigencia de
// ENCUESTA_VIGENCIA_HORAS desde AHORA. Llamar desde el webhook justo al
// recibir la respuesta de la encuesta. Devuelve ['codigo'=>string,
// 'vence_at'=>string] o null si no se pudo crear (cliente_id vacío o
// choque persistente de código único, muy improbable).
function encuestaGenerarCodigo(PDO $db, $cliente_id, $conversacion_id = null) {
    $cliente_id = (int)$cliente_id;
    if (!$cliente_id) return null;

    for ($i = 0; $i < 5; $i++) {
        $codigo = 'ENC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        try {
            $db->prepare("INSERT INTO encuesta_codigos_descuento
                (cliente_id, conversacion_id, codigo, porcentaje, generado_at, vence_at)
                VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR))")
               ->execute([$cliente_id, $conversacion_id ?: null, $codigo, ENCUESTA_DESCUENTO_PCT, ENCUESTA_VIGENCIA_HORAS]);
            $id = (int)$db->lastInsertId();
            $st = $db->prepare("SELECT vence_at FROM encuesta_codigos_descuento WHERE id = ?");
            $st->execute([$id]);
            return ['codigo' => $codigo, 'vence_at' => $st->fetchColumn()];
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) continue; // choque de UNIQUE en codigo, reintenta con uno nuevo
            throw $e;
        }
    }
    return null;
}

// Valida el código escrito por el asesor en una cotización. Solo lectura.
// Devuelve ['error'=>?string, 'codigo_id'=>?int, 'porcentaje'=>float].
function encuestaValidarCodigo(PDO $db, $codigo, $cliente_id) {
    $codigo = strtoupper(trim((string)$codigo));
    if ($codigo === '') return ['error' => null, 'codigo_id' => null, 'porcentaje' => 0.0];

    $st = $db->prepare("SELECT id, cliente_id, porcentaje, vence_at, usado FROM encuesta_codigos_descuento WHERE codigo = ?");
    $st->execute([$codigo]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['error' => 'El código "' . $codigo . '" no existe.', 'codigo_id' => null, 'porcentaje' => 0.0];
    }
    if ((int)$row['cliente_id'] !== (int)$cliente_id) {
        return ['error' => 'Este código de encuesta pertenece a otro cliente.', 'codigo_id' => null, 'porcentaje' => 0.0];
    }
    if ((int)$row['usado']) {
        return ['error' => 'Este código de encuesta ya fue utilizado.', 'codigo_id' => null, 'porcentaje' => 0.0];
    }
    if ($row['vence_at'] < date('Y-m-d H:i:s')) {
        return ['error' => 'Este código de encuesta ya venció (era válido 24 horas después de contestarla).', 'codigo_id' => null, 'porcentaje' => 0.0];
    }
    return ['error' => null, 'codigo_id' => (int)$row['id'], 'porcentaje' => (float)$row['porcentaje']];
}

// Marca el código como usado, ligado a la cotización. Llamar DENTRO de la
// misma transacción, justo después del INSERT/UPDATE de la cotización —
// solo si $codigo_id vino de una validación hecha EN ESTA MISMA llamada
// (nunca reaplicar sobre un código ya usado previamente por esta cotización).
function encuestaMarcarUsado(PDO $db, $codigo_id, $cotizacion_id) {
    if (!$codigo_id) return;
    $db->prepare("UPDATE encuesta_codigos_descuento SET usado = 1, usado_at = NOW(), cotizacion_id_usado = ? WHERE id = ? AND usado = 0")
       ->execute([$cotizacion_id, $codigo_id]);
}
