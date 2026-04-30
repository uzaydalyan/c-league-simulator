<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface PredictionServiceInterface
{
    /**
     * Calculate the championship probability percentage for every team.
     *
     * @return Collection<int, \App\DataTransferObjects\ChampionshipPrediction>
     */
    public function predict(): Collection;
}
