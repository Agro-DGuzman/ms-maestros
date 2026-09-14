<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Habilitacion;

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Illuminate\Console\Command;

/** Detecta el estado parcial: habilitado acá y no allá, o al revés. */
final class ConciliarIdentidadesCommand extends Command
{
    protected $signature = 'identidad:conciliar';

    protected $description = 'Compara las personas habilitadas contra los usuarios del directorio';

    public function handle(
        DirectorioDeContactos $contactos,
        DirectorioDeIdentidades $directorio,
        BovedaDeContrasenas $boveda,
    ): int {
        $discrepancias = 0;

        foreach ($contactos->habilitadas() as $persona) {
            if (! $directorio->existe($persona)) {
                $this->warn("Falta en el directorio: {$persona->value()}");
                $discrepancias++;

                continue;
            }

            if ($boveda->leer($persona) === null) {
                $this->warn("Sin contraseña guardada: {$persona->value()}");
                $discrepancias++;
            }
        }

        if ($discrepancias === 0) {
            $this->info('Sin discrepancias.');

            return self::SUCCESS;
        }

        $this->error("{$discrepancias} discrepancia(s). Corregir con identidad:habilitar.");

        return self::FAILURE;
    }
}
