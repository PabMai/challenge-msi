<?php

declare(strict_types=1);

namespace Helpers;

/**
 * Utilidades de carga de configuración para la suite Domain.
 */
final class DomainTest
{
    /**
     * Carga config/reservations.php desde la raíz del proyecto,
     * sin depender de la profundidad del archivo que lo invoca.
     *
     * @return array<string, mixed>
     */
    public static function reservationsConfig(): array
    {
        return require dirname(__DIR__).'/config/reservations.php';
    }
}
