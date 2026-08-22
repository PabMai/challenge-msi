<?php

declare(strict_types=1);

namespace Features\Table\CombinateTable\Domain\Model;

/**
 * Mesa disponible en una ubicación para un turno concreto.
 */
final readonly class AvailableTable
{
    public function __construct(
        public int $id,
        public string $code,
        public int $capacity,
    ) {
    }
}
