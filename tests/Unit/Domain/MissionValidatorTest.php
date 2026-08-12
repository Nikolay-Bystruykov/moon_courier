<?php

use App\Domain\Lunar\MissionEstimate;
use App\Domain\Lunar\MissionPlanner;
use App\Domain\Lunar\MissionValidator;
use App\Domain\Lunar\RejectionReason;

function planFor(int $length, int $cargoKg, int $capacity = 400, int $battery = 200, ?float $level = null): MissionEstimate
{
    [$map, $route] = flatCorridor($length);

    return MissionPlanner::estimate(
        $map, $route, $capacity, $battery, $level ?? (float) $battery, 14.0, $cargoKg, 1, 10,
    );
}

function validate(
    ?MissionEstimate $estimate,
    int $cargoKg = 100,
    int $capacity = 400,
    int $battery = 200,
    string $roverStatus = 'idle',
    int $repairDaysLeft = 0,
    string $orderStatus = 'pending',
    int $currentDay = 1,
    int $deadlineDay = 10,
) {
    return MissionValidator::validate(
        $estimate, $cargoKg, $capacity, $battery, $roverStatus, $repairDaysLeft, $orderStatus, $currentDay, $deadlineDay,
    );
}

it('allows a mission that fits every constraint', function () {
    $result = validate(planFor(6, 100));

    expect($result->allowed)->toBeTrue();
    expect($result->reasons)->toBeEmpty();
});

it('rejects cargo heavier than the rover can carry', function () {
    $result = validate(planFor(6, 500), cargoKg: 500);

    expect($result->allowed)->toBeFalse();
    expect($result->reasons)->toContain(RejectionReason::Overweight);
});

it('rejects a trip that would strand the rover', function () {
    // Плечо заведомо длиннее, чем позволяет заряд.
    $result = validate(planFor(60, 200, battery: 100, level: 100.0), cargoKg: 200, battery: 100);

    expect($result->allowed)->toBeFalse();
    expect($result->reasons)->toContain(RejectionReason::InsufficientBattery);
});

it('keeps the mandatory battery reserve', function () {
    // Заряда хватает ровно на круг, но неснижаемого остатка не остаётся.
    $exact = planFor(10, 100, battery: 200)->batteryCost;

    $result = validate(planFor(10, 100, battery: 200, level: $exact), battery: 200);

    expect($result->reasons)->toContain(RejectionReason::InsufficientBattery);
});

it('allows a trip that keeps just above the reserve', function () {
    $cost = planFor(10, 100, battery: 200)->batteryCost;

    $result = validate(planFor(10, 100, battery: 200, level: $cost + 21.0), battery: 200);

    expect($result->allowed)->toBeTrue();
});

it('rejects a rover that is already out', function () {
    expect(validate(planFor(6, 100), roverStatus: 'en_route')->reasons)
        ->toContain(RejectionReason::RoverBusy);
});

it('rejects a rover under repair', function () {
    expect(validate(planFor(6, 100), roverStatus: 'repair', repairDaysLeft: 2)->reasons)
        ->toContain(RejectionReason::RoverInRepair);
});

it('rejects an order that is already assigned', function () {
    expect(validate(planFor(6, 100), orderStatus: 'assigned')->reasons)
        ->toContain(RejectionReason::OrderTaken);
});

it('rejects an order whose deadline has passed', function () {
    expect(validate(planFor(6, 100), currentDay: 11, deadlineDay: 10)->reasons)
        ->toContain(RejectionReason::OrderExpired);
});

it('rejects a destination with no route to it', function () {
    expect(validate(null)->reasons)->toContain(RejectionReason::Unreachable);
});

it('reports every failing constraint at once', function () {
    // Игрок должен видеть все причины сразу, а не исправлять их по одной.
    $result = validate(
        planFor(60, 500, battery: 100, level: 100.0),
        cargoKg: 500,
        battery: 100,
        roverStatus: 'en_route',
    );

    expect($result->reasons)->toContain(RejectionReason::Overweight);
    expect($result->reasons)->toContain(RejectionReason::InsufficientBattery);
    expect($result->reasons)->toContain(RejectionReason::RoverBusy);
});

it('renders human readable messages', function () {
    $messages = validate(planFor(6, 500), cargoKg: 500)->messages();

    expect($messages)->toContain('Груз тяжелее грузоподъёмности ровера');
});
