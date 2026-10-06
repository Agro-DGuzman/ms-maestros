<?php

declare(strict_types=1);

use Core\Results\DomainException;
use Maestros\Domain\Contactos\IdDePersona;

it('arma el id de persona desde el codigo de contacto de SAP', function () {
    expect(IdDePersona::deContactoSap('1523')->value())->toBe('p-1523');
});

it('rechaza un codigo de contacto que no son solo digitos o es demasiado largo', function (string $malo) {
    // El id de persona es el usuario de Keycloak y el preferred_username del
    // token: solo se arma desde un código de SAP limpio, y entra en 40.
    expect(fn () => IdDePersona::deContactoSap($malo))->toThrow(DomainException::class);
})->with(['', '15a3', ' 1523', str_repeat('9', 39)]);
