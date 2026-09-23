<?php

declare(strict_types=1);

namespace Maestros\Application\Configuracion;

use Core\Results\ResultWithValue;

/**
 * Lo que la App lee del servidor en vez de llevarlo compilado: qué versiones
 * dejar pasar, dónde pagar y a quién escribir. Un fallo es una configuración
 * incompleta, no un dato del socio.
 */
interface ConfiguracionDeLaApp
{
    /** El valor es una `PoliticaDeVersion`. */
    public function politicaDeVersion(string $plataforma): ResultWithValue;

    /** El valor es `CuentasParaPagar`. */
    public function cuentasParaPagar(): ResultWithValue;

    /** El valor es un `CanalDeAtencion`. */
    public function canalDeAtencion(): ResultWithValue;
}
