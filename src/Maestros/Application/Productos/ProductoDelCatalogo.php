<?php

declare(strict_types=1);

namespace Maestros\Application\Productos;

/**
 * Un producto visible, con todo lo que pintan la tarjeta y la ficha. Los
 * cultivos solo se leen para la ficha: en el listado quedan vacíos.
 */
final readonly class ProductoDelCatalogo
{
    /**
     * @param  list<string>  $cultivos
     * @param  list<DocumentoTecnico>  $documentos
     */
    public function __construct(
        public string $itemCode,
        public string $nombre,
        public CategoriaDelCatalogo $categoria,
        public ?string $presentacion,
        public ?string $imagenUrl,
        public ?string $descripcion,
        public ?string $ingredienteActivo,
        public ?string $formulacion,
        public ?string $dosisReferencial,
        public array $cultivos,
        public ?RegistroDelProducto $registro,
        public array $documentos,
    ) {}
}
