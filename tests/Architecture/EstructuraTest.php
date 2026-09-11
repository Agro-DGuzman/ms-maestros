<?php

declare(strict_types=1);

arch('el Core no conoce Laravel')
    ->expect('Core')
    ->not->toUse('Illuminate');

arch('Maestros no conoce Identidad')
    ->expect('Maestros')
    ->not->toUse('Identidad');

arch('Identidad no toca el dominio de Maestros directamente')
    ->expect('Identidad')
    ->not->toUse('Maestros\Domain');

arch('todo src declara tipos estrictos')
    ->expect('Core')
    ->toUseStrictTypes();

arch('el dominio no conoce Eloquent ni Laravel')
    ->expect(['Maestros\Domain', 'Identidad\Domain'])
    ->not->toUse(['Illuminate', 'Eloquent']);
