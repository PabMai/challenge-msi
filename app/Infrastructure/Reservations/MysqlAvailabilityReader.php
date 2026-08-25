<?php

declare(strict_types=1);

namespace App\Infrastructure\Reservations;

use App\Models\Table;
use Features\Reservation\CreateReservation\Application\Port\AvailabilityReaderInterface;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;
use Features\Table\CombinateTable\Domain\Model\AvailableTable;
use Illuminate\Support\Facades\DB;

/**
 * Adaptador MySQL del puerto de disponibilidad (fuente de verdad).
 *
 * Una mesa está ocupada si tiene alguna reserva el mismo business_date
 * cuyo intervalo [starts_at, ends_at) se solape con el turno solicitado.
 */
final class MysqlAvailabilityReader implements AvailabilityReaderInterface
{
    public function orderedLocations(): array
    {
        return DB::table('locations')
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(static fn ($l) => ['id' => $l->id, 'name' => $l->name])
            ->all();
    }

    public function availableTables(int $locationId, ServiceSlot $slot, int $sectionId): array
    {
        $busyTableIds = DB::table('reservation_table')
            ->join('reservations', 'reservations.id', '=', 'reservation_table.reservation_id')
            ->where('reservations.business_date', $slot->businessDateString())
            ->where('reservations.starts_at', '<', $slot->endsAt)
            ->where('reservations.ends_at', '>', $slot->startsAt)
            ->pluck('reservation_table.table_id');

        return Table::query()
            ->where('location_id', $locationId)
            ->where('section_id', $sectionId)
            ->whereNotIn('id', $busyTableIds)
            ->orderBy('code')
            ->get()
            ->map(static fn (Table $t) => new AvailableTable($t->id, $t->code, $t->capacity))
            ->all();
    }
}
