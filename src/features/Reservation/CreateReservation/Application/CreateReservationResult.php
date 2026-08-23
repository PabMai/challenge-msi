<?php

declare(strict_types=1);

namespace Features\Reservation\CreateReservation\Application;

use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;

/**
 * Salida exitosa del caso de uso "Crear reserva".
 */
final readonly class CreateReservationResult
{
    /**
     * @param list<string> $tableCodes
     */
    public function __construct(
        public int $reservationId,
        public int $locationId,
        public string $locationName,
        public int $peopleCount,
        public ServiceSlot $slot,
        public array $tableCodes,
    ) {
    }
}
