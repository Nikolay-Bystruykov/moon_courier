<?php

namespace App\Http\Controllers;

use App\Domain\Lunar\RejectionReason;
use App\Domain\Lunar\Rules;
use App\Models\Game;
use App\Models\Order;
use App\Models\Rover;
use App\Services\MissionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MissionController extends Controller
{
    public function __construct(private readonly MissionService $missions)
    {
    }

    public function estimate(Request $request): JsonResponse
    {
        [$game, $rover, $order] = $this->resolve($request);

        $plan = $this->missions->plan($game, $rover, $order);
        $estimate = $plan->estimate;

        return response()->json([
            'allowed' => $plan->validation->allowed,
            'reasons' => $this->explain($plan, $rover, $order),
            'route' => $estimate?->route->toArray() ?? [],
            'estimate' => $estimate === null ? null : [
                'route_length' => $estimate->route->length(),
                'battery_cost' => round($estimate->batteryCost, 1),
                'battery_after' => round($estimate->batteryAfter, 1),
                'battery_percent_after' => (int) round($estimate->batteryAfter / $rover->battery_capacity * 100),
                'days' => $estimate->days,
                'return_day' => $estimate->returnDay,
                'risk' => (int) round($estimate->risk->total * 100),
                'risk_components' => array_map(fn (array $part) => [
                    'label' => $part['label'],
                    'value' => (int) round($part['value'] * 100),
                ], $estimate->risk->components()),
            ],
            'order' => [
                'weight_kg' => $order->weight_kg,
                'reward' => $order->reward,
                'outpost' => $order->outpost->name,
                'days_left' => $order->deadline_day - $game->day,
            ],
            'rover' => [
                'name' => $rover->name,
                'capacity_kg' => $rover->capacity_kg,
            ],
        ]);
    }

    public function dispatch(Request $request): RedirectResponse
    {
        [$game, $rover, $order] = $this->resolve($request);

        try {
            $this->missions->dispatch($game, $rover, $order);
        } catch (DomainException $exception) {
            return redirect()->route('game.show')->with('error', $exception->getMessage());
        }

        return redirect()->route('game.show');
    }

    /**
     * Причины отказа с конкретными числами там, где голая формулировка
     * оставляет вопрос «а насколько не хватило».
     *
     * @return string[]
     */
    private function explain($plan, Rover $rover, Order $order): array
    {
        $estimate = $plan->estimate;

        return array_map(function (RejectionReason $reason) use ($estimate, $rover, $order) {
            return match ($reason) {
                RejectionReason::InsufficientBattery => sprintf(
                    'Заряда не хватит на дорогу туда и обратно: нужно %s, доступно %s из %d',
                    number_format($estimate->batteryCost, 1, ',', ' '),
                    number_format($rover->battery_level - $rover->battery_capacity * Rules::BATTERY_RESERVE, 1, ',', ' '),
                    $rover->battery_capacity,
                ),
                RejectionReason::Overweight => sprintf(
                    'Груз тяжелее грузоподъёмности ровера: %d кг против %d кг',
                    $order->weight_kg,
                    $rover->capacity_kg,
                ),
                default => $reason->message(),
            };
        }, $plan->validation->reasons);
    }

    /** @return array{0: Game, 1: Rover, 2: Order} */
    private function resolve(Request $request): array
    {
        $game = Game::find($request->session()->get('game_id'));

        if ($game === null) {
            throw new NotFoundHttpException('Партия не найдена');
        }

        $rover = $game->rovers()->find($request->integer('rover_id'));
        $order = $game->orders()->with('outpost')->find($request->integer('order_id'));

        // Ровер и заявка обязаны принадлежать текущей партии: иначе чужой
        // идентификатор позволил бы управлять объектами другой игры.
        if ($rover === null || $order === null) {
            throw new NotFoundHttpException('Ровер или заявка не принадлежат текущей партии');
        }

        return [$game, $rover, $order];
    }
}
