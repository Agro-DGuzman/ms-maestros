<?php

declare(strict_types=1);

namespace Maestros\Application\Contactos;

/**
 * Modelo de lectura de la lista del back-office: personas de contacto con su
 * socio, su grupo y su estado de habilitación, ya paginadas.
 *
 * Es un puerto aparte del `ContactoRepository` porque no carga agregados: la
 * pantalla necesita una proyección que junta tres tablas, y hacerla pasar por
 * el repositorio obligaría a cargar socios y grupos uno por uno.
 */
interface BuscadorDeContactos
{
    public function buscar(CriterioDeBusqueda $criterio): PaginaDeContactos;
}
