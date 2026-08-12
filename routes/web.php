<?php

use App\Http\Controllers\DayController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\MissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GameController::class, 'show'])->name('game.show');
Route::post('/game', [GameController::class, 'store'])->name('game.new');

Route::get('/mission/estimate', [MissionController::class, 'estimate'])->name('mission.estimate');
Route::post('/mission/dispatch', [MissionController::class, 'dispatch'])->name('mission.dispatch');

Route::post('/day/advance', [DayController::class, 'advance'])->name('day.advance');
