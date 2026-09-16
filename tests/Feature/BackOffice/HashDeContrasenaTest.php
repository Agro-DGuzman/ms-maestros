<?php

declare(strict_types=1);

it('imprime el registro del operador', function () {
    // El formato lo verifica el test de ida y vuelta de OperadorConContrasena;
    // acá solo importa que el comando esté cableado y lo imprima.
    $this->artisan('backoffice:hash', [
        'correo' => 'ana@agropartners.com.bo',
        'nombre' => 'Ana Suárez',
    ])
        ->expectsQuestion('Contraseña', 'Clave-De-Ana-1')
        ->expectsOutputToContain('ana@agropartners.com.bo|$2y$')
        ->assertExitCode(0);
});

it('no acepta una contrasena corta', function () {
    // Es la llave del back-office y la escribe una persona: si acepta cuatro
    // caracteres, alguien va a poner cuatro.
    $this->artisan('backoffice:hash', [
        'correo' => 'ana@agropartners.com.bo',
        'nombre' => 'Ana Suárez',
    ])
        ->expectsQuestion('Contraseña', 'corta')
        ->assertExitCode(1);
});
