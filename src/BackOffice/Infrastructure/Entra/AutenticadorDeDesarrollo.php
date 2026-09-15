<?php

declare(strict_types=1);

namespace BackOffice\Infrastructure\Entra;

use BackOffice\Application\Contracts\AutenticadorDeOperador;
use BackOffice\Application\Contracts\IngresoRechazado;
use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use RuntimeException;

/**
 * Entra sin Entra. Devuelve siempre el operador de configuración.
 *
 * Muere en el constructor si el entorno es producción, con el mismo criterio
 * que `ResolutorQueNiegaTodo`: un fallo de arranque es ruidoso y temprano; un
 * back-office que deja entrar a cualquiera es silencioso y tarde.
 */
final readonly class AutenticadorDeDesarrollo implements AutenticadorDeOperador
{
    private const CODIGO_FIJO = 'desarrollo';

    public function __construct(
        string $entorno,
        private string $nombre,
        private string $correo,
        private string $oid,
        private string $urlDeCallback,
    ) {
        if ($entorno === 'production') {
            throw new RuntimeException('AUTENTICADOR_DE_DESARROLLO_EN_PRODUCCION');
        }
    }

    public function urlDeIngreso(string $estado, string $desafioPkce): string
    {
        return $this->urlDeCallback.'?'.http_build_query([
            'code' => self::CODIGO_FIJO,
            'state' => $estado,
        ]);
    }

    public function resolver(string $codigo, string $verificadorPkce): Operador
    {
        if ($codigo !== self::CODIGO_FIJO) {
            throw IngresoRechazado::codigoInvalido();
        }

        return new Operador(
            id: IdDeOperador::desdeOid($this->oid),
            nombre: $this->nombre,
            correo: $this->correo,
        );
    }
}
