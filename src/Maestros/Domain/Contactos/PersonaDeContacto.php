<?php

declare(strict_types=1);

namespace Maestros\Domain\Contactos;

use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\Iniciales;
use Maestros\Domain\Socios\RazonSocial;

/** Réplica de `OCPR`. Vive dentro del socio; es quien entra a la App. */
final class PersonaDeContacto extends AggregateRoot
{
    private function __construct(
        IdDePersona $id,
        private readonly CodigoDeSocio $codigoDeSocio,
        private readonly string $nombre,
        private readonly ?Celular $celular,
        private readonly ?DateTimeImmutable $habilitadaEl,
        private readonly DateTimeImmutable $vigenteDesde,
        private readonly bool $activa,
        private readonly ?DateTimeImmutable $dadoDeBajaEl,
    ) {
        parent::__construct($id);
    }

    /**
     * Sin celular se replica igual: SAP puede traer uno vacío o un fijo, y eso
     * no es motivo para rechazar al socio entero. Esa persona simplemente no
     * puede entrar.
     */
    public static function replica(
        IdDePersona $id,
        CodigoDeSocio $codigoDeSocio,
        string $nombre,
        ?Celular $celular,
        ?DateTimeImmutable $habilitadaEl,
        DateTimeImmutable $vigenteDesde,
        bool $activa = true,
        ?DateTimeImmutable $dadoDeBajaEl = null,
    ): self {
        return new self($id, $codigoDeSocio, trim($nombre), $celular, $habilitadaEl, $vigenteDesde, $activa, $dadoDeBajaEl);
    }

    public function idDePersona(): IdDePersona
    {
        $id = $this->id();
        assert($id instanceof IdDePersona);

        return $id;
    }

    public function codigoDeSocio(): CodigoDeSocio
    {
        return $this->codigoDeSocio;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function iniciales(): Iniciales
    {
        return RazonSocial::desde($this->nombre)->iniciales();
    }

    public function celular(): ?Celular
    {
        return $this->celular;
    }

    public function activa(): bool
    {
        return $this->activa;
    }

    public function dadoDeBajaEl(): ?DateTimeImmutable
    {
        return $this->dadoDeBajaEl;
    }

    public function habilitadaEl(): ?DateTimeImmutable
    {
        return $this->habilitadaEl;
    }

    /** Estar registrada en SAP no habilita: la habilitación la concede Agropartners. */
    public function estaHabilitada(): bool
    {
        return $this->habilitadaEl !== null;
    }

    public function vigenteDesde(): DateTimeImmutable
    {
        return $this->vigenteDesde;
    }

    public function debeReemplazarA(DateTimeImmutable $marca): bool
    {
        return $this->vigenteDesde > $marca;
    }
}
