<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Habilitacion;

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Illuminate\Console\Command;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Habilitado es tener credencial: un usuario activo en el directorio y su
 * contraseña en la bóveda, con el id del número. Se miran las dos direcciones,
 * porque nada propaga solo una habilitación o una revocación a medias.
 */
final class ConciliarIdentidadesCommand extends Command
{
    protected $signature = 'identidad:conciliar';

    protected $description = 'Compara los números con credencial contra los usuarios del directorio';

    public function handle(
        DirectorioDeContactos $contactos,
        DirectorioDeIdentidades $directorio,
        BovedaDeContrasenas $boveda,
    ): int {
        $activos = $directorio->activos();

        // Sin la lista del directorio no se puede afirmar nada: decir «sin
        // discrepancias» sería mentir.
        if ($activos === null) {
            $this->error('El directorio de identidades no respondió.');

            return self::FAILURE;
        }

        $discrepancias = 0;
        $conCredencial = $boveda->personas();

        foreach ($conCredencial as $persona) {
            $celular = $persona->celular();

            if ($celular === null) {
                $this->warn("Credencial con id por contacto, anterior al cambio a números: {$persona->value()}");
                $discrepancias++;

                continue;
            }

            if (! $directorio->estaActivo($persona)) {
                $this->warn("Credencial guardada pero el usuario no está activo en el directorio: {$persona->value()}");
                $discrepancias++;

                continue;
            }

            // No es un error de credenciales: el acceso sigue a SAP, y SAP puede
            // corregirlo solo. Se informa para que alguien decida.
            $motivo = $contactos->motivoDeNoIngreso($celular);

            if ($motivo !== null) {
                $this->line("Habilitado pero hoy no ingresa ({$motivo->value}): {$persona->value()}");
            }
        }

        $guardadas = array_map(static fn (IdDePersona $p): string => $p->value(), $conCredencial);

        foreach ($activos as $persona) {
            if (! in_array($persona->value(), $guardadas, true)) {
                $this->warn("Usuario activo en el directorio sin credencial guardada: {$persona->value()}");
                $discrepancias++;
            }
        }

        if ($discrepancias === 0) {
            $this->info('Sin discrepancias.');

            return self::SUCCESS;
        }

        // No siempre se corrige igual: a un número que le falta la credencial
        // se le vuelve a dar acceso, y a uno que la conserva sin estar
        // habilitado se le quita con `identidad:deshabilitar`. Las credenciales
        // por contacto las migra `identidad:migrar-a-numeros`.
        $this->error("{$discrepancias} discrepancia(s).");

        return self::FAILURE;
    }
}
