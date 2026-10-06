<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Envelope;
use App\Persistence\RespuestasDeIngesta;
use Closure;
use Core\Results\FieldError;
use Core\Results\ValidationError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un envío repetido devuelve lo mismo que la primera vez sin volver a
 * aplicarse. La identidad es la terna clave, método y ruta: el Sincronizador
 * reintenta un POST que dio 409 como PUT con la misma clave.
 *
 * No hace falta que sea atómica con la escritura: reaplicar el mismo cuerpo es
 * inofensivo por la regla de vigencia. Las 5xx no se guardan, porque son
 * transitorias y tienen que poder reintentarse.
 */
final readonly class Idempotencia
{
    public function __construct(private RespuestasDeIngesta $respuestas) {}

    public function handle(Request $pedido, Closure $siguiente): Response
    {
        $clave = $pedido->header('Idempotency-Key');

        if (! is_string($clave) || preg_match('/^[0-9a-f]{32}$/', $clave) !== 1) {
            return new JsonResponse(Envelope::fallo(new ValidationError(
                $clave === null
                    ? new FieldError('Idempotency-Key', 'CAMPO_REQUERIDO', 'Requerido.')
                    : new FieldError('Idempotency-Key', 'PARAMETRO_INVALIDO', 'Tienen que ser 32 caracteres hexadecimales en minúscula.'),
            )), 400);
        }

        $metodo = $pedido->getMethod();
        $ruta = $pedido->getPathInfo();
        $previa = $this->respuestas->buscar($clave, $metodo, $ruta);

        if ($previa !== null) {
            return new Response($previa['cuerpo'] ?? '', $previa['status'], $previa['cuerpo'] === null ? [] : ['Content-Type' => 'application/json']);
        }

        $respuesta = $siguiente($pedido);
        assert($respuesta instanceof Response);

        if ($respuesta->getStatusCode() < 500) {
            $cuerpo = $respuesta->getContent();
            $this->respuestas->guardar($clave, $metodo, $ruta, $respuesta->getStatusCode(), $cuerpo === false || $cuerpo === '' ? null : $cuerpo);
        }

        return $respuesta;
    }
}
