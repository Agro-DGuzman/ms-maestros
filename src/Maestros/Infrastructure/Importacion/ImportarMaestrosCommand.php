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
use Maestros\Domain\Grupos\GrupoRepository;
use Maestros\Domain\Grupos\IdDeGrupo;
use Maestros\Domain\Socios\CodigoDeSocio;
use Maestros\Domain\Socios\RazonSocial;
use Maestros\Domain\Socios\Socio;
use Maestros\Domain\Socios\SocioRepository;

/**
 * Carga manual de la réplica mientras no exista la ingesta de eventos.
 * Es reejecutable: usa `save()` con upsert, así que volver a correrla
 * reemplaza en vez de duplicar o fallar por clave repetida.
 *
 * Una réplica nunca retrocede: compara `vigenteDesde` contra lo guardado y
 * omite la fila del archivo si lo que ya está es igual de nuevo o más. Sin
 * eso, reejecutar una exportación vieja pisaría datos más nuevos.
 */
final class ImportarMaestrosCommand extends Command
{
    protected $signature = 'maestros:importar {archivo : Ruta del JSON exportado de SAP}';

    protected $description = 'Carga socios, grupos y personas de contacto desde un archivo JSON';

    public function handle(
        SocioRepository $socios,
        ContactoRepository $contactos,
        GrupoRepository $grupos,
    ): int {
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

        $filasDeGrupos = $this->filas($raiz['grupos'] ?? null);
        $filasDeSocios = $this->filas($raiz['socios'] ?? null);
        $filasDeContactos = $this->filas($raiz['contactos'] ?? null);

        $omitidos = 0;

        foreach ($filasDeGrupos as $fila) {
            $grupo = GrupoEconomico::replica(
                IdDeGrupo::desde($fila['id'] ?? ''),
                $fila['nombre'] ?? '',
                $vigenteDesde,
            );

            $existente = $grupos->find($grupo->idDeGrupo());

            // Una réplica nunca retrocede: si lo que está guardado es igual de
            // nuevo o más, la fila del archivo se ignora.
            if ($existente instanceof GrupoEconomico && ! $grupo->debeReemplazarA($existente->vigenteDesde())) {
                $omitidos++;

                continue;
            }

            $grupos->save($grupo);
        }

        foreach ($filasDeSocios as $fila) {
            $socio = Socio::replica(
                CodigoDeSocio::desde($fila['cardCode'] ?? ''),
                RazonSocial::desde($fila['razonSocial'] ?? ''),
                IdDeGrupo::desde($fila['grupoId'] ?? ''),
                $vigenteDesde,
            );

            $existente = $socios->find($socio->codigoDeSocio());

            if ($existente instanceof Socio && ! $socio->debeReemplazarA($existente->vigenteDesde())) {
                $omitidos++;

                continue;
            }

            $socios->save($socio);
        }

        $vistas = [];

        foreach ($filasDeContactos as $fila) {
            $persona = PersonaDeContacto::replica(
                IdDePersona::desde($fila['id'] ?? ''),
                CodigoDeSocio::desde($fila['cardCode'] ?? ''),
                $fila['nombre'] ?? '',
                Celular::desdeLocalBoliviano($fila['celular'] ?? ''),
                isset($fila['habilitadaEl']) ? new DateTimeImmutable($fila['habilitadaEl']) : null,
                $vigenteDesde,
            );

            // La vimos en el archivo: eso vale aunque después se omita por
            // vieja. Es la diferencia entre "no cambió" y "ya no está en SAP".
            $vistas[] = $persona->idDePersona();

            $existente = $contactos->find($persona->idDePersona());

            if ($existente instanceof PersonaDeContacto && ! $persona->debeReemplazarA($existente->vigenteDesde())) {
                $omitidos++;

                continue;
            }

            $contactos->save($persona);
        }

        $contactos->marcarVistasEnImportacion($vistas, new DateTimeImmutable);

        $this->info(sprintf(
            'Importados %d grupos, %d socios y %d contactos. Omitidos por ser más viejos: %d.',
            count($filasDeGrupos),
            count($filasDeSocios),
            count($filasDeContactos),
            $omitidos,
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
