<?php

declare(strict_types=1);

return [
    'base_url' => (string) env('KEYCLOAK_BASE_URL', 'http://keycloak:8080'),
    'realm' => (string) env('KEYCLOAK_REALM', 'agropartners'),
    'client_id' => (string) env('KEYCLOAK_CLIENT_ID', 'ms-maestros'),
    'client_secret' => (string) env('KEYCLOAK_CLIENT_SECRET', ''),
    'timeout' => (int) env('KEYCLOAK_TIMEOUT', 5),
];
