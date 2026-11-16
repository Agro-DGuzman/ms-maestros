<?php

declare(strict_types=1);

namespace Identidad\Domain\Sesiones;

use Core\Contracts\Repository;
use DateTimeImmutable;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\TokenPush;
use Maestros\Domain\Contactos\IdDePersona;

interface SesionRepository extends Repository
{
    /** @return list<SesionDeAplicacion> */
    public function abiertasDe(IdDePersona $persona): array;

    public function porRefreshHash(string $hash): ?SesionDeAplicacion;

    /**
     * Las abiertas con esa instalación, de cualquier persona: un teléfono es
     * de la última sesión que lo registró (NP4).
     *
     * @return list<SesionDeAplicacion>
     */
    public function abiertasConInstalacion(IdDeInstalacion $instalacion, DateTimeImmutable $ahora): array;

    /**
     * Las únicas que reciben un push: abiertas y con token (NP6).
     *
     * @return list<SesionDeAplicacion>
     */
    public function abiertasConTokenPush(DateTimeImmutable $ahora): array;

    /**
     * Las abiertas con ese mismo token, de cualquier persona: reinstalar la
     * App da otra instalación, pero Firebase puede devolver el mismo token, y
     * un token es de la última sesión que lo registró.
     *
     * @return list<SesionDeAplicacion>
     */
    public function abiertasConEsteTokenPush(TokenPush $token, DateTimeImmutable $ahora): array;
}
