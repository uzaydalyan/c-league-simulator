<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    protected $fillable = [
        'name',
        'power',
        'played',
        'won',
        'drawn',
        'lost',
        'goals_for',
        'goals_against',
        'points',
    ];

    protected $casts = [
        'power'         => 'integer',
        'played'        => 'integer',
        'won'           => 'integer',
        'drawn'         => 'integer',
        'lost'          => 'integer',
        'goals_for'     => 'integer',
        'goals_against' => 'integer',
        'points'        => 'integer',
    ];

    protected $appends = ['goal_difference'];

    public function getGoalDifferenceAttribute(): int
    {
        return $this->goals_for - $this->goals_against;
    }

    public function homeFixtures(): HasMany
    {
        return $this->hasMany(Fixture::class, 'home_team_id');
    }

    public function awayFixtures(): HasMany
    {
        return $this->hasMany(Fixture::class, 'away_team_id');
    }
}
