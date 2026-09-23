<?php

declare(strict_types=1);

use Identidad\Infrastructure\Keycloak\KeycloakEmisorDeToken;
use Illuminate\Http\Client\Factory;
use Psr\Log\NullLogger;

function emisorContra(Factory $http): KeycloakEmisorDeToken
{
    return new KeycloakEmisorDeToken($http, new NullLogger, 'http://keycloak', 'agropartners', 'ms-maestros', 'secreto', 5);
}

it('un refresh que Keycloak ya no acepta es REFRESH_TOKEN_INVALIDO, no un problema nuestro', function () {
    // Keycloak responde 400 invalid_grant cuando el refresh venció por
    // inactividad o fue revocado. Es el caso de todos los días, no una caída:
    // si saliera como 500, la App trataría una sesión vencida como un error
    // del servidor y reintentaría en vez de volver a pedir el código.
    $http = new Factory;
    $http->fake(['*' => $http->response(['error' => 'invalid_grant', 'error_description' => 'Token is not active'], 400)]);

    $resultado = emisorContra($http)->renovar('refresh-vencido');

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('REFRESH_TOKEN_INVALIDO');
});

it('cualquier otro rechazo de Keycloak sigue siendo un problema nuestro', function () {
    $http = new Factory;
    $http->fake(['*' => $http->response(['error' => 'invalid_request'], 400)]);

    expect(emisorContra($http)->renovar('refresh')->error->code)->toBe('IDENTIDAD_NO_DISPONIBLE');
});
