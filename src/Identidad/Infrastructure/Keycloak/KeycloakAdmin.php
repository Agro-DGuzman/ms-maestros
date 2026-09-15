<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Keycloak;

use Core\Results\Result;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Maestros\Domain\Contactos\IdDePersona;

final readonly class KeycloakAdmin implements DirectorioDeIdentidades
{
    public function __construct(
        private Http $http,
        private string $baseUrl,
        private string $realm,
        private string $clientId,
        private string $clientSecret,
        private int $timeout,
    ) {}

    public function crearOActualizar(IdDePersona $persona, string $contrasena): Result
    {
        $token = $this->tokenDeServicio();

        if ($token === null) {
            return Result::failure(KeycloakErrors::noDisponible('sin token de servicio'));
        }

        try {
            $existente = $this->buscar($token, $persona);

            if ($existente === null) {
                $creado = $this->http->withToken($token)->timeout($this->timeout)
                    ->post($this->admin('users'), [
                        'username' => $persona->value(),
                        'enabled' => true,
                        'emailVerified' => false,
                        'credentials' => [
                            ['type' => 'password', 'value' => $contrasena, 'temporary' => false],
                        ],
                    ]);

                return $creado->successful()
                    ? Result::success()
                    : Result::failure(KeycloakErrors::noDisponible('alta '.$creado->status()));
            }

            $this->http->withToken($token)->timeout($this->timeout)
                ->put($this->admin("users/{$existente}"), ['enabled' => true]);

            $reset = $this->http->withToken($token)->timeout($this->timeout)
                ->put($this->admin("users/{$existente}/reset-password"), [
                    'type' => 'password',
                    'value' => $contrasena,
                    'temporary' => false,
                ]);

            return $reset->successful()
                ? Result::success()
                : Result::failure(KeycloakErrors::noDisponible('reset '.$reset->status()));
        } catch (ConnectionException $e) {
            return Result::failure(KeycloakErrors::noDisponible($e->getMessage()));
        }
    }

    public function deshabilitar(IdDePersona $persona): Result
    {
        $token = $this->tokenDeServicio();

        if ($token === null) {
            return Result::failure(KeycloakErrors::noDisponible('sin token de servicio'));
        }

        try {
            $id = $this->buscar($token, $persona);

            if ($id === null) {
                return Result::success();   // ya no está: nada que deshabilitar
            }

            $respuesta = $this->http->withToken($token)->timeout($this->timeout)
                ->put($this->admin("users/{$id}"), ['enabled' => false]);

            return $respuesta->successful()
                ? Result::success()
                : Result::failure(KeycloakErrors::noDisponible('baja '.$respuesta->status()));
        } catch (ConnectionException $e) {
            return Result::failure(KeycloakErrors::noDisponible($e->getMessage()));
        }
    }

    public function estaActivo(IdDePersona $persona): bool
    {
        $token = $this->tokenDeServicio();

        if ($token === null) {
            return false;
        }

        // El `enabled` es el que decide: deshabilitar deja al usuario en su
        // lugar, así que encontrarlo no dice nada sobre si puede entrar.
        return ($this->fila($token, $persona)['enabled'] ?? false) === true;
    }

    private function buscar(string $token, IdDePersona $persona): ?string
    {
        $id = $this->fila($token, $persona)['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /** @return array<string, mixed> */
    private function fila(string $token, IdDePersona $persona): array
    {
        $respuesta = $this->http->withToken($token)->timeout($this->timeout)
            ->get($this->admin('users'), ['username' => $persona->value(), 'exact' => 'true']);

        if (! $respuesta->successful()) {
            return [];
        }

        $usuarios = $respuesta->json();

        if (! is_array($usuarios) || ! isset($usuarios[0]) || ! is_array($usuarios[0])) {
            return [];
        }

        /** @var array<string, mixed> $fila */
        $fila = $usuarios[0];

        return $fila;
    }

    private function tokenDeServicio(): ?string
    {
        try {
            $respuesta = $this->http->asForm()->timeout($this->timeout)->post(
                sprintf('%s/realms/%s/protocol/openid-connect/token', rtrim($this->baseUrl, '/'), $this->realm),
                [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ],
            );
        } catch (ConnectionException) {
            return null;
        }

        if (! $respuesta->successful()) {
            return null;
        }

        $cuerpo = $respuesta->json();
        $token = is_array($cuerpo) ? ($cuerpo['access_token'] ?? null) : null;

        return is_string($token) ? $token : null;
    }

    private function admin(string $ruta): string
    {
        return sprintf('%s/admin/realms/%s/%s', rtrim($this->baseUrl, '/'), $this->realm, $ruta);
    }
}
