<?php

declare(strict_types=1);

namespace BackOffice\Application\Bitacora\HistorialDePersona;

use BackOffice\Domain\Bitacora\BitacoraRepository;
use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;

final readonly class HistorialDePersonaHandler implements RequestHandler
{
    public function __construct(private BitacoraRepository $bitacora) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof HistorialDePersona);

        // Una persona sin movimientos devuelve lista vacía, no un fallo: no
        // tener historial es una respuesta, no un error.
        return ResultWithValue::of($this->bitacora->historialDe($peticion->idDePersona));
    }
}
