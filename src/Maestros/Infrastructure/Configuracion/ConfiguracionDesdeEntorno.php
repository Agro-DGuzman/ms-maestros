<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Configuracion;

use Core\Results\DomainException;
use Core\Results\Error;
use Core\Results\ResultWithValue;
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
 * Por ahora la configuración vive en variables de entorno: cambiarla es editar
 * el contenedor. Se interpreta en cada pedido y no al arrancar, para que una
 * variable mal escrita rompa solo su endpoint y no la API entera.
 *
 * Lo que está mal responde CONFIGURACION_INCOMPLETA, y el detalle va al log:
 * el nombre de una variable no le sirve al socio.
 */
final readonly class ConfiguracionDesdeEntorno implements ConfiguracionDeLaApp
{
    /** @param array<string, array{minima: string, recomendada: string, tienda: string}> $versiones */
    public function __construct(
        private array $versiones,
        private AvisoDeVersion $alExigir,
        private AvisoDeVersion $alSugerir,
        private string $bancos,
        private string $razonSocialDelTitular,
        private string $nitDelTitular,
        private string $areaDeAtencion,
        private string $telefonoDeAtencion,
        private LoggerInterface $log,
    ) {}

    public function politicaDeVersion(string $plataforma): ResultWithValue
    {
        $valores = $this->versiones[$plataforma] ?? ['minima' => '', 'recomendada' => '', 'tienda' => ''];

        try {
            return ResultWithValue::of(new PoliticaDeVersion(
                self::versionOpcional($valores['minima']),
                self::versionOpcional($valores['recomendada']),
                trim($valores['tienda']) === '' ? null : trim($valores['tienda']),
                $this->alExigir,
                $this->alSugerir,
            ));
        } catch (DomainException $e) {
            return $this->incompleta("versión de {$plataforma}: ".$e->getError()->description);
        }
    }

    public function cuentasParaPagar(): ResultWithValue
    {
        $cuentas = [];

        foreach (array_filter(array_map('trim', explode(';', $this->bancos))) as $entrada) {
            $partes = array_map('trim', explode('|', $entrada));

            // Saltear una entrada mal escrita dejaría esa cuenta afuera sin
            // que nadie se entere.
            if (count($partes) !== 2 || $partes[0] === '' || $partes[1] === '') {
                return $this->incompleta("BANCOS: la entrada «{$entrada}» no tiene la forma banco|cuenta");
            }

            $cuentas[] = new CuentaBancaria($partes[0], $partes[1]);
        }

        if ($cuentas === []) {
            return $this->incompleta('BANCOS está vacío');
        }

        if (trim($this->nitDelTitular) === '') {
            return $this->incompleta('BANCOS_TITULAR_NIT está vacío');
        }

        return ResultWithValue::of(new CuentasParaPagar(
            $cuentas,
            trim($this->razonSocialDelTitular),
            trim($this->nitDelTitular),
        ));
    }

    public function canalDeAtencion(): ResultWithValue
    {
        try {
            return ResultWithValue::of(new CanalDeAtencion(
                trim($this->areaDeAtencion),
                Celular::desdeLocalBoliviano($this->telefonoDeAtencion),
            ));
        } catch (DomainException $e) {
            return $this->incompleta('ATENCION_TELEFONO: '.$e->getError()->description);
        }
    }

    private static function versionOpcional(string $texto): ?Version
    {
        return trim($texto) === '' ? null : Version::desde(trim($texto));
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
