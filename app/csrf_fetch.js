// S2-11: agrega el token CSRF de la sesión a todo fetch de mutación (POST/PUT/DELETE/PATCH).
// Requiere <meta name="csrf-token" content="..."> ya presente en el <head> antes de este script.
(function () {
  var meta = document.querySelector('meta[name="csrf-token"]');
  var _csrf = meta ? meta.content : '';
  var _fetch = window.fetch;
  window.fetch = function (url, opts) {
    opts = opts || {};
    var m = (opts.method || 'GET').toUpperCase();
    if (m !== 'GET' && m !== 'HEAD') {
      opts.headers = Object.assign({}, opts.headers, { 'X-CSRF-Token': _csrf });
    }
    return _fetch(url, opts);
  };
})();
