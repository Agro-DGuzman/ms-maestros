<?php

declare(strict_types=1);

use Core\Results\DomainException;
use Identidad\Domain\Desafios\DesafioDeIngreso;
use Identidad\Domain\Desafios\IdDeDesafio;
use Maestros\Domain\Contactos\Celular;

function unDesafio(string $digitos = '4821', string $ahora = '2026-09-11T12:00:00Z'): DesafioDeIngreso
{
    return DesafioDeIngreso::emitir(
        IdDeDesafio::desde('d-1'),
        Celular::desdeLocalBoliviano('70741828'),
        $digitos,
        new DateTimeImmutable($ahora),
    );
}

it('exige cuatro digitos', function (string $malo) {
    expect(fn () => unDesafio($malo))->toThrow(DomainException::class);
})->with(['', '123', '12345', 'abcd', '12a4']);

it('vive cinco minutos desde su emision', function () {
    $desafio = unDesafio();

    expect($desafio->expiraEn()->format('c'))
        ->toBe((new DateTimeImmutable('2026-09-11T12:05:00Z'))->format('c'));
});

it('se resuelve con el codigo correcto y se consume', function () {
    $desafio = unDesafio();

    expect($desafio->resolver('4821', new DateTimeImmutable('2026-09-11T12:01:00Z'))->isSuccess)->toBeTrue()
        ->and($desafio->estaConsumido())->toBeTrue();
});

it('no se puede usar dos veces', function () {
    $desafio = unDesafio();
    $desafio->resolver('4821', new DateTimeImmutable('2026-09-11T12:01:00Z'));

    $segundo = $desafio->resolver('4821', new DateTimeImmutable('2026-09-11T12:02:00Z'));

    expect($segundo->isFailure())->toBeTrue()
        ->and($segundo->error->code)->toBe('CODIGO_INVALIDO');
});

it('cuenta los intentos fallidos y se cierra al quinto', function () {
    $desafio = unDesafio();
    $ahora = new DateTimeImmutable('2026-09-11T12:01:00Z');

    for ($i = 0; $i < 5; $i++) {
        expect($desafio->resolver('0000', $ahora)->isFailure())->toBeTrue();
    }

    expect($desafio->intentosFallidos())->toBe(5);

    // Incluso con el código correcto, ya no entra.
    expect($desafio->resolver('4821', $ahora)->isFailure())->toBeTrue();
});

it('rechaza el codigo correcto despues de expirar', function () {
    $desafio = unDesafio();

    $resultado = $desafio->resolver('4821', new DateTimeImmutable('2026-09-11T12:05:01Z'));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('CODIGO_INVALIDO');
});

it('todos los fallos devuelven el mismo codigo, sin distinguir la causa', function () {
    $expirado = unDesafio();
    $consumido = unDesafio();
    $consumido->resolver('4821', new DateTimeImmutable('2026-09-11T12:01:00Z'));
    $equivocado = unDesafio();

    $codigos = [
        $expirado->resolver('4821', new DateTimeImmutable('2026-09-11T13:00:00Z'))->error->code,
        $consumido->resolver('4821', new DateTimeImmutable('2026-09-11T12:02:00Z'))->error->code,
        $equivocado->resolver('0000', new DateTimeImmutable('2026-09-11T12:01:00Z'))->error->code,
    ];

    expect($codigos)->toBe(['CODIGO_INVALIDO', 'CODIGO_INVALIDO', 'CODIGO_INVALIDO']);
});
