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

    /*
     * Los tres que siguen cambian solo el token y solo si la fila sigue como
     * se la leyó. Guardar la sesión entera pisaría lo que otro pedido confirmó
     * mientras tanto: un logout quedaría deshecho, o un token recién
     * registrado, borrado.
     */

    /**
     * Escribe el token de la sesión si sigue abierta y con la misma
     * instalación. Dice si lo escribió.
     */
    public function guardarTokenPushSiSigueAbierta(SesionDeAplicacion $sesion, DateTimeImmutable $ahora): bool;

    /** Un token es de la última sesión que lo registró: se lo quita a todas las demás, de cualquier persona. */
    public function quitarEsteTokenPushDeOtras(TokenPush $token, IdDeSesion $excepto): void;

    /** Borra el token de la sesión solo si sigue siendo ese: el que Firebase dio por muerto. */
    public function quitarTokenPushSiSigueSiendo(IdDeSesion $sesion, TokenPush $token): void;
}
