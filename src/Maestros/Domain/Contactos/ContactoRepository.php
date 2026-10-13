<?php

declare(strict_types=1);

namespace Maestros\Domain\Contactos;

use Core\Contracts\Repository;
use DateTimeImmutable;
use Maestros\Domain\Socios\CodigoDeSocio;

interface ContactoRepository extends Repository
{
    /**
     * Guarda lo que vino de SAP sin tocar `habilitada_el`: la habilitación es
     * del back-office. Una persona nueva queda sin habilitar.
     */
    public function replicar(PersonaDeContacto $persona): void;

    /**
     * Los socios donde este número es contacto visible, con su grupo: de ahí
     * sale si la persona entra y qué ve.
     */
    public function alcanceDeNumero(Celular $celular): AlcanceDeNumero;

    /** Activa, sin baja y de un socio visible: lo único que cuenta para la App. */
    public function visible(IdDePersona $id): ?PersonaDeContacto;

    /** @return list<PersonaDeContacto> */
    public function habilitadas(): array;

    /**
     * Los contactos del socio que no vinieron en la última entrega ya no están
     * en SAP. Va aparte de `save()` porque una réplica que no retrocede omite
     * los contactos que no son más nuevos, y omitirlos no significa que no
     * vinieran. Una baja anterior conserva su fecha.
     *
     * @param  list<IdDePersona>  $vistos
     */
    public function darDeBajaLosQueNoVinieron(CodigoDeSocio $socio, array $vistos, DateTimeImmutable $momento): void;

    /**
     * Lo mismo sobre toda la tabla, para el importador de JSON, que trae a
     * todos los contactos a la vez.
     *
     * @param  list<IdDePersona>  $vistos
     */
    public function darDeBajaAusentes(array $vistos, DateTimeImmutable $momento): void;
}
