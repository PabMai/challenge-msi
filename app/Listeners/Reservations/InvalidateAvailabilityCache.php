<?php

declare(strict_types=1);

namespace App\Listeners\Reservations;

use App\Events\Reservations\ReservationConfirmed;
use App\Models\Location;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Listener síncrono: invalida la cache de disponibilidad del turno
 * confirmado para todas las ubicaciones, de modo que la siguiente
 * lectura recalcula contra MySQL.
 */
final class InvalidateAvailabilityCache
{
    public function handle(ReservationConfirmed $event): void
    {
        $startHi = Carbon::parse($event->startsAt)->format('Hi');
        $endHi = Carbon::parse($event->endsAt)->format('Hi');

        foreach (Location::query()->pluck('id') as $locationId) {
            Cache::store('redis')->forget(sprintf(
                'reservations:availability:%d:%s:%s-%s',
                $locationId,
                $event->businessDate,
                $startHi,
                $endHi,
            ));
        }

        Log::info('Cache de disponibilidad invalidada', [
            'reservation_id' => $event->reservationId,
            'business_date' => $event->businessDate,
            'slot' => "{$startHi}-{$endHi}",
        ]);
    }
}
