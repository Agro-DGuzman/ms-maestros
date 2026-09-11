<?php

declare(strict_types=1);

namespace Identidad\Domain\Desafios;

use Core\Contracts\Repository;
use DateTimeImmutable;
use Maestros\Domain\Contactos\Celular;

interface DesafioRepository extends Repository
{
    /** Cuántos desafíos se emitieron para ese celular desde una marca. */
    public function emitidosDesde(Celular $celular, DateTimeImmutable $desde): int;
}
