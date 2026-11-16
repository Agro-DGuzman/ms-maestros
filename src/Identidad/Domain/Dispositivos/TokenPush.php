<?php

declare(strict_types=1);

namespace Identidad\Domain\Dispositivos;

use Core\Results\DomainException;

/**
 * El token de registro de Firebase de un teléfono, en las dos plataformas (en
 * iOS no es el de Apple). Identifica el teléfono de una persona, así que no
 * tiene __toString: escribirlo en un log o en un mensaje tiene que ser una
 * decisión explícita, y no hay ninguna que lo justifique (NP16).
 */
final readonly class TokenPush
{
    private const int LARGO_MAXIMO = 4096;

    private function __construct(private string $valor) {}

    public static function desde(string $valor): self
    {
        $limpio = trim($valor);

        if ($limpio === '' || strlen($limpio) > self::LARGO_MAXIMO || preg_match('/\s/', $limpio) === 1) {
            throw new DomainException(DispositivoErrors::tokenPushInvalido());
        }

        return new self($limpio);
    }

    public function valor(): string
    {
        return $this->valor;
    }

    public function equals(self $otro): bool
    {
        return $otro->valor === $this->valor;
    }
}
