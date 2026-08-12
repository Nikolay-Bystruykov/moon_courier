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
                'route_length', 'battery_cost', 'battery_percent_after',
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

    $this->getJson("/mission/estimate?rover_id={$scout->id}&order_id={$order->id}")
        ->assertOk()
        ->assertJsonPath('allowed', false)
        ->assertJsonFragment(['Груз тяжелее грузоподъёмности ровера']);
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
