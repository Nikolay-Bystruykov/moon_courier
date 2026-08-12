<?php

namespace App\Services;

use App\Domain\Lunar\Coordinate;
use App\Domain\Lunar\LunarMap;
use App\Domain\Lunar\Rules;
use App\Models\Game;
use App\Models\MapTile;

/**
 * Единственное место, где карта переходит из базы в доменный вид и обратно.
 */
class MapRepository
{
    public function load(Game $game): LunarMap
    {
        $tiles = [];

        foreach ($game->tiles as $tile) {
            $tiles[(new Coordinate($tile->x, $tile->y))->key()] = $tile->terrain;
        }

        return new LunarMap(Rules::MAP_WIDTH, Rules::MAP_HEIGHT, $tiles);
    }

    public function store(Game $game, LunarMap $map): void
    {
        $rows = [];

        foreach ($map->all() as $key => $terrain) {
            [$x, $y] = explode(':', $key);

            $rows[] = [
                'game_id' => $game->id,
                'x' => (int) $x,
                'y' => (int) $y,
                'terrain' => $terrain->value,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            MapTile::insert($chunk);
        }
    }
}
