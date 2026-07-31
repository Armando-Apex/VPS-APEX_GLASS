# APEX GLASS — MEMORIA ÚNICA DEL PROYECTO
# Sistema de Rastreo de Producción (Templadora Noreste, S.A. de C.V.)
# Última actualización: 31 julio 2026 | Próximo UPD disponible: UPD-421

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
| ALTA | Armando | **Depósito a Cuenta / Saldo a Favor — rediseño en curso (10-jul-2026), NADA IMPLEMENTADO TODAVÍA.** Problema raíz: hoy se crea una orden con m² inventados solo para poder registrar un abono/depósito del cliente cuando aún no sabe qué va a comprar — esto duplica el ingreso reportado (una vez en la orden placeholder, otra vez cuando el cliente devenga el saldo en una orden real). Hallazgo clave de la investigación: **el mecanismo correcto YA EXISTE** (`clientes_saldo_favor` + `api/saldo_favor.php?accion=deposito` + tab "Saldo a Favor" en `finanzas_cobranza.php`) pero no se está usando. Recomendaciones dadas (sin confirmar/implementar): badge junto al folio en vez de nomenclatura de folio nueva; botón "Registrar Depósito" en la ficha del cliente; columna informativa "Pagado con Saldo a Favor" en Ventas y Cobranza (no afecta Acumulado en Pedidos); el depósito debería aparecer como fila de "cobranza" separada el día que se registra, sin sumar a "ventas" hasta que se devengue en una orden real (pendiente que Armando lo confirme). También se encontraron 2 bugs en el mecanismo existente sin arreglar (falta guard anti-doble-clic en `saldo_favor.php`, XSS en `sfSelCliente` mismo patrón que UPD-275) y un blast radius completo de 7+ queries en `api/reporte_direccion.php`/`api/inventario.php`/`portal/tablero.php` que habría que filtrar si se limpian órdenes placeholder históricas (falta que Armando pase los folios, no se detectan por texto). Detalle completo de la investigación, decisiones abiertas y citas textuales de Armando en la memoria de Claude (`project_deposito_cuenta_saldo_favor.md`) | Pendiente — diseño en discusión |
| ALTA | Armando | QR de salida por chofer (UPD-319) — verificado 13-jul-2026: las 4 plantillas Meta (`chofer_en_ruta_cliente`/`siguiente_entrega_cliente`/`chofer_en_ruta_asesor`/`siguiente_entrega_asesor`) ya están **APPROVED** (confirmado consultando la Graph API directo); `usuarios.telefono` de Bethy (8134000145) y Cynthia (8140051992) ya está cargado; nombres de choferes (`Juan Roberto García`, `Víctor Bautista`) ya son reales, no genéricos. Flujo completo funcional. Solo falta: prueba visual con un chofer real escaneando el QR físico | HECHO (config) — falta prueba física |
| ALTA | Mando | **GPS ProTrack365 en Logística Rutas (ver UPD-327/328, 338/339)** — HECHO: frontend conectado (línea única al siguiente destino, GPS en vivo), cron `scripts/gps_tracker.php` corriendo cada minuto guardando histórico en `gps_posiciones` y detectando llegada/movimiento. Sigue pendiente pedir al distribuidor la Open API oficial para no depender a largo plazo del fallback web no documentado (`permission denied` en la oficial) | Mayormente HECHO — falta Open API oficial del distribuidor |
| MEDIA | Mando | Radio de "llegada GPS" (250m, ver UPD-338/339) — con la primera prueba real el camión quedó a 268m sin disparar. Evaluar subir a 300-350m con más pruebas | Pendiente |
| MEDIA | Mando | Trazabilidad de rutas (UPD-339/340) — falta prueba con un chofer real completando el flujo físico completo (escaneo QR salida → manejar → llegar) para confirmar que las 4 columnas de la tabla en Productividad se llenan solas | Pendiente |
| MEDIA | Ambos | Rutas de Entrega — activar avisos WA (UPD-355): redactar y aprobar en Meta las plantillas `ruta_iniciada_eta_cliente` (ETA a la 1ra parada) y `ruta_en_curso_cliente` (aviso genérico "en camino, llega hoy" al resto); confirmar si se sigue reusando `siguiente_entrega_cliente` para el aviso "eres el siguiente" tras cada entrega confirmada; una vez listas, cambiar `RUTA_WA_AVISOS_ACTIVO` a `true` en `api/rutas_lib.php` | Pendiente |
| MEDIA | Ambos | Rutas de Entrega — probar con un chofer real el nuevo significado del QR de hoja de ruta (ahora escanea al ENTREGAR, no al salir) y confirmar que el mapa avanza solo a la siguiente parada | Pendiente |
| MEDIA | Armando | Revisar 3 posibles pagos de OC duplicados encontrados al arreglar UPD-420 (no se tocaron, requieren tu autorización para borrar): OC APEX-0190 (id=22, pagos id=12 $38,161.63 + id=13 $38,161.68, mismo día 14-jul, 8 min de diferencia), APEX-0193 (id=25, pagos id=14 y 17, $69,744.00 cada uno, 14 y 16-jul), APEX-0194 (id=26, pagos id=15 y 18, $4,078.78 cada uno, 14 y 16-jul). El botón de "Registrar pago" ya tiene candado anti-doble-clic (`compras.php` línea 899), así que no parece ser doble clic — más bien registro manual duplicado. Si se confirma, hay que borrar el pago duplicado de cada OC y verificar que el estado/fecha de pago de la OC no haya quedado mal por el monto extra | Pendiente — confirmar con Armando antes de tocar `oc_pagos` |
| BAJA | Armando | **Contabilidad (WIP)** — solo existe el Catálogo de Cuentas (`app/modulos/contabilidad_catalogo.php` + tabla `cuentas_contables`, 13 cuentas seed: 4 Ingresos, 5 Costo de Ventas, 6 Gastos Operativos, 7 Financieros, 8 Impuestos). Es la base de un proyecto de Estado de Resultados (P&L) que no arrancó la parte de movimientos/transacciones ni el reporte en sí — el módulo no está conectado a ningún dato real todavía (banner "no afecta ningún otro módulo del sistema"). Sin fecha ni alcance definido, documentado 30-jul-2026 porque no tenía entrada previa en CLAUDE.md pese a ya estar en el sidebar | Pendiente — sin diseño del alcance completo aún |
| MEDIA | Mando | **AVISO PARA MANDO — revisar en tu próxima sesión (30-jul-2026):** el hook de auto-commit subió `scripts/gps_cache/` (incl. `apex_gps_token.json` con el `web_token` de sesión de ProTrack365) al repo de GitHub en el commit `b3dc5bb`. Verificado: ese token puntual ya había expirado (`web_exp` 19:09 UTC del mismo día) al momento de encontrarlo, no era explotable. La cuenta/contraseña real (`PROTRACK_ACCOUNT`/`PROTRACK_PASSWORD`) vive en `.env` fuera del repo y NO se filtró. Ya se agregó `scripts/gps_cache/` a `.gitignore` y se sacó del tracking (`git rm --cached`) para que no se repita — Armando decidió NO purgar el historial de git por ahora (repo privado, dato ya no explotable). Mando: confirma que el cache de GPS (`gps_tracker.php`, `gps_lib.php`) sigue funcionando bien sin estar trackeado en git (debería ser transparente, es solo un cache en disco) | Pendiente — solo revisión/confirmación |
| MEDIA | Armando | Videos de marketing con Remotion (UPD-351/352) — herramienta instalada y funcional en `herramientas/video-marketing/` (fuera del webroot). 3 videos de muestra hechos: promo genérico de marca, demo del Portal de Clientes (escritorio) y demo del Portal de Clientes (vertical, formato celular con marco de teléfono). Ninguno conectado todavía a una campaña real. Falta: (1) que Armando confirme si le gustan y para qué campaña específica los quiere usar, (2) revisar si `app/modulos/campanas.php` necesita soporte para plantillas Meta con header tipo VIDEO (hoy el wizard solo maneja `header_image_url` de imagen) | Pendiente — esperando feedback de Armando |
| BAJA | Ambos | **Sprint1 (dinero) — migración SQL de A-2 descartada por decisión explícita** (ver UPD-360): 3 UPDATEs que hubieran corregido `cotizaciones.subtotal/iva/total/saldo_pendiente` HISTÓRICOS con la fórmula canónica (8 cotizaciones activas con servicios, +$1,760.12). Armando confirmó 21-jul-2026 que NO quiere editar datos históricos — solo quería que dejara de fallar hacia adelante. Verificado: los 10 parches de código de UPD-359 (incl. helper `api/helpers/totales.php`) ya están en producción, así que toda cotización/pago/corrección nueva desde entonces ya usa la fórmula canónica. Si más adelante se decide corregir el histórico, el SQL de los 3 UPDATEs sigue documentado en `/home/mando/files_apexglass/parches_sprint1.md` sección A-2 | Descartado — código hacia adelante ya OK, no se tocan datos viejos |
| MEDIA | Armando | Sprint1 (dinero) — revisar manualmente **COT-0105** (rechazada históricamente): `saldo_pagado=$1,297.05` nunca se reseteó a 0 aunque el saldo a favor ya se depositó correctamente (mismo monto) — hoy se cuenta doble en reportes hasta que se corrija a mano | Pendiente |
| MEDIA | Mando | **Sprint2 (Producción/Piso) — falta C-6** (ver UPD-361): "Registrar consumo" en el Optimizador de Corte (`api/optimizador_corte.php`) sigue sin descontar inventario real — solo escribe en la tabla muerta `corte_laminas`, el stock nunca baja y las piezas no pasan a `en_corte` (riesgo de doble corte en la siguiente corrida). Mando decidió manejar el descuento de inventario de otra forma; se retoma al cerrar el sprint | Pendiente — Mando lo maneja diferente |
| BAJA | Ambos | Sprint2 — 2 cambios de comportamiento visibles para piso ya en producción (UPD-361): (1) el botón "Confirmar omisión" en `operador.php` ya no funciona para operadores/choferes, solo jefe_piso+ (C-4); (2) escanear QR/CNC ya no funciona en órdenes sin VoBo — `pendiente_vobo`/`cancelada`/`rechazada`/`entregada` (A-4). Avisar a piso si aún no se ha comunicado | Pendiente — confirmar que piso ya lo sabe |

---

## 13. HISTORIAL DE ACTUALIZACIONES

REGLA: Cada cambio se agrega aquí. NUNCA se elimina. Código UPD secuencial e irrepetible.
Próximo UPD disponible: **UPD-414**

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

**Próximo UPD disponible: UPD-421**
