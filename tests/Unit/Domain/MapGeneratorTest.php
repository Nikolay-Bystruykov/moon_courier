<?php

use App\Domain\Lunar\Coordinate;
use App\Domain\Lunar\MapGenerator;
use App\Domain\Lunar\Rules;
use App\Domain\Lunar\SeededRandom;
use App\Domain\Lunar\Terrain;

it('fills every cell of the grid', function () {
    $map = MapGenerator::generate(new SeededRandom(1));

    expect($map->width())->toBe(Rules::MAP_WIDTH);
    expect($map->height())->toBe(Rules::MAP_HEIGHT);
    expect($map->all())->toHaveCount(Rules::MAP_WIDTH * Rules::MAP_HEIGHT);
});

it('produces the same map for the same seed', function () {
    expect(MapGenerator::generate(new SeededRandom(99))->all())
        ->toBe(MapGenerator::generate(new SeededRandom(99))->all());
});

it('produces different maps for different seeds', function () {
    expect(MapGenerator::generate(new SeededRandom(1))->all())
        ->not->toBe(MapGenerator::generate(new SeededRandom(2))->all());
});

it('uses more than two kinds of terrain', function () {
    $map = MapGenerator::generate(new SeededRandom(5));

    $kinds = array_unique(array_map(fn (Terrain $t) => $t->value, $map->all()));

    expect(count($kinds))->toBeGreaterThan(2);
});

it('keeps the base tile drivable', function () {
    // База на дорогой местности дала бы каждому рейсу незаслуженную надбавку.
    foreach ([1, 2, 3, 50, 777] as $seed) {
        $map = MapGenerator::generate(new SeededRandom($seed));

        expect($map->at(new Coordinate(Rules::BASE_X, Rules::BASE_Y)))->toBe(Terrain::Mare);
    }
});

it('leaves most of the surface passable', function () {
    $map = MapGenerator::generate(new SeededRandom(11));

    $rough = array_filter(
        $map->all(),
        fn (Terrain $t) => $t === Terrain::Rille || $t === Terrain::Crater,
    );

    // Если труднопроходимой окажется половина карты, объезды перестанут работать.
    expect(count($rough) / count($map->all()))->toBeLessThan(0.5);
});
