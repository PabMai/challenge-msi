<?php

use Features\Table\CombinateTable\Domain\Model\AvailableTable;
use Features\Table\CombinateTable\Domain\TableCombinator;

function makeTables(array $specs): array
{
    return array_map(
        static fn (array $s) => new AvailableTable($s[0], $s[1], $s[2]),
        $specs,
    );
}

function codes(?array $combination): ?array
{
    return $combination === null ? null : array_map(static fn ($t) => $t->code, $combination);
}

test('usa una sola mesa cuando una cubre a todos', function () {
    $mesas = makeTables([[1, 'S01', 2], [2, 'S02', 4], [3, 'S03', 4]]);

    expect(codes((new TableCombinator)->combine($mesas, 3, 3)))->toBe(['S02']);
});

test('combina el minimo de mesas necesarias', function () {
    $mesas = makeTables([[1, 'S01', 2], [2, 'S02', 4], [3, 'S03', 4], [4, 'S04', 6]]);

    expect(codes((new TableCombinator)->combine($mesas, 7, 3)))->toBe(['S04', 'S02']);
});

test('devuelve null si no alcanza la capacidad total', function () {
    $mesas = makeTables([[1, 'S01', 2], [2, 'S02', 4], [3, 'S03', 4]]);

    expect((new TableCombinator)->combine($mesas, 12, 3))->toBeNull();
});

test('devuelve null si excede el maximo de mesas combinables', function () {
    $mesas = makeTables([
        [1, 'S01', 2], [2, 'S02', 2], [3, 'S03', 2],
        [4, 'S04', 2], [5, 'S05', 2],
    ]);

    // 10 personas requieren 5 mesas de 2 → supera maxTables=3
    expect((new TableCombinator)->combine($mesas, 10, 3))->toBeNull();
});

test('recorta mesas sobrantes minimizando desperdicio', function () {
    $mesas = makeTables([[1, 'T01', 8], [2, 'T02', 4], [3, 'T03', 4], [4, 'T04', 2]]);

    // Greedy toma 8+4=12, recorta el 4 → queda solo la de 8 para 6 personas
    expect(codes((new TableCombinator)->combine($mesas, 6, 3)))->toBe(['T01']);
});
