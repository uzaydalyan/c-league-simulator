<?php

namespace App\Services;

use App\Contracts\PredictionServiceInterface;
use App\DataTransferObjects\ChampionshipPrediction;
use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Support\Collection;

class PredictionService implements PredictionServiceInterface
{
    // Large enough to separate tied teams, small enough not to overshadow a points lead.
    private const GOAL_DIFFERENCE_WEIGHT = 0.5;

    public function predict(): Collection
    {
        $teams     = Team::all();
        $remaining = $this->getRemainingMatchCounts();

        $champion = $this->findGuaranteedChampion($teams, $remaining);
        if ($champion !== null) {
            return $this->buildCertainResult($teams, $champion->id);
        }

        $leaderPoints = $teams->max('points');
        $maxPower     = $teams->max('power');

        $scores     = [];
        $totalScore = 0.0;

        foreach ($teams as $team) {
            $teamRemaining = $remaining[$team->id] ?? 0;

            if ($this->isEliminated($team, $teamRemaining, $leaderPoints)) {
                $scores[$team->id] = 0.0;
                continue;
            }

            $score              = $this->calculateScore($team, $teamRemaining, $maxPower);
            $scores[$team->id]  = $score;
            $totalScore        += $score;
        }

        $percentages = $this->normalise($scores, $totalScore);

        return $teams->map(fn(Team $team) => new ChampionshipPrediction(
            team:       $team,
            percentage: $percentages[$team->id],
        ));
    }

    private function getRemainingMatchCounts(): array
    {
        $counts = [];

        Fixture::where('is_played', false)
            ->get(['home_team_id', 'away_team_id'])
            ->each(function (Fixture $fixture) use (&$counts): void {
                $counts[$fixture->home_team_id] = ($counts[$fixture->home_team_id] ?? 0) + 1;
                $counts[$fixture->away_team_id] = ($counts[$fixture->away_team_id] ?? 0) + 1;
            });

        return $counts;
    }

    /*
     * Two ways to clinch: (1) points already exceed every rival's max possible
     * tally; (2) season is over, this team leads on points, and no rival matches
     * its goal difference.
     */
    private function findGuaranteedChampion(Collection $teams, array $remaining): ?Team
    {
        foreach ($teams as $candidate) {
            $beatenByAll = $teams
                ->filter(fn(Team $other) => $other->id !== $candidate->id)
                ->every(fn(Team $other) => $candidate->points > $other->points + ($remaining[$other->id] ?? 0) * 3);

            if ($beatenByAll) {
                return $candidate;
            }
        }

        if (array_sum($remaining) > 0) {
            return null;
        }

        $maxPoints = $teams->max('points');
        $topTeams  = $teams->filter(fn(Team $t) => $t->points === $maxPoints);

        if ($topTeams->count() === 1) {
            return $topTeams->first();
        }

        $maxGD    = $topTeams->max('goal_difference');
        $gdLeader = $topTeams->filter(fn(Team $t) => $t->goal_difference === $maxGD);

        return $gdLeader->count() === 1 ? $gdLeader->first() : null;
    }

    private function isEliminated(Team $team, int $remaining, int $leaderPoints): bool
    {
        return ($team->points + $remaining * 3) < $leaderPoints;
    }

    /*
     * Score = locked points + (remaining × 3 × power ratio) + GD bonus.
     * Clamped to ≥ 0.01 so a negative GD never makes a live contender appear eliminated.
     */
    private function calculateScore(Team $team, int $remaining, int $maxPower): float
    {
        $powerRatio   = $team->power / $maxPower;
        $lockedPoints = (float) $team->points;
        $expectedGain = $remaining * 3.0 * $powerRatio;
        $gdBonus      = $team->goal_difference * self::GOAL_DIFFERENCE_WEIGHT;

        return max(0.01, $lockedPoints + $expectedGain + $gdBonus);
    }

    private function normalise(array $scores, float $totalScore): array
    {
        if ($totalScore <= 0.0) {
            $count = count(array_filter($scores, fn(float $s) => $s >= 0.0));
            $share = $count > 0 ? round(100.0 / $count, 2) : 0.0;
            return array_fill_keys(array_keys($scores), $share);
        }

        $percentages = array_map(
            fn(float $score) => $score > 0.0 ? round($score / $totalScore * 100.0, 2) : 0.0,
            $scores,
        );

        // Absorb floating-point rounding residual into the highest scorer.
        $residual = round(100.0 - array_sum($percentages), 2);
        if ($residual !== 0.0) {
            $topId               = (int) array_search(max($percentages), $percentages);
            $percentages[$topId] = round($percentages[$topId] + $residual, 2);
        }

        return $percentages;
    }

    private function buildCertainResult(Collection $teams, int $championId): Collection
    {
        return $teams->map(fn(Team $team) => new ChampionshipPrediction(
            team:       $team,
            percentage: $team->id === $championId ? 100.0 : 0.0,
        ));
    }
}
