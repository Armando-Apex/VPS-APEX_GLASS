<?php
// ============================================================
//  APEX GLASS - Candado de roles de SOLO CONSULTA (UPD-652)
//  Archivo: api/helpers/rol_restringido.php
// ============================================================
// Rol 'contador': entra a ver Cobranza, Facturación y Contabilidad y nada más.
// El candado vive en el servidor, no en el menú: se llama desde los 4 puntos donde el
// sistema valida una sesión (requireSession, requireSessionApi, requireLogin y
// session_boot.php), así que no depende de que cada pantalla se acuerde de revisarlo.
//   1. Solo lectura: cualquier método distinto de GET/HEAD se rechaza.
//   2. Lista cerrada: solo se abren los archivos de abajo; todo lo demás da 403,
//      aunque se escriba la dirección a mano.
// Para ampliar lo que ve el contador, se agrega aquí el archivo (y, si es API, se
// revisa que sus GET no modifiquen datos).

if (!defined('ROLES_SOLO_CONSULTA')) {
    define('ROLES_SOLO_CONSULTA', ['contador']);

    // Nómina trae sueldos: Armando decidió NO mostrársela al contador (01-oct-2026).
    // Con true se vería el apartado Nómina dentro de Contabilidad y su API.
    define('CONTADOR_VE_NOMINA', false);
}

function apexEsSoloConsulta($rol) {
    return in_array($rol, ROLES_SOLO_CONSULTA, true);
}

// Archivos (ruta relativa a produccion/) que puede abrir un rol de solo consulta.
function apexArchivosSoloConsulta() {
    $lista = [
        // Páginas
        'app/dashboard.php',
        'app/login.php',
        'app/modulos/finanzas_cobranza.php',
        'app/modulos/facturacion.php',
        'app/modulos/contabilidad.php',
        'app/modulos/contabilidad_catalogo.php',
        'app/modulos/contabilidad_mapeo.php',
        'app/modulos/contabilidad_pnl.php',
        'app/modulos/contabilidad_polizas.php',
        'app/modulos/contabilidad_balance.php',
        'app/modulos/caja_chica.php',
        'app/modulos/gastos_fijos.php',
        // APIs que usan esas pantallas (sus GET solo leen; revisado 01-oct-2026)
        'api/logout.php',
        'api/finanzas.php',
        'api/saldo_favor.php',
        'api/clientes.php',
        'api/cristales.php',
        'api/facturapi.php',
        'api/contabilidad_catalogo.php',
        'api/contabilidad_mapeo.php',
        'api/contabilidad_pnl.php',
        'api/polizas.php',
        'api/contabilidad_balance.php',
        'api/caja_chica.php',
        'api/gastos_fijos.php',
    ];
    if (CONTADOR_VE_NOMINA) {
        $lista[] = 'app/modulos/nomina.php';
        $lista[] = 'api/nomina.php';
    }
    return $lista;
}

// Decisión pura (sin efectos): ¿se permite este archivo con este método?
function apexSoloConsultaPermite($rutaRelativa, $metodo) {
    if (!in_array(strtoupper((string)$metodo), ['GET', 'HEAD'], true)) return false;
    return in_array($rutaRelativa, apexArchivosSoloConsulta(), true);
}

// Ruta del script en curso relativa a produccion/ (ej. 'api/finanzas.php').
function apexRutaScriptActual() {
    $raiz   = realpath(__DIR__ . '/../..');
    $script = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    if (!$raiz || !$script || strpos($script, $raiz . DIRECTORY_SEPARATOR) !== 0) return '';
    return str_replace(DIRECTORY_SEPARATOR, '/', substr($script, strlen($raiz) + 1));
}

// Se llama con el rol de la sesión. Si es de solo consulta y la petición no está
// permitida, corta aquí con 403 (JSON para las APIs, texto para las páginas).
function apexCandadoSoloConsulta($rol) {
    if (!apexEsSoloConsulta($rol)) return;
    if (PHP_SAPI === 'cli') return;
    $ruta   = apexRutaScriptActual();
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (apexSoloConsultaPermite($ruta, $metodo)) return;

    error_log('APEX solo-consulta: bloqueado rol=' . $rol . ' ' . $metodo . ' ' . $ruta);
    http_response_code(403);
    $msg = in_array(strtoupper($metodo), ['GET', 'HEAD'], true)
        ? 'Usuario de solo consulta: esta sección no está disponible.'
        : 'Usuario de solo consulta: no puede guardar, modificar ni borrar información.';
    if (strpos($ruta, 'api/') === 0) {
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    } else {
        if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
        echo '<div style="padding:40px;text-align:center;font-family:sans-serif;color:#b91c1c">'
            . htmlspecialchars($msg) . '</div>';
    }
    exit;
}
