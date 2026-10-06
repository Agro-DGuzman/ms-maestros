<?php

declare(strict_types=1);

namespace Tests\Soporte;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * Un Entra ID de mentira: tres claves RSA fijas, su JWKS servido con
 * `Http::fake`, y tokens firmados con cualquiera de ellas. Las claves 2 y 3
 * sirven para simular una rotación.
 */
final class TokenDeEntra
{
    public const string TENANT = 'tenant-de-prueba';

    public const string AUDIENCIA = 'api-de-prueba';

    public const string EMISOR = 'https://login.microsoftonline.com/tenant-de-prueba/v2.0';

    public const string JWKS = 'https://login.microsoftonline.com/tenant-de-prueba/discovery/v2.0/keys';

    /** @var array<string, OpenSSLAsymmetricKey> */
    private static array $claves = [];

    /** @var list<string> */
    private static array $publicadas = ['clave-1'];

    private static bool $caido = false;

    /**
     * Configura la ingesta para el tenant de prueba y sirve el JWKS. El stub
     * lee el estado en cada pedido: `Http::fake` usa el primer stub que
     * coincide, así que un test no puede registrar otro encima.
     */
    public static function configurar(string $secreto = 'secreto-del-gateway'): void
    {
        self::$publicadas = ['clave-1'];
        self::$caido = false;

        config([
            'ingesta.gateway.secreto' => $secreto,
            'ingesta.entra.tenant' => self::TENANT,
            'ingesta.entra.audiencia' => self::AUDIENCIA,
            'ingesta.entra.rol' => 'Ingesta.Maestros.Escribir',
        ]);

        Http::fake([self::JWKS => static fn () => self::$caido
            ? Http::response('caido', 500)
            : Http::response(self::jwks(self::$publicadas))]);
    }

    /** Lo que Entra publica desde ahora: así se simula una rotación. */
    public static function publicar(array $kids): void
    {
        self::$publicadas = array_values($kids);
    }

    public static function caido(bool $caido = true): void
    {
        self::$caido = $caido;
    }

    /** @return array{keys: list<array<string, string>>} */
    public static function jwks(array $kids = ['clave-1']): array
    {
        return ['keys' => array_values(array_map(static function (string $kid): array {
            $detalles = openssl_pkey_get_details(self::clave($kid));

            if ($detalles === false) {
                throw new RuntimeException('No se pudo leer la clave de prueba');
            }

            // Como las publica Entra: sin `alg`, que el verificador fija.
            return [
                'kty' => 'RSA',
                'use' => 'sig',
                'kid' => $kid,
                'n' => self::base64Url($detalles['rsa']['n']),
                'e' => self::base64Url($detalles['rsa']['e']),
            ];
        }, $kids))];
    }

    /**
     * `$kidEnCabecera` permite firmar con una clave y declarar otra, para una
     * firma que no verifica.
     *
     * @param  array<string, mixed>  $claims
     */
    public static function firmar(array $claims, string $kid = 'clave-1', ?string $kidEnCabecera = null): string
    {
        return JWT::encode($claims, self::clave($kid), 'RS256', $kidEnCabecera ?? $kid);
    }

    /** @param array<string, mixed> $sobrescribir */
    public static function valido(array $sobrescribir = [], string $kid = 'clave-1', ?string $kidEnCabecera = null): string
    {
        $ahora = time();

        return self::firmar(array_merge([
            'iss' => self::EMISOR,
            'aud' => self::AUDIENCIA,
            'roles' => ['Ingesta.Maestros.Escribir'],
            'iat' => $ahora,
            'nbf' => $ahora,
            'exp' => $ahora + 3600,
        ], $sobrescribir), $kid, $kidEnCabecera);
    }

    /**
     * Claves fijas de `claves-de-prueba/`, solo para tests: generarlas en cada
     * corrida exige un `openssl.cnf` que el PHP de Windows no trae.
     */
    private static function clave(string $kid): OpenSSLAsymmetricKey
    {
        if (! isset(self::$claves[$kid])) {
            $pem = file_get_contents(__DIR__."/claves-de-prueba/{$kid}.pem");
            $clave = $pem === false ? false : openssl_pkey_get_private($pem);

            if ($clave === false) {
                throw new RuntimeException("No hay clave de prueba {$kid}");
            }

            self::$claves[$kid] = $clave;
        }

        return self::$claves[$kid];
    }

    private static function base64Url(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }
}
