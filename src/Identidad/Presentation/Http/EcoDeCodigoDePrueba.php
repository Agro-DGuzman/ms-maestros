<?php

declare(strict_types=1);

namespace Identidad\Presentation\Http;

use Identidad\Domain\Desafios\DesafioDeIngreso;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Desafios\IdDeDesafio;
use Maestros\Domain\Contactos\Celular;
use RuntimeException;

/**
 * Devuelve el código del desafío en la respuesta, para probar sin leer logs.
 *
 * TEMPORAL. Devolverle el código a cualquiera es saltearse el OTP: quien sepa
 * un número entra como esa persona. Por eso responde solo a los números de una
 * lista explícita —vacía es apagado, y es el valor por defecto— y con
 * `APP_ENV=production` se niega a existir.
 *
 * En `staging` ese último resguardo no aplica, así que lo que protege de verdad
 * es la lista: aunque alguien olvide la variable puesta, un número real nunca
 * recibe su código.
 *
 * Vive en la presentación y no en el caso de uso a propósito: retirarlo es
 * borrar esta clase, su binding y dos líneas del controlador, sin tocar la
 * lógica del desafío.
 */
final readonly class EcoDeCodigoDePrueba
{
    /** @param list<string> $numeros en E.164 */
    public function __construct(
        private DesafioRepository $desafios,
        private array $numeros,
        string $entorno,
    ) {
        if ($numeros !== [] && $entorno === 'production') {
            throw new RuntimeException('OTP_ECO_EN_PRODUCCION');
        }
    }

    public function para(Celular $celular, IdDeDesafio $id): ?string
    {
        if (! in_array($celular->e164(), $this->numeros, true)) {
            return null;
        }

        $desafio = $this->desafios->find($id);

        return $desafio instanceof DesafioDeIngreso ? $desafio->digitos() : null;
    }
}
