<?php

declare(strict_types=1);

namespace Features\Table\CombinateTable\Application;

use Features\Table\CombinateTable\Domain\Model\AvailableTable;

/**
 * Entrada del caso de uso "Combinar mesas".
 */
final readonly class CombinateTablesCommand
{
    /**
     * @param list<AvailableTable> $availableTables mesas libres para el turno
     * @param int                  $peopleCount    tamaño del grupo
     * @param int                  $maxTables      tope de mesas por combinación
     */
    public function __construct(
        public array $availableTables,
        public int $peopleCount,
        public int $maxTables,
    ) {
    }
}
