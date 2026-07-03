# QR maestro de orden para registro masivo en CNC (Corte)

**Fecha:** 2026-07-03
**Solicitado por:** Armando
**Estado:** Aprobado, pendiente de plan de implementación

## Problema

El encargado de corte, al bajar una orden de producción impresa (`app/imprimir_orden.php`) e ingresar los pedidos al CNC, hoy tiene que escanear el QR de **cada pieza individualmente** en `app/operador.php` para pasarlas de `pendiente` a `en_corte`. Esto es lento cuando una orden tiene muchas piezas.

## Objetivo

Agregar un QR "maestro" en `imprimir_orden.php`, junto al título de la empresa, que al escanearse en `operador.php` pase **todas las piezas pendientes de esa orden** a `en_corte` de una sola vez, con confirmación previa.

## Alcance

Solo cubre la transición `pendiente → en_corte`. No toca ninguna otra transición de estatus ni otras estaciones.

## Diseño

### 1. QR en `imprimir_orden.php` (visual)

- El bloque `.header` (líneas ~160-164) es hoy `text-align:center`, sin columnas, con `border:2px solid #000; padding:10px`.
- Se le agrega `position:relative`.
- Se agrega `<div class="qr-masivo">` con `position:absolute; top: Npx; right: Npx` (ajustar en implementación con vista previa de impresión), tamaño aproximado 55-60px, generado con la misma librería cliente `../lib/qrcode.min.js` que ya usa `imprimir_etiquetas.php`.
- Al ser `position:absolute`, no afecta el flujo del texto centrado (empresa/título) ni agranda el recuadro — cumple el requisito de "sin incrementar los márgenes del recuadro".
- Se coloca en la esquina superior derecha del header, al lado del título "TEMPLADORA NORESTE, S.A. DE C.V.".

### 2. Payload del QR

- URL: `https://apex.glass/produccion/app/operador.php?orden_masivo=<orden_id>`
- Se distingue del patrón de QR de pieza (`?qr=<qr_code>`, formato `{FOLIO}-{PP}-{NNN}-{TTT}`) por el nombre del parámetro `orden_masivo`.
- Nota de implementación: `imprimir_orden.php` se genera hoy con `?id=<cotizacion_id>` — antes de codear, confirmar en qué momento del flujo cotización→orden se resuelve `ordenes.id` (el `orden_id` real usado en `piezas.orden_id`), para no confundir cotización con orden en el QR.

### 3. Detección en `operador.php`

- `extraerCodigo()` ya extrae el código de una URL completa o un texto plano.
- Se agrega detección: si el texto decodificado contiene `orden_masivo=`, en vez de invocar `loadPieza(code)` (flujo de una pieza), se invoca una función nueva `loadOrdenMasiva(ordenId)`.
- `loadOrdenMasiva(ordenId)` llama a un endpoint (nuevo o parámetro nuevo en uno existente) que devuelve: folio, cliente, y conteo de piezas en estatus `pendiente` para esa orden.
- Esta rama solo se activa para usuarios cuyo rol de estación es "corte" (mismo `ESTACION_REG` que ya restringe qué botones/acciones ve cada estación). Si un usuario de otra estación escanea el QR maestro por error, no debe ofrecerle la acción masiva.

### 4. Pantalla de confirmación (UX)

- Al escanear, se muestra una pantalla/tarjeta: *"Orden R-806 — 12 piezas pendientes → pasarán a EN_CORTE"* con un botón de confirmar y uno de cancelar.
- Solo al presionar "Confirmar" se dispara la actualización masiva (evita que un escaneo accidental — ej. la hoja pasando frente a la cámara — mueva piezas sin intención).
- Si la orden ya no tiene piezas en `pendiente` (0 piezas), se muestra un mensaje informativo: *"Esta orden ya fue registrada en CNC"*, sin botón de confirmar y sin generar error. Reintentar el escaneo es seguro (idempotente).

### 5. Backend — actualización masiva

- Nueva rama/acción en `api/actualizar_estatus.php` (ej. `modo=orden_masivo`) que recibe `orden_id` + `usuario_id`.
- Query: `UPDATE piezas SET estatus='en_corte', updated_at=NOW() WHERE orden_id=? AND estatus='pendiente'`.
- Por cada pieza afectada, se inserta un renglón en `historial_estatus` (mismo patrón que el loop de omisiones ya existente en el archivo, líneas ~162-183), registrando `usuario_id`, estatus anterior (`pendiente`), estatus nuevo (`en_corte`), y una nota indicando que fue vía "registro masivo QR de orden" — para no perder trazabilidad individual pese a ser una acción en lote.
- Piezas que no están en `pendiente` (ya avanzadas, o en `reproceso`) no se tocan.
- Se usa transacción PDO para que la actualización de piezas + inserciones de historial sean atómicas.

### 6. Permisos

- Igual que las demás acciones de `operador.php`: solo usuarios logueados con estación "corte" (según `$rolesOperador`/`ESTACION_REG`) pueden ver y ejecutar la confirmación del registro masivo.

## Fuera de alcance

- No se modifica el flujo de escaneo pieza por pieza existente (sigue disponible como respaldo).
- No se agregan estatus nuevos al ENUM — se reutiliza `en_corte` ya existente.
- No se automatiza ninguna otra transición de estatus (`cortado`, `canteado`, etc.) — solo `pendiente → en_corte`.

## Riesgos / cosas a validar en implementación

- Confirmar que `imprimir_orden.php?id=X` resuelve correctamente a `ordenes.id` (no `cotizacion_id`) para construir el payload del QR con el `orden_id` correcto.
- Ajustar tamaño/posición exacta del QR en el header con una vista previa de impresión real (letter landscape) para que no se traslape con el texto ni se corte en la impresora térmica/láser que usan.
- Verificar que el `BarcodeDetector`/fallback `jsQR` de `operador.php` decodifique bien un QR de tamaño reducido (~55-60px) impreso, ya que los QR de piezas actuales se imprimen a 80px/22mm — puede requerir aumentar el tamaño del QR maestro si la cámara tiene dificultad a esa escala.
