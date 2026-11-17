<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Closure;
use Identidad\Application\Contracts\EnviadorDePush;
use Identidad\Application\Contracts\ResultadoDeEnvio;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Push\AvisoParaPush;

/**
 * Responde siempre lo mismo y recuerda a qué token mandó cada aviso. Lo que
 * se le pase en `$mientras` corre durante el envío, para lo que pasa en la
 * base mientras se espera a Firebase.
 */
final class EnviadorDePushFalso implements EnviadorDePush
{
    /** @var list<array{token: string, avisoId: string}> */
    public array $enviados = [];

    public function __construct(
        private readonly ResultadoDeEnvio $respuesta,
        private readonly ?Closure $mientras = null,
    ) {}

    public function enviar(TokenPush $token, AvisoParaPush $aviso): ResultadoDeEnvio
    {
        $this->enviados[] = ['token' => $token->valor(), 'avisoId' => $aviso->avisoId];

        if ($this->mientras !== null) {
            ($this->mientras)();
        }

        return $this->respuesta;
    }
}
