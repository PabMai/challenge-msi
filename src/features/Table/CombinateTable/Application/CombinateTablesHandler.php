<?php

declare(strict_types=1);

namespace Features\Table\CombinateTable\Application;

use Features\Table\CombinateTable\Domain\Model\AvailableTable;
use Features\Table\CombinateTable\Domain\TableCombinator;

/**
 * Orquestador del caso de uso "Combinar mesas".
 *
 * API pública de la feature: otras features (p.ej. CreateReservation)
 * consumen la combinación de mesas a través de este handler.
 *
 * @return list<AvailableTable>|null null si ninguna combinación cubre al grupo
 */
final class CombinateTablesHandler
{
    public function __construct(
        private readonly TableCombinator $combinator,
    ) {
    }

    public function handle(CombinateTablesCommand $command): ?array
    {
        return $this->combinator->combine(
            $command->availableTables,
            $command->peopleCount,
            $command->maxTables,
        );
    }
}
