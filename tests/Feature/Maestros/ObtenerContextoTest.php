<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Application\Contactos\ObtenerContexto\ObtenerContexto;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Soporte\PropiedadesDeEjemplo;
use Tests\Soporte\ReplicaDeEjemplo;

uses(RefreshDatabase::class);

// La persona es su número. Mónica (+59170741828) es contacto solo de C-004871,
// del grupo GRP-014: ve ese socio y ninguno más del grupo.
beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->contexto = fn (string $id) => app(Mediator::class)->send(new ObtenerContexto(IdDePersona::desde($id)));
});

it('Monica ve solo su socio y el nombre de su grupo', function () {
    $contexto = ($this->contexto)(ReplicaDeEjemplo::MONICA)->value();

    expect($contexto->nombre)->toBe('Grupo Monasterio')
        ->and($contexto->iniciales)->toBe('GM')
        ->and($contexto->celular)->toBe('+591 707 41 828')
        ->and($contexto->grupoId)->toBe('GRP-014')
        ->and($contexto->grupoNombre)->toBe('Grupo Monasterio')
        ->and(array_column($contexto->socios, 'cardCode'))->toBe(['C-004871'])
        ->and($contexto->socios[0]['iniciales'])->toBe('SM')
        ->and($contexto->socios[0]['cantidadPropiedades'])->toBe(0);
});

it('el mismo numero en otro socio del grupo suma ese socio', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828', 'p-9999', cardCode: 'C-004872');

    expect(array_column(($this->contexto)(ReplicaDeEjemplo::MONICA)->value()->socios, 'cardCode'))
        ->toBe(['C-004871', 'C-004872']);
});

it('cuenta las propiedades activas de cada socio del numero', function () {
    // C-004871 tiene una tercera dada de baja, que no cuenta.
    PropiedadesDeEjemplo::sembrar();
    ReplicaDeEjemplo::otroContactoCon('+59170741828', 'p-9999', cardCode: 'C-004872');

    $contexto = ($this->contexto)(ReplicaDeEjemplo::MONICA)->value();

    expect(array_column($contexto->socios, 'cantidadPropiedades', 'cardCode'))
        ->toBe(['C-004871' => 2, 'C-004872' => 1]);
});

it('un numero en dos grupos no tiene contexto', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828');

    expect(($this->contexto)(ReplicaDeEjemplo::MONICA)->error->code)->toBe('CONTACTO_NO_ENCONTRADO');
});

it('un grupo que no esta en la replica da el nombre vacio', function () {
    ReplicaDeEjemplo::aGrupo('C-004871', 'GRP-099');

    $contexto = ($this->contexto)(ReplicaDeEjemplo::MONICA)->value();

    expect($contexto->nombre)->toBe('')
        ->and($contexto->iniciales)->toBe('')
        ->and($contexto->grupoId)->toBe('GRP-099')
        ->and(array_column($contexto->socios, 'cardCode'))->toBe(['C-004871']);
});

it('un id por contacto no tiene contexto', function () {
    expect(($this->contexto)('p-8f2b1c40')->error->code)->toBe('CONTACTO_NO_ENCONTRADO');
});

it('un numero que no es de nadie no tiene contexto', function () {
    expect(($this->contexto)('n-59179999999')->error->code)->toBe('CONTACTO_NO_ENCONTRADO');
});
