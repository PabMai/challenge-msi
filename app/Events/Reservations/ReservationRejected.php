<?php

declare(strict_types=1);

namespace App\Events\Reservations;

/**
 * Se dispara cuando la solicitud de reserva es rechazada por reglas de negocio.
 */
final readonly class ReservationRejected
{
    public function __construct(
        public string $attemptId,
        public string $reason,
        public string $businessDate, // Y-m-d solicitado
        public string $time,         // H:i solicitado
        public int $peopleCount,
    ) {
    }
}
