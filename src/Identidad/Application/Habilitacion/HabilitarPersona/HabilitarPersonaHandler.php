<?php

declare(strict_types=1);

namespace Identidad\Application\Habilitacion\HabilitarPersona;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Maestros\Domain\Contactos\ContactoErrors;

/**
 * Lo que se habilita es un número: todos los contactos de SAP con ese celular
 * son la misma persona. Habilitar es darle credencial; no hay otra marca.
 */
final readonly class HabilitarPersonaHandler implements RequestHandler
{
    public function __construct(
        private DirectorioDeContactos $contactos,
        private DirectorioDeIdentidades $directorio,
        private BovedaDeContrasenas $boveda,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof HabilitarPersona);

        $celular = $peticion->persona->celular();

        if ($celular === null) {
            return Result::failure(ContactoErrors::celularNoUtilizable());
        }

        // Darle credencial a un número que igual no podría entrar solo
        // confunde: el operador cree que habilitó y la persona recibe «código
        // inválido». Se le dice por qué, para que lo corrija en SAP.
        $motivo = $this->contactos->motivoDeNoIngreso($celular);

        if ($motivo !== null) {
            return Result::failure(ContactoErrors::numeroNoIngresa($motivo));
        }

        $contrasena = bin2hex(random_bytes(16));   // 32 caracteres, nunca la ve nadie

        // Primero el directorio: si falla, no queda una contraseña guardada
        // que no sirve para nada. Si el directorio anda y la bóveda falla,
        // `identidad:conciliar` lo detecta.
        $creado = $this->directorio->crearOActualizar($peticion->persona, $contrasena);

        if ($creado->isFailure()) {
            return $creado;
        }

        $this->boveda->guardar($peticion->persona, $contrasena);

        return Result::success();
    }
}
