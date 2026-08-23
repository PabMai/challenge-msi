<?php

declare(strict_types=1);

namespace Features\Reservation\CreateReservation\Domain\Exception;

use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;

final class InsufficientCapacityException extends \RuntimeException
{
    public static function forSlot(ServiceSlot $slot, int $peopleCount, int $maxTablesPerReservation): self
    {
        return new self(
            sprintf(
                'Sin disponibilidad para %d personas el %s a las %s (límite de %d mesas combinadas).',
                $peopleCount,
                $slot->businessDateString(),
                $slot->startsAt->format('H:i'),
                $maxTablesPerReservation,
            )
        );
    }
}
