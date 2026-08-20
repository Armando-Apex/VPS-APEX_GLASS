# APEX GLASS — MEMORIA ÚNICA DEL PROYECTO
# Sistema de Rastreo de Producción (Templadora Noreste, S.A. de C.V.)
# Última actualización: 20 agosto 2026 | Próximo UPD disponible: UPD-530

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
| MEDIA | Armando | **Costo de compra por período (Reporte Dirección → Rentabilidad, `api/inventario.php` accion=costo_promedio, UPD-473) no es un costeo promedio real.** Armando detectó la limitación al preguntar sobre el impacto de comprar 10 láminas caras de Claro 9mm: `costo_prom_m2_mes_actual`/`FECHA_INICIO_PRECIO_REAL` solo suman las compras (`inventario_compras.fecha_compra`) DENTRO del período seleccionado — no incluyen el valor del inventario que ya estaba en almacén al inicio del período. Es "cuánto pagaste por lo comprado en el rango", no "cuánto vale hoy el inventario disponible" (que requeriría saldo inicial a la fecha de corte + compras del período, ponderado por m²). Hoy no afecta a Claro 9mm porque su stock físico ya está en 0 desde antes de agosto, pero en cualquier tipo/espesor que sí tenga stock arrastrado de un mes anterior, ese inventario viejo se ignora por completo en este número aunque siga físicamente en la bodega. Armando decidió dejarlo como pendiente, sin implementar por ahora. | Pendiente — diseño a definir |
| MEDIA | Armando | **Insulado/espaciador — Paso 2: automatizar el cálculo por metro lineal (ver UPD-498/499).** Paso 1 (UPD-498) y Paso 2 (UPD-499) HECHOS: al agregar un servicio `ml` a una partida el módulo autocalcula el perímetro y propone la mitad de piezas; el alta por UI ya acepta decimales; el catálogo de servicios permite marcar "por pieza / por m.l." al crear/editar. Falta solo: (1) que Armando registre en el catálogo los demás espaciadores por tamaño con su precio (autoservicio desde el modal "Catálogo de Servicios"), y (2) prueba visual en navegador (agregar el espaciador a una partida y confirmar que el perímetro se llena solo). | HECHO UPD-499 — falta registrar precios por tamaño + prueba visual |
| BAJA | Armando | S2-08 (auditoría, UPD-503): rollover de folio `Z-999`→`[-001` rompe QR — hoy en letra S, sin urgencia. Requiere `ALTER TABLE folios_control` (VARCHAR(2)) + tocar QR/escáner. Diseño propuesto: doble letra estilo Excel (AA, AB...) | Pendiente — sin urgencia |
| MEDIA | Ambos | S2-14 fase 2 (auditoría, UPD-504): quitar el fallback de autorización de portal por nombre (`cliente_id OR cliente_nombre`) — hoy solo se loguea cada ocurrencia (`[S2-14]` en error_log). Revisar el log en unas semanas; si está limpio, quitar el fallback; si no, corregir esas órdenes primero | Pendiente — esperando datos del log |
| ALTA | Armando | Sprint 2 S2-11 (CSRF, UPD-504): falta prueba visual en navegador — guardar/editar en 2-3 módulos clave (Cotización, Finanzas, Inventario) para confirmar que el token no rompió nada | Confirmado en producción 20-ago-2026: Armando reportó "Token CSRF inválido o ausente" tras dejar la pestaña abierta un rato (token vencido en la sesión del servidor); un F5 lo resolvió al instante — el candado se comportó como está diseñado (bloquea en vez de dejar pasar, y se repara solo con recarga). No fue la prueba sistemática de 2-3 módulos originalmente planeada, pero sí una confirmación real de que el mecanismo funciona sin romper el guardado normal |
| BAJA | Armando | Sprint 2 S2-12: falta correr `git rm --cached error_log api/error_log app/error_log app/modulos/error_log` + commit (Claude no corre git) | Pendiente — acción manual de Armando |
| MEDIA | Mando | **Sprint 3 (auditoría, UPD-505) — falta lo más grande.** Hecho: S3-01 infra, S3-03, S3-04, S3-07, S3-08, S3-02 parcial (2 de ~8 archivos). Falta: sweep de emojis en `operador.php` (~104), `produccion_estaciones.php` (SmartTV), `admin_comunicados.php`, `logistica_rutas.php`, `facturacion.php`, `clientes.php`, `chofer_ruta.php`; migrar los 338 `alert()`/`confirm()` a `toast()`/`confirmar()` (S3-01 resto); S3-05 (labels for/id + aria-label); S3-06 (validación visual completa); S3-09 (paginación "Mostrando X–Y de Z"); S3-10 (responsive en 7 módulos densos) | Pendiente — retomar en sesión dedicada |
| ALTA | Armando | **Promo Estados WhatsApp por volumen (UPD-516/517) — falta el gráfico + prueba visual.** Código construido y probado con dry-run en BD (código personal CTN-###PROMO). Tramos vigentes (ajustados en UPD-517): 1-4→5%, 5-50→7.5%, 51-99→12.5%, 100+→20%. Riesgo conocido sin confirmar por Armando: el tramo 100+ al 20% da solo ~$341/m² de utilidad en Claro 9mm si se usa el costo de compra más caro del mes (por debajo del piso de $400-550/m² discutido) — sí cumple ($416/m²) con costo promedio. Falta: (1) que Armando diseñe y publique el gráfico/texto del Estado de WhatsApp explicando la tabla de tramos y pidiendo al cliente decir su código al asesor; (2) prueba visual en navegador creando una cotización real con el código (no hay Chrome DevTools/Playwright MCP en la sesión que lo construyó); (3) si se quiere medir el ROI de la campaña más adelante, ya se puede reportar por `cotizaciones.promocion_id` — no hay dashboard dedicado todavía, se arma bajo pedido | Pendiente — falta gráfico de Armando + prueba visual |
| ALTA | Ambos | **Auditoría v2 pre-release 19-ago-2026 (`/home/mando/files_apexglass/auditoria_19_08_26/`) — Sprints A/B/C hechos y probados, Sprint D apenas arrancado (UPD-525 a 528).** Falta: A-04 IDOR portal por nombre (necesita backfill de `cliente_id` en 216 órdenes antes de quitar el fallback — decisión de Mando pendiente); C-04 rotar token WA `apex_wh_2026` (confirmado que sigue siendo el mismo en producción — requiere acceso de Armando a Meta Business Manager, Mando no puede hacerlo); C-05 `git rm --cached` de los 4 `error_log` (acción manual, mismo pendiente que S2-12); D-02 descartado a petición de Mando (set de íconos SVG no cubre todos los emojis de `orden.php`, se deja como está); D-03 a D-07 (colores `#94a3b8` residuales, labels for/id, paginación resumen.php, estados vacío/error en orden.php, media queries) sin empezar | Pendiente — retomar Sprint D en sesión dedicada, A-04/C-04/C-05 requieren decisión/acción de Armando o Mando |

---

## 13. HISTORIAL DE ACTUALIZACIONES

REGLA: Cada cambio se agrega aquí. NUNCA se elimina. Código UPD secuencial e irrepetible.
Próximo UPD disponible: **UPD-530**

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

### Bloque actual: UPD-500 en adelante

| Código | Fecha | Resp. | Descripción |
|---|---|---|---|
| UPD-500 | 13-ago-2026 | Mando | Auditoría externa 13-ago-2026 (`/home/mando/files_apexglass/auditoria_13_08_26/`), Sprint 1 parcial: S1-08 fail-closed en `backup_runner.php` (BACKUP_TOKEN vacío ya no da bypass, `hash_equals`); S1-09 XSS de `cliente_nombre`/`asesor`/`cristal` en `admin_ordenes.php` (helpers `escHtml`/`escJs`); S1-05 guard anti-doble-clic en "Guardar cotización". S1-06 (auth en `api/estaciones.php`) queda sin tocar por decisión consciente (es el endpoint del SmartTV sin login). Faltan S1-01 a S1-04 (dinero: descuento_referido en Finanzas/WA, IVA doble en OC impresa, clamp de descuento) — pausados a petición explícita, plan documentado en `/home/mando/.claude/plans/primero-hacemos-la-planeacion-fancy-sutherland.md`. |
| UPD-501 | 13-ago-2026 | Mando | S1-07 (auditoría) resuelto con cifrado reversible en vez de solo-hash, a petición de Armando (necesita seguir viendo/copiando/reenviando la contraseña real desde el ERP). Nuevo `api/helpers/crypto.php` (AES-256-GCM, llave `PORTAL_PASS_KEY` en `.env`, guardada aparte por Armando). `portal_password` ya no se guarda en claro: se cifra en `api/portal_clientes.php` (generar/reenviar) y se descifra al leer en `api/clientes.php` — el login sigue usando solo `portal_password_hash` (bcrypt), sin cambio. Migradas las 60 contraseñas existentes (backup previo + verificación round-trip por fila + comparación contra su hash bcrypt, 60/60 OK). UI de `app/modulos/clientes.php` sin cambios — el descifrado es transparente para el frontend. |

| UPD-502 | 13-ago-2026 | Mando | Sprint 2 lote A (auditoría 13-ago): S2-01 quita `reproceso` de `actualizar_estatus.php` (callejón sin salida). S2-03 fix IVA por línea en `facturapi.php` (evita centavos de diferencia vs. FacturAPI). S2-04 guard anti-doble-clic en `api/saldo_favor.php`. S2-02 se deja tal cual por decisión de Armando. |
| UPD-503 | 13-ago-2026 | Mando | Sprint 2 lote B: S2-05 rechaza medidas negativas en `cotizaciones.php`/`maquila.php`. S2-06 `actualizar_estatus_masivo.php` ya no confía `usuario_id` del body. S2-07 `JSON.parse` con try/catch en `facturacion.php`. S2-09 `recibir_orden.php` aborta si importa 0 piezas. S2-08 (folio Z-999) documentado como pendiente, sin urgencia. |
| UPD-504 | 13-ago-2026 | Mando | Sprint 2 lote C: S2-10 `api/session_boot.php` nuevo (cookie segura) en 16 archivos. S2-11 CSRF con synchronizer token en `requireSessionApi()` + wrapper `app/csrf_fetch.js` en 10 entry points + 3 XHR parcheados a mano; portal no se tocó (sesión propia). Verificado con tests de lógica aislados, falta prueba en navegador. S2-12/S2-13 `.htaccess`/`.gitignore` de uploads y logs — falta que Armando corra `git rm --cached` de los `error_log`. S2-14 fase 1: solo log `[S2-14]` del fallback de portal por nombre, sin cambiar comportamiento. |

| UPD-505 | 13-ago-2026 | Mando | Sprint 3 (auditoría, visual/UX) parcial: S3-03 fix `body{}` en 4 módulos contables (retargeteado a `.main`). S3-04 contraste WCAG, 249 ocurrencias `#94a3b8`→`var(--c-muted)`. S3-01 infraestructura (`toast()`/`confirmar()` global en `utils.js`+CSS en `dashboard.php`, sin migrar los 338 `alert()`/`confirm()` todavía). S3-07 `.state-error` con reintentar, wireado en `reporte_direccion.php`(×2)/`corte_dashboard.php`. S3-08 estados vacíos, wireado en `productividad.php`(×2)/`optimizador.php`/`orden.php`. S3-02 parcial: `admin_ordenes.php`+`inventario.php` (6 emojis) + ~15 íconos SVG nuevos en `api/helpers/icons.php`/`utils.js` para el resto. Nota operativa: mientras se editaban archivos JS compartidos en vivo, un usuario vio "Error de conexión" en Productividad por caché de navegador desactualizado (`utils.js` viejo sin las funciones nuevas) — se resolvió con refresh forzado, sin bug real de código. Falta lo más grande: `operador.php` (~104 emojis), `produccion_estaciones.php` (SmartTV, ~25), `admin_comunicados.php`/`logistica_rutas.php`/`facturacion.php`/`clientes.php`/`chofer_ruta.php`, y S3-05/06/09/10 completos — pausado a petición explícita, retomar en sesión dedicada. |

| UPD-506 | 13-ago-2026 | Armando | **4 tipos de vidrio nuevos para dar de alta láminas en Inventario** (reportado: al cargar láminas faltaban Timeless, BioClean, Bronce, Espejo Filtra). Causa: el `<select>` de tipo en el formulario de láminas (`app/modulos/inventario.php`) estaba desincronizado del ENUM `laminas.tipo` — **bronce** ya existía en el ENUM desde UPD-440 pero nunca se agregó ni al menú ni al mapa `tipoLabel` (se veía como "bronce" crudo en la tabla de Stock si se registraba); los otros 3 eran totalmente nuevos. Verificados los nombres contra el catálogo `cristales`: existen "Timeless 9mm" y "Espejo Filtra 6mm" (es **"Espejo Filtra"**, NO "Espejo Filtrasol"); "BioClean" no existía en ningún lado, es nuevo. Cambios: (1) `ALTER TABLE laminas MODIFY tipo ENUM(...)` agregando `timeless`, `bioclean`, `espejo_filtra` (bronce ya estaba) — listados TODOS los valores existentes + nuevos según la regla del proyecto; verificado que la columna conserva NOT NULL sin default, igual que antes. (2) 4 `<option>` nuevas en el menú (Bronce, Timeless, BioClean, Espejo Filtra). (3) Las 4 etiquetas en el mapa JS `tipoLabel` para que se lean bien en la tabla de Stock. Ejecutado el ALTER como root vía socket (MCP MySQL es solo-lectura); `php -l` OK. **Nota para P&L/costeo:** estos tipos nuevos aún no tienen precio de compra de referencia ni cobertura en la normalización REGEXP de `piezas.cristal` (`api/helpers/pnl_datos.php`), así que si se venden, su Costo de Ventas no se costeará hasta registrar una compra y (si el REGEXP no lo captura por nombre) extender el mapeo — pendiente aparte, fuera del alcance de este pedido. || En la misma sesión: se diagnosticó por qué "no cargaba el stock" en `?m=inventario` — backend sano (SQL de `api/laminas.php?accion=stock` corre bien, `iconoJS()` existe en el `utils.js` del servidor); la causa es **caché del navegador** con un `utils.js` viejo (sin `iconoJS`) tras las ediciones en vivo del Sprint 3 (UPD-505), porque `dashboard.php` carga `utils.js`/`csrf_fetch.js` sin parámetro de versión (`?v=`). Solución inmediata dada a Armando: recarga forzada (Ctrl/Cmd+Shift+R). Arreglo de fondo propuesto (agregar `?v=` a esos scripts en `dashboard.php`) — **Armando dio OK (más gente batallando) y se APLICÓ**: `csrf_fetch.js`/`utils.js` en `dashboard.php` ahora llevan `?v=<?= filemtime(__DIR__.'/archivo.js') ?>` (se usó `filemtime` en vez de fecha fija, así el navegador re-descarga solo cada vez que el archivo cambie, sin bumping manual; fallback a `date('Ymd')` si `filemtime` falla). `php -l` OK, render verificado (resuelve al timestamp del archivo). Como `dashboard.php` es PHP (no se cachea como el JS), la gente que ahora batalla solo necesita **UN reload normal (F5)** para recibir el HTML nuevo con la URL versionada — de ahí en adelante es automático.

| UPD-507 | 13-ago-2026 | Armando | **Campañas WhatsApp — soporte de header de VIDEO en el módulo de Campañas** (antes solo imagen; era limitación conocida documentada desde el bloque 251-397). Armando quiere lanzar 3 campañas: 2 CRM personalizadas + 1 genérica a prospectos con el video "Tiempos de respuesta" en el header. Se construyó el soporte de video de punta a punta: (1) **`api/campanas.php`** (envío): se detecta el tipo de media del header (`image`/`video`) por extensión de URL como default y se corrige con el MIME real tras descargar el archivo (`strncmp($mime,'video/',6)`); el bloque de subida a Media API ya era agnóstico al tipo (usa `mime_content_type`), solo se agregó la variable `$headerMediaTipo`; el componente `header` ahora arma `{type: image|video, <tipo>:{id|link}}` en vez de fijar `image`. (2) **`app/modulos/campanas.php`** (asistente): el campo de URL del header ahora se muestra también cuando `header_format==='VIDEO'` (antes solo IMAGE), con label/placeholder/ayuda dinámicos (video: "MP4 H.264+AAC, máx 16MB") y validación de requerido para VIDEO; se agregaron ids `cmpHeaderImgLabel`/`cmpHeaderImgHint`. Sin cambio de esquema — el video reutiliza la columna `campanas.header_image_url`. (3) **Video hospedado público** en `public_html/produccion/media/campanas/tiempos_respuesta_wa.mp4` (copia del último render, UPD-497; 3.7MB, H.264+AAC 1080×1080 45s — cumple límites de Meta) → URL `https://apex.glass/produccion/media/campanas/tiempos_respuesta_wa.mp4` (probado HTTP 206, `video/mp4`; más confiable que el handle `scontent.whatsapp.net` de Meta que expira). **Prueba real ejecutada** (sin enviar mensajes): subida del video a la Media API de Meta vía el mismo código del envío → **HTTP 200, media_id obtenido** — confirma que Meta acepta el formato y que todo el camino de video funciona. `php -l` OK en ambos archivos. **Segmentos calculados** (para cuando Armando cree las plantillas en Meta y se armen los borradores): **S1 CRM nunca han cotizado** (desde el inicio) = 70 (67 con tel) → plantilla propuesta `025_crm_nunca_cotizo_ago`; **S2 CRM cotizaron pero NO en agosto** = 146 (143 con tel) → `026_crm_reactivar_agosto`; **S3 prospectos NL+Coah+Tamps genérico+video, excluyendo fallidos en los últimos 2 envíos a prospectos** (camp. 32 "Magnos" 15-jul + camp. 36/37/38 "Incremento" 30-jul; 856 fallidos excluidos de una base de 2,226) = 1,409 (NL 1,185 · Coah 187 · Tamps 37) → `027_prospectos_video_ago` (header VIDEO, sin variables). **Traslape de 142 personas** entre los CRM personalizados (S1+S2) y S3 (mismo teléfono) — recomendación dada: excluirlas de S3 para que no reciban 2 mensajes (dejaría S3 en ~1,267); pendiente que Armando confirme al armar los borradores. **Bloqueante:** las 3 plantillas Meta aún no existen — Armando debe crearlas en Meta BM (categoría MARKETING; S1/S2 con variable `{{1}}`=nombre; S3 con header VIDEO y sin variables) y esperar APPROVED; luego Claude arma los 3 borradores con las listas exactas (dedup aplicado) listos para enviar. **Sin prueba visual en navegador** del asistente (no hay Chrome DevTools/Playwright MCP en esta sesión) — pendiente que Armando confirme que el campo de video aparece al elegir la plantilla de video. |

| UPD-508 | 14-ago-2026 | Armando | **Visibilidad cruzada de Cotizaciones y Órdenes entre asesores.** A petición de Armando: los asesores (Bethy/Cynthia/Yahaira) ahora ven las cotizaciones y órdenes de TODOS los asesores, no solo las propias — antes `api/cotizaciones.php` (lista) y `api/ordenes.php` (las 4 secciones) filtraban duro por `asesor_nombre LIKE '%nombre_sesion%'` cuando `rol==='comercial'`; se quitó ese filtro en ambos archivos (dejando `$filtroAsesor`/`$paramAsesor` siempre vacíos). A propósito **NO se tocó** el candado A-7 de `api/cotizaciones.php` (líneas ~209 y ~696) que solo deja al asesor dueño editar/convertir SU cotización — eso sigue igual, es un candado de edición, no de visibilidad. Para no revolverse entre ellos: (1) tag de color por asesor — badge morado (Bethy), teal (Cynthia), rosa (Yahaira), gris (otros/interno) — en la columna Asesor de `app/modulos/cotizaciones.php` (tabla) y `app/modulos/ordenes.php` (las 4 secciones: por iniciar/en proceso/listas/entregadas); (2) columna "Asesor" de Cotizaciones ahora es clicable para ordenar A-Z/Z-A; (3) Órdenes ya tenía botones de orden (# Folio/A-Z Cliente/Fecha) — se agregó un 4° botón "A-Z Asesor" al mismo patrón. Todo el filtrado/orden es client-side sobre los datos ya cargados, sin cambios de paginación. A propósito NO se tocó `api/dashboard.php` (Resumen/KPIs), que también filtra por asesor propio — Armando solo pidió Cotizaciones y Órdenes; avisar si también quiere que el Resumen muestre KPIs de todos. Verificado con `php -l` en los 4 archivos + extracción y validación del `<script>` embebido con `node --check` en los 2 módulos frontend. Sin prueba visual en navegador — pendiente que Armando/los asesores lo confirmen viéndolo en vivo. |

| UPD-509 | 14-ago-2026 | Armando | Seguimiento a UPD-508: dropdown "Todos los asesores" agregado junto a los tabs (Cotizaciones/Órdenes/Canceladas/Rechazadas/Inactivas) en `app/modulos/cotizaciones.php` — se puebla dinámicamente con los nombres reales encontrados en la data ya cargada (no hardcodeado), ordenado alfabéticamente. Al filtrar por un asesor, tanto la tabla como los contadores de cada tab se recalculan sobre ese subconjunto (antes los contadores ignoraban cualquier filtro, solo el buscador de texto libre se dejó igual: sigue sin afectar contadores, mismo comportamiento previo). Combinable con el buscador de texto y con el orden A-Z/Z-A por asesor de UPD-508. Fix de seguridad aplicado en la misma sesión (revisión automática detectó el hallazgo): `cotPoblarAsesores()` armaba las `<option>` del dropdown concatenando HTML a mano (`'<option value="'+n.replace(/"/g,...)+'">'+n+'</option>'`), que solo escapaba comillas dobles — no `<`, `>` ni `&`; si algún `asesor_nombre` llegara a contener esos caracteres, quedaba expuesto a XSS. Cambiado a `document.createElement('option')` + `.textContent`/`.value`, que el navegador encodea de forma segura por diseño. Verificado con `php -l` + `node --check` del `<script>` embebido. Sin prueba visual en navegador — pendiente que Armando lo confirme viéndolo en vivo. |

| UPD-510 | 14-ago-2026 | Armando | **Tag + filtro de "Retrabajo" en Cobranza.** Hallazgo al explicarle a Armando el flujo del retrabajo comercial (UPD-467/470): el VoBo de una orden de retrabajo no toca `saldo_pendiente/saldo_pagado` para nada (correcto, es solo el candado de anticipo el que se brinca) — pero eso significa que esa orden aparece en Cobranza (`?m=finanzas_cobranza`) exactamente igual que cualquier orden con saldo pendiente real, con el total completo sin cobrar y sin ninguna marca, aunque el sistema ya sabe (por diseño) que la empresa absorbe esa pérdida y no la cuenta como venta en el P&L ni en comisiones. Fix: (1) `api/finanzas.php` (`accion=cobranza`) ahora regresa `es_retrabajo` en cada fila (no se tocó ningún otro campo/cálculo — el saldo se sigue mostrando igual, a propósito, esto es solo visibilidad); (2) `app/modulos/finanzas_cobranza.php` — badge ámbar "Retrabajo — no cobrar" debajo del folio cuando `es_retrabajo=1`, y filtro nuevo "Retrabajo" (Todos/Sin retrabajo/Solo retrabajo) junto a los demás filtros de la barra — como los KPIs (Total facturado/Cobrado/Por cobrar) ya se calculan sobre la lista filtrada, elegir "Sin retrabajo" también saca esas órdenes de los totales sin necesidad de tocar `renderKpis()`. Verificado con `php -l` en ambos archivos + `node --check` del `<script>` embebido del módulo. Sin prueba visual en navegador — pendiente que Armando/Lina lo confirmen viéndolo en vivo. |

| UPD-511 | 14-ago-2026 | Armando | Seguimiento a UPD-510: mismo aviso de "Retrabajo" ahora también en el módulo **VoBo de Órdenes** (`?m=finanzas_vobo`), que era el que Armando señaló que no avisaba nada. `api/finanzas.php` — agregado `es_retrabajo` a las queries `lista_vobo` y `detalle` (antes solo se había agregado a `cobranza` en UPD-510). `app/modulos/finanzas_vobo.php`: (1) badge ámbar "Retrabajo" debajo del folio en la tabla de pendientes; (2) banner de advertencia (mismo estilo ya usado ahí para "Saldo a favor disponible") en el panel de detalle de la orden — donde Lina ve el botón de "Confirmar VoBo" — con el texto "Orden de retrabajo — la empresa absorbe la pérdida, no se exige anticipo para dar VoBo. No perseguir este cobro en Cobranza", para que quede claro ANTES de dar el VoBo, no solo después en Cobranza (UPD-510). Verificado con `php -l` en ambos archivos + `node --check` del `<script>` embebido. Sin prueba visual en navegador — pendiente que Armando/Lina lo confirmen viéndolo en vivo. |

| UPD-512 | 14-ago-2026 | Armando | **Reporte Dirección — pestaña "Ventas y Cobranza" ahora excluye retrabajo, mismo criterio que el P&L.** Al explicarle a Armando el manejo del saldo de retrabajo se encontró que `api/reporte_direccion.php` tenía el mismo hueco que Cobranza tenía antes de UPD-510: 5 queries de esa pestaña sumaban `cotizaciones.total`/`saldo_pagado` sin excluir `es_retrabajo=1` — a diferencia del Estado de Resultados (`pnl_datos.php`, UPD-467) que sí las excluye de Ingresos. Afectaba: (1) tarjetas KPI "Ventas/Cobrado/Por cobrar/Ticket promedio" (Resumen financiero del período), (2) Top 5 clientes por ventas, (3) Top 5 clientes por pedidos, (4) Top 5 clientes por m², (5) Ventas por asesor. Se agregó `AND c.es_retrabajo = 0` a las 5 queries — mismo criterio en toda la pestaña para que no queden números inconsistentes entre sí en la misma pantalla. Verificado con lectura directa en BD (agosto 1-14): "Ventas" bajaba de $509,133.13 a $508,610.10 al excluir 1 retrabajo real del período ($523.03 de diferencia). No se tocó el KPI "Reproceso" (usa `piezas.es_retrabajo`, concepto distinto de retrabajo de piso/producción, UPD-432/484) ni ningún otro reporte fuera de esta pestaña. `php -l` OK. Sin prueba visual en navegador — pendiente que Armando lo confirme viéndolo en vivo. |

| UPD-513 | 14-ago-2026 | Armando | **Retrabajo comercial reclasificado a cuenta 5.5 del P&L + Salida ya no depende de un estatus de pago falso.** Discutido a fondo con Armando el manejo del saldo absorbido en retrabajo (caso real S-593). 2 hallazgos: (1) el costo del vidrio de un retrabajo comercial YA se contaba en el Estado de Resultados — pero mezclado dentro de 5.1 Vidrio (`costoVentasPeriodo()` no excluía `es_retrabajo`), sin visibilidad propia; (2) para poder imprimir la Salida de una orden de retrabajo, Lina tenía que mover manualmente el "Estatus de pago" a "En proceso"/"Pago a entrega" — estatus que implican que se está cobrando algo, cuando casi nunca es cierto. **Fix 1 — reclasificación (sin tocar Utilidad Bruta/Neta, es 100% movimiento entre 2 cuentas de Costo de Ventas):** `api/helpers/pnl_datos.php` — `costoVentasPeriodo()`/`costoVentasCobertura()` ahora excluyen `c.es_retrabajo=1` (ya no cuenta como venta normal en 5.1); función nueva `costoRetrabajoComercialPeriodo()` (mismo costeo m²×costo promedio, fechado por `c.vobo_at` igual que el resto de 5.1, sin corte de fecha explícito porque `es_retrabajo` no existía antes del 06-ago/UPD-467) sumada a la de piso en `api/contabilidad_pnl.php` para el total de 5.5. Verificado con lectura directa en BD (1-14 ago): $138,275.36 se dividía en $138,164.96 (5.1) + $110.40 (5.5) exacto, sin perder ni duplicar un peso. Cuenta `5.5` renombrada en `cuentas_contables` de "Retrabajo de **Corte** (vidrio desperdiciado)" a **"Retrabajo (vidrio desperdiciado)"** — ya cubre 2 orígenes (piso Y comercial), el nombre viejo sugería solo piso. **Fix 2 — Salida ya no exige estatus de pago falso:** `app/imprimir_salida.php` (el candado real, server-side) y `app/modulos/finanzas_cobranza.php` (el botón, solo cosmético) ahora también permiten la salida si `cotizaciones.es_retrabajo=1`, sin tocar el selector de estatus de pago — mismo criterio que UPD-470 ya usa para el VoBo (el sistema ya sabe que es retrabajo, no hace falta que Lina lo simule). El selector de estatus de pago sigue funcionando igual para todo lo demás. Sin backup de BD (solo fue un `UPDATE` de 1 fila de texto, cambio trivial y reversible). Sin prueba visual en navegador — pendiente que Armando/Lina lo confirmen viéndolo en vivo (Estado de Resultados con línea 5.5 más alta, y el botón Salida de S-593 ya habilitado sin tocar el estatus de pago). |

| UPD-514 | 14-ago-2026 | Armando | Fix: módulo Retrabajos (`?m=retrabajo`) mostraba **$0.00 de costo en toda orden de retrabajo comercial** (reportado por Armando con S-593) — `api/retrabajo.php` solo sabía costear el retrabajo de PISO (marcador `historial_estatus` "Retrabajo:%", viene de `api/reproceso.php`); una orden de retrabajo comercial (UPD-467, nace ya con la pieza marcada `es_retrabajo=1` desde `convertir_orden`, nunca pasa por reproceso.php) no tenía ningún marcador que la query supiera buscar. Mismo hueco que UPD-513 ya había encontrado y corregido en el P&L (`pnl_datos.php`) — aquí faltaba aplicar el mismo criterio al módulo operativo. Agregada `costoRetrabajoComercialSub` (mismo costeo m²×costo promedio, ahora por `cotizaciones.es_retrabajo=1` de la orden en vez del marcador de historial) sumada a la de piso — el costo mostrado por orden ahora es piso + comercial, igual que la cuenta 5.5. Verificado con lectura directa en BD: S-593 pasa de $0.00 a **$110.40**, exacto igual al monto ya confirmado en el Estado de Resultados (UPD-513). Nota: esta vista sigue sin corte de fecha (histórico completo de cada orden viva, no por período) — comportamiento ya documentado en UPD-485, no cambia con este fix. `php -l` OK. Sin prueba visual en navegador — pendiente que Armando lo confirme viéndolo en vivo. |

| UPD-515 | 14-ago-2026 | Armando | Corrección de datos (no código): parada 3 (S-519, IVAN DEL ANGEL GARCIA) de la ruta 93 (Roberto García, camioneta gris, 14-ago) escaneada por error por el chofer — Armando pidió regresarla a pendiente. Revertida manualmente replicando exacto el "deshacer" ya existente en `api/rutas.php` (`accion=marcar_estado`, estado='pendiente' desde 'entregado'): `ruta_entregas.estado` 232 → `pendiente` (entregado_at limpiado, nota de la corrección) + `ruta_entrega_piezas.estado` 1047 → `asignada` (pieza 5870, `cargado_at` intacto — sigue registrada como cargada en la camioneta). **A propósito NO se tocó** `piezas.estatus` ni `ordenes.estado` de S-519 (orden 765): la orden ya estaba `entregada` con `fecha_cierre` del mismo día pero **antes** del escaneo de ruta (14:31 vs. 21:54) — o sea Cobranza ya había cerrado la orden por otra vía (impresión de Salida) horas antes de que el chofer la cargara/escaneara; mismo candado `cerradaPorCobranza` que ya usa el endpoint real para no reabrir una orden que no cerró la ruta. Verificado con SELECT antes/después. |

| UPD-516 | 15-ago-2026 | Armando | **Nueva promo: descuento escalonado por volumen para publicidad en Estados de WhatsApp.** Armando quería lanzar publicidad y primero pidió ver factibilidad financiera (utilidad neta agosto 1-15 $115,412.06 y julio completo $498,363.42 vía `pnl_datos.php`; efectivo en Bancos hoy $159,183.48 vía `contabilidad_balance.php`, con ~$145k ya comprometido en CxP+IVA por Pagar — presentado antes de diseñar la promo). Esquema: código personal por cliente `CTN-###PROMO` (ej. CTN-146PROMO — el sistema valida que el CTN coincida con el cliente de la cotización, nadie puede usar el código de otro), descuento automático según el total de piezas de la cotización: 1-4→5%, 5-10→8%, 11-30→12%, 31-100→16%, 101-249→19%, 250+→22%. Los tramos de 11+ piezas superan el candado de autorización dir_admin >10% (`autorizaciones_descuento`) — decisión explícita de Armando de MANTENERLO activo: el % de la promo se escribe directo en `cotizaciones.descuento` (no como campo aditivo aparte, a diferencia de `descuento_referido`) para que ese candado se dispare igual que un descuento manual, sin tocar su lógica. El techo de 22% para 250+ se validó contra el vidrio más caro de cada espesor con costo de compra real (6mm: Espejo Filtra $2,421.54/m² costo $775.87 → utilidad $1,112.93/m²; 9mm: Tintex $2,692.75/m² costo $610.98 → utilidad $1,489.37/m²) — muy por arriba del piso pedido por Armando ($400/6mm, $500/9mm); el techo técnico real sería ~51%/~59%, se dejó en 22% por sentido comercial. **BD (aditivo):** tablas nuevas `promociones` (contenedor con switch on/off, hoy 1 fila activa) y `promociones_tramos` (rangos de piezas → %); columna `cotizaciones.promocion_id` (nullable). **Código:** `api/helpers/promo_wa_lib.php` (`promoWaValidarCodigo`, `promoWaCalcularDescuento`) conectado en `api/cotizaciones.php` tanto en `crear` como en `actualizar` (edición) — en edición el `promocion_id` queda LOCKED desde que se creó, solo se recalcula el % si cambian las piezas; excluido para retrabajo (`es_retrabajo`) y para maquila (mismo alcance que Referidos, UPD-450). UI: campo "Código de Promoción" en `app/modulos/cotizacion.php` (grupo Proyecto, junto a Código de Referido — se amplió a `form-grid-4` para que quepan los 4 campos). Checkpoint de rollback: tag git `pre-promo-wa-volumen-2026-08-15` + backup BD `_backups/pre_promo_wa_volumen_20260815_17-00.sql`. Probado con dry-run real en BD (BEGIN/ROLLBACK): validación de código propio/ajeno/formato inválido, los 6 tramos exactos, confirmado que 12 piezas (12%) SÍ dispara el candado de autorización para rol comercial, INSERT con `promocion_id`/`descuento` correctos, 0 filas tras rollback. `php -l` en los 3 archivos + `node --check` del `<script>` embebido del módulo. **Pendiente:** que Armando diseñe/publique el gráfico del Estado de WhatsApp con la tabla de tramos y la instrucción de decir su código al asesor; prueba visual en navegador (no hay Chrome DevTools/Playwright MCP en esta sesión) creando una cotización real con el código; si más adelante se quiere medir el ROI de la campaña, ya se puede reportar por `cotizaciones.promocion_id` (no hay dashboard dedicado todavía, se puede armar bajo pedido). |

| UPD-517 | 15-ago-2026 | Armando | **Ajuste de tramos de la promo WA por volumen (UPD-516), corrección de datos, sin cambio de código.** Tras analizar margen de Claro 9mm con el costo de compra más alto del mes ($369.60/m², COMPERS 07-ago) + 15% de merma estimada vs. precio de lista ($957.85/m²), se confirmó que el tramo original de 250+ (22%) no garantizaba el piso de utilidad que Armando quería para ese vidrio específico (a 22% deja $322.08/m² en el peor caso de costo, por debajo de $400–550/m²) — el 22% solo se validó originalmente contra vidrios de mayor margen (Espejo Filtra/Tintex). Armando definió tramos nuevos directamente: 1-4→5%, 5-50→7.5%, 51-99→12.5%, 100+→20% (reemplaza los 6 tramos anteriores 1-4/5-10/11-30/31-100/101-249/250+). `UPDATE` en `promociones_tramos` (DELETE + INSERT de las 4 filas nuevas para `promocion_id=1`, verificado antes/después) — sin tocar `api/helpers/promo_wa_lib.php` ni ningún código, la tabla es 100% configuración. A 20% con costo más caro de Claro 9mm ($425.04/m² con merma), utilidad = $341.24/m² — sigue por debajo del piso de $400 que se discutió, pero por debajo del de $550 en mayor margen; con costo promedio de agosto ($350.31/m² con merma) da $415.97/m², sí cumple $400. Armando no confirmó explícitamente aceptar ese margen bajo el escenario de costo más caro — queda como riesgo conocido, mismo criterio que "pedidos grandes normalmente mezclan varios tipos de vidrio" discutido antes de fijar el número. |

| UPD-518 | 17-ago-2026 | Armando | Fix "tiempo desde corte" en detalle de Orden (chip ⏱ por pieza, `?m=orden`/`app/orden.php`): Armando reportó ver "68h y 8min" en S-592 y preguntó si contaba el domingo — sí lo contaba, `api/orden.php` calculaba con `TIMESTAMPDIFF(MINUTE, fecha_cortado, NOW())` en reloj corrido, sin excluir noches/domingos/sábado tarde. Armando definió el horario laboral real: lunes-viernes 8am-5pm, sábado 8am-1pm, domingo cerrado (hora Monterrey/CDMX, misma zona que ya usa `created_at` en todo el sistema). Nuevo `api/helpers/horario_habil.php` (`minutosHabilesEntre()`, itera día por día y suma solo el traslape con la ventana laboral de cada uno) conectado en `api/orden.php` en vez del `TIMESTAMPDIFF` crudo — `app/orden.php`/`app/modulos/orden.php` no se tocaron, ambos ya consumen `minutos_desde_corte` del API tal cual. **Alcance:** solo cubre el horario semanal pedido, NO excluye festivos (`festivos`, tabla ya usada para días hábiles en Finanzas/Reporte Dirección) — si se quiere sumar esa exclusión, es un paso aparte. Verificado con la pieza real de S-592 (cortada viernes 14-ago 14:32:13): 4,092 min crudos (68h 12min) → **10h 12min** en horario laboral (2h28 restante del viernes + 5h sábado + 0 domingo + 2h44 de hoy lunes hasta las 10:44) — cuadra exacto a mano. `php -l` OK en ambos archivos. Sin prueba visual en navegador — pendiente que Armando lo confirme viendo el chip de S-592 en vivo. |

| UPD-519 | 17-ago-2026 | Armando | Fix período por default en Estado de Resultados (`?m=contabilidad`, `app/modulos/contabilidad_pnl.php`): Armando pidió que al entrar muestre el mes en curso (agosto) — antes abría en el mes **anterior** (julio) a propósito (`periodoAnteriorAHoy()`, decisión original documentada en el código: "el período en curso normalmente está incompleto"). Renombrada/simplificada a `periodoActual()`, que regresa el período que contiene hoy en vez del anterior; usada en `iniciarControles()` (carga inicial del módulo) y `granularidadCambio()` (al cambiar Mensual/Trimestral/Semestral/Anual). Solo afecta el modo "Individual" (vista de un solo período) — el modo "Comparar períodos" ya mostraba Ene-Hoy por default, sin cambio. El aviso de cobertura de costo trazado (banner cuando `<80%` de piezas tienen costo real, ver UPD-434/438) sigue funcionando igual y avisa si agosto (mes corriendo) no tiene suficiente dato todavía — no se quitó ninguna protección, solo cambió qué mes se ve primero. `php -l` + `node --check` del `<script>` embebido OK. Sin prueba visual en navegador — pendiente que Armando confirme que abre en agosto. |

| UPD-520 | 17-ago-2026 | Armando | Corrección de datos (no código): eliminado cliente duplicado **CTN-494** (id=352, "JOSÉ GUADALUPE TAMAYO PALACIOS", creado 12-ago-2026 por Yahaira) — mismo teléfono (8145936765) que **CTN-207** (id=63, "JOSE GUADALUPE TAMAYO", creado 07-jun-2026), mismo caso que UPD-451. Verificadas las 9 tablas con `cliente_id`/`referente_cliente_id`/`cliente_solicito_id` antes de borrar: CTN-494 no tenía ninguna actividad real (0 cotizaciones, órdenes, saldo a favor, referidos, conversación WA, rechazos, facturas ni campañas) — solo su registro de creación en `clientes_bitacora`. Se conservó CTN-207, que sí tiene 2 cotizaciones, 2 órdenes, 15 envíos de campaña y 1 conversación de WhatsApp reales. Borrado `clientes_bitacora` (id=313) + `clientes` (id=352) dentro de una transacción con SELECT de verificación antes/después; confirmado que CTN-207 quedó intacto. |

| UPD-521 | 17-ago-2026 | Armando | **Fix de fondo: imágenes/archivos/ubicación por WhatsApp no llegaban al cliente (ej. "portada" de cotización) — ventana de servicio de 24h de Meta mal calculada.** Armando reportó que algunas imágenes enviadas desde el inbox de Campañas no le llegaban al cliente. Causa raíz: `whatsapp_conversaciones.ultima_actividad` se actualiza tanto con mensajes ENTRANTES (webhook) como con SALIENTES (cualquier envío nuestro: texto, media, ubicación, template, cotización) — pero la ventana de servicio al cliente de WhatsApp (24h) SOLO se reabre con una respuesta real del cliente, nunca con algo que nosotros mandemos. El check del frontend (`app/modulos/campanas.php`, banner "Ventana cerrada") usaba `ultima_actividad`, así que en cuanto alguien del equipo le escribía a un cliente (aunque llevara semanas sin responder), el sistema creía la ventana abierta por 24h más y dejaba mandar imágenes/documentos/texto libre que Meta acepta (HTTP 200 + message id) pero luego rechaza en silencio vía webhook async (error 131047 "Re-engagement message") — el fallo solo quedaba en `error_log`, invisible para el asesor. Verificado con el `error_log` real: decenas de fallos 131047 en los últimos días, incluida una conversación con el último mensaje real del cliente de hace 48 días que el sistema mostraba "abierta" solo porque alguien le había escrito esa misma mañana. **Fix:** (1) columna nueva `whatsapp_conversaciones.ultimo_mensaje_cliente_at` (backfill desde `whatsapp_mensajes` donde `direccion='inbound'`, 386 de 446 conversaciones con dato — las otras 60 nunca han recibido respuesta del cliente, correctamente quedan sin ventana nunca abierta); (2) `api/whatsapp_webhook.php` solo actualiza esta columna en el mensaje ENTRANTE (nunca en los salientes); (3) `app/modulos/campanas.php` cambia el check de ventana de `ultima_actividad` a esta columna nueva (map `_convUltimoClienteMap`, reemplaza al `_convActividadMap` que quedó sin uso — eliminado); también se quitó una actualización optimista incorrecta que fingía reabrir la ventana al mandar el template de "Reactivar conversación" (mandarlo NO reabre la ventana, solo la respuesta real del cliente la reabre); (4) candado del lado del servidor (`api/wa_helper.php`, función nueva `waVentanaAbierta()`) en `api/campanas.php` acciones `responder`/`enviar_media`/`enviar_ubicacion` — si la ventana está cerrada, ya no se intenta el envío (que fallaría después en silencio), se corta al instante con error 409 claro ("Ventana de 24h cerrada... usa Reactivar conversación primero") que ya se muestra al asesor vía `alert()` (el manejo de errores del frontend ya existía). Los mensajes tipo `template` (incl. `enviar_cotizacion_wa`, la plantilla "cotización" con folio/proyecto/total) NO estaban afectados — los templates aprobados por Meta siempre se pueden enviar sin importar la ventana, es la razón de que existan. Checkpoint: backup dirigido `_backups/pre_ventana_24h_whatsapp_conversaciones_20260817.sql.gz` antes del ALTER (aditivo, reversible con `DROP COLUMN`). Verificado con datos reales de producción (incluida una conversación que cambió de cerrada a abierta EN VIVO durante la prueba porque el cliente real contestó). `php -l` en los 4 archivos + `node --check` del `<script>` embebido de `campanas.php` OK. Sin prueba visual en navegador — pendiente que Armando/los asesores confirmen mandando una imagen a un cliente con ventana cerrada (debe rechazarse con el aviso) y a uno con ventana abierta (debe llegar). |

| UPD-522 | 19-ago-2026 | Mando | **Mensajería interna: hilo 1-a-1 entre cada persona y desarrollo/dir_admin, ligado al sistema de Reportes existente.** Tabla nueva `mensajes_internos` (aditiva). Nuevo `api/mensajes.php` (conversaciones/hilo/enviar/sin_leer/resolver_usuario) — el backend impide que el destinatario sea otro `desarrollo`/`dir_admin` (nunca entre dos usuarios que no sean tú). Ícono ✉️ nuevo en topbar de `dashboard.php` junto a 🔔, mismo patrón (badge+panel+polling 30s): para ti lista conversaciones por persona, para el resto abre directo su único hilo. Botón "Mandar mensaje" en el detalle de cada reporte (`app/modulos/reportes.php`) — un solo hilo por persona sin importar cuántos reportes tenga. Escapado con `msgEscHtml`/`textContent` para evitar el patrón XSS de UPD-275/509. `php -l` OK en los 3 archivos. Sin prueba visual en navegador — pendiente que Armando/Mando lo confirmen viéndolo en vivo. |

| UPD-523 | 19-ago-2026 | Mando | **Direcciones guardadas por cliente en Logística Rutas.** No existía dirección de calle en Cotizaciones/Órdenes ni en `clientes` — solo se capturaba manualmente por parada en `ruta_entregas`. Tabla nueva `clientes_direcciones` (aditiva: `cliente_id, etiqueta, direccion, colonia, ciudad, referencias`). En los modales "Asignar a Ruta" y "Editar Dirección" (`app/modulos/logistica_rutas.php`) se agregó checkbox "Guardar dirección" (pide nombre, ej. "Taller") y un selector "Dirección guardada" que autocompleta los campos si el cliente ya tiene alguna — nunca obligatorio. Nuevo `api/clientes_direcciones.php` (`listar`/`guardar`); se agregó `cliente_id` a las queries de `pendientes` y `rutas_fecha` en `api/rutas.php` (antes solo traían `cliente_nombre`). `php -l` OK en los 3 archivos. Sin prueba visual en navegador — pendiente que Mando lo confirme viéndolo en vivo. |

| UPD-524 | 19-ago-2026 | Mando | Fix de seguridad en UPD-522/523: `api/clientes_direcciones.php` no tenía candado de rol (cualquier usuario con sesión, incl. `operador`/`chofer`, podía llamarlo directo por fuera de la UI). Agregado el mismo gate que ya usa `api/rutas.php` (`administracion, dir_admin, dueno, desarrollo, comercial`) — sin tocar accesos de `director` en ningún otro módulo. Probado con rollback por rol (`comercial/dir_admin/administracion/dueno` permitidos, `director/operador/chofer` bloqueados 403) y flujo completo guardar→listar→autocompletar de "Dirección guardada", ambos con `SELECT COUNT(*)` antes/después confirmando 0 filas tras `ROLLBACK`. `php -l` OK. |

| UPD-525 | 20-ago-2026 | Mando | **Auditoría v2 pre-release 19-ago-2026** (`/home/mando/files_apexglass/auditoria_19_08_26/`), Sprint A (bloqueantes de release): A-01 escapado de XSS nuevo en `cotizaciones.php`/`ordenes.php` (badges de asesor + tabla principal); A-02 doble IVA en `imprimir_orden_compra.php` corregido (respeta `iva_incluido` por partida); A-03 `admin_ordenes.php` corregir_estatus ya no marca "entregado" con saldo pendiente (excepción retrabajo); A-05 `api/cotizaciones.php` agregar/eliminar servicio ya rebloquea la entrega si sube el saldo. Los 4 probados con `BEGIN`/`ROLLBACK` sobre datos reales de producción, sin dejar rastro. A-04 (IDOR portal por nombre) investigado — log real revisado (96 líneas, un solo cliente, 6 órdenes propias con `cliente_id` NULL) pero de las 216 órdenes totales sin `cliente_id`, falta backfill antes de poder quitar el fallback — queda pendiente por decisión de Mando de no tocar datos esta sesión. |

| UPD-526 | 20-ago-2026 | Mando | Sprint B (misma auditoría, dinero/cotizaciones): B-01 `api/cotizaciones.php` recalcula perímetro/piezas de servicios `ml` (espaciador) contra las medidas NUEVAS al editar una cotización, en vez de restaurar el valor viejo. B-02 promo (`promocion_id`) y descuento de referido ya no se heredan al reasignar `cliente_id` a otro cliente — se resetean/recalculan para el cliente nuevo. B-03 `api/finanzas.php` la póliza automática de venta usa el total canónico (`apexTotalesCotizacion`) en vez de los campos `subtotal/iva/total` guardados en la cotización. B-04 (truncamiento de piezas de espaciador) cerrado SIN código — confirmado con datos reales que la cantidad de vidrio insulado siempre es par (nunca ocurre el caso fraccionario). B-05 ya era una decisión de negocio tomada (UPD-516), no un bug. Los 3 fixes de código probados con `BEGIN`/`ROLLBACK` sobre cotizaciones reales. |

| UPD-527 | 20-ago-2026 | Mando | Sprint C (misma auditoría, hardening): C-01 XSS de `razones` escapado en `retrabajo.php`. C-02 CSRF migrado de `requirePermiso()` a `requirePermisoApi()` en 6 endpoints (`croquis.php, inventario.php, laminas.php, ordenes_compra.php, proveedores.php, retrabajo.php`) — sin tocar frontend, `csrf_fetch.js` ya cubre todos los módulos SPA globalmente. C-03 `api/campanas.php` descarga el header de campaña con cURL (timeout 30s, límite 16MB, bloqueo de IPs privadas/loopback anti-SSRF) en vez de `file_get_contents()` sin límites. C-06 `api/estaciones.php` oculta `cliente_nombre`/`asesor` del payload cuando no hay sesión iniciada (SmartTV público); con sesión (vista admin) no cambia nada. C-04 (rotar token WA `apex_wh_2026`, confirmado que sigue siendo el mismo en producción) y C-05 (`git rm --cached` de los `error_log`) quedan pendientes — requieren acción de Armando/Mando fuera del alcance de Claude. C-07 (portal_password visible) ya era decisión de producto tomada en UPD-501. |

| UPD-528 | 20-ago-2026 | Mando | Sprint D parcial (misma auditoría, visual): D-01 — 8 `alert()` migrados a `toast()` en `finanzas_vobo.php`; 4 funciones `toast()` locales duplicadas eliminadas (`logistica_rutas.php`, `admin_ordenes.php`, `optimizador.php`, `chofer_ruta.php`), ahora caen en la global de `utils.js`; de paso se corrigieron entidades HTML que se hubieran mostrado rotas (`admin_ordenes.php`) o que ya se mostraban rotas en producción desde antes (`optimizador.php`, bug preexistente no relacionado). D-02 (sweep de emojis en `app/orden.php`) evaluado con Mando — el set de íconos SVG del sistema no cubre todos los conceptos (falta reloj/tornillo/fuego/impresora/mensaje); Mando decidió dejar `orden.php` como está, sin tocar. D-03 a D-07 quedan pendientes para otra sesión. |

| UPD-529 | 20-ago-2026 | Armando | **Carpeta fija para actualizar la imagen/video de Ofertas del portal, sin tocar código.** Antes `portal/index.php` apuntaba a un nombre fijo `img/oferta.jpeg` en el modal "Conoce nuestras ofertas" — cualquier cambio requería que Claude editara el HTML. Creada `portal/img/ofertas/` (se movió ahí el archivo vigente + el historial `oferta_001/002/003.jpeg` que ya existía suelto en `img/`). El modal ahora detecta en cada carga el archivo más reciente por fecha de modificación (`glob` + `filemtime`) y lo muestra como `<img>` o `<video controls autoplay muted loop playsinline>` según la extensión (`jpg/jpeg/png/webp` vs. `mp4/webm/mov`) — soporta video, no solo imagen. De aquí en adelante: subir el archivo nuevo a `portal/img/ofertas/` por FTP/AdminBolt (mismo patrón ya establecido de "Armando sube archivos, Claude nunca sube") y se refleja solo, sin pedirle nada a Claude. Verificado en producción: `curl` a `img/ofertas/oferta.jpeg` da 200 y el HTML servido del portal ya referencia la ruta nueva. `php -l` OK. |

**Próximo UPD disponible: UPD-530**
