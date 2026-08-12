<?php

use App\Domain\Lunar\Coordinate;
use App\Domain\Lunar\RouteFinder;
use App\Domain\Lunar\Terrain;

it('walks a straight line across flat terrain', function () {
    $map = mapFromRows(['.....']);

    $route = RouteFinder::find($map, new Coordinate(0, 0), new Coordinate(4, 0));

    expect($route->length())->toBe(5);
    // Заряд тратится на въезд в клетку, поэтому стартовая не считается.
    expect($route->cost)->toBe(4.0);
});

it('drives around expensive terrain instead of through it', function () {
    // Напрямую через две клетки борозды — 7.0, в обход по равнине — 5.0.
    // Одна клетка борозды дала бы ровно 4.0 против 4.0, и выбор был бы произволен.
    $map = mapFromRows([
        '.xx.',
        '....',
    ]);

    $route = RouteFinder::find($map, new Coordinate(0, 0), new Coordinate(3, 0));

    $terrains = array_map(fn (Coordinate $c) => $map->at($c), $route->coordinates);

    expect($terrains)->not->toContain(Terrain::Rille);
    expect($route->cost)->toBe(5.0);
    expect($route->length())->toBe(6);
});

it('takes the expensive shortcut when the detour costs more', function () {
    // Объезд перекрыт бороздой со всех сторон — дешевле проехать напрямую.
    $map = mapFromRows([
        '.x.',
        'xxx',
    ]);

    $route = RouteFinder::find($map, new Coordinate(0, 0), new Coordinate(2, 0));

    expect($route->length())->toBe(3);
    expect($route->cost)->toBe(Terrain::Rille->moveCost() + Terrain::Mare->moveCost());
});

it('returns a zero-cost route when start equals destination', function () {
    $map = mapFromRows(['...']);

    $route = RouteFinder::find($map, new Coordinate(1, 0), new Coordinate(1, 0));

    expect($route->cost)->toBe(0.0);
    expect($route->length())->toBe(1);
});

it('returns null when the destination lies outside the map', function () {
    $map = mapFromRows(['...']);

    expect(RouteFinder::find($map, new Coordinate(0, 0), new Coordinate(9, 9)))->toBeNull();
});

it('finds the cheapest of several possible paths', function () {
    // Верхний ряд — кратеры, нижний — равнина: маршрут обязан уйти вниз.
    $map = mapFromRows([
        '.ccc.',
        '.....',
    ]);

    $route = RouteFinder::find($map, new Coordinate(0, 0), new Coordinate(4, 0));

    $terrains = array_map(fn (Coordinate $c) => $map->at($c), $route->coordinates);

    expect($terrains)->not->toContain(Terrain::Crater);
});
