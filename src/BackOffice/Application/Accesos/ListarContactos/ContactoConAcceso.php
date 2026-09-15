<?php

declare(strict_types=1);

namespace BackOffice\Application\Accesos\ListarContactos;

use Maestros\Application\Contactos\ContactoDeBackOffice;

/**
 * Una fila de la pantalla, con las dos verdades separadas.
 *
 * `$replica->estaHabilitada` es lo que dice SAP; `$tieneCredencial` es si esta
 * persona puede entrar hoy. Divergen, y mostrarlas como una sola cosa fue lo
 * que hacía que el interruptor no respondiera al operador: él actúa sobre la
 * segunda y la pantalla leía la primera.
 */
final readonly class ContactoConAcceso
{
    public function __construct(
        public ContactoDeBackOffice $replica,
        public bool $tieneCredencial,
    ) {}

    /** SAP la marca habilitada pero nunca le dimos acceso. */
    public function faltaDarleAcceso(): bool
    {
        return $this->replica->estaHabilitada && ! $this->tieneCredencial;
    }

    /** Puede entrar, pero SAP ya no la marca habilitada. */
    public function conservaAccesoSinRespaldo(): bool
    {
        return $this->tieneCredencial && ! $this->replica->estaHabilitada;
    }
}
