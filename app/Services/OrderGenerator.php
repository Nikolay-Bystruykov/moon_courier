<?php

namespace App\Services;

use App\Domain\Lunar\Rules;
use App\Domain\Lunar\SeededRandom;
use App\Models\Game;
use App\Models\Order;
use App\Models\Outpost;

class OrderGenerator
{
    public function generate(Game $game, SeededRandom $rng, int $count): void
    {
        $outposts = $game->outposts()->orderBy('id')->get();

        if ($outposts->isEmpty()) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            /** @var Outpost $outpost */
            $outpost = $rng->pick($outposts->all());

            Order::create($this->buildOrder(
                $game,
                $outpost,
                $rng->nextInt(Rules::ORDER_WEIGHT_MIN, Rules::ORDER_WEIGHT_MAX),
                $rng->nextInt(Rules::ORDER_DEADLINE_MIN, Rules::ORDER_DEADLINE_MAX),
            ));
        }
    }

    /**
     * Собирает поля заказа без записи в базу — так награду можно проверить
     * тестом, не создавая партию целиком.
     *
     * @return array<string, mixed>
     */
    public function buildOrder(Game $game, Outpost $outpost, int $weight, int $deadlineIn): array
    {
        return [
            'game_id' => $game->id,
            'outpost_id' => $outpost->id,
            'weight_kg' => $weight,
            'reward' => $this->reward($weight, $outpost->route_cost, $deadlineIn),
            'deadline_day' => $game->day + $deadlineIn,
            'created_day' => $game->day,
            'status' => 'pending',
        ];
    }

    /**
     * Награда растёт от веса, дальности и срочности: короткий срок на дальний
     * аванпост должен окупать риск, иначе такие заказы никто не берёт и они
     * просто сгорают.
     */
    private function reward(int $weight, float $routeCost, int $deadlineIn): int
    {
        $urgency = 1 + (Rules::ORDER_DEADLINE_MAX - $deadlineIn) * Rules::URGENCY_BONUS;

        return (int) round(($weight * Rules::REWARD_PER_KG + $routeCost * Rules::REWARD_PER_COST) * $urgency);
    }
}
