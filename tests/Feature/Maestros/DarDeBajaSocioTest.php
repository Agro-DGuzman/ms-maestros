<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Results\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Application\Ingesta\DarDeBajaSocio\DarDeBajaSocio;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\ReplicarSocio\ReplicarSocio;
use Maestros\Application\Ingesta\ResultadoDeIngesta;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\SocioRepository;
use Tests\Soporte\CuerpoDeIngesta;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->baja = new DateTimeImmutable('2026-10-05T15:00:00Z');

    app(Mediator::class)->send(new ReplicarSocio(
        OperacionDeIngesta::Crear, null, CuerpoDeIngesta::socio(), new DateTimeImmutable('2026-10-05T12:30:00Z'),
    ));
});

function darDeBaja(string $cardCode, DateTimeImmutable $momento): Result
{
    return app(Mediator::class)->send(new DarDeBajaSocio(CodigoDeSocio::desde($cardCode), $momento));
}

it('da de baja al socio y a sus contactos, con la vigencia en el momento de la baja', function () {
    expect(darDeBaja('C-900001', $this->baja)->isSuccess)->toBeTrue();

    $socio = app(SocioRepository::class)->find(CodigoDeSocio::desde('C-900001'));

    expect($socio?->dadoDeBajaEl())->toEqual($this->baja)
        ->and($socio?->vigenteDesde())->toEqual($this->baja)
        ->and(app(ContactoRepository::class)->find(IdDePersona::desde('p-1523'))?->dadoDeBajaEl())->toEqual($this->baja);
});

it('un socio inexistente o ya dado de baja responde SOCIO_NO_ENCONTRADO', function () {
    expect(darDeBaja('C-999999', $this->baja)->error->code)->toBe('SOCIO_NO_ENCONTRADO');

    darDeBaja('C-900001', $this->baja);

    expect(darDeBaja('C-900001', $this->baja)->error->code)->toBe('SOCIO_NO_ENCONTRADO');
});

it('un envio leido antes de la baja no lo resucita, uno leido despues si', function () {
    darDeBaja('C-900001', $this->baja);

    $viejo = app(Mediator::class)->send(new ReplicarSocio(
        OperacionDeIngesta::Crear, null, CuerpoDeIngesta::socio(vigenteDesde: '2026-10-05T14:59:00Z'), new DateTimeImmutable('2026-10-05T15:01:00Z'),
    ));

    expect($viejo->value())->toBe(ResultadoDeIngesta::IgnoradoPorViejo)
        ->and(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-900001'))?->dadoDeBajaEl())->not->toBeNull();

    $nuevo = app(Mediator::class)->send(new ReplicarSocio(
        OperacionDeIngesta::Crear, null, CuerpoDeIngesta::socio(vigenteDesde: '2026-10-05T15:30:00Z'), new DateTimeImmutable('2026-10-05T15:31:00Z'),
    ));

    expect($nuevo->value())->toBe(ResultadoDeIngesta::Aplicado)
        ->and(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-900001'))?->dadoDeBajaEl())->toBeNull();
});
