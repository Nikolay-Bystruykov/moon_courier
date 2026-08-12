<?php

use App\Domain\Lunar\Coordinate;
use App\Domain\Lunar\MissionEstimate;
use App\Domain\Lunar\MissionPlanner;
use App\Domain\Lunar\RouteFinder;
use App\Domain\Lunar\Rules;

function estimateFor(
    array $mapAndRoute,
    int $cargoKg,
    int $capacity = 400,
    int $battery = 100,
    ?float $level = null,
    float $speed = 14.0,
    int $deadlineDay = 10,
): MissionEstimate {
    [$map, $route] = $mapAndRoute;

    return MissionPlanner::estimate(
        $map, $route, $capacity, $battery, $level ?? (float) $battery, $speed, $cargoKg, 1, $deadlineDay,
    );
}

it('charges more battery for a heavier load', function () {
    expect(estimateFor(flatCorridor(8), 360)->batteryCost)
        ->toBeGreaterThan(estimateFor(flatCorridor(8), 40)->batteryCost);
});

it('charges nothing extra for the empty return leg', function () {
    // Порожний круг равен удвоенному пути в одну сторону.
    $oneWay = 8 * Rules::BASE_BATTERY_DRAW;

    expect(estimateFor(flatCorridor(9), 0)->batteryCost)->toBe($oneWay * 2);
});

it('makes the loaded leg more expensive than the empty one', function () {
    $estimate = estimateFor(flatCorridor(9), 400, capacity: 400, battery: 300);

    $emptyLeg = 8 * Rules::BASE_BATTERY_DRAW;
    $loadedLeg = $emptyLeg * (1 + Rules::LOAD_PENALTY);

    expect($estimate->batteryCost)->toEqualWithDelta($emptyLeg + $loadedLeg, 0.0001);
});

it('draws extra power in permanent shadow', function () {
    $flat = mapFromRows(['....']);
    $shady = mapFromRows(['.ss.']);

    $flatRoute = RouteFinder::find($flat, new Coordinate(0, 0), new Coordinate(3, 0));
    $shadyRoute = RouteFinder::find($shady, new Coordinate(0, 0), new Coordinate(3, 0));

    $flatEstimate = MissionPlanner::estimate($flat, $flatRoute, 400, 300, 300.0, 14.0, 0, 1, 10);
    $shadyEstimate = MissionPlanner::estimate($shady, $shadyRoute, 400, 300, 300.0, 14.0, 0, 1, 10);

    expect($shadyEstimate->batteryCost)->toBeGreaterThan($flatEstimate->batteryCost);
});

it('takes longer with a heavier load', function () {
    $light = estimateFor(flatCorridor(20), 40, battery: 400);
    $heavy = estimateFor(flatCorridor(20), 400, battery: 400);

    expect($heavy->days)->toBeGreaterThanOrEqual($light->days);
});

it('never reports a trip shorter than one day', function () {
    expect(estimateFor(flatCorridor(2), 40)->days)->toBeGreaterThanOrEqual(1);
});

it('reports the day the rover comes back', function () {
    $estimate = estimateFor(flatCorridor(10), 100, battery: 300);

    // Отправка на первые сутки, поэтому возврат — первые сутки плюс путь.
    expect($estimate->returnDay)->toBe(1 + $estimate->days);
});

it('raises risk on dangerous terrain', function () {
    $safe = mapFromRows(['.....']);
    $rough = mapFromRows(['.xxx.']);

    $safeRoute = RouteFinder::find($safe, new Coordinate(0, 0), new Coordinate(4, 0));
    $roughRoute = RouteFinder::find($rough, new Coordinate(0, 0), new Coordinate(4, 0));

    $safeEstimate = MissionPlanner::estimate($safe, $safeRoute, 400, 300, 300.0, 14.0, 40, 1, 10);
    $roughEstimate = MissionPlanner::estimate($rough, $roughRoute, 400, 300, 300.0, 14.0, 40, 1, 10);

    expect($roughEstimate->risk->route)->toBeGreaterThan($safeEstimate->risk->route);
});

it('adds a penalty for running near full capacity', function () {
    expect(estimateFor(flatCorridor(6), 390, capacity: 400)->risk->overload)->toBe(Rules::OVERLOAD_RISK);
    expect(estimateFor(flatCorridor(6), 100, capacity: 400)->risk->overload)->toBe(0.0);
});

it('adds a penalty for returning on empty', function () {
    // Полугружёный ровер тратит 3.6 заряда на клетку в оба конца, значит на
    // 24 клетках от сотни остаётся 13.6 — ниже порога в 15%.
    $estimate = estimateFor(flatCorridor(25), 200, capacity: 400, battery: 100, level: 100.0);

    expect($estimate->batteryAfter)->toBeLessThan(100 * Rules::LOW_BATTERY_THRESHOLD);
    expect($estimate->risk->lowBattery)->toBe(Rules::LOW_BATTERY_RISK);
});

it('adds no low battery penalty when the reserve is comfortable', function () {
    expect(estimateFor(flatCorridor(4), 40, battery: 400)->risk->lowBattery)->toBe(0.0);
});

it('adds a penalty for returning after the deadline', function () {
    expect(estimateFor(flatCorridor(20), 200, battery: 400, deadlineDay: 14)->risk->lateReturn)->toBe(0.0);
    expect(estimateFor(flatCorridor(20), 200, battery: 400, deadlineDay: 2)->risk->lateReturn)->toBe(Rules::LATE_RETURN_RISK);
});

it('caps total risk', function () {
    $map = mapFromRows([str_repeat('x', 40)]);
    $route = RouteFinder::find($map, new Coordinate(0, 0), new Coordinate(39, 0));

    $estimate = MissionPlanner::estimate($map, $route, 400, 1000, 1000.0, 14.0, 400, 1, 1);

    expect($estimate->risk->total)->toBe(Rules::MAX_RISK);
});

it('lists only the penalties that actually apply', function () {
    $codes = array_column(estimateFor(flatCorridor(6), 390, capacity: 400)->risk->components(), 'code');

    expect($codes)->toContain('route');
    expect($codes)->toContain('overload');
    expect($codes)->not->toContain('late_return');
});

it('reports the charge left after the trip', function () {
    $estimate = estimateFor(flatCorridor(6), 100, battery: 200, level: 200.0);

    expect($estimate->batteryAfter)->toBe(200.0 - $estimate->batteryCost);
});
