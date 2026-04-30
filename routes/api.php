<?php

use App\Http\Controllers\Api\FixtureController;
use App\Http\Controllers\Api\LeagueStateController;
use App\Http\Controllers\Api\SimulationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('league/state', [LeagueStateController::class, 'index']);

    Route::post('fixtures/generate', [FixtureController::class, 'generate']);
    Route::post('fixtures/reset', [FixtureController::class, 'reset']);
    Route::put('fixtures/{fixture}', [FixtureController::class, 'update']);

    Route::post('simulation/week/{week}', [SimulationController::class, 'playWeek']);
    Route::post('simulation/play-all', [SimulationController::class, 'playAll']);
});
