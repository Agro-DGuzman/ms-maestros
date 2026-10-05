<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\RenovarSesion;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Application\Contracts\TokenEmitido;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionErrors;
use Identidad\Domain\Sesiones\SesionRepository;

final readonly class RenovarSesionHandler implements RequestHandler
{
    public function __construct(
        private SesionRepository $sesiones,
        private EmisorDeToken $emisor,
        private RelojDelSistema $reloj,
        private DirectorioDeContactos $directorio,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof RenovarSesion);

        $sesion = $this->sesiones->porRefreshHash(hash('sha256', $peticion->refreshToken));

        if (! $sesion instanceof SesionDeAplicacion || ! $sesion->estaAbierta($this->reloj->ahora())) {
            return ResultWithValue::failure(SesionErrors::refreshInvalido());
        }

        // La sesión dura 30 días y el token 5 minutos: si la renovación no
        // preguntara, una baja en SAP no cortaría nada hasta que la sesión
        // venciera sola. Se cierra, y reactivarla después no la revive.
        if ($this->directorio->contexto($sesion->persona()) === null) {
            $sesion->cerrar($this->reloj->ahora());
            $this->sesiones->save($sesion);

            return ResultWithValue::failure(SesionErrors::refreshInvalido());
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
