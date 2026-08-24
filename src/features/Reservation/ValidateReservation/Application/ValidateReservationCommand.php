<?php

declare(strict_types=1);

namespace Features\Reservation\ValidateReservation\Application;

/**
 * Entrada del caso de uso "Validar reserva" (fecha/hora contra el horario).
 */
final readonly class ValidateReservationCommand
{
    public function __construct(
        public string $date,   // Y-m-d solicitado
        public string $time,   // H:i solicitado (admite madrugada)
        public \DateTimeImmutable $now,
    ) {}
}
