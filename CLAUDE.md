# APEX GLASS — MEMORIA ÚNICA DEL PROYECTO
# Sistema de Rastreo de Producción (Templadora Noreste, S.A. de C.V.)
# Última actualización: 15 junio 2026 | Próximo UPD disponible: UPD-080

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
| ALTA | Armando | Seguridad HTTP (CORS, CSRF, headers, session regenerate) | Pendiente |
| ALTA | Armando | Agregar UPDs 059+ al Google Doc (cambios 12-14 jun) | Pendiente |
| MEDIA | Armando | Mover DB_PASS a .env fuera del webroot | Pendiente |
| MEDIA | Armando | Cargar tzdata en MariaDB VPS | Pendiente |
| MEDIA | Armando | Instalar n8n via Docker (n8n.apex.glass, puerto 5678) | Pendiente |
| MEDIA | Armando | PDF croquis: app/imprimir_croquis.php | Pendiente |
| MEDIA | Armando | Feature Órdenes Express | Pendiente |
| MEDIA | Armando | Google Sheets / Apps Script — verificar columna M | Pendiente |
| MEDIA | Mando | Completar módulo Retrabajo: modal + razones por estación | Pendiente |
| MEDIA | Mando | Rutas: optimización de zonas | Pendiente |
| MEDIA | Ambos | Alerta reorden automática láminas (esperar 2-3 semanas historial) | Pendiente |
| BAJA | Armando | Error consola JS guardarCristal | Pendiente |
| BAJA | Ambos | m2_requeridos en laminas.php | Pendiente |
| MANUAL | Armando | Actualizar CTN-259: "PRUEBA PORTAL" → "JESUS MANUEL SALDANA DE LA ROSA" | Pendiente |
| MANUAL | Armando | Capturar precios: Claro 12mm, Claro Zafiro 9mm, Filtrasol 9mm, Tintex 6mm, Tintex 9mm | Pendiente |

---

## 13. HISTORIAL DE ACTUALIZACIONES

REGLA: Cada cambio se agrega aquí. NUNCA se elimina. Código UPD secuencial e irrepetible.
Próximo UPD disponible: **UPD-059**

| Código | Fecha | Resp. | Descripción |
|---|---|---|---|
| UPD-001 | 30-may | Armando | Búsqueda global módulo Órdenes |
| UPD-002 | 30-may | Armando | Modal detalle clickable en KPI cards Reporte |
| UPD-003 | 30-may | Armando | Fix cálculo local/foráneo (ubicacion) y fecha_cierre |
| UPD-004 | 30-may | Armando | Limpieza BD: 'concluida' → 'entregada' |
| UPD-005 | 01-jun | Mando | SmartTV: sin login, robots.txt, auto-scroll, popup nueva orden |
| UPD-006 | 01-jun | Armando | Drive compartido + protocolo sincronización |
| UPD-007 | 02-jun | Armando | Módulo Inventario completo |
| UPD-008 | 02-jun | Armando | Fix selector período Reporte Dirección |
| UPD-009 | 02-jun | Mando | Dashboard: módulos Retrabajo, Comunicados, Notificaciones |
| UPD-010 | 03-jun | Armando | Fix módulo Cotización — primer intento |
| UPD-011 | 04-jun | Armando | Fix módulo Cotización — reescritura completa patrón SPA |
| UPD-012 | 04-jun | Armando | API cotizaciones: acción PUT actualizar |
| UPD-013 | 04-jun | Armando | Fix botón guardar duplicado |
| UPD-014 | 04-jun | Armando | Fix var ModCotizacion — eliminada redeclaración const |
| UPD-015 | 04-jun | Armando | Fix cancelar — solo UPDATE, redirección correcta |
| UPD-016 | 04-jun | Armando | Fix ModCotizaciones — cotTab y cotFiltrar expuestas globalmente |
| UPD-017 | 04-jun | Armando | Campo requiere_templado en cotizaciones_partidas |
| UPD-018 | 04-jun | Armando | Flujo sin templado en api/actualizar_estatus.php |
| UPD-019 | 04-jun | Armando | Dropdowns Detalles y CPB en cotizador |
| UPD-020 | 04-jun | Armando | Fix leerPartidasDelDOM — no pierde datos |
| UPD-021 | 04-jun | Armando | Teléfono empresa en imprimir_orden.php |
| UPD-022 | 04-jun | Armando | BD: ENUM ordenes.estado + pendiente_vobo, tabla cotizacion_pagos |
| UPD-023 | 04-jun | Armando | Módulo Finanzas VoBo completo |
| UPD-024 | 04-jun | Armando | API api/finanzas.php — VoBo completo |
| UPD-025 | 04-jun | Armando | Dashboard: sección FINANZAS en sidebar |
| UPD-026 | 04-jun | Armando | api/cotizaciones.php: convertir_orden → estado pendiente_vobo |
| UPD-027 | 04-jun | Armando | imprimir_etiquetas.php: bloqueado en pendiente_vobo |
| UPD-028 | 04-jun | Armando | Fix inventario.php: duplicados eliminados, patrón SPA |
| UPD-029 | 01-jun | Mando | Portal Clientes completo |
| UPD-030 | 01-jun | Mando | Módulo Clientes: panel lateral con credenciales portal |
| UPD-031 | 05-jun | Armando | Diagnóstico completo sistema |
| UPD-032 | 05-jun | Armando | Fix api/orden.php: en_horno => 0 |
| UPD-033 | 05-jun | Armando | Limpieza api/actualizar_estatus.php: código muerto |
| UPD-034 | 05-jun | Armando | Unificación memorias técnicas en v5.1 |
| UPD-035 | 07-jun | Armando | SEGURIDAD: credenciales FTP expuestas — rotadas |
| UPD-036 | 07-jun | Armando | Claude Code conectado a BD vía Remote MySQL |
| UPD-037 | 07-jun | Armando | Creación Memoria_Tecnica_APEX_GLASS_UNIFICADA_v1.0 |
| UPD-038 | 07-jun | Armando | Fix seguridad api/clientes.php: SEMECT→SELECT + permisos portal_password |
| UPD-039 | 07-jun | Armando | Fix seguridad api/inventario.php: SQL injection → prepared statements |
| UPD-040 | 08-jun | Mando | Google Maps en módulo Rutas: Places Autocomplete + Routes API |
| UPD-041 | 08-jun | Mando | Fix api/rutas.php: alias entrega_estado |
| UPD-042 | 08-jun | Mando | imprimir_cotizacion.php: nombres asesores, datos bancarios, resumen material |
| UPD-043 | 08-jun | Mando | BD: CREATE TABLE ruta_entrega_piezas |
| UPD-044 | 08-jun | Mando | api/rutas.php: piezas_orden, asignar con pieza_ids[], marcar_pieza |
| UPD-045 | 08-jun | Mando | modulos/logistica_rutas.php: modal Asignar rediseñado con piezas |
| UPD-046 | 09-jun | Mando | Sistema backup automático BD: mysqldump diario cron → _backups/ |
| UPD-047 | 09-jun | Mando | Portal Clientes: rediseño visual completo, responsivo |
| UPD-048 | 09-jun | Mando | produccion_estaciones.php: 6 → 8 columnas |
| UPD-049 | 09-jun | Mando | modulos/clientes.php: permisos portal ampliados a administracion |
| UPD-050 | 09-jun | Mando | Edición inline nombre/contacto cliente desde panel lateral |
| UPD-051 | 10-jun | Mando | Responsivo móvil: drawer hamburguesa + @media en todos los módulos |
| UPD-052 | 10-jun | Mando | Fix clientes.php: cliEditarNombre() faltante |
| UPD-053 | 10-jun | Mando | Fix backup_runner.php: ruta cron corregida |
| UPD-054 | 10-jun | Mando | NUEVO módulo Archivos Órdenes |
| UPD-055 | 11-jun | Mando | NUEVO módulo Croquis Técnicos (MVP) |
| UPD-056 | 09-jun | Armando | Fix permisos: dueno → ver_inventario + gestionar_inventario |
| UPD-057 | 09-jun | Armando | Saldo a Favor como forma de pago en VoBo |
| UPD-058 | 11-jun | Armando | Fix SQL injection api/ordenes_compra.php |
| UPD-059 | 12-jun | Armando | NUEVO api/autorizaciones.php: flujo descuentos >10% |
| UPD-060 | 12-jun | Armando | api/cotizaciones.php: bloqueo conversión sin auth aprobada |
| UPD-061 | 12-jun | Armando | modulos/cotizacion.php: modal motivo + banner estado autorización |
| UPD-062 | 12-jun | Armando | modulos/cotizaciones.php: sección naranja pendientes auth dir_admin |
| UPD-063 | 12-jun | Armando | api/optimizador_corte.php: límite ≤4 órdenes; 30 shuffles; stock vs necesario |
| UPD-064 | 12-jun | Armando | modulos/optimizador.php: tarjeta stock vs necesario |
| UPD-065 | 12-jun | Armando | modulos/inventario.php: tab Consumo Diario + XSS fix escAttr() |
| UPD-066 | 12-jun | Armando | imprimir_etiquetas.php: PLANTILLA descuenta -100mm ancho/alto |
| UPD-067 | 12-jun | Armando | NUEVO api/correcciones.php: correcciones dir_admin con log |
| UPD-068 | 12-jun | Armando | modulos/cotizacion.php: modal Corregir (botón púrpura dir_admin) |
| UPD-069 | 12-jun | Armando | imprimir_cotizacion.php: fix IVA/total con SUM(precio_m2_usado × m2 × cantidad) |
| UPD-070 | 13-jun | Armando | api/correcciones.php: fix recálculo precio_unitario + subtotal/iva/total al cambiar precio_m2_usado |
| UPD-071 | 14-jun | Armando | MIGRACIÓN VPS: servidor migrado de HostGator a Hostinger VPS KVM 2 |
| UPD-072 | 14-jun | Armando | DNS apex.glass → 82.29.197.33 propagado, SSL ZeroSSL activo, login HTTPS confirmado |
| UPD-073 | 15-jun | Mando | imprimir_salida.php: quitar folio cot. del header, agrandar y bold folio orden, quitar folio orden duplicado en totales |
| UPD-074 | 15-jun | Mando | croquis.php: fix proporcionalidad al hacer zoom — elementos especiales ya no se doble-escalan |
| UPD-075 | 15-jun | Armando | Fix cámara QR Android: AdminBolt bloqueaba cámara con `Permissions-Policy: camera=()` global — sobreescrito en vhost con `camera=(self)` + mejoras error handling en operador.php |
| UPD-076 | 15-jun | Mando | operador.php: estación templado solo registra en_horno; nueva estación terminado maneja salida + reproceso; cooldown reducido a 30s; cooldown es por pieza (qr_code) no por orden |
| UPD-077 | 15-jun | Mando | factura_tipo en cotizaciones: BD ALTER + checkbox Genérica/RFC en form + footer "PÚBLICO EN GENERAL" en PDF cotización |
| UPD-078 | 15-jun | Mando | factura_tipo simplificado: solo checkbox "Público en general", sin input RFC; PDF muestra footer solo si está marcado |
| UPD-079 | 15-jun | Mando | NUEVO app/imprimir_croquis.php: render SVG estático en PHP (puerto de la lógica JS de croquis.php) + botón "Guardar como PDF" vía window.print(); el croquis sigue editable en el módulo |
| UPD-080 | 15-jun | Armando | Fix modulos/cotizaciones.php: limit 50→200 — COT-0138 y anteriores no aparecían por límite de paginación (52 cots más recientes la desplazaban) |
| UPD-081 | 16-jun | Armando | produccion_estaciones.php: delay 2s al arranque antes de iniciar auto-scroll; pausa 3s al llegar al último registro ya estaba implementada (PAUSA_MS=3000) |
| UPD-082 | 16-jun | Armando | Fix BD: ALTER TABLE cotizacion_pagos — agrega saldo_favor al ENUM forma_pago (omitido en UPD-057) |
| UPD-083 | 16-jun | Armando | Fix api/finanzas.php: tipo 'cargo' → 'aplicacion' al insertar en clientes_saldo_favor al aplicar saldo a favor |
| UPD-084 | 16-jun | Armando | Modal Corregir: agrega campos Ancho mm y Alto mm editables por partida; backend recalcula m2, precio_unitario y propaga a piezas |
| UPD-085 | 16-jun | Armando | Reporte Dirección: 6 KPIs nuevos — tasa conversión cotizaciones, rendimiento por asesor (órdenes+ventas), top 5 clientes, tasa de reproceso, valor total almacén, ocupación horno por semana |
| UPD-086 | 16-jun | Armando | Fix retraso: ahora se mide por fecha_terminado (última pieza en 'terminado') no por fecha_cierre — el cliente puede recoger tarde sin generar retraso de producción |
| UPD-087 | 17-jun | Mando | Reporte Dirección: barra "% Entrega a tiempo" ahora es apilada con 3 segmentos (verde=A tiempo, rojo=Con retraso, naranja=En proceso) proporcionales al total |
| UPD-088 | 17-jun | Armando | Fix api/finanzas.php: total y saldo_pendiente se calculaban con valor bruto (sin descuento). Las 3 queries (lista_vobo, detalle, cobranza) ahora computan ROUND(subtotal*(1-descuento/100)*1.16,2) — sin tocar BD |
| UPD-089 | 17-jun | Armando | Fix api/cotizaciones.php: mismo bug de total bruto — detalle (SELECT c.*) corregido en PHP post-fetch; lista corregida en SQL. Botón "Imprimir Salida" ya aparece cuando saldo_pagado >= total real |
| UPD-090 | 17-jun | Armando | NUEVO Feature: Servicios adicionales por partida — tabla servicios_catalogo + cotizacion_partida_servicios + servicios_subtotal en cotizaciones; UI en cotizacion.php bajo cada partida; PDF cotización muestra sub-filas verdes + línea en totales; servicios sin descuento, con IVA; se preservan al editar cotización. Catálogo vacío para que Armando lo llene. Botón ⚙ Catálogo servicios (solo dir_admin) en sección Partidas |
| UPD-092 | 17-jun | Armando | Fix definitivo total cotizaciones: cotizaciones.subtotal es inconsistente (bruto en registros viejos, neto en nuevos) — todas las queries ahora usan SUM(precio_m2_usado×m2×cantidad) FROM cotizaciones_partidas como bruto canónico. Afecta api/finanzas.php (lista_vobo, detalle, cobranza, registrar_pago) y api/cotizaciones.php (GET detalle + GET lista). Detectado en S-078: subtotal almacenado como neto causaba doble descuento en VoBo |
| UPD-090 | 17-jun | Mando | Cobranza: auto-refresh al registrar pago y al cambiar estatus de pago — llama a cargar() para traer datos frescos del servidor |
| UPD-091 | 17-jun | Mando | Archivos en cotización: módulo archivos_ordenes removido del sidebar; botón "Archivos" integrado en barra de acciones de cotizacion.php (visible cuando es orden/entregada); abre modal con lista + subida de archivos (roles: comercial, administracion, dir_admin, dueno) |
| UPD-093 | 17-jun | Mando | Backup automático BD VPS: backup_runner.php actualizado con credenciales VPS (host ::1, apexglass2025_prod); cron configurado 0 6 * * * (12:00 AM Monterrey / UTC-6); backups en produccion/_backups/, retención 15 días, log en backup.log |
| UPD-094 | 17-jun | Mando | Seguridad _backups/: permisos carpeta 750, archivos 640; .htaccess actualizado con Require all denied + Deny from all (Apache 2.4); backup verificado: 39 tablas, 35 INSERT, 179.6 KB. Para revertir: chmod 755 _backups/ && chmod 644 _backups/*.sql.gz |
| UPD-095 | 18-jun | Armando | HostGator cancelado — servidor único ahora es VPS Hostinger |
| UPD-096 | 18-jun | Armando | modulos/cotizaciones.php + api/cotizaciones.php: search ahora busca en folio orden (S-XXX), cliente, folio cot y proyecto; auto-switch de tab si resultado está en tab distinto; paginación 25 registros por tab con controles Ant/Sig |
| UPD-097 | 18-jun | Armando | api/finanzas.php: estatus_pago se actualiza automáticamente a 'pagado' al registrar pago si (total - saldo_pagado) <= $0.99; BD: UPDATE retroactivo a 39 órdenes ya pagadas; modulos/finanzas_cobranza.php: estadoPago() usa misma tolerancia $0.99 |
| UPD-098 | 18-jun | Armando | imprimir_etiquetas.php: muestra servicios adicionales de la partida debajo del badge CPB/FILO MUERTO |
| UPD-099 | 18-jun | Armando | imprimir_etiquetas.php: badge servicio ajustado a fondo blanco + borde negro + texto negro; formato "DESCRIPCION - N" usando unidades_por_pieza (ej. RADIO - 4) |
| UPD-100 | 18-jun | Armando | BD: ordenes_compra += tipo ENUM(material,suministro) DEFAULT material + categoria VARCHAR(100) |
| UPD-101 | 18-jun | Armando | api/ordenes_compra.php: filtro ?tipo=, campos tipo/categoria en crear/actualizar, fix tipo_check en registrar_entrega para suministros (tipo='otro') |
| UPD-102 | 18-jun | Armando | NUEVO modulos/compras.php: tabs Suministros + OC Material, KPIs, CRUD completo (crear OC, partidas, pagos, recepción, cambio estado); sidebar dashboard += Compras bajo Inventario |
| UPD-103 | 18-jun | Armando | Reporte Dirección: sección Top Clientes expandida a 3 paneles lado a lado — Por Monto ($), Por Pedidos (órdenes) y Por M²; función mkTopPanel reutilizable; 2 queries nuevas en api/reporte_direccion.php |
| UPD-104 | 18-jun | Armando | Reporte Dirección: tabla "Rentabilidad por M² de Vidrio" — columnas Costo s/IVA, Costo c/IVA (×1.16), Precio venta (catálogo ×0.90), Utilidad $, Markup, % Utilidad ((P-CostoSinIVA)/P), Margen % ((P-CostoConIVA)/P) con barra color; orden alfabético por tipo + espesor asc |
| UPD-105 | 19-jun | Armando | BD: ALTER TABLE historial_estatus ADD COLUMN omision TINYINT(1) NOT NULL DEFAULT 0 |
| UPD-106 | 19-jun | Armando | api/actualizar_estatus.php: acepta flag omision=1, salta validación de flujo, registra en historial con omision=1 |
| UPD-107 | 19-jun | Armando | app/operador.php: modal ámbar confirmación de omisión en lugar de bloqueo para estaciones canteado/trazo/taladro/terminado; funciones abrirModalOmision/cerrarModalOmision/confirmarOmision |
| UPD-108 | 19-jun | Armando | NUEVO api/omisiones.php + modulos/omisiones.php: tablero de omisiones con KPIs (hoy/semana/período), barras por estación omitida, tabla detalle; visible a jefe_piso/dir_admin/dueno/director en sidebar bajo Producción |
| UPD-109 | 19-jun | Armando | Fix omisiones múltiples: api/actualizar_estatus.php auto-inserta un registro por cada paso saltado cuando omision=1 (ej: en_corte→en_horno genera 4 registros individuales) |
| UPD-110 | 19-jun | Armando | Fix operador.php estación horno: muestra botón ámbar de omisión cuando pieza viene en trazo/cortado/en_corte con mensaje específico por cuántas estaciones saltó |
| UPD-111 | 19-jun | Armando | NUEVO módulo Campañas WhatsApp: Meta Cloud API v20.0, 4 tablas BD, api/campanas.php (10 acciones), api/whatsapp_webhook.php, modulos/campanas.php (ModCampanas) con wizard 3 pasos + inbox conversaciones; permisos dir_admin/dueno crean campañas, comercial responde chats; 6 fixes de seguridad aplicados |
| UPD-112 | 19-jun | Armando | Fix campanas: módulo faltaba en mapa MODULOS dashboard; $user['username']→$user['nombre'] (fatal error al crear campaña); $rol leía $_SESSION['rol'] incorrecto→$user['rol']; fetch crear sin .catch(); Step 2 wizard reemplaza input manual por dropdown de plantillas aprobadas desde Meta API (listar_plantillas); WA_WABA_ID=1517799296194687 agregado a config.php |
| UPD-113 | 19-jun | Armando | Campañas: soporte header imagen — BD ALTER TABLE campanas ADD header_image_url; listar_plantillas devuelve header_format+header_example; wizard Step 2 muestra campo URL imagen auto-rellenado con ejemplo Meta cuando template tiene IMAGE header; enviar construye components dinámicamente (header+body); 24/30 plantillas tienen imagen |

---

## 14. PROTOCOLO PARA CADA SESIÓN

Al terminar cualquier sesión con cambios:
1. Subir archivos modificados a Drive (`ARCHIVOS SERVIDOR/`)
2. Registrar el cambio con próximo UPD en este archivo
3. Las tareas completadas se marcan HECHO — NUNCA se borran

| UPD-114 | 19-jun | Armando | NUEVO flujo Rechazo por Calidad: BD ordenes.estado += 'rechazada' + tabla rechazo_calidad; api/cotizaciones.php acción 'rechazar' (marca orden rechazada + bitácora + mueve saldo_pagado a clientes_saldo_favor tipo deposito); botón rojo "Rechazar" en modulos/cotizacion.php para dir_admin cuando estatus=orden o entregada; modal con campo motivo + monto a transferir |
| UPD-115 | 19-jun | Armando | Fix modal rechazo: confirmarRechazo() usaba API_COT/ID_COT/cargar() del IIFE — inaccesibles desde script externo; corregido a URL hardcoded + window._cotData.id + location.reload(); php://input re-leído en rechazar eliminado (ya consumido en línea 184) |
| UPD-116 | 19-jun | Armando | UI cotizacion.php: botones en 2 filas — fila 1 visible a asesores, fila 2 (Marcar Entregada, Cancelar, Corregir, Rechazar) solo admins con separador punteado; contenedor flex-direction:column |
| UPD-117 | 19-jun | Armando | BD: cotizaciones.estatus ENUM += 'rechazada'; api/cotizaciones.php rechazar actualiza también cotizaciones.estatus; badge-rechazada rojo + etiqueta "Rechazada por Calidad" en cotizacion.php |
| UPD-118 | 19-jun | Armando | modulos/cotizaciones.php: tab "Rechazadas" con contador; badge-rech rojo; auto-switch de tab incluye rechazada; badge-canc corregido a gris |
| UPD-119 | 19-jun | Armando | Banner rechazo en detalle cotizacion: api devuelve rechazo_calidad (motivo, monto, registrado_por, fecha) cuando estatus=rechazada; banner rojo visible debajo del header con motivo completo |

| UPD-120 | 20-jun | Armando | Fix api/omisiones.php: require_once 'permisos.php' faltante causaba Fatal Error (requirePermiso undefined) — tablero de omisiones no cargaba datos |
| UPD-121 | 20-jun | Armando | VPS: dnf install python3.11 — resuelve error stop hook security-guidance que requería Python 3.10+ (servidor tenía solo 3.9.25) |
| UPD-122 | 20-jun | Armando | SEGURIDAD login.php: (1) IP spoofing corregido — usa solo REMOTE_ADDR en lugar de HTTP_X_FORWARDED_FOR falsificable; (2) session_regenerate_id(true) agregado al login exitoso para prevenir session fixation; (3) test.php eliminado de producción (tenía display_errors=On) |
| UPD-123 | 20-jun | Armando | SEGURIDAD autenticación APIs: 8 endpoints sin protección ahora requieren sesión activa — buscar_orden.php, pieza.php, orden.php, actualizar_estatus.php, ordenes.php, dashboard.php → requireSessionApi(); reporte_direccion.php + reporte_detalle.php → requirePermisoApi('ver_reportes'); archivos_ordenes/.htaccess creado (Deny from all). Único endpoint público intencional: estaciones.php (SmartTV sin login) |
| UPD-124 | 20-jun | Armando | Rediseño visual modulos/reporte_direccion.php: estilo minimal profesional — emojis eliminados, headers tabla gris claro (no navy), KPI cards sin borde de color superior (color en número), tokens CSS unificados (--muted-lt, --border-lt, --amber), section titles compactos con border-bottom, metric cards sin ícono emoji, mkTopPanel con rank numérico, var en lugar de const/let (SPA pattern) |

| UPD-125 | 20-jun | Armando | Fix clasificación reporte dirección: órdenes activas con TODAS las piezas en terminado/entregado ahora se clasifican como a_tiempo o con_retraso (no en_proceso/retraso_abierto); JOIN pt (todas_terminadas) en ambas queries resumen + mensual |
| UPD-126 | 20-jun | Armando | Reporte Dirección: quita subtexto "X cerradas · Y activas" de tarjeta Total — datos redundantes ya visibles en las otras tarjetas |
| UPD-127 | 20-jun | Armando | prom_dias incluye órdenes activas con producción terminada — consistente con nueva clasificación a_tiempo/con_retraso |
| UPD-128 | 20-jun | Armando | Fix badge búsqueda módulo Órdenes: etiquetaEstado() usaba arrays locales paginados; órdenes antiguas caían a "Por iniciar" aunque fueran entregadas. Ahora usa o.estado del API directamente |
| UPD-129 | 21-jun | Armando | WhatsApp: token permanente via System User tnwapp (sin expiración); app Meta en modo Producción; WaNotifier desconectado del WABA — mensajes ahora llegan a APEX GLASS |
| UPD-130 | 21-jun | Armando | Inbox conversaciones: pegar imagen con Ctrl+V + subir archivos con botón 📎 (imágenes/PDF/docs); acción enviar_media en api/campanas.php — sube a Meta Media API y envía con media_id |
| UPD-131 | 21-jun | Armando | Fix envío media: BD ALTER TABLE whatsapp_mensajes ENUM tipo += 'documento'; fix paste handler llamaba window.cmpSetMedia (no expuesto) → corregido a cmpSetMedia directo por closure |
| UPD-132 | 21-jun | Armando | Badge mensajes WA sin leer en sidebar dashboard: polling 30s via api/campanas.php?accion=sin_leer; badge rojo desaparece inmediatamente al abrir conversación (window.actualizarBadgeWA expuesto globalmente) |

**Próximo UPD disponible: UPD-133**
