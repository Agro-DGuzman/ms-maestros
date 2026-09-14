<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Identidad\Application\Contracts\EnviadorDeDesafio;
use Maestros\Domain\Contactos\Celular;

final class EnviadorQueRecuerda implements EnviadorDeDesafio
{
    public string $ultimoCodigo = '';

    public function enviar(Celular $a, string $digitos): void
    {
        $this->ultimoCodigo = $digitos;
    }
}
