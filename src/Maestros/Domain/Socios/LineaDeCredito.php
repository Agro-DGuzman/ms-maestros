<?php

declare(strict_types=1);

namespace Maestros\Domain\Socios;

use Core\Results\DomainException;

/**
 * `OCRD.CreditLine`, en dólares. Se guarda y se devuelve como el texto que
 * mandó SAP porque este servicio no hace cuentas con ella: solo se la pasa a
 * ms-comercial, y un número de por medio pierde decimales en el camino.
 *
 * El patrón es el de ms-comercial con el tope de decimales de SAP, así que lo
 * que entra acá ms-comercial lo acepta.
 */
final readonly class LineaDeCredito
{
    public const string PATRON = '^\d{1,15}(\.\d{1,6})?$';

    private function __construct(private string $texto) {}

    public static function desde(string $texto): self
    {
        // Sin `D`, el `$` acepta un salto de línea al final.
        if (preg_match('/'.self::PATRON.'/D', $texto) !== 1) {
            throw new DomainException(SocioErrors::lineaDeCreditoInvalida($texto));
        }

        return new self($texto);
    }

    public function texto(): string
    {
        return $this->texto;
    }
}
