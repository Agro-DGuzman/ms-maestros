<?php

declare(strict_types=1);

namespace Identidad\Application\Habilitacion\DeshabilitarPersona;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeIdentidades;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Domain\Sesiones\SesionRepository;

/**
 * Quitar el acceso son dos cosas y las dos tienen que pasar: bloquear al
 * usuario en el directorio, y cerrar las sesiones que ya estaban abiertas.
 * Solo lo primero deja a alguien operando con un token todavía válido.
 *
 * El directorio va primero: si el bloqueo falla, cerrar las sesiones daría
 * una falsa sensación de haber quitado el acceso cuando la persona todavía
 * puede pedir un token nuevo.
 */
final readonly class DeshabilitarPersonaHandler implements RequestHandler
{
    public function __construct(
        private DirectorioDeIdentidades $directorio,
        private SesionRepository $sesiones,
        private RelojDelSistema $reloj,
        private BovedaDeContrasenas $boveda,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof DeshabilitarPersona);

        $bloqueado = $this->directorio->deshabilitar($peticion->persona);

        if ($bloqueado->isFailure()) {
            return $bloqueado;
        }

        $ahora = $this->reloj->ahora();

        foreach ($this->sesiones->abiertasDe($peticion->persona) as $sesion) {
            $sesion->cerrar($ahora);
            $this->sesiones->save($sesion);
        }

        // Recién con el bloqueo confirmado: nuestra copia cifrada ya no abre
        // nada, y mientras exista, «tiene credencial» sigue diciendo que sí.
        $this->boveda->olvidar($peticion->persona);

        return Result::success();
    }
}
