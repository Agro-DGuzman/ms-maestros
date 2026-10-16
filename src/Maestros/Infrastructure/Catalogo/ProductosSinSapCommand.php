<?php

declare(strict_types=1);

namespace Maestros\Infrastructure\Catalogo;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Maestros\Application\Productos\CatalogoAdministrable;
use Maestros\Application\Productos\FiltroDeEnlaces;
use Maestros\Application\Productos\ProductoAdministrable;

/**
 * La transición al catálogo de SAP (P8). ms-maestros no puede saber cuándo
 * terminó la carga inicial del Sincronizador, así que la baja se hace a mano:
 * primero se mira la lista, y después se da de baja.
 *
 * Si SAP manda después uno de esos artículos con vigencia posterior, vuelve.
 */
final class ProductosSinSapCommand extends Command
{
    protected $signature = 'maestros:productos-sin-sap {--dar-de-baja : Darlos de baja, igual que un DELETE de la ingesta}';

    protected $description = 'Lista los productos que nunca llegaron de SAP, y con la opción los da de baja';

    public function handle(CatalogoAdministrable $catalogo): int
    {
        $candidatos = array_values(array_filter(
            $catalogo->listar(null, FiltroDeEnlaces::Todos),
            static fn (ProductoAdministrable $p): bool => ! $p->llegoDeSap && ! $p->dadoDeBaja,
        ));

        if ($candidatos === []) {
            $this->info('Todos los productos llegaron de SAP.');

            return self::SUCCESS;
        }

        $this->table(
            ['Código', 'Nombre', 'Se ve en la App'],
            array_map(
                static fn (ProductoAdministrable $p): array => [
                    $p->itemCode ?? '—',
                    $p->nombre,
                    $p->visibleEnApp() ? 'Sí' : 'No',
                ],
                $candidatos,
            ),
        );

        if (! $this->option('dar-de-baja')) {
            return self::SUCCESS;
        }

        $momento = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        foreach ($candidatos as $producto) {
            $catalogo->darDeBaja($producto->id, $momento);
        }

        $this->info('Se dieron de baja '.count($candidatos).' productos.');

        return self::SUCCESS;
    }
}
