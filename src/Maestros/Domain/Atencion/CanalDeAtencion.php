<?php

declare(strict_types=1);

namespace Maestros\Domain\Atencion;

use Maestros\Domain\Contactos\Celular;

/**
 * El número al que el socio escribe por WhatsApp. Es un `Celular` y no un
 * texto suelto porque WhatsApp solo abre chats con móviles: un fijo mal
 * configurado se descubriría recién cuando un socio toque la fila.
 */
final readonly class CanalDeAtencion
{
    public function __construct(
        public string $area,
        private Celular $celular,
    ) {}

    public function telefono(): string
    {
        return $this->celular->paraMostrar();
    }

    public function whatsappUrl(): string
    {
        return 'https://wa.me/'.ltrim($this->celular->e164(), '+');
    }
}
