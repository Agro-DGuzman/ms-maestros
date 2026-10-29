<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Psr\Log\AbstractLogger;
use Stringable;

final class LoggerEspia extends AbstractLogger
{
    /** @var list<array{nivel: string, mensaje: string, contexto: array<array-key, mixed>}> */
    public array $registros = [];

    /** @param array<array-key, mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->registros[] = ['nivel' => (string) $level, 'mensaje' => (string) $message, 'contexto' => $context];
    }
}
