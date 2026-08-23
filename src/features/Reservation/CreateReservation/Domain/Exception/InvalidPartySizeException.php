<?php

declare(strict_types=1);

namespace Features\Reservation\CreateReservation\Domain\Exception;

final class InvalidPartySizeException extends \InvalidArgumentException
{
    public static function belowMinimum(int $peopleCount): self
    {
        return new self("La cantidad de personas debe ser al menos 1 (recibido: {$peopleCount}).");
    }
}
