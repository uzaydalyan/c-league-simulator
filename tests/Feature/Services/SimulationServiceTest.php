<?php

namespace Tests\Feature\Services;

use App\Contracts\MatchScoreCalculatorInterface;
use App\DataTransferObjects\MatchScore;
use App\Models\Fixture;
use App\Models\Team;
use App\Services\FixtureService;
use App\Services\RoundRobinGenerator;
use App\Services\SimulationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a SimulationService whose calculator always returns the given score.
     * This eliminates randomness so every assertion is deterministic.
     */
    private function makeService(int $homeScore, int $awayScore): SimulationService
    {
        $calculator = new class($homeScore, $awayScore) implements MatchScoreCalculatorInterface {
            public function __construct(
                private readonly int $homeScore,
                private readonly int $awayScore,
            ) {}

            public function calculate(Team $homeTeam, Team $awayTeam): MatchScore
            {
                return new MatchScore($this->homeScore, $this->awayScore);
            }
        };

        return new SimulationService($calculator);
    }

    private function seedTeamsAndFixtures(): void
    {
        foreach ([
            ['name' => 'Manchester City', 'power' => 90],
            ['name' => 'Liverpool',        'power' => 85],
            ['name' => 'Chelsea',          'power' => 78],
            ['name' => 'Arsenal',          'power' => 72],
        ] as $team) {
            Team::create($team);
        }

        (new FixtureService(new RoundRobinGenerator()))->generateFixtures();
    }

    private function firstUnplayedFixture(): Fixture
    {
        return Fixture::with(['homeTeam', 'awayTeam'])
            ->where('is_played', false)
            ->orderBy('week')
            ->firstOrFail();
    }

    // -------------------------------------------------------------------------
    // playMatch — fixture state
    // -------------------------------------------------------------------------

    public function test_play_match_marks_fixture_as_played(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $this->makeService(2, 1)->playMatch($fixture);

        $this->assertDatabaseHas('fixtures', [
            'id'        => $fixture->id,
            'is_played' => true,
        ]);
    }

    public function test_play_match_saves_correct_scores_on_fixture(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $this->makeService(3, 2)->playMatch($fixture);

        $this->assertDatabaseHas('fixtures', [
            'id'         => $fixture->id,
            'home_score' => 3,
            'away_score' => 2,
        ]);
    }

    public function test_play_match_returns_updated_fixture(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $result = $this->makeService(1, 0)->playMatch($fixture);

        $this->assertTrue($result->is_played);
        $this->assertSame(1, $result->home_score);
        $this->assertSame(0, $result->away_score);
    }

    public function test_play_match_throws_if_fixture_already_played(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();
        $service = $this->makeService(1, 0);

        $service->playMatch($fixture);
        $fixture->refresh();

        $this->expectException(\RuntimeException::class);
        $service->playMatch($fixture);
    }

    // -------------------------------------------------------------------------
    // playMatch — team stats after a home win (2-1)
    // -------------------------------------------------------------------------

    public function test_home_win_increments_played_for_both_teams(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();
        $homeId  = $fixture->home_team_id;
        $awayId  = $fixture->away_team_id;

        $this->makeService(2, 1)->playMatch($fixture);

        $this->assertSame(1, Team::find($homeId)->played);
        $this->assertSame(1, Team::find($awayId)->played);
    }

    public function test_home_win_awards_three_points_to_home_team(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $this->makeService(2, 1)->playMatch($fixture);

        $this->assertSame(3, Team::find($fixture->home_team_id)->points);
    }

    public function test_home_win_awards_zero_points_to_away_team(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $this->makeService(2, 1)->playMatch($fixture);

        $this->assertSame(0, Team::find($fixture->away_team_id)->points);
    }

    public function test_home_win_increments_won_for_home_team(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $this->makeService(2, 1)->playMatch($fixture);

        $this->assertSame(1, Team::find($fixture->home_team_id)->won);
    }

    public function test_home_win_increments_lost_for_away_team(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $this->makeService(2, 1)->playMatch($fixture);

        $this->assertSame(1, Team::find($fixture->away_team_id)->lost);
    }

    public function test_home_win_updates_goals_for_and_against_correctly(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();
        $homeId  = $fixture->home_team_id;
        $awayId  = $fixture->away_team_id;

        $this->makeService(3, 1)->playMatch($fixture);

        $home = Team::find($homeId);
        $away = Team::find($awayId);

        $this->assertSame(3, $home->goals_for);
        $this->assertSame(1, $home->goals_against);
        $this->assertSame(1, $away->goals_for);
        $this->assertSame(3, $away->goals_against);
    }

    // -------------------------------------------------------------------------
    // playMatch — team stats after a draw (1-1)
    // -------------------------------------------------------------------------

    public function test_draw_awards_one_point_to_each_team(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();
        $homeId  = $fixture->home_team_id;
        $awayId  = $fixture->away_team_id;

        $this->makeService(1, 1)->playMatch($fixture);

        $this->assertSame(1, Team::find($homeId)->points);
        $this->assertSame(1, Team::find($awayId)->points);
    }

    public function test_draw_increments_drawn_for_both_teams(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();
        $homeId  = $fixture->home_team_id;
        $awayId  = $fixture->away_team_id;

        $this->makeService(1, 1)->playMatch($fixture);

        $this->assertSame(1, Team::find($homeId)->drawn);
        $this->assertSame(1, Team::find($awayId)->drawn);
    }

    public function test_draw_does_not_increment_won_or_lost(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();
        $homeId  = $fixture->home_team_id;
        $awayId  = $fixture->away_team_id;

        $this->makeService(0, 0)->playMatch($fixture);

        $this->assertSame(0, Team::find($homeId)->won);
        $this->assertSame(0, Team::find($homeId)->lost);
        $this->assertSame(0, Team::find($awayId)->won);
        $this->assertSame(0, Team::find($awayId)->lost);
    }

    // -------------------------------------------------------------------------
    // playMatch — team stats after an away win (0-2)
    // -------------------------------------------------------------------------

    public function test_away_win_awards_three_points_to_away_team(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $this->makeService(0, 2)->playMatch($fixture);

        $this->assertSame(3, Team::find($fixture->away_team_id)->points);
    }

    public function test_away_win_awards_zero_points_to_home_team(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $this->makeService(0, 2)->playMatch($fixture);

        $this->assertSame(0, Team::find($fixture->home_team_id)->points);
    }

    public function test_away_win_increments_won_for_away_team(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $this->makeService(0, 2)->playMatch($fixture);

        $this->assertSame(1, Team::find($fixture->away_team_id)->won);
    }

    public function test_away_win_increments_lost_for_home_team(): void
    {
        $this->seedTeamsAndFixtures();
        $fixture = $this->firstUnplayedFixture();

        $this->makeService(0, 2)->playMatch($fixture);

        $this->assertSame(1, Team::find($fixture->home_team_id)->lost);
    }

    // -------------------------------------------------------------------------
    // playWeek
    // -------------------------------------------------------------------------

    public function test_play_week_marks_all_fixtures_in_week_as_played(): void
    {
        $this->seedTeamsAndFixtures();

        $this->makeService(1, 0)->playWeek(1);

        $unplayed = Fixture::where('week', 1)->where('is_played', false)->count();
        $this->assertSame(0, $unplayed);
    }

    public function test_play_week_does_not_touch_other_weeks(): void
    {
        $this->seedTeamsAndFixtures();

        $this->makeService(1, 0)->playWeek(1);

        $unplayedInOtherWeeks = Fixture::where('week', '!=', 1)
            ->where('is_played', false)
            ->count();

        // 6 weeks × 2 matches, minus the 2 played in week 1 = 10 remaining
        $this->assertSame(10, $unplayedInOtherWeeks);
    }

    public function test_play_week_returns_fixtures_for_that_week(): void
    {
        $this->seedTeamsAndFixtures();

        $fixtures = $this->makeService(2, 1)->playWeek(1);

        $this->assertCount(2, $fixtures);
        $fixtures->each(fn(Fixture $f) => $this->assertSame(1, $f->week));
    }

    public function test_play_week_throws_when_week_has_no_unplayed_fixtures(): void
    {
        $this->seedTeamsAndFixtures();
        $service = $this->makeService(1, 0);

        $service->playWeek(1); // play it once

        $this->expectException(\RuntimeException::class);
        $service->playWeek(1); // already played — should throw
    }

    public function test_play_week_throws_for_nonexistent_week(): void
    {
        $this->seedTeamsAndFixtures();

        $this->expectException(\RuntimeException::class);
        $this->makeService(1, 0)->playWeek(99);
    }

    // -------------------------------------------------------------------------
    // playAll
    // -------------------------------------------------------------------------

    public function test_play_all_marks_every_fixture_as_played(): void
    {
        $this->seedTeamsAndFixtures();

        $this->makeService(1, 0)->playAll();

        $unplayed = Fixture::where('is_played', false)->count();
        $this->assertSame(0, $unplayed);
    }

    public function test_play_all_returns_all_fixtures(): void
    {
        $this->seedTeamsAndFixtures();

        $fixtures = $this->makeService(1, 0)->playAll();

        // 4 teams × 3 opponents × 2 (home+away) = 12
        $this->assertCount(12, $fixtures);
    }

    public function test_play_all_returns_fixtures_ordered_by_week(): void
    {
        $this->seedTeamsAndFixtures();

        $fixtures = $this->makeService(1, 0)->playAll();

        $weeks = $fixtures->pluck('week')->toArray();
        $this->assertSame($weeks, collect($weeks)->sort()->values()->toArray());
    }

    public function test_play_all_accumulates_team_stats_across_all_matches(): void
    {
        $this->seedTeamsAndFixtures();

        // Every match is a home win (1-0), so each team plays 6 matches.
        $this->makeService(1, 0)->playAll();

        Team::all()->each(fn(Team $team) => $this->assertSame(6, $team->played));
    }

    public function test_play_all_throws_when_all_fixtures_already_played(): void
    {
        $this->seedTeamsAndFixtures();
        $service = $this->makeService(1, 0);

        $service->playAll();

        $this->expectException(\RuntimeException::class);
        $service->playAll();
    }
}
