<?php

declare(strict_types=1);

namespace Tests\Soporte;

use Firebase\JWT\JWT;

/**
 * Par de claves fijo para firmar tokens de prueba, con el JWKS que le
 * corresponde. Va escrito y no generado en cada corrida: openssl_pkey_new()
 * necesita un openssl.cnf que no esta en todas las maquinas, y firmar con un
 * PEM ya escrito no lo necesita.
 *
 * No es secreto: solo firma tokens de prueba.
 */
final class ClaveDePrueba
{
    public const KID = 'kid-de-prueba';

    private const PEM_BASE64 = 'LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0tCk1JSUV2UUlCQURBTkJna3Foa2lHOXcwQkFRRUZBQVNDQktjd2dnU2pBZ0VBQW9JQkFRRFh5aXJxMXVmUlR0Qk0KdnpQcUxsMndrUW5acEx5dlhCZm14S3JzU0RDR24yZlZHS1ViaWRveWNPZWlmOXVXUlorQWQzNXMyRTVPQUpqLwpuclVaWkZ1NzkrZURsWmpmd1MzRXNQZlV1ZjZmSTdUd1ZCblV1QmRHeGFnVC94bFN5Y2JpQkhGYzBEWFNrZVBwCnRqK3ZaYjc4ZjV3T09zY3g0dVUyeHFoSlNlQVZmU1NGUkp5NmZyV1lQSHR4N1pDbFVnelF2OWZvN0VDUDIxSGoKeVhhSkt0Vm91K2lGSm5WMmROMmdNcmJ4TDBLRWJqakNUUHM1QUFTOU8zclNsR0F6aFdhWFpvZytpaENaOERqZgp4UkRTOUxqdGJGVU5sbmxhVjhRUVBwbmpnWmdyZC9vanRVMC80Q3JrSHliUDRDL2JUWUNodU1sL280cXVabGgwClhNQVpQUTdmQWdNQkFBRUNnZ0VBTXJtSnhDRlhaZXJHYVR3SzZwSVNvUFkvSUFPcS9QZkRnSkliY2FaNGpiUzcKOWlvd2FaeEtoOC9saTF2TjFQR3gwRU9HbXZSdjE4TXBNL0Z0TmJaVElBaml5Wm9wVVBPNm5BRlRpSlJlSjY5LwprWHpiZzVid2xjalJ2VDJhaU16NHJObUpnbDFKUWFIY3R1d0o1V09mOVAvVVFiNE1aQXduZ0d4TFU4c0RXMWxjCmJESlNmM2JwZzBBcW1kZE5CVCtlUERWRi9EQTF1ZkhYN2lyR05SMGVYdXE5c0N0TGZkV2YzSitTZExOMjZIdVcKVlE0QWlDOTkydXA0SXNmU1lFeFRxaFNuOTZnWS9ab2NIUDNXNWwyVVJjM3hOVUE2VGRJQi9IRzFzemRQTkxoUQpZdS9icmhwNHJleUxIbUpSNW5JZzl0VGp5MHlqUnBTUkZXM05lWCtHc1FLQmdRRHdtU3ZkMDBIemtZMEVnV3d5CnRZcXJBN2tQNFFDTWhYdmZ1czdONlNvUE1EWVNxaUhXSUpabXpCc1M1alk1NEc1enhQL2JzK0RLNWZ0K3J6czUKdnhrUDV4TEtqamRkTFVqbjBCeEc2RGJaSXVXaXlveXRnTno4NXdCVVNCditYeDE2bXZBN1JQaDVmMkx2SWxjWgpSUXRaTWs3cWFFMW14dld1NUFrZGJnZEtUd0tCZ1FEbG1uRmE0TXl3dFVISW5UUW9BeUNtdFQ4dGdZZy8wdWswCjVESTJmNDl6c0k2WmJYNG13ZEtCTVpNcVRJZW8wU2cyQ3pxOEJ6eDBrWUpycTVJSGMvUmNmNFF3TmdKZ3hqWXMKVjk4SHBiaTBNRnF4Y2NTQUtpS3ZEdTBnWkx2UU9FcXhkeXI1aVZiRWZSbmJKeldkTlcrNGR1OTAvRk55YlJqSApQaHNCT2JBZWNRS0JnQWxnVHI5VVRrdXBybTh3aEFEdDVqdUg1NXhnemw2cmpRcEpBMm91M2Y2OWlEM3Q5MmVhCjJZR2tEcUlMNnEwU3Uvc3pBQzJWc3ZyMVAzbk9abVozdGdoU252N1p6L3FIbTBHOWNIeXE3QWhHUGVDOE9BTkMKOUZtK3Z0cUovTjFLNDZFMWpJc2l3dlFwTExmWkJML3RXdVhjK2dwVWlqK3BIVVgxaVExbWprNHRBb0dCQUlxOQpIVHZ0MUtIK2xPYVZYM3ZDRUF2TVA0WE8zTGE4U21ERWR6Y0pNM1NUdmtjbHEzSGQ4c1pRWDMzU3lyS08yRDUzCjRLRFh1b2N4bWQ1WHlTQ3B4NEhSSjk0OTlJZm5uYnFEeW1nRGtxMkcvblowcVdsTWpMSzlVVG9leElKWVVZSVUKNFNueC9EVTA1dGZQUkkxZlNjZnNUbHVoVVFjMnR4OUYvdWxwbzJ0eEFvR0FQZ0pIVWwxakRxeUVWWlJDa243UQpKcDA4Q2hDMjB3QUpHZndEaVpNWjUwVThGSVRpSHB3SHR4dzVUU2ZON213WDloM25xQnRyWnpDYWh6NHJ2VmhnCmdTMzhPOWVXc1kxNzRqZDJXTHpVZk9kazlGTXB5dHpPcG0rWGszR3JIMlJCU1lFWU1sRytWOTcxVkZtbW5QSCsKN2Y2aDd5OXRmNlBBZGZwVGpBNkdpOUU9Ci0tLS0tRU5EIFBSSVZBVEUgS0VZLS0tLS0K';

    public static function pem(): string
    {
        return (string) base64_decode(self::PEM_BASE64, true);
    }

    /**
     * El JWKS tal como lo publicaria Keycloak.
     *
     * @return array<string, mixed>
     */
    public static function jwks(): array
    {
        return ['keys' => [[
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => 'RS256',
            'kid' => self::KID,
            'n' => '18oq6tbn0U7QTL8z6i5dsJEJ2aS8r1wX5sSq7Egwhp9n1RilG4naMnDnon_blkWfgHd-bNhOTgCY_561GWRbu_fng5WY38EtxLD31Ln-nyO08FQZ1LgXRsWoE_8ZUsnG4gRxXNA10pHj6bY_r2W-_H-cDjrHMeLlNsaoSUngFX0khUScun61mDx7ce2QpVIM0L_X6OxAj9tR48l2iSrVaLvohSZ1dnTdoDK28S9ChG44wkz7OQAEvTt60pRgM4Vml2aIPooQmfA438UQ0vS47WxVDZZ5WlfEED6Z44GYK3f6I7VNP-Aq5B8mz-Av202AobjJf6OKrmZYdFzAGT0O3w',
            'e' => 'AQAB',
        ]]];
    }

    /** @param array<string, mixed> $claims */
    public static function token(array $claims): string
    {
        return JWT::encode($claims, self::pem(), 'RS256', self::KID);
    }
}
