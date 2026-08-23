<?php

namespace App\Providers;

use App\Events\Reservations\ReservationConfirmed;
use App\Events\Reservations\ReservationRejected;
use App\Infrastructure\Reservations\CacheAvailabilityReader;
use App\Infrastructure\Reservations\EloquentReservationWriter;
use App\Infrastructure\Reservations\MysqlAvailabilityReader;
use App\Listeners\Reservations\InvalidateAvailabilityCache;
use App\Listeners\Reservations\LogRejectedReason;
use Features\Reservation\CreateReservation\Application\CreateReservationHandler;
use Features\Reservation\CreateReservation\Application\Port\AvailabilityReaderInterface;
use Features\Reservation\CreateReservation\Application\Port\ReservationWriterInterface;
use Features\Reservation\ValidateReservation\Application\ValidateReservationHandler;
use Features\Reservation\ValidateReservation\Domain\ScheduleValidator;
use Features\Table\CombinateTable\Application\CombinateTablesHandler;
use Features\Table\CombinateTable\Domain\TableCombinator;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Puertos de la feature CreateReservation -> adaptadores de infraestructura.
        $this->app->bind(AvailabilityReaderInterface::class, static fn (): AvailabilityReaderInterface => new CacheAvailabilityReader(
            new MysqlAvailabilityReader,
        ));

        $this->app->bind(ReservationWriterInterface::class, EloquentReservationWriter::class);

        // APIs públicas de features de dominio (sin estado: singleton).
        $this->app->singleton(ScheduleValidator::class, static fn (): ScheduleValidator => new ScheduleValidator(
            config('reservations.business_hours'),
            config('reservations.duration_minutes'),
            config('reservations.cutoff_minutes'),
        ));

        $this->app->singleton(ValidateReservationHandler::class, static fn (Application $app): ValidateReservationHandler => new ValidateReservationHandler(
            $app->make(ScheduleValidator::class),
        ));

        $this->app->singleton(CombinateTablesHandler::class, static fn (): CombinateTablesHandler => new CombinateTablesHandler(
            new TableCombinator,
        ));

        // Orquestador de la feature: compone las APIs públicas + puertos + config.
        $this->app->bind(CreateReservationHandler::class, static fn (Application $app): CreateReservationHandler => new CreateReservationHandler(
            $app->make(ValidateReservationHandler::class),
            $app->make(CombinateTablesHandler::class),
            $app->make(AvailabilityReaderInterface::class),
            $app->make(ReservationWriterInterface::class),
            (int) config('reservations.max_tables_per_reservation'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Eventos del dominio de reservas -> listeners (sync y en cola).
        Event::listen(ReservationConfirmed::class, InvalidateAvailabilityCache::class);
        Event::listen(ReservationRejected::class, LogRejectedReason::class);
    }
}
