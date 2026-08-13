<?php

use App\Domain\Lunar\Rules;
use App\Models\Delivery;
use App\Models\Game;
use App\Services\GameFactory;

it('creates a game on the first visit', function () {
    $this->get('/')->assertOk();

    expect(Game::count())->toBe(1);
});

it('keeps returning the same game within a session', function () {
    $this->get('/')->assertOk();
    $this->get('/')->assertOk();

    expect(Game::count())->toBe(1);
});

it('shows the fleet, the orders and the run state', function () {
    $game = app(GameFactory::class)->create(seed: 5150);
    $this->withSession(['game_id' => $game->id]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Гусеница')
        ->assertSee('Скаут')
        ->assertSee('Тягач')
        ->assertSee($game->outposts()->first()->name)
        ->assertSee('сутки')
        ->assertSee('кредиты')
        ->assertSee('рейтинг базы');
});

it('lays out outpost labels without overlaps', function () {
    // Соседние аванпосты раньше подписывались друг поверх друга, а точки
    // нижнего ряда — за краем карты.
    foreach ([5150, 777, 31337, 90210] as $seed) {
        $game = app(GameFactory::class)->create(seed: $seed);
        $this->withSession(['game_id' => $game->id]);

        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all(
            '/<text x="([\d.]+)" y="([\d.]+)"[^>]*font-size="9\.5"[^>]*>([^<]+)<\/text>/',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        expect($matches)->toHaveCount(Rules::OUTPOST_COUNT);

        foreach ($matches as $label) {
            [, $x, $y, $name] = $label;

            expect((float) $y)->toBeGreaterThan(4.0);
            expect((float) $y)->toBeLessThan(Rules::MAP_HEIGHT * 32);
        }

        foreach ($matches as $i => $first) {
            foreach (array_slice($matches, $i + 1) as $second) {
                $sameRow = abs((float) $first[2] - (float) $second[2]) < 8;
                $halfWidths = (mb_strlen($first[3]) + mb_strlen($second[3])) * 5.7 / 2;

                if ($sameRow) {
                    expect(abs((float) $first[1] - (float) $second[1]))
                        ->toBeGreaterThanOrEqual($halfWidths, "{$first[3]} и {$second[3]} накладываются");
                }
            }
        }
    }
});

it('shows every rover on the map', function () {
    $game = app(GameFactory::class)->create(seed: 5150);
    $this->withSession(['game_id' => $game->id]);

    $html = $this->get('/')->assertOk()->getContent();

    foreach ($game->rovers as $rover) {
        expect($html)->toContain(">{$rover->name}</text>");
    }
});

it('starts a fresh run on request', function () {
    $first = app(GameFactory::class)->create(seed: 1);
    $this->withSession(['game_id' => $first->id]);

    $this->post('/game')->assertRedirect('/');

    expect(Game::count())->toBe(2);
});

it('returns a full estimate for a feasible mission', function () {
    $game = app(GameFactory::class)->create(seed: 8080);
    $this->withSession(['game_id' => $game->id]);

    [$rover, $order] = nearestOrderFor($game);

    $this->getJson("/mission/estimate?rover_id={$rover->id}&order_id={$order->id}")
        ->assertOk()
        ->assertJsonPath('allowed', true)
        ->assertJsonStructure([
            'allowed',
            'reasons',
            'route',
            'estimate' => [
                'distance_km', 'battery_cost', 'battery_percent_after',
                'days', 'return_day', 'risk', 'risk_components',
            ],
            'order' => ['weight_kg', 'reward', 'outpost'],
            'rover' => ['name', 'capacity_kg'],
        ]);
});

it('explains why a mission is not allowed', function () {
    $game = app(GameFactory::class)->create(seed: 8080);
    $this->withSession(['game_id' => $game->id]);

    $scout = $game->rovers()->where('rover_class', 'scout')->first();
    $order = $game->orders()->first();
    $order->update(['weight_kg' => $scout->capacity_kg + 50]);

    $response = $this->getJson("/mission/estimate?rover_id={$scout->id}&order_id={$order->id}")
        ->assertOk()
        ->assertJsonPath('allowed', false);

    // Причина обязана называть конкретные числа: «не хватает» без величины
    // не объясняет игроку, что менять.
    $reasons = implode(' ', $response->json('reasons'));

    expect($reasons)->toContain('Груз тяжелее грузоподъёмности ровера');
    expect($reasons)->toContain((string) $order->weight_kg);
    expect($reasons)->toContain((string) $scout->capacity_kg);
});

it('reports how much charge is missing', function () {
    $game = app(GameFactory::class)->create(seed: 8080);
    $this->withSession(['game_id' => $game->id]);

    $scout = $game->rovers()->where('rover_class', 'scout')->first();
    $farthest = $game->outposts()->orderByDesc('route_cost')->first();

    $order = App\Models\Order::create(app(App\Services\OrderGenerator::class)->buildOrder(
        $game, $farthest, weight: 40, deadlineIn: 5,
    ));

    $reasons = implode(' ', $this->getJson("/mission/estimate?rover_id={$scout->id}&order_id={$order->id}")
        ->assertOk()
        ->json('reasons'));

    // Обе величины называются в километрах: сравнивать заряд с расстоянием
    // игрок не должен.
    expect($reasons)->toContain('км');
    expect($reasons)->toContain('до аванпоста');
    expect($reasons)->toContain('уедет на');
});

it('dispatches a rover from the console', function () {
    $game = app(GameFactory::class)->create(seed: 8080);
    $this->withSession(['game_id' => $game->id]);

    [$rover, $order] = nearestOrderFor($game);

    $this->post('/mission/dispatch', ['rover_id' => $rover->id, 'order_id' => $order->id])
        ->assertRedirect('/');

    expect(Delivery::count())->toBe(1);
    expect($rover->fresh()->status)->toBe('en_route');
});

it('refuses an impossible dispatch and creates nothing', function () {
    $game = app(GameFactory::class)->create(seed: 8080);
    $this->withSession(['game_id' => $game->id]);

    [$rover, $order] = nearestOrderFor($game);
    $order->update(['weight_kg' => 5000]);

    $this->post('/mission/dispatch', ['rover_id' => $rover->id, 'order_id' => $order->id])
        ->assertRedirect('/')
        ->assertSessionHas('error');

    expect(Delivery::count())->toBe(0);
});

it('rejects records from another game', function () {
    $mine = app(GameFactory::class)->create(seed: 3);
    $other = app(GameFactory::class)->create(seed: 4);
    $this->withSession(['game_id' => $mine->id]);

    [$rover, $order] = nearestOrderFor($other);

    $this->getJson("/mission/estimate?rover_id={$rover->id}&order_id={$order->id}")
        ->assertNotFound();
});

it('advances the day from the console', function () {
    $game = app(GameFactory::class)->create(seed: 606);
    $this->withSession(['game_id' => $game->id]);

    $this->post('/day/advance')->assertRedirect('/');

    expect($game->fresh()->day)->toBe(2);
});

it('shows the victory summary and hides the controls', function () {
    $game = app(GameFactory::class)->create(seed: 606);
    $this->withSession(['game_id' => $game->id]);
    $game->update(['day' => Rules::TOTAL_DAYS]);

    $this->post('/day/advance');

    $this->get('/')
        ->assertOk()
        ->assertSee('смена завершена')
        ->assertSee('итог')
        ->assertDontSee('завершить сутки');
});

it('shows the defeat summary', function () {
    $game = app(GameFactory::class)->create(seed: 606);
    $this->withSession(['game_id' => $game->id]);
    $game->update(['reputation' => 1]);
    $game->orders()->update(['deadline_day' => 1]);

    $this->post('/day/advance');

    $this->get('/')->assertOk()->assertSee('база потеряла доверие');
});
