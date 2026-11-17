<?php

declare(strict_types=1);

namespace Identidad\Application\Push\CambiarTokenPush;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Dispositivos\DispositivoErrors;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Sesiones\SesionRepository;

final readonly class CambiarTokenPushHandler implements RequestHandler
{
    public function __construct(
        private SesionRepository $sesiones,
        private RelojDelSistema $reloj,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof CambiarTokenPush);

        $ahora = $this->reloj->ahora();
        $token = TokenPush::desde($peticion->tokenPush);

        // Solo entre las de quien llama: con el token de una persona no se
        // toca la sesión de otra, aunque sepa la instalación.
        foreach ($this->sesiones->abiertasDe($peticion->persona) as $sesion) {
            if (! $sesion->estaAbierta($ahora)
                || $sesion->dispositivo()?->instalacion()->value() !== $peticion->instalacionId) {
                continue;
            }

            // Igual que en el login: un token es de la última sesión que lo
            // registró, o el push de otra sonaría en este teléfono.
            foreach ($this->sesiones->abiertasConEsteTokenPush($token, $ahora) as $conElMismo) {
                if (! $conElMismo->idDeSesion()->equals($sesion->idDeSesion())) {
                    $conElMismo->quitarTokenPush();
                    $this->sesiones->save($conElMismo);
                }
            }

            $sesion->cambiarTokenPush($token, $ahora);
            $this->sesiones->save($sesion);

            return Result::success();
        }

        return Result::failure(DispositivoErrors::noEncontrado());
    }
}
