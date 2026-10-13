<?php

declare(strict_types=1);

use Identidad\Application\Contracts\BovedaDeContrasenas;
use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Infrastructure\Whatsapp\EnviarDesafioJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maestros\Domain\Contactos\IdDePersona;
use Tests\Dobles\EnviadorEspia;
use Tests\Soporte\ReplicaDeEjemplo;

uses(RefreshDatabase::class);

// Solo recibe el código un número que puede entrar: habilitado, y con sus
// contactos visibles en un solo grupo. Mandárselo a otro no le sirve de nada.
beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->enviador = new EnviadorEspia;
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);
    app(BovedaDeContrasenas::class)->guardar(IdDePersona::desde(ReplicaDeEjemplo::MONICA), 'la-contrasena');
});

it('envia a un numero habilitado que entra', function () {
    dispatch_sync(new EnviarDesafioJob('+59170741828', '4821'));

    expect($this->enviador->enviados)->toHaveCount(1)
        ->and($this->enviador->enviados[0]['celular'])->toBe('+59170741828')
        ->and($this->enviador->enviados[0]['digitos'])->toBe('4821');
});

it('no envia nada cuando el celular no esta registrado', function () {
    dispatch_sync(new EnviarDesafioJob('+59179999999', '4821'));

    expect($this->enviador->enviados)->toBe([]);
});

it('no envia a un numero sin credencial', function () {
    app(BovedaDeContrasenas::class)->olvidar(IdDePersona::desde(ReplicaDeEjemplo::MONICA));

    dispatch_sync(new EnviarDesafioJob('+59170741828', '4821'));

    expect($this->enviador->enviados)->toBe([]);
});

it('no envia a un numero en dos grupos', function () {
    ReplicaDeEjemplo::otroContactoCon('+59170741828');

    dispatch_sync(new EnviarDesafioJob('+59170741828', '4821'));

    expect($this->enviador->enviados)->toBe([]);
});
