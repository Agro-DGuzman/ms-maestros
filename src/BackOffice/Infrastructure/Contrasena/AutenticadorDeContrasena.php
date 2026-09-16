<?php

declare(strict_types=1);

namespace BackOffice\Infrastructure\Contrasena;

use BackOffice\Application\Contracts\AutenticadorDeOperador;
use BackOffice\Application\Contracts\IngresoRechazado;
use BackOffice\Domain\Operadores\IdDeOperador;
use BackOffice\Domain\Operadores\Operador;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class AutenticadorDeContrasena implements AutenticadorDeOperador
{
    /**
     * Un hash de descarte con el que comparar cuando el correo no es de nadie,
     * para que un correo inexistente cueste lo mismo que una contraseña mala.
     * Sin esto, el rechazo inmediato delata qué correos son de operadores.
     */
    private const HASH_DE_DESCARTE = '$2y$12$0000000000000000000000000000000000000000000000000000';

    /**
     * Segundos entre verificar la contraseña y canjear el código. Es el salto
     * de un redirect, no una sesión: un minuto sobra y acota la ventana.
     */
    private const VIDA_DEL_CODIGO = 60;

    /** @param list<OperadorConContrasena> $operadores */
    public function __construct(
        private array $operadores,
        private Repository $cache,
        private string $urlDelFormulario,
    ) {
        // Sin operadores nadie entra. Es seguro pero es un error de
        // configuración: fallar al arrancar lo delata antes de que alguien
        // descubra que no puede trabajar.
        if ($operadores === []) {
            throw new RuntimeException('BACKOFFICE_SIN_OPERADORES');
        }
    }

    public function urlDeIngreso(string $estado, string $desafioPkce): string
    {
        return $this->urlDelFormulario;
    }

    /**
     * Verifica la contraseña y devuelve un código de un solo uso.
     *
     * El código existe para que la contraseña no viaje en ninguna URL: el
     * formulario la recibe por POST, y lo que se redirige al callback es esto.
     *
     * @throws IngresoRechazado
     */
    public function emitirCodigoPara(string $correo, string $contrasena): string
    {
        $operador = $this->buscar($correo);
        $coincide = password_verify(
            $contrasena,
            $operador === null ? self::HASH_DE_DESCARTE : $operador->hash,
        );

        if ($operador === null || ! $coincide) {
            throw IngresoRechazado::credencialesInvalidas();
        }

        $codigo = Str::random(64);
        $this->cache->put($this->llave($codigo), $operador->correo, self::VIDA_DEL_CODIGO);

        return $codigo;
    }

    /**
     * El código va hasheado en la llave: el caché es compartido, y guardar ahí
     * el código en claro dejaría una credencial utilizable a la vista de
     * cualquiera que pueda listar llaves.
     */
    private function llave(string $codigo): string
    {
        return 'backoffice.codigo.'.hash('sha256', $codigo);
    }

    public function resolver(string $codigo, string $verificadorPkce): Operador
    {
        // `pull` lee y borra: el código queda gastado apenas se canjea.
        $correo = $this->cache->pull($this->llave($codigo));
        $operador = is_string($correo) ? $this->buscar($correo) : null;

        if ($operador === null) {
            throw IngresoRechazado::codigoInvalido();
        }

        return new Operador(
            id: IdDeOperador::desdeOid($operador->correo),
            nombre: $operador->nombre,
            correo: $operador->correo,
        );
    }

    private function buscar(string $correo): ?OperadorConContrasena
    {
        $buscado = mb_strtolower(trim($correo));

        foreach ($this->operadores as $operador) {
            if (mb_strtolower($operador->correo) === $buscado) {
                return $operador;
            }
        }

        return null;
    }
}
