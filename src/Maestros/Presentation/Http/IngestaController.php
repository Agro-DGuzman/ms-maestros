<?php

declare(strict_types=1);

namespace Maestros\Presentation\Http;

use App\Http\Envelope;
use Core\Contracts\Mediator;
use Core\Results\DomainException;
use Core\Results\Result;
use Core\Results\ResultWithValue;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Maestros\Application\Ingesta\ContactoIngresado;
use Maestros\Application\Ingesta\DarDeBajaSocio\DarDeBajaSocio;
use Maestros\Application\Ingesta\GrupoIngresado;
use Maestros\Application\Ingesta\OperacionDeIngesta;
use Maestros\Application\Ingesta\ReplicarSocio\ReplicarSocio;
use Maestros\Application\Ingesta\ResultadoDeIngesta;
use Maestros\Application\Ingesta\SocioIngresado;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\SocioErrors;

/**
 * La API de ingesta del Sincronizador. Acá se valida la forma del cuerpo (los
 * 400 del contrato); las reglas de negocio son de los casos de uso.
 */
final readonly class IngestaController
{
    public function __construct(private Mediator $mediator) {}

    public function crear(Request $pedido): JsonResponse
    {
        return $this->replicar($pedido, OperacionDeIngesta::Crear, null, 201);
    }

    public function reemplazar(Request $pedido, string $cardCode): JsonResponse
    {
        return $this->replicar($pedido, OperacionDeIngesta::Reemplazar, $cardCode, 200);
    }

    public function eliminar(string $cardCode): Response|JsonResponse
    {
        // Un código que ni siquiera es válido no puede estar en la réplica:
        // para el Sincronizador, «ya no estaba».
        try {
            $socio = CodigoDeSocio::desde($cardCode);
        } catch (DomainException) {
            return Envelope::responder(Result::failure(SocioErrors::noEncontrado($cardCode)));
        }

        $resultado = $this->mediator->send(new DarDeBajaSocio($socio, self::ahora()));

        self::registrar($cardCode, 'eliminar', $resultado->isSuccess ? 'dado-de-baja' : $resultado->error->code, null);

        return $resultado->isSuccess ? response()->noContent() : Envelope::responder($resultado);
    }

    private function replicar(Request $pedido, OperacionDeIngesta $operacion, ?string $cardCodeDeLaRuta, int $statusExito): JsonResponse
    {
        $socio = self::socioIngresado($pedido->validate([
            'cardCode' => ['required', 'string', 'max:15'],
            'razonSocial' => ['required', 'string', 'max:100'],
            'tipoSap' => ['required', 'in:C,S,L'],
            'activo' => ['required', 'boolean'],
            'grupoEconomico' => ['nullable', 'array'],
            'grupoEconomico.codigo' => ['required_with:grupoEconomico', 'string', 'max:50'],
            'grupoEconomico.nombre' => ['required_with:grupoEconomico', 'string', 'max:200'],
            'grupoEconomico.segmento' => ['nullable', 'string', 'max:100'],
            'contactos' => ['present', 'array'],
            // El id de persona es p-{codigo}: solo dígitos, y entra en 40.
            'contactos.*.codigoDeContacto' => ['required', 'string', 'regex:/^\d{1,38}$/', 'distinct'],
            // SAP siempre trae OCPR.Name: un nombre vacío es un dato corrupto,
            // y la pantalla de Contactos no podría calcular sus iniciales.
            'contactos.*.nombre' => ['required', 'string', 'max:200'],
            'contactos.*.celular' => ['nullable', 'string', 'max:40'],
            'contactos.*.activo' => ['required', 'boolean'],
            'vigenteDesde' => ['required', 'date'],
            'origen.esquema' => ['required', 'string', 'max:20'],
            'origen.eventoId' => ['required', 'integer', 'min:0'],
        ]));

        $resultado = $this->mediator->send(new ReplicarSocio($operacion, $cardCodeDeLaRuta, $socio, self::ahora()));

        $detalle = $resultado instanceof ResultWithValue && $resultado->isSuccess && $resultado->value() instanceof ResultadoDeIngesta
            ? $resultado->value()->value
            : $resultado->error->code;

        self::registrar($socio->cardCode, $operacion === OperacionDeIngesta::Crear ? 'crear' : 'reemplazar', $detalle, $socio->origenEventoId);

        return Envelope::responder($resultado, ['cardCode' => $socio->cardCode], $statusExito);
    }

    /** @param array<string, mixed> $datos ya validados */
    private static function socioIngresado(array $datos): SocioIngresado
    {
        $grupo = is_array($datos['grupoEconomico'] ?? null) ? $datos['grupoEconomico'] : null;
        $origen = is_array($datos['origen'] ?? null) ? $datos['origen'] : [];
        $contactos = is_array($datos['contactos'] ?? null) ? $datos['contactos'] : [];

        return new SocioIngresado(
            cardCode: self::texto($datos, 'cardCode'),
            razonSocial: self::texto($datos, 'razonSocial'),
            tipoSap: self::texto($datos, 'tipoSap'),
            activo: (bool) ($datos['activo'] ?? false),
            grupo: $grupo === null ? null : new GrupoIngresado(
                self::texto($grupo, 'codigo'),
                self::texto($grupo, 'nombre'),
                isset($grupo['segmento']) ? self::texto($grupo, 'segmento') : null,
            ),
            contactos: array_values(array_map(
                static fn (mixed $c): ContactoIngresado => new ContactoIngresado(
                    self::texto((array) $c, 'codigoDeContacto'),
                    self::texto((array) $c, 'nombre'),
                    isset(((array) $c)['celular']) ? self::texto((array) $c, 'celular') : null,
                    (bool) (((array) $c)['activo'] ?? false),
                ),
                $contactos,
            )),
            vigenteDesde: new DateTimeImmutable(self::texto($datos, 'vigenteDesde')),
            origenEsquema: self::texto($origen, 'esquema'),
            origenEventoId: is_numeric($origen['eventoId'] ?? null) ? (int) $origen['eventoId'] : 0,
        );
    }

    /** @param array<mixed> $datos */
    private static function texto(array $datos, string $clave): string
    {
        $valor = $datos[$clave] ?? '';

        return is_scalar($valor) ? trim((string) $valor) : '';
    }

    private static function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** Una línea por envío, para cruzarla con el log del Sincronizador. */
    private static function registrar(string $cardCode, string $operacion, string $resultado, ?int $eventoId): void
    {
        Log::info('ingesta', [
            'cardCode' => $cardCode,
            'operacion' => $operacion,
            'resultado' => $resultado,
            'eventoId' => $eventoId,
        ]);
    }
}
