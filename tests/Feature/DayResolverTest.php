<?php

use App\Domain\Lunar\MissionResolver;
use App\Domain\Lunar\Rules;
use App\Domain\Lunar\SeededRandom;
use App\Models\Order;
use App\Services\DayResolver;
use App\Services\GameFactory;
use App\Services\MissionService;

beforeEach(function () {
    $this->game = app(GameFactory::class)->create(seed: 24680);
    $this->missions = app(MissionService::class);
    $this->resolver = app(DayResolver::class);
});

/** Прокручивает сутки, пока рейс не завершится или не кончится терпение. */
function runUntilResolved($resolver, $game, $delivery, int $limit = 12): void
{
    for ($i = 0; $i < $limit && $delivery->fresh()->status === 'in_transit'; $i++) {
        $resolver->advance($game->fresh());
    }
}

it('moves the calendar forward', function () {
    $this->resolver->advance($this->game);

    expect($this->game->fresh()->day)->toBe(2);
});

it('recharges rovers without exceeding capacity', function () {
    $rover = $this->game->rovers()->first();
    $rover->update(['battery_level' => 10.0]);

    $this->resolver->advance($this->game);

    expect($rover->fresh()->battery_level)
        ->toBe(10.0 + $rover->battery_capacity * Rules::RECHARGE_RATE);

    $rover->update(['battery_level' => $rover->battery_capacity]);
    $this->resolver->advance($this->game->fresh());

    expect($rover->fresh()->battery_level)->toBe((float) $rover->battery_capacity);
});

it('generates new orders every day', function () {
    $before = $this->game->orders()->count();

    $this->resolver->advance($this->game);

    expect($this->game->fresh()->orders()->count())->toBeGreaterThan($before);
});

it('expires unclaimed orders and charges reputation for them', function () {
    $this->game->orders()->update(['deadline_day' => 1]);
    $count = $this->game->orders()->count();
    $before = $this->game->reputation;

    $this->resolver->advance($this->game);

    expect(Order::where('status', 'expired')->count())->toBe($count);
    expect($this->game->fresh()->reputation)->toBe($before + Rules::REPUTATION_EXPIRED * $count);
});

it('does not expire an order that is already on its way', function () {
    [$rover, $order] = nearestOrderFor($this->game);
    $this->missions->dispatch($this->game, $rover, $order);
    $order->update(['deadline_day' => 1]);

    $this->resolver->advance($this->game);

    expect($order->fresh()->status)->not->toBe('expired');
});

it('completes a delivery, pays out and frees the rover', function () {
    [$rover, $order] = nearestOrderFor($this->game);
    $delivery = $this->missions->dispatch($this->game, $rover, $order);

    $creditsBefore = $this->game->fresh()->credits;

    runUntilResolved($this->resolver, $this->game, $delivery);

    $delivery = $delivery->fresh();

    expect($delivery->status)->toBe('completed');
    expect($delivery->resolved_day)->not->toBeNull();
    expect($rover->fresh()->status)->toBeIn(['idle', 'repair']);
    expect($order->fresh()->status)->toBeIn(['delivered', 'failed']);

    if ($order->fresh()->status === 'delivered') {
        expect($this->game->fresh()->credits)->toBeGreaterThan($creditsBefore);
    }
});

it('drains the battery by the estimated cost', function () {
    [$rover, $order] = nearestOrderFor($this->game);
    $delivery = $this->missions->dispatch($this->game, $rover, $order);
    $before = $rover->battery_level;

    runUntilResolved($this->resolver, $this->game, $delivery);

    // Заряд тратится на рейс и лишь частично восстанавливается на базе.
    expect($rover->fresh()->battery_level)->toBeLessThan($before);
});

it('writes an event for the resolved delivery', function () {
    [$rover, $order] = nearestOrderFor($this->game);
    $delivery = $this->missions->dispatch($this->game, $rover, $order);

    runUntilResolved($this->resolver, $this->game, $delivery);

    expect($this->game->events()->where('type', 'delivery')->count())->toBe(1);
});

it('matches the stored outcome with the recorded seed', function () {
    [$rover, $order] = nearestOrderFor($this->game);
    $delivery = $this->missions->dispatch($this->game, $rover, $order);

    runUntilResolved($this->resolver, $this->game, $delivery);

    $delivery = $delivery->fresh();

    // Записанный исход обязан выводиться из сохранённого зерна: иначе рейс
    // невозможно ни повторить, ни объяснить.
    $expected = MissionResolver::resolve($delivery->risk, new SeededRandom($delivery->seed));

    expect($delivery->incident)->toBe($expected->incident?->value);
});

it('keeps a delayed delivery on the road exactly once', function () {
    [$rover, $order] = nearestOrderFor($this->game);
    $delivery = $this->missions->dispatch($this->game, $rover, $order);

    $outcome = MissionResolver::resolve($delivery->risk, new SeededRandom($delivery->seed));
    $expectedReturn = $delivery->return_day + $outcome->extraDays;

    runUntilResolved($this->resolver, $this->game, $delivery);

    // Задержка применяется один раз: иначе рейс продлевался бы вечно.
    expect($delivery->fresh()->resolved_day)->toBe($expectedReturn);
});

it('finishes repairs after the idle days pass', function () {
    $rover = $this->game->rovers()->first();
    $rover->update(['status' => 'repair', 'repair_days_left' => 2]);

    $this->resolver->advance($this->game);
    expect($rover->fresh()->repair_days_left)->toBe(1);
    expect($rover->fresh()->status)->toBe('repair');

    $this->resolver->advance($this->game->fresh());
    expect($rover->fresh()->repair_days_left)->toBe(0);
    expect($rover->fresh()->status)->toBe('idle');
});

it('ends the game when reputation runs out', function () {
    $this->game->update(['reputation' => 5]);
    $this->game->orders()->update(['deadline_day' => 1]);

    $this->resolver->advance($this->game);

    expect($this->game->fresh()->status)->toBe('lost');
    expect($this->game->fresh()->reputation)->toBe(0);
});

it('wins the game after the final day', function () {
    $this->game->update(['day' => Rules::TOTAL_DAYS]);

    $this->resolver->advance($this->game);

    expect($this->game->fresh()->status)->toBe('won');
});

it('does nothing once the game is over', function () {
    $this->game->update(['status' => 'lost']);

    $this->resolver->advance($this->game);

    expect($this->game->fresh()->day)->toBe(1);
});

it('survives a full run to the last day', function () {
    // Партия обязана доигрываться до конца без исключений и зависших рейсов.
    for ($i = 0; $i < Rules::TOTAL_DAYS + 1; $i++) {
        $game = $this->game->fresh();

        if (! $game->isActive()) {
            break;
        }

        $this->resolver->advance($game);
    }

    expect($this->game->fresh()->status)->toBeIn(['won', 'lost']);
    expect($this->game->deliveries()->where('status', 'in_transit')->count())->toBe(0);
});
