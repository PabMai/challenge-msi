<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Infrastructure\Reservations\DailyAgendaQuery;
use Illuminate\View\View;

/**
 * Agenda de reservas: listado completo sin filtro de fecha.
 */
final class AgendaController extends Controller
{
    public function __invoke(DailyAgendaQuery $query): View
    {
        $reservations = $query->all();

        return view('pages.agenda', [
            'reservations' => $reservations,
            'total' => count($reservations),
        ]);
    }
}
