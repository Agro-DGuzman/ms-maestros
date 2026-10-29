<?php

declare(strict_types=1);

use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Maestros\Domain\Socios\LineaDeCredito;
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

it('lee el contrato de ingesta y encuentra sus seis operaciones', function () {
    expect(operacionesDeIngestaEnElContrato())->toEqualCanonicalizing([
        'POST ingesta/v1/socios',
        'PUT ingesta/v1/socios/{cardCode}',
        'DELETE ingesta/v1/socios/{cardCode}',
        'POST ingesta/v1/productos',
        'PUT ingesta/v1/productos/{itemCode}',
        'DELETE ingesta/v1/productos/{itemCode}',
    ]);
});

it('toda operacion del contrato de ingesta existe', function () {
    expect(array_values(array_diff(operacionesDeIngestaEnElContrato(), operacionesDeIngestaEnLaApp())))->toBe([]);
});

it('la app no expone en ingesta nada que el contrato no tenga', function () {
    expect(array_values(array_diff(operacionesDeIngestaEnLaApp(), operacionesDeIngestaEnElContrato())))->toBe([]);
});

/**
 * Las rutas del contrato donde `type` es una lista.
 *
 * @param  array<mixed>  $nodo
 * @return list<string>
 */
function tiposEnListaDelContratoDeIngesta(array $nodo, string $ruta = '#'): array
{
    $encontrados = [];

    foreach ($nodo as $clave => $valor) {
        // Una propiedad que se llama `type` (la de ErrorApi) es un mapa: se
        // recorre como cualquier esquema.
        if ($clave === 'type' && is_array($valor) && array_is_list($valor)) {
            $encontrados[] = "{$ruta}/type";
        } elseif (is_array($valor)) {
            $encontrados = [...$encontrados, ...tiposEnListaDelContratoDeIngesta($valor, "{$ruta}/{$clave}")];
        }
    }

    return $encontrados;
}

it('la linea de credito es obligatoria y el contrato la valida con el patron del dominio', function () {
    /** @var array{components: array{schemas: array<string, array<string, mixed>>}} $contrato */
    $contrato = Yaml::parseFile(base_path('contrato/ingesta-api-v1.yaml'));
    $socio = $contrato['components']['schemas']['SocioIngesta'];

    expect($socio['required'])->toContain('lineaDeCredito')
        ->and($socio['properties']['lineaDeCredito']['pattern'])->toBe(LineaDeCredito::PATRON);
});

it('ningun type del contrato de ingesta es una lista', function () {
    // OpenAPI 3.1 admite `type: [string, 'null']`, pero el importador del APIM
    // no: rechaza el archivo entero con «Cannot create a scalar value». Un nulo
    // se escribe con `oneOf` y `type: 'null'`, que los dos aceptan.
    expect(tiposEnListaDelContratoDeIngesta(Yaml::parseFile(base_path('contrato/ingesta-api-v1.yaml'))))->toBe([]);
});
