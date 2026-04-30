<?php

namespace App\Services;

use App\Contracts\FixtureGeneratorInterface;

class RoundRobinGenerator implements FixtureGeneratorInterface
{
    /*
     * Circle (polygon) method: fix one team, rotate the rest counter-clockwise
     * each round, pair position i with position (n-1-i). First half is single
     * round-robin; second half repeats with home/away swapped.
     */
    public function generate(array $teamIds): array
    {
        $teamIds = array_values($teamIds);

        if (count($teamIds) % 2 !== 0) {
            $teamIds[] = null; // bye slot for odd team count
        }

        $firstHalf  = $this->buildRounds($teamIds);
        $secondHalf = $this->buildReturnFixtures($firstHalf);

        return array_merge($firstHalf, $secondHalf);
    }

    private function buildRounds(array $teamIds): array
    {
        $n        = count($teamIds);
        $fixed    = array_pop($teamIds);
        $rotation = $teamIds;
        $rounds   = [];

        for ($round = 0; $round < $n - 1; $round++) {
            $circle  = array_merge($rotation, [$fixed]);
            $matches = [];

            for ($i = 0; $i < $n / 2; $i++) {
                $home = $circle[$i];
                $away = $circle[$n - 1 - $i];

                if ($home !== null && $away !== null) {
                    $matches[] = [
                        'home_team_id' => $home,
                        'away_team_id' => $away,
                    ];
                }
            }

            $rounds[] = $matches;

            // Rotate: move the last element of the rotation to the front
            array_unshift($rotation, array_pop($rotation));
        }

        return $rounds;
    }

    private function buildReturnFixtures(array $firstHalfRounds): array
    {
        return array_map(function (array $round): array {
            return array_map(function (array $match): array {
                return [
                    'home_team_id' => $match['away_team_id'],
                    'away_team_id' => $match['home_team_id'],
                ];
            }, $round);
        }, $firstHalfRounds);
    }
}
