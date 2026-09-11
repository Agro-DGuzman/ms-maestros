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
            $datos = json_decode((string) file_get_contents($archivo), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("El archivo no es JSON válido: {$e->getMessage()}");

            return self::FAILURE;
        }

        $raiz = is_array($datos) ? $datos : [];
        $marca = $raiz['vigenteDesde'] ?? null;

        $vigenteDesde = new DateTimeImmutable(is_string($marca) ? $marca : 'now');
        $ahora = (new DateTimeImmutable)->format('Y-m-d H:i:s');

        $grupos = $this->filas($raiz['grupos'] ?? null);
        $socios_ = $this->filas($raiz['socios'] ?? null);
        $contactos_ = $this->filas($raiz['contactos'] ?? null);

        foreach ($grupos as $fila) {
            $grupo = GrupoEconomico::replica(
                IdDeGrupo::desde($fila['id'] ?? ''),
                $fila['nombre'] ?? '',
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

        foreach ($socios_ as $fila) {
            $socios->save(Socio::replica(
                CodigoDeSocio::desde($fila['cardCode'] ?? ''),
                RazonSocial::desde($fila['razonSocial'] ?? ''),
                IdDeGrupo::desde($fila['grupoId'] ?? ''),
                $vigenteDesde,
            ));
        }

        foreach ($contactos_ as $fila) {
            $contactos->save(PersonaDeContacto::replica(
                IdDePersona::desde($fila['id'] ?? ''),
                CodigoDeSocio::desde($fila['cardCode'] ?? ''),
                $fila['nombre'] ?? '',
                Celular::desdeLocalBoliviano($fila['celular'] ?? ''),
                isset($fila['habilitadaEl']) ? new DateTimeImmutable($fila['habilitadaEl']) : null,
                $vigenteDesde,
            ));
        }

        $this->info(sprintf(
            'Importados %d grupos, %d socios y %d contactos.',
            count($grupos),
            count($socios_),
            count($contactos_),
        ));

        return self::SUCCESS;
    }

    /**
     * El archivo es entrada externa: se queda solo con las filas y los campos
     * escalares, y deja que el dominio rechace lo que falte.
     *
     * @return list<array<string, string>>
     */
    private function filas(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $filas = [];

        foreach ($valor as $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $campos = [];

            foreach ($fila as $clave => $dato) {
                if (is_string($clave) && (is_string($dato) || is_int($dato) || is_float($dato))) {
                    $campos[$clave] = (string) $dato;
                }
            }

            $filas[] = $campos;
        }

        return $filas;
    }
}
