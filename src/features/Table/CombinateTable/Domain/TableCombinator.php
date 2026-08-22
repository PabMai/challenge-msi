<?php

declare(strict_types=1);

namespace Features\Table\CombinateTable\Domain;

use Features\Table\CombinateTable\Domain\Model\AvailableTable;

/**
 * Selecciona el conjunto mínimo de mesas que cubre a los comensales.
 *
 * Estrategia best-fit decreciente (determinista):
 *  1. Ordena mesas por capacidad descendente.
 *  2. Toma mesas hasta cubrir peopleCount.
 *  3. Recorta desde la mesa más pequeña mientras siga cubriendo
 *     (minimiza desperdicio de butacas).
 * Devuelve null si no logra cubrir con ≤ maxTablesPerReservation.
 */
final class TableCombinator
{
    /**
     * @param list<AvailableTable> $tables
     * @return list<AvailableTable>|null
     */
    public function combine(array $tables, int $peopleCount, int $maxTables): ?array
    {
        if ($peopleCount < 1) {
            return null;
        }

        $candidates = array_values(
            array_filter($tables, static fn (AvailableTable $table) => $table->capacity > 0)
        );
        usort($candidates, static fn (AvailableTable $a, AvailableTable $b) => $b->capacity <=> $a->capacity);

        $chosen = [];
        $covered = 0;

        foreach ($candidates as $table) {
            if ($covered >= $peopleCount) {
                break;
            }
            $chosen[] = $table;
            $covered += $table->capacity;
        }

        if ($covered < $peopleCount || count($chosen) > $maxTables) {
            return null;
        }

        // Recorte: descarta colas sobrantes manteniendo la cobertura.
        while (count($chosen) > 1) {
            $last = end($chosen);
            if ($covered - $last->capacity < $peopleCount) {
                break;
            }
            array_pop($chosen);
            $covered -= $last->capacity;
        }

        return array_values($chosen);
    }
}
