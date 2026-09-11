<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Habilitacion;

use Core\Contracts\Mediator;
use Identidad\Application\Habilitacion\HabilitarPersona\HabilitarPersona;
use Illuminate\Console\Command;
use Maestros\Domain\Contactos\IdDePersona;

final class HabilitarPersonaCommand extends Command
{
    protected $signature = 'identidad:habilitar {persona : Identificador de la persona de contacto}';

    protected $description = 'Crea o actualiza el usuario de la persona en el directorio de identidades';

    public function handle(Mediator $mediator): int
    {
        $persona = IdDePersona::desde((string) $this->argument('persona'));

        $resultado = $mediator->send(new HabilitarPersona($persona));

        if ($resultado->isFailure()) {
            $this->error("[{$resultado->error->code}] {$resultado->error->description}");

            return self::FAILURE;
        }

        $this->info("Habilitada {$persona->value()}.");

        return self::SUCCESS;
    }
}
