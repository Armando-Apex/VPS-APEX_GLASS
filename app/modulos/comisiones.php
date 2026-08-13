<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=comisiones'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<style>
.cm-wrap { padding: 24px; max-width: 1000px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.cm-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
.cm-sub { font-size: 12.5px; color: #64748b; margin-bottom: 20px; }

.cm-nav { display:flex; align-items:center; gap:10px; margin-bottom:20px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px; width:fit-content; }
.cm-nav button { width:28px; height:28px; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; color:#64748b; cursor:pointer; font-size:14px; }
.cm-nav button:hover { background:#eef2f7; color:#0f172a; }
.cm-nav span { font-size:13px; font-weight:700; color:#0f172a; min-width:120px; text-align:center; text-transform:capitalize; }

.cm-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:16px; overflow:hidden; }
.cm-head { display:flex; align-items:center; gap:14px; padding:16px 18px; flex-wrap:wrap; }
.cm-avatar { width:36px; height:36px; border-radius:50%; background:#0f766e; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0; }
.cm-nombre { font-weight:700; font-size:14px; color:#0f172a; }
.cm-metrics { display:flex; gap:20px; margin-left:auto; flex-wrap:wrap; }
.cm-metrics .m { text-align:right; }
.cm-metrics .m .v { font-size:15px; font-weight:700; color:#0f172a; }
.cm-metrics .m .l { font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
.cm-metrics .m.neta .v { font-size:17px; color:#0f766e; }
.cm-metrics .m.penal .v { color:#b91c1c; }

.cm-pill { font-size:11px; font-weight:700; padding:4px 10px; border-radius:99px; white-space:nowrap; }
.cm-pill.cero { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }

.cm-det-toggle { width:100%; text-align:left; display:flex; align-items:center; gap:8px; padding:10px 18px; background:#fef2f2; border:none; border-top:1px solid #e2e8f0; cursor:pointer; font-size:12px; color:#991b1b; font-weight:600; }
.cm-det-toggle .n { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; font-size:10.5px; font-weight:700; padding:1px 7px; border-radius:99px; }
.cm-det-panel { display:none; border-top:1px solid #e2e8f0; }
.cm-det-panel.open { display:block; }
.cm-det-panel table { width:100%; border-collapse:collapse; font-size:12.5px; }
.cm-det-panel th { text-align:left; font-size:10px; text-transform:uppercase; color:var(--c-muted); padding:8px 18px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
.cm-det-panel td { padding:8px 18px; border-bottom:1px solid #e2e8f0; color:#374151; }
.cm-det-panel .folio { font-weight:700; color:#2563eb; }
.cm-badge-ok { color:#15803d; font-weight:600; }
.cm-badge-no { color:#b91c1c; font-weight:600; }

.cm-empty { text-align:center; padding:60px 20px; color:var(--c-muted); }
</style>

<div class="cm-wrap">
  <div class="cm-title">Comisiones de Asesores</div>
  <div class="cm-sub">Ventas del mes por fecha de VoBo. &Oacute;rdenes de retrabajo (error comercial) no cuentan como venta y penalizan la comisi&oacute;n, salvo que el cliente haya pagado al menos el 50% de la pieza.</div>

  <div class="cm-nav">
    <button onclick="Comisiones.prevMes()">&lsaquo;</button>
    <span id="cm-mes-label">&mdash;</span>
    <button onclick="Comisiones.nextMes()">&rsaquo;</button>
  </div>

  <div id="cm-lista"><div class="cm-empty">Cargando...</div></div>
</div>

<script>
var Comisiones = (function() {
  var API = '../api/comisiones.php';
  var _mes = null; // 'YYYY-MM'

  var MESES = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];

  function mesActualStr() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
  }

  function setMesLabel() {
    var partes = _mes.split('-');
    var anio = partes[0], mesIdx = parseInt(partes[1], 10) - 1;
    document.getElementById('cm-mes-label').textContent = MESES[mesIdx] + ' ' + anio;
  }

  function sumarMes(delta) {
    var partes = _mes.split('-');
    var anio = parseInt(partes[0], 10), mesIdx = parseInt(partes[1], 10) - 1;
    var d = new Date(anio, mesIdx + delta, 1);
    _mes = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    cargar();
  }

  function prevMes() { sumarMes(-1); }
  function nextMes() { sumarMes(1); }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function fmtMonto(n) {
    return '$' + Number(n).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }

  function fmtPct(t) {
    return (Number(t) * 100).toFixed(1) + '%';
  }

  async function cargar() {
    setMesLabel();
    var cont = document.getElementById('cm-lista');
    cont.innerHTML = '<div class="cm-empty">Cargando...</div>';
    try {
      var r = await fetch(API + '?accion=resumen_mes&mes=' + encodeURIComponent(_mes));
      var d = await r.json();
      if (!d.ok) { cont.innerHTML = '<div class="cm-empty">Error al cargar</div>'; return; }
      render(d);
    } catch (e) {
      cont.innerHTML = '<div class="cm-empty">Error de conexi&oacute;n</div>';
    }
  }

  function render(d) {
    var cont = document.getElementById('cm-lista');
    if (!d.asesores || !d.asesores.length) {
      cont.innerHTML = '<div class="cm-empty">Sin datos este mes</div>';
      return;
    }
    var html = '';
    d.asesores.forEach(function(a) {
      var iniciales = (a.asesor_label || '?').trim().charAt(0).toUpperCase();
      var pillHtml = '';
      if (a.motivo_cero === 'minimo_no_alcanzado') {
        pillHtml = '<span class="cm-pill cero">No alcanz&oacute; m&iacute;nimo ($450,000)</span>';
      }
      var detHtml = '';
      if (a.penalizaciones_detalle && a.penalizaciones_detalle.length) {
        var rows = '';
        a.penalizaciones_detalle.forEach(function(p) {
          var estadoHtml = p.perdonada
            ? '<span class="cm-badge-ok">Perdonada (cliente pag&oacute; &ge;50%)</span>'
            : '<span class="cm-badge-no">Aplica -' + fmtMonto(p.monto) + '</span>';
          rows += '<tr>'
            + '<td class="folio">' + esc(p.orden_folio || p.folio) + '</td>'
            + '<td>' + esc(p.cliente) + '</td>'
            + '<td>' + fmtMonto(p.subtotal) + '</td>'
            + '<td>' + fmtMonto(p.pagado) + ' / ' + fmtMonto(p.total) + '</td>'
            + '<td>' + estadoHtml + '</td>'
            + '</tr>';
        });
        detHtml = '<button class="cm-det-toggle" onclick="Comisiones.toggleDet(\'' + a.asesor_key + '\')">'
          + '<span>' + a.penalizaciones_detalle.length + ' orden(es) de retrabajo este mes</span> <span class="n">-' + fmtMonto(a.penalizaciones) + ' total</span></button>'
          + '<div class="cm-det-panel" id="cm-det-' + a.asesor_key + '"><table><thead><tr><th>Orden</th><th>Cliente</th><th>Valor pieza</th><th>Pagado / Total</th><th>Resultado</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
      }
      html += '<div class="cm-card">'
        + '<div class="cm-head">'
        +   '<div class="cm-avatar">' + esc(iniciales) + '</div>'
        +   '<div class="cm-nombre">' + esc(a.asesor_label) + '</div>'
        +   pillHtml
        +   '<div class="cm-metrics">'
        +     '<div class="m"><div class="v">' + fmtMonto(a.ventas) + '</div><div class="l">Ventas del mes</div></div>'
        +     '<div class="m"><div class="v">' + fmtPct(a.tasa) + '</div><div class="l">Tasa</div></div>'
        +     '<div class="m"><div class="v">' + fmtMonto(a.comision_bruta) + '</div><div class="l">Comisi&oacute;n bruta</div></div>'
        +     '<div class="m penal"><div class="v">-' + fmtMonto(a.penalizaciones) + '</div><div class="l">Penalizaciones</div></div>'
        +     '<div class="m neta"><div class="v">' + fmtMonto(a.comision_neta) + '</div><div class="l">Comisi&oacute;n neta</div></div>'
        +   '</div>'
        + '</div>'
        + detHtml
        + '</div>';
    });
    cont.innerHTML = html;
  }

  function toggleDet(asesorKey) {
    var el = document.getElementById('cm-det-' + asesorKey);
    if (el) el.classList.toggle('open');
  }

  _mes = mesActualStr();
  cargar();

  return {
    prevMes: prevMes,
    nextMes: nextMes,
    toggleDet: toggleDet
  };
})();
</script>
