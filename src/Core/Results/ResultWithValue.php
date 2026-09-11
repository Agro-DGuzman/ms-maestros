<?php

declare(strict_types=1);

namespace Core\Results;

use LogicException;

/**
 * @template T
 *
 * Nota: el core Java usa sobrecarga (`Result.success(T)`), que PHP no tiene.
 * Acá el éxito con valor se construye con `of()` y el fallo con `failure()`.
 */
final class ResultWithValue extends Result
{
    /** @param T|null $valor */
    private function __construct(
        private readonly mixed $valor,
        bool $isSuccess,
        Error $error,
    ) {
        parent::__construct($isSuccess, $error);
    }

    /** @return T */
    public function value(): mixed
    {
        if ($this->isFailure()) {
            throw new LogicException('No se puede leer el valor de un resultado fallido');
        }

        return $this->valor;
    }

    /**
     * @param  T|null  $valor
     * @return self<T>
     */
    public static function of(mixed $valor): self
    {
        return $valor !== null
            ? new self($valor, true, Error::none())
            : new self(null, false, Error::nullValue());
    }

    /** @return self<T> */
    public static function failure(Error $error): self
    {
        return new self(null, false, $error);
    }
}
