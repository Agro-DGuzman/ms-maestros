<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\ServiceBus;

use Illuminate\Http\Client\Factory as Http;
use Maestros\Application\Eventos\PublicadorDeEventos;
use RuntimeException;

/**
 * Publica sobres en un topic: el sobre de cuerpo, `tipo` como propiedad (sobre
 * ella filtran las reglas de cada suscripción) y `SessionId = cardCode`, que
 * en una suscripción sin sesiones es inofensivo.
 *
 * Lo usa el consumidor, que vacía el outbox en `maestros-eventos` con la
 * clave de publicación, y la prueba de integración, contra su topic de
 * pruebas. Copiado de ms-comercial.
 */
final readonly class PublicadorServiceBusRest implements PublicadorDeEventos
{
    public function __construct(
        private Http $http,
        private string $namespace,
        private string $topic,
        private string $nombreDeClave,
        private string $clave,
    ) {}

    /** @param array<array-key, mixed> $sobre */
    public function publicar(array $sobre): void
    {
        $cardCode = is_array($sobre['datos'] ?? null) ? ($sobre['datos']['cardCode'] ?? null) : null;

        $propiedades = array_filter([
            'MessageId' => $sobre['eventoId'] ?? null,
            'SessionId' => $cardCode,
        ], static fn (mixed $valor): bool => $valor !== null);

        $recurso = "https://{$this->namespace}.servicebus.windows.net/{$this->topic}";

        $respuesta = $this->http
            ->withHeaders([
                'Authorization' => FirmaSas::token($recurso, $this->nombreDeClave, $this->clave, time() + 300),
                'BrokerProperties' => json_encode($propiedades, JSON_THROW_ON_ERROR),
                // Las propiedades propias viajan como cabecera con el valor en
                // JSON: un texto va entre comillas.
                'tipo' => json_encode($sobre['tipo'] ?? null, JSON_THROW_ON_ERROR),
            ])
            ->withBody(json_encode($sobre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'application/json')
            ->post("{$recurso}/messages");

        if ($respuesta->status() !== 201) {
            throw new RuntimeException("Service Bus respondió {$respuesta->status()} al publicar");
        }
    }
}
