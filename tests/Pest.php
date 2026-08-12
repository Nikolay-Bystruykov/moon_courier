<?php

use App\Domain\Lunar\Coordinate;
use App\Domain\Lunar\LunarMap;
use App\Domain\Lunar\Terrain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Собирает карту из текстовой схемы, где каждый символ — тип местности.
 * Проверять маршруты на нарисованной карте нагляднее, чем на сгенерированной:
 * правильный ответ виден глазами.
 *
 * @param  string[]  $rows
 */
function mapFromRows(array $rows): LunarMap
{
    $legend = [
        '.' => Terrain::Mare,
        'r' => Terrain::Regolith,
        'c' => Terrain::Crater,
        'x' => Terrain::Rille,
        's' => Terrain::Shadow,
    ];

    $tiles = [];

    foreach ($rows as $y => $row) {
        foreach (str_split($row) as $x => $char) {
            $tiles[(new Coordinate($x, $y))->key()] = $legend[$char];
        }
    }

    return new LunarMap(strlen($rows[0]), count($rows), $tiles);
}
