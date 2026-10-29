<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as Http;
use Maestros\Application\Eventos\MensajeRecibido;
use Maestros\Infrastructure\ServiceBus\BuzonServiceBusRest;
use Maestros\Infrastructure\ServiceBus\PublicadorServiceBusRest;
use Ramsey\Uuid\Uuid;
use Tests\Soporte\SobresDePropiedad;

/*
 * Contra un namespace real, con el topic de pruebas pruebas-ms-comercial, su
 * suscripción sub-pruebas (sin sesiones, bloqueo de 60 s) y dos políticas SAS
 * en el topic: una de escucha y una de envío (13.5 de la guía de ms-comercial,
 * EM15). El emulador local de Service Bus solo habla AMQP, así que esto no se
 * puede probar en local. **Nunca** contra los topics reales: vacía la
 * suscripción antes de empezar, y se comería eventos sin aplicarlos.
 */

function faltaElNamespace(): bool
{
    return (string) env('SERVICEBUS_NAMESPACE') === ''
        || (string) env('SERVICEBUS_SAS_CLAVE') === ''
        || (string) env('SERVICEBUS_SAS_ENVIO_CLAVE') === '';
}

function buzonDeDesarrollo(): BuzonServiceBusRest
{
    return new BuzonServiceBusRest(
        new Http,
        (string) env('SERVICEBUS_NAMESPACE'),
        (string) env('SERVICEBUS_TOPIC', 'pruebas-ms-comercial'),
        (string) env('SERVICEBUS_SUSCRIPCION', 'sub-pruebas'),
        (string) env('SERVICEBUS_SAS_NOMBRE'),
        (string) env('SERVICEBUS_SAS_CLAVE'),
        5,
    );
}

function publicarUnSobreNuevo(): string
{
    $eventoId = Uuid::uuid4()->toString();

    (new PublicadorServiceBusRest(
        new Http,
        (string) env('SERVICEBUS_NAMESPACE'),
        (string) env('SERVICEBUS_TOPIC', 'pruebas-ms-comercial'),
        (string) env('SERVICEBUS_SAS_ENVIO_NOMBRE'),
        (string) env('SERVICEBUS_SAS_ENVIO_CLAVE'),
    ))->publicar(SobresDePropiedad::de(sobre: ['eventoId' => $eventoId]));

    return $eventoId;
}

function vaciarLaSuscripcion(BuzonServiceBusRest $buzon): void
{
    for ($i = 0; $i < 50; $i++) {
        $mensaje = $buzon->recibir();

        if (! $mensaje instanceof MensajeRecibido) {
            return;
        }

        $buzon->completar($mensaje);
    }
}

beforeEach(function () {
    if (faltaElNamespace()) {
        $this->markTestSkipped('sin namespace de Service Bus de desarrollo (SERVICEBUS_*)');
    }
});

it('publica, recibe y completa', function () {
    $buzon = buzonDeDesarrollo();
    vaciarLaSuscripcion($buzon);

    $eventoId = publicarUnSobreNuevo();
    $mensaje = $buzon->recibir();

    expect($mensaje)->toBeInstanceOf(MensajeRecibido::class)
        ->and(json_decode((string) $mensaje?->cuerpo, true)['eventoId'] ?? null)->toBe($eventoId);

    assert($mensaje instanceof MensajeRecibido);
    $buzon->completar($mensaje);

    expect($buzon->recibir())->toBeNull();
});

it('un mensaje sin completar vuelve cuando vence el bloqueo', function () {
    $buzon = buzonDeDesarrollo();
    vaciarLaSuscripcion($buzon);
    publicarUnSobreNuevo();

    $primera = $buzon->recibir();
    assert($primera instanceof MensajeRecibido);

    sleep(65);

    $segunda = $buzon->recibir();
    assert($segunda instanceof MensajeRecibido);

    expect($segunda->idDeMensaje)->toBe($primera->idDeMensaje)
        ->and($segunda->numeroDeEntrega)->toBe(2);

    $buzon->completar($segunda);
});
