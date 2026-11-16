<?php

declare(strict_types=1);

namespace Identidad\Application\Push\RegistrarPreferencias;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Domain\Push\PreferenciasDePush;
use Identidad\Domain\Push\PreferenciasDePushRepository;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * Se guarda aunque la persona no tenga sesiones ni contactos: ms-comercial
 * publica una vez todas las que existen, y las de quien todavía no entró
 * tienen que estar cuando entre.
 *
 * @return ResultWithValue<bool> si se aplicó
 */
final readonly class RegistrarPreferenciasHandler implements RequestHandler
{
    public function __construct(private PreferenciasDePushRepository $preferencias) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof RegistrarPreferencias);

        return ResultWithValue::of($this->preferencias->guardarSiEsMasNueva(PreferenciasDePush::de(
            IdDePersona::desde($peticion->persona),
            $peticion->facturas,
            $peticion->pagos,
            $peticion->reportes,
            $peticion->catalogo,
            $peticion->ocurridoEn,
        )));
    }
}
