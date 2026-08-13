<?php

use App\Domain\Lunar\Rules;
use App\Domain\Lunar\Scoring;

function fleet(array $rovers): array
{
    return array_map(fn (array $rover) => [
        'class' => $rover[0],
        'battery_upgraded' => $rover[1] ?? false,
        'capacity_upgraded' => $rover[2] ?? false,
    ], $rovers);
}

it('values the starting fleet at list price', function () {
    $value = Scoring::fleetValue(fleet([['crawler'], ['scout'], ['hauler']]));

    expect($value)->toBe(
        Rules::ROVER_PRICES['crawler'] + Rules::ROVER_PRICES['scout'] + Rules::ROVER_PRICES['hauler']
    );
});

it('counts installed upgrades as part of the fleet value', function () {
    $plain = Scoring::fleetValue(fleet([['scout']]));
    $upgraded = Scoring::fleetValue(fleet([['scout', true, true]]));

    expect($upgraded - $plain)->toBe(Rules::UPGRADE_BATTERY_COST + Rules::UPGRADE_CAPACITY_COST);
});

it('keeps a purchase roughly score neutral', function () {
    // Ровер, купленный за свою цену, не должен обваливать итог: иначе
    // выгоднее всего не тратить ничего, и гараж становится бутафорией.
    $before = Scoring::total(5000, Scoring::fleetValue(fleet([['crawler'], ['scout'], ['hauler']])), 40);

    $after = Scoring::total(
        5000 - Rules::ROVER_PRICES['hauler'],
        Scoring::fleetValue(fleet([['crawler'], ['scout'], ['hauler'], ['hauler']])),
        40,
    );

    expect($after)->toBe($before);
});

it('weighs reputation heavily', function () {
    $low = Scoring::total(1000, 0, 10);
    $high = Scoring::total(1000, 0, 20);

    expect($high - $low)->toBe(10 * Scoring::REPUTATION_WEIGHT);
});
