<?php

declare(strict_types=1);

namespace Maestros\Presentation\Http;

use App\Http\Envelope;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maestros\Application\Configuracion\ConfiguracionDeLaApp;
use Maestros\Domain\Aplicacion\PoliticaDeVersion;
use Maestros\Domain\Aplicacion\Version;
use Maestros\Domain\Atencion\CanalDeAtencion;
use Maestros\Domain\Atencion\CuentaBancaria;
use Maestros\Domain\Atencion\CuentasParaPagar;

/**
 * Los tres endpoints que no dependen de SAP. Los tiempos de caché son los del
 * contrato: la versión, corto, para que retirar una surta efecto en minutos.
 */
final readonly class ConfiguracionDeLaAppController
{
    public function __construct(private ConfiguracionDeLaApp $configuracion) {}

    public function version(Request $peticion): JsonResponse
    {
        $datos = $peticion->validate([
            'plataforma' => ['required', 'in:android,ios'],
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'],
        ]);

        $resultado = $this->configuracion->politicaDeVersion((string) $datos['plataforma']);

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        $politica = $resultado->value();
        assert($politica instanceof PoliticaDeVersion);

        $instalada = Version::desde((string) $datos['version']);
        $estado = $politica->evaluar($instalada);
        $aviso = $politica->aviso($estado);

        $data = ['estado' => $estado->value, 'versionConsultada' => $instalada->texto()]
            + ($politica->minima === null ? [] : ['versionMinimaSoportada' => $politica->minima->texto()])
            + ($politica->recomendada === null ? [] : ['versionRecomendada' => $politica->recomendada->texto()])
            + [
                'permiteContinuar' => $estado->permiteContinuar(),
                'titulo' => $aviso?->titulo,
                'mensaje' => $aviso?->mensaje,
                'urlTienda' => $politica->urlTienda,
                'consultadoEn' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            ];

        return Envelope::responder($resultado, $data)
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function bancos(Request $peticion): JsonResponse
    {
        $resultado = $this->configuracion->cuentasParaPagar();

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        $cuentas = $resultado->value();
        assert($cuentas instanceof CuentasParaPagar);

        $data = [
            'items' => array_map(
                static fn (CuentaBancaria $c): array => ['banco' => $c->banco, 'numeroCuenta' => $c->numeroCuenta],
                $cuentas->cuentas,
            ),
            'titular' => ['razonSocial' => $cuentas->razonSocialDelTitular, 'nit' => $cuentas->nitDelTitular],
        ];

        $respuesta = Envelope::responder($resultado, $data)
            ->setEtag(hash('xxh128', (string) json_encode($data)))
            ->setPublic()
            ->setMaxAge(3600);

        // Con el mismo ETag, la App reusa lo que ya tiene y no baja la lista.
        $respuesta->isNotModified($peticion);

        return $respuesta;
    }

    public function atencionAlCliente(): JsonResponse
    {
        $resultado = $this->configuracion->canalDeAtencion();

        if ($resultado->isFailure()) {
            return Envelope::responder($resultado);
        }

        $canal = $resultado->value();
        assert($canal instanceof CanalDeAtencion);

        return Envelope::responder($resultado, [
            'area' => $canal->area,
            'telefono' => $canal->telefono(),
            'whatsappUrl' => $canal->whatsappUrl(),
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
