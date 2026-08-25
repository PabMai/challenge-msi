<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\View\View;

/**
 * Página principal con el formulario de reserva.
 */
final class HomeController extends Controller
{
    public function index(): View
    {
        return view('pages.index', [
            'locations' => Location::query()
                ->with(['sections' => fn ($query) => $query->orderBy('id')])
                ->orderBy('sort_order')
                ->get(['id', 'name']),
        ]);
    }
}
