<?php

use App\Infrastructure\Reservations\EloquentReservationWriter;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function writeSlot(): ServiceSlot
{
    return new ServiceSlot(
        new DateTimeImmutable('2026-08-21'),
        new DateTimeImmutable('2026-08-21 20:00:00'),
        new DateTimeImmutable('2026-08-21 22:00:00'),
    );
}

test('persiste la reserva y sus mesas de forma atomica', function () {
    $salon = App\Models\Location::factory()->create();
    $s01 = App\Models\Table::factory()->create(['location_id' => $salon->id, 'code' => 'S01']);
    $s02 = App\Models\Table::factory()->create(['location_id' => $salon->id, 'code' => 'S02', 'capacity' => 4]);

    $writer = new EloquentReservationWriter;
    $id = $writer->persist(writeSlot(), 6, $salon->id, [$s01->id, $s02->id]);

    $row = DB::table('reservations')->find($id);
    expect($id)->toBeInt()->toBeGreaterThan(0)
        ->and($row->business_date)->toBe('2026-08-21')
        ->and(substr((string) $row->starts_at, 0, 16))->toBe('2026-08-21 20:00')
        ->and(substr((string) $row->ends_at, 0, 16))->toBe('2026-08-21 22:00')
        ->and($row->people_count)->toBe(6)
        ->and($row->location_id)->toBe($salon->id);

    expect(DB::table('reservation_table')->where('reservation_id', $id)->orderBy('table_id')->pluck('table_id')->all())
        ->toEqualCanonicalizing([$s01->id, $s02->id]);
});

test('reemplaza las mesas asignadas al persistir sobre la misma logica de sync', function () {
    $salon = App\Models\Location::factory()->create();
    $t1 = App\Models\Table::factory()->create(['location_id' => $salon->id, 'code' => 'S01']);
    $t2 = App\Models\Table::factory()->create(['location_id' => $salon->id, 'code' => 'S02', 'capacity' => 4]);

    $writer = new EloquentReservationWriter;
    $first = $writer->persist(writeSlot(), 2, $salon->id, [$t1->id]);
    $second = $writer->persist(writeSlot(), 4, $salon->id, [$t1->id, $t2->id]);

    expect(DB::table('reservation_table')->where('reservation_id', $first)->count())->toBe(1)
        ->and(DB::table('reservation_table')->where('reservation_id', $second)->count())->toBe(2);
});

test('el slot que cruza medianoche se persiste con el business_date del servicio', function () {
    $salon = App\Models\Location::factory()->create();
    $t1 = App\Models\Table::factory()->create(['location_id' => $salon->id]);

    // sábado 23:00 → domingo 01:00: business_date sigue siendo sábado
    $slot = new ServiceSlot(
        new DateTimeImmutable('2026-08-22'),
        new DateTimeImmutable('2026-08-22 23:00:00'),
        new DateTimeImmutable('2026-08-23 01:00:00'),
    );

    $id = (new EloquentReservationWriter)->persist($slot, 2, $salon->id, [$t1->id]);

    $row = DB::table('reservations')->find($id);
    expect($row->business_date)->toBe('2026-08-22')
        ->and(substr((string) $row->ends_at, 0, 16))->toBe('2026-08-23 01:00');
});
