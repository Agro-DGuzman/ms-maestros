<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Push;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Manda un aviso a un teléfono. Lleva el id de la sesión y no el token: así
 * el token no queda en la tabla de trabajos fallidos, y si la App lo cambió
 * mientras esperaba se usa el del momento (NP22).
 *
 * Va en su propia cola, `push`, para que cien avisos de catálogo no demoren
 * el código de alguien que espera para entrar (NP23).
 */
final class EnviarPushJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** @param array<string, string|null> $aviso lo que dejó AvisoParaPush::aArreglo() */
    public function __construct(
        public readonly string $idDeSesion,
        public readonly array $aviso,
    ) {
        $this->onQueue('push');
    }
}
