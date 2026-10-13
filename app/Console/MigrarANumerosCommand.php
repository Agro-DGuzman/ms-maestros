<?php

declare(strict_types=1);

namespace App\Console;

use Core\Contracts\Mediator;
use Core\Results\DomainException;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Habilitacion\DeshabilitarPersona\DeshabilitarPersona;
use Identidad\Application\Habilitacion\HabilitarPersona\HabilitarPersona;
use Illuminate\Console\Command;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;

/**
 * Una sola vez, al desplegar «la persona es el número»: cada credencial que
 * todavía tiene id por contacto pasa al número de ese contacto. Vive en el
 * host porque cruza los dos módulos; después de correrlo en Azure, se borra.
 */
final class MigrarANumerosCommand extends Command
{
    protected $signature = 'identidad:migrar-a-numeros';

    protected $description = 'Pasa las credenciales con id por contacto al id del número';

    public function handle(BovedaDeContrasenas $boveda, ContactoRepository $contactos, Mediator $mediator): int
    {
        // Corre una sola vez en Azure: lo que falle tiene que verse en la
        // salida y en el código de salida, no solo en una línea entre otras.
        $migradas = 0;
        $conError = 0;

        foreach ($boveda->personas() as $vieja) {
            if ($vieja->celular() !== null) {
                continue;
            }

            try {
                $contacto = $contactos->find($vieja);
            } catch (DomainException) {
                $contacto = null;
            }

            $celular = $contacto instanceof PersonaDeContacto ? $contacto->celular() : null;

            if ($celular === null) {
                $this->warn("{$vieja->value()}: sin contacto o sin celular válido, no se toca.");
                $conError++;

                continue;
            }

            $numero = IdDePersona::deCelular($celular);
            $habilitado = $mediator->send(new HabilitarPersona($numero));

            // La vieja se deshabilita aunque habilitar el número haya fallado:
            // después del despliegue ese id ya no sirve para entrar, y dejarla
            // activa es una credencial que nadie controla.
            $deshabilitada = $mediator->send(new DeshabilitarPersona($vieja));

            if ($habilitado->isFailure()) {
                $this->error("{$vieja->value()} → {$numero->value()}: no se pudo habilitar el número: [{$habilitado->error->code}] {$habilitado->error->description}");
            }

            if ($deshabilitada->isFailure()) {
                $this->error("{$vieja->value()}: no se pudo deshabilitar el id viejo: [{$deshabilitada->error->code}] {$deshabilitada->error->description}");
            }

            if ($habilitado->isFailure() || $deshabilitada->isFailure()) {
                $conError++;

                continue;
            }

            $migradas++;
            $this->line("{$vieja->value()} → {$numero->value()}");
        }

        $this->info("Migradas {$migradas}, con error {$conError}.");

        return $conError === 0 ? self::SUCCESS : self::FAILURE;
    }
}
