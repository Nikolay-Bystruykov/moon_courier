<?php

namespace App\Services;

use App\Domain\Lunar\Coordinate;
use App\Domain\Lunar\MissionPlanner;
use App\Domain\Lunar\MissionResolver;
use App\Domain\Lunar\MissionValidator;
use App\Domain\Lunar\RouteFinder;
use App\Domain\Lunar\Rules;
use App\Models\Delivery;
use App\Models\Game;
use App\Models\GameEvent;
use App\Models\Order;
use App\Models\Rover;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Расчёт и отправка рейса. Единственная точка, через которую ровер уходит
 * с базы, поэтому проверки нельзя обойти.
 */
class MissionService
{
    public function __construct(private readonly MapRepository $maps)
    {
    }

    public function plan(Game $game, Rover $rover, Order $order): MissionPlanResult
    {
        $map = $this->maps->load($game);
        $outpost = $order->outpost;

        $route = RouteFinder::find(
            $map,
            new Coordinate(Rules::BASE_X, Rules::BASE_Y),
            new Coordinate($outpost->x, $outpost->y),
        );

        $estimate = $route === null ? null : MissionPlanner::estimate(
            $map,
            $route,
            $rover->capacity_kg,
            $rover->battery_capacity,
            $rover->battery_level,
            $rover->rover_class->speed(),
            $order->weight_kg,
            $game->day,
            $order->deadline_day,
        );

        $validation = MissionValidator::validate(
            $estimate,
            $order->weight_kg,
            $rover->capacity_kg,
            $rover->battery_capacity,
            $rover->status,
            $rover->repair_days_left,
            $order->status,
            $game->day,
            $order->deadline_day,
        );

        return new MissionPlanResult($estimate, $validation);
    }

    public function dispatch(Game $game, Rover $rover, Order $order): Delivery
    {
        $plan = $this->plan($game, $rover, $order);

        if (! $plan->validation->allowed) {
            throw new DomainException(implode('; ', $plan->validation->messages()));
        }

        return DB::transaction(function () use ($game, $rover, $order, $plan) {
            $estimate = $plan->estimate;

            $delivery = Delivery::create([
                'game_id' => $game->id,
                'rover_id' => $rover->id,
                'order_id' => $order->id,
                'dispatched_day' => $game->day,
                'return_day' => $estimate->returnDay,
                'route' => $estimate->route->toArray(),
                'route_cost' => round($estimate->route->cost, 2),
                'battery_cost' => round($estimate->batteryCost, 2),
                'risk' => round($estimate->risk->total, 4),
                'risk_breakdown' => $estimate->risk->components(),
                'seed' => 0,
                'status' => 'in_transit',
            ]);

            // Зерно выводится из идентификатора рейса, а он известен только
            // после вставки строки.
            $delivery->update(['seed' => MissionResolver::seedFor($game->seed, $delivery->id)]);

            $rover->update(['status' => 'en_route']);
            $order->update(['status' => 'assigned']);

            GameEvent::create([
                'game_id' => $game->id,
                'day' => $game->day,
                'type' => 'dispatch',
                'message' => sprintf(
                    '%s вышел к аванпосту %s: груз %d кг, риск %d%%, возврат на %d сутки',
                    $rover->name,
                    $order->outpost->name,
                    $order->weight_kg,
                    round($estimate->risk->total * 100),
                    $estimate->returnDay,
                ),
                'payload' => ['delivery_id' => $delivery->id],
            ]);

            return $delivery->refresh();
        });
    }
}
