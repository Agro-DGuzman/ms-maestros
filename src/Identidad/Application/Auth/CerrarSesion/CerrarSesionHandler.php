<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\CerrarSesion;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;

final readonly class CerrarSesionHandler implements RequestHandler
{
    public function __construct(
        private SesionRepository $sesiones,
        private EmisorDeToken $emisor,
        private RelojDelSistema $reloj,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof CerrarSesion);

        // Cerrar sesión es idempotente: si no hay nada que cerrar, ya está.
        $sesion = $this->sesiones->porRefreshHash(hash('sha256', $peticion->refreshToken));

        if ($sesion instanceof SesionDeAplicacion) {
            $sesion->cerrar($this->reloj->ahora());
            $this->sesiones->save($sesion);
        }

        $this->emisor->revocar($peticion->refreshToken);

        return Result::success();
    }
}
