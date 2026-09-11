<?php

declare(strict_types=1);

namespace Core\Results;

final class ValidationError extends Error
{
    /** @var list<Error> */
    private readonly array $errores;

    public function __construct(Error ...$errores)
    {
        parent::__construct(
            'Validation.General',
            'Ocurrieron uno o más errores de validación',
            ErrorType::Validation,
        );

        $this->errores = array_values($errores);
    }

    /** @return list<Error> */
    public function errors(): array
    {
        return $this->errores;
    }

    public static function fromResults(Result ...$resultados): self
    {
        $fallidos = array_map(
            static fn (Result $r): Error => $r->error,
            array_filter($resultados, static fn (Result $r): bool => $r->isFailure()),
        );

        return new self(...array_values($fallidos));
    }
}
