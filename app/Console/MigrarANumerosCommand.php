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
        $migradas = 0;

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

                continue;
            }

            $numero = IdDePersona::deCelular($celular);
            $habilitado = $mediator->send(new HabilitarPersona($numero));

            // La vieja se deshabilita aunque habilitar el número haya fallado:
            // después del despliegue ese id ya no sirve para entrar, y dejarla
            // activa es una credencial que nadie controla.
            $mediator->send(new DeshabilitarPersona($vieja));
            $migradas++;

            $this->line($habilitado->isSuccess
                ? "{$vieja->value()} → {$numero->value()}"
                : "{$vieja->value()} → {$numero->value()} no se pudo habilitar: [{$habilitado->error->code}] {$habilitado->error->description}");
        }

        $this->info("Migradas {$migradas}.");

        return self::SUCCESS;
    }
}
