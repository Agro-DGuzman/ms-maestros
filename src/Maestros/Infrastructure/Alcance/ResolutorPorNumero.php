<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Alcance;

use Maestros\Application\Alcance\ResolutorDeAlcance;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Socios\CodigoDeSocio;

/**
 * La persona es su número, y alcanza los socios donde ese número es contacto
 * visible. El grupo no suma socios: solo decide si el número entra.
 */
final readonly class ResolutorPorNumero implements ResolutorDeAlcance
{
    public function __construct(private ContactoRepository $contactos) {}

    public function alcanza(IdDePersona $persona, CodigoDeSocio $socio): bool
    {
        $celular = $persona->celular();

        // Un id que no sale de un número (un token anterior al cambio) no es
        // una persona: no alcanza nada.
        if ($celular === null) {
            return false;
        }

        return $this->contactos->alcanceDeNumero($celular)->alcanza($socio);
    }
}
