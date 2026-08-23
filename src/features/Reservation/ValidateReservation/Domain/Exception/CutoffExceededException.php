<?php

declare(strict_types=1);

namespace Features\Reservation\ValidateReservation\Domain\Exception;

final class CutoffExceededException extends \DomainException
{
    public static function forSlot(\DateTimeImmutable $startsAt, int $cutoffMinutes): self
    {
        $limit = $startsAt->modify("-{$cutoffMinutes} minutes");

        return new self(
            sprintf(
                'Plazo vencido: solo se aceptan reservas hasta %s para el turno de %s (corte de %d min).',
                $limit->format('H:i'),
                $startsAt->format('H:i'),
                $cutoffMinutes,
            )
        );
    }
}
