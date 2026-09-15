<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Habilitacion;

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Illuminate\Console\Command;
use Maestros\Domain\Contactos\IdDePersona;

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
        $habilitadas = $contactos->habilitadas();

        foreach ($habilitadas as $persona) {
            if (! $directorio->estaActivo($persona)) {
                $this->warn("Habilitada pero sin acceso en el directorio: {$persona->value()}");
                $discrepancias++;

                continue;
            }

            if ($boveda->leer($persona) === null) {
                $this->warn("Sin contraseña guardada: {$persona->value()}");
                $discrepancias++;
            }
        }

        // La otra dirección, que es la que detecta una revocación a medias: a
        // esta persona le dimos acceso y la réplica ya no la da por habilitada.
        // Nadie lo deshace solo, así que hay que verlo para ir a corregirlo.
        $sigueHabilitada = array_map(
            static fn (IdDePersona $p): string => $p->value(),
            $habilitadas,
        );

        foreach ($boveda->personas() as $persona) {
            if (in_array($persona->value(), $sigueHabilitada, true)) {
                continue;
            }

            $this->warn("Con credencial pero ya no habilitada en la réplica: {$persona->value()}");
            $discrepancias++;
        }

        if ($discrepancias === 0) {
            $this->info('Sin discrepancias.');

            return self::SUCCESS;
        }

        // No siempre se corrige igual: a quien le falta acceso se le da con
        // `identidad:habilitar`, y a quien lo conserva sin estar habilitado
        // se le quita con `identidad:deshabilitar`.
        $this->error("{$discrepancias} discrepancia(s). Corregir con identidad:habilitar o identidad:deshabilitar.");

        return self::FAILURE;
    }
}
