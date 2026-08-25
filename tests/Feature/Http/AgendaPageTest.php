<?php

use App\Infrastructure\Reservations\DailyAgendaQuery;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\Section;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function agendaPageReservation(
    Location $location,
    Section $section,
    array $tables,
    string $date,
    string $start,
    int $peopleCount,
): Reservation {
    $reservation = Reservation::create([
        'business_date' => $date,
        'starts_at' => "$date $start:00",
        'ends_at' => "$date 22:00:00",
        'people_count' => $peopleCount,
        'location_id' => $location->id,
        'section_id' => $section->id,
    ]);
    $reservation->tables()->attach(array_map(static fn (Table $t) => $t->id, $tables));

    return $reservation;
}

test('muestra todas las reservas de todas las fechas sin filtro', function () {
    $salon = Location::factory()->create(['name' => 'Salón', 'sort_order' => 1]);
    $terraza = Location::factory()->create(['name' => 'Terraza', 'sort_order' => 2]);
    $principal = Section::query()->create(['location_id' => $salon->id, 'name' => 'Salón Principal']);
    $jardin = Section::query()->create(['location_id' => $terraza->id, 'name' => 'Jardín']);

    agendaPageReservation($salon, $principal, [
        Table::factory()->create(['section_id' => $principal->id, 'code' => 'S02', 'capacity' => 4]),
        Table::factory()->create(['section_id' => $principal->id, 'code' => 'S03', 'capacity' => 8]),
    ], '2026-08-21', '20:00', 6);

    agendaPageReservation(
        $terraza,
        $jardin,
        [Table::factory()->create(['section_id' => $jardin->id, 'code' => 'T01'])],
        '2026-08-28',
        '19:00',
        4,
    );

    $this->get('/agenda')
        ->assertOk()
        ->assertSee('Agenda de reservas')
        ->assertSee('2026-08-21')
        ->assertSee('2026-08-28')
        ->assertSee('20:00–22:00')
        ->assertSee('19:00–22:00')
        ->assertSee('Salón Principal')
        ->assertSee('Jardín')
        ->assertSee('S02, S03')
        ->assertSee('T01')
        ->assertSee('>2<', false); // badge con el total
});

test('agenda vacia muestra mensaje sin tabla', function () {
    $this->get('/agenda')
        ->assertOk()
        ->assertSee('No hay reservas registradas.')
        ->assertDontSee('<table', false);
});

test('el navbar enlaza a crear reserva y agenda con la pagina activa marcada', function () {
    $this->get('/agenda')
        ->assertOk()
        ->assertSee(route('home'))
        ->assertSee(route('agenda'))
        ->assertSee('Crear Reserva')
        ->assertSeeInOrder(['Crear Reserva', 'Agenda']);

    // en /agenda el link activo es Agenda
    $this->get('/agenda')
        ->assertOk()
        ->assertSee('btn-outline-light active', false);
});
