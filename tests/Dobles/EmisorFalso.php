<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Core\Results\Error;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\TokenEmitido;
use Maestros\Domain\Contactos\IdDePersona;

final class EmisorFalso implements EmisorDeToken
{
    public bool $caido = false;

    /** @var list<string> */
    public array $pedidos = [];

    public function emitirPara(IdDePersona $persona, string $contrasena): ResultWithValue
    {
        $this->pedidos[] = $persona->value();

        if ($this->caido) {
            return ResultWithValue::failure(
                Error::problem('IDENTIDAD_NO_DISPONIBLE', 'El proveedor de identidad no responde'),
            );
        }

        return ResultWithValue::of(new TokenEmitido('jwt-de-prueba', 'refresh-de-prueba', 900));
    }

    public function renovar(string $refreshToken): ResultWithValue
    {
        return ResultWithValue::of(new TokenEmitido('jwt-2', 'refresh-2', 900));
    }

    public function revocar(string $refreshToken): Result
    {
        return Result::success();
    }
}
