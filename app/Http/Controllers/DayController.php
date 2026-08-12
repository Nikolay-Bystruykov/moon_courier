<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\DayResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DayController extends Controller
{
    public function __construct(private readonly DayResolver $resolver)
    {
    }

    public function advance(Request $request): RedirectResponse
    {
        $game = Game::find($request->session()->get('game_id'));

        if ($game === null) {
            throw new NotFoundHttpException('Партия не найдена');
        }

        $this->resolver->advance($game);

        return redirect()->route('game.show');
    }
}
