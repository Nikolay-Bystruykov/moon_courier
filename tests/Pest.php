<?php

use App\Domain\Lunar\Coordinate;
use App\Domain\Lunar\LunarMap;
use App\Domain\Lunar\RouteFinder;
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

/**
 * Прямая дорога заданной длины по морской равнине. Стоимость такого маршрута
 * равна числу клеток без стартовой, поэтому ожидаемые расходы и время
 * считаются на бумаге и не зависят от генератора карты.
 *
 * @return array{0: LunarMap, 1: App\Domain\Lunar\Route}
 */
function flatCorridor(int $length): array
{
    $map = mapFromRows([str_repeat('.', $length)]);

    return [$map, RouteFinder::find($map, new Coordinate(0, 0), new Coordinate($length - 1, 0))];
}

/**
 * Ровер заданного класса и заведомо подъёмный для него заказ на ближайший
 * аванпост — отправная точка для тестов, которым нужен выполнимый рейс.
 *
 * @return array{0: App\Models\Rover, 1: App\Models\Order}
 */
function nearestOrderFor(App\Models\Game $game, string $roverClass = 'hauler'): array
{
    $rover = $game->rovers()->where('rover_class', $roverClass)->firstOrFail();

    $order = $game->orders()
        ->join('outposts', 'orders.outpost_id', '=', 'outposts.id')
        ->where('orders.status', 'pending')
        ->where('orders.weight_kg', '<=', $rover->capacity_kg)
        ->orderBy('outposts.route_cost')
        ->select('orders.*')
        ->firstOrFail();

    return [$rover, $order];
}
