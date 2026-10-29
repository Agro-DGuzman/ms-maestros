<?php

declare(strict_types=1);

namespace Maestros\Domain\Propiedades;

use Core\Results\DomainException;
use DateTimeImmutable;
use DateTimeZone;
use Maestros\Domain\Socios\CodigoDeSocio;

/**
 * La foto de una propiedad que llega de ms-comercial (EM8). Las reglas son
 * las de los CHECK de `maestros.propiedad`: decididas acá, un dato que no
 * cumple es un evento inválido que se ve en mensajes muertos, y no un error de
 * la base a mitad de la transacción.
 */
final readonly class PropiedadReplicada
{
    private function __construct(
        private string $id,
        private string $nombre,
        private CodigoDeSocio $socio,
        private EstadoDePropiedad $estado,
        private DateTimeImmutable $vigenteDesde,
    ) {}

    public static function desde(string $id, string $nombre, string $cardCode, string $estado, DateTimeImmutable $vigenteDesde): self
    {
        // El patrón del contrato de eventos: deja afuera los espacios y los
        // caracteres de control que el CHECK de la tabla también rechaza.
        if (preg_match('/^[A-Za-z0-9-]{1,30}$/D', $id) !== 1) {
            throw new DomainException(PropiedadErrors::idInvalido($id));
        }

        // Un espacio al borde, un tab, el CR de un CSV o el espacio duro que
        // se pega desde Excel: el CHECK los rechaza en cualquier posición.
        if ($nombre === '' || mb_strlen($nombre) > 200 || trim($nombre, ' ') !== $nombre
            || preg_match('/[\t\n\r\x{00A0}]/u', $nombre) === 1) {
            throw new DomainException(PropiedadErrors::nombreInvalido($nombre));
        }

        $enEstado = EstadoDePropiedad::tryFrom($estado)
            ?? throw new DomainException(PropiedadErrors::estadoInvalido($estado));

        return new self($id, $nombre, CodigoDeSocio::desde($cardCode), $enEstado, $vigenteDesde->setTimezone(new DateTimeZone('UTC')));
    }

    public function id(): string
    {
        return $this->id;
    }

    public function nombre(): string
    {
        return $this->nombre;
    }

    public function socio(): CodigoDeSocio
    {
        return $this->socio;
    }

    public function estado(): EstadoDePropiedad
    {
        return $this->estado;
    }

    public function vigenteDesde(): DateTimeImmutable
    {
        return $this->vigenteDesde;
    }
}
