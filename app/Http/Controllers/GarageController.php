<?php

namespace App\Http\Controllers;

use App\Domain\Lunar\RoverClass;
use App\Domain\Lunar\Upgrade;
use App\Models\Game;
use App\Services\GarageService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GarageController extends Controller
{
    public function __construct(private readonly GarageService $garage)
    {
    }

    public function buy(Request $request): RedirectResponse
    {
        $game = $this->currentGame($request);

        $validated = $request->validate([
            'rover_class' => ['required', Rule::enum(RoverClass::class)],
        ]);

        return $this->run(fn () => $this->garage->buy($game, RoverClass::from($validated['rover_class'])));
    }

    public function upgrade(Request $request): RedirectResponse
    {
        $game = $this->currentGame($request);

        $validated = $request->validate([
            'rover_id' => ['required', 'integer'],
            'upgrade' => ['required', Rule::enum(Upgrade::class)],
        ]);

        $rover = $game->rovers()->find($validated['rover_id']);

        if ($rover === null) {
            throw new NotFoundHttpException('Ровер не принадлежит текущей партии');
        }

        return $this->run(fn () => $this->garage->upgrade($game, $rover, Upgrade::from($validated['upgrade'])));
    }

    private function run(callable $action): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $exception) {
            return redirect()->route('game.show')->with('error', $exception->getMessage());
        }

        return redirect()->route('game.show');
    }

    private function currentGame(Request $request): Game
    {
        $game = Game::find($request->session()->get('game_id'));

        if ($game === null) {
            throw new NotFoundHttpException('Партия не найдена');
        }

        return $game;
    }
}
