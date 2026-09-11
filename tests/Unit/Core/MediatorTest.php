<?php

declare(strict_types=1);

use Core\Contracts\PipelineBehavior;
use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Mediator\ContainerMediator;
use Core\Results\Error;
use Core\Results\Result;
use Psr\Container\ContainerInterface;

final class PeticionDePrueba implements Request
{
    public function __construct(public readonly string $dato) {}
}

final class HandlerDePrueba implements RequestHandler
{
    /** @var list<string> */
    public static array $huellas = [];

    public function handle(Request $peticion): Result
    {
        self::$huellas[] = 'handler';

        return Result::success();
    }
}

final class BehaviorQueRegistra implements PipelineBehavior
{
    public function handle(Request $peticion, Closure $siguiente): Result
    {
        HandlerDePrueba::$huellas[] = 'antes';
        $resultado = $siguiente($peticion);
        HandlerDePrueba::$huellas[] = 'despues';

        return $resultado;
    }
}

final class BehaviorQueCorta implements PipelineBehavior
{
    public function handle(Request $peticion, Closure $siguiente): Result
    {
        return Result::failure(Error::failure('ACCESO_DENEGADO', 'Fuera de alcance'));
    }
}

final class ContenedorDePrueba implements ContainerInterface
{
    public function get(string $id): object
    {
        return new $id;
    }

    public function has(string $id): bool
    {
        return class_exists($id);
    }
}

beforeEach(fn () => HandlerDePrueba::$huellas = []);

it('despacha la peticion a su handler', function () {
    $mediator = new ContainerMediator(
        new ContenedorDePrueba,
        [PeticionDePrueba::class => HandlerDePrueba::class],
        [],
    );

    expect($mediator->send(new PeticionDePrueba('x'))->isSuccess)->toBeTrue()
        ->and(HandlerDePrueba::$huellas)->toBe(['handler']);
});

it('envuelve el handler con los behaviors en el orden declarado', function () {
    $mediator = new ContainerMediator(
        new ContenedorDePrueba,
        [PeticionDePrueba::class => HandlerDePrueba::class],
        [BehaviorQueRegistra::class],
    );

    $mediator->send(new PeticionDePrueba('x'));

    expect(HandlerDePrueba::$huellas)->toBe(['antes', 'handler', 'despues']);
});

it('un behavior puede cortar antes de llegar al handler', function () {
    $mediator = new ContainerMediator(
        new ContenedorDePrueba,
        [PeticionDePrueba::class => HandlerDePrueba::class],
        [BehaviorQueCorta::class],
    );

    $resultado = $mediator->send(new PeticionDePrueba('x'));

    expect($resultado->isFailure())->toBeTrue()
        ->and($resultado->error->code)->toBe('ACCESO_DENEGADO')
        ->and(HandlerDePrueba::$huellas)->toBe([]);
});

it('falla ruidosamente si no hay handler registrado', function () {
    $mediator = new ContainerMediator(new ContenedorDePrueba, [], []);

    expect(fn () => $mediator->send(new PeticionDePrueba('x')))
        ->toThrow(LogicException::class);
});
