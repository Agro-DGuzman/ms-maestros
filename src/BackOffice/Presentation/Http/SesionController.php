<?php

declare(strict_types=1);

namespace BackOffice\Presentation\Http;

use BackOffice\Application\Contracts\AutenticadorDeOperador;
use BackOffice\Application\Contracts\IngresoRechazado;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class SesionController
{
    public function __construct(private AutenticadorDeOperador $autenticador) {}

    public function entrar(Request $pedido): RedirectResponse
    {
        $estado = Str::random(40);
        $verificador = Str::random(64);

        $pedido->session()->put('backoffice.estado', $estado);
        $pedido->session()->put('backoffice.verificador', $verificador);

        $desafio = rtrim(strtr(base64_encode(hash('sha256', $verificador, true)), '+/', '-_'), '=');

        return redirect()->away($this->autenticador->urlDeIngreso($estado, $desafio));
    }

    public function callback(Request $pedido): RedirectResponse
    {
        $estadoEsperado = $pedido->session()->pull('backoffice.estado');
        $verificador = $pedido->session()->pull('backoffice.verificador');

        $estadoRecibido = $pedido->query('state');

        // Sin estado o sin verificador en la sesión no hay transacción que
        // completar: la cookie venció, o el callback llegó sin haber pasado
        // antes por /admin/entrar.
        if (! is_string($estadoEsperado) || ! is_string($verificador) || ! is_string($estadoRecibido)
            || ! hash_equals($estadoEsperado, $estadoRecibido)) {
            return redirect()->route('admin.entrar')
                ->with('error', 'El ingreso expiró. Volvé a intentar.');
        }

        $codigo = $pedido->query('code');

        try {
            $operador = $this->autenticador->resolver(
                is_string($codigo) ? $codigo : '',
                $verificador,
            );
        } catch (IngresoRechazado $rechazo) {
            return redirect()->route('admin.entrar')->with('error', $rechazo->getMessage());
        }

        // Antes de guardar al operador: quien haya plantado el identificador
        // de sesión antes del login no lo hereda después.
        $pedido->session()->regenerate();
        SesionDeOperador::guardar($operador);

        return redirect()->route('admin.contactos');
    }

    public function salir(Request $pedido): RedirectResponse
    {
        SesionDeOperador::olvidar();
        $pedido->session()->invalidate();
        $pedido->session()->regenerateToken();

        return redirect()->route('admin.entrar');
    }
}
