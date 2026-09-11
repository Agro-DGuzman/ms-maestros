<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Maestros\Domain\Contactos\Celular;

interface EnviadorDeDesafio
{
    public function enviar(Celular $a, string $digitos): void;
}
