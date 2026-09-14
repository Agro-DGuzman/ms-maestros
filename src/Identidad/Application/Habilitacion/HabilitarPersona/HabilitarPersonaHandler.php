<?php

declare(strict_types=1);

namespace Identidad\Application\Habilitacion\HabilitarPersona;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Maestros\Domain\Contactos\ContactoErrors;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\PersonaDeContacto;

final readonly class HabilitarPersonaHandler implements RequestHandler
{
    public function __construct(
        private ContactoRepository $contactos,
        private DirectorioDeIdentidades $directorio,
        private BovedaDeContrasenas $boveda,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof HabilitarPersona);

        $persona = $this->contactos->find($peticion->persona);

        if (! $persona instanceof PersonaDeContacto) {
            return Result::failure(
                ContactoErrors::noEncontrado($peticion->persona->value()),
            );
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
