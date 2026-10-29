<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\ServiceBus;

use Illuminate\Http\Client\Factory as Http;
use Maestros\Application\Eventos\BuzonDeEntrada;
use Maestros\Application\Eventos\MensajeRecibido;
use RuntimeException;

/**
 * La suscripción por la API REST, con peek-lock. REST y no AMQP porque PHP no
 * tiene un cliente AMQP 1.0 mantenido; el costo es que REST no recibe de una
 * suscripción con sesiones, y por eso `sub-ms-maestros` no las exige. Copiado
 * de ms-comercial.
 *
 * La clave es de una política solo de escucha: con ella no se puede publicar
 * ni administrar nada.
 */
final readonly class BuzonServiceBusRest implements BuzonDeEntrada
{
    public function __construct(
        private Http $http,
        private string $namespace,
        private string $topic,
        private string $suscripcion,
        private string $nombreDeClave,
        private string $clave,
        private int $esperaSegundos = 10,
    ) {}

    public function recibir(?int $esperaSegundos = null): ?MensajeRecibido
    {
        $espera = $esperaSegundos ?? $this->esperaSegundos;

        $url = sprintf(
            'https://%s.servicebus.windows.net/%s/subscriptions/%s/messages/head?timeout=%d',
            $this->namespace,
            $this->topic,
            $this->suscripcion,
            $espera,
        );

        // El pedido espera en el bus hasta que llega algo o se cumple la
        // espera; el timeout HTTP le deja margen para no cortarlo antes.
        $respuesta = $this->http
            ->withHeaders(['Authorization' => $this->firma()])
            ->timeout($espera + 10)
            ->post($url);

        if ($respuesta->status() === 204) {
            return null;
        }

        if ($respuesta->status() !== 201) {
            throw new RuntimeException("Service Bus respondió {$respuesta->status()} al recibir");
        }

        $propiedades = json_decode($respuesta->header('BrokerProperties'), true);

        if (! is_array($propiedades) || ! is_string($propiedades['MessageId'] ?? null) || ! is_int($propiedades['DeliveryCount'] ?? null)) {
            throw new RuntimeException('Service Bus entregó un mensaje sin BrokerProperties legibles');
        }

        return new MensajeRecibido(
            $respuesta->body(),
            $propiedades['MessageId'],
            $propiedades['DeliveryCount'],
            $respuesta->header('Location'),
        );
    }

    public function completar(MensajeRecibido $mensaje): void
    {
        $respuesta = $this->http
            ->withHeaders(['Authorization' => $this->firma()])
            ->delete($mensaje->referenciaDeBloqueo);

        if (! $respuesta->successful()) {
            throw new RuntimeException("Service Bus respondió {$respuesta->status()} al completar {$mensaje->idDeMensaje}");
        }
    }

    /** Sobre el topic: cubre sus suscripciones. Cinco minutos alcanzan para un pedido. */
    private function firma(): string
    {
        return FirmaSas::token(
            "https://{$this->namespace}.servicebus.windows.net/{$this->topic}",
            $this->nombreDeClave,
            $this->clave,
            time() + 300,
        );
    }
}
