<?php

declare(strict_types=1);

use Symfony\Component\Yaml\Yaml;

/*
 * Lo que el pipeline tiene que hacer en cada despliegue y ninguna otra prueba
 * ve: un Container App que no actualiza queda con el código viejo, y el
 * pipeline sale en verde igual.
 */

/** @return array{variables: array<string, string>, script: string} */
function despliegueDelPipeline(): array
{
    /** @var array{variables: array<string, string>, stages: list<array<string, mixed>>} $pipeline */
    $pipeline = Yaml::parseFile(dirname(__DIR__, 2).'/azure-pipelines.yml');

    foreach ($pipeline['stages'] as $etapa) {
        if (($etapa['stage'] ?? null) === 'Desplegar') {
            /** @var array{jobs: list<array{strategy: array{runOnce: array{deploy: array{steps: list<array{inputs: array{inlineScript: string}}>}}}}>} $etapa */
            return [
                'variables' => $pipeline['variables'],
                'script' => $etapa['jobs'][0]['strategy']['runOnce']['deploy']['steps'][0]['inputs']['inlineScript'],
            ];
        }
    }

    throw new RuntimeException('El pipeline no tiene la etapa Desplegar');
}

/**
 * El nombre de cada Container App que el script actualiza, con la imagen que le pone.
 *
 * @return array<string, string>
 */
function imagenesQueDespliega(string $script): array
{
    $imagenes = [];

    foreach (array_slice(explode('az containerapp update', $script), 1) as $comando) {
        if (preg_match('/--name (\S+)/', $comando, $nombre) === 1 && preg_match('/--image (\S+)/', $comando, $imagen) === 1) {
            $imagenes[$nombre[1]] = $imagen[1];
        }
    }

    return $imagenes;
}

it('el despliegue actualiza la app, el worker y el consumidor con la misma imagen', function () {
    // El consumidor faltaba: el pipeline actualizaba la app y el worker, y
    // ms-maestros-consumidor se quedaba con la imagen con que se lo configuró.
    ['variables' => $variables, 'script' => $script] = despliegueDelPipeline();

    expect($variables['consumidorDeContenedor'] ?? null)->toBe('ms-maestros-consumidor')
        ->and(imagenesQueDespliega($script))->toBe([
            '$(appDeContenedor)' => '$(servidorDelRegistro)/$(repositorioDeImagen):$(etiqueta)',
            '$(workerDeContenedor)' => '$(servidorDelRegistro)/$(repositorioDeImagen):$(etiqueta)',
            '$(consumidorDeContenedor)' => '$(servidorDelRegistro)/$(repositorioDeImagen):$(etiqueta)',
        ]);
});

it('el despliegue espera que la revision nueva del consumidor quede lista, como la del worker', function () {
    // Si la revisión no arranca, la vieja sigue corriendo y nadie se entera.
    ['script' => $script] = despliegueDelPipeline();

    expect($script)->toMatch('/for contenedor in \$\(workerDeContenedor\) \$\(consumidorDeContenedor\)/')
        ->and($script)->toContain('latestReadyRevisionName');
});
