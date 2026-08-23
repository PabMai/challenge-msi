<?php

declare(strict_types=1);

namespace Features\Reservation\CreateReservation\Application;

/**
 * Entrada del caso de uso "Crear reserva".
 */
final readonly class CreateReservationCommand
{
    public function __construct(
        public string $businessDate, // Y-m-d tal como la pide el cliente
        public string $time,         // H:i solicitado (puede ser madrugada)
        public int $peopleCount,
        public \DateTimeImmutable $now,
    ) {
    }
}
