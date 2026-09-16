<?php

declare(strict_types=1);

return [
    // Dónde vive Keycloak: una dirección de red, puede cambiar.
    'base_url' => (string) env('KEYCLOAK_BASE_URL', 'http://keycloak:8080'),

    // Quién dice ser: el origen que Keycloak anuncia como `iss` en cada token,
    // el mismo valor que su `KC_HOSTNAME`. Cambiarlo invalida todo lo emitido,
    // así que es un nombre propio y estable, no la dirección por la que se lo
    // alcanza. Sin configurar, las dos cosas coinciden, que es el caso de local.
    'issuer' => (string) env('KEYCLOAK_ISSUER', env('KEYCLOAK_BASE_URL', 'http://keycloak:8080')),

    'realm' => (string) env('KEYCLOAK_REALM', 'agropartners'),
    'client_id' => (string) env('KEYCLOAK_CLIENT_ID', 'ms-maestros'),
    'client_secret' => (string) env('KEYCLOAK_CLIENT_SECRET', ''),
    'timeout' => (int) env('KEYCLOAK_TIMEOUT', 5),
];
