# APEX GLASS — MEMORIA ÚNICA DEL PROYECTO
# Sistema de Rastreo de Producción (Templadora Noreste, S.A. de C.V.)
# Última actualización: 17 julio 2026 | Próximo UPD disponible: UPD-353

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
| BAJA | Armando | UX: Badge órdenes vencidas global — actualmente solo se actualiza desde módulo Resumen | Pendiente |
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
| ALTA | Armando | **Depósito a Cuenta / Saldo a Favor — rediseño en curso (10-jul-2026), NADA IMPLEMENTADO TODAVÍA.** Problema raíz: hoy se crea una orden con m² inventados solo para poder registrar un abono/depósito del cliente cuando aún no sabe qué va a comprar — esto duplica el ingreso reportado (una vez en la orden placeholder, otra vez cuando el cliente devenga el saldo en una orden real). Hallazgo clave de la investigación: **el mecanismo correcto YA EXISTE** (`clientes_saldo_favor` + `api/saldo_favor.php?accion=deposito` + tab "Saldo a Favor" en `finanzas_cobranza.php`) pero no se está usando. Recomendaciones dadas (sin confirmar/implementar): badge junto al folio en vez de nomenclatura de folio nueva; botón "Registrar Depósito" en la ficha del cliente; columna informativa "Pagado con Saldo a Favor" en Ventas y Cobranza (no afecta Acumulado en Pedidos); el depósito debería aparecer como fila de "cobranza" separada el día que se registra, sin sumar a "ventas" hasta que se devengue en una orden real (pendiente que Armando lo confirme). También se encontraron 2 bugs en el mecanismo existente sin arreglar (falta guard anti-doble-clic en `saldo_favor.php`, XSS en `sfSelCliente` mismo patrón que UPD-275) y un blast radius completo de 7+ queries en `api/reporte_direccion.php`/`api/inventario.php`/`portal/tablero.php` que habría que filtrar si se limpian órdenes placeholder históricas (falta que Armando pase los folios, no se detectan por texto). Detalle completo de la investigación, decisiones abiertas y citas textuales de Armando en la memoria de Claude (`project_deposito_cuenta_saldo_favor.md`) | Pendiente — diseño en discusión |
| ALTA | Armando | QR de salida por chofer (UPD-319) — verificado 13-jul-2026: las 4 plantillas Meta (`chofer_en_ruta_cliente`/`siguiente_entrega_cliente`/`chofer_en_ruta_asesor`/`siguiente_entrega_asesor`) ya están **APPROVED** (confirmado consultando la Graph API directo); `usuarios.telefono` de Bethy (8134000145) y Cynthia (8140051992) ya está cargado; nombres de choferes (`Juan Roberto García`, `Víctor Bautista`) ya son reales, no genéricos. Flujo completo funcional. Solo falta: prueba visual con un chofer real escaneando el QR físico | HECHO (config) — falta prueba física |
| ALTA | Mando | **GPS ProTrack365 en Logística Rutas (ver UPD-327/328, 338/339)** — HECHO: frontend conectado (línea única al siguiente destino, GPS en vivo), cron `scripts/gps_tracker.php` corriendo cada minuto guardando histórico en `gps_posiciones` y detectando llegada/movimiento. Sigue pendiente pedir al distribuidor la Open API oficial para no depender a largo plazo del fallback web no documentado (`permission denied` en la oficial) | Mayormente HECHO — falta Open API oficial del distribuidor |
| MEDIA | Mando | Radio de "llegada GPS" (250m, ver UPD-338/339) — con la primera prueba real el camión quedó a 268m sin disparar. Evaluar subir a 300-350m con más pruebas | Pendiente |
| MEDIA | Mando | Trazabilidad de rutas (UPD-339/340) — falta prueba con un chofer real completando el flujo físico completo (escaneo QR salida → manejar → llegar) para confirmar que las 4 columnas de la tabla en Productividad se llenan solas | Pendiente |
| MEDIA | Armando | Videos de marketing con Remotion (UPD-351/352) — herramienta instalada y funcional en `herramientas/video-marketing/` (fuera del webroot). 3 videos de muestra hechos: promo genérico de marca, demo del Portal de Clientes (escritorio) y demo del Portal de Clientes (vertical, formato celular con marco de teléfono). Ninguno conectado todavía a una campaña real. Falta: (1) que Armando confirme si le gustan y para qué campaña específica los quiere usar, (2) revisar si `app/modulos/campanas.php` necesita soporte para plantillas Meta con header tipo VIDEO (hoy el wizard solo maneja `header_image_url` de imagen) | Pendiente — esperando feedback de Armando |

---

## 13. HISTORIAL DE ACTUALIZACIONES

REGLA: Cada cambio se agrega aquí. NUNCA se elimina. Código UPD secuencial e irrepetible.
Próximo UPD disponible: **UPD-301**

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

## 14. PROTOCOLO PARA CADA SESIÓN

Al terminar cualquier sesión con cambios:
1. Subir archivos modificados a Drive (`ARCHIVOS SERVIDOR/`)
2. Registrar el cambio con próximo UPD en este archivo
3. Las tareas completadas se marcan HECHO — NUNCA se borran

### Bloque actual: UPD-251 en adelante

| Código | Fecha | Resp. | Descripción |
|---|---|---|---|
|

| UPD-251 | 29-jun | Mando | Fix Eliminar en timbradas modo test: causa raíz era `.fac-table-wrap { overflow:hidden }` cortando el dropdown — el HTML y lógica `f.modo==='test'` eran correctos; dropdown cambiado a `position:fixed` con coordenadas via `getBoundingClientRect()` en `menuToggle()`; fix IDOR faltante UPD-249: `AND creado_por=?` en DELETE de `api/facturapi.php`; confirm() diferenciado para timbradas vs borradores |
| UPD-252 | 29-jun | Mando | Lector CSF con OCR: instalar tesseract + tesseract-langpack-spa en VPS; api/extraer_constancia.php detecta automáticamente si el PDF tiene capa de texto (pdftotext) o es imagen (Print to PDF/escaneado) y usa pdftoppm+tesseract como fallback; OCR pages 1-2; regex nombre físico acepta "Primer/Segundo Apellido" (formato OCR) además de "Apellido Paterno/Materno" (PDF nativo SAT) |
| UPD-253 | 29-jun | Mando | Facturación WIP fixes: (1) OCR flujo corregido — PDF.js solo lee PDFs con capa de texto; si texto<100 chars hace fallback a upload servidor con FormData → api/extraer_constancia.php → tesseract; DPI 200→150, solo página 1, formato JPEG, --psm 6 para reducir tiempo OCR; exec() habilitado quitándolo de disable_functions en /etc/opt/remi/php84/php.d/zz-php.ini (pool override no es suficiente para reducir disable_functions); (2) unidad predeterminada en conceptos cambiada de 'M2' a vacío en los 4 puntos donde se hardcodeaba |
| UPD-254 | 29-jun-2026 | Armando | Bloqueo ventana 24h inbox WA: conversaciones >24h sin actividad muestran banner amarillo bloqueando el input; botón "Reactivar conversación" envía template `atencion_apex` automáticamente ({{1}}=nombre cliente CRM, {{2}}=asesor logueado). Nuevo endpoint `api/campanas.php?accion=template_inbox` con check `$puedeEnviar`. |
| UPD-255 | 29-jun-2026 | Mando | Facturación: receptor conectado al CRM. BD: 3 columnas fiscales en clientes (rfc, cp_fiscal, regimen_fiscal). Módulo Clientes: badge RFC en tabla, widget CSF inline en panel para guardar datos fiscales desde constancia SAT. Facturación: buscador CRM con autocomplete pre-llena receptor; aviso si faltan datos fiscales. Fix OCR: regex 626 ahora detecta "Régimen Simplificado de Confianza" además de "Resico". Archivos: api/clientes.php, modulos/clientes.php, api/facturapi.php, modulos/facturacion.php, api/extraer_constancia.php |
| UPD-256 | 29-jun-2026 | Mando | Impresión cotización: nombre "Templadora Noreste S.A. de C.V." agregado en sección Datos Bancarios. Botón borrar archivos (dir_admin/desarrollo) en modal de archivos adjuntos de cotización. Modal archivos más grande (560→780px). Archivos: app/imprimir_cotizacion.php, app/modulos/cotizacion.php |
| UPD-257 | 29-jun-2026 | Mando | Sistema de reportes bugs/mejoras: tabla `reportes` con campo `elemento` (JSON), botón 🚩 en topbar abre modal con picker de elemento (crosshair + highlight), captura módulo/ruta CSS/texto; módulo `modulos/reportes.php` para desarrollo/dir_admin con filtros y "Marcar completado". Archivos: api/reportes.php, app/dashboard.php, app/modulos/reportes.php |
| UPD-258 | 29-jun-2026 | Mando | Vista operador para desarrollo: agregado a `$rolesOperador` en `operador.php`; botón "Vista Operador" en sidebar (solo desarrollo) abre nueva pestaña. Archivos: app/operador.php, app/dashboard.php |
| UPD-259 | 29-jun-2026 | Armando | Fix SSL AdminBolt: cert `*.myboltip.com` en `/etc/ssl/bolt/` expiró 17-jun; reemplazado con Let's Encrypt de panel.apex.glass (`/home/apexglass2025/panel.apex.glass/certs/`); recargado bolt-nginx. Fix XSS stored en `app/modulos/reportes.php`: agregada función `esc()` y aplicada a el.modulo, el.ruta, el.texto, r.creado_por, r.completado_por, rolLabel. Cambio teléfono: 81 2315 3005 → 81 1180 5078 en index.html, enviar-contacto.php, imprimir_cotizacion.php. |
| UPD-260 | 29-jun-2026 | Armando | WIP Portal WA por cliente: botón "📱 Enviar por WA" en panel de cliente (junto a contraseña portal); endpoint `api/portal_clientes.php?accion=enviar_acceso_wa` genera pass si no tiene y envía template `acceso_portal`. PENDIENTE: Meta rechaza como UTILITY/MARKETING — debe ser plantilla de Autenticación (Authentication). Armando debe crear en Meta BM y confirmar nombre exacto. |
| UPD-261 | 30-jun-2026 | Mando | Auditoría arquitectura completa (Fixes 1-13): SQL injection, CORS wildcard, auth centralizado, IIFE namespaces, exception handler, jsonResponse, transacciones PDO, utils.js compartido, icono() a helpers/icons.php (fix bug ?> en comentario), historiales a docs/ |
| UPD-262 | 30-jun-2026 | Mando | Inbox campañas WA: marcar como no leído (menú ⋮), multilinea Shift+Enter, foco automático en textarea, reply con cita (botón ↩ en burbuja + `context.message_id` a Meta). BD: reply_to_wa_id, reply_preview en whatsapp_mensajes |
| UPD-263 | 30-jun-2026 | Mando | Performance: session_write_close() en permisos.php; N+1 fix en ordenes.php y cotizaciones.php; polling 30s→90s/60s en estaciones/resumen/SmartTV; productividad.php de 25 queries a 1 por franja con metricasFranjaAll() |
| UPD-264 | 30-jun-2026 | Mando | Fix PDFs inbound WA: webhook descarga y guarda documentos recibidos (wa_doc_*.ext en archivos_campanas/wa_media/); frontend muestra link clicable en lugar de texto plano |
| UPD-265 | 30-jun-2026 | Armando | Campañas WA segmentadas mensuales: 4 segmentos por cliente (frecuentes ≥3 órdenes/mes, compradores del mes 1-2 órdenes/mes, cotizó sin comprar, sin cotizar en el mes), prioridad 1>2>3>4 sin traslape, cubre 100% de clientes activos con teléfono. `{{1}}`=nombre corto desde `clientes.contacto` (primeras 2 palabras, Title Case, overrides manuales por cliente_id en código); `{{2}}` solo en "cotizó sin comprar"=asesora de `cotizaciones.asesor_nombre` más reciente, normalizado a Bethy/Cynthia (default Bethy si quedó a nombre de un no-comercial). Primera corrida junio 2026: campañas #18-21 (16/77/51/90 destinatarios) en estado borrador. Nuevo botón "Enviar campaña" en detalle de campaña (antes no existía forma de enviar un borrador ya creado desde la lista). Nueva columna "Se enviará como" en detalle para distinguir razón social vs nombre real del mensaje. Script reusable `scripts/generar_campanas_segmentadas.php` (protegido con .htaccess) + trigger mensual día 26 para repetir el proceso con los templates Meta de cada mes. Fix bug latente: `ce.cliente_id` faltaba en el SELECT de `accion=enviar`, por lo que `{{nombre_asesor}}`/`{{num_ordenes}}`/`{{num_cotizaciones}}`/`{{monto_cotizado}}` nunca funcionaban para clientes (solo para prospectos). Fix XSS en mensajes de ubicación del inbox (lat/lng sin escapar). Archivos: api/campanas.php, app/modulos/campanas.php, scripts/generar_campanas_segmentadas.php |
| UPD-266 | 30-jun-2026 | Mando | Campañas WA inbox: botón "+" con menú de burbujas (📎 Archivo, 😊 Emoji, 📍 Ubicación) reemplaza 3 botones separados; emoji picker aparece arriba-derecha del textarea (estilo WhatsApp); ubicación manda preset hardcodeado de Templadora Noreste (25.6930336, -100.4807059, C. de la Industria 214 Santa Catarina); nuevo endpoint `api/campanas.php?accion=enviar_ubicacion`; render ubicaciones outbound soporta `lat,lng|nombre`. Archivos: app/modulos/campanas.php, api/campanas.php |
| UPD-267 | 01-jul-2026 | Armando | Fix Reporte Dirección — "Pipeline vigente"/"Pendientes" (cots_resumen) estaban filtrados por `c.created_at BETWEEN` del período seleccionado, por lo que cotizaciones abiertas (estatus='cotizacion') creadas en meses anteriores desaparecían del reporte al cambiar de mes aunque siguieran vivas sin decisión del cliente. Detectado con caso real: 150 cotizaciones abiertas ($1,157,007.75) todas creadas en junio, invisibles al ver "mes_actual" en julio. Fix: quitado el filtro de fecha de ese query — ahora es una foto del estado actual (todas las abiertas, sin importar cuándo se crearon), independiente del período. "Tasa de conversión" (query `conversion`) sí se dejó period-based intencionalmente porque mide desempeño de cotizaciones generadas en el período. Archivos: api/reporte_direccion.php, app/modulos/reporte_direccion.php |
| UPD-268 | 01-jul-2026 | Mando | Auditoría cohesión visual: tokens CSS unificados en dashboard.php + 6 módulos (colores/radios/bordes consistentes). Fix acceso: 4 módulos (admin_ordenes, admin_comunicados, finanzas_vobo, finanzas_cobranza) tenían chequeo de rol viejo que bloqueaba a `desarrollo` aunque sus APIs ya lo permitían. |
| UPD-269 | 01-jul-2026 | Armando | Reporte Dirección — Pipeline vigente (card de Cotizaciones) ahora muestra "mes anterior / mes actual" en vez de un solo total, ej. $1,157,008 / $5,345, para poder comparar cómo evoluciona el pipeline sin perder de vista lo que sigue vivo del mes pasado. Backend: 2 columnas nuevas (pipeline_mes_anterior, pipeline_mes_actual) en la query cots_resumen de UPD-267, con SUM condicional sobre calendario fijo (mes calendario pasado completo / mes calendario actual en adelante) — independiente del selector de período del reporte. El total histórico de todas las abiertas (cot.total_cotizado) se conservó como dato secundario entre paréntesis en el card "Pendientes". Archivos: api/reporte_direccion.php, app/modulos/reporte_direccion.php |
| UPD-270 | 03-jul-2026 | Armando | Reporte Dirección — nueva pestaña "Ventas y Cobranza" junto a "Resumen" (navegación por pestañas agregada al módulo). Listado de órdenes (solo `activa`/`entregada`, excluye pendiente_vobo y cancelada) con columnas #Orden, Asesor, Cliente, Anticipo, Restante, Total del Pedido y Acumulado en Pedidos del Día/Semana/Mes (suma corrida cronológica). Toggle Día/Semana/Mes + flechas `< >` para navegar a periodos pasados + botón "Hoy". Anticipo/Restante son una FOTO HISTÓRICA al final del período visto (`SUM(cotizacion_pagos.monto) WHERE fecha_pago <= fin_del_período`), no el saldo vivo de hoy — confirmado explícitamente por Armando: "es una radiografía del día, no un reporte de cobranza". Nuevo endpoint `api/reporte_direccion.php?accion=ventas_cobranza&gran=dia\|semana\|mes&fecha=YYYY-MM-DD`, independiente del selector de período de la pestaña Resumen. Probado end-to-end contra la BD real (día/semana/mes + caso vacío) simulando sesión PHP con `php84` CLI, sin acceso a navegador (Playwright MCP no disponible en esta sesión) — pendiente una verificación visual en el navegador real cuando Armando pueda. Archivos: api/reporte_direccion.php, app/modulos/reporte_direccion.php |
| UPD-271 | 03-jul-2026 | Armando | Campañas WA: soporte a plantillas con botón WhatsApp Flow (encuestas interactivas dentro del chat, sin salir a página externa). Causa raíz del fallo en campañas "Prueba_Encuesta"/"Prueba Encuesta 2": Meta rechazaba el envío (error 131009 "Components sub_type invalid") porque la plantilla `encuesta_clientes` tiene un botón FLOW y el código nunca mandaba el componente `button`/`sub_type:flow` que Meta exige. Fix: `api/campanas.php` detecta automáticamente (1 consulta a Meta por campaña, no por destinatario) si la plantilla tiene botón FLOW y agrega el componente con `flow_token` único = id de `campana_envios` (no requirió columna nueva). `api/whatsapp_webhook.php` ahora reconoce `interactive.type=nfm_reply`, guarda las respuestas de la encuesta de forma legible en el inbox, y usa el `flow_token` para vincular la respuesta al envío exacto (más preciso que teléfono+fecha cuando hay varias campañas activas al mismo tiempo). Probado con envío real de prueba al número 528116286089 (campaña 22, envio id 3634) — Meta respondió 200 "accepted". Pendiente: confirmar visualmente que el Flow de 3 preguntas ya configurado en Meta (flow_id 718026311405450) se ve bien al abrir el botón — eso no depende del código. Archivos: api/campanas.php, api/whatsapp_webhook.php |
| UPD-272 | 04-jul-2026 | Armando | Config: plugin `superpowers` deshabilitado para este proyecto (`.claude/settings.json`, `"superpowers@claude-plugins-official": false`, gana sobre el `true` global de `~/.claude/settings.json` por precedencia project>user). Causa: el plugin fuerza brainstorming/plan/TDD/subagent-driven-development casi sin excepción (SessionStart hook con instrucción "no negociable") y el módulo de Maquila (UPD reciente) gastó tanto en tokens/tiempo por ese flujo como el resto del proyecto completo. Efecto: Claude vuelve a trabajar directo en cambios y mejoras puntuales de este repo; sigue disponible para Mando en otros repos porque el `true` global no se tocó. Requiere reiniciar sesión de Claude Code (`/hooks` o nueva sesión) para que tome efecto — no aplica en caliente a la sesión ya abierta. Reactivar cambiando el valor a `true` si se necesita para una feature grande con planning formal. Archivos: .claude/settings.json |
| UPD-273 | 04-jul-2026 | Armando | Cierre módulo Maquila — Tasks 17/18 del plan `docs/superpowers/plans/2026-07-04-maquila.md`, hechos directo (sin superpowers) tras suspender la sesión anterior que traía Tasks 1-16. Task 17: `app/imprimir_orden.php` rama `$esMaquila` — nota: el archivo trabaja por PARTIDA (`cotizaciones_partidas`/`cotizaciones_maquila_partidas`), no por pieza como asumía el plan; se adaptó consultando `cotizaciones_maquila_partidas` + join `maquila_tipos_vidrio` y columna Servicios (Corte/Canteado/Taladro/Templado) igual que Task 16, sin columna Estatus porque tampoco existe en la rama suministro de este archivo. Task 18: `app/imprimir_etiquetas.php` — badge "MAQUILA" (`.badge-maquila`, negro/blanco a juego con badge-cpb/fm/srv existentes, no el naranja que sugería el plan, para imprimir bien en impresoras térmicas B&N) cuando `$orden['tipo']==='maquila'`. Ambos verificados con datos de prueba reales insertados con `INSERT`/`DELETE` reversible (cotización+partida+orden marcada `es_prueba=1`) ya que no existía ninguna cotización maquila en producción; limpiados al terminar, confirmado 0 filas. Pendiente: Task 18 Step 5 (escaneo físico de una etiqueta maquila real con cámara/scanner) solo lo puede hacer Armando. Archivos: app/imprimir_orden.php, app/imprimir_etiquetas.php |
| UPD-274 | 04-jul-2026 | Armando | Rediseño UI/UX de `app/modulos/maquila.php` (las 3 vistas: lista/nueva/detalle) para que coincida con el sistema visual de `cotizaciones.php`/`cotizacion.php` — antes tenía su propio layout ad-hoc (tabla plana sin tabs/búsqueda/paginación, badges con otros colores, inputs sin estilo ni labels, cliente por ID numérico en vez de buscador). Cambios: (1) Lista — tabs Cotizaciones/Órdenes/Canceladas + buscador + paginación (25/página) + hover en filas + badges con los mismos colores semánticos que `cotizaciones.php` (badge-cot azul/badge-orden verde/badge-canc gris), igual que UPD-267 en filosofía de reporte pero aquí es UI de módulo. (2) Nueva — layout `.card`/`.form-grid`/`.field` calcado de `cotizacion.php`, y se agregó buscador de cliente con autocomplete contra `api/clientes.php` (mismo patrón que `ModCotizacion._buscarCliente`) en vez del input numérico de cliente_id que existía; partidas rediseñadas como tarjetas con campos etiquetados en vez de inputs sueltos con solo placeholder. (3) Detalle — header page-title/page-sub, card de info (cliente/estatus/total), mismos badges y botones btn-primary/btn-danger/btn-ghost que el resto del sistema. Sin cambios de backend/API — solo vista. Verificado sin errores PHP renderizando las 3 vistas vía `php84` CLI con sesión simulada (mismo método de UPD-270/273); **verificación visual real en navegador PENDIENTE** — Playwright MCP no está disponible en esta sesión y no se usaron credenciales de Armando para loguear un browser real; pedir a Armando que abra `?m=maquila` y confirme que se ve bien. Archivos: app/modulos/maquila.php |
| UPD-275 | 04-jul-2026 | Armando | Fix XSS en `app/modulos/maquila.php` (buscador de cliente de UPD-274), detectado por el hook de revisión de seguridad automática al terminar la tarea anterior. `escJs()` solo escapaba `\` y `'`, pero el string iba embebido dentro de un atributo `onclick="..."` con comillas dobles — un cliente con `"` en su razón social podía romper el atributo e inyectar HTML/JS. Fix: se eliminó la construcción de HTML por concatenación de strings para ese listado; ahora se crean nodos DOM (`createElement`/`textContent`) y el click se ata con `addEventListener` en closure, sin pasar nunca datos del cliente por un contexto de parseo HTML/JS. **Nota importante:** el mismo patrón (idéntico, `escJs` + onclick concatenado) existe también en `app/modulos/cotizacion.php` (`ModCotizacion._buscarCliente`, de donde se copió el patrón como referencia) — ese archivo NO se tocó en esta sesión y queda como vulnerabilidad latente pendiente, agregado a Pendientes Activos (sección 12). Archivos: app/modulos/maquila.php |
| UPD-276 | 04-jul-2026 | Armando | Ajuste espesores maquila: `ESPESORES` en `app/modulos/maquila.php` (selector de "Nueva Maquila") reducido de `[3,4,5,6,8,10,12,15,19]` a `[5,6,9,12]`. Nota: solo hay precios cargados en BD para 6mm en las 4 tarifas (corte/canteado/taladro/horno) — cotizar en 5/9/12mm dará error "Sin precio de X para espesor Ymm" hasta que se carguen esos precios en Administración → Precios Maquila. Sin cambios de backend/esquema. Archivos: app/modulos/maquila.php |
| UPD-277 | 04-jul-2026 | Armando | `app/modulos/maquila_precios.php`: (1) botón "Editar" agregado por fila de precio — precarga servicio/espesor/precio en el formulario para actualizar (el backend ya hacía upsert por `(servicio, espesor_mm)`, solo faltaba la UI para precargar). (2) Rediseño UI/UX completo para que coincida con el sistema visual de `cotizaciones.php`/`maquila.php` (page-title/card/form-grid/field/mq-table en vez del `.main`/`.table-wrap` original). De paso se corrigieron huecos de UX detectados al tocar el archivo: labels de servicio legibles (Corte/Canteado/Taladro/Horno (Templado)) en vez del valor crudo de BD, indicador "Editando: X Ymm" + botón "Cancelar edición" visible (antes reusaba el form de crear sin avisar que estabas editando), validación inline de espesor/precio > 0 en vez de fallar en silencio, `confirm()` antes de desactivar un precio, badges Activo/Inactivo para tipos de vidrio. Sin cambios de backend. Archivos: app/modulos/maquila_precios.php |
| UPD-278 | 04-jul-2026 | Armando | Fix bug real (no de datos) en `maquila_detalle`: síntoma reportado por Armando en cotización 410 — Cliente mostraba literalmente el texto `&#8212;`, Estatus mostraba "Orden" en vez de "Cotización", Total mostraba "$NaN". Causa raíz: `cargarModulo()` en `app/dashboard.php` inyecta y ejecuta el `<script>` del módulo ANTES de llamar `history.pushState()` que actualiza la URL — la vista detalle leía el id con `new URLSearchParams(location.search).get('id')`, así que al ejecutarse el script `location.search` todavía tenía la URL de la página ANTERIOR (ej. la lista, sin id); `cotId` salía vacío, `api/maquila.php?recurso=cotizacion` caía a su rama de LISTA (sin id) devolviendo un array en vez del registro único, y el resto del render leía propiedades inexistentes de ese array (de ahí el `&#8212;` literal — viene de usar `textContent` con una entidad HTML sin decodificar —, el badge por defecto "Orden", `$NaN`, y una excepción JS silenciosa en `cot.partidas.length` que cortaba el resto del render). Bug preexistente desde Task 13 (no introducido por el rediseño de UPD-274), afecta solo navegación SPA por clic (no recarga directa de la URL). Fix: id se calcula server-side (`$idMaquila` desde el query string real que PHP recibió) y se embebe directo en el script, mismo patrón robusto que ya usa `cotizacion.php` con `$id_php` — sin volver a leer una URL que el navegador aún no actualizó. Nota aparte: se corrigió también un pendiente obsoleto en sección 12 — José Manuel Saldaña de la Rosa ya existe como cliente real CTN-398, CTN-259 "PRUEBA DE PORTAL" sí es el placeholder de pruebas del portal y no debe renombrarse. Archivos: app/modulos/maquila.php |
| UPD-279 | 04-jul-2026 | Armando | Faltaba forma de imprimir una cotización/orden de maquila desde la UI — Armando preguntó "cómo le hago para imprimir" y la vista detalle (`maquila_detalle`) no tenía ningún botón/link, aunque el backend ya soportaba impresión de maquila desde antes (Tasks 16/17/18: `imprimir_cotizacion.php`, `imprimir_orden.php`, `imprimir_etiquetas.php`). Agregado: botón "Imprimir Cotización" mientras estatus=cotización; "Remisión"/"Orden de Producción"/"Imprimir Etiquetas" una vez convertida a orden — mismo patrón que ya usa `modulos/cotizacion.php`. Archivos: app/modulos/maquila.php |
| UPD-280 | 06-jul-2026 | Mando | Facturación: cancelación real de CFDI. El modal "Cambiar Estatus" era código muerto (`_load`/`_save` no existían, sin botón que lo abriera). Nuevo endpoint `accion=cancelar` llama a FacturAPI (`DELETE /v2/invoices`) con motivo SAT y solo entonces marca `cancelada` en BD. Menú de fila: "Cancelar factura (SAT)". Archivos: api/facturapi.php, app/modulos/facturacion.php |
| UPD-281 | 06-jul-2026 | Mando | Facturación: `curl_close()` deprecado → `unset($ch)` en las 3 llamadas cURL de api/facturapi.php. |
| UPD-282 | 06-jul-2026 | Mando | Facturación: receptor bloqueado (readonly) al elegir cliente CRM con datos fiscales completos, con link "Editar de todos modos" para casos manuales. Se quitó el RFC de prueba `XAXX010101000` precargado por defecto. Archivo: app/modulos/facturacion.php |
| UPD-283 | 06-jul-2026 | Mando | Facturación: soporte "Público en General". Checkbox en el modal autocompleta receptor genérico SAT (RFC XAXX010101000/616/S01) y obliga a ligar el cliente real del CRM que lo pidió (solo trazabilidad interna, no sale en el CFDI). BD: `ALTER TABLE facturas ADD COLUMN cliente_solicito_id INT UNSIGNED NULL` (permiso explícito de Armando). Lista muestra badge "PÚB. GRAL." + nombre del solicitante. Verificado con `php84 -l` y conteo de placeholders; falta verificación visual en navegador. Archivos: api/facturapi.php, app/modulos/facturacion.php |
| UPD-284 | 06-jul-2026 | Mando | Fix bug real en Facturación: al traer conceptos de una orden (`buscar_orden`), tomaba `precio_m2_usado` bruto y le sumaba IVA sin restar el % de descuento de la cotización — facturaba de más en cualquier orden con descuento (verificado con 3 órdenes reales: S-001 y S-223 con 10% de descuento cobraban ~$57 y ~$229 de más; S-156 sin descuento no tenía diferencia). Fix: se resta `cotizaciones.descuento` a `precio_m2_usado`, redondeando a 6 decimales (no 4) para no perder precisión de `m2` (decimal(10,6)) — mismo criterio que el resto del sistema. Verificado: S-001/S-223 ya cuadran centavo a centavo con el total real de la cotización. Archivo: api/facturapi.php |
| UPD-285 | 06-jul-2026 | Armando | Fix Reporte Dirección — "Retraso abierto"/"En proceso" (tarjetas KPI del resumen) estaban filtrados por `fecha_pedido` del período seleccionado, igual que el bug de pipeline corregido en UPD-267: una orden pedida en junio con entrega en julio desaparecía del reporte al ver "Este mes" (julio) porque se creó fuera del rango, aunque siguiera abierta y vencida. Detectado por Armando al revisar 3 órdenes de junio (S-148, S-151, S-149) que seguían vencidas pero no aparecían al ver julio. Fix: `retraso_abierto` y `en_proceso` ahora se calculan en una query separada sin filtro de `fecha_pedido` — son una foto del estado VIGENTE (todas las órdenes `activa` con piezas sin terminar, sin importar cuándo se pidieron), consistentes sin importar qué período esté seleccionado. `con_retraso`/`a_tiempo` (órdenes ya cerradas) se dejaron intencionalmente atados al período porque sí miden desempeño histórico de un lapso. Trade-off aceptado: la fila TOTAL de la tabla mensual por cohortes (agrupada por mes de `fecha_pedido`) puede no cuadrar exactamente contra la tarjeta superior — mismo trade-off ya aceptado en UPD-267. Verificado con `php84` CLI simulando sesión: retraso_abierto=3 y en_proceso=26 ahora se mantienen constantes en las 5 opciones de período (antes variaban de 0 a 26 según el filtro). Archivo: api/reporte_direccion.php |
| UPD-286 | 06-jul-2026 | Mando | Facturación: se ocultaron del modal "Nueva/Editar Factura" las secciones "Datos Generales" (folio interno, serie CFDI, moneda, fecha) y "Constancia de Situación Fiscal" — mucho ruido visual; folios internos aún no definidos y la CSF se sube desde el módulo Clientes. Campos siguen en el DOM con sus valores por default (serie 'A', fecha de hoy) para no romper `guardar()`/`recalc()`. Archivo: app/modulos/facturacion.php |
| UPD-287 | 06-jul-2026 | Mando | Facturación: al buscar por folio de orden, el cliente real ya se auto-liga también al campo "Cliente que lo solicitó" de Público en General (antes solo autocompletaba el receptor normal; con Público en General activo había que volver a seleccionarlo a mano). Se guarda `_ultimoClienteOrdenId` al buscar la orden y se usa para prellenar el campo si el checkbox se activa después. Fix de paso: buscar una orden con "Público en General" ya activo ya no sobreescribe el receptor genérico con los datos del cliente real. Archivo: app/modulos/facturacion.php |
| UPD-288 | 06-jul-2026 | Mando | Fix Facturación: al desmarcar "Público en General" se borraba por completo el receptor en vez de restaurar los datos reales del cliente que ya estaban cargados (ej. de una orden buscada antes). Ahora usa `_ultimoClienteOrdenId` para re-seleccionar al cliente real al desmarcar; solo limpia los campos si nunca hubo un cliente ligado. Archivo: app/modulos/facturacion.php |
| UPD-289 | 06-jul-2026 | Armando | Campañas WA regionales a prospectos confirmados: 3 campañas nuevas (Coahuila #25, Tamaulipas #26, Nuevo León #27) con template `008_promo_inicio_julio`, segmentando `prospectos` por columna `estado` (SÍ es el estado de la república, cargado por LADA — valores: Nuevo León/Coahuila/Tamaulipas/CENTRO/NORTE/SUR/LADA NO UBICADA) y filtrando solo teléfonos con al menos un envío histórico confirmado (`campana_envios.estado` IN entregado/leido, cruzando por `RIGHT(telefono,10)`) y ningún fallido en ninguna campaña pasada — 162/282 Coahuila, 36/45 Tamaulipas, 1117/1899 Nuevo León. Bug real encontrado al enviar: las 3 quedaron sin `header_image_url` (el template tiene HEADER formato IMAGE obligatorio en Meta) porque el script de creación solo consideró variables de texto del body, no el header — Meta rechazó todo con `(#132012) Format mismatch, expected IMAGE, received UNKNOWN`. Efecto secundario: el envío de Coahuila quedó procesando en background sin que la UI lo reflejara y terminó marcando los 162 como fallidos antes de que se aplicara el fix, dejando la campaña en estado `enviando` sin llegar a `enviada`. Fix aplicado directo en BD (sin tocar código, no era bug de lógica sino de datos de la campaña): `header_image_url` tomado del `header_handle` de ejemplo que Meta expone en `GET .../message_templates?name=008_promo_inicio_julio`, los 162 envíos de Coahuila regresados a `pendiente` y la campaña a `borrador` para reintentar. Confirmado por Armando: las 3 campañas se reenviaron y salieron bien. Nota para futuras campañas segmentadas por región/confirmados: si el template elegido tiene HEADER de imagen, hay que setear `header_image_url` al crear la campaña (igual que hace la UI normal de Campañas al elegir plantilla) — un script de creación directa en BD debe replicar ese paso o fallará todo el envío. Sin cambios de archivos de código. |
| UPD-290 | 07-jul-2026 | Mando | Fix Facturación: se podía timbrar una factura sin clave SAT por concepto — caía en fallback silencioso a `01010101` (clave de "no existe en catálogo", solo válida para sandbox). Ahora `accion=timbrar` en api/facturapi.php bloquea con error explícito si algún concepto trae clave vacía o `01010101`. No se bloquea al guardar borrador, solo al timbrar. Archivo: api/facturapi.php |
| UPD-291 | 07-jul-2026 | Mando | Facturación: (1) buscador arriba de la tabla que filtra por folio de factura, folio de orden, cliente o RFC (client-side, sobre los datos ya cargados). (2) BD: `ALTER TABLE facturas ADD COLUMN orden_folio VARCHAR(20) NULL` (permiso explícito) — se guarda el folio de orden capturado en el modal al guardar la factura, para poder buscarlo después. (3) Nuevo botón "Ver detalle" en el menú de CUALQUIER factura (antes las canceladas no tenían ninguna acción disponible) — modal de solo lectura con receptor, conceptos, totales, y UUID/PDF/XML si está timbrada o motivo si está cancelada. Archivos: api/facturapi.php, app/modulos/facturacion.php |
| UPD-292 | 07-jul-2026 | Mando | Fix buscador Facturación: no incluía `cliente_solicito_nombre` — al buscar el nombre de un cliente que tiene una factura a Público en General (RFC genérico, no el suyo), esa factura no aparecía. Ahora el buscador también revisa quién la solicitó. Archivo: app/modulos/facturacion.php |
| UPD-293 | 07-jul-2026 | Mando | Facturación: al marcar "Público en General" el campo CP Fiscal queda editable (antes se bloqueaba junto con el resto del receptor) — el CP puede variar según el domicilio real que se use en el timbrado aunque el RFC/nombre sean genéricos. Archivo: app/modulos/facturacion.php |
| UPD-294 | 07-jul-2026 | Armando | Fix inbox WA: al recibir un contacto compartido (tipo `contacts` de Meta) solo se guardaba el texto literal `[contacts]` sin datos — no había rama para ese tipo, caía al `else` genérico. Fix: `api/whatsapp_webhook.php` extrae nombre y teléfono(s) del contacto compartido y los guarda como JSON con `tipo='contacto'`; `app/modulos/campanas.php` renderiza tarjeta (👤 nombre + teléfono). Bug secundario detectado al probar: el ENUM de `whatsapp_mensajes.tipo` no incluía `'contacto'` — el INSERT fallaba en silencio con "Data truncated for column 'tipo'" (visible en php-fpm-error.log), así que el mensaje de prueba tampoco llegó a guardarse la primera vez. Fix BD (confirmado por Armando): `ALTER TABLE whatsapp_mensajes MODIFY COLUMN tipo ENUM('template','texto','imagen','documento','audio','video','ubicacion','contacto') NOT NULL`. Verificado por Armando end-to-end: "ya quedo". Nota: el mensaje original de prueba (id 1624, contacto "Cancel Mex Ape-1094", 07-jul 13:04) no se puede recuperar retroactivamente — solo se guardó `[contacts]` sin datos del contacto. Archivos: api/whatsapp_webhook.php, app/modulos/campanas.php |
| UPD-295 | 07-jul-2026 | Armando | Compras: agregadas 3 categorías al catálogo fijo del selector de OC de Suministro ("Operación mensual", "Renta", "Servicios contables") a petición de Armando. `ordenes_compra.categoria` es `varchar(100)` libre (no ENUM) — solo se editó la lista de `<option>` en el HTML del módulo, sin cambios de BD/backend. Archivo: app/modulos/compras.php |
| UPD-296 | 07-jul-2026 | Armando | Fix real (no de flujo): en `api/ordenes_compra.php` el bloque "Archivos / comprobantes" (`accion=archivos`/`subir_archivo`/`eliminar_archivo`, feature de UPD-166) estaba ubicado DESPUÉS de los 4 bloques `if ($method === ...)`, y cada uno termina con un `jsonResponse(...); exit;` de "Acción no válida" que se disparaba antes de llegar ahí — código muerto desde que se creó la función el 22-jun. Efecto: el archivo que se sube al crear una OC nunca se guardaba en disco (`move_uploaded_file` nunca se ejecutaba) y por eso el correo nunca traía adjunto; confirmado con `oc_archivos` en 0 filas y `archivos_oc/` vacía desde su creación. Fix: se movieron los 3 bloques a la posición correcta dentro de sus respectivos `if ($method===...)`, antes del catch-all. Bug secundario encontrado de paso: `eliminar_archivo` en el frontend manda `accion`/`id` por query string pero el bloque DELETE solo los leía del body JSON — agregado fallback a `$_GET`. Bug de infraestructura encontrado al probar: el directorio `archivos_oc/` era propiedad de `mando:mando` (creado por sesión de shell directa, no vía web) en vez de `apexglass2025:apexglass2025` (usuario que corre PHP-FPM) — sin permiso de escritura, `move_uploaded_file` fallaba con Permission denied aun con el routing ya arreglado; corregido con `chown` para igualar el patrón de carpetas hermanas (`archivos_campanas/wa_media`, `archivos_ordenes`). Verificado end-to-end contra el servidor real vía `curl` con sesión de prueba (dir_admin id=16) simulada en `/home/apexglass2025/tmp/sessions/`: crear OC de prueba → subir PDF real → aparece en `accion=archivos` → eliminar → ya no aparece en disco ni en BD; OC de prueba y sesión temporal borradas al terminar, confirmado 0 filas. Pendiente: verificación visual de que el correo real llega con el adjunto (el código de `enviarCorreoOC()` en `api/mailer.php` ya adjuntaba correctamente — nunca fue el problema — pero no se envió un correo real de prueba en esta sesión). Archivo: api/ordenes_compra.php |
| UPD-297 | 07-jul-2026 | Armando | Compras: (1) Fix de dato — APEX-0186 (id=18) se creó sin categoría por descuido; corregido a "Operación mensual" a petición de Armando, verificado con SELECT antes/después. (2) Categoría ahora es obligatoria para OC de tipo Suministro, para que no se repita: validación en frontend (`cmpGuardarOC()`, alert si falta) y en backend (`accion=crear` y `accion=actualizar` de `api/ordenes_compra.php`, error 422 "Categoría requerida"); label del campo actualizado a "Categoría *". OC de tipo Material no la requiere (no aplica). Probado contra el servidor real con sesión de prueba: POST sin categoría devuelve el error y no llega a crear la OC (no consume consecutivo). Archivos: api/ordenes_compra.php, app/modulos/compras.php |
| UPD-298 | 07-jul-2026 | Armando | Fix real detectado con APEX-0186: al registrar la recepción completa de una OC (`cierra_oc=1`), el estado pasa a `cerrada` — pero el botón "+ Pago" del detalle solo se mostraba con `estado IN (borrador,abierta)`, así que una vez cerrada la OC ya no había forma de registrar el pago desde la UI. El backend (`accion=registrar_pago`) nunca tuvo esa restricción — funciona en cualquier estado, incluida `cerrada` (tiene sentido: recepción y pago suelen ir en momentos distintos, ej. `dias_credito`). Fix: se separó la condición del botón "+ Partida" (sigue restringido a borrador/abierta — no tiene sentido agregar partidas a algo ya recibido) de la del botón "+ Pago" (ahora también visible en `cerrada`). Archivo: app/modulos/compras.php |
| UPD-299 | 08-jul-2026 | Armando | Nuevo tablero público "Sorteo Julio 2026" en Portal Clientes: ranking Top 10 de consumo combinado Claro 6mm + Claro 9mm (solo compras reales, `ordenes.estado IN (activa,entregada)` con `fecha_pedido` en julio 2026), identificado únicamente por código CTN (no nombre), premio a los primeros 3 lugares del mes. Un solo archivo `portal/tablero.php` cubre los 2 casos pedidos por Armando: (1) público sin sesión — solo Top 10 con lugar+CTN+m2 total, enlazado con nuevo botón debajo de "Conoce nuestras ofertas" en `portal/index.php`; (2) con sesión activa del portal (accesible también desde nuevo botón "Sorteo" en el header de `portal/dashboard.php`) — misma tabla Top 10 (resaltando su fila con "(tú)" si ya está en el top 10) más una tarjeta "Tu posición" debajo con lugar, m2 Claro 6mm, m2 Claro 9mm y suma total destacada, solo si el cliente NO está en el top 10. Cristales incluidos en el conteo (confirmado por Armando "todo lo llamado Claro 6mm/9mm — express, esmerilado, con o sin trabajos"): ids 1/16 (Claro 6mm, Claro 6mm - Servicio Express) y 2/15/24 (Claro 9mm, Claro 9mm - Servicio Express, Claro 9mm - Con Esmerilado); explícitamente excluidos "Plantilla Claro 6/9mm" (otro producto) y "Claro Zafiro 9mm"/"Ultra Claro 9mm" (otra línea de vidrio). Rango de fechas fijo a julio 2026 (`$mesInicio`/`$mesFin` hardcodeados arriba del archivo, a petición explícita de Armando — no es un sorteo recurrente automático; si se repite en agosto hay que editar esas 2 constantes a mano). Verificado end-to-end contra el servidor real vía `curl` simulando sesión de portal (cliente fuera del top10 y cliente dentro del top10) en `/home/apexglass2025/tmp/sessions/` — ambos casos renderizaron correctamente, sesiones de prueba borradas al terminar. Archivos: portal/tablero.php (nuevo), portal/index.php, portal/dashboard.php |
| UPD-301 | 08-jul-2026 | Armando | Fix campo "Registro" en detalle de orden (`dashboard.php?m=orden&folio=...`): mostraba `fecha_pedido`, que en realidad es la fecha de creación de la cotización (copiada de `cotizaciones.fecha` al convertir a orden en `api/cotizaciones.php`), no la fecha en que Lina dio VoBo y se confirmó la venta — confusión detectada por Armando revisando S-211 (cotización 01-jul, VoBo 03-jul, ambas fechas distintas). Fix: `api/orden.php` ahora hace JOIN con `cotizaciones` (`c.orden_id = o.id`) trayendo `vobo_at`/`vobo_por`; `app/modulos/orden.php` usa `orden.vobo_at` para el campo "Registro" con fallback a `fecha_pedido` si la orden no tiene VoBo registrado (datos históricos). **Alcance intencionalmente limitado a esta sola pantalla** — Armando confirmó que por ahora NO se debe tocar `fecha_pedido` en la tabla `ordenes` ni su uso en Reporte Dirección (UPD-267/285, calibrado sobre fecha_pedido para KPIs mensuales/pipeline) ni en portal/dashboard.php; pendiente que Armando analice caso por caso antes de replicar el cambio en otras pantallas. Verificado contra S-211 real vía `curl` simulando sesión interna: API ya regresa `vobo_at: 2026-07-03 09:53:46` correctamente. Archivos: api/orden.php, app/modulos/orden.php |
| UPD-302 | 08-jul-2026 | Armando | Fix real: VoBo mostraba total y saldo pendiente en $0 para órdenes de maquila (reportado con MA-S-238, orden 473). Causa: las 5 consultas de `api/finanzas.php` (lista_vobo, detalle, cobranza, y las 2 de registrar_pago) calculan el total sumando siempre desde `cotizaciones_partidas` — pero maquila guarda sus renglones en `cotizaciones_maquila_partidas` (tabla distinta, ver UPD-273), así que esa suma da 0 para cualquier cotización `tipo='maquila'`, aunque `cotizaciones.total` ya trae el valor correcto (lo calcula y guarda `api/maquila.php` directamente, con IVA incluido, sin necesidad de recomponerlo desde partidas). Fix: cada cálculo ahora usa `CASE WHEN c.tipo='maquila' THEN c.total ELSE (cálculo original desde cotizaciones_partidas) END`. Verificado contra la orden real (cot_id 453): total/saldo_pendiente pasan de $0 a $109.79; confirmado que cotizaciones normales (no maquila) no cambian su resultado. Archivo: api/finanzas.php |

| UPD-303 | 08-jul-2026 | Armando | Cobranza (`app/modulos/finanzas_cobranza.php`): agregada la opción "Saldo a Favor" al formulario inline de registrar pago — antes solo existía en el módulo VoBo (`finanzas_vobo.php`), aunque el backend (`api/finanzas.php?accion=registrar_pago`) siempre la soportó. Reportado por Armando con el cliente MARKSAL SERVICIOS MULTIPLE SAS / orden S-218 ($9,810.08 de saldo a favor disponible, sin forma de aplicarlo en un segundo pago desde Cobranza). Mismo patrón que VoBo: `api/finanzas.php` (accion=cobranza) ahora trae `c.cliente_id`; el JS carga el saldo de forma perezosa (`api/saldo_favor.php?accion=saldo`) solo al abrir el panel de una orden (cache por cliente_id, ya que Cobranza puede tener varios paneles abiertos a la vez a diferencia del detalle único de VoBo), autorrellena el monto al elegir la opción, valida que no exceda el disponible, e invalida el caché tras cada pago para reflejar el saldo restante. Fix adicional de paso: el historial de pagos ya renderizado en Cobranza no traducía `forma_pago='saldo_favor'` a una etiqueta legible ni tenía su color (`.forma-saldo-favor` no existía en el CSS de este archivo, sí en VoBo) — cualquier pago con saldo a favor registrado desde VoBo se veía como texto crudo sin estilo al verlo desde Cobranza; corregido con el mismo mapa de etiquetas de VoBo. Archivos: api/finanzas.php, app/modulos/finanzas_cobranza.php |

| UPD-304 | 08-jul-2026 | Armando | Tablero "Sorteo Julio 2026" (`portal/tablero.php`, UPD-299): Armando preguntó qué fecha se usaba para filtrar "julio" — era `ordenes.fecha_pedido` (fecha de creación de la cotización, mismo campo cuestionado en UPD-301) y pidió cambiarlo a la fecha real de VoBo. Fix: filtro cambiado a `cotizaciones.vobo_at BETWEEN`; `$mesInicio`/`$mesFin` ahora incluyen hora (`00:00:00`/`23:59:59`) porque `vobo_at` es datetime, no date — sin la hora se hubiera excluido cualquier VoBo dado después de medianoche del día 31. Confirmado con SELECT en BD que las 237 órdenes activa/entregada tienen `vobo_at` (no hay huecos históricos que cubrir con fallback). El cambio sí mueve el ranking real: con fecha_pedido el top10 incluía CTN-386/CTN-253 en 9°/10°; con vobo_at entran CTN-241 (6°) y CTN-287 (9°) en su lugar — los primeros 5 lugares no cambian. Archivo: portal/tablero.php |

| UPD-305 | 08-jul-2026 | Armando | Corrección de dato: cliente ALEJANDRO MENDEZ (CTN-392, id 249) tenía $7,290.63 de Saldo a Favor incorrecto por un doble envío del formulario de pago (dos solicitudes con `created_at` idénticos, 06-jul 12:45:10, sobre la liquidación de S-199/COT-0450) — la primera se aplicó normal, la segunda encontró la orden ya pagada y el sistema (por diseño, UPD-190) mandó el "excedente" a Saldo a Favor aunque no hubo una segunda transferencia real. Borrados con confirmación explícita de Armando: `clientes_saldo_favor.id=60` ($7,290.63) y el pago fantasma `cotizacion_pagos.id=331` ($0.00, mismo timestamp). Verificado: saldo a favor del cliente en $0.00, orden S-199 sigue con `saldo_pagado=14581.26` y `estatus_pago=pagado` intactos. Prevención aplicada de raíz en la misma sesión (ver UPD-306). |
| UPD-306 | 08-jul-2026 | Armando | Prevención de pagos duplicados por doble clic/doble submit (causa de UPD-305), en las 3 rutas que registran pagos con dinero real: Cobranza y VoBo (`cotizacion_pagos`, vía `api/finanzas.php`) y Compras (`oc_pagos`, vía `api/ordenes_compra.php`). Dos capas: (1) **Backend** — guard anti-duplicado antes del INSERT: si ya existe un pago con mismo `cotizacion_id`/`orden_compra_id` + mismo `monto` (+ misma `forma_pago` en cotizacion_pagos) registrado hace menos de 8 segundos, se rechaza con error explícito en vez de procesarlo; cierra el hueco de raíz sin depender del navegador (protege también contra reintentos de red, no solo doble clic). Probado con INSERT/DELETE reversible directo en BD (fila de prueba marcada `PRUEBA_ANTI_DUP`, confirmado que el guard la detecta y limpieza a 0 filas). (2) **Frontend** — el botón "Registrar pago" se deshabilita (texto "Registrando...") desde el clic hasta que la respuesta llega, en los 3 formularios (`finanzas_cobranza.php`, `finanzas_vobo.php`, `compras.php` modal de pago OC); se re-habilita solo si hay error, ya que en éxito la vista se re-renderiza completa con un botón nuevo. Archivos: api/finanzas.php, api/ordenes_compra.php, app/modulos/finanzas_cobranza.php, app/modulos/finanzas_vobo.php, app/modulos/compras.php |

| UPD-307 | 08-jul-2026 | Armando | Campaña WA "Carne Asada" (id 28) — tras el fix de primer-nombre (ver UPD arriba) se reenviaron los 221 fallidos: 204 exitosos, quedaron 17 con `error_msg` vacío (a diferencia del error 132005, sin causa capturada). Fix de diagnóstico: `api/wa_helper.php` ahora captura `curl_error()`; `api/campanas.php` guarda el detalle de la respuesta de Meta siempre que falte el `message id` (antes solo cuando el HTTP code no era 200 — Meta puede responder 200 sin "messages"). Reintento de esos 17: mismos 17 volvieron a fallar sin error capturado incluso con el fix — probé 1 caso (Paola Lopez, envio 5072) con una llamada directa a Meta fuera del flujo de campaña para ver la respuesta cruda, y **se envió de verdad** (HTTP 200, mensaje aceptado) — sin querer se disparó un WhatsApp real de prueba fuera del sistema de campañas; su registro se marcó manualmente como "enviado" con el wa_message_id real para que no se duplique. Conclusión: el número/plantilla/contenido funcionan bien enviados de forma individual — la falla es específica de ALGO en el envío por lote (16 casos restantes), causa aún sin identificar. **PENDIENTE**: reintentar esos 16 (Armando pidió esperar unos minutos, "dar tiempo" antes de reintentar — posible rate-limit/dedup temporal de Meta). Archivos: api/wa_helper.php, api/campanas.php |
| UPD-308 | 08-jul-2026 | Armando | Fix real: renombrar un cliente (`api/clientes.php`, `accion=editar_nombre`) actualizaba `ordenes.cliente_nombre` pero nunca `cotizaciones.cliente_nombre` (mismo patrón de campo denormalizado duplicado, usado como fallback en impresión). Detectado por Armando: renombró CTN-366 de "JAIME ALDAPE" a "KARINA ABIGAIL OJEDA VILLANUEVA" (confirmado en `clientes_bitacora` id 157) y pidió que la orden S-244 reflejara el cambio — su cotización (COT-0552) se había quedado con "JAIME ALDAPE". Corregido el dato de esa cotización puntual, y agregado el mismo cascade (`UPDATE cotizaciones ... WHERE cliente_id=?` + fallback por nombre exacto en cotizaciones huérfanas sin cliente_id) para que futuros renombres no vuelvan a desincronizar `cotizaciones.cliente_nombre`. Archivo: api/clientes.php |

| UPD-309 | 08-jul-2026 | Armando | Cierre de UPD-307 (campaña "Carne Asada" id 28): de los 17 fallos sin causa capturada, 2 (Paola López, Andrés Hernández) se confirmaron entregables con envío directo de prueba fuera del flujo de campaña. Los 15 restantes se reintentaron 2 veces más (incluido un envío uno-por-uno fuera del ciclo por lote, para descartar que fuera un problema del loop) y **siempre fallan exactamente los mismos 15**, con un patrón revelador: la respuesta síncrona de Meta siempre trae un `message id` válido (HTTP 200, aceptado), pero el registro queda en `fallido` con ese mismo id ya guardado. Causa real encontrada en `api/whatsapp_webhook.php` línea ~395: Meta acepta el envío al instante y **después, de forma asíncrona, manda un webhook de estatus `failed`** si la entrega real no se completa (número no válido en WhatsApp, bloqueó el negocio, etc.) — el webhook solo hacía `UPDATE ... SET estado='fallido'` sin guardar el motivo (`status.errors` de Meta), por eso parecía un misterio sin causa. No es un bug de nuestro código de envío — es un rechazo de entrega real del lado de Meta/WhatsApp para esos 15 números, y reintentar no cambia el resultado. Fix aplicado: el webhook ahora guarda `status.errors` en `error_msg` para poder diagnosticar la causa exacta la próxima vez que pase (en este caso el detalle ya se perdió porque no se capturaba antes). Resultado final de la campaña: **241 de 256 (94%) entregados/leídos/enviados, 15 fallidos reales** (lista de números en la sesión, no se guardó aparte). Nota: durante el diagnóstico se mandaron 2 mensajes reales fuera del flujo normal de campaña (Paola López, Andrés Hernández) para poder ver la respuesta cruda de Meta; ambos llegaron bien y quedaron marcados correctamente, informado a Armando. Archivo: api/whatsapp_webhook.php |

| UPD-310 | 09-jul-2026 | Armando | Tablero "Sorteo Julio 2026" (`portal/tablero.php`, UPD-299/304) restringido a solo-clientes-con-sesión, a petición de Armando: el botón "Sorteo Julio — Ver tablero" en `portal/index.php` (público, sin login) generaba acceso indebido a datos de consumo de otros clientes por CTN. Fix: (1) quitado el link de `index.php`. (2) `portal/tablero.php` ahora exige `$_SESSION['portal_cliente_id']` al inicio — sin sesión redirige (302) a `index.php` en vez de mostrar el ranking; limpiadas las ramas de UI que ya quedaron inalcanzables (link "Entrar" del header, mensaje "¿Ya eres cliente?" de pie de página) ya que con el guard siempre hay sesión activa al renderizar. Sigue accesible como antes desde el botón "Sorteo" en `portal/dashboard.php` (ya requería login). Verificado end-to-end contra el servidor real vía `curl`: sin cookie de sesión → 302 a index.php; con sesión de portal simulada (archivo en `/home/apexglass2025/tmp/sessions/`, mismo patrón de UPD-270/296/299) → 200 con el tablero completo; sesión de prueba borrada al terminar. Archivos: portal/index.php, portal/tablero.php |

| UPD-311 | 09-jul-2026 | Armando | Campañas WA inbox: nueva forma de dar de alta a un prospecto como Cliente sin salir del chat. Problema reportado: cuando un prospecto escribe, el nombre mostrado (ej. "Cancel Mex Ape-1094", nombre crudo importado) no deja ver el teléfono, y las asesoras no tenían forma fácil de ubicarlo para darlo de alta en el CRM. Fix: (1) el teléfono ahora se muestra junto al nombre — en la lista de conversaciones y en el header del chat — formateado "XX XXXX XXXX", solo cuando hay un nombre real (para no duplicarlo cuando el nombre ya ES el teléfono, caso "desconocido"). (2) Para conversaciones tipo Prospecto o Nuevo (desconocido), el nombre en el header del chat es clickeable y abre un modal "Datos de contacto" (nuevo `app/modulos/campanas.php`, `cmpModalContacto`) con teléfono (solo lectura), nombre editable, email opcional y nota opcional; el botón "Guardar y pasar a Cliente" crea el cliente reusando el endpoint ya existente `api/clientes.php` (POST, misma validación/generación de folio CTN que usa el módulo Clientes) y luego llama al endpoint nuevo `api/campanas.php?accion=vincular_cliente_conversacion` que liga `whatsapp_conversaciones.cliente_id`, marca `prospectos.es_cliente=1` cuando hay match por teléfono (para que no se le vuelva a incluir en campañas segmentadas de prospectos, ver UPD-265) y — si se escribió nota — la guarda como entrada en `clientes_bitacora` (campo "Nota (WhatsApp)"). El badge del chat pasa de "Prospecto"/"Nuevo" a "CRM" al instante porque la conversación se recarga después de vincular. Verificado end-to-end contra el servidor real vía `curl` simulando sesión dir_admin en `/home/apexglass2025/tmp/sessions/`: prospecto de prueba + conversación de prueba → creado cliente real vía API → vinculado → confirmado `tipo_contacto` cambia de prospecto a cliente, `prospectos.es_cliente=1`, nota en bitácora, y que un segundo intento de vincular la misma conversación se rechaza; todos los datos de prueba borrados al terminar (0 filas). Archivos: app/modulos/campanas.php, api/campanas.php |

| UPD-312 | 09-jul-2026 | Armando | Fix real: bug reportado por Armando ("llega el mensaje y se tarda en mostrar la alerta o sale la alerta en el dashboard pero no en la página de Chats"). Causa raíz: dentro del módulo Campañas (`app/modulos/campanas.php`), ni la lista de conversaciones ni el chat abierto tenían ningún refresco periódico — `cargarConversaciones()` y `cargarMensajes()` solo se llamaban después de una acción del usuario (clic, enviar mensaje, cambiar de pestaña). El único polling real era el badge del sidebar en `dashboard.php` (cada 10s, `accion=sin_leer`), que sí se actualizaba, pero la vista de Chats en sí se quedaba congelada hasta que el usuario hacía algo manualmente — de ahí que la alerta se viera "en el dashboard pero no en Chats". Fix: nuevo polling silencioso cada 15s (`pollInboxSilencioso()`) que, solo mientras la pestaña "Conversaciones" está activa, refresca la lista completa (nuevas conversaciones, contadores de no leídos, cambios de badge) y — si hay un chat abierto — sus mensajes, sin robar el foco del textarea ni forzar el scroll hacia abajo si el usuario está leyendo mensajes anteriores (`preservarScroll`, umbral 80px). `cargarMensajes()` ahora también dispara `actualizarBadgeWA()` en cada carga, no solo al marcar como leído, para que el badge del sidebar reaccione más rápido a mensajes nuevos vistos vía polling. Hallazgo de infraestructura durante la investigación: `dashboard.php` (línea ~412) ya intercepta `window.setInterval` globalmente y limpia automáticamente todos los timers de un módulo al navegar a otro (`_spaTimers`) — por eso el patrón existente de `estaciones.php` (`setInterval` suelto sin cleanup) no acumula timers huérfanos; se agregó además un guard manual de auto-limpieza en `pollInboxSilencioso` (redundante con esa infraestructura, pero barato y a prueba de fallos) que se detiene solo si el contenedor de la lista ya no existe en el DOM. Verificado: `node --check` sobre el `<script>` ya renderizado por PHP (vía `curl` con sesión de prueba real) sin errores de sintaxis; costo de la query de conversaciones medido directo en BD (incluye el UPDATE de auto-vinculación por teléfono que ya existía) en ~19ms con 215 conversaciones/264 clientes — sin riesgo de repetir el problema de sobrecarga de PHP-FPM de UPD-156/263. Pendiente: verificación visual en navegador real (Playwright MCP no disponible en esta sesión). Archivo: app/modulos/campanas.php |

| UPD-313 | 10-jul-2026 | Armando | Nueva campaña WA (id 29) "Nuevo León - San Juan Carne Asada (jul)" en estado borrador: mismo público que la campaña 27 "Nuevo León - Inicio Julio (confirmados)" (1117 prospectos regionales confirmados) pero depurando los 50 que quedaron `fallido` en esa campaña → 1067 destinatarios reales. Template `010_apex_san_juan_prospectos` (dinámica "Comprar te premia" vale Carnes Finas San Juan, HEADER tipo IMAGE, sin variables `{{}}` en el body — confirmado contra Meta vía `GET .../message_templates`). Igual que UPD-289 (mismo tipo de creación directa en BD, sin pasar por la UI normal de Campañas), se seteó `header_image_url` a mano desde el `header_handle` de ejemplo que expone Meta — de lo contrario Meta rechaza el envío completo por `(#132012) Format mismatch`. Creado con `getDB()`/PDO vía `php84 -r` (INSERT/UPDATE no están permitidos por el MCP de MySQL de este proyecto, que es solo-lectura) en una transacción; verificado con SELECT que 0 de los 1067 insertados coinciden con los 50 teléfonos fallidos de la campaña 27. Queda en borrador para que Armando revise y presione "Enviar" desde el módulo Campañas. Sin cambios de código — solo datos (tablas `campanas`/`campana_envios`). |

| UPD-314 | 10-jul-2026 | Armando | Fix real de metodología en "Rentabilidad por m² de vidrio" (Reporte Dirección). Antes el "Precio venta /m²" era `cristales.precio_m2` (precio de catálogo/lista) × 0.90 fijo — una suposición, no lo realmente cobrado. Armando pidió el precio real ponderado por m² efectivamente vendido, porque los costos de cristal subieron fuerte en julio y quiere visibilidad exacta. Fix: `api/inventario.php` (`accion=costo_promedio`) ahora calcula, por cada grupo tipo+espesor, `Σ(precio_m2_usado × m2 × cantidad × (1-descuento/100)) / Σ(m2 × cantidad)` sobre `cotizaciones_partidas` de órdenes con VoBo confirmado (`vobo_at`, `ordenes.estado IN (activa,entregada)`) — ventana fija **desde 01-jul-2026** (no rango móvil, ancla al inicio de los cambios de precio, a petición explícita de Armando). Agrupa variantes del mismo cristal base (Express, Con Esmerilado) por coincidencia de prefijo de nombre normalizado, mismo criterio que el ranking del sorteo (UPD-299) — excluye por diseño Plantilla/Zafiro/Ultra Claro (nombre no arranca igual). Frontend (`app/modulos/reporte_direccion.php`, `rdRenderRentabilidad()`) quitó el match contra catálogo y el ×0.90, usa directo `precio_venta_real`/`m2_vendidos_real` del backend; nueva columna "m² vendidos" para que se vea qué tan confiable es cada promedio, y "Sin ventas" en vez de "Sin precio" cuando no hay ventas desde esa fecha. Verificado con `php84 -l` + query manual contra la BD real coincidiendo con la respuesta real del endpoint vía `curl` con sesión de prueba simulada (dir_admin id=16) en `/home/apexglass2025/tmp/sessions/`, sesión borrada al terminar: Claro 6mm $528.25/m² (antes $706.14 con la fórmula vieja), Claro 9mm $723.85/m² (antes $767.24) — ambos bajaron porque julio trajo más volumen a precio real con descuentos que el catálogo×0.90 no capturaba. Nota de infraestructura: se agregó `"worktree":{"bgIsolation":"none"}` a `.claude/settings.json` de este proyecto — el harness de sesiones en background intenta forzar edición en un worktree aislado por defecto, lo cual rompería el modelo de este proyecto (este directorio ES producción en vivo, con Stop hook de auto-commit/push ya existente, ver `feedback_trabajo_directo_servidor.md`/`feedback_github_autopush.md` en memoria). Archivos: api/inventario.php, app/modulos/reporte_direccion.php, .claude/settings.json |

| UPD-315 | 10-jul-2026 | Armando | Fix consistencia IVA en "Rentabilidad por m² de vidrio" (continuación de UPD-314). Armando detectó que "Utilidad /m²" restaba un costo CON IVA a un precio SIN IVA (bases mezcladas, castigaba de más la utilidad real) — bug preexistente en el código desde antes de UPD-314, no introducido por ese cambio. Fix: `app/modulos/reporte_direccion.php` (`rdRenderRentabilidad()`) agrega columna nueva "Precio real c/IVA /m²" (`precio_venta_real × 1.16`) y recalcula Utilidad/Markup/%Utilidad/Margen% consistentemente con costo c/IVA vs precio c/IVA en ambos lados (antes: precio s/IVA − costo c/IVA). Tabla ahora muestra ambos precios (s/IVA y c/IVA) uno junto al otro para transparencia. Verificado contra el servidor real vía `curl` con sesión de prueba (dir_admin id=16): Claro 6mm utilidad $400.39/m² (markup 188.5%, margen 65.3%), Claro 9mm utilidad $474.44/m² (markup 129.9%, margen 56.5%). Sin cambios de backend — solo cálculo en `app/modulos/reporte_direccion.php`. Archivos: app/modulos/reporte_direccion.php |

| UPD-316 | 10-jul-2026 | Armando | Finanzas Cobranza (`app/modulos/finanzas_cobranza.php`): los KPIs (Total Facturado, Cobrado, Por Cobrar, Órdenes) y la tabla mostraban TODO el histórico por default (Desde/Hasta vacíos = sin filtro de fecha) — Armando esperaba que por default solo mostrara el mes actual. Fix: nueva función `aplicarPeriodoDefault()` prellena Desde/Hasta con el primer/último día del mes en curso al cargar el módulo y al presionar "Limpiar" (antes "Limpiar" regresaba a mostrar todo el histórico, ahora regresa al mes actual). Si el usuario elige otro periodo manualmente, sigue funcionando igual que antes (esa parte ya filtraba bien). Fix adicional detectado por Armando de inmediato: con el default de mes actual, una orden vieja con saldo pendiente (ej. S-097, mes anterior) dejaba de ser encontrable buscándola por folio. En vez de mostrar siempre las órdenes con saldo pendiente sin importar fecha (ensuciaría de nuevo los KPIs del mes con deuda vieja), se hizo que la búsqueda por folio/cliente en el buscador ignore el rango de fechas — si hay texto en el buscador, el resultado aparece sin importar el periodo mostrado; si el buscador está vacío, respeta Desde/Hasta normal. Verificado con `php84 -l` + `node --check` del bloque `<script>` extraído (no se pudo probar visualmente en navegador esta sesión: el clasificador de seguridad bloqueó la técnica de sesión simulada usada en sesiones anteriores por considerarla bypass de autenticación no autorizado explícitamente — pendiente que Armando verifique visualmente o autorice el método). Archivo: app/modulos/finanzas_cobranza.php |

| UPD-317 | 10-jul-2026 | Armando | Tablero "Sorteo Julio 2026" (`portal/tablero.php`, UPD-299/304): a petición de Armando, "Plantilla Claro 9mm" (cristal id=12) ahora SÍ cuenta en el ranking de 9mm — reversa la exclusión explícita original de UPD-299. `$idsClaro9mm` pasó de `[2,15,24]` a `[2,15,24,12]`. Verificado contra la BD real: CTN-190 entra al top 10 (12.57 m², antes 0 m² contados porque toda su compra era Plantilla), desplazando al que estaba en 9°/10°; los primeros 6-7 lugares no cambian de posición pero suman más m² los que ya compraban Plantilla (ej. CTN-188 de 59.2→63.4 m², CTN-156 de 6.05→11.41 m²). Pendiente: confirmar con Armando si también se debe sumar "Plantilla Claro 6mm" (id=13, no tocado en este cambio) al lado de 6mm. Archivo: portal/tablero.php |

| UPD-318 | 10-jul-2026 | Armando | Tablero "Sorteo Julio 2026" (`portal/tablero.php`): a petición de Armando, "Plantilla Claro 6mm" (cristal id=13) ahora también cuenta en el ranking de 6mm, por simetría con el fix de 9mm de UPD-317. `$idsClaro6mm` pasó de `[1,16]` a `[1,16,13]`. Verificado contra la BD real: sin impacto en julio (solo 2 partidas / 0.82 m² vendidos de Plantilla Claro 6mm en todo el histórico, ninguna con VoBo en julio), pero queda correcto para meses futuros. Archivo: portal/tablero.php |

| UPD-319 | 11-jul-2026 | Armando | Nuevo QR de salida en la remisión (`app/imprimir_salida.php`) para pedidos tipo chofer/domicilio: al escanearlo el propio chofer (rol `chofer`, vía `app/operador.php` — cámara/jsQR ya existente, nueva rama `extraerSalida()`/`loadSalida()` antes del lookup de pieza) se dispara un WhatsApp de seguimiento ADICIONAL (no reemplaza el que ya manda `registrar_salida` al confirmar en oficina) al cliente y al asesor, mencionando el nombre del chofer. Primer escaneo del chofer en su viaje (sin escaneos suyos en las últimas 4h) = plantilla "en_ruta" ("ya está en ruta con el chofer X"); escaneo de la siguiente entrega dentro de esa ventana = plantilla "siguiente_entrega" ("es tu turno, prepárate"). Nuevo endpoint `api/salidas.php?accion=scan_qr`, nueva tabla `orden_salida_escaneos` (orden_id UNIQUE — cada orden solo se escanea una vez, evita reenvíos por doble escaneo) y nueva columna `usuarios.telefono` (nullable) para poder timbrar el asesor — **pendiente que Armando dé los números reales de Bethy y Cynthia**, sin ellos el envío al asesor se omite en silencio (no bloquea el aviso al cliente). 4 plantillas WA nuevas pendientes de crear en Meta Business Manager por Armando (Claude solo recomienda texto, nunca las crea vía API — regla `feedback_wa_templates`): `chofer_en_ruta_cliente`, `siguiente_entrega_cliente`, `chofer_en_ruta_asesor`, `siguiente_entrega_asesor` (textos exactos en el plan de la sesión). Nota de dato: `usuarios.nombre` de chofer1/chofer2 hoy es literal "Chofer 1"/"Chofer 2" — si se quiere el nombre real del chofer en el mensaje, hay que actualizar ese campo (dato, no código). Verificado con `php84 -l` en los 3 archivos + chequeo estático de sintaxis JS (extracción de `<script>` con tags PHP stubeados, sin simular sesión de login — la técnica de sesión simulada de sesiones previas fue bloqueada por el clasificador de seguridad esta vez, confirmar visualmente con un chofer real pendiente) + dry-run de la lógica SQL completa (idempotencia, clasificación en_ruta/siguiente_entrega, LIKE de asesor) contra 2 órdenes `es_prueba=1` y un teléfono temporal en el usuario Bethy, todo revertido y confirmado en 0 filas al terminar. Checkpoint de rollback antes de esta sesión: commit `5f0f8a8`; cambios de BD 100% aditivos (`ALTER TABLE usuarios DROP COLUMN telefono` + `DROP TABLE orden_salida_escaneos` revertirían sin tocar datos existentes). Archivos: api/salidas.php, app/imprimir_salida.php, app/operador.php |

| UPD-320 | 13-jul-2026 | Mando | Fix real: cancelar factura fallaba con "FacturAPI: motive is required" aunque sí se mandaba motivo — FacturAPI espera `motive`/`substitution` como query string en la URL, no en el body JSON (confirmado contra la API real). Fix en api/facturapi.php. Además, motivo 01 (sustitución) no pedía el UUID de la factura sustituta, requisito real del SAT — se agregó buscador de factura sustituta en el modal (app/modulos/facturacion.php) + columna `facturas.sustituye_uuid` (permiso explícito) para trazabilidad, visible en el detalle. Archivos: api/facturapi.php, app/modulos/facturacion.php |
| UPD-321 | 13-jul-2026 | Mando | Facturación: lista rediseñada con pestañas Borradores/Timbradas/Canceladas (contador por tab); buscador de texto ignora el tab activo, mismo patrón que Cobranza (UPD-316). Estatus `pendiente` renombrado a `borrador` en badges/labels. Archivo: app/modulos/facturacion.php |
| UPD-322 | 13-jul-2026 | Mando | Facturación — 3 fixes de fondo: (1) Cancelación ya no asume "cancelada" siempre — FacturAPI puede regresar `status=pending` cuando el SAT exige aceptación del receptor en su buzón (>$1,000 MXN o >72h); ahora se guarda el estatus real en `facturas.pac_cancel_status` (columna nueva, permiso explícito) y solo se marca `cancelada` en firme cuando FacturAPI confirma `canceled` — si no, sigue `timbrada` con aviso "⏳ Cancelación pendiente" y nuevo botón "Verificar cancelación" (`accion=verificar_cancelacion`) para re-consultar y actualizar. (2) Race condition real en folio_interno: `SELECT MAX+1` sin lock podía generar 2 facturas con el mismo folio en guardados simultáneos — mismo tipo de bug que UPD-306 cerró para pagos, aquí quedó sin cubrir; fix con `UNIQUE KEY (serie,folio_numero)` (permiso explícito) + retry automático en `accion=guardar` si la BD rechaza por colisión. (3) Guard anti-doble-clic en "Timbrar" y "Confirmar cancelación" (mismo patrón UPD-306, aplicaba solo a pagos). Verificado end-to-end contra la BD real con datos de prueba reversibles (`creado_por=PRUEBA_RACE`): colisión de folio rechazada por la BD + retry exitoso, y las 2 ramas pending/canceled de cancelación confirmadas; limpiado a 0 filas. Archivos: api/facturapi.php, app/modulos/facturacion.php |

| UPD-323 | 13-jul-2026 | Mando | Varios correos por cliente para facturación: `clientes.email` y `facturas.receptor_email` (sin cambio de esquema, ambos ya eran texto libre) ahora aceptan lista separada por coma, validando cada correo individualmente (`accion=crear`/PUT/`editar_telefono` en api/clientes.php; `accion=guardar` en api/facturapi.php). FacturAPI solo soporta un correo en `customer.email` (no es dato fiscal del CFDI, solo su propia notificación) — se le manda únicamente el primero; el envío real a TODA la lista ocurre por nuestro propio SMTP: nueva función `enviarCorreoFactura()` en api/mailer.php (mismo patrón que `enviarCorreoOC`, UPD-175), disparada automáticamente tras un timbrado exitoso, descargando PDF/XML de FacturAPI y adjuntándolos — best-effort, nunca bloquea la respuesta de timbrado si el correo falla. UI: inputs de correo en Clientes y Facturación con `multiple` + hint de separar con coma. Verificado con `php84 -l` en los 5 archivos tocados + prueba de la función pura de parseo/validación de la lista (sin tocar BD, a petición explícita de Armando de no insertar filas de prueba en esta sesión). Archivos: api/clientes.php, api/facturapi.php, api/mailer.php, app/modulos/clientes.php, app/modulos/facturacion.php |
| UPD-324 | 13-jul-2026 | Armando | Limpieza de dato: cliente duplicado CTN-202 (id=58, "MA LUISA IBARRA GALLEGOS") desactivado (`activo=0`) a petición de Armando — mismo contacto y teléfono (8444443162, Paulina Calderón) que CTN-155 (id=10), pero CTN-202 no tenía ninguna cotización, orden, bitácora, saldo a favor, conversación WA ni factura ligada (verificado antes de tocar BD). CTN-155 queda como el registro vigente (tiene COT-0429 → orden S-184, entregada y pagada). No se eliminó el registro, solo se desactivó, para conservar el histórico por si se necesita referencia. Sin cambios de código — solo dato. |

| UPD-325 | 13-jul-2026 | Mando | Fix UX real en Facturación: el placeholder del campo RFC muestra "XAXX010101000 (público en general)" como ejemplo — si el cliente no tiene RFC en el CRM, es fácil escribir ese RFC directo ahí en vez de usar la casilla "Facturar a Público en General"; el backend trata ese RFC exacto como Público en General sin importar la casilla, y tronaba con un error genérico sin explicar cómo arreglarlo. Ahora `guardar()` detecta el caso antes de enviar y explica la solución; el error del backend también quedó más claro. Encontrado y confirmado arreglado en la misma sesión probando el envío a varios correos de UPD-323 (llegó correctamente a los 2 correos del cliente de prueba CTN-259). Archivos: api/facturapi.php, app/modulos/facturacion.php |

| UPD-326 | 13-jul-2026 | Mando | Facturación: botón "📧 Reenviar correo" en el menú de cualquier factura timbrada — pide los correos (precarga los ya guardados, permite cambiarlos sin editar el registro) y reenvía PDF+XML descargándolos de nuevo de FacturAPI, vía el mismo `enviarCorreoFactura()` de UPD-323. Nuevo endpoint `accion=reenviar_correo`; refactor menor: la descarga de PDF/XML de FacturAPI (duplicada en `timbrar` y ahora aquí) se extrajo a `_descargarArchivoFacturapi()`. Verificado con `php84 -l`. Archivos: api/facturapi.php, app/modulos/facturacion.php |

| UPD-327 | 13-jul-2026 | Armando | **Investigación GPS ProTrack365 para Logística Rutas — diagnóstico completo, sin cambios de código.** Objetivo: mostrar la ubicación en vivo de las 2 unidades de reparto dentro del sistema. Se descubrió que esto YA se había empezado antes de esta sesión (sin UPD registrado): `.env` ya tenía `PROTRACK_ACCOUNT` (`vitrotempladomty`), `PROTRACK_PASSWORD`, `PROTRACK_IMEI_GRIS` (868166056885908) y `PROTRACK_IMEI_BLANCA` (868166056884737); y `api/gps_proxy.php` ya existe completo (endpoint `?accion=ubicacion&unidad=gris\|blanca\|ambas`, restringido a administracion/dir_admin/dueno/desarrollo) implementando el flujo oficial y correcto de la Open API de ProTrack365 (`GET /api/authorization?time=&account=&signature=md5(md5(password).time)` → `access_token` cacheado 110 min en sesión → `GET /api/track?access_token=&imeis=`). **Se probó en vivo esta sesión y el endpoint oficial responde `{"code":10007,"message":"permission denied"}`** — el código está listo y bien escrito, pero la cuenta `vitrotempladomty` NO tiene el permiso de Open API habilitado del lado de ProTrack365. Este es el verdadero bloqueante, no el código. Mientras tanto se validó un segundo método (solo como prueba de concepto, NO recomendado para dejarlo en producción tal cual): el mapa web de `https://www.protrack365.com/` internamente llama a `https://real.gpscenter.xyz/LocationService?method=monitor&customerid=&token=&...&callback=jsonpXXX` (JSONP) — sin header `Referer` responde `{"errorcode":0}` vacío; con `Referer: https://www.protrack365.com/` sí regresa datos reales (`records[]` con `deviceid`, `lat`, `lng`, `speed`, `gpstime` epoch ms). Probado con un token de sesión capturado por Armando desde el Network tab del navegador (13-jul ~15:41 hora MTY, no guardado en ningún archivo — solo quedó en el historial de chat de esa sesión de Claude, no en el repo ni en `.env`): confirmó 2 unidades reales, `deviceid` 1525959 detenida en la planta (25.693151,-100.480343 — coincide con la dirección de Templadora Noreste en Santa Catarina) y `deviceid` 1550184 detenida en zona San Nicolás de los Garza (25.739473,-100.279410). Riesgo de este método alterno: el token es de sesión web (no Open API), expira sin aviso y hay que recapturarlo a mano — por eso NO se implementó nada con él, solo se dejó documentado como fallback si el distribuidor tarda en habilitar el permiso oficial. **Pendiente sin resolver:** no hay correspondencia confirmada entre los `deviceid` (1525959/1550184, vistos por el método alterno) y los IMEI gris/blanca (868166056885908/868166056884737, en `.env`/método oficial) — falta cruzar cuál unidad es cuál para cuando se conecte el dato al sistema. **Camino recomendado para terminar el desarrollo** (ver sección 12): (1) pedir al distribuidor de ProTrack habilitar Open API para la cuenta `vitrotempladomty` — en cuanto responda `code:0` en vez de `10007`, `api/gps_proxy.php` funciona sin tocar una línea; (2) decidir si se consulta en vivo cada vez que se abre Logística Rutas o si se guarda histórico en tabla nueva (ej. `gps_posiciones`) vía cron; (3) conectar al frontend de `app/modulos/logistica_rutas.php`. Archivos tocados esta sesión: ninguno (solo lectura/pruebas contra APIs externas y BD). |

| UPD-328 | 13-jul-2026 | Mando | GPS ProTrack365 (continuación de UPD-327) — implementado el método alterno como fallback automático en `api/gps_proxy.php`, a petición explícita de Mando pese a que UPD-327 lo había descartado por el riesgo del token manual: la diferencia clave es que aquí el login completo se automatiza en el servidor (no se guarda ningún token capturado a mano) — `getWebToken()` hace GET home (JSESSIONID) → POST `/LoginService?method=login` (mismas credenciales de `.env`) → GET `/V2/index.jsp` (el HTML ya trae el token de sesión embebido, generado server-side) → cachea 20 min en sesión PHP; `getWebUbicaciones()` llama `real.gpscenter.xyz/LocationService?method=customerDeviceAndGpsone` (JSONP, se parsea quitando el wrapper) que además de lat/lng/speed regresa `imei`+`devicename` directo, confirmando el mapeo gris="NISSAN GRIS"/blanca="NP TN - BLANCA" contra `PROTRACK_IMEI_GRIS`/`PROTRACK_IMEI_BLANCA` ya existentes. `accion=ubicacion` ahora prueba primero la Open API oficial (como ya hacía) y cae automáticamente a este método si sigue en `permission denied` — transparente para quien consuma el endpoint, incluye `"fuente":"open_api"|"web_fallback"` en la respuesta. En cuanto el distribuidor habilite la Open API, el fallback deja de usarse solo (sin tocar código). Verificado end-to-end contra el servidor real de ProTrack365 (solo lectura, mismas credenciales ya en `.env`): login + token + las 2 unidades reales correctamente identificadas. Pendiente: (1) decidir si Logística Rutas consulta en vivo o guarda histórico en `gps_posiciones` + cron, (2) conectar al frontend de `app/modulos/logistica_rutas.php` (sin cambios aún), (3) monitorear si el fallback se rompe cuando ProTrack365 cambie su web (es un endpoint no documentado). Archivo: api/gps_proxy.php |

| UPD-329 | 13-jul-2026 | Mando | GPS ProTrack365 (continuación de UPD-328) — Mando aclaró que la ubicación en vivo la van a ver también los CLIENTES en el portal, no solo staff interno, así que el caché por `$_SESSION` de UPD-328 no servía (cada cliente/pestaña forzaría su propio login). Refactor: lógica movida de `api/gps_proxy.php` a nueva librería compartida `api/gps_lib.php`, con caché en ARCHIVO (no sesión) — posición cacheada 12s en `sys_get_temp_dir()/apex_gps_pos.json`, tokens (oficial y web) cacheados igual en `apex_gps_token.json` (110 min / 20 min), protegido con `flock()` para que si varias pestañas piden al mismo tiempo tras vencer el caché, solo UN proceso haga el login real y los demás usen ese resultado (sin duplicar logins contra ProTrack). Nuevo endpoint `api/portal_gps.php` para el Portal Clientes — a propósito NO reusa `gps_proxy.php` (ese sigue siendo solo para staff interno, ve la flota completa): busca si el cliente autenticado (`$_SESSION['portal_cliente_id']`) tiene una entrega en una ruta `estado='en_ruta'` (JOIN `ruta_entregas`→`rutas`→`ordenes` por `cliente_id`), y solo si existe regresa lat/lng/velocidad/tiempo de la unidad asignada — nunca IMEI, nombre de dispositivo, batería, ni la otra unidad. Sin entrega en curso, o sin permiso, responde `disponible:false` sin dar pistas de por qué. Verificado: `php84 -l` en los 3 archivos; caché real contra ProTrack (1a llamada 3.4s vía fallback web, 2a instantánea desde caché); 2 procesos simultáneos tras vencer el caché no dispararon login duplicado (~0.9s cada uno, ambos con datos válidos); query de `portal_gps.php` corrida contra la BD real (0 filas esperado, sin cliente de prueba con entrega en curso — hay 1 ruta real `en_ruta` ahora mismo pero no se probó con ese cliente real para no exponer sus datos). Pendiente: conectar al frontend de `app/modulos/logistica_rutas.php` (staff) y a `portal/dashboard.php` (mapa del cliente) — ningún frontend tocado todavía. Archivos: api/gps_lib.php (nuevo), api/gps_proxy.php, api/portal_gps.php (nuevo) |

| UPD-330 | 13-jul-2026 | Mando | Logística Rutas: marcador 🚚 en vivo por ruta `en_ruta`, refrescado c/15s vía `api/gps_proxy.php`. Fix real aparte: Google Maps llevaba semanas roto (sin error visible) porque `GOOGLE_MAPS_KEY`/`SERVER_KEY` estaban vacíos en el `.env` real — se encontró la llave correcta en un `.env` suelto y huérfano dentro del webroot (confirmado con Armando), se restauró en el `.env` real y se borró el archivo suelto. Archivos: app/modulos/logistica_rutas.php, .env |
| UPD-331 | 13-jul-2026 | Mando | Logística Rutas: botón 🗑️ para borrar una ruta completa (`accion=eliminar_ruta`). Bloqueado si ya tiene entregas marcadas `entregado`/`no_entregado`, para no perder histórico real. Archivos: api/rutas.php, app/modulos/logistica_rutas.php |
| UPD-332 | 13-jul-2026 | Mando | Logística Rutas GPS (fixes tras pruebas de Mando): (1) pin de planta corregido — coordenada hardcodeada estaba mal (a varios km de la real), ahora usa la misma ubicación exacta de UPD-266 (C. de la Industria 214, Marfer) + ícono de fábrica 🏭 en vez del círculo azul genérico. (2) Timer visual "📡 Actualiza cada 15s · próxima en Ns" bajo el mapa de cada ruta `en_ruta`. Fixes de infraestructura encontrados en el camino (no de código): CSP bloqueaba `places.googleapis.com` (agregado a connect-src en .htaccess); llave de servidor con restricción de IP vieja (de HostGator) causaba error en "Planificar" — Mando actualizó la IP en Google Cloud Console; Directions/Geocoding API no estaban activadas — Mando las activó. Archivo: app/modulos/logistica_rutas.php |
| UPD-333 | 13-jul-2026 | Mando | Fix real en autocompletado de direcciones (Logística Rutas): la colonia no se autorellenaba porque el código solo buscaba el tipo `sublocality_level_1` de Google — muchas direcciones de México regresan la colonia como `sublocality` o `neighborhood` en su lugar. Ahora prueba los 3 tipos en orden de preferencia (mismo criterio para ciudad con `locality`/`administrative_area_level_2`). Archivo: app/modulos/logistica_rutas.php |
| UPD-334 | 13-jul-2026 | Mando | Fix real, causa raíz de UPD-333 seguía sin funcionar: el listener `gmp-select` leía `e.oh.toPlace()` — `oh` es un alias interno minificado de una build vieja de la librería (no documentado por Google), que dejó de existir con la versión actual (`v=beta`). Cambiado al nombre oficial `e.placePrediction.toPlace()`, con fallback a `e.oh` por si acaso. Archivo: app/modulos/logistica_rutas.php |
| UPD-335 | 14-jul-2026 | Mando | Autocomplete de direcciones: fix carga duplicada del script de Maps al navegar (rompía teclas en Brave) + fix cajas de búsqueda apiladas al reabrir el modal de asignar. Probada API clásica como alternativa, revertida (no está activada en el proyecto). Archivo: app/modulos/logistica_rutas.php |
| UPD-336 | 14-jul-2026 | Mando | Autocomplete restringido a Nuevo León/Tamaulipas/Coahuila (`locationRestriction` + validación de estado al seleccionar). Fix: `componentRestrictions` no aplica en el componente nuevo de Google, corregido a `includedRegionCodes` (por eso seguían saliendo direcciones de EE.UU.). Archivo: app/modulos/logistica_rutas.php |
| UPD-337 | 14-jul-2026 | Mando | Optimizador de rutas: compara tiempo antes/después de optimizar (`antes_min`/`ahorro_min`), suma 15 min de tolerancia por parada para descarga, y regresa desglose tiempo/km por tramo. Archivos: api/rutas.php, app/modulos/logistica_rutas.php |
| UPD-338 | 14-jul-2026 | Mando | Mapa de rutas: líneas de colores por tramo (paleta de 20) mientras se planea; una vez iniciada la ruta (`en_ruta`) cambia a una sola línea del camión (GPS en vivo) hacia la siguiente parada, avanzando sola al detectar llegada (radio 250m). Archivo: app/modulos/logistica_rutas.php |
| UPD-339 | 14-jul-2026 | Mando | Trazabilidad GPS server-side: tabla `gps_posiciones` + columnas `lat`/`lng`/`llegada_gps_at`/`movimiento_iniciado_at` en `ruta_entregas` (permiso explícito) + cron nuevo `scripts/gps_tracker.php` (cada minuto) que graba posición, detecta llegada y movimiento tras escaneo QR, independiente del navegador. |
| UPD-340 | 14-jul-2026 | Mando | Nueva pestaña "Rutas de Entrega" en Productividad: tabla Orden/Cliente/Unidad/Chofer/Salida QR/Llegada GPS/Tiempo muerto/Entregado. Archivos: api/productividad.php, app/modulos/productividad.php |
| UPD-341 | 14-jul-2026 | Mando | dir_admin ahora puede ver y usar los módulos marcados WIP (Facturación, Rutas de Entrega) — antes solo `desarrollo`. Archivos: api/permisos.php, app/dashboard.php |
| UPD-342 | 14-jul-2026 | Mando | Entregas parciales visibles en Rutas de Entrega: badge "Parcial X/Y" (jala de `orden_salidas`, ya existente) en lista y mapa. Fix real: al confirmar una salida (parcial o completa) desde Cobranza, la parada en `ruta_entregas` se quedaba pegada en `pendiente` para siempre — ahora se cierra sola, liberando la orden para "Pendientes de asignar". Archivos: api/rutas.php, api/salidas.php, app/modulos/logistica_rutas.php |
| UPD-343 | 14-jul-2026 | Armando | Fix real en el wizard de Nueva Campaña (`app/modulos/campanas.php`), a raíz de que Armando reportó que la campaña con template `016_proyectos_magnos` mostraba la razón social del cliente en lugar del nombre del contacto en la vista previa. El envío real (`api/campanas.php`, `accion=crear`) ya priorizaba `contacto` sobre razón social desde antes (`nombreCampanaCorto()`, UPD-265) — el bug estaba solo en el frontend del wizard: `clientes_segmento` sí regresa el campo `contacto`, pero `cmpFiltrarClientes()` lo descartaba y solo guardaba `nombre` (razón social) en `_clientesMap`/`_clientesSeleccionados`, por lo que el "Paso 3: Mensaje (preview)" y la vista previa en vivo del Paso 2 siempre sustituían `{{nombre_cliente}}` con la razón social, sin reflejar lo que realmente se iba a enviar. Fix: `contacto` ahora viaja completo desde la selección de audiencia hasta el preview; nueva función JS `nombreEnvioPreview()` replica en el navegador el mismo criterio del backend (prioriza contacto, primeras 2 palabras, Title Case, fallback a razón social solo si el cliente no tiene contacto capturado). Sin cambios de backend — la campaña `016_proyectos_magnos` aún no se había creado en BD (verificado con SELECT, 0 filas), así que no hubo datos que corregir, solo el preview antes de crearla. Verificado con `php84 -l` + `node --check` del bloque `<script>` extraído. Pendiente informativo (no bloqueante): si algún cliente del segmento de esta campaña tiene `contacto` vacío en el CRM, para ese caso seguirá cayendo a razón social por diseño — falta que Armando revise el segmento antes de enviar. Archivo: app/modulos/campanas.php |

| UPD-344 | 14-jul-2026 | Mando | Rutas de Entrega: (1) Radio de "llegada GPS" subido de 250m a 300m (ver pendiente de UPD-338/339, primera prueba real quedó a 268m sin disparar) en `scripts/gps_tracker.php` y `app/modulos/logistica_rutas.php`. (2) Nuevo "Tiempo Estimado" al iniciar una ruta: `api/rutas.php` calcula con Google Routes API el tiempo Planta→siguiente parada pendiente (+15 min de tolerancia por parada, mismo criterio que el optimizador de UPD-337) y lo guarda en columna nueva `ruta_entregas.eta_min` (permiso explícito) — se muestra como línea "Tiempo Estimado: X min/h" debajo del botón "⏱️ Recalcular tiempo estimado" en cada tarjeta de unidad (ajustado de "ETA" a este texto a petición de Armando, ningún texto visible dice ya "ETA"). Se recalcula: al presionar "Iniciar Ruta", con el botón manual, y **automáticamente cuando el chofer marca una entrega como `entregado`** (`accion=marcar_estado` en api/rutas.php) — así la cuenta de la siguiente parada ya no sigue sumando el tramo que se acaba de completar. Backfill aplicado a la ruta 18 (ya estaba `en_ruta` desde antes del fix, sin este dato). Nota de diseño: el tiempo mostrado es el acumulado desde Planta hasta la parada pendiente en turno (no se re-basa "desde donde va el camión ahora" salvo que se recalcule). Archivos: scripts/gps_tracker.php, app/modulos/logistica_rutas.php, api/rutas.php |

| UPD-345 | 16-jul-2026 | Mando | Nuevo QR de "hoja de ruta" (`app/imprimir_ruta.php`, nuevo — una sección por parada con QR propio) para cuando el chofer sale hacia cada cliente, separado del QR de la remisión (UPD-319) que ahora solo marca piezas cargadas al camión en planta. `api/salidas.php`: `scan_qr` (remisión, carga) vs `scan_qr_ruta` (hoja de ruta, dispara aviso WA en_ruta/siguiente_entrega). Si al cargar la última pieza ya quedó todo cargado, la ruta arranca sola (`en_ruta` + ETA) sin necesitar el botón "Iniciar Ruta". Archivos: app/imprimir_ruta.php, api/salidas.php, app/operador.php |
| UPD-346 | 16-jul-2026 | Mando | `api/rutas_lib.php` (nuevo): helpers `computeRouteGoogle()`/`calcularYGuardarEtas()` extraídos de `api/rutas.php` para reusarlos también en el arranque automático de ruta de UPD-345 (`api/salidas.php`). `scripts/gps_tracker.php`: coordenadas de planta como constantes `PLANTA_LAT`/`PLANTA_LNG` (mismo valor de UPD-332). |
| UPD-347 | 16-jul-2026 | Mando | Rutas de Entrega deja de ser WIP: quitado badge "WIP" y candado dir_admin/desarrollo en `app/dashboard.php` — ahora también accesible a `comercial`. Nueva columna `ordenes.requiere_ruta`: al cerrar una orden tipo `chofer` (domicilio) se marca para que aparezca en "Pendientes de asignar" de Rutas; tipo `recoleccion` no la requiere. Archivos: app/dashboard.php, api/salidas.php |
| UPD-348 | 16-jul-2026 | Mando | Fix `app/imprimir_salida.php`: el tipo de entrega mostrado (chofer/recolección) ahora usa el tipo real ya registrado en `orden_salidas` si existe, en vez de la preferencia original de la cotización — antes, al reabrir la remisión después de confirmar la salida, mostraba lo cotizado en vez de lo realmente registrado. |

| UPD-349 | 17-jul-2026 | Mando | Productividad → "Rutas de Entrega": botón "📍 Ver recorrido" por ruta abre modal con mapa — pines numerados de las paradas en el orden ya optimizado + polyline animada con el trazo GPS real del chofer (play/pause, slider, velocidad 1x/4x/10x/30x). Reusa `ruta_entregas.lat/lng` ya cacheado y lee el track de `gps_posiciones` (propia BD) — sin geocoding ni Routes API nuevos, solo 1 Dynamic Maps load por apertura del modal (mismo costo que ya paga Logística Rutas). Nuevo `vista=ruta_replay` en `api/productividad.php`. Verificado con `php84 -l` + balance de llaves del bloque `<script>` (sin `node` disponible en esta sesión); falta verificación visual en navegador real. Archivos: api/productividad.php, app/modulos/productividad.php |

| UPD-350 | 17-jul-2026 | Armando | Campañas WA — buscador de conversaciones: caja de búsqueda fija arriba de la lista en la pestaña Conversaciones (`?m=campanas`), a petición de Armando porque la lista se corre hacia abajo con mucha actividad y cuesta ubicar a un cliente. Filtra en vivo (sin recargar del servidor) por nombre del cliente o por teléfono (cualquier fragmento de dígitos, ignora espacios/formato). `.conv-lista` pasó de bloque con scroll único a columna flex (barra de búsqueda fija + `.conv-lista-items` con su propio scroll). JS: se cachea la respuesta de `accion=conversaciones` en `_convDataAll` y se separó el render en `renderConvLista()` (antes iba inline dentro del `.then()` del fetch) para poder re-filtrar sin pegarle al servidor; el polling silencioso de 15s (UPD-312) sigue actualizando `_convDataAll` y respeta el texto de búsqueda ya escrito. El badge de "sin leer" del tab sigue contando el total real, no lo filtrado. Verificado con `php84 -l` + `node --check` del bloque `<script>` extraído (tags PHP stubeados). Archivo: app/modulos/campanas.php |

| UPD-351 | 17-jul-2026 | Armando | Herramienta de video para marketing (Remotion) — instalación exploratoria a petición de Armando, sin integrar todavía a ninguna campaña real: quiere probar video corto (15seg) en vez de imagen estática en Campañas WhatsApp, porque convierte mejor. Infra: (1) `ffmpeg` no estaba instalado en el VPS y EPEL no lo trae completo por licenciamiento — se agregó el repo RPM Fusion Free y se instaló `ffmpeg` 5.1.10 con `--allowerasing` (reemplazó 4 libs `-free` de EPEL — `libavcodec/avformat/avutil/swresample-free` — instaladas 16-jun-2026 sin nada que las requiriera hoy, confirmado con `rpm -q --whatrequires` antes de tocarlas). (2) Proyecto Remotion nuevo en `/home/apexglass2025/herramientas/video-marketing/` (`npx create-video@latest --hello-world`), **fuera del webroot** a propósito — no es parte de la app PHP, no se sirve ni se toca desde `produccion/`. (3) Se instalaron los 9 Agent Skills oficiales de `remotion-dev/skills` (`npx skills add remotion-dev/skills`) para que Claude sepa las buenas prácticas de Remotion al armar videos ahí. Benchmark real (motivo: este VPS es de producción, 2 vCPU compartidos con Apache/PHP-FPM/MariaDB — se midió antes de decidir si convenía): video 1920x1080 de 5s (150 frames) tardó 57s la primera vez (incluye descarga única del Chrome headless propio de Remotion, ~200MB) y 27s la segunda (estado estable) — proyección lineal ~80-90s para un clip de 15s. Apache/PHP-FPM/MariaDB confirmados `active` sin interrupción durante ambos renders. Carpeta final 679MB (case casi todo `node_modules`), dueño `apexglass2025:apexglass2025` (no root), archivos de prueba (`out/`) borrados al terminar. **Nada de esto está conectado a Campañas WhatsApp todavía** — falta: (a) definir/diseñar el primer video real de marketing con Armando, (b) revisar si `app/modulos/campanas.php` necesita soporte para plantillas Meta con header tipo VIDEO (hoy el wizard solo maneja `header_image_url`, ver UPD-289/313 — los videos de WA Business también van como plantilla con media de ejemplo subida a Meta, mismo patrón que las imágenes pero sin probar aún). Archivos: ninguno de la app tocado — solo infraestructura del VPS (`dnf`) y carpeta nueva fuera de `produccion/`. |

| UPD-352 | 17-jul-2026 | Armando | Herramienta de video para marketing (continuación de UPD-351) — 2 videos de prueba mostrando el Portal de Clientes "en función", a petición de Armando. (1) `PortalDemo` (escritorio, 1920x1080, 22s/660 frames): login → mis órdenes → detalle de orden → CTA. (2) `PortalDemoMobile` (vertical 1080x1920, mismo guion): mismo recorrido pero dentro de un marco de teléfono genérico en CSS (isla dinámica, botones laterales — sin usar ninguna marca de teléfono real), usando la vista de **tarjetas** real del portal móvil (`cards-list`/`orden-card` de `portal/dashboard.php`), no la tabla de escritorio encogida. Metodología: la pantalla de login es una **captura real** del portal (`portal/index.php`, sin sesión, sin datos de cliente — capturada con Playwright vía `playwright-core` + el `chromium-browser` del sistema, ya que el MCP de Playwright configurado en `~/.claude/settings.json` no está disponible en sesiones de Claude Code de este VPS). El dashboard y detalle de orden **no son screenshots de datos reales** — se evaluó usar la cuenta de prueba real CTN-259 (`clientes.id=47`, ya tiene `portal_activo=1` y password de prueba guardado en claro en `portal_password` para referencia del admin) pero sus órdenes históricas son datos de prueba con totales de centavos ($0.06–$0.10) que se verían rotos en video; se descartó insertar pedidos "bonitos" temporales en producción (hubiera requerido autorización de escritura en BD) y en su lugar se recrearon las pantallas en React/Remotion con el CSS/tokens exactos del portal real (`--amber #F5A623`, `--bg #F0F1F3`, fuentes Outfit/Syncopate) y un cliente/pedido 100% ficticio ("Cliente Ejemplo", folio S-241). Bug de Remotion encontrado y corregido: un `<div>` con `maxWidth` + `margin:"0 auto"` dentro de un `AbsoluteFill` (que por defecto es `display:flex;flexDirection:column`) se encoge a *fit-content* en vez de expandirse al ancho máximo — los márgenes automáticos desactivan el `align-items:stretch` de flexbox; fix: agregar `width:"100%"` explícito junto al `maxWidth`. Nuevo patrón para compartir estos videos con Armando: página HTML autocontenida (Artifact) con el `.mp4` embebido como `data:` URI en un `<video>`, más un botón "Descargar MP4" que dispara la descarga leyendo `document.querySelector('video source').src` (evita duplicar el blob base64 en el HTML). Archivos nuevos: `src/PortalDemo/*` (tokens, fonts, demoData, Cursor, LoginScene, DashboardScene, OrderScene, CTAScene, index — todos en `herramientas/video-marketing/`, fuera del webroot), `src/PortalDemoMobile/*` (PhoneFrame, MobileLoginScene, MobileDashboardScene, MobileOrderScene, index), `public/portal-login.png` y `public/portal-login-mobile.png` (screenshots reales del login, sin datos de cliente). Sin cambios en la app de producción — solo la carpeta de herramientas. Pendiente: nada de esto está conectado a una campaña real de WhatsApp todavía (ver pendiente en sección 12); Armando está evaluando los 2 videos de muestra. |

**Próximo UPD disponible: UPD-353**
