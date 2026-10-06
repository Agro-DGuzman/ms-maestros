<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Envelope;
use Closure;
use Core\Results\Error;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * La prueba de que el pedido pasó por el APIM: la cabecera la agrega el
 * gateway y nadie más la conoce. El ingress del Container App es externo
 * (el APIM Consumption no alcanza uno interno), así que sin esto cualquiera
 * que conozca su dirección llega directo.
 *
 * En /ingesta es obligatorio siempre. En /v1 depende de `GATEWAY_SECRETO_EN_V1`,
 * que se lee en cada pedido y no al registrar las rutas: `route:cache`
 * congelaría la configuración del momento en que se cacheó.
 */
final class ExigirSecretoDelGateway
{
    public function handle(Request $pedido, Closure $siguiente, string $ambito = 'ingesta'): Response
    {
        if ($ambito === 'v1' && ! Config::boolean('ingesta.gateway.en_v1')) {
            return $siguiente($pedido);
        }

        $secreto = Config::string('ingesta.gateway.secreto');

        // Falla cerrado: un secreto vacío no puede dejar pasar una cabecera vacía.
        if ($secreto === '') {
            Log::error('gateway: falta GATEWAY_SECRETO, se rechaza todo', ['ambito' => $ambito]);

            return self::denegado();
        }

        if (! hash_equals($secreto, (string) $pedido->header('X-Gateway-Secret', ''))) {
            return self::denegado();
        }

        return $siguiente($pedido);
    }

    private static function denegado(): JsonResponse
    {
        return new JsonResponse(
            Envelope::fallo(Error::failure('ACCESO_DENEGADO', 'La llamada no pasó por el gateway.')),
            403,
        );
    }
}
