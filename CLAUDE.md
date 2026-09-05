# APEX GLASS — MEMORIA ÚNICA DEL PROYECTO
# Sistema de Rastreo de Producción (Templadora Noreste, S.A. de C.V.)
# Última actualización: 05 septiembre 2026 | Próximo UPD disponible: UPD-567

**REGLA DE ORO:** Este archivo es la ÚNICA memoria del proyecto — no memorias internas de Claude, no documentos sueltos. Todo conocimiento de features, historial de cambios y decisiones técnicas vive aquí. Claude lo lee al inicio de cada sesión y **debe actualizarlo automáticamente al terminar cualquier sesión con cambios, sin que se le pida** (nuevo UPD + refrescar "Próximo UPD disponible" en la cabecera y en la sección 13). Armando y Mando trabajan en el mismo archivo. NUNCA borrar entradas anteriores — solo agregar.

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

**Seguridad:**
- NUNCA escribir credenciales en el chat — usar .env o cPanel directamente
- SIEMPRE hacer SELECT de verificación antes de cualquier UPDATE/ALTER en producción

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

| Prioridad | Resp. | Tarea | Estado |
|---|---|---|---|
| URGENTE | Ambos | Operador horno: 2 acciones separadas (en_horno + terminado/reproceso) | HECHO UPD-076 |
| ALTA | Armando | Cancelar HostGator (~1 semana margen desde 14-jun) | HECHO 18-jun |
| ALTA | Armando | Seguridad HTTP (CORS, CSRF, headers, session regenerate) | HECHO UPD-138 a 147 |
| ALTA | Armando | Agregar UPDs 059+ al Google Doc (cambios 12-14 jun) | Pendiente |
| MEDIA | Armando | Mover DB_PASS a .env fuera del webroot | HECHO UPD-139 |
| MEDIA | Armando | Cargar tzdata en MariaDB VPS | Pendiente |
| MEDIA | Armando | Instalar n8n via Docker (n8n.apex.glass, puerto 5678) | Pendiente |
| MEDIA | Armando | PDF croquis: app/imprimir_croquis.php | HECHO UPD-172/187/188/189 |
| MEDIA | Armando | Feature Órdenes Express | Pendiente |
| MEDIA | Armando | Google Sheets / Apps Script — verificar columna M | Pendiente |
| MEDIA | Mando | Completar módulo Retrabajo: modal + razones por estación | Pendiente |
| MEDIA | Mando | Rutas: optimización de zonas | Pendiente |
| MEDIA | Mando | Facturación WIP: bug opción "Eliminar" timbradas modo test | HECHO UPD-251 |
| MEDIA | Mando | Facturación WIP: claves SAT de vidrio sin verificar con contador — confirmar antes de producción | Pendiente |
| MEDIA | Mando | Facturación WIP: curl_close() deprecado PHP 8.4 en api/facturapi.php líneas ~238 — reemplazar por unset($ch) | Pendiente |
| BAJA | Mando | Facturación WIP: conectar con cliente real del CRM (actualmente receptor libre) | Pendiente |
| BAJA | Mando | Facturación WIP: cuando llegue CSD cambiar FACTURAPI_MODE=live en .env y agregar FACTURAPI_KEY_LIVE | Pendiente |
| MEDIA | Ambos | Alerta reorden automática láminas (esperar 2-3 semanas historial) | Pendiente |
| BAJA | Armando | Error consola JS guardarCristal | Pendiente |
| BAJA | Ambos | m2_requeridos en laminas.php | Pendiente |
| MANUAL | Armando | ~~Actualizar CTN-259: "PRUEBA PORTAL" → "JESUS MANUEL SALDANA DE LA ROSA"~~ — NOTA 04-jul-2026: José Manuel Saldaña de la Rosa ya existe como cliente real en CTN-398; CTN-259 "PRUEBA DE PORTAL" es efectivamente el cliente placeholder de pruebas del portal, no hay que renombrarlo | OBSOLETO — confirmado por Armando |
| MANUAL | Armando | Capturar precios: Claro 12mm, Claro Zafiro 9mm, Filtrasol 9mm, Tintex 6mm, Tintex 9mm | Pendiente |
| ALTA | Ambos | SEGURIDAD: Fail2ban en puerto 8443 (AdminBolt) — protección brute force, panel expuesto al internet | HECHO UPD-243 |
| ALTA | Ambos | SEGURIDAD: FTP puerto 21 abierto — vsftpd corre pero firewall ya bloquea puerto 21 externamente; AdminBolt depende de vsftpd para monitoreo, no se detiene | HECHO UPD-243 |
| ALTA | Ambos | SEGURIDAD: Rate limiting en login.php — verificar/implementar bloqueo por intentos fallidos | HECHO (ya existía: 10 intentos, 15 min bloqueo) |
| MEDIA | Armando | SEGURIDAD: SSH hardening — authorized_keys de root está VACÍO; se redujo MaxAuthTries 6→3 y LoginGraceTime 120s→30s; deshabilitar PasswordAuth requiere configurar llaves primero | PARCIAL UPD-243 |
| MEDIA | Armando | SEGURIDAD: Revisar permisos de archivos en servidor (buscar 777) | HECHO UPD-243 — ninguno encontrado |
| MEDIA | Armando | UX: Dark mode en dashboard (topbar ya es oscuro, extender al sidebar y contenido) | Pendiente |
| BAJA | Armando | UX: Badge órdenes vencidas global — actualmente solo se actualiza desde módulo Resumen | HECHO UPD-549 — ahora usa conteo real server-side de todas las órdenes activas, no solo la página cargada |
| BAJA | Armando | UX: Paginación resumen con total de registros "Mostrando X–Y de Z órdenes" | Pendiente |
| MEDIA | Mando | AUDIT Fix 8: CSS compartido (app/shared.css) — extraer .page-title, .page-sub, .btn-*, .modal-*, badges; skip por ahora porque valores inconsistentes entre módulos activos; hacer gradualmente al tocar cada módulo | Pendiente |
| MEDIA | Mando | AUDIT Fix 10: Mover CORS/Content-Type boilerplate a api/config.php — skip por ahora porque rompe endpoints que sirven PDFs/archivos (facturapi.php, archivos_ordenes.php); requiere refactorizar esos primero | Pendiente |
| MEDIA | Mando | AUDIT Fix 11: Split módulos grandes — cotizacion.php (1854 líneas), inventario.php (1715), croquis.php (1527); skip por ahora por actividad activa; hacer cuando haya pausa natural en desarrollo | Pendiente |
| MEDIA | Mando | AUDIT Fix 12: Mover HISTORIAL_UPD_*.md a docs/ y limpiar error_log en api/, app/, app/modulos/ | HECHO |
| MEDIA | Ambos | Performance: índices BD — hacer cuando producción esté inactiva (fin de semana/noche): `CREATE INDEX idx_piezas_estatus_orden ON piezas(estatus, orden_id)`, `idx_historial_pieza_estatus_fecha ON historial_estatus(pieza_id, estatus_nuevo, created_at)`, `idx_historial_creado ON historial_estatus(created_at, estatus_nuevo)`, `idx_ordenes_estado_cierre ON ordenes(estado, fecha_cierre)` | Pendiente |
| MEDIA | Armando | SEGURIDAD: `app/modulos/cotizacion.php` (`ModCotizacion._buscarCliente`) tiene el mismo patrón de XSS corregido en UPD-275 para maquila — `escJs()` solo escapa `\`/`'` pero el nombre del cliente se concatena dentro de un atributo `onclick="..."` con comillas dobles; un cliente con `"` en razón social rompe el atributo. Aplicar el mismo fix (DOM/addEventListener en vez de concatenación) | Pendiente |
| MEDIA | Armando | Campañas WA segmentadas mensuales (4 segmentos: frecuentes/compradores del mes/cotizó sin comprar/sin cotizar en el mes) — correr `scripts/generar_campanas_segmentadas.php` día 25-28 con los 4 templates Meta del mes, revisar y dar OK de envío por campaña en el módulo Campañas | RECURRENTE — primera corrida UPD-265 (jun-2026, campañas #18-21), trigger mensual automático día 26 |
| ALTA | Ambos | Facturación — pedir CSF (Constancia de Situación Fiscal) y correo electrónico a los clientes que se vayan a facturar, para completar sus datos fiscales en el CRM antes de pasar a modo live | Pendiente (07-jul-2026) |
| ALTA | Armando | Facturación — conseguir el CSD (Certificado de Sello Digital) y los datos que pida FacturAPI para poder pasar de modo test a modo live (`FACTURAPI_MODE=live` + `FACTURAPI_KEY_LIVE` en .env) | Pendiente (07-jul-2026) |
| MEDIA | Mando | Facturación — revisar a fondo el flujo de cancelación de CFDI ante el SAT (`accion=cancelar` en api/facturapi.php, UPD-280) antes de usarlo en real: motivos, plazos de 72h/$1000, aceptación del receptor en su buzón SAT | Pendiente (07-jul-2026) |
| MEDIA | Mando | Facturación — quitar lo que quede de "modo prueba" en la UI una vez que se pase a modo live: badge "WIP" en el título del módulo, banner amarillo "Modo prueba: Facturas a nombre de PRUEBA DE PORTAL... Datos guardados solo en este navegador" (texto además desactualizado, ya no aplica solo al navegador), y cualquier mención de sandbox | Pendiente (07-jul-2026) |
| ALTA | Armando | **Apartado de Precio con vigencia (sub-feature del rediseño de Saldo a Favor) — HECHO UPD-422.** Ver detalle abajo. | HECHO UPD-422 |
| ALTA | Armando | **Depósito a Cuenta / Saldo a Favor — rediseño en curso (10-jul-2026), NADA IMPLEMENTADO TODAVÍA.** Problema raíz: hoy se crea una orden con m² inventados solo para poder registrar un abono/depósito del cliente cuando aún no sabe qué va a comprar — esto duplica el ingreso reportado (una vez en la orden placeholder, otra vez cuando el cliente devenga el saldo en una orden real). Hallazgo clave de la investigación: **el mecanismo correcto YA EXISTE** (`clientes_saldo_favor` + `api/saldo_favor.php?accion=deposito` + tab "Saldo a Favor" en `finanzas_cobranza.php`) pero no se está usando. Recomendaciones dadas (sin confirmar/implementar): badge junto al folio en vez de nomenclatura de folio nueva; botón "Registrar Depósito" en la ficha del cliente; columna informativa "Pagado con Saldo a Favor" en Ventas y Cobranza (no afecta Acumulado en Pedidos); el depósito debería aparecer como fila de "cobranza" separada el día que se registra, sin sumar a "ventas" hasta que se devengue en una orden real (pendiente que Armando lo confirme). También se encontraron 2 bugs en el mecanismo existente sin arreglar (falta guard anti-doble-clic en `saldo_favor.php`, XSS en `sfSelCliente` mismo patrón que UPD-275) y un blast radius completo de 7+ queries en `api/reporte_direccion.php`/`api/inventario.php`/`portal/tablero.php` que habría que filtrar si se limpian órdenes placeholder históricas (falta que Armando pase los folios, no se detectan por texto). Detalle completo de la investigación, decisiones abiertas y citas textuales de Armando en la memoria de Claude (`project_deposito_cuenta_saldo_favor.md`) | Pendiente — diseño en discusión |
| ALTA | Armando | QR de salida por chofer (UPD-319) — verificado 13-jul-2026: las 4 plantillas Meta (`chofer_en_ruta_cliente`/`siguiente_entrega_cliente`/`chofer_en_ruta_asesor`/`siguiente_entrega_asesor`) ya están **APPROVED** (confirmado consultando la Graph API directo); `usuarios.telefono` de Bethy (8134000145) y Cynthia (8140051992) ya está cargado; nombres de choferes (`Juan Roberto García`, `Víctor Bautista`) ya son reales, no genéricos. Flujo completo funcional. Solo falta: prueba visual con un chofer real escaneando el QR físico | HECHO (config) — falta prueba física |
| ALTA | Mando | **GPS ProTrack365 en Logística Rutas (ver UPD-327/328, 338/339)** — HECHO: frontend conectado (línea única al siguiente destino, GPS en vivo), cron `scripts/gps_tracker.php` corriendo cada minuto guardando histórico en `gps_posiciones` y detectando llegada/movimiento. Sigue pendiente pedir al distribuidor la Open API oficial para no depender a largo plazo del fallback web no documentado (`permission denied` en la oficial) | Mayormente HECHO — falta Open API oficial del distribuidor |
| MEDIA | Mando | Radio de "llegada GPS" (250m, ver UPD-338/339) — con la primera prueba real el camión quedó a 268m sin disparar. Evaluar subir a 300-350m con más pruebas | Pendiente |
| MEDIA | Mando | Trazabilidad de rutas (UPD-339/340) — falta prueba con un chofer real completando el flujo físico completo (escaneo QR salida → manejar → llegar) para confirmar que las 4 columnas de la tabla en Productividad se llenan solas | Pendiente |
| MEDIA | Ambos | Rutas de Entrega — activar avisos WA (UPD-355): redactar y aprobar en Meta las plantillas `ruta_iniciada_eta_cliente` (ETA a la 1ra parada) y `ruta_en_curso_cliente` (aviso genérico "en camino, llega hoy" al resto); confirmar si se sigue reusando `siguiente_entrega_cliente` para el aviso "eres el siguiente" tras cada entrega confirmada; una vez listas, cambiar `RUTA_WA_AVISOS_ACTIVO` a `true` en `api/rutas_lib.php` | Pendiente |
| MEDIA | Ambos | Rutas de Entrega — probar con un chofer real el nuevo significado del QR de hoja de ruta (ahora escanea al ENTREGAR, no al salir) y confirmar que el mapa avanza solo a la siguiente parada | Pendiente |
| — | 31-jul-2026 | Armando | ~~Revisar 3 posibles pagos de OC duplicados~~ — CONFIRMADO por Armando y CORREGIDO: borrados `oc_pagos.id` 12 (OC APEX-0190, $38,161.63 vs el correcto id=13 $38,161.68 que sí cuadra con el total), 17 (APEX-0193, reingreso del 16-jul de un pago ya registrado el 14-jul, id=14) y 18 (APEX-0194, mismo patrón, id=18 dup de id=15). Los 3 duplicados son de antes del candado anti-sobrepago M-2 (agregado hasta UPD-380, 22-jul) — no puede repetirse hacia adelante. Total pagado global bajó de $690,840.61 (40 pagos) a $578,856.20 (37 pagos), verificado con SELECT antes/después dentro de una transacción | HECHO |
| MEDIA | Mando | **Contabilidad (WIP) — plan por fases en curso** (proyecto Estado de Resultados/P&L, ver UPD-417/422/424/425). Navegación unificada: un solo botón "Contabilidad" en sidebar con pestañas internas (Catálogo/Mapeo/Nómina/...). Fases 0-5 HECHAS (Catálogo, Mapeo Compras, Nómina, Gastos Fijos, Caja Chica, Estado de Resultados) — el plan de fases queda completo. Pendiente: probar Nómina/Gastos Fijos/Caja Chica con datos reales capturados en el navegador, y comparar el P&L resultante contra el Excel de Armando de 1-2 meses cerrados antes de confiar en el reporte hacia adelante. Fase 6 (partida doble real) documentada pero fuera de alcance salvo que un contador externo la pida — plan conceptual armado 01-ago-2026 (ver UPD-442): requiere (1) ampliar `cuentas_contables` con cuentas de Balance (Activo: Bancos/CxC/Inventario; Pasivo: CxP/IVA por Pagar/Nómina por Pagar; Capital), (2) tabla de pólizas con encabezado + líneas Debe/Haber que sumen igual, (3) generador de póliza automático por cada tipo de evento de negocio (venta, costo de venta, pago a proveedor, nómina, etc. — ~10 tipos). Con eso se obtiene también Balance General, que hoy no existe. Confirmado con Armando 01-ago-2026: se queda solo como plan, no se construye salvo que lo empuje un contador/banco/inversionista real. Hallazgo clave de Fase 1: el costo de ventas por consumo real solo es confiable desde jul-2026 (cuando arrancó el wizard de corte que lo traza) — meses anteriores saldrían con margen falsamente alto. | Fase 0-5 HECHAS — falta probar captura real (Fases 2-4) y validar el P&L contra el Excel de Armando |
| MEDIA | Ambos | **Contabilidad — pruebas pendientes en navegador (01-ago-2026).** Con las Fases 0-5 construidas (y la lógica de ingresos/costo de ventas reescrita el mismo día por Armando — ver `api/helpers/pnl_datos.php`: ingreso reconocido al VoBo con `cotizaciones.subtotal`, costo de ventas por m² vendidos × precio promedio de compra por tipo/espesor normalizado desde `piezas.cristal`, gastos de Compras tipo suministro mapeados por categoría vía `gastosComprasPorCuenta()`), falta probar TODOS los módulos con datos reales capturados desde el navegador: Catálogo de Cuentas, Mapeo Compras, Nómina, Gastos Fijos, Caja Chica y el Estado de Resultados final. Nota de aislamiento confirmada con el usuario: las 8 tablas nuevas de Contabilidad (`cuentas_contables`, `cuenta_mapeo_reglas`, `nomina_empleados`, `nomina_pagos`, `gastos_fijos_conceptos`, `gastos_fijos_pagos`, `caja_chica_movimientos`, `movimientos_contables`) son de solo lectura hacia el resto del sistema — borrar cualquier fila de prueba en ellas mientras se prueba NO afecta ningún otro módulo (ninguna tabla existente del sistema las referencia). Al terminar de probar, comparar el P&L resultante contra el Excel de Armando de 1-2 meses ya cerrados antes de confiar en el reporte hacia adelante. | Pendiente — probar todos los módulos y validar contra el Excel |
| ALTA | Armando | **AVISO PARA ARMANDO — revisar `/home/mando/files_apexglass/auditoria_contabilidad_partida_doble_2026-08-03.md`.** Auditoría del módulo Contabilidad (Fases 6.0-6.3, partida doble) contra prácticas contables estándar. Hallazgo crítico: `movimientos_contables` (de donde lee el P&L) y `polizas_lineas` (de donde lee el Balance) son dos libros separados sin reconciliar — coinciden hoy porque se generan juntos en el mismo código, pero pueden desincronizarse sin aviso. Además: Nómina/Gastos Fijos sobrescriben en vez de versionar sus correcciones, sin doble control (maker-checker) en pólizas manuales, sin motivo/responsable al anular una póliza, sin cierre de periodo. Detalle completo y priorización en el archivo. | Pendiente — Armando debe revisar el archivo y decidir qué se corrige |
| MEDIA | Mando | **AVISO PARA MANDO — revisar en tu próxima sesión (30-jul-2026):** el hook de auto-commit subió `scripts/gps_cache/` (incl. `apex_gps_token.json` con el `web_token` de sesión de ProTrack365) al repo de GitHub en el commit `b3dc5bb`. Verificado: ese token puntual ya había expirado (`web_exp` 19:09 UTC del mismo día) al momento de encontrarlo, no era explotable. La cuenta/contraseña real (`PROTRACK_ACCOUNT`/`PROTRACK_PASSWORD`) vive en `.env` fuera del repo y NO se filtró. Ya se agregó `scripts/gps_cache/` a `.gitignore` y se sacó del tracking (`git rm --cached`) para que no se repita — Armando decidió NO purgar el historial de git por ahora (repo privado, dato ya no explotable). Mando: confirma que el cache de GPS (`gps_tracker.php`, `gps_lib.php`) sigue funcionando bien sin estar trackeado en git (debería ser transparente, es solo un cache en disco) | Pendiente — solo revisión/confirmación |
| MEDIA | Armando | Videos de marketing con Remotion (UPD-351/352) — herramienta instalada y funcional en `herramientas/video-marketing/` (fuera del webroot). 3 videos de muestra hechos: promo genérico de marca, demo del Portal de Clientes (escritorio) y demo del Portal de Clientes (vertical, formato celular con marco de teléfono). Ninguno conectado todavía a una campaña real. Falta: (1) que Armando confirme si le gustan y para qué campaña específica los quiere usar, (2) revisar si `app/modulos/campanas.php` necesita soporte para plantillas Meta con header tipo VIDEO (hoy el wizard solo maneja `header_image_url` de imagen) | Pendiente — esperando feedback de Armando |
| BAJA | Ambos | **Sprint1 (dinero) — migración SQL de A-2 descartada por decisión explícita** (ver UPD-360): 3 UPDATEs que hubieran corregido `cotizaciones.subtotal/iva/total/saldo_pendiente` HISTÓRICOS con la fórmula canónica (8 cotizaciones activas con servicios, +$1,760.12). Armando confirmó 21-jul-2026 que NO quiere editar datos históricos — solo quería que dejara de fallar hacia adelante. Verificado: los 10 parches de código de UPD-359 (incl. helper `api/helpers/totales.php`) ya están en producción, así que toda cotización/pago/corrección nueva desde entonces ya usa la fórmula canónica. Si más adelante se decide corregir el histórico, el SQL de los 3 UPDATEs sigue documentado en `/home/mando/files_apexglass/parches_sprint1.md` sección A-2 | Descartado — código hacia adelante ya OK, no se tocan datos viejos |
| MEDIA | Armando | Sprint1 (dinero) — revisar manualmente **COT-0105** (rechazada históricamente): `saldo_pagado=$1,297.05` nunca se reseteó a 0 aunque el saldo a favor ya se depositó correctamente (mismo monto) — hoy se cuenta doble en reportes hasta que se corrija a mano | Pendiente |
| MEDIA | Mando | **Sprint2 (Producción/Piso) — falta C-6** (ver UPD-361): "Registrar consumo" en el Optimizador de Corte (`api/optimizador_corte.php`) sigue sin descontar inventario real — solo escribe en la tabla muerta `corte_laminas`, el stock nunca baja y las piezas no pasan a `en_corte` (riesgo de doble corte en la siguiente corrida). Mando decidió manejar el descuento de inventario de otra forma; se retoma al cerrar el sprint | Pendiente — Mando lo maneja diferente |
| BAJA | Ambos | Sprint2 — 2 cambios de comportamiento visibles para piso ya en producción (UPD-361): (1) el botón "Confirmar omisión" en `operador.php` ya no funciona para operadores/choferes, solo jefe_piso+ (C-4); (2) escanear QR/CNC ya no funciona en órdenes sin VoBo — `pendiente_vobo`/`cancelada`/`rechazada`/`entregada` (A-4). Avisar a piso si aún no se ha comunicado | Pendiente — confirmar que piso ya lo sabe |
| ALTA | Armando | **Esquema de Referidos (UPD-450) — falta dar de alta la plantilla WhatsApp `referido_saldo_abonado` en Meta Business Manager** (categoría UTILITY, 3 variables: nombre del referente, monto abonado, nombre del referido) — sin ella, el abono de saldo al referente se sigue registrando bien en BD pero el aviso por WA no se envía (el código lo intenta y falla en silencio, a propósito, para no bloquear el VoBo). También pendiente prueba visual en navegador: campo "Código de Referido" en Cotizaciones, línea de descuento en la impresión, y el apartado nuevo `portal/referidos.php` | Pendiente — falta plantilla Meta + prueba visual |
| MEDIA | Armando | **Costo de compra por período (Reporte Dirección → Rentabilidad, `api/inventario.php` accion=costo_promedio, UPD-473) no es un costeo promedio real.** Armando detectó la limitación al preguntar sobre el impacto de comprar 10 láminas caras de Claro 9mm: `costo_prom_m2_mes_actual`/`FECHA_INICIO_PRECIO_REAL` solo suman las compras (`inventario_compras.fecha_compra`) DENTRO del período seleccionado — no incluyen el valor del inventario que ya estaba en almacén al inicio del período. Es "cuánto pagaste por lo comprado en el rango", no "cuánto vale hoy el inventario disponible" (que requeriría saldo inicial a la fecha de corte + compras del período, ponderado por m²). Hoy no afecta a Claro 9mm porque su stock físico ya está en 0 desde antes de agosto, pero en cualquier tipo/espesor que sí tenga stock arrastrado de un mes anterior, ese inventario viejo se ignora por completo en este número aunque siga físicamente en la bodega. Armando decidió dejarlo como pendiente, sin implementar por ahora. | Pendiente — diseño a definir |
| MEDIA | Armando | **Insulado/espaciador — Paso 2: automatizar el cálculo por metro lineal (ver UPD-498/499).** Paso 1 (UPD-498) y Paso 2 (UPD-499) HECHOS: al agregar un servicio `ml` a una partida el módulo autocalcula el perímetro y propone la mitad de piezas; el alta por UI ya acepta decimales; el catálogo de servicios permite marcar "por pieza / por m.l." al crear/editar. Falta solo: (1) que Armando registre en el catálogo los demás espaciadores por tamaño con su precio (autoservicio desde el modal "Catálogo de Servicios"), y (2) prueba visual en navegador (agregar el espaciador a una partida y confirmar que el perímetro se llena solo). | HECHO UPD-499 — falta registrar precios por tamaño + prueba visual |
| BAJA | Armando | S2-08 (auditoría, UPD-503): rollover de folio `Z-999`→`[-001` rompe QR — hoy en letra S, sin urgencia. Requiere `ALTER TABLE folios_control` (VARCHAR(2)) + tocar QR/escáner. Diseño propuesto: doble letra estilo Excel (AA, AB...) | Pendiente — sin urgencia |
| MEDIA | Ambos | S2-14 fase 2 (auditoría, UPD-504): quitar el fallback de autorización de portal por nombre (`cliente_id OR cliente_nombre`) — hoy solo se loguea cada ocurrencia (`[S2-14]` en error_log). Revisar el log en unas semanas; si está limpio, quitar el fallback; si no, corregir esas órdenes primero | Pendiente — esperando datos del log |
| ALTA | Armando | Sprint 2 S2-11 (CSRF, UPD-504): falta prueba visual en navegador — guardar/editar en 2-3 módulos clave (Cotización, Finanzas, Inventario) para confirmar que el token no rompió nada | Confirmado en producción 20-ago-2026: Armando reportó "Token CSRF inválido o ausente" tras dejar la pestaña abierta un rato (token vencido en la sesión del servidor); un F5 lo resolvió al instante — el candado se comportó como está diseñado (bloquea en vez de dejar pasar, y se repara solo con recarga). No fue la prueba sistemática de 2-3 módulos originalmente planeada, pero sí una confirmación real de que el mecanismo funciona sin romper el guardado normal |
| BAJA | Armando | Sprint 2 S2-12: falta correr `git rm --cached error_log api/error_log app/error_log app/modulos/error_log` + commit (Claude no corre git) | Pendiente — acción manual de Armando |
| MEDIA | Mando | **Sprint 3 (auditoría, UPD-505) — falta lo más grande.** Hecho: S3-01 infra, S3-03, S3-04, S3-07, S3-08, S3-02 parcial (2 de ~8 archivos). Falta: sweep de emojis en `operador.php` (~104), `produccion_estaciones.php` (SmartTV), `admin_comunicados.php`, `logistica_rutas.php`, `facturacion.php`, `clientes.php`, `chofer_ruta.php`; migrar los 338 `alert()`/`confirm()` a `toast()`/`confirmar()` (S3-01 resto); S3-05 (labels for/id + aria-label); S3-06 (validación visual completa); S3-09 (paginación "Mostrando X–Y de Z"); S3-10 (responsive en 7 módulos densos) | Pendiente — retomar en sesión dedicada |
| ALTA | Armando | **Promo Estados WhatsApp por volumen (UPD-516/517) — falta el gráfico + prueba visual.** Código construido y probado con dry-run en BD (código personal CTN-###PROMO). Tramos vigentes (ajustados en UPD-517): 1-4→5%, 5-50→7.5%, 51-99→12.5%, 100+→20%. Riesgo conocido sin confirmar por Armando: el tramo 100+ al 20% da solo ~$341/m² de utilidad en Claro 9mm si se usa el costo de compra más caro del mes (por debajo del piso de $400-550/m² discutido) — sí cumple ($416/m²) con costo promedio. Falta: (1) que Armando diseñe y publique el gráfico/texto del Estado de WhatsApp explicando la tabla de tramos y pidiendo al cliente decir su código al asesor; (2) prueba visual en navegador creando una cotización real con el código (no hay Chrome DevTools/Playwright MCP en la sesión que lo construyó); (3) si se quiere medir el ROI de la campaña más adelante, ya se puede reportar por `cotizaciones.promocion_id` — no hay dashboard dedicado todavía, se arma bajo pedido | Pendiente — falta gráfico de Armando + prueba visual |
| ALTA | Ambos | **Auditoría v2 pre-release 19-ago-2026 (`/home/mando/files_apexglass/auditoria_19_08_26/`) — Sprints A/B/C hechos y probados, Sprint D apenas arrancado (UPD-525 a 528).** Falta: A-04 IDOR portal por nombre (necesita backfill de `cliente_id` en 216 órdenes antes de quitar el fallback — decisión de Mando pendiente); C-04 rotar token WA `apex_wh_2026` (confirmado que sigue siendo el mismo en producción — requiere acceso de Armando a Meta Business Manager, Mando no puede hacerlo); C-05 `git rm --cached` de los 4 `error_log` (acción manual, mismo pendiente que S2-12); D-02 descartado a petición de Mando (set de íconos SVG no cubre todos los emojis de `orden.php`, se deja como está); D-03 a D-07 (colores `#94a3b8` residuales, labels for/id, paginación resumen.php, estados vacío/error en orden.php, media queries) sin empezar | Pendiente — retomar Sprint D en sesión dedicada, A-04/C-04/C-05 requieren decisión/acción de Armando o Mando |
| ALTA | Ambos | **Auditoría externa 20-ago-2026 (`/home/mando/files_apexglass/auditoria_20_08_26/`, 61 hallazgos) — Sprint P0 (UPD-531), P1 (UPD-532) hechos. Sprint P2 (visual, 20 hallazgos) CERRADO 22-ago-2026 (UPD-534/535/536): 18 de 20 hallazgos atendidos (UX-1,2,3,5,6,8,9,10,11,14,15,17,18,19,20 + parcial 6).** Quedan documentados como pendiente aparte, sin fecha comprometida: **UX-7** (spinner en "Cargando…" de tablas, decisión consciente de no tocar — bajo impacto real); **UX-12** (modal compartido — evaluar si conviene un helper nuevo en utils.js o parchear módulo por módulo); **UX-4** (rebrand de paleta login/operador oscuro-ámbar vs. dashboard claro-azul vs. portal claro-ámbar — alto impacto visual para piso, requiere decisión explícita antes de tocar); **UX-13** (hoja de estilos `apex-ui.css` compartida real — cambio de arquitectura, blast radius amplio); **UX-16** (header de contexto/breadcrumb por módulo, prioridad Baja); y los 190 sitios de labels sin `for`/`id` fuera de login.php/finanzas_cobranza.php (hallado al hacer UX-6, no estaba en el alcance original de la auditoría). También sigue pendiente el resto de hallazgos medios/bajos de `auditoria_logica_negocio.md`/`auditoria_edge_cases.md` no cubiertos en P0/P1 | Sprint P2 cerrado — pendientes sueltos documentados arriba, sin sesión dedicada agendada |
| ALTA | Armando | **Certificado SSL de apex.glass vence 12-sep-2026, sin mecanismo de renovación (UPD-563).** No es que se haya roto con Cloudflare — nunca existió un cron/timer que lo renovara. El comando propio del panel (`bolt-cli run-auto-ssl`) falla con exit 1 cuando lo dispara el agente de AdminBolt, y corriéndolo directo como root da exit 0 pero no renueva nada (no-op silencioso, causa sin explicar todavía). Riesgo depende del modo SSL de la zona Cloudflare (no visible sin el panel): "Full strict" → si el cert vence, el sitio cae con 502 aunque el visitante vea el cert válido de Cloudflare; "Full" a secas → seguiría funcionando igual con el cert de origen vencido. Preguntar a Armando el modo SSL en cuanto tenga el panel a la mano. Investigación pausada a propósito la noche del 03-sep para no arriesgar estabilidad antes del arranque del turno — retomar en sesión de baja actividad, sin usar `--force` sin plan de rollback claro | Pendiente — 9 días de margen al 03-sep-2026 |
| MEDIA | Mando | **SSH puerto 2222 (workaround de UPD-562) no está activo tras el reinicio del VPS del 2-sep — la regla de firewall era solo runtime y se perdió (P1-1, hallado 03-sep).** No se ha tocado todavía en la sesión root. Antes de decirle a Armando "prueba el 2222 sin VPN", avisarle que la prueba de UPD-562 nunca fue válida (el puerto nunca llegó a abrirse en firme) para no confundirlo con un segundo intento fallido. Falta: confirmar si sobrevive `/etc/ssh/sshd_config.d/02-manual-port2222.conf`, reponer la regla de firewall, y esta vez darla de alta desde el panel AdminBolt (Firewall Rules) para que sí sobreviva un reinicio | Pendiente |
| MEDIA | Mando | **Journal de systemd no es persistente (`/var/log/journal` no existe, P1-3, hallado 03-sep).** Los logs de systemd viven solo en RAM y se borran en cada reinicio — toda la evidencia previa al reinicio del 2-sep se perdió y se volvería a perder en el próximo incidente. Fix documentado en el plan (`mkdir -p /var/log/journal` + `systemd-tmpfiles --create` + `systemctl restart systemd-journald`, con `SystemMaxUse` acotado) — bajo riesgo, no se ha ejecutado todavía | Pendiente |
| MEDIA | Mando | **Blindar contra AdminBolt los archivos que regenera en silencio (P1-4, hallado 03-sep).** Tres archivos con parches manuales que el panel puede pisar sin avisar si Armando toca algo relacionado desde ahí: `/etc/opt/remi/php84/php.d/zzz-apex.glass.ini` (vida de sesión 8h de UPD-560, límites de subida), `/etc/ssh/sshd_config.d/01-custom.conf` (el puerto 2222 vive aparte, en `02-manual-port2222.conf`, a propósito), y `/etc/httpd/vhosts.d/000-cloudflare-remoteip.conf` (mod_remoteip de UPD-563 — tiene precedente a favor, `panel-apex-glass-http.conf` es manual y sobrevive ahí desde el 27-jun). Ideal: configurar vida de sesión y límites de subida desde el panel en vez de a mano, donde se pueda | Pendiente |
| ALTA | Armando | **Fase B del diagnóstico Cloudflare (UPD-563) — requiere el panel de Cloudflare, que esta sesión no tiene.** (1) Confirmar el modo SSL de la zona (debe ser "Full strict"; si el certificado del Paso 3 de arriba vence sin resolver, esto decide si el sitio cae o no); (2) corregir MX/SPF de `apex.glass` — siguen apuntando al servidor de HostGator cancelado desde junio, cualquier correo a `@apex.glass` está roto (P2-1); (3) revisar que `panel.apex.glass` (AdminBolt) no esté proxeado por Cloudflare — hoy sale por IPs de Cloudflare y no debería; (4) revisar el nivel de seguridad / Bot Fight Mode de la zona — si está agresivo puede estar retando peticiones del SPA; (5) una vez resuelto lo anterior, cerrar el origen (443 directo a la IP del VPS) a solo los rangos de Cloudflare (P0-2) — hoy cualquiera puede saltarse el proxy yendo directo a `82.29.197.33`, coordinado con el firewall del servidor | Pendiente — esperando que Armando tenga acceso al panel |
| MEDIA | Ambos | **WhatsApp usernames / BSUID (Meta) — migración futura pendiente.** Armando reservó el username `@apex.glass` para la cuenta de WhatsApp Business (24-ago-2026, confirmación de Meta). Cuando un cliente activa su propio username, Meta deja de mandar su número de teléfono en el webhook y en su lugar manda un **BSUID** (Business-Scoped User ID, formato `PAIS.dígitos`, ej. `MX.134912086`) en el campo `user_id`. Verificado: **todo `api/whatsapp_webhook.php` está armado 100% sobre número de teléfono** — extrae `$msg['from']` como teléfono (línea ~43) y descarta cualquier valor que no tenga 10-15 dígitos numéricos (línea ~50, un BSUID se perdería en silencio); `whatsapp_conversaciones` se liga por `telefono` (match contra `clientes.telefono`/`telefono_alterno`); `campana_envios` liga respuestas de campaña por `telefono`. Todo el módulo Campañas (inbox, ventana 24h de UPD-521) depende de esa cadena. **No es urgente todavía** — reservar el username no activa el envío de BSUID por sí solo, depende de cuándo Meta lo prenda para nuestro número/clientes. Decisión 24-ago-2026: NO construir la migración completa por adelantado (mecanismo de Meta puede seguir cambiando) — solo se puso un canario barato (UPD-538): `api/whatsapp_webhook.php` ahora loggea `[BSUID?]` en error_log cuando un `from` no numérico llega y se descarta, en vez de perderlo en silencio como antes. Cuando aparezca ese log en producción, adaptar los 4 puntos de arriba (webhook, whatsapp_conversaciones, campana_envios, matching de clientes) para aceptar BSUID como identificador alterno sin perder el teléfono real donde ya se tiene. | Pendiente — canario puesto (UPD-538), migración completa en espera hasta que se vea `[BSUID?]` en el log real |

---

## 13. HISTORIAL DE ACTUALIZACIONES

REGLA: Cada cambio se agrega aquí. NUNCA se elimina. Código UPD secuencial e irrepetible.
Próximo UPD disponible: **UPD-567**

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

### Bloque actual: UPD-546 en adelante

| Código | Fecha | Resp. | Descripción |
|---|---|---|---|

| UPD-546 | 26-ago-2026 | Armando | Tabla "Saldos a Favor" del Reporte Dirección — 2 columnas nuevas "Saldo Previo" y "Saldo Posterior" por depósito/bono, para ver de un vistazo cuánto tenía el cliente antes del abono y cuánto le queda después. `api/reporte_direccion.php` `accion=ventas_cobranza`: como el saldo de un cliente no arranca en `$desde` del período, se trae el HISTÓRICO COMPLETO de `clientes_saldo_favor` (los 4 tipos: deposito/aplicacion/ajuste/referido, no solo lo mostrado en la tabla) de los clientes que aparecen en el período, se calcula el saldo corrido en PHP (ordenado por fecha/id) y se le pega `saldo_previo`/`saldo_posterior` a cada fila de depósito/referido que ya se mostraba. `app/modulos/reporte_direccion.php` agrega las 2 columnas a la tabla. Verificado a mano con un cliente real de 14 movimientos mezclados (depósitos + aplicaciones desde jun-2026): la secuencia calculada cuadra exacto (ej. depósito de $60,320 el 17-jul: previo $1,332.28 → posterior $61,652.28). `php -l` OK + `node --check` del `<script>` embebido. Sin prueba visual en navegador — pendiente que Armando lo confirme viéndolo en vivo. |

| UPD-547 | 26-ago-2026 | Armando | **Umbral de $10 para acreditar excedente de pago a Saldo a Favor.** Regla nueva de Armando: si un cliente tiene, por ejemplo, $994 pendientes y deposita $1,000, el excedente de $6 ya NO se acredita como depósito de saldo a favor — se registra el pago completo tal cual ($1,000 aplicados a la orden) y el residuo queda absorbido por ahora (pendiente que Armando defina más adelante cómo tratar esos pesos/centavos). Antes (A-10, UPD-531) CUALQUIER excedente >$0.01 generaba un depósito formal en `clientes_saldo_favor`. `api/finanzas.php` `accion=registrar_pago`: el corte pasó de `> 0.01` a `>= $10.00` (constante `$UMBRAL_EXCEDENTE_SALDO_FAVOR`) tanto para `$monto_aplicar` como para `$depositar_favor` — un solo endpoint, usado por Cobranza y por VoBo (`finanzas_vobo.php` llama el mismo `accion=registrar_pago`), así que el fix cubre ambas pantallas con un cambio. El mensaje de "excedente abonado a saldo a favor" en ambos frontends ya solo depende de `data.excedente` (queda `null` cuando el excedente es <$10), sin tocar frontend. **No se tocó** el patrón similar de `api/correcciones.php` (BLV-5, excedente cuando una corrección baja el total por debajo de lo ya cobrado) — dispara en un escenario distinto (corrección de dir_admin, no depósito del cliente) y Armando no lo mencionó; queda como posible ajuste aparte si también lo quiere con el mismo umbral. Verificado con casos reales de producción (ej. COT-0976, $1.97 pendiente). `php -l` OK. |

| UPD-548 | 27-ago-2026 | Armando | **Columna "Razón" en la tabla "Retrabajo del período" de Reporte Dirección (UPD-542) + aclaración de alcance.** Armando preguntó si se podía agregar la razón del retrabajo a esa tabla, y si ya incluía el retrabajo por errores de producción (piso). Aclarado: NO — esa tabla solo cuenta retrabajo **comercial** (`cotizaciones.es_retrabajo=1`, nace con folio/orden propia); el retrabajo de **piso** (`api/reproceso.php`, marca `piezas.es_retrabajo=1` + `razon_retrabajo` por pieza, reprocesa dentro de la MISMA orden sin folio nuevo) vive aparte en el módulo `?m=retrabajo` — decisión explícita de Armando de NO fusionarlos en esta tabla. El retrabajo comercial nunca capturaba motivo alguno (solo un checkbox al crear la cotización) — se agregó de cero: (1) BD aditiva `cotizaciones.motivo_retrabajo VARCHAR(255) NULL` (checkpoint: tag git `pre-motivo-retrabajo-2026-08-27` + backup `_backups/pre_motivo_retrabajo_cotizaciones_20260827.sql`); (2) `app/modulos/cotizacion.php` — textarea "Motivo del retrabajo *" en el panel rojo de retrabajo (`#rtMotivo`), obligatorio antes de guardar; (3) `api/cotizaciones.php` (`crear`) — valida que venga no vacío cuando `es_retrabajo=1` y lo guarda en el INSERT; (4) `api/reporte_direccion.php` (`accion=ventas_cobranza`) — agrega `motivo_retrabajo` a la fila de la tabla 2; (5) `app/modulos/reporte_direccion.php` — columna nueva "Razón" en esa tabla (colspan del detalle de pagos ajustado 5→6). Los retrabajos ya existentes (S-593/610/659/665) quedan con motivo `NULL` (se ven como "—") — no hay dato histórico que rescatar, solo aplica hacia adelante. Verificado con dry-run real en BD (`BEGIN`/`ROLLBACK`): INSERT con `motivo_retrabajo` OK, 0 filas de prueba quedaron en producción; confirmado que los 4 retrabajos reales existentes traen `NULL` como se esperaba. `php -l` en los 4 archivos + `node --check` de los `<script>` embebidos de ambos módulos frontend, todo OK. Sin prueba visual en navegador — pendiente que Armando confirme creando una cotización de retrabajo real y viendo la columna en Reporte Dirección. |

| UPD-549 | 27-ago-2026 | Armando | **Rediseño de "Resumen" (`?m=resumen`, página de entrada del dashboard) — de tablero solo-operativo a bienvenida corporativa con KPIs.** Motivo de Armando: nadie usaba esa página porque no era funcional (solo mostraba conteo de piezas por estatus + tabla de órdenes activas). Cambios: (1) `app/modulos/resumen.php` — banda superior navy (`#0f172a`, mismo tono del topbar) con saludo dinámico por hora + fecha en español, usando Syncopate (la misma fuente del logo "APEX GLASS") como firma visual — sin emojis, iconografía SVG vía `api/helpers/icons.php`; (2) fila de KPIs **Comercial** (Ventas del mes, Cobrado del mes, Por cobrar, Pendientes de VoBo) — visible **solo** a roles con visión financiera (dueño/dir_admin/director/administración/desarrollo, mismo criterio que Reporte Dirección), oculta a comercial/jefe_piso; reutiliza exactamente el mismo WHERE que la query canónica de Reporte Dirección (`c.es_retrabajo = 0`, UPD-510/512/541) para no reintroducir ese bug con una definición de "ventas" distinta; (3) fila de KPIs **Producción** (Órdenes activas, Piezas en proceso, Órdenes con retraso, Piezas terminadas hoy) — visible a todos los roles que entran a Resumen; (4) tabla de Órdenes Activas y Actividad Reciente se quedan igual, debajo de las 2 filas nuevas, sin tocar su lógica. **Bono de paso:** el badge de "vencidas" del sidebar usaba un conteo hecho en JS solo sobre las 15 órdenes de la página cargada (nunca todas las activas) — pendiente documentado en sección 12 desde hace semanas ("Badge órdenes vencidas global"); ahora usa el conteo real server-side (`operativo.retraso`, cuenta TODAS las órdenes activas con `fecha_entrega < hoy` y al menos una pieza sin terminar), así que ese pendiente queda resuelto como efecto colateral. `api/dashboard.php` agrega los bloques `operativo` (siempre) y `comercial` (null si el rol no califica) a la respuesta JSON existente, sin tocar los campos que ya consumían otros módulos. Las 6 queries nuevas se corrieron en modo solo-lectura contra producción antes de integrarlas (42 órdenes activas, $903,095.65 ventas del mes, $843,091.75 cobrado, $69,007.72 por cobrar, 4 pendientes de VoBo, 1 orden con retraso real — todo cuadra). `php -l` + `node --check` del `<script>` embebido en ambos archivos, OK. **Pendiente:** prueba visual en navegador — no hay Chrome DevTools/Playwright MCP en esta sesión; falta que Armando confirme que la banda de bienvenida, los KPIs y el ocultamiento de la fila Comercial para roles sin `ver_reportes` se ven como se espera. |

| UPD-550 | 27-ago-2026 | Mando | Auditoría 25-ago Sprint P0 (dinero/fiscal): CFDI ya timbra conceptos reconstruidos en servidor con descuento de referido y servicios (`facturapi.php`); `distribuir_flete` ya acumula costo y genera póliza (`ordenes_compra.php`); de paso, fix de `flete_tipo` con valor de ENUM inválido que truena en producción. Probado en BD con rollback, falta prueba visual. |
| UPD-551 | 27-ago-2026 | Mando | Auditoría 25-ago Sprint P1: reversa de entrega solo Logística+motivo+bitácora (`rutas.php` BLO-04), escaneo de piezas transaccional y exige 'terminado' (BLO-05), retry en colisión de código de cliente (`clientes.php`), salida exige orden activa (`salidas.php`). BLO-08 (portal) queda pendiente. Probado en BD con rollback. |

| UPD-552 | 28-ago-2026 | Armando | Fix: los resaques de Maquila (UPD-533) no aparecían en la Orden de Producción impresa (`app/imprimir_orden.php?id=1440`, reportado por Armando). Causa: UPD-533 cableó el cobro y la captura de resaques (`api/maquila.php`, `app/modulos/maquila.php`/`maquila_precios.php`, `piezas.resaques`) pero nunca tocó este archivo de impresión — el arreglo de "Servicios" por partida (rama `$esMaquila`) solo listaba Corte/Canteado-Filo Muerto/Taladro/Templado. Verificado en BD que el dato sí estaba guardado (cotización 1440/orden MA-S-701, partidas 1/3/5 con 2 resaques c/u). Agregada la línea `if ((int)($p['resaques'] ?? 0) > 0) $servicios[] = 'Resaque (' . $p['resaques'] . ')';` en `app/imprimir_orden.php`. `php -l` OK. Sin prueba visual en navegador — pendiente que Armando confirme viendo MA-S-701 impresa. |

| UPD-553 | 28-ago-2026 | Armando | **Estación Canteado: registro directo a Terminado cuando la pieza no necesita nada más.** Motivado por el caso de S-674 (espejo, sin templado/taladro/resaques): Armando notó que "Terminado" para esas piezas era puro papeleo — nadie hace nada físico ahí, solo alguien tenía que ir a escanear la pieza otra vez en la estación Terminado sin agregar valor, ya que el equipo de Canteado ya la dejó lista. Criterio (no amarrado al nombre "espejo", sino a la condición real, confirmado con Armando): pieza de Suministro (`p.tipo !== 'maquila'`) sin templado (`requiere_templado=0`) y sin taladro/resaques (`tp=ta=resaques=0`) — mismo cálculo que ya usaba `nextEstatus()` para saber que el siguiente paso lógico tras canteado es terminado directo. Cambio solo en `app/operador.php`: en la estación Canteado, cuando la pieza escaneada cumple esa condición, el botón pasa a "▶ Registrar: Canteado y Terminado" y la nueva función `doUpdateCanteadoTerminado()` hace las 2 llamadas a `api/actualizar_estatus.php` en secuencia (primero `canteado`, si sale bien encadena `terminado`) — sigue quedando canteado en el historial (trazabilidad de quién/cuándo), solo que ya no espera un segundo escaneo de otra persona. Sin cambios de backend — `actualizar_estatus.php` ya aceptaba `canteado→terminado` directo para piezas sin templado sin necesitar `omision:1` (confirmado leyendo `FLUJO_PREVIO['terminado']`). Si la 2ª llamada (terminado) falla, la pieza queda registrada en canteado y se avisa por toast para cerrarla manual — no se pierde nada. Checkpoint: tag git `pre-canteado-terminado-directo-2026-08-28`. `php -l` OK. Sin prueba visual en navegador (no hay Chrome DevTools/Playwright MCP en esta sesión) — pendiente que Armando/piso lo confirmen escaneando una pieza de espejo real en Canteado. |

| UPD-554 | 31-ago-2026 | Armando | **Venta anticipada de lámina completa (sin cortar), con reserva contra compra futura.** Motivado por un caso real: Armando va a comprar 66 láminas de un tipo, pero 3 de esas ya tienen comprador desde antes de que llegue la compra. Antes no había forma de vender una lámina completa (siempre se cotizaba por pieza cortada a medida) ni de comprometer una venta contra stock que todavía no existe físicamente. **BD (aditivo, con backup + tag `pre-venta-lamina-completa-2026-08-31`):** `cotizaciones_partidas` +columna `lamina_id` (liga la partida a la lámina exacta del catálogo `laminas` que se vende completa, sin cortar); tabla nueva `laminas_reservas` (`lamina_id`, `cantidad`, `cantidad_cumplida`, `cotizacion_partida_id`, `folio`, `estado` activa/cumplida/cancelada) — **no se liga a una OC en particular a propósito**: en cuanto llega CUALQUIER compra (por OC o manual) de esa misma lámina de catálogo, se liquida sola por antigüedad (FIFO), sin que Armando tenga que ir a ligar nada. **Helper nuevo `api/helpers/laminas_reservas.php`** con 3 funciones: `laminasCrearReservaVenta()` (crea la reserva al VoBo e intenta liquidarla contra el stock que ya exista), `laminasLiquidarReservas()` (se llama cada vez que entra una compra nueva — resuelve reservas activas más viejas primero, generando el movimiento de salida en `inventario_movimientos` solo por lo que realmente se cubre; soporta liquidación parcial en varias entregas), `laminasRevertirReservaPorPartida()` (al cancelar la orden, regresa a stock físico lo que ya se hubiera liquidado con un movimiento compensatorio — nunca borra el historial). **Conectado en 4 puntos:** (1) `api/finanzas.php` VoBo — por cada partida con `lamina_id`, crea la reserva; (2) `api/cotizaciones.php` y `api/admin_ordenes.php` acción cancelar — revierte la reserva de esa cotización; (3) `api/inventario.php` `registrar_compra` (ahora transaccional) — liquida reservas de esa lámina tras la compra manual; (4) `api/ordenes_compra.php` recepción de OC — liquida reservas tras cada entrega recibida de una partida tipo lámina. **Cotización (`app/modulos/cotizacion.php`):** checkbox "Vender lámina completa (descuenta stock)" por partida — al marcarlo aparece un selector de la lámina de stock (tipo/espesor/medida, catálogo `laminas`) que autocompleta ancho×alto de la partida con la medida exacta; el precio no cambia (sigue siendo `precio_m2` del catálogo Cristales × m² × cantidad, antes de IVA, como cualquier partida). **Inventario (`app/modulos/inventario.php` + `api/laminas.php` `accion=stock`):** nuevo campo `reservado`/`disponible` por lámina — la tabla de Stock muestra "N reservada(s) — venta anticipada" en ámbar bajo el conteo de stock cuando aplica, para que nadie cuente ni corte por error una lámina ya comprometida. **Fuera de alcance de este UPD** (mismo criterio que el pendiente C-6 ya documentado): el Optimizador de Corte y Sesión de Corte no descuentan lo reservado de su propio cálculo de disponibilidad — si hace falta cerrar ese hueco, se hace aparte. Probado con dry-run real en BD (`BEGIN`/`ROLLBACK`, sin tocar el flujo web): venta sin stock → reserva activa 0 cumplida; llega compra → liquida y stock libre correcto; 2 reservas de distinta antigüedad sobre la misma lámina con una compra que solo alcanza para la primera → confirma orden FIFO y liquidación parcial de la segunda; segunda entrega completa la reserva parcial; reversión por cancelación regresa el stock exacto — las 3 corridas terminaron con 0 filas de prueba en producción. `php -l` limpio en los 9 archivos tocados/creados + `node --check` del `<script>` embebido en ambos módulos frontend. **Sin prueba visual en navegador** (no hay Chrome DevTools/Playwright MCP en esta sesión) — pendiente que Armando confirme creando una cotización real con "Vender lámina completa" marcado y viendo el aviso de reservado en Inventario. |

| UPD-555 | 02-sep-2026 | Mando | Fix reporte #19: en `app/imprimir_salida.php` el QR de "escanear al cargar" solo se imprimía si el tipo de entrega calculado AL ABRIR la página era 'chofer' — en una orden con salida parcial previa de otro tipo (ej. S-554: recolección 14-ago, luego chofer 31-ago), el cálculo inicial tomaba el tipo de la salida anterior y el QR nunca aparecía, aunque en pantalla se seleccionara "Chofer" (el botón solo cambiaba texto, no mostraba el QR). Ahora el bloque del QR siempre se genera y el botón "Chofer/Recolección" lo muestra/oculta al instante. `php -l` OK. Sin prueba visual en navegador — pendiente confirmar imprimiendo una salida parcial real. |

| UPD-556 | 02-sep-2026 | Mando | Fix reporte #17: en `app/imprimir_croquis.php` (PDF de croquis), un resaque colocado exactamente pegado a un borde (0mm de distancia — común en resaques para clips) hacía que el código, para "evitar mostrar 0mm", calculara la cota desde el borde OPUESTO — resultando en una cota gigante y falsa cruzando toda la pieza (ej. "1160 mm" o "1900 mm") que tapaba visualmente la cota correcta del otro eje (ej. "250 mm"). Quitada esa regla — ahora un resaque pegado al borde simplemente muestra "0 mm", sin cruzar la pieza. Verificado recreando el cálculo con los datos reales de la partida del reporte (1160×1900mm, 4 resaques). `php -l` OK. **Confirmado visualmente por Mando 02-sep-2026** (screenshot real de COT-1533/S-716, GERARDO GAUNA): ya no hay cota falsa cruzando la pieza, los 4 resaques muestran "0 mm" en el lado que están pegados al borde. |

| UPD-557 | 02-sep-2026 | Mando | **Auditoría 25-ago, resto de Franja 1 (de "Correcciones" para abajo, a petición explícita).** BLV-6: `api/correcciones.php` ya clampa descuento 0-100% y descarta ancho/alto/precio negativos (mismo candado S2-05 que ya tenía cotizaciones.php); de paso, fix de que `cantidad` se guardaba cruda (podía quedar <1) aunque el total ya se calculaba con mínimo 1 — ahora usan el mismo mínimo. BLO-03: `api/ordenes_compra.php` `cambiar_estado` ya no deja forzar "cerrada" con material sin recibir ni "pagada" sin que el saldo esté cubierto por pagos reales (reabrir hacia atrás no se tocó, sigue igual). BLO-06: `api/admin_ordenes.php` `corregir_estatus` revivía una orden cancelada a "activa" a ciegas (sin pasar por el candado que sí tiene `accion=restaurar` de re-vincular la cotización) — ahora se rechaza y pide restaurar primero. BLO-11: `api/campanas.php` ya no inserta destinatarios duplicados por teléfono al armar una campaña (mismo número en distinto formato, o repetido entre cliente/prospecto) — confirmado con un caso real en BD (mismo teléfono en 2 clientes distintos) que hubiera duplicado el envío. Los 4 probados con datos reales dentro de transacciones con `ROLLBACK` garantizado, uno por uno con confirmación del usuario antes de avanzar al siguiente. `php -l` limpio en los 4 archivos. **BLO-07 (Sesión de Corte sin reversa de stock) se deja pendiente a propósito** — el más riesgoso de los 6: revertir una sesión confirmada implica decidir qué hacer con piezas que ya avanzaron en producción y con el bono de pedacería ya calculado; usuario decidió documentarlo para sesión dedicada en vez de resolverlo hoy. Con esto queda cerrada toda la Franja 1 de la auditoría del 25-ago excepto BLO-07 y BLO-08 (portal por nombre, ya documentado desde antes, esperando decisión de backfill de 216 órdenes). |

| UPD-558 | 02-sep-2026 | Mando | **Auditoría 25-ago, hallazgos que solo estaban en el detalle de `bl_auditoria_operaciones.md` (no en el resumen consolidado ya cerrado en UPD-557).** BLO-15: `api/inventario.php` `registrar_uso` (descuento manual de stock/mermas) exigía solo `ver_inventario` (que `comercial` también tiene) — ahora exige rol de escritura real (dir_admin/administracion/dueno/desarrollo) y la fecha se fuerza a hoy server-side (ya no se podía registrar con fecha atrasada para esconderla en otro período de costo). BLO-14: `api/actualizar_estatus.php` — el WhatsApp automático "orden lista" tenía check-then-set no atómico (dos escaneos casi simultáneos de las últimas 2 piezas de una orden podían mandarlo duplicado); ahora usa claim atómico antes de enviar, con liberación del candado si Meta rechaza el envío o el teléfono es inválido (mismo comportamiento de reintento de antes). BLO-10: mismo patrón en `api/rutas_lib.php` (`enviarAvisosInicioRuta`, avisos de inicio de ruta) y `api/salidas.php` (arranque automático de ruta por escaneo, que además leía `ruta_estado` con dato potencialmente viejo) — ambos ya usan el mismo claim atómico que ya tenía el botón manual "Iniciar Ruta". Los 3 puntos probados con datos reales (una orden, una ruta real) dentro de transacciones con `ROLLBACK` garantizado. `php -l` limpio en los 4 archivos. **Quedan sin tocar, documentados aparte:** BLO-12 (OC de flete pura se autocompleta sin capitalizar el costo — dinero, media) y BLO-13 (rechazo de piezas en puerta sin flujo financiero — requiere más diseño, no es solo un candado). |

| UPD-560 | 02-sep-2026 | Armando | **Fix: usuarios reportaban que el sistema "los sacaba" o "no los dejaba entrar" de forma intermitente, todo el día.** Investigación de logs (firewall, fail2ban, PHP-FPM, Apache, journalctl) descartó ataque/caída/DoS — 0 bans relevantes, sin OOM, sin errores de aplicación. El reinicio completo del VPS de hoy 20:31 UTC fue solicitado por Armando (no infraestructura) y no fue la causa: las sesiones viven en disco persistente (`/home/apexglass2025/tmp/sessions/`), sobrevivieron el reinicio sin problema. Causa real encontrada en `access_log`: ráfagas reales de `401 Sesión requerida` en `campanas.php`/`reportes.php`/`notificaciones.php`/`mensajes.php`/`autorizaciones.php` (hasta 87 en 1 minuto) — `session.gc_maxlifetime` estaba en `1440` segundos (24 min, el default de PHP, nunca ajustado a propósito) en `/etc/opt/remi/php84/php.d/zzz-apex.glass.ini`. Con jornadas donde una pestaña queda abierta sin interacción >24 min (llamada, ir a piso, junta), el servidor podía recolectar el archivo de sesión de fondo; el siguiente poll en esa pestaña recibía 401 en silencio, y si el usuario intentaba guardar algo, el token CSRF de esa pestaña ya no coincidía con la sesión nueva (mismo síntoma ya confirmado y documentado el 20-ago, que se resolvía con F5). Subido a `session.gc_maxlifetime = 28800` (8 horas) en ambos bloques `[HOST=apex.glass]`/`[HOST=www.apex.glass]` del `.ini`; `php -l` OK, `systemctl reload php84-php-fpm` (sin caída), verificado el valor efectivo en vivo con una petición HTTP real (`ini_get()` devolvió 28800 desde apex.glass). Backup del `.ini` original en `/root/backup_zzz-apex.glass.ini.<timestamp>`. Sin código tocado — cambio de configuración únicamente. |
| UPD-561 | 02-sep-2026 | Mando | **Reporte "se batalla mucho para usar el sistema" — causa real: el VPS se congeló por completo ~5.5 horas (14:59 a 20:31), sin swap configurado.** Investigado con Mando en consola root. `mysql_error.log` mostró que MariaDB nunca hizo shutdown ordenado — el último registro antes del hueco fue un timeout normal a las 14:59:16, y el siguiente fue ya el arranque con "crash recovery" (816 páginas) al reiniciarse a las 20:31 — la máquina completa quedó sin responder ese rato, no fue un servicio individual cayéndose. Mismo patrón exacto ya había ocurrido el 12-ago-2026 (segunda vez en 3 semanas). No se encontró OOM-killer ni ataque en los logs revisados — causa raíz del pico de memoria queda sin identificar. Fix aplicado (mitigación, no cura): **swap de 4GB creado y activado** (`/swapfile`, `mkswap`+`swapon`, agregado a `/etc/fstab` para persistir tras reinicio, `vm.swappiness=10` en `sysctl.conf`) — antes el VPS tenía `Swap: 0B`, así que cualquier pico de RAM no tenía colchón y podía congelar el sistema entero en vez de solo ir más lento. Verificado con `free -h` (`Swap: 4.0Gi`, 0 usado) y `tail /etc/fstab` (línea agregada sin tocar el resto). Sin cambio de código — 100% configuración de sistema operativo, ejecutado por Mando como root. **Pendiente:** si el sistema se vuelve a sentir lento antes de congelarse, correr `top`/`htop` en el momento para atrapar qué proceso está disparando el consumo de RAM — el swap es red de seguridad, no explica la causa original. **CORRECCIÓN (UPD-563, 03-sep-2026): el diagnóstico de este UPD está mal — el VPS NO se congeló.** Auditoría más profunda (root, con acceso a `historial_estatus`) encontró escrituras de base de datos continuas durante toda la ventana 08:56–14:59, sin ningún hueco largo. El hueco visto en `mysql_error.log` era simplemente ausencia de errores (un log de errores no escribe nada cuando no hay errores — no es evidencia de caída) y el "crash recovery" correspondía al reinicio de las 14:31 que Armando pidió a propósito, no a un congelamiento previo. El swap de 4GB se queda (buena práctica, no hace daño), pero no hay una causa de RAM pendiente de identificar — ese pendiente queda cerrado como falso positivo. La causa real de "se batalla para usar el sistema" ese día fue la caducidad de sesión a los ~24 min (`session.gc_maxlifetime`, ya corregida esa misma tarde por este mismo UPD) combinada, desde la noche, con el hallazgo de UPD-563 (Cloudflare ocultando la IP real de todos los usuarios). |
| UPD-562 | 02-sep-2026 | Armando | **Diagnóstico: SSH sin VPN se corta durante el intercambio de llaves — confirmado bloqueo de puerto 22 en la ruta de Armando (Telmex/router), no DNS ni servidor. Puerto 2222 agregado como workaround.** Seguimiento del hallazgo de `project_telmex_dns_desconexiones.md` (misma sesión, más temprano): Armando pasó el log real de `ssh -vvv root@82.29.197.33` sin VPN — el TCP conecta, se intercambian banners SSH y KEXINIT completo en ambos sentidos, y se corta justo cuando el cliente ya mandó el paquete ECDH init y espera la respuesta del servidor ("Connection closed by 82.29.197.33 port 22"). Verificado en el servidor con `journalctl -u sshd` (día completo): **cero rastro** de la IP de Armando tocando sshd — ni un intento fallido, ni timeout — a diferencia de 2 bots reales que sí quedan logueados aunque se corten en una etapa aún más temprana (identificación). Como conectar por IP directa no usa DNS ("hostname 82.29.197.33 is address", confirmado en su propio log), y el servidor no ve nada, la conclusión es que **algo en la ruta de Armando (router o Telmex) intercepta/simula el tráfico del puerto 22 y lo corta apenas detecta que es SSH real** — la conexión que su cliente "ve" avanzar nunca llega a este servidor. Coincide con el hallazgo previo de la misma sesión: 443 (HTTPS) sí pasa bien por el mismo bloque de Telmex, 22 no. **Fix/diagnóstico aplicado (autorizado por Armando):** `Port 2222` agregado en archivo nuevo `/etc/ssh/sshd_config.d/02-manual-port2222.conf` (separado de `01-custom.conf` a propósito — ese lo regenera AdminBolt desde su plantilla mustache y solo escribe `Port 22`, así que un segundo `Port` ahí se hubiera perdido en el próximo cambio desde el panel); `sshd -t` OK, `systemctl restart sshd` sin caída, confirmado escuchando en 22 y 2222 (`ss -tlnp`). Regla de firewall para 2222 agregada en runtime (`nft add rule ip filter INPUT tcp dport 2222 accept`) sobre la tabla real activa (`/etc/sysconfig/nftables.conf`, cargada por `nftables.service` — **no** la de `/etc/nftables/main.nft`, que es solo un archivo de ejemplo sin usar). Nota importante: la regla de firewall es **solo en runtime, no persiste un reinicio** — y `/etc/sysconfig/nftables.conf` trae la advertencia "auto-generado, no editar a mano" (lo regenera AdminBolt desde `firewall_rules` en su BD) — si el puerto 2222 se vuelve permanente, hay que darlo de alta desde el panel AdminBolt (Firewall Rules), no solo dejar la regla de runtime. **Pendiente:** que Armando pruebe `ssh -vvv -p 2222 root@82.29.197.33` sin VPN — si conecta, confirma 100% el bloqueo de puerto (router/ISP) y da acceso funcional ya mismo; si falla igual, el problema no es específico del puerto 22. |
| UPD-559 | 02-sep-2026 | Armando | **Fix: subida de video en Archivos de Video (`?m=media_manager`) daba "error de red" y luego "Parte no recibida".** Armando reportó el error al intentar subir un video de >8MB. Causa en cadena, 2 límites de PHP distintos configurados por AdminBolt para el dominio apex.glass (`/etc/opt/remi/php84/php.d/zzz-apex.glass.ini`, `[HOST=apex.glass]`): `post_max_size=8M` y `upload_max_filesize=2M` (más estricto). El chunk de subida en `app/modulos/media_manager.php` estaba fijado en exactamente 8MB — igual al primer límite, así que el overhead de `multipart/form-data` (~700 bytes) lo empujaba por encima y PHP tumbaba la petición completa antes de que la app respondiera nada (se ve como "error de red" en el navegador; confirmado en `php-fpm-error.log`: "POST Content-Length of 8389325 bytes exceeds the limit of 8388608 bytes"). Se bajó el chunk a 6MB — la petición ya pasaba `post_max_size`, pero entonces se topó con el límite más chico (`upload_max_filesize=2M`, aplica por archivo individual dentro de la petición, no por el total) y `api/media_manager.php` respondía "Parte no recibida" (línea 145, `$_FILES['chunk']['error'] !== UPLOAD_ERR_OK`). Chunk bajado de nuevo a **1.5MB**, con margen bajo ambos límites — confirmado por Armando que ya sube el archivo. **Hallazgo adicional en el camino, corregido de una vez:** el SPA (`cargarModulo()` en `app/dashboard.php`) carga cada módulo con `fetch()` sin ningún control de caché — el navegador servía la copia vieja de `media_manager.php` (con el chunk viejo) después de cada edición, obligando a recarga forzada (Ctrl+Shift+R) para ver el cambio; mismo síntoma que UPD-506 pero en la carga de módulos del SPA, no cubierto por ese fix. Agregado `cache: 'no-store'` al `fetch()` de `cargarModulo()` — de aquí en adelante cualquier edición en vivo de un módulo se refleja con un F5 normal, sin necesitar recarga forzada. `php -l` OK en los 2 archivos. |
| UPD-563 | 03-sep-2026 | Mando | **Diagnóstico de fondo del incidente del 2-sep, sesión aparte corriendo como root (`/root/.claude`) — hallazgo real: Cloudflare (activado ~17:53 UTC del 2-sep) ocultaba la IP de todos los visitantes al servidor, no el código ni el VPS. Corregido y verificado. Certificado del sitio investigado, sin resolver, pausado a propósito antes del arranque del turno.** Continuación del plan `/home/mando/.claude/plans/revisame-el-sistema-en-luminous-graham.md` (Pasos 1-2 ya hechos en la sesión anterior como usuario `mando` sin sudo; este UPD cierra Paso 1/2 con verificación root y avanza Paso 3). **P0-1 (el hallazgo más importante):** Apache no tenía `mod_remoteip` configurado, así que desde que el tráfico empezó a pasar por el proxy de Cloudflare, el servidor veía la IP del nodo de Cloudflare en vez de la del usuario real — probado con un login fallido real que quedó registrado en `login_intentos` con una IP de Cloudflare (`104.22.14.216`) en vez de la del cliente. Esto volvía compartidos entre TODA la empresa: los baneos de `fail2ban` (bloquear un nodo de Cloudflare saca a cualquiera que salga por ahí — mismo patrón de "la VPN sí funciona, sin VPN no" reportado por el equipo, porque la VPN cae en otro nodo), el bloqueo de intentos de login (`login_intentos` se llavea por IP+usuario), y volvía inservible cualquier bitácora de IP del sistema. **Fix:** `/etc/httpd/vhosts.d/000-cloudflare-remoteip.conf` (nombre `000-*` para que cargue antes que los vhosts) con `RemoteIPHeader CF-Connecting-IP` + los 24 rangos oficiales de Cloudflare descargados de `cloudflare.com/ips-v4`/`ips-v6` (no de memoria) + localhost. **Lección de método que costó un intento fallido:** el primer archivo se puso en `/etc/httpd/conf.d/`, que `httpd -t` validó como "Syntax OK" — pero ese directorio **no se incluye nunca** en este servidor (confirmado con `httpd -t -D DUMP_INCLUDES`; AdminBolt solo lee `conf/modules-load.conf`, `conf/modules-config.conf` y `vhosts.d/*.conf`), así que el archivo quedó inerte y el `OK` no probaba nada — corregido a `vhosts.d/`. **Verificado end-to-end** dos veces (sesión anterior como `mando`, y de nuevo esta noche como root): la IP registrada por la app pasó de una de Cloudflare a la IP real del visitante; una petición directa al origen (sin pasar por Cloudflare) conserva su IP sin modificar; el log del vhost ya registra la IPv6 real de Telmex de un usuario navegando en vivo. **P0-1 efecto colateral en el informe:** con `conf.d/` confirmado como no-incluido, se determinó que `mod_evasive` (cuya config vivía ahí) nunca ha estado activo en este servidor — baja de "riesgo latente" a "no aplica"; `mod_security` sí está activo (se configura desde `modules-config.conf`, tiene `modsec_audit.log`/`modsec_debug.log` reales). **Paso 2 — fail2ban revisado con las IPs reales ya restauradas:** las 5 jaulas activas, **cero IPs de Cloudflare baneadas** en el momento de la revisión, jaulas de Apache en cero, único baneo activo un bot conocido en SSH ajeno a la oficina — la hipótesis de "un baneo a un nodo de Cloudflare tumbó a medio equipo" era razonable pero no fue lo que ocurrió esa noche en concreto (el síntoma sí es 100% real y viene del mismo hueco P0-1, solo que no vía un baneo activo capturado en el momento de mirar). **Paso 3 — certificado, hallazgo crítico, sin resolver:** el sitio corre con el mismo certificado desde el 14-jun (huella SHA-256 idéntica en disco y en lo que sirve Apache), vence **12-sep-2026 (9 días de margen al momento de este UPD)**. **No hay ningún mecanismo de renovación — nunca existió, no lo rompió Cloudflare:** crontab de root solo tiene las 2 tareas de AdminBolt (`check-for-updates`, `refresh-hosting-accounts-stats`), sin timer de systemd de SSL, sin actividad ACME en el journal de `bolt-agent`. Se investigó `bolt-cli` (tiene comandos propios `run-auto-ssl`/`run-hostname-ssl`/`ssl-health-check`): correr `ssl-health-check` disparó internamente ambos comandos vía el agente local de AdminBolt (puerto 3030) y **los dos fallaron con exit 1** (confirmado en `journalctl -u bolt-agent`, sin detalle del error real porque el agente no captura stdout/stderr al log) — es decir, cuando el panel intenta renovar solo, falla activamente, no solo está ausente. Corriendo el mismo comando directo como root (fuera del agente) dio exit 0 sin ninguna salida, pero **tampoco renovó nada** (mismo archivo, mismo `mtime`, misma huella antes/después) — un no-op silencioso, causa todavía sin explicar. Se probó (autorizado) que la ruta de validación ACME (`/.well-known/acme-challenge/`) está despejada — Cloudflare la deja pasar al origen sin bloquear. **Investigación pausada aquí a propósito, sin usar `--force`:** el usuario interrumpió antes de escalar a `run-auto-ssl --force` por priorizar que el sistema estuviera 100% estable para el arranque del turno en pocas horas, en vez de seguir escarbando el certificado esa misma madrugada — decisión correcta, quedan 9 días de margen real. **Verificación de salud en vivo, todas las IPs, no solo la de quien opera:** con Paso 1/2 aplicados, se revisó el tráfico real del día completo (no solo pruebas propias) — **0 códigos 401/5xx en todo el log**, **0 usuarios bloqueados** en `login_intentos` (solo 2 filas de prueba, limpiadas), **0 IPs de Cloudflare baneadas**, DNS resuelve igual desde Cloudflare (1.1.1.1) y Google (8.8.8.8) sin ningún split-DNS que trate distinto a la oficina. El único 403 que se repetía cada ~60s en el log (`api/autorizaciones.php`) es ruido normal y preexistente — ese endpoint rechaza a cualquier rol que no sea `dir_admin`/`desarrollo` a propósito, y el frontend de `dashboard.php` (línea ~895) lo traga en silencio (`.catch(function(){})`) sin cerrar sesión ni mostrar error; no es nuevo ni parte del incidente. **Conclusión de la noche: el sistema debería estar arriba al 100% para todos los roles/IPs cuando entre el equipo** — la causa de fondo de "me saca"/"no me deja entrar" (P0-1) ya está corregida y verificada con tráfico real, independiente del certificado (que sigue vigente 9 días más). **Pendiente (ver también sección 12):** cerrar la investigación del certificado sin prisa (P1-2), reponer y blindar el workaround de SSH puerto 2222 que UPD-562 dejó en runtime y ya no está activo tras el reinicio del VPS (P1-1, sin tocar esta sesión — no confirmar con Armando que "el 2222 no funciona" sin decirle primero que la prueba de UPD-562 nunca fue válida, el puerto nunca llegó a abrirse en firme), journal persistente (P1-3, `/var/log/journal` no existe — toda la evidencia previa al reinicio del 2-sep se perdió y volvería a perderse), blindar contra AdminBolt los 3 archivos que regenera en silencio (P1-4: `zzz-apex.glass.ini`, `sshd_config.d/01-custom.conf`, y ahora también `vhosts.d/000-cloudflare-remoteip.conf` — este último con precedente a favor, `panel-apex-glass-http.conf` es manual y sobrevive en esa carpeta desde el 27-jun), MX/SPF de `apex.glass` apuntando todavía al HostGator cancelado (P2-1, requiere Fase B con Armando en el panel de Cloudflare), y no restringir el origen 443 a los rangos de Cloudflare (P0-2) hasta que Armando tenga el panel de Cloudflare a la mano (riesgo real de dejar a toda la empresa fuera sin forma de revertir desde la terminal). Limpiadas las filas de prueba `portal:ZZTEST-%` en `login_intentos`. |

| UPD-565 | 03-sep-2026 | Armando | Corrección de datos (no código): **S-723** (orden id=986, COT-1544) se había registrado por error como "Recolección en planta" en vez de "Entrega a domicilio" (la cotización sí decía domicilio; el error fue al elegir el tipo de salida en `imprimir_salida.php` al momento de imprimir/registrar). Eso ya había cerrado la orden (`estado='entregada'`, `fecha_cierre` del mismo día) y marcado su única pieza (id=6606) como `entregado`, sin pasar por Rutas (tipo recolección no activa `requiere_ruta`). Revertido dentro de una transacción con SELECT de verificación antes/después: pieza 6606 `entregado`→`terminado`; borrado el registro de salida errónea (`orden_salidas` id=609 + su detalle en `orden_salida_piezas`); orden 986 `entregada`→`activa`, `fecha_cierre` limpiado, `requiere_ruta=1` para que aparezca en Logística Rutas → Pendientes de asignar. `tipo_entrega` de la cotización y de la orden ya estaban en `domicilio`, sin necesidad de tocarlos. El WhatsApp de "pasa a recoger a planta" (plantilla `salida_recoleccion`) ya se había enviado al cliente al momento del error — a petición de Armando NO se mandó ningún mensaje de corrección, solo se avisará al cliente cuando se programe la ruta real. |
| UPD-566 | 05-sep-2026 | Armando | **Nuevo: recibo imprimible del Bono de Corte — Pedacería.** Armando pidió poder generar el recibo del pago del bono (módulo `?m=bono_corte`, UPD reciente de Mando) para dárselo firmado a Angel. Antes solo existía el botón "Marcar como pagado" en BD, sin ningún comprobante imprimible. Nuevo `app/imprimir_bono_corte.php` (mismo estilo visual que `imprimir_apartado.php`: header APEX GLASS, Syncopate, doc-title-bar navy) — muestra operador, semana laborada, m² de pedacería aprovechados, monto, fecha/hora de pago, quién autorizó, nota breve de la fórmula ($150 por cada tramo completo de 18 m², primer tramo proporcional) y área de firmas ("Recibí conforme" del operador + "Autorizó el pago"); si el registro todavía está en estado `calculado` (no pagado), el archivo se niega a imprimir con un mensaje explícito en vez de mostrar un recibo de algo que no se ha pagado. `api/bono_pedaceria.php` `accion=resumen_semana` — se agregó `id` al SELECT de `bono_pedaceria_pagos` y se expone como `pago_id` en la respuesta (antes no se exponía el id del pago individual, solo estado/aprobado_por/aprobado_at, así que no había forma de enlazar un recibo a un pago específico). `app/modulos/bono_corte.php` — botón nuevo "Imprimir recibo" junto al pill "Pagado ✓" de cada operador (visible solo cuando `estado==='pagado'` y existe `pago_id`), abre `imprimir_bono_corte.php?id=<pago_id>` en pestaña nueva. **2 rondas de fix de ruta, ambas confirmadas por Armando el mismo día:** (1) el archivo vive en `app/` (no en la raíz de `produccion/`), igual que el resto de los `imprimir_*.php` — Armando probó primero pegando la URL a mano sin `/app/` y dio "File not found"; correregida la URL manual funcionó. (2) el botón "Imprimir recibo" del propio módulo seguía roto porque el `onclick` usaba `../imprimir_bono_corte.php` — el SPA nunca cambia la URL del navegador (se queda en `app/dashboard.php`), así que una ruta relativa dentro del HTML inyectado por un módulo se resuelve contra `app/`, no contra `app/modulos/` de donde viene el archivo fuente; el `../` de más mandaba a `produccion/imprimir_bono_corte.php` (inexistente). Corregido a ruta relativa simple `imprimir_bono_corte.php` (mismo patrón ya usado en `finanzas_cobranza.php:595` para `imprimir_salida.php`) — confirmado funcionando por Armando tras el fix. `php -l` limpio en los 3 archivos + `node --check` del `<script>` embebido en el módulo. Verificado contra datos reales de BD (Angel Baltazar, 5 semanas pagadas) antes de la prueba visual. |
| UPD-564 | 03-sep-2026 | Armando | **Fix: columna "Entrega" en `app/imprimir_salida.php` (remisión) mostraba conteo acumulado en vez del rango de la entrega específica.** Armando reportó que en una orden con 2 entregas parciales (4+4 de 22 láminas), la 2a impresión decía "8 de 22" (acumulado de ambos envíos) en vez de indicar cuáles piezas iba ESTA entrega en concreto. Fix: la columna ahora muestra el rango por número de pieza del envío más reciente que tocó esa partida — "5 al 8 de 22 al 03/sep/2026" — tanto en el render PHP inicial (`formatRangoPiezas()`, usa `$pieza_salida_idx` para saber qué salida tocó cada pieza por última vez) como en la actualización JS en vivo justo al confirmar una nueva entrega (`actualizarCeldasEntrega()`). Piezas no consecutivas se agrupan en tramos ("5 al 8, 11"). **Seguimiento en la misma sesión** (pregunta de Armando "¿y si quiero reimprimir la primera entrega?"): agregada capacidad de reimprimir cualquier entrega histórica específica, no solo el estado actual — nuevo mapa `PIEZAS_MAP` (id→partida/pieza_num de TODAS las piezas de la orden, embebido en JS) + función genérica `estadoEntregaCeldas(cutoffIdx)` que recalcula la columna Entrega "como si el tiempo estuviera congelado" justo después de la entrega `cutoffIdx` (ignora entregas posteriores); usada tanto por el botón "Reimprimir" genérico (cutoff = última entrega, estado actual) como por el link nuevo "Reimprimir esta" en cada renglón del historial "Entregas registradas" del menú (`reimprimirEntrega(idx)`, cutoff = esa entrega específica) — esta última agrega un badge azul "REIMPRESIÓN ENTREGA N DE M" junto al título para no confundirla con el estado actual, y restaura la fecha de entrega original del header al volver al "Reimprimir" genérico. Verificado con `php -l`, `node --check` del `<script>` embebido, y simulación de la lógica completa contra los datos reales de la orden 990 (S-990, cotización COT-1557): entrega 1 (piezas 6617-6620) da "1 al 4 de 22", entrega 2 (piezas 6621-6624) da "5 al 8 de 22" — cada reimpresión aislada en su propio rango, sin mezclarse. Sin prueba visual en navegador (no hay Chrome DevTools/Playwright MCP en esta sesión) — pendiente que Armando lo confirme viéndolo en vivo. |

**Próximo UPD disponible: UPD-567**
