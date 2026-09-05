<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requirePermiso('ver_contabilidad');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=bono_corte'); exit;
}
header('Content-Type: text/html; charset=utf-8');
$puedeAprobar = tienePermiso($user['rol'], 'gestionar_contabilidad');
?>
<style>
.bc-wrap { padding: 24px; max-width: 1000px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
.bc-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
.bc-sub { font-size: 12.5px; color: #64748b; margin-bottom: 20px; }

.bc-nav { display:flex; align-items:center; gap:10px; margin-bottom:20px; background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:10px 14px; width:fit-content; }
.bc-nav button { width:28px; height:28px; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; color:#64748b; cursor:pointer; font-size:14px; }
.bc-nav button:hover { background:#eef2f7; color:#0f172a; }
.bc-nav span { font-size:13px; font-weight:700; color:#0f172a; min-width:170px; text-align:center; }

.bc-op-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; margin-bottom:16px; overflow:hidden; }
.bc-op-head { display:flex; align-items:center; gap:14px; padding:16px 18px; flex-wrap:wrap; }
.bc-avatar { width:36px; height:36px; border-radius:50%; background:#2563eb; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:13px; flex-shrink:0; }
.bc-op-name { font-weight:700; font-size:14px; color:#0f172a; }
.bc-op-metrics { display:flex; gap:20px; margin-left:auto; flex-wrap:wrap; }
.bc-op-metrics .m { text-align:right; }
.bc-op-metrics .m .v { font-size:15px; font-weight:700; color:#0f172a; }
.bc-op-metrics .m .l { font-size:10px; color:#64748b; text-transform:uppercase; letter-spacing:.04em; }
.bc-op-metrics .m.money .v { color:#0f766e; }

.bc-pill { font-size:11px; font-weight:700; padding:4px 10px; border-radius:99px; white-space:nowrap; }
.bc-pill.calculado { background:#fffbeb; color:#b45309; border:1px solid #fde68a; }
.bc-pill.pagado { background:#f0fdf4; color:#15803d; border:1px solid #bbf7d0; }

.bc-btn { font-size:12.5px; font-weight:600; border-radius:8px; padding:8px 14px; cursor:pointer; border:1px solid #2563eb; background:#2563eb; color:#fff; }
.bc-btn:hover { filter:brightness(1.08); }
.bc-btn:disabled { opacity:.55; cursor:default; filter:none; }
.bc-btn-recibo { border-color:#0f766e; background:#fff; color:#0f766e; margin-left:8px; }
.bc-btn-recibo:hover { background:#f0fdfa; }

.bc-excl-toggle { width:100%; text-align:left; display:flex; align-items:center; gap:8px; padding:10px 18px; background:#f8fafc; border:none; border-top:1px solid #e2e8f0; cursor:pointer; font-size:12px; color:#64748b; font-weight:600; }
.bc-excl-toggle .n { background:#fffbeb; color:#b45309; border:1px solid #fde68a; font-size:10.5px; font-weight:700; padding:1px 7px; border-radius:99px; }
.bc-excl-panel { display:none; border-top:1px solid #e2e8f0; }
.bc-excl-panel.open { display:block; }
.bc-excl-panel table { width:100%; border-collapse:collapse; font-size:12.5px; }
.bc-excl-panel th { text-align:left; font-size:10px; text-transform:uppercase; color:var(--c-muted); padding:8px 18px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
.bc-excl-panel td { padding:8px 18px; border-bottom:1px solid #e2e8f0; color:#64748b; }

.bc-det-toggle { width:100%; text-align:left; display:flex; align-items:center; gap:8px; padding:10px 18px; background:#f0fdf4; border:none; border-top:1px solid #e2e8f0; cursor:pointer; font-size:12px; color:#166534; font-weight:600; }
.bc-det-toggle .n { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; font-size:10.5px; font-weight:700; padding:1px 7px; border-radius:99px; }
.bc-det-panel { display:none; border-top:1px solid #e2e8f0; }
.bc-det-panel.open { display:block; }
.bc-det-panel table { width:100%; border-collapse:collapse; font-size:12.5px; }
.bc-det-panel th { text-align:left; font-size:10px; text-transform:uppercase; color:var(--c-muted); padding:8px 18px; border-bottom:1px solid #e2e8f0; background:#f8fafc; }
.bc-det-panel td { padding:8px 18px; border-bottom:1px solid #e2e8f0; color:#374151; }
.bc-det-panel .folio { font-weight:700; color:#2563eb; }
.bc-det-panel .sin-piezas { color:var(--c-muted); font-style:italic; }

.bc-empty { text-align:center; padding:60px 20px; color:var(--c-muted); }
</style>

<div class="bc-wrap">
  <div class="bc-title">Bono de Corte — Pedacería</div>
  <div class="bc-sub">Calcula el bono semanal por m&sup2; de pedacer&iacute;a aprovechados. Excluye autom&aacute;ticamente sobrantes declarados grandes (tope silencioso, no visible para el operador).</div>

  <div class="bc-nav">
    <button onclick="BonoCorte.prevSemana()">&lsaquo;</button>
    <span id="bc-semana-label">&mdash;</span>
    <button onclick="BonoCorte.nextSemana()">&rsaquo;</button>
  </div>

  <div id="bc-lista"><div class="bc-empty">Cargando...</div></div>
</div>

<script>
var BonoCorte = (function() {
  var API = '../api/bono_pedaceria.php';
  var PUEDE_APROBAR = <?= $puedeAprobar ? 'true' : 'false' ?>;
  var _semana = null; // Y-m-d de cualquier día dentro de la semana a consultar

  function lunesDe(fecha) {
    var d = new Date(fecha + 'T00:00:00');
    var dow = d.getDay() === 0 ? 7 : d.getDay();
    d.setDate(d.getDate() - (dow - 1));
    return d;
  }

  function fmtFecha(d) {
    var meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    return d.getDate() + ' ' + meses[d.getMonth()];
  }

  function hoyStr() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
  }

  function setSemanaLabel() {
    var lunes = lunesDe(_semana);
    var domingo = new Date(lunes); domingo.setDate(domingo.getDate() + 6);
    document.getElementById('bc-semana-label').textContent = fmtFecha(lunes) + ' – ' + fmtFecha(domingo);
  }

  function prevSemana() {
    var d = lunesDe(_semana);
    d.setDate(d.getDate() - 7);
    _semana = d.toISOString().slice(0,10);
    cargar();
  }

  function nextSemana() {
    var d = lunesDe(_semana);
    d.setDate(d.getDate() + 7);
    _semana = d.toISOString().slice(0,10);
    cargar();
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function fmtMonto(n) {
    return '$' + Number(n).toFixed(2);
  }

  async function cargar() {
    setSemanaLabel();
    var cont = document.getElementById('bc-lista');
    cont.innerHTML = '<div class="bc-empty">Cargando...</div>';
    try {
      var r = await fetch(API + '?accion=resumen_semana&semana=' + encodeURIComponent(_semana));
      var d = await r.json();
      if (!d.ok) { cont.innerHTML = '<div class="bc-empty">Error al cargar</div>'; return; }
      render(d);
    } catch (e) {
      cont.innerHTML = '<div class="bc-empty">Error de conexión</div>';
    }
  }

  function render(d) {
    var cont = document.getElementById('bc-lista');
    if (!d.operadores || !d.operadores.length) {
      cont.innerHTML = '<div class="bc-empty">Sin actividad de pedacería esta semana</div>';
      return;
    }
    var html = '';
    d.operadores.forEach(function(op) {
      var iniciales = (op.operador_nombre || '?').trim().charAt(0).toUpperCase();
      var pillCls = op.estado === 'pagado' ? 'pagado' : 'calculado';
      var pillTxt = op.estado === 'pagado' ? 'Pagado' : 'Calculado';
      var btnHtml = '';
      if (PUEDE_APROBAR) {
        var disabled = op.estado === 'pagado' ? 'disabled' : '';
        var btnTxt = op.estado === 'pagado' ? 'Pagado ✓' : 'Marcar como pagado';
        btnHtml = '<button class="bc-btn" ' + disabled + ' onclick="BonoCorte.marcarPagado(' + op.operador_id + ',this)">' + btnTxt + '</button>';
      }
      if (op.estado === 'pagado' && op.pago_id) {
        btnHtml += '<button class="bc-btn bc-btn-recibo" onclick="window.open(\'../imprimir_bono_corte.php?id=' + op.pago_id + '\', \'_blank\')">Imprimir recibo</button>';
      }
      var exclHtml = '';
      if (op.excluidas && op.excluidas.length) {
        var rows = '';
        op.excluidas.forEach(function(e) {
          var fecha = new Date(e.created_at.replace(' ', 'T'));
          rows += '<tr><td>' + fecha.toLocaleString('es-MX', {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}) + '</td>'
            + '<td>#' + e.id + '</td>'
            + '<td>' + esc(e.tipo) + ' ' + e.espesor_mm + 'mm</td>'
            + '<td>' + Number(e.m2_disponible).toFixed(2) + ' m²</td>'
            + '<td>Excede tope</td></tr>';
        });
        exclHtml = '<button class="bc-excl-toggle" onclick="BonoCorte.toggleExcl(' + op.operador_id + ')">'
          + '<span>' + op.excluidas.length + ' sesión(es) excluida(s)</span> <span class="n">no contaron</span></button>'
          + '<div class="bc-excl-panel" id="bc-excl-' + op.operador_id + '"><table><thead><tr><th>Fecha</th><th>Sesión</th><th>Vidrio</th><th>m² declarados</th><th>Motivo</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
      }
      var detHtml = '';
      if (op.sesiones_detalle && op.sesiones_detalle.length) {
        var detRows = '';
        op.sesiones_detalle.forEach(function(s) {
          var fecha = new Date(s.created_at.replace(' ', 'T'));
          var fechaTxt = fecha.toLocaleString('es-MX', {day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'});
          var tamano = Number(s.m2_disponible).toFixed(2) + ' m²';
          var vidrio = esc(s.tipo) + ' ' + s.espesor_mm + 'mm';
          if (!s.piezas || !s.piezas.length) {
            detRows += '<tr><td>' + fechaTxt + '</td><td>' + vidrio + '</td><td>' + tamano + '</td>'
              + '<td colspan="3" class="sin-piezas">Sin piezas asociadas</td></tr>';
          } else {
            s.piezas.forEach(function(p, idx) {
              var medida = p.ancho_mm + '×' + p.alto_mm + 'mm';
              detRows += '<tr>'
                + '<td>' + (idx === 0 ? fechaTxt : '') + '</td>'
                + '<td>' + (idx === 0 ? vidrio : '') + '</td>'
                + '<td>' + (idx === 0 ? tamano : '') + '</td>'
                + '<td class="folio">' + esc(p.folio) + '</td>'
                + '<td>P' + p.partida + ' · ' + p.pieza_num + '/' + p.pieza_total + '</td>'
                + '<td>' + medida + '</td>'
                + '</tr>';
            });
          }
        });
        detHtml = '<button class="bc-det-toggle" onclick="BonoCorte.toggleDet(' + op.operador_id + ')">'
          + '<span>Detalle de ' + op.sesiones_detalle.length + ' sesión(es) elegible(s)</span> <span class="n">orden · partida · pieza</span></button>'
          + '<div class="bc-det-panel" id="bc-det-' + op.operador_id + '"><table><thead><tr><th>Fecha</th><th>Vidrio</th><th>Tamaño sobrante</th><th>Orden</th><th>Partida/Pieza</th><th>Medida</th></tr></thead><tbody>' + detRows + '</tbody></table></div>';
      }
      html += '<div class="bc-op-card">'
        + '<div class="bc-op-head">'
        +   '<div class="bc-avatar">' + esc(iniciales) + '</div>'
        +   '<div class="bc-op-name">' + esc(op.operador_nombre) + '</div>'
        +   '<div class="bc-op-metrics">'
        +     '<div class="m"><div class="v">' + Number(op.m2_elegible).toFixed(1) + ' m²</div><div class="l">Elegibles</div></div>'
        +     '<div class="m"><div class="v">' + op.sesiones + '</div><div class="l">Sesiones</div></div>'
        +     '<div class="m money"><div class="v">' + fmtMonto(op.monto) + '</div><div class="l">Monto</div></div>'
        +   '</div>'
        +   '<span class="bc-pill ' + pillCls + '">' + pillTxt + '</span>'
        +   btnHtml
        + '</div>'
        + detHtml
        + exclHtml
        + '</div>';
    });
    cont.innerHTML = html;
  }

  function toggleExcl(operadorId) {
    var el = document.getElementById('bc-excl-' + operadorId);
    if (el) el.classList.toggle('open');
  }

  function toggleDet(operadorId) {
    var el = document.getElementById('bc-det-' + operadorId);
    if (el) el.classList.toggle('open');
  }

  async function marcarPagado(operadorId, btnEl) {
    if (!confirm('¿Marcar el bono de esta semana como pagado?')) return;
    btnEl.disabled = true;
    try {
      var r = await fetch(API, {
        method: 'POST', headers: {'Content-Type':'application/json'},
        body: JSON.stringify({accion:'marcar_pagado', operador_id: operadorId, semana_inicio: _semana})
      });
      var d = await r.json();
      if (d.ok) {
        cargar();
      } else {
        alert(d.error || 'No se pudo marcar como pagado');
        btnEl.disabled = false;
      }
    } catch (e) {
      alert('Error de conexión');
      btnEl.disabled = false;
    }
  }

  _semana = hoyStr();
  cargar();

  return {
    prevSemana: prevSemana,
    nextSemana: nextSemana,
    toggleExcl: toggleExcl,
    toggleDet: toggleDet,
    marcarPagado: marcarPagado
  };
})();
</script>
