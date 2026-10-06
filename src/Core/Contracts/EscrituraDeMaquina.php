<?php

declare(strict_types=1);

namespace Core\Contracts;

/**
 * Marcador: esta petición la hace una identidad de máquina (el Sincronizador),
 * no una persona de contacto. No hay persona, así que no hay alcance que
 * verificar, y por eso queda fuera de la regla de `ConAlcanceDeSocio`.
 *
 * La excepción está vigilada: `AlcanceTest` exige que solo la implementen
 * peticiones de la ingesta, para que no se cuele en una que sí tiene persona.
 */
interface EscrituraDeMaquina {}
