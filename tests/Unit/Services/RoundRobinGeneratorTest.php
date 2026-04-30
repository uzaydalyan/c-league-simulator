<?php

namespace Tests\Unit\Services;

use App\Services\RoundRobinGenerator;
use PHPUnit\Framework\TestCase;

class RoundRobinGeneratorTest extends TestCase
{
    private RoundRobinGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new RoundRobinGenerator();
    }

    // -------------------------------------------------------------------------
    // Round count
    // -------------------------------------------------------------------------

    public function test_generates_six_rounds_for_four_teams(): void
    {
        $rounds = $this->generator->generate([1, 2, 3, 4]);

        $this->assertCount(6, $rounds);
    }

    public function test_generates_two_rounds_for_two_teams(): void
    {
        $rounds = $this->generator->generate([1, 2]);

        $this->assertCount(2, $rounds);
    }

    public function test_generates_ten_rounds_for_six_teams(): void
    {
        $rounds = $this->generator->generate([1, 2, 3, 4, 5, 6]);

        $this->assertCount(10, $rounds);
    }

    // -------------------------------------------------------------------------
    // Match count per round
    // -------------------------------------------------------------------------

    public function test_each_round_has_two_matches_for_four_teams(): void
    {
        $rounds = $this->generator->generate([1, 2, 3, 4]);

        foreach ($rounds as $round) {
            $this->assertCount(2, $round);
        }
    }

    public function test_each_round_has_three_matches_for_six_teams(): void
    {
        $rounds = $this->generator->generate([1, 2, 3, 4, 5, 6]);

        foreach ($rounds as $round) {
            $this->assertCount(3, $round);
        }
    }

    public function test_each_round_has_one_match_for_two_teams(): void
    {
        $rounds = $this->generator->generate([1, 2]);

        foreach ($rounds as $round) {
            $this->assertCount(1, $round);
        }
    }

    // -------------------------------------------------------------------------
    // No team plays twice in the same round
    // -------------------------------------------------------------------------

    public function test_no_team_plays_twice_in_same_round_for_four_teams(): void
    {
        $rounds = $this->generator->generate([1, 2, 3, 4]);

        foreach ($rounds as $round) {
            $teamIds = array_merge(
                array_column($round, 'home_team_id'),
                array_column($round, 'away_team_id'),
            );
            $this->assertCount(count($teamIds), array_unique($teamIds));
        }
    }

    public function test_no_team_plays_twice_in_same_round_for_six_teams(): void
    {
        $rounds = $this->generator->generate([1, 2, 3, 4, 5, 6]);

        foreach ($rounds as $round) {
            $teamIds = array_merge(
                array_column($round, 'home_team_id'),
                array_column($round, 'away_team_id'),
            );
            $this->assertCount(count($teamIds), array_unique($teamIds));
        }
    }

    // -------------------------------------------------------------------------
    // Every pair plays home and away exactly once
    // -------------------------------------------------------------------------

    public function test_every_pair_plays_home_and_away_for_four_teams(): void
    {
        $teamIds = [1, 2, 3, 4];
        $rounds  = $this->generator->generate($teamIds);

        $fixtures = array_merge(...$rounds);

        foreach ($teamIds as $home) {
            foreach ($teamIds as $away) {
                if ($home === $away) {
                    continue;
                }
                $count = count(array_filter(
                    $fixtures,
                    fn($f) => $f['home_team_id'] === $home && $f['away_team_id'] === $away,
                ));
                $this->assertSame(1, $count, "Expected {$home} vs {$away} exactly once as home fixture.");
            }
        }
    }

    public function test_every_pair_plays_home_and_away_for_six_teams(): void
    {
        $teamIds = [1, 2, 3, 4, 5, 6];
        $rounds  = $this->generator->generate($teamIds);
        $fixtures = array_merge(...$rounds);

        foreach ($teamIds as $home) {
            foreach ($teamIds as $away) {
                if ($home === $away) {
                    continue;
                }
                $count = count(array_filter(
                    $fixtures,
                    fn($f) => $f['home_team_id'] === $home && $f['away_team_id'] === $away,
                ));
                $this->assertSame(1, $count, "Expected {$home} vs {$away} exactly once as home fixture.");
            }
        }
    }

    // -------------------------------------------------------------------------
    // Total match count
    // -------------------------------------------------------------------------

    public function test_total_fixture_count_for_four_teams(): void
    {
        // 4 teams × 3 opponents × 2 (home+away) / 2 per match = 12 fixtures
        $rounds   = $this->generator->generate([1, 2, 3, 4]);
        $fixtures = array_merge(...$rounds);

        $this->assertCount(12, $fixtures);
    }

    public function test_total_fixture_count_for_two_teams(): void
    {
        $rounds   = $this->generator->generate([1, 2]);
        $fixtures = array_merge(...$rounds);

        $this->assertCount(2, $fixtures);
    }

    // -------------------------------------------------------------------------
    // Each team plays the same number of matches
    // -------------------------------------------------------------------------

    public function test_each_team_plays_equal_number_of_matches(): void
    {
        $teamIds  = [1, 2, 3, 4];
        $rounds   = $this->generator->generate($teamIds);
        $fixtures = array_merge(...$rounds);

        foreach ($teamIds as $id) {
            $played = count(array_filter(
                $fixtures,
                fn($f) => $f['home_team_id'] === $id || $f['away_team_id'] === $id,
            ));
            // each team plays 2*(N-1) matches in a double round-robin
            $this->assertSame(2 * (count($teamIds) - 1), $played);
        }
    }

    // -------------------------------------------------------------------------
    // Second half is a mirror of the first half (home/away swapped)
    // -------------------------------------------------------------------------

    public function test_second_half_mirrors_first_half_with_swapped_sides(): void
    {
        $rounds    = $this->generator->generate([1, 2, 3, 4]);
        $half      = count($rounds) / 2;
        $firstHalf = array_slice($rounds, 0, $half);
        $secondHalf = array_slice($rounds, $half);

        foreach ($firstHalf as $roundIndex => $matches) {
            foreach ($matches as $matchIndex => $match) {
                $returnMatch = $secondHalf[$roundIndex][$matchIndex];
                $this->assertSame($match['home_team_id'], $returnMatch['away_team_id']);
                $this->assertSame($match['away_team_id'], $returnMatch['home_team_id']);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Two teams edge case
    // -------------------------------------------------------------------------

    public function test_two_teams_play_each_other_home_and_away(): void
    {
        $rounds   = $this->generator->generate([1, 2]);
        $fixtures = array_merge(...$rounds);

        $this->assertContains(['home_team_id' => 1, 'away_team_id' => 2], $fixtures);
        $this->assertContains(['home_team_id' => 2, 'away_team_id' => 1], $fixtures);
    }

    // -------------------------------------------------------------------------
    // Odd team count — byes must not appear in fixtures
    // -------------------------------------------------------------------------

    public function test_odd_team_count_produces_no_null_team_ids(): void
    {
        $rounds   = $this->generator->generate([1, 2, 3]); // odd
        $fixtures = array_merge(...$rounds);

        foreach ($fixtures as $fixture) {
            $this->assertNotNull($fixture['home_team_id']);
            $this->assertNotNull($fixture['away_team_id']);
        }
    }

    public function test_odd_team_count_every_real_pair_plays_home_and_away(): void
    {
        $teamIds  = [1, 2, 3];
        $rounds   = $this->generator->generate($teamIds);
        $fixtures = array_merge(...$rounds);

        foreach ($teamIds as $home) {
            foreach ($teamIds as $away) {
                if ($home === $away) {
                    continue;
                }
                $count = count(array_filter(
                    $fixtures,
                    fn($f) => $f['home_team_id'] === $home && $f['away_team_id'] === $away,
                ));
                $this->assertSame(1, $count);
            }
        }
    }
}
