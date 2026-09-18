<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Deja que `$pedido->ip()` sea la dirección de la persona y no la del proxy.
 *
 * De `ip()` cuelgan tres cosas que importan: el filtro de rangos del
 * back-office, el freno de intentos del ingreso, y la columna que la bitácora
 * guarda **para siempre**. Detrás del ingress de Container Apps, sin esto, las
 * tres ven la dirección de Azure.
 *
 * `X-Forwarded-For` se recorta a su última entrada porque es la única que no
 * viene del cliente: Container Apps agrega ahí la dirección que él vio, y lo
 * que el cliente haya escrito antes queda a la izquierda. Symfony resuelve la
 * cabecera tomando la primera entrada —la semántica habitual de una cadena de
 * proxies—, que acá sería precisamente la parte falsificable.
 *
 * Va con interruptor y apagado por defecto: confiar en esa cabecera cuando
 * **no** hay un proxy adelante es regalar la lista de rangos, porque entonces
 * quien la escribe es quien conecta.
 */
final class IpRealDetrasDelIngress
{
    public function handle(Request $pedido, Closure $siguiente): mixed
    {
        if (! Config::boolean('app.detras_de_proxy')) {
            return $siguiente($pedido);
        }

        $cabecera = $pedido->headers->get('X-Forwarded-For');

        if (is_string($cabecera) && trim($cabecera) !== '') {
            $entradas = array_map('trim', explode(',', $cabecera));
            $real = (string) end($entradas);

            if ($real !== '') {
                // Se reescribe `REMOTE_ADDR` en vez de declarar proxies
                // confiables: el `TrustProxies` que Laravel trae en la cadena
                // global corre después y pisaría esa configuración. Además
                // evita depender de cómo Symfony resuelve la cadena, que toma
                // la primera entrada — justo la que el cliente controla.
                $pedido->server->set('REMOTE_ADDR', $real);
                $pedido->headers->set('X-Forwarded-For', $real);
            }
        }

        return $siguiente($pedido);
    }
}
