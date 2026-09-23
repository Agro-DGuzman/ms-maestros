<?php

declare(strict_types=1);

return [
    'desafios_por_hora' => (int) env('DESAFIO_MAX_POR_CELULAR_POR_HORA', 5),
    'dias_de_sesion' => (int) env('SESION_DIAS', 30),

    // TEMPORAL, solo para probar. Números separados por coma a los que
    // `/auth/otp` les devuelve el código en la respuesta. Vacío es apagado.
    // Con cualquier número que no sea de prueba, esto es un salteo del OTP:
    // ver EcoDeCodigoDePrueba.
    'numeros_con_codigo_en_respuesta' => (string) env('OTP_ECO_NUMEROS', ''),
];
