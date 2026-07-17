<?php
// ============================================================
//  APEX GLASS - Módulo: Campañas WhatsApp
//  Archivo: app/modulos/campanas.php
// ============================================================
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requireSession();
$rol  = $user['rol'];
$puedeEnviar = in_array($rol, ['dir_admin','dueno','desarrollo','comercial','administracion']);
?>
<style>
.cmp-tabs{display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:16px;}
.cmp-tab{padding:10px 20px;font-size:13px;font-weight:600;color:#64748b;cursor:pointer;border:none;background:none;border-bottom:2px solid transparent;margin-bottom:-2px;}
.cmp-tab.active{color:#2563eb;border-bottom-color:#2563eb;}
.cmp-card{background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:12px;}
.cmp-badge{display:inline-block;font-size:11px;font-weight:700;padding:2px 8px;border-radius:99px;}
.cmp-badge.enviada{background:#dcfce7;color:#16a34a;}
.cmp-badge.enviando{background:#fef9c3;color:#ca8a04;}
.cmp-badge.borrador{background:#f1f5f9;color:#64748b;}
.cmp-badge.cancelada{background:#fee2e2;color:#dc2626;}
.cmp-metricas{display:flex;gap:20px;font-size:12px;color:#64748b;margin-top:10px;flex-wrap:wrap;}
.cmp-metrica span{font-weight:700;color:#1e293b;}
.cmp-wizard-steps{display:flex;gap:0;margin-bottom:20px;}
.cmp-step{flex:1;text-align:center;padding:8px;font-size:12px;font-weight:600;color:#94a3b8;border-bottom:3px solid #e2e8f0;}
.cmp-step.active{color:#2563eb;border-bottom-color:#2563eb;}
.cmp-step.done{color:#16a34a;border-bottom-color:#16a34a;}
.cmp-clientes-tabla{max-height:300px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px;}
.cmp-clientes-tabla table{width:100%;font-size:12px;border-collapse:collapse;}
.cmp-clientes-tabla th{background:#f8fafc;padding:8px 10px;text-align:left;font-weight:600;position:sticky;top:0;z-index:1;}
.cmp-clientes-tabla td{padding:7px 10px;border-top:1px solid #f1f5f9;}
.cmp-clientes-tabla tr:hover td{background:#f8fafc;}
.cmp-preview{background:#dcfce7;border-radius:12px 12px 12px 3px;padding:12px 16px;font-size:13px;max-width:320px;margin-top:8px;line-height:1.5;}
.cmp-progreso{background:#e2e8f0;border-radius:99px;height:8px;margin:12px 0;}
.cmp-progreso-bar{background:#2563eb;border-radius:99px;height:8px;transition:width .3s;}
.conv-panel{display:flex;gap:0;height:520px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;}
.conv-lista{width:300px;border-right:1px solid #e2e8f0;overflow-y:auto;flex-shrink:0;background:#fff;}
.conv-item{padding:12px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .1s;position:relative;}
.conv-item:hover,.conv-item.active{background:var(--c-blue-light);}
.conv-item-nombre{font-size:13px;font-weight:600;color:#1e293b;}
.conv-item-preview{font-size:11px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px;}
.conv-badge{background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;float:right;margin-top:2px;}
.conv-item-menu-btn{position:absolute;right:8px;bottom:10px;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:16px;padding:2px 6px;border-radius:4px;display:none;line-height:1;}
.conv-item:hover .conv-item-menu-btn{display:block;}
.conv-item-menu-btn:hover{background:#e2e8f0;color:#475569;}
.conv-ctx-menu{position:fixed;background:#fff;border:1px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);z-index:9999;min-width:160px;padding:4px 0;}
.conv-ctx-menu button{display:block;width:100%;text-align:left;padding:8px 14px;font-size:13px;background:none;border:none;cursor:pointer;color:#1e293b;}
.conv-ctx-menu button:hover{background:#f1f5f9;}
.conv-chat{flex:1;display:flex;flex-direction:column;background:#f8fafc;}
.conv-header{padding:12px 16px;background:#fff;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:600;color:#1e293b;}
.conv-mensajes{flex:1;padding:16px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;}
.msg-wrap{display:flex;flex-direction:column;position:relative;}
.msg-wrap.out{align-items:flex-end;}.msg-wrap.in{align-items:flex-start;}
.msg-burbuja{max-width:75%;padding:10px 14px;border-radius:12px;font-size:13px;line-height:1.5;word-break:break-word;white-space:pre-wrap;}
.msg-out{background:var(--c-blue-light);border-bottom-right-radius:3px;}
.msg-in{background:#fff;border:1px solid #e2e8f0;border-bottom-left-radius:3px;}
.msg-meta{font-size:10px;color:#94a3b8;margin-top:4px;text-align:right;}
.msg-in .msg-meta{text-align:left;}
.msg-burbuja{position:relative;}
.msg-reply-btn{display:none;position:absolute;top:4px;right:4px;background:rgba(255,255,255,.9);border:1px solid #e2e8f0;border-radius:99px;padding:2px 8px;font-size:12px;cursor:pointer;color:#475569;box-shadow:0 1px 4px rgba(0,0,0,.1);line-height:1.4;}
.msg-burbuja:hover .msg-reply-btn{display:block;}
.msg-quoted{background:rgba(0,0,0,.06);border-left:3px solid #2563eb;border-radius:4px;padding:4px 8px;margin-bottom:6px;font-size:11px;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;}
.msg-out .msg-quoted{border-left-color:#16a34a;}
.reply-bar{display:flex;align-items:center;gap:8px;background:#f1f5f9;border-radius:6px;padding:6px 10px;font-size:12px;color:#475569;}
.reply-bar-text{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.reply-bar-close{background:none;border:none;cursor:pointer;color:#94a3b8;font-size:16px;line-height:1;padding:0 2px;}
.reply-bar-close:hover{color:#dc2626;}
.conv-input{border-top:1px solid #e2e8f0;padding:12px;display:flex;flex-direction:column;gap:8px;background:#fff;}
.conv-input-row{display:flex;gap:8px;align-items:flex-end;}
.conv-input textarea{flex:1;border:1px solid #e2e8f0;border-radius:6px;padding:8px;font-size:13px;resize:none;height:60px;font-family:inherit;}
.conv-input textarea:focus{outline:none;border-color:#2563eb;}
.conv-input button{padding:0 18px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px;height:60px;}
.conv-input button:hover{background:#1d4ed8;}
/* ── Botón + y burbujas de acciones ── */
.conv-btn-plus{width:40px;height:40px;border-radius:50%;background:#e2e8f0;border:none;font-size:22px;cursor:pointer;color:#475569;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s,transform .2s;}
.conv-btn-plus:hover{background:#cbd5e1;}
.conv-btn-plus.open{background:#2563eb;color:#fff;transform:rotate(45deg);}
.conv-actions-wrap{position:relative;display:flex;align-items:flex-end;}
.conv-bubbles{position:absolute;bottom:52px;left:0;display:flex;flex-direction:column;align-items:center;gap:10px;z-index:200;}
.conv-bubbles.hidden{display:none;}
.conv-bubble-item{display:flex;align-items:center;gap:8px;animation:bubbleIn .15s ease;}
.conv-bubble-item button{width:42px;height:42px;border-radius:50%;border:none;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.18);}
.conv-bubble-item button:hover{filter:brightness(.92);}
.bubble-clip{background:#fff;color:#475569;}
.bubble-emoji{background:var(--c-amber);color:#fff;}
.bubble-loc{background:var(--c-green);color:#fff;}
.conv-bubble-label{font-size:11px;background:rgba(0,0,0,.7);color:#fff;border-radius:4px;padding:2px 7px;white-space:nowrap;pointer-events:none;}
@keyframes bubbleIn{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}
/* ── Emoji panel (arriba derecha del área de input) ── */
.emoji-panel{position:absolute;bottom:calc(100% + 8px);right:0;background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.15);padding:10px;z-index:9999;width:296px;max-height:320px;overflow-y:auto;display:grid;grid-template-columns:repeat(8,1fr);gap:2px;}
.emoji-panel button{background:none;border:none;font-size:20px;cursor:pointer;padding:4px;border-radius:4px;line-height:1;}
.emoji-panel button:hover{background:#f1f5f9;}
.emoji-section-sep{grid-column:1/-1;height:1px;background:#f1f5f9;margin:4px 0;}
.emoji-section-label{grid-column:1/-1;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;padding:2px 0 4px;}
.conv-media-preview{display:flex;align-items:center;gap:10px;padding:8px 10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;color:#475569;}
.conv-media-preview img{height:48px;width:48px;object-fit:cover;border-radius:4px;}
.conv-media-preview .prev-nombre{flex:1;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.conv-media-preview .prev-quitar{background:none;border:none;color:#dc2626;cursor:pointer;font-size:16px;padding:0 4px;}
.conv-vacio{flex:1;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:13px;flex-direction:column;gap:8px;}
.conv-ventana-cerrada{background:#fef3c7;border-top:1px solid #fde68a;padding:10px 14px;font-size:12px;color:#92400e;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
.conv-ventana-cerrada button{background:#d97706;color:#fff;border:none;border-radius:5px;padding:6px 14px;font-size:12px;font-weight:600;cursor:pointer;flex-shrink:0;white-space:nowrap;}
.conv-ventana-cerrada button:hover{background:#b45309;}
@media(max-width:640px){
  .conv-panel{flex-direction:column;height:auto;}
  .conv-lista{width:100%;height:200px;border-right:none;border-bottom:1px solid #e2e8f0;}
  .conv-chat{height:400px;}
  .cmp-metricas{gap:10px;}
}
</style>

<div style="padding:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <h2 style="margin:0;font-size:18px;color:#1e293b;">&#128241; Campa&ntilde;as WhatsApp</h2>
    <?php if ($puedeEnviar): ?>
    <button onclick="window.cmpNuevaCampana()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:9px 16px;font-size:13px;font-weight:600;cursor:pointer;">+ Nueva Campa&ntilde;a</button>
    <?php endif; ?>
  </div>

  <div class="cmp-tabs">
    <button class="cmp-tab active" id="cmpTabBtnConv" onclick="window.cmpTab('conversaciones',this)">Conversaciones <span id="cmpBadgeTot" style="display:none;background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;"></span></button>
    <button class="cmp-tab" id="cmpTabBtnCampanas" onclick="window.cmpTab('campanas',this)">Campa&ntilde;as</button>
  </div>

  <div id="cmpPanelCampanas">
    <div id="cmpListaCampanas"><p style="color:#64748b;font-size:13px;">Cargando...</p></div>
  </div>

  <div id="cmpPanelConversaciones" style="display:none;">
    <div class="conv-panel">
      <div class="conv-lista" id="cmpConvLista"><p style="padding:14px;font-size:12px;color:#64748b;">Cargando...</p></div>
      <div class="conv-chat" id="cmpConvChat">
        <div class="conv-vacio">
          <span style="font-size:32px;">&#128172;</span>
          Selecciona una conversaci&oacute;n
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Wizard Nueva Campaña -->
<div id="cmpModalWizard" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:flex-start;justify-content:center;padding-top:40px;overflow-y:auto;">
  <div style="background:#fff;border-radius:10px;width:700px;max-width:95vw;padding:24px;margin-bottom:40px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="margin:0;font-size:16px;color:#1e293b;">Nueva Campa&ntilde;a WhatsApp</h3>
      <button onclick="window.cmpCerrarWizard()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">&#10005;</button>
    </div>
    <div class="cmp-wizard-steps">
      <div class="cmp-step active" id="cmpStepInd1">1. Segmento</div>
      <div class="cmp-step" id="cmpStepInd2">2. Mensaje</div>
      <div class="cmp-step" id="cmpStepInd3">3. Confirmar</div>
    </div>
    <div id="cmpWizardContenido"></div>
  </div>
</div>

<!-- Modal Detalle Campaña -->
<div id="cmpModalDetalle" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:flex-start;justify-content:center;padding-top:40px;overflow-y:auto;">
  <div style="background:#fff;border-radius:10px;width:720px;max-width:95vw;padding:24px;margin-bottom:40px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
      <h3 style="margin:0;font-size:16px;color:#1e293b;" id="cmpDetalleTitulo">Detalle de campa&ntilde;a</h3>
      <button onclick="window.cmpCerrarDetalle()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">&#10005;</button>
    </div>
    <div id="cmpDetalleAcciones" style="margin-bottom:14px;"></div>
    <div id="cmpDetalleContenido"></div>
  </div>
</div>

<!-- Modal Contacto (editar y pasar prospecto a Cliente) -->
<div id="cmpModalContacto" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1100;align-items:flex-start;justify-content:center;padding-top:60px;overflow-y:auto;">
  <div style="background:#fff;border-radius:10px;width:420px;max-width:92vw;padding:22px;margin-bottom:40px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
      <h3 style="margin:0;font-size:15px;color:#1e293b;">Datos de contacto</h3>
      <button onclick="window.cmpCerrarContacto()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">&#10005;</button>
    </div>
    <div id="cmpContactoError" style="display:none;background:#fee2e2;color:#b91c1c;font-size:12px;padding:8px 10px;border-radius:6px;margin-bottom:12px;"></div>
    <div style="margin-bottom:12px;">
      <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#64748b;margin-bottom:5px;">Tel&eacute;fono (WhatsApp)</label>
      <div id="cmpContactoTel" style="font-size:14px;color:#1e293b;font-weight:600;"></div>
    </div>
    <div style="margin-bottom:12px;">
      <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#64748b;margin-bottom:5px;">Nombre <span style="color:#ef4444">*</span></label>
      <input id="cmpContactoNombre" type="text" style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;" placeholder="Nombre del contacto">
    </div>
    <div style="margin-bottom:12px;">
      <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#64748b;margin-bottom:5px;">Email</label>
      <input id="cmpContactoEmail" type="email" style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;" placeholder="correo@ejemplo.com (opcional)">
    </div>
    <div style="margin-bottom:16px;">
      <label style="display:block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:#64748b;margin-bottom:5px;">Nota</label>
      <textarea id="cmpContactoNota" rows="3" style="width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;" placeholder="Opcional. Se guarda en la bit&aacute;cora del cliente."></textarea>
    </div>
    <div style="display:flex;justify-content:flex-end;gap:10px;">
      <button onclick="window.cmpCerrarContacto()" style="background:none;border:1px solid #e2e8f0;padding:9px 16px;border-radius:8px;font-size:13px;cursor:pointer;color:#64748b;">Cancelar</button>
      <button id="cmpContactoBtnGuardar" onclick="window.cmpGuardarContacto()" style="background:#2563eb;color:#fff;border:none;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Guardar y pasar a Cliente</button>
    </div>
  </div>
</div>

<script>
var ModCampanas = (function() {
    var _step = 1;
    var _fuente = 'clientes';
    var _clientesSeleccionados = [];
    var _templateNombre = '';
    var _templateBody = '';
    var _templateHeaderFormat = '';
    var _templateVars = [];
    var _headerImageUrl = '';
    var _plantillas = [];
    var _nombreCampana = '';
    var _convActiva = null;
    var _convActivaNombre = '';
    var _pollTimer = null;
    var _tabActual = 'campanas';
    var _convTelMap = {};
    var _contactoConvId = null;

    // ── Escape XSS ────────────────────────────────────────────
    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ── Formatea a "XX XXXX XXXX" tomando los últimos 10 dígitos ──
    function fmtTel10(tel) {
        var d = String(tel || '').replace(/\D/g, '').substr(-10);
        if (d.length !== 10) return tel || '';
        return d.substr(0, 2) + ' ' + d.substr(2, 4) + ' ' + d.substr(6, 4);
    }

    function fmtFecha(s) {
        if (!s) return '-';
        return s.replace('T',' ').substring(0,16);
    }

    // ── Normalizar teléfono (JS) ──────────────────────────────
    function normTel(tel) {
        tel = String(tel).replace(/\D/g, '');
        if (tel.length === 10) return '52' + tel;
        if (tel.length === 12 && tel.substring(0,2) === '52') return tel;
        return '52' + tel.substring(tel.length - 10);
    }

    // ── Tabs ──────────────────────────────────────────────────
    function tab(cual, btn) {
        _tabActual = cual;
        document.querySelectorAll('.cmp-tab').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('cmpPanelCampanas').style.display      = cual === 'campanas' ? '' : 'none';
        document.getElementById('cmpPanelConversaciones').style.display = cual === 'conversaciones' ? '' : 'none';
        if (cual === 'conversaciones') { cargarConversaciones(); }
        else { cargarCampanas(); }
    }

    // ── Lista campañas ────────────────────────────────────────
    function cargarCampanas() {
        fetch('/produccion/api/campanas.php?accion=listar')
          .then(function(r) { return r.json(); })
          .then(function(data) {
            var html = '';
            if (!data.campanas || data.campanas.length === 0) {
                html = '<p style="color:#64748b;font-size:13px;">Sin campa&ntilde;as a&uacute;n.</p>';
            } else {
                data.campanas.forEach(function(c) {
                    var tot  = parseInt(c.total_destinatarios) || 0;
                    var env  = parseInt(c.enviados)   || 0;
                    var ent  = parseInt(c.entregados) || 0;
                    var lei  = parseInt(c.leidos)     || 0;
                    var res  = parseInt(c.respuestas) || 0;
                    var pEnv = tot  > 0 ? Math.round(env / tot  * 100) : 0;
                    var pEnt = env  > 0 ? Math.round(ent / env  * 100) : 0;
                    var pLei = env  > 0 ? Math.round(lei / env  * 100) : 0;
                    var pRes = env  > 0 ? Math.round(res / env  * 100) : 0;
                    html += '<div class="cmp-card">' +
                        '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">' +
                        '<div>' +
                        '<strong style="font-size:14px;">' + esc(c.nombre) + '</strong>' +
                        '<div style="font-size:11px;color:#94a3b8;margin-top:2px;">' + fmtFecha(c.created_at) + ' &middot; <code>' + esc(c.template_nombre) + '</code></div>' +
                        '</div>' +
                        '<span class="cmp-badge ' + esc(c.estado) + '">' + esc(c.estado).toUpperCase() + '</span>' +
                        '</div>' +
                        '<div style="margin:12px 0 4px;display:grid;grid-template-columns:repeat(4,1fr);gap:8px;text-align:center;">' +
                        '<div style="background:#f8fafc;border-radius:6px;padding:8px 4px;">' +
                            '<div style="font-size:18px;font-weight:700;color:#334155;">' + env + '</div>' +
                            '<div style="font-size:10px;color:#64748b;margin-top:2px;">Enviados</div>' +
                            '<div style="font-size:11px;color:#94a3b8;">' + pEnv + '% de ' + tot + '</div>' +
                        '</div>' +
                        '<div style="background:#f0fdf4;border-radius:6px;padding:8px 4px;">' +
                            '<div style="font-size:18px;font-weight:700;color:#16a34a;">' + ent + '</div>' +
                            '<div style="font-size:10px;color:#64748b;margin-top:2px;">Entregados</div>' +
                            '<div style="font-size:11px;color:#94a3b8;">' + pEnt + '% env.</div>' +
                        '</div>' +
                        '<div style="background:#f5f3ff;border-radius:6px;padding:8px 4px;">' +
                            '<div style="font-size:18px;font-weight:700;color:#7c3aed;">' + lei + '</div>' +
                            '<div style="font-size:10px;color:#64748b;margin-top:2px;">Le&iacute;dos</div>' +
                            '<div style="font-size:11px;color:#94a3b8;">' + pLei + '% env.</div>' +
                        '</div>' +
                        '<div style="background:#fff7ed;border-radius:6px;padding:8px 4px;">' +
                            '<div style="font-size:18px;font-weight:700;color:#ea580c;">' + res + '</div>' +
                            '<div style="font-size:10px;color:#64748b;margin-top:2px;">Respuestas</div>' +
                            '<div style="font-size:11px;color:#94a3b8;">' + pRes + '% env.</div>' +
                        '</div>' +
                        '</div>' +
                        '<div style="background:#f1f5f9;border-radius:4px;overflow:hidden;height:6px;margin-bottom:12px;">' +
                            (lei > 0  ? '<div style="height:6px;width:' + pLei + '%;background:#7c3aed;display:inline-block;"></div>' : '') +
                            (ent > lei ? '<div style="height:6px;width:' + Math.round((ent-lei)/env*100) + '%;background:#16a34a;display:inline-block;"></div>' : '') +
                        '</div>' +
                        '<button onclick="window.cmpVerDetalle(' + c.id + ')" style="font-size:12px;padding:5px 14px;border:1px solid #e2e8f0;border-radius:5px;background:#fff;cursor:pointer;">Ver detalle</button>' +
                        '</div>';
                });
            }
            document.getElementById('cmpListaCampanas').innerHTML = html;
          })
          .catch(function() {
            document.getElementById('cmpListaCampanas').innerHTML = '<p style="color:#dc2626;font-size:13px;">Error al cargar campa&ntilde;as.</p>';
          });
    }

    // ── Detalle campaña ───────────────────────────────────────
    function verDetalle(id) {
        document.getElementById('cmpModalDetalle').style.display = 'flex';
        document.getElementById('cmpDetalleContenido').innerHTML = '<p style="color:#64748b;font-size:13px;">Cargando...</p>';
        fetch('/produccion/api/campanas.php?accion=detalle&id=' + parseInt(id))
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (data.error) {
                document.getElementById('cmpDetalleContenido').innerHTML = '<p style="color:#dc2626;">' + esc(data.error) + '</p>';
                return;
            }
            var c = data.campana;
            var filas = '';
            (data.envios || []).forEach(function(e) {
                var estadoColor = {enviado:'#2563eb',entregado:'#16a34a',leido:'#7c3aed',fallido:'#dc2626',pendiente:'#64748b'};
                filas += '<tr>' +
                    '<td style="padding:7px 10px;font-size:12px;">' + esc(e.nombre_cliente || '-') + '</td>' +
                    '<td style="padding:7px 10px;font-size:12px;color:#2563eb;font-weight:600;">' + esc(e.nombre_envio || '-') + '</td>' +
                    '<td style="padding:7px 10px;font-size:12px;">' + esc(e.telefono) + '</td>' +
                    '<td style="padding:7px 10px;"><span class="cmp-badge" style="background:' + (estadoColor[e.estado] || '#64748b') + '20;color:' + (estadoColor[e.estado] || '#64748b') + ';">' + esc(e.estado) + '</span></td>' +
                    '<td style="padding:7px 10px;font-size:11px;color:#64748b;">' + fmtFecha(e.enviado_at) + '</td>' +
                    '</tr>';
            });
            document.getElementById('cmpDetalleTitulo').textContent = c.nombre;
            var accionesEl = document.getElementById('cmpDetalleAcciones');
            if (accionesEl) {
                if (c.estado === 'borrador' || c.estado === 'cancelada') {
                    accionesEl.innerHTML = '<button id="cmpBtnEnviarExistente" onclick="window.cmpEnviarCampanaExistente(' + c.id + ', ' + c.total_destinatarios + ')" ' +
                        'style="background:#16a34a;color:#fff;border:none;border-radius:6px;padding:9px 18px;font-size:13px;font-weight:600;cursor:pointer;">' +
                        '&#128241; Enviar campa&ntilde;a (' + c.total_destinatarios + ' destinatarios)</button>';
                } else {
                    accionesEl.innerHTML = '';
                }
            }
            document.getElementById('cmpDetalleContenido').innerHTML =
                '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">' +
                '<thead><tr style="background:#f8fafc;">' +
                '<th style="padding:8px 10px;text-align:left;font-size:12px;">Cliente</th>' +
                '<th style="padding:8px 10px;text-align:left;font-size:12px;">Se enviar&aacute; como</th>' +
                '<th style="padding:8px 10px;text-align:left;font-size:12px;">Tel&eacute;fono</th>' +
                '<th style="padding:8px 10px;text-align:left;font-size:12px;">Estado</th>' +
                '<th style="padding:8px 10px;text-align:left;font-size:12px;">Enviado</th>' +
                '</tr></thead><tbody>' + filas + '</tbody></table></div>';
          });
    }

    function cerrarDetalle() {
        document.getElementById('cmpModalDetalle').style.display = 'none';
    }

    // ── Enviar una campaña ya creada (estado borrador/cancelada) ──
    function enviarCampanaExistente(campanaId, total) {
        if (!confirm('Esto enviará el mensaje de WhatsApp real a ' + total + ' destinatarios. ¿Confirmas?')) {
            return;
        }
        var btn = document.getElementById('cmpBtnEnviarExistente');
        if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
        fetch('/produccion/api/campanas.php?accion=enviar', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': (window._csrfToken || '')},
            body: JSON.stringify({campana_id: campanaId})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                if (btn) { btn.textContent = 'Enviando en proceso ✓'; }
                alert('Envío iniciado: ' + data.total + ' mensajes en proceso (1 por segundo). Cierra y vuelve a abrir el detalle en unos minutos para ver el avance.');
                verDetalle(campanaId);
            } else {
                alert('Error: ' + (data.error || 'desconocido'));
                if (btn) { btn.disabled = false; btn.textContent = '📱 Enviar campaña (' + total + ' destinatarios)'; }
            }
        })
        .catch(function() {
            alert('Error de red al iniciar el envío');
            if (btn) { btn.disabled = false; btn.textContent = '📱 Enviar campaña (' + total + ' destinatarios)'; }
        });
    }

    // ── Wizard Nueva Campaña ──────────────────────────────────
    function nuevaCampana() {
        _step = 1;
        _fuente = 'clientes';
        _clientesSeleccionados = [];
        _templateNombre = '';
        _templateBody = '';
        _templateHeaderFormat = '';
        _headerImageUrl = '';
        _templateVars = [];
        _nombreCampana = '';
        document.getElementById('cmpModalWizard').style.display = 'flex';
        renderStep();
    }

    function cerrarWizard() {
        document.getElementById('cmpModalWizard').style.display = 'none';
        clearInterval(_pollTimer);
    }

    function actualizarIndicadores() {
        [1,2,3].forEach(function(n) {
            var el = document.getElementById('cmpStepInd' + n);
            el.className = 'cmp-step' + (n < _step ? ' done' : '') + (n === _step ? ' active' : '');
        });
    }

    function renderStep() {
        actualizarIndicadores();
        var cont = document.getElementById('cmpWizardContenido');

        if (_step === 1) {
            cont.innerHTML =
                '<div style="margin-bottom:14px;">' +
                '<label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">Nombre de la campa&ntilde;a *</label>' +
                '<input id="cmpNombre" type="text" placeholder="Ej: Promo Julio 2026" maxlength="200" ' +
                'style="width:100%;box-sizing:border-box;padding:9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">' +
                '</div>' +
                '<div style="margin-bottom:14px;">' +
                '<label style="font-size:12px;font-weight:600;display:block;margin-bottom:6px;">Audiencia</label>' +
                '<div style="display:flex;gap:0;border:1px solid #e2e8f0;border-radius:6px;overflow:hidden;width:fit-content;">' +
                '<button id="cmpBtnFuenteClientes" onclick="window.cmpCambiarFuente(\'clientes\')" ' +
                'style="padding:7px 16px;font-size:12px;font-weight:600;border:none;cursor:pointer;background:' + (_fuente==='clientes'?'#2563eb':'#fff') + ';color:' + (_fuente==='clientes'?'#fff':'#64748b') + ';">Clientes CRM</button>' +
                '<button id="cmpBtnFuenteProspectos" onclick="window.cmpCambiarFuente(\'prospectos\')" ' +
                'style="padding:7px 16px;font-size:12px;font-weight:600;border:none;cursor:pointer;border-left:1px solid #e2e8f0;background:' + (_fuente==='prospectos'?'#2563eb':'#fff') + ';color:' + (_fuente==='prospectos'?'#fff':'#64748b') + ';">Prospectos (2,365)</button>' +
                '</div></div>' +
                '<div id="cmpFiltrosArea"></div>' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">' +
                '<span id="cmpContador" style="font-size:13px;font-weight:600;color:#2563eb;">Cargando...</span>' +
                '<label style="font-size:12px;cursor:pointer;"><input type="checkbox" id="cmpChkAll" onchange="window.cmpToggleTodos()"> Seleccionar todos</label>' +
                '</div>' +
                '<div class="cmp-clientes-tabla" id="cmpTablaWrap">' +
                '<table><thead><tr id="cmpTablaHead">' +
                '<th style="width:36px;"><input type="checkbox" id="cmpChkAllTh" onchange="window.cmpToggleTodos()"></th>' +
                '<th>Cliente</th><th>Tel&eacute;fono</th><th>Ciudad</th>' +
                '</tr></thead><tbody id="cmpTablaBody"><tr><td colspan="4" style="padding:12px;color:#64748b;">Cargando...</td></tr></tbody></table>' +
                '</div>' +
                '<div style="text-align:right;margin-top:16px;">' +
                '<button onclick="window.cmpSiguiente()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:9px 22px;font-size:13px;font-weight:600;cursor:pointer;">Siguiente &#8594;</button>' +
                '</div>';
            renderFiltros();
            cargarAudiencia();

        } else if (_step === 2) {
            cont.innerHTML =
                '<div style="margin-bottom:14px;">' +
                '<label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px;">Plantilla aprobada *</label>' +
                '<select id="cmpSelectTemplate" style="width:100%;padding:9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;background:#fff;">' +
                '<option value="">-- Cargando plantillas... --</option>' +
                '</select>' +
                '</div>' +
                '<div id="cmpBodyPlantilla" style="display:none;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:#166534;white-space:pre-wrap;line-height:1.6;">' +
                '</div>' +
                '<div id="cmpHeaderImgSection" style="display:none;margin-bottom:14px;">' +
                '<label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">URL de imagen del encabezado *</label>' +
                '<input id="cmpHeaderImgUrl" type="url" placeholder="https://tu-servidor.com/imagen.jpg" maxlength="500" ' +
                'style="width:100%;box-sizing:border-box;padding:9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">' +
                '<p style="font-size:11px;color:#64748b;margin:5px 0 0;">La imagen debe ser p&uacute;blica (URL accesible desde internet). Formatos: JPG, PNG. M&iacute;n. 300px ancho.</p>' +
                '</div>' +
                '<div id="cmpVarsSection" style="display:none;margin-bottom:14px;">' +
                '<label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">Variables del mensaje</label>' +
                '<p style="font-size:11px;color:#64748b;margin:0 0 8px;">Selecciona qu&eacute; dato se env&iacute;a en cada posici&oacute;n &mdash; se consulta de BD al momento de enviar.</p>' +
                '<div id="cmpVarsLista"></div>' +
                '</div>' +
                '<div id="cmpPreviewArea"></div>' +
                '<div style="display:flex;justify-content:space-between;margin-top:18px;">' +
                '<button onclick="window.cmpAnterior()" style="background:#f1f5f9;color:#1e293b;border:none;border-radius:6px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;">&#8592; Atr&aacute;s</button>' +
                '<button onclick="window.cmpSiguiente()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:9px 22px;font-size:13px;font-weight:600;cursor:pointer;">Siguiente &#8594;</button>' +
                '</div>';

            // Asignar evento al select sin inline handler
            var selEl = document.getElementById('cmpSelectTemplate');
            if (selEl) {
                selEl.addEventListener('change', function() { window.cmpSeleccionarTemplate(this.value); });
            }

            if (_plantillas.length > 0) {
                renderPlantillasSelect();
            } else {
                cargarPlantillas();
            }

        } else if (_step === 3) {
            var filas = '';
            var muestra = _clientesSeleccionados.slice(0, 50);
            muestra.forEach(function(c) {
                filas += '<tr>' +
                    '<td style="padding:6px 10px;font-size:12px;">' + esc(c.nombre) + '</td>' +
                    '<td style="padding:6px 10px;font-size:12px;">' + esc(c.telefono) + '</td>' +
                    '<td style="padding:6px 10px;font-size:11px;color:#64748b;">' + esc(construirPreview(nombreEnvioPreview(c))) + '</td>' +
                    '</tr>';
            });
            if (_clientesSeleccionados.length > 50) {
                filas += '<tr><td colspan="3" style="padding:6px 10px;font-size:11px;color:#94a3b8;">...y ' + (_clientesSeleccionados.length - 50) + ' m&aacute;s</td></tr>';
            }
            cont.innerHTML =
                '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin-bottom:14px;">' +
                '<strong style="font-size:13px;">' + esc(_nombreCampana) + '</strong>' +
                '<div style="font-size:12px;color:#64748b;margin-top:4px;">' +
                '<strong>' + _clientesSeleccionados.length + '</strong> destinatarios &middot; Template: <code>' + esc(_templateNombre) + '</code>' +
                '</div></div>' +
                '<div style="max-height:260px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:6px;margin-bottom:16px;">' +
                '<table style="width:100%;border-collapse:collapse;">' +
                '<thead><tr style="background:#f8fafc;">' +
                '<th style="padding:8px 10px;font-size:12px;text-align:left;">Cliente</th>' +
                '<th style="padding:8px 10px;font-size:12px;text-align:left;">Tel&eacute;fono</th>' +
                '<th style="padding:8px 10px;font-size:12px;text-align:left;">Mensaje (preview)</th>' +
                '</tr></thead><tbody>' + filas + '</tbody></table></div>' +
                '<div id="cmpProgresoArea" style="display:none;">' +
                '<div class="cmp-progreso"><div class="cmp-progreso-bar" id="cmpBarraProgreso" style="width:0%"></div></div>' +
                '<p id="cmpProgresoTxt" style="font-size:12px;color:#64748b;text-align:center;margin:0;"></p>' +
                '</div>' +
                '<div style="display:flex;justify-content:space-between;margin-top:4px;">' +
                '<button onclick="window.cmpAnterior()" id="cmpBtnAtras" style="background:#f1f5f9;color:#1e293b;border:none;border-radius:6px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;">&#8592; Atr&aacute;s</button>' +
                '<button onclick="window.cmpEnviarCampana()" id="cmpBtnEnviar" style="background:#16a34a;color:#fff;border:none;border-radius:6px;padding:9px 22px;font-size:13px;font-weight:600;cursor:pointer;">&#128241; Enviar campa&ntilde;a</button>' +
                '</div>';
        }
    }

    var _varOpciones = [
        ['', '— elige una variable —'],
        ['{{nombre_cliente}}',  '{{nombre_cliente}} — Nombre del cliente'],
        ['{{nombre_asesor}}',   '{{nombre_asesor}} — Asesor que lo atendió'],
        ['{{num_ordenes}}',     '{{num_ordenes}} — Total de órdenes'],
        ['{{num_cotizaciones}}','{{num_cotizaciones}} — Núm. de cotizaciones'],
        ['{{monto_cotizado}}',  '{{monto_cotizado}} — Monto total cotizado'],
        ['{{codigo_portal}}',   '{{codigo_portal}} — Código CTN'],
        ['{{punto}}',           '{{punto}} — Un punto (.)'],
    ];

    function renderVars() {
        var el = document.getElementById('cmpVarsLista');
        if (!el) return;
        el.innerHTML = '';
        _templateVars.forEach(function(v, i) {
            var wrap = document.createElement('div');
            wrap.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:6px;';

            var lbl = document.createElement('span');
            lbl.style.cssText = 'font-size:12px;color:#94a3b8;width:32px;flex-shrink:0;';
            lbl.textContent = '{{' + (i + 1) + '}}';

            var sel = document.createElement('select');
            sel.style.cssText = 'flex:1;padding:7px;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;background:#fff;';
            for (var oi = 0; oi < _varOpciones.length; oi++) {
                var opt = document.createElement('option');
                opt.value       = _varOpciones[oi][0];
                opt.textContent = _varOpciones[oi][1];
                if (_varOpciones[oi][0] === v) { opt.selected = true; }
                sel.appendChild(opt);
            }
            (function(idx) {
                sel.addEventListener('change', function() { actualizarVar(idx, this.value); });
            })(i);

            var btn = document.createElement('button');
            btn.title = 'Eliminar';
            btn.style.cssText = 'background:none;border:none;color:#dc2626;cursor:pointer;font-size:16px;padding:2px 6px;';
            btn.innerHTML = '&#10005;';
            (function(idx) {
                btn.addEventListener('click', function() { eliminarVar(idx); });
            })(i);

            wrap.appendChild(lbl);
            wrap.appendChild(sel);
            wrap.appendChild(btn);
            el.appendChild(wrap);
        });
        actualizarPreview();
    }

    // ── Plantillas Meta ───────────────────────────────────────
    function cargarPlantillas() {
        var sel = document.getElementById('cmpSelectTemplate');
        if (sel) sel.innerHTML = '<option value="">Cargando plantillas...</option>';
        fetch('/produccion/api/campanas.php?accion=listar_plantillas')
          .then(function(r) { return r.json(); })
          .then(function(data) {
            if (data.error) {
                if (sel) sel.innerHTML = '<option value="">Error: ' + esc(data.error) + '</option>';
                return;
            }
            _plantillas = data.plantillas || [];
            renderPlantillasSelect();
          })
          .catch(function() {
            if (sel) sel.innerHTML = '<option value="">Error de red al cargar plantillas</option>';
          });
    }

    function renderPlantillasSelect() {
        var sel = document.getElementById('cmpSelectTemplate');
        if (!sel) return;
        var html = '<option value="">-- Selecciona una plantilla --</option>';
        _plantillas.forEach(function(p) {
            var sel2 = p.name === _templateNombre ? ' selected' : '';
            html += '<option value="' + esc(p.name) + '"' + sel2 + '>' + esc(p.name) + ' &mdash; ' + esc(p.category) + '</option>';
        });
        sel.innerHTML = html;
        if (_templateNombre) { seleccionarTemplate(_templateNombre); }
    }

    function seleccionarTemplate(nombre) {
        _templateNombre = nombre;
        var plantilla = null;
        for (var i = 0; i < _plantillas.length; i++) {
            if (_plantillas[i].name === nombre) { plantilla = _plantillas[i]; break; }
        }
        var bodyEl    = document.getElementById('cmpBodyPlantilla');
        var varsSection = document.getElementById('cmpVarsSection');

        if (!plantilla || !nombre) {
            _templateBody = '';
            if (bodyEl)      { bodyEl.style.display = 'none'; bodyEl.textContent = ''; }
            if (varsSection) { varsSection.style.display = 'none'; }
            return;
        }
        _templateBody        = plantilla.body || '';
        _templateHeaderFormat = plantilla.header_format || '';
        if (bodyEl) { bodyEl.style.display = ''; bodyEl.textContent = _templateBody; }

        // Campo imagen si el header es IMAGE
        var imgSection = document.getElementById('cmpHeaderImgSection');
        if (imgSection) {
            imgSection.style.display = _templateHeaderFormat === 'IMAGE' ? '' : 'none';
            // Auto-rellenar con imagen de ejemplo de Meta si existe
            var imgInput = document.getElementById('cmpHeaderImgUrl');
            if (imgInput && _templateHeaderFormat === 'IMAGE') {
                if (!imgInput.value) {
                    imgInput.value = plantilla.header_example || '';
                }
            }
        }

        // Detectar cuántas variables {{N}} tiene el body
        var matches  = _templateBody.match(/\{\{\d+\}\}/g) || [];
        var maxVar = 0;
        for (var j = 0; j < matches.length; j++) {
            var n = parseInt(matches[j].replace(/\D/g, ''));
            if (n > maxVar) maxVar = n;
        }

        // Ajustar array de vars solo si cambia la cantidad
        if (_templateVars.length !== maxVar) {
            _templateVars = [];
            for (var k = 0; k < maxVar; k++) { _templateVars.push(''); }
        }

        if (varsSection) { varsSection.style.display = maxVar > 0 ? '' : 'none'; }
        if (maxVar > 0)  { renderVars(); }
        actualizarPreview();
    }

    function construirPreview(nombreCliente) {
        if (!_templateBody) return _templateNombre ? 'Template: ' + _templateNombre : '(selecciona una plantilla)';
        var texto = _templateBody;
        for (var i = 0; i < _templateVars.length; i++) {
            var v = _templateVars[i];
            var val = v === '{{nombre_cliente}}'   ? (nombreCliente || 'Cliente') :
                      v === '{{punto}}'             ? '.' :
                      v === '{{nombre_asesor}}'     ? 'Cynthia' :
                      v === '{{num_ordenes}}'       ? '3' :
                      v === '{{num_cotizaciones}}'  ? '2' :
                      v === '{{monto_cotizado}}'    ? '$12,500' :
                      v === '{{codigo_portal}}'     ? 'CTN-171' :
                      (v || '...');
            var token = '{{' + (i + 1) + '}}';
            while (texto.indexOf(token) !== -1) {
                texto = texto.replace(token, val);
            }
        }
        return texto;
    }

    function actualizarPreview() {
        var area = document.getElementById('cmpPreviewArea');
        if (!area || !_templateNombre) return;
        var nombre = nombreEnvioPreview(_clientesSeleccionados[0] || {nombre: 'Ramón'});
        area.innerHTML =
            '<label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">Vista previa (primer cliente):</label>' +
            '<div class="cmp-preview" style="white-space:pre-wrap;">' + esc(construirPreview(nombre)) + '</div>';
    }

    // ── Cambiar fuente audiencia ──────────────────────────────
    function cambiarFuente(nueva) {
        _fuente = nueva;
        _clientesSeleccionados = [];
        var btnC = document.getElementById('cmpBtnFuenteClientes');
        var btnP = document.getElementById('cmpBtnFuenteProspectos');
        if (btnC) { btnC.style.background = (nueva === 'clientes') ? '#2563eb' : '#fff'; btnC.style.color = (nueva === 'clientes') ? '#fff' : '#64748b'; }
        if (btnP) { btnP.style.background = (nueva === 'prospectos') ? '#2563eb' : '#fff'; btnP.style.color = (nueva === 'prospectos') ? '#fff' : '#64748b'; }
        renderFiltros();
        cargarAudiencia();
    }

    function renderFiltros() {
        var area = document.getElementById('cmpFiltrosArea');
        if (!area) return;
        var headEl = document.getElementById('cmpTablaHead');
        if (_fuente === 'clientes') {
            area.innerHTML =
                '<div style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap;">' +
                '<div style="flex:1;min-width:140px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Localidad</label>' +
                '<select id="cmpLocalidad" ' +
                'style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">' +
                '<option value="">Todos</option><option value="LOCAL">Local (MTY)</option><option value="FORANEO">For&aacute;neo</option>' +
                '</select></div>' +
                '<div style="flex:1;min-width:140px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Ciudad</label>' +
                '<input id="cmpCiudad" type="text" placeholder="Filtrar ciudad..." maxlength="100" ' +
                'style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;"></div>' +
                '</div>';
            var locEl = document.getElementById('cmpLocalidad');
            var cidEl = document.getElementById('cmpCiudad');
            if (locEl) { locEl.addEventListener('change', cargarAudiencia); }
            if (cidEl) { cidEl.addEventListener('input',  cargarAudiencia); }
            if (headEl) { headEl.innerHTML = '<th style="width:36px;"><input type="checkbox" id="cmpChkAllTh" onchange="window.cmpToggleTodos()"></th><th>Cliente</th><th>Tel&eacute;fono</th><th>Ciudad</th>'; }
        } else {
            area.innerHTML =
                '<div style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap;align-items:flex-end;">' +
                '<div style="flex:1;min-width:160px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Estado / Zona</label>' +
                '<select id="cmpEstadoPr" style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">' +
                '<option value="">Todos los estados</option>' +
                '<option value="Nuevo León">Nuevo León (1,899)</option>' +
                '<option value="Coahuila">Coahuila (282)</option>' +
                '<option value="CENTRO">CENTRO (91)</option>' +
                '<option value="Tamaulipas">Tamaulipas (45)</option>' +
                '<option value="NORTE">NORTE (28)</option>' +
                '<option value="SUR">SUR (10)</option>' +
                '<option value="LADA NO UBICADA">LADA NO UBICADA (10)</option>' +
                '</select></div>' +
                '<div style="flex:1;min-width:200px;padding-bottom:2px;">' +
                '<label style="font-size:12px;cursor:pointer;display:flex;align-items:center;gap:6px;">' +
                '<input type="checkbox" id="cmpExcluirClientes" checked> ' +
                '<span>Excluir quienes ya son clientes</span></label></div>' +
                '</div>';
            var estEl = document.getElementById('cmpEstadoPr');
            var excEl = document.getElementById('cmpExcluirClientes');
            if (estEl) { estEl.addEventListener('change', cargarAudiencia); }
            if (excEl) { excEl.addEventListener('change', cargarAudiencia); }
            if (headEl) { headEl.innerHTML = '<th style="width:36px;"><input type="checkbox" id="cmpChkAllTh" onchange="window.cmpToggleTodos()"></th><th>Nombre</th><th>Tel&eacute;fono</th><th>Estado</th>'; }
        }
    }

    function cargarAudiencia() {
        if (_fuente === 'clientes') { cmpFiltrarClientes(); }
        else                        { cmpFiltrarProspectos(); }
    }

    // ── Filtrar/cargar prospectos ─────────────────────────────
    var _prospectosMap = {};

    function cmpFiltrarProspectos() {
        var estado  = (document.getElementById('cmpEstadoPr') || {}).value || '';
        var excl    = (document.getElementById('cmpExcluirClientes') || {}).checked ? 1 : 0;
        var url     = '/produccion/api/campanas.php?accion=prospectos_segmento&excluir_clientes=' + excl;
        if (estado) { url += '&estado=' + encodeURIComponent(estado); }

        fetch(url)
          .then(function(r) { return r.json(); })
          .then(function(data) {
            var lista = data.prospectos || [];
            _prospectosMap = {};
            var filas = '';
            lista.forEach(function(p) {
                _prospectosMap[p.id] = {id: p.id, nombre: p.nombre || 'Sin nombre', telefono: '52' + p.telefono};
                var sel = _clientesSeleccionados.some(function(x) { return x.id === p.id; });
                filas += '<tr>' +
                    '<td style="padding:6px 10px;"><input type="checkbox" data-id="' + parseInt(p.id) + '" ' + (sel ? 'checked' : '') + '></td>' +
                    '<td style="padding:6px 10px;">' + esc(p.nombre || 'Sin nombre') + '</td>' +
                    '<td style="padding:6px 10px;">' + esc(p.telefono) + '</td>' +
                    '<td style="padding:6px 10px;">' + esc(p.estado || '') + '</td>' +
                    '</tr>';
            });
            var body = document.getElementById('cmpTablaBody');
            if (body) {
                body.innerHTML = filas || '<tr><td colspan="4" style="padding:12px;color:#64748b;font-size:12px;">Sin resultados</td></tr>';
                body.addEventListener('change', function(e) {
                    if (e.target.type === 'checkbox' && e.target.dataset.id) {
                        var id  = parseInt(e.target.dataset.id);
                        var obj = _prospectosMap[id];
                        if (obj) { toggleCliente(id, obj.nombre, obj.telefono, e.target.checked); }
                    }
                });
            }
            var cnt = document.getElementById('cmpContador');
            if (cnt) { cnt.textContent = _clientesSeleccionados.length + ' seleccionados de ' + lista.length + ' prospectos'; }
          });
    }

    // ── Filtrar/cargar clientes ───────────────────────────────
    // Mapa id→objeto cliente para event delegation (evita JS en atributos HTML)
    var _clientesMap = {};

    function cmpFiltrarClientes() {
        var loc    = (document.getElementById('cmpLocalidad') || {}).value || '';
        var ciudad = (document.getElementById('cmpCiudad')    || {}).value || '';
        var url    = '/produccion/api/campanas.php?accion=clientes_segmento&activos=1';
        if (loc)    { url += '&localidad=' + encodeURIComponent(loc); }
        if (ciudad) { url += '&ciudad='    + encodeURIComponent(ciudad); }

        fetch(url)
          .then(function(r) { return r.json(); })
          .then(function(data) {
            var clientes = data.clientes || [];
            _clientesMap = {};
            var filas = '';
            clientes.forEach(function(c) {
                // Guardar objeto completo en mapa — nunca interpolar en JS inline
                _clientesMap[c.id] = {id: c.id, nombre: c.nombre, telefono: normTel(c.telefono || ''), contacto: c.contacto || ''};
                var seleccionado = _clientesSeleccionados.some(function(x) { return x.id === c.id; });
                // data-id es entero seguro; no hay datos de usuario en atributos JS
                filas += '<tr>' +
                    '<td style="padding:6px 10px;"><input type="checkbox" data-id="' + parseInt(c.id) + '" ' + (seleccionado ? 'checked' : '') + '></td>' +
                    '<td style="padding:6px 10px;">' + esc(c.nombre) + '</td>' +
                    '<td style="padding:6px 10px;">' + esc(c.telefono || '') + '</td>' +
                    '<td style="padding:6px 10px;">' + esc(c.ciudad || '') + '</td>' +
                    '</tr>';
            });
            var body = document.getElementById('cmpTablaBody');
            if (body) {
                body.innerHTML = filas || '<tr><td colspan="4" style="padding:12px;color:#64748b;font-size:12px;">Sin resultados</td></tr>';
                // Event delegation: un solo listener en tbody
                body.addEventListener('change', function(e) {
                    if (e.target.type === 'checkbox' && e.target.dataset.id) {
                        var id  = parseInt(e.target.dataset.id);
                        var obj = _clientesMap[id];
                        if (obj) { toggleCliente(id, obj.nombre, obj.telefono, e.target.checked, obj.contacto); }
                    }
                });
            }
            var cnt = document.getElementById('cmpContador');
            if (cnt) { cnt.textContent = _clientesSeleccionados.length + ' seleccionados de ' + clientes.length; }
          });
    }

    function toggleCliente(id, nombre, tel, checked, contacto) {
        if (checked) {
            if (!_clientesSeleccionados.some(function(x) { return x.id === id; })) {
                _clientesSeleccionados.push({id: id, nombre: nombre, telefono: tel, contacto: contacto || ''});
            }
        } else {
            _clientesSeleccionados = _clientesSeleccionados.filter(function(x) { return x.id !== id; });
        }
        var cnt = document.getElementById('cmpContador');
        if (cnt) { cnt.textContent = _clientesSeleccionados.length + ' seleccionados'; }
    }

    // Aproxima en el navegador el mismo criterio que api/campanas.php::nombreCampanaCorto()
    // usa al crear la campaña: prioriza el nombre del contacto (persona) sobre la razón
    // social, primeras 2 palabras, Title Case. Solo para la vista previa del wizard —
    // el valor real que se envía siempre se calcula server-side al crear la campaña.
    function nombreEnvioPreview(c) {
        var base = (c && c.contacto && c.contacto.trim()) ? c.contacto : ((c && c.nombre) || '');
        base = base.trim();
        if (!base) { return 'Cliente'; }
        var particulas = ['de', 'del', 'la', 'los', 'las'];
        var palabras = base.split(/\s+/);
        var resultado = [palabras[0]];
        if (palabras.length > 1) {
            if (particulas.indexOf(palabras[1].toLowerCase()) !== -1) {
                var i = 1;
                while (palabras[i] && particulas.indexOf(palabras[i].toLowerCase()) !== -1) {
                    resultado.push(palabras[i]);
                    i++;
                }
                if (palabras[i]) { resultado.push(palabras[i]); }
            } else {
                resultado.push(palabras[1]);
            }
        }
        var nombre = resultado.map(function(w) {
            return w.charAt(0).toUpperCase() + w.slice(1).toLowerCase();
        }).join(' ');
        return nombre.replace(/\b(De|Del|La|Los|Las)\b/g, function(m) {
            return m.toLowerCase();
        });
    }

    function toggleTodos() {
        var chkAll = document.getElementById('cmpChkAll') || document.getElementById('cmpChkAllTh');
        var marcar = chkAll ? chkAll.checked : false;
        var chks = document.querySelectorAll('#cmpTablaBody input[type=checkbox]');
        chks.forEach(function(ch) {
            if (ch.checked !== marcar) { ch.click(); }
        });
    }

    function agregarVar()         { _templateVars.push(''); renderVars(); }
    function actualizarVar(i, v)  { _templateVars[i] = v;  actualizarPreview(); }
    function eliminarVar(i)       { _templateVars.splice(i, 1); renderVars(); }

    // ── Navegación wizard ─────────────────────────────────────
    function siguiente() {
        if (_step === 1) {
            _nombreCampana = ((document.getElementById('cmpNombre') || {}).value || '').trim();
            if (!_nombreCampana) { alert('Ingresa el nombre de la campaña'); return; }
            if (_clientesSeleccionados.length === 0) {
                alert(_fuente === 'prospectos' ? 'Selecciona al menos un prospecto' : 'Selecciona al menos un cliente');
                return;
            }
        } else if (_step === 2) {
            if (!_templateNombre) { alert('Selecciona una plantilla de la lista'); return; }
            _headerImageUrl = ((document.getElementById('cmpHeaderImgUrl') || {}).value || '').trim();
            if (_templateHeaderFormat === 'IMAGE' && !_headerImageUrl) {
                alert('Esta plantilla requiere una imagen de encabezado. Ingresa la URL de la imagen.');
                return;
            }
        }
        if (_step < 3) { _step++; renderStep(); }
    }

    function anterior() { if (_step > 1) { _step--; renderStep(); } }

    // ── Enviar campaña ────────────────────────────────────────
    function enviarCampana() {
        var btnEnviar = document.getElementById('cmpBtnEnviar');
        var btnAtras  = document.getElementById('cmpBtnAtras');
        if (btnEnviar) { btnEnviar.disabled = true; btnEnviar.textContent = 'Guardando...'; }
        if (btnAtras)  { btnAtras.disabled  = true; }

        var ids = _clientesSeleccionados.map(function(c) { return c.id; });
        var payload = {
            nombre:           _nombreCampana,
            template_nombre:  _templateNombre,
            template_vars:    _templateVars,
            header_image_url: _headerImageUrl,
            segmento:         {}
        };
        if (_fuente === 'prospectos') {
            payload.prospecto_ids = ids;
        } else {
            payload.cliente_ids = ids;
        }
        fetch('/produccion/api/campanas.php?accion=crear', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        })
        .then(function(r) {
            if (!r.ok) { return r.text().then(function(t) { throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 200)); }); }
            return r.json();
        })
        .then(function(data) {
            if (!data.ok) {
                alert('Error al crear: ' + (data.error || 'desconocido'));
                if (btnEnviar) { btnEnviar.disabled = false; btnEnviar.textContent = '&#128241; Enviar campaña'; }
                if (btnAtras)  { btnAtras.disabled = false; }
                return;
            }
            var campanaId = data.id;
            var progArea = document.getElementById('cmpProgresoArea');
            if (progArea) { progArea.style.display = ''; }
            if (btnEnviar) { btnEnviar.textContent = 'Enviando...'; }

            // Poll progreso — cierra wizard cuando el backend confirma 'enviada'
            _pollTimer = setInterval(function() {
                fetch('/produccion/api/campanas.php?accion=progreso&id=' + campanaId)
                  .then(function(r) { return r.json(); })
                  .then(function(p) {
                    var pct = p.total > 0 ? Math.round(p.enviados / p.total * 100) : 0;
                    var bar = document.getElementById('cmpBarraProgreso');
                    var txt = document.getElementById('cmpProgresoTxt');
                    if (bar) { bar.style.width = pct + '%'; }
                    if (txt) { txt.textContent = p.enviados + ' / ' + p.total + ' enviados (' + pct + '%)'; }
                    if (p.estado === 'enviada') {
                        clearInterval(_pollTimer);
                        if (btnEnviar) { btnEnviar.textContent = 'Enviado ✓'; }
                        setTimeout(function() { cerrarWizard(); cargarCampanas(); }, 1500);
                    }
                  });
            }, 3000);

            // Disparar envío — el backend responde de inmediato y sigue en background
            fetch('/produccion/api/campanas.php?accion=enviar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({campana_id: campanaId})
            })
            .then(function(r) {
                if (!r.ok) { return r.text().then(function(t) { throw new Error('HTTP ' + r.status + ': ' + t.substring(0, 200)); }); }
                return r.json();
            })
            .then(function(res) {
                if (!res.ok) {
                    clearInterval(_pollTimer);
                    if (btnEnviar) { btnEnviar.textContent = 'Error'; }
                    alert('Error al enviar: ' + (res.error || 'desconocido'));
                }
                // Si ok, el poll detecta 'enviada' y cierra el wizard
            })
            .catch(function(err) {
                clearInterval(_pollTimer);
                if (btnEnviar) { btnEnviar.textContent = 'Error'; }
                alert('Error al conectar con el servidor: ' + (err.message || 'desconocido'));
            });
        })
        .catch(function(err) {
            if (btnEnviar) { btnEnviar.disabled = false; btnEnviar.textContent = '&#128241; Enviar campaña'; }
            if (btnAtras)  { btnAtras.disabled = false; }
            alert('Error al guardar campaña: ' + (err.message || 'desconocido'));
        });
    }

    // ── Conversaciones ────────────────────────────────────────
    var _convTipoMap = {};
    var _convActividadMap = {};

    var _tipoBadge = {
        cliente:     '<span style="font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;background:#dbeafe;color:#1d4ed8;margin-left:6px;vertical-align:middle;">CRM</span>',
        prospecto:   '<span style="font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;background:#ffedd5;color:#c2410c;margin-left:6px;vertical-align:middle;">Prospecto</span>',
        desconocido: '<span style="font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;background:#f1f5f9;color:#64748b;margin-left:6px;vertical-align:middle;">Nuevo</span>'
    };

    function cargarConversaciones(silencioso) {
        var lista = document.getElementById('cmpConvLista');
        if (!lista) return;
        if (!silencioso) {
            lista.innerHTML = '<p style="padding:14px;font-size:12px;color:#64748b;">Cargando...</p>';
        }
        fetch('/produccion/api/campanas.php?accion=conversaciones')
          .then(function(r) { return r.json(); })
          .then(function(data) {
            var lista2 = document.getElementById('cmpConvLista');
            if (!lista2) return;
            var html = '';
            var sinLeerTotal = 0;
            _convTipoMap = {};
            _convActividadMap = {};
            _convTelMap = {};
            (data.conversaciones || []).forEach(function(c) {
                var sl   = parseInt(c.mensajes_sin_leer) || 0;
                var tipo = c.tipo_contacto || 'desconocido';
                _convTipoMap[c.id]      = tipo;
                _convActividadMap[c.id] = c.ultima_actividad || null;
                _convTelMap[c.id]       = c.telefono || '';
                sinLeerTotal += sl;
                var badgeSL   = sl > 0 ? '<span class="conv-badge">' + sl + '</span>' : '';
                var badgeTipo = _tipoBadge[tipo] || '';
                var cid = c.id;
                var cnombre = esc(c.nombre_cliente || c.telefono).replace(/'/g,"&#39;");
                var nombreMostrar = c.nombre_cliente ? esc(c.nombre_cliente) : esc(fmtTel10(c.telefono));
                var telChip = c.nombre_cliente ? ' <span style="font-size:11px;color:#94a3b8;font-weight:500;">' + esc(fmtTel10(c.telefono)) + '</span>' : '';
                var activaCls = (_convActiva === cid) ? ' active' : '';
                html += '<div class="conv-item' + activaCls + '" onclick="window.cmpAbrirConv(' + cid + ',\'' + cnombre + '\')" id="convItem' + cid + '">' +
                    badgeSL +
                    '<div class="conv-item-nombre">' + nombreMostrar + telChip + badgeTipo + '</div>' +
                    '<div class="conv-item-preview">' + esc((c.ultimo_mensaje || 'Sin mensajes').substring(0, 60)) + '</div>' +
                    '<button class="conv-item-menu-btn" onclick="event.stopPropagation();window.cmpMenuConv(event,' + cid + ')" title="Más opciones">&#8942;</button>' +
                    '</div>';
            });
            lista2.innerHTML = html ||
                '<p style="padding:14px;font-size:12px;color:#64748b;">Sin conversaciones a&uacute;n.</p>';
            var badge = document.getElementById('cmpBadgeTot');
            if (badge) {
                badge.textContent  = sinLeerTotal;
                badge.style.display = sinLeerTotal > 0 ? '' : 'none';
            }
          });
    }

    // ── Auto-refresco silencioso del inbox (lista + chat abierto) ──
    // Se auto-detiene solo cuando el módulo ya no está en pantalla (SPA
    // reemplaza el contenido sin limpiar setInterval, ver UPD-312).
    var _pollInboxTimer = null;
    function pollInboxSilencioso() {
        if (!document.getElementById('cmpConvLista')) { clearInterval(_pollInboxTimer); return; }
        if (_tabActual !== 'conversaciones') return;
        cargarConversaciones(true);
        if (_convActiva) { cargarMensajes(_convActiva, true); }
    }

    function abrirConv(convId, nombre) {
        _convActiva      = convId;
        _convActivaNombre = nombre || '';
        document.querySelectorAll('.conv-item').forEach(function(el) { el.classList.remove('active'); });
        var item = document.getElementById('convItem' + convId);
        if (item) { item.classList.add('active'); }

        var tipo      = _convTipoMap[convId] || 'desconocido';
        var badgeTipo = _tipoBadge[tipo] || '';
        var telChip   = ' <span style="font-size:11px;color:#94a3b8;font-weight:500;">' + esc(fmtTel10(_convTelMap[convId])) + '</span>';
        var nombreHeaderHtml = (tipo === 'prospecto' || tipo === 'desconocido')
            ? '<span onclick="window.cmpAbrirContacto(' + convId + ')" style="cursor:pointer;text-decoration:underline;text-decoration-style:dotted;" title="Ver / editar contacto">' + esc(_convActivaNombre) + '</span>'
            : esc(_convActivaNombre);

        // Verificar ventana 24h
        var ultimaActividad = _convActividadMap[convId] || null;
        var ventanaAbierta  = true;
        var fechaLeg        = '';
        if (ultimaActividad) {
            var diffMs = Date.now() - new Date(ultimaActividad.replace(' ', 'T')).getTime();
            ventanaAbierta = diffMs < 24 * 3600 * 1000;
            if (!ventanaAbierta) {
                var d = new Date(ultimaActividad.replace(' ', 'T'));
                fechaLeg = d.toLocaleString('es-MX', {day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'});
            }
        }

        var inputHtml;
        if (!ventanaAbierta) {
            inputHtml =
                '<div class="conv-ventana-cerrada">' +
                '<span>&#128274; Ventana cerrada &middot; &uacute;ltima actividad: ' + fechaLeg + '</span>' +
                '<button id="cmpBtnReabrir" onclick="window.cmpEnviarTemplateInbox()">Reactivar conversaci&oacute;n</button>' +
                '</div>';
        } else {
            inputHtml =
                '<div class="conv-input">' +
                '<div id="cmpMediaPreview" style="display:none;" class="conv-media-preview">' +
                '<img id="cmpMediaThumb" src="" style="display:none;">' +
                '<span class="prev-nombre" id="cmpMediaNombre"></span>' +
                '<button class="prev-quitar" onclick="window.cmpQuitarMedia()">&#x2715;</button>' +
                '</div>' +
                '<div id="cmpReplyBar" class="reply-bar" style="display:none;">' +
                '<span style="font-size:14px;">&#8626;</span>' +
                '<span class="reply-bar-text" id="cmpReplyBarText"></span>' +
                '<button class="reply-bar-close" onclick="window.cmpCancelarReply()" title="Cancelar">&#x2715;</button>' +
                '</div>' +
                '<div class="conv-input-row">' +
                '<input type="file" id="cmpFileInput" accept="image/*,.pdf" style="display:none;" onchange="window.cmpArchivoSeleccionado(this)">' +
                '<div class="conv-actions-wrap">' +
                '<div class="conv-bubbles hidden" id="cmpBubblesMenu">' +
                '<div class="conv-bubble-item"><span class="conv-bubble-label">Ubicaci&oacute;n</span><button class="bubble-loc" onclick="window.cmpEnviarUbicacion()" title="Enviar ubicaci&oacute;n">&#128205;</button></div>' +
                '<div class="conv-bubble-item"><span class="conv-bubble-label">Emoji</span><button class="bubble-emoji" id="cmpBtnEmoji" onclick="window.cmpToggleEmojis()" title="Emojis">&#128512;</button></div>' +
                '<div class="conv-bubble-item"><span class="conv-bubble-label">Archivo</span><button class="bubble-clip" onclick="document.getElementById(\'cmpFileInput\').click();window.cmpCerrarBurbujas();" title="Adjuntar archivo">&#128206;</button></div>' +
                '</div>' +
                '<button class="conv-btn-plus" id="cmpBtnPlus" onclick="window.cmpToggleBurbujas()" title="Acciones">&#43;</button>' +
                '</div>' +
                '<div style="position:relative;flex:1;display:flex;align-items:flex-end;">' +
                '<div id="cmpEmojiPanelWrap" style="position:absolute;bottom:100%;right:0;padding-bottom:8px;display:none;z-index:300;"></div>' +
                '<textarea id="cmpMsgInput" placeholder="Escribe tu respuesta... (Shift+Enter para nueva línea)" maxlength="4096" style="width:100%;"></textarea>' +
                '</div>' +
                '<button onclick="window.cmpEnviarMensaje()">Enviar</button>' +
                '</div>' +
                '</div>';
        }

        var chat = document.getElementById('cmpConvChat');
        chat.innerHTML =
            '<div class="conv-header">' + nombreHeaderHtml + telChip + badgeTipo + '</div>' +
            '<div class="conv-mensajes" id="cmpMsgs"><p style="color:#94a3b8;font-size:12px;text-align:center;">Cargando mensajes...</p></div>' +
            inputHtml;

        if (ventanaAbierta) {
            // Enter para enviar (Shift+Enter = nueva línea)
            var textarea = document.getElementById('cmpMsgInput');
            if (textarea) {
                textarea.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        window.cmpEnviarMensaje();
                    }
                });
                // Pegar imagen desde clipboard
                textarea.addEventListener('paste', function(e) {
                    var items = (e.clipboardData || e.originalEvent.clipboardData).items;
                    for (var i = 0; i < items.length; i++) {
                        if (items[i].type.indexOf('image') !== -1) {
                            e.preventDefault();
                            var blob = items[i].getAsFile();
                            cmpSetMedia(blob, 'imagen_pegada.png');
                            break;
                        }
                    }
                });
                textarea.focus();
            }
        } else {
            // Cargar plantillas para el panel de reapertura
            fetch('/produccion/api/campanas.php?accion=listar_plantillas')
              .then(function(r) { return r.json(); })
              .then(function(data) {
                var sel = document.getElementById('cmpTemplateReabrir');
                if (!sel) return;
                var opts = '<option value="">-- Selecciona un template --</option>';
                (data.plantillas || []).forEach(function(p) {
                    if (p.status === 'APPROVED') {
                        opts += '<option value="' + esc(p.name) + '">' + esc(p.name) + '</option>';
                    }
                });
                sel.innerHTML = opts;
              });
        }

        cargarMensajes(convId);

        // Marcar como leído y refrescar badge sidebar
        fetch('/produccion/api/campanas.php?accion=marcar_leido', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({conversacion_id: convId})
        }).then(function() {
            if (typeof window.actualizarBadgeWA === 'function') window.actualizarBadgeWA();
        });
    }

    function cargarMensajes(convId, preservarScroll) {
        fetch('/produccion/api/campanas.php?accion=mensajes&conversacion_id=' + parseInt(convId))
          .then(function(r) { return r.json(); })
          .then(function(data) {
            var msgs = '';
            (data.mensajes || []).forEach(function(m) {
                var dir  = m.direccion === 'outbound' ? 'out' : 'in';
                var cls  = m.direccion === 'outbound' ? 'msg-out' : 'msg-in';
                var meta = '';
                if (m.direccion === 'outbound' && m.enviado_por) {
                    meta = '<div class="msg-meta">' + esc(m.enviado_por) + ' &middot; ' + fmtFecha(m.created_at) + '</div>';
                } else {
                    meta = '<div class="msg-meta">' + fmtFecha(m.created_at) + '</div>';
                }
                // Burbuja de mensaje citado (reply)
                var quotedHtml = '';
                if (m.reply_preview) {
                    quotedHtml = '<div class="msg-quoted">&#8626; ' + esc(m.reply_preview.substring(0, 120)) + '</div>';
                }
                var contenidoHtml = '';
                if (m.tipo === 'imagen') {
                    if (m.contenido && m.contenido.indexOf('/produccion/') === 0) {
                        contenidoHtml = '<img src="' + esc(m.contenido) + '" style="max-width:220px;max-height:220px;border-radius:6px;display:block;cursor:pointer;" onclick="window.open(this.src,\'_blank\')">';
                    } else {
                        contenidoHtml = '<div style="font-size:11px;color:#94a3b8;">&#128247; Imagen</div>';
                    }
                } else if (m.tipo === 'audio') {
                    if (m.contenido && m.contenido.indexOf('/produccion/') === 0) {
                        contenidoHtml = '<audio controls style="max-width:220px;display:block;margin:2px 0;"><source src="' + esc(m.contenido) + '">Tu navegador no soporta audio.</audio>';
                    } else {
                        contenidoHtml = '<div style="font-size:11px;color:#94a3b8;">&#127908; Nota de voz</div>';
                    }
                } else if (m.tipo === 'video') {
                    if (m.contenido && m.contenido.indexOf('/produccion/') === 0) {
                        contenidoHtml = '<video controls style="max-width:260px;max-height:200px;border-radius:6px;display:block;"><source src="' + esc(m.contenido) + '" type="video/mp4">Tu navegador no soporta video.</video>';
                    } else {
                        contenidoHtml = '<div style="font-size:11px;color:#94a3b8;">&#127916; Video</div>';
                    }
                } else if (m.tipo === 'ubicacion') {
                    // formato: "lat,lng" (inbound) o "lat,lng|nombre" (outbound)
                    var rawUbic = (m.contenido || '');
                    var nombreUbic = '';
                    if (rawUbic.indexOf('|') !== -1) {
                        var partsUbic = rawUbic.split('|');
                        rawUbic    = partsUbic[0];
                        nombreUbic = partsUbic[1] || '';
                    }
                    var coords = rawUbic.split(',');
                    var coordRe = /^-?\d+(\.\d+)?$/;
                    if (coords.length === 2 && coordRe.test(coords[0].trim()) && coordRe.test(coords[1].trim())) {
                        var lat = coords[0].trim();
                        var lng = coords[1].trim();
                        var mapsUrl = 'https://www.google.com/maps?q=' + encodeURIComponent(lat) + ',' + encodeURIComponent(lng);
                        var labelUbic = nombreUbic ? esc(nombreUbic) : (esc(lat) + ', ' + esc(lng));
                        contenidoHtml = '<a href="' + esc(mapsUrl) + '" target="_blank" rel="noopener noreferrer" style="display:block;text-decoration:none;">' +
                            '<div style="font-size:12px;color:#2563eb;padding:6px 0;">&#128205; ' + labelUbic + ' &mdash; Ver en Google Maps</div>' +
                            '</a>';
                    } else {
                        contenidoHtml = '<div style="font-size:11px;color:#94a3b8;">&#128205; Ubicaci&oacute;n</div>';
                    }
                } else if (m.tipo === 'contacto') {
                    var contactosHtml = '';
                    try {
                        var contactos = JSON.parse(m.contenido || '[]');
                        contactos.forEach(function(c) {
                            var tels = (c.telefonos || []).filter(function(t){ return t; }).map(function(t){ return esc(t); }).join('<br>');
                            contactosHtml += '<div style="border:1px solid #e2e8f0;border-radius:8px;padding:8px 10px;margin:2px 0;min-width:180px;">' +
                                '<div style="font-weight:600;font-size:13px;color:#0f172a;">&#128100; ' + esc(c.nombre || 'Contacto') + '</div>' +
                                (tels ? '<div style="font-size:12px;color:#2563eb;margin-top:2px;">' + tels + '</div>' : '') +
                                '</div>';
                        });
                        contenidoHtml = contactosHtml || '<div style="font-size:11px;color:#94a3b8;">&#128100; Contacto</div>';
                    } catch (e) {
                        contenidoHtml = '<div style="font-size:11px;color:#94a3b8;">&#128100; Contacto</div>';
                    }
                } else if (m.tipo === 'documento') {
                    var docParts = (m.contenido || '').split('|');
                    if (docParts.length === 2 && docParts[0].indexOf('/produccion/') === 0) {
                        // Nuevo formato: url|nombre
                        contenidoHtml = '<a href="' + esc(docParts[0]) + '" target="_blank" style="display:flex;align-items:center;gap:6px;color:#2563eb;font-size:13px;text-decoration:none;">&#128196; ' + esc(docParts[1]) + '</a>';
                    } else if (m.contenido && m.contenido.indexOf('/produccion/') === 0) {
                        // URL directa sin nombre
                        contenidoHtml = '<a href="' + esc(m.contenido) + '" target="_blank" style="display:flex;align-items:center;gap:6px;color:#2563eb;font-size:13px;text-decoration:none;">&#128196; Ver documento</a>';
                    } else {
                        // Solo nombre (mensajes viejos sin descarga)
                        contenidoHtml = '<div style="font-size:11px;color:#94a3b8;">&#128196; ' + esc(m.contenido) + '</div>';
                    }
                } else {
                    // Texto: preservar saltos de línea con white-space:pre-wrap (aplicado via CSS en .msg-burbuja)
                    contenidoHtml = esc(m.contenido);
                }
                // Botón reply — solo en mensajes de texto (no audio)
                var waId    = esc(m.wa_message_id || '');
                var preview = esc((m.contenido || '').substring(0, 100));
                var replyBtn = (m.tipo !== 'audio' && waId)
                    ? '<button class="msg-reply-btn" onclick="window.cmpSetReply(\'' + waId + '\',\'' + preview + '\')" title="Responder">&#8626;</button>'
                    : '';
                msgs += '<div class="msg-wrap ' + dir + '">' +
                    '<div class="msg-burbuja ' + cls + '">' + replyBtn + quotedHtml + contenidoHtml + meta + '</div>' +
                    '</div>';
            });
            var msgsEl = document.getElementById('cmpMsgs');
            if (msgsEl) {
                var cercaAbajo = !preservarScroll || (msgsEl.scrollHeight - msgsEl.scrollTop - msgsEl.clientHeight) < 80;
                msgsEl.innerHTML = msgs || '<p style="color:#94a3b8;font-size:12px;text-align:center;">Sin mensajes a&uacute;n.</p>';
                if (cercaAbajo) { msgsEl.scrollTop = msgsEl.scrollHeight; }
            }
            // Mantener foco en el textarea después de recargar mensajes (no en refrescos silenciosos)
            if (!preservarScroll) {
                var ta = document.getElementById('cmpMsgInput');
                if (ta && document.activeElement !== ta) ta.focus();
            }
            if (typeof window.actualizarBadgeWA === 'function') window.actualizarBadgeWA();
          });
    }

    function enviarTemplateInbox() {
        if (!_convActiva) return;
        var btn = document.getElementById('cmpBtnReabrir');
        if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }
        fetch('/produccion/api/campanas.php?accion=template_inbox', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': (window._csrfToken || '')},
            body: JSON.stringify({conversacion_id: _convActiva, template_nombre: 'atencion_apex', nombre_cliente: _convActivaNombre})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                _convActividadMap[_convActiva] = new Date().toISOString().replace('T', ' ').substring(0, 19);
                cargarMensajes(_convActiva);
                var banner = document.querySelector('.conv-ventana-cerrada span');
                if (banner) banner.innerHTML = '&#9989; Mensaje enviado &middot; La ventana se abrir&aacute; cuando el cliente responda.';
                if (btn) { btn.style.display = 'none'; }
            } else {
                alert('Error: ' + (data.error || 'desconocido'));
                if (btn) { btn.disabled = false; btn.textContent = 'Reactivar conversación'; }
            }
        })
        .catch(function() {
            alert('Error de red al enviar template');
            if (btn) { btn.disabled = false; btn.textContent = 'Reactivar conversación'; }
        });
    }

    var _mediaArchivo      = null;
    var _replyToWaId       = null;
    var _replyPreview      = null;
    var _emojiPanelEl      = null;
    var _emojiStorageKey   = 'apex_emoji_recent_<?= (int)$user['id'] ?>';

    function setReply(waId, preview) {
        _replyToWaId  = waId;
        _replyPreview = preview;
        var bar = document.getElementById('cmpReplyBar');
        if (bar) {
            document.getElementById('cmpReplyBarText').textContent = preview;
            bar.style.display = 'flex';
        }
        var ta = document.getElementById('cmpMsgInput');
        if (ta) ta.focus();
    }

    function cancelarReply() {
        _replyToWaId  = null;
        _replyPreview = null;
        var bar = document.getElementById('cmpReplyBar');
        if (bar) bar.style.display = 'none';
    }

    function menuConv(e, convId) {
        // Cerrar menú existente
        var old = document.getElementById('cmpCtxMenu');
        if (old) old.remove();
        var menu = document.createElement('div');
        menu.id = 'cmpCtxMenu';
        menu.className = 'conv-ctx-menu';
        menu.innerHTML = '<button onclick="window.cmpMarcarNoLeido(' + convId + ')">&#128140; Marcar como no leído</button>';
        document.body.appendChild(menu);
        var rect = e.target.getBoundingClientRect();
        menu.style.top  = (rect.bottom + 4) + 'px';
        menu.style.left = Math.max(4, rect.left - menu.offsetWidth + rect.width) + 'px';
        setTimeout(function() {
            document.addEventListener('click', function cerrar() {
                var m = document.getElementById('cmpCtxMenu');
                if (m) m.remove();
                document.removeEventListener('click', cerrar);
            });
        }, 10);
    }

    function marcarNoLeido(convId) {
        var m = document.getElementById('cmpCtxMenu');
        if (m) m.remove();
        fetch('/produccion/api/campanas.php?accion=marcar_no_leido', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': (window._csrfToken || '')},
            body: JSON.stringify({conversacion_id: convId})
        }).then(function() {
            cargarConversaciones();
            if (typeof window.actualizarBadgeWA === 'function') window.actualizarBadgeWA();
        });
    }

    function cmpSetMedia(blob, nombre) {
        _mediaArchivo = blob;
        var preview = document.getElementById('cmpMediaPreview');
        var thumb   = document.getElementById('cmpMediaThumb');
        var nomEl   = document.getElementById('cmpMediaNombre');
        if (!preview) return;
        preview.style.display = 'flex';
        nomEl.textContent = nombre;
        if (blob.type.indexOf('image') !== -1) {
            var reader = new FileReader();
            reader.onload = function(ev) { thumb.src = ev.target.result; thumb.style.display = 'block'; };
            reader.readAsDataURL(blob);
        } else {
            thumb.style.display = 'none';
        }
    }

    function quitarMedia() {
        _mediaArchivo = null;
        var preview = document.getElementById('cmpMediaPreview');
        var thumb   = document.getElementById('cmpMediaThumb');
        var fi      = document.getElementById('cmpFileInput');
        if (preview) preview.style.display = 'none';
        if (thumb)   { thumb.src = ''; thumb.style.display = 'none'; }
        if (fi)      fi.value = '';
    }

    function archivoSeleccionado(input) {
        if (!input.files || !input.files[0]) return;
        var f = input.files[0];
        cmpSetMedia(f, f.name);
    }

    function enviarMensaje() {
        var input = document.getElementById('cmpMsgInput');
        var msg   = (input ? input.value : '').trim();
        if (!_convActiva) return;
        if (!msg && !_mediaArchivo) return;

        // Envío con media
        if (_mediaArchivo) {
            var fd = new FormData();
            fd.append('conversacion_id', _convActiva);
            var nombre = (_mediaArchivo.name && _mediaArchivo.name !== 'blob') ? _mediaArchivo.name : 'imagen_pegada.png';
            fd.append('archivo', _mediaArchivo, nombre);

            if (input) input.disabled = true;
            quitarMedia();

            fetch('/produccion/api/campanas.php?accion=enviar_media', {
                method: 'POST',
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (input) input.disabled = false;
                if (data.ok) {
                    cargarMensajes(_convActiva);
                } else {
                    alert('Error al enviar archivo: ' + (data.error || 'desconocido'));
                }
            })
            .catch(function() {
                if (input) input.disabled = false;
                alert('Error de red al enviar el archivo');
            });
            return;
        }

        // Envío solo texto
        var replyId      = _replyToWaId;
        var replyPreview = _replyPreview;
        input.value    = '';
        input.disabled = true;
        cancelarReply();

        fetch('/produccion/api/campanas.php?accion=responder', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({conversacion_id: _convActiva, mensaje: msg, reply_to_wa_id: replyId || '', reply_preview: replyPreview || ''})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            input.disabled = false;
            input.focus();
            if (data.ok) {
                cargarMensajes(_convActiva);
            } else {
                alert('Error al enviar: ' + (data.error || 'desconocido'));
                input.value = msg;
            }
        })
        .catch(function() {
            input.disabled = false;
            input.focus();
            alert('Error de red al enviar el mensaje');
            input.value = msg;
        });
    }

    // ── Menú burbujas ────────────────────────────────────────
    function toggleBurbujas() {
        var menu = document.getElementById('cmpBubblesMenu');
        var btn  = document.getElementById('cmpBtnPlus');
        if (!menu) return;
        var open = !menu.classList.contains('hidden');
        if (open) {
            menu.classList.add('hidden');
            if (btn) btn.classList.remove('open');
        } else {
            menu.classList.remove('hidden');
            if (btn) btn.classList.add('open');
            // Cerrar al hacer click fuera
            setTimeout(function() {
                document.addEventListener('click', function _cls(e) {
                    var wrap = document.querySelector('.conv-actions-wrap');
                    if (!wrap || !wrap.contains(e.target)) {
                        cerrarBurbujas();
                    }
                    document.removeEventListener('click', _cls);
                });
            }, 0);
        }
    }

    function cerrarBurbujas() {
        var menu = document.getElementById('cmpBubblesMenu');
        var btn  = document.getElementById('cmpBtnPlus');
        if (menu) menu.classList.add('hidden');
        if (btn)  btn.classList.remove('open');
    }

    // ── Emoji picker ─────────────────────────────────────────
    var EMOJIS = [
        // Caras felices
        '😀','😁','😂','🤣','😃','😄','😅','😆','😊','😋','😎','🥰','😍','😘','🤩','😗',
        // Caras neutrales / pensativas
        '🙂','🤔','🤨','😐','😑','😶','🙄','😏','🤗','🥱','😌','😛','😜','😝','🤤','🫠',
        // Caras tristes / negativas
        '😒','😓','😔','😕','🙃','🤑','😲','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳',
        // Caras susto / enfermas
        '🥵','🥶','😱','😨','😰','😟','😧','😦','🤢','🤮','🤧','😷','🤒','🤕','🤠','🥳',
        // Manos y gestos
        '👍','👎','👋','🤙','🤝','👏','🙌','🙏','✊','👊','🤜','🤛','🤞','✌️','🤟','🤘',
        // Más manos
        '👌','🤏','🖐️','✋','👆','👇','👉','👈','☝️','💪','🦾','🫶','🫵','🫷','🫸','🖖',
        // Corazones
        '❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❤️‍🔥','💕','💞','💓','💗','💖',
        // Símbolos frecuentes
        '🔥','⚡','✨','💫','⭐','🌟','💥','✅','❌','❗','❓','💯','🔴','🟢','🟡','🔵',
        // Celebración / premios
        '🎉','🎊','🎈','🎁','🎀','🏆','🥇','🥈','🥉','🎯','🚀','💎','🔑','🗝️','🎖️','🏅',
        // Naturaleza
        '☀️','🌙','🌈','❄️','💧','🌊','🌸','🌺','🌻','🌹','🌷','🌼','💐','🍀','🌿','🌱',
        // Animales
        '🐶','🐱','🐰','🦊','🐻','🐼','🐨','🐯','🦁','🐮','🐷','🐸','🐵','🐔','🐧','🦋',
        // Más animales
        '🐝','🐞','🦎','🐢','🦕','🦓','🦒','🐘','🦏','🦛','🦚','🦜','🦩','🦢','🕊️','🦅',
        // Comida y bebida
        '🍎','🍊','🍋','🍇','🍓','🍑','🍒','🥭','🍕','🍔','🌮','🌯','🍜','🍣','🍩','🎂',
        // Bebidas
        '☕','🍵','🧋','🥤','🍺','🥂','🍷','🍸','🍹','🧃','🥛','🍫','🍬','🍭','🍦','🥧',
        // Deportes
        '⚽','🏀','🏈','⚾','🎾','🏐','🏉','🥊','🎯','🎮','🎲','🎳','🎸','🎹','🥁','🎤',
        // Transporte
        '✈️','🚗','🏎️','🚕','🚙','🚌','🚎','🚓','🚑','🚒','🚀','🛸','🛺','🚲','🛵','🏍️',
        // Lugares
        '🏠','🏢','🏰','🏯','⛩️','⛺','🌆','🌇','🌉','🌃','🌁','🗼','🗽','🗺️','🧭','📍',
        // Objetos cotidianos
        '📱','💻','🖥️','⌨️','📷','📸','📹','📞','☎️','📺','📻','🔋','💡','🔦','📚','📖',
        // Oficina / trabajo
        '📝','✏️','📌','🖊️','📋','📊','📈','📉','🗂️','📁','📂','🖨️','🖱️','💾','💿','📦',
        // Herramientas
        '🔧','🔨','⚙️','🔬','🔭','🧲','🪛','🪚','⚒️','🛠️','🔩','🪝','🧰','🔐','🔒','🔓'
    ];

    function getEmojiRecientes() {
        try { return JSON.parse(localStorage.getItem(_emojiStorageKey) || '[]'); }
        catch(e) { return []; }
    }

    function guardarEmojiReciente(emoji) {
        var lista = getEmojiRecientes().filter(function(e) { return e !== emoji; });
        lista.unshift(emoji);
        if (lista.length > 24) lista = lista.slice(0, 24);
        try { localStorage.setItem(_emojiStorageKey, JSON.stringify(lista)); } catch(e) {}
    }

    function renderEmojiPanel() {
        var wrap = document.getElementById('cmpEmojiPanelWrap');
        if (!wrap) return;
        var panel = document.createElement('div');
        panel.className = 'emoji-panel';
        var html = '';
        var recientes = getEmojiRecientes();
        if (recientes.length > 0) {
            html += '<div class="emoji-section-label">Recientes</div>';
            for (var r = 0; r < recientes.length; r++) {
                html += '<button onclick="window.cmpInsertarEmoji(\'' + recientes[r] + '\')" title="' + recientes[r] + '">' + recientes[r] + '</button>';
            }
            html += '<div class="emoji-section-sep"></div>';
            html += '<div class="emoji-section-label">Todos</div>';
        }
        for (var i = 0; i < EMOJIS.length; i++) {
            if (i > 0 && i % 16 === 0) html += '<div class="emoji-section-sep"></div>';
            html += '<button onclick="window.cmpInsertarEmoji(\'' + EMOJIS[i] + '\')" title="' + EMOJIS[i] + '">' + EMOJIS[i] + '</button>';
        }
        panel.innerHTML = html;
        wrap.innerHTML = '';
        wrap.appendChild(panel);
        wrap.style.display = 'block';
    }

    function toggleEmojis() {
        var wrap = document.getElementById('cmpEmojiPanelWrap');
        if (!wrap) return;
        if (wrap.style.display !== 'none' && wrap.innerHTML !== '') {
            wrap.style.display = 'none';
            wrap.innerHTML = '';
            return;
        }
        renderEmojiPanel();
        cerrarBurbujas();
        setTimeout(function() {
            document.addEventListener('click', function _cls(e) {
                if (!wrap.contains(e.target) && e.target.id !== 'cmpBtnEmoji') {
                    wrap.style.display = 'none';
                    wrap.innerHTML = '';
                    document.removeEventListener('click', _cls);
                }
            });
        }, 0);
    }

    function insertarEmoji(emoji) {
        var ta = document.getElementById('cmpMsgInput');
        if (!ta) return;
        var start = ta.selectionStart;
        var end   = ta.selectionEnd;
        ta.value  = ta.value.substring(0, start) + emoji + ta.value.substring(end);
        ta.selectionStart = ta.selectionEnd = start + emoji.length;
        ta.focus();
        guardarEmojiReciente(emoji);
        // Refrescar el panel para que "Recientes" se actualice en tiempo real
        var wrap = document.getElementById('cmpEmojiPanelWrap');
        if (wrap && wrap.style.display !== 'none') renderEmojiPanel();
    }

    // ── Enviar ubicación (preset: Templadora Noreste) ────────
    function enviarUbicacion() {
        if (!_convActiva) return;
        cerrarBurbujas();
        fetch('/produccion/api/campanas.php?accion=enviar_ubicacion', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({conversacion_id: _convActiva, lat: 25.6930336, lng: -100.4807059, nombre: 'Templadora Noreste S.A. de C.V.'})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                cargarMensajes(_convActiva);
            } else {
                alert('Error al enviar ubicación: ' + (data.error || 'desconocido'));
            }
        })
        .catch(function() { alert('Error de red al enviar la ubicación'); });
    }

    // ── Modal Contacto: editar prospecto/desconocido y pasar a Cliente ──
    function abrirContacto(convId) {
        _contactoConvId = convId;
        var errBox = document.getElementById('cmpContactoError');
        errBox.style.display = 'none';
        errBox.textContent = '';
        document.getElementById('cmpContactoTel').textContent = fmtTel10(_convTelMap[convId]) || '-';
        document.getElementById('cmpContactoNombre').value = (_convActivaNombre || '').toUpperCase();
        document.getElementById('cmpContactoEmail').value = '';
        document.getElementById('cmpContactoNota').value = '';
        var btn = document.getElementById('cmpContactoBtnGuardar');
        btn.disabled = false;
        btn.textContent = 'Guardar y pasar a Cliente';
        document.getElementById('cmpModalContacto').style.display = 'flex';
    }

    function cerrarContacto() {
        document.getElementById('cmpModalContacto').style.display = 'none';
    }

    function mostrarErrorContacto(msg) {
        var errBox = document.getElementById('cmpContactoError');
        errBox.textContent = msg;
        errBox.style.display = 'block';
    }

    function guardarContacto() {
        var convId  = _contactoConvId;
        var nombre  = document.getElementById('cmpContactoNombre').value.trim();
        var email   = document.getElementById('cmpContactoEmail').value.trim();
        var nota    = document.getElementById('cmpContactoNota').value.trim();
        if (!nombre) { mostrarErrorContacto('El nombre es obligatorio'); return; }

        var tel10 = String(_convTelMap[convId] || '').replace(/\D/g, '').substr(-10);
        var btn = document.getElementById('cmpContactoBtnGuardar');
        btn.disabled = true;
        btn.textContent = 'Guardando...';

        fetch('/produccion/api/clientes.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({razon_social: nombre, contacto: nombre, telefono: tel10, email: email, localidad: 'local'})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) { throw new Error(data.error); }
            return fetch('/produccion/api/campanas.php?accion=vincular_cliente_conversacion', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({conversacion_id: convId, cliente_id: data.id, nota: nota})
            })
            .then(function(r) { return r.json(); })
            .then(function(link) {
                if (link.error) {
                    alert('Cliente ' + data.codigo + ' creado, pero no se pudo vincular la conversación: ' + link.error + '. Vincúlalo manualmente desde Clientes.');
                }
                cerrarContacto();
                cargarConversaciones();
                abrirConv(convId, nombre);
            });
        })
        .catch(function(err) {
            mostrarErrorContacto(err.message || 'Error al guardar el contacto');
            btn.disabled = false;
            btn.textContent = 'Guardar y pasar a Cliente';
        });
    }

    // ── Init ──────────────────────────────────────────────────
    function init() {
        tab('conversaciones', document.getElementById('cmpTabBtnConv'));
        _pollInboxTimer = setInterval(pollInboxSilencioso, 15000);
    }

    // ── Exposición global (requerida por SPA) ─────────────────
    window.cmpTab                = tab;
    window.cmpNuevaCampana       = nuevaCampana;
    window.cmpCerrarWizard       = cerrarWizard;
    window.cmpSiguiente          = siguiente;
    window.cmpAnterior           = anterior;
    window.cmpEnviarCampana      = enviarCampana;
    window.cmpVerDetalle         = verDetalle;
    window.cmpCerrarDetalle      = cerrarDetalle;
    window.cmpEnviarCampanaExistente = enviarCampanaExistente;
    window.cmpFiltrarClientes    = cmpFiltrarClientes;
    window.cmpToggleCliente      = toggleCliente;
    window.cmpToggleTodos        = toggleTodos;
    window.cmpCambiarFuente      = cambiarFuente;
    window.cmpAgregarVar         = agregarVar;
    window.cmpActualizarVar      = actualizarVar;
    window.cmpEliminarVar        = eliminarVar;
    window.cmpAbrirConv           = abrirConv;
    window.cmpEnviarMensaje       = enviarMensaje;
    window.cmpSeleccionarTemplate = seleccionarTemplate;
    window.cmpQuitarMedia         = quitarMedia;
    window.cmpArchivoSeleccionado = archivoSeleccionado;
    window.cmpEnviarTemplateInbox = enviarTemplateInbox;
    window.cmpSetReply            = setReply;
    window.cmpCancelarReply       = cancelarReply;
    window.cmpMenuConv            = menuConv;
    window.cmpMarcarNoLeido       = marcarNoLeido;
    window.cmpToggleBurbujas      = toggleBurbujas;
    window.cmpCerrarBurbujas      = cerrarBurbujas;
    window.cmpToggleEmojis        = toggleEmojis;
    window.cmpInsertarEmoji       = insertarEmoji;
    window.cmpEnviarUbicacion     = enviarUbicacion;
    window.cmpAbrirContacto       = abrirContacto;
    window.cmpCerrarContacto      = cerrarContacto;
    window.cmpGuardarContacto     = guardarContacto;

    return { init: init };
})();

ModCampanas.init();
</script>
