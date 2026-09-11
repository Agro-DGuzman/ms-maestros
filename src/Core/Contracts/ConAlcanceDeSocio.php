<?php

declare(strict_types=1);

namespace Core\Contracts;

use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Socios\CodigoDeSocio;

/**
 * Toda petición que reciba un `cardCode` implementa esta interfaz, y el
 * behavior la corta antes del handler. Un `if` en cada controlador tarde o
 * temprano se olvida en una copia.
 */
interface ConAlcanceDeSocio
{
    public function persona(): IdDePersona;

    /** Omitirlo equivale a consultar todo el alcance. */
    public function cardCode(): ?CodigoDeSocio;
}
