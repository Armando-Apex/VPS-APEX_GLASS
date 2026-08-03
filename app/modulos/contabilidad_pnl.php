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
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f4f8; }

.main { padding: 24px; max-width: 1100px; margin: 0 auto; }

.top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px; }
.section-title { font-size: 18px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px; }
.wip-badge { font-size: 10px; background: #f59e0b; color: #000; font-weight: 700; padding: 2px 8px; border-radius: 99px; }
.wip-banner {
  background: #fef3c7; color: #92400e; font-size: 13px; padding: 10px 16px;
  border-radius: 8px; margin-bottom: 20px;
}
.aviso-cobertura {
  background: #fee2e2; color: #991b1b; font-size: 13px; padding: 10px 16px;
  border-radius: 8px; margin-bottom: 20px; display: none;
}
.aviso-cobertura.leve { background: #fef3c7; color: #92400e; }

.rango-selector { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rango-selector select { padding: 8px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; background: white; }
.rango-selector .sep { color: #94a3b8; font-size: 13px; }
.rango-selector label.chk { font-size: 12.5px; color: #64748b; display: flex; align-items: center; gap: 5px; margin-left: 4px; cursor: pointer; padding: 4px 2px; }
.rango-selector label.chk input { cursor: pointer; }

.spinner {
  width: 22px; height: 22px; margin: 0 auto 10px; border-radius: 50%;
  border: 3px solid #e2e8f0; border-top-color: #64748b;
  animation: girar .7s linear infinite;
}
@keyframes girar { to { transform: rotate(360deg); } }

/* Estado financiero clásico: tabla con columnas por período */
.pnl-wrap { background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.06); overflow-x: auto; }
.pnl-table { border-collapse: collapse; width: 100%; font-size: 14.5px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }
.pnl-table th, .pnl-table td { padding: 8px 16px; white-space: nowrap; }
.pnl-table th { text-align: right; font-weight: 700; color: #1e293b; border-bottom: 2px solid #1e293b; font-size: 13px; }
.pnl-table th:first-child, .pnl-table td:first-child { text-align: left; white-space: normal; min-width: 260px; }
.pnl-table td { text-align: right; font-variant-numeric: tabular-nums; color: #1e293b; }
.pnl-table td.concepto { color: #334155; }

.fila-seccion td.concepto { font-weight: 700; padding-top: 14px; }
.fila-seccion:first-child td.concepto { padding-top: 7px; }
.fila-seccion.suma td:not(.concepto) { color: #15803d; font-weight: 700; }
.fila-seccion.resta td:not(.concepto) { color: #b91c1c; font-weight: 700; }

.fila-linea td.concepto { color: #64748b; padding-left: 32px; font-size: 13.5px; }
.fila-linea td { font-size: 13.5px; }
.fila-linea.suma td:not(.concepto) { color: #16a34a; }
.fila-linea.resta td:not(.concepto) { color: #dc2626; }

.fila-vacio td { color: #94a3b8; font-style: italic; padding-left: 32px; font-size: 13px; }

.fila-total td { font-weight: 700; border-top: 1px solid #e2e8f0; padding-top: 8px; padding-bottom: 10px; }
.fila-total.suma td:not(.concepto) { color: #15803d; }
.fila-total.resta td:not(.concepto) { color: #b91c1c; }

.fila-subtotal td { font-weight: 700; border-top: 1px solid #94a3b8; padding-top: 9px; }
.fila-subtotal td:not(.concepto) { color: #1e293b; }

.fila-final td { font-weight: 800; font-size: 15.5px; border-top: 3px double #1e293b; padding-top: 10px; padding-bottom: 12px; }
.fila-final.positivo td:not(.concepto) { color: #15803d; }
.fila-final.negativo td:not(.concepto) { color: #b91c1c; }

.empty { text-align: center; padding: 48px; color: #94a3b8; font-size: 15px; }
</style>

<div class="main">

  <div class="top-bar">
    <div class="section-title"><?= icono('bar-chart-2') ?> Estado de Resultados (P&amp;L) <span class="wip-badge">WIP</span></div>
    <div class="rango-selector">
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
        <label class="chk"><input type="checkbox" id="soloExtremos"> Solo comparar estos dos (sin los intermedios)</label>
      </span>

      <label class="chk"><input type="checkbox" id="modoComparacion"> Comparación</label>
    </div>
  </div>

  <div class="wip-banner">Módulo en construcción — el costo de ventas se calcula como m² vendidos × precio promedio de compra por tipo/espesor de vidrio (no depende del wizard de corte). Si un tipo de vidrio nunca se ha comprado, esa pieza no se puede costear y baja la cobertura (ver aviso abajo si aplica).</div>

  <div class="aviso-cobertura" id="avisoCobertura"></div>

  <div class="pnl-wrap" id="pnlContent">
    <div class="empty">Cargando...</div>
  </div>

</div>

<script>
var ModContabilidadPnl = (function(){
var API = '../api/contabilidad_pnl.php';

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

// El período anterior al actual (ej. si hoy es agosto, en Mensual regresa julio) — es el que se
// muestra por default al entrar, ya que el período en curso normalmente está incompleto.
function periodoAnteriorAHoy(gran) {
  var n = totalIdx(gran);
  var anioActual = new Date().getFullYear();
  var idxHoy = idxDeHoy(gran);
  var k = anioActual * n + (idxHoy - 1) - 1;
  var anio = Math.floor(k / n);
  var idx = (k % n) + 1;
  return { anio: anio, idx: idx };
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
  var anterior = periodoAnteriorAHoy(gran);

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

  document.getElementById('individualAnio').value = anterior.anio;
  document.getElementById('individualPeriodo').value = anterior.idx;
}

function granularidadCambio() {
  var gran = document.getElementById('granularidad').value;
  var anterior = periodoAnteriorAHoy(gran);

  poblarPeriodo(document.getElementById('desdePeriodo'), gran);
  poblarPeriodo(document.getElementById('hastaPeriodo'), gran);
  poblarPeriodo(document.getElementById('individualPeriodo'), gran);
  aplicarVisibilidadPeriodo(gran);

  document.getElementById('desdePeriodo').value = 1;
  document.getElementById('hastaPeriodo').value = idxDeHoy(gran);
  document.getElementById('individualAnio').value = anterior.anio;
  document.getElementById('individualPeriodo').value = anterior.idx;

  cargar();
}

function pct(parte, base) {
  if (!base) return '—';
  return (parte / base * 100).toFixed(1) + '%';
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
  var signo = esResta ? 'resta' : 'suma';
  var html = '<tr class="fila-seccion ' + signo + '"><td class="concepto">' + esc(titulo) + '</td>';
  for (var i = 0; i < datas.length; i++) html += '<td></td>';
  html += '</tr>';

  var lineas = unirLineas(datas, seccion);
  if (!lineas.length) {
    html += '<tr class="fila-vacio"><td class="concepto">Sin movimientos</td>';
    for (var k = 0; k < datas.length; k++) html += '<td></td>';
    html += '</tr>';
  } else {
    for (var j = 0; j < lineas.length; j++) {
      html += '<tr class="fila-linea ' + signo + '"><td class="concepto">' + esc(lineas[j].codigo) + ' — ' + esc(lineas[j].nombre) + '</td>';
      for (var m = 0; m < datas.length; m++) {
        html += '<td>' + fmtMonto(montoDeLinea(datas[m], seccion, lineas[j].codigo), esResta) + '</td>';
      }
      html += '</tr>';
    }
  }

  html += '<tr class="fila-total ' + signo + '"><td class="concepto">Total ' + esc(titulo) + '</td>';
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
    return;
  }

  document.getElementById('pnlContent').innerHTML = '<div class="empty"><div class="spinner"></div>Cargando...</div>';

  try {
    var datas = await Promise.all(periodos.map(function(p) {
      return fetch(API + '?desde=' + p.desde + '&hasta=' + p.hasta).then(function(r) { return r.json(); });
    }));
    for (var i = 0; i < datas.length; i++) {
      if (datas[i].error) { document.getElementById('pnlContent').innerHTML = '<div class="empty" style="color:#dc2626">' + esc(datas[i].error) + '</div>'; return; }
    }
    render(periodos, datas);
  } catch (e) {
    document.getElementById('pnlContent').innerHTML = '<div class="empty" style="color:#dc2626">Error al cargar</div>';
  }
}

function render(periodos, datas) {
  // Avisos: se muestran solo si el ÚLTIMO período (el más reciente/relevante) los presenta.
  var d = datas[datas.length - 1];
  var cob = d.costo_ventas.cobertura;
  var avisosCriticos = [];
  var avisosLeves = [];
  if (cob && !cob.confiable) {
    avisosCriticos.push('Aviso: en "' + esc(periodos[periodos.length - 1].label) + '" solo ' + cob.pct_cobertura + '% de las piezas entregadas tienen costo real trazado (' + cob.piezas_con_costo + ' de ' + cob.piezas_total + '). El costo de ventas está subestimado y el margen mostrado no es confiable.');
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
    avisosLeves.push('Aviso: hay $' + fmt(totalSinMapear) + ' en pagos de Compras (' + Object.keys(nombres).join(', ') + ') sin cuenta contable asignada en el rango mostrado — no están incluidos en Gastos Operativos. Asígnalas en Contabilidad → Mapeo Compras.');
  }
  var avisoEl = document.getElementById('avisoCobertura');
  if (avisosCriticos.length) {
    avisoEl.className = 'aviso-cobertura';
    avisoEl.style.display = 'block';
    avisoEl.innerHTML = avisosCriticos.concat(avisosLeves).join('<br>');
  } else if (avisosLeves.length) {
    avisoEl.className = 'aviso-cobertura leve';
    avisoEl.style.display = 'block';
    avisoEl.innerHTML = avisosLeves.join('<br>');
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
  html += filaSubtotal('fila-subtotal ' + claseSigno(datas, 'utilidad_bruta'), 'Utilidad Bruta', datas, 'utilidad_bruta');

  html += filaSeccion('(-) Gastos Operativos', datas, 'gastos_operativos', true);
  html += filaSubtotal('fila-subtotal ' + claseSigno(datas, 'utilidad_operativa'), 'Utilidad Operativa', datas, 'utilidad_operativa');

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
