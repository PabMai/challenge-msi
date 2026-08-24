<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Features\Reservation\ValidateReservation\Domain\Exception\CutoffExceededException;
use Features\Reservation\ValidateReservation\Domain\Exception\OutsideBusinessHoursException;
use Features\Reservation\ValidateReservation\Domain\ScheduleValidator;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regla HTTP que envuelve al ScheduleValidator del dominio.
 *
 * Rechaza la petición si la fecha/hora solicitada cae fuera del horario
 * de servicio o vence el cutoff, reutilizando los mensajes del dominio.
 */
final class SchedulableSlot implements ValidationRule
{
    public function __construct(
        private readonly ?ScheduleValidator $validator = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $date = request()?->input('reservation_date');

        // Sin fecha aún: la regla required/date_format ya habrá fallado.
        if (! is_string($date) || ! is_string($value)) {
            return;
        }

        try {
            ($this->validator ?? app(ScheduleValidator::class))->resolve(
                now()->toDateTimeImmutable(),
                $date,
                $value,
            );
        } catch (OutsideBusinessHoursException|CutoffExceededException|\InvalidArgumentException $e) {
            $fail($e->getMessage());
        }
    }
}
