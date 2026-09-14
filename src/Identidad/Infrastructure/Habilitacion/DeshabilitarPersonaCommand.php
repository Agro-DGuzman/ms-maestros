<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Habilitacion;

use Core\Contracts\Mediator;
use Identidad\Application\Habilitacion\DeshabilitarPersona\DeshabilitarPersona;
use Illuminate\Console\Command;
use Maestros\Domain\Contactos\IdDePersona;

final class DeshabilitarPersonaCommand extends Command
{
    protected $signature = 'identidad:deshabilitar {persona : Id de la persona de contacto}';

    protected $description = 'Quita el acceso a la App: bloquea el usuario y cierra sus sesiones';

    public function handle(Mediator $mediator): int
    {
        $resultado = $mediator->send(
            new DeshabilitarPersona(IdDePersona::desde((string) $this->argument('persona'))),
        );

        if ($resultado->isFailure()) {
            $this->error($resultado->error->description);

            return self::FAILURE;
        }

        $this->info('Deshabilitada.');

        return self::SUCCESS;
    }
}
