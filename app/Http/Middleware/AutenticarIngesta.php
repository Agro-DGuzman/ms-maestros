<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Envelope;
use App\Http\MapaDeErroresHttp;
use App\Ingesta\VerificadorEntra;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El token de Entra del Sincronizador, validado acá además de en el gateway (D7). */
final readonly class AutenticarIngesta
{
    public function __construct(private VerificadorEntra $verificador) {}

    public function handle(Request $pedido, Closure $siguiente): Response
    {
        $cabecera = (string) $pedido->header('Authorization', '');
        $jwt = str_starts_with($cabecera, 'Bearer ') ? substr($cabecera, 7) : '';

        $resultado = $this->verificador->verificar($jwt);

        if ($resultado->isFailure()) {
            return new JsonResponse(Envelope::fallo($resultado->error), MapaDeErroresHttp::status($resultado->error));
        }

        return $siguiente($pedido);
    }
}
