<?php

declare(strict_types=1);

namespace BackOffice\Application\Contracts;

use BackOffice\Domain\Operadores\Operador;

/**
 * Resuelve quién es el operador que está intentando entrar.
 *
 * Existe como puerto porque el app role de Entra depende de un pedido a TI con
 * plazo propio (§6 del diseño): todo el back-office se construye contra esta
 * interfaz mientras esa respuesta no llega.
 */
interface AutenticadorDeOperador
{
    /**
     * URL a la que hay que mandar el navegador para que el operador se autentique.
     *
     * @param  string  $estado  valor opaco contra CSRF, vuelve en el callback
     * @param  string  $desafioPkce  el code_challenge (S256) de esta transacción
     */
    public function urlDeIngreso(string $estado, string $desafioPkce): string;

    /**
     * @param  string  $codigo  el authorization code del callback
     * @param  string  $verificadorPkce  el code_verifier guardado en la sesión
     *
     * @throws IngresoRechazado si el código no sirve o falta el app role Administrador
     */
    public function resolver(string $codigo, string $verificadorPkce): Operador;
}
