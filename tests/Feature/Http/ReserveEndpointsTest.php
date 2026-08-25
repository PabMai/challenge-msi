<?php

use App\Jobs\CreateReservationJob;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\ReservationAttempt;
use App\Models\Section;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function nextFriday(): string
{
    // viernes futuro garantizado: dentro del horario L-V y sin cutoff vencido
    return Carbon::now()->next(Carbon::FRIDAY)->format('Y-m-d');
}

function reservePayload(array $overrides = []): array
{
    $salon = Location::query()
        ->firstOrCreate(['name' => 'Salón'], ['sort_order' => 1]);

    return array_merge([
        'reservation_date' => nextFriday(),
        'reservation_time' => '20:00',
        'reservation_people_count' => 4,
        'reservation_location' => $salon->id,
        'reservation_section' => Section::query()
            ->firstOrCreate(['location_id' => $salon->id, 'name' => 'Bar'])
            ->id,
    ], $overrides);
}

test('POST /reserve encola el job y devuelve 202 con attempt_url', function () {
    Queue::fake();

    $response = $this->postJson('/api/v1/reserve', reservePayload());

    $response->assertStatus(202)
        ->assertJsonStructure(['attempt_url']);

    $attemptUrl = $response->json('attempt_url');
    expect($attemptUrl)->toMatch('#/reserve/attempts/[0-9a-f-]{36}$#');

    $attemptId = Str::afterLast($attemptUrl, '/');
    expect(ReservationAttempt::query()->find($attemptId))->status->toBe(ReservationAttempt::STATUS_PENDING);

    Queue::assertPushedOn('reservations', CreateReservationJob::class);
});

test('POST /reserve rechaza horario fuera de servicio con mensaje del dominio', function () {
    Queue::fake();

    $this->postJson('/api/v1/reserve', reservePayload(['reservation_time' => '23:30']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('reservation_time')
        ->assertJsonPath('errors.reservation_time.0', fn (string $m) => str_contains($m, 'no cabe completo'));
});

test('POST /reserve rechaza cutoff vencido con mensaje del dominio', function () {
    Queue::fake();

    // fecha pasada dentro del horario: el corte ya está vencido
    $this->postJson('/api/v1/reserve', reservePayload(['reservation_date' => '2026-01-02']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('reservation_time')
        ->assertJsonPath('errors.reservation_time.0', fn (string $m) => str_contains($m, 'corte'));
});

test('POST /reserve valida campos basicos', function () {
    Queue::fake();

    $this->postJson('/api/v1/reserve', reservePayload(['reservation_people_count' => 0]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reservation_people_count']);

    $this->postJson('/api/v1/reserve', reservePayload(['reservation_time' => '7pm']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['reservation_time']);

    $this->postJson('/api/v1/reserve', ['reservation_date' => nextFriday()])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'reservation_time',
            'reservation_people_count',
            'reservation_location',
            'reservation_section',
        ]);
});

test('POST /reserve exige seccion existente y perteneciente a la ubicacion', function () {
    Queue::fake();

    $terraza = Location::factory()->create(['name' => 'Terraza', 'sort_order' => 2]);
    $jardin = Section::query()->create(['location_id' => $terraza->id, 'name' => 'Jardín']);

    // faltante
    $this->postJson('/api/v1/reserve', reservePayload(['reservation_section' => null]))
        ->assertStatus(422)
        ->assertJsonPath('errors.reservation_section.0', 'La sección es obligatoria.');

    // inexistente
    $this->postJson('/api/v1/reserve', reservePayload(['reservation_section' => 99999]))
        ->assertStatus(422)
        ->assertJsonPath('errors.reservation_section.0', 'La sección seleccionada no existe.');

    // de otra ubicación
    $this->postJson('/api/v1/reserve', reservePayload(['reservation_section' => $jardin->id]))
        ->assertStatus(422)
        ->assertJsonPath('errors.reservation_section.0', 'La sección seleccionada no pertenece a la ubicación elegida.');

    // no entero
    $this->postJson('/api/v1/reserve', reservePayload(['reservation_section' => 'bar']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('reservation_section');
});

test('POST /reserve exige ubicacion existente', function () {
    Queue::fake();

    $this->postJson('/api/v1/reserve', reservePayload(['reservation_location' => null]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('reservation_location');

    $this->postJson('/api/v1/reserve', reservePayload(['reservation_location' => 99999]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('reservation_location')
        ->assertJsonPath('errors.reservation_location.0', 'La ubicación seleccionada no existe.');

    $this->postJson('/api/v1/reserve', reservePayload(['reservation_location' => 'salon']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('reservation_location');
});

test('GET attempt devuelve estado pendiente y resuelto', function () {
    $pending = ReservationAttempt::query()->create([
        'status' => ReservationAttempt::STATUS_PENDING,
        'payload' => ['date' => '2026-08-21', 'time' => '20:00', 'people_count' => 2],
    ]);

    $this->getJson("/api/v1/reserve/attempts/{$pending->id}")
        ->assertOk()
        ->assertJsonPath('status', ReservationAttempt::STATUS_PENDING)
        ->assertJsonPath('result', null)
        ->assertJsonPath('error', null);

    $confirmed = ReservationAttempt::query()->create([
        'status' => ReservationAttempt::STATUS_CONFIRMED,
        'payload' => ['date' => '2026-08-21', 'time' => '20:00', 'people_count' => 2],
        'result' => [
            'reservation_id' => 9,
            'location_name' => 'Salón',
            'table_codes' => ['S01'],
        ],
    ]);

    $this->getJson("/api/v1/reserve/attempts/{$confirmed->id}")
        ->assertOk()
        ->assertJsonPath('status', ReservationAttempt::STATUS_CONFIRMED)
        ->assertJsonPath('result.reservation_id', 9)
        ->assertJsonPath('result.table_codes.0', 'S01');
});

test('GET attempt inexistente devuelve 404', function () {
    $this->getJson('/api/v1/reserve/attempts/'.Str::uuid()->toString())->assertNotFound();
});

test('GET attempt con uuid invalido devuelve 404', function () {
    $this->getJson('/api/v1/reserve/attempts/not-a-uuid')->assertNotFound();
});

test('GET /reservations lista las reservas de una fecha con ubicacion y mesas', function () {
    $salon = Location::factory()->create(['name' => 'Salón', 'sort_order' => 1]);
    $seccion = Section::query()->create(['location_id' => $salon->id, 'name' => 'Salón Principal']);
    $t1 = Table::factory()->create(['section_id' => $seccion->id, 'code' => 'S01']);
    $t2 = Table::factory()->create(['section_id' => $seccion->id, 'code' => 'S02', 'capacity' => 4]);

    $r = Reservation::create([
        'business_date' => '2026-08-21',
        'starts_at' => '2026-08-21 20:00:00',
        'ends_at' => '2026-08-21 22:00:00',
        'people_count' => 6,
        'location_id' => $salon->id,
        'section_id' => $seccion->id,
    ]);
    $r->tables()->attach([$t1->id, $t2->id]);

    Reservation::create([
        'business_date' => '2026-08-22', // otra fecha: no debe salir
        'starts_at' => '2026-08-22 20:00:00',
        'ends_at' => '2026-08-22 22:00:00',
        'people_count' => 2,
        'location_id' => $salon->id,
        'section_id' => $seccion->id,
    ]);

    $this->getJson('/api/v1/reservations?date=2026-08-21')
        ->assertOk()
        ->assertJsonPath('date', '2026-08-21')
        ->assertJsonPath('total', 1)
        ->assertJsonPath('reservations.0.people_count', 6)
        ->assertJsonPath('reservations.0.starts_at', '20:00')
        ->assertJsonPath('reservations.0.location.name', 'Salón')
        ->assertJsonCount(2, 'reservations.0.tables')
        ->assertJsonPath('reservations.0.tables.0.code', 'S01');
});

test('GET /reservations sin fecha devuelve 422', function () {
    $this->getJson('/api/v1/reservations')->assertStatus(422);
});

test('POST /reserve aplica throttle de 30 por minuto', function () {
    Queue::fake();

    foreach (range(1, 30) as $i) {
        $this->postJson('/api/v1/reserve', reservePayload(['reservation_time' => sprintf('%02d:00', 10 + ($i % 10))]));
    }

    $this->postJson('/api/v1/reserve', reservePayload())
        ->assertStatus(429);
});
