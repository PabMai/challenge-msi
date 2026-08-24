<?php

declare(strict_types=1);

namespace Features\Reservation\CreateReservation\Application;

use Features\Reservation\CreateReservation\Application\Port\AvailabilityReaderInterface;
use Features\Reservation\CreateReservation\Application\Port\ReservationWriterInterface;
use Features\Reservation\CreateReservation\Domain\Exception\InsufficientCapacityException;
use Features\Reservation\CreateReservation\Domain\Exception\InvalidPartySizeException;
use Features\Reservation\ValidateReservation\Application\ValidateReservationCommand;
use Features\Reservation\ValidateReservation\Application\ValidateReservationHandler;
use Features\Reservation\ValidateReservation\Domain\Exception\CutoffExceededException;
use Features\Reservation\ValidateReservation\Domain\Exception\OutsideBusinessHoursException;
use Features\Table\CombinateTable\Application\CombinateTablesCommand;
use Features\Table\CombinateTable\Application\CombinateTablesHandler;

/**
 * Orquestador del caso de uso "Crear reserva".
 *
 * Flujo:
 *  1. Valida tamaño del grupo (regla de dominio).
 *  2. Delega la validación de fecha/hora a la feature ValidateReservation.
 *  3. Recorre ubicaciones por prioridad delegando la combinación de mesas
 *     a la feature CombinateTable.
 *  4. Persiste vía puerto y devuelve el resultado.
 *
 * No conoce Eloquent ni Laravel: depende de puertos y de las APIs públicas
 * de las features ValidateReservation y CombinateTable.
 */
final class CreateReservationHandler
{
    public function __construct(
        private readonly ValidateReservationHandler $validateReservation,
        private readonly CombinateTablesHandler $combinateTables,
        private readonly AvailabilityReaderInterface $reader,
        private readonly ReservationWriterInterface $writer,
        private readonly int $maxTablesPerReservation,
    ) {}

    /**
     * @throws InvalidPartySizeException
     * @throws OutsideBusinessHoursException
     * @throws CutoffExceededException
     * @throws InsufficientCapacityException
     */
    public function handle(CreateReservationCommand $command): CreateReservationResult
    {
        if ($command->peopleCount < 1) {
            throw InvalidPartySizeException::belowMinimum($command->peopleCount);
        }

        $slot = $this->validateReservation->handle(new ValidateReservationCommand(
            date: $command->date,
            time: $command->time,
            now: $command->now,
        ));

        foreach ($this->reader->orderedLocations() as ['id' => $locationId, 'name' => $locationName]) {
            $combination = $this->combinateTables->handle(new CombinateTablesCommand(
                availableTables: $this->reader->availableTables($locationId, $slot),
                peopleCount: $command->peopleCount,
                maxTables: $this->maxTablesPerReservation,
            ));

            if ($combination === null) {
                continue;
            }

            $reservationId = $this->writer->persist(
                $slot,
                $command->peopleCount,
                $locationId,
                array_map(
                    static fn ($table) => $table->id,
                    $combination,
                ),
            );

            return new CreateReservationResult(
                reservationId: $reservationId,
                locationId: $locationId,
                locationName: $locationName,
                peopleCount: $command->peopleCount,
                slot: $slot,
                tableCodes: array_map(static fn ($table) => $table->code, $combination),
            );
        }

        throw InsufficientCapacityException::forSlot($slot, $command->peopleCount, $this->maxTablesPerReservation);
    }
}
