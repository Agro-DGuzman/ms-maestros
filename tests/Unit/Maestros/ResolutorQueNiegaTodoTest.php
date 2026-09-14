<?php

declare(strict_types=1);

use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Infrastructure\Alcance\ResolutorQueNiegaTodo;

it('se niega a existir en produccion', function () {
    expect(fn () => new ResolutorQueNiegaTodo('production'))
        ->toThrow(RuntimeException::class);
});

it('en cualquier otro entorno arranca y niega todo', function (string $entorno) {
    $resolutor = new ResolutorQueNiegaTodo($entorno);

    expect($resolutor->alcanza(IdDePersona::desde('p-1'), CodigoDeSocio::desde('C-004871')))
        ->toBeFalse();
})->with(['testing', 'local', 'staging']);
