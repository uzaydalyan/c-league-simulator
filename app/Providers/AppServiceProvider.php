<?php

namespace App\Providers;

use App\Contracts\FixtureGeneratorInterface;
use App\Contracts\FixtureServiceInterface;
use App\Contracts\MatchScoreCalculatorInterface;
use App\Contracts\PredictionServiceInterface;
use App\Contracts\SimulationServiceInterface;
use App\Services\FixtureService;
use App\Services\PowerBasedScoreCalculator;
use App\Services\PredictionService;
use App\Services\RoundRobinGenerator;
use App\Services\SimulationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FixtureGeneratorInterface::class, RoundRobinGenerator::class);
        $this->app->bind(MatchScoreCalculatorInterface::class, PowerBasedScoreCalculator::class);
        $this->app->bind(FixtureServiceInterface::class, FixtureService::class);
        $this->app->bind(SimulationServiceInterface::class, SimulationService::class);
        $this->app->bind(PredictionServiceInterface::class, PredictionService::class);
    }

    public function boot(): void
    {
        //
    }
}
