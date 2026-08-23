<?php

namespace App\Providers;

use App\Infrastructure\Reservations\CacheAvailabilityReader;
use App\Infrastructure\Reservations\EloquentReservationWriter;
use App\Infrastructure\Reservations\MysqlAvailabilityReader;
use Features\Reservation\CreateReservation\Application\Port\AvailabilityReaderInterface;
use Features\Reservation\CreateReservation\Application\Port\ReservationWriterInterface;
use Features\Reservation\ValidateReservation\Application\ValidateReservationHandler;
use Features\Reservation\ValidateReservation\Domain\ScheduleValidator;
use Features\Table\CombinateTable\Application\CombinateTablesHandler;
use Features\Table\CombinateTable\Domain\TableCombinator;
use Illuminate\Contracts\Foundation\Application;
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
        $this->app->singleton(ValidateReservationHandler::class, static fn (Application $app): ValidateReservationHandler => new ValidateReservationHandler(
            new ScheduleValidator(
                config('reservations.business_hours'),
                config('reservations.duration_minutes'),
                config('reservations.cutoff_minutes'),
            ),
        ));

        $this->app->singleton(CombinateTablesHandler::class, static fn (): CombinateTablesHandler => new CombinateTablesHandler(
            new TableCombinator,
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
