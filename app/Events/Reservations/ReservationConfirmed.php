<?php

declare(strict_types=1);

namespace App\Events\Reservations;

/**
 * Se dispara cuando un intento de reserva queda confirmado.
 *
 * Solo transporta escalares para que los listeners en cola puedan
 * serializarlo sin sorpresas.
 */
final readonly class ReservationConfirmed
{
    /**
     * @param  list<string>  $tableCodes
     */
    public function __construct(
        public string $attemptId,
        public int $reservationId,
        public int $locationId,
        public int $peopleCount,
        public string $businessDate, // Y-m-d (día de servicio)
        public string $startsAt,     // DATE_ATOM
        public string $endsAt,       // DATE_ATOM
        public array $tableCodes,
    ) {
    }
}
