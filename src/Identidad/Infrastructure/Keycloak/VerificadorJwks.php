<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Keycloak;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Maestros\Domain\Contactos\IdDePersona;
use stdClass;
use Throwable;

/**
 * En producción quien valida el token es APIM contra el JWKS del realm; este
 * verificador es la segunda línea, para que el servicio no dependa de que el
 * gateway esté bien configurado. El JWKS se cachea: si Keycloak se cae, los
 * que ya están adentro siguen operando.
 */
final readonly class VerificadorJwks implements VerificadorDeToken
{
    /**
     * `$baseUrl` es dónde vive Keycloak y `$emisor` es quién dice ser. En local
     * coinciden, pero en Azure no: el `iss` es un nombre propio y estable que
     * viaja dentro de cada token, y la llamada al JWKS va al FQDN interno del
     * entorno, que puede cambiar sin que eso invalide nada de lo ya emitido.
     */
    public function __construct(
        private Http $http,
        private Cache $cache,
        private string $baseUrl,
        private string $realm,
        private string $clientId,
        private string $emisor,
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

        // `JWT::decode` comprueba la firma y las marcas de tiempo, no de dónde
        // viene ni para quién es. Sin estas dos, cualquier token firmado por
        // una clave que este realm publique entra: el de otro realm del mismo
        // Keycloak, y el que el propio realm emitió para otra aplicación.
        if (! $this->esDeNuestroEmisor($claims) || ! $this->esParaNosotros($claims)) {
            return null;
        }

        $usuario = $claims->preferred_username ?? null;

        if (! is_string($usuario) || trim($usuario) === '') {
            return null;
        }

        return IdDePersona::desde($usuario);
    }

    private function esDeNuestroEmisor(stdClass $claims): bool
    {
        $esperado = sprintf('%s/realms/%s', rtrim($this->emisor, '/'), $this->realm);

        return ($claims->iss ?? null) === $esperado;
    }

    /**
     * Dos formas de que el token sea nuestro, y alcanza con una.
     *
     * `aud` nos nombra explícitamente, que es lo que pasa cuando el realm tiene
     * un audience mapper. O `azp` dice que se emitió para nuestro cliente, que
     * es lo que Keycloak pone siempre.
     *
     * No se puede preferir `aud` cuando está: Keycloak le pone `aud: account` a
     * todo usuario con los roles por defecto del realm —o sea, a toda persona
     * que crea `identidad:habilitar`— sin que eso tenga nada que ver con
     * nosotros. Exigir entonces que `aud` nos nombre rechaza a todas ellas.
     *
     * Lo que la comprobación tiene que impedir sigue en pie: un token que el
     * mismo realm emitió para otra aplicación no nos nombra en `aud` ni lleva
     * nuestro cliente en `azp`.
     */
    private function esParaNosotros(stdClass $claims): bool
    {
        if (in_array($this->clientId, (array) ($claims->aud ?? []), true)) {
            return true;
        }

        return ($claims->azp ?? null) === $this->clientId;
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
