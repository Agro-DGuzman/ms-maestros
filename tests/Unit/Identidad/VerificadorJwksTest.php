<?php

declare(strict_types=1);

use Identidad\Infrastructure\Keycloak\VerificadorJwks;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as Http;
use Tests\Soporte\ClaveDePrueba;

const BASE_URL = 'http://keycloak:8080';
const REALM = 'agropartners';
const CLIENTE = 'ms-maestros';
const EMISOR = BASE_URL.'/realms/'.REALM;
const EMISOR_PUBLICO = 'https://identidad.agropartners.com.bo';

function verificador(): VerificadorJwks
{
    $http = new Http;
    $http->fake(['*' => $http->response(ClaveDePrueba::jwks())]);

    // El caso de local: Keycloak vive donde dice vivir, asi que emisor y
    // direccion son el mismo valor.
    return new VerificadorJwks(
        $http,
        new CacheRepository(new ArrayStore),
        BASE_URL,
        REALM,
        CLIENTE,
        BASE_URL,
    );
}

/** @param array<string, mixed> $extra */
function tokenCon(array $extra = []): string
{
    return ClaveDePrueba::token([
        'iss' => EMISOR,
        'azp' => CLIENTE,
        'preferred_username' => 'p-8f2b1c40',
        'iat' => time() - 10,
        'exp' => time() + 300,
        ...$extra,
    ]);
}

it('acepta un token del emisor y el cliente correctos', function () {
    expect(verificador()->verificar(tokenCon())?->value())->toBe('p-8f2b1c40');
});

it('rechaza un token emitido por otro realm', function () {
    // Mismo par de claves, otro emisor: sin validar `iss`, la firma sola no
    // distingue un realm de otro.
    $otro = tokenCon(['iss' => 'http://keycloak:8080/realms/otro-realm']);

    expect(verificador()->verificar($otro))->toBeNull();
});

it('rechaza un token sin iss', function () {
    $sinIss = ClaveDePrueba::token([
        'azp' => CLIENTE,
        'preferred_username' => 'p-8f2b1c40',
        'exp' => time() + 300,
    ]);

    expect(verificador()->verificar($sinIss))->toBeNull();
});

it('rechaza un token del mismo realm emitido para otro cliente', function () {
    // El caso que motiva todo esto: el realm es el nuestro y la firma valida,
    // pero el token se emitio para otra aplicacion.
    $deOtroCliente = tokenCon(['azp' => 'otra-app']);

    expect(verificador()->verificar($deOtroCliente))->toBeNull();
});

it('acepta cuando aud nombra a nuestro cliente', function () {
    $conAud = tokenCon(['aud' => [CLIENTE, 'account']]);

    expect(verificador()->verificar($conAud)?->value())->toBe('p-8f2b1c40');
});

it('acepta cuando aud es un string con nuestro cliente', function () {
    $conAud = tokenCon(['aud' => CLIENTE]);

    expect(verificador()->verificar($conAud)?->value())->toBe('p-8f2b1c40');
});

it('rechaza cuando aud existe y no nos nombra, aunque azp si', function () {
    // `aud` presente es la afirmacion explicita de para quien es el token.
    // Si esta y no nos incluye, no es para nosotros.
    $ajeno = tokenCon(['aud' => ['account'], 'azp' => CLIENTE]);

    expect(verificador()->verificar($ajeno))->toBeNull();
});

it('cae en azp cuando el realm no emite aud', function () {
    // Keycloak no agrega `aud` salvo que el realm tenga un audience mapper.
    // Los tokens de este realm hoy no lo traen, asi que exigir `aud` a secas
    // rechazaria todos y dejaria el servicio sin nadie adentro.
    expect(tokenCon())->not->toContain('"aud"');

    expect(verificador()->verificar(tokenCon())?->value())->toBe('p-8f2b1c40');
});

it('rechaza un token sin aud ni azp', function () {
    $anonimo = ClaveDePrueba::token([
        'iss' => EMISOR,
        'preferred_username' => 'p-8f2b1c40',
        'exp' => time() + 300,
    ]);

    expect(verificador()->verificar($anonimo))->toBeNull();
});

it('sigue rechazando un token vencido', function () {
    $vencido = tokenCon(['iat' => time() - 600, 'exp' => time() - 300]);

    expect(verificador()->verificar($vencido))->toBeNull();
});

it('sigue rechazando un token sin preferred_username', function () {
    $sinUsuario = ClaveDePrueba::token([
        'iss' => EMISOR,
        'azp' => CLIENTE,
        'exp' => time() + 300,
    ]);

    expect(verificador()->verificar($sinUsuario))->toBeNull();
});

it('acepta el emisor configurado aunque a Keycloak lo llame por otra direccion', function () {
    // En Azure el `iss` es un nombre estable que no resuelve a ningun lado y el
    // JWKS se trae del FQDN interno del entorno. Con un solo valor para las dos
    // cosas habria que elegir: o el token verifica, o el JWKS se puede traer.
    $http = new Http;
    $http->fake(['*' => $http->response(ClaveDePrueba::jwks())]);

    $verificador = new VerificadorJwks(
        $http,
        new CacheRepository(new ArrayStore),
        BASE_URL,
        REALM,
        CLIENTE,
        EMISOR_PUBLICO,
    );

    $token = tokenCon(['iss' => EMISOR_PUBLICO.'/realms/'.REALM]);

    expect($verificador->verificar($token)?->value())->toBe('p-8f2b1c40');
});

it('rechaza un token que dice venir de la direccion interna y no del emisor', function () {
    // Una vez que el emisor es un nombre propio, el FQDN interno deja de ser
    // una identidad valida: quien alcance a Keycloak por dentro no puede
    // hacerse pasar por el emisor publico.
    $http = new Http;
    $http->fake(['*' => $http->response(ClaveDePrueba::jwks())]);

    $verificador = new VerificadorJwks(
        $http,
        new CacheRepository(new ArrayStore),
        BASE_URL,
        REALM,
        CLIENTE,
        EMISOR_PUBLICO,
    );

    expect($verificador->verificar(tokenCon()))->toBeNull();
});
