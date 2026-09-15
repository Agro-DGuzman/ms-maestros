<?php

declare(strict_types=1);

namespace Maestros\Domain\Contactos;

use Core\Contracts\Repository;
use DateTimeImmutable;

interface ContactoRepository extends Repository
{
    public function porCelular(Celular $celular): ?PersonaDeContacto;

    /** @return list<PersonaDeContacto> */
    public function habilitadas(): array;

    /**
     * Deja constancia de que la importación vio a estas personas, hayan
     * traído cambios o no. Va aparte de `save()` porque una réplica que no
     * retrocede omite las filas que no son más nuevas, y omitirlas no
     * significa que no vinieran en el archivo.
     *
     * @param  list<IdDePersona>  $personas
     */
    public function marcarVistasEnImportacion(array $personas, DateTimeImmutable $momento): void;
}
