<?php

declare(strict_types=1);

return [
    'desafios_por_hora' => (int) env('DESAFIO_MAX_POR_CELULAR_POR_HORA', 5),
    'dias_de_sesion' => (int) env('SESION_DIAS', 30),
];
