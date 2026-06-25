# APEX GLASS — MEMORIA ÚNICA DEL PROYECTO
# Sistema de Rastreo de Producción (Templadora Noreste, S.A. de C.V.)
# Última actualización: 25 junio 2026 | Próximo UPD disponible: UPD-219

**REGLA DE ORO:** Este archivo es la memoria compartida del proyecto. Claude lo lee al inicio de cada sesión y lo actualiza al terminar. Armando y Mando trabajan en el mismo archivo. NUNCA borrar entradas anteriores — solo agregar.

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

---

## 1. INFRAESTRUCTURA — ESTADO ACTUAL (POST-MIGRACIÓN 14-Jun-2026)

### VPS Hostinger (ACTIVO — servidor principal)
- Plan: KVM 2 (2 vCPU, 8GB RAM, 100GB NVMe)
- OS: AlmaLinux 9 | Panel: AdminBolt
- IP: 82.29.197.33 | Hostname: srv1754712.hstgr.cloud
- Dominio: apex.glass → 82.29.197.33 (DNS name.com actualizado 14-jun)
- SSL: ZeroSSL activo (expira Sep 12, 2026)

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

**Lecciones aprendidas VPS:**
- AdminBolt guarda vhosts en `/etc/httpd/vhosts.d/` (NO en conf.d/)
- Terminal browser Hostinger auto-indenta → rompe heredocs; usar Python one-liners
- MariaDB AlmaLinux 9: si falla galera, hacer `dnf clean all` antes de reinstalar
- AdminBolt bloquea upload .sql → comprimir a .zip primero
- CSP upgrade-insecure-requests de AdminBolt requiere HTTPS para que el browser funcione
- DB_PASS con caracteres especiales → usar Python getpass para no exponer en pantalla
- **AdminBolt pone `Permissions-Policy: camera=()` global** en `/etc/httpd/conf/modules-config.conf` — bloquea cámara en todos los sitios. Fix: agregar `Header always set Permissions-Policy "geolocation=(), microphone=(), camera=(self)"` en el vhost de apex.glass (443) para sobreescribirlo.

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
| MEDIA | Ambos | Alerta reorden automática láminas (esperar 2-3 semanas historial) | Pendiente |
| BAJA | Armando | Error consola JS guardarCristal | Pendiente |
| BAJA | Ambos | m2_requeridos en laminas.php | Pendiente |
| MANUAL | Armando | Actualizar CTN-259: "PRUEBA PORTAL" → "JESUS MANUEL SALDANA DE LA ROSA" | Pendiente |
| MANUAL | Armando | Capturar precios: Claro 12mm, Claro Zafiro 9mm, Filtrasol 9mm, Tintex 6mm, Tintex 9mm | Pendiente |
| ALTA | Ambos | SEGURIDAD: Fail2ban en puerto 8443 (AdminBolt) — protección brute force, panel expuesto al internet | Pendiente |
| ALTA | Ambos | SEGURIDAD: FTP puerto 21 abierto — evaluar migrar a SFTP (puerto 22) y cerrar FTP | Pendiente |
| ALTA | Ambos | SEGURIDAD: Rate limiting en login.php — verificar/implementar bloqueo por intentos fallidos | Pendiente |
| MEDIA | Armando | SEGURIDAD: SSH hardening — verificar que solo acepta llaves, no password | Pendiente |
| MEDIA | Armando | SEGURIDAD: Revisar permisos de archivos en servidor (buscar 777) | Pendiente |
| MEDIA | Armando | UX: Dark mode en dashboard (topbar ya es oscuro, extender al sidebar y contenido) | Pendiente |
| BAJA | Armando | UX: Badge órdenes vencidas global — actualmente solo se actualiza desde módulo Resumen | Pendiente |
| BAJA | Armando | UX: Paginación resumen con total de registros "Mostrando X–Y de Z órdenes" | Pendiente |

---

## 13. HISTORIAL DE ACTUALIZACIONES

REGLA: Cada cambio se agrega aquí. NUNCA se elimina. Código UPD secuencial e irrepetible.
Próximo UPD disponible: **UPD-168**

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

## 14. PROTOCOLO PARA CADA SESIÓN

Al terminar cualquier sesión con cambios:
1. Subir archivos modificados a Drive (`ARCHIVOS SERVIDOR/`)
2. Registrar el cambio con próximo UPD en este archivo
3. Las tareas completadas se marcan HECHO — NUNCA se borran

### Bloque actual: UPD-151 en adelante

| Código | Fecha | Resp. | Descripción |
|---|---|---|---|
| UPD-151 | 22-jun | Armando | Fix reporte_direccion.php: % Entrega a tiempo ahora usa a_tiempo/(a_tiempo+con_retraso) — solo órdenes terminadas, excluye en_proceso y retraso_abierto. Barra apilada queda en 2 segmentos (verde/rojo). Concentrado mensual usa mismo criterio |
| UPD-152 | 22-jun | Armando | Fix campanas WA "error de red": accion=enviar ahora usa fastcgi_finish_request() para cerrar conexión HTTP inmediatamente y seguir enviando en background — evita que Apache (ProxyTimeout 300s) corte campañas largas. Frontend ajustado: wizard se cierra cuando el poll detecta estado='enviada' |
| UPD-153 | 22-jun | Armando | Fix campañas WA entrega 0: URLs scontent.whatsapp.net del ejemplo de plantilla tienen tokens de sesión — Meta no las puede fetchear desde sus servidores de entrega. Fix: accion=enviar sube la imagen a Media API de Meta antes del loop y usa media_id en todos los envíos (fallback a link si falla el upload) |

| UPD-154 | 22-jun | Armando | Campañas WA: métricas visuales en cards — 4 tarjetas (Enviados/Entregados/Leídos/Respuestas) con número grande + porcentaje; mini-barra tipo embudo morado(leídos)+verde(entregados sin leer) |
| UPD-155 | 22-jun | Armando | Webhook WA: manejo de tipos reaction (muestra emoji), audio, video, sticker, location, interactive — ya no aparece "[Mensaje tipo: X]" para tipos desconocidos |
| UPD-156 | 22-jun | Armando | Fix servidor bloqueado durante envío campaña: (1) eliminado sleep(60) cada 25 mensajes — Meta Cloud API permite 80/seg; (2) PHP-FPM pm.max_children 5→12 — evita que un worker de envío bloquee el resto del sistema |
| UPD-157 | 22-jun | Armando | Badge ámbar autorizaciones pendientes en sidebar "Cotizaciones": polling 60s a api/autorizaciones.php?pendientes=1; solo visible para dir_admin; color #d97706 |
| UPD-158 | 22-jun | Armando | Cobranza: orden cambiado a pendiente → parcial → pagado (los que necesitan atención primero), dentro de cada grupo por o.id DESC (más reciente arriba) |
| UPD-159 | 22-jun | Armando | cotizacion.php: seleccionar espejo → templado automático NO; cambiar a otro cristal → templado vuelve a SÍ; implementado como window.cotAutoTemplado (patrón correcto SPA para funciones llamadas desde onchange HTML) |
| UPD-160 | 22-jun | Mando | SEGURIDAD pentesting Kali: nmap, testssl (SSL A+), curl (headers), gobuster, nikto — sin hallazgos críticos post-fixes ||
| UPD-161 | 22-jun | Mando | SEGURIDAD error_log expuesto: bloque <Files "error_log"> en .htaccess raíz |
| UPD-162 | 22-jun | Mando | SEGURIDAD ETags: FileETag MTime Size en .htaccess — evita filtrar inodos (CVE-2003-1418) |
| UPD-163 | 22-jun | Mando | Fix operador.php estación terminado: botón ámbar de omisión para piezas en estatus intermedios|
| UPD-164 | 22-jun | Mando | Fix buscar_orden.php: filtro por estación ampliado para incluir estatus anteriores por omisión|
| UPD-165 | 22-jun | Mando | Permisos Compras: administracion y dueno pueden crear/editar OCs igual que dir_admin |
| UPD-166 | 22-jun | Mando | NUEVO Comprobantes en OC: tabla oc_archivos, carpeta archivos_oc/, tab Comprobantes en modalDetalle |
| UPD-167 | 22-jun | Mando | Croquis Esq. cortada: selector de 4 esquinas (Sup Izq/Der, Inf Izq/Der) — botones tipo canteo; Corte X/Y aplica a todas las seleccionadas; corte-esq en params_forma; actualizado en editor y PDF |
| UPD-168 | 22-jun | Mando | Croquis tabla elementos: reubicada a la derecha de las cotas Y (canvas se amplía 120px cuando hay elementos); nunca tapa la pieza; cada elemento muestra tipo+detalle+X+Y en tarjeta de 2 líneas; mismo cambio en PDF (página 280mm) |
| UPD-169 | 23-jun | Mando | Croquis tabla elementos: cuadro de color cambiado de fondo completo a barra delgada lateral (5-6px); fondo de tarjeta neutro #f8fafc; número integrado al texto "N. TIPO"; aplica en editor y PDF |
| UPD-170 | 23-jun | Mando | Croquis tabla elementos: texto X/Y movido a tblX+8 para no quedar tapado por la barra de color; aplica en editor y PDF |
| UPD-171 | 23-jun | Mando | Croquis modal editar elemento: inputs de Pos X/Y se salían del modal — fix con min-width:0 + box-sizing:border-box en .cq-fi; max-width:calc(100vw-32px) en .cq-modal; labels reducidos a 80px |
| UPD-172 | 23-jun | Mando | Croquis PDF imprimir_croquis.php: SVG_H 560→960, MB 90→140, fuentes 9/8→14/12, flechas/ticks escalados; tabla elementos cardH 40→28, textos 15/14/12px; @page A4 portrait; SVG width:100% height:auto; botón "Guardar como PDF" → "Imprimir" |
| UPD-173 | 23-jun | Mando | Croquis tabla elementos: ancho limitado a 90px (editor) y ~140px proporcional (PDF) para evitar exceso de espacio en blanco |
| UPD-174 | 23-jun | Mando | Croquis resaque fuera de pieza: posición de dibujo clampeada con exD=min(ex, ox+gw-rw) y rySVG=max(ey-rh, oy) — resaque nunca sale del diagrama; todas las cotas y etiquetas actualizadas a exD; aplica en editor y PDF |
| UPD-175 | 23-jun | Armando | Correo OC: BD += correo_enviado/correo_enviado_at; api/mailer.php (PHPMailer SMTP .env); ordenes_compra.php += pendientes_envio, enviar_correo, auto-send al abrir OC si dir_admin; compras.php += upload archivo en modal creación + botón "Enviar OC por correo" (morado, solo dir_admin no enviado) + badge morado sidebar polling 60s |
| UPD-176 | 23-jun | Mando | Fix chat WA nombre contacto: webhook SELECT += cliente_id para detectar NULL; si conversación ya existe y cliente_id IS NULL → busca en clientes por teléfono y actualiza; GET conversaciones: COALESCE(c.nombre, cp.nombre) con LEFT JOIN fallback por teléfono + UPDATE auto-vinculación al cargar lista |
| UPD-177 | 23-jun | Armando | SEGURIDAD api/orden_comentarios.php: fix IDOR — cancelar ahora requiere ser autor o admin (dir_admin/dueno/administracion); listar/agregar verifican existencia de cotización y que comercial solo acceda a las suyas vía asesor_id |
| UPD-178 | 23-jun | Armando | Envío cotización por WhatsApp: BD clientes += telefono_alterno VARCHAR(20); api/clientes.php incluye telefono_alterno en GET/POST/PUT+bitácora; modulos/clientes.php muestra campo alterno en panel y formulario nuevo; api/campanas.php acción enviar_cotizacion_wa (plantilla cotizacion, calcula total, crea conversación en inbox); imprimir_cotizacion.php botón verde "Enviar por WhatsApp" + modal con tel pre-llenado + opción guardar como alterno |
| UPD-179 | 23-jun | Armando | Fix etiquetas QR: lib/qrcode.min.js descargado localmente; imprimir_etiquetas.php cambia CDN jsdelivr por ruta local — CSP bloqueaba scripts externos |
| UPD-180 | 23-jun | Armando | Fix doble chat WA: campanas.php y whatsapp_webhook.php buscan conversación por RIGHT(telefono,10) en lugar de match exacto — Meta envía 521XXXXXXXXXX (13 dígitos) pero normalizarTelefono() genera 52XXXXXXXXXX (12 dígitos); limpieza BD: 2 conversaciones duplicadas eliminadas, mensajes migrados al chat original |
| UPD-181 | 23-jun | Mando | Fix imprimir_etiquetas.php: requireSession() → requirePermiso('ver_ordenes') — más robusto ante OPcache stale; consistente con demás archivos de impresión |
| UPD-182 | 23-jun | Mando | Fix botones impresión cotizacion.php: window.open() → a[target=_blank rel=noopener] para evitar bloqueo silencioso de popup blocker en scripts inyectados por SPA; CSS .btn += display:inline-block + text-decoration:none |
| UPD-183 | 23-jun | Mando | Croquis nuevo elemento BI (Bisagra): chip teal en panel, tipo propio con forma U+círculos a 45°/135° (herraje cancel baño CT29), auto-rota al borde más cercano, dimensiones editables (default 58×37.5mm); RS queda como resaque genérico sin preset; aplica en editor y PDF |
| UPD-184 | 23-jun | Mando | Fix portal clientes móvil "No se encontró la orden": api/orden.php acepta sesión portal (portal_cliente_id) además de sesión interna — UPD-123 había roto el portal al agregar requireSessionApi(); verifica que la orden pertenezca al cliente antes de mostrarla |
| UPD-185 | 23-jun | Armando | BD ordenes += wa_lista_enviado TINYINT(1) DEFAULT 0; api/wa_helper.php (enviarMensajeWA extraída a helper compartido); actualizar_estatus.php envía plantilla WA 'orden_lista' automáticamente al marcar última pieza terminada — verifica que ninguna pieza quede fuera de terminado/entregado, marca flag para no reintentar |
| UPD-186 | 23-jun | Mando | Fix editor croquis (modulos/croquis.php): MB dinámico max(90, EL_BASE+N×14+20) — todas las cotas X de elementos caben sin importar cuántos haya; SVG_H crece con MB; números de elemento clampeados dentro del outline (TP/TA: min(ex+r, ox+gw-6), RS/BI: dentro de la forma); ML=86, gap ejYX 14→20; bug _layout()→_getOrigin() corregido |
| UPD-187 | 23-jun | Mando | Fix PDF croquis (imprimir_croquis.php): MB dinámico (cOff+14+12 + N_elem×rowStep + 24); ML=110; gap ejYX 14→24 — elimina overlap entre rect "alto mm" y rect "Eje Y"; label bisagra "BI posición"→"BI"; rect fondo BI width 52→18px |
| UPD-188 | 23-jun | Mando | PDF croquis en blanco y negro para impresión: vidrio fill #f0f0f0 borde #1a1a1a; canteado línea guiones negra (stroke-dasharray:6,3); cotas #222222; ejes X/Y en #222222/#555555; TP/TA bordes #111111/#333333; RS/BI fill #e8e8e8 borde #333333; tabla elementos fondos #eeeeee barras #333333; colores HTML wrapper (botón, cabecera) sin cambio |
| UPD-189 | 23-jun | Mando | PDF croquis selector de escala: barra fija top-right con select 40%-200% (default 100%) + botón Imprimir; JS escalarDiagrama() modifica width/height del SVG directamente (viewBox mantiene proporciones); svg-wrap overflow:auto para scroll en pantalla; ctrl-bar oculta en @media print |
| UPD-190 | 23-jun | Mando | Fix finanzas VoBo pago excedente: api/finanzas.php calcula total_real y saldo_pendiente ANTES de insertar; si excedente >$5 registra solo monto necesario para saldar y deposita diferencia en clientes_saldo_favor tipo 'deposito' con referencia al folio; si excedente ≤$5 se absorbe sin depósito; frontend muestra alerta con monto abonado a saldo a favor |
| UPD-191 | 23-jun | Mando | Fix precio cotización: recalcular() usaba cris.precio_m2 (catálogo actual) en lugar del precio bloqueado al guardar. Fix: hidden input p_pm2_i por partida inicializado con precio_m2_usado guardado; cotCristalChange() actualiza el hidden al precio del catálogo solo si el usuario cambia el cristal; recalcular() y armarPayload() leen del hidden; api/cotizaciones.php PUT respeta precio_m2_usado del frontend si es >0 (solo usa catálogo para partidas nuevas sin precio previo) |
| UPD-192 | 23-jun | Armando | Fix WA orden_lista: (1) wa_lista_enviado=1 solo si Meta responde HTTP 200 con message_id — antes se marcaba incluso en fallo; (2) mensaje guardado en whatsapp_mensajes (inbox) al enviar exitosamente — enviado_por='sistema', contenido '[Plantilla orden_lista] Orden X lista — N piezas'; (3) error_log cuando falla el envío; (4) cliente_id agregado al SELECT de la consulta de orden |
| UPD-193 | 23-jun | Armando | Notas de voz WA reproducibles: BD whatsapp_mensajes.tipo ENUM += 'audio'; webhook descarga archivo de Meta y guarda en archivos_campanas/wa_media/ (ogg/mp3/m4a/aac/amr, límite 16MB, anti-SSRF); inbox campanas.php renderiza <audio controls> cuando tipo=audio y hay ruta local, fallback a texto si falta el archivo |
| UPD-194 | 23-jun | Armando | Fix WA orden_lista sin teléfono: (1) INSERT INTO ordenes al convertir cotización ahora incluye cliente_id; (2) query en actualizar_estatus.php usa COALESCE(o.cliente_id, c.cliente_id) + LEFT JOIN doble a clientes para fallback vía cotización; (3) UPDATE masivo: 123 órdenes existentes sin cliente_id rellenas desde cotizaciones |
| UPD-195 | 23-jun | Armando | Fix botones admin cotizacion.php: corrModal/archModal/catModal/.motivo-overlay se agregaban a document.body y sobrevivían navegaciones SPA — si el usuario navegaba sin cerrar, el backdrop position:fixed;inset:0 bloqueaba todos los clicks silenciosamente (Rechazar funciona porque z-index:2000 > 1400). Fix: init() remueve esos elementos al inicio de cada carga |
| UPD-196 | 23-jun | Armando | Fix complementario botones admin: limpieza de modales movida a cargarModulo() en dashboard.php — aplica en TODA navegación SPA, no solo al recargar cotizacion. Elimina corrModal/archModal/catModal/modalRechazoCalidad y .motivo-overlay antes de cargar cualquier módulo nuevo |
| UPD-197 | 24-jun | Armando | Fix precio cotizador en tiempo real: recalcular() ahora usa catálogo como fallback cuando p_pm2_i=0 (partidas nuevas o con precio_m2_usado=0 en BD); renderPartidas inicializa el hidden con precio del catálogo si cristal_id existe y precio_m2_usado=0 — preserva el precio bloqueado de UPD-191 cuando precio_m2_usado>0 |
| UPD-198 | 24-jun | Mando | Fix correcciones.php: propagar cambios de cpb, resaques, tp, ta, requiere_templado y detalles a tabla piezas al aplicar corrección — antes solo se propagaban cambios de dimensiones (ancho/alto/m2) |
| UPD-199 | 24-jun | Armando | Auditoría cotizaciones CRÍTICO: límite registros 200→1000 en fetch JS (cotizaciones.php) y en API (cotizaciones.php) — con 222 cotizaciones en BD, 22 registros eran invisibles y los contadores de tabs eran incorrectos |
| UPD-200 | 24-jun | Armando | Auditoría cotizaciones ALTOS: (1) location.reload()→irA() en confirmarRechazo — evita romper SPA al rechazar por calidad; (2) listener click autocomplete cliente ya no se acumula entre navegaciones — usa window._cotAutocompleteHandler con removeEventListener previo; (3) abrirRechazo/cerrarRechazo/confirmarRechazo movidas dentro del IIFE y expuestas via window.* — eliminado segundo script suelto duplicado |
| UPD-201 | 24-jun | Armando | Auditoría cotizaciones MEDIOS: (1) toggleFactura() eliminada — buscaba fFacturaRfc inexistente, nunca se llamaba; (2) 5 funciones imprimir* (imprimirOrden/Cotizacion/Remision/Etiquetas/Salida) eliminadas — dead code desde UPD-182 cuando los botones cambiaron a <a> tags; (3) query lista cotizaciones optimizada — reemplazadas 2 subqueries correlacionadas por LEFT JOIN con GROUP BY (cp_sums), reduciendo de N×2 queries a 1 JOIN |
| UPD-202 | 24-jun | Armando | Reporte Dirección: prom_dias ahora excluye sábados, domingos y festivos (tabla festivos) — query adicional trae fechas crudas por orden; diasHabiles() itera día a día contando solo Lun-Vie no festivos; mismo cálculo para resumen global, filas mensuales y fila TOTAL |
| UPD-203 | 24-jun | Mando | Fix PDF croquis cotas: bordHx/bordVy con margen de 4px desde el contorno del vidrio (antes 1px) — tick y número ya no se empalman con la línea del borde de la pieza; tabla ELEMENTOS en editor muestra "Izq/Der Nmm  Inf/Sup Nmm" en lugar de coordenadas X/Y |
| UPD-204 | 24-jun | Mando | Usuario 'desarrollo' (rol desarrollo): acceso total + permiso exclusivo 'ver_wip' para módulos WIP; ENUM rol usuarios += desarrollo; $esDesarrollo en dashboard.php; nadie más ve features WIP |
| UPD-205 | 24-jun | Mando | Rutas de Entrega → WIP: sidebar solo visible para desarrollo con badge ámbar WIP; fix permisos hardcodeados en logistica_rutas.php y api/rutas.php — ambos incluyen 'desarrollo' en lista de roles permitidos |
| UPD-206 | 25-jun | Mando | GPS tracking ProTrack365: constantes PROTRACK_ACCOUNT/PASSWORD/IMEI_GRIS/IMEI_BLANCA en config.php + .env; api/gps_proxy.php con auth MD5, cache de token en sesión 110min, retry automático — pendiente activación Open API por proveedor; plan alternativo: Traccar self-hosted cuando llegue dispositivo GPS |
| UPD-207 | 25-jun | Mando | Rutas: mapa con pins de entrega visible automáticamente al cargar — dibujarPines() geocodifica cada dirección y pinta pin SVG numerado (amarillo=pendiente, verde=entregado, rojo=no_entregado); pin azul en origen planta; click en pin abre popup con folio/cliente/dirección/referencias/peso; botón "Ruta óptima" traza línea sobre los mismos pins; pendiente: GOOGLE_MAPS_KEY en .env (actualmente vacía — autocomplete de direcciones no funciona) |
| UPD-208 | 25-jun | Mando | Fix resumen material remisión (`imprimir_cotizacion.php?remision=1`): agrupación cambiada de `cristal_etiqueta` a `cristal_nombre` — `cristal_etiqueta` tenía el mismo valor para plantillas y cristal normal, fusionándolos en una sola línea. Ahora cada tipo aparece separado |
| UPD-209 | 25-jun | Mando | Fix campañas WA: `$esCampanas` en `api/campanas.php` agregó `administracion` — Lina no podía ver conversaciones ni inbox (API devolvía 403) |
| UPD-210 | 25-jun | Mando | Croquis nueva forma `esq` (Esquinero de vidrio): 3 subtipos — Recto (triángulo 90° con selector de 4 esquinas), Isósceles (apex arriba, base plana), Curvo (apex arriba, base en arco); selector con íconos SVG en el panel; aplica en editor y PDF |
| UPD-211 | 25-jun | Mando | Croquis bisagra `bi_ref`: nuevo campo inicio/centro/final que indica desde qué punto de la bisagra se mide la posición Y; selector de 3 botones en modal de doble clic; afecta dibujo SVG y tabla de elementos en editor y PDF |
| UPD-212 | 25-jun | Armando | Quick wins UX dashboard: (1) todos los emojis del sidebar reemplazados por SVG Lucide inline (20 íconos); campana y hamburger también con SVG; (2) focus-visible ring azul en sidebar-link, hamburger y notif-btn (accesibilidad); (3) etiquetas de rol legibles: dir_admin→Director Admin, dueno→Propietario, etc. |
| UPD-213 | 25-jun | Armando | Mejoras UX completas auditoría: panel notificaciones tema claro; cursor:pointer sidebar; scrollbar thin; logout touch 44px + confirm; reloj móvil HH:MM visible; error SPA con SVG; h2 headings en resumen; stat lbl 11px; títulos sin emoji; prog-bar con title tooltip |
| UPD-214 | 25-jun | Armando | Resumen órdenes activas: ORDER BY cambiado a grupo (Sin Iniciar→En proceso→Lista) usando CASE sobre agregados SQL + o.id DESC para folio desc dentro de cada grupo |
| UPD-215 | 25-jun | Mando | Permisos desarrollo: rol 'desarrollo' recibe acceso total equivalente a dir_admin en todas las APIs y módulos — correcciones, admin_ordenes, croquis, archivos_ordenes, servicios_catalogo, comunicados, autorizaciones, cotizaciones, cristales, clientes, saldo_favor, campanas, omisiones, finanzas, prioridad, portal_clientes, ordenes_compra, reproceso, orden_comentarios, notificaciones; módulos frontend compras/cotizacion/cotizaciones |
| UPD-216 | 25-jun | Mando | NUEVO módulo Facturación WIP: app/modulos/facturacion.php — solo visible para rol desarrollo en sección Finanzas; lista de facturas, formulario Nueva/Editar con campos CFDI (RFC, uso CFDI, régimen fiscal, forma/método de pago), conceptos con importes, IVA 16%, cambio de estatus (pendiente/timbrada/cancelada); datos en localStorage (sin BD real) |
| UPD-217 | 25-jun | Mando | Facturación WIP ampliado: CP fiscal del receptor; conceptos += Clave SAT (dropdown con claves vidrio) + Unidad SAT (M2/PZA/MTR/H87); modal Estatus: UUID/folio fiscal al seleccionar Timbrada + motivo cancelación SAT 01-04 al seleccionar Cancelada; banner rojo advierte que claves SAT son de ejemplo; cliente fijo PRUEBA DE PORTAL (CTN-259); fix permisos croquis.php frontend faltaba 'desarrollo' en $puede_editar |
| UPD-218 | 25-jun | Armando | Portal clientes: nueva sección Cotizaciones — tabla desktop + cards mobile; folio, fecha, proyecto, asesor, total, estatus traducido (Pendiente/En producción/Entregada/Cancelada/No aprobada); query por cliente_id o cliente_nombre; sin link a detalle (no existe portal/cotizacion.php) |
**Próximo UPD disponible: UPD-219**
