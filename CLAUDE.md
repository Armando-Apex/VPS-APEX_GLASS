# APEX GLASS — MEMORIA ÚNICA DEL PROYECTO
# Sistema de Rastreo de Producción (Templadora Noreste, S.A. de C.V.)
# Última actualización: 06 agosto 2026 | Próximo UPD disponible: UPD-472

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
| Contabilidad — Catálogo de Cuentas (WIP) | modulos/contabilidad_catalogo.php | ModCatalogoContable | Armando |

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

---

## 13. HISTORIAL DE ACTUALIZACIONES

REGLA: Cada cambio se agrega aquí. NUNCA se elimina. Código UPD secuencial e irrepetible.
Próximo UPD disponible: **UPD-424**

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

## 14. PROTOCOLO PARA CADA SESIÓN

Al terminar cualquier sesión con cambios:
1. Subir archivos modificados a Drive (`ARCHIVOS SERVIDOR/`)
2. Registrar el cambio con próximo UPD en este archivo
3. Las tareas completadas se marcan HECHO — NUNCA se borran

### Bloque actual: UPD-398 en adelante

| Código | Fecha | Resp. | Descripción |
|---|---|---|---|
| UPD-398 | 27-jul-2026 | Mando | Fix lista Cotizaciones mostraba TODAS las maquilas en $0.00 (bug de `auditoria_e2e_v3.md` V3-C1): la query solo sumaba `cotizaciones_partidas`, tabla vacía para maquila. `api/cotizaciones.php` ahora ramifica por `tipo='maquila'` usando `c.total`, mismo patrón ya usado en `api/finanzas.php` (UPD-302). Verificado con COT-0496: $0.00→$107.65. |
| UPD-399 | 27-jul-2026 | Mando | Fix: editar una cotización con anticipo ya cobrado reseteaba el saldo pendiente al total/50% completo, ignorando lo cobrado (`api/cotizaciones.php:697`). Ahora resta `saldo_pagado` (`max(0, saldo_base - saldo_pagado)`), mismo criterio que ya usa `api/correcciones.php` (A-10). |
| UPD-400 | 27-jul-2026 | Mando | Fix: Portal Clientes mostraba $0.00 en cotizaciones de maquila (mismo bug raíz de UPD-398, visible al cliente). `portal/cotizacion.php` calcula el total desde `c.total` cuando `tipo='maquila'` y muestra una nota en vez de la tabla de partidas vacía (esa tabla no aplica a maquila, que no usa `cotizaciones_partidas`). |
| UPD-401 | 27-jul-2026 | Mando | Fix: cancelar una orden desde Cotizaciones no revisaba si tenía parada pendiente en una ruta `en_ruta` — el chofer la entregaba igual y la orden cancelada "resucitaba" `entregada` (dinero en monedero + producto entregado). Agregado el mismo candado que ya existía en `admin_ordenes.php`; y `rutas_lib.php` ya no marca `entregada` una orden que esté `cancelada`. Archivos: api/cotizaciones.php, api/rutas_lib.php |
| UPD-402 | 27-jul-2026 | Mando | Fix: retrabajar (`api/reproceso.php`) una pieza de una orden ya entregada/cancelada la regresaba a `pendiente` sin validar nada, pero ningún otro endpoint puede volver a moverla si la orden no está `activa` (A-4) — quedaba atascada para siempre. Ahora valida `orden.estado='activa'` y que la pieza venga de un estatus de producción real antes de aceptar el retrabajo. Verificado: sin piezas huérfanas existentes por este bug. |
| UPD-403 | 28-jul-2026 | Mando | Fix V3-C2 (`auditoria_e2e_v3.md`): el wizard de corte (`api/sesion_corte.php`) checaba stock de la lámina ANTES de `beginTransaction()`, sin `FOR UPDATE` — dos confirmaciones casi simultáneas de la misma lámina podían dejar stock en −1 permanente. Movido el chequeo dentro de la transacción con `SELECT ... FOR UPDATE` sobre `laminas`, mismo patrón A-3 que ya usa `api/inventario.php`. |
| UPD-404 | 28-jul-2026 | Mando | Fix V3-C5: marcar una parada de ruta como "No entregado" (`api/rutas.php`, `marcar_estado`) no validaba si sus piezas ya estaban `entregado` por otra vía (Cobranza, escaneo) — se detectaron 8 paradas reales así en producción (`pendiente` con piezas ya entregadas de órdenes viejas, S-235/254/318/319/306/295/322/350). Se agregó un candado que bloquea la acción con error claro en ese caso, en vez de reversar a ciegas entregas reales ya cerradas (que hubiera tocado histórico). Las 8 paradas desincronizadas existentes NO se tocaron — quedan pendientes de limpieza manual con tu autorización. |
| UPD-405 | 28-jul-2026 | Mando | Revisión de `auditoria_e2e_v3.md`: 2 hallazgos más resultaron ser falsos positivos (ya corregidos en rondas previas al snapshot del 24-jul, no se tocó nada) — V3-C7 (mojibake "año" en Reporte Dirección, ya en UTF-8 correcto con alias `anio`) y Alto#9 (doble timbrado CFDI, `api/facturapi.php` ya tiene reserva atómica `estatus='timbrando'` con `rowCount()` + abort en todo camino de falla, tag "A-9b"). V3-C3 (escaneo QR salta cobro) descartado por decisión de negocio, sin tocar código. |
| UPD-406 | 28-jul-2026 | Mando | Investigando las 8 paradas de ruta desincronizadas (ver UPD-404) se encontró que `entregado` en `piezas.estatus` significa "Cobranza ya imprimió la salida/remisión", no "llegó al cliente" (Alto#16 de la auditoría) — de ahí la confusión. Sin cambios de código por esto; a petición de Armando se agregaron 2 mejoras aparte: (1) columna "Ruta creada por" (`rutas.creado_por`) visible en Productividad → Rutas de Entrega, por grupo de ruta. (2) Campo "Chofer" en Logística Rutas → Nueva Ruta cambiado de texto libre a `<select>` con las 2 opciones reales (ROBERTO GARCIA, VICTOR BAUTISTA) — evita nombres mal escritos/inconsistentes. Archivos: api/productividad.php, app/modulos/productividad.php, app/modulos/logistica_rutas.php |
| UPD-407 | 28-jul-2026 | Mando | Verificación uno a uno de los 18 Altos de `auditoria_e2e_v3.md` contra código real: #2 (rutas sin validar pago) y #15 (pieza rechazada en domicilio) resultaron ya corregidos — 2 falsos positivos más. #1 (estatus_pago sin evidencia) matizado: sí hay bitácora automática, es más una decisión de negocio que un bug — sin tocar. #11 (estaciones.php SmartTV sin sesión) y #17 (OC "pagada" manual) confirmados reales pero saltados a petición explícita, sin implementar. |
| UPD-408 | 28-jul-2026 | Mando | Fix Alto#4: eliminar una partida (`api/correcciones.php`) no borraba sus servicios adicionales en `cotizacion_partida_servicios` ni recalculaba `servicios_subtotal` — se seguía cobrando el servicio de una partida ya eliminada. Ahora se borran y `servicios_subtotal` se recalcula desde lo que realmente queda. |
| UPD-409 | 28-jul-2026 | Mando | Fix Alto#7: restaurar una orden cancelada (`api/admin_ordenes.php`) dejaba la cotización ligada en `estatus='cancelada'` — Finanzas nunca puede registrar pago sobre una cotización cancelada, así que la orden restaurada quedaba viva pero incobrable. Ahora re-vincula la cotización (`estatus='orden'`) al restaurar; si ya se había movido dinero a saldo a favor por la cancelación, se bloquea el restore con instrucción de aplicar ese saldo manualmente en vez de re-cobrar solo o dejarlo huérfano — el dinero movido no se toca automático (tema ligado al rediseño de Saldo a Favor aún en discusión, sección 12). |
| UPD-410 | 28-jul-2026 | Mando | Fix Alto#8: recepción parcial de una OC de flete (`api/ordenes_compra.php`) recalculaba el flete a distribuir con la `cantidad` TOTAL ordenada en cada entrega — 2 entregas parciales de la misma OC de flete duplicaban `costo_flete_total` sobre las láminas de la OC de vidrio vinculada. Ahora distribuye solo el importe proporcional a lo recibido en esa entrega específica (`precio_unitario × cantidad recibida`), sin afectar el caso legítimo de 2 OCs de flete DISTINTAS que sí deben sumarse. |
| UPD-411 | 28-jul-2026 | Mando | Fix Alto#10: el wizard de corte (`api/sesion_corte.php`) no filtraba `piezas.requiere_corte` — una pieza de maquila que no necesita corte (cliente trae su vidrio ya cortado) podía forzarse a `cortado` igual, pero `actualizar_estatus.php` arma su ruta dinámica SIN ese paso para esa pieza, dejándola atascada para siempre (ningún endpoint la reconoce después). Ahora se rechaza esa pieza en el wizard con error claro, mismo criterio que ya usa para tipo/espesor incorrecto. |
| UPD-412 | 28-jul-2026 | Mando | Fix Alto#14: convertir cotización a orden (`api/cotizaciones.php`, `accion=convertir_orden`) tenía el chequeo de estatus fuera de la transacción — 2 clics casi simultáneos podían crear 2 órdenes reales con QR duplicados para la misma cotización. Agregado claim atómico (`SELECT ... FOR UPDATE` + re-chequeo de estatus) al entrar a la transacción, mismo patrón ya usado en el timbrado de facturas (A-9b). |
| UPD-413 | 28-jul-2026 | Mando | Fix Alto#18: cancelar una cotización de maquila (`api/maquila.php`, `accion=cancelar`) era un `UPDATE` crudo sin ningún candado — a diferencia de cancelar suministro, no validaba doble cancelación, orden ya entregada, ni ruta en curso, y no movía el dinero cobrado a saldo a favor (quedaba huérfano en la cotización cancelada). Aplicados los mismos candados que ya existen en `api/cotizaciones.php` (V3-C4/C-13). La parte de "filo_muerto cobra $0 con CPB=No" del hallazgo original NO era un bug — CPB=No significa Filo Muerto, correcto que no cobre canteado si no se trabajó. |
| UPD-414 | 30-jul-2026 | Armando | Fix impresión cotización (suministro, ej. COT id=905): la columna "Subtotal" por partida en `app/imprimir_cotizacion.php` mostraba `cotizaciones_partidas.subtotal` (guardado con el descuento YA aplicado desde `api/cotizaciones.php:473`), pero la columna "Precio/m²" mostraba `precio_m2_usado` bruto (catálogo, sin descuento) — el cliente multiplicaba m² × precio/m² y no le daba el subtotal de la fila. Verificado en BD: partida 1 de COT-905 (5% desc.) bruto=$1,217.36 vs subtotal guardado=$1,156.49. Fix: la fila ahora recalcula bruto (`precio_m2_usado × m2 × cantidad`), consistente con la fórmula canónica del proyecto (sección "Precio en cotizaciones_partidas") y con el "Subtotal" del bloque de totales (que ya era bruto, con el descuento aplicado una sola vez al final). Portal Clientes (`portal/cotizacion.php`) y maquila ya calculaban bruto correctamente, no tenían este bug. |

| UPD-415 | 30-jul-2026 | Armando | Revisión de seguridad automática detectó 2 hallazgos tras el auto-commit `b3dc5bb`: (1) XSS en `app/modulos/contabilidad_catalogo.php` — `renderTabla()`/`renderSelectPadre()` concatenaban `c.codigo`/`c.nombre` sin escapar en `innerHTML` (mismo patrón pendiente de UPD-275 para `cotizacion.php`); agregado helper `esc()` y aplicado en ambos renders. (2) `scripts/gps_cache/apex_gps_token.json` (token de sesión web de ProTrack365) quedó versionado y subido a GitHub por el hook de auto-commit; verificado que el token ya había expirado al detectarlo (no explotable) y que las credenciales reales (`PROTRACK_ACCOUNT`/`PROTRACK_PASSWORD`) están en `.env`, fuera del repo, sin filtrarse. Agregado `scripts/gps_cache/` a `.gitignore` + `git rm --cached` para que no se repita. Armando decidió no purgar el historial de git (repo privado, dato ya no explotable). Aviso dejado para Mando en Pendientes Activos (sección 12) para que confirme que el tracker GPS sigue funcionando normal sin el cache trackeado. |

| UPD-416 | 30-jul-2026 | Armando | Fix de seguimiento a UPD-414 (COT-907): el fix anterior mostraba Subtotal por partida como `precio_m2_usado × round(m2×cantidad, 4)` — ese redondeo intermedio de m² a 4 decimales desfasaba centavos vs el Subtotal general (que sí usa m2 completo sin redondear) en medidas cuyo m²×cantidad termina en x.xxxx50 (ej. partida 7 de COT-907: mostraba $210.82, el bruto real es $210.78). Fix: (1) el bruto por fila ahora se calcula UNA sola vez con precisión completa (`precio_m2_usado × m2 × cantidad`, sin redondear m² a medio camino) y se reutiliza igual en la tabla; (2) el Subtotal general ahora es la suma exacta de esos brutos por fila (antes se recalculaba aparte con `round(SUM(...))`, lo que podía diferir 1 centavo del total de sumar las filas a mano); (3) columna m² ahora se imprime con `fmtM2Exacto()` — recorta ceros sobrantes pero conserva hasta 6 decimales (precisión real de la columna `m2 DECIMAL(10,6)`, exacta porque viene de mm enteros ÷1000) en vez de truncar siempre a 3, para que precio/m² × m² mostrados multipliquen exacto al centavo. Verificado con las 11 partidas reales de COT-907: las 11 cuadran exacto y suman $20,932.86 = Subtotal mostrado. |

| UPD-417 | 30-jul-2026 | Armando | Documentación (sin cambio de código): módulo Contabilidad — Catálogo de Cuentas (`app/modulos/contabilidad_catalogo.php` + `api/contabilidad_catalogo.php` + tabla `cuentas_contables`) no tenía entrada previa en CLAUDE.md pese a ya estar visible en el sidebar con badge WIP. Registrado en sección 5 (Módulos SPA) y sección 12 (Pendientes): es solo el plan de cuentas (13 cuentas, 5 categorías raíz) de un proyecto de Estado de Resultados (P&L) que aún no tiene módulo de movimientos ni reporte — no conectado a ningún dato real del sistema todavía. |

| UPD-418 | 31-jul-2026 | Armando | UX: el panel de chat de Campañas WhatsApp (`app/modulos/campanas.php`, `.conv-panel`) tenía altura fija `520px` sin importar el tamaño de pantalla — Armando reportó que se veía como ~60% del alto disponible. Cambiado a `height:calc(100vh - var(--topbar-h) - 160px)` con `min-height:460px` de piso; `.conv-lista`/`.conv-chat` ya heredan la altura por flexbox (`align-items:stretch` implícito), no necesitaron cambio. El offset de 160px es estimado a partir del CSS (padding del wrapper + fila de título + fila de tabs), sin verificación visual en navegador (no había Chrome DevTools MCP conectado en la sesión) — pendiente que Armando confirme que se ve bien y no corta contenido en pantallas chicas. Sin cambio en el breakpoint móvil (`@media max-width:640px` ya fuerza `height:auto`, no se tocó). |

| UPD-419 | 31-jul-2026 | Armando | Fix orden de la bandeja de Campañas WhatsApp (`api/campanas.php`, `accion=conversaciones`): ordenaba solo por `ultima_actividad DESC`, así que una conversación sin leer se hundía en la lista en cuanto entraban mensajes nuevos en OTRAS conversaciones (reportado: contestas 4 de 5, llegan 8 más en otros chats, la sin leer queda en la posición 13, fuera de vista). Cambiado a `ORDER BY (mensajes_sin_leer > 0) DESC, ultima_actividad DESC` — las conversaciones sin leer siempre flotan arriba (ordenadas entre sí por más reciente), y bajan al grupo de leídas en cuanto se abren (`accion=marcar_leido` ya existente). No requirió cambios en el JS del módulo — `renderConvLista()` ya pinta la lista tal cual llega del API. |

| UPD-420 | 31-jul-2026 | Armando | Fix KPIs del módulo Compras (`?m=compras`): las tarjetas "Pagado" y "Por pagar" no funcionaban — siempre mostraban $0.00 y el total completo respectivamente. Causa: `app/modulos/compras.php` (`cmpRenderKpis`) suma `o.pagado_total` por OC, pero `api/ordenes_compra.php` (`accion=lista`) nunca regresaba ese campo. Agregada subconsulta independiente `(SELECT SUM(monto) FROM oc_pagos WHERE orden_compra_id=oc.id)` — a propósito NO como JOIN directo, para no repetir el fan-out partidas×pagos que ya se corrigió en calendario_pagos (C-8). "Total periodo" y "N° de OCs" ya estaban correctos (no tocados). Nota: "Total periodo" es una etiqueta engañosa — no hay selector de fechas real, es solo el total de lo que muestra la tabla en ese momento (tab + búsqueda + filtro estado), con tope de 200 OCs más recientes en el API (hoy hay 43 en total, no afecta todavía). Verificado con BD: $690,840.61 en 40 pagos reales ahora sí se reflejan. |

| UPD-421 | 31-jul-2026 | Armando | Corrección de datos (no código): borrados 3 pagos de OC duplicados en `oc_pagos` (ids 12, 17, 18) detectados al verificar el fix de UPD-420 — Armando confirmó explícitamente ("no es posible que tenga total periodo 132,477.09 y pagado de 206,299.88"). Detalle en Pendientes Activos (sección 12). Total pagado global: $690,840.61 (40 pagos) → $578,856.20 (37 pagos). Ejecutado con SELECT de verificación antes/después dentro de una transacción PDO, sin tocar ninguna otra tabla. |

| UPD-422 | 31-jul-2026 | Armando | **Apartado de Precio con vigencia** (sub-feature del rediseño de Depósito a Cuenta/Saldo a Favor, diseño discutido a fondo con Armando el mismo día, ver `project_apartado_precio_vigencia.md`): cliente deposita para congelar precio de productos específicos antes de un incremento. Es un solo saldo en dinero (no tope de cantidad por producto) repartible entre los productos amarrados, con garantía de precio que vence (≤45 días, nunca el dinero) — vigencia cuenta desde el día siguiente al depósito. Requiere VoBo del Director si la vigencia pedida es >7 días (activo directo si es ≤7). Tablas nuevas `saldo_favor_apartados` + `saldo_favor_apartado_items` (aditivas, no se tocó `clientes_saldo_favor` ni ningún dato existente). Nuevas acciones en `api/saldo_favor.php`: `crear_apartado`, `vobo_apartado`, `apartados_pendientes`, `apartados_cliente`, `apartado`. UI en `app/modulos/finanzas_cobranza.php` (checkbox "Apartar precio" en el modal de depósito + panel de VoBo pendiente solo para dir_admin + apartados visibles en el historial de cada cliente). Documento imprimible nuevo `app/imprimir_apartado.php` con leyenda obligatoria de "no reembolsable en efectivo, solo aplicable a productos" y banner de estatus (vigente/pendiente de VoBo/rechazado/vencido). Aplicación del precio pactado en cotizaciones reales sigue siendo MANUAL en v1 (el asesor consulta el apartado y teclea el precio) — automatizar detección/prellenado y ligar a facturación de anticipos quedan fuera de alcance por ahora. Probado con dry-run en BD dentro de una transacción con ROLLBACK (creación, cálculo de vigencia, aprobación de VoBo) antes de dar por buena la lógica — falta que Armando lo pruebe visualmente en el navegador. |

| UPD-423 | 31-jul-2026 | Armando | Fix "Fecha pedido" en Portal Clientes (reportado por Armando viendo S-435): mostraba la fecha de la **cotización** (`ordenes.fecha_pedido`, se llena con `cot.fecha` al convertir), no la fecha en que Lina dio el **VoBo** (`cotizaciones.vobo_at`) — Armando confirmó que debe ser la del VoBo. Cambiado solo en las 2 vistas del portal: `portal/dashboard.php` (lista, agregado `LEFT JOIN cotizaciones` + `COALESCE(DATE(c.vobo_at), o.fecha_pedido)`, mismo criterio y orden) y `api/orden.php` (agregado campo nuevo `fecha_pedido_portal` sin tocar `fecha_pedido` original, consumido por `portal/orden.php`). A propósito NO se tocó el valor interno `ordenes.fecha_pedido` ni las vistas internas del dashboard (`app/modulos/orden.php`, `admin_ordenes.php`, `app/orden.php` comparten el mismo `api/orden.php` pero siguen leyendo el campo original) — mismo criterio ya usado en `api/reporte_direccion.php` desde UPD-267/285/301/304/363/364, pero nunca se había aplicado al portal. Verificado con S-435: 22-jul (cotización) → 28-jul (VoBo real), consulta corrida contra la BD real antes de publicar. |

| UPD-424 | 31-jul-2026 | Mando | **Contabilidad/P&L — Fase 0 verificada + Fase 1 construida** (proyecto de Estado de Resultados, ver sección 12). Fase 0 (`cuentas_contables`) ya estaba completa, solo se confirmó contra la BD real. Fase 1: tabla nueva `cuenta_mapeo_reglas` (mapea `oc_partidas.tipo`/`ordenes_compra.categoria` → cuenta contable) + `api/contabilidad_mapeo.php` + módulo `app/modulos/contabilidad_mapeo.php` ("Mapeo Compras" en sidebar, junto a Contabilidad). Helper nuevo `api/helpers/pnl_datos.php` (`ingresosPeriodo`, `costoVentasPeriodo`, `costoVentasCobertura`) para uso futuro en el reporte de Fase 5. Costo de ventas se calcula por consumo real trazado desde `sesiones_corte`/`sesiones_corte_piezas` (wizard de corte, UPD-403) prorrateado por m² × costo promedio ponderado por lámina — **hallazgo importante**: el wizard arrancó apenas el 21-jul-2026, así que solo 27.5% de las piezas entregadas en julio tienen costo trazado (527 de 1917); cualquier mes antes de esa fecha saldría con margen falsamente alto. Decisión de Armando: el P&L (Fase 5) solo se considera confiable desde jul-2026 en adelante; `costoVentasCobertura()` ya trae un flag `confiable` (≥80% de piezas con costo trazado) para que el reporte pueda avisar cuando el rango pedido no cumple. De paso se corrigieron 2 bugs encontrados al probar en navegador: (1) `icono()` es de `api/helpers/icons.php`, pero los módulos SPA se sirven por fetch directo sin pasar por `dashboard.php` (que es el único que la importaba) — `contabilidad_catalogo.php` (Fase 0, nunca antes abierto en navegador) y el nuevo `contabilidad_mapeo.php` daban HTTP 500 por esto; se agregó el `require_once` en ambos módulos. (2) el rol `desarrollo` (usado para pruebas) no tenía `ver_contabilidad`/`gestionar_contabilidad` en `api/permisos.php` — agregado. Probado end-to-end: INSERT/SELECT/DELETE de prueba en `cuenta_mapeo_reglas` (limpiado después) y ambas queries de `pnl_datos.php` corridas contra datos reales de julio. |

| UPD-425 | 31-jul-2026 | Mando | **Contabilidad — navegación unificada a pestañas + Fase 2 (Nómina)**. A petición de Armando ("¿no sería mejor todo en un módulo centralizado?") se reemplazaron los botones sueltos de sidebar (Contabilidad + Mapeo Compras, UPD-424) por un solo botón "Contabilidad" → módulo contenedor nuevo `app/modulos/contabilidad.php` con pestañas (Catálogo de Cuentas / Mapeo Compras / Nómina) que cargan por fetch interno el contenido de cada sub-módulo — los archivos PHP de cada fase siguen siendo independientes por dentro (mismo aislamiento del proyecto), solo cambió la UI de navegación. Fase 2 (Nómina): tablas nuevas `nomina_empleados`, `nomina_pagos` y `movimientos_contables` (esta última es la bitácora compartida que también usarán Gastos Fijos y Caja Chica en Fases 3-4) + `api/nomina.php` + pestaña de captura mensual editable en `app/modulos/nomina.php`. Al guardar un pago se registra automáticamente en `movimientos_contables` contra la cuenta 6.1 Nómina del catálogo (upsert por empleado+periodo, sin duplicar si se vuelve a guardar el mismo mes). Probado: sintaxis PHP de los 5 archivos tocados/nuevos; el flujo INSERT/UPDATE de `api/nomina.php` no se probó con datos reales en BD a petición explícita del usuario (solo revisión de código) — pendiente de que Armando lo pruebe visualmente en el navegador antes de darlo por bueno. |

| UPD-426 | 31-jul-2026 | Mando | **Contabilidad — Fase 3 (Gastos Fijos)**. Tablas nuevas `gastos_fijos_conceptos` (catálogo de conceptos recurrentes: renta, luz, seguros, etc., cada uno ligado a una cuenta del catálogo) y `gastos_fijos_pagos` (registro mensual). Nueva pestaña "Gastos Fijos" en el módulo Contabilidad (checklist mensual: pendiente/pagado por concepto). Al guardar un pago se materializa en `movimientos_contables` (misma bitácora compartida de Fase 2) contra la cuenta del concepto. Mismo patrón de upsert por concepto+periodo que Nómina. Fix reportado por Armando en esta sesión: las pestañas de Contabilidad daban "error al cargar" porque el fetch interno de `app/modulos/contabilidad.php` apuntaba a rutas relativas sin el prefijo `modulos/` (ej. `contabilidad_catalogo.php` en vez de `modulos/contabilidad_catalogo.php`) — corregido, confirmado funcionando por Armando. Probado: sintaxis PHP de los 3 archivos; sin prueba de datos reales en BD (mismo criterio que Fase 2, pendiente de prueba visual). |

| UPD-427 | 31-jul-2026 | Armando | Fix "Pipeline Vigente" en Reporte Dirección (`api/reporte_direccion.php`) — Armando reportó ver $1,118,810 en el bloque "mes anterior" que ya no correspondían a nada real. Causa: la query sumaba TODAS las cotizaciones con `estatus='cotizacion'` sin filtrar por antigüedad ("vivas hoy sin importar cuándo se crearon", comentario original de UPD-267) — verificado en BD cotizaciones de hasta 52 días sin decisión del cliente seguían contando como pipeline abierto. Armando confirmó la regla real de la empresa: una cotización **vence a los 15 días naturales** desde su fecha de emisión (la leyenda de "3 días hábiles" en `imprimir_cotizacion.php` es aparte — vigencia de precio corta y temporal por un incremento de materia prima anunciado para el día siguiente, no la regla general). Agregado `AND c.fecha >= DATE_SUB(CURDATE(), INTERVAL 15 DAY)` al WHERE de `$stmtC` — no se creó estatus `vencida` ni se tocó el ENUM (alcance acordado: solo lectura para este KPI, no bloquear otros módulos). Verificado contra BD real: total pipeline vigente pasa de $4,319,354.54 (438 cotizaciones, incluía basura de meses) a $1,698,813.11 (171 cotizaciones realmente dentro de sus 15 días); el bloque "mes anterior" pasa de $1,118,810.45 a $0.00 (correcto — con vigencia de 15 días, prácticamente ninguna cotización de un mes calendario completo atrás puede seguir viva hoy). |

| UPD-428 | 31-jul-2026 | Armando | Rediseño de la tarjeta "Pipeline" en Reporte Dirección (`api/reporte_direccion.php` + `app/modulos/reporte_direccion.php`), a petición de Armando tras UPD-427: ya no muestra "mes anterior / mes actual" — ahora muestra **"Vigentes / Total del período"**. `pipeline_vigente` = cotizaciones sin convertir a orden que aún no cumplen los 15 días de vigencia (igual que UPD-427, independiente del selector de período). `pipeline_total_periodo` = TODAS las cotizaciones sin convertir del período seleccionado en el reporte (mismo `$desde`/`$hasta` que usa el resto de las tarjetas), **incluyendo las ya vencidas** — responde "cuánto se cotizó en el período" sin importar si ya caducó. De paso se corrigió una regresión propia de UPD-427: el filtro de 15 días se había puesto sobre el WHERE de toda la query, lo que también recortaba sin querer `total_cots`/`total_cotizado` (tarjeta "Pendientes", rotulada "cualquier fecha") y el desglose por asesor (Bethy/Cynthia) — esos 3 ya vuelven a ser sin filtro de antigüedad, como estaban antes de UPD-427. Verificado contra BD real: Pendientes regresa a $4,319,354.54 (438, cualquier fecha, correcto); Vigentes = $1,698,813.11; Total del período (julio) = $3,200,544.09. |

| UPD-429 | 31-jul-2026 | Armando | Fix columna "Cotizado (pipeline)" en "Rendimiento por asesor" (Reporte Dirección) — Armando pidió que sea "las del mes", pero `bethy_total`/`cynthia_total` en `api/reporte_direccion.php` sumaban TODO histórico sin filtro de fecha. Agregado `AND c.created_at BETWEEN ? AND ?` (mismo `$desde`/`$hasta` del selector de período del reporte) a ambos CASE — mismo criterio que `pipeline_total_periodo` de UPD-428 (incluye vencidas del período, filtra por cuándo se cotizó, no por vigencia). Verificado con BD real (período por defecto, julio): Bethy $2,399,213.19 → $1,753,034.46; Cynthia $1,576,964.90 → $1,133,279.18. No se tocó `total_cots`/`total_cotizado` (tarjeta "Pendientes", sigue sin filtro por diseño). |

| UPD-430 | 31-jul-2026 | Armando | Fix tarjeta "Pendientes" en Reporte Dirección (`api/reporte_direccion.php` + `app/modulos/reporte_direccion.php`) — Armando pidió que solo cuente las que "conforman el período y que estén vivas", en vez de todo el histórico vivo sin filtro de fecha (comportamiento previo a este UPD, y que UPD-427 casi rompió por accidente). `total_cots`/`total_cotizado` ahora se filtran con `CASE WHEN c.created_at BETWEEN ? AND ?` (mismo `$desde`/`$hasta` del selector de período, igual criterio que `pipeline_total_periodo` de UPD-428) en vez de estar en el `WHERE` general — así no arrastran el filtro a `pipeline_vigente`, que a propósito sigue siendo independiente del período. Sub-texto de la tarjeta actualizado de "Vivas hoy, cualquier fecha" a "Vivas del período". `ticket_promedio` de esta misma query se deja sin tocar — confirmado que no lo consume ningún KPI visible (dead field). Verificado con BD real (julio): Pendientes pasa de 438 ($4,319,354.54, histórico completo) a **302 ($3,201,847.49)**, ya acotado al período. |

| UPD-431 | 31-jul-2026 | Armando | Fix tarjeta "Foráneas" en Reporte Dirección mostrando siempre $0/0 — mismo patrón de bug que `cliente_id` en UPD-194: `api/cotizaciones.php` (`accion=convertir_orden`, ambos flujos suministro y maquila) nunca copiaba `ubicacion`/`ciudad_destino` de la cotización a la orden nueva al `INSERT INTO ordenes`. Solo `api/recibir_orden.php` (import viejo desde Google Sheets/Apps Script) llenaba ese campo, y ese camino dejó de usarse desde el 9-jun-2026 — desde entonces el 100% de las órdenes quedaban con `ubicacion` vacío, y la fórmula de la tarjeta (`!= 'FORANEO'` cuenta vacíos como local) las metía todas a "Locales" sin que se notara. Fix: (1) agregado `ubicacion` (`UPPER(cotizaciones.localidad)`) y `ciudad_destino` al INSERT en ambos flujos; (2) backfill de las 465 órdenes existentes con `ubicacion` vacío, copiando desde su cotización ligada (`UPDATE ... JOIN`, dentro de transacción con SELECT antes/después) — 99 resultaron foráneas, 366 locales. Verificado con BD real: total global Foráneas 54→153, Locales 153→519; para julio específicamente (período default del reporte) la tarjeta pasa de 0 foráneas a **217 locales / 57 foráneas**. |

| UPD-432 | 31-jul-2026 | Armando | Fix tarjeta "Reproceso" en Reporte Dirección — mostraba 0 siempre, en cualquier período, no solo julio. Causa: la query (`api/reporte_direccion.php`) leía de la tabla `reprocesos`, que está **completamente vacía** (0 filas) — el flujo real de retrabajo (`api/reproceso.php`) nunca escribió ahí; en vez de eso marca `piezas.es_retrabajo=1` y deja el registro en `historial_estatus` (`notas = "Retrabajo: <razón>"`). Cambiada la subquery de `piezas_reproceso` para contar `COUNT(DISTINCT h.pieza_id)` desde `historial_estatus` con ese patrón de nota, unido a `piezas`/`ordenes` para poder filtrar por el período igual que antes. Verificado con BD real: total histórico son 7 piezas retrabajadas (todas de junio) — período julio sigue en 0 correctamente (no hubo retrabajos ese mes, ahora es un cero real y no un cero estructural), período junio muestra 4/1,260 piezas. No se tocó la tabla `reprocesos` ni se investigó por qué existe vacía — puede ser candidata a limpieza en una sesión futura si se confirma que no la usa ningún otro proceso. |

| UPD-433 | 01-ago-2026 | Mando | **Contabilidad — Fase 4 (Caja Chica / Viáticos)**. Tabla nueva `caja_chica_movimientos` (fecha, concepto, monto, categoría, cuenta, empleado opcional ligado a `nomina_empleados`, comprobante, autorizado_por). Nueva pestaña "Caja Chica" en el módulo Contabilidad: bitácora libre por rango de fechas (no por periodo mensual fijo como Nómina/Gastos Fijos) con alta y baja de movimientos. A diferencia de Fases 2-3, cada registro se materializa individualmente en `movimientos_contables` al crearse (sin upsert por periodo) y se elimina junto con su reflejo contable al borrar el movimiento. Con esto quedan completas las Fases 0-4 del proyecto de Estado de Resultados — solo falta Fase 5 (reporte P&L final). Probado: sintaxis PHP de los 3 archivos; sin prueba de datos reales en BD (mismo criterio que Fases 2-3, pendiente de prueba visual). |

| UPD-434 | 01-ago-2026 | Mando | **Contabilidad — Fase 5 (Estado de Resultados / P&L, entregable final del proyecto)**. Nueva pestaña "Estado de Resultados" en el módulo Contabilidad + `api/contabilidad_pnl.php` (sin tabla nueva, es 100% cálculo sobre lo ya construido en Fases 0-4). Arma Ingresos - Costo de Ventas = Utilidad Bruta - Gastos Operativos = Utilidad Operativa (- Financieros - Impuestos = Utilidad Neta), con selector de rango de fechas. Ingresos se desglosan en 4.1 Ventas Suministro / 4.2 Ventas Maquila (agregado `ingresosPorTipoPeriodo()` a `api/helpers/pnl_datos.php`, usa `cotizaciones.tipo`); Costo de Ventas usa `costoVentasPeriodo()` de Fase 1; Gastos Operativos/Financieros/Impuestos se agrupan por cuenta desde `movimientos_contables` (Fases 2-4). El reporte muestra un aviso visible en rojo cuando `costoVentasCobertura().confiable` es falso (< 80% de piezas con costo trazado en el rango) en vez de mostrar un margen engañoso — hallazgo de Fase 1 sigue aplicando: julio-2026 solo tiene ~27% de cobertura real. Con esto el plan de fases del proyecto de Estado de Resultados queda completo (Fases 0-5); Fase 6 (partida doble real) sigue fuera de alcance salvo petición futura de un contador externo. Probado: sintaxis PHP de los 4 archivos tocados/nuevos + las queries del reporte corridas de solo-lectura contra la BD real de julio (ingresos $2,041,108.91, costo de ventas $128,128.01, utilidad bruta $1,912,980.90 — gastos operativos en $0.00 porque Nómina/Gastos Fijos/Caja Chica siguen sin datos capturados, pendiente de prueba visual). Falta la verificación final acordada desde el plan original: comparar el P&L contra el Excel de Armando de 1-2 meses ya cerrados antes de confiar en el reporte hacia adelante. |

| UPD-435 | 01-ago-2026 | Armando | Fix "no me deja recibir órdenes de compra" (rol `dueno`, reportado por Armando desde `?m=inventario`) — `app/modulos/inventario.php` línea 5 calculaba `$puedeGestionar = in_array($user['rol'], ['dir_admin','administracion','desarrollo'])`, sin incluir `'dueno'`. Esta variable controla toda la columna de acciones de la pestaña "Órdenes de Compra" (Ver detalle, Abrir OC, Registrar entrega, Registrar pago, Distribuir flete) — para rol `dueno` la columna se renderizaba vacía en todas las filas, sin ningún mensaje de error visible. Confirmado con `git log` que es un descuido de sincronización, no una restricción intencional: `api/ordenes_compra.php` (`$ROLES_GESTIONAR_OC`) y `api/permisos.php` ya incluían `dueno` con permiso completo (`gestionar_inventario`) desde el commit inicial — Armando confirmó explícitamente que dueño y dir_admin deben tener los mismos privilegios. Fix: agregado `'dueno'` al arreglo de la línea 5. Verificado en BD que actualmente no hay ninguna OC en estado `abierta`/`borrador` (todas `cerrada`/`pagada`), consistente con que el usuario no podía ni abrir una OC nueva para empezar a recibirla. No se tocó `$esDirAdmin` (línea 6, usado solo para permitir editar encabezado de una OC ya `abierta`) — ese gate es más estricto que el backend (`ocHeaderEsEditable` sí acepta cualquier rol de `$ROLES_GESTIONAR_OC` para ese caso), queda pendiente de que Armando confirme si también debe incluir `dueno`/`administracion`/`desarrollo` antes de tocarlo. Verificado con `php -l`, sin prueba visual en navegador (pendiente que Armando confirme con la cuenta `dueno`). |

| UPD-436 | 01-ago-2026 | Armando | Fix de seguimiento a UPD-435: seguía sin poder recibir mercancía en OCs ya pagadas (caso real: APEX-0210, faltaban por recibir 7 láminas Claro 12.00MM de la partida 78 — la OC quedó en `pagada` el 30-jul sin haber recibido esa partida, flujo pagar-antes-de-recibir). Causa: el botón "Registrar entrega" en `app/modulos/inventario.php` línea 1191 solo se mostraba con `oc.estado==='abierta'`, sin contemplar `'pagada'` — aunque `api/ordenes_compra.php` (guard `[C-7]`, línea 368) documenta explícitamente que ese flujo es válido (`in_array($estado_oc, ['abierta','pagada'])`) y que el cierre de OC nunca degrada una `pagada` de vuelta a `cerrada`. Fix: condición del botón ampliada a `(oc.estado==='abierta' \|\| oc.estado==='pagada') && puedeGestionar`. No se tocó el backend (ya soportaba el caso) ni la lógica de cierre. Verificado con `php -l`; pendiente que Armando confirme en el navegador registrando la entrega de las 7 láminas pendientes de APEX-0210. |

| UPD-437 | 01-ago-2026 | Armando | Fix criterio de reconocimiento de ingreso en Estado de Resultados (`api/helpers/pnl_datos.php`) — Armando reportó que julio mostraba $2,024,374.96 en Ingresos y no cuadraba; encontramos que el filtro contaba órdenes `activa` (aún sin entregar) cuyo `updated_at` cayó en el rango por cualquier motivo, no por venderse ese mes ($357,580.91 de 48 órdenes así en julio). Acuerdo explícito con Armando: **"las ventas son las órdenes que tuvieron acción de VoBo de parte de Lina"** — no la entrega física. Cambiadas las 4 funciones (`ingresosPeriodo`, `ingresosPorTipoPeriodo`, `costoVentasPeriodo`, `costoVentasCobertura`) para filtrar por `DATE(cotizaciones.vobo_at)` en vez de `COALESCE(o.fecha_cierre, o.updated_at)` — mismo criterio en las 4 para que Ingresos y Costo de Ventas midan el mismo período de cada orden. `costoVentasPeriodo`/`costoVentasCobertura` no tenían JOIN a `cotizaciones`, se agregó. Verificado contra BD real: julio pasa de $2,024,374.96 a **$1,317,476.21** (293 órdenes con VoBo en julio: 244 entregadas + 49 activas). Cobertura de costo trazado también cambia de base (1,306 piezas / 572 con costo = 43.8%) porque ahora incluye piezas de órdenes activas aún no cortadas del todo. |

| UPD-438 | 01-ago-2026 | Armando | Cambio de método de Costo de Ventas en Estado de Resultados (`api/helpers/pnl_datos.php`) — el método anterior (consumo real vía wizard de corte) solo cubría ~44% de las piezas vendidas de julio, dando $139,975.32 cuando el gasto real de vidrio es mucho mayor (Armando lo detectó: "se consumieron más de 1000 [m²] y sé que más de 700 son de Claro 9mm"). Acuerdo con Armando: reemplazar por **m² de cada pieza vendida × precio promedio de compra por tipo/espesor de vidrio**, sin importar de qué lámina/tamaño específico salió — ya no depende del wizard. Como `piezas.cristal` es texto libre sin llave foránea a `laminas`, se normaliza con `REGEXP` a tipo (claro/filtrasol/bronce/tintex/satinado/espejo/evo_50/cliente_maquila/laminado) y espesor (5/6/9/12mm) para cruzarlo contra el costo promedio ponderado de `inventario_compras`. Piezas de maquila (`cliente_maquila`) correctamente sin costo — el cliente trae su propio vidrio. `costoVentasCobertura()` ahora mide qué % de piezas tiene tipo/espesor con precio de compra de referencia (no cuenta maquila como hueco) — julio pasa de 43.8% a **96.2% de cobertura**. Verificado contra BD real: julio pasa de $139,975.32 a **$418,566.07** en Costo de Ventas (Utilidad Bruta: $898,910.14). Quedan 40.55 m² (2.5%) sin costear porque esos tipos/espesor nunca se han comprado (Claro 5mm, Filtrasol 9mm, Bronce 6mm, Satinado 6mm) — no es un hueco de trazabilidad, es falta total de referencia de precio. |

| UPD-439 | 01-ago-2026 | Armando | Fix contable: Ingresos del Estado de Resultados sumaba `cotizaciones.total`, que INCLUYE IVA (16%) — el IVA es un pasivo (dinero que se le debe al SAT), no ingreso. Armando pidió revisar si el P&L seguía reglas contables comúnmente aceptadas; se encontró este error real (los otros 3 puntos revisados — reconocimiento por VoBo en vez de entrega, gastos en base de efectivo, costeo promedio vs FIFO usado en inventario — son decisiones de diseño válidas para un P&L gerencial interno, no bugs). Cambiadas `ingresosPeriodo()` e `ingresosPorTipoPeriodo()` en `api/helpers/pnl_datos.php` para usar `cotizaciones.subtotal` (neto de IVA, ya viene post-descuento) en vez de `cotizaciones.total`. Verificado contra BD real: julio pasa de $1,317,476.21 a **$1,135,410.54** en Ingresos (Utilidad Bruta: $716,844.47). |

| UPD-440 | 01-ago-2026 | Armando | Captura manual de precios de compra para completar cobertura de costeo del P&L (catálogo `laminas` + `inventario_compras`, ver hallazgo UPD-438). Se agregaron 3 de los 5 tipos de vidrio sin precio de referencia detectados: **Bronce 6mm** (3.60×2.60m=9.36m², $6,149 neto, Bodega de Vidrios y Cristales de León — requirió `ALTER TABLE laminas MODIFY tipo ENUM(...)` para agregar 'bronce', no estaba en el catálogo cerrado), **Filtrasol 9mm** (3.66×2.14m=7.83m², $5,286.87 neto, mismo proveedor — se creó lámina nueva en vez de usar la existente id=2 de 3.60×2.60m porque el tamaño real es distinto y esa entrada nunca tuvo compras), **Satinado 6mm** (1.80×2.60m=4.68m², $1,869 neto, mismo proveedor). Todos son precios de referencia manuales (no atados a una OC real), marcados así en `notas`. Cobertura histórica de piezas costeables sube de la base anterior a **97.1%** (2,094 de 2,156 piezas, excluyendo maquila). Pendiente (Armando decidió pausar aquí): Bronce 9mm, Claro 5mm, y el precio de INDI GLASS para Satinado 6mm (Armando mencionó que también se lo vende, para promediar entre las 2 fuentes). |

| UPD-441 | 01-ago-2026 | Armando | Corrección de datos (no código): los 3 precios de UPD-440 (Bronce 6mm $6,149, Filtrasol 9mm $5,286.87, Satinado 6mm $1,869) los dio Armando **con IVA incluido**, no netos como se asumió inicialmente — Armando lo confirmó al preguntarle directo. Corregidos los 3 `inventario_compras.precio_unitario` dividiendo entre 1.16 (Bronce→$5,300.8621, Filtrasol→$4,557.6466, Satinado→$1,611.2069), nota agregada documentando la corrección. Costo de Ventas de julio baja de $422,087.87 a **$421,602.10** (Utilidad Bruta: $713,808.44). |

| UPD-442 | 01-ago-2026 | Armando | Documentación (sin cambio de código): Armando pidió auditar si el Estado de Resultados sigue reglas contables generalmente aceptadas (NIF/GAAP). Revisión completa contra el motor real del P&L — 1 error real corregido (UPD-439, IVA en Ingresos) y 3 puntos identificados como decisiones de diseño válidas para un P&L gerencial interno, no NIF-compliant si algún día sale a un tercero: (1) ingreso reconocido al VoBo en vez de a la entrega, (2) gastos en base de efectivo (fecha de pago) en vez de devengado, (3) sin partida doble (debe/haber) — es una bitácora de una sola entrada, no libro mayor. Se armó plan conceptual de cómo se construiría la partida doble (Fase 6, ver detalle en sección 12) — Armando confirmó dejarlo solo como plan por ahora, sin implementar. |

| UPD-443 | 03-ago-2026 | Mando | **Contabilidad — arranca Fase 6 (partida doble), Fase 6.0 hecha.** Armando pidió recrear el nivel de robustez de SAP/Compac de forma preventiva (sin auditor externo pidiéndolo aún) — plan por fases (6.0 catálogo Balance → 6.1 pólizas → 6.2 generador automático → 6.3 Balance General/Comprobación → 6.4 arranque hacia adelante). Fase 6.0: `cuentas_contables` ampliada con tipo_financiero activo/pasivo/capital + 11 cuentas nuevas (ACTIVO/PASIVO/CAPITAL y subcuentas Bancos/CxC/Inventario/CxP/IVA por Pagar/Nómina por Pagar/Capital), sin tocar las 23 cuentas de P&L existentes. UI de `contabilidad_catalogo.php` agrupa visualmente P&L vs Balance y relabela Suma/Resta→Deudora/Acreedora para cuentas de Balance. Sin prueba visual en navegador. |

| UPD-444 | 03-ago-2026 | Mando | **Contabilidad — Fase 6.1 (Pólizas), captura manual.** Tablas nuevas `polizas`/`polizas_lineas` (Debe/Haber, folio auto por tipo D/I/E, anulación en vez de borrado físico) + `api/polizas.php` + pestaña "Pólizas" en el módulo Contabilidad: captura con validación en vivo (botón Guardar bloqueado hasta que Debe=Haber) y backend que rechaza la póliza completa si no cuadra dentro de la transacción. Todavía sin conexión a ningún evento real del sistema (eso es Fase 6.2) — es captura manual únicamente. Probado con INSERT real dentro de transacción con ROLLBACK confirmado (0 filas después) antes de darlo por bueno. |

| UPD-445 | 03-ago-2026 | Mando | **Contabilidad — reorganización de la barra de pestañas (UX).** Armando (no contador) reportó que el módulo se sentía "desordenado" y no le "hacía sentido" — las 7 pestañas estaban en una sola fila en el orden en que se construyeron por fases, abriendo por default en "Catálogo de Cuentas" (el término más técnico). Rediseño validado primero con un mockup en Artifact antes de tocar el módulo real: las mismas 7 pestañas ahora se agrupan visualmente en 3 bloques con color guía — **Reporte** (Estado de Resultados, ahora la pestaña que abre por default), **Captura mensual** (Nómina/Gastos Fijos/Caja Chica/Pólizas), **Configuración** (Catálogo de Cuentas/Mapeo Compras). Solo cambió `app/modulos/contabilidad.php` (CSS + agrupación de botones + tab inicial) — ninguna lógica, tabla ni otro módulo tocado. |

| UPD-446 | 03-ago-2026 | Mando | **Contabilidad — Fase 6.2 (generador automático de pólizas), primeros 4 eventos.** Armando pidió que el sistema sea "lo más automático posible" en vez de capturar pólizas a mano — se distinguió que para compras a proveedor el dato ya se teclea al dar de alta la OC (no requiere OCR/lectura de factura, eso quedó documentado como posible fase futura si algún proveedor da CFDI en XML). Nuevo `api/helpers/polizas_lib.php`: `pl_generarAutomatica()` crea la póliza balanceada y, si el mismo origen se regenera (ej. upsert de nómina/gastos fijos por periodo), anula la anterior y crea una nueva en vez de editarla — nunca lanza excepción hacia el llamador, si falla solo queda sin póliza y loguea a error_log, nunca bloquea la operación real de negocio. Conectado en 4 puntos: `api/caja_chica.php` (alta y baja de movimiento), `api/gastos_fijos.php` (pago mensual), `api/nomina.php` (pago mensual), `api/ordenes_compra.php` (`registrar_entrega` → Debe Inventario/Haber CxP, `registrar_pago` → Debe CxP/Haber Bancos). Pestaña Pólizas ahora muestra badge Auto/Manual. Pendiente explícito: Ventas/Cobros (VoBo de Lina, pagos de cliente en Finanzas) — es el flujo de mayor riesgo (ingreso real), se deja para revisar con más cuidado en otra sesión. Probado con `pl_generarAutomatica()` real en BD dentro de transacción con ROLLBACK, incluyendo el caso de regeneración (anula+crea nueva) — confirmado 0 filas después. Falta que Armando/Mando prueben el flujo completo en el navegador (recibir/pagar una OC real, pagar nómina/gastos fijos/caja chica) y revisen los montos generados. |

| UPD-447 | 03-ago-2026 | Mando | **Contabilidad — Fase 6.3 (Balance de Comprobación + Balance General).** Nuevo `api/contabilidad_balance.php` + pestaña "Balance General" (grupo Reporte). Se calcula 100% desde `polizas_lineas` (pólizas activas, corte a una fecha) — nunca se captura a mano. La dirección Deudora/Acreedora de cada cuenta se decide por regla universal de partida doble según `tipo_financiero` (Activo/Costo/Gasto/Financiero/Impuesto = deudora; Pasivo/Capital/Ingreso = acreedora), no por el campo `naturaleza` de `cuentas_contables` (ese describe el signo dentro del Estado de Resultados, es un concepto distinto). El Balance General muestra Activo vs. Pasivo+Capital+"Resultado del periodo" (Ingresos−Costos−Gastos acumulado de las pólizas) con badge Cuadra ✓/✗ como verificación visual inmediata. Banner explícito: como Ventas/Cobros aún no generan póliza (pendiente de Fase 6.2), Ingresos y CxC se ven bajos/en $0 por ahora — no es un bug. Probado end-to-end con 2 pólizas de prueba dentro de BEGIN/ROLLBACK (recepción OC $5,000 + viáticos $800): Activo=$4,200, Pasivo+Capital+Resultado=$4,200, cuadra exacto — confirmado 0 filas tras el rollback. |

| UPD-448 | 03-ago-2026 | Armando | **Incremento de precios — catálogo de cristales (11%, programado).** Corrección de datos, sin cambio de código. Aplicado a los 31 registros activos de `cristales` (excluido el único inactivo, "Claro de 9mm - Descuento Fin de Mes"). Fórmula acordada con Armando (no `precio×1.11`): `precio_m2 = ROUND(precio_m2 / 0.89, 2)` — es el inverso exacto de un descuento del 11%, así que aplicar después un 11% de descuento regresa exacto al precio original (a diferencia de multiplicar por 1.11, que no es la inversa exacta de dividir entre 0.89). Ejecutado en una sola transacción: `INSERT INTO cristales_historial` (motivo="Incremento Agosto 2026", usuario_id=16/Armando) capturando precio_anterior antes del `UPDATE cristales`, seguido del `UPDATE`; verificado con SELECT antes/después — los 31 precios en `cristales_historial.precio_nuevo` cuadran exacto contra `cristales.precio_m2` tras el commit. Ejemplos: Claro 9mm $852.49→$957.85, Filtrasol 9mm $1,676.25→$1,883.43, Tintex 6mm $909.96→$1,022.43. Nota para consistencia futura: si se vuelve a necesitar un incremento/descuento homologado de catálogo, usar la misma fórmula de división (no multiplicación) para que sea reversible exacto. |

| UPD-449 | 03-ago-2026 | Armando | Corrección de datos (no código): agregada partida 2 (Claro 9mm, 775×1992mm, CPB Perimetral, TP=5, $1,450.31) a la orden **S-473** (COT-0993, cliente id=49) ya activa con VoBo desde el 31-jul — la única pieza de P1 seguía en `pendiente`, sin riesgo de tocar trabajo real. Precio/m² usado: $852.49, el mismo con el que se dio VoBo a P1 (decisión explícita de Armando de no usar el precio de catálogo post-incremento de UPD-448, para mantener consistencia dentro de la misma orden). Insertada 1 fila en `cotizaciones_partidas` (id=4880→4881... real id=5019) + 1 pieza nueva en `piezas` (QR `COT-0993-02-001-001`, estatus `pendiente`) + recálculo de totales de la cotización con la fórmula canónica. Efecto colateral encontrado y corregido de paso: `saldo_pendiente` de esta cotización ya estaba mal antes de este cambio ($1,503.11 con el pago completo ya registrado — debía ser $0); al recalcular con la fórmula canónica (`total - saldo_pagado`) quedó en $1,450.31, exactamente lo que falta cobrar por la pieza nueva. No se investigó la causa raíz de ese desfase previo (no es parte de este pedido) — si se repite en otras cotizaciones, revisar aparte. Sin endpoint dedicado para "agregar partida a una orden ya activa" en el sistema (`api/correcciones.php` solo edita/elimina partidas existentes) — hecho como INSERT manual siguiendo exactamente el mismo patrón de piezas/QR que ya usa `api/correcciones.php` al aumentar cantidad, dentro de una transacción con SELECT de verificación antes de comprometer. |

| UPD-450 | 03-ago-2026 | Armando | **Esquema de Referidos — construido completo (promo agosto 2026).** Cliente refiere a alguien con su CTN; el referido recibe 5% de descuento automático en su primera cotización (visible en la impresión) y de ahí en adelante en TODAS sus cotizaciones del mes sin volver a teclear el código; el referente recibe 5% de saldo a favor por cada cotización del referido que llegue a VoBo, con aviso por WhatsApp. Solo aplica a clientes nuevos (sin cotizaciones previas), promoción corre 01 al 31-ago-2026. **BD:** `clientes_saldo_favor.tipo` ampliado con `'referido'`; tabla nueva `clientes_referidos` (cliente_id referido UNIQUE, referente_cliente_id, referente_ctn, mes_promo, fecha_registro, registrado_por, cotizacion_origen_id) con FKs a `clientes`; `cotizaciones` +columna `descuento_referido DECIMAL(5,2)`. **Lógica central** en `api/helpers/referidos_lib.php` (nuevo): `referidosValidar`/`referidosRegistrar` (captura del código antes/después del INSERT de la cotización), `referidosDescuentoAutomatico` (aplica el 5% sin repetir código en cotizaciones subsecuentes del mes), `referidosAcreditarVoBo` (abona el saldo al referente, idempotente vía `clientes_saldo_favor.cotizacion_id`, solo si el VoBo cae en el mismo mes de la promo). `api/helpers/totales.php` (`apexTotalesCotizacion`) suma `descuento + descuento_referido` para el neto — el candado de autorización dir_admin >10% sigue evaluando solo el descuento manual, a propósito. Conectado en `api/cotizaciones.php` (crear y editar cotización, mismo criterio de suma en ambos flujos) y `api/finanzas.php` (acción `vobo`, dispara el abono + WA con plantilla `referido_saldo_abonado` **pendiente de que Armando la dé de alta en Meta Business Manager**, categoría UTILITY, 3 variables: nombre del referente, monto abonado, nombre del referido). UI: campo "Código de Referido (CTN)" en `app/modulos/cotizacion.php` (solo visible en cotización nueva) con preview en vivo del 5%; línea "Descuento cliente referido" en `app/imprimir_cotizacion.php`. Portal: quitado el link "Sorteo" (era el ranking de julio, `portal/tablero.php` — el archivo se dejó intacto, solo se desligó del nav, reversible) y agregado `portal/referidos.php` nuevo (código propio para compartir, a quién ha referido, saldo acumulado). Maquila: a propósito NO recibe descuento_referido por esta vía (mismo criterio que el descuento manual, que tampoco aplica a maquila en este helper — su descuento vive aparte en `api/maquila.php`). Probado con dry-run completo en BD real dentro de una transacción con ROLLBACK (cliente real sin cotizaciones + cotización de prueba clonada con vobo_at=hoy): validación, registro, auto-descuento en 2da cotización, cálculo exacto del 5% ($50 sobre $1,000 de subtotal), candado anti-duplicado confirmado (segunda corrida no vuelve a abonar), y verificado que el ROLLBACK dejó todo en 0 sin rastro. **Pendiente:** que Armando dé de alta la plantilla de WhatsApp en Meta; prueba visual en navegador del campo nuevo en Cotizaciones, la impresión y el apartado del portal; checkpoint de rollback completo (tag git `pre-referidos-2026-08-03` + backup BD `_backups/backup_pre_referidos_2026-08-03_18-56.sql.gz`) generado antes de empezar por si hay que revertir. |

| UPD-451 | 03-ago-2026 | Armando | Corrección de datos (no código): eliminado cliente duplicado **CTN-473** (id=331, ABEL CEDILLO COVARRUBIAS) — la asesora Cynthia lo registró dos veces por error el 31-jul con 9 segundos de diferencia (CTN-472 id=330 y CTN-473 id=331, mismo nombre y teléfono). Verificadas las 8 tablas con `cliente_id` antes de borrar: CTN-473 no tenía ninguna actividad real (ni cotizaciones, órdenes, saldo a favor, referidos, conversación WA, rechazos ni campañas) — solo su registro de creación en `clientes_bitacora`. Se conservó CTN-472, que sí tiene 1 cotización, 1 orden y 1 conversación de WhatsApp reales. Borrado `clientes_bitacora` (FK) + `clientes` dentro de una transacción con SELECT de verificación antes/después; confirmado que CTN-472 quedó intacto. |

| UPD-452 | 03-ago-2026 | Armando | **Contabilidad — UI directiva del Estado de Resultados + ocultado el resto del módulo salvo `desarrollo`.** Armando pidió (con apoyo de la skill `frontend-design`) que por ahora solo se vea una presentación profesional y minimalista del Estado de Resultados — el resto (Balance General, Nómina, Gastos Fijos, Caja Chica, Pólizas, Catálogo, Mapeo Compras) sigue en desarrollo y "se ve feo y no genera valor" mostrárselo a nadie más todavía. Cambios: (1) `app/modulos/contabilidad.php` — nueva rama `if ($user['rol'] !== 'desarrollo')` que salta toda la barra de pestañas y carga directo (sin UI de navegación) solo `contabilidad_pnl.php`; el rol `desarrollo` sigue viendo exactamente las 8 pestañas de siempre, sin tocar. (2) `app/dashboard.php` — el badge "WIP" junto a "Contabilidad" en el sidebar ahora solo se muestra a `desarrollo` (antes lo veían todos los roles con `ver_contabilidad`: dir_admin, administracion, dueno). (3) `app/modulos/contabilidad_pnl.php` — rediseño completo: hero con el número de Utilidad Neta en grande (con flecha de tendencia vs. período anterior cuando está en modo Comparación), tipografía Outfit (ya usada en Portal Clientes, vía Google Fonts — el CSP del dashboard ya la permite por el logo Syncopate), paleta alineada a los tokens del dashboard (`#0f172a`/`#64748b`/`#e2e8f0`) con el verde `#0f766e` ya usado como acento del grupo "Reporte" en la barra de pestañas. Decisión de diseño: se quitó el color rojo/verde de cada línea de Ingresos/Gastos (evita la lectura de "alarma" en cada renglón) y se reserva el color solo para la cifra final de Utilidad Neta — el signo de resta se sigue viendo con paréntesis, convención contable estándar. El aviso de cobertura de costo y el aviso de compras sin mapear se conservan (son señal real para el director) pero restyleados como nota discreta en vez de banner rojo grande; se quitó el banner estático de metodología y el badge "WIP" de esta vista. Sin cambio en `api/contabilidad_pnl.php` ni en los datos — es 100% presentación. Verificado con `php -l` en los 3 archivos; **sin prueba visual en navegador** (no hay Chrome DevTools/Playwright MCP conectado en esta sesión) — pendiente que Armando confirme que se ve bien. |

| UPD-453 | 04-ago-2026 | Armando | Auditoría de seguridad externa (Kali, `nuclei -u https://apex.glass -tags cve,exposed-panels,misconfiguration,default-logins -etags dos,fuzz,intrusive -rl 10 -c 5`, 3873 templates, 9 min): **0 hallazgos**. Efecto colateral: fail2ban baneó la IP de la VPN (California) de Armando por el volumen de requests del scan — se repetirá en cualquier próxima corrida de nuclei/similar desde IP nueva. Pendiente agregado en sección 12: whitelist temporal o pausar fail2ban antes del próximo escaneo. |

| UPD-454 | 04-ago-2026 | Mando | Fix `operador.php` (Jefe de Piso): `nextEstatus()` no conocía las reglas de maquila (requiere_corte/cpb/tp+ta/requiere_templado) y sugería avanzar a estaciones que la pieza no necesita — el backend las rechazaba con 400. Ahora calcula las mismas estaciones aplicables que ya usa `api/actualizar_estatus.php`. De paso, se ocultó el botón "Avanzar → Entregado" para jefe_piso/director (sin permiso `registrar_entrega`, el backend ya lo bloqueaba con 403, ahora ni se ofrece). |

| UPD-455 | 04-ago-2026 | Mando | Nuevo botón "Liberar" en Logística Rutas (solo dir_admin/administracion/dueno/desarrollo): quita una orden ya `entregada` de la cola de "pendientes de asignar" (`requiere_ruta=0`) sin crear una ruta falsa — para casos donde la entrega física ya ocurrió fuera del flujo formal de rutas y la orden se queda atorada para siempre (`requiere_ruta` nunca se apaga solo, ver diseño en `api/rutas.php` accion=pendientes). Deja rastro en `observaciones` (quién y cuándo). Nueva acción `liberar_pendiente` en `api/rutas.php`. Usado por Mando para liberar 11 folios reales (S-251 a S-308) detectados atorados desde jul-2026; queda pendiente 1 caso aislado más viejo (S-115, no aparece en la lista visible, se dejó sin tocar por no ser necesario). Archivos: api/rutas.php, app/modulos/logistica_rutas.php. Sin prueba en navegador de mi parte — Mando ya lo probó en vivo y confirmó que funciona. |

| UPD-456 | 04-ago-2026 | Mando | Nuevo botón "Ajustar stock" (📊) por lámina en Inventario → Stock, solo dir_admin/administracion. Corrige el stock de una lámina directo a la cantidad correcta (conteo físico, roturas, etc.) en vez de pedir "cuánto rebajar" — el modal calcula la diferencia y solo permite corregir hacia abajo (para subir stock sigue siendo por "Registrar compra", que sí trae precio/proveedor para el costeo). Mismo mecanismo ya usado por "Registrar uso" (inserta en `inventario_movimientos`, no toca `inventario_compras`), con motivo obligatorio y permiso más estricto (nueva acción `ajustar_stock`, separada de `registrar_uso` que sigue abierta a cualquier rol con `ver_inventario`). Archivos: api/inventario.php, app/modulos/inventario.php. Sin prueba en navegador de mi parte — pendiente que Armando/Lina lo confirmen en vivo. |

| UPD-457 | 04-ago-2026 | Armando | **Contabilidad — Merma neta de corte agregada a Costo de Ventas del P&L, desde agosto en adelante.** Discusión de fondo con Armando sobre si la merma de corte debe ser Costo de Ventas: confirmado que sí (NIF C-4, desperdicio normal de producción), con dos matices resueltos con Armando — (1) cuando el operador reutiliza sobrante (pedacería) para sacar producto, ese m² deja de ser pérdida y pasa a costo de vidrio consumido (ya se contabiliza normal por pieza, sin cambio); solo hay que RESTAR ese m² de la merma reportada para no cobrarlo dos veces — el sistema ya soporta esto sin trabajo extra del piso vía el flag `es_pedaceria` que ya existe en el wizard de corte (`api/sesion_corte.php`), sin necesidad de ligar cada sobrante a su sesión de origen. (2) Armando confirmó su expectativa de 20-25% de merma por lámina como "normal" para el negocio — coincide con lo observado (23-27%), así que se carga el 100% a Costo de Ventas sin separar una porción como "anormal" (la distinción NIF normal/anormal no aplica aquí). Implementado: cuenta nueva `5.4 Merma Neta de Corte` en `cuentas_contables` (bajo Costo de Ventas, junto a 5.1 Vidrio/5.2 MO/5.3 CIF); función nueva `mermaNetaPeriodo()` en `api/helpers/pnl_datos.php` — SUM por sesión de `(m2_disponible - m2_aprovechado si es_pedaceria=0) - (m2_aprovechado si es_pedaceria=1)`, × costo promedio por tipo/espesor (mismo criterio que 5.1); conectada en `api/contabilidad_pnl.php` como línea independiente, sumada al total de Costo de Ventas. Candado de fecha explícito: si el rango de reporte termina antes del 1-ago-2026 regresa $0 — julio queda exactamente igual que antes (ya reportado, no se toca); un rango que cruce la frontera solo cuenta cortes desde el 1-ago. Bug propio detectado y corregido antes de dar por bueno: la primera versión de la fórmula SQL multiplicaba el `SUM()` agregado por un solo precio arbitrario en vez de aplicar el precio correcto por fila (paréntesis mal anidados) — corregido a `SUM(x*y)` real. Probado con datos reales: julio = $0 de merma (correcto, antes del corte), agosto 1-4 = $7,086.76, rango cruzado (15-jul a 4-ago) = mismo $7,086.76 que agosto puro (confirma que no cuenta julio). Reporte completo probado end-to-end (ingresos/costo/utilidad) fuera de sesión HTTP real, sin tocar producción salvo el INSERT de la cuenta 5.4 (con SELECT de verificación antes/después). UI (`app/modulos/contabilidad_pnl.php`) no requirió cambios — ya renderiza líneas de forma dinámica por código/nombre. Pendiente: confirmar visualmente en navegador con un mes completo de agosto (con solo 4 días el número es chico y ruidoso). |

| UPD-458 | 04-ago-2026 | Mando | **Operador Chofer — pantalla dividida + advertencia de orden de escaneo.** Rediseño de `app/operador.php` solo para rol `chofer` (mockup validado antes en Artifact, mismos tokens ya existentes del tema oscuro del archivo — no se inventó paleta nueva): mitad superior cámara QR (42vh), mitad inferior lista completa de la ruta activa con nombre de cliente + folio + colonia y pastilla OK/Pendiente/Siguiente por parada (nueva acción GET `mi_ruta` en `api/salidas.php`, arma la ruta del chofer en sesión por `secuencia ASC`). Al escanear el QR de hoja de ruta (`accion=scan_qr_ruta`) fuera de orden — hay paradas con `secuencia` menor aún `pendiente` en la misma ruta — ya NO confirma directo: regresa `requiere_confirmacion` con el nombre del cliente que falta y la lista completa de paradas saltadas, y el chofer ve una hoja de advertencia grande (reutiliza el patrón `.modal-bg`/`.btn-confirmar` ya usado en Omisión) con dos botones sólidos y llamativos: "Continuar de todos modos" (rojo) y "Cancelar y escanear en orden" (azul, a petición de Armando — el botón de cancelar original se veía apagado/texto plano) (reintenta con `forzar:true`) — no bloquea, solo pregunta, cubre el caso real de que el cliente no estaba y hay que reordenar en la calle. Archivos: api/salidas.php, app/operador.php. **Bug real encontrado al probar con la cuenta `chofer1`:** `rutas.chofer` guarda el nombre del selector de "Nueva Ruta" (`ROBERTO GARCIA`/`VICTOR BAUTISTA`, ver UPD-406) pero la sesión de operador.php trae `usuarios.nombre` completo (ej. "Juan Roberto García", con segundo nombre y acentos) — nunca hacían match exacto, así que "mi_ruta" salía vacío para cualquier chofer real. Fix: mapa fijo `CHOFER_LABEL_POR_USUARIO_ID` en `api/rutas_lib.php` (usuario_id 12→ROBERTO GARCIA/chofer1, 13→VICTOR BAUTISTA/chofer2, los 2 únicos choferes reales hoy) — `mi_ruta` resuelve por `user_id` de sesión en vez de comparar nombres de texto. Si se da de alta un tercer chofer más adelante, hay que agregarlo a ese mapa. Sin prueba en navegador de mi parte (no hay sesión de chofer real disponible en este entorno) — pendiente que Mando lo pruebe con la cuenta `chofer1` antes de confiar en el flujo. |

| UPD-459 | 05-ago-2026 | Mando | Fix `api/reproceso.php` (retrabajo): intentaba guardar `estatus='descartada'`, valor que no existe en el ENUM de `piezas.estatus`/`historial_estatus.estatus_nuevo` — truena con error SQL en cualquier estación (no era exclusivo de horno, como se creía). Colapsado a un solo paso directo (estatus anterior → `pendiente`), mismo patrón usado para corregir manualmente una pieza real de S-483. Verificado con dry-run (BEGIN/ROLLBACK) sobre una pieza real de S-316. De paso se corrigió texto con codificación rota en los mensajes de notificación de retrabajo (nunca se veían porque el código siempre truena antes de llegar ahí). |
| UPD-460 | 05-ago-2026 | Mando | **Bono de Corte por Pedacería (Angel) — construido.** `$150 por cada 18 m² de pedacería aprovechados`, calculado semanalmente (lunes-domingo), fórmula por tramos (proporcional 0-18 m², luego $150 fijo por cada tramo de 18 m² YA completo — ej. 35 m² = $150, no $291.67; 36 m² = $300). Candado anti-trampa: tope silencioso de 2.5 m² por sesión de pedacería — el wizard de corte (`api/sesion_corte.php`) NO se tocó, sigue exactamente igual; una sesión que declare un sobrante mayor a 2.5 m² simplemente no cuenta para el bono, sin bloqueo ni aviso a Angel. Sin retroactividad: solo cuenta desde el 03-ago-2026 (`BONO_PEDACERIA_INICIO` en el nuevo archivo) en adelante. Nuevo: tabla `bono_pedaceria_pagos` (aislada, no toca `sesiones_corte` ni ninguna tabla de producción), `api/bono_pedaceria.php` (acciones `mi_bono`/`resumen_semana`/`marcar_pagado`), tarjeta de progreso en `app/corte_dashboard.php` (visible solo a operadores de estación `corte`, sin exponer el tope de 2.5 m²), módulo nuevo `app/modulos/bono_corte.php` ("Bono Corte" en sidebar, mismo permiso `ver_contabilidad`/`gestionar_contabilidad` que Contabilidad — dir_admin/administracion/dueno/desarrollo) con revisión semanal y botón "Marcar como pagado" (único checkpoint humano, un clic por semana). Verificado con datos reales de Angel (operador_id=2): semana 03-09 ago, 4.745 m² elegibles → $39.54, excluyendo correctamente la única sesión de esa semana que pasa el tope (4.69 m²). Riesgo aceptado a propósito (decisión explícita, sin construir nada para esto): nada impide reusar/repetir el mismo pedazo físico de sobrante en el discurso entre varias sesiones — se descartaron foto de evidencia y alerta automática de proporción anómala por pedirlo así explícitamente; quedan documentadas como siguiente capa si se detecta abuso real. Pendiente: prueba visual en navegador (no hay Chrome DevTools/Playwright MCP en este entorno). |

| UPD-461 | 05-ago-2026 | Mando | **Portal Clientes — botón "Ver remisión".** `api/orden.php` ahora regresa `cotizacion_folio` (se muestra en `portal/orden.php` debajo del folio de orden, gris fuerte) y `tiene_remision`. Nuevo `portal/remision.php`: vista de solo lectura del documento de remisión — a propósito NO reutiliza `app/imprimir_salida.php` (esa es una herramienta interactiva de staff que deja elegir piezas y registrar nuevas entregas; exponerla tal cual al cliente hubiera sido un hueco de seguridad), sino que recalcula el mismo documento con verificación de que la orden pertenece al cliente de la sesión. Disponible desde que existe la orden (no espera a que se imprima una salida real) — sin salidas registradas muestra "PENDIENTE" en cada partida con la fecha/tipo de entrega estimados de la cotización. Verificado con S-481 (con salidas) y S-264 (sin salidas) — ambos casos renderizan bien; ownership check confirmado (cliente equivocado no ve nada). |
| UPD-462 | 05-ago-2026 | Mando | Fix retrabajo para roles de supervisión (jefe_piso/director/dir_admin/desarrollo) en `app/operador.php`: la detección automática de estación por el estatus de la pieza (`ESTATUS_A_ESTACION`) solo se activaba si `session.estacion === 'jefe_piso'` — coincidencia frágil con el valor que esa cuenta tiene en la BD. Cualquier otro rol de supervisión (o variación de sesión) usaba su propia estación/rol en vez de mirar la pieza, dando siempre las mismas razones sin importar dónde estuviera la pieza (reportado: "no importa la estación, solo sale Roto en corte"). Cambiado a checar `session.rol` contra un set explícito `ROLES_SUPERVISION` — señal correcta y estable. De paso se quitó `ESTACIONES_JEFE`, constante que ya no se usaba en ningún lado. Operadores normales (estación fija) no se tocaron, ya funcionaban bien. |
| UPD-463 | 05-ago-2026 | Mando | **Contabilidad — Bono de Corte agregado al Estado de Resultados.** Discutido a fondo con Armando si el bono de Angel (UPD-460) es Costo de Ventas o Gasto Operativo: es mano de obra directa variable (escala con m² de pedacería real, no es sueldo fijo) — mismo criterio que 5.1/5.4, así que va a **Costo de Ventas, cuenta 5.2 Mano de Obra Directa (Planta)** (existía en el catálogo desde Fase 0 pero no le entraba nada). Nueva función `bonoManoObraPeriodo()` en `api/helpers/pnl_datos.php` — suma `bono_pedaceria_pagos.monto` donde `estado='pagado'`, en base de efectivo (`aprobado_at`, no la semana en que se ganó — mismo criterio que Nómina/Gastos Fijos/Caja Chica). Conectada en `api/contabilidad_pnl.php` junto a 5.1 y 5.4. Verificado con dry-run (INSERT de prueba + ROLLBACK): con un pago de $39.54 la función y el total del período lo suman bien; hoy marca $0 porque ningún bono real se ha marcado como pagado todavía. |
| UPD-464 | 05-ago-2026 | Mando | **Nuevo módulo: Bitácora de Desechos.** Registro de trazabilidad de cuándo un proveedor de reciclaje se lleva la merma física de la planta (vidrio, madera, artículos de oficina) con evidencia adjunta — puramente operativo/administrativo, NO genera pólizas ni toca Contabilidad/Estado de Resultados (a propósito, decidido explícitamente). Visible para Lina (rol `administracion`), dir_admin, desarrollo y dueño — mismo permiso `ver_contabilidad`/`gestionar_contabilidad` ya usado por Contabilidad/Bono Corte, sin crear uno nuevo. Tablas nuevas y aisladas: `bitacora_desechos`, `bitacora_desechos_proveedores` (catálogo propio de recicladoras — Empresa/Nombre de contacto/Número de contacto/Número de empresa/Correo opcional; a propósito NO reutiliza la tabla `proveedores` de Compras para no mezclar proveedores de vidrio con recicladoras ni requerir ALTER sobre una tabla compartida), `bitacora_desechos_archivos`. Subida de archivos reutiliza el patrón ya probado de `api/archivos_ordenes.php` (whitelist jpg/png/pdf, validación MIME real, tope 10MB, nombre de archivo generado por el servidor, carpeta `bitacora_desechos/` con `.htaccess Deny from all`). Nuevo `api/bitacora_desechos.php` (listar/crear/listar_proveedores/crear_proveedor/subir_archivo/descargar/eliminar_archivo/eliminar — borrado restringido a dir_admin/desarrollo) + módulo `app/modulos/bitacora_desechos.php` (botones "+ Nuevo proveedor" y "+ Registrar recolección", chips de color por categoría) + entrada "Bitácora de Desechos" en el sidebar dentro de "Administración" (esa sección estaba gateada solo a dir_admin/desarrollo — se amplió el wrapper para incluir el permiso `ver_contabilidad` sin tocar la visibilidad de los botones ya existentes ahí, que quedaron cada uno con su propio candado interno). Diseño validado con mockup clicable antes de construir (skill ui-ux-pro-max). Verificado con dry-run real (INSERT proveedor→desecho→archivo con JOIN, BEGIN/ROLLBACK) y con sesión simulada contra el endpoint real: `listar`/`listar_proveedores` responden bien para `desarrollo`, bloquean correctamente con "Sin permiso" a `comercial`. Sin residuos de prueba en las tablas. Pendiente: prueba visual en navegador (no hay Chrome DevTools/Playwright en este entorno) y subida real de un archivo (el flujo de `$_FILES` no se pudo simular por CLI). |
| UPD-465 | 05-ago-2026 | Armando | **4 campañas WA segmentadas ad-hoc (jul-01 a la fecha)** — a petición de Armando, distintas de la campaña mensual recurrente ya existente (`scripts/generar_campanas_segmentadas.php`, segmenta por # de órdenes en el mes). Esta corrida segmenta por cotizó/compró en el rango jul-01→ago-05: (1) No cotizaron en el rango pero sí antes (dormant, 40 clientes) → reusa plantilla ya aprobada `017_clientes_sin_cotizacion` (texto evergreen, sin precio ni fecha). (2) Cotizaron sin comprar en el rango (77) → `022_cotizo_sin_comprar_ago`. (3) Cotizaron y compraron en el rango (139) → `023_gracias_compra_ago_1` (Armando tuvo que renombrar tras borrar el original `023_gracias_compra_ago`, Meta no deja reusar el nombre). (4) Nunca han cotizado en toda la historia del sistema (68) → `024_primer_contacto_ago`. Las 4 quedaron como borrador en `campanas`/`campana_envios` (id 40-43), sin enviar nada — pendiente que Armando las revise/active desde el módulo Campañas. Hallazgo antes de generar: las 4 plantillas reutilizadas de la campaña de junio (`011_segmento_1` a `014_segmento_4`) traían precio congelado ($790/m² Claro 9mm "por esta semana") y fecha "hoy 30 de junio" — con el incremento del 1-ago (UPD-448) el precio real ya es $957.85, así que se descartó reusarlas para no mandar un precio equivocado a 317 clientes; se pidieron plantillas nuevas sin precio/fecha en su lugar. Ejecutado con scripts ad-hoc en scratchpad (no agregados al repo, es una corrida puntual, no recurrente) contra `api/config.php`. Aparte, en la misma sesión: contraseña del usuario operador `trazo` — Armando la resolvió directamente, sin intervención de Claude (solo se ofreció resetearla, nunca se llegó a ejecutar). |

| UPD-466 | 06-ago-2026 | Armando | Corrección de datos + envío real (no cambio de código): campaña **id=40 "Sin cotizar desde julio - 05ago2026"** (40 destinatarios, borrador desde UPD-465) tenía template `017_clientes_sin_cotizacion` — Armando decidió usar la plantilla nueva `017_clientes_sin_cotizacion_1` en su lugar. `UPDATE campanas SET template_nombre=...` (verificado antes/después) y despachado el envío real con un script CLI puntual (`php84`, no `php` — el CLI default del VPS sigue en 8.0.30, la app requiere 8.4.1+) que replica exactamente la lógica de `api/campanas.php` (`accion=enviar`) porque el MCP de MySQL es solo-lectura y ya no se puede fabricar una sesión HTTP para llamar el endpoint real (ver `feedback_no_session_simulation_testing.md`) — mismo `enviarMensajeWA()`, mismo throttle de 1s/mensaje, mismas queries de variables dinámicas. Se agregó de más una verificación de que la plantilla esté `APPROVED` en Meta antes de disparar nada (el endpoint real no la tiene, confía en que Meta rechace si no existe) — bug propio detectado y corregido en la primera corrida: la query a Meta pedía `fields=name,components` (igual que el original) pero la verificación nueva leía `status`, campo que nunca se pidió, así que abortaba con status vacío aunque la plantilla sí estaba aprobada; agregado `status` a los fields. Resultado real: 40/40 procesados, 39 entregados (29 entregados + 9 leídos + 1 enviado) y 1 fallido (Carlos Alejandro, tel 528123848411, código Meta 131049 "not delivered to maintain healthy ecosystem engagement" — throttle propio de Meta por bajo engagement de ese número, no reintentado). Script temporal borrado del scratchpad al terminar, no vive en el repo. |

| UPD-467 | 06-ago-2026 | Armando | **Comisiones de Asesores + Órdenes de Retrabajo desde Cotización — construido completo.** Esquema confirmado con Armando: Bethy/Cynthia siempre y Yahaira desde nov-2026 usan tramos por venta del mes con TASA ÚNICA sobre el total ($0-749,999.99→1%, $750k-999,999.99→1.5%, $1M+→2%); Yahaira ene-oct 2026 es 1.5% fijo; Yahaira nov-2026+ tiene además un mínimo mensual PERMANENTE de $450,000 (si no lo alcanza, comisión = $0 ese mes). "Venta del mes" = `cotizaciones.vobo_at` (mismo criterio que Ingresos del P&L, UPD-437), base `subtotal` neto. **Retrabajo (error comercial):** a diferencia del retrabajo de piso/producción ya existente (`piezas.es_retrabajo` marcado en la MISMA pieza, módulo Retrabajo de Mando — sin tocar, a petición explícita de Armando de "no mezclar los retrabajos de operación con los de comercial"), este es un flujo nuevo: el asesor, desde Cotización, marca "Es retrabajo" y selecciona Orden original (folio) → Partida → Pieza específica (nuevas acciones `retrabajo_buscar_orden`/`retrabajo_piezas_orden` en `api/cotizaciones.php`) — reutiliza `piezas.pieza_origen_id`, columna que ya existía en la BD sin usarse en ningún código. Los datos de la pieza (cristal, medidas, cpb, tp/ta, templado) se prellenan editables; el cliente queda fijo al de la orden original (validado también server-side, no solo en el frontend). La cotización de retrabajo sigue TODO el proceso normal (VoBo, producción, cobranza) pero se excluye de Ingresos (`ingresosPeriodo()`/`ingresosPorTipoPeriodo()` en `api/helpers/pnl_datos.php`, ahora con `AND c.es_retrabajo=0`) y de "ventas del mes" del asesor — el costo de material si aplica SÍ se sigue contando en Costo de Ventas (es la pérdida real que la empresa absorbe). Penalización = 50% del `subtotal` (neto) de esa cotización, descontada de la comisión el mes en que recibe VoBo — **se perdona por completo** si el cliente pagó (`saldo_pagado`) al menos 50% del `total` (con IVA) de esa misma cotización; calculado en vivo dentro de `penalizacionesRetrabajoAsesorMes()`, sin tabla de penalizaciones aparte (la cotización de retrabajo ya trae todo lo necesario). Nuevo: `cotizaciones.es_retrabajo` + `cotizaciones_partidas.pieza_origen_id` (ALTER aditivos, 1047 cotizaciones existentes quedaron en `es_retrabajo=0`), `api/helpers/comisiones_lib.php` (constantes de tramos/Yahaira + `ventasAsesorMes()`/`calcularComisionAsesorMes()`/`penalizacionesRetrabajoAsesorMes()`), `api/comisiones.php` + módulo `app/modulos/comisiones.php` ("Comisiones" en sidebar, mismo permiso `ver_contabilidad` que Bono Corte/Bitácora de Desechos — dato de nómina, no lo ven los asesores). **Supuesto propio no confirmado explícitamente:** la comisión neta nunca queda negativa ni se arrastra al mes siguiente (topa en $0) — documentado para que Armando lo corrija si no es lo que quiere. **Alcance v1:** solo cotizaciones tipo `suministro` (no maquila); 1 pieza original = 1 orden de retrabajo (si son varias piezas, se crean varias órdenes de retrabajo); sin serie de folio especial para retrabajo. Probado con dry-run real en BD dentro de transacción con ROLLBACK: pieza_origen_id/es_retrabajo viajan correctos hasta la pieza nueva al convertir a orden, Ingresos y ventas del asesor excluyen la cotización de retrabajo, penalización aplica sin pago y se perdona con pago ≥50% — confirmado 0 filas tras el rollback. Verificado además que Ingresos de julio no cambiaron con el nuevo filtro (drift de ~$5 vs UPD-439 es actividad normal de días recientes, no causado por este cambio). **Sin prueba visual en navegador** (no hay Chrome DevTools/Playwright MCP en este entorno) — pendiente que Armando/asesores prueben en vivo: crear una cotización de retrabajo real desde Cotización, y revisar el módulo Comisiones. |

| UPD-468 | 06-ago-2026 | Armando | **Reorganización visual del módulo Cotización (`app/modulos/cotizacion.php`) — sin cambios de lógica/API.** Armando reportó que la pantalla se sentía desordenada tras varios agregados sucesivos (referidos, retrabajo). Validado primero con mockup Antes/Después en Artifact (skill `frontend-design` + `artifact-design`) antes de tocar el módulo real. Cambios: (1) el checkbox rojo grande de "Es retrabajo" (UPD-467) se reemplaza por un selector de tipo tipo pill "Cotización normal / Retrabajo (corrección)" — el checkbox `fEsRetrabajo` sigue existiendo pero oculto, el pill solo lo marca vía `setTipoCotizacion()`, sin tocar `guardarCotizacion()`/`toggleRetrabajo()`. (2) Los 12 campos del encabezado (antes un solo `.form-grid` plano de 3 columnas) se agrupan en 3 bloques con eyebrow label + separador: **Proyecto** (Proyecto, Código Referido, Alerta), **Condiciones comerciales** (Descuento, Condición de pago, Crédito, Factura genérica) y **Entrega** (Tipo de entrega, Localidad, Ciudad destino, Fecha de entrega) — mismos IDs de inputs, mismo `armarPayload()`, cero cambio de datos. (3) Tabla de partidas: franja alterna explícita por índice par/impar (`i % 2`, no `nth-child` — evita que los `.srv-wrap` intercalados de servicios adicionales rompan la paridad visual), columnas numéricas (Cant/Ancho/Alto/Res/TP/TA) centradas, y sub-encabezado nuevo agrupando visualmente Res/TP/TA bajo "Taladros y resaques" (un solo `<span style="grid-column:8/11">` sobre el mismo grid de 13 columnas ya existente, sin tocar el ancho de columnas). Verificado con `php -l`; sin prueba visual en navegador (no hay Chrome DevTools/Playwright MCP en este entorno) — pendiente que Armando confirme viéndolo en vivo. |

| UPD-469 | 06-ago-2026 | Armando | Fix búsqueda de orden original en retrabajo (`api/cotizaciones.php`, `retrabajo_buscar_orden`): Armando probó buscar "397" (sin el prefijo "S-") y daba "Orden no encontrada o cancelada" — el buscador hacía coincidencia EXACTA de folio (`folio = ?`), a diferencia del resto del sistema que usa `LIKE` (`api/ordenes.php`). Cambiado a `LIKE '%folio%'`, mismo criterio. De paso se encontró la causa real del caso de prueba: no existe ninguna orden "S-397" — solo existe **MA-S-397** (id=642), que es de **Maquila**, fuera del alcance v1 del retrabajo desde Cotización (documentado desde UPD-467). Antes esto también hubiera dado "no encontrada" genérico aunque se tecleara el folio completo; ahora el mensaje es específico ("son de Maquila — por ahora el retrabajo desde Cotización solo aplica a órdenes de Suministro") tanto en la búsqueda como en la validación server-side al guardar (agregado el mismo candado `tipo==='maquila'` en la acción `crear`, defensa en profundidad por si se manda el `pieza_origen_id` sin pasar por el picker). También se agregó manejo de múltiples coincidencias (pide escribir el folio completo). Verificado con SELECT directo contra BD real: `LIKE '%397%'` regresa exactamente MA-S-397. |

| UPD-470 | 06-ago-2026 | Armando | Fix UX menor: quitado el texto explicativo del panel de retrabajo en Cotización ("El precio que le pongas a esta pieza es el valor de la pérdida a absorber...") a petición de Armando — CSS `.rt-note` también removido por quedar sin uso. || Fix de fondo: **VoBo exento de anticipo requerido para órdenes de retrabajo** (`api/finanzas.php`, acción `vobo`). Armando señaló que en la gran mayoría de los retrabajos el cliente no paga nada (la empresa absorbe la pérdida) — antes de este fix, el candado de anticipo (C-12, 50%/100% según condición de pago) trataba eso como una excepción: Lina (rol `administracion`) tenía que forzar el VoBo escribiendo un motivo a mano cada vez. Ahora, si `cotizaciones.es_retrabajo=1`, el override se aplica automático con una nota fija ("VoBo sin anticipo — orden de retrabajo, pérdida absorbida por la empresa") — Lina sigue dando el VoBo manualmente igual que siempre, solo ya no se le exige justificar cada caso. Queda igual en `correcciones_log` para trazabilidad. De paso se investigó y confirmó que el siguiente candado (Cobranza → Salida en `app/imprimir_salida.php`) **no necesita cambio**: ya permite salida con estatus de pago `en_proceso` (sin exigir cobro completo ni override) — solo `pagado` exige cobro completo, y para retrabajo basta con no usar ese estatus. Verificado con dry-run real en BD (BEGIN/ROLLBACK): cotización de retrabajo con `saldo_pagado=0` y `condicion_pago=pago_total` → `vobo_override=true` con nota automática, sin pedir motivo. |

| UPD-471 | 06-ago-2026 | Armando | Fix: partidas de flete/"otro" (servicios, sin unidad física real) en Órdenes de Compra nunca podían marcarse como recibidas — el modal "Registrar entrega" (`app/modulos/inventario.php`, `ocAbrirEntrega`) solo ofrece partidas tipo `lamina`, así que el flete se quedaba en 0% para siempre, la OC nunca cerraba automáticamente (`api/ordenes_compra.php`, `registrar_entrega`), y el botón "Registrar pago" (gateado a `estado==='cerrada'` en el frontend, más estricto que el backend que ya permite `abierta`/`cerrada`) nunca aparecía — caso real: APEX-0216 con 100% de láminas recibidas pero sin poder registrar el pago. Reportado por Armando junto con APEX-0210/APEX-0208 (ya pagadas, mismo síntoma en la barra de % recibido). Regla de negocio confirmada: el flete/servicio se da por completado al 100% en cuanto TODO el material (partidas `lamina`) de la OC ya se recibió. Fix de código (2 archivos): (1) `registrar_entrega` ahora auto-marca `cantidad_recibida=cantidad` en partidas `flete`/`otro` de la OC cuando las partidas `lamina` quedan 100% completas, antes de evaluar si la OC cierra; (2) botón "Registrar pago" (lista de OCs y calendario de pagos) ahora se muestra también con `estado==='abierta'`, igual que ya lo permite el backend. Corrección de datos retroactiva (las 3 OCs ya existían antes del fix, así que el código nuevo no las alcanzaba): partidas flete/otro de OC 40 (APEX-0208, id=75), OC 42 (APEX-0210, id=80 "Ajuste de Precio") y OC 48 (APEX-0216, id=91) marcadas recibidas; OC 48 transicionada de `abierta` a `cerrada` (fecha_pago_programada=06-ago, dias_credito=0) — mismo criterio que el cierre automático real. Verificado con SELECT antes/después. |

**Próximo UPD disponible: UPD-472**
