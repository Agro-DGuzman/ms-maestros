<?php

declare(strict_types=1);

use Core\Contracts\ConAlcanceDeSocio;
use Core\Contracts\Request;
use Maestros\Domain\Socios\CodigoDeSocio;

it('toda peticion con cardCode declara su alcance', function () {
    $sinDeclarar = [];

    foreach (get_declared_classes() as $clase) {
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

            if ($esCardCode && ! $reflexion->implementsInterface(ConAlcanceDeSocio::class)) {
                $sinDeclarar[] = $clase;
            }
        }
    }

    expect($sinDeclarar)->toBe([]);
});
