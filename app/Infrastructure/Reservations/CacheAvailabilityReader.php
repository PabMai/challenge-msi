<?php

declare(strict_types=1);

namespace App\Infrastructure\Reservations;

use Features\Reservation\CreateReservation\Application\Port\AvailabilityReaderInterface;
use Features\Reservation\ValidateReservation\Domain\Model\ServiceSlot;
use Features\Table\CombinateTable\Domain\Model\AvailableTable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decorador Redis del puerto de disponibilidad.
 *
 * - TTL de 120 s por clave (ubicación + turno).
 * - Si Redis falla (caída, timeout), degrada a MySQL sin romper la petición
 *   y lo registra en el log.
 */
final class CacheAvailabilityReader implements AvailabilityReaderInterface
{
    private const TTL_SECONDS = 120;

    public function __construct(
        private readonly AvailabilityReaderInterface $inner,
    ) {
    }

    public function orderedLocations(): array
    {
        return $this->remember(
            'reservations:locations',
            fn (): array => $this->inner->orderedLocations(),
        );
    }

    public function availableTables(int $locationId, ServiceSlot $slot): array
    {
        $key = sprintf(
            'reservations:availability:%d:%s:%s-%s',
            $locationId,
            $slot->businessDateString(),
            $slot->startsAt->format('Hi'),
            $slot->endsAt->format('Hi'),
        );

        /** @var list<AvailableTable> */
        return $this->remember($key, function () use ($locationId, $slot): array {
            return $this->inner->availableTables($locationId, $slot);
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $produce
     * @return T
     */
    private function remember(string $key, callable $produce): mixed
    {
        try {
            $hit = Cache::store('redis')->get($key);
        } catch (Throwable $e) {
            Log::warning('Redis no disponible; leyendo disponibilidad desde MySQL', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return $produce();
        }

        if ($hit !== null) {
            return $this->hydrate($hit);
        }

        $fresh = $produce();

        try {
            Cache::store('redis')->put($key, $this->dehydrate($fresh), self::TTL_SECONDS);
        } catch (Throwable $e) {
            Log::warning('Redis no disponible; no se pudo cachear disponibilidad', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }

        return $fresh;
    }

    /**
     * AvailableTable es readonly: se serializa como array plano.
     *
     * @template T
     *
     * @param  T  $value
     * @return T
     */
    private function dehydrate(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_map(
            static fn ($item) => $item instanceof AvailableTable
                ? ['id' => $item->id, 'code' => $item->code, 'capacity' => $item->capacity]
                : $item,
            $value,
        );
    }

    /**
     * @template T
     *
     * @param  T  $cached
     * @return T
     */
    private function hydrate(mixed $cached): mixed
    {
        if (! is_array($cached) || ! isset($cached[0]) || ! is_array($cached[0])) {
            return $cached;
        }

        if (! array_is_list($cached) || ! isset($cached[0]['id'], $cached[0]['code'], $cached[0]['capacity'])) {
            return $cached;
        }

        return array_map(
            static fn (array $row) => new AvailableTable($row['id'], $row['code'], $row['capacity']),
            $cached,
        );
    }
}
