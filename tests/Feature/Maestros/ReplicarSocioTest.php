<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Results\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Application\Ingesta\ContactoIngresado;
use Maestros\Application\Ingesta\GrupoIngresado;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\ReplicarSocio\ReplicarSocio;
use Maestros\Application\Ingesta\ResultadoDeIngesta;
use Maestros\Application\Ingesta\SocioIngresado;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioRepository;
use Maestros\Infrastructure\Persistence\ContactoRecord;
use Maestros\Infrastructure\Persistence\SocioRecord;
use Tests\Soporte\CuerpoDeIngesta;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->momento = new DateTimeImmutable('2026-10-05T12:30:00Z');
});

function replicar(SocioIngresado $socio, OperacionDeIngesta $operacion = OperacionDeIngesta::Crear, ?string $ruta = null): Result
{
    return app(Mediator::class)->send(new ReplicarSocio(
        $operacion,
        $ruta ?? ($operacion === OperacionDeIngesta::Reemplazar ? $socio->cardCode : null),
        $socio,
        test()->momento,
    ));
}

function socioReplicado(string $cardCode = 'C-900001'): ?Socio
{
    return app(SocioRepository::class)->find(CodigoDeSocio::desde($cardCode));
}

function contactoReplicado(string $id): ?PersonaDeContacto
{
    return app(ContactoRepository::class)->find(IdDePersona::desde($id));
}

it('crear guarda grupo, socio y contactos', function () {
    $resultado = replicar(CuerpoDeIngesta::socio());

    expect($resultado->value())->toBe(ResultadoDeIngesta::Aplicado)
        ->and(app(GrupoRepository::class)->find(IdDeGrupo::desde('GRP-900'))?->segmento())->toBe('Agroindustrial')
        ->and(socioReplicado()?->grupo()?->value())->toBe('GRP-900')
        ->and(socioReplicado()?->esVisible())->toBeTrue()
        ->and(socioReplicado()?->origenEventoId())->toBe(1842)
        ->and(contactoReplicado('p-1523')?->celular()?->e164())->toBe('+59170741828');
});

it('crear un socio que ya existe responde SOCIO_YA_EXISTE, aunque este inactivo', function () {
    replicar(CuerpoDeIngesta::socio(activo: false));

    $resultado = replicar(CuerpoDeIngesta::socio(vigenteDesde: '2026-10-05T13:00:00Z'));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('SOCIO_YA_EXISTE');
});

it('crear un socio dado de baja lo reactiva', function () {
    replicar(CuerpoDeIngesta::socio());
    SocioRecord::query()->where('codigo_de_socio', 'C-900001')->update(['dado_de_baja_el' => '2026-10-05 12:10:00', 'vigente_desde' => '2026-10-05 12:10:00']);

    $resultado = replicar(CuerpoDeIngesta::socio(razonSocial: 'Agro Prueba Nueva SRL', vigenteDesde: '2026-10-05T13:00:00Z'));

    expect($resultado->value())->toBe(ResultadoDeIngesta::Aplicado)
        ->and(socioReplicado()?->dadoDeBajaEl())->toBeNull()
        ->and(socioReplicado()?->razonSocial()->texto())->toBe('Agro Prueba Nueva SRL');
});

it('reemplazar uno inexistente o dado de baja responde SOCIO_NO_ENCONTRADO', function () {
    expect(replicar(CuerpoDeIngesta::socio(), OperacionDeIngesta::Reemplazar)->error->code)->toBe('SOCIO_NO_ENCONTRADO');

    replicar(CuerpoDeIngesta::socio());
    SocioRecord::query()->where('codigo_de_socio', 'C-900001')->update(['dado_de_baja_el' => '2026-10-05 12:10:00']);

    expect(replicar(CuerpoDeIngesta::socio(vigenteDesde: '2026-10-05T13:00:00Z'), OperacionDeIngesta::Reemplazar)->error->code)
        ->toBe('SOCIO_NO_ENCONTRADO');
});

it('reemplazar con otro cardCode responde CARDCODE_NO_COINCIDE y no escribe nada', function () {
    $resultado = replicar(CuerpoDeIngesta::socio(), OperacionDeIngesta::Reemplazar, 'C-900002');

    expect($resultado->error->code)->toBe('CARDCODE_NO_COINCIDE')
        ->and(app(GrupoRepository::class)->find(IdDeGrupo::desde('GRP-900')))->toBeNull()
        ->and(socioReplicado())->toBeNull();
});

it('un proveedor al crear no se guarda', function () {
    $resultado = replicar(CuerpoDeIngesta::socio(tipoSap: 'S'));

    expect($resultado->value())->toBe(ResultadoDeIngesta::NoConservado)
        ->and(socioReplicado())->toBeNull()
        ->and(app(GrupoRepository::class)->find(IdDeGrupo::desde('GRP-900')))->toBeNull();
});

it('un socio que pasa a proveedor se da de baja con sus contactos', function () {
    replicar(CuerpoDeIngesta::socio());

    $resultado = replicar(CuerpoDeIngesta::socio(tipoSap: 'S', vigenteDesde: '2026-10-05T13:00:00Z'), OperacionDeIngesta::Reemplazar);

    expect($resultado->value())->toBe(ResultadoDeIngesta::DadoDeBaja)
        ->and(socioReplicado()?->dadoDeBajaEl())->toEqual($this->momento)
        ->and(contactoReplicado('p-1523')?->dadoDeBajaEl())->toEqual($this->momento);
});

it('un cuerpo viejo se ignora entero, grupo incluido', function () {
    replicar(CuerpoDeIngesta::socio());

    $resultado = replicar(CuerpoDeIngesta::socio(
        razonSocial: 'Nombre viejo',
        grupo: new GrupoIngresado('GRP-900', 'Nombre de grupo viejo', null),
        vigenteDesde: '2026-10-05T11:59:00Z',
    ), OperacionDeIngesta::Reemplazar);

    expect($resultado->value())->toBe(ResultadoDeIngesta::IgnoradoPorViejo)
        ->and(socioReplicado()?->razonSocial()->texto())->toBe('Agro Prueba SRL')
        ->and(app(GrupoRepository::class)->find(IdDeGrupo::desde('GRP-900'))?->nombre())->toBe('Grupo Prueba');
});

it('un cuerpo del mismo segundo que lo guardado se ignora', function () {
    // Lo guardado se trunca al segundo: sin truncar también el cuerpo, uno
    // viejo dentro del mismo segundo parecería más nuevo y pisaría al nuevo.
    replicar(CuerpoDeIngesta::socio(vigenteDesde: '2026-10-05T12:00:00.900Z'));

    $resultado = replicar(CuerpoDeIngesta::socio(razonSocial: 'Del mismo segundo', vigenteDesde: '2026-10-05T12:00:00.500Z'), OperacionDeIngesta::Reemplazar);

    expect($resultado->value())->toBe(ResultadoDeIngesta::IgnoradoPorViejo)
        ->and(socioReplicado()?->razonSocial()->texto())->toBe('Agro Prueba SRL');
});

it('el grupo conserva su vigencia propia', function () {
    // Otro socio del mismo grupo lo renombró a las 12:05; un cuerpo de las
    // 12:01 de este socio trae el nombre viejo del grupo.
    replicar(CuerpoDeIngesta::socio());
    replicar(CuerpoDeIngesta::socio(
        cardCode: 'C-900002', razonSocial: 'Otro socio',
        grupo: new GrupoIngresado('GRP-900', 'Grupo Prueba Renombrado', 'Agroindustrial'),
        contactos: [], vigenteDesde: '2026-10-05T12:05:00Z',
    ));

    $resultado = replicar(CuerpoDeIngesta::socio(razonSocial: 'Agro Prueba Cambiada', vigenteDesde: '2026-10-05T12:01:00Z'), OperacionDeIngesta::Reemplazar);

    expect($resultado->value())->toBe(ResultadoDeIngesta::Aplicado)
        ->and(socioReplicado()?->razonSocial()->texto())->toBe('Agro Prueba Cambiada')
        ->and(app(GrupoRepository::class)->find(IdDeGrupo::desde('GRP-900'))?->nombre())->toBe('Grupo Prueba Renombrado');
});

it('un socio sin grupo se guarda sin grupo', function () {
    replicar(CuerpoDeIngesta::socio(grupo: null));

    expect(socioReplicado()?->grupo())->toBeNull();
});

it('los contactos que no vienen se dan de baja', function () {
    replicar(CuerpoDeIngesta::socio());

    replicar(CuerpoDeIngesta::socio(
        contactos: [new ContactoIngresado('1523', 'Mónica Salvatierra', '70741828', true)],
        vigenteDesde: '2026-10-05T13:00:00Z',
    ), OperacionDeIngesta::Reemplazar);

    expect(contactoReplicado('p-1524')?->dadoDeBajaEl())->toEqual($this->momento)
        ->and(contactoReplicado('p-1523')?->dadoDeBajaEl())->toBeNull();
});

it('la habilitacion se conserva al reemplazar', function () {
    replicar(CuerpoDeIngesta::socio());
    ContactoRecord::query()->where('id_de_persona', 'p-1523')->update(['habilitada_el' => '2026-10-01 09:00:00']);

    replicar(CuerpoDeIngesta::socio(vigenteDesde: '2026-10-05T13:00:00Z'), OperacionDeIngesta::Reemplazar);

    expect(contactoReplicado('p-1523')?->habilitadaEl()?->format('Y-m-d H:i:s'))->toBe('2026-10-01 09:00:00');
});

it('normaliza los celulares de SAP y guarda sin celular un fijo', function () {
    replicar(CuerpoDeIngesta::socio(contactos: [
        new ContactoIngresado('1523', 'Mónica Salvatierra', '+591 70741828', true),
        new ContactoIngresado('1524', 'Rodrigo Téllez', '591-7011-2233', true),
        new ContactoIngresado('1525', 'Oficina', '33456789', true),
        new ContactoIngresado('1526', 'Sin número', null, true),
    ]));

    expect(contactoReplicado('p-1523')?->celular()?->e164())->toBe('+59170741828')
        ->and(contactoReplicado('p-1524')?->celular()?->e164())->toBe('+59170112233')
        ->and(contactoReplicado('p-1525')?->celular())->toBeNull()
        ->and(contactoReplicado('p-1526')?->celular())->toBeNull()
        ->and(socioReplicado())->not->toBeNull();
});

it('un contacto que estaba en otro socio pasa a este', function () {
    replicar(CuerpoDeIngesta::socio(cardCode: 'C-900002', razonSocial: 'Primer socio'));

    replicar(CuerpoDeIngesta::socio(contactos: [new ContactoIngresado('1523', 'Mónica Salvatierra', '70741828', true)]));

    expect(contactoReplicado('p-1523')?->codigoDeSocio()->value())->toBe('C-900001');
});
