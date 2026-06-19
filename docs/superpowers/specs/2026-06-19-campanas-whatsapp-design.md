# Módulo Campañas WhatsApp — Diseño
**Fecha:** 2026-06-19  
**Responsable:** Armando  
**Próximo UPD:** UPD-111

---

## Resumen

Módulo integrado en el dashboard APEX GLASS para enviar campañas masivas de WhatsApp a clientes segmentados, recibir y responder sus mensajes, todo desde el sistema existente. Reemplaza el uso de wanotifier. Implementado con Meta Cloud API (WhatsApp Business API oficial). Facebook Ads queda como Fase 2 futura.

---

## 1. Arquitectura

```
APEX Dashboard (SPA)
└── modulos/campanas.php   → namespace ModCampanas
    ├── Tab "Campañas"     → crear, historial, métricas
    └── Tab "Conversaciones" → inbox + chat por cliente

api/campanas.php           → CRUD campañas, envío, segmentación, responder
api/whatsapp_webhook.php   → endpoint HTTPS receptor de eventos Meta

Meta Cloud API (graph.facebook.com/v20.0)
  ← APEX envía mensajes via HTTP POST
  → Meta llama al webhook al recibir respuestas o actualizaciones de estado
```

**Archivos nuevos:**
- `api/campanas.php`
- `api/whatsapp_webhook.php`
- `app/modulos/campanas.php`

**Archivos modificados:**
- `api/config.php` — 4 constantes nuevas: `WA_TOKEN`, `WA_PHONE_ID`, `WA_VERIFY_TOKEN`, `WA_APP_SECRET`
- `app/dashboard.php` — entrada en sidebar bajo sección Comercial

---

## 2. Base de Datos (4 tablas nuevas)

```sql
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
```

---

## 3. Permisos por Rol

| Acción | dir_admin / dueno | comercial | administracion |
|--------|:-----------------:|:---------:|:--------------:|
| Crear y enviar campañas masivas | ✅ | ❌ | ❌ |
| Ver campañas y métricas (solo lectura) | ✅ | ✅ | ❌ |
| Ver Tab Conversaciones | ✅ | ✅ | ❌ |
| Responder mensajes en chat | ✅ | ✅ | ❌ |

---

## 4. Módulo UI (campanas.php)

### Tab Campañas
- Lista paginada de campañas con métricas: enviados / entregados / leídos / respuestas
- Botón **"+ Nueva Campaña"** → abre wizard de 3 pasos
- Cada fila tiene botón **"Ver"** → detalle con tabla de envíos por cliente

### Wizard Nueva Campaña

**Paso 1 — Segmento**
- Filtros: Localidad (Local/Foráneo/Todos), Ciudad, solo Activos (default on)
- Tabla de clientes con checkbox para selección manual
- Contador en tiempo real: *"32 clientes seleccionados"*

**Paso 2 — Mensaje**
- Dropdown de templates registrados y aprobados en Meta
- Campos editables por variable del template (ej. `{{1}}` = nombre_cliente)
- Preview del mensaje final con datos reales del primer cliente seleccionado

**Paso 3 — Confirmar**
- Tabla: cliente | teléfono | mensaje personalizado completo
- Botón **"Enviar campaña"** → dispara envíos a razón de máx 25/min
- Barra de progreso en tiempo real (polling cada 3s a `api/campanas.php?accion=progreso`)

### Tab Conversaciones
- Panel izquierdo: lista de conversaciones ordenadas por `ultima_actividad`, badge rojo de sin leer
- Panel derecho: historial de mensajes del cliente seleccionado (burbuja outbound/inbound)
- Input de texto + botón Enviar → `api/campanas.php?accion=responder` → Meta API tipo "text"
- Las asesoras (comercial) ven todas las conversaciones y pueden responder

---

## 5. API (api/campanas.php)

| Acción | Método | Descripción |
|--------|--------|-------------|
| `listar` | GET | Lista campañas con métricas |
| `detalle` | GET | Campaña + tabla de envíos |
| `clientes_segmento` | GET | Clientes filtrados por segmento |
| `crear` | POST | Crea campaña en estado borrador |
| `enviar` | POST | Dispara envíos masivos vía Meta API |
| `progreso` | GET | Estado actual de envío (para polling) |
| `conversaciones` | GET | Lista conversaciones con último mensaje |
| `mensajes` | GET | Historial de mensajes de una conversación |
| `responder` | POST | Envía mensaje de texto libre a cliente |
| `marcar_leido` | POST | Marca conversación como leída |

---

## 6. Webhook (api/whatsapp_webhook.php)

**GET** — Verificación inicial de Meta:
```
?hub.mode=subscribe&hub.challenge=XXXXX&hub.verify_token=apex_wh_xyz
→ responde: hub.challenge
```

**POST** — Eventos en tiempo real:

- `messages` → cliente envió mensaje → crear/actualizar `whatsapp_conversaciones` + insertar en `whatsapp_mensajes` (inbound) + incrementar `mensajes_sin_leer` + incrementar `campanas.respuestas` si hay `campana_envio_id` asociado
- `statuses` → actualización: `sent`/`delivered`/`read`/`failed` → actualizar `campana_envios.estado` + timestamps + contadores en `campanas`

Seguridad: validar `X-Hub-Signature-256` header con `hash_hmac('sha256', $payload, WA_APP_SECRET)`. El App Secret viene de la configuración de la Meta App (distinto al WA_TOKEN de System User).

---

## 7. Integración Meta Cloud API

**Envío template (campaña masiva):**
```
POST https://graph.facebook.com/v20.0/{WA_PHONE_ID}/messages
Authorization: Bearer {WA_TOKEN}
Content-Type: application/json

{
  "messaging_product": "whatsapp",
  "to": "52811XXXXXXX",
  "type": "template",
  "template": {
    "name": "promo_julio",
    "language": { "code": "es_MX" },
    "components": [{
      "type": "body",
      "parameters": [{ "type": "text", "text": "Ramón" }]
    }]
  }
}
```

**Respuesta texto libre (conversación activa):**
```
POST .../messages
{
  "messaging_product": "whatsapp",
  "to": "52811XXXXXXX",
  "type": "text",
  "text": { "body": "Hola Ramón, con gusto te ayudo..." }
}
```

Formato de teléfono: siempre `52` + 10 dígitos, sin guiones ni espacios. APEX normaliza al guardar/enviar.

---

## 8. Configuración Inicial (Paso 0)

Antes de implementar, Armando localiza en Meta Business Manager:

1. **WA_TOKEN** — Token permanente de System User con permisos `whatsapp_business_messaging`
   - Ruta: Business Manager → Usuarios del sistema → tu usuario → Generar token
2. **WA_PHONE_ID** — ID numérico del número de WhatsApp Business
   - Ruta: Business Manager → Cuentas de WhatsApp → tu número → ver ID
3. **WA_APP_SECRET** — App Secret de la Meta App
   - Ruta: Meta for Developers → tu App → Configuración → Básica → App Secret
4. **WA_VERIFY_TOKEN** — string secreto que tú defines (ej. `apex_wh_2026`)
5. Configurar webhook en Meta: URL `https://apex.glass/produccion/api/whatsapp_webhook.php`, campo verificación = `apex_wh_2026`, suscribir a: `messages`, `message_deliveries`, `message_reads`

---

## 9. Fase 2 (Futura) — Facebook Ads

Agregar Tab "Ads" en el mismo módulo:
- Subir audiencia personalizada (teléfonos de clientes) a Meta Custom Audiences
- Crear campañas de retargeting y lookalike
- Aprovechar MCP tools Facebook Ads ya disponibles en Claude Code

---

## 10. Patrón SPA (obligatorio)

Siguiendo convenciones del proyecto:
- Namespace: `var ModCampanas = (function() {`
- Variables internas: `var` (no `const`/`let`)
- Sin template literals — usar concatenación de strings
- Sin arrow functions en `onclick` inline
- Funciones llamadas desde HTML expuestas vía `window.nombreFuncion`
