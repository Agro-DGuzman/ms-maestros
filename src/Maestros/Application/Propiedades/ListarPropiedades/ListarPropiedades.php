<?php

declare(strict_types=1);

namespace Maestros\Application\Propiedades\ListarPropiedades;

use Core\Contracts\ConAlcanceDeSocio;
use Core\Contracts\Request;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Socios\CodigoDeSocio;

final readonly class ListarPropiedades implements ConAlcanceDeSocio, Request
{
    public function __construct(
        private IdDePersona $persona,
        private CodigoDeSocio $socio,
    ) {}

    public function persona(): IdDePersona
    {
        return $this->persona;
    }

    public function cardCode(): CodigoDeSocio
    {
        return $this->socio;
    }
}
