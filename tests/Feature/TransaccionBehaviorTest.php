<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Contracts\RequiereTransaccion;
use Core\Mediator\Behaviors\TransaccionBehavior;
use Core\Mediator\ContainerMediator;
use Core\Results\Error;
use Core\Results\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioRepository;

uses(RefreshDatabase::class);

final class EscrituraDoble implements Request, RequiereTransaccion
{
    public function __construct(public readonly bool $fallarAlFinal) {}
}

final class EscrituraDobleHandler implements RequestHandler
{
    public function __construct(private readonly SocioRepository $socios) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof EscrituraDoble);

        $this->socios->save(Socio::replica(
            CodigoDeSocio::desde('C-100'),
            RazonSocial::desde('Primero'),
            IdDeGrupo::desde('GRP-1'),
            new DateTimeImmutable('2026-09-14T12:00:00Z'),
        ));

        $this->socios->save(Socio::replica(
            CodigoDeSocio::desde('C-200'),
            RazonSocial::desde('Segundo'),
            IdDeGrupo::desde('GRP-1'),
            new DateTimeImmutable('2026-09-14T12:00:00Z'),
        ));

        return $peticion->fallarAlFinal
            ? Result::failure(Error::conflict('ALGO_SALIO_MAL', 'Se rompió al final'))
            : Result::success();
    }
}

final class EscrituraQueRevienta implements Request, RequiereTransaccion {}

final class EscrituraQueRevientaHandler implements RequestHandler
{
    public function __construct(private readonly SocioRepository $socios) {}

    public function handle(Request $peticion): Result
    {
        $this->socios->save(Socio::replica(
            CodigoDeSocio::desde('C-300'),
            RazonSocial::desde('Tercero'),
            IdDeGrupo::desde('GRP-1'),
            new DateTimeImmutable('2026-09-14T12:00:00Z'),
        ));

        throw new RuntimeException('boom');
    }
}

beforeEach(function () {
    $this->app->bind(Mediator::class, fn ($app) => new ContainerMediator(
        $app,
        [
            EscrituraDoble::class => EscrituraDobleHandler::class,
            EscrituraQueRevienta::class => EscrituraQueRevientaHandler::class,
        ],
        [TransaccionBehavior::class],
    ));
});

it('confirma las dos escrituras cuando el handler tiene exito', function () {
    app(Mediator::class)->send(new EscrituraDoble(fallarAlFinal: false));

    expect(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-100')))->not->toBeNull()
        ->and(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-200')))->not->toBeNull();
});

it('deshace las escrituras cuando el handler lanza', function () {
    expect(fn () => app(Mediator::class)->send(new EscrituraQueRevienta))
        ->toThrow(RuntimeException::class);

    expect(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-300')))->toBeNull();
});

it('confirma lo escrito aunque el handler devuelva un Result fallido', function () {
    // Un `Result` fallido es una salida deliberada del caso de uso, no un
    // accidente: lo que escribió antes de decidirlo es parte de esa decisión.
    // El caso concreto que esto protege es el contador de intentos fallidos
    // del desafío de ingreso, que se persiste justo antes de devolver
    // CODIGO_INVALIDO. Si el fallo deshiciera, el límite de 5 intentos no
    // existiría y un código de 4 dígitos se podría probar sin tope.
    $resultado = app(Mediator::class)->send(new EscrituraDoble(fallarAlFinal: true));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('ALGO_SALIO_MAL')
        ->and(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-100')))->not->toBeNull()
        ->and(app(SocioRepository::class)->find(CodigoDeSocio::desde('C-200')))->not->toBeNull();
});

it('no abre transaccion para una peticion que no la pide', function () {
    // El nivel se mide contra el de afuera y no contra cero: RefreshDatabase
    // ya tiene su propia transacción abierta durante toda la prueba.
    $afuera = DB::transactionLevel();
    $adentro = null;

    app(TransaccionBehavior::class)->handle(
        new class implements Request {},
        function () use (&$adentro): Result {
            $adentro = DB::transactionLevel();

            return Result::success();
        },
    );

    expect($adentro)->toBe($afuera);
});

it('abre transaccion para una peticion que la pide', function () {
    $afuera = DB::transactionLevel();
    $adentro = null;

    app(TransaccionBehavior::class)->handle(
        new EscrituraQueRevienta,
        function () use (&$adentro): Result {
            $adentro = DB::transactionLevel();

            return Result::success();
        },
    );

    expect($adentro)->toBe($afuera + 1);
});
