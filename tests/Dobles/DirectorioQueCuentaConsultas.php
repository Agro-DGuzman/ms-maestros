<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Identidad\Application\Contracts\DirectorioDeContactos;
use Maestros\Application\Contactos\ObtenerContexto\ContextoDeContacto;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\MotivoDeNoIngreso;

/**
 * Cuenta toda consulta a Maestros. Sirve para afirmar que algo NO consulta:
 * `POST /auth/otp` responde igual exista o no el número porque no pregunta.
 */
final class DirectorioQueCuentaConsultas implements DirectorioDeContactos
{
    public int $consultas = 0;

    public function contexto(IdDePersona $id): ?ContextoDeContacto
    {
        $this->consultas++;

        return null;
    }

    public function motivoDeNoIngreso(Celular $celular): ?MotivoDeNoIngreso
    {
        $this->consultas++;

        return MotivoDeNoIngreso::SinContactos;
    }

    /** @return list<IdDePersona> */
    public function habilitadas(): array
    {
        $this->consultas++;

        return [];
    }
}
