# Maquila de piezas — Diseño

Fecha: 2026-07-04
Autor: Armando (brainstorming con Claude)
Estado: Aprobado, pendiente de plan de implementación

## Contexto

Apex Glass ya opera cotización → orden → VoBo → producción → entrega para
venta de vidrio (suministro). Se necesita un complemento para **maquila**:
el cliente trae su propio vidrio y Apex solo cobra los servicios aplicados
(corte, canteado, taladro, templado/horno). El proceso de producción física
es el mismo tablero de estaciones, solo que cada pieza puede saltarse
estaciones que no aplican (ej. una pieza que solo lleva templado brinca
directo a horno).

`ordenes.tipo` ya tiene el valor `'maquila'` en el ENUM desde hace tiempo,
pero no hay código que lo use — este proyecto lo activa.

## Alcance

Incluye: cotización de maquila, conversión a orden, VoBo/cobranza (reuso),
flujo de producción con salto dinámico de estaciones, catálogo de precios
por servicio y espesor, catálogo de tipos de vidrio, módulo nuevo en el
dashboard, impresión de cotización/orden/etiquetas adaptada.

Fuera de alcance (explícito):
- Resaques (cortes internos) no se cobran en maquila.
- Reportes de Dirección/Productividad/Ventas y Cobranza no desglosan
  maquila por separado en esta primera versión — queda mezclado con
  suministro (mismo comportamiento, sin cambios en esas queries).
- No hay catálogo de clientes nuevo; se reutiliza el CRM existente.

## 1. Modelo de datos

### `cotizaciones` (columna nueva)
```sql
ALTER TABLE cotizaciones
  ADD COLUMN tipo ENUM('suministro','maquila') NOT NULL DEFAULT 'suministro';
```
Mismo folio COT-XXXX / xCOT-XXX (sin cambio a `generarFolio()`), mismo
header: cliente, asesor, descuento, condición de pago, localidad/entrega,
fecha de entrega — **mismas reglas de días hábiles** que suministro
(`calcularFechaEntrega()` reutilizada tal cual, sin duplicar lógica).

### Nueva tabla `maquila_tipos_vidrio`
Catálogo simple (sin precio) para que el asesor seleccione qué tipo de
vidrio trajo el cliente, en vez de texto libre — mantiene orden en la BD.
```sql
CREATE TABLE maquila_tipos_vidrio (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```
Ejemplos iniciales: Claro, Bronce, Reflectivo, Filtrasol, Templado (traído
ya templado por el cliente, solo requiere otro servicio), Laminado.

### Nueva tabla `cotizaciones_maquila_partidas`
```sql
CREATE TABLE cotizaciones_maquila_partidas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cotizacion_id INT NOT NULL,
  num_partida INT NOT NULL,
  cristal_tipo_id INT DEFAULT NULL,
  espesor_mm DECIMAL(5,2) NOT NULL,
  ancho INT NOT NULL,
  alto INT NOT NULL,
  cantidad INT NOT NULL DEFAULT 1,
  m2 DECIMAL(10,6) DEFAULT NULL,

  corte TINYINT(1) NOT NULL DEFAULT 0,
  ml_corte DECIMAL(10,4) DEFAULT NULL,
  precio_corte_usado DECIMAL(10,4) DEFAULT NULL,
  subtotal_corte DECIMAL(12,2) DEFAULT NULL,

  canteado TINYINT(1) NOT NULL DEFAULT 0,
  cpb VARCHAR(100) DEFAULT NULL,
  ml_canteado DECIMAL(10,4) DEFAULT NULL,
  precio_canteado_usado DECIMAL(10,4) DEFAULT NULL,
  subtotal_canteado DECIMAL(12,2) DEFAULT NULL,

  taladros_pasados INT NOT NULL DEFAULT 0,
  taladros_avellanados INT NOT NULL DEFAULT 0,
  precio_taladro_usado DECIMAL(10,4) DEFAULT NULL,
  subtotal_taladro DECIMAL(12,2) DEFAULT NULL,

  templado TINYINT(1) NOT NULL DEFAULT 0,
  precio_horno_usado DECIMAL(10,4) DEFAULT NULL,
  subtotal_horno DECIMAL(12,2) DEFAULT NULL,

  detalles VARCHAR(200) DEFAULT NULL,
  subtotal DECIMAL(12,2) DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id) ON DELETE CASCADE,
  FOREIGN KEY (cristal_tipo_id) REFERENCES maquila_tipos_vidrio(id) ON DELETE SET NULL
);
```

**Cálculo de ml (mismo esquema que CPB de canteado):**
- `Perimetral` = 2×(ancho+alto)/1000
- `Larguero` = alto/1000 · `Largueros` = 2×alto/1000
- `Cabezal` = ancho/1000 · `Cabezales` = 2×ancho/1000
- `1 Larguero - 1 cabezal` = alto/1000 + ancho/1000
- `2 Largueros - 1 cabezal` = 2×alto/1000 + ancho/1000
- `1 Larguero - 2 cabezales` = alto/1000 + 2×ancho/1000
- `No` → canteado no aplica, `ml_canteado = 0`

`ml_corte` siempre = perímetro completo (2×(ancho+alto)/1000), automático,
sin selector — cortar es sacar la pieza completa a su medida final.

Taladro: se capturan pasados y avellanados por separado (para producción),
pero se cobran con **un solo precio** total: `(pasados+avellanados) ×
precio_taladro_usado`.

### Nueva tabla `maquila_precios` (catálogo admin)
```sql
CREATE TABLE maquila_precios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  servicio ENUM('corte','canteado','taladro','horno') NOT NULL,
  espesor_mm DECIMAL(5,2) NOT NULL,
  precio DECIMAL(10,4) NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_servicio_espesor (servicio, espesor_mm)
);
```
Espesores estándar iniciales (dropdown en el formulario): 3, 4, 5, 6, 8, 10,
12, 15, 19 mm. Panel CRUD (solo `dir_admin`/`dueno`) igual al patrón del
módulo Cristales, agrupado por servicio.

### `piezas` (columna nueva)
```sql
ALTER TABLE piezas
  ADD COLUMN requiere_corte TINYINT(1) NOT NULL DEFAULT 1;
```
Default `1` preserva el comportamiento actual de suministro (siempre pasa
por corte). En maquila se llena desde `partida.corte`. `requiere_canteado`
y `requiere_taladro` **no necesitan columna nueva** — se derivan de
`cpb != 'No'` y `tp + ta > 0` respectivamente (ambas columnas ya existen).

## 2. Flujo cotización → orden → VoBo

Al convertir (mismo botón "Convertir a Orden" existente en
`api/cotizaciones.php`, acción `convertir`):
- `generarFolioOrden()` se llama igual que hoy (misma secuencia S-XXX
  compartida con suministro — el contador nunca se reinicia ni cambia).
- Se guarda `ordenes.folio = "MA-" . $folio_orden` y `ordenes.tipo =
  'maquila'` (ej. `MA-S-321`, sin espacio para no romper parsers/regex de
  folio en otros módulos).
- Se crean `piezas` igual que hoy (mismo INSERT, misma tabla), con las
  columnas de servicios ya descritas arriba, más `requiere_corte`.
- `cristal` / `cristal_corto` de la pieza se llenan con el nombre del tipo
  de vidrio + espesor (ej. "Cliente: Claro 6mm") para que la etiqueta lo
  muestre — son campos de texto libre, no requieren catálogo en `piezas`.
- **QR de la pieza**: para `tipo='maquila'`, el QR usa el folio de la
  **orden** (`"MA-S-321" . '-P' . partida . '-' . n . 'de' . total`), a
  diferencia de suministro que sigue usando el folio de cotización sin
  cambios. Este es el único punto donde el comportamiento difiere entre
  tipos en el código de generación de QR.
- La orden entra en `pendiente_vobo` igual que hoy. Lina la ve en el mismo
  módulo Finanzas VoBo/Cobranza sin cambios (esas queries no filtran por
  `tipo`), registra el pago/anticipo y da VoBo → pasa a `activa`.

## 3. Flujo de producción — salto dinámico de estaciones

`api/actualizar_estatus.php` hoy tiene flujo fijo con una sola excepción
hardcodeada (`requiere_templado=0` salta `en_horno`). Se generaliza **solo
para `ordenes.tipo='maquila'`** (suministro no se toca, mismo código
`$FLUJO_PREVIO`/`$FLUJO_ORDEN` estático de siempre):

1. El SELECT de la pieza agrega `o.tipo` (ya hace JOIN con `ordenes`, solo
   falta el campo).
2. Si `$pieza['tipo'] === 'maquila'`, se construye la secuencia aplicable
   filtrando `FLUJO_COMPLETO` según:
   - `en_corte`, `cortado` → incluidos solo si `requiere_corte = 1`
   - `canteado` → incluido solo si `cpb != 'No'`
   - `trazo`, `taladro` → incluidos solo si `tp + ta > 0`
   - `en_horno` → incluido solo si `requiere_templado = 1`
3. El predecesor válido de cada estatus destino se calcula como el último
   elemento de esa secuencia filtrada antes del estatus, en vez de leerse
   de la tabla estática. `terminado` toma como predecesor el último
   elemento aplicable (podría ser `en_horno`, `taladro`, `canteado`,
   `cortado`, o `pendiente` si ningún servicio intermedio aplica).
4. El resto de la lógica (INSERT a `historial_estatus`, notificación de
   orden completa, WA de entrega) sigue igual — opera sobre el estatus
   actual/anterior, no sobre la lista fija.

Ejemplos:
- Pieza solo templado: `pendiente → en_horno → terminado → entregado`.
- Pieza canteado+taladro+templado (vidrio ya cortado por el cliente):
  `pendiente → canteado → trazo → taladro → en_horno → terminado →
  entregado`.
- Pieza corte+templado: `pendiente → en_corte → cortado → en_horno →
  terminado → entregado`.

## 4. Módulo nuevo "Maquila" (UI)

- Sidebar: nueva entrada "Maquila", visible para `comercial`, `dir_admin`,
  `dueno`, `administracion`, `desarrollo` (mismos roles que Cotizaciones).
- `app/modulos/maquila.php`, namespace `var ModMaquila = (function() {...`,
  patrón SPA estándar del proyecto (var, sin arrow functions, sin
  template literals, exposición vía `window.*`).
- Lista: folio, cliente, estatus, total — mismo estilo visual que la lista
  de Cotizaciones/Órdenes actual (tokens CSS, badges, headers gris claro).
- Botón "+ Nueva Maquila" abre formulario con:
  - Header: cliente, asesor, localidad/entrega (reuso de selector de
    cliente existente).
  - Tabla de partidas: tipo de vidrio (`maquila_tipos_vidrio`), espesor
    (dropdown mm), ancho/alto/cantidad, checkboxes de servicio (Corte /
    Canteado con selector CPB / Taladro con conteo pasados-avellanados /
    Templado), precio y subtotal calculados en vivo contra
    `maquila_precios` por espesor.
- Detalle de cotización/orden de maquila: mismo patrón que
  `cotizacion.php`/`orden.php` (ver partidas, convertir a orden, imprimir).
- Catálogo de precios: pestaña o panel dentro del mismo módulo, solo
  `dir_admin`/`dueno`, CRUD sobre `maquila_precios` agrupado por servicio
  y espesor (mismo patrón visual que el módulo Cristales). Incluye además
  CRUD simple de `maquila_tipos_vidrio`.
- Impresión: adaptar `imprimir_cotizacion.php`, `imprimir_orden.php` e
  `imprimir_etiquetas.php` para mostrar servicios/ml/perforaciones en vez
  de m²/cristal cuando `tipo='maquila'` (rama condicional, sin duplicar
  los archivos).

## 5. Integración con módulos existentes

- SmartTV / Estaciones / Operador: sin cambios de código — leen `piezas`
  por estatus tal cual; las piezas de maquila aparecen mezcladas,
  identificables por el folio `MA-S-321`.
- Finanzas VoBo/Cobranza: sin cambios — queries no filtran por `tipo`.
- Retrabajo/reproceso (`api/reproceso.php`): sin cambios, opera sobre
  `piezas` sin importar el tipo de orden.
- Reporte Dirección / Productividad / Ventas y Cobranza: quedan mezclados
  con suministro por ahora (ver "Fuera de alcance").

## Riesgos / puntos de atención para el plan de implementación

- Verificar que ningún reporte/consulta existente asuma implícitamente
  `tipo='suministro'` o dependa de un formato de folio sin el prefijo
  `MA-` (revisar parsers/regex de folio en `api/ordenes.php`, WA helper,
  portal de clientes, etc.).
- `generarFolioOrden()` sigue compartiendo el mismo contador global; no
  requiere cambios, pero confirmar que no hay unicidad rota si dos
  procesos leen `folio` sin el prefijo en algún reporte.
- Migraciones a correr en producción: 2 `ALTER TABLE` (`cotizaciones.tipo`,
  `piezas.requiere_corte`) + 3 `CREATE TABLE` nuevas — requieren
  autorización antes de ejecutar (regla del proyecto).
