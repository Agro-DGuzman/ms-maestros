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
        $contacto = $this->contactos->visible($persona);

        if (! $contacto instanceof PersonaDeContacto) {
            return false;
        }

        $suSocio = $this->socios->find($contacto->codigoDeSocio());
        $pedido = $this->socios->find($socio);

        if (! $suSocio instanceof Socio || ! $pedido instanceof Socio || ! $pedido->esVisible()) {
            return false;
        }

        $suGrupo = $suSocio->grupo();

        // Sin grupo, el alcance es su propio socio (D12): un grupo de uno no
        // junta a nadie, que es lo que un grupo SIN_ASIGNAR habría hecho.
        if ($suGrupo === null) {
            return $pedido->codigoDeSocio()->equals($suSocio->codigoDeSocio());
        }

        $grupoDelPedido = $pedido->grupo();

        return $grupoDelPedido !== null && $grupoDelPedido->equals($suGrupo);
    }
}
