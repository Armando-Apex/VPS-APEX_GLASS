# Facturación CFDI 4.0 — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implementar timbrado de facturas CFDI 4.0 integrado al flujo de cotizaciones/órdenes, con lectura automática de CSF para datos fiscales del cliente.

**Architecture:** Un helper PHP centralizado abstrae las llamadas a la API de facturación (Facturapi v2). El módulo SPA `facturacion.php` es el punto único de gestión. Los datos fiscales del cliente viven en la tabla `clientes` y se pre-llenan automáticamente al timbrar. La lectura del CSF usa `pdftotext` (ya instalado en VPS) + regex PHP sin dependencias externas.

**Tech Stack:** PHP 8.0, MariaDB 10.11, curl (nativo PHP), pdftotext 21.01 (poppler-utils), Facturapi v2 REST API, patrón SPA existente.

---

## Decisión de API: Facturapi vs Facturama

| Criterio | Facturapi | Facturama |
|---|---|---|
| CFDI 4.0 nativo | ✅ | ✅ |
| Documentación | Excelente (inglés/español) | Buena (español) |
| Sandbox | ✅ (API key separada) | ✅ (subdominio sandbox) |
| Autenticación | Bearer token | HTTP Basic (user:pass) |
| Precio 200-300 facturas/mes | ~$899 MXN/mes (Professional) | ~$500-750 MXN/mes (pago por factura ~$2-2.50 c/u) |
| Cancelación SAT | ✅ API | ✅ API |
| Validación RFC | ✅ endpoint `/tax_ids` | ❌ no disponible |
| PHP SDK | ✅ (requiere Composer) | ❌ |
| Curl directo | ✅ | ✅ |

**Recomendación:** Facturapi — mejor DX, validación RFC integrada, sandbox limpio.
**Si el costo importa más:** Facturama a ~$2/factura es $100-300 MXN/mes más barato.

**Este plan implementa Facturapi.** Cambiar a Facturama solo requiere reescribir `api/facturacion_helper.php`.

---

## Global Constraints

- Patrón SPA obligatorio: `var` no `const/let`, no arrow functions en onclick, `window.func` para funciones desde HTML
- No template literals (backticks) en PHP inline ni JS en SPA modules
- Todos los montos en MXN con 2 decimales
- CFDI 4.0: RFC receptor obligatorio, nombre debe coincidir con SAT, CP fiscal obligatorio, régimen fiscal obligatorio
- PHP `escapeshellarg()` en TODA llamada a `pdftotext` — evitar RCE
- Archivos CSF: validar MIME `application/pdf`, tamaño máx 5MB, nombre sanitizado con `uniqid()`
- Archivos Facturas (PDF/XML): guardados en `archivos_facturas/` fuera del webroot NO aplica (seguir patrón `archivos_ordenes/` con `.htaccess deny`)
- No borrar ENUM values existentes al hacer ALTER TABLE
- SIEMPRE `SELECT` de verificación antes de cualquier `ALTER` o `INSERT` en producción
- Próximo UPD disponible al iniciar implementación: verificar en CLAUDE.md

---

## Estructura de Archivos

### Nuevos archivos
| Archivo | Responsabilidad |
|---|---|
| `api/facturacion.php` | CRUD facturas: timbrar, cancelar, listar, descargar |
| `api/facturacion_helper.php` | Wrapper curl Facturapi v2 — única capa que conoce la API externa |
| `api/csf_parser.php` | Upload PDF CSF → `pdftotext` → regex → JSON campos fiscales |
| `app/modulos/facturacion.php` | Módulo SPA: lista facturas, modal timbrado, acciones |
| `archivos_csf/` | PDFs CSF subidos (protegido con .htaccess) |
| `archivos_facturas/` | PDFs y XMLs de facturas timbradas (protegido con .htaccess) |

### Archivos modificados
| Archivo | Cambio |
|---|---|
| BD: tabla `clientes` | +11 columnas fiscales: rfc, regimen_fiscal, cp_fiscal, uso_cfdi_default, calle_fiscal, num_exterior, num_interior, colonia_fiscal, municipio_fiscal, estado_fiscal, csf_path, facturapi_cliente_id |
| BD: nueva tabla `facturas` | Historial de CFDIs timbrados |
| `.env` | +FACTURAPI_KEY_TEST, +FACTURAPI_KEY_PROD, +FACTURAPI_MODO |
| `api/config.php` | Leer variables Facturapi del .env |
| `api/clientes.php` | Incluir campos fiscales en GET/PUT; sincronizar a Facturapi en PUT si RFC cambia |
| `app/modulos/clientes.php` | Tab "Fiscal" con datos fiscales + upload CSF |
| `app/modulos/cotizacion.php` | Botón "Facturar" cuando cotización tiene orden entregada y cliente tiene RFC |
| `app/dashboard.php` | Módulo Facturación en sidebar (esFinanzas) |

---

## Task 1: BD — Campos fiscales + tabla facturas + directorios

**Files:**
- Modify: MariaDB tabla `clientes`
- Create: MariaDB tabla `facturas`
- Create: `archivos_csf/.htaccess`
- Create: `archivos_facturas/.htaccess`

**Interfaces:**
- Produces: columnas fiscales en `clientes`, tabla `facturas` con estructura completa

- [ ] **Step 1: Verificar estado actual antes de ALTER**

```sql
-- Ejecutar en MCP MySQL o terminal:
DESCRIBE clientes;
SHOW TABLES LIKE 'facturas';
```
Expected: clientes tiene 16 columnas (hasta `updated_at`), tabla `facturas` NO existe.

- [ ] **Step 2: ALTER TABLE clientes — agregar campos fiscales**

```sql
ALTER TABLE clientes
  ADD COLUMN rfc VARCHAR(13) NULL AFTER razon_social,
  ADD COLUMN regimen_fiscal VARCHAR(10) NULL AFTER rfc,
  ADD COLUMN cp_fiscal VARCHAR(5) NULL AFTER regimen_fiscal,
  ADD COLUMN uso_cfdi_default VARCHAR(10) NULL DEFAULT 'G03' AFTER cp_fiscal,
  ADD COLUMN calle_fiscal VARCHAR(200) NULL AFTER uso_cfdi_default,
  ADD COLUMN num_exterior VARCHAR(20) NULL AFTER calle_fiscal,
  ADD COLUMN num_interior VARCHAR(20) NULL AFTER num_exterior,
  ADD COLUMN colonia_fiscal VARCHAR(200) NULL AFTER num_interior,
  ADD COLUMN municipio_fiscal VARCHAR(150) NULL AFTER colonia_fiscal,
  ADD COLUMN estado_fiscal VARCHAR(100) NULL AFTER municipio_fiscal,
  ADD COLUMN csf_path VARCHAR(300) NULL AFTER estado_fiscal,
  ADD COLUMN facturapi_cliente_id VARCHAR(30) NULL AFTER csf_path;
```

- [ ] **Step 3: Crear tabla facturas**

```sql
CREATE TABLE facturas (
  id               INT(11) NOT NULL AUTO_INCREMENT,
  cotizacion_id    INT(11) NULL,
  cliente_id       INT(11) NULL,
  facturapi_id     VARCHAR(30) NULL COMMENT 'ID en Facturapi',
  uuid             VARCHAR(36) NULL COMMENT 'Folio fiscal SAT',
  folio_numero     INT(11) NULL,
  serie            VARCHAR(10) NULL DEFAULT 'A',
  fecha_emision    DATETIME NULL,
  subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  iva              DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  moneda           VARCHAR(3) NOT NULL DEFAULT 'MXN',
  forma_pago       VARCHAR(5) NULL COMMENT '03=transferencia,01=efectivo,04=tarjeta',
  metodo_pago      VARCHAR(5) NULL COMMENT 'PUE o PPD',
  uso_cfdi         VARCHAR(10) NULL COMMENT 'G01,G03,S01...',
  receptor_rfc     VARCHAR(13) NULL,
  receptor_nombre  VARCHAR(200) NULL,
  concepto_desc    VARCHAR(300) NULL COMMENT 'Descripción del concepto principal',
  status           ENUM('valid','cancelled') NOT NULL DEFAULT 'valid',
  pdf_path         VARCHAR(300) NULL,
  xml_path         VARCHAR(300) NULL,
  cancelado_at     DATETIME NULL,
  cancelado_por    VARCHAR(100) NULL,
  cancelacion_motivo VARCHAR(5) NULL COMMENT '01,02,03,04 SAT',
  sustitucion_uuid VARCHAR(36) NULL,
  creado_por       VARCHAR(100) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_facturapi_id (facturapi_id),
  UNIQUE KEY uk_uuid (uuid),
  KEY idx_cotizacion (cotizacion_id),
  KEY idx_cliente (cliente_id),
  KEY idx_fecha (fecha_emision)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 4: Verificar tablas creadas correctamente**

```sql
DESCRIBE clientes;
-- Debe mostrar las 28 columnas incluyendo rfc, regimen_fiscal, cp_fiscal, etc.

DESCRIBE facturas;
-- Debe mostrar las 26 columnas

SHOW TABLES LIKE 'facturas';
-- Debe retornar 1 fila
```

- [ ] **Step 5: Crear directorios protegidos**

```bash
mkdir -p /home/apexglass2025/apex.glass/public_html/produccion/archivos_csf
mkdir -p /home/apexglass2025/apex.glass/public_html/produccion/archivos_facturas

cat > /home/apexglass2025/apex.glass/public_html/produccion/archivos_csf/.htaccess << 'EOF'
Deny from all
EOF

cat > /home/apexglass2025/apex.glass/public_html/produccion/archivos_facturas/.htaccess << 'EOF'
Deny from all
EOF

chmod 750 /home/apexglass2025/apex.glass/public_html/produccion/archivos_csf
chmod 750 /home/apexglass2025/apex.glass/public_html/produccion/archivos_facturas
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: BD campos fiscales clientes + tabla facturas + directorios CSF/facturas"
```

---

## Task 2: Variables de entorno y config

**Files:**
- Modify: `.env` (agregar Facturapi keys)
- Modify: `api/config.php` (leer Facturapi vars)

**Interfaces:**
- Produces: funciones `getFacturapiKey()` y `getFacturapiModo()` disponibles en `api/config.php`

- [ ] **Step 1: Agregar variables Facturapi al .env**

Agregar al final del archivo `.env` existente:
```
FACTURAPI_KEY_TEST=sk_test_XXXXXXXX  ← pegar key de sandbox Facturapi
FACTURAPI_KEY_PROD=sk_live_XXXXXXXX  ← pegar key de producción Facturapi
FACTURAPI_MODO=test
```
> **NOTA:** Armando debe obtener las keys en https://app.facturapi.io → Settings → API Keys. Empezar con MODO=test.

- [ ] **Step 2: Leer variables en config.php**

Leer `api/config.php` primero, luego agregar al final (antes del `?>`):

```php
function getFacturapiKey(): string {
    $modo = getenv('FACTURAPI_MODO') ?: 'test';
    $key  = ($modo === 'prod')
        ? (getenv('FACTURAPI_KEY_PROD') ?: '')
        : (getenv('FACTURAPI_KEY_TEST') ?: '');
    if (!$key) throw new RuntimeException('FACTURAPI_KEY no configurada en .env');
    return $key;
}

function getFacturapiModo(): string {
    return getenv('FACTURAPI_MODO') ?: 'test';
}
```

- [ ] **Step 3: Verificar que config.php carga sin errores**

```bash
php -r "require '/home/apexglass2025/apex.glass/public_html/produccion/api/config.php'; echo getFacturapiModo();"
```
Expected: `test`

- [ ] **Step 4: Commit**

```bash
git add api/config.php
git commit -m "feat: variables Facturapi en config.php (keys en .env, no en git)"
```
> El `.env` está en `.gitignore` — no se sube al repo.

---

## Task 3: Helper Facturapi (api/facturacion_helper.php)

**Files:**
- Create: `api/facturacion_helper.php`

**Interfaces:**
- Consumes: `getFacturapiKey()` de `config.php`
- Produces:
  - `facturapi_post(string $endpoint, array $body): array` → `['ok'=>bool, 'data'=>array, 'error'=>string]`
  - `facturapi_get(string $endpoint): array` → mismo formato
  - `facturapi_delete(string $endpoint, array $body=[]): array` → mismo formato
  - `facturapi_crearCliente(array $datos): array` → `['ok'=>bool, 'facturapi_id'=>string, 'error'=>string]`
  - `facturapi_actualizarCliente(string $facturapi_id, array $datos): array`
  - `facturapi_crearFactura(array $payload): array` → `['ok'=>bool, 'data'=>array, 'error'=>string]`
  - `facturapi_cancelarFactura(string $facturapi_id, string $motivo, string $sustitucion_uuid=''): array`
  - `facturapi_descargarArchivo(string $facturapi_id, string $tipo): array` → `['ok'=>bool, 'contenido'=>string, 'mime'=>string]`

- [ ] **Step 1: Crear api/facturacion_helper.php**

```php
<?php
// ============================================================
//  APEX GLASS — Helper Facturapi v2
//  Única capa que hace llamadas HTTP a Facturapi.
//  Requiere config.php cargado previamente.
// ============================================================

define('FACTURAPI_BASE', 'https://www.facturapi.io/v2');

function _facturapi_curl(string $method, string $endpoint, $body = null): array {
    $url = FACTURAPI_BASE . $endpoint;
    $key = getFacturapiKey();

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $key,
            'Content-Type: application/json',
        ],
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw   = curl_exec($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['ok' => false, 'data' => [], 'error' => 'curl: ' . $err];
    }

    $data = json_decode($raw, true) ?? [];

    if ($http >= 200 && $http < 300) {
        return ['ok' => true, 'data' => $data, 'error' => ''];
    }

    $msg = $data['message'] ?? $data['error'] ?? ('HTTP ' . $http);
    return ['ok' => false, 'data' => $data, 'error' => $msg];
}

function facturapi_post(string $endpoint, array $body): array {
    return _facturapi_curl('POST', $endpoint, $body);
}

function facturapi_get(string $endpoint): array {
    return _facturapi_curl('GET', $endpoint);
}

function facturapi_delete(string $endpoint, array $body = []): array {
    return _facturapi_curl('DELETE', $endpoint, $body ?: null);
}

// ──────────────────────────────────────────────────────────
//  CLIENTES
// ──────────────────────────────────────────────────────────

function facturapi_crearCliente(array $d): array {
    $payload = [
        'legal_name' => strtoupper(trim($d['razon_social'])),
        'tax_id'     => strtoupper(trim($d['rfc'])),
        'tax_system' => $d['regimen_fiscal'],
        'address'    => ['zip' => $d['cp_fiscal']],
        'email'      => $d['email'] ?? '',
    ];
    $r = facturapi_post('/customers', $payload);
    if (!$r['ok']) return ['ok' => false, 'facturapi_id' => '', 'error' => $r['error']];
    return ['ok' => true, 'facturapi_id' => $r['data']['id'], 'error' => ''];
}

function facturapi_actualizarCliente(string $facturapi_id, array $d): array {
    $payload = [
        'legal_name' => strtoupper(trim($d['razon_social'])),
        'tax_id'     => strtoupper(trim($d['rfc'])),
        'tax_system' => $d['regimen_fiscal'],
        'address'    => ['zip' => $d['cp_fiscal']],
        'email'      => $d['email'] ?? '',
    ];
    $r = _facturapi_curl('PUT', '/customers/' . $facturapi_id, $payload);
    return ['ok' => $r['ok'], 'error' => $r['error']];
}

// ──────────────────────────────────────────────────────────
//  FACTURAS
// ──────────────────────────────────────────────────────────

function facturapi_crearFactura(array $payload): array {
    return facturapi_post('/invoices', $payload);
}

function facturapi_cancelarFactura(string $facturapi_id, string $motivo, string $sustitucion_uuid = ''): array {
    $params = '?motive=' . urlencode($motivo);
    if ($sustitucion_uuid) $params .= '&substitution=' . urlencode($sustitucion_uuid);
    return facturapi_delete('/invoices/' . $facturapi_id . $params);
}

function facturapi_descargarArchivo(string $facturapi_id, string $tipo): array {
    // $tipo: 'pdf' o 'xml'
    $url  = FACTURAPI_BASE . '/invoices/' . $facturapi_id . '/' . $tipo;
    $key  = getFacturapiKey();
    $ch   = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
    ]);
    $raw  = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $mime = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || $http !== 200) {
        return ['ok' => false, 'contenido' => '', 'mime' => '', 'error' => $err ?: ('HTTP ' . $http)];
    }
    return ['ok' => true, 'contenido' => $raw, 'mime' => $mime, 'error' => ''];
}
```

- [ ] **Step 2: Smoke test del helper en CLI (con sandbox key ya configurada)**

```bash
php -r "
require '/home/apexglass2025/apex.glass/public_html/produccion/api/config.php';
require '/home/apexglass2025/apex.glass/public_html/produccion/api/facturacion_helper.php';
\$r = facturapi_get('/legal');
echo \$r['ok'] ? 'OK: ' . json_encode(\$r['data']) : 'ERROR: ' . \$r['error'];
"
```
Expected: `OK: {"id":"...","name":"Templadora Noreste...","is_production_ready":...}`

- [ ] **Step 3: Commit**

```bash
git add api/facturacion_helper.php
git commit -m "feat: helper Facturapi v2 (curl, sin Composer)"
```

---

## Task 4: Parser CSF (api/csf_parser.php)

**Files:**
- Create: `api/csf_parser.php`

**Interfaces:**
- Consumes: POST multipart con campo `csf_pdf` (archivo PDF)
- Produces: JSON `{ok: bool, campos: {rfc, razon_social, regimen_fiscal, regimen_desc, cp_fiscal, calle_fiscal, num_exterior, num_interior, colonia_fiscal, municipio_fiscal, estado_fiscal}, raw_text: string, error: string}`

**Notas críticas:**
- `pdftotext` instalado en `/usr/bin/pdftotext`
- SIEMPRE `escapeshellarg()` en el path del archivo
- No guardar el PDF permanentemente en este endpoint — solo parsear y retornar
- Archivo temporal en `/tmp/claude-*/scratchpad/` o `/home/apexglass2025/tmp/`

- [ ] **Step 1: Crear api/csf_parser.php**

```php
<?php
require_once 'config.php';
require_once 'permisos.php';

header('Content-Type: application/json');
$user = requireSessionApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

// ── Validar archivo subido ────────────────────────────────
if (empty($_FILES['csf_pdf']) || $_FILES['csf_pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => 'No se recibió el archivo PDF']);
    exit;
}

$f = $_FILES['csf_pdf'];

// Validar MIME real con fileinfo
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $f['tmp_name']);
finfo_close($finfo);

if ($mime !== 'application/pdf') {
    echo json_encode(['ok' => false, 'error' => 'El archivo debe ser PDF (se detectó: ' . $mime . ')']);
    exit;
}

if ($f['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => 'El archivo supera el límite de 5MB']);
    exit;
}

// ── Extraer texto con pdftotext ───────────────────────────
$tmp_path = escapeshellarg($f['tmp_name']);
$raw = shell_exec('/usr/bin/pdftotext -layout ' . $tmp_path . ' -');

if (!$raw) {
    echo json_encode(['ok' => false, 'error' => 'No se pudo extraer texto del PDF. Verifica que sea el CSF del SAT.']);
    exit;
}

// ── Parsear campos con regex ──────────────────────────────
function extraer(string $patron, string $texto, int $grupo = 1): string {
    if (preg_match($patron, $texto, $m)) {
        return trim($m[$grupo]);
    }
    return '';
}

$rfc = extraer('/RFC:\s+([A-Z&\x{00D1}]{3,4}\d{6}[A-Z0-9]{3})/u', $raw);

// Nombre: tomar hasta el fin de línea, limpiar espacios múltiples
$nombre_raw = extraer('/Nombre\s+o\s+Raz[oó]n\s+Social:\s*(.+)/iu', $raw);
$razon_social = preg_replace('/\s{2,}/', ' ', trim($nombre_raw));

// Régimen: primer match "NNN - Descripción"
$regimen_codigo = extraer('/(\d{3})\s+-\s+[^\n\r]+/', $raw);
$regimen_desc   = extraer('/\d{3}\s+-\s+([^\n\r]+)/', $raw);
$regimen_desc   = trim($regimen_desc);

// CP fiscal
$cp_fiscal = extraer('/C[oó]digo\s+Postal:\s*(\d{5})/iu', $raw);

// Domicilio
$calle        = extraer('/^Calle:\s+(.+)$/imu', $raw);
$num_ext      = extraer('/N[uú]mero\s+Exterior:\s*(.+)$/imu', $raw);
$num_int      = extraer('/N[uú]mero\s+Interior:\s*(.+)$/imu', $raw);
$colonia      = extraer('/^Colonia:\s+(.+)$/imu', $raw);
$municipio    = extraer('/Municipio[^:]*:\s+(.+)$/imu', $raw);
$estado       = extraer('/^Estado:\s+(.+)$/imu', $raw);

// Limpiar valores vacíos que son guiones o espacios
foreach (['num_int', 'calle', 'num_ext', 'colonia', 'municipio', 'estado'] as $var) {
    $$var = ($$var === '-' || $$var === '') ? '' : $$var;
}

// Verificar mínimos
if (!$rfc || !$razon_social) {
    echo json_encode([
        'ok'      => false,
        'error'   => 'No se encontró RFC o Razón Social en el PDF. Verifica que sea el CSF oficial del SAT.',
        'raw_text'=> mb_substr($raw, 0, 1000)
    ]);
    exit;
}

echo json_encode([
    'ok'     => true,
    'campos' => [
        'rfc'             => strtoupper($rfc),
        'razon_social'    => strtoupper($razon_social),
        'regimen_fiscal'  => $regimen_codigo,
        'regimen_desc'    => $regimen_desc,
        'cp_fiscal'       => $cp_fiscal,
        'calle_fiscal'    => $calle,
        'num_exterior'    => $num_ext,
        'num_interior'    => $num_int,
        'colonia_fiscal'  => $colonia,
        'municipio_fiscal'=> $municipio,
        'estado_fiscal'   => $estado,
    ],
    'error'  => ''
]);
```

- [ ] **Step 2: Test manual con un CSF real**

```bash
# Descargar un CSF de prueba del SAT y testearlo:
curl -X POST https://apex.glass/produccion/api/csf_parser.php \
  -b "PHPSESSID=<tu_session>" \
  -F "csf_pdf=@/ruta/al/CSF_prueba.pdf"
```
Expected: JSON con `ok:true` y campos extraídos.

Si no hay CSF disponible, crear un PDF de prueba con el texto mínimo:
```bash
echo "RFC:                    TAXX890101ABC
Nombre o Razón Social:  EMPRESA PRUEBA SA DE CV
Código Postal:          64000
Estado:                 NUEVO LEON
601 - General de Ley Personas Morales   01/01/2020" | \
  enscript -o - 2>/dev/null | ps2pdf - /tmp/csf_test.pdf
```

- [ ] **Step 3: Commit**

```bash
git add api/csf_parser.php
git commit -m "feat: parser CSF PDF con pdftotext + regex (RFC, razón social, régimen, domicilio fiscal)"
```

---

## Task 5: Datos fiscales en módulo Clientes

**Files:**
- Modify: `app/modulos/clientes.php` (tab Fiscal + upload CSF)
- Modify: `api/clientes.php` (incluir campos fiscales en GET/PUT + sincronizar Facturapi)

**Interfaces:**
- Consumes: `api/csf_parser.php` (upload PDF → JSON campos)
- Consumes: `api/facturacion_helper.php` (crear/actualizar cliente en Facturapi)
- Produces: campos fiscales guardados en `clientes`, `facturapi_cliente_id` actualizado

- [ ] **Step 1: Leer api/clientes.php completo**

Leer el archivo antes de editar para ver la estructura actual del GET y PUT.

- [ ] **Step 2: Agregar campos fiscales al GET en api/clientes.php**

El `SELECT * FROM clientes WHERE id = ?` ya los retorna porque `*` incluye las nuevas columnas.
Verificar que el GET de id individual funcione y retorne los nuevos campos.

En la respuesta del GET lista (`SELECT ... FROM clientes`), si hay columnas explícitas, agregar:
`rfc, regimen_fiscal, cp_fiscal, uso_cfdi_default, calle_fiscal, num_exterior, num_interior, colonia_fiscal, municipio_fiscal, estado_fiscal, facturapi_cliente_id`

- [ ] **Step 3: Agregar lógica de sincronización Facturapi al PUT**

En `api/clientes.php`, en el bloque PUT, **después** del `UPDATE clientes SET ... WHERE id = ?`, agregar:

```php
// Sincronizar a Facturapi si hay RFC y campos fiscales mínimos
$stmt_fis = $pdo->prepare("SELECT rfc, regimen_fiscal, cp_fiscal, razon_social, email, facturapi_cliente_id FROM clientes WHERE id = ?");
$stmt_fis->execute([$id]);
$cli_fis = $stmt_fis->fetch(PDO::FETCH_ASSOC);

if ($cli_fis['rfc'] && $cli_fis['regimen_fiscal'] && $cli_fis['cp_fiscal']) {
    require_once __DIR__ . '/facturacion_helper.php';
    if ($cli_fis['facturapi_cliente_id']) {
        // Actualizar en Facturapi
        facturapi_actualizarCliente($cli_fis['facturapi_cliente_id'], $cli_fis);
    } else {
        // Crear en Facturapi y guardar ID
        $r_fp = facturapi_crearCliente($cli_fis);
        if ($r_fp['ok']) {
            $pdo->prepare("UPDATE clientes SET facturapi_cliente_id = ? WHERE id = ?")
                ->execute([$r_fp['facturapi_id'], $id]);
        }
    }
}
```

> IMPORTANTE: La sincronización falla silenciosamente (no interrumpe el UPDATE de clientes). Loguear error con `error_log()` si falla.

- [ ] **Step 4: Agregar campos fiscales al array de campos actualizables en PUT**

En el bloque que construye `$sets`/`$params` del PUT, agregar los campos:
```php
'rfc', 'regimen_fiscal', 'cp_fiscal', 'uso_cfdi_default',
'calle_fiscal', 'num_exterior', 'num_interior',
'colonia_fiscal', 'municipio_fiscal', 'estado_fiscal'
```
Con sus respectivas entradas en bitácora (`clientes_bitacora`) para cambio de RFC.

- [ ] **Step 5: Leer app/modulos/clientes.php completo**

Antes de editar — identificar: dónde está el panel detalle del cliente, cómo está estructurado el formulario de edición.

- [ ] **Step 6: Agregar tab "Fiscal" en el panel detalle del cliente**

En el panel de detalle del cliente, agregar una pestaña "Fiscal" (junto a las existentes como Datos, Bitácora, etc.). Contenido del tab:

```html
<!-- Tab Fiscal — muestra campos si existen, formulario de edición y botón CSF -->
<div id="tab-fiscal" style="display:none">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <span style="font-weight:600;color:#374151">Datos Fiscales</span>
    <label style="cursor:pointer">
      <input type="file" id="csfFile" accept="application/pdf" style="display:none" onchange="window.leerCSF(this)">
      <span style="background:#7c3aed;color:#fff;padding:6px 12px;border-radius:4px;font-size:13px">Leer CSF</span>
    </label>
  </div>
  <!-- Preview CSF antes de guardar -->
  <div id="csf-preview" style="display:none;background:#fef9c3;border:1px solid #fde68a;border-radius:4px;padding:10px;margin-bottom:12px;font-size:13px">
    <strong>CSF leído — confirma los datos:</strong>
    <div id="csf-preview-campos"></div>
    <button onclick="window.guardarDatosFiscales()" style="margin-top:8px;background:#16a34a;color:#fff;border:none;padding:6px 14px;border-radius:4px;cursor:pointer">Guardar datos fiscales</button>
    <button onclick="document.getElementById('csf-preview').style.display='none'" style="margin-left:8px;background:#e5e7eb;border:none;padding:6px 14px;border-radius:4px;cursor:pointer">Cancelar</button>
  </div>
  <!-- Datos actuales -->
  <table style="width:100%;font-size:13px;border-collapse:collapse">
    <tr><td style="color:#6b7280;padding:4px 0;width:140px">RFC</td><td id="fis-rfc" style="font-weight:600;color:#1e3a5f">—</td></tr>
    <tr><td style="color:#6b7280;padding:4px 0">Razón Social</td><td id="fis-razon">—</td></tr>
    <tr><td style="color:#6b7280;padding:4px 0">Régimen Fiscal</td><td id="fis-regimen">—</td></tr>
    <tr><td style="color:#6b7280;padding:4px 0">CP Fiscal</td><td id="fis-cp">—</td></tr>
    <tr><td style="color:#6b7280;padding:4px 0">Dirección Fiscal</td><td id="fis-dir">—</td></tr>
    <tr><td style="color:#6b7280;padding:4px 0">Uso CFDI default</td><td id="fis-uso">—</td></tr>
    <tr><td style="color:#6b7280;padding:4px 0">Facturapi ID</td><td id="fis-fpid" style="font-size:11px;color:#9ca3af">—</td></tr>
  </table>
</div>
```

- [ ] **Step 7: Agregar funciones JS en el módulo clientes.php**

Las funciones usan `var` y se exponen via `window.*` (patrón SPA):

```javascript
window.leerCSF = function(input) {
    if (!input.files[0]) return;
    var fd = new FormData();
    fd.append('csf_pdf', input.files[0]);
    fetch('api/csf_parser.php', { method:'POST', body:fd, credentials:'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (!d.ok) { alert('Error al leer CSF: ' + d.error); return; }
        // Guardar en variable global temporal
        window._csfCampos = d.campos;
        // Mostrar preview
        var html = '<table style="font-size:12px;margin-top:6px">';
        var labels = {rfc:'RFC',razon_social:'Razón Social',regimen_fiscal:'Régimen',
                      cp_fiscal:'CP Fiscal',calle_fiscal:'Calle',estado_fiscal:'Estado'};
        Object.keys(labels).forEach(function(k){
            html += '<tr><td style="color:#78350f;padding:2px 8px 2px 0">' + labels[k] + '</td>' +
                    '<td style="font-weight:600">' + (d.campos[k]||'—') + '</td></tr>';
        });
        html += '</table>';
        document.getElementById('csf-preview-campos').innerHTML = html;
        document.getElementById('csf-preview').style.display = 'block';
    })
    .catch(function(){ alert('Error de red al leer CSF'); });
};

window.guardarDatosFiscales = function() {
    if (!window._csfCampos || !window._clienteIdActual) return;
    var c = window._csfCampos;
    var payload = {
        id: window._clienteIdActual,
        rfc:              c.rfc,
        razon_social:     c.razon_social,
        regimen_fiscal:   c.regimen_fiscal,
        cp_fiscal:        c.cp_fiscal,
        calle_fiscal:     c.calle_fiscal,
        num_exterior:     c.num_exterior,
        num_interior:     c.num_interior,
        colonia_fiscal:   c.colonia_fiscal,
        municipio_fiscal: c.municipio_fiscal,
        estado_fiscal:    c.estado_fiscal
    };
    fetch('api/clientes.php', {
        method:'PUT',
        headers:{'Content-Type':'application/json','X-CSRF-Token': window.csrfToken||''},
        body: JSON.stringify(payload),
        credentials:'same-origin'
    })
    .then(function(r){ return r.json(); })
    .then(function(d) {
        if (d.error) { alert('Error: ' + d.error); return; }
        document.getElementById('csf-preview').style.display = 'none';
        alert('Datos fiscales guardados correctamente.');
        // Recargar datos del cliente
        window.verCliente(window._clienteIdActual);
    });
};
```

- [ ] **Step 8: Poblar tab Fiscal al cargar detalle del cliente**

En la función que muestra el detalle del cliente (después de fetch a `api/clientes.php?id=X`), agregar:

```javascript
// Llenar tab Fiscal
var rfc = c.rfc || '';
document.getElementById('fis-rfc').textContent = rfc || '—';
document.getElementById('fis-razon').textContent = c.razon_social || '—';
document.getElementById('fis-regimen').textContent = c.regimen_fiscal ? (c.regimen_fiscal) : '—';
document.getElementById('fis-cp').textContent = c.cp_fiscal || '—';
var dir = [c.calle_fiscal, c.num_exterior, c.colonia_fiscal, c.municipio_fiscal, c.estado_fiscal]
            .filter(Boolean).join(', ');
document.getElementById('fis-dir').textContent = dir || '—';
document.getElementById('fis-uso').textContent = c.uso_cfdi_default || 'G03';
document.getElementById('fis-fpid').textContent = c.facturapi_cliente_id || '—';
window._clienteIdActual = c.id;
```

- [ ] **Step 9: Test manual end-to-end**

1. Abrir módulo Clientes en el dashboard
2. Ver un cliente → tab "Fiscal" → todos los campos en "—"
3. Click "Leer CSF" → subir PDF CSF del SAT → preview con campos extraídos
4. Click "Guardar datos fiscales" → campos guardados
5. Volver a abrir el mismo cliente → tab Fiscal muestra los datos guardados
6. Verificar en BD: `SELECT rfc, regimen_fiscal, cp_fiscal, facturapi_cliente_id FROM clientes WHERE id=X;`

- [ ] **Step 10: Commit**

```bash
git add api/clientes.php app/modulos/clientes.php
git commit -m "feat: datos fiscales en clientes + upload CSF auto-parse + sync Facturapi"
```

---

## Task 6: API Facturación (api/facturacion.php)

**Files:**
- Create: `api/facturacion.php`

**Interfaces:**
- Consumes: `facturacion_helper.php`, `config.php`, `permisos.php`
- Exposes:
  - `GET ?accion=lista` → facturas paginadas con filtros (cliente_id, fecha_desde, fecha_hasta, status)
  - `GET ?accion=detalle&id=N` → una factura
  - `POST accion=timbrar` → crear CFDI
  - `POST accion=cancelar` → cancelar CFDI
  - `GET ?accion=descargar&id=N&tipo=pdf|xml` → stream del archivo
  - `GET ?accion=resumen_mes` → total facturas + monto del mes actual

**Reglas de negocio críticas:**
- Solo `dir_admin` y `administracion` (y `dueno`) pueden timbrar y cancelar
- Cancelación motivos SAT: `01`=Comprobantes emitidos con errores con relación, `02`=Comprobantes emitidos con errores sin relación, `03`=No se llevó a cabo la operación, `04`=Operación nominativa relacionada en una factura global
- Motivo `01` requiere UUID de sustitución
- Al cancelar: guardar PDF y XML localmente si no están guardados aún

- [ ] **Step 1: Crear api/facturacion.php**

```php
<?php
require_once 'config.php';
require_once 'permisos.php';
require_once 'facturacion_helper.php';

header('Content-Type: application/json');

$user      = requireSessionApi();
$rol       = $user['rol'];
$usr_name  = $user['nombre'];
$method    = $_SERVER['REQUEST_METHOD'];
$pdo       = getDB();

$puede_facturar  = in_array($rol, ['dir_admin', 'dueno', 'administracion']);
$puede_cancelar  = in_array($rol, ['dir_admin', 'dueno']);
$puede_ver       = in_array($rol, ['dir_admin', 'dueno', 'administracion', 'comercial']);

if (!$puede_ver) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permiso']);
    exit;
}

$accion = $_GET['accion'] ?? ($_POST['accion'] ?? '');
if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true) ?? [];
    $accion = $body['accion'] ?? $accion;
}

// ── LISTA ────────────────────────────────────────────────
if ($method === 'GET' && $accion === 'lista') {
    $where  = ['1=1'];
    $params = [];

    if (!empty($_GET['cliente_id'])) {
        $where[] = 'f.cliente_id = ?'; $params[] = (int)$_GET['cliente_id'];
    }
    if (!empty($_GET['fecha_desde'])) {
        $where[] = 'DATE(f.fecha_emision) >= ?'; $params[] = $_GET['fecha_desde'];
    }
    if (!empty($_GET['fecha_hasta'])) {
        $where[] = 'DATE(f.fecha_emision) <= ?'; $params[] = $_GET['fecha_hasta'];
    }
    if (!empty($_GET['status'])) {
        $where[] = 'f.status = ?'; $params[] = $_GET['status'];
    }

    $sql = "SELECT f.*, c.nombre AS cliente_nombre_cta, cot.folio AS cotizacion_folio
            FROM facturas f
            LEFT JOIN clientes c ON c.id = f.cliente_id
            LEFT JOIN cotizaciones cot ON cot.id = f.cotizacion_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY f.created_at DESC LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['ok' => true, 'facturas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── RESUMEN MES ──────────────────────────────────────────
if ($method === 'GET' && $accion === 'resumen_mes') {
    $stmt = $pdo->query("
        SELECT COUNT(*) AS total, COALESCE(SUM(total),0) AS monto_total
        FROM facturas
        WHERE status='valid' AND YEAR(fecha_emision)=YEAR(NOW()) AND MONTH(fecha_emision)=MONTH(NOW())
    ");
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'total' => (int)$r['total'], 'monto_total' => (float)$r['monto_total']]);
    exit;
}

// ── DETALLE ──────────────────────────────────────────────
if ($method === 'GET' && $accion === 'detalle') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT f.*, c.nombre AS cliente_nombre_cta FROM facturas f LEFT JOIN clientes c ON c.id=f.cliente_id WHERE f.id=?");
    $stmt->execute([$id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $f ? json_encode(['ok' => true, 'factura' => $f]) : json_encode(['ok' => false, 'error' => 'No encontrada']);
    exit;
}

// ── DESCARGAR PDF o XML ──────────────────────────────────
if ($method === 'GET' && $accion === 'descargar') {
    $id   = (int)($_GET['id'] ?? 0);
    $tipo = ($_GET['tipo'] ?? 'pdf') === 'xml' ? 'xml' : 'pdf';

    $stmt = $pdo->prepare("SELECT facturapi_id, pdf_path, xml_path, uuid FROM facturas WHERE id=?");
    $stmt->execute([$id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$f) { echo json_encode(['ok' => false, 'error' => 'Factura no encontrada']); exit; }

    $path_col = $tipo . '_path';
    $local    = $f[$path_col];

    // Si ya está guardado localmente, servir desde disco
    if ($local && file_exists($local)) {
        $mime = $tipo === 'pdf' ? 'application/pdf' : 'application/xml';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="factura_' . ($f['uuid'] ?? $id) . '.' . $tipo . '"');
        readfile($local);
        exit;
    }

    // Descargar de Facturapi
    $r = facturapi_descargarArchivo($f['facturapi_id'], $tipo);
    if (!$r['ok']) { echo json_encode(['ok' => false, 'error' => $r['error']]); exit; }

    // Guardar localmente
    $dir      = BASE_PATH . '/archivos_facturas/';
    $filename = 'factura_' . $f['facturapi_id'] . '.' . $tipo;
    $ruta     = $dir . $filename;
    file_put_contents($ruta, $r['contenido']);
    $pdo->prepare("UPDATE facturas SET " . $tipo . "_path=? WHERE id=?")->execute([$ruta, $id]);

    header('Content-Type: ' . $r['mime']);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $r['contenido'];
    exit;
}

// ── TIMBRAR ──────────────────────────────────────────────
if ($method === 'POST' && $accion === 'timbrar') {
    if (!$puede_facturar) { http_response_code(403); echo json_encode(['error' => 'Sin permiso para timbrar']); exit; }

    $cotizacion_id  = (int)($body['cotizacion_id'] ?? 0);
    $forma_pago     = $body['forma_pago'] ?? '03';
    $metodo_pago    = $body['metodo_pago'] ?? 'PUE';
    $uso_cfdi       = $body['uso_cfdi'] ?? 'G03';
    $concepto_desc  = trim($body['concepto_desc'] ?? 'Servicio de vidrio templado y/o procesado');

    // Validar cotización
    $stmt = $pdo->prepare("
        SELECT cot.*, cli.rfc, cli.razon_social, cli.regimen_fiscal, cli.cp_fiscal,
               cli.uso_cfdi_default, cli.facturapi_cliente_id, cli.nombre AS cli_nombre
        FROM cotizaciones cot
        JOIN clientes cli ON cli.id = cot.cliente_id
        WHERE cot.id = ?
    ");
    $stmt->execute([$cotizacion_id]);
    $cot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cot) { echo json_encode(['ok' => false, 'error' => 'Cotización no encontrada']); exit; }
    if (!$cot['rfc']) { echo json_encode(['ok' => false, 'error' => 'El cliente no tiene RFC registrado. Agrega los datos fiscales primero.']); exit; }
    if (!$cot['regimen_fiscal'] || !$cot['cp_fiscal']) { echo json_encode(['ok' => false, 'error' => 'Faltan datos fiscales del cliente: régimen fiscal o CP fiscal.']); exit; }

    // Verificar que no haya factura válida ya existente para esta cotización
    $stmt2 = $pdo->prepare("SELECT id FROM facturas WHERE cotizacion_id=? AND status='valid'");
    $stmt2->execute([$cotizacion_id]);
    if ($stmt2->fetch()) { echo json_encode(['ok' => false, 'error' => 'Esta cotización ya tiene una factura válida.']); exit; }

    // Calcular totales desde partidas (fórmula canónica CLAUDE.md)
    $stmt3 = $pdo->prepare("SELECT precio_m2_usado, m2, cantidad FROM cotizaciones_partidas WHERE cotizacion_id=?");
    $stmt3->execute([$cotizacion_id]);
    $partidas = $stmt3->fetchAll(PDO::FETCH_ASSOC);

    $bruto = 0;
    foreach ($partidas as $p) {
        $bruto += (float)$p['precio_m2_usado'] * (float)$p['m2'] * (int)$p['cantidad'];
    }
    $descuento = (float)($cot['descuento'] ?? 0);
    $subtotal  = round($descuento > 0 ? $bruto * (1 - $descuento / 100) : $bruto, 2);
    $iva       = round($subtotal * 0.16, 2);
    $total     = round($subtotal * 1.16, 2);

    if ($total <= 0) { echo json_encode(['ok' => false, 'error' => 'Total calculado es $0 — verifica las partidas de la cotización.']); exit; }

    // Asegurar que el cliente existe en Facturapi
    $facturapi_cliente_id = $cot['facturapi_cliente_id'];
    if (!$facturapi_cliente_id) {
        $r_fp = facturapi_crearCliente($cot);
        if (!$r_fp['ok']) { echo json_encode(['ok' => false, 'error' => 'Error al registrar cliente en Facturapi: ' . $r_fp['error']]); exit; }
        $facturapi_cliente_id = $r_fp['facturapi_id'];
        $pdo->prepare("UPDATE clientes SET facturapi_cliente_id=? WHERE id=?")->execute([$facturapi_cliente_id, $cot['cliente_id']]);
    }

    // Forma de pago: mapa frontend → código SAT
    $forma_map = ['efectivo'=>'01','tarjeta'=>'04','transferencia'=>'03'];
    $forma_sat = $forma_map[$forma_pago] ?? $forma_pago;

    // Construir payload Facturapi
    $payload = [
        'customer'     => $facturapi_cliente_id,
        'payment_form' => $forma_sat,
        'payment_method'=> $metodo_pago,
        'use'          => $uso_cfdi,
        'items'        => [[
            'quantity'    => 1,
            'product'     => [
                'description'  => $concepto_desc,
                'product_key'  => '44121600',   // Vidrio procesado — código SAT
                'unit_key'     => 'E48',         // Unidad de servicio
                'price'        => $subtotal,
                'tax_included' => false,
                'taxes'        => [['type'=>'IVA','rate'=>0.16,'factor'=>'Tasa']],
            ],
        ]],
    ];

    $r = facturapi_crearFactura($payload);
    if (!$r['ok']) { echo json_encode(['ok' => false, 'error' => 'Facturapi: ' . $r['error']]); exit; }

    $f = $r['data'];

    // Guardar en BD
    $pdo->prepare("
        INSERT INTO facturas (cotizacion_id, cliente_id, facturapi_id, uuid, folio_numero, serie,
            fecha_emision, subtotal, iva, total, forma_pago, metodo_pago, uso_cfdi,
            receptor_rfc, receptor_nombre, concepto_desc, status, creado_por)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'valid',?)
    ")->execute([
        $cotizacion_id, $cot['cliente_id'], $f['id'], $f['uuid'] ?? '',
        $f['folio_number'] ?? null, $f['series'] ?? 'A',
        $f['date'] ?? null, $subtotal, $iva, $total,
        $forma_sat, $metodo_pago, $uso_cfdi,
        $cot['rfc'], strtoupper($cot['razon_social']),
        $concepto_desc, $usr_name
    ]);

    $factura_id = $pdo->lastInsertId();

    echo json_encode(['ok' => true, 'factura_id' => $factura_id, 'uuid' => $f['uuid'] ?? '', 'folio' => ($f['series'] ?? 'A') . ($f['folio_number'] ?? '')]);
    exit;
}

// ── CANCELAR ─────────────────────────────────────────────
if ($method === 'POST' && $accion === 'cancelar') {
    if (!$puede_cancelar) { http_response_code(403); echo json_encode(['error' => 'Solo dir_admin puede cancelar facturas']); exit; }

    $id               = (int)($body['id'] ?? 0);
    $motivo           = $body['motivo'] ?? '';
    $sustitucion_uuid = $body['sustitucion_uuid'] ?? '';

    if (!in_array($motivo, ['01','02','03','04'])) {
        echo json_encode(['ok' => false, 'error' => 'Motivo de cancelación inválido (01-04)']); exit;
    }
    if ($motivo === '01' && !$sustitucion_uuid) {
        echo json_encode(['ok' => false, 'error' => 'Motivo 01 requiere el UUID de la factura sustituta']); exit;
    }

    $stmt = $pdo->prepare("SELECT facturapi_id, status FROM facturas WHERE id=?");
    $stmt->execute([$id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$f) { echo json_encode(['ok' => false, 'error' => 'Factura no encontrada']); exit; }
    if ($f['status'] === 'cancelled') { echo json_encode(['ok' => false, 'error' => 'Factura ya cancelada']); exit; }

    $r = facturapi_cancelarFactura($f['facturapi_id'], $motivo, $sustitucion_uuid);
    if (!$r['ok']) { echo json_encode(['ok' => false, 'error' => 'Facturapi: ' . $r['error']]); exit; }

    $pdo->prepare("UPDATE facturas SET status='cancelled', cancelado_at=NOW(), cancelado_por=?, cancelacion_motivo=?, sustitucion_uuid=? WHERE id=?")
        ->execute([$usr_name, $motivo, $sustitucion_uuid ?: null, $id]);

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['error' => 'Acción no reconocida']);
```

> **NOTA sobre `BASE_PATH`:** Agregar en `config.php`:
> ```php
> define('BASE_PATH', '/home/apexglass2025/apex.glass/public_html/produccion');
> ```

- [ ] **Step 2: Verificar que config.php tiene BASE_PATH definido**

Buscar en `api/config.php` si ya existe algún define de ruta base. Si no, agregar.

- [ ] **Step 3: Test smoke timbrado en sandbox**

Con una cotización de prueba:
```bash
curl -X POST https://apex.glass/produccion/api/facturacion.php \
  -b "PHPSESSID=<tu_session>" \
  -H "Content-Type: application/json" \
  -d '{"accion":"timbrar","cotizacion_id":1,"forma_pago":"transferencia","metodo_pago":"PUE","uso_cfdi":"G03"}'
```
Expected (sandbox): `{"ok":true,"factura_id":1,"uuid":"...","folio":"A1"}`

- [ ] **Step 4: Commit**

```bash
git add api/facturacion.php api/config.php
git commit -m "feat: API facturacion.php - timbrar, cancelar, descargar, lista, resumen mes"
```

---

## Task 7: Módulo SPA Facturación (app/modulos/facturacion.php)

**Files:**
- Create: `app/modulos/facturacion.php`
- Modify: `app/dashboard.php` (sidebar + cargarModulo)

**Interfaces:**
- Consumes: `api/facturacion.php` (todas las acciones)
- Namespace: `ModFacturacion`

- [ ] **Step 1: Leer app/dashboard.php**

Identificar: dónde está el sidebar, cómo se registran módulos, patrón de `cargarModulo()`.

- [ ] **Step 2: Agregar "Facturación" al sidebar en dashboard.php**

En el bloque de Finanzas del sidebar (junto a VoBo y Cobranza), agregar:

```php
<?php if ($esFinanzas): ?>
<a href="#" onclick="cargarModulo('facturacion'); return false;" class="sidebar-link" data-mod="facturacion">
  Facturación
</a>
<?php endif; ?>
```

- [ ] **Step 3: Crear app/modulos/facturacion.php**

```php
<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requireSession();
$rol  = $user['rol'];
if (!in_array($rol, ['dir_admin','dueno','administracion','comercial'])) {
    echo '<div style="padding:24px;color:#dc2626">Sin acceso a Facturación</div>'; exit;
}
$puede_facturar = in_array($rol, ['dir_admin','dueno','administracion']);
$puede_cancelar = in_array($rol, ['dir_admin','dueno']);
?>

<div id="mod-facturacion" style="padding:24px;max-width:1100px">

  <!-- KPIs mes -->
  <div style="display:flex;gap:16px;margin-bottom:20px">
    <div class="kpi-card">
      <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Facturas este mes</div>
      <div id="fac-kpi-total" style="font-size:28px;font-weight:700;color:#1e3a5f">—</div>
    </div>
    <div class="kpi-card">
      <div style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em">Monto facturado</div>
      <div id="fac-kpi-monto" style="font-size:28px;font-weight:700;color:#1e3a5f">—</div>
    </div>
  </div>

  <!-- Filtros -->
  <div style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <label style="font-size:12px;color:#6b7280">Desde</label><br>
      <input type="date" id="fac-f-desde" style="border:1px solid #d1d5db;border-radius:4px;padding:6px 10px;font-size:13px">
    </div>
    <div>
      <label style="font-size:12px;color:#6b7280">Hasta</label><br>
      <input type="date" id="fac-f-hasta" style="border:1px solid #d1d5db;border-radius:4px;padding:6px 10px;font-size:13px">
    </div>
    <div>
      <label style="font-size:12px;color:#6b7280">Estado</label><br>
      <select id="fac-f-status" style="border:1px solid #d1d5db;border-radius:4px;padding:6px 10px;font-size:13px">
        <option value="">Todos</option>
        <option value="valid">Vigentes</option>
        <option value="cancelled">Canceladas</option>
      </select>
    </div>
    <button onclick="ModFacturacion.cargar()" style="background:#1e3a5f;color:#fff;border:none;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer">Filtrar</button>
    <?php if ($puede_facturar): ?>
    <button onclick="ModFacturacion.abrirNueva()" style="background:#16a34a;color:#fff;border:none;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer;margin-left:auto">+ Nueva Factura</button>
    <?php endif; ?>
  </div>

  <!-- Tabla -->
  <div id="fac-lista" style="font-size:13px"></div>

</div>

<!-- Modal Nueva Factura -->
<div id="fac-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1500;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:6px;width:520px;max-width:95vw;padding:24px">
    <h3 style="margin:0 0 16px;font-size:16px;color:#1e3a5f">Nueva Factura</h3>

    <div style="margin-bottom:12px">
      <label style="font-size:12px;color:#6b7280">Folio de Cotización</label>
      <div style="display:flex;gap:8px;margin-top:4px">
        <input type="text" id="fac-folio-input" placeholder="S-001" style="flex:1;border:1px solid #d1d5db;border-radius:4px;padding:7px 10px;font-size:13px">
        <button onclick="ModFacturacion.buscarCotizacion()" style="background:#6b7280;color:#fff;border:none;padding:7px 14px;border-radius:4px;font-size:13px;cursor:pointer">Buscar</button>
      </div>
    </div>
    <div id="fac-cot-info" style="display:none;background:#f0f9ff;border:1px solid #bae6fd;border-radius:4px;padding:10px;font-size:13px;margin-bottom:12px"></div>

    <div style="display:flex;gap:12px;margin-bottom:12px">
      <div style="flex:1">
        <label style="font-size:12px;color:#6b7280">Forma de pago</label>
        <select id="fac-forma-pago" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:7px 10px;font-size:13px;margin-top:4px">
          <option value="transferencia">Transferencia (03)</option>
          <option value="efectivo">Efectivo (01)</option>
          <option value="tarjeta">Tarjeta (04)</option>
        </select>
      </div>
      <div style="flex:1">
        <label style="font-size:12px;color:#6b7280">Método de pago</label>
        <select id="fac-metodo-pago" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:7px 10px;font-size:13px;margin-top:4px">
          <option value="PUE">PUE — Una sola exhibición</option>
          <option value="PPD">PPD — Parcialidades</option>
        </select>
      </div>
    </div>

    <div style="margin-bottom:12px">
      <label style="font-size:12px;color:#6b7280">Uso CFDI</label>
      <select id="fac-uso-cfdi" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:7px 10px;font-size:13px;margin-top:4px">
        <option value="G03">G03 — Gastos en general</option>
        <option value="G01">G01 — Adquisición de mercancias</option>
        <option value="S01">S01 — Sin efectos fiscales</option>
        <option value="P01">P01 — Por definir</option>
      </select>
    </div>

    <div style="margin-bottom:16px">
      <label style="font-size:12px;color:#6b7280">Concepto (descripción en factura)</label>
      <input type="text" id="fac-concepto" value="Servicio de vidrio templado y/o procesado" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:7px 10px;font-size:13px;margin-top:4px;box-sizing:border-box">
    </div>

    <div id="fac-modal-err" style="display:none;color:#dc2626;font-size:13px;margin-bottom:12px"></div>
    <div id="fac-modal-ok"  style="display:none;color:#16a34a;font-size:13px;margin-bottom:12px"></div>

    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button onclick="ModFacturacion.cerrarModal()" style="border:1px solid #d1d5db;background:#fff;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer">Cancelar</button>
      <button id="fac-btn-timbrar" onclick="ModFacturacion.timbrar()" style="background:#16a34a;color:#fff;border:none;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer">Timbrar CFDI</button>
    </div>
  </div>
</div>

<!-- Modal Cancelar -->
<div id="fac-cancel-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1500;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:6px;width:440px;max-width:95vw;padding:24px">
    <h3 style="margin:0 0 16px;font-size:16px;color:#dc2626">Cancelar Factura</h3>
    <input type="hidden" id="fac-cancel-id">
    <div style="margin-bottom:12px">
      <label style="font-size:12px;color:#6b7280">Motivo SAT</label>
      <select id="fac-cancel-motivo" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:7px 10px;font-size:13px;margin-top:4px" onchange="ModFacturacion.toggleSustitucion(this.value)">
        <option value="02">02 — Errores sin relación (el más común)</option>
        <option value="01">01 — Errores con relación (requiere UUID sustituta)</option>
        <option value="03">03 — No se llevó a cabo la operación</option>
        <option value="04">04 — Operación nominativa en factura global</option>
      </select>
    </div>
    <div id="fac-sustitucion-row" style="display:none;margin-bottom:12px">
      <label style="font-size:12px;color:#6b7280">UUID factura sustituta</label>
      <input type="text" id="fac-sustitucion-uuid" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:7px 10px;font-size:13px;margin-top:4px;box-sizing:border-box">
    </div>
    <div id="fac-cancel-err" style="display:none;color:#dc2626;font-size:13px;margin-bottom:12px"></div>
    <div style="display:flex;justify-content:flex-end;gap:10px">
      <button onclick="document.getElementById('fac-cancel-modal').style.display='none'" style="border:1px solid #d1d5db;background:#fff;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer">Cerrar</button>
      <button onclick="ModFacturacion.cancelar()" style="background:#dc2626;color:#fff;border:none;padding:8px 16px;border-radius:4px;font-size:13px;cursor:pointer">Cancelar Factura</button>
    </div>
  </div>
</div>

<script>
var ModFacturacion = (function() {
    var _cotizacionId = null;

    function init() {
        cargar();
        cargarKPIs();
    }

    function cargar() {
        var desde  = document.getElementById('fac-f-desde').value;
        var hasta  = document.getElementById('fac-f-hasta').value;
        var status = document.getElementById('fac-f-status').value;
        var url    = 'api/facturacion.php?accion=lista';
        if (desde)  url += '&fecha_desde=' + desde;
        if (hasta)  url += '&fecha_hasta=' + hasta;
        if (status) url += '&status=' + status;

        var lista = document.getElementById('fac-lista');
        lista.innerHTML = '<div style="color:#6b7280;padding:16px">Cargando...</div>';

        fetch(url, { credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d) {
            if (!d.ok || !d.facturas.length) {
                lista.innerHTML = '<div style="color:#6b7280;padding:16px">Sin facturas</div>';
                return;
            }
            var html = '<table style="width:100%;border-collapse:collapse">';
            html += '<thead><tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb">';
            ['Serie/Folio','UUID','Cliente','Fecha','Total','Estado','Acciones'].forEach(function(h){
                html += '<th style="padding:8px 10px;font-size:11px;color:#6b7280;font-weight:600;text-align:left">' + h + '</th>';
            });
            html += '</tr></thead><tbody>';
            d.facturas.forEach(function(f) {
                var color = f.status === 'valid' ? '#16a34a' : '#dc2626';
                var label = f.status === 'valid' ? 'Vigente' : 'Cancelada';
                var folio = (f.serie||'A') + (f.folio_numero||'');
                var uuid_short = f.uuid ? f.uuid.split('-')[0] + '...' : '—';
                var total = f.total ? '$' + parseFloat(f.total).toLocaleString('es-MX', {minimumFractionDigits:2}) : '—';
                html += '<tr style="border-bottom:1px solid #f3f4f6">';
                html += '<td style="padding:8px 10px;font-weight:600">' + folio + '</td>';
                html += '<td style="padding:8px 10px;font-size:11px;color:#6b7280" title="' + (f.uuid||'') + '">' + uuid_short + '</td>';
                html += '<td style="padding:8px 10px">' + (f.receptor_nombre || f.cliente_nombre_cta || '—') + '</td>';
                html += '<td style="padding:8px 10px">' + (f.fecha_emision ? f.fecha_emision.substr(0,10) : '—') + '</td>';
                html += '<td style="padding:8px 10px;font-weight:600">' + total + '</td>';
                html += '<td style="padding:8px 10px"><span style="color:' + color + ';font-weight:600">' + label + '</span></td>';
                html += '<td style="padding:8px 10px">';
                html += '<a href="api/facturacion.php?accion=descargar&id=' + f.id + '&tipo=pdf" target="_blank" style="color:#2563eb;font-size:12px;margin-right:8px">PDF</a>';
                html += '<a href="api/facturacion.php?accion=descargar&id=' + f.id + '&tipo=xml" target="_blank" style="color:#7c3aed;font-size:12px;margin-right:8px">XML</a>';
                <?php if ($puede_cancelar): ?>
                if (f.status === 'valid') {
                    html += '<button onclick="ModFacturacion.abrirCancelar(' + f.id + ')" style="border:none;background:none;color:#dc2626;font-size:12px;cursor:pointer">Cancelar</button>';
                }
                <?php endif; ?>
                html += '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            lista.innerHTML = html;
        });
    }

    function cargarKPIs() {
        fetch('api/facturacion.php?accion=resumen_mes', { credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d) {
            if (!d.ok) return;
            document.getElementById('fac-kpi-total').textContent = d.total;
            document.getElementById('fac-kpi-monto').textContent = '$' + parseFloat(d.monto_total).toLocaleString('es-MX',{minimumFractionDigits:2});
        });
    }

    function abrirNueva() {
        _cotizacionId = null;
        document.getElementById('fac-folio-input').value = '';
        document.getElementById('fac-cot-info').style.display = 'none';
        document.getElementById('fac-modal-err').style.display = 'none';
        document.getElementById('fac-modal-ok').style.display = 'none';
        document.getElementById('fac-modal').style.display = 'flex';
    }

    function cerrarModal() {
        document.getElementById('fac-modal').style.display = 'none';
    }

    function buscarCotizacion() {
        var folio = document.getElementById('fac-folio-input').value.trim();
        if (!folio) return;
        fetch('api/cotizaciones.php?folio=' + encodeURIComponent(folio), { credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d) {
            var info = document.getElementById('fac-cot-info');
            if (!d || d.error || (!d.id && !d[0])) {
                info.style.display = 'block';
                info.style.background = '#fef2f2';
                info.style.border = '1px solid #fecaca';
                info.innerHTML = 'Cotización no encontrada';
                _cotizacionId = null;
                return;
            }
            var cot = d.id ? d : d[0];
            _cotizacionId = cot.id;
            var total = cot.total ? '$' + parseFloat(cot.total).toLocaleString('es-MX',{minimumFractionDigits:2}) : '—';
            info.style.display = 'block';
            info.style.background = '#f0f9ff';
            info.style.border = '1px solid #bae6fd';
            info.innerHTML = '<strong>' + (cot.folio||'') + '</strong> &nbsp;|&nbsp; ' +
                             (cot.cliente_nombre||'') + ' &nbsp;|&nbsp; Total: <strong>' + total + '</strong>';
            // Pre-seleccionar uso CFDI del cliente si está disponible
        });
    }

    function timbrar() {
        if (!_cotizacionId) { alert('Primero busca una cotización'); return; }
        var btn = document.getElementById('fac-btn-timbrar');
        btn.disabled = true; btn.textContent = 'Timbrando...';
        var err = document.getElementById('fac-modal-err');
        var ok  = document.getElementById('fac-modal-ok');
        err.style.display = 'none'; ok.style.display = 'none';

        fetch('api/facturacion.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-Token': window.csrfToken||'' },
            body: JSON.stringify({
                accion:       'timbrar',
                cotizacion_id: _cotizacionId,
                forma_pago:   document.getElementById('fac-forma-pago').value,
                metodo_pago:  document.getElementById('fac-metodo-pago').value,
                uso_cfdi:     document.getElementById('fac-uso-cfdi').value,
                concepto_desc: document.getElementById('fac-concepto').value
            }),
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(d) {
            btn.disabled = false; btn.textContent = 'Timbrar CFDI';
            if (!d.ok) {
                err.textContent = d.error || 'Error al timbrar';
                err.style.display = 'block';
                return;
            }
            ok.textContent = 'CFDI timbrado correctamente. Folio: ' + (d.folio||'') + ' | UUID: ' + (d.uuid||'').substr(0,8) + '...';
            ok.style.display = 'block';
            setTimeout(function(){ cerrarModal(); cargar(); cargarKPIs(); }, 2500);
        })
        .catch(function() {
            btn.disabled = false; btn.textContent = 'Timbrar CFDI';
            err.textContent = 'Error de red'; err.style.display = 'block';
        });
    }

    function abrirCancelar(id) {
        document.getElementById('fac-cancel-id').value = id;
        document.getElementById('fac-cancel-motivo').value = '02';
        document.getElementById('fac-sustitucion-row').style.display = 'none';
        document.getElementById('fac-sustitucion-uuid').value = '';
        document.getElementById('fac-cancel-err').style.display = 'none';
        document.getElementById('fac-cancel-modal').style.display = 'flex';
    }

    function toggleSustitucion(motivo) {
        document.getElementById('fac-sustitucion-row').style.display = motivo === '01' ? 'block' : 'none';
    }

    function cancelar() {
        var id     = document.getElementById('fac-cancel-id').value;
        var motivo = document.getElementById('fac-cancel-motivo').value;
        var sust   = document.getElementById('fac-sustitucion-uuid').value.trim();
        var err    = document.getElementById('fac-cancel-err');
        err.style.display = 'none';

        if (!confirm('¿Confirmas la cancelación de esta factura? Esta acción no se puede deshacer.')) return;

        fetch('api/facturacion.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-Token': window.csrfToken||'' },
            body: JSON.stringify({ accion:'cancelar', id: parseInt(id), motivo: motivo, sustitucion_uuid: sust }),
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(d) {
            if (!d.ok) { err.textContent = d.error || 'Error al cancelar'; err.style.display = 'block'; return; }
            document.getElementById('fac-cancel-modal').style.display = 'none';
            cargar();
        });
    }

    return { init:init, cargar:cargar, abrirNueva:abrirNueva, cerrarModal:cerrarModal,
             buscarCotizacion:buscarCotizacion, timbrar:timbrar, abrirCancelar:abrirCancelar,
             toggleSustitucion:toggleSustitucion, cancelar:cancelar };
})();

document.addEventListener('DOMContentLoaded', ModFacturacion.init);
</script>
```

- [ ] **Step 4: Limpiar modales en cargarModulo() del dashboard**

En `app/dashboard.php`, en la función `cargarModulo()`, agregar a la lista de modales a limpiar:
```javascript
['fac-modal', 'fac-cancel-modal'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) el.remove();
});
```

- [ ] **Step 5: Test visual del módulo**

1. Abrir dashboard → Finanzas → Facturación
2. Ver KPIs (0 facturas al inicio)
3. Click "Nueva Factura" → buscar folio → seleccionar → timbrar en sandbox
4. Ver factura en lista → descargar PDF y XML
5. Cancelar factura (motivo 02) → verificar que cambia a "Cancelada"

- [ ] **Step 6: Commit**

```bash
git add app/modulos/facturacion.php app/dashboard.php
git commit -m "feat: módulo SPA Facturación - lista, timbrado, cancelación, descarga PDF/XML"
```

---

## Task 8: Botón "Facturar" desde Cotización

**Files:**
- Modify: `app/modulos/cotizacion.php`

**Interfaces:**
- Consumes: `api/facturacion.php?accion=lista&cotizacion_id=N` (verificar si ya tiene factura)
- Produces: botón "Facturar" que abre modal pre-llenado

- [ ] **Step 1: Leer app/modulos/cotizacion.php**

Identificar dónde están los botones de acción de la cotización (imprimir, enviar WA, etc.).

- [ ] **Step 2: Agregar botón Facturar en cotizacion.php**

Junto a los botones de impresión, agregar (solo si `$puede_facturar` del rol PHP):

```php
<?php if (in_array($rol, ['dir_admin','dueno','administracion'])): ?>
<button id="btn-facturar-cot" onclick="window.abrirFacturarCot()" style="background:#7c3aed;color:#fff;border:none;padding:7px 14px;border-radius:4px;font-size:13px;cursor:pointer;display:none">
  Facturar
</button>
<?php endif; ?>
```

- [ ] **Step 3: JS — verificar si ya tiene factura y mostrar/ocultar el botón**

En la función que carga el detalle de la cotización:

```javascript
// Verificar factura existente
fetch('api/facturacion.php?accion=lista&cotizacion_id=' + cotId, { credentials:'same-origin' })
.then(function(r){ return r.json(); })
.then(function(d) {
    var btn = document.getElementById('btn-facturar-cot');
    if (!btn) return;
    var tieneFactura = d.ok && d.facturas && d.facturas.some(function(f){ return f.status === 'valid'; });
    if (!tieneFactura && clienteRfc) {
        btn.style.display = 'inline-block';
    }
});
```

- [ ] **Step 4: JS — abrir modal facturación pre-llenado**

```javascript
window.abrirFacturarCot = function() {
    // Navegar al módulo facturación y abrir modal con folio pre-llenado
    cargarModulo('facturacion');
    setTimeout(function() {
        ModFacturacion.abrirNueva();
        var fi = document.getElementById('fac-folio-input');
        if (fi) { fi.value = window._cotFolioActual || ''; }
    }, 600);
};
```

- [ ] **Step 5: Test end-to-end completo**

1. Abrir una cotización con cliente que tiene RFC → ver botón "Facturar" (morado)
2. Click "Facturar" → módulo Facturación abre con folio pre-llenado
3. Click "Buscar" → muestra datos de la cotización
4. Timbrar → éxito
5. Volver a la cotización → botón "Facturar" ya no aparece (ya tiene factura válida)

- [ ] **Step 6: Commit final + tag UPD**

```bash
git add app/modulos/cotizacion.php
git commit -m "feat: botón Facturar en cotización - navega a módulo y pre-llena folio"
```

Registrar en CLAUDE.md:
- UPD-210: BD campos fiscales clientes + tabla facturas + directorios
- UPD-211: Facturapi helper curl + config .env
- UPD-212: Parser CSF PDF (pdftotext + regex)
- UPD-213: Tab Fiscal en módulo Clientes + upload CSF + sync Facturapi
- UPD-214: API facturacion.php (timbrar/cancelar/descargar/lista)
- UPD-215: Módulo SPA Facturación + sidebar
- UPD-216: Botón Facturar desde módulo Cotización

---

## Self-Review

### Cobertura de requisitos del spec
| Requisito | Task |
|---|---|
| Integración API facturación (Facturapi) | Tasks 2, 3, 6 |
| ~200-300 facturas/mes → elegir plan correcto | Decisión de API header |
| Datos fiscales en tabla clientes | Task 1 |
| Upload PDF CSF → lectura automática | Tasks 4, 5 |
| Lineamientos SAT México (CFDI 4.0) | Tasks 3, 6 — RFC, nombre, CP, régimen |
| No afectar contabilidad (validaciones) | Task 6 — validaciones antes de timbrar |

### Validaciones críticas incluidas
- RFC requerido antes de timbrar
- Régimen fiscal + CP fiscal requeridos
- No permitir doble timbrado de misma cotización
- Solo dir_admin puede cancelar
- Motivo 01 requiere UUID sustituta
- Montos calculados con fórmula canónica de CLAUDE.md (no `precio_unitario`)
- CSF: validar MIME real con fileinfo (no extensión)
- `escapeshellarg()` en llamada a pdftotext

### Checklist pre-producción (ANTES de cambiar FACTURAPI_MODO=prod)
- [ ] Timbrar 3-5 facturas en sandbox y descargar PDF/XML — verificar que sean válidos
- [ ] Cancelar una factura en sandbox — verificar respuesta
- [ ] Subir 2-3 CSFs reales de clientes — verificar extracción correcta
- [ ] Confirmar nombre/razón social en Facturapi coincide con SAT (validación RFC)
- [ ] Confirmar `producto_key` 44121600 es correcto para vidrio templado (o ajustar)
- [ ] Configurar datos del emisor en Facturapi (CSD — Certificado de Sello Digital)
- [ ] Cambiar `FACTURAPI_MODO=prod` en `.env`

---

## Prerequisitos antes de empezar (Armando debe completar)

1. **Crear cuenta en Facturapi:** https://app.facturapi.io/register
2. **Subir CSD** (archivo .cer y .key del SAT) en Facturapi → Settings → Tax info
3. **Copiar API keys** (test y producción) al `.env` del servidor
4. **Confirmar** producto SAT clave `44121600` (Vidrio plano) — o pedir a su contador la clave correcta para sus productos

