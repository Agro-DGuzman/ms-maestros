<?php

declare(strict_types=1);

use Core\Contracts\EntityId;
use Core\Domain\AggregateRoot;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Presentation\Http\EcoDeCodigoDePrueba;
use Maestros\Domain\Contactos\Celular;

/** El resguardo salta en el constructor, antes de tocar el repositorio. */
function repositorioQueNoSeUsa(): DesafioRepository
{
    return new class implements DesafioRepository
    {
        public function find(EntityId $id): ?AggregateRoot
        {
            return null;
        }

        public function add(AggregateRoot $agregado): void {}

        public function save(AggregateRoot $agregado): void {}

        public function emitidosDesde(Celular $celular, DateTimeImmutable $desde): int
        {
            return 0;
        }
    };
}

it('con numeros de prueba se niega a existir en produccion', function () {
    // En producción no hay números de prueba que valgan: cualquier entrada en
    // la lista es un salteo del OTP para ese número.
    expect(fn () => new EcoDeCodigoDePrueba(repositorioQueNoSeUsa(), ['+59170741828'], 'production'))
        ->toThrow(RuntimeException::class, 'OTP_ECO_EN_PRODUCCION');
});

it('con la lista vacia puede existir en produccion', function () {
    // Vacía es apagado, y es el valor por defecto: tiene que poder desplegarse
    // la misma imagen en producción sin tocar nada.
    expect(new EcoDeCodigoDePrueba(repositorioQueNoSeUsa(), [], 'production'))
        ->toBeInstanceOf(EcoDeCodigoDePrueba::class);
});
