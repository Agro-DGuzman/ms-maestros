<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Alcance;

use Maestros\Application\Alcance\ResolutorDeAlcance;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioRepository;

/**
 * El alcance es el grupo económico, y el grupo sale de OCRG (ADR 0003).
 * Se resuelve por petición contra la base local en vez de viajar en el token:
 * un socio reasignado en SAP cambia de alcance de inmediato.
 */
final readonly class ResolutorPorGrupo implements ResolutorDeAlcance
{
    public function __construct(
        private ContactoRepository $contactos,
        private SocioRepository $socios,
    ) {}

    public function alcanza(IdDePersona $persona, CodigoDeSocio $socio): bool
    {
        $contacto = $this->contactos->find($persona, readOnly: true);

        if (! $contacto instanceof PersonaDeContacto) {
            return false;
        }

        $suSocio = $this->socios->find($contacto->codigoDeSocio(), readOnly: true);
        $pedido = $this->socios->find($socio, readOnly: true);

        if (! $suSocio instanceof Socio || ! $pedido instanceof Socio) {
            return false;
        }

        return $pedido->idDeGrupo()->equals($suSocio->idDeGrupo());
    }
}
