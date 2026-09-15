<?php

declare(strict_types=1);

return [
    // Rangos CIDR desde los que se admite tráfico a /admin/ (B6).
    // Vacío = sin restricción; prohibido en producción, ver RestringirPorIp.
    'rangos_ip' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('BACKOFFICE_RANGOS_IP', ''))),
    )),

    'autenticador' => env('BACKOFFICE_AUTENTICADOR', 'desarrollo'), // desarrollo | entra

    'desarrollo' => [
        'oid' => env('BACKOFFICE_DEV_OID', 'dev-0001'),
        'nombre' => env('BACKOFFICE_DEV_NOMBRE', 'Operador Local'),
        'correo' => env('BACKOFFICE_DEV_CORREO', 'local@agropartners.com.bo'),
    ],

    'entra' => [
        'tenant' => env('ENTRA_TENANT_ID'),
        'cliente' => env('ENTRA_CLIENT_ID'),
        'secreto' => env('ENTRA_CLIENT_SECRET'),
        'rol_requerido' => env('ENTRA_ROL_REQUERIDO', 'Administrador'),
    ],

    'tamano_de_pagina' => 25,
];
