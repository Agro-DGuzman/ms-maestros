<?php

declare(strict_types=1);

/*
 * Una regla por par (capa, prohibido), a proposito.
 *
 * Pest combina las listas con AND: `expect([a, b])->not->toUse([x, y])` solo
 * falla si *las dos* capas usan *los dos* destinos. Escrito con listas, cada
 * regla se relaja sola hasta no verificar casi nada —el mismo defecto que H3
 * encontro en AlcanceTest, en otro archivo. Los foreach de abajo generan una
 * regla con un solo sujeto y un solo destino, que es la unica forma que falla
 * cuando tiene que fallar.
 */

arch('el Core no conoce Laravel')
    ->expect('Core')
    ->not->toUse('Illuminate');

arch('Maestros no conoce Identidad')
    ->expect('Maestros')
    ->not->toUse('Identidad');

// La dependencia va en un solo sentido: BackOffice -> Identidad -> Maestros.
// Nadie mira hacia el back-office: Identidad sigue sin saber que hay pantallas.
foreach (['Core', 'Maestros', 'Identidad'] as $modulo) {
    arch("{$modulo} no conoce BackOffice")
        ->expect($modulo)
        ->not->toUse('BackOffice');
}

foreach (['Core', 'Maestros', 'Identidad', 'BackOffice'] as $modulo) {
    arch("{$modulo} declara tipos estrictos")
        ->expect($modulo)
        ->toUseStrictTypes();
}

// Identidad toma de Maestros solo el vocabulario de contacto: el celular y el
// identificador de persona. Ni los agregados ajenos ni las capas internas.
$ajenoAIdentidad = [
    'Maestros\Domain\Socios\Socio',
    'Maestros\Domain\Grupos\GrupoEconomico',
    'Maestros\Domain\Contactos\PersonaDeContacto',
    'Maestros\Infrastructure',
    'Maestros\Application',
];

foreach ($ajenoAIdentidad as $prohibido) {
    arch("Identidad\\Domain no toma {$prohibido}")
        ->expect('Identidad\Domain')
        ->not->toUse($prohibido);
}

// El dominio y la capa Application no conocen el framework ni la persistencia.
// Prohibir solo 'Illuminate' no alcanza: los records no se llaman Illuminate,
// viven en Infrastructure, que es como GrupoRecord se colo en
// ObtenerContextoHandler sin que ninguna regla lo atrapara (H6).
$prohibidosPorCapa = [
    'Maestros\Domain' => ['Illuminate', 'Eloquent'],
    'Identidad\Domain' => ['Illuminate', 'Eloquent'],
    'BackOffice\Domain' => ['Illuminate', 'Eloquent'],
    'Maestros\Application' => ['Illuminate', 'Eloquent', 'Maestros\Infrastructure', 'Identidad\Infrastructure'],
    'Identidad\Application' => ['Illuminate', 'Eloquent', 'Maestros\Infrastructure', 'Identidad\Infrastructure'],
    'BackOffice\Application' => ['Eloquent', 'BackOffice\Infrastructure', 'Maestros\Infrastructure', 'Identidad\Infrastructure'],
];

foreach ($prohibidosPorCapa as $capa => $prohibidos) {
    foreach ($prohibidos as $prohibido) {
        arch("{$capa} no conoce {$prohibido}")
            ->expect($capa)
            ->not->toUse($prohibido);
    }
}
