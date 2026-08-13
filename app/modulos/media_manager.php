<?php
require_once __DIR__ . '/../../api/config.php';
require_once __DIR__ . '/../../api/permisos.php';
require_once __DIR__ . '/../../api/helpers/icons.php';
$user = requireSession();
if (!in_array($user['rol'], ['dir_admin', 'desarrollo'], true)) {
    http_response_code(403);
    echo '<div style="padding:40px;text-align:center;color:#dc2626">Sin permiso</div>';
    exit;
}
if (!isset($_SERVER['HTTP_X_SPA_REQUEST'])) {
    header('Location: ../dashboard.php?m=media_manager'); exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<style>
.mm-wrap { padding:24px; max-width:1040px; margin:0 auto; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; }
.mm-title { font-size:18px; font-weight:700; color:#0f172a; margin-bottom:4px; }
.mm-sub { font-size:12.5px; color:#64748b; margin-bottom:20px; }
.mm-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:16px; }
.mm-btn { display:inline-flex; align-items:center; gap:7px; border:1px solid #e2e8f0; background:#fff; color:#0f172a; font-size:13px; font-weight:600; padding:8px 14px; border-radius:9px; cursor:pointer; }
.mm-btn:hover { background:#f8fafc; }
.mm-btn.primary { background:#0f172a; color:#fff; border-color:#0f172a; }
.mm-btn.primary:hover { background:#1e293b; }
.mm-crumbs { display:flex; align-items:center; gap:4px; font-size:13px; color:#64748b; margin-bottom:14px; flex-wrap:wrap; }
.mm-crumb { color:#2563eb; cursor:pointer; }
.mm-crumb:hover { text-decoration:underline; }
.mm-crumb.cur { color:#0f172a; cursor:default; font-weight:600; }
.mm-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.mm-table { width:100%; border-collapse:collapse; }
.mm-table th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--c-muted); font-weight:600; padding:11px 16px; border-bottom:1px solid #f1f5f9; }
.mm-table td { padding:11px 16px; border-bottom:1px solid #f1f5f9; font-size:13px; color:#334155; vertical-align:middle; }
.mm-table tr:last-child td { border-bottom:none; }
.mm-name { display:flex; align-items:center; gap:10px; }
.mm-name .ic { width:18px; height:18px; flex-shrink:0; color:#64748b; display:flex; }
.mm-name.dir .ic { color:#f59e0b; }
.mm-name .txt { font-weight:600; color:#0f172a; }
.mm-name.dir .txt { cursor:pointer; }
.mm-name.dir .txt:hover { color:#2563eb; text-decoration:underline; }
.mm-num { text-align:right; color:#64748b; font-variant-numeric:tabular-nums; white-space:nowrap; }
.mm-act { text-align:right; white-space:nowrap; }
.mm-ia { border:none; background:none; cursor:pointer; color:#64748b; padding:5px; border-radius:7px; }
.mm-ia:hover { background:#f1f5f9; color:#0f172a; }
.mm-ia.del:hover { color:#dc2626; background:#fef2f2; }
.mm-empty { padding:48px 16px; text-align:center; color:var(--c-muted); font-size:13px; }
.mm-up { margin-bottom:16px; }
.mm-up-item { background:#fff; border:1px solid #e2e8f0; border-radius:9px; padding:9px 14px; margin-bottom:8px; }
.mm-up-top { display:flex; justify-content:space-between; font-size:12.5px; color:#334155; margin-bottom:6px; }
.mm-up-top b { color:#0f172a; font-weight:600; }
.mm-prog { height:6px; background:#f1f5f9; border-radius:99px; overflow:hidden; }
.mm-prog i { display:block; height:100%; width:0; background:#0f766e; transition:width .15s; }
.mm-up-item.err .mm-prog i { background:#dc2626; }
.mm-up-item.ok .mm-prog i { background:#0f766e; }
</style>

<div class="mm-wrap">
  <div class="mm-title">Archivos de Video</div>
  <div class="mm-sub">Carpeta de trabajo de videos de marketing. Sube material, descarga los videos terminados. Solo tú ves esto.</div>

  <div class="mm-bar">
    <button class="mm-btn primary" onclick="ModMedia.pickFiles()"><?= icono('package', 15) ?> Subir archivos</button>
    <button class="mm-btn" onclick="ModMedia.nuevaCarpeta()"><?= icono('layers', 15) ?> Nueva carpeta</button>
    <button class="mm-btn" onclick="ModMedia.recargar()"><?= icono('activity', 15) ?> Actualizar</button>
    <input type="file" id="mmFile" multiple style="display:none">
  </div>

  <div id="mmUploads" class="mm-up"></div>
  <div id="mmCrumbs" class="mm-crumbs"></div>
  <div class="mm-card"><div id="mmList"></div></div>
</div>

<script>
var ModMedia = (function () {
  var API = '../api/media_manager.php';
  var CHUNK = 8 * 1024 * 1024;
  var path = '';

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
    });
  }
  function fmtSize(b) {
    if (!b) return '—';
    var u = ['B','KB','MB','GB']; var i = 0; var n = b;
    while (n >= 1024 && i < u.length - 1) { n = n / 1024; i++; }
    return (i === 0 ? n : n.toFixed(1)) + ' ' + u[i];
  }
  function fmtDate(ts) {
    var d = new Date(ts * 1000);
    return d.toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric' }) +
           ' ' + d.toLocaleTimeString('es-MX', { hour:'2-digit', minute:'2-digit' });
  }
  function iconoExt(ext) {
    var vid = ['mp4','mov','m4v','webm','mkv','avi','mpg','mpeg','wmv','flv','3gp'];
    var img = ['jpg','jpeg','png','gif','webp','bmp','tif','tiff','svg','heic'];
    var aud = ['mp3','wav','aac','m4a','ogg','flac','wma'];
    if (vid.indexOf(ext) >= 0) return '🎬';
    if (img.indexOf(ext) >= 0) return '🖼️';
    if (aud.indexOf(ext) >= 0) return '🎵';
    return '📄';
  }

  function recargar() { cargar(path); }

  function cargar(p) {
    fetch(API + '?accion=listar&path=' + encodeURIComponent(p), { headers: { 'X-SPA-Request': '1' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.error) { alert(d.error); return; }
        path = d.path || '';
        render(d.items || []);
        renderCrumbs();
      })
      .catch(function () { alert('Error al listar archivos'); });
  }

  function renderCrumbs() {
    var el = document.getElementById('mmCrumbs');
    var html = '<span class="mm-crumb" data-go="">Inicio</span>';
    if (path) {
      var parts = path.split('/');
      var acc = '';
      for (var i = 0; i < parts.length; i++) {
        acc = acc ? acc + '/' + parts[i] : parts[i];
        var last = (i === parts.length - 1);
        html += ' <span style="color:#cbd5e1">/</span> ';
        html += '<span class="mm-crumb' + (last ? ' cur' : '') + '"' + (last ? '' : ' data-go="' + esc(acc) + '"') + '>' + esc(parts[i]) + '</span>';
      }
    }
    el.innerHTML = html;
    var links = el.querySelectorAll('.mm-crumb[data-go]');
    for (var j = 0; j < links.length; j++) {
      links[j].addEventListener('click', function () { cargar(this.getAttribute('data-go')); });
    }
  }

  function render(items) {
    var cont = document.getElementById('mmList');
    if (!items.length) {
      cont.innerHTML = '<div class="mm-empty">Esta carpeta está vacía. Usa “Subir archivos” para empezar.</div>';
      return;
    }
    var h = '<table class="mm-table"><thead><tr>' +
            '<th>Nombre</th><th style="text-align:right">Tamaño</th><th style="text-align:right">Modificado</th><th></th>' +
            '</tr></thead><tbody>';
    for (var i = 0; i < items.length; i++) {
      var it = items[i];
      var ic = '<span style="font-size:15px">' + (it.es_dir ? '📁' : iconoExt(it.ext)) + '</span>';
      h += '<tr>';
      h += '<td><div class="mm-name ' + (it.es_dir ? 'dir' : '') + '">' +
             '<span class="ic">' + ic + '</span>' +
             '<span class="txt"' + (it.es_dir ? ' data-open="' + esc(it.nombre) + '"' : '') + '>' + esc(it.nombre) + '</span>' +
           '</div></td>';
      h += '<td class="mm-num">' + (it.es_dir ? '—' : fmtSize(it.size)) + '</td>';
      h += '<td class="mm-num">' + fmtDate(it.mtime) + '</td>';
      h += '<td class="mm-act">';
      if (!it.es_dir) {
        h += '<button class="mm-ia" data-dl="' + esc(it.nombre) + '" title="Descargar" style="font-size:15px">⬇</button>';
      }
      h += '<button class="mm-ia del" data-del="' + esc(it.nombre) + '" data-isdir="' + (it.es_dir ? '1' : '0') + '" title="Eliminar" style="font-size:15px">🗑</button>';
      h += '</td></tr>';
    }
    h += '</tbody></table>';
    cont.innerHTML = h;

    var opens = cont.querySelectorAll('[data-open]');
    for (var a = 0; a < opens.length; a++) {
      opens[a].addEventListener('click', function () {
        cargar(path ? path + '/' + this.getAttribute('data-open') : this.getAttribute('data-open'));
      });
    }
    var dls = cont.querySelectorAll('[data-dl]');
    for (var b = 0; b < dls.length; b++) {
      dls[b].addEventListener('click', function () { descargar(this.getAttribute('data-dl')); });
    }
    var dels = cont.querySelectorAll('[data-del]');
    for (var c = 0; c < dels.length; c++) {
      dels[c].addEventListener('click', function () {
        eliminar(this.getAttribute('data-del'), this.getAttribute('data-isdir') === '1');
      });
    }
  }

  function descargar(nombre) {
    var full = path ? path + '/' + nombre : nombre;
    window.location = API + '?accion=descargar&path=' + encodeURIComponent(full);
  }

  function eliminar(nombre, esDir) {
    if (!confirm('¿Eliminar ' + (esDir ? 'la carpeta' : 'el archivo') + ' "' + nombre + '"?' + (esDir ? '\n(Debe estar vacía)' : ''))) return;
    var full = path ? path + '/' + nombre : nombre;
    var fd = new FormData(); fd.append('path', full);
    fetch(API + '?accion=eliminar', { method:'POST', body:fd, headers:{ 'X-SPA-Request':'1' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.error) alert(d.error); else recargar(); });
  }

  function nuevaCarpeta() {
    var n = prompt('Nombre de la nueva carpeta:');
    if (!n) return;
    var fd = new FormData(); fd.append('path', path); fd.append('nombre', n);
    fetch(API + '?accion=crear_carpeta', { method:'POST', body:fd, headers:{ 'X-SPA-Request':'1' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.error) alert(d.error); else recargar(); });
  }

  function pickFiles() {
    var inp = document.getElementById('mmFile');
    inp.value = '';
    inp.onchange = function () {
      var files = Array.prototype.slice.call(inp.files);
      for (var i = 0; i < files.length; i++) { subirArchivo(files[i]); }
    };
    inp.click();
  }

  function randId() {
    var s = ''; var c = 'abcdefghijklmnopqrstuvwxyz0123456789';
    for (var i = 0; i < 20; i++) { s += c.charAt(Math.floor(Math.random() * c.length)); }
    return s;
  }

  function subirArchivo(file) {
    var box = document.getElementById('mmUploads');
    var row = document.createElement('div');
    row.className = 'mm-up-item';
    row.innerHTML = '<div class="mm-up-top"><b>' + esc(file.name) + '</b><span class="pct">0%</span></div>' +
                    '<div class="mm-prog"><i></i></div>';
    box.appendChild(row);
    var bar = row.querySelector('.mm-prog i');
    var pct = row.querySelector('.pct');

    var uploadId = randId();
    var total = Math.max(1, Math.ceil(file.size / CHUNK));
    var idx = 0;

    function sendNext() {
      if (idx >= total) return;
      var start = idx * CHUNK;
      var blob = file.slice(start, Math.min(start + CHUNK, file.size));
      var fd = new FormData();
      fd.append('path', path);
      fd.append('filename', file.name);
      fd.append('upload_id', uploadId);
      fd.append('chunk_index', idx);
      fd.append('total_chunks', total);
      fd.append('chunk', blob);
      fetch(API + '?accion=subir', { method:'POST', body:fd, headers:{ 'X-SPA-Request':'1' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.error) { row.className = 'mm-up-item err'; pct.textContent = d.error; return; }
          idx++;
          var p = Math.round((idx / total) * 100);
          bar.style.width = p + '%'; pct.textContent = p + '%';
          if (d.finalizado) {
            row.className = 'mm-up-item ok'; pct.textContent = 'Listo';
            setTimeout(function () { if (row.parentNode) row.parentNode.removeChild(row); }, 2500);
            recargar();
          } else {
            sendNext();
          }
        })
        .catch(function () { row.className = 'mm-up-item err'; pct.textContent = 'Error de red'; });
    }
    sendNext();
  }

  function init() { cargar(''); }

  window.ModMedia = {
    init: init, recargar: recargar, pickFiles: pickFiles,
    nuevaCarpeta: nuevaCarpeta
  };
  return window.ModMedia;
})();
ModMedia.init();
</script>
