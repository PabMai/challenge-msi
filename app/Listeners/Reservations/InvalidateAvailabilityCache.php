<?php

declare(strict_types=1);

namespace App\Listeners\Reservations;

use App\Events\Reservations\ReservationConfirmed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Listener síncrono: invalida la cache de disponibilidad del turno
 * confirmado para todas las ubicaciones y secciones, de modo que la
 * siguiente lectura recalcula contra MySQL.
 *
 * Borra por patrón (y no por clave exacta) porque las claves incluyen
 * la sección elegida o el comodín "all". El comodín inicial cubre el
 * prefijo que agrega la store de cache entre el prefijo de conexión
 * y el nombre lógico de la clave.
 */
final class InvalidateAvailabilityCache
{
    private const PATTERN = '*reservations:availability:*';

    public function handle(ReservationConfirmed $event): void
    {
        $startHi = Carbon::parse($event->startsAt)->format('Hi');
        $endHi = Carbon::parse($event->endsAt)->format('Hi');

        try {
            // keys() devuelve nombres con el prefijo del cliente phpredis;
            // se lo quitamos para que del() lo re-aplique correctamente.
            $redis = Redis::connection('cache');
            $clientPrefix = (string) config('database.redis.options.prefix');
            $keys = array_map(
                static fn (string $key): string => str_starts_with($key, $clientPrefix)
                    ? substr($key, strlen($clientPrefix))
                    : $key,
                $redis->keys(self::PATTERN),
            );

            if ($keys !== []) {
                $redis->del($keys);
            }
        } catch (Throwable $e) {
            Log::warning('Redis no disponible; no se pudo invalidar cache de disponibilidad', [
                'error' => $e->getMessage(),
            ]);

            return;
        }

        Log::info('Cache de disponibilidad invalidada', [
            'reservation_id' => $event->reservationId,
            'business_date' => $event->businessDate,
            'slot' => "{$startHi}-{$endHi}",
            'keys' => count($keys),
        ]);
    }
}
