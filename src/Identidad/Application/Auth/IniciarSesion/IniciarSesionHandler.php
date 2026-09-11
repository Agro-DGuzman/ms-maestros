<?php

declare(strict_types=1);

namespace Identidad\Application\Auth\IniciarSesion;

use Core\Contracts\Request;
use Core\Contracts\RequestHandler;
use Core\Results\Error;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\DirectorioDeContactos;
use Identidad\Application\Contracts\EmisorDeToken;
use Identidad\Application\Contracts\RelojDelSistema;
use Identidad\Application\Contracts\TokenEmitido;
use Identidad\Domain\Desafios\DesafioDeIngreso;
use Identidad\Domain\Desafios\DesafioErrors;
use Identidad\Domain\Desafios\DesafioRepository;
use Identidad\Domain\Dispositivos\Dispositivo;
use Identidad\Domain\Dispositivos\IdDeInstalacion;
use Identidad\Domain\Dispositivos\Plataforma;
use Identidad\Domain\Sesiones\IdDeSesion;
use Identidad\Domain\Sesiones\SesionDeAplicacion;
use Identidad\Domain\Sesiones\SesionRepository;

final readonly class IniciarSesionHandler implements RequestHandler
{
    public function __construct(
        private DesafioRepository $desafios,
        private DirectorioDeContactos $directorio,
        private BovedaDeContrasenas $boveda,
        private EmisorDeToken $emisor,
        private SesionRepository $sesiones,
        private RelojDelSistema $reloj,
        private int $diasDeSesion,
    ) {}

    public function handle(Request $peticion): Result
    {
        assert($peticion instanceof IniciarSesion);

        $ahora = $this->reloj->ahora();
        $desafio = $this->desafios->find($peticion->idDeDesafio);

        // Un desafío inexistente responde igual que uno mal resuelto.
        if (! $desafio instanceof DesafioDeIngreso) {
            return ResultWithValue::failure(DesafioErrors::codigoInvalido());
        }

        $resuelto = $desafio->resolver($peticion->codigo, $ahora);
        $this->desafios->save($desafio);   // persiste el intento, acertado o no

        if ($resuelto->isFailure()) {
            return ResultWithValue::failure($resuelto->error);
        }

        $persona = $this->directorio->buscarPorCelular($desafio->celular());
        $contrasena = $persona === null ? null : $this->boveda->leer($persona);

        if ($persona === null || $contrasena === null) {
            return ResultWithValue::failure(Error::problem(
                'IDENTIDAD_NO_DISPONIBLE',
                'La persona no tiene credencial en el proveedor de identidad',
            ));
        }

        $token = $this->emisor->emitirPara($persona, $contrasena);

        if ($token->isFailure()) {
            return ResultWithValue::failure($token->error);
        }

        $dispositivo = $peticion->instalacionId === null ? null : Dispositivo::registrar(
            IdDeInstalacion::desde($peticion->instalacionId),
            Plataforma::tryFrom((string) $peticion->plataforma) ?? Plataforma::Android,
        );

        $sesion = SesionDeAplicacion::abrir(
            IdDeSesion::nueva(),
            $persona,
            $dispositivo,
            $ahora,
            $ahora->modify('+'.$this->diasDeSesion.' days'),
        );

        $emitido = $token->value();
        assert($emitido instanceof TokenEmitido);

        $sesion->asociarRefresh($emitido->refreshToken);
        $this->sesiones->save($sesion);

        $contexto = $this->directorio->contexto($persona);

        if ($contexto === null) {
            return ResultWithValue::failure(Error::notFound(
                'CONTACTO_NO_ENCONTRADO',
                'No se pudo armar el contexto de la persona',
            ));
        }

        return ResultWithValue::of([
            'token' => $emitido,
            'sesion' => $sesion->idDeSesion(),
            'contexto' => $contexto,
        ]);
    }
}
