<?php

declare(strict_types=1);

namespace BackOffice\Application\Accesos\RevocarAcceso;

use BackOffice\Domain\Bitacora\AccionDeAcceso;
use BackOffice\Domain\Bitacora\AsientoDeBitacora;
use BackOffice\Domain\Bitacora\BitacoraRepository;
use Core\Contracts\Mediator;
use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Application\Habilitacion\DeshabilitarPersona\DeshabilitarPersona;
use Maestros\Domain\Contactos\IdDePersona;

final readonly class RevocarAccesoHandler implements RequestHandler
{
    public function __construct(
        private Mediator $mediator,
        private BitacoraRepository $bitacora,
        private RelojDelSistema $reloj,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof RevocarAcceso);

        $resultado = $this->mediator->send(
            new DeshabilitarPersona(IdDePersona::desde($peticion->idDePersona)),
        );

        if ($resultado->isFailure()) {
            return $resultado;
        }

        $this->bitacora->asentar(AsientoDeBitacora::nuevo(
            operador: $peticion->operador,
            idDePersona: $peticion->idDePersona,
            accion: AccionDeAcceso::Revoco,
            direccionIp: $peticion->direccionIp,
            ocurrioEl: $this->reloj->ahora(),
        ));

        return $resultado;
    }
}
