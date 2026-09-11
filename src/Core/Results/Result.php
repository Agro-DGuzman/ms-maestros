<?php

declare(strict_types=1);

namespace Core\Results;

use InvalidArgumentException;

class Result
{
    protected function __construct(
        public readonly bool $isSuccess,
        public readonly Error $error,
    ) {
        if ($isSuccess && $error !== Error::none()) {
            throw new InvalidArgumentException('Un resultado exitoso no puede llevar un error');
        }

        if (! $isSuccess && $error === Error::none()) {
            throw new InvalidArgumentException('Un resultado fallido necesita un error');
        }
    }

    public function isFailure(): bool
    {
        return ! $this->isSuccess;
    }

    public static function success(): self
    {
        return new self(true, Error::none());
    }

    public static function failure(Error $error): self
    {
        return new self(false, $error);
    }
}
