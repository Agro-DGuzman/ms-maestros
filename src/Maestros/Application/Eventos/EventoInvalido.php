<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

use RuntimeException;

/**
 * Un evento que el consumidor no entiende (EM7). Es una excepción y no un
 * `Result` a propósito: un `Result` fallido confirma la transacción, y el
 * evento quedaría registrado como procesado y perdido. La excepción deshace,
 * el mensaje no se completa, y a la quinta entrega termina en la cola de
 * mensajes muertos, donde alguien lo puede mirar. Copiado de ms-comercial.
 */
final class EventoInvalido extends RuntimeException
{
    public static function porForma(string $detalle): self
    {
        return new self("El sobre no tiene la forma acordada: {$detalle}");
    }

    public static function porDatos(string $detalle): self
    {
        return new self("Los datos del evento no se pueden traducir: {$detalle}");
    }

    public static function sinTraductor(string $tipo): self
    {
        return new self("No hay traductor para el tipo {$tipo}");
    }
}
