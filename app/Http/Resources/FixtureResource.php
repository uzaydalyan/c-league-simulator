<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FixtureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'week'       => $this->week,
            'home_team'  => new TeamBasicResource($this->whenLoaded('homeTeam')),
            'away_team'  => new TeamBasicResource($this->whenLoaded('awayTeam')),
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'is_played'  => $this->is_played,
        ];
    }
}
