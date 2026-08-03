<?php
// Generador de pólizas (Debe/Haber) — Fase 6.2 del proyecto de partida doble (ver CLAUDE.md sección 12/13).
// Se llama desde los endpoints que YA registran el evento real (compra, pago, nómina, gastos fijos,
// caja chica); nunca captura desde cero. Las pólizas automáticas nunca se editan: cuando el origen
// cambia (ej. upsert de nómina de un periodo), se anula la póliza previa y se crea una nueva.
// Cualquier falla aquí se registra en error_log y NO debe tumbar la operación de negocio que la originó.

$PREFIJO_TIPO_POLIZA = ['diario' => 'D', 'ingresos' => 'I', 'egresos' => 'E'];

function pl_cuentaId(PDO $pdo, string $codigo): ?int {
    static $cache = [];
    if (array_key_exists($codigo, $cache)) return $cache[$codigo];
    $stmt = $pdo->prepare("SELECT id FROM cuentas_contables WHERE codigo = ?");
    $stmt->execute([$codigo]);
    $id = $stmt->fetchColumn();
    return $cache[$codigo] = ($id !== false ? (int)$id : null);
}

// Crea una póliza balanceada. $lineas = [[cuenta_id, debe, haber, concepto_linea], ...]
// Lanza Exception si no cuadra o faltan datos — quien llame decide si la deja burbujear o la atrapa.
function pl_crearPoliza(PDO $pdo, string $tipo, string $fecha, string $concepto, array $lineas, int $usuarioId, ?string $origenModulo = null, ?int $origenId = null): string {
    global $PREFIJO_TIPO_POLIZA;
    if (!isset($PREFIJO_TIPO_POLIZA[$tipo])) throw new Exception('Tipo de póliza inválido: ' . $tipo);
    if (count($lineas) < 2) throw new Exception('Se requieren al menos 2 líneas');

    $totalDebe = 0; $totalHaber = 0;
    foreach ($lineas as $l) {
        if (empty($l[0])) throw new Exception('Línea sin cuenta contable válida');
        $totalDebe  += round((float)$l[1], 2);
        $totalHaber += round((float)$l[2], 2);
    }
    if (round($totalDebe, 2) !== round($totalHaber, 2)) {
        throw new Exception('Póliza no balanceada: Debe ' . $totalDebe . ' vs Haber ' . $totalHaber);
    }

    $stmt = $pdo->prepare("INSERT INTO polizas (folio, tipo, fecha, concepto, origen_modulo, origen_id, creado_por) VALUES ('', ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tipo, $fecha, $concepto, $origenModulo, $origenId, $usuarioId]);
    $polizaId = $pdo->lastInsertId();

    $folio = $PREFIJO_TIPO_POLIZA[$tipo] . '-' . str_pad($polizaId, 6, '0', STR_PAD_LEFT);
    $pdo->prepare("UPDATE polizas SET folio = ? WHERE id = ?")->execute([$folio, $polizaId]);

    $stmtL = $pdo->prepare("INSERT INTO polizas_lineas (poliza_id, cuenta_id, debe, haber, concepto_linea, orden) VALUES (?,?,?,?,?,?)");
    foreach ($lineas as $i => $l) {
        $stmtL->execute([$polizaId, (int)$l[0], round((float)$l[1], 2), round((float)$l[2], 2), $l[3] ?? null, $i]);
    }

    return $folio;
}

// Anula (nunca borra) cualquier póliza activa ya generada para este origen — usado antes de
// regenerar (upsert) y cuando el registro fuente se elimina.
function pl_anularPorOrigen(PDO $pdo, string $origenModulo, int $origenId): void {
    try {
        $pdo->prepare("UPDATE polizas SET estado = 'anulada' WHERE origen_modulo = ? AND origen_id = ? AND estado = 'activa'")
            ->execute([$origenModulo, $origenId]);
    } catch (Exception $e) {
        error_log('polizas_lib: fallo al anular póliza de ' . $origenModulo . '#' . $origenId . ': ' . $e->getMessage());
    }
}

// Genera (o regenera) la póliza automática de un evento de negocio. Nunca lanza — si algo falla,
// el evento de negocio que la originó (compra, pago, nómina...) se guarda igual; solo queda sin
// póliza y el error se registra en error_log para revisarlo.
function pl_generarAutomatica(PDO $pdo, string $origenModulo, int $origenId, string $tipo, string $fecha, string $concepto, array $lineas, int $usuarioId): void {
    try {
        pl_anularPorOrigen($pdo, $origenModulo, $origenId);
        pl_crearPoliza($pdo, $tipo, $fecha, $concepto, $lineas, $usuarioId, $origenModulo, $origenId);
    } catch (Exception $e) {
        error_log('polizas_lib: fallo generación automática de ' . $origenModulo . '#' . $origenId . ': ' . $e->getMessage());
    }
}
