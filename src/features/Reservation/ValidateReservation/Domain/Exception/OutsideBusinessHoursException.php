<?php

declare(strict_types=1);

namespace Features\Reservation\ValidateReservation\Domain\Exception;

final class OutsideBusinessHoursException extends \DomainException
{
    public static function forDay(int $isoDay, string $businessDate, string $time): self
    {
        return new self(
            sprintf('El %s no hay servicio a las %s (día ISO %d).', $businessDate, $time, $isoDay)
        );
    }

    public static function slotExceedsClosing(string $time, int $durationMinutes): self
    {
        return new self(
            sprintf(
                'El turno de %s con duración de %d min no cabe completo dentro del horario de cierre.',
                $time,
                $durationMinutes,
            )
        );
    }
}
