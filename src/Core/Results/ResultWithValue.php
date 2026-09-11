<?php

declare(strict_types=1);

namespace Core\Results;

use LogicException;

/**
 * Nota: el core Java usa sobrecarga (`Result.success(T)`), que PHP no tiene.
 * Acá el éxito con valor se construye con `of()` y el fallo con `failure()`.
 *
 * No lleva `@template`: `failure()` no tiene valor con el que instanciarlo, así
 * que la promesa genérica sería falsa. Quien consume estrecha con `assert`.
 */
final class ResultWithValue extends Result
{
    private function __construct(
        private readonly mixed $valor,
        bool $isSuccess,
        Error $error,
    ) {
        parent::__construct($isSuccess, $error);
    }

    public function value(): mixed
    {
        if ($this->isFailure()) {
            throw new LogicException('No se puede leer el valor de un resultado fallido');
        }

        return $this->valor;
    }

    public static function of(mixed $valor): self
    {
        return $valor !== null
            ? new self($valor, true, Error::none())
            : new self(null, false, Error::nullValue());
    }

    public static function failure(Error $error): self
    {
        return new self(null, false, $error);
    }
}
