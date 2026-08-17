<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=contabilidad_pnl'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
#pnlRoot { font-family: 'Outfit', system-ui, -apple-system, sans-serif; }

#pnlRoot .main { padding: 28px 32px 48px; max-width: 980px; margin: 0 auto; }

/* ── Hero: el número que importa, primero ── */
.pnl-hero {
  display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap;
  gap: 20px; padding-bottom: 24px; margin-bottom: 24px; border-bottom: 1px solid #e2e8f0;
}
.pnl-hero-eyebrow { font-size: 11px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
.pnl-hero-periodo { font-size: 15px; font-weight: 500; color: #334155; }
.pnl-hero-numero-wrap { text-align: right; }
.pnl-hero-label { font-size: 11px; font-weight: 600; letter-spacing: .1em; text-transform: uppercase; color: #64748b; margin-bottom: 4px; }
.pnl-hero-numero { font-size: 38px; font-weight: 700; letter-spacing: -.01em; color: #0f172a; font-variant-numeric: tabular-nums; line-height: 1; }
.pnl-hero-numero.positivo { color: #0f766e; }
.pnl-hero-numero.negativo { color: #9f1239; }
.pnl-hero-delta { font-size: 12.5px; font-weight: 600; margin-top: 6px; display: inline-flex; align-items: center; gap: 4px; }
.pnl-hero-delta.up { color: #0f766e; }
.pnl-hero-delta.down { color: #9f1239; }

/* ── Barra de controles: discreta, no compite con el hero ── */
.pnl-controls { display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap; margin-bottom: 22px; }
.pnl-controls select {
  padding: 7px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12.5px;
  font-family: inherit; background: #fff; color: #334155; cursor: pointer;
}
.pnl-controls select:focus-visible { outline: 2px solid #0f766e; outline-offset: 1px; }
.pnl-controls .sep { color: #cbd5e1; font-size: 12.5px; }
.pnl-controls label.chk { font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 5px; cursor: pointer; padding: 4px 2px; white-space: nowrap; }
.pnl-controls label.chk input { cursor: pointer; accent-color: #0f766e; }

/* ── Avisos de calidad de datos: nota discreta, no alarma ── */
.pnl-aviso {
  display: none; align-items: flex-start; gap: 9px; font-size: 12.5px; line-height: 1.55;
  color: #78716c; background: #fafaf9; border: 1px solid #e7e5e4; border-left: 3px solid #d97706;
  padding: 11px 14px; border-radius: 6px; margin-bottom: 20px;
}
.pnl-aviso.critico { border-left-color: #9f1239; color: #57534e; }
.pnl-aviso svg { flex-shrink: 0; margin-top: 1px; color: #d97706; }
.pnl-aviso.critico svg { color: #9f1239; }

.spinner {
  width: 20px; height: 20px; margin: 0 auto 10px; border-radius: 50%;
  border: 2.5px solid #e2e8f0; border-top-color: #64748b;
  animation: girar .7s linear infinite;
}
@keyframes girar { to { transform: rotate(360deg); } }

/* ── Estado financiero ── */
.pnl-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow-x: auto; }
.pnl-table { border-collapse: collapse; width: 100%; font-size: 14px; }
.pnl-table th, .pnl-table td { padding: 9px 20px; white-space: nowrap; }
.pnl-table th { text-align: right; font-weight: 600; color: #64748b; font-size: 11px; letter-spacing: .06em; text-transform: uppercase; padding-top: 18px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0; }
.pnl-table th:first-child, .pnl-table td:first-child { text-align: left; white-space: normal; min-width: 260px; }
.pnl-table td { text-align: right; font-variant-numeric: tabular-nums; color: #1e293b; }
.pnl-table td.concepto { color: #334155; }

.fila-seccion td.concepto { font-weight: 600; padding-top: 18px; color: #0f172a; font-size: 13px; }
.fila-seccion:first-child td.concepto { padding-top: 14px; }

.fila-linea td.concepto { color: #78716c; padding-left: 28px; font-size: 13px; }
.fila-linea td { font-size: 13px; color: #57534e; }

.fila-vacio td { color: #cbd5e1; font-style: italic; padding-left: 28px; font-size: 12.5px; }

.fila-total td { font-weight: 600; border-top: 1px solid #f1f5f9; padding-top: 8px; padding-bottom: 14px; color: #334155; }

.fila-subtotal td { font-weight: 600; border-top: 1px solid #cbd5e1; padding-top: 11px; font-size: 14.5px; }
.fila-subtotal td:not(.concepto) { color: #0f172a; }

.fila-final td { font-weight: 700; font-size: 16px; border-top: 2px solid #0f172a; padding-top: 14px; padding-bottom: 18px; }
.fila-final.positivo td:not(.concepto) { color: #0f766e; }
.fila-final.negativo td:not(.concepto) { color: #9f1239; }

.empty { text-align: center; padding: 48px; color:var(--c-muted); font-size: 14px; }

@media (max-width: 640px) {
  #pnlRoot .main { padding: 20px 16px 36px; }
  .pnl-hero { flex-direction: column; align-items: flex-start; }
  .pnl-hero-numero-wrap { text-align: left; }
  .pnl-hero-numero { font-size: 30px; }
  .pnl-controls { justify-content: flex-start; }
}
</style>

<div id="pnlRoot">
<div class="main">

  <div class="pnl-hero">
    <div>
      <div class="pnl-hero-eyebrow">Estado de Resultados</div>
      <div class="pnl-hero-periodo" id="heroPeriodo">&mdash;</div>
    </div>
    <div class="pnl-hero-numero-wrap">
      <div class="pnl-hero-label">Utilidad Neta</div>
      <div class="pnl-hero-numero" id="heroUtilidad">&mdash;</div>
      <div class="pnl-hero-delta" id="heroDelta" style="display:none"></div>
    </div>
  </div>

  <div class="pnl-controls">
    <select id="granularidad" aria-label="Granularidad del período">
      <option value="mensual" selected>Mensual</option>
      <option value="trimestral">Trimestral</option>
      <option value="semestral">Semestral</option>
      <option value="anual">Anual</option>
    </select>

    <span id="individualWrap">
      <span id="individualPeriodoWrap"><select id="individualPeriodo" aria-label="Período a mostrar"></select></span>
      <select id="individualAnio" aria-label="Año del período a mostrar"></select>
    </span>

    <span id="comparacionWrap" style="display:none">
      <span class="sep">de</span>
      <span id="desdePeriodoWrap"><select id="desdePeriodo" aria-label="Período inicial"></select></span>
      <select id="desdeAnio" aria-label="Año inicial"></select>
      <span class="sep">a</span>
      <span id="hastaPeriodoWrap"><select id="hastaPeriodo" aria-label="Período final"></select></span>
      <select id="hastaAnio" aria-label="Año final"></select>
      <label class="chk"><input type="checkbox" id="soloExtremos"> Solo extremos</label>
    </span>

    <label class="chk"><input type="checkbox" id="modoComparacion"> Comparar períodos</label>
  </div>

  <div class="pnl-aviso" id="avisoCobertura"></div>

  <div class="pnl-wrap" id="pnlContent">
    <div class="empty">Cargando...</div>
  </div>

</div>
</div>

<script>
var ModContabilidadPnl = (function(){
var API = '../api/contabilidad_pnl.php';
var ICONO_ALERTA = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>';

function fmt(n) {
  n = parseFloat(n || 0);
  return Math.abs(n).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function fmtMonto(n, esResta) {
  var v = parseFloat(n || 0);
  if (esResta && v > 0) return '(' + fmt(v) + ')';
  if (v < 0) return '(' + fmt(v) + ')';
  return fmt(v);
}

function fmtDinero(n) {
  var v = parseFloat(n || 0);
  return (v < 0 ? '-$' : '$') + fmt(v);
}

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s === null || s === undefined) ? '' : String(s);
  return d.innerHTML;
}

function hoy() {
  var d = new Date();
  return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
}

function pad2(n) { return ('0' + n).slice(-2); }
function fechaStr(y, m, d) { return y + '-' + pad2(m) + '-' + pad2(d); }
function ultimoDiaMes(y, m) { return new Date(y, m, 0).getDate(); }

var MESES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

function totalIdx(gran) {
  if (gran === 'mensual') return 12;
  if (gran === 'trimestral') return 4;
  if (gran === 'semestral') return 2;
  return 1;
}

function rangoMeses(gran, idx) {
  if (gran === 'mensual') return [idx, idx];
  if (gran === 'trimestral') return [(idx - 1) * 3 + 1, idx * 3];
  if (gran === 'semestral') return [(idx - 1) * 6 + 1, idx * 6];
  return [1, 12];
}

function labelPeriodo(gran, anio, idx) {
  if (gran === 'mensual') return MESES[idx - 1] + ' ' + anio;
  if (gran === 'trimestral') return 'T' + idx + ' ' + anio;
  if (gran === 'semestral') return 'S' + idx + ' ' + anio;
  return '' + anio;
}

function idxDeHoy(gran) {
  var m = new Date().getMonth() + 1;
  if (gran === 'mensual') return m;
  if (gran === 'trimestral') return Math.ceil(m / 3);
  if (gran === 'semestral') return Math.ceil(m / 6);
  return 1;
}

function periodoObj(gran, anio, idx) {
  var hoyStr = hoy();
  var rm = rangoMeses(gran, idx);
  var desde = fechaStr(anio, rm[0], 1);
  var hastaCalc = fechaStr(anio, rm[1], ultimoDiaMes(anio, rm[1]));
  var hasta = hastaCalc > hoyStr ? hoyStr : hastaCalc;
  return { label: labelPeriodo(gran, anio, idx), desde: desde, hasta: hasta, futuro: desde > hoyStr };
}

// Genera la lista de períodos (columnas) entre un punto Desde y un punto Hasta, en cualquier orden y
// pudiendo cruzar varios años (ej. T1 2025 a T3 2026). Si soloExtremos=true, omite los intermedios.
function periodosRango(gran, anioA, idxA, anioB, idxB, soloExtremos) {
  var n = totalIdx(gran);
  var kA = anioA * n + (idxA - 1);
  var kB = anioB * n + (idxB - 1);
  var kMin = Math.min(kA, kB), kMax = Math.max(kA, kB);

  function decodificar(k) {
    var anio = Math.floor(k / n);
    var idx = (k % n) + 1;
    return periodoObj(gran, anio, idx);
  }

  var periodos = [];
  if (soloExtremos) {
    periodos.push(decodificar(kMin));
    if (kMax !== kMin) periodos.push(decodificar(kMax));
  } else {
    for (var k = kMin; k <= kMax; k++) periodos.push(decodificar(k));
  }
  return periodos.filter(function(p) { return !p.futuro; });
}

function poblarAnios(sel) {
  var anioActual = new Date().getFullYear();
  var html = '';
  for (var y = anioActual; y >= anioActual - 6; y--) {
    html += '<option value="' + y + '">' + y + '</option>';
  }
  sel.innerHTML = html;
}

function poblarPeriodo(sel, gran) {
  var n = totalIdx(gran);
  var html = '';
  for (var idx = 1; idx <= n; idx++) {
    html += '<option value="' + idx + '">' + labelPeriodo(gran, '', idx).replace(/\s*$/, '') + '</option>';
  }
  sel.innerHTML = html;
}

function aplicarVisibilidadPeriodo(gran) {
  var mostrar = gran !== 'anual';
  document.getElementById('desdePeriodoWrap').style.display = mostrar ? '' : 'none';
  document.getElementById('hastaPeriodoWrap').style.display = mostrar ? '' : 'none';
  document.getElementById('individualPeriodoWrap').style.display = mostrar ? '' : 'none';
}

// El período que contiene hoy (ej. si hoy es agosto, en Mensual regresa agosto) — es el que se
// muestra por default al entrar. El período puede estar incompleto (mes en curso), el aviso de
// cobertura de costo ya avisa aparte si el dato no es confiable todavía.
function periodoActual(gran) {
  var anioActual = new Date().getFullYear();
  var idxHoy = idxDeHoy(gran);
  return { anio: anioActual, idx: idxHoy };
}

function aplicarModoComparacion() {
  var comparar = document.getElementById('modoComparacion').checked;
  document.getElementById('individualWrap').style.display = comparar ? 'none' : '';
  document.getElementById('comparacionWrap').style.display = comparar ? '' : 'none';
  cargar();
}

function iniciarControles() {
  var gran = document.getElementById('granularidad').value;
  var anioActual = new Date().getFullYear();
  var actual = periodoActual(gran);

  poblarAnios(document.getElementById('desdeAnio'));
  poblarAnios(document.getElementById('hastaAnio'));
  poblarAnios(document.getElementById('individualAnio'));
  poblarPeriodo(document.getElementById('desdePeriodo'), gran);
  poblarPeriodo(document.getElementById('hastaPeriodo'), gran);
  poblarPeriodo(document.getElementById('individualPeriodo'), gran);
  aplicarVisibilidadPeriodo(gran);

  document.getElementById('desdeAnio').value = anioActual;
  document.getElementById('hastaAnio').value = anioActual;
  document.getElementById('desdePeriodo').value = 1;
  document.getElementById('hastaPeriodo').value = idxDeHoy(gran);

  document.getElementById('individualAnio').value = actual.anio;
  document.getElementById('individualPeriodo').value = actual.idx;
}

function granularidadCambio() {
  var gran = document.getElementById('granularidad').value;
  var actual = periodoActual(gran);

  poblarPeriodo(document.getElementById('desdePeriodo'), gran);
  poblarPeriodo(document.getElementById('hastaPeriodo'), gran);
  poblarPeriodo(document.getElementById('individualPeriodo'), gran);
  aplicarVisibilidadPeriodo(gran);

  document.getElementById('desdePeriodo').value = 1;
  document.getElementById('hastaPeriodo').value = idxDeHoy(gran);
  document.getElementById('individualAnio').value = actual.anio;
  document.getElementById('individualPeriodo').value = actual.idx;

  cargar();
}

// Une los códigos de cuenta presentes en cualquiera de los períodos, preservando el orden de aparición.
function unirLineas(datas, seccion) {
  var vistos = {};
  var orden = [];
  for (var i = 0; i < datas.length; i++) {
    var lineas = datas[i][seccion].lineas;
    for (var j = 0; j < lineas.length; j++) {
      var cod = lineas[j].codigo;
      if (!vistos[cod]) { vistos[cod] = lineas[j].nombre; orden.push(cod); }
    }
  }
  return orden.map(function(cod) { return { codigo: cod, nombre: vistos[cod] }; });
}

function montoDeLinea(data, seccion, codigo) {
  var lineas = data[seccion].lineas;
  for (var i = 0; i < lineas.length; i++) {
    if (lineas[i].codigo === codigo) return parseFloat(lineas[i].monto || 0);
  }
  return 0;
}

function filaSeccion(titulo, datas, seccion, esResta) {
  var html = '<tr class="fila-seccion"><td class="concepto">' + esc(titulo) + '</td>';
  for (var i = 0; i < datas.length; i++) html += '<td></td>';
  html += '</tr>';

  var lineas = unirLineas(datas, seccion);
  if (!lineas.length) {
    html += '<tr class="fila-vacio"><td class="concepto">Sin movimientos</td>';
    for (var k = 0; k < datas.length; k++) html += '<td></td>';
    html += '</tr>';
  } else {
    for (var j = 0; j < lineas.length; j++) {
      html += '<tr class="fila-linea"><td class="concepto">' + esc(lineas[j].codigo) + ' — ' + esc(lineas[j].nombre) + '</td>';
      for (var m = 0; m < datas.length; m++) {
        html += '<td>' + fmtMonto(montoDeLinea(datas[m], seccion, lineas[j].codigo), esResta) + '</td>';
      }
      html += '</tr>';
    }
  }

  html += '<tr class="fila-total"><td class="concepto">Total ' + esc(titulo) + '</td>';
  for (var i2 = 0; i2 < datas.length; i2++) {
    html += '<td>' + fmtMonto(datas[i2][seccion].total, esResta) + '</td>';
  }
  html += '</tr>';

  return html;
}

function filaSubtotal(clase, titulo, datas, campo) {
  var html = '<tr class="' + clase + '"><td class="concepto">' + esc(titulo) + '</td>';
  for (var i = 0; i < datas.length; i++) {
    var v = parseFloat(datas[i][campo] || 0);
    html += '<td>' + fmtMonto(v, false) + '</td>';
  }
  html += '</tr>';
  return html;
}

function claseSigno(datas, campo) {
  var ultimo = datas[datas.length - 1][campo];
  return parseFloat(ultimo || 0) >= 0 ? 'positivo' : 'negativo';
}

async function cargar() {
  var gran = document.getElementById('granularidad').value;
  var comparar = document.getElementById('modoComparacion').checked;
  var periodos;

  if (comparar) {
    var desdeAnio = parseInt(document.getElementById('desdeAnio').value, 10);
    var hastaAnio = parseInt(document.getElementById('hastaAnio').value, 10);
    var desdeIdx = parseInt(document.getElementById('desdePeriodo').value, 10) || 1;
    var hastaIdx = parseInt(document.getElementById('hastaPeriodo').value, 10) || 1;
    var soloExtremos = document.getElementById('soloExtremos').checked;
    periodos = periodosRango(gran, desdeAnio, desdeIdx, hastaAnio, hastaIdx, soloExtremos);
  } else {
    var anio = parseInt(document.getElementById('individualAnio').value, 10);
    var idx = parseInt(document.getElementById('individualPeriodo').value, 10) || 1;
    var p = periodoObj(gran, anio, idx);
    periodos = p.futuro ? [] : [p];
  }

  if (!periodos.length) {
    document.getElementById('pnlContent').innerHTML = '<div class="empty">No hay períodos disponibles para ese año</div>';
    document.getElementById('heroPeriodo').textContent = '—';
    document.getElementById('heroUtilidad').textContent = '—';
    document.getElementById('heroDelta').style.display = 'none';
    return;
  }

  document.getElementById('pnlContent').innerHTML = '<div class="empty"><div class="spinner"></div>Cargando...</div>';

  try {
    var datas = await Promise.all(periodos.map(function(p) {
      return fetch(API + '?desde=' + p.desde + '&hasta=' + p.hasta).then(function(r) { return r.json(); });
    }));
    for (var i = 0; i < datas.length; i++) {
      if (datas[i].error) { document.getElementById('pnlContent').innerHTML = '<div class="empty" style="color:#9f1239">' + esc(datas[i].error) + '</div>'; return; }
    }
    render(periodos, datas);
  } catch (e) {
    document.getElementById('pnlContent').innerHTML = '<div class="empty" style="color:#9f1239">Error al cargar</div>';
  }
}

function renderHero(periodos, datas) {
  var ultimo = datas[datas.length - 1];
  var esComparacion = datas.length > 1;

  document.getElementById('heroPeriodo').textContent = esComparacion
    ? (periodos[0].label + ' → ' + periodos[periodos.length - 1].label)
    : periodos[0].label;

  var un = parseFloat(ultimo.utilidad_neta || 0);
  var elNum = document.getElementById('heroUtilidad');
  elNum.textContent = fmtDinero(un);
  elNum.className = 'pnl-hero-numero ' + (un >= 0 ? 'positivo' : 'negativo');

  var elDelta = document.getElementById('heroDelta');
  if (esComparacion) {
    var primero = parseFloat(datas[0].utilidad_neta || 0);
    var diff = un - primero;
    var subio = diff >= 0;
    elDelta.style.display = 'inline-flex';
    elDelta.className = 'pnl-hero-delta ' + (subio ? 'up' : 'down');
    elDelta.textContent = (subio ? '↑ ' : '↓ ') + (subio ? '+' : '-') + fmt(Math.abs(diff)) + ' vs ' + periodos[0].label;
  } else {
    elDelta.style.display = 'none';
  }
}

function render(periodos, datas) {
  renderHero(periodos, datas);

  // Avisos: se muestran solo si el ÚLTIMO período (el más reciente/relevante) los presenta.
  var d = datas[datas.length - 1];
  var cob = d.costo_ventas.cobertura;
  var avisosCriticos = [];
  var avisosLeves = [];
  if (cob && !cob.confiable) {
    avisosCriticos.push('En "' + esc(periodos[periodos.length - 1].label) + '" solo ' + cob.pct_cobertura + '% de las piezas entregadas tienen costo real trazado (' + cob.piezas_con_costo + ' de ' + cob.piezas_total + '). El margen mostrado puede estar sobrestimado.');
  }
  var comprasSinMapear = [];
  for (var di = 0; di < datas.length; di++) {
    if (datas[di].compras_sin_mapear && datas[di].compras_sin_mapear.length) comprasSinMapear = comprasSinMapear.concat(datas[di].compras_sin_mapear);
  }
  if (comprasSinMapear.length) {
    var totalSinMapear = 0, nombres = {};
    for (var si = 0; si < comprasSinMapear.length; si++) {
      totalSinMapear += parseFloat(comprasSinMapear[si].monto);
      nombres[comprasSinMapear[si].categoria] = true;
    }
    avisosLeves.push('$' + fmt(totalSinMapear) + ' en Compras (' + Object.keys(nombres).join(', ') + ') sin cuenta contable asignada — no incluidos en Gastos Operativos. Asígnalas en Mapeo Compras.');
  }
  var avisoEl = document.getElementById('avisoCobertura');
  if (avisosCriticos.length) {
    avisoEl.className = 'pnl-aviso critico';
    avisoEl.style.display = 'flex';
    avisoEl.innerHTML = ICONO_ALERTA + '<span>' + avisosCriticos.concat(avisosLeves).join('<br>') + '</span>';
  } else if (avisosLeves.length) {
    avisoEl.className = 'pnl-aviso';
    avisoEl.style.display = 'flex';
    avisoEl.innerHTML = ICONO_ALERTA + '<span>' + avisosLeves.join('<br>') + '</span>';
  } else {
    avisoEl.style.display = 'none';
  }

  var hayFinancieros = datas.some(function(x) { return x.financieros.lineas.length > 0; });
  var hayImpuestos = datas.some(function(x) { return x.impuestos.lineas.length > 0; });

  var html = '<table class="pnl-table"><thead><tr><th style="text-align:left">Concepto</th>';
  for (var i = 0; i < periodos.length; i++) html += '<th>' + esc(periodos[i].label) + '</th>';
  html += '</tr></thead><tbody>';

  html += filaSeccion('Ingresos', datas, 'ingresos', false);
  html += filaSeccion('(-) Costo de Ventas', datas, 'costo_ventas', true);
  html += filaSubtotal('fila-subtotal', 'Utilidad Bruta', datas, 'utilidad_bruta');

  html += filaSeccion('(-) Gastos Operativos', datas, 'gastos_operativos', true);
  html += filaSubtotal('fila-subtotal', 'Utilidad Operativa', datas, 'utilidad_operativa');

  if (hayFinancieros) html += filaSeccion('(-) Gastos Financieros', datas, 'financieros', true);
  if (hayImpuestos) html += filaSeccion('(-) Impuestos', datas, 'impuestos', true);

  html += filaSubtotal('fila-final ' + claseSigno(datas, 'utilidad_neta'), 'Utilidad Neta', datas, 'utilidad_neta');

  html += '</tbody></table>';

  document.getElementById('pnlContent').innerHTML = html;
}

iniciarControles();
document.getElementById('granularidad').addEventListener('change', granularidadCambio);
document.getElementById('modoComparacion').addEventListener('change', aplicarModoComparacion);
document.getElementById('individualAnio').addEventListener('change', cargar);
document.getElementById('individualPeriodo').addEventListener('change', cargar);
document.getElementById('desdeAnio').addEventListener('change', cargar);
document.getElementById('hastaAnio').addEventListener('change', cargar);
document.getElementById('desdePeriodo').addEventListener('change', cargar);
document.getElementById('hastaPeriodo').addEventListener('change', cargar);
document.getElementById('soloExtremos').addEventListener('change', cargar);

cargar();

return { init: cargar };
})();
</script>
