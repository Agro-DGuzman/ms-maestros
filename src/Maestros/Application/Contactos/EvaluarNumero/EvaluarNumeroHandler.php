<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos\EvaluarNumero;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Domain\Contactos\ContactoRepository;

final readonly class EvaluarNumeroHandler implements RequestHandler
{
    public function __construct(private ContactoRepository $contactos) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof EvaluarNumero);

        return ResultWithValue::of($this->contactos->alcanceDeNumero($peticion->celular));
    }
}
