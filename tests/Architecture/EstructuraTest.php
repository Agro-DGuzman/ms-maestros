<?php

declare(strict_types=1);

arch('el Core no conoce Laravel')
    ->expect('Core')
    ->not->toUse('Illuminate');

arch('Maestros no conoce Identidad')
    ->expect('Maestros')
    ->not->toUse('Identidad');

arch('Identidad solo toma de Maestros el vocabulario de contacto')
    ->expect('Identidad\Domain')
    ->not->toUse([
        'Maestros\Domain\Socios\Socio',
        'Maestros\Domain\Grupos\GrupoEconomico',
        'Maestros\Domain\Contactos\PersonaDeContacto',
        'Maestros\Infrastructure',
        'Maestros\Application',
    ]);

arch('todo src declara tipos estrictos')
    ->expect('Core')
    ->toUseStrictTypes();

arch('el dominio no conoce Eloquent ni Laravel')
    ->expect(['Maestros\Domain', 'Identidad\Domain'])
    ->not->toUse(['Illuminate', 'Eloquent']);
