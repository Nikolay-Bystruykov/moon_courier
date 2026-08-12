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
 * Пара «ровер и заявка», для которой рейс заведомо допустим.
 *
 * Состав заявок зависит от зерна партии, поэтому пара подбирается проверкой
 * через сам сервис, а не угадывается по весу: иначе тест ломался бы на
 * партиях, где все заявки оказались тяжёлыми.
 *
 * @return array{0: App\Models\Rover, 1: App\Models\Order}
 */
function nearestOrderFor(App\Models\Game $game, ?string $roverClass = null): array
{
    $missions = app(App\Services\MissionService::class);

    $rovers = $game->rovers()
        ->when($roverClass, fn ($query) => $query->where('rover_class', $roverClass))
        ->get();

    $orders = $game->orders()
        ->with('outpost')
        ->join('outposts', 'orders.outpost_id', '=', 'outposts.id')
        ->where('orders.status', 'pending')
        ->orderBy('outposts.route_cost')
        ->select('orders.*')
        ->get();

    foreach ($orders as $order) {
        foreach ($rovers as $rover) {
            if ($missions->plan($game, $rover, $order)->validation->allowed) {
                return [$rover, $order];
            }
        }
    }

    throw new RuntimeException('В партии нет ни одной выполнимой пары ровер—заявка');
}
