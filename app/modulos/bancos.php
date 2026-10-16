<?php
// ============================================================
//  APEX GLASS - Bancos (UPD-697) — Fase 1: registro idéntico al estado de cuenta
//  Archivo: app/modulos/bancos.php   API: api/bancos.php
// ============================================================
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
$puedeImportar = tienePermiso($user['rol'], 'gestionar_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=bancos'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
.bco { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 24px; max-width: 1250px; margin: 0 auto; color: #1e293b; }
.bco * { box-sizing: border-box; }
.bco-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
.bco-title { font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.bco-sub { font-size: 13px; color: #64748b; margin-top: 2px; }
.bco-btn { padding: 9px 16px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; }
.bco-btn:hover { opacity: .88; }
.bco-btn-pri { background: #2563eb; color: #fff; }
.bco-btn-ghost { background: #f1f5f9; color: #334155; }
.bco-btn-ok { background: #16a34a; color: #fff; }
.bco-btn:disabled { opacity: .5; cursor: default; }

.bco-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-bottom: 18px; }
.bco-card { background: #fff; border-radius: 12px; padding: 14px 16px; box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 0 0 1px rgba(15,23,42,.06); }
.bco-card-l { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
.bco-card-v { font-size: 22px; font-weight: 700; margin-top: 4px; font-variant-numeric: tabular-nums; }
.bco-card-s { font-size: 12px; color: #64748b; margin-top: 2px; }
.bco-ok { color: #16a34a; } .bco-bad { color: #dc2626; } .bco-warn { color: #b45309; }

.bco-filtros { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 12px; }
.bco-filtros input, .bco-filtros select { padding: 8px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; background: #fff; color: #1e293b; }
.bco-filtros .bco-q { min-width: 200px; flex: 1; }

.bco-tabla-wrap { background: #fff; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 0 0 1px rgba(15,23,42,.06); overflow-x: auto; margin-bottom: 18px; }
.bco table { width: 100%; border-collapse: collapse; }
.bco th { background: #f8fafc; padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .4px; white-space: nowrap; }
.bco td { padding: 8px 12px; border-top: 1px solid #f1f5f9; font-size: 13px; vertical-align: top; }
.bco td.num, .bco th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.bco .det { font-size: 12px; color: #64748b; }
.bco .tipo { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 99px; background: #f1f5f9; color: #475569; white-space: nowrap; }
.bco .abono { color: #15803d; } .bco .cargo { color: #b91c1c; }
.bco tr.bco-saldo-ini td { background: #f8fafc; font-weight: 600; color: #475569; }
.bco tr.bco-tot td { background: #f8fafc; font-weight: 700; border-top: 2px solid #e2e8f0; }
.bco .vacio { text-align: center; padding: 40px; color: #94a3b8; }

.bco details { background: #fff; border-radius: 12px; box-shadow: 0 0 0 1px rgba(15,23,42,.06); padding: 12px 16px; }
.bco summary { cursor: pointer; font-weight: 600; font-size: 14px; color: #334155; }

.bco-modal-bg { display: none; position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 1000; align-items: center; justify-content: center; padding: 16px; }
.bco-modal-bg.open { display: flex; }
.bco-modal { background: #fff; border-radius: 14px; padding: 24px; width: 100%; max-width: 860px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.2); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1e293b; }
.bco-modal h2 { font-size: 18px; font-weight: 700; margin: 0 0 6px; }
.bco-modal p { font-size: 13px; color: #475569; margin: 0 0 14px; line-height: 1.5; }
.bco-res { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin: 12px 0; }
.bco-res div { background: #f8fafc; border-radius: 8px; padding: 10px 12px; font-size: 12px; color: #64748b; }
.bco-res b { display: block; font-size: 16px; color: #1e293b; margin-top: 2px; font-variant-numeric: tabular-nums; }
.bco-aviso { background: #fef3c7; color: #92400e; font-size: 13px; padding: 10px 12px; border-radius: 8px; margin: 10px 0; }
.bco-error { background: #fee2e2; color: #991b1b; font-size: 13px; padding: 10px 12px; border-radius: 8px; margin: 10px 0; }
.bco-modal-foot { display: flex; gap: 10px; justify-content: flex-end; margin-top: 16px; }
.bco-modal table td, .bco-modal table th { padding: 6px 10px; font-size: 12px; }
.bco-modal th { background: #f8fafc; text-align: left; color: #64748b; font-size: 11px; text-transform: uppercase; }
.bco-modal td { border-top: 1px solid #f1f5f9; }
.bco-modal td.num, .bco-modal th.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.bco .cc { white-space: nowrap; font-size: 12px; }
.bco .cc-ok { color: #15803d; font-weight: 600; }
.bco .cc-par { color: #b45309; font-weight: 600; }
.bco .cc-btn { padding: 3px 10px; border-radius: 6px; border: 1.5px solid #cbd5e1; background: #fff; color: #334155; font-size: 12px; font-weight: 600; cursor: pointer; }
.bco .cc-btn:hover { border-color: #2563eb; color: #2563eb; }
.bco .cc-link { background: none; border: none; padding: 0; cursor: pointer; font: inherit; text-align: left; }
.bco .cc-link:hover { text-decoration: underline; }
.bco-modal .razon { display: inline-block; font-size: 11px; padding: 1px 6px; border-radius: 99px; background: #dcfce7; color: #166534; margin: 1px 3px 1px 0; }
.bco-modal .razon.mal { background: #fee2e2; color: #991b1b; }
.bco-modal tr.sel td { background: #eff6ff; }
.bco-modal .mini { font-size: 11px; color: #64748b; }
.bco-modal input[type=search], .bco-modal input[type=number], .bco-modal input[type=text], .bco-modal select { padding: 7px 9px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; }
.bco-sum { display: flex; gap: 14px; flex-wrap: wrap; font-size: 13px; margin: 8px 0; }
.bco-sum b { font-variant-numeric: tabular-nums; }
@media (max-width: 640px) { .bco { padding: 14px; } .bco-card-v { font-size: 18px; } }
</style>

<div class="bco">
  <div class="bco-top">
    <div>
      <div class="bco-title"><?= icono('credit-card', 20) ?> Bancos</div>
      <div class="bco-sub" id="bcoCuentaTxt">Cargando cuenta…</div>
    </div>
    <?php if ($puedeImportar): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="bco-btn bco-btn-ghost" onclick="ModBancos._sinBanco()">Apex sin banco</button>
      <button class="bco-btn bco-btn-ghost" onclick="ModBancos._auto()">Conciliar automático</button>
      <button class="bco-btn bco-btn-pri" onclick="ModBancos._abrirImportar()"><?= icono('upload', 14) ?> Importar PDF de BBVA</button>
    </div>
    <?php else: ?>
    <button class="bco-btn bco-btn-ghost" onclick="ModBancos._sinBanco()">Apex sin banco</button>
    <?php endif; ?>
  </div>

  <div class="bco-cards">
    <div class="bco-card"><div class="bco-card-l">Saldo registrado</div><div class="bco-card-v" id="kSaldo">—</div><div class="bco-card-s" id="kSaldoS"></div></div>
    <div class="bco-card"><div class="bco-card-l">Cuadre de saldos</div><div class="bco-card-v" id="kCadena">—</div><div class="bco-card-s" id="kCadenaS"></div></div>
    <div class="bco-card"><div class="bco-card-l">Abonos del rango</div><div class="bco-card-v bco-ok" id="kAbonos">—</div><div class="bco-card-s" id="kAbonosS"></div></div>
    <div class="bco-card"><div class="bco-card-l">Cargos del rango</div><div class="bco-card-v bco-bad" id="kCargos">—</div><div class="bco-card-s" id="kCargosS"></div></div>
    <div class="bco-card"><div class="bco-card-l">Abonos conciliados</div><div class="bco-card-v" id="kConc">—</div><div class="bco-card-s" id="kConcS"></div></div>
  </div>

  <div class="bco-filtros">
    <input type="date" id="bcoDesde" aria-label="Desde">
    <span style="color:#94a3b8">a</span>
    <input type="date" id="bcoHasta" aria-label="Hasta">
    <select id="bcoTipo" aria-label="Tipo de movimiento">
      <option value="">Todos los movimientos</option>
      <option value="abonos">Solo abonos (entradas)</option>
      <option value="cargos">Solo cargos (salidas)</option>
      <option value="spei_recibido">Transferencias recibidas</option>
      <option value="pago_tercero">Pagos BBVA a BBVA</option>
      <option value="deposito_efectivo">Depósitos en efectivo</option>
      <option value="deposito_cheque">Depósitos de cheque</option>
      <option value="terminal_venta">Terminal: ventas</option>
      <option value="terminal_comision">Terminal: comisiones</option>
      <option value="spei_enviado">Transferencias enviadas</option>
      <option value="spei_devuelto">Transferencias devueltas</option>
      <option value="pago_servicio">Pago de servicios (CFE, Telmex, SAT…)</option>
      <option value="comision_banco">Comisiones del banco</option>
      <option value="otro">Otros</option>
    </select>
    <select id="bcoConc" aria-label="Conciliación">
      <option value="">Conciliación: todos</option>
      <option value="pendientes">Abonos sin conciliar</option>
      <option value="conciliados">Abonos conciliados</option>
    </select>
    <input type="search" id="bcoQ" class="bco-q" placeholder="Buscar concepto, referencia o monto…" aria-label="Buscar">
    <button class="bco-btn bco-btn-ghost" id="bcoOrdenBtn" onclick="ModBancos._toggleOrden()">Más recientes primero</button>
  </div>

  <div class="bco-tabla-wrap">
    <table>
      <thead><tr><th>Fecha</th><th>Concepto / Referencia</th><th>Tipo</th><th class="num">Cargo</th><th class="num">Abono</th><th class="num">Saldo</th><th>Ligado a</th></tr></thead>
      <tbody id="bcoTabla"><tr><td colspan="7" class="vacio">Cargando…</td></tr></tbody>
    </table>
  </div>

  <details id="bcoImpDet">
    <summary>Historial de PDFs importados</summary>
    <div style="overflow-x:auto;margin-top:10px">
      <table>
        <thead><tr><th>Importado</th><th>Archivo</th><th>Impreso en BBVA</th><th>Rango del PDF</th><th class="num">Nuevos</th><th class="num">Ya registrados</th><th class="num">Saldo final</th><th>Usuario</th></tr></thead>
        <tbody id="bcoImpTabla"><tr><td colspan="8" class="vacio">—</td></tr></tbody>
      </table>
    </div>
  </details>
</div>

<div class="bco-modal-bg" id="bcoModalBg">
  <div class="bco-modal" role="dialog" aria-modal="true" aria-labelledby="bcoModalTit">
    <h2 id="bcoModalTit">Importar movimientos de BBVA</h2>
    <div id="bcoPaso1">
      <p>En BBVA abre <b>Detalle de movimientos</b> (Mes actual), da clic en <b>Imprimir</b> y guárdalo como PDF. Puedes subirlo las veces que quieras: lo que ya está registrado no se duplica.<br>
      El primer día de cada mes sube también el <b>Mes anterior</b> completo, para que entren los movimientos del último día.</p>
      <label for="bcoArchivo" style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase">Archivo PDF</label>
      <input type="file" id="bcoArchivo" accept="application/pdf,.pdf" style="display:block;margin-top:6px;font-size:13px">
      <div id="bcoErr1"></div>
      <div class="bco-modal-foot">
        <button class="bco-btn bco-btn-ghost" onclick="ModBancos._cerrarImportar()">Cancelar</button>
        <button class="bco-btn bco-btn-pri" id="bcoBtnRevisar" onclick="ModBancos._revisar()">Revisar PDF</button>
      </div>
    </div>
    <div id="bcoPaso2" style="display:none">
      <div id="bcoResumen"></div>
      <div id="bcoNuevas"></div>
      <div id="bcoErr2"></div>
      <div class="bco-modal-foot">
        <button class="bco-btn bco-btn-ghost" onclick="ModBancos._cerrarImportar()">Cancelar</button>
        <button class="bco-btn bco-btn-ok" id="bcoBtnConfirmar" onclick="ModBancos._confirmar()">Registrar</button>
      </div>
    </div>
  </div>
</div>

<div class="bco-modal-bg" id="bcoM2Bg">
  <div class="bco-modal" role="dialog" aria-modal="true" aria-labelledby="bcoM2Tit">
    <h2 id="bcoM2Tit"></h2>
    <div id="bcoM2"></div>
  </div>
</div>

<script>
window._bcoPuedeImportar = <?= $puedeImportar ? 'true' : 'false' ?>;
var ModBancos = (function() {
var API = '../api/bancos.php';
var cuenta = null;
var ordenDesc = true;
var ultimoData = null;
var tokenImp = null;
var timerQ = null;

var TIPOS = {
  spei_recibido: 'Transf. recibida', spei_enviado: 'Transf. enviada', spei_devuelto: 'Transf. devuelta',
  pago_tercero: 'BBVA a BBVA', deposito_efectivo: 'Efectivo', deposito_cheque: 'Cheque',
  terminal_venta: 'Terminal venta', terminal_comision: 'Terminal comisión',
  comision_banco: 'Comisión banco', pago_servicio: 'Servicio', otro: 'Otro'
};
var MESES = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];

function esc(s) {
  var d = document.createElement('div');
  d.textContent = (s === null || s === undefined) ? '' : String(s);
  return d.innerHTML;
}
function fmt(n) {
  n = parseFloat(n || 0);
  return '$' + n.toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
function fFecha(s) {
  if (!s) return '—';
  var p = String(s).substr(0, 10).split('-');
  return p[2] + '-' + MESES[parseInt(p[1], 10) - 1] + '-' + p[0];
}
function fFechaHora(s) {
  if (!s) return '—';
  return fFecha(s) + ' ' + String(s).substr(11, 5);
}
function hoy() {
  var d = new Date();
  return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
}
function inicioMes() { return hoy().substr(0, 8) + '01'; }
function el(id) { return document.getElementById(id); }

async function cargarCuentas() {
  try {
    var res = await fetch(API + '?accion=cuentas');
    var data = await res.json();
    if (!data.cuentas || !data.cuentas.length) { el('bcoCuentaTxt').textContent = 'No hay cuentas dadas de alta'; return; }
    cuenta = data.cuentas[0];
    el('bcoCuentaTxt').textContent = cuenta.banco + ' · Cuenta ' + cuenta.numero + ' · registrada desde el ' + fFecha(cuenta.fecha_inicial) + ' (saldo inicial ' + fmt(cuenta.saldo_inicial) + ')';
    el('kSaldo').textContent = fmt(cuenta.saldo_actual);
    el('kSaldoS').textContent = cuenta.ultima_fecha ? 'Al último movimiento: ' + fFecha(cuenta.ultima_fecha) : 'Sin movimientos todavía';
    if (cuenta.cadena.ok) {
      el('kCadena').innerHTML = '<span class="bco-ok">Cuadra</span>';
      el('kCadenaS').textContent = cuenta.cadena.movimientos + ' movimientos revisados al centavo';
    } else {
      el('kCadena').innerHTML = '<span class="bco-bad">No cuadra</span>';
      el('kCadenaS').textContent = 'Falla el ' + fFecha(cuenta.cadena.fecha) + ': esperado ' + fmt(cuenta.cadena.esperado) + ', registrado ' + fmt(cuenta.cadena.registrado);
    }
    var ui = cuenta.ultima_importacion;
    if (ui) el('kSaldoS').textContent += ' · PDF impreso ' + fFechaHora(ui.fecha_impresion);
  } catch (e) {
    el('bcoCuentaTxt').textContent = 'Error al cargar la cuenta';
  }
}

async function cargarMovimientos() {
  if (!cuenta) return;
  var qs = 'accion=movimientos&cuenta_id=' + cuenta.id +
           '&desde=' + encodeURIComponent(el('bcoDesde').value || inicioMes()) +
           '&hasta=' + encodeURIComponent(el('bcoHasta').value || hoy()) +
           '&tipo=' + encodeURIComponent(el('bcoTipo').value) +
           '&conc=' + encodeURIComponent(el('bcoConc').value) +
           '&q=' + encodeURIComponent(el('bcoQ').value.trim());
  try {
    var res = await fetch(API + '?' + qs);
    ultimoData = await res.json();
    if (ultimoData.error) { el('bcoTabla').innerHTML = '<tr><td colspan="7" class="vacio">' + esc(ultimoData.error) + '</td></tr>'; return; }
    render();
  } catch (e) {
    el('bcoTabla').innerHTML = '<tr><td colspan="7" class="vacio bco-bad">Error al cargar</td></tr>';
  }
}

function render() {
  var d = ultimoData;
  var filas = d.filas.slice();
  if (!ordenDesc) filas.reverse();
  var nA = 0, nC = 0;
  for (var i = 0; i < d.filas.length; i++) { if (parseFloat(d.filas[i].abono) > 0) nA++; else nC++; }
  el('kAbonos').textContent = fmt(d.total_abonos);
  el('kAbonosS').textContent = nA + ' movimientos';
  el('kCargos').textContent = fmt(d.total_cargos);
  el('kCargosS').textContent = nC + ' movimientos';
  var cc = d.conciliacion || {abonos: 0, conciliados: 0, total: 0, ligado: 0};
  el('kConc').innerHTML = '<span class="' + (cc.abonos && cc.conciliados === cc.abonos ? 'bco-ok' : 'bco-warn') + '">' + cc.conciliados + ' de ' + cc.abonos + '</span>';
  el('kConcS').textContent = fmt(cc.ligado) + ' de ' + fmt(cc.total);

  var filtrado = el('bcoTipo').value !== '' || el('bcoQ').value.trim() !== '' || el('bcoConc').value !== '';
  var filaIni = '<tr class="bco-saldo-ini"><td>' + fFecha(d.desde) + '</td><td colspan="4">Saldo al iniciar el ' + fFecha(d.desde) + '</td><td class="num">' + fmt(d.saldo_inicial) + '</td><td></td></tr>';
  var html = '';
  if (!ordenDesc) html += filaIni;
  if (!filas.length) html += '<tr><td colspan="7" class="vacio">Sin movimientos en este rango</td></tr>';
  for (var j = 0; j < filas.length; j++) {
    var f = filas[j];
    var c = parseFloat(f.cargo), a = parseFloat(f.abono);
    html += '<tr>' +
      '<td style="white-space:nowrap">' + fFecha(f.fecha) + '</td>' +
      '<td>' + esc(f.concepto) + (f.detalle ? '<div class="det">' + esc(f.detalle) + '</div>' : '') + '</td>' +
      '<td><span class="tipo">' + esc(TIPOS[f.tipo] || f.tipo) + '</span></td>' +
      '<td class="num cargo">' + (c > 0 ? fmt(c) : '') + '</td>' +
      '<td class="num abono">' + (a > 0 ? fmt(a) : '') + '</td>' +
      '<td class="num">' + fmt(f.saldo) + '</td>' +
      '<td class="cc">' + celdaConc(f) + '</td></tr>';
  }
  if (ordenDesc) html += filaIni;
  html += '<tr class="bco-tot"><td colspan="3">' + (filtrado ? 'Total de lo filtrado' : 'Totales del rango') +
          '</td><td class="num cargo">' + fmt(d.total_cargos) + '</td><td class="num abono">' + fmt(d.total_abonos) +
          '</td><td class="num">' + (filtrado ? '' : fmt(d.saldo_final)) + '</td><td></td></tr>';
  el('bcoTabla').innerHTML = html;
}

// ─── Conciliación (UPD-702) ───────────────────────────────────────────────────
var movSel = null;      // movimiento abierto en el modal de ligar
var selItems = {};      // 'origen:id' -> monto
var autoPares = [];
var timerSug = null;

function celdaConc(f) {
  var a = parseFloat(f.abono), l = parseFloat(f.ligado || 0);
  if (!(a > 0)) return '';
  var abrir = 'ModBancos._ligar(' + parseInt(f.id, 10) + ')';
  if (l >= a - 0.005) return '<button class="cc-link cc-ok" onclick="' + abrir + '">' + esc(f.ligado_a || 'Conciliado') + '</button>';
  if (l > 0) return '<button class="cc-link cc-par" onclick="' + abrir + '">Parcial · faltan ' + fmt(a - l) + '</button>';
  return window._bcoPuedeImportar ? '<button class="cc-btn" onclick="' + abrir + '">Ligar</button>' : '<span class="det">Pendiente</span>';
}

function abrirM2(titulo, html) {
  el('bcoM2Tit').textContent = titulo;
  el('bcoM2').innerHTML = html;
  el('bcoM2Bg').classList.add('open');
}
function cerrarM2() { el('bcoM2Bg').classList.remove('open'); movSel = null; }
function descReg(r) {
  var partes = [];
  if (r.orden_folio) partes.push('<b>' + esc(r.orden_folio) + '</b>');
  if (r.cot_folio) partes.push(esc(r.cot_folio));
  if (r.origen === 'saldo_favor') partes.push('<span class="mini">Saldo a favor</span>');
  var cli = (r.cliente_codigo ? esc(r.cliente_codigo) + ' ' : '') + esc(r.cliente || '');
  return partes.join(' · ') + (cli ? '<div class="mini">' + cli + '</div>' : '') + (r.notas || r.r_notas ? '<div class="mini">' + esc(r.notas || r.r_notas) + '</div>' : '');
}

async function ligar(movId, q) {
  try {
    var res = await fetch(API + '?accion=conciliacion&movimiento_id=' + movId + '&q=' + encodeURIComponent(q || ''));
    var d = await res.json();
    if (d.error) { toast(d.error); return; }
    if (!movSel || movSel.movimiento.id !== d.movimiento.id) selItems = {};
    movSel = d;
    pintarLigar(q || '');
  } catch (e) { toast('Error de conexión'); }
}

function pintarLigar(q) {
  var d = movSel, m = d.movimiento, puede = window._bcoPuedeImportar;
  var h = '<p>' + fFecha(m.fecha) + ' · ' + esc(m.concepto) + (m.detalle ? ' · <b>' + esc(m.detalle) + '</b>' : '') + '</p>' +
    '<div class="bco-sum"><span>Abono: <b class="bco-ok">' + fmt(m.abono) + '</b></span><span>Ligado: <b>' + fmt(d.ligado) + '</b></span>' +
    (d.restante < -0.005
      ? '<span>Diferencia: <b class="bco-warn">' + fmt(-d.restante) + '</b> (Apex arriba del banco)</span></div>'
      : '<span>Falta: <b class="' + (d.restante > 0.005 ? 'bco-warn' : 'bco-ok') + '">' + fmt(d.restante) + '</b></span></div>');
  if (d.ligas.length) {
    h += '<table style="margin-bottom:12px"><thead><tr><th>Ligado a</th><th>Fecha</th><th>Forma</th><th class="num">Monto</th><th></th></tr></thead><tbody>';
    for (var i = 0; i < d.ligas.length; i++) {
      var g = d.ligas[i];
      var desc = g.origen === 'otro' ? '<b>' + esc(d.categorias[g.categoria] || g.categoria) + '</b><div class="mini">' + esc(g.nota) + '</div>' : descReg(g);
      h += '<tr><td>' + desc + '<div class="mini">' + (parseInt(g.automatico, 10) ? 'Automático · ' : '') + esc(g.usuario) + ' · ' + fFechaHora(g.created_at) + '</div></td>' +
        '<td style="white-space:nowrap">' + (g.r_fecha ? fFecha(g.r_fecha) : '') + '</td><td>' + esc(g.forma || '') + '</td>' +
        '<td class="num">' + fmt(g.monto) + '</td>' +
        '<td>' + (puede ? '<button class="cc-btn" onclick="ModBancos._desligar(' + parseInt(g.id, 10) + ')">Quitar</button>' : '') + '</td></tr>';
    }
    h += '</tbody></table>';
  }
  if (puede && d.restante > 0.005) {
    h += '<div style="display:flex;gap:8px;align-items:center;margin:6px 0 8px"><input type="search" id="bcoSugQ" placeholder="Buscar por folio, cotización, cliente o monto…" style="flex:1" value="' + esc(q) + '">' +
      '<span class="mini">' + (q ? 'Resultados de la búsqueda' : 'Sugerencias: pagos y depósitos sin ligar cercanos a la fecha') + '</span></div>';
    if (!d.sugerencias.length) {
      h += '<div class="bco-aviso" style="background:#f1f5f9;color:#334155">No hay registros de Apex sin ligar que coincidan.</div>';
    } else {
      h += '<div style="max-height:300px;overflow-y:auto;border:1px solid #f1f5f9;border-radius:8px"><table><thead><tr><th></th><th>Registro de Apex</th><th>Fecha</th><th>Forma</th><th class="num">Monto</th><th>Por qué</th></tr></thead><tbody>';
      for (var j = 0; j < d.sugerencias.length; j++) {
        var r = d.sugerencias[j], k = r.origen + ':' + r.origen_id;
        var raz = '';
        for (var z = 0; z < r.razones.length; z++) raz += '<span class="razon' + (r.razones[z].indexOf('Forma distinta') === 0 ? ' mal' : '') + '">' + esc(r.razones[z]) + '</span>';
        h += '<tr class="' + (selItems[k] !== undefined ? 'sel' : '') + '"><td><input type="checkbox" data-k="' + esc(k) + '" data-m="' + esc(r.monto) + '"' + (selItems[k] !== undefined ? ' checked' : '') + ' aria-label="Elegir"></td>' +
          '<td>' + descReg(r) + '</td><td style="white-space:nowrap">' + fFecha(r.fecha) + '</td><td>' + esc(r.forma || '') + (r.tarjeta_tipo ? ' ' + esc(r.tarjeta_tipo) : '') + '</td>' +
          '<td class="num">' + fmt(r.monto) + '</td><td>' + raz + '</td></tr>';
      }
      h += '</tbody></table></div>';
    }
    h += '<details style="margin-top:10px;box-shadow:none;padding:0"><summary style="font-size:13px">No está en Apex (traspaso, préstamo, devolución…)</summary>' +
      '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;align-items:center">' +
      '<select id="bcoOtroCat" aria-label="Categoría"><option value="">Categoría…</option>';
    for (var c in d.categorias) { if (Object.prototype.hasOwnProperty.call(d.categorias, c)) h += '<option value="' + esc(c) + '">' + esc(d.categorias[c]) + '</option>'; }
    h += '</select><input type="number" id="bcoOtroMonto" step="0.01" min="0" placeholder="Monto" value="' + d.restante.toFixed(2) + '" style="width:120px" aria-label="Monto">' +
      '<input type="text" id="bcoOtroNota" maxlength="300" placeholder="Nota: de dónde viene" style="flex:1;min-width:180px" aria-label="Nota"></div></details>';
  }
  h += '<div id="bcoLigErr"></div><div class="bco-modal-foot"><span id="bcoSelSum" class="mini" style="margin-right:auto;align-self:center"></span>' +
    '<button class="bco-btn bco-btn-ghost" onclick="ModBancos._cerrarM2()">Cerrar</button>' +
    (puede && d.restante > 0.005 ? '<button class="bco-btn bco-btn-ok" id="bcoBtnLigar" onclick="ModBancos._guardarLiga()">Ligar</button>' : '') + '</div>';
  abrirM2('Ligar abono del banco', h);
  var qi = el('bcoSugQ');
  if (qi) {
    qi.addEventListener('input', function() {
      var v = this.value;
      clearTimeout(timerSug);
      timerSug = setTimeout(function() { ligar(movSel.movimiento.id, v.trim()).then(function() { var x = el('bcoSugQ'); if (x) { x.focus(); x.setSelectionRange(x.value.length, x.value.length); } }); }, 400);
    });
  }
  var checks = el('bcoM2').querySelectorAll('input[type=checkbox][data-k]');
  for (var w = 0; w < checks.length; w++) {
    checks[w].addEventListener('change', function() {
      if (this.checked) selItems[this.getAttribute('data-k')] = parseFloat(this.getAttribute('data-m'));
      else delete selItems[this.getAttribute('data-k')];
      this.closest('tr').className = this.checked ? 'sel' : '';
      pintarSuma();
    });
  }
  pintarSuma();
}

function pintarSuma() {
  var s = el('bcoSelSum');
  if (!s || !movSel) return;
  var t = 0, n = 0;
  for (var k in selItems) { if (Object.prototype.hasOwnProperty.call(selItems, k)) { t += selItems[k]; n++; } }
  if (!n) { s.textContent = ''; return; }
  var dif = Math.round((movSel.restante - t) * 100) / 100;
  s.innerHTML = n + ' elegido(s): <b>' + fmt(t) + '</b> · ' + (Math.abs(dif) < 0.005 ? '<span class="bco-ok">cuadra exacto</span>' : (dif > 0 ? 'faltan ' + fmt(dif) : (-dif < 1 ? '<span class="bco-warn">se pasa por ' + fmt(-dif) + ' (centavos, se acepta)</span>' : '<span class="bco-bad">se pasa por ' + fmt(-dif) + '</span>')));
}

async function guardarLiga() {
  var items = [];
  for (var k in selItems) {
    if (Object.prototype.hasOwnProperty.call(selItems, k)) {
      var p = k.split(':');
      items.push({ origen: p[0], origen_id: parseInt(p[1], 10) });
    }
  }
  var cat = el('bcoOtroCat') ? el('bcoOtroCat').value : '';
  if (cat) items.push({ origen: 'otro', categoria: cat, monto: parseFloat(el('bcoOtroMonto').value || 0), nota: el('bcoOtroNota').value });
  if (!items.length) { el('bcoLigErr').innerHTML = '<div class="bco-error">Elige al menos un registro, o llena "No está en Apex"</div>'; return; }
  var btn = el('bcoBtnLigar');
  btn.disabled = true;
  try {
    var res = await fetch(API + '?accion=ligar', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ movimiento_id: movSel.movimiento.id, items: items }) });
    var d = await res.json();
    if (!d.ok) { el('bcoLigErr').innerHTML = '<div class="bco-error">' + esc(d.error || 'No se pudo ligar') + '</div>'; btn.disabled = false; return; }
    selItems = {};
    toast('Ligado');
    var id = movSel.movimiento.id;
    await ligar(id, '');
    cargarMovimientos();
  } catch (e) {
    el('bcoLigErr').innerHTML = '<div class="bco-error">Error de conexión</div>';
    btn.disabled = false;
  }
}

async function desligar(id) {
  if (!(await confirmar('¿Quitar esta liga? El movimiento del banco y el registro de Apex no se borran, solo se deshace la relación.'))) return;
  try {
    var res = await fetch(API + '?accion=desligar', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ id: id }) });
    var d = await res.json();
    if (!d.ok) { toast(d.error || 'No se pudo quitar'); return; }
    await ligar(movSel.movimiento.id, '');
    cargarMovimientos();
  } catch (e) { toast('Error de conexión'); }
}

function rango() {
  return '&desde=' + encodeURIComponent(el('bcoDesde').value || inicioMes()) + '&hasta=' + encodeURIComponent(el('bcoHasta').value || hoy());
}

async function auto() {
  if (!cuenta) return;
  abrirM2('Conciliar automático', '<p>Buscando coincidencias…</p>');
  try {
    var res = await fetch(API + '?accion=auto_propuesta&cuenta_id=' + cuenta.id + rango());
    var d = await res.json();
    autoPares = d.pares || [];
    var h = '<p>Abonos del ' + fFecha(el('bcoDesde').value) + ' al ' + fFecha(el('bcoHasta').value) + ' que tienen <b>un solo</b> pago o depósito en Apex con el mismo monto exacto, la misma forma de pago y fecha cercana (hasta 3 días antes). Revisa y quita la palomita a lo que no te convenza.</p>';
    if (!autoPares.length) {
      h += '<div class="bco-aviso" style="background:#f1f5f9;color:#334155">No hay coincidencias seguras pendientes en este rango.</div>' +
        '<div class="bco-modal-foot"><button class="bco-btn bco-btn-ghost" onclick="ModBancos._cerrarM2()">Cerrar</button></div>';
      abrirM2('Conciliar automático', h);
      return;
    }
    h += '<div style="max-height:420px;overflow-y:auto;border:1px solid #f1f5f9;border-radius:8px"><table><thead><tr><th><input type="checkbox" id="bcoAutoTodos" checked aria-label="Todos"></th><th>Banco</th><th class="num">Abono</th><th>Apex</th><th>Fecha Apex</th></tr></thead><tbody>';
    for (var i = 0; i < autoPares.length; i++) {
      var p = autoPares[i];
      h += '<tr><td><input type="checkbox" class="bcoAutoChk" data-i="' + i + '" checked aria-label="Elegir"></td>' +
        '<td style="max-width:280px"><span style="white-space:nowrap">' + fFecha(p.fecha) + '</span> · ' + esc(TIPOS[p.tipo] || p.tipo) + (p.detalle ? '<div class="mini">' + esc(p.detalle) + '</div>' : '') + '</td>' +
        '<td class="num">' + fmt(p.abono) + '</td><td>' + descReg(p) + '</td><td style="white-space:nowrap">' + fFecha(p.r_fecha) + ' · ' + esc(p.forma || '') + '</td></tr>';
    }
    h += '</tbody></table></div><div id="bcoAutoErr"></div><div class="bco-modal-foot"><button class="bco-btn bco-btn-ghost" onclick="ModBancos._cerrarM2()">Cancelar</button>' +
      '<button class="bco-btn bco-btn-ok" id="bcoBtnAuto" onclick="ModBancos._autoConfirmar()">Ligar ' + autoPares.length + '</button></div>';
    abrirM2('Conciliar automático', h);
    var chks = el('bcoM2').querySelectorAll('.bcoAutoChk');
    var contar = function() {
      var n = el('bcoM2').querySelectorAll('.bcoAutoChk:checked').length;
      el('bcoBtnAuto').textContent = 'Ligar ' + n;
      el('bcoBtnAuto').disabled = n === 0;
    };
    for (var j = 0; j < chks.length; j++) chks[j].addEventListener('change', contar);
    el('bcoAutoTodos').addEventListener('change', function() {
      for (var k = 0; k < chks.length; k++) chks[k].checked = this.checked;
      contar();
    });
  } catch (e) {
    abrirM2('Conciliar automático', '<div class="bco-error">Error de conexión</div>');
  }
}

async function autoConfirmar() {
  var chks = el('bcoM2').querySelectorAll('.bcoAutoChk:checked');
  var pares = [];
  for (var i = 0; i < chks.length; i++) {
    var p = autoPares[parseInt(chks[i].getAttribute('data-i'), 10)];
    pares.push({ movimiento_id: p.movimiento_id, origen: p.origen, origen_id: p.origen_id });
  }
  if (!pares.length) return;
  var btn = el('bcoBtnAuto');
  btn.disabled = true;
  btn.textContent = 'Ligando…';
  try {
    var res = await fetch(API + '?accion=auto_confirmar', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ pares: pares }) });
    var d = await res.json();
    if (!d.ok) { el('bcoAutoErr').innerHTML = '<div class="bco-error">' + esc(d.error || 'No se pudo ligar') + '</div>'; btn.disabled = false; btn.textContent = 'Reintentar'; return; }
    cerrarM2();
    toast(d.ligados + ' abonos ligados' + (d.omitidos ? ' · ' + d.omitidos + ' omitidos (cambiaron mientras tanto)' : ''));
    cargarMovimientos();
  } catch (e) {
    el('bcoAutoErr').innerHTML = '<div class="bco-error">Error de conexión</div>';
    btn.disabled = false;
  }
}

async function sinBanco() {
  if (!cuenta) return;
  abrirM2('Cobrado en Apex y sin ligar al banco', '<p>Cargando…</p>');
  try {
    var res = await fetch(API + '?accion=apex_sin_banco&cuenta_id=' + cuenta.id + rango());
    var d = await res.json();
    var regs = d.registros || [];
    var h = '<p>Pagos y depósitos a saldo a favor registrados en Apex del ' + fFecha(d.desde) + ' al ' + fFecha(d.hasta) + ' que todavía no están ligados a ningún abono del banco. ' +
      'El efectivo suele aparecer días después, cuando se deposita; lo demás debería estar en el banco.</p>';
    if (!regs.length) {
      h += '<div class="bco-aviso" style="background:#dcfce7;color:#166534">Todo lo cobrado en Apex en este rango ya está ligado al banco.</div>';
    } else {
      var grupos = {}, orden = [];
      for (var i = 0; i < regs.length; i++) {
        var f = regs[i].forma || 'sin forma';
        if (!grupos[f]) { grupos[f] = []; orden.push(f); }
        grupos[f].push(regs[i]);
      }
      for (var g = 0; g < orden.length; g++) {
        var lista = grupos[orden[g]], tot = 0;
        for (var t = 0; t < lista.length; t++) tot += parseFloat(lista[t].monto);
        h += '<details' + (g === 0 ? ' open' : '') + ' style="margin-bottom:8px;box-shadow:0 0 0 1px rgba(15,23,42,.08)"><summary>' + esc(orden[g].charAt(0).toUpperCase() + orden[g].slice(1)) + ' · ' + lista.length + ' · ' + fmt(tot) + '</summary>' +
          '<table style="margin-top:8px"><thead><tr><th>Fecha</th><th>Registro de Apex</th><th class="num">Monto</th><th>Capturó</th></tr></thead><tbody>';
        for (var r = 0; r < lista.length; r++) {
          var x = lista[r];
          h += '<tr><td style="white-space:nowrap">' + fFecha(x.fecha) + '</td><td>' + descReg(x) + '</td><td class="num">' + fmt(x.monto) + '</td><td class="mini">' + esc(x.registrado_por || '') + '</td></tr>';
        }
        h += '</tbody></table></details>';
      }
    }
    h += '<div class="bco-modal-foot"><button class="bco-btn bco-btn-ghost" onclick="ModBancos._cerrarM2()">Cerrar</button></div>';
    abrirM2('Cobrado en Apex y sin ligar al banco', h);
  } catch (e) {
    abrirM2('Cobrado en Apex y sin ligar al banco', '<div class="bco-error">Error de conexión</div>');
  }
}

async function cargarImportaciones() {
  if (!cuenta) return;
  try {
    var res = await fetch(API + '?accion=importaciones&cuenta_id=' + cuenta.id);
    var data = await res.json();
    var rows = data.importaciones || [];
    if (!rows.length) { el('bcoImpTabla').innerHTML = '<tr><td colspan="8" class="vacio">Todavía no se ha importado ningún PDF</td></tr>'; return; }
    var html = '';
    for (var i = 0; i < rows.length; i++) {
      var r = rows[i];
      html += '<tr><td style="white-space:nowrap">' + fFechaHora(r.created_at) + '</td><td>' + esc(r.archivo_nombre) + '</td>' +
        '<td style="white-space:nowrap">' + fFechaHora(r.fecha_impresion) + '</td>' +
        '<td style="white-space:nowrap">' + fFecha(r.primera_fecha) + ' a ' + fFecha(r.ultima_fecha) + '</td>' +
        '<td class="num">' + r.filas_nuevas + '</td><td class="num">' + r.filas_existentes + '</td>' +
        '<td class="num">' + (r.saldo_final !== null ? fmt(r.saldo_final) : '—') + '</td><td>' + esc(r.usuario) + '</td></tr>';
    }
    el('bcoImpTabla').innerHTML = html;
  } catch (e) {}
}

function abrirImportar() {
  tokenImp = null;
  el('bcoArchivo').value = '';
  el('bcoErr1').innerHTML = '';
  el('bcoErr2').innerHTML = '';
  el('bcoPaso1').style.display = '';
  el('bcoPaso2').style.display = 'none';
  el('bcoBtnRevisar').disabled = false;
  el('bcoModalBg').classList.add('open');
}
function cerrarImportar() { el('bcoModalBg').classList.remove('open'); }

async function revisar() {
  var archivo = el('bcoArchivo').files[0];
  if (!archivo) { el('bcoErr1').innerHTML = '<div class="bco-error">Elige el PDF primero</div>'; return; }
  var fd = new FormData();
  fd.append('cuenta_id', cuenta.id);
  fd.append('pdf', archivo);
  el('bcoBtnRevisar').disabled = true;
  el('bcoBtnRevisar').textContent = 'Revisando…';
  el('bcoErr1').innerHTML = '';
  try {
    var res = await fetch(API + '?accion=previsualizar', { method: 'POST', body: fd });
    var d = await res.json();
    if (!d.ok) { el('bcoErr1').innerHTML = '<div class="bco-error">' + esc(d.error || 'No se pudo leer el PDF') + '</div>'; return; }
    tokenImp = d.token;
    pintarResumen(d);
    el('bcoPaso1').style.display = 'none';
    el('bcoPaso2').style.display = '';
  } catch (e) {
    el('bcoErr1').innerHTML = '<div class="bco-error">Error de conexión</div>';
  } finally {
    el('bcoBtnRevisar').disabled = false;
    el('bcoBtnRevisar').textContent = 'Revisar PDF';
  }
}

function pintarResumen(d) {
  var n = d.nuevas.length;
  var html = '<p>PDF de la cuenta <b>' + esc(d.cuenta) + '</b>, impreso en BBVA el <b>' + fFechaHora(d.impreso) + '</b>, del ' + fFecha(d.primera_fecha) + ' al ' + fFecha(d.ultima_fecha) + '.</p>' +
    '<div class="bco-res">' +
      '<div>Movimientos en el PDF<b>' + d.filas_archivo + '</b></div>' +
      '<div>Ya registrados<b>' + d.existentes + '</b></div>' +
      '<div>Nuevos por registrar<b class="' + (n ? 'bco-ok' : '') + '">' + n + '</b></div>' +
      (d.previas ? '<div>Antes del arranque (se ignoran)<b>' + d.previas + '</b></div>' : '') +
      (d.saldo_final !== null ? '<div>Saldo final<b>' + fmt(d.saldo_final) + '</b></div>' : '') +
    '</div>';
  if (n) html += '<div class="bco-aviso" style="background:#dcfce7;color:#166534">El saldo cuadra al centavo con lo ya registrado.</div>';
  if (d.saldo_disponible !== null && d.saldo_final !== null && Math.abs(d.saldo_disponible - d.saldo_final) >= 0.005) {
    html += '<div class="bco-aviso">BBVA muestra un saldo disponible de ' + fmt(d.saldo_disponible) + ', distinto del saldo del último movimiento (' + fmt(d.saldo_final) + '). Suele ser un movimiento en proceso; no impide registrar.</div>';
  }
  if (d.impreso_hoy) {
    html += '<div class="bco-aviso">Este PDF se imprimió hoy a las ' + esc(String(d.impreso).substr(11, 5)) + '. Los movimientos que entren más tarde se registran con el siguiente PDF.</div>';
  }
  el('bcoResumen').innerHTML = html;

  if (n) {
    var t = '<div style="max-height:320px;overflow-y:auto;border:1px solid #f1f5f9;border-radius:8px"><table><thead><tr><th>Fecha</th><th>Concepto</th><th class="num">Cargo</th><th class="num">Abono</th><th class="num">Saldo</th></tr></thead><tbody>';
    for (var i = n - 1; i >= 0; i--) {
      var f = d.nuevas[i];
      t += '<tr><td style="white-space:nowrap">' + fFecha(f.fecha) + '</td><td>' + esc(f.concepto) + (f.detalle ? '<div class="det" style="font-size:11px;color:#64748b">' + esc(f.detalle) + '</div>' : '') + '</td>' +
        '<td class="num" style="color:#b91c1c">' + (f.cargo > 0 ? fmt(f.cargo) : '') + '</td>' +
        '<td class="num" style="color:#15803d">' + (f.abono > 0 ? fmt(f.abono) : '') + '</td>' +
        '<td class="num">' + fmt(f.saldo) + '</td></tr>';
    }
    t += '</tbody></table></div>';
    el('bcoNuevas').innerHTML = t;
    el('bcoBtnConfirmar').style.display = '';
    el('bcoBtnConfirmar').textContent = 'Registrar ' + n + ' movimiento' + (n === 1 ? '' : 's');
  } else {
    el('bcoNuevas').innerHTML = '<div class="bco-aviso" style="background:#f1f5f9;color:#334155">No hay movimientos nuevos en este PDF: todo ya estaba registrado.</div>';
    el('bcoBtnConfirmar').style.display = 'none';
  }
}

async function confirmarImp() {
  if (!tokenImp) return;
  var btn = el('bcoBtnConfirmar');
  btn.disabled = true;
  btn.textContent = 'Registrando…';
  try {
    var res = await fetch(API + '?accion=confirmar', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ token: tokenImp }) });
    var d = await res.json();
    if (!d.ok) { el('bcoErr2').innerHTML = '<div class="bco-error">' + esc(d.error || 'No se pudo registrar') + '</div>'; return; }
    tokenImp = null;
    cerrarImportar();
    toast(d.nuevas + ' movimientos registrados. Saldo: ' + fmt(d.saldo_final));
    await cargarCuentas();
    cargarMovimientos();
    cargarImportaciones();
  } catch (e) {
    el('bcoErr2').innerHTML = '<div class="bco-error">Error de conexión</div>';
  } finally {
    btn.disabled = false;
  }
}

function toggleOrden() {
  ordenDesc = !ordenDesc;
  el('bcoOrdenBtn').textContent = ordenDesc ? 'Más recientes primero' : 'Más antiguos primero';
  if (ultimoData) render();
}

async function init() {
  el('bcoDesde').value = inicioMes();
  el('bcoHasta').value = hoy();
  el('bcoDesde').addEventListener('change', cargarMovimientos);
  el('bcoHasta').addEventListener('change', cargarMovimientos);
  el('bcoTipo').addEventListener('change', cargarMovimientos);
  el('bcoConc').addEventListener('change', cargarMovimientos);
  el('bcoM2Bg').addEventListener('click', function(e) { if (e.target === this) cerrarM2(); });
  el('bcoQ').addEventListener('input', function() {
    clearTimeout(timerQ);
    timerQ = setTimeout(cargarMovimientos, 350);
  });
  el('bcoModalBg').addEventListener('click', function(e) { if (e.target === this) cerrarImportar(); });
  await cargarCuentas();
  cargarMovimientos();
  cargarImportaciones();
}

init();

return {
  init: init,
  _abrirImportar: abrirImportar,
  _cerrarImportar: cerrarImportar,
  _revisar: revisar,
  _confirmar: confirmarImp,
  _toggleOrden: toggleOrden,
  _ligar: function(id) { selItems = {}; movSel = null; ligar(id, ''); },
  _desligar: desligar,
  _guardarLiga: guardarLiga,
  _cerrarM2: cerrarM2,
  _auto: auto,
  _autoConfirmar: autoConfirmar,
  _sinBanco: sinBanco
};
})();
</script>
