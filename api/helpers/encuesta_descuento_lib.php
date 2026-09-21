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

// ------------------------------------------------------------------
// Promo de recordatorio (21-sep-2026, ver CLAUDE.md sección 12/13 UPD-596).
// Plazo normal de la encuesta: hoy (INICIO) hasta el miércoles 23-sep
// (ENCUESTA_FIN) — código con vigencia fija hasta FIN (en vez de 24h) y
// sorteo por orden de llegada: primeros CUPO_10 que cumplan "sin compra en
// SIN_COMPRA_DIAS+ días" se quedan con PCT_10, siguientes CUPO_75 que
// cumplan con PCT_75, todos los demás (cumplan o no, o ya agotado el cupo)
// con ENCUESTA_DESCUENTO_PCT normal.
// Plazo tardío (jue 24 / vie 25 / sáb 26, TARDIO_INICIO..FIN): quien
// conteste ahí ya no entra al sorteo — % fijo reducido PCT_TARDIO, misma
// vigencia hasta FIN (para quien conteste el sábado casi no le da tiempo,
// a propósito, para desincentivar contestar tarde).
// A partir del domingo 27 (después de FIN): los códigos ya no sirven
// (vence_at siempre <= FIN) y cualquier código nuevo generado ese día en
// adelante vuelve solo a 24h / 5% fijo, sin tocar código otra vez.
// ------------------------------------------------------------------
const ENCUESTA_PROMO_INICIO          = '2026-09-21 00:00:00';
const ENCUESTA_PROMO_ENCUESTA_FIN    = '2026-09-23 23:59:59';
const ENCUESTA_PROMO_TARDIO_INICIO   = '2026-09-24 00:00:00';
const ENCUESTA_PROMO_PCT_TARDIO      = 3.00;
const ENCUESTA_PROMO_FIN             = '2026-09-26 23:59:59';
const ENCUESTA_PROMO_SIN_COMPRA_DIAS = 20;
const ENCUESTA_PROMO_CUPO_10         = 3;
const ENCUESTA_PROMO_PCT_10          = 10.00;
const ENCUESTA_PROMO_CUPO_75         = 10;
const ENCUESTA_PROMO_PCT_75          = 7.50;

function encuestaEnPromoRecordatorio() {
    $ahora = date('Y-m-d H:i:s');
    return ($ahora >= ENCUESTA_PROMO_INICIO && $ahora <= ENCUESTA_PROMO_FIN);
}

// true si el cliente no tiene ninguna venta real en los últimos $dias días (o
// nunca ha comprado). Mismo criterio canónico de "venta" que reporte_direccion.php:
// ordenes.estado IN ('activa','entregada'), cotizaciones.es_retrabajo=0,
// fecha = COALESCE(vobo_at, fecha_pedido, created_at).
function encuestaSinCompraReciente(PDO $db, $cliente_id, $dias) {
    $st = $db->prepare("
        SELECT MAX(COALESCE(DATE(c.vobo_at), o.fecha_pedido, DATE(o.created_at))) AS ultima_venta
        FROM ordenes o
        JOIN cotizaciones c ON c.orden_id = o.id
        WHERE o.estado IN ('activa','entregada') AND c.es_retrabajo = 0 AND c.cliente_id = ?
    ");
    $st->execute([(int)$cliente_id]);
    $ultima = $st->fetchColumn();
    if (!$ultima) return true;
    return (strtotime(date('Y-m-d')) - strtotime($ultima)) >= $dias * 86400;
}

// % de un código dentro de la promo. Si contesta después del plazo normal
// de la encuesta (jue 24 / vie 25 / sáb 26) es % fijo reducido, sin sorteo.
// Dentro del plazo normal, se decide por orden de llegada solo entre quienes
// cumplen encuestaSinCompraReciente(). Cupo total de solo 13 códigos — un
// empate exacto entre 2 webhooks concurrentes podría dar 1 código de más
// del cupo; riesgo aceptado dado el volumen bajísimo esperado.
function encuestaPromoPorcentaje(PDO $db, $cliente_id) {
    if (date('Y-m-d H:i:s') >= ENCUESTA_PROMO_TARDIO_INICIO) {
        return ENCUESTA_PROMO_PCT_TARDIO;
    }
    if (!encuestaSinCompraReciente($db, $cliente_id, ENCUESTA_PROMO_SIN_COMPRA_DIAS)) {
        return ENCUESTA_DESCUENTO_PCT;
    }
    $st = $db->prepare("SELECT COUNT(*) FROM encuesta_codigos_descuento
        WHERE porcentaje = ? AND generado_at BETWEEN ? AND ?");

    $st->execute([ENCUESTA_PROMO_PCT_10, ENCUESTA_PROMO_INICIO, ENCUESTA_PROMO_FIN]);
    if ((int)$st->fetchColumn() < ENCUESTA_PROMO_CUPO_10) return ENCUESTA_PROMO_PCT_10;

    $st->execute([ENCUESTA_PROMO_PCT_75, ENCUESTA_PROMO_INICIO, ENCUESTA_PROMO_FIN]);
    if ((int)$st->fetchColumn() < ENCUESTA_PROMO_CUPO_75) return ENCUESTA_PROMO_PCT_75;

    return ENCUESTA_DESCUENTO_PCT;
}

// Genera un código único, lo liga al cliente y lo guarda con vigencia de
// ENCUESTA_VIGENCIA_HORAS desde AHORA (o la vigencia fija de la promo de
// recordatorio si está activa). Llamar desde el webhook justo al recibir la
// respuesta de la encuesta. Devuelve ['codigo'=>string, 'vence_at'=>string,
// 'porcentaje'=>float] o null si no se pudo crear (cliente_id vacío o choque
// persistente de código único, muy improbable).
function encuestaGenerarCodigo(PDO $db, $cliente_id, $conversacion_id = null) {
    $cliente_id = (int)$cliente_id;
    if (!$cliente_id) return null;

    $enPromo    = encuestaEnPromoRecordatorio();
    $porcentaje = $enPromo ? encuestaPromoPorcentaje($db, $cliente_id) : ENCUESTA_DESCUENTO_PCT;

    for ($i = 0; $i < 5; $i++) {
        $codigo = 'ENC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        try {
            if ($enPromo) {
                $db->prepare("INSERT INTO encuesta_codigos_descuento
                    (cliente_id, conversacion_id, codigo, porcentaje, generado_at, vence_at)
                    VALUES (?, ?, ?, ?, NOW(), ?)")
                   ->execute([$cliente_id, $conversacion_id ?: null, $codigo, $porcentaje, ENCUESTA_PROMO_FIN]);
            } else {
                $db->prepare("INSERT INTO encuesta_codigos_descuento
                    (cliente_id, conversacion_id, codigo, porcentaje, generado_at, vence_at)
                    VALUES (?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? HOUR))")
                   ->execute([$cliente_id, $conversacion_id ?: null, $codigo, $porcentaje, ENCUESTA_VIGENCIA_HORAS]);
            }
            $id = (int)$db->lastInsertId();
            $st = $db->prepare("SELECT vence_at FROM encuesta_codigos_descuento WHERE id = ?");
            $st->execute([$id]);
            return ['codigo' => $codigo, 'vence_at' => $st->fetchColumn(), 'porcentaje' => $porcentaje];
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
