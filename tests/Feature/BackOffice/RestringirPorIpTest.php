<?php

declare(strict_types=1);

use BackOffice\Presentation\Http\Middleware\RestringirPorIp;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

function pedidoDesde(string $ip): Request
{
    return Request::create('/admin/contactos', 'GET', server: ['REMOTE_ADDR' => $ip]);
}

function pasar(RestringirPorIp $middleware, string $ip): mixed
{
    return $middleware->handle(pedidoDesde($ip), static fn (): string => 'pasó');
}

it('deja pasar una ip del rango', function () {
    expect(pasar(new RestringirPorIp(['190.129.4.0/24'], 'production'), '190.129.4.7'))->toBe('pasó');
});

it('bloquea una ip fuera del rango', function () {
    expect(fn () => pasar(new RestringirPorIp(['190.129.4.0/24'], 'production'), '8.8.8.8'))
        ->toThrow(AccessDeniedHttpException::class);
});

it('sin rangos configurados deja pasar en desarrollo', function () {
    expect(pasar(new RestringirPorIp([], 'local'), '8.8.8.8'))->toBe('pasó');
});

it('sin rangos configurados falla en produccion', function () {
    // Una lista vacia en produccion no puede significar "deja pasar a todos":
    // seria abrir el back-office al mundo por un .env incompleto.
    expect(fn () => pasar(new RestringirPorIp([], 'production'), '190.129.4.7'))
        ->toThrow(RuntimeException::class, 'BACKOFFICE_SIN_RANGOS_IP');
});

it('admite una ip suelta ademas de un rango', function () {
    $middleware = new RestringirPorIp(['190.129.4.7', '10.0.0.0/8'], 'production');

    expect(pasar($middleware, '190.129.4.7'))->toBe('pasó')
        ->and(pasar($middleware, '10.1.2.3'))->toBe('pasó')
        ->and(fn () => pasar($middleware, '190.129.4.8'))->toThrow(AccessDeniedHttpException::class);
});
