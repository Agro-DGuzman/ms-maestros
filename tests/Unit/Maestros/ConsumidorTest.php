<?php

declare(strict_types=1);

use Core\Contracts\Mediator;
use Core\Contracts\Request;
use Core\Results\Error;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Maestros\Application\Eventos\BuzonDeEntrada;
use Maestros\Application\Eventos\Consumidor;
use Maestros\Application\Eventos\EventoSaliente;
use Maestros\Application\Eventos\MensajeRecibido;
use Maestros\Application\Eventos\ResultadoDeVuelta;
use Maestros\Application\Eventos\SalidaDeEventos;
use Maestros\Application\Ingesta\ProcesarEvento\ProcesarEvento;
use Maestros\Application\Ingesta\ProcesarEvento\ResultadoDelEvento;
use Tests\Dobles\BuzonEnMemoria;
use Tests\Dobles\LoggerEspia;
use Tests\Dobles\MediatorEspia;
use Tests\Dobles\PublicadorEnMemoria;
use Tests\Dobles\SalidaDeEventosEnMemoria;
use Tests\Dobles\SaludFija;
use Tests\Soporte\SobresDePropiedad;

function consumidorCon(
    BuzonDeEntrada $buzon,
    Mediator $mediator,
    bool $baseResponde = true,
    ?LoggerEspia $log = null,
    ?SalidaDeEventos $salida = null,
    ?PublicadorEnMemoria $publicador = null,
): Consumidor {
    return new Consumidor(
        $buzon,
        new SaludFija($baseResponde),
        $mediator,
        $log ?? new LoggerEspia,
        $salida ?? new SalidaDeEventosEnMemoria,
        $publicador ?? new PublicadorEnMemoria,
    );
}

/** Un socio en el outbox, el n-ésimo en llegar. */
function eventoDeSocioPendiente(int $n): EventoSaliente
{
    $eventoId = sprintf('00000000-0000-4000-8000-%012d', $n);

    return new EventoSaliente(
        $eventoId,
        'maestros.socio.actualizado',
        ['eventoId' => $eventoId, 'tipo' => 'maestros.socio.actualizado', 'datos' => ['cardCode' => sprintf('C-%06d', $n)]],
        (new DateTimeImmutable('2026-10-28T12:00:00.000000Z'))->modify("+{$n} seconds"),
    );
}

/**
 * @param  list<array<array-key, mixed>>  $sobres
 * @return list<mixed>
 */
function eventoIdsDe(array $sobres): array
{
    return array_map(static fn (array $s): mixed => $s['eventoId'] ?? null, $sobres);
}

function mediatorQueAplica(): MediatorEspia
{
    return new MediatorEspia(ResultWithValue::of(ResultadoDelEvento::Aplicado));
}

it('con la base caida no recibe nada', function () {
    $buzon = BuzonEnMemoria::conSobres(SobresDePropiedad::de());

    expect(consumidorCon($buzon, mediatorQueAplica(), baseResponde: false)->vuelta())->toBe(ResultadoDeVuelta::BaseCaida)
        ->and($buzon->recibidos)->toBe(0);
});

it('sin mensajes no hace nada', function () {
    expect(consumidorCon(BuzonEnMemoria::conSobres(), mediatorQueAplica())->vuelta())->toBe(ResultadoDeVuelta::SinMensajes);
});

it('procesa el sobre y completa el mensaje', function () {
    $buzon = BuzonEnMemoria::conSobres(SobresDePropiedad::de());
    $mediator = mediatorQueAplica();

    expect(consumidorCon($buzon, $mediator)->vuelta())->toBe(ResultadoDeVuelta::Completado)
        ->and($buzon->completados)->toHaveCount(1)
        ->and($mediator->ultimo())->toBeInstanceOf(ProcesarEvento::class);

    $despachado = $mediator->ultimo();
    assert($despachado instanceof ProcesarEvento);
    expect($despachado->sobre->eventoId)->toBe('a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d');
});

it('un duplicado tambien se completa', function () {
    $buzon = BuzonEnMemoria::conSobres(SobresDePropiedad::de());

    consumidorCon($buzon, new MediatorEspia(ResultWithValue::of(ResultadoDelEvento::Duplicado)))->vuelta();

    expect($buzon->completados)->toHaveCount(1);
});

it('un cuerpo que no es un sobre no se completa y queda en el log', function () {
    $buzon = BuzonEnMemoria::conCuerpos('{');
    $log = new LoggerEspia;

    expect(consumidorCon($buzon, mediatorQueAplica(), log: $log)->vuelta())->toBe(ResultadoDeVuelta::Fallido)
        ->and($buzon->completados)->toBe([])
        ->and($log->registros[0]['nivel'])->toBe('error')
        ->and($log->registros[0]['contexto'])->toMatchArray(['idDeMensaje' => 'm-1', 'numeroDeEntrega' => 1]);
});

it('si el procesamiento lanza no se completa, y el log dice que evento era', function () {
    $buzon = BuzonEnMemoria::conSobres(SobresDePropiedad::de());
    $log = new LoggerEspia;
    $queLanza = new class implements Mediator
    {
        public function send(Request $peticion): Result
        {
            throw new RuntimeException('se cayo la base a mitad de camino');
        }
    };

    expect(consumidorCon($buzon, $queLanza, log: $log)->vuelta())->toBe(ResultadoDeVuelta::Fallido)
        ->and($buzon->completados)->toBe([])
        ->and($log->registros[0]['contexto'])->toMatchArray([
            'eventoId' => 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d',
            'tipo' => 'comercial.propiedad.actualizada',
        ]);
});

it('un Result fallido tampoco se completa', function () {
    $buzon = BuzonEnMemoria::conSobres(SobresDePropiedad::de());
    $mediator = new MediatorEspia(Result::failure(Error::failure('ALGO', 'no')));

    expect(consumidorCon($buzon, $mediator)->vuelta())->toBe(ResultadoDeVuelta::Fallido)
        ->and($buzon->completados)->toBe([]);
});

/** Un buzón que falla como falla el bus: un 503, un corte de red, un bloqueo perdido. */
function buzonQueFalla(bool $alRecibir, bool $alCompletar): BuzonDeEntrada
{
    return new class($alRecibir, $alCompletar) implements BuzonDeEntrada
    {
        public function __construct(private readonly bool $alRecibir, private readonly bool $alCompletar) {}

        public function recibir(?int $esperaSegundos = null): ?MensajeRecibido
        {
            if ($this->alRecibir) {
                throw new RuntimeException('Service Bus respondió 503 al recibir');
            }

            return new MensajeRecibido((string) json_encode(SobresDePropiedad::de()), 'm-1', 1, 'bloqueo-1');
        }

        public function completar(MensajeRecibido $mensaje): void
        {
            if ($this->alCompletar) {
                throw new RuntimeException('Service Bus respondió 410 al completar m-1');
            }
        }
    };
}

it('si el bus falla al recibir, la vuelta no se cae', function () {
    $log = new LoggerEspia;
    $consumidor = consumidorCon(buzonQueFalla(alRecibir: true, alCompletar: false), mediatorQueAplica(), log: $log);

    expect($consumidor->vuelta())->toBe(ResultadoDeVuelta::BusNoResponde)
        ->and($log->registros[0]['nivel'])->toBe('warning');
});

it('si el bus falla al completar, la vuelta no se cae y el evento ya quedo aplicado', function () {
    // El mensaje vuelve a entregarse y sale como duplicado: no se pierde nada.
    $log = new LoggerEspia;
    $mediator = mediatorQueAplica();
    $consumidor = consumidorCon(buzonQueFalla(alRecibir: false, alCompletar: true), $mediator, log: $log);

    expect($consumidor->vuelta())->toBe(ResultadoDeVuelta::BusNoResponde)
        ->and($mediator->despachados)->toHaveCount(1)
        ->and($log->registros[0]['contexto'])->toMatchArray(['idDeMensaje' => 'm-1']);
});

it('publica las pendientes en orden antes de recibir y las marca', function () {
    $salida = SalidaDeEventosEnMemoria::con(eventoDeSocioPendiente(3), eventoDeSocioPendiente(1), eventoDeSocioPendiente(2));
    $publicador = new PublicadorEnMemoria;

    consumidorCon(BuzonEnMemoria::conSobres(), mediatorQueAplica(), salida: $salida, publicador: $publicador)->vuelta();

    expect(eventoIdsDe($publicador->publicados))->toBe([
        eventoDeSocioPendiente(1)->eventoId,
        eventoDeSocioPendiente(2)->eventoId,
        eventoDeSocioPendiente(3)->eventoId,
    ])
        ->and(array_keys($salida->publicados))->toEqualCanonicalizing(eventoIdsDe($publicador->publicados))
        ->and($salida->pendientes(50))->toBe([]);
});

it('no publica mas de 50 por vuelta y con la tanda llena recibe esperando 1 segundo', function () {
    $salida = SalidaDeEventosEnMemoria::con(...array_map(eventoDeSocioPendiente(...), range(1, 51)));
    $publicador = new PublicadorEnMemoria;
    $buzon = BuzonEnMemoria::conSobres();

    consumidorCon($buzon, mediatorQueAplica(), salida: $salida, publicador: $publicador)->vuelta();

    expect($publicador->publicados)->toHaveCount(50)
        ->and($salida->pendientes(50))->toHaveCount(1)
        ->and($salida->pendientes(50)[0]->eventoId)->toBe(eventoDeSocioPendiente(51)->eventoId)
        ->and($buzon->esperas)->toBe([1]);
});

it('con una tanda corta recibe con la espera configurada', function () {
    $salida = SalidaDeEventosEnMemoria::con(...array_map(eventoDeSocioPendiente(...), range(1, 3)));
    $buzon = BuzonEnMemoria::conSobres();

    consumidorCon($buzon, mediatorQueAplica(), salida: $salida)->vuelta();

    expect($buzon->esperas)->toBe([null]);
});

it('si la publicacion falla corta la tanda pero igual recibe y aplica el mensaje', function () {
    // Una clave Send mal cargada no puede frenar la llegada de los socios (ED8).
    $salida = SalidaDeEventosEnMemoria::con(...array_map(eventoDeSocioPendiente(...), range(1, 3)));
    $buzon = BuzonEnMemoria::conSobres(SobresDePropiedad::de());
    $mediator = mediatorQueAplica();
    $log = new LoggerEspia;

    $resultado = consumidorCon($buzon, $mediator, log: $log, salida: $salida, publicador: PublicadorEnMemoria::queFalla())->vuelta();

    expect($resultado)->toBe(ResultadoDeVuelta::Completado)
        ->and($salida->publicados)->toBe([])
        ->and($salida->intentos)->toBe([eventoDeSocioPendiente(1)->eventoId => 1])
        ->and($salida->pendientes(50))->toHaveCount(3)
        ->and($mediator->despachados)->toHaveCount(1)
        ->and($buzon->completados)->toHaveCount(1)
        ->and($buzon->esperas)->toBe([null])
        ->and($log->registros[0]['nivel'])->toBe('warning')
        ->and($log->registros[0]['contexto'])->toMatchArray([
            'eventoId' => eventoDeSocioPendiente(1)->eventoId,
            'error' => 'Service Bus respondió 401 al publicar',
        ]);
});

it('con la base caida no publica ni recibe', function () {
    $salida = SalidaDeEventosEnMemoria::con(eventoDeSocioPendiente(1));
    $publicador = new PublicadorEnMemoria;
    $buzon = BuzonEnMemoria::conSobres(SobresDePropiedad::de());

    expect(consumidorCon($buzon, mediatorQueAplica(), baseResponde: false, salida: $salida, publicador: $publicador)->vuelta())
        ->toBe(ResultadoDeVuelta::BaseCaida)
        ->and($publicador->publicados)->toBe([])
        ->and($salida->intentos)->toBe([])
        ->and($buzon->recibidos)->toBe(0);
});

it('si el outbox no se puede leer la vuelta no se cae y recibe igual', function () {
    // La base respondió al empezar la vuelta, pero una consulta puede fallar
    // igual: el consumidor es de larga vida y no puede caerse por eso.
    $queFalla = new class implements SalidaDeEventos
    {
        public function registrar(EventoSaliente $evento): void {}

        public function pendientes(int $limite): array
        {
            throw new RuntimeException('se cortó la conexión a la base');
        }

        public function marcarPublicado(string $eventoId): void {}

        public function sumarIntento(string $eventoId): void {}
    };
    $buzon = BuzonEnMemoria::conSobres(SobresDePropiedad::de());
    $log = new LoggerEspia;

    expect(consumidorCon($buzon, mediatorQueAplica(), log: $log, salida: $queFalla)->vuelta())->toBe(ResultadoDeVuelta::Completado)
        ->and($buzon->completados)->toHaveCount(1)
        ->and($buzon->esperas)->toBe([null])
        ->and($log->registros[0]['nivel'])->toBe('warning')
        ->and($log->registros[0]['contexto'])->toMatchArray(['error' => 'se cortó la conexión a la base']);
});
