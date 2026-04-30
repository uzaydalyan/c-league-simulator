<?php

namespace App\Contracts;

use App\Models\Fixture;
use Illuminate\Database\Eloquent\Collection;

interface SimulationServiceInterface
{
    public function playWeek(int $week): Collection;

    public function playAll(): Collection;

    public function editResult(Fixture $fixture, int $homeScore, int $awayScore): Fixture;
}
