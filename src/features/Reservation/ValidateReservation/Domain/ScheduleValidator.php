<?php

declare(strict_types=1);

namespace Features\Reservation\ValidateReservation\Domain;

use DateTimeImmutable;
use Features\Reservation\ValidateReservation\Domain\Exception\CutoffExceededException;
use Features\Reservation\ValidateReservation\Domain\Exception\InvalidPartySizeException;
use Features\Reservation\ValidateReservation\Domain\Exception\OutsideBusinessHoursException;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;

/**
 * Valida y normaliza la fecha/hora solicitada contra el horario de servicio.
 *
 * Reglas (config-driven, inyectadas por constructor para mantener la
 * clase libre de framework):
 *  - Ventanas por día ISO en minutos desde medianoche; un `end > 1440`
 *    representa cruce de medianoche normalizado al día de servicio.
 *  - La madrugada dominical (00:00–04:00) se atribuye al día anterior:
 *    domingo 00:30 pertenece al servicio del sábado.
 *  - El turno debe caber completo dentro de la ventana (ends_at ≤ cierre).
 *  - Corte: no se aceptan solicitudes después de starts_at − cutoff.
 */
final class ScheduleValidator
{

    /**
     * @param array<int, list<array{start:int,end:int}>> $businessHours
     */
    public function __construct(
        private readonly array $businessHours,
        private readonly int $durationMinutes,
        private readonly int $cutoffMinutes,
    ) {
    }

    /**
     * @throws InvalidPartySizeException
     * @throws OutsideBusinessHoursException
     * @throws CutoffExceededException
     */
    public function resolve(DateTimeImmutable $now, string $businessDate, string $time): ServiceSlot
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $businessDate);
        if ($date === false) {
            throw new \InvalidArgumentException("Fecha inválida: {$businessDate} (formato esperado Y-m-d).");
        }

        $minutes = $this->parseTime($time);

        // 1) La fecha pedida tal cual; 2) madrugada → servicio del día
        //    anterior (el offset +1440 solo puede caer en ventanas que
        //    cruzan medianoche, p.ej. sábado [1320,1560]).
        foreach (
            [
                [$date, $minutes],
                [$date->modify('-1 day'), $minutes + 1440],
            ] as [$day, $offset]
        ) {
            $window = $this->findWindow((int) $day->format('N'), $offset);
            if ($window !== null) {
                return $this->buildSlot($now, $day, $time, $offset, $window);
            }
        }

        throw OutsideBusinessHoursException::forDay((int) $date->format('N'), $businessDate, $time);
    }

    /**
     * @param array{start:int,end:int} $window
     */
    private function findWindow(int $isoDay, int $offset): ?array
    {
        foreach ($this->businessHours[$isoDay] ?? [] as $window) {
            if ($offset >= $window['start'] && $offset < $window['end']) {
                return $window;
            }
        }

        return null;
    }

    /**
     * @param array{start:int,end:int} $window
     */
    private function buildSlot(
        DateTimeImmutable $now,
        DateTimeImmutable $day,
        string $requestedTime,
        int $offset,
        array $window,
    ): ServiceSlot {
        $midnight = $day->setTime(0, 0);
        $startsAt = $midnight->modify("+{$offset} minutes");
        $endsAt = $startsAt->modify("+{$this->durationMinutes} minutes");
        $closingAt = $midnight->modify("+{$window['end']} minutes");

        if ($endsAt > $closingAt) {
            throw OutsideBusinessHoursException::slotExceedsClosing($requestedTime, $this->durationMinutes);
        }

        if ($now > $startsAt->modify("-{$this->cutoffMinutes} minutes")) {
            throw CutoffExceededException::forSlot($startsAt, $this->cutoffMinutes);
        }

        return new ServiceSlot($day, $startsAt, $endsAt);
    }

    private function parseTime(string $time): int
    {
        $parts = \DateTimeImmutable::createFromFormat('!H:i', $time);
        if ($parts === false) {
            throw new \InvalidArgumentException("Hora inválida: {$time} (formato esperado H:i).");
        }

        return ((int) $parts->format('G')) * 60 + ((int) $parts->format('i'));
    }
}
