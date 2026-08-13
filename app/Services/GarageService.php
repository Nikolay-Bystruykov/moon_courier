<?php

namespace App\Services;

use App\Domain\Lunar\RoverClass;
use App\Domain\Lunar\Rules;
use App\Domain\Lunar\Upgrade;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Rover;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Гараж базы: покупка роверов и установка улучшений.
 *
 * Благодаря нему кредиты становятся выбором, а не счётчиком очков: те же
 * деньги уходят либо на ремонт после неудачного рейса, либо на расширение
 * парка.
 */
class GarageService
{
    public function buy(Game $game, RoverClass $class): Rover
    {
        $price = Rules::ROVER_PRICES[$class->value];

        if (! $game->isActive()) {
            throw new DomainException('Смена завершена, гараж закрыт');
        }

        if ($game->rovers()->count() >= Rules::MAX_FLEET) {
            throw new DomainException('База не обслуживает больше '.Rules::MAX_FLEET.' роверов');
        }

        if ($game->credits < $price) {
            throw new DomainException(sprintf(
                'Не хватает кредитов: ровер стоит %d, в кассе %d',
                $price,
                $game->credits,
            ));
        }

        return DB::transaction(function () use ($game, $class, $price) {
            $game->decrement('credits', $price);

            $rover = Rover::create([
                'game_id' => $game->id,
                'name' => 'R'.($game->rovers()->count() + 1),
                'rover_class' => $class,
                'capacity_kg' => $class->capacityKg(),
                'battery_capacity' => $class->batteryCapacity(),
                'battery_level' => $class->batteryCapacity(),
                'status' => 'idle',
                'repair_days_left' => 0,
            ]);

            GameEvent::create([
                'game_id' => $game->id,
                'day' => $game->day,
                'type' => 'garage',
                'message' => sprintf('Гараж: куплен %s %s за %d кр', $rover->name, $class->label(), $price),
                'payload' => ['rover_id' => $rover->id],
            ]);

            return $rover;
        });
    }

    public function upgrade(Game $game, Rover $rover, Upgrade $upgrade): Rover
    {
        if (! $game->isActive()) {
            throw new DomainException('Смена завершена, гараж закрыт');
        }

        if ($rover->status === 'en_route') {
            throw new DomainException('Ровер в рейсе, установить улучшение не на что');
        }

        if ($this->alreadyInstalled($rover, $upgrade)) {
            throw new DomainException('Это улучшение уже установлено');
        }

        if ($game->credits < $upgrade->cost()) {
            throw new DomainException(sprintf(
                'Не хватает кредитов: улучшение стоит %d, в кассе %d',
                $upgrade->cost(),
                $game->credits,
            ));
        }

        return DB::transaction(function () use ($game, $rover, $upgrade) {
            $game->decrement('credits', $upgrade->cost());

            if ($upgrade === Upgrade::Battery) {
                $capacity = $upgrade->apply($rover->battery_capacity);

                // Прибавка ёмкости сразу доступна: новые ячейки приходят заряженными.
                $rover->update([
                    'battery_capacity' => $capacity,
                    'battery_level' => $rover->battery_level + ($capacity - $rover->battery_capacity),
                    'battery_upgraded' => true,
                ]);
            } else {
                $rover->update([
                    'capacity_kg' => $upgrade->apply($rover->capacity_kg),
                    'capacity_upgraded' => true,
                ]);
            }

            GameEvent::create([
                'game_id' => $game->id,
                'day' => $game->day,
                'type' => 'garage',
                'message' => sprintf(
                    'Гараж: %s — %s %s за %d кр',
                    $rover->name,
                    $upgrade->label(),
                    $upgrade->note(),
                    $upgrade->cost(),
                ),
                'payload' => ['rover_id' => $rover->id, 'upgrade' => $upgrade->value],
            ]);

            return $rover->refresh();
        });
    }

    private function alreadyInstalled(Rover $rover, Upgrade $upgrade): bool
    {
        return $upgrade === Upgrade::Battery
            ? $rover->battery_upgraded
            : $rover->capacity_upgraded;
    }
}
