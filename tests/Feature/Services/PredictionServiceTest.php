<?php

namespace Tests\Feature\Services;

use App\DataTransferObjects\ChampionshipPrediction;
use App\Models\Fixture;
use App\Models\Team;
use App\Services\FixtureService;
use App\Services\PredictionService;
use App\Services\RoundRobinGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PredictionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PredictionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PredictionService();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function team(
        string $name,
        int    $power,
        int    $points       = 0,
        int    $goalsFor     = 0,
        int    $goalsAgainst = 0,
    ): Team {
        return Team::create([
            'name'          => $name,
            'power'         => $power,
            'points'        => $points,
            'played'        => 0,
            'won'           => 0,
            'drawn'         => 0,
            'lost'          => 0,
            'goals_for'     => $goalsFor,
            'goals_against' => $goalsAgainst,
        ]);
    }

    private function unplayed(Team $home, Team $away, int $week = 1): void
    {
        Fixture::create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'week'         => $week,
            'is_played'    => false,
        ]);
    }

    private function played(Team $home, Team $away, int $week = 1): void
    {
        Fixture::create([
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'week'         => $week,
            'is_played'    => true,
            'home_score'   => 1,
            'away_score'   => 0,
        ]);
    }

    /** Pull a single team's prediction from the result collection. */
    private function predictionFor(Collection $predictions, Team $team): ChampionshipPrediction
    {
        return $predictions->first(fn(ChampionshipPrediction $p) => $p->team->id === $team->id);
    }

    // -------------------------------------------------------------------------
    // Return type & structure
    // -------------------------------------------------------------------------

    public function test_returns_collection(): void
    {
        $this->team('A', 80);

        $result = $this->service->predict();

        $this->assertInstanceOf(Collection::class, $result);
    }

    public function test_returns_one_championship_prediction_dto_per_team(): void
    {
        $this->team('A', 90);
        $this->team('B', 85);
        $this->team('C', 78);
        $this->team('D', 72);

        $result = $this->service->predict();

        $this->assertCount(4, $result);
        $result->each(fn($p) => $this->assertInstanceOf(ChampionshipPrediction::class, $p));
    }

    public function test_dto_contains_the_correct_team_model(): void
    {
        $team   = $this->team('Manchester City', 90, 9);
        $result = $this->service->predict();

        $prediction = $this->predictionFor($result, $team);

        $this->assertSame($team->id,   $prediction->team->id);
        $this->assertSame($team->name, $prediction->team->name);
    }

    // -------------------------------------------------------------------------
    // Percentages always sum to 100
    // -------------------------------------------------------------------------

    public function test_percentages_sum_to_100_on_guaranteed_champion_path(): void
    {
        // Leader 15 pts; others' max < 15 → guaranteed champion path.
        $leader = $this->team('Leader', 90, 15);
        $a      = $this->team('A', 85, 6);
        $b      = $this->team('B', 78, 3);
        $c      = $this->team('C', 72, 0);

        $this->unplayed($a, $b, 1); // a: 1 remaining, b: 1 remaining
        $this->unplayed($a, $c, 2); // a: 2 remaining, c: 1 remaining
        // max for a = 6+6=12, b = 3+3=6, c = 0+3=3 — all < 15 → leader guaranteed

        $sum = $this->service->predict()->sum(fn($p) => $p->percentage);

        $this->assertEqualsWithDelta(100.0, $sum, 0.01);
    }

    public function test_percentages_sum_to_100_on_scoring_path(): void
    {
        // No guaranteed champion; percentages distributed via scoring.
        $a = $this->team('A', 90, 9);
        $b = $this->team('B', 85, 6);
        $c = $this->team('C', 78, 3);
        $d = $this->team('D', 72, 0);

        $this->unplayed($a, $b, 1);
        $this->unplayed($c, $d, 1);

        $sum = $this->service->predict()->sum(fn($p) => $p->percentage);

        $this->assertEqualsWithDelta(100.0, $sum, 0.01);
    }

    public function test_percentages_sum_to_100_when_some_teams_are_eliminated(): void
    {
        // Leader 12 pts; C and D eliminated; only Leader and B are contenders.
        $leader = $this->team('Leader', 90, 12);
        $b      = $this->team('B',      85, 9);
        $c      = $this->team('C',      78, 5);  // max 5+3=8 < 12 → eliminated
        $d      = $this->team('D',      72, 3);  // max 3 < 12 → eliminated

        $this->unplayed($b, $c, 1); // b: 1 remaining (max 12), c: 1 remaining (max 8 — still eliminated)

        $sum = $this->service->predict()->sum(fn($p) => $p->percentage);

        $this->assertEqualsWithDelta(100.0, $sum, 0.01);
    }

    public function test_percentages_sum_to_100_at_end_of_season(): void
    {
        $a = $this->team('A', 90, 15);
        $b = $this->team('B', 85, 12);
        $c = $this->team('C', 78,  9);
        $d = $this->team('D', 72,  6);
        // No unplayed fixtures — all matches done.

        $sum = $this->service->predict()->sum(fn($p) => $p->percentage);

        $this->assertEqualsWithDelta(100.0, $sum, 0.01);
    }

    // -------------------------------------------------------------------------
    // All percentages are non-negative
    // -------------------------------------------------------------------------

    public function test_all_percentages_are_non_negative(): void
    {
        $a = $this->team('A', 90, 9);
        $b = $this->team('B', 85, 6);
        $c = $this->team('C', 78, 3);
        $d = $this->team('D', 72, 0);

        $this->unplayed($a, $b, 1);
        $this->unplayed($c, $d, 1);

        $this->service->predict()
            ->each(fn($p) => $this->assertGreaterThanOrEqual(0.0, $p->percentage));
    }

    // -------------------------------------------------------------------------
    // Guaranteed champion
    // -------------------------------------------------------------------------

    public function test_guaranteed_champion_receives_exactly_100_percent(): void
    {
        $leader = $this->team('Leader', 90, 15);
        $a      = $this->team('A', 85, 6);
        $b      = $this->team('B', 78, 3);
        $c      = $this->team('C', 72, 0);

        // Others max: a→12, b→6, c→3 — all < 15
        $this->unplayed($a, $b, 1);
        $this->unplayed($a, $c, 2);

        $prediction = $this->predictionFor($this->service->predict(), $leader);

        $this->assertSame(100.0, $prediction->percentage);
    }

    public function test_all_other_teams_receive_0_percent_when_champion_is_guaranteed(): void
    {
        $leader = $this->team('Leader', 90, 15);
        $a      = $this->team('A', 85, 6);
        $b      = $this->team('B', 78, 3);
        $c      = $this->team('C', 72, 0);

        $this->unplayed($a, $b, 1);
        $this->unplayed($a, $c, 2);

        $predictions = $this->service->predict();

        foreach ([$a, $b, $c] as $team) {
            $this->assertSame(0.0, $this->predictionFor($predictions, $team)->percentage);
        }
    }

    public function test_leader_is_not_guaranteed_when_rival_can_match_on_points(): void
    {
        // Leader 12 pts, Rival 9 pts + 1 remaining = max 12 — cannot be guaranteed (strict >).
        $leader = $this->team('Leader', 90, 12);
        $rival  = $this->team('Rival',  85,  9);

        $this->unplayed($rival, $leader, 1); // rival gets 1 remaining

        $prediction = $this->predictionFor($this->service->predict(), $leader);

        $this->assertLessThan(100.0, $prediction->percentage);
    }

    // -------------------------------------------------------------------------
    // Mathematically eliminated teams
    // -------------------------------------------------------------------------

    public function test_eliminated_team_receives_exactly_0_percent(): void
    {
        // Leader 12 pts; C max = 5+3=8 < 12 → eliminated.
        $leader = $this->team('Leader', 90, 12);
        $b      = $this->team('B',      85,  9);
        $c      = $this->team('C',      78,  5);

        $this->unplayed($b, $c, 1); // b: 1 remaining, c: 1 remaining

        $prediction = $this->predictionFor($this->service->predict(), $c);

        $this->assertSame(0.0, $prediction->percentage);
    }

    public function test_all_eliminated_teams_receive_0_percent(): void
    {
        $leader = $this->team('Leader', 90, 12);
        $b      = $this->team('B',      85,  9);
        $c      = $this->team('C',      78,  5);
        $d      = $this->team('D',      72,  2);

        $this->unplayed($b, $c, 1);
        // c max = 8 < 12, d max = 2 < 12 → both eliminated

        $predictions = $this->service->predict();

        $this->assertSame(0.0, $this->predictionFor($predictions, $c)->percentage);
        $this->assertSame(0.0, $this->predictionFor($predictions, $d)->percentage);
    }

    public function test_team_that_can_tie_leader_is_not_eliminated(): void
    {
        // Leader 12 pts; Rival max = 12 (not < 12) → NOT eliminated → gets > 0%.
        $leader = $this->team('Leader', 90, 12);
        $rival  = $this->team('Rival',  85,  9);

        $this->unplayed($rival, $leader, 1);

        $prediction = $this->predictionFor($this->service->predict(), $rival);

        $this->assertGreaterThan(0.0, $prediction->percentage);
    }

    public function test_eliminated_teams_do_not_reduce_contenders_percentage_sum(): void
    {
        $leader = $this->team('Leader', 90, 12);
        $b      = $this->team('B',      85,  9);
        $c      = $this->team('C',      78,  2); // max 2 < 12 → eliminated
        $d      = $this->team('D',      72,  1); // max 1 < 12 → eliminated

        $this->unplayed($b, $leader, 1);

        $predictions = $this->service->predict();
        $contenderSum = $predictions
            ->filter(fn($p) => $p->percentage > 0.0)
            ->sum(fn($p) => $p->percentage);

        $this->assertEqualsWithDelta(100.0, $contenderSum, 0.01);
    }

    // -------------------------------------------------------------------------
    // End of season
    // -------------------------------------------------------------------------

    public function test_end_of_season_clear_winner_receives_100_percent(): void
    {
        $a = $this->team('A', 90, 15);
        $b = $this->team('B', 85, 12);
        $c = $this->team('C', 78,  9);
        $d = $this->team('D', 72,  6);
        // No remaining fixtures.

        $prediction = $this->predictionFor($this->service->predict(), $a);

        $this->assertSame(100.0, $prediction->percentage);
    }

    public function test_end_of_season_others_receive_0_when_winner_is_clear(): void
    {
        $a = $this->team('A', 90, 15);
        $b = $this->team('B', 85, 12);
        $c = $this->team('C', 78,  9);
        $d = $this->team('D', 72,  6);

        $predictions = $this->service->predict();

        foreach ([$b, $c, $d] as $team) {
            $this->assertSame(0.0, $this->predictionFor($predictions, $team)->percentage);
        }
    }

    // -------------------------------------------------------------------------
    // Scoring: relative factors
    // -------------------------------------------------------------------------

    public function test_team_with_more_points_gets_higher_percentage(): void
    {
        // Same power, same remaining, same GD — only points differ.
        $a = $this->team('A', 80, 9);
        $b = $this->team('B', 80, 6);

        $this->unplayed($a, $b, 1); // both get 1 remaining

        $predictions = $this->service->predict();

        $this->assertGreaterThan(
            $this->predictionFor($predictions, $b)->percentage,
            $this->predictionFor($predictions, $a)->percentage,
        );
    }

    public function test_team_with_higher_power_gets_higher_percentage(): void
    {
        // Same points, same remaining, same GD — only power differs.
        $a = $this->team('A', 90, 6);
        $b = $this->team('B', 72, 6);

        $this->unplayed($a, $b, 1);

        $predictions = $this->service->predict();

        $this->assertGreaterThan(
            $this->predictionFor($predictions, $b)->percentage,
            $this->predictionFor($predictions, $a)->percentage,
        );
    }

    public function test_team_with_more_remaining_matches_gets_higher_percentage(): void
    {
        // Same points, same power, same GD — only remaining matches differ.
        $a = $this->team('A', 80, 6);
        $b = $this->team('B', 80, 6);
        $c = $this->team('C', 80, 0); // eliminated (max 3 < 6), used only as A's extra opponent

        $this->unplayed($a, $b, 1); // a: 1, b: 1
        $this->unplayed($a, $c, 2); // a: 2, c: 1 — c eliminated (max 3 < 6)

        $predictions = $this->service->predict();

        // a has 2 remaining, b has 1 → a should score higher
        $this->assertGreaterThan(
            $this->predictionFor($predictions, $b)->percentage,
            $this->predictionFor($predictions, $a)->percentage,
        );
    }

    public function test_goal_difference_breaks_tie_between_otherwise_equal_teams(): void
    {
        // Same power, same points, same remaining — only GD differs.
        $a = $this->team('A', 80, 9, goalsFor: 10, goalsAgainst: 5); // GD = +5
        $b = $this->team('B', 80, 9, goalsFor:  8, goalsAgainst: 5); // GD = +3

        $this->unplayed($a, $b, 1);

        $predictions = $this->service->predict();

        $this->assertGreaterThan(
            $this->predictionFor($predictions, $b)->percentage,
            $this->predictionFor($predictions, $a)->percentage,
        );
    }

    public function test_identical_teams_in_every_dimension_split_exactly_50_50(): void
    {
        // Power, points, remaining, GD all identical — expect 50/50.
        $a = $this->team('A', 80, 9);
        $b = $this->team('B', 80, 9);

        $this->unplayed($a, $b, 1);

        $predictions = $this->service->predict();

        $this->assertEqualsWithDelta(50.0, $this->predictionFor($predictions, $a)->percentage, 0.01);
        $this->assertEqualsWithDelta(50.0, $this->predictionFor($predictions, $b)->percentage, 0.01);
    }

    // -------------------------------------------------------------------------
    // Tie at the top (end of season, no remaining)
    // -------------------------------------------------------------------------

    public function test_goal_difference_separates_tied_teams_at_end_of_season(): void
    {
        // Both 15 pts, 0 remaining — neither guaranteed; GD decides.
        $a = $this->team('A', 80, 15, goalsFor: 20, goalsAgainst: 15); // GD = +5
        $b = $this->team('B', 80, 15, goalsFor: 18, goalsAgainst: 15); // GD = +3

        $predictions = $this->service->predict();

        $this->assertGreaterThan(
            $this->predictionFor($predictions, $b)->percentage,
            $this->predictionFor($predictions, $a)->percentage,
        );
    }

    // -------------------------------------------------------------------------
    // Edge cases
    // -------------------------------------------------------------------------

    public function test_no_fixtures_exist_gives_each_team_equal_percentage(): void
    {
        // No fixtures at all — 0 pts, 0 remaining for everyone.
        // Scores all zero → edge case: equal split.
        $this->team('A', 90);
        $this->team('B', 85);
        $this->team('C', 78);
        $this->team('D', 72);

        $result = $this->service->predict();

        $result->each(
            fn($p) => $this->assertEqualsWithDelta(25.0, $p->percentage, 0.01),
        );
    }

    public function test_start_of_season_stronger_team_has_higher_percentage(): void
    {
        // 0 pts for all; generate a full fixture list so remaining > 0.
        // Score = remaining × 3 × powerRatio → stronger team scores higher.
        $a = $this->team('Manchester City', 90);
        $b = $this->team('Liverpool',        85);
        $c = $this->team('Chelsea',          78);
        $d = $this->team('Arsenal',          72);

        (new FixtureService(new RoundRobinGenerator()))->generateFixtures();

        $predictions = $this->service->predict();

        $pA = $this->predictionFor($predictions, $a)->percentage;
        $pB = $this->predictionFor($predictions, $b)->percentage;
        $pC = $this->predictionFor($predictions, $c)->percentage;
        $pD = $this->predictionFor($predictions, $d)->percentage;

        $this->assertGreaterThan($pB, $pA);
        $this->assertGreaterThan($pC, $pB);
        $this->assertGreaterThan($pD, $pC);
    }

    public function test_single_remaining_contender_gets_100_percent(): void
    {
        // Leader already guaranteed — effectively a single contender.
        $leader = $this->team('Leader', 90, 18);
        $a      = $this->team('A', 85, 3);
        $b      = $this->team('B', 78, 2);
        $c      = $this->team('C', 72, 1);
        // No unplayed fixtures — leader max 18 > a max 3, b max 2, c max 1.

        $prediction = $this->predictionFor($this->service->predict(), $leader);

        $this->assertSame(100.0, $prediction->percentage);
    }

    public function test_played_fixtures_do_not_count_as_remaining(): void
    {
        // If a fixture is marked played, it must NOT inflate the remaining count.
        $a = $this->team('A', 90, 12);
        $b = $this->team('B', 85, 9);

        $this->played($a, $b, 1);   // played → should not count
        $this->unplayed($a, $b, 2); // unplayed → counts as 1 remaining each

        // b max = 9+3=12, a max = 12+3=15.
        // a: 12 > b_max 12? No → a not guaranteed.
        // b: 9+3=12 >= leader(12) → b not eliminated.

        $predictions = $this->service->predict();

        // Both should be in contention — neither 0% nor 100%.
        $this->assertGreaterThan(0.0,   $this->predictionFor($predictions, $a)->percentage);
        $this->assertGreaterThan(0.0,   $this->predictionFor($predictions, $b)->percentage);
        $this->assertLessThan(100.0,    $this->predictionFor($predictions, $a)->percentage);
        $this->assertLessThan(100.0,    $this->predictionFor($predictions, $b)->percentage);
    }
}
