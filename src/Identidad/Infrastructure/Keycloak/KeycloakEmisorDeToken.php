<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Keycloak;

use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\TokenEmitido;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Maestros\Domain\Contactos\IdDePersona;
use Psr\Log\LoggerInterface;

final readonly class KeycloakEmisorDeToken implements EmisorDeToken
{
    public function __construct(
        private Http $http,
        private LoggerInterface $log,
        private string $baseUrl,
        private string $realm,
        private string $clientId,
        private string $clientSecret,
        private int $timeout,
    ) {}

    public function emitirPara(IdDePersona $persona, string $contrasena): ResultWithValue
    {
        return $this->pedirToken([
            'grant_type' => 'password',
            'username' => $persona->value(),
            'password' => $contrasena,
            'scope' => 'openid',
        ]);
    }

    public function renovar(string $refreshToken): ResultWithValue
    {
        return $this->pedirToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function revocar(string $refreshToken): Result
    {
        try {
            $respuesta = $this->http->asForm()->timeout($this->timeout)->post(
                $this->url('logout'),
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshToken,
                ],
            );
        } catch (ConnectionException $e) {
            return Result::failure(KeycloakErrors::noDisponible($e->getMessage()));
        }

        // Un refresh ya vencido devuelve 400: cerrar sesión igual es correcto.
        return $respuesta->successful() || $respuesta->status() === 400
            ? Result::success()
            : Result::failure(KeycloakErrors::noDisponible('logout '.$respuesta->status()));
    }

    /**
     * @param  array<string, string>  $campos
     * @return ResultWithValue<TokenEmitido>
     */
    private function pedirToken(array $campos): ResultWithValue
    {
        try {
            $respuesta = $this->http->asForm()->timeout($this->timeout)->post($this->url('token'), [
                ...$campos,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);
        } catch (ConnectionException $e) {
            return ResultWithValue::failure(KeycloakErrors::noDisponible($e->getMessage()));
        }

        if ($respuesta->status() === 401) {
            // El cliente o la contraseña guardada están mal: es un problema
            // nuestro, no del socio. Nunca se le cuenta al cliente por qué.
            $this->log->error('Keycloak rechazó la credencial', ['status' => 401]);

            return ResultWithValue::failure(KeycloakErrors::credencialRechazada());
        }

        if (! $respuesta->successful()) {
            $this->log->error('Keycloak respondió con error', ['status' => $respuesta->status()]);

            return ResultWithValue::failure(KeycloakErrors::noDisponible((string) $respuesta->status()));
        }

        /** @var array{access_token?: string, refresh_token?: string, expires_in?: int} $cuerpo */
        $cuerpo = $respuesta->json();

        if (! isset($cuerpo['access_token'], $cuerpo['refresh_token'])) {
            return ResultWithValue::failure(KeycloakErrors::noDisponible('respuesta sin token'));
        }

        return ResultWithValue::of(new TokenEmitido(
            $cuerpo['access_token'],
            $cuerpo['refresh_token'],
            (int) ($cuerpo['expires_in'] ?? 0),
        ));
    }

    private function url(string $operacion): string
    {
        return sprintf(
            '%s/realms/%s/protocol/openid-connect/%s',
            rtrim($this->baseUrl, '/'),
            $this->realm,
            $operacion,
        );
    }
}
