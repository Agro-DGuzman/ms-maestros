<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\CerrarSesion;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
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

    /** El valor dice si había una sesión abierta que cerrar. */
    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof CerrarSesion);

        $ahora = $this->reloj->ahora();
        $aCerrar = [];

        if ($peticion->refreshToken !== null) {
            $sesion = $this->sesiones->porRefreshHash(hash('sha256', $peticion->refreshToken));

            // Con el token de una persona no se cierra la sesión de otra.
            if ($sesion instanceof SesionDeAplicacion && ! $sesion->persona()->equals($peticion->persona)) {
                return ResultWithValue::of(false);
            }

            if ($sesion instanceof SesionDeAplicacion) {
                $aCerrar[] = $sesion;
            }

            $this->emisor->revocar($peticion->refreshToken);
        }

        if ($peticion->instalacionId !== null) {
            foreach ($this->sesiones->abiertasDe($peticion->persona) as $abierta) {
                if ($abierta->dispositivo()?->instalacion()->value() === $peticion->instalacionId) {
                    $aCerrar[] = $abierta;
                }
            }
        }

        // Cerrar sesión es idempotente: si no hay nada abierto, ya está.
        $cerrada = false;

        foreach ($aCerrar as $sesion) {
            if ($sesion->estaAbierta($ahora)) {
                $sesion->cerrar($ahora);
                $this->sesiones->save($sesion);
                $cerrada = true;
            }
        }

        return ResultWithValue::of($cerrada);
    }
}
