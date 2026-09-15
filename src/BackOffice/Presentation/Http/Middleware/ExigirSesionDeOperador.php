<?php

declare(strict_types=1);

namespace BackOffice\Presentation\Http\Middleware;

use BackOffice\Presentation\Http\SesionDeOperador;
use Closure;
use Illuminate\Http\Request;

final class ExigirSesionDeOperador
{
    public function handle(Request $pedido, Closure $siguiente): mixed
    {
        if (SesionDeOperador::actual() === null) {
            return redirect()->route('admin.entrar');
        }

        return $siguiente($pedido);
    }
}
