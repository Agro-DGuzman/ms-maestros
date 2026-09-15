<?php

declare(strict_types=1);

namespace BackOffice\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class RestringirPorIp
{
    /** @param list<string> $rangos rangos CIDR o IPs sueltas */
    public function __construct(private array $rangos, private string $entorno) {}

    public function handle(Request $pedido, Closure $siguiente): mixed
    {
        if ($this->rangos === []) {
            // En producción, no configurar rangos abriría el back-office al
            // mundo. Falla al primer pedido, que es ruidoso y temprano.
            if ($this->entorno === 'production') {
                throw new RuntimeException('BACKOFFICE_SIN_RANGOS_IP');
            }

            return $siguiente($pedido);
        }

        if (! IpUtils::checkIp((string) $pedido->ip(), $this->rangos)) {
            throw new AccessDeniedHttpException;
        }

        return $siguiente($pedido);
    }
}
