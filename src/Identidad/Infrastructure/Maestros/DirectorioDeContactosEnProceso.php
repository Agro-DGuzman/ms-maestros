<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Maestros;

use Core\Contracts\Mediator;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Maestros\Application\Contactos\BuscarPorCelular\BuscarPorCelular;
use Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContexto;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;

final readonly class DirectorioDeContactosEnProceso implements DirectorioDeContactos
{
    public function __construct(private Mediator $mediator) {}

    public function buscarPorCelular(Celular $celular): ?IdDePersona
    {
        $resultado = $this->mediator->send(new BuscarPorCelular($celular));

        return $resultado->isSuccess ? $resultado->value() : null;
    }

    public function contexto(IdDePersona $id): ?ContextoDeContacto
    {
        $resultado = $this->mediator->send(new ObtenerContexto($id));

        return $resultado->isSuccess ? $resultado->value() : null;
    }
}
