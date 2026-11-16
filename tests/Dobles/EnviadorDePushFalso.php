<?php

declare(strict_types=1);

namespace Tests\Dobles;

use Identidad\Application\Contracts\EnviadorDePush;
use Identidad\Application\Contracts\ResultadoDeEnvio;
use Identidad\Domain\Dispositivos\TokenPush;
use Identidad\Domain\Push\AvisoParaPush;

/** Responde siempre lo mismo y recuerda a qué token mandó cada aviso. */
final class EnviadorDePushFalso implements EnviadorDePush
{
    /** @var list<array{token: string, avisoId: string}> */
    public array $enviados = [];

    public function __construct(private readonly ResultadoDeEnvio $respuesta) {}

    public function enviar(TokenPush $token, AvisoParaPush $aviso): ResultadoDeEnvio
    {
        $this->enviados[] = ['token' => $token->valor(), 'avisoId' => $aviso->avisoId];

        return $this->respuesta;
    }
}
