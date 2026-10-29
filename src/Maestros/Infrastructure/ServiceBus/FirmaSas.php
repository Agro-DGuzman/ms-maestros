<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\ServiceBus;

/**
 * El token Shared Access Signature que pide la API REST de Service Bus. La
 * clave se usa tal cual, sin decodificarla de base64: así la firma Azure.
 */
final class FirmaSas
{
    public static function token(string $uriDelRecurso, string $nombreDeClave, string $clave, int $expiraEn): string
    {
        $sr = strtolower(rawurlencode(strtolower($uriDelRecurso)));
        $sig = rawurlencode(base64_encode(hash_hmac('sha256', $sr."\n".$expiraEn, $clave, true)));

        return "SharedAccessSignature sr={$sr}&sig={$sig}&se={$expiraEn}&skn={$nombreDeClave}";
    }
}
