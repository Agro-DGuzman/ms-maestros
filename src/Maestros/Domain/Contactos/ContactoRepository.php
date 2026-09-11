<?php

declare(strict_types=1);

namespace Maestros\Domain\Contactos;

use Core\Contracts\Repository;

interface ContactoRepository extends Repository
{
    public function porCelular(Celular $celular): ?PersonaDeContacto;
}
