<?php

declare(strict_types=1);

namespace Maestros\Domain\Socios;

use Core\Contracts\EntityId;
use Core\Results\DomainException;

/** El `OCRD.CardCode` de SAP. No se inventa otro identificador. */
final readonly class CodigoDeSocio implements EntityId
{
    private function __construct(private string $codigo) {}

    public static function desde(string $codigo): self
    {
        $limpio = trim($codigo);

        if ($limpio === '' || mb_strlen($limpio) > 15) {
            throw new DomainException(SocioErrors::codigoInvalido($codigo));
        }

        return new self($limpio);
    }

    public function value(): string
    {
        return $this->codigo;
    }

    public function equals(EntityId $otro): bool
    {
        return $otro instanceof self && $otro->codigo === $this->codigo;
    }
}
