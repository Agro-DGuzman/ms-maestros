<?php

declare(strict_types=1);

use Maestros\Infrastructure\ServiceBus\FirmaSas;

it('firma como Azure', function () {
    // El vector sale del ejemplo PHP de Microsoft corrido desde un archivo: el
    // separador entre recurso y vencimiento es un salto de línea. Calculado
    // con `php -r` desde Git Bash, MSYS lo convierte en "/n" y da otra firma.
    expect(FirmaSas::token('https://agp-dev.servicebus.windows.net/sap-eventos', 'escucha', 'Y2xhdmUtZGUtcHJ1ZWJh', 1790000000))
        ->toBe('SharedAccessSignature sr=https%3a%2f%2fagp-dev.servicebus.windows.net%2fsap-eventos&sig=o7GOAfHgdIWYp5uBCcJerMjR7rvS%2FSSxqBwupKSEB54%3D&se=1790000000&skn=escucha');
});

it('la firma cambia con el vencimiento', function () {
    $recurso = 'https://agp-dev.servicebus.windows.net/sap-eventos';

    expect(FirmaSas::token($recurso, 'escucha', 'clave', 1790000000))
        ->not->toBe(FirmaSas::token($recurso, 'escucha', 'clave', 1790000001));
});
