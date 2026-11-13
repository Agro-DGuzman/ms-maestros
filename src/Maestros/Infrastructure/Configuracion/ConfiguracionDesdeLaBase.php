<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Configuracion;

use Core\Results\DomainException;
use Core\Results\Error;
use Core\Results\ResultWithValue;
use Illuminate\Support\Facades\DB;
use Maestros\Application\Configuracion\ConfiguracionDeLaApp;
use Maestros\Domain\Aplicacion\AvisoDeVersion;
use Maestros\Domain\Aplicacion\PoliticaDeVersion;
use Maestros\Domain\Aplicacion\Version;
use Maestros\Domain\Atencion\CanalDeAtencion;
use Maestros\Domain\Atencion\CuentaBancaria;
use Maestros\Domain\Atencion\CuentasParaPagar;
use Maestros\Domain\Contactos\Celular;
use Psr\Log\LoggerInterface;

/**
 * La configuración de la App vive en dos tablas que se cargan a mano: las
 * cuentas para pagar y un diccionario con el resto. Se lee en cada pedido,
 * para que un valor mal cargado rompa solo su endpoint y un cambio rija sin
 * reiniciar nada.
 *
 * Lo que falta o está mal responde CONFIGURACION_INCOMPLETA, y el detalle va
 * al log: el nombre de una clave no le sirve al socio. Una base que no
 * responde no es un dato que falta, así que esa excepción sube.
 */
final readonly class ConfiguracionDesdeLaBase implements ConfiguracionDeLaApp
{
    /**
     * Las claves que el código lee. La misma lista vive en el CHECK
     * CK_configuracion_app_clave de database/sql/maestros-configuracion.sql, y
     * una prueba exige que coincidan: una clave que la base aceptara y nadie
     * leyera se cargaría sin error y no tendría ningún efecto.
     */
    public const CLAVES = [
        'titular.nit',
        'titular.razon_social',
        'atencion.telefono',
        'atencion.area',
        'version.android.minima',
        'version.android.recomendada',
        'version.android.tienda',
        'version.ios.minima',
        'version.ios.recomendada',
        'version.ios.tienda',
        'aviso.obligatoria.titulo',
        'aviso.obligatoria.mensaje',
        'aviso.sugerida.titulo',
        'aviso.sugerida.mensaje',
    ];

    /** Lo que no hace falta cargar para que la App funcione. */
    private const POR_DEFECTO = [
        'titular.razon_social' => 'Agropartners S.R.L.',
        'atencion.area' => 'Atención al Cliente',
        'aviso.obligatoria.titulo' => 'Actualización necesaria',
        'aviso.obligatoria.mensaje' => 'Esta versión dejó de estar disponible. Actualiza para seguir usando la App.',
        'aviso.sugerida.titulo' => 'Hay una versión nueva',
        'aviso.sugerida.mensaje' => 'Hay una versión nueva de la App. Puedes actualizar cuando quieras.',
    ];

    public function __construct(private LoggerInterface $log) {}

    public function politicaDeVersion(string $plataforma): ResultWithValue
    {
        $valores = $this->diccionario();
        $versiones = [];

        foreach (['minima', 'recomendada'] as $tipo) {
            $clave = "version.{$plataforma}.{$tipo}";

            try {
                $versiones[$tipo] = isset($valores[$clave]) ? Version::desde($valores[$clave]) : null;
            } catch (DomainException $e) {
                return $this->incompleta("{$clave}: ".$e->getError()->description);
            }
        }

        return ResultWithValue::of(new PoliticaDeVersion(
            $versiones['minima'],
            $versiones['recomendada'],
            $valores["version.{$plataforma}.tienda"] ?? null,
            new AvisoDeVersion(self::valor($valores, 'aviso.obligatoria.titulo'), self::valor($valores, 'aviso.obligatoria.mensaje')),
            new AvisoDeVersion(self::valor($valores, 'aviso.sugerida.titulo'), self::valor($valores, 'aviso.sugerida.mensaje')),
        ));
    }

    public function cuentasParaPagar(): ResultWithValue
    {
        $cuentas = [];

        foreach (DB::table(self::tabla('cuentas_bancarias'))->orderBy('orden')->get(['banco', 'numero_cuenta']) as $fila) {
            $datos = (array) $fila;
            $banco = self::texto($datos, 'banco');
            $numero = self::texto($datos, 'numero_cuenta');

            // SQLite no tiene los CHECK: una fila en blanco no puede llegar a
            // la App como una cuenta donde no se puede pagar.
            if ($banco === '' || $numero === '') {
                return $this->incompleta('maestros.cuentas_bancarias tiene una cuenta sin banco o sin número');
            }

            $cuentas[] = new CuentaBancaria($banco, $numero);
        }

        if ($cuentas === []) {
            return $this->incompleta('maestros.cuentas_bancarias está vacía');
        }

        $valores = $this->diccionario();

        if (! isset($valores['titular.nit'])) {
            return $this->incompleta('titular.nit no está cargado');
        }

        return ResultWithValue::of(new CuentasParaPagar(
            $cuentas,
            self::valor($valores, 'titular.razon_social'),
            $valores['titular.nit'],
        ));
    }

    public function canalDeAtencion(): ResultWithValue
    {
        $valores = $this->diccionario();

        try {
            return ResultWithValue::of(new CanalDeAtencion(
                self::valor($valores, 'atencion.area'),
                Celular::desdeLocalBoliviano($valores['atencion.telefono'] ?? ''),
            ));
        } catch (DomainException $e) {
            return $this->incompleta('atencion.telefono: '.$e->getError()->description);
        }
    }

    /**
     * Las claves conocidas que tienen valor, ya recortado. Un valor en blanco
     * cuenta como ausente: en SQLite no hay CHECK que lo frene.
     *
     * @return array<string, string>
     */
    private function diccionario(): array
    {
        $valores = [];

        foreach (DB::table(self::tabla('configuracion_app'))->get(['clave', 'valor']) as $fila) {
            $datos = (array) $fila;
            $clave = self::texto($datos, 'clave');
            $valor = self::texto($datos, 'valor');

            if ($valor !== '' && in_array($clave, self::CLAVES, true)) {
                $valores[$clave] = $valor;
            }
        }

        return $valores;
    }

    /** @param array<string, string> $valores */
    private static function valor(array $valores, string $clave): string
    {
        return $valores[$clave] ?? self::POR_DEFECTO[$clave] ?? '';
    }

    /** @param array<mixed> $fila */
    private static function texto(array $fila, string $columna): string
    {
        $valor = $fila[$columna] ?? '';

        return is_string($valor) || is_int($valor) ? trim((string) $valor) : '';
    }

    private static function tabla(string $nombre): string
    {
        return DB::getDriverName() === 'sqlsrv' ? "maestros.{$nombre}" : "maestros_{$nombre}";
    }

    private function incompleta(string $detalle): ResultWithValue
    {
        $this->log->error('Configuración de la App incompleta', ['detalle' => $detalle]);

        return ResultWithValue::failure(Error::problem(
            'CONFIGURACION_INCOMPLETA',
            'El servicio no tiene esta información configurada.',
        ));
    }
}
