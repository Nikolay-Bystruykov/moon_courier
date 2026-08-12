<?php

namespace App\Services;

use App\Domain\Lunar\Coordinate;
use App\Domain\Lunar\LunarMap;
use App\Domain\Lunar\MapGenerator;
use App\Domain\Lunar\RouteFinder;
use App\Domain\Lunar\RoverClass;
use App\Domain\Lunar\Rules;
use App\Domain\Lunar\SeededRandom;
use App\Models\Game;
use App\Models\Outpost;
use App\Models\Rover;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GameFactory
{
    /** Карта вымышленная, названия объектов — настоящие лунные. */
    private const OUTPOST_NAMES = [
        'Море Спокойствия',
        'Тихо',
        'Коперник',
        'Аристарх',
        'Море Дождей',
        'Платон',
        'Клавий',
        'Борозда Хэдли',
    ];

    private const ROVER_NAMES = [
        'crawler' => 'R1',
        'scout' => 'R2',
        'hauler' => 'R3',
    ];

    public function __construct(
        private readonly MapRepository $maps,
        private readonly OrderGenerator $orders,
    ) {
    }

    public function create(?int $seed = null): Game
    {
        // Выбор зерна новой партии — единственное место, где допустима
        // недетерминированная случайность. Всё остальное следует из него.
        $seed ??= random_int(1, PHP_INT_MAX - 1);

        return DB::transaction(function () use ($seed) {
            $rng = new SeededRandom($seed);

            $game = Game::create([
                'seed' => $seed,
                'day' => 1,
                'credits' => Rules::START_CREDITS,
                'reputation' => Rules::START_REPUTATION,
                'status' => 'active',
            ]);

            $map = MapGenerator::generate($rng);
            $this->maps->store($game, $map);

            $this->placeOutposts($game, $map, $rng);
            $this->buildFleet($game);

            $this->orders->generate(
                $game->refresh(),
                $rng,
                $rng->nextInt(Rules::ORDERS_PER_DAY_MIN, Rules::ORDERS_PER_DAY_MAX),
            );

            return $game->refresh();
        });
    }

    /**
     * Аванпосты разносятся по поясам дальности: ближние доступны любому
     * роверу, дальние — только Тягачу и только налегке. Без этого случайная
     * расстановка сбивает почти все точки в один пояс, и выбор ровера
     * перестаёт что-либо значить.
     *
     * Стоимости до всех клеток берутся одним проходом Дейкстры, а не поиском
     * маршрута до каждой клетки по отдельности.
     */
    private function placeOutposts(Game $game, LunarMap $map, SeededRandom $rng): void
    {
        $base = new Coordinate(Rules::BASE_X, Rules::BASE_Y);
        $costs = RouteFinder::costsFrom($map, $base);

        unset($costs[$base->key()]);

        $names = self::OUTPOST_NAMES;
        $taken = [];

        foreach (Rules::OUTPOST_BANDS as $band) {
            for ($i = 0; $i < $band['count']; $i++) {
                $candidates = array_filter(
                    $costs,
                    fn (float $cost, string $key) => ! isset($taken[$key])
                        && $cost >= $band['min']
                        && $cost <= $band['max'],
                    ARRAY_FILTER_USE_BOTH,
                );

                // На «дешёвой» карте дальний пояс может оказаться пустым:
                // тогда берём самые далёкие клетки из доступных.
                if ($candidates === []) {
                    $candidates = $this->farthestAvailable($costs, $taken);
                }

                if ($candidates === []) {
                    throw new RuntimeException("Не удалось разместить аванпосты на карте партии {$game->id}");
                }

                $key = $rng->pick(array_keys($candidates));
                $taken[$key] = true;

                [$x, $y] = array_map('intval', explode(':', $key));

                Outpost::create([
                    'game_id' => $game->id,
                    'name' => array_shift($names),
                    'x' => $x,
                    'y' => $y,
                    'route_cost' => round($costs[$key], 2),
                ]);
            }
        }
    }

    /**
     * @param  array<string, float>  $costs
     * @param  array<string, true>  $taken
     * @return array<string, float>
     */
    private function farthestAvailable(array $costs, array $taken): array
    {
        $free = array_filter(
            $costs,
            fn (float $cost, string $key) => ! isset($taken[$key]) && $cost >= Rules::MIN_OUTPOST_COST,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($free === []) {
            return [];
        }

        arsort($free);

        return array_slice($free, 0, 5, preserve_keys: true);
    }

    private function buildFleet(Game $game): void
    {
        foreach (RoverClass::cases() as $class) {
            Rover::create([
                'game_id' => $game->id,
                'name' => self::ROVER_NAMES[$class->value],
                'rover_class' => $class,
                'capacity_kg' => $class->capacityKg(),
                'battery_capacity' => $class->batteryCapacity(),
                'battery_level' => $class->batteryCapacity(),
                'status' => 'idle',
                'repair_days_left' => 0,
            ]);
        }
    }
}
