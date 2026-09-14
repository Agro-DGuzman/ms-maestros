<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Maestros;

use Core\Contracts\Mediator;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Maestros\Application\Contactos\BuscarPorCelular\BuscarPorCelular;
use Maestros\Application\Contactos\ListarHabilitadas\ListarHabilitadas;
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

        if ($resultado->isFailure()) {
            return null;
        }

        assert($resultado instanceof ResultWithValue);
        $persona = $resultado->value();
        assert($persona instanceof IdDePersona);

        return $persona;
    }

    /** @return list<IdDePersona> */
    public function habilitadas(): array
    {
        $resultado = $this->mediator->send(new ListarHabilitadas);

        if ($resultado->isFailure()) {
            return [];
        }

        assert($resultado instanceof ResultWithValue);

        /** @var list<IdDePersona> $personas */
        $personas = $resultado->value();

        return $personas;
    }

    public function contexto(IdDePersona $id): ?ContextoDeContacto
    {
        $resultado = $this->mediator->send(new ObtenerContexto($id));

        if ($resultado->isFailure()) {
            return null;
        }

        assert($resultado instanceof ResultWithValue);
        $contexto = $resultado->value();
        assert($contexto instanceof ContextoDeContacto);

        return $contexto;
    }
}
