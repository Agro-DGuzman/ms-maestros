<?php

declare(strict_types=1);

use Identidad\Infrastructure\Keycloak\KeycloakAdmin;
use Identidad\Infrastructure\Keycloak\KeycloakEmisorDeToken;
use Illuminate\Http\Client\Factory as Http;
use Maestros\Domain\Contactos\IdDePersona;
use Psr\Log\NullLogger;

function emisorReal(): KeycloakEmisorDeToken
{
    return new KeycloakEmisorDeToken(
        new Http,
        new NullLogger,
        (string) env('KEYCLOAK_BASE_URL', 'http://localhost:8080'),
        'agropartners',
        'ms-maestros',
        'secreto-de-desarrollo',
        10,
    );
}

it('Keycloak entrega un token con grant_type=password', function () {
    $resultado = emisorReal()->emitirPara(IdDePersona::desde('p-8f2b1c40'), 'contrasena-de-desarrollo');

    expect($resultado->isSuccess)->toBeTrue()
        ->and($resultado->value()->accessToken)->toBeString()
        ->and($resultado->value()->refreshToken)->toBeString()
        ->and($resultado->value()->expiraEnSegundos)->toBeGreaterThan(0);
});

it('renueva y despues revoca', function () {
    $emisor = emisorReal();
    $primero = $emisor->emitirPara(IdDePersona::desde('p-8f2b1c40'), 'contrasena-de-desarrollo');

    $renovado = $emisor->renovar($primero->value()->refreshToken);
    expect($renovado->isSuccess)->toBeTrue();

    expect($emisor->revocar($renovado->value()->refreshToken)->isSuccess)->toBeTrue();

    // Un refresh ya revocado no sirve más.
    expect($emisor->renovar($renovado->value()->refreshToken)->isFailure())->toBeTrue();
});

it('rechaza una contrasena equivocada sin filtrar por que', function () {
    $resultado = emisorReal()->emitirPara(IdDePersona::desde('p-8f2b1c40'), 'equivocada');

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('IDENTIDAD_NO_DISPONIBLE');
});

it('la Admin API crea, encuentra y deshabilita un usuario', function () {
    $admin = new KeycloakAdmin(
        new Http,
        (string) env('KEYCLOAK_BASE_URL', 'http://localhost:8080'),
        'agropartners',
        'ms-maestros',
        'secreto-de-desarrollo',
        10,
    );

    $persona = IdDePersona::desde('p-integracion-'.bin2hex(random_bytes(4)));

    expect($admin->crearOActualizar($persona, 'Una-Contrasena-Larga-1')->isSuccess)->toBeTrue()
        ->and($admin->existe($persona))->toBeTrue()
        ->and($admin->deshabilitar($persona)->isSuccess)->toBeTrue();
});
