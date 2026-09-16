<?php

declare(strict_types=1);

namespace BackOffice\Presentation\Http;

use BackOffice\Application\Contracts\AutenticadorDeOperador;
use BackOffice\Application\Contracts\IngresoRechazado;
use BackOffice\Infrastructure\Contrasena\AutenticadorDeContrasena;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * El formulario de ingreso del piloto. Existe mientras el back-office no tenga
 * Entra: cuando lo tenga, se borra este controlador, sus dos rutas y su vista,
 * y nada más cambia — el resto del ingreso ya pasa por
 * `AutenticadorDeOperador`.
 */
final readonly class IngresoConContrasenaController
{
    /** Los mismos cinco del desafío de ingreso, por coherencia. */
    private const INTENTOS = 5;

    private const VENTANA_EN_SEGUNDOS = 900;

    public function __construct(private AutenticadorDeOperador $autenticador) {}

    public function formulario(): View
    {
        $this->deContrasena();

        return view('backoffice::formulario');
    }

    public function verificar(Request $pedido): RedirectResponse
    {
        $autenticador = $this->deContrasena();

        // El estado lo puso `/admin/entrar`. Sin él no hay transacción que
        // completar: el formulario se abrió suelto o la cookie venció.
        $estado = $pedido->session()->get('backoffice.estado');

        if (! is_string($estado)) {
            return redirect()->route('admin.entrar');
        }

        $correo = (string) $pedido->string('correo');
        $llave = $this->llaveDelFreno($correo, (string) $pedido->ip());

        if (RateLimiter::tooManyAttempts($llave, self::INTENTOS)) {
            return redirect()->route('admin.formulario')
                ->with('error', IngresoRechazado::demasiadosIntentos()->getMessage());
        }

        try {
            $codigo = $autenticador->emitirCodigoPara($correo, (string) $pedido->string('contrasena'));
        } catch (IngresoRechazado $rechazo) {
            RateLimiter::hit($llave, self::VENTANA_EN_SEGUNDOS);

            return redirect()->route('admin.formulario')->with('error', $rechazo->getMessage());
        }

        RateLimiter::clear($llave);

        // El código va en la URL; la contraseña nunca. De acá en adelante el
        // recorrido es el mismo que tendrá con Entra.
        return redirect()->route('admin.callback', ['code' => $codigo, 'state' => $estado]);
    }

    /**
     * Cuenta por correo y por IP, no solo por IP: los dos operadores salen a
     * internet por la misma oficina, y contar solo la IP dejaría que uno
     * bloquee al otro equivocándose cinco veces.
     */
    private function llaveDelFreno(string $correo, string $ip): string
    {
        return 'backoffice.ingreso.'.hash('sha256', mb_strtolower(trim($correo)).'|'.$ip);
    }

    /**
     * Las rutas se registran siempre, para no depender de que la configuración
     * sea la misma cuando se cachean. Con otro autenticador puesto, este
     * formulario simplemente no está.
     */
    private function deContrasena(): AutenticadorDeContrasena
    {
        if (! $this->autenticador instanceof AutenticadorDeContrasena) {
            abort(404);
        }

        return $this->autenticador;
    }
}
