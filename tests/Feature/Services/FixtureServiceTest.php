<?php

namespace Tests\Feature\Services;

use App\Models\Fixture;
use App\Models\Team;
use App\Services\FixtureService;
use App\Services\RoundRobinGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixtureServiceTest extends TestCase
{
    use RefreshDatabase;

    private FixtureService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FixtureService(new RoundRobinGenerator());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedTeams(int $count = 4): void
    {
        $teams = [
            ['name' => 'Manchester City', 'power' => 90],
            ['name' => 'Liverpool',        'power' => 85],
            ['name' => 'Chelsea',          'power' => 78],
            ['name' => 'Arsenal',          'power' => 72],
            ['name' => 'Tottenham',        'power' => 70],
            ['name' => 'Man United',       'power' => 68],
        ];

        foreach (array_slice($teams, 0, $count) as $team) {
            Team::create($team);
        }
    }

    // -------------------------------------------------------------------------
    // fixturesExist()
    // -------------------------------------------------------------------------

    public function test_fixtures_exist_returns_false_when_no_fixtures(): void
    {
        $this->assertFalse($this->service->fixturesExist());
    }

    public function test_fixtures_exist_returns_true_after_generation(): void
    {
        $this->seedTeams();
        $this->service->generateFixtures();

        $this->assertTrue($this->service->fixturesExist());
    }

    // -------------------------------------------------------------------------
    // generateFixtures() — fixture count
    // -------------------------------------------------------------------------

    public function test_generates_correct_number_of_fixtures_for_four_teams(): void
    {
        $this->seedTeams(4);
        $this->service->generateFixtures();

        // 4 teams: 4*(4-1) = 12 fixtures (each pair home+away)
        $this->assertDatabaseCount('fixtures', 12);
    }

    public function test_generates_correct_number_of_fixtures_for_two_teams(): void
    {
        $this->seedTeams(2);
        $this->service->generateFixtures();

        $this->assertDatabaseCount('fixtures', 2);
    }

    public function test_generates_correct_number_of_fixtures_for_six_teams(): void
    {
        $this->seedTeams(6);
        $this->service->generateFixtures();

        // 6 teams: 6*(6-1) = 30 fixtures
        $this->assertDatabaseCount('fixtures', 30);
    }

    // -------------------------------------------------------------------------
    // generateFixtures() — initial state of saved fixtures
    // -------------------------------------------------------------------------

    public function test_all_fixtures_are_saved_as_unplayed(): void
    {
        $this->seedTeams();
        $this->service->generateFixtures();

        $played = Fixture::where('is_played', true)->count();
        $this->assertSame(0, $played);
    }

    public function test_all_fixtures_have_null_scores(): void
    {
        $this->seedTeams();
        $this->service->generateFixtures();

        $withScores = Fixture::whereNotNull('home_score')
            ->orWhereNotNull('away_score')
            ->count();

        $this->assertSame(0, $withScores);
    }

    // -------------------------------------------------------------------------
    // generateFixtures() — week numbers
    // -------------------------------------------------------------------------

    public function test_week_numbers_start_at_one(): void
    {
        $this->seedTeams();
        $this->service->generateFixtures();

        $this->assertDatabaseHas('fixtures', ['week' => 1]);
    }

    public function test_week_numbers_end_at_total_weeks_for_four_teams(): void
    {
        $this->seedTeams(4);
        $this->service->generateFixtures();

        // double round-robin for 4 teams = 6 weeks
        $this->assertDatabaseHas('fixtures', ['week' => 6]);
        $this->assertDatabaseMissing('fixtures', ['week' => 7]);
    }

    public function test_each_week_has_correct_number_of_matches_for_four_teams(): void
    {
        $this->seedTeams(4);
        $this->service->generateFixtures();

        // N/2 = 2 matches per week
        for ($week = 1; $week <= 6; $week++) {
            $count = Fixture::where('week', $week)->count();
            $this->assertSame(2, $count, "Week {$week} should have 2 matches.");
        }
    }

    public function test_no_team_plays_more_than_once_per_week(): void
    {
        $this->seedTeams(4);
        $this->service->generateFixtures();

        $teamIds = Team::pluck('id');
        $weeks   = Fixture::distinct()->pluck('week');

        foreach ($weeks as $week) {
            $fixtures = Fixture::where('week', $week)->get();

            foreach ($teamIds as $teamId) {
                $appearances = $fixtures->filter(
                    fn($f) => $f->home_team_id === $teamId || $f->away_team_id === $teamId,
                )->count();

                $this->assertLessThanOrEqual(1, $appearances, "Team {$teamId} appears more than once in week {$week}.");
            }
        }
    }

    // -------------------------------------------------------------------------
    // generateFixtures() — guard conditions
    // -------------------------------------------------------------------------

    public function test_throws_runtime_exception_if_fixtures_already_exist(): void
    {
        $this->seedTeams();
        $this->service->generateFixtures();

        $this->expectException(\RuntimeException::class);
        $this->service->generateFixtures();
    }

    public function test_throws_runtime_exception_if_fewer_than_two_teams(): void
    {
        $this->seedTeams(1);

        $this->expectException(\RuntimeException::class);
        $this->service->generateFixtures();
    }

    public function test_throws_runtime_exception_if_no_teams(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->service->generateFixtures();
    }

    // -------------------------------------------------------------------------
    // resetFixtures()
    // -------------------------------------------------------------------------

    public function test_reset_removes_all_fixtures(): void
    {
        $this->seedTeams();
        $this->service->generateFixtures();
        $this->service->resetFixtures();

        $this->assertDatabaseCount('fixtures', 0);
    }

    public function test_reset_zeroes_all_team_stats(): void
    {
        $this->seedTeams();
        $this->service->generateFixtures();

        // Manually dirty some team stats
        Team::query()->update([
            'played'        => 5,
            'won'           => 3,
            'drawn'         => 1,
            'lost'          => 1,
            'goals_for'     => 10,
            'goals_against' => 5,
            'points'        => 10,
        ]);

        $this->service->resetFixtures();

        $teams = Team::all();
        foreach ($teams as $team) {
            $this->assertSame(0, $team->played);
            $this->assertSame(0, $team->won);
            $this->assertSame(0, $team->drawn);
            $this->assertSame(0, $team->lost);
            $this->assertSame(0, $team->goals_for);
            $this->assertSame(0, $team->goals_against);
            $this->assertSame(0, $team->points);
        }
    }

    public function test_reset_allows_regeneration_of_fixtures(): void
    {
        $this->seedTeams();
        $this->service->generateFixtures();
        $this->service->resetFixtures();

        // Should not throw
        $this->service->generateFixtures();

        $this->assertDatabaseCount('fixtures', 12);
    }

    public function test_fixtures_exist_returns_false_after_reset(): void
    {
        $this->seedTeams();
        $this->service->generateFixtures();
        $this->service->resetFixtures();

        $this->assertFalse($this->service->fixturesExist());
    }
}
