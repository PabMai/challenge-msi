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
 * - TTL de 120 s por clave (ubicación + turno + sección).
 * - Solo cachea lecturas con sección concreta: cada clave corresponde a
 *   un contenido real; sin sección se lee directo de MySQL.
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

    public function availableTables(int $locationId, ServiceSlot $slot, ?int $sectionId = null): array
    {
        // Sin sección concreta no se carga disponibilidad: la lectura exige
        // una sección real, que es además lo único que se cachea.
        if ($sectionId === null) {
            return [];
        }

        $key = sprintf(
            'reservations:availability:%d:%s:%s-%s:%d',
            $locationId,
            $slot->businessDateString(),
            $slot->startsAt->format('Hi'),
            $slot->endsAt->format('Hi'),
            $sectionId,
        );

        /** @var list<AvailableTable> */
        return $this->remember($key, fn (): array => $this->inner->availableTables($locationId, $slot, $sectionId));
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
