<?php

declare(strict_types=1);

namespace Identidad\Domain\Push;

/** Las categorías de la bandeja de ms-comercial (`CategoriaDeAviso` del contrato). */
enum CategoriaDeAviso: string
{
    case Factura = 'factura';
    case Vencimiento = 'vencimiento';
    case Pago = 'pago';
    case ReporteCampo = 'reporte_campo';
    case Catalogo = 'catalogo';
}
