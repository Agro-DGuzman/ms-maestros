<?php

declare(strict_types=1);

namespace Maestros\Application\Ingesta\DarDeBajaSocio;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Result;
use Maestros\Application\Eventos\SalidaDeEventos;
use Maestros\Application\Eventos\SocioActualizado;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioErrors;
use Maestros\Domain\Socios\SocioRepository;
use Psr\Log\LoggerInterface;

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
        private GrupoRepository $grupos,
        private SalidaDeEventos $salida,
        private LoggerInterface $log,
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
        $this->publicar($peticion);

        return Result::success();
    }

    /** De lo guardado, como en ReplicarSocioHandler: la vigencia de la baja va al segundo. */
    private function publicar(DarDeBajaSocio $peticion): void
    {
        $dado = $this->socios->find($peticion->socio);
        assert($dado instanceof Socio);

        $grupo = $dado->grupo() === null ? null : $this->grupos->find($dado->grupo());
        $evento = SocioActualizado::de($dado, $grupo instanceof GrupoEconomico ? $grupo : null);

        // Sin línea es un socio de la semilla: ms-comercial no lo tiene y
        // rechazaría la foto (EM4). La baja vale igual.
        if ($evento === null) {
            $this->log->warning('Baja sin evento: el socio no tiene línea', ['cardCode' => $peticion->socio->value()]);

            return;
        }

        $this->salida->registrar($evento);
    }
}
