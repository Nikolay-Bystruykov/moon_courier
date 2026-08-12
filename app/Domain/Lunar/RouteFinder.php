<?php

namespace App\Domain\Lunar;

use SplPriorityQueue;

/**
 * Поиск самого дешёвого маршрута алгоритмом Дейкстры. Стоимость входа в клетку
 * задаётся её местностью, поэтому объезд борозды по равнине выходит дешевле
 * прямой линии — ради этого зоны на карте и различаются.
 */
class RouteFinder
{
    public static function find(LunarMap $map, Coordinate $from, Coordinate $to): ?Route
    {
        if (! $map->contains($from) || ! $map->contains($to)) {
            return null;
        }

        $distances = [$from->key() => 0.0];
        $previous = [];
        $settled = [];

        $queue = new SplPriorityQueue();
        $queue->insert($from, 0.0);

        while (! $queue->isEmpty()) {
            /** @var Coordinate $current */
            $current = $queue->extract();
            $key = $current->key();

            if (isset($settled[$key])) {
                continue;
            }

            $settled[$key] = true;

            if ($current->equals($to)) {
                return self::buildRoute($previous, $from, $to, $distances[$key]);
            }

            foreach ($map->neighbours($current) as $neighbour) {
                $neighbourKey = $neighbour->key();

                if (isset($settled[$neighbourKey])) {
                    continue;
                }

                $candidate = $distances[$key] + $map->at($neighbour)->moveCost();

                if (! isset($distances[$neighbourKey]) || $candidate < $distances[$neighbourKey]) {
                    $distances[$neighbourKey] = $candidate;
                    $previous[$neighbourKey] = $current;
                    // Очередь отдаёт наибольший приоритет первым, поэтому знак обратный.
                    $queue->insert($neighbour, -$candidate);
                }
            }
        }

        return null;
    }

    /** @param  array<string, Coordinate>  $previous */
    private static function buildRoute(array $previous, Coordinate $from, Coordinate $to, float $cost): Route
    {
        $path = [$to];
        $cursor = $to;

        while (! $cursor->equals($from)) {
            $cursor = $previous[$cursor->key()];
            array_unshift($path, $cursor);
        }

        return new Route($path, $cost);
    }
}
