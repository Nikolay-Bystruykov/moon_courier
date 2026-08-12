<?php

use App\Domain\Lunar\SeededRandom;

it('produces the same sequence for the same seed', function () {
    $first = new SeededRandom(42);
    $second = new SeededRandom(42);

    $a = [$first->next(), $first->next(), $first->next()];
    $b = [$second->next(), $second->next(), $second->next()];

    expect($a)->toBe($b);
});

it('produces different sequences for different seeds', function () {
    expect((new SeededRandom(42))->next())->not->toBe((new SeededRandom(43))->next());
});

it('stays within the unit interval', function () {
    $rng = new SeededRandom(7);

    for ($i = 0; $i < 500; $i++) {
        $value = $rng->next();

        expect($value)->toBeGreaterThanOrEqual(0.0)->toBeLessThan(1.0);
    }
});

it('returns integers within the requested bounds', function () {
    $rng = new SeededRandom(7);
    $seen = [];

    for ($i = 0; $i < 500; $i++) {
        $value = $rng->nextInt(3, 6);

        expect($value)->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(6);

        $seen[$value] = true;
    }

    // За 500 бросков все четыре значения обязаны выпасть хотя бы раз.
    expect(array_keys($seen))->toHaveCount(4);
});

it('survives a zero seed', function () {
    $rng = new SeededRandom(0);

    expect($rng->next())->toBeGreaterThan(0.0);
    expect($rng->next())->not->toBe(0.0);
});

it('picks an element from the given list', function () {
    expect(['a', 'b', 'c'])->toContain((new SeededRandom(7))->pick(['a', 'b', 'c']));
});
