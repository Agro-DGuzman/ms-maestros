<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Eventos;

use Core\Contracts\Mediator;
use Core\Results\ResultWithValue;
use Illuminate\Console\Command;
use Maestros\Application\Ingesta\PublicarSocios\PublicarSocios;

/**
 * El paso de la puesta en marcha en que ms-maestros le manda a ms-comercial
 * todos sus socios (paso 3 del 12.3 de la guía de comercial). Se corre a
 * mano, después de que comercial borre los suyos: antes, lo publicado se
 * borra con ellos.
 */
final class PublicarSociosCommand extends Command
{
    protected $signature = 'eventos:publicar-socios';

    protected $description = 'Pone en el outbox la foto de cada socio con línea y sin baja';

    public function handle(Mediator $mediator): int
    {
        $resultado = $mediator->send(new PublicarSocios);

        $encolados = $resultado instanceof ResultWithValue && $resultado->isSuccess ? $resultado->value() : null;

        if (! is_int($encolados)) {
            $this->error('No se pudo encolar los socios: '.$resultado->error->description);

            return self::FAILURE;
        }

        $this->info("Socios en la cola: {$encolados}.");

        return self::SUCCESS;
    }
}
