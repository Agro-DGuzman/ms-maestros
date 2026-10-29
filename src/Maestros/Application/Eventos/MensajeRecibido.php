<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

/**
 * Un mensaje tomado con peek-lock. `referenciaDeBloqueo` es opaca: con la API
 * REST de Service Bus es la URL del mensaje bloqueado, y solo el buzón sabe
 * qué hacer con ella.
 */
final readonly class MensajeRecibido
{
    public function __construct(
        public string $cuerpo,
        public string $idDeMensaje,
        public int $numeroDeEntrega,
        public string $referenciaDeBloqueo,
    ) {}
}
