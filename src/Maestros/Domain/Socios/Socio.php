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
 *
 * Sin grupo económico se replica igual (D12): no entra en el alcance de nadie
 * de afuera, y sus personas de contacto ven solo este socio.
 */
final class Socio extends AggregateRoot
{
    private function __construct(
        CodigoDeSocio $codigo,
        private readonly RazonSocial $razonSocial,
        private readonly ?IdDeGrupo $grupo,
        private readonly DateTimeImmutable $vigenteDesde,
        private readonly bool $activo,
        private readonly ?DateTimeImmutable $dadoDeBajaEl,
        private readonly ?string $origenEsquema,
        private readonly ?int $origenEventoId,
    ) {
        parent::__construct($codigo);
    }

    public static function replica(
        CodigoDeSocio $codigo,
        RazonSocial $razonSocial,
        ?IdDeGrupo $grupo,
        DateTimeImmutable $vigenteDesde,
        bool $activo = true,
        ?DateTimeImmutable $dadoDeBajaEl = null,
        ?string $origenEsquema = null,
        ?int $origenEventoId = null,
    ): self {
        return new self($codigo, $razonSocial, $grupo, $vigenteDesde, $activo, $dadoDeBajaEl, $origenEsquema, $origenEventoId);
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

    public function grupo(): ?IdDeGrupo
    {
        return $this->grupo;
    }

    public function vigenteDesde(): DateTimeImmutable
    {
        return $this->vigenteDesde;
    }

    public function activo(): bool
    {
        return $this->activo;
    }

    public function dadoDeBajaEl(): ?DateTimeImmutable
    {
        return $this->dadoDeBajaEl;
    }

    public function origenEsquema(): ?string
    {
        return $this->origenEsquema;
    }

    public function origenEventoId(): ?int
    {
        return $this->origenEventoId;
    }

    /** Lo único que la App puede ver: activo en SAP y sin baja. */
    public function esVisible(): bool
    {
        return $this->activo && $this->dadoDeBajaEl === null;
    }

    /**
     * La baja fija también la vigencia: un envío leído en SAP antes de la baja
     * tiene vigencia anterior y se ignora, en vez de resucitar al socio.
     */
    public function dadoDeBaja(DateTimeImmutable $momento): self
    {
        return new self(
            $this->codigoDeSocio(), $this->razonSocial, $this->grupo, $momento,
            $this->activo, $momento, $this->origenEsquema, $this->origenEventoId,
        );
    }

    /** Una réplica nunca retrocede. */
    public function debeReemplazarA(DateTimeImmutable $marca): bool
    {
        return $this->vigenteDesde > $marca;
    }
}
