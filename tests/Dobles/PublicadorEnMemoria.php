<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Maestros\Application\Eventos\PublicadorDeEventos;
use RuntimeException;

/** Recuerda los sobres publicados, en orden; con `queFalla()` lanza como un 401 del bus. */
final class PublicadorEnMemoria implements PublicadorDeEventos
{
    /** @var list<array<array-key, mixed>> */
    public array $publicados = [];

    private bool $falla = false;

    public static function queFalla(): self
    {
        $publicador = new self;
        $publicador->falla = true;

        return $publicador;
    }

    public function publicar(array $sobre): void
    {
        if ($this->falla) {
            throw new RuntimeException('Service Bus respondió 401 al publicar');
        }

        $this->publicados[] = $sobre;
    }
}
