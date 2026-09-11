<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Whatsapp;

use Identidad\Application\Contracts\EnviadorDeDesafio;
use Illuminate\Http\Client\Factory as Http;
use Maestros\Domain\Contactos\Celular;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final readonly class EnviadorCloudApi implements EnviadorDeDesafio
{
    public function __construct(
        private Http $http,
        private string $baseUrl,
        private string $phoneNumberId,
        private string $token,
        private string $plantilla,
        private string $idioma,
        private LoggerInterface $log = new NullLogger,
    ) {}

    public function enviar(Celular $a, string $digitos): void
    {
        try {
            $respuesta = $this->http->withToken($this->token)->timeout(8)->post(
                sprintf('%s/%s/messages', rtrim($this->baseUrl, '/'), $this->phoneNumberId),
                [
                    'messaging_product' => 'whatsapp',
                    // La API quiere el número sin el signo más.
                    'to' => ltrim($a->e164(), '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => $this->plantilla,
                        'language' => ['code' => $this->idioma],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => [['type' => 'text', 'text' => $digitos]],
                        ]],
                    ],
                ],
            );

            if (! $respuesta->successful()) {
                $this->log->error('WhatsApp rechazó el envío', ['status' => $respuesta->status()]);
            }
        } catch (Throwable $e) {
            // Nunca propaga: el endpoint tiene que responder igual y en el
            // mismo tiempo exista o no el número.
            $this->log->error('WhatsApp no respondió', ['excepcion' => $e->getMessage()]);
        }
    }
}
