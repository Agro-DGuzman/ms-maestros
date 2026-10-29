<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

use Core\Contracts\Mediator;
use Maestros\Application\Ingesta\ProcesarEvento\ProcesarEvento;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Una vuelta del ciclo: publicar el outbox, recibir, procesar, completar. El
 * ciclo en sí —dormir, atender SIGTERM— vive en el comando; esto es lo que se
 * puede probar sin bus.
 *
 * Nada que no se entienda se completa: el mensaje se deja vencer, vuelve, y
 * a la quinta entrega Service Bus lo manda a la cola de mensajes muertos,
 * donde alguien lo puede mirar.
 */
final readonly class Consumidor
{
    /** Las que se publican por vuelta, como máximo (EM6). */
    private const int TANDA = 50;

    /** Con una tanda llena quedan más: el recibir espera poco para volver pronto. */
    private const int ESPERA_CON_COLA = 1;

    public function __construct(
        private BuzonDeEntrada $buzon,
        private SaludDeLaBase $base,
        private Mediator $mediator,
        private LoggerInterface $log,
        private SalidaDeEventos $salida,
        private PublicadorDeEventos $publicador,
    ) {}

    public function vuelta(): ResultadoDeVuelta
    {
        if (! $this->base->responde()) {
            return ResultadoDeVuelta::BaseCaida;
        }

        $quedanMas = $this->publicarPendientes();

        // Un 503, un 401 o un corte de red no pueden tirar el proceso: el
        // consumidor es de larga vida, y que Container Apps lo reinicie una y
        // otra vez solo hace crecer la espera mientras los mensajes envejecen.
        try {
            $mensaje = $this->buzon->recibir($quedanMas ? self::ESPERA_CON_COLA : null);
        } catch (Throwable $e) {
            $this->log->warning('El bus no respondió al recibir', ['error' => $e->getMessage()]);

            return ResultadoDeVuelta::BusNoResponde;
        }

        if (! $mensaje instanceof MensajeRecibido) {
            return ResultadoDeVuelta::SinMensajes;
        }

        $sobre = null;

        try {
            $sobre = Sobre::desdeJson($mensaje->cuerpo);
            $resultado = $this->mediator->send(new ProcesarEvento($sobre));

            if ($resultado->isFailure()) {
                $this->fallo($mensaje, $sobre, "{$resultado->error->code}: {$resultado->error->description}");

                return ResultadoDeVuelta::Fallido;
            }
        } catch (Throwable $e) {
            $this->fallo($mensaje, $sobre, $e->getMessage());

            return ResultadoDeVuelta::Fallido;
        }

        // El evento ya quedó aplicado y registrado. Si no se puede completar
        // (el bloqueo venció, el bus no responde), el mensaje vuelve a
        // entregarse y sale como duplicado: no se pierde nada.
        try {
            $this->buzon->completar($mensaje);
        } catch (Throwable $e) {
            $this->log->warning('El bus no respondió al completar el mensaje', [
                'idDeMensaje' => $mensaje->idDeMensaje,
                'eventoId' => $sobre->eventoId,
                'error' => $e->getMessage(),
            ]);

            return ResultadoDeVuelta::BusNoResponde;
        }

        return ResultadoDeVuelta::Completado;
    }

    /**
     * Publica el outbox por orden de llegada y marca cada una. En el primer
     * fallo corta solo la publicación: la fila suma un intento y queda
     * primera, y la vuelta sigue y recibe, porque una clave Send mal cargada
     * no puede frenar la llegada de las propiedades (EM6).
     *
     * @return bool si la tanda salió llena, y quedan más
     */
    private function publicarPendientes(): bool
    {
        // La base respondió al empezar la vuelta, pero una consulta puede
        // fallar igual; el outbox se retoma en la vuelta siguiente.
        try {
            $pendientes = $this->salida->pendientes(self::TANDA);

            foreach ($pendientes as $evento) {
                if (! $this->publicar($evento)) {
                    return false;
                }
            }
        } catch (Throwable $e) {
            $this->log->warning('No se pudo leer o marcar el outbox', ['error' => $e->getMessage()]);

            return false;
        }

        return count($pendientes) === self::TANDA;
    }

    private function publicar(EventoSaliente $evento): bool
    {
        try {
            $this->publicador->publicar($evento->sobre);
        } catch (Throwable $e) {
            $this->salida->sumarIntento($evento->eventoId);
            $this->log->warning('El bus no respondió al publicar un evento', [
                'eventoId' => $evento->eventoId,
                'tipo' => $evento->tipo,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        // Si esto falla con el evento ya publicado, se vuelve a publicar en la
        // vuelta siguiente, y ms-comercial lo descarta por eventoId.
        $this->salida->marcarPublicado($evento->eventoId);

        return true;
    }

    private function fallo(MensajeRecibido $mensaje, ?Sobre $sobre, string $error): void
    {
        $this->log->error('No se pudo procesar el mensaje', array_filter([
            'idDeMensaje' => $mensaje->idDeMensaje,
            'numeroDeEntrega' => $mensaje->numeroDeEntrega,
            'eventoId' => $sobre?->eventoId,
            'tipo' => $sobre?->tipo,
            'error' => $error,
        ], static fn (mixed $valor): bool => $valor !== null));
    }
}
