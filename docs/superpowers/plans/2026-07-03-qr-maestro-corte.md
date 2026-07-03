# QR maestro de orden para registro masivo en CNC — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar un QR maestro a `imprimir_orden.php` que, al escanearse en `operador.php`, mueve todas las piezas `pendiente` de esa orden a `en_corte` de un solo golpe, con pantalla de confirmación previa.

**Architecture:** Dos endpoints PHP nuevos (`GET` para resumen, `POST` para ejecutar el cambio masivo), un QR nuevo generado del lado cliente en `imprimir_orden.php` (misma librería `qrcode.min.js` ya usada en `imprimir_etiquetas.php`), y una rama nueva en el flujo de escaneo de `operador.php` que detecta ese QR y muestra una tarjeta de confirmación en vez de la tarjeta de pieza individual.

**Tech Stack:** PHP 8.4 + PDO/MariaDB, JS vanilla (sin build step, sin frameworks), librería cliente `../lib/qrcode.min.js` (davidshimjs/qrcodejs) ya presente en el proyecto.

## Adaptación de "testing" a este proyecto

Este proyecto **no tiene suite de pruebas automatizadas** (no hay PHPUnit/Jest, `composer.json` solo trae `sentry/sentry`). El "ciclo de test" de cada tarea se adapta así:
1. `php -l archivo.php` para verificar sintaxis antes de considerar el paso terminado.
2. Verificación funcional con `curl` contra el endpoint real (este es el servidor de producción — los cambios son inmediatos, no hay "deploy" separado).
3. **Regla dura de este proyecto:** cualquier `SELECT` de verificación se ejecuta libremente. **Cualquier `INSERT`/`UPDATE`/`DELETE` (incluida la prueba end-to-end del endpoint POST) requiere confirmación explícita del usuario antes de ejecutarse**, incluso en modo de prueba. No hay base de datos de staging separada.

## Global Constraints

- Solo se toca la transición `pendiente → en_corte`. Ninguna otra transición de estatus se automatiza.
- No se agregan valores nuevos al ENUM `piezas.estatus` — se reutiliza `en_corte` ya existente.
- Seguir el patrón de permisos ya establecido en el archivo hermano `api/actualizar_estatus.php`: la restricción por estación ("solo Corte ve el botón") vive en el frontend (`operador.php`), igual que ya ocurre hoy con las demás acciones de estación — no se agrega enforcement de estación en el backend, para ser consistentes con el resto del archivo `actualizar_estatus.php` (que tampoco lo tiene).
- Todo INSERT/UPDATE en producción durante las pruebas de este plan requiere confirmación explícita del usuario antes de ejecutarse (ver sección de testing arriba).
- Los archivos nuevos de `api/` siguen exactamente el estilo de `api/pieza.php` (headers manuales + `jsonResponse()`, aunque `jsonResponse()` ya los re-setea — así lo hace el archivo existente).

---

### Task 1: Endpoint de resumen — `api/orden_masivo.php`

**Files:**
- Create: `api/orden_masivo.php`

**Interfaces:**
- Consumes: `getDB()`, `requireSessionApi()`, `jsonResponse()` de `api/config.php` / `api/permisos.php` (ya existentes, sin cambios).
- Produces: `GET api/orden_masivo.php?orden_id=<int>` → JSON `{ orden_id, folio, cliente, pendientes }` en éxito, o `{ error }` con código 400/404 en fallo. Este endpoint lo consume el Task 4 (`loadOrdenMasiva()` en `operador.php`).

- [ ] **Step 1: Crear el archivo**

```php
<?php
// ============================================================
//  APEX GLASS - API: Resumen de orden para registro masivo en CNC
//  Archivo: api/orden_masivo.php
//  Método: GET  ?orden_id=123
// ============================================================

require_once 'config.php';
require_once 'permisos.php';
requireSessionApi();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

$ordenId = (int)($_GET['orden_id'] ?? 0);
if (!$ordenId) jsonResponse(['error' => 'orden_id requerido'], 400);

$db = getDB();

$stmt = $db->prepare('SELECT id, folio, cliente_nombre FROM ordenes WHERE id = ?');
$stmt->execute([$ordenId]);
$orden = $stmt->fetch();
if (!$orden) jsonResponse(['error' => 'Orden no encontrada'], 404);

$stmt = $db->prepare("SELECT COUNT(*) FROM piezas WHERE orden_id = ? AND estatus = 'pendiente'");
$stmt->execute([$ordenId]);
$pendientes = (int)$stmt->fetchColumn();

jsonResponse([
    'orden_id'   => $orden['id'],
    'folio'      => $orden['folio'],
    'cliente'    => $orden['cliente_nombre'],
    'pendientes' => $pendientes,
]);
```

- [ ] **Step 2: Verificar sintaxis**

Run: `php -l api/orden_masivo.php`
Expected: `No syntax errors detected in api/orden_masivo.php`

- [ ] **Step 3: Verificar funcionalmente (lectura, sin riesgo — libre de ejecutar)**

Obtener un `orden_id` real de una orden activa con piezas pendientes:

```bash
mysql -u apexglass2025_usr -p apexglass2025_prod -e \
  "SELECT o.id, o.folio, COUNT(*) pendientes FROM piezas p JOIN ordenes o ON o.id=p.orden_id WHERE p.estatus='pendiente' GROUP BY o.id ORDER BY pendientes DESC LIMIT 3;"
```

Con sesión activa de un usuario válido (usar cookie de sesión del navegador o `curl -b cookies.txt` tras hacer login vía `api/login.php`), probar:

```bash
curl -s -b cookies.txt "https://apex.glass/produccion/api/orden_masivo.php?orden_id=<ID_REAL>" | python3 -m json.tool
```

Expected: JSON con `orden_id`, `folio`, `cliente`, `pendientes` (número > 0 si se usó una orden con piezas pendientes). Probar también con `orden_id=999999999` (no existe) — expected `{"error": "Orden no encontrada"}` con HTTP 404.

- [ ] **Step 4: Commit**

```bash
git add api/orden_masivo.php
git commit -m "Agregar endpoint de resumen para registro masivo en CNC"
```

---

### Task 2: Endpoint de actualización masiva — `api/actualizar_estatus_masivo.php`

**Files:**
- Create: `api/actualizar_estatus_masivo.php`

**Interfaces:**
- Consumes: `getDB()`, `requireSessionApi()`, `jsonResponse()` (sin cambios). Tabla `piezas` (`orden_id`, `estatus`), tabla `historial_estatus` (`pieza_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, notas, omision`) — mismas columnas que ya usa `api/actualizar_estatus.php:167-179`.
- Produces: `POST api/actualizar_estatus_masivo.php` con body `{ orden_id: int, usuario_id: int }` → JSON `{ ok: true, folio, actualizadas: int }`. Lo consume el Task 4 (`confirmarOrdenMasiva()` en `operador.php`).

- [ ] **Step 1: Crear el archivo**

```php
<?php
// ============================================================
//  APEX GLASS - API: Registro masivo en CNC (QR maestro de orden)
//  Archivo: api/actualizar_estatus_masivo.php
//  Método: POST { orden_id, usuario_id }
//  Efecto: todas las piezas 'pendiente' de la orden pasan a 'en_corte'
// ============================================================

require_once 'config.php';
require_once 'permisos.php';
requireSessionApi();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://apex.glass');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Método no permitido'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) jsonResponse(['error' => 'JSON inválido'], 400);

$ordenId = (int)($input['orden_id']   ?? 0);
$userId  = (int)($input['usuario_id'] ?? 0);

if (!$ordenId) jsonResponse(['error' => 'orden_id requerido'], 400);

$db = getDB();

$ord = $db->prepare('SELECT id, folio FROM ordenes WHERE id = ?');
$ord->execute([$ordenId]);
$ord = $ord->fetch();
if (!$ord) jsonResponse(['error' => 'Orden no encontrada'], 404);

$nombreUsuario = 'Desconocido';
if ($userId) {
    $u = $db->prepare('SELECT nombre FROM usuarios WHERE id = ?');
    $u->execute([$userId]);
    $u = $u->fetch();
    if ($u) $nombreUsuario = $u['nombre'];
} elseif (!empty($_SESSION['user_name'])) {
    $nombreUsuario = $_SESSION['user_name'];
}

$piezas = $db->prepare("SELECT id FROM piezas WHERE orden_id = ? AND estatus = 'pendiente'");
$piezas->execute([$ordenId]);
$piezaIds = $piezas->fetchAll(PDO::FETCH_COLUMN);

if (!$piezaIds) {
    jsonResponse(['ok' => true, 'folio' => $ord['folio'], 'actualizadas' => 0]);
}

$db->beginTransaction();
try {
    $upd  = $db->prepare("UPDATE piezas SET estatus = 'en_corte', updated_at = NOW() WHERE id = ?");
    $hist = $db->prepare('
        INSERT INTO historial_estatus
            (pieza_id, estatus_anterior, estatus_nuevo, usuario_id, usuario_nombre, notas, omision)
        VALUES (?, ?, ?, ?, ?, ?, 0)
    ');
    foreach ($piezaIds as $piezaId) {
        $upd->execute([$piezaId]);
        $hist->execute([$piezaId, 'pendiente', 'en_corte', $userId ?: null, $nombreUsuario, 'Registro masivo — QR de orden']);
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['error' => 'Error al actualizar piezas'], 500);
}

jsonResponse([
    'ok'           => true,
    'folio'        => $ord['folio'],
    'actualizadas' => count($piezaIds),
]);
```

- [ ] **Step 2: Verificar sintaxis**

Run: `php -l api/actualizar_estatus_masivo.php`
Expected: `No syntax errors detected in api/actualizar_estatus_masivo.php`

- [ ] **Step 3: Verificación con datos reales — PEDIR CONFIRMACIÓN ANTES DE EJECUTAR**

Este paso ejecuta un `UPDATE` real sobre `piezas` y un `INSERT` real en `historial_estatus`. **Antes de correrlo, pedir confirmación explícita al usuario** y, si es posible, usar una orden de prueba (columna `ordenes.es_prueba = 1`) o una orden pequeña que Armando/Mando indiquen para no afectar producción real sin querer.

Verificación previa (SELECT, libre):
```bash
mysql -u apexglass2025_usr -p apexglass2025_prod -e \
  "SELECT id, folio FROM ordenes WHERE es_prueba = 1 LIMIT 5;"
```

Con la orden de prueba elegida y sesión válida:
```bash
curl -s -b cookies.txt -X POST https://apex.glass/produccion/api/actualizar_estatus_masivo.php \
  -H "Content-Type: application/json" \
  -d '{"orden_id": <ID_PRUEBA>, "usuario_id": <USER_ID>}' | python3 -m json.tool
```

Expected: `{"ok": true, "folio": "...", "actualizadas": N}` con `N` igual al número de piezas que estaban en `pendiente`.

Verificar el resultado (SELECT, libre):
```bash
mysql -u apexglass2025_usr -p apexglass2025_prod -e \
  "SELECT estatus, COUNT(*) FROM piezas WHERE orden_id = <ID_PRUEBA> GROUP BY estatus;"
mysql -u apexglass2025_usr -p apexglass2025_prod -e \
  "SELECT * FROM historial_estatus WHERE pieza_id IN (SELECT id FROM piezas WHERE orden_id=<ID_PRUEBA>) ORDER BY created_at DESC LIMIT 5;"
```

Expected: las piezas que estaban en `pendiente` ahora están en `en_corte`; `historial_estatus` tiene un renglón nuevo por cada una con `notas = 'Registro masivo — QR de orden'`.

Repetir la misma llamada `curl` una segunda vez — expected: `{"ok": true, "folio": "...", "actualizadas": 0}` (idempotente, no rompe ni da error).

- [ ] **Step 4: Commit**

```bash
git add api/actualizar_estatus_masivo.php
git commit -m "Agregar endpoint de actualizacion masiva de estatus para QR de orden"
```

---

### Task 3: QR maestro en `app/imprimir_orden.php`

**Files:**
- Modify: `app/imprimir_orden.php:10-22` (query — agregar `o.id`)
- Modify: `app/imprimir_orden.php:34-49` (variables PHP — agregar `$ordenId`)
- Modify: `app/imprimir_orden.php:54-56` (head — agregar script de la librería QR)
- Modify: `app/imprimir_orden.php:70-74` (CSS del header)
- Modify: `app/imprimir_orden.php:160-164` (markup del header)
- Modify: `app/imprimir_orden.php:281-363` (script — inicializar el QR)

**Interfaces:**
- Consumes: `../lib/qrcode.min.js` (librería ya presente, patrón de uso igual a `app/imprimir_etiquetas.php:393-399`). Endpoint del Task 1/2 vía el QR (no llamada directa — el QR solo codifica una URL que se lee después en `operador.php`).
- Produces: un `<div id="qrMasivo">` renderizado con un QR cuyo payload es `https://apex.glass/produccion/app/operador.php?orden_masivo=<orden_id>`, visible únicamente cuando la cotización ya tiene una orden asociada (`orden_id > 0`).

- [ ] **Step 1: Agregar `o.id` a la consulta y resolver `$ordenId`**

En `app/imprimir_orden.php`, la consulta actual (líneas 10-20) selecciona `o.folio AS orden_folio` pero no `o.id`. Cambiar:

```php
$cot = $db->prepare('
    SELECT c.*,
           cl.razon_social, cl.telefono as cliente_tel,
           u.nombre as asesor_nombre_usr,
           o.folio AS orden_folio
    FROM cotizaciones c
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
    LEFT JOIN usuarios u  ON u.id  = c.asesor_id
    LEFT JOIN ordenes o   ON o.id  = c.orden_id
    WHERE c.id = ?
');
```

por:

```php
$cot = $db->prepare('
    SELECT c.*,
           cl.razon_social, cl.telefono as cliente_tel,
           u.nombre as asesor_nombre_usr,
           o.id AS orden_id_real, o.folio AS orden_folio
    FROM cotizaciones c
    LEFT JOIN clientes cl ON cl.id = c.cliente_id
    LEFT JOIN usuarios u  ON u.id  = c.asesor_id
    LEFT JOIN ordenes o   ON o.id  = c.orden_id
    WHERE c.id = ?
');
```

Justo después de `$tipoEntrega = ...` (línea 40), agregar:

```php
$ordenId = (int)($c['orden_id_real'] ?? 0);
```

- [ ] **Step 2: Incluir la librería QR en el `<head>`**

Buscar (línea ~54-56):
```html
<title>Orden de Producción <?= htmlspecialchars($folio) ?> — APEX GLASS</title>
<style>
```

Cambiar por:
```html
<title>Orden de Producción <?= htmlspecialchars($folio) ?> — APEX GLASS</title>
<script src="../lib/qrcode.min.js"></script>
<style>
```

- [ ] **Step 3: CSS del header — agregar posicionamiento del QR**

Buscar (línea 71):
```css
.header { text-align: center; border: 2px solid #000; padding: 10px; margin-bottom: 0; }
```

Cambiar por:
```css
.header { text-align: center; border: 2px solid #000; padding: 10px; margin-bottom: 0; position: relative; }
.qr-masivo { position: absolute; top: 6px; right: 8px; width: 56px; text-align: center; }
.qr-masivo canvas, .qr-masivo img { width: 56px !important; height: 56px !important; }
.qr-masivo .qr-masivo-lbl { font-size: 6px; font-weight: 700; color: #6b7280; margin-top: 1px; letter-spacing: .3px; }
```

Esto no cambia `padding`/`margin`/`border` del `.header` — el QR queda posicionado absoluto dentro de la caja existente, así que el recuadro no crece.

- [ ] **Step 4: Markup — agregar el contenedor del QR**

Buscar (líneas 161-164):
```html
  <div class="header">
    <div class="empresa">TEMPLADORA NORESTE, S. A. DE C. V.</div>
    <div class="titulo">ORDEN DE PRODUCCIÓN — TEMPLADOS</div>
  </div>
```

Cambiar por:
```html
  <div class="header">
    <div class="empresa">TEMPLADORA NORESTE, S. A. DE C. V.</div>
    <div class="titulo">ORDEN DE PRODUCCIÓN — TEMPLADOS</div>
    <?php if ($ordenId): ?>
    <div class="qr-masivo" id="qrMasivo">
      <div class="qr-masivo-lbl">ESCANEA AL<br>TERMINAR CNC</div>
    </div>
    <?php endif; ?>
  </div>
```

- [ ] **Step 5: Script — generar el QR**

Buscar (línea 282):
```js
var COT_ID = <?= (int)$id ?>;
```

Cambiar por:
```js
var COT_ID = <?= (int)$id ?>;
var ORDEN_ID_MASIVO = <?= (int)$ordenId ?>;
```

Buscar el final del script, antes de `cargarComentarios();` (línea 362):
```js
cargarComentarios();
</script>
```

Cambiar por:
```js
if (ORDEN_ID_MASIVO) {
    var qrEl = document.getElementById('qrMasivo');
    var qrBox = document.createElement('div');
    qrEl.insertBefore(qrBox, qrEl.firstChild);
    new QRCode(qrBox, {
        text: 'https://apex.glass/produccion/app/operador.php?orden_masivo=' + ORDEN_ID_MASIVO,
        width: 48,
        height: 48,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
    });
}

cargarComentarios();
</script>
```

- [ ] **Step 6: Verificar sintaxis**

Run: `php -l app/imprimir_orden.php`
Expected: `No syntax errors detected in app/imprimir_orden.php`

- [ ] **Step 7: Verificar visualmente (sin riesgo — solo lectura)**

Abrir en el navegador `https://apex.glass/produccion/app/imprimir_orden.php?id=<ID_DE_UNA_COTIZACION_YA_CONVERTIDA_A_ORDEN>` (usar un `id` de `cotizaciones` cuya orden ya exista — confirmar con `SELECT id, orden_id FROM cotizaciones WHERE orden_id IS NOT NULL LIMIT 5;`).

Expected:
- El QR aparece en la esquina superior derecha del recuadro con el título, sin que el recuadro se vea más alto o más ancho que antes.
- El texto "TEMPLADORA NORESTE, S.A. DE C.V." y el título siguen centrados.
- Con una cotización sin orden asociada (`orden_id IS NULL`), el QR no aparece (sin errores en consola).
- Probar la vista de impresión (`Ctrl+P` / botón "Imprimir") para confirmar que el QR se ve bien en modo landscape carta.

- [ ] **Step 8: Commit**

```bash
git add app/imprimir_orden.php
git commit -m "Agregar QR maestro de orden en imprimir_orden.php"
```

---

### Task 4: Detección y confirmación en `app/operador.php`

**Files:**
- Modify: `app/operador.php:369-404` (HTML — agregar tarjeta `ordenMasivaCard`)
- Modify: `app/operador.php:745-754` (`loadPieza` — detectar QR maestro)
- Modify: `app/operador.php:1076` área (agregar `loadOrdenMasiva`, `renderOrdenMasiva`, `confirmarOrdenMasiva`, `cancelarOrdenMasiva`)

**Interfaces:**
- Consumes: `GET api/orden_masivo.php?orden_id=` (Task 1), `POST api/actualizar_estatus_masivo.php` (Task 2), funciones/variables ya existentes: `session.estacion`, `session.id`, `showFeedback(tipo, icon, label, sub)`, `toast(msg, tipo)`.
- Produces: nada consumido por otras tareas — es la última pieza del flujo.

- [ ] **Step 1: HTML — agregar la tarjeta de confirmación masiva**

Buscar el cierre de `piezaCard` (líneas 400-404):
```html
      <div class="sec-row">
        <button class="btn-sec" onclick="verHistorial()">📋 Historial</button>
        <button class="btn-sec danger" onclick="reportarError()">⚠️ Reportar</button>
      </div>
    </div>

    <div class="manual-wrap">
```

Cambiar por (agregando la tarjeta nueva entre el cierre de `piezaCard` y `.manual-wrap`):
```html
      <div class="sec-row">
        <button class="btn-sec" onclick="verHistorial()">📋 Historial</button>
        <button class="btn-sec danger" onclick="reportarError()">⚠️ Reportar</button>
      </div>
    </div>

    <div class="pieza-card" id="ordenMasivaCard">
      <div class="card-head">
        <div>
          <div class="card-folio" id="omFolio">—</div>
          <div class="card-cliente" id="omCliente">—</div>
        </div>
      </div>
      <div id="omBody" style="padding:14px 0;font-size:14px;color:#cbd5e1;text-align:center"></div>
      <div class="action-wrap" id="omActions"></div>
    </div>

    <div class="manual-wrap">
```

- [ ] **Step 2: Detectar el QR maestro dentro de `loadPieza`**

Buscar (líneas 745-754):
```js
async function loadPieza(raw) {
  const qr = extraerCodigo(raw);
  try {
    const r = await fetch(API + 'pieza.php?qr=' + encodeURIComponent(qr));
    const d = await r.json();
    if (d.error) { showFeedback('err', '❌', 'QR no encontrado', qr); return; }
    pieza = d.pieza;
    renderPieza(d.pieza);
  } catch(e) { toast('Error de conexión', 'error'); }
}
```

Cambiar por:
```js
function extraerOrdenMasivo(raw) {
  try {
    const url = new URL(raw);
    const param = url.searchParams.get('orden_masivo');
    if (param) return parseInt(param, 10) || null;
  } catch(_) {}
  return null;
}

async function loadPieza(raw) {
  const ordenId = extraerOrdenMasivo(raw);
  if (ordenId) { await loadOrdenMasiva(ordenId); return; }

  const qr = extraerCodigo(raw);
  try {
    const r = await fetch(API + 'pieza.php?qr=' + encodeURIComponent(qr));
    const d = await r.json();
    if (d.error) { showFeedback('err', '❌', 'QR no encontrado', qr); return; }
    pieza = d.pieza;
    renderPieza(d.pieza);
  } catch(e) { toast('Error de conexión', 'error'); }
}
```

`loadPieza` es el único punto de entrada de las 4 rutas de escaneo (`scanLoopNative`, `scanLoopJsQR`, `procesarFotoQR`, y el flujo manual llama a `loadPieza` indirectamente vía `seleccionarPieza`), así que modificarlo aquí cubre cámara nativa, fallback jsQR y subida de foto sin tocar esos 3 sitios por separado.

- [ ] **Step 3: Agregar `loadOrdenMasiva`, `renderOrdenMasiva`, `confirmarOrdenMasiva`, `cancelarOrdenMasiva`**

Insertar después del cierre de la función `doUpdate` (después de la línea `}` que cierra `doUpdate`, justo antes de la sección `// ── Manual ──` en la línea 1110):

```js
// ── Orden masiva (QR maestro de Corte) ────────────────────
let ordenMasivaActual = null;

async function loadOrdenMasiva(ordenId) {
  const est = session.estacion || session.rol || 'admin';
  if (est !== 'corte') {
    toast('Este QR es solo para la estación de Corte', 'error');
    return;
  }
  try {
    const r = await fetch(API + 'orden_masivo.php?orden_id=' + encodeURIComponent(ordenId));
    const d = await r.json();
    if (d.error) { showFeedback('err', '❌', 'Orden no encontrada', ''); return; }
    ordenMasivaActual = d;
    renderOrdenMasiva(d);
  } catch(e) { toast('Error de conexión', 'error'); }
}

function renderOrdenMasiva(d) {
  document.getElementById('emptyState').classList.remove('show');
  document.getElementById('emptyState').style.display = 'none';
  document.getElementById('piezaCard').classList.remove('show');
  document.getElementById('ordenMasivaCard').classList.add('show');

  document.getElementById('omFolio').textContent   = d.folio;
  document.getElementById('omCliente').textContent = d.cliente || '—';

  const body   = document.getElementById('omBody');
  const acts   = document.getElementById('omActions');

  if (d.pendientes === 0) {
    body.textContent = 'Esta orden ya fue registrada en CNC';
    acts.innerHTML = '';
    return;
  }

  body.textContent = d.pendientes + (d.pendientes === 1 ? ' pieza pendiente' : ' piezas pendientes') + ' → pasarán a EN CNC';
  acts.innerHTML = '';

  const btnOk = document.createElement('button');
  btnOk.className = 'btn-action go';
  btnOk.textContent = '▶ Confirmar y registrar en CNC';
  btnOk.onclick = confirmarOrdenMasiva;

  const btnCancel = document.createElement('button');
  btnCancel.className = 'btn-sec';
  btnCancel.style.marginTop = '8px';
  btnCancel.textContent = 'Cancelar';
  btnCancel.onclick = cancelarOrdenMasiva;

  acts.appendChild(btnOk);
  acts.appendChild(btnCancel);
}

async function confirmarOrdenMasiva() {
  if (!ordenMasivaActual) return;
  const acts = document.getElementById('omActions');
  acts.innerHTML = '<span class="spin"></span>';
  try {
    const r = await fetch(API + 'actualizar_estatus_masivo.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ orden_id: ordenMasivaActual.orden_id, usuario_id: session.id })
    });
    const d = await r.json();
    if (d.ok) {
      showFeedback('ok', '✅', 'Orden ' + d.folio, d.actualizadas + ' piezas registradas en CNC');
      cancelarOrdenMasiva();
    } else {
      toast('❌ ' + (d.error || 'Error'), 'error');
    }
  } catch(e) {
    toast('❌ Error de conexión', 'error');
  }
}

function cancelarOrdenMasiva() {
  ordenMasivaActual = null;
  document.getElementById('ordenMasivaCard').classList.remove('show');
  document.getElementById('emptyState').style.display = 'flex';
}
```

- [ ] **Step 4: Verificar sintaxis PHP**

Run: `php -l app/operador.php`
Expected: `No syntax errors detected in app/operador.php`

- [ ] **Step 5: Verificar en navegador (usar la app real, sin generar cambios en BD todavía)**

1. Loguearse en `operador.php` con un usuario de estación "corte".
2. Abrir la consola del navegador y ejecutar manualmente para simular el escaneo, sin usar la cámara física todavía:
   ```js
   loadPieza('https://apex.glass/produccion/app/operador.php?orden_masivo=<ID_ORDEN_CON_PENDIENTES>')
   ```
3. Expected: aparece la tarjeta con folio, cliente, conteo de piezas pendientes, y los botones "Confirmar y registrar en CNC" / "Cancelar". La tarjeta de pieza individual y el estado vacío quedan ocultos.
4. Probar `cancelarOrdenMasiva()` — expected: vuelve a la pantalla vacía sin llamar al backend.
5. Loguearse con un usuario de OTRA estación (ej. "canteado") y repetir el paso 2 — expected: toast de error "Este QR es solo para la estación de Corte", sin mostrar la tarjeta.

- [ ] **Step 6: Verificar el flujo completo con confirmación real — PEDIR CONFIRMACIÓN ANTES DE EJECUTAR**

Con el usuario de estación "corte" y la tarjeta visible (paso 5.3), y **tras confirmación explícita del usuario para modificar datos reales**, hacer clic en "Confirmar y registrar en CNC" usando preferentemente una orden de prueba (`es_prueba = 1`).

Expected:
- Aparece el `showFeedback` verde con "Orden `<folio>`" y "`N` piezas registradas en CNC".
- La tarjeta vuelve al estado vacío.
- Escanear el mismo QR maestro de nuevo (repetir paso 2 de Step 5) — expected: "Esta orden ya fue registrada en CNC", sin botón de confirmar, sin error.
- Verificar en BD (SELECT, libre): las piezas de esa orden que estaban `pendiente` ahora están `en_corte`.

- [ ] **Step 7: Prueba física con cámara (recomendado, no bloqueante)**

Imprimir (o mostrar en pantalla) el QR maestro generado por el Task 3 para una orden real de prueba, y escanearlo con el celular/tablet que usa el encargado de corte en `operador.php`. Confirmar que el `BarcodeDetector` nativo (o el fallback jsQR) lo reconoce igual que reconoce los QR de pieza — si no lo detecta bien a 48px, aumentar `width`/`height` en el Task 3 Step 5 (ej. a 64px) y repetir esta prueba.

- [ ] **Step 8: Commit**

```bash
git add app/operador.php
git commit -m "Agregar deteccion y confirmacion de QR maestro de orden en operador.php"
```

---

## Self-Review (completado durante la redacción del plan)

- **Cobertura del spec:** QR visual sin agrandar el recuadro (Task 3), payload distinguible (Task 3/4), detección en `operador.php` sin tocar los 4 call-sites de escaneo por separado (Task 4 Step 2), pantalla de confirmación (Task 4 Step 3), re-escaneo idempotente sin error (Task 4 `renderOrdenMasiva` cuando `pendientes===0`), backend transaccional con historial por pieza (Task 2), permiso de estación en frontend (Task 4 Step 3 `loadOrdenMasiva`) — todos cubiertos.
- **Placeholders:** ninguno — todo el código de cada paso está completo y es el código real a escribir.
- **Consistencia de tipos/nombres:** `orden_id` (int) es el mismo nombre en `orden_masivo.php`, `actualizar_estatus_masivo.php` y en el JS de `operador.php`. `d.pendientes`, `d.folio`, `d.cliente`, `d.actualizadas`, `d.ok` son los mismos nombres que devuelven los endpoints de los Tasks 1 y 2.
- **Riesgo abierto documentado (spec, sección "Riesgos"):** el tamaño del QR (48px) puede necesitar ajuste tras la prueba física con cámara — cubierto explícitamente en Task 4 Step 7.
