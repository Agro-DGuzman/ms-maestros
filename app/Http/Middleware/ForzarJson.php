<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * La ingesta la consume una máquina, que no siempre manda
 * `Accept: application/json`. Sin él, Laravel responde a una validación
 * fallida con una redirección 302 en lugar del envelope 400, y el
 * Sincronizador clasificaría eso como un error definitivo sin explicación.
 */
final class ForzarJson
{
    public function handle(Request $pedido, Closure $siguiente): Response
    {
        $pedido->headers->set('Accept', 'application/json');

        return $siguiente($pedido);
    }
}
