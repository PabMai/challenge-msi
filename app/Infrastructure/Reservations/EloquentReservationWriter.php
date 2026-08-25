<?php

declare(strict_types=1);

namespace App\Infrastructure\Reservations;

use App\Models\Reservation;
use Features\Reservation\CreateReservation\Application\Port\ReservationWriterInterface;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;
use Illuminate\Support\Facades\DB;

/**
 * Adaptador Eloquent/SQL del puerto de escritura de reservas.
 *
 * Persiste la cabecera y sus mesas (pivot reservation_table) de forma
 * atómica dentro de una transacción.
 */
final class EloquentReservationWriter implements ReservationWriterInterface
{
    public function persist(ServiceSlot $slot, int $peopleCount, int $locationId, array $tableIds, int $sectionId): int
    {
        return DB::transaction(function () use ($slot, $peopleCount, $locationId, $tableIds, $sectionId): int {
            $reservation = Reservation::query()->create([
                'business_date' => $slot->businessDateString(),
                'starts_at' => $slot->startsAt,
                'ends_at' => $slot->endsAt,
                'people_count' => $peopleCount,
                'location_id' => $locationId,
                'section_id' => $sectionId,
            ]);

            $reservation->tables()->sync($tableIds);

            return $reservation->id;
        });
    }
}
