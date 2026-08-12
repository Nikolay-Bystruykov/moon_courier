<?php

use App\Domain\Lunar\Incident;
use App\Domain\Lunar\MissionResolver;
use App\Domain\Lunar\SeededRandom;

it('never reports an incident when risk is zero', function () {
    for ($seed = 1; $seed <= 50; $seed++) {
        $outcome = MissionResolver::resolve(0.0, new SeededRandom($seed));

        expect($outcome->incidentOccurred)->toBeFalse();
        expect($outcome->incident)->toBeNull();
    }
});

it('always reports an incident when risk is certain', function () {
    for ($seed = 1; $seed <= 50; $seed++) {
        $outcome = MissionResolver::resolve(1.0, new SeededRandom($seed));

        expect($outcome->incidentOccurred)->toBeTrue();
        expect($outcome->incident)->toBeInstanceOf(Incident::class);
    }
});

it('returns the same outcome for the same seed', function () {
    $first = MissionResolver::resolve(0.5, new SeededRandom(2024));
    $second = MissionResolver::resolve(0.5, new SeededRandom(2024));

    expect($first->incidentOccurred)->toBe($second->incidentOccurred);
    expect($first->incident)->toBe($second->incident);
    expect($first->extraDays)->toBe($second->extraDays);
});

it('fires roughly as often as the stated risk', function () {
    $incidents = 0;

    for ($seed = 1; $seed <= 2000; $seed++) {
        if (MissionResolver::resolve(0.3, new SeededRandom($seed))->incidentOccurred) {
            $incidents++;
        }
    }

    // Показанный игроку риск обязан совпадать с наблюдаемой частотой,
    // иначе число на карточке ничего не значит.
    expect($incidents / 2000)->toBeGreaterThan(0.27)->toBeLessThan(0.33);
});

it('reaches every kind of incident across many seeds', function () {
    $seen = [];

    for ($seed = 1; $seed <= 500; $seed++) {
        $seen[MissionResolver::resolve(1.0, new SeededRandom($seed))->incident->value] = true;
    }

    expect(array_keys($seen))->toHaveCount(count(Incident::cases()));
});

it('carries the consequences of the drawn incident', function () {
    for ($seed = 1; $seed <= 50; $seed++) {
        $outcome = MissionResolver::resolve(1.0, new SeededRandom($seed));

        expect($outcome->extraDays)->toBe($outcome->incident->extraDays());
        expect($outcome->repairDays)->toBe($outcome->incident->repairDays());
        expect($outcome->repairCost)->toBe($outcome->incident->repairCost());
        expect($outcome->orderFailed)->toBe($outcome->incident->failsOrder());
    }
});

it('leaves a clean run without consequences', function () {
    $outcome = MissionResolver::resolve(0.0, new SeededRandom(1));

    expect($outcome->extraDays)->toBe(0);
    expect($outcome->repairDays)->toBe(0);
    expect($outcome->repairCost)->toBe(0);
    expect($outcome->orderFailed)->toBeFalse();
    expect($outcome->dropsUrgencyBonus)->toBeFalse();
});

it('loses the rover only rarely', function () {
    $critical = 0;

    for ($seed = 1; $seed <= 2000; $seed++) {
        if (MissionResolver::resolve(1.0, new SeededRandom($seed))->incident === Incident::CriticalFailure) {
            $critical++;
        }
    }

    // Потеря груза должна быть редкой даже среди сработавших инцидентов.
    expect($critical / 2000)->toBeLessThan(0.15);
});

it('derives a stable seed from game and delivery', function () {
    expect(MissionResolver::seedFor(777, 42))->toBe(MissionResolver::seedFor(777, 42));
    expect(MissionResolver::seedFor(777, 42))->not->toBe(MissionResolver::seedFor(777, 43));
    expect(MissionResolver::seedFor(778, 42))->not->toBe(MissionResolver::seedFor(777, 42));
});
