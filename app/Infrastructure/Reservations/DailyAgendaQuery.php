<?php

declare(strict_types=1);

namespace App\Infrastructure\Reservations;

use Illuminate\Support\Facades\DB;

/**
 * Listado de reservas en UNA sola consulta SQL.
 *
 * Une reservations con su ubicación, sección y mesas asignadas,
 * agregando los códigos de mesa con GROUP_CONCAT (sin N+1).
 * MySQL 8 resuelve el GROUP BY r.id por dependencia funcional con la PK.
 */
final class DailyAgendaQuery
{
    /**
     * Todas las reservas disponibles, sin filtro de fecha.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->run();
    }

    /**
     * Reservas de una fecha de servicio concreta (Y-m-d).
     *
     * @return list<array<string, mixed>>
     */
    public function forDate(string $businessDate): array
    {
        return $this->run($businessDate);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function run(?string $businessDate = null): array
    {
        $sql = <<<'SQL'
SELECT
    r.id,
    DATE_FORMAT(r.business_date, '%Y-%m-%d') AS business_date,
    TIME_FORMAT(r.starts_at, '%H:%i') AS start_time,
    TIME_FORMAT(r.ends_at, '%H:%i') AS end_time,
    r.people_count,
    l.id AS location_id,
    l.name AS location_name,
    s.id AS section_id,
    s.name AS section_name,
    GROUP_CONCAT(t.code ORDER BY t.code SEPARATOR ', ') AS table_codes,
    GROUP_CONCAT(t.capacity ORDER BY t.code SEPARATOR ', ') AS table_capacities
FROM reservations r
INNER JOIN locations l ON l.id = r.location_id
INNER JOIN sections s ON s.id = r.section_id
INNER JOIN reservation_table rt ON rt.reservation_id = r.id
INNER JOIN `tables` t ON t.id = rt.table_id
SQL;

        $bindings = [];
        if ($businessDate !== null) {
            $sql .= ' WHERE r.business_date = ?';
            $bindings[] = $businessDate;
        }

        $sql .= ' GROUP BY r.id ORDER BY r.business_date, r.starts_at, l.sort_order, s.name';

        return array_map(
            static fn (object $row): array => [
                'id' => (int) $row->id,
                'business_date' => (string) $row->business_date,
                'start_time' => (string) $row->start_time,
                'end_time' => (string) $row->end_time,
                'people_count' => (int) $row->people_count,
                'location_id' => (int) $row->location_id,
                'location_name' => (string) $row->location_name,
                'section_id' => (int) $row->section_id,
                'section_name' => (string) $row->section_name,
                'table_codes' => (string) $row->table_codes,
                'table_capacities' => (string) $row->table_capacities,
            ],
            DB::select($sql, $bindings),
        );
    }
}
