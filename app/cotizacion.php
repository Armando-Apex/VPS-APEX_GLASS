<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user         = requirePermiso('ver_ordenes');
$rol          = $user['rol'];
$es_admin     = in_array($rol, ['dir_admin', 'dueno']);
$puede_editar = in_array($rol, ['dir_admin', 'dueno', 'comercial']);
$id_cot       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modo         = $id_cot ? 'ver' : 'nuevo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APEX GLASS — <?= $modo === 'nuevo' ? 'Nueva Cotización' : 'Cotización' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@400;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.header { background: #1a1a2e; color: white; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
.header h1 { font-size: 20px; font-weight: 800; letter-spacing: 1px; font-family: 'Syncopate', sans-serif; }
.header .right { display: flex; gap: 16px; align-items: center; }
.header a { color: #94a3b8; font-size: 13px; text-decoration: none; }

.main { padding: 24px; max-width: 1500px; margin: 0 auto; }

/* Cards */
.card { background: white; border-radius: 14px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,.06); margin-bottom: 20px; }
.card-title { font-size: 15px; font-weight: 800; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }

/* Folio badge */
.folio-badge { background: #1a1a2e; color: white; font-size: 22px; font-weight: 800; padding: 8px 20px; border-radius: 10px; font-family: 'Syncopate', sans-serif; letter-spacing: 2px; }

/* Grid de campos */
.form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
.form-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
.span-2 { grid-column: span 2; }
.span-3 { grid-column: span 3; }

.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .5px; }
.field input, .field select, .field textarea {
  padding: 10px 14px; border: 1.5px solid #e2e8f0;
  border-radius: 8px; font-size: 14px; color: #1e293b; background: white;
  width: 100%;
}
.field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: #2563eb; }
.field input[readonly] { background: #f8fafc; color: #64748b; }
.field .hint { font-size: 11px; color: #94a3b8; }

/* Partidas */
.partidas-header { display: grid; grid-template-columns: 36px 200px 55px 75px 75px 120px 150px 52px 52px 52px 130px 36px; gap: 6px; padding: 6px 10px; background: #f8fafc; border-radius: 8px; margin-bottom: 8px; }
.partidas-header span { font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; }
.partida-row { display: grid; grid-template-columns: 36px 200px 55px 75px 75px 120px 150px 52px 52px 52px 130px 36px; gap: 6px; padding: 8px 10px; background: white; border: 1.5px solid #e2e8f0; border-radius: 10px; margin-bottom: 8px; align-items: center; }
.partida-row input, .partida-row select { padding: 6px 8px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 12px; width: 100%; }
.partida-row input:focus, .partida-row select:focus { outline: none; border-color: #2563eb; }
.partida-row input[readonly] { background: #f8fafc; color: #64748b; }
.num-partida { font-weight: 800; color: #2563eb; text-align: center; font-size: 15px; }
.btn-del { background: #fee2e2; color: #dc2626; border: none; border-radius: 6px; width: 32px; height: 32px; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; }

/* Totales */
.totales-box { background: #f8fafc; border-radius: 12px; padding: 20px; margin-top: 16px; }
.totales-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; font-size: 14px; color: #374151; }
.totales-row.total-final { font-size: 20px; font-weight: 800; color: #1e293b; border-top: 2px solid #e2e8f0; padding-top: 12px; margin-top: 4px; }

/* Botones */
.btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; transition: opacity .15s; }
.btn:hover { opacity: .85; }
.btn-primary  { background: #2563eb; color: white; }
.btn-success  { background: #16a34a; color: white; }
.btn-warning  { background: #d97706; color: white; }
.btn-danger   { background: #dc2626; color: white; }
.btn-ghost    { background: #f1f5f9; color: #374151; }
.btn-sm { padding: 7px 14px; font-size: 12px; }

.acciones { display: flex; gap: 10px; flex-wrap: wrap; }

/* Badges */
.badge { font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; }
.badge-cotizacion { background: #dbeafe; color: #1d4ed8; }
.badge-orden      { background: #fef3c7; color: #d97706; }
.badge-entregada  { background: #dcfce7; color: #16a34a; }
.badge-cancelada  { background: #f1f5f9; color: #94a3b8; }

/* Alerta saldo */
.alerta-saldo { background: #fee2e2; border: 1.5px solid #fca5a5; border-radius: 10px; padding: 14px 18px; color: #dc2626; font-weight: 700; margin-bottom: 16px; display: none; }
.alerta-saldo.show { display: flex; align-items: center; gap: 10px; }

/* Autocomplete cliente */
.autocomplete-wrap { position: relative; }
.autocomplete-list { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1.5px solid #e2e8f0; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12); z-index: 100; max-height: 240px; overflow-y: auto; }
.autocomplete-item { padding: 10px 14px; cursor: pointer; font-size: 14px; }
.autocomplete-item:hover { background: #f0f4f8; }
.autocomplete-item .codigo { font-size: 11px; color: #94a3b8; margin-top: 2px; }
</style>
</head>
<body>

<div class="header">
  <h1>APEX GLASS — <?= $modo === 'nuevo' ? 'Nueva Cotización' : 'Cotización' ?></h1>
  <div class="right">
    <a href="cotizaciones.php">← Cotizaciones</a>
    <a href="dashboard.php">Dashboard</a>
    <a href="../api/logout.php?redirect=../app/login.php">Salir</a>
  </div>
</div>

<div class="main" id="mainContent">
  <div class="empty" style="padding:48px;text-align:center;color:#94a3b8">Cargando...</div>
</div>

<script>
const API_COT      = '../api/cotizaciones.php';
const API_CLI      = '../api/clientes.php';
const API_CRIS     = '../api/cristales.php';
const ES_ADMIN     = <?= $es_admin ? 'true' : 'false' ?>;
const PUEDE_EDITAR = <?= $puede_editar ? 'true' : 'false' ?>;
const MODO         = '<?= $modo ?>';
const ID_COT       = <?= $id_cot ?>;

let cristales  = [];
let clienteSeleccionado = null;
let partidas   = [];

// ── Inicializar ───────────────────────────────────────────────────────────────
async function init() {
  // Cargar catálogo de cristales
  var res = await fetch(API_CRIS + '?activos=1');
  cristales = await res.json();

  if (MODO === 'nuevo') {
    renderFormulario(null);
  } else {
    var res2 = await fetch(API_COT + '?id=' + ID_COT);
    var data  = await res2.json();
    renderFormulario(data);
  }
}

// ── Render principal ──────────────────────────────────────────────────────────
function renderFormulario(data) {
  var esNuevo    = !data;
  var editable   = PUEDE_EDITAR && (esNuevo || data.estatus === 'cotizacion');
  var estatus    = data ? data.estatus : 'cotizacion';
  var bloqueada  = data && data.entrega_bloqueada == 1 && data.estatus === 'orden';

  var html = '';

  // Alerta saldo pendiente
  if (bloqueada && estatus !== 'entregada') {
    html += '<div class="alerta-saldo show">🔒 Esta orden tiene saldo pendiente — la entrega está bloqueada hasta que el dir_admin la autorice.</div>';
  }

  // ── Encabezado ──
  html += '<div class="card">';
  html += '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">';
  html += '<div>';
  if (!esNuevo) {
    html += '<div class="folio-badge">' + data.folio + '</div>';
    html += '<div style="margin-top:8px"><span class="badge badge-' + estatus + '">' + etiquetaEstatus(estatus) + '</span></div>';
  } else {
    html += '<div style="font-size:15px;font-weight:700;color:#1e293b">Nueva Cotización</div>';
    html += '<div style="font-size:13px;color:#94a3b8;margin-top:4px">El folio se generará al guardar</div>';
  }
  html += '</div>';

  // Acciones según estatus
  if (!esNuevo) {
    html += '<div class="acciones">';
    if (estatus === 'cotizacion' && PUEDE_EDITAR) {
      html += '<button class="btn btn-warning" onclick="convertirOrden()">🏭 Convertir a Orden</button>';
    }
    if ((estatus === 'orden' || estatus === 'cotizacion') && PUEDE_EDITAR) {
      html += '<button class="btn btn-ghost btn-sm" onclick="imprimirOrden()">🖨️ Orden de Producción</button>';
      html += '<button class="btn btn-ghost btn-sm" onclick="generarPDF()">📄 PDF Cotización</button>';
    }
    if (estatus === 'orden' && PUEDE_EDITAR) {
      html += '<button class="btn btn-success btn-sm" onclick="marcarEntregada()">✅ Marcar Entregada</button>';
    }
    if (estatus === 'orden' && bloqueada && ES_ADMIN) {
      html += '<button class="btn btn-danger btn-sm" onclick="autorizarEntrega()">🔓 Autorizar Entrega</button>';
    }
    if (estatus !== 'cancelada' && ES_ADMIN) {
      html += '<button class="btn btn-ghost btn-sm" onclick="cancelar()">✕ Cancelar</button>';
    }
    html += '</div>';
  }
  html += '</div>';

  // Campos encabezado
  html += '<div class="form-grid">';

  // Cliente
  html += '<div class="field span-2"><label>Cliente *</label>';
  if (editable) {
    html += '<div class="autocomplete-wrap">';
    html += '<input type="text" id="clienteBusqueda" placeholder="Buscar cliente..." oninput="buscarCliente()" autocomplete="off" value="' + (data ? escHtml(data.cliente_nombre) : '') + '">';
    html += '<div class="autocomplete-list" id="clienteLista" style="display:none"></div>';
    html += '<input type="hidden" id="clienteId" value="' + (data ? data.cliente_id : '') + '">';
    html += '</div>';
  } else {
    html += '<input type="text" readonly value="' + escHtml(data.cliente_nombre || '') + '">';
  }
  html += '</div>';

  // Proyecto
  html += '<div class="field"><label>Proyecto / Referencia</label>';
  html += '<input type="text" id="fProyecto" placeholder="Ej: Casa Lomas" ' + (!editable ? 'readonly' : '') + ' value="' + escHtml(data ? data.proyecto : '') + '"></div>';

  // Descuento
  html += '<div class="field"><label>Descuento %</label>';
  html += '<input type="number" id="fDescuento" min="0" max="100" step="0.5" value="' + (data ? data.descuento : '0') + '" ' + (!editable ? 'readonly' : '') + ' onchange="recalcular()"></div>';

  // Condición de pago
  html += '<div class="field"><label>Condición de pago</label>';
  if (editable) {
    html += '<select id="fCondicion"><option value="anticipo"' + (data && data.condicion_pago==='anticipo'?' selected':'') + '>50% Anticipo</option><option value="pago_total"' + (data && data.condicion_pago==='pago_total'?' selected':'') + '>Pago Total</option></select>';
  } else {
    html += '<input type="text" readonly value="' + (data.condicion_pago === 'anticipo' ? '50% Anticipo' : 'Pago Total') + '">';
  }
  html += '</div>';

  // Tipo de entrega
  html += '<div class="field"><label>Tipo de entrega</label>';
  if (editable) {
    html += '<select id="fTipoEntrega"><option value="domicilio"' + (data && data.tipo_entrega==='domicilio'?' selected':'') + '>A domicilio</option><option value="planta"' + (data && data.tipo_entrega==='planta'?' selected':'') + '>Recoge en planta</option></select>';
  } else {
    html += '<input type="text" readonly value="' + (data.tipo_entrega === 'domicilio' ? 'A domicilio' : 'Recoge en planta') + '">';
  }
  html += '</div>';

  // Localidad
  html += '<div class="field"><label>Localidad</label>';
  if (editable) {
    html += '<select id="fLocalidad" onchange="toggleCiudad()"><option value="local"' + (data && data.localidad==='local'?' selected':'') + '>Local (MTY/SCT)</option><option value="foraneo"' + (data && data.localidad==='foraneo'?' selected':'') + '>Foráneo</option></select>';
  } else {
    html += '<input type="text" readonly value="' + (data.localidad === 'foraneo' ? 'Foráneo' : 'Local') + '">';
  }
  html += '</div>';

  // Ciudad
  html += '<div class="field" id="campoCiudad" style="display:' + (data && data.localidad==='foraneo' ? 'flex' : 'none') + '"><label>Ciudad destino</label>';
  html += '<input type="text" id="fCiudad" ' + (!editable?'readonly':'') + ' value="' + escHtml(data ? data.ciudad_destino : '') + '" placeholder="Ej: Saltillo" onchange="actualizarFechaEntrega()"></div>';

  // Fecha entrega
  html += '<div class="field"><label>Fecha de entrega</label>';
  html += '<input type="date" id="fFechaEntrega" ' + (!editable?'readonly':'') + ' value="' + (data ? (data.fecha_entrega||'').substring(0,10) : '') + '">';
  html += '<div class="hint">Se calcula automáticamente (5 días hábiles)</div>';
  html += '</div>';

  // Crédito
  html += '<div class="field"><label>Crédito</label>';
  if (editable) {
    html += '<select id="fCredito"><option value="no"' + (data && data.credito==='no'?' selected':'') + '>No</option><option value="si"' + (data && data.credito==='si'?' selected':'') + '>Sí</option></select>';
  } else {
    html += '<input type="text" readonly value="' + (data.credito === 'si' ? 'Sí' : 'No') + '">';
  }
  html += '</div>';

  // Alerta
  html += '<div class="field span-3"><label>Alerta / Nota especial</label>';
  html += '<input type="text" id="fAlerta" ' + (!editable?'readonly':'') + ' value="' + escHtml(data ? data.alerta : '') + '" placeholder="Ej: Urgente, cliente espera..."></div>';

  html += '</div>'; // form-grid
  html += '</div>'; // card encabezado

  // ── Partidas ──
  html += '<div class="card">';
  html += '<div class="card-title">📦 Partidas ' + (editable ? '<button class="btn btn-ghost btn-sm" onclick="agregarPartida()">+ Agregar partida</button>' : '') + '</div>';

  html += '<div class="partidas-header">';
  html += '<span>#</span><span>Cristal</span><span>Cant.</span><span>Ancho mm</span><span>Alto mm</span><span>Detalles</span><span>CPB</span><span>Resaques</span><span>TP</span><span>TA</span><span>Comentario etiq.</span><span></span>';
  html += '</div>';

  html += '<div id="partidasContainer">';

  // Cargar partidas existentes o vacías
  var partidasIniciales = data ? data.partidas : [];
  if (partidasIniciales.length) {
    partidas = partidasIniciales;
  } else {
    partidas = [crearPartidaVacia()];
  }

  for (var i = 0; i < partidas.length; i++) {
    html += renderPartida(i, partidas[i], editable);
  }
  html += '</div>';

  // Totales
  html += '<div class="totales-box" id="totalesBox">';
  html += renderTotales(data);
  html += '</div>';

  html += '</div>'; // card partidas

  // Botón guardar
  if (editable) {
    html += '<div style="display:flex;gap:12px;justify-content:flex-end;margin-top:8px">';
    html += '<a href="cotizaciones.php" class="btn btn-ghost">← Volver</a>';
    if (esNuevo) {
      html += '<button class="btn btn-success" onclick="guardarCotizacion()">💾 Guardar Cotización</button>';
    } else {
      html += '<button class="btn btn-success" onclick="actualizarCotizacion()">💾 Guardar Cambios</button>';
    }
    html += '</div>';
  }

  document.getElementById('mainContent').innerHTML = html;

  // Calcular fecha entrega si es nuevo
  if (esNuevo) actualizarFechaEntrega();
}

// ── Partida vacía ─────────────────────────────────────────────────────────────
function crearPartidaVacia() {
  return { cristal_id:'', cantidad:1, ancho:'', alto:'', detalles:'', cpb:'', resaques:0, taladros_pasados:0, taladros_avellanados:0, comentarios_etiqueta:'' };
}

// ── Render una partida ────────────────────────────────────────────────────────
function renderPartida(idx, p, editable) {
  var opciones = '<option value="">Seleccionar...</option>';
  for (var i = 0; i < cristales.length; i++) {
    var sel = (cristales[i].id == p.cristal_id) ? ' selected' : '';
    opciones += '<option value="' + cristales[i].id + '"' + sel + '>' + escHtml(cristales[i].nombre) + '</option>';
  }

  var h = '<div class="partida-row" id="partida_' + idx + '">';
  h += '<div class="num-partida">' + (idx + 1) + '</div>';

  if (editable) {
    h += '<select onchange="actualizarPartida(' + idx + ')" id="p_cristal_' + idx + '">' + opciones + '</select>';
    h += '<input type="number" min="1" value="' + (p.cantidad||1) + '" id="p_cant_' + idx + '" onchange="recalcularPartida(' + idx + ')">';
    h += '<input type="number" min="0" value="' + (p.ancho||'') + '" id="p_ancho_' + idx + '" placeholder="mm" onchange="recalcularPartida(' + idx + ')">';
    h += '<input type="number" min="0" value="' + (p.alto||'') + '" id="p_alto_' + idx + '" placeholder="mm" onchange="recalcularPartida(' + idx + ')">';
    var selDet = '<select id="p_det_' + idx + '">';
    selDet += '<option value="NO"'     + (p.detalles==='NO'?      ' selected':'') + '>NO</option>';
    selDet += '<option value="Plantilla"'  + (p.detalles==='Plantilla'?  ' selected':'') + '>Plantilla</option>';
    selDet += '<option value="Descuadre"'  + (p.detalles==='Descuadre'?  ' selected':'') + '>Descuadre</option>';
    selDet += '<option value="Forma"'      + (p.detalles==='Forma'?      ' selected':'') + '>Forma</option>';
    selDet += '<option value="Diametro"'   + (p.detalles==='Diametro'?   ' selected':'') + '>Diametro</option>';
    selDet += '</select>';
    h += selDet;
    var selCpb = '<select id="p_cpb_' + idx + '">';
    selCpb += '<option value="No"'                   + (p.cpb==='No'?                   ' selected':'') + '>No</option>';
    selCpb += '<option value="Perimetral"'            + (p.cpb==='Perimetral'?            ' selected':'') + '>Perimetral</option>';
    selCpb += '<option value="Larguero"'              + (p.cpb==='Larguero'?              ' selected':'') + '>Larguero</option>';
    selCpb += '<option value="Largueros"'             + (p.cpb==='Largueros'?             ' selected':'') + '>Largueros</option>';
    selCpb += '<option value="Cabezal"'               + (p.cpb==='Cabezal'?               ' selected':'') + '>Cabezal</option>';
    selCpb += '<option value="Cabezales"'             + (p.cpb==='Cabezales'?             ' selected':'') + '>Cabezales</option>';
    selCpb += '<option value="1 Larguero - 1 cabezal"'  + (p.cpb==='1 Larguero - 1 cabezal'?  ' selected':'') + '>1 Larguero - 1 cabezal</option>';
    selCpb += '<option value="2 Largueros - 1 cabezal"' + (p.cpb==='2 Largueros - 1 cabezal'? ' selected':'') + '>2 Largueros - 1 cabezal</option>';
    selCpb += '<option value="1 Larguero - 2 cabezales"'+ (p.cpb==='1 Larguero - 2 cabezales'?' selected':'') + '>1 Larguero - 2 cabezales</option>';
    selCpb += '</select>';
    h += selCpb;
    h += '<input type="number" min="0" value="' + (p.resaques||0) + '" id="p_res_' + idx + '">';
    h += '<input type="number" min="0" value="' + (p.taladros_pasados||0) + '" id="p_tp_' + idx + '">';
    h += '<input type="number" min="0" value="' + (p.taladros_avellanados||0) + '" id="p_ta_' + idx + '">';
    h += '<input type="text" value="' + escHtml(p.comentarios_etiqueta||'') + '" id="p_com_' + idx + '" placeholder="Nota etiqueta...">';
    h += '<button class="btn-del" onclick="eliminarPartida(' + idx + ')">×</button>';
  } else {
    var cristalNombre = p.cristal_nombre || '—';
    h += '<div style="font-size:13px">' + escHtml(cristalNombre) + '</div>';
    h += '<div style="text-align:center">' + (p.cantidad||1) + '</div>';
    h += '<div style="text-align:center">' + (p.ancho||'—') + '</div>';
    h += '<div style="text-align:center">' + (p.alto||'—') + '</div>';
    h += '<div style="font-size:12px;color:#64748b">' + escHtml(p.detalles||'—') + '</div>';
    h += '<div style="font-size:12px;color:#64748b">' + escHtml(p.cpb||'—') + '</div>';
    h += '<div style="text-align:center">' + (p.resaques||0) + '</div>';
    h += '<div style="text-align:center">' + (p.taladros_pasados||0) + '</div>';
    h += '<div style="text-align:center">' + (p.taladros_avellanados||0) + '</div>';
    h += '<div style="font-size:12px;color:#64748b">' + escHtml(p.comentarios_etiqueta||'—') + '</div>';
    h += '<div></div>';
  }
  h += '</div>';
  return h;
}

// ── Render totales ────────────────────────────────────────────────────────────
function renderTotales(data) {
  var subtotal = data ? parseFloat(data.subtotal||0) : 0;
  var iva      = data ? parseFloat(data.iva||0)      : 0;
  var total    = data ? parseFloat(data.total||0)    : 0;
  var saldo    = data ? parseFloat(data.saldo_pendiente||0) : 0;
  var fmt = function(n) { return '$' + n.toLocaleString('es-MX', {minimumFractionDigits:2}); };

  var h = '';
  h += '<div class="totales-row"><span>Subtotal</span><span id="tSubtotal">' + fmt(subtotal) + '</span></div>';
  h += '<div class="totales-row"><span>IVA (16%)</span><span id="tIva">' + fmt(iva) + '</span></div>';
  h += '<div class="totales-row total-final"><span>TOTAL</span><span id="tTotal">' + fmt(total) + '</span></div>';
  if (saldo > 0) {
    h += '<div class="totales-row" style="color:#dc2626"><span>Saldo pendiente</span><span>' + fmt(saldo) + '</span></div>';
  }
  return h;
}

// ── Agregar / eliminar partidas ───────────────────────────────────────────────
function agregarPartida() {
  partidas.push(crearPartidaVacia());
  var container = document.getElementById('partidasContainer');
  var idx = partidas.length - 1;
  var div = document.createElement('div');
  div.innerHTML = renderPartida(idx, partidas[idx], true);
  container.appendChild(div.firstChild);
}

function eliminarPartida(idx) {
  if (partidas.length <= 1) { alert('Debe haber al menos una partida'); return; }
  partidas.splice(idx, 1);
  var container = document.getElementById('partidasContainer');
  container.innerHTML = '';
  for (var i = 0; i < partidas.length; i++) {
    var div = document.createElement('div');
    div.innerHTML = renderPartida(i, partidas[i], true);
    container.appendChild(div.firstChild);
  }
  recalcular();
}

function actualizarPartida(idx) {
  // Al cambiar cristal re-calcular
  recalcularPartida(idx);
}

function recalcularPartida(idx) {
  recalcular();
}

// ── Calcular totales en tiempo real ──────────────────────────────────────────
function recalcular() {
  var descuento = parseFloat(document.getElementById('fDescuento')?.value || 0) / 100;
  var subtotal  = 0;

  for (var i = 0; i < partidas.length; i++) {
    var cristalId = document.getElementById('p_cristal_' + i)?.value;
    var cantidad  = parseInt(document.getElementById('p_cant_' + i)?.value || 1);
    var ancho     = parseInt(document.getElementById('p_ancho_' + i)?.value || 0);
    var alto      = parseInt(document.getElementById('p_alto_'  + i)?.value || 0);

    if (!cristalId || !ancho || !alto) continue;

    var cristal = cristales.find(function(c) { return c.id == cristalId; });
    if (!cristal) continue;

    var m2         = (ancho / 1000) * (alto / 1000);
    var precioUnit = m2 * parseFloat(cristal.precio_m2) * (1 - descuento);
    subtotal      += precioUnit * cantidad;
  }

  var iva   = subtotal * 0.16;
  var total = subtotal + iva;
  var fmt   = function(n) { return '$' + n.toLocaleString('es-MX', {minimumFractionDigits:2}); };

  if (document.getElementById('tSubtotal')) document.getElementById('tSubtotal').textContent = fmt(subtotal);
  if (document.getElementById('tIva'))      document.getElementById('tIva').textContent      = fmt(iva);
  if (document.getElementById('tTotal'))    document.getElementById('tTotal').textContent    = fmt(total);
}

// ── Autocomplete cliente ──────────────────────────────────────────────────────
var clienteTimer = null;
async function buscarCliente() {
  clearTimeout(clienteTimer);
  clienteTimer = setTimeout(async function() {
    var q = document.getElementById('clienteBusqueda').value.trim();
    document.getElementById('clienteId').value = '';
    clienteSeleccionado = null;
    if (q.length < 2) { document.getElementById('clienteLista').style.display = 'none'; return; }
    var res   = await fetch(API_CLI + '?q=' + encodeURIComponent(q) + '&activos=1');
    var lista = await res.json();
    var ul    = document.getElementById('clienteLista');
    if (!lista.length) { ul.style.display = 'none'; return; }
    ul.innerHTML = lista.slice(0,8).map(function(c) {
      return '<div class="autocomplete-item" onclick="seleccionarCliente(' + c.id + ',\'' + escJs(c.razon_social||c.nombre) + '\',\'' + escJs(c.localidad) + '\',\'' + escJs(c.ciudad||'') + '\')">' +
             escHtml(c.razon_social||c.nombre) + '<div class="codigo">' + (c.codigo||'') + ' · ' + (c.localidad==='foraneo'?'Foráneo':'Local') + (c.ciudad?' — '+c.ciudad:'') + '</div></div>';
    }).join('');
    ul.style.display = 'block';
  }, 250);
}

function seleccionarCliente(id, nombre, localidad, ciudad) {
  document.getElementById('clienteId').value         = id;
  document.getElementById('clienteBusqueda').value   = nombre;
  document.getElementById('clienteLista').style.display = 'none';
  clienteSeleccionado = { id, nombre, localidad, ciudad };

  // Auto-llenar localidad y ciudad
  if (document.getElementById('fLocalidad')) {
    document.getElementById('fLocalidad').value = localidad;
    toggleCiudad();
  }
  if (ciudad && document.getElementById('fCiudad')) {
    document.getElementById('fCiudad').value = ciudad;
  }
  actualizarFechaEntrega();
}

document.addEventListener('click', function(e) {
  var lista = document.getElementById('clienteLista');
  if (lista && !lista.contains(e.target) && e.target.id !== 'clienteBusqueda') {
    lista.style.display = 'none';
  }
});

// ── Localidad / Ciudad ────────────────────────────────────────────────────────
function toggleCiudad() {
  var esForaneo = document.getElementById('fLocalidad')?.value === 'foraneo';
  var campo = document.getElementById('campoCiudad');
  if (campo) campo.style.display = esForaneo ? 'flex' : 'none';
  actualizarFechaEntrega();
}

async function actualizarFechaEntrega() {
  var localidad = document.getElementById('fLocalidad')?.value || 'local';
  var ciudad    = document.getElementById('fCiudad')?.value    || '';
  try {
    var res  = await fetch(API_COT + '?calcular_fecha=1&localidad=' + localidad + '&ciudad=' + encodeURIComponent(ciudad));
    var data = await res.json();
    if (data.fecha_entrega && document.getElementById('fFechaEntrega')) {
      document.getElementById('fFechaEntrega').value = data.fecha_entrega;
    }
  } catch(e) {}
}

// ── Guardar cotización ────────────────────────────────────────────────────────
async function guardarCotizacion() {
  var clienteId = document.getElementById('clienteId')?.value;
  if (!clienteId) { alert('Selecciona un cliente'); return; }

  var partidasPayload = [];
  for (var i = 0; i < partidas.length; i++) {
    var cristalId = document.getElementById('p_cristal_' + i)?.value;
    var cantidad  = parseInt(document.getElementById('p_cant_'   + i)?.value || 1);
    var ancho     = parseInt(document.getElementById('p_ancho_'  + i)?.value || 0);
    var alto      = parseInt(document.getElementById('p_alto_'   + i)?.value || 0);
    if (!cristalId || !ancho || !alto) continue;
    partidasPayload.push({
      cristal_id:            parseInt(cristalId),
      cantidad:              cantidad,
      ancho:                 ancho,
      alto:                  alto,
      detalles:              document.getElementById('p_det_' + i)?.value || '',
      cpb:                   document.getElementById('p_cpb_' + i)?.value || '',
      resaques:              parseInt(document.getElementById('p_res_' + i)?.value || 0),
      taladros_pasados:      parseInt(document.getElementById('p_tp_'  + i)?.value || 0),
      taladros_avellanados:  parseInt(document.getElementById('p_ta_'  + i)?.value || 0),
      comentarios_etiqueta:  document.getElementById('p_com_' + i)?.value || '',
    });
  }

  if (!partidasPayload.length) { alert('Agrega al menos una partida válida (cristal + medidas)'); return; }

  var payload = {
    cliente_id:    parseInt(clienteId),
    proyecto:      document.getElementById('fProyecto')?.value    || '',
    descuento:     parseFloat(document.getElementById('fDescuento')?.value || 0),
    credito:       document.getElementById('fCredito')?.value     || 'no',
    condicion_pago:document.getElementById('fCondicion')?.value   || 'anticipo',
    tipo_entrega:  document.getElementById('fTipoEntrega')?.value || 'domicilio',
    localidad:     document.getElementById('fLocalidad')?.value   || 'local',
    ciudad_destino:document.getElementById('fCiudad')?.value      || '',
    fecha_entrega: document.getElementById('fFechaEntrega')?.value || '',
    alerta:        document.getElementById('fAlerta')?.value      || '',
    partidas:      partidasPayload,
  };

  try {
    var res  = await fetch(API_COT, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    var data = await res.json();
    if (data.ok) {
      window.location.href = 'cotizacion.php?id=' + data.id;
    } else {
      alert(data.error || 'Error al guardar');
    }
  } catch(e) { alert('Error de conexión'); }
}

// ── Acciones de estatus ───────────────────────────────────────────────────────
async function convertirOrden() {
  if (!confirm('¿Convertir esta cotización a Orden de Producción? No podrá editarse después.')) return;
  var res  = await fetch(API_COT, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({accion:'convertir_orden', id: ID_COT}) });
  var data = await res.json();
  if (data.ok) { location.reload(); } else { alert(data.error); }
}

async function marcarEntregada() {
  if (!confirm('¿Marcar esta orden como entregada?')) return;
  var res  = await fetch(API_COT, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify({accion:'marcar_entregada', id: ID_COT}) });
  var data = await res.json();
  if (data.ok) { location.reload(); } else { alert(data.error || 'Entrega bloqueada por saldo pendiente'); }
}

async function autorizarEntrega() {
  var nota = prompt('Motivo de autorización (opcional):') || '';
  var res  = await fetch(API_COT, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({accion:'autorizar_entrega', id: ID_COT, nota}) });
  var data = await res.json();
  if (data.ok) { location.reload(); } else { alert(data.error); }
}

async function cancelar() {
  if (!confirm('¿Cancelar esta cotización/orden? Esta acción no se puede deshacer.')) return;
  var res  = await fetch(API_COT, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify({accion:'cancelar', id: ID_COT}) });
  var data = await res.json();
  if (data.ok) { location.reload(); } else { alert(data.error); }
}

function imprimirOrden() {
  window.open('imprimir_orden.php?id=' + ID_COT, '_blank');
}

function generarPDF() {
  window.open('imprimir_cotizacion.php?id=' + ID_COT, '_blank');
}

async function actualizarCotizacion() {
  var clienteId = document.getElementById('clienteId')?.value;
  if (!clienteId) { alert('Selecciona un cliente'); return; }

  var partidasPayload = [];
  for (var i = 0; i < partidas.length; i++) {
    var cristalId = document.getElementById('p_cristal_' + i)?.value;
    var cantidad  = parseInt(document.getElementById('p_cant_'   + i)?.value || 1);
    var ancho     = parseInt(document.getElementById('p_ancho_'  + i)?.value || 0);
    var alto      = parseInt(document.getElementById('p_alto_'   + i)?.value || 0);
    if (!cristalId || !ancho || !alto) continue;
    partidasPayload.push({
      cristal_id:            parseInt(cristalId),
      cantidad:              cantidad,
      ancho:                 ancho,
      alto:                  alto,
      detalles:              document.getElementById('p_det_' + i)?.value || '',
      cpb:                   document.getElementById('p_cpb_' + i)?.value || '',
      resaques:              parseInt(document.getElementById('p_res_' + i)?.value || 0),
      taladros_pasados:      parseInt(document.getElementById('p_tp_'  + i)?.value || 0),
      taladros_avellanados:  parseInt(document.getElementById('p_ta_'  + i)?.value || 0),
      comentarios_etiqueta:  document.getElementById('p_com_' + i)?.value || '',
    });
  }
  if (!partidasPayload.length) { alert('Agrega al menos una partida válida'); return; }

  var payload = {
    accion:        'editar',
    id:            ID_COT,
    cliente_id:    parseInt(clienteId),
    proyecto:      document.getElementById('fProyecto')?.value    || '',
    descuento:     parseFloat(document.getElementById('fDescuento')?.value || 0),
    credito:       document.getElementById('fCredito')?.value     || 'no',
    condicion_pago:document.getElementById('fCondicion')?.value   || 'anticipo',
    tipo_entrega:  document.getElementById('fTipoEntrega')?.value || 'domicilio',
    localidad:     document.getElementById('fLocalidad')?.value   || 'local',
    ciudad_destino:document.getElementById('fCiudad')?.value      || '',
    fecha_entrega: document.getElementById('fFechaEntrega')?.value || '',
    alerta:        document.getElementById('fAlerta')?.value      || '',
    partidas:      partidasPayload,
  };

  try {
    var res  = await fetch(API_COT, { method:'PUT', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
    var data = await res.json();
    if (data.ok) {
      location.reload();
    } else {
      alert(data.error || 'Error al guardar');
    }
  } catch(e) { alert('Error de conexión'); }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function etiquetaEstatus(s) {
  var map = { cotizacion:'Cotización', orden:'Orden de Producción', entregada:'Entregada', cancelada:'Cancelada' };
  return map[s] || s;
}
function escHtml(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function escJs(s)   { return String(s||'').replace(/'/g,"\\'"); }

init();
</script>
</body>
</html>