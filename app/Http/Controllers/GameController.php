<?php

namespace App\Http\Controllers;

use App\Domain\Lunar\Rules;
use App\Domain\Lunar\Terrain;
use App\Models\Game;
use App\Services\GameFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private readonly GameFactory $factory)
    {
    }

    public function show(Request $request): View
    {
        return view('game.show', $this->viewData($this->currentGame($request)));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->session()->put('game_id', $this->factory->create()->id);

        return redirect()->route('game.show');
    }

    private function currentGame(Request $request): Game
    {
        $game = Game::find($request->session()->get('game_id'));

        if ($game === null) {
            $game = $this->factory->create();
            $request->session()->put('game_id', $game->id);
        }

        return $game;
    }

    /** @return array<string, mixed> */
    private function viewData(Game $game): array
    {
        $game->load(['tiles', 'outposts', 'rovers', 'orders.outpost']);

        $pending = $game->orders->where('status', 'pending');
        $byOutpost = $pending->groupBy('outpost_id');

        return [
            'game' => $game,
            'map' => [
                'width' => Rules::MAP_WIDTH,
                'height' => Rules::MAP_HEIGHT,
                'tiles' => $game->tiles->map(fn ($tile) => [
                    'x' => $tile->x,
                    'y' => $tile->y,
                    'terrain' => $tile->terrain->value,
                    'label' => $tile->terrain->label(),
                ])->all(),
            ],
            'base' => ['x' => Rules::BASE_X, 'y' => Rules::BASE_Y],
            'legend' => array_map(fn (Terrain $terrain) => [
                'value' => $terrain->value,
                'label' => $terrain->label(),
                'cost' => $terrain->moveCost(),
            ], Terrain::cases()),
            'outposts' => $this->outpostMarkers($game, $byOutpost),
            'roversOnMap' => $this->roverMarkers($game),
            // Клик по аванпосту выбирает его первый ожидающий заказ: карта
            // должна быть способом управления, а не иллюстрацией.
            'ordersByOutpost' => $byOutpost->map(fn ($orders) => $orders->first()->id)->all(),
            'rovers' => $game->rovers->map(fn ($rover) => [
                'id' => $rover->id,
                'name' => $rover->name,
                'class_label' => $rover->rover_class->label(),
                'class_note' => $rover->rover_class->description(),
                'capacity_kg' => $rover->capacity_kg,
                'battery_level' => round($rover->battery_level),
                'battery_capacity' => $rover->battery_capacity,
                'battery_percent' => (int) round($rover->battery_level / $rover->battery_capacity * 100),
                'available' => $rover->isAvailable(),
                'status_label' => match ($rover->status) {
                    'idle' => 'на базе',
                    'en_route' => 'в рейсе',
                    'repair' => 'ремонт '.$rover->repair_days_left.' сут',
                    default => $rover->status,
                },
            ])->all(),
            'orders' => $pending->map(fn ($order) => [
                'id' => $order->id,
                'outpost' => $order->outpost->name,
                'outpost_id' => $order->outpost_id,
                'weight_kg' => $order->weight_kg,
                'reward' => $order->reward,
                'days_left' => $order->deadline_day - $game->day,
            ])->values()->all(),
            'events' => $game->events()
                ->orderByDesc('day')
                ->orderByDesc('id')
                ->limit(24)
                ->get()
                ->map(fn ($event) => [
                    'day' => $event->day,
                    'type' => $event->type,
                    'message' => $event->message,
                ])->all(),
            'delivered' => $game->orders->where('status', 'delivered')->count(),
            'inTransit' => $game->deliveries()->where('status', 'in_transit')->count(),
        ];
    }

    /**
     * Раскладывает подписи аванпостов так, чтобы они не наезжали друг на
     * друга: соседние точки получают разные вертикальные уровни, а нижний ряд
     * подписывается сверху, иначе текст уходит за край карты.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $byOutpost
     * @return array<int, array<string, mixed>>
     */
    private function outpostMarkers(Game $game, $byOutpost): array
    {
        $placed = [];
        $markers = [];

        // Ближние к базе подписываются первыми: их читают чаще.
        foreach ($game->outposts->sortBy('route_cost') as $outpost) {
            $halfWidth = mb_strlen($outpost->name) * 0.31;
            $above = $outpost->y >= Rules::MAP_HEIGHT - 2;

            $level = 0;

            while ($this->labelCollides($placed, $outpost->x, $outpost->y, $halfWidth, $level, $above)) {
                $level++;
            }

            $placed[] = [
                'x' => $outpost->x,
                'y' => $outpost->y,
                'halfWidth' => $halfWidth,
                'level' => $level,
                'above' => $above,
            ];

            $markers[] = [
                'id' => $outpost->id,
                'name' => $outpost->name,
                'x' => $outpost->x,
                'y' => $outpost->y,
                'route_cost' => $outpost->route_cost,
                'pending' => $byOutpost->get($outpost->id)?->count() ?? 0,
                'label_above' => $above,
                'label_level' => $level,
            ];
        }

        return $markers;
    }

    /** @param  array<int, array<string, mixed>>  $placed */
    private function labelCollides(array $placed, int $x, int $y, float $halfWidth, int $level, bool $above): bool
    {
        foreach ($placed as $other) {
            $sameRow = $other['above'] === $above
                && $other['level'] === $level
                && abs($other['y'] - $y) < 1;

            if ($sameRow && abs($other['x'] - $x) < $halfWidth + $other['halfWidth'] + 0.6) {
                return true;
            }
        }

        return false;
    }

    /**
     * Роверы на базе стоят рядом с ней, ушедшие в рейс показываются у своей
     * цели: игрок должен видеть, где сейчас парк, не открывая панели.
     *
     * @return array<int, array<string, mixed>>
     */
    private function roverMarkers(Game $game): array
    {
        $destinations = $game->deliveries()
            ->with('order.outpost')
            ->where('status', 'in_transit')
            ->get()
            ->keyBy('rover_id');

        $markers = [];
        $atBase = 0;

        foreach ($game->rovers as $rover) {
            $delivery = $destinations->get($rover->id);

            if ($delivery !== null) {
                $markers[] = [
                    'name' => $rover->name,
                    'x' => $delivery->order->outpost->x,
                    'y' => $delivery->order->outpost->y,
                    'slot' => 0,
                    'en_route' => true,
                ];

                continue;
            }

            $markers[] = [
                'name' => $rover->name,
                'x' => Rules::BASE_X,
                'y' => Rules::BASE_Y,
                'slot' => $atBase++,
                'en_route' => false,
            ];
        }

        return $markers;
    }
}
