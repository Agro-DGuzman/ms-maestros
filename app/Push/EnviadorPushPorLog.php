<?php

declare(strict_types=1);

namespace App\Push;

use Identidad\Application\Contracts\EnviadorDePush;
use Identidad\Application\Contracts\ResultadoDeEnvio;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Push\AvisoParaPush;
use Psr\Log\LoggerInterface;

/**
 * PUSH_DRIVER=log (NP15): escribe lo que se mandaría, sin el token. Así se
 * prueba todo el camino —el bus, los destinatarios, la cola— sin Firebase y
 * sin que suene un teléfono.
 */
final readonly class EnviadorPushPorLog implements EnviadorDePush
{
    public function __construct(private LoggerInterface $log) {}

    public function enviar(TokenPush $token, AvisoParaPush $aviso): ResultadoDeEnvio
    {
        $this->log->info('Push que se mandaría', [
            'avisoId' => $aviso->avisoId,
            'categoria' => $aviso->categoria->value,
            'cardCode' => $aviso->cardCode,
            'titulo' => $aviso->titulo,
            'cuerpo' => $aviso->cuerpo,
        ]);

        return ResultadoDeEnvio::entregado();
    }
}
