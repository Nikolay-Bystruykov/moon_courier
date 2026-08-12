<?php

use App\Domain\Lunar\Coordinate;
use App\Domain\Lunar\RouteFinder;
use App\Domain\Lunar\Rules;
use App\Models\Game;
use App\Services\GameFactory;
use App\Services\MapRepository;

it('creates a game with map, outposts, rovers and orders', function () {
    $game = app(GameFactory::class)->create(seed: 12345);

    expect($game->day)->toBe(1);
    expect($game->credits)->toBe(Rules::START_CREDITS);
    expect($game->reputation)->toBe(Rules::START_REPUTATION);
    expect($game->status)->toBe('active');

    expect($game->tiles()->count())->toBe(Rules::MAP_WIDTH * Rules::MAP_HEIGHT);
    expect($game->outposts()->count())->toBe(Rules::OUTPOST_COUNT);
    expect($game->rovers()->count())->toBe(3);
    expect($game->orders()->count())->toBeGreaterThanOrEqual(Rules::ORDERS_PER_DAY_MIN);
});

it('reproduces the same game from the same seed', function () {
    $first = app(GameFactory::class)->create(seed: 777);
    $second = app(GameFactory::class)->create(seed: 777);

    $terrainOf = fn (Game $game) => $game->tiles()->orderBy('x')->orderBy('y')->pluck('terrain')->all();
    $outpostsOf = fn (Game $game) => $game->outposts()->orderBy('name')->get(['name', 'x', 'y'])->toArray();

    expect($terrainOf($first))->toBe($terrainOf($second));
    expect($outpostsOf($first))->toBe($outpostsOf($second));
});

it('places every outpost within the allowed cost band', function () {
    foreach ([1, 42, 4242, 90210] as $seed) {
        $game = app(GameFactory::class)->create(seed: $seed);
        $map = app(MapRepository::class)->load($game);
        $base = new Coordinate(Rules::BASE_X, Rules::BASE_Y);

        expect($game->outposts()->count())->toBe(Rules::OUTPOST_COUNT);

        foreach ($game->outposts as $outpost) {
            $route = RouteFinder::find($map, $base, new Coordinate($outpost->x, $outpost->y));

            expect($route)->not->toBeNull();
            expect($route->cost)->toBeGreaterThanOrEqual(Rules::MIN_OUTPOST_COST);
            expect($route->cost)->toBeLessThanOrEqual(Rules::MAX_OUTPOST_COST);
            // Сохранённая стоимость обязана совпадать с пересчитанной,
            // иначе награды разойдутся с реальной дальностью.
            expect($outpost->route_cost)->toEqualWithDelta($route->cost, 0.01);
        }
    }
});

it('spreads outposts across near and far distance bands', function () {
    // Если все аванпосты окажутся на одной дальности, выбор ровера потеряет
    // смысл: любой доедет куда угодно.
    foreach ([7, 555, 20260812] as $seed) {
        $game = app(GameFactory::class)->create(seed: $seed);
        $costs = $game->outposts->pluck('route_cost');

        expect($costs->min())->toBeLessThan(13.0);
        expect($costs->max())->toBeGreaterThan(18.0);
    }
});

it('gives every outpost a distinct name and position', function () {
    $game = app(GameFactory::class)->create(seed: 31337);

    expect($game->outposts()->distinct()->count('name'))->toBe(Rules::OUTPOST_COUNT);
    expect($game->outposts->map(fn ($o) => $o->x.':'.$o->y)->unique())->toHaveCount(Rules::OUTPOST_COUNT);
});

it('gives the starting fleet one rover of each class', function () {
    $game = app(GameFactory::class)->create(seed: 1);

    expect($game->rovers->map(fn ($rover) => $rover->rover_class->value)->sort()->values()->all())
        ->toBe(['crawler', 'hauler', 'scout']);

    foreach ($game->rovers as $rover) {
        expect($rover->battery_level)->toBe((float) $rover->battery_capacity);
        expect($rover->status)->toBe('idle');
        expect($rover->capacity_kg)->toBe($rover->rover_class->capacityKg());
    }
});

it('creates orders within the configured bounds', function () {
    $game = app(GameFactory::class)->create(seed: 99);

    foreach ($game->orders as $order) {
        expect($order->weight_kg)->toBeGreaterThanOrEqual(Rules::ORDER_WEIGHT_MIN);
        expect($order->weight_kg)->toBeLessThanOrEqual(Rules::ORDER_WEIGHT_MAX);
        expect($order->deadline_day)->toBeGreaterThan($game->day);
        expect($order->reward)->toBeGreaterThan(0);
        expect($order->status)->toBe('pending');
        expect($order->created_day)->toBe(1);
    }
});

it('pays more for a heavier load to the same outpost', function () {
    // Награда обязана расти по весу: иначе тяжёлые заказы никто не возьмёт.
    $game = app(GameFactory::class)->create(seed: 2);
    $generator = app(App\Services\OrderGenerator::class);
    $outpost = $game->outposts()->first();

    $light = $generator->buildOrder($game, $outpost, weight: 50, deadlineIn: 3);
    $heavy = $generator->buildOrder($game, $outpost, weight: 300, deadlineIn: 3);

    expect($heavy['reward'])->toBeGreaterThan($light['reward']);
});

it('pays more for a tighter deadline', function () {
    $game = app(GameFactory::class)->create(seed: 2);
    $generator = app(App\Services\OrderGenerator::class);
    $outpost = $game->outposts()->first();

    $relaxed = $generator->buildOrder($game, $outpost, weight: 200, deadlineIn: 5);
    $urgent = $generator->buildOrder($game, $outpost, weight: 200, deadlineIn: 2);

    expect($urgent['reward'])->toBeGreaterThan($relaxed['reward']);
});

it('stores a map that survives a round trip through the database', function () {
    $game = app(GameFactory::class)->create(seed: 55);
    $map = app(MapRepository::class)->load($game);

    expect($map->all())->toHaveCount(Rules::MAP_WIDTH * Rules::MAP_HEIGHT);
    expect($map->at(new Coordinate(Rules::BASE_X, Rules::BASE_Y))->value)->toBe('mare');
});

it('keeps games independent from each other', function () {
    $first = app(GameFactory::class)->create(seed: 10);
    $second = app(GameFactory::class)->create(seed: 20);

    expect($first->tiles()->count())->toBe(Rules::MAP_WIDTH * Rules::MAP_HEIGHT);
    expect($second->tiles()->count())->toBe(Rules::MAP_WIDTH * Rules::MAP_HEIGHT);
    expect($first->rovers()->count())->toBe(3);
});
