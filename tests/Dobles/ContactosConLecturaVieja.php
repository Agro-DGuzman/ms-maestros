<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use DateTimeImmutable;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Infrastructure\Persistence\EloquentContactoRepository;

/**
 * El repositorio real, salvo que `find()` devuelve a la persona sin
 * habilitación: es lo que leyó la ingesta un instante antes de que el
 * back-office la habilitara. Sirve para reproducir esa carrera sin hilos.
 */
final class ContactosConLecturaVieja implements ContactoRepository
{
    public function __construct(private EloquentContactoRepository $real) {}

    public function find(EntityId $id): ?PersonaDeContacto
    {
        $persona = $this->real->find($id);

        return $persona === null ? null : PersonaDeContacto::replica(
            $persona->idDePersona(), $persona->codigoDeSocio(), $persona->nombre(), $persona->celular(),
            null, $persona->vigenteDesde(), $persona->activa(), $persona->dadoDeBajaEl(),
        );
    }

    public function add(AggregateRoot $agregado): void
    {
        $this->real->add($agregado);
    }

    public function save(AggregateRoot $agregado): void
    {
        $this->real->save($agregado);
    }

    public function replicar(PersonaDeContacto $persona): void
    {
        $this->real->replicar($persona);
    }

    public function porCelular(Celular $celular): ?PersonaDeContacto
    {
        return $this->real->porCelular($celular);
    }

    public function visible(IdDePersona $id): ?PersonaDeContacto
    {
        return $this->real->visible($id);
    }

    public function habilitadas(): array
    {
        return $this->real->habilitadas();
    }

    public function darDeBajaLosQueNoVinieron(CodigoDeSocio $socio, array $vistos, DateTimeImmutable $momento): void
    {
        $this->real->darDeBajaLosQueNoVinieron($socio, $vistos, $momento);
    }

    public function darDeBajaAusentes(array $vistos, DateTimeImmutable $momento): void
    {
        $this->real->darDeBajaAusentes($vistos, $momento);
    }
}
