<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Eventos;

use Illuminate\Console\Command;
use Maestros\Application\Eventos\Consumidor;
use Maestros\Application\Eventos\ResultadoDeVuelta;

/**
 * El proceso de la Container App `ms-maestros-consumidor`: la misma imagen
 * que sirve HTTP, arrancada con este comando (EM6). Copiado de ms-comercial.
 */
final class ConsumirCommand extends Command
{
    protected $signature = 'eventos:consumir {--una-vez : Hace una sola vuelta y sale}';

    protected $description = 'Publica el outbox en maestros-eventos y aplica las propiedades de sub-ms-maestros';

    private const int ESPERA_MAXIMA = 60;

    /** Lo que tiene que estar para arrancar, con la variable que lo carga. */
    private const array CONFIGURACION = [
        'servicebus.namespace' => 'SERVICEBUS_NAMESPACE',
        'servicebus.escucha.nombre' => 'SERVICEBUS_SAS_NOMBRE',
        'servicebus.escucha.clave' => 'SERVICEBUS_SAS_CLAVE',
        'servicebus.publicacion.nombre' => 'SERVICEBUS_PUBLICACION_SAS_NOMBRE',
        'servicebus.publicacion.clave' => 'SERVICEBUS_PUBLICACION_SAS_CLAVE',
    ];

    private bool $detener = false;

    public function handle(): int
    {
        // Antes de resolver el buzón: sin esto, una variable que falta se ve
        // como un 401 tras otro en el log, con reintentos cada vez más largos.
        $faltan = array_values(array_filter(
            self::CONFIGURACION,
            static function (string $variable, string $clave): bool {
                $valor = config($clave);

                return ! is_string($valor) || trim($valor) === '';
            },
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($faltan !== []) {
            $this->error('Falta configurar Service Bus: '.implode(', ', $faltan).'.');

            return self::FAILURE;
        }

        $consumidor = app(Consumidor::class);
        $this->atenderSenales();
        $espera = 1;

        do {
            $resultado = $consumidor->vuelta();

            // Con la base caída no se toma ningún mensaje, así que no se gasta
            // ninguno de sus cinco intentos; con el bus caído, insistir sin
            // pausa solo suma pedidos fallidos. En los dos se espera, cada vez
            // más.
            if ($resultado === ResultadoDeVuelta::BaseCaida || $resultado === ResultadoDeVuelta::BusNoResponde) {
                $this->warn(($resultado === ResultadoDeVuelta::BaseCaida ? 'La base' : 'El bus')." no responde; reintento en {$espera} s");

                if ($this->option('una-vez')) {
                    break;
                }

                sleep($espera);
                $espera = min($espera * 2, self::ESPERA_MAXIMA);

                continue;
            }

            // Sin mensajes no se duerme: recibir ya esperó en el bus.
            $espera = 1;
        } while (! $this->option('una-vez') && ! $this->detener);

        return self::SUCCESS;
    }

    /**
     * Container Apps manda SIGTERM al cambiar de revisión o al escalar hacia
     * abajo. La bandera se mira entre vueltas: el mensaje en curso termina, y
     * un mensaje a medio procesar no queda bloqueado hasta que venza. Windows
     * no tiene pcntl, y ahí no se atrapa nada.
     */
    private function atenderSenales(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGTERM, SIGINT] as $senal) {
            pcntl_signal($senal, function (): void {
                $this->detener = true;
            });
        }
    }
}
