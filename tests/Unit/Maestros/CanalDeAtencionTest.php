<?php

declare(strict_types=1);

use Maestros\Domain\Atencion\CanalDeAtencion;
use Maestros\Domain\Contactos\Celular;

it('arma el enlace de WhatsApp con el numero internacional sin el signo', function () {
    $canal = new CanalDeAtencion('Atención al Cliente', Celular::desdeLocalBoliviano('67701468'));

    expect($canal->whatsappUrl())->toBe('https://wa.me/59167701468')
        ->and($canal->telefono())->toBe('+591 677 01 468');
});
