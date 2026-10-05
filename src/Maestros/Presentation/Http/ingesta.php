<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maestros\Presentation\Http\IngestaController;

/*
 * La API de ingesta del Sincronizador (contrato/ingesta-api-v1.yaml). Fuera de
 * `auth.token`: no hay persona, hay una identidad de máquina. El orden de los
 * middlewares es el de la seguridad: gateway, token, idempotencia.
 */
Route::prefix('ingesta/v1')
    ->middleware(['json', 'gateway:ingesta', 'ingesta.token', 'ingesta.idempotencia'])
    ->group(function (): void {
        Route::post('socios', [IngestaController::class, 'crear']);
        Route::put('socios/{cardCode}', [IngestaController::class, 'reemplazar']);
        Route::delete('socios/{cardCode}', [IngestaController::class, 'eliminar']);
    });
