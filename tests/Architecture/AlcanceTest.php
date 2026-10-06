<?php

declare(strict_types=1);

use Core\Contracts\ConAlcanceDeSocio;
use Core\Contracts\EscrituraDeMaquina;
use Core\Contracts\Request;
use Maestros\Domain\Socios\CodigoDeSocio;
use Tests\Soporte\Clases;

it('el recolector encuentra las peticiones reales del proyecto', function () {
    // Guarda contra el defecto que tenía este archivo: si el recolector
    // devuelve poco o nada, el test de abajo pasa sin verificar.
    $peticiones = array_filter(
        Clases::deSrc(),
        static fn (string $c): bool => is_subclass_of($c, Request::class),
    );

    expect(count($peticiones))->toBeGreaterThanOrEqual(7);
});

it('toda peticion con cardCode declara su alcance', function () {
    $sinDeclarar = [];

    foreach (Clases::deSrc() as $clase) {
        if (! is_subclass_of($clase, Request::class)) {
            continue;
        }

        $reflexion = new ReflectionClass($clase);
        $constructor = $reflexion->getConstructor();

        if ($constructor === null) {
            continue;
        }

        foreach ($constructor->getParameters() as $parametro) {
            $tipo = $parametro->getType();

            $esCardCode = $tipo instanceof ReflectionNamedType
                && $tipo->getName() === CodigoDeSocio::class;

            if ($esCardCode && ! $reflexion->implementsInterface(ConAlcanceDeSocio::class)
                && ! $reflexion->implementsInterface(EscrituraDeMaquina::class)) {
                $sinDeclarar[] = $clase;
            }
        }
    }

    expect($sinDeclarar)->toBe([]);
});

it('solo las peticiones de la ingesta se declaran escritura de maquina', function () {
    // La excepcion a la regla de arriba es para el Sincronizador, que no tiene
    // persona detras. En cualquier otra peticion seria saltearse el alcance.
    $deMaquina = array_values(array_filter(
        Clases::deSrc(),
        static fn (string $c): bool => is_subclass_of($c, EscrituraDeMaquina::class),
    ));

    $fuera = array_values(array_filter(
        $deMaquina,
        static fn (string $c): bool => ! str_starts_with($c, 'Maestros\\Application\\Ingesta\\'),
    ));

    // Sin esto, un recolector que no encuentra nada dejaría pasar la regla.
    expect(count($deMaquina))->toBeGreaterThanOrEqual(2)
        ->and($fuera)->toBe([]);
});
