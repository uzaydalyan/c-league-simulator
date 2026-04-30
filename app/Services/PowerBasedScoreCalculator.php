<?php

namespace App\Services;

use App\Contracts\MatchScoreCalculatorInterface;
use App\DataTransferObjects\MatchScore;
use App\Models\Team;

class PowerBasedScoreCalculator implements MatchScoreCalculatorInterface
{
    private const HOME_ADVANTAGE    = 1.15;  // home teams win ~46% of EPL matches
    private const AVERAGE_TOTAL_GOALS = 2.7; // average goals per EPL match

    /*
     * λ_home = (home_power × HOME_ADVANTAGE / total_power) × AVERAGE_TOTAL_GOALS
     * λ_away = (away_power / total_power) × AVERAGE_TOTAL_GOALS
     * Goals are sampled independently via Knuth's Poisson algorithm.
     */
    public function calculate(Team $homeTeam, Team $awayTeam): MatchScore
    {
        $homeEffectivePower = $homeTeam->power * self::HOME_ADVANTAGE;
        $awayEffectivePower = $awayTeam->power;
        $totalPower         = $homeEffectivePower + $awayEffectivePower;

        $homeLambda = ($homeEffectivePower / $totalPower) * self::AVERAGE_TOTAL_GOALS;
        $awayLambda = ($awayEffectivePower / $totalPower) * self::AVERAGE_TOTAL_GOALS;

        return new MatchScore(
            homeScore: $this->samplePoisson($homeLambda),
            awayScore: $this->samplePoisson($awayLambda),
        );
    }

    /*
     * Knuth's algorithm: multiply uniform randoms until their product drops below
     * e^(-λ). The count of multiplications minus one is Poisson-distributed.
     */
    private function samplePoisson(float $lambda): int
    {
        $threshold = exp(-$lambda);
        $product   = 1.0;
        $goals     = 0;

        do {
            $goals++;
            $product *= mt_rand() / mt_getrandmax();
        } while ($product > $threshold);

        return $goals - 1;
    }
}
