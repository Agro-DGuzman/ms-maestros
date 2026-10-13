<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Habilitacion;

use Core\Contracts\Mediator;
use Core\Results\DomainException;
use Identidad\Application\Habilitacion\HabilitarPersona\HabilitarPersona;
use Illuminate\Console\Command;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\IdDePersona;

final class HabilitarPersonaCommand extends Command
{
    protected $signature = 'identidad:habilitar {celular : Celular de la persona, con o sin +591}';

    protected $description = 'Crea o actualiza el usuario de la persona en el directorio de identidades';

    public function handle(Mediator $mediator): int
    {
        $persona = self::numero((string) $this->argument('celular'));

        if ($persona === null) {
            $this->error('No es un celular móvil boliviano.');

            return self::FAILURE;
        }

        $resultado = $mediator->send(new HabilitarPersona($persona));

        if ($resultado->isFailure()) {
            $this->error("[{$resultado->error->code}] {$resultado->error->description}");

            return self::FAILURE;
        }

        $this->info("Habilitada {$persona->value()}.");

        return self::SUCCESS;
    }

    /** Lo que se habilita es el número: el id de la persona sale de él. */
    private static function numero(string $celular): ?IdDePersona
    {
        try {
            return IdDePersona::deCelular(Celular::desdeLocalBoliviano($celular));
        } catch (DomainException) {
            return null;
        }
    }
}
