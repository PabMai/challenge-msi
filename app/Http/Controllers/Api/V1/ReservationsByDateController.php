<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listado de reservas por fecha de servicio.
 *
 * GET /reservations?date=YYYY-MM-DD
 */
final class ReservationsByDateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $reservations = Reservation::query()
            ->with(['location', 'tables'])
            ->where('business_date', $validated['date'])
            ->orderBy('starts_at')
            ->get()
            ->map(static fn (Reservation $r): array => [
                'id' => $r->id,
                'business_date' => $r->business_date->format('Y-m-d'),
                'starts_at' => $r->starts_at->format('H:i'),
                'ends_at' => $r->ends_at->format('H:i'),
                'people_count' => $r->people_count,
                'location' => [
                    'id' => $r->location->id,
                    'name' => $r->location->name,
                ],
                'tables' => $r->tables->map(static fn ($t): array => [
                    'code' => $t->code,
                    'capacity' => $t->capacity,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return response()->json([
            'date' => $validated['date'],
            'total' => count($reservations),
            'reservations' => $reservations,
        ]);
    }
}
