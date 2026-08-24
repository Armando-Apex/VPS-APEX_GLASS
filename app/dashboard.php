<?php
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/permisos.php';
$user  = requirePermiso('ver_dashboard');
$_rol  = $user['rol'];
$_name = $user['nombre'];

$esDesarrollo = $_rol === 'desarrollo';
$esDir        = in_array($_rol, ['dueno','dir_admin','director','administracion','desarrollo']);
$esComercial  = in_array($_rol, ['dueno','dir_admin','comercial','administracion','desarrollo']);
$esAdmin      = in_array($_rol, ['dir_admin','desarrollo']);
$esInventario = in_array($_rol, ['dir_admin','administracion','desarrollo']);
$veInventarioStock = $esInventario || $_rol === 'comercial';
$esFinanzas   = in_array($_rol, ['dir_admin','administracion','dueno','desarrollo']);
$esLogistica  = in_array($_rol, ['dir_admin','administracion','dueno','chofer','desarrollo','comercial']);
$esJefe       = in_array($_rol, ['jefe_piso','dir_admin','dueno','director','desarrollo']);
$esArchivos   = in_array($_rol, ['dir_admin','administracion','comercial','desarrollo']);

$ROL_LABELS = [
    'dir_admin'      => 'Director Admin',
    'administracion' => 'Administración',
    'comercial'      => 'Asesor Comercial',
    'jefe_piso'      => 'Jefe de Piso',
    'operador'       => 'Operador',
    'dueno'          => 'Propietario',
    'chofer'         => 'Chofer',
    'director'       => 'Director',
    'desarrollo'     => 'Dev',
];
$_rolLabel = $ROL_LABELS[$_rol] ?? ucfirst(str_replace('_', ' ', $_rol));

require_once __DIR__ . '/../api/helpers/icons.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
<script src="csrf_fetch.js?v=<?= @filemtime(__DIR__.'/csrf_fetch.js') ?: date('Ymd') ?>"></script>
<link rel="icon" type="image/png" href="/favicon/favicon-96x96.png" sizes="96x96">
<link rel="icon" type="image/x-icon" href="/favicon/favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-touch-icon.png">
<link rel="manifest" href="/favicon/site.webmanifest">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APEX GLASS &mdash; Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@700&display=swap" rel="stylesheet">
<style>
:root { --sidebar-w:220px; --topbar-h:56px; --c-bg:#f8fafc; --c-white:#fff; --c-border:#e2e8f0; --c-text:#1e293b; --c-muted:#64748b; --c-blue:#2563eb; --c-blue-dark:#1d4ed8; --c-blue-light:#eff6ff; --c-red:#dc2626; --c-red-light:#fee2e2; --c-green:#16a34a; --c-green-light:#dcfce7; --c-amber:#d97706; --c-amber-light:#fef9c3; --c-dark:#0f172a; --c-dark-2:#1a1a2e; --r-sm:8px; --r-md:12px; --r-lg:16px; --r-pill:99px; }
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;}
/* S3-01 Toast global */
#apex-toast-host{position:fixed;right:20px;bottom:20px;display:flex;flex-direction:column;gap:8px;z-index:9999;}
.apex-toast{background:var(--c-dark);color:#fff;font-size:13px;padding:10px 16px;border-radius:var(--r-sm);box-shadow:0 4px 16px rgba(15,23,42,.25);opacity:0;transform:translateY(8px);transition:all .25s;max-width:340px;}
.apex-toast.show{opacity:1;transform:none;}
.apex-toast-ok{border-left:3px solid var(--c-green);}
.apex-toast-error{background:var(--c-red);}
.apex-toast-warn{background:var(--c-amber);}
/* S3-01 Modal confirmar */
.apex-confirm-overlay{position:fixed;inset:0;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;z-index:9998;}
.apex-confirm{background:var(--c-white);border-radius:var(--r-md);padding:20px 24px;max-width:380px;width:90%;box-shadow:0 12px 40px rgba(15,23,42,.3);}
.apex-confirm-title{font-size:15px;font-weight:700;color:var(--c-text);margin-bottom:8px;}
.apex-confirm-msg{font-size:13px;color:var(--c-muted);margin-bottom:16px;}
.apex-confirm-btns{display:flex;justify-content:flex-end;gap:8px;}
.apex-btn-ghost{padding:8px 14px;border:1px solid var(--c-border);border-radius:var(--r-sm);background:#fff;cursor:pointer;font-size:13px;}
.apex-btn-danger{padding:8px 14px;border:none;border-radius:var(--r-sm);background:var(--c-red);color:#fff;font-weight:600;cursor:pointer;font-size:13px;}
.apex-btn-danger:hover{background:#b91c1c;}
/* S3-07 Estado de error */
.state-error{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;padding:48px 20px;text-align:center;}
.state-error-icon{color:var(--c-red);display:flex;}
.state-error-msg{font-size:13px;color:var(--c-text);font-weight:600;}
.state-error-detail{font-size:12px;color:var(--c-muted);max-width:420px;}
.state-error-retry{padding:8px 16px;border:1px solid var(--c-border);border-radius:var(--r-sm);background:var(--c-white);color:var(--c-blue);font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
.state-error-retry:hover{background:var(--c-blue-light);border-color:var(--c-blue);}
/* S3-08 Estado vacío */
.state-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:48px 20px;text-align:center;color:var(--c-muted);}
.state-empty-icon{color:var(--c-muted);opacity:.6;display:flex;}
.state-empty-txt{font-size:13px;}
/* S3-06 Validación visual */
.campo-error input, .campo-error select, .campo-error textarea,
input.campo-error, select.campo-error{border-color:var(--c-red)!important;background:#fef2f2;}
.campo-error label{color:var(--c-red);}
.campo-error .error-msg{display:block;}
.error-msg{display:none;font-size:11px;color:var(--c-red);margin-top:3px;}
body{font-family:system-ui,-apple-system,sans-serif;background:var(--c-bg);color:var(--c-text);display:flex;flex-direction:column;}
.topbar{height:var(--topbar-h);background:#0f172a;display:flex;align-items:center;padding:0 20px;gap:16px;flex-shrink:0;z-index:100;}
.topbar-logo{font-family:'Syncopate',sans-serif;font-size:16px;font-weight:700;color:white;letter-spacing:2px;cursor:pointer;}
.topbar-sep{width:1px;height:24px;background:#334155;}
.topbar-sub{font-size:11px;color:#64748b;letter-spacing:1px;text-transform:uppercase;}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:14px;}
.topbar-user{font-size:12px;color:var(--c-muted);}
.topbar-rol{font-size:11px;background:#1e3a5f;color:#93c5fd;padding:2px 8px;border-radius:99px;}
#reloj{font-size:12px;color:#64748b;font-variant-numeric:tabular-nums;min-width:70px;}
.topbar-logout{font-size:12px;color:#64748b;text-decoration:none;}
.topbar-logout:hover{color:#cbd5e1;}
.layout{display:flex;flex:1;overflow:hidden;}
.sidebar{width:var(--sidebar-w);background:var(--c-white);border-right:1px solid var(--c-border);display:flex;flex-direction:column;overflow-y:auto;flex-shrink:0;}
.sidebar-section{padding:4px 0;border-bottom:1px solid #f1f5f9;}
.sidebar-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--c-muted);padding:10px 16px 4px;}
.sidebar-link{display:flex;align-items:center;gap:10px;padding:9px 16px;font-size:13px;font-weight:500;color:var(--c-muted);cursor:pointer;transition:all .15s;position:relative;border:none;background:none;width:100%;text-align:left;}
.sidebar-link:hover{background:#f8fafc;color:var(--c-text);}
.sidebar-link.active{background:#eff6ff;color:var(--c-blue);font-weight:600;}
.sidebar-link.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--c-blue);border-radius:0 2px 2px 0;}
.sidebar-icon{width:20px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:inherit;}
.sidebar-link:focus-visible{outline:2px solid var(--c-blue);outline-offset:-2px;border-radius:4px;}
.topbar-hamburger:focus-visible,.notif-btn:focus-visible{outline:2px solid #60a5fa;outline-offset:2px;border-radius:4px;}
/* UX-19: foco visible por defecto para toda la app + módulos SPA (botones, inputs, links, etc. que no definan su propio :focus-visible) */
button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,[tabindex]:focus-visible{outline:2px solid var(--c-blue);outline-offset:2px;border-radius:4px;}
.sidebar-link{cursor:pointer;}
.sidebar{scrollbar-width:thin;scrollbar-color:#e2e8f0 transparent;}
.topbar-logout{padding:8px 10px;min-height:44px;display:flex;align-items:center;}
.sidebar-badge{margin-left:auto;background:var(--c-red);color:white;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;display:none;}
.content-area{flex:1;overflow-y:auto;position:relative;}
#spa-content{min-height:100%;}
.spa-loading{display:none;position:absolute;inset:0;background:rgba(248,250,252,.8);z-index:10;align-items:center;justify-content:center;flex-direction:column;gap:12px;}
.spa-loading.show{display:flex;}
.spa-spinner{width:36px;height:36px;border:3px solid #e2e8f0;border-top-color:var(--c-blue);border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
}
.spa-loading-txt{font-size:13px;color:var(--c-muted);}

/* ── Móvil ────────────────────────────────────────────────────────────────── */
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:var(--c-muted);font-size:22px;padding:4px 6px;line-height:1;flex-shrink:0;}
.topbar-hamburger:hover{color:white;}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:149;}
.sidebar-overlay.open{display:block;}

@media(max-width:768px){
  /* Desbloquear scroll en móvil */
  html,body{height:auto;overflow:auto;}

  /* Topbar compacto */
  .topbar{padding:0 14px;gap:10px;}
  .topbar-sep{display:none;}
  .topbar-sub{display:none;}
  .topbar-hamburger{display:block;}
  #reloj{font-size:11px;letter-spacing:0;}
  .topbar-user{display:none;}
  .topbar-rol{display:none;}
  .topbar-logout{font-size:13px;}

  /* Layout ocupa el resto de la pantalla */
  .layout{
    display:block;
    height:auto;
    min-height:calc(100vh - var(--topbar-h));
    overflow:visible;
  }

  /* Content ocupa todo el ancho */
  .content-area{
    width:100%;
    min-width:0;
    overflow:visible;
    position:static;
  }

  /* Sidebar como drawer */
  .sidebar{
    position:fixed;
    top:var(--topbar-h);
    left:0;
    bottom:0;
    width:260px;
    z-index:150;
    transform:translateX(-100%);
    transition:transform .25s ease;
    box-shadow:4px 0 20px rgba(0,0,0,.15);
  }
  .sidebar.open{transform:translateX(0);}

  /* spa-loading fijo al viewport en móvil */
  .spa-loading{position:fixed;}
}

/* Botón reportar bug/mejora */
.rep-btn{background:none;border:none;cursor:pointer;color:#64748b;padding:4px 6px;line-height:1;transition:color .15s;}
.rep-btn:hover{color:#cbd5e1;}
.rep-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1600;align-items:center;justify-content:center;padding:16px;}
.rep-overlay.open{display:flex;}
.rep-modal{background:#fff;border-radius:14px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden;}
.rep-head{background:#0f172a;color:#fff;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;}
.rep-head h3{font-size:14px;font-weight:700;}
.rep-close{background:none;border:none;color:var(--c-muted);font-size:20px;cursor:pointer;line-height:1;}
.rep-close:hover{color:#fff;}
.rep-body{padding:20px;}
.rep-tipos{display:flex;gap:10px;margin-bottom:16px;}
.rep-tipo-btn{flex:1;border:2px solid #e2e8f0;border-radius:8px;padding:10px 8px;text-align:center;cursor:pointer;font-size:13px;font-weight:600;background:#fff;color:#475569;transition:all .15s;}
.rep-tipo-btn.sel-bug{border-color:#dc2626;background:#fef2f2;color:#dc2626;}
.rep-tipo-btn.sel-mejora{border-color:#2563eb;background:#eff6ff;color:#2563eb;}
.rep-tipo-btn:not(.sel-bug):not(.sel-mejora):hover{border-color:#94a3b8;}
.rep-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;display:block;margin-bottom:6px;}
.rep-textarea{width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;min-height:90px;outline:none;}
.rep-textarea:focus{border-color:#2563eb;}
.rep-foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid #f1f5f9;}
.rep-btn-cancel{background:#f1f5f9;color:#475569;border:1px solid #e2e8f0;padding:8px 16px;border-radius:8px;font-size:13px;cursor:pointer;}
.rep-btn-send{background:#2563eb;color:#fff;border:none;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;}
.rep-btn-send:disabled{opacity:.6;cursor:not-allowed;}
.rep-msg{font-size:12px;font-weight:600;padding:6px 10px;border-radius:6px;display:none;margin-top:10px;}
.rep-msg.ok{background:#dcfce7;color:#16a34a;display:block;}
.rep-msg.err{background:#fee2e2;color:#dc2626;display:block;}
.rep-btn-pick{width:100%;background:#f8fafc;border:1.5px dashed #cbd5e1;border-radius:8px;padding:9px 14px;font-size:12px;color:#64748b;cursor:pointer;text-align:left;}
.rep-btn-pick:hover{border-color:#94a3b8;color:#334155;}
.rep-elem-preview{background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;padding:10px 12px;font-size:12px;}
.rep-elem-row{display:flex;gap:8px;margin-bottom:4px;align-items:baseline;}
.rep-elem-lbl{color:#0284c7;font-weight:700;min-width:64px;font-size:11px;text-transform:uppercase;}
.rep-elem-val{color:#0c4a6e;word-break:break-all;}
.rep-elem-ruta{font-family:monospace;font-size:11px;}
.rep-elem-clear{background:none;border:none;color:var(--c-muted);font-size:11px;cursor:pointer;padding:0;margin-top:4px;}
.rep-elem-clear:hover{color:#dc2626;}
/* Pick mode */
body.rep-pick-mode *{cursor:crosshair !important;}
.rep-pick-highlight{outline:2.5px solid #f97316 !important;outline-offset:2px;background:rgba(249,115,22,.08) !important;}
#rep-pick-banner{display:none;position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;padding:12px 24px;border-radius:99px;font-size:13px;font-weight:600;z-index:9000;align-items:center;gap:16px;box-shadow:0 8px 30px rgba(0,0,0,.4);}
body.rep-pick-mode #rep-pick-banner{display:flex;}
#rep-pick-banner button{background:#f97316;border:none;color:#fff;padding:4px 14px;border-radius:99px;font-size:12px;font-weight:700;cursor:pointer;}

/* Campana notificaciones */
.notif-wrap{position:relative;}
.notif-btn{background:none;border:none;cursor:pointer;color:#64748b;font-size:18px;padding:4px 6px;position:relative;line-height:1;transition:color .15s;}
.notif-btn:hover{color:#cbd5e1;}
.notif-badge{position:absolute;top:-2px;right:-2px;background:var(--c-red);color:white;font-size:10px;font-weight:700;min-width:16px;height:16px;border-radius:99px;display:none;align-items:center;justify-content:center;padding:0 4px;line-height:1;}
.notif-badge.show{display:flex;}
.notif-panel{display:none;position:absolute;top:calc(100% + 8px);right:0;width:340px;background:var(--c-white);border:1px solid var(--c-border);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:200;overflow:hidden;}
.notif-panel.open{display:block;}
.notif-panel-head{padding:12px 16px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;}
.notif-panel-titulo{font-size:13px;font-weight:700;color:var(--c-text);}
.notif-btn-leer-todas{font-size:11px;color:var(--c-muted);background:none;border:none;cursor:pointer;padding:0;}
.notif-btn-leer-todas:hover{color:var(--c-blue);}
#msgPanel{width:420px;max-width:calc(100vw - 24px);}
#msgLista{max-height:75vh;min-height:320px;overflow-y:auto;}
#msgPanel .notif-item-titulo{font-size:14px;}
#msgPanel .notif-item-msg{font-size:13px;}
#msgPanel .msg-item-bubble{font-size:14px;max-width:90%;}
.msg-panel-footer{display:flex;gap:6px;padding:10px 12px;border-top:1px solid #f1f5f9;}
.msg-panel-footer input{flex:1;border:1px solid var(--c-border);border-radius:8px;padding:10px 12px;font-size:14px;font-family:inherit;}
.msg-panel-footer button{background:var(--c-blue);color:#fff;border:none;border-radius:8px;padding:10px 16px;font-size:13px;font-weight:700;cursor:pointer;}
.msg-item{padding:8px 12px;}
.msg-item-bubble{max-width:85%;border-radius:10px;padding:7px 10px;font-size:12.5px;line-height:1.4;margin-bottom:4px;}
.msg-item-bubble.mio{background:var(--c-blue);color:#fff;margin-left:auto;}
.msg-item-bubble.otro{background:#f1f5f9;color:var(--c-text);}
.msg-item-tiempo{font-size:10px;color:var(--c-muted);margin-bottom:8px;}
.msg-item-tiempo.mio{text-align:right;}
.notif-lista{max-height:380px;overflow-y:auto;}
.notif-item{padding:12px 16px;border-bottom:1px solid #f1f5f9;cursor:pointer;transition:background .1s;display:flex;gap:10px;align-items:flex-start;}
.notif-item:hover{background:#f8fafc;}
.notif-item.no-leida{background:rgba(37,99,235,.04);}
.notif-item.no-leida:hover{background:rgba(37,99,235,.08);}
.notif-dot{width:8px;height:8px;border-radius:50%;background:var(--c-blue);flex-shrink:0;margin-top:4px;}
.notif-dot.leida{background:transparent;}
.notif-item-body{flex:1;min-width:0;}
.notif-item-titulo{font-size:13px;font-weight:600;color:var(--c-text);}
.notif-item-msg{font-size:11px;color:var(--c-muted);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.notif-item-tiempo{font-size:10px;color:var(--c-muted);margin-top:3px;}
.notif-empty{padding:32px;text-align:center;color:var(--c-muted);font-size:13px;}
</style>
<script src="utils.js?v=<?= @filemtime(__DIR__.'/utils.js') ?: date('Ymd') ?>"></script>
</head>
<body>

<div class="topbar">
  <button class="topbar-hamburger" onclick="toggleSidebar()" aria-label="Menú"><?= icono('menu', 20) ?></button>
  <div class="topbar-logo" onclick="cargarModulo('resumen')">APEX GLASS</div>
  <div class="topbar-sep"></div>
  <div class="topbar-sub">Producci&oacute;n</div>
  <div class="topbar-right">
    <span id="reloj"></span>
    <button class="rep-btn" onclick="repAbrirModal()" title="Reportar bug o mejora"><?= icono('flag', 18) ?></button>
    <?php if ($esAdmin || $esComercial): ?>
    <div class="notif-wrap" id="notifWrap">
      <button class="notif-btn" onclick="toggleNotifPanel()" title="Notificaciones" aria-label="Notificaciones">
        <?= icono('bell', 18) ?><span class="notif-badge" id="notifBadge"></span>
      </button>
      <div class="notif-panel" id="notifPanel">
        <div class="notif-panel-head">
          <span class="notif-panel-titulo">Notificaciones</span>
          <button class="notif-btn-leer-todas" onclick="leerTodas()">Marcar todas como le&#237;das</button>
        </div>
        <div class="notif-lista" id="notifLista"><div class="notif-empty">Cargando&#8230;</div></div>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($esAdmin || $esComercial): ?>
    <div class="notif-wrap" id="msgWrap">
      <button class="notif-btn" onclick="toggleMsgPanel()" title="Mensajes" aria-label="Mensajes">
        <?= icono('message-square', 18) ?><span class="notif-badge" id="msgBadge"></span>
      </button>
      <div class="notif-panel" id="msgPanel">
        <div class="notif-panel-head">
          <span class="notif-panel-titulo" id="msgPanelTitulo">Mensajes</span>
          <button class="notif-btn-leer-todas" id="msgBtnVolver" onclick="msgVolverLista()" style="display:none;">&larr; Conversaciones</button>
        </div>
        <div class="notif-lista" id="msgLista"><div class="notif-empty">Cargando&#8230;</div></div>
        <div class="msg-panel-footer" id="msgFooter" style="display:none;">
          <input type="text" id="msgInput" placeholder="Escribe un mensaje..." maxlength="2000" autocomplete="off" name="msg-nuevo-<?= bin2hex(random_bytes(4)) ?>" onkeydown="if(event.key==='Enter')msgEnviar()">
          <button onclick="msgEnviar()">Enviar</button>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <span class="topbar-user"><?= htmlspecialchars($_name) ?></span>
    <span class="topbar-rol"><?= htmlspecialchars($_rolLabel) ?></span>
    <a href="../api/logout.php?redirect=login.php" class="topbar-logout" onclick="return confirm('¿Cerrar sesión?')">Salir &rarr;</a>
  </div>
</div>

<div class="layout">
<div class="sidebar-overlay" id="sidebarOverlay" onclick="cerrarSidebar()"></div>
  <nav class="sidebar" id="sidebarNav">
    <div class="sidebar-section">
      <div class="sidebar-label">Producci&oacute;n</div>
      <button class="sidebar-link" data-modulo="resumen" onclick="cargarModulo('resumen')">
        <span class="sidebar-icon"><?= icono('bar-chart-2') ?></span>Resumen
      </button>
      <button class="sidebar-link" data-modulo="ordenes" onclick="cargarModulo('ordenes')">
        <span class="sidebar-icon"><?= icono('clipboard-list') ?></span>&Oacute;rdenes
        <span class="sidebar-badge" id="badge-vencidas">0</span>
      </button>
      <button class="sidebar-link" data-modulo="estaciones" onclick="cargarModulo('estaciones')">
        <span class="sidebar-icon"><?= icono('layers') ?></span>Estaciones
      </button>
      <button class="sidebar-link" data-modulo="retrabajo" onclick="cargarModulo('retrabajo')">
        <span class="sidebar-icon"><?= icono('alert-triangle') ?></span>Retrabajo
      </button>
      <?php if ($esJefe): ?>
      <button class="sidebar-link" data-modulo="omisiones" onclick="cargarModulo('omisiones')">
        <span class="sidebar-icon"><?= icono('ban') ?></span>Omisiones
      </button>
      <?php endif; ?>
    </div>
    <?php if ($esComercial): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Comercial</div>
      <button class="sidebar-link" data-modulo="cotizaciones" onclick="cargarModulo('cotizaciones')">
        <span class="sidebar-icon"><?= icono('file-text') ?></span>Cotizaciones
        <span id="authBadge" style="display:none;background:#d97706;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;margin-left:auto;"></span>
      </button>
      <button class="sidebar-link" data-modulo="clientes" onclick="cargarModulo('clientes')">
        <span class="sidebar-icon"><?= icono('users') ?></span>Clientes
      </button>
      <button class="sidebar-link" data-modulo="cristales" onclick="cargarModulo('cristales')">
        <span class="sidebar-icon"><?= icono('box') ?></span>Cristales
      </button>
      <button class="sidebar-link" data-modulo="optimizador" onclick="cargarModulo('optimizador')">
        <span class="sidebar-icon"><?= icono('scissors') ?></span>Optimizador
      </button>
      <button class="sidebar-link" data-modulo="campanas" onclick="cargarModulo('campanas')">
        <span class="sidebar-icon"><?= icono('message-square') ?></span>Campa&ntilde;as WA
        <span id="waBadge" style="display:none;background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;margin-left:auto;"></span>
      </button>
      <button class="sidebar-link" data-modulo="maquila" onclick="cargarModulo('maquila')">
        <span class="sidebar-icon"><?= icono('scissors') ?></span>Maquila
      </button>
    </div>
    <?php endif; ?>
    <?php if ($esDir): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Reportes</div>
      <button class="sidebar-link" data-modulo="reporte_direccion" onclick="cargarModulo('reporte_direccion')">
        <span class="sidebar-icon"><?= icono('trending-up') ?></span>Direcci&oacute;n
      </button>
      <button class="sidebar-link" data-modulo="productividad" onclick="cargarModulo('productividad')">
        <span class="sidebar-icon"><?= icono('activity') ?></span>Productividad
      </button>
    </div>
    <?php endif; ?>
    <?php if ($esAdmin || tienePermiso($_rol, 'ver_contabilidad')): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Administraci&oacute;n</div>
      <?php if ($esAdmin): ?>
      <button class="sidebar-link" data-modulo="admin_ordenes" onclick="cargarModulo('admin_ordenes')">
        <span class="sidebar-icon"><?= icono('settings') ?></span>Admin &Oacute;rdenes
      </button>
      <button class="sidebar-link" data-modulo="admin_comunicados" onclick="cargarModulo('admin_comunicados')">
        <span class="sidebar-icon"><?= icono('megaphone') ?></span>Admin Comunicados
      </button>
      <button class="sidebar-link" data-modulo="maquila_precios" onclick="cargarModulo('maquila_precios')">
        <span class="sidebar-icon"><?= icono('settings') ?></span>Precios Maquila
      </button>
      <button class="sidebar-link" data-modulo="media_manager" onclick="cargarModulo('media_manager')">
        <span class="sidebar-icon"><?= icono('package') ?></span>Archivos de Video
      </button>
      <?php endif; ?>
      <?php if ($esDesarrollo || $esAdmin): ?>
      <button class="sidebar-link" data-modulo="reportes" onclick="cargarModulo('reportes')">
        <span class="sidebar-icon"><?= icono('flag') ?></span>Reportes
        <span id="reportesBadge" style="display:none;background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:99px;margin-left:auto;"></span>
      </button>
      <?php endif; ?>
      <?php if (tienePermiso($_rol, 'ver_contabilidad')): ?>
      <button class="sidebar-link" data-modulo="bitacora_desechos" onclick="cargarModulo('bitacora_desechos')">
        <span class="sidebar-icon"><?= icono('truck') ?></span>Bit&aacute;cora de Desechos
      </button>
      <button class="sidebar-link" data-modulo="bono_corte" onclick="cargarModulo('bono_corte')">
        <span class="sidebar-icon"><?= icono('trending-up') ?></span>Bono Corte
      </button>
      <button class="sidebar-link" data-modulo="comisiones" onclick="cargarModulo('comisiones')">
        <span class="sidebar-icon"><?= icono('credit-card') ?></span>Comisiones
      </button>
      <?php endif; ?>
      <?php if ($esDesarrollo): ?>
      <a class="sidebar-link" href="operador.php" target="_blank" style="text-decoration:none">
        <span class="sidebar-icon"><?= icono('settings') ?></span>Vista Operador
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($veInventarioStock): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Inventario</div>
      <button class="sidebar-link" data-modulo="inventario" onclick="cargarModulo('inventario')">
        <span class="sidebar-icon"><?= icono('package') ?></span>Inventario
      </button>
      <?php if ($esInventario): ?>
      <button class="sidebar-link" data-modulo="compras" onclick="cargarModulo('compras')">
        <span class="sidebar-icon"><?= icono('shopping-cart') ?></span>Compras
        <?php if ($esAdmin): ?><span id="badge-compras-envio" style="display:none;background:#7c3aed;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;margin-left:4px"></span><?php endif; ?>
      </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($esFinanzas): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Finanzas</div>
      <button class="sidebar-link" data-modulo="finanzas_vobo" onclick="cargarModulo('finanzas_vobo')">
        <span class="sidebar-icon"><?= icono('check-square') ?></span>VoBo &Oacute;rdenes
      </button>
      <button class="sidebar-link" data-modulo="finanzas_cobranza" onclick="cargarModulo('finanzas_cobranza')">
        <span class="sidebar-icon"><?= icono('credit-card') ?></span>Cobranza
      </button>
      <?php if ($esDesarrollo || $esAdmin): ?>
      <button class="sidebar-link" data-modulo="facturacion" onclick="cargarModulo('facturacion')">
        <span class="sidebar-icon"><?= icono('file-text') ?></span>Facturación <span style="font-size:10px;background:#f59e0b;color:#000;padding:1px 5px;border-radius:99px;margin-left:4px">WIP</span>
      </button>
      <?php endif; ?>
      <?php if (tienePermiso($_rol, 'ver_contabilidad')): ?>
      <button class="sidebar-link" data-modulo="contabilidad" onclick="cargarModulo('contabilidad')">
        <span class="sidebar-icon"><?= icono('layers') ?></span>Contabilidad<?php if ($_rol === 'desarrollo'): ?> <span style="font-size:10px;background:#f59e0b;color:#000;padding:1px 5px;border-radius:99px;margin-left:4px">WIP</span><?php endif; ?>
      </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($esLogistica): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Log&iacute;stica</div>
      <button class="sidebar-link" data-modulo="logistica_rutas" onclick="cargarModulo('logistica_rutas')">
        <span class="sidebar-icon"><?= icono('truck') ?></span>Rutas de Entrega
      </button>
      <?php if ($_rol === 'chofer'): ?>
      <button class="sidebar-link" data-modulo="chofer_ruta" onclick="cargarModulo('chofer_ruta')">
        <span class="sidebar-icon"><?= icono('map-pin') ?></span>Mi Ruta
      </button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </nav>

  <div class="content-area">
    <div class="spa-loading" id="spa-loading">
      <div class="spa-spinner"></div>
      <div class="spa-loading-txt">Cargando&#8230;</div>
    </div>
    <div id="spa-content"></div>
  </div>
</div>

<script>
const SPA_USER = {
  rol:    '<?= htmlspecialchars($_rol,  ENT_QUOTES) ?>',
  nombre: '<?= htmlspecialchars($_name, ENT_QUOTES) ?>',
  soloMio: <?= ($_rol === 'comercial') ? 'true' : 'false' ?>,
};

(function reloj() {
  const el = document.getElementById('reloj');
  function tick() {
    var opts = window.innerWidth <= 768
      ? {hour:'2-digit',minute:'2-digit'}
      : {hour:'2-digit',minute:'2-digit',second:'2-digit'};
    el.textContent = new Date().toLocaleTimeString('es-MX', opts);
  }
  tick(); window.setInterval(tick, 1000);
})();

const MODULOS = {
  resumen:'modulos/resumen.php', ordenes:'modulos/ordenes.php',
  estaciones:'modulos/estaciones.php', retrabajo:'modulos/retrabajo.php',
  cotizaciones:'modulos/cotizaciones.php', clientes:'modulos/clientes.php',
  cristales:'modulos/cristales.php', optimizador:'modulos/optimizador.php',
  reporte_direccion:'modulos/reporte_direccion.php', productividad:'modulos/productividad.php',
  admin_ordenes:'modulos/admin_ordenes.php', admin_comunicados:'modulos/admin_comunicados.php', reportes:'modulos/reportes.php',
  bitacora_desechos:'modulos/bitacora_desechos.php',
  inventario:'modulos/inventario.php', compras:'modulos/compras.php',
  finanzas_vobo:'modulos/finanzas_vobo.php',
  finanzas_cobranza:'modulos/finanzas_cobranza.php',
  facturacion:'modulos/facturacion.php',
  contabilidad:'modulos/contabilidad.php',
  bono_corte:'modulos/bono_corte.php',
  comisiones:'modulos/comisiones.php',
  logistica_rutas:'modulos/logistica_rutas.php', chofer_ruta:'modulos/chofer_ruta.php',
  omisiones:'modulos/omisiones.php',
  campanas:'modulos/campanas.php',
  orden:'modulos/orden.php', cotizacion:'modulos/cotizacion.php',
  maquila:'modulos/maquila.php',
  maquila_nueva:'modulos/maquila.php?vista=nueva',
  maquila_detalle:'modulos/maquila.php?vista=detalle',
  maquila_precios:'modulos/maquila_precios.php',
  media_manager:'modulos/media_manager.php',
};

let _moduloActivo = null;
let _spaTimers    = [];
let _spaScripts   = [];

const _siOrig = window.setInterval;
window.setInterval = function(fn, ms) {
  const id = _siOrig.call(window, fn, ms);
  _spaTimers.push(id);
  return id;
};

async function cargarModulo(nombre, params = {}) {
  const archivo = MODULOS[nombre];
  if (!archivo) return;
  if (_moduloActivo === nombre && !Object.keys(params).length) return;

  document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
  const lnk = document.querySelector(`[data-modulo="${nombre}"]`);
  if (lnk) lnk.classList.add('active');

  _spaTimers.forEach(id => clearInterval(id));  _spaTimers = [];
  document.querySelectorAll('script[data-spa-mod]').forEach(s => s.remove());
  _spaScripts = [];

  // Cerrar y eliminar cualquier modal/overlay que haya quedado abierto del módulo anterior
  ['corrModal','archModal','catModal','modalRechazoCalidad'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el && el.parentNode) el.parentNode.removeChild(el);
  });
  document.querySelectorAll('.motivo-overlay').forEach(function(el) {
    if (el.parentNode) el.parentNode.removeChild(el);
  });

  document.getElementById('spa-content').innerHTML = '';
  document.getElementById('spa-loading').classList.add('show');

  const qs  = new URLSearchParams(params).toString();
  const sep = archivo.indexOf('?') === -1 ? '?' : '&';
  const url = archivo + (qs ? sep + qs : '');

  try {
    const res = await fetch(url, { headers: { 'X-SPA-Request': '1' } });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const html = new TextDecoder('utf-8').decode(await res.arrayBuffer());

    const cont = document.getElementById('spa-content');
    const tmp  = document.createElement('div');
    tmp.innerHTML = html;
    const scripts = Array.from(tmp.querySelectorAll('script'));
    scripts.forEach(s => s.remove());
    cont.innerHTML = tmp.innerHTML;

    for (const s of scripts) {
      if (s.src) {
        const ns = document.createElement('script');
        ns.src = s.src;
        ns.setAttribute('data-spa-mod', nombre);
        document.head.appendChild(ns);
        _spaScripts.push(ns);
        await new Promise(r => { ns.onload = r; ns.onerror = r; });
      } else {
        let code = s.textContent
          .replace(/\bconst\s+(?!ModResumen|ModOrdenes|ModEstaciones|ModCotizaciones|ModClientes|ModCristales|ModProductividad|ModReporte|ModAdminOrdenes|ModAdminComunicados|ModInventario|ModCompras|ModRetrabajo|ModCotizacion|ModFinanzasVobo|ModFinanzasCobranza|ModArchivos|ModFacturacion|LR|CR\b)/g, 'var ')
          .replace(/\blet\s+/g, 'var ');
        const ns = document.createElement('script');
        ns.textContent = code;
        ns.setAttribute('data-spa-mod', nombre);
        document.head.appendChild(ns);
        _spaScripts.push(ns);
      }
    }

    _moduloActivo = nombre;
    const st = qs ? '?m='+nombre+'&'+qs : '?m='+nombre;
    history.pushState({ modulo: nombre, params }, '', st);
    document.querySelector('.content-area').scrollTop = 0;

  } catch(e) {
    document.getElementById('spa-content').innerHTML =
      `<div style="padding:40px;text-align:center">
        <div style="color:#dc2626;margin-bottom:12px"><?= icono('alert-triangle', 32) ?></div>
        <div style="font-size:15px;font-weight:600;color:#dc2626">Error al cargar m&#243;dulo</div>
        <div style="font-size:13px;color:#64748b;margin-top:6px">${e.message}</div>
        <button onclick="cargarModulo('${nombre}')" style="margin-top:16px;padding:8px 20px;background:#2563eb;color:white;border:none;border-radius:8px;cursor:pointer">Reintentar</button>
      </div>`;
  } finally {
    document.getElementById('spa-loading').classList.remove('show');
  }
}

window.addEventListener('popstate', e => {
  if (e.state?.modulo) cargarModulo(e.state.modulo, e.state.params || {});
});
window.irA = (mod, p={}) => cargarModulo(mod, p);
window.actualizarBadge = function(venc) {
  const b = document.getElementById('badge-vencidas');
  if (!b || venc === undefined) return;
  b.textContent = venc; b.style.display = venc > 0 ? '' : 'none';
};

const _up = new URLSearchParams(location.search);
const _m  = (_up.get('m') || 'resumen').split('?')[0];
const _p  = {}; _up.forEach((v,k) => { if (k !== 'm') _p[k] = v; });
cargarModulo(_m, _p);

<?php if ($esAdmin || $esComercial): ?>
// ── Notificaciones ────────────────────────────────────────────────────────────
let _notifData = [];

async function cargarNotificaciones() {
  try {
    const r = await fetch('../api/notificaciones.php?accion=listar&t=' + Date.now());
    const d = await r.json();
    if (!d.ok) return;
    _notifData = d.notificaciones || [];
    const badge = document.getElementById('notifBadge');
    if (d.no_leidas > 0) {
      badge.textContent = d.no_leidas > 99 ? '99+' : d.no_leidas;
      badge.classList.add('show');
    } else {
      badge.classList.remove('show');
    }
    renderNotifLista();
  } catch(e) {}
}

function renderNotifLista() {
  const lista = document.getElementById('notifLista');
  if (!_notifData.length) {
    lista.innerHTML = '<div class="notif-empty">Sin notificaciones</div>';
    return;
  }
  lista.innerHTML = _notifData.map(n => {
    const leida  = n.leida == 1;
    const tiempo = tiempoRelativo(n.created_at);
    return `<div class="notif-item ${leida ? '' : 'no-leida'}" onclick="notifClick(${n.id}, '${n.folio || ''}')">
      <div class="notif-dot ${leida ? 'leida' : ''}"></div>
      <div class="notif-item-body">
        <div class="notif-item-titulo">${n.titulo}</div>
        <div class="notif-item-msg">${n.mensaje || ''}</div>
        <div class="notif-item-tiempo">${tiempo}</div>
      </div>
    </div>`;
  }).join('');
}

function tiempoRelativo(fechaStr) {
  const diff = Math.floor((Date.now() - new Date(fechaStr)) / 1000);
  if (diff < 60)    return 'Hace un momento';
  if (diff < 3600)  return 'Hace ' + Math.floor(diff/60) + ' min';
  if (diff < 86400) return 'Hace ' + Math.floor(diff/3600) + 'h';
  return 'Hace ' + Math.floor(diff/86400) + ' d&#237;as';
}

async function notifClick(id, folio) {
  await fetch('../api/notificaciones.php?accion=leer', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id })
  });
  if (folio) { cerrarNotifPanel(); cargarModulo('orden', { folio }); }
  const n = _notifData.find(x => x.id == id);
  if (n) n.leida = 1;
  const noLeidas = _notifData.filter(x => !x.leida).length;
  const badge = document.getElementById('notifBadge');
  badge.textContent = noLeidas > 99 ? '99+' : noLeidas;
  noLeidas > 0 ? badge.classList.add('show') : badge.classList.remove('show');
  renderNotifLista();
}

async function leerTodas() {
  await fetch('../api/notificaciones.php?accion=leer_todas', { method: 'POST' });
  _notifData.forEach(n => n.leida = 1);
  document.getElementById('notifBadge').classList.remove('show');
  renderNotifLista();
}

function toggleNotifPanel() {
  const panel = document.getElementById('notifPanel');
  panel.classList.toggle('open');
  if (panel.classList.contains('open')) cargarNotificaciones();
}

function cerrarNotifPanel() {
  document.getElementById('notifPanel').classList.remove('open');
}

document.addEventListener('click', e => {
  const wrap = document.getElementById('notifWrap');
  if (wrap && !wrap.contains(e.target)) cerrarNotifPanel();
});

cargarNotificaciones();
_siOrig.call(window, cargarNotificaciones, 30000);
<?php endif; ?>

<?php if ($esAdmin || $esComercial): ?>
// ── Mensajes internos (hilo persona <-> desarrollo) ───────────────────────────
var _esDevMsg = <?= $esAdmin ? 'true' : 'false' ?>;
var _msgConversaciones = [];
var _msgHiloActivo = [];
var _msgOtroIdActivo = null;
var _msgOtroNombreActivo = null;

function msgFechaHora(fechaStr) {
  var d = new Date(fechaStr.replace(' ', 'T'));
  return d.toLocaleDateString('es-MX', {day:'2-digit', month:'short'}) + ', ' +
    d.toLocaleTimeString('es-MX', {hour:'2-digit', minute:'2-digit'});
}

function msgEscHtml(s) {
  var d = document.createElement('div');
  d.textContent = s == null ? '' : String(s);
  return d.innerHTML;
}

async function cargarMsgBadge() {
  try {
    var r = await fetch('../api/mensajes.php?accion=sin_leer&t=' + Date.now());
    var d = await r.json();
    if (!d.ok) return;
    var badge = document.getElementById('msgBadge');
    if (d.total > 0) {
      badge.textContent = d.total > 99 ? '99+' : d.total;
      badge.classList.add('show');
    } else {
      badge.classList.remove('show');
    }
  } catch(e) {}
}

async function toggleMsgPanel() {
  var panel = document.getElementById('msgPanel');
  panel.classList.toggle('open');
  if (!panel.classList.contains('open')) return;
  if (_esDevMsg) {
    msgVolverLista();
  } else {
    msgAbrirHilo(null, null);
  }
}

async function msgVolverLista() {
  document.getElementById('msgBtnVolver').style.display = 'none';
  document.getElementById('msgFooter').style.display = 'none';
  document.getElementById('msgPanelTitulo').textContent = 'Mensajes';
  _msgOtroIdActivo = null;
  var lista = document.getElementById('msgLista');
  lista.innerHTML = '<div class="notif-empty">Cargando&#8230;</div>';
  try {
    var r = await fetch('../api/mensajes.php?accion=conversaciones&t=' + Date.now());
    var d = await r.json();
    if (!d.ok) { lista.innerHTML = '<div class="notif-empty">Error al cargar</div>'; return; }
    _msgConversaciones = d.conversaciones || [];
    if (!_msgConversaciones.length) {
      lista.innerHTML = '<div class="notif-empty">Sin conversaciones todav&#237;a</div>';
      return;
    }
    lista.innerHTML = '';
    _msgConversaciones.forEach(function(c) {
      var noLeidosHtml = c.no_leidos > 0 ? '<span class="notif-badge show" style="position:static;margin-left:6px;">' + c.no_leidos + '</span>' : '';
      var item = document.createElement('div');
      item.className = 'notif-item' + (c.no_leidos > 0 ? ' no-leida' : '');
      item.innerHTML =
        '<div class="notif-item-body">' +
          '<div class="notif-item-titulo">' + msgEscHtml(c.nombre) + noLeidosHtml + '</div>' +
          '<div class="notif-item-msg">' + msgEscHtml(c.ultimo_mensaje || '') + '</div>' +
          '<div class="notif-item-tiempo">' + tiempoRelativo(c.ultimo_at) + '</div>' +
        '</div>';
      item.addEventListener('click', function() { msgAbrirHilo(c.usuario_id, c.nombre); });
      lista.appendChild(item);
    });
  } catch(e) { lista.innerHTML = '<div class="notif-empty">Error al cargar</div>'; }
}

async function msgAbrirHilo(otroId, otroNombre) {
  _msgOtroIdActivo = otroId;
  _msgOtroNombreActivo = otroNombre;
  document.getElementById('msgFooter').style.display = 'flex';
  if (_esDevMsg) {
    document.getElementById('msgBtnVolver').style.display = 'inline';
    document.getElementById('msgPanelTitulo').textContent = otroNombre || 'Mensajes';
  }
  var lista = document.getElementById('msgLista');
  lista.innerHTML = '<div class="notif-empty">Cargando&#8230;</div>';
  try {
    var url = '../api/mensajes.php?accion=hilo&t=' + Date.now();
    if (_esDevMsg) url += '&con=' + otroId;
    var r = await fetch(url);
    var d = await r.json();
    if (!d.ok) { lista.innerHTML = '<div class="notif-empty">Error al cargar</div>'; return; }
    _msgHiloActivo = d.mensajes || [];
    msgRenderHilo();
    cargarMsgBadge();
  } catch(e) { lista.innerHTML = '<div class="notif-empty">Error al cargar</div>'; }
}

function msgRenderHilo() {
  var lista = document.getElementById('msgLista');
  if (!_msgHiloActivo.length) {
    lista.innerHTML = '<div class="notif-empty">Sin mensajes todav&#237;a. Escribe el primero.</div>';
    return;
  }
  lista.innerHTML = '<div class="msg-item">' + _msgHiloActivo.map(function(m) {
    var mio = _esDevMsg ? (m.de_otro == 0) : (m.de_otro == 1);
    return '<div class="msg-item-bubble ' + (mio ? 'mio' : 'otro') + '">' + msgEscHtml(m.mensaje) + '</div>' +
      '<div class="msg-item-tiempo ' + (mio ? 'mio' : '') + '">' + msgEscHtml(m.autor_nombre) + ' &middot; ' + msgFechaHora(m.created_at) + '</div>';
  }).join('') + '</div>';
  lista.scrollTop = lista.scrollHeight;
}

async function msgEnviar() {
  var input = document.getElementById('msgInput');
  var texto = input.value.trim();
  if (!texto) return;
  var body = { mensaje: texto };
  if (_esDevMsg) {
    if (!_msgOtroIdActivo) return;
    body.para = _msgOtroIdActivo;
  }
  input.value = '';
  try {
    var r = await fetch('../api/mensajes.php?accion=enviar', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    var d = await r.json();
    if (!d.ok) { alert(d.error || 'No se pudo enviar'); return; }
    msgAbrirHilo(_msgOtroIdActivo, _msgOtroNombreActivo);
  } catch(e) { alert('No se pudo enviar'); }
}

function cerrarMsgPanel() {
  document.getElementById('msgPanel').classList.remove('open');
}

document.addEventListener('click', function(e) {
  var wrap = document.getElementById('msgWrap');
  if (!wrap) return;
  // Usa composedPath() en vez de wrap.contains(e.target): un click en un ítem de la
  // lista de conversaciones dispara msgAbrirHilo(), que reemplaza el innerHTML de
  // #msgLista (para mostrar "Cargando...") ANTES de que el evento termine de subir
  // hasta aquí — para entonces e.target ya no está en el DOM y contains() da falso,
  // cerrando el panel de golpe. composedPath() congela la ruta al momento del click.
  var ruta = typeof e.composedPath === 'function' ? e.composedPath() : [e.target];
  if (ruta.indexOf(wrap) === -1) cerrarMsgPanel();
});

cargarMsgBadge();
_siOrig.call(window, cargarMsgBadge, 30000);
<?php endif; ?>
// ── Sidebar drawer móvil ──────────────────────────────────────────────────────
var _scrollY = 0;
function toggleSidebar() {
  var nav = document.getElementById('sidebarNav');
  var overlay = document.getElementById('sidebarOverlay');
  var isOpen = nav.classList.contains('open');
  if (isOpen) { cerrarSidebar(); } else {
    nav.classList.add('open');
    overlay.classList.add('open');
    _scrollY = window.scrollY || window.pageYOffset;
    document.body.style.position = 'fixed';
    document.body.style.top = '-' + _scrollY + 'px';
    document.body.style.width = '100%';
  }
}
function cerrarSidebar() {
  document.getElementById('sidebarNav').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
  document.body.style.position = '';
  document.body.style.top = '';
  document.body.style.width = '';
  window.scrollTo(0, _scrollY);
}
// Al navegar a un módulo en móvil, cerrar el drawer automáticamente
var _cargarModuloOrig = cargarModulo;
window.cargarModulo = function(nombre, params) {
  cerrarSidebar();
  return _cargarModuloOrig(nombre, params);
};

// ── Badge autorizaciones pendientes (ámbar) ───────────────────
(function() {
  function actualizarBadgeAuth() {
    fetch('/produccion/api/autorizaciones.php?pendientes=1')
      .then(function(r) { if (!r.ok) throw new Error(); return r.json(); })
      .then(function(data) {
        var badge = document.getElementById('authBadge');
        if (!badge) return;
        var total = Array.isArray(data) ? data.length : 0;
        if (total > 0) {
          badge.textContent = total;
          badge.style.display = 'inline-block';
        } else {
          badge.style.display = 'none';
        }
      })
      .catch(function() {});
  }
  actualizarBadgeAuth();
  _siOrig.call(window, actualizarBadgeAuth, 10000);
  window.actualizarBadgeAuth = actualizarBadgeAuth;
})();

// ── Badge OCs pendientes de envío (dir_admin) ─────────────────
<?php if ($esAdmin): ?>
(function() {
  function actualizarBadgeCompras() {
    fetch('/produccion/api/ordenes_compra.php?accion=pendientes_envio')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var badge = document.getElementById('badge-compras-envio');
        if (!badge) return;
        var total = data.total || 0;
        if (total > 0) {
          badge.textContent = total;
          badge.style.display = 'inline-block';
        } else {
          badge.style.display = 'none';
        }
      })
      .catch(function() {});
  }
  actualizarBadgeCompras();
  _siOrig.call(window, actualizarBadgeCompras, 60000);
  window.actualizarBadgeCompras = actualizarBadgeCompras;
})();
<?php else: ?>
window.actualizarBadgeCompras = function() {};
<?php endif; ?>

// ── Badge mensajes WA sin leer ────────────────────────────────
(function() {
  function actualizarBadgeWA() {
    fetch('/produccion/api/campanas.php?accion=sin_leer')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var badge = document.getElementById('waBadge');
        if (!badge) return;
        var total = data.total || 0;
        if (total > 0) {
          badge.textContent = total;
          badge.style.display = 'inline-block';
        } else {
          badge.style.display = 'none';
        }
      })
      .catch(function() {});
  }
  actualizarBadgeWA();
  _siOrig.call(window, actualizarBadgeWA, 10000);
  window.actualizarBadgeWA = actualizarBadgeWA;
})();

// ── Badge reportes pendientes ─────────────────────────────────
(function() {
  function actualizarBadgeReportes() {
    fetch('/produccion/api/reportes.php?accion=sin_leer')
      .then(function(r) { return r.json(); })
      .then(function(data) {
        var badge = document.getElementById('reportesBadge');
        if (!badge) return;
        var total = data.total || 0;
        if (total > 0) {
          badge.textContent = total;
          badge.style.display = 'inline-block';
        } else {
          badge.style.display = 'none';
        }
      })
      .catch(function() {});
  }
  actualizarBadgeReportes();
  _siOrig.call(window, actualizarBadgeReportes, 10000);
  window.actualizarBadgeReportes = actualizarBadgeReportes;
})();

// ── Reportar bug / mejora ─────────────────────────────────────────────────────
(function() {
  var _tipoSel    = null;
  var _elemento   = null;
  var _pickMode   = false;
  var _lastHover  = null;

  var overlay = document.createElement('div');
  overlay.className = 'rep-overlay';
  overlay.id = 'repOverlay';
  overlay.innerHTML =
    '<div class="rep-modal">'
    + '<div class="rep-head"><h3>&#128681; Reportar</h3><button class="rep-close" onclick="repCerrarModal()">&#10005;</button></div>'
    + '<div class="rep-body">'
    +   '<div class="rep-tipos">'
    +     '<button class="rep-tipo-btn" id="rep-tbug"    onclick="repTipo(\'bug\')"   ><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:5px"><path d="m8 2 1.88 1.88"/><path d="M14.12 3.88 16 2"/><path d="M9 7.13v-1a3.003 3.003 0 1 1 6 0v1"/><path d="M12 20c-3.3 0-6-2.7-6-6v-3a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v3c0 3.3-2.7 6-6 6z"/><path d="M12 20v-9"/><path d="M6.53 9C4.6 8.8 3 7.1 3 5"/><path d="M6 13H2"/><path d="M3 21c0-2.1 1.7-3.9 3.8-4"/><path d="M20.97 5c0 2.1-1.6 3.8-3.5 4"/><path d="M22 13h-4"/><path d="M17.2 17c2.1.1 3.8 1.9 3.8 4"/></svg>Bug / Error</button>'
    +     '<button class="rep-tipo-btn" id="rep-tmejora" onclick="repTipo(\'mejora\')"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:5px"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A6 6 0 0 0 6 8c0 1 .2 2.2 1.5 3.5.7.7 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>Mejora / Idea</button>'
    +   '</div>'
    +   '<div style="margin-bottom:14px">'
    +     '<label class="rep-label" style="margin-bottom:6px;display:block">Elemento en pantalla (opcional)</label>'
    +     '<button class="rep-btn-pick" id="rep-pick-btn" onclick="repIniciarPick()">&#8987; Haz clic para seleccionar un elemento</button>'
    +     '<div class="rep-elem-preview" id="rep-elem-preview" style="display:none"></div>'
    +   '</div>'
    +   '<label class="rep-label">¿Qué está pasando?</label>'
    +   '<textarea class="rep-textarea" id="rep-desc" placeholder="Describe el bug o la mejora..."></textarea>'
    +   '<div class="rep-msg" id="rep-msg"></div>'
    + '</div>'
    + '<div class="rep-foot">'
    +   '<button class="rep-btn-cancel" onclick="repCerrarModal()">Cancelar</button>'
    +   '<button class="rep-btn-send" id="rep-send-btn" onclick="repEnviar()">Enviar</button>'
    + '</div>'
    + '</div>';
  document.body.appendChild(overlay);

  // Banner de pick mode (fuera del overlay para cubrir toda la pantalla)
  var pickBanner = document.createElement('div');
  pickBanner.id = 'rep-pick-banner';
  pickBanner.innerHTML = '<span>&#128247; Haz clic en el elemento que está fallando</span><button onclick="repCancelarPick()">Cancelar (ESC)</button>';
  document.body.appendChild(pickBanner);

  // ── Pick mode ──────────────────────────────────────────────────────────────
  window.repIniciarPick = function() {
    overlay.classList.remove('open');
    _pickMode = true;
    document.body.classList.add('rep-pick-mode');
    document.addEventListener('mouseover', _onPickHover);
    document.addEventListener('click',     _onPickClick, true);
    document.addEventListener('keydown',   _onPickKey);
  };

  window.repCancelarPick = function() {
    _exitPick();
    overlay.classList.add('open');
  };

  function _exitPick() {
    _pickMode = false;
    document.body.classList.remove('rep-pick-mode');
    if (_lastHover) { _lastHover.classList.remove('rep-pick-highlight'); _lastHover = null; }
    document.removeEventListener('mouseover', _onPickHover);
    document.removeEventListener('click',     _onPickClick, true);
    document.removeEventListener('keydown',   _onPickKey);
  }

  function _onPickHover(e) {
    if (!_pickMode) return;
    var el = e.target;
    if (el === pickBanner || pickBanner.contains(el)) return;
    if (_lastHover && _lastHover !== el) _lastHover.classList.remove('rep-pick-highlight');
    el.classList.add('rep-pick-highlight');
    _lastHover = el;
  }

  function _onPickClick(e) {
    if (!_pickMode) return;
    var el = e.target;
    if (el === pickBanner || pickBanner.contains(el)) return;
    e.preventDefault(); e.stopPropagation();
    _capturarElemento(el);
    _exitPick();
    overlay.classList.add('open');
  }

  function _onPickKey(e) {
    if (e.key === 'Escape') repCancelarPick();
  }

  function _capturarElemento(el) {
    // Módulo activo
    var moduloEl = document.querySelector('.sidebar-link.active');
    var modulo   = moduloEl ? (moduloEl.dataset.modulo || moduloEl.textContent.trim()) : '(inicio)';

    // Texto visible del elemento
    var texto = (el.textContent || '').replace(/\s+/g,' ').trim().slice(0, 120);

    // Ruta: tag + id/atributo-clave/clase del elemento y 2 padres, desambiguado por posición si hace falta
    function _desc(node) {
      if (!node || node === document.body) return '';
      var d = node.tagName.toLowerCase();
      var especifico = false;
      if (node.id) {
        d += '#' + node.id;
        especifico = true;
      } else if (node.dataset && node.dataset.modulo) {
        d += '[data-modulo="' + node.dataset.modulo + '"]';
        especifico = true;
      } else if (node.className && typeof node.className === 'string') {
        var cls = node.className.trim().split(/\s+/).filter(function(c){ return c && c !== 'rep-pick-highlight'; }).slice(0,2).join('.');
        if (cls) d += '.' + cls;
      }
      // Si no quedó identificado por id/atributo único, desambiguar por posición entre hermanos del mismo tag
      if (!especifico && node.parentElement) {
        var hermanos = Array.prototype.filter.call(node.parentElement.children, function(h) { return h.tagName === node.tagName; });
        if (hermanos.length > 1) d += ':nth-of-type(' + (hermanos.indexOf(node) + 1) + ')';
      }
      return d;
    }
    var ruta = [_desc(el.parentElement && el.parentElement.parentElement), _desc(el.parentElement), _desc(el)].filter(Boolean).join(' > ');

    _elemento = { modulo: modulo, texto: texto, ruta: ruta, tag: el.tagName.toLowerCase() };

    // Caso especial: elemento dentro de un mensaje de chat (contenido dinámico, se re-pinta con el polling)
    // — se guarda el id del mensaje y de la conversación para poder reabrirlo exacto, en vez de un selector CSS frágil.
    var msgWrap = el.closest && el.closest('[data-msg-id]');
    if (msgWrap) {
      var convWrap = el.closest('[data-conv-id]');
      if (msgWrap.dataset.msgId)  _elemento.msgId  = msgWrap.dataset.msgId;
      if (convWrap && convWrap.dataset.convId) _elemento.convId = convWrap.dataset.convId;
    }

    // Mostrar preview en modal
    var preview = document.getElementById('rep-elem-preview');
    var pickBtn = document.getElementById('rep-pick-btn');
    if (preview) {
      preview.style.display = 'block';
      preview.innerHTML =
        '<div class="rep-elem-row"><span class="rep-elem-lbl">Módulo</span><span class="rep-elem-val">' + modulo + '</span></div>'
        + '<div class="rep-elem-row"><span class="rep-elem-lbl">Elemento</span><span class="rep-elem-val rep-elem-ruta">' + ruta + '</span></div>'
        + (texto ? '<div class="rep-elem-row"><span class="rep-elem-lbl">Texto</span><span class="rep-elem-val">' + texto + '</span></div>' : '')
        + '<button class="rep-elem-clear" onclick="repLimpiarElemento()">&#10005; Quitar</button>';
    }
    if (pickBtn) pickBtn.style.display = 'none';
  }

  window.repLimpiarElemento = function() {
    _elemento = null;
    var preview = document.getElementById('rep-elem-preview');
    var pickBtn = document.getElementById('rep-pick-btn');
    if (preview) preview.style.display = 'none';
    if (pickBtn) pickBtn.style.display = '';
  };

  // ── Modal ──────────────────────────────────────────────────────────────────
  window.repAbrirModal = function() {
    _tipoSel  = null;
    _elemento = null;
    var d = document.getElementById('rep-desc');
    var m = document.getElementById('rep-msg');
    if (d) d.value = '';
    if (m) { m.className = 'rep-msg'; m.textContent = ''; }
    document.getElementById('rep-tbug').className    = 'rep-tipo-btn';
    document.getElementById('rep-tmejora').className = 'rep-tipo-btn';
    repLimpiarElemento();
    overlay.classList.add('open');
  };

  window.repCerrarModal = function() {
    if (_pickMode) _exitPick();
    overlay.classList.remove('open');
  };

  window.repTipo = function(t) {
    _tipoSel = t;
    document.getElementById('rep-tbug').className    = 'rep-tipo-btn' + (t === 'bug'    ? ' sel-bug'    : '');
    document.getElementById('rep-tmejora').className = 'rep-tipo-btn' + (t === 'mejora' ? ' sel-mejora' : '');
  };

  window.repEnviar = function() {
    var desc = (document.getElementById('rep-desc').value || '').trim();
    var msg  = document.getElementById('rep-msg');
    var btn  = document.getElementById('rep-send-btn');
    if (!_tipoSel) { msg.textContent = 'Selecciona Bug o Mejora'; msg.className = 'rep-msg err'; return; }
    if (!desc)     { msg.textContent = 'Escribe una descripción';  msg.className = 'rep-msg err'; return; }
    btn.disabled = true; btn.textContent = 'Enviando...';
    msg.className = 'rep-msg';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/produccion/api/reportes.php?accion=crear');
    xhr.setRequestHeader('Content-Type', 'application/json');
    // S2-11: XMLHttpRequest no pasa por el wrapper global de fetch.
    var _metaTokRep = document.querySelector('meta[name="csrf-token"]');
    xhr.setRequestHeader('X-CSRF-Token', _metaTokRep ? _metaTokRep.content : '');
    xhr.onload = function() {
      btn.disabled = false; btn.textContent = 'Enviar';
      var res;
      try { res = JSON.parse(xhr.responseText); } catch(e) { res = {ok:false}; }
      if (res.ok) {
        msg.textContent = '¡Gracias! Tu reporte fue enviado.';
        msg.className = 'rep-msg ok';
        if (typeof window.actualizarBadgeReportes === 'function') window.actualizarBadgeReportes();
        setTimeout(repCerrarModal, 1800);
      } else {
        msg.textContent = res.error || 'Error al enviar';
        msg.className = 'rep-msg err';
      }
    };
    xhr.onerror = function() { btn.disabled = false; btn.textContent = 'Enviar'; msg.textContent = 'Error de conexión'; msg.className = 'rep-msg err'; };
    xhr.send(JSON.stringify({ tipo: _tipoSel, descripcion: desc, elemento: _elemento || null }));
  };

  overlay.addEventListener('click', function(e) { if (e.target === overlay) repCerrarModal(); });
})();
</script>