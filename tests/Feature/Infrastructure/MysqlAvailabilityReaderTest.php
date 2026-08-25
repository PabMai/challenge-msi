<?php

use App\Infrastructure\Reservations\MysqlAvailabilityReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSlot(string $date, string $start, string $end): Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot
{
    return new Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot(
        new DateTimeImmutable($date),
        new DateTimeImmutable("$date $start"),
        new DateTimeImmutable("$date $end"),
    );
}

function seedSection(App\Models\Location|int $location, string $name = 'Salón Principal'): App\Models\Section
{
    return App\Models\Section::query()->create([
        'location_id' => $location instanceof App\Models\Location ? $location->id : $location,
        'name' => $name,
    ]);
}

test('ordena las ubicaciones por sort_order', function () {
    App\Models\Location::create(['name' => 'Terraza', 'sort_order' => 2]);
    App\Models\Location::create(['name' => 'Salón', 'sort_order' => 1]);

    $locations = (new MysqlAvailabilityReader)->orderedLocations();

    expect(array_column($locations, 'name'))->toBe(['Salón', 'Terraza'])
        ->and(array_column($locations, 'id'))->each->toBeInt();
});

test('devuelve solo mesas de la ubicacion y seccion ordenadas por codigo', function () {
    $salon = App\Models\Location::factory()->create(['name' => 'Salón', 'sort_order' => 1]);
    $terraza = App\Models\Location::factory()->create(['name' => 'Terraza', 'sort_order' => 2]);
    $seccion = seedSection($salon);
    $otraSeccion = seedSection($terraza, 'Jardín');

    App\Models\Table::factory()->create(['section_id' => $otraSeccion->id, 'code' => 'T01']);
    App\Models\Table::factory()->create(['section_id' => $seccion->id, 'code' => 'S02', 'capacity' => 4]);
    App\Models\Table::factory()->create(['section_id' => $seccion->id, 'code' => 'S01']);

    $tables = (new MysqlAvailabilityReader)->availableTables($salon->id, makeSlot('2026-08-21', '20:00', '22:00'), $seccion->id);

    expect(array_map(fn ($t) => $t->code, $tables))->toBe(['S01', 'S02'])
        ->and($tables[1])->capacity->toBe(4);
});

test('excluye mesas con reserva que se solapa con el turno', function () {
    $salon = App\Models\Location::factory()->create();
    $seccion = seedSection($salon);
    $libre = App\Models\Table::factory()->create(['section_id' => $seccion->id, 'code' => 'S01']);
    $ocupada = App\Models\Table::factory()->create(['section_id' => $seccion->id, 'code' => 'S02', 'capacity' => 4]);

    $reserva = App\Models\Reservation::create([
        'business_date' => '2026-08-21',
        'starts_at' => '2026-08-21 20:00:00',
        'ends_at' => '2026-08-21 22:00:00',
        'people_count' => 4,
        'location_id' => $salon->id,
        'section_id' => $seccion->id,
    ]);
    $reserva->tables()->attach([$ocupada->id]);

    // el turno solicitado 21:00-23:00 solapa con la reserva existente 20:00-22:00
    $tables = (new MysqlAvailabilityReader)->availableTables($salon->id, makeSlot('2026-08-21', '21:00', '23:00'), $seccion->id);

    expect(array_map(fn ($t) => $t->code, $tables))->toBe([$libre->code]);
});

test('devuelve solo las mesas de la seccion indicada', function () {
    $salon = App\Models\Location::factory()->create();
    $bar = seedSection($salon, 'Bar');
    $principal = seedSection($salon, 'Salón Principal');

    App\Models\Table::factory()->create(['section_id' => $bar->id, 'code' => 'S01']);
    App\Models\Table::factory()->create(['section_id' => $principal->id, 'code' => 'S02']);

    $tables = (new MysqlAvailabilityReader)->availableTables($salon->id, makeSlot('2026-08-21', '20:00', '22:00'), $bar->id);

    expect(array_map(fn ($t) => $t->code, $tables))->toBe(['S01']);
});

test('no excluye reservas de otro business_date ni turnos contiguos', function () {
    $salon = App\Models\Location::factory()->create();
    $seccion = seedSection($salon);
    $otroDia = App\Models\Table::factory()->create(['section_id' => $seccion->id, 'code' => 'S01']);
    $contigua = App\Models\Table::factory()->create(['section_id' => $seccion->id, 'code' => 'S02', 'capacity' => 4]);

    // S01 ocupada el sábado (otro business_date)
    $r1 = App\Models\Reservation::create([
        'business_date' => '2026-08-22',
        'starts_at' => '2026-08-22 20:00:00',
        'ends_at' => '2026-08-22 22:00:00',
        'people_count' => 2,
        'location_id' => $salon->id,
        'section_id' => $seccion->id,
    ]);
    $r1->tables()->attach([$otroDia->id]);

    // S02 con turno que termina justo antes del solicitado (19:59 < 20:00)
    $r2 = App\Models\Reservation::create([
        'business_date' => '2026-08-21',
        'starts_at' => '2026-08-21 18:00:00',
        'ends_at' => '2026-08-21 20:00:00',
        'people_count' => 2,
        'location_id' => $salon->id,
        'section_id' => $seccion->id,
    ]);
    $r2->tables()->attach([$contigua->id]);

    $tables = (new MysqlAvailabilityReader)->availableTables($salon->id, makeSlot('2026-08-21', '20:00', '22:00'), $seccion->id);

    expect(array_map(fn ($t) => $t->code, $tables))->toBe(['S01', 'S02']);
});
