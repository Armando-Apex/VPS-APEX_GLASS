# APEX GLASS — MEMORIA ÚNICA DEL PROYECTO
# Sistema de Rastreo de Producción (Templadora Noreste, S.A. de C.V.)
# Última actualización: 01 octubre 2026 | Próximo UPD disponible: UPD-633

**REGLA DE ORO:** Este archivo es la ÚNICA memoria del proyecto — no memorias internas de Claude, no documentos sueltos. Todo conocimiento de features, historial de cambios y decisiones técnicas vive aquí. Claude lo lee al inicio de cada sesión y **debe actualizarlo automáticamente al terminar cualquier sesión con cambios, sin que se le pida** (nuevo UPD + refrescar "Próximo UPD disponible" en la cabecera y en la sección 13). Armando y Mando trabajan en el mismo archivo. NUNCA borrar entradas anteriores — solo agregar. Los archivos de `docs/` que este archivo enlaza (historiales archivados, `PENDIENTES_DETALLE.md`, `PENDIENTES_CERRADOS.md`) son parte de esta misma memoria; mover una entrada ahí no es borrarla.

---

## COLABORADORES

| Nombre | Correo | Rol en sistema | Área |
|--------|--------|----------------|------|
| Armando | armando@tnglass.com.mx | dir_admin | CRM, administración, reportes, inventario, finanzas |
| Mando / Areyna | areyna.sanchez@gmail.com | operaciones/piso | Producción, SmartTV, retrabajo, comunicados |
| Lina | — | administracion | Finanzas, VoBo de órdenes, registro de pagos |

Mando y Areyna son la misma persona. Sus cambios se registran como "Mando".

---

## USUARIOS CLAVE (nombre real → usuario/rol BD)

| Nombre real | Usuario BD | Rol |
|---|---|---|
| Armando | armando | dir_admin |
| Lina | admin_op (verificar) | administracion |
| Mando / Areyna | areyna | operaciones/piso |

---

## REGLAS CRÍTICAS — LEER ANTES DE TOCAR CÓDIGO

**BD y terminología:**
- ENUM estatus piezas: `pendiente, en_corte, cortado, canteado, trazo, taladro, en_horno, terminado, entregado, reproceso`
- Los valores `cortado` y `templado` son HISTÓRICOS — no eliminar del ENUM, no usar como nuevos
- ENUM estado ordenes: `pendiente_vobo, activa, entregada, cancelada`
- Campo `ubicacion` en ordenes: valores `LOCAL` y `FORANEO` (mayúsculas) — NO usar `tipo_entrega` para local/foráneo
- Campo `fecha_cierre` en ordenes: datetime, fecha real de entrega (fallback a `updated_at`)
- `CONVERT_TZ` NO usar en queries de fecha — `created_at` está en hora local Monterrey
- `ALTER TABLE ENUM`: siempre listar TODOS los valores existentes + nuevos, nunca solo los nuevos
- Filtro asesor: en BD con `LIKE '%nombre%'`, NO en frontend
- QR codes: formato `{FOLIO}-P{partida}-{n}de{total}`

**Precio en cotizaciones_partidas (IMPORTANTE):**
- `precio_unitario` no es confiable como neto en registros viejos (pre-descuento)
- SIEMPRE usar `precio_m2_usado × m2 × cantidad` para calcular bruto
- Fórmula canónica para impresión/reportes:
  ```php
  $subtotal = 0;
  foreach ($partidas as $p) {
      $subtotal += (float)$p['precio_m2_usado'] * (float)$p['m2'] * (int)$p['cantidad'];
  }
  $subtotal_neto = ($descuento > 0) ? round($subtotal * (1 - $descuento/100), 2) : $subtotal;
  $iva   = round($subtotal_neto * 0.16, 2);
  $total = round($subtotal_neto * 1.16, 2);
  ```

**Íconos SVG en dashboard.php (UPD-212):**
- `dashboard.php` tiene función PHP `icono($nombre, $size=16)` con paths Lucide inline
- Usar `<?= icono('bar-chart-2') ?>` en lugar de emojis en cualquier archivo PHP del dashboard
- NO usar CDN de Lucide — el CSP del servidor bloquea scripts externos
- Íconos disponibles: bar-chart-2, clipboard-list, layers, alert-triangle, ban, file-text, users, box, scissors, message-square, trending-up, activity, settings, megaphone, package, shopping-cart, check-square, credit-card, truck, map-pin, bell, menu

**Patrón SPA (obligatorio en TODOS los módulos):**
El SPA loader del dashboard agrega scripts al head sin limpiarlos entre navegaciones.
1. Namespace del módulo DEBE ser `var` (no `const`): `var ModX = (function() {`
2. Variables internas usan `var` (no `const/let`)
3. No usar template literals (backticks) — usar concatenación de strings
4. No usar arrow functions en onclick inline
5. Funciones que se llamen desde HTML se exponen vía `window.nombreFuncion`

**Dashboard:**
- SIEMPRE obtener `dashboard.php` del Drive (carpeta app/) antes de modificarlo
- Mando trabaja activamente en el dashboard — verificar sus cambios antes de subir

**Archivos servidor:**
- `ARCHIVOS SERVIDOR/` en Drive = estado actual en producción (fuente de verdad)
- Armando sube los archivos manualmente via FTP/AdminBolt — Claude NUNCA sube archivos

**Archivo peligroso:**
- `api/reprocesos.php` — NUNCA usar. Clona piezas con IDs nuevos. El correcto es `api/reproceso.php` (sin "s").
- CONFIRMADO ELIMINADO del servidor (12-Jun-2026) ✅

**Nómina — outsourcing (08-sep-2026):**
- Armando **NO procesa la dispersión de nómina en Apex** — paga a un despacho externo de outsourcing de personal por semana de servicio; ellos hacen la dispersión real. El módulo `?m=nomina` (UPD-572) es secundario para él por eso mismo.
- **Su semana de nómina real es JUEVES a MIÉRCOLES** (con domingo como día de descanso) — NO lunes-domingo (esa es la semana que usa Bono de Corte/`bono_pedaceria.php`, un sistema distinto sin relación con esto). El corte de asistencia se debe enviar **el miércoles** al despacho para que procesen el pago.
- Este criterio (jueves-miércoles) es el que debe usar cualquier "reporte semanal" de asistencia para nómina que se construya a partir del checador — pendiente, ver sección 12.

**Seguridad:**
- NUNCA escribir credenciales en el chat — usar .env o cPanel directamente
- SIEMPRE hacer SELECT de verificación antes de cualquier UPDATE/ALTER en producción

**Tamaño de este archivo (01-oct-2026, UPD-632) — límite de Claude Code: 150k caracteres:**
- Sección 13: UNA línea por UPD aquí; el detalle completo va a `docs/HISTORIAL_UPD_632_actual.md`. Al pasar de ~50 líneas, archivar el bloque como los anteriores.
- Sección 12: cada pendiente en máx. 2-3 líneas. Si necesita más, el texto completo va a `docs/PENDIENTES_DETALLE.md` con etiqueta `[D-nn]`. Las filas cerradas se mueven a `docs/PENDIENTES_CERRADOS.md` (moverlas no es borrarlas).
- Antes de terminar una sesión, si `wc -c CLAUDE.md` pasa de 120k, archivar.

**Memoria del proyecto (premisa, 03-jul-2026):**
- `CLAUDE.md` es la ÚNICA memoria de este proyecto — no crear documentos de memoria sueltos para hechos/features/historial del proyecto.
- Actualizarlo EN AUTOMÁTICO al terminar cualquier sesión con cambios: nuevo UPD en la sección 13, refrescar "Próximo UPD disponible" (cabecera + sección 13), sin esperar que Armando o Mando lo pidan.

---

## 1. INFRAESTRUCTURA — ESTADO ACTUAL (POST-MIGRACIÓN 14-Jun-2026)

### VPS Hostinger (ACTIVO — servidor principal)
- Plan: KVM 2 (2 vCPU, 8GB RAM, 100GB NVMe)
- OS: AlmaLinux 9 | Panel: AdminBolt
- IP: 82.29.197.33 | Hostname: srv1754712.hstgr.cloud | AdminBolt: https://panel.apex.glass/
- Dominio: apex.glass → **proxeado por Cloudflare desde ~17:53 UTC del 02-sep-2026** (confirmado por Armando; NS delegados a Cloudflare en el registrador). Ya NO es DNS directo de name.com al VPS — ese dato quedó obsoleto. IP directa del VPS (82.29.197.33) sigue respondiendo el sitio igual (P0-2, sin restringir todavía — ver sección 12).
- **`mod_remoteip` configurado (UPD-563, 03-sep-2026)** en `/etc/httpd/vhosts.d/000-cloudflare-remoteip.conf` (ojo: NO en `conf.d/`, ese directorio no se incluye en este servidor — ver nota de método abajo) — sin esto, Apache/fail2ban/PHP ven la IP de Cloudflare en vez de la del visitante real. `RemoteIPHeader CF-Connecting-IP` + los 24 rangos oficiales de Cloudflare + localhost.
- SSL: ZeroSSL activo (expira Sep 12, 2026) — **sin mecanismo de renovación automática, nunca existió uno** (ver pendiente ALTA en sección 12)

**Paths VPS:**
- Usuario del sistema: `apexglass2025`
- Home: `/home/apexglass2025/`
- Web root: `/home/apexglass2025/apex.glass/public_html/`
- App APEX: `/home/apexglass2025/apex.glass/public_html/produccion/`
- Logs PHP: `/home/apexglass2025/logs/php-fpm-error.log`
- Logs Apache: `/var/log/httpd/users/apexglass2025/apex.glass/error_log`
- Sessions: `/home/apexglass2025/tmp/sessions/`

**Base de datos VPS:**
- Motor: MariaDB 10.11.18
- BD: `apexglass2025_prod` (37 tablas importadas desde HostGator)
- Usuario: `apexglass2025_usr`
- Host conexión PHP: `::1` (IPv6 localhost, MariaDB escucha en [::]:3306)
- Puerto: 3306
- Root MariaDB: unix_socket auth (sin password, conectar como root del sistema)
- `DB_PASS` en config.php local — pendiente mover a .env fuera del webroot

**PHP y Apache VPS:**
- PHP: 8.4 via php84-php-fpm
- Pool PHP-FPM: `/etc/opt/remi/php84/php-fpm.d/00-apexglass2025-apex.glass.conf`
- Vhost AdminBolt: `/etc/httpd/vhosts.d/apexglass2025-apex.glass.conf`
- open_basedir: `/home/apexglass2025:/tmp:/var/lib/mysql`
- timezone en config.php: `SET time_zone = '-06:00'` (MariaDB no tiene tzdata cargado)
- `parse_ini_file` REMOVIDO de config.php — Google Maps keys vacías por ahora

**Claude Code en VPS:**
- Instalado en `/root/.claude/`
- MCP MySQL conectado vía `@benborla29/mcp-server-mysql`
- Proyecto: `/home/apexglass2025/apex.glass/public_html/produccion/`
- Config MCP: `MYSQL_HOST=::1`, `MYSQL_PORT=3306`, `MYSQL_USER=apexglass2025_usr`, `MYSQL_DB=apexglass2025_prod`
- **MCP Playwright reinstalado correctamente (07-sep-2026):** la config vieja de 16-jun-2026 vivía en `~/.claude/settings.json` bajo `mcpServers.playwright` — esta versión de Claude Code YA NO lee esa clave para nada (confirmado con `claude mcp list`, no aparecía). Registrado de nuevo con el comando correcto: `claude mcp add playwright -s user -e PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH=/usr/bin/chromium-browser -e DISPLAY= -- playwright-mcp --browser chromium --headless` (paquete `@playwright/mcp@0.0.76` y el binario `chromium-browser` ya estaban instalados de antes, solo faltaba el registro). Verificado con `claude mcp list` → `✔ Connected`. **Las herramientas de un MCP nuevo solo cargan al iniciar sesión** — si se acaba de registrar, hay que reiniciar la sesión de Claude Code para poder usarlas, no basta con que `claude mcp list` lo muestre conectado.

### HostGator (CANCELADO 18-jun-2026)
- Ruta: `/home3/a3026051/apex_tnglass/apex.glass/produccion/`
- BD: `a3026051_apexglass_prod`
- PHP: 8.3 | MySQL: 5.7.44 (Percona)
- cPanel user: `a3026051` | IP dedicada: 192.185.70.129

**Herramientas (fuera del webroot, no forman parte de la app):**
- `ffmpeg` 5.1.10 instalado vía RPM Fusion Free (repo agregado 17-jul-2026; EPEL no trae ffmpeg completo por licenciamiento)
- `/home/apexglass2025/herramientas/video-marketing/` — proyecto Remotion (generación de video con React) para clips cortos de marketing (WA/campañas), dueño `apexglass2025`. Ver UPD-351 (sección 13) para detalle de instalación y benchmark de render.

**Lecciones aprendidas VPS:**
- AdminBolt guarda vhosts en `/etc/httpd/vhosts.d/` (NO en conf.d/)
- Terminal browser Hostinger auto-indenta → rompe heredocs; usar Python one-liners
- MariaDB AlmaLinux 9: si falla galera, hacer `dnf clean all` antes de reinstalar
- AdminBolt bloquea upload .sql → comprimir a .zip primero
- CSP upgrade-insecure-requests de AdminBolt requiere HTTPS para que el browser funcione
- DB_PASS con caracteres especiales → usar Python getpass para no exponer en pantalla
- **AdminBolt pone `Permissions-Policy: camera=()` global** en `/etc/httpd/conf/modules-config.conf` — bloquea cámara en todos los sitios. Fix: agregar `Header always set Permissions-Policy "geolocation=(), microphone=(), camera=(self)"` en el vhost de apex.glass (443) para sobreescribirlo.
- **`httpd -t` da "Syntax OK" aunque el archivo nuevo esté en una carpeta que Apache nunca lee** — solo valida los archivos que sí están incluidos (03-sep-2026: un `.conf` puesto en `conf.d/` pasó `httpd -t` limpio y quedó completamente inerte, porque este servidor solo incluye `conf/modules-load.conf`, `conf/modules-config.conf` y `vhosts.d/*.conf`). Verificar SIEMPRE con `httpd -t -D DUMP_INCLUDES` que el archivo nuevo aparece en la lista antes de dar por buena una config nueva de Apache.
- `mod_security` se activa desde `conf/modules-config.conf` (si está, sí corre). `mod_evasive`, en cambio, tenía su config en `conf.d/` — nunca ha estado activo en este servidor (confirmado 03-sep-2026).
- **Editar `.env` como root le cambia el owner a `root:root` y le quita el permiso de lectura a `apexglass2025`** (el archivo vive en `640 apexglass2025:apexglass2025`) — PHP-FPM corre como `apexglass2025`, así que en cuanto pierde acceso de lectura, `config.php` calla el error (`error_log`, sin excepción) y **todas las constantes del `.env` quedan vacías, incluyendo `DB_PASS`** → el sitio completo se cae con "No database selected" para cualquier request que toque BD. Pasó en vivo el 08-sep-2026 (UPD-570, ver detalle ahí) con ~4 minutos de caída real hasta notar el patrón en `php-fpm-error.log` y corregir con `chown apexglass2025:apexglass2025 .env && chmod 640 .env`. **Regla para cualquier sesión futura corriendo como root:** después de tocar `.env` con Write/Edit, correr `chown apexglass2025:apexglass2025 /home/apexglass2025/apex.glass/.env` de inmediato — no asumir que el permiso se conserva.

---

## 2. ESTRUCTURA DE ARCHIVOS EN SERVIDOR

```
produccion/
├── api/                          ← APIs PHP
├── app/
│   ├── dashboard.php             ← SPA contenedor principal
│   ├── operador.php              ← escáner QR operadores
│   ├── jefe_movil.php            ← vista móvil jefe_piso
│   ├── produccion_estaciones.php ← SmartTV planta (sin login)
│   ├── imprimir_orden.php
│   ├── imprimir_cotizacion.php
│   ├── imprimir_etiquetas.php
│   ├── imprimir_salida.php
│   ├── corte_dashboard.php
│   └── modulos/                  ← módulos SPA cargados dinámicamente
├── portal/                       ← Portal clientes externo
│   ├── index.php
│   ├── dashboard.php
│   └── orden.php
├── archivos_ordenes/             ← archivos subidos (protegida con .htaccess)
└── lib/jsqr.min.js
```

Convención nombres proyecto Claude:
- `API-archivo.php` → `api/archivo.php`
- `APP-archivo.php` → `app/archivo.php`
- `modulos-archivo.php` → `app/modulos/archivo.php`

---

## 3. BASE DE DATOS — 37 TABLAS

| Categoría | Tablas |
|---|---|
| Clientes | clientes, clientes_bitacora |
| Cotizaciones | cotizaciones, cotizaciones_partidas, cotizacion_pagos, croquis_partidas, autorizaciones_descuento |
| Órdenes de trabajo | ordenes, historial_estatus, reprocesos, orden_archivos |
| Órdenes de compra | ordenes_compra, oc_partidas, oc_pagos, oc_entrega_detalle, oc_entregas, oc_consecutivo |
| Cristales / Láminas | cristales, cristales_historial, laminas, corte_laminas, piezas |
| Inventario | inventario_compras, inventario_movimientos |
| Rutas / Entrega | rutas, ruta_entregas, ruta_entrega_piezas |
| Proveedores | proveedores |
| Usuarios / Auth | usuarios, login_intentos, folios_control |
| Notificaciones | notificaciones, notificaciones_leidas_usuario, comunicados |
| Finanzas | clientes_saldo_favor |
| Otros | festivos |

**Tablas clave:**
| Tabla | Notas |
|---|---|
| cotizaciones | descuento, subtotal, iva, total, saldo_pendiente, saldo_pagado, vobo_por, vobo_at, estatus_pago, express |
| cotizaciones_partidas | precio_m2_usado, m2, cantidad, precio_unitario, requiere_templado |
| cotizacion_pagos | fecha_pago, hora_pago, monto, forma_pago(efectivo/tarjeta/transferencia/saldo_favor), registrado_por |
| ordenes | estado ENUM(pendiente_vobo,activa,entregada,cancelada), ubicacion(LOCAL/FORANEO), fecha_cierre |
| autorizaciones_descuento | descuentos >10% requieren aprobación dir_admin |
| cristales | precio_m2 = precio público de referencia |
| folios_control | modo_cot=produccion, letra_actual=S, numero_actual=38 (S-001…S-038) |

---

## 4. FLUJOS PRINCIPALES

### Flujo de producción
```
pendiente → en_corte → cortado → canteado → trazo → taladro → en_horno → terminado → entregado
```
Sin templado (requiere_templado=0): salta en_horno.

### Flujo cotización → orden
1. Asesor crea cotización
2. Si descuento >10% → requiere autorización dir_admin (módulo autorizaciones)
3. Cliente aprueba → asesor convierte a Orden (estado: pendiente_vobo)
4. Lina ve en Finanzas > VoBo → registra pago → da VoBo
5. Sistema calcula fecha entrega → Orden pasa a activa
6. Producción arranca. Etiquetas QR disponibles solo post-VoBo.
7. Toda la orden llega a Terminado → alerta a Lina + asesor
8. Lina actualiza estatus_pago en Cobranza → botón Salida se desbloquea

**Fechas entrega al VoBo:**
- Local MTY = +5 días hábiles
- Foráneo Saltillo = siguiente viernes
- Express = +3 días hábiles

---

## 5. MÓDULOS SPA — LISTADO COMPLETO

| Módulo | Archivo | Namespace | Responsable |
|---|---|---|---|
| Resumen | modulos/resumen.php | ModResumen | Armando |
| Órdenes | modulos/ordenes.php | ModOrdenes | Armando |
| Estaciones | modulos/estaciones.php | ModEstaciones | Armando |
| Retrabajo | modulos/retrabajo.php | ModRetrabajo | Mando |
| Cotizaciones lista | modulos/cotizaciones.php | ModCotizaciones | Armando |
| Cotización detalle | modulos/cotizacion.php | ModCotizacion | Armando |
| Clientes | modulos/clientes.php | ModClientes | Armando |
| Cristales | modulos/cristales.php | ModCristales | Armando |
| Inventario | modulos/inventario.php | ModInventario | Armando |
| VoBo Órdenes | modulos/finanzas_vobo.php | ModFinanzasVobo | Armando |
| Cobranza | modulos/finanzas_cobranza.php | ModFinanzasCobranza | Armando |
| Admin Órdenes | modulos/admin_ordenes.php | ModAdminOrdenes | Armando |
| Admin Comunicados | modulos/admin_comunicados.php | ModAdminComunicados | Mando |
| Reporte Dirección | modulos/reporte_direccion.php | ModReporte | Armando |
| Productividad | modulos/productividad.php | ModProductividad | Armando |
| Optimizador Corte | modulos/optimizador.php | ModOptimizador | Armando |
| Logística Rutas | modulos/logistica_rutas.php | ModLogisticaRutas | Mando |
| Archivos Órdenes | modulos/archivos_ordenes.php | ModArchivosOrdenes | Mando |
| Croquis Técnicos | modulos/croquis.php | ModCroquis | Mando |
| Orden detalle | modulos/orden.php | — | Armando |
| Campañas WhatsApp | modulos/campanas.php | ModCampanas | Armando |
| Contabilidad — Catálogo de Cuentas (WIP) | modulos/contabilidad_catalogo.php | ModCatalogoContable | Armando |
| Archivos de Video (file manager, solo dir_admin) | modulos/media_manager.php | ModMedia | Armando |

---

## 6. ROLES Y PERMISOS

| Rol | Acceso |
|---|---|
| operador | Solo su estación |
| chofer | registrar_entrega |
| jefe_piso | cambiar_cualquier_estatus |
| comercial | ver_ordenes, cotizaciones propias |
| director | ver_reportes |
| dir_admin | todo |
| administracion | inventario + finanzas (VoBo) |
| dueno | producción + comercial + reportes + finanzas + inventario |

Variables PHP sidebar:
```php
$esDir        = in_array($_rol, ['dueno','dir_admin','director','administracion']);
$esComercial  = in_array($_rol, ['dueno','dir_admin','comercial','administracion']);
$esAdmin      = $_rol === 'dir_admin';
$esInventario = in_array($_rol, ['dir_admin','administracion','dueno']);
$esFinanzas   = in_array($_rol, ['dir_admin','administracion','dueno']);
```

---

## 7. APIS PRINCIPALES

| API | Descripción |
|---|---|
| api/dashboard.php | Resumen + movimientos paginado 15 |
| api/ordenes.php | 4 secciones + búsqueda global en BD |
| api/cotizaciones.php | CRUD completo + acciones (convertir, cancelar, vobo) |
| api/finanzas.php | VoBo: lista pendiente_vobo, registrar pago, dar VoBo, calcular fecha |
| api/autorizaciones.php | Flujo autorización descuentos >10% |
| api/correcciones.php | Correcciones dir_admin con log |
| api/reporte_direccion.php | KPIs dirección |
| api/reporte_detalle.php | Detalle órdenes por KPI clickable |
| api/estaciones.php | Piezas por estación (solo órdenes activas) |
| api/admin_ordenes.php | Cancelar/restaurar/corregir_estatus masivo |
| api/actualizar_estatus.php | Cambio estatus con validación de flujo + sin templado |
| api/optimizador_corte.php | Límite ≤4 órdenes; 30 shuffles; stock vs necesario |
| api/productividad.php | Métricas por estación |
| api/retrabajo.php | Órdenes con piezas en reproceso |
| api/notificaciones.php | CRUD notificaciones |
| api/clientes.php | CRUD clientes + portal password |
| api/cristales.php | CRUD cristales catálogo |
| api/laminas.php | CRUD láminas + stock + alertas |
| api/inventario.php | Compras y movimientos |
| api/ordenes_compra.php | OCs con entregas y pagos |
| api/rutas.php | Rutas + Google Maps + marcar piezas |
| api/archivos_ordenes.php | Subida y consulta de archivos por orden |
| api/croquis.php | CRUD croquis técnicos por partida |
| api/reproceso.php | Retrabajo piezas (SIN "s" — EL CORRECTO) |
| api/portal_clientes.php | Portal: generar_pass, login, logout |
| api/login.php / logout.php | Auth sistema interno |
| api/permisos.php | Mapa de permisos por rol (include) |
| api/recibir_orden.php | Recibe órdenes desde Google Apps Script |

---

## 8. TABLERO SMARTTV

- Sin login requerido. Bloqueado de buscadores (robots.txt + meta noindex).
- Optimizado para TV 1920x1080 ONN Google TV. 8 columnas.
- Auto-scroll: 28px/segundo via requestAnimationFrame, independiente por columna.
- Intervalo actualización: 120 segundos.
- Popup nueva orden: detecta folios nuevos cada 30 segundos, muestra 3 segundos barra naranja.

---

## 9. PORTAL CLIENTES

- URL: https://apex.glass/produccion/portal/
- Login: código CTN + contraseña 8 caracteres generada por admin.
- Seguridad: bcrypt cost 12, session_regenerate_id, protección timing attack.
- Solo lectura. Diseño: Outfit font, tokens CSS, border-radius 4px, acento naranja.

---

## 10. IDs GOOGLE DRIVE

| Recurso | ID |
|---|---|
| Carpeta raíz "Proyecto APEX GLASS - Colaboracion" | 1iTNZ2fgjKC-DiSmq-N-NUykfZCUiXfTI |
| ARCHIVOS SERVIDOR | 1ijZVTT5gFCsl--9eD2fQl8_rqhxng1Ip |
| api/ | 1pMefwwWKi1Fbd_A5XExplnSqk8jBvDBj |
| app/ | 1-rfw0uh3-T90xWhbxZm139c9PbWRl1_p |
| app/modulos/ | 1olUd1dagqt0Piz-ccOTp4tXWk9lpW1_9 |
| Memorias_Tecnicas_Historial | 1mmyceQ-1jrEXhC7HNInZu-Ka4_Qp7qCr |
| Memoria Técnica Google Doc (canónica) | 1ZNUJe_b6aUyN3IYjCqgZVzvYnGVLL6HxULHeDOGx4NM |

---

## 11. FEATURE PLANIFICADA: ÓRDENES EXPRESS

1. BD: `ALTER TABLE cotizaciones ADD COLUMN express TINYINT(1) NOT NULL DEFAULT 0;`
2. Precio mínimo: cada partida >= `cristales.precio_m2 × 1.15` (validación frontend + backend)
3. Fecha entrega al VoBo: máx 3 días hábiles (vs 5 normal) — afecta `calcularFechaVobo()` en `api/finanzas.php`
4. Badge "EXPRESS" visible en lista órdenes, producción y reporte dirección; prioridad al ordenar
5. Revisar columnas `cotizaciones_partidas` antes de implementar

---

## 12. PENDIENTES ACTIVOS

> Las filas cerradas viven en `docs/PENDIENTES_CERRADOS.md`. Las marcadas `[D-nn]` tienen su texto completo en `docs/PENDIENTES_DETALLE.md#d-nn`. Al cerrar una fila, moverla a PENDIENTES_CERRADOS.md (no borrarla).

| Prioridad | Resp. | Tarea | Estado |
|---|---|---|---|
| ALTA | Armando | Agregar UPDs 059+ al Google Doc (cambios 12-14 jun) | Pendiente |
| MEDIA | Armando | Cargar tzdata en MariaDB VPS | Pendiente |
| MEDIA | Armando | Instalar n8n via Docker (n8n.apex.glass, puerto 5678) | Pendiente |
| MEDIA | Armando | Feature Órdenes Express | Pendiente |
| MEDIA | Armando | Google Sheets / Apps Script — verificar columna M | Pendiente |
| MEDIA | Mando | Completar módulo Retrabajo: modal + razones por estación | Pendiente |
| MEDIA | Mando | Rutas: optimización de zonas | Pendiente |
| MEDIA | Ambos | Alerta reorden automática láminas (esperar 2-3 semanas historial) | Pendiente |
| BAJA | Armando | Error consola JS guardarCristal | Pendiente |
| BAJA | Ambos | m2_requeridos en laminas.php | Pendiente |
| MANUAL | Armando | Capturar precios: Claro 12mm, Claro Zafiro 9mm, Filtrasol 9mm, Tintex 6mm, Tintex 9mm | Pendiente |
| MEDIA | Armando | SEGURIDAD: SSH hardening — authorized_keys de root está VACÍO; se redujo MaxAuthTries 6→3 y LoginGraceTime 120s→30s; deshabilitar PasswordAuth requiere configurar llaves primero | PARCIAL UPD-243 |
| MEDIA | Armando | UX: Dark mode en dashboard (topbar ya es oscuro, extender al sidebar y contenido) | Pendiente |
| BAJA | Armando | UX: Paginación resumen con total de registros "Mostrando X–Y de Z órdenes" | Pendiente |
| MEDIA | Mando | AUDIT Fix 8: CSS compartido (app/shared.css) — extraer .page-title, .page-sub, .btn-*, .modal-*, badges; skip por ahora porque valores inconsistentes entre módulos activos; hacer gradualmente al tocar cada módulo | Pendiente |
| MEDIA | Mando | AUDIT Fix 10: Mover CORS/Content-Type boilerplate a api/config.php — skip por ahora porque rompe endpoints que sirven PDFs/archivos (facturapi.php, archivos_ordenes.php); requiere refactorizar esos primero | Pendiente |
| MEDIA | Mando | AUDIT Fix 11: Split módulos grandes — cotizacion.php (1854 líneas), inventario.php (1715), croquis.php (1527); skip por ahora por actividad activa; hacer cuando haya pausa natural en desarrollo | Pendiente |
| MEDIA | Ambos | Performance: índices BD — hacer cuando producción esté inactiva (fin de semana/noche): `CREATE INDEX idx_piezas_estatus_orden ON piezas(estatus, orden_id)`, `idx_historial_pieza_estatus_fecha ON historial_estatus(pieza_id, estatus_nuevo, created_at)`, `idx_historial_creado ON historial_estatus(created_at, estatus_nuevo)`, `idx_ordenes_estado_cierre ON ordenes(estado, fecha_cierre)` | Pendiente |
| MEDIA | Armando | SEGURIDAD: `app/modulos/cotizacion.php` (`ModCotizacion._buscarCliente`) tiene el mismo patrón de XSS corregido en UPD-275 para maquila — `escJs()` solo escapa `\`/`'` pero el nombre del cliente se concatena dentro de un atributo `onclick="..."` con comillas dobles; un cliente con `"` en razón social rompe el atributo. Aplicar el mismo fix (DOM/addEventListener en vez de concatenación) | Pendiente |
| MEDIA | Armando | Campañas WA segmentadas mensuales (4 segmentos: frecuentes/compradores del mes/cotizó sin comprar/sin cotizar en el mes) — correr `scripts/generar_campanas_segmentadas.php` día 25-28 con los 4 templates Meta del mes, revisar y dar OK de envío por campaña en el módulo Campañas | RECURRENTE — primera corrida UPD-265 (jun-2026, campañas #18-21), trigger mensual automático día 26 |
| MEDIA | Mando | Facturación — revisar a fondo el flujo de cancelación de CFDI ante el SAT (`accion=cancelar` en api/facturapi.php, UPD-280) antes de usarlo en real: motivos, plazos de 72h/$1000, aceptación del receptor en su buzón SAT | Pendiente (07-jul-2026) |
| ALTA | Armando | Saldo a Favor / Depósito a Cuenta — parte fiscal resuelta con el esquema A de anticipos (UPD-623 a 629); ya no se crean órdenes placeholder, se usa "Registrar Depósito" en Cobranza. Queda: limpiar las órdenes placeholder históricas (Armando debe pasar los folios) y revisar el XSS de `sfSelCliente` (patrón UPD-275). [D-42] | Pendiente — limpieza histórica + XSS |
| ALTA | Armando | QR de salida por chofer (UPD-319) — plantillas Meta aprobadas, teléfonos de asesoras y nombres de choferes cargados. Falta prueba física con un chofer real escaneando el QR. [D-43] | HECHO (config) — falta prueba física |
| ALTA | Mando | GPS ProTrack365 en Logística Rutas (UPD-327/328/338/339) — funcionando (cron `scripts/gps_tracker.php` cada minuto, histórico en `gps_posiciones`). Falta pedir al distribuidor la Open API oficial (hoy depende de un fallback web no documentado). [D-44] | Mayormente HECHO — falta Open API oficial del distribuidor |
| MEDIA | Mando | Radio de "llegada GPS" (250m, ver UPD-338/339) — con la primera prueba real el camión quedó a 268m sin disparar. Evaluar subir a 300-350m con más pruebas | Pendiente |
| MEDIA | Mando | Trazabilidad de rutas (UPD-339/340) — falta prueba con un chofer real completando el flujo físico completo (escaneo QR salida → manejar → llegar) para confirmar que las 4 columnas de la tabla en Productividad se llenan solas | Pendiente |
| MEDIA | Ambos | Rutas de Entrega — activar avisos WA (UPD-355): redactar y aprobar en Meta las plantillas `ruta_iniciada_eta_cliente` (ETA a la 1ra parada) y `ruta_en_curso_cliente` (aviso genérico "en camino, llega hoy" al resto); confirmar si se sigue reusando `siguiente_entrega_cliente` para el aviso "eres el siguiente" tras cada entrega confirmada; una vez listas, cambiar `RUTA_WA_AVISOS_ACTIVO` a `true` en `api/rutas_lib.php` | Pendiente |
| MEDIA | Ambos | Rutas de Entrega — probar con un chofer real el nuevo significado del QR de hoja de ruta (ahora escanea al ENTREGAR, no al salir) y confirmar que el mapa avanza solo a la siguiente parada | Pendiente |
| MEDIA | Mando | Contabilidad — Fases 0-5 HECHAS (Catálogo, Mapeo Compras, Nómina, Gastos Fijos, Caja Chica, P&L); partida doble Fases 6.0-6.3 construida (ver bloque 398-499). Falta probar captura real y comparar el P&L contra el Excel de Armando de 1-2 meses cerrados. Ojo: el costo de ventas por consumo real solo es confiable desde jul-2026. [D-50] | Falta probar captura real y validar contra Excel |
| MEDIA | Ambos | Contabilidad — probar en navegador todos los módulos con datos reales (lógica de ingresos/costo en `api/helpers/pnl_datos.php`) y validar el P&L contra el Excel. Las 8 tablas de Contabilidad están aisladas: borrar filas de prueba en ellas no afecta a ningún otro módulo. [D-51] | Pendiente — probar todos los módulos y validar contra el Excel |
| ALTA | Armando | AVISO ARMANDO — revisar `/home/mando/files_apexglass/auditoria_contabilidad_partida_doble_2026-08-03.md`: `movimientos_contables` (P&L) y `polizas_lineas` (Balance) son libros separados sin reconciliar; sin versionado de correcciones, maker-checker, motivo al anular póliza ni cierre de periodo. [D-52] | Pendiente — Armando debe revisar el archivo y decidir qué se corrige |
| MEDIA | Mando | AVISO MANDO — `scripts/gps_cache/` (token ProTrack ya expirado) se subió a git en el commit `b3dc5bb`; ya está en `.gitignore` y fuera del tracking, no se purga el historial. Confirmar que el cache GPS funciona bien sin estar trackeado. [D-53] | Pendiente — solo revisión/confirmación |
| MEDIA | Armando | Videos Remotion (UPD-351/352) en `herramientas/video-marketing/` — 3 muestras hechas, ninguna conectada a campaña. Falta feedback de Armando y revisar si `app/modulos/campanas.php` necesita soporte para plantillas con header VIDEO. [D-54] | Pendiente — esperando feedback de Armando |
| MEDIA | Armando | Sprint1 (dinero) — revisar manualmente **COT-0105** (rechazada históricamente): `saldo_pagado=$1,297.05` nunca se reseteó a 0 aunque el saldo a favor ya se depositó correctamente (mismo monto) — hoy se cuenta doble en reportes hasta que se corrija a mano | Pendiente |
| MEDIA | Mando | **Sprint2 (Producción/Piso) — falta C-6** (ver UPD-361): "Registrar consumo" en el Optimizador de Corte (`api/optimizador_corte.php`) sigue sin descontar inventario real — solo escribe en la tabla muerta `corte_laminas`, el stock nunca baja y las piezas no pasan a `en_corte` (riesgo de doble corte en la siguiente corrida). Mando decidió manejar el descuento de inventario de otra forma; se retoma al cerrar el sprint | Pendiente — Mando lo maneja diferente |
| BAJA | Ambos | Sprint2 — 2 cambios de comportamiento visibles para piso ya en producción (UPD-361): (1) el botón "Confirmar omisión" en `operador.php` ya no funciona para operadores/choferes, solo jefe_piso+ (C-4); (2) escanear QR/CNC ya no funciona en órdenes sin VoBo — `pendiente_vobo`/`cancelada`/`rechazada`/`entregada` (A-4). Avisar a piso si aún no se ha comunicado | Pendiente — confirmar que piso ya lo sabe |
| ALTA | Armando | Referidos (UPD-450) — falta dar de alta en Meta la plantilla `referido_saldo_abonado` (UTILITY, 3 variables: referente, monto abonado, referido); sin ella el saldo se abona bien pero el aviso WA falla en silencio. Falta prueba visual (campo en Cotizaciones, impresión, `portal/referidos.php`). [D-59] | Pendiente — falta plantilla Meta + prueba visual |
| MEDIA | Armando | Costo de compra por período (`api/inventario.php` accion=costo_promedio, UPD-473) no es costeo promedio real: solo suma compras dentro del período e ignora el inventario arrastrado de meses anteriores. [D-60] | Pendiente — diseño a definir |
| MEDIA | Armando | Insulado/espaciador por ml (UPD-498/499) — falta registrar espaciadores por tamaño con su precio y prueba visual. (El separador por m² de UPD-613/614 ya existe.) [D-61] | HECHO UPD-499 — falta precios por tamaño + prueba visual |
| BAJA | Armando | S2-08 (auditoría, UPD-503): rollover de folio `Z-999`→`[-001` rompe QR — hoy en letra S, sin urgencia. Requiere `ALTER TABLE folios_control` (VARCHAR(2)) + tocar QR/escáner. Diseño propuesto: doble letra estilo Excel (AA, AB...) | Pendiente — sin urgencia |
| MEDIA | Ambos | S2-14 fase 2 (auditoría, UPD-504): quitar el fallback de autorización de portal por nombre (`cliente_id OR cliente_nombre`) — hoy solo se loguea cada ocurrencia (`[S2-14]` en error_log). Revisar el log en unas semanas; si está limpio, quitar el fallback; si no, corregir esas órdenes primero | Pendiente — esperando datos del log |
| BAJA | Armando | Sprint 2 S2-12: falta correr `git rm --cached error_log api/error_log app/error_log app/modulos/error_log` + commit (Claude no corre git) | Pendiente — acción manual de Armando |
| MEDIA | Mando | Sprint 3 auditoría (UPD-505) — falta: sweep de emojis (`operador.php`, SmartTV, `admin_comunicados`, `logistica_rutas`, `facturacion`, `clientes`, `chofer_ruta`), migrar ~338 `alert()`/`confirm()` a `toast()`/`confirmar()`, S3-05 labels/aria, S3-06 validación visual, S3-09 paginación, S3-10 responsive. [D-66] | Pendiente — retomar en sesión dedicada |
| ALTA | Armando | Promo WA por volumen (UPD-516/517, código `CTN-###PROMO`; tramos 1-4→5%, 5-50→7.5%, 51-99→12.5%, 100+→20%) — falta el gráfico del Estado de Armando y prueba visual. Riesgo: 100+ al 20% deja ~$341/m² en Claro 9mm con el costo de compra más caro. [D-67] | Pendiente — falta gráfico de Armando + prueba visual |
| ALTA | Ambos | Auditoría v2 19-ago (`/home/mando/files_apexglass/auditoria_19_08_26/`) — falta A-04 (backfill de `cliente_id` en 216 órdenes antes de quitar el fallback del portal por nombre), C-04 rotar token de verificación WA (Armando, Meta BM), C-05 `git rm --cached` de los `error_log`, D-03 a D-07. [D-68] | Pendiente — sesión dedicada |
| ALTA | Ambos | Auditoría externa 20-ago (`/home/mando/files_apexglass/auditoria_20_08_26/`) — P0/P1/P2 cerrados. Quedan sueltos: UX-7, UX-12, UX-4 (rebrand de paleta, requiere decisión), UX-13, UX-16, ~190 labels sin `for`/`id`, y medios/bajos de lógica de negocio / edge cases. [D-69] | P2 cerrado — sueltos sin fecha |
| MEDIA | Armando | Certificado SSL de apex.glass vigente hasta **02-dic-2026** (se renovó solo el 03-sep, causa sin confirmar; no existe cron/timer de renovación). Revisar ANTES del 02-dic y confirmar el modo SSL de Cloudflare. [D-70] | Pendiente — revisar antes del 02-dic-2026 |
| MEDIA | Mando | SSH puerto 2222 (UPD-562) se perdió tras el reinicio del 2-sep (la regla de firewall era solo runtime). Avisar a Armando que la prueba previa nunca fue válida; reponer la regla desde AdminBolt para que sobreviva reinicios. [D-71] | Pendiente |
| MEDIA | Mando | Journal de systemd no persistente (P1-3): `mkdir -p /var/log/journal` + `systemd-tmpfiles --create` + reiniciar journald con `SystemMaxUse` acotado. [D-72] | Pendiente |
| MEDIA | Mando | Blindar contra AdminBolt (P1-4) los archivos con parches manuales que puede pisar: `php.d/zzz-apex.glass.ini` (sesión 8h, subidas), `sshd_config.d/01-custom.conf`, `vhosts.d/000-cloudflare-remoteip.conf`. Ideal: configurarlos desde el panel donde se pueda. [D-73] | Pendiente |
| ALTA | Armando | Fase B Cloudflare (UPD-563), requiere el panel: (1) modo SSL Full strict; (2) MX/SPF de apex.glass siguen en HostGator → correo @apex.glass roto; (3) `panel.apex.glass` no debería ir proxeado; (4) Bot Fight Mode bloquea clientes no-browser (UPD-574); (5) cerrar el origen 443 solo a rangos de Cloudflare (P0-2). [D-74] | Pendiente — esperando que Armando tenga acceso al panel |
| MEDIA | Ambos | WhatsApp BSUID (Meta) — username `@apex.glass` reservado. Webhook, `whatsapp_conversaciones`, `campana_envios` y el matching de clientes dependen del teléfono. Canario `[BSUID?]` en error_log (UPD-538): migrar cuando aparezca. [D-75] | En espera del canario |
| ALTA | Armando | Checador ZKTeco Fase 2 — construida (UPD-570 a 576: RH, cola alta/baja VPS↔Pi, listener en la Pi, Asistencia jueves-miércoles). Alta remota solo crea PIN+nombre; huella/rostro requiere presencia física. Todo el tráfico lo inicia la Pi. Falta probar el alta de un empleado nuevo en el reloj. [D-76] | Mayormente HECHO — falta probar alta en el reloj |
| MEDIA | Armando | Cotizador de Insulados — HECHO UPD-613/614 (separador por m² $750 con IVA). Falta prueba de Armando en navegador. [D-77] | HECHO — falta prueba visual |
| MEDIA | Armando | Reporte Dirección — la tarjeta global "Cotizaciones" (`stmtConv`, `api/reporte_direccion.php` ~L614) cuenta órdenes `pendiente_vobo` y retrabajo. Aplicar el mismo fix de UPD-579 (LEFT JOIN ordenes + estado activa/entregada + `es_retrabajo=0`). Aun así nunca igualará a "Órdenes" (cohortes distintas). [D-78] | Pendiente — "lo revisamos después" |
| BAJA | Armando | Encuesta de Satisfacción (UPD-588 a 590) — en producción. Solo falta que Armando retire la plantilla vieja `encuesta_clientes` en Meta y prueba visual del campo "Código de Encuesta". [D-79] | Casi cerrado |
| BAJA | Armando | `portal/cotizacion.php` calcula sus totales usando solo `cotizaciones.descuento` (manual) — nunca incluyó `descuento_referido` y, desde UPD-588, tampoco `descuento_encuesta`. El portal de clientes puede mostrar un total distinto al real en cualquier cotización con descuento automático (referido o encuesta). Hallazgo encontrado de paso en UPD-588, preexistente, sin corregir por estar fuera de alcance | Pendiente — sin fecha |
| ALTA | Armando | Encuesta — plantilla `encuesta_codigo_reactivado` (UTILITY: "Hola {{1}}, tu código de descuento por haber contestado nuestra encuesta {{2}} sigue vigente. Nueva fecha límite: {{3}}.") para avisar a los 28 códigos reactivados (UPD-596). Decidir si se reintenta con los 34 fallidos de UPD-590. [D-81] | Pendiente — falta plantilla Meta |
| BAJA | Mando | No existe "agregar partida" a una cotización/orden ya convertida (UPD-598; S-893 se hizo por SQL). Construirlo solo si el caso se repite. [D-82] | Pendiente — sin urgencia, solo si se repite |
| **ALTA** | **Ambos** | **⭐ FACTURACIÓN — CHECKLIST PARA LIVE (índice maestro) ⭐** Hechos: (1) manifiesto firmado, (3) prueba borrada. **Bloqueante:** (2) `FACTURAPI_KEY_LIVE` + `FACTURAPI_MODE=live` vía AdminBolt, solo después de 4-6. **Contador:** (4) confirmar trato de anticipos/saldo a favor (el 50% de una orden pactada = parcialidad/PPD, no anticipo); (5) notas de crédito manuales por devolución/descuento (relación 01) siguen bloqueadas; (6) catálogo de claves SAT por producto. **Mando:** (7) respaldar `archivos_facturas/` a Drive (hoy solo viven en el VPS, fuera de git); (8) reporte facturado vs. sin facturar; (9) reactivar correo probando con un correo interno. **Continuo:** (10) CSF de clientes (15 de ~396 completos; validar con `scripts/validar_datos_fiscales.php`). Arranque 01-oct-2026; candado de fecha propuesto sin aplicar. [D-83] | Pendiente — falta 2 (live); contador 4-6; Mando 7-9; continuo 10 |
| ALTA | Ambos | Facturación — plan por sprints (UPD-604). Sprints 1-2 cerrados; Sprint 3 al 60% (falta catálogo de claves SAT). Sprint 4: reporte facturado vs. ventas, serie definitiva, alerts/emojis, paginación (tope 200), columnas folio de orden/correo. Sprint 5: complementos HECHOS (UPD-615), notas de anticipo (UPD-625). Decisión: todo se prueba en sandbox antes de live. [D-85] | Sprints 1-2 cerrados; 3 al 60%; 4 pendiente |
| BAJA | Mando | `api/clientes.php` — rarezas de gates por rol (dueno puede `guardar_fiscal` pero no `editar_contacto`/`editar_nombre`; comercial sí puede `guardar_fiscal`). Decisión de producto, no agujero. [D-86] | Pendiente — sin urgencia, no es un agujero |
| ALTA | Ambos | Envío de CFDI por correo APAGADO: `FACTURACION_ENVIO_CORREO_ACTIVO` en `api/facturapi.php` + `FAC_CORREO_ACTIVO` en `app/modulos/facturacion.php` (cambiar LOS DOS). Además no envía si el modo no es live. Probar con un correo interno: en sandbox le llegó un CFDI de prueba a un cliente real (CRISTAL Y ALGO MAS). [D-87] | Pendiente — reactivar al final |
| ALTA | Armando | Anticipos esquema A (UPD-623 a 629) — Fases 1-4 HECHAS. Falta prueba en navegador (depósito con forma, Anticipos por facturar, factura con anticipo + nota N, cancelación de orden pagada con saldo) y validación del contador (PEPS, referido como descuento, saldos previos al 01-oct). [D-89] | Fases 1-4 HECHAS — falta prueba en navegador y validación del contador |
| ALTA | Lina | **Revisar los 45 depósitos de saldo a favor anteriores al 01-oct ($195,164.69) en Cobranza → Saldo a Favor → "Anticipos anteriores al 1-oct"** y marcar de cada uno si se facturó como anticipo en CONTPAQi (si sí: folio + UUID). Mientras no se revise, no se puede timbrar la factura de una orden que use ese saldo (UPD-629) | Pendiente — Lina, 30-sep-2026 |
| ALTA | Armando | **Folios de Apex vs. CONTPAQi:** hoy se factura en CONTPAQi con folios numéricos (ej. 8754); Apex arranca las series A (facturas), P (complementos) y N (notas de crédito) en 1. El SAT no exige consecutivos (serie y folio son de control interno). Recomendación dada: si CONTPAQi deja de usarse, continuar la numeración de facturas (A-8755…); P y N desde 1. Falta que Armando confirme el último folio de CONTPAQi y si se seguirá usando en paralelo; después, agregar un folio inicial configurable por serie | Pendiente — decisión de Armando |
| MEDIA | Armando | PROYECTO buzón de facturas de proveedores → OC "por revisar" (30-sep, nada construido). `proveedores` no tiene RFC; el MX de apex.glass está roto (usar `facturas@tnglass.com.mx`). Fase 1 sugerida: RFC en proveedores + subir XML a mano. Decisiones: (a) ¿la OC existe antes o nace con la factura?, (b) estado "por revisar", (c) crear la cuenta de correo. [D-92] | Pendiente — definir (a)(b)(c) y armar plan por fases |
| MEDIA | Armando | Descuentos/campañas Claro 9mm/6mm — EN PAUSA (24-sep), solo análisis: descuento real ~18-22%, merma neta 7.9%/15.4%; precios propuestos campaña $835/$585 y mayoreo $800/$550 con IVA; revisar COT-1752 (99.9% de descuento). [D-93] | En pausa — retomar cuando Armando lo pida |

---

## 13. HISTORIAL DE ACTUALIZACIONES

REGLA: Cada cambio se agrega aquí. NUNCA se elimina. Código UPD secuencial e irrepetible.
Próximo UPD disponible: **UPD-633**

### Bloque archivado: UPD-001 a UPD-100
Archivo completo: `HISTORIAL_UPD_001_100.md` (30-may-2026 → 18-jun-2026)

**Resumen del bloque:**
- Módulos core construidos: Órdenes, Cotizaciones (reescritura SPA), Inventario, Finanzas VoBo, Portal Clientes, Rutas (Google Maps), Archivos Órdenes, Croquis Técnicos, SmartTV
- Flujos clave: sin templado (UPD-018), VoBo + saldo_a_favor (UPD-022/057), autorizaciones descuentos >10% (UPD-059/062), optimizador corte (UPD-063/064)
- Producción: fix cámara QR Android (UPD-075), operador horno en 2 pasos (UPD-076), servicios adicionales por partida (UPD-090)
- Infraestructura: MIGRACIÓN VPS Hostinger (UPD-071/072), HostGator cancelado (UPD-095), backup BD automático (UPD-046/093/094)
- Correcciones totales: fix precio bruto/neto en finanzas y cotizaciones (UPD-069/088/089/092)
- Reporte Dirección: 6 KPIs nuevos (UPD-085), fix retraso por fecha_terminado (UPD-086)
- Seguridad inicial: SQL injection fixes (UPD-038/039/058), credenciales FTP rotadas (UPD-035)

**Contexto al cerrar bloque (UPD-100):** ordenes_compra tenía columnas tipo/categoria listas en BD pero sin lógica ni UI. Pendientes entrantes al bloque siguiente: módulo Compras completo, Top Clientes 3 paneles, rentabilidad m², sistema omisiones de estación, módulo Campañas WhatsApp, hardening de seguridad completo (CORS/CSRF/headers/credenciales).

---

### Bloque archivado: UPD-101 a UPD-150
Archivo completo: `HISTORIAL_UPD_101_150.md` (18-jun-2026 → 22-jun-2026)

**Resumen del bloque:**
- Módulo Compras completo: OC Material + Suministros, KPIs, CRUD, pagos, recepción (UPD-101/102)
- Reporte Dirección ampliado: Top Clientes 3 paneles, Rentabilidad m², rediseño minimal (UPD-103/104/124)
- Sistema Omisiones de Estación completo: BD, API, operador.php, tablero (UPD-105 a 110)
- NUEVO módulo Campañas WhatsApp: Meta Cloud API v20.0, wizard, inbox, media, badge sin leer (UPD-111 a 113, 129 a 136)
- Flujo Rechazo por Calidad: BD, API, UI, badges, banner (UPD-114 a 119)
- Seguridad HTTP completa: login hardening, auth APIs, CORS, CSRF, headers, .env, directory listing, .git (UPD-122/123, 135, 137 a 147)
- Fixes reporte dirección, badge órdenes, token WA permanente, app Meta en Producción (UPD-120/121/125 a 128/129)
- Permisos Compras ampliados a administracion y dueno (UPD-150)

**Contexto al cerrar bloque (UPD-150):** Hardening de seguridad HTTP completado (CORS/CSRF/headers/credenciales en .env). Módulo Campañas WA funcional con inbox, envío media, imágenes en chat y token permanente sin expiración. App Meta en modo Producción. Pendientes al entrar al bloque siguiente: métricas WA visuales, tipos de mensaje adicionales, fixes performance campaña, correo OC, croquis PDF mejoras, módulo Comprobantes OC.

---

### Bloque archivado: UPD-151 a UPD-200
Archivo completo: `HISTORIAL_UPD_151_200.md` (22-jun-2026 → 24-jun-2026)

**Resumen del bloque:**
- Campañas WA maduradas: métricas visuales 4 cards (UPD-154), tipos de mensaje WA (UPD-155), fix servidor bloqueado + PHP-FPM max_children 5→12 (UPD-156)
- Correo OC completo: PHPMailer SMTP, badge morado sidebar, auto-send al abrir (UPD-175)
- WA automático orden_lista: helper compartido wa_helper.php, flag wa_lista_enviado, notas de voz reproducibles (UPD-185/192/193/194)
- telefono_alterno: nuevo campo clientes para WA, envío cotización por WA, fix doble chat RIGHT(telefono,10) (UPD-178/180)
- Croquis PDF completado: bisagra BI (UPD-183), esquinas cortadas (UPD-167), tabla elementos reubicada (UPD-168-174), B&N (UPD-188), selector escala (UPD-189), MB dinámico (UPD-186/187)
- Seguridad: pentesting Kali sin hallazgos (UPD-160), IDOR orden_comentarios fix (UPD-177), ETags (UPD-162), error_log protegido (UPD-161)
- Fix precio cotización bloqueado al guardar: hidden p_pm2_i, catálogo solo al cambiar cristal (UPD-191/197)
- Fix VoBo pago excedente → saldo a favor automático (UPD-190)
- SPA modal cleanup en cargarModulo() para evitar backdrops zombie (UPD-195/196)
- Auditoría cotizaciones: límite 200→1000 registros (UPD-199), fix SPA listeners acumulados (UPD-200)
- Comprobantes OC (UPD-166), Fix correcciones propagación campos (UPD-198), Fix portal móvil (UPD-184)

**Contexto al cerrar bloque (UPD-200):** WA maduro con automatización orden_lista, notas de voz, doble teléfono y métricas. Croquis PDF completo y listo. Cotizaciones auditadas (límite, SPA cleanup). Precio bloqueado funcional. Pendientes al entrar al bloque siguiente: auditoría cotizaciones medios, reporte días hábiles, usuario desarrollo/WIP, facturación CFDI, portal cotizaciones, módulo rutas WIP.

---

### Bloque archivado: UPD-251 a UPD-397
Archivo completo: `docs/HISTORIAL_UPD_251_397.md` (29-jun-2026 → 24-jul-2026)

**Resumen del bloque:**
- Facturación CFDI completa: FacturAPI (timbrado/cancelación real SAT), OCR CSF con tesseract, receptor ligado al CRM, Público en General, múltiples correos, folio único + anti-doble-clic, cancelación async pending/canceled (UPD-251 a 253, 255, 280 a 283, 290 a 293, 320 a 326)
- Rediseño y madurez de Maquila: UI a juego con Cotizaciones, corrección de órdenes ya convertidas, servicio Filo Muerto nuevo (UPD-273 a 279, 371, 372)
- Reporte Dirección: fixes de fecha (fecha_pedido→vobo_at) en Pipeline/Retraso/Ventas/Registro (UPD-267, 285, 301, 304, 363, 364), Rentabilidad por m² real ponderado por ventas/mes actual con IVA consistente (UPD-314/315/365/366/385/389)
- Logística Rutas completa: GPS ProTrack365 en vivo (Open API + fallback web), optimizador con ETA real, QR de hoja de ruta al entregar, soporte a salidas parciales múltiples por pieza, replay de recorrido (UPD-327 a 330, 337 a 340, 344 a 349, 355/356, 393 a 396)
- WA: bloqueo ventana 24h, Flow interactivo, campañas segmentadas/regionales, alta de cliente desde inbox, polling silencioso de chat, fix teléfono internacional (UPD-254, 265, 271, 289, 311 a 313, 343, 350, 397)
- 3 Sprints de auditoría de lógica de negocio (`auditoria_business_logic.md`): Sprint1 dinero (fórmula canónica de totales, autorización descuentos, VoBo/pagos), Sprint2 producción/piso (reimportación, omisiones, VoBo obligatorio para escanear), Sprint3 compras/inventario (recepción atómica, calendario de pagos, IVA por partida) (UPD-359 a 361, 380)
- Compras/Inventario: OC con archivos adjuntos, impresión OC, fix pagos con centavos, FIFO en costo de stock, EVO 50 nuevo tipo (UPD-296 a 298, 353/354, 362, 373, 376 a 380)
- Video marketing con Remotion (herramienta fuera del webroot, sin conectar a campañas todavía) (UPD-351/352)
- Correcciones de datos puntuales documentadas caso por caso: saldo a favor duplicado, pagos OC mal marcados, actividad reciente con fecha futura, efectividad de corte >100%, piezas con estatus incorrecto (UPD-305, 362, 386, 388, 392, 396)

**Contexto al cerrar bloque (UPD-397):** Sistema maduro en producción con Facturación CFDI real, Rutas de Entrega con GPS en vivo y salidas parciales, 3 sprints de hardening de lógica de negocio aplicados. Pendientes al entrar al bloque siguiente: ver sección 12 (Pendientes Activos) — Depósito a Cuenta/Saldo a Favor sin rediseñar, C-6 optimizador de corte sin descuento de inventario real, plantillas WA de avisos de ruta sin aprobar en Meta, videos Remotion sin conectar a campaña real.

---

### Bloque archivado: UPD-398 a UPD-499
Archivo completo: `docs/HISTORIAL_UPD_398_499.md` (27-jul-2026 → 13-ago-2026)

**Resumen del bloque:**
- Auditoría de negocio E2E v3 (`auditoria_e2e_v3.md`): fix de todos los Altos reales encontrados — maquila en $0.00 (lista y Portal), saldo pendiente mal reseteado al editar, cancelación de orden con ruta en curso, retrabajo de pieza atascada tras orden cerrada, race condition en wizard de corte y en convertir cotización→orden, recepción parcial de OC de flete duplicando costo, wizard sin filtrar `requiere_corte`, cancelación de maquila sin candados, partida eliminada sin borrar sus servicios, restaurar orden cancelada sin re-vincular cotización — y varios falsos positivos confirmados y descartados (UPD-398 a 413)
- Fix de fondo en impresión de cotización: Subtotal por partida usa bruto (`precio_m2_usado × m2 × cantidad`) en vez de neto guardado, con precisión completa de m² sin redondeo intermedio (UPD-414/416)
- **Proyecto Contabilidad / Estado de Resultados construido completo, Fases 0 a 6.3:** Catálogo de Cuentas, Mapeo Compras, Nómina, Gastos Fijos, Caja Chica, Estado de Resultados (P&L) con ingreso reconocido al VoBo y costo de ventas por m²×costo promedio de compra por tipo/espesor (no por consumo de wizard, cobertura insuficiente), merma neta de corte y retrabajo (piso y comercial) como líneas propias de Costo de Ventas, y partida doble real (catálogo de Balance, Pólizas manuales, generador automático de pólizas para Compras/Nómina/Gastos Fijos/Caja Chica/Ventas-Cobros, Balance General con selector de período y fecha de apertura 01-ago-2026) (UPD-417, 424 a 434, 437 a 447, 452, 457, 463, 479 a 484, 491 a 494)
- Reporte Dirección: Pipeline vigente acotado a 15 días de vigencia real de cotización, tarjeta Pendientes y Ventas por asesor acotadas al período, fix `ubicacion` LOCAL/FORANEO nunca copiada de cotización a orden (backfill de 465 órdenes), fix KPI Reproceso (leía tabla vacía), Rentabilidad m² con ventana de mes en curso y respetando el selector de período, Efectividad de Corte separando pedacería de láminas completas (UPD-427 a 432, 472/473, 482/483)
- **Apartado de Precio con vigencia** (sub-feature de Saldo a Favor, congela precio ≤45 días con VoBo si >7 días) (UPD-422)
- **Esquema de Referidos** completo (5% descuento al referido, 5% saldo a favor al referente vía WA al VoBo) + corrección de un caso real no acreditado por omisión del asesor + fix de que el aviso WA de bono no se archivaba en el inbox del referente + auditoría de los 6 puntos de envío WA transaccional (solo faltaba `acceso_portal`) (UPD-450, 486 a 488)
- **Comisiones de Asesores + Retrabajo comercial desde Cotización**: tramos por venta del mes, penalización 50% del retrabajo (perdonable si el cliente pagó ≥50%), excluido de Ingresos/ventas del asesor, badge/filtro "Retrabajo — no cobrar" en Cobranza y VoBo, costeado en el P&L y en el módulo Retrabajo (UPD-467 a 470, 484/485, 513/514 — nota: 513/514 documentados en el bloque siguiente por fecha, referenciados aquí por continuidad temática)
- Producción/piso: fix `nextEstatus()` de jefe de piso sin reglas de maquila, pantalla dividida + advertencia de orden de escaneo para Chofer, botón "Liberar" en Rutas para folios atorados, "Ajustar stock" directo en Inventario, bono de corte por pedacería para Angel (UPD-454 a 460)
- Portal Clientes: botón "Ver remisión" de solo lectura (UPD-461)
- **Nuevo módulo Bitácora de Desechos** (trazabilidad de recolección de merma física, sin tocar Contabilidad) y **Nuevo módulo Archivos de Video** (file manager para Remotion/ffmpeg, subida chunked por el límite de Apache) (UPD-464, 490)
- Rediseños visuales (skill `frontend-design`): Tablero de Omisiones reescrito con metodología correcta de "escaneada" + estilo minimalista, header de Nueva Cotización + densidad del formulario (UPD-474 a 477, 495/496)
- Insulado/espaciador pasa a cobrarse por metro lineal real (perímetro × mitad de piezas), Paso 1 corrección de datos + Paso 2 automatización en el módulo (UPD-498/499)
- Correcciones de datos puntuales documentadas caso por caso: pagos OC duplicados, cliente duplicado CTN-473, cancelación parcial de S-540 con reutilización de vidrio, corrección de tipo de vidrio en pieza de S-518 (UPD-421, 449, 451, 478, 489)

**Contexto al cerrar bloque (UPD-499):** Estado de Resultados y Balance General en producción con partida doble completa (Fases 0-6.3), abriendo libros el 01-ago-2026. Referidos y Comisiones/Retrabajo comercial operando de punta a punta. Auditoría E2E v3 cerrada. Pendientes al entrar al bloque siguiente: ver sección 12 — pruebas visuales acumuladas de Contabilidad/Referidos/Comisiones, Sprint 3 de la auditoría 13-ago (emojis/alerts/accesibilidad) apenas arrancando, Promo WA por volumen sin gráfico de Armando.

---

## 14. PROTOCOLO PARA CADA SESIÓN

Al terminar cualquier sesión con cambios:
1. Subir archivos modificados a Drive (`ARCHIVOS SERVIDOR/`)
2. Registrar el cambio con próximo UPD en este archivo
3. Las tareas completadas se marcan HECHO — NUNCA se borran

### Bloque archivado: UPD-500 a UPD-545
Archivo completo: `docs/HISTORIAL_UPD_500_545.md` (13-ago-2026 → 26-ago-2026)

**Resumen del bloque:**
- 4 auditorías externas encadenadas, cerradas por sprints con confirmación uno-a-uno antes de avanzar: auditoría 13-ago (Sprints 1-3: fail-closed en backups, XSS admin_ordenes, cifrado reversible de portal_password con AES-256-GCM, CSRF synchronizer token vía `session_boot.php` en 16 archivos, toasts/emojis/contraste WCAG parcial) (UPD-500 a 505); auditoría v2 pre-release 19-ago (Sprints A-D: XSS, doble IVA en OC, candados de saldo/IDOR/mensajería interna, Sprint P2 visual Fases 1-4 completo — 18/20 hallazgos UI/UX) (UPD-525/528, 534 a 536); auditoría externa 20-ago 61 hallazgos (Sprint P0 dinero/fiscal: CFDI reconstruido en servidor, claims atómicos anti-doble-clic en VoBo/rutas/campañas; Sprint P1: doble escaneo, folio OC, chunks de video, permisos de OC) (UPD-531/532); auditoría 2ª pasada 25-ago Franja 1 (CFDI vigente bloquea cancelación, clamp de descuento, revive de orden cancelada, dedup de destinatarios WA) (UPD-539)
- **Retrabajo comercial madurado de cabo a rabo:** badge/filtro "Retrabajo — no cobrar" en Cobranza y VoBo (UPD-510/511), reclasificado a cuenta propia 5.5 del P&L sin tocar Utilidad Bruta/Neta, Salida ya no exige estatus de pago falso (UPD-513), costo visible en el módulo Retrabajo (UPD-514), excluido de las 5 queries de "Ventas y Cobranza" en Reporte Dirección incluida la vista detallada por día/semana/mes (UPD-512/541) — con 2 tablas nuevas dedicadas (Retrabajo del período, Saldos a Favor registrados) con desglose de pagos y saldo previo/posterior por depósito (UPD-542 a 546, documentado hasta 545 en este bloque)
- Visibilidad cruzada de Cotizaciones/Órdenes entre asesores con tags de color y filtro por asesor (UPD-508/509); Mensajería interna 1-a-1 ligada a Reportes (UPD-522); Direcciones guardadas por cliente en Logística Rutas (UPD-523/524)
- Promo WA por volumen con código personal `CTN-###PROMO` y tramos escalonados de descuento, ajustados tras análisis de margen real (UPD-516/517); reserva de username `@apex.glass` en Meta y canario de detección BSUID sin migrar todavía (UPD-537/538)
- Maquila: servicio de Resaques cobrado igual que Taladro (UPD-533); Portal Ofertas con carpeta fija autodetectada por fecha + animación de aviso (UPD-529/530)
- Correcciones de datos puntuales documentadas caso por caso: cliente duplicado CTN-494, parada de ruta revertida S-519, orden S-677/COT-1347 reasignada de cliente (UPD-515, 520, 540)

**Contexto al cerrar bloque (UPD-545):** Sistema con 4 rondas de auditoría externa aplicadas y confirmadas por sprint, retrabajo comercial completamente aislado de ventas/cobranza/P&L, y Reporte Dirección con vistas dedicadas de Retrabajo y Saldos a Favor. Pendientes al entrar al bloque siguiente: ver sección 12 — BLO-07/BLO-08 de la auditoría 25-ago sin resolver (Sesión de Corte sin reversa de stock, portal por nombre), resto de hallazgos medios/bajos de lógica de negocio, UX-4/12/13/16 de la auditoría UI/UX, plantilla Meta de Referidos, prueba visual acumulada de varios UPDs.

---

### Bloque archivado: UPD-546 a UPD-580
Archivo completo: `docs/HISTORIAL_UPD_546_580.md` (26-ago-2026 → 10-sep-2026)

**Resumen del bloque:**
- Reporte Dirección madurado: saldo previo/posterior por depósito, umbral de $10 para no generar saldo a favor por centavos de excedente, columna "Razón" en Retrabajo del período, pipeline y % de conversión generalizados por asesor (ya no hardcodeados a Bethy/Berenice) con su fix de cohortes contaminadas (UPD-546/547/548/578/579)
- Rediseño de "Resumen" (página de entrada del dashboard): banda de bienvenida + KPIs Comercial/Producción reales, y de paso corrige el badge global de "órdenes vencidas" a conteo server-side (UPD-549)
- Producción/piso: resaques visibles en Orden de Producción impresa, estación Canteado con registro directo a Terminado quando no hace falta nada más, venta anticipada de lámina completa con reservas contra compra futura, fix de columna "Entrega"/reimpresión por entrega en la remisión, fix de cota falsa en croquis con resaque pegado al borde, recibo imprimible del Bono de Corte (UPD-552 a 556, 559, 566)
- Cierre de la Franja 1 de la auditoría del 25-ago (BLV-6, BLO-03/06/11, BLO-14/15, BLO-10) — queda BLO-07/BLO-08 documentados como pendientes a propósito (UPD-557/558)
- Infraestructura: fix de sesión expirando a los 24 min (subida a 8h), diagnóstico y descarte de "VPS congelado" (falso positivo, la causa real fue la sesión), workaround de SSH puerto 2222, y el hallazgo más importante del bloque — **Cloudflare sin `mod_remoteip`** ocultaba la IP real de todos los visitantes ante Apache/fail2ban/login_intentos desde el 02-sep, corregido y verificado (UPD-560 a 563)
- **Checador ZKTeco MB20-VL completado de punta a punta:** Fase 1 hardware/red, módulo RH nuevo (expediente/documentos/vacaciones/incidencias, probado con Playwright), cola de comandos alta/baja VPS↔Pi, primeros empleados reales cargados con PIN, nómina semanal con bonos de Puntualidad/Productividad, "Dar de baja" combinado RH+checador, listener de producción en la Raspberry Pi confirmado contra el reloj físico real, mapeo PIN↔número de dispersión de nómina, módulo nuevo de Asistencia semanal (jueves-miércoles) (UPD-567 a 576)
- Auditoría de seguridad 09-sep: primera operación masiva remota real sobre el reloj (14 bajas), rotación de `CHECADOR_LISTENER_KEY`, limpieza de secretos en documentación trackeada (UPD-580)
- Correcciones de datos puntuales: S-639 (piezas no cargadas devueltas a Pendientes de ruta) (UPD-577)

**Contexto al cerrar bloque (UPD-580):** Checador ZKTeco y módulo RH en producción y probados de punta a punta (falta solo probar el flujo de "alta" de empleado nuevo en el reloj). Hallazgo crítico de Cloudflare/mod_remoteip corregido. Reporte Dirección con pipeline/conversión generalizados por asesor. Pendientes al entrar al bloque siguiente: ver sección 12 — certificado SSL sin mecanismo de renovación confirmado, Fase B de Cloudflare (MX/SPF, modo SSL, restringir origen) esperando panel de Armando, SSH puerto 2222 sin persistir reinicio, journal de systemd no persistente, reporte semanal de nómina jueves-miércoles para el despacho de outsourcing sin construir.

---

### Bloque archivado: UPD-581 a UPD-604
Archivo completo: `docs/HISTORIAL_UPD_581_604.md` (12-sep-2026 → 26-sep-2026)

**Resumen del bloque:**
- Cotizaciones/Órdenes: pestaña Órdenes ordenada por fecha real de creación de la orden (UPD-581); Referidos extendido a 31-oct-2026 con fix del bono que solo pagaba en el mismo mes calendario (UPD-582); hueco confirmado de "agregar partida post-conversión", resuelto a mano en S-893 (UPD-598)
- Reporte Dirección: gráfica "Ventas diarias acumuladas" mes actual vs. 2 anteriores en SVG propio (UPD-584); tabla de concentrado de la Encuesta de Satisfacción con tooltip de nombres (UPD-589/591/592)
- **Encuesta de Satisfacción de punta a punta:** Flow WA de 5 preguntas + código `ENC-XXXXXX` de 5% adicional de un solo uso (UPD-588), prueba real y tabla `encuesta_respuestas` (UPD-589), campaña a 365 clientes (UPD-590), reactivación de 28 códigos vencidos + sorteo 3%/5%/7.5%/10% hasta el 26-sep (UPD-596) y campaña de recordatorio con imagen a 343 clientes (UPD-597)
- Campañas WA: alta manual de prospectos `prospecto-NNN` (UPD-585); campaña Coahuila `promo_saltillo_sep` + nuevo código de **precio fijo por m²** `SALT_SEP2026` (Claro 6mm $585 / 9mm $835 con IVA, vigente al 30-sep, VoBo al 05-oct) vía `promo_precio_lib.php` (UPD-601); marca `sin_whatsapp` (error 131026) en prospectos/clientes en vez de borrarlos (UPD-602)
- Cobranza: "Total facturado" filtrado por fecha de VoBo en vez de fecha_pedido, alineado a Reporte Dirección (UPD-594)
- Facturación: auditoría completa + Sprint 1 — clave SAT que impedía timbrar cualquier factura ligada a orden, tipos E/P/IG bloqueados, XSS del listado, proxy PDF/XML con mensaje real (UPD-604)
- Correcciones de datos puntuales documentadas caso por caso: cotización COT-1790 de insulado (UPD-583), fusión cliente duplicado CTN-197→CTN-224 (UPD-586), salidas capturadas como chofer en vez de recolección S-810/S-554/S-598/S-685/S-898 (UPD-587/593/603), pago mal capturado S-876 y S-882 (UPD-595/599), código de encuesta movido de COT-1883 a COT-1925 (UPD-600)

**Contexto al cerrar bloque (UPD-604):** Encuesta de Satisfacción y su descuento operando en producción real; promo de precio fijo por m² disponible como mecanismo reutilizable; Facturación con Sprint 1 cerrado y en camino a live. Pendientes al entrar al bloque siguiente: ver sección 12 — checklist único de Facturación para live (manifiesto, llave live, decisiones del contador PPD/notas de crédito/claves SAT, respaldo de XML), plantilla Meta de código reactivado, Fase B de Cloudflare, SSL antes del 02-dic-2026.

---

### Bloque archivado: UPD-605 a UPD-631
Archivo completo: `docs/HISTORIAL_UPD_605_631.md` (26-sep-2026 → 30-sep-2026)

**Resumen del bloque:**
- Facturación CFDI llevada a punto de live: Sprints 1-3 (resguardo propio de XML/PDF, total del PAC como verdad, permiso `facturar`, Público en General con InformacionGlobal, CFDI relacionados, candados de estado de orden, XSS) (UPD-605 a 608, 611, 617, 618)
- Complementos de Pago (serie P) manuales y automáticos al registrar pagos, candados Facturación↔Cobranza, alineación con la doc oficial de FacturAPI (202/pending, external_id, idempotency_key) (UPD-615, 616, 619, 622)
- Anticipos esquema A del SAT, Fases 1-4: forma de pago en saldo a favor, pestaña "Anticipos por facturar", relación 07 + nota de crédito serie N automática, referido como descuento, reintegros con origen, revisión de anticipos de CONTPAQi previos al 01-oct (UPD-623 a 629)
- Cotizador de Insulados como unidad (2 partidas ext/int + separador generado en servidor) y separador por m² $750 con IVA; COT-1821 convertida (UPD-613, 614)
- Bono de Corte: tope 1.5 m² desde 28-sep (UPD-610); códigos de precio fijo `MTY_SEP2026` y campaña de reenvío Coahuila (UPD-620, 621, 630)
- Manual de uso de Facturación en PDF (UPD-631); correcciones de datos puntuales S-886, S-945 (UPD-609, 612)

**Índice:**
- UPD-605 (26-sep-2026, Mando) — Facturación Sprint 2 (confiabilidad) — HECHO y probado de punta a punta en sandbox
- UPD-606 (26-sep-2026, Mando) — Facturación — 4 bugs encontrados por Armando probando el módulo en navegador, todos corregidos
- UPD-607 (26-sep-2026, Mando) — Facturación Sprint 3 — 3 de 5 puntos hechos (los que no dependen del contador)
- UPD-608 (26-sep-2026, Mando) — Re-auditoría del módulo Facturación tras los sprints 1-3 — 6 huecos nuevos encontrados y corregidos
- UPD-609 (26-sep-2026, Armando) — Corrección de datos (no código): S-886 (orden id=1149, ANTONIO EFREN REYES GONZALEZ) regresada a planta por un proceso faltante posterior al templado — se volvió a…
- UPD-610 (26-sep-2026, Armando) — Bono de Corte por Pedacería: el tope de sobrante por sesión baja de 2.5 m² a 1.5 m² a partir del lunes 28-sep-2026
- UPD-611 (26-sep-2026, Armando) — Facturación — fix del error SAT CFDI40145 ("El campo Nombre del receptor debe pertenecer al nombre asociado al RFC") por espacios dobles en el nombre
- UPD-612 (28-sep-2026, Armando) — Consulta + corrección de datos (no código)
- UPD-613 (28-sep-2026, Armando) — Nuevo: Cotizador de Insulados + conversión de COT-1821
- UPD-614 (28-sep-2026, Armando) — Separador de insulado por m² dado de alta + aplicado a COT-1821 (datos, no código)
- UPD-615 (29-sep-2026, Armando) — Nuevo: Complementos de Pago (CFDI tipo P) en Facturación
- UPD-616 (29-sep-2026, Armando) — Auditoría de Complementos de Pago (UPD-615) contra SAT/buenas prácticas + 4 correcciones
- UPD-617 (29-sep-2026, Armando) — Auditoría de seguridad del módulo Facturación + correcciones
- UPD-618 (29-sep-2026, Armando) — Escaneo Aikido (SAST/secretos/IaC) del proyecto Facturación + Complementos de Pago — limpio
- UPD-619 (29-sep-2026, Armando) — Candados de Facturación ↔ Cobranza + Complementos de Pago automáticos + alta de clientes desde la factura
- UPD-620 (29-sep-2026, Armando) — Campaña WA de reenvío a Coahuila creada en borrador (datos, no código): `campanas.id=60` "Promo Saltillo Sep - Coahuila (reenvío 29-sep)"
- UPD-621 (29-sep-2026, Armando) — Nuevo código de precio fijo `MTY_SEP2026` (campaña Monterrey): mismas reglas que `SALT_SEP2026` (UPD-601)
- UPD-622 (30-sep-2026, Armando) — Facturación alineada con la documentación oficial de FacturAPI + 2 bugs corregidos antes de live
- UPD-623 (30-sep-2026, Armando) — Anticipos (esquema A del SAT) — Fase 1: el saldo a favor guarda la forma de pago real
- UPD-624 (30-sep-2026, Armando) — Anticipos (esquema A) — Fase 2: facturar el anticipo al recibir el dinero
- UPD-625 (30-sep-2026, Armando) — Anticipos (esquema A) — Fase 3: factura de la orden con relación 07 y nota de crédito automática
- UPD-626 (30-sep-2026, Armando) — Anticipos (esquema A) — Fase 4: bono de referido, saldo anterior, PPD y reintegros. Con esto quedan las 4 fases
- UPD-627 (30-sep-2026, Armando) — Forma de pago de la factura con anticipo, alineada al texto oficial del SAT (corrige la regla de UPD-625)
- UPD-628 (30-sep-2026, Armando) — Decisión de negocio (no código): todo el saldo a favor, incluido el Apartado de Precio (UPD-422), es ANTICIPO para el SAT
- UPD-629 (30-sep-2026, Armando) — Anticipos anteriores al 01-oct: revisión de lo que se facturó en CONTPAQi + uso en la facturación
- UPD-630 (30-sep-2026, Armando) — Cierre de sesión 29/30-sep — estados que no quedaron en los UPD de origen (solo documentación)
- UPD-631 (30-sep-2026, Armando) — Manual de uso de Facturación (documento, sin cambios de código ni BD)

---

### Bloque actual: UPD-632 en adelante

**Formato obligatorio (desde UPD-632):** aquí va UNA línea por UPD (código, fecha, responsable y una frase de máx. ~200 caracteres). El detalle completo va en `docs/HISTORIAL_UPD_632_actual.md` con el mismo código. Nunca escribir párrafos largos en este archivo.

- UPD-632 (01-oct-2026, Armando) — Reorganización de CLAUDE.md por exceder el límite de 150k caracteres: historial 605-631 archivado, pendientes cerrados y detalle largo movidos a `docs/`. Sin cambios de código ni BD.

**Próximo UPD disponible: UPD-633**
