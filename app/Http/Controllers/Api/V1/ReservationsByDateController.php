<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Reservations\DailyAgendaQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Listado de reservas por fecha de servicio.
 *
 * GET /reservations?date=YYYY-MM-DD
 * Una sola consulta SQL (ubicación + sección + mesas agregadas).
 */
final class ReservationsByDateController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $rows = (new DailyAgendaQuery)->forDate($validated['date']);

        return response()->json([
            'date' => $validated['date'],
            'total' => count($rows),
            'reservations' => array_map(static fn (array $row): array => [
                'id' => $row['id'],
                'business_date' => $row['business_date'],
                'starts_at' => $row['start_time'],
                'ends_at' => $row['end_time'],
                'people_count' => $row['people_count'],
                'location' => [
                    'id' => $row['location_id'],
                    'name' => $row['location_name'],
                ],
                'section' => [
                    'id' => $row['section_id'],
                    'name' => $row['section_name'],
                ],
                'tables' => self::zipTables($row['table_codes'], $row['table_capacities']),
            ], $rows),
        ]);
    }

    /**
     * Combina los códigos y capacidades agregados en paralelo.
     *
     * @return list<array{code: string, capacity: int}>
     */
    private static function zipTables(string $codes, string $capacities): array
    {
        $codeList = $codes === '' ? [] : explode(', ', $codes);
        $capacityList = $capacities === '' ? [] : explode(', ', $capacities);

        return array_values(array_map(
            static fn (string $code, string $capacity): array => [
                'code' => $code,
                'capacity' => (int) $capacity,
            ],
            $codeList,
            $capacityList,
        ));
    }
}
