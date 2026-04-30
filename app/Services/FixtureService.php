<?php

namespace App\Services;

use App\Contracts\FixtureGeneratorInterface;
use App\Contracts\FixtureServiceInterface;
use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class FixtureService implements FixtureServiceInterface
{
    public function __construct(
        private readonly FixtureGeneratorInterface $generator,
    ) {}

    public function generateFixtures(): void
    {
        if ($this->fixturesExist()) {
            throw new \RuntimeException('Fixtures have already been generated.');
        }

        $teamIds = Team::orderBy('id')->pluck('id')->toArray();

        if (count($teamIds) < 2) {
            throw new \RuntimeException('At least 2 teams are required to generate fixtures.');
        }

        $rounds = $this->generator->generate($teamIds);

        DB::transaction(function () use ($rounds): void {
            $this->persistFixtures($rounds);
        });
    }

    public function resetFixtures(): void
    {
        DB::transaction(function (): void {
            Fixture::truncate();
            Team::query()->update([
                'played'        => 0,
                'won'           => 0,
                'drawn'         => 0,
                'lost'          => 0,
                'goals_for'     => 0,
                'goals_against' => 0,
                'points'        => 0,
            ]);
        });
    }

    public function fixturesExist(): bool
    {
        return Fixture::exists();
    }

    private function persistFixtures(array $rounds): void
    {
        $rows = [];
        $now  = now();

        foreach ($rounds as $weekIndex => $matches) {
            $week = $weekIndex + 1;

            foreach ($matches as $match) {
                $rows[] = [
                    'week'         => $week,
                    'home_team_id' => $match['home_team_id'],
                    'away_team_id' => $match['away_team_id'],
                    'home_score'   => null,
                    'away_score'   => null,
                    'is_played'    => false,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }

        Fixture::insert($rows);
    }
}
