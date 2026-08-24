<?php

use App\Http\Controllers\Api\V1\ReservationsByDateController;
use App\Http\Controllers\Api\V1\ReserveController;
use Illuminate\Support\Facades\Route;

// Prefijo final: /api/v1 (el prefijo /api lo aplica withRouting).
Route::prefix('v1')->group(function (): void {
    // Reservas: alta en cola (30 req/min) + polling del estado del intento.
    Route::post('/reserve', [ReserveController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('reserve.store');

    Route::get('/reserve/attempts/{attempt}', [ReserveController::class, 'show'])
        ->whereUuid('attempt')
        ->name('reserve.attempts.show');

    // Agenda por fecha de servicio.
    Route::get('/reservations', ReservationsByDateController::class)
        ->name('reservations.index');
});
