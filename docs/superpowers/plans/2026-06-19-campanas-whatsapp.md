# Módulo Campañas WhatsApp — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Módulo integrado en APEX GLASS para enviar campañas masivas de WhatsApp a clientes segmentados, recibir respuestas y chatear desde el dashboard, usando Meta Cloud API oficial.

**Architecture:** Tres archivos nuevos (api/campanas.php, api/whatsapp_webhook.php, app/modulos/campanas.php) más 4 tablas en MariaDB. APEX envía mensajes vía HTTP a Meta; Meta llama al webhook del VPS para entregar respuestas y actualizaciones de estado en tiempo real.

**Tech Stack:** PHP 8.4, MariaDB 10.11, jQuery (patrón SPA existente), Meta Cloud API v20.0 (WhatsApp Business), curl PHP para llamadas HTTP salientes.

## Global Constraints

- Patrón SPA obligatorio: namespace `var ModCampanas = (function() {`, variables internas con `var`, sin backticks, sin arrow functions en onclick inline, funciones HTML-callable expuestas vía `window.nombreFuncion`
- BD: MariaDB en `::1:3306`, base `apexglass2025_prod`, usuario `apexglass2025_usr`; usar prepared statements siempre
- `CONVERT_TZ` prohibido en queries; `created_at` ya está en hora local Monterrey
- Autenticación: siempre `requireSessionApi()` de `api/permisos.php` al inicio de cada API
- Roles con acceso: `dir_admin`, `dueno`, `comercial` (comercial no puede crear campañas masivas)
- Rate limit Meta API: máx 25 mensajes/minuto al enviar campaña
- Formato teléfono saliente: `52` + 10 dígitos, sin espacios ni guiones
- Archivos viven en `/home/apexglass2025/apex.glass/public_html/produccion/`
- Nunca escribir credenciales en el chat; van a `api/config.php` como constantes `define()`
- UPD secuencial: primer UPD disponible es UPD-111

---

## Mapa de Archivos

| Archivo | Acción | Responsabilidad |
|---------|--------|-----------------|
| `api/config.php` | Modificar | Agregar 4 constantes WA_* |
| `api/campanas.php` | Crear | 10 acciones GET/POST: CRUD campañas, envío, conversaciones, responder |
| `api/whatsapp_webhook.php` | Crear | Receptor de eventos Meta: verificación GET + eventos POST |
| `app/modulos/campanas.php` | Crear | Módulo SPA ModCampanas: Tab Campañas + Tab Conversaciones + Wizard |
| `app/dashboard.php` | Modificar | Agregar botón "Campañas" en sidebar sección Comercial |

---

## Task 0: Configuración inicial — Credenciales Meta

**Files:**
- Modify: `api/config.php`

**Objetivo:** Tener las 4 credenciales de Meta en config.php antes de tocar cualquier otro archivo. Sin esto nada funciona.

- [ ] **Paso 1: Localizar WA_TOKEN en Meta Business Manager**

  Ir a: business.facebook.com → Configuración del negocio → Usuarios → Usuarios del sistema → seleccionar tu usuario de sistema → "Generar token nuevo".
  Seleccionar la app que usa wanotifier, permisos mínimos: `whatsapp_business_messaging`, `whatsapp_business_management`. Copiar el token generado (empieza con `EAA...`).

- [ ] **Paso 2: Localizar WA_PHONE_ID**

  En Meta Business Manager → Cuentas de WhatsApp → tu cuenta → clic en el número de teléfono → copiar el **Phone Number ID** (número largo, ej: `102938475612345`).

  Alternativa si usabas wanotifier con API: busca en la configuración de wanotifier el campo "Phone Number ID" o "WABA Phone ID".

- [ ] **Paso 3: Localizar WA_APP_SECRET**

  Ir a developers.facebook.com → Mis Apps → seleccionar la app → Configuración → Básica → copiar "Clave secreta de la app" (App Secret).

- [ ] **Paso 4: Definir WA_VERIFY_TOKEN**

  Es un string secreto que tú inventas ahora mismo para validar el webhook. Usar exactamente: `apex_wh_2026`

- [ ] **Paso 5: Agregar las 4 constantes a api/config.php**

  Abrir `api/config.php`. Después de la línea `define('GOOGLE_MAPS_SERVER_KEY', '');` agregar:

  ```php
  // WhatsApp Business API (Meta Cloud API v20.0)
  define('WA_TOKEN',        'PEGAR_TOKEN_AQUI');
  define('WA_PHONE_ID',     'PEGAR_PHONE_ID_AQUI');
  define('WA_APP_SECRET',   'PEGAR_APP_SECRET_AQUI');
  define('WA_VERIFY_TOKEN', 'apex_wh_2026');
  ```

- [ ] **Paso 6: Verificar que config.php carga sin errores**

  ```bash
  php -r "require '/home/apexglass2025/apex.glass/public_html/produccion/api/config.php'; echo WA_PHONE_ID . PHP_EOL;"
  ```
  Resultado esperado: el Phone Number ID numérico sin error.

- [ ] **Paso 7: Commit**

  ```bash
  git add api/config.php
  git commit -m "config: agregar constantes WA_TOKEN/WA_PHONE_ID/WA_APP_SECRET/WA_VERIFY_TOKEN (UPD-111)"
  ```

---

## Task 1: Base de datos — 4 tablas nuevas

**Files:**
- Ningún archivo de código; solo BD

**Interfaces:**
- Produce: tablas `campanas`, `campana_envios`, `whatsapp_conversaciones`, `whatsapp_mensajes` que todas las tasks posteriores usan

- [ ] **Paso 1: Verificar que la BD está accesible**

  ```bash
  mysql -u apexglass2025_usr -p -h "::1" apexglass2025_prod -e "SHOW TABLES LIKE 'campanas';"
  ```
  Resultado esperado: tabla vacía (no existe aún).

- [ ] **Paso 2: Crear las 4 tablas**

  ```bash
  mysql -u apexglass2025_usr -p -h "::1" apexglass2025_prod << 'SQL'
  CREATE TABLE campanas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(200) NOT NULL,
    template_nombre VARCHAR(100) NOT NULL,
    template_vars_json TEXT,
    segmento_json TEXT,
    creado_por VARCHAR(100) NOT NULL,
    estado ENUM('borrador','enviando','enviada','cancelada') DEFAULT 'borrador',
    total_destinatarios INT DEFAULT 0,
    enviados INT DEFAULT 0,
    entregados INT DEFAULT 0,
    leidos INT DEFAULT 0,
    respuestas INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );

  CREATE TABLE campana_envios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campana_id INT NOT NULL,
    cliente_id INT NOT NULL,
    telefono VARCHAR(50) NOT NULL,
    mensaje_texto TEXT,
    wa_message_id VARCHAR(200),
    estado ENUM('pendiente','enviado','entregado','leido','fallido') DEFAULT 'pendiente',
    enviado_at DATETIME,
    entregado_at DATETIME,
    leido_at DATETIME,
    error_msg TEXT,
    FOREIGN KEY (campana_id) REFERENCES campanas(id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
  );

  CREATE TABLE whatsapp_conversaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    telefono VARCHAR(50) NOT NULL,
    ultima_actividad DATETIME,
    mensajes_sin_leer INT DEFAULT 0,
    estado ENUM('abierta','cerrada') DEFAULT 'abierta',
    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
  );

  CREATE TABLE whatsapp_mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversacion_id INT NOT NULL,
    campana_envio_id INT,
    direccion ENUM('outbound','inbound') NOT NULL,
    contenido TEXT NOT NULL,
    tipo ENUM('template','texto','imagen') DEFAULT 'texto',
    wa_message_id VARCHAR(200),
    enviado_por VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversacion_id) REFERENCES whatsapp_conversaciones(id),
    FOREIGN KEY (campana_envio_id) REFERENCES campana_envios(id)
  );
  SQL
  ```

- [ ] **Paso 3: Verificar las 4 tablas**

  ```bash
  mysql -u apexglass2025_usr -p -h "::1" apexglass2025_prod -e "SHOW TABLES LIKE '%campana%'; SHOW TABLES LIKE 'whatsapp%';"
  ```
  Resultado esperado: 4 filas — `campanas`, `campana_envios`, `whatsapp_conversaciones`, `whatsapp_mensajes`.

- [ ] **Paso 4: Commit**

  ```bash
  git commit -m "db: 4 tablas campanas/campana_envios/whatsapp_conversaciones/whatsapp_mensajes (UPD-111)"
  ```
  (No hay archivos PHP que agregar — el commit documenta el cambio de BD.)

---

## Task 2: Webhook — verificación GET con Meta

**Files:**
- Create: `api/whatsapp_webhook.php`

**Interfaces:**
- Produce: endpoint `GET /produccion/api/whatsapp_webhook.php` que responde al handshake de Meta
- La Task 5 completará el handler POST de este mismo archivo

- [ ] **Paso 1: Crear api/whatsapp_webhook.php con handler GET**

  ```php
  <?php
  // ============================================================
  //  APEX GLASS - WhatsApp Webhook (Meta Cloud API)
  //  Archivo: api/whatsapp_webhook.php
  // ============================================================
  require_once __DIR__ . '/config.php';

  // ── GET: verificación inicial de Meta ────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $mode      = $_GET['hub_mode']         ?? $_GET['hub.mode']         ?? '';
      $token     = $_GET['hub_verify_token'] ?? $_GET['hub.verify_token'] ?? '';
      $challenge = $_GET['hub_challenge']    ?? $_GET['hub.challenge']    ?? '';

      if ($mode === 'subscribe' && $token === WA_VERIFY_TOKEN) {
          http_response_code(200);
          echo $challenge;
          exit;
      }
      http_response_code(403);
      echo 'Forbidden';
      exit;
  }

  // ── POST: eventos de Meta (implementado en Task 5) ──────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      http_response_code(200);
      echo 'OK';
      exit;
  }

  http_response_code(405);
  ```

- [ ] **Paso 2: Verificar que el archivo existe y tiene sintaxis correcta**

  ```bash
  php -l /home/apexglass2025/apex.glass/public_html/produccion/api/whatsapp_webhook.php
  ```
  Resultado esperado: `No syntax errors detected`

- [ ] **Paso 3: Probar verificación GET manualmente**

  ```bash
  curl -s "https://apex.glass/produccion/api/whatsapp_webhook.php?hub.mode=subscribe&hub.verify_token=apex_wh_2026&hub.challenge=TESTCHALLENGE123"
  ```
  Resultado esperado: `TESTCHALLENGE123`

- [ ] **Paso 4: Registrar el webhook en Meta**

  Ir a developers.facebook.com → tu App → WhatsApp → Configuración → sección "Webhook":
  - URL de devolución de llamada: `https://apex.glass/produccion/api/whatsapp_webhook.php`
  - Token de verificación: `apex_wh_2026`
  - Clic en "Verificar y guardar" — si el paso anterior funcionó, esto pasa en verde
  - En "Campos de webhook", suscribirse a: `messages`, `message_deliveries`, `message_reads`

- [ ] **Paso 5: Commit**

  ```bash
  git add api/whatsapp_webhook.php
  git commit -m "feat: webhook whatsapp GET verification (UPD-111)"
  ```

---

## Task 3: API campanas — acciones de lectura

**Files:**
- Create: `api/campanas.php` (acciones GET: listar, detalle, clientes_segmento, conversaciones, mensajes, progreso)

**Interfaces:**
- Produce:
  - `GET ?accion=listar` → `{"campanas": [{id, nombre, estado, total_destinatarios, enviados, entregados, leidos, respuestas, created_at}]}`
  - `GET ?accion=detalle&id=N` → `{"campana": {...}, "envios": [{cliente_id, nombre_cliente, telefono, mensaje_texto, estado, enviado_at}]}`
  - `GET ?accion=clientes_segmento&localidad=LOCAL&ciudad=&activos=1` → `{"clientes": [{id, nombre, contacto, telefono, localidad, ciudad}]}`
  - `GET ?accion=progreso&id=N` → `{"enviados": N, "total": N, "estado": "enviando"}`
  - `GET ?accion=conversaciones` → `{"conversaciones": [{id, cliente_id, nombre_cliente, telefono, ultima_actividad, mensajes_sin_leer, ultimo_mensaje}]}`
  - `GET ?accion=mensajes&conversacion_id=N` → `{"mensajes": [{id, direccion, contenido, tipo, enviado_por, created_at}]}`

- [ ] **Paso 1: Crear api/campanas.php con estructura base y acciones GET**

  ```php
  <?php
  // ============================================================
  //  APEX GLASS - API: Campañas WhatsApp
  //  Archivo: api/campanas.php
  // ============================================================
  require_once 'config.php';
  require_once 'permisos.php';

  header('Content-Type: application/json; charset=utf-8');

  $user   = requireSessionApi();
  $rol    = $user['rol'];
  $db     = getDB();
  $accion = $_GET['accion'] ?? '';
  $metodo = $_SERVER['REQUEST_METHOD'];

  $esCampanas = in_array($rol, ['dir_admin','dueno','comercial']);
  $puedeEnviar = in_array($rol, ['dir_admin','dueno']);

  if (!$esCampanas) {
      http_response_code(403);
      echo json_encode(['error' => 'Sin permiso']);
      exit;
  }

  // ── Función auxiliar: normalizar teléfono a 52XXXXXXXXXX ─────
  function normalizarTelefono($tel) {
      $tel = preg_replace('/\D/', '', $tel);
      if (strlen($tel) === 10) {
          return '52' . $tel;
      }
      if (strlen($tel) === 12 && substr($tel, 0, 2) === '52') {
          return $tel;
      }
      return '52' . substr($tel, -10);
  }

  // ── GET listar campañas ──────────────────────────────────────
  if ($metodo === 'GET' && $accion === 'listar') {
      $stmt = $db->query("SELECT id, nombre, template_nombre, estado,
          total_destinatarios, enviados, entregados, leidos, respuestas, created_at
          FROM campanas ORDER BY created_at DESC");
      echo json_encode(['campanas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
      exit;
  }

  // ── GET detalle campaña ──────────────────────────────────────
  if ($metodo === 'GET' && $accion === 'detalle') {
      $id = (int)($_GET['id'] ?? 0);
      $stmt = $db->prepare("SELECT * FROM campanas WHERE id = ?");
      $stmt->execute([$id]);
      $campana = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$campana) { http_response_code(404); echo json_encode(['error'=>'No encontrada']); exit; }

      $stmt2 = $db->prepare("SELECT ce.*, c.nombre as nombre_cliente
          FROM campana_envios ce
          LEFT JOIN clientes c ON c.id = ce.cliente_id
          WHERE ce.campana_id = ?
          ORDER BY c.nombre ASC");
      $stmt2->execute([$id]);
      echo json_encode(['campana' => $campana, 'envios' => $stmt2->fetchAll(PDO::FETCH_ASSOC)]);
      exit;
  }

  // ── GET clientes por segmento ────────────────────────────────
  if ($metodo === 'GET' && $accion === 'clientes_segmento') {
      $localidad = $_GET['localidad'] ?? '';
      $ciudad    = trim($_GET['ciudad'] ?? '');
      $soloActivos = (int)($_GET['activos'] ?? 1);

      $where = ['telefono IS NOT NULL', "telefono != ''"];
      $params = [];

      if ($soloActivos) {
          $where[] = 'activo = 1';
      }
      if ($localidad === 'LOCAL' || $localidad === 'FORANEO') {
          $where[] = 'localidad = ?';
          $params[] = strtolower($localidad);
      }
      if ($ciudad !== '') {
          $where[] = 'ciudad LIKE ?';
          $params[] = '%' . $ciudad . '%';
      }

      $sql = "SELECT id, nombre, contacto, telefono, localidad, ciudad
              FROM clientes WHERE " . implode(' AND ', $where) . " ORDER BY nombre ASC";
      $stmt = $db->prepare($sql);
      $stmt->execute($params);
      echo json_encode(['clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
      exit;
  }

  // ── GET progreso de envío ────────────────────────────────────
  if ($metodo === 'GET' && $accion === 'progreso') {
      $id = (int)($_GET['id'] ?? 0);
      $stmt = $db->prepare("SELECT estado, total_destinatarios, enviados FROM campanas WHERE id = ?");
      $stmt->execute([$id]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) { http_response_code(404); echo json_encode(['error'=>'No encontrada']); exit; }
      echo json_encode(['estado' => $row['estado'], 'total' => (int)$row['total_destinatarios'], 'enviados' => (int)$row['enviados']]);
      exit;
  }

  // ── GET conversaciones ───────────────────────────────────────
  if ($metodo === 'GET' && $accion === 'conversaciones') {
      $stmt = $db->query("
          SELECT wc.id, wc.cliente_id, c.nombre as nombre_cliente, wc.telefono,
                 wc.ultima_actividad, wc.mensajes_sin_leer, wc.estado,
                 (SELECT contenido FROM whatsapp_mensajes wm
                  WHERE wm.conversacion_id = wc.id
                  ORDER BY wm.created_at DESC LIMIT 1) as ultimo_mensaje
          FROM whatsapp_conversaciones wc
          LEFT JOIN clientes c ON c.id = wc.cliente_id
          ORDER BY wc.ultima_actividad DESC");
      echo json_encode(['conversaciones' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
      exit;
  }

  // ── GET mensajes de conversación ─────────────────────────────
  if ($metodo === 'GET' && $accion === 'mensajes') {
      $cid = (int)($_GET['conversacion_id'] ?? 0);
      $stmt = $db->prepare("SELECT id, direccion, contenido, tipo, enviado_por, created_at
          FROM whatsapp_mensajes WHERE conversacion_id = ? ORDER BY created_at ASC");
      $stmt->execute([$cid]);
      echo json_encode(['mensajes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
      exit;
  }

  http_response_code(400);
  echo json_encode(['error' => 'Accion no reconocida']);
  ```

- [ ] **Paso 2: Verificar sintaxis**

  ```bash
  php -l /home/apexglass2025/apex.glass/public_html/produccion/api/campanas.php
  ```
  Resultado esperado: `No syntax errors detected`

- [ ] **Paso 3: Probar GET listar desde navegador/curl autenticado**

  Desde el navegador con sesión iniciada en APEX (o con curl con la cookie de sesión):
  ```
  https://apex.glass/produccion/api/campanas.php?accion=listar
  ```
  Resultado esperado: `{"campanas":[]}` (array vacío, tabla recién creada)

- [ ] **Paso 4: Probar GET clientes_segmento**

  ```
  https://apex.glass/produccion/api/campanas.php?accion=clientes_segmento&activos=1
  ```
  Resultado esperado: JSON con ~160 clientes que tienen teléfono.

- [ ] **Paso 5: Commit**

  ```bash
  git add api/campanas.php
  git commit -m "feat: api/campanas.php acciones GET lectura (UPD-111)"
  ```

---

## Task 4: API campanas — acciones de escritura y envío

**Files:**
- Modify: `api/campanas.php` (agregar acciones POST: crear, enviar, responder, marcar_leido)

**Interfaces:**
- Consume: constantes `WA_TOKEN`, `WA_PHONE_ID` de config.php; función `normalizarTelefono()` definida en Task 3
- Produce:
  - `POST accion=crear` body: `{nombre, template_nombre, template_vars_json, segmento_json, cliente_ids[]}` → `{"id": N, "ok": true}`
  - `POST accion=enviar` body: `{campana_id: N}` → dispara envíos y retorna `{"iniciado": true}`
  - `POST accion=responder` body: `{conversacion_id: N, mensaje: "texto"}` → `{"ok": true, "wa_message_id": "..."}`
  - `POST accion=marcar_leido` body: `{conversacion_id: N}` → `{"ok": true}`

- [ ] **Paso 1: Agregar función auxiliar enviarMensajeWA() al archivo**

  Abrir `api/campanas.php`. Después de la función `normalizarTelefono()` agregar:

  ```php
  // ── Función: enviar mensaje a Meta Cloud API ──────────────────
  function enviarMensajeWA($payload) {
      $url = 'https://graph.facebook.com/v20.0/' . WA_PHONE_ID . '/messages';
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
          'Authorization: Bearer ' . WA_TOKEN,
          'Content-Type: application/json'
      ]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
      $resp = curl_exec($ch);
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      $data = json_decode($resp, true);
      return ['code' => $code, 'data' => $data];
  }
  ```

- [ ] **Paso 2: Agregar acción POST crear**

  Antes de la línea `http_response_code(400);` final, agregar:

  ```php
  // ── POST crear campaña ───────────────────────────────────────
  if ($metodo === 'POST' && $accion === 'crear') {
      if (!$puedeEnviar) { http_response_code(403); echo json_encode(['error'=>'Sin permiso']); exit; }
      $body = json_decode(file_get_contents('php://input'), true);

      $nombre       = trim($body['nombre'] ?? '');
      $template     = trim($body['template_nombre'] ?? '');
      $varsJson     = json_encode($body['template_vars'] ?? []);
      $segmentoJson = json_encode($body['segmento'] ?? []);
      $clienteIds   = $body['cliente_ids'] ?? [];

      if (!$nombre || !$template || !$clienteIds) {
          http_response_code(400); echo json_encode(['error'=>'Faltan campos']); exit;
      }

      $db->beginTransaction();
      $stmt = $db->prepare("INSERT INTO campanas
          (nombre, template_nombre, template_vars_json, segmento_json, creado_por, total_destinatarios)
          VALUES (?, ?, ?, ?, ?, ?)");
      $stmt->execute([$nombre, $template, $varsJson, $segmentoJson, $user['username'], count($clienteIds)]);
      $campanaId = $db->lastInsertId();

      $stmtCli = $db->prepare("SELECT id, nombre, telefono FROM clientes WHERE id = ?");
      $stmtIns = $db->prepare("INSERT INTO campana_envios (campana_id, cliente_id, telefono) VALUES (?, ?, ?)");
      foreach ($clienteIds as $cid) {
          $stmtCli->execute([(int)$cid]);
          $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);
          if ($cli && $cli['telefono']) {
              $tel = normalizarTelefono($cli['telefono']);
              $stmtIns->execute([$campanaId, $cli['id'], $tel]);
          }
      }
      $db->commit();
      echo json_encode(['ok' => true, 'id' => $campanaId]);
      exit;
  }

  // ── POST enviar campaña ──────────────────────────────────────
  if ($metodo === 'POST' && $accion === 'enviar') {
      if (!$puedeEnviar) { http_response_code(403); echo json_encode(['error'=>'Sin permiso']); exit; }
      set_time_limit(600); // hasta 10 min para 160 clientes a 25/min
      $body = json_decode(file_get_contents('php://input'), true);
      $campanaId = (int)($body['campana_id'] ?? 0);

      $stmtC = $db->prepare("SELECT * FROM campanas WHERE id = ? AND estado IN ('borrador','cancelada')");
      $stmtC->execute([$campanaId]);
      $campana = $stmtC->fetch(PDO::FETCH_ASSOC);
      if (!$campana) { http_response_code(400); echo json_encode(['error'=>'Campaña no válida']); exit; }

      $db->prepare("UPDATE campanas SET estado='enviando' WHERE id=?")->execute([$campanaId]);

      $stmtE = $db->prepare("SELECT ce.id, ce.telefono, c.nombre as nombre_cliente
          FROM campana_envios ce
          LEFT JOIN clientes c ON c.id = ce.cliente_id
          WHERE ce.campana_id = ? AND ce.estado = 'pendiente'");
      $stmtE->execute([$campanaId]);
      $envios = $stmtE->fetchAll(PDO::FETCH_ASSOC);

      $vars = json_decode($campana['template_vars_json'], true) ?? [];
      $stmtUpd = $db->prepare("UPDATE campana_envios SET estado=?, wa_message_id=?, enviado_at=NOW(), error_msg=? WHERE id=?");
      $stmtCnt = $db->prepare("UPDATE campanas SET enviados = enviados + 1 WHERE id=?");

      $enviados   = 0;
      $inicioMin  = time();

      foreach ($envios as $envio) {
          // Rate limit: 25/min
          if ($enviados > 0 && $enviados % 25 === 0) {
              $transcurrido = time() - $inicioMin;
              if ($transcurrido < 60) {
                  sleep(60 - $transcurrido);
              }
              $inicioMin = time();
          }

          // Construir parámetros del template sustituyendo {{nombre_cliente}}
          $parametros = [];
          foreach ($vars as $var) {
              $valor = $var;
              if ($var === '{{nombre_cliente}}') {
                  $valor = $envio['nombre_cliente'] ?? 'Cliente';
              }
              $parametros[] = ['type' => 'text', 'text' => $valor];
          }

          $payload = [
              'messaging_product' => 'whatsapp',
              'to' => $envio['telefono'],
              'type' => 'template',
              'template' => [
                  'name' => $campana['template_nombre'],
                  'language' => ['code' => 'es_MX'],
                  'components' => [['type' => 'body', 'parameters' => $parametros]]
              ]
          ];

          $res = enviarMensajeWA($payload);
          $waId  = $res['data']['messages'][0]['id'] ?? null;
          $error = ($res['code'] !== 200) ? substr(json_encode($res['data']), 0, 255) : null;
          $nuevoEstado = $waId ? 'enviado' : 'fallido';

          $stmtUpd->execute([$nuevoEstado, $waId, $error, $envio['id']]);
          if ($waId) { $stmtCnt->execute([$campanaId]); }
          $enviados++;
      }

      $db->prepare("UPDATE campanas SET estado='enviada' WHERE id=?")->execute([$campanaId]);
      echo json_encode(['ok' => true, 'enviados' => $enviados]);
      exit;
  }

  // ── POST responder en conversación ───────────────────────────
  if ($metodo === 'POST' && $accion === 'responder') {
      $body = json_decode(file_get_contents('php://input'), true);
      $convId  = (int)($body['conversacion_id'] ?? 0);
      $mensaje = trim($body['mensaje'] ?? '');
      if (!$convId || !$mensaje) { http_response_code(400); echo json_encode(['error'=>'Faltan campos']); exit; }

      $stmtConv = $db->prepare("SELECT * FROM whatsapp_conversaciones WHERE id = ?");
      $stmtConv->execute([$convId]);
      $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);
      if (!$conv) { http_response_code(404); echo json_encode(['error'=>'Conversación no encontrada']); exit; }

      $payload = [
          'messaging_product' => 'whatsapp',
          'to' => $conv['telefono'],
          'type' => 'text',
          'text' => ['body' => $mensaje]
      ];
      $res = enviarMensajeWA($payload);
      if ($res['code'] !== 200) {
          http_response_code(502);
          echo json_encode(['error' => 'Error Meta API', 'detalle' => $res['data']]);
          exit;
      }

      $waId = $res['data']['messages'][0]['id'] ?? null;
      $stmtMsg = $db->prepare("INSERT INTO whatsapp_mensajes
          (conversacion_id, direccion, contenido, tipo, wa_message_id, enviado_por)
          VALUES (?, 'outbound', ?, 'texto', ?, ?)");
      $stmtMsg->execute([$convId, $mensaje, $waId, $user['username']]);

      $db->prepare("UPDATE whatsapp_conversaciones SET ultima_actividad=NOW() WHERE id=?")->execute([$convId]);
      echo json_encode(['ok' => true, 'wa_message_id' => $waId]);
      exit;
  }

  // ── POST marcar conversación como leída ──────────────────────
  if ($metodo === 'POST' && $accion === 'marcar_leido') {
      $body = json_decode(file_get_contents('php://input'), true);
      $convId = (int)($body['conversacion_id'] ?? 0);
      $db->prepare("UPDATE whatsapp_conversaciones SET mensajes_sin_leer=0 WHERE id=?")->execute([$convId]);
      echo json_encode(['ok' => true]);
      exit;
  }
  ```

- [ ] **Paso 3: Verificar sintaxis**

  ```bash
  php -l /home/apexglass2025/apex.glass/public_html/produccion/api/campanas.php
  ```
  Resultado esperado: `No syntax errors detected`

- [ ] **Paso 4: Prueba manual — crear campaña de prueba**

  Desde la consola del navegador (con sesión dir_admin activa), ejecutar en devtools:
  ```javascript
  fetch('/produccion/api/campanas.php?accion=crear', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      nombre: 'Test Campaign',
      template_nombre: 'hello_world',
      template_vars: [],
      segmento: {localidad: 'TODOS'},
      cliente_ids: [1]
    })
  }).then(r => r.json()).then(console.log);
  ```
  Resultado esperado: `{ok: true, id: 1}`

  Verificar en BD:
  ```bash
  mysql -u apexglass2025_usr -p -h "::1" apexglass2025_prod -e "SELECT * FROM campanas; SELECT * FROM campana_envios;"
  ```

- [ ] **Paso 5: Commit**

  ```bash
  git add api/campanas.php
  git commit -m "feat: api/campanas.php acciones POST crear/enviar/responder/marcar_leido (UPD-111)"
  ```

---

## Task 5: Webhook — handler POST completo

**Files:**
- Modify: `api/whatsapp_webhook.php` (reemplazar el POST stub con handler completo)

**Interfaces:**
- Consume: tablas `whatsapp_conversaciones`, `whatsapp_mensajes`, `campana_envios`, `campanas`
- Produce: procesa eventos `messages` (inbound) y `statuses` (delivered/read/failed) de Meta

- [ ] **Paso 1: Reemplazar el stub POST en api/whatsapp_webhook.php**

  Abrir `api/whatsapp_webhook.php`. Reemplazar el bloque POST completo:

  ```php
  // ── POST: eventos de Meta ────────────────────────────────────
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      // Validar firma X-Hub-Signature-256
      $rawBody   = file_get_contents('php://input');
      $sigHeader = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
      $expected  = 'sha256=' . hash_hmac('sha256', $rawBody, WA_APP_SECRET);
      if (!hash_equals($expected, $sigHeader)) {
          http_response_code(401);
          exit;
      }

      $data  = json_decode($rawBody, true);
      $db    = getDB();

      foreach (($data['entry'] ?? []) as $entry) {
          foreach (($entry['changes'] ?? []) as $change) {
              $value = $change['value'] ?? [];

              // ── Mensajes inbound (cliente escribió) ──────────
              foreach (($value['messages'] ?? []) as $msg) {
                  $telefono = $msg['from'] ?? '';
                  $waId     = $msg['id']   ?? '';
                  $tipo     = $msg['type'] ?? 'texto';
                  $contenido = '';

                  if ($tipo === 'text') {
                      $contenido = $msg['text']['body'] ?? '';
                      $tipo = 'texto';
                  } elseif ($tipo === 'image') {
                      $contenido = '[Imagen recibida]';
                      $tipo = 'imagen';
                  } else {
                      $contenido = '[Mensaje tipo: ' . $tipo . ']';
                      $tipo = 'texto';
                  }

                  // Buscar o crear conversación
                  $stmtConv = $db->prepare("SELECT id FROM whatsapp_conversaciones WHERE telefono = ?");
                  $stmtConv->execute([$telefono]);
                  $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);

                  if (!$conv) {
                      // Intentar asociar a cliente por teléfono
                      $stmtCli = $db->prepare("SELECT id FROM clientes WHERE REGEXP_REPLACE(telefono,'[^0-9]','') LIKE ?");
                      $stmtCli->execute(['%' . substr($telefono, -10)]);
                      $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);
                      $clienteId = $cli ? $cli['id'] : null;

                      $db->prepare("INSERT INTO whatsapp_conversaciones (cliente_id, telefono, ultima_actividad) VALUES (?,?,NOW())")
                         ->execute([$clienteId, $telefono]);
                      $convId = $db->lastInsertId();
                  } else {
                      $convId = $conv['id'];
                  }

                  // Asociar a envío de campaña si existe
                  $stmtEnv = $db->prepare("SELECT id, campana_id FROM campana_envios WHERE telefono = ? AND estado IN ('enviado','entregado','leido') ORDER BY enviado_at DESC LIMIT 1");
                  $stmtEnv->execute([$telefono]);
                  $envio = $stmtEnv->fetch(PDO::FETCH_ASSOC);
                  $campanaEnvioId = $envio ? $envio['id'] : null;

                  // Insertar mensaje
                  $db->prepare("INSERT INTO whatsapp_mensajes (conversacion_id, campana_envio_id, direccion, contenido, tipo, wa_message_id) VALUES (?,?,'inbound',?,?,?)")
                     ->execute([$convId, $campanaEnvioId, $contenido, $tipo, $waId]);

                  // Incrementar sin_leer y actualizar conversación
                  $db->prepare("UPDATE whatsapp_conversaciones SET mensajes_sin_leer = mensajes_sin_leer + 1, ultima_actividad = NOW() WHERE id=?")
                     ->execute([$convId]);

                  // Incrementar contador de respuestas en campaña
                  if ($envio) {
                      $db->prepare("UPDATE campanas SET respuestas = respuestas + 1 WHERE id=?")
                         ->execute([$envio['campana_id']]);
                  }
              }

              // ── Actualizaciones de estado (entregado/leído/fallido) ──
              foreach (($value['statuses'] ?? []) as $status) {
                  $waId   = $status['id']     ?? '';
                  $estado = $status['status'] ?? '';

                  $mapa = ['sent' => 'enviado', 'delivered' => 'entregado', 'read' => 'leido', 'failed' => 'fallido'];
                  $nuevoEstado = $mapa[$estado] ?? null;
                  if (!$nuevoEstado || !$waId) continue;

                  $stmtEnv = $db->prepare("SELECT id, campana_id, estado FROM campana_envios WHERE wa_message_id = ?");
                  $stmtEnv->execute([$waId]);
                  $envio = $stmtEnv->fetch(PDO::FETCH_ASSOC);
                  if (!$envio) continue;

                  // Solo avanzar hacia adelante: pendiente→enviado→entregado→leido
                  $orden = ['pendiente'=>0,'enviado'=>1,'entregado'=>2,'leido'=>3,'fallido'=>4];
                  $actualIdx = $orden[$envio['estado']] ?? 0;
                  $nuevoIdx  = $orden[$nuevoEstado] ?? 0;
                  if ($nuevoIdx <= $actualIdx && $nuevoEstado !== 'fallido') continue;

                  $campoFecha = ['entregado'=>'entregado_at','leido'=>'leido_at'];
                  $sqlFecha   = isset($campoFecha[$nuevoEstado]) ? ', ' . $campoFecha[$nuevoEstado] . '=NOW()' : '';

                  $db->prepare("UPDATE campana_envios SET estado=?" . $sqlFecha . " WHERE id=?")
                     ->execute([$nuevoEstado, $envio['id']]);

                  // Actualizar contadores en campaña
                  $campoContador = ['entregado'=>'entregados','leido'=>'leidos'];
                  if (isset($campoContador[$nuevoEstado])) {
                      $col = $campoContador[$nuevoEstado];
                      $db->prepare("UPDATE campanas SET $col = $col + 1 WHERE id=?")
                         ->execute([$envio['campana_id']]);
                  }
              }
          }
      }

      http_response_code(200);
      echo 'OK';
      exit;
  }
  ```

- [ ] **Paso 2: Verificar sintaxis**

  ```bash
  php -l /home/apexglass2025/apex.glass/public_html/produccion/api/whatsapp_webhook.php
  ```
  Resultado esperado: `No syntax errors detected`

- [ ] **Paso 3: Probar con payload simulado de Meta**

  ```bash
  curl -s -X POST "https://apex.glass/produccion/api/whatsapp_webhook.php" \
    -H "Content-Type: application/json" \
    -H "X-Hub-Signature-256: sha256=PLACEHOLDER" \
    -d '{"entry":[{"changes":[{"value":{"messages":[{"from":"528112345678","id":"wamid.test1","type":"text","text":{"body":"Hola prueba"}}]}}]}]}'
  ```
  Nota: la firma será inválida (retorna 401) — eso es correcto. La prueba real se valida con una respuesta de un cliente real vía Meta.

- [ ] **Paso 4: Commit**

  ```bash
  git add api/whatsapp_webhook.php
  git commit -m "feat: webhook handler POST mensajes inbound + statuses (UPD-111)"
  ```

---

## Task 6: Módulo SPA — Tab Campañas y wizard

**Files:**
- Create: `app/modulos/campanas.php`

**Interfaces:**
- Consume: `api/campanas.php` acciones listar, detalle, clientes_segmento, crear, enviar, progreso
- Produce: módulo SPA con namespace `ModCampanas`, Tab Campañas funcional con wizard de 3 pasos

- [ ] **Paso 1: Crear app/modulos/campanas.php**

  ```php
  <?php
  // ============================================================
  //  APEX GLASS - Módulo: Campañas WhatsApp
  //  Archivo: app/modulos/campanas.php
  // ============================================================
  require_once __DIR__ . '/../../api/config.php';
  require_once __DIR__ . '/../../api/permisos.php';
  $user = requireSession();
  $rol  = $_SESSION['rol'] ?? '';
  $puedeEnviar = in_array($rol, ['dir_admin','dueno']);
  ?>
  <style>
  .cmp-tabs{display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:16px;}
  .cmp-tab{padding:10px 20px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;margin-bottom:-2px;}
  .cmp-tab.active{color:#2563eb;border-bottom-color:#2563eb;}
  .cmp-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:12px;}
  .cmp-badge{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;}
  .cmp-badge.enviada{background:#dcfce7;color:#16a34a;}
  .cmp-badge.enviando{background:#fef9c3;color:#ca8a04;}
  .cmp-badge.borrador{background:#f1f5f9;color:#64748b;}
  .cmp-badge.cancelada{background:#fee2e2;color:#dc2626;}
  .cmp-metricas{display:flex;gap:16px;font-size:12px;color:#64748b;margin-top:8px;}
  .cmp-metrica span{font-weight:700;color:#1e293b;}
  .cmp-wizard-steps{display:flex;gap:0;margin-bottom:20px;}
  .cmp-step{flex:1;text-align:center;padding:8px;font-size:12px;font-weight:600;color:#94a3b8;border-bottom:3px solid #e2e8f0;}
  .cmp-step.active{color:#2563eb;border-bottom-color:#2563eb;}
  .cmp-step.done{color:#16a34a;border-bottom-color:#16a34a;}
  .cmp-clientes-tabla{max-height:300px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px;}
  .cmp-clientes-tabla table{width:100%;font-size:12px;border-collapse:collapse;}
  .cmp-clientes-tabla th{background:#f8fafc;padding:8px 10px;text-align:left;font-weight:600;position:sticky;top:0;}
  .cmp-clientes-tabla td{padding:7px 10px;border-top:1px solid #f1f5f9;}
  .cmp-clientes-tabla tr:hover td{background:#f8fafc;}
  .cmp-preview{background:#dcfce7;border-radius:12px;padding:12px 16px;font-size:13px;max-width:320px;margin-top:8px;}
  .cmp-progreso{background:#e2e8f0;border-radius:99px;height:8px;margin:12px 0;}
  .cmp-progreso-bar{background:#2563eb;border-radius:99px;height:8px;transition:width .3s;}
  .conv-panel{display:flex;gap:0;height:520px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;}
  .conv-lista{width:300px;border-right:1px solid #e2e8f0;overflow-y:auto;flex-shrink:0;}
  .conv-item{padding:12px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;}
  .conv-item:hover,.conv-item.active{background:#eff6ff;}
  .conv-item-nombre{font-size:13px;font-weight:600;color:#1e293b;}
  .conv-item-preview{font-size:11px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .conv-badge{background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;float:right;}
  .conv-chat{flex:1;display:flex;flex-direction:column;}
  .conv-mensajes{flex:1;padding:16px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;}
  .msg-burbuja{max-width:75%;padding:10px 14px;border-radius:12px;font-size:13px;line-height:1.4;}
  .msg-out{background:#dcfce7;align-self:flex-end;border-bottom-right-radius:3px;}
  .msg-in{background:#f1f5f9;align-self:flex-start;border-bottom-left-radius:3px;}
  .msg-meta{font-size:10px;color:#94a3b8;margin-top:3px;}
  .conv-input{border-top:1px solid #e2e8f0;padding:12px;display:flex;gap:8px;}
  .conv-input textarea{flex:1;border:1px solid #e2e8f0;border-radius:6px;padding:8px;font-size:13px;resize:none;height:60px;}
  .conv-input button{padding:0 18px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;}
  </style>

  <div style="padding:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h2 style="margin:0;font-size:18px;color:#1e293b;">&#128241; Campañas WhatsApp</h2>
      <?php if ($puedeEnviar): ?>
      <button onclick="window.cmpNuevaCampana()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;">+ Nueva Campaña</button>
      <?php endif; ?>
    </div>

    <div class="cmp-tabs">
      <button class="cmp-tab active" onclick="window.cmpTab('campanas',this)">Campañas</button>
      <button class="cmp-tab" onclick="window.cmpTab('conversaciones',this)">Conversaciones <span id="cmpBadgeTot" style="display:none;background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;"></span></button>
    </div>

    <div id="cmpPanelCampanas">
      <div id="cmpListaCampanas"><p style="color:#64748b;">Cargando...</p></div>
    </div>

    <div id="cmpPanelConversaciones" style="display:none;">
      <div class="conv-panel">
        <div class="conv-lista" id="cmpConvLista"></div>
        <div class="conv-chat" id="cmpConvChat">
          <div style="flex:1;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:13px;">Selecciona una conversación</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Wizard Nueva Campaña -->
  <div id="cmpModalWizard" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;width:700px;max-width:95vw;max-height:90vh;overflow-y:auto;padding:24px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;font-size:16px;">Nueva Campaña WhatsApp</h3>
        <button onclick="window.cmpCerrarWizard()" style="background:none;border:none;font-size:20px;cursor:pointer;">&#10005;</button>
      </div>
      <div class="cmp-wizard-steps">
        <div class="cmp-step active" id="cmpStepInd1">1. Segmento</div>
        <div class="cmp-step" id="cmpStepInd2">2. Mensaje</div>
        <div class="cmp-step" id="cmpStepInd3">3. Confirmar</div>
      </div>
      <div id="cmpWizardContenido"></div>
    </div>
  </div>

  <!-- Modal Detalle Campaña -->
  <div id="cmpModalDetalle" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;width:720px;max-width:95vw;max-height:90vh;overflow-y:auto;padding:24px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;font-size:16px;" id="cmpDetalleTitulo">Detalle de campaña</h3>
        <button onclick="window.cmpCerrarDetalle()" style="background:none;border:none;font-size:20px;cursor:pointer;">&#10005;</button>
      </div>
      <div id="cmpDetalleContenido"></div>
    </div>
  </div>

  <script>
  var ModCampanas = (function() {
      var _step = 1;
      var _clientesSeleccionados = [];
      var _templateNombre = '';
      var _templateVars = [];
      var _nombreCampana = '';
      var _convActiva = null;
      var _pollTimer = null;

      // ── Tabs ─────────────────────────────────────────────────
      function tab(cual, btn) {
          document.querySelectorAll('.cmp-tab').forEach(function(b){ b.classList.remove('active'); });
          btn.classList.add('active');
          document.getElementById('cmpPanelCampanas').style.display      = cual === 'campanas' ? '' : 'none';
          document.getElementById('cmpPanelConversaciones').style.display = cual === 'conversaciones' ? '' : 'none';
          if (cual === 'conversaciones') { cargarConversaciones(); }
          else { cargarCampanas(); }
      }

      // ── Cargar lista campañas ─────────────────────────────────
      function cargarCampanas() {
          fetch('/produccion/api/campanas.php?accion=listar')
            .then(function(r){ return r.json(); })
            .then(function(data) {
              var html = '';
              if (!data.campanas || data.campanas.length === 0) {
                  html = '<p style="color:#64748b;font-size:13px;">Sin campañas aún. Crea la primera.</p>';
              } else {
                  data.campanas.forEach(function(c) {
                      html += '<div class="cmp-card">' +
                          '<div style="display:flex;justify-content:space-between;align-items:center;">' +
                          '<strong style="font-size:14px;">' + esc(c.nombre) + '</strong>' +
                          '<span class="cmp-badge ' + c.estado + '">' + c.estado.toUpperCase() + '</span>' +
                          '</div>' +
                          '<div class="cmp-metricas">' +
                          '<div class="cmp-metrica">Enviados: <span>' + c.enviados + '/' + c.total_destinatarios + '</span></div>' +
                          '<div class="cmp-metrica">Entregados: <span>' + c.entregados + '</span></div>' +
                          '<div class="cmp-metrica">Leídos: <span>' + c.leidos + '</span></div>' +
                          '<div class="cmp-metrica">Respuestas: <span>' + c.respuestas + '</span></div>' +
                          '</div>' +
                          '<div style="margin-top:10px;">' +
                          '<button onclick="window.cmpVerDetalle(' + c.id + ')" style="font-size:12px;padding:5px 12px;border:1px solid #e2e8f0;border-radius:5px;background:#fff;cursor:pointer;">Ver detalle</button>' +
                          '</div>' +
                          '</div>';
                  });
              }
              document.getElementById('cmpListaCampanas').innerHTML = html;
          });
      }

      // ── Detalle campaña ───────────────────────────────────────
      function verDetalle(id) {
          document.getElementById('cmpModalDetalle').style.display = 'flex';
          document.getElementById('cmpDetalleContenido').innerHTML = '<p>Cargando...</p>';
          fetch('/produccion/api/campanas.php?accion=detalle&id=' + id)
            .then(function(r){ return r.json(); })
            .then(function(data) {
              var c = data.campana;
              var filas = '';
              (data.envios || []).forEach(function(e) {
                  filas += '<tr><td>' + esc(e.nombre_cliente) + '</td><td>' + e.telefono + '</td>' +
                      '<td><span class="cmp-badge ' + e.estado + '">' + e.estado + '</span></td>' +
                      '<td style="font-size:11px;">' + (e.enviado_at || '-') + '</td></tr>';
              });
              document.getElementById('cmpDetalleTitulo').textContent = c.nombre;
              document.getElementById('cmpDetalleContenido').innerHTML =
                  '<table style="width:100%;font-size:13px;border-collapse:collapse;">' +
                  '<thead><tr style="background:#f8fafc;"><th style="padding:8px;text-align:left;">Cliente</th><th>Teléfono</th><th>Estado</th><th>Enviado</th></tr></thead>' +
                  '<tbody>' + filas + '</tbody></table>';
          });
      }

      function cerrarDetalle() {
          document.getElementById('cmpModalDetalle').style.display = 'none';
      }

      // ── Wizard Nueva Campaña ──────────────────────────────────
      function nuevaCampana() {
          _step = 1; _clientesSeleccionados = []; _templateNombre = ''; _templateVars = []; _nombreCampana = '';
          document.getElementById('cmpModalWizard').style.display = 'flex';
          renderStep();
      }

      function cerrarWizard() {
          document.getElementById('cmpModalWizard').style.display = 'none';
          clearInterval(_pollTimer);
      }

      function actualizarIndicadores() {
          ['1','2','3'].forEach(function(n) {
              var el = document.getElementById('cmpStepInd' + n);
              var ni = parseInt(n);
              el.className = 'cmp-step' + (ni < _step ? ' done' : '') + (ni === _step ? ' active' : '');
          });
      }

      function renderStep() {
          actualizarIndicadores();
          var cont = document.getElementById('cmpWizardContenido');
          if (_step === 1) {
              cont.innerHTML =
                  '<div style="margin-bottom:12px;">' +
                  '<label style="font-size:13px;font-weight:600;">Nombre de la campaña</label><br>' +
                  '<input id="cmpNombre" type="text" placeholder="Ej: Promo Julio" style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;margin-top:4px;">' +
                  '</div>' +
                  '<div style="display:flex;gap:12px;margin-bottom:12px;">' +
                  '<div style="flex:1;"><label style="font-size:12px;font-weight:600;">Localidad</label><br>' +
                  '<select id="cmpLocalidad" onchange="window.cmpFiltrarClientes()" style="width:100%;padding:7px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;margin-top:4px;">' +
                  '<option value="">Todos</option><option value="LOCAL">Local (MTY)</option><option value="FORANEO">Foráneo</option>' +
                  '</select></div>' +
                  '<div style="flex:1;"><label style="font-size:12px;font-weight:600;">Ciudad</label><br>' +
                  '<input id="cmpCiudad" type="text" placeholder="Filtrar por ciudad..." oninput="window.cmpFiltrarClientes()" style="width:100%;padding:7px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;margin-top:4px;"></div>' +
                  '</div>' +
                  '<div style="margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">' +
                  '<span id="cmpContador" style="font-size:13px;font-weight:600;color:#2563eb;">Cargando clientes...</span>' +
                  '<label style="font-size:12px;"><input type="checkbox" id="cmpTodos" onchange="window.cmpToggleTodos()"> Seleccionar todos</label>' +
                  '</div>' +
                  '<div class="cmp-clientes-tabla"><table><thead><tr><th><input type="checkbox" id="cmpChkAll" onchange="window.cmpToggleTodos()"></th><th>Cliente</th><th>Teléfono</th><th>Ciudad</th></tr></thead><tbody id="cmpTablaBody"></tbody></table></div>' +
                  '<div style="text-align:right;margin-top:16px;"><button onclick="window.cmpSiguiente()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;">Siguiente →</button></div>';
              cmpFiltrarClientes();
          } else if (_step === 2) {
              cont.innerHTML =
                  '<div style="margin-bottom:12px;">' +
                  '<label style="font-size:13px;font-weight:600;">Nombre del template (exacto de Meta)</label><br>' +
                  '<input id="cmpTemplate" type="text" placeholder="Ej: promo_julio_2026" value="' + esc(_templateNombre) + '" style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;margin-top:4px;">' +
                  '<p style="font-size:11px;color:#64748b;margin:4px 0 0;">Debe coincidir exactamente con el nombre aprobado en Meta Business Manager.</p>' +
                  '</div>' +
                  '<div style="margin-bottom:12px;">' +
                  '<label style="font-size:13px;font-weight:600;">Variables del template</label>' +
                  '<p style="font-size:11px;color:#64748b;margin:4px 0 8px;">Usa <code>{{nombre_cliente}}</code> para insertar el nombre del cliente automáticamente. Agrega las variables en el orden del template.</p>' +
                  '<div id="cmpVarsLista"></div>' +
                  '<button onclick="window.cmpAgregarVar()" style="font-size:12px;padding:5px 12px;border:1px solid #2563eb;border-radius:5px;color:#2563eb;background:#fff;cursor:pointer;margin-top:6px;">+ Agregar variable</button>' +
                  '</div>' +
                  '<div id="cmpPreviewArea"></div>' +
                  '<div style="display:flex;justify-content:space-between;margin-top:16px;">' +
                  '<button onclick="window.cmpAnterior()" style="background:#f1f5f9;color:#1e293b;border:none;border-radius:6px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;">← Atrás</button>' +
                  '<button onclick="window.cmpSiguiente()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;">Siguiente →</button>' +
                  '</div>';
              if (_templateVars.length > 0) { renderVars(); }
          } else if (_step === 3) {
              var filas = '';
              _clientesSeleccionados.slice(0,50).forEach(function(c) {
                  var msg = construirMensaje(c.nombre);
                  filas += '<tr><td style="font-size:12px;padding:6px 8px;">' + esc(c.nombre) + '</td>' +
                      '<td style="font-size:12px;padding:6px 8px;">' + c.telefono + '</td>' +
                      '<td style="font-size:11px;padding:6px 8px;color:#64748b;">' + esc(msg) + '</td></tr>';
              });
              var extra = _clientesSeleccionados.length > 50 ? '<tr><td colspan="3" style="font-size:11px;color:#64748b;padding:6px 8px;">...y ' + (_clientesSeleccionados.length - 50) + ' más</td></tr>' : '';
              cont.innerHTML =
                  '<div class="cmp-card" style="margin-bottom:12px;">' +
                  '<strong>' + esc(_nombreCampana) + '</strong> · ' +
                  '<span style="font-size:13px;color:#64748b;">' + _clientesSeleccionados.length + ' destinatarios · Template: <code>' + esc(_templateNombre) + '</code></span>' +
                  '</div>' +
                  '<div style="max-height:280px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:16px;">' +
                  '<table style="width:100%;border-collapse:collapse;"><thead><tr style="background:#f8fafc;"><th style="padding:7px 8px;font-size:12px;text-align:left;">Cliente</th><th style="text-align:left;font-size:12px;padding:7px 8px;">Teléfono</th><th style="text-align:left;font-size:12px;padding:7px 8px;">Mensaje</th></tr></thead>' +
                  '<tbody>' + filas + extra + '</tbody></table></div>' +
                  '<div id="cmpProgresoArea" style="display:none;">' +
                  '<div class="cmp-progreso"><div class="cmp-progreso-bar" id="cmpBarraProgreso" style="width:0%"></div></div>' +
                  '<p id="cmpProgresoTxt" style="font-size:12px;color:#64748b;text-align:center;"></p></div>' +
                  '<div style="display:flex;justify-content:space-between;margin-top:4px;">' +
                  '<button onclick="window.cmpAnterior()" id="cmpBtnAtras" style="background:#f1f5f9;color:#1e293b;border:none;border-radius:6px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;">← Atrás</button>' +
                  '<button onclick="window.cmpEnviarCampana()" id="cmpBtnEnviar" style="background:#16a34a;color:#fff;border:none;border-radius:6px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;">&#128241; Enviar campaña</button>' +
                  '</div>';
          }
      }

      function renderVars() {
          var html = '';
          _templateVars.forEach(function(v, i) {
              html += '<div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">' +
                  '<span style="font-size:12px;color:#64748b;width:40px;">&#123;&#123;' + (i+1) + '&#125;&#125;</span>' +
                  '<input type="text" value="' + esc(v) + '" placeholder="{{nombre_cliente}} o texto fijo" ' +
                  'oninput="window.cmpActualizarVar(' + i + ',this.value)" ' +
                  'style="flex:1;padding:6px;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;">' +
                  '<button onclick="window.cmpEliminarVar(' + i + ')" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:16px;">&#10005;</button>' +
                  '</div>';
          });
          var el = document.getElementById('cmpVarsLista');
          if (el) el.innerHTML = html;
          actualizarPreview();
      }

      function construirMensaje(nombreCliente) {
          return 'Template: ' + _templateNombre + ' | Vars: ' + _templateVars.map(function(v) {
              return v === '{{nombre_cliente}}' ? nombreCliente : v;
          }).join(', ');
      }

      function actualizarPreview() {
          var area = document.getElementById('cmpPreviewArea');
          if (!area) return;
          var nombre = (_clientesSeleccionados[0] || {nombre: 'Ramón'}).nombre;
          area.innerHTML = '<label style="font-size:12px;font-weight:600;color:#64748b;">Preview (primer cliente):</label>' +
              '<div class="cmp-preview">' + esc(construirMensaje(nombre)) + '</div>';
      }

      function cmpFiltrarClientes() {
          var loc    = (document.getElementById('cmpLocalidad') || {}).value || '';
          var ciudad = (document.getElementById('cmpCiudad')    || {}).value || '';
          var url    = '/produccion/api/campanas.php?accion=clientes_segmento&activos=1';
          if (loc)    url += '&localidad=' + encodeURIComponent(loc);
          if (ciudad) url += '&ciudad='    + encodeURIComponent(ciudad);
          fetch(url).then(function(r){ return r.json(); }).then(function(data) {
              var clientes = data.clientes || [];
              var filas = '';
              clientes.forEach(function(c) {
                  var chk = _clientesSeleccionados.find(function(x){ return x.id === c.id; }) ? 'checked' : '';
                  filas += '<tr><td style="padding:6px 10px;"><input type="checkbox" ' + chk + ' onchange="window.cmpToggleCliente(' + c.id + ',\'' + esc(c.nombre) + '\',\'' + normTel(c.telefono) + '\',this.checked)"></td>' +
                      '<td style="padding:6px 10px;">' + esc(c.nombre) + '</td>' +
                      '<td style="padding:6px 10px;">' + (c.telefono || '') + '</td>' +
                      '<td style="padding:6px 10px;">' + (c.ciudad || '') + '</td></tr>';
              });
              var body = document.getElementById('cmpTablaBody');
              if (body) body.innerHTML = filas;
              var cnt = document.getElementById('cmpContador');
              if (cnt) cnt.textContent = _clientesSeleccionados.length + ' seleccionados de ' + clientes.length;
          });
      }

      function normTel(tel) {
          tel = tel.replace(/\D/g,'');
          if (tel.length === 10) return '52' + tel;
          if (tel.length === 12 && tel.substr(0,2) === '52') return tel;
          return '52' + tel.substr(-10);
      }

      function toggleCliente(id, nombre, tel, checked) {
          if (checked) {
              if (!_clientesSeleccionados.find(function(x){ return x.id === id; })) {
                  _clientesSeleccionados.push({id:id, nombre:nombre, telefono:tel});
              }
          } else {
              _clientesSeleccionados = _clientesSeleccionados.filter(function(x){ return x.id !== id; });
          }
          var cnt = document.getElementById('cmpContador');
          if (cnt) cnt.textContent = _clientesSeleccionados.length + ' seleccionados';
      }

      function toggleTodos() {
          var chks = document.querySelectorAll('#cmpTablaBody input[type=checkbox]');
          var checkAll = document.getElementById('cmpChkAll');
          var marcar = checkAll ? checkAll.checked : false;
          chks.forEach(function(ch){ if (ch.checked !== marcar) ch.click(); });
      }

      function agregarVar() { _templateVars.push(''); renderVars(); }
      function actualizarVar(i, v) { _templateVars[i] = v; actualizarPreview(); }
      function eliminarVar(i) { _templateVars.splice(i,1); renderVars(); }

      function siguiente() {
          if (_step === 1) {
              _nombreCampana = (document.getElementById('cmpNombre') || {}).value || '';
              if (!_nombreCampana.trim()) { alert('Ingresa el nombre de la campaña'); return; }
              if (_clientesSeleccionados.length === 0) { alert('Selecciona al menos un cliente'); return; }
          } else if (_step === 2) {
              _templateNombre = (document.getElementById('cmpTemplate') || {}).value || '';
              if (!_templateNombre.trim()) { alert('Ingresa el nombre del template'); return; }
          }
          if (_step < 3) { _step++; renderStep(); }
      }

      function anterior() { if (_step > 1) { _step--; renderStep(); } }

      function enviarCampana() {
          var btn = document.getElementById('cmpBtnEnviar');
          var btnAtras = document.getElementById('cmpBtnAtras');
          if (btn) { btn.disabled = true; btn.textContent = 'Guardando...'; }
          if (btnAtras) { btnAtras.disabled = true; }

          var clienteIds = _clientesSeleccionados.map(function(c){ return c.id; });
          fetch('/produccion/api/campanas.php?accion=crear', {
              method: 'POST',
              headers: {'Content-Type':'application/json'},
              body: JSON.stringify({
                  nombre: _nombreCampana,
                  template_nombre: _templateNombre,
                  template_vars: _templateVars,
                  segmento: {},
                  cliente_ids: clienteIds
              })
          })
          .then(function(r){ return r.json(); })
          .then(function(data) {
              if (!data.ok) { alert('Error: ' + (data.error || 'desconocido')); return; }
              var campanaId = data.id;
              document.getElementById('cmpProgresoArea').style.display = '';
              if (btn) { btn.textContent = 'Enviando...'; }

              fetch('/produccion/api/campanas.php?accion=enviar', {
                  method: 'POST',
                  headers: {'Content-Type':'application/json'},
                  body: JSON.stringify({campana_id: campanaId})
              }).then(function(r){ return r.json(); }).then(function(res) {
                  if (btn) { btn.textContent = 'Enviado ✓'; }
                  clearInterval(_pollTimer);
                  setTimeout(function() { cerrarWizard(); cargarCampanas(); }, 1500);
              });

              // Poll progreso
              _pollTimer = setInterval(function() {
                  fetch('/produccion/api/campanas.php?accion=progreso&id=' + campanaId)
                    .then(function(r){ return r.json(); })
                    .then(function(p) {
                      var pct = p.total > 0 ? Math.round(p.enviados / p.total * 100) : 0;
                      var bar = document.getElementById('cmpBarraProgreso');
                      var txt = document.getElementById('cmpProgresoTxt');
                      if (bar) bar.style.width = pct + '%';
                      if (txt) txt.textContent = p.enviados + ' / ' + p.total + ' enviados (' + pct + '%)';
                      if (p.estado === 'enviada') { clearInterval(_pollTimer); }
                  });
              }, 3000);
          });
      }

      // ── Conversaciones ────────────────────────────────────────
      function cargarConversaciones() {
          fetch('/produccion/api/campanas.php?accion=conversaciones')
            .then(function(r){ return r.json(); })
            .then(function(data) {
              var html = '';
              var sinLeerTotal = 0;
              (data.conversaciones || []).forEach(function(c) {
                  sinLeerTotal += parseInt(c.mensajes_sin_leer) || 0;
                  var badge = c.mensajes_sin_leer > 0 ? '<span class="conv-badge">' + c.mensajes_sin_leer + '</span>' : '';
                  html += '<div class="conv-item" onclick="window.cmpAbrirConv(' + c.id + ')" id="convItem' + c.id + '">' +
                      badge +
                      '<div class="conv-item-nombre">' + esc(c.nombre_cliente || c.telefono) + '</div>' +
                      '<div class="conv-item-preview">' + esc((c.ultimo_mensaje || 'Sin mensajes').substring(0,60)) + '</div>' +
                      '</div>';
              });
              document.getElementById('cmpConvLista').innerHTML = html || '<p style="padding:14px;font-size:12px;color:#64748b;">Sin conversaciones aún.</p>';
              var badge = document.getElementById('cmpBadgeTot');
              if (badge) {
                  badge.textContent = sinLeerTotal;
                  badge.style.display = sinLeerTotal > 0 ? '' : 'none';
              }
          });
      }

      function abrirConv(convId) {
          _convActiva = convId;
          document.querySelectorAll('.conv-item').forEach(function(el){ el.classList.remove('active'); });
          var item = document.getElementById('convItem' + convId);
          if (item) item.classList.add('active');

          fetch('/produccion/api/campanas.php?accion=mensajes&conversacion_id=' + convId)
            .then(function(r){ return r.json(); })
            .then(function(data) {
              var msgs = '';
              (data.mensajes || []).forEach(function(m) {
                  var cls = m.direccion === 'outbound' ? 'msg-out' : 'msg-in';
                  var meta = m.direccion === 'outbound' ? (m.enviado_por || '') : '';
                  msgs += '<div class="msg-burbuja ' + cls + '">' + esc(m.contenido) +
                      (meta ? '<div class="msg-meta">' + esc(meta) + '</div>' : '') +
                      '</div>';
              });
              document.getElementById('cmpConvChat').innerHTML =
                  '<div class="conv-mensajes" id="cmpMsgs">' + msgs + '</div>' +
                  '<div class="conv-input">' +
                  '<textarea id="cmpMsgInput" placeholder="Escribe tu respuesta..."></textarea>' +
                  '<button onclick="window.cmpEnviarMensaje()">Enviar</button>' +
                  '</div>';
              var msgsEl = document.getElementById('cmpMsgs');
              if (msgsEl) msgsEl.scrollTop = msgsEl.scrollHeight;

              // Marcar como leído
              fetch('/produccion/api/campanas.php?accion=marcar_leido', {
                  method:'POST', headers:{'Content-Type':'application/json'},
                  body: JSON.stringify({conversacion_id: convId})
              });
          });
      }

      function enviarMensaje() {
          var input = document.getElementById('cmpMsgInput');
          var msg = (input || {}).value || '';
          if (!msg.trim() || !_convActiva) return;
          input.value = '';
          fetch('/produccion/api/campanas.php?accion=responder', {
              method: 'POST', headers:{'Content-Type':'application/json'},
              body: JSON.stringify({conversacion_id: _convActiva, mensaje: msg})
          }).then(function(r){ return r.json(); }).then(function(data) {
              if (data.ok) { abrirConv(_convActiva); }
              else { alert('Error al enviar: ' + (data.error || 'desconocido')); }
          });
      }

      // ── Utilidad escape ───────────────────────────────────────
      function esc(s) {
          if (!s) return '';
          return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
      }

      // ── Init ──────────────────────────────────────────────────
      function init() { cargarCampanas(); }

      // ── Exposición global ─────────────────────────────────────
      window.cmpTab             = tab;
      window.cmpNuevaCampana    = nuevaCampana;
      window.cmpCerrarWizard    = cerrarWizard;
      window.cmpSiguiente       = siguiente;
      window.cmpAnterior        = anterior;
      window.cmpEnviarCampana   = enviarCampana;
      window.cmpVerDetalle      = verDetalle;
      window.cmpCerrarDetalle   = cerrarDetalle;
      window.cmpFiltrarClientes = cmpFiltrarClientes;
      window.cmpToggleCliente   = toggleCliente;
      window.cmpToggleTodos     = toggleTodos;
      window.cmpAgregarVar      = agregarVar;
      window.cmpActualizarVar   = actualizarVar;
      window.cmpEliminarVar     = eliminarVar;
      window.cmpAbrirConv       = abrirConv;
      window.cmpEnviarMensaje   = enviarMensaje;

      return { init: init };
  })();

  ModCampanas.init();
  </script>
  ```

- [ ] **Paso 2: Verificar sintaxis PHP**

  ```bash
  php -l /home/apexglass2025/apex.glass/public_html/produccion/app/modulos/campanas.php
  ```
  Resultado esperado: `No syntax errors detected`

- [ ] **Paso 3: Commit**

  ```bash
  git add app/modulos/campanas.php
  git commit -m "feat: modulos/campanas.php ModCampanas Tab Campanas + wizard 3 pasos + Tab Conversaciones (UPD-111)"
  ```

---

## Task 7: Dashboard — entrada en sidebar

**Files:**
- Modify: `app/dashboard.php` (agregar botón Campañas en sección Comercial)

**Interfaces:**
- Consume: módulo `campanas` cargado por `cargarModulo('campanas')`

- [ ] **Paso 1: Agregar botón Campañas en sección Comercial del sidebar**

  Abrir `app/dashboard.php`. Localizar la línea que dice:
  ```html
  <button class="sidebar-link" data-modulo="optimizador" onclick="cargarModulo('optimizador')">
  ```
  Después del cierre `</button>` de ese botón, agregar:

  ```php
        <button class="sidebar-link" data-modulo="campanas" onclick="cargarModulo('campanas')">
          <span class="sidebar-icon">&#128241;</span>Campa&ntilde;as WA
        </button>
  ```

- [ ] **Paso 2: Verificar sintaxis PHP del dashboard**

  ```bash
  php -l /home/apexglass2025/apex.glass/public_html/produccion/app/dashboard.php
  ```
  Resultado esperado: `No syntax errors detected`

- [ ] **Paso 3: Prueba en navegador**

  1. Abrir `https://apex.glass/produccion/app/dashboard.php` con sesión `dir_admin`
  2. Verificar que aparece "Campañas WA" en el sidebar bajo Comercial
  3. Hacer clic → debe cargar el módulo sin errores en consola
  4. Verificar que los tabs "Campañas" y "Conversaciones" son visibles
  5. Entrar como usuario `comercial` → verificar que ve el módulo pero NO el botón "Nueva Campaña"

- [ ] **Paso 4: Commit final**

  ```bash
  git add app/dashboard.php
  git commit -m "feat: sidebar Campanas WA bajo seccion Comercial (UPD-111)"
  ```

---

## Task 8: Prueba end-to-end y registro UPD

**Files:**
- Modify: `CLAUDE.md` (registrar UPD-111)

- [ ] **Paso 1: Prueba completa de flujo feliz**

  1. Login como `dir_admin` en `https://apex.glass/produccion/app/dashboard.php`
  2. Clic en "Campañas WA" → debe abrir el módulo sin errores de consola JS
  3. Clic en "+ Nueva Campaña"
  4. Paso 1: escribir nombre "Test Julio 2026", seleccionar 1-2 clientes con teléfono real de prueba
  5. Paso 2: escribir el nombre exacto de un template aprobado en Meta (ej. `hello_world`), agregar variable `{{nombre_cliente}}`
  6. Paso 3: verificar que aparece la tabla de confirmación con el mensaje preview
  7. Clic "Enviar campaña" → verificar barra de progreso → esperar "Enviado ✓"
  8. Verificar en BD: `SELECT * FROM campanas; SELECT * FROM campana_envios;`
  9. Verificar que el cliente recibió el mensaje en WhatsApp
  10. Responder desde el WhatsApp del cliente → verificar que aparece en Tab "Conversaciones"
  11. Responder desde APEX → verificar que llega al WhatsApp del cliente

- [ ] **Paso 2: Prueba de permisos**

  Login como usuario `comercial`:
  - Debe ver "Campañas WA" en sidebar ✓
  - NO debe ver botón "+ Nueva Campaña" ✓
  - Debe poder ver lista de campañas ✓
  - Debe poder abrir Conversaciones y responder ✓

- [ ] **Paso 3: Registrar UPD-111 en CLAUDE.md**

  En la tabla de historial de CLAUDE.md (sección 13), agregar:
  ```
  | UPD-111 | 19-jun | Armando | NUEVO módulo Campañas WhatsApp: Meta Cloud API, 4 tablas BD, wizard 3 pasos, inbox conversaciones, permisos dir_admin/comercial |
  ```

  Actualizar "Próximo UPD disponible" a `UPD-112`.

- [ ] **Paso 4: Commit final**

  ```bash
  git add CLAUDE.md
  git commit -m "docs: registrar UPD-111 modulo Campanas WhatsApp"
  ```
