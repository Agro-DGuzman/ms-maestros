<?php

declare(strict_types=1);

namespace Maestros\Application\Eventos;

use DateTimeImmutable;
use DateTimeZone;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Socios\Socio;
use Ramsey\Uuid\Uuid;

/**
 * «El socio cambió» (`maestros.socio.actualizado`, EM3): la foto completa que
 * ms-comercial guarda en su réplica. Sin contactos, que son datos personales y
 * comercial no usa.
 */
final class SocioActualizado
{
    public const string TIPO = 'maestros.socio.actualizado';

    /**
     * Sin línea no hay evento (EM4): ms-comercial la exige y lo mandaría a
     * mensajes muertos. Solo les pasa a los socios de la semilla.
     *
     * El grupo llega aparte porque el socio guarda solo su código; un grupo
     * que no está guardado viaja como `null`, igual que no tener grupo.
     */
    public static function de(Socio $socio, ?GrupoEconomico $grupo): ?EventoSaliente
    {
        $linea = $socio->lineaDeCredito();

        if ($linea === null) {
            return null;
        }

        $utc = new DateTimeZone('UTC');
        $eventoId = Uuid::uuid4()->toString();
        $cardCode = $socio->codigoDeSocio()->value();

        $sobre = [
            'eventoId' => $eventoId,
            'tipo' => self::TIPO,
            // El vigenteDesde con el que se guardó: así la foto del bus y la de
            // SAP se ordenan con el mismo reloj.
            'ocurridoEn' => $socio->vigenteDesde()->setTimezone($utc)->format('Y-m-d\TH:i:s.u\Z'),
            'origen' => 'ms-maestros',
            'clave' => ['objeto' => 'socio', 'cardCode' => $cardCode],
            'version' => 1,
            'datos' => [
                'cardCode' => $cardCode,
                'razonSocial' => $socio->razonSocial()->texto(),
                'grupoEconomico' => $grupo === null ? null : [
                    'codigo' => $grupo->idDeGrupo()->value(),
                    'nombre' => $grupo->nombre(),
                    'segmento' => $grupo->segmento(),
                ],
                'lineaDeCredito' => $linea->texto(),
                // Inactivo no es baja: comercial no decide quién ve al socio.
                'deBaja' => $socio->dadoDeBajaEl() !== null,
            ],
        ];

        return new EventoSaliente($eventoId, self::TIPO, $sobre, new DateTimeImmutable('now', $utc));
    }
}
