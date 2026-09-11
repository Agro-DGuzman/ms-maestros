<?php

declare(strict_types=1);

namespace Maestros\Domain\Socios;

use Core\Results\DomainException;

final readonly class RazonSocial
{
    private function __construct(private string $texto) {}

    public static function desde(string $texto): self
    {
        $limpio = trim(preg_replace('/\s+/u', ' ', $texto) ?? $texto);

        if ($limpio === '') {
            throw new DomainException(SocioErrors::razonSocialVacia());
        }

        return new self($limpio);
    }

    public function texto(): string
    {
        return $this->texto;
    }

    /**
     * Las iniciales se derivan, no se guardan: almacenarlas abre la puerta a
     * que dejen de coincidir con el nombre que abrevian.
     */
    public function iniciales(): Iniciales
    {
        $palabras = explode(' ', $this->texto);
        $letras = '';

        foreach (array_slice($palabras, 0, 2) as $palabra) {
            $letras .= mb_strtoupper(mb_substr($palabra, 0, 1));
        }

        return new Iniciales($letras);
    }
}
