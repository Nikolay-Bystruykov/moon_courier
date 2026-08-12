<?php

namespace App\Domain\Lunar;

/**
 * Карта собирается из пятен: сначала всё поле — морская равнина, затем на неё
 * набрасываются области реголита, кратеров, борозд и вечной тени.
 *
 * Пятна дают связные зоны, вокруг которых имеет смысл строить объезды.
 * Равномерная россыпь отдельных клеток такого не даёт: маршрут всё равно
 * получается почти прямым, и разница между типами местности пропадает.
 */
class MapGenerator
{
    /** @var array<string, array{count: int, minRadius: int, maxRadius: int}> */
    private const BLOBS = [
        'regolith' => ['count' => 14, 'minRadius' => 2, 'maxRadius' => 3],
        'crater' => ['count' => 7, 'minRadius' => 1, 'maxRadius' => 2],
        'rille' => ['count' => 5, 'minRadius' => 1, 'maxRadius' => 2],
        'shadow' => ['count' => 4, 'minRadius' => 1, 'maxRadius' => 2],
    ];

    public static function generate(SeededRandom $rng): LunarMap
    {
        $tiles = [];

        for ($y = 0; $y < Rules::MAP_HEIGHT; $y++) {
            for ($x = 0; $x < Rules::MAP_WIDTH; $x++) {
                $tiles[(new Coordinate($x, $y))->key()] = Terrain::Mare;
            }
        }

        foreach (self::BLOBS as $value => $blob) {
            $terrain = Terrain::from($value);

            for ($i = 0; $i < $blob['count']; $i++) {
                $centre = new Coordinate(
                    $rng->nextInt(0, Rules::MAP_WIDTH - 1),
                    $rng->nextInt(0, Rules::MAP_HEIGHT - 1),
                );

                self::paint($tiles, $centre, $rng->nextInt($blob['minRadius'], $blob['maxRadius']), $terrain);
            }
        }

        // База обязана стоять на проезжей клетке: иначе каждый рейс получал бы
        // надбавку к расходу и риску ещё до выезда.
        $tiles[(new Coordinate(Rules::BASE_X, Rules::BASE_Y))->key()] = Terrain::Mare;

        return new LunarMap(Rules::MAP_WIDTH, Rules::MAP_HEIGHT, $tiles);
    }

    /** @param  array<string, Terrain>  $tiles */
    private static function paint(array &$tiles, Coordinate $centre, int $radius, Terrain $terrain): void
    {
        for ($dy = -$radius; $dy <= $radius; $dy++) {
            for ($dx = -$radius; $dx <= $radius; $dx++) {
                // Ромб вместо квадрата: у пятна нет прямых углов.
                if (abs($dx) + abs($dy) > $radius) {
                    continue;
                }

                $key = (new Coordinate($centre->x + $dx, $centre->y + $dy))->key();

                if (isset($tiles[$key])) {
                    $tiles[$key] = $terrain;
                }
            }
        }
    }
}
