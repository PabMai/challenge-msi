<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Página principal con el formulario de reserva.
 */
final class HomeController extends Controller
{
    public function index(): View
    {
        return view('pages.index');
    }
}
