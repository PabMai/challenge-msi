<?php

declare(strict_types=1);

namespace App\Listeners\Reservations;

use App\Events\Reservations\ReservationRejected;
use Illuminate\Support\Facades\Log;

/**
 * Registra el motivo del rechazo de una solicitud de reserva.
 */
final class LogRejectedReason
{
    public function handle(ReservationRejected $event): void
    {
        Log::warning('Reserva rechazada', [
            'attempt_id' => $event->attemptId,
            'reason' => $event->reason,
            'requested' => [
                'date' => $event->date,
                'time' => $event->time,
                'people_count' => $event->peopleCount,
            ],
        ]);
    }
}
