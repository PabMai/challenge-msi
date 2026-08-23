<?php

declare(strict_types=1);

namespace Features\Reservation\CreateReservation\Application\Port;

use Features\Table\CombinateTable\Domain\Model\AvailableTable;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;

/**
 * Puerto de lectura de disponibilidad.
 */
interface AvailabilityReaderInterface
{
    /**
     * Ubicaciones en orden de asignación (sort_order).
     *
     * @return list<array{id:int, name:string}>
     */
    public function orderedLocations(): array;

    /**
     * Mesas de la ubicación libres para el turno completo.
     *
     * @return list<AvailableTable>
     */
    public function availableTables(int $locationId, ServiceSlot $slot): array;
}
