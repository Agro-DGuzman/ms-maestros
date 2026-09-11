<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Keycloak;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Maestros\Domain\Contactos\IdDePersona;
use Throwable;

/**
 * En producción quien valida el token es APIM contra el JWKS del realm; este
 * verificador es la segunda línea, para que el servicio no dependa de que el
 * gateway esté bien configurado. El JWKS se cachea: si Keycloak se cae, los
 * que ya están adentro siguen operando.
 */
final readonly class VerificadorJwks implements VerificadorDeToken
{
    public function __construct(
        private Http $http,
        private Cache $cache,
        private string $baseUrl,
        private string $realm,
        private int $minutosDeCache = 60,
    ) {}

    public function verificar(string $jwt): ?IdDePersona
    {
        try {
            $claves = JWK::parseKeySet($this->jwks());
            $claims = JWT::decode($jwt, $claves);
        } catch (Throwable) {
            return null;
        }

        $usuario = $claims->preferred_username ?? null;

        if (! is_string($usuario) || trim($usuario) === '') {
            return null;
        }

        return IdDePersona::desde($usuario);
    }

    /** @return array<string, mixed> */
    private function jwks(): array
    {
        return $this->cache->remember(
            'keycloak:jwks:'.$this->realm,
            now()->addMinutes($this->minutosDeCache),
            function (): array {
                $url = sprintf(
                    '%s/realms/%s/protocol/openid-connect/certs',
                    rtrim($this->baseUrl, '/'),
                    $this->realm,
                );

                /** @var array<string, mixed> $cuerpo */
                $cuerpo = $this->http->timeout(5)->get($url)->json();

                return $cuerpo;
            },
        );
    }
}
