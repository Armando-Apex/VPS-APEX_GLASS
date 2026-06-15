<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user = requirePermiso('cambiar_cualquier_estatus');
// Solo dir_admin puede entrar
if ($user['rol'] !== 'dir_admin') {
    header('Location: 403.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Administrar Órdenes — APEX GLASS</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; background: #f1f5f9; color: #1e293b; min-height: 100vh; }

.topbar { background: #1a1a2e; color: white; padding: 14px 24px; display: flex; align-items: center; gap: 16px; }
.topbar .titulo { font-size: 15px; font-weight: 700; letter-spacing: .5px; }
.topbar .subtitulo { font-size: 12px; color: #94a3b8; }
.topbar .back { margin-left: auto; color: #94a3b8; text-decoration: none; font-size: 13px; }
.topbar .back:hover { color: white; }

.container { max-width: 900px; margin: 32px auto; padding: 0 16px; }

.aviso { background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 14px 18px; margin-bottom: 24px; font-size: 13px; color: #991b1b; display: flex; gap: 10px; align-items: flex-start; }

.filtros { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
.filtros input { flex: 1; min-width: 200px; padding: 9px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; background: white; }
.filtros input:focus { outline: none; border-color: #2563eb; }

.tabla-wrap { background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f8fafc; border-bottom: 2px solid #e2e8f0; }
thead th { padding: 11px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #64748b; text-align: left; }
tbody tr { border-bottom: 1px solid #f1f5f9; transition: background .15s; }
tbody tr:hover { background: #f8fafc; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 12px 14px; font-size: 13px; }

.folio { font-weight: 700; color: #2563eb; }
.cliente { color: #374151; }
.asesor { color: #64748b; font-size: 12px; }
.fecha { font-size: 12px; color: #64748b; }
.piezas { font-size: 12px; color: #374151; }

.estado-badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 99px; white-space: nowrap; }
.estado-activa    { background: #dbeafe; color: #1d4ed8; }
.estado-entregada { background: #dcfce7; color: #15803d; }
.estado-cancelada { background: #fee2e2; color: #b91c1c; }

.btn-cancelar { background: #fee2e2; color: #b91c1c; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: background .15s; white-space: nowrap; }
.btn-cancelar:hover { background: #fecaca; }
.btn-cancelar:disabled { opacity: .4; cursor: default; }
.btn-restaurar { background: #dbeafe; color: #1d4ed8; border: none; padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; transition: background .15s; white-space: nowrap; }
.btn-restaurar:hover { background: #bfdbfe; }

.empty { padding: 40px; text-align: center; color: #94a3b8; font-size: 14px; }
.loading { padding: 40px; text-align: center; color: #64748b; font-size: 13px; }

/* Modal de confirmación */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 100; align-items: center; justify-content: center; }
.modal-overlay.activo { display: flex; }
.modal { background: white; border-radius: 14px; padding: 28px; max-width: 420px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,.25); }
.modal h3 { font-size: 17px; font-weight: 700; margin-bottom: 10px; color: #1e293b; }
.modal p { font-size: 13px; color: #64748b; margin-bottom: 6px; line-height: 1.6; }
.modal .orden-info { background: #f8fafc; border-radius: 8px; padding: 12px 14px; margin: 14px 0; font-size: 13px; }
.modal .orden-info strong { color: #1e293b; }
.modal .orden-info span { color: #64748b; }
.modal .acciones { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
.modal .btn-ok { background: #dc2626; color: white; border: none; padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
.modal .btn-ok:hover { background: #b91c1c; }
.modal .btn-cancel { background: #f1f5f9; color: #374151; border: none; padding: 9px 20px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; }
.modal .btn-cancel:hover { background: #e2e8f0; }

.toast { position: fixed; bottom: 24px; right: 24px; background: #1e293b; color: white; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 20px rgba(0,0,0,.3); z-index: 200; transform: translateY(80px); opacity: 0; transition: all .3s; }
.toast.show { transform: translateY(0); opacity: 1; }
.toast.error { background: #dc2626; }
</style>
</head>
<body>

<div class="topbar">
  <div>
    <div class="titulo">⚙️ Administrar Órdenes</div>
    <div class="subtitulo">Cancelar / restaurar órdenes</div>
  </div>
  <a href="dashboard.php" class="back">← Volver al dashboard</a>
</div>

<div class="container">

  <div class="aviso">
    ⚠️ <div>Al <strong>cancelar</strong> una orden se oculta del dashboard y las piezas dejan de contarse en producción. El historial se conserva. Puedes restaurarla en cualquier momento.</div>
  </div>

  <div class="filtros">
    <input type="text" id="buscar" placeholder="🔍 Número de orden, folio o cliente..." 
           oninput="filtrar()" onkeydown="if(event.key==='Enter')filtrar()">
  </div>

  <div class="tabla-wrap">
    <table>
      <thead>
        <tr>
          <th>Folio</th>
          <th>Cliente</th>
          <th>Asesor</th>
          <th>Fecha entrega</th>
          <th>Piezas</th>
          <th>Estado</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody id="cuerpo">
        <tr><td colspan="7" class="loading">Cargando órdenes…</td></tr>
      </tbody>
    </table>
  </div>

</div>

<!-- Modal confirmación cancelar -->
<div class="modal-overlay" id="modalCancelar">
  <div class="modal">
    <h3>🚫 Cancelar orden</h3>
    <p>¿Estás seguro de que deseas cancelar esta orden?</p>
    <div class="orden-info" id="modalInfo"></div>
    <p>La orden se ocultará del sistema pero el historial se conserva. Puedes restaurarla después.</p>
    <div class="acciones">
      <button class="btn-cancel" onclick="cerrarModal()">No, regresar</button>
      <button class="btn-ok" onclick="confirmarCancelar()">Sí, cancelar orden</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
const API = '../api/admin_ordenes.php';
let ordenes = [];
let ordenSeleccionada = null;

async function cargar() {
  try {
    const res  = await fetch(API);
    const data = await res.json();
    if (data.error) throw new Error(data.error);
    ordenes = data.ordenes || [];
    renderTabla(ordenes);
  } catch(e) {
    document.getElementById('cuerpo').innerHTML =
      `<tr><td colspan="7" class="empty">Error al cargar: ${e.message}</td></tr>`;
  }
}

function filtrar() {
  const q = document.getElementById('buscar').value.trim();
  if (!q) {
    renderTabla(ordenes);
    return;
  }

  // Normalizar igual que el scanner: solo números+letras, sin guiones ni espacios
  const num = q.replace(/[^0-9A-Za-z\s]/g, '').toUpperCase().trim();

  // Buscar primero en los datos ya cargados (folio y cliente)
  const local = ordenes.filter(o =>
    (o.folio||'').toUpperCase().includes(num) ||
    (o.cliente_nombre||'').toUpperCase().includes(q.toUpperCase())
  );

  if (local.length > 0) {
    renderTabla(local);
  } else {
    // Si no hay resultados locales, consultar el API igual que el scanner
    fetch(API + '?buscar=' + encodeURIComponent(num))
      .then(r => r.json())
      .then(data => {
        if (data.ordenes && data.ordenes.length) {
          renderTabla(data.ordenes);
        } else {
          document.getElementById('cuerpo').innerHTML =
            '<tr><td colspan="7" class="empty">No se encontró la orden "' + q + '"</td></tr>';
        }
      })
      .catch(() => renderTabla([]));
  }
}

function renderTabla(lista) {
  if (!lista.length) {
    document.getElementById('cuerpo').innerHTML =
      '<tr><td colspan="7" class="empty">No hay órdenes</td></tr>';
    return;
  }

  document.getElementById('cuerpo').innerHTML = lista.map(o => {
    const fecha = o.fecha_entrega
      ? new Date(o.fecha_entrega).toLocaleDateString('es-MX', {day:'2-digit', month:'short', year:'numeric'})
      : '—';
    const estadoClass = `estado-${o.estado}`;
    const estadoLabel = {activa:'Activa', entregada:'Entregada', cancelada:'Cancelada'}[o.estado] || o.estado;
    const esCancelada = o.estado === 'cancelada';

    const btnAccion = esCancelada
      ? `<button class="btn-restaurar" onclick="restaurar('${o.folio}', '${(o.cliente_nombre||'').replace(/'/g,"\\'")}')">↩ Restaurar</button>`
      : `<button class="btn-cancelar" onclick="abrirModal('${o.folio}', '${(o.cliente_nombre||'').replace(/'/g,"\\'")}', ${o.total_piezas||0})">🚫 Cancelar</button>`;

    return `<tr>
      <td class="folio">${o.folio}</td>
      <td class="cliente">${o.cliente_nombre||'—'}</td>
      <td class="asesor">${o.asesor||'—'}</td>
      <td class="fecha">${fecha}</td>
      <td class="piezas">${o.total_piezas||0} pzs</td>
      <td><span class="estado-badge ${estadoClass}">${estadoLabel}</span></td>
      <td>${btnAccion}</td>
    </tr>`;
  }).join('');
}

function abrirModal(folio, cliente, piezas) {
  ordenSeleccionada = folio;
  document.getElementById('modalInfo').innerHTML =
    `<strong>Folio:</strong> <span>${folio}</span><br>
     <strong>Cliente:</strong> <span>${cliente}</span><br>
     <strong>Piezas:</strong> <span>${piezas} piezas en producción</span>`;
  document.getElementById('modalCancelar').classList.add('activo');
}

function cerrarModal() {
  document.getElementById('modalCancelar').classList.remove('activo');
  ordenSeleccionada = null;
}

async function confirmarCancelar() {
  if (!ordenSeleccionada) return;
  try {
    const res  = await fetch(API, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ accion: 'cancelar', folio: ordenSeleccionada })
    });
    const data = await res.json();
    cerrarModal();
    if (data.ok) {
      toast('Orden cancelada correctamente');
      await cargar();
    } else {
      toast(data.error || 'Error al cancelar', true);
    }
  } catch(e) {
    cerrarModal();
    toast('Error de conexión', true);
  }
}

async function restaurar(folio, cliente) {
  if (!confirm(`¿Restaurar la orden ${folio} (${cliente})?`)) return;
  try {
    const res  = await fetch(API, {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ accion: 'restaurar', folio })
    });
    const data = await res.json();
    if (data.ok) {
      toast('Orden restaurada correctamente');
      await cargar();
    } else {
      toast(data.error || 'Error al restaurar', true);
    }
  } catch(e) {
    toast('Error de conexión', true);
  }
}

function toast(msg, error = false) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast show' + (error ? ' error' : '');
  setTimeout(() => t.className = 'toast', 3000);
}

cargar();
</script>
</body>
</html>