<?php

declare(strict_types=1);

namespace BackOffice\Application\Accesos\ListarContactos;

use Maestros\Application\Contactos\ContactoDeBackOffice;

/**
 * Una fila de la pantalla: un contacto de SAP y si su número tiene acceso.
 * Varias filas con el mismo número son la misma persona, y comparten acceso.
 */
final readonly class ContactoConAcceso
{
    public function __construct(
        public ContactoDeBackOffice $replica,
        public bool $tieneCredencial,
    ) {}

    /**
     * El acceso es del número: se ofrece solo si ese número puede entrar hoy.
     * Una fila dada de baja lo ofrece igual si otro contacto con el mismo
     * número sigue en SAP, porque es la misma persona.
     */
    public function puedeDarAcceso(): bool
    {
        return $this->replica->personaDelNumero !== null
            && ! $this->tieneCredencial
            && $this->replica->alcanceDelNumero?->entra() === true;
    }

    /** Tiene acceso pero hoy no puede entrar: hay algo que corregir en SAP o quitarle. */
    public function conservaAccesoSinPoderEntrar(): bool
    {
        return $this->tieneCredencial && $this->replica->alcanceDelNumero?->entra() !== true;
    }
}
