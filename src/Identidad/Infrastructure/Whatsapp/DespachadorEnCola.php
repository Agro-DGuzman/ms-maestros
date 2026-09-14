<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Whatsapp;

use Identidad\Application\Contracts\DespachadorDeDesafio;
use Maestros\Domain\Contactos\Celular;

final class DespachadorEnCola implements DespachadorDeDesafio
{
    public function despachar(Celular $celular, string $digitos): void
    {
        EnviarDesafioJob::dispatch($celular->e164(), $digitos);
    }
}
