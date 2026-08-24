<?php

use Features\Reservation\CreateReservation\Application\CreateReservationCommand;
use Features\Reservation\CreateReservation\Application\CreateReservationHandler;
use Features\Reservation\CreateReservation\Application\Port\AvailabilityReaderInterface;
use Features\Reservation\CreateReservation\Application\Port\ReservationWriterInterface;
use Features\Reservation\CreateReservation\Domain\Exception\InsufficientCapacityException;
use Features\Table\CombinateTable\Domain\Model\AvailableTable;
use Features\Table\CombinateTable\Application\CombinateTablesCommand;
use Features\Table\CombinateTable\Domain\TableCombinator;
use Features\Table\CombinateTable\Application\CombinateTablesHandler;
use Features\Reservation\ValidateReservation\Application\ValidateReservationCommand;
use Features\Reservation\ValidateReservation\Application\ValidateReservationHandler;
use Features\Reservation\ValidateReservation\Domain\Exception\CutoffExceededException;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;
use Features\Reservation\ValidateReservation\Domain\ScheduleValidator;

final class InMemoryReader implements AvailabilityReaderInterface
{
    /** @param array<int, list<AvailableTable>> $tablesByLocation */
    public function __construct(
        private readonly array $locations,
        private readonly array $tablesByLocation,
    ) {
    }

    public function orderedLocations(): array
    {
        return $this->locations;
    }

    public function availableTables(int $locationId, ServiceSlot $slot): array
    {
        return $this->tablesByLocation[$locationId] ?? [];
    }
}

final class InMemoryWriter implements ReservationWriterInterface
{
    public array $persisted = [];

    public function persist(ServiceSlot $slot, int $peopleCount, int $locationId, array $tableIds): int
    {
        $this->persisted[] = compact('slot', 'peopleCount', 'locationId', 'tableIds');

        return count($this->persisted);
    }
}

function createHandler(AvailabilityReaderInterface $reader, ReservationWriterInterface $writer): CreateReservationHandler
{
    $config = Helpers\DomainTest::reservationsConfig();

    return new CreateReservationHandler(
        validateReservation: new ValidateReservationHandler(
            new ScheduleValidator($config['business_hours'], $config['duration_minutes'], $config['cutoff_minutes']),
        ),
        combinateTables: new CombinateTablesHandler(new TableCombinator),
        reader: $reader,
        writer: $writer,
        maxTablesPerReservation: $config['max_tables_per_reservation'],
    );
}

$fridayNoon = new DateTimeImmutable('2026-08-21 12:00:00');

test('crea reserva delegando la validacion de horario a ValidateReservation', function () use ($fridayNoon) {
    $reader = new InMemoryReader(
        [['id' => 1, 'name' => 'Salón'], ['id' => 2, 'name' => 'Terraza']],
        [1 => [new AvailableTable(10, 'S01', 4)]],
    );
    $writer = new InMemoryWriter;

    $result = createHandler($reader, $writer)->handle(
        new CreateReservationCommand('2026-08-21', '20:00', 3, $fridayNoon),
    );

    expect($result->reservationId)->toBe(1)
        ->and($result->locationName)->toBe('Salón')
        ->and($result->tableCodes)->toBe(['S01'])
        ->and($result->slot->startsAt->format('H:i'))->toBe('20:00')
        ->and($writer->persisted)->toHaveCount(1)
        ->and($writer->persisted[0]['tableIds'])->toBe([10]);
});

test('pasa a la siguiente ubicacion cuando la primera no tiene mesas', function () use ($fridayNoon) {
    $reader = new InMemoryReader(
        [['id' => 1, 'name' => 'Salón'], ['id' => 2, 'name' => 'Terraza']],
        [2 => [new AvailableTable(20, 'T01', 4), new AvailableTable(21, 'T02', 4)]],
    );
    $writer = new InMemoryWriter;

    $result = createHandler($reader, $writer)->handle(
        new CreateReservationCommand('2026-08-21', '20:00', 6, $fridayNoon),
    );

    expect($result->locationName)->toBe('Terraza')
        ->and($result->tableCodes)->toBe(['T01', 'T02']);
});

test('lanza InsufficientCapacity cuando ninguna ubicacion cubre al grupo', function () use ($fridayNoon) {
    $reader = new InMemoryReader(
        [['id' => 1, 'name' => 'Salón']],
        [1 => [new AvailableTable(10, 'S01', 2)]],
    );

    createHandler($reader, new InMemoryWriter)->handle(
        new CreateReservationCommand('2026-08-21', '20:00', 8, $fridayNoon),
    );
})->throws(InsufficientCapacityException::class);

test('respeta la ubicacion elegida aunque otra tenga disponibilidad', function () use ($fridayNoon) {
    $reader = new InMemoryReader(
        [
            ['id' => 1, 'name' => 'Salón'],
            ['id' => 2, 'name' => 'Terraza'],
        ],
        [
            2 => [new AvailableTable(20, 'T01', 4)],
        ],
    );
    $writer = new InMemoryWriter;

    $result = createHandler($reader, $writer)->handle(
        new CreateReservationCommand('2026-08-21', '20:00', 4, $fridayNoon, locationId: 2),
    );

    expect($result->locationName)->toBe('Terraza')
        ->and($result->tableCodes)->toBe(['T01'])
        ->and($writer->persisted[0]['locationId'])->toBe(2);
});

test('rechaza la ubicacion elegida llena sin caer a otras', function () use ($fridayNoon) {
    $reader = new InMemoryReader(
        [
            ['id' => 1, 'name' => 'Salón'],
            ['id' => 2, 'name' => 'Terraza'],
        ],
        [
            1 => [new AvailableTable(10, 'S01', 8)],
            2 => [new AvailableTable(20, 'T01', 2)],
        ],
    );
    $writer = new InMemoryWriter;

    createHandler($reader, $writer)->handle(
        new CreateReservationCommand('2026-08-21', '20:00', 6, $fridayNoon, locationId: 2),
    );
})->throws(InsufficientCapacityException::class);

test('propaga el cutoff desde la feature de validacion', function () use ($fridayNoon) {
    // límite para 21:00 es 20:45 → 20:50 queda fuera
    $reader = new InMemoryReader([['id' => 1, 'name' => 'Salón']], [1 => []]);

    createHandler($reader, new InMemoryWriter)->handle(
        new CreateReservationCommand('2026-08-21', '21:00', 2, new DateTimeImmutable('2026-08-21 20:50:00')),
    );
})->throws(CutoffExceededException::class);

test('rechaza grupos vacios antes de validar horario', function () {
    $reader = new InMemoryReader([], []);

    createHandler($reader, new InMemoryWriter)->handle(
        new CreateReservationCommand('2026-08-21', '20:00', 0, $fridayNoon ?? new DateTimeImmutable),
    );
})->throws(InvalidArgumentException::class);

test('ValidateReservationCommand construye slots reutilizables por otras features', function () {
    $config = Helpers\DomainTest::reservationsConfig();
    $handler = new ValidateReservationHandler(
        new ScheduleValidator($config['business_hours'], $config['duration_minutes'], $config['cutoff_minutes']),
    );

    $slot = $handler->handle(new ValidateReservationCommand('2026-08-22', '23:00', new DateTimeImmutable('2026-08-21 12:00')));

    expect($slot->businessDateString())->toBe('2026-08-22')
        ->and($slot->endsAt->format('D H:i'))->toBe('Sun 01:00');
});
