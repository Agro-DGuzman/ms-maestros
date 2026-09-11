<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Importacion;

use DateTimeImmutable;
use Illuminate\Console\Command;
use JsonException;
use Maestros\Domain\Contactos\Celular;
use Maestros\Domain\Contactos\ContactoRepository;
use Maestros\Domain\Contactos\IdDePersona;
use Maestros\Domain\Contactos\PersonaDeContacto;
use Maestros\Domain\Grupos\GrupoEconomico;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioRepository;
use Maestros\Infrastructure\Persistence\GrupoRecord;

/**
 * Carga manual de la réplica mientras no exista la ingesta de eventos.
 * Es reejecutable: usa `save()` con upsert, así que volver a correrla
 * reemplaza en vez de duplicar o fallar por clave repetida.
 */
final class ImportarMaestrosCommand extends Command
{
    protected $signature = 'maestros:importar {archivo : Ruta del JSON exportado de SAP}';

    protected $description = 'Carga socios, grupos y personas de contacto desde un archivo JSON';

    public function handle(SocioRepository $socios, ContactoRepository $contactos): int
    {
        $archivo = (string) $this->argument('archivo');

        if (! is_file($archivo)) {
            $this->error("No existe el archivo {$archivo}");

            return self::FAILURE;
        }

        try {
            /** @var array<string, mixed> $datos */
            $datos = json_decode((string) file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("El archivo no es JSON válido: {$e->getMessage()}");

            return self::FAILURE;
        }

        $vigenteDesde = new DateTimeImmutable((string) ($datos['vigenteDesde'] ?? 'now'));
        $ahora = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ((array) ($datos['grupos'] ?? []) as $fila) {
            $grupo = GrupoEconomico::replica(
                IdDeGrupo::desde((string) $fila['id']),
                (string) $fila['nombre'],
                $vigenteDesde,
            );

            GrupoRecord::query()->updateOrCreate(
                ['id_de_grupo' => $grupo->idDeGrupo()->value()],
                [
                    'id_de_grupo' => $grupo->idDeGrupo()->value(),
                    'nombre' => $grupo->nombre(),
                    'vigente_desde' => $vigenteDesde->format('Y-m-d H:i:s'),
                    'importado_el' => $ahora,
                ],
            );
        }

        foreach ((array) ($datos['socios'] ?? []) as $fila) {
            $socios->save(Socio::replica(
                CodigoDeSocio::desde((string) $fila['cardCode']),
                RazonSocial::desde((string) $fila['razonSocial']),
                IdDeGrupo::desde((string) $fila['grupoId']),
                $vigenteDesde,
            ));
        }

        foreach ((array) ($datos['contactos'] ?? []) as $fila) {
            $contactos->save(PersonaDeContacto::replica(
                IdDePersona::desde((string) $fila['id']),
                CodigoDeSocio::desde((string) $fila['cardCode']),
                (string) $fila['nombre'],
                Celular::desdeLocalBoliviano((string) $fila['celular']),
                isset($fila['habilitadaEl']) ? new DateTimeImmutable((string) $fila['habilitadaEl']) : null,
                $vigenteDesde,
            ));
        }

        $this->info(sprintf(
            'Importados %d grupos, %d socios y %d contactos.',
            count((array) ($datos['grupos'] ?? [])),
            count((array) ($datos['socios'] ?? [])),
            count((array) ($datos['contactos'] ?? [])),
        ));

        return self::SUCCESS;
    }
}
