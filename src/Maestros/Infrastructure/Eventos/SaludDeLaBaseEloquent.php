<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Eventos;

use App\Persistence\TablaConEsquema;
use Illuminate\Database\ConnectionInterface;
use Maestros\Application\Eventos\SaludDeLaBase;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Consulta la tabla de eventos y no un `select 1` pelado: una base a la que se
 * llega pero que no está migrada no puede guardar nada, y tiene que contar
 * como caída. Si no, el consumidor recibiría cada mensaje, reventaría al
 * escribirlo y a los cinco intentos lo mandaría a la cola de mensajes muertos.
 */
final class SaludDeLaBaseEloquent implements SaludDeLaBase
{
    use TablaConEsquema;

    public function __construct(
        private readonly ConnectionInterface $conexion,
        private readonly LoggerInterface $log,
    ) {}

    public function responde(): bool
    {
        try {
            $this->conexion->select("select 1 from {$this->tablaEn('maestros', 'evento_procesado')} where 1 = 0");

            return true;
        } catch (Throwable $e) {
            // El comando solo dice «La base no responde»: sin el motivo, un
            // login rechazado y una base sin migrar se ven igual. El mensaje
            // del driver nombra servidor, base y usuario, nunca la contraseña.
            $this->log->warning('La base no responde', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
