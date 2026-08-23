<?php

declare(strict_types=1);

namespace Features\Reservation\ValidateReservation\Domain\Model;

/**
 * Turno confirmado dentro del horario de servicio, ya normalizado.
 *
 * businessDate es el día de servicio (la madrugada que cruza medianoche
 * pertenece al día anterior, ej. sábado 22:00–02:00).
 */
final readonly class ServiceSlot
{
    public function __construct(
        public \DateTimeImmutable $businessDate,
        public \DateTimeImmutable $startsAt,
        public \DateTimeImmutable $endsAt,
    ) {
    }

    public function businessDateString(): string
    {
        return $this->businessDate->format('Y-m-d');
    }
}
