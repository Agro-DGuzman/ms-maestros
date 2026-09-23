<?php

declare(strict_types=1);

use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

/*
 * La App se construye contra el contrato, no contra este código. Una ruta del
 * contrato que acá no existe es un 404 que el equipo de la App descubre recién
 * integrando: así estuvo `/socios`, publicado en APIM y ausente acá.
 */

/**
 * Lo que el contrato le asigna a este servicio y todavía no existe. Se achica:
 * implementar una hace fallar el test hasta sacarla de acá.
 */
const PENDIENTES = [
    'GET v1/version',
    'GET v1/socios/{cardCode}/propiedades',
    'GET v1/productos',
    'GET v1/productos/{itemCode}',
    'GET v1/productos/{itemCode}/documentos',
    'GET v1/categorias',
    'GET v1/bancos',
    'GET v1/contactos/atencion-al-cliente',
];

/** @return list<string> las operaciones del contrato que son de maestros */
function operacionesDelContrato(): array
{
    /** @var array{paths: array<string, array<string, mixed>>} $contrato */
    $contrato = Yaml::parseFile(base_path('contrato/agropartners-api-v1.yaml'));
    $operaciones = [];

    foreach ($contrato['paths'] as $ruta => $definicion) {
        if (($definicion['x-agropartners-servicio'] ?? null) !== 'maestros') {
            continue;
        }

        foreach (['get', 'post', 'put', 'patch', 'delete'] as $metodo) {
            if (isset($definicion[$metodo])) {
                $operaciones[] = strtoupper($metodo).' v1'.$ruta;
            }
        }
    }

    return $operaciones;
}

/** @return list<string> las rutas de la API que la aplicación registra */
function operacionesDeLaApp(): array
{
    $operaciones = [];

    foreach (Route::getRoutes()->getRoutes() as $ruta) {
        assert($ruta instanceof Ruta);

        if (! str_starts_with($ruta->uri(), 'v1/')) {
            continue;
        }

        foreach (array_diff($ruta->methods(), ['HEAD']) as $metodo) {
            $operaciones[] = $metodo.' '.$ruta->uri();
        }
    }

    return $operaciones;
}

it('lee el contrato y encuentra las operaciones de maestros', function () {
    // Si el recolector no encontrara nada, los tests de abajo pasarían en
    // verde sin comparar nada.
    expect(operacionesDelContrato())->toContain('POST v1/auth/otp', 'GET v1/mi-cuenta')
        ->and(count(operacionesDelContrato()))->toBeGreaterThanOrEqual(10);
});

it('toda operacion de maestros del contrato existe o esta pendiente', function () {
    $faltan = array_diff(operacionesDelContrato(), operacionesDeLaApp(), PENDIENTES);

    expect(array_values($faltan))->toBe([]);
});

it('ninguna pendiente existe ya', function () {
    expect(array_values(array_intersect(PENDIENTES, operacionesDeLaApp())))->toBe([]);
});

it('toda pendiente esta en el contrato', function () {
    // Una pendiente mal escrita, o de una ruta que el contrato ya no tiene,
    // nunca haría fallar nada.
    expect(array_values(array_diff(PENDIENTES, operacionesDelContrato())))->toBe([]);
});

it('la app no expone en v1 nada que el contrato no tenga', function () {
    expect(array_values(array_diff(operacionesDeLaApp(), operacionesDelContrato())))->toBe([]);
});
