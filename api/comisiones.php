<?php
// ============================================================
//  APEX GLASS - API: Comisiones de Asesores
//  Método: GET ?accion=resumen_mes&mes=2026-08
//  Dato de nómina — gateado a ver_contabilidad (dir_admin/administracion/
//  dueno/desarrollo). Ver CLAUDE.md sección 12 y api/helpers/comisiones_lib.php
//  para las reglas de negocio (tramos, esquema Yahaira, retrabajo).
// ============================================================
require_once 'config.php';
require_once 'permisos.php';
require_once __DIR__ . '/helpers/comisiones_lib.php';

header('Content-Type: application/json; charset=utf-8');

$user = requirePermisoApi('ver_contabilidad');
$pdo  = getDB();

$accion = $_GET['accion'] ?? 'resumen_mes';

if ($accion === 'resumen_mes') {
    $mes = $_GET['mes'] ?? date('Y-m');
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        jsonResponse(['error' => 'Formato de mes inválido, use YYYY-MM'], 422);
    }

    $resumen = [];
    foreach (array_keys(COMISION_ASESORES) as $asesorKey) {
        $resumen[] = calcularComisionAsesorMes($pdo, $asesorKey, $mes);
    }

    jsonResponse(['ok' => true, 'mes' => $mes, 'asesores' => $resumen]);
    exit;
}

jsonResponse(['error' => 'Acción no reconocida'], 400);
