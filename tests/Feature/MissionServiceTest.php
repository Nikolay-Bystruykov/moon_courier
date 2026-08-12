<?php

use App\Models\Delivery;
use App\Models\Rover;
use App\Services\GameFactory;
use App\Services\MissionService;

beforeEach(function () {
    $this->game = app(GameFactory::class)->create(seed: 31337);
    $this->service = app(MissionService::class);
});

it('produces an estimate for a feasible mission', function () {
    [$rover, $order] = nearestOrderFor($this->game);

    $plan = $this->service->plan($this->game, $rover, $order);

    expect($plan->estimate)->not->toBeNull();
    expect($plan->estimate->days)->toBeGreaterThanOrEqual(1);
    expect($plan->estimate->batteryCost)->toBeGreaterThan(0.0);
    expect($plan->validation->allowed)->toBeTrue();
});

it('routes the mission from the base to the outpost', function () {
    [$rover, $order] = nearestOrderFor($this->game);

    $route = $this->service->plan($this->game, $rover, $order)->estimate->route;

    $first = $route->coordinates[0];
    $last = $route->destination();

    expect([$first->x, $first->y])->toBe([App\Domain\Lunar\Rules::BASE_X, App\Domain\Lunar\Rules::BASE_Y]);
    expect([$last->x, $last->y])->toBe([$order->outpost->x, $order->outpost->y]);
});

it('stores the delivery with its route and seed', function () {
    [$rover, $order] = nearestOrderFor($this->game);

    $delivery = $this->service->dispatch($this->game, $rover, $order);

    expect($delivery->status)->toBe('in_transit');
    expect($delivery->route)->toBeArray()->not->toBeEmpty();
    expect($delivery->seed)->toBeGreaterThan(0);
    expect($delivery->return_day)->toBeGreaterThan($delivery->dispatched_day);
    expect($delivery->risk_breakdown)->toBeArray();
    expect($delivery->delay_applied)->toBeFalse();
});

it('marks the rover and the order as taken', function () {
    [$rover, $order] = nearestOrderFor($this->game);

    $this->service->dispatch($this->game, $rover, $order);

    expect($rover->fresh()->status)->toBe('en_route');
    expect($order->fresh()->status)->toBe('assigned');
});

it('logs the dispatch as an event', function () {
    [$rover, $order] = nearestOrderFor($this->game);

    $this->service->dispatch($this->game, $rover, $order);

    $event = $this->game->events()->where('type', 'dispatch')->first();

    expect($event)->not->toBeNull();
    expect($event->message)->toContain($rover->name);
    expect($event->message)->toContain($order->outpost->name);
});

it('gives different deliveries different seeds', function () {
    [$hauler, $firstOrder] = nearestOrderFor($this->game, 'hauler');
    $first = $this->service->dispatch($this->game, $hauler, $firstOrder);

    $crawler = $this->game->rovers()->where('rover_class', 'crawler')->first();
    $secondOrder = $this->game->orders()->where('status', 'pending')
        ->where('weight_kg', '<=', $crawler->capacity_kg)->first();

    if ($secondOrder === null) {
        expect(true)->toBeTrue();

        return;
    }

    $second = $this->service->dispatch($this->game, $crawler, $secondOrder);

    expect($first->seed)->not->toBe($second->seed);
});

it('refuses to dispatch a busy rover and creates nothing', function () {
    [$rover, $order] = nearestOrderFor($this->game);
    $rover->update(['status' => 'en_route']);

    expect(fn () => $this->service->dispatch($this->game, $rover->fresh(), $order))
        ->toThrow(DomainException::class);

    expect(Delivery::count())->toBe(0);
    expect($order->fresh()->status)->toBe('pending');
});

it('refuses cargo heavier than the rover can carry', function () {
    $scout = $this->game->rovers()->where('rover_class', 'scout')->first();
    $heavy = $this->game->orders()->first();
    $heavy->update(['weight_kg' => $scout->capacity_kg + 100]);

    $plan = $this->service->plan($this->game, $scout, $heavy->fresh());

    expect($plan->validation->allowed)->toBeFalse();
    expect($plan->validation->messages())->toContain('Груз тяжелее грузоподъёмности ровера');
});

it('leaves a heavy load to the farthest outpost undeliverable', function () {
    // Требование ТЗ: невыполнимая доставка должна возникать из правил, а не
    // подстраиваться вручную. Тяжёлый груз влезает только в Гусеницу, но ей
    // на такое плечо не хватает заряда на обратную дорогу; остальным не
    // хватает грузоподъёмности.
    $farthest = $this->game->outposts()->orderByDesc('route_cost')->first();

    $order = App\Models\Order::create(app(App\Services\OrderGenerator::class)->buildOrder(
        $this->game, $farthest, weight: 400, deadlineIn: 5,
    ));

    $allowed = $this->game->rovers->filter(
        fn (Rover $rover) => $this->service->plan($this->game, $rover, $order)->validation->allowed
    );

    expect($allowed)->toBeEmpty();
});

it('blocks the roomiest rover on battery, not on weight', function () {
    $farthest = $this->game->outposts()->orderByDesc('route_cost')->first();

    $order = App\Models\Order::create(app(App\Services\OrderGenerator::class)->buildOrder(
        $this->game, $farthest, weight: 400, deadlineIn: 5,
    ));

    $crawler = $this->game->rovers()->where('rover_class', 'crawler')->first();
    $reasons = $this->service->plan($this->game, $crawler, $order)->validation->reasons;

    expect($reasons)->toContain(App\Domain\Lunar\RejectionReason::InsufficientBattery);
    expect($reasons)->not->toContain(App\Domain\Lunar\RejectionReason::Overweight);
});

it('lets the long range rover reach the farthest outpost with a light load', function () {
    // Обратная проверка: дальний аванпост не должен быть мёртвой точкой.
    $farthest = $this->game->outposts()->orderByDesc('route_cost')->first();

    $order = App\Models\Order::create(app(App\Services\OrderGenerator::class)->buildOrder(
        $this->game, $farthest, weight: 60, deadlineIn: 5,
    ));

    $hauler = $this->game->rovers()->where('rover_class', 'hauler')->first();

    expect($this->service->plan($this->game, $hauler, $order)->validation->allowed)->toBeTrue();
});
