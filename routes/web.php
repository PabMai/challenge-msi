<?php

use App\Http\Controllers\Web\AgendaController;
use App\Http\Controllers\Web\HomeController;
use Illuminate\Support\Facades\Route;

// Página principal con el formulario de reserva.
Route::get('/', [HomeController::class, 'index'])->name('home');

// Agenda: listado completo de reservas (sin filtro de fecha).
Route::get('/agenda', AgendaController::class)->name('agenda');
