<?php

declare(strict_types=1);

namespace Maestros\Domain\Socios;

use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Maestros\Domain\Grupos\IdDeGrupo;

/**
 * Réplica de `OCRD`. Inmutable y sin constructor público: un `Socio::crear()`
 * en este servicio sería una segunda fuente de verdad esperando a divergir.
 *
 * El constructor con nombre es `replica` y no `reconstituirDesdeSap` a
 * propósito: el agregado no tiene por qué saber el nombre del sistema que lo
 * alimenta.
 */
final class Socio extends AggregateRoot
{
    private function __construct(
        CodigoDeSocio $codigo,
        private readonly RazonSocial $razonSocial,
        private readonly IdDeGrupo $idDeGrupo,
        private readonly DateTimeImmutable $vigenteDesde,
    ) {
        parent::__construct($codigo);
    }

    public static function replica(
        CodigoDeSocio $codigo,
        RazonSocial $razonSocial,
        IdDeGrupo $idDeGrupo,
        DateTimeImmutable $vigenteDesde,
    ): self {
        return new self($codigo, $razonSocial, $idDeGrupo, $vigenteDesde);
    }

    public function codigoDeSocio(): CodigoDeSocio
    {
        $id = $this->id();
        assert($id instanceof CodigoDeSocio);

        return $id;
    }

    public function razonSocial(): RazonSocial
    {
        return $this->razonSocial;
    }

    public function idDeGrupo(): IdDeGrupo
    {
        return $this->idDeGrupo;
    }

    public function vigenteDesde(): DateTimeImmutable
    {
        return $this->vigenteDesde;
    }

    /** Una réplica nunca retrocede. */
    public function debeReemplazarA(DateTimeImmutable $marca): bool
    {
        return $this->vigenteDesde > $marca;
    }
}
