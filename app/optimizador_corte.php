<?php
// ============================================================
//  APEX GLASS - Optimizador de Corte (móvil)
//  Archivo: /produccion/app/optimizador_corte.php
// ============================================================
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: /produccion/app/login.php'); exit;
}
$nombre = $_SESSION['user_name'] ?? 'Operador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>APEX GLASS — Optimizador Corte</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --bg:      #0d0d0f;
  --surface: #18181c;
  --border:  #2a2a32;
  --text:    #f0f0f0;
  --muted:   #6b6b7a;
  --accent:  #f5a623;
  --green:   #22d47a;
  --red:     #ff4757;
  --blue:    #4a9eff;
}
body {
  background: var(--bg); color: var(--text);
  font-family: -apple-system, 'Helvetica Neue', sans-serif;
  min-height: 100dvh; padding-bottom: 30px;
}

.header {
  background: var(--surface); border-bottom: 1px solid var(--border);
  padding: 14px 16px;
  display: flex; align-items: center; justify-content: space-between;
  position: sticky; top: 0; z-index: 10;
}
.header-title { font-size: 16px; font-weight: 800; }
.header-sub   { font-size: 11px; color: var(--muted); margin-top: 1px; }
.btn-back {
  background: none; border: 1px solid var(--border);
  color: var(--muted); font-size: 13px; padding: 6px 12px;
  border-radius: 6px; cursor: pointer; text-decoration: none;
}

.content { padding: 16px; }

/* PASO */
.step { display: none; }
.step.active { display: block; }

.step-title {
  font-size: 12px; font-weight: 700; letter-spacing: 2px;
  text-transform: uppercase; color: var(--muted);
  margin-bottom: 14px;
}

/* CRISTAL LIST */
.cristal-btn {
  width: 100%; background: var(--surface);
  border: 1.5px solid var(--border); border-radius: 12px;
  padding: 16px; margin-bottom: 10px;
  display: flex; align-items: center; justify-content: space-between;
  cursor: pointer; -webkit-tap-highlight-color: transparent;
  color: var(--text);
}
.cristal-btn:active { border-color: var(--accent); }
.cristal-btn-name { font-size: 16px; font-weight: 700; }
.cristal-btn-info { font-size: 12px; color: var(--muted); margin-top: 2px; }
.cristal-btn-count {
  font-size: 22px; font-weight: 900; color: var(--accent);
  background: rgba(245,166,35,.1); border-radius: 8px;
  padding: 4px 12px;
}

/* FORM LÁMINA */
.form-card {
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 14px; padding: 18px; margin-bottom: 16px;
}
.form-card h3 { font-size: 14px; font-weight: 700; margin-bottom: 14px; }
.field { margin-bottom: 14px; }
.field label {
  display: block; font-size: 10px; font-weight: 700;
  letter-spacing: 2px; text-transform: uppercase;
  color: var(--muted); margin-bottom: 7px;
}
.field input {
  width: 100%; background: var(--bg);
  border: 1.5px solid var(--border); border-radius: 10px;
  padding: 13px 14px; font-size: 18px; color: var(--text);
  outline: none; -webkit-appearance: none;
}
.field input:focus { border-color: var(--accent); }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

.btn-primary {
  width: 100%; padding: 16px; background: var(--accent); color: #000;
  border: none; border-radius: 12px; font-size: 16px; font-weight: 800;
  cursor: pointer; -webkit-tap-highlight-color: transparent;
}
.btn-primary:active { opacity: .85; }
.btn-secondary {
  width: 100%; padding: 14px; background: none; color: var(--muted);
  border: 1.5px solid var(--border); border-radius: 12px;
  font-size: 14px; font-weight: 700; cursor: pointer; margin-top: 10px;
}

/* RESULTADOS */
.resumen-card {
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 14px; padding: 16px; margin-bottom: 16px;
}
.resumen-grid {
  display: grid; grid-template-columns: 1fr 1fr 1fr;
  gap: 12px; margin-bottom: 4px;
}
.resumen-item { text-align: center; }
.resumen-num  { font-size: 28px; font-weight: 900; line-height: 1; }
.resumen-lbl  { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; margin-top: 3px; }

.lamina-card {
  background: var(--surface); border: 1.5px solid var(--border);
  border-radius: 14px; overflow: hidden; margin-bottom: 12px;
}
.lamina-head {
  padding: 12px 16px; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.lamina-title { font-size: 15px; font-weight: 800; }
.aprov-badge {
  font-size: 13px; font-weight: 800; padding: 4px 12px; border-radius: 8px;
}
.aprov-high { background: rgba(34,212,122,.15); color: var(--green); }
.aprov-mid  { background: rgba(245,166,35,.15); color: var(--accent); }
.aprov-low  { background: rgba(255,71,87,.15);  color: var(--red); }

.lamina-folios {
  padding: 10px 16px; border-bottom: 1px solid var(--border);
  display: flex; flex-wrap: wrap; gap: 6px;
}
.folio-tag {
  font-size: 12px; font-weight: 700; padding: 4px 10px;
  background: rgba(74,158,255,.1); color: var(--blue);
  border: 1px solid rgba(74,158,255,.25); border-radius: 6px;
}

.lamina-detalle { padding: 10px 16px; }
.detalle-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 6px 0; border-bottom: 1px solid var(--border);
  font-size: 13px;
}
.detalle-row:last-child { border-bottom: none; }
.detalle-folio { font-weight: 700; }
.detalle-medida { color: var(--muted); font-size: 12px; }
.cpb-tag {
  font-size: 10px; font-weight: 700; padding: 2px 6px;
  border: 1px solid var(--accent); color: var(--accent); border-radius: 4px;
}

/* NO CABEN */
.no-caben-card {
  background: rgba(255,71,87,.08); border: 1.5px solid rgba(255,71,87,.3);
  border-radius: 12px; padding: 14px 16px; margin-bottom: 16px;
}
.no-caben-title { font-size: 12px; font-weight: 700; color: var(--red); margin-bottom: 8px; }
.no-caben-row { font-size: 13px; color: var(--muted); padding: 3px 0; }

/* REGISTRAR */
.registro-card {
  background: var(--surface); border: 1.5px solid var(--green);
  border-radius: 14px; padding: 16px; margin-bottom: 16px;
}
.registro-card h3 { font-size: 14px; font-weight: 700; color: var(--green); margin-bottom: 12px; }

/* LOADING */
.loading { text-align: center; padding: 40px; color: var(--muted); }
.spin {
  display: inline-block; width: 28px; height: 28px;
  border: 3px solid var(--border); border-top-color: var(--accent);
  border-radius: 50%; animation: spin .7s linear infinite; margin-bottom: 12px;
}
@keyframes spin { to { transform: rotate(360deg); } }

.toast {
  position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%);
  background: #222; color: var(--text); padding: 12px 20px;
  border-radius: 10px; font-size: 14px; font-weight: 600;
  opacity: 0; transition: opacity .3s; pointer-events: none; white-space: nowrap; z-index: 99;
}
.toast.show { opacity: 1; }
.toast.ok  { background: #1a3a2a; color: var(--green); }
.toast.err { background: #3a1a1a; color: var(--red); }
</style>
</head>
<body>

<div class="header">
  <div>
    <div class="header-title">📐 Optimizador de Corte</div>
    <div class="header-sub"><?= htmlspecialchars($nombre) ?></div>
  </div>
  <a href="/produccion/app/corte_dashboard.php" class="btn-back">← Volver</a>
</div>

<div class="content">

  <!-- PASO 1: Seleccionar cristal -->
  <div class="step active" id="paso1">
    <div class="step-title">Paso 1 — Selecciona tipo de vidrio</div>
    <div id="cristalList">
      <div class="loading"><div class="spin"></div><br>Cargando...</div>
    </div>
  </div>

  <!-- PASO 2: Dimensiones de lámina -->
  <div class="step" id="paso2">
    <div class="step-title">Paso 2 — Dimensiones de la lámina</div>
    <div class="form-card">
      <h3 id="paso2Cristal">Tipo de vidrio</h3>
      <div class="field-row">
        <div class="field">
          <label>Ancho (mm)</label>
          <input type="number" id="anchoLamina" placeholder="3050" inputmode="numeric">
        </div>
        <div class="field">
          <label>Alto (mm)</label>
          <input type="number" id="altoLamina" placeholder="2140" inputmode="numeric">
        </div>
      </div>
    </div>
    <button class="btn-primary" onclick="optimizar()">📐 Calcular optimización</button>
    <button class="btn-secondary" onclick="irPaso(1)">← Cambiar vidrio</button>
  </div>

  <!-- PASO 3: Resultados -->
  <div class="step" id="paso3">
    <div class="step-title">Paso 3 — Resultado</div>
    <div id="resultados"></div>
    <button class="btn-secondary" onclick="irPaso(2)">← Cambiar dimensiones</button>
  </div>

</div>

<div class="toast" id="toast"></div>

<script>
const API = '/produccion/api/';
let cristalSel = '';

// ── Paso 1: cargar cristales ──────────────────────────────
async function cargarCristales() {
  try {
    const r = await fetch(API + 'optimizador_corte.php?t=' + Date.now());
    const d = await r.json();
    const el = document.getElementById('cristalList');
    if (!d.cristales?.length) {
      el.innerHTML = '<div class="loading">Sin piezas pendientes de corte</div>'; return;
    }
    el.innerHTML = d.cristales.map(c => `
      <button class="cristal-btn" onclick="selCristal('${c.cristal.replace(/'/g,"\\'")}')">
        <div>
          <div class="cristal-btn-name">${c.cristal}</div>
          <div class="cristal-btn-info">${parseFloat(c.total_m2).toFixed(2)} m²</div>
        </div>
        <div class="cristal-btn-count">${c.total_piezas}</div>
      </button>
    `).join('');
  } catch(e) {
    document.getElementById('cristalList').innerHTML = '<div class="loading">Error de conexión</div>';
  }
}

function selCristal(cristal) {
  cristalSel = cristal;
  document.getElementById('paso2Cristal').textContent = cristal;
  irPaso(2);
}

// ── Paso 2: optimizar ─────────────────────────────────────
async function optimizar() {
  const ancho = parseInt(document.getElementById('anchoLamina').value);
  const alto  = parseInt(document.getElementById('altoLamina').value);
  if (!ancho || !alto) { toast('Ingresa las dimensiones de la lámina', 'err'); return; }

  irPaso(3);
  document.getElementById('resultados').innerHTML = '<div class="loading"><div class="spin"></div><br>Calculando...</div>';

  try {
    const r = await fetch(API + 'optimizador_corte.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cristal: cristalSel, ancho_lamina: ancho, alto_lamina: alto })
    });
    const d = await r.json();
    if (d.error) { document.getElementById('resultados').innerHTML = `<div class="loading">${d.error}</div>`; return; }
    renderResultados(d, ancho, alto);
  } catch(e) {
    document.getElementById('resultados').innerHTML = '<div class="loading">Error de conexión</div>';
  }
}

function aprovClass(pct) {
  if (pct >= 70) return 'aprov-high';
  if (pct >= 40) return 'aprov-mid';
  return 'aprov-low';
}

function renderResultados(d, ancho, alto) {
  let html = '';

  // Resumen global
  html += `
    <div class="resumen-card">
      <div class="resumen-grid">
        <div class="resumen-item">
          <div class="resumen-num" style="color:var(--accent)">${d.total_laminas}</div>
          <div class="resumen-lbl">Láminas</div>
        </div>
        <div class="resumen-item">
          <div class="resumen-num" style="color:var(--blue)">${d.total_piezas}</div>
          <div class="resumen-lbl">Piezas</div>
        </div>
        <div class="resumen-item">
          <div class="resumen-num" style="color:var(--green)">${d.prom_aprovechamiento}%</div>
          <div class="resumen-lbl">Prom. aprov.</div>
        </div>
      </div>
    </div>`;

  // Láminas
  d.laminas.forEach(l => {
    const cls = aprovClass(l.aprovechamiento);
    const foliosHTML = Object.entries(l.folios)
      .map(([f, c]) => `<span class="folio-tag">${f}</span>`).join('');

    const detalleHTML = l.detalle.map(p => `
      <div class="detalle-row">
        <div>
          <span class="detalle-folio">${p.folio} P${p.partida}</span>
          <span class="detalle-medida"> · ${p.medida}mm</span>
          ${p.tiene_cpb ? '<span class="cpb-tag">CPB</span>' : ''}
        </div>
        <div class="detalle-medida">${p.corte}mm</div>
      </div>`).join('');

    html += `
      <div class="lamina-card">
        <div class="lamina-head">
          <div class="lamina-title">Lámina ${l.lamina_num} — ${l.piezas} pzs</div>
          <div class="aprov-badge ${cls}">${l.aprovechamiento}%</div>
        </div>
        <div class="lamina-folios">${foliosHTML}</div>
        <div class="lamina-detalle">${detalleHTML}</div>
      </div>`;
  });

  // No caben
  if (d.no_caben?.length) {
    html += `<div class="no-caben-card">
      <div class="no-caben-title">⚠️ ${d.no_caben.length} piezas no caben en esta lámina</div>
      ${d.no_caben.map(p => `<div class="no-caben-row">${p.folio} — ${p.medida}mm</div>`).join('')}
    </div>`;
  }

  // Registrar consumo
  const foliosTodos = d.laminas.flatMap(l => Object.keys(l.folios)).filter((v,i,a) => a.indexOf(v)===i).join(', ');
  html += `
    <div class="registro-card">
      <h3>✅ Registrar láminas usadas</h3>
      <div class="field">
        <label>Cantidad de láminas a usar</label>
        <input type="number" id="regCantidad" value="${d.total_laminas}" min="1" inputmode="numeric">
      </div>
      <div class="field">
        <label>Pedidos incluidos</label>
        <input type="text" id="regPedidos" value="${foliosTodos}">
      </div>
      <button class="btn-primary" onclick="registrar(${ancho}, ${alto})">💾 Registrar consumo</button>
    </div>`;

  document.getElementById('resultados').innerHTML = html;
}

async function registrar(ancho, alto) {
  const cantidad = parseInt(document.getElementById('regCantidad').value);
  const pedidos  = document.getElementById('regPedidos').value.trim();
  if (!cantidad) { toast('Indica la cantidad de láminas', 'err'); return; }

  try {
    const r = await fetch(API + 'optimizador_corte.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        accion: 'registrar',
        cristal: cristalSel,
        ancho_lamina: ancho,
        alto_lamina: alto,
        cantidad, pedidos
      })
    });
    const d = await r.json();
    if (d.ok) {
      toast('✅ Consumo registrado', 'ok');
      setTimeout(() => irPaso(1), 1500);
    } else {
      toast('❌ ' + (d.error || 'Error'), 'err');
    }
  } catch(e) {
    toast('❌ Error de conexión', 'err');
  }
}

function irPaso(n) {
  document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));
  document.getElementById('paso' + n).classList.add('active');
  window.scrollTo(0, 0);
}

function toast(msg, tipo = '') {
  const el = document.getElementById('toast');
  el.textContent = msg; el.className = 'toast show ' + tipo;
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 3000);
}

cargarCristales();
</script>
</body>
</html>