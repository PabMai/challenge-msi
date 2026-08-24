<?php

use App\Http\Controllers\Web\HomeController;
use Illuminate\Support\Facades\Route;

// Página principal con el formulario de reserva.
Route::get('/', [HomeController::class, 'index'])->name('home');
