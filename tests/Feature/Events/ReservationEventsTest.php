<?php

use App\Events\Reservations\ReservationConfirmed;
use App\Events\Reservations\ReservationRejected;
use App\Jobs\CreateReservationJob;
use App\Listeners\Reservations\InvalidateAvailabilityCache;
use App\Listeners\Reservations\LogRejectedReason;
use App\Models\Location;
use App\Models\ReservationAttempt;
use App\Models\Table;
use Features\Reservation\CreateReservation\Application\CreateReservationHandler;
use Features\Reservation\CreateReservation\Application\Port\AvailabilityReaderInterface;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::store('redis')->flush();
});

function eventsSalon(): Location
{
    $salon = Location::factory()->create(['name' => 'Salón', 'sort_order' => 1]);
    Location::factory()->create(['name' => 'Terraza', 'sort_order' => 2]);
    $section = \App\Models\Section::query()->create(['location_id' => $salon->id, 'name' => 'Bar']);
    Table::factory()->create(['location_id' => $salon->id, 'section_id' => $section->id, 'code' => 'S01']);

    return $salon;
}

function eventsSlot(): ServiceSlot
{
    return new ServiceSlot(
        new DateTimeImmutable('2026-08-21'),
        new DateTimeImmutable('2026-08-21 20:00:00'),
        new DateTimeImmutable('2026-08-21 22:00:00'),
    );
}

function availabilityKeysCount(): int
{
    return count(Redis::connection('cache')->keys('*reservations:availability:*'));
}

test('confirmar reserva invalida la cache de disponibilidad del turno', function () {
    $salon = eventsSalon();
    $section = \App\Models\Section::query()->where('location_id', $salon->id)->firstOrFail();
    $reader = app(AvailabilityReaderInterface::class);

    // calienta la cache para el turno (MySQL + Redis) con seccion concreta
    $reader->availableTables($salon->id, eventsSlot(), $section->id);
    expect(availabilityKeysCount())->toBeGreaterThan(0);

    $attempt = ReservationAttempt::query()->create([
        'status' => ReservationAttempt::STATUS_PENDING,
        'payload' => ['date' => '2026-08-21', 'time' => '20:00', 'people_count' => 2],
    ]);

    $job = new CreateReservationJob($attempt->id, '2026-08-21', '20:00', 2, $salon->id, new DateTimeImmutable('2026-08-21 12:00:00'), $section->id);
    $job->handle(app(CreateReservationHandler::class));

    $attempt->refresh();
    expect($attempt->status)->toBe(ReservationAttempt::STATUS_CONFIRMED)
        ->and(availabilityKeysCount())->toBe(0);
});

test('registro: cache sincrono y rechazo con log', function () {
    Event::fake();

    Event::assertListening(ReservationConfirmed::class, InvalidateAvailabilityCache::class);
    Event::assertListening(ReservationRejected::class, LogRejectedReason::class);

    // el de cache corre síncrono (sin ShouldQueue): la invalidación no puede esperar a la cola
    $cacheRef = new ReflectionClass(InvalidateAvailabilityCache::class);

    expect($cacheRef->implementsInterface(ShouldQueue::class))->toBeFalse();
});

test('el rechazo registra el motivo con contexto de la solicitud', function () {
    Log::spy();
    $salon = eventsSalon();

    $attempt = ReservationAttempt::query()->create([
        'status' => ReservationAttempt::STATUS_PENDING,
        'payload' => ['date' => '2026-08-21', 'time' => '23:30', 'people_count' => 2, 'location_id' => $salon->id],
    ]);

    (new CreateReservationJob($attempt->id, '2026-08-21', '23:30', 2, $salon->id, new DateTimeImmutable('2026-08-21 12:00:00')))
        ->handle(app(CreateReservationHandler::class));

    $attempt->refresh();
    expect($attempt->status)->toBe(ReservationAttempt::STATUS_REJECTED);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => str_contains($message, 'rechazada')
            && $context['reason'] !== ''
            && $context['requested']['time'] === '23:30'
            && $context['requested']['people_count'] === 2)
        ->once();
});
