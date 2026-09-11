<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\RenovarSesion;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Error;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Application\Contracts\TokenEmitido;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;

final readonly class RenovarSesionHandler implements RequestHandler
{
    public function __construct(
        private SesionRepository $sesiones,
        private EmisorDeToken $emisor,
        private RelojDelSistema $reloj,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof RenovarSesion);

        $sesion = $this->sesiones->porRefreshHash(hash('sha256', $peticion->refreshToken));

        if (! $sesion instanceof SesionDeAplicacion || ! $sesion->estaAbierta($this->reloj->ahora())) {
            return ResultWithValue::failure(
                Error::failure('NO_AUTENTICADO', 'La sesión no está vigente'),
            );
        }

        $token = $this->emisor->renovar($peticion->refreshToken);

        if ($token->isFailure()) {
            return ResultWithValue::failure($token->error);
        }

        $emitido = $token->value();
        assert($emitido instanceof TokenEmitido);

        // Rotación: el refresh viejo deja de servir en cuanto se usa.
        $sesion->asociarRefresh($emitido->refreshToken);
        $this->sesiones->save($sesion);

        return $token;
    }
}
