<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fixture extends Model
{
    protected $fillable = [
        'week',
        'home_team_id',
        'away_team_id',
        'home_score',
        'away_score',
        'is_played',
    ];

    protected $casts = [
        'week'         => 'integer',
        'home_team_id' => 'integer',
        'away_team_id' => 'integer',
        'home_score'   => 'integer',
        'away_score'   => 'integer',
        'is_played'    => 'boolean',
    ];

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
