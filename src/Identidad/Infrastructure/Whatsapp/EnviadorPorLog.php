<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Whatsapp;

use Identidad\Application\Contracts\EnviadorDeDesafio;
use Maestros\Domain\Contactos\Celular;
use Psr\Log\LoggerInterface;

/** Implementación de desarrollo: escribe el código en el log en vez de mandarlo. */
final readonly class EnviadorPorLog implements EnviadorDeDesafio
{
    public function __construct(private LoggerInterface $log) {}

    public function enviar(Celular $a, string $digitos): void
    {
        $this->log->info('Desafío de ingreso', ['celular' => $a->e164(), 'digitos' => $digitos]);
    }
}
