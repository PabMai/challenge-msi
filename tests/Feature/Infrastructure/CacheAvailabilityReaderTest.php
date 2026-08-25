<?php

use App\Infrastructure\Reservations\CacheAvailabilityReader;
use Features\Reservation\CreateReservation\Application\Port\AvailabilityReaderInterface;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;
use Features\Table\CombinateTable\Domain\Model\AvailableTable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

function cacheTestSlot(): ServiceSlot
{
    return new ServiceSlot(
        new DateTimeImmutable('2026-08-21'),
        new DateTimeImmutable('2026-08-21 20:00:00'),
        new DateTimeImmutable('2026-08-21 22:00:00'),
    );
}

final class CountingInnerReader implements AvailabilityReaderInterface
{
    public int $availableCalls = 0;
    public int $locationCalls = 0;

    public function __construct(
        private readonly array $tables = [],
        private readonly array $locations = [['id' => 1, 'name' => 'Salón']],
    ) {
    }

    public function orderedLocations(): array
    {
        $this->locationCalls++;

        return $this->locations;
    }

    public function availableTables(int $locationId, ServiceSlot $slot, int $sectionId): array
    {
        $this->availableCalls++;

        return $this->tables;
    }
}

beforeEach(function () {
    Cache::store('redis')->flush();
});

test('sirve la segunda lectura desde redis sin golpear mysql', function () {
    $inner = new CountingInnerReader([
        new AvailableTable(10, 'S01', 4),
        new AvailableTable(11, 'S02', 2),
    ]);
    $reader = new CacheAvailabilityReader($inner);
    $slot = cacheTestSlot();

    $first = $reader->availableTables(1, $slot, 10);
    $second = $reader->availableTables(1, $slot, 10);

    expect($inner->availableCalls)->toBe(1)
        ->and($second)->toEqual($first)
        ->and($second)->each->toBeInstanceOf(AvailableTable::class)
        ->and(array_map(fn ($t) => $t->code, $second))->toBe(['S01', 'S02']);
});

test('la clave en redis expira segun el ttl de 120 segundos', function () {
    $reader = new CacheAvailabilityReader(new CountingInnerReader([new AvailableTable(10, 'S01', 4)]));
    $slot = cacheTestSlot();

    $reader->availableTables(1, $slot, 10);

    // phpredis prefija los comandos pero keys() devuelve nombres completos:
    // quitamos el prefijo del cliente para que ttl() lo re-aplique correctamente
    $redis = Redis::connection('cache');
    $clientPrefix = (string) config('database.redis.options.prefix');
    $keys = array_map(
        static fn (string $k): string => str_starts_with($k, $clientPrefix) ? substr($k, strlen($clientPrefix)) : $k,
        $redis->keys('*reservations:availability:1:*'),
    );

    expect($keys)->toHaveCount(1);
    expect($redis->ttl($keys[0]))->toBeGreaterThan(0)->toBeLessThanOrEqual(120);
});

test('secciones distintas del mismo turno no comparten entrada de cache', function () {
    $inner = new CountingInnerReader([new AvailableTable(10, 'S01', 4)]);
    $reader = new CacheAvailabilityReader($inner);
    $slot = cacheTestSlot();

    $reader->availableTables(1, $slot, 10);
    $reader->availableTables(1, $slot, 10);
    $reader->availableTables(1, $slot, 11);

    expect($inner->availableCalls)->toBe(2);

    $keys = Redis::connection('cache')->keys('*reservations:availability:*');
    expect(count($keys))->toBe(2);
});

test('cachea tambien las ubicaciones ordenadas', function () {
    $inner = new CountingInnerReader;

    $reader = new CacheAvailabilityReader($inner);
    $reader->orderedLocations();
    $again = $reader->orderedLocations();

    expect($inner->locationCalls)->toBe(1)
        ->and($again)->toBe([['id' => 1, 'name' => 'Salón']]);
});

test('si redis falla al leer degrada a mysql y no rompe', function () {
    Log::spy();
    $repo = Mockery::mock(Illuminate\Cache\Repository::class);
    $repo->shouldReceive('get')->andThrow(new RuntimeException('redis down'));
    Cache::shouldReceive('store')->with('redis')->andReturn($repo);

    $inner = new CountingInnerReader([new AvailableTable(10, 'S01', 4)]);
    $reader = new CacheAvailabilityReader($inner);

    $tables = $reader->availableTables(1, cacheTestSlot(), 10);

    expect($inner->availableCalls)->toBe(1)
        ->and(array_map(fn ($t) => $t->code, $tables))->toBe(['S01']);
    Log::shouldHaveReceived('warning')->once();
});

test('si redis falla al escribir devuelve igualmente el dato fresco', function () {
    Log::spy();
    $repo = Mockery::mock(Illuminate\Cache\Repository::class);
    $repo->shouldReceive('get')->andReturnNull();
    $repo->shouldReceive('put')->andThrow(new RuntimeException('redis down on write'));
    Cache::shouldReceive('store')->with('redis')->andReturn($repo);

    $inner = new CountingInnerReader([new AvailableTable(10, 'S01', 4)]);
    $reader = new CacheAvailabilityReader($inner);

    $tables = $reader->availableTables(1, cacheTestSlot(), 10);

    expect(array_map(fn ($t) => $t->code, $tables))->toBe(['S01']);
    Log::shouldHaveReceived('warning')->once();
});
