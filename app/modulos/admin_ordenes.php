<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requirePermiso('cambiar_cualquier_estatus');
if (!in_array($user['rol'], ['dir_admin', 'desarrollo'])) { echo '<div style="padding:40px;color:#dc2626">Acceso denegado</div>'; exit; }
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=admin_ordenes'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<meta charset="UTF-8">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
.container{padding:24px;max-width:1100px;}
.aviso{background:#fef9c3;border-left:4px solid #ca8a04;border-radius:8px;padding:12px 16px;font-size:13px;color:#713f12;display:flex;gap:10px;margin-bottom:16px;}
.filtros{display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap;}
.filtros input{flex:1;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;outline:none;}
.filtros input:focus{border-color:#2563eb;}
.tabla-wrap{background:white;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);}
table{width:100%;border-collapse:collapse;}
thead th{padding:10px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#64748b;text-align:left;border-bottom:1px solid #f1f5f9;}
tbody tr{border-bottom:1px solid #f8fafc;}
tbody tr:hover{background:#f8fafc;}
tbody td{padding:10px 14px;font-size:13px;}
.td-folio{font-weight:700;color:#2563eb;cursor:pointer;}
.td-folio:hover{text-decoration:underline;}
.estado-badge{font-size:11px;font-weight:600;padding:3px 10px;border-radius:99px;white-space:nowrap;}
.badge-activa{background:#dcfce7;color:#15803d;}
.badge-cancelada{background:#fee2e2;color:#b91c1c;}
.btn-cancelar{background:#fee2e2;color:#b91c1c;border:none;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;}
.btn-restaurar{background:#dbeafe;color:#1d4ed8;border:none;padding:6px 12px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:center;justify-content:center;}
.modal-overlay.activo{display:flex;}
.modal{background:white;border-radius:14px;padding:28px;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.25);}
.modal h3{font-size:17px;font-weight:700;margin-bottom:10px;}
.modal p{font-size:13px;color:#64748b;margin-bottom:6px;line-height:1.6;}
.modal .orden-info{background:#f8fafc;border-radius:8px;padding:12px 14px;margin:14px 0;font-size:13px;}
.modal .acciones{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
.modal .btn-ok{background:#dc2626;color:white;border:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;}
.modal .btn-cancel{background:#f1f5f9;color:#374151;border:none;padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;}
.empty{padding:32px;text-align:center;color:#94a3b8;font-size:13px;}
.toast{position:fixed;bottom:24px;right:24px;background:#1e293b;color:white;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 4px 20px rgba(0,0,0,.3);z-index:200;transform:translateY(80px);opacity:0;transition:all .3s;}
.toast.show{transform:translateY(0);opacity:1;}
.toast.error{background:#dc2626;}

@media(max-width:768px){
  .container { padding: 12px; }

  /* Tabs inline → extraer a clase para poder controlar */
  #tab-btn-cancelar, #tab-btn-estatus {
    padding: 10px 14px !important;
    font-size: 12px !important;
    flex: 1;
    text-align: center;
  }

  /* Filtros */
  .filtros { flex-direction: column; }
  .filtros input { width: 100%; }

  /* Tabla cancelar/restaurar: ocultar Asesor, Fecha, Piezas */
  .tabla-wrap thead th:nth-child(3),
  .tabla-wrap thead th:nth-child(4),
  .tabla-wrap thead th:nth-child(5),
  .tabla-wrap tbody td:nth-child(3),
  .tabla-wrap tbody td:nth-child(4),
  .tabla-wrap tbody td:nth-child(5) { display: none; }

  thead th { padding: 8px 10px; font-size: 10px; }
  tbody td  { padding: 9px 10px; font-size: 12px; }
  .btn-cancelar, .btn-restaurar { font-size: 11px; padding: 5px 8px; }

  /* Panel corrección estatus: grid 2 cols → 1 col */
  #estatus-form > div[style*="grid-template-columns"] {
    grid-template-columns: 1fr !important;
  }
  #estatus-form > div[style*="justify-content:flex-end"] {
    flex-direction: column !important;
  }
  #estatus-form > div[style*="justify-content:flex-end"] button {
    width: 100% !important;
    text-align: center !important;
  }

  /* Modal */
  .modal { padding: 20px 16px; }
  .modal .acciones { flex-direction: column; }
  .modal .btn-ok, .modal .btn-cancel { width: 100%; text-align: center; }

  /* Toast */
  .toast { right: 12px; left: 12px; bottom: 16px; text-align: center; }
}
</style>

<div class="container">

  <!-- Tabs -->
  <div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid #e2e8f0;">
    <button id="tab-btn-cancelar" onclick="adminTab('cancelar')"
      style="padding:10px 20px;font-size:13px;font-weight:600;border:none;background:none;cursor:pointer;color:#2563eb;border-bottom:2px solid #2563eb;margin-bottom:-2px">
      &#128683; Cancelar / Restaurar
    </button>
    <button id="tab-btn-estatus" onclick="adminTab('estatus')"
      style="padding:10px 20px;font-size:13px;font-weight:600;border:none;background:none;cursor:pointer;color:#64748b;border-bottom:2px solid transparent;margin-bottom:-2px">
      &#9998;&#65039; Correcci&#243;n de Estatus
    </button>
  </div>

  <!-- Panel 1: Cancelar/Restaurar -->
  <div id="panel-cancelar">
    <div class="aviso">
      &#9888;&#65039; <div>Al <strong>cancelar</strong> una orden se oculta del dashboard y las piezas dejan de contarse en producci&#243;n. El historial se conserva. Puedes restaurarla en cualquier momento.</div>
    </div>
    <div class="filtros">
      <input type="text" id="buscar" placeholder="&#128269; N&#250;mero de orden, folio o cliente..." oninput="filtrar()" onkeydown="if(event.key==='Enter')filtrar()">
    </div>
    <div class="tabla-wrap">
      <table>
        <thead><tr><th>Folio</th><th>Cliente</th><th>Asesor</th><th>Fecha entrega</th><th>Piezas</th><th>Estado</th><th>Acci&#243;n</th></tr></thead>
        <tbody id="tbody">
          <tr><td colspan="7" class="empty">Cargando&#8230;</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Panel 2: Corrección de Estatus -->
  <div id="panel-estatus" style="display:none">
    <div class="aviso" style="background:#fef3c7;border-left-color:#d97706">
      &#9888;&#65039; <div>Cambia el estatus de <strong>todas las piezas</strong> de una orden de un solo golpe. &#218;til para corregir &#243;rdenes del sistema anterior.</div>
    </div>
    <div class="filtros">
      <input type="text" id="buscar-estatus" placeholder="&#128269; Buscar folio o cliente..." oninput="buscarParaEstatus()">
    </div>
    <div id="estatus-resultado"></div>
    <div id="estatus-form" style="display:none;background:white;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-top:16px">
      <h3 style="font-size:15px;font-weight:700;margin-bottom:16px;color:#1e293b">&#9998;&#65039; Corregir Estatus Masivo</h3>
      <div id="estatus-orden-info" style="background:#f8fafc;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;font-weight:600;color:#1e293b"></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:6px">NUEVO ESTATUS PARA TODAS LAS PIEZAS</label>
          <select id="nuevo-estatus" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px">
            <option value="">Seleccionar estatus...</option>
            <option value="pendiente">Pendiente</option>
            <option value="en_corte">En CNC</option>
            <option value="cortado">Cortado</option>
            <option value="canteado">Canteado</option>
            <option value="trazo">Trazo</option>
            <option value="taladro">Taladro</option>
            <option value="en_horno">En Horno</option>
            <option value="terminado">Terminado</option>
            <option value="entregado">Entregado</option>
          </select>
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:6px">FECHA DEL CAMBIO (OPCIONAL)</label>
          <input type="datetime-local" id="fecha-cambio" style="width:100%;padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px">
        </div>
      </div>
      <div id="estatus-piezas-lista" style="margin-bottom:16px;max-height:280px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:8px;font-size:12px"></div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button onclick="cancelarEstatus()" style="padding:9px 20px;background:#f1f5f9;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer">Cancelar</button>
        <button onclick="aplicarEstatus()" style="padding:9px 20px;background:#2563eb;color:white;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer">&#10003; Aplicar a todas las piezas</button>
      </div>
    </div>
  </div>

</div>

<!-- Modal cancelar -->
<div class="modal-overlay" id="modalCancelar">
  <div class="modal">
    <h3>&#128683; Cancelar orden</h3>
    <p>&#191;Est&#225;s seguro de que deseas cancelar esta orden?</p>
    <p>La orden se ocultar&#225; del sistema pero el historial se conserva. Puedes restaurarla despu&#233;s.</p>
    <div class="orden-info">
      <strong id="modalFolio">—</strong><br>
      <span id="modalCliente">—</span><br>
      <span id="modalPiezas">—</span>
    </div>
    <div class="acciones">
      <button class="btn-cancel" onclick="cerrarModal()">No, regresar</button>
      <button class="btn-ok" onclick="confirmarCancelar()">S&#237;, cancelar orden</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
window.ModAdminOrdenes = (function() {
  let _ordenes = [], _folioActual = null, _estatusOrdenActual = null;

  async function cargar() {
    try {
      const res  = await fetch('../api/admin_ordenes.php');
      const data = await res.json();
      _ordenes = Array.isArray(data) ? data : (data.ordenes || []);
      renderTabla(_ordenes);
    } catch(e) {
      document.getElementById('tbody').innerHTML = '<tr><td colspan="7" class="empty" style="color:#dc2626">Error al cargar</td></tr>';
    }
  }

  function renderTabla(lista) {
    if (!lista.length) {
      document.getElementById('tbody').innerHTML = '<tr><td colspan="7" class="empty">Sin &#243;rdenes</td></tr>';
      return;
    }
    document.getElementById('tbody').innerHTML = lista.map(o => {
      const badgeCls = o.estado === 'activa' ? 'badge-activa' : 'badge-cancelada';
      const btn = o.estado === 'cancelada'
        ? `<button class="btn-restaurar" onclick="restaurar('${o.folio}','${(o.cliente_nombre||'').replace(/'/g,"\\'")}')">&#8617; Restaurar</button>`
        : `<button class="btn-cancelar" onclick="abrirModal('${o.folio}','${(o.cliente_nombre||'').replace(/'/g,"\\'")}',${o.total_piezas||0})">&#128683; Cancelar</button>`;
      const fecha = o.fecha_entrega ? new Date(o.fecha_entrega+'T12:00:00').toLocaleDateString('es-MX',{day:'2-digit',month:'short',year:'numeric'}) : '&#8212;';
      return `<tr>
        <td class="td-folio" onclick="irA('orden',{folio:'${o.folio}'})">${o.folio}</td>
        <td>${o.cliente_nombre||'&#8212;'}</td>
        <td style="color:#64748b">${o.asesor||'&#8212;'}</td>
        <td style="color:#64748b">${fecha}</td>
        <td style="color:#64748b">${o.total_piezas||0} pzs</td>
        <td><span class="estado-badge ${badgeCls}">${o.estado||''}</span></td>
        <td>${btn}</td>
      </tr>`;
    }).join('');
  }

  function filtrar() {
    const q = document.getElementById('buscar').value.toLowerCase();
    renderTabla(_ordenes.filter(o =>
      (o.folio||'').toLowerCase().includes(q) ||
      (o.cliente_nombre||'').toLowerCase().includes(q) ||
      (o.asesor||'').toLowerCase().includes(q)
    ));
  }

  function abrirModal(folio, cliente, pzs) {
    _folioActual = folio;
    document.getElementById('modalFolio').textContent   = folio;
    document.getElementById('modalCliente').textContent = cliente;
    document.getElementById('modalPiezas').textContent  = pzs + ' piezas';
    document.getElementById('modalCancelar').classList.add('activo');
  }

  function cerrarModal() {
    document.getElementById('modalCancelar').classList.remove('activo');
    _folioActual = null;
  }

  async function confirmarCancelar() {
    if (!_folioActual) return;
    try {
      const res  = await fetch('../api/admin_ordenes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({accion:'cancelar', folio:_folioActual})
      });
      const data = await res.json();
      if (data.ok) { cerrarModal(); toast('Orden cancelada'); await cargar(); }
      else toast('Error: '+(data.error||''), true);
    } catch(e) { toast('Error de conexi&#243;n', true); }
  }

  async function restaurar(folio, cliente) {
    if (!confirm('&#191;Restaurar orden '+folio+'?')) return;
    try {
      const res  = await fetch('../api/admin_ordenes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({accion:'restaurar', folio})
      });
      const data = await res.json();
      if (data.ok) { toast('Orden restaurada'); await cargar(); }
      else toast('Error: '+(data.error||''), true);
    } catch(e) { toast('Error', true); }
  }

  function toast(msg, err=false) {
    const t = document.getElementById('toast');
    t.innerHTML = msg; t.className = 'toast show' + (err?' error':'');
    setTimeout(() => t.className='toast', 3000);
  }

  // ── TABS ───────────────────────────────────────────────
  function adminTab(tab) {
    ['cancelar','estatus'].forEach(p => {
      document.getElementById('panel-'+p).style.display = p===tab ? '' : 'none';
      const btn = document.getElementById('tab-btn-'+p);
      if (btn) {
        btn.style.color = p===tab ? '#2563eb' : '#64748b';
        btn.style.borderBottom = p===tab ? '2px solid #2563eb' : '2px solid transparent';
      }
    });
  }

  // ── CORRECCIÓN DE ESTATUS ──────────────────────────────
  async function buscarParaEstatus() {
    const q = document.getElementById('buscar-estatus').value.trim();
    if (q.length < 2) { document.getElementById('estatus-resultado').innerHTML = ''; return; }
    const lista = _ordenes.filter(o =>
      (o.folio||'').toLowerCase().includes(q.toLowerCase()) ||
      (o.cliente_nombre||'').toLowerCase().includes(q.toLowerCase())
    );
    if (!lista.length) {
      document.getElementById('estatus-resultado').innerHTML = '<div style="padding:12px;color:#64748b;font-size:13px">Sin resultados</div>';
      return;
    }
    document.getElementById('estatus-resultado').innerHTML = lista.map(o =>
      `<div onclick="seleccionarOrdenEstatus('${o.folio}','${(o.cliente_nombre||'').replace(/'/g,"\\'")}',${o.total_piezas||0})"
        style="padding:12px 16px;background:white;border-radius:8px;margin-bottom:6px;cursor:pointer;border:1.5px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center"
        onmouseover="this.style.borderColor='#2563eb'" onmouseout="this.style.borderColor='#e2e8f0'">
        <div>
          <div style="font-weight:700;color:#2563eb">${o.folio}</div>
          <div style="font-size:12px;color:#64748b">${o.cliente_nombre||''} &middot; ${o.asesor||''}</div>
        </div>
        <div style="font-size:12px;color:#64748b">${o.total_piezas||0} pzs &middot; ${o.estado||''}</div>
      </div>`
    ).join('');
  }

  async function seleccionarOrdenEstatus(folio, cliente, totalPzs) {
    _estatusOrdenActual = folio;
    document.getElementById('estatus-resultado').innerHTML = '';
    document.getElementById('buscar-estatus').value = folio;
    document.getElementById('estatus-orden-info').innerHTML = `<strong>${folio}</strong> &mdash; ${cliente} &mdash; ${totalPzs} piezas`;

    try {
      const res  = await fetch('../api/orden.php?folio='+encodeURIComponent(folio));
      const data = await res.json();
      const partidas = data.partidas || [];
      let rows = '';
      partidas.forEach(p => {
        (p.piezas||[]).forEach(pz => {
          rows += `<div style="display:flex;justify-content:space-between;padding:8px 14px;border-bottom:1px solid #f1f5f9">
            <span>P${p.partida} &bull; Pieza ${pz.pieza_num} &bull; ${p.cristal||''} ${p.ancho_mm}&#215;${p.alto_mm}mm</span>
            <span style="color:#64748b;font-weight:600">${pz.estatus||''}</span>
          </div>`;
        });
      });
      document.getElementById('estatus-piezas-lista').innerHTML = rows || '<div style="padding:12px;color:#64748b">Sin piezas</div>';
    } catch(e) {
      document.getElementById('estatus-piezas-lista').innerHTML = '<div style="padding:12px;color:#64748b">No se pudieron cargar las piezas</div>';
    }
    document.getElementById('estatus-form').style.display = '';
  }

  function cancelarEstatus() {
    _estatusOrdenActual = null;
    document.getElementById('estatus-form').style.display = 'none';
    document.getElementById('buscar-estatus').value = '';
    document.getElementById('estatus-resultado').innerHTML = '';
    document.getElementById('estatus-piezas-lista').innerHTML = '';
  }

  async function aplicarEstatus() {
    if (!_estatusOrdenActual) return;
    const estatus = document.getElementById('nuevo-estatus').value;
    const fecha   = document.getElementById('fecha-cambio').value;
    if (!estatus) { alert('Selecciona un estatus'); return; }
    if (!confirm('Aplicar estatus "'+estatus+'" a TODAS las piezas de '+_estatusOrdenActual+'?')) return;

    try {
      const res  = await fetch('../api/admin_ordenes.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({accion:'corregir_estatus', folio:_estatusOrdenActual, estatus, fecha:fecha||null})
      });
      const data = await res.json();
      if (data.ok) {
        toast('&#10003; Estatus actualizado: '+data.piezas_actualizadas+' piezas');
        cancelarEstatus();
        await cargar();
      } else {
        toast('Error: '+(data.error||''), true);
      }
    } catch(e) { toast('Error de conexi&#243;n', true); }
  }

  // Exponer funciones usadas en onclick
  window.adminTab              = adminTab;
  window.filtrar               = filtrar;
  window.abrirModal            = abrirModal;
  window.cerrarModal           = cerrarModal;
  window.confirmarCancelar     = confirmarCancelar;
  window.restaurar             = restaurar;
  window.buscarParaEstatus     = buscarParaEstatus;
  window.seleccionarOrdenEstatus = seleccionarOrdenEstatus;
  window.cancelarEstatus       = cancelarEstatus;
  window.aplicarEstatus        = aplicarEstatus;

  return { init: cargar };
})();
window.ModAdminOrdenes.init();
</script>