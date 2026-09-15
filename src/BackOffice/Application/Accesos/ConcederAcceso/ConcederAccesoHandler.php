<?php

declare(strict_types=1);

namespace BackOffice\Application\Accesos\ConcederAcceso;

use BackOffice\Domain\Bitacora\AccionDeAcceso;
use BackOffice\Domain\Bitacora\AsientoDeBitacora;
use BackOffice\Domain\Bitacora\BitacoraRepository;
use Core\Contracts\Mediator;
use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Application\Habilitacion\HabilitarPersona\HabilitarPersona;
use Maestros\Domain\Contactos\IdDePersona;

/**
 * El back-office no sabe habilitar: despacha el caso de uso de Identidad que
 * ya existe y registra el hecho. Si Identidad cambia cómo habilita, acá no se
 * toca nada.
 */
final readonly class ConcederAccesoHandler implements RequestHandler
{
    public function __construct(
        private Mediator $mediator,
        private BitacoraRepository $bitacora,
        private RelojDelSistema $reloj,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof ConcederAcceso);

        $resultado = $this->mediator->send(
            new HabilitarPersona(IdDePersona::desde($peticion->idDePersona)),
        );

        // Solo si Identidad tuvo éxito: una bitácora que registre intentos
        // fallidos miente sobre quién tiene acceso, que es exactamente la
        // pregunta que se le va a hacer.
        if ($resultado->isFailure()) {
            return $resultado;
        }

        $this->bitacora->asentar(AsientoDeBitacora::nuevo(
            operador: $peticion->operador,
            idDePersona: $peticion->idDePersona,
            accion: AccionDeAcceso::Concedio,
            direccionIp: $peticion->direccionIp,
            ocurrioEl: $this->reloj->ahora(),
        ));

        return $resultado;
    }
}
