<?php

namespace App\Contracts;

interface FixtureGeneratorInterface
{
    /**
     * Generate a full double round-robin schedule from a list of team IDs.
     *
     * Returns an array of rounds. Each round is an array of match pairs:
     * [['home_team_id' => int, 'away_team_id' => int], ...]
     *
     * @param  int[]  $teamIds
     * @return array<int, array<int, array{home_team_id: int, away_team_id: int}>>
     */
    public function generate(array $teamIds): array;
}
