<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Envelope;
use App\Http\PersonaAutenticada;
use Closure;
use Core\Results\Error;
use Identidad\Application\Contracts\VerificadorDeToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class AutenticarPorToken
{
    public function __construct(private VerificadorDeToken $verificador) {}

    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $cabecera = (string) $peticion->header('Authorization', '');

        if (! str_starts_with($cabecera, 'Bearer ')) {
            return $this->noAutenticado();
        }

        $persona = $this->verificador->verificar(substr($cabecera, 7));

        if ($persona === null) {
            return $this->noAutenticado();
        }

        $peticion->attributes->set(PersonaAutenticada::ATRIBUTO, $persona);

        return $siguiente($peticion);
    }

    private function noAutenticado(): JsonResponse
    {
        return new JsonResponse(
            Envelope::fallo(Error::failure('NO_AUTENTICADO', 'Falta un token válido')),
            401,
        );
    }
}
