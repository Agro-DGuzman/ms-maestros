<?php

declare(strict_types=1);

namespace Identidad\Presentation\Http;

use App\Http\Envelope;
use App\Http\PersonaAutenticada;
use Core\Contracts\Mediator;
use Identidad\Application\Push\CambiarTokenPush\CambiarTokenPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** El token push de una instalación, cambiado sin cerrar sesión (NP18). */
final readonly class DispositivosController
{
    public function __construct(private Mediator $mediator) {}

    public function cambiarToken(Request $peticion, string $instalacionId): JsonResponse
    {
        // La instalación viene en la ruta, así que se valida junto al cuerpo:
        // un id mal escrito es un error del campo, no una sesión que no está.
        $datos = Validator::make(
            ['instalacionId' => $instalacionId, 'tokenPush' => $peticion->input('tokenPush')],
            [
                'instalacionId' => ['uuid'],
                'tokenPush' => ['required', 'string', 'max:4000', 'regex:/^\S+$/'],
            ],
        )->validate();

        $resultado = $this->mediator->send(new CambiarTokenPush(
            PersonaAutenticada::deLaPeticion($peticion),
            (string) $datos['instalacionId'],
            (string) $datos['tokenPush'],
        ));

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        // El contrato fija `true`: si se guardó, la sesión quedó con token.
        return Envelope::responder($resultado, ['dispositivoRegistrado' => true]);
    }
}
