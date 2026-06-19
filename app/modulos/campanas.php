<?php
// ============================================================
//  APEX GLASS - Módulo: Campañas WhatsApp
//  Archivo: app/modulos/campanas.php
// ============================================================
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requireSession();
$rol  = $user['rol'];
$puedeEnviar = in_array($rol, ['dir_admin','dueno']);
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
.conv-item{padding:12px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;transition:background .1s;}
.conv-item:hover,.conv-item.active{background:#eff6ff;}
.conv-item-nombre{font-size:13px;font-weight:600;color:#1e293b;}
.conv-item-preview{font-size:11px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:240px;}
.conv-badge{background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;float:right;margin-top:2px;}
.conv-chat{flex:1;display:flex;flex-direction:column;background:#f8fafc;}
.conv-header{padding:12px 16px;background:#fff;border-bottom:1px solid #e2e8f0;font-size:13px;font-weight:600;color:#1e293b;}
.conv-mensajes{flex:1;padding:16px;overflow-y:auto;display:flex;flex-direction:column;gap:8px;}
.msg-burbuja{max-width:75%;padding:10px 14px;border-radius:12px;font-size:13px;line-height:1.5;word-break:break-word;}
.msg-out{background:#dcfce7;align-self:flex-end;border-bottom-right-radius:3px;}
.msg-in{background:#fff;border:1px solid #e2e8f0;align-self:flex-start;border-bottom-left-radius:3px;}
.msg-meta{font-size:10px;color:#94a3b8;margin-top:4px;text-align:right;}
.msg-in .msg-meta{text-align:left;}
.conv-input{border-top:1px solid #e2e8f0;padding:12px;display:flex;gap:8px;background:#fff;}
.conv-input textarea{flex:1;border:1px solid #e2e8f0;border-radius:6px;padding:8px;font-size:13px;resize:none;height:60px;font-family:inherit;}
.conv-input textarea:focus{outline:none;border-color:#2563eb;}
.conv-input button{padding:0 18px;background:#2563eb;color:#fff;border:none;border-radius:6px;font-weight:600;cursor:pointer;font-size:13px;}
.conv-input button:hover{background:#1d4ed8;}
.conv-vacio{flex:1;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:13px;flex-direction:column;gap:8px;}
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
    <button class="cmp-tab active" id="cmpTabBtnCampanas" onclick="window.cmpTab('campanas',this)">Campa&ntilde;as</button>
    <button class="cmp-tab" id="cmpTabBtnConv" onclick="window.cmpTab('conversaciones',this)">Conversaciones <span id="cmpBadgeTot" style="display:none;background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;"></span></button>
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
    <div id="cmpDetalleContenido"></div>
  </div>
</div>

<script>
var ModCampanas = (function() {
    var _step = 1;
    var _clientesSeleccionados = [];
    var _templateNombre = '';
    var _templateVars = [];
    var _nombreCampana = '';
    var _convActiva = null;
    var _convActivaNombre = '';
    var _pollTimer = null;
    var _tabActual = 'campanas';

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
                    html += '<div class="cmp-card">' +
                        '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">' +
                        '<div>' +
                        '<strong style="font-size:14px;">' + esc(c.nombre) + '</strong>' +
                        '<div style="font-size:11px;color:#94a3b8;margin-top:2px;">' + fmtFecha(c.created_at) + ' &middot; Template: <code>' + esc(c.template_nombre) + '</code></div>' +
                        '</div>' +
                        '<span class="cmp-badge ' + esc(c.estado) + '">' + esc(c.estado).toUpperCase() + '</span>' +
                        '</div>' +
                        '<div class="cmp-metricas">' +
                        '<div class="cmp-metrica">&#9993; Enviados: <span>' + c.enviados + '/' + c.total_destinatarios + '</span></div>' +
                        '<div class="cmp-metrica">&#10003;&#10003; Entregados: <span>' + c.entregados + '</span></div>' +
                        '<div class="cmp-metrica">&#128065; Le&iacute;dos: <span>' + c.leidos + '</span></div>' +
                        '<div class="cmp-metrica">&#128172; Respuestas: <span>' + c.respuestas + '</span></div>' +
                        '</div>' +
                        '<div style="margin-top:12px;">' +
                        '<button onclick="window.cmpVerDetalle(' + c.id + ')" style="font-size:12px;padding:5px 14px;border:1px solid #e2e8f0;border-radius:5px;background:#fff;cursor:pointer;">Ver detalle</button>' +
                        '</div>' +
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
                    '<td style="padding:7px 10px;font-size:12px;">' + esc(e.telefono) + '</td>' +
                    '<td style="padding:7px 10px;"><span class="cmp-badge" style="background:' + (estadoColor[e.estado] || '#64748b') + '20;color:' + (estadoColor[e.estado] || '#64748b') + ';">' + esc(e.estado) + '</span></td>' +
                    '<td style="padding:7px 10px;font-size:11px;color:#64748b;">' + fmtFecha(e.enviado_at) + '</td>' +
                    '</tr>';
            });
            document.getElementById('cmpDetalleTitulo').textContent = c.nombre;
            document.getElementById('cmpDetalleContenido').innerHTML =
                '<div style="overflow-x:auto;"><table style="width:100%;border-collapse:collapse;">' +
                '<thead><tr style="background:#f8fafc;">' +
                '<th style="padding:8px 10px;text-align:left;font-size:12px;">Cliente</th>' +
                '<th style="padding:8px 10px;text-align:left;font-size:12px;">Tel&eacute;fono</th>' +
                '<th style="padding:8px 10px;text-align:left;font-size:12px;">Estado</th>' +
                '<th style="padding:8px 10px;text-align:left;font-size:12px;">Enviado</th>' +
                '</tr></thead><tbody>' + filas + '</tbody></table></div>';
          });
    }

    function cerrarDetalle() {
        document.getElementById('cmpModalDetalle').style.display = 'none';
    }

    // ── Wizard Nueva Campaña ──────────────────────────────────
    function nuevaCampana() {
        _step = 1;
        _clientesSeleccionados = [];
        _templateNombre = '';
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
                '<div style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap;">' +
                '<div style="flex:1;min-width:140px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Localidad</label>' +
                '<select id="cmpLocalidad" onchange="window.cmpFiltrarClientes()" ' +
                'style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">' +
                '<option value="">Todos</option><option value="LOCAL">Local (MTY)</option><option value="FORANEO">For&aacute;neo</option>' +
                '</select></div>' +
                '<div style="flex:1;min-width:140px;"><label style="font-size:12px;font-weight:600;display:block;margin-bottom:4px;">Ciudad</label>' +
                '<input id="cmpCiudad" type="text" placeholder="Filtrar ciudad..." maxlength="100" oninput="window.cmpFiltrarClientes()" ' +
                'style="width:100%;box-sizing:border-box;padding:8px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;"></div>' +
                '</div>' +
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">' +
                '<span id="cmpContador" style="font-size:13px;font-weight:600;color:#2563eb;">Cargando clientes...</span>' +
                '<label style="font-size:12px;cursor:pointer;"><input type="checkbox" id="cmpChkAll" onchange="window.cmpToggleTodos()"> Seleccionar todos</label>' +
                '</div>' +
                '<div class="cmp-clientes-tabla">' +
                '<table><thead><tr>' +
                '<th style="width:36px;"><input type="checkbox" id="cmpChkAllTh" onchange="window.cmpToggleTodos()"></th>' +
                '<th>Cliente</th><th>Tel&eacute;fono</th><th>Ciudad</th>' +
                '</tr></thead><tbody id="cmpTablaBody"><tr><td colspan="4" style="padding:12px;color:#64748b;">Cargando...</td></tr></tbody></table>' +
                '</div>' +
                '<div style="text-align:right;margin-top:16px;">' +
                '<button onclick="window.cmpSiguiente()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:9px 22px;font-size:13px;font-weight:600;cursor:pointer;">Siguiente &#8594;</button>' +
                '</div>';
            cmpFiltrarClientes();

        } else if (_step === 2) {
            cont.innerHTML =
                '<div style="margin-bottom:14px;">' +
                '<label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">Nombre del template en Meta *</label>' +
                '<input id="cmpTemplate" type="text" placeholder="Ej: promo_julio_2026" maxlength="100" value="' + esc(_templateNombre) + '" ' +
                'style="width:100%;box-sizing:border-box;padding:9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;">' +
                '<p style="font-size:11px;color:#64748b;margin:5px 0 0;">Debe coincidir exactamente con el nombre aprobado en Meta Business Manager (min&uacute;sculas, sin espacios).</p>' +
                '</div>' +
                '<div style="margin-bottom:14px;">' +
                '<label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px;">Variables del template</label>' +
                '<p style="font-size:11px;color:#64748b;margin:0 0 8px;">Usa <code>{{nombre_cliente}}</code> para personalizar con el nombre del cliente. Agrega las variables en el mismo orden del template aprobado.</p>' +
                '<div id="cmpVarsLista"></div>' +
                '<button onclick="window.cmpAgregarVar()" style="font-size:12px;padding:6px 14px;border:1px solid #2563eb;border-radius:5px;color:#2563eb;background:#fff;cursor:pointer;margin-top:6px;">+ Agregar variable</button>' +
                '</div>' +
                '<div id="cmpPreviewArea"></div>' +
                '<div style="display:flex;justify-content:space-between;margin-top:18px;">' +
                '<button onclick="window.cmpAnterior()" style="background:#f1f5f9;color:#1e293b;border:none;border-radius:6px;padding:9px 20px;font-size:13px;font-weight:600;cursor:pointer;">&#8592; Atr&aacute;s</button>' +
                '<button onclick="window.cmpSiguiente()" style="background:#2563eb;color:#fff;border:none;border-radius:6px;padding:9px 22px;font-size:13px;font-weight:600;cursor:pointer;">Siguiente &#8594;</button>' +
                '</div>';
            if (_templateVars.length > 0) { renderVars(); }
            actualizarPreview();

        } else if (_step === 3) {
            var filas = '';
            var muestra = _clientesSeleccionados.slice(0, 50);
            muestra.forEach(function(c) {
                filas += '<tr>' +
                    '<td style="padding:6px 10px;font-size:12px;">' + esc(c.nombre) + '</td>' +
                    '<td style="padding:6px 10px;font-size:12px;">' + esc(c.telefono) + '</td>' +
                    '<td style="padding:6px 10px;font-size:11px;color:#64748b;">' + esc(construirPreview(c.nombre)) + '</td>' +
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

    function renderVars() {
        var el = document.getElementById('cmpVarsLista');
        if (!el) return;
        el.innerHTML = '';
        _templateVars.forEach(function(v, i) {
            var wrap  = document.createElement('div');
            wrap.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:6px;';

            var lbl = document.createElement('span');
            lbl.style.cssText = 'font-size:12px;color:#94a3b8;width:32px;flex-shrink:0;';
            lbl.textContent = '{{' + (i+1) + '}}';

            var inp = document.createElement('input');
            inp.type = 'text';
            inp.value = v;
            inp.placeholder = '{{nombre_cliente}} o texto fijo';
            inp.maxLength = 200;
            inp.style.cssText = 'flex:1;padding:7px;border:1px solid #e2e8f0;border-radius:5px;font-size:12px;';
            // Closure captura i correctamente — sin datos de usuario en atributos JS
            (function(idx) {
                inp.addEventListener('input', function() { actualizarVar(idx, this.value); });
            })(i);

            var btn = document.createElement('button');
            btn.title = 'Eliminar';
            btn.style.cssText = 'background:none;border:none;color:#dc2626;cursor:pointer;font-size:16px;padding:2px 6px;';
            btn.innerHTML = '&#10005;';
            (function(idx) {
                btn.addEventListener('click', function() { eliminarVar(idx); });
            })(i);

            wrap.appendChild(lbl);
            wrap.appendChild(inp);
            wrap.appendChild(btn);
            el.appendChild(wrap);
        });
        actualizarPreview();
    }

    function construirPreview(nombreCliente) {
        var vars = _templateVars.map(function(v) {
            return v === '{{nombre_cliente}}' ? (nombreCliente || 'Cliente') : v;
        });
        return 'Template: ' + _templateNombre + (vars.length ? ' | ' + vars.join(', ') : '');
    }

    function actualizarPreview() {
        var area = document.getElementById('cmpPreviewArea');
        if (!area || !_templateNombre) return;
        var nombre = (_clientesSeleccionados[0] || {nombre: 'Ramón'}).nombre;
        area.innerHTML =
            '<label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">Preview (primer cliente):</label>' +
            '<div class="cmp-preview">' + esc(construirPreview(nombre)) + '</div>';
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
                _clientesMap[c.id] = {id: c.id, nombre: c.nombre, telefono: normTel(c.telefono || '')};
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
                        if (obj) { toggleCliente(id, obj.nombre, obj.telefono, e.target.checked); }
                    }
                });
            }
            var cnt = document.getElementById('cmpContador');
            if (cnt) { cnt.textContent = _clientesSeleccionados.length + ' seleccionados de ' + clientes.length; }
          });
    }

    function toggleCliente(id, nombre, tel, checked) {
        if (checked) {
            if (!_clientesSeleccionados.some(function(x) { return x.id === id; })) {
                _clientesSeleccionados.push({id: id, nombre: nombre, telefono: tel});
            }
        } else {
            _clientesSeleccionados = _clientesSeleccionados.filter(function(x) { return x.id !== id; });
        }
        var cnt = document.getElementById('cmpContador');
        if (cnt) { cnt.textContent = _clientesSeleccionados.length + ' seleccionados'; }
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
            if (_clientesSeleccionados.length === 0) { alert('Selecciona al menos un cliente'); return; }
        } else if (_step === 2) {
            _templateNombre = ((document.getElementById('cmpTemplate') || {}).value || '').trim();
            if (!_templateNombre) { alert('Ingresa el nombre del template'); return; }
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

        var clienteIds = _clientesSeleccionados.map(function(c) { return c.id; });
        fetch('/produccion/api/campanas.php?accion=crear', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                nombre:           _nombreCampana,
                template_nombre:  _templateNombre,
                template_vars:    _templateVars,
                segmento:         {},
                cliente_ids:      clienteIds
            })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                alert('Error al crear: ' + (data.error || 'desconocido'));
                if (btnEnviar) { btnEnviar.disabled = false; btnEnviar.textContent = '\u{1F4F1} Enviar campaña'; }
                if (btnAtras)  { btnAtras.disabled = false; }
                return;
            }
            var campanaId = data.id;
            var progArea = document.getElementById('cmpProgresoArea');
            if (progArea) { progArea.style.display = ''; }
            if (btnEnviar) { btnEnviar.textContent = 'Enviando...'; }

            // Poll progreso mientras envía
            _pollTimer = setInterval(function() {
                fetch('/produccion/api/campanas.php?accion=progreso&id=' + campanaId)
                  .then(function(r) { return r.json(); })
                  .then(function(p) {
                    var pct = p.total > 0 ? Math.round(p.enviados / p.total * 100) : 0;
                    var bar = document.getElementById('cmpBarraProgreso');
                    var txt = document.getElementById('cmpProgresoTxt');
                    if (bar) { bar.style.width = pct + '%'; }
                    if (txt) { txt.textContent = p.enviados + ' / ' + p.total + ' enviados (' + pct + '%)'; }
                    if (p.estado === 'enviada') { clearInterval(_pollTimer); }
                  });
            }, 3000);

            // Disparar envío (proceso largo — PHP manejará el tiempo)
            fetch('/produccion/api/campanas.php?accion=enviar', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({campana_id: campanaId})
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                clearInterval(_pollTimer);
                if (btnEnviar) { btnEnviar.textContent = res.ok ? 'Enviado ✓' : 'Error'; }
                setTimeout(function() { cerrarWizard(); cargarCampanas(); }, 1500);
            })
            .catch(function() {
                clearInterval(_pollTimer);
                if (btnEnviar) { btnEnviar.textContent = 'Error de red'; }
            });
        });
    }

    // ── Conversaciones ────────────────────────────────────────
    function cargarConversaciones() {
        document.getElementById('cmpConvLista').innerHTML = '<p style="padding:14px;font-size:12px;color:#64748b;">Cargando...</p>';
        fetch('/produccion/api/campanas.php?accion=conversaciones')
          .then(function(r) { return r.json(); })
          .then(function(data) {
            var html = '';
            var sinLeerTotal = 0;
            (data.conversaciones || []).forEach(function(c) {
                var sl = parseInt(c.mensajes_sin_leer) || 0;
                sinLeerTotal += sl;
                var badge = sl > 0 ? '<span class="conv-badge">' + sl + '</span>' : '';
                html += '<div class="conv-item" onclick="window.cmpAbrirConv(' + c.id + ',\'' + esc(c.nombre_cliente || c.telefono).replace(/'/g,"&#39;") + '\')" id="convItem' + c.id + '">' +
                    badge +
                    '<div class="conv-item-nombre">' + esc(c.nombre_cliente || c.telefono) + '</div>' +
                    '<div class="conv-item-preview">' + esc((c.ultimo_mensaje || 'Sin mensajes').substring(0, 60)) + '</div>' +
                    '</div>';
            });
            document.getElementById('cmpConvLista').innerHTML = html ||
                '<p style="padding:14px;font-size:12px;color:#64748b;">Sin conversaciones a&uacute;n.</p>';
            var badge = document.getElementById('cmpBadgeTot');
            if (badge) {
                badge.textContent  = sinLeerTotal;
                badge.style.display = sinLeerTotal > 0 ? '' : 'none';
            }
          });
    }

    function abrirConv(convId, nombre) {
        _convActiva      = convId;
        _convActivaNombre = nombre || '';
        document.querySelectorAll('.conv-item').forEach(function(el) { el.classList.remove('active'); });
        var item = document.getElementById('convItem' + convId);
        if (item) { item.classList.add('active'); }

        var chat = document.getElementById('cmpConvChat');
        chat.innerHTML =
            '<div class="conv-header">' + esc(_convActivaNombre) + '</div>' +
            '<div class="conv-mensajes" id="cmpMsgs"><p style="color:#94a3b8;font-size:12px;text-align:center;">Cargando mensajes...</p></div>' +
            '<div class="conv-input">' +
            '<textarea id="cmpMsgInput" placeholder="Escribe tu respuesta..." maxlength="4096"></textarea>' +
            '<button onclick="window.cmpEnviarMensaje()">Enviar</button>' +
            '</div>';

        // Enter para enviar (Shift+Enter = nueva línea)
        var textarea = document.getElementById('cmpMsgInput');
        if (textarea) {
            textarea.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    window.cmpEnviarMensaje();
                }
            });
        }

        cargarMensajes(convId);

        // Marcar como leído
        fetch('/produccion/api/campanas.php?accion=marcar_leido', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({conversacion_id: convId})
        });
    }

    function cargarMensajes(convId) {
        fetch('/produccion/api/campanas.php?accion=mensajes&conversacion_id=' + parseInt(convId))
          .then(function(r) { return r.json(); })
          .then(function(data) {
            var msgs = '';
            (data.mensajes || []).forEach(function(m) {
                var cls  = m.direccion === 'outbound' ? 'msg-out' : 'msg-in';
                var meta = '';
                if (m.direccion === 'outbound' && m.enviado_por) {
                    meta = '<div class="msg-meta">' + esc(m.enviado_por) + ' &middot; ' + fmtFecha(m.created_at) + '</div>';
                } else {
                    meta = '<div class="msg-meta">' + fmtFecha(m.created_at) + '</div>';
                }
                msgs += '<div class="msg-burbuja ' + cls + '">' + esc(m.contenido) + meta + '</div>';
            });
            var msgsEl = document.getElementById('cmpMsgs');
            if (msgsEl) {
                msgsEl.innerHTML = msgs || '<p style="color:#94a3b8;font-size:12px;text-align:center;">Sin mensajes a&uacute;n.</p>';
                msgsEl.scrollTop = msgsEl.scrollHeight;
            }
          });
    }

    function enviarMensaje() {
        var input = document.getElementById('cmpMsgInput');
        var msg   = (input ? input.value : '').trim();
        if (!msg || !_convActiva) return;

        input.value    = '';
        input.disabled = true;

        fetch('/produccion/api/campanas.php?accion=responder', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({conversacion_id: _convActiva, mensaje: msg})
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            input.disabled = false;
            if (data.ok) {
                cargarMensajes(_convActiva);
            } else {
                alert('Error al enviar: ' + (data.error || 'desconocido'));
                input.value = msg;
            }
        })
        .catch(function() {
            input.disabled = false;
            alert('Error de red al enviar el mensaje');
            input.value = msg;
        });
    }

    // ── Init ──────────────────────────────────────────────────
    function init() {
        cargarCampanas();
    }

    // ── Exposición global (requerida por SPA) ─────────────────
    window.cmpTab             = tab;
    window.cmpNuevaCampana    = nuevaCampana;
    window.cmpCerrarWizard    = cerrarWizard;
    window.cmpSiguiente       = siguiente;
    window.cmpAnterior        = anterior;
    window.cmpEnviarCampana   = enviarCampana;
    window.cmpVerDetalle      = verDetalle;
    window.cmpCerrarDetalle   = cerrarDetalle;
    window.cmpFiltrarClientes = cmpFiltrarClientes;
    window.cmpToggleCliente   = toggleCliente;
    window.cmpToggleTodos     = toggleTodos;
    window.cmpAgregarVar      = agregarVar;
    window.cmpActualizarVar   = actualizarVar;
    window.cmpEliminarVar     = eliminarVar;
    window.cmpAbrirConv       = abrirConv;
    window.cmpEnviarMensaje   = enviarMensaje;

    return { init: init };
})();

ModCampanas.init();
</script>
