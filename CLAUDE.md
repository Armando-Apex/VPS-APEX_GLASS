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
| ALTA | Armando | Seguridad HTTP (CORS, CSRF, headers, session regenerate) | HECHO UPD-138 a 147 |
| ALTA | Armando | Agregar UPDs 059+ al Google Doc (cambios 12-14 jun) | Pendiente |
| MEDIA | Armando | Mover DB_PASS a .env fuera del webroot | HECHO UPD-139 |
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
| ALTA | Ambos | SEGURIDAD: Fail2ban en puerto 8443 (AdminBolt) — protección brute force, panel expuesto al internet | Pendiente |
| ALTA | Ambos | SEGURIDAD: FTP puerto 21 abierto — evaluar migrar a SFTP (puerto 22) y cerrar FTP | Pendiente |
| ALTA | Ambos | SEGURIDAD: Rate limiting en login.php — verificar/implementar bloqueo por intentos fallidos | Pendiente |
| MEDIA | Armando | SEGURIDAD: SSH hardening — verificar que solo acepta llaves, no password | Pendiente |
| MEDIA | Armando | SEGURIDAD: Revisar permisos de archivos en servidor (buscar 777) | Pendiente |

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

### Bloque actual: UPD-101 en adelante

| Código | Fecha | Resp. | Descripción |
|---|---|---|---|
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

| UPD-133 | 21-jun | Armando | Campañas WA: tab Conversaciones pasa a primer lugar (más usada); init() llama tab() para mostrar panel correcto al cargar |
| UPD-134 | 21-jun | Armando | Imágenes en chat: enviadas se guardan en archivos_campanas/wa_media/ con nombre único; recibidas el webhook descarga de Meta y guarda localmente; se muestran como thumbnail clickable en el chat |
| UPD-135 | 21-jun | Armando | SEGURIDAD enviar_media: whitelist extensiones + validación MIME real con finfo; .htaccess en wa_media/ desactiva ejecución PHP (RemoveHandler/RemoveType); fix php_flag engine off incompatible con PHP-FPM |
| UPD-136 | 21-jun | Armando | Limitar adjuntos chat WA a imágenes y PDF solamente — frontend accept + backend whitelist reducidos |

| UPD-137 | 21-jun | Armando | SEGURIDAD webhook: anti-SSRF en descarga imágenes Meta — whitelist dominios (lookaside.fbsbx.com, scontent.whatsapp.net, mmg.whatsapp.net, media.fbcdn.net); límite 5MB con CURLOPT_PROGRESSFUNCTION |
| UPD-138 | 21-jun | Armando | SEGURIDAD sesiones: cookies con HttpOnly + Secure + SameSite=Lax en requireSession() y requireSessionApi() en permisos.php |

| UPD-139 | 21-jun | Armando | Credenciales movidas a .env fuera del webroot (/home/apexglass2025/apex.glass/.env, permisos 640); config.php lee con parser propio sin dependencias externas; DB_PASS, API_KEY, WA_TOKEN y todas las claves eliminadas del código fuente |

| UPD-140 | 21-jun | Armando | SEGURIDAD CORS: Access-Control-Allow-Origin cambiado de * a https://apex.glass en jsonResponse() y login.php |
| UPD-141 | 21-jun | Armando | SEGURIDAD CSRF: validación de header Origin en requireSessionApi() para peticiones POST/PUT/DELETE/PATCH — complementa SameSite=Lax |
| UPD-142 | 21-jun | Armando | SEGURIDAD headers HTTP: .htaccess en produccion/ con X-Frame-Options, X-Content-Type-Options, Referrer-Policy, CSP, HSTS y bloqueo de archivos sensibles (.env, .log, .sql) |

| UPD-143 | 22-jun | Mando | SEGURIDAD directory listing: Options -Indexes en .htaccess raíz de produccion/ — bloquea listado de archivos en todo el proyecto (cascadea a subcarpetas) |
| UPD-144 | 22-jun | Mando | SEGURIDAD .git y .claude expuestos: RewriteRule en .htaccess raíz bloquea acceso web a /.git/ y /.claude/ con 403 |
| UPD-145 | 22-jun | Mando | SEGURIDAD test.php eliminado de producción (tenía display_errors=On y error_reporting=E_ALL) |
| UPD-146 | 22-jun | Mando | SEGURIDAD CORS completo: api/.htaccess y 14 APIs autenticadas cambiadas de * a https://apex.glass; estaciones.php y recibir_orden.php mantienen * intencionalmente |
| UPD-147 | 22-jun | Mando | SEGURIDAD directory listing en subcarpetas: .htaccess con Options -Indexes creado en app/, imagenes_comunicados/, archivos_campanas/, lib/, portal/ |

| UPD-148 | 22-jun | Mando | Fix operador.php estación terminado: agrega botón ámbar de omisión para piezas en estatus intermedios (pendiente, en_corte, cortado, canteado, trazo); flujo sin templado con pieza en taladro ahora muestra botón verde normal |
| UPD-149 | 22-jun | Mando | Fix buscar_orden.php: filtro por estación ampliado para incluir estatus anteriores en el flujo — operadores pueden encontrar piezas llegadas por omisión desde estaciones previas |

| UPD-150 | 22-jun | Mando | Permisos Compras: administracion y dueno pueden crear/editar OCs y partidas (antes solo dir_admin); api/ordenes_compra.php usa $ROLES_GESTIONAR_OC global |
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
**Próximo UPD disponible: UPD-178**
