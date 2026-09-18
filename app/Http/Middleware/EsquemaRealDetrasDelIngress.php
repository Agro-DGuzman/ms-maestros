<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Deja que el pedido sepa que la persona entró por HTTPS.
 *
 * El ingress de Container Apps atiende HTTPS y le pasa a la app HTTP plano.
 * Cada URL que la app arma —la acción de un formulario, una redirección— sale
 * del esquema del pedido, así que sin esto todas apuntan a `http://`. El
 * navegador avisa que el formulario no es seguro, el ingress redirige a HTTPS,
 * y en esa redirección el POST llega como GET: el ingreso al back-office no
 * funciona.
 *
 * Mismo interruptor y misma forma que `IpRealDetrasDelIngress`, por la misma
 * razón: el `TrustProxies` de la cadena global pisaría una configuración de
 * proxies confiables, y sin un proxy adelante la cabecera la escribe quien
 * conecta.
 */
final class EsquemaRealDetrasDelIngress
{
    public function handle(Request $pedido, Closure $siguiente): mixed
    {
        if (! Config::boolean('app.detras_de_proxy')) {
            return $siguiente($pedido);
        }

        $cabecera = $pedido->headers->get('X-Forwarded-Proto');

        if (is_string($cabecera)) {
            $entradas = array_map('trim', explode(',', $cabecera));

            if (strtolower((string) end($entradas)) === 'https') {
                $pedido->server->set('HTTPS', 'on');
            }
        }

        return $siguiente($pedido);
    }
}
