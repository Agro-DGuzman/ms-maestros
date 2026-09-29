<?php

declare(strict_types=1);

namespace BackOffice\Presentation\Http\Middleware;

use BackOffice\Domain\Operadores\Permiso;
use BackOffice\Presentation\Http\SesionDeOperador;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Va después de `backoffice.sesion`: acá ya hay operador, lo que falta saber
 * es si esta sección es suya. Un 403 y no una redirección: volver a ingresar
 * no le daría el permiso.
 */
final class ExigirPermiso
{
    public function handle(Request $pedido, Closure $siguiente, string $permiso): mixed
    {
        $operador = SesionDeOperador::actual();

        if ($operador === null || ! $operador->puede(Permiso::from($permiso))) {
            return new Response('No tenés permiso para esta sección.', 403);
        }

        return $siguiente($pedido);
    }
}
