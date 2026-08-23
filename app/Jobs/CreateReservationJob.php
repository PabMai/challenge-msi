<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\Reservations\ReservationConfirmed;
use App\Events\Reservations\ReservationRejected;
use App\Models\ReservationAttempt;
use Features\Reservation\CreateReservation\Application\CreateReservationCommand;
use Features\Reservation\CreateReservation\Application\CreateReservationHandler;
use Features\Reservation\CreateReservation\Application\CreateReservationResult;
use Features\Reservation\CreateReservation\Domain\Exception\InsufficientCapacityException;
use Features\Reservation\CreateReservation\Domain\Exception\InvalidPartySizeException;
use Features\Reservation\ValidateReservation\Domain\Exception\CutoffExceededException;
use Features\Reservation\ValidateReservation\Domain\Exception\OutsideBusinessHoursException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Procesa la creación de una reserva en cola.
 *
 * - Cola "reservations", 3 intentos con backoff [5, 15, 60] segundos.
 * - Sin solapamiento por fecha de servicio: los turnos del mismo día se
 *   procesan en serie para reducir condiciones de carrera de disponibilidad.
 * - Idempotencia vía ReservationAttempt: solo procesa si el intento está
 *   "pending"; un intento ya resuelto (confirmed/rejected/failed) se ignora.
 *
 * Rechazos de negocio (partido inválido, fuera de horario, cutoff, sin
 * capacidad) son permanentes -> attempt "rejected", SIN reintento.
 * Errores inesperados mantienen el intento "pending" y relanzan la excepción
 * para que Laravel reintente con backoff; agotados los intentos, failed()
 * marca el attempt como "failed".
 */
final class CreateReservationJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 15, 60];

    /**
     * @param  non-empty-string  $businessDate  Y-m-d (día de servicio)
     * @param  non-empty-string  $time          H:i solicitado
     */
    public function __construct(
        public readonly string $attemptId,
        public readonly string $businessDate,
        public readonly string $time,
        public readonly int $peopleCount,
        public readonly ?\DateTimeImmutable $now = null,
    ) {
        $this->queue = 'reservations';
    }

    /**
     * Crea el intento en base de datos y encola el job.
     */
    public static function enqueue(string $businessDate, string $time, int $peopleCount): self
    {
        $attempt = ReservationAttempt::query()->create([
            'status' => ReservationAttempt::STATUS_PENDING,
            'payload' => [
                'business_date' => $businessDate,
                'time' => $time,
                'people_count' => $peopleCount,
            ],
        ]);

        return tap(new self($attempt->id, $businessDate, $time, $peopleCount), fn (self $job) => dispatch($job));
    }

    /**
     * Serializa el procesamiento por fecha de servicio.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('create-reservation:'.$this->businessDate))
                ->releaseAfter(15)
                ->expireAfter(600),
        ];
    }

    public function handle(CreateReservationHandler $handler): ?array
    {
        $attempt = ReservationAttempt::query()->find($this->attemptId);

        // Intento inexistente o ya resuelto: ack silencioso (idempotencia).
        if ($attempt === null || $attempt->status !== ReservationAttempt::STATUS_PENDING) {
            Log::info('CreateReservationJob omitido', [
                'attempt_id' => $this->attemptId,
                'reason' => $attempt === null ? 'not_found' : 'already_'.$attempt->status,
            ]);

            return null;
        }

        try {
            $result = $handler->handle(new CreateReservationCommand(
                businessDate: $this->businessDate,
                time: $this->time,
                peopleCount: $this->peopleCount,
                now: $this->now ?? now()->toDateTimeImmutable(),
            ));
        } catch (
            InvalidPartySizeException
            | InsufficientCapacityException
            | OutsideBusinessHoursException
            | CutoffExceededException $e
        ) {
            // Rechazo de negocio: permanente, no reintentar.
            $attempt->forceFill([
                'status' => ReservationAttempt::STATUS_REJECTED,
                'error' => Str::limit(static::class.': '.$e->getMessage(), 250),
            ])->save();

            event(new ReservationRejected(
                $this->attemptId,
                $e->getMessage(),
                $this->businessDate,
                $this->time,
                $this->peopleCount,
            ));

            return null;
        }

        $attempt->forceFill([
            'status' => ReservationAttempt::STATUS_CONFIRMED,
            'result' => self::serializeResult($result),
        ])->save();

        event(new ReservationConfirmed(
            attemptId: $this->attemptId,
            reservationId: $result->reservationId,
            locationId: $result->locationId,
            peopleCount: $result->peopleCount,
            businessDate: $result->slot->businessDateString(),
            startsAt: $result->slot->startsAt->format(DATE_ATOM),
            endsAt: $result->slot->endsAt->format(DATE_ATOM),
            tableCodes: $result->tableCodes,
        ));

        return $attempt->result;
    }

    /**
     * Agotados los intentos por errores inesperados.
     */
    public function failed(\Throwable $exception): void
    {
        ReservationAttempt::query()
            ->whereKey($this->attemptId)
            ->where('status', ReservationAttempt::STATUS_PENDING)
            ->update([
                'status' => ReservationAttempt::STATUS_FAILED,
                'error' => Str::limit(static::class.': '.$exception->getMessage(), 250),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function serializeResult(CreateReservationResult $result): array
    {
        return [
            'reservation_id' => $result->reservationId,
            'location_id' => $result->locationId,
            'location_name' => $result->locationName,
            'people_count' => $result->peopleCount,
            'starts_at' => $result->slot->startsAt->format(DATE_ATOM),
            'ends_at' => $result->slot->endsAt->format(DATE_ATOM),
            'table_codes' => $result->tableCodes,
        ];
    }
}
