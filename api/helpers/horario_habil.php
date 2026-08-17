<?php
// Horario laboral: lunes-viernes 08:00-17:00, sábado 08:00-13:00, domingo cerrado.
// Horas de Monterrey/CDMX, mismas que ya usa created_at en el resto del sistema.
function minutosHabilesEntre($inicioStr, $finStr) {
    if (!$inicioStr || !$finStr) return null;

    $inicio = new DateTime($inicioStr);
    $fin    = new DateTime($finStr);
    if ($fin <= $inicio) return 0;

    $horario = [
        1 => ['08:00', '17:00'],
        2 => ['08:00', '17:00'],
        3 => ['08:00', '17:00'],
        4 => ['08:00', '17:00'],
        5 => ['08:00', '17:00'],
        6 => ['08:00', '13:00'],
        7 => null,
    ];

    $totalMin = 0;
    $cursor = clone $inicio;
    $cursor->setTime(0, 0, 0);

    while ($cursor < $fin) {
        $dow = (int)$cursor->format('N');
        $rango = $horario[$dow] ?? null;
        if ($rango !== null) {
            $diaStr = $cursor->format('Y-m-d');
            $apertura = new DateTime($diaStr . ' ' . $rango[0]);
            $cierre   = new DateTime($diaStr . ' ' . $rango[1]);
            $desde = max($apertura, $inicio);
            $hasta = min($cierre, $fin);
            if ($hasta > $desde) {
                $totalMin += ($hasta->getTimestamp() - $desde->getTimestamp()) / 60;
            }
        }
        $cursor->modify('+1 day');
    }
    return (int)round($totalMin);
}
