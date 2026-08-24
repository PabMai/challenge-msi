<?php

use App\Jobs\CreateReservationJob;
use App\Models\Location;
use App\Models\ReservationAttempt;
use App\Models\Table;
use Features\Reservation\CreateReservation\Application\CreateReservationHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function jobSlot(string $date = '2026-08-21', string $time = '20:00'): array
{
    return [$date, $time];
}

function seedSalon(): Location
{
    $salon = Location::factory()->create(['name' => 'Salón', 'sort_order' => 1]);
    Table::factory()->count(3)->sequence(
        ['code' => 'S01', 'capacity' => 2],
        ['code' => 'S02', 'capacity' => 4],
        ['code' => 'S03', 'capacity' => 4],
    )->create(['location_id' => $salon->id]);

    return $salon;
}

function makePendingAttempt(array $payload): ReservationAttempt
{
    return ReservationAttempt::query()->create(['status' => ReservationAttempt::STATUS_PENDING, 'payload' => $payload]);
}

test('procesa un intento pending y lo marca confirmed con resultado', function () {
    $salon = seedSalon();
    $attempt = makePendingAttempt(['date' => '2026-08-21', 'time' => '20:00', 'people_count' => 6, 'location_id' => $salon->id]);

    $job = new CreateReservationJob($attempt->id, '2026-08-21', '20:00', 6, $salon->id, new DateTimeImmutable('2026-08-21 12:00:00'));
    $out = $job->handle(app(CreateReservationHandler::class));

    $attempt->refresh();
    expect($attempt->status)->toBe(ReservationAttempt::STATUS_CONFIRMED)
        ->and($out['reservation_id'])->toBeInt()
        ->and($out['location_name'])->toBe('Salón')
        ->and($out['table_codes'])->toBe(['S02', 'S03'])
        ->and($out['starts_at'])->toEndWith('20:00:00+00:00');

    expect(DB::table('reservations')->count())->toBe(1)
        ->and(DB::table('reservation_table')->where('reservation_id', $out['reservation_id'])->count())->toBe(2);
});

test('intento ya resuelto se ignora por idempotencia', function () {
    $salon = seedSalon();

    foreach ([
        ReservationAttempt::STATUS_CONFIRMED,
        ReservationAttempt::STATUS_REJECTED,
        ReservationAttempt::STATUS_FAILED,
    ] as $status) {
        $attempt = ReservationAttempt::query()->create([
            'status' => $status,
            'payload' => ['date' => '2026-08-21', 'time' => '20:00', 'people_count' => 2, 'location_id' => $salon->id],
        ]);

        $out = (new CreateReservationJob($attempt->id, '2026-08-21', '20:00', 2, $salon->id, new DateTimeImmutable('2026-08-21 12:00:00')))
            ->handle(app(CreateReservationHandler::class));

        expect($out)->toBeNull();
    }

    expect(DB::table('reservations')->count())->toBe(0)
        ->and(ReservationAttempt::query()->whereKeyNot('x')->count())->toBe(3);
});

test('intento inexistente se omite silenciosamente', function () {
    $salon = seedSalon();

    $out = (new CreateReservationJob(Str::uuid()->toString(), '2026-08-21', '20:00', 4, $salon->id, new DateTimeImmutable('2026-08-21 12:00:00')))
        ->handle(app(CreateReservationHandler::class));

    expect($out)->toBeNull()
        ->and(DB::table('reservations')->count())->toBe(0);
});

test('rechazo de negocio marca rejected sin reintentar', function () {
    $salon = seedSalon();
    // grupo de 10 con max 3 mesas (2+4+4=10 justo)... usamos 12 para exceder capacidad
    $attempt = makePendingAttempt(['date' => '2026-08-21', 'time' => '20:00', 'people_count' => 12, 'location_id' => $salon->id]);

    $out = (new CreateReservationJob($attempt->id, '2026-08-21', '20:00', 12, $salon->id, new DateTimeImmutable('2026-08-21 12:00:00')))
        ->handle(app(CreateReservationHandler::class));

    $attempt->refresh();
    expect($out)->toBeNull()
        ->and($attempt->status)->toBe(ReservationAttempt::STATUS_REJECTED)
        ->and($attempt->error)->not->toBeNull()
        ->and(DB::table('reservations')->count())->toBe(0);
});

test('fuera de horario tambien es rechazo permanente', function () {
    $salon = seedSalon();
    $attempt = makePendingAttempt(['date' => '2026-08-21', 'time' => '23:30', 'people_count' => 2, 'location_id' => $salon->id]);

    (new CreateReservationJob($attempt->id, '2026-08-21', '23:30', 2, $salon->id, new DateTimeImmutable('2026-08-21 12:00:00')))
        ->handle(app(CreateReservationHandler::class));

    $attempt->refresh();
    expect($attempt->status)->toBe(ReservationAttempt::STATUS_REJECTED)
        ->and($attempt->error)->not->toBeNull();
});

test('failed() agota el intento marcandolo failed solo si sigue pending', function () {
    $attempt = makePendingAttempt(['date' => '2026-08-21', 'time' => '20:00', 'people_count' => 2]);

    (new CreateReservationJob($attempt->id, '2026-08-21', '20:00', 2, 1, new DateTimeImmutable('2026-08-21 12:00:00')))
        ->failed(new RuntimeException('boom'));

    $attempt->refresh();
    expect($attempt->status)->toBe(ReservationAttempt::STATUS_FAILED)
        ->and($attempt->error)->toContain('boom');

    // un attempt ya confirmado no debe sobreescribirse
    $ok = makePendingAttempt(['date' => '2026-08-22', 'time' => '20:00', 'people_count' => 2]);
    $ok->forceFill(['status' => ReservationAttempt::STATUS_CONFIRMED])->save();
    (new CreateReservationJob($ok->id, '2026-08-22', '20:00', 2, 1, new DateTimeImmutable('2026-08-21 12:00:00')))->failed(new RuntimeException('tarde'));
    $ok->refresh();
    expect($ok->status)->toBe(ReservationAttempt::STATUS_CONFIRMED);
});

test('configuracion del job: cola tries backoff y middleware', function () {
    $job = new CreateReservationJob(Str::uuid()->toString(), '2026-08-21', '20:00', 4, 1, new DateTimeImmutable('2026-08-21 12:00:00'));

    expect($job->queue)->toBe('reservations')
        ->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([5, 15, 60])
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class);

    Queue::fake();

    $location = Location::factory()->create(['name' => 'Salón']);
    CreateReservationJob::enqueue('2026-08-21', '20:00', 4, $location->id);
    Queue::assertPushedOn('reservations', CreateReservationJob::class);

    $attempt = ReservationAttempt::query()->where('status', ReservationAttempt::STATUS_PENDING)->firstOrFail();
    expect($attempt->payload)->toMatchArray([
        'date' => '2026-08-21',
        'time' => '20:00',
        'people_count' => 4,
        'location_id' => $location->id,
    ]);
});
