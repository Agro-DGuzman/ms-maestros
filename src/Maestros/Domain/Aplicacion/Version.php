<?php

declare(strict_types=1);

namespace Maestros\Domain\Aplicacion;

use Core\Results\DomainException;
use Core\Results\Error;

/** Una versión de la App en formato semántico `MAYOR.MENOR.PARCHE`. */
final readonly class Version
{
    private function __construct(
        private int $mayor,
        private int $menor,
        private int $parche,
    ) {}

    public static function desde(string $texto): self
    {
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)$/', $texto, $partes) !== 1) {
            throw new DomainException(Error::validation(
                'VERSION_INVALIDA',
                'La versión {texto} no tiene el formato MAYOR.MENOR.PARCHE',
                $texto,
            ));
        }

        return new self((int) $partes[1], (int) $partes[2], (int) $partes[3]);
    }

    public function menorQue(self $otra): bool
    {
        return [$this->mayor, $this->menor, $this->parche] < [$otra->mayor, $otra->menor, $otra->parche];
    }

    public function texto(): string
    {
        return "{$this->mayor}.{$this->menor}.{$this->parche}";
    }
}
