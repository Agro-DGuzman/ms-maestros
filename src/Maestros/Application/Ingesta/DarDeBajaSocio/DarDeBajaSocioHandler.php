<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\DarDeBajaSocio;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioErrors;
use Maestros\Domain\Socios\SocioRepository;

/**
 * Baja lógica: la fila queda, porque la bitácora, las propiedades y las
 * credenciales la siguen nombrando. La vigencia pasa a ser el momento de la
 * baja, así un envío leído en SAP antes no lo resucita.
 */
final readonly class DarDeBajaSocioHandler implements RequestHandler
{
    public function __construct(
        private SocioRepository $socios,
        private ContactoRepository $contactos,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof DarDeBajaSocio);

        $socio = $this->socios->find($peticion->socio);

        // El Sincronizador toma este 404 como éxito: el socio ya no estaba.
        if (! $socio instanceof Socio || $socio->dadoDeBajaEl() !== null) {
            return Result::failure(SocioErrors::noEncontrado($peticion->socio->value()));
        }

        $this->socios->save($socio->dadoDeBaja($peticion->momento));
        $this->contactos->darDeBajaLosQueNoVinieron($peticion->socio, [], $peticion->momento);

        return Result::success();
    }
}
