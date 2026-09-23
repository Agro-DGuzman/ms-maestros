<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maestros\Presentation\Http\MiCuentaController;
use Maestros\Presentation\Http\SociosController;

Route::prefix('v1')->middleware('auth.token')->group(function (): void {
    Route::get('mi-cuenta', MiCuentaController::class);
    Route::get('socios', SociosController::class);
});
