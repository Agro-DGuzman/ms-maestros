<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Identidad\Application\Contracts\EnviadorDeDesafio;
use Maestros\Domain\Contactos\Celular;

final class EnviadorEspia implements EnviadorDeDesafio
{
    /** @var list<array{celular: string, digitos: string}> */
    public array $enviados = [];

    public function enviar(Celular $a, string $digitos): void
    {
        $this->enviados[] = ['celular' => $a->e164(), 'digitos' => $digitos];
    }
}
