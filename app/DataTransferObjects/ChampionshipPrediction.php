<?php

namespace App\DataTransferObjects;

use App\Models\Team;

final class ChampionshipPrediction
{
    public function __construct(
        public readonly Team  $team,
        public readonly float $percentage,
    ) {}
}
