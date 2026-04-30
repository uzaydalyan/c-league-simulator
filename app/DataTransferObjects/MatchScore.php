<?php

namespace App\DataTransferObjects;

final class MatchScore
{
    public function __construct(
        public readonly int $homeScore,
        public readonly int $awayScore,
    ) {}
}
