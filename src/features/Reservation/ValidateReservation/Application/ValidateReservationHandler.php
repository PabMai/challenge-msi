<?php

declare(strict_types=1);

namespace Features\Reservation\ValidateReservation\Application;

use Features\Reservation\ValidateReservation\Domain\Exception\CutoffExceededException;
use Features\Reservation\ValidateReservation\Domain\Exception\OutsideBusinessHoursException;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;
use Features\Reservation\ValidateReservation\Domain\ScheduleValidator;

/**
 * Orquestador del caso de uso "Validar reserva".
 *
 * API pública de la feature: otras features (p.ej. CreateReservation)
 * consumen la validación de horario a través de este handler.
 *
 * @throws OutsideBusinessHoursException
 * @throws CutoffExceededException
 */
final class ValidateReservationHandler
{
    public function __construct(
        private readonly ScheduleValidator $validator,
    ) {}

    /**
     * Normaliza la fecha/hora solicitada y devuelve el turno confirmado.
     */
    public function handle(ValidateReservationCommand $command): ServiceSlot
    {
        return $this->validator->resolve(
            $command->now,
            $command->date,
            $command->time,
        );
    }
}
