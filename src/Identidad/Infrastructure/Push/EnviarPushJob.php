<?php

declare(strict_types=1);

namespace Identidad\Infrastructure\Push;

use Identidad\Application\Contracts\EnviadorDePush;
use Identidad\Application\Contracts\EstadoDeEnvio;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Push\AvisoParaPush;
use Identidad\Domain\Push\PreferenciasDePushRepository;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Manda un aviso a un teléfono. Lleva el id de la sesión y no el token: así
 * el token no queda en la tabla de trabajos fallidos, y si la App lo cambió
 * mientras esperaba se usa el del momento (NP22).
 *
 * Va en su propia cola, `push`, para que cien avisos de catálogo no demoren
 * el código de alguien que espera para entrar (NP23).
 */
final class EnviarPushJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /** Con la espera creciente suman unos 30 minutos: lejos de las 24 horas de NP13. */
    public int $tries = 6;

    private const int ESPERA_INICIAL = 60;

    private const int ESPERA_MAXIMA = 3600;

    /** @param array<string, string|null> $aviso lo que dejó AvisoParaPush::aArreglo() */
    public function __construct(
        public readonly string $idDeSesion,
        public readonly array $aviso,
    ) {
        $this->onQueue('push');
    }

    public function handle(
        SesionRepository $sesiones,
        PreferenciasDePushRepository $preferencias,
        EnviadorDePush $enviador,
        RelojDelSistema $reloj,
    ): void {
        $aviso = AvisoParaPush::desdeArreglo($this->aviso);
        $ahora = $reloj->ahora();
        $sesion = $sesiones->find(IdDeSesion::desde($this->idDeSesion));

        // Todo se vuelve a mirar al mandar: mientras esperaba, la sesión pudo
        // cerrarse o perder el teléfono, la persona pudo apagar la categoría,
        // y el aviso pudo volverse viejo (NP13, NP22).
        if (! $sesion instanceof SesionDeAplicacion || ! $sesion->estaAbierta($ahora) || $aviso->esViejo($ahora)) {
            return;
        }

        $token = $sesion->tokenPush();

        if ($token === null || ! $preferencias->de($sesion->persona())->permite($aviso->categoria)) {
            return;
        }

        $resultado = $enviador->enviar($token, $aviso);

        if ($resultado->estado === EstadoDeEnvio::TokenMuerto) {
            $sesion->quitarTokenPush();
            $sesiones->save($sesion);
        }

        if ($resultado->estado === EstadoDeEnvio::Reintentar) {
            $this->release($resultado->esperaSegundos ?? self::espera($this->attempts()));
        }
    }

    /** Un minuto el primer reintento, el doble cada vez, hasta una hora. */
    public static function espera(int $intento): int
    {
        return min(self::ESPERA_INICIAL * 2 ** max(0, $intento - 1), self::ESPERA_MAXIMA);
    }
}
