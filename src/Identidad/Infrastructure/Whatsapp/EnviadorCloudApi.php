<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Whatsapp;

use Identidad\Application\Contracts\EnviadorDeDesafio;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
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
                        // Una plantilla de autenticación lleva el código dos
                        // veces, y Meta rechaza el envío si falta el del botón.
                        // `url` aunque sea «Copiar código»: Meta le cambia el
                        // tipo al botón cuando crea la plantilla.
                        'components' => [
                            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $digitos]]],
                            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $digitos]]],
                        ],
                    ],
                ],
            );

            if (! $respuesta->successful()) {
                $this->log->error('WhatsApp rechazó el envío', self::sinCodigo(self::motivo($respuesta), $digitos));
            }
        } catch (Throwable $e) {
            // Nunca propaga: el endpoint tiene que responder igual y en el
            // mismo tiempo exista o no el número.
            $this->log->error('WhatsApp no respondió', self::sinCodigo(['excepcion' => $e->getMessage()], $digitos));
        }
    }

    /**
     * El error de Meta es lo único que distingue un rechazo de otro: una
     * plantilla que no existe o no está aprobada, parámetros que no coinciden
     * o un token vencido. Sin él, el log dice «400» y hay que adivinar.
     *
     * @return array<string, mixed>
     */
    private static function motivo(Response $respuesta): array
    {
        $error = $respuesta->json('error');
        $error = is_array($error) ? $error : [];
        $datos = is_array($error['error_data'] ?? null) ? $error['error_data'] : [];

        return [
            'status' => $respuesta->status(),
            'codigo' => $error['code'] ?? null,
            'mensaje' => $error['message'] ?? null,
            'detalle' => $datos['details'] ?? null,
            'fbtrace_id' => $error['fbtrace_id'] ?? null,
        ];
    }

    /**
     * El contrato prohíbe registrar el código, y el texto que vuelve —de Meta
     * o de la red— no está bajo nuestro control: si lo repite, se tapa.
     *
     * @param  array<string, mixed>  $contexto
     * @return array<string, mixed>
     */
    private static function sinCodigo(array $contexto, string $digitos): array
    {
        return array_map(
            static fn (mixed $valor): mixed => is_string($valor) ? str_replace($digitos, '****', $valor) : $valor,
            array_filter($contexto, static fn (mixed $valor): bool => $valor !== null),
        );
    }
}
