<?php

use App\Infrastructure\Reservations\DailyAgendaQuery;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\Section;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function agendaTable(Section $section, string $code, int $capacity): Table
{
    return Table::factory()->create([
        'section_id' => $section->id,
        'code' => $code,
        'capacity' => $capacity,
    ]);
}

/**
 * @param  list<Table>  $tables
 */
function agendaReservation(
    Location $location,
    Section $section,
    array $tables,
    string $date,
    string $start,
    int $peopleCount,
): int {
    $reservation = Reservation::create([
        'business_date' => $date,
        'starts_at' => "$date $start:00",
        'ends_at' => "$date 23:00:00",
        'people_count' => $peopleCount,
        'location_id' => $location->id,
        'section_id' => $section->id,
    ]);
    $reservation->tables()->attach(array_map(static fn (Table $t) => $t->id, $tables));

    return $reservation->id;
}

test('devuelve todas las reservas de todas las fechas ordenadas por fecha hora ubicacion y seccion', function () {
    $salon = Location::factory()->create(['name' => 'Salón', 'sort_order' => 1]);
    $terraza = Location::factory()->create(['name' => 'Terraza', 'sort_order' => 2]);
    $bar = Section::query()->create(['location_id' => $salon->id, 'name' => 'Bar']);
    $principal = Section::query()->create(['location_id' => $salon->id, 'name' => 'Salón Principal']);
    $jardin = Section::query()->create(['location_id' => $terraza->id, 'name' => 'Jardín']);

    // fuera de orden a propósito: el SQL debe reordenar
    $r1 = agendaReservation($salon, $bar, [agendaTable($bar, 'S01', 2)], '2026-08-22', '21:00', 2);
    $r2 = agendaReservation($salon, $principal, [
        agendaTable($principal, 'S03', 8),
        agendaTable($principal, 'S02', 4),
    ], '2026-08-21', '20:00', 6);
    $r3 = agendaReservation($terraza, $jardin, [agendaTable($jardin, 'T01', 4)], '2026-08-21', '19:00', 4);

    $rows = (new DailyAgendaQuery)->all();

    expect(count($rows))->toBe(3)
        ->and(array_column($rows, 'id'))->toBe([$r3, $r2, $r1]);

    [$first, $second] = $rows;

    expect($first['business_date'])->toBe('2026-08-21')
        ->and($first['start_time'])->toBe('19:00')
        ->and($first['location_name'])->toBe('Terraza')
        ->and($first['section_name'])->toBe('Jardín')
        ->and($first['table_codes'])->toBe('T01')
        ->and($second['people_count'])->toBe(6)
        ->and($second['location_name'])->toBe('Salón')
        ->and($second['section_name'])->toBe('Salón Principal')
        ->and($second['table_codes'])->toBe('S02, S03')
        ->and($second['table_capacities'])->toBe('4, 8');
});

test('forDate filtra por la fecha indicada en una sola consulta', function () {
    $salon = Location::factory()->create();
    $seccion = Section::query()->create(['location_id' => $salon->id, 'name' => 'Bar']);

    $viernes = agendaReservation(
        $salon,
        $seccion,
        [agendaTable($seccion, 'S01', 2)],
        '2026-08-21',
        '20:00',
        2,
    );
    $sabado = agendaReservation(
        $salon,
        $seccion,
        [agendaTable($seccion, 'S02', 4)],
        '2026-08-22',
        '20:00',
        4,
    );

    $query = new DailyAgendaQuery;

    DB::enableQueryLog();
    $rows = $query->forDate('2026-08-21');
    $queriesForDate = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queriesForDate)->toBe(1)
        ->and(array_column($rows, 'id'))->toBe([$viernes])
        ->and($rows[0]['business_date'])->toBe('2026-08-21');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $all = $query->all();
    $queriesAll = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queriesAll)->toBe(1)
        ->and(count($all))->toBe(2)
        ->and(in_array($sabado, array_column($all, 'id'), true))->toBeTrue();
});
