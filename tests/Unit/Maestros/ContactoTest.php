<?php

declare(strict_types=1);

use Core\Results\DomainException;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Socios\CodigoDeSocio;

it('antepone el prefijo boliviano y normaliza la entrada', function (string $entrada) {
    expect(Celular::desdeLocalBoliviano($entrada)->e164())->toBe('+59170741828');
})->with(['70741828', '707 41 828', '+591 707 41 828', '59170741828']);

it('rechaza un celular que no es movil boliviano', function (string $malo) {
    expect(fn () => Celular::desdeLocalBoliviano($malo))->toThrow(DomainException::class);
})->with(['', '12345', '30741828', '7074182812']);

it('una persona registrada no esta habilitada por ese solo hecho', function () {
    $sinHabilitar = PersonaDeContacto::replica(
        IdDePersona::desde('p-8f2b1c40'),
        CodigoDeSocio::desde('C-004871'),
        'Monica Salvatierra',
        Celular::desdeLocalBoliviano('70741828'),
        null,
        new DateTimeImmutable('2026-08-19T11:42:07Z'),
    );

    expect($sinHabilitar->estaHabilitada())->toBeFalse()
        ->and((string) $sinHabilitar->iniciales())->toBe('MS');
});

it('una persona habilitada lo esta', function () {
    $habilitada = PersonaDeContacto::replica(
        IdDePersona::desde('p-8f2b1c40'),
        CodigoDeSocio::desde('C-004871'),
        'Monica Salvatierra',
        Celular::desdeLocalBoliviano('70741828'),
        new DateTimeImmutable('2024-02-14T00:00:00Z'),
        new DateTimeImmutable('2026-08-19T11:42:07Z'),
    );

    expect($habilitada->estaHabilitada())->toBeTrue();
});
