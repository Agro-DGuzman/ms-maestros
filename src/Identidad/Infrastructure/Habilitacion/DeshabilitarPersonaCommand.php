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
    protected $signature = 'identidad:deshabilitar {persona : Celular (con o sin +591) o id de persona (n-… o p-…)}';

    protected $description = 'Quita el acceso a la App: bloquea el usuario y cierra sus sesiones';

    public function handle(Mediator $mediator): int
    {
        $persona = self::persona((string) $this->argument('persona'));

        if ($persona === null) {
            $this->error('No es un celular móvil boliviano ni un id de persona.');

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

    /**
     * Quitar acceso es por número, pero también acepta un id: un resto
     * anterior a los números (`p-…`) no tiene celular por el que nombrarlo, y
     * la conciliación lo muestra por su id.
     */
    private static function persona(string $argumento): ?IdDePersona
    {
        $argumento = trim($argumento);

        if (preg_match('/^p-[0-9a-z]+$/', $argumento) === 1) {
            return IdDePersona::desde($argumento);
        }

        if (str_starts_with($argumento, 'n-')) {
            $id = IdDePersona::desde($argumento);

            return $id->celular() === null ? null : $id;
        }

        try {
            return IdDePersona::deCelular(Celular::desdeLocalBoliviano($argumento));
        } catch (DomainException) {
            return null;
        }
    }
}
