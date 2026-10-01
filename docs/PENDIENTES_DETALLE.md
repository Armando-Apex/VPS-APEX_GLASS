# DETALLE DE PENDIENTES ACTIVOS (texto completo)
# Movido desde CLAUDE.md sección 12 el 01-oct-2026 (UPD-632). En CLAUDE.md queda un resumen con la etiqueta [D-nn] que apunta aquí.

## D-42

**Prioridad:** ALTA | **Resp.:** Armando

**ACTUALIZACIÓN 30-sep-2026: resuelto en su parte fiscal con el esquema A de anticipos del SAT (UPD-623 a 629).** Todo el saldo a favor (depósitos, excedentes, Apartado de Precio) es anticipo: se factura al recibirlo (pestaña "Anticipos por facturar"), la factura de la orden donde se usa va relacionada 07 y se emite sola la nota de crédito serie N; los reintegros de órdenes canceladas conservan su origen. Ya no hace falta crear órdenes placeholder con m² inventados para registrar un depósito: se usa "Registrar Depósito" en Cobranza → Saldo a Favor. Queda de esta fila: limpiar las órdenes placeholder históricas (Armando debe pasar los folios) y los 2 bugs señalados abajo (guard anti-doble-clic ya existe desde S2-04; revisar el XSS de `sfSelCliente`). Texto original: **Depósito a Cuenta / Saldo a Favor — rediseño en curso (10-jul-2026), NADA IMPLEMENTADO TODAVÍA.** Problema raíz: hoy se crea una orden con m² inventados solo para poder registrar un abono/depósito del cliente cuando aún no sabe qué va a comprar — esto duplica el ingreso reportado (una vez en la orden placeholder, otra vez cuando el cliente devenga el saldo en una orden real). Hallazgo clave de la investigación: **el mecanismo correcto YA EXISTE** (`clientes_saldo_favor` + `api/saldo_favor.php?accion=deposito` + tab "Saldo a Favor" en `finanzas_cobranza.php`) pero no se está usando. Recomendaciones dadas (sin confirmar/implementar): badge junto al folio en vez de nomenclatura de folio nueva; botón "Registrar Depósito" en la ficha del cliente; columna informativa "Pagado con Saldo a Favor" en Ventas y Cobranza (no afecta Acumulado en Pedidos); el depósito debería aparecer como fila de "cobranza" separada el día que se registra, sin sumar a "ventas" hasta que se devengue en una orden real (pendiente que Armando lo confirme). También se encontraron 2 bugs en el mecanismo existente sin arreglar (falta guard anti-doble-clic en `saldo_favor.php`, XSS en `sfSelCliente` mismo patrón que UPD-275) y un blast radius completo de 7+ queries en `api/reporte_direccion.php`/`api/inventario.php`/`portal/tablero.php` que habría que filtrar si se limpian órdenes placeholder históricas (falta que Armando pase los folios, no se detectan por texto). Detalle completo de la investigación, decisiones abiertas y citas textuales de Armando en la memoria de Claude (`project_deposito_cuenta_saldo_favor.md`)

**Estado:** Pendiente — diseño en discusión

## D-43

**Prioridad:** ALTA | **Resp.:** Armando

QR de salida por chofer (UPD-319) — verificado 13-jul-2026: las 4 plantillas Meta (`chofer_en_ruta_cliente`/`siguiente_entrega_cliente`/`chofer_en_ruta_asesor`/`siguiente_entrega_asesor`) ya están **APPROVED** (confirmado consultando la Graph API directo); `usuarios.telefono` de Bethy (8134000145) y Cynthia (8140051992) ya está cargado; nombres de choferes (`Juan Roberto García`, `Víctor Bautista`) ya son reales, no genéricos. Flujo completo funcional. Solo falta: prueba visual con un chofer real escaneando el QR físico

**Estado:** HECHO (config) — falta prueba física

## D-44

**Prioridad:** ALTA | **Resp.:** Mando

**GPS ProTrack365 en Logística Rutas (ver UPD-327/328, 338/339)** — HECHO: frontend conectado (línea única al siguiente destino, GPS en vivo), cron `scripts/gps_tracker.php` corriendo cada minuto guardando histórico en `gps_posiciones` y detectando llegada/movimiento. Sigue pendiente pedir al distribuidor la Open API oficial para no depender a largo plazo del fallback web no documentado (`permission denied` en la oficial)

**Estado:** Mayormente HECHO — falta Open API oficial del distribuidor

## D-50

**Prioridad:** MEDIA | **Resp.:** Mando

**Contabilidad (WIP) — plan por fases en curso** (proyecto Estado de Resultados/P&L, ver UPD-417/422/424/425). Navegación unificada: un solo botón "Contabilidad" en sidebar con pestañas internas (Catálogo/Mapeo/Nómina/...). Fases 0-5 HECHAS (Catálogo, Mapeo Compras, Nómina, Gastos Fijos, Caja Chica, Estado de Resultados) — el plan de fases queda completo. Pendiente: probar Nómina/Gastos Fijos/Caja Chica con datos reales capturados en el navegador, y comparar el P&L resultante contra el Excel de Armando de 1-2 meses cerrados antes de confiar en el reporte hacia adelante. Fase 6 (partida doble real) documentada pero fuera de alcance salvo que un contador externo la pida — plan conceptual armado 01-ago-2026 (ver UPD-442): requiere (1) ampliar `cuentas_contables` con cuentas de Balance (Activo: Bancos/CxC/Inventario; Pasivo: CxP/IVA por Pagar/Nómina por Pagar; Capital), (2) tabla de pólizas con encabezado + líneas Debe/Haber que sumen igual, (3) generador de póliza automático por cada tipo de evento de negocio (venta, costo de venta, pago a proveedor, nómina, etc. — ~10 tipos). Con eso se obtiene también Balance General, que hoy no existe. Confirmado con Armando 01-ago-2026: se queda solo como plan, no se construye salvo que lo empuje un contador/banco/inversionista real. Hallazgo clave de Fase 1: el costo de ventas por consumo real solo es confiable desde jul-2026 (cuando arrancó el wizard de corte que lo traza) — meses anteriores saldrían con margen falsamente alto.

**Estado:** Fase 0-5 HECHAS — falta probar captura real (Fases 2-4) y validar el P&L contra el Excel de Armando

## D-51

**Prioridad:** MEDIA | **Resp.:** Ambos

**Contabilidad — pruebas pendientes en navegador (01-ago-2026).** Con las Fases 0-5 construidas (y la lógica de ingresos/costo de ventas reescrita el mismo día por Armando — ver `api/helpers/pnl_datos.php`: ingreso reconocido al VoBo con `cotizaciones.subtotal`, costo de ventas por m² vendidos × precio promedio de compra por tipo/espesor normalizado desde `piezas.cristal`, gastos de Compras tipo suministro mapeados por categoría vía `gastosComprasPorCuenta()`), falta probar TODOS los módulos con datos reales capturados desde el navegador: Catálogo de Cuentas, Mapeo Compras, Nómina, Gastos Fijos, Caja Chica y el Estado de Resultados final. Nota de aislamiento confirmada con el usuario: las 8 tablas nuevas de Contabilidad (`cuentas_contables`, `cuenta_mapeo_reglas`, `nomina_empleados`, `nomina_pagos`, `gastos_fijos_conceptos`, `gastos_fijos_pagos`, `caja_chica_movimientos`, `movimientos_contables`) son de solo lectura hacia el resto del sistema — borrar cualquier fila de prueba en ellas mientras se prueba NO afecta ningún otro módulo (ninguna tabla existente del sistema las referencia). Al terminar de probar, comparar el P&L resultante contra el Excel de Armando de 1-2 meses ya cerrados antes de confiar en el reporte hacia adelante.

**Estado:** Pendiente — probar todos los módulos y validar contra el Excel

## D-52

**Prioridad:** ALTA | **Resp.:** Armando

**AVISO PARA ARMANDO — revisar `/home/mando/files_apexglass/auditoria_contabilidad_partida_doble_2026-08-03.md`.** Auditoría del módulo Contabilidad (Fases 6.0-6.3, partida doble) contra prácticas contables estándar. Hallazgo crítico: `movimientos_contables` (de donde lee el P&L) y `polizas_lineas` (de donde lee el Balance) son dos libros separados sin reconciliar — coinciden hoy porque se generan juntos en el mismo código, pero pueden desincronizarse sin aviso. Además: Nómina/Gastos Fijos sobrescriben en vez de versionar sus correcciones, sin doble control (maker-checker) en pólizas manuales, sin motivo/responsable al anular una póliza, sin cierre de periodo. Detalle completo y priorización en el archivo.

**Estado:** Pendiente — Armando debe revisar el archivo y decidir qué se corrige

## D-53

**Prioridad:** MEDIA | **Resp.:** Mando

**AVISO PARA MANDO — revisar en tu próxima sesión (30-jul-2026):** el hook de auto-commit subió `scripts/gps_cache/` (incl. `apex_gps_token.json` con el `web_token` de sesión de ProTrack365) al repo de GitHub en el commit `b3dc5bb`. Verificado: ese token puntual ya había expirado (`web_exp` 19:09 UTC del mismo día) al momento de encontrarlo, no era explotable. La cuenta/contraseña real (`PROTRACK_ACCOUNT`/`PROTRACK_PASSWORD`) vive en `.env` fuera del repo y NO se filtró. Ya se agregó `scripts/gps_cache/` a `.gitignore` y se sacó del tracking (`git rm --cached`) para que no se repita — Armando decidió NO purgar el historial de git por ahora (repo privado, dato ya no explotable). Mando: confirma que el cache de GPS (`gps_tracker.php`, `gps_lib.php`) sigue funcionando bien sin estar trackeado en git (debería ser transparente, es solo un cache en disco)

**Estado:** Pendiente — solo revisión/confirmación

## D-54

**Prioridad:** MEDIA | **Resp.:** Armando

Videos de marketing con Remotion (UPD-351/352) — herramienta instalada y funcional en `herramientas/video-marketing/` (fuera del webroot). 3 videos de muestra hechos: promo genérico de marca, demo del Portal de Clientes (escritorio) y demo del Portal de Clientes (vertical, formato celular con marco de teléfono). Ninguno conectado todavía a una campaña real. Falta: (1) que Armando confirme si le gustan y para qué campaña específica los quiere usar, (2) revisar si `app/modulos/campanas.php` necesita soporte para plantillas Meta con header tipo VIDEO (hoy el wizard solo maneja `header_image_url` de imagen)

**Estado:** Pendiente — esperando feedback de Armando

## D-59

**Prioridad:** ALTA | **Resp.:** Armando

**Esquema de Referidos (UPD-450) — falta dar de alta la plantilla WhatsApp `referido_saldo_abonado` en Meta Business Manager** (categoría UTILITY, 3 variables: nombre del referente, monto abonado, nombre del referido) — sin ella, el abono de saldo al referente se sigue registrando bien en BD pero el aviso por WA no se envía (el código lo intenta y falla en silencio, a propósito, para no bloquear el VoBo). También pendiente prueba visual en navegador: campo "Código de Referido" en Cotizaciones, línea de descuento en la impresión, y el apartado nuevo `portal/referidos.php`

**Estado:** Pendiente — falta plantilla Meta + prueba visual

## D-60

**Prioridad:** MEDIA | **Resp.:** Armando

**Costo de compra por período (Reporte Dirección → Rentabilidad, `api/inventario.php` accion=costo_promedio, UPD-473) no es un costeo promedio real.** Armando detectó la limitación al preguntar sobre el impacto de comprar 10 láminas caras de Claro 9mm: `costo_prom_m2_mes_actual`/`FECHA_INICIO_PRECIO_REAL` solo suman las compras (`inventario_compras.fecha_compra`) DENTRO del período seleccionado — no incluyen el valor del inventario que ya estaba en almacén al inicio del período. Es "cuánto pagaste por lo comprado en el rango", no "cuánto vale hoy el inventario disponible" (que requeriría saldo inicial a la fecha de corte + compras del período, ponderado por m²). Hoy no afecta a Claro 9mm porque su stock físico ya está en 0 desde antes de agosto, pero en cualquier tipo/espesor que sí tenga stock arrastrado de un mes anterior, ese inventario viejo se ignora por completo en este número aunque siga físicamente en la bodega. Armando decidió dejarlo como pendiente, sin implementar por ahora.

**Estado:** Pendiente — diseño a definir

## D-61

**Prioridad:** MEDIA | **Resp.:** Armando

**Insulado/espaciador — Paso 2: automatizar el cálculo por metro lineal (ver UPD-498/499).** Paso 1 (UPD-498) y Paso 2 (UPD-499) HECHOS: al agregar un servicio `ml` a una partida el módulo autocalcula el perímetro y propone la mitad de piezas; el alta por UI ya acepta decimales; el catálogo de servicios permite marcar "por pieza / por m.l." al crear/editar. Falta solo: (1) que Armando registre en el catálogo los demás espaciadores por tamaño con su precio (autoservicio desde el modal "Catálogo de Servicios"), y (2) prueba visual en navegador (agregar el espaciador a una partida y confirmar que el perímetro se llena solo).

**Estado:** HECHO UPD-499 — falta registrar precios por tamaño + prueba visual

## D-66

**Prioridad:** MEDIA | **Resp.:** Mando

**Sprint 3 (auditoría, UPD-505) — falta lo más grande.** Hecho: S3-01 infra, S3-03, S3-04, S3-07, S3-08, S3-02 parcial (2 de ~8 archivos). Falta: sweep de emojis en `operador.php` (~104), `produccion_estaciones.php` (SmartTV), `admin_comunicados.php`, `logistica_rutas.php`, `facturacion.php`, `clientes.php`, `chofer_ruta.php`; migrar los 338 `alert()`/`confirm()` a `toast()`/`confirmar()` (S3-01 resto); S3-05 (labels for/id + aria-label); S3-06 (validación visual completa); S3-09 (paginación "Mostrando X–Y de Z"); S3-10 (responsive en 7 módulos densos)

**Estado:** Pendiente — retomar en sesión dedicada

## D-67

**Prioridad:** ALTA | **Resp.:** Armando

**Promo Estados WhatsApp por volumen (UPD-516/517) — falta el gráfico + prueba visual.** Código construido y probado con dry-run en BD (código personal CTN-###PROMO). Tramos vigentes (ajustados en UPD-517): 1-4→5%, 5-50→7.5%, 51-99→12.5%, 100+→20%. Riesgo conocido sin confirmar por Armando: el tramo 100+ al 20% da solo ~$341/m² de utilidad en Claro 9mm si se usa el costo de compra más caro del mes (por debajo del piso de $400-550/m² discutido) — sí cumple ($416/m²) con costo promedio. Falta: (1) que Armando diseñe y publique el gráfico/texto del Estado de WhatsApp explicando la tabla de tramos y pidiendo al cliente decir su código al asesor; (2) prueba visual en navegador creando una cotización real con el código (no hay Chrome DevTools/Playwright MCP en la sesión que lo construyó); (3) si se quiere medir el ROI de la campaña más adelante, ya se puede reportar por `cotizaciones.promocion_id` — no hay dashboard dedicado todavía, se arma bajo pedido

**Estado:** Pendiente — falta gráfico de Armando + prueba visual

## D-68

**Prioridad:** ALTA | **Resp.:** Ambos

**Auditoría v2 pre-release 19-ago-2026 (`/home/mando/files_apexglass/auditoria_19_08_26/`) — Sprints A/B/C hechos y probados, Sprint D apenas arrancado (UPD-525 a 528).** Falta: A-04 IDOR portal por nombre (necesita backfill de `cliente_id` en 216 órdenes antes de quitar el fallback — decisión de Mando pendiente); C-04 rotar token WA `***TOKEN-VERIFICACION-WA-REDACTADO***` (confirmado que sigue siendo el mismo en producción — requiere acceso de Armando a Meta Business Manager, Mando no puede hacerlo); C-05 `git rm --cached` de los 4 `error_log` (acción manual, mismo pendiente que S2-12); D-02 descartado a petición de Mando (set de íconos SVG no cubre todos los emojis de `orden.php`, se deja como está); D-03 a D-07 (colores `#94a3b8` residuales, labels for/id, paginación resumen.php, estados vacío/error en orden.php, media queries) sin empezar

**Estado:** Pendiente — retomar Sprint D en sesión dedicada, A-04/C-04/C-05 requieren decisión/acción de Armando o Mando

## D-69

**Prioridad:** ALTA | **Resp.:** Ambos

**Auditoría externa 20-ago-2026 (`/home/mando/files_apexglass/auditoria_20_08_26/`, 61 hallazgos) — Sprint P0 (UPD-531), P1 (UPD-532) hechos. Sprint P2 (visual, 20 hallazgos) CERRADO 22-ago-2026 (UPD-534/535/536): 18 de 20 hallazgos atendidos (UX-1,2,3,5,6,8,9,10,11,14,15,17,18,19,20 + parcial 6).** Quedan documentados como pendiente aparte, sin fecha comprometida: **UX-7** (spinner en "Cargando…" de tablas, decisión consciente de no tocar — bajo impacto real); **UX-12** (modal compartido — evaluar si conviene un helper nuevo en utils.js o parchear módulo por módulo); **UX-4** (rebrand de paleta login/operador oscuro-ámbar vs. dashboard claro-azul vs. portal claro-ámbar — alto impacto visual para piso, requiere decisión explícita antes de tocar); **UX-13** (hoja de estilos `apex-ui.css` compartida real — cambio de arquitectura, blast radius amplio); **UX-16** (header de contexto/breadcrumb por módulo, prioridad Baja); y los 190 sitios de labels sin `for`/`id` fuera de login.php/finanzas_cobranza.php (hallado al hacer UX-6, no estaba en el alcance original de la auditoría). También sigue pendiente el resto de hallazgos medios/bajos de `auditoria_logica_negocio.md`/`auditoria_edge_cases.md` no cubiertos en P0/P1

**Estado:** Sprint P2 cerrado — pendientes sueltos documentados arriba, sin sesión dedicada agendada

## D-70

**Prioridad:** MEDIA | **Resp.:** Armando

**Certificado SSL de apex.glass — se renovó solo la madrugada del 03-sep-2026 (verificado 12-sep-2026), sin que nadie lo forzara con éxito confirmado. Sigue sin existir un mecanismo de renovación automática real.** Historial: vencía 12-sep, sin cron/timer dedicado a SSL (confirmado de nuevo 12-sep: solo existen los 2 jobs normales de AdminBolt — `check-for-updates` cada 35 min, `refresh-hosting-accounts-stats` cada 5 min — ningún timer de systemd de SSL). Verificado 12-sep-2026: `/home/apexglass2025/apex.glass/certs/cert.pem` tiene fecha de archivo 03-sep 07:55 UTC, vigente 03-sep-2026 → **02-dic-2026**, coincide con la ventana en que UPD-563 estaba probando `bolt-cli run-auto-ssl`/`ssl-health-check` esa misma madrugada (en ese momento el propio UPD documentó que el comando corrido a mano no parecía renovar nada — pero el archivo en disco muestra que sí se renovó poco después, causa exacta sin confirmar: no hay log de bolt-agent en esa ventana porque el journal no persiste reinicios, P1-3 sigue abierto). Riesgo de fondo sigue igual que UPD-563: depende del modo SSL de la zona Cloudflare (Full strict vs Full) — sigue pendiente que Armando lo confirme en el panel. **Acción concreta: revisar de nuevo el certificado ANTES de que se acerque el 02-dic-2026** — no asumir que "se renueva solo" solo porque pasó una vez sin causa confirmada.

**Estado:** Pendiente — resuelto hasta 02-dic-2026, sin mecanismo confirmado para la próxima vez

## D-71

**Prioridad:** MEDIA | **Resp.:** Mando

**SSH puerto 2222 (workaround de UPD-562) no está activo tras el reinicio del VPS del 2-sep — la regla de firewall era solo runtime y se perdió (P1-1, hallado 03-sep).** No se ha tocado todavía en la sesión root. Antes de decirle a Armando "prueba el 2222 sin VPN", avisarle que la prueba de UPD-562 nunca fue válida (el puerto nunca llegó a abrirse en firme) para no confundirlo con un segundo intento fallido. Falta: confirmar si sobrevive `/etc/ssh/sshd_config.d/02-manual-port2222.conf`, reponer la regla de firewall, y esta vez darla de alta desde el panel AdminBolt (Firewall Rules) para que sí sobreviva un reinicio

**Estado:** Pendiente

## D-72

**Prioridad:** MEDIA | **Resp.:** Mando

**Journal de systemd no es persistente (`/var/log/journal` no existe, P1-3, hallado 03-sep).** Los logs de systemd viven solo en RAM y se borran en cada reinicio — toda la evidencia previa al reinicio del 2-sep se perdió y se volvería a perder en el próximo incidente. Fix documentado en el plan (`mkdir -p /var/log/journal` + `systemd-tmpfiles --create` + `systemctl restart systemd-journald`, con `SystemMaxUse` acotado) — bajo riesgo, no se ha ejecutado todavía

**Estado:** Pendiente

## D-73

**Prioridad:** MEDIA | **Resp.:** Mando

**Blindar contra AdminBolt los archivos que regenera en silencio (P1-4, hallado 03-sep).** Tres archivos con parches manuales que el panel puede pisar sin avisar si Armando toca algo relacionado desde ahí: `/etc/opt/remi/php84/php.d/zzz-apex.glass.ini` (vida de sesión 8h de UPD-560, límites de subida), `/etc/ssh/sshd_config.d/01-custom.conf` (el puerto 2222 vive aparte, en `02-manual-port2222.conf`, a propósito), y `/etc/httpd/vhosts.d/000-cloudflare-remoteip.conf` (mod_remoteip de UPD-563 — tiene precedente a favor, `panel-apex-glass-http.conf` es manual y sobrevive ahí desde el 27-jun). Ideal: configurar vida de sesión y límites de subida desde el panel en vez de a mano, donde se pueda

**Estado:** Pendiente

## D-74

**Prioridad:** ALTA | **Resp.:** Armando

**Fase B del diagnóstico Cloudflare (UPD-563) — requiere el panel de Cloudflare, que esta sesión no tiene.** (1) Confirmar el modo SSL de la zona (debe ser "Full strict"; si el certificado del Paso 3 de arriba vence sin resolver, esto decide si el sitio cae o no); (2) corregir MX/SPF de `apex.glass` — siguen apuntando al servidor de HostGator cancelado desde junio, cualquier correo a `@apex.glass` está roto (P2-1); (3) revisar que `panel.apex.glass` (AdminBolt) no esté proxeado por Cloudflare — hoy sale por IPs de Cloudflare y no debería; (4) revisar el nivel de seguridad / Bot Fight Mode de la zona — si está agresivo puede estar retando peticiones del SPA (**confirmado en la práctica 08-sep-2026, UPD-574: bloqueaba con 403 cualquier request con User-Agent por default de Python antes de llegar a Apache — resuelto ahí con un User-Agent propio para el listener del checador, pero el mismo problema podría afectar otros clientes no-browser legítimos**); (5) una vez resuelto lo anterior, cerrar el origen (443 directo a la IP del VPS) a solo los rangos de Cloudflare (P0-2) — hoy cualquiera puede saltarse el proxy yendo directo a `82.29.197.33`, coordinado con el firewall del servidor

**Estado:** Pendiente — esperando que Armando tenga acceso al panel

## D-75

**Prioridad:** MEDIA | **Resp.:** Ambos

**WhatsApp usernames / BSUID (Meta) — migración futura pendiente.** Armando reservó el username `@apex.glass` para la cuenta de WhatsApp Business (24-ago-2026, confirmación de Meta). Cuando un cliente activa su propio username, Meta deja de mandar su número de teléfono en el webhook y en su lugar manda un **BSUID** (Business-Scoped User ID, formato `PAIS.dígitos`, ej. `MX.134912086`) en el campo `user_id`. Verificado: **todo `api/whatsapp_webhook.php` está armado 100% sobre número de teléfono** — extrae `$msg['from']` como teléfono (línea ~43) y descarta cualquier valor que no tenga 10-15 dígitos numéricos (línea ~50, un BSUID se perdería en silencio); `whatsapp_conversaciones` se liga por `telefono` (match contra `clientes.telefono`/`telefono_alterno`); `campana_envios` liga respuestas de campaña por `telefono`. Todo el módulo Campañas (inbox, ventana 24h de UPD-521) depende de esa cadena. **No es urgente todavía** — reservar el username no activa el envío de BSUID por sí solo, depende de cuándo Meta lo prenda para nuestro número/clientes. Decisión 24-ago-2026: NO construir la migración completa por adelantado (mecanismo de Meta puede seguir cambiando) — solo se puso un canario barato (UPD-538): `api/whatsapp_webhook.php` ahora loggea `[BSUID?]` en error_log cuando un `from` no numérico llega y se descarta, en vez de perderlo en silencio como antes. Cuando aparezca ese log en producción, adaptar los 4 puntos de arriba (webhook, whatsapp_conversaciones, campana_envios, matching de clientes) para aceptar BSUID como identificador alterno sin perder el teléfono real donde ya se tiene.

**Estado:** Pendiente — canario puesto (UPD-538), migración completa en espera hasta que se vea `[BSUID?]` en el log real

## D-76

**Prioridad:** ALTA | **Resp.:** Armando

**Checador ZKTeco — Fase 2 (software).** Fase 1 (hardware/red) 100% lista y probada (UPD-567): reloj MB20-VL push por ADMS hacia la Raspberry Pi, funcionando con checadas reales. El bloqueo original ("esperar a que RH esté probado y estable") ya se resolvió: módulo de RH probado de punta a punta en navegador vía Playwright MCP y con un fix real aplicado (UPD-569) — listo para retomar Fase 2. Falta: (1) construir el listener de producción en la Pi (el actual `~/adms_listener.py` solo imprime, es de prueba) que parsee `ATTLOG` real y reenvíe a un endpoint nuevo `api/checador.php` en el VPS por HTTPS con llave de autenticación + buffer local si se cae internet; (2) dar de alta en `nomina_empleados` (ya con expediente completo gracias a UPD-568) a los 29 empleados reales que el reloj ya trae enrolados de antes, y mapear su PIN del reloj a su `id` de `nomina_empleados` (lista PIN↔nombre ya capturada, ver UPD-567); (3) decidir si el checador calcula el sueldo automático (requeriría tarifa diaria/hora, hoy `nomina_empleados.sueldo_base` es un monto fijo) o solo queda como registro de respaldo junto a la captura manual actual. **Conversación 07-sep-2026 (misma sesión root, tras UPD-569) — Armando abrió una feature nueva dentro de esta fase: alta/baja de empleados en el reloj físico manejada desde Apex**, porque muchos del listado PIN↔nombre de UPD-567 ya no trabajan ahí. Aclarado con Armando: **baja es 100% remota** (ADMS soporta comando servidor→dispositivo para borrar un usuario del reloj, sin tocar el aparato); **alta es solo parcial** — se puede crear el registro (PIN+nombre) remoto, pero el enrolamiento de huella/rostro exige que la persona esté físicamente frente al reloj al menos una vez (limitación de hardware, no de software). Confirmado que **con la configuración actual esto NO se puede hacer todavía** — faltan los 3 puntos de arriba más, específicamente para esta feature: una cola de "comandos pendientes" en el VPS que el listener de la Pi consulte por polling saliente (mismo patrón que ya usa el propio reloj para preguntarle al Pi) y una ruta en `api/checador.php` para encolar comandos de alta/baja desde la UI de RH — decisión de arquitectura ya tomada (no implementada): todo el tráfico Pi↔VPS es **iniciado siempre por el Pi** (push de checadas + poll de comandos pendientes), nunca al revés, para no requerir un túnel/VPN hacia el VPS (mismo criterio de seguridad de UPD-567 que ya descartó Tailscale hacia el VPS). **También aclarado:** la lista PIN↔nombre de los 29 empleados mencionada en UPD-567 no quedó guardada como archivo en ningún lado — solo existió en el contexto de esa sesión anterior — así que Armando tendrá que volver a compartirla para poder cargarla en `nomina_empleados` (hoy 0 registros). **Actualización 08-sep-2026 (UPD-570): el lado VPS de "alta/baja vía Apex" ya quedó construido** (BD + `api/checador.php` + pestaña Checador en RH) — falta el lado Pi (listener de producción que consuma `pendientes`/`confirmar`) y cargar los 29 empleados reales. Ver UPD-570 para el contrato completo. **08-sep-2026, misma sesión: Armando aclaró que él NO procesa la dispersión de nómina (outsourcing externo) y que el "reporte semanal" que eventualmente saldrá de aquí para mandar al despacho debe usar semana JUEVES-MIÉRCOLES (domingo descanso), no lunes-domingo — ver regla nueva en sección 1. Ese reporte queda para después; hoy el foco sigue siendo RH + el listener de la Pi.**

**Estado:** Pendiente — falta el listener de producción en la Pi (lado dispositivo) + cargar los 29 empleados reales; el reporte semanal para el despacho de nómina queda para después

## D-77

**Prioridad:** MEDIA | **Resp.:** Armando

**Cotizador dedicado de Insulados — idea propuesta 14-sep-2026, "lo hacemos después".** El insulado hoy vive como servicio adicional (`ml`, UPD-498/499) montado sobre 2 partidas normales pareadas manualmente por el asesor (una por cada tipo de cristal del par) — funciona bien cuando ambos vidrios del par son el MISMO tipo (la fórmula "mitad de piezas" asume eso), pero se rompe cuando son de tipos DISTINTOS (ver fix puntual UPD-583, COT-1790: Claro 6mm + Tintex 6mm, el sistema sub-contaba el espaciador a la mitad porque no distingue "1 partida = N paneles del mismo cristal" de "2 partidas ya pareadas 1:1 de cristales distintos"). Un cotizador propio de insulados capturaría el par como una unidad desde el inicio (2 cristales del par + medida + tipo de espaciador) en vez de simular el par con 2 partidas sueltas — eliminaría de raíz esta clase de error, no solo este caso. Sin diseño ni alcance definido todavía

**Estado:** HECHO UPD-613/614 — separador por m² ($750 con IVA) ya dado de alta; falta prueba de Armando en navegador

## D-78

**Prioridad:** MEDIA | **Resp.:** Armando

**Reporte Dirección — tarjeta global "Cotizaciones" (convTotal/convConv, "X convertidas a orden") arrastra el mismo bug ya corregido en UPD-579 para la columna por asesor: cuenta cualquier `orden_id` sin importar si la orden sigue `pendiente_vobo` o si la cotización es retrabajo.** Detectado 10-sep-2026 cuando a Armando no le cuadraba que "Órdenes" sumara 80 entre Yahaira+Bethy+Director Administrativo pero la tarjeta "Cotizaciones" dijera 72 convertidas. Desglose verificado: 68 en común, 12 solo en "Órdenes" (cotizadas en un mes anterior, VoBo cayó este mes — desfase de cohorte por diseño, no se puede eliminar), 4 solo en la tarjeta "Cotizaciones" (1 retrabajo + 3 `pendiente_vobo`, venta no confirmada — mismo bug de UPD-579, sin corregir aquí). Fix propuesto y aceptado en principio por Armando, pendiente de ejecutar: aplicar a `stmtConv` (api/reporte_direccion.php, ~línea 614, la query detrás de la tarjeta "Cotizaciones"/"Pendientes") el mismo `LEFT JOIN ordenes` + `o.estado IN ('activa','entregada')` en el numerador + `es_retrabajo=0` en el filtro, igual que ya se hizo en `conversion_por_asesor`. Aun con el fix, "Órdenes" (cohorte por VoBo) y "Cotizaciones convertidas" (cohorte por fecha de creación) NUNCA van a igualar exacto — eso hay que dejarlo claro de nuevo cuando se retome, no es un bug adicional

**Estado:** Pendiente — Armando dijo "lo revisamos después"

## D-79

**Prioridad:** BAJA | **Resp.:** Armando

**Descuento por Encuesta de Satisfacción (UPD-588/589/590) — HECHO y enviado a producción real.** Flow publicado en Meta, plantilla `encuesta_satisfaccion_v2` aprobada, código de descuento verificado con una respuesta real, reporte de concentrado en Reporte Dirección → Comercial, y campaña real mandada a los 365 clientes activos del CRM (UPD-590, 100% aceptados por Meta). Solo queda: (1) que Armando retire la plantilla vieja `encuesta_clientes` en Meta Business Manager cuando quiera (no es urgente, no afecta nada del sistema, ninguna referencia de código depende de ella); (2) prueba visual del campo "Código de Encuesta" en Cotización, cuando algún cliente real lo use por primera vez

**Estado:** Casi cerrado — solo falta que Armando retire la plantilla vieja en Meta cuando quiera

## D-81

**Prioridad:** ALTA | **Resp.:** Armando

**Encuesta de Satisfacción — falta la plantilla de "código reactivado" (ver UPD-596).** Los 28 códigos vencidos sin usar ya están reactivados en BD (vigencia hasta sábado 26-sep 23:59:59), pero nadie se ha enterado todavía — el mensaje original que recibieron decía que vencía en 24h, así que sin un aviso nuevo nadie lo va a volver a intentar. Propuesta ya dada: plantilla `encuesta_codigo_reactivado`, categoría UTILITY, body "Hola {{1}}, tu código de descuento por haber contestado nuestra encuesta {{2}} sigue vigente. Nueva fecha límite: {{3}}." — falta que Armando la dé de alta en Meta Business Manager; en cuanto esté aprobada se manda el aviso real a los 28. También pendiente decidir si se reintenta con los clientes de la campaña original (UPD-590) cuyo envío falló (34 de 365)

**Estado:** Pendiente — falta plantilla Meta

## D-82

**Prioridad:** BAJA | **Resp.:** Mando

**Hueco de funcionalidad confirmado en UPD-598: no existe forma de agregar una partida nueva a una cotización/orden ya convertida** (ni en `app/modulos/cotizacion.php` — el botón "+ Agregar partida" solo aparece con `estatus==='cotizacion'` — ni en `api/correcciones.php`, que solo edita/elimina partidas existentes). Se resolvió a mano vía SQL directo esa vez (S-893); si el caso se repite seguido, valorar construir una función real de "agregar partida post-conversión" (mismo patrón de recalcular `piezas` + totales que ya usa correcciones.php al aumentar cantidad)

**Estado:** Pendiente — sin urgencia, solo si se repite

## D-83

**Prioridad:** **ALTA** | **Resp.:** **Ambos**

**⭐ FACTURACIÓN — CHECKLIST ÚNICO PARA LEVANTAR A LIVE (28-sep-2026) ⭐**
**Esta fila es el índice maestro: lo que falta estaba repartido en 6 pendientes distintos. Empezar aquí.** Estado al 28-sep: el módulo funciona de punta a punta y está probado con datos reales en sandbox (emitir, cancelar, resguardo propio, trazabilidad, validación contra el padrón del SAT, candados de qué se puede facturar). Sprints 1, 2 y 3 cerrados (UPD-604 a 608, 611). `facturas` en 0 filas a propósito para que la primera real sea **A-001**. **Falta esto, en orden:**

**BLOQUEANTES — sin esto no se puede timbrar en real:**
~~**(1) Manifiesto en FacturAPI**~~ **HECHO 30-sep-2026: Armando confirma que ya se firmó** (FacturAPI ya reportaba `is_production_ready: true` y `pending_steps: []`). Texto original: **(1) Manifiesto en FacturAPI — lo firma HÉCTOR (actualizado 29-sep-2026; la e.firma de la empresa la tiene él, no Armando).** **ARRANQUE: la facturación en el sistema empieza el 01-oct-2026 con los ingresos de octubre.** Propuesto y SIN aplicar (Armando: "dejamos las cosas como están" hasta que Héctor firme): candado de fecha de arranque = solo facturar órdenes con VoBo ≥ 01-oct-2026 (Opción A recomendada: las 18 órdenes de septiembre con saldo, $33,099 al 29-sep, se facturan por fuera); y respaldo diario de `archivos_facturas/` a Drive (punto 7). Se firma con la **e.firma (FIEL) de la empresa, NO con el CSD** (el CSD solo sella comprobantes). Por eso Mando no puede hacerlo: requiere la e.firma del representante legal. Se sube directo en el panel de facturapi.io — ni la e.firma ni su contraseña pasan por el chat ni por el servidor. Hasta que esté firmado, la llave `sk_live_` no timbra aunque exista.
**(2) Llave `sk_live_` al `.env`** (`FACTURAPI_KEY_LIVE`) + cambiar `FACTURAPI_MODE=live`, vía AdminBolt. **NO hacerlo antes de cerrar los puntos 3 a 6.**
~~**(3) Borrar la factura de prueba A-002 que quedó timbrada**~~ **HECHO 29-sep (UPD-615): cancelada en sandbox y borrada, `facturas` en 0 filas.** (UPD-611, UUID F641E58B-…): si no, la primera real no arranca en A-001. Cancelarla en el sandbox de FacturAPI y borrar su fila + archivos de resguardo.

**DECISIONES DEL CONTADOR — definen si falta construir algo grande:**
**(4) ¿Se factura con saldo pendiente o solo ya pagado?** — **Aclaración del Anexo 20, Apéndice 8 (revisado 30-sep, UPD-627):** el 50% que el cliente paga al aceptar una cotización con producto y precio ya pactados NO es anticipo, es **venta en parcialidades → PPD con complemento por cada pago** (ya construido, UPD-615/619). Solo el saldo a favor, cuyo destino no está determinado, es anticipo (esquema A, UPD-623 a 629). — **ACTUALIZACIÓN 29-sep (UPD-615): el módulo de Complementos de Pago YA EXISTE y está probado en sandbox**, así que PPD ya es viable; la pregunta al contador queda solo para confirmar el tratamiento de anticipos (pagos anteriores a la factura) y de pagos con saldo a favor. Texto original: El flujo normal de la empresa es 50% de anticipo + resto, o sea territorio **PPD**. Si se factura al VoBo con saldo pendiente → PPD → el SAT obliga a emitir un **Complemento para Recepción de Pagos por cada abono, a más tardar el día 5 del mes siguiente**, y **el módulo hoy puede timbrar PPD pero NO puede emitir esos complementos** — genera una obligación que no cumple. Si la respuesta es "solo se factura ya pagado", se resuelve poniendo un candado que bloquee PPD y el Sprint 5 desaparece. Si es lo otro, es el bloque de trabajo más grande que queda. **Mientras no se decida: nadie debe timbrar en PPD.**
**(5) ¿Necesitan notas de crédito?** — **ACTUALIZACIÓN 30-sep (UPD-625): el CFDI de egreso (serie N) ya existe para la aplicación de anticipos (esquema A: anticipo → factura del total con relación 07 → nota de crédito con forma 30, automática). Siguen bloqueadas las notas de crédito manuales por devolución o descuento posterior a la factura (relación 01).** Texto original: (devoluciones, descuentos post-factura, rechazo por calidad después de facturar). Cancelar solo funciona limpio dentro de 72h; después el cliente tiene que aceptar en su buzón. Y cuando la venta SÍ ocurrió pero por menos monto, cancelar no aplica — se necesita nota de crédito. **Está bloqueada hoy, pero ya es trabajo chico:** la infraestructura de CFDI relacionados se construyó y probó en UPD-608, solo falta destrabar el tipo E y conectarlo.
**(6) Catálogo de claves SAT por producto.** Qué clave y qué unidad va con cada tipo de cristal y cada servicio. Es el único punto que queda del Sprint 3. Con eso, `clave_sat`/`unidad_sat` en `cristales` y `servicios_catalogo`, la clave deja de teclearse en cada factura y el parche de F-1 (UPD-604) sobra.

**REQUERIDO ANTES DE LIVE, PERO NO DEPENDE DE NADIE EXTERNO (Mando lo puede hacer):**
**(7) Los XML no salen del servidor — hallado 26-sep, es de cumplimiento.** El respaldo diario (`backup_runner.php`, cron 6am) sube la BD a Google Drive pero **NO respalda ningún archivo** (0 menciones a `archivos_`). Los comprobantes del resguardo viven SOLO en el disco del VPS. El SAT obliga a conservar el XML 5 años: un respaldo que no incluye lo que estás obligado a conservar no es respaldo. El resguardo protege contra que se caiga FacturAPI, no contra que se caiga el servidor. Fix barato: agregar `archivos_facturas/` al mismo script que ya sube a Drive.
**(8) Reporte de facturado vs. ventas sin facturar.** No existe ninguna pantalla que cruce facturas contra órdenes, así que hoy no hay forma de cerrar el mes ni de darse cuenta de que se quedó una venta sin comprobante. Va en Reporte Dirección.
**(9) Reactivar el envío de comprobantes por correo.** Apagado a propósito (ver pendiente dedicado abajo). El módulo no está terminado hasta que el cliente reciba su factura solo. **Probar primero con un correo interno, NO con el del CRM** — fue exactamente lo que falló el 26-sep.

**EL MÁS LENTO Y EL QUE NO DEPENDE DE CÓDIGO:**
**(10) Constancias de Situación Fiscal de los clientes.** Hoy **15 de ~396 clientes activos** tienen RFC + CP + régimen completos. La cadena ya está lista y sin fricción (subir CSF → el OCR llena los campos → guardar), y desbloqueada para administracion y comercial desde UPD-605/606. Después de capturar, correr `scripts/validar_datos_fiscales.php` para confirmar contra el padrón del SAT antes de facturarle a alguien.

**NO BLOQUEA (se puede hacer después de live):** paginación del listado (tope fijo de 200); limpieza de `alert()`/`confirm()`/emojis; que el descuento va disuelto en el precio unitario, así que el PDF del CFDI no muestra la línea de descuento que sí aparece en la cotización impresa (cosmético, pero el cliente puede notar que los papeles no se parecen).

**Estado:** **Pendiente — 1 y 3 HECHOS (30-sep). Bloqueante que queda: 2 (llave live). Decisiones del contador: 4, 5, 6. Mando puede hacer: 7, 8, 9. Captura continua: 10**

## D-85

**Prioridad:** ALTA | **Resp.:** Ambos

**Facturación — plan de arreglo por sprints (auditoría 24-sep, UPD-604). Sprints 1 y 2 HECHOS (ver UPD-604/605).** Falta: **Sprint 2 (confiabilidad) — HECHO en UPD-605, detalle conservado como referencia:** resguardo propio de XML/PDF en `archivos_facturas/` con `.htaccess deny` + columnas `pdf_path`/`xml_path` (hoy los CFDI solo viven en FacturAPI — el SAT exige conservar el XML 5 años, y la caída de suscripción del 24-sep demostró que se pierde el acceso); guardar el total/subtotal/IVA que devuelve el PAC tras timbrar (hoy no se guarda: si el PAC redondea distinto, BD y CFDI quedan desalineados sin aviso); cambiar la propiedad de `creado_por = nombre del usuario` a permiso (hoy otro dir_admin no puede cancelar la factura de un compañero, y si cambia el nombre de un usuario sus facturas quedan huérfanas) agregando `timbrado_por`/`cancelado_por` con fecha para no perder trazabilidad; dar permiso de facturar al rol `administracion` (Lina hoy no puede, solo dir_admin/dueno vía `ver_wip` — valorar un permiso propio `facturar`). **Sprint 3 (usabilidad):** catálogo real de claves SAT por producto (`clave_sat`/`unidad_sat` en `cristales` y `servicios_catalogo`, llenado con el contador) para que la clave no se teclee y el parche de F-1 sobre; botón "Facturar" desde Cotización/Cobranza (Task 8 del plan original, nunca se hizo — hoy hay que teclear el folio a mano); textos del módulo por modo real en vez de hardcodeados. **Sprint 4 (cierre):** reporte de facturado vs. ventas sin facturar en Reporte Dirección (hoy no existe nada, y es lo que se necesita para conciliar contra el SAT); definir serie/folio definitivo (hoy fija en "A" con la sección oculta); migrar 15 `alert()`/4 `confirm()`/1 `prompt()` a `toast()`/`confirmar()` y los emojis a `icono()` (pendiente de Sprint 3 de la auditoría 13-ago que nombra este archivo); paginación del listado (hoy tope fijo de 200 sin paginar); columna de folio de orden y estado del correo en el listado. **DECISIÓN 26-sep-2026 (Armando):** se construye y prueba TODO en sandbox antes de pasar a live — el paso a live debe ser solo meter la llave y cambiar el modo. Justificación verificada ese día: el sandbox de FacturAPI **sí valida RFC + razón social contra el padrón del SAT** (probado: nombre inventado con RFC real → 400; RFC inexistente → 400; combinación correcta → 200), así que no hay sorpresas que solo aparezcan en live. Lo único no verificable antes es que el manifiesto/CSD estén bien cargados (eso lo dice la primera factura real, pasa o truena con mensaje claro). **Dos items nuevos que salen de ahí:** (1) **validación masiva de datos fiscales contra el padrón** — script que por cada cliente con RFC capturado intente una validación en sandbox y liste quién pasa y quién no con el motivo; ataja el error más común de CFDI 4.0 (la razón social debe coincidir EXACTO con el padrón, mayúsculas y sin régimen de capital) antes de tenerlo enfrente de un cliente esperando su factura — va en Sprint 3 junto con la captura de CSF; (2) **folios de prueba:** las 5 facturas de prueba ocupan A-001..A-005 de forma permanente (el índice único es `(serie, folio_numero)` sin mirar `modo`, y el cálculo del siguiente folio tampoco distingue), así que live habría arrancado en A-006. Armando decidió **borrar A-001..A-005 al terminar de probar todo**, con lo cual live arranca limpio en A-001 y NO hace falta cambiar de serie. **Sprint 5 (solo si aplica):** complemento de pago (P) y nota de crédito (E) de verdad — **antes preguntar al contador si van a existir facturas PPD; si todas son PUE, este sprint no existe.** Detalle completo del plan y el razonamiento en el chat de la sesión del 26-sep.

**Estado:** Sprints 1 y 2 CERRADOS (UPD-604/605/606); Sprint 3 al 60% (UPD-607, falta el catálogo de claves SAT que depende del contador); sprints 4 y 5 pendientes, TODOS antes de live

## D-86

**Prioridad:** BAJA | **Resp.:** Mando

**`api/clientes.php` — inconsistencias menores en los gates por acción, revisadas y dejadas como están (UPD-605).** Tras corregir `$puede_editar` (ver UPD-605), la matriz quedó coherente y los roles de piso bloqueados. Quedan dos rarezas del propio archivo, sin corregir por ser decisión de producto y no un agujero: `dueno` puede `guardar_fiscal` pero NO `editar_contacto`/`editar_nombre` (no está en esas listas internas), y `guardar_fiscal` no tiene lista propia así que lo puede usar `comercial`, mientras `editar_nombre` está restringido a dir_admin/administracion. Si algún día se homologan, hacerlo con la matriz de 9 roles × acciones a la vista

**Estado:** Pendiente — sin urgencia, no es un agujero

## D-87

**Prioridad:** ALTA | **Resp.:** Ambos

**Facturación — envío de CFDI por correo APAGADO a propósito (26-sep-2026), se reactiva al FINAL del proyecto.** Interruptor: `FACTURACION_ENVIO_CORREO_ACTIVO = false` al inicio de `api/facturapi.php` (mismo patrón que `RUTA_WA_AVISOS_ACTIVO` en `rutas_lib.php`); su espejo en el frontend es `var FAC_CORREO_ACTIVO = false` en `app/modulos/facturacion.php`, que además oculta el botón "Reenviar correo" — **si se reactiva, hay que cambiar LOS DOS**. Motivo: al probar el módulo en vivo se descubrió que el correo del receptor viene pre-llenado del CRM, así que timbrar en sandbox **le mandó a un cliente real (CRISTAL Y ALGO MAS) un CFDI de pruebas adjunto** — pasó de verdad con las facturas de prueba A-001 y A-003 antes de poner el candado. Al reactivarlo queda vigente un segundo candado independiente: mientras `FACTURAPI_MODE` no sea `live`, tampoco se envía. Antes de prenderlo, confirmar con quién se prueba (idealmente un correo interno, no el del CRM)

**Estado:** Pendiente — reactivar al final

## D-89

**Prioridad:** ALTA | **Resp.:** Armando

**Anticipos — esquema A del SAT (decidido por Armando 30-sep-2026), por fases.** Fase 1 HECHA (UPD-623: forma de pago en el saldo a favor). Fase 2 HECHA (UPD-624: pestaña Anticipos por facturar). Fase 3 HECHA (UPD-625). Fase 4 HECHA (UPD-626: referido como descuento, saldo anterior, PUE obligatorio, reintegros con origen). **Queda:** prueba en navegador con sesión (Cobranza → depósito con forma; Facturación → Anticipos por facturar, factura con anticipo y nota N; cancelación de orden pagada con saldo) y validación del contador (PEPS, referido como descuento, saldos anteriores al 01-oct). Apartado de Precio = anticipo, resuelto por Armando en UPD-628 (el cliente lo usa en lo que quiera y no se le devuelve). Detalle original de las fases: ~~**Fase 2**~~, pestaña "Anticipos por facturar" en Facturación + botón "Facturar anticipo" (CFDI I 84111506/ACT, PUE, forma real; Público en General con Información Global si el cliente no tiene datos fiscales; solo depósitos desde el 01-oct-2026); **Fase 3**, la factura de una orden pagada con saldo a favor sale por el total con relación 07 a los anticipos consumidos del más antiguo al más nuevo (forma = la del monto mayor; 30 si es el anticipo) + nota de crédito automática (CFDI E, serie `N`, forma 30, relación 07); **Fase 4**, bono de referido aplicado = descuento dentro de la factura; saldos anteriores al 01-oct = factura normal con forma a mano; orden con factura PPD pagada después con saldo a favor = bloqueada con aviso. Pendiente que el contador valide: referido como descuento y el trato de los saldos anteriores al 01-oct

**Estado:** Fases 1-4 HECHAS — falta prueba en navegador y validación del contador

## D-92

**Prioridad:** MEDIA | **Resp.:** Armando

**PROYECTO: Buzón de facturas de proveedores → orden de compra interna (propuesto 30-sep-2026, NADA construido).** Idea de Armando: que los proveedores manden sus facturas a un buzón y que, según el proveedor, se genere la orden de compra. **Estado actual verificado:** Compras tiene 21 proveedores y 79 OC (58 de material/lámina, 21 de suministro) con partidas lamina/flete/otro; **`proveedores` no tiene columna RFC**, que es la llave para identificar al emisor del CFDI. **Bloqueo de correo:** el MX de `apex.glass` sigue en `mail.apex.glass` → 192.185.70.129 (HostGator, cancelado en junio), así que todo correo a @apex.glass se pierde (mismo pendiente P2-1 de la Fase B de Cloudflare); `tnglass.com.mx` sí está en Google Workspace. El servidor no tiene la extensión `imap` de PHP (se leería por IMAP con Python o con una conexión propia, o por la API de Gmail). **Diseño propuesto:** (1) buzón `facturas@tnglass.com.mx`, revisado por cron cada ~10 min, más la opción de subir el XML a mano en Compras (facturas que llegan por WhatsApp); (2) lectura del CFDI: receptor = TNO140604640, emisor → proveedor por RFC, no duplicar por UUID, consulta de vigencia en el SAT, resguardo de XML/PDF; (3) reglas por proveedor: tipo de OC (material/suministro), categoría, días de crédito y mapeo de conceptos a `laminas` (p. ej. "CLARO 6MM 3600X2600" → lámina); fletes como partida flete; lo no reconocido va a revisión manual; (4) la OC se crea en estado **"por revisar"** ligada a su factura y se confirma con un clic, de ahí sigue el flujo normal (recepción, pagos, inventario, pólizas). **Decisiones pendientes de Armando:** (a) ¿hoy la OC se crea antes de pedir o cuando llega la factura? Si existe antes, la factura debe ligarse a la OC existente (comparar lo pedido contra lo facturado) en vez de crear otra; (b) confirmar que se cree "por revisar" y no final; (c) crear la cuenta `facturas@tnglass.com.mx` y su contraseña de aplicación (la captura Armando en el panel, nunca por chat). **Primera fase sugerida, que no depende del correo:** RFC en proveedores + lectura de XML subido a mano.

**Estado:** Pendiente — definir (a)(b)(c) y armar plan por fases

## D-93

**Prioridad:** MEDIA | **Resp.:** Armando

**Orden de descuentos + campañas de precio Claro 9mm/6mm — EN PAUSA (24-sep-2026), solo análisis, nada implementado.** Datos de sept-2026 (sin lámina completa): descuento real vs lista 18.4% en 9mm y 21.7% en 6mm; 21.7% manual es el más usado (28 órdenes, = $870/m² con IVA en 9mm); las 79 órdenes con >10% fueron aprobadas (candado sin efecto); descuentos manual+encuesta+referido se enciman (COT-1838 llegó a 33%). **Merma real:** la pedacería recupera 76% (9mm) / 62% (6mm) de la merma bruta → pérdida neta 7.9% / 15.4%; costo por m² vendido con merma real ≈ $311.80 (9mm) / $204.47 (6mm) sin IVA (compras sept $287.32 / $172.88). Precios que quiere Armando: **campaña $835 / $585** con IVA (margen real $408 / $300 por m²) — 2 campañas mismo precio, una enfocada en tiempo y otra en precio, con 2 códigos distintos vía `promo_precio_lib.php`; **mayoreo $800 / $550** (margen $378 / $270). Propuesta pendiente de decidir: acercar lista a la realidad (~$949/$649), tramos fijos por volumen, tope total de descuento 25%, asesor libre hasta 5%, definir quién es "mayorista", y no mandar la campaña a compradores activos (la última semana todos pagaron arriba de $835). Revisar también COT-1752 (partida 6mm con 99.9% de descuento)

**Estado:** En pausa — retomar cuando Armando lo pida
