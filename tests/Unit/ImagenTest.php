<?php

declare(strict_types=1);

/*
 * Lo que la imagen tiene que traer y ninguna prueba ejercita, porque la
 * batería corre en el PHP del host y no adentro de la imagen.
 */

it('la imagen instala pcntl, para que eventos:consumir atienda SIGTERM', function () {
    // Sin pcntl, ConsumirCommand no atrapa la señal: en cada cambio de
    // revisión Container Apps corta el proceso a mitad de una vuelta, y el
    // mensaje en curso queda bloqueado hasta que vence.
    $dockerfile = (string) file_get_contents(dirname(__DIR__, 2).'/docker/Dockerfile');

    expect($dockerfile)->toMatch('/^RUN install-php-extensions .*\bpcntl\b/m');
});
