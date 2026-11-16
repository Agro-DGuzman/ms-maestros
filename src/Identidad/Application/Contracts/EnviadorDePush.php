<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Push\AvisoParaPush;

/**
 * Manda un aviso a un teléfono. No lanza por lo que responda el proveedor:
 * lo dice en el resultado, y el motivo de un rechazo va al log sin el token
 * (NP16).
 */
interface EnviadorDePush
{
    public function enviar(TokenPush $token, AvisoParaPush $aviso): ResultadoDeEnvio;
}
