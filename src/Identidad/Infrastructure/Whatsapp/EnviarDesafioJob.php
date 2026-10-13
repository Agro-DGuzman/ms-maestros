<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Whatsapp;

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;

final class EnviarDesafioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** El celular viaja como string E.164: la cola serializa, y un objeto de valor no aporta acá. */
    public function __construct(
        private readonly string $celularE164,
        private readonly string $digitos,
    ) {}

    public function handle(DirectorioDeContactos $directorio, BovedaDeContrasenas $boveda, EnviadorDeDesafio $enviador): void
    {
        $celular = Celular::desdeLocalBoliviano($this->celularE164);
        $persona = IdDePersona::deCelular($celular);

        // La consulta vive acá y no en el handler: es lo que hacía que el
        // endpoint tardara distinto según si el número existía. Solo recibe el
        // código un número que puede entrar: habilitado y en un solo grupo.
        if ($boveda->leer($persona) === null || $directorio->contexto($persona) === null) {
            return;
        }

        $enviador->enviar($celular, $this->digitos);
    }
}
