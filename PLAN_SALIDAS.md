# PLAN: Rediseño flujo imprimir_salida.php
# Creado: 27-jun-2026 | UPD destino: UPD-241
# Estado al iniciar: $ya_entregada = true (bypass temporal activo)

---

## ESTADO ACTUAL DE ARCHIVOS (pre-cambio)

- `app/imprimir_salida.php` — bypass activo: `$ya_entregada = true`, imprime todas las piezas directo
- `api/salidas.php` — registro de salidas funcionando (POST registrar_salida)
- `api/orden.php` — fix 403 aplicado (UPD-239)
- BD: `orden_salidas` + `orden_salida_piezas` — estructura existente y correcta

---

## FLUJO OBJETIVO (5 casos)

### Caso A — Orden activa CON salidas previas registradas
Pregunta: "¿Qué deseas hacer?"
- [Reimprimir] → Caso B
- [Registrar nueva entrega] → Caso C

### Caso B — Reimprimir (parcial o completa, ya registrada)
- Sin preguntas. Directo al documento con estado actual de todas las piezas.

### Caso C — Registrar nueva entrega (piezas pendientes)
1. Mostrar selector: chips de piezas (terminado=seleccionable, entregado=bloqueado gris, en_proceso=bloqueado naranja)
2. Elegir método: [Recolección en planta] | [Chofer / Domicilio]
3. Si Chofer: campo fecha de entrega programada
4. Preview del documento
5. Botón Confirmar → BD (registrar_salida) + auto-print + WA automático

### Caso D — Primera entrega, TODAS las piezas terminadas (orden completa)
1. Solo pregunta método: [Recolección en planta] | [Chofer / Domicilio]
2. Si Chofer: campo fecha
3. Preview
4. Confirmar → BD + print + WA → orden se cierra como "entregada"

### Caso E — Orden ya cerrada (estado "entregada"), reimprimir
- Sin preguntas. Directo al documento con todas las fechas de entrega reales.

---

## DOCUMENTO DE SALIDA — FORMATO

Muestra TODAS las piezas de la orden en una tabla con columna de estatus:

| Part. | Cristal | Ancho | Alto | Pzas | Detalles | Estatus entrega |
|-------|---------|-------|------|------|----------|----------------|
| 1     | Claro 6mm | 800 | 900 | 1  | ...      | Entregada 27/jun/2026 |
| 2     | Claro 6mm | 700 | 800 | 1  | ...      | Entregada 28/jun/2026 |
| 3     | Claro 9mm | 500 | 600 | 1  | ...      | PENDIENTE |

- Piezas entregadas: fecha real de entrega (de `orden_salidas.fecha_entrega_chofer` o `created_at`)
- Pieza de la salida actual: fecha programada o fecha de hoy
- Piezas pendientes (no terminadas aún): "PENDIENTE"

---

## CAMBIOS TÉCNICOS — EN ORDEN DE EJECUCIÓN

### PASO 1: PHP — Lógica de casos al inicio del archivo
Reemplazar el bloque `$ya_entregada = true` (bypass) por detección real de casos:

```php
// Detectar caso
$ya_entregada    = ($orden && $orden['estado'] === 'entregada');
$tiene_salidas   = false;
$salidas_previas = [];
$fechas_entrega  = []; // pieza_id => fecha_entrega string

if ($orden_id_php) {
    // Obtener salidas registradas con sus piezas y fechas
    $stmtSp = $db->prepare('
        SELECT os.id, os.tipo, os.piezas_count, os.es_parcial,
               os.fecha_entrega_chofer, os.created_at,
               GROUP_CONCAT(osp.pieza_id ORDER BY osp.pieza_id) AS pieza_ids_str
        FROM orden_salidas os
        LEFT JOIN orden_salida_piezas osp ON osp.salida_id = os.id
        WHERE os.orden_id = ? AND os.cotizacion_id = ?
        GROUP BY os.id ORDER BY os.created_at ASC
    ');
    $stmtSp->execute([$orden_id_php, $cotizacion_id_php]);
    foreach ($stmtSp->fetchAll(PDO::FETCH_ASSOC) as $sp) {
        $sp['pieza_ids'] = $sp['pieza_ids_str'] ? array_map('intval', explode(',', $sp['pieza_ids_str'])) : [];
        unset($sp['pieza_ids_str']);
        // Fecha de entrega real: fecha_entrega_chofer > created_at
        $fecha_real = $sp['fecha_entrega_chofer'] ?: date('Y-m-d', strtotime($sp['created_at']));
        foreach ($sp['pieza_ids'] as $pid) {
            $fechas_entrega[$pid] = $fecha_real;
        }
        $salidas_previas[] = $sp;
    }
    $tiene_salidas = count($salidas_previas) > 0;
}
```

### PASO 2: PHP — Generar tbody con TODAS las piezas + columna estatus
Siempre generar el tbody (no solo cuando ya_entregada). Agregar columna "Entrega":

```php
// Columna Entrega por pieza
$meses_doc = ['','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
$fmtFecha = function($f) use ($meses_doc) {
    if (!$f) return 'PENDIENTE';
    $ts = strtotime($f);
    return date('d', $ts) . '/' . $meses_doc[(int)date('n', $ts)] . '/' . date('Y', $ts);
};

// En el thead: agregar <th>Entrega</th>
// En cada tr: agregar <td> según $fechas_entrega[$p['id']] ?? null </td>
// Piezas de la salida actual (pieza_ids seleccionados): mostrar $fecha_chofer_php o hoy
// Piezas entregadas anteriormente: mostrar fecha de $fechas_entrega
// Piezas pendientes: "PENDIENTE" en gris
```

### PASO 3: PHP — Variables JS inyectadas
```php
var TIENE_SALIDAS  = <?= $tiene_salidas ? 'true' : 'false' ?>;
var YA_ENTREGADA   = <?= $ya_entregada ? 'true' : 'false' ?>;
var SALIDAS_PREVIAS = <?= $salidas_previas_json ?>;
```

### PASO 4: JS — init() con lógica de casos
```javascript
(function init() {
    if (YA_ENTREGADA) {
        // Caso E: orden cerrada, solo imprimir
        setTimeout(function() { window.print(); }, 400);
        return;
    }
    if (TIENE_SALIDAS) {
        // Caso A: mostrar pantalla "¿Reimprimir o nueva entrega?"
        mostrarMenuSalidas();
        return;
    }
    if (!ORDEN_ID) {
        mostrarError('Sin orden vinculada');
        return;
    }
    // Caso C o D: cargar piezas para nueva entrega
    cargarPiezasYMostrarSelector();
})();
```

### PASO 5: JS — mostrarMenuSalidas()
Pantalla con dos botones:
- "Reimprimir última salida" → llama reimprimir(SALIDAS_PREVIAS.length - 1)
- "Registrar nueva entrega" → cargarPiezasYMostrarSelector()

### PASO 6: JS — cargarPiezasYMostrarSelector()
Fetch piezas, detectar si TODAS terminadas (Caso D: skip selector, ir a método directo) o parciales (Caso C: mostrar selector primero).

### PASO 7: JS — Preview antes de confirmar
Antes del POST, mostrar el documento con las piezas seleccionadas en estatus "Hoy / fecha programada" y las ya entregadas con su fecha. Botón "Confirmar y registrar" dispara el POST.

### PASO 8: JS — Post-confirmación
- Pushear a SALIDAS_PREVIAS la nueva salida
- Mostrar documento final con auto-print
- "Volver" regresa al menú (Caso A, ya con salidas)

---

## CAMBIOS EN API (mínimos)

- `api/salidas.php` — sin cambios necesarios en la lógica de registro
- Posible adición: acción `GET historial_salidas` para obtener salidas con fechas por orden (opcional, se puede hacer todo en PHP al cargar)

---

## ORDEN DE EJECUCIÓN SEGURO (si se corta la luz)

El cambio es TODO en `imprimir_salida.php`. El bypass actual (`$ya_entregada = true`) 
se mantiene hasta el último momento — solo se remueve cuando el nuevo código esté completo.

1. ✅ Escribir nueva lógica PHP (pasos 1-3) — probar que página carga sin error
2. ✅ Escribir HTML: pantalla menú (Caso A) + documento con columna Entrega
3. ✅ Escribir JS: init() + mostrarMenuSalidas() + cargarPiezasYMostrarSelector()
4. ✅ Quitar bypass ($ya_entregada = true) — activar nueva lógica
5. ✅ Prueba Caso E (orden entregada) → auto-print ✓
6. ✅ Prueba Caso A (orden activa con salidas) → menú ✓
7. ✅ Prueba Caso C (registrar parcial) → selector → print ✓
8. ✅ Prueba Caso D (todo terminado, primera vez) → método → print ✓
9. ✅ Prueba Reimprimir → print directo ✓

---

## NOTA IMPORTANTE
Si se corta a mitad: el bypass `$ya_entregada = true` sigue activo mientras
el nuevo código esté incompleto. Lina puede seguir imprimiendo en todo momento.
Solo se desactiva el bypass en el PASO 4 cuando TODO el código nuevo esté en su lugar.

---
FIN DEL PLAN
