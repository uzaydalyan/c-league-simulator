<?php

namespace App\Services;

use App\Contracts\MatchScoreCalculatorInterface;
use App\Contracts\SimulationServiceInterface;
use App\DataTransferObjects\MatchScore;
use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SimulationService implements SimulationServiceInterface
{
    public function __construct(
        private readonly MatchScoreCalculatorInterface $calculator,
    ) {}

    public function playMatch(Fixture $fixture): Fixture
    {
        if ($fixture->is_played) {
            throw new \RuntimeException("Fixture #{$fixture->id} has already been played.");
        }

        $fixture->loadMissing(['homeTeam', 'awayTeam']);

        $score = $this->calculator->calculate($fixture->homeTeam, $fixture->awayTeam);

        DB::transaction(function () use ($fixture, $score): void {
            $this->persistResult($fixture, $score);
            $this->applyStatsToTeam($fixture->home_team_id, $score->homeScore, $score->awayScore);
            $this->applyStatsToTeam($fixture->away_team_id, $score->awayScore, $score->homeScore);
        });

        return $fixture->fresh(['homeTeam', 'awayTeam']);
    }

    public function playWeek(int $week): Collection
    {
        $fixtures = Fixture::with(['homeTeam', 'awayTeam'])
            ->where('week', $week)
            ->where('is_played', false)
            ->get();

        if ($fixtures->isEmpty()) {
            throw new \RuntimeException("No unplayed fixtures found for week {$week}.");
        }

        $fixtures->each(fn(Fixture $fixture) => $this->playMatch($fixture));

        return Fixture::with(['homeTeam', 'awayTeam'])
            ->where('week', $week)
            ->get();
    }

    public function playAll(): Collection
    {
        $unplayed = Fixture::with(['homeTeam', 'awayTeam'])
            ->where('is_played', false)
            ->orderBy('week')
            ->get();

        if ($unplayed->isEmpty()) {
            throw new \RuntimeException('All fixtures have already been played.');
        }

        $unplayed->each(fn(Fixture $fixture) => $this->playMatch($fixture));

        return Fixture::with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->get();
    }

    // Reverses the old result before applying the new one if fixture was already played.
    public function editResult(Fixture $fixture, int $homeScore, int $awayScore): Fixture
    {
        DB::transaction(function () use ($fixture, $homeScore, $awayScore): void {
            if ($fixture->is_played) {
                $this->reverseStatsOnTeam($fixture->home_team_id, $fixture->home_score, $fixture->away_score);
                $this->reverseStatsOnTeam($fixture->away_team_id, $fixture->away_score, $fixture->home_score);
            }

            $score = new MatchScore($homeScore, $awayScore);
            $this->persistResult($fixture, $score);
            $this->applyStatsToTeam($fixture->home_team_id, $homeScore, $awayScore);
            $this->applyStatsToTeam($fixture->away_team_id, $awayScore, $homeScore);
        });

        return $fixture->fresh(['homeTeam', 'awayTeam']);
    }

    private function persistResult(Fixture $fixture, MatchScore $score): void
    {
        $fixture->update([
            'home_score' => $score->homeScore,
            'away_score' => $score->awayScore,
            'is_played'  => true,
        ]);
    }

    // Raw SQL increments avoid stale model data overwriting DB state between concurrent requests.
    private function applyStatsToTeam(int $teamId, int $scored, int $conceded): void
    {
        $isWin  = $scored > $conceded;
        $isDraw = $scored === $conceded;
        $isLoss = $scored < $conceded;

        Team::where('id', $teamId)->update([
            'played'        => DB::raw('played + 1'),
            'goals_for'     => DB::raw("goals_for + {$scored}"),
            'goals_against' => DB::raw("goals_against + {$conceded}"),
            'won'           => DB::raw('won + ' . ($isWin  ? 1 : 0)),
            'drawn'         => DB::raw('drawn + ' . ($isDraw ? 1 : 0)),
            'lost'          => DB::raw('lost + ' . ($isLoss ? 1 : 0)),
            'points'        => DB::raw('points + ' . ($isWin ? 3 : ($isDraw ? 1 : 0))),
        ]);
    }

    private function reverseStatsOnTeam(int $teamId, int $scored, int $conceded): void
    {
        $wasWin  = $scored > $conceded;
        $wasDraw = $scored === $conceded;
        $wasLoss = $scored < $conceded;

        Team::where('id', $teamId)->update([
            'played'        => DB::raw('played - 1'),
            'goals_for'     => DB::raw("goals_for - {$scored}"),
            'goals_against' => DB::raw("goals_against - {$conceded}"),
            'won'           => DB::raw('won - ' . ($wasWin  ? 1 : 0)),
            'drawn'         => DB::raw('drawn - ' . ($wasDraw ? 1 : 0)),
            'lost'          => DB::raw('lost - ' . ($wasLoss ? 1 : 0)),
            'points'        => DB::raw('points - ' . ($wasWin ? 3 : ($wasDraw ? 1 : 0))),
        ]);
    }
}
