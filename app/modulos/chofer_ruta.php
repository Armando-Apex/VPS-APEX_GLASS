<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
$user = requireSessionApi();
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=chofer_ruta'); exit;
}
header('Content-Type: text/html; charset=utf-8');
$nombre_chofer = htmlspecialchars($user['nombre'], ENT_QUOTES);
$maps_key = defined('GOOGLE_MAPS_KEY') ? GOOGLE_MAPS_KEY : '';
?>
<style>
.cr-wrap { padding:16px; max-width:520px; margin:0 auto; font-family:-apple-system,sans-serif; }
.cr-header { text-align:center; margin-bottom:20px; }
.cr-header h2 { font-size:18px; font-weight:700; color:#0f172a; }
.cr-header p  { font-size:13px; color:#64748b; margin-top:4px; }
.cr-fecha-nav { display:flex; align-items:center; justify-content:center; gap:10px; margin-bottom:20px; }
.cr-fecha-nav button { background:#f1f5f9; border:1px solid #e2e8f0; border-radius:8px; padding:7px 14px; cursor:pointer; font-size:14px; }
#cr-fecha-lbl { font-size:15px; font-weight:700; color:#2563eb; min-width:130px; text-align:center; }

/* Tarjeta de parada */
.cr-stop { background:#fff; border:1px solid #e2e8f0; border-radius:14px; padding:16px; margin-bottom:12px; position:relative; }
.cr-stop.done { border-color:#86efac; background:#f0fdf4; }
.cr-stop.noent{ border-color:#fca5a5; background:#fef2f2; }
.stop-num { position:absolute; top:14px; left:14px; width:32px; height:32px; border-radius:50%; background:#2563eb; color:#fff; font-size:14px; font-weight:700; display:flex; align-items:center; justify-content:center; }
.cr-stop.done .stop-num  { background:#16a34a; }
.cr-stop.noent .stop-num { background:#dc2626; }
.stop-body { margin-left:44px; }
.stop-folio  { font-size:11px; font-weight:700; color:#2563eb; }
.stop-cliente{ font-size:16px; font-weight:700; color:#0f172a; margin:2px 0 6px; }
.stop-dir    { font-size:13px; color:#374151; margin-bottom:2px; }
.stop-ref    { font-size:11px; color:#64748b; }
.stop-peso   { font-size:11px; color:#94a3b8; margin-top:4px; }
.stop-btns   { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
.btn-maps    { display:inline-flex; align-items:center; justify-content:center; background:#2563eb; color:#fff; border:none; border-radius:8px; padding:9px 14px; font-size:13px; font-weight:600; cursor:pointer; flex:1; min-width:110px; text-decoration:none; }
.btn-maps:hover { background:#1d4ed8; }
.btn-entregado { background:#16a34a; color:#fff; border:none; border-radius:8px; padding:9px 14px; font-size:13px; font-weight:600; cursor:pointer; flex:1; min-width:110px; }
.btn-entregado:hover { background:#15803d; }
.btn-no-entregado { background:#f1f5f9; color:#dc2626; border:1px solid #fca5a5; border-radius:8px; padding:9px 14px; font-size:13px; font-weight:600; cursor:pointer; flex:1; min-width:110px; }
.btn-no-entregado:hover { background:#fee2e2; }
.btn-deshacer { background:#f1f5f9; color:#374151; border:1px solid #e2e8f0; border-radius:8px; padding:7px 12px; font-size:12px; cursor:pointer; }
.cr-empty { text-align:center; color:#94a3b8; padding:40px 20px; font-size:14px; }

.cr-resumen { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 16px; margin-bottom:16px; display:flex; gap:20px; justify-content:center; }
.cr-res-item { text-align:center; }
.cr-res-val  { font-size:20px; font-weight:700; color:#0f172a; }
.cr-res-lbl  { font-size:10px; color:#64748b; margin-top:2px; }

/* Mapa ruta chofer */
.cr-mapa-wrap { margin-bottom:16px; border-radius:12px; overflow:hidden; border:1px solid #e2e8f0; display:none; }
.cr-mapa-wrap.visible { display:block; }
.cr-mapa { width:100%; height:220px; }
.cr-mapa-lbl { background:#f8fafc; padding:8px 12px; font-size:11px; color:#64748b; display:flex; align-items:center; gap:6px; }
.cr-mapa-tiempo { font-weight:700; color:#2563eb; }

/* Toast */
.cr-toast { position:fixed; bottom:20px; left:50%; transform:translateX(-50%); background:#0f172a; color:#fff; padding:10px 20px; border-radius:10px; font-size:13px; z-index:999; display:none; white-space:nowrap; }
</style>

<div class="cr-wrap" id="cr-wrap">
  <div class="cr-header">
    <h2>&#128666; Mi Ruta</h2>
    <p id="cr-chofer-lbl"></p>
  </div>

  <div class="cr-fecha-nav">
    <button onclick="CR.cambiarDia(-1)">&#8592;</button>
    <span id="cr-fecha-lbl"></span>
    <button onclick="CR.cambiarDia(1)">&#8594;</button>
  </div>

  <div class="cr-resumen" id="cr-resumen" style="display:none">
    <div class="cr-res-item"><div class="cr-res-val" id="cr-r-total">0</div><div class="cr-res-lbl">Paradas</div></div>
    <div class="cr-res-item"><div class="cr-res-val" id="cr-r-ok">0</div><div class="cr-res-lbl">Entregadas</div></div>
    <div class="cr-res-item"><div class="cr-res-val" id="cr-r-pend">0</div><div class="cr-res-lbl">Pendientes</div></div>
  </div>

  <div class="cr-mapa-wrap" id="cr-mapa-wrap">
    <div class="cr-mapa" id="cr-mapa"></div>
    <div class="cr-mapa-lbl">🗺️ Ruta optimizada &nbsp;·&nbsp; Tiempo estimado: <span class="cr-mapa-tiempo" id="cr-mapa-tiempo">—</span></div>
  </div>

  <div id="cr-stops"></div>
</div>

<div id="cr-toast" class="cr-toast"></div>

<script>
var CR = (function() {
  var API    = '../api/rutas.php';
  var CHOFER = <?= json_encode($nombre_chofer) ?>;
  var _fecha = new Date().toISOString().slice(0,10);
  var _datos = [];

  function fmt(d) {
    return new Date(d+'T12:00:00').toLocaleDateString('es-MX',{weekday:'long',day:'2-digit',month:'long'});
  }

  function toast(msg) {
    var el = document.getElementById('cr-toast');
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(function(){ el.style.display='none'; }, 2500);
  }

  function setFecha(f) {
    _fecha = f;
    document.getElementById('cr-fecha-lbl').textContent = fmt(f);
    cargar();
  }

  function cambiarDia(d) {
    var dt = new Date(_fecha+'T12:00:00');
    dt.setDate(dt.getDate() + d);
    setFecha(dt.toISOString().slice(0,10));
  }

  async function cargar() {
    var url = API + '?accion=mi_ruta&fecha=' + _fecha + '&chofer=' + encodeURIComponent(CHOFER);
    var r   = await fetch(url);
    _datos  = await r.json();
    render();
  }

  function render() {
    var cont = document.getElementById('cr-stops');
    var res  = document.getElementById('cr-resumen');

    document.getElementById('cr-chofer-lbl').textContent = CHOFER;

    if (!_datos.length) {
      cont.innerHTML = '<div class="cr-empty">No tienes entregas programadas para este día.</div>';
      res.style.display = 'none';
      return;
    }

    var total = _datos.length;
    var ok    = _datos.filter(function(e){ return e.entrega_estado==='entregado'; }).length;
    var pend  = _datos.filter(function(e){ return e.entrega_estado==='pendiente'; }).length;
    document.getElementById('cr-r-total').textContent = total;
    document.getElementById('cr-r-ok').textContent    = ok;
    document.getElementById('cr-r-pend').textContent  = pend;
    res.style.display = 'flex';

    var html = '';
    _datos.forEach(function(e, i) {
      var cls = e.entrega_estado === 'entregado'   ? 'done'
              : e.entrega_estado === 'no_entregado' ? 'noent' : '';

      var dirParts = [e.direccion, e.colonia, e.ciudad].filter(Boolean);
      var dirStr   = dirParts.join(', ') || 'Sin dirección capturada';
      var mapsQ    = encodeURIComponent(
                       (dirParts.join(', ') || (e.cliente_nombre + ' ' + (e.ciudad||'Monterrey')))
                     ).replace(/'/g,'%27');
      var mapsHref = 'https://maps.google.com/?q=' + mapsQ;

      var btnMaps  = '<a class="btn-maps" href="'+mapsHref+'" target="_blank" rel="noopener">&#128205; Ver en Maps</a>';
      var btns = '';
      if (e.entrega_estado === 'pendiente') {
        btns = btnMaps
             + '<button class="btn-entregado" onclick="CR.marcar('+e.entrega_id+',\'entregado\')">&#10003; Entregado</button>'
             + '<button class="btn-no-entregado" onclick="CR.marcar('+e.entrega_id+',\'no_entregado\')">&#10005; No entregado</button>';
      } else {
        btns = '<a class="btn-maps" href="'+mapsHref+'" target="_blank" rel="noopener">&#128205; Maps</a>'
             + '<button class="btn-deshacer" onclick="CR.marcar('+e.entrega_id+',\'pendiente\')">&#8634; Deshacer</button>';
      }

      html += '<div class="cr-stop '+cls+'" id="stop-'+e.entrega_id+'">'
        + '<div class="stop-num">'+(i+1)+'</div>'
        + '<div class="stop-body">'
        +   '<div class="stop-folio">'+escH(e.folio)+' — '+escH(e.unidad||'')+'</div>'
        +   '<div class="stop-cliente">'+escH(e.cliente_nombre)+'</div>'
        +   '<div class="stop-dir">'+escH(dirStr)+'</div>'
        +   (e.referencias ? '<div class="stop-ref">Ref: '+escH(e.referencias)+'</div>' : '')
        +   '<div class="stop-peso">Peso aprox. '+parseFloat(e.peso_kg||0).toFixed(1)+' kg'
        +     (e.telefono_cliente ? ' &nbsp;&#128222; '+escH(e.telefono_cliente) : '')+'</div>'
        +   '<div class="stop-btns">'+btns+'</div>'
        + '</div>'
        + '</div>';
    });
    cont.innerHTML = html;
  }

  function escH(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  async function marcar(entrega_id, estado) {
    var r = await fetch(API, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({accion:'marcar_estado', entrega_id:entrega_id, estado:estado})
    });
    var d = await r.json();
    if (d.ok) {
      toast(estado==='entregado' ? '✓ Marcado como entregado' : estado==='no_entregado' ? 'Marcado como no entregado' : 'Restablecido');
      await cargar();
      // Re-optimizar si quedan pendientes
      var ruta_id = _datos.length ? _datos[0].ruta_id : null;
      var pendientes = _datos.filter(function(e){ return e.entrega_estado === 'pendiente'; });
      if (ruta_id && pendientes.length >= 2) reoptimizar(ruta_id);
    } else {
      toast(d.error || 'Error');
    }
  }

  async function reoptimizar(ruta_id) {
    var r = await fetch(API, {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({accion:'optimizar', ruta_id:ruta_id})
    });
    var d = await r.json();
    if (d.ok) {
      await cargar();
      if (d.tiempo_min) document.getElementById('cr-mapa-tiempo').textContent = d.tiempo_min + ' min';
      dibujarMapa();
    }
  }

  function dibujarMapa() {
    if (!window.google || !_datos.length) return;
    var pendientes = _datos.filter(function(e){ return e.entrega_estado === 'pendiente'; });
    if (!pendientes.length) {
      document.getElementById('cr-mapa-wrap').classList.remove('visible');
      return;
    }

    document.getElementById('cr-mapa-wrap').classList.add('visible');
    var mapEl = document.getElementById('cr-mapa');
    var origen = {lat: 25.6752, lng: -100.4573};
    var map = new google.maps.Map(mapEl, {
      zoom:11, center:origen, disableDefaultUI:true, zoomControl:true,
    });

    var waypoints = pendientes.map(function(e) {
      var addr = [e.direccion, e.colonia, e.ciudad].filter(Boolean).join(', ');
      return { location: addr || 'Monterrey, Nuevo León', stopover: true };
    });

    var svc = new google.maps.DirectionsService();
    var renderer = new google.maps.DirectionsRenderer({ map:map });
    svc.route({
      origin: origen,
      destination: origen,
      waypoints: waypoints,
      travelMode: google.maps.TravelMode.DRIVING,
    }, function(result, status) {
      if (status === 'OK') {
        renderer.setDirections(result);
        // Calcular tiempo total
        var legs = result.routes[0].legs;
        var totalMin = Math.round(legs.reduce(function(s,l){ return s + l.duration.value; },0) / 60);
        document.getElementById('cr-mapa-tiempo').textContent = totalMin + ' min';
      }
    });
  }

  function initMapa() {
    dibujarMapa();
  }

  // Init
  setFecha(new Date().toISOString().slice(0,10));

  return { cambiarDia:cambiarDia, marcar:marcar, initMapa:initMapa };
})();
</script>
<?php if ($maps_key): ?>
<script>
(function() {
  var script = document.createElement('script');
  script.src = 'https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($maps_key) ?>&v=beta&loading=async';
  script.async = true;
  script.defer = true;
  script.onload = function() { if (window.CR && CR.initMapa) CR.initMapa(); };
  document.head.appendChild(script);
})();
</script>
<?php endif; ?>