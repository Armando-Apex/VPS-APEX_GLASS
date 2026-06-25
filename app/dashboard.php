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
$esFinanzas   = in_array($_rol, ['dir_admin','administracion','dueno','desarrollo']);
$esLogistica  = in_array($_rol, ['dir_admin','administracion','dueno','chofer','desarrollo']);
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

function icono($nombre, $size = 16) {
    $p = [
        'bar-chart-2'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'clipboard-list' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><line x1="12" y1="11" x2="16" y2="11"/><line x1="12" y1="16" x2="16" y2="16"/><line x1="8" y1="11" x2="8.01" y2="11"/><line x1="8" y1="16" x2="8.01" y2="16"/>',
        'layers'         => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'alert-triangle' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'ban'            => '<circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>',
        'file-text'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'users'          => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'box'            => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'scissors'       => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><line x1="20" y1="4" x2="8.12" y2="15.88"/><line x1="14.47" y1="14.48" x2="20" y2="20"/><line x1="8.12" y1="8.12" x2="12" y2="12"/>',
        'message-square' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'trending-up'    => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
        'activity'       => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'settings'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'megaphone'      => '<path d="M3 11l19-9v18L3 13v-2z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
        'package'        => '<line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'shopping-cart'  => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
        'check-square'   => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'credit-card'    => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'truck'          => '<rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
        'map-pin'        => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'bell'           => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'menu'           => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
    ];
    $inner = $p[$nombre] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$inner.'</svg>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>APEX GLASS &mdash; Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Syncopate:wght@700&display=swap" rel="stylesheet">
<style>
:root { --sidebar-w:220px; --topbar-h:56px; --c-bg:#f8fafc; --c-white:#fff; --c-border:#e2e8f0; --c-text:#1e293b; --c-muted:#64748b; --c-blue:#2563eb; --c-red:#dc2626; }
*{box-sizing:border-box;margin:0;padding:0;}
html,body{height:100%;overflow:hidden;}
body{font-family:system-ui,-apple-system,sans-serif;background:var(--c-bg);color:var(--c-text);display:flex;flex-direction:column;}
.topbar{height:var(--topbar-h);background:#0f172a;display:flex;align-items:center;padding:0 20px;gap:16px;flex-shrink:0;z-index:100;}
.topbar-logo{font-family:'Syncopate',sans-serif;font-size:16px;font-weight:700;color:white;letter-spacing:2px;cursor:pointer;}
.topbar-sep{width:1px;height:24px;background:#334155;}
.topbar-sub{font-size:11px;color:#64748b;letter-spacing:1px;text-transform:uppercase;}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:14px;}
.topbar-user{font-size:12px;color:#94a3b8;}
.topbar-rol{font-size:11px;background:#1e3a5f;color:#93c5fd;padding:2px 8px;border-radius:99px;}
#reloj{font-size:12px;color:#64748b;font-variant-numeric:tabular-nums;min-width:70px;}
.topbar-logout{font-size:12px;color:#64748b;text-decoration:none;}
.topbar-logout:hover{color:#cbd5e1;}
.layout{display:flex;flex:1;overflow:hidden;}
.sidebar{width:var(--sidebar-w);background:var(--c-white);border-right:1px solid var(--c-border);display:flex;flex-direction:column;overflow-y:auto;flex-shrink:0;}
.sidebar-section{padding:4px 0;border-bottom:1px solid #f1f5f9;}
.sidebar-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;padding:10px 16px 4px;}
.sidebar-link{display:flex;align-items:center;gap:10px;padding:9px 16px;font-size:13px;font-weight:500;color:var(--c-muted);cursor:pointer;transition:all .15s;position:relative;border:none;background:none;width:100%;text-align:left;}
.sidebar-link:hover{background:#f8fafc;color:var(--c-text);}
.sidebar-link.active{background:#eff6ff;color:var(--c-blue);font-weight:600;}
.sidebar-link.active::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--c-blue);border-radius:0 2px 2px 0;}
.sidebar-icon{width:20px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:inherit;}
.sidebar-link:focus-visible{outline:2px solid var(--c-blue);outline-offset:-2px;border-radius:4px;}
.topbar-hamburger:focus-visible,.notif-btn:focus-visible{outline:2px solid #60a5fa;outline-offset:2px;border-radius:4px;}
.sidebar-badge{margin-left:auto;background:var(--c-red);color:white;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;display:none;}
.content-area{flex:1;overflow-y:auto;position:relative;}
#spa-content{min-height:100%;}
.spa-loading{display:none;position:absolute;inset:0;background:rgba(248,250,252,.8);z-index:10;align-items:center;justify-content:center;flex-direction:column;gap:12px;}
.spa-loading.show{display:flex;}
.spa-spinner{width:36px;height:36px;border:3px solid #e2e8f0;border-top-color:var(--c-blue);border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.spa-loading-txt{font-size:13px;color:var(--c-muted);}

/* ── Móvil ────────────────────────────────────────────────────────────────── */
.topbar-hamburger{display:none;background:none;border:none;cursor:pointer;color:#94a3b8;font-size:22px;padding:4px 6px;line-height:1;flex-shrink:0;}
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
  #reloj{display:none;}
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

/* Campana notificaciones */
.notif-wrap{position:relative;}
.notif-btn{background:none;border:none;cursor:pointer;color:#64748b;font-size:18px;padding:4px 6px;position:relative;line-height:1;transition:color .15s;}
.notif-btn:hover{color:#cbd5e1;}
.notif-badge{position:absolute;top:-2px;right:-2px;background:var(--c-red);color:white;font-size:10px;font-weight:700;min-width:16px;height:16px;border-radius:99px;display:none;align-items:center;justify-content:center;padding:0 4px;line-height:1;}
.notif-badge.show{display:flex;}
.notif-panel{display:none;position:absolute;top:calc(100% + 8px);right:0;width:340px;background:#1e293b;border:1px solid #334155;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.4);z-index:200;overflow:hidden;}
.notif-panel.open{display:block;}
.notif-panel-head{padding:12px 16px;border-bottom:1px solid #334155;display:flex;justify-content:space-between;align-items:center;}
.notif-panel-titulo{font-size:13px;font-weight:700;color:#f0f0f0;}
.notif-btn-leer-todas{font-size:11px;color:#64748b;background:none;border:none;cursor:pointer;padding:0;}
.notif-btn-leer-todas:hover{color:#93c5fd;}
.notif-lista{max-height:380px;overflow-y:auto;}
.notif-item{padding:12px 16px;border-bottom:1px solid #273548;cursor:pointer;transition:background .1s;display:flex;gap:10px;align-items:flex-start;}
.notif-item:hover{background:#273548;}
.notif-item.no-leida{background:rgba(37,99,235,.08);}
.notif-item.no-leida:hover{background:rgba(37,99,235,.15);}
.notif-dot{width:8px;height:8px;border-radius:50%;background:var(--c-blue);flex-shrink:0;margin-top:4px;}
.notif-dot.leida{background:transparent;}
.notif-item-body{flex:1;min-width:0;}
.notif-item-titulo{font-size:13px;font-weight:600;color:#f0f0f0;}
.notif-item-msg{font-size:11px;color:#64748b;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.notif-item-tiempo{font-size:10px;color:#475569;margin-top:3px;}
.notif-empty{padding:32px;text-align:center;color:#475569;font-size:13px;}
</style>
</head>
<body>

<div class="topbar">
  <button class="topbar-hamburger" onclick="toggleSidebar()" aria-label="Menú"><?= icono('menu', 20) ?></button>
  <div class="topbar-logo" onclick="cargarModulo('resumen')">APEX GLASS</div>
  <div class="topbar-sep"></div>
  <div class="topbar-sub">Producci&oacute;n</div>
  <div class="topbar-right">
    <span id="reloj"></span>
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
    <span class="topbar-user"><?= htmlspecialchars($_name) ?></span>
    <span class="topbar-rol"><?= htmlspecialchars($_rolLabel) ?></span>
    <a href="../api/logout.php?redirect=login.php" class="topbar-logout">Salir &rarr;</a>
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
    <?php if ($esAdmin): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Administraci&oacute;n</div>
      <button class="sidebar-link" data-modulo="admin_ordenes" onclick="cargarModulo('admin_ordenes')">
        <span class="sidebar-icon"><?= icono('settings') ?></span>Admin &Oacute;rdenes
      </button>
      <button class="sidebar-link" data-modulo="admin_comunicados" onclick="cargarModulo('admin_comunicados')">
        <span class="sidebar-icon"><?= icono('megaphone') ?></span>Admin Comunicados
      </button>
    </div>
    <?php endif; ?>
    <?php if ($esInventario): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Inventario</div>
      <button class="sidebar-link" data-modulo="inventario" onclick="cargarModulo('inventario')">
        <span class="sidebar-icon"><?= icono('package') ?></span>Inventario
      </button>
      <button class="sidebar-link" data-modulo="compras" onclick="cargarModulo('compras')">
        <span class="sidebar-icon"><?= icono('shopping-cart') ?></span>Compras
        <?php if ($esAdmin): ?><span id="badge-compras-envio" style="display:none;background:#7c3aed;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:99px;margin-left:4px"></span><?php endif; ?>
      </button>
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
    </div>
    <?php endif; ?>
    <?php if ($esLogistica): ?>
    <div class="sidebar-section">
      <div class="sidebar-label">Log&iacute;stica</div>
      <?php if ($esDesarrollo): ?>
      <button class="sidebar-link" data-modulo="logistica_rutas" onclick="cargarModulo('logistica_rutas')">
        <span class="sidebar-icon"><?= icono('truck') ?></span>Rutas de Entrega <span style="font-size:10px;background:#f59e0b;color:#000;padding:1px 5px;border-radius:99px;margin-left:4px">WIP</span>
      </button>
      <?php endif; ?>
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
  function tick() { el.textContent = new Date().toLocaleTimeString('es-MX',{hour:'2-digit',minute:'2-digit',second:'2-digit'}); }
  tick(); window.setInterval(tick, 1000);
})();

const MODULOS = {
  resumen:'modulos/resumen.php', ordenes:'modulos/ordenes.php',
  estaciones:'modulos/estaciones.php', retrabajo:'modulos/retrabajo.php',
  cotizaciones:'modulos/cotizaciones.php', clientes:'modulos/clientes.php',
  cristales:'modulos/cristales.php', optimizador:'modulos/optimizador.php',
  reporte_direccion:'modulos/reporte_direccion.php', productividad:'modulos/productividad.php',
  admin_ordenes:'modulos/admin_ordenes.php', admin_comunicados:'modulos/admin_comunicados.php',
  inventario:'modulos/inventario.php', compras:'modulos/compras.php',
  finanzas_vobo:'modulos/finanzas_vobo.php',
  finanzas_cobranza:'modulos/finanzas_cobranza.php',
  logistica_rutas:'modulos/logistica_rutas.php', chofer_ruta:'modulos/chofer_ruta.php',
  omisiones:'modulos/omisiones.php',
  campanas:'modulos/campanas.php',
  orden:'modulos/orden.php', cotizacion:'modulos/cotizacion.php',
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
  const url = archivo + (qs ? '?' + qs : '');

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
          .replace(/\bconst\s+(?!ModResumen|ModOrdenes|ModEstaciones|ModCotizaciones|ModClientes|ModCristales|ModProductividad|ModReporte|ModAdminOrdenes|ModAdminComunicados|ModInventario|ModCompras|ModRetrabajo|ModCotizacion|ModFinanzasVobo|ModFinanzasCobranza|ModArchivos|LR|CR\b)/g, 'var ')
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
      `<div style="padding:40px;text-align:center;color:#dc2626">
        <div style="font-size:28px">&#9888;&#65039;</div>
        <div style="font-size:15px;font-weight:600;margin-top:12px">Error al cargar m&#243;dulo</div>
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
  setInterval(actualizarBadgeAuth, 60000);
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
  setInterval(actualizarBadgeCompras, 60000);
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
  setInterval(actualizarBadgeWA, 30000);
  window.actualizarBadgeWA = actualizarBadgeWA;
})();
</script>