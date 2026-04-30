<?php

namespace App\Contracts;

use App\DataTransferObjects\MatchScore;
use App\Models\Team;

interface MatchScoreCalculatorInterface
{
    public function calculate(Team $homeTeam, Team $awayTeam): MatchScore;
}
