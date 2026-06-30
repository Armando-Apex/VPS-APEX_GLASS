# APEX GLASS — HISTORIAL UPD-001 a UPD-100
# Bloque archivado: 30-may-2026 → 18-jun-2026
# Referenciado desde CLAUDE.md § 13

---

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
| UPD-090b | 17-jun | Mando | Cobranza: auto-refresh al registrar pago y al cambiar estatus de pago — llama a cargar() para traer datos frescos del servidor |
| UPD-091 | 17-jun | Mando | Archivos en cotización: módulo archivos_ordenes removido del sidebar; botón "Archivos" integrado en barra de acciones de cotizacion.php (visible cuando es orden/entregada); abre modal con lista + subida de archivos (roles: comercial, administracion, dir_admin, dueno) |
| UPD-092 | 17-jun | Armando | Fix definitivo total cotizaciones: cotizaciones.subtotal es inconsistente (bruto en registros viejos, neto en nuevos) — todas las queries ahora usan SUM(precio_m2_usado×m2×cantidad) FROM cotizaciones_partidas como bruto canónico. Afecta api/finanzas.php (lista_vobo, detalle, cobranza, registrar_pago) y api/cotizaciones.php (GET detalle + GET lista). Detectado en S-078: subtotal almacenado como neto causaba doble descuento en VoBo |
| UPD-093 | 17-jun | Mando | Backup automático BD VPS: backup_runner.php actualizado con credenciales VPS (host ::1, apexglass2025_prod); cron configurado 0 6 * * * (12:00 AM Monterrey / UTC-6); backups en produccion/_backups/, retención 15 días, log en backup.log |
| UPD-094 | 17-jun | Mando | Seguridad _backups/: permisos carpeta 750, archivos 640; .htaccess actualizado con Require all denied + Deny from all (Apache 2.4); backup verificado: 39 tablas, 35 INSERT, 179.6 KB |
| UPD-095 | 18-jun | Armando | HostGator cancelado — servidor único ahora es VPS Hostinger |
| UPD-096 | 18-jun | Armando | modulos/cotizaciones.php + api/cotizaciones.php: search ahora busca en folio orden (S-XXX), cliente, folio cot y proyecto; auto-switch de tab si resultado está en tab distinto; paginación 25 registros por tab con controles Ant/Sig |
| UPD-097 | 18-jun | Armando | api/finanzas.php: estatus_pago se actualiza automáticamente a 'pagado' al registrar pago si (total - saldo_pagado) <= $0.99; BD: UPDATE retroactivo a 39 órdenes ya pagadas; modulos/finanzas_cobranza.php: estadoPago() usa misma tolerancia $0.99 |
| UPD-098 | 18-jun | Armando | imprimir_etiquetas.php: muestra servicios adicionales de la partida debajo del badge CPB/FILO MUERTO |
| UPD-099 | 18-jun | Armando | imprimir_etiquetas.php: badge servicio ajustado a fondo blanco + borde negro + texto negro; formato "DESCRIPCION - N" usando unidades_por_pieza (ej. RADIO - 4) |
| UPD-100 | 18-jun | Armando | BD: ordenes_compra += tipo ENUM(material,suministro) DEFAULT material + categoria VARCHAR(100) |
