<?php

declare(strict_types=1);

namespace Features\Reservation\CreateReservation\Application\Port;

use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;

/**
 * Puerto de escritura de reservas confirmadas.
 */
interface ReservationWriterInterface
{
    /**
     * Persiste la reserva junto a sus mesas asignadas.
     *
     * @param list<int> $tableIds
     * @param int       $sectionId Sección elegida (obligatoria).
     *
     * @return int Id de la reserva creada.
     */
    public function persist(
        ServiceSlot $slot,
        int $peopleCount,
        int $locationId,
        array $tableIds,
        int $sectionId,
    ): int;
}
