<?php

use App\Models\Delivery;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\MapTile;
use App\Models\Order;
use App\Models\Outpost;
use App\Models\Rover;

it('persists a game with all related records', function () {
    $game = Game::create([
        'seed' => 12345,
        'day' => 1,
        'credits' => 500,
        'reputation' => 100,
        'status' => 'active',
    ]);

    MapTile::create(['game_id' => $game->id, 'x' => 3, 'y' => 6, 'terrain' => 'mare']);

    $outpost = Outpost::create([
        'game_id' => $game->id,
        'name' => 'Тихо',
        'x' => 12,
        'y' => 9,
        'route_cost' => 18.4,
        'route_tiles' => 14,
    ]);

    $rover = Rover::create([
        'game_id' => $game->id,
        'name' => 'R1',
        'rover_class' => 'crawler',
        'capacity_kg' => 400,
        'battery_capacity' => 100,
        'battery_level' => 100,
        'status' => 'idle',
        'repair_days_left' => 0,
    ]);

    $order = Order::create([
        'game_id' => $game->id,
        'outpost_id' => $outpost->id,
        'weight_kg' => 180,
        'reward' => 440,
        'deadline_day' => 4,
        'created_day' => 1,
        'status' => 'pending',
    ]);

    $delivery = Delivery::create([
        'game_id' => $game->id,
        'rover_id' => $rover->id,
        'order_id' => $order->id,
        'dispatched_day' => 1,
        'return_day' => 3,
        'route' => [['x' => 3, 'y' => 6], ['x' => 4, 'y' => 6]],
        'route_cost' => 14.4,
        'battery_cost' => 68.2,
        'risk' => 0.34,
        'risk_breakdown' => [['code' => 'route', 'label' => 'местность', 'value' => 0.26]],
        'seed' => 987654,
        'status' => 'in_transit',
    ]);

    GameEvent::create([
        'game_id' => $game->id,
        'day' => 1,
        'type' => 'dispatch',
        'message' => 'R1 отправлен к аванпосту Тихо',
        'payload' => ['rover' => 'R1'],
    ]);

    expect($game->tiles)->toHaveCount(1);
    expect($game->outposts)->toHaveCount(1);
    expect($game->rovers)->toHaveCount(1);
    expect($game->orders)->toHaveCount(1);
    expect($game->deliveries)->toHaveCount(1);
    expect($game->events)->toHaveCount(1);

    expect($delivery->fresh()->route)->toBeArray();
    expect($delivery->fresh()->risk_breakdown[0]['code'])->toBe('route');
    expect($delivery->fresh()->delay_applied)->toBeFalse();
    expect($order->fresh()->outpost->name)->toBe('Тихо');
    expect($rover->fresh()->battery_level)->toBe(100.0);
    expect($outpost->fresh()->route_cost)->toBe(18.4);
    expect($outpost->fresh()->distanceKm())->toBe(14 * App\Domain\Lunar\Rules::KM_PER_TILE);
});

it('rejects a duplicate tile at the same coordinates', function () {
    $game = Game::create([
        'seed' => 1, 'day' => 1, 'credits' => 0, 'reputation' => 100, 'status' => 'active',
    ]);

    MapTile::create(['game_id' => $game->id, 'x' => 1, 'y' => 1, 'terrain' => 'mare']);

    expect(fn () => MapTile::create(['game_id' => $game->id, 'x' => 1, 'y' => 1, 'terrain' => 'crater']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('casts terrain and rover class to enums', function () {
    $game = Game::create([
        'seed' => 1, 'day' => 1, 'credits' => 0, 'reputation' => 100, 'status' => 'active',
    ]);

    $tile = MapTile::create(['game_id' => $game->id, 'x' => 0, 'y' => 0, 'terrain' => 'rille']);

    $rover = Rover::create([
        'game_id' => $game->id,
        'name' => 'R2',
        'rover_class' => 'scout',
        'capacity_kg' => 120,
        'battery_capacity' => 80,
        'battery_level' => 80,
        'status' => 'idle',
        'repair_days_left' => 0,
    ]);

    expect($tile->fresh()->terrain)->toBe(App\Domain\Lunar\Terrain::Rille);
    expect($rover->fresh()->rover_class)->toBe(App\Domain\Lunar\RoverClass::Scout);
});
