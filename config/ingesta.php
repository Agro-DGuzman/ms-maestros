<?php

declare(strict_types=1);

/*
 * La API de ingesta del Sincronizador. Las variables de Entra se leen al
 * usarse, no al arrancar, como la configuración de la App: una mal escrita
 * rompe solo la ingesta.
 */
return [
    'gateway' => [
        // El mismo valor que el `gateway-shared-secret` del APIM. Vacío,
        // /ingesta rechaza todo: falla cerrado.
        'secreto' => (string) env('GATEWAY_SECRETO', ''),

        // En /v1 se prende recién cuando el APIM manda la cabecera también
        // ahí; prenderlo antes deja a la App afuera.
        'en_v1' => (bool) env('GATEWAY_SECRETO_EN_V1', false),
    ],

    'entra' => [
        'tenant' => (string) env('INGESTA_ENTRA_TENANT_ID', ''),
        'audiencia' => (string) env('INGESTA_ENTRA_AUDIENCIA', ''),
        'rol' => (string) env('INGESTA_ROL', 'Ingesta.Maestros.Escribir'),
        'timeout' => (int) env('INGESTA_ENTRA_TIMEOUT', 10),
    ],
];
