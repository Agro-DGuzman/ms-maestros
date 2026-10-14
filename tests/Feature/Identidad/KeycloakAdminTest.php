<?php

declare(strict_types=1);

use Identidad\Infrastructure\Keycloak\KeycloakAdmin;
use Illuminate\Http\Client\Factory;
use Maestros\Domain\Contactos\IdDePersona;

function adminContra(Factory $http): KeycloakAdmin
{
    return new KeycloakAdmin($http, 'http://keycloak', 'agropartners', 'ms-maestros', 'secreto', 5);
}

it('activos lista solo los usernames de personas', function () {
    $http = new Factory;
    $http->fake([
        'keycloak/realms/agropartners/protocol/openid-connect/token' => $http->response(['access_token' => 'token-de-servicio']),
        'keycloak/admin/realms/agropartners/users*' => $http->response([
            ['username' => 'n-59170741828', 'enabled' => true],
            ['username' => 'p-8f2b1c40', 'enabled' => true],
            // La cuenta de servicio del cliente también es un usuario del realm.
            ['username' => 'service-account-ms-maestros', 'enabled' => true],
        ]),
    ]);

    $activos = adminContra($http)->activos();

    expect(array_map(static fn (IdDePersona $p): string => $p->value(), $activos ?? []))
        ->toBe(['n-59170741828', 'p-8f2b1c40']);
});

it('activos es null si el directorio no responde', function () {
    $http = new Factory;
    $http->fake([
        'keycloak/realms/agropartners/protocol/openid-connect/token' => $http->response(['access_token' => 'token-de-servicio']),
        'keycloak/admin/realms/agropartners/users*' => $http->response('caido', 500),
    ]);

    expect(adminContra($http)->activos())->toBeNull();
});

it('activos recorre todas las paginas del directorio', function () {
    $pagina = array_map(static fn (int $i): array => ['username' => sprintf('n-5917%07d', $i), 'enabled' => true], range(1, 1000));

    $http = new Factory;
    $http->fake([
        'keycloak/realms/agropartners/protocol/openid-connect/token' => $http->response(['access_token' => 'token-de-servicio']),
        'keycloak/admin/realms/agropartners/users*' => $http->sequence()
            ->push($pagina)
            ->push([['username' => 'n-59179999999', 'enabled' => true]]),
    ]);

    expect(adminContra($http)->activos())->toHaveCount(1001);
});

it('activos no cuenta un usuario deshabilitado aunque el servidor lo devuelva', function () {
    $http = new Factory;
    $http->fake([
        'keycloak/realms/agropartners/protocol/openid-connect/token' => $http->response(['access_token' => 'token-de-servicio']),
        'keycloak/admin/realms/agropartners/users*' => $http->response([
            ['username' => 'n-59170741828', 'enabled' => true],
            ['username' => 'n-59171112233', 'enabled' => false],
        ]),
    ]);

    expect(array_map(static fn (IdDePersona $p): string => $p->value(), adminContra($http)->activos() ?? []))
        ->toBe(['n-59170741828']);
});
