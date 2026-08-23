<?php

use Features\Reservation\ValidateReservation\Domain\Exception\CutoffExceededException;
use Features\Reservation\ValidateReservation\Domain\Exception\OutsideBusinessHoursException;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;
use Features\Reservation\ValidateReservation\Domain\ScheduleValidator;

function scheduleValidator(): ScheduleValidator
{
    $config = Helpers\DomainTest::reservationsConfig();

    return new ScheduleValidator(
        $config['business_hours'],
        $config['duration_minutes'],
        $config['cutoff_minutes'],
    );
}

$now = new DateTimeImmutable('2026-08-21 12:00:00'); // viernes mediodía

test('acepta viernes 20:00 y calcula fin 22:00', function () use ($now) {
    $slot = scheduleValidator()->resolve($now, '2026-08-21', '20:00');

    expect($slot)->toBeInstanceOf(ServiceSlot::class)
        ->and($slot->startsAt->format('D H:i'))->toBe('Fri 20:00')
        ->and($slot->endsAt->format('D H:i'))->toBe('Fri 22:00')
        ->and($slot->businessDateString())->toBe('2026-08-21');
});

test('sabado 23:00 cruza medianoche y termina domingo 01:00', function () use ($now) {
    $slot = scheduleValidator()->resolve($now, '2026-08-22', '23:00');

    expect($slot->startsAt->format('D H:i'))->toBe('Sat 23:00')
        ->and($slot->endsAt->format('D H:i'))->toBe('Sun 01:00');
});

test('normaliza madrugada dominical al servicio del sabado', function () use ($now) {
    $slot = scheduleValidator()->resolve($now, '2026-08-23', '00:00');

    expect($slot->businessDateString())->toBe('2026-08-22') // sábado
        ->and($slot->startsAt->format('D H:i'))->toBe('Sun 00:00')
        ->and($slot->endsAt->format('D H:i'))->toBe('Sun 02:00'); // cierre exacto
});

test('rechaza turno que no cabe completo en la ventana', function (string $date, string $time) use ($now) {
    scheduleValidator()->resolve($now, $date, $time);
})->throws(OutsideBusinessHoursException::class)->with([
    'madrugada dominical excede cierre' => ['2026-08-23', '00:31'],
    'domingo tarde excede cierre' => ['2026-08-23', '14:01'],
]);

test('rechaza horario sin servicio', function () use ($now) {
    scheduleValidator()->resolve($now, '2026-08-21', '09:00'); // viernes abre 10:00
})->throws(OutsideBusinessHoursException::class);

test('rechaza horario fuera del turno del dia', function () use ($now) {
    scheduleValidator()->resolve($now, '2026-08-23', '18:00'); // domingo cierra 16:00
})->throws(OutsideBusinessHoursException::class);

test('rechaza solicitud posterior al cutoff', function () {
    // límite = 21:00 - 15min = 20:45 → 20:46 queda fuera
    scheduleValidator()->resolve(new DateTimeImmutable('2026-08-21 20:46:00'), '2026-08-21', '21:00');
})->throws(CutoffExceededException::class);

test('acepta solicitud justo en el limite del cutoff', function () {
    $slot = scheduleValidator()->resolve(new DateTimeImmutable('2026-08-21 20:45:00'), '2026-08-21', '21:00');

    expect($slot->startsAt->format('H:i'))->toBe('21:00');
});
