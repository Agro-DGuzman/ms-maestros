<?php

declare(strict_types=1);

namespace Core\Contracts;

/**
 * Marcador: esta petición escribe, así que el pipeline la envuelve en una
 * transacción. Las consultas no lo implementan — abrir una transacción para
 * leer sostiene bloqueos en SQL Server sin ganar nada.
 */
interface RequiereTransaccion {}
