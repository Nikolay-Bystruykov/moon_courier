<?php

use App\Domain\Lunar\RoverClass;
use App\Domain\Lunar\Rules;
use App\Domain\Lunar\Upgrade;
use App\Services\GameFactory;
use App\Services\GarageService;

beforeEach(function () {
    $this->game = app(GameFactory::class)->create(seed: 4242);
    $this->garage = app(GarageService::class);
});

it('buys a rover and charges the price', function () {
    $this->game->update(['credits' => 5000]);

    $rover = $this->garage->buy($this->game->fresh(), RoverClass::Hauler);

    expect($this->game->fresh()->credits)->toBe(5000 - Rules::ROVER_PRICES['hauler']);
    expect($this->game->fresh()->rovers()->count())->toBe(4);
    expect($rover->status)->toBe('idle');
    expect($rover->battery_level)->toBe((float) RoverClass::Hauler->batteryCapacity());
    expect($rover->name)->toBe('R4');
});

it('refuses a purchase without enough credits', function () {
    $this->game->update(['credits' => 100]);

    expect(fn () => $this->garage->buy($this->game->fresh(), RoverClass::Hauler))
        ->toThrow(DomainException::class);

    expect($this->game->fresh()->rovers()->count())->toBe(3);
    expect($this->game->fresh()->credits)->toBe(100);
});

it('refuses to exceed the fleet limit', function () {
    $this->game->update(['credits' => 100000]);

    while ($this->game->fresh()->rovers()->count() < Rules::MAX_FLEET) {
        $this->garage->buy($this->game->fresh(), RoverClass::Scout);
    }

    expect(fn () => $this->garage->buy($this->game->fresh(), RoverClass::Scout))
        ->toThrow(DomainException::class);

    expect($this->game->fresh()->rovers()->count())->toBe(Rules::MAX_FLEET);
});

it('installs a battery upgrade and raises capacity', function () {
    $this->game->update(['credits' => 5000]);
    $rover = $this->game->rovers()->first();
    $before = $rover->battery_capacity;

    $this->garage->upgrade($this->game->fresh(), $rover, Upgrade::Battery);

    $rover = $rover->fresh();

    expect($rover->battery_capacity)->toBe(Upgrade::Battery->apply($before));
    expect($rover->battery_upgraded)->toBeTrue();
    expect($this->game->fresh()->credits)->toBe(5000 - Rules::UPGRADE_BATTERY_COST);
});

it('delivers the extra cells already charged', function () {
    $this->game->update(['credits' => 5000]);
    $rover = $this->game->rovers()->first();
    $rover->update(['battery_level' => 40.0]);
    $before = $rover->battery_capacity;

    $this->garage->upgrade($this->game->fresh(), $rover->fresh(), Upgrade::Battery);

    $gain = Upgrade::Battery->apply($before) - $before;

    expect($rover->fresh()->battery_level)->toBe(40.0 + $gain);
});

it('installs a cargo upgrade', function () {
    $this->game->update(['credits' => 5000]);
    $rover = $this->game->rovers()->first();
    $before = $rover->capacity_kg;

    $this->garage->upgrade($this->game->fresh(), $rover, Upgrade::Capacity);

    expect($rover->fresh()->capacity_kg)->toBe(Upgrade::Capacity->apply($before));
    expect($rover->fresh()->capacity_upgraded)->toBeTrue();
});

it('refuses to install the same upgrade twice', function () {
    $this->game->update(['credits' => 5000]);
    $rover = $this->game->rovers()->first();

    $this->garage->upgrade($this->game->fresh(), $rover, Upgrade::Battery);

    expect(fn () => $this->garage->upgrade($this->game->fresh(), $rover->fresh(), Upgrade::Battery))
        ->toThrow(DomainException::class);
});

it('refuses to upgrade a rover that is away', function () {
    $this->game->update(['credits' => 5000]);
    $rover = $this->game->rovers()->first();
    $rover->update(['status' => 'en_route']);

    expect(fn () => $this->garage->upgrade($this->game->fresh(), $rover->fresh(), Upgrade::Battery))
        ->toThrow(DomainException::class);
});

it('closes the garage once the run is over', function () {
    $this->game->update(['credits' => 5000, 'status' => 'won']);

    expect(fn () => $this->garage->buy($this->game->fresh(), RoverClass::Scout))
        ->toThrow(DomainException::class);
});

it('logs garage activity', function () {
    $this->game->update(['credits' => 5000]);

    $this->garage->buy($this->game->fresh(), RoverClass::Scout);

    expect($this->game->events()->where('type', 'garage')->count())->toBe(1);
});

it('buys a rover through the console', function () {
    $this->game->update(['credits' => 5000]);
    $this->withSession(['game_id' => $this->game->id]);

    $this->post('/garage/buy', ['rover_class' => 'scout'])->assertRedirect('/');

    expect($this->game->fresh()->rovers()->count())->toBe(4);
});

it('reports a failed purchase to the player', function () {
    $this->game->update(['credits' => 10]);
    $this->withSession(['game_id' => $this->game->id]);

    $this->post('/garage/buy', ['rover_class' => 'hauler'])
        ->assertRedirect('/')
        ->assertSessionHas('error');

    expect($this->game->fresh()->rovers()->count())->toBe(3);
});

it('upgrades a rover through the console', function () {
    $this->game->update(['credits' => 5000]);
    $this->withSession(['game_id' => $this->game->id]);
    $rover = $this->game->rovers()->first();

    $this->post('/garage/upgrade', ['rover_id' => $rover->id, 'upgrade' => 'capacity'])
        ->assertRedirect('/');

    expect($rover->fresh()->capacity_upgraded)->toBeTrue();
});

it('rejects an upgrade for a rover from another game', function () {
    $other = app(GameFactory::class)->create(seed: 11);
    $this->game->update(['credits' => 5000]);
    $this->withSession(['game_id' => $this->game->id]);

    $this->post('/garage/upgrade', [
        'rover_id' => $other->rovers()->first()->id,
        'upgrade' => 'battery',
    ])->assertNotFound();
});

it('shows the garage on the console', function () {
    $this->withSession(['game_id' => $this->game->id]);

    $this->get('/')
        ->assertOk()
        ->assertSee('гараж базы')
        ->assertSee('пополнить парк');
});
