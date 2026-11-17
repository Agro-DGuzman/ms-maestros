<?php

declare(strict_types=1);

use Identidad\Presentation\Http\AuthController;
use Identidad\Presentation\Http\DispositivosController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->middleware('gateway:v1')->group(function (): void {
    Route::post('otp', [AuthController::class, 'otp']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    // Cierra la sesión de quien la pide: sin token no se sabe de quién es.
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth.token');
});

// Es de Identidad aunque cuelgue fuera de /auth: la sesión es la que guarda el token.
Route::prefix('v1')->middleware(['gateway:v1', 'auth.token'])->group(function (): void {
    Route::put('dispositivos/{instalacionId}/token', [DispositivosController::class, 'cambiarToken']);
});
