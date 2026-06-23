# APEX GLASS — HISTORIAL UPD-101 a UPD-150
# Bloque archivado: 18-jun-2026 → 22-jun-2026
# Referenciado desde CLAUDE.md § 13

---

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
