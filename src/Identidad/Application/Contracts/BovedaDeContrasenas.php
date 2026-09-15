<?php

declare(strict_types=1);

namespace Identidad\Application\Contracts;

use Maestros\Domain\Contactos\IdDePersona;

/**
 * La contraseña por persona: generada al habilitar, guardada cifrada, rotable.
 * El socio nunca la conoce ni la necesita.
 */
interface BovedaDeContrasenas
{
    public function guardar(IdDePersona $persona, string $contrasena): void;

    public function leer(IdDePersona $persona): ?string;

    /**
     * Borra la credencial. La llama la revocación: con el usuario bloqueado en
     * el directorio, conservar nuestra copia cifrada no sirve para entrar y
     * hace que «tiene credencial» siga diciendo que sí.
     */
    public function olvidar(IdDePersona $persona): void;

    /**
     * A quiénes les concedimos acceso alguna vez. Es lo que le falta a la
     * conciliación para mirar en la otra dirección: quién conserva credencial
     * sin seguir habilitado en la réplica.
     *
     * @return list<IdDePersona>
     */
    public function personas(): array;
}
