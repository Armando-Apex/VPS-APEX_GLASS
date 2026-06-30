// APEX GLASS — Utilidades JS compartidas
// Cargado una vez en dashboard.php y standalone apps (operador, jefe_movil, estaciones)

// Número con decimales configurables; null/undefined → em dash
function fmt(n, dec) {
  dec = dec === undefined ? 2 : dec;
  if (n === null || n === undefined) return '&#8212;';
  var v = parseFloat(n);
  if (isNaN(v)) return '&#8212;';
  return v.toLocaleString('es-MX', {minimumFractionDigits: dec, maximumFractionDigits: dec});
}

// Peso en MXN con 2 decimales
function fmtPeso(n) {
  return n == null ? '&#8212;' : '$' + fmt(n, 2);
}

// Peso en MXN sin decimales (montos grandes en reportes)
function fmtMXN(n) {
  if (!n || isNaN(parseFloat(n))) return '$0';
  return '$' + parseFloat(n).toLocaleString('es-MX', {minimumFractionDigits: 0, maximumFractionDigits: 0});
}

// Fecha corta: "15 jun 2026"
function fmtFecha(f) {
  if (!f) return '&#8212;';
  var d = new Date(String(f).replace(' ', 'T') + (String(f).length === 10 ? 'T12:00:00' : ''));
  return d.toLocaleDateString('es-MX', {day: '2-digit', month: 'short', year: 'numeric'});
}

// Fecha + hora: "15 jun 2026 14:30"
function fmtFechaHora(f) {
  if (!f) return '&#8212;';
  var d = new Date(String(f).includes('T') ? f : String(f).replace(' ', 'T'));
  return d.toLocaleDateString('es-MX', {day: '2-digit', month: 'short', year: 'numeric'}) +
         ' ' + d.toLocaleTimeString('es-MX', {hour: '2-digit', minute: '2-digit'});
}

// Peso en kg con 1 decimal
function fmtKg(n) {
  return parseFloat(n || 0).toFixed(1) + ' kg';
}

// Escapar HTML para atributos
function escAttr(s) {
  return String(s)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}
