<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
requirePermiso('ver_ordenes');
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=retrabajo'); exit;
}
?>
<style>
.ret-wrap      { padding: 24px; }
.page-title    { font-size: 18px; font-weight: 600; color: #1a1a1a; margin-bottom: 4px; }
.page-sub      { font-size: 12px; color: #9ca3af; margin-bottom: 16px; }
.ret-toolbar   { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; align-items: center; }
.ret-search    { flex: 1; min-width: 200px; padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; outline: none; }
.ret-search:focus { border-color: #2563eb; }
.ret-table     { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
table          { width: 100%; border-collapse: collapse; }
thead tr       { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
thead th       { padding: 10px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; }
tbody tr       { border-bottom: 1px solid #f1f5f9; transition: background .1s; cursor: pointer; }
tbody tr:hover { background: #eff6ff; }
tbody tr:last-child { border-bottom: none; }
tbody td       { padding: 12px 14px; font-size: 13px; vertical-align: middle; }
.ret-folio     { font-weight: 700; color: #1e293b; font-size: 14px; }
.ret-cliente   { font-size: 12px; color: #64748b; margin-top: 2px; }
.badge         { display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 99px; white-space: nowrap; }
.badge-activa  { background: #dcfce7; color: #15803d; }
.badge-entregada { background: #dbeafe; color: #1d4ed8; }
.badge-ret     { background: #fef3c7; color: #92400e; }
.ret-razones   { font-size: 11px; color: #94a3b8; margin-top: 3px; max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.loading-msg   { text-align: center; padding: 48px; color: #9ca3af; font-size: 14px; cursor: default; }
</style>

<div class="ret-wrap">
  <div class="page-title">Retrabajo</div>
  <div class="page-sub" id="ret-sub">Cargando&#8230;</div>
  <div class="ret-toolbar">
    <input type="text" class="ret-search" id="ret-q" placeholder="&#128269; Buscar folio o cliente&#8230;" oninput="retFiltrar()">
  </div>
  <div class="ret-table">
    <table>
      <thead><tr>
        <th>Folio</th>
        <th>Estado</th>
        <th>Piezas retrabajo</th>
        <th>Fecha entrega</th>
        <th>Asesor</th>
      </tr></thead>
      <tbody id="ret-tbody"><tr><td colspan="5" class="loading-msg">Cargando&#8230;</td></tr></tbody>
    </table>
  </div>
</div>

<script>
window.ModRetrabajo = (function(){

let _data = [];

async function cargar() {
  try {
    const r = await fetch('../api/retrabajo.php?t=' + Date.now());
    const d = await r.json();
    _data = d.data || [];
    retFiltrar();
    const activas = _data.filter(o => o.estado === 'activa').length;
    document.getElementById('ret-sub').textContent =
      _data.length + ' orden(es) con retrabajo — ' + activas + ' activa(s)';
  } catch(e) {
    document.getElementById('ret-sub').textContent = 'Error al cargar';
  }
}

window.retFiltrar = function() {
  const q = (document.getElementById('ret-q')?.value || '').toLowerCase();
  const lista = q ? _data.filter(o =>
    (o.folio || '').toLowerCase().includes(q) ||
    (o.cliente_nombre || '').toLowerCase().includes(q) ||
    (o.asesor || '').toLowerCase().includes(q)
  ) : _data;

  if (!lista.length) {
    document.getElementById('ret-tbody').innerHTML =
      '<tr><td colspan="5" class="loading-msg">Sin resultados</td></tr>';
    return;
  }

  document.getElementById('ret-tbody').innerHTML = lista.map(o => {
    const badgeEst = o.estado === 'activa'
      ? '<span class="badge badge-activa">Activa</span>'
      : '<span class="badge badge-entregada">Entregada</span>';

    const fechaEnt = o.fecha_entrega
      ? new Date(o.fecha_entrega + 'T12:00:00').toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric'})
      : '—';

    // Color fecha si está vencida y activa
    let fechaColor = '#64748b';
    if (o.estado === 'activa' && o.fecha_entrega) {
      const dias = Math.ceil((new Date(o.fecha_entrega) - new Date()) / 86400000);
      if (dias < 0)      fechaColor = '#dc2626';
      else if (dias <= 2) fechaColor = '#d97706';
      else                fechaColor = '#16a34a';
    }

    return `<tr onclick="window.irA('orden', {folio: '${o.folio}'})">
      <td>
        <div class="ret-folio">${o.folio}</div>
        <div class="ret-cliente">${o.cliente_nombre || '—'}</div>
        ${o.razones ? `<div class="ret-razones" title="${o.razones}">${o.razones}</div>` : ''}
      </td>
      <td>${badgeEst}</td>
      <td>
        <span class="badge badge-ret">&#9888; ${o.piezas_retrabajo} pieza(s)</span>
        <div style="font-size:11px;color:#94a3b8;margin-top:3px">de ${o.total_piezas} total</div>
      </td>
      <td><span style="color:${fechaColor};font-weight:600;font-size:13px">${fechaEnt}</span></td>
      <td style="color:#64748b;font-size:12px">${o.asesor || '—'}</td>
    </tr>`;
  }).join('');
};

cargar();
return { init: cargar };
})();
ModRetrabajo.init();
</script>