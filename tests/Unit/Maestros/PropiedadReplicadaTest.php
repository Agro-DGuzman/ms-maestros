<?php

declare(strict_types=1);

use Core\Results\DomainException;
use Maestros\Domain\Propiedades\EstadoDePropiedad;
use Maestros\Domain\Propiedades\PropiedadReplicada;

function propiedadReplicada(
    string $id = 'PROP-014',
    string $nombre = 'Lote 14 · San Julián',
    string $cardCode = 'C-004871',
    string $estado = 'activo',
    string $vigente = '2026-10-21T12:00:00.123456Z',
): PropiedadReplicada {
    return PropiedadReplicada::desde($id, $nombre, $cardCode, $estado, new DateTimeImmutable($vigente));
}

it('arma la propiedad del ejemplo del contrato', function () {
    $propiedad = propiedadReplicada();

    expect($propiedad->id())->toBe('PROP-014')
        ->and($propiedad->nombre())->toBe('Lote 14 · San Julián')
        ->and($propiedad->socio()->value())->toBe('C-004871')
        ->and($propiedad->estado())->toBe(EstadoDePropiedad::Activo)
        ->and($propiedad->vigenteDesde()->format('Y-m-d\TH:i:s.uP'))->toBe('2026-10-21T12:00:00.123456+00:00');
});

it('la vigencia queda en UTC', function () {
    expect(propiedadReplicada(vigente: '2026-10-21T08:00:00-04:00')->vigenteDesde()->format('Y-m-d\TH:i:sP'))
        ->toBe('2026-10-21T12:00:00+00:00');
});

it('rechaza lo que el CHECK de la tabla rechazaria', function (array $campos) {
    propiedadReplicada(...$campos);
})->throws(DomainException::class)->with([
    'id vacio' => [['id' => '']],
    'id con espacio' => [['id' => 'PROP 014']],
    'id con guion bajo' => [['id' => 'PROP_014']],
    'id de 31' => [['id' => str_repeat('P', 31)]],
    'nombre vacio' => [['nombre' => '']],
    'nombre con espacio al principio' => [['nombre' => ' Lote 14']],
    'nombre con espacio al final' => [['nombre' => 'Lote 14 ']],
    'nombre con tab' => [['nombre' => "Lote\t14"]],
    'nombre con salto de linea' => [['nombre' => "Lote\n14"]],
    'nombre con retorno de carro' => [['nombre' => "Lote\r14"]],
    'nombre con espacio duro' => [['nombre' => "Lote\u{00A0}14"]],
    'nombre de 201' => [['nombre' => str_repeat('a', 201)]],
    'socio vacio' => [['cardCode' => '']],
    'socio de 16' => [['cardCode' => str_repeat('C', 16)]],
    'estado desconocido' => [['estado' => 'baja']],
    'estado con mayuscula' => [['estado' => 'Activo']],
]);

it('un nombre de 200 con acentos entra', function () {
    expect(mb_strlen(propiedadReplicada(nombre: str_repeat('á', 200))->nombre()))->toBe(200);
});
