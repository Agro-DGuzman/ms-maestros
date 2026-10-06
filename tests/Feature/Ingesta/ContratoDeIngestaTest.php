<?php

declare(strict_types=1);

use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

/*
 * El Sincronizador se construye contra el contrato de ingesta, como la App
 * contra el suyo: una operación que falte acá es un FALLIDO que se descubre
 * recién en la prueba de punta a punta.
 */

/** @return list<string> */
function operacionesDeIngestaEnElContrato(): array
{
    /** @var array{paths: array<string, array<string, mixed>>} $contrato */
    $contrato = Yaml::parseFile(base_path('contrato/ingesta-api-v1.yaml'));
    $operaciones = [];

    foreach ($contrato['paths'] as $ruta => $definicion) {
        foreach (['get', 'post', 'put', 'patch', 'delete'] as $metodo) {
            if (isset($definicion[$metodo])) {
                $operaciones[] = strtoupper($metodo).' ingesta/v1'.$ruta;
            }
        }
    }

    return $operaciones;
}

/** @return list<string> */
function operacionesDeIngestaEnLaApp(): array
{
    $operaciones = [];

    foreach (Route::getRoutes()->getRoutes() as $ruta) {
        assert($ruta instanceof Ruta);

        if (! str_starts_with($ruta->uri(), 'ingesta/')) {
            continue;
        }

        foreach (array_diff($ruta->methods(), ['HEAD']) as $metodo) {
            $operaciones[] = $metodo.' '.$ruta->uri();
        }
    }

    return $operaciones;
}

it('lee el contrato de ingesta y encuentra sus tres operaciones', function () {
    expect(operacionesDeIngestaEnElContrato())->toEqualCanonicalizing([
        'POST ingesta/v1/socios',
        'PUT ingesta/v1/socios/{cardCode}',
        'DELETE ingesta/v1/socios/{cardCode}',
    ]);
});

it('toda operacion del contrato de ingesta existe', function () {
    expect(array_values(array_diff(operacionesDeIngestaEnElContrato(), operacionesDeIngestaEnLaApp())))->toBe([]);
});

it('la app no expone en ingesta nada que el contrato no tenga', function () {
    expect(array_values(array_diff(operacionesDeIngestaEnLaApp(), operacionesDeIngestaEnElContrato())))->toBe([]);
});
