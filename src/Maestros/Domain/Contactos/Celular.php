<?php

declare(strict_types=1);

namespace Maestros\Domain\Contactos;

use Core\Results\DomainException;

/**
 * El celular llega sin prefijo desde la App; el servicio antepone +591 y
 * normaliza el número replicado de SAP antes de comparar.
 */
final readonly class Celular
{
    private function __construct(private string $e164) {}

    public static function desdeLocalBoliviano(string $numero): self
    {
        $digitos = preg_replace('/\D/', '', $numero) ?? '';

        if (str_starts_with($digitos, '591')) {
            $digitos = substr($digitos, 3);
        }

        if (preg_match('/^[67]\d{7}$/', $digitos) !== 1) {
            throw new DomainException(ContactoErrors::celularInvalido($numero));
        }

        return new self('+591'.$digitos);
    }

    public function e164(): string
    {
        return $this->e164;
    }

    /** `+591 707 41 828`: la forma con que una persona reconoce su número. */
    public function paraMostrar(): string
    {
        $local = substr($this->e164, 4);

        return sprintf('+591 %s %s %s', substr($local, 0, 3), substr($local, 3, 2), substr($local, 5));
    }

    public function equals(self $otro): bool
    {
        return $otro->e164 === $this->e164;
    }
}
