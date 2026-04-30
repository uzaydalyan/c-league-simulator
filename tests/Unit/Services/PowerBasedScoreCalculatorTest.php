<?php

namespace Tests\Unit\Services;

use App\Models\Team;
use App\Services\PowerBasedScoreCalculator;
use Tests\TestCase;

/**
 * Because PowerBasedScoreCalculator uses randomness, every statistical
 * assertion runs N iterations and checks an aggregate property (average,
 * win rate, bounds). Thresholds are deliberately conservative so the tests
 * are essentially impossible to fail by chance while still being meaningful.
 *
 * Sample sizes are kept small (200–1 000) so the suite stays fast.
 */
class PowerBasedScoreCalculatorTest extends TestCase
{
    private PowerBasedScoreCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new PowerBasedScoreCalculator();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeTeam(int $power): Team
    {
        return new Team(['power' => $power]);
    }

    /**
     * Run N simulations and return ['home' => [...scores], 'away' => [...scores]].
     */
    private function sample(Team $home, Team $away, int $iterations): array
    {
        $homeScores = [];
        $awayScores = [];

        for ($i = 0; $i < $iterations; $i++) {
            $score        = $this->calculator->calculate($home, $away);
            $homeScores[] = $score->homeScore;
            $awayScores[] = $score->awayScore;
        }

        return ['home' => $homeScores, 'away' => $awayScores];
    }

    // -------------------------------------------------------------------------
    // Return type & non-negative scores
    // -------------------------------------------------------------------------

    public function test_returns_match_score_instance(): void
    {
        $score = $this->calculator->calculate($this->makeTeam(80), $this->makeTeam(80));

        $this->assertInstanceOf(\App\DataTransferObjects\MatchScore::class, $score);
    }

    public function test_scores_are_always_non_negative(): void
    {
        $home   = $this->makeTeam(80);
        $away   = $this->makeTeam(80);
        $sample = $this->sample($home, $away, 200);

        foreach ($sample['home'] as $goals) {
            $this->assertGreaterThanOrEqual(0, $goals);
        }
        foreach ($sample['away'] as $goals) {
            $this->assertGreaterThanOrEqual(0, $goals);
        }
    }

    public function test_scores_are_integers(): void
    {
        $score = $this->calculator->calculate($this->makeTeam(80), $this->makeTeam(80));

        $this->assertIsInt($score->homeScore);
        $this->assertIsInt($score->awayScore);
    }

    // -------------------------------------------------------------------------
    // Realistic score bounds
    // -------------------------------------------------------------------------

    public function test_scores_stay_within_realistic_bounds_over_many_simulations(): void
    {
        // With any reasonable lambda (< 5), P(goals >= 15) is astronomically small.
        $home   = $this->makeTeam(100);
        $away   = $this->makeTeam(10);
        $sample = $this->sample($home, $away, 500);

        foreach (array_merge($sample['home'], $sample['away']) as $goals) {
            $this->assertLessThanOrEqual(15, $goals, 'A single team scored an unrealistically large number of goals.');
        }
    }

    // -------------------------------------------------------------------------
    // Home advantage
    // -------------------------------------------------------------------------

    public function test_home_team_scores_more_on_average_than_away_when_power_is_equal(): void
    {
        // With equal power, home effective power = power × 1.15 > away effective power.
        // Over 1 000 samples this should be essentially certain.
        $home   = $this->makeTeam(80);
        $away   = $this->makeTeam(80);
        $sample = $this->sample($home, $away, 1000);

        $homeAvg = array_sum($sample['home']) / 1000;
        $awayAvg = array_sum($sample['away']) / 1000;

        $this->assertGreaterThan($awayAvg, $homeAvg, 'Home team should outscore the away team on average when power is equal.');
    }

    // -------------------------------------------------------------------------
    // Power difference reflected in averages
    // -------------------------------------------------------------------------

    public function test_dominant_home_team_scores_more_on_average_than_weak_away_team(): void
    {
        // Power 100 vs 10 — the stronger side should average far more goals.
        $home   = $this->makeTeam(100);
        $away   = $this->makeTeam(10);
        $sample = $this->sample($home, $away, 500);

        $homeAvg = array_sum($sample['home']) / 500;
        $awayAvg = array_sum($sample['away']) / 500;

        $this->assertGreaterThan($awayAvg, $homeAvg);
    }

    public function test_dominant_away_team_scores_more_on_average_than_weak_home_team(): void
    {
        // Even with home advantage a power-100 away side should outscore a power-10 home side.
        $home   = $this->makeTeam(10);
        $away   = $this->makeTeam(100);
        $sample = $this->sample($home, $away, 500);

        $homeAvg = array_sum($sample['home']) / 500;
        $awayAvg = array_sum($sample['away']) / 500;

        $this->assertGreaterThan($homeAvg, $awayAvg);
    }

    // -------------------------------------------------------------------------
    // Win rates reflect power difference
    // -------------------------------------------------------------------------

    public function test_much_stronger_home_team_wins_majority_of_matches(): void
    {
        // power 100 at home vs power 10 away — home should win >> 60% of the time.
        $home   = $this->makeTeam(100);
        $away   = $this->makeTeam(10);
        $sample = $this->sample($home, $away, 500);

        $homeWins = count(array_filter(
            array_keys($sample['home']),
            fn(int $i) => $sample['home'][$i] > $sample['away'][$i],
        ));

        $winRate = $homeWins / 500;

        $this->assertGreaterThan(0.60, $winRate, 'A much stronger home team should win more than 60% of matches.');
    }

    public function test_much_stronger_away_team_wins_majority_of_matches(): void
    {
        // power 100 away vs power 10 home — away should still win majority despite home advantage.
        $home   = $this->makeTeam(10);
        $away   = $this->makeTeam(100);
        $sample = $this->sample($home, $away, 500);

        $awayWins = count(array_filter(
            array_keys($sample['away']),
            fn(int $i) => $sample['away'][$i] > $sample['home'][$i],
        ));

        $winRate = $awayWins / 500;

        $this->assertGreaterThan(0.50, $winRate, 'A much stronger away team should win the majority of matches.');
    }

    public function test_equal_power_teams_have_no_dominant_winner(): void
    {
        // With equal power, neither team should win more than 60% of matches.
        $home   = $this->makeTeam(80);
        $away   = $this->makeTeam(80);
        $sample = $this->sample($home, $away, 1000);

        $homeWins = count(array_filter(
            array_keys($sample['home']),
            fn(int $i) => $sample['home'][$i] > $sample['away'][$i],
        ));

        $awayWins = count(array_filter(
            array_keys($sample['away']),
            fn(int $i) => $sample['away'][$i] > $sample['home'][$i],
        ));

        $this->assertLessThan(0.60, $homeWins / 1000);
        $this->assertLessThan(0.60, $awayWins / 1000);
    }

    // -------------------------------------------------------------------------
    // Upsets are possible
    // -------------------------------------------------------------------------

    public function test_weaker_team_can_still_win_occasionally(): void
    {
        // With power 10 vs 100 the weak side should still win at least once in 500 matches.
        $home   = $this->makeTeam(10);
        $away   = $this->makeTeam(100);
        $sample = $this->sample($home, $away, 500);

        $homeWins = count(array_filter(
            array_keys($sample['home']),
            fn(int $i) => $sample['home'][$i] > $sample['away'][$i],
        ));

        $this->assertGreaterThan(0, $homeWins, 'The weaker team should be able to win at least occasionally.');
    }
}
