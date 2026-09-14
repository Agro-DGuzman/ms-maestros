<?php

declare(strict_types=1);

use Identidad\Application\Contracts\EnviadorDeDesafio;
use Identidad\Infrastructure\Whatsapp\EnviarDesafioJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Dobles\EnviadorEspia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('maestros:importar', ['archivo' => database_path('semillas/maestros-ejemplo.json')]);
    $this->enviador = new EnviadorEspia;
    $this->app->instance(EnviadorDeDesafio::class, $this->enviador);
});

it('envia cuando el celular corresponde a una persona registrada', function () {
    dispatch_sync(new EnviarDesafioJob('+59170741828', '4821'));

    expect($this->enviador->enviados)->toHaveCount(1)
        ->and($this->enviador->enviados[0]['celular'])->toBe('+59170741828')
        ->and($this->enviador->enviados[0]['digitos'])->toBe('4821');
});

it('no envia nada cuando el celular no esta registrado', function () {
    dispatch_sync(new EnviarDesafioJob('+59179999999', '4821'));

    expect($this->enviador->enviados)->toBe([]);
});
