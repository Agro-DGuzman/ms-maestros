<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Habilitacion;

use Core\Contracts\Mediator;
use Core\Results\DomainException;
use Identidad\Application\Habilitacion\DeshabilitarPersona\DeshabilitarPersona;
use Illuminate\Console\Command;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;

final class DeshabilitarPersonaCommand extends Command
{
    protected $signature = 'identidad:deshabilitar {celular : Celular de la persona, con o sin +591}';

    protected $description = 'Quita el acceso a la App: bloquea el usuario y cierra sus sesiones';

    public function handle(Mediator $mediator): int
    {
        try {
            $persona = IdDePersona::deCelular(Celular::desdeLocalBoliviano((string) $this->argument('celular')));
        } catch (DomainException) {
            $this->error('No es un celular móvil boliviano.');

            return self::FAILURE;
        }

        $resultado = $mediator->send(new DeshabilitarPersona($persona));

        if ($resultado->isFailure()) {
            $this->error($resultado->error->description);

            return self::FAILURE;
        }

        $this->info('Deshabilitada.');

        return self::SUCCESS;
    }
}
