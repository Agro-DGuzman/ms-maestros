<?php

declare(strict_types=1);

namespace Maestros\Domain\Aplicacion;

enum EstadoDeVersion: string
{
    case Vigente = 'vigente';
    case ActualizacionSugerida = 'actualizacion_sugerida';
    case ActualizacionObligatoria = 'actualizacion_obligatoria';

    /**
     * La App decide por esto y no por el estado: el enumerado puede crecer,
     * y un estado nuevo que la App no conoce no debe bloquear a nadie.
     */
    public function permiteContinuar(): bool
    {
        return $this !== self::ActualizacionObligatoria;
    }
}
