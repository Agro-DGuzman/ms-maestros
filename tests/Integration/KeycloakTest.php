<?php

declare(strict_types=1);

use Core\Results\ResultWithValue;
use Identidad\Infrastructure\Keycloak\KeycloakAdmin;
use Identidad\Infrastructure\Keycloak\KeycloakEmisorDeToken;
use Identidad\Infrastructure\Keycloak\VerificadorJwks;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Factory as Http;
use Maestros\Domain\Contactos\IdDePersona;
use Psr\Log\NullLogger;

/** La contraseña que el realm importado le pone a `p-8f2b1c40`. */
const CONTRASENA_DEL_REALM = 'contrasena-de-desarrollo';

/**
 * El motivo por el que estos dos tests fallan casi siempre, dicho en el propio
 * fallo: `identidad:habilitar` rota la contraseña del usuario, así que después
 * de correrlo el realm ya no tiene la que el archivo importado traía.
 */
const PISTA_DEL_REALM = 'Keycloak rechazó la contraseña del realm importado. '
    .'Si corriste identidad:habilitar o usaste el back-office, la rotó: recreá el '
    .'contenedor de Keycloak para reimportar el realm y volvé a intentar.';

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

/**
 * Emite un token y se detiene con una explicación si no puede, en vez de
 * devolver un `Result` fallido cuyo `value()` reventaría más adelante con un
 * `LogicException` que apunta al core y no dice nada de la causa.
 */
function tokenDelRealm(KeycloakEmisorDeToken $emisor): ResultWithValue
{
    $resultado = $emisor->emitirPara(IdDePersona::desde('p-8f2b1c40'), CONTRASENA_DEL_REALM);

    expect($resultado->isSuccess)->toBeTrue(PISTA_DEL_REALM);

    return $resultado;
}

it('Keycloak entrega un token con grant_type=password', function () {
    $emitido = tokenDelRealm(emisorReal())->value();

    expect($emitido->accessToken)->toBeString()
        ->and($emitido->refreshToken)->toBeString()
        ->and($emitido->expiraEnSegundos)->toBeGreaterThan(0);
});

it('renueva y despues revoca', function () {
    $emisor = emisorReal();
    $primero = tokenDelRealm($emisor)->value();

    $renovado = $emisor->renovar($primero->refreshToken);
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

function adminReal(): KeycloakAdmin
{
    return new KeycloakAdmin(
        new Http,
        (string) env('KEYCLOAK_BASE_URL', 'http://localhost:8080'),
        'agropartners',
        'ms-maestros',
        'secreto-de-desarrollo',
        10,
    );
}

it('la Admin API crea, encuentra y deshabilita un usuario', function () {
    $admin = adminReal();
    $persona = IdDePersona::desde('p-integracion-'.bin2hex(random_bytes(4)));

    expect($admin->crearOActualizar($persona, 'Una-Contrasena-Larga-1')->isSuccess)->toBeTrue()
        ->and($admin->estaActivo($persona))->toBeTrue()
        ->and($admin->deshabilitar($persona)->isSuccess)->toBeTrue();
});

it('un usuario deshabilitado deja de estar activo', function () {
    // Esto es lo que ningun doble puede probar: Keycloak no borra al usuario,
    // le pone enabled=false, y buscarlo por username lo sigue encontrando. El
    // doble hacia unset() y afirmaba lo contrario, asi que la conciliacion
    // daba el visto bueno sobre alguien que ya no podia entrar.
    $admin = adminReal();
    $persona = IdDePersona::desde('p-integracion-'.bin2hex(random_bytes(4)));

    $admin->crearOActualizar($persona, 'Una-Contrasena-Larga-1');
    expect($admin->estaActivo($persona))->toBeTrue();

    $admin->deshabilitar($persona);

    expect($admin->estaActivo($persona))->toBeFalse();
});

it('alguien que nunca existio tampoco esta activo', function () {
    expect(adminReal()->estaActivo(IdDePersona::desde('p-no-existe-'.bin2hex(random_bytes(4)))))
        ->toBeFalse();
});

it('el token de una persona creada por el Admin API pasa el verificador', function () {
    // El recorrido real de punta a punta, que ninguna prueba cubria: las
    // personas de verdad no vienen del realm importado, las crea
    // `identidad:habilitar` por el Admin API. Keycloak les pone `aud: account`
    // por los roles por defecto del realm, y con eso el verificador las
    // rechazaba: entraban y despues todos sus pedidos daban NO_AUTENTICADO.
    $admin = adminReal();
    $persona = IdDePersona::desde('p-integracion-'.bin2hex(random_bytes(4)));
    $contrasena = 'Una-Contrasena-Larga-1';

    expect($admin->crearOActualizar($persona, $contrasena)->isSuccess)->toBeTrue();

    $emitido = emisorReal()->emitirPara($persona, $contrasena);
    expect($emitido->isSuccess)->toBeTrue();

    $verificador = new VerificadorJwks(
        new Http,
        new Repository(new ArrayStore),
        (string) env('KEYCLOAK_BASE_URL', 'http://localhost:8080'),
        'agropartners',
        'ms-maestros',
        (string) env('KEYCLOAK_BASE_URL', 'http://localhost:8080'),
    );

    expect($verificador->verificar($emitido->value()->accessToken)?->value())
        ->toBe($persona->value());

    $admin->deshabilitar($persona);
});
